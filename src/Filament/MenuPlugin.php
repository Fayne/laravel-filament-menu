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
        $panel->resources([Resources\MenuResource::class, Resources\MenuItemResource::class, Resources\MenuComponentResource::class, Resources\MenuComponentFieldResource::class, Resources\MenuWidgetResource::class, Resources\MenuWidgetFieldResource::class]);
    }

    public function boot(Panel $panel): void {}
}
