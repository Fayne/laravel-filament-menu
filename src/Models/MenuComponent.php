<?php

namespace Fayne\Menu\Models;

use Fayne\CMS\Support\Concerns\BelongsToTenant;
use Fayne\CMS\Support\Concerns\RegionAware;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MenuComponent extends Model
{
    use BelongsToTenant;
    use RegionAware;

    protected $guarded = ['id'];

    protected $casts = ['mockup' => 'array', 'specifications' => 'array'];

    public function getTable(): string
    {
        return config('filament-menu.tables.menu_components', 'nova_menu_components');
    }

    public function fields(): HasMany
    {
        return $this->hasMany(config('filament-menu.models.menu_component_field'), 'nova_menu_component_id')->orderBy('weight')->orderBy('id');
    }
}
