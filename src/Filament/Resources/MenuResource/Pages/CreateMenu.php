<?php

namespace Fayne\Menu\Filament\Resources\MenuResource\Pages;

use Fayne\CMS\Filament\Operations\CreateRecord;
use Fayne\Menu\Filament\Concerns\ResolvesConfiguredResource;
use Fayne\Menu\Filament\Resources\MenuResource;

class CreateMenu extends CreateRecord
{
    use ResolvesConfiguredResource;

    protected static string $resource = MenuResource::class;
}
