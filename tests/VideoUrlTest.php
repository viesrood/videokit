<?php

declare(strict_types=1);

namespace viesrood\videokit\tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use viesrood\videokit\helpers\VideoUrl;

final class VideoUrlTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: string, 2: string|null}>
     */
    public static function youtubeUrls(): array
    {
        return [
            'short link' => ['https://youtu.be/dQw4w9WgXcQ', 'dQw4w9WgXcQ', null],
            'watch page' => ['https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'dQw4w9WgXcQ', null],
            'watch page with timestamp' => ['https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=30s', 'dQw4w9WgXcQ', null],
            'watch page without www' => ['https://youtube.com/watch?v=dQw4w9WgXcQ', 'dQw4w9WgXcQ', null],
            'mobile' => ['https://m.youtube.com/watch?v=dQw4w9WgXcQ', 'dQw4w9WgXcQ', null],
            'music' => ['https://music.youtube.com/watch?v=dQw4w9WgXcQ', 'dQw4w9WgXcQ', null],
            'embed' => ['https://www.youtube.com/embed/dQw4w9WgXcQ', 'dQw4w9WgXcQ', null],
            'no-cookie embed' => ['https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ', 'dQw4w9WgXcQ', null],
            'legacy v path' => ['https://www.youtube.com/v/dQw4w9WgXcQ', 'dQw4w9WgXcQ', null],
            'shorts' => ['https://www.youtube.com/shorts/dQw4w9WgXcQ', 'dQw4w9WgXcQ', null],
            'live' => ['https://www.youtube.com/live/dQw4w9WgXcQ', 'dQw4w9WgXcQ', null],
            'http' => ['http://youtu.be/dQw4w9WgXcQ', 'dQw4w9WgXcQ', null],
            'without scheme' => ['youtu.be/dQw4w9WgXcQ', 'dQw4w9WgXcQ', null],
            'protocol relative' => ['//youtu.be/dQw4w9WgXcQ', 'dQw4w9WgXcQ', null],
            'surrounding whitespace' => ['  https://youtu.be/dQw4w9WgXcQ  ', 'dQw4w9WgXcQ', null],
            'uppercase host' => ['https://WWW.YouTube.com/watch?v=dQw4w9WgXcQ', 'dQw4w9WgXcQ', null],
            'id with dash and underscore' => ['https://youtu.be/a_B-cD1eF2g', 'a_B-cD1eF2g', null],
            'short link with query' => ['https://youtu.be/dQw4w9WgXcQ?si=abc123', 'dQw4w9WgXcQ', null],
        ];
    }

    #[DataProvider('youtubeUrls')]
    public function testParsesYouTubeUrls(string $url, string $expectedId, ?string $expectedHash): void
    {
        $parsed = VideoUrl::parse($url);

        self::assertNotNull($parsed, $url);
        self::assertSame(VideoUrl::YOUTUBE, $parsed['provider']);
        self::assertSame($expectedId, $parsed['videoId']);
        self::assertSame($expectedHash, $parsed['hash']);
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string|null}>
     */
    public static function vimeoUrls(): array
    {
        return [
            'plain' => ['https://vimeo.com/76979871', '76979871', null],
            'without www' => ['http://vimeo.com/76979871', '76979871', null],
            'with www' => ['https://www.vimeo.com/76979871', '76979871', null],
            'unlisted hash segment' => ['https://vimeo.com/76979871/abc123def0', '76979871', 'abc123def0'],
            'unlisted hash query' => ['https://vimeo.com/76979871?h=abc123def0', '76979871', 'abc123def0'],
            'player' => ['https://player.vimeo.com/video/76979871', '76979871', null],
            'player with hash query' => ['https://player.vimeo.com/video/76979871?h=abc123def0', '76979871', 'abc123def0'],
            'player with hash segment' => ['https://player.vimeo.com/video/76979871/abc123def0', '76979871', 'abc123def0'],
            'video path' => ['https://vimeo.com/video/76979871', '76979871', null],
            'channel' => ['https://vimeo.com/channels/staffpicks/76979871', '76979871', null],
            'group' => ['https://vimeo.com/groups/motion/videos/76979871', '76979871', null],
            'album' => ['https://vimeo.com/album/2222222/video/76979871', '76979871', null],
            'trailing slash' => ['https://vimeo.com/76979871/', '76979871', null],
        ];
    }

    #[DataProvider('vimeoUrls')]
    public function testParsesVimeoUrls(string $url, string $expectedId, ?string $expectedHash): void
    {
        $parsed = VideoUrl::parse($url);

        self::assertNotNull($parsed, $url);
        self::assertSame(VideoUrl::VIMEO, $parsed['provider']);
        self::assertSame($expectedId, $parsed['videoId']);
        self::assertSame($expectedHash, $parsed['hash']);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function rejectedUrls(): array
    {
        return [
            'empty' => [''],
            'whitespace only' => ['   '],
            'not a url' => ['just some text'],
            'unknown host' => ['https://example.com/watch?v=dQw4w9WgXcQ'],
            'host as query parameter' => ['https://evil.example.com/?x=youtube.com/watch?v=dQw4w9WgXcQ'],
            'host as path' => ['https://evil.example.com/youtube.com/watch?v=dQw4w9WgXcQ'],
            'suffixed host' => ['https://youtube.com.evil.example/watch?v=dQw4w9WgXcQ'],
            'prefixed host' => ['https://notyoutube.com/watch?v=dQw4w9WgXcQ'],
            'subdomain of an allowed host' => ['https://evil.youtube.com.example/watch?v=dQw4w9WgXcQ'],
            'credentials in url' => ['https://user:pass@www.youtube.com/watch?v=dQw4w9WgXcQ'],
            'ftp scheme' => ['ftp://youtu.be/dQw4w9WgXcQ'],
            'javascript scheme' => ['javascript:alert(1)'],
            'file scheme' => ['file:///etc/passwd'],
            'localhost' => ['http://127.0.0.1:8080/'],
            'whitespace inside' => ['https://youtu.be/dQw4w 9WgXcQ'],
            'youtube id too short' => ['https://youtu.be/abc123'],
            'youtube id too long' => ['https://youtu.be/dQw4w9WgXcQextra'],
            'youtube watch without v' => ['https://www.youtube.com/watch?list=PL123'],
            'youtube playlist embed' => ['https://www.youtube.com/embed/videoseries?list=PL123'],
            'youtube channel page' => ['https://www.youtube.com/@someone'],
            'vimeo user page' => ['https://vimeo.com/someuser'],
            'vimeo zero id' => ['https://vimeo.com/0'],
            'vimeo root' => ['https://vimeo.com/'],
        ];
    }

    #[DataProvider('rejectedUrls')]
    public function testRejectsEverythingElse(string $url): void
    {
        self::assertNull(VideoUrl::parse($url), $url);
    }

    public function testIgnoresAMalformedHashButKeepsTheVideo(): void
    {
        $parsed = VideoUrl::parse('https://vimeo.com/76979871?h=' . str_repeat('a', 64));

        self::assertNotNull($parsed);
        self::assertSame('76979871', $parsed['videoId']);
        self::assertNull($parsed['hash']);
    }

    public function testReturnsTheLowercasedHost(): void
    {
        self::assertSame('www.youtube.com', VideoUrl::host('https://WWW.YouTube.com/watch?v=dQw4w9WgXcQ'));
        self::assertNull(VideoUrl::host('not a url at all'));
    }

    public function testValidatesIdsPerProvider(): void
    {
        self::assertTrue(VideoUrl::isValidId(VideoUrl::YOUTUBE, 'dQw4w9WgXcQ'));
        self::assertFalse(VideoUrl::isValidId(VideoUrl::YOUTUBE, 'abc'));
        self::assertTrue(VideoUrl::isValidId(VideoUrl::VIMEO, '76979871'));
        self::assertFalse(VideoUrl::isValidId(VideoUrl::VIMEO, 'abc'));
        self::assertFalse(VideoUrl::isValidId('dailymotion', '123'));
    }
}
