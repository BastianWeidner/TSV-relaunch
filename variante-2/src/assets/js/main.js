// TSV Fischach Schwimmen – das einzige Skript der Seite. Alles funktioniert auch ohne.
(function () {
  // Mobilmenü
  var toggle = document.querySelector('.nav-toggle');
  var nav = document.getElementById('hauptmenue');
  if (toggle && nav) {
    toggle.addEventListener('click', function () {
      var open = nav.classList.toggle('open');
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
  }

  // Hell/Dunkel (nur im eigenen Browser gespeichert)
  document.querySelectorAll('[data-theme-switch]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var root = document.documentElement;
      var dark = root.dataset.theme
        ? root.dataset.theme === 'dark'
        : window.matchMedia('(prefers-color-scheme: dark)').matches;
      root.dataset.theme = dark ? 'light' : 'dark';
      try { localStorage.setItem('theme', root.dataset.theme); } catch (e) {}
    });
  });

  // Formulare: per fetch senden, damit die Seite stehen bleibt.
  // Ohne JavaScript schickt der Browser das Formular normal ab (api/formular.php leitet auf danke.html).
  document.querySelectorAll('form.js-form:not([data-vorschau])').forEach(function (form) {
    var status = form.querySelector('.form-status');
    // Zeitfalle: beim ersten Klick ins Formular einen signierten Zeitstempel holen.
    // Klappt das nicht, wird trotzdem gesendet (dann gilt serverseitig eine strengere Grenze).
    var zeit = document.createElement('input');
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
    function show(kind, text) {
      status.hidden = false;
      status.className = 'form-status ' + kind;
      status.textContent = text;
    }
    form.addEventListener('submit', function (ev) {
      ev.preventDefault();
      if (!form.checkValidity()) {
        var first = form.querySelector(':invalid');
        show('err', 'Bitte füll die markierten Pflichtfelder aus.');
        if (first) first.focus();
        form.classList.add('checked');
        return;
      }
      var btn = form.querySelector('[type="submit"]');
      btn.disabled = true;
      show('info', 'Wird gesendet …');
      fetch(form.action, { method: 'POST', body: new FormData(form), headers: { Accept: 'application/json' } })
        .then(function (r) {
          return r.json().catch(function () { return { ok: false, demo: true }; });
        })
        .then(function (res) {
          if (res.ok) { show('ok', res.message || 'Danke! Deine Angaben sind angekommen.'); form.reset(); }
          else if (res.demo) { show('info', 'Vorschau: Das Formular ist noch nicht mit dem Server verbunden. Es wurde nichts gesendet.'); }
          else { show('err', res.message || 'Das hat nicht geklappt. Bitte versuch es später noch einmal oder schreib uns eine Mail.'); }
        })
        .catch(function () {
          show('info', 'Vorschau: Das Formular ist noch nicht mit dem Server verbunden. Es wurde nichts gesendet.');
        })
        .then(function () { btn.disabled = false; });
    });
  });
})();
