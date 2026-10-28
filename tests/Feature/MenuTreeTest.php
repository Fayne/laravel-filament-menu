<?php

use Fayne\Menu\Models\Menu;
use Fayne\Menu\Services\MenuTreeService;
use Illuminate\Validation\ValidationException;

it('moves a subtree and updates parent depth and sibling order atomically', function () {
    $menu = Menu::create(['name' => 'Main', 'slug' => 'main']);
    $first = $menu->menuItems()->create(['name' => 'First']);
    $second = $menu->menuItems()->create(['name' => 'Second']);
    $child = $menu->menuItems()->create(['name' => 'Child', 'parent_id' => $first->id, 'level' => 2]);
    (new MenuTreeService)->reorder($menu, [
        ['id' => $second->id, 'children' => [['id' => $first->id, 'children' => [['id' => $child->id, 'children' => []]]]]],
    ]);
    expect($first->fresh()->parent_id)->toBe($second->id)->and($first->fresh()->level)->toBe(2)
        ->and($child->fresh()->level)->toBe(3)->and($second->fresh()->parent_id)->toBeNull();
});

it('rejects a malformed or stale tree without changing any items', function (string $case) {
    $menu = Menu::create(['name' => 'Main', 'slug' => 'main']);
    $first = $menu->menuItems()->create(['name' => 'First']);
    $second = $menu->menuItems()->create(['name' => 'Second']);
    $other = Menu::create(['name' => 'Other', 'slug' => 'other'])->menuItems()->create(['name' => 'Foreign']);
    $tree = match ($case) {
        'duplicate' => [['id' => $first->id], ['id' => $first->id]],
        'missing' => [['id' => $first->id]],
        'foreign' => [['id' => $first->id], ['id' => $other->id]],
        'self' => [['id' => $first->id, 'children' => [['id' => $first->id]]], ['id' => $second->id]],
        'deep' => [['id' => $first->id, 'children' => [['id' => $second->id]]]],
        'locale' => [['id' => $first->id], ['id' => $second->id]],
    };
    if ($case === 'deep') {
        config(['filament-menu.menu_builder.max_depth' => 1]);
    }
    if ($case === 'locale') {
        $second->update(['locale' => 'zh-CN']);
    }
    $before = $menu->menuItems()->get()->toArray();
    try {
        (new MenuTreeService)->reorder($menu, $tree);
        $this->fail('Invalid tree accepted.');
    } catch (ValidationException) {
        expect($menu->menuItems()->get()->toArray())->toBe($before);
    }
})->with(['duplicate', 'missing', 'foreign', 'self', 'deep', 'locale']);

it('builds a public tree without exposing inactive parents or their descendants', function () {
    $menu = Menu::create(['name' => 'Main', 'slug' => 'main', 'is_active' => true]);
    $hidden = $menu->menuItems()->create(['name' => 'Hidden', 'is_active' => false]);
    $menu->menuItems()->create(['name' => 'Hidden child', 'parent_id' => $hidden->id, 'is_active' => true]);
    $visible = $menu->menuItems()->create(['name' => 'Visible', 'is_active' => true]);
    expect($menu->publicTree()->pluck('name')->all())->toBe(['Visible']);
});

it('enforces menu-specific depth and per-item allowed levels', function () {
    $menu = Menu::create(['name' => 'Main', 'slug' => 'main', 'deep' => 1, 'settings' => ['keep' => true], 'changed' => false]);
    $one = $menu->menuItems()->create(['name' => 'One']);
    $two = $menu->menuItems()->create(['name' => 'Two', 'available_levels' => [1]]);
    expect($menu->fresh()->settings)->toBe(['keep' => true]);
    foreach ([1, 5] as $depth) {
        $menu->update(['deep' => $depth]);
        try {
            (new MenuTreeService)->reorder($menu, [['id' => $one->id, 'children' => [['id' => $two->id]]]]);
            $this->fail('Invalid level accepted.');
        } catch (ValidationException) {
            expect($two->fresh()->parent_id)->toBeNull();
        }
    }
});
