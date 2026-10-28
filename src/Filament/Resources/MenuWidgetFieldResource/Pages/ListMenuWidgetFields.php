<?php

namespace Fayne\Menu\Filament\Resources\MenuWidgetFieldResource\Pages;

use Fayne\Menu\Filament\Resources\MenuWidgetFieldResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMenuWidgetFields extends ListRecords
{
    protected static string $resource = MenuWidgetFieldResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
