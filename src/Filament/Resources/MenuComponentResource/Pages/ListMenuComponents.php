<?php

namespace Fayne\Menu\Filament\Resources\MenuComponentResource\Pages;

use Fayne\Menu\Filament\Concerns\ResolvesConfiguredResource;
use Fayne\Menu\Filament\Resources\MenuComponentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMenuComponents extends ListRecords
{
    use ResolvesConfiguredResource;

    protected static string $resource = MenuComponentResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
