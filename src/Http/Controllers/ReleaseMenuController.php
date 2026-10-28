<?php

namespace Fayne\Menu\Http\Controllers;

use Fayne\CMS\Models\ReleaseManifest;
use Fayne\CMS\Publishing\FrozenReleaseAccess;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Arr;

final class ReleaseMenuController extends Controller
{
    public function index(Request $request, string $releaseId)
    {
        $manifest = ReleaseManifest::query()
            ->where('release_id', $releaseId)
            ->whereIn('status', ['frozen', 'succeeded'])
            ->firstOrFail();

        app(FrozenReleaseAccess::class)->authorize($request, $manifest);

        return response()->json([
            'data' => collect(app(\Fayne\CMS\Publishing\FrozenReleaseEntries::class)->select($manifest, $request, 'menu', true))->map(fn ($entry) => Arr::except($entry, ['comparison_projection', 'connection', 'content_snapshot', 'translation_group_id']))->values(),
        ]);
    }
}
