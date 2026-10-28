<?php

namespace Fayne\Menu\Filament\Pages;

use Fayne\CMS\Support\ContentLocale;
use Fayne\FilamentNestable\Filament\Pages\TreePage;
use Fayne\FilamentNestable\Support\TreeDefinition;
use Fayne\Menu\Filament\Concerns\ResolvesConfiguredResource;
use Fayne\Menu\Filament\Resources\MenuItemResource;
use Fayne\Menu\Filament\Resources\MenuResource;
use Fayne\Menu\Services\MenuItemService;
use Fayne\Menu\Services\MenuTreeService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Js;

class MenuTree extends TreePage
{
    use InteractsWithRecord;
    use ResolvesConfiguredResource;

    protected static string $resource = MenuResource::class;

    public function getTitle(): string
    {
        return __('filament-menu::ui.menu_tree');
    }

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
        $this->loadTree();
    }

    protected function authorizeTreeAccess(): void
    {
        Gate::authorize('update', $this->record);
    }

    private function otherLanguageTargets(): Collection
    {
        if (! $this->record::isMultilingualEnabled()) {
            return collect();
        }

        return $this->record->translations()->filter(fn ($target): bool => $target->locale !== $this->record->locale
            && array_key_exists($target->locale, ContentLocale::supported())
            && Gate::allows('view', $target) && Gate::allows('update', $target));
    }

    protected function getHeaderActions(): array
    {
        return [Action::make('switchLanguage')->label(__('filament-menu::translations.switch'))->icon('heroicon-o-language')
            ->visible(fn (): bool => $this->otherLanguageTargets()->isNotEmpty())
            ->extraAttributes(['x-on:click.capture' => "if (document.querySelector('.nestable-editor')?.dataset.dirty === 'true') { \$event.preventDefault(); \$event.stopImmediatePropagation(); alert(".Js::from(__('filament-menu::translations.save_first')).'); }'])
            ->schema(fn (): array => $this->otherLanguageTargets()->count() > 1 ? [
                Select::make('locale')->label(__('filament-menu::translations.locale'))->required()
                    ->options(fn (): array => $this->otherLanguageTargets()->mapWithKeys(fn ($target): array => [$target->locale => ContentLocale::supported()[$target->locale]])->all()),
            ] : [])
            ->action(function (array $data): void {
                $this->authorizeTreeAccess();
                $targets = $this->otherLanguageTargets();
                $target = $targets->count() === 1 ? $targets->first() : $targets->firstWhere('locale', $data['locale'] ?? null);
                abort_unless($target, 403);
                $this->redirect(MenuResource::configuredResource()::getUrl('tree', ['record' => $target]));
            })];
    }

    protected function treeDefinition(): TreeDefinition
    {
        return app(MenuTreeService::class)->definition($this->record, function ($item, string $ability): void {
            Gate::authorize('update', $this->record);
            if ($ability !== 'view') {
                Gate::authorize($ability, $item);
            }
        });
    }

    public function canAddChild(array $node): bool
    {
        return Gate::allows('create', MenuItemResource::configuredResource()::getModel()) && $node['depth'] < $this->treeDefinition()->maxDepth;
    }

    public function canAddSibling(array $node): bool
    {
        return Gate::allows('create', MenuItemResource::configuredResource()::getModel());
    }

    public function deletionDisabledReason(array $node): ?string
    {
        return $node['active'] ? __('filament-menu::ui.menu_item_disable_before_delete')
            : ($node['children'] !== [] ? __('filament-menu::ui.menu_item_children_first') : null);
    }

    public function getTreeFooterActions(): array
    {
        return [$this->getAction('createNode')];
    }

    public function createNodeAction(): Action
    {
        return Action::make('createNode')->label(__('filament-menu::ui.add_root_menu_item'))->icon('heroicon-o-plus')
            ->modalHeading(fn (array $arguments): string => isset($arguments['after']) ? __('filament-nestable::ui.add_sibling') : (isset($arguments['parent']) ? __('filament-nestable::ui.add_child') : __('filament-menu::ui.add_root_menu_item')))
            ->visible(fn () => Gate::allows('create', MenuItemResource::configuredResource()::getModel()))
            ->schema([
                TextInput::make('name')->label(__('filament-menu::ui.field_name'))->required()->maxLength(255),
                TextInput::make('url')->label(__('filament-menu::ui.field_url'))->maxLength(255),
                Toggle::make('is_active')->label(__('filament-menu::ui.field_is_active'))->default(false),
            ])->action(function (array $data, array $arguments): void {
                $this->authorizeTreeAccess();
                Gate::authorize('create', MenuItemResource::configuredResource()::getModel());
                $parent = isset($arguments['parent']) ? $this->record->menuItems()->findOrFail($arguments['parent']) : null;
                $after = isset($arguments['after']) ? $this->record->menuItems()->findOrFail($arguments['after']) : null;
                app(MenuItemService::class)->create([
                    ...$data, config('filament-menu.foreign_keys.menu_id', 'menu_id') => $this->record->getKey(),
                ], $parent, $after);
                $this->loadTree();
            });
    }

    public function toggleNode(string $key): void
    {
        $this->authorizeTreeAccess();
        $item = $this->record->menuItems()->findOrFail($key);
        Gate::authorize('update', $item);
        app(MenuItemService::class)->update($item, ['is_active' => ! $item->is_active]);
        $this->loadTree();
    }

    public function deleteNode(string $key): void
    {
        $this->authorizeTreeAccess();
        $item = $this->record->menuItems()->findOrFail($key);
        Gate::authorize('delete', $item);
        app(MenuItemService::class)->delete($item);
        $this->loadTree();
    }

    protected function persistTree(array $tree): void
    {
        app(MenuTreeService::class)->reorder($this->record, $tree);
    }
}
