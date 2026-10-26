// Version 2 : storytelling + motion design (particules, typographie cinétique, light sweeps, 3D).
// Usage : OUT=… FF=… node scripts/ad/render2.mjs [--preview]
import { chromium } from 'playwright';
import fs from 'node:fs';
import path from 'node:path';
import { execFileSync } from 'node:child_process';

const OUT = process.env.OUT; const FF = process.env.FF; const BRAND = '/home/user/joow/public/brand';
const PREVIEW = process.argv.includes('--preview');
const W = 1080, H = 1920, FPS = 30;
const seq = (name) => JSON.parse(fs.readFileSync(path.join(OUT, name, 'seq.json'), 'utf8')).map((s) => ({ src: 'file://' + path.join(OUT, name, s.f), hold: s.hold }));
const S = { tw: seq('m1-typewriter'), typing: seq('m2-typing'), sel: seq('m3-selected'), ready: seq('m4-ready'), scroll: seq('m5-site-scroll'), studio: seq('d1-studio') };
const png = (n) => 'file://' + path.join(OUT, n + '.png');
const svg = (n) => 'file://' + path.join(BRAND, n);

/* ───────── Timeline (s) ───────── */
// Durées calées sur la voix off (vo1..vo8, lues à 1,08×) : chaque scène accueille sa phrase.
const T = { problem: [0, 6.6], cost: [6.6, 11.4], type: [11.4, 16.0], build: [16.0, 21.6], reveal: [21.6, 26.4], studio: [26.4, 31.4], notif: [31.4, 36.6], cta: [36.6, 41.5] };
const TOTAL = T.cta[1];

const html = `<!doctype html><html lang="fr"><head><meta charset="utf-8">
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;700&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0}
html,body{width:${W}px;height:${H}px;overflow:hidden;background:transparent;font-family:Inter,system-ui,sans-serif;color:#fff}
.display{font-family:'Space Grotesk',sans-serif;font-weight:700;letter-spacing:-.03em}
#stage{position:relative;width:${W}px;height:${H}px;overflow:hidden}
.bg{position:absolute;inset:0;background:radial-gradient(70% 45% at 20% 8%,rgba(99,102,241,.34),transparent 60%),radial-gradient(60% 40% at 90% 30%,rgba(168,85,247,.26),transparent 60%),radial-gradient(50% 40% at 40% 95%,rgba(56,189,248,.16),transparent 60%),rgba(6,6,11,.55)}
.aurora{position:absolute;border-radius:50%;filter:blur(90px);opacity:.5}
#fx{position:absolute;inset:0;z-index:1}
.grain{position:absolute;inset:0;opacity:.26;mix-blend-mode:overlay;z-index:40;pointer-events:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='160' height='160'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='.85' numOctaves='2'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='.4'/%3E%3C/svg%3E")}
.vignette{position:absolute;inset:0;z-index:39;pointer-events:none;background:radial-gradient(80% 60% at 50% 50%,transparent 55%,rgba(0,0,0,.55) 100%)}
.scene{position:absolute;inset:0;display:none;z-index:2}
.scene.on{display:block}
.phone{position:absolute;width:600px;height:1300px;border-radius:84px;background:#101018;border:10px solid #1c1c2b;box-shadow:0 70px 140px -40px rgba(0,0,0,.95),0 0 0 2px rgba(255,255,255,.08),0 0 110px rgba(129,140,248,.22);overflow:hidden;transform-style:preserve-3d}
.phone img{position:absolute;left:0;top:0;width:100%;height:100%;object-fit:cover;object-position:top}
.phone .notch{position:absolute;left:50%;top:14px;width:180px;height:38px;transform:translateX(-50%);border-radius:999px;background:#1c1c2b;z-index:3}
.sweep{position:absolute;inset:0;z-index:4;background:linear-gradient(115deg,transparent 35%,rgba(255,255,255,.22) 50%,transparent 65%);transform:translateX(-120%);pointer-events:none}
.win{position:absolute;border-radius:28px;background:#14141f;border:1px solid rgba(255,255,255,.1);box-shadow:0 60px 120px -40px rgba(0,0,0,.95),0 0 90px rgba(129,140,248,.18);overflow:hidden}
.win .bar{height:52px;display:flex;align-items:center;gap:10px;padding:0 22px;border-bottom:1px solid rgba(255,255,255,.07);background:#14141f}
.win .bar i{width:14px;height:14px;border-radius:50%;display:inline-block}
.win .bar span{margin-left:12px;font-size:17px;color:#94a3b8;background:rgba(255,255,255,.05);padding:5px 14px;border-radius:8px}
.win img{display:block;width:100%}
.h1{font-size:118px;line-height:1.0}
.h2{font-size:82px;line-height:1.04}
.kicker{font-size:28px;font-weight:700;letter-spacing:.34em;text-transform:uppercase;color:#a5b4fc}
.sub{font-size:40px;line-height:1.3;color:#cbd5e1;font-weight:500}
.grad{background:linear-gradient(100deg,#818cf8 10%,#e879f9 45%,#38bdf8 80%);-webkit-background-clip:text;background-clip:text;color:transparent}
.w{display:inline-block;will-change:transform,opacity,filter}
.chip{display:inline-flex;align-items:center;gap:14px;border-radius:999px;border:1px solid rgba(255,255,255,.14);background:rgba(13,13,20,.84);backdrop-filter:blur(10px);padding:18px 30px;font-size:30px;font-weight:600;color:#e2e8f0;box-shadow:0 30px 60px -30px rgba(0,0,0,.9);white-space:nowrap}
.chip b{color:#fff}
.dot{width:16px;height:16px;border-radius:50%;background:#34d399;box-shadow:0 0 18px #34d399;flex:none}
.btn{display:inline-flex;align-items:center;gap:14px;border-radius:28px;padding:36px 64px;font-size:46px;font-weight:700;color:#fff;background:linear-gradient(135deg,#6366f1,#a855f7);box-shadow:0 30px 80px -20px rgba(124,92,246,.85)}
.gcard{position:absolute;width:780px;border-radius:34px;background:#fff;color:#1f1f1f;padding:34px 38px;box-shadow:0 60px 120px -40px rgba(0,0,0,.9)}
.gcard .nm{font-size:42px;font-weight:700;color:#202124}
.gcard .st{margin-top:10px;font-size:30px;color:#5f6368;display:flex;align-items:center;gap:12px}
.gcard .st b{color:#e37400;letter-spacing:2px}
.gcard .row{margin-top:26px;display:flex;gap:14px}
.gcard .row span{flex:1;border:1px solid #dadce0;border-radius:999px;padding:16px 0;text-align:center;font-size:26px;font-weight:600;color:#1a73e8}
.gcard .row span.off{color:#9aa0a6;border-style:dashed;position:relative}
.notif{position:absolute;left:70px;right:70px;border-radius:34px;background:rgba(20,20,31,.92);backdrop-filter:blur(14px);border:1px solid rgba(255,255,255,.12);padding:28px 32px;display:flex;gap:24px;align-items:center;box-shadow:0 40px 80px -30px rgba(0,0,0,.9)}
.notif .ic{width:84px;height:84px;border-radius:24px;display:grid;place-items:center;font-size:40px;flex:none;background:linear-gradient(135deg,#6366f1,#a855f7)}
.notif .t{font-size:32px;font-weight:700;color:#fff}
.notif .d{font-size:26px;color:#94a3b8;margin-top:4px}
.notif .tm{margin-left:auto;font-size:24px;color:#64748b;flex:none}
.stat{position:absolute;text-align:center}
.stat .n{font-family:'Space Grotesk',sans-serif;font-weight:700;font-size:96px;line-height:1;color:#fff}
.stat .l{font-size:28px;color:#94a3b8;margin-top:10px;font-weight:500}
.slab{position:absolute;left:0;right:0;height:14px;background:linear-gradient(90deg,transparent,#e879f9,transparent);filter:blur(2px)}
.slice{position:absolute;left:50%;width:600px;border-radius:28px;overflow:hidden;box-shadow:0 40px 80px -30px rgba(0,0,0,.95);border:1px solid rgba(255,255,255,.12)}
.slice img{display:block;width:100%}
</style></head><body><div id="stage">
<div class="bg"></div>
<div class="aurora" id="au1" style="left:-200px;top:-200px;width:900px;height:900px;background:radial-gradient(circle,rgba(99,102,241,.7),transparent 70%)"></div>
<div class="aurora" id="au2" style="right:-300px;top:500px;width:900px;height:900px;background:radial-gradient(circle,rgba(168,85,247,.55),transparent 70%)"></div>
<div class="aurora" id="au3" style="left:100px;bottom:-300px;width:900px;height:900px;background:radial-gradient(circle,rgba(56,189,248,.4),transparent 70%)"></div>
<canvas id="fx" width="${W}" height="${H}"></canvas>
<div class="vignette"></div><div class="grain"></div>

<!-- 1. PROBLÈME : vos clients vous cherchent -->
<div class="scene" id="problem">
  <div id="pK" class="kicker" style="position:absolute;left:0;right:0;top:170px;text-align:center">Chaque jour</div>
  <div id="pT" class="display h1" style="position:absolute;left:60px;right:60px;top:240px;text-align:center"></div>
  <div class="gcard" id="gcard" style="left:150px;top:760px">
    <div class="nm">Plomberie Durand</div>
    <div class="st"><b>★★★★★</b> 4,8 · 212 avis · Plombier · Nantes</div>
    <div class="row"><span>Appeler</span><span>Itinéraire</span><span class="off" id="gsite">Site web</span></div>
  </div>
  <div id="pS" class="sub" style="position:absolute;left:100px;right:100px;top:1240px;text-align:center"></div>
</div>

<!-- 2. LE COÛT : agence -->
<div class="scene" id="cost">
  <div id="cT" class="display h1" style="position:absolute;left:60px;right:60px;top:430px;text-align:center"></div>
  <div class="slab" id="cSlab" style="top:980px;opacity:0"></div>
  <div id="cT2" class="display h1 grad" style="position:absolute;left:60px;right:60px;top:1060px;text-align:center"></div>
</div>

<!-- 3. LA BASCULE : tapez le nom -->
<div class="scene" id="type">
  <div id="tK" class="kicker" style="position:absolute;left:0;right:0;top:150px;text-align:center">Avec Joow</div>
  <div id="tT" class="display h2" style="position:absolute;left:60px;right:60px;top:210px;text-align:center"></div>
  <div class="phone" id="tPhone" style="left:240px;top:440px"><div class="notch"></div><img id="tImg"><div class="sweep" id="tSweep"></div></div>
  <div class="chip" id="tChip" style="position:absolute;left:50%;top:1740px;transform:translateX(-50%)"><span class="dot"></span><span>Votre fiche Google, <b>retrouvée</b></span></div>
</div>

<!-- 4. CONSTRUCTION : l'IA assemble le site (tranches 3D) -->
<div class="scene" id="build">
  <div id="bK" class="kicker" style="position:absolute;left:0;right:0;top:150px;text-align:center">L'IA travaille</div>
  <div id="bT" class="display h2" style="position:absolute;left:60px;right:60px;top:210px;text-align:center"></div>
  <div id="bSlices" style="position:absolute;inset:0;perspective:2000px"></div>
  <div id="bTimer" class="display" style="position:absolute;left:0;right:0;top:1560px;text-align:center;font-size:140px;line-height:1">0 s</div>
  <div id="bL" style="position:absolute;left:0;right:0;top:1710px;text-align:center;font-size:30px;color:#94a3b8;font-weight:500">textes · photos · avis · design · modules</div>
</div>

<!-- 5. RÉVÉLATION -->
<div class="scene" id="reveal">
  <div id="rT" class="display h2" style="position:absolute;left:60px;right:60px;top:200px;text-align:center"></div>
  <div class="phone" id="rPhone" style="left:240px;top:420px"><div class="notch"></div><img id="rImg"><div class="sweep" id="rSweep"></div></div>
  <div class="chip" id="rC1" style="position:absolute;left:50px;top:880px"><span style="color:#fbbf24;font-size:28px">★★★★★</span><span><b id="rN1">0</b> avis Google intégrés</span></div>
  <div class="chip" id="rC2" style="position:absolute;right:40px;top:1180px"><span class="dot"></span><span>Réservation <b>en ligne</b></span></div>
  <div class="chip" id="rC3" style="position:absolute;left:70px;top:1500px"><span>✨</span><span>Design <b>adapté à votre métier</b></span></div>
</div>

<!-- 6. STUDIO -->
<div class="scene" id="studio">
  <div id="sK" class="kicker" style="position:absolute;left:0;right:0;top:150px;text-align:center">Vous changez d'avis ?</div>
  <div id="sT" class="display h2" style="position:absolute;left:60px;right:60px;top:210px;text-align:center"></div>
  <div class="win" id="sWin" style="left:-130px;top:560px;width:1340px"><div class="bar"><i style="background:#f87171"></i><i style="background:#fbbf24"></i><i style="background:#34d399"></i><span>app.joow.fr/studio</span></div><img id="sImg"></div>
  <div class="chip" id="sChip" style="position:absolute;left:50%;top:1500px;transform:translateX(-50%);max-width:980px;white-space:normal;text-align:center"><span>✨</span><span>« Passe le site en thème sombre et ajoute une FAQ »</span></div>
  <div class="chip" id="sDone" style="position:absolute;left:50%;top:1640px;transform:translateX(-50%)"><span class="dot"></span><span><b>Appliqué</b> en 11 secondes</span></div>
</div>

<!-- 7. PENDANT QUE VOUS TRAVAILLEZ : notifications -->
<div class="scene" id="notif">
  <div id="nK" class="kicker" style="position:absolute;left:0;right:0;top:150px;text-align:center">Pendant que vous travaillez</div>
  <div id="nT" class="display h2" style="position:absolute;left:60px;right:60px;top:210px;text-align:center"></div>
  <div class="win" id="nWin" style="left:90px;top:520px;width:900px;height:620px"><div class="bar"><i style="background:#f87171"></i><i style="background:#fbbf24"></i><i style="background:#34d399"></i><span>app.joow.fr/reservations</span></div><img src="${png('reservations')}"></div>
  <div class="notif" id="n1" style="top:1230px"><div class="ic">📅</div><div><div class="t">Nouvelle réservation</div><div class="d">Ce soir 20h30 · 4 couverts · Camille R.</div></div><div class="tm">à l'instant</div></div>
  <div class="notif" id="n2" style="top:1390px"><div class="ic">📩</div><div><div class="t">Demande de devis reçue</div><div class="d">Rénovation salle de bain · 12 m²</div></div><div class="tm">il y a 2 min</div></div>
  <div class="notif" id="n3" style="top:1550px"><div class="ic">💬</div><div><div class="t">L'assistant IA a répondu</div><div class="d">« Oui, la terrasse est ouverte jusqu'à 23h »</div></div><div class="tm">il y a 5 min</div></div>
</div>

<!-- 8. CTA -->
<div class="scene" id="cta">
  <img id="ctaLogo" src="${svg('joow-logo-white.svg')}" style="position:absolute;left:50%;top:330px;width:560px;transform:translateX(-50%)">
  <div id="ctaT" class="display h1" style="position:absolute;left:60px;right:60px;top:600px;text-align:center;font-size:104px"></div>
  <div class="stat" id="st1" style="left:60px;top:960px;width:320px"><div class="n" id="stn1">0</div><div class="l">sites créés</div></div>
  <div class="stat" id="st2" style="left:380px;top:960px;width:320px"><div class="n" id="stn2">0</div><div class="l">note moyenne</div></div>
  <div class="stat" id="st3" style="left:700px;top:960px;width:320px"><div class="n" id="stn3">0 s</div><div class="l">pour votre site</div></div>
  <div id="ctaBtn" class="btn" style="position:absolute;left:50%;top:1200px;transform:translateX(-50%)">Créer mon site gratuitement →</div>
  <div id="ctaUrl" class="display" style="position:absolute;left:0;right:0;top:1400px;text-align:center;font-size:66px">app.joow.fr</div>
  <div id="ctaSub" class="sub" style="position:absolute;left:0;right:0;top:1510px;text-align:center;color:#94a3b8;font-size:30px">Sans carte bancaire · aperçu immédiat · modifiable à volonté</div>
</div>
</div>
<script>
const S = ${JSON.stringify(S)};
const T = ${JSON.stringify(T)};
const W=${W},H=${H};
const clamp=(x,a=0,b=1)=>Math.max(a,Math.min(b,x));
const ease=(x)=>1-Math.pow(1-clamp(x),3);
const easeIO=(x)=>{x=clamp(x);return x<.5?4*x*x*x:1-Math.pow(-2*x+2,3)/2;};
const back=(x)=>{x=clamp(x);const c=1.70158;return 1+(c+1)*Math.pow(x-1,3)+c*Math.pow(x-1,2);};
const lerp=(a,b,t)=>a+(b-a)*t;
const frameAt=(s,t)=>{let acc=0;for(const it of s){acc+=it.hold;if(t<acc)return it.src;}return s[s.length-1].src;};
const seqDur=(s)=>s.reduce((a,b)=>a+b.hold,0);
const $=(id)=>document.getElementById(id);
const st=(id,css)=>Object.assign($(id).style,css);
const fmt=(n)=>Math.round(n).toLocaleString('fr-FR');
// Typographie cinétique : mots révélés un à un (flou → net, montée)
const words=(id,text,t,delay=0,gap=.08,dur=.5)=>{const el=$(id);if(el.dataset.txt!==text){el.innerHTML=text.split(' ').map(w=>'<span class="w">'+w+'</span>').join(' ');el.dataset.txt=text;}
  [...el.children].forEach((w,i)=>{const k=ease((t-delay-i*gap)/dur);w.style.opacity=k;w.style.transform='translateY('+((1-k)*40)+'px)';w.style.filter='blur('+((1-k)*14)+'px)';});};
const fadeAll=(ids,t,start,dur=.4)=>{if(t>start){const f=1-ease((t-start)/dur);for(const id of ids)$(id).style.opacity=String(Math.min(parseFloat($(id).style.opacity||1),f));}};
const pop=(id,t,delay=0,from=60,extra='')=>{const k=ease((t-delay)/.6);st(id,{opacity:k,transform:extra+' translateY('+((1-k)*from)+'px)'});};
const scenes=Object.keys(T);
// Particules (déterministes)
const N=420;const rnd=(i,s)=>{let x=Math.sin(i*127.1+s*311.7)*43758.5453;return x-Math.floor(x);};
const P=Array.from({length:N},(_,i)=>({x:rnd(i,1)*W,y:rnd(i,2)*H,r:.8+rnd(i,3)*2.4,sp:.4+rnd(i,4)*1.2,ph:rnd(i,5)*6.28,hue:rnd(i,6)}));
function drawFx(time,mode){const c=$('fx'),g=c.getContext('2d');g.clearRect(0,0,W,H);
  const conv=mode.conv||0;const cx=mode.cx||W/2,cy=mode.cy||H/2;
  for(const p of P){let x=p.x+Math.sin(time*p.sp*.6+p.ph)*40,y=(p.y-time*p.sp*28)%H;if(y<0)y+=H;
    if(conv>0){const k=easeIO(conv);const ang=p.ph+time*.6;const rad=lerp(900,120,k)*(.6+.4*p.hue);x=lerp(x,cx+Math.cos(ang)*rad,k);y=lerp(y,cy+Math.sin(ang)*rad*1.3,k);}
    const a=(.25+.5*p.hue)*(mode.alpha??1);g.beginPath();g.arc(x,y,p.r*(1+conv*.8),0,6.28);g.fillStyle=p.hue>.66?'rgba(56,189,248,'+a+')':(p.hue>.33?'rgba(168,85,247,'+a+')':'rgba(129,140,248,'+a+')');g.fill();}
  if(mode.burst>0){const k=mode.burst;g.beginPath();g.arc(cx,cy,lerp(60,1100,k),0,6.28);g.strokeStyle='rgba(165,180,252,'+(1-k)*.9+')';g.lineWidth=lerp(14,2,k);g.stroke();}
}
// Tranches du site pour la scène de construction (images du défilement mobile)
const slices=[0,22,44,66,88].map((i)=>S.scroll[Math.min(i,S.scroll.length-1)].src);
const bs=$('bSlices');slices.forEach((src,i)=>{const d=document.createElement('div');d.className='slice';d.style.height='300px';d.innerHTML='<img src="'+src+'" style="margin-top:-'+(i===0?0:300)+'px">';d.id='sl'+i;bs.appendChild(d);});

window.seek=function(time){
  $('au1').style.transform='translate('+(Math.sin(time*.35)*70)+'px,'+(Math.cos(time*.3)*50)+'px)';
  $('au2').style.transform='translate('+(Math.cos(time*.28)*80)+'px,'+(Math.sin(time*.33)*60)+'px)';
  $('au3').style.transform='translate('+(Math.sin(time*.25)*60)+'px,'+(Math.cos(time*.4)*40)+'px)';
  for(const s of scenes){const [a,b]=T[s];$(s).classList.toggle('on',time>=a&&time<b);}
  let fx={alpha:.7};
  // 1. PROBLÈME
  if(time>=T.problem[0]&&time<T.problem[1]){const t=time-T.problem[0];const D=T.problem[1]-T.problem[0];
    pop('pK',t,0,30);words('pT','Vos clients vous cherchent sur Google.',t,.15,.09,.55);
    const k=back((t-1.2)/.7);st('gcard',{opacity:ease((t-1.2)/.4),transform:'perspective(1800px) rotateX('+lerp(30,4,k)+'deg) translateY('+((1-k)*120)+'px) scale('+lerp(.8,1,k)+')'});
    const gs=$('gsite');const blink=t>3.0&&Math.floor((t-3.0)*4)%2===0;gs.style.background=blink?'rgba(234,67,53,.12)':'';gs.style.color=blink?'#d93025':'#9aa0a6';gs.textContent=t>3.0?'Aucun site':'Site web';
    words('pS','Et ils ne trouvent… rien.',t,3.6,.1,.5);
    fadeAll(['pK','pT','gcard','pS'],t,D-.4,.4);
  }
  // 2. COÛT
  if(time>=T.cost[0]&&time<T.cost[1]){const t=time-T.cost[0];const D=T.cost[1]-T.cost[0];
    words('cT','Une agence ? 3 semaines. 2 000 €.',t,0,.09,.5);
    const k=ease((t-1.5)/.5);st('cSlab',{opacity:k*(1-ease((t-3.4)/.4)),transform:'scaleX('+k+')'});
    words('cT2','Et si c\\'était 30 secondes ?',t,2.4,.1,.55);
    fadeAll(['cT','cT2','cSlab'],t,D-.35,.35);
  }
  // 3. BASCULE
  if(time>=T.type[0]&&time<T.type[1]){const t=time-T.type[0];const D=T.type[1]-T.type[0];
    pop('tK',t,0,30);words('tT','Tapez le nom de votre établissement.',t,.1,.07,.5);
    const k=back(t/.9);st('tPhone',{opacity:ease(t/.4),transform:'perspective(2200px) rotateY('+(lerp(26,-4,k)+Math.sin(time*.8)*1.5)+'deg) rotateX('+lerp(10,2,k)+'deg) translateY('+((1-k)*220)+'px) scale('+lerp(.85,1,k)+')'});
    const twD=seqDur(S.tw),typD=seqDur(S.typing);const ts=t-.6;
    $('tImg').src=ts<twD?frameAt(S.tw,Math.max(0,ts)):(ts<twD+typD?frameAt(S.typing,ts-twD):S.sel[0].src);
    $('tSweep').style.transform='translateX('+lerp(-120,120,clamp((t-.3)/.9))+'%)';
    const ck=ease((ts-(twD+typD-1.1))/.5);st('tChip',{opacity:ck,transform:'translateX(-50%) translateY('+((1-ck)*40)+'px)'});
    fadeAll(['tK','tT','tPhone','tChip'],t,D-.4,.4);
    fx={alpha:.8,conv:clamp((t-D+1.2)/1.2),cx:W/2,cy:1090};
  }
  // 4. CONSTRUCTION
  if(time>=T.build[0]&&time<T.build[1]){const t=time-T.build[0];const D=T.build[1]-T.build[0];
    pop('bK',t,0,30);words('bT','L\\'IA rédige, choisit, assemble.',t,.1,.08,.5);
    slices.forEach((_,i)=>{const d=.3+i*.42;const k=back((t-d)/.7);const o=ease((t-d)/.3);
      st('sl'+i,{opacity:o,top:(520+i*230)+'px',transform:'translateX(-50%) perspective(2000px) rotateX('+lerp(-70,0,k)+'deg) rotateY('+lerp(i%2?30:-30,0,k)+'deg) translateZ('+lerp(-600,0,k)+'px) translateY('+((1-k)*160)+'px)',zIndex:10+i,boxShadow:'0 40px 80px -30px rgba(0,0,0,.95), 0 0 '+(40*(1-clamp((t-d-.7)/.6)))+'px rgba(165,180,252,.6)'});});
    const p=clamp((t-.2)/(D-1.0));$('bTimer').textContent=Math.round(p*28)+' s';$('bTimer').style.transform='scale('+(1+Math.sin(t*5)*.015)+')';
    fadeAll(['bK','bT','bSlices','bTimer','bL'],t,D-.35,.35);
    fx={alpha:.9,conv:1-clamp(t/1.0),cx:W/2,cy:900,burst:clamp((t)/0.9)};
  }
  // 5. RÉVÉLATION
  if(time>=T.reveal[0]&&time<T.reveal[1]){const t=time-T.reveal[0];const D=T.reveal[1]-T.reveal[0];
    words('rT','Votre site est prêt.',t,.15,.1,.5);
    const k=back(t/.9);st('rPhone',{opacity:ease(t/.35),transform:'perspective(2200px) rotateY('+(lerp(-24,6,k)+Math.sin(time*.9)*1.5)+'deg) rotateX('+lerp(12,2,k)+'deg) translateY('+((1-k)*200)+'px) scale('+lerp(.82,1,k)+')'});
    $('rSweep').style.transform='translateX('+lerp(-120,120,clamp((t-.5)/1.0))+'%)';
    const scrollStart=1.4;$('rImg').src=t<scrollStart?S.ready[0].src:frameAt(S.scroll,(t-scrollStart)*1.5);
    [['rC1',1.2],['rC2',1.8],['rC3',2.4]].forEach(([id,d])=>{const kk=back((t-d)/.6);st(id,{opacity:ease((t-d)/.3),transform:'translateY('+((1-kk)*40)+'px) scale('+lerp(.85,1,kk)+')'});});
    $('rN1').textContent=fmt(33272*ease((t-1.2)/1.4));
    fadeAll(['rT','rPhone','rC1','rC2','rC3'],t,D-.4,.4);
    fx={alpha:.6,burst:clamp(t/1.1)};
  }
  // 6. STUDIO
  if(time>=T.studio[0]&&time<T.studio[1]){const t=time-T.studio[0];const D=T.studio[1]-T.studio[0];
    pop('sK',t,0,30);words('sT','Dites-le à l\\'IA.',t,.1,.1,.5);
    const k=back(t/.9);st('sWin',{opacity:ease(t/.4),transform:'perspective(2400px) rotateY('+lerp(18,-6,k)+'deg) rotateX('+lerp(12,4,k)+'deg) translateY('+((1-k)*140)+'px)'});
    $('sImg').src=frameAt(S.studio,Math.max(0,(t-.5)*1.25));
    const kk=ease((t-.9)/.5);st('sChip',{opacity:kk,transform:'translateX(-50%) translateY('+((1-kk)*40)+'px)'});
    const kd=back((t-3.4)/.6);st('sDone',{opacity:ease((t-3.4)/.3),transform:'translateX(-50%) scale('+lerp(.8,1,kd)+')'});
    fadeAll(['sK','sT','sWin','sChip','sDone'],t,D-.4,.4);
  }
  // 7. NOTIFICATIONS
  if(time>=T.notif[0]&&time<T.notif[1]){const t=time-T.notif[0];const D=T.notif[1]-T.notif[0];
    pop('nK',t,0,30);words('nT','Votre site travaille pour vous.',t,.1,.08,.5);
    const k=ease(t/.8);st('nWin',{opacity:k,transform:'perspective(2400px) rotateY('+lerp(10,-4,k)+'deg) rotateX('+lerp(8,3,k)+'deg) translateY('+((1-k)*100)+'px)'});
    [['n1',1.0],['n2',1.9],['n3',2.8]].forEach(([id,d])=>{const kk=back((t-d)/.6);st(id,{opacity:ease((t-d)/.3),transform:'translateX('+((1-kk)*120)+'px) scale('+lerp(.9,1,kk)+')'});});
    fadeAll(['nK','nT','nWin','n1','n2','n3'],t,D-.4,.4);
  }
  // 8. CTA
  if(time>=T.cta[0]&&time<T.cta[1]){const t=time-T.cta[0];
    const k=back(t/.8);st('ctaLogo',{opacity:ease(t/.4),transform:'translateX(-50%) scale('+lerp(.6,1,k)+')'});
    words('ctaT','Votre site. Aujourd\\'hui.',t,.3,.12,.55);
    [['st1',.9],['st2',1.05],['st3',1.2]].forEach(([id,d])=>pop(id,t,d,40));
    $('stn1').textContent=fmt(1000*ease((t-.9)/1.2))+'+';$('stn2').textContent=(4.7*ease((t-1.05)/1.2)).toFixed(1).replace('.',',')+'/5';$('stn3').textContent=Math.round(30*ease((t-1.2)/1.2))+' s';
    const kb=back((t-1.3)/.6);st('ctaBtn',{opacity:ease((t-1.3)/.3),transform:'translateX(-50%) scale('+(lerp(.8,1,kb)*(1+Math.sin(Math.max(0,t-2.0)*4)*.025))+')'});
    pop('ctaUrl',t,1.6,40);pop('ctaSub',t,1.8,30);
    fx={alpha:.7,burst:clamp((t-.1)/1.2)};
  }
  drawFx(time,fx);
};
window.preload=async function(){const all=[...new Set(Object.values(S).flat().map(x=>x.src))];let i=0;for(const src of all){await new Promise(r=>{const im=new Image();im.onload=im.onerror=r;im.src=src;});i++;}await document.fonts.ready;return i;};
</script></body></html>`;

fs.writeFileSync(path.join(OUT, 'compose2.html'), html);
const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome', proxy: { server: process.env.HTTPS_PROXY }, args: ['--ignore-certificate-errors'] });
const p = await b.newPage({ viewport: { width: W, height: H }, deviceScaleFactor: 1 });
await p.goto('file://' + path.join(OUT, 'compose2.html'), { waitUntil: 'load' });
console.log('préchargement…', await p.evaluate(() => window.preload()), 'images');
const FR = path.join(OUT, 'frames2'); fs.rmSync(FR, { recursive: true, force: true }); fs.mkdirSync(FR);
const times = PREVIEW ? [0.6, 1.8, 3.2, 4.8, 6.2, 8.2, 10.5, 11.8, 12.8, 14.2, 15.5, 16.8, 18.8, 21.6, 23.9, 26.2, 28.0, 30.4, 32.2] : Array.from({ length: Math.round(TOTAL * FPS) }, (_, i) => i / FPS);
const t0 = Date.now();
for (let i = 0; i < times.length; i++) {
  await p.evaluate((t) => window.seek(t), times[i]);
  await p.evaluate(async () => { for (const im of document.querySelectorAll('.scene.on img')) { try { await im.decode(); } catch {} } });
  await p.screenshot({ path: path.join(FR, `f${String(i).padStart(5, '0')}.${PREVIEW ? 'jpg' : 'png'}`), type: PREVIEW ? 'jpeg' : 'png', quality: PREVIEW ? 80 : undefined, omitBackground: !PREVIEW });
  if (i % 100 === 0) console.log('frame', i, '/', times.length, Math.round((Date.now() - t0) / 1000) + 's');
}
await b.close();
if (!PREVIEW) {
  console.log('frames prêtes (PNG alpha) dans', FR);
  console.log('ENCODED');
}
console.log('DONE', Math.round((Date.now() - t0) / 1000) + 's');
