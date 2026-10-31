{{-- Page d'offre Joow Ads : une page, un objectif. Variantes A/B dans le HTML (tirage fixe par visiteur), formulaire à étapes commun. --}}
@php
    $goalCall = $lp->goal === 'calls';
    $fixed = $lp->winner ?: (count($variants) === 1 ? $variants[0]['key'] : null);
    $c = $content;
    $form = $c['form'];
    $steps = $form['steps'] ?? [];
    $hasProof = $rating && $reviews;
    $stars = $rating ? str_repeat('★', (int) round((float) $rating)) : '';
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
:root{--c:{{ $color }};--ink:#0f172a;--mut:#475569;--bg:#f8fafc}
*{box-sizing:border-box}html,body{margin:0}html{scroll-behavior:smooth}body{font-family:Inter,system-ui,sans-serif;color:var(--ink);background:#fff;-webkit-font-smoothing:antialiased}
a{color:inherit}.wrap{max-width:1120px;margin:0 auto;padding:0 20px}
.urg{background:var(--ink);color:#fff;text-align:center;font-size:13px;font-weight:600;padding:9px 16px}.urg b{color:#fde68a}
.top{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:14px 0}
.brand{font-weight:800;font-size:17px;text-decoration:none}.brand small{display:block;font-weight:500;font-size:12px;color:var(--mut)}
.tel{display:inline-flex;align-items:center;gap:8px;font-weight:700;text-decoration:none;padding:9px 14px;border-radius:999px;border:2px solid var(--c);color:var(--c);font-size:14px;white-space:nowrap}
.tel .m-only{display:none}@media(max-width:520px){.tel .n{display:none}.tel .m-only{display:inline}}
.hero{display:grid;gap:32px;padding:20px 0 48px;align-items:start}@media(min-width:960px){.hero{grid-template-columns:1.1fr .9fr;gap:48px;padding:40px 0 72px}}
.rate{display:inline-flex;align-items:center;gap:8px;background:#fff7ed;border:1px solid #fed7aa;color:#9a3412;font-weight:700;font-size:13px;padding:7px 12px;border-radius:999px}.rate b{color:#f59e0b;letter-spacing:1px}
h1{font-size:clamp(32px,5.6vw,54px);line-height:1.04;letter-spacing:-.025em;margin:16px 0 14px;font-weight:900}
.sub{font-size:18px;line-height:1.55;color:var(--mut);margin:0 0 22px}
.offer{display:flex;gap:12px;align-items:center;background:linear-gradient(135deg,var(--c),color-mix(in srgb,var(--c) 70%,#000));color:#fff;border-radius:18px;padding:16px 18px;font-weight:800;font-size:17px;margin-bottom:20px;box-shadow:0 18px 40px -18px var(--c)}
.offer span{font-size:26px}
ul.b{list-style:none;padding:0;margin:0 0 22px;display:grid;gap:11px}ul.b li{display:flex;gap:11px;font-weight:600;font-size:16px}ul.b li:before{content:"✓";color:#fff;background:#16a34a;border-radius:999px;width:22px;height:22px;flex:none;display:grid;place-items:center;font-size:12px;font-weight:900}
.chips{display:flex;flex-wrap:wrap;gap:8px}.chips span{display:inline-flex;gap:6px;align-items:center;background:var(--bg);border:1px solid #e2e8f0;border-radius:999px;padding:7px 12px;font-size:13px;font-weight:600;color:#334155}
.photo{width:100%;aspect-ratio:16/9;object-fit:cover;border-radius:20px;margin-top:22px;display:block;box-shadow:0 20px 50px -24px rgba(15,23,42,.45)}@media(max-width:959px){.photo{display:none}.card{position:static}}
/* Formulaire à étapes */
.card{position:sticky;top:16px;background:#fff;border-radius:24px;box-shadow:0 40px 80px -30px rgba(15,23,42,.35),0 0 0 1px rgba(15,23,42,.07);padding:24px;overflow:hidden}
.card h2{margin:0;font-size:22px;font-weight:800}.card p.m{margin:4px 0 0;color:var(--mut);font-size:14px}
.bar{height:6px;background:#eef2f7;border-radius:999px;margin:18px 0 6px;overflow:hidden}.bar i{display:block;height:100%;width:0;background:var(--c);border-radius:999px;transition:width .35s}
.stepn{font-size:12px;font-weight:700;color:var(--mut);text-transform:uppercase;letter-spacing:.06em}
.q{font-size:19px;font-weight:800;margin:14px 0 14px;line-height:1.3}
.st{display:none;animation:in .3s ease}.st.on{display:block}@keyframes in{from{opacity:0;transform:translateX(12px)}to{opacity:1;transform:none}}
.opts{display:grid;gap:10px}.opts.two{grid-template-columns:1fr 1fr}
.opt{display:flex;align-items:center;gap:10px;width:100%;text-align:left;font:600 15px Inter,sans-serif;background:#fff;border:2px solid #e2e8f0;border-radius:14px;padding:14px 16px;cursor:pointer;color:var(--ink);transition:.15s}
.opt:hover{border-color:var(--c);background:color-mix(in srgb,var(--c) 6%,#fff)}.opt.sel{border-color:var(--c);background:color-mix(in srgb,var(--c) 10%,#fff)}
.opt .dot{width:18px;height:18px;border-radius:999px;border:2px solid #cbd5e1;flex:none}.opt.sel .dot{border-color:var(--c);background:var(--c);box-shadow:inset 0 0 0 3px #fff}
label{display:block;font-size:13px;font-weight:700;margin:12px 0 5px}input,textarea{width:100%;font:500 16px Inter,sans-serif;border:2px solid #e2e8f0;border-radius:14px;padding:13px 15px;outline:none}input:focus,textarea:focus{border-color:var(--c)}
.btn{display:flex;width:100%;justify-content:center;align-items:center;gap:8px;border:0;cursor:pointer;background:var(--c);color:#fff;font:800 17px Inter,sans-serif;padding:17px;border-radius:15px;margin-top:16px;text-decoration:none;box-shadow:0 16px 34px -12px var(--c);transition:transform .15s}.btn:hover{transform:translateY(-1px)}.btn:disabled{opacity:.5;cursor:default}
.btn.alt{background:#fff;color:var(--ink);border:2px solid #e2e8f0;box-shadow:none;font-size:15px;padding:13px}
.nav{display:flex;justify-content:space-between;align-items:center;margin-top:12px}.back{background:none;border:0;color:var(--mut);font:600 14px Inter,system-ui,sans-serif;cursor:pointer;padding:6px 0}
.safe{display:flex;gap:6px;justify-content:center;align-items:center;font-size:12px;color:#64748b;margin-top:12px}
.done{display:none;text-align:center;padding:24px 4px}.done .ic{width:64px;height:64px;border-radius:999px;background:#dcfce7;color:#16a34a;display:grid;place-items:center;font-size:30px;margin:0 auto 14px}
/* Sections */
section.s{padding:56px 0}section.s.alt{background:var(--bg)}
.tt{font-size:clamp(26px,3.6vw,36px);font-weight:900;letter-spacing:-.02em;margin:0 0 8px;text-align:center}.ts{color:var(--mut);text-align:center;margin:0 auto 32px;max-width:620px}
.proof{display:flex;flex-wrap:wrap;justify-content:center;gap:12px 36px;padding:22px 0;border-block:1px solid #eef2f7}.proof div{text-align:center}.proof b{display:block;font-size:24px;font-weight:900}.proof span{font-size:13px;color:var(--mut)}
.revs{display:grid;gap:16px}@media(min-width:800px){.revs{grid-template-columns:repeat(3,1fr)}}
.rev{background:#fff;border-radius:18px;padding:22px;box-shadow:0 0 0 1px #eef2f7}.rev .s{color:#f59e0b;letter-spacing:2px}.rev p{font-size:15px;line-height:1.6;color:#334155;margin:10px 0}.rev cite{font-style:normal;font-weight:700;font-size:14px}
.how{display:grid;gap:16px;counter-reset:n}@media(min-width:800px){.how{grid-template-columns:repeat(3,1fr)}}
.how div{background:#fff;border-radius:18px;padding:24px;box-shadow:0 0 0 1px #eef2f7;counter-increment:n}.how div:before{content:counter(n);display:grid;place-items:center;width:40px;height:40px;border-radius:12px;background:var(--c);color:#fff;font-weight:900;margin-bottom:12px}.how h3{margin:0 0 6px;font-size:18px}.how p{margin:0;color:var(--mut);line-height:1.5}
.faq{max-width:760px;margin:0 auto;display:grid;gap:10px}.faq details{background:#fff;border-radius:16px;box-shadow:0 0 0 1px #e2e8f0;padding:18px 20px}.faq summary{font-weight:700;cursor:pointer;list-style:none;display:flex;justify-content:space-between;gap:12px}.faq summary:after{content:"+";color:var(--c);font-weight:900}.faq details[open] summary:after{content:"–"}.faq p{color:var(--mut);line-height:1.6;margin:10px 0 0}
.final{background:linear-gradient(135deg,var(--ink),#1e293b);color:#fff;border-radius:28px;padding:44px 24px;text-align:center}.final h2{font-size:clamp(26px,4vw,38px);margin:0 0 10px;font-weight:900}.final p{color:#cbd5e1;margin:0 0 22px}.final .btn{max-width:360px;margin:0 auto}
footer{padding:26px 0 100px;font-size:13px;color:#64748b;text-align:center}
.sticky{position:fixed;inset:auto 12px 12px 12px;z-index:50;display:flex;gap:8px;transition:transform .25s}@media(min-width:960px){.sticky{display:none}}.sticky.hide{transform:translateY(140%)}
.sticky a{flex:1}.sticky .btn{margin:0;padding:15px 12px;font-size:15px;white-space:nowrap}
[data-v]{display:none!important}html.ab-ready [data-v].on{display:block!important}
.pv{position:fixed;top:0;inset-inline:0;z-index:99;background:#111827;color:#fbbf24;font:600 12px Inter,system-ui,sans-serif;text-align:center;padding:6px}
</style>
</head>
<body>
@if($preview)<style>body{padding-top:28px}</style><div class="pv">Aperçu · variante <span id="pv-v"></span> · <a href="#" id="pv-sw" style="color:#fff">changer</a></div>@endif
@if(!empty($c['urgency']))<div class="urg">⏳ <b>{{ $c['urgency'] }}</b></div>@endif
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
        <h1>{{ $v['headline'] ?? '' }}</h1>
        @if(!empty($v['subheadline']))<p class="sub">{{ $v['subheadline'] }}</p>@endif
        @if(!empty($v['offer']))<div class="offer"><span>🎁</span>{{ $v['offer'] }}</div>@endif
        @if(!empty($v['bullets']))<ul class="b">@foreach(array_slice((array) $v['bullets'], 0, 4) as $bl)<li>{{ $bl }}</li>@endforeach</ul>@endif
        @php $img = $v['image'] ?? $hero; @endphp
        @if($img)<img class="photo" src="{{ $img }}" alt="{{ $name }}" loading="eager">@endif
      </div>
      @endforeach
      @if(!empty($c['guarantees']))<div class="chips" style="margin-top:20px">@foreach($c['guarantees'] as $g)<span>🛡 {{ $g }}</span>@endforeach</div>@endif
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

@if($hasProof || !empty($c['guarantees']))
<div class="wrap"><div class="proof">
  @if($hasProof)<div><b>{{ number_format((float) $rating, 1, ',', '') }}/5</b><span>note Google</span></div><div><b>{{ $reviews }}</b><span>avis clients</span></div>@endif
  @foreach(array_slice((array) $c['guarantees'], 0, 2) as $g)<div><b>✓</b><span>{{ $g }}</span></div>@endforeach
</div></div>
@endif

@if(count($quotes))
<section class="s alt"><div class="wrap">
  <h2 class="tt">Ils nous ont fait confiance</h2><p class="ts">Avis Google vérifiés de nos clients{{ $city ? ' à '.$city.' et alentours' : '' }}.</p>
  <div class="revs">@foreach($quotes as $r)<div class="rev"><div class="s">{{ str_repeat('★', (int) ($r['rating'] ?? 5)) }}</div><p>« {{ \Illuminate\Support\Str::limit($r['text'], 240) }} »</p><cite>{{ $r['author'] ?? 'Client' }}</cite></div>@endforeach</div>
</div></section>
@endif

@if(!empty($c['process']))
<section class="s"><div class="wrap">
  <h2 class="tt">Comment ça se passe ?</h2><p class="ts">Simple, rapide et sans engagement.</p>
  <div class="how">@foreach($c['process'] as $p)<div><h3>{{ $p['title'] }}</h3><p>{{ $p['text'] }}</p></div>@endforeach</div>
</div></section>
@endif

@if(!empty($c['faq']))
<section class="s alt"><div class="wrap">
  <h2 class="tt">Vos questions</h2><p class="ts">Tout ce qu'il faut savoir avant de nous contacter.</p>
  <div class="faq">@foreach($c['faq'] as $q)<details><summary>{{ $q['q'] }}</summary><p>{{ $q['a'] }}</p></details>@endforeach</div>
</div></section>
@endif

<section class="s"><div class="wrap"><div class="final">
  @foreach($variants as $v)<div data-v="{{ $v['key'] }}"><h2>{{ ($v['offer'] ?? '') ?: ($v['headline'] ?? '') }}</h2></div>@endforeach
  <p>{{ $form['subtitle'] }}</p>
  <a class="btn" href="#form" data-goform>{{ $form['button'] }} →</a>
  @if($phoneHref)<p style="margin:16px 0 0">ou appelez le <a href="{{ $phoneHref }}" data-call style="color:#fff;font-weight:700">{{ $phone }}</a></p>@endif
</div></div></section>

<footer><a href="{{ $siteUrl }}">{{ $name }}</a>@if($city) · {{ $city }}@endif · <a href="{{ $siteUrl }}">Voir le site</a></footer>
<div class="sticky" id="sticky">
  @if($phoneHref)<a class="btn alt" href="{{ $phoneHref }}" data-call>📞 Appeler</a>@endif
  <a class="btn" href="#form" data-goform>{{ $form['button'] }}</a>
</div>
<script>
(function(){
  var KEYS=@json(array_column($variants, 'key')), FIXED=@json($fixed), API=@json($api), LP=@json($lp->id), PREVIEW=@json($preview), TITLE=@json($lp->title);
  var ck='joow_ab_'+LP, v=FIXED;
  if(!v){try{v=localStorage.getItem(ck)}catch(e){}if(KEYS.indexOf(v)<0){v=KEYS[Math.floor(Math.random()*KEYS.length)];try{localStorage.setItem(ck,v)}catch(e){}}}
  if(PREVIEW){var qv=new URLSearchParams(location.search).get('v');if(KEYS.indexOf(qv)>=0)v=qv;var s=document.getElementById('pv-v');if(s)s.textContent=v;var sw=document.getElementById('pv-sw');if(sw)sw.onclick=function(e){e.preventDefault();location.search='?v='+KEYS[(KEYS.indexOf(v)+1)%KEYS.length];};}
  document.querySelectorAll('[data-v]').forEach(function(el){if(el.getAttribute('data-v')===v)el.classList.add('on')});
  document.documentElement.classList.add('ab-ready');
  var utm={};new URLSearchParams(location.search).forEach(function(val,k){if(/^utm_|fbclid|gclid/.test(k))utm[k]=val});
  function hit(t){if(PREVIEW)return;try{fetch(API+'/lp/'+LP+'/hit',{method:'POST',headers:{'Content-Type':'application/json',Accept:'application/json'},body:JSON.stringify({v:v,t:t}),keepalive:true})}catch(e){}}
  var once={};function hitOnce(t){if(once[t])return;once[t]=1;hit(t)}
  hit('view');
  document.querySelectorAll('[data-call]').forEach(function(a){a.addEventListener('click',function(){hitOnce('call')})});
  document.querySelectorAll('[data-goform]').forEach(function(a){a.addEventListener('click',function(e){e.preventDefault();document.getElementById('form').scrollIntoView({behavior:'smooth',block:'start'})})});
  // Barre mobile masquée quand le formulaire est visible
  var sticky=document.getElementById('sticky'),card=document.getElementById('form');
  if('IntersectionObserver' in window){new IntersectionObserver(function(e){sticky.classList.toggle('hide',e[0].isIntersecting)},{threshold:.2}).observe(card);}
  // Formulaire à étapes
  var f=document.querySelector('form[data-lead]'),steps=[].slice.call(f.querySelectorAll('.st')),cur=0,answers={},bar=document.getElementById('f-bar'),num=document.getElementById('f-n'),back=document.getElementById('f-back');
  function show(i){cur=i;steps.forEach(function(s,k){s.classList.toggle('on',k===i)});bar.style.width=Math.round((i)/(steps.length)*100+100/steps.length*.5)+'%';num.textContent=steps.length>1?'Étape '+(i+1)+' sur '+steps.length:'';back.hidden=i===0;var inp=steps[i].querySelector('[data-input],input[name=name]');if(inp&&i>0)setTimeout(function(){inp.focus({preventScroll:true})},250)}
  function next(){if(cur<steps.length-1)show(cur+1)}
  steps.forEach(function(s){
    var type=s.getAttribute('data-type'),key=s.getAttribute('data-key'),q=s.getAttribute('data-q');
    s.querySelectorAll('.opt').forEach(function(b){b.addEventListener('click',function(){
      if(type==='multi'){b.classList.toggle('sel');answers[q]=[].slice.call(s.querySelectorAll('.opt.sel')).map(function(x){return x.getAttribute('data-val')}).join(', ');}
      else{s.querySelectorAll('.opt').forEach(function(x){x.classList.remove('sel')});b.classList.add('sel');answers[q]=b.getAttribute('data-val');setTimeout(next,220);}
    })});
    var nb=s.querySelector('[data-next]');if(nb)nb.addEventListener('click',function(){var inp=s.querySelector('[data-input]');if(inp)answers[q]=inp.value.trim();
      if(s.getAttribute('data-req')==='1'&&!answers[q]){(inp||s).animate&&(inp||s).animate([{transform:'translateX(-6px)'},{transform:'translateX(6px)'},{transform:'none'}],{duration:250});return;}next()});
    var inp=s.querySelector('[data-input]');if(inp)inp.addEventListener('keydown',function(e){if(e.key==='Enter'){e.preventDefault();nb.click()}});
  });
  back.addEventListener('click',function(){if(cur>0)show(cur-1)});
  show(0);
  f.addEventListener('submit',function(e){e.preventDefault();var b=f.querySelector('button[type=submit]');
    if(!f.name.value.trim()||f.phone.value.replace(/\D/g,'').length<9){(f.phone.value?f.phone:f.name).focus();return;}
    b.disabled=true;
    var payload=Object.assign({'Page':TITLE,'Variante':v},answers,utm);if(f.postcode)payload['Code postal']=f.postcode.value;
    var d={type:'offre',name:f.name.value,phone:f.phone.value,email:f.email?f.email.value:null,message:f.message?f.message.value:Object.keys(answers).map(function(k){return k+' : '+answers[k]}).join('\n'),hp:f.hp.value,payload:payload};
    fetch(API.replace('/api/site/','/api/lead/'),{method:'POST',headers:{'Content-Type':'application/json',Accept:'application/json'},body:JSON.stringify(d)}).then(function(r){if(!r.ok)throw 0;
      f.style.display='none';document.getElementById('f-head').style.display='none';document.getElementById('f-done').style.display='block';hitOnce('conversion');
    }).catch(function(){b.disabled=false;alert('Envoi impossible, appelez-nous directement.')});
  });
})();
</script>
</body>
</html>
