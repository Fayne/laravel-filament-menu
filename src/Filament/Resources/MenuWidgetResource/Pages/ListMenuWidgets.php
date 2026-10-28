<?php

namespace Fayne\Menu\Filament\Resources\MenuWidgetResource\Pages;

use Fayne\Menu\Filament\Resources\MenuWidgetResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMenuWidgets extends ListRecords
{
    protected static string $resource = MenuWidgetResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
