<?php

namespace Fayne\Menu\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;

class MenuPreviewController extends Controller
{
    public function __invoke(Request $request, string $slug)
    {
        $request->validate(['locale' => ['nullable', 'string']]);
        $menu = config('filament-menu.models.menu')::query()->locale($request->query('locale'))->where('slug', $slug)->firstOrFail();
        Gate::authorize('view', $menu);

        return response()->json($menu->publicTree(false)->map(fn ($item) => $item->publicProjection())->all(), 200, ['Cache-Control' => 'private, no-store']);
    }
}
