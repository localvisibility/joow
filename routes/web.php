<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SiteCreationController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// Accueil : pas de vitrine ici — on envoie vers l'app.
Route::get('/', function () {
    return redirect(Auth::check() ? route('dashboard') : route('login'));
});

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
