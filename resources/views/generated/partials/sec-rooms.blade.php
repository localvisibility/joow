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
      @php $ph = $rm->photos[0] ?? ($photos[($loop->index + 1) % max(1, count($photos))] ?? $hero); $fromP = app(\App\Services\Modules\StayPlanner::class)->fromPrice($rm); $varies = $fromP !== null && ($rm->weekend_price || ! empty($rm->seasons)); @endphp
      <div class="reveal card-hover overflow-hidden rounded-3xl border border-slate-100 bg-white shadow-sm hover:shadow-2xl">
        <img src="{{ $ph }}" class="h-52 w-full object-cover" alt="{{ $rm->name }}" loading="lazy">
        <div class="p-6">
          <div class="flex items-start justify-between gap-3">
            <h3 class="font-display text-xl font-bold">{{ $rm->name }}</h3>
            @if($fromP)<span class="shrink-0 rounded-full bg-grad px-3 py-1 text-sm font-bold text-white">@if($varies)<span class="font-normal opacity-80">dès </span>@endif{{ number_format($fromP, 0, ',', ' ') }} €<span class="font-normal opacity-80">/nuit</span></span>@endif
          </div>
          <p class="mt-1 text-sm text-slate-500"><i class="fa-solid fa-user-group accent mr-1"></i>{{ $rm->capacity }} personne{{ $rm->capacity > 1 ? 's' : '' }}@if(($rm->min_nights ?? 1) > 1)<span class="mx-1.5">·</span><i class="fa-regular fa-moon accent mr-1"></i>{{ $rm->min_nights }} nuits min.@endif</p>
          @if($rm->description)<p class="mt-3 text-sm text-slate-600">{{ $rm->description }}</p>@endif
          @if($rm->amenities)<div class="mt-4 flex flex-wrap gap-1.5">@foreach(array_slice($rm->amenities, 0, 6) as $am)<span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs text-slate-600">{{ $am }}</span>@endforeach</div>@endif
          <button type="button" class="mt-5 w-full rounded-xl border-2 border-accent px-4 py-2.5 text-sm font-bold accent transition hover:bg-accent hover:text-white" onclick="joowPickRoom('{{ $rm->id }}')">Réserver cette chambre</button>
        </div>
      </div>
      @endforeach
    </div>

    <div class="reveal mt-14 rounded-[2rem] border border-slate-100 bg-white p-7 shadow-xl sm:p-9" id="stay-box">
      @php $instant = ! empty($rmCfg['instant']); @endphp
      <h3 class="font-display text-2xl font-bold">{{ $instant ? 'Réserver votre séjour' : 'Demande de séjour' }}</h3>
      <p class="mt-1 text-slate-500">Disponibilités et tarifs en direct · {{ $instant ? 'confirmation immédiate' : 'réponse rapide' }}</p>
      <form id="joow-stay" class="mt-6 space-y-5">
        <input type="text" name="hp" class="hidden" tabindex="-1" autocomplete="off" aria-hidden="true">
        <input type="hidden" name="check_in"><input type="hidden" name="check_out">
        <div class="grid gap-4 sm:grid-cols-2">
          <label class="block"><span class="mb-1 block text-sm font-semibold text-slate-700">Hébergement</span><select name="room_id" id="stay-room" class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 outline-none focus:border-accent">@if($rooms->count() > 1)<option value="">Au choix (le meilleur prix)</option>@endif @foreach($rooms as $rm)<option value="{{ $rm->id }}" data-cap="{{ $rm->capacity }}">{{ $rm->name }}</option>@endforeach</select></label>
          <label class="block"><span class="mb-1 block text-sm font-semibold text-slate-700">Voyageurs</span><input name="guests" type="number" min="1" max="30" value="2" class="w-full rounded-xl border border-slate-200 px-4 py-3 outline-none focus:border-accent"></label>
        </div>
        <div id="stay-cal" class="select-none rounded-2xl border border-slate-100 bg-slate-50/60 p-4 sm:p-5">
          <div class="mb-3 flex items-center justify-between gap-3">
            <button type="button" data-cal="prev" class="grid h-9 w-9 place-items-center rounded-full border border-slate-200 bg-white text-slate-600 transition hover:border-accent" aria-label="Mois précédent"><i class="fa-solid fa-chevron-left text-xs"></i></button>
            <p id="stay-hint" class="text-center text-sm font-semibold text-slate-600">Choisissez votre date d'arrivée</p>
            <button type="button" data-cal="next" class="grid h-9 w-9 place-items-center rounded-full border border-slate-200 bg-white text-slate-600 transition hover:border-accent" aria-label="Mois suivant"><i class="fa-solid fa-chevron-right text-xs"></i></button>
          </div>
          <div id="stay-months" class="grid gap-6 md:grid-cols-2"></div>
          <div class="mt-3 flex flex-wrap items-center gap-4 text-xs text-slate-500"><span class="flex items-center gap-1.5"><i class="inline-block h-3 w-3 rounded bg-white ring-1 ring-slate-200"></i>Disponible</span><span class="flex items-center gap-1.5"><i class="inline-block h-3 w-3 rounded bg-slate-200"></i>Complet</span><span class="flex items-center gap-1.5"><i class="inline-block h-3 w-3 rounded bg-accent"></i>Votre séjour</span><span>Prix par nuit</span></div>
        </div>
        <div id="stay-quote" class="hidden rounded-2xl border border-slate-100 bg-white p-5 shadow-sm"></div>
        <div class="grid gap-4 sm:grid-cols-3">
          <label class="block"><span class="mb-1 block text-sm font-semibold text-slate-700">Nom</span><input name="name" type="text" required class="w-full rounded-xl border border-slate-200 px-4 py-3 outline-none focus:border-accent"></label>
          <label class="block"><span class="mb-1 block text-sm font-semibold text-slate-700">Téléphone</span><input name="phone" type="tel" required class="w-full rounded-xl border border-slate-200 px-4 py-3 outline-none focus:border-accent"></label>
          <label class="block"><span class="mb-1 block text-sm font-semibold text-slate-700">Email</span><input name="email" type="email" @if($instant) required @endif class="w-full rounded-xl border border-slate-200 px-4 py-3 outline-none focus:border-accent"></label>
        </div>
        <label class="block"><span class="mb-1 block text-sm font-semibold text-slate-700">Message (optionnel)</span><textarea name="notes" rows="2" class="w-full rounded-xl border border-slate-200 px-4 py-3 outline-none focus:border-accent"></textarea></label>
        <button type="submit" id="joow-stay-btn" class="w-full rounded-xl bg-grad px-6 py-4 font-bold text-white shadow-c transition hover:-translate-y-0.5 disabled:cursor-not-allowed disabled:opacity-50">{{ $instant ? 'Réserver maintenant' : 'Envoyer ma demande de séjour' }}</button>
        <p id="joow-stay-ok" class="hidden rounded-xl bg-emerald-50 px-4 py-3 text-center text-sm font-semibold text-emerald-700"></p>
        <p id="joow-stay-err" class="hidden rounded-xl bg-rose-50 px-4 py-3 text-center text-sm font-semibold text-rose-700"></p>
      </form>
    </div>
  </div>
</section>
