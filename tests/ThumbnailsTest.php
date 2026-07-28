<?php

declare(strict_types=1);

namespace viesrood\videokit\tests;

use PHPUnit\Framework\TestCase;
use viesrood\videokit\helpers\Thumbnails;

final class ThumbnailsTest extends TestCase
{
    /**
     * @return array<int, array{url: string, width: int, height: int}>
     */
    private static function sample(): array
    {
        return [
            ['url' => 'https://example.com/hq.jpg', 'width' => 480, 'height' => 360],
            ['url' => 'https://example.com/default.jpg', 'width' => 120, 'height' => 90],
            ['url' => 'https://example.com/max.jpg', 'width' => 1280, 'height' => 720],
        ];
    }

    public function testPicksTheClosestWidth(): void
    {
        self::assertSame('https://example.com/default.jpg', Thumbnails::closest(self::sample(), 100));
        self::assertSame('https://example.com/hq.jpg', Thumbnails::closest(self::sample(), 500));
        self::assertSame('https://example.com/max.jpg', Thumbnails::closest(self::sample(), 4000));
    }

    public function testPicksAnExactMatch(): void
    {
        self::assertSame('https://example.com/hq.jpg', Thumbnails::closest(self::sample(), 480));
    }

    public function testPrefersTheLargerCandidateOnATie(): void
    {
        $thumbnails = [
            ['url' => 'https://example.com/small.jpg', 'width' => 400, 'height' => 300],
            ['url' => 'https://example.com/large.jpg', 'width' => 800, 'height' => 600],
        ];

        // 600 is exactly between the two: upscaling looks worse than downscaling.
        self::assertSame('https://example.com/large.jpg', Thumbnails::closest($thumbnails, 600));
    }

    public function testSkipsMalformedEntries(): void
    {
        $thumbnails = [
            'not an array',
            ['width' => 100],
            ['url' => '', 'width' => 200],
            ['url' => '   ', 'width' => 200],
            ['url' => 'https://example.com/ok.jpg'],
        ];

        self::assertSame('https://example.com/ok.jpg', Thumbnails::closest($thumbnails, 600));
        self::assertCount(1, Thumbnails::normalize($thumbnails));
    }

    public function testReturnsNullWithoutUsableThumbnails(): void
    {
        self::assertNull(Thumbnails::closest([], 600));
        self::assertNull(Thumbnails::largest([]));
        self::assertNull(Thumbnails::closestEntry([], 600));
        self::assertNull(Thumbnails::closest(['nonsense'], 600));
    }

    public function testNormalizeSortsByWidthAndFillsInDefaults(): void
    {
        $normalized = Thumbnails::normalize(self::sample());

        self::assertSame([120, 480, 1280], array_column($normalized, 'width'));

        $withoutSize = Thumbnails::normalize([['url' => ' https://example.com/a.jpg ']]);

        self::assertSame([
            ['url' => 'https://example.com/a.jpg', 'width' => 0, 'height' => 0],
        ], $withoutSize);
    }

    public function testReturnsTheWidestThumbnail(): void
    {
        self::assertSame('https://example.com/max.jpg', Thumbnails::largest(self::sample()));
    }

    public function testClosestEntryCarriesTheDimensions(): void
    {
        $entry = Thumbnails::closestEntry(self::sample(), 1280);

        self::assertSame(['url' => 'https://example.com/max.jpg', 'width' => 1280, 'height' => 720], $entry);
    }
}
