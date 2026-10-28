<?php

use Fayne\AdvancedText\Forms\Components\AdvancedText;
use Fayne\CMS\Models\Admin;
use Fayne\Menu\Filament\Resources\MenuComponentFieldResource\Pages\CreateMenuComponentField;
use Fayne\Menu\Filament\Resources\MenuItemResource\Pages\CreateMenuItem;
use Fayne\Menu\Filament\Resources\MenuItemResource\Pages\EditMenuItem;
use Fayne\Menu\Filament\Resources\MenuResource\Pages\CreateMenu;
use Fayne\Menu\Models\Menu;
use Fayne\Menu\Models\MenuComponent;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;

it('requires server authorization for menu creation', function () {
    $admin = Admin::create(['name' => 'Editor', 'email' => 'editor@example.test', 'password' => 'password', 'is_active' => true]);
    auth('admin')->login($admin);
    Livewire::test(CreateMenu::class)->assertForbidden();
    test()->grantPermissions($admin, [test()->permission('createMenu'), test()->permission('viewAnyMenu')]);
    expect($admin->can('create', Menu::class))->toBeTrue();
    Livewire::test(CreateMenu::class)->assertSuccessful()->fillForm(['name' => 'Main', 'slug' => 'main', 'locale' => 'en', 'is_active' => true])->call('create')->assertHasNoFormErrors();
    expect(Menu::where('slug', 'main')->count())->toBe(1);
});

it('derives the read-only item locale from its menu on creation', function () {
    $menu = Menu::create(['name' => 'Main', 'slug' => 'main', 'locale' => 'en']);
    $admin = Admin::create(['name' => 'Editor', 'email' => 'item-form@example.test', 'password' => 'password', 'is_active' => true]);
    foreach (['viewAnyMenuItem', 'createMenuItem'] as $ability) {
        test()->grantPermissions($admin, test()->permission($ability));
    }
    auth('admin')->login($admin);
    Livewire::test(CreateMenuItem::class)
        ->assertFormFieldIsDisabled('locale')
        ->fillForm(['menu_id' => $menu->id, 'name' => 'Valid', 'locale' => 'zh-CN'])->call('create')->assertHasNoFormErrors();
    expect($menu->menuItems()->count())->toBe(1)->and($menu->menuItems()->first()->locale)->toBe('en');
});

it('uses AdvancedText for item URLs and the configured internal path suggestion route', function () {
    Route::get('/fixture-path-suggestions', fn () => ['首页' => '/home'])->name('fixture-path-suggestions');
    config()->set('filament-menu.internal_path_suggestion_route', 'fixture-path-suggestions');
    $admin = Admin::create(['name' => 'Editor', 'email' => 'url-editor@example.test', 'password' => 'password', 'is_active' => true]);
    foreach (['viewAnyMenuItem', 'createMenuItem'] as $ability) {
        test()->grantPermissions($admin, test()->permission($ability));
    }
    auth('admin')->login($admin);
    Livewire::test(CreateMenuItem::class)->assertFormFieldExists('url', fn ($field) => $field instanceof AdvancedText && $field->getAdvancedTextConfig()['remoteUrl'] === route('fixture-path-suggestions'));
});

it('hides item content when there are no components and shows it when a component exists', function () {
    $admin = Admin::factory()->create(['is_active' => true]);
    foreach (['viewAnyMenuItem', 'createMenuItem'] as $ability) {
        test()->grantPermissions($admin, test()->permission($ability));
    }
    auth('admin')->login($admin);
    Livewire::test(CreateMenuItem::class)->assertFormFieldIsHidden('content');
    MenuComponent::create(['name' => 'Banner', 'slug' => 'banner']);
    Livewire::test(CreateMenuItem::class)->assertFormFieldIsVisible('content');
});

it('keeps the locale of an edited menu item immutable in its form', function () {
    $menu = Menu::create(['name' => 'Main', 'slug' => 'main', 'locale' => 'en']);
    $item = $menu->menuItems()->create(['name' => 'Original', 'locale' => 'en']);
    $admin = Admin::factory()->create(['is_active' => true]);
    foreach (['viewAnyMenuItem', 'viewMenuItem', 'updateMenuItem'] as $ability) {
        test()->grantPermissions($admin, test()->permission($ability));
    }
    auth('admin')->login($admin);
    Livewire::test(EditMenuItem::class, ['record' => $item->id])
        ->assertFormFieldIsDisabled('locale')->fillForm(['name' => 'Updated', 'locale' => 'zh_CN'])->call('save')->assertHasNoFormErrors();
    expect($item->fresh()->name)->toBe('Updated')->and($item->fresh()->locale)->toBe('en');
});

it('renders localized field type labels even when published configuration contains type keys', function () {
    $component = MenuComponent::create(['name' => 'Banner', 'slug' => 'banner']);
    $admin = Admin::factory()->create(['is_active' => true]);
    foreach (['createMenuComponentField', 'viewAnyMenuComponentField', 'viewMenuComponent'] as $ability) {
        test()->grantPermissions($admin, test()->permission($ability));
    }
    auth('admin')->login($admin);
    config(['filament-cms.fields.types.text.label' => 'text']);
    foreach (['en' => 'Text', 'zh_CN' => '文本框'] as $locale => $label) {
        app()->setLocale($locale);
        Livewire::test(CreateMenuComponentField::class)
            ->assertFormFieldExists('resolver', fn ($field) => $field->getOptions()['text'] === $label);
    }
});
