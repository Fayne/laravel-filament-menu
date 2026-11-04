<p align="center"><img src="https://res.cloudinary.com/fayne/image/upload/v1787643269/fayne_logo.png" width="300"></p>

# Laravel Filament Menu

简体中文 | [English](docs/README.en.md)

适用于 **PHP 8.4+、Laravel 13、Filament 5** 的菜单管理包。提供多语言菜单、层级编辑、组件内容及前端 API，基于 CMS Core 的认证、授权与可选多租户能力。

## 功能与边界

| 能力 | 说明 |
| --- | --- |
| 菜单与菜单项 | 名称、slug、链接、启用状态及独立菜单树 |
| 层级编辑 | 拖拽排序/缩进、单一落点提示、展开/折叠、添加根节点/兄弟/子节点、编辑与状态操作 |
| 删除约束 | 启用节点不能删除；有子节点时需先处理子节点 |
| 组件与 Widget | 定义可复用字段和嵌套内容；没有组件定义时隐藏内容配置 |
| 中英文与译本 | 简体中文/英语界面；翻译组管理独立语言版本 |
| 前端与发布 | 实时树、管理员预览以及冻结发布快照 |

## 1. 全新项目安装

先按 [CMS Core](../laravel-filament-cms-core/README.md) 配置宿主认证、Panel 与权限，再在宿主 Composer 中配置本私有包及全部关联 `fayne/*` 依赖的软件源。依赖包括 CMS Core、Nestable 与 Page Builder。

```bash
composer require fayne/laravel-filament-menu
php artisan vendor:publish --tag=filament-menu-config
php artisan cms:install
php artisan filament:assets
php artisan cms:menu:install
```

`cms:menu:install` 检查模块表结构是否就绪，默认不生成内容；请先运行 `cms:install`，由 Core 统一执行迁移和权限初始化。仅显式传入 `--demo` 才创建未发布的中英文演示内容，不再询问是否生成示例。生产环境需确认或使用 `--force`。不创建管理员或自动授予账号权限。`app:init`、`admin:create` 是宿主命令。

迁移面向全新数据库，默认使用 `filament_*` 表名；表名及关联键可配置。旧 Nova 数据库需另行评估结构和数据迁移，不要在业务库使用 `migrate:fresh`。

本包权限由 [`resources/permissions.php`](resources/permissions.php) 自行定义，Service Provider 注册到 Core 的 `PermissionRegistry`；Core 不再硬编码本模块权限。运行 `cms:install` 汇总已注册模块并创建默认角色。已有角色不自动扩权；新增模块后须明确配置新增授权。详见 [权限扩展](../laravel-filament-cms-core/docs/permission-registration.md)。

## 2. 注册后台与权限

在普通站点 Panel 注册本插件；多租户时注册在租户工作台，而不是平台管理端。保留宿主现有的 session、CSRF、认证中间件与 CMS 插件。

```php
use Fayne\Menu\Filament\MenuPlugin;

return $panel->plugins([
    // Keep the host's existing plugins.
    MenuPlugin::make(),
]);
```

使用 CMS Core 的角色与权限界面分配菜单、菜单项及定义管理权限。平台超级管理员不自动绕过租户成员关系或内容归属。初始化权限不等于为所有管理员授权。

## 3. 通过配置扩展 Model、Resource 与 URI

宿主可以分别替换模型、后台 Resource 和后台路径，无需修改包源码，也无需复制所有内置页面。对应旧 Nova 的 `models` / `resources` / `uri_key`，Filament 使用 `models` / `resources` / `resource_slugs`。

先创建继承包模型的宿主模型：

```php
namespace App\Models;

class Menu extends \Fayne\Menu\Models\Menu
{
}
```

再继承包 Resource；下面在原表单中追加宿主字段，使用已有 JSON 属性保存：

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

在已发布配置中设置对应键。以下是配置片段；配置使用 Laravel 原生递归合并，宿主同名值优先，未填写的嵌套键保留包默认值：

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

- 模型、Resource 和 slug 可独立配置，不要求三者同时替换。`models` 是模型选择的配置入口，不依赖宿主 Resource 的 `$model` 属性猜测配置。
- 两个映射支持的键为：`menu`、`menu_item`、`menu_component`、`menu_component_field`、`menu_widget`、`menu_widget_field`。菜单项、组件、Widget 与字段可以分别替换。自定义类应继承对应包类，并保留原有租户、语言、关联、内容及权限契约。
- 插件注册配置的 Resource；内置列表、创建、编辑及关联入口跟随新类。语言切换和 MenuTree不会回跳默认 Resource。宿主可以继承 `getPages()`，也可覆写它以替换个别页面。
- `resource_slugs` 默认为空。未配置时保留 Filament 原生 `$slug`、默认推导及 Panel ResourceConfiguration；继承包方法时，显式配置优先。自行覆写 `getSlug()` 且不调用父方法时，由宿主实现负责处理配置。
- `content-menus` 修改的是后台 Resource 路径，例如 `/admin/content-menus`；不修改前端内容路径或 Headless API。导航标签、分组、表格和操作可用 Filament 原生覆写方式定制。
- 继续通过 `MenuPlugin` 注册；若宿主启用了 Resource 自动发现，避免同时发现/注册默认类与替代类。自定义 Policy 使用 Laravel 标准注册方式，并在宿主中确保注册顺序，不绕过原有授权范围。

修改后清理并按部署流程重新生成配置/路由缓存，重启长驻进程。旧版已发布配置可以补充上述新键，缺失模型/Resource 默认键会自动补齐；不需要改动业务表或内容结构。

## 4. 菜单与层级编辑

1. 创建菜单，填写名称、slug、内容语言及允许深度。
2. 创建菜单项，配置名称、URL、目标窗口及启用状态。菜单项语言由所属菜单确定。
3. 打开层级编辑页，拖动节点改变顺序或层级；只显示当前释放位置的提示。完成排序后点击“保存”。
4. 使用节点右侧“＋”选择添加兄弟或子节点；树底部按钮添加根节点。状态操作与新增/删除是独立操作，不应当作未保存排序的一部分。
5. 删除前先停用节点，并处理子节点；不允许删除的按钮置灰。

```php
// config/filament-menu.php
'menu_builder' => ['max_depth' => 5],
'use_default_frontend_routes' => true,
```

最终深度同时受包配置、菜单深度与组件限制约束。服务端校验完整节点集合、父子关系、菜单/语言归属和深度；不会只信任浏览器提交的顺序。

## 5. 中英文与内容语言

界面提供 `zh_CN` 和 `en`。管理员的界面语言与菜单内容语言独立；全局或租户内容语言由 Core 配置。

```php
// config/filament-cms.php
'multilingual_enabled' => true,
'default_locale' => 'en',
'supported_locales' => ['en' => 'English', 'zh_CN' => '简体中文'],
```

列表按翻译组展示菜单；在编辑页和层级页切换已有译本，候选排除当前语言，仅有一个时直接切换。所有语言均已创建时不显示“创建翻译”。译本各有菜单项和层级，修改一份不会自动翻译或同步另一份。自定义组件名称、字段标签及菜单文字需要自行提供对应内容。

## 6. 电商演示数据

```bash
php artisan cms:menu:install
php artisan cms:menu:install --demo
php artisan cms:menu:install --demo --tenant=shop
php artisan cms:menu:install --no-interaction
```

默认安装不创建演示数据；只有显式 `--demo` 才生成草稿。`--tenant` 必须与 `--demo` 一起使用，接受启用租户的 ID 或 slug；多租户非交互演示必须指定目标，交互时可沿用可信上下文或多选启用租户。单租户不传 `--tenant`，内容写入作用域 0。

`Fayne\Menu\Database\Seeders\DemoMenuSeeder` 创建英文与简体中文的 `demo-main-navigation`、`demo-footer-navigation`，包括商品分类、账户、购物指南等示例链接。支持语言必须包含 `en` 及 `zh_CN`/`zh-CN`；Seeder 不修改语言配置。新菜单默认停用，示例节点启用。已有同 slug 的任一译本时跳过整组，不覆盖编辑。只创建导航数据，不创建商品、订单或目标页面。

## 7. 前端 API 与构建预览

| 用途 | 请求 | 行为 |
| --- | --- | --- |
| 当前菜单 | `GET /modules/menus/frontend/{slug}?locale=en` | 只返回启用菜单及启用节点；停用父项的后代也被隐藏 |
| v2 当前菜单 | `GET /modules/menus/v2/frontend/{slug}?locale=en` | 同样返回菜单项数组，不额外包裹 `data` |
| 编辑预览 | `GET /modules/menus/preview/{slug}?locale=en` | 需要管理员登录会话及当前租户的 `view` 权限；包含停用节点，响应 `private, no-store` |
| 冻结构建输入 | `GET /headless/releases/{releaseId}/menus` | 返回 `data` 中的发布快照条目；只允许 `frozen`/`succeeded` 清单 |

公开和预览接口可传 `region`，只接受 Core 配置的区域。多租户按已启用域名及租户解析 Host；未知/停用域名返回 404，单站使用范围 0。菜单预览需要域名能接收管理员登录 Cookie，不使用构建 token 代替登录。

冻结接口遵循 Core 的 `CMS_HEADLESS_PREFIX`；构建服务器使用 `CMS_BUILD_API_TOKEN`：

```bash
curl -H "Authorization: Bearer $CMS_BUILD_API_TOKEN" \
  'https://cms.example.com/headless/releases/RELEASE_ID/menus'
```

冻结读取要求 token 正确且清单租户与请求域名匹配；旧无租户归属清单拒绝读取，需重新发布。构建凭证只保存在服务器。静态构建应从同一 release ID 读取 Page、Menu 和设置；实时菜单返回当前编辑记录，不能保证只反映最后一次发布。`frozen` 代表构建候选，不代表部署成功。

## 8. 组件内容与扩展

通过 Component、Widget 及其字段管理菜单项附加内容，字段使用 Page Builder 的稳定 resolver 标识。没有 Component 定义时，不显示内容构建区域。

配置 `filament-menu.models`、`tables`、`foreign_keys` 可替换模型或映射表结构；自定义模型应保留原有数据、语言、租户和授权契约。`internal_path_suggestion_route` 指向宿主定义的内部路径建议路由名。关闭 `use_default_frontend_routes` 只关闭默认实时/预览路由，不关闭独立的冻结发布接口。复杂字段详见 [Page Builder](../laravel-filament-page-builder/README.md)。

## 9. 测试与上线

在本包目录执行：

```bash
composer install
composer test
composer format
```

拖拽测试位于 Nestable 包：`node --test tests/drag-preview.test.cjs`。包测试通过不能替代目标数据库、真实后台拖拽保存、跨租户隔离、前端构建及发布回调验收。部署时包含全部本地依赖，刷新配置/路由缓存并重启长驻进程。不要在生产执行开发宿主的破坏性初始化流程。

开发包的 `composer.lock` 不提交，允许本地生成并忽略；宿主应用应保留自己的锁文件用于生产部署。

## 贡献代码

1. 创建功能分支。
2. 实现所需改动，并为新增功能或缺陷修复补充测试。
3. 确认相关测试通过，注明尚未完成的环境验收。
4. 修改公开用法时，同步更新中英文文档。
5. 提交合并请求。

## 许可证

本软件采用[专有许可证](LICENSE.md)，使用与分发须遵守其中的授权限制。

## 整站冻结 API v2

启用 Core 发布路由后，`/headless/releases/{release_id}/menus` 从不可变公共清单读取，不依赖当前区域、语言或路由配置。通过所属租户域名及构建 Bearer token 鉴权。存在多个范围时，菜单读取必须指定 `scope_id`；可选 `target_id` 必须与其匹配，未知或不兼容值返回 422。仅返回选中冻结范围及已解析公共链接，不暴露内部翻译组和连接信息；v1 API 保持兼容。


本包权限名称、分组及标准操作由 `resources/permissions.php` 声明，中英文译文由本包 `resources/lang` 提供。CMS Core 仅汇总和解析，不再维护本包的业务名称映射。
