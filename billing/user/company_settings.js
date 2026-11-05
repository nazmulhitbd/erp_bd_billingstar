/* Company & Login Settings: tabs, delete confirm, unsaved-changes warning and the live sign-in preview. */
(function () {
  var tabs = Array.prototype.slice.call(document.querySelectorAll('[data-tab]'));
  var preview = document.getElementById('cs-preview');
  var saveBar = document.getElementById('cs-save');
  var form = document.getElementById('cs-form');
  var state = document.getElementById('cs-state');
  var saving = false;

  function resizeFrame() {
    try { if (window.parent.calcHeight) window.parent.calcHeight(); } catch (e) {}
  }
  function show(name) {
    tabs.forEach(function (t) {
      var on = t.dataset.tab === name;
      t.setAttribute('aria-selected', on ? 'true' : 'false');
      document.getElementById('panel-' + t.dataset.tab).hidden = !on;
    });
    saveBar.hidden = name === 'notices';
    preview.hidden = name !== 'company' && name !== 'login';
    history.replaceState(null, '', '?tab=' + name);
    resizeFrame();
  }
  tabs.forEach(function (t) { t.addEventListener('click', function () { show(t.dataset.tab); }); });

  document.querySelectorAll('form[data-confirm]').forEach(function (f) {
    f.addEventListener('submit', function (e) { if (!confirm(f.dataset.confirm)) e.preventDefault(); });
  });

  /* Same rule as brand_ink() in addon/inc/brand.php. */
  function ink(hex) {
    var c = [1, 3, 5].map(function (i) {
      var v = parseInt(hex.substr(i, 2), 16) / 255;
      return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4);
    });
    var l = 0.2126 * c[0] + 0.7152 * c[1] + 0.0722 * c[2];
    return 1.05 / (l + 0.05) >= 4.5 ? '#ffffff' : '#0f172a';
  }
  function value(id) { return document.getElementById(id).value.trim(); }
  function put(key, text) {
    preview.querySelector('[data-show="' + key + '"]').textContent = text;
    var row = preview.querySelector('[data-row="' + key + '"]');
    if (row) row.hidden = text === '';
  }
  function draw() {
    put('brand', value('brand_name'));
    put('slogan', value('slogan'));
    put('headline', value('login_title') || value('company_name') || value('brand_name'));
    put('welcome', value('login_welcome'));
    ['address', 'phone', 'mobile', 'email', 'hours'].forEach(function (k) { put(k, value('company_' + k)); });
    put('website', value('company_website').replace(/^https?:\/\//i, ''));
    var color = value('login_color');
    var mini = document.getElementById('cs-mini');
    mini.style.background = color;
    mini.style.color = ink(color);
  }
  function snapshot() {
    return Array.prototype.map.call(form.elements, function (el) {
      return el.type === 'checkbox' ? el.checked : el.type === 'file' ? el.value : el.name === 'csrf_token' ? '' : el.value;
    }).join('\u0001');
  }
  var initial = snapshot();
  function refresh() {
    var dirty = snapshot() !== initial;
    state.textContent = dirty ? 'Unsaved changes' : 'No changes';
    state.classList.toggle('dirty', dirty);
    form.dataset.dirty = dirty ? '1' : '';
    draw();
  }
  form.addEventListener('input', refresh);
  form.addEventListener('change', refresh);
  form.addEventListener('submit', function () { saving = true; });
  window.addEventListener('beforeunload', function (e) {
    if (!saving && form.dataset.dirty) { e.preventDefault(); e.returnValue = ''; }
  });
  draw();
})();
