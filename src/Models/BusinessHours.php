<?php

declare(strict_types=1);

namespace LaBoiteACode\FilamentBusinessHours\Models;

use Illuminate\Database\Eloquent\Model;
use LaBoiteACode\FilamentBusinessHours\Support\Weekdays;
use Spatie\OpeningHours\OpeningHours;

/**
 * One set of opening hours, identified by a unique key so an application can
 * store several ("default", "shop-paris", ...).
 *
 * The "hours" and "exceptions" columns hold data exactly as consumed by
 * spatie/opening-hours; {@see self::toOpeningHoursArray()} only assembles the
 * columns into a single definition array.
 *
 * @property int $id
 * @property string $key
 * @property string|null $timezone
 * @property bool $overflow
 * @property array<string, array<int, string>> $hours
 * @property array<string, mixed> $exceptions
 */
class BusinessHours extends Model
{
    protected $table = 'business_hours';

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'hours' => 'array',
            'exceptions' => 'array',
            'overflow' => 'boolean',
        ];
    }

    /**
     * The stored record for the given key, or a fresh unsaved one. Either way
     * every weekday is present in "hours" so callers never deal with holes.
     */
    public static function resolve(string $key): static
    {
        $record = static::query()->firstOrNew(['key' => $key]);

        $hours = [];

        foreach (Weekdays::DAYS as $day) {
            $hours[$day] = array_values((array) ($record->hours[$day] ?? []));
        }

        $record->hours = $hours;
        $record->exceptions = (array) ($record->exceptions ?? []);
        $record->overflow = (bool) $record->overflow;

        return $record;
    }

    /**
     * A definition array for OpeningHours::create(), containing everything the
     * record knows (weekly hours, exceptions, overflow flag and timezone).
     *
     * @return array<string, mixed>
     */
    public function toOpeningHoursArray(): array
    {
        $data = [];

        foreach (Weekdays::DAYS as $day) {
            $data[$day] = array_values((array) ($this->hours[$day] ?? []));
        }

        $data['exceptions'] = (array) ($this->exceptions ?? []);

        if ($this->overflow) {
            $data['overflow'] = true;
        }

        if (is_string($this->timezone) && $this->timezone !== '') {
            $data['timezone'] = $this->timezone;
        }

        return $data;
    }

    public function toOpeningHours(): OpeningHours
    {
        return OpeningHours::create($this->toOpeningHoursArray());
    }

    /**
     * The spatie/opening-hours object for a stored set of hours, or null when
     * no record exists under that key.
     */
    public static function openingHours(string $key = 'default'): ?OpeningHours
    {
        return static::query()->where('key', $key)->first()?->toOpeningHours();
    }
}
