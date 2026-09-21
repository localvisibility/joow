<?php

namespace App\Http\Controllers;

use App\Models\DomainRequest;
use App\Models\Site;
use App\Services\Modules\BotAnswer;
use App\Services\Modules\LegalGenerator;
use App\Services\Modules\ReservationAvailability;
use App\Services\SiteRenderer;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Espace client : catalogue et configuration des modules d'un site.
 */
class ModulesController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $sites = $this->userSites($request)->get(['slug', 'name', 'city', 'sector', 'status', 'modules']);
        $slug = $request->query('site') ?: $sites->first()?->slug;
        $site = $slug ? $sites->firstWhere('slug', $slug) : null;

        return Inertia::render('Modules', [
            'sites'   => $sites->map(fn ($s) => ['slug' => $s->slug, 'name' => $s->name, 'city' => $s->city, 'sector' => $s->sector]),
            'current' => $site?->slug,
            'sector'  => $site?->sector,
            'catalog' => config('modules'),
            'state'   => $site ? $this->stateOf($site) : null,
        ]);
    }

    /** Active/désactive ou configure un module, puis republie le site. */
    public function update(Request $request, string $slug, SiteRenderer $renderer)
    {
        $site = $this->ownedSite($request, $slug);
        $data = $request->validate([
            'module' => ['required', 'string', 'max:30'],
            'config' => ['nullable', 'array'],
        ]);
        abort_unless(config("modules.{$data['module']}"), 422, 'Module inconnu');

        $cfg = $this->sanitize($data['module'], $data['config'] ?? []);
        $site->setModule($data['module'], $cfg);

        if (! empty($site->site_data['content'])) {
            $renderer->publish($site);
        }

        return back()->with('ok', true);
    }

    /** Demande de nom de domaine (connexion ou commande). */
    public function domain(Request $request, string $slug)
    {
        $site = $this->ownedSite($request, $slug);
        $data = $request->validate([
            'domain' => ['required', 'string', 'max:120', 'regex:/^[a-z0-9.-]+\.[a-z]{2,}$/i'],
            'type'   => ['required', 'in:connect,order'],
            'notes'  => ['nullable', 'string', 'max:500'],
        ]);
        DomainRequest::create([
            'site_id' => $site->id, 'site_slug' => $slug, 'domain' => strtolower($data['domain']),
            'type' => $data['type'], 'contact_email' => $request->user()->email, 'notes' => $data['notes'] ?? null,
        ]);
        $site->update(['custom_domain' => strtolower($data['domain']), 'domain_verified' => false]);

        return back()->with('ok', true);
    }

    // ───────────────────────── helpers ─────────────────────────

    private function stateOf(Site $site): array
    {
        $modules = [];
        foreach (array_keys(config('modules')) as $key) {
            $modules[$key] = array_merge($this->defaultsFor($key), $site->module($key), ['enabled' => $site->moduleEnabled($key)]);
        }

        return [
            'modules'   => $modules,
            'business'  => \Illuminate\Support\Arr::only($site->site_data['business'] ?? [], ['name', 'phone', 'email', 'address', 'rating', 'reviews_count']),
            'menu_count' => $site->menuItems()->count(),
            'rooms_count' => $site->rooms()->count(),
            'domain'    => ['custom' => $site->custom_domain, 'verified' => (bool) $site->domain_verified, 'requests' => DomainRequest::where('site_slug', $site->slug)->latest()->take(3)->get(['domain', 'type', 'status', 'created_at'])],
            'stripe'    => [
                'configured'      => (bool) config('cashier.secret'),
                'connected'       => (bool) $site->stripe_account_id,
                'charges_enabled' => (bool) $site->stripe_charges_enabled,
            ],
            'sms_enabled' => (bool) config('services.brevo.key'),
            'live_url'  => 'https://'.$site->slug.'.joow.fr',
        ];
    }

    private function defaultsFor(string $key): array
    {
        return match ($key) {
            'restaurant' => ReservationAvailability::defaults(),
            'legal'      => LegalGenerator::defaults(),
            'bot'        => BotAnswer::defaults(),
            'whatsapp'   => ['enabled' => false, 'number' => '', 'message' => 'Bonjour, je souhaite avoir des informations.', 'position' => 'right'],
            'reviews'    => ['enabled' => true, 'min_rating' => 4, 'count' => 6],
            'rooms'      => ['enabled' => false, 'notify_email' => '', 'intro' => ''],
            'menu'       => ['enabled' => false, 'title' => 'Notre carte', 'qr' => true],
            'zenchef'    => ['enabled' => false, 'restaurant_id' => ''],
            'payment'    => ['enabled' => false, 'type' => 'deposit', 'deposit_percent' => 30, 'hold_per_cover' => 10, 'notify_email' => ''],
            'booking'    => ['enabled' => true],
            default      => ['enabled' => false],
        };
    }

    private function sanitize(string $key, array $cfg): array
    {
        $allowed = array_keys($this->defaultsFor($key));
        if ($key === 'restaurant') {
            $allowed[] = 'hours';
        }
        $out = [];
        foreach ($cfg as $k => $v) {
            if (! in_array($k, $allowed, true)) {
                continue;
            }
            $out[$k] = is_string($v) ? mb_substr(strip_tags($v), 0, 4000) : $v;
        }
        if (isset($cfg['enabled'])) {
            $out['enabled'] = (bool) $cfg['enabled'];
        }
        if ($key === 'whatsapp' && ! empty($out['number'])) {
            $out['number'] = preg_replace('/\D+/', '', $out['number']);
            if (str_starts_with($out['number'], '0')) {
                $out['number'] = '33'.substr($out['number'], 1);
            }
        }

        return $out;
    }

    private function userSites(Request $request)
    {
        $user = $request->user();

        return $user->isAdmin()
            ? Site::query()->orderByDesc('created_at')
            : Site::query()->where(fn ($q) => $q->where('user_id', $user->id)->orWhere('owner_email', $user->email))->orderByDesc('created_at');
    }

    private function ownedSite(Request $request, string $slug): Site
    {
        $site = Site::where('slug', $slug)->firstOrFail();
        $user = $request->user();
        abort_unless($user->isAdmin() || ($site->user_id && $site->user_id === $user->id) || ($site->owner_email && strtolower($site->owner_email) === strtolower($user->email)), 403);

        return $site;
    }
}
