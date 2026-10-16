/* Bayline guard console: pop-ups, the clock, polling, alerts, the three checks and the idle warning. */
(function () {
  'use strict';
  var shell = document.getElementById('gshell');

  function openDialog(d) { if (d && typeof d.showModal === 'function' && !d.open) d.showModal(); }

  document.addEventListener('click', function (e) {
    var o = e.target.closest('[data-open]');
    if (o) { e.preventDefault(); openDialog(document.querySelector(o.getAttribute('data-open'))); return; }
    var c = e.target.closest('[data-close]');
    if (c) { var d = c.closest('dialog'); if (d) { e.preventDefault(); d.close(); } return; }
    if (e.target.tagName === 'DIALOG' && e.target.id !== 'idle-dlg') {
      var r = e.target.getBoundingClientRect();
      if (e.clientX < r.left || e.clientX > r.right || e.clientY < r.top || e.clientY > r.bottom) e.target.close();
    }
  });
  document.querySelectorAll('dialog[data-autoopen]').forEach(openDialog);
  document.querySelectorAll('.g-toast').forEach(function (t) { setTimeout(function () { t.hidden = true; }, 5000); });
  document.querySelectorAll('[data-print]').forEach(function (b) { b.addEventListener('click', function () { window.print(); }); });
  document.querySelectorAll('select[data-autosubmit]').forEach(function (s) { s.addEventListener('change', function () { s.form.submit(); }); });

  /* The clock follows the lot's time zone. */
  var clock = document.getElementById('clock');
  if (clock && window.Intl) {
    var tick = function () {
      try {
        var d = new Date();
        var day = new Intl.DateTimeFormat('en-US', { timeZone: 'Asia/Manila', weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' }).format(d);
        var t = new Intl.DateTimeFormat('en-US', { timeZone: 'Asia/Manila', hour: 'numeric', minute: '2-digit' }).format(d);
        clock.innerHTML = '';
        clock.appendChild(document.createTextNode(day.replace(/(\w+), (\w+ \d+), (\d+)/, '$1, $2, $3') + ' '));
        var b = document.createElement('b'); b.textContent = t; clock.appendChild(b);
      } catch (e) { /* keep the server time */ }
    };
    setInterval(tick, 20000);
  }

  function pollFetch(url) {
    return fetch(url, { headers: { 'Accept': 'application/json', 'X-Bayline-Poll': '1' }, credentials: 'same-origin' }).then(function (r) {
      var type = r.headers.get('content-type') || '';
      if (!r.ok || type.indexOf('json') < 0) { if (shell) window.location.href = shell.getAttribute('data-login'); return null; }
      return r.json();
    });
  }

  /* Alerts: refresh the button and the panel, toast for a new alert. */
  if (shell) {
    var list = document.getElementById('alert-list');
    var badge = document.getElementById('alert-count');
    var known = (list.getAttribute('data-keys') || '').split(',').filter(Boolean);
    var toasts = document.getElementById('g-toasts');

    var render = function (items) {
      list.innerHTML = '';
      if (!items.length) { var n = document.createElement('li'); n.className = 'none'; n.textContent = 'No alerts right now.'; list.appendChild(n); return; }
      items.forEach(function (a) {
        var li = document.createElement('li'); if (a.unread) li.className = 'unread';
        var link = document.createElement('a'); link.href = a.url;
        var b = document.createElement('b'); b.textContent = a.title;
        var s = document.createElement('span'); s.textContent = a.body;
        var m = document.createElement('small'); m.textContent = a.time;
        link.appendChild(b); link.appendChild(s); link.appendChild(m); li.appendChild(link); list.appendChild(li);
      });
    };

    var poll = function () {
      if (document.hidden) return;
      pollFetch(shell.getAttribute('data-alerts-url')).then(function (d) {
        if (!d) return;
        badge.textContent = d.count; badge.hidden = d.count < 1;
        render(d.items);
        d.items.forEach(function (a) {
          if (a.unread && known.indexOf(a.key) < 0) {
            var t = document.createElement('a'); t.href = a.url; t.className = 'stop'; t.setAttribute('role', 'status');
            var b = document.createElement('b'); b.textContent = a.title;
            var s = document.createElement('span'); s.textContent = a.body + ' Open';
            t.appendChild(b); t.appendChild(s); toasts.appendChild(t);
            setTimeout(function () { t.remove(); }, 12000);
          }
        });
        known = d.items.map(function (a) { return a.key; });
      }).catch(function () { /* try again next time */ });
    };
    setInterval(poll, 20000);

    document.getElementById('alerts-read').addEventListener('click', function () {
      fetch(shell.getAttribute('data-alerts-read'), { method: 'POST', headers: { 'X-CSRF-TOKEN': shell.getAttribute('data-csrf'), 'Accept': 'application/json', 'X-Bayline-Poll': '1' }, credentials: 'same-origin' })
        .then(function () { badge.hidden = true; list.querySelectorAll('li.unread').forEach(function (li) { li.classList.remove('unread'); }); });
    });

    /* Idle warning one minute before the server signs the guard out. */
    var idleMs = Math.max(2, parseInt(shell.getAttribute('data-idle'), 10) || 15) * 60000;
    var last = Date.now(), lastPing = Date.now();
    var idleDlg = document.getElementById('idle-dlg'), idleLeft = document.getElementById('idle-left');
    var ping = function () {
      lastPing = Date.now();
      fetch(shell.getAttribute('data-keepalive'), { method: 'POST', headers: { 'X-CSRF-TOKEN': shell.getAttribute('data-csrf') }, credentials: 'same-origin' }).catch(function () {});
    };
    var activity = function () {
      last = Date.now();
      if (!idleDlg.open && Date.now() - lastPing > 60000) ping();
    };
    ['click', 'keydown', 'touchstart', 'mousemove', 'scroll'].forEach(function (ev) { window.addEventListener(ev, activity, { passive: true }); });
    document.getElementById('idle-stay').addEventListener('click', function () { idleDlg.close(); last = Date.now(); ping(); });
    idleDlg.addEventListener('cancel', function (e) { e.preventDefault(); });
    setInterval(function () {
      var left = idleMs - (Date.now() - last);
      if (left <= 0) { window.location.href = shell.getAttribute('data-login'); return; }
      if (left <= 60000) { idleLeft.textContent = Math.ceil(left / 1000); openDialog(idleDlg); }
    }, 1000);
  }

  /* Queue page: reload when the list of paid vehicles changes, tick the minutes left. */
  var queue = document.getElementById('queue');
  if (queue) {
    var ids = queue.getAttribute('data-ids');
    setInterval(function () {
      if (document.hidden || document.querySelector('dialog[open]')) return;
      pollFetch(queue.getAttribute('data-feed')).then(function (d) {
        if (!d) return;
        if (d.ids.join(',') !== ids) { window.location.reload(); return; }
        queue.querySelectorAll('[data-left-for]').forEach(function (el) {
          var m = d.left[el.getAttribute('data-left-for')];
          if (typeof m === 'number') el.textContent = m < 1 ? 'Ending' : m + ' min';
        });
      }).catch(function () {});
    }, 15000);
  }

  /* Exit detail: three checks, progress line, confirm pop-up. */
  var verify = document.getElementById('verify');
  if (verify) {
    var boxes = verify.querySelectorAll('input[type=checkbox][name^="checks"]');
    var prog = document.getElementById('progress');
    var go = document.getElementById('confirm-form');
    var count = function () { var n = 0; boxes.forEach(function (b) { if (b.checked) n++; b.closest('.g-check').classList.remove('bad'); }); return n; };
    var update = function () { var n = count(); prog.textContent = n + ' of ' + boxes.length + ' checks done'; };
    boxes.forEach(function (b) { b.addEventListener('change', update); });
    update();
    verify.addEventListener('submit', function (e) {
      if (go && count() === boxes.length) { e.preventDefault(); openDialog(document.getElementById('confirm-go')); return; }
      if (go) {
        e.preventDefault();
        boxes.forEach(function (b) { if (!b.checked) b.closest('.g-check').classList.add('bad'); });
        prog.textContent = 'Finish all ' + boxes.length + ' checks before Good to Go. Unchecked items are outlined in red.';
        prog.style.color = 'var(--stop)'; prog.style.fontWeight = '700';
      }
    });
    if (go) go.addEventListener('submit', function () {
      boxes.forEach(function (b) {
        var h = document.createElement('input'); h.type = 'hidden'; h.name = b.name; h.value = b.checked ? '1' : '0'; go.appendChild(h);
      });
      var btn = go.querySelector('button[type=submit]'); if (btn) { setTimeout(function () { btn.disabled = true; btn.textContent = 'Approving'; }, 0); }
    });
  }

  /* Incident form: category radios and photo previews. */
  document.querySelectorAll('.g-photo input[type=file]').forEach(function (inp) {
    inp.addEventListener('change', function () {
      var f = inp.files && inp.files[0], box = inp.closest('.g-photo');
      var old = box.querySelector('img'); if (old) old.remove();
      if (f && window.URL) { var img = document.createElement('img'); img.alt = f.name; img.src = URL.createObjectURL(f); box.appendChild(img); }
      var n = document.querySelectorAll('.g-photo input[type=file]'), c = 0;
      n.forEach(function (i) { if (i.files && i.files.length) c++; });
      var out = document.getElementById('photo-count'); if (out) out.textContent = c + ' of 3 added. JPG or PNG.';
    });
  });

  /* Records: show the custom date inputs only for a custom range. */
  var range = document.getElementById('range');
  if (range) {
    var custom = document.querySelectorAll('[data-custom]');
    var sync = function () { custom.forEach(function (el) { el.hidden = range.value !== 'custom'; }); };
    range.addEventListener('change', sync); sync();
  }

  /* Slots view: tap a slot for its detail. */
  var sheet = document.getElementById('slot-dlg');
  if (sheet) {
    document.querySelectorAll('button[data-slot]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        sheet.querySelector('[data-f=title]').textContent = 'Slot ' + btn.getAttribute('data-slot');
        ['zone', 'type', 'status', 'vehicle', 'since', 'booking', 'reason'].forEach(function (k) {
          var row = sheet.querySelector('[data-row=' + k + ']');
          var v = btn.getAttribute('data-' + k);
          if (row) { row.style.display = v ? 'contents' : 'none'; var dd = row.querySelector('dd'); if (dd) dd.textContent = v || ''; }
        });
        openDialog(sheet);
      });
    });
  }
})();
