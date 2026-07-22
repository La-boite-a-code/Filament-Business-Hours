<?php

declare(strict_types=1);

use Filament\Forms\Components\Repeater;
use LaBoiteACode\FilamentBusinessHours\FilamentBusinessHoursPlugin;
use LaBoiteACode\FilamentBusinessHours\Models\BusinessHours;
use LaBoiteACode\FilamentBusinessHours\Pages\ManageBusinessHours;
use Livewire\Livewire;

it('renders with the seven days of the week', function (): void {
    Livewire::test(ManageBusinessHours::class)
        ->assertSuccessful()
        ->assertSee(['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday']);
});

it('loads the stored record into its editable state', function (): void {
    BusinessHours::create([
        'key' => 'default',
        'timezone' => 'Europe/Paris',
        'hours' => ['monday' => ['09:00-12:00', '14:00-18:00']],
        'exceptions' => [
            '2026-12-25' => ['hours' => [], 'data' => 'Christmas'],
            '01-01' => [],
            '2026-08-03 to 2026-08-16' => ['hours' => [], 'data' => 'Summer break'],
            '12-24 to 12-26' => [],
        ],
    ]);

    $component = Livewire::test(ManageBusinessHours::class);

    expect($component->get('hours')['monday'])->toBe(['09:00-12:00', '14:00-18:00'])
        ->and($component->get('hours')['tuesday'])->toBe([])
        ->and($component->get('timezone'))->toBe('Europe/Paris')
        ->and($component->get('exceptions'))->toHaveCount(2)
        ->and($component->get('vacations'))->toBe([
            ['from' => '2026-08-03', 'until' => '2026-08-16', 'label' => 'Summer break'],
        ])
        ->and($component->get('rawExceptions'))->toBe(['12-24 to 12-26' => []]);
});

it('saves the grid state as a spatie-consumable record', function (): void {
    Livewire::test(ManageBusinessHours::class)
        ->set('hours.monday', ['09:00-12:00', '11:00-14:00', '16:00-20:00'])
        ->call('save')
        ->assertNotified(trans('filament-business-hours::filament-business-hours.save.success_title'));

    $record = BusinessHours::query()->where('key', 'default')->firstOrFail();

    // Overlapping ranges are merged on save.
    expect($record->hours['monday'])->toBe(['09:00-14:00', '16:00-20:00'])
        ->and($record->toOpeningHours()->isOpenAt(new DateTime('2026-07-20 13:30')))->toBeTrue();
});

it('rejects invalid ranges without persisting anything', function (): void {
    Livewire::test(ManageBusinessHours::class)
        ->set('hours.monday', ['12:00-09:00'])
        ->call('save')
        ->assertNotified(trans('filament-business-hours::filament-business-hours.save.invalid_title'));

    expect(BusinessHours::query()->count())->toBe(0);
});

it('keeps unmanaged exception entries verbatim through a save round-trip', function (): void {
    // A yearly recurring period, a structured "data" payload and a period
    // with special hours: all valid for spatie/opening-hours, none editable
    // through the page.
    BusinessHours::create([
        'key' => 'default',
        'hours' => ['monday' => ['09:00-12:00']],
        'exceptions' => [
            '12-24 to 12-26' => [],
            '2026-11-11' => ['hours' => [], 'data' => ['reason' => 'inventory']],
            '2026-09-01 to 2026-09-03' => ['10:00-12:00'],
        ],
    ]);

    Livewire::test(ManageBusinessHours::class)
        ->call('save')
        ->assertNotified(trans('filament-business-hours::filament-business-hours.save.success_title'));

    $record = BusinessHours::query()->where('key', 'default')->firstOrFail();

    expect($record->exceptions)->toHaveKey('12-24 to 12-26')
        ->and($record->exceptions['2026-11-11'])->toBe(['hours' => [], 'data' => ['reason' => 'inventory']])
        ->and($record->exceptions['2026-09-01 to 2026-09-03'])->toBe(['10:00-12:00'])
        ->and($record->hours['monday'])->toBe(['09:00-12:00'])
        ->and($record->toOpeningHours()->isOpenAt(new DateTime('2027-12-25 10:00')))->toBeFalse();
});

it('copies a day onto other days', function (): void {
    Livewire::test(ManageBusinessHours::class)
        ->set('hours.monday', ['09:00-12:00'])
        ->callAction('copyDay', data: ['targets' => ['tuesday', 'friday']], arguments: ['day' => 'monday'])
        ->assertHasNoActionErrors()
        ->assertSet('hours.tuesday', ['09:00-12:00'])
        ->assertSet('hours.friday', ['09:00-12:00'])
        ->assertSet('hours.wednesday', []);
});

it('clears a day', function (): void {
    Livewire::test(ManageBusinessHours::class)
        ->set('hours.monday', ['09:00-12:00'])
        ->call('clearDay', 'monday')
        ->assertSet('hours.monday', []);
});

it('adds an exceptional closure through the modal and stores it in spatie shape', function (): void {
    Livewire::test(ManageBusinessHours::class)
        ->callAction('addException', data: [
            'date' => '2026-12-25',
            'recurring' => false,
            'closed' => true,
            'label' => 'Christmas',
        ])
        ->assertHasNoActionErrors()
        ->call('save');

    $record = BusinessHours::query()->where('key', 'default')->firstOrFail();

    expect($record->exceptions)->toBe(['2026-12-25' => ['hours' => [], 'data' => 'Christmas']])
        ->and($record->toOpeningHours()->isOpenAt(new DateTime('2026-12-25 10:00')))->toBeFalse();
});

it('adds a yearly recurring closure keyed by month and day', function (): void {
    Livewire::test(ManageBusinessHours::class)
        ->callAction('addException', data: [
            'date' => '2026-05-01',
            'recurring' => true,
            'closed' => true,
        ])
        ->call('save');

    $record = BusinessHours::query()->where('key', 'default')->firstOrFail();

    expect($record->exceptions)->toHaveKey('05-01');
});

it('adds special hours for a date through the repeater', function (): void {
    $undoRepeaterFake = Repeater::fake();

    Livewire::test(ManageBusinessHours::class)
        ->callAction('addException', data: [
            'date' => '2026-12-24',
            'recurring' => false,
            'closed' => false,
            'hours' => [
                ['from' => '10:00', 'until' => '16:00'],
            ],
        ])
        ->assertHasNoActionErrors()
        ->call('save');

    $undoRepeaterFake();

    $record = BusinessHours::query()->where('key', 'default')->firstOrFail();

    expect($record->exceptions)->toBe(['2026-12-24' => ['10:00-16:00']])
        ->and($record->toOpeningHours()->isOpenAt(new DateTime('2026-12-24 11:00')))->toBeTrue();
});

it('adds a vacation period stored as a spatie date range', function (): void {
    Livewire::test(ManageBusinessHours::class)
        ->set('hours.monday', ['09:00-12:00'])
        ->callAction('addVacation', data: [
            'from' => '2026-08-03',
            'until' => '2026-08-16',
            'label' => 'Summer break',
        ])
        ->assertHasNoActionErrors()
        ->call('save');

    $record = BusinessHours::query()->where('key', 'default')->firstOrFail();

    expect($record->exceptions)
        ->toBe(['2026-08-03 to 2026-08-16' => ['hours' => [], 'data' => 'Summer break']])
        // A monday within the vacation is closed, the next one is open again.
        ->and($record->toOpeningHours()->isOpenAt(new DateTime('2026-08-10 10:00')))->toBeFalse()
        ->and($record->toOpeningHours()->isOpenAt(new DateTime('2026-08-17 10:00')))->toBeTrue();
});

it('rejects a date covered by both a vacation and an exception', function (): void {
    Livewire::test(ManageBusinessHours::class)
        ->callAction('addVacation', data: ['from' => '2026-08-01', 'until' => '2026-08-15'])
        ->callAction('addException', data: ['date' => '2026-08-10', 'recurring' => false, 'closed' => true])
        ->call('save')
        ->assertNotified(trans('filament-business-hours::filament-business-hours.save.invalid_title'));

    expect(BusinessHours::query()->count())->toBe(0);
});

it('applies timezone and overflow through the settings action', function (): void {
    Livewire::test(ManageBusinessHours::class)
        ->callAction('settings', data: ['timezone' => 'Europe/Paris', 'overflow' => true])
        ->assertSet('timezone', 'Europe/Paris')
        ->assertSet('overflow', true)
        ->call('save');

    $record = BusinessHours::query()->where('key', 'default')->firstOrFail();

    expect($record->timezone)->toBe('Europe/Paris')
        ->and($record->overflow)->toBeTrue()
        ->and($record->toOpeningHoursArray()['timezone'])->toBe('Europe/Paris');
});

it('accepts overnight ranges once overflow is enabled', function (): void {
    Livewire::test(ManageBusinessHours::class)
        ->callAction('settings', data: ['timezone' => null, 'overflow' => true])
        ->set('hours.friday', ['20:00-02:00'])
        ->call('save')
        ->assertNotified(trans('filament-business-hours::filament-business-hours.save.success_title'));

    $record = BusinessHours::query()->where('key', 'default')->firstOrFail();

    expect($record->hours['friday'])->toBe(['20:00-02:00'])
        ->and($record->toOpeningHours()->isOpenAt(new DateTime('2026-07-25 01:00')))->toBeTrue();
});

it('edits and removes exceptional dates by index', function (): void {
    BusinessHours::create([
        'key' => 'default',
        'hours' => [],
        'exceptions' => ['2026-12-25' => []],
    ]);

    $component = Livewire::test(ManageBusinessHours::class)
        ->callAction('editException', data: [
            'date' => '2026-12-26',
            'recurring' => false,
            'closed' => true,
            'label' => 'Boxing day',
        ], arguments: ['index' => 0])
        ->assertHasNoActionErrors();

    expect($component->get('exceptions.0.key'))->toBe('2026-12-26')
        ->and($component->get('exceptions.0.label'))->toBe('Boxing day');

    $component
        ->callAction('removeException', arguments: ['index' => 0])
        ->assertSet('exceptions', []);
});

it('restricts access through the plugin callback', function (): void {
    expect(ManageBusinessHours::canAccess())->toBeTrue();

    FilamentBusinessHoursPlugin::get()->canAccessUsing(fn (): bool => false);

    expect(ManageBusinessHours::canAccess())->toBeFalse();
});

it('edits and removes vacation periods by index', function (): void {
    BusinessHours::create([
        'key' => 'default',
        'hours' => [],
        'exceptions' => ['2026-08-03 to 2026-08-16' => ['hours' => [], 'data' => 'Summer break']],
    ]);

    $component = Livewire::test(ManageBusinessHours::class)
        ->callAction('editVacation', data: [
            'from' => '2026-08-10',
            'until' => '2026-08-20',
            'label' => 'Moved break',
        ], arguments: ['index' => 0])
        ->assertHasNoActionErrors();

    expect($component->get('vacations'))->toBe([
        ['from' => '2026-08-10', 'until' => '2026-08-20', 'label' => 'Moved break'],
    ]);

    $component
        ->callAction('removeVacation', arguments: ['index' => 0])
        ->assertSet('vacations', []);
});

it('refuses a vacation ending before it starts', function (): void {
    Livewire::test(ManageBusinessHours::class)
        ->callAction('addVacation', data: [
            'from' => '2026-08-10',
            'until' => '2026-08-01',
        ])
        ->assertHasActionErrors(['until']);
});

it('accepts a single day vacation', function (): void {
    Livewire::test(ManageBusinessHours::class)
        ->set('hours.monday', ['09:00-12:00'])
        ->callAction('addVacation', data: ['from' => '2026-07-27', 'until' => '2026-07-27'])
        ->call('save');

    $record = BusinessHours::query()->where('key', 'default')->firstOrFail();

    expect($record->exceptions)->toHaveKey('2026-07-27 to 2026-07-27')
        ->and($record->toOpeningHours()->isOpenAt(new DateTime('2026-07-27 10:00')))->toBeFalse()
        ->and($record->toOpeningHours()->isOpenAt(new DateTime('2026-08-03 10:00')))->toBeTrue();
});

it('keeps exceptional dates sorted, recurring ones last', function (): void {
    $component = Livewire::test(ManageBusinessHours::class)
        ->callAction('addException', data: ['date' => '2026-12-25', 'recurring' => false, 'closed' => true])
        ->callAction('addException', data: ['date' => '2026-05-01', 'recurring' => true, 'closed' => true])
        ->callAction('addException', data: ['date' => '2026-02-14', 'recurring' => false, 'closed' => true]);

    expect(array_column($component->get('exceptions'), 'key'))->toBe(['2026-02-14', '2026-12-25', '05-01']);
});

it('rejects february 29th as a recurring closure, which spatie cannot represent', function (): void {
    Livewire::test(ManageBusinessHours::class)
        ->callAction('addException', data: ['date' => '2024-02-29', 'recurring' => true, 'closed' => true])
        ->call('save')
        ->assertNotified(trans('filament-business-hours::filament-business-hours.save.invalid_title'));

    expect(BusinessHours::query()->count())->toBe(0);
});

it('round-trips ranges ending at midnight', function (): void {
    Livewire::test(ManageBusinessHours::class)
        ->set('hours.saturday', ['20:00-24:00'])
        ->call('save')
        ->assertNotified(trans('filament-business-hours::filament-business-hours.save.success_title'));

    $record = BusinessHours::query()->where('key', 'default')->firstOrFail();

    expect($record->hours['saturday'])->toBe(['20:00-24:00'])
        ->and($record->toOpeningHours()->isOpenAt(new DateTime('2026-07-25 23:30')))->toBeTrue();
});

it('lets a vacation and a yearly recurring closure cover the same date', function (): void {
    Livewire::test(ManageBusinessHours::class)
        ->callAction('addVacation', data: ['from' => '2026-08-01', 'until' => '2026-08-15'])
        ->callAction('addException', data: ['date' => '2026-08-10', 'recurring' => true, 'closed' => true])
        ->call('save')
        ->assertNotified(trans('filament-business-hours::filament-business-hours.save.success_title'));

    $record = BusinessHours::query()->where('key', 'default')->firstOrFail();

    expect($record->exceptions)->toHaveKey('2026-08-01 to 2026-08-15')
        ->and($record->exceptions)->toHaveKey('08-10');
});

it('copies a day by value, not by reference', function (): void {
    Livewire::test(ManageBusinessHours::class)
        ->set('hours.monday', ['09:00-12:00'])
        ->callAction('copyDay', data: ['targets' => ['tuesday']], arguments: ['day' => 'monday'])
        ->call('clearDay', 'tuesday')
        ->assertSet('hours.tuesday', [])
        ->assertSet('hours.monday', ['09:00-12:00']);
});

it('highlights the current day column', function (): void {
    Livewire::test(ManageBusinessHours::class)
        ->assertSee('fbh-day-head--today', false)
        ->assertSee('fbh-col--today', false);
});

it('manages the record configured on the plugin', function (): void {
    FilamentBusinessHoursPlugin::get()->record('shop-paris');

    Livewire::test(ManageBusinessHours::class)
        ->set('hours.monday', ['09:00-12:00'])
        ->call('save');

    expect(BusinessHours::query()->pluck('key')->all())->toBe(['shop-paris']);
});
