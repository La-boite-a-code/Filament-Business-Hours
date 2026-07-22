<?php

declare(strict_types=1);

namespace LaBoiteACode\FilamentBusinessHours\Support;

final class Weekdays
{
    /** @var list<string> */
    public const DAYS = [
        'monday',
        'tuesday',
        'wednesday',
        'thursday',
        'friday',
        'saturday',
        'sunday',
    ];

    /**
     * The days of the week in display order, starting from the given day.
     * Unknown day names fall back to a monday-first week.
     *
     * @return list<string>
     */
    public static function startingFrom(string $firstDay): array
    {
        $index = array_search(strtolower($firstDay), self::DAYS, true);

        if ($index === false) {
            $index = 0;
        }

        return array_merge(
            array_slice(self::DAYS, $index),
            array_slice(self::DAYS, 0, $index),
        );
    }
}
