<?php

declare(strict_types=1);

use LaBoiteACode\FilamentBusinessHours\Support\ExceptionEntry;
use LaBoiteACode\FilamentBusinessHours\Support\ExceptionType;

it('parses a closed single date', function (): void {
    $entry = ExceptionEntry::tryFromSpatie('2026-12-25', []);

    expect($entry)->not->toBeNull()
        ->and($entry->type)->toBe(ExceptionType::Date)
        ->and($entry->date)->toBe('2026-12-25')
        ->and($entry->isClosed())->toBeTrue()
        ->and($entry->label)->toBeNull();
});

it('parses a single date with special hours and a label', function (): void {
    $entry = ExceptionEntry::tryFromSpatie('2026-12-24', [
        'hours' => ['10:00-16:00'],
        'data' => 'Christmas Eve',
    ]);

    expect($entry->type)->toBe(ExceptionType::Date)
        ->and($entry->hours)->toBe(['10:00-16:00'])
        ->and($entry->label)->toBe('Christmas Eve')
        ->and($entry->isClosed())->toBeFalse();
});

it('parses a plain list of hours', function (): void {
    $entry = ExceptionEntry::tryFromSpatie('2026-06-01', ['09:00-12:00', '14:00-17:00']);

    expect($entry->hours)->toBe(['09:00-12:00', '14:00-17:00'])
        ->and($entry->label)->toBeNull();
});

it('parses a yearly recurring date', function (): void {
    $entry = ExceptionEntry::tryFromSpatie('12-25', []);

    expect($entry->type)->toBe(ExceptionType::Recurring)
        ->and($entry->date)->toBe('12-25');
});

it('parses a period', function (): void {
    $entry = ExceptionEntry::tryFromSpatie('2026-08-01 to 2026-08-15', [
        'hours' => [],
        'data' => 'Summer break',
    ]);

    expect($entry->type)->toBe(ExceptionType::Period)
        ->and($entry->from)->toBe('2026-08-01')
        ->and($entry->until)->toBe('2026-08-15')
        ->and($entry->label)->toBe('Summer break')
        ->and($entry->isClosed())->toBeTrue();
});

it('refuses keys and values it cannot represent', function (string $key, mixed $value): void {
    expect(ExceptionEntry::tryFromSpatie($key, $value))->toBeNull();
})->with([
    'weekday key' => ['friday', []],
    'dash period key' => ['12-24 to 12-26', []],
    'garbage key' => ['not a date', []],
    'structured data payload' => ['2026-12-25', ['hours' => [], 'data' => ['complex' => true]]],
]);

it('serializes back to the exact spatie shape', function (): void {
    expect(ExceptionEntry::date('2026-12-25')->toSpatie())
        ->toBe(['2026-12-25' => []]);

    expect(ExceptionEntry::date('2026-12-24', ['10:00-16:00'])->toSpatie())
        ->toBe(['2026-12-24' => ['10:00-16:00']]);

    expect(ExceptionEntry::date('2026-12-25', [], 'Christmas')->toSpatie())
        ->toBe(['2026-12-25' => ['hours' => [], 'data' => 'Christmas']]);

    expect(ExceptionEntry::recurring('12-25', [], 'Christmas')->toSpatie())
        ->toBe(['12-25' => ['hours' => [], 'data' => 'Christmas']]);

    expect(ExceptionEntry::period('2026-08-01', '2026-08-15', 'Summer break')->toSpatie())
        ->toBe(['2026-08-01 to 2026-08-15' => ['hours' => [], 'data' => 'Summer break']]);

    expect(ExceptionEntry::period('2026-08-01', '2026-08-15')->toSpatie())
        ->toBe(['2026-08-01 to 2026-08-15' => []]);
});

it('round-trips through the spatie shape', function (): void {
    $original = ExceptionEntry::date('2026-12-24', ['10:00-16:00'], 'Christmas Eve');

    [$key, $value] = [array_key_first($original->toSpatie()), $original->toSpatie()[array_key_first($original->toSpatie())]];

    expect(ExceptionEntry::tryFromSpatie($key, $value))->toEqual($original);
});

it('partitions a spatie exceptions map into entries and raw leftovers', function (): void {
    $map = [
        '2026-12-25' => [],
        '2026-08-01 to 2026-08-15' => ['hours' => [], 'data' => 'Summer break'],
        'friday' => ['09:00-12:00'],
    ];

    ['entries' => $entries, 'raw' => $raw] = ExceptionEntry::parseMap($map);

    expect($entries)->toHaveCount(2)
        ->and($entries[0]->date)->toBe('2026-12-25')
        ->and($entries[1]->type)->toBe(ExceptionType::Period)
        ->and($raw)->toBe(['friday' => ['09:00-12:00']]);
});
