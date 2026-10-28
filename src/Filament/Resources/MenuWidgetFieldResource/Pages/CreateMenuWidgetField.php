<?php

namespace Fayne\Menu\Filament\Resources\MenuWidgetFieldResource\Pages;

use Fayne\CMS\Filament\Operations\CreateRecord;
use Fayne\Menu\Filament\Concerns\ResolvesConfiguredResource;
use Fayne\Menu\Filament\Resources\MenuWidgetFieldResource;

class CreateMenuWidgetField extends CreateRecord
{
    use ResolvesConfiguredResource;

    protected static string $resource = MenuWidgetFieldResource::class;
}
