<?php

namespace Fayne\Menu\Http\Controllers;

use Fayne\CMS\Support\ContentLocale;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class MenuController extends Controller
{
    public function __invoke(Request $request, string $slug)
    {
        $request->validate(['locale' => ['nullable', 'string']]);
        $menu = config('filament-menu.models.menu')::query()->where('is_active', true)->locale($request->query('locale') ?? ContentLocale::default())->where('slug', $slug)->firstOrFail();

        return response()->json($menu->publicTree()->map(fn ($item) => $item->publicProjection())->all());
    }
}
