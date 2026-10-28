<?php

namespace Fayne\Menu\Filament\Resources\MenuComponentResource\RelationManagers;

use Fayne\Menu\Filament\Resources\MenuComponentFieldResource;
use Fayne\PageBuilder\Filament\RelationManagers\DefinitionFieldsRelationManager;

class FieldsRelationManager extends DefinitionFieldsRelationManager
{
    protected static ?string $relatedResource = MenuComponentFieldResource::class;
}
