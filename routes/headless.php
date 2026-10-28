<?php

use Fayne\Menu\Http\Controllers\ReleaseMenuController;
use Illuminate\Support\Facades\Route;

Route::get(config('filament-menu.headless.release_menus_path', 'releases/{releaseId}/menus'), [ReleaseMenuController::class, 'index'])->name('filament-cms.headless.menus');
