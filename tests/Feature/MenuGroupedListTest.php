<?php

use Fayne\CMS\Models\Admin;
use Fayne\CMS\Models\Tenant;
use Fayne\CMS\Support\TenantContext;
use Fayne\Menu\Filament\Resources\MenuResource;
use Fayne\Menu\Filament\Resources\MenuResource\Pages\ListMenus;
use Fayne\Menu\Models\Menu;
use Livewire\Livewire;

it('groups menus by translation family and filters by every language in that family', function () {
    config(['filament-cms.default_locale' => 'en', 'filament-cms.multilingual_enabled' => true, 'filament-cms.supported_locales' => ['en' => 'English', 'zh_CN' => '简体中文']]);
    $admin = Admin::factory()->create(['is_active' => true]);
    test()->grantPermissions($admin, test()->permission('viewAnyMenu'));
    $zh = Menu::create(['name' => '中文导航', 'slug' => 'main', 'locale' => 'zh_CN']);
    $en = Menu::create(['name' => 'English', 'slug' => 'main', 'locale' => 'en', 'translation_group_id' => $zh->translation_group_id]);
    $zhOnly = Menu::create(['name' => '中文独立', 'slug' => 'zh-only', 'locale' => 'zh_CN']);
    $enOnly = Menu::create(['name' => 'English only', 'slug' => 'en-only', 'locale' => 'en']);
    Livewire::actingAs($admin, 'admin')->test(ListMenus::class)
        ->assertCanSeeTableRecords([$en, $zhOnly, $enOnly])->assertCanNotSeeTableRecords([$zh])
        ->assertCountTableRecords(3)
        ->assertTableColumnStateSet('translation_languages', 'English / 简体中文', $en)
        ->filterTable('locale', 'zh_CN')->assertCanSeeTableRecords([$en, $zhOnly])
        ->assertCanNotSeeTableRecords([$enOnly])->assertCountTableRecords(2);
});

it('keeps language switching off the list and opens the representative tree directly', function () {
    config(['filament-cms.default_locale' => 'en', 'filament-cms.multilingual_enabled' => true, 'filament-cms.supported_locales' => ['en' => 'English', 'zh_CN' => '简体中文']]);
    $admin = Admin::factory()->create(['is_active' => true]);
    foreach (['viewAnyMenu', 'viewMenu', 'updateMenu'] as $permission) {
        test()->grantPermissions($admin, test()->permission($permission));
    }
    $en = Menu::create(['name' => 'English', 'slug' => 'main', 'locale' => 'en']);
    Menu::create(['name' => '中文', 'slug' => 'main', 'locale' => 'zh_CN', 'translation_group_id' => $en->translation_group_id]);
    Livewire::actingAs($admin, 'admin')->test(ListMenus::class)
        ->assertTableActionDoesNotExist('switchLanguage', record: $en)
        ->assertTableActionHasUrl('tree', MenuResource::getUrl('tree', ['record' => $en]), $en);
});

it('does not group or reveal menus belonging to another tenant', function () {
    config(['filament-cms.default_locale' => 'en', 'filament-cms.multilingual_enabled' => true, 'filament-cms.supported_locales' => ['en' => 'English', 'zh_CN' => '简体中文']]);
    $admin = Admin::factory()->create(['is_active' => true]);
    test()->grantPermissions($admin, test()->permission('viewAnyMenu'));
    $local = Menu::create(['name' => 'Local', 'slug' => 'main', 'locale' => 'en']);
    $other = Tenant::create(['name' => 'Other', 'slug' => 'other']);
    $foreign = app(TenantContext::class)->run($other, fn () => Menu::create(['name' => 'Foreign', 'slug' => 'main', 'locale' => 'zh_CN', 'translation_group_id' => $local->translation_group_id]));
    Livewire::actingAs($admin, 'admin')->test(ListMenus::class)
        ->assertCanSeeTableRecords([$local])->assertCanNotSeeTableRecords([$foreign])->assertCountTableRecords(1)
        ->assertTableColumnStateSet('translation_languages', 'English', $local);
});
