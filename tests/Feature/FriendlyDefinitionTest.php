<?php

use Fayne\CMS\Models\Admin;
use Fayne\PageBuilder\Filament\Forms\Components\FieldOptionsInput;
use Filament\Forms\Components\Select;
use Livewire\Livewire;

it('uses friendly types and structured rules in both menu field resources', function (string $kind, string $foreignKey) {
    $model = 'Fayne\\Menu\\Models\\Menu'.$kind;
    $resource = 'Fayne\\Menu\\Filament\\Resources\\Menu'.$kind.'FieldResource\\Pages\\CreateMenu'.$kind.'Field';
    $owner = $model::create(['name' => 'Friendly', 'slug' => 'friendly']);
    $admin = Admin::create(['name' => 'Field editor', 'email' => 'friendly@example.test', 'password' => 'password', 'is_active' => true]);
    foreach (['viewAnyMenuWidget', 'viewAnyMenu'.$kind.'Field', 'createMenu'.$kind.'Field'] as $ability) {
        test()->grantPermissions($admin, test()->permission($ability));
    }
    auth('admin')->login($admin);
    $widgetModel = 'Fayne\\Menu\\Models\\MenuWidget';
    $widget = $widgetModel::firstOrCreate(['slug' => 'allowed'], ['name' => 'Allowed widget']);
    Livewire::test($resource)
        ->assertFormFieldExists('options', fn ($field) => ($field->getOptionDefinitions()['widget']['widgets'][2][$widget->id] ?? null) === 'Allowed widget')
        ->assertSee('基本设置')->assertSee('字段选项')->assertSee('验证规则')
        ->assertFormFieldExists('options', fn ($field) => $field instanceof FieldOptionsInput)
        ->assertFormFieldExists('resolver', fn ($field) => $field instanceof Select)
        ->fillForm([$foreignKey => $owner->id, 'title' => 'Photo', 'slug' => 'photo', 'resolver' => 'image', 'options' => '{}', 'rules' => ['rules' => ['required', 'max:2048']]])->call('create')->assertHasNoFormErrors();
    expect($owner->fields()->sole()->resolver)->toBe('file')->and($owner->fields()->sole()->options)->toBe(['is_image' => true])->and($owner->fields()->sole()->rules)->toBe(['rules' => ['required', 'max:2048']]);
})->with([['Component', 'nova_menu_component_id'], ['Widget', 'menu_widget_id']]);
