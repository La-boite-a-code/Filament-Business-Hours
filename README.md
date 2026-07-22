# Filament Business Hours

[![Latest Version on Packagist](https://img.shields.io/packagist/v/laboiteacode/filament-business-hours.svg?style=flat-square)](https://packagist.org/packages/laboiteacode/filament-business-hours)
[![Tests](https://img.shields.io/github/actions/workflow/status/la-boite-a-code/filament-business-hours/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/la-boite-a-code/filament-business-hours/actions/workflows/run-tests.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/laboiteacode/filament-business-hours.svg?style=flat-square)](https://packagist.org/packages/laboiteacode/filament-business-hours)
[![License](https://img.shields.io/packagist/l/laboiteacode/filament-business-hours.svg?style=flat-square)](LICENSE.md)

> [!WARNING]
> This package is under active development (0.x). The API and the stored
> format may still change between minor releases; do not rely on it in
> production yet.

Manage the opening hours of a business from your Filament panel: a weekly
24-hour grid you drag ranges onto, exceptional closures, special-hours days,
vacation periods and timezones. Everything is stored **exactly in the format
consumed by [spatie/opening-hours](https://github.com/spatie/opening-hours)**,
so your application can query the hours with the library you already know.

## Features

- **A real weekly grid.** 7 days of stacked hour cells, planner style, themed
  with your panel's own Filament color palette. Drag on empty cells to create
  a range, drag a range to move it, stretch its edges to resize it. Everything
  snaps to a configurable step, and partially covered cells carry a small
  minute badge ("30" on the 05:00 cell means the range starts at 05:30).
- **Precise editing.** Click a range (or right-click it, or press Enter) to
  open an inline editor with real time inputs, down to the minute, plus a
  delete button. `Delete` on a focused range removes it.
- **Day tooling.** Copy one day onto any others in two clicks, clear a day,
  per-day totals, first day of the week configurable (Monday by default).
- **Exceptional dates.** Closures or special hours for a single date, with an
  optional label ("Christmas") and an optional *repeat every year*.
- **Vacation periods.** Date ranges during which the business is closed, with
  a label, stored as native spatie date-range exceptions.
- **Timezones.** Pick the timezone the hours are expressed in (all IANA
  identifiers, or a restricted list you provide).
- **spatie/opening-hours native.** What is stored is what
  `OpeningHours::create()` eats: no export step, no proprietary format. Data
  is validated by actually instantiating spatie's object before saving, so a
  stored record can always be queried.
- **Configurable twice over.** Every option is available both in a published
  config file and through a fluent, per-panel plugin API.
- **Authorization built in.** A single `canAccess()` hook drives both the
  navigation item and the route.
- **Translated.** Ships with English, French and Spanish.

## Compatibility

| Package  | Supported versions |
| -------- | ------------------ |
| PHP      | 8.2, 8.3, 8.4, 8.5 |
| Laravel  | 12, 13             |
| Filament | 4, 5               |

## Installation

```bash
composer require laboiteacode/filament-business-hours
```

Publish and run the migration (one `business_hours` table):

```bash
php artisan vendor:publish --tag=filament-business-hours-migrations
php artisan migrate
```

Register the plugin in your panel provider:

```php
use LaBoiteACode\FilamentBusinessHours\FilamentBusinessHoursPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        // ...
        ->plugin(FilamentBusinessHoursPlugin::make());
}
```

That's it: a "Business hours" page appears in your panel navigation.

## Using the stored hours in your application

The page manages one row of the `business_hours` table (the `default` one
unless configured otherwise). Query it anywhere with spatie/opening-hours:

```php
use LaBoiteACode\FilamentBusinessHours\Models\BusinessHours;

// The spatie object, ready to be queried (null when nothing is stored yet):
$openingHours = BusinessHours::openingHours(); // or ->openingHours('shop-paris')

$openingHours->isOpenAt(now());
$openingHours->nextOpen(now());
$openingHours->forWeek();
```

Or from a record instance:

```php
$record = BusinessHours::resolve('default');

$record->toOpeningHours();      // Spatie\OpeningHours\OpeningHours
$record->toOpeningHoursArray(); // the raw definition array for OpeningHours::create()
```

`toOpeningHoursArray()` returns a plain spatie definition: weekly hours,
exceptions (single dates, yearly recurring `m-d` dates, `Y-m-d to Y-m-d`
vacation ranges), the `overflow` flag and the `timezone`:

```php
[
    'monday' => ['09:00-12:00', '14:00-18:00'],
    // ...
    'exceptions' => [
        '2026-12-24' => ['10:00-16:00'],                                  // special hours
        '2026-12-25' => ['hours' => [], 'data' => 'Christmas'],           // closed, labelled
        '01-01'      => [],                                               // closed every year
        '2026-08-03 to 2026-08-16' => ['hours' => [], 'data' => 'Summer'], // vacation period
    ],
    'timezone' => 'Europe/Paris',
]
```

Because the columns themselves (`hours`, `exceptions`) already hold spatie
formats, you can also build, seed or mutate records from your own code and the
page will pick them up. Exception entries the page cannot represent (recurring
`m-d to m-d` periods, structured `data` payloads, ...) are preserved verbatim
across edits.

## The grid

- **Create**: click and drag vertically on a day; a plain click creates a
  one-hour block.
- **Move / resize**: drag a range, or drag its top/bottom edge. Everything
  snaps to the configured step (15 minutes by default).
- **Edit precisely / delete**: click a range, right-click it, or focus it and
  press Enter. The inline editor accepts any minute, regardless of the snap
  step. Overlapping or touching ranges are merged automatically on save.
- **Copy / clear a day**: hover a day header for its actions.

Saving is explicit: the **Save** header action persists the whole page (grid,
exceptional dates, vacation periods and settings) as one record, after
validating it against spatie/opening-hours itself. A date covered twice (say,
an exceptional closure inside a vacation period) is rejected with a readable
message.

### Ranges past midnight

The grid works on a per-day, 00:00 to 24:00 basis. A night business closing
at 2 AM is usually best modelled with two ranges (`20:00-24:00` on Friday and
`00:00-02:00` on Saturday). If you prefer spatie's `overflow` mode, enable
"Ranges past midnight" in the settings modal: overnight ranges such as
`20:00-02:00` are then accepted, shown clamped to midnight in the grid, and
editable through the inline editor.

## Configuration

Publish the config file if you want application-wide defaults:

```bash
php artisan vendor:publish --tag=filament-business-hours-config
```

Every option can also be set per panel through the fluent API, which wins
over the config file:

```php
FilamentBusinessHoursPlugin::make()
    // Navigation
    ->navigationLabel('Opening hours')
    ->navigationIcon('heroicon-o-clock')
    ->navigationGroup('Shop')
    ->navigationSort(10)
    ->registerNavigation(true)
    ->slug('opening-hours')
    ->cluster(Settings::class)

    // Which row of the business_hours table this panel manages
    ->record('shop-paris')
    // Swap the model for your own (must extend the package model)
    ->model(App\Models\ShopHours::class)

    // Grid
    ->firstDayOfWeek('sunday')  // 'monday' ... 'sunday'
    ->slotStep(30)              // snapping, in minutes

    // Timezones
    ->timezones(['Europe/Paris', 'Europe/Brussels'])
    ->defaultTimezone('Europe/Paris')

    // Authorization
    ->canAccessUsing(fn (): bool => auth()->user()?->can('manageBusinessHours') ?? false);
```

Several panels can manage different records by giving each plugin instance its
own `->record()` key (one page per panel).

### Authorization

By default the page is available to anyone who can access the panel. Restrict
it with a Gate ability in the config:

```php
'authorization' => [
    'gate' => 'manageBusinessHours',
],
```

or with the `->canAccessUsing()` closure shown above, which takes precedence.

## Database

One table, one row per set of hours:

| Column       | Type   | Content                                                        |
| ------------ | ------ | -------------------------------------------------------------- |
| `key`        | string | identifies the set (`default`, `shop-paris`, ...), unique      |
| `timezone`   | string / null | IANA identifier, null = application timezone            |
| `overflow`   | bool   | spatie "overflow" flag (ranges past midnight)                  |
| `hours`      | json   | `{"monday": ["09:00-12:00"], ...}`, spatie day format          |
| `exceptions` | json   | spatie exceptions map (dates, `m-d`, `Y-m-d to Y-m-d` ranges)  |

## Testing

```bash
composer test
composer analyse
composer format
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed
recently.

## Credits

- [Alexandre Ribes](https://github.com/RibesAlexandre)
- [Spatie](https://github.com/spatie) for
  [spatie/opening-hours](https://github.com/spatie/opening-hours)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more
information.
