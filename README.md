# Filament Business Hours

[![Latest Version on Packagist](https://img.shields.io/packagist/v/laboiteacode/filament-business-hours.svg?style=flat-square)](https://packagist.org/packages/laboiteacode/filament-business-hours)
[![Tests](https://img.shields.io/github/actions/workflow/status/la-boite-a-code/filament-business-hours/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/la-boite-a-code/filament-business-hours/actions/workflows/run-tests.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/laboiteacode/filament-business-hours.svg?style=flat-square)](https://packagist.org/packages/laboiteacode/filament-business-hours)
[![License](https://img.shields.io/packagist/l/laboiteacode/filament-business-hours.svg?style=flat-square)](LICENSE.md)

Manage the opening hours of a business from your Filament panel: a weekly
24-hour grid you drag ranges onto, exceptional closures, special-hours days,
vacation periods and timezones. Everything is stored **exactly in the format
consumed by [spatie/opening-hours](https://github.com/spatie/opening-hours)**,
so your application can query the hours with the library you already know.

![Filament Business Hours](art/banner.jpg)

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

## Querying the hours with spatie/opening-hours

The page persists everything in one row of the package's `business_hours`
table (the `default` row unless configured otherwise), and that row holds the
**exact definition format of spatie/opening-hours**. There is no export or
conversion step: turning a stored record into a queryable object is a plain
`OpeningHours::create()` call, which the model wraps for you.

### The one-liner

```php
use LaBoiteACode\FilamentBusinessHours\Models\BusinessHours;

$openingHours = BusinessHours::openingHours();             // the "default" record
$openingHours = BusinessHours::openingHours('shop-paris'); // another record

// Null when no record has been saved yet under that key.
```

### Everything spatie can answer, your data can too

What you get back is a regular `Spatie\OpeningHours\OpeningHours` instance,
so the [whole spatie query API](https://github.com/spatie/opening-hours#usage)
applies to the hours managed from your panel:

```php
$openingHours->isOpenAt(new DateTime('2026-12-24 11:00')); // special hours applied
$openingHours->isClosedAt(now());
$openingHours->isOpenOn('monday');

$openingHours->nextOpen(now());   // when do we open next?
$openingHours->nextClose(now());  // until when are we open?

$openingHours->forDay('monday');                    // the weekly ranges of a day
$openingHours->forDate(new DateTime('2026-12-25')); // a concrete date, exceptions applied
$openingHours->forWeek();

$openingHours->exceptionalClosingDates();           // vacations and closures, expanded
```

The timezone picked in the panel is part of the definition, so date and time
queries are interpreted in that timezone automatically. Labels typed in the
panel ("Christmas", "Summer break", ...) travel as spatie `data` and come
back through `$openingHours->forDate(...)->data`.

### From a record instance

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

### Without the model

Because the columns themselves are stored in spatie shape, nothing forces you
through the Eloquent model. Any code that can read the table can assemble the
definition on its own, for instance from a raw query or another service:

```php
use Illuminate\Support\Facades\DB;
use Spatie\OpeningHours\OpeningHours;

$row = DB::table('business_hours')->where('key', 'default')->first();

$data = json_decode($row->hours, true)
    + ['exceptions' => json_decode($row->exceptions, true)];

if ($row->overflow) {
    $data['overflow'] = true;
}

if ($row->timezone) {
    $data['timezone'] = $row->timezone;
}

$openingHours = OpeningHours::create($data);
```

### Caching busy paths

`OpeningHours::create()` parses the whole definition each time. On a page hit
on every request (a storefront header showing "open until 18:00", say), cache
the definition array rather than rebuilding it:

```php
use Spatie\OpeningHours\OpeningHours;

$data = cache()->remember(
    'business-hours:default',
    now()->addHour(),
    fn (): array => BusinessHours::resolve('default')->toOpeningHoursArray(),
);

$openingHours = OpeningHours::create($data);
```

Invalidate the cache key when the record is saved (a model observer on
`BusinessHours`, or simply a short TTL as above).

### Seeding and writing from code

The door swings both ways: seeders, imports or your own admin code can write
rows directly, and the page will pick them up on next load:

```php
BusinessHours::create([
    'key' => 'shop-paris',
    'timezone' => 'Europe/Paris',
    'hours' => [
        'monday' => ['09:00-12:00', '14:00-18:00'],
        'tuesday' => ['09:00-18:00'],
    ],
    'exceptions' => [
        '12-25' => ['hours' => [], 'data' => 'Christmas'],
    ],
]);
```

Exception entries the page cannot represent (recurring `m-d to m-d` periods,
structured `data` payloads, ...) are preserved verbatim across edits, so
advanced hand-written definitions survive the UI.

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

### Validation and limits

Nothing reaches the database without being validated. On save, the page:

- normalizes every range (format check, chronological sort, merge of
  overlapping or touching ranges);
- refuses dates covered twice (an exceptional date inside a vacation period,
  two overlapping vacation periods, ...) with a readable message;
- validates the timezone;
- validates the whole payload by actually instantiating
  `Spatie\OpeningHours\OpeningHours`, so a stored record is queryable by
  definition.

Because the Livewire state is client-writable, hard caps also protect the
server against crafted payloads: at most 100 ranges per day, 500 exceptional
dates and vacation periods in total, 1100 days per vacation period, and
255 characters per label. Malformed state degrades gracefully instead of
breaking the page render.

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

- [Alexandre Ribes](https://alexandre-ribes.fr)
- [La Boîte à Code](https://laboiteacode.fr)
- [Spatie](https://github.com/spatie) for
  [spatie/opening-hours](https://github.com/spatie/opening-hours)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more
information.
