<?php

use Fayne\CMS\Models\Admin;
use Fayne\CMS\Models\DeploymentRecord;
use Fayne\CMS\Models\Tenant;
use Fayne\CMS\Models\TenantDomain;
use Fayne\CMS\Services\ReleaseManifestService;
use Fayne\CMS\Support\Authorization\AuthorizationContext;
use Fayne\CMS\Support\TenantContext;
use Fayne\Menu\Models\Menu;
use Illuminate\Support\Facades\Context;

it('resolves menu domains and isolates identical slugs across tenants', function () {
    $menu = Menu::create(['name' => 'Main', 'slug' => 'main', 'is_active' => true]);
    $menu->menuItems()->create(['name' => 'First tenant', 'is_active' => true]);
    TenantDomain::create(['tenant_id' => $menu->tenant_id, 'host' => 'first.test', 'is_active' => true]);
    $other = Tenant::create(['name' => 'Second', 'slug' => 'second', 'is_active' => true]);
    app(TenantContext::class)->run($other, function () use ($other) {
        $menu = Menu::create(['name' => 'Main', 'slug' => 'main', 'is_active' => true]);
        $menu->menuItems()->create(['name' => 'Second tenant', 'is_active' => true]);
        TenantDomain::create(['tenant_id' => $other->id, 'host' => 'second.test', 'is_active' => true]);
    });
    Context::forget('filament-cms.tenant_id');
    app(AuthorizationContext::class)->activate(null);
    $this->getJson('https://first.test/modules/menus/v2/frontend/main')->assertOk()->assertJsonPath('0.name', 'First tenant');
    $this->getJson('https://second.test/modules/menus/frontend/main')->assertOk()->assertJsonPath('0.name', 'Second tenant');
    $this->getJson('https://unknown.test/modules/menus/frontend/main')->assertNotFound();
    expect(app(TenantContext::class)->id())->toBeNull()->and(app(AuthorizationContext::class)->id())->toBeNull();
});

it('previews disabled menu items with authenticated scoped permissions and rejects revoked membership', function () {
    $menu = Menu::create(['name' => 'Draft', 'slug' => 'draft']);
    $menu->menuItems()->create(['name' => 'Disabled draft', 'is_active' => false]);
    TenantDomain::create(['tenant_id' => $menu->tenant_id, 'host' => 'preview.test', 'is_active' => true]);
    $admin = Admin::factory()->create(['is_active' => true]);
    test()->grantPermissions($admin, test()->permission('viewMenu'));
    $this->actingAs($admin, 'admin');
    Context::forget('filament-cms.tenant_id');
    app(AuthorizationContext::class)->activate(null);
    $this->getJson('https://preview.test/modules/menus/preview/draft')->assertOk()
        ->assertJsonPath('0.name', 'Disabled draft')->assertHeader('Cache-Control', 'no-store, private');
    expect(app(TenantContext::class)->id())->toBeNull()->and(app(AuthorizationContext::class)->id())->toBeNull();
    $admin->tenants()->detach($menu->tenant_id);
    $this->getJson('https://preview.test/modules/menus/preview/draft')->assertForbidden();
});

it('rejects unowned legacy releases even with a build credential', function () {
    config(['filament-cms.deployment.build_api_token' => 'build-secret']);
    $release = DeploymentRecord::create(['title' => 'Legacy', 'deployment_type' => 'full', 'status' => 0]);
    $manifest = app(ReleaseManifestService::class)->freeze($release);
    $url = '/headless/releases/'.$manifest->release_id.'/menus';
    $this->getJson($url)->assertForbidden();
    $this->getJson($url, ['Authorization' => 'Bearer build-secret'])->assertNotFound();
});

it('requires a build credential and matching tenant for owned frozen menus', function () {
    config(['filament-cms.deployment.build_api_token' => 'build-secret']);
    $release = DeploymentRecord::create(['title' => 'Owned', 'deployment_type' => 'full', 'status' => 0]);
    $release->forceFill(['tenant_id' => app(TenantContext::class)->requireId()])->save();
    $manifest = app(ReleaseManifestService::class)->freeze($release);
    $url = '/headless/releases/'.$manifest->release_id.'/menus';
    Context::forget('filament-cms.tenant_id');
    app(AuthorizationContext::class)->activate(null);
    $this->getJson($url)->assertForbidden();
    $this->getJson($url, ['Authorization' => 'Bearer wrong'])->assertForbidden();
    $this->getJson($url, ['Authorization' => 'Bearer build-secret'])->assertOk();
    $other = Tenant::create(['name' => 'Other', 'slug' => 'release-other', 'is_active' => true]);
    TenantDomain::where('host', 'localhost')->update(['tenant_id' => $other->id]);
    $this->getJson($url, ['Authorization' => 'Bearer build-secret'])->assertNotFound();
    expect(app(TenantContext::class)->id())->toBeNull()->and(app(AuthorizationContext::class)->id())->toBeNull();
});

it('loads menu preview authentication from the web session in a fresh request', function () {
    $menu = Menu::create(['name' => 'Draft', 'slug' => 'session-draft']);
    $admin = Admin::factory()->create(['is_active' => true]);
    test()->grantPermissions($admin, test()->permission('viewMenu'));
    $sessionKey = auth('admin')->getName();
    auth()->forgetGuards();
    Context::forget('filament-cms.tenant_id');
    app(AuthorizationContext::class)->activate(null);
    $this->withSession([$sessionKey => $admin->id])->getJson('/modules/menus/preview/session-draft')->assertOk();
});
