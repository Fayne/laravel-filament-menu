<?php

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
    'tables' => [
        'menus' => 'filament_menus', 'menu_items' => 'filament_menu_items', 'menu_components' => 'filament_menu_components',
        'menu_component_fields' => 'filament_menu_component_fields', 'menu_widgets' => 'filament_menu_widgets', 'menu_widget_fields' => 'filament_menu_widget_fields',
    ],
    'foreign_keys' => ['menu_id' => 'menu_id', 'parent_id' => 'parent_id'],
    'menu_builder' => ['max_depth' => 5],
    'internal_path_suggestion_route' => env('FILAMENT_PATH_SUGGESTION_ROUTE_NAME'),
    'use_default_frontend_routes' => true,
];
