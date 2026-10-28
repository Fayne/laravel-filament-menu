<?php

namespace Fayne\Menu\Services;

use Fayne\CMS\Support\ContentLocale;
use Fayne\CMS\Support\Operations\Operations;
use Fayne\Menu\Models\Menu;
use Fayne\PageBuilder\Support\ContentStateAdapter;
use Fayne\PageBuilder\Support\EloquentContentDefinitions;
use Fayne\PageBuilder\Support\SensitiveContent;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class MenuTranslationService
{
    public function create(Menu $source, string $locale, string $slug): Menu
    {
        return app(Operations::class)->run('menu.translation.create', function () use ($source, $locale, $slug): Menu {
            $gate = Gate::forUser(auth(config('filament-cms.guard', 'admin'))->user());
            $gate->authorize('view', $source);
            $gate->authorize('create', $source::class);
            $locale = ContentLocale::resolve($locale);
            if (! Menu::isMultilingualEnabled()) {
                throw ValidationException::withMessages(['locale' => __('filament-menu::translations.disabled')]);
            }
            validator(['slug' => $slug], ['slug' => ['required', 'string', 'max:255']])->validate();

            try {
                return $source->getConnection()->transaction(function () use ($source, $locale, $slug, $gate): Menu {
                    $source = $source->newQuery()->lockForUpdate()->findOrFail($source->getKey());
                    if ($source->newQuery()->where('translation_group_id', $source->translation_group_id)->where('locale', $locale)->exists()) {
                        throw ValidationException::withMessages(['locale' => __('filament-menu::translations.exists')]);
                    }
                    if ($source->newQuery()->where('locale', $locale)->where('slug', $slug)->exists()) {
                        throw ValidationException::withMessages(['slug' => __('filament-menu::translations.slug_conflict')]);
                    }
                    $target = $source->newInstance($source->only(['name', 'description', 'deep', 'settings']));
                    $target->setConnection($source->getConnectionName());
                    $target->forceFill(['slug' => $slug, 'locale' => $locale, 'translation_group_id' => $source->translation_group_id, 'is_active' => false, 'changed' => true]);
                    $target->save();
                    $gate->authorize('update', $target);
                    $definitions = new EloquentContentDefinitions(config('filament-menu.models.menu_component'), config('filament-menu.models.menu_widget'), $source->getConnectionName());
                    $adapter = new ContentStateAdapter($definitions);
                    $parentKey = config('filament-menu.foreign_keys.parent_id', 'parent_id');
                    $map = [];
                    $parents = [];
                    foreach ($source->menuItems()->get() as $item) {
                        $content = $item->content ?? [];
                        if (app(SensitiveContent::class)->contains($content, $definitions)) {
                            $gate->authorize('viewSensitiveContent', $item);
                        }
                        $copy = $target->menuItems()->create([...$item->only(['name', 'url', 'target', 'available_levels', 'weight', 'level', 'is_active', 'icon', 'css_class']), 'content' => $adapter->fromBuilder($adapter->duplicate($adapter->toBuilder($content))), 'locale' => $locale, $parentKey => null]);
                        $map[$item->getKey()] = $copy;
                        $parents[$item->getKey()] = $item->{$parentKey};
                    }
                    foreach ($parents as $id => $parent) {
                        if ($parent !== null && (string) $parent !== '0') {
                            if (! isset($map[$parent])) {
                                throw ValidationException::withMessages(['tree' => __('filament-menu::translations.invalid_tree')]);
                            }
                            $map[$id]->forceFill([$parentKey => $map[$parent]->getKey()])->save();
                        }
                    }

                    return $target;
                });
            } catch (UniqueConstraintViolationException $exception) {
                if ($source->newQuery()->where('translation_group_id', $source->translation_group_id)->where('locale', $locale)->exists()) {
                    throw ValidationException::withMessages(['locale' => __('filament-menu::translations.exists')]);
                }
                if ($source->newQuery()->where('locale', $locale)->where('slug', $slug)->exists()) {
                    throw ValidationException::withMessages(['slug' => __('filament-menu::translations.slug_conflict')]);
                }
                throw $exception;
            }
        }, ['locale' => $locale, 'slug' => $slug], context: ['source_id' => (string) $source->getKey()], connection: $source->getConnectionName());
    }
}
