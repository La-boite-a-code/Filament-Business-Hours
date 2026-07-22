<?php

declare(strict_types=1);

use LaBoiteACode\FilamentBusinessHours\Support\HourRanges;

it('sorts ranges chronologically', function (): void {
    expect(HourRanges::normalize(['14:00-18:00', '09:00-12:00']))
        ->toBe(['09:00-12:00', '14:00-18:00']);
});

it('merges overlapping and adjacent ranges', function (): void {
    expect(HourRanges::normalize(['14:00-18:00', '09:00-12:30', '12:00-14:00']))
        ->toBe(['09:00-18:00']);
});

it('keeps disjoint ranges apart', function (): void {
    expect(HourRanges::normalize(['09:00-12:00', '14:00-18:00']))
        ->toBe(['09:00-12:00', '14:00-18:00']);
});

it('deduplicates identical ranges', function (): void {
    expect(HourRanges::normalize(['09:00-12:00', '09:00-12:00']))
        ->toBe(['09:00-12:00']);
});

it('supports 24:00 as an end of range', function (): void {
    expect(HourRanges::normalize(['20:00-24:00', '00:00-06:00']))
        ->toBe(['00:00-06:00', '20:00-24:00']);
});

it('returns an empty list for no ranges', function (): void {
    expect(HourRanges::normalize([]))->toBe([]);
});

it('rejects malformed ranges', function (string $range): void {
    HourRanges::normalize([$range]);
})->throws(InvalidArgumentException::class)->with([
    '9:00-12:00',
    '09:00',
    '09h00-12h00',
    'abc',
    '09:00-25:00',
    '24:00-24:30',
]);

it('rejects reversed ranges by default', function (): void {
    HourRanges::normalize(['12:00-09:00']);
})->throws(InvalidArgumentException::class);

it('rejects zero-length ranges', function (): void {
    HourRanges::normalize(['09:00-09:00']);
})->throws(InvalidArgumentException::class);

it('rejects non-string items', function (): void {
    HourRanges::normalize([['09:00', '12:00']]);
})->throws(InvalidArgumentException::class);

it('keeps overnight ranges verbatim when overflow is allowed', function (): void {
    expect(HourRanges::normalize(['22:00-02:00', '09:00-12:00'], allowOverflow: true))
        ->toBe(['09:00-12:00', '22:00-02:00']);
});

it('accepts a full day range', function (): void {
    expect(HourRanges::normalize(['00:00-24:00']))->toBe(['00:00-24:00']);
});

it('accepts the last quarter of the day', function (): void {
    expect(HourRanges::normalize(['23:45-24:00']))->toBe(['23:45-24:00']);
});

it('trims surrounding whitespace', function (): void {
    expect(HourRanges::normalize(['  09:00-12:00  ']))->toBe(['09:00-12:00']);
});

it('absorbs ranges fully contained in another', function (): void {
    expect(HourRanges::normalize(['09:00-18:00', '10:00-11:00']))->toBe(['09:00-18:00']);
});

it('deduplicates identical overnight ranges', function (): void {
    expect(HourRanges::normalize(['22:00-02:00', '22:00-02:00'], allowOverflow: true))
        ->toBe(['22:00-02:00']);
});

it('converts between minutes and times, including midnight as 24:00', function (): void {
    expect(HourRanges::minutes('00:00'))->toBe(0)
        ->and(HourRanges::minutes('09:30'))->toBe(570)
        ->and(HourRanges::minutes('24:00'))->toBe(1440)
        ->and(HourRanges::time(0))->toBe('00:00')
        ->and(HourRanges::time(570))->toBe('09:30')
        ->and(HourRanges::time(1440))->toBe('24:00');
});
