<?php

namespace Fayne\Menu\Tests\Feature;

require_once __DIR__.'/../Fixtures/ResourceExtensions.php';

use Fayne\CMS\Models\Admin;
use Fayne\CMS\Models\Tenant;
use Fayne\CMS\Support\TenantContext;
use Fayne\Menu\Filament\Pages\MenuTree;
use Fayne\Menu\Filament\Resources\MenuComponentResource\Pages\EditMenuComponent;
use Fayne\Menu\Filament\Resources\MenuComponentResource\RelationManagers\FieldsRelationManager;
use Fayne\Menu\Filament\Resources\MenuResource;
use Fayne\Menu\Filament\Resources\MenuResource\Pages\CreateMenu;
use Fayne\Menu\Filament\Resources\MenuResource\Pages\EditMenu;
use Fayne\Menu\Models\Menu;
use Fayne\Menu\Tests\Fixtures\ExtensionTestCase;
use Fayne\Menu\Tests\Fixtures\HostMenu;
use Fayne\Menu\Tests\Fixtures\HostMenuComponent;
use Fayne\Menu\Tests\Fixtures\HostMenuComponentFieldResource;
use Fayne\Menu\Tests\Fixtures\HostMenuItem;
use Fayne\Menu\Tests\Fixtures\HostMenuItemResource;
use Fayne\Menu\Tests\Fixtures\HostMenuResource;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Resources\ResourceConfiguration;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

class ResourceExtensionTest extends ExtensionTestCase
{
    private function editor(): Admin
    {
        $admin = Admin::factory()->create(['is_active' => true]);
        $this->grantPermissions($admin, Permission::where('name', 'like', 'tenant.%')->get()->all());
        $this->actingAs($admin, 'admin');
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        return $admin;
    }

    public function test_plugin_and_inherited_pages_resolve_every_configured_resource(): void
    {
        $registered = Filament::getPanel('admin')->getResources();
        foreach (config('filament-menu.resources') as $key => $resource) {
            $this->assertContains($resource, $registered);
            $this->assertSame(config('filament-menu.models.'.$key), $resource::getModel());
            $this->assertSame('host-'.str_replace('_', '-', $key).'s', $resource::getSlug());
            foreach ($resource::getRelations() as $manager) {
                $this->assertContains($manager::getRelatedResource(), config('filament-menu.resources'));
            }
            foreach ($resource::getPages() as $page) {
                $this->assertSame($resource, $page->getPage()::getResource());
            }
        }
        $this->assertNotContains(MenuResource::class, $registered);
    }

    public function test_slug_fallback_preserves_native_host_resource_slug(): void
    {
        config(['filament-menu.resource_slugs.menu' => null]);
        $this->assertSame('native-menus', HostMenuResource::getSlug());
        config(['filament-menu.resource_slugs.menu' => 'configured-menus']);
        $this->assertSame('configured-menus', HostMenuResource::getSlug());
    }

    public function test_builtin_create_and_edit_use_host_form_and_model(): void
    {
        $this->editor();
        $page = Livewire::test(CreateMenu::class);
        $page->fillForm(['name' => 'Host record', 'slug' => 'host', 'deep' => 3, 'locale' => 'en', 'is_active' => false, 'settings' => ['host_note' => 'Created by host']])
            ->call('create')->assertHasNoFormErrors();
        $record = HostMenu::where('name', 'Host record')->firstOrFail();
        $this->assertSame('Created by host', $record->settings['host_note']);
        Livewire::test(EditMenu::class, ['record' => $record->id])
            ->assertFormSet(['settings.host_note' => 'Created by host'])
            ->fillForm(['name' => 'Edited by host'])->call('save')->assertHasNoFormErrors();
        $this->assertSame('Edited by host', $record->fresh()->name);
        $this->get(HostMenuResource::getUrl('edit', ['record' => $record]))->assertOk()->assertSee('Host menu editor');
    }

    public function test_configured_related_field_resource_is_used_for_creation_links(): void
    {
        $this->editor();
        $component = HostMenuComponent::create(['name' => 'Hero', 'slug' => 'host-hero']);
        $manager = FieldsRelationManager::class;
        $fieldResource = HostMenuComponentFieldResource::class;
        $this->assertSame($fieldResource, $manager::getRelatedResource());
        $view = Livewire::test($manager, ['ownerRecord' => $component, 'pageClass' => EditMenuComponent::class]);
        $url = $fieldResource::getUrl('create', [$component->fields()->getForeignKeyName() => $component->id]);
        $this->assertSame($url, $view->instance()->getTable()->getHeaderActions()[0]->getUrl());
        $field = $component->fields()->create(['title' => 'Heading', 'slug' => 'heading', 'resolver' => 'text']);
        $view->mountAction(TestAction::make('edit')->table($field))
            ->assertFormFieldExists('host_marker');

    }

    public function test_models_and_resources_can_be_replaced_independently(): void
    {
        config(['filament-menu.resources.menu' => MenuResource::class]);
        $this->assertSame(HostMenu::class, MenuResource::getModel());
        $this->assertSame(MenuResource::class, CreateMenu::getResource());
        config(['filament-menu.resources.menu' => HostMenuResource::class, 'filament-menu.models.menu' => Menu::class]);
        $this->assertSame(Menu::class, HostMenuResource::getModel());
    }

    public function test_custom_resource_still_enforces_tenant_record_scope(): void
    {
        $this->editor();
        $tenant = Tenant::create(['name' => 'Other', 'slug' => 'other-resource', 'is_active' => true]);
        $foreign = app(TenantContext::class)->run($tenant, fn () => HostMenu::create(['name' => 'Foreign', 'slug' => 'foreign']));
        Livewire::test(EditMenu::class, ['record' => $foreign->id])->assertNotFound();
    }

    public function test_language_switch_redirects_to_the_host_resource(): void
    {
        config(['filament-cms.multilingual_enabled' => true, 'filament-cms.supported_locales' => ['en' => 'English', 'zh_CN' => '简体中文']]);
        $this->editor();
        $en = HostMenu::create(['name' => 'English', 'slug' => 'language', 'locale' => 'en']);
        $zh = HostMenu::create(['name' => '中文', 'slug' => 'language', 'locale' => 'zh_CN', 'translation_group_id' => $en->translation_group_id]);
        Livewire::test(EditMenu::class, ['record' => $en->id])
            ->callAction('switchLanguage', data: ['locale' => 'zh_CN'])->assertRedirect(HostMenuResource::getUrl('edit', ['record' => $zh]));
    }

    public function test_tree_links_language_switch_and_public_tree_use_host_classes(): void
    {
        config(['filament-cms.multilingual_enabled' => true, 'filament-cms.supported_locales' => ['en' => 'English', 'zh_CN' => '简体中文']]);
        $this->editor();
        $menu = HostMenu::create(['name' => 'Main', 'slug' => 'host-tree', 'locale' => 'en', 'is_active' => true]);
        $item = $menu->menuItems()->create(['name' => 'Link', 'is_active' => true]);
        $this->assertInstanceOf(HostMenuItem::class, $item);
        $tree = Livewire::test(MenuTree::class, ['record' => $menu->id]);
        $this->assertSame(HostMenuItemResource::getUrl('edit', ['record' => $item]), $tree->get('tree')[0]['edit_url']);
        $zh = HostMenu::create(['name' => '中文', 'slug' => 'host-tree', 'locale' => 'zh_CN', 'translation_group_id' => $menu->translation_group_id]);
        $tree->callAction('switchLanguage')->assertRedirect(HostMenuResource::getUrl('tree', ['record' => $zh]));
        $this->getJson('/modules/menus/v2/frontend/host-tree?locale=en')->assertOk()->assertJsonPath('0.host_projection', true);
    }

    public function test_unconfigured_slug_keeps_filament_panel_configuration(): void
    {
        config(['filament-menu.resource_slugs.menu' => null]);
        $panel = Filament::getPanel('admin');
        Filament::setCurrentPanel($panel);
        $panel->resources([ResourceConfiguration::make(HostMenuResource::class, 'special')->slug('panel-content')]);
        HostMenuResource::withConfiguration('special', function () use ($panel): void {
            $this->assertSame('panel-content', HostMenuResource::getSlug($panel));
        });
    }
}
