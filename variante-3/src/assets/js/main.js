// TSV Fischach Schwimmen, Variante 3 – das einzige Skript der Seite.
// Alles Wichtige funktioniert auch ohne: Inhalte, Menü, Wochenplan und Formulare sind reines HTML.
// Das Skript ergänzt Bewegung, Filter, Reiter und das Absenden ohne Seitenwechsel.
(function () {
  'use strict';
  var doc = document;
  var root = doc.documentElement;
  var ruhig = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  function alle(sel, el) { return Array.prototype.slice.call((el || doc).querySelectorAll(sel)); }

  // Erst jetzt dürfen Elemente unsichtbar starten. Lädt das Skript nicht, bleibt alles sichtbar.
  root.classList.add('bewegt');

  // ---------- Einblenden beim Scrollen ----------
  var zeigen = alle('.reveal');
  if ('IntersectionObserver' in window && !ruhig) {
    var io = new IntersectionObserver(function (eintraege) {
      eintraege.forEach(function (e) {
        if (e.isIntersecting) { e.target.classList.add('da'); io.unobserve(e.target); }
      });
    }, { rootMargin: '0px 0px -8% 0px', threshold: 0.08 });
    zeigen.forEach(function (el) { io.observe(el); });
    // Sicherheitsnetz: nach dem Laden nichts dauerhaft verstecken, was schon im Bild ist
    window.addEventListener('load', function () {
      setTimeout(function () {
        zeigen.forEach(function (el) {
          if (el.getBoundingClientRect().top < window.innerHeight) el.classList.add('da');
        });
      }, 400);
    });
  } else {
    zeigen.forEach(function (el) { el.classList.add('da'); });
  }

  // ---------- Kopf: Menü, Verstecken beim Runterscrollen, Bahn-Fortschritt ----------
  var kopf = doc.querySelector('[data-kopf]');
  var knopf = doc.querySelector('.menue-knopf');
  var nav = doc.getElementById('hauptmenue');
  function menue(auf) {
    doc.body.classList.toggle('menue-offen', auf);
    kopf.classList.toggle('menue-offen', auf);
    knopf.setAttribute('aria-expanded', auf ? 'true' : 'false');
    knopf.querySelector('.menue-wort').textContent = auf ? 'Schließen' : 'Menü';
  }
  if (kopf && knopf && nav) {
    knopf.addEventListener('click', function () { menue(knopf.getAttribute('aria-expanded') !== 'true'); });
    nav.addEventListener('click', function (ev) { if (ev.target.closest('a')) menue(false); });
    doc.addEventListener('keydown', function (ev) {
      if (ev.key === 'Escape' && knopf.getAttribute('aria-expanded') === 'true') { menue(false); knopf.focus(); }
    });
    window.matchMedia('(min-width: 64rem)').addEventListener('change', function () { menue(false); });

    // Fleck, der im Menü unter dem Mauszeiger mitwandert
    var liste = nav.querySelector('.haupt-liste');
    var fleck = doc.createElement('span');
    fleck.className = 'haupt-fleck';
    fleck.setAttribute('aria-hidden', 'true');
    liste.appendChild(fleck);
    alle('a', liste).forEach(function (a) {
      function hin() {
        fleck.style.width = a.offsetWidth + 'px';
        fleck.style.transform = 'translateX(' + a.offsetLeft + 'px)';
        fleck.style.opacity = '1';
      }
      a.addEventListener('mouseenter', hin);
      a.addEventListener('focus', hin);
    });
    liste.addEventListener('mouseleave', function () { fleck.style.opacity = '0'; });
    liste.addEventListener('focusout', function () { fleck.style.opacity = '0'; });
  }

  var hoch = doc.querySelector('[data-auftauchen]');
  var stufen = alle('[data-stufen]');
  var letztes = window.scrollY;
  var wartet = false;
  function beimScrollen() {
    wartet = false;
    var y = window.scrollY;
    var max = root.scrollHeight - window.innerHeight;
    root.style.setProperty('--scroll', max > 0 ? Math.min(1, y / max).toFixed(4) : '0');
    if (kopf && !doc.body.classList.contains('menue-offen')) {
      if (y > 320 && y > letztes + 6) kopf.classList.add('weg');
      else if (y < letztes - 6 || y < 320) kopf.classList.remove('weg');
    }
    letztes = y;
    if (hoch) hoch.hidden = y < 900;
    // Stufen: die Leine füllt sich, die Krake schwimmt mit
    stufen.forEach(function (el) {
      var r = el.getBoundingClientRect();
      var p = (window.innerHeight * 0.62 - r.top) / r.height;
      el.style.setProperty('--p', Math.max(0, Math.min(1, p)).toFixed(4));
    });
  }
  window.addEventListener('scroll', function () {
    if (!wartet) { wartet = true; requestAnimationFrame(beimScrollen); }
  }, { passive: true });
  window.addEventListener('resize', beimScrollen);
  beimScrollen();
  // Fokus per Tastatur holt den Kopf zurück
  if (kopf) kopf.addEventListener('focusin', function () { kopf.classList.remove('weg'); });

  // ---------- Hell/Dunkel (nur im eigenen Browser gespeichert) ----------
  alle('[data-theme-switch]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var dunkel = root.dataset.theme
        ? root.dataset.theme === 'dark'
        : window.matchMedia('(prefers-color-scheme: dark)').matches;
      root.dataset.theme = dunkel ? 'light' : 'dark';
      try { localStorage.setItem('theme', root.dataset.theme); } catch (e) {}
    });
  });

  // ---------- Krake folgt dem Mauszeiger ein kleines Stück ----------
  var krake = doc.querySelector('.held-start .held-krake');
  if (krake && !ruhig && window.matchMedia('(pointer: fine)').matches) {
    var held = krake.closest('.held');
    held.addEventListener('pointermove', function (ev) {
      var r = held.getBoundingClientRect();
      krake.style.setProperty('--mx', ((ev.clientX - r.left) / r.width - 0.5).toFixed(3));
      krake.style.setProperty('--my', ((ev.clientY - r.top) / r.height - 0.5).toFixed(3));
    });
    held.addEventListener('pointerleave', function () {
      krake.style.setProperty('--mx', 0);
      krake.style.setProperty('--my', 0);
    });
  }

  // ---------- Spritzer beim Klick auf Knöpfe ----------
  if (!ruhig) {
    doc.addEventListener('pointerdown', function (ev) {
      var k = ev.target.closest('.knopf');
      if (!k) return;
      var r = k.getBoundingClientRect();
      var g = Math.max(r.width, r.height);
      var s = doc.createElement('span');
      s.className = 'spritzer';
      s.style.width = s.style.height = g + 'px';
      s.style.left = (ev.clientX - r.left - g / 2) + 'px';
      s.style.top = (ev.clientY - r.top - g / 2) + 'px';
      k.appendChild(s);
      s.addEventListener('animationend', function () { s.remove(); });
    });
  }

  // ---------- Zahlen hochzählen ----------
  var zahlen = alle('[data-zahl]');
  if (zahlen.length && 'IntersectionObserver' in window && !ruhig) {
    var zio = new IntersectionObserver(function (eintraege) {
      eintraege.forEach(function (e) {
        if (!e.isIntersecting) return;
        zio.unobserve(e.target);
        var el = e.target;
        var ziel = parseInt(el.dataset.zahl, 10);
        var von = parseInt(el.dataset.von || '0', 10);
        var start = null;
        function schritt(t) {
          if (start === null) start = t;
          var f = Math.min(1, (t - start) / 1400);
          f = 1 - Math.pow(1 - f, 3);
          el.textContent = Math.round(von + (ziel - von) * f);
          if (f < 1) requestAnimationFrame(schritt);
        }
        requestAnimationFrame(schritt);
      });
    }, { threshold: 0.6 });
    zahlen.forEach(function (el) { zio.observe(el); });
  }

  // ---------- Reiter (Wegweiser, Anmeldeformulare) ----------
  // Im HTML sind es normale Sprunglinks auf sichtbare Abschnitte. Mit Skript werden daraus Reiter.
  var reiter = [];
  alle('[data-reiter]').forEach(function (kiste) {
    var tabs = alle('[data-tab]', kiste);
    var felder = tabs.map(function (t) { return doc.getElementById(t.getAttribute('href').slice(1)); });
    var wahl = kiste.querySelector('.reiter-wahl');
    wahl.setAttribute('role', 'tablist');
    tabs.forEach(function (t, i) {
      t.setAttribute('role', 'tab');
      t.id = t.id || felder[i].id + '-reiter';
      t.setAttribute('aria-controls', felder[i].id);
      felder[i].setAttribute('role', 'tabpanel');
      felder[i].setAttribute('aria-labelledby', t.id);
      t.addEventListener('click', function (ev) {
        ev.preventDefault();
        zeige(i, true);
        if (kiste.hasAttribute('data-reiter-hash')) history.replaceState(null, '', '#' + felder[i].id);
      });
      t.addEventListener('keydown', function (ev) {
        var d = ev.key === 'ArrowRight' || ev.key === 'ArrowDown' ? 1 : ev.key === 'ArrowLeft' || ev.key === 'ArrowUp' ? -1 : 0;
        if (!d) return;
        ev.preventDefault();
        var n = (i + d + tabs.length) % tabs.length;
        zeige(n, true);
        tabs[n].focus();
      });
    });
    function zeige(n, bewegt) {
      tabs.forEach(function (t, i) {
        var an = i === n;
        t.setAttribute('aria-selected', an ? 'true' : 'false');
        t.tabIndex = an ? 0 : -1;
        felder[i].hidden = !an;
        felder[i].classList.toggle('kommt', an && !!bewegt);
        if (an) alle('.reveal', felder[i]).forEach(function (el) { el.classList.add('da'); });
      });
    }
    zeige(0, false);
    reiter.push({ felder: felder, zeige: zeige, kiste: kiste });
  });

  // Sprungmarken wie anmelden.html#erwachsene öffnen den passenden Reiter.
  // #nachricht-schnuppern wählt zusätzlich das Thema im Kontaktformular vor.
  function nachHash(scrollen) {
    var h = decodeURIComponent(location.hash.slice(1));
    if (!h) return;
    var thema = null;
    if (h.indexOf('nachricht-') === 0) { thema = h.slice(10); h = 'nachricht'; }
    if (h === 'bestaetigt') h = 'infomail';
    reiter.forEach(function (r) {
      r.felder.forEach(function (f, i) {
        if (f.id !== h && !f.querySelector('[id="' + h.replace(/"/g, '') + '"]')) return;
        r.zeige(i, false);
        if (thema) {
          var s = f.querySelector('select[name="thema"]');
          if (s && s.querySelector('option[value="' + thema.replace(/"/g, '') + '"]')) s.value = thema;
        }
        if (location.hash === '#bestaetigt') {
          var ok = f.querySelector('[data-bestaetigt]');
          if (ok) ok.hidden = false;
        }
        if (scrollen) r.kiste.scrollIntoView({ block: 'start' });
      });
    });
  }
  window.addEventListener('hashchange', function () { nachHash(true); });
  nachHash(true);

  // ---------- Wochenplan: Heute, Filter, was läuft gerade ----------
  alle('[data-plan]').forEach(function (plan) {
    var tage = alle('.plan-day', plan);
    var slots = alle('.slot', plan);
    var live = plan.querySelector('[data-plan-live]');
    var leiste = plan.querySelector('.plan-filter');
    var namen = ['', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag', 'Sonntag'];

    // Uhrzeit in Fischach, egal wo der Browser steht
    function jetzt() {
      try {
        var t = new Intl.DateTimeFormat('en-GB', { timeZone: 'Europe/Berlin', weekday: 'short', hour: '2-digit', minute: '2-digit', hour12: false }).formatToParts(new Date());
        var o = {};
        t.forEach(function (p) { o[p.type] = p.value; });
        var wt = { Mon: 1, Tue: 2, Wed: 3, Thu: 4, Fri: 5, Sat: 6, Sun: 7 }[o.weekday];
        return { tag: wt, min: (parseInt(o.hour, 10) % 24) * 60 + parseInt(o.minute, 10) };
      } catch (e) {
        var d = new Date();
        return { tag: d.getDay() || 7, min: d.getHours() * 60 + d.getMinutes() };
      }
    }
    function minuten(s) { var p = s.split(':'); return parseInt(p[0], 10) * 60 + parseInt(p[1], 10); }
    function name(slot) { return slot.querySelector('a').textContent; }

    function stand() {
      var n = jetzt();
      var laeuft = [];
      var naechster = null;
      tage.forEach(function (tag) {
        var nr = parseInt(tag.dataset.tag, 10);
        var heute = nr === n.tag;
        tag.classList.toggle('ist-heute', heute);
        tag.querySelector('.heute').hidden = !heute;
        alle('.slot', tag).forEach(function (s) {
          var von = minuten(s.dataset.von);
          var bis = minuten(s.dataset.bis);
          var an = heute && n.min >= von && n.min < bis;
          s.classList.toggle('laeuft', an);
          if (an) laeuft.push(s);
          // Abstand in Minuten bis zum Beginn, über die Woche gerechnet
          var ab = ((nr - n.tag + 7) % 7) * 1440 + von - n.min;
          if (ab <= 0) ab += 7 * 1440;
          if (!naechster || ab < naechster.ab) naechster = { ab: ab, slot: s, tag: nr };
        });
      });
      if (!live) return;
      var text;
      if (laeuft.length) {
        text = 'Laut Plan gerade im Wasser: ' + laeuft.map(function (s) { return name(s) + ' (bis ' + s.dataset.bis + ' Uhr)'; }).join(', ');
      } else if (naechster) {
        var wann = naechster.tag === n.tag && naechster.ab < 1440 ? 'heute' : (naechster.tag === (n.tag % 7) + 1 ? 'morgen' : 'am ' + namen[naechster.tag]);
        text = 'Laut Plan als Nächstes: ' + name(naechster.slot) + ', ' + wann + ' um ' + naechster.slot.dataset.von + ' Uhr';
      }
      live.classList.toggle('jetzt', laeuft.length > 0);
      live.textContent = text || '';
      live.hidden = !text;
    }
    stand();
    setInterval(stand, 60000);

    if (leiste) {
      leiste.hidden = false;
      var knoepfe = alle('.filter', leiste);
      knoepfe.forEach(function (b) {
        b.addEventListener('click', function () {
          var f = b.dataset.filter;
          knoepfe.forEach(function (k) {
            var an = k === b;
            k.classList.toggle('is-on', an);
            k.setAttribute('aria-pressed', an ? 'true' : 'false');
          });
          slots.forEach(function (s) {
            var zeigen = f === 'alle' || s.dataset.kat === f;
            s.classList.toggle('aus', !zeigen);
            s.classList.remove('kommt');
            if (zeigen) { void s.offsetWidth; s.classList.add('kommt'); }
          });
          tage.forEach(function (t) { t.classList.toggle('leer', !t.querySelector('.slot:not(.aus)')); });
        });
      });
    }
  });

  // ---------- Mailadresse kopieren ----------
  alle('[data-kopieren]').forEach(function (b) {
    if (!navigator.clipboard) { b.hidden = true; return; }
    var alt = b.textContent;
    b.addEventListener('click', function () {
      navigator.clipboard.writeText(b.dataset.kopieren).then(function () {
        b.textContent = 'Kopiert';
        b.classList.add('ok');
        setTimeout(function () { b.textContent = alt; b.classList.remove('ok'); }, 1800);
      }).catch(function () {});
    });
  });

  // ---------- Formulare ----------
  // Fortschrittsbalken: wie viele Pflichtfelder schon passen
  alle('form[data-fortschritt]').forEach(function (form) {
    var balken = form.querySelector('.fortschritt');
    var text = form.querySelector('.fortschritt-text');
    if (!balken) return;
    balken.hidden = false;
    function messen() {
      var pflicht = alle('[required]', form);
      var gut = pflicht.filter(function (f) { return f.checkValidity(); }).length;
      balken.style.setProperty('--f', pflicht.length ? (gut / pflicht.length).toFixed(3) : '0');
      if (text) { text.hidden = false; text.textContent = gut === pflicht.length ? 'Alles ausgefüllt, du kannst absenden.' : gut + ' von ' + pflicht.length + ' Pflichtangaben ausgefüllt'; }
    }
    form.addEventListener('input', messen);
    form.addEventListener('change', messen);
    form.addEventListener('reset', function () { setTimeout(messen, 0); });
    messen();
  });

  // Per fetch senden, damit die Seite stehen bleibt.
  // Ohne JavaScript schickt der Browser das Formular normal ab (api/formular.php leitet auf danke.html).
  alle('form.js-form:not([data-vorschau])').forEach(function (form) {
    var status = form.querySelector('.form-status');
    // Zeitfalle: beim ersten Klick ins Formular einen signierten Zeitstempel holen.
    // Klappt das nicht, wird trotzdem gesendet (dann gilt serverseitig eine strengere Grenze).
    var zeit = doc.createElement('input');
    zeit.type = 'hidden';
    zeit.name = 't';
    form.appendChild(zeit);
    form.addEventListener('focusin', function () {
      if (zeit.value || zeit.dataset.geholt) return;
      zeit.dataset.geholt = '1';
      fetch(form.action + '?zeit=1', { headers: { Accept: 'application/json' } })
        .then(function (r) { return r.json(); })
        .then(function (res) { if (res && res.t) zeit.value = res.t; })
        .catch(function () {});
    });
    function melde(art, text) {
      status.hidden = false;
      status.className = 'form-status ' + art;
      status.textContent = text;
    }
    var vorschau = 'Vorschau: Das Formular ist noch nicht mit dem Server verbunden. Es wurde nichts gesendet.';
    form.addEventListener('submit', function (ev) {
      ev.preventDefault();
      if (!form.checkValidity()) {
        var erstes = form.querySelector(':invalid:not(fieldset)');
        melde('err', 'Bitte füll die markierten Pflichtfelder aus.');
        form.classList.add('geprueft');
        if (erstes) erstes.focus();
        return;
      }
      var btn = form.querySelector('[type="submit"]');
      btn.disabled = true;
      melde('info', 'Wird gesendet …');
      fetch(form.action, { method: 'POST', body: new FormData(form), headers: { Accept: 'application/json' } })
        .then(function (r) { return r.json().catch(function () { return { ok: false, demo: true }; }); })
        .then(function (res) {
          if (res.ok) {
            melde('ok', res.message || 'Danke! Deine Angaben sind angekommen.');
            form.reset();
            form.classList.remove('geprueft');
          } else if (res.demo) { melde('info', vorschau); }
          else { melde('err', res.message || 'Das hat nicht geklappt. Bitte versuch es später noch einmal oder schreib uns eine Mail.'); }
        })
        .catch(function () { melde('info', vorschau); })
        .then(function () { btn.disabled = false; });
    });
  });
})();
