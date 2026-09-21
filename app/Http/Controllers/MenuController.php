<?php

namespace App\Http\Controllers;

use App\Models\MenuItem;
use App\Models\Site;
use App\Services\SiteRenderer;
use Illuminate\Http\Request;

/** Espace client : gestion de la carte (module Menu / Carte). */
class MenuController extends Controller
{
    public function index(Request $request, string $slug)
    {
        $site = $this->ownedSite($request, $slug);

        return response()->json(['items' => $site->menuItems()->get()]);
    }

    public function store(Request $request, string $slug, SiteRenderer $renderer)
    {
        $site = $this->ownedSite($request, $slug);
        $data = $this->validated($request);
        $item = MenuItem::create($data + ['site_id' => $site->id, 'site_slug' => $slug, 'sort_order' => (int) $site->menuItems()->where('category', $data['category'])->max('sort_order') + 1]);
        $this->republish($site, $renderer);

        return response()->json(['item' => $item]);
    }

    public function update(Request $request, string $slug, MenuItem $item, SiteRenderer $renderer)
    {
        $site = $this->ownedSite($request, $slug);
        abort_unless($item->site_slug === $slug, 404);
        $item->update($this->validated($request));
        $this->republish($site, $renderer);

        return response()->json(['item' => $item->fresh()]);
    }

    public function destroy(Request $request, string $slug, MenuItem $item, SiteRenderer $renderer)
    {
        $site = $this->ownedSite($request, $slug);
        abort_unless($item->site_slug === $slug, 404);
        $item->delete();
        $this->republish($site, $renderer);

        return response()->json(['ok' => true]);
    }

    /** Import rapide : une ligne par plat "Catégorie | Nom | Prix | Description". */
    public function import(Request $request, string $slug, SiteRenderer $renderer)
    {
        $site = $this->ownedSite($request, $slug);
        $text = (string) $request->validate(['text' => ['required', 'string', 'max:20000']])['text'];
        $n = 0;
        foreach (preg_split('/\r?\n/', $text) as $line) {
            $p = array_map('trim', explode('|', $line));
            if (count($p) < 2 || $p[1] === '') {
                continue;
            }
            MenuItem::create(['site_id' => $site->id, 'site_slug' => $slug, 'category' => $p[0] ?: 'Plats', 'name' => $p[1], 'price' => isset($p[2]) ? (float) str_replace([',', '€'], ['.', ''], $p[2]) : null, 'description' => $p[3] ?? null, 'sort_order' => $n++]);
        }
        $this->republish($site, $renderer);

        return response()->json(['ok' => true, 'imported' => $n]);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'category' => ['required', 'string', 'max:60'], 'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:500'], 'price' => ['nullable', 'numeric', 'min:0'],
            'available' => ['nullable', 'boolean'], 'sort_order' => ['nullable', 'integer'],
        ]);
    }

    private function republish(Site $site, SiteRenderer $renderer): void
    {
        if (! empty($site->site_data['content'])) {
            $renderer->publish($site);
        }
    }

    private function ownedSite(Request $request, string $slug): Site
    {
        $site = Site::where('slug', $slug)->firstOrFail();
        $user = $request->user();
        abort_unless($user->isAdmin() || ($site->user_id && $site->user_id === $user->id) || ($site->owner_email && strtolower($site->owner_email) === strtolower($user->email)), 403);

        return $site;
    }
}
