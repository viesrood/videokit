<?php

declare(strict_types=1);

namespace viesrood\videokit\helpers;

/**
 * Converts between video durations in seconds, the ISO 8601 form the YouTube
 * API returns, and a human-readable clock string.
 *
 * Every method accepts and returns nullable values on purpose: live streams,
 * premieres and some Vimeo events have no duration at all, and a missing
 * duration must never turn into a TypeError halfway down a template.
 */
final class Duration
{
    /**
     * Parse an ISO 8601 duration such as `PT1H2M3S` into seconds.
     *
     * Years and months are deliberately unsupported: their length is not fixed,
     * and no video duration ever uses them.
     */
    public static function fromIso8601(?string $iso): ?int
    {
        if ($iso === null) {
            return null;
        }

        $iso = trim($iso);

        if ($iso === '') {
            return null;
        }

        $pattern = '/^P(?:(\d+)W)?(?:(\d+)D)?(?:T(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)(?:\.\d+)?S)?)?$/';

        if (preg_match($pattern, $iso, $matches) !== 1) {
            return null;
        }

        $weeks = $matches[1] ?? '';
        $days = $matches[2] ?? '';
        $hours = $matches[3] ?? '';
        $minutes = $matches[4] ?? '';
        $seconds = $matches[5] ?? '';

        // "P" and "PT" match the pattern but carry no value at all.
        if ($weeks === '' && $days === '' && $hours === '' && $minutes === '' && $seconds === '') {
            return null;
        }

        return (int)$weeks * 604800
            + (int)$days * 86400
            + (int)$hours * 3600
            + (int)$minutes * 60
            + (int)$seconds;
    }

    /**
     * Format seconds as an ISO 8601 duration, for schema.org markup.
     */
    public static function toIso8601(?int $seconds): ?string
    {
        if ($seconds === null || $seconds < 0) {
            return null;
        }

        if ($seconds === 0) {
            return 'PT0S';
        }

        $iso = 'PT';

        if ($hours = intdiv($seconds, 3600)) {
            $iso .= $hours . 'H';
        }

        if ($minutes = intdiv($seconds % 3600, 60)) {
            $iso .= $minutes . 'M';
        }

        if ($remainder = $seconds % 60) {
            $iso .= $remainder . 'S';
        }

        return $iso;
    }

    /**
     * Format seconds as a clock string: `1:01` under an hour, `1:01:01` above.
     * Returns an empty string when there is no duration to show.
     */
    public static function format(?int $seconds): string
    {
        if ($seconds === null || $seconds < 0) {
            return '';
        }

        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $remainder = $seconds % 60;

        if ($hours > 0) {
            return sprintf('%d:%02d:%02d', $hours, $minutes, $remainder);
        }

        return sprintf('%d:%02d', $minutes, $remainder);
    }
}
