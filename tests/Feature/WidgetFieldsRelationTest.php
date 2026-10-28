<?php

use Fayne\CMS\Models\Admin;
use Fayne\Menu\Filament\Resources\MenuWidgetResource;
use Fayne\Menu\Filament\Resources\MenuWidgetResource\Pages\EditMenuWidget;
use Fayne\Menu\Filament\Resources\MenuWidgetResource\RelationManagers\FieldsRelationManager;
use Fayne\Menu\Models\MenuWidget;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

function menuWidgetFieldEditor(array $permissions = []): Admin
{
    $admin = Admin::create(['name' => 'Schema editor', 'email' => 'fields@example.test', 'password' => 'password', 'is_active' => true]);
    foreach (['viewAnyMenuWidget', 'viewMenuWidget', 'updateMenuWidget', 'viewAnyMenuWidgetField', ...$permissions] as $permission) {
        test()->grantPermissions($admin, test()->permission($permission));
    }
    auth('admin')->login($admin);

    return $admin;
}
function menuWidgetFieldsManager(MenuWidget $widget): mixed
{
    return Livewire::test(FieldsRelationManager::class, ['ownerRecord' => $widget, 'pageClass' => EditMenuWidget::class]);
}
it('embeds the field manager on the widget edit page and scopes its records', function () {
    menuWidgetFieldEditor();
    $widget = MenuWidget::create(['name' => 'Hero', 'slug' => 'hero']);
    $own = $widget->fields()->create(['title' => 'Headline', 'slug' => 'headline', 'resolver' => 'text']);
    $other = MenuWidget::create(['name' => 'Other', 'slug' => 'other'])->fields()->create(['title' => 'Foreign', 'slug' => 'foreign', 'resolver' => 'text']);
    expect(MenuWidgetResource::getRelations())->toContain(FieldsRelationManager::class);
    Livewire::test(EditMenuWidget::class, ['record' => $widget->id])->assertSeeLivewire(FieldsRelationManager::class);
    menuWidgetFieldsManager($widget)->assertCanSeeTableRecords([$own])->assertCanNotSeeTableRecords([$other]);
});
it('creates edits and deletes a field without permitting a different widget association', function () {
    menuWidgetFieldEditor(['createMenuWidgetField', 'viewMenuWidgetField', 'updateMenuWidgetField', 'deleteMenuWidgetField']);
    $widget = MenuWidget::create(['name' => 'Hero', 'slug' => 'hero']);
    $other = MenuWidget::create(['name' => 'Other', 'slug' => 'other']);
    menuWidgetFieldsManager($widget)->callAction(TestAction::make('create')->table(), data: ['title' => 'Body', 'slug' => 'body', 'resolver' => 'rich_text', 'weight' => 10, 'options' => '{"toolbar":["bold","attachFiles"]}', 'rules' => '{"rules":["required"]}', 'menu_widget_id' => $other->id])->assertHasNoFormErrors();
    $field = $widget->fields()->sole();
    expect($field->menu_widget_id)->toBe($widget->id)->and($field->options)->toBe(['toolbar' => ['bold', 'attachFiles']]);
    menuWidgetFieldsManager($widget)->callAction(TestAction::make('edit')->table($field), data: ['title' => 'Article', 'slug' => 'body', 'resolver' => 'rich_text', 'weight' => 20, 'options' => '{}', 'rules' => '{}', 'menu_widget_id' => $other->id])->assertHasNoFormErrors();
    expect($field->fresh()->title)->toBe('Article')->and($field->fresh()->menu_widget_id)->toBe($widget->id);
    menuWidgetFieldsManager($widget)->callAction(TestAction::make('delete')->table($field))->assertHasNoErrors();
    expect($field->fresh())->toBeNull();
});
it('reorders only the complete current widget field set', function () {
    menuWidgetFieldEditor(['updateMenuWidgetField']);
    $widget = MenuWidget::create(['name' => 'Hero', 'slug' => 'hero']);
    $first = $widget->fields()->create(['title' => 'First', 'slug' => 'first', 'resolver' => 'text', 'weight' => 10]);
    $second = $widget->fields()->create(['title' => 'Second', 'slug' => 'second', 'resolver' => 'text', 'weight' => 20]);
    $foreign = MenuWidget::create(['name' => 'Other', 'slug' => 'other'])->fields()->create(['title' => 'Foreign', 'resolver' => 'text', 'weight' => 50]);
    menuWidgetFieldsManager($widget)->call('reorderTable', [$second->id, $first->id])->assertHasNoErrors();
    expect($widget->fields()->pluck('id')->all())->toBe([$second->id, $first->id]);
    menuWidgetFieldsManager($widget)->call('reorderTable', [$first->id, $foreign->id])->assertHasErrors();
    expect($widget->fields()->pluck('id')->all())->toBe([$second->id, $first->id])->and($foreign->fresh()->weight)->toBe(50);
});
it('does not grant field mutations through widget edit permission alone', function () {
    menuWidgetFieldEditor();
    $widget = MenuWidget::create(['name' => 'Hero', 'slug' => 'hero']);
    $field = $widget->fields()->create(['title' => 'Title', 'resolver' => 'text']);
    menuWidgetFieldsManager($widget)->assertActionHidden(TestAction::make('create')->table())->assertActionHidden(TestAction::make('edit')->table($field))->assertActionHidden(TestAction::make('delete')->table($field));
    menuWidgetFieldsManager($widget)->call('reorderTable', [$field->id])->assertForbidden();
});

it('reorders widgets with more than nine fields', function () {
    menuWidgetFieldEditor(['updateMenuWidgetField']);
    $widget = MenuWidget::create(['name' => 'Large', 'slug' => 'large']);
    foreach (range(1, 12) as $index) {
        $widget->fields()->create(['title' => 'Field '.$index, 'slug' => 'field_'.$index, 'resolver' => 'text', 'weight' => $index]);
    }
    $order = $widget->fields()->pluck('id')->reverse()->values()->all();
    menuWidgetFieldsManager($widget)->call('reorderTable', $order)->assertHasNoErrors();
    expect($widget->fields()->pluck('id')->all())->toBe($order);
});

it('cannot edit a field belonging to a different widget by supplying its record key', function () {
    menuWidgetFieldEditor(['updateMenuWidgetField']);
    $widget = MenuWidget::create(['name' => 'Hero', 'slug' => 'hero']);
    $foreign = MenuWidget::create(['name' => 'Other', 'slug' => 'other'])->fields()->create(['title' => 'Foreign', 'resolver' => 'text']);
    menuWidgetFieldsManager($widget)->mountAction(TestAction::make('edit')->table($foreign))->assertActionNotMounted();
    expect($foreign->fresh()->title)->toBe('Foreign');
});

it('requires permission to update the parent even when field creation is allowed', function () {
    $admin = menuWidgetFieldEditor(['createMenuWidgetField']);
    $widget = MenuWidget::create(['name' => 'Hero', 'slug' => 'hero']);
    $admin->revokePermissionTo('tenant.updateMenuWidget');
    menuWidgetFieldsManager($widget)->assertActionHidden(TestAction::make('create')->table());
    expect($widget->fields()->count())->toBe(0);
});

it('rejects direct relation manager access without permission to view the parent', function () {
    $admin = menuWidgetFieldEditor();
    $widget = MenuWidget::create(['name' => 'Hero', 'slug' => 'hero']);
    $admin->revokePermissionTo('tenant.viewMenuWidget');
    menuWidgetFieldsManager($widget)->assertForbidden();
});
