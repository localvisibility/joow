{{-- Page d'offre Joow Ads : une page, un objectif. Variantes A/B dans le HTML (répartition adaptative), formulaire à étapes,
     outil interactif selon le métier, reprise de l'accroche et du visuel de la pub d'origine (?c=<clé>). --}}
@php
    $goalCall = $lp->goal === 'calls';
    $fixed = $lp->winner ?: (count($variants) === 1 ? $variants[0]['key'] : null);
    $c = $content;
    $form = $c['form'];
    $steps = $form['steps'] ?? [];
    $w = $c['widget'] ?? ['type' => 'none'];
    $theme = $c['theme'] ?? 'bold';
    $hasProof = $rating && $reviews;
    $stars = $rating ? str_repeat('★', (int) round((float) $rating)) : '';
    $T = [
        'premium' => ['bg' => '#0b0f19', 'alt' => '#0f1524', 'card' => '#141b2d', 'ink' => '#f8fafc', 'mut' => '#94a3b8', 'line' => 'rgba(255,255,255,.08)'],
        'warm' => ['bg' => '#fbf7f1', 'alt' => '#f4ece1', 'card' => '#ffffff', 'ink' => '#2a1f14', 'mut' => '#7c6a58', 'line' => '#eadfce'],
        'bold' => ['bg' => '#ffffff', 'alt' => '#f6f7fb', 'card' => '#ffffff', 'ink' => '#0f172a', 'mut' => '#475569', 'line' => '#e8ecf3'],
    ][$theme] ?? null;
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $variants[0]['headline'] ?? $name }} · {{ $name }}</title>
<meta name="description" content="{{ $variants[0]['subheadline'] ?? '' }}">
<meta name="robots" content="noindex">
@if($hero)<meta property="og:image" content="{{ $hero }}">@endif
<link rel="preconnect" href="https://fonts.googleapis.com"><link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<style>
:root{--c:{{ $color }};--bg:{{ $T['bg'] }};--alt:{{ $T['alt'] }};--card:{{ $T['card'] }};--ink:{{ $T['ink'] }};--mut:{{ $T['mut'] }};--line:{{ $T['line'] }}}
*{box-sizing:border-box}html,body{margin:0}html{scroll-behavior:smooth}body{font-family:Inter,system-ui,sans-serif;color:var(--ink);background:var(--bg);-webkit-font-smoothing:antialiased}
a{color:inherit}.wrap{max-width:1160px;margin:0 auto;padding:0 20px}
.urg{background:var(--c);color:#fff;text-align:center;font-size:13px;font-weight:700;padding:9px 16px;letter-spacing:.01em}
/* Haut de page plein écran */
.hw{position:relative;color:#fff;overflow:hidden;isolation:isolate}
.hw .bgi{position:absolute;inset:0;z-index:-2;background:#0b0f19}.hw .bgi img{width:100%;height:100%;object-fit:cover;transform:scale(1.06);animation:zoom 14s ease-out forwards}@keyframes zoom{to{transform:scale(1)}}
.hw:after{content:"";position:absolute;inset:0;z-index:-1;background:linear-gradient(100deg,rgba(8,10,18,.92) 0%,rgba(8,10,18,.75) 45%,rgba(8,10,18,.25) 100%)}
@media(max-width:959px){.hw:after{background:linear-gradient(180deg,rgba(8,10,18,.55),rgba(8,10,18,.92) 60%)}}
.top{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:18px 0}
.brand{font-weight:800;font-size:17px;text-decoration:none}.brand small{display:block;font-weight:500;font-size:12px;opacity:.75}
.tel{display:inline-flex;align-items:center;gap:8px;font-weight:700;text-decoration:none;padding:10px 16px;border-radius:999px;background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.3);backdrop-filter:blur(8px);font-size:14px;white-space:nowrap}
.tel .m-only{display:none}@media(max-width:520px){.tel .n{display:none}.tel .m-only{display:inline}}
.hero{display:grid;gap:32px;padding:26px 0 64px;align-items:center}@media(min-width:960px){.hero{grid-template-columns:1.12fr .88fr;gap:56px;padding:48px 0 96px;min-height:min(86vh,820px)}}
.rate{display:inline-flex;align-items:center;gap:8px;background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.25);backdrop-filter:blur(8px);font-weight:700;font-size:13px;padding:8px 14px;border-radius:999px}.rate b{color:#fbbf24;letter-spacing:1px}
h1{font-size:clamp(34px,5.8vw,62px);line-height:1.02;letter-spacing:-.03em;margin:18px 0 16px;font-weight:900;text-wrap:balance}
.sub{font-size:clamp(17px,1.6vw,20px);line-height:1.55;color:rgba(255,255,255,.82);margin:0 0 24px;max-width:600px}
.offer{display:inline-flex;gap:12px;align-items:center;background:var(--c);color:#fff;border-radius:18px;padding:15px 20px;font-weight:800;font-size:17px;margin-bottom:22px;box-shadow:0 20px 50px -15px var(--c)}.offer span{font-size:24px}
ul.b{list-style:none;padding:0;margin:0 0 22px;display:grid;gap:11px}ul.b li{display:flex;gap:11px;font-weight:600;font-size:16px}ul.b li:before{content:"✓";color:#0b0f19;background:#86efac;border-radius:999px;width:22px;height:22px;flex:none;display:grid;place-items:center;font-size:12px;font-weight:900}
.chips{display:flex;flex-wrap:wrap;gap:8px}.chips span{display:inline-flex;gap:6px;align-items:center;background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.2);border-radius:999px;padding:7px 12px;font-size:13px;font-weight:600}
/* Formulaire à étapes */
.card{background:#fff;color:#0f172a;border-radius:26px;box-shadow:0 50px 100px -30px rgba(0,0,0,.6);padding:26px;overflow:hidden}
.card h2{margin:0;font-size:22px;font-weight:800}.card p.m{margin:4px 0 0;color:#64748b;font-size:14px}
.bar{height:6px;background:#eef2f7;border-radius:999px;margin:18px 0 6px;overflow:hidden}.bar i{display:block;height:100%;width:0;background:var(--c);border-radius:999px;transition:width .35s}
.stepn{font-size:12px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.06em}
.q{font-size:19px;font-weight:800;margin:14px 0;line-height:1.3}
.st{display:none;animation:in .3s ease}.st.on{display:block}@keyframes in{from{opacity:0;transform:translateX(12px)}to{opacity:1;transform:none}}
.opts{display:grid;gap:10px}.opts.two{grid-template-columns:1fr 1fr}
.opt{display:flex;align-items:center;gap:10px;width:100%;text-align:left;font:600 15px Inter,system-ui,sans-serif;background:#fff;border:2px solid #e2e8f0;border-radius:14px;padding:14px 16px;cursor:pointer;color:#0f172a;transition:.15s}
.opt:hover{border-color:var(--c);background:color-mix(in srgb,var(--c) 6%,#fff)}.opt.sel{border-color:var(--c);background:color-mix(in srgb,var(--c) 10%,#fff)}
.opt .dot{width:18px;height:18px;border-radius:999px;border:2px solid #cbd5e1;flex:none}.opt.sel .dot{border-color:var(--c);background:var(--c);box-shadow:inset 0 0 0 3px #fff}
label{display:block;font-size:13px;font-weight:700;margin:12px 0 5px}input,textarea{width:100%;font:500 16px Inter,system-ui,sans-serif;border:2px solid #e2e8f0;border-radius:14px;padding:13px 15px;outline:none;color:#0f172a;background:#fff}input:focus,textarea:focus{border-color:var(--c)}
.btn{display:flex;width:100%;justify-content:center;align-items:center;gap:8px;border:0;cursor:pointer;background:var(--c);color:#fff;font:800 17px Inter,system-ui,sans-serif;padding:17px;border-radius:15px;margin-top:16px;text-decoration:none;box-shadow:0 16px 34px -12px var(--c);transition:transform .15s}.btn:hover{transform:translateY(-1px)}.btn:disabled{opacity:.5;cursor:default}
.btn.alt{background:#fff;color:#0f172a;border:2px solid #e2e8f0;box-shadow:none;font-size:15px;padding:13px}
.nav{display:flex;justify-content:space-between;align-items:center;margin-top:12px}.back{background:none;border:0;color:#64748b;font:600 14px Inter,system-ui,sans-serif;cursor:pointer;padding:6px 0}
.safe{display:flex;gap:6px;justify-content:center;align-items:center;font-size:12px;color:#64748b;margin-top:12px}
.done{display:none;text-align:center;padding:24px 4px}.done .ic{width:64px;height:64px;border-radius:999px;background:#dcfce7;color:#16a34a;display:grid;place-items:center;font-size:30px;margin:0 auto 14px}
/* Sections */
section.s{padding:72px 0}section.s.alt{background:var(--alt)}
.tt{font-size:clamp(28px,3.8vw,42px);font-weight:900;letter-spacing:-.025em;margin:0 0 10px;text-align:center;text-wrap:balance}.ts{color:var(--mut);text-align:center;margin:0 auto 36px;max-width:640px;font-size:17px}
.proof{display:flex;flex-wrap:wrap;justify-content:center;gap:14px 44px;padding:28px 0;border-bottom:1px solid var(--line)}.proof div{text-align:center}.proof b{display:block;font-size:28px;font-weight:900}.proof span{font-size:13px;color:var(--mut)}
.revs{display:flex;gap:16px;overflow-x:auto;scroll-snap-type:x mandatory;padding:4px 2px 14px;scrollbar-width:none}.revs::-webkit-scrollbar{display:none}
.rev{flex:0 0 min(86%,360px);scroll-snap-align:start;background:var(--card);border-radius:22px;padding:24px;box-shadow:0 0 0 1px var(--line),0 20px 40px -30px rgba(0,0,0,.4)}.rev .s{color:#f59e0b;letter-spacing:2px;font-size:18px}.rev p{font-size:16px;line-height:1.6;margin:12px 0}.rev cite{font-style:normal;font-weight:700;font-size:14px;display:flex;align-items:center;gap:8px}.rev cite i{width:30px;height:30px;border-radius:999px;background:var(--c);color:#fff;display:grid;place-items:center;font-style:normal;font-size:13px}
.how{display:grid;gap:16px;counter-reset:n}@media(min-width:800px){.how{grid-template-columns:repeat(3,1fr)}}
.how div{background:var(--card);border-radius:22px;padding:26px;box-shadow:0 0 0 1px var(--line);counter-increment:n;position:relative}.how div:before{content:counter(n);display:grid;place-items:center;width:44px;height:44px;border-radius:14px;background:var(--c);color:#fff;font-weight:900;font-size:18px;margin-bottom:14px}.how h3{margin:0 0 6px;font-size:19px}.how p{margin:0;color:var(--mut);line-height:1.55}
.faq{max-width:780px;margin:0 auto;display:grid;gap:10px}.faq details{background:var(--card);border-radius:18px;box-shadow:0 0 0 1px var(--line);padding:20px 22px}.faq summary{font-weight:700;cursor:pointer;list-style:none;display:flex;justify-content:space-between;gap:12px;font-size:17px}.faq summary:after{content:"+";color:var(--c);font-weight:900;font-size:20px;line-height:1}.faq details[open] summary:after{content:"–"}.faq p{color:var(--mut);line-height:1.65;margin:12px 0 0}
/* Outil interactif */
.tool{max-width:860px;margin:0 auto;background:var(--card);border-radius:28px;padding:32px;box-shadow:0 0 0 1px var(--line),0 40px 80px -50px rgba(0,0,0,.5)}
.tool .row{display:flex;justify-content:space-between;align-items:baseline;gap:12px;font-weight:700}.tool .row b{font-size:28px;color:var(--c)}
input[type=range]{width:100%;accent-color:var(--c);padding:0;border:0;margin:18px 0 6px;height:28px}
.res{display:grid;gap:12px;margin-top:22px}@media(min-width:700px){.res{grid-template-columns:1fr 1fr}}
.res div{border-radius:20px;padding:20px;background:var(--alt);text-align:center}.res b{display:block;font-size:clamp(24px,3.2vw,34px);font-weight:900;color:var(--c);letter-spacing:-.02em;white-space:nowrap}.res span{color:var(--mut);font-size:14px}
.note{font-size:12px;color:var(--mut);text-align:center;margin-top:14px}
.eopts{display:flex;flex-wrap:wrap;gap:10px;justify-content:center;margin:18px 0}.eopts button{font:700 15px Inter,system-ui,sans-serif;border:2px solid var(--line);background:var(--card);color:var(--ink);border-radius:14px;padding:12px 18px;cursor:pointer}.eopts button.sel{border-color:var(--c);background:color-mix(in srgb,var(--c) 12%,var(--card))}
.ba{position:relative;max-width:900px;margin:0 auto;aspect-ratio:16/10;border-radius:26px;overflow:hidden;box-shadow:0 40px 80px -40px rgba(0,0,0,.5);user-select:none;touch-action:none;cursor:ew-resize}
.ba img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover}.ba .af{clip-path:inset(0 0 0 50%)}.ba .h{position:absolute;top:0;bottom:0;left:50%;width:4px;background:#fff;transform:translateX(-50%)}.ba .h:after{content:"⇆";position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);width:48px;height:48px;border-radius:999px;background:#fff;color:#0f172a;display:grid;place-items:center;font-weight:900;box-shadow:0 8px 20px rgba(0,0,0,.3)}
.ba .lb{position:absolute;top:16px;padding:6px 12px;border-radius:999px;background:rgba(0,0,0,.6);color:#fff;font-size:13px;font-weight:700}
.final{background:linear-gradient(135deg,var(--c),color-mix(in srgb,var(--c) 55%,#000));color:#fff;border-radius:32px;padding:56px 24px;text-align:center;box-shadow:0 40px 80px -40px var(--c)}.final h2{font-size:clamp(28px,4.4vw,44px);margin:0 0 10px;font-weight:900;letter-spacing:-.02em;text-wrap:balance}.final p{opacity:.85;margin:0 0 24px}.final .btn{max-width:380px;margin:0 auto;background:#fff;color:var(--c)}
footer{padding:28px 0 110px;font-size:13px;color:var(--mut);text-align:center}
.sticky{position:fixed;inset:auto 12px 12px 12px;z-index:50;display:flex;gap:8px;transition:transform .25s}@media(min-width:960px){.sticky{display:none}}.sticky.hide{transform:translateY(140%)}
.sticky a{flex:1}.sticky .btn{margin:0;padding:15px 12px;font-size:15px;white-space:nowrap}
.rv{opacity:0;transform:translateY(24px);transition:opacity .7s ease,transform .7s ease}.rv.in{opacity:1;transform:none}
@media(prefers-reduced-motion:reduce){.rv{opacity:1;transform:none}.hw .bgi img{animation:none;transform:none}}
[data-v]{display:none!important}html.ab-ready [data-v].on{display:block!important}
.pv{position:fixed;top:0;inset-inline:0;z-index:99;background:#111827;color:#fbbf24;font:600 12px Inter,system-ui,sans-serif;text-align:center;padding:6px}
</style>
</head>
<body>
@if($preview)<style>body{padding-top:28px}</style><div class="pv">Aperçu · variante <span id="pv-v"></span> · <a href="#" id="pv-sw" style="color:#fff">changer</a></div>@endif
@if(!empty($c['urgency']))<div class="urg">⏳ {{ $c['urgency'] }}</div>@endif

<div class="hw">
  @foreach($variants as $v)
  <div class="bgi" data-v="{{ $v['key'] }}">@php $img = $v['image'] ?? $hero; @endphp @if($img)<img src="{{ $img }}" alt="" data-img fetchpriority="high">@endif</div>
  @endforeach
  <div class="wrap">
    <div class="top">
      <a class="brand" href="{{ $siteUrl }}">{{ $name }}@if($city)<small>📍 {{ $city }}</small>@endif</a>
      @if($phoneHref)<a class="tel" href="{{ $phoneHref }}" data-call>📞 <span class="n">{{ $phone }}</span><span class="m-only">Appeler</span></a>@endif
    </div>
    <div class="hero">
      <div>
        @foreach($variants as $v)
        <div data-v="{{ $v['key'] }}">
          @if($hasProof)<div class="rate"><b>{{ $stars }}</b> {{ number_format((float) $rating, 1, ',', '') }}/5 · {{ $reviews }} avis Google</div>@endif
          <h1 data-h1>{{ $v['headline'] ?? '' }}</h1>
          <p class="sub" data-sub @if(empty($v['subheadline'])) hidden @endif>{{ $v['subheadline'] ?? '' }}</p>
          @if(!empty($v['offer']))<div class="offer"><span>🎁</span>{{ $v['offer'] }}</div>@endif
          @if(!empty($v['bullets']))<ul class="b">@foreach(array_slice((array) $v['bullets'], 0, 4) as $bl)<li>{{ $bl }}</li>@endforeach</ul>@endif
        </div>
        @endforeach
        @if(!empty($c['guarantees']))<div class="chips">@foreach($c['guarantees'] as $g)<span>🛡 {{ $g }}</span>@endforeach</div>@endif
      </div>

      <div class="card" id="form">
        <div id="f-head"><h2>{{ $form['title'] }}</h2><p class="m">{{ $form['subtitle'] }}</p></div>
        <form data-lead novalidate>
          <input type="text" name="hp" style="display:none" tabindex="-1" autocomplete="off">
          <div class="bar"><i id="f-bar"></i></div><div class="stepn" id="f-n"></div>
          @foreach($steps as $i => $st)
          <div class="st" data-step="{{ $i }}" data-key="{{ $st['key'] }}" data-type="{{ $st['type'] }}" data-q="{{ $st['question'] }}" data-req="{{ $st['required'] ? 1 : 0 }}">
            <p class="q">{{ $st['question'] }}</p>
            @if(in_array($st['type'], ['choice', 'multi'], true))
              <div class="opts {{ count($st['options']) > 4 ? 'two' : '' }}">@foreach($st['options'] as $o)<button type="button" class="opt" data-val="{{ $o }}"><span class="dot"></span>{{ $o }}</button>@endforeach</div>
              @if($st['type'] === 'multi')<button type="button" class="btn" data-next>Continuer →</button>@endif
            @else
              <input type="{{ $st['type'] === 'number' ? 'number' : 'text' }}" data-input inputmode="{{ $st['type'] === 'number' ? 'numeric' : 'text' }}">
              <button type="button" class="btn" data-next>Continuer →</button>
            @endif
          </div>
          @endforeach
          <div class="st" data-step="{{ count($steps) }}" data-contact>
            <p class="q">{{ count($steps) ? 'Dernière étape : où vous joindre ?' : ($goalCall ? 'Laissez votre numéro, on vous rappelle' : 'Vos coordonnées') }}</p>
            <label>Prénom et nom</label><input name="name" required autocomplete="name">
            <label>Téléphone</label><input name="phone" type="tel" required autocomplete="tel" inputmode="tel">
            @if($form['ask_email'])<label>Email</label><input name="email" type="email" autocomplete="email">@endif
            @if($form['ask_postcode'])<label>Code postal</label><input name="postcode" inputmode="numeric" maxlength="5" autocomplete="postal-code">@endif
            @if(!count($steps))<label>Votre besoin (facultatif)</label><textarea name="message" rows="2"></textarea>@endif
            <button class="btn" type="submit">{{ $form['button'] }} →</button>
            <p class="safe">🔒 Vos données restent confidentielles · aucun démarchage</p>
          </div>
          <div class="nav"><button type="button" class="back" id="f-back" hidden>← Retour</button><span></span></div>
        </form>
        <div class="done" id="f-done"><div class="ic">✓</div><h2>Demande envoyée !</h2><p class="m" style="margin-top:8px">{{ $form['success'] }}</p>
          @if($phoneHref)<a class="btn alt" href="{{ $phoneHref }}" data-call style="margin-top:18px">📞 Pressé ? Appelez le {{ $phone }}</a>@endif</div>
      </div>
    </div>
  </div>
</div>

@if($hasProof || !empty($c['guarantees']))
<div class="wrap"><div class="proof rv">
  @if($hasProof)<div><b>{{ number_format((float) $rating, 1, ',', '') }}/5</b><span>note Google</span></div><div><b>{{ $reviews }}</b><span>avis clients</span></div>@endif
  @foreach(array_slice((array) $c['guarantees'], 0, 2) as $g)<div><b>✓</b><span>{{ $g }}</span></div>@endforeach
</div></div>
@endif

@if($w['type'] === 'savings')
<section class="s"><div class="wrap rv">
  <h2 class="tt">{{ $w['title'] ?: 'Combien pouvez-vous économiser ?' }}</h2><p class="ts">Faites le calcul en 5 secondes.</p>
  <div class="tool" id="tool-sav" data-min="{{ $w['savings']['pct_min'] }}" data-max="{{ $w['savings']['pct_max'] }}">
    <div class="row"><span>{{ $w['savings']['label'] }}</span><b><span id="sav-v">{{ number_format($w['savings']['default'], 0, ',', ' ') }}</span> {{ $w['savings']['unit'] }}</b></div>
    <input type="range" id="sav-r" min="{{ $w['savings']['min'] }}" max="{{ $w['savings']['max'] }}" step="50" value="{{ $w['savings']['default'] }}" aria-label="{{ $w['savings']['label'] }}">
    <div class="res"><div><b id="sav-y">—</b><span>d'économies estimées par an</span></div><div><b id="sav-10">—</b><span>sur 10 ans</span></div></div>
    <a class="btn" href="#form" data-goform data-sim style="max-width:420px;margin:22px auto 0">Recevoir mon étude détaillée →</a>
    <p class="note">{{ $w['savings']['note'] }}</p>
  </div>
</div></section>
@elseif($w['type'] === 'estimate')
<section class="s"><div class="wrap rv">
  <h2 class="tt">{{ $w['title'] ?: 'Estimez votre budget' }}</h2><p class="ts">{{ $w['estimate']['question'] ?: 'Choisissez votre situation' }}</p>
  <div class="tool" style="text-align:center">
    <div class="eopts">@foreach($w['estimate']['options'] as $i => $o)<button type="button" data-from="{{ $o['from'] }}" data-to="{{ $o['to'] }}" data-label="{{ $o['label'] }}" @if($i === 0) class="sel" @endif>{{ $o['label'] }}</button>@endforeach</div>
    <div class="res" style="grid-template-columns:1fr"><div><b id="est-v">—</b><span>fourchette indicative</span></div></div>
    <a class="btn" href="#form" data-goform data-sim style="max-width:420px;margin:22px auto 0">Recevoir mon devis précis →</a>
    <p class="note">{{ $w['estimate']['note'] }}</p>
  </div>
</div></section>
@elseif($w['type'] === 'beforeafter')
<section class="s"><div class="wrap rv">
  <h2 class="tt">{{ $w['title'] ?: 'Avant / après' }}</h2><p class="ts">Faites glisser pour comparer.</p>
  <div class="ba" id="ba"><img src="{{ $w['beforeafter']['before'] }}" alt="Avant"><img class="af" src="{{ $w['beforeafter']['after'] }}" alt="Après"><span class="lb" style="left:16px">Avant</span><span class="lb" style="right:16px">Après</span><div class="h"></div></div>
</div></section>
@endif

@if(count($quotes))
<section class="s alt"><div class="wrap">
  <h2 class="tt rv">Ils nous ont fait confiance</h2><p class="ts rv">Avis Google de nos clients{{ $city ? ' à '.$city.' et alentours' : '' }}.</p>
  <div class="revs rv">@foreach($quotes as $r)<div class="rev"><div class="s">{{ str_repeat('★', (int) ($r['rating'] ?? 5)) }}</div><p>« {{ \Illuminate\Support\Str::limit($r['text'], 260) }} »</p><cite><i>{{ mb_strtoupper(mb_substr($r['author'] ?? 'C', 0, 1)) }}</i>{{ $r['author'] ?? 'Client' }}</cite></div>@endforeach</div>
</div></section>
@endif

@if(!empty($c['process']))
<section class="s"><div class="wrap">
  <h2 class="tt rv">Comment ça se passe ?</h2><p class="ts rv">Simple, rapide et sans engagement.</p>
  <div class="how">@foreach($c['process'] as $p)<div class="rv"><h3>{{ $p['title'] }}</h3><p>{{ $p['text'] }}</p></div>@endforeach</div>
</div></section>
@endif

@if(!empty($c['faq']))
<section class="s alt"><div class="wrap">
  <h2 class="tt rv">Vos questions</h2><p class="ts rv">Tout ce qu'il faut savoir avant de nous contacter.</p>
  <div class="faq rv">@foreach($c['faq'] as $q)<details><summary>{{ $q['q'] }}</summary><p>{{ $q['a'] }}</p></details>@endforeach</div>
</div></section>
@endif

<section class="s"><div class="wrap"><div class="final rv">
  @foreach($variants as $v)<div data-v="{{ $v['key'] }}"><h2>{{ ($v['offer'] ?? '') ?: ($v['headline'] ?? '') }}</h2></div>@endforeach
  <p>{{ $form['subtitle'] }}</p>
  <a class="btn" href="#form" data-goform>{{ $form['button'] }} →</a>
  @if($phoneHref)<p style="margin:18px 0 0">ou appelez le <a href="{{ $phoneHref }}" data-call style="color:#fff;font-weight:800">{{ $phone }}</a></p>@endif
</div></div></section>

<footer><a href="{{ $siteUrl }}">{{ $name }}</a>@if($city) · {{ $city }}@endif · <a href="{{ $siteUrl }}">Voir le site</a></footer>
<div class="sticky" id="sticky">
  @if($phoneHref)<a class="btn alt" href="{{ $phoneHref }}" data-call>📞 Appeler</a>@endif
  <a class="btn" href="#form" data-goform>{{ $form['button'] }}</a>
</div>
<script>
(function(){
  var KEYS=@json(array_column($variants, 'key')), FIXED=@json($fixed), API=@json($api), LP=@json($lp->id), PREVIEW=@json($preview), TITLE=@json($lp->title), MATCH=@json((object) ($c['match'] ?? []));
  var qs=new URLSearchParams(location.search), ck='joow_ab_'+LP, v=FIXED, sim=null;
  var utm={};qs.forEach(function(val,k){if(/^utm_|fbclid|gclid/.test(k))utm[k]=val});
  function hit(t){if(PREVIEW||!v)return;try{fetch(API+'/lp/'+LP+'/hit',{method:'POST',headers:{'Content-Type':'application/json',Accept:'application/json'},body:JSON.stringify({v:v,t:t}),keepalive:true})}catch(e){}}
  var once={};function hitOnce(t){if(once[t])return;once[t]=1;hit(t)}
  function show(){
    document.querySelectorAll('[data-v]').forEach(function(el){el.classList.toggle('on',el.getAttribute('data-v')===v)});
    // Cohérence pub → page : accroche et visuel de la pub cliquée
    var m=MATCH[qs.get('c')||''];
    if(m){document.querySelectorAll('[data-v="'+v+'"] [data-h1]').forEach(function(e){e.textContent=m.headline});
      if(m.sub)document.querySelectorAll('[data-v="'+v+'"] [data-sub]').forEach(function(e){e.textContent=m.sub;e.hidden=false});
      if(m.image)document.querySelectorAll('[data-v="'+v+'"] [data-img]').forEach(function(e){e.src=m.image});}
    document.documentElement.classList.add('ab-ready');hit('view');
  }
  if(PREVIEW){var qv=qs.get('v');v=KEYS.indexOf(qv)>=0?qv:KEYS[0];var s=document.getElementById('pv-v');if(s)s.textContent=v;var sw=document.getElementById('pv-sw');if(sw)sw.onclick=function(e){e.preventDefault();qs.set('v',KEYS[(KEYS.indexOf(v)+1)%KEYS.length]);location.search=qs.toString();};show();}
  else if(v){show();}
  else{try{v=localStorage.getItem(ck)}catch(e){}
    if(KEYS.indexOf(v)>=0){show();}
    else{var done=false,fallback=setTimeout(function(){if(done)return;done=true;v=KEYS[Math.floor(Math.random()*KEYS.length)];try{localStorage.setItem(ck,v)}catch(e){}show()},1200);
      fetch(API+'/lp/'+LP+'/pick',{headers:{Accept:'application/json'}}).then(function(r){return r.json()}).then(function(d){if(done)return;done=true;clearTimeout(fallback);v=KEYS.indexOf(d.v)>=0?d.v:KEYS[0];try{localStorage.setItem(ck,v)}catch(e){}show()}).catch(function(){});}}
  document.querySelectorAll('[data-call]').forEach(function(a){a.addEventListener('click',function(){hitOnce('call')})});
  document.querySelectorAll('[data-goform]').forEach(function(a){a.addEventListener('click',function(e){e.preventDefault();document.getElementById('form').scrollIntoView({behavior:'smooth',block:'start'})})});
  // Apparitions au défilement
  var rv=document.querySelectorAll('.rv');if('IntersectionObserver' in window){var io=new IntersectionObserver(function(es){es.forEach(function(e){if(e.isIntersecting){e.target.classList.add('in');io.unobserve(e.target)}})},{threshold:.12});rv.forEach(function(e){io.observe(e)});}else rv.forEach(function(e){e.classList.add('in')});
  var sticky=document.getElementById('sticky'),card=document.getElementById('form');
  if('IntersectionObserver' in window){new IntersectionObserver(function(e){sticky.classList.toggle('hide',e[0].isIntersecting)},{threshold:.2}).observe(card);}
  var eur=function(n){return Math.round(n).toLocaleString('fr-FR')+' €'};
  // Simulateur d'économies
  var sv=document.getElementById('tool-sav');if(sv){var r=document.getElementById('sav-r'),pmin=+sv.dataset.min/100,pmax=+sv.dataset.max/100;
    var upd=function(){var x=+r.value;document.getElementById('sav-v').textContent=x.toLocaleString('fr-FR');var lo=x*pmin,hi=x*pmax;
      document.getElementById('sav-y').textContent=(pmin<pmax?eur(lo)+' à ':'')+eur(hi);document.getElementById('sav-10').textContent='jusqu’à '+eur(hi*10);sim='Facture '+x+' € · économies estimées '+(pmin<pmax?eur(lo)+' à ':'')+eur(hi)+' / an'};
    r.addEventListener('input',upd);upd();}
  // Fourchette de prix
  var eb=document.querySelectorAll('.eopts button');if(eb.length){var shw=function(b){eb.forEach(function(x){x.classList.toggle('sel',x===b)});var f=b.dataset.from,t=b.dataset.to;
      document.getElementById('est-v').textContent=f?(t?eur(+f)+' – '+eur(+t):'à partir de '+eur(+f)):'sur devis';sim=b.dataset.label+' · '+document.getElementById('est-v').textContent};
    eb.forEach(function(b){b.addEventListener('click',function(){shw(b)})});shw(eb[0]);}
  // Avant / après
  var ba=document.getElementById('ba');if(ba){var af=ba.querySelector('.af'),h=ba.querySelector('.h'),mv=function(x){var r=ba.getBoundingClientRect(),p=Math.min(100,Math.max(0,(x-r.left)/r.width*100));af.style.clipPath='inset(0 0 0 '+p+'%)';h.style.left=p+'%'},drag=false;
    ba.addEventListener('pointerdown',function(e){drag=true;mv(e.clientX)});window.addEventListener('pointermove',function(e){if(drag)mv(e.clientX)});window.addEventListener('pointerup',function(){drag=false});}
  // Formulaire à étapes
  var f=document.querySelector('form[data-lead]'),steps=[].slice.call(f.querySelectorAll('.st')),cur=0,answers={},bar=document.getElementById('f-bar'),num=document.getElementById('f-n'),back=document.getElementById('f-back');
  function step(i){cur=i;steps.forEach(function(s,k){s.classList.toggle('on',k===i)});bar.style.width=Math.round(i/steps.length*100+100/steps.length*.5)+'%';num.textContent=steps.length>1?'Étape '+(i+1)+' sur '+steps.length:'';back.hidden=i===0;var inp=steps[i].querySelector('[data-input],input[name=name]');if(inp&&i>0)setTimeout(function(){inp.focus({preventScroll:true})},250)}
  function next(){if(cur<steps.length-1)step(cur+1)}
  steps.forEach(function(s){
    var type=s.getAttribute('data-type'),q=s.getAttribute('data-q');
    s.querySelectorAll('.opt').forEach(function(b){b.addEventListener('click',function(){
      if(type==='multi'){b.classList.toggle('sel');answers[q]=[].slice.call(s.querySelectorAll('.opt.sel')).map(function(x){return x.getAttribute('data-val')}).join(', ');}
      else{s.querySelectorAll('.opt').forEach(function(x){x.classList.remove('sel')});b.classList.add('sel');answers[q]=b.getAttribute('data-val');setTimeout(next,220);}
    })});
    var nb=s.querySelector('[data-next]'),inp=s.querySelector('[data-input]');
    if(nb)nb.addEventListener('click',function(){if(inp)answers[q]=inp.value.trim();
      if(s.getAttribute('data-req')==='1'&&!answers[q]){(inp||s).animate&&(inp||s).animate([{transform:'translateX(-6px)'},{transform:'translateX(6px)'},{transform:'none'}],{duration:250});return;}next()});
    if(inp)inp.addEventListener('keydown',function(e){if(e.key==='Enter'){e.preventDefault();nb.click()}});
  });
  back.addEventListener('click',function(){if(cur>0)step(cur-1)});
  step(0);
  f.addEventListener('submit',function(e){e.preventDefault();var b=f.querySelector('button[type=submit]');
    if(!f.name.value.trim()||f.phone.value.replace(/\D/g,'').length<9){(f.phone.value?f.phone:f.name).focus();return;}
    b.disabled=true;
    var payload=Object.assign({'Page':TITLE,'Variante':v||'','Pub':qs.get('c')||''},answers,utm);if(sim)payload['Simulation']=sim;if(f.postcode)payload['Code postal']=f.postcode.value;
    var d={type:'offre',name:f.name.value,phone:f.phone.value,email:f.email?f.email.value:null,message:f.message?f.message.value:Object.keys(answers).map(function(k){return k+' : '+answers[k]}).join('\n'),hp:f.hp.value,payload:payload};
    fetch(API.replace('/api/site/','/api/lead/'),{method:'POST',headers:{'Content-Type':'application/json',Accept:'application/json'},body:JSON.stringify(d)}).then(function(r){if(!r.ok)throw 0;
      f.style.display='none';document.getElementById('f-head').style.display='none';document.getElementById('f-done').style.display='block';hitOnce('conversion');
    }).catch(function(){b.disabled=false;alert('Envoi impossible, appelez-nous directement.')});
  });
})();
</script>
</body>
</html>
