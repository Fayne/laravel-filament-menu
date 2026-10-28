<?php

namespace Fayne\Menu\Filament\Resources;

use Fayne\CMS\Support\ContentFieldTypes;
use Fayne\Menu\Filament\Concerns\HasResourceConfiguration;
use Fayne\Menu\Models\MenuWidgetField;
use Fayne\PageBuilder\Filament\Forms\FieldDefinitionSchema;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MenuWidgetFieldResource extends Resource
{
    use HasResourceConfiguration;

    protected static string $configurationKey = 'menu_widget_field';

    public static function getNavigationLabel(): string
    {
        return __('filament-menu::navigation.widget_fields');
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return __('filament-cms::navigation.cms');
    }

    protected static bool $isScopedToTenant = false;

    protected static ?string $model = MenuWidgetField::class;

    public static function getModelLabel(): string
    {
        return __('filament-menu::ui.field_model_menuwidgetfield');
    }

    public static function getModel(): string
    {
        return config('filament-menu.models.menu_widget_field', static::$model);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            FieldDefinitionSchema::owner('menu_widget_id', config('filament-menu.models.menu_widget'), MenuWidgetResource::configuredResource(), __('filament-menu::ui.owning_widget')),
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
        return ['index' => MenuWidgetFieldResource\Pages\ListMenuWidgetFields::route('/'), 'create' => MenuWidgetFieldResource\Pages\CreateMenuWidgetField::route('/create'), 'edit' => MenuWidgetFieldResource\Pages\EditMenuWidgetField::route('/{record}/edit')];
    }
}
