<?php

declare(strict_types=1);

use LaBoiteACode\FilamentBusinessHours\Support\Weekdays;

it('lists the week from monday by default', function (): void {
    expect(Weekdays::startingFrom('monday'))->toBe([
        'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday',
    ]);
});

it('rotates the week to start from any day', function (): void {
    expect(Weekdays::startingFrom('sunday'))->toBe([
        'sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday',
    ]);

    expect(Weekdays::startingFrom('saturday'))->toBe([
        'saturday', 'sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday',
    ]);
});

it('ignores case when rotating', function (): void {
    expect(Weekdays::startingFrom('Sunday'))->toBe(Weekdays::startingFrom('sunday'));
});

it('falls back to monday for unknown days', function (): void {
    expect(Weekdays::startingFrom('lundi'))->toBe(Weekdays::startingFrom('monday'));
});
