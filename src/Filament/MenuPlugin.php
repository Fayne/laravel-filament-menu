<?php

namespace Fayne\Menu\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;

class MenuPlugin implements Plugin
{
    public static function make(): static
    {
        return new static;
    }

    public function getId(): string
    {
        return 'filament-menu';
    }

    public function register(Panel $panel): void
    {
        $panel->resources([Resources\MenuResource::configuredResource(), Resources\MenuItemResource::configuredResource(), Resources\MenuComponentResource::configuredResource(), Resources\MenuComponentFieldResource::configuredResource(), Resources\MenuWidgetResource::configuredResource(), Resources\MenuWidgetFieldResource::configuredResource()]);
    }

    public function boot(Panel $panel): void {}
}
