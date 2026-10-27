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
  document.querySelectorAll('[data-print]').forEach(function (b) { b.addEventListener('click', function () { window.print(); }); });
  document.querySelectorAll('select[data-autosubmit]').forEach(function (s) { s.addEventListener('change', function () { s.form.submit(); }); });
  document.querySelectorAll('input[data-search-safe]').forEach(function (input) {
    input.addEventListener('input', function () {
      input.value = input.value.replace(/[^\p{L}\p{N} -]/gu, '').slice(0, 20);
    });
  });

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

  /* Alerts: refresh the button and the panel, pop-up for a new alert. */
  if (shell) {
    var list = document.getElementById('alert-list');
    var badge = document.getElementById('alert-count');
    var known = (list.getAttribute('data-keys') || '').split(',').filter(Boolean);
    var liveDlg = document.getElementById('live-dlg');

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
            /* A pop-up, unless the guard is in the middle of another one. The badge still counts it. */
            if (liveDlg && !document.querySelector('dialog[open]')) {
              document.getElementById('live-t').textContent = a.title;
              document.getElementById('live-body').textContent = a.body;
              document.getElementById('live-open').href = a.url;
              openDialog(liveDlg);
            }
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

  /* Incident form: what the category requires, dependent fields, and live checks. */
  var inc = document.querySelector('[data-incident-form]');
  if (inc) {
    var rules = JSON.parse(inc.getAttribute('data-rules') || '{}');
    var plateIn = document.getElementById('plate_number'), placeIn = document.getElementById('location');
    var respIn = document.getElementById('responsible_plate'), respBox = document.getElementById('responsible-wrap');
    var unknownBox = document.getElementById('responsible_unknown');
    var otherIn = document.getElementById('other_label'), otherBox = document.getElementById('other-wrap');
    var textIn = document.getElementById('place_text'), textBox = document.getElementById('place-text-wrap');
    var plateTag = inc.querySelector('[data-need-plate]'), placeTag = inc.querySelector('[data-need-place]');
    var plateLabel = inc.querySelector('[data-plate-label]');
    var live = document.getElementById('plate-live'), placeHint = document.getElementById('place-hint');
    var respLive = document.getElementById('resp-live');
    var timer = null, respTimer = null;
    var cat = function () { var r = inc.querySelector('input[name=category]:checked'); return r ? r.value : ''; };
    var need = function () { return rules[cat()] || { plate: 'optional', place: 'optional' }; };
    var setErr = function (el, on) { if (el) el.setAttribute('aria-invalid', on ? 'true' : 'false'); };

    var applyRules = function () {
      var n = need(), c = cat();
      if (!c) return;
      var damage = n.responsible === 'required', other = n.label === 'required';
      plateLabel.textContent = damage ? 'Damaged vehicle plate' : 'Plate';
      plateTag.textContent = n.plate === 'required' ? '(required)' : '(optional)';
      plateIn.required = n.plate === 'required';
      if (plateIn.hasAttribute('data-plate-optional') && n.plate === 'required') plateIn.removeAttribute('data-plate-optional');
      if (!plateIn.hasAttribute('data-plate-optional') && n.plate !== 'required') plateIn.setAttribute('data-plate-optional', '');

      respBox.hidden = !damage;
      respIn.required = damage && !(unknownBox && unknownBox.checked);
      otherBox.hidden = !other;
      otherIn.required = other;

      var names = {
        slot: ['(required)', 'Only slots with a vehicle in them are listed.'],
        location: ['(choose one, or type another place)', 'Only slots with a vehicle in them are listed. Use "Other place" for somewhere else in the lot.'],
        auto: ['(filled in from the plate)', 'Taken from the parked vehicle.'],
        optional: ['(optional)', 'Only slots with a vehicle in them are listed.']
      };
      var p = names[n.place] || names.optional;
      placeTag.textContent = p[0]; if (placeHint) placeHint.textContent = p[1];
      placeIn.required = n.place === 'slot';
      textBox.hidden = !(n.place === 'location' || n.place === 'optional');
      if (n.place === 'auto') placeIn.value = placeIn.value;
    };

    var say = function (box, input, text, bad) { if (!box) return; box.textContent = text; box.className = bad ? 'g-err' : 'hint'; setErr(input, bad); };

    var checkPlate = function () {
      var v = plateIn.value.trim();
      if (!v) { say(live, plateIn, 'Only a vehicle that is parked in the lot now can be reported.', false); return; }
      if (window.BaylinePlate && window.BaylinePlate.problem(v)) return; /* plate.js shows the format message */
      fetch(inc.getAttribute('data-check') + '?plate=' + encodeURIComponent(v), { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (d) {
          if (plateIn.value.trim() !== v) return;
          if (d.state === 'parked') {
            say(live, plateIn, d.message, false);
            if (d.slot) { placeIn.value = d.slot; }
          } else { say(live, plateIn, d.message || 'That vehicle is not parked now.', true); }
        }).catch(function () {});
    };

    var checkResponsible = function () {
      var v = respIn.value.trim();
      if (!v || (unknownBox && unknownBox.checked)) { say(respLive, respIn, 'The report and any warning count against this vehicle, not the damaged one. It may have left already.', false); return; }
      if (window.BaylinePlate && window.BaylinePlate.problem(v)) return;
      if (plateIn.value.trim() && window.BaylinePlate && window.BaylinePlate.normalize(plateIn.value) === window.BaylinePlate.normalize(v)) {
        say(respLive, respIn, 'The responsible vehicle cannot be the damaged vehicle. Check both plates.', true); return;
      }
      fetch(inc.getAttribute('data-check') + '?role=responsible&plate=' + encodeURIComponent(v), { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (d) {
          if (respIn.value.trim() !== v) return;
          say(respLive, respIn, d.message || '', d.state === 'unknown' || d.state === 'invalid');
        }).catch(function () {});
    };

    /* The lookups run when the person leaves the field. While typing, the message goes back to its plain hint. */
    plateIn.addEventListener('input', function () { clearTimeout(timer); say(live, plateIn, 'Only a vehicle that is parked in the lot now can be reported.', false); });
    plateIn.addEventListener('blur', checkPlate);
    respIn.addEventListener('input', function () { clearTimeout(respTimer); say(respLive, respIn, 'The report and any warning count against this vehicle, not the damaged one. It may have left already.', false); });
    respIn.addEventListener('blur', checkResponsible);
    if (unknownBox) unknownBox.addEventListener('change', function () {
      respIn.disabled = unknownBox.checked;
      if (unknownBox.checked) { respIn.value = ''; respIn.setCustomValidity(''); }
      respIn.required = need().responsible === 'required' && !unknownBox.checked;
      checkResponsible();
    });

    /* Choosing a slot fills in the plate of the vehicle parked there. */
    placeIn.addEventListener('change', function () {
      var opt = placeIn.options[placeIn.selectedIndex];
      setErr(placeIn, false);
      if (opt && opt.getAttribute('data-label') && !plateIn.value.trim()) {
        plateIn.value = opt.getAttribute('data-label');
        plateIn.dispatchEvent(new Event('input', { bubbles: true }));
        say(live, plateIn, opt.getAttribute('data-label') + ' is parked in ' + opt.value + '.', false);
      }
    });

    inc.addEventListener('change', function (e) { if (e.target.name === 'category') applyRules(); });

    /* Say what is missing before the form leaves the screen. The server checks it all again. */
    inc.addEventListener('submit', function (e) {
      var n = need(), c = cat(), problems = [];
      if (!c) problems.push([inc.querySelector('input[name=category]'), 'Choose a category.']);
      if (n.label === 'required' && !otherIn.value.trim()) problems.push([otherIn, 'Say what kind of incident this is.']);
      var d = document.getElementById('description');
      if (d && d.value.trim().length < 10) problems.push([d, 'Write at least 10 characters.']);
      if (n.plate === 'required' && !plateIn.value.trim() && !(document.querySelector('input[name=session_id]') || {}).value) problems.push([plateIn, 'Enter the plate number.']);
      if (n.place === 'slot' && !placeIn.value && !plateIn.value.trim()) problems.push([placeIn, 'Choose an occupied slot.']);
      if (n.place === 'location' && !placeIn.value && !textIn.value.trim()) problems.push([placeIn, 'Choose a slot, or type another place.']);
      if (n.responsible === 'required' && !respIn.value.trim() && !(unknownBox && unknownBox.checked)) problems.push([respIn, 'Enter the responsible plate, or tick "Responsible vehicle unknown".']);
      if (!problems.length) return;
      e.preventDefault();
      problems.forEach(function (p) {
        setErr(p[0], true);
        var hint = p[0].closest('.fld, section') && p[0].closest('.fld, section').querySelector('.hint, .g-err');
        if (hint && p[0].id) { hint.textContent = p[1]; hint.className = 'g-err'; }
      });
      if (problems[0][0].focus) problems[0][0].focus();
    });

    applyRules();
    if (respIn.value) checkResponsible();
    if (unknownBox && unknownBox.checked) respIn.disabled = true;
    if (plateIn.value) checkPlate();
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

    /* Pick dates: no day after today in either box, and the start never after the end. */
    var fromEl = document.getElementById('from'), toEl = document.getElementById('to');
    if (fromEl && toEl) {
      var pad = function (n) { return (n < 10 ? '0' : '') + n; };
      var d = new Date();
      var today = d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate());
      var guard = function (changed) {
        [fromEl, toEl].forEach(function (el) { el.max = today; if (el.value && el.value > today) el.value = today; });
        if (fromEl.value && toEl.value && fromEl.value > toEl.value) { if (changed === fromEl) toEl.value = fromEl.value; else fromEl.value = toEl.value; }
        fromEl.max = toEl.value && toEl.value < today ? toEl.value : today;
        toEl.min = fromEl.value || '';
      };
      [fromEl, toEl].forEach(function (el) {
        el.addEventListener('input', function () { guard(el); });
        el.addEventListener('change', function () { guard(el); });
      });
      guard(null);
    }
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
        var report = sheet.querySelector('[data-f=report]');
        if (report) { var url = btn.getAttribute('data-report'); report.hidden = !url; if (url) report.setAttribute('href', url); }
        openDialog(sheet);
      });
    });
  }
})();
