<?php

use Fayne\CMS\Models\Admin;
use Fayne\Menu\Filament\Resources\MenuItemResource\Pages\EditMenuItem;
use Fayne\Menu\Filament\Resources\MenuResource;
use Fayne\Menu\Filament\Resources\MenuResource\Pages\EditMenu;
use Fayne\Menu\Models\Menu;
use Fayne\Menu\Models\MenuComponent;
use Fayne\Menu\Models\MenuItem;
use Fayne\Menu\Services\MenuTranslationService;
use Filament\Forms\Components\Select;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    config(['filament-cms.multilingual_enabled' => true, 'filament-cms.supported_locales' => ['en' => 'English', 'zh_CN' => '中文'], 'filament-cms.default_locale' => 'zh_CN']);
});

it('clones a menu as an independent inactive tree with remapped parents', function () {
    $admin = Admin::create(['name' => 'Translator', 'email' => 'translator@example.test', 'password' => 'password', 'is_active' => true]);
    auth('admin')->login($admin);
    Gate::before(fn () => true);
    $source = Menu::create(['name' => 'Main', 'slug' => 'main', 'is_active' => true]);
    $root = $source->menuItems()->create(['name' => 'Root', 'locale' => 'zh_CN', 'weight' => 2, 'is_active' => true]);
    $source->menuItems()->create(['name' => 'Child', 'parent_id' => $root->id, 'locale' => 'zh_CN', 'level' => 2, 'weight' => 3]);
    $target = app(MenuTranslationService::class)->create($source, 'EN', 'main-en');
    expect($source->locale)->toBe('zh_CN')->and($target->locale)->toBe('en')->and($target->is_active)->toBeFalse()->and($target->translation_group_id)->toBe($source->translation_group_id);
    $items = $target->menuItems()->get()->keyBy('name');
    expect($items['Child']->parent_id)->toBe($items['Root']->id)->and($items['Child']->locale)->toBe('en')->and($items['Child']->weight)->toBe(3);
    expect(fn () => app(MenuTranslationService::class)->create($source, 'en', 'another'))->toThrow(ValidationException::class);
    expect(Menu::count())->toBe(2);
});

it('rolls back translations when target update authorization fails', function () {
    $admin = Admin::create(['name' => 'Translator', 'email' => 'denied@example.test', 'password' => 'password', 'is_active' => true]);
    auth('admin')->login($admin);
    Gate::before(fn ($user, $ability) => $ability !== 'update');
    $source = Menu::create(['name' => 'Main', 'slug' => 'main']);
    expect(fn () => app(MenuTranslationService::class)->create($source, 'en', 'main-en'))->toThrow(AuthorizationException::class);
    expect(Menu::count())->toBe(1);
});

it('uses strict content locale rules in the versioned public endpoint', function () {
    $menu = Menu::create(['name' => '中文', 'slug' => 'main', 'is_active' => true]);
    $menu->menuItems()->create(['name' => '首页', 'locale' => 'zh_CN', 'is_active' => true]);
    app()->setLocale('en');
    $this->getJson('/modules/menus/v2/frontend/main')->assertOk()->assertJsonPath('0.name', '首页');
    $this->getJson('/modules/menus/v2/frontend/main?locale=zh')->assertUnprocessable();
    $this->getJson('/modules/menus/v2/frontend/main?locale=en')->assertNotFound();
    config(['filament-cms.multilingual_enabled' => false]);
    $this->getJson('/modules/menus/v2/frontend/main?locale=en')->assertUnprocessable();
});

it('creates and switches translations through authorized editor actions', function () {
    $admin = Admin::create(['name' => 'Translator', 'email' => 'livewire@example.test', 'password' => 'password', 'is_active' => true]);
    auth('admin')->login($admin);
    Gate::before(fn () => true);
    $source = Menu::create(['name' => 'Main', 'slug' => 'main']);
    Livewire::test(EditMenu::class, ['record' => $source->id])
        ->assertActionVisible('createTranslation')->assertActionHidden('switchLanguage')
        ->callAction('createTranslation', ['locale' => 'en', 'slug' => 'english'])->assertHasNoActionErrors()
        ->assertRedirect(MenuResource::getUrl('edit', ['record' => Menu::where('locale', 'en')->first()]));
    $target = Menu::where('locale', 'en')->firstOrFail();
    Livewire::test(EditMenu::class, ['record' => $source->id])
        ->assertActionHidden('createTranslation')
        ->assertActionExists('switchLanguage', fn ($action): bool => ! $action->shouldOpenModal(fn () => false))
        ->callAction('switchLanguage')->assertHasNoActionErrors()
        ->assertRedirect(MenuResource::getUrl('edit', ['record' => $target]));
    expect($source->fresh()->locale)->toBe('zh_CN');
});

it('keeps the language picker only for multiple other versions and excludes the current language', function () {
    config(['filament-cms.supported_locales' => ['en' => 'English', 'zh_CN' => '中文', 'fr' => 'French']]);
    $admin = Admin::create(['name' => 'Translator', 'email' => 'multiple-languages@example.test', 'password' => 'password', 'is_active' => true]);
    auth('admin')->login($admin);
    Gate::before(fn () => true);
    $source = Menu::create(['name' => 'Main', 'slug' => 'main']);
    $en = app(MenuTranslationService::class)->create($source, 'en', 'english');
    app(MenuTranslationService::class)->create($source, 'fr', 'french');
    $page = Livewire::test(EditMenu::class, ['record' => $source->id])
        ->assertActionHidden('createTranslation')->mountAction('switchLanguage');
    $schema = (new ReflectionMethod($page->instance(), 'getMountedActionSchema'))->invoke($page->instance());
    $select = collect($schema->getFlatComponents())->first(fn ($field) => $field instanceof Select);
    expect(array_keys($select->getOptions()))->toBe(['en', 'fr']);
    $page->setActionData(['locale' => 'en'])->callMountedAction()
        ->assertHasNoActionErrors()->assertRedirect(MenuResource::getUrl('edit', ['record' => $en]));
});

it('rejects conflicting slugs and protects nested sensitive content without leaving partial trees', function () {
    $admin = Admin::create(['name' => 'Translator', 'email' => 'sensitive@example.test', 'password' => 'password', 'is_active' => true]);
    auth('admin')->login($admin);
    Gate::before(fn ($user, $ability) => $ability !== 'viewSensitiveContent');
    $source = Menu::create(['name' => 'Main', 'slug' => 'main']);
    Menu::create(['name' => 'Taken', 'slug' => 'taken', 'locale' => 'en']);
    expect(fn () => app(MenuTranslationService::class)->create($source, 'en', 'taken'))->toThrow(ValidationException::class);
    $component = MenuComponent::create(['name' => 'Panel', 'slug' => 'panel']);
    $component->fields()->create(['title' => 'Secret', 'slug' => 'secret', 'resolver' => 'sensitive']);
    $source->menuItems()->create(['name' => 'Private', 'content' => [['layout' => 'panel', 'key' => 'source-key', 'attributes' => ['secret' => 'private-value']]]]);
    expect(fn () => app(MenuTranslationService::class)->create($source, 'en', 'english'))->toThrow(AuthorizationException::class);
    expect(Menu::count())->toBe(2)->and(MenuItem::count())->toBe(1);
});

it('regenerates copied block keys while preserving entity ids and source content', function () {
    $admin = Admin::create(['name' => 'Translator', 'email' => 'keys@example.test', 'password' => 'password', 'is_active' => true]);
    auth('admin')->login($admin);
    Gate::before(fn () => true);
    $source = Menu::create(['name' => 'Main', 'slug' => 'main']);
    $component = MenuComponent::create(['name' => 'Panel', 'slug' => 'panel']);
    $component->fields()->create(['title' => 'Nested', 'slug' => 'nested', 'resolver' => 'widget']);
    $content = [['layout' => 'panel', 'key' => 'outer', 'attributes' => ['reference' => ['model' => 'Page', 'id' => 45], 'nested' => [['layout' => 'widget', 'key' => 'inner', 'attributes' => ['text' => 'Original']]]]]];
    $item = $source->menuItems()->create(['name' => 'Link', 'content' => $content]);
    $target = app(MenuTranslationService::class)->create($source, 'en', 'english');
    $copied = $target->menuItems()->first()->content;
    expect($copied[0]['key'])->not->toBe('outer')->and($copied[0]['attributes']['nested'][0]['key'])->not->toBe('inner')->and($copied[0]['attributes']['reference']['id'])->toBe(45)->and($item->fresh()->content)->toBe($content);
});

it('blocks translation navigation and cloning while editor changes are unsaved', function () {
    $admin = Admin::create(['name' => 'Translator', 'email' => 'dirty@example.test', 'password' => 'password', 'is_active' => true]);
    auth('admin')->login($admin);
    Gate::before(fn () => true);
    $source = Menu::create(['name' => 'Main', 'slug' => 'main']);
    app(MenuTranslationService::class)->create($source, 'en', 'english');
    config(['filament-cms.supported_locales' => ['en' => 'English', 'zh_CN' => '中文', 'fr' => 'French']]);
    foreach (['createTranslation', 'switchLanguage'] as $action) {
        Livewire::test(EditMenu::class, ['record' => $source->id])
            ->fillForm(['name' => 'Unsaved title'])
            ->callAction($action, ['locale' => $action === 'createTranslation' ? 'fr' : 'en', 'slug' => 'another'])
            ->assertHasActionErrors(['locale'])->assertNoRedirect();
    }
    expect(Menu::count())->toBe(2)->and($source->fresh()->name)->toBe('Main');
});

it('hides other content languages from normal menu editing when disabled', function () {
    $admin = Admin::create(['name' => 'Editor', 'email' => 'disabled-editor@example.test', 'password' => 'password', 'is_active' => true]);
    auth('admin')->login($admin);
    Gate::before(fn () => true);
    $other = Menu::create(['name' => 'English', 'slug' => 'english', 'locale' => 'en']);
    $item = $other->menuItems()->create(['name' => 'English item']);
    config(['filament-cms.multilingual_enabled' => false]);
    expect(MenuResource::getEloquentQuery()->find($other->id))->toBeNull();
    Livewire::test(EditMenu::class, ['record' => $other->id])->assertNotFound();
    Livewire::test(EditMenuItem::class, ['record' => $item->id])->assertNotFound();
});
