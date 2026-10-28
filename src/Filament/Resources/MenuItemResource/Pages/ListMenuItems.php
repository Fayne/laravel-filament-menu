<?php

namespace Fayne\Menu\Filament\Resources\MenuItemResource\Pages;

use Fayne\Menu\Filament\Concerns\ResolvesConfiguredResource;
use Fayne\Menu\Filament\Resources\MenuItemResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMenuItems extends ListRecords
{
    use ResolvesConfiguredResource;

    protected static string $resource = MenuItemResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
