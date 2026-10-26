/*
 * Name and mobile number inputs, for every form that has them. The server checks the same rules again.
 *
 *   <input data-person>                 letters (and single spaces) only; anything else is dropped as you type
 *   <input data-person data-person-optional>   empty is fine
 *   <input data-mobile data-optional>   empty is fine (a mobile number that is not required)
 *   <input data-mobile>                 digits only. 09... stops at 11 digits, 63... at 12, +63... at 13 characters
 *
 * A message appears once, when the person leaves the field (or submits). Typing, or emptying the field, clears it.
 * A key that the field does not accept (a number in a name box) simply does nothing: it is not an error, so it never
 * turns the field red. Only pasted or dropped text that had to be cleaned is reported, because the person did not see
 * what was removed.
 */
(function () {
  'use strict';

  var PERSON_BAD = /[^\p{L}\p{M}\s]/gu;

  function box(input) {
    if (input._msgBox) return input._msgBox;
    var next = input.nextElementSibling;
    var b = next && next.classList.contains('plate-msg') ? next : null;
    if (!b) {
      b = document.createElement('p');
      b.className = 'plate-msg';
      b.setAttribute('aria-live', 'polite');
      b.style.cssText = 'font-size:13px;margin:6px 0 0;color:var(--stop,#b42318);font-weight:600';
      b.hidden = true;
      input.insertAdjacentElement('afterend', b);
    }
    input._msgBox = b;
    return b;
  }

  function show(input, text) {
    var b = box(input);
    input.setCustomValidity(text || '');
    input.setAttribute('aria-invalid', text ? 'true' : 'false');
    b.textContent = text || '';
    b.hidden = !text;
  }

  function clearServer(input) {
    var server = input.parentNode && input.parentNode.querySelector('p.err');
    if (server) server.hidden = true;
    input.removeAttribute('aria-invalid');
  }

  function bind(input, fix, check) {
    if (input._inputsBound) return;
    input._inputsBound = true;
    var run = function (final, pasted) {
      var before = input.value;
      var fixed = fix(before);
      if (fixed.value !== before) input.value = fixed.value;
      if (fixed.dropped && pasted) input._dropped = true;
      if (!input.value) input._dropped = false;
      if (!final) { show(input, null); return; }
      var optional = input.hasAttribute('data-person-optional') || input.hasAttribute('data-optional');
      var empty = !input.value.trim();
      var p = null;
      if (input._dropped) p = 'Letters only. No numbers or special characters.';
      else if (!empty) p = check(input.value);
      else if (final === true && !optional) p = input.getAttribute('data-empty-message') || 'Fill in this field.';
      show(input, p);
    };
    input.addEventListener('input', function (e) {
      clearServer(input);
      var t = e && e.inputType ? e.inputType : '';
      var pasted = t === 'insertFromPaste' || t === 'insertFromDrop' || t === 'insertFromYank';
      // Typing a new character after a paste clears the paste note; the next paste sets it again.
      if (!pasted && t) input._dropped = false;
      run(false, pasted);
    });
    input.addEventListener('blur', function () { run('blur'); });
    if (input.form) input.form.addEventListener('submit', function (e) {
      run(true);
      if (!input.checkValidity()) { e.preventDefault(); input.focus(); }
    }, true);
  }

  /* Names */
  function fixPerson(v) {
    var cleaned = v.replace(PERSON_BAD, '').replace(/\s{2,}/g, ' ').replace(/^\s+/, '');
    // Spaces typed in front of nothing, or twice in a row, are quietly dropped. Anything else removed is reported on leave.
    var onlySpaces = v.replace(/\s+/g, ' ').replace(/^\s+/, '');
    return { value: cleaned, dropped: cleaned !== onlySpaces };
  }

  function checkPerson(input) {
    return function (v) {
      var s = v.trim();
      var min = input.hasAttribute('data-person-optional') ? 1 : 2;
      return s.length < min ? 'Enter at least ' + min + ' letters.' : null;
    };
  }

  /* Mobile numbers */
  function mobileLimit(v) {
    if (v.charAt(0) === '+') return 13;
    if (v.slice(0, 2) === '63') return 12;
    return 11;
  }

  function fixMobile(v) {
    var plus = v.charAt(0) === '+';
    var digits = v.replace(/\D/g, '');
    var out = (plus ? '+' : '') + digits;
    var limit = mobileLimit(out);
    if (out.length > limit) out = out.slice(0, limit);
    return { value: out, dropped: false };
  }

  function checkMobile(v) {
    if (v.slice(0, 2) === '09') return /^09\d{9}$/.test(v) ? null : 'A number that starts with 09 must be exactly 11 digits, for example 09171234567.';
    if (v.slice(0, 3) === '+63') return /^\+639\d{9}$/.test(v) ? null : 'A number that starts with +63 must be exactly 13 characters, for example +639171234567.';
    if (v.slice(0, 2) === '63') return /^639\d{9}$/.test(v) ? null : 'A number that starts with 63 must be exactly 12 digits, for example 639171234567.';
    return 'The number must start with 09, 63 or +63.';
  }

  function init() {
    document.querySelectorAll('input[data-person]').forEach(function (input) {
      input.setAttribute('autocapitalize', 'words');
      input.setAttribute('data-empty-message', input.getAttribute('data-empty-message') || (input.id === 'last_name' ? 'Enter your last name.' : 'Enter your first name.'));
      bind(input, fixPerson, checkPerson(input));
    });
    document.querySelectorAll('input[data-mobile]').forEach(function (input) {
      input.setAttribute('inputmode', 'tel');
      // Pasted text such as "+63 917 123 4567" has spaces. The script cleans it and applies the length rule, so the box takes more at first.
      input.maxLength = 24;
      input.setAttribute('data-empty-message', 'Enter your mobile number.');
      bind(input, fixMobile, checkMobile);
      // A value that was filled in by the page (or the browser) is tidied once at the start.
      if (input.value) { var f = fixMobile(input.value); if (f.value !== input.value) input.value = f.value; }
    });
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
