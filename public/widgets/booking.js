/*
 * Joow — module de réservation d'hébergement pour les sites rapatriés (anciens sites Local Visibility).
 * S'installe dans <div id="lv-calendar-widget" data-joow-slug="…"> (ou data-slug) et utilise l'API Joow :
 * disponibilités et prix réels par chambre, devis, demande de séjour envoyée au propriétaire par Joow.
 */
(function () {
    'use strict';
    var box = document.getElementById('lv-calendar-widget') || document.getElementById('joow-booking');
    if (!box) return;
    var slug = box.getAttribute('data-joow-slug') || box.getAttribute('data-slug');
    if (!slug) return;
    var src = (document.currentScript && document.currentScript.src) || '';
    var API = (box.getAttribute('data-api') || (src ? src.replace(/\/widgets\/.*$/, '') : 'https://app.joow.fr')) + '/api/site/' + encodeURIComponent(slug);

    var MONTHS = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
    var DAYS = ['L', 'M', 'M', 'J', 'V', 'S', 'D'];
    var st = { rooms: [], room: null, month: null, days: {}, ci: null, co: null, quote: null, busy: false };

    var css = '#jwb{font-family:inherit;color:#1f2937;max-width:960px;margin:0 auto}#jwb *{box-sizing:border-box}'
        + '#jwb .jw-tabs{display:flex;flex-wrap:wrap;gap:.5rem;margin-bottom:1rem}#jwb .jw-tab{border:1.5px solid #e5e7eb;background:#fff;border-radius:999px;padding:.55rem 1.1rem;font-weight:600;cursor:pointer;color:#374151}'
        + '#jwb .jw-tab.on{background:#1f2937;color:#fff;border-color:#1f2937}#jwb .jw-grid{display:grid;gap:1.25rem;grid-template-columns:1fr}#jwb.wide .jw-grid{grid-template-columns:1.25fr 1fr}'
        + '#jwb .jw-card{background:#fff;border:1px solid #eef0f3;border-radius:1.25rem;padding:1.25rem;box-shadow:0 10px 30px -18px rgba(0,0,0,.25)}'
        + '#jwb .jw-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:.75rem}#jwb .jw-head b{font-size:1.05rem;text-transform:capitalize}'
        + '#jwb .jw-nav{border:0;background:#f3f4f6;width:2.2rem;height:2.2rem;border-radius:999px;cursor:pointer;font-size:1.1rem}#jwb .jw-nav:disabled{opacity:.35;cursor:default}'
        + '#jwb .jw-cal{display:grid;grid-template-columns:repeat(7,1fr);gap:4px}#jwb .jw-dn{text-align:center;font-size:.72rem;color:#9ca3af;font-weight:700;padding:.2rem 0}'
        + '#jwb .jw-d{border:0;background:#f9fafb;border-radius:.6rem;min-height:3.1rem;padding:.25rem;cursor:pointer;display:flex;flex-direction:column;align-items:center;justify-content:center;font-weight:600;color:#111827}'
        + '#jwb .jw-d small{font-size:.66rem;font-weight:500;color:#6b7280}#jwb .jw-d:hover:not(:disabled){outline:2px solid #1f2937}'
        + '#jwb .jw-d:disabled{background:transparent;color:#d1d5db;cursor:not-allowed;text-decoration:line-through}#jwb .jw-d.full{background:#fef2f2;color:#fca5a5}'
        + '#jwb .jw-d.sel{background:#1f2937;color:#fff}#jwb .jw-d.sel small{color:#e5e7eb}#jwb .jw-d.in{background:#e5e7eb}#jwb .jw-e{visibility:hidden}'
        + '#jwb .jw-legend{display:flex;gap:1rem;margin-top:.75rem;font-size:.75rem;color:#6b7280}'
        + '#jwb label{display:block;font-size:.8rem;font-weight:600;color:#374151;margin-top:.7rem}#jwb input,#jwb select,#jwb textarea{width:100%;margin-top:.3rem;border:1.5px solid #e5e7eb;border-radius:.7rem;padding:.65rem .8rem;font:inherit;font-size:.95rem;background:#fff;color:#111827}'
        + '#jwb .jw-row{display:grid;grid-template-columns:1fr 1fr;gap:.6rem}#jwb .jw-sum{background:#f9fafb;border-radius:.9rem;padding:.8rem 1rem;margin-top:.4rem;font-size:.9rem}'
        + '#jwb .jw-sum div{display:flex;justify-content:space-between;margin:.15rem 0}#jwb .jw-tot{font-weight:800;font-size:1.05rem;border-top:1px solid #e5e7eb;padding-top:.45rem;margin-top:.45rem}'
        + '#jwb .jw-btn{width:100%;margin-top:1rem;border:0;border-radius:.9rem;padding:.95rem;font-weight:700;font-size:1rem;background:#1f2937;color:#fff;cursor:pointer}#jwb .jw-btn:disabled{opacity:.6;cursor:wait}'
        + '#jwb .jw-err{color:#b91c1c;font-size:.85rem;margin-top:.6rem}#jwb .jw-ok{text-align:center;padding:2rem 1rem}#jwb .jw-ok b{display:block;font-size:1.3rem;margin-bottom:.4rem}'
        + '#jwb .jw-muted{color:#6b7280;font-size:.85rem}';
    var style = document.createElement('style'); style.textContent = css; document.head.appendChild(style);

    function iso(d) { return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0'); }
    function fr(s) { var p = s.split('-'); return +p[2] + ' ' + MONTHS[+p[1] - 1] + ' ' + p[0]; }
    function eur(n) { return (Math.round(n * 100) / 100).toLocaleString('fr-FR') + ' €'; }
    function esc(s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
    function get(path) { return fetch(API + path, { headers: { Accept: 'application/json' } }).then(function (r) { if (!r.ok) throw new Error(r.status); return r.json(); }); }

    function loadMonth() {
        var first = new Date(st.month.getFullYear(), st.month.getMonth(), 1);
        var key = st.room.id + iso(first);
        if (st.days[key]) return Promise.resolve();
        return get('/calendar?room=' + st.room.id + '&from=' + iso(first) + '&days=62').then(function (d) {
            var m = {}; (d.days || []).forEach(function (x) { m[x.d] = x; });
            st.days[key] = m;
        });
    }
    function day(s) {
        var first = new Date(st.month.getFullYear(), st.month.getMonth(), 1);
        var m = st.days[st.room.id + iso(first)] || {};
        if (m[s]) return m[s];
        var prev = new Date(st.month.getFullYear(), st.month.getMonth() - 1, 1);
        return (st.days[st.room.id + iso(prev)] || {})[s];
    }

    function render() {
        var today = iso(new Date());
        var y = st.month.getFullYear(), mo = st.month.getMonth();
        var first = new Date(y, mo, 1), shift = (first.getDay() + 6) % 7, n = new Date(y, mo + 1, 0).getDate();
        var cells = DAYS.map(function (d) { return '<div class="jw-dn">' + d + '</div>'; }).join('');
        for (var i = 0; i < shift; i++) cells += '<div class="jw-d jw-e"></div>';
        for (var dd = 1; dd <= n; dd++) {
            var s = iso(new Date(y, mo, dd)), info = day(s), past = s < today;
            var free = info && info.a, cls = 'jw-d';
            if (st.ci === s || st.co === s) cls += ' sel'; else if (st.ci && st.co && s > st.ci && s < st.co) cls += ' in';
            // On peut choisir un départ sur une nuit occupée (la nuit d'avant est libre)
            var canCheckout = st.ci && !st.co && s > st.ci;
            var disabled = past || (!free && !canCheckout);
            if (!free && !past && !canCheckout) cls += ' full';
            cells += '<button type="button" class="' + cls + '" data-d="' + s + '"' + (disabled ? ' disabled' : '') + '>' + dd
                + (free && info.p ? '<small>' + Math.round(info.p) + '€</small>' : '<small>&nbsp;</small>') + '</button>';
        }
        var canPrev = new Date(y, mo, 1) > new Date(new Date().getFullYear(), new Date().getMonth(), 1);
        var q = st.quote;
        var summary = '';
        if (st.ci && st.co) {
            summary = '<div class="jw-sum"><div><span>Arrivée</span><b>' + fr(st.ci) + '</b></div><div><span>Départ</span><b>' + fr(st.co) + '</b></div>';
            if (q && q.lines && q.lines.length) summary += q.lines.map(function (l) { return '<div><span>' + esc(l.label) + '</span><span>' + eur(l.amount) + '</span></div>'; }).join('');
            if (q && q.total != null) summary += '<div class="jw-tot"><span>Total</span><span>' + eur(q.total) + '</span></div>';
            summary += '</div>';
            if (q && q.error) summary += '<p class="jw-err">' + esc(q.error) + '</p>';
        } else {
            summary = '<p class="jw-muted">' + (st.ci ? 'Choisissez maintenant votre date de départ.' : 'Choisissez votre date d\'arrivée dans le calendrier.') + '</p>';
        }
        box.innerHTML = '<div id="jwb"' + (box.offsetWidth >= 820 ? ' class="wide"' : '') + '>'
            + (st.rooms.length > 1 ? '<div class="jw-tabs">' + st.rooms.map(function (r) { return '<button type="button" class="jw-tab' + (r.id === st.room.id ? ' on' : '') + '" data-room="' + r.id + '">' + esc(r.name) + (r.from_price ? ' · dès ' + Math.round(r.from_price) + ' €' : '') + '</button>'; }).join('') + '</div>' : '')
            + '<div class="jw-grid"><div class="jw-card"><div class="jw-head"><button type="button" class="jw-nav" data-nav="-1"' + (canPrev ? '' : ' disabled') + '>‹</button><b>' + MONTHS[mo] + ' ' + y + '</b><button type="button" class="jw-nav" data-nav="1">›</button></div>'
            + '<div class="jw-cal">' + cells + '</div><div class="jw-legend"><span>Prix par nuit</span><span>Barré : indisponible</span>' + (st.room.min_nights > 1 ? '<span>' + st.room.min_nights + ' nuits minimum</span>' : '') + '</div></div>'
            + '<form class="jw-card" novalidate><b style="font-size:1.1rem">Demande de réservation</b><p class="jw-muted" style="margin-top:.2rem">' + esc(st.room.name) + ' · réponse rapide du propriétaire par e-mail ou téléphone.</p>'
            + summary
            + '<label>Nombre de personnes<select name="guests">' + Array.from({ length: Math.max(1, st.room.capacity || 2) }, function (_, i) { return '<option value="' + (i + 1) + '"' + (i + 1 === Math.min(2, st.room.capacity || 2) ? ' selected' : '') + '>' + (i + 1) + '</option>'; }).join('') + '</select></label>'
            + '<label>Nom et prénom<input name="name" required autocomplete="name"></label>'
            + '<div class="jw-row"><label>E-mail<input name="email" type="email" required autocomplete="email"></label><label>Téléphone<input name="phone" type="tel" required autocomplete="tel"></label></div>'
            + '<label>Message (optionnel)<textarea name="notes" rows="3" placeholder="Heure d\'arrivée, occasion particulière…"></textarea></label>'
            + '<input name="hp" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px" aria-hidden="true">'
            + '<button class="jw-btn" type="submit"' + (st.busy ? ' disabled' : '') + '>' + (st.busy ? 'Envoi…' : 'Envoyer ma demande') + '</button><p class="jw-err" data-err></p></form></div></div>';
    }

    function quote() {
        st.quote = null;
        if (!st.ci || !st.co) return render();
        render();
        get('/rooms?from=' + st.ci + '&to=' + st.co + '&guests=1').then(function (d) {
            var r = (d.rooms || []).filter(function (x) { return x.id === st.room.id; })[0];
            st.quote = r ? { total: r.total, lines: r.lines || [], error: r.bookable ? null : (r.error || 'Indisponible à ces dates') } : null;
            render();
        }).catch(function () { render(); });
    }

    box.addEventListener('click', function (e) {
        var t = e.target.closest('button'); if (!t || !box.contains(t)) return;
        if (t.dataset.room) { st.room = st.rooms.filter(function (r) { return r.id === t.dataset.room; })[0]; st.ci = st.co = null; st.quote = null; loadMonth().then(render); return; }
        if (t.dataset.nav) { st.month = new Date(st.month.getFullYear(), st.month.getMonth() + (+t.dataset.nav), 1); loadMonth().then(render); return; }
        if (t.dataset.d) {
            var s = t.dataset.d;
            if (!st.ci || st.co || s <= st.ci) { st.ci = s; st.co = null; st.quote = null; render(); }
            else { st.co = s; quote(); }
        }
    });
    box.addEventListener('submit', function (e) {
        e.preventDefault();
        var f = e.target, err = f.querySelector('[data-err]');
        if (!st.ci || !st.co) { err.textContent = 'Choisissez vos dates d\'arrivée et de départ dans le calendrier.'; return; }
        if (!f.name.value.trim() || !f.email.value.trim() || !f.phone.value.trim()) { err.textContent = 'Merci d\'indiquer votre nom, votre e-mail et votre téléphone.'; return; }
        var body = { check_in: st.ci, check_out: st.co, guests: +f.guests.value, room_id: st.room.id, name: f.name.value.trim(), email: f.email.value.trim(), phone: f.phone.value.trim(), notes: f.notes.value.trim(), hp: f.hp.value };
        st.busy = true; f.querySelector('.jw-btn').disabled = true; f.querySelector('.jw-btn').textContent = 'Envoi…';
        fetch(API + '/stay', { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json' }, body: JSON.stringify(body) })
            .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, d: d }; }); })
            .then(function (x) {
                st.busy = false;
                if (!x.ok || !x.d.ok) { var e2 = x.d && (x.d.error || x.d.message); render(); box.querySelector('[data-err]').textContent = e2 || 'La demande n\'a pas pu être envoyée. Réessayez ou appelez-nous.'; return; }
                box.innerHTML = '<div id="jwb"><div class="jw-card jw-ok"><b>Merci, votre demande est envoyée ✅</b><p>' + esc(st.room.name) + ' · du ' + fr(st.ci) + ' au ' + fr(st.co) + '</p><p class="jw-muted" style="margin-top:.6rem">Le propriétaire vous répond rapidement par e-mail ou par téléphone pour confirmer votre séjour.</p></div></div>';
            })
            .catch(function () { st.busy = false; render(); box.querySelector('[data-err]').textContent = 'Connexion impossible. Réessayez dans un instant ou appelez-nous.'; });
    });

    get('/rooms').then(function (d) {
        st.rooms = d.rooms || [];
        if (!st.rooms.length) { box.innerHTML = '<p class="jw-muted">Réservation momentanément indisponible : contactez-nous directement.</p>'; return; }
        st.room = st.rooms[0];
        st.month = new Date(new Date().getFullYear(), new Date().getMonth(), 1);
        return loadMonth().then(render);
    }).catch(function () {
        box.innerHTML = '<p style="text-align:center;color:#6b7280">Réservation momentanément indisponible : contactez-nous directement.</p>';
    });
})();
