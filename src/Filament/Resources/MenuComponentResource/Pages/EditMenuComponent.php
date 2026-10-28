<?php

namespace Fayne\Menu\Filament\Resources\MenuComponentResource\Pages;

use Fayne\CMS\Filament\Operations\EditRecord;
use Fayne\Menu\Filament\Concerns\ResolvesConfiguredResource;
use Fayne\Menu\Filament\Resources\MenuComponentResource;

class EditMenuComponent extends EditRecord
{
    use ResolvesConfiguredResource;

    protected static string $resource = MenuComponentResource::class;
}
