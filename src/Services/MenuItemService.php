<?php

namespace Fayne\Menu\Services;

use Fayne\Menu\Models\MenuItem;
use Illuminate\Validation\ValidationException;

final class MenuItemService
{
    public function create(array $data, ?MenuItem $parent = null, ?MenuItem $after = null): MenuItem
    {
        $class = config('filament-menu.models.menu');
        $menu = $class::query()->findOrFail($data[$this->menuKey()]);

        return $menu->getConnection()->transaction(function () use ($menu, $data, $parent, $after): MenuItem {
            $menu = $menu->newQuery()->lockForUpdate()->findOrFail($menu->getKey());
            $parentKey = config('filament-menu.foreign_keys.parent_id', 'parent_id');
            if ($after) {
                $after = $menu->menuItems()->lockForUpdate()->findOrFail($after->getKey());
                $parent = $after->getAttribute($parentKey) ? $menu->menuItems()->findOrFail($after->getAttribute($parentKey)) : null;
            }
            $level = 1;
            if ($parent) {
                $parent = $menu->menuItems()->lockForUpdate()->findOrFail($parent->getKey());
                $level = $parent->level + 1;
            }
            if ($level > min($menu->deep ?: config('filament-menu.menu_builder.max_depth', 5), config('filament-menu.menu_builder.max_depth', 5))) {
                throw ValidationException::withMessages(['name' => __('filament-menu::ui.menu_max_depth')]);
            }
            if (($data['locale'] ?? $menu->locale) !== $menu->locale) {
                throw ValidationException::withMessages(['locale' => __('filament-menu::ui.menu_item_locale_mismatch')]);
            }
            unset($data[config('filament-menu.foreign_keys.parent_id', 'parent_id')], $data['level'], $data['weight']);
            app(MenuContentValidator::class)->validate($menu, $data['content'] ?? [], $level);
            $siblings = $menu->menuItems()->where($parentKey, $parent?->getKey());
            $weight = ($siblings->max('weight') ?? -1) + 1;
            if ($after) {
                $position = 0;
                foreach ($siblings->orderBy('weight')->orderBy('id')->get() as $sibling) {
                    $sibling->forceFill(['weight' => $position++])->save();
                    if ($sibling->is($after)) {
                        $weight = $position++;
                    }
                }
            }
            $item = $menu->menuItems()->create([...$data, 'locale' => $menu->locale, $parentKey => $parent?->getKey(), 'level' => $level, 'weight' => $weight]);
            $menu->forceFill(['changed' => true])->save();

            return $item;
        });
    }

    public function update(MenuItem $item, array $data): MenuItem
    {
        return $item->getConnection()->transaction(function () use ($item, $data): MenuItem {
            $menu = $item->menu()->lockForUpdate()->firstOrFail();
            $item = $item->newQuery()->lockForUpdate()->findOrFail($item->getKey());
            if ((string) ($data[$this->menuKey()] ?? $item->{$this->menuKey()}) !== (string) $menu->getKey()) {
                throw ValidationException::withMessages([$this->menuKey() => __('filament-menu::ui.menu_item_parent_locked')]);
            }
            if (($data['locale'] ?? $item->locale) !== $menu->locale) {
                throw ValidationException::withMessages(['locale' => __('filament-menu::ui.menu_item_locale_mismatch')]);
            }
            unset($data[config('filament-menu.foreign_keys.parent_id', 'parent_id')], $data['level'], $data['weight']);
            app(MenuContentValidator::class)->validate($menu, $data['content'] ?? $item->content ?? [], $item->level);
            $item->fill($data)->save();
            $menu->forceFill(['changed' => true])->save();

            return $item;
        });
    }

    public function delete(MenuItem $item): bool
    {
        return $item->getConnection()->transaction(function () use ($item): bool {
            $menu = $item->menu()->lockForUpdate()->firstOrFail();
            $item = $item->newQuery()->lockForUpdate()->findOrFail($item->getKey());
            if ($item->is_active) {
                throw ValidationException::withMessages(['tree' => __('filament-menu::ui.menu_item_disable_before_delete')]);
            }
            if ($item->children()->exists()) {
                throw ValidationException::withMessages(['tree' => __('filament-menu::ui.menu_item_children_first')]);
            }
            $deleted = (bool) $item->delete();
            $menu->forceFill(['changed' => true])->save();

            return $deleted;
        });
    }

    private function menuKey(): string
    {
        return config('filament-menu.foreign_keys.menu_id', 'menu_id');
    }
}
