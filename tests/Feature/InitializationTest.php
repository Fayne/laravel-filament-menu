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

it('publishes its own config without replacing host customizations', function () {
    $directory = sys_get_temp_dir() . '/menu-config-' . bin2hex(random_bytes(6));
    mkdir($directory);
    $original = app()->configPath();
    app()->useConfigPath($directory);
    try {
        (new ReflectionMethod(\Illuminate\Support\ServiceProvider::class, 'publishes'))->invoke(app()->getProvider(\Fayne\Menu\MenuServiceProvider::class), [
            dirname(__DIR__, 2) . '/config/filament-menu.php' => $directory . '/filament-menu.php',
        ], 'filament-menu-config');
        $this->artisan('cms:menu:install', ['--no-interaction' => true])->assertSuccessful();
        expect(is_file($directory . '/filament-menu.php'))->toBeTrue();
        file_put_contents($directory . '/filament-menu.php', '<?php return ["host_setting" => true];');
        $this->artisan('cms:menu:install', ['--no-interaction' => true])->assertSuccessful();
        expect(require $directory . '/filament-menu.php')->toBe(['host_setting' => true]);
    } finally {
        app()->useConfigPath($original);
        \Illuminate\Support\Facades\File::deleteDirectory($directory);
    }
});
