<?php

namespace Fayne\Menu\Filament\Resources\MenuWidgetFieldResource\Pages;

use Fayne\CMS\Filament\Operations\EditRecord;
use Fayne\Menu\Filament\Concerns\ResolvesConfiguredResource;
use Fayne\Menu\Filament\Resources\MenuWidgetFieldResource;

class EditMenuWidgetField extends EditRecord
{
    use ResolvesConfiguredResource;

    protected static string $resource = MenuWidgetFieldResource::class;
}
