<?php

namespace Fayne\Menu\Http\Controllers;

use Fayne\CMS\Support\ContentLocale;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class MenuV2Controller extends Controller
{
    public function __invoke(Request $request, string $slug)
    {
        $request->validate(['locale' => ['nullable', 'string']]);
        $locale = ContentLocale::resolve($request->query('locale'));
        $menu = config('filament-menu.models.menu')::query()->where('is_active', true)->where('locale', $locale)->where('slug', $slug)->firstOrFail();

        return response()->json($menu->publicTree()->map(fn ($item) => $item->publicProjection())->all());
    }
}
