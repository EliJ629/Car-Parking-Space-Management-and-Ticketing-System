/*
 * "Are all these details correct?" Before a form is sent, the answers are listed on a screen and the person confirms them.
 *
 *   <form data-confirm-details="#details-confirm" data-confirm-always>        sign up, Google finish, Add vehicle: every time
 *   <form data-confirm-details="#details-confirm" data-plate-original="KLM4455">   Edit vehicle: only when a plate is being set or replaced
 *   <input type="hidden" name="details_confirmed">                            set to 1 by "Yes, all correct"; the server checks it again
 */
(function () {
  'use strict';

  /* Starts after plate.js and inputs.js have set up their own checks, so a mistake is reported first. */
  function start() {
    var form = document.querySelector('form[data-confirm-details]');
    if (!form) return;
    var dlg = document.querySelector(form.getAttribute('data-confirm-details'));
    var flag = form.querySelector('[name="details_confirmed"]');
    if (!dlg || !flag) return;
    var list = dlg.querySelector('[data-confirm-list]');
    var yes = dlg.querySelector('[data-confirm-yes]');
    var no = dlg.querySelector('[data-confirm-no]');
    var always = form.hasAttribute('data-confirm-always');
    var original = form.getAttribute('data-plate-original') || '';
    var plate = document.getElementById('plate_number');
    var tick = document.getElementById('no_plate');

    function lib() { return window.BaylinePlate || null; }
    function key(v) { return lib() ? lib().normalize(v) : String(v || '').toUpperCase().replace(/[^A-Z0-9-]/g, ''); }
    function shown(v) { return lib() ? lib().format(v) : String(v || ''); }
    function val(id) { var e = document.getElementById(id); return e ? String(e.value || '').trim() : ''; }
    function chosen(id, other) { var v = val(id); return v === 'Others' ? val(other) : v; }

    /* True when the plate box holds a plate that is not the one already saved. */
    function plateIsNew() {
      if (!plate || plate.disabled || plate.readOnly || !plate.value.trim()) return false;
      return key(plate.value) !== original;
    }

    function needsConfirm() { return always || plateIsNew(); }

    function rows() {
      var out = [];
      var add = function (label, text, cls) { if (text) out.push([label, text, cls || '']); };
      add('Name', [val('first_name'), val('middle_name'), val('last_name')].filter(Boolean).join(' '));
      add('Gmail address', val('email'));
      add('Mobile number', val('phone'));
      add('Vehicle type', val('vehicle_type'));
      add('Vehicle model', chosen('make_model', 'make_model_other'));
      add('Color', chosen('color', 'color_other'));

      var temp = !!(tick && tick.checked);
      var value = plate ? plate.value.trim() : '';
      if (value) add('Plate number', shown(value) + (temp ? ' (temporary plate)' : ''), 'plate-line');
      else if (temp) add('Plate number', 'No plate yet (still in registration)');

      add('Contact number', val('contact_number'));
      if (document.getElementById('password') && val('password')) add('Password', 'Set (hidden)');
      return out;
    }

    function fill() {
      list.innerHTML = '';
      rows().forEach(function (r) {
        var row = document.createElement('div');
        var dt = document.createElement('dt'); dt.textContent = r[0];
        var dd = document.createElement('dd'); dd.textContent = r[1];
        if (r[2]) dd.className = r[2];
        row.appendChild(dt); row.appendChild(dd); list.appendChild(row);
      });
    }

    function close() { if (dlg.open) dlg.close(); }

    // Any change after the confirmation needs a new one.
    form.addEventListener('input', function () { flag.value = ''; });
    form.addEventListener('change', function () { flag.value = ''; });

    form.addEventListener('submit', function (e) {
      // A check in the page already stopped this submit, or the details were just confirmed.
      if (e.defaultPrevented) return;
      if (!needsConfirm() || flag.value === '1') return;

      e.preventDefault();
      e.stopImmediatePropagation();
      fill();
      if (typeof dlg.showModal === 'function') dlg.showModal(); else dlg.setAttribute('open', '');
    }, true);

    // The next send asks again, even if nothing was changed (for example after the server reported a problem).
    form.addEventListener('submit', function () { setTimeout(function () { flag.value = ''; }, 0); });

    if (yes) yes.addEventListener('click', function () {
      flag.value = '1';
      close();
      if (typeof form.requestSubmit === 'function') form.requestSubmit(); else form.submit();
    });
    if (no) no.addEventListener('click', close);
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start); else start();
})();
