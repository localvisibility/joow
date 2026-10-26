@extends('marketing.layout')
@push('jsonld')<script type="application/ld+json">{!! json_encode(['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => array_map(fn ($f) => ['@type' => 'Question', 'name' => $f['q'], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f['a']]], $faq)], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>@endpush
@section('content')
@include('marketing.partials.page-head', ['kicker' => 'FAQ', 'heading' => 'Questions fréquentes', 'lead' => 'Tout ce que l\'on nous demande avant de se lancer. Une autre question ? Écrivez-nous, nous répondons sous 24 h ouvrées.'])
<section class="mx-auto max-w-3xl px-5"><div class="space-y-3">@foreach($faq as $f)<details class="reveal glass group rounded-2xl p-5" {{ $loop->first ? 'open' : '' }}><summary class="flex cursor-pointer list-none items-center justify-between gap-4 font-semibold text-white"><span>{{ $f['q'] }}</span><span class="text-slate-500 transition group-open:rotate-45">+</span></summary><p class="mt-3 text-slate-400">{{ $f['a'] }}</p></details>@endforeach</div><p class="mt-8 text-center text-slate-400">Pas trouvé ? <a href="{{ route('marketing.contact') }}" class="font-semibold text-brand-400 hover:text-white">Contactez-nous</a>.</p></section>
@include('marketing.partials.cta')
@endsection
