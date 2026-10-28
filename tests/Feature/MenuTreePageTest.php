<?php

use Fayne\CMS\Models\Admin;
use Fayne\Menu\Filament\Pages\MenuTree;
use Fayne\Menu\Filament\Resources\MenuResource;
use Fayne\Menu\Models\Menu;
use Fayne\Menu\Models\MenuComponent;
use Filament\Forms\Components\Select;
use Livewire\Livewire;

it('authorizes tree updates and persists cross-level moves through the page', function () {
    $menu = Menu::create(['name' => 'Main', 'slug' => 'main']);
    $one = $menu->menuItems()->create(['name' => 'One']);
    $two = $menu->menuItems()->create(['name' => 'Two']);
    $admin = Admin::create(['name' => 'Editor', 'email' => 'tree@example.test', 'password' => 'password', 'is_active' => true]);
    auth('admin')->login($admin);
    Livewire::test(MenuTree::class, ['record' => $menu->id])->assertForbidden();
    foreach (['viewAnyMenu', 'viewMenu', 'updateMenu'] as $ability) {
        test()->grantPermissions($admin, test()->permission($ability));
    }
    Livewire::test(MenuTree::class, ['record' => $menu->id])->assertSuccessful()->call('saveTree', [['id' => $one->id, 'children' => [['id' => $two->id, 'children' => []]]]])->assertHasNoErrors();
    expect($two->fresh()->parent_id)->toBe($one->id)->and($two->fresh()->level)->toBe(2);
});

it('shows component level failures on the tree page without saving a partial move', function () {
    $menu = Menu::create(['name' => 'Main', 'slug' => 'main']);
    MenuComponent::create(['name' => 'Root only', 'slug' => 'root', 'specifications' => ['main' => [1]]]);
    $one = $menu->menuItems()->create(['name' => 'One', 'content' => [['layout' => 'root', 'key' => 'root', 'attributes' => []]]]);
    $two = $menu->menuItems()->create(['name' => 'Two']);
    $admin = Admin::create(['name' => 'Editor', 'email' => 'tree-error@example.test', 'password' => 'password', 'is_active' => true]);
    foreach (['viewAnyMenu', 'viewMenu', 'updateMenu'] as $ability) {
        test()->grantPermissions($admin, test()->permission($ability));
    }
    auth('admin')->login($admin);
    Livewire::test(MenuTree::class, ['record' => $menu->id])->call('saveTree', [['id' => $two->id, 'children' => [['id' => $one->id, 'children' => []]]]])->assertHasErrors(['tree']);
    expect($one->fresh()->parent_id)->toBeNull();
});

it('uses the independent Nestable editor instead of a second private implementation', function () {
    expect(is_subclass_of(MenuTree::class, 'Fayne\\FilamentNestable\\Filament\\Pages\\TreePage'))->toBeTrue();
});

it('switches straight to the only other language and hides the entry without translations', function () {
    config(['filament-cms.multilingual_enabled' => true, 'filament-cms.supported_locales' => ['en' => 'English', 'zh_CN' => '简体中文']]);
    $admin = Admin::factory()->create(['is_active' => true]);
    foreach (['viewAnyMenu', 'viewMenu', 'updateMenu'] as $permission) {
        test()->grantPermissions($admin, test()->permission($permission));
    }
    auth('admin')->login($admin);
    $en = Menu::create(['name' => 'English', 'slug' => 'main', 'locale' => 'en']);
    Livewire::test(MenuTree::class, ['record' => $en->id])->assertActionHidden('switchLanguage');
    $zh = Menu::create(['name' => '中文', 'slug' => 'main', 'locale' => 'zh_CN', 'translation_group_id' => $en->translation_group_id]);
    Livewire::test(MenuTree::class, ['record' => $en->id])->callAction('switchLanguage')
        ->assertRedirect(MenuResource::getUrl('tree', ['record' => $zh]));
});

it('offers only other languages in the tree picker', function () {
    config(['filament-cms.multilingual_enabled' => true, 'filament-cms.supported_locales' => ['en' => 'English', 'zh_CN' => '简体中文', 'fr' => 'French']]);
    $admin = Admin::factory()->create(['is_active' => true]);
    foreach (['viewAnyMenu', 'viewMenu', 'updateMenu'] as $permission) {
        test()->grantPermissions($admin, test()->permission($permission));
    }
    auth('admin')->login($admin);
    $en = Menu::create(['name' => 'English', 'slug' => 'main', 'locale' => 'en']);
    $zh = Menu::create(['name' => '中文', 'slug' => 'main', 'locale' => 'zh_CN', 'translation_group_id' => $en->translation_group_id]);
    Menu::create(['name' => 'French', 'slug' => 'main', 'locale' => 'fr', 'translation_group_id' => $en->translation_group_id]);
    $page = Livewire::test(MenuTree::class, ['record' => $en->id])->mountAction('switchLanguage');
    $schema = (new ReflectionMethod($page->instance(), 'getMountedActionSchema'))->invoke($page->instance());
    $fields = $schema->getFlatComponents();
    $select = collect($fields)->first(fn ($field) => $field instanceof Select);
    expect($select->getOptions())->toBe(['fr' => 'French', 'zh_CN' => '简体中文']);
    $page->setActionData(['locale' => 'zh_CN'])->callMountedAction()->assertRedirect(MenuResource::getUrl('tree', ['record' => $zh]));
});

it('creates root and child items and manages their state through tree actions', function () {
    $menu = Menu::create(['name' => 'Main', 'slug' => 'main', 'deep' => 2]);
    $admin = Admin::factory()->create(['is_active' => true]);
    foreach (['viewAnyMenu', 'viewMenu', 'updateMenu', 'createMenuItem', 'updateMenuItem', 'deleteMenuItem'] as $ability) {
        test()->grantPermissions($admin, test()->permission($ability));
    }
    auth('admin')->login($admin);
    $page = Livewire::test(MenuTree::class, ['record' => $menu->id])
        ->callAction('createNode', data: ['name' => 'Root', 'url' => '/', 'is_active' => false])->assertHasNoErrors();
    $root = $menu->menuItems()->where('name', 'Root')->firstOrFail();
    $page->callAction('createNode', data: ['name' => 'Child', 'url' => '/child', 'is_active' => true], arguments: ['parent' => $root->id])->assertHasNoErrors();
    $child = $menu->menuItems()->where('name', 'Child')->firstOrFail();
    expect($child->parent_id)->toBe($root->id)->and($child->level)->toBe(2)->and($child->locale)->toBe($menu->locale);
    $page->callAction('createNode', data: ['name' => 'Too deep'], arguments: ['parent' => $child->id])->assertHasErrors();
    $page->call('toggleNode', (string) $root->id)->assertHasNoErrors();
    expect($root->fresh()->is_active)->toBeTrue()->and($menu->fresh()->changed)->toBeTrue();
    $page->call('deleteNode', (string) $root->id)->assertHasErrors(['tree']);
    $page->call('deleteNode', (string) $child->id)->assertHasErrors(['tree']);
    expect($child->fresh())->not->toBeNull();
    $page->call('toggleNode', (string) $child->id);
    $page->call('deleteNode', (string) $child->id);
    expect($child->fresh())->toBeNull()->and($root->fresh())->not->toBeNull();
});

it('inserts siblings immediately after the selected root or child even with duplicate weights', function () {
    $menu = Menu::create(['name' => 'Main', 'slug' => 'main', 'deep' => 2]);
    $first = $menu->menuItems()->create(['name' => 'First', 'weight' => 0]);
    $last = $menu->menuItems()->create(['name' => 'Last', 'weight' => 0]);
    $child = $menu->menuItems()->create(['name' => 'Child', 'parent_id' => $first->id, 'level' => 2]);
    $admin = Admin::factory()->create(['is_active' => true]);
    foreach (['viewAnyMenu', 'viewMenu', 'updateMenu', 'createMenuItem'] as $ability) {
        test()->grantPermissions($admin, test()->permission($ability));
    }
    auth('admin')->login($admin);
    $page = Livewire::test(MenuTree::class, ['record' => $menu->id]);
    $page->callAction('createNode', data: ['name' => 'Middle'], arguments: ['after' => $first->id])->assertHasNoErrors();
    expect($menu->menuItems()->whereNull('parent_id')->orderBy('weight')->orderBy('id')->pluck('name')->all())->toBe(['First', 'Middle', 'Last']);
    $page->callAction('createNode', data: ['name' => 'Child sibling'], arguments: ['after' => $child->id])->assertHasNoErrors();
    $sibling = $menu->menuItems()->where('name', 'Child sibling')->firstOrFail();
    expect($sibling->parent_id)->toBe($first->id)->and($sibling->level)->toBe(2);
    $foreignMenu = Menu::create(['name' => 'Other', 'slug' => 'other']);
    $foreign = $foreignMenu->menuItems()->create(['name' => 'Foreign']);
    Livewire::test(MenuTree::class, ['record' => $menu->id])
        ->callAction('createNode', data: ['name' => 'Wrong scope'], arguments: ['after' => $foreign->id])->assertNotFound();
});

it('does not allow menu update permission alone to mutate menu items', function () {
    $menu = Menu::create(['name' => 'Main', 'slug' => 'main']);
    $item = $menu->menuItems()->create(['name' => 'Protected']);
    $admin = Admin::factory()->create(['is_active' => true]);
    foreach (['viewAnyMenu', 'viewMenu', 'updateMenu'] as $ability) {
        test()->grantPermissions($admin, test()->permission($ability));
    }
    auth('admin')->login($admin);
    Livewire::test(MenuTree::class, ['record' => $menu->id])->call('toggleNode', (string) $item->id)->assertForbidden();
    Livewire::test(MenuTree::class, ['record' => $menu->id])->call('deleteNode', (string) $item->id)->assertForbidden();
    expect($item->fresh())->not->toBeNull()->and($item->fresh()->is_active)->toBeFalse();
});
