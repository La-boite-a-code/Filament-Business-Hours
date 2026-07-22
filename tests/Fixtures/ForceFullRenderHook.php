<?php

declare(strict_types=1);

namespace LaBoiteACode\FilamentBusinessHours\Tests\Fixtures;

use Filament\Support\Livewire\Partials\PartialsComponentHook;
use Livewire\ComponentHook;

/**
 * Forces a full component render (opting out of Filament's partial-only
 * rendering optimisation) for every Livewire component during the test suite.
 *
 * Filament's partial rendering relies on per-request state that Livewire does
 * not reset between component tests running in the same PHP process, which makes
 * a re-rendered action modal come back empty. Over HTTP each request is isolated
 * so this only ever affects the test harness, never a real application.
 */
class ForceFullRenderHook extends ComponentHook
{
    public function boot(): void
    {
        if (method_exists($this->component, 'forceRender')) {
            app(PartialsComponentHook::class)->forceRender($this->component);
        }
    }
}
