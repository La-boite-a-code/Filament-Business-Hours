<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;
use LaBoiteACode\FilamentBusinessHours\FilamentBusinessHoursPlugin;
use LaBoiteACode\FilamentBusinessHours\Models\BusinessHours;
use LaBoiteACode\FilamentBusinessHours\Pages\ManageBusinessHours;

it('resolves sane defaults', function (): void {
    $plugin = FilamentBusinessHoursPlugin::get();

    expect($plugin->getId())->toBe('filament-business-hours')
        ->and($plugin->getSlug())->toBe('business-hours')
        ->and($plugin->getRecord())->toBe('default')
        ->and($plugin->getModel())->toBe(BusinessHours::class)
        ->and($plugin->getFirstDayOfWeek())->toBe('monday')
        ->and($plugin->getSlotStep())->toBe(15)
        ->and($plugin->getCluster())->toBeNull()
        ->and($plugin->getNavigationIcon())->toBe('heroicon-o-clock')
        ->and($plugin->shouldRegisterNavigation())->toBeTrue()
        ->and($plugin->getTimezones())->toBe(timezone_identifiers_list())
        ->and($plugin->getDefaultTimezone())->toBe((string) config('app.timezone'))
        ->and($plugin->canAccess())->toBeTrue();
});

it('lets the config file provide the defaults', function (): void {
    config()->set('filament-business-hours.slug', 'opening-hours');
    config()->set('filament-business-hours.record', 'shop');
    config()->set('filament-business-hours.first_day_of_week', 'tuesday');
    config()->set('filament-business-hours.slot_step', 30);
    config()->set('filament-business-hours.timezones', ['Europe/Paris']);
    config()->set('filament-business-hours.default_timezone', 'Europe/Paris');
    config()->set('filament-business-hours.navigation.label', 'Hours');

    $plugin = FilamentBusinessHoursPlugin::get();

    expect($plugin->getSlug())->toBe('opening-hours')
        ->and($plugin->getRecord())->toBe('shop')
        ->and($plugin->getFirstDayOfWeek())->toBe('tuesday')
        ->and($plugin->getSlotStep())->toBe(30)
        ->and($plugin->getTimezones())->toBe(['Europe/Paris'])
        ->and($plugin->getDefaultTimezone())->toBe('Europe/Paris')
        ->and($plugin->getNavigationLabel())->toBe('Hours');
});

it('lets the fluent API override the config file', function (): void {
    config()->set('filament-business-hours.slug', 'from-config');
    config()->set('filament-business-hours.slot_step', 30);

    $plugin = FilamentBusinessHoursPlugin::get()
        ->slug('from-fluent')
        ->record('shop-paris')
        ->firstDayOfWeek('sunday')
        ->slotStep(10)
        ->timezones(['Europe/Brussels'])
        ->defaultTimezone('Europe/Brussels')
        ->navigationLabel('Fluent label')
        ->registerNavigation(false);

    expect($plugin->getSlug())->toBe('from-fluent')
        ->and($plugin->getRecord())->toBe('shop-paris')
        ->and($plugin->getFirstDayOfWeek())->toBe('sunday')
        ->and($plugin->getSlotStep())->toBe(10)
        ->and($plugin->getTimezones())->toBe(['Europe/Brussels'])
        ->and($plugin->getDefaultTimezone())->toBe('Europe/Brussels')
        ->and($plugin->getNavigationLabel())->toBe('Fluent label')
        ->and($plugin->shouldRegisterNavigation())->toBeFalse();
});

it('clamps the slot step to a sane range', function (): void {
    expect(FilamentBusinessHoursPlugin::get()->slotStep(1)->getSlotStep())->toBe(5)
        ->and(FilamentBusinessHoursPlugin::get()->slotStep(500)->getSlotStep())->toBe(60);
});

it('authorizes through a configured gate', function (): void {
    config()->set('filament-business-hours.authorization.gate', 'manage-business-hours');

    Gate::define('manage-business-hours', fn ($user = null): bool => false);

    expect(FilamentBusinessHoursPlugin::get()->canAccess())->toBeFalse()
        ->and(ManageBusinessHours::canAccess())->toBeFalse();
});

it('lets the access closure take precedence over the gate', function (): void {
    config()->set('filament-business-hours.authorization.gate', 'manage-business-hours');

    Gate::define('manage-business-hours', fn ($user = null): bool => false);

    FilamentBusinessHoursPlugin::get()->canAccessUsing(fn (): bool => true);

    expect(ManageBusinessHours::canAccess())->toBeTrue();
});

it('resolves the record configured on the plugin', function (): void {
    $record = FilamentBusinessHoursPlugin::get()->record('shop-paris')->resolveRecord();

    expect($record)->toBeInstanceOf(BusinessHours::class)
        ->and($record->key)->toBe('shop-paris');
});

it('drives the page navigation and slug from the plugin', function (): void {
    FilamentBusinessHoursPlugin::get()
        ->slug('opening-hours')
        ->navigationLabel('Hours');

    expect(ManageBusinessHours::getSlug())->toBe('opening-hours')
        ->and(ManageBusinessHours::getNavigationLabel())->toBe('Hours');
});
