<?php

namespace Fayne\Menu\Filament\Resources;

use Fayne\CMS\Support\ContentFieldTypes;
use Fayne\Menu\Models\MenuComponentField;
use Fayne\PageBuilder\Filament\Forms\FieldDefinitionSchema;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MenuComponentFieldResource extends Resource
{
    public static function getNavigationLabel(): string
    {
        return __('filament-menu::navigation.component_fields');
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return __('filament-cms::navigation.cms');
    }

    protected static bool $isScopedToTenant = false;

    protected static ?string $model = MenuComponentField::class;

    public static function getModelLabel(): string
    {
        return __('filament-menu::ui.field_model_menucomponentfield');
    }

    public static function getModel(): string
    {
        return config('filament-menu.models.menu_component_field', static::$model);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            FieldDefinitionSchema::owner('nova_menu_component_id', config('filament-menu.models.menu_component'), MenuComponentResource::class, __('filament-menu::ui.owning_component')),
            ...static::fieldFormComponents(),
        ]);
    }

    public static function fieldFormComponents(): array
    {
        return FieldDefinitionSchema::components(config('filament-menu.models.menu_widget'));
    }

    public static function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('title')->label(__('filament-menu::ui.field_title'))->searchable(), TextColumn::make('resolver')->label(__('filament-menu::translations.field_type'))->formatStateUsing(fn ($state, $record) => ContentFieldTypes::label($state, $record->options)), TextColumn::make('weight')->label(__('filament-menu::ui.field_weight'))->sortable()])->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => MenuComponentFieldResource\Pages\ListMenuComponentFields::route('/'), 'create' => MenuComponentFieldResource\Pages\CreateMenuComponentField::route('/create'), 'edit' => MenuComponentFieldResource\Pages\EditMenuComponentField::route('/{record}/edit')];
    }
}
