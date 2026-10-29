<?php

namespace Fayne\Menu;

use Fayne\CMS\Events\PublicationSucceeded;
use Fayne\CMS\Http\Middleware\ResolvePublicTenant;
use Fayne\CMS\Http\Middleware\SelectContentRegion;
use Fayne\CMS\Support\ReleaseSnapshotRegistry;
use Fayne\Menu\Console\InstallCommand;
use Fayne\Menu\Policies\MenuComponentFieldPolicy;
use Fayne\Menu\Policies\MenuComponentPolicy;
use Fayne\Menu\Policies\MenuItemPolicy;
use Fayne\Menu\Policies\MenuPolicy;
use Fayne\Menu\Policies\MenuWidgetFieldPolicy;
use Fayne\Menu\Policies\MenuWidgetPolicy;
use Fayne\Menu\Support\MenuDefinitionsReleaseSnapshotProvider;
use Fayne\Menu\Support\MenuReleaseSnapshotProvider;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class MenuServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->callAfterResolving(\Fayne\CMS\Support\Authorization\PermissionRegistry::class, function ($registry): void {
            $registry->register('menu', require __DIR__.'/../resources/permissions.php');
        });
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'filament-menu');
        $this->app->afterResolving(ReleaseSnapshotRegistry::class, function ($registry): void {
            if (! isset($registry->all()['global_menu_definitions'])) {
                $registry->register(new MenuDefinitionsReleaseSnapshotProvider);
            }
            if (! isset($registry->all()['menu'])) {
                $registry->register(new MenuReleaseSnapshotProvider);
            }
        });
    }

    public function boot(): void
    {
        $this->replaceConfigRecursivelyFrom(__DIR__.'/../config/filament-menu.php', 'filament-menu');

        Event::listen(PublicationSucceeded::class, function ($event): void {
            foreach ($event->deployment->releaseManifest->entries['menu'] ?? [] as $entry) {
                $model = config('filament-menu.models.menu');
                (new $model)->setConnection($entry['connection'] ?? config('database.default'))->newQuery()
                    ->whereKey($entry['entity_id'])->update(['changed' => false]);
            }
        });
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        if (! $this->app->routesAreCached()) {
            Route::prefix(config('filament-cms.headless.prefix', 'headless'))->middleware(['api', ResolvePublicTenant::class])->group(__DIR__.'/../routes/headless.php');
        }
        $this->commands([InstallCommand::class]);
        if (! $this->app->routesAreCached() && config('filament-menu.use_default_frontend_routes')) {
            Route::prefix('modules/menus')->middleware(['api', ResolvePublicTenant::class, SelectContentRegion::class])->name('modules.menus.')->group(__DIR__.'/../routes/frontend.php');
        }
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'filament-menu');
        Gate::policy(config('filament-menu.models.menu_widget_field'), MenuWidgetFieldPolicy::class);
        Gate::policy(config('filament-menu.models.menu_widget'), MenuWidgetPolicy::class);
        Gate::policy(config('filament-menu.models.menu_component_field'), MenuComponentFieldPolicy::class);
        Gate::policy(config('filament-menu.models.menu_component'), MenuComponentPolicy::class);
        Gate::policy(config('filament-menu.models.menu_item'), MenuItemPolicy::class);
        Gate::policy(config('filament-menu.models.menu'), MenuPolicy::class);
        $this->publishes([__DIR__.'/../config/filament-menu.php' => config_path('filament-menu.php')], 'filament-menu-config');
    }
}
