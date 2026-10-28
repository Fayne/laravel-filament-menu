<?php

namespace Fayne\Menu\Services;

use Fayne\Menu\Models\Menu;
use Illuminate\Validation\ValidationException;

final class MenuContentValidator
{
    public function validate(Menu $menu, array $content, int $level): void
    {
        $class = config('filament-menu.models.menu_component');
        foreach ($content as $block) {
            $component = (new $class)->setConnection($menu->getConnectionName())->newQuery()->where('slug', $block['layout'])->first();
            if (! $component || ($component->specifications !== null && ! in_array($level, $component->specifications[$menu->slug] ?? [], true))) {
                throw ValidationException::withMessages(['content' => __('filament-menu::ui.component_not_allowed')]);
            }
        }
    }
}
