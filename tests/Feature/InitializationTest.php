<?php

use Fayne\Menu\Models\Menu;

it('installs without asking for or creating demo data', function () {
    $this->artisan('cms:menu:install')->assertSuccessful();
    expect(Menu::withoutGlobalScopes()->count())->toBe(0);
});

it('requires a target for unattended multi tenant demos', function () {
    \Illuminate\Support\Facades\Context::forget('filament-cms.tenant_id');
    $this->artisan('cms:menu:install', ['--demo' => true, '--no-interaction' => true])->assertFailed();
    expect(Menu::withoutGlobalScopes()->count())->toBe(0);
});

it('registers its own permissions without unrelated modules and preserves restricted defaults', function () {
    expect(\Spatie\Permission\Models\Permission::where('name', 'tenant.updateMenu')->exists())->toBeTrue()
        ->and(\Spatie\Permission\Models\Permission::where('name', 'tenant.createPage')->exists())->toBeFalse()
        ->and(\Spatie\Permission\Models\Permission::where('name', 'tenant.createEvent')->exists())->toBeFalse()
        ->and(\Spatie\Permission\Models\Permission::where('name', 'tenant.createStore')->exists())->toBeFalse();
    $editor = \Spatie\Permission\Models\Role::where('name', 'content-editor')->where('authorization_scope_id', 0)->firstOrFail();
    $viewer = \Spatie\Permission\Models\Role::where('name', 'tenant-viewer')->where('authorization_scope_id', 0)->firstOrFail();
    $manager = \Spatie\Permission\Models\Role::where('name', 'tenant-manager')->where('authorization_scope_id', 0)->firstOrFail();
    expect($editor->hasPermissionTo('tenant.updateMenu'))->toBeTrue()
        ->and($editor->hasPermissionTo('tenant.executeContentHttpMenuItem'))->toBeFalse()
        ->and($editor->hasPermissionTo('tenant.viewSensitiveContentMenuItem'))->toBeTrue()
        ->and($viewer->hasPermissionTo('tenant.viewAnyMenu'))->toBeTrue()
        ->and($viewer->hasPermissionTo('tenant.viewSensitiveContentMenuItem'))->toBeFalse()
        ->and($manager->hasPermissionTo('tenant.executeContentHttpMenuItem'))->toBeTrue();
});
