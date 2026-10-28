<?php

namespace Fayne\Menu\Models;

use Fayne\CMS\Support\Concerns\BelongsToTenant;
use Fayne\CMS\Support\Concerns\RegionAware;
use Fayne\CMS\Support\ContentLocale;
use Fayne\PageBuilder\Support\ContentOutputResolver;
use Fayne\PageBuilder\Support\EloquentContentDefinitions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MenuItem extends Model
{
    use BelongsToTenant;
    use RegionAware;

    protected $guarded = ['id'];

    protected $casts = ['content' => 'array', 'available_levels' => 'array', 'is_active' => 'boolean', 'level' => 'integer', 'weight' => 'integer'];

    protected $attributes = ['is_active' => false, 'target' => '_self', 'level' => 1, 'weight' => 0];

    protected static function booted(): void
    {
        static::creating(function ($item): void {
            if (blank($item->locale)) {
                $item->locale = $item->menu()->first()?->locale ?? ContentLocale::default();
            }
        });
    }

    public function publicProjection(): array
    {
        $data = $this->attributesToArray();
        $definitions = new EloquentContentDefinitions(config('filament-menu.models.menu_component'), config('filament-menu.models.menu_widget'), $this->getConnectionName());
        $data['content'] = app(ContentOutputResolver::class)->resolve($this->content ?? [], $definitions, connection: $this->getConnectionName(), locale: $this->locale);
        $data['children'] = $this->relationLoaded('children') ? $this->children->map(fn ($child) => $child->publicProjection())->all() : [];

        return $data;
    }

    public function getTable(): string
    {
        return config('filament-menu.tables.menu_items', 'nova_menu_items');
    }

    public function menu(): BelongsTo
    {
        return $this->belongsTo(config('filament-menu.models.menu'), config('filament-menu.foreign_keys.menu_id', 'menu_id'));
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(config('filament-menu.models.menu_item'), config('filament-menu.foreign_keys.parent_id', 'parent_id'));
    }

    public function children(): HasMany
    {
        return $this->hasMany(config('filament-menu.models.menu_item'), config('filament-menu.foreign_keys.parent_id', 'parent_id'))->orderBy('weight')->orderBy('id');
    }
}
