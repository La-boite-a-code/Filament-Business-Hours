<?php

declare(strict_types=1);

namespace LaBoiteACode\FilamentBusinessHours\Support;

enum ExceptionType: string
{
    // A single "Y-m-d" date: an exceptional closure or special hours.
    case Date = 'date';

    // A yearly recurring "m-d" date.
    case Recurring = 'recurring';

    // A "Y-m-d to Y-m-d" date range: a vacation period.
    case Period = 'period';
}
