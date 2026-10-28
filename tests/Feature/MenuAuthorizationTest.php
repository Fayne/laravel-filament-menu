<?php

use Fayne\CMS\Models\Admin;
use Fayne\CMS\Support\TenantContext;
use Fayne\Menu\Models\MenuItem;
use Illuminate\Support\Facades\Gate;

it('requires separate item permissions for protected content actions', function () {
    $admin = Admin::create(['name' => 'Editor', 'email' => 'special-menu@example.test', 'password' => 'password', 'is_active' => true]);
    $item = (new MenuItem)->forceFill(['tenant_id' => app(TenantContext::class)->id()]);
    foreach (['viewSensitiveContentMenuItem', 'executeContentHttpMenuItem'] as $permission) {
        test()->permission($permission);
    }
    expect(Gate::forUser($admin)->allows('viewSensitiveContent', $item))->toBeFalse();
    test()->grantPermissions($admin, ['viewSensitiveContentMenuItem', 'executeContentHttpMenuItem']);
    expect(Gate::forUser($admin)->allows('viewSensitiveContent', $item))->toBeTrue()
        ->and(Gate::forUser($admin)->allows('executeContentHttp', $item))->toBeTrue();
});
