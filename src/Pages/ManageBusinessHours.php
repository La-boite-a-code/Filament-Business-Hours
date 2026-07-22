<?php

declare(strict_types=1);

namespace LaBoiteACode\FilamentBusinessHours\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Panel;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Contracts\Support\Htmlable;
use InvalidArgumentException;
use LaBoiteACode\FilamentBusinessHours\FilamentBusinessHoursPlugin;
use LaBoiteACode\FilamentBusinessHours\Models\BusinessHours;
use LaBoiteACode\FilamentBusinessHours\Support\ExceptionEntry;
use LaBoiteACode\FilamentBusinessHours\Support\ExceptionType;
use LaBoiteACode\FilamentBusinessHours\Support\HourRanges;
use LaBoiteACode\FilamentBusinessHours\Support\Weekdays;
use Spatie\OpeningHours\Exceptions\Exception as OpeningHoursException;
use Throwable;
use UnitEnum;

class ManageBusinessHours extends Page
{
    protected string $view = 'filament-business-hours::pages.manage-business-hours';

    /**
     * Weekly hours, keyed by english day name, each day a list of
     * "HH:MM-HH:MM" strings. Entangled with the Alpine grid component.
     *
     * @var array<string, array<int, string>>
     */
    public array $hours = [];

    /**
     * Exceptional dates (closures or special hours), in page shape:
     * {key: "Y-m-d"|"m-d", recurring: bool, closed: bool, hours: list<string>, label: ?string}.
     *
     * @var array<int, array<string, mixed>>
     */
    public array $exceptions = [];

    /**
     * Vacation periods, in page shape: {from: "Y-m-d", until: "Y-m-d", label: ?string}.
     *
     * @var array<int, array<string, mixed>>
     */
    public array $vacations = [];

    /**
     * Exception entries the page cannot represent (weekday keys, structured
     * data payloads, ...), kept verbatim and merged back on save so that
     * hand-crafted records survive a round-trip through the page.
     *
     * @var array<string, mixed>
     */
    public array $rawExceptions = [];

    public ?string $timezone = null;

    public bool $overflow = false;

    protected static function trans(string $key, array $replace = []): string
    {
        return (string) trans("filament-business-hours::filament-business-hours.{$key}", $replace);
    }

    /*
    |--------------------------------------------------------------------------
    | Navigation (delegated to the plugin, which falls back to config)
    |--------------------------------------------------------------------------
    */

    public static function getNavigationLabel(): string
    {
        return static::plugin()?->getNavigationLabel()
            ?? static::trans('navigation.label');
    }

    public static function getNavigationIcon(): string|BackedEnum|Htmlable|null
    {
        return static::plugin()?->getNavigationIcon()
            ?? config('filament-business-hours.navigation.icon');
    }

    public static function getActiveNavigationIcon(): string|BackedEnum|Htmlable|null
    {
        return static::plugin()?->getActiveNavigationIcon()
            ?? config('filament-business-hours.navigation.active_icon')
            ?? static::getNavigationIcon();
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return static::plugin()?->getNavigationGroup();
    }

    public static function getNavigationSort(): ?int
    {
        return static::plugin()?->getNavigationSort();
    }

    public static function getNavigationParentItem(): ?string
    {
        return static::plugin()?->getNavigationParentItem();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::plugin()?->shouldRegisterNavigation() ?? true;
    }

    public static function getSlug(?Panel $panel = null): string
    {
        return static::plugin()?->getSlug() ?? 'business-hours';
    }

    public static function getCluster(): ?string
    {
        return static::plugin()?->getCluster() ?? parent::getCluster();
    }

    public static function canAccess(): bool
    {
        return static::plugin()?->canAccess() ?? true;
    }

    /*
    |--------------------------------------------------------------------------
    | Page chrome
    |--------------------------------------------------------------------------
    */

    public function getTitle(): string|Htmlable
    {
        return static::trans('page.title');
    }

    public function getHeading(): string|Htmlable|null
    {
        return static::trans('page.heading');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return static::trans('page.subheading');
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            $this->settingsAction(),
            Action::make('save')
                ->label(static::trans('actions.save'))
                ->icon('heroicon-o-check')
                ->action(fn () => $this->save()),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | State
    |--------------------------------------------------------------------------
    */

    public function mount(): void
    {
        $this->fillFromRecord($this->resolveRecord());
    }

    protected function fillFromRecord(BusinessHours $record): void
    {
        $this->hours = $record->hours;

        ['entries' => $entries, 'raw' => $this->rawExceptions] = ExceptionEntry::parseMap($record->exceptions);

        $this->exceptions = [];
        $this->vacations = [];

        foreach ($entries as $entry) {
            if ($entry->type === ExceptionType::Period) {
                // The vacations UI models fully closed periods; a period that
                // carries special hours is preserved verbatim instead of being
                // silently flattened to "closed" on the next save.
                if (! $entry->isClosed()) {
                    $this->rawExceptions[$entry->spatieKey()] = $entry->spatieValue();

                    continue;
                }

                $this->vacations[] = [
                    'from' => $entry->from,
                    'until' => $entry->until,
                    'label' => $entry->label,
                ];

                continue;
            }

            $this->exceptions[] = [
                'key' => $entry->date,
                'recurring' => $entry->type === ExceptionType::Recurring,
                'closed' => $entry->isClosed(),
                'hours' => $entry->hours,
                'label' => $entry->label,
            ];
        }

        $this->sortEntries();

        $this->timezone = $record->timezone;
        $this->overflow = (bool) $record->overflow;
    }

    /**
     * Persist the whole page state as one spatie-compatible record. Every
     * range is normalized first and the payload is validated by actually
     * instantiating spatie's OpeningHours, so anything stored is guaranteed
     * to be consumable by spatie/opening-hours.
     */
    public function save(): void
    {
        try {
            $record = $this->resolveRecord();

            $hours = [];

            foreach (Weekdays::DAYS as $day) {
                $hours[$day] = HourRanges::normalize((array) ($this->hours[$day] ?? []), $this->overflow);
            }

            $record->hours = $hours;
            $record->exceptions = $this->compileExceptions();
            $record->timezone = ($this->timezone !== null && $this->timezone !== '') ? $this->timezone : null;
            $record->overflow = $this->overflow;

            $record->toOpeningHours();

            $record->save();

            // Re-sync so the grid shows the normalized (sorted, merged) state.
            $this->fillFromRecord($record);
        } catch (InvalidArgumentException|OpeningHoursException $exception) {
            Notification::make()
                ->danger()
                ->title(static::trans('save.invalid_title'))
                ->body($exception->getMessage())
                ->send();

            return;
        }

        Notification::make()
            ->success()
            ->title(static::trans('save.success_title'))
            ->send();
    }

    /**
     * The full spatie exceptions map: verbatim leftovers, vacations and
     * exceptional dates. Overlapping dates are rejected upfront with a
     * readable message (spatie would reject them too, more cryptically).
     *
     * @return array<string, mixed>
     */
    protected function compileExceptions(): array
    {
        $map = $this->rawExceptions;
        $coveredDates = [];
        $recurringDates = [];

        $cover = function (string $date, string $source) use (&$coveredDates): void {
            if (isset($coveredDates[$date])) {
                throw new InvalidArgumentException(static::trans('save.conflicting_dates', [
                    'date' => $date,
                    'first' => $coveredDates[$date],
                    'second' => $source,
                ]));
            }

            $coveredDates[$date] = $source;
        };

        foreach ($this->vacations as $vacation) {
            $entry = ExceptionEntry::period(
                (string) $vacation['from'],
                (string) $vacation['until'],
                self::stringOrNull($vacation['label'] ?? null),
            );

            $label = $entry->label ?? static::trans('vacations.fallback_label');

            foreach (self::datesBetween($entry->from, $entry->until) as $date) {
                $cover($date, $label);
            }

            $map[$entry->spatieKey()] = $entry->spatieValue();
        }

        foreach ($this->exceptions as $exception) {
            $hours = $exception['closed']
                ? []
                : HourRanges::normalize((array) ($exception['hours'] ?? []), $this->overflow);

            $label = self::stringOrNull($exception['label'] ?? null);

            $entry = ($exception['recurring'] ?? false)
                ? ExceptionEntry::recurring((string) $exception['key'], $hours, $label)
                : ExceptionEntry::date((string) $exception['key'], $hours, $label);

            $source = $label ?? $entry->spatieKey();

            if ($entry->type === ExceptionType::Recurring) {
                if (isset($recurringDates[$entry->spatieKey()])) {
                    throw new InvalidArgumentException(static::trans('save.conflicting_dates', [
                        'date' => $entry->spatieKey(),
                        'first' => $recurringDates[$entry->spatieKey()],
                        'second' => $source,
                    ]));
                }

                $recurringDates[$entry->spatieKey()] = $source;
            } else {
                $cover($entry->spatieKey(), $source);
            }

            $map[$entry->spatieKey()] = $entry->spatieValue();
        }

        return $map;
    }

    protected function sortEntries(): void
    {
        // Dated entries first (chronologically), recurring ones last (by month-day).
        usort($this->exceptions, function (array $a, array $b): int {
            return [$a['recurring'], $a['key']] <=> [$b['recurring'], $b['key']];
        });

        usort($this->vacations, fn (array $a, array $b): int => $a['from'] <=> $b['from']);
    }

    /*
    |--------------------------------------------------------------------------
    | Grid actions
    |--------------------------------------------------------------------------
    */

    /**
     * The modal mounted from a day header: copies that day's hours onto the
     * checked days. Mounted with `mountAction('copyDay', { day: 'monday' })`.
     */
    public function copyDayAction(): Action
    {
        return Action::make('copyDay')
            ->label(static::trans('grid.copy_day'))
            ->modalHeading(fn (array $arguments): string => static::trans('grid.copy_day_heading', [
                'day' => $this->dayLabel((string) ($arguments['day'] ?? '')),
            ]))
            ->modalSubmitActionLabel(static::trans('grid.copy'))
            ->schema(fn (array $arguments): array => [
                CheckboxList::make('targets')
                    ->hiddenLabel()
                    ->required()
                    ->columns(2)
                    ->options(collect($this->orderedDays())
                        ->reject(fn (string $day): bool => $day === ($arguments['day'] ?? null))
                        ->mapWithKeys(fn (string $day): array => [$day => $this->dayLabel($day)])
                        ->all()),
            ])
            ->action(function (array $arguments, array $data): void {
                $source = (string) ($arguments['day'] ?? '');

                if (! in_array($source, Weekdays::DAYS, true)) {
                    return;
                }

                foreach ((array) ($data['targets'] ?? []) as $target) {
                    if (in_array($target, Weekdays::DAYS, true)) {
                        $this->hours[$target] = $this->hours[$source];
                    }
                }
            });
    }

    /**
     * Empties a day. Called straight from the day header menu.
     */
    public function clearDay(string $day): void
    {
        if (in_array($day, Weekdays::DAYS, true)) {
            $this->hours[$day] = [];
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Settings
    |--------------------------------------------------------------------------
    */

    public function settingsAction(): Action
    {
        return Action::make('settings')
            ->label(static::trans('settings.label'))
            ->icon('heroicon-o-cog-6-tooth')
            ->color('gray')
            ->modalHeading(static::trans('settings.heading'))
            ->modalSubmitActionLabel(static::trans('settings.apply'))
            ->modalDescription(static::trans('settings.description'))
            ->schema([
                Select::make('timezone')
                    ->label(static::trans('settings.timezone'))
                    ->options(array_combine($this->timezoneOptions(), $this->timezoneOptions()))
                    ->searchable()
                    ->placeholder(static::trans('settings.timezone_placeholder', [
                        'timezone' => static::plugin()?->getDefaultTimezone() ?? (string) config('app.timezone'),
                    ]))
                    ->helperText(static::trans('settings.timezone_helper')),
                Toggle::make('overflow')
                    ->label(static::trans('settings.overflow'))
                    ->helperText(static::trans('settings.overflow_helper')),
            ])
            ->fillForm(fn (): array => [
                'timezone' => $this->timezone,
                'overflow' => $this->overflow,
            ])
            ->action(function (array $data): void {
                $this->timezone = self::stringOrNull($data['timezone'] ?? null);
                $this->overflow = (bool) ($data['overflow'] ?? false);
            });
    }

    /*
    |--------------------------------------------------------------------------
    | Exceptional dates
    |--------------------------------------------------------------------------
    */

    public function addExceptionAction(): Action
    {
        return Action::make('addException')
            ->label(static::trans('exceptions.add'))
            ->icon('heroicon-o-plus')
            ->color('gray')
            ->modalHeading(static::trans('exceptions.add_heading'))
            ->schema($this->exceptionSchema())
            ->action(function (array $data): void {
                $this->exceptions[] = $this->exceptionFromForm($data);
                $this->sortEntries();
            });
    }

    /**
     * Mounted with `mountAction('editException', { index: 2 })`.
     */
    public function editExceptionAction(): Action
    {
        return Action::make('editException')
            ->label(static::trans('exceptions.edit'))
            ->modalHeading(static::trans('exceptions.edit_heading'))
            ->schema($this->exceptionSchema())
            ->fillForm(function (array $arguments): array {
                $exception = $this->exceptions[$arguments['index'] ?? -1] ?? null;

                if ($exception === null) {
                    return [];
                }

                return [
                    'date' => $exception['recurring']
                        ? now()->format('Y').'-'.$exception['key']
                        : $exception['key'],
                    'recurring' => $exception['recurring'],
                    'closed' => $exception['closed'],
                    'hours' => array_map(
                        fn (string $range): array => array_combine(['from', 'until'], explode('-', $range)),
                        $exception['hours'],
                    ),
                    'label' => $exception['label'],
                ];
            })
            ->action(function (array $arguments, array $data): void {
                $index = $arguments['index'] ?? null;

                if (! is_int($index) || ! array_key_exists($index, $this->exceptions)) {
                    return;
                }

                $this->exceptions[$index] = $this->exceptionFromForm($data);
                $this->sortEntries();
            });
    }

    /**
     * Mounted with `mountAction('removeException', { index: 2 })`.
     */
    public function removeExceptionAction(): Action
    {
        return Action::make('removeException')
            ->label(static::trans('exceptions.remove'))
            ->icon('heroicon-o-trash')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading(static::trans('exceptions.remove_heading'))
            ->action(function (array $arguments): void {
                $index = $arguments['index'] ?? null;

                if (is_int($index) && array_key_exists($index, $this->exceptions)) {
                    array_splice($this->exceptions, $index, 1);
                }
            });
    }

    /**
     * @return array<int, mixed>
     */
    protected function exceptionSchema(): array
    {
        return [
            DatePicker::make('date')
                ->label(static::trans('exceptions.form.date'))
                ->required(),
            Toggle::make('recurring')
                ->label(static::trans('exceptions.form.recurring'))
                ->helperText(static::trans('exceptions.form.recurring_helper')),
            Toggle::make('closed')
                ->label(static::trans('exceptions.form.closed'))
                ->default(true)
                ->live(),
            Repeater::make('hours')
                ->label(static::trans('exceptions.form.hours'))
                ->visible(fn (Get $get): bool => ! $get('closed'))
                ->required(fn (Get $get): bool => ! $get('closed'))
                ->defaultItems(1)
                ->addActionLabel(static::trans('exceptions.form.add_range'))
                ->schema([
                    TimePicker::make('from')
                        ->label(static::trans('exceptions.form.from'))
                        ->seconds(false)
                        ->required(),
                    TimePicker::make('until')
                        ->label(static::trans('exceptions.form.until'))
                        ->seconds(false)
                        ->required(),
                ])
                ->columns(2),
            TextInput::make('label')
                ->label(static::trans('exceptions.form.label'))
                ->placeholder(static::trans('exceptions.form.label_placeholder'))
                ->maxLength(255),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function exceptionFromForm(array $data): array
    {
        $date = substr((string) $data['date'], 0, 10);
        $recurring = (bool) ($data['recurring'] ?? false);
        $closed = (bool) ($data['closed'] ?? true);

        $hours = $closed ? [] : array_map(
            fn (array $range): string => substr((string) $range['from'], 0, 5).'-'.substr((string) $range['until'], 0, 5),
            array_values((array) ($data['hours'] ?? [])),
        );

        return [
            'key' => $recurring ? substr($date, 5) : $date,
            'recurring' => $recurring,
            'closed' => $closed,
            'hours' => $hours,
            'label' => self::stringOrNull($data['label'] ?? null),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Vacation periods
    |--------------------------------------------------------------------------
    */

    public function addVacationAction(): Action
    {
        return Action::make('addVacation')
            ->label(static::trans('vacations.add'))
            ->icon('heroicon-o-plus')
            ->color('gray')
            ->modalHeading(static::trans('vacations.add_heading'))
            ->schema($this->vacationSchema())
            ->action(function (array $data): void {
                $this->vacations[] = $this->vacationFromForm($data);
                $this->sortEntries();
            });
    }

    /**
     * Mounted with `mountAction('editVacation', { index: 2 })`.
     */
    public function editVacationAction(): Action
    {
        return Action::make('editVacation')
            ->label(static::trans('vacations.edit'))
            ->modalHeading(static::trans('vacations.edit_heading'))
            ->schema($this->vacationSchema())
            ->fillForm(fn (array $arguments): array => $this->vacations[$arguments['index'] ?? -1] ?? [])
            ->action(function (array $arguments, array $data): void {
                $index = $arguments['index'] ?? null;

                if (! is_int($index) || ! array_key_exists($index, $this->vacations)) {
                    return;
                }

                $this->vacations[$index] = $this->vacationFromForm($data);
                $this->sortEntries();
            });
    }

    /**
     * Mounted with `mountAction('removeVacation', { index: 2 })`.
     */
    public function removeVacationAction(): Action
    {
        return Action::make('removeVacation')
            ->label(static::trans('vacations.remove'))
            ->icon('heroicon-o-trash')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading(static::trans('vacations.remove_heading'))
            ->action(function (array $arguments): void {
                $index = $arguments['index'] ?? null;

                if (is_int($index) && array_key_exists($index, $this->vacations)) {
                    array_splice($this->vacations, $index, 1);
                }
            });
    }

    /**
     * @return array<int, mixed>
     */
    protected function vacationSchema(): array
    {
        return [
            DatePicker::make('from')
                ->label(static::trans('vacations.form.from'))
                ->required(),
            DatePicker::make('until')
                ->label(static::trans('vacations.form.until'))
                ->required()
                ->afterOrEqual('from'),
            TextInput::make('label')
                ->label(static::trans('vacations.form.label'))
                ->placeholder(static::trans('vacations.form.label_placeholder'))
                ->maxLength(255),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function vacationFromForm(array $data): array
    {
        return [
            'from' => substr((string) $data['from'], 0, 10),
            'until' => substr((string) $data['until'], 0, 10),
            'label' => self::stringOrNull($data['label'] ?? null),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | View data
    |--------------------------------------------------------------------------
    */

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $effectiveTimezone = $this->timezone
            ?? static::plugin()?->getDefaultTimezone()
            ?? (string) config('app.timezone');

        return [
            'orderedDays' => $this->orderedDays(),
            'dayLabels' => collect(Weekdays::DAYS)
                ->mapWithKeys(fn (string $day): array => [$day => $this->dayLabel($day)])
                ->all(),
            'slotStep' => static::plugin()?->getSlotStep() ?? 15,
            'effectiveTimezone' => $effectiveTimezone,
            'today' => strtolower(now($effectiveTimezone)->format('l')),
        ];
    }

    /**
     * @return list<string>
     */
    public function orderedDays(): array
    {
        return Weekdays::startingFrom(static::plugin()?->getFirstDayOfWeek() ?? 'monday');
    }

    public function dayLabel(string $day): string
    {
        return in_array($day, Weekdays::DAYS, true)
            ? static::trans("days.{$day}")
            : $day;
    }

    /*
    |--------------------------------------------------------------------------
    | Internals
    |--------------------------------------------------------------------------
    */

    protected function resolveRecord(): BusinessHours
    {
        return static::plugin()?->resolveRecord() ?? BusinessHours::resolve('default');
    }

    /**
     * @return list<string>
     */
    protected function timezoneOptions(): array
    {
        return static::plugin()?->getTimezones() ?? timezone_identifiers_list();
    }

    /**
     * @return list<string>
     */
    protected static function datesBetween(?string $from, ?string $until): array
    {
        $dates = [];

        $cursor = \DateTimeImmutable::createFromFormat('Y-m-d', (string) $from);
        $end = \DateTimeImmutable::createFromFormat('Y-m-d', (string) $until);

        if ($cursor === false || $end === false) {
            throw new InvalidArgumentException(static::trans('save.invalid_period'));
        }

        while ($cursor->format('Y-m-d') <= $end->format('Y-m-d')) {
            $dates[] = $cursor->format('Y-m-d');
            $cursor = $cursor->modify('+1 day');
        }

        return $dates;
    }

    protected static function stringOrNull(mixed $value): ?string
    {
        return (is_string($value) && trim($value) !== '') ? $value : null;
    }

    protected static function plugin(): ?FilamentBusinessHoursPlugin
    {
        try {
            return FilamentBusinessHoursPlugin::get();
        } catch (Throwable) {
            return null;
        }
    }
}
