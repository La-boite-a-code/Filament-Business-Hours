<?php

declare(strict_types=1);

namespace LaBoiteACode\FilamentBusinessHours;

use BackedEnum;
use Closure;
use Filament\Contracts\Plugin;
use Filament\Panel;
use Illuminate\Support\Facades\Gate;
use LaBoiteACode\FilamentBusinessHours\Models\BusinessHours;
use LaBoiteACode\FilamentBusinessHours\Pages\ManageBusinessHours;
use UnitEnum;

/**
 * The Filament plugin entry point.
 *
 * Everything is configurable, either globally through the published config file
 * or per-panel through this fluent API:
 *
 *     use LaBoiteACode\FilamentBusinessHours\FilamentBusinessHoursPlugin;
 *
 *     $panel->plugin(
 *         FilamentBusinessHoursPlugin::make()
 *             ->navigationGroup('Shop')
 *             ->navigationIcon('heroicon-o-clock')
 *             ->record('shop-paris')
 *             ->firstDayOfWeek('sunday')
 *             ->slotStep(30)
 *             ->timezones(['Europe/Paris', 'Europe/Brussels'])
 *             ->canAccessUsing(fn (): bool => auth()->user()?->can('manageHours') ?? false),
 *     );
 */
class FilamentBusinessHoursPlugin implements Plugin
{
    protected string|BackedEnum|null $navigationIcon = null;

    protected string|BackedEnum|null $activeNavigationIcon = null;

    protected string|UnitEnum|null $navigationGroup = null;

    protected ?string $navigationLabel = null;

    protected ?int $navigationSort = null;

    protected ?string $navigationParentItem = null;

    protected ?bool $registerNavigation = null;

    protected ?string $slug = null;

    protected ?string $cluster = null;

    protected ?string $record = null;

    /** @var class-string<BusinessHours>|null */
    protected ?string $model = null;

    protected ?string $firstDayOfWeek = null;

    protected ?int $slotStep = null;

    /** @var array<int, string>|null */
    protected ?array $timezones = null;

    protected ?string $defaultTimezone = null;

    protected ?Closure $canAccessUsing = null;

    public function getId(): string
    {
        return 'filament-business-hours';
    }

    public static function make(): static
    {
        return app(static::class);
    }

    public static function get(): static
    {
        /** @var static $plugin */
        $plugin = filament(app(static::class)->getId());

        return $plugin;
    }

    public function register(Panel $panel): void
    {
        $panel->pages([
            ManageBusinessHours::class,
        ]);
    }

    public function boot(Panel $panel): void
    {
        //
    }

    /*
    |--------------------------------------------------------------------------
    | Fluent configuration
    |--------------------------------------------------------------------------
    */

    public function navigationIcon(string|BackedEnum|null $icon): static
    {
        $this->navigationIcon = $icon;

        return $this;
    }

    public function activeNavigationIcon(string|BackedEnum|null $icon): static
    {
        $this->activeNavigationIcon = $icon;

        return $this;
    }

    public function navigationGroup(string|UnitEnum|null $group): static
    {
        $this->navigationGroup = $group;

        return $this;
    }

    public function navigationLabel(?string $label): static
    {
        $this->navigationLabel = $label;

        return $this;
    }

    public function navigationSort(?int $sort): static
    {
        $this->navigationSort = $sort;

        return $this;
    }

    public function navigationParentItem(?string $item): static
    {
        $this->navigationParentItem = $item;

        return $this;
    }

    public function registerNavigation(bool $condition = true): static
    {
        $this->registerNavigation = $condition;

        return $this;
    }

    public function slug(?string $slug): static
    {
        $this->slug = $slug;

        return $this;
    }

    public function cluster(?string $cluster): static
    {
        $this->cluster = $cluster;

        return $this;
    }

    /**
     * Which set of opening hours the page manages (the "key" column of the
     * business_hours table). Lets different panels manage different sets.
     */
    public function record(?string $key): static
    {
        $this->record = $key;

        return $this;
    }

    /**
     * @param  class-string<BusinessHours>|null  $model
     */
    public function model(?string $model): static
    {
        $this->model = $model;

        return $this;
    }

    /**
     * The day shown in the first column of the grid ("monday" ... "sunday").
     */
    public function firstDayOfWeek(?string $day): static
    {
        $this->firstDayOfWeek = $day;

        return $this;
    }

    /**
     * The snapping precision of the grid, in minutes.
     */
    public function slotStep(?int $minutes): static
    {
        $this->slotStep = $minutes;

        return $this;
    }

    /**
     * @param  array<int, string>|null  $timezones
     */
    public function timezones(?array $timezones): static
    {
        $this->timezones = $timezones;

        return $this;
    }

    public function defaultTimezone(?string $timezone): static
    {
        $this->defaultTimezone = $timezone;

        return $this;
    }

    public function canAccessUsing(Closure $callback): static
    {
        $this->canAccessUsing = $callback;

        return $this;
    }

    /*
    |--------------------------------------------------------------------------
    | Resolved values (fluent API overrides config, config provides defaults)
    |--------------------------------------------------------------------------
    */

    public function getNavigationIcon(): string|BackedEnum|null
    {
        return $this->navigationIcon ?? config('filament-business-hours.navigation.icon');
    }

    public function getActiveNavigationIcon(): string|BackedEnum|null
    {
        return $this->activeNavigationIcon
            ?? $this->navigationIcon
            ?? config('filament-business-hours.navigation.active_icon')
            ?? config('filament-business-hours.navigation.icon');
    }

    public function getNavigationGroup(): string|UnitEnum|null
    {
        return $this->navigationGroup ?? config('filament-business-hours.navigation.group');
    }

    public function getNavigationLabel(): ?string
    {
        return $this->navigationLabel
            ?? config('filament-business-hours.navigation.label')
            ?? (string) trans('filament-business-hours::filament-business-hours.navigation.label');
    }

    public function getNavigationSort(): ?int
    {
        return $this->navigationSort ?? config('filament-business-hours.navigation.sort');
    }

    public function getNavigationParentItem(): ?string
    {
        return $this->navigationParentItem ?? config('filament-business-hours.navigation.parent_item');
    }

    public function shouldRegisterNavigation(): bool
    {
        return $this->registerNavigation ?? (bool) config('filament-business-hours.navigation.register', true);
    }

    public function getSlug(): string
    {
        return $this->slug
            ?? config('filament-business-hours.slug')
            ?? 'business-hours';
    }

    public function getCluster(): ?string
    {
        return $this->cluster ?? config('filament-business-hours.cluster');
    }

    public function getRecord(): string
    {
        return $this->record
            ?? config('filament-business-hours.record')
            ?? 'default';
    }

    /**
     * @return class-string<BusinessHours>
     */
    public function getModel(): string
    {
        return $this->model
            ?? config('filament-business-hours.model')
            ?? BusinessHours::class;
    }

    public function getFirstDayOfWeek(): string
    {
        return $this->firstDayOfWeek
            ?? config('filament-business-hours.first_day_of_week')
            ?? 'monday';
    }

    public function getSlotStep(): int
    {
        $step = $this->slotStep ?? (int) config('filament-business-hours.slot_step', 15);

        return max(5, min(60, $step));
    }

    /**
     * @return array<int, string>
     */
    public function getTimezones(): array
    {
        $timezones = $this->timezones ?? config('filament-business-hours.timezones');

        return $timezones ?? timezone_identifiers_list();
    }

    public function getDefaultTimezone(): ?string
    {
        return $this->defaultTimezone
            ?? config('filament-business-hours.default_timezone')
            ?? config('app.timezone');
    }

    public function canAccess(): bool
    {
        if ($this->canAccessUsing instanceof Closure) {
            return (bool) call_user_func($this->canAccessUsing);
        }

        $gate = config('filament-business-hours.authorization.gate');

        if (is_string($gate) && $gate !== '') {
            return Gate::allows($gate) === true;
        }

        return true;
    }

    /*
    |--------------------------------------------------------------------------
    | Factories
    |--------------------------------------------------------------------------
    */

    /**
     * The record the page manages, resolved through the configured model.
     */
    public function resolveRecord(): BusinessHours
    {
        $model = $this->getModel();

        return $model::resolve($this->getRecord());
    }
}
