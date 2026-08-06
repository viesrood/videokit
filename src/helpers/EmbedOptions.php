<?php

declare(strict_types=1);

namespace viesrood\videokit\helpers;

/**
 * Maps a provider-neutral option vocabulary onto player query parameters.
 *
 * This is an allowlist, not a passthrough: anything the maps below do not
 * recognise is dropped, so a template can never inject arbitrary parameters
 * (or, worse, arbitrary attributes) into a third-party player URL.
 */
final class EmbedOptions
{
    /** Option name => YouTube parameter name, for on/off options. */
    private const YOUTUBE_FLAGS = [
        'autoplay' => 'autoplay',
        'captions' => 'cc_load_policy',
        'controls' => 'controls',
        'loop' => 'loop',
        'muted' => 'mute',
        'playsinline' => 'playsinline',
        'related' => 'rel',
    ];

    /** Option name => YouTube parameter name, for whole-second options. */
    private const YOUTUBE_SECONDS = [
        'end' => 'end',
        'start' => 'start',
    ];

    /** Option name => Vimeo parameter name, for on/off options. */
    private const VIMEO_FLAGS = [
        'autopause' => 'autopause',
        'autoplay' => 'autoplay',
        'byline' => 'byline',
        'controls' => 'controls',
        'loop' => 'loop',
        'muted' => 'muted',
        'playsinline' => 'playsinline',
        'portrait' => 'portrait',
        'pictureInPicture' => 'pip',
        'title' => 'title',
    ];

    /**
     * The player host for a provider. YouTube's no-cookie host still contacts
     * Google once the player loads, but it does not set profiling cookies.
     */
    public static function host(string $provider, bool $noCookie = true): ?string
    {
        return match ($provider) {
            VideoUrl::YOUTUBE => $noCookie
                ? 'https://www.youtube-nocookie.com'
                : 'https://www.youtube.com',
            VideoUrl::VIMEO => 'https://player.vimeo.com',
            default => null,
        };
    }

    /**
     * Player parameters for a provider, sorted by name so the resulting URL is
     * stable (which keeps caches and tests predictable).
     *
     * @param array<string, mixed> $options
     * @return array<string, string>
     */
    public static function forProvider(string $provider, array $options, bool $doNotTrack = true): array
    {
        return match ($provider) {
            VideoUrl::YOUTUBE => self::youtube($options),
            VideoUrl::VIMEO => self::vimeo($options, $doNotTrack),
            default => [],
        };
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, string>
     */
    public static function youtube(array $options): array
    {
        $params = [];

        foreach (self::YOUTUBE_FLAGS as $option => $param) {
            if (array_key_exists($option, $options)) {
                $params[$param] = self::flag($options[$option]);
            }
        }

        foreach (self::YOUTUBE_SECONDS as $option => $param) {
            $seconds = self::seconds($options[$option] ?? null);

            if ($seconds !== null) {
                $params[$param] = (string)$seconds;
            }
        }

        $language = self::language($options['language'] ?? null);

        if ($language !== null) {
            $params['hl'] = $language;
        }

        ksort($params);

        return $params;
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, string>
     */
    public static function vimeo(array $options, bool $doNotTrack = true): array
    {
        $params = [];

        foreach (self::VIMEO_FLAGS as $option => $param) {
            if (array_key_exists($option, $options)) {
                $params[$param] = self::flag($options[$option]);
            }
        }

        $color = self::color($options['color'] ?? null);

        if ($color !== null) {
            $params['color'] = $color;
        }

        $language = self::language($options['language'] ?? null);

        if ($language !== null) {
            $params['texttrack'] = $language;
        }

        if ($doNotTrack) {
            $params['dnt'] = '1';
        }

        ksort($params);

        return $params;
    }

    private static function flag(mixed $value): string
    {
        return $value ? '1' : '0';
    }

    private static function seconds(mixed $value): ?int
    {
        if (!is_int($value) && !(is_string($value) && preg_match('/^\d+$/', $value) === 1)) {
            return null;
        }

        $seconds = (int)$value;

        return $seconds >= 0 ? $seconds : null;
    }

    private static function color(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $color = ltrim(trim($value), '#');

        return preg_match('/^[0-9a-fA-F]{6}$/', $color) === 1 ? strtolower($color) : null;
    }

    private static function language(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $language = trim($value);

        return preg_match('/^[a-zA-Z]{2}(-[a-zA-Z]{2,4})?$/', $language) === 1 ? $language : null;
    }
}
