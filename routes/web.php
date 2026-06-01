<?php

use App\Http\Controllers\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Admin\BlogController as AdminBlogController;
use App\Http\Controllers\Admin\BuffAccountController;
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
Route::get('/blog/{post}', [BlogController::class, 'show'])->whereNumber('post')->name('blog.show');
Route::get('/blog/{slug}', [BlogController::class, 'redirectFromSlug'])->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')->name('blog.legacy');
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

        Route::get('/blog', [AdminBlogController::class, 'index'])->name('blog.index');
        Route::get('/blog/create', [AdminBlogController::class, 'create'])->name('blog.create');
        Route::post('/blog/upload-image', [AdminBlogController::class, 'uploadImage'])->name('blog.upload-image');
        Route::post('/blog', [AdminBlogController::class, 'store'])->name('blog.store');
        Route::get('/blog/{blog}/edit', [AdminBlogController::class, 'edit'])->name('blog.edit');
        Route::put('/blog/{blog}', [AdminBlogController::class, 'update'])->name('blog.update');
        Route::delete('/blog/{blog}', [AdminBlogController::class, 'destroy'])->name('blog.destroy');

        Route::get('/buff-accounts', [BuffAccountController::class, 'index'])->name('buff-accounts.index');
        Route::get('/buff-accounts/create', [BuffAccountController::class, 'create'])->name('buff-accounts.create');
        Route::post('/buff-accounts', [BuffAccountController::class, 'store'])->name('buff-accounts.store');
        Route::post('/buff-accounts/import-env', [BuffAccountController::class, 'importFromEnv'])->name('buff-accounts.import-env');
        Route::get('/buff-accounts/{buffAccount}/edit', [BuffAccountController::class, 'edit'])->name('buff-accounts.edit');
        Route::put('/buff-accounts/{buffAccount}', [BuffAccountController::class, 'update'])->name('buff-accounts.update');
        Route::delete('/buff-accounts/{buffAccount}', [BuffAccountController::class, 'destroy'])->name('buff-accounts.destroy');
        Route::post('/buff-accounts/probe-all', [BuffAccountController::class, 'probeAll'])->name('buff-accounts.probe-all');
        Route::post('/buff-accounts/cstrade-probe', [BuffAccountController::class, 'probeCsTrade'])->name('buff-accounts.cstrade-probe');
        Route::post('/buff-accounts/empire-probe', [BuffAccountController::class, 'probeEmpire'])->name('buff-accounts.empire-probe');
        Route::put('/buff-accounts/exchange-rates', [BuffAccountController::class, 'updateExchangeRates'])->name('buff-accounts.exchange-rates');
        Route::post('/buff-accounts/{label}/probe', [BuffAccountController::class, 'probe'])
            ->where('label', '[a-zA-Z0-9\-]+')
            ->name('buff-accounts.probe');

        Route::post('/logout', [AdminAuthController::class, 'logout'])->name('logout');
    });
});
