<?php

namespace Fayne\Menu\Tests\Fixtures;

use Fayne\Menu\Filament\Resources\MenuComponentFieldResource;
use Fayne\Menu\Filament\Resources\MenuComponentResource;
use Fayne\Menu\Filament\Resources\MenuItemResource;
use Fayne\Menu\Filament\Resources\MenuResource;
use Fayne\Menu\Filament\Resources\MenuResource\Pages\EditMenu;
use Fayne\Menu\Filament\Resources\MenuWidgetFieldResource;
use Fayne\Menu\Filament\Resources\MenuWidgetResource;
use Fayne\Menu\Models\Menu;
use Fayne\Menu\Models\MenuComponent;
use Fayne\Menu\Models\MenuComponentField;
use Fayne\Menu\Models\MenuItem;
use Fayne\Menu\Models\MenuWidget;
use Fayne\Menu\Models\MenuWidgetField;
use Fayne\Menu\Tests\TestCase;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class HostMenu extends Menu {}

class HostMenuResource extends MenuResource
{
    protected static ?string $slug = 'native-menus';

    public static function form(Schema $schema): Schema
    {
        $schema = parent::form($schema);

        return $schema->components([...$schema->getComponents(), TextInput::make('settings.host_note')]);
    }

    public static function getPages(): array
    {
        return [...parent::getPages(), 'edit' => HostEditMenu::route('/{record}/edit')];
    }
}

class HostMenuItem extends MenuItem
{
    public function publicProjection(): array
    {
        return [...parent::publicProjection(), 'host_projection' => true];
    }
}

class HostMenuItemResource extends MenuItemResource {}

class HostMenuComponent extends MenuComponent {}

class HostMenuComponentResource extends MenuComponentResource {}

class HostMenuComponentField extends MenuComponentField {}

class HostMenuComponentFieldResource extends MenuComponentFieldResource
{
    public static function fieldFormComponents(): array
    {
        return [...parent::fieldFormComponents(), TextInput::make('host_marker')->dehydrated(false)];
    }
}

class HostMenuWidget extends MenuWidget {}

class HostMenuWidgetResource extends MenuWidgetResource {}

class HostMenuWidgetField extends MenuWidgetField {}

class HostMenuWidgetFieldResource extends MenuWidgetFieldResource
{
    public static function fieldFormComponents(): array
    {
        return [...parent::fieldFormComponents(), TextInput::make('host_marker')->dehydrated(false)];
    }
}

class ExtensionTestCase extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('filament-menu.models.menu', HostMenu::class);
        $app['config']->set('filament-menu.resources.menu', HostMenuResource::class);
        $app['config']->set('filament-menu.resource_slugs.menu', 'host-menus');
        $app['config']->set('filament-menu.models.menu_item', HostMenuItem::class);
        $app['config']->set('filament-menu.resources.menu_item', HostMenuItemResource::class);
        $app['config']->set('filament-menu.resource_slugs.menu_item', 'host-menu-items');
        $app['config']->set('filament-menu.models.menu_component', HostMenuComponent::class);
        $app['config']->set('filament-menu.resources.menu_component', HostMenuComponentResource::class);
        $app['config']->set('filament-menu.resource_slugs.menu_component', 'host-menu-components');
        $app['config']->set('filament-menu.models.menu_component_field', HostMenuComponentField::class);
        $app['config']->set('filament-menu.resources.menu_component_field', HostMenuComponentFieldResource::class);
        $app['config']->set('filament-menu.resource_slugs.menu_component_field', 'host-menu-component-fields');
        $app['config']->set('filament-menu.models.menu_widget', HostMenuWidget::class);
        $app['config']->set('filament-menu.resources.menu_widget', HostMenuWidgetResource::class);
        $app['config']->set('filament-menu.resource_slugs.menu_widget', 'host-menu-widgets');
        $app['config']->set('filament-menu.models.menu_widget_field', HostMenuWidgetField::class);
        $app['config']->set('filament-menu.resources.menu_widget_field', HostMenuWidgetFieldResource::class);
        $app['config']->set('filament-menu.resource_slugs.menu_widget_field', 'host-menu-widget-fields');
    }
}

class HostEditMenu extends EditMenu
{
    public function getTitle(): string
    {
        return 'Host menu editor';
    }
}
