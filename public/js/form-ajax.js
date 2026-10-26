/*
 * <form data-ajax>: send the form in the background.
 *
 * A mistake that only the server can find (a Gmail address that is already registered, a plate that belongs to another
 * account) is shown next to its field without loading the page again, so the other answers, the scroll position and
 * the passwords stay as they are. A form with no mistakes goes on to the next page as before.
 * If anything unexpected happens (no network, a session that ran out), the form is sent the ordinary way.
 * The checks in plate.js and inputs.js run first and can stop the form before anything is sent.
 */
(function () {
  'use strict';
  if (typeof fetch !== 'function' || typeof FormData === 'undefined') return;

  function clearErrors(form) {
    form.querySelectorAll('p.err[data-ajax-err]').forEach(function (p) { p.remove(); });
    form.querySelectorAll('[data-ajax-hid]').forEach(function (h) { h.hidden = false; h.removeAttribute('data-ajax-hid'); });
    form.querySelectorAll('[aria-invalid="true"]').forEach(function (i) { i.removeAttribute('aria-invalid'); });
    var old = document.querySelector('[data-ajax-alert]');
    if (old) old.remove();
  }

  function fieldFor(form, key) {
    var el = form.querySelector('[name="' + key.replace(/"/g, '') + '"]');
    // The full-name check (name already registered) is reported on the last name box.
    if (!el && key === 'name') el = form.querySelector('[name="last_name"]');
    // "Confirm the plate is correct" has no box of its own; it is reported under the plate.
    if (key === 'plate_confirmed') el = form.querySelector('[name="plate_number"]') || el;
    return el;
  }

  function showErrors(form, errors) {
    clearErrors(form);
    var first = null, loose = [];
    Object.keys(errors).forEach(function (key) {
      var msg = [].concat(errors[key])[0];
      var el = fieldFor(form, key);
      if (!el) { loose.push(msg); return; }
      var box = el.closest('.fld') || el.parentNode;
      // The server draws one message per field, in place of the hint under it.
      box.querySelectorAll('p.err').forEach(function (p) { p.remove(); });
      box.querySelectorAll('p.hint').forEach(function (h) { h.hidden = true; h.setAttribute('data-ajax-hid', '1'); });
      var p = document.createElement('p');
      p.className = 'err';
      p.setAttribute('data-ajax-err', '1');
      p.textContent = msg;
      var anchor = el;
      while (anchor.nextElementSibling && anchor.nextElementSibling.classList.contains('plate-msg')) anchor = anchor.nextElementSibling;
      anchor.insertAdjacentElement('afterend', p);
      el.setAttribute('aria-invalid', 'true');
      if (!first) first = el;
    });

    var count = Object.keys(errors).length;
    var alert = document.createElement('div');
    alert.setAttribute('data-ajax-alert', '1');
    alert.setAttribute('role', 'alert');
    alert.className = document.querySelector('.dapp') ? 'err-box' : 'alert stop';
    alert.innerHTML = '<b></b>';
    alert.firstChild.textContent = loose.length ? loose[0] : 'Please fix the ' + (count === 1 ? 'highlighted field' : 'highlighted fields') + ' below.';
    form.insertAdjacentElement('beforebegin', alert);

    if (first) {
      first.scrollIntoView({ block: 'center', behavior: 'smooth' });
      try { first.focus({ preventScroll: true }); } catch (e) { first.focus(); }
    } else {
      alert.scrollIntoView({ block: 'center', behavior: 'smooth' });
    }
  }

  document.querySelectorAll('form[data-ajax]').forEach(function (form) {
    var sending = false;

    form.addEventListener('submit', function (e) {
      if (sending) { e.preventDefault(); return; }
      // A check in the page (plate.js, inputs.js) already stopped this submit.
      if (e.defaultPrevented) return;
      e.preventDefault();
      sending = true;

      var btn = form.querySelector('button[type=submit]');
      var label = btn ? btn.textContent : '';
      var restore = function () {
        sending = false;
        if (btn) { btn.disabled = false; btn.textContent = label; }
      };

      fetch(form.action, {
        method: 'POST',
        body: new FormData(form),
        credentials: 'same-origin',
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-Bayline-Ajax': '1' }
      }).then(function (res) {
        if (!/json/.test(res.headers.get('content-type') || '')) throw new Error('not json');
        return res.json().then(function (data) { return { status: res.status, data: data }; });
      }).then(function (r) {
        if (r.status === 422 && r.data && r.data.errors) { restore(); showErrors(form, r.data.errors); return; }
        if (r.data && r.data.redirect) { window.location.assign(r.data.redirect); return; }
        throw new Error('unexpected');
      }).catch(function () {
        sending = false;
        form.submit();
      });
    });
  });
})();
