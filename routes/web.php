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
// Accueil : site vitrine sur joow.fr, page de génération (publicités) sur app.joow.fr
Route::get('/', function (\Illuminate\Http\Request $request) {
    return \App\Http\Middleware\MarketingHost::isMarketing($request)
        ? app(\App\Http\Controllers\MarketingController::class)->home()
        : app(PublicSiteController::class)->landing();
})->name('home');

// Site vitrine (rendu serveur, référencement) : accessible sur tous les hôtes, canonique sur joow.fr
Route::middleware(\App\Http\Middleware\MarketingHost::class)->name('marketing.')->group(function () {
    Route::get('/decouvrir', [\App\Http\Controllers\MarketingController::class, 'home'])->name('home');
    Route::get('/fonctionnalites', [\App\Http\Controllers\MarketingController::class, 'features'])->name('features');
    Route::get('/fonctionnalites/{slug}', [\App\Http\Controllers\MarketingController::class, 'feature'])->name('feature');
    Route::get('/metiers', [\App\Http\Controllers\MarketingController::class, 'sectors'])->name('sectors');
    Route::get('/site-internet/{key}', [\App\Http\Controllers\MarketingController::class, 'sector'])->name('sector');
    Route::get('/site-internet/{sector}/{city}', [\App\Http\Controllers\MarketingController::class, 'local'])->name('local');
    Route::get('/villes', [\App\Http\Controllers\MarketingController::class, 'cities'])->name('cities');
    Route::get('/villes/{city}', [\App\Http\Controllers\MarketingController::class, 'city'])->name('city');
    Route::get('/tarifs', [\App\Http\Controllers\MarketingController::class, 'pricing'])->name('pricing');
    Route::get('/exemples', [\App\Http\Controllers\MarketingController::class, 'examples'])->name('examples');
    Route::get('/blog', [\App\Http\Controllers\MarketingController::class, 'blog'])->name('blog');
    Route::get('/blog/{slug}', [\App\Http\Controllers\MarketingController::class, 'post'])->name('post');
    Route::get('/questions-frequentes', [\App\Http\Controllers\MarketingController::class, 'faq'])->name('faq');
    Route::get('/a-propos', [\App\Http\Controllers\MarketingController::class, 'about'])->name('about');
    Route::get('/contact', [\App\Http\Controllers\MarketingController::class, 'contact'])->name('contact');
    Route::post('/contact', [\App\Http\Controllers\MarketingController::class, 'sendContact'])->middleware('throttle:5,10')->name('contact.send');
    Route::get('/{page}', [\App\Http\Controllers\MarketingController::class, 'legal'])->where('page', 'mentions-legales|confidentialite|cgv')->name('legal');
    Route::get('/sitemap.xml', [\App\Http\Controllers\MarketingController::class, 'sitemap'])->name('sitemap');
});
Route::get('/recherche', [PublicSiteController::class, 'search'])->middleware('throttle:60,1')->name('public.search');
Route::post('/generer', [PublicSiteController::class, 'generate'])->middleware('throttle:8,1')->name('public.generate');
// Sans fiche Google : mini-questionnaire → même génération
Route::get('/creer', [\App\Http\Controllers\IntakeController::class, 'create'])->name('intake.create');
Route::get('/creer/suggestions', [\App\Http\Controllers\IntakeController::class, 'suggestions'])->middleware('throttle:30,1')->name('intake.suggestions');
Route::post('/creer/photos', [\App\Http\Controllers\IntakeController::class, 'photos'])->middleware('throttle:20,1')->name('intake.photos');
Route::post('/creer', [\App\Http\Controllers\IntakeController::class, 'store'])->middleware('throttle:8,1')->name('intake.store');
// Refonte d'un site existant : l'adresse suffit
Route::post('/refonte', [\App\Http\Controllers\RedesignController::class, 'start'])->middleware('throttle:6,1')->name('redesign.start');
Route::get('/site/{slug}', [PublicSiteController::class, 'show'])->name('public.site');
Route::get('/site/{slug}/status', [PublicSiteController::class, 'status'])->name('public.site.status');
Route::post('/site/{slug}/retry', [PublicSiteController::class, 'retry'])->middleware('throttle:5,1')->name('public.site.retry');
Route::post('/site/{slug}/email', [PublicSiteController::class, 'email'])->middleware('throttle:10,1')->name('public.site.email');
Route::post('/site/{slug}/checkout', [CheckoutController::class, 'start'])->middleware('throttle:10,1')->name('public.checkout');
Route::get('/site/{slug}/merci', [CheckoutController::class, 'paid'])->name('public.paid');

// Journalisation des erreurs JavaScript du front (diagnostic, pas de données personnelles)
Route::post('/js-error', function (\Illuminate\Http\Request $request) {
    $d = $request->validate(['message' => ['required', 'string', 'max:1000'], 'stack' => ['nullable', 'string', 'max:4000'], 'info' => ['nullable', 'string', 'max:200'], 'url' => ['nullable', 'string', 'max:500']]);
    \Illuminate\Support\Facades\Log::warning('JS : '.$d['message'].' ['.($d['info'] ?? '').'] '.($d['url'] ?? ''), ['stack' => $d['stack'] ?? '', 'ua' => substr((string) $request->userAgent(), 0, 160), 'user' => $request->user()?->id]);

    return response()->noContent();
})->middleware('throttle:20,1')->name('js.error');

Route::get('/dashboard', DashboardController::class)
    ->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['auth', 'verified'])->group(function () {
    // Opérateur : dernières lignes du journal applicatif (diagnostic sans accès SSH)
    Route::get('/admin/journal', function (\Illuminate\Http\Request $request) {
        abort_unless($request->user()->isAdmin(), 403);
        $file = storage_path('logs/laravel.log');
        $lines = is_file($file) ? array_slice(file($file, FILE_IGNORE_NEW_LINES), -(int) min(2000, max(50, (int) $request->query('n', 300)))) : ['(journal vide)'];
        $filter = (string) $request->query('q', '');
        if ($filter !== '') {
            $lines = array_values(array_filter($lines, fn ($l) => stripos($l, $filter) !== false));
        }

        return response(implode("\n", $lines), 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    })->name('admin.journal');
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
    Route::get('/modules/{slug}/stripe/connecter', [\App\Http\Controllers\PaymentController::class, 'connect'])->name('modules.stripe.connect');
    Route::get('/modules/{slug}/stripe/retour', [\App\Http\Controllers\PaymentController::class, 'connectReturn'])->name('modules.stripe.return');

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

});

// ─── Éditeur de site "Studio" (édition en place, pages, IA) ───
// Accessible sans compte au créateur du site (session ou lien signé) : on modifie AVANT de payer.
// Le contrôle d'accès est fait par SiteEditorController::ownedSite().
Route::prefix('/sites/{slug}/editeur')->name('sites.editor')->group(function () {
    Route::get('/', [SiteEditorController::class, 'show']);
    Route::get('/preview', [SiteEditorController::class, 'preview'])->name('.preview');
    Route::get('/state', [SiteEditorController::class, 'state'])->name('.state');
    Route::post('/content', [SiteEditorController::class, 'content'])->middleware('throttle:120,1')->name('.content');
    Route::post('/bulk', [SiteEditorController::class, 'bulk'])->middleware('throttle:120,1')->name('.bulk');
    Route::post('/image', [SiteEditorController::class, 'image'])->middleware('throttle:30,1')->name('.image');
    Route::post('/sections', [SiteEditorController::class, 'sections'])->name('.sections');
    Route::post('/style', [SiteEditorController::class, 'style'])->name('.style');
    Route::post('/publish', [SiteEditorController::class, 'publish'])->middleware('throttle:20,1')->name('.publish');
    Route::post('/abonnement', [SiteEditorController::class, 'startSubscription'])->middleware('throttle:5,1')->name('.subscribe');
    Route::post('/chat', [SiteEditorController::class, 'chat'])->middleware('throttle:20,1')->name('.chat');
    Route::post('/revert', [SiteEditorController::class, 'revert'])->middleware('throttle:30,1')->name('.revert');
    Route::post('/claim', [SiteEditorController::class, 'claim'])->middleware('throttle:10,1')->name('.claim');
    Route::post('/module', [SiteEditorController::class, 'module'])->name('.module');
    Route::post('/accent', [SiteEditorController::class, 'accent'])->name('.accent');
    Route::get('/banque', [SiteEditorController::class, 'bank'])->middleware('throttle:40,1')->name('.bank');
    Route::post('/banque/utiliser', [SiteEditorController::class, 'bankUse'])->middleware('throttle:30,1')->name('.bank.use');
    Route::post('/brand', [SiteEditorController::class, 'brand'])->middleware('throttle:30,1')->name('.brand');
    Route::post('/logo-ia', [SiteEditorController::class, 'logoAi'])->middleware('throttle:10,1')->name('.logo');
    Route::post('/regenerer', [SiteEditorController::class, 'regenerate'])->middleware('throttle:5,1')->name('.regenerate');
    Route::post('/google', [SiteEditorController::class, 'attachGoogle'])->middleware('throttle:10,1')->name('.google');
});

// ─── Nom de domaine d'un site (compte requis) ───
Route::middleware(['auth', 'throttle:60,1'])->prefix('/sites/{slug}/domaine')->name('domains.')->group(function () {
    Route::get('/', [\App\Http\Controllers\DomainsController::class, 'state'])->name('state');
    Route::post('/connecter', [\App\Http\Controllers\DomainsController::class, 'connect'])->name('connect');
    Route::post('/verifier', [\App\Http\Controllers\DomainsController::class, 'check'])->name('check');
    Route::delete('/', [\App\Http\Controllers\DomainsController::class, 'remove'])->name('remove');
    Route::get('/recherche', [\App\Http\Controllers\DomainsController::class, 'search'])->name('search');
    Route::post('/commander', [\App\Http\Controllers\DomainsController::class, 'order'])->middleware('throttle:5,1')->name('order');
    Route::post('/demarrer-abonnement', [\App\Http\Controllers\DomainsController::class, 'startSubscription'])->middleware('throttle:5,1')->name('start');
});

// ─── Crédits IA : recharge (compte requis) ───
Route::post('/sites/{slug}/credits/checkout', [\App\Http\Controllers\CreditsController::class, 'checkout'])->middleware(['auth', 'throttle:10,1'])->name('credits.checkout');
Route::get('/sites/{slug}/credits/retour', [\App\Http\Controllers\CreditsController::class, 'return'])->name('credits.return');

// ─── Connexion sans mot de passe (lien signé) & Google ───
Route::get('/emails/desinscription/{user}', [\App\Http\Controllers\EmailPreferencesController::class, 'unsubscribe'])->middleware('signed')->name('emails.unsubscribe');
Route::post('/connexion/lien', [\App\Http\Controllers\Auth\MagicLinkController::class, 'send'])->middleware('throttle:6,1')->name('magic.send');
Route::get('/connexion/lien/{user}', [\App\Http\Controllers\Auth\MagicLinkController::class, 'login'])->middleware('signed')->name('magic.login');
Route::get('/auth/google', [\App\Http\Controllers\Auth\GoogleAuthController::class, 'redirect'])->name('google.redirect');
Route::get('/auth/google/callback', [\App\Http\Controllers\Auth\GoogleAuthController::class, 'callback'])->name('google.callback');

// ─── Liens publics signés (emails / SMS) & retour de paiement ───
Route::get('/r/{reservation}/annuler', [\App\Http\Controllers\PaymentController::class, 'cancelReservation'])->middleware('signed')->name('public.reservation.cancel');
Route::get('/s/{booking}/annuler', [\App\Http\Controllers\PaymentController::class, 'cancelStay'])->middleware('signed')->name('public.stay.cancel');
Route::get('/paiement/retour', [\App\Http\Controllers\PaymentController::class, 'paymentReturn'])->name('public.pay.return');

// Images uploadées depuis l'éditeur (servies aux sites générés, public)
Route::get('/media/{slug}/{file}', [\App\Http\Controllers\MediaController::class, 'show'])
    ->where(['slug' => '[a-z0-9-]+', 'file' => '[A-Za-z0-9._-]+'])->name('media.show');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
