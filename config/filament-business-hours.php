<?php

declare(strict_types=1);
use LaBoiteACode\FilamentBusinessHours\Models\BusinessHours;

return [

    /*
    |--------------------------------------------------------------------------
    | Navigation
    |--------------------------------------------------------------------------
    |
    | Customise how the plugin registers itself in the Filament panel
    | navigation. Every value here can also be overridden at runtime through
    | the plugin's fluent API, e.g.:
    |
    |     FilamentBusinessHoursPlugin::make()
    |         ->navigationLabel('Horaires')
    |         ->navigationIcon('heroicon-o-clock')
    |         ->navigationGroup('Établissement')
    |         ->navigationSort(10);
    |
    */

    'navigation' => [
        'register' => true,
        'sort' => null,
        'group' => null,
        // Nest the entry under an existing navigation item, by its label.
        'parent_item' => null,
        'label' => null,
        'icon' => 'heroicon-o-clock',
        'active_icon' => 'heroicon-s-clock',
    ],

    // The URL slug used for the page inside the panel.
    'slug' => 'business-hours',

    // Optionally attach the page to a Filament cluster (FQCN) or leave null.
    'cluster' => null,

    /*
    |--------------------------------------------------------------------------
    | Storage
    |--------------------------------------------------------------------------
    |
    | Opening hours are stored in the "business_hours" table through the
    | BusinessHours Eloquent model. Each row is identified by a unique key so
    | a single application can hold several sets of opening hours (one per
    | shop, venue, ...). The page manages the row identified by "record".
    |
    | The model can be swapped for your own as long as it extends the default
    | one.
    |
    */

    'record' => 'default',

    'model' => BusinessHours::class,

    /*
    |--------------------------------------------------------------------------
    | Week grid
    |--------------------------------------------------------------------------
    |
    | "first_day_of_week" is the day displayed in the first column of the
    | weekly grid: any english day name in lowercase ("monday" ... "sunday").
    |
    | "slot_step" is the snapping precision of the grid, in minutes. Creating,
    | moving and resizing ranges snaps to this step. Any divisor of 60 works
    | nicely (5, 10, 15, 20, 30, 60). Precise times can always be typed by
    | hand in the range editor regardless of this value.
    |
    */

    'first_day_of_week' => 'monday',

    'slot_step' => 15,

    /*
    |--------------------------------------------------------------------------
    | Timezones
    |--------------------------------------------------------------------------
    |
    | "timezones" is the list of timezone identifiers offered in the settings
    | modal. Null offers every identifier known to PHP; provide an array to
    | restrict the choice, e.g. ['Europe/Paris', 'Europe/Brussels'].
    |
    | "default_timezone" is used for records that have no timezone stored yet.
    | Null falls back to the application timezone (config 'app.timezone').
    |
    */

    'timezones' => null,

    'default_timezone' => null,

    /*
    |--------------------------------------------------------------------------
    | Authorization
    |--------------------------------------------------------------------------
    |
    | Controls the page's canAccess() method. By default the page is available
    | to anyone who can access the panel. Set a Gate ability name to restrict
    | it, or override the whole check through the plugin's fluent API:
    |
    |     FilamentBusinessHoursPlugin::make()
    |         ->canAccessUsing(fn (): bool => auth()->user()?->isAdmin() ?? false);
    |
    */

    'authorization' => [
        'gate' => null,
    ],

];
