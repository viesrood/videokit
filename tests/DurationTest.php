<?php

declare(strict_types=1);

namespace viesrood\videokit\tests;

use PHPUnit\Framework\TestCase;
use viesrood\videokit\helpers\Duration;

final class DurationTest extends TestCase
{
    public function testParsesIso8601Durations(): void
    {
        self::assertSame(3723, Duration::fromIso8601('PT1H2M3S'));
        self::assertSame(0, Duration::fromIso8601('PT0S'));
        self::assertSame(59, Duration::fromIso8601('PT59S'));
        self::assertSame(600, Duration::fromIso8601('PT10M'));
        self::assertSame(93600, Duration::fromIso8601('P1DT2H'));
        self::assertSame(604800, Duration::fromIso8601('P1W'));
        self::assertSame(3723, Duration::fromIso8601('  PT1H2M3S  '));
        self::assertSame(3, Duration::fromIso8601('PT3.5S'));
    }

    public function testRejectsUnusableIso8601Durations(): void
    {
        self::assertNull(Duration::fromIso8601(null));
        self::assertNull(Duration::fromIso8601(''));
        self::assertNull(Duration::fromIso8601('P'));
        self::assertNull(Duration::fromIso8601('PT'));
        self::assertNull(Duration::fromIso8601('1H2M3S'));
        self::assertNull(Duration::fromIso8601('nonsense'));
        // Years and months have no fixed length, so they are not supported.
        self::assertNull(Duration::fromIso8601('P1Y'));
        self::assertNull(Duration::fromIso8601('P1M'));
    }

    public function testFormatsSecondsAsAClock(): void
    {
        self::assertSame('0:00', Duration::format(0));
        self::assertSame('0:07', Duration::format(7));
        self::assertSame('1:01', Duration::format(61));
        self::assertSame('59:59', Duration::format(3599));
        self::assertSame('1:00:00', Duration::format(3600));
        self::assertSame('1:01:01', Duration::format(3661));
        self::assertSame('100:00:00', Duration::format(360000));
    }

    public function testFormattingAMissingDurationReturnsAnEmptyString(): void
    {
        // Live streams and premieres have no duration; this must never throw.
        self::assertSame('', Duration::format(null));
        self::assertSame('', Duration::format(-1));
    }

    public function testConvertsSecondsBackToIso8601(): void
    {
        self::assertSame('PT0S', Duration::toIso8601(0));
        self::assertSame('PT3S', Duration::toIso8601(3));
        self::assertSame('PT1M1S', Duration::toIso8601(61));
        self::assertSame('PT1H', Duration::toIso8601(3600));
        self::assertSame('PT1H2M3S', Duration::toIso8601(3723));
        self::assertNull(Duration::toIso8601(null));
        self::assertNull(Duration::toIso8601(-1));
    }

    public function testIso8601RoundTrips(): void
    {
        foreach ([0, 1, 59, 60, 61, 3599, 3600, 3661, 86399] as $seconds) {
            $iso = Duration::toIso8601($seconds);

            self::assertNotNull($iso);
            self::assertSame($seconds, Duration::fromIso8601($iso), $iso);
        }
    }
}
