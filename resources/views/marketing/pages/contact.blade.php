@extends('marketing.layout')
@section('content')
@include('marketing.partials.page-head', ['kicker' => 'Contact', 'heading' => 'Parlons de votre site', 'lead' => 'Une question avant de vous lancer, un souci sur votre site, une suggestion ? Nous répondons sous 24 h ouvrées, en français, par un humain.'])
<section class="mx-auto max-w-xl px-5">
    @if(session('status'))<div class="rounded-2xl border border-emerald-400/30 bg-emerald-500/10 p-4 text-sm text-emerald-200">{{ session('status') }}</div>@else
    <form method="post" action="{{ route('marketing.contact.send') }}" class="glass space-y-4 rounded-3xl p-6 sm:p-8">
        @csrf
        <input type="text" name="hp" class="hidden" tabindex="-1" autocomplete="off" aria-hidden="true">
        <label class="block"><span class="mb-1.5 block text-sm font-semibold text-white">Votre nom</span><input name="name" type="text" required class="field" value="{{ old('name') }}"></label>
        <label class="block"><span class="mb-1.5 block text-sm font-semibold text-white">Votre email</span><input name="email" type="email" required class="field" value="{{ old('email') }}"></label>
        <label class="block"><span class="mb-1.5 block text-sm font-semibold text-white">Votre site Joow ou votre site actuel <span class="font-normal text-slate-500">(facultatif)</span></span><input name="website" type="text" class="field" value="{{ old('website') }}" placeholder="mon-site.joow.fr"></label>
        <label class="block"><span class="mb-1.5 block text-sm font-semibold text-white">Votre message</span><textarea name="message" rows="5" required class="field">{{ old('message') }}</textarea></label>
        @error('message')<p class="text-sm text-rose-300">{{ $message }}</p>@enderror
        <button type="submit" class="btn-brand w-full">Envoyer</button>
        <p class="text-center text-xs text-slate-500">Ou directement : <a href="mailto:{{ config('mail.from.address', 'contact@joow.fr') }}" class="text-slate-300 hover:text-white">{{ config('mail.from.address', 'contact@joow.fr') }}</a></p>
    </form>
    @endif
</section>
@include('marketing.partials.cta')
@endsection
