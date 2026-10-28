<?php

namespace Fayne\Menu\Filament\Resources\MenuResource\Pages;

use Fayne\Menu\Filament\Resources\MenuResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMenus extends ListRecords
{
    protected static string $resource = MenuResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
