{{-- Section « sec-reviews » : accueil (ordre + édition par section) ou insérée dans une page via un bloc « module » ($inPage) --}}
<section id="avis" class="py-24" {!! empty($inPage) ? 'style="order:'.$ord('reviews').'" data-section="reviews" data-label="Avis"' : '' !!}>
  <div class="mx-auto max-w-6xl px-5">
    <div class="reveal mb-12 text-center">
      <p class="text-sm font-bold uppercase tracking-[0.3em] accent">Témoignages</p>
      <h2 class="mt-3 font-display text-4xl font-bold sm:text-5xl" data-edit="content.reviews_title">{{ $t('reviews_title', 'Ils nous recommandent') }}</h2>
    </div>
    @if(!empty($b['rating']))
    <div class="reveal rating-big mb-12">
      <div class="text-center"><p class="rating-num" data-count>{{ $b['rating'] }}</p><p class="mt-1 text-2xl text-amber-500">{{ $stars($b['rating']) }}</p></div>
      <div class="text-left">
        <p class="flex items-center gap-2 font-display text-xl font-bold"><span class="grid h-7 w-7 place-items-center rounded-full bg-white text-sm font-black text-[#4285f4] shadow ring-1 ring-slate-200">G</span> {{ $b['reviews_count'] ?? 0 }} avis Google</p>
        <p class="mt-1 text-slate-500">Note moyenne authentique, mise à jour depuis notre fiche Google.</p>
        <div class="mt-3 h-2 w-64 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-grad" style="width:{{ min(100, round(((float) $b['rating']) / 5 * 100)) }}%"></div></div>
      </div>
    </div>
    @endif
  </div>
  @php $revCards = $reviews->take(8)->values(); $twoRows = $revCards->count() >= 4; $rowA = $twoRows ? $revCards->filter(fn($r, $i) => $i % 2 === 0)->values() : $revCards; $rowB = $twoRows ? $revCards->filter(fn($r, $i) => $i % 2 === 1)->values() : collect(); @endphp
  @foreach([[$rowA, 'rev-left'], [$rowB, 'rev-right']] as [$row, $dir])
  @if($row->count())
  <div class="rev-mask reveal mt-5">
    <div class="rev-track {{ $editMode ? '' : $dir }}">
      @foreach(($editMode ? $row : $row->concat($row)) as $r)
      <figure class="rev">
        <span class="q">"</span>
        <div class="text-amber-500">{{ $stars($r['rating'] ?? 5) }}</div>
        <blockquote class="mt-3 text-slate-600">{{ Str::limit($r['text'], 200) }}</blockquote>
        <figcaption class="mt-5 flex items-center gap-3">
          <span class="grid h-10 w-10 place-items-center rounded-full bg-grad font-display font-bold text-white">{{ $initial($r['author'] ?? 'C') }}</span>
          <span><span class="block font-bold text-slate-800">{{ $r['author'] ?: 'Client' }}</span><span class="text-xs text-slate-400"><i class="fa-brands fa-google mr-1"></i>Avis Google</span></span>
        </figcaption>
      </figure>
      @endforeach
    </div>
  </div>
  @endif
  @endforeach
</section>
