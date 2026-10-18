{{-- Section « sec-booking-resto » : accueil (ordre + édition par section) ou insérée dans une page via un bloc « module » ($inPage) --}}
<section id="reserver" class="relative overflow-hidden py-24" {!! empty($inPage) ? 'style="order:'.$ord('booking').'" data-section="booking" data-label="Réservation"' : '' !!}>
  <div class="pointer-events-none absolute -top-20 right-0 h-80 w-80 rounded-full blur-3xl" style="background:var(--grad);opacity:.14"></div>
  <div class="mx-auto grid max-w-6xl items-start gap-14 px-5 lg:grid-cols-[.9fr,1.1fr]">
    <div class="reveal">
      <p class="text-sm font-bold uppercase tracking-[0.3em] accent">Réservation</p>
      <h2 class="mt-3 font-display text-4xl font-bold sm:text-5xl" data-edit="booking.title">{{ $bk('title', 'Réserver votre table') }}</h2>
      <p class="mt-4 text-lg text-slate-600" data-edit="booking.sub">{{ $bk('sub', 'Choisissez votre créneau, confirmation immédiate.') }}</p>
      <ul class="mt-8 space-y-3 text-slate-600">
        <li class="flex items-center gap-3"><span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-grad text-white"><i class="fa-solid fa-bolt text-xs"></i></span><span data-edit="booking.point1">{{ $bk('point1', 'Disponibilités en temps réel') }}</span></li>
        <li class="flex items-center gap-3"><span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-grad text-white"><i class="fa-solid fa-envelope-circle-check text-xs"></i></span><span data-edit="booking.point2">{{ $bk('point2', 'Confirmation par email') }}</span></li>
        @if(!empty($b['phone']))<li class="flex items-center gap-3"><span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-grad text-white"><i class="fa-solid fa-phone text-xs"></i></span>Ou appelez le {{ $b['phone'] }}</li>@endif
      </ul>
    </div>
    <div class="reveal rounded-[2rem] border border-slate-100 bg-white p-7 shadow-xl sm:p-9">
      @if($zcOn)
      <iframe src="https://bookings.zenchef.com/results?rid={{ e($zcCfg['restaurant_id']) }}" class="h-[640px] w-full rounded-2xl" style="border:0" loading="lazy" title="Réservation ZenChef"></iframe>
      @else
      <form id="joow-resa" class="space-y-4">
        <input type="text" name="hp" class="hidden" tabindex="-1" autocomplete="off" aria-hidden="true">
        <div class="grid gap-4 sm:grid-cols-2">
          <label class="block"><span class="mb-1 block text-sm font-semibold text-slate-700">Date</span><input name="date" type="date" required min="{{ now()->toDateString() }}" class="w-full rounded-xl border border-slate-200 px-4 py-3 outline-none focus:border-accent"></label>
          <label class="block"><span class="mb-1 block text-sm font-semibold text-slate-700">{{ $partyLabel }}</span><input name="covers" type="number" min="1" max="{{ (int) ($rsCfg['max_party'] ?? 10) }}" value="2" class="w-full rounded-xl border border-slate-200 px-4 py-3 outline-none focus:border-accent"></label>
        </div>
        <div>
          <span class="mb-2 block text-sm font-semibold text-slate-700">Créneau</span>
          <div id="resa-slots" class="flex flex-wrap gap-2 text-sm text-slate-500">Choisissez une date pour voir les créneaux disponibles.</div>
          <input type="hidden" name="time" id="resa-time">
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
          <label class="block"><span class="mb-1 block text-sm font-semibold text-slate-700">Nom</span><input name="name" type="text" required class="w-full rounded-xl border border-slate-200 px-4 py-3 outline-none focus:border-accent"></label>
          <label class="block"><span class="mb-1 block text-sm font-semibold text-slate-700">Téléphone</span><input name="phone" type="tel" required class="w-full rounded-xl border border-slate-200 px-4 py-3 outline-none focus:border-accent"></label>
        </div>
        <label class="block"><span class="mb-1 block text-sm font-semibold text-slate-700">Email{{ !empty($rsCfg['require_email']) ? '' : ' (optionnel, pour la confirmation)' }}</span><input name="email" type="email" {{ !empty($rsCfg['require_email']) ? 'required' : '' }} class="w-full rounded-xl border border-slate-200 px-4 py-3 outline-none focus:border-accent"></label>
        <label class="block"><span class="mb-1 block text-sm font-semibold text-slate-700">Demande particulière (optionnel)</span><textarea name="notes" rows="2" class="w-full rounded-xl border border-slate-200 px-4 py-3 outline-none focus:border-accent"></textarea></label>
        <button type="submit" id="joow-resa-btn" class="w-full rounded-xl bg-grad px-6 py-4 font-bold text-white shadow-c transition hover:-translate-y-0.5"><span data-edit="booking.cta">{{ $bk('cta', 'Réserver ma table') }}</span></button>
        @php $payCfg = $mod('payment'); @endphp
        @if($on('payment') && ($payCfg['type'] ?? '') === 'hold' && !empty($payCfg['ready']) && (float) ($payCfg['hold_per_cover'] ?? 0) > 0)
        <p class="flex items-center justify-center gap-2 text-center text-xs text-slate-500"><i class="fa-solid fa-lock accent"></i>Empreinte bancaire de {{ number_format((float) $payCfg['hold_per_cover'], 0, ',', ' ') }} € par couvert, débitée uniquement en cas de no-show · paiement sécurisé Stripe</p>
        @endif
        <p id="joow-resa-ok" class="hidden rounded-xl bg-emerald-50 px-4 py-3 text-center text-sm font-semibold text-emerald-700"></p>
        <p id="joow-resa-err" class="hidden rounded-xl bg-rose-50 px-4 py-3 text-center text-sm font-semibold text-rose-700"></p>
      </form>
      @endif
    </div>
  </div>
</section>
