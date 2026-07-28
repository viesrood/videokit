<?php

declare(strict_types=1);

namespace viesrood\videokit\helpers;

/**
 * Recognises YouTube and Vimeo video URLs.
 *
 * This is the plugin's allowlist: a URL that does not parse here is never
 * looked up, so no user-supplied URL is ever fetched. The provider takes the
 * resulting id and calls its own hard-coded API base instead.
 *
 * Hosts are compared exactly, never with a substring or an unanchored pattern,
 * so neither `https://evil.example.com/?x=youtube.com/watch?v=ID` nor
 * `https://youtube.com.evil.example/watch?v=ID` is accepted.
 */
final class VideoUrl
{
    public const YOUTUBE = 'youtube';

    public const VIMEO = 'vimeo';

    /** @var string[] */
    public const YOUTUBE_HOSTS = [
        'youtube.com',
        'www.youtube.com',
        'm.youtube.com',
        'music.youtube.com',
        'youtube-nocookie.com',
        'www.youtube-nocookie.com',
        'youtu.be',
        'www.youtu.be',
    ];

    /** @var string[] */
    public const VIMEO_HOSTS = [
        'vimeo.com',
        'www.vimeo.com',
        'player.vimeo.com',
    ];

    /** YouTube ids are always 11 characters from a URL-safe alphabet. */
    private const YOUTUBE_ID_PATTERN = '/^[A-Za-z0-9_-]{11}$/';

    /**
     * `/embed/videoseries?list=...` embeds a playlist, not a video, and
     * "videoseries" happens to be exactly 11 valid characters.
     */
    private const YOUTUBE_RESERVED_IDS = ['videoseries'];

    private const VIMEO_ID_PATTERN = '/^[1-9]\d*$/';

    /** Vimeo unlisted-video hashes are short hex-ish tokens. */
    private const VIMEO_HASH_PATTERN = '/^[A-Za-z0-9]{4,32}$/';

    /**
     * Parse a video URL into its provider, id and (Vimeo only) privacy hash.
     *
     * @return array{provider: string, videoId: string, hash: string|null}|null
     *         Null when the URL is not a recognised video URL.
     */
    public static function parse(string $url): ?array
    {
        $parts = self::split($url);

        if ($parts === null) {
            return null;
        }

        [$host, $segments, $query] = $parts;

        if (in_array($host, self::YOUTUBE_HOSTS, true)) {
            return self::parseYouTube($host, $segments, $query);
        }

        if (in_array($host, self::VIMEO_HOSTS, true)) {
            return self::parseVimeo($host, $segments, $query);
        }

        return null;
    }

    /**
     * The lowercased host of an http(s) URL, or null when the URL is unusable.
     */
    public static function host(string $url): ?string
    {
        $parts = self::split($url);

        return $parts[0] ?? null;
    }

    /**
     * Whether the given string looks like a valid id for the given provider.
     */
    public static function isValidId(string $provider, string $videoId): bool
    {
        return match ($provider) {
            self::YOUTUBE => preg_match(self::YOUTUBE_ID_PATTERN, $videoId) === 1
                && !in_array($videoId, self::YOUTUBE_RESERVED_IDS, true),
            self::VIMEO => preg_match(self::VIMEO_ID_PATTERN, $videoId) === 1,
            default => false,
        };
    }

    /**
     * Split a URL into its host, non-empty path segments and query parameters.
     *
     * @return array{0: string, 1: string[], 2: array<string, mixed>}|null
     */
    private static function split(string $url): ?array
    {
        $url = trim($url);

        // Whitespace inside a URL is always either a typo or an obfuscation
        // attempt; there is no reason to try and repair it.
        if ($url === '' || preg_match('/\s/', $url) === 1) {
            return null;
        }

        if (str_starts_with($url, '//')) {
            $url = 'https:' . $url;
        } elseif (preg_match('#^[a-zA-Z][a-zA-Z0-9+.-]*://#', $url) !== 1) {
            $url = 'https://' . $url;
        }

        $parsed = parse_url($url);

        if ($parsed === false || !isset($parsed['host'])) {
            return null;
        }

        if (!in_array(strtolower($parsed['scheme'] ?? ''), ['http', 'https'], true)) {
            return null;
        }

        // Credentials never appear in a real video link and are a classic way
        // to disguise the actual host, so reject them outright.
        if (isset($parsed['user']) || isset($parsed['pass'])) {
            return null;
        }

        $segments = array_values(array_filter(
            explode('/', $parsed['path'] ?? ''),
            static fn(string $segment): bool => $segment !== '',
        ));

        $query = [];

        if (isset($parsed['query'])) {
            parse_str($parsed['query'], $query);
        }

        return [strtolower($parsed['host']), $segments, $query];
    }

    /**
     * @param string[] $segments
     * @param array<string, mixed> $query
     * @return array{provider: string, videoId: string, hash: string|null}|null
     */
    private static function parseYouTube(string $host, array $segments, array $query): ?array
    {
        $first = $segments[0] ?? null;
        $videoId = null;

        if ($host === 'youtu.be' || $host === 'www.youtu.be') {
            $videoId = $first;
        } elseif ($first === 'watch') {
            $videoId = is_string($query['v'] ?? null) ? $query['v'] : null;
        } elseif (in_array($first, ['embed', 'v', 'shorts', 'live'], true)) {
            $videoId = $segments[1] ?? null;
        }

        if ($videoId === null || !self::isValidId(self::YOUTUBE, $videoId)) {
            return null;
        }

        return [
            'provider' => self::YOUTUBE,
            'videoId' => $videoId,
            'hash' => null,
        ];
    }

    /**
     * @param string[] $segments
     * @param array<string, mixed> $query
     * @return array{provider: string, videoId: string, hash: string|null}|null
     */
    private static function parseVimeo(string $host, array $segments, array $query): ?array
    {
        $first = $segments[0] ?? null;
        $videoId = null;
        $hash = null;

        if ($host === 'player.vimeo.com') {
            if ($first === 'video') {
                $videoId = $segments[1] ?? null;
                $hash = $segments[2] ?? null;
            }
        } elseif ($first === 'video') {
            $videoId = $segments[1] ?? null;
            $hash = $segments[2] ?? null;
        } elseif ($first === 'channels') {
            $videoId = $segments[2] ?? null;
            $hash = $segments[3] ?? null;
        } elseif ($first === 'groups' && ($segments[2] ?? null) === 'videos') {
            $videoId = $segments[3] ?? null;
            $hash = $segments[4] ?? null;
        } elseif ($first === 'album' && ($segments[2] ?? null) === 'video') {
            $videoId = $segments[3] ?? null;
            $hash = $segments[4] ?? null;
        } elseif ($first !== null) {
            $videoId = $first;
            $hash = $segments[1] ?? null;
        }

        if ($videoId === null || !self::isValidId(self::VIMEO, $videoId)) {
            return null;
        }

        if ($hash === null && is_string($query['h'] ?? null)) {
            $hash = $query['h'];
        }

        // A malformed hash is dropped rather than rejecting the whole URL: the
        // video is still valid, it just will not play if it is unlisted.
        if ($hash !== null && preg_match(self::VIMEO_HASH_PATTERN, $hash) !== 1) {
            $hash = null;
        }

        return [
            'provider' => self::VIMEO,
            'videoId' => $videoId,
            'hash' => $hash,
        ];
    }
}
