<?php

namespace App\Http\Controllers;

use App\Models\User;
use Inertia\Inertia;

class EmailPreferencesController extends Controller
{
    /** Lien signé présent dans chaque email de conseil : stoppe les relances, pas les emails indispensables. */
    public function unsubscribe(User $user)
    {
        if (! $user->marketing_optout_at) {
            $user->forceFill(['marketing_optout_at' => now()])->save();
        }

        return Inertia::render('Public/Unsubscribed', ['email' => $user->email]);
    }
}
