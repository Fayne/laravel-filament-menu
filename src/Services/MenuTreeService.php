<?php

namespace Fayne\Menu\Services;

use Closure;
use Fayne\CMS\Support\Operations\Operations;
use Fayne\FilamentNestable\Services\TreeService;
use Fayne\FilamentNestable\Support\TreeDefinition;
use Fayne\Menu\Filament\Resources\MenuItemResource;
use Fayne\Menu\Models\Menu;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class MenuTreeService
{
    public function definition(Menu $menu, ?Closure $authorize = null): TreeDefinition
    {
        return new TreeDefinition(
            $menu->menuItems()->getQuery(),
            $authorize ?? fn () => true,
            parentColumn: config('filament-menu.foreign_keys.parent_id', 'parent_id'),
            levelColumn: 'level',
            maxDepth: min($menu->deep ?: config('filament-menu.menu_builder.max_depth', 5), config('filament-menu.menu_builder.max_depth', 5)),
            scope: 'menu:'.$menu->getKey(),
            subtitleColumn: 'url',
            toggleColumn: 'is_active',
            canDelete: true,
            validate: function ($item, int $depth) use ($menu): void {
                if ($item->locale !== $menu->locale || (! empty($item->available_levels) && ! in_array($depth, array_map('intval', $item->available_levels), true))) {
                    throw ValidationException::withMessages(['tree' => __('filament-menu::ui.menu_item_tree_mismatch')]);
                }
                app(MenuContentValidator::class)->validate($menu, $item->content ?? [], $depth);
            },
            editUrl: fn ($item) => Gate::allows('update', $item) ? MenuItemResource::configuredResource()::getUrl('edit', ['record' => $item]) : null,
        );
    }

    public function reorder(Menu $menu, array $tree): void
    {
        app(Operations::class)->run('menu.reorder', function () use ($menu, $tree): void {
            $menu->getConnection()->transaction(function () use ($menu, $tree): void {
                $menu = $menu->newQuery()->lockForUpdate()->findOrFail($menu->getKey());
                app(TreeService::class)->reorder($this->definition($menu), $tree);
                $menu->forceFill(['changed' => true])->save();
            });
        }, ['tree' => $tree], $menu, []);
    }
}
