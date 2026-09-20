<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Site;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class InvoicesController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $isAdmin = $user->isAdmin();

        $query = Invoice::query()->orderByDesc('issued_at');
        if (! $isAdmin) {
            $slugs = Site::where('user_id', $user->id)->orWhere('owner_email', $user->email)->pluck('slug');
            $query->where(fn ($q) => $q->where('client_email', $user->email)->orWhereIn('site_slug', $slugs));
        }

        $invoices = $query->take(300)->get()->map(fn ($i) => [
            'id'         => $i->id,
            'number'     => $i->invoice_number,
            'label'      => $i->module_name,
            'site_slug'  => $i->site_slug,
            'price_ht'   => (float) $i->price_ht,
            'tva_rate'   => (float) $i->tva_rate,
            'amount_ttc' => $i->amount_ttc,
            'currency'   => $i->currency,
            'status'     => $i->status,
            'issued_at'  => $i->issued_at,
            'has_pdf'    => ! empty($i->html),
        ]);

        $totalTtc = $invoices->where('status', 'paid')->sum('amount_ttc');

        return Inertia::render('Invoices', [
            'invoices' => $invoices->values(),
            'totalTtc' => round($totalTtc, 2),
            'isAdmin'  => $isAdmin,
        ]);
    }

    /** Affiche/télécharge la facture (HTML stocké). */
    public function show(Request $request, Invoice $invoice): Response
    {
        $user = $request->user();
        if (! $user->isAdmin()) {
            $owns = $invoice->client_email === $user->email
                || Site::where('slug', $invoice->site_slug)
                    ->where(fn ($q) => $q->where('user_id', $user->id)->orWhere('owner_email', $user->email))
                    ->exists();
            abort_unless($owns, 403);
        }

        $html = $invoice->html ?: '<!doctype html><meta charset="utf-8"><body style="font-family:sans-serif;padding:40px">'
            .'<h1>Facture '.$invoice->invoice_number.'</h1>'
            .'<p>'.$invoice->module_name.'</p>'
            .'<p>Montant TTC : '.number_format($invoice->amount_ttc, 2, ',', ' ').' '.$invoice->currency.'</p>'
            .'<p>Émise le '.optional($invoice->issued_at)->format('d/m/Y').'</p></body>';

        return response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }
}
