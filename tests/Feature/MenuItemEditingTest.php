<?php

use Fayne\Menu\Models\Menu;
use Fayne\Menu\Models\MenuComponent;
use Fayne\Menu\Services\MenuItemService;
use Fayne\Menu\Services\MenuTreeService;
use Illuminate\Validation\ValidationException;

it('rechecks persisted activation before deleting and permits deletion only after disabling', function () {
    $menu = Menu::create(['name' => 'Main', 'slug' => 'main']);
    $item = $menu->menuItems()->create(['name' => 'Protected', 'is_active' => false]);
    $menu->menuItems()->whereKey($item->id)->update(['is_active' => true]);
    expect(fn () => app(MenuItemService::class)->delete($item))->toThrow(ValidationException::class);
    expect($item->fresh())->not->toBeNull();
    app(MenuItemService::class)->update($item, ['is_active' => false]);
    expect(app(MenuItemService::class)->delete($item))->toBeTrue()->and($item->fresh())->toBeNull();
});

it('creates items inside the menu locale and rejects a stale complete tree', function () {
    $menu = Menu::create(['name' => 'Main', 'slug' => 'main', 'locale' => 'en']);
    $service = new MenuItemService;
    $one = $service->create(['menu_id' => $menu->id, 'name' => 'One', 'locale' => 'en']);
    $two = $service->create(['menu_id' => $menu->id, 'name' => 'Two', 'locale' => 'en']);
    expect($two->weight)->toBe(1);
    try {
        (new MenuTreeService)->reorder($menu, [['id' => $one->id, 'children' => []]]);
        $this->fail('Stale tree accepted.');
    } catch (ValidationException) {
        expect($menu->menuItems()->count())->toBe(2);
    }
});

it('rejects moving an edited item to another menu or locale', function () {
    $menu = Menu::create(['name' => 'Main', 'slug' => 'main', 'locale' => 'en']);
    $other = Menu::create(['name' => 'Other', 'slug' => 'other', 'locale' => 'en']);
    $item = $menu->menuItems()->create(['name' => 'One', 'locale' => 'en']);
    foreach ([['menu_id' => $other->id], ['locale' => 'zh-CN']] as $data) {
        try {
            (new MenuItemService)->update($item, $data);
            $this->fail('Context changed.');
        } catch (ValidationException) {
            expect($item->fresh()->menu_id)->toBe($menu->id)->and($item->fresh()->locale)->toBe('en');
        }
    }
});

it('rejects foreign locales and deleting a parent with children', function () {
    $menu = Menu::create(['name' => 'Main', 'slug' => 'main', 'locale' => 'en']);
    try {
        (new MenuItemService)->create(['menu_id' => $menu->id, 'name' => 'Wrong', 'locale' => 'zh-CN']);
        $this->fail('Wrong locale accepted.');
    } catch (ValidationException) {
        expect($menu->menuItems()->count())->toBe(0);
    }
    $parent = $menu->menuItems()->create(['name' => 'Parent']);
    $menu->menuItems()->create(['name' => 'Child', 'parent_id' => $parent->id, 'level' => 2]);
    try {
        (new MenuItemService)->delete($parent);
        $this->fail('Parent deleted.');
    } catch (ValidationException) {
        expect($menu->menuItems()->count())->toBe(2);
    }
});

it('rejects components outside their menu and level specifications', function () {
    $menu = Menu::create(['name' => 'Main', 'slug' => 'main', 'locale' => 'en']);
    MenuComponent::create(['name' => 'Root only', 'slug' => 'hero', 'specifications' => ['main' => [1]]]);
    $one = (new MenuItemService)->create(['menu_id' => $menu->id, 'name' => 'One', 'content' => [['layout' => 'hero', 'key' => 'key', 'attributes' => []]]]);
    $parent = $menu->menuItems()->create(['name' => 'Parent']);
    try {
        (new MenuTreeService)->reorder($menu, [['id' => $parent->id, 'children' => [['id' => $one->id, 'children' => []]]]]);
        $this->fail('Invalid component level accepted.');
    } catch (ValidationException) {
        expect($one->fresh()->parent_id)->toBeNull();
    }
});
