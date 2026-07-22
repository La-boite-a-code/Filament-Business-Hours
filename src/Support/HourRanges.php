<?php

declare(strict_types=1);

namespace LaBoiteACode\FilamentBusinessHours\Support;

use InvalidArgumentException;

/**
 * Normalizes lists of "HH:MM-HH:MM" ranges (the format shared by the grid,
 * the database and spatie/opening-hours): validates the format, sorts
 * chronologically and merges overlapping or touching ranges.
 *
 * spatie/opening-hours ships its own mergeOverlappingRanges(), but it returns
 * TimeRange objects (not JSON-serializable strings) and trips over the
 * "timezone" key, so the package normalizes with this helper instead.
 */
final class HourRanges
{
    private const PATTERN = '/^(?:[01]\d|2[0-3]):[0-5]\d-(?:(?:[01]\d|2[0-3]):[0-5]\d|24:00)$/';

    /**
     * @param  array<mixed>  $ranges
     * @param  bool  $allowOverflow  keep ranges ending past midnight ("20:00-02:00") verbatim instead of rejecting them
     * @return list<string>
     */
    public static function normalize(array $ranges, bool $allowOverflow = false): array
    {
        $sameDay = [];
        $overnight = [];

        foreach ($ranges as $range) {
            if (! is_string($range)) {
                throw new InvalidArgumentException(
                    sprintf('Opening hour ranges must be "HH:MM-HH:MM" strings, %s given.', get_debug_type($range)),
                );
            }

            $range = trim($range);

            if (preg_match(self::PATTERN, $range) !== 1) {
                throw new InvalidArgumentException(
                    sprintf('"%s" is not a valid "HH:MM-HH:MM" opening hour range.', $range),
                );
            }

            [$from, $until] = explode('-', $range);
            $start = self::minutes($from);
            $end = self::minutes($until);

            if ($start === $end) {
                throw new InvalidArgumentException(sprintf('"%s" is an empty range.', $range));
            }

            if ($end < $start) {
                if (! $allowOverflow) {
                    throw new InvalidArgumentException(
                        sprintf('"%s" ends before it starts. Enable overflow to allow ranges past midnight.', $range),
                    );
                }

                // Overnight ranges cannot be merged with same-day ones; they are
                // kept verbatim (and deduplicated through the array key).
                $overnight[$range] = $start;

                continue;
            }

            $sameDay[] = [$start, $end];
        }

        usort($sameDay, fn (array $a, array $b): int => $a[0] <=> $b[0] ?: $a[1] <=> $b[1]);

        $merged = [];

        foreach ($sameDay as [$start, $end]) {
            $last = array_key_last($merged);

            if ($last !== null && $start <= $merged[$last][1]) {
                $merged[$last][1] = max($merged[$last][1], $end);

                continue;
            }

            $merged[] = [$start, $end];
        }

        asort($overnight);

        return array_merge(
            array_map(fn (array $range): string => self::time($range[0]).'-'.self::time($range[1]), $merged),
            array_keys($overnight),
        );
    }

    /**
     * "09:30" => 570. "24:00" => 1440.
     */
    public static function minutes(string $time): int
    {
        [$hours, $minutes] = explode(':', $time);

        return ((int) $hours) * 60 + (int) $minutes;
    }

    /**
     * 570 => "09:30". 1440 => "24:00".
     */
    public static function time(int $minutes): string
    {
        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }
}
