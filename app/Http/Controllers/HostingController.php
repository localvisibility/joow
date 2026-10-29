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
            ->get(['slug', 'name', 'city', 'status', 'hosting_plan', 'subscription_status', 'subscription_period_end', 'paid_at'])
            ->map(fn ($s) => [
                'sub'     => $s->subscription_status,
                'period_end' => optional($s->subscription_period_end)->toDateString(),
                'paid_at' => optional($s->paid_at)->toDateString(),
                'slug'    => $s->slug,
                'name'    => $s->name,
                'city'    => $s->city,
                'status'  => $s->status,
                'plan'    => $s->hosting_plan,
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
