<?php

namespace App\Http\Controllers;

use App\Models\Site;
use Illuminate\Http\Request;
use Inertia\Inertia;

class HostingController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $sites = Site::query()
            ->where(fn ($q) => $q->where('user_id', $user->id)->orWhere('owner_email', $user->email))
            ->orderByDesc('created_at')
            ->get(['slug', 'name', 'city', 'status', 'hosting_plan', 'subscription_status', 'subscription_period_end', 'paid_at', 'site_data', 'custom_domain'])
            ->map(fn ($s) => [
                'sub'     => $s->subscription_status,
                'period_end' => optional($s->subscription_period_end)->toDateString(),
                'paid_at' => optional($s->paid_at)->toDateString(),
                'slug'    => $s->slug,
                'name'    => $s->name,
                'city'    => $s->city,
                'status'  => $s->status,
                'plan'    => $s->hosting_plan,
                // Formule historique (ancien compte Stripe Local Visibility) : affichée telle quelle
                'legacy'  => $s->hosting_plan === 'historique' ? ($s->site_data['legacy_plan'] ?? ['label' => 'Formule historique']) : null,
                'url'     => $s->custom_domain ? 'https://'.$s->custom_domain : 'https://'.$s->slug.'.joow.fr',
                'online'  => in_array($s->status, ['paid', 'published'], true),
            ]);

        $sub = $user->subscription('hosting');

        return Inertia::render('Hosting', [
            'sites'      => $sites,
            'cancelAtEnd' => (bool) $sub?->onGracePeriod(),
            'subEndsAt'  => optional($sub?->ends_at)->toDateString(),
            'userEmail'  => $user->email,
        ]);
    }

    /** Portail de l'ancien compte Stripe (formule historique) : carte bancaire, factures, résiliation. */
    public function legacyPortal(Request $request, string $slug, \App\Services\LegacyStripe $stripe)
    {
        $user = $request->user();
        $site = Site::where('slug', $slug)->where(fn ($q) => $q->where('user_id', $user->id)->orWhere('owner_email', $user->email))->firstOrFail();
        $url = $stripe->portal($site, route('hosting.index'));

        return $url ? redirect()->away($url)
            : redirect()->route('hosting.index')->withErrors(['email' => 'La gestion de cet abonnement est momentanément indisponible. Écrivez-nous à contact@joow.fr.']);
    }

    /** Espace de facturation Stripe : carte bancaire, factures, résiliation de l'abonnement. */
    public function portal(Request $request)
    {
        $user = $request->user();
        if (! $user->stripe_id) {
            return redirect()->route('hosting.index')->withErrors(['email' => 'Aucun abonnement associé à ce compte.']);
        }
        try {
            return $user->redirectToBillingPortal(route('hosting.index'));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Portail Stripe : '.$e->getMessage());

            return redirect()->route('hosting.index')->withErrors(['email' => 'La gestion de l\'abonnement est momentanément indisponible. Écrivez-nous à contact@joow.fr.']);
        }
    }
}
