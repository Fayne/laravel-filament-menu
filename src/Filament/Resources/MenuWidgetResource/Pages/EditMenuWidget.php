<?php

namespace Fayne\Menu\Filament\Resources\MenuWidgetResource\Pages;

use Fayne\CMS\Filament\Operations\EditRecord;
use Fayne\Menu\Filament\Concerns\ResolvesConfiguredResource;
use Fayne\Menu\Filament\Resources\MenuWidgetResource;

class EditMenuWidget extends EditRecord
{
    use ResolvesConfiguredResource;

    protected static string $resource = MenuWidgetResource::class;
}
