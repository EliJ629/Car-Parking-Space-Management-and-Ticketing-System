/* Bayline driver app: pop-ups, QR codes, the bell, the reserve time chips and the slot sheet. */
(function () {
  'use strict';

  function openDialog(d) { if (d && typeof d.showModal === 'function' && !d.open) d.showModal(); }

  document.addEventListener('click', function (e) {
    var o = e.target.closest('[data-open]');
    if (o) { e.preventDefault(); openDialog(document.querySelector(o.getAttribute('data-open'))); return; }
    var c = e.target.closest('[data-close]');
    if (c) { var d = c.closest('dialog'); if (d) { e.preventDefault(); d.close(); } return; }
    if (e.target.tagName === 'DIALOG') {
      var r = e.target.getBoundingClientRect();
      if (e.clientX < r.left || e.clientX > r.right || e.clientY < r.top || e.clientY > r.bottom) e.target.close();
    }
  });

  document.querySelectorAll('dialog[data-autoopen]').forEach(openDialog);
  document.querySelectorAll('[data-print]').forEach(function (b) { b.addEventListener('click', function () { window.print(); }); });
  document.querySelectorAll('select[data-autosubmit]').forEach(function (s) { s.addEventListener('change', function () { s.form.submit(); }); });
  document.querySelectorAll('.dtoast').forEach(function (t) { setTimeout(function () { t.hidden = true; }, 4500); });

  /* QR codes: <div data-qr="RSV-3H8P54"></div> */
  document.querySelectorAll('[data-qr]').forEach(function (el) {
    try {
      var q = qrcode(0, 'M');
      q.addData(el.getAttribute('data-qr'));
      q.make();
      var n = q.getModuleCount(), p = '';
      for (var r = 0; r < n; r++) for (var c = 0; c < n; c++) if (q.isDark(r, c)) p += 'M' + (c + 3) + ' ' + (r + 3) + 'h1v1h-1z';
      el.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' + (n + 6) + ' ' + (n + 6) + '" role="img" aria-label="QR code for ' + el.getAttribute('data-qr') + '" shape-rendering="crispEdges"><path d="' + p + '" fill="#1b2127"/></svg>';
    } catch (err) { /* the code is printed under the QR anyway */ }
  });

  /* The bell: poll the unread count and show a banner for a new notification. */
  var app = document.getElementById('dapp');
  if (app && app.getAttribute('data-count-url')) {
    var url = app.getAttribute('data-count-url');
    var badge = document.getElementById('bell-count');
    var bell = document.getElementById('bell');
    var KEY = 'bayline-notice-seen';
    var seen = function () { try { return parseInt(sessionStorage.getItem(KEY) || '0', 10); } catch (e) { return 0; } };
    var mark = function (id) { try { sessionStorage.setItem(KEY, String(id)); } catch (e) { /* ignore */ } };
    var first = parseInt(app.getAttribute('data-latest') || '0', 10);
    if (seen() < first) mark(first);

    var banner = document.createElement('a');
    banner.className = 'banner-app';
    banner.hidden = true;
    banner.setAttribute('role', 'status');
    document.body.appendChild(banner);

    var tick = function () {
      if (document.hidden) return;
      fetch(url, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
        .then(function (r) { return r.ok ? r.json() : null; })
        .then(function (d) {
          if (!d) return;
          badge.textContent = d.count;
          badge.hidden = d.count < 1;
          if (bell) bell.setAttribute('aria-label', 'Notifications, ' + d.count + ' unread');
          if (d.latest && d.latest.id > seen()) {
            mark(d.latest.id);
            banner.innerHTML = '';
            var b = document.createElement('b'); b.textContent = d.latest.title;
            var s = document.createElement('span'); s.textContent = d.latest.body;
            banner.appendChild(b); banner.appendChild(s);
            banner.href = d.latest.url;
            banner.hidden = false;
            setTimeout(function () { banner.hidden = true; }, 8000);
          }
        })
        .catch(function () { /* offline: try again next time */ });
    };
    setInterval(tick, 15000);
  }

  /* Reserve step 1: time chips follow the chosen day. */
  var when = document.querySelector('[data-reserve-when]');
  if (when) {
    var holder = document.getElementById('timechips');
    var timeInput = document.getElementById('time-input');
    var other = document.getElementById('other-date');
    var today = when.getAttribute('data-today');
    var nowTime = when.getAttribute('data-now');
    var pad = function (n) { return (n < 10 ? '0' : '') + n; };
    var label = function (h, m) { var ap = h >= 12 ? 'PM' : 'AM'; var hh = h % 12 || 12; return hh + ':' + pad(m) + ' ' + ap; };
    var chosenDay = function () {
      if (other && other.value) return other.value;
      var r = when.querySelector('input[name=date]:checked');
      return r ? r.value : today;
    };
    var build = function () {
      var isToday = chosenDay() === today, h, m, step;
      if (isToday) {
        var parts = nowTime.split(':'); h = parseInt(parts[0], 10); m = Math.ceil((parseInt(parts[1], 10) + 1) / 15) * 15; step = 15;
        if (m >= 60) { h += 1; m -= 60; }
      } else { h = 7; m = 0; step = 30; }
      var html = '';
      for (var i = 0; i < 6 && h < 24; i++) {
        var v = pad(h) + ':' + pad(m);
        html += '<label class="cp"><input type="radio" name="timechip" value="' + v + '"' + (timeInput.value === v ? ' checked' : '') + '><span>' + label(h, m) + '</span></label>';
        m += step; if (m >= 60) { h += 1; m -= 60; }
      }
      holder.innerHTML = html;
    };
    holder.addEventListener('change', function (e) { if (e.target.name === 'timechip') timeInput.value = e.target.value; });
    timeInput.addEventListener('input', function () {
      holder.querySelectorAll('input').forEach(function (r) { r.checked = r.value === timeInput.value; });
    });
    when.querySelectorAll('input[name=date]').forEach(function (r) {
      r.addEventListener('change', function () { if (other) other.value = ''; build(); });
    });
    if (other) other.addEventListener('change', function () {
      when.querySelectorAll('input[name=date]').forEach(function (r) { r.checked = false; });
      build();
    });
    build();
  }

  /* Find parking: tap a slot to open its sheet. */
  var sheet = document.getElementById('slot-sheet');
  if (sheet) {
    document.querySelectorAll('button[data-slot]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        sheet.querySelector('[data-f=title]').textContent = 'Slot ' + btn.getAttribute('data-slot');
        sheet.querySelector('[data-f=zone]').textContent = btn.getAttribute('data-zone');
        sheet.querySelector('[data-f=type]').textContent = btn.getAttribute('data-type');
        var st = sheet.querySelector('[data-f=status]');
        st.textContent = btn.getAttribute('data-status');
        st.className = 'status-tag ' + (btn.getAttribute('data-free') === '1' ? 'st-booked' : 'st-completed');
        var go = sheet.querySelector('[data-f=reserve]');
        var free = btn.getAttribute('data-free') === '1';
        go.hidden = !free;
        go.href = btn.getAttribute('data-reserve');
        openDialog(sheet);
      });
    });
  }
})();
