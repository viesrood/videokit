<?php

declare(strict_types=1);

namespace viesrood\videokit\tests;

use PHPUnit\Framework\TestCase;
use viesrood\videokit\helpers\Facade;

final class FacadeTest extends TestCase
{
    public function testRendersAClickToLoadPoster(): void
    {
        $html = Facade::render([
            'embedUrl' => 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?autoplay=1',
            'thumbnail' => 'https://example.com/poster.jpg',
            'thumbnailWidth' => 1280,
            'thumbnailHeight' => 720,
            'title' => 'A video',
            'watchUrl' => 'https://youtu.be/dQw4w9WgXcQ',
        ]);

        self::assertStringContainsString('data-videokit-facade', $html);
        self::assertStringContainsString(
            'data-embed-url="https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?autoplay=1"',
            $html,
        );
        self::assertStringContainsString('src="https://example.com/poster.jpg"', $html);
        self::assertStringContainsString('width="1280"', $html);
        self::assertStringContainsString('height="720"', $html);
        self::assertStringContainsString('type="button"', $html);
        self::assertStringContainsString('--vk-ratio: 16 / 9;', $html);
        self::assertStringContainsString('<noscript>', $html);
        self::assertStringContainsString('href="https://youtu.be/dQw4w9WgXcQ"', $html);
    }

    public function testNothingIsRequestedFromTheProviderUpFront(): void
    {
        $html = Facade::render([
            'embedUrl' => 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ',
            'thumbnail' => 'https://example.com/poster.jpg',
            'watchUrl' => 'https://youtu.be/dQw4w9WgXcQ',
        ]);

        // The player URL may only appear as data, never as a live iframe or script.
        self::assertStringNotContainsString('<iframe', $html);
        self::assertStringNotContainsString('<script', $html);
        self::assertStringNotContainsString('src="https://www.youtube-nocookie.com', $html);
    }

    public function testEscapesAHostileTitle(): void
    {
        $html = Facade::render([
            'embedUrl' => 'https://player.vimeo.com/video/76979871',
            'title' => '"><script>alert(1)</script>',
            'playLabel' => 'Play video: "><script>alert(1)</script>',
            'showTitle' => true,
        ]);

        self::assertStringNotContainsString('<script>', $html);
        self::assertStringContainsString('&lt;script&gt;', $html);
        self::assertStringContainsString('&quot;&gt;', $html);
    }

    public function testRejectsARatioThatCouldInjectStyles(): void
    {
        $html = Facade::render([
            'embedUrl' => 'https://player.vimeo.com/video/76979871',
            'ratio' => '1; background: url(https://evil.example.com)',
        ]);

        self::assertStringContainsString('--vk-ratio: 16 / 9;', $html);
        self::assertStringNotContainsString('evil.example.com', $html);
    }

    public function testAcceptsACustomRatioAndClass(): void
    {
        $html = Facade::render([
            'embedUrl' => 'https://player.vimeo.com/video/76979871',
            'ratio' => '4 / 3',
            'class' => 'my-8 rounded',
        ]);

        self::assertStringContainsString('--vk-ratio: 4 / 3;', $html);
        self::assertStringContainsString('class="vk-facade my-8 rounded"', $html);
    }

    public function testWorksWithoutAThumbnailOrWatchUrl(): void
    {
        $html = Facade::render(['embedUrl' => 'https://player.vimeo.com/video/76979871']);

        self::assertStringContainsString('vk-facade__play', $html);
        self::assertStringNotContainsString('<img', $html);
        self::assertStringNotContainsString('<noscript>', $html);
    }

    public function testRendersNothingWithoutAnEmbedUrl(): void
    {
        self::assertSame('', Facade::render(['embedUrl' => '   ']));
    }
}
