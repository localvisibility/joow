{{-- Section « sec-rooms » : accueil (ordre + édition par section) ou insérée dans une page via un bloc « module » ($inPage) --}}
@php $rmCfg = $mod('rooms'); @endphp
<section id="sejour" class="py-24" {!! empty($inPage) ? 'style="order:'.$ord('rooms').'" data-section="rooms" data-label="Chambres"' : '' !!}>
  <div class="mx-auto max-w-6xl px-5">
    <div class="reveal mb-12 max-w-2xl">
      <p class="text-sm font-bold uppercase tracking-[0.3em] accent">Hébergement</p>
      <h2 class="mt-3 font-display text-4xl font-bold sm:text-5xl">{{ $rmCfg['title'] ?? 'Nos chambres' }}</h2>
      @if(!empty($rmCfg['intro']))<p class="mt-4 text-lg text-slate-500">{{ $rmCfg['intro'] }}</p>@endif
    </div>
    <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
      @foreach($rooms as $rm)
      @php $ph = $rm->photos[0] ?? ($photos[($loop->index + 1) % max(1, count($photos))] ?? $hero); @endphp
      <div class="reveal card-hover overflow-hidden rounded-3xl border border-slate-100 bg-white shadow-sm hover:shadow-2xl">
        <img src="{{ $ph }}" class="h-52 w-full object-cover" alt="{{ $rm->name }}" loading="lazy">
        <div class="p-6">
          <div class="flex items-start justify-between gap-3">
            <h3 class="font-display text-xl font-bold">{{ $rm->name }}</h3>
            @if($rm->price_night)<span class="shrink-0 rounded-full bg-grad px-3 py-1 text-sm font-bold text-white">{{ number_format((float) $rm->price_night, 0, ',', ' ') }} €<span class="font-normal opacity-80">/nuit</span></span>@endif
          </div>
          <p class="mt-1 text-sm text-slate-500"><i class="fa-solid fa-user-group accent mr-1"></i>{{ $rm->capacity }} personne{{ $rm->capacity > 1 ? 's' : '' }}</p>
          @if($rm->description)<p class="mt-3 text-sm text-slate-600">{{ $rm->description }}</p>@endif
          @if($rm->amenities)<div class="mt-4 flex flex-wrap gap-1.5">@foreach(array_slice($rm->amenities, 0, 6) as $am)<span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs text-slate-600">{{ $am }}</span>@endforeach</div>@endif
          <button type="button" class="mt-5 w-full rounded-xl border-2 border-accent px-4 py-2.5 text-sm font-bold accent transition hover:bg-accent hover:text-white" onclick="joowPickRoom('{{ $rm->id }}')">Réserver cette chambre</button>
        </div>
      </div>
      @endforeach
    </div>

    <div class="reveal mt-14 rounded-[2rem] border border-slate-100 bg-white p-7 shadow-xl sm:p-9" id="stay-box">
      <h3 class="font-display text-2xl font-bold">Demande de séjour</h3>
      <p class="mt-1 text-slate-500">Disponibilités vérifiées en direct · réponse rapide</p>
      <form id="joow-stay" class="mt-6 space-y-4">
        <input type="text" name="hp" class="hidden" tabindex="-1" autocomplete="off" aria-hidden="true">
        <div class="grid gap-4 sm:grid-cols-4">
          <label class="block"><span class="mb-1 block text-sm font-semibold text-slate-700">Arrivée</span><input name="check_in" type="date" required min="{{ now()->toDateString() }}" class="w-full rounded-xl border border-slate-200 px-4 py-3 outline-none focus:border-accent"></label>
          <label class="block"><span class="mb-1 block text-sm font-semibold text-slate-700">Départ</span><input name="check_out" type="date" required class="w-full rounded-xl border border-slate-200 px-4 py-3 outline-none focus:border-accent"></label>
          <label class="block"><span class="mb-1 block text-sm font-semibold text-slate-700">Personnes</span><input name="guests" type="number" min="1" value="2" class="w-full rounded-xl border border-slate-200 px-4 py-3 outline-none focus:border-accent"></label>
          <label class="block"><span class="mb-1 block text-sm font-semibold text-slate-700">Chambre</span><select name="room_id" id="stay-room" class="w-full rounded-xl border border-slate-200 px-4 py-3 outline-none focus:border-accent"><option value="">Au choix</option>@foreach($rooms as $rm)<option value="{{ $rm->id }}" data-price="{{ $rm->price_night }}">{{ $rm->name }}</option>@endforeach</select></label>
        </div>
        <p id="stay-avail" class="text-sm font-medium text-slate-500"></p>
        <div class="grid gap-4 sm:grid-cols-3">
          <label class="block"><span class="mb-1 block text-sm font-semibold text-slate-700">Nom</span><input name="name" type="text" required class="w-full rounded-xl border border-slate-200 px-4 py-3 outline-none focus:border-accent"></label>
          <label class="block"><span class="mb-1 block text-sm font-semibold text-slate-700">Téléphone</span><input name="phone" type="tel" required class="w-full rounded-xl border border-slate-200 px-4 py-3 outline-none focus:border-accent"></label>
          <label class="block"><span class="mb-1 block text-sm font-semibold text-slate-700">Email</span><input name="email" type="email" class="w-full rounded-xl border border-slate-200 px-4 py-3 outline-none focus:border-accent"></label>
        </div>
        <label class="block"><span class="mb-1 block text-sm font-semibold text-slate-700">Message (optionnel)</span><textarea name="notes" rows="2" class="w-full rounded-xl border border-slate-200 px-4 py-3 outline-none focus:border-accent"></textarea></label>
        <button type="submit" id="joow-stay-btn" class="w-full rounded-xl bg-grad px-6 py-4 font-bold text-white shadow-c transition hover:-translate-y-0.5">Envoyer ma demande de séjour</button>
        <p id="joow-stay-ok" class="hidden rounded-xl bg-emerald-50 px-4 py-3 text-center text-sm font-semibold text-emerald-700"></p>
        <p id="joow-stay-err" class="hidden rounded-xl bg-rose-50 px-4 py-3 text-center text-sm font-semibold text-rose-700"></p>
      </form>
    </div>
  </div>
</section>
