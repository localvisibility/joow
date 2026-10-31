<?php

namespace App\Http\Controllers;

use App\Models\Site;
use App\Services\SupportBot;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/** Onglet Support du tableau de bord : aide, assistant IA, message à l'équipe. */
class SupportController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        return Inertia::render('Support', [
            'sites' => Site::where(fn ($q) => $q->where('user_id', $user->id)->orWhere('owner_email', $user->email))->latest()->take(20)->get(['slug', 'name'])->map->only(['slug', 'name']),
            'tickets' => DB::table('support_tickets')->where('user_id', $user->id)->orderByDesc('updated_at')->take(10)->get()
                ->map(fn ($t) => ['id' => $t->id, 'subject' => $t->subject, 'status' => $t->status, 'thread' => app(\App\Services\SupportDesk::class)->thread($t)]),
        ]);
    }

    public function ask(Request $request, SupportBot $bot)
    {
        $data = $request->validate(['message' => ['required', 'string', 'max:1000'], 'history' => ['nullable', 'array', 'max:20']]);
        $out = $bot->answer($request->user(), $data['message'], (array) ($data['history'] ?? []));
        $id = DB::table('support_questions')->insertGetId([
            'user_id' => $request->user()->id, 'question' => $data['message'], 'answer' => $out['answer'], 'escalate' => $out['escalate'],
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return response()->json($out + ['id' => $id]);
    }

    public function feedback(Request $request, int $id)
    {
        $data = $request->validate(['helpful' => ['required', 'boolean']]);
        DB::table('support_questions')->where('id', $id)->where('user_id', $request->user()->id)->update(['helpful' => $data['helpful'], 'updated_at' => now()]);

        return response()->json(['ok' => true]);
    }

    public function contact(Request $request, \App\Services\SupportDesk $desk)
    {
        $data = $request->validate(['subject' => ['required', 'string', 'max:160'], 'message' => ['required', 'string', 'max:5000'], 'site' => ['nullable', 'string', 'max:120']]);
        $desk->create($request->user(), $data['subject'], $data['message'], ($data['site'] ?? null) ?: null);

        return back()->with('status', 'Message envoyé : nous vous répondons ici et par email sous 24 h ouvrées.');
    }

    public function reply(Request $request, int $id, \App\Services\SupportDesk $desk)
    {
        $data = $request->validate(['message' => ['required', 'string', 'max:5000']]);
        $desk->clientReply($id, $request->user(), $data['message']);

        return back()->with('status', 'Réponse envoyée.');
    }
}
