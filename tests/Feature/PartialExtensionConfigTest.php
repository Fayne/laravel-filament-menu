<?php

namespace Fayne\Menu\Tests\Feature;

use Fayne\Menu\Filament\Resources\MenuComponentResource;
use Fayne\Menu\Models\MenuComponent;
use Fayne\Menu\Tests\Fixtures\HostMenu;
use Fayne\Menu\Tests\Fixtures\HostMenuResource;
use Fayne\Menu\Tests\TestCase;
use Filament\Facades\Filament;

require_once __DIR__.'/../Fixtures/ResourceExtensions.php';

class PartialExtensionConfigTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('filament-menu.models', ['menu' => HostMenu::class]);
        $app['config']->set('filament-menu.resources', ['menu' => HostMenuResource::class]);
    }

    public function test_partial_maps_preserve_default_models_and_resources(): void
    {
        $this->assertSame(HostMenu::class, config('filament-menu.models.menu'));
        $this->assertSame(MenuComponent::class, config('filament-menu.models.menu_component'));
        $this->assertSame(MenuComponentResource::class, config('filament-menu.resources.menu_component'));
        $this->assertContains(HostMenuResource::class, Filament::getPanel('admin')->getResources());
    }
}
