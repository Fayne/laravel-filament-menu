<?php

namespace Fayne\Menu\Filament\Resources\MenuWidgetResource\RelationManagers;

use Fayne\Menu\Filament\Resources\MenuWidgetFieldResource;
use Fayne\PageBuilder\Filament\RelationManagers\DefinitionFieldsRelationManager;

class FieldsRelationManager extends DefinitionFieldsRelationManager
{
    protected static ?string $relatedResource = MenuWidgetFieldResource::class;
}
