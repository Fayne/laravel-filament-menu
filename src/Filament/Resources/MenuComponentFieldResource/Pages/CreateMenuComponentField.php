<?php

namespace Fayne\Menu\Filament\Resources\MenuComponentFieldResource\Pages;

use Fayne\CMS\Filament\Operations\CreateRecord;
use Fayne\Menu\Filament\Concerns\ResolvesConfiguredResource;
use Fayne\Menu\Filament\Resources\MenuComponentFieldResource;

class CreateMenuComponentField extends CreateRecord
{
    use ResolvesConfiguredResource;

    protected static string $resource = MenuComponentFieldResource::class;
}
