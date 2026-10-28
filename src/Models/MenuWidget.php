<?php

namespace Fayne\Menu\Models;

use Fayne\CMS\Support\Concerns\BelongsToTenant;
use Fayne\CMS\Support\Concerns\RegionAware;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MenuWidget extends Model
{
    use BelongsToTenant;
    use RegionAware;

    protected $guarded = ['id'];

    protected $casts = ['mockup' => 'array', 'specifications' => 'array'];

    public function getTable(): string
    {
        return config('filament-menu.tables.menu_widgets', 'nova_menu_widgets');
    }

    public function fields(): HasMany
    {
        return $this->hasMany(config('filament-menu.models.menu_widget_field'), 'menu_widget_id')->orderBy('weight')->orderBy('id');
    }
}
