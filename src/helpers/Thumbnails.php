<?php

declare(strict_types=1);

namespace viesrood\videokit\helpers;

/**
 * Picks a poster image out of the thumbnail list a provider returns.
 *
 * Both providers hand back a handful of fixed sizes rather than an arbitrary
 * transform endpoint, so the best we can do is pick the closest one and prefer
 * the larger candidate on a tie (upscaling a poster looks worse than
 * downscaling it).
 */
final class Thumbnails
{
    /**
     * Normalise a raw thumbnail list into `{url, width, height}` entries,
     * sorted by width, with unusable entries dropped.
     *
     * @param array<int|string, mixed> $thumbnails
     * @return array<int, array{url: string, width: int, height: int}>
     */
    public static function normalize(array $thumbnails): array
    {
        $normalized = [];

        foreach ($thumbnails as $thumbnail) {
            if (!is_array($thumbnail)) {
                continue;
            }

            // YouTube uses `url`; Vimeo's `pictures.sizes` uses `link`.
            $url = $thumbnail['url'] ?? $thumbnail['link'] ?? null;

            if (!is_string($url) || trim($url) === '') {
                continue;
            }

            $normalized[] = [
                'url' => trim($url),
                'width' => max(0, (int)($thumbnail['width'] ?? 0)),
                'height' => max(0, (int)($thumbnail['height'] ?? 0)),
            ];
        }

        usort($normalized, static fn(array $a, array $b): int => $a['width'] <=> $b['width']);

        return $normalized;
    }

    /**
     * The URL of the thumbnail closest to the requested width.
     *
     * @param array<int|string, mixed> $thumbnails
     */
    public static function closest(array $thumbnails, int $width = 600): ?string
    {
        $best = self::closestEntry($thumbnails, $width);

        return $best['url'] ?? null;
    }

    /**
     * The full thumbnail entry closest to the requested width, so callers that
     * need width and height for an `<img>` tag do not have to look it up twice.
     *
     * @param array<int|string, mixed> $thumbnails
     * @return array{url: string, width: int, height: int}|null
     */
    public static function closestEntry(array $thumbnails, int $width = 600): ?array
    {
        $normalized = self::normalize($thumbnails);

        if ($normalized === []) {
            return null;
        }

        $best = null;
        $bestDistance = null;

        foreach ($normalized as $thumbnail) {
            $distance = abs($thumbnail['width'] - $width);

            // The list is sorted ascending, so ">=" makes the larger candidate
            // win a tie.
            if ($bestDistance === null || $distance <= $bestDistance) {
                $best = $thumbnail;
                $bestDistance = $distance;
            }
        }

        return $best;
    }

    /**
     * The URL of the widest available thumbnail.
     *
     * @param array<int|string, mixed> $thumbnails
     */
    public static function largest(array $thumbnails): ?string
    {
        $normalized = self::normalize($thumbnails);

        if ($normalized === []) {
            return null;
        }

        return $normalized[count($normalized) - 1]['url'];
    }
}
