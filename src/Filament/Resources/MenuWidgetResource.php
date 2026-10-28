<?php

namespace Fayne\Menu\Filament\Resources;

use Fayne\Menu\Models\MenuWidget;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MenuWidgetResource extends Resource
{
    public static function getNavigationLabel(): string
    {
        return __('filament-menu::navigation.widgets');
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return __('filament-cms::navigation.cms');
    }

    protected static bool $isScopedToTenant = false;

    protected static ?string $model = MenuWidget::class;

    public static function getModelLabel(): string
    {
        return __('filament-menu::ui.field_model_menuwidget');
    }

    public static function getModel(): string
    {
        return config('filament-menu.models.menu_widget', static::$model);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([TextInput::make('name')->label(__('filament-menu::ui.field_name'))->required(), TextInput::make('slug')->label(__('filament-menu::ui.field_slug'))->required()->unique(ignoreRecord: true), Textarea::make('description')->label(__('filament-menu::ui.field_description')), TextInput::make('weight')->label(__('filament-menu::ui.field_weight'))->numeric()->default(0)]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('id')->label(__('filament-menu::ui.field_id'))->sortable(), TextColumn::make('name')->label(__('filament-menu::ui.field_name'))->searchable()])->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getRelations(): array
    {
        return [MenuWidgetResource\RelationManagers\FieldsRelationManager::class];
    }

    public static function getPages(): array
    {
        return ['index' => MenuWidgetResource\Pages\ListMenuWidgets::route('/'), 'create' => MenuWidgetResource\Pages\CreateMenuWidget::route('/create'), 'edit' => MenuWidgetResource\Pages\EditMenuWidget::route('/{record}/edit')];
    }
}
