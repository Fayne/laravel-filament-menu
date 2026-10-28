<p align="center"><img src="https://res.cloudinary.com/fayne/image/upload/v1787643269/fayne_logo.png" width="300"></p>

# Laravel Filament Menu

[简体中文](../README.md) | English

Menu management for **PHP 8.4+, Laravel 13, and Filament 5**. Provides multilingual menus, tree editing, component content, and frontend APIs on top of CMS Core authentication, authorization, and optional multi-tenancy.

## Features and scope

| Capability | Description |
| --- | --- |
| Menus and items | Names, slugs, links, active states, and a separate tree per menu |
| Tree editing | Drag ordering and nesting, one drop indicator, expand/collapse, root/sibling/child creation, editing, and status actions |
| Deletion rules | Active nodes cannot be deleted; children must be handled before deleting their parent |
| Components and widgets | Reusable fields and nested content; content configuration is hidden when no component definitions exist |
| Chinese, English, and translations | Simplified Chinese/English UI and independent language records linked by translation groups |
| Frontend and releases | Live trees, administrator previews, and frozen release snapshots |

## 1. Install in a new project

Configure host authentication, panels, and permissions using [CMS Core](../../laravel-filament-cms-core/docs/README.en.md), then register Composer repositories for this private package and all related `fayne/*` dependencies. Dependencies include CMS Core, Nestable, and Page Builder.

```bash
composer require fayne/laravel-filament-menu
php artisan vendor:publish --tag=filament-menu-config
php artisan migrate
php artisan filament:assets
php artisan cms:menu:init
```

`cms:menu:init` initializes the permission catalog and asks whether to create demo data. It does not create administrators or automatically grant super-admin access. This repository's `app:init` is a host command, not part of this package.

Migrations target new databases and use `filament_*` table names by default; table names and relationship keys are configurable. Assess schema and data migration separately for an existing Nova database. Do not run `migrate:fresh` on business data.

## 2. Register the panel and permissions

Register the plugin on the site panel, or on the tenant workspace when multi-tenancy is enabled, rather than the platform administration panel. Keep the host session, CSRF, authentication middleware, and existing CMS plugin.

```php
use Fayne\Menu\Filament\MenuPlugin;

return $panel->plugins([
    // Keep the host's existing plugins.
    MenuPlugin::make(),
]);
```

Assign menu, item, and definition-management permissions through CMS Core roles and permissions. Platform super-admin access does not bypass tenant membership or content ownership. Initializing permissions does not grant every administrator access.

## 3. Extend models, resources, and URIs through configuration

Hosts can replace models, admin Resources, and admin paths independently without editing package source or copying every built-in page. The Nova `models` / `resources` / `uri_key` configuration corresponds to Filament `models` / `resources` / `resource_slugs`.

Create a host model extending the package model:

```php
namespace App\Models;

class Menu extends \Fayne\Menu\Models\Menu
{
}
```

Then extend the package Resource. This example adds a host field to the original form and stores it in an existing JSON attribute:

```php
namespace App\Filament\Resources;

use Fayne\Menu\Filament\Resources\MenuResource as BaseMenuResource;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class MenuResource extends BaseMenuResource
{
    public static function form(Schema $schema): Schema
    {
        $schema = parent::form($schema);

        return $schema->components([
            ...$schema->getComponents(),
            TextInput::make('settings.project_note'),
        ]);
    }
}
```

Set the corresponding keys in the published configuration. This is a configuration fragment. Laravel recursively merges configuration: host values take precedence, while omitted nested keys retain package defaults:

```php
// config/filament-menu.php
'models' => [
    'menu' => \App\Models\Menu::class,
],
'resources' => [
    'menu' => \App\Filament\Resources\MenuResource::class,
],
'resource_slugs' => [
    'menu' => 'content-menus',
],
```

- Models, Resources, and slugs can be replaced independently. `models` selects model classes; configuration is not inferred from a host Resource's `$model` property.
- Supported mapping keys are `menu`, `menu_item`, `menu_component`, `menu_component_field`, `menu_widget`, `menu_widget_field`. Items, components, widgets, and fields can each be replaced. Custom classes should extend their corresponding package classes and preserve tenant, language, relationship, content, and authorization contracts.
- The plugin registers configured Resources; built-in lists, creation, editing, and relationship entry points follow them. Language switching and MenuTree do not redirect back to default Resources. Inherit `getPages()` or override it to replace individual pages.
- `resource_slugs` is empty by default. Without an entry, Filament's native `$slug`, default inference, and Panel ResourceConfiguration remain effective. Explicit configuration takes priority when inheriting the package method. A host overriding `getSlug()` without calling its parent is responsible for honoring configuration itself.
- `content-menus` changes the admin Resource path, for example `/admin/content-menus`, not frontend content paths or Headless APIs. Customize navigation labels, groups, tables, and actions through native Filament overrides.
- Keep registration through `MenuPlugin`. When using Resource auto-discovery, avoid discovering/registering both the default and replacement classes. Register custom policies through Laravel, with the appropriate host provider order, without bypassing authorization scopes.

After changes, clear and regenerate configuration/route caches through the deployment process and restart long-running processes. Older published configuration can add these keys; omitted model/Resource defaults are filled automatically. No business-table or content-format changes are required.

## 4. Menus and tree editing

1. Create a menu with its name, slug, content language, and allowed depth.
2. Create items with names, URLs, link targets, and active states. Item language comes from the owning menu.
3. Open the tree editor and drag nodes to reorder or change depth. Only the current drop destination is highlighted. Click **Save** to persist ordering.
4. Use the row **+** action to add a sibling or child; the button below the tree adds a root node. Status changes and create/delete actions are separate operations, not part of an unsaved ordering change.
5. Disable nodes and handle their children before deletion; unavailable delete actions are disabled.

```php
// config/filament-menu.php
'menu_builder' => ['max_depth' => 5],
'use_default_frontend_routes' => true,
```

Effective depth is constrained by package configuration, menu depth, and component restrictions. The server validates the complete node set, relationships, menu/language ownership, and depth instead of trusting browser ordering.

## 5. Chinese, English, and content languages

The interface provides `zh_CN` and `en`. Administrator interface language is independent of menu content language; global or tenant content languages are configured in Core.

```php
// config/filament-cms.php
'multilingual_enabled' => true,
'default_locale' => 'en',
'supported_locales' => ['en' => 'English', 'zh_CN' => '简体中文'],
```

The list groups menus by translation group. Switch existing translations on the edit or tree page; the current language is excluded, and a single alternative opens directly. **Create translation** is hidden when all supported versions exist. Each version has its own items and hierarchy; edits do not automatically translate or synchronize another version. Supply localized component names, field labels, and menu text yourself.

## 6. E-commerce demo data

```bash
php artisan cms:menu:init
php artisan cms:menu:init --tenant=shop
php artisan cms:menu:init --no-interaction
```

Demo creation defaults to no and requires an explicit interactive choice. In tenant mode, `--tenant` accepts an active tenant ID or slug; without it, a trusted current context takes priority, otherwise multiple tenants can be selected. Omit `--tenant` for a single site. Non-interactive runs only initialize permissions.

`Fayne\Menu\Database\Seeders\DemoMenuSeeder` creates English and Simplified Chinese versions of `demo-main-navigation` and `demo-footer-navigation`, with sample categories, account links, and shopping information. Supported languages must include `en` and `zh_CN`/`zh-CN`; the seeder does not change language settings. Menus start inactive while sample items are active. An existing slug in either language causes the whole group to be skipped without overwriting edits. This creates navigation data, not products, orders, or destination pages.

## 7. Frontend APIs, builds, and previews

| Purpose | Request | Behavior |
| --- | --- | --- |
| Current menu | `GET /modules/menus/frontend/{slug}?locale=en` | Only active menus and nodes; descendants of inactive parents are also hidden |
| v2 current menu | `GET /modules/menus/v2/frontend/{slug}?locale=en` | Also returns an array of items without a `data` envelope |
| Editor preview | `GET /modules/menus/preview/{slug}?locale=en` | Requires an admin session and `view` permission in the current tenant; includes inactive nodes and returns `private, no-store` |
| Frozen build input | `GET /headless/releases/{releaseId}/menus` | Release snapshot entries under `data`; only `frozen`/`succeeded` manifests |

Public and preview endpoints accept `region` values from Core configuration only. In tenant mode, Host resolves an active domain and tenant; unknown/inactive domains return 404. Single sites use scope 0. Menu preview requires an administrator login cookie usable on that domain; a build token does not replace login.

Frozen endpoints follow Core's `CMS_HEADLESS_PREFIX`. Build servers use `CMS_BUILD_API_TOKEN`:

```bash
curl -H "Authorization: Bearer $CMS_BUILD_API_TOKEN" \
  'https://cms.example.com/headless/releases/RELEASE_ID/menus'
```

Frozen reads require a valid token and a manifest tenant matching the request domain. Legacy manifests without tenant ownership are rejected and must be republished. Keep build credentials on the server. Static builds should read Page, Menu, and settings from the same release ID. Live menus read current edited records and are not guaranteed to reflect only the last release. `frozen` means a build candidate, not a successful deployment.

## 8. Component content and extension

Manage additional item content through Components, Widgets, and their fields using stable Page Builder resolver identifiers. The content builder is hidden when no Component definitions exist.

Use `filament-menu.models`, `tables`, and `foreign_keys` to replace models or map storage. Custom models must preserve data, language, tenant, and authorization contracts. `internal_path_suggestion_route` names a host-defined internal-path suggestion route. Disabling `use_default_frontend_routes` disables the default live/preview routes, not the separately registered frozen release endpoint. See [Page Builder](../../laravel-filament-page-builder/docs/README.en.md) for complex fields.

## 9. Tests and deployment

Run in this package directory:

```bash
composer install
composer test
composer format
```

Drag tests live in Nestable: `node --test tests/drag-preview.test.cjs`. Package tests do not replace target-database, real editor drag/save, tenant isolation, frontend build, or publication callback acceptance. Ship all local dependencies, refresh configuration/route caches, and restart long-running processes. Do not run destructive development-host initialization in production.

The library does not commit `composer.lock`; local generated lock files are ignored. Host applications should retain their own lock file for production deployment.

## Contributing

1. Create a feature branch.
2. Implement the change and add tests for new behavior or bug fixes.
3. Verify the relevant tests and state any outstanding environment acceptance.
4. Update both language versions when public usage changes.
5. Submit a merge request.

## License

This software uses a [proprietary license](../LICENSE.md). Use and distribution are subject to its authorization restrictions.

## Whole-site frozen API v2

With Core publication routing configured, `/headless/releases/{release_id}/menus` reads the immutable public bundle, independent of current region, locale or routing settings. Authenticate with the build Bearer token through the owning tenant domain. Menu reads require an explicit `scope_id` when multiple scopes are available; optional `target_id` must match it. Unknown or incompatible scopes/targets return 422. Each response contains only that frozen scope and its resolved public links; it excludes internal translation-group and connection metadata. V1 API responses remain compatible.
