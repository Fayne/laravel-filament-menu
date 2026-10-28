<?php

namespace Fayne\Menu\Filament\Resources;

use Fayne\CMS\Support\ContentLocale;
use Fayne\Menu\Filament\Concerns\HasResourceConfiguration;
use Fayne\Menu\Filament\Pages\MenuTree;
use Fayne\Menu\Models\Menu;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class MenuResource extends Resource
{
    use HasResourceConfiguration;

    protected static string $configurationKey = 'menu';

    public static function getNavigationLabel(): string
    {
        return __('filament-menu::navigation.menus');
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return __('filament-cms::navigation.cms');
    }

    protected static bool $isScopedToTenant = false;

    protected static ?string $model = Menu::class;

    public static function getModelLabel(): string
    {
        return __('filament-menu::ui.field_model_menu');
    }

    public static function getModel(): string
    {
        return config('filament-menu.models.menu', static::$model);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->when(! config('filament-menu.models.menu')::isMultilingualEnabled(), fn ($query) => $query->where('locale', ContentLocale::default()));
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([TextInput::make('name')->label(__('filament-menu::ui.field_name'))->required()->maxLength(255), TextInput::make('slug')->label(__('filament-menu::ui.field_slug'))->required()->maxLength(255), Textarea::make('description')->label(__('filament-menu::ui.field_description')), TextInput::make('deep')->label(__('filament-menu::ui.field_deep'))->numeric()->minValue(1)->maxValue(config('filament-menu.menu_builder.max_depth', 5)), Select::make('locale')->label(__('filament-menu::translations.locale'))->options(fn () => config('filament-menu.models.menu')::isMultilingualEnabled() ? ContentLocale::supported() : [ContentLocale::default() => ContentLocale::supported()[ContentLocale::default()]])->required()->default(fn () => ContentLocale::default())->disabled(fn ($record) => $record !== null)->dehydrated(fn ($record) => $record === null), Toggle::make('is_active')->label(__('filament-menu::ui.field_is_active'))->default(false)]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query): Builder {
                $tableName = $query->getModel()->getTable();
                $representative = DB::table($tableName.' as variant')
                    ->select('variant.id')
                    ->whereColumn('variant.translation_group_id', $tableName.'.translation_group_id')
                    ->whereColumn('variant.tenant_id', $tableName.'.tenant_id')
                    ->orderByRaw('CASE WHEN variant.locale = ? THEN 0 ELSE 1 END', [ContentLocale::default()])
                    ->orderBy('variant.id')->limit(1);

                return $query->where($tableName.'.id', '=', $representative)->with('translationVariants');
            })
            ->columns([
                TextColumn::make('id')->label(__('filament-menu::ui.field_id'))->sortable(),
                TextColumn::make('name')->label(__('filament-menu::ui.field_name'))->searchable(),
                TextColumn::make('translation_languages')->label(__('filament-menu::translations.locale'))
                    ->state(fn (Menu $record): string => $record->translationVariants
                        ->map(fn (Menu $variant): string => ContentLocale::supported()[$variant->locale] ?? $variant->locale)->join(' / ')),
            ])
            ->filters([
                SelectFilter::make('locale')->label(__('filament-menu::translations.locale'))
                    ->options(fn (): array => config('filament-menu.models.menu')::isMultilingualEnabled() ? ContentLocale::supported() : [ContentLocale::default() => ContentLocale::supported()[ContentLocale::default()]])
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        filled($data['value'] ?? null),
                        fn (Builder $query): Builder => $query->whereHas('translationVariants', fn (Builder $variants): Builder => $variants->where('locale', $data['value'])),
                    )),
            ])
            ->recordActions([
                Action::make('tree')->label(__('filament-menu::translations.edit_tree'))
                    ->url(fn (Menu $record): string => static::getUrl('tree', ['record' => $record]))
                    ->visible(fn (Menu $record): bool => Gate::allows('update', $record)),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return ['tree' => MenuTree::route('/{record}/tree'), 'index' => MenuResource\Pages\ListMenus::route('/'), 'create' => MenuResource\Pages\CreateMenu::route('/create'), 'edit' => MenuResource\Pages\EditMenu::route('/{record}/edit')];
    }
}
