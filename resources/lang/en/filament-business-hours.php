<?php

declare(strict_types=1);

return [

    'navigation' => [
        'label' => 'Business hours',
    ],

    'page' => [
        'title' => 'Business hours',
        'heading' => 'Business hours',
        'subheading' => 'Manage the weekly opening hours, exceptional closures and vacation periods.',
    ],

    'actions' => [
        'save' => 'Save',
    ],

    'days' => [
        'monday' => 'Monday',
        'tuesday' => 'Tuesday',
        'wednesday' => 'Wednesday',
        'thursday' => 'Thursday',
        'friday' => 'Friday',
        'saturday' => 'Saturday',
        'sunday' => 'Sunday',
    ],

    'grid' => [
        'heading' => 'Weekly hours',
        'description' => 'Timezone: :timezone. The grid snaps to :step minutes.',
        'hint' => 'Drag on a day to create a range, drag a range to move it, drag its edges to resize it. Click a range (or right-click, or press Enter) to type precise times or delete it.',
        'closed' => 'Closed',
        'overnight' => '(+1)',
        'total_hours' => ':hours h',
        'total_hours_minutes' => ':hours h :minutes',
        'add_range' => 'Add a range',
        'copy_day' => 'Copy this day to…',
        'copy_day_heading' => 'Copy ":day" hours to…',
        'copy' => 'Copy',
        'clear_day' => 'Clear this day',
        'editor' => [
            'edit' => 'Edit the range',
            'from' => 'From',
            'until' => 'Until',
            'delete' => 'Delete',
            'done' => 'Done',
            'invalid' => 'The end must come after the start.',
        ],
    ],

    'settings' => [
        'label' => 'Settings',
        'heading' => 'Business hours settings',
        'description' => 'These settings are applied when you save.',
        'apply' => 'Apply',
        'timezone' => 'Timezone',
        'timezone_placeholder' => 'Application timezone (:timezone)',
        'timezone_helper' => 'The timezone the opening hours are expressed in.',
        'overflow' => 'Ranges past midnight',
        'overflow_helper' => 'Allow ranges that end after midnight, such as 20:00-02:00 (spatie/opening-hours "overflow" flag). They can only be typed in the range editor.',
    ],

    'exceptions' => [
        'heading' => 'Exceptional dates',
        'description' => 'Days the establishment is exceptionally closed, or open on special hours.',
        'empty' => 'No exceptional date yet.',
        'add' => 'Add a date',
        'add_heading' => 'Add an exceptional date',
        'edit' => 'Edit',
        'edit_heading' => 'Edit the exceptional date',
        'remove' => 'Remove',
        'remove_heading' => 'Remove this exceptional date?',
        'recurring_badge' => 'Every year',
        'closed_badge' => 'Closed',
        'form' => [
            'date' => 'Date',
            'recurring' => 'Repeats every year',
            'recurring_helper' => 'The year of the picked date is ignored.',
            'closed' => 'Closed all day',
            'hours' => 'Opening hours on that day',
            'add_range' => 'Add a range',
            'from' => 'From',
            'until' => 'Until',
            'label' => 'Label',
            'label_placeholder' => 'Christmas, inventory, …',
        ],
    ],

    'vacations' => [
        'heading' => 'Vacation periods',
        'description' => 'Date ranges the establishment is closed.',
        'empty' => 'No vacation period yet.',
        'add' => 'Add a period',
        'add_heading' => 'Add a vacation period',
        'edit' => 'Edit',
        'edit_heading' => 'Edit the vacation period',
        'remove' => 'Remove',
        'remove_heading' => 'Remove this vacation period?',
        'range' => ':from → :until',
        'days_count' => '{1} :count day|[2,*] :count days',
        'fallback_label' => 'Vacation',
        'form' => [
            'from' => 'First closed day',
            'until' => 'Last closed day',
            'label' => 'Label',
            'label_placeholder' => 'Summer break, …',
        ],
    ],

    'save' => [
        'success_title' => 'Business hours saved',
        'invalid_title' => 'The hours could not be saved',
        'conflicting_dates' => ':date is covered twice (by ":first" and ":second"). Remove one of them.',
        'invalid_period' => 'A vacation period has an invalid date.',
        'period_too_long' => 'A vacation period may not exceed :max days.',
        'too_many_entries' => 'Too many exceptional dates and vacation periods (at most :max in total).',
        'too_many_ranges' => 'Too many ranges on :day (at most :max).',
        'invalid_timezone' => '":timezone" is not a valid timezone.',
    ],

];
