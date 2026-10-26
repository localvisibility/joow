<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateSiteJob;
use App\Models\GenerationJob;
use App\Models\Site;
use App\Services\SiteImporter;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Refonte d'un site existant : l'adresse suffit. On vérifie que le site se lit, puis la génération
 * (lecture des pages, fiche Google, rédaction, pages internes) se fait en tâche de fond.
 */
class RedesignController extends Controller
{
    public function start(Request $request, SiteImporter $importer)
    {
        $data = $request->validate([
            'url'   => ['required', 'string', 'max:300'],
            'email' => ['nullable', 'email:rfc', 'max:190'],
        ]);

        $final = $importer->probe($data['url']);
        if (! $final) {
            // Illisible pour un robot (site bloqué, entièrement JavaScript, hors ligne) : on bascule sur le questionnaire
            $host = preg_replace('/^www\./', '', (string) parse_url(Str::startsWith($data['url'], 'http') ? $data['url'] : 'https://'.$data['url'], PHP_URL_HOST));

            return redirect()->route('intake.create', ['q' => Str::title(str_replace(['-', '_'], ' ', explode('.', (string) $host)[0])), 'email' => $data['email'] ?? null, 'from_url' => $host])
                ->with('notice', 'Nous n\'avons pas pu lire ce site automatiquement. Répondez à quelques questions et nous créons votre nouveau site.');
        }

        $host = preg_replace('/^www\./', '', (string) parse_url($final, PHP_URL_HOST));
        $base = Str::slug(explode('.', $host)[0]) ?: 'site';
        $slug = $base.'-'.Str::lower(Str::random(4));

        $site = Site::create([
            'slug'        => $slug,
            'name'        => Str::title(str_replace(['-', '_'], ' ', explode('.', $host)[0])),
            'sector'      => 'service',
            'status'      => 'generating',
            'place_id'    => null,
            'owner_email' => $data['email'] ?? null,
            'source'      => 'refonte',
            'preview_url' => 'https://'.$slug.'.joow.fr',
            'site_data'   => ['import' => ['url' => $final, 'host' => $host]],
        ]);

        $job = GenerationJob::create([
            'status'    => 'pending',
            'sector'    => 'service',
            'site_slug' => $slug,
            'input'     => ['url' => $final, 'email' => $data['email'] ?? null],
        ]);
        GenerateSiteJob::dispatch($site, $job->id);
        $request->session()->push('joow_sites', $slug);
        if (! empty($data['email'])) {
            app(\App\Services\Accounts::class)->ensure($data['email'], $request->session()->get('joow_sites', []), 'refonte');
        }

        return redirect()->route('public.site', $slug);
    }
}
