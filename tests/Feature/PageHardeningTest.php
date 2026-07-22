<?php

declare(strict_types=1);

use LaBoiteACode\FilamentBusinessHours\Models\BusinessHours;
use LaBoiteACode\FilamentBusinessHours\Pages\ManageBusinessHours;
use Livewire\Livewire;

/*
 * Every public Livewire property is client-writable, so each of these tests
 * simulates a crafted payload (bypassing the forms entirely) and asserts the
 * page refuses it with a notification instead of persisting or crashing.
 */

function assertNothingPersisted(): void
{
    expect(BusinessHours::query()->count())->toBe(0);
}

it('rejects garbage injected into the grid state', function (): void {
    Livewire::test(ManageBusinessHours::class)
        ->set('hours.monday', ['garbage'])
        ->call('save')
        ->assertNotified(trans('filament-business-hours::filament-business-hours.save.invalid_title'));

    assertNothingPersisted();
});

it('rejects non-string items injected into the grid state', function (): void {
    Livewire::test(ManageBusinessHours::class)
        ->set('hours.monday', [['nested' => 'array']])
        ->call('save')
        ->assertNotified(trans('filament-business-hours::filament-business-hours.save.invalid_title'));

    assertNothingPersisted();
});

it('rejects an absurd number of ranges on a single day', function (): void {
    Livewire::test(ManageBusinessHours::class)
        ->set('hours.monday', array_fill(0, 101, '09:00-12:00'))
        ->call('save')
        ->assertNotified(trans('filament-business-hours::filament-business-hours.save.invalid_title'));

    assertNothingPersisted();
});

it('rejects an absurd number of exception entries', function (): void {
    $entries = array_fill(0, 501, [
        'key' => '2026-12-25', 'recurring' => false, 'closed' => true, 'hours' => [], 'label' => null,
    ]);

    Livewire::test(ManageBusinessHours::class)
        ->set('exceptions', $entries)
        ->call('save')
        ->assertNotified(trans('filament-business-hours::filament-business-hours.save.invalid_title'));

    assertNothingPersisted();
});

it('rejects a vacation period long enough to burn memory', function (): void {
    Livewire::test(ManageBusinessHours::class)
        ->set('vacations', [['from' => '2020-01-01', 'until' => '2030-01-01', 'label' => null]])
        ->call('save')
        ->assertNotified(trans('filament-business-hours::filament-business-hours.save.invalid_title'));

    assertNothingPersisted();
});

it('rejects an injected invalid timezone with a readable message', function (): void {
    Livewire::test(ManageBusinessHours::class)
        ->set('timezone', 'Hacker/Zone')
        ->call('save')
        ->assertNotified(trans('filament-business-hours::filament-business-hours.save.invalid_title'));

    assertNothingPersisted();
});

it('drops an out-of-list timezone submitted through the settings modal', function (): void {
    Livewire::test(ManageBusinessHours::class)
        ->callAction('settings', data: ['timezone' => 'Not/AZone', 'overflow' => false])
        ->assertSet('timezone', null);
});

it('rejects an injected exception entry with a garbage date key', function (): void {
    Livewire::test(ManageBusinessHours::class)
        ->set('exceptions', [
            ['key' => '<script>alert(1)</script>', 'recurring' => false, 'closed' => true, 'hours' => [], 'label' => null],
        ])
        ->call('save')
        ->assertNotified(trans('filament-business-hours::filament-business-hours.save.invalid_title'));

    assertNothingPersisted();
});

it('rejects malformed non-array entries in the page state', function (): void {
    Livewire::test(ManageBusinessHours::class)
        ->set('exceptions', ['just-a-string'])
        ->call('save')
        ->assertNotified(trans('filament-business-hours::filament-business-hours.save.invalid_title'));

    assertNothingPersisted();
});

it('keeps rendering when the injected state is malformed', function (): void {
    Livewire::test(ManageBusinessHours::class)
        ->set('exceptions', [
            ['key' => 'garbage', 'recurring' => false, 'closed' => true, 'hours' => [], 'label' => null],
        ])
        ->set('vacations', [
            ['from' => 'garbage', 'until' => 'worse', 'label' => null],
        ])
        ->assertSuccessful()
        ->assertSee('garbage');
});

it('caps injected labels at 255 characters', function (): void {
    Livewire::test(ManageBusinessHours::class)
        ->set('exceptions', [
            ['key' => '2026-12-25', 'recurring' => false, 'closed' => true, 'hours' => [], 'label' => str_repeat('a', 300)],
        ])
        ->call('save')
        ->assertNotified(trans('filament-business-hours::filament-business-hours.save.success_title'));

    $record = BusinessHours::query()->where('key', 'default')->firstOrFail();

    expect(mb_strlen($record->exceptions['2026-12-25']['data']))->toBe(255);
});

it('rejects overnight ranges while overflow is disabled', function (): void {
    Livewire::test(ManageBusinessHours::class)
        ->set('hours.friday', ['20:00-02:00'])
        ->call('save')
        ->assertNotified(trans('filament-business-hours::filament-business-hours.save.invalid_title'));

    assertNothingPersisted();
});

it('ignores a copy from a bogus day argument', function (): void {
    Livewire::test(ManageBusinessHours::class)
        ->set('hours.monday', ['09:00-12:00'])
        ->callAction('copyDay', data: ['targets' => ['monday']], arguments: ['day' => 'bogusday'])
        ->assertSet('hours.monday', ['09:00-12:00']);
});

it('ignores clearing a bogus day', function (): void {
    Livewire::test(ManageBusinessHours::class)
        ->set('hours.monday', ['09:00-12:00'])
        ->call('clearDay', 'bogusday')
        ->assertSet('hours.monday', ['09:00-12:00']);
});

it('ignores edits pointed at an out-of-range index', function (): void {
    Livewire::test(ManageBusinessHours::class)
        ->callAction('editException', data: [
            'date' => '2026-12-26', 'recurring' => false, 'closed' => true,
        ], arguments: ['index' => 99])
        ->assertSet('exceptions', []);
});

it('mounts the exception editor without crashing on malformed entries', function (mixed $entry): void {
    Livewire::test(ManageBusinessHours::class)
        ->set('exceptions', [$entry])
        ->mountAction('editException', arguments: ['index' => 0])
        ->assertSuccessful();
})->with([
    'string entry' => ['just-a-string'],
    'missing hours key' => [['key' => '2026-12-25']],
    'garbage range' => [['key' => '2026-12-25', 'recurring' => false, 'closed' => false, 'hours' => ['garbage'], 'label' => null]],
    'non-string range' => [['key' => '2026-12-25', 'recurring' => false, 'closed' => false, 'hours' => [['a' => 'b']], 'label' => null]],
]);

it('mounts the vacation editor without crashing on malformed entries', function (mixed $entry): void {
    Livewire::test(ManageBusinessHours::class)
        ->set('vacations', [$entry])
        ->mountAction('editVacation', arguments: ['index' => 0])
        ->assertSuccessful();
})->with([
    'string entry' => ['just-a-string'],
    'missing keys' => [['label' => 'only-a-label']],
]);

it('ignores removals pointed at a negative index', function (): void {
    BusinessHours::create([
        'key' => 'default',
        'hours' => [],
        'exceptions' => ['2026-12-25' => []],
    ]);

    $component = Livewire::test(ManageBusinessHours::class)
        ->callAction('removeException', arguments: ['index' => -5]);

    expect($component->get('exceptions'))->toHaveCount(1);
});
