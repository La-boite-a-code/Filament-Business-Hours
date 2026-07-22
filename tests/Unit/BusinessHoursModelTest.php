<?php

declare(strict_types=1);

use LaBoiteACode\FilamentBusinessHours\Models\BusinessHours;
use Spatie\OpeningHours\OpeningHours;

it('resolves a fresh record with sane defaults', function (): void {
    $record = BusinessHours::resolve('default');

    expect($record->exists)->toBeFalse()
        ->and($record->key)->toBe('default')
        ->and($record->hours)->toBe([
            'monday' => [], 'tuesday' => [], 'wednesday' => [], 'thursday' => [],
            'friday' => [], 'saturday' => [], 'sunday' => [],
        ])
        ->and($record->exceptions)->toBe([])
        ->and($record->overflow)->toBeFalse();
});

it('resolves the stored record when it exists', function (): void {
    BusinessHours::create([
        'key' => 'shop',
        'hours' => ['monday' => ['09:00-12:00']],
        'exceptions' => [],
    ]);

    $record = BusinessHours::resolve('shop');

    expect($record->exists)->toBeTrue()
        ->and($record->hours['monday'])->toBe(['09:00-12:00']);
});

it('builds a spatie-compatible array', function (): void {
    $record = BusinessHours::resolve('default');
    $record->hours = ['monday' => ['09:00-12:00', '14:00-18:00']] + $record->hours;
    $record->exceptions = ['2026-12-25' => []];
    $record->timezone = 'Europe/Paris';
    $record->overflow = true;

    $array = $record->toOpeningHoursArray();

    expect($array['monday'])->toBe(['09:00-12:00', '14:00-18:00'])
        ->and($array['tuesday'])->toBe([])
        ->and($array['exceptions'])->toBe(['2026-12-25' => []])
        ->and($array['timezone'])->toBe('Europe/Paris')
        ->and($array['overflow'])->toBeTrue();
});

it('omits timezone and overflow when unset', function (): void {
    $array = BusinessHours::resolve('default')->toOpeningHoursArray();

    expect($array)->not->toHaveKey('timezone')
        ->not->toHaveKey('overflow');
});

it('feeds spatie/opening-hours directly', function (): void {
    $record = BusinessHours::resolve('default');
    $record->hours = ['monday' => ['09:00-12:00']] + $record->hours;
    $record->exceptions = [
        '2026-07-27' => [],                                          // an exceptionally closed monday
        '2026-08-03 to 2026-08-16' => ['hours' => [], 'data' => 'Summer break'],
        '01-01' => [],                                               // recurring yearly closure
    ];

    $openingHours = $record->toOpeningHours();

    expect($openingHours)->toBeInstanceOf(OpeningHours::class)
        // A regular monday morning is open, the afternoon is not.
        ->and($openingHours->isOpenAt(new DateTime('2026-07-20 10:00')))->toBeTrue()
        ->and($openingHours->isOpenAt(new DateTime('2026-07-20 15:00')))->toBeFalse()
        // The exceptionally closed monday.
        ->and($openingHours->isOpenAt(new DateTime('2026-07-27 10:00')))->toBeFalse()
        // A monday that falls within the vacation period.
        ->and($openingHours->isOpenAt(new DateTime('2026-08-10 10:00')))->toBeFalse()
        // New year's day, any year.
        ->and($openingHours->isOpenAt(new DateTime('2027-01-01 10:00')))->toBeFalse()
        ->and($openingHours->exceptionalClosingDates())->not->toBeEmpty();
});

it('applies the stored timezone when querying', function (): void {
    $record = BusinessHours::resolve('default');
    $record->hours = ['monday' => ['09:00-12:00']] + $record->hours;
    $record->timezone = 'Europe/Paris';

    $openingHours = $record->toOpeningHours();

    // 08:00 UTC is 10:00 in Paris during summer time: open.
    expect($openingHours->isOpenAt(new DateTime('2026-07-20 08:00', new DateTimeZone('UTC'))))->toBeTrue()
        // 22:00 UTC the day before is 00:00 in Paris: closed.
        ->and($openingHours->isOpenAt(new DateTime('2026-07-20 23:00', new DateTimeZone('UTC'))))->toBeFalse();
});

it('guards against mass assignment of unknown attributes', function (): void {
    $record = BusinessHours::create([
        'key' => 'shop',
        'hours' => [],
        'exceptions' => [],
        'id' => 999,
        'created_at' => '1990-01-01 00:00:00',
    ]);

    expect($record->id)->not->toBe(999)
        ->and($record->created_at->year)->not->toBe(1990);
});

it('exposes a static shortcut to the spatie object', function (): void {
    expect(BusinessHours::openingHours('missing'))->toBeNull();

    BusinessHours::create([
        'key' => 'shop',
        'hours' => ['monday' => ['09:00-12:00']],
        'exceptions' => [],
    ]);

    expect(BusinessHours::openingHours('shop'))->toBeInstanceOf(OpeningHours::class);
});
