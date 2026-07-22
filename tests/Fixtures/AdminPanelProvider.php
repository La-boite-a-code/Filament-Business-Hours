<?php

declare(strict_types=1);

namespace LaBoiteACode\FilamentBusinessHours\Tests\Fixtures;

use Filament\Panel;
use Filament\PanelProvider;
use LaBoiteACode\FilamentBusinessHours\FilamentBusinessHoursPlugin;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('admin')
            ->path('admin')
            ->default()
            ->plugin(FilamentBusinessHoursPlugin::make());
    }
}
