<?php

use Fayne\Menu\Models\Menu;
use Illuminate\Support\Facades\Schema;

it('uses the configured content language instead of the interface language when no locale is requested', function () {
    config(['filament-cms.multilingual_enabled' => true, 'filament-cms.default_locale' => 'en']);
    app()->setLocale('zh_CN');
    $menu = Menu::create(['name' => 'Main', 'slug' => 'default-language', 'locale' => 'en', 'is_active' => true]);
    $menu->menuItems()->create(['name' => 'English link', 'locale' => 'en', 'is_active' => true]);

    $this->getJson('/modules/menus/frontend/default-language')->assertOk()->assertJsonPath('0.name', 'English link');
});

it('returns only the active tree for the requested locale', function () {
    config(['filament-cms.multilingual_enabled' => true]);
    $menu = Menu::create(['name' => 'Main', 'slug' => 'main', 'locale' => 'en', 'is_active' => true]);
    $menu->menuItems()->create(['name' => 'English', 'locale' => 'en', 'url' => '/about', 'is_active' => true]);
    $menu->menuItems()->create(['name' => 'Wrong locale', 'locale' => 'zh-CN']);
    $hidden = $menu->menuItems()->create(['name' => 'Hidden', 'is_active' => false]);
    $menu->menuItems()->create(['name' => 'Hidden child', 'parent_id' => $hidden->id, 'is_active' => true]);
    $this->getJson('/modules/menus/frontend/main?locale=en')->assertOk()->assertJsonCount(1)->assertJsonPath('0.name', 'English');
    $this->getJson('/modules/menus/frontend/main?locale=zh-CN')->assertNotFound();
    $menu->update(['is_active' => false]);
    $this->getJson('/modules/menus/frontend/main?locale=en')->assertNotFound();
});

it('does not expose anonymous menu previews', function () {
    Menu::create(['name' => 'Main', 'slug' => 'main']);
    $this->getJson('/modules/menus/preview/main')->assertUnauthorized();
});

it('does not expose loaded administrative relations in item projections', function () {
    $menu = Menu::create(['name' => 'Internal menu', 'slug' => 'main']);
    $item = $menu->menuItems()->create(['name' => 'Link']);
    $item->load('menu');
    expect($item->publicProjection())->not->toHaveKey('menu');
});

it('keeps legacy zero-parent roots visible in the public menu tree', function () {
    $menu = Menu::create(['name' => 'Legacy', 'slug' => 'legacy', 'locale' => 'en', 'is_active' => true]);
    Schema::withoutForeignKeyConstraints(fn () => $menu->menuItems()->create(['name' => 'Legacy root', 'parent_id' => 0, 'is_active' => true]));
    $this->getJson('/modules/menus/frontend/legacy')->assertOk()->assertJsonPath('0.name', 'Legacy root');
});
