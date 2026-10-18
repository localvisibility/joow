{{-- Section « sec-menu » : accueil (ordre + édition par section) ou insérée dans une page via un bloc « module » ($inPage) --}}
<section id="carte" class="bg-slate-50 py-24" {!! empty($inPage) ? 'style="order:'.$ord('menu').'" data-section="menu" data-label="Carte"' : '' !!}>
  <div class="mx-auto max-w-5xl px-5">
    <div class="reveal mb-12 flex flex-wrap items-end justify-between gap-6">
      <div>
        <p class="text-sm font-bold uppercase tracking-[0.3em] accent">Menu</p>
        <h2 class="mt-3 font-display text-4xl font-bold sm:text-5xl">{{ $menuCfg['title'] ?? 'Notre carte' }}</h2>
      </div>
      @if(!empty($menuCfg['qr']) && !$editMode)
      <div class="text-center"><img src="https://api.qrserver.com/v1/create-qr-code/?size=110x110&data={{ urlencode('https://'.$slug.'.joow.fr/#carte') }}" class="mx-auto h-24 w-24 rounded-xl border border-slate-200 bg-white p-1" alt="QR code de la carte" loading="lazy"><p class="mt-1 text-[11px] text-slate-500">Carte sur mobile</p></div>
      @endif
    </div>
    <div class="grid gap-8 md:grid-cols-2">
      @php $catOrder = ['menus', 'formules', 'entrées', 'entrees', 'plats', 'poissons', 'viandes', 'pizzas', 'burgers', 'desserts', 'fromages', 'boissons', 'vins', 'cocktails']; @endphp
      @foreach($menuItems->groupBy('category')->sortBy(fn($g, $cat) => (($i = array_search(mb_strtolower($cat), $catOrder, true)) === false ? 50 : $i)) as $cat => $items)
      <div class="reveal rounded-3xl border border-slate-100 bg-white p-7 shadow-sm">
        <h3 class="font-display text-2xl font-bold accent">{{ $cat }}</h3>
        <ul class="mt-5 divide-y divide-slate-100">
          @foreach($items as $it)
          <li class="flex items-start justify-between gap-4 py-3">
            <div><p class="font-semibold text-slate-800">{{ $it->name }}</p>@if($it->description)<p class="text-sm text-slate-500">{{ $it->description }}</p>@endif</div>
            @if($it->price !== null)<span class="shrink-0 font-display font-bold">{{ number_format((float) $it->price, 2, ',', ' ') }} €</span>@endif
          </li>
          @endforeach
        </ul>
      </div>
      @endforeach
    </div>
  </div>
</section>
