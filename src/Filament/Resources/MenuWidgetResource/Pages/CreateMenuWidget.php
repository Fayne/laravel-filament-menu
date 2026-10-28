<?php

namespace Fayne\Menu\Filament\Resources\MenuWidgetResource\Pages;

use Fayne\CMS\Filament\Operations\CreateRecord;
use Fayne\Menu\Filament\Concerns\ResolvesConfiguredResource;
use Fayne\Menu\Filament\Resources\MenuWidgetResource;

class CreateMenuWidget extends CreateRecord
{
    use ResolvesConfiguredResource;

    protected static string $resource = MenuWidgetResource::class;
}
