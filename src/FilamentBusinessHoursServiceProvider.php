<?php

declare(strict_types=1);

namespace LaBoiteACode\FilamentBusinessHours;

use Filament\Support\Assets\Css;
use Filament\Support\Assets\Js;
use Filament\Support\Facades\FilamentAsset;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class FilamentBusinessHoursServiceProvider extends PackageServiceProvider
{
    public static string $name = 'filament-business-hours';

    public function configurePackage(Package $package): void
    {
        $package
            ->name(static::$name)
            ->hasConfigFile()
            ->hasTranslations()
            ->hasViews()
            ->hasMigration('create_business_hours_table');
    }

    public function packageBooted(): void
    {
        FilamentAsset::register(
            assets: [
                Js::make('filament-business-hours', __DIR__.'/../resources/dist/filament-business-hours.js'),
                Css::make('filament-business-hours', __DIR__.'/../resources/dist/filament-business-hours.css'),
            ],
            package: 'laboiteacode/filament-business-hours',
        );
    }
}
