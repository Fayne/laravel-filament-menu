<?php

use Fayne\Menu\Http\Controllers\MenuController;
use Fayne\Menu\Http\Controllers\MenuPreviewController;
use Fayne\Menu\Http\Controllers\MenuV2Controller;
use Illuminate\Support\Facades\Route;

Route::get('frontend/{slug}', MenuController::class)->name('frontend.detail');
Route::get('preview/{slug}', MenuPreviewController::class)->middleware(['web', 'auth:'.config('filament-cms.guard', 'admin')])->name('preview.detail');

Route::get('v2/frontend/{slug}', MenuV2Controller::class)->name('v2.frontend.detail');
