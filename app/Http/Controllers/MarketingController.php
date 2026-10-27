<?php

namespace App\Http\Controllers;

use App\Mail\JoowNotification;
use App\Models\Site;
use App\Services\Content;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/** Site vitrine joow.fr : rendu serveur (Blade) pour le référencement, générateur embarqué. */
class MarketingController extends Controller
{
    private function stats(): array
    {
        return Cache::remember('joow:marketing:stats', now()->addMinutes(30), fn () => [
            'sites'   => max(Site::count(), (int) config('joow.landing.sites_min', 0)),
            'rating'  => round((float) Site::whereNotNull('rating')->where('rating', '>', 0)->avg('rating'), 1) ?: 4.8,
            'sectors' => count(config('sectors', [])),
        ]);
    }

    public function home()
    {
        return view('marketing.pages.home', [
            'stats' => $this->stats(),
            'features' => config('marketing.features'),
            'examples' => config('marketing.examples'),
            'testimonials' => config('marketing.testimonials'),
            'faq' => array_slice(config('marketing.faq'), 0, 6),
            'pricing' => config('marketing.pricing'),
            'sectors' => collect(config('sectors'))->map(fn ($c, $k) => ['key' => $k, 'label' => $c['label'], 'icon' => $c['icon']])->values()->all(),
            'posts' => array_slice(app(Content::class)->posts(), 0, 3),
            'seo' => ['title' => 'Joow — Le site pro de votre métier, créé par IA en 90 secondes', 'description' => 'Tapez le nom de votre établissement : Joow rédige, choisit vos photos, intègre vos avis Google et active réservation, devis et assistant IA. Gratuit jusqu\'à la mise en ligne.'],
        ]);
    }

    public function features()
    {
        return view('marketing.pages.features', [
            'features' => config('marketing.features'),
            'seo' => ['title' => 'Fonctionnalités — Tout ce que fait votre site Joow', 'description' => 'Génération par IA, Studio d\'édition, réservation, devis, assistant IA, chatbot, avis Google, refonte, nom de domaine, référencement local : le détail de chaque fonctionnalité.'],
        ]);
    }

    public function feature(string $slug)
    {
        $f = config("marketing.features.$slug");
        abort_unless($f, 404);
        $others = collect(config('marketing.features'))->except($slug)->take(4)->all();

        return view('marketing.pages.feature', [
            'slug' => $slug, 'f' => $f, 'others' => $others,
            'seo' => ['title' => $f['title'].' — Joow', 'description' => Str::limit($f['summary'], 155)],
        ]);
    }

    public function sectors()
    {
        return view('marketing.pages.sectors', [
            'sectors' => collect(config('sectors'))->map(fn ($c, $k) => $c + ['key' => $k])->values()->all(),
            'seo' => ['title' => 'Un site internet pour chaque métier — 73 modèles pensés par secteur', 'description' => 'Restaurant, plombier, coiffeur, cabinet médical, agence web, boutique… Joow génère un site adapté à votre métier, avec les modules qui comptent pour vos clients.'],
        ]);
    }

    public function sector(string $key)
    {
        $cfg = config("sectors.$key");
        abort_unless($cfg && $key !== 'service', 404);
        $family = $this->family($cfg);
        $services = $this->servicesFromBrief($cfg['brief'] ?? '');
        $related = collect(config('sectors'))->except([$key, 'service'])->filter(fn ($c) => $this->family($c) === $family)->take(6)->map(fn ($c, $k) => $c + ['key' => $k])->all();

        $posts = app(Content::class)->forSector($key, array_keys($related));

        return view('marketing.pages.sector', [
            'key' => $key, 'cfg' => $cfg, 'family' => config("marketing.families.$family"), 'services' => $services, 'related' => $related, 'posts' => $posts,
            'seo' => ['title' => 'Site internet pour '.lcfirst($cfg['label']).' : créé par IA en 90 secondes — Joow', 'description' => 'Créez le site de votre activité ('.lcfirst($cfg['label']).') à partir de votre fiche Google : textes, photos, avis, '.strtolower($cfg['cta']).' en ligne. Gratuit jusqu\'à la mise en ligne.'],
        ]);
    }

    public function pricing()
    {
        return view('marketing.pages.pricing', [
            'pricing' => config('marketing.pricing'), 'faq' => config('marketing.faq'),
            'seo' => ['title' => 'Tarifs — Gratuit jusqu\'à la mise en ligne, puis 39 € HT/mois ou 349 € une fois', 'description' => 'Deux formules, tous les modules inclus : Pro à 39 € HT par mois sans engagement, Liberté à 349 € HT une fois. Création et aperçu gratuits, sans carte bancaire.'],
        ]);
    }

    public function examples()
    {
        return view('marketing.pages.examples', [
            'examples' => config('marketing.examples'), 'sectors' => config('sectors'),
            'seo' => ['title' => 'Exemples de sites générés par Joow', 'description' => 'Restaurant, institut, rénovation, cabinet médical, agence immobilière, hébergement : des sites complets générés par IA à partir d\'une fiche Google.'],
        ]);
    }

    public function blog(Content $content)
    {
        $category = (string) request()->query('categorie', '');
        $posts = $category !== '' ? array_values(array_filter($content->posts(), fn ($p) => $p['category'] === $category)) : $content->posts();
        abort_if($category !== '' && ! $posts, 404);

        return view('marketing.pages.blog', [
            'posts' => $posts, 'categories' => $content->categories(), 'category' => $category,
            'seo' => ['title' => 'Blog — Conseils pour la visibilité des commerces et artisans', 'description' => 'Site internet, fiche Google, avis, réservation en ligne, nom de domaine : des conseils concrets pour être trouvé et choisi par vos clients.'],
        ]);
    }

    public function post(Content $content, string $slug)
    {
        $post = $content->post($slug);
        abort_unless($post, 404);
        $more = $content->related($post);
        $sectors = collect($post['sectors'])->filter(fn ($k) => $k !== 'service' && config("sectors.$k"))->map(fn ($k) => ['key' => $k, 'label' => config("sectors.$k.label")])->values()->all();

        return view('marketing.pages.post', ['post' => $post, 'more' => $more, 'sectors' => $sectors, 'seo' => ['title' => $post['title'].' — Blog Joow', 'description' => $post['description'], 'type' => 'article', 'image' => $post['cover']]]);
    }

    public function faq()
    {
        return view('marketing.pages.faq', ['faq' => config('marketing.faq'), 'seo' => ['title' => 'Questions fréquentes — Joow', 'description' => 'Prix, modules, compétences, contenus, nom de domaine, propriété du site : les réponses aux questions que l\'on nous pose le plus.']]);
    }

    public function about()
    {
        return view('marketing.pages.about', ['stats' => $this->stats(), 'seo' => ['title' => 'À propos de Joow', 'description' => 'Joow rend le site professionnel accessible à chaque commerce, artisan et indépendant : généré par IA à partir de vos informations réelles, modifiable en quelques clics.']]);
    }

    public function contact()
    {
        return view('marketing.pages.contact', ['seo' => ['title' => 'Contact — Joow', 'description' => 'Une question sur Joow, votre site ou votre formule ? Écrivez-nous, nous répondons sous 24 h ouvrées.']]);
    }

    public function sendContact(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'], 'email' => ['required', 'email:rfc', 'max:190'], 'message' => ['required', 'string', 'min:10', 'max:4000'],
            'website' => ['nullable', 'string', 'max:200'], 'hp' => ['prohibited'], // pot de miel
        ]);
        try {
            Mail::to(config('mail.from.address', 'contact@joow.fr'))->send(new JoowNotification('Contact joow.fr : '.$data['name'], [
                'title' => 'Nouveau message depuis joow.fr', 'intro' => $data['message'],
                'rows' => array_values(array_filter([['Nom', $data['name']], ['Email', $data['email']], ($data['website'] ?? null) ? ['Site', $data['website']] : null])),
                'cta' => ['label' => 'Répondre', 'url' => 'mailto:'.$data['email']],
            ]));
        } catch (\Throwable) {
            return back()->withErrors(['message' => 'Envoi impossible pour le moment, réessayez dans un instant.'])->withInput();
        }

        return back()->with('status', 'Merci ! Votre message est bien envoyé, nous revenons vers vous sous 24 h ouvrées.');
    }

    public function legal(string $page)
    {
        abort_unless(in_array($page, ['mentions-legales', 'confidentialite', 'cgv'], true), 404);
        $titles = ['mentions-legales' => 'Mentions légales', 'confidentialite' => 'Politique de confidentialité', 'cgv' => 'Conditions générales de vente'];

        return view('marketing.pages.legal-'.$page, ['seo' => ['title' => $titles[$page].' — Joow', 'description' => $titles[$page].' du service Joow.', 'noindex' => $page !== 'confidentialite']]);
    }

    public function sitemap(Content $content)
    {
        $base = 'https://'.config('marketing.host', 'joow.fr');
        $urls = [['loc' => $base.'/', 'priority' => '1.0', 'freq' => 'weekly']];
        foreach (['marketing.features', 'marketing.sectors', 'marketing.examples', 'marketing.pricing', 'marketing.blog', 'marketing.faq', 'marketing.about', 'marketing.contact'] as $r) {
            $urls[] = ['loc' => $base.route($r, [], false), 'priority' => '0.8', 'freq' => 'weekly'];
        }
        foreach (array_keys(config('marketing.features')) as $f) {
            $urls[] = ['loc' => $base.route('marketing.feature', $f, false), 'priority' => '0.7', 'freq' => 'monthly'];
        }
        foreach (array_keys(config('sectors')) as $k) {
            if ($k !== 'service') {
                $urls[] = ['loc' => $base.route('marketing.sector', $k, false), 'priority' => '0.7', 'freq' => 'monthly'];
            }
        }
        foreach ($content->posts() as $p) {
            $urls[] = ['loc' => $base.route('marketing.post', $p['slug'], false), 'priority' => '0.6', 'freq' => 'monthly', 'lastmod' => $p['date']];
        }
        $urls[] = ['loc' => $base.route('marketing.legal', 'confidentialite', false), 'priority' => '0.2', 'freq' => 'yearly'];

        // Prologue passé en variable : avec short_open_tag actif, un « <?xml » littéral dans un Blade est lu comme du PHP
        return response()->view('marketing.sitemap', ['urls' => $urls, 'xml' => '<'.'?xml version="1.0" encoding="UTF-8"?'.'>'])->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    /** Famille d'argumentaire selon l'ordre des sections du métier. */
    private function family(array $cfg): string
    {
        $order = $cfg['order'] ?? [];
        $first = $order[0] ?? '';
        if ($first === 'menu') {
            return 'food';
        }
        if ($first === 'rooms') {
            return 'stay';
        }
        if (($order[1] ?? '') === 'gallery' && $first === 'services') {
            return 'b2b';
        }
        if ($first === 'about') {
            return 'shop';
        }
        if (($order[1] ?? '') === 'about' && $first === 'services') {
            return 'care';
        }
        if ($first === 'services') {
            return 'trade';
        }

        return 'local';
    }

    /** Prestations typiques extraites du brief métier (« Services = … »). */
    private function servicesFromBrief(string $brief): array
    {
        if (! preg_match('/Services?\s*=\s*([^.]{10,260})/u', $brief, $m)) {
            return [];
        }
        $txt = preg_replace('/\s*\(.*?\)\s*/u', ' ', $m[1]);

        return collect(preg_split('/[,;]| et /u', $txt))->map(fn ($s) => trim($s, " .:·"))->filter(fn ($s) => mb_strlen($s) > 3 && mb_strlen($s) < 60)->take(6)->values()->all();
    }
}
