<?php

declare(strict_types=1);

namespace viesrood\videokit\tests;

use Craft;
use PHPUnit\Framework\TestCase;
use viesrood\videokit\models\Video;
use yii\console\Application;

final class VideoEmbedHtmlTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        if (Craft::$app === null) {
            Craft::$app = new Application([
                'id' => 'videokit-tests',
                'basePath' => dirname(__DIR__),
            ]);
        }
    }

    public function testAllowsSafeIframeOverridesAndKeepsThePlayerUrlFixed(): void
    {
        $video = new class extends Video {
            public function getEmbedUrl(array $options = []): string
            {
                return 'https://player.vimeo.com/video/76979871?dnt=1';
            }
        };
        $video->title = 'Stored title';

        $html = (string)$video->getEmbedHtml([
            'class' => 'legacy-class',
            'attributes' => [
                'class' => 'hero-video',
                'title' => 'Main title',
                'loading' => 'eager',
                'sandbox' => 'allow-scripts allow-same-origin',
                'allowfullscreen' => false,
                'src' => 'https://evil.example.com',
                'style' => 'display:none',
                'onload' => 'alert(1)',
            ],
        ]);

        self::assertStringContainsString('src="https://player.vimeo.com/video/76979871?dnt=1"', $html);
        self::assertStringContainsString('class="hero-video"', $html);
        self::assertStringContainsString('title="Main title"', $html);
        self::assertStringContainsString('loading="eager"', $html);
        self::assertStringContainsString('sandbox="allow-scripts allow-same-origin"', $html);
        self::assertStringNotContainsString('evil.example.com', $html);
        self::assertStringNotContainsString('style=', $html);
        self::assertStringNotContainsString('onload=', $html);
        self::assertStringNotContainsString('allowfullscreen', $html);
    }

    public function testEscapesCustomAttributes(): void
    {
        $video = new class extends Video {
            public function getEmbedUrl(array $options = []): string
            {
                return 'https://player.vimeo.com/video/76979871';
            }
        };
        $video->title = 'Stored title';

        $html = (string)$video->getEmbedHtml([
            'attributes' => ['title' => '\"><script>alert(1)</script>'],
        ]);

        self::assertStringNotContainsString('<script>', $html);
        self::assertStringContainsString('&lt;script&gt;', $html);
    }
}
