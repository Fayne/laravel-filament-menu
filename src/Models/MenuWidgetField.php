<?php

namespace Fayne\Menu\Models;

use Fayne\CMS\Support\Concerns\BelongsToTenant;
use Fayne\CMS\Support\Concerns\RegionAware;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MenuWidgetField extends Model
{
    use BelongsToTenant;
    use RegionAware;

    protected $guarded = ['id'];

    protected $casts = ['options' => 'array', 'rules' => 'array'];

    public function getTable(): string
    {
        return config('filament-menu.tables.menu_widget_fields', 'nova_menu_widget_fields');
    }

    public function widget(): BelongsTo
    {
        return $this->belongsTo(config('filament-menu.models.menu_widget'), 'menu_widget_id');
    }
}
