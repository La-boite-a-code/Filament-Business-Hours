<?php

declare(strict_types=1);

namespace LaBoiteACode\FilamentBusinessHours\Tests;

use BladeUI\Heroicons\BladeHeroiconsServiceProvider;
use BladeUI\Icons\BladeIconsServiceProvider;
use Filament\Actions\ActionsServiceProvider;
use Filament\FilamentServiceProvider;
use Filament\Forms\FormsServiceProvider;
use Filament\Infolists\InfolistsServiceProvider;
use Filament\Notifications\NotificationsServiceProvider;
use Filament\Schemas\SchemasServiceProvider;
use Filament\Support\SupportServiceProvider;
use Filament\Tables\TablesServiceProvider;
use Filament\Widgets\WidgetsServiceProvider;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ViewErrorBag;
use LaBoiteACode\FilamentBusinessHours\FilamentBusinessHoursServiceProvider;
use LaBoiteACode\FilamentBusinessHours\Tests\Fixtures\AdminPanelProvider;
use LaBoiteACode\FilamentBusinessHours\Tests\Fixtures\ForceFullRenderHook;
use LaBoiteACode\FilamentBusinessHours\Tests\Fixtures\User;
use Livewire\Livewire;
use Livewire\LivewireServiceProvider;
use Livewire\Mechanisms\DataStore;
use Orchestra\Testbench\TestCase as Orchestra;
use RyanChandler\BladeCaptureDirective\BladeCaptureDirectiveServiceProvider;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        // In the isolated Livewire test harness the DataStore mechanism is not
        // always resolved as a shared instance, which breaks error-bag state
        // during render. Pin it as a singleton so state persists as it does
        // over HTTP. (Only needed for these component tests.)
        app()->singleton(DataStore::class);

        // Livewire normally flushes its per-request state between HTTP requests.
        // The isolated test harness does not, so Filament's partial rendering
        // leaks between tests and a second rendered modal comes back empty.
        // Flushing what we can and forcing full renders keeps things isolated.
        Livewire::flushState();
        View::flushState();
        Livewire::componentHook(ForceFullRenderHook::class);

        $this->actingAs(new User);

        filament()->setCurrentPanel('admin');

        // Livewire component tests do not run the HTTP middleware stack, so the
        // "errors" view bag that ShareErrorsFromSession normally provides is
        // never shared. Filament pages rely on it during render.
        View::share('errors', new ViewErrorBag);
    }

    protected function getPackageProviders($app): array
    {
        return [
            // Livewire and the Blade icon providers must boot before Filament,
            // which depends on them.
            BladeIconsServiceProvider::class,
            BladeHeroiconsServiceProvider::class,
            BladeCaptureDirectiveServiceProvider::class,
            LivewireServiceProvider::class,
            SupportServiceProvider::class,
            ActionsServiceProvider::class,
            FormsServiceProvider::class,
            InfolistsServiceProvider::class,
            NotificationsServiceProvider::class,
            SchemasServiceProvider::class,
            TablesServiceProvider::class,
            WidgetsServiceProvider::class,
            FilamentServiceProvider::class,
            FilamentBusinessHoursServiceProvider::class,
            AdminPanelProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    protected function defineDatabaseMigrations(): void
    {
        $migration = include __DIR__.'/../database/migrations/create_business_hours_table.php.stub';
        $migration->up();
    }
}
