<?php

namespace App\Http\Controllers;

use App\Models\Site;
use App\Services\SupportBot;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;

/** Onglet Support du tableau de bord : aide, assistant IA, message à l'équipe. */
class SupportController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        return Inertia::render('Support', [
            'sites' => Site::where(fn ($q) => $q->where('user_id', $user->id)->orWhere('owner_email', $user->email))->latest()->take(20)->get(['slug', 'name'])->map->only(['slug', 'name']),
            'tickets' => DB::table('support_tickets')->where('user_id', $user->id)->latest()->take(10)->get(['id', 'subject', 'status', 'created_at']),
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

    public function contact(Request $request)
    {
        $user = $request->user();
        $data = $request->validate(['subject' => ['required', 'string', 'max:160'], 'message' => ['required', 'string', 'max:5000'], 'site' => ['nullable', 'string', 'max:120']]);
        $id = DB::table('support_tickets')->insertGetId(['user_id' => $user->id, 'site_slug' => $data['site'] ?? null, 'subject' => $data['subject'], 'message' => $data['message'], 'created_at' => now(), 'updated_at' => now()]);
        $body = "Message de {$user->name} <{$user->email}>".(! empty($data['site']) ? "\nSite : https://{$data['site']}.joow.fr" : '')."\n\n{$data['message']}\n\n— Ticket #{$id} · back-office : ".route('admin.index', ['tab' => 'support']);
        foreach (array_filter((array) config('joow.admins')) ?: ['contact@joow.fr'] as $to) {
            try {
                Mail::raw($body, fn ($m) => $m->to($to)->replyTo($user->email, $user->name)->subject("Support Joow #{$id} : {$data['subject']}"));
            } catch (\Throwable $e) {
                Log::warning('Support : '.$e->getMessage());
            }
        }

        return back()->with('status', 'Message envoyé : nous vous répondons par email sous 24 h ouvrées.');
    }
}
