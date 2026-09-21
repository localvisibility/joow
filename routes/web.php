<?php

use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HostingController;
use App\Http\Controllers\InvoicesController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\LeadsInboxController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicSiteController;
use App\Http\Controllers\SiteCreationController;
use App\Http\Controllers\SiteEditorController;
use Illuminate\Support\Facades\Route;

// ─── Demandes entrantes des sites générés (CORS, public) ───
Route::post('/api/lead/{slug}', [LeadController::class, 'store'])->middleware('throttle:20,1')->name('api.lead');

// ─── API publique des modules (widgets des sites générés, CORS) ───
Route::prefix('/api/site/{slug}')->middleware('throttle:120,1')->group(function () {
    Route::get('/availability', [\App\Http\Controllers\SiteApiController::class, 'availability'])->name('api.site.availability');
    Route::post('/reserve', [\App\Http\Controllers\SiteApiController::class, 'reserve'])->middleware('throttle:15,1')->name('api.site.reserve');
    Route::get('/rooms', [\App\Http\Controllers\SiteApiController::class, 'rooms'])->name('api.site.rooms');
    Route::post('/stay', [\App\Http\Controllers\SiteApiController::class, 'stay'])->middleware('throttle:15,1')->name('api.site.stay');
    Route::post('/bot', [\App\Http\Controllers\SiteApiController::class, 'bot'])->middleware('throttle:30,1')->name('api.site.bot');
    Route::post('/view', [\App\Http\Controllers\SiteApiController::class, 'view'])->name('api.site.view');
});

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

    // Boîte de réception des demandes
    Route::get('/demandes', [LeadsInboxController::class, 'index'])->name('leads.index');
    Route::patch('/demandes/{lead}', [LeadsInboxController::class, 'update'])->name('leads.update');

    // Modules (catalogue + configuration) & domaine
    Route::get('/modules', [\App\Http\Controllers\ModulesController::class, 'index'])->name('modules.index');
    Route::post('/modules/{slug}', [\App\Http\Controllers\ModulesController::class, 'update'])->name('modules.update');
    Route::post('/modules/{slug}/domaine', [\App\Http\Controllers\ModulesController::class, 'domain'])->name('modules.domain');

    // Réservations (tables) & séjours (chambres)
    Route::get('/reservations', [\App\Http\Controllers\ReservationsController::class, 'index'])->name('reservations.index');
    Route::post('/reservations/table', [\App\Http\Controllers\ReservationsController::class, 'storeTable'])->name('reservations.table.store');
    Route::patch('/reservations/table/{reservation}', [\App\Http\Controllers\ReservationsController::class, 'updateTable'])->name('reservations.table.update');
    Route::post('/reservations/sejour', [\App\Http\Controllers\ReservationsController::class, 'storeStay'])->name('reservations.stay.store');
    Route::patch('/reservations/sejour/{booking}', [\App\Http\Controllers\ReservationsController::class, 'updateStay'])->name('reservations.stay.update');

    // Menu / carte (JSON)
    Route::get('/sites/{slug}/menu', [\App\Http\Controllers\MenuController::class, 'index'])->name('menu.index');
    Route::post('/sites/{slug}/menu', [\App\Http\Controllers\MenuController::class, 'store'])->name('menu.store');
    Route::post('/sites/{slug}/menu/import', [\App\Http\Controllers\MenuController::class, 'import'])->name('menu.import');
    Route::patch('/sites/{slug}/menu/{item}', [\App\Http\Controllers\MenuController::class, 'update'])->name('menu.update');
    Route::delete('/sites/{slug}/menu/{item}', [\App\Http\Controllers\MenuController::class, 'destroy'])->name('menu.destroy');

    // Chambres / calendrier (JSON)
    Route::get('/sites/{slug}/chambres', [\App\Http\Controllers\RoomsController::class, 'index'])->name('rooms.index');
    Route::post('/sites/{slug}/chambres', [\App\Http\Controllers\RoomsController::class, 'store'])->name('rooms.store');
    Route::patch('/sites/{slug}/chambres/{room}', [\App\Http\Controllers\RoomsController::class, 'update'])->name('rooms.update');
    Route::delete('/sites/{slug}/chambres/{room}', [\App\Http\Controllers\RoomsController::class, 'destroy'])->name('rooms.destroy');
    Route::post('/sites/{slug}/chambres/blocage', [\App\Http\Controllers\RoomsController::class, 'block'])->name('rooms.block');
    Route::delete('/sites/{slug}/chambres/blocage/{block}', [\App\Http\Controllers\RoomsController::class, 'unblock'])->name('rooms.unblock');
    Route::post('/sites/{slug}/chambres/sync', [\App\Http\Controllers\RoomsController::class, 'sync'])->middleware('throttle:10,1')->name('rooms.sync');

    // Statistiques
    Route::get('/statistiques', [\App\Http\Controllers\StatsController::class, 'index'])->name('stats.index');

    // Hébergement & formules
    Route::get('/hebergement', [HostingController::class, 'index'])->name('hosting.index');

    // Factures
    Route::get('/factures', [InvoicesController::class, 'index'])->name('invoices.index');
    Route::get('/factures/{invoice}', [InvoicesController::class, 'show'])->name('invoices.show');

    // Éditeur de site "Studio" (édition en place + IA)
    Route::get('/sites/{slug}/editeur', [SiteEditorController::class, 'show'])->name('sites.editor');
    Route::get('/sites/{slug}/editeur/preview', [SiteEditorController::class, 'preview'])->name('sites.editor.preview');
    Route::get('/sites/{slug}/editeur/state', [SiteEditorController::class, 'state'])->name('sites.editor.state');
    Route::post('/sites/{slug}/editeur/content', [SiteEditorController::class, 'content'])->middleware('throttle:120,1')->name('sites.editor.content');
    Route::post('/sites/{slug}/editeur/bulk', [SiteEditorController::class, 'bulk'])->middleware('throttle:120,1')->name('sites.editor.bulk');
    Route::post('/sites/{slug}/editeur/image', [SiteEditorController::class, 'image'])->middleware('throttle:30,1')->name('sites.editor.image');
    Route::post('/sites/{slug}/editeur/sections', [SiteEditorController::class, 'sections'])->name('sites.editor.sections');
    Route::post('/sites/{slug}/editeur/style', [SiteEditorController::class, 'style'])->name('sites.editor.style');
    Route::post('/sites/{slug}/editeur/publish', [SiteEditorController::class, 'publish'])->middleware('throttle:20,1')->name('sites.editor.publish');
    Route::post('/sites/{slug}/editeur/chat', [SiteEditorController::class, 'chat'])->middleware('throttle:20,1')->name('sites.editor.chat');
    Route::post('/sites/{slug}/editeur/module', [SiteEditorController::class, 'module'])->name('sites.editor.module');
    Route::post('/sites/{slug}/editeur/accent', [SiteEditorController::class, 'accent'])->name('sites.editor.accent');
    Route::post('/sites/{slug}/editeur/regenerer', [SiteEditorController::class, 'regenerate'])->middleware('throttle:5,1')->name('sites.editor.regenerate');
});

// Images uploadées depuis l'éditeur (servies aux sites générés, public)
Route::get('/media/{slug}/{file}', [\App\Http\Controllers\MediaController::class, 'show'])
    ->where(['slug' => '[a-z0-9-]+', 'file' => '[A-Za-z0-9._-]+'])->name('media.show');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
