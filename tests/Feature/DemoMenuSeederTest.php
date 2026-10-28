<?php

use Fayne\CMS\Models\Tenant;
use Fayne\CMS\Support\TenantContext;
use Fayne\Menu\Database\Seeders\DemoMenuSeeder;
use Fayne\Menu\Models\Menu;
use Fayne\Menu\Models\MenuItem;
use Illuminate\Support\Facades\Context;
use Illuminate\Validation\ValidationException;

it('creates bilingual linked e-commerce navigation trees without publishing them', function () {
    $this->seed(DemoMenuSeeder::class);

    expect(Menu::count())->toBe(4);
    foreach (['demo-main-navigation', 'demo-footer-navigation'] as $slug) {
        $english = Menu::where('slug', $slug)->where('locale', 'en')->firstOrFail();
        $chinese = Menu::where('slug', $slug)->where('locale', 'zh_CN')->firstOrFail();
        expect($english->translation_group_id)->toBe($chinese->translation_group_id)
            ->and($english->is_active)->toBeFalse()->and($chinese->is_active)->toBeFalse()
            ->and($english->menuItems()->count())->toBe($chinese->menuItems()->count())->toBeGreaterThan(0);
        foreach ([$english, $chinese] as $menu) {
            foreach ($menu->menuItems as $item) {
                expect($item->locale)->toBe($menu->locale)->and($item->tenant_id)->toBe($menu->tenant_id);
                if ($item->parent_id !== null) {
                    expect($item->parent->menu_id)->toBe($menu->id)
                        ->and($item->level)->toBe($item->parent->level + 1);
                }
            }
        }
    }
    expect(MenuItem::max('level'))->toBe(3);
    expect(MenuItem::count())->toBe(60)
        ->and(Menu::where('slug', 'demo-main-navigation')->where('locale', 'en')->firstOrFail()->publicTree()->first()->name)->toBe('Home')
        ->and(Menu::where('slug', 'demo-main-navigation')->where('locale', 'zh_CN')->firstOrFail()->publicTree()->first()->name)->toBe('首页');
});

it('keeps edited menus and items unchanged when the demo seeder runs again', function () {
    $this->seed(DemoMenuSeeder::class);
    $menu = Menu::where('slug', 'demo-main-navigation')->where('locale', 'en')->firstOrFail();
    $menu->update(['name' => 'My navigation']);
    $menu->menuItems()->firstOrFail()->update(['name' => 'My link', 'url' => '/custom']);
    $before = Menu::orderBy('id')->get()->toArray();
    $items = MenuItem::orderBy('id')->get()->toArray();

    $this->seed(DemoMenuSeeder::class);

    expect(Menu::orderBy('id')->get()->toArray())->toBe($before)
        ->and(MenuItem::orderBy('id')->get()->toArray())->toBe($items);
});

it('does not populate or overwrite a pre-existing menu with a reserved demo slug', function () {
    $existing = Menu::create(['name' => 'Real menu', 'slug' => 'demo-main-navigation', 'locale' => 'en']);
    $this->seed(DemoMenuSeeder::class);

    expect($existing->fresh()->name)->toBe('Real menu')->and($existing->menuItems()->count())->toBe(0)
        ->and(Menu::where('slug', 'demo-main-navigation')->count())->toBe(1)
        ->and(Menu::where('slug', 'demo-footer-navigation')->count())->toBe(2);
});

it('seeds only the selected tenant and supports the configured Chinese locale spelling', function () {
    $other = Tenant::create(['name' => 'Other', 'slug' => 'other']);
    app(TenantContext::class)->run($other, function () {
        config(['filament-cms.supported_locales' => ['en' => 'English', 'zh-CN' => '中文']]);
        $this->seed(DemoMenuSeeder::class);
        expect(Menu::count())->toBe(4)->and(Menu::where('locale', 'zh-CN')->count())->toBe(2);
    });

    expect(Menu::count())->toBe(0)->and(MenuItem::count())->toBe(0);
});

it('rejects unsupported bilingual settings before writing any demo content', function () {
    config(['filament-cms.supported_locales' => ['en' => 'English']]);
    expect(fn () => $this->seed(DemoMenuSeeder::class))->toThrow(ValidationException::class);
    expect(Menu::count())->toBe(0)->and(MenuItem::count())->toBe(0);
});

it('asks before creating test data and leaves content untouched when declined', function () {
    $this->artisan('cms:menu:init')
        ->expectsConfirmation(__('filament-menu::commands.confirm_demo'), 'no')->assertSuccessful();
    expect(Menu::count())->toBe(0);
});

it('creates demo data in the explicitly selected tenant only after confirmation', function () {
    $other = Tenant::create(['name' => 'Other', 'slug' => 'other']);
    $this->artisan('cms:menu:init', ['--tenant' => $other->slug])
        ->expectsConfirmation(__('filament-menu::commands.confirm_demo'), 'yes')->assertSuccessful();
    expect(Menu::count())->toBe(0);
    app(TenantContext::class)->run($other, fn () => expect(Menu::count())->toBe(4));
});

it('does not create demo content during noninteractive initialization', function () {
    $this->artisan('cms:menu:init', ['--no-interaction' => true])->assertSuccessful();
    expect(Menu::count())->toBe(0);
});

it('asks which tenant receives demo data when run without an existing tenant context', function () {
    $tenant = Tenant::create(['name' => 'Shop', 'slug' => 'shop']);
    $second = Tenant::create(['name' => 'Second', 'slug' => 'second']);
    $unselected = Tenant::create(['name' => 'Unselected', 'slug' => 'unselected']);
    $labels = Tenant::where('is_active', true)->orderBy('id')->get()->map(fn ($tenant) => $tenant->name.' ('.$tenant->slug.')')->all();
    Context::forget('filament-cms.tenant_id');

    $this->artisan('cms:menu:init')
        ->expectsConfirmation(__('filament-menu::commands.confirm_demo'), 'yes')
        ->expectsChoice(__('filament-menu::commands.select_tenant'), ['Shop (shop)', 'Second (second)'], $labels)->assertSuccessful();

    expect(app(TenantContext::class)->id())->toBeNull();
    app(TenantContext::class)->run($tenant, fn () => expect(Menu::count())->toBe(4));
    app(TenantContext::class)->run($second, fn () => expect(Menu::count())->toBe(4));
    app(TenantContext::class)->run($unselected, fn () => expect(Menu::count())->toBe(0));
});

it('rejects an unknown tenant without falling back to the current tenant', function () {
    $this->artisan('cms:menu:init', ['--tenant' => 'missing-shop'])
        ->expectsConfirmation(__('filament-menu::commands.confirm_demo'), 'yes')->assertFailed();
    expect(Menu::count())->toBe(0);
});

it('supports single-site initialization without creating a tenant', function () {
    config(['filament-cms.tenancy.enabled' => false]);
    $this->artisan('cms:menu:init')
        ->expectsConfirmation(__('filament-menu::commands.confirm_demo'), 'yes')->assertSuccessful();

    expect(Menu::count())->toBe(4)->and(Menu::where('tenant_id', 0)->count())->toBe(4);
});

it('keeps demo menus private until explicitly enabled and serves separate language trees', function () {
    config(['filament-cms.multilingual_enabled' => true]);
    $this->seed(DemoMenuSeeder::class);
    $this->getJson('/modules/menus/frontend/demo-main-navigation?locale=en')->assertNotFound();
    Menu::where('slug', 'demo-main-navigation')->get()->each->update(['is_active' => true]);

    $this->getJson('/modules/menus/frontend/demo-main-navigation?locale=en')->assertOk()->assertJsonPath('0.name', 'Home');
    $this->getJson('/modules/menus/frontend/demo-main-navigation?locale=zh_CN')->assertOk()->assertJsonPath('0.name', '首页');
});
