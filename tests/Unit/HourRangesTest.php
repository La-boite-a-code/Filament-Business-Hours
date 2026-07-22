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
