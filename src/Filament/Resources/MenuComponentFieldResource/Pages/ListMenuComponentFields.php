<?php

namespace Fayne\Menu\Filament\Resources\MenuComponentFieldResource\Pages;

use Fayne\Menu\Filament\Resources\MenuComponentFieldResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMenuComponentFields extends ListRecords
{
    protected static string $resource = MenuComponentFieldResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
