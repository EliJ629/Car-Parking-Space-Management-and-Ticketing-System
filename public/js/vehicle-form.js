/* Vehicle form: the model list follows the vehicle type, and "Others" opens a box to type in. */
(function () {
  'use strict';
  var data = document.getElementById('vehicle-catalog');
  var type = document.getElementById('vehicle_type');
  var model = document.getElementById('make_model');
  var color = document.getElementById('color');
  if (!data || !type || !model) return;

  var catalog = {};
  try { catalog = JSON.parse(data.textContent); } catch (e) { catalog = {}; }

  var modelWrap = document.getElementById('model-other-wrap');
  var modelOther = document.getElementById('make_model_other');
  var colorWrap = document.getElementById('color-other-wrap');
  var colorOther = document.getElementById('color_other');

  function option(value, label) {
    var o = document.createElement('option');
    o.value = value; o.textContent = label;
    return o;
  }

  function fill(keep) {
    var list = catalog[type.value] || [];
    model.innerHTML = '';
    model.appendChild(option('', type.value ? 'Choose a model' : 'Choose a vehicle type first'));
    list.forEach(function (m) { model.appendChild(option(m, m)); });
    model.disabled = list.length === 0;
    if (keep && list.indexOf(keep) !== -1) model.value = keep;
  }

  function toggle(select, wrap, input) {
    if (!wrap || !input) return;
    var show = select.value === 'Others';
    wrap.hidden = !show;
    input.required = show;
    if (show && document.activeElement === select) input.focus();
  }

  type.addEventListener('change', function () { fill(''); toggle(model, modelWrap, modelOther); });
  model.addEventListener('change', function () { toggle(model, modelWrap, modelOther); });
  if (color) color.addEventListener('change', function () { toggle(color, colorWrap, colorOther); });

  fill(model.getAttribute('data-selected') || '');
  toggle(model, modelWrap, modelOther);
  if (color) toggle(color, colorWrap, colorOther);
})();
