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
php artisan migrate
php artisan filament:assets
php artisan cms:menu:init
```

`cms:menu:init` 初始化权限目录，并询问是否创建演示数据；它不创建管理员或自动授予超级管理员权限。本仓库的 `app:init` 是宿主命令，不属于本包。

迁移面向全新数据库，默认保留 `nova_*` 表名；表名及关联键可配置。旧 Nova 数据库需另行评估结构和数据迁移，不要在业务库使用 `migrate:fresh`。

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

## 3. 菜单与层级编辑

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

## 4. 中英文与内容语言

界面提供 `zh_CN` 和 `en`。管理员的界面语言与菜单内容语言独立；全局或租户内容语言由 Core 配置。

```php
// config/filament-cms.php
'multilingual_enabled' => true,
'default_locale' => 'en',
'supported_locales' => ['en' => 'English', 'zh_CN' => '简体中文'],
```

列表按翻译组展示菜单；在编辑页和层级页切换已有译本，候选排除当前语言，仅有一个时直接切换。所有语言均已创建时不显示“创建翻译”。译本各有菜单项和层级，修改一份不会自动翻译或同步另一份。自定义组件名称、字段标签及菜单文字需要自行提供对应内容。

## 5. 电商演示数据

```bash
php artisan cms:menu:init
php artisan cms:menu:init --tenant=shop
php artisan cms:menu:init --no-interaction
```

演示数据默认不创建，需在交互中明确选择。多租户下 `--tenant` 接受启用租户的 ID 或 slug；未指定时优先沿用可信上下文，否则多选目标租户。单站不传 `--tenant`。非交互运行只初始化权限。

`Fayne\Menu\Database\Seeders\DemoMenuSeeder` 创建英文与简体中文的 `demo-main-navigation`、`demo-footer-navigation`，包括商品分类、账户、购物指南等示例链接。支持语言必须包含 `en` 及 `zh_CN`/`zh-CN`；Seeder 不修改语言配置。新菜单默认停用，示例节点启用。已有同 slug 的任一译本时跳过整组，不覆盖编辑。只创建导航数据，不创建商品、订单或目标页面。

## 6. 前端 API 与构建预览

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

## 7. 组件内容与扩展

通过 Component、Widget 及其字段管理菜单项附加内容，字段使用 Page Builder 的稳定 resolver 标识。没有 Component 定义时，不显示内容构建区域。

配置 `filament-menu.models`、`tables`、`foreign_keys` 可替换模型或映射表结构；自定义模型应保留原有数据、语言、租户和授权契约。`internal_path_suggestion_route` 指向宿主定义的内部路径建议路由名。关闭 `use_default_frontend_routes` 只关闭默认实时/预览路由，不关闭独立的冻结发布接口。复杂字段详见 [Page Builder](../laravel-filament-page-builder/README.md)。

## 8. 测试与上线

在本包目录执行：

```bash
composer install
composer test
composer format
```

拖拽测试位于 Nestable 包：`node --test tests/drag-preview.test.cjs`。包测试通过不能替代目标数据库、真实后台拖拽保存、跨租户隔离、前端构建及发布回调验收。部署时包含全部本地依赖，刷新配置/路由缓存并重启长驻进程。不要在生产执行开发宿主的破坏性初始化流程。

## 贡献代码

1. 创建功能分支。
2. 实现所需改动，并为新增功能或缺陷修复补充测试。
3. 确认相关测试通过，注明尚未完成的环境验收。
4. 修改公开用法时，同步更新中英文文档。
5. 提交合并请求。

## 许可证

本软件采用[专有许可证](LICENSE.md)，使用与分发须遵守其中的授权限制。
