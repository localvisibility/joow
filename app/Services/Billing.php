<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Site;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Cashier;

/**
 * Factures Joow : chaque paiement Stripe (abonnement Pro, Liberté, renouvellement) crée une facture
 * dans l'espace client, avec un rendu HTML conforme (mentions GMD COM, HT / TVA / TTC) et le lien
 * vers le PDF Stripe. Idempotent : la facture Stripe est la clé (payment_id).
 */
class Billing
{
    public const PLANS = ['pro' => 'Formule Pro · hébergement et modules, mensuel', 'liberte' => 'Formule Liberté · hébergement et modules, paiement unique', 'ads' => 'Module Joow Ads · publicité et pages d\'offre, mensuel'];

    public const COMPANY = [
        'name' => 'GMD COM', 'legal' => 'SASU au capital de 1 800 €', 'address' => '70 rue Damrémont, 75018 Paris, France',
        'rcs' => 'RCS Paris 882 334 733', 'siret' => '882 334 733 00038', 'vat' => 'FR20 882 334 733', 'brand' => 'Joow', 'email' => 'contact@joow.fr',
    ];

    /** Après un Checkout terminé : récupère la facture Stripe liée à la session et l'enregistre. */
    public function recordFromCheckoutSession(object|array $session, Site $site, string $plan): ?Invoice
    {
        $s = is_array($session) ? $session : $session->toArray();
        $invoiceId = is_array($s['invoice'] ?? null) ? ($s['invoice']['id'] ?? null) : ($s['invoice'] ?? null);
        if (! $invoiceId) {
            return null;
        }
        if ($existing = Invoice::where('payment_id', $invoiceId)->first()) {
            return $existing;
        }
        try {
            $inv = Cashier::stripe()->invoices->retrieve($invoiceId, []);
        } catch (\Throwable $e) {
            Log::warning("Facture Stripe $invoiceId introuvable : ".$e->getMessage());

            return null;
        }

        return $this->recordFromStripeInvoice($inv, $site, $plan);
    }

    /** Enregistre (ou retrouve) la facture Joow correspondant à une facture Stripe payée. */
    public function recordFromStripeInvoice(object|array $inv, ?Site $site = null, ?string $plan = null): ?Invoice
    {
        $i = is_array($inv) ? $inv : $inv->toArray();
        $id = (string) ($i['id'] ?? '');
        if ($id === '' || (int) ($i['amount_paid'] ?? $i['total'] ?? 0) <= 0) {
            return null;
        }
        if ($existing = Invoice::where('payment_id', $id)->first()) {
            return $existing;
        }

        $total = (int) ($i['total'] ?? $i['amount_paid'] ?? 0);
        $tax = (int) ($i['tax'] ?? 0);
        $ht = ($total - $tax) / 100;
        $rate = $ht > 0 && $tax > 0 ? round($tax / 100 / $ht * 100, 2) : 0.0;
        $currency = strtoupper((string) ($i['currency'] ?? 'eur'));
        $email = (string) ($i['customer_email'] ?? $site?->owner_email ?? '');
        $name = (string) ($i['customer_name'] ?? $site?->owner_name ?? '');
        $plan = $plan ?: $this->planFromInvoice($i, $site);
        $period = $this->period($i);
        $label = (self::PLANS[$plan] ?? 'Prestation Joow').($period ? ' · '.$period : '');
        $issued = ! empty($i['status_transitions']['paid_at']) ? now()->setTimestamp((int) $i['status_transitions']['paid_at']) : (! empty($i['created']) ? now()->setTimestamp((int) $i['created']) : now());
        $number = $this->nextNumber($issued->year);

        $invoice = Invoice::create([
            'invoice_number' => $number,
            'site_slug'      => $site?->slug,
            'client_email'   => $email,
            'client_name'    => $name ?: null,
            'module_name'    => $label,
            'price_ht'       => round($ht, 2),
            'tva_rate'       => $rate,
            'currency'       => $currency,
            'payment_id'     => $id,
            'status'         => 'paid',
            'issued_at'      => $issued,
        ]);
        $invoice->update(['html' => $this->html($invoice, $site, [
            'stripe_number' => $i['number'] ?? null, 'pdf' => $i['invoice_pdf'] ?? null, 'hosted' => $i['hosted_invoice_url'] ?? null,
            'period' => $period, 'site_url' => $site ? 'https://'.$site->slug.'.joow.fr' : null,
        ])]);

        return $invoice;
    }

    /** Numéro séquentiel par année : JOOW-2026-00001. */
    public function nextNumber(int $year): string
    {
        $prefix = "JOOW-$year-";
        $last = Invoice::where('invoice_number', 'like', $prefix.'%')->orderByDesc('invoice_number')->value('invoice_number');
        $n = $last ? (int) substr($last, strlen($prefix)) + 1 : 1;

        return $prefix.str_pad((string) $n, 5, '0', STR_PAD_LEFT);
    }

    private function planFromInvoice(array $i, ?Site $site): string
    {
        if ($site?->hosting_plan) {
            return $site->hosting_plan;
        }

        return ! empty($i['subscription']) ? 'pro' : 'liberte';
    }

    /** « octobre 2026 » pour un abonnement, à partir de la période de la première ligne. */
    private function period(array $i): ?string
    {
        $line = $i['lines']['data'][0] ?? null;
        $start = $line['period']['start'] ?? null;
        $end = $line['period']['end'] ?? null;
        if (! $start || ! $end || empty($i['subscription'])) {
            return null;
        }
        $a = now()->setTimestamp((int) $start);
        $b = now()->setTimestamp((int) $end);

        return 'du '.$a->translatedFormat('j F Y').' au '.$b->translatedFormat('j F Y');
    }

    /** Rendu HTML imprimable de la facture (mentions légales, HT / TVA / TTC, lien PDF Stripe). */
    public function html(Invoice $inv, ?Site $site, array $x = []): string
    {
        $c = self::COMPANY;
        $e = fn ($v) => e((string) $v);
        $money = fn ($v) => number_format((float) $v, 2, ',', ' ').' '.($inv->currency === 'EUR' ? '€' : $inv->currency);
        $tva = (float) $inv->tva_rate;
        $tvaLine = $tva > 0 ? "<tr><td>TVA ({$e(number_format($tva, 1, ',', ' '))} %)</td><td class=\"r\">{$e($money($inv->amount_ttc - (float) $inv->price_ht))}</td></tr>" : '<tr><td>TVA</td><td class="r">0,00 € · facturée hors taxe, TVA réglée selon le régime applicable</td></tr>';
        $pdf = ! empty($x['pdf']) ? "<p class=\"muted\">Exemplaire Stripe : <a href=\"{$e($x['pdf'])}\">télécharger le PDF</a>".(! empty($x['stripe_number']) ? " · n° Stripe {$e($x['stripe_number'])}" : '').'</p>' : '';
        $siteLine = ! empty($x['site_url']) ? "<p class=\"muted\">Site concerné : {$e($x['site_url'])}</p>" : '';

        return <<<HTML
<!doctype html><html lang="fr"><head><meta charset="utf-8"><title>Facture {$e($inv->invoice_number)}</title>
<style>body{font-family:Inter,Helvetica,Arial,sans-serif;color:#111;margin:0;padding:48px;max-width:820px}h1{font-size:28px;margin:0 0 4px}.muted{color:#666;font-size:13px}.grid{display:flex;justify-content:space-between;gap:40px;margin:36px 0}.box{flex:1}.box h3{font-size:12px;letter-spacing:.15em;text-transform:uppercase;color:#888;margin:0 0 8px}table{width:100%;border-collapse:collapse;margin-top:24px}th,td{padding:12px 10px;border-bottom:1px solid #e5e5e5;text-align:left;font-size:14px}th{font-size:12px;letter-spacing:.1em;text-transform:uppercase;color:#888}.r{text-align:right}.total td{font-weight:700;font-size:16px;border-top:2px solid #111}.foot{margin-top:40px;font-size:12px;color:#666;line-height:1.6}@media print{body{padding:24px}a{color:#111}}</style></head>
<body>
<h1>Facture {$e($inv->invoice_number)}</h1>
<p class="muted">Émise le {$e(optional($inv->issued_at)->format('d/m/Y'))} · Acquittée · Paiement par carte bancaire via Stripe</p>
<div class="grid">
  <div class="box"><h3>Émetteur</h3><strong>{$e($c['name'])}</strong> ({$e($c['brand'])})<br>{$e($c['legal'])}<br>{$e($c['address'])}<br>{$e($c['rcs'])} · SIRET {$e($c['siret'])}<br>TVA intracommunautaire {$e($c['vat'])}<br>{$e($c['email'])}</div>
  <div class="box"><h3>Client</h3><strong>{$e($inv->client_name ?: $inv->client_email)}</strong><br>{$e($inv->client_email)}{$siteLine}</div>
</div>
<table><thead><tr><th>Désignation</th><th class="r">Montant HT</th></tr></thead>
<tbody><tr><td>{$e($inv->module_name)}</td><td class="r">{$e($money($inv->price_ht))}</td></tr>
{$tvaLine}
<tr class="total"><td>Total TTC</td><td class="r">{$e($money($inv->amount_ttc))}</td></tr></tbody></table>
{$pdf}
<div class="foot">Facture réglée intégralement à la date d'émission. Formule Pro : abonnement mensuel sans engagement, résiliable à tout moment depuis l'espace client. Formule Liberté : paiement unique. En cas de retard de paiement entre professionnels : indemnité forfaitaire de recouvrement de 40 € et pénalités au taux légal. Conditions générales : https://joow.fr/cgv</div>
</body></html>
HTML;
    }

    /** Compte à qui rattacher une facture (le propriétaire du site, créé si besoin). */
    public function owner(Site $site): ?User
    {
        if ($site->user) {
            return $site->user;
        }
        if ($site->owner_email) {
            [$user] = app(Accounts::class)->ensure($site->owner_email, [$site->slug], 'checkout');

            return $user;
        }

        return null;
    }
}
