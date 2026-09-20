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
            ->get(['slug', 'name', 'city', 'status', 'hosting_plan'])
            ->map(fn ($s) => [
                'slug'    => $s->slug,
                'name'    => $s->name,
                'city'    => $s->city,
                'status'  => $s->status,
                'plan'    => $s->hosting_plan,
                'online'  => in_array($s->status, ['paid', 'published'], true),
            ]);

        return Inertia::render('Hosting', [
            'sites'      => $sites,
            'userEmail'  => $user->email,
        ]);
    }
}
