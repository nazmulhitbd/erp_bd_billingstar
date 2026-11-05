/* Role & Permission: role search and delete confirm on the list; in the editor the menu list, search, presets, live
   counts, unsaved-changes warning, and turning the ticks into the one JSON field the page posts. */
(function () {
  document.querySelectorAll('form[data-confirm]').forEach(function (f) {
    f.addEventListener('submit', function (e) { if (!confirm(f.dataset.confirm)) e.preventDefault(); });
  });
  var roleSearch = document.getElementById('ra-role-search');
  if (roleSearch) {
    roleSearch.addEventListener('input', function () {
      var q = roleSearch.value.trim().toLowerCase();
      var shown = 0;
      document.querySelectorAll('.ra-card[data-name]').forEach(function (c) {
        c.hidden = c.dataset.name.indexOf(q) < 0;
        if (!c.hidden) shown++;
      });
      document.getElementById('ra-role-none').hidden = shown > 0;
    });
  }

  var form = document.getElementById('ra-form');
  if (!form) return;
  var boxes = Array.prototype.slice.call(form.querySelectorAll('[data-f]'));
  var panels = Array.prototype.slice.call(form.querySelectorAll('.ra-panel'));
  var navItems = Array.prototype.slice.call(form.querySelectorAll('.ra-nav-item'));
  var nav = form.querySelector('.ra-nav');
  var main = document.getElementById('ra-main');
  var search = document.getElementById('ra-search');
  var grantedOnly = document.getElementById('ra-granted');
  var state = document.getElementById('ra-state');
  var discard = document.getElementById('ra-discard');
  var total = document.getElementById('ra-total');
  var activePanel = panels[0];
  var saving = false;

  function rowBoxes(key) { return boxes.filter(function (b) { return b.dataset.f === key; }); }
  function rowOf(box) {
    var tr = box.closest('tr');
    return tr.classList.contains('ra-opts') ? tr.previousElementSibling : tr;
  }
  function snapshot() {
    var rights = {};
    boxes.forEach(function (b) {
      if (b.checked) (rights[b.dataset.f] = rights[b.dataset.f] || []).push(b.dataset.a);
    });
    return rights;
  }
  var initial = JSON.stringify(snapshot());

  /* The frame grows to fit its page, so the panes need a height from the window around the frame. */
  function fit() {
    if (window.matchMedia('(max-width: 860px)').matches) { form.style.height = ''; return; }
    var room = window.innerHeight;
    try {
      if (window.parent !== window) {
        var frame = window.parent.document.getElementById('frm_main');
        room = window.parent.innerHeight - Math.max(frame.getBoundingClientRect().top, 0) - 24;
      }
    } catch (e) {}
    form.style.height = Math.max(480, room - form.getBoundingClientRect().top - 14) + 'px';
  }
  function resizeFrame() {
    try { if (window.parent.calcHeight) window.parent.calcHeight(); } catch (e) {}
  }

  /* Counts, tri-state boxes and the save bar, all from the ticks. */
  function refresh() {
    var forms = {};
    boxes.forEach(function (b) { if (b.checked) forms[b.dataset.f] = (forms[b.dataset.f] || 0) + 1; });
    var formCount = Object.keys(forms).length;
    total.textContent = formCount + ' form' + (formCount === 1 ? '' : 's') + ' · ' + boxes.filter(function (b) { return b.checked; }).length + ' rights';
    panels.forEach(function (p) {
      var keys = {};
      p.querySelectorAll('[data-f]:checked').forEach(function (b) { keys[b.dataset.f] = 1; });
      var n = Object.keys(keys).length;
      var badge = form.querySelector('[data-count="' + p.id + '"]');
      badge.textContent = n + '/' + p.querySelectorAll('.ra-row').length;
      badge.classList.toggle('on', n > 0);
    });
    form.querySelectorAll('.ra-section').forEach(function (s) {
      var rows = s.querySelectorAll('.ra-row');
      var on = 0;
      rows.forEach(function (r) {
        var key = r.querySelector('[data-f]').dataset.f;
        var has = !!forms[key];
        r.classList.toggle('on', has);
        if (has) on++;
        var more = r.querySelector('.ra-more .n');
        if (more) {
          var extra = r.nextElementSibling.querySelectorAll('[data-f]:checked').length;
          more.textContent = extra || '';
        }
      });
      var count = s.querySelector('.ra-sub-count');
      if (count) count.textContent = on + ' of ' + rows.length;
      s.querySelectorAll('[data-col]').forEach(function (head) {
        var col = s.querySelectorAll('[data-a="' + head.dataset.col + '"]');
        var ticked = s.querySelectorAll('[data-a="' + head.dataset.col + '"]:checked').length;
        head.checked = ticked === col.length;
        head.indeterminate = ticked > 0 && ticked < col.length;
      });
    });
    var dirty = JSON.stringify(snapshot()) !== initial;
    state.textContent = dirty ? 'Unsaved changes' : 'No changes';
    state.classList.toggle('dirty', dirty);
    discard.disabled = !dirty;
    form.dataset.dirty = dirty ? '1' : '';
  }

  function filter() {
    var q = search.value.trim().toLowerCase();
    var only = grantedOnly.checked;
    var filtering = q !== '' || only;
    var shown = 0;
    form.querySelectorAll('.ra-row').forEach(function (r) {
      var opts = r.nextElementSibling && r.nextElementSibling.classList.contains('ra-opts') ? r.nextElementSibling : null;
      var match = r.dataset.name.indexOf(q) >= 0 && (!only || r.classList.contains('on'));
      r.hidden = !match;
      if (opts && !match) opts.hidden = true;
      if (match) shown++;
    });
    form.querySelectorAll('.ra-section').forEach(function (s) {
      s.hidden = !s.querySelector('.ra-row:not([hidden])');
      if (filtering) s.classList.remove('folded');
    });
    panels.forEach(function (p) {
      p.hidden = filtering ? !p.querySelector('.ra-row:not([hidden])') : p !== activePanel;
    });
    nav.classList.toggle('off', filtering);
    document.getElementById('ra-none').hidden = shown > 0;
    resizeFrame();
  }

  function setAll(list, on) { list.forEach(function (b) { b.checked = on; }); }

  form.addEventListener('change', function (e) {
    var el = e.target;
    if (el.dataset.col) {
      var col = Array.prototype.slice.call(el.closest('.ra-section').querySelectorAll('[data-a="' + el.dataset.col + '"]'));
      setAll(col, el.checked);
      if (el.dataset.col === 'view' && !el.checked) {
        col.forEach(function (b) { setAll(rowBoxes(b.dataset.f), false); });
      } else if (el.checked) {
        col.forEach(function (b) { setAll(rowBoxes(b.dataset.f).filter(function (x) { return x.dataset.a === 'view'; }), true); });
      }
    } else if (el.dataset.f) {
      var row = rowBoxes(el.dataset.f);
      // A right needs the form to open; closing the form takes its rights away.
      if (el.dataset.a === 'view' && !el.checked) setAll(row, false);
      if (el.dataset.a !== 'view' && el.checked) setAll(row.filter(function (x) { return x.dataset.a === 'view'; }), true);
    } else {
      if (el === grantedOnly) filter();
      return;
    }
    refresh();
  });

  form.addEventListener('click', function (e) {
    var item = e.target.closest('.ra-nav-item');
    if (item) {
      search.value = '';
      grantedOnly.checked = false;
      activePanel = document.getElementById(item.dataset.panel);
      navItems.forEach(function (n) {
        var on = n === item;
        n.classList.toggle('active', on);
        if (on) n.setAttribute('aria-current', 'true'); else n.removeAttribute('aria-current');
      });
      filter();
      main.scrollTop = 0;
      return;
    }
    var fold = e.target.closest('.ra-fold');
    if (fold) {
      var open = fold.getAttribute('aria-expanded') === 'true';
      fold.setAttribute('aria-expanded', open ? 'false' : 'true');
      fold.closest('.ra-section').classList.toggle('folded', open);
      resizeFrame();
      return;
    }
    var more = e.target.closest('.ra-more');
    if (more) {
      var expanded = more.getAttribute('aria-expanded') === 'true';
      more.setAttribute('aria-expanded', expanded ? 'false' : 'true');
      document.getElementById(more.getAttribute('aria-controls')).hidden = expanded;
      resizeFrame();
      return;
    }
    var preset = e.target.closest('[data-preset]');
    if (preset) {
      var mode = preset.dataset.preset;
      preset.closest('.ra-panel').querySelectorAll('[data-f]').forEach(function (b) {
        b.checked = mode === 'full' || (mode === 'view' && b.dataset.a === 'view');
      });
      refresh();
    }
  });

  search.addEventListener('input', filter);

  var copy = document.getElementById('ra-copy');
  if (copy) {
    var roles = JSON.parse(form.dataset.copy || '{}');
    copy.addEventListener('change', function () {
      var from = roles[copy.value];
      if (from) {
        boxes.forEach(function (b) { b.checked = (from[b.dataset.f] || []).indexOf(b.dataset.a) >= 0; });
        refresh();
      }
      copy.value = '';
    });
  }

  discard.addEventListener('click', function () {
    var was = JSON.parse(initial);
    boxes.forEach(function (b) { b.checked = (was[b.dataset.f] || []).indexOf(b.dataset.a) >= 0; });
    refresh();
    filter();
  });

  form.addEventListener('submit', function () {
    saving = true;
    document.getElementById('rights_json').value = JSON.stringify(snapshot());
  });
  window.addEventListener('beforeunload', function (e) {
    if (!saving && form.dataset.dirty) { e.preventDefault(); e.returnValue = ''; }
  });
  window.addEventListener('resize', fit);

  // An existing role opens on the first menu it uses.
  var first = panels.filter(function (p) { return p.querySelector('[data-f]:checked'); })[0];
  if (first) {
    activePanel = first;
    navItems.forEach(function (n) {
      var on = n.dataset.panel === first.id;
      n.classList.toggle('active', on);
      if (on) n.setAttribute('aria-current', 'true'); else n.removeAttribute('aria-current');
    });
  }
  fit();
  refresh();
  filter();
})();
