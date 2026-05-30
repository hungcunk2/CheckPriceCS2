<?php

use App\Http\Controllers\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Admin\InventoryController as AdminInventoryController;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\PublicInventoryController;
use App\Http\Controllers\SitemapController;
use App\Http\Middleware\EnsureAdmin;
use Illuminate\Support\Facades\Route;

Route::get('/', [PublicInventoryController::class, 'landing'])->name('public.landing');
Route::post('/', [PublicInventoryController::class, 'landing']);
Route::get('/bang-gia', [PublicInventoryController::class, 'index'])->name('public.index');
Route::get('/blog', [BlogController::class, 'index'])->name('blog.index');
Route::get('/blog/{slug}', [BlogController::class, 'show'])->name('blog.show');
Route::get('/kho/{inventory}', [PublicInventoryController::class, 'show'])->whereNumber('inventory')->name('public.show');
Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');

Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('/login', [AdminAuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AdminAuthController::class, 'login'])->name('login.submit');

    Route::middleware(EnsureAdmin::class)->group(function () {
        Route::get('/', fn () => redirect()->route('admin.inventories.index'));

        Route::get('/inventories', [AdminInventoryController::class, 'index'])->name('inventories.index');
        Route::get('/inventories/create', [AdminInventoryController::class, 'create'])->name('inventories.create');
        Route::post('/inventories', [AdminInventoryController::class, 'store'])->name('inventories.store');
        Route::get('/inventories/{inventory}/edit', [AdminInventoryController::class, 'edit'])->name('inventories.edit');
        Route::put('/inventories/{inventory}', [AdminInventoryController::class, 'update'])->name('inventories.update');
        Route::delete('/inventories/{inventory}', [AdminInventoryController::class, 'destroy'])->name('inventories.destroy');
        Route::post('/inventories/{inventory}/refresh', [AdminInventoryController::class, 'refresh'])->name('inventories.refresh');

        Route::post('/logout', [AdminAuthController::class, 'logout'])->name('logout');
    });
});
