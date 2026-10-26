// Compositeur : génère la page HTML de la vidéo (1080×1920) pilotée par le temps, puis rend chaque image
// avec Playwright et encode en H.264 via ffmpeg. Usage : OUT=… FF=… node scripts/ad/render.mjs [--preview]
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

/* ───────── Timeline (secondes) ───────── */
const T = { hook: [0, 2.4], landing: [2.4, 8.6], gen: [8.6, 12.4], ready: [12.4, 16.6], studio: [16.6, 21.0], console: [21.0, 24.6], cta: [24.6, 29.0] };
const TOTAL = T.cta[1];

const html = `<!doctype html><html lang="fr"><head><meta charset="utf-8">
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0}
html,body{width:${W}px;height:${H}px;overflow:hidden;background:#07070c;font-family:Inter,system-ui,sans-serif;color:#fff}
.display{font-family:'Space Grotesk',sans-serif;font-weight:700;letter-spacing:-.025em}
#stage{position:relative;width:${W}px;height:${H}px;overflow:hidden}
.bg{position:absolute;inset:0;background:radial-gradient(70% 45% at 20% 8%,rgba(99,102,241,.38),transparent 60%),radial-gradient(60% 40% at 90% 30%,rgba(168,85,247,.30),transparent 60%),radial-gradient(50% 40% at 40% 95%,rgba(56,189,248,.18),transparent 60%),#07070c}
.aurora{position:absolute;border-radius:50%;filter:blur(90px);opacity:.55;will-change:transform}
.grain{position:absolute;inset:0;opacity:.28;mix-blend-mode:overlay;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='160' height='160'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='.85' numOctaves='2'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='.4'/%3E%3C/svg%3E")}
.scene{position:absolute;inset:0;display:none}
.scene.on{display:block}
.phone{position:absolute;width:600px;height:1300px;border-radius:78px;background:#101018;border:10px solid #1c1c2b;box-shadow:0 60px 120px -40px rgba(0,0,0,.9),0 0 0 2px rgba(255,255,255,.08),0 0 90px rgba(129,140,248,.22);overflow:hidden;transform-style:preserve-3d}
.phone img{position:absolute;left:0;top:0;width:100%;height:100%;object-fit:cover;object-position:top}
.phone .notch{position:absolute;left:50%;top:14px;width:170px;height:36px;transform:translateX(-50%);border-radius:999px;background:#1c1c2b;z-index:3}
.win{position:absolute;border-radius:28px;background:#14141f;border:1px solid rgba(255,255,255,.1);box-shadow:0 60px 120px -40px rgba(0,0,0,.9),0 0 90px rgba(129,140,248,.18);overflow:hidden}
.win .bar{height:52px;display:flex;align-items:center;gap:10px;padding:0 22px;border-bottom:1px solid rgba(255,255,255,.07);background:#14141f}
.win .bar i{width:14px;height:14px;border-radius:50%;display:inline-block}
.win .bar span{margin-left:12px;font-size:17px;color:#94a3b8;background:rgba(255,255,255,.05);padding:5px 14px;border-radius:8px}
.win img{display:block;width:100%}
.h1{font-size:112px;line-height:1.02}
.h2{font-size:78px;line-height:1.05}
.kicker{font-size:30px;font-weight:600;letter-spacing:.32em;text-transform:uppercase;color:#a5b4fc}
.sub{font-size:38px;line-height:1.3;color:#cbd5e1;font-weight:500}
.grad{background:linear-gradient(100deg,#818cf8 10%,#e879f9 45%,#38bdf8 80%);-webkit-background-clip:text;background-clip:text;color:transparent}
.chip{display:inline-flex;align-items:center;gap:14px;border-radius:999px;border:1px solid rgba(255,255,255,.14);background:rgba(13,13,20,.82);backdrop-filter:blur(10px);padding:18px 30px;font-size:30px;font-weight:600;color:#e2e8f0;box-shadow:0 30px 60px -30px rgba(0,0,0,.9)}
.chip b{color:#fff}
.dot{width:16px;height:16px;border-radius:50%;background:#34d399;box-shadow:0 0 18px #34d399}
.btn{display:inline-flex;align-items:center;gap:14px;border-radius:26px;padding:34px 60px;font-size:44px;font-weight:700;color:#fff;background:linear-gradient(135deg,#6366f1,#a855f7);box-shadow:0 30px 80px -20px rgba(124,92,246,.8)}
.steps{display:flex;flex-direction:column;gap:26px}
.step{display:flex;align-items:center;gap:26px;border-radius:30px;border:1px solid rgba(255,255,255,.1);background:rgba(255,255,255,.04);padding:30px 36px;font-size:36px;font-weight:600;color:#e2e8f0}
.step .ic{width:62px;height:62px;border-radius:50%;display:grid;place-items:center;font-size:30px;flex:none;background:rgba(255,255,255,.08);color:#94a3b8}
.step.done .ic{background:rgba(52,211,153,.2);color:#34d399}
.step.cur .ic{background:rgba(99,102,241,.3);color:#a5b4fc}
.card{position:absolute;border-radius:28px;overflow:hidden;border:1px solid rgba(255,255,255,.12);box-shadow:0 50px 100px -40px rgba(0,0,0,.95);background:#14141f}
.card img{display:block;width:100%}
.label{position:absolute;font-size:34px;font-weight:700;color:#fff;background:rgba(13,13,20,.85);border:1px solid rgba(255,255,255,.14);padding:16px 28px;border-radius:999px;backdrop-filter:blur(8px)}
.ring{position:absolute;border-radius:50%;border:3px solid rgba(165,180,252,.9)}
.cursor{position:absolute;width:40px;height:40px;border-radius:50%;background:rgba(255,255,255,.85);box-shadow:0 0 0 10px rgba(255,255,255,.25)}
</style></head><body><div id="stage">
<div class="bg"></div>
<div class="aurora" id="au1" style="left:-200px;top:-200px;width:900px;height:900px;background:radial-gradient(circle,rgba(99,102,241,.75),transparent 70%)"></div>
<div class="aurora" id="au2" style="right:-300px;top:500px;width:900px;height:900px;background:radial-gradient(circle,rgba(168,85,247,.6),transparent 70%)"></div>
<div class="aurora" id="au3" style="left:100px;bottom:-300px;width:900px;height:900px;background:radial-gradient(circle,rgba(56,189,248,.45),transparent 70%)"></div>
<div class="grain"></div>

<!-- HOOK -->
<div class="scene" id="hook">
  <img id="hookLogo" src="${svg('joow-logo-white.svg')}" style="position:absolute;left:50%;top:560px;width:620px;transform:translateX(-50%)">
  <div id="hookT1" class="display h1" style="position:absolute;left:80px;right:80px;top:860px;text-align:center">Votre site pro.</div>
  <div id="hookT2" class="display h1 grad" style="position:absolute;left:80px;right:80px;top:990px;text-align:center">En 30 secondes.</div>
  <div id="hookSub" class="sub" style="position:absolute;left:120px;right:120px;top:1200px;text-align:center">Généré par l'IA depuis votre fiche Google.</div>
</div>

<!-- LANDING : saisie -->
<div class="scene" id="landing">
  <div id="lKick" class="kicker" style="position:absolute;left:0;right:0;top:150px;text-align:center">Étape 1</div>
  <div id="lTitle" class="display h2" style="position:absolute;left:70px;right:70px;top:210px;text-align:center">Tapez le nom de<br>votre établissement</div>
  <div class="phone" id="lPhone" style="left:240px;top:440px"><div class="notch"></div><img id="lImg"></div>
  <div class="cursor" id="lCur" style="left:0;top:0;opacity:0"></div>
  <div class="ring" id="lRing" style="left:0;top:0;width:40px;height:40px;opacity:0"></div>
  <div class="chip" id="lChip" style="position:absolute;left:50%;top:1700px;transform:translateX(-50%)"><span class="dot"></span><span>On retrouve <b>votre fiche Google</b></span></div>
</div>

<!-- GÉNÉRATION -->
<div class="scene" id="gen">
  <div class="kicker" style="position:absolute;left:0;right:0;top:150px;text-align:center">L'IA construit tout</div>
  <div id="gTitle" class="display h2" style="position:absolute;left:70px;right:70px;top:210px;text-align:center">Textes, photos,<br>avis, design…</div>
  <div class="steps" id="gSteps" style="position:absolute;left:110px;right:110px;top:560px">
    <div class="step"><span class="ic">1</span><span>Lecture de votre fiche Google</span></div>
    <div class="step"><span class="ic">2</span><span>Rédaction des textes par l'IA</span></div>
    <div class="step"><span class="ic">3</span><span>Sélection de vos meilleures photos</span></div>
    <div class="step"><span class="ic">4</span><span>Design adapté à votre métier</span></div>
    <div class="step"><span class="ic">5</span><span>Réservation, avis, assistant IA activés</span></div>
  </div>
  <div id="gBarWrap" style="position:absolute;left:110px;right:110px;top:1380px;height:18px;border-radius:999px;background:rgba(255,255,255,.08);overflow:hidden"><div id="gBar" style="height:100%;width:0;border-radius:999px;background:linear-gradient(90deg,#6366f1,#a855f7)"></div></div>
  <div id="gTimer" class="display" style="position:absolute;left:0;right:0;top:1440px;text-align:center;font-size:150px;line-height:1">0 s</div>
  <div class="sub" style="position:absolute;left:0;right:0;top:1630px;text-align:center;color:#94a3b8;font-size:32px">sans compte · sans attente</div>
</div>

<!-- SITE PRÊT -->
<div class="scene" id="ready">
  <div class="kicker" style="position:absolute;left:0;right:0;top:150px;text-align:center">Résultat</div>
  <div id="rTitle" class="display h2" style="position:absolute;left:70px;right:70px;top:210px;text-align:center">Votre site est prêt.</div>
  <div class="phone" id="rPhone" style="left:240px;top:420px"><div class="notch"></div><img id="rImg"></div>
  <div class="chip" id="rChip1" style="position:absolute;left:60px;top:900px"><span style="color:#fbbf24;font-size:28px">★★★★★</span><span><b>4.1</b> · 33 272 avis Google</span></div>
  <div class="chip" id="rChip2" style="position:absolute;right:50px;top:1190px"><span class="dot"></span><span>Réservation <b>en ligne</b></span></div>
  <div class="chip" id="rChip3" style="position:absolute;left:80px;top:1500px"><span>📱</span><span>Parfait sur <b>mobile</b></span></div>
</div>

<!-- STUDIO -->
<div class="scene" id="studio">
  <div class="kicker" style="position:absolute;left:0;right:0;top:150px;text-align:center">Le Studio</div>
  <div id="sTitle" class="display h2" style="position:absolute;left:60px;right:60px;top:210px;text-align:center">Modifiez tout en 2 clics.<br><span class="grad">Ou demandez à l'IA.</span></div>
  <div class="win" id="sWin" style="left:-130px;top:540px;width:1340px"><div class="bar"><i style="background:#f87171"></i><i style="background:#fbbf24"></i><i style="background:#34d399"></i><span>app.joow.fr/studio</span></div><img id="sImg"></div>
  <div class="chip" id="sChip" style="position:absolute;left:50%;top:1480px;transform:translateX(-50%);max-width:960px"><span>✨</span><span>« Passe le site en thème sombre et ajoute une FAQ »</span></div>
</div>

<!-- CONSOLE -->
<div class="scene" id="console">
  <div class="kicker" style="position:absolute;left:0;right:0;top:150px;text-align:center">Tout est inclus</div>
  <div id="cTitle" class="display h2" style="position:absolute;left:60px;right:60px;top:210px;text-align:center">Réservations, paiements,<br>assistant IA, avis…</div>
  <div class="card" id="c1" style="width:900px;left:90px;top:560px"><img src="${png('reservations')}"></div>
  <div class="card" id="c2" style="width:900px;left:90px;top:560px"><img src="${png('modules')}"></div>
  <div class="card" id="c3" style="width:900px;left:90px;top:560px;height:470px"><img src="${png('dashboard')}"></div>
  <div class="label" id="cl1" style="left:120px;top:1420px">📅 Réservations de table</div>
  <div class="label" id="cl2" style="left:120px;top:1420px">🧩 12 modules en un clic</div>
  <div class="label" id="cl3" style="left:120px;top:1420px">📊 Votre espace Joow</div>
</div>

<!-- CTA -->
<div class="scene" id="cta">
  <img id="ctaLogo" src="${svg('joow-logo-white.svg')}" style="position:absolute;left:50%;top:420px;width:560px;transform:translateX(-50%)">
  <div id="ctaT" class="display h1" style="position:absolute;left:60px;right:60px;top:700px;text-align:center;font-size:104px">Essayez<br><span class="grad">gratuitement.</span></div>
  <div id="ctaBtn" class="btn" style="position:absolute;left:50%;top:1040px;transform:translateX(-50%)">Créer mon site →</div>
  <div id="ctaUrl" class="display" style="position:absolute;left:0;right:0;top:1230px;text-align:center;font-size:64px;color:#fff">app.joow.fr</div>
  <div id="ctaSub" class="sub" style="position:absolute;left:0;right:0;top:1340px;text-align:center;color:#94a3b8;font-size:32px">Sans carte bancaire · aperçu immédiat · modifiable à volonté</div>
</div>
</div>
<script>
const S = ${JSON.stringify(S)};
const T = ${JSON.stringify(T)};
const clamp=(x,a=0,b=1)=>Math.max(a,Math.min(b,x));
const ease=(x)=>1-Math.pow(1-clamp(x),3);
const easeIO=(x)=>{x=clamp(x);return x<.5?4*x*x*x:1-Math.pow(-2*x+2,3)/2;};
const lerp=(a,b,t)=>a+(b-a)*t;
const frameAt=(s,t)=>{let acc=0;for(const it of s){acc+=it.hold;if(t<acc)return it.src;}return s[s.length-1].src;};
const seqDur=(s)=>s.reduce((a,b)=>a+b.hold,0);
const $=(id)=>document.getElementById(id);
const show=(id,on)=>$(id).classList.toggle('on',on);
const st=(id,css)=>Object.assign($(id).style,css);
const pop=(id,t,delay=0,from=60)=>{const k=ease((t-delay)/.6);st(id,{opacity:k,transform:$(id).dataset.base? $(id).dataset.base+' translateY('+((1-k)*from)+'px)':'translateY('+((1-k)*from)+'px)'});};
const popX=(id,t,delay=0,from=60)=>{const k=ease((t-delay)/.6);st(id,{opacity:k,transform:'translateX(-50%) translateY('+((1-k)*from)+'px)'});};
const fadeOut=(id,t,start,dur=.35)=>{if(t>start){const k=1-ease((t-start)/dur);$(id).style.opacity=String(Math.min(parseFloat($(id).style.opacity||1),k));}};
const scenes=['hook','landing','gen','ready','studio','console','cta'];
window.seek=function(time){
  // Fond
  $('au1').style.transform='translate('+(Math.sin(time*.35)*70)+'px,'+(Math.cos(time*.3)*50)+'px)';
  $('au2').style.transform='translate('+(Math.cos(time*.28)*80)+'px,'+(Math.sin(time*.33)*60)+'px)';
  $('au3').style.transform='translate('+(Math.sin(time*.25)*60)+'px,'+(Math.cos(time*.4)*40)+'px)';
  for(const s of scenes){const [a,b]=T[s];show(s,time>=a&&time<b);}
  // ── HOOK
  if(time>=T.hook[0]&&time<T.hook[1]){const t=time-T.hook[0];
    const k=ease(t/.7);st('hookLogo',{opacity:k,transform:'translateX(-50%) scale('+lerp(.6,1,k)+')'});
    pop('hookT1',t,.35,90);pop('hookT2',t,.6,90);pop('hookSub',t,1.0,40);
    for(const id of ['hookLogo','hookT1','hookT2','hookSub'])fadeOut(id,t,2.0,.4);
  }
  // ── LANDING
  if(time>=T.landing[0]&&time<T.landing[1]){const t=time-T.landing[0];
    pop('lKick',t,0,40);pop('lTitle',t,.1,60);
    const k=ease(t/.9);const rot=lerp(18,-4,k)+Math.sin(time*.8)*1.5;
    st('lPhone',{opacity:k,transform:'perspective(2200px) rotateY('+rot+'deg) rotateX('+(lerp(8,2,k))+'deg) translateY('+((1-k)*160)+'px)'});
    const twD=seqDur(S.tw);const typD=seqDur(S.typing);
    let src;const ts=t-.5;
    if(ts<twD)src=frameAt(S.tw,Math.max(0,ts));else if(ts<twD+typD)src=frameAt(S.typing,ts-twD);else src=S.sel[0].src;
    $('lImg').src=src;
    // curseur qui vient taper dans le champ puis sélectionne le résultat
    const tapT=twD+.5-.35;const c=$('lCur'),r=$('lRing');
    if(ts>tapT-.6&&ts<tapT+.4){const kk=ease((ts-(tapT-.6))/.6);st('lCur',{opacity:1,left:lerp(900,560,kk)+'px',top:lerp(1500,930,kk)+'px'});const rr=clamp((ts-tapT)/.4);st('lRing',{opacity:1-rr,left:(560+20-20-rr*60)+'px',top:(930+20-20-rr*60)+'px',width:(40+rr*120)+'px',height:(40+rr*120)+'px'});}else{c.style.opacity=0;r.style.opacity=0;}
    const selT=twD+typD;
    if(ts>selT-.5&&ts<selT+.5){const kk=ease((ts-(selT-.5))/.5);st('lCur',{opacity:1,left:lerp(700,520,kk)+'px',top:lerp(1300,1120,kk)+'px'});const rr=clamp((ts-selT)/.4);st('lRing',{opacity:1-rr,left:(520-rr*60)+'px',top:(1120-rr*60)+'px',width:(40+rr*120)+'px',height:(40+rr*120)+'px'});}
    const chipK=ease((ts-(twD+typD-1.2))/.5);st('lChip',{opacity:chipK,transform:'translateX(-50%) translateY('+((1-chipK)*40)+'px)'});
    if(t>T.landing[1]-T.landing[0]-.4){const f=1-ease((t-(T.landing[1]-T.landing[0]-.4))/.4);for(const id of ['lPhone','lChip','lTitle','lKick'])$(id).style.opacity=String(Math.min(parseFloat($(id).style.opacity||1),f));}
  }
  // ── GÉNÉRATION
  if(time>=T.gen[0]&&time<T.gen[1]){const t=time-T.gen[0];const D=T.gen[1]-T.gen[0];
    pop('gTitle',t,0,60);
    const steps=[...$('gSteps').children];const per=.55;
    steps.forEach((el,i)=>{const k=ease((t-.2-i*per)/.4);el.style.opacity=k;el.style.transform='translateX('+((1-k)*-60)+'px)';const done=t>.2+(i+1)*per;const cur=!done&&t>.2+i*per;el.classList.toggle('done',done);el.classList.toggle('cur',cur);el.querySelector('.ic').textContent=done?'✓':String(i+1);});
    const p=clamp((t-.2)/(per*5+.3));$('gBar').style.width=(p*100)+'%';
    $('gTimer').textContent=Math.round(p*28)+' s';$('gTimer').style.transform='scale('+(1+Math.sin(t*6)*.015)+')';
    if(t>D-.4){const f=1-ease((t-(D-.4))/.4);for(const id of ['gTitle','gSteps','gBarWrap','gTimer'])$(id).style.opacity=String(f);}else{$('gSteps').style.opacity=1;$('gBarWrap').style.opacity=1;$('gTimer').style.opacity=1;}
  }
  // ── READY
  if(time>=T.ready[0]&&time<T.ready[1]){const t=time-T.ready[0];const D=T.ready[1]-T.ready[0];
    pop('rTitle',t,0,70);
    const k=ease(t/.8);st('rPhone',{opacity:k,transform:'perspective(2200px) rotateY('+(lerp(-14,6,k)+Math.sin(time*.9)*1.5)+'deg) rotateX('+lerp(6,2,k)+'deg) translateY('+((1-k)*140)+'px) scale('+lerp(.92,1,k)+')'});
    const scrollStart=1.1;
    $('rImg').src=t<scrollStart?S.ready[0].src:frameAt(S.scroll,(t-scrollStart)*1.6);
    [['rChip1',1.3],['rChip2',1.9],['rChip3',2.5]].forEach(([id,d])=>{const kk=ease((t-d)/.5);st(id,{opacity:kk,transform:'translateY('+((1-kk)*40)+'px) scale('+lerp(.9,1,kk)+')'});});
    if(t>D-.4){const f=1-ease((t-(D-.4))/.4);for(const id of ['rPhone','rTitle','rChip1','rChip2','rChip3'])$(id).style.opacity=String(Math.min(parseFloat($(id).style.opacity||1),f));}
  }
  // ── STUDIO
  if(time>=T.studio[0]&&time<T.studio[1]){const t=time-T.studio[0];const D=T.studio[1]-T.studio[0];
    pop('sTitle',t,0,70);
    const k=ease(t/.9);st('sWin',{opacity:k,transform:'perspective(2400px) rotateY('+(lerp(16,-6,k))+'deg) rotateX('+lerp(10,4,k)+'deg) translateY('+((1-k)*120)+'px)'});
    $('sImg').src=frameAt(S.studio,Math.max(0,(t-.6)*1.15));
    const kk=ease((t-1.0)/.5);st('sChip',{opacity:kk,transform:'translateX(-50%) translateY('+((1-kk)*40)+'px)'});
    if(t>D-.4){const f=1-ease((t-(D-.4))/.4);for(const id of ['sWin','sTitle','sChip'])$(id).style.opacity=String(Math.min(parseFloat($(id).style.opacity||1),f));}
  }
  // ── CONSOLE : 3 cartes en cascade
  if(time>=T.console[0]&&time<T.console[1]){const t=time-T.console[0];const D=T.console[1]-T.console[0];
    pop('cTitle',t,0,70);
    const each=1.1;
    [['c1','cl1',0],['c2','cl2',1],['c3','cl3',2]].forEach(([cid,lid,i])=>{const a=.25+i*each;const k=ease((t-a)/.6);const out=1-ease((t-a-each-.1)/.4);const o=Math.min(k,i<2?out:1);
      st(cid,{opacity:o,transform:'perspective(2400px) rotateY('+lerp(14,-4,k)+'deg) rotateX('+lerp(10,3,k)+'deg) translateY('+((1-k)*120-(1-out)*40)+'px) scale('+lerp(.94,1,k)+')',zIndex:10+i});
      st(lid,{opacity:o,transform:'translateY('+((1-k)*30)+'px)',zIndex:20+i});});
    if(t>D-.4){const f=1-ease((t-(D-.4))/.4);for(const id of ['c3','cl3','cTitle'])$(id).style.opacity=String(Math.min(parseFloat($(id).style.opacity||1),f));}
  }
  // ── CTA
  if(time>=T.cta[0]&&time<T.cta[1]){const t=time-T.cta[0];
    const k=ease(t/.7);st('ctaLogo',{opacity:k,transform:'translateX(-50%) scale('+lerp(.7,1,k)+')'});
    pop('ctaT',t,.3,80);
    const kb=ease((t-.8)/.6);st('ctaBtn',{opacity:kb,transform:'translateX(-50%) translateY('+((1-kb)*50)+'px) scale('+(1+Math.sin(Math.max(0,t-1.4)*4)*.025)+')'});
    pop('ctaUrl',t,1.1,40);pop('ctaSub',t,1.3,30);
  }
};
window.preload=async function(){const all=[...new Set(Object.values(S).flat().map(x=>x.src))];let i=0;for(const src of all){await new Promise(r=>{const im=new Image();im.onload=im.onerror=r;im.src=src;});i++;}await document.fonts.ready;return i;};
</script></body></html>`;

fs.writeFileSync(path.join(OUT, 'compose.html'), html);
const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome', proxy: { server: process.env.HTTPS_PROXY }, args: ['--ignore-certificate-errors'] });
const p = await b.newPage({ viewport: { width: W, height: H }, deviceScaleFactor: 1 });
await p.goto('file://' + path.join(OUT, 'compose.html'), { waitUntil: 'load' });
console.log('préchargement…', await p.evaluate(() => window.preload()), 'images');
const FR = path.join(OUT, 'frames'); fs.rmSync(FR, { recursive: true, force: true }); fs.mkdirSync(FR);
const times = PREVIEW ? [0.8, 1.8, 3.0, 5.0, 7.2, 8.2, 9.5, 11.6, 13.2, 15.0, 17.5, 19.5, 21.6, 22.9, 24.1, 25.6, 27.5] : Array.from({ length: Math.round(TOTAL * FPS) }, (_, i) => i / FPS);
const t0 = Date.now();
for (let i = 0; i < times.length; i++) {
  await p.evaluate((t) => window.seek(t), times[i]);
  await p.evaluate(async () => { for (const im of document.querySelectorAll('.scene.on img')) { try { await im.decode(); } catch {} } });
  await p.screenshot({ path: path.join(FR, `f${String(i).padStart(5, '0')}.${PREVIEW ? 'jpg' : 'png'}`), type: PREVIEW ? 'jpeg' : 'png', quality: PREVIEW ? 80 : undefined });
  if (i % 100 === 0) console.log('frame', i, '/', times.length, Math.round((Date.now() - t0) / 1000) + 's');
}
await b.close();
if (!PREVIEW) {
  execFileSync(FF, ['-y', '-loglevel', 'error', '-framerate', String(FPS), '-i', path.join(FR, 'f%05d.png'), '-c:v', 'libx264', '-preset', 'slow', '-crf', '16', '-pix_fmt', 'yuv420p', '-movflags', '+faststart', path.join(OUT, 'joow-ad-9x16-silent.mp4')]);
  console.log('ENCODED', path.join(OUT, 'joow-ad-9x16-silent.mp4'));
}
console.log('DONE', Math.round((Date.now() - t0) / 1000) + 's');
