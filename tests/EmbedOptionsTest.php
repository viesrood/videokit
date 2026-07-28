<?php

declare(strict_types=1);

namespace viesrood\videokit\tests;

use PHPUnit\Framework\TestCase;
use viesrood\videokit\helpers\EmbedOptions;
use viesrood\videokit\helpers\VideoUrl;

final class EmbedOptionsTest extends TestCase
{
    public function testMapsYouTubeOptions(): void
    {
        $params = EmbedOptions::youtube([
            'autoplay' => true,
            'muted' => true,
            'controls' => false,
            'related' => false,
            'start' => 30,
            'language' => 'nl',
        ]);

        self::assertSame([
            'autoplay' => '1',
            'controls' => '0',
            'hl' => 'nl',
            'mute' => '1',
            'rel' => '0',
            'start' => '30',
        ], $params);
    }

    public function testMapsVimeoOptions(): void
    {
        $params = EmbedOptions::vimeo([
            'autoplay' => true,
            'title' => false,
            'byline' => false,
            'color' => '#FF8800',
        ]);

        self::assertSame([
            'autoplay' => '1',
            'byline' => '0',
            'color' => 'ff8800',
            'dnt' => '1',
            'title' => '0',
        ], $params);
    }

    public function testDropsUnknownOptions(): void
    {
        $params = EmbedOptions::youtube([
            'autoplay' => true,
            'onload' => 'alert(1)',
            'src' => 'https://evil.example.com',
            'enablejsapi' => 1,
            'origin' => 'https://evil.example.com',
        ]);

        self::assertSame(['autoplay' => '1'], $params);
    }

    public function testDropsInvalidValues(): void
    {
        self::assertSame([], EmbedOptions::youtube(['start' => 'thirty']));
        self::assertSame([], EmbedOptions::youtube(['start' => -5]));
        self::assertSame([], EmbedOptions::youtube(['language' => 'not-a-language-tag']));
        self::assertSame(['dnt' => '1'], EmbedOptions::vimeo(['color' => 'red']));
        self::assertSame(['dnt' => '1'], EmbedOptions::vimeo(['color' => '#12345']));
    }

    public function testDoNotTrackCanBeTurnedOff(): void
    {
        self::assertArrayNotHasKey('dnt', EmbedOptions::vimeo([], false));
        self::assertSame('1', EmbedOptions::vimeo([])['dnt']);
    }

    public function testEmptyOptionsProduceNoYouTubeParams(): void
    {
        self::assertSame([], EmbedOptions::youtube([]));
    }

    public function testSelectsTheHostPerProvider(): void
    {
        self::assertSame('https://www.youtube-nocookie.com', EmbedOptions::host(VideoUrl::YOUTUBE));
        self::assertSame('https://www.youtube.com', EmbedOptions::host(VideoUrl::YOUTUBE, false));
        self::assertSame('https://player.vimeo.com', EmbedOptions::host(VideoUrl::VIMEO));
        self::assertSame('https://player.vimeo.com', EmbedOptions::host(VideoUrl::VIMEO, false));
        self::assertNull(EmbedOptions::host('dailymotion'));
    }

    public function testForProviderDispatchesToTheRightMap(): void
    {
        self::assertSame(['mute' => '1'], EmbedOptions::forProvider(VideoUrl::YOUTUBE, ['muted' => true]));
        self::assertSame(
            ['dnt' => '1', 'muted' => '1'],
            EmbedOptions::forProvider(VideoUrl::VIMEO, ['muted' => true]),
        );
        self::assertSame([], EmbedOptions::forProvider('dailymotion', ['muted' => true]));
    }
}
