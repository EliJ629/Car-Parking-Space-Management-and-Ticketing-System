/*
 * Plate and ticket inputs. One script for every screen, mirroring App\Support\PlateNumber and
 * App\Support\TicketNumber on the server (the server always checks again).
 *
 *   <input data-plate>                  upper case, spaced as you type, message under the field
 *   <input data-plate data-plate-optional>   empty is fine
 *   <input data-plate data-plate-type="#vehicle_type" data-plate-hint="#plate-hint">   the formats follow the vehicle type:
 *        the box stays closed until a type is chosen, and the hint and example change with it
 *   <input data-ticket>                 PK-0003 style
 *   <input data-plate-or-ticket>        gate and booth search: either a plate or a ticket
 */
(function () {
  'use strict';

  var PATTERN = /^(?:[A-Z]{3}[0-9]{2,4}|[A-Z]{2}[0-9]{3,4}|[A-Z][0-9]{3,4}[A-Z]{2}|[A-Z][0-9]{4,5}|[0-9]{3}[A-Z]{3}|[0-9]{4}[A-Z]{2}|[0-9]{2}-[0-9]{4}|[0-9]{6,7}|[0-9]{4}-[0-9]{11}|[1-9]|1[0-7])$/;
  var MESSAGE = 'This is not a supported Philippine plate format. Examples: ABC 1234, ABC 123, A 123 BC, 123 ABC, AB 1234, 1234 AB, 12-3456, 123 4567, or a government plate from 1 to 17.';
  /* By vehicle type, as PlateNumber::PATTERN_FOUR and PATTERN_TWO: cars, SUVs, vans, pickups and trucks ("four"), motorcycles and tricycles ("two"). */
  var PATTERN_FOUR = /^(?:[A-Z]{3}[0-9]{2,4}|[A-Z][0-9]{4}|[A-Z]{2}[0-9]{3,4}|[A-Z][0-9]{5}|[A-Z][0-9]{4}[A-Z]{2}|[0-9]{3}[A-Z]{3}|[0-9]{2}-[0-9]{4}|[0-9]{6,7}|[1-9]|1[0-7])$/;
  var PATTERN_TWO = /^(?:[A-Z][0-9]{3}[A-Z]{2}|[0-9]{4}[A-Z]{2}|[0-9]{4}-[0-9]{11}|[A-Z]{2}[0-9]{3,4}|[A-Z][0-9]{5}|[A-Z][0-9]{4}[A-Z]{2}|[0-9]{3}[A-Z]{3}|[0-9]{2}-[0-9]{4}|[0-9]{6,7}|[1-9]|1[0-7])$/;
  /* A temporary plate (the vehicle is still in registration), as PlateNumber::PATTERN_TEMP_FOUR and PATTERN_TEMP_TWO. */
  var TEMP_FOUR = /^(?:[A-Z][0-9]{4}|[A-Z]{2}[0-9]{4})$/;
  var TEMP_TWO = /^[0-9]{4}-[0-9]{11}$/;
  var TEMP_MESSAGE = 'A temporary plate looks like T 1234 or AB 1234 for a car, SUV, van or pickup, and 1234-12345678901 for a motorcycle or tricycle.';
  var MAX = 20;

  /* What may be typed. Each plate format as a shape (L letter, D digit, - dash); a typed plate is accepted as long as it is
     the start of one of the shapes of the chosen vehicle type. Shapes mirror the patterns above. */
  var SHARED_SHAPES = ['LLDDD', 'LLDDDD', 'LDDDDD', 'LDDDDLL', 'DDDLLL', 'DD-DDDD', 'DDDDDD', 'DDDDDDD'];
  var SHAPES = {
    four: SHARED_SHAPES.concat(['LLLDD', 'LLLDDD', 'LLLDDDD', 'LDDDD']),
    two: SHARED_SHAPES.concat(['LDDDLL', 'DDDDLL', 'DDDD-DDDDDDDDDDD']),
    none: SHARED_SHAPES.concat(['LLLDD', 'LLLDDD', 'LLLDDDD', 'LDDDD', 'LDDDLL', 'DDDDLL', 'DDDD-DDDDDDDDDDD']),
    tempfour: ['LDDDD', 'LLDDDD'],
    temptwo: ['DDDD-DDDDDDDDDDD'],
    tempnone: ['LDDDD', 'LLDDDD', 'DDDD-DDDDDDDDDDD']
  };

  function wheels(type) {
    type = String(type || '').trim().toLowerCase();
    if (type === 'motorcycle' || type === 'tricycle') return 'two';
    return ['car', 'suv', 'van', 'pickup', 'truck'].indexOf(type) !== -1 ? 'four' : null;
  }

  function kind(c) { return /[0-9]/.test(c) ? 'd' : (/[A-Z]/.test(c) ? 'l' : 'x'); }

  /* Same rules as PlateNumber::normalize: a separator between a letter and a digit is dropped; between two digits it is
     kept when it is a dash (12-3456), dropped after the first three digits of an all-number plate (123 4567), and
     otherwise kept as "_" so the plate stays invalid. */
  function normalize(raw) {
    raw = String(raw || '').toUpperCase().trim();
    if (!raw) return '';
    var tokens = raw.split(/([\s\-._]+)/).filter(Boolean);
    var numeric = !/[^0-9\s\-._]/.test(raw), key = '', sep = null;
    tokens.forEach(function (t) {
      if (/^[\s\-._]+$/.test(t)) { sep = t; return; }
      if (key !== '' && sep !== null) {
        var a = kind(key.charAt(key.length - 1)), b = kind(t.charAt(0));
        if (a !== b && a !== 'x' && b !== 'x') { /* dropped */ }
        else if (a === 'd' && b === 'd') key += sep.indexOf('-') !== -1 ? '-' : (numeric && key.length === 3 ? '' : '_');
        else key += '_';
      }
      key += t; sep = null;
    });
    return key;
  }

  function isStart(key, shapes) {
    if (key.indexOf('_') !== -1) return false;
    var shape = key.replace(/[A-Z]/g, 'L').replace(/[0-9]/g, 'D');
    if (shape === '') return true;
    return shapes.some(function (s) { return s.indexOf(shape) === 0; });
  }

  /* Keeps only the part of what was typed or pasted that can still become a plate of this vehicle type.
     A character that no format allows at that spot is dropped, so the box never fills with something that cannot be a plate. */
  function limit(raw, type, temporary) {
    var shapes = SHAPES[(temporary ? 'temp' : '') + (wheels(type) || 'none')];
    var out = '';
    String(raw || '').toUpperCase().split('').forEach(function (ch) {
      if (!/[A-Z0-9\s\-._]/.test(ch)) return;
      if (/[\s\-._]/.test(ch)) {
        if (out === '' || /[\s\-._]$/.test(out)) return;
        var next = out + ch;
        if (isStart(normalize(next + '0'), shapes) || isStart(normalize(next + 'A'), shapes)) out = next;
        return;
      }
      if (isStart(normalize(out + ch), shapes)) out += ch;
      else if (/^[0-9]{4}$/.test(out) && /[0-9]/.test(ch) && isStart(normalize(out + '-' + ch), shapes)) out += '-' + ch; /* 1234 then 5 becomes 1234-5 */
    });
    return out;
  }

  function format(raw) {
    var key = normalize(raw);
    if (!key) return '';
    if (key.indexOf('_') !== -1) return key.replace(/_/g, ' ');
    if (key.indexOf('-') !== -1) return key;
    if (/^[0-9]+$/.test(key)) return (key.length === 6 || key.length === 7) ? key.slice(0, 3) + ' ' + key.slice(3) : key;
    var runs = key.match(/[A-Z]+|[0-9]+/g) || [];
    return runs.join('') === key ? runs.join(' ') : key;
  }

  function problem(raw, type) {
    var s = String(raw || '').trim();
    if (!s) return 'Enter the plate number.';
    if (s.length > MAX) return 'A plate has at most ' + MAX + ' characters.';
    if (/[^A-Za-z0-9\s\-._]/.test(s)) return 'Use letters and numbers only.';
    var key = normalize(s), w = wheels(type);
    if (w === null) return PATTERN.test(key) ? null : MESSAGE;
    if ((w === 'four' ? PATTERN_FOUR : PATTERN_TWO).test(key)) return null;
    var name = String(type).trim().toLowerCase();
    if (PATTERN.test(key)) {
      return w === 'four'
        ? 'This plate format belongs to a motorcycle or tricycle. A ' + name + ' plate looks like ABC 1234, ABC 123 or ZEN 88.'
        : 'This plate format belongs to a car, SUV, van or pickup. A ' + name + ' plate looks like A 123 BC, 123 ABC, AB 1234 or 1234 AB.';
    }
    return w === 'four'
      ? 'This is not a supported plate for a ' + name + '. Examples: ABC 1234, ABC 123, ZEN 88, T 1234, 12-3456, 123 4567, or a government plate from 1 to 17.'
      : 'This is not a supported plate for a ' + name + '. Examples: A 123 BC, 123 ABC, AB 1234, 1234 AB, 12-3456, or 1234-12345678901 for a temporary plate.';
  }

  /* The plate box while "still in registration" is ticked: empty is fine, anything typed must be a temporary plate. */
  function temporaryProblem(raw, type) {
    var s = String(raw || '').trim();
    if (!s) return null;
    var general = problem(s, type);
    if (general) return general;
    var key = normalize(s), w = wheels(type);
    var ok = w === 'four' ? TEMP_FOUR.test(key) : (w === 'two' ? TEMP_TWO.test(key) : (TEMP_FOUR.test(key) || TEMP_TWO.test(key)));
    if (ok) return null;
    var name = String(type || '').trim().toLowerCase();
    if (w === 'four') return 'A temporary plate for a ' + name + ' looks like T 1234 or AB 1234. Untick "still in registration" to enter a permanent plate.';
    if (w === 'two') return 'A temporary plate for a ' + name + ' looks like 1234-12345678901. Untick "still in registration" to enter a permanent plate.';
    return TEMP_MESSAGE;
  }

  /* Tickets: PK-0003. "pk3", "PK 3", "3", "pk-03" all become PK-0003. */
  function ticket(raw) {
    var s = String(raw || '').toUpperCase().replace(/[\s_.]+/g, '').replace(/^PK-?/, '');
    if (s === '' || !/^[0-9]{1,4}$/.test(s)) return null;
    if (parseInt(s, 10) < 1) return null;
    return 'PK-' + ('0000' + s).slice(-4);
  }

  function looksLikeTicket(raw) { return /^\s*PK/i.test(raw || ''); }

  /* One message box per field, found or created once. (Creating a new box on every keystroke is what stacked the duplicates.) */
  function messageBox(input) {
    if (input._plateBox) return input._plateBox;
    var id = input.getAttribute('data-plate-msg');
    var box = id ? document.getElementById(id) : null;
    if (!box) {
      var next = input.nextElementSibling;
      if (next && next.classList.contains('plate-msg')) box = next;
    }
    if (!box) {
      box = document.createElement('p');
      box.className = 'plate-msg';
      box.setAttribute('aria-live', 'polite');
      box.style.cssText = 'font-size:13px;margin:6px 0 0;color:var(--stop,#b42318);font-weight:600';
      box.hidden = true;
      input.insertAdjacentElement('afterend', box);
    }
    input._plateBox = box;
    return box;
  }

  function show(input, text) {
    var box = messageBox(input);
    input.setCustomValidity(text || '');
    input.setAttribute('aria-invalid', text ? 'true' : 'false');
    box.textContent = text || '';
    box.hidden = !text;
  }

  /* A message appears once, when the person leaves the field (or submits). Typing, and emptying the field, clears it,
     so nothing is shown while the person is still entering the value. */
  function bind(input, check, fix) {
    if (input._plateBound) return;
    input._plateBound = true;
    var touched = false;
    var run = function (final) {
      var v = input.value;
      if (fix) { var f = fix(v, final); if (f !== v) input.value = f; }
      if (!final) { show(input, null); return; }
      var p = check(input.value);
      if (input.hasAttribute('data-plate-optional') && !input.value.trim()) p = null;
      // An empty field is only reported when the form is submitted, not while moving through it.
      if (final === 'blur' && !input.value.trim()) p = null;
      show(input, p);
    };
    input.addEventListener('input', function () { run(false); });
    input.addEventListener('blur', function () { touched = true; run('blur'); });
    if (input.form) input.form.addEventListener('submit', function (e) {
      touched = true; run(true);
      if (!input.checkValidity()) { e.preventDefault(); input.focus(); }
    }, true);
    if (input.value) { touched = true; run('blur'); }
  }

  /* The plate depends on the vehicle type: the box opens when a type is chosen, the example and hint follow it,
     and a plate already typed is checked again when the type changes. */
  var HINTS = {
    none: 'Choose the vehicle type first. The plate formats depend on it.',
    four: 'For a car, SUV, van or pickup, for example ABC 1234, ABC 123 or 12-3456. Government plates 1 to 17 are accepted too.',
    two: 'For a motorcycle or tricycle, for example A 123 BC, 123 ABC, AB 1234 or 1234 AB. A temporary plate looks like 1234-12345678901.'
  };
  var EXAMPLES = { none: 'Choose the vehicle type first', four: 'ABC 1234', two: 'A 123 BC' };
  /* While "still in registration" is ticked the plate is optional and, if typed, a temporary plate. */
  var HINTS_TEMP = {
    none: 'Choose the vehicle type first. The plate formats depend on it.',
    four: 'Optional. If you have a temporary plate, enter it, for example T 1234 or AB 1234. Otherwise leave this empty and add the plate later.',
    two: 'Optional. If you have a temporary plate, enter it, for example 1234-12345678901. Otherwise leave this empty and add the plate later.'
  };
  var EXAMPLES_TEMP = { none: 'Choose the vehicle type first', four: 'T 1234 (optional)', two: '1234-12345678901 (optional)' };

  function temporary(input) { return !!(input._temp && input._temp.checked); }

  function verdict(input, type) { return temporary(input) ? temporaryProblem(input.value, type) : problem(input.value, type); }

  function followType(input, typeEl) {
    var hint = input.getAttribute('data-plate-hint') ? document.querySelector(input.getAttribute('data-plate-hint')) : null;
    var sync = function (changed) {
      var w = wheels(typeEl.value), k = w || 'none', t = temporary(input);
      input.disabled = w === null;
      input.placeholder = (t ? EXAMPLES_TEMP : EXAMPLES)[k];
      if (hint && !hint.classList.contains('err')) hint.textContent = (t ? HINTS_TEMP : HINTS)[k];
      if (changed === true) show(input, input.value.trim() && w !== null ? verdict(input, typeEl.value) : null);
    };
    typeEl.addEventListener('change', function () { sync(true); });
    input._resync = function (changed) { sync(changed === true); };
    sync(false);
    if (input.value && wheels(typeEl.value) !== null) show(input, verdict(input, typeEl.value));
  }

  function init() {
    document.querySelectorAll('input[data-plate]').forEach(function (input) {
      input.maxLength = MAX;
      input.setAttribute('autocapitalize', 'characters');
      input.removeAttribute('oninput');
      var typeEl = input.getAttribute('data-plate-type') ? document.querySelector(input.getAttribute('data-plate-type')) : null;
      var typeOf = function () { return typeEl ? typeEl.value : null; };

      /* "Still in registration" checkbox: the box stays open. Ticked, the plate is optional and must be a temporary one. */
      var tempEl = input.getAttribute('data-plate-temp') ? document.querySelector(input.getAttribute('data-plate-temp')) : null;
      if (tempEl) input._temp = tempEl;

      bind(input, function () { return verdict(input, typeOf()); }, function (v, final) {
        v = v.toUpperCase().replace(/[^A-Z0-9\s\-._]/g, '');
        // While typing, only what the chosen vehicle type's formats allow stays in the box.
        return final ? format(v) : limit(v, typeOf(), temporary(input));
      });

      if (tempEl) {
        // A vehicle that already has a temporary plate shows it while the box is ticked. Unticking clears the box for the permanent plate.
        var saved = input.getAttribute('data-plate-locked') || '';
        var apply = function (changed) {
          if (tempEl.checked) {
            input.required = false;
            if (saved) { input.value = format(saved); input.readOnly = true; show(input, null); }
          } else {
            input.required = true;
            if (saved) { input.readOnly = false; if (changed) input.value = ''; }
          }
          if (input._resync) input._resync(changed === true);
          else if (changed === true) show(input, input.value.trim() ? verdict(input, typeOf()) : null);
        };
        tempEl.addEventListener('change', function () { apply(true); });
        if (typeEl) followType(input, typeEl);
        apply(false);
        return;
      }
      if (typeEl) followType(input, typeEl);
    });

    document.querySelectorAll('input[data-ticket]').forEach(function (input) {
      input.maxLength = 8;
      bind(input, function (v) { return ticket(v) ? null : 'Enter the ticket number like PK-0003. Only PK and up to four digits.'; }, function (v, final) {
        v = v.toUpperCase();
        return final && ticket(v) ? ticket(v) : v;
      });
    });

    /* Gate and booth search: a ticket (PK...) or a plate. */
    document.querySelectorAll('input[data-plate-or-ticket]').forEach(function (input) {
      input.maxLength = MAX;
      input.setAttribute('autocapitalize', 'characters');
      bind(input, function (v) {
        if (!v.trim()) return null;
        if (looksLikeTicket(v)) return ticket(v) ? null : 'A ticket is PK and up to four digits, like PK-0003.';
        return problem(v);
      }, function (v, final) {
        v = v.toUpperCase().replace(/[^A-Z0-9\s\-._]/g, '');
        if (!final) return v;
        if (looksLikeTicket(v)) return ticket(v) || v;
        return format(v);
      });
    });
  }

  window.BaylinePlate = { normalize: normalize, format: format, problem: problem, temporaryProblem: temporaryProblem, ticket: ticket, limit: limit, wheels: wheels };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
