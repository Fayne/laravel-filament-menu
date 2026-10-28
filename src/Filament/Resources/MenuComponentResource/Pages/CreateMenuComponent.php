<?php

namespace Fayne\Menu\Filament\Resources\MenuComponentResource\Pages;

use Fayne\CMS\Filament\Operations\CreateRecord;
use Fayne\Menu\Filament\Concerns\ResolvesConfiguredResource;
use Fayne\Menu\Filament\Resources\MenuComponentResource;

class CreateMenuComponent extends CreateRecord
{
    use ResolvesConfiguredResource;

    protected static string $resource = MenuComponentResource::class;
}
