<?php

use Fayne\CMS\Contracts\ReleaseSnapshotProvider;
use Fayne\CMS\Models\DeploymentRecord;
use Fayne\CMS\Models\Tenant;
use Fayne\CMS\Services\ReleaseManifestService;
use Fayne\CMS\Support\ReleaseSnapshotRegistry;
use Fayne\CMS\Support\TenantContext;
use Fayne\Menu\Models\Menu;
use Fayne\Menu\Models\MenuComponent;
use Fayne\Menu\Models\MenuItem;
use Fayne\Menu\Tests\TestCase;
use Illuminate\Support\Str;

class WholeSiteMenuSnapshotTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        foreach (['cn', 'us'] as $region) {
            $app['config']->set('database.connections.' . $region, ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true]);
        }
        $app['config']->set('filament-cms.regions', [['id' => 'cn', 'database' => 'cn'], ['id' => 'us', 'database' => 'us']]);
        $app['config']->set('filament-cms.multilingual_enabled', true);
        $app['config']->set('filament-cms.supported_locales', ['en' => 'English', 'zh_CN' => 'Chinese']);
        $app['config']->set('filament-cms.publication_routing', ['region_mode' => 'path', 'base_host' => 'site.test', 'regions' => [['id' => 'cn', 'path_prefix' => '/cn'], ['id' => 'us', 'path_prefix' => '/us']], 'language_prefixes' => ['en' => '/en', 'zh_CN' => '/zh']]);
    }

    public function test_freezes_real_regional_menus_and_definitions_and_resolves_only_same_scope_links(): void
    {
        Tenant::findOrFail(app(TenantContext::class)->requireId())->domains()->create(['host' => 'site.test', 'is_active' => true]);
        foreach (['cn', 'us'] as $region) {
            MenuComponent::on($region)->create(['name' => $region . ' definition', 'slug' => 'regional']);
            $group = (string) Str::uuid();
            foreach (['en', 'zh_CN'] as $locale) {
                $menu = Menu::on($region)->create(['name' => $region . ' ' . $locale, 'slug' => 'main', 'locale' => $locale, 'translation_group_id' => $group, 'is_active' => true]);
                foreach (['/about', 'about?q=1#title', 'https://outside.test/about'] as $url) {
                    MenuItem::on($region)->create(['menu_id' => $menu->id, 'name' => $locale, 'url' => $url, 'locale' => $locale, 'is_active' => true]);
                }
            }
        }
        app(ReleaseSnapshotRegistry::class)->register(new class implements ReleaseSnapshotProvider
        {
            public function entityType(): string
            {
                return 'page';
            }

            public function snapshot(DeploymentRecord $release): array
            {
                return [['entity_type' => 'page', 'entity_id' => '1', 'path' => '/about', 'projection' => ['path' => '/about']]];
            }
        });
        $release = DeploymentRecord::create(['title' => 'All menus', 'deployment_type' => 'frontend', 'deployment_contents' => ['publication_mode' => 'whole_site']]);
        $manifest = app(ReleaseManifestService::class)->freeze($release);
        $rows = $manifest->entries['menu'];
        $this->assertCount(4, $rows);
        $this->assertSame('1', $rows[0]['entity_id']);
        $this->assertSame('1', $rows[2]['entity_id']);
        $this->assertSame('cn en', $rows[0]['menu']['name']);
        $this->assertSame('us en', $rows[2]['menu']['name']);
        $this->assertSame(['https://site.test/cn/en/about', 'https://site.test/cn/en/about?q=1#title', 'https://outside.test/about'], array_column($rows[0]['projection'], 'url'));
        $this->assertSame('https://site.test/us/zh/about', $rows[3]['projection'][0]['url']);
        $this->assertArrayHasKey('translation_group_id', $rows[0]);
        $this->assertArrayNotHasKey('translation_group_id', json_decode($manifest->public_payload, true)['entries']['menu'][0]);
        $definitions = $manifest->entries['global_menu_definitions'];
        $this->assertSame('cn definition', $definitions[0]['projection']['component'][0]['name']);
        $this->assertSame('us definition', $definitions[2]['projection']['component'][0]['name']);
        $this->assertCount(4, $manifest->entries['global_settings']);
    }
    public function test_v2_menu_api_requires_scope_and_returns_frozen_scope_only(): void
    {
        $tenant = Tenant::findOrFail(app(TenantContext::class)->requireId());
        $tenant->domains()->create(['host' => 'site.test', 'is_active' => true]);
        foreach (['cn', 'us'] as $region) {
            foreach (['en', 'zh_CN'] as $locale) {
                Menu::on($region)->create(['name' => $region . ' ' . $locale, 'slug' => 'main', 'locale' => $locale, 'is_active' => true]);
            }
        }
        $release = DeploymentRecord::create(['title' => 'Menu API', 'deployment_type' => 'full', 'deployment_contents' => ['publication_mode' => 'whole_site']]);
        $release->forceFill(['tenant_id' => $tenant->id])->save();
        $manifest = app(ReleaseManifestService::class)->freeze($release);
        config(['filament-cms.deployment.build_api_token' => 'build-secret', 'filament-cms.publication_routing' => null]);
        $this->withToken('build-secret');
        $url = '/headless/releases/' . $manifest->release_id . '/menus';
        $this->getJson($url)->assertUnprocessable();
        $this->getJson($url . '?scope_id=foreign')->assertUnprocessable();
        $this->getJson($url . '?scope_id=' . $manifest->context['scopes'][3]['scope_id'])->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.menu.name', 'us zh_CN')->assertJsonMissingPath('data.0.translation_group_id');
    }

}
