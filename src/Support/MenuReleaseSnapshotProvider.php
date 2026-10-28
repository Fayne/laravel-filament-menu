<?php

namespace Fayne\Menu\Support;

use Fayne\CMS\Contracts\ReleaseSnapshotProvider;
use Fayne\CMS\Models\DeploymentRecord;
use Fayne\CMS\Support\ContentLocale;
use Fayne\CMS\Support\PublicationRegion;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Context;
use Illuminate\Validation\ValidationException;

final class MenuReleaseSnapshotProvider implements ReleaseSnapshotProvider
{
    public function entityType(): string
    {
        return 'menu';
    }

    public function snapshot(DeploymentRecord $release): array
    {
        $locale = ContentLocale::resolve(data_get($release->deployment_contents, 'locale'));
        $regionId = PublicationRegion::resolve(data_get($release->deployment_contents, 'region'));
        $connection = config('database.default');
        if ($regionId !== null && $regionId !== '') {
            $region = collect(config('filament-cms.regions', []))->first(fn ($region) => (string) ($region['id'] ?? '') === (string) $regionId);
            $connection = $region['database'] ?? null;
            if (! $connection || ! config('database.connections.'.$connection)) {
                throw ValidationException::withMessages(['region' => __('filament-menu::errors.release_region_database_missing')]);
            }
        }

        return Context::scope(function () use ($locale, $connection, $release): array {
            $model = config('filament-menu.models.menu');

            return (new $model)->setConnection($connection)->newQuery()->where('is_active', true)->where('locale', $locale)->get()->map(function ($menu) use ($release): array {
                $tree = $menu->publicTree()->map(fn ($item) => $item->publicProjection())->all();
                $compare = function (array $nodes) use (&$compare): array {
                    return array_map(function ($node) use (&$compare): array {
                        $node = Arr::except($node, ['created_at', 'updated_at', 'tenant_id']);
                        $node['children'] = $compare($node['children'] ?? []);

                        return $node;
                    }, $nodes);
                };

                return [...(data_get($release->deployment_contents, 'publication_mode') === 'whole_site' ? ['translation_group_id' => $menu->translation_group_id] : []), 'entity_type' => 'menu', 'entity_id' => (string) $menu->getKey(), 'slug' => $menu->slug, 'locale' => $menu->locale,
                    'connection' => $menu->getConnectionName(), 'projection' => $tree, 'menu' => $menu->only(['name', 'slug', 'settings', 'deep', 'locale']),
                    'comparison_projection' => ['menu' => $menu->only(['name', 'slug', 'settings', 'deep', 'locale']), 'tree' => $compare($tree)]];
            })->all();
        }, ['filament-cms.region_id' => $regionId ?? '']);
    }
}
