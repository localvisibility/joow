@php
    use Illuminate\Support\Str;
    $images   = $images ?? [];
    $booking  = $booking ?? [];
    $editMode = $editMode ?? false;
    $font     = $font ?? null;
    // ── Système de design sectoriel ──
    $design    = $design ?? [];
    $theme     = ($design['theme'] ?? 'light') === 'dark' ? 'dark' : 'light';
    $heroStyle = in_array($design['hero'] ?? '', ['editorial', 'split', 'center'], true) ? $design['hero'] : 'editorial';
    $stock     = array_values(array_filter($design['stock'] ?? []));
    // ── Photos : celles de la fiche Google (déjà triées paysage/HD), complétées par la banque sectorielle ──
    $photos   = array_values(array_filter($b['photos'] ?? []));
    $pool     = array_values(array_unique(array_merge($photos, $stock)));
    $hero     = $images['hero'] ?? ($pool[0] ?? 'https://images.unsplash.com/photo-1497366216548-37526070297c?w=1920&q=80');
    $second   = $images['about'] ?? ($pool[1] ?? $hero);
    $gallery  = array_values(array_filter($images['gallery'] ?? array_slice($pool, 2, 6)));
    // Miniature floutée (LQIP) dérivée de l'URL pour un chargement flou → net
    $lqip = function (string $u): ?string {
        if (str_contains($u, 'maxwidth=1600')) return str_replace('maxwidth=1600', 'maxwidth=48', $u);
        if (str_contains($u, 'unsplash.com') && preg_match('/[?&]w=\d+/', $u)) return preg_replace('/([?&])w=\d+/', '$1w=40', $u);
        return null;
    };
    $phoneHref = !empty($b['phone']) ? 'tel:'.preg_replace('/\s/', '', $b['phone']) : '';
    $services = $c['services'] ?? [];
    $faq      = $c['faq'] ?? [];
    $badges   = $c['badges'] ?? [];
    $stats    = $c['stats'] ?? [];
    $hours    = $b['opening_hours'] ?? [];
    $reviews  = collect($b['reviews'] ?? [])->filter(fn($r) => strlen($r['text'] ?? '') > 20)->take(6)->values();
    $tagline  = $c['tagline'] ?? $label.' · '.($b['city'] ?? 'France');
    $stars = fn($n) => str_repeat('★', max(0, (int) round($n)));
    $initial = fn($name) => Str::upper(Str::substr(trim($name) ?: 'C', 0, 1));
    $t = fn($k, $d) => (isset($c[$k]) && $c[$k] !== '') ? $c[$k] : $d;
    $ctaLabel = $t('cta_label', $cta);

    // Sections : visibilité + ordre (flex order)
    $secCfg = $c['sections'] ?? [];
    $defaultOrder = ['services', 'process', 'menu', 'rooms', 'gallery', 'about', 'reviews', 'faq', 'booking', 'cta', 'contact'];

    // Icône par service (mots-clés) — repli sur l'icône du secteur
    $svcIcon = function (string $name) use ($icon): string {
        $n = mb_strtolower($name);
        $map = ['livraison' => 'fa-truck', 'mariage' => 'fa-ring', 'brunch' => 'fa-mug-hot', 'dégustation' => 'fa-utensils', 'menu' => 'fa-utensils', 'vin' => 'fa-wine-glass', 'cave' => 'fa-wine-glass', 'privatis' => 'fa-key', 'traiteur' => 'fa-cake-candles', 'terrasse' => 'fa-umbrella-beach',
            'coupe' => 'fa-scissors', 'coiff' => 'fa-scissors', 'couleur' => 'fa-palette', 'balayage' => 'fa-palette', 'lissage' => 'fa-wind', 'soin' => 'fa-spa', 'ongle' => 'fa-hand-sparkles', 'massage' => 'fa-hands', 'épilation' => 'fa-feather',
            'isolation' => 'fa-temperature-low', 'peinture' => 'fa-paint-roller', 'cuisine' => 'fa-kitchen-set', 'salle de bain' => 'fa-bath', 'électri' => 'fa-bolt', 'plomb' => 'fa-faucet', 'toit' => 'fa-house-chimney', 'extension' => 'fa-up-right-and-down-left-from-center', 'combles' => 'fa-up-right-and-down-left-from-center', 'rénovation' => 'fa-hammer', 'chauffage' => 'fa-fire', 'clim' => 'fa-snowflake', 'fenêtre' => 'fa-window-maximize', 'menuiserie' => 'fa-ruler-combined', 'carrel' => 'fa-border-all',
            'estimation' => 'fa-tag', 'vente' => 'fa-handshake', 'achat' => 'fa-key', 'recherche' => 'fa-magnifying-glass', 'location' => 'fa-file-signature', 'gestion' => 'fa-file-signature', 'investis' => 'fa-chart-line', 'home staging' => 'fa-couch',
            'urgence' => 'fa-bolt', 'dépannage' => 'fa-bolt', 'entretien' => 'fa-oil-can', 'révision' => 'fa-oil-can', 'pneu' => 'fa-circle-dot', 'frein' => 'fa-circle-dot', 'diagnostic' => 'fa-stethoscope', 'carrosserie' => 'fa-car-burst', 'contrôle technique' => 'fa-clipboard-check',
            'consultation' => 'fa-user-doctor', 'enfant' => 'fa-baby', 'nourrisson' => 'fa-baby', 'pédiatr' => 'fa-baby', 'sport' => 'fa-person-running', 'enceinte' => 'fa-person-pregnant', 'grossesse' => 'fa-person-pregnant', 'digest' => 'fa-hand-holding-medical', 'suivi' => 'fa-notes-medical', 'dos' => 'fa-bone',
            'chambre' => 'fa-bed', 'suite' => 'fa-bed', 'petit-déj' => 'fa-mug-saucer', 'spa' => 'fa-spa', 'piscine' => 'fa-water-ladder', 'événement' => 'fa-champagne-glasses', 'séminaire' => 'fa-people-group', 'conciergerie' => 'fa-bell-concierge', 'parking' => 'fa-square-parking',
            'formation' => 'fa-chalkboard-user', 'coaching' => 'fa-user-tie', 'stratégie' => 'fa-chess', 'marketing' => 'fa-bullhorn', 'organisation' => 'fa-sitemap', 'rh' => 'fa-people-group',
            'divorce' => 'fa-people-roof', 'famille' => 'fa-people-roof', 'travail' => 'fa-briefcase', 'pénal' => 'fa-gavel', 'immobilier' => 'fa-building', 'succession' => 'fa-scroll', 'contrat' => 'fa-file-contract',
            'création' => 'fa-rocket', 'paie' => 'fa-users', 'bilan' => 'fa-book', 'compta' => 'fa-book', 'fiscal' => 'fa-percent', 'juridique' => 'fa-scale-balanced', 'assurance' => 'fa-shield-halved', 'devis' => 'fa-file-invoice', 'conseil' => 'fa-lightbulb', 'garantie' => 'fa-shield-halved'];
        foreach ($map as $k => $ic) { if (str_contains($n, $k)) return $ic; }
        return $icon;
    };

    // "Comment ça se passe" : 3 étapes (IA) ou repli générique
    $process = array_values(array_filter($c['process'] ?? [], fn($p) => is_array($p) && !empty($p['title'])));
    if (count($process) < 2) {
        $process = match (true) {
            in_array($sector, ['restaurant'], true) => [['title' => 'Vous réservez', 'desc' => 'En ligne ou par téléphone, en 30 secondes.'], ['title' => 'On prépare votre table', 'desc' => 'Vos préférences sont notées, votre place vous attend.'], ['title' => 'Vous savourez', 'desc' => 'Le reste, c\'est notre métier.']],
            in_array($sector, ['hebergement'], true) => [['title' => 'Vous choisissez vos dates', 'desc' => 'Disponibilités en direct, réponse rapide.'], ['title' => 'On confirme votre séjour', 'desc' => 'Tout est prêt avant votre arrivée.'], ['title' => 'Vous profitez', 'desc' => 'Un accueil aux petits soins, du premier au dernier jour.']],
            in_array($sector, ['sante', 'bienetre', 'beaute'], true) => [['title' => 'Vous prenez rendez-vous', 'desc' => 'En ligne ou par téléphone, selon vos disponibilités.'], ['title' => 'On vous écoute', 'desc' => 'Un diagnostic personnalisé, sans précipitation.'], ['title' => 'On prend soin de vous', 'desc' => 'Des gestes experts et un suivi attentif.']],
            default => [['title' => 'Vous nous contactez', 'desc' => 'Un premier échange gratuit pour comprendre votre besoin.'], ['title' => 'On vous propose une solution', 'desc' => 'Un devis clair et détaillé, sans surprise.'], ['title' => 'On s\'occupe de tout', 'desc' => 'Travail soigné, délais tenus, suivi jusqu\'au bout.']],
        };
    }
    $order = array_values(array_unique(array_merge(array_values($secCfg['order'] ?? []), $defaultOrder)));
    $hidden = $secCfg['hidden'] ?? [];
    $show = fn($k) => empty($hidden[$k]);
    $ord  = fn($k) => (($i = array_search($k, $order, true)) === false ? 99 : $i + 1);

    // ── Modules (catalogue Joow) : état résolu {enabled, ...config} ──
    $modules   = $modules ?? [];
    $menuItems = $menuItems ?? collect();
    $rooms     = $rooms ?? collect();
    $legalHtml = $legalHtml ?? null;
    $mod = fn($k) => is_array($modules[$k] ?? null) ? $modules[$k] : ['enabled' => (bool) ($modules[$k] ?? false)];
    $on  = fn($k) => (bool) ($mod($k)['enabled'] ?? false);
    $restaurantOn = $on('restaurant');
    $zcCfg = $mod('zenchef');   $zcOn   = $on('zenchef') && !empty($zcCfg['restaurant_id']);
    $roomsOn = $on('rooms') && $rooms->count() > 0;
    $menuCfg = $mod('menu');    $menuOn = $on('menu') && $menuItems->count() > 0;
    $waCfg = $mod('whatsapp');  $waOn   = $on('whatsapp') && !empty($waCfg['number']);
    $botCfg = $mod('bot');      $botOn  = $on('bot');
    $legalOn = $on('legal') && $legalHtml;
    $rvCfg = $mod('reviews');   $reviewsOn = array_key_exists('reviews', $modules) ? $on('reviews') : true;
    $reviews = $reviewsOn
        ? $reviews->filter(fn($r) => (int) ($r['rating'] ?? 5) >= (int) ($rvCfg['min_rating'] ?? 4))->take(max(1, (int) ($rvCfg['count'] ?? 6)))->values()
        : collect();
    $rsCfg = $mod('restaurant');
    // Formulaire générique (réservation/RDV/devis) : remplacé par le module restaurant ou ZenChef s'ils sont actifs
    $bookingOn = (array_key_exists('booking', $modules) ? $on('booking') : true) && $show('booking') && !$restaurantOn && !$zcOn;
    $autoType = in_array($sector, ['restaurant', 'hebergement'], true) ? 'reservation'
        : (in_array($sector, ['sante', 'beaute', 'bienetre'], true) ? 'rdv' : 'devis');
    $bt = in_array($booking['type'] ?? '', ['reservation', 'rdv', 'devis'], true) ? $booking['type'] : $autoType;
    $resaTitle = $sector === 'restaurant' ? 'Réserver votre table' : 'Réserver votre séjour';
    $partyLabel = $sector === 'restaurant' ? 'Couverts' : 'Personnes';
    $bookDefaults = [
        'reservation' => ['title' => $resaTitle, 'sub' => 'Une demande, une réponse rapide.'],
        'rdv'         => ['title' => 'Prendre rendez-vous', 'sub' => 'Indiquez vos disponibilités, on vous recontacte.'],
        'devis'       => ['title' => 'Demander un devis', 'sub' => 'Décrivez votre besoin, réponse sous 48h.'],
    ][$bt];
    $bk = fn($k, $d) => (isset($booking[$k]) && $booking[$k] !== '') ? $booking[$k] : $d;
    $bookTitle = $bk('title', $bookDefaults['title']);
    $bookSub   = $bk('sub', $bookDefaults['sub']);
    $bookCta   = $bk('cta', $ctaLabel);
    $ctaHref = ($bookingOn || $restaurantOn || $zcOn) ? '#reserver' : ($roomsOn ? '#sejour' : '#contact');
    $apiBase = 'https://app.joow.fr'; // domaine fixe de l'app (réception des demandes)

    // Police d'affichage (Google Fonts)
    $fontsAllowed = ['Space Grotesk', 'Playfair Display', 'DM Serif Display', 'Sora', 'Poppins', 'Montserrat', 'Cormorant Garamond'];
    $sectorFont  = in_array($design['font'] ?? '', $fontsAllowed, true) ? $design['font'] : 'Space Grotesk';
    $displayFont = in_array($font, $fontsAllowed, true) ? $font : $sectorFont;
    $serif = in_array($displayFont, ['Playfair Display', 'DM Serif Display', 'Cormorant Garamond'], true);
    $fontParam = str_replace(' ', '+', $displayFont).':wght@'.($serif ? '400;500;600;700' : '500;600;700');

    // Stats par défaut si l'IA n'en fournit pas
    if (!count($stats)) {
        $stats = array_values(array_filter([
            !empty($b['rating']) ? ['v' => $b['rating'].'/5', 'l' => 'Note Google'] : null,
            !empty($b['reviews_count']) ? ['v' => $b['reviews_count'].'+', 'l' => 'Avis clients'] : null,
            !empty($b['city']) ? ['v' => $b['city'], 'l' => "Zone d'intervention"] : null,
            ['v' => '100%', 'l' => 'Clients satisfaits'],
        ]));
        $stats = array_slice($stats, 0, 3);
    }
@endphp
<!DOCTYPE html>
<html lang="fr" class="scroll-smooth theme-{{ $theme }}{{ $editMode ? ' joow-edit' : '' }}">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>{{ $t('hero_title', $b['name'] ?? '') }} — {{ $b['name'] ?? '' }}{{ !empty($b['city']) ? ', '.$b['city'] : '' }}</title>
<meta name="description" content="{{ $t('hero_subtitle', $label) }} — {{ $b['name'] ?? '' }}{{ !empty($b['city']) ? ', '.$b['city'] : '' }}. {{ $c['cta_text'] ?? '' }}">
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family={{ $fontParam }}&display=swap" rel="stylesheet">
<style>
:root{
  --c:{{ $color }};
  --c-d:color-mix(in srgb, {{ $color }} 62%, #000);
  --c-l:color-mix(in srgb, {{ $color }} 12%, #fff);
  --grad:linear-gradient(135deg, {{ $color }} 0%, color-mix(in srgb, {{ $color }} 55%, #7c3aed) 100%);
}
*{font-family:'Plus Jakarta Sans',sans-serif}
/* Aucun débordement horizontal (carrousels) : évite l'élargissement du viewport mobile et garde les éléments fixed calés sur l'écran. */
html,body{overflow-x:clip;max-width:100%}
.font-display{font-family:'{{ $displayFont }}',sans-serif;letter-spacing:-.01em}
.accent{color:var(--c)}.bg-accent{background:var(--c)}.bg-grad{background:var(--grad)}
.text-grad{background:var(--grad);-webkit-background-clip:text;background-clip:text;color:transparent}
.border-accent{border-color:var(--c)}
.ring-accent{--tw-ring-color:color-mix(in srgb,var(--c) 40%,transparent)}
.shadow-c{box-shadow:0 20px 45px -20px color-mix(in srgb,var(--c) 70%,transparent)}
.hero-ov{background:linear-gradient(115deg,rgba(7,8,14,.94) 0%,rgba(7,8,14,.66) 42%,rgba(7,8,14,.30) 100%)}
.grain{background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='120' height='120'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='.9' numOctaves='2'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='.35'/%3E%3C/svg%3E");mix-blend-mode:overlay;opacity:.5}
.reveal{opacity:0;transform:translateY(28px);transition:opacity .8s cubic-bezier(.22,1,.36,1),transform .8s cubic-bezier(.22,1,.36,1)}
.reveal.on{opacity:1;transform:none}
@keyframes floaty{0%,100%{transform:translateY(0)}50%{transform:translateY(-10px)}}
.floaty{animation:floaty 5s ease-in-out infinite}
@keyframes marq{to{transform:translateX(-50%)}}
.marq{animation:marq 28s linear infinite}
.card-hover{transition:transform .35s cubic-bezier(.22,1,.36,1),box-shadow .35s}
.card-hover:hover{transform:translateY(-6px)}
::selection{background:var(--c);color:#fff}
html{scroll-padding-top:80px}
main{display:flex;flex-direction:column}
/* ── Première impression ── */
@keyframes kb{from{transform:scale(1.12)}to{transform:scale(1)}}
.kb{animation:kb 9s cubic-bezier(.22,1,.36,1) both}
.lqip{background-size:cover;background-position:center;filter:blur(18px);transform:scale(1.1)}
.hero-img{opacity:0;transition:opacity .9s ease}.hero-img.ready{opacity:1}
@keyframes rise{from{opacity:0;transform:translateY(22px)}to{opacity:1;transform:none}}
.stagger>*{animation:rise .85s cubic-bezier(.22,1,.36,1) both}
.stagger>*:nth-child(1){animation-delay:.15s}.stagger>*:nth-child(2){animation-delay:.3s}.stagger>*:nth-child(3){animation-delay:.45s}.stagger>*:nth-child(4){animation-delay:.6s}.stagger>*:nth-child(5){animation-delay:.75s}.stagger>*:nth-child(6){animation-delay:.9s}.stagger>*:nth-child(7){animation-delay:1.05s}
.shine{position:relative;overflow:hidden}.shine::after{content:"";position:absolute;inset:0;background:linear-gradient(115deg,transparent 30%,rgba(255,255,255,.35) 50%,transparent 70%);transform:translateX(-120%);transition:transform .7s}.shine:hover::after{transform:translateX(120%)}
.open-pill{display:inline-flex;align-items:center;gap:.5rem;border-radius:9999px;padding:.35rem .8rem;font-size:.8rem;font-weight:600;background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.2);backdrop-filter:blur(8px)}
.open-dot{width:.5rem;height:.5rem;border-radius:9999px;background:#34d399;box-shadow:0 0 0 0 rgba(52,211,153,.6);animation:pulse 2s infinite}
.open-dot.off{background:#f87171;animation:none}
@keyframes pulse{0%{box-shadow:0 0 0 0 rgba(52,211,153,.55)}70%{box-shadow:0 0 0 9px rgba(52,211,153,0)}100%{box-shadow:0 0 0 0 rgba(52,211,153,0)}}
#joow-curtain{position:fixed;inset:0;z-index:100;display:grid;place-items:center;background:#07080e;transition:opacity .6s ease,visibility .6s}
#joow-curtain.gone{opacity:0;visibility:hidden}
#joow-curtain span{display:grid;place-items:center;width:64px;height:64px;border-radius:18px;color:#fff;font-size:1.6rem;background:var(--grad);animation:rise .6s both}
.glow{pointer-events:none;position:absolute;inset:0;background:radial-gradient(520px circle at var(--mx,50%) var(--my,40%),color-mix(in srgb,var(--c) 28%,transparent),transparent 60%);opacity:.9}
.hero-photo{border-radius:2rem;box-shadow:0 40px 80px -30px rgba(0,0,0,.7)}
@media (prefers-reduced-motion:reduce){.kb,.stagger>*,#joow-curtain span{animation:none}.hero-img{opacity:1}}
/* ── Bandeau de confiance défilant ── */
.trust{border-top:1px solid rgba(15,23,42,.06);border-bottom:1px solid rgba(15,23,42,.06);overflow:hidden;background:#fff}
.trust-track{display:flex;width:max-content;gap:2.5rem;padding:.9rem 0;animation:marq 32s linear infinite;white-space:nowrap}
.trust:hover .trust-track{animation-play-state:paused}
.trust-item{display:inline-flex;align-items:center;gap:.5rem;font-size:.85rem;font-weight:600;color:#475569}
.trust-item i{color:var(--c)}
.trust-item .g{display:inline-grid;place-items:center;width:1.25rem;height:1.25rem;border-radius:9999px;background:#fff;box-shadow:0 0 0 1px #e2e8f0;font-weight:800;font-size:.7rem;color:#4285f4}
/* ── Services bento ── */
.bento{display:grid;gap:1.25rem;grid-template-columns:repeat(1,minmax(0,1fr))}
@media(min-width:768px){.bento{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(min-width:1024px){.bento{grid-template-columns:repeat(3,minmax(0,1fr));grid-auto-rows:minmax(200px,auto)}.bento .feat{grid-column:span 2;grid-row:span 2}}
.svc{position:relative;overflow:hidden;border-radius:1.5rem;border:1px solid #f1f5f9;background:#fff;padding:1.75rem;transition:transform .35s cubic-bezier(.22,1,.36,1),box-shadow .35s,border-color .35s;transform-style:preserve-3d}
.svc:hover{box-shadow:0 30px 60px -28px rgba(15,23,42,.35);border-color:color-mix(in srgb,var(--c) 35%,#f1f5f9)}
.svc::before{content:"";position:absolute;inset:0;background:radial-gradient(420px circle at var(--px,50%) var(--py,50%),color-mix(in srgb,var(--c) 10%,transparent),transparent 60%);opacity:0;transition:opacity .35s;pointer-events:none}
.svc:hover::before{opacity:1}
.svc-ic{display:grid;place-items:center;width:3.25rem;height:3.25rem;border-radius:1rem;background:var(--grad);color:#fff;font-size:1.15rem;box-shadow:0 14px 30px -14px color-mix(in srgb,var(--c) 70%,transparent);transition:transform .35s}
.svc:hover .svc-ic{transform:scale(1.08) rotate(-4deg)}
.svc-num{position:absolute;right:1.25rem;top:1rem;font-family:'{{ $displayFont }}',sans-serif;font-size:3rem;font-weight:700;color:#f1f5f9;line-height:1;transition:color .35s}
.svc:hover .svc-num{color:color-mix(in srgb,var(--c) 18%,#f1f5f9)}
.svc.feat{min-height:26rem;color:#fff;border:0;display:flex;flex-direction:column;justify-content:flex-end}
.svc.feat img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;transition:transform 1.2s cubic-bezier(.22,1,.36,1)}
.svc.feat:hover img{transform:scale(1.06)}
.svc.feat .ov{position:absolute;inset:0;background:linear-gradient(180deg,rgba(7,8,14,.05) 0%,rgba(7,8,14,.55) 55%,rgba(7,8,14,.9) 100%)}
.svc.feat>*:not(img):not(.ov){position:relative}
.svc.feat .svc-tag{display:inline-flex;align-items:center;gap:.4rem;border-radius:9999px;background:rgba(255,255,255,.14);backdrop-filter:blur(8px);border:1px solid rgba(255,255,255,.25);padding:.35rem .8rem;font-size:.75rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase}
/* ── Parcours (timeline) ── */
.steps{position:relative;display:grid;gap:1.5rem}
@media(min-width:768px){.steps{grid-template-columns:repeat(3,minmax(0,1fr))}.steps::before{content:"";position:absolute;left:12%;right:12%;top:2.1rem;height:2px;background:linear-gradient(90deg,transparent,color-mix(in srgb,var(--c) 45%,transparent),transparent)}}
.step{position:relative;text-align:center;padding:0 .75rem}
.step-n{position:relative;z-index:1;margin:0 auto 1.1rem;display:grid;place-items:center;width:4.2rem;height:4.2rem;border-radius:9999px;background:var(--grad);color:#fff;font-family:'{{ $displayFont }}',sans-serif;font-size:1.5rem;font-weight:700;box-shadow:0 0 0 8px color-mix(in srgb,var(--c) 12%,transparent),0 20px 40px -18px color-mix(in srgb,var(--c) 75%,transparent)}
/* ── Avis : note + carrousel ── */
.rating-big{display:flex;flex-wrap:wrap;align-items:center;justify-content:center;gap:1.5rem}
.rating-num{font-family:'{{ $displayFont }}',sans-serif;font-size:4.5rem;font-weight:700;line-height:1}
.rev-mask{overflow:hidden;-webkit-mask-image:linear-gradient(90deg,transparent,#000 8%,#000 92%,transparent);mask-image:linear-gradient(90deg,transparent,#000 8%,#000 92%,transparent)}
.rev-track{display:flex;width:max-content;gap:1.25rem}
.rev-left{animation:marq 48s linear infinite}.rev-right{animation:marqr 52s linear infinite}
@keyframes marqr{from{transform:translateX(-50%)}to{transform:translateX(0)}}
.rev-mask:hover .rev-track{animation-play-state:paused}
.rev{width:22rem;flex-shrink:0;border-radius:1.5rem;border:1px solid #f1f5f9;background:#fff;padding:1.5rem;box-shadow:0 10px 30px -22px rgba(15,23,42,.35);position:relative}
.rev .q{position:absolute;right:1.25rem;top:.9rem;font-family:Georgia,serif;font-size:3.5rem;line-height:1;color:color-mix(in srgb,var(--c) 22%,#fff)}
/* ── Lightbox ── */
#joow-lb{position:fixed;inset:0;z-index:90;display:none;align-items:center;justify-content:center;background:rgba(3,6,23,.92);backdrop-filter:blur(6px)}
#joow-lb.open{display:flex}
#joow-lb img{max-width:92vw;max-height:86vh;border-radius:1rem;box-shadow:0 40px 80px -30px rgba(0,0,0,.8);animation:rise .35s both}
#joow-lb button{position:absolute;display:grid;place-items:center;width:3rem;height:3rem;border-radius:9999px;background:rgba(255,255,255,.12);color:#fff;border:1px solid rgba(255,255,255,.2);cursor:pointer;font-size:1.1rem}
#joow-lb .lb-x{top:1.25rem;right:1.25rem}#joow-lb .lb-p{left:1.25rem;top:50%;transform:translateY(-50%)}#joow-lb .lb-n{right:1.25rem;top:50%;transform:translateY(-50%)}
[data-lb]{cursor:zoom-in}
/* ── Bande d'appel à l'action ── */
.cta-band{position:relative;overflow:hidden;border-radius:2rem;background:#0b0b10;color:#fff;padding:3.5rem 2rem;text-align:center;isolation:isolate}
.cta-band::before{content:"";position:absolute;inset:-40%;background:conic-gradient(from 180deg at 50% 50%,color-mix(in srgb,var(--c) 55%,transparent),transparent 30%,color-mix(in srgb,var(--c) 40%,#7c3aed) 60%,transparent 80%,color-mix(in srgb,var(--c) 55%,transparent));filter:blur(60px);opacity:.55;animation:spin 18s linear infinite;z-index:-1}
@keyframes spin{to{transform:rotate(1turn)}}
/* ── Cascade & relief ── */
.stagger-grid>.reveal:nth-child(2){transition-delay:.08s}.stagger-grid>.reveal:nth-child(3){transition-delay:.16s}.stagger-grid>.reveal:nth-child(4){transition-delay:.24s}.stagger-grid>.reveal:nth-child(5){transition-delay:.32s}.stagger-grid>.reveal:nth-child(6){transition-delay:.4s}
.tilt{will-change:transform}
/* ── Pied de page ── */
.foot-grid{display:grid;gap:2.5rem}
@media(min-width:768px){.foot-grid{grid-template-columns:1.4fr 1fr 1fr}}
.foot-link{display:block;padding:.3rem 0;color:#94a3b8;transition:color .2s}.foot-link:hover{color:#fff}
.theme-dark .trust{background:#0e0e15;border-color:rgba(255,255,255,.07)}.theme-dark .trust-item{color:#cbd5e1}
.theme-dark .svc{background:#12121a;border-color:rgba(255,255,255,.08)}.theme-dark .svc-num{color:rgba(255,255,255,.06)}
.theme-dark .rev{background:#12121a;border-color:rgba(255,255,255,.08)}
/* ── Formulaire multi-étapes ── */
.jw-progress{display:flex;gap:.5rem}
.jw-pstep{flex:1;display:flex;align-items:center;gap:.5rem;min-width:0;opacity:.45;transition:opacity .3s}
.jw-pstep.active{opacity:1}
.jw-pnum{display:grid;place-items:center;width:1.6rem;height:1.6rem;border-radius:9999px;font-size:.75rem;font-weight:800;background:#e2e8f0;color:#334155;flex-shrink:0;transition:.3s}
.jw-pstep.active .jw-pnum{background:var(--grad);color:#fff;box-shadow:0 8px 20px -8px color-mix(in srgb,var(--c) 70%,transparent)}
.jw-pstep.done .jw-pnum{background:var(--c);color:#fff}
.jw-ptitle{font-size:.78rem;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.jw-bar{height:4px;border-radius:9999px;background:#e2e8f0;margin-top:.9rem;overflow:hidden}
.jw-bar-fill{height:100%;background:var(--grad);border-radius:9999px;transition:width .5s cubic-bezier(.22,1,.36,1)}
.jw-step{display:none}.jw-step.active{display:block;animation:rise .5s cubic-bezier(.22,1,.36,1) both}
.jw-h{font-family:'{{ $displayFont }}',sans-serif;font-size:1.35rem;font-weight:700;margin-bottom:1.1rem;display:flex;align-items:center;gap:.6rem}
.jw-label{display:block;font-size:.85rem;font-weight:700;color:#334155;margin-bottom:.55rem}
.jw-input{width:100%;border-radius:.85rem;border:1.5px solid #e2e8f0;padding:.8rem 1rem;font-size:.95rem;outline:none;background:#fff;transition:border-color .2s,box-shadow .2s}
.jw-input:focus{border-color:var(--c);box-shadow:0 0 0 4px color-mix(in srgb,var(--c) 18%,transparent)}
.jw-cards{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.7rem}
@media(min-width:640px){.jw-cards{grid-template-columns:repeat(3,minmax(0,1fr))}}
.jw-card{display:flex;flex-direction:column;align-items:flex-start;gap:.25rem;text-align:left;padding:.9rem;border-radius:1rem;border:1.5px solid #e2e8f0;background:#fff;cursor:pointer;transition:transform .2s,border-color .2s,box-shadow .2s,background .2s}
.jw-card i{display:grid;place-items:center;width:2.2rem;height:2.2rem;border-radius:.7rem;background:color-mix(in srgb,var(--c) 12%,#fff);color:var(--c);margin-bottom:.35rem;transition:.2s}
.jw-card:hover{transform:translateY(-2px);border-color:color-mix(in srgb,var(--c) 45%,#e2e8f0);box-shadow:0 14px 30px -18px rgba(15,23,42,.35)}
.jw-card.sel{border-color:var(--c);background:color-mix(in srgb,var(--c) 7%,#fff);box-shadow:0 0 0 3px color-mix(in srgb,var(--c) 18%,transparent)}
.jw-card.sel i{background:var(--grad);color:#fff}
.jw-card-l{font-weight:700;font-size:.92rem;line-height:1.2}.jw-card-d{font-size:.75rem;color:#64748b;line-height:1.3}
.jw-chips,.jw-toggle{display:flex;flex-wrap:wrap;gap:.5rem}
.jw-chip,.jw-tg{padding:.55rem .95rem;border-radius:9999px;border:1.5px solid #e2e8f0;background:#fff;font-size:.88rem;font-weight:600;color:#334155;cursor:pointer;transition:.2s}
.jw-chip:hover,.jw-tg:hover{border-color:color-mix(in srgb,var(--c) 45%,#e2e8f0)}
.jw-chip.sel,.jw-tg.sel{background:var(--grad);border-color:transparent;color:#fff;box-shadow:0 10px 24px -12px color-mix(in srgb,var(--c) 70%,transparent)}
.jw-tg{border-radius:.8rem}
.jw-err{display:none;margin-top:.4rem;font-size:.78rem;color:#e11d48;font-weight:600}
.jw-invalid .jw-err{display:block}.jw-invalid .jw-input,.jw-invalid .jw-card,.jw-invalid .jw-chip,.jw-invalid .jw-tg{border-color:#fda4af}
.jw-recap{display:flex;flex-wrap:wrap;gap:.4rem;margin-bottom:1.1rem}
.jw-recap span{font-size:.78rem;background:color-mix(in srgb,var(--c) 9%,#fff);color:#334155;border-radius:9999px;padding:.35rem .7rem}
.jw-recap b{color:var(--c);margin-right:.25rem}
.jw-nav{display:flex;align-items:center;justify-content:space-between;gap:.75rem;margin-top:1.5rem}
.jw-back{background:none;border:0;color:#64748b;font-weight:600;cursor:pointer;padding:.7rem .4rem}
.jw-back:hover{color:#0f172a}
.jw-next{margin-left:auto;display:inline-flex;align-items:center;gap:.5rem;padding:.95rem 1.6rem;border-radius:.9rem;border:0;color:#fff;font-weight:800;cursor:pointer;box-shadow:0 20px 45px -20px color-mix(in srgb,var(--c) 70%,transparent);transition:transform .2s}
.jw-next:hover{transform:translateY(-2px)}.jw-next:disabled{opacity:.6;transform:none}
.jw-foot{margin-top:1rem;text-align:center;font-size:.75rem;color:#64748b}
.jw-success{text-align:center;padding:1.5rem .5rem;animation:rise .6s both}
.jw-success h3{font-size:1.6rem;font-weight:700;margin:1rem 0 .4rem}.jw-success p{color:#64748b;max-width:26rem;margin:0 auto}
.jw-call{display:inline-flex;align-items:center;gap:.5rem;margin-top:1.2rem;font-weight:700;color:var(--c)}
.jw-check{width:86px;height:86px;margin:0 auto}
.jw-check svg{width:100%;height:100%;stroke:var(--c);stroke-width:3;fill:none;stroke-linecap:round;stroke-linejoin:round}
.jw-check circle{stroke-dasharray:160;stroke-dashoffset:160;animation:draw .8s ease forwards}
.jw-check path{stroke-dasharray:40;stroke-dashoffset:40;animation:draw .5s ease .6s forwards}
@keyframes draw{to{stroke-dashoffset:0}}
.theme-dark .jw-card,.theme-dark .jw-chip,.theme-dark .jw-tg,.theme-dark .jw-input{background:#181822;border-color:rgba(255,255,255,.1);color:#f1f5f9}
.theme-dark .jw-card i{background:color-mix(in srgb,var(--c) 22%,#181822)}
.theme-dark .jw-card.sel{background:color-mix(in srgb,var(--c) 16%,#181822)}
.theme-dark .jw-card-d,.theme-dark .jw-foot,.theme-dark .jw-back,.theme-dark .jw-success p{color:#a1a1aa}
.theme-dark .jw-label,.theme-dark .jw-chip,.theme-dark .jw-tg{color:#e2e8f0}
.theme-dark .jw-pnum{background:rgba(255,255,255,.1);color:#e2e8f0}.theme-dark .jw-bar{background:rgba(255,255,255,.1)}
.theme-dark .jw-recap span{background:rgba(255,255,255,.07);color:#e2e8f0}
@if($theme === 'dark')
/* ── Thème sombre sectoriel ── */
body{background:#0b0b10;color:#e2e8f0}
.bg-white{background:#12121a!important}.bg-slate-50{background:#0e0e15!important}.bg-slate-100{background:rgba(255,255,255,.08)!important}
.bg-white\/95{background:rgba(11,11,16,.92)!important}
.border-slate-100,.border-slate-200{border-color:rgba(255,255,255,.09)!important}
.text-slate-800,.text-slate-900,.text-slate-700{color:#f1f5f9!important}
.text-slate-600,.text-slate-500{color:#a1a1aa!important}.text-slate-400{color:#8b8b98!important}
.text-slate-100{color:#f8fafc!important}
.divide-slate-100>*+*{border-color:rgba(255,255,255,.08)!important}
input,select,textarea{background:rgba(255,255,255,.04)!important;color:#f1f5f9!important;border-color:rgba(255,255,255,.12)!important;color-scheme:dark}
.card-hover:hover{box-shadow:0 30px 60px -25px rgba(0,0,0,.8)!important}
.hover\:shadow-2xl:hover,.shadow-xl,.shadow-2xl,.shadow-sm{--tw-shadow-color:rgba(0,0,0,.6)}
#nav.bg-white\/95 #brand{color:#f8fafc!important}
footer.bg-slate-900{background:#07070b!important}
@endif
@if($editMode)
/* ── Mode édition (Studio Joow) ── */
.joow-edit [data-edit]{cursor:text;border-radius:4px;outline:2px dashed transparent;outline-offset:3px;transition:outline-color .15s,background .15s}
.joow-edit [data-edit]:hover{outline-color:rgba(99,102,241,.75)}
.joow-edit [data-edit].joow-editing{outline:2px solid #6366f1 !important;background:rgba(99,102,241,.08);cursor:text}
.joow-edit [data-edit-img]{cursor:pointer}
.joow-edit img[data-edit-img]:hover{outline:3px solid rgba(99,102,241,.85);outline-offset:-3px;filter:brightness(.92)}
.joow-edit .marq{animation:none;width:auto;flex-wrap:wrap}
.joow-edit .floaty{animation:none}
.joow-edit .reveal{opacity:1;transform:none}
.joow-edit [data-section]{position:relative}
.joow-edit [data-section]:hover::before{content:attr(data-label);position:absolute;top:10px;left:10px;z-index:40;background:#6366f1;color:#fff;font:600 11px/1 'Plus Jakarta Sans',sans-serif;padding:6px 9px;border-radius:999px;letter-spacing:.06em;text-transform:uppercase;pointer-events:none}
.joow-img-btn{position:absolute;z-index:30;display:inline-flex;align-items:center;gap:.4rem;background:#fff;color:#0f172a;font:600 12px 'Plus Jakarta Sans',sans-serif;padding:.55rem .8rem;border-radius:999px;box-shadow:0 10px 30px -10px rgba(0,0,0,.6);cursor:pointer;border:0}
.joow-img-btn:hover{background:#eef2ff}
@endif
</style>
</head>
<body class="bg-white text-slate-800 antialiased">
@if(!$editMode)<div id="joow-curtain" aria-hidden="true"><span><i class="fa-solid {{ $icon }}"></i></span></div>@endif

<!-- NAV -->
<nav id="nav" class="fixed inset-x-0 top-0 z-50 transition-all duration-300">
  <div class="mx-auto flex max-w-6xl items-center justify-between px-5 py-4">
    <a href="#top" class="flex items-center gap-2.5 text-white" id="brand">
      <span class="grid h-10 w-10 place-items-center rounded-xl text-white bg-grad shadow-c"><i class="fa-solid {{ $icon }}"></i></span>
      <span class="font-display text-lg font-bold" data-edit="business.name">{{ $b['name'] ?? '' }}</span>
    </a>
    <div class="hidden items-center gap-7 md:flex" id="links">
      @if($show('services') && count($services))<a href="#services" class="text-sm font-semibold text-white/90 transition hover:text-white">Services</a>@endif
      @if($menuOn)<a href="#carte" class="text-sm font-semibold text-white/90 transition hover:text-white">Carte</a>@endif
      @if($roomsOn)<a href="#sejour" class="text-sm font-semibold text-white/90 transition hover:text-white">Chambres</a>@endif
      @if($show('about'))<a href="#apropos" class="text-sm font-semibold text-white/90 transition hover:text-white">À propos</a>@endif
      @if($show('reviews') && $reviews->count())<a href="#avis" class="text-sm font-semibold text-white/90 transition hover:text-white">Avis</a>@endif
      <a href="{{ $ctaHref }}" class="rounded-xl bg-grad px-5 py-2.5 text-sm font-bold text-white shadow-c transition hover:-translate-y-0.5"><span data-edit="content.cta_label">{{ $ctaLabel }}</span></a>
    </div>
    @if($phoneHref)<a href="{{ $phoneHref }}" class="grid h-10 w-10 place-items-center rounded-xl bg-white/10 text-white backdrop-blur md:hidden"><i class="fa-solid fa-phone"></i></a>@endif
  </div>
</nav>

<!-- HERO ({{ $heroStyle }}) -->
@php $heroLq = $lqip($hero); $hoursJson = json_encode(array_values($hours), JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT); @endphp
<header id="top" class="relative flex min-h-[100svh] items-center overflow-hidden" data-section="hero" data-label="Accueil">
  <div class="absolute inset-0 overflow-hidden">
    @if($heroLq)<div class="lqip absolute inset-0" style="background-image:url('{{ $heroLq }}')"></div>@endif
    <div class="kb absolute inset-0"><img src="{{ $hero }}" id="heroimg" class="hero-img absolute inset-0 h-[115%] w-full object-cover" alt="{{ $b['name'] ?? '' }}" fetchpriority="high" decoding="async" onload="this.classList.add('ready')"></div>
  </div>
  <div class="hero-ov absolute inset-0"></div>
  <div class="grain absolute inset-0"></div>
  <div class="glow" id="heroglow"></div>
  <div class="pointer-events-none absolute -bottom-24 right-0 h-96 w-96 rounded-full blur-3xl" style="background:var(--grad);opacity:.28"></div>
  @if($editMode)<button type="button" class="joow-img-btn" style="right:1.25rem;bottom:1.25rem" data-edit-img="images.hero"><i class="fa-solid fa-image"></i> Changer la photo de fond</button>@endif

  @php
    $pills = '';
  @endphp
  <div class="relative mx-auto w-full max-w-6xl px-5 py-28 text-white {{ $heroStyle === 'center' ? 'max-w-4xl text-center' : 'grid items-center gap-10 lg:grid-cols-[1.15fr,.85fr]' }}">
    <div class="stagger {{ $heroStyle === 'center' ? 'flex flex-col items-center' : '' }}">
      <div class="mb-6 flex flex-wrap items-center gap-2 {{ $heroStyle === 'center' ? 'justify-center' : '' }}">
        @if(!empty($b['rating']))
        <div class="inline-flex items-center gap-2 rounded-full border border-white/20 bg-white/10 px-4 py-2 text-sm backdrop-blur">
          <span class="text-amber-400">{{ $stars($b['rating']) }}</span>
          <span class="font-semibold">{{ $b['rating'] }}/5</span>
          <span class="text-white/70">· {{ $b['reviews_count'] ?? 0 }} avis Google</span>
        </div>
        @endif
        @if(count($hours))<span class="open-pill" id="open-pill" data-hours="{{ $hoursJson }}" style="display:none"><span class="open-dot"></span><span></span></span>@endif
      </div>
      <p class="mb-3 text-sm font-bold uppercase tracking-[0.3em] accent" data-edit="content.tagline">{{ $t('tagline', $label) }}</p>
      <h1 class="font-display font-bold {{ $serif ? 'text-[2.9rem] leading-[1.0] sm:text-7xl' : 'text-[2.6rem] leading-[1.03] sm:text-6xl' }} {{ $heroStyle === 'center' ? 'max-w-4xl' : '' }}" data-edit="content.hero_title">{{ $t('hero_title', $b['name'] ?? '') }}</h1>
      <p class="mt-5 max-w-xl text-lg text-white/80 {{ $heroStyle === 'center' ? 'sm:text-xl' : '' }}" data-edit="content.hero_subtitle">{{ $t('hero_subtitle', $tagline) }}</p>
      <div class="mt-9 flex flex-wrap gap-4 {{ $heroStyle === 'center' ? 'justify-center' : '' }}">
        <a href="{{ $ctaHref }}" class="group shine rounded-xl bg-grad px-7 py-4 font-bold text-white shadow-c transition hover:-translate-y-0.5"><span data-edit="content.cta_label">{{ $ctaLabel }}</span> <i class="fa-solid fa-arrow-right ml-1 transition group-hover:translate-x-1"></i></a>
        @if($phoneHref)<a href="{{ $phoneHref }}" class="rounded-xl border-2 border-white/25 px-7 py-4 font-bold text-white backdrop-blur transition hover:bg-white/10"><i class="fa-solid fa-phone mr-2"></i><span data-edit="business.phone">{{ $b['phone'] }}</span></a>@endif
      </div>
      @if($badges)
      <div class="mt-10 flex flex-wrap gap-x-7 gap-y-3 {{ $heroStyle === 'center' ? 'justify-center' : '' }}">
        @foreach(array_slice($badges,0,4) as $bi => $bd)<span class="flex items-center gap-2 text-sm text-white/85"><i class="fa-solid fa-circle-check accent"></i><span data-edit="content.badges.{{ $bi }}">{{ $bd }}</span></span>@endforeach
      </div>
      @endif
      @if($heroStyle === 'center' && count($stats))
      <div class="mt-12 grid w-full max-w-2xl grid-cols-3 gap-4 border-t border-white/15 pt-8">
        @foreach($stats as $si => $st)
        <div><p class="font-display text-2xl font-bold text-white sm:text-3xl" data-count data-edit="content.stats.{{ $si }}.v">{{ $st['v'] }}</p><p class="mt-1 text-xs text-white/65" data-edit="content.stats.{{ $si }}.l">{{ $st['l'] }}</p></div>
        @endforeach
      </div>
      @endif
    </div>

    @if($heroStyle === 'split')
    <!-- Photo éditoriale -->
    <div class="reveal on hidden lg:block">
      <div class="floaty relative">
        <img src="{{ $second }}" class="hero-photo aspect-[4/5] w-full object-cover" alt="{{ $b['name'] ?? '' }}" loading="eager" decoding="async" @if($editMode) data-edit-img="images.about" @endif>
        @if(!empty($b['rating']))
        <div class="absolute -bottom-5 -left-6 rounded-2xl bg-white p-4 text-slate-900 shadow-2xl">
          <div class="text-amber-500">{{ $stars($b['rating']) }}</div>
          <p class="mt-0.5 font-display text-2xl font-bold" data-count>{{ $b['rating'] }}<span class="text-sm font-medium text-slate-400">/5</span></p>
          <p class="text-xs text-slate-500">{{ $b['reviews_count'] ?? 0 }} avis Google</p>
        </div>
        @endif
        @if(count($stats))
        <div class="absolute -right-4 top-6 rounded-2xl bg-white/10 px-4 py-3 text-white backdrop-blur-xl border border-white/15 shadow-xl">
          <p class="font-display text-xl font-bold" data-count data-edit="content.stats.0.v">{{ $stats[0]['v'] }}</p><p class="text-[11px] text-white/70" data-edit="content.stats.0.l">{{ $stats[0]['l'] }}</p>
        </div>
        @endif
      </div>
    </div>
    @elseif($heroStyle === 'editorial')
    <!-- Carte flottante -->
    <div class="reveal on hidden lg:block">
      <div class="floaty rounded-3xl border border-white/15 bg-white/10 p-6 backdrop-blur-xl shadow-2xl">
        <div class="flex items-center gap-3 border-b border-white/15 pb-4">
          <span class="grid h-11 w-11 place-items-center rounded-xl bg-grad"><i class="fa-solid fa-location-dot text-white"></i></span>
          <div class="min-w-0">
            <p class="truncate font-display font-bold text-white">{{ Str::limit($b['name'] ?? '', 22) }}</p>
            <p class="truncate text-xs text-white/70">{{ $b['city'] ?? 'France' }}</p>
          </div>
        </div>
        <div class="grid grid-cols-3 gap-3 py-5 text-center">
          @foreach($stats as $si => $st)
          <div><p class="font-display text-xl font-bold text-white" data-count data-edit="content.stats.{{ $si }}.v">{{ $st['v'] }}</p><p class="mt-0.5 text-[11px] leading-tight text-white/65" data-edit="content.stats.{{ $si }}.l">{{ $st['l'] }}</p></div>
          @endforeach
        </div>
        @if($phoneHref)<a href="{{ $phoneHref }}" class="flex items-center justify-center gap-2 rounded-xl bg-white px-4 py-3 text-sm font-bold text-slate-900 transition hover:bg-white/90"><i class="fa-solid fa-phone accent"></i>Appeler maintenant</a>@endif
      </div>
    </div>
    @endif
  </div>

  <a href="#services" class="absolute bottom-6 left-1/2 -translate-x-1/2 text-white/50 transition hover:text-white"><i class="fa-solid fa-chevron-down animate-bounce"></i></a>
</header>

<!-- BANDEAU DE CONFIANCE DÉFILANT -->
@php
  $trust = array_values(array_filter(array_merge(
      !empty($b['rating']) ? [['g', $b['rating'].'/5 · '.($b['reviews_count'] ?? 0).' avis Google']] : [],
      array_map(fn($x) => ['fa-circle-check', $x], array_slice($badges, 0, 4)),
      array_map(fn($s) => ['fa-star', trim(($s['v'] ?? '').' '.($s['l'] ?? ''))], $stats),
      !empty($b['city']) ? [['fa-location-dot', $b['city']]] : [],
      [['fa-shield-halved', $label]],
  )));
@endphp
@if(count($trust) >= 3)
<div class="trust">
  <div class="trust-track">
    @foreach(array_merge($trust, $trust) as [$ic, $txt])
    <span class="trust-item">@if($ic === 'g')<span class="g">G</span>@else<i class="fa-solid {{ $ic }}"></i>@endif{{ $txt }}</span>
    @endforeach
  </div>
</div>
@endif

<!-- STATS BAND (mobile) -->
<section class="border-b border-slate-100 bg-white">
  <div class="mx-auto grid max-w-5xl grid-cols-3 gap-4 px-5 py-8 text-center lg:hidden">
    @foreach($stats as $st)
    <div><p class="font-display text-2xl font-bold accent">{{ $st['v'] }}</p><p class="mt-1 text-xs text-slate-500">{{ $st['l'] }}</p></div>
    @endforeach
  </div>
</section>

<main>
<!-- SERVICES -->
@if($show('services') && count($services))
<section id="services" class="py-24" style="order:{{ $ord('services') }}" data-section="services" data-label="Services">
  <div class="mx-auto max-w-6xl px-5">
    <div class="reveal mb-14 max-w-2xl">
      <p class="text-sm font-bold uppercase tracking-[0.3em] accent" data-edit="content.services_tag">{{ $t('services_tag', 'Nos prestations') }}</p>
      <h2 class="mt-3 font-display text-4xl font-bold sm:text-5xl" data-edit="content.services_title">{{ $t('services_title', 'Un savoir-faire complet') }}</h2>
      <p class="mt-4 text-lg text-slate-500" data-edit="content.services_intro">{{ $t('services_intro', $c['about_p1'] ?? '') }}</p>
    </div>
    @php $featImg = $images['services'] ?? ($pool[2] ?? ($pool[1] ?? $hero)); $featLq = $lqip($featImg); @endphp
    <div class="bento stagger-grid">
      @foreach($services as $i => $s)
      @if($i === 0 && count($services) >= 3)
      <div class="reveal svc feat tilt">
        @if($featLq)<div class="lqip absolute inset-0" style="background-image:url('{{ $featLq }}')"></div>@endif
        <img src="{{ $featImg }}" alt="{{ $s['name'] ?? '' }}" loading="lazy" decoding="async" @if($editMode) data-edit-img="images.services" @endif>
        <div class="ov"></div>
        <span class="svc-tag"><i class="fa-solid fa-star"></i> Le plus demandé</span>
        <h3 class="mt-4 font-display text-3xl font-bold leading-tight sm:text-4xl" data-edit="content.services.0.name">{{ $s['name'] ?? '' }}</h3>
        <p class="mt-2 max-w-md text-white/85" data-edit="content.services.0.desc">{{ $s['desc'] ?? '' }}</p>
        <div class="mt-5 flex flex-wrap items-center gap-4">
          <span class="rounded-full bg-white px-4 py-1.5 text-sm font-bold text-slate-900" data-edit="content.services.0.price">{{ $s['price'] ?? 'Sur devis' }}</span>
          <a href="{{ $ctaHref }}" class="text-sm font-bold text-white/90 hover:text-white">{{ $ctaLabel }} →</a>
        </div>
      </div>
      @else
      <div class="reveal svc tilt group">
        <span class="svc-num">{{ sprintf('%02d', $i+1) }}</span>
        <div class="svc-ic mb-5"><i class="fa-solid {{ $svcIcon($s['name'] ?? '') }}"></i></div>
        <h3 class="font-display text-xl font-bold" data-edit="content.services.{{ $i }}.name">{{ $s['name'] ?? '' }}</h3>
        <p class="mt-2 text-slate-500" data-edit="content.services.{{ $i }}.desc">{{ $s['desc'] ?? '' }}</p>
        <div class="mt-5 flex items-center justify-between border-t border-slate-100 pt-4">
          <span class="text-sm font-bold accent" data-edit="content.services.{{ $i }}.price">{{ $s['price'] ?? 'Sur devis' }}</span>
          <a href="{{ $ctaHref }}" class="text-sm font-semibold text-slate-400 transition group-hover:accent">En savoir plus →</a>
        </div>
      </div>
      @endif
      @endforeach
    </div>
  </div>
</section>
@endif

<!-- PARCOURS : comment ça se passe -->
@if($show('process'))
<section id="parcours" class="relative overflow-hidden py-24" style="order:{{ $ord('process') }}" data-section="process" data-label="Parcours">
  <div class="pointer-events-none absolute -left-24 top-10 h-72 w-72 rounded-full blur-3xl" style="background:var(--grad);opacity:.10"></div>
  <div class="mx-auto max-w-6xl px-5">
    <div class="reveal mb-14 text-center">
      <p class="text-sm font-bold uppercase tracking-[0.3em] accent" data-edit="content.process_tag">{{ $t('process_tag', 'Comment ça se passe') }}</p>
      <h2 class="mt-3 font-display text-4xl font-bold sm:text-5xl" data-edit="content.process_title">{{ $t('process_title', 'Simple, rapide, sans surprise') }}</h2>
    </div>
    <div class="steps stagger-grid">
      @foreach(array_slice($process, 0, 4) as $pi => $p)
      <div class="reveal step">
        <div class="step-n">{{ $pi + 1 }}</div>
        <h3 class="font-display text-xl font-bold" data-edit="content.process.{{ $pi }}.title">{{ $p['title'] }}</h3>
        <p class="mt-2 text-slate-500" data-edit="content.process.{{ $pi }}.desc">{{ $p['desc'] ?? '' }}</p>
      </div>
      @endforeach
    </div>
  </div>
</section>
@endif

<!-- CARTE / MENU (module) -->
@if($show('menu') && $menuOn)
<section id="carte" class="bg-slate-50 py-24" style="order:{{ $ord('menu') }}" data-section="menu" data-label="Carte">
  <div class="mx-auto max-w-5xl px-5">
    <div class="reveal mb-12 flex flex-wrap items-end justify-between gap-6">
      <div>
        <p class="text-sm font-bold uppercase tracking-[0.3em] accent">Menu</p>
        <h2 class="mt-3 font-display text-4xl font-bold sm:text-5xl">{{ $menuCfg['title'] ?? 'Notre carte' }}</h2>
      </div>
      @if(!empty($menuCfg['qr']) && !$editMode)
      <div class="text-center"><img src="https://api.qrserver.com/v1/create-qr-code/?size=110x110&data={{ urlencode('https://'.$slug.'.joow.fr/#carte') }}" class="mx-auto h-24 w-24 rounded-xl border border-slate-200 bg-white p-1" alt="QR code de la carte" loading="lazy"><p class="mt-1 text-[11px] text-slate-500">Carte sur mobile</p></div>
      @endif
    </div>
    <div class="grid gap-8 md:grid-cols-2">
      @php $catOrder = ['menus', 'formules', 'entrées', 'entrees', 'plats', 'poissons', 'viandes', 'pizzas', 'burgers', 'desserts', 'fromages', 'boissons', 'vins', 'cocktails']; @endphp
      @foreach($menuItems->groupBy('category')->sortBy(fn($g, $cat) => (($i = array_search(mb_strtolower($cat), $catOrder, true)) === false ? 50 : $i)) as $cat => $items)
      <div class="reveal rounded-3xl border border-slate-100 bg-white p-7 shadow-sm">
        <h3 class="font-display text-2xl font-bold accent">{{ $cat }}</h3>
        <ul class="mt-5 divide-y divide-slate-100">
          @foreach($items as $it)
          <li class="flex items-start justify-between gap-4 py-3">
            <div><p class="font-semibold text-slate-800">{{ $it->name }}</p>@if($it->description)<p class="text-sm text-slate-500">{{ $it->description }}</p>@endif</div>
            @if($it->price !== null)<span class="shrink-0 font-display font-bold">{{ number_format((float) $it->price, 2, ',', ' ') }} €</span>@endif
          </li>
          @endforeach
        </ul>
      </div>
      @endforeach
    </div>
  </div>
</section>
@endif

<!-- GALERIE -->
@if($show('gallery') && (count($gallery) >= 3 || $editMode))
<section class="overflow-hidden pb-4" style="order:{{ $ord('gallery') }}" data-section="gallery" data-label="Galerie">
  <div class="reveal mx-auto mb-10 max-w-6xl px-5">
    <p class="text-sm font-bold uppercase tracking-[0.3em] accent">Galerie</p>
    <h2 class="mt-3 font-display text-4xl font-bold" data-edit="content.gallery_title">{{ $t('gallery_title', 'En images') }}</h2>
  </div>
  <div class="flex w-max marq gap-5 pl-5">
    @foreach(($editMode ? $gallery : array_merge($gallery, $gallery)) as $gi => $g)
    <div class="group h-64 w-96 shrink-0 overflow-hidden rounded-3xl"><img src="{{ $g }}" class="h-full w-full object-cover transition duration-700 group-hover:scale-105" alt="{{ $b['name'] ?? '' }}" loading="lazy" @if($editMode) data-edit-img="images.gallery.{{ $gi }}" @else data-lb="{{ $g }}" @endif></div>
    @endforeach
    @if($editMode)<button type="button" class="joow-img-btn" style="position:static;height:16rem;width:12rem;border-radius:1.5rem;justify-content:center;border:2px dashed #c7d2fe;background:#f5f3ff" data-edit-img="images.gallery.{{ count($gallery) }}"><i class="fa-solid fa-plus"></i> Ajouter une photo</button>@endif
  </div>
</section>
@endif

<!-- À PROPOS -->
@if($show('about'))
<section id="apropos" class="bg-slate-50 py-24" style="order:{{ $ord('about') }}" data-section="about" data-label="À propos">
  <div class="mx-auto grid max-w-6xl items-center gap-14 px-5 lg:grid-cols-2">
    <div class="reveal relative">
      <img src="{{ $second }}" class="aspect-[4/3] w-full rounded-[2rem] object-cover shadow-2xl" alt="{{ $b['name'] ?? '' }}" @if($editMode) data-edit-img="images.about" @endif>
      @if(!empty($b['rating']))
      <div class="absolute -bottom-6 -right-4 rounded-2xl bg-white p-5 shadow-xl sm:-right-6">
        <div class="text-amber-500">{{ $stars($b['rating']) }}</div>
        <p class="mt-1 font-display text-2xl font-bold">{{ $b['rating'] }}<span class="text-base text-slate-400">/5</span></p>
        <p class="text-xs text-slate-500">{{ $b['reviews_count'] ?? 0 }} avis Google</p>
      </div>
      @endif
    </div>
    <div class="reveal">
      <p class="text-sm font-bold uppercase tracking-[0.3em] accent" data-edit="content.about_tag">{{ $t('about_tag', 'À propos') }}</p>
      <h2 class="mt-3 font-display text-4xl font-bold sm:text-5xl" data-edit="content.about_title">{{ $t('about_title', 'Votre partenaire de confiance') }}</h2>
      <p class="mt-5 text-lg text-slate-600" data-edit="content.about_p1">{{ $c['about_p1'] ?? '' }}</p>
      <p class="mt-4 text-slate-600" data-edit="content.about_p2">{{ $c['about_p2'] ?? '' }}</p>
      @if($badges)
      <div class="mt-8 grid grid-cols-2 gap-4">
        @foreach(array_slice($badges,0,4) as $bi => $bd)
        <div class="flex items-center gap-3"><span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-grad text-white"><i class="fa-solid fa-check text-xs"></i></span><span class="text-sm font-semibold text-slate-700" data-edit="content.badges.{{ $bi }}">{{ $bd }}</span></div>
        @endforeach
      </div>
      @endif
      @if($phoneHref)<a href="{{ $phoneHref }}" class="mt-9 inline-flex items-center gap-2 rounded-xl bg-grad px-6 py-3.5 font-bold text-white shadow-c transition hover:-translate-y-0.5"><i class="fa-solid fa-phone"></i>{{ $b['phone'] }}</a>@endif
    </div>
  </div>
</section>
@endif

<!-- AVIS -->
@if($show('reviews') && $reviews->count())
<section id="avis" class="py-24" style="order:{{ $ord('reviews') }}" data-section="reviews" data-label="Avis">
  <div class="mx-auto max-w-6xl px-5">
    <div class="reveal mb-12 text-center">
      <p class="text-sm font-bold uppercase tracking-[0.3em] accent">Témoignages</p>
      <h2 class="mt-3 font-display text-4xl font-bold sm:text-5xl" data-edit="content.reviews_title">{{ $t('reviews_title', 'Ils nous recommandent') }}</h2>
    </div>
    @if(!empty($b['rating']))
    <div class="reveal rating-big mb-12">
      <div class="text-center"><p class="rating-num" data-count>{{ $b['rating'] }}</p><p class="mt-1 text-2xl text-amber-500">{{ $stars($b['rating']) }}</p></div>
      <div class="text-left">
        <p class="flex items-center gap-2 font-display text-xl font-bold"><span class="grid h-7 w-7 place-items-center rounded-full bg-white text-sm font-black text-[#4285f4] shadow ring-1 ring-slate-200">G</span> {{ $b['reviews_count'] ?? 0 }} avis Google</p>
        <p class="mt-1 text-slate-500">Note moyenne authentique, mise à jour depuis notre fiche Google.</p>
        <div class="mt-3 h-2 w-64 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-grad" style="width:{{ min(100, round(((float) $b['rating']) / 5 * 100)) }}%"></div></div>
      </div>
    </div>
    @endif
  </div>
  @php $revCards = $reviews->take(8)->values(); $twoRows = $revCards->count() >= 4; $rowA = $twoRows ? $revCards->filter(fn($r, $i) => $i % 2 === 0)->values() : $revCards; $rowB = $twoRows ? $revCards->filter(fn($r, $i) => $i % 2 === 1)->values() : collect(); @endphp
  @foreach([[$rowA, 'rev-left'], [$rowB, 'rev-right']] as [$row, $dir])
  @if($row->count())
  <div class="rev-mask reveal mt-5">
    <div class="rev-track {{ $editMode ? '' : $dir }}">
      @foreach(($editMode ? $row : $row->concat($row)) as $r)
      <figure class="rev">
        <span class="q">"</span>
        <div class="text-amber-500">{{ $stars($r['rating'] ?? 5) }}</div>
        <blockquote class="mt-3 text-slate-600">{{ Str::limit($r['text'], 200) }}</blockquote>
        <figcaption class="mt-5 flex items-center gap-3">
          <span class="grid h-10 w-10 place-items-center rounded-full bg-grad font-display font-bold text-white">{{ $initial($r['author'] ?? 'C') }}</span>
          <span><span class="block font-bold text-slate-800">{{ $r['author'] ?: 'Client' }}</span><span class="text-xs text-slate-400"><i class="fa-brands fa-google mr-1"></i>Avis Google</span></span>
        </figcaption>
      </figure>
      @endforeach
    </div>
  </div>
  @endif
  @endforeach
</section>
@endif

<!-- FAQ -->
@if($show('faq') && count($faq))
<section class="bg-slate-50 py-24" style="order:{{ $ord('faq') }}" data-section="faq" data-label="FAQ">
  <div class="mx-auto max-w-3xl px-5">
    <div class="reveal mb-12 text-center">
      <p class="text-sm font-bold uppercase tracking-[0.3em] accent">FAQ</p>
      <h2 class="mt-3 font-display text-4xl font-bold" data-edit="content.faq_title">{{ $t('faq_title', 'Questions fréquentes') }}</h2>
    </div>
    <div class="space-y-3">
      @foreach($faq as $fi => $f)
      <details class="reveal group rounded-2xl border border-slate-100 bg-white p-5 shadow-sm" @if($editMode) open @endif>
        <summary class="flex cursor-pointer list-none items-center justify-between font-bold text-slate-800"><span data-edit="content.faq.{{ $fi }}.q">{{ $f['q'] ?? '' }}</span><span class="grid h-7 w-7 shrink-0 place-items-center rounded-full bg-slate-100 accent transition group-open:rotate-45"><i class="fa-solid fa-plus text-xs"></i></span></summary>
        <p class="mt-3 text-slate-600" data-edit="content.faq.{{ $fi }}.a">{{ $f['a'] ?? '' }}</p>
      </details>
      @endforeach
    </div>
  </div>
</section>
@endif

<!-- CHAMBRES & SÉJOUR (module) -->
@if($show('rooms') && $roomsOn)
@php $rmCfg = $mod('rooms'); @endphp
<section id="sejour" class="py-24" style="order:{{ $ord('rooms') }}" data-section="rooms" data-label="Chambres">
  <div class="mx-auto max-w-6xl px-5">
    <div class="reveal mb-12 max-w-2xl">
      <p class="text-sm font-bold uppercase tracking-[0.3em] accent">Hébergement</p>
      <h2 class="mt-3 font-display text-4xl font-bold sm:text-5xl">{{ $rmCfg['title'] ?? 'Nos chambres' }}</h2>
      @if(!empty($rmCfg['intro']))<p class="mt-4 text-lg text-slate-500">{{ $rmCfg['intro'] }}</p>@endif
    </div>
    <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
      @foreach($rooms as $rm)
      @php $ph = $rm->photos[0] ?? ($photos[($loop->index + 1) % max(1, count($photos))] ?? $hero); @endphp
      <div class="reveal card-hover overflow-hidden rounded-3xl border border-slate-100 bg-white shadow-sm hover:shadow-2xl">
        <img src="{{ $ph }}" class="h-52 w-full object-cover" alt="{{ $rm->name }}" loading="lazy">
        <div class="p-6">
          <div class="flex items-start justify-between gap-3">
            <h3 class="font-display text-xl font-bold">{{ $rm->name }}</h3>
            @if($rm->price_night)<span class="shrink-0 rounded-full bg-grad px-3 py-1 text-sm font-bold text-white">{{ number_format((float) $rm->price_night, 0, ',', ' ') }} €<span class="font-normal opacity-80">/nuit</span></span>@endif
          </div>
          <p class="mt-1 text-sm text-slate-500"><i class="fa-solid fa-user-group accent mr-1"></i>{{ $rm->capacity }} personne{{ $rm->capacity > 1 ? 's' : '' }}</p>
          @if($rm->description)<p class="mt-3 text-sm text-slate-600">{{ $rm->description }}</p>@endif
          @if($rm->amenities)<div class="mt-4 flex flex-wrap gap-1.5">@foreach(array_slice($rm->amenities, 0, 6) as $am)<span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs text-slate-600">{{ $am }}</span>@endforeach</div>@endif
          <button type="button" class="mt-5 w-full rounded-xl border-2 border-accent px-4 py-2.5 text-sm font-bold accent transition hover:bg-accent hover:text-white" onclick="joowPickRoom('{{ $rm->id }}')">Réserver cette chambre</button>
        </div>
      </div>
      @endforeach
    </div>

    <div class="reveal mt-14 rounded-[2rem] border border-slate-100 bg-white p-7 shadow-xl sm:p-9" id="stay-box">
      <h3 class="font-display text-2xl font-bold">Demande de séjour</h3>
      <p class="mt-1 text-slate-500">Disponibilités vérifiées en direct · réponse rapide</p>
      <form id="joow-stay" class="mt-6 space-y-4">
        <input type="text" name="hp" class="hidden" tabindex="-1" autocomplete="off" aria-hidden="true">
        <div class="grid gap-4 sm:grid-cols-4">
          <label class="block"><span class="mb-1 block text-sm font-semibold text-slate-700">Arrivée</span><input name="check_in" type="date" required min="{{ now()->toDateString() }}" class="w-full rounded-xl border border-slate-200 px-4 py-3 outline-none focus:border-accent"></label>
          <label class="block"><span class="mb-1 block text-sm font-semibold text-slate-700">Départ</span><input name="check_out" type="date" required class="w-full rounded-xl border border-slate-200 px-4 py-3 outline-none focus:border-accent"></label>
          <label class="block"><span class="mb-1 block text-sm font-semibold text-slate-700">Personnes</span><input name="guests" type="number" min="1" value="2" class="w-full rounded-xl border border-slate-200 px-4 py-3 outline-none focus:border-accent"></label>
          <label class="block"><span class="mb-1 block text-sm font-semibold text-slate-700">Chambre</span><select name="room_id" id="stay-room" class="w-full rounded-xl border border-slate-200 px-4 py-3 outline-none focus:border-accent"><option value="">Au choix</option>@foreach($rooms as $rm)<option value="{{ $rm->id }}" data-price="{{ $rm->price_night }}">{{ $rm->name }}</option>@endforeach</select></label>
        </div>
        <p id="stay-avail" class="text-sm font-medium text-slate-500"></p>
        <div class="grid gap-4 sm:grid-cols-3">
          <label class="block"><span class="mb-1 block text-sm font-semibold text-slate-700">Nom</span><input name="name" type="text" required class="w-full rounded-xl border border-slate-200 px-4 py-3 outline-none focus:border-accent"></label>
          <label class="block"><span class="mb-1 block text-sm font-semibold text-slate-700">Téléphone</span><input name="phone" type="tel" required class="w-full rounded-xl border border-slate-200 px-4 py-3 outline-none focus:border-accent"></label>
          <label class="block"><span class="mb-1 block text-sm font-semibold text-slate-700">Email</span><input name="email" type="email" class="w-full rounded-xl border border-slate-200 px-4 py-3 outline-none focus:border-accent"></label>
        </div>
        <label class="block"><span class="mb-1 block text-sm font-semibold text-slate-700">Message (optionnel)</span><textarea name="notes" rows="2" class="w-full rounded-xl border border-slate-200 px-4 py-3 outline-none focus:border-accent"></textarea></label>
        <button type="submit" id="joow-stay-btn" class="w-full rounded-xl bg-grad px-6 py-4 font-bold text-white shadow-c transition hover:-translate-y-0.5">Envoyer ma demande de séjour</button>
        <p id="joow-stay-ok" class="hidden rounded-xl bg-emerald-50 px-4 py-3 text-center text-sm font-semibold text-emerald-700"></p>
        <p id="joow-stay-err" class="hidden rounded-xl bg-rose-50 px-4 py-3 text-center text-sm font-semibold text-rose-700"></p>
      </form>
    </div>
  </div>
</section>
@endif

@if($show('booking') && ($restaurantOn || $zcOn))
<!-- RÉSERVATION RESTAURANT (module natif ou ZenChef) -->
<section id="reserver" class="relative overflow-hidden py-24" style="order:{{ $ord('booking') }}" data-section="booking" data-label="Réservation">
  <div class="pointer-events-none absolute -top-20 right-0 h-80 w-80 rounded-full blur-3xl" style="background:var(--grad);opacity:.14"></div>
  <div class="mx-auto grid max-w-6xl items-start gap-14 px-5 lg:grid-cols-[.9fr,1.1fr]">
    <div class="reveal">
      <p class="text-sm font-bold uppercase tracking-[0.3em] accent">Réservation</p>
      <h2 class="mt-3 font-display text-4xl font-bold sm:text-5xl" data-edit="booking.title">{{ $bk('title', 'Réserver votre table') }}</h2>
      <p class="mt-4 text-lg text-slate-600" data-edit="booking.sub">{{ $bk('sub', 'Choisissez votre créneau, confirmation immédiate.') }}</p>
      <ul class="mt-8 space-y-3 text-slate-600">
        <li class="flex items-center gap-3"><span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-grad text-white"><i class="fa-solid fa-bolt text-xs"></i></span><span data-edit="booking.point1">{{ $bk('point1', 'Disponibilités en temps réel') }}</span></li>
        <li class="flex items-center gap-3"><span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-grad text-white"><i class="fa-solid fa-envelope-circle-check text-xs"></i></span><span data-edit="booking.point2">{{ $bk('point2', 'Confirmation par email') }}</span></li>
        @if(!empty($b['phone']))<li class="flex items-center gap-3"><span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-grad text-white"><i class="fa-solid fa-phone text-xs"></i></span>Ou appelez le {{ $b['phone'] }}</li>@endif
      </ul>
    </div>
    <div class="reveal rounded-[2rem] border border-slate-100 bg-white p-7 shadow-xl sm:p-9">
      @if($zcOn)
      <iframe src="https://bookings.zenchef.com/results?rid={{ e($zcCfg['restaurant_id']) }}" class="h-[640px] w-full rounded-2xl" style="border:0" loading="lazy" title="Réservation ZenChef"></iframe>
      @else
      <form id="joow-resa" class="space-y-4">
        <input type="text" name="hp" class="hidden" tabindex="-1" autocomplete="off" aria-hidden="true">
        <div class="grid gap-4 sm:grid-cols-2">
          <label class="block"><span class="mb-1 block text-sm font-semibold text-slate-700">Date</span><input name="date" type="date" required min="{{ now()->toDateString() }}" class="w-full rounded-xl border border-slate-200 px-4 py-3 outline-none focus:border-accent"></label>
          <label class="block"><span class="mb-1 block text-sm font-semibold text-slate-700">{{ $partyLabel }}</span><input name="covers" type="number" min="1" max="{{ (int) ($rsCfg['max_party'] ?? 10) }}" value="2" class="w-full rounded-xl border border-slate-200 px-4 py-3 outline-none focus:border-accent"></label>
        </div>
        <div>
          <span class="mb-2 block text-sm font-semibold text-slate-700">Créneau</span>
          <div id="resa-slots" class="flex flex-wrap gap-2 text-sm text-slate-500">Choisissez une date pour voir les créneaux disponibles.</div>
          <input type="hidden" name="time" id="resa-time">
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
          <label class="block"><span class="mb-1 block text-sm font-semibold text-slate-700">Nom</span><input name="name" type="text" required class="w-full rounded-xl border border-slate-200 px-4 py-3 outline-none focus:border-accent"></label>
          <label class="block"><span class="mb-1 block text-sm font-semibold text-slate-700">Téléphone</span><input name="phone" type="tel" required class="w-full rounded-xl border border-slate-200 px-4 py-3 outline-none focus:border-accent"></label>
        </div>
        <label class="block"><span class="mb-1 block text-sm font-semibold text-slate-700">Email{{ !empty($rsCfg['require_email']) ? '' : ' (optionnel, pour la confirmation)' }}</span><input name="email" type="email" {{ !empty($rsCfg['require_email']) ? 'required' : '' }} class="w-full rounded-xl border border-slate-200 px-4 py-3 outline-none focus:border-accent"></label>
        <label class="block"><span class="mb-1 block text-sm font-semibold text-slate-700">Demande particulière (optionnel)</span><textarea name="notes" rows="2" class="w-full rounded-xl border border-slate-200 px-4 py-3 outline-none focus:border-accent"></textarea></label>
        <button type="submit" id="joow-resa-btn" class="w-full rounded-xl bg-grad px-6 py-4 font-bold text-white shadow-c transition hover:-translate-y-0.5"><span data-edit="booking.cta">{{ $bk('cta', 'Réserver ma table') }}</span></button>
        @php $payCfg = $mod('payment'); @endphp
        @if($on('payment') && ($payCfg['type'] ?? '') === 'hold' && !empty($payCfg['ready']) && (float) ($payCfg['hold_per_cover'] ?? 0) > 0)
        <p class="flex items-center justify-center gap-2 text-center text-xs text-slate-500"><i class="fa-solid fa-lock accent"></i>Empreinte bancaire de {{ number_format((float) $payCfg['hold_per_cover'], 0, ',', ' ') }} € par couvert, débitée uniquement en cas de no-show · paiement sécurisé Stripe</p>
        @endif
        <p id="joow-resa-ok" class="hidden rounded-xl bg-emerald-50 px-4 py-3 text-center text-sm font-semibold text-emerald-700"></p>
        <p id="joow-resa-err" class="hidden rounded-xl bg-rose-50 px-4 py-3 text-center text-sm font-semibold text-rose-700"></p>
      </form>
      @endif
    </div>
  </div>
</section>
@endif

@if($bookingOn)
<!-- RÉSERVATION / RDV / DEVIS -->
<section id="reserver" class="relative overflow-hidden py-24" style="order:{{ $ord('booking') }}" data-section="booking" data-label="Réservation">
  <div class="pointer-events-none absolute -top-20 right-0 h-80 w-80 rounded-full blur-3xl" style="background:var(--grad);opacity:.14"></div>
  <div class="mx-auto grid max-w-6xl items-center gap-14 px-5 lg:grid-cols-[.9fr,1.1fr]">
    <div class="reveal">
      <p class="text-sm font-bold uppercase tracking-[0.3em] accent">{{ $bt === 'reservation' ? 'Réservation' : ($bt === 'rdv' ? 'Rendez-vous' : 'Devis') }}</p>
      <h2 class="mt-3 font-display text-4xl font-bold sm:text-5xl" data-edit="booking.title">{{ $bookTitle }}</h2>
      <p class="mt-4 text-lg text-slate-600" data-edit="booking.sub">{{ $bookSub }}</p>
      <ul class="mt-8 space-y-3 text-slate-600">
        <li class="flex items-center gap-3"><span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-grad text-white"><i class="fa-solid fa-bolt text-xs"></i></span><span data-edit="booking.point1">{{ $bk('point1', 'Réponse rapide, sans engagement') }}</span></li>
        <li class="flex items-center gap-3"><span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-grad text-white"><i class="fa-solid fa-shield-halved text-xs"></i></span><span data-edit="booking.point2">{{ $bk('point2', 'Vos informations restent confidentielles') }}</span></li>
        @if(!empty($b['phone']))<li class="flex items-center gap-3"><span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-grad text-white"><i class="fa-solid fa-phone text-xs"></i></span>Ou appelez directement le {{ $b['phone'] }}</li>@endif
      </ul>
    </div>

    @php
      $form   = $form ?? (config('forms.'.$sector) ?? config('forms.default'));
      $fSteps = array_values(array_filter($form['steps'] ?? [], fn($s) => is_array($s)));
      $fType  = in_array($form['type'] ?? '', ['reservation', 'rdv', 'devis', 'contact'], true) ? $form['type'] : $bt;
      $wide   = ['cards', 'chips', 'toggle', 'textarea'];
    @endphp
    <!-- Formulaire multi-étapes (modèle métier, personnalisable dans le Studio) -->
    <div class="reveal jw rounded-[2rem] border border-slate-100 bg-white p-6 shadow-xl sm:p-8" id="joow-wizard" data-type="{{ $fType }}" data-success="{{ $form['success'] ?? 'Merci ! Votre demande est bien envoyée.' }}">
      <div class="jw-progress">
        @foreach($fSteps as $i => $stp)
        <div class="jw-pstep{{ $i === 0 ? ' active' : '' }}"><span class="jw-pnum">{{ $i + 1 }}</span><span class="jw-ptitle">{{ $stp['title'] ?? 'Étape '.($i+1) }}</span></div>
        @endforeach
      </div>
      <div class="jw-bar"><div class="jw-bar-fill" style="width:{{ count($fSteps) ? round(100 / count($fSteps)) : 100 }}%"></div></div>

      <form id="joow-form" class="mt-6" novalidate>
        <input type="text" name="hp" class="hidden" tabindex="-1" autocomplete="off" aria-hidden="true">
        @foreach($fSteps as $i => $stp)
        <div class="jw-step{{ $i === 0 ? ' active' : '' }}" data-step="{{ $i }}">
          <h3 class="jw-h">@if(!empty($stp['icon']))<i class="fa-solid {{ $stp['icon'] }} accent"></i>@endif{{ $stp['title'] ?? '' }}</h3>
          @if(!empty($stp['contact']))<div class="jw-recap" id="jw-recap" style="display:none"></div>@endif
          <div class="grid gap-5 sm:grid-cols-2">
            @foreach(($stp['fields'] ?? []) as $f)
              @php $key = $f['key'] ?? Str::slug($f['label'] ?? 'champ'); $req = !empty($f['required']); $ft = $f['type'] ?? 'text'; $opts = $f['options'] ?? []; @endphp
              <div class="jw-field {{ in_array($ft, $wide, true) ? 'sm:col-span-2' : '' }}" data-key="{{ $key }}" data-label="{{ $f['label'] ?? $key }}" data-required="{{ $req ? 1 : 0 }}" data-type="{{ $ft }}">
                <label class="jw-label">{{ $f['label'] ?? $key }}@if($req)<span class="accent"> *</span>@endif</label>
                @if($ft === 'cards')
                  <div class="jw-cards">@foreach($opts as $o)@php $o = is_array($o) ? $o : ['label' => $o]; @endphp<button type="button" class="jw-card" data-value="{{ $o['label'] ?? '' }}"><i class="fa-solid {{ $o['icon'] ?? 'fa-circle-check' }}"></i><span class="jw-card-l">{{ $o['label'] ?? '' }}</span>@if(!empty($o['desc']))<span class="jw-card-d">{{ $o['desc'] }}</span>@endif</button>@endforeach</div>
                @elseif($ft === 'chips')
                  <div class="jw-chips">@foreach($opts as $o)<button type="button" class="jw-chip" data-value="{{ is_array($o) ? ($o['label'] ?? '') : $o }}">{{ is_array($o) ? ($o['label'] ?? '') : $o }}</button>@endforeach</div>
                @elseif($ft === 'toggle')
                  <div class="jw-toggle">@foreach($opts as $o)<button type="button" class="jw-tg" data-value="{{ is_array($o) ? ($o['label'] ?? '') : $o }}">{{ is_array($o) ? ($o['label'] ?? '') : $o }}</button>@endforeach</div>
                @elseif($ft === 'select')
                  <select class="jw-input" name="{{ $key }}"><option value="">Choisir…</option>@foreach($opts as $o)<option>{{ is_array($o) ? ($o['label'] ?? '') : $o }}</option>@endforeach</select>
                @elseif($ft === 'textarea')
                  <textarea class="jw-input" name="{{ $key }}" rows="3" placeholder="{{ $f['placeholder'] ?? '' }}"></textarea>
                @elseif($ft === 'date')
                  <input class="jw-input" type="date" name="{{ $key }}" min="{{ now()->toDateString() }}">
                @elseif($ft === 'time')
                  <input class="jw-input" type="time" name="{{ $key }}">
                @elseif($ft === 'number')
                  <input class="jw-input" type="number" name="{{ $key }}" min="{{ $f['min'] ?? 0 }}" placeholder="{{ $f['placeholder'] ?? '' }}">
                @elseif($ft === 'phone')
                  <input class="jw-input" type="tel" name="{{ $key }}" placeholder="{{ $f['placeholder'] ?? '' }}" autocomplete="tel">
                @elseif($ft === 'email')
                  <input class="jw-input" type="email" name="{{ $key }}" placeholder="{{ $f['placeholder'] ?? '' }}" autocomplete="email">
                @else
                  <input class="jw-input" type="text" name="{{ $key }}" placeholder="{{ $f['placeholder'] ?? '' }}" @if($key === 'name') autocomplete="name" @endif>
                @endif
                <p class="jw-err">Merci de renseigner ce champ.</p>
              </div>
            @endforeach
          </div>
        </div>
        @endforeach
        <div class="jw-nav">
          <button type="button" class="jw-back" id="jw-back">← Retour</button>
          <button type="button" class="jw-next bg-grad shine" id="jw-next">Continuer <i class="fa-solid fa-arrow-right"></i></button>
          <button type="submit" class="jw-next bg-grad shine" id="jw-submit" style="display:none">{{ $form['cta'] ?? $bookCta }}</button>
        </div>
        <p class="jw-foot"><i class="fa-solid fa-lock accent"></i> {{ $form['delay'] ?? 'Réponse rapide' }} · vos informations restent confidentielles</p>
      </form>

      <div class="jw-success" id="jw-success" style="display:none">
        <div class="jw-check"><svg viewBox="0 0 52 52"><circle cx="26" cy="26" r="24"/><path d="M15 27l8 8 15-16"/></svg></div>
        <h3 class="font-display">Demande envoyée !</h3>
        <p></p>
        @if(!empty($b['phone']))<a href="{{ $phoneHref }}" class="jw-call"><i class="fa-solid fa-phone"></i> Une urgence ? {{ $b['phone'] }}</a>@endif
      </div>
    </div>
  </div>
</section>
@endif

<!-- BANDE D'APPEL À L'ACTION -->
@if($show('cta'))
<section class="py-12" style="order:{{ $ord('cta') }}" data-section="cta" data-label="Appel à l'action">
  <div class="mx-auto max-w-6xl px-5">
    <div class="reveal cta-band">
      <p class="text-xs font-bold uppercase tracking-[0.3em] text-white/70" data-edit="content.cta_band_tag">{{ $t('cta_band_tag', 'On vous attend') }}</p>
      <h2 class="mx-auto mt-3 max-w-3xl font-display text-3xl font-bold leading-tight sm:text-5xl" data-edit="content.cta_band_title">{{ $t('cta_band_title', $c['cta_text'] ?? 'Prêt à nous rencontrer ?') }}</h2>
      <div class="mt-8 flex flex-wrap items-center justify-center gap-4">
        <a href="{{ $ctaHref }}" class="shine rounded-xl bg-white px-7 py-4 font-bold text-slate-900 shadow-2xl transition hover:-translate-y-0.5">{{ $ctaLabel }} <i class="fa-solid fa-arrow-right ml-1"></i></a>
        @if($phoneHref)<a href="{{ $phoneHref }}" class="rounded-xl border-2 border-white/30 px-7 py-4 font-bold text-white backdrop-blur transition hover:bg-white/10"><i class="fa-solid fa-phone mr-2"></i>{{ $b['phone'] }}</a>@endif
      </div>
      @if(!empty($b['rating']))<p class="mt-6 text-sm text-white/70"><span class="text-amber-400">{{ $stars($b['rating']) }}</span> {{ $b['rating'] }}/5 · {{ $b['reviews_count'] ?? 0 }} avis Google</p>@endif
    </div>
  </div>
</section>
@endif

<!-- CONTACT -->
@if($show('contact'))
<section id="contact" class="relative py-24" style="order:{{ $ord('contact') }}" data-section="contact" data-label="Contact">
  <div class="mx-auto max-w-6xl px-5">
    <div class="grid gap-10 lg:grid-cols-2">
      <div class="reveal">
        <p class="text-sm font-bold uppercase tracking-[0.3em] accent">Contact</p>
        <h2 class="mt-3 font-display text-4xl font-bold sm:text-5xl" data-edit="content.contact_title">{{ $t('contact_title', $ctaLabel) }}</h2>
        <p class="mt-4 text-lg text-slate-600" data-edit="content.cta_text">{{ $c['cta_text'] ?? '' }}</p>
        <div class="mt-9 space-y-4">
          @if(!empty($b['phone']) || $editMode)<a href="{{ $phoneHref ?: '#' }}" class="group flex items-center gap-4"><span class="grid h-12 w-12 place-items-center rounded-xl bg-grad text-white shadow-c"><i class="fa-solid fa-phone"></i></span><span><span class="block text-xs text-slate-400">Téléphone</span><span class="font-bold text-slate-800" data-edit="business.phone">{{ $b['phone'] ?? '' }}</span></span></a>@endif
          @if(!empty($b['address']) || $editMode)
          @php $itin = $b['maps_url'] ?? ('https://www.google.com/maps/search/?api=1&query='.urlencode(($b['address'] ?? '').' '.($b['city'] ?? ''))); @endphp
          <a href="{{ $itin }}" target="_blank" rel="noopener" class="group flex items-center gap-4"><span class="grid h-12 w-12 place-items-center rounded-xl bg-grad text-white shadow-c"><i class="fa-solid fa-location-dot"></i></span><span><span class="block text-xs text-slate-400">Adresse</span><span class="font-bold text-slate-800 group-hover:accent" data-edit="business.address">{{ $b['address'] ?? '' }}</span><span class="block text-xs font-semibold accent">Voir l'itinéraire →</span></span></a>
          @endif
        </div>
        @if(count($hours))
        <div class="mt-8 rounded-2xl border border-slate-100 bg-slate-50 p-5">
          <p class="mb-3 flex items-center gap-2 font-bold text-slate-800"><i class="fa-solid fa-clock accent"></i>Horaires</p>
          <ul class="space-y-1 text-sm text-slate-600">
            @foreach($hours as $h)<li class="flex justify-between gap-4"><span>{{ Str::before($h, ':') }}</span><span class="font-medium">{{ trim(Str::after($h, ':')) }}</span></li>@endforeach
          </ul>
        </div>
        @endif
      </div>
      <div class="reveal overflow-hidden rounded-[2rem] shadow-xl">
        <iframe class="h-full min-h-[380px] w-full" style="border:0" loading="lazy" allowfullscreen referrerpolicy="no-referrer-when-downgrade"
          src="https://www.google.com/maps/embed/v1/place?key={{ $mapsKey }}&q={{ urlencode(($b['address'] ?? '').' '.($b['city'] ?? '')) }}&zoom=15"></iframe>
      </div>
    </div>
  </div>
</section>
@endif
</main>

<!-- FOOTER -->
<footer class="bg-slate-900 py-16 text-slate-400">
  <div class="mx-auto max-w-6xl px-5">
    <div class="foot-grid border-b border-white/10 pb-10">
      <div>
        <a href="#top" class="flex items-center gap-2.5 text-white">
          <span class="grid h-11 w-11 place-items-center rounded-xl bg-grad"><i class="fa-solid {{ $icon }}"></i></span>
          <span class="font-display text-xl font-bold">{{ $b['name'] ?? '' }}</span>
        </a>
        <p class="mt-4 max-w-sm text-sm leading-relaxed">{{ Str::limit($t('hero_subtitle', $tagline), 160) }}</p>
        @if(!empty($b['rating']))<p class="mt-4 text-sm"><span class="text-amber-400">{{ $stars($b['rating']) }}</span> <span class="text-white">{{ $b['rating'] }}/5</span> · {{ $b['reviews_count'] ?? 0 }} avis Google</p>@endif
        @if($waOn)<a href="https://wa.me/{{ preg_replace('/\D/', '', $waCfg['number']) }}" target="_blank" rel="noopener" class="mt-4 inline-flex items-center gap-2 rounded-xl bg-white/5 px-3 py-2 text-sm text-white transition hover:bg-white/10"><i class="fa-brands fa-whatsapp text-[#25D366]"></i> Écrire sur WhatsApp</a>@endif
      </div>
      <div>
        <p class="mb-3 text-xs font-bold uppercase tracking-[0.2em] text-slate-500">Navigation</p>
        @if($show('services') && count($services))<a href="#services" class="foot-link">Services</a>@endif
        @if($menuOn)<a href="#carte" class="foot-link">Carte</a>@endif
        @if($roomsOn)<a href="#sejour" class="foot-link">Chambres</a>@endif
        @if($show('about'))<a href="#apropos" class="foot-link">À propos</a>@endif
        @if($reviews->count() && $show('reviews'))<a href="#avis" class="foot-link">Avis</a>@endif
        @if($show('contact'))<a href="#contact" class="foot-link">Contact</a>@endif
        @if($legalOn)<a href="#" onclick="document.getElementById('joow-legal').showModal();return false;" class="foot-link">Mentions légales</a>@endif
      </div>
      <div>
        <p class="mb-3 text-xs font-bold uppercase tracking-[0.2em] text-slate-500">{{ count($hours) ? 'Horaires' : 'Contact' }}</p>
        @if(count($hours))
        <ul class="space-y-1 text-sm">
          @foreach(array_slice($hours, 0, 7) as $h)<li class="flex justify-between gap-3"><span class="capitalize">{{ Str::before($h, ':') }}</span><span class="text-slate-300">{{ trim(Str::after($h, ':')) }}</span></li>@endforeach
        </ul>
        @else
        @if(!empty($b['phone']))<a href="{{ $phoneHref }}" class="foot-link"><i class="fa-solid fa-phone mr-2 accent"></i>{{ $b['phone'] }}</a>@endif
        @if(!empty($b['address']))<p class="foot-link"><i class="fa-solid fa-location-dot mr-2 accent"></i>{{ $b['address'] }}</p>@endif
        @endif
      </div>
    </div>
    <div class="mt-8 flex flex-col items-center justify-between gap-3 text-sm sm:flex-row">
      <p>{{ $b['address'] ?? '' }}</p>
      <p class="text-xs">© {{ date('Y') }} {{ $b['name'] ?? '' }} — Site créé avec <a href="https://joow.fr" class="inline-flex items-center gap-1 font-semibold accent" rel="noopener"><img src="https://app.joow.fr/brand/joow-mark.svg" alt="" width="14" height="14" class="inline-block h-3.5 w-3.5" loading="lazy">Joow</a></p>
    </div>
  </div>
</footer>

<!-- BARRE MOBILE STICKY -->
@if(!$editMode)
<div class="fixed inset-x-4 bottom-4 z-50 flex gap-2 md:hidden">
  @if($phoneHref)<a href="{{ $phoneHref }}" class="grid h-14 w-14 shrink-0 place-items-center rounded-2xl border border-white/20 bg-slate-900/90 text-white shadow-2xl backdrop-blur"><i class="fa-solid fa-phone"></i></a>@endif
  <a href="{{ $ctaHref }}" class="flex flex-1 items-center justify-center gap-2 rounded-2xl bg-grad px-6 py-4 font-bold text-white shadow-2xl">{{ $ctaLabel }} <i class="fa-solid fa-arrow-right"></i></a>
</div>
@endif

<!-- LIGHTBOX GALERIE -->
@if(!$editMode)
<div id="joow-lb" role="dialog" aria-label="Photo">
  <button type="button" class="lb-x" aria-label="Fermer">✕</button>
  <button type="button" class="lb-p" aria-label="Précédente"><i class="fa-solid fa-chevron-left"></i></button>
  <img src="" alt="">
  <button type="button" class="lb-n" aria-label="Suivante"><i class="fa-solid fa-chevron-right"></i></button>
</div>
@endif

<script>
const nav=document.getElementById('nav'),brand=document.getElementById('brand'),hero=document.getElementById('heroimg');
const onScroll=()=>{const s=scrollY>40;
  nav.classList.toggle('bg-white/95',s);nav.classList.toggle('backdrop-blur-lg',s);nav.classList.toggle('shadow-lg',s);
  brand.classList.toggle('text-slate-900',s);brand.classList.toggle('text-white',!s);
  document.querySelectorAll('#links a:not(.bg-grad)').forEach(a=>{a.classList.toggle('text-slate-700',s);a.classList.toggle('text-white/90',!s)});
  if(hero) hero.style.transform='translateY('+Math.min(scrollY*.28,180)+'px)';
};
onScroll();addEventListener('scroll',onScroll,{passive:true});
const io=new IntersectionObserver(e=>e.forEach(x=>{if(x.isIntersecting){x.target.classList.add('on');io.unobserve(x.target)}}),{threshold:.12});
document.querySelectorAll('.reveal:not(.on)').forEach(el=>io.observe(el));

// ── Première impression : rideau, halo curseur, compteurs, "Ouvert maintenant"
const cur=document.getElementById('joow-curtain');
if(cur){const hide=()=>cur.classList.add('gone');(document.readyState==='complete')?setTimeout(hide,200):addEventListener('load',()=>setTimeout(hide,200));setTimeout(hide,2200);}
const hg=document.getElementById('heroglow'),hd=document.getElementById('top');
if(hg&&hd&&matchMedia('(pointer:fine)').matches){hd.addEventListener('pointermove',e=>{const r=hd.getBoundingClientRect();hg.style.setProperty('--mx',((e.clientX-r.left)/r.width*100)+'%');hg.style.setProperty('--my',((e.clientY-r.top)/r.height*100)+'%');},{passive:true});}
document.querySelectorAll('[data-count]').forEach(el=>{
  const node=[...el.childNodes].find(n=>n.nodeType===3&&n.textContent.trim())||el.firstChild;if(!node)return;
  const t=node.textContent.trim(),m=t.match(/^([^\d]*)(\d+(?:[.,]\d+)?)(.*)$/);if(!m)return;
  const end=parseFloat(m[2].replace(',','.')),dec=(m[2].split(/[.,]/)[1]||'').length,sep=m[2].includes(',')?',':'.',st=performance.now(),dur=1500;
  const tick=n=>{const p=Math.min((n-st)/dur,1),v=end*(1-Math.pow(1-p,3));node.textContent=m[1]+(dec?v.toFixed(dec).replace('.',sep):Math.round(v))+m[3];if(p<1)requestAnimationFrame(tick);};
  setTimeout(()=>requestAnimationFrame(tick),500);
});
const op=document.getElementById('open-pill');
if(op){try{
  const hours=JSON.parse(op.dataset.hours||'[]'),days=['dimanche','lundi','mardi','mercredi','jeudi','vendredi','samedi'],now=new Date();
  const line=hours.find(h=>h.toLowerCase().startsWith(days[now.getDay()]));
  if(line){const txt=line.split(':').slice(1).join(':');
    const ranges=[...txt.matchAll(/(\d{1,2})[:h](\d{2})\s*[–—-]\s*(\d{1,2})[:h](\d{2})/g)].map(m=>[+m[1]*60+ +m[2],+m[3]*60+ +m[4]]);
    const c=now.getHours()*60+now.getMinutes(),fmt=v=>String(Math.floor((v%1440)/60)).padStart(2,'0')+'h'+String(v%60).padStart(2,'0');
    let open=false,label='';
    if(/24\s*h/i.test(txt)){open=true;label='Ouvert 24h/24';}
    else{for(const [a,b] of ranges){const bb=b<=a?b+1440:b;if(c>=a&&c<bb){open=true;label='Ouvert · ferme à '+fmt(b);break;}}
      if(!open){const next=ranges.find(([a])=>a>c);label=ranges.length?(next?'Fermé · ouvre à '+fmt(next[0]):'Fermé · à demain'):'Fermé aujourd\'hui';}}
    op.querySelector('.open-dot').classList.toggle('off',!open);op.lastElementChild.textContent=label;op.style.display='inline-flex';
  }
}catch(e){}}

// ── Relief 3D au survol (desktop) + halo suivant le curseur sur les cartes
if(matchMedia('(pointer:fine)').matches&&!matchMedia('(prefers-reduced-motion:reduce)').matches){
  document.querySelectorAll('.tilt').forEach(el=>{
    el.addEventListener('pointermove',e=>{const r=el.getBoundingClientRect(),x=(e.clientX-r.left)/r.width,y=(e.clientY-r.top)/r.height;
      el.style.transform='perspective(900px) rotateX('+((.5-y)*6)+'deg) rotateY('+((x-.5)*8)+'deg) translateY(-4px)';el.style.setProperty('--px',(x*100)+'%');el.style.setProperty('--py',(y*100)+'%');});
    el.addEventListener('pointerleave',()=>{el.style.transform='';});
  });
}
// ── Lightbox galerie
const lb=document.getElementById('joow-lb');
if(lb){const imgs=[...document.querySelectorAll('[data-lb]')],srcs=[...new Set(imgs.map(i=>i.dataset.lb))],im=lb.querySelector('img');let k=0;
  const open=n=>{k=(n+srcs.length)%srcs.length;im.src=srcs[k];lb.classList.add('open');document.body.style.overflow='hidden';};
  const close=()=>{lb.classList.remove('open');document.body.style.overflow='';};
  imgs.forEach(i=>i.addEventListener('click',()=>open(srcs.indexOf(i.dataset.lb))));
  lb.querySelector('.lb-x').onclick=close;lb.querySelector('.lb-p').onclick=()=>open(k-1);lb.querySelector('.lb-n').onclick=()=>open(k+1);
  lb.addEventListener('click',e=>{if(e.target===lb)close();});
  addEventListener('keydown',e=>{if(!lb.classList.contains('open'))return;if(e.key==='Escape')close();if(e.key==='ArrowLeft')open(k-1);if(e.key==='ArrowRight')open(k+1);});
}

// ── Formulaire multi-étapes -> API Joow (demandes)
const W=document.getElementById('joow-wizard');
if(W){
  const form=document.getElementById('joow-form'),steps=[...form.querySelectorAll('.jw-step')],pst=[...W.querySelectorAll('.jw-pstep')],bar=W.querySelector('.jw-bar-fill');
  const back=document.getElementById('jw-back'),next=document.getElementById('jw-next'),sub=document.getElementById('jw-submit');
  const CONTACT=['name','phone','email','message'];let i=0;const vals={};
  const setVal=(f,v)=>{vals[f.dataset.key]={label:f.dataset.label,value:v};if(v)f.classList.remove('jw-invalid');};
  W.querySelectorAll('.jw-card').forEach(b=>b.addEventListener('click',()=>{const f=b.closest('.jw-field');f.querySelectorAll('.jw-card').forEach(x=>x.classList.remove('sel'));b.classList.add('sel');setVal(f,b.dataset.value);}));
  W.querySelectorAll('.jw-tg').forEach(b=>b.addEventListener('click',()=>{const f=b.closest('.jw-field');f.querySelectorAll('.jw-tg').forEach(x=>x.classList.remove('sel'));b.classList.add('sel');setVal(f,b.dataset.value);}));
  W.querySelectorAll('.jw-chip').forEach(b=>b.addEventListener('click',()=>{b.classList.toggle('sel');const f=b.closest('.jw-field');setVal(f,[...f.querySelectorAll('.jw-chip.sel')].map(x=>x.dataset.value).join(', '));}));
  W.querySelectorAll('.jw-input').forEach(el=>{const h=()=>setVal(el.closest('.jw-field'),el.value.trim());el.addEventListener('input',h);el.addEventListener('change',h);});
  const validate=()=>{let ok=true;steps[i].querySelectorAll('.jw-field[data-required="1"]').forEach(f=>{const v=vals[f.dataset.key]&&vals[f.dataset.key].value;if(!v){ok=false;f.classList.add('jw-invalid');}});
    const em=steps[i].querySelector('input[type=email]');if(em&&em.value&&!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(em.value)){ok=false;em.closest('.jw-field').classList.add('jw-invalid');}
    return ok;};
  const show=n=>{steps[i].classList.remove('active');i=n;steps[i].classList.add('active');
    pst.forEach((p,k)=>{p.classList.toggle('active',k<=i);p.classList.toggle('done',k<i);});bar.style.width=Math.round((i+1)/steps.length*100)+'%';
    back.style.visibility=i?'visible':'hidden';const last=i===steps.length-1;next.style.display=last?'none':'';sub.style.display=last?'':'none';
    const r=document.getElementById('jw-recap');
    if(r){const items=Object.entries(vals).filter(([k,v])=>v.value&&!CONTACT.includes(k));r.innerHTML=items.map(([k,v])=>'<span><b>'+v.label+'</b>'+v.value+'</span>').join('');r.style.display=items.length?'flex':'none';}
    if(n>0)W.scrollIntoView({behavior:'smooth',block:'start'});};
  next.addEventListener('click',()=>{if(validate())show(i+1);});back.addEventListener('click',()=>show(i-1));
  form.addEventListener('submit',async ev=>{ev.preventDefault();if(!validate())return;sub.disabled=true;const old=sub.innerHTML;sub.textContent='Envoi…';
    const base={type:W.dataset.type,hp:form.hp.value,payload:{}};CONTACT.forEach(k=>base[k]=(vals[k]&&vals[k].value)||'');
    Object.entries(vals).forEach(([k,v])=>{if(!CONTACT.includes(k)&&v.value)base.payload[v.label]=v.value;});
    try{const r=await fetch('{{ $apiBase }}/api/lead/{{ $slug }}',{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json'},body:JSON.stringify(base)});
      if(!r.ok)throw 0;form.style.display='none';W.querySelector('.jw-progress').style.display='none';W.querySelector('.jw-bar').style.display='none';
      const s=document.getElementById('jw-success');s.querySelector('p').textContent=W.dataset.success;s.style.display='block';W.scrollIntoView({behavior:'smooth',block:'center'});
    }catch(e){sub.disabled=false;sub.innerHTML=old;alert('Une erreur est survenue. Réessayez ou appelez-nous.');}
  });
  show(0);
}
</script>

{{-- ═══════════ MODULES : widgets flottants, mentions légales, mesure d'audience ═══════════ --}}
@if($waOn || $botOn || $legalOn)
<style>
.joow-fab{position:fixed;bottom:1.5rem;z-index:60;display:grid;place-items:center;height:3.5rem;width:3.5rem;border-radius:9999px;color:#fff;box-shadow:0 18px 40px -12px rgba(0,0,0,.45);transition:transform .2s;cursor:pointer;border:0}
.joow-fab:hover{transform:translateY(-2px) scale(1.04)}
@media (max-width:767px){.joow-fab{bottom:5.5rem}}
.joow-wa{background:#25D366;font-size:1.6rem}
.joow-bot{background:var(--grad);font-size:1.35rem}
.joow-panel{position:fixed;bottom:5.5rem;z-index:61;width:min(92vw,360px);max-height:70vh;display:none;flex-direction:column;overflow:hidden;border-radius:1.25rem;background:#fff;box-shadow:0 30px 60px -20px rgba(0,0,0,.45);border:1px solid #e2e8f0}
@media (max-width:767px){.joow-panel{bottom:9.5rem}}
.joow-panel.open{display:flex}
.joow-msgs{flex:1;overflow-y:auto;padding:1rem;display:flex;flex-direction:column;gap:.5rem;font-size:.9rem}
.joow-msg{max-width:85%;padding:.55rem .8rem;border-radius:1rem;line-height:1.35}
.joow-msg.ai{align-self:flex-start;background:#f1f5f9;color:#0f172a;border-bottom-left-radius:.25rem}
.joow-msg.user{align-self:flex-end;background:var(--grad);color:#fff;border-bottom-right-radius:.25rem}
dialog#joow-legal{max-width:760px;width:92vw;border:0;border-radius:1.5rem;padding:0}
dialog#joow-legal::backdrop{background:rgba(2,6,23,.6);backdrop-filter:blur(4px)}
#joow-legal .legal{padding:2rem;max-height:80vh;overflow-y:auto;color:#334155;font-size:.95rem;line-height:1.6}
#joow-legal .legal h2{font-family:'{{ $displayFont }}',sans-serif;font-weight:700;font-size:1.5rem;color:#0f172a;margin:1.25rem 0 .5rem}
#joow-legal .legal h3{font-weight:700;color:#0f172a;margin:1rem 0 .35rem}
#joow-legal .legal p{margin:.35rem 0}
</style>
@endif

@if($waOn)
<a href="https://wa.me/{{ preg_replace('/\D/', '', $waCfg['number']) }}?text={{ urlencode($waCfg['message'] ?? '') }}" target="_blank" rel="noopener" class="joow-fab joow-wa" style="{{ ($waCfg['position'] ?? 'right') === 'left' ? 'left:1.25rem' : 'right:1.25rem' }}" aria-label="Discuter sur WhatsApp"><i class="fa-brands fa-whatsapp"></i></a>
@endif

@if($botOn)
@php $botSide = ($botCfg['position'] ?? 'right') === 'left' ? 'left' : 'right'; if ($waOn && ($waCfg['position'] ?? 'right') === $botSide) { $botSide = $botSide === 'left' ? 'right' : 'left'; } @endphp
<button type="button" class="joow-fab joow-bot" id="joow-bot-fab" style="{{ $botSide }}:1.25rem" aria-label="Assistant"><i class="fa-solid fa-comment-dots"></i></button>
<div class="joow-panel" id="joow-bot-panel" style="{{ $botSide }}:1.25rem">
  <div class="flex items-center gap-3 bg-grad px-4 py-3 text-white">
    <span class="grid h-9 w-9 place-items-center rounded-full bg-white/20"><i class="fa-solid fa-robot"></i></span>
    <div class="min-w-0 flex-1"><p class="truncate font-bold">{{ $botCfg['name'] ?? 'Assistant' }}</p><p class="text-xs opacity-80">Répond en quelques secondes</p></div>
    <button type="button" onclick="document.getElementById('joow-bot-panel').classList.remove('open')" class="text-white/80 hover:text-white">✕</button>
  </div>
  <div class="joow-msgs" id="joow-bot-msgs"><div class="joow-msg ai">{{ $botCfg['welcome'] ?? 'Bonjour 👋 Une question ?' }}</div></div>
  <form id="joow-bot-form" class="flex gap-2 border-t border-slate-100 p-3">
    <input id="joow-bot-input" type="text" placeholder="Votre question…" class="flex-1 rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none focus:border-accent" autocomplete="off">
    <button type="submit" class="rounded-xl bg-grad px-3 py-2 text-white"><i class="fa-solid fa-paper-plane"></i></button>
  </form>
</div>
@endif

@if($legalOn)
<dialog id="joow-legal">
  <div class="legal">
    <div class="flex items-center justify-between"><p class="text-xs font-bold uppercase tracking-[0.3em] accent">Informations légales</p><button type="button" onclick="document.getElementById('joow-legal').close()" class="grid h-8 w-8 place-items-center rounded-full bg-slate-100 text-slate-600 hover:bg-slate-200">✕</button></div>
    {!! $legalHtml !!}
  </div>
</dialog>
@endif

<script>
(function(){
  const API='{{ $apiBase }}/api/site/{{ $slug }}';
  const J={'Content-Type':'application/json','Accept':'application/json'};
  const form2json=(f,fields)=>{const fd=new FormData(f),o={};fd.forEach((v,k)=>{if(!fields||fields.includes(k))o[k]=v;});return o;};

  @if(!$editMode)
  // Mesure d'audience anonyme
  try{fetch(API+'/view',{method:'POST',headers:J,body:'{}',keepalive:true}).catch(()=>{});}catch(e){}
  @endif

  // ── Réservation restaurant (module natif)
  const rf=document.getElementById('joow-resa');
  if(rf){
    const slotsEl=document.getElementById('resa-slots'),timeEl=document.getElementById('resa-time');
    const loadSlots=async()=>{
      const d=rf.date.value,c=rf.covers.value||2;timeEl.value='';
      if(!d){slotsEl.innerHTML='<span class="text-slate-500">Choisissez une date pour voir les créneaux disponibles.</span>';return;}
      slotsEl.innerHTML='<span class="text-slate-400">Recherche des créneaux…</span>';
      try{const r=await fetch(API+'/availability?date='+encodeURIComponent(d)+'&covers='+encodeURIComponent(c),{headers:J});const j=await r.json();
        if(!j.slots||!j.slots.length){slotsEl.innerHTML='<span class="text-rose-600">'+(j.reason||'Aucun créneau disponible pour ce nombre de couverts.')+'</span>';return;}
        slotsEl.innerHTML='';let lastSvc='';
        j.slots.forEach(s=>{if(s.service!==lastSvc){lastSvc=s.service;const h=document.createElement('span');h.className='w-full text-xs font-bold uppercase tracking-wider text-slate-400 mt-1';h.textContent=s.service==='lunch'?'Midi':'Soir';slotsEl.appendChild(h);}
          const b=document.createElement('button');b.type='button';b.textContent=s.time;b.className='rounded-xl border border-slate-200 px-3.5 py-2 font-semibold text-slate-700 transition hover:border-accent hover:accent';
          b.onclick=()=>{timeEl.value=s.time;slotsEl.querySelectorAll('button').forEach(x=>x.classList.remove('bg-grad','text-white','border-transparent'));b.classList.add('bg-grad','text-white','border-transparent');};slotsEl.appendChild(b);});
      }catch(e){slotsEl.innerHTML='<span class="text-rose-600">Impossible de charger les créneaux.</span>';}
    };
    rf.date.addEventListener('change',loadSlots);rf.covers.addEventListener('change',loadSlots);
    rf.addEventListener('submit',async ev=>{ev.preventDefault();
      const ok=document.getElementById('joow-resa-ok'),er=document.getElementById('joow-resa-err'),btn=document.getElementById('joow-resa-btn');
      ok.classList.add('hidden');er.classList.add('hidden');
      if(!timeEl.value){er.textContent='Choisissez un créneau.';er.classList.remove('hidden');return;}
      btn.disabled=true;
      try{const r=await fetch(API+'/reserve',{method:'POST',headers:J,body:JSON.stringify(form2json(rf))});const j=await r.json();
        if(!r.ok||!j.ok)throw new Error(j.error||(j.errors&&Object.values(j.errors)[0][0])||'Erreur');
        if(j.pay_url){ok.textContent='Redirection vers l\'enregistrement sécurisé de votre carte…';ok.classList.remove('hidden');location.href=j.pay_url;return;}
        ok.textContent=(j.status==='confirmed'?'✓ Réservation confirmée ! ':'✓ Demande envoyée ! ')+(j.message||'');ok.classList.remove('hidden');rf.reset();slotsEl.innerHTML='';
      }catch(e){er.textContent=e.message||'Une erreur est survenue.';er.classList.remove('hidden');}
      finally{btn.disabled=false;}
    });
  }

  // ── Chambres / séjour
  const sf=document.getElementById('joow-stay');
  window.joowPickRoom=(id)=>{const s=document.getElementById('stay-room');if(s){s.value=id;s.dispatchEvent(new Event('change'));}document.getElementById('stay-box')?.scrollIntoView({behavior:'smooth',block:'start'});};
  if(sf){
    const av=document.getElementById('stay-avail'),roomSel=document.getElementById('stay-room');
    const check=async()=>{
      const a=sf.check_in.value,b=sf.check_out.value;if(!a||!b||b<=a){av.textContent='';return;}
      av.textContent='Vérification des disponibilités…';
      try{const r=await fetch(API+'/rooms?from='+a+'&to='+b,{headers:J});const j=await r.json();
        const n=Math.round((new Date(b)-new Date(a))/86400000);
        [...roomSel.options].forEach(o=>{if(!o.value)return;const rm=j.rooms.find(x=>x.id===o.value);o.disabled=rm&&rm.available===false;o.textContent=(rm?rm.name:o.textContent).replace(/ \(.*\)$/,'')+(rm&&rm.available===false?' (indisponible)':'');});
        const sel=j.rooms.find(x=>x.id===roomSel.value);
        av.textContent=n+' nuit'+(n>1?'s':'')+(sel&&sel.price_night?' · total estimé '+(n*sel.price_night).toLocaleString('fr-FR')+' €':'')+(sel&&sel.available===false?' · cette chambre est indisponible sur ces dates':' · '+j.rooms.filter(x=>x.available!==false).length+' hébergement(s) disponible(s)');
      }catch(e){av.textContent='';}
    };
    ['check_in','check_out'].forEach(k=>sf[k].addEventListener('change',check));roomSel.addEventListener('change',check);
    sf.addEventListener('submit',async ev=>{ev.preventDefault();
      const ok=document.getElementById('joow-stay-ok'),er=document.getElementById('joow-stay-err'),btn=document.getElementById('joow-stay-btn');
      ok.classList.add('hidden');er.classList.add('hidden');btn.disabled=true;
      try{const r=await fetch(API+'/stay',{method:'POST',headers:J,body:JSON.stringify(form2json(sf))});const j=await r.json();
        if(!r.ok||!j.ok)throw new Error(j.error||(j.errors&&Object.values(j.errors)[0][0])||'Erreur');
        ok.textContent='✓ Demande envoyée pour '+j.nights+' nuit(s)'+(j.total?' · '+j.total.toLocaleString('fr-FR')+' € estimés':'')+'. Nous vous confirmons très vite.';ok.classList.remove('hidden');sf.reset();av.textContent='';
      }catch(e){er.textContent=e.message||'Une erreur est survenue.';er.classList.remove('hidden');}
      finally{btn.disabled=false;}
    });
  }

  // ── Assistant IA
  const fab=document.getElementById('joow-bot-fab');
  if(fab){
    const panel=document.getElementById('joow-bot-panel'),msgs=document.getElementById('joow-bot-msgs'),form=document.getElementById('joow-bot-form'),input=document.getElementById('joow-bot-input');
    const hist=[];const add=(role,text)=>{const d=document.createElement('div');d.className='joow-msg '+role;d.textContent=text;msgs.appendChild(d);msgs.scrollTop=msgs.scrollHeight;return d;};
    fab.onclick=()=>{panel.classList.toggle('open');if(panel.classList.contains('open'))input.focus();};
    form.addEventListener('submit',async ev=>{ev.preventDefault();const q=input.value.trim();if(!q)return;input.value='';add('user',q);hist.push({role:'user',text:q});
      const w=add('ai','…');
      try{const r=await fetch(API+'/bot',{method:'POST',headers:J,body:JSON.stringify({message:q,history:hist.slice(-8)})});const j=await r.json();w.textContent=j.reply||'…';hist.push({role:'ai',text:w.textContent});}
      catch(e){w.textContent='Petit souci technique, réessayez dans un instant.';}
    });
  }
})();
</script>

@if($editMode)
<script>
/* ── Runtime d'édition en place (Studio Joow) ── */
(function(){
  const send=(m)=>window.parent.postMessage(Object.assign({joow:1},m),'*');
  document.querySelectorAll('.reveal').forEach(e=>e.classList.add('on'));
  // Neutraliser la navigation et les envois de formulaire en mode édition
  document.addEventListener('click',e=>{const a=e.target.closest('a');if(a&&!e.target.closest('[data-edit]'))e.preventDefault();},true);
  document.querySelectorAll('form').forEach(f=>f.addEventListener('submit',e=>e.preventDefault(),true));

  const setEditable=(el,on)=>{try{el.contentEditable=on?'plaintext-only':'false';}catch(_){el.contentEditable=on?'true':'false';}};
  document.querySelectorAll('[data-edit]').forEach(el=>{
    el.addEventListener('click',e=>{
      e.preventDefault();e.stopPropagation();
      if(el.isContentEditable)return;
      el.dataset.orig=el.textContent;setEditable(el,true);el.classList.add('joow-editing');el.focus();
      const r=document.createRange();r.selectNodeContents(el);const s=getSelection();s.removeAllRanges();s.addRange(r);
      send({type:'focus',path:el.dataset.edit});
    });
    el.addEventListener('keydown',e=>{
      const multi=el.matches('p,blockquote');
      if(e.key==='Enter'&&!(multi&&e.shiftKey)){e.preventDefault();el.blur();}
      if(e.key==='Escape'){el.textContent=el.dataset.orig;el.blur();}
    });
    el.addEventListener('blur',()=>{
      if(!el.isContentEditable)return;
      setEditable(el,false);el.classList.remove('joow-editing');
      const v=el.textContent.replace(/\s+/g,' ').trim();
      if(v!==el.dataset.orig)send({type:'edit',path:el.dataset.edit,value:v});
    });
  });
  document.querySelectorAll('[data-edit-img]').forEach(el=>{
    el.addEventListener('click',e=>{e.preventDefault();e.stopPropagation();send({type:'image',path:el.dataset.editImg,current:el.currentSrc||el.src||''});});
  });
  document.querySelectorAll('[data-section]').forEach(s=>{
    s.addEventListener('click',e=>{if(e.target.closest('[data-edit],[data-edit-img]'))return;send({type:'section',id:s.dataset.section});});
  });
  window.addEventListener('message',e=>{
    const m=e.data||{};if(!m.joow)return;
    if(m.type==='setText'){document.querySelectorAll('[data-edit="'+m.path+'"]').forEach(el=>{if(!el.isContentEditable)el.textContent=m.value;});}
    if(m.type==='setImage'){document.querySelectorAll('[data-edit-img="'+m.path+'"]').forEach(el=>{if(el.tagName==='IMG')el.src=m.value;});if(m.path==='images.hero'&&hero)hero.src=m.value;}
    if(m.type==='scrollTo'){const s=document.querySelector('[data-section="'+m.id+'"]');if(s)s.scrollIntoView({behavior:'smooth',block:'start'});}
    if(m.type==='setAccent'){const c=m.value;document.documentElement.style.setProperty('--c',c);document.documentElement.style.setProperty('--grad','linear-gradient(135deg, '+c+' 0%, color-mix(in srgb, '+c+' 55%, #7c3aed) 100%)');}
  });
  send({type:'ready'});
})();
</script>
@endif
</body>
</html>
