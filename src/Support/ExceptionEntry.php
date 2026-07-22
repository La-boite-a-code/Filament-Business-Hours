<?php

declare(strict_types=1);

namespace LaBoiteACode\FilamentBusinessHours\Support;

/**
 * One entry of a spatie/opening-hours "exceptions" map, in a shape the page
 * can edit: a single date, a yearly recurring date or a period (vacation),
 * with optional special hours and an optional label.
 *
 * Keys or values this class cannot represent (weekday exceptions, structured
 * "data" payloads, ...) are left untouched by {@see self::parseMap()} so that
 * hand-crafted records survive a round-trip through the page.
 */
final class ExceptionEntry
{
    /**
     * @param  list<string>  $hours
     */
    private function __construct(
        public readonly ExceptionType $type,
        public readonly ?string $date,
        public readonly ?string $from,
        public readonly ?string $until,
        public readonly array $hours,
        public readonly ?string $label,
    ) {}

    /**
     * @param  array<string>  $hours
     */
    public static function date(string $date, array $hours = [], ?string $label = null): self
    {
        return new self(ExceptionType::Date, $date, null, null, array_values($hours), self::presence($label));
    }

    /**
     * @param  array<string>  $hours
     */
    public static function recurring(string $monthDay, array $hours = [], ?string $label = null): self
    {
        return new self(ExceptionType::Recurring, $monthDay, null, null, array_values($hours), self::presence($label));
    }

    public static function period(string $from, string $until, ?string $label = null): self
    {
        return new self(ExceptionType::Period, null, $from, $until, [], self::presence($label));
    }

    /**
     * Parse one spatie exceptions pair. Null when the pair uses a feature this
     * package does not manage (the caller should then keep the pair verbatim).
     */
    public static function tryFromSpatie(string|int $key, mixed $value): ?self
    {
        $key = (string) $key;

        if (! is_array($value)) {
            return null;
        }

        $hours = $value;
        $label = null;

        if (array_key_exists('hours', $value) || array_key_exists('data', $value)) {
            if (array_diff_key($value, array_flip(['hours', 'data'])) !== []) {
                return null;
            }

            $label = $value['data'] ?? null;

            if ($label !== null && ! is_string($label)) {
                return null;
            }

            $hours = $value['hours'] ?? [];

            if (! is_array($hours)) {
                return null;
            }
        }

        foreach ($hours as $index => $range) {
            if (! is_int($index) || ! is_string($range)) {
                return null;
            }
        }

        $hours = array_values($hours);

        if (preg_match('/^(\d{4}-\d{2}-\d{2}) to (\d{4}-\d{2}-\d{2})$/', $key, $matches) === 1) {
            if (! self::isFullDate($matches[1]) || ! self::isFullDate($matches[2])) {
                return null;
            }

            return new self(ExceptionType::Period, null, $matches[1], $matches[2], $hours, self::presence($label));
        }

        if (self::isFullDate($key)) {
            return new self(ExceptionType::Date, $key, null, null, $hours, self::presence($label));
        }

        if (self::isMonthDay($key)) {
            return new self(ExceptionType::Recurring, $key, null, null, $hours, self::presence($label));
        }

        return null;
    }

    /**
     * Split a spatie exceptions map into editable entries and verbatim
     * leftovers, preserving order.
     *
     * @param  array<array-key, mixed>  $map
     * @return array{entries: list<self>, raw: array<string, mixed>}
     */
    public static function parseMap(array $map): array
    {
        $entries = [];
        $raw = [];

        foreach ($map as $key => $value) {
            $entry = self::tryFromSpatie($key, $value);

            if ($entry === null) {
                $raw[(string) $key] = $value;

                continue;
            }

            $entries[] = $entry;
        }

        return ['entries' => $entries, 'raw' => $raw];
    }

    public function spatieKey(): string
    {
        return match ($this->type) {
            ExceptionType::Date, ExceptionType::Recurring => (string) $this->date,
            ExceptionType::Period => "{$this->from} to {$this->until}",
        };
    }

    /**
     * @return array<mixed>
     */
    public function spatieValue(): array
    {
        if ($this->label === null) {
            return $this->hours;
        }

        return ['hours' => $this->hours, 'data' => $this->label];
    }

    /**
     * @return array<string, array<mixed>>
     */
    public function toSpatie(): array
    {
        return [$this->spatieKey() => $this->spatieValue()];
    }

    public function isClosed(): bool
    {
        return $this->hours === [];
    }

    private static function isFullDate(string $value): bool
    {
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $matches) !== 1) {
            return false;
        }

        return checkdate((int) $matches[2], (int) $matches[3], (int) $matches[1]);
    }

    private static function isMonthDay(string $value): bool
    {
        if (preg_match('/^(\d{2})-(\d{2})$/', $value, $matches) !== 1) {
            return false;
        }

        // An arbitrary leap year, so that "02-29" is accepted.
        return checkdate((int) $matches[1], (int) $matches[2], 2024);
    }

    private static function presence(?string $value): ?string
    {
        return ($value === null || trim($value) === '') ? null : $value;
    }
}
