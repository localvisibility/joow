{{-- Section « sec-booking » : accueil (ordre + édition par section) ou insérée dans une page via un bloc « module » ($inPage) --}}
<section id="reserver" class="relative overflow-hidden py-24" {!! empty($inPage) ? 'style="order:'.$ord('booking').'" data-section="booking" data-label="Réservation"' : '' !!}>
  <div class="pointer-events-none absolute -top-20 right-0 h-80 w-80 rounded-full blur-3xl" style="background:var(--grad);opacity:.14"></div>
  <div class="mx-auto grid max-w-6xl items-center gap-14 px-5 lg:grid-cols-[.9fr,1.1fr]">
    <div class="reveal">
      <p class="text-sm font-bold uppercase tracking-[0.3em] accent">{{ $bt === 'reservation' ? 'Réservation' : ($bt === 'rdv' ? 'Rendez-vous' : 'Devis') }}</p>
      <h2 class="mt-3 font-display text-4xl font-bold sm:text-5xl" data-edit="booking.title">{{ $bookTitle }}</h2>
      <p class="mt-4 text-lg text-slate-600" data-edit="booking.sub">{{ $bookSub }}</p>
      <ul class="mt-8 space-y-3 text-slate-600">
        <li class="flex items-center gap-3"><span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-grad text-white"><i class="fa-solid fa-bolt text-xs"></i></span><span data-edit="booking.point1">{{ $bk('point1', 'Réponse rapide, sans engagement') }}</span></li>
        <li class="flex items-center gap-3"><span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-grad text-white"><i class="fa-solid fa-shield-halved text-xs"></i></span><span data-edit="booking.point2">{{ $bk('point2', 'Vos informations restent confidentielles') }}</span></li>
        @if(!empty($b['phone']))<li class="flex items-center gap-3"><span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-grad text-white"><i class="fa-solid fa-phone text-xs"></i></span>Ou appelez directement le {{ $b['phone'] }}</li>@endif
      </ul>
    </div>

    @php
      $form   = $form ?? (config('forms.'.$sector) ?? config('forms.default'));
      $fSteps = array_values(array_filter($form['steps'] ?? [], fn($s) => is_array($s)));
      $fType  = in_array($form['type'] ?? '', ['reservation', 'rdv', 'devis', 'contact'], true) ? $form['type'] : $bt;
      $wide   = ['cards', 'chips', 'toggle', 'textarea'];
    @endphp
    <!-- Formulaire multi-étapes (modèle métier, personnalisable dans le Studio) -->
    <div class="reveal jw rounded-[2rem] border border-slate-100 bg-white p-6 shadow-xl sm:p-8" id="joow-wizard" data-type="{{ $fType }}" data-success="{{ $form['success'] ?? 'Merci ! Votre demande est bien envoyée.' }}">
      <div class="jw-progress">
        @foreach($fSteps as $i => $stp)
        <div class="jw-pstep{{ $i === 0 ? ' active' : '' }}"><span class="jw-pnum">{{ $i + 1 }}</span><span class="jw-ptitle">{{ $stp['title'] ?? 'Étape '.($i+1) }}</span></div>
        @endforeach
      </div>
      <div class="jw-bar"><div class="jw-bar-fill" style="width:{{ count($fSteps) ? round(100 / count($fSteps)) : 100 }}%"></div></div>

      <form id="joow-form" class="mt-6" novalidate>
        <input type="text" name="hp" class="hidden" tabindex="-1" autocomplete="off" aria-hidden="true">
        @foreach($fSteps as $i => $stp)
        <div class="jw-step{{ $i === 0 ? ' active' : '' }}" data-step="{{ $i }}">
          <h3 class="jw-h">@if(!empty($stp['icon']))<i class="fa-solid {{ $stp['icon'] }} accent"></i>@endif{{ $stp['title'] ?? '' }}</h3>
          @if(!empty($stp['contact']))<div class="jw-recap" id="jw-recap" style="display:none"></div>@endif
          <div class="grid gap-5 sm:grid-cols-2">
            @foreach(($stp['fields'] ?? []) as $f)
              @php $key = $f['key'] ?? Str::slug($f['label'] ?? 'champ'); $req = !empty($f['required']); $ft = $f['type'] ?? 'text'; $opts = $f['options'] ?? []; @endphp
              <div class="jw-field {{ in_array($ft, $wide, true) ? 'sm:col-span-2' : '' }}" data-key="{{ $key }}" data-label="{{ $f['label'] ?? $key }}" data-required="{{ $req ? 1 : 0 }}" data-type="{{ $ft }}">
                <label class="jw-label">{{ $f['label'] ?? $key }}@if($req)<span class="accent"> *</span>@endif</label>
                @if($ft === 'cards')
                  <div class="jw-cards">@foreach($opts as $o)@php $o = is_array($o) ? $o : ['label' => $o]; @endphp<button type="button" class="jw-card" data-value="{{ $o['label'] ?? '' }}"><i class="fa-solid {{ $o['icon'] ?? 'fa-circle-check' }}"></i><span class="jw-card-l">{{ $o['label'] ?? '' }}</span>@if(!empty($o['desc']))<span class="jw-card-d">{{ $o['desc'] }}</span>@endif</button>@endforeach</div>
                @elseif($ft === 'chips')
                  <div class="jw-chips">@foreach($opts as $o)<button type="button" class="jw-chip" data-value="{{ is_array($o) ? ($o['label'] ?? '') : $o }}">{{ is_array($o) ? ($o['label'] ?? '') : $o }}</button>@endforeach</div>
                @elseif($ft === 'toggle')
                  <div class="jw-toggle">@foreach($opts as $o)<button type="button" class="jw-tg" data-value="{{ is_array($o) ? ($o['label'] ?? '') : $o }}">{{ is_array($o) ? ($o['label'] ?? '') : $o }}</button>@endforeach</div>
                @elseif($ft === 'select')
                  <select class="jw-input" name="{{ $key }}"><option value="">Choisir…</option>@foreach($opts as $o)<option>{{ is_array($o) ? ($o['label'] ?? '') : $o }}</option>@endforeach</select>
                @elseif($ft === 'textarea')
                  <textarea class="jw-input" name="{{ $key }}" rows="3" placeholder="{{ $f['placeholder'] ?? '' }}"></textarea>
                @elseif($ft === 'date')
                  <input class="jw-input" type="date" name="{{ $key }}" min="{{ now()->toDateString() }}">
                @elseif($ft === 'time')
                  <input class="jw-input" type="time" name="{{ $key }}">
                @elseif($ft === 'number')
                  <input class="jw-input" type="number" name="{{ $key }}" min="{{ $f['min'] ?? 0 }}" placeholder="{{ $f['placeholder'] ?? '' }}">
                @elseif($ft === 'phone')
                  <input class="jw-input" type="tel" name="{{ $key }}" placeholder="{{ $f['placeholder'] ?? '' }}" autocomplete="tel">
                @elseif($ft === 'email')
                  <input class="jw-input" type="email" name="{{ $key }}" placeholder="{{ $f['placeholder'] ?? '' }}" autocomplete="email">
                @else
                  <input class="jw-input" type="text" name="{{ $key }}" placeholder="{{ $f['placeholder'] ?? '' }}" @if($key === 'name') autocomplete="name" @endif>
                @endif
                <p class="jw-err">Merci de renseigner ce champ.</p>
              </div>
            @endforeach
          </div>
        </div>
        @endforeach
        <div class="jw-nav">
          <button type="button" class="jw-back" id="jw-back">← Retour</button>
          <button type="button" class="jw-next bg-grad shine" id="jw-next">Continuer <i class="fa-solid fa-arrow-right"></i></button>
          <button type="submit" class="jw-next bg-grad shine" id="jw-submit" style="display:none">{{ $form['cta'] ?? $bookCta }}</button>
        </div>
        <p class="jw-foot"><i class="fa-solid fa-lock accent"></i> {{ $form['delay'] ?? 'Réponse rapide' }} · vos informations restent confidentielles</p>
      </form>

      <div class="jw-success" id="jw-success" style="display:none">
        <div class="jw-check"><svg viewBox="0 0 52 52"><circle cx="26" cy="26" r="24"/><path d="M15 27l8 8 15-16"/></svg></div>
        <h3 class="font-display">Demande envoyée !</h3>
        <p></p>
        @if(!empty($b['phone']))<a href="{{ $phoneHref }}" class="jw-call"><i class="fa-solid fa-phone"></i> Une urgence ? {{ $b['phone'] }}</a>@endif
      </div>
    </div>
  </div>
</section>
