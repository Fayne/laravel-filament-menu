<?php

use Fayne\Menu\Filament\Resources\MenuComponentFieldResource;
use Fayne\Menu\Filament\Resources\MenuComponentResource;
use Fayne\Menu\Filament\Resources\MenuItemResource;
use Fayne\Menu\Filament\Resources\MenuResource;
use Fayne\Menu\Filament\Resources\MenuWidgetFieldResource;
use Fayne\Menu\Filament\Resources\MenuWidgetResource;
use Fayne\Menu\Models\Menu;
use Fayne\Menu\Models\MenuComponent;
use Fayne\Menu\Models\MenuComponentField;
use Fayne\Menu\Models\MenuItem;
use Fayne\Menu\Models\MenuWidget;
use Fayne\Menu\Models\MenuWidgetField;

return [
    'models' => [
        'menu' => Menu::class, 'menu_item' => MenuItem::class,
        'menu_component' => MenuComponent::class, 'menu_component_field' => MenuComponentField::class,
        'menu_widget' => MenuWidget::class, 'menu_widget_field' => MenuWidgetField::class,
    ],
    'resources' => [
        'menu_item' => MenuItemResource::class,
        'menu_component_field' => MenuComponentFieldResource::class,
        'menu' => MenuResource::class,
        'menu_component' => MenuComponentResource::class,
        'menu_widget_field' => MenuWidgetFieldResource::class,
        'menu_widget' => MenuWidgetResource::class,
    ],
    'resource_slugs' => [],
    'tables' => [
        'menus' => 'filament_menus', 'menu_items' => 'filament_menu_items', 'menu_components' => 'filament_menu_components',
        'menu_component_fields' => 'filament_menu_component_fields', 'menu_widgets' => 'filament_menu_widgets', 'menu_widget_fields' => 'filament_menu_widget_fields',
    ],
    'foreign_keys' => ['menu_id' => 'menu_id', 'parent_id' => 'parent_id'],
    'menu_builder' => ['max_depth' => 5],
    'internal_path_suggestion_route' => env('FILAMENT_PATH_SUGGESTION_ROUTE_NAME'),
    'use_default_frontend_routes' => true,
];
