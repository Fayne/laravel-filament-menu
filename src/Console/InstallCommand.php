<?php

namespace Fayne\Menu\Console;

use Fayne\CMS\Models\Tenant;
use Fayne\CMS\Support\ContentLocale;
use Fayne\CMS\Support\TenantContext;
use Fayne\Menu\Database\Seeders\DemoMenuSeeder;
use Fayne\Menu\Models\Menu;
use Illuminate\Console\ConfirmableTrait;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

class InstallCommand extends Command
{
    use ConfirmableTrait;

    protected $signature = 'cms:menu:install {--demo : Create unpublished bilingual demo content} {--tenant= : Target tenant ID or slug for demo data} {--force : Allow installation in production}';

    protected $description = 'Install the Menu module; optionally create unpublished bilingual demo menus.';

    public function handle(): int
    {
        if (! $this->confirmToProceed()) {
            return self::FAILURE;
        }
        if (! (app()->bound(\Fayne\CMS\Support\InstallationContext::class) && app(\Fayne\CMS\Support\InstallationContext::class)->prepared)) {
            if ($this->call('vendor:publish', ['--tag' => ['filament-menu-config', 'filament-menu-migrations'], '--no-interaction' => true]) !== self::SUCCESS) {
                return self::FAILURE;
            }
        }
        if (! $this->option('demo') && $this->option('tenant') !== null) {
            $this->error(__('filament-menu::commands.tenant_requires_demo'));

            return self::FAILURE;
        }
        if (! (app()->bound(\Fayne\CMS\Support\InstallationContext::class) && app(\Fayne\CMS\Support\InstallationContext::class)->prepared)) {
            if ($this->call('migrate', ['--force' => true]) !== self::SUCCESS) {
                return self::FAILURE;
            }
        }
        $model = config('filament-menu.models.menu', Menu::class);
        if (! (new $model)->getConnection()->getSchemaBuilder()->hasTable((new $model)->getTable())) {
            $this->error(__('filament-menu::commands.install_core_first'));

            return self::FAILURE;
        }
        if (! $this->option('demo')) {
            $this->info(__('filament-menu::commands.installed'));

            return self::SUCCESS;
        }

        try {
            if (! config('filament-cms.tenancy.enabled', false)) {
                if ($this->option('tenant') !== null) {
                    $this->error(__('filament-menu::commands.tenancy_disabled'));

                    return self::FAILURE;
                }

                return $this->seedDemo();
            }
            $tenants = $this->targetTenants();
            if ($tenants === []) {
                return self::FAILURE;
            }

            foreach ($tenants as $tenant) {
                $this->line($tenant->name);
                $status = app(TenantContext::class)->run($tenant, fn (): int => $this->seedDemo());
                if ($status !== self::SUCCESS) {
                    return $status;
                }
            }

            return self::SUCCESS;
        } catch (ValidationException $exception) {
            $this->error(collect($exception->errors())->flatten()->implode(' '));

            return self::FAILURE;
        }
    }

    private function targetTenants(): array
    {
        $target = $this->option('tenant') ?? app(TenantContext::class)->id();
        $query = Tenant::where('is_active', true);
        if ($target !== null) {
            $tenant = ctype_digit((string) $target) ? $query->find($target) : $query->where('slug', $target)->first();
            if (! $tenant) {
                $this->error(__('filament-menu::commands.tenant_not_found'));
            }

            return $tenant ? [$tenant] : [];
        }
        if (! $this->input->isInteractive()) {
            $this->error(__('filament-menu::commands.tenant_required'));

            return [];
        }
        $tenants = $query->orderBy('id')->get();
        if ($tenants->isEmpty()) {
            $this->error(__('filament-menu::commands.tenant_not_found'));

            return [];
        }
        $labels = $tenants->mapWithKeys(fn (Tenant $tenant): array => [$tenant->id => $tenant->name]);
        $selected = \Laravel\Prompts\multiselect(
            label: __('filament-menu::commands.select_tenant'),
            options: $labels->all(),
            required: true,
        );

        return $tenants->whereIn('id', $selected)->all();
    }

    private function seedDemo(): int
    {
        $status = $this->call('db:seed', ['--force' => true, '--class' => DemoMenuSeeder::class]);
        if ($status === self::SUCCESS) {
            $this->info(__('filament-menu::commands.demo_ready'));
            if (! ContentLocale::enabled()) {
                $this->warn(__('filament-menu::commands.multilingual_disabled'));
            }
        }

        return $status;
    }
}
