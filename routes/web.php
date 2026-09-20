<?php

use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicSiteController;
use App\Http\Controllers\SiteCreationController;
use Illuminate\Support\Facades\Route;

// ─── Tunnel public : générer un site depuis sa fiche Google, sans compte ───
Route::get('/', [PublicSiteController::class, 'landing'])->name('home');
Route::get('/recherche', [PublicSiteController::class, 'search'])->middleware('throttle:60,1')->name('public.search');
Route::post('/generer', [PublicSiteController::class, 'generate'])->middleware('throttle:8,1')->name('public.generate');
Route::get('/site/{slug}', [PublicSiteController::class, 'show'])->name('public.site');
Route::get('/site/{slug}/status', [PublicSiteController::class, 'status'])->name('public.site.status');
Route::post('/site/{slug}/retry', [PublicSiteController::class, 'retry'])->middleware('throttle:5,1')->name('public.site.retry');
Route::post('/site/{slug}/checkout', [CheckoutController::class, 'start'])->middleware('throttle:10,1')->name('public.checkout');
Route::get('/site/{slug}/merci', [CheckoutController::class, 'paid'])->name('public.paid');

Route::get('/dashboard', DashboardController::class)
    ->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/sites/create', [SiteCreationController::class, 'create'])->name('sites.create');
    Route::post('/sites/lookup', [SiteCreationController::class, 'lookup'])->name('sites.lookup');
    Route::post('/sites', [SiteCreationController::class, 'store'])->name('sites.store');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
