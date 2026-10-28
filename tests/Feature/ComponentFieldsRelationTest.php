<?php

use Fayne\CMS\Models\Admin;
use Fayne\Menu\Filament\Resources\MenuComponentResource;
use Fayne\Menu\Filament\Resources\MenuComponentResource\Pages\EditMenuComponent;
use Fayne\Menu\Filament\Resources\MenuComponentResource\RelationManagers\FieldsRelationManager;
use Fayne\Menu\Models\MenuComponent;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

function menuComponentFieldEditor(array $permissions = []): Admin
{
    $admin = Admin::create(['name' => 'Schema editor', 'email' => 'fields@example.test', 'password' => 'password', 'is_active' => true]);
    foreach (['viewAnyMenuComponent', 'viewMenuComponent', 'updateMenuComponent', 'viewAnyMenuComponentField', ...$permissions] as $permission) {
        test()->grantPermissions($admin, test()->permission($permission));
    }
    auth('admin')->login($admin);

    return $admin;
}
function menuComponentFieldsManager(MenuComponent $component): mixed
{
    return Livewire::test(FieldsRelationManager::class, ['ownerRecord' => $component, 'pageClass' => EditMenuComponent::class]);
}
it('embeds the field manager on the component edit page and scopes its records', function () {
    menuComponentFieldEditor();
    $component = MenuComponent::create(['name' => 'Hero', 'slug' => 'hero']);
    $own = $component->fields()->create(['title' => 'Headline', 'slug' => 'headline', 'resolver' => 'text']);
    $other = MenuComponent::create(['name' => 'Other', 'slug' => 'other'])->fields()->create(['title' => 'Foreign', 'slug' => 'foreign', 'resolver' => 'text']);
    expect(MenuComponentResource::getRelations())->toContain(FieldsRelationManager::class);
    Livewire::test(EditMenuComponent::class, ['record' => $component->id])->assertSeeLivewire(FieldsRelationManager::class);
    menuComponentFieldsManager($component)->assertCanSeeTableRecords([$own])->assertCanNotSeeTableRecords([$other]);
});
it('creates edits and deletes a field without permitting a different component association', function () {
    menuComponentFieldEditor(['createMenuComponentField', 'viewMenuComponentField', 'updateMenuComponentField', 'deleteMenuComponentField']);
    $component = MenuComponent::create(['name' => 'Hero', 'slug' => 'hero']);
    $other = MenuComponent::create(['name' => 'Other', 'slug' => 'other']);
    menuComponentFieldsManager($component)->callAction(TestAction::make('create')->table(), data: ['title' => 'Body', 'slug' => 'body', 'resolver' => 'rich_text', 'weight' => 10, 'options' => '{"toolbar":["bold","attachFiles"]}', 'rules' => '{"rules":["required"]}', 'nova_menu_component_id' => $other->id])->assertHasNoFormErrors();
    $field = $component->fields()->sole();
    expect($field->nova_menu_component_id)->toBe($component->id)->and($field->options)->toBe(['toolbar' => ['bold', 'attachFiles']]);
    menuComponentFieldsManager($component)->callAction(TestAction::make('edit')->table($field), data: ['title' => 'Article', 'slug' => 'body', 'resolver' => 'rich_text', 'weight' => 20, 'options' => '{}', 'rules' => '{}', 'nova_menu_component_id' => $other->id])->assertHasNoFormErrors();
    expect($field->fresh()->title)->toBe('Article')->and($field->fresh()->nova_menu_component_id)->toBe($component->id);
    menuComponentFieldsManager($component)->callAction(TestAction::make('delete')->table($field))->assertHasNoErrors();
    expect($field->fresh())->toBeNull();
});
it('reorders only the complete current component field set', function () {
    menuComponentFieldEditor(['updateMenuComponentField']);
    $component = MenuComponent::create(['name' => 'Hero', 'slug' => 'hero']);
    $first = $component->fields()->create(['title' => 'First', 'slug' => 'first', 'resolver' => 'text', 'weight' => 10]);
    $second = $component->fields()->create(['title' => 'Second', 'slug' => 'second', 'resolver' => 'text', 'weight' => 20]);
    $foreign = MenuComponent::create(['name' => 'Other', 'slug' => 'other'])->fields()->create(['title' => 'Foreign', 'resolver' => 'text', 'weight' => 50]);
    menuComponentFieldsManager($component)->call('reorderTable', [$second->id, $first->id])->assertHasNoErrors();
    expect($component->fields()->pluck('id')->all())->toBe([$second->id, $first->id]);
    menuComponentFieldsManager($component)->call('reorderTable', [$first->id, $foreign->id])->assertHasErrors();
    expect($component->fields()->pluck('id')->all())->toBe([$second->id, $first->id])->and($foreign->fresh()->weight)->toBe(50);
});
it('does not grant field mutations through component edit permission alone', function () {
    menuComponentFieldEditor();
    $component = MenuComponent::create(['name' => 'Hero', 'slug' => 'hero']);
    $field = $component->fields()->create(['title' => 'Title', 'resolver' => 'text']);
    menuComponentFieldsManager($component)->assertActionHidden(TestAction::make('create')->table())->assertActionHidden(TestAction::make('edit')->table($field))->assertActionHidden(TestAction::make('delete')->table($field));
    menuComponentFieldsManager($component)->call('reorderTable', [$field->id])->assertForbidden();
});

it('reorders components with more than nine fields', function () {
    menuComponentFieldEditor(['updateMenuComponentField']);
    $component = MenuComponent::create(['name' => 'Large', 'slug' => 'large']);
    foreach (range(1, 12) as $index) {
        $component->fields()->create(['title' => 'Field '.$index, 'slug' => 'field_'.$index, 'resolver' => 'text', 'weight' => $index]);
    }
    $order = $component->fields()->pluck('id')->reverse()->values()->all();
    menuComponentFieldsManager($component)->call('reorderTable', $order)->assertHasNoErrors();
    expect($component->fields()->pluck('id')->all())->toBe($order);
});

it('cannot edit a field belonging to a different component by supplying its record key', function () {
    menuComponentFieldEditor(['updateMenuComponentField']);
    $component = MenuComponent::create(['name' => 'Hero', 'slug' => 'hero']);
    $foreign = MenuComponent::create(['name' => 'Other', 'slug' => 'other'])->fields()->create(['title' => 'Foreign', 'resolver' => 'text']);
    menuComponentFieldsManager($component)->mountAction(TestAction::make('edit')->table($foreign))->assertActionNotMounted();
    expect($foreign->fresh()->title)->toBe('Foreign');
});

it('requires permission to update the parent even when field creation is allowed', function () {
    $admin = menuComponentFieldEditor(['createMenuComponentField']);
    $component = MenuComponent::create(['name' => 'Hero', 'slug' => 'hero']);
    $admin->revokePermissionTo('tenant.updateMenuComponent');
    menuComponentFieldsManager($component)->assertActionHidden(TestAction::make('create')->table());
    expect($component->fields()->count())->toBe(0);
});

it('rejects direct relation manager access without permission to view the parent', function () {
    $admin = menuComponentFieldEditor();
    $component = MenuComponent::create(['name' => 'Hero', 'slug' => 'hero']);
    $admin->revokePermissionTo('tenant.viewMenuComponent');
    menuComponentFieldsManager($component)->assertForbidden();
});
