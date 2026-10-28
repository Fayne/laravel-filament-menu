<?php

namespace Fayne\Menu\Models;

use Fayne\CMS\Support\Concerns\BelongsToTenant;
use Fayne\CMS\Support\Concerns\HasTranslationGroup;
use Fayne\CMS\Support\Concerns\RegionAware;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class Menu extends Model
{
    use BelongsToTenant;
    use HasTranslationGroup;
    use RegionAware;

    protected $guarded = ['id'];

    protected $casts = ['is_active' => 'boolean', 'settings' => 'array', 'changed' => 'boolean', 'deep' => 'integer'];

    protected $attributes = ['is_active' => false];

    public function getTable(): string
    {
        return config('filament-menu.tables.menus', 'nova_menus');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function translationVariants(): HasMany
    {
        return $this->hasMany(static::class, 'translation_group_id', 'translation_group_id');
    }

    public function menuItems(): HasMany
    {
        return $this->hasMany(config('filament-menu.models.menu_item'), config('filament-menu.foreign_keys.menu_id', 'menu_id'))->orderBy('weight')->orderBy('id');
    }

    public function publicTree(bool $onlyActive = true): Collection
    {
        $parentKey = config('filament-menu.foreign_keys.parent_id', 'parent_id');
        $grouped = $this->menuItems()->when($onlyActive, fn ($query) => $query->where('is_active', true))->where('locale', $this->locale)->get()->groupBy(fn ($item) => $item->{$parentKey} === null || (string) $item->{$parentKey} === '0' ? '' : (string) $item->{$parentKey});
        $build = function ($parent, array $seen = []) use (&$build, $grouped): Collection {
            return $grouped->get($parent, collect())->reject(fn ($item) => isset($seen[$item->getKey()]))->values()->map(function ($item) use (&$build, $seen) {
                $item->setRelation('children', $build($item->getKey(), [$item->getKey() => true] + $seen));

                return $item;
            });
        };

        return $build('');
    }
}
