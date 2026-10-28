<?php

use Fayne\CMS\Models\Admin;
use Livewire\Livewire;

it('preserves the owner when navigating from a definition into standalone field creation', function (string $kind, string $foreignKey) {
    $parent = 'Fayne\\Menu\\Models\\Menu'.$kind;
    $parentResource = 'Fayne\\Menu\\Filament\\Resources\\Menu'.$kind.'Resource';
    $fieldResource = 'Fayne\\Menu\\Filament\\Resources\\Menu'.$kind.'FieldResource';
    $owner = $parent::create(['name' => 'Owner', 'slug' => 'owner']);
    $admin = Admin::create(['name' => 'Editor', 'email' => 'owner-context@example.test', 'password' => 'password', 'is_active' => true]);
    foreach (['viewMenu'.$kind, 'updateMenu'.$kind, 'viewAnyMenu'.$kind.'Field', 'createMenu'.$kind.'Field'] as $ability) {
        test()->grantPermissions($admin, test()->permission($ability));
    }
    auth('admin')->login($admin);
    $manager = Livewire::test($parentResource.'\\RelationManagers\\FieldsRelationManager', ['ownerRecord' => $owner, 'pageClass' => $parentResource.'\\Pages\\EditMenu'.$kind]);
    expect($manager->instance()->getTable()->getHeaderActions()[0]->getUrl())->toBe($fieldResource::getUrl('create', [$foreignKey => $owner->id]));
    Livewire::withQueryParams([$foreignKey => $owner->id])->test($fieldResource.'\\Pages\\CreateMenu'.$kind.'Field')
        ->assertSet('data.'.$foreignKey, $owner->id)
        ->fillForm(['title' => 'Headline', 'slug' => 'headline', 'resolver' => 'text', 'options' => '{}', 'rules' => '[]'])
        ->call('create')->assertHasNoFormErrors();
    expect($owner->fields()->sole()->getAttribute($foreignKey))->toBe($owner->id);
})->with([['Component', 'nova_menu_component_id'], ['Widget', 'menu_widget_id']]);
