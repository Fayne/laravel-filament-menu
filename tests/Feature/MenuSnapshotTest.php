<?php

use Fayne\CMS\Models\DeploymentRecord;
use Fayne\CMS\Services\ReleaseManifestService;
use Fayne\CMS\Support\TenantContext;
use Fayne\Menu\Models\Menu;
use Fayne\Menu\Models\MenuComponent;
use Fayne\Menu\Support\MenuDefinitionsReleaseSnapshotProvider;
use Fayne\Menu\Support\MenuReleaseSnapshotProvider;
use Illuminate\Validation\ValidationException;

it('freezes menu projections independently of later edits', function () {
    $record = Menu::create(['name' => 'Original',
        'slug' => 'main', 'is_active' => true]);
    $item = $record->menuItems()->create(['name' => 'Original item', 'is_active' => true]);
    $release = DeploymentRecord::create(['title' => 'Snapshot', 'deployment_type' => 'full', 'status' => 0]);
    $manifest = app(ReleaseManifestService::class)->freeze($release);
    expect($manifest->entries['menu'])->toHaveCount(1);
    $before = $manifest->entries;
    $item->update(['name' => 'Changed item']);
    expect((new MenuReleaseSnapshotProvider)->snapshot($release)[0]['projection'][0]['name'])->toBe('Changed item');
    expect(app(ReleaseManifestService::class)->freeze($release)->entries)->toBe($before);
});

it('limits menu snapshots to the requested release locale', function () {
    foreach (['en', 'zh-CN'] as $locale) {
        Menu::create(['name' => $locale, 'slug' => 'main', 'locale' => $locale, 'is_active' => true]);
    }
    $release = DeploymentRecord::create(['title' => 'English', 'deployment_type' => 'full', 'status' => 0, 'deployment_contents' => ['locale' => 'en']]);
    $entries = (new MenuReleaseSnapshotProvider)->snapshot($release);
    expect($entries)->toHaveCount(1)->and($entries[0]['locale'])->toBe('en');
});

it('serves frozen release menus and rejects missing manifests', function () {
    $menu = Menu::create(['name' => 'Main', 'slug' => 'main', 'is_active' => true]);
    $menu->menuItems()->create(['name' => 'Frozen link', 'is_active' => true]);
    $release = DeploymentRecord::create(['title' => 'Snapshot', 'deployment_type' => 'full', 'status' => 0]);
    config(['filament-cms.deployment.build_api_token' => 'build-secret']);
    $release->forceFill(['tenant_id' => app(TenantContext::class)->requireId()])->save();
    $manifest = app(ReleaseManifestService::class)->freeze($release);
    $this->withToken('build-secret');
    $this->getJson('/headless/releases/'.$manifest->release_id.'/menus')->assertOk()->assertJsonPath('data.0.projection.0.name', 'Frozen link');
    $this->getJson('/headless/releases/missing/menus')->assertNotFound();
});

it('uses explicit release locale and connection independently of admin session', function () {
    config(['filament-cms.default_locale' => 'en', 'filament-cms.supported_locales' => ['en' => 'English', 'zh_CN' => '中文'], 'filament-cms.multilingual_enabled' => true]);
    Menu::create(['name' => 'English', 'slug' => 'main', 'locale' => 'en', 'is_active' => true]);
    Menu::create(['name' => '中文', 'slug' => 'main', 'locale' => 'zh_CN', 'is_active' => true]);
    $release = DeploymentRecord::create(['title' => 'Snapshot', 'deployment_type' => 'full', 'status' => 0]);
    session(['filament-cms.region_id' => 'unknown']);
    app()->setLocale('zh_CN');
    $entries = (new MenuReleaseSnapshotProvider)->snapshot($release);
    expect($entries)->toHaveCount(1)->and($entries[0]['locale'])->toBe('en');
    $release->deployment_contents = ['locale' => 'unsupported'];
    expect(fn () => (new MenuReleaseSnapshotProvider)->snapshot($release))->toThrow(ValidationException::class);
});

it('snapshots the configured release region instead of another database with matching ids', function () {
    config(['database.connections.release_region' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true], 'filament-cms.regions' => [['id' => 'target', 'database' => 'release_region']]]);
    (require dirname(__DIR__, 2).'/database/migrations/2026_10_20_110000_create_menu_content_tables.php')->up();
    (require dirname(__DIR__, 2).'/database/migrations/2026_10_22_110000_add_menu_translation_groups.php')->up();
    Menu::create(['name' => 'Default', 'slug' => 'default-database', 'is_active' => true]);
    (new Menu)->setConnection('release_region')->newQuery()->create(['name' => 'Target', 'slug' => 'target-database', 'is_active' => true]);
    $release = DeploymentRecord::create(['title' => 'Regional', 'deployment_type' => 'full', 'status' => 0, 'deployment_contents' => ['region' => 'target']]);
    session(['filament-cms.region_id' => 'unknown']);
    $entries = (new MenuReleaseSnapshotProvider)->snapshot($release);
    expect($entries)->toHaveCount(1)->and($entries[0]['slug'])->toBe('target-database');
});

it('compares menu settings and preserves nested output fields', function () {
    $menu = Menu::create(['name' => 'Main', 'slug' => 'main', 'settings' => ['updated_at' => 'legitimate'], 'is_active' => true]);
    $release = DeploymentRecord::create(['title' => 'First', 'deployment_type' => 'full', 'status' => 0]);
    $before = (new MenuReleaseSnapshotProvider)->snapshot($release)[0];
    $menu->update(['name' => 'Renamed']);
    $after = (new MenuReleaseSnapshotProvider)->snapshot($release)[0];
    expect($before['comparison_projection'])->not->toBe($after['comparison_projection'])
        ->and($after['comparison_projection']['menu']['settings']['updated_at'])->toBe('legitimate')
        ->and($after['projection'])->toBe([]);
});

it('freezes menu field definitions independently from later definition edits', function () {
    $component = MenuComponent::create(['name' => 'Link', 'slug' => 'link']);
    $field = $component->fields()->create(['title' => 'Label', 'slug' => 'label', 'resolver' => 'text', 'options' => ['updated_at' => 'nested']]);
    $release = DeploymentRecord::create(['title' => 'Definitions', 'deployment_type' => 'full', 'status' => 0]);
    $manifest = app(ReleaseManifestService::class)->freeze($release);
    $before = $manifest->entries['global_menu_definitions'];
    $field->update(['slug' => 'renamed', 'weight' => 1]);
    $current = (new MenuDefinitionsReleaseSnapshotProvider)->snapshot($release);
    expect($current)->not->toBe($before)
        ->and(app(ReleaseManifestService::class)->freeze($release)->entries['global_menu_definitions'])->toBe($before)
        ->and($current[0]['projection']['component'][0]['fields'][0]['options']['updated_at'])->toBe('nested');
});
