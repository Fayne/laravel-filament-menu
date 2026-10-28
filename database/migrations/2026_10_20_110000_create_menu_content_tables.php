<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach ($this->connections() as $connection) {
            $this->createTables($connection);
        }
    }

    public function down(): void
    {
        foreach ($this->connections() as $connection) {
            $this->dropTables($connection);
        }
    }

    private function connections(): array
    {
        return array_values(array_unique(array_filter(array_column(config('filament-cms.regions', []), 'database')))) ?: [null];
    }

    protected function createTables(?string $connection): void
    {
        Schema::connection($connection)->create(config('filament-menu.tables.menus'), function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(false);
            $table->unsignedSmallInteger('deep')->nullable();
            $table->json('settings')->nullable();
            $table->boolean('changed')->default(false);
            $table->string('locale')->default('en');
            $table->unsignedBigInteger('fallback_id')->nullable();
            $table->unique(['tenant_id', 'slug', 'locale']);
            $table->timestamps();
        });
        Schema::connection($connection)->create(config('filament-menu.tables.menu_items'), function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->foreignId(config('filament-menu.foreign_keys.menu_id'))->constrained(config('filament-menu.tables.menus'))->cascadeOnDelete();
            $table->foreignId(config('filament-menu.foreign_keys.parent_id'))->nullable()->constrained(config('filament-menu.tables.menu_items'))->cascadeOnDelete();
            $table->string('name');
            $table->string('url')->nullable();
            $table->string('target')->default('_self');
            foreach (['content', 'available_levels'] as $name) {
                $table->json($name)->nullable();
            }
            $table->unsignedInteger('weight')->default(0);
            $table->unsignedSmallInteger('level')->default(1);
            $table->string('locale')->default('en');
            $table->boolean('is_active')->default(false);
            $table->string('icon')->nullable();
            $table->string('css_class')->nullable();
            $table->timestamps();
        });
        foreach (['component', 'widget'] as $kind) {
            Schema::connection($connection)->create(config("filament-menu.tables.menu_{$kind}s"), function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->string('name');
                $table->string('slug');
                $table->unique(['tenant_id', 'slug']);
                $table->text('description')->nullable();
                $table->unsignedInteger('weight')->default(0);
                $table->json('mockup')->nullable();
                $table->json('specifications')->nullable();
                $table->unsignedInteger('usage')->default(0);
                $table->unsignedSmallInteger('max_deep')->nullable();
                $table->timestamps();
            });
            Schema::connection($connection)->create(config("filament-menu.tables.menu_{$kind}_fields"), function (Blueprint $table) use ($kind) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->foreignId($kind === 'component' ? 'nova_menu_component_id' : 'menu_widget_id')->constrained(config("filament-menu.tables.menu_{$kind}s"))->cascadeOnDelete();
                $table->string('title');
                $table->string('slug')->nullable();
                $table->string('resolver');
                $table->json('options')->nullable();
                $table->json('rules')->nullable();
                $table->unsignedInteger('weight')->default(99);
                $table->timestamps();
            });
        }
    }

    protected function dropTables(?string $connection): void
    {
        foreach (['menu_widget_fields', 'menu_widgets', 'menu_component_fields', 'menu_components', 'menu_items', 'menus'] as $name) {
            Schema::connection($connection)->dropIfExists(config('filament-menu.tables.'.$name));
        }
    }
};
