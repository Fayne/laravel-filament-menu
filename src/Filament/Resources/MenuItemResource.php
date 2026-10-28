<?php

namespace Fayne\Menu\Filament\Resources;

use Fayne\AdvancedText\Forms\Components\AdvancedText;
use Fayne\CMS\Support\ContentLocale;
use Fayne\Menu\Models\Menu;
use Fayne\Menu\Models\MenuItem;
use Fayne\Menu\Services\MenuItemService;
use Fayne\PageBuilder\Filament\Forms\Components\ContentBuilder;
use Fayne\PageBuilder\Support\EloquentContentDefinitions;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Route;

class MenuItemResource extends Resource
{
    public static function getNavigationLabel(): string
    {
        return __('filament-menu::navigation.menu_items');
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return __('filament-cms::navigation.cms');
    }

    protected static bool $isScopedToTenant = false;

    protected static ?string $model = MenuItem::class;

    public static function getModelLabel(): string
    {
        return __('filament-menu::ui.field_model_menuitem');
    }

    public static function getModel(): string
    {
        return config('filament-menu.models.menu_item', static::$model);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->when(! Menu::isMultilingualEnabled(), fn ($query) => $query->where('locale', ContentLocale::default()));
    }

    public static function form(Schema $schema): Schema
    {
        $suggestionRoute = config('filament-menu.internal_path_suggestion_route');
        $suggestionUrl = is_string($suggestionRoute) && Route::has($suggestionRoute) ? route($suggestionRoute) : null;
        $definitions = new EloquentContentDefinitions(config('filament-menu.models.menu_component'), config('filament-menu.models.menu_widget'));

        return $schema->components([Select::make(config('filament-menu.foreign_keys.menu_id', 'menu_id'))->options(fn () => MenuResource::getEloquentQuery()->pluck('name', 'id'))->required()->live()
            ->afterStateUpdated(fn ($state, Set $set) => $set('locale', MenuResource::getEloquentQuery()->find($state)?->locale ?? ContentLocale::default())),
            TextInput::make('name')->label(__('filament-menu::ui.field_name'))->required()->maxLength(255), AdvancedText::make('url')->label(__('filament-menu::ui.field_url'))->remoteOptionsUrl($suggestionUrl)->maxLength(255), Select::make('target')->label(__('filament-menu::ui.field_target'))->options(['_self' => __('filament-menu::ui.current_window'), '_blank' => __('filament-menu::ui.new_window')])->default('_self')->required(),
            Select::make('locale')->label(__('filament-menu::ui.field_locale'))->options(fn () => ContentLocale::supported())->disabled()->dehydrated(false)->validatedWhenNotDehydrated(false)
                ->default(fn (Get $get) => MenuResource::getEloquentQuery()->find($get(config('filament-menu.foreign_keys.menu_id', 'menu_id')))?->locale ?? ContentLocale::default()),
            Toggle::make('is_active')->label(__('filament-menu::ui.field_is_active'))->default(false), TextInput::make('icon')->label(__('filament-menu::ui.field_icon')), TextInput::make('css_class')->label(__('filament-menu::ui.field_css_class')),
            ContentBuilder::make('content')->label(__('filament-menu::ui.field_content'))->definitions($definitions, $definitions->layouts(), $definitions->layouts(true))->visible(fn (): bool => $definitions->layouts() !== [])->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('id')->label(__('filament-menu::ui.field_id'))->sortable(), TextColumn::make('name')->label(__('filament-menu::ui.field_name'))->searchable()])->recordActions([EditAction::make(), DeleteAction::make()
            ->disabled(fn (MenuItem $record): bool => $record->is_active || $record->children()->exists())
            ->tooltip(fn (MenuItem $record): ?string => $record->is_active ? __('filament-menu::ui.menu_item_disable_before_delete') : ($record->children()->exists() ? __('filament-menu::ui.menu_item_children_first') : null))
            ->using(fn (MenuItem $record): bool => app(MenuItemService::class)->delete($record))]);
    }

    public static function getPages(): array
    {
        return ['index' => MenuItemResource\Pages\ListMenuItems::route('/'), 'create' => MenuItemResource\Pages\CreateMenuItem::route('/create'), 'edit' => MenuItemResource\Pages\EditMenuItem::route('/{record}/edit')];
    }
}
