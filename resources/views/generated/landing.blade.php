{{-- Page d'offre Joow Ads : une page, un objectif. Toutes les variantes A/B sont dans le HTML ; le navigateur en tire une (fixe pour le visiteur). --}}
@php
    $goalCall = $lp->goal === 'calls';
    $fixed = $lp->winner ?: (count($variants) === 1 ? $variants[0]['key'] : null);
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
<link rel="preconnect" href="https://fonts.googleapis.com"><link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
:root{--c:{{ $color }};--ink:#0f172a;--mut:#475569}
*{box-sizing:border-box}html,body{margin:0}body{font-family:Inter,system-ui,sans-serif;color:var(--ink);background:#fff;-webkit-font-smoothing:antialiased}
a{color:inherit}.wrap{max-width:1080px;margin:0 auto;padding:0 20px}
.top{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:14px 0}
.brand{font-weight:800;font-size:17px;text-decoration:none}
.tel{display:inline-flex;align-items:center;gap:8px;font-weight:700;text-decoration:none;padding:9px 14px;border-radius:999px;border:2px solid var(--c);color:var(--c);font-size:14px;white-space:nowrap}@media(max-width:520px){.tel .n{display:none}.brand{font-size:15px}}
.hero{display:grid;gap:36px;padding:28px 0 48px;align-items:center}
@media(min-width:900px){.hero{grid-template-columns:1.1fr .9fr;padding:48px 0 72px}}
.badge{display:inline-flex;gap:8px;align-items:center;background:color-mix(in srgb,var(--c) 12%,#fff);color:var(--c);font-weight:700;font-size:13px;padding:7px 12px;border-radius:999px}
h1{font-size:clamp(32px,6vw,52px);line-height:1.05;letter-spacing:-.02em;margin:16px 0 14px;font-weight:800}
.sub{font-size:18px;line-height:1.5;color:var(--mut);margin:0 0 20px}
.offer{display:flex;gap:12px;align-items:center;background:#0f172a;color:#fff;border-radius:16px;padding:14px 16px;font-weight:700;margin-bottom:18px}
.offer span{font-size:22px}
ul.b{list-style:none;padding:0;margin:0 0 24px;display:grid;gap:10px}ul.b li{display:flex;gap:10px;font-weight:500}ul.b li:before{content:"✓";color:#fff;background:var(--c);border-radius:999px;width:22px;height:22px;flex:none;display:grid;place-items:center;font-size:12px;font-weight:800}
.stars{display:flex;align-items:center;gap:8px;font-size:14px;color:var(--mut)}.stars b{color:#f59e0b;letter-spacing:1px}
.card{background:#fff;border-radius:22px;box-shadow:0 30px 60px -20px rgba(15,23,42,.25),0 0 0 1px rgba(15,23,42,.06);padding:22px}
.photo{width:100%;aspect-ratio:4/3;object-fit:cover;border-radius:16px;margin-bottom:16px;display:block}
.card h2{margin:0 0 4px;font-size:20px}.card p.m{margin:0 0 14px;color:var(--mut);font-size:14px}
label{display:block;font-size:13px;font-weight:600;margin:10px 0 4px}input,textarea{width:100%;font:inherit;border:1px solid #e2e8f0;border-radius:12px;padding:12px 14px;outline:none}input:focus,textarea:focus{border-color:var(--c)}
.btn{display:flex;width:100%;justify-content:center;align-items:center;gap:8px;border:0;cursor:pointer;background:var(--c);color:#fff;font:800 17px Inter,sans-serif;padding:16px;border-radius:14px;margin-top:14px;text-decoration:none;box-shadow:0 14px 30px -10px var(--c)}
.btn.alt{background:#fff;color:var(--ink);border:2px solid #e2e8f0;box-shadow:none;font-size:15px;padding:13px}
.ok{display:none;background:#ecfdf5;color:#047857;border-radius:12px;padding:14px;font-weight:700;margin-top:12px;text-align:center}
.q{display:grid;gap:14px;padding:8px 0 56px}@media(min-width:800px){.q{grid-template-columns:repeat(3,1fr)}}
.q blockquote{margin:0;background:#f8fafc;border-radius:16px;padding:18px;font-size:14px;line-height:1.55;color:#334155}.q cite{display:block;margin-top:8px;font-style:normal;font-weight:700;font-size:13px;color:var(--ink)}
footer{border-top:1px solid #f1f5f9;padding:22px 0 90px;font-size:13px;color:#64748b;text-align:center}
.sticky{position:fixed;inset:auto 12px 12px 12px;z-index:50;display:flex;gap:8px}@media(min-width:900px){.sticky{display:none}}
.sticky a{flex:1}.sticky .btn{margin:0;padding:15px}
[data-v]{display:none!important}html.ab-ready [data-v].on{display:grid!important}
.tel .m-only{display:none}@media(max-width:520px){.tel .m-only{display:inline}}.pv{position:fixed;top:0;inset-inline:0;z-index:99;background:#111827;color:#fbbf24;font:600 12px Inter;text-align:center;padding:6px}
</style>
</head>
<body>
@if($preview)<style>body{padding-top:28px}</style><div class="pv">Aperçu · variante <span id="pv-v"></span> · <a href="#" id="pv-sw" style="color:#fff">changer</a></div>@endif
<div class="wrap">
  <div class="top">
    <a class="brand" href="{{ $siteUrl }}">{{ $name }}</a>
    @if($phoneHref)<a class="tel" href="{{ $phoneHref }}" data-call>📞 <span class="n">{{ $phone }}</span><span class="m-only">Appeler</span></a>@endif
  </div>

  @foreach($variants as $v)
  <section class="hero" data-v="{{ $v['key'] }}">
    <div>
      @if($rating && $reviews)<div class="stars"><b>★★★★★</b> {{ number_format((float) $rating, 1, ',', '') }}/5 · {{ $reviews }} avis Google</div>@endif
      <h1>{{ $v['headline'] ?? '' }}</h1>
      @if(!empty($v['subheadline']))<p class="sub">{{ $v['subheadline'] }}</p>@endif
      @if(!empty($v['offer']))<div class="offer"><span>🎁</span>{{ $v['offer'] }}</div>@endif
      @if(!empty($v['bullets']))<ul class="b">@foreach(array_slice((array) $v['bullets'], 0, 4) as $bl)<li>{{ $bl }}</li>@endforeach</ul>@endif
      @if($city)<div class="badge">📍 {{ $city }} et alentours</div>@endif
    </div>
    <div class="card" id="form-{{ $v['key'] }}">
      @if(!empty($v['image'] ?? $hero))<img class="photo" src="{{ $v['image'] ?? $hero }}" alt="{{ $name }}" loading="eager">@endif
      @if($goalCall && $phoneHref)
        <h2>{{ $v['cta'] ?? 'Appelez-nous' }}</h2><p class="m">Réponse immédiate, sans engagement.</p>
        <a class="btn" href="{{ $phoneHref }}" data-call>📞 {{ $phone }}</a>
        <p class="m" style="margin:14px 0 0;text-align:center">ou laissez votre numéro, on vous rappelle</p>
      @else
        <h2>{{ $v['cta'] ?? 'Demander' }}</h2><p class="m">Réponse rapide · sans engagement</p>
      @endif
      <form data-lead>
        <input type="text" name="hp" style="display:none" tabindex="-1" autocomplete="off">
        <label>Prénom et nom</label><input name="name" required autocomplete="name">
        <label>Téléphone</label><input name="phone" type="tel" required autocomplete="tel">
        @unless($goalCall)<label>Votre besoin (facultatif)</label><textarea name="message" rows="2"></textarea>@endunless
        <button class="btn" type="submit">{{ $goalCall ? 'Être rappelé' : ($v['cta'] ?? 'Envoyer') }} →</button>
        <div class="ok">✓ Merci ! Nous vous recontactons très vite.</div>
      </form>
    </div>
  </section>
  @endforeach

  @if(count($quotes))
  <div class="q">
    @foreach($quotes as $r)<blockquote>« {{ \Illuminate\Support\Str::limit($r['text'], 220) }} »<cite>{{ $r['author'] ?? 'Client' }} · ★ {{ $r['rating'] ?? 5 }}/5</cite></blockquote>@endforeach
  </div>
  @endif
</div>
<footer><a href="{{ $siteUrl }}">{{ $name }}</a>@if($city) · {{ $city }}@endif · <a href="{{ $siteUrl }}">Voir le site</a></footer>
<div class="sticky">
  @if($phoneHref)<a class="btn alt" href="{{ $phoneHref }}" data-call>📞 Appeler</a>@endif
  <a class="btn" href="#" data-goform>{{ $goalCall ? 'Être rappelé' : 'Demander' }}</a>
</div>
<script>
(function(){
  var KEYS=@json(array_column($variants, 'key')), FIXED=@json($fixed), API=@json($api), LP=@json($lp->id), PREVIEW=@json($preview);
  var ck='joow_ab_'+LP, v=FIXED;
  if(!v){try{v=localStorage.getItem(ck)}catch(e){}if(KEYS.indexOf(v)<0){v=KEYS[Math.floor(Math.random()*KEYS.length)];try{localStorage.setItem(ck,v)}catch(e){}}}
  if(PREVIEW){var q=new URLSearchParams(location.search).get('v');if(KEYS.indexOf(q)>=0)v=q;var s=document.getElementById('pv-v');if(s)s.textContent=v;var sw=document.getElementById('pv-sw');if(sw)sw.onclick=function(e){e.preventDefault();var n=KEYS[(KEYS.indexOf(v)+1)%KEYS.length];location.search='?v='+n;};}
  document.querySelectorAll('[data-v]').forEach(function(el){if(el.getAttribute('data-v')===v)el.classList.add('on')});
  document.documentElement.classList.add('ab-ready');
  var utm={};new URLSearchParams(location.search).forEach(function(val,k){if(/^utm_|fbclid|gclid/.test(k))utm[k]=val});
  function hit(t){if(PREVIEW)return;try{fetch(API+'/lp/'+LP+'/hit',{method:'POST',headers:{'Content-Type':'application/json',Accept:'application/json'},body:JSON.stringify({v:v,t:t}),keepalive:true})}catch(e){}}
  var once={};function hitOnce(t){if(once[t])return;once[t]=1;hit(t)}
  hit('view');
  document.querySelectorAll('[data-call]').forEach(function(a){a.addEventListener('click',function(){hitOnce('call')})});
  document.querySelectorAll('[data-goform]').forEach(function(a){a.addEventListener('click',function(e){e.preventDefault();var f=document.getElementById('form-'+v);if(f)f.scrollIntoView({behavior:'smooth',block:'start'})})});
  document.querySelectorAll('form[data-lead]').forEach(function(f){f.addEventListener('submit',function(e){e.preventDefault();var b=f.querySelector('button');b.disabled=true;
    var d={type:'offre',name:f.name.value,phone:f.phone.value,message:f.message?f.message.value:'',hp:f.hp.value,payload:Object.assign({'Page':@json($lp->title),'Variante':v},utm)};
    fetch(API.replace('/api/site/','/api/lead/'),{method:'POST',headers:{'Content-Type':'application/json',Accept:'application/json'},body:JSON.stringify(d)}).then(function(r){if(!r.ok)throw 0;f.querySelectorAll('input,textarea,label,button').forEach(function(x){x.style.display='none'});f.querySelector('.ok').style.display='block';hitOnce('conversion')}).catch(function(){b.disabled=false;alert('Envoi impossible, appelez-nous directement.')});});});
})();
</script>
</body>
</html>
