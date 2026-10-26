@extends('marketing.layout')
@section('content')
@include('marketing.partials.page-head', ['kicker' => count($sectors).' métiers', 'heading' => 'Un site pensé pour <span class="text-shimmer">votre</span> métier', 'lead' => 'Un restaurant ne se présente pas comme un plombier, un cabinet médical comme une agence web. Chaque métier a son modèle : sections, ton, couleurs, photos et modules adaptés à ce que cherchent ses clients.'])
<section class="mx-auto max-w-7xl px-5 pb-10">
    <div class="stagger grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
        @foreach($sectors as $s)@if($s['key'] !== 'service')
        <a href="{{ route('marketing.sector', $s['key']) }}" class="reveal glass glass-hover group flex items-center gap-4 rounded-2xl p-4"><span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl text-white" style="background: {{ $s['color'] }}"><i class="fa-solid {{ $s['icon'] }}"></i><span class="sr-only"></span></span><span class="min-w-0"><span class="block truncate font-semibold text-white group-hover:text-brand-400">{{ $s['label'] }}</span><span class="block text-xs text-slate-500">{{ $s['cta'] }}</span></span></a>
        @endif
        @endforeach
    </div>
</section>
@include('marketing.partials.cta', ['title' => 'Votre métier n\'est pas dans la liste ?', 'text' => 'Décrivez-le en quelques mots dans le questionnaire : l\'IA s\'adapte et choisit le modèle le plus proche.'])
@endsection
