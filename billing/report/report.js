/* Register pages: filter form, report preview in a frame, print, and opening ticked reports in new tabs. */
(function () {
  var form = document.getElementById('rep-filters');
  if (!form) return;
  var pane = document.getElementById('preview');
  var frame = document.getElementById('preview-frame');
  var statusEl = document.getElementById('preview-status');
  var printWhenLoaded = false;
  var startedAt = 0;

  function reportUrl(key) {
    var p = new URLSearchParams(new FormData(form));
    p.set('report', key);
    return location.pathname + '?' + p.toString();
  }

  function printFrame() {
    try { frame.contentWindow.focus(); frame.contentWindow.print(); } catch (e) { window.open(frame.src + '&auto=1', '_blank'); }
  }

  function showReport(key, caption, print) {
    var url = reportUrl(key);
    pane.hidden = false;
    document.getElementById('preview-title').textContent = caption;
    document.getElementById('preview-tab').href = url;
    statusEl.textContent = 'Loading...';
    printWhenLoaded = !!print;
    startedAt = performance.now();
    frame.src = url;
    pane.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }

  frame.addEventListener('load', function () {
    if (!frame.src || frame.src === 'about:blank') return;
    statusEl.textContent = 'Loaded in ' + ((performance.now() - startedAt) / 1000).toFixed(1) + ' s';
    if (printWhenLoaded) { printWhenLoaded = false; printFrame(); }
  });
  document.getElementById('preview-print').addEventListener('click', printFrame);
  document.getElementById('preview-close').addEventListener('click', function () { pane.hidden = true; frame.src = 'about:blank'; });

  document.querySelectorAll('[data-report]').forEach(function (b) {
    b.addEventListener('click', function () { showReport(b.dataset.report, b.dataset.caption, b.dataset.print); });
  });
  document.getElementById('open-ticked').addEventListener('click', function () {
    var ticked = document.querySelectorAll('[data-tick]:checked');
    if (!ticked.length) { alert('Tick one or more reports.'); return; }
    ticked.forEach(function (c) { window.open(reportUrl(c.dataset.tick), '_blank'); });
  });

  // Product scope radios show only the fields that scope uses.
  function applyScope() {
    var checked = form.querySelector('input[name=scope]:checked');
    var scope = checked ? checked.value : 'all';
    form.querySelectorAll('[data-scope]').forEach(function (el) {
      var on = el.dataset.scope.split(' ').indexOf(scope) >= 0;
      el.hidden = !on;
      el.querySelectorAll('input').forEach(function (i) { i.disabled = !on; });
    });
  }
  form.querySelectorAll('input[name=scope]').forEach(function (r) { r.addEventListener('change', applyScope); });
  applyScope();

  var allDate = document.getElementById('all-date');
  if (allDate) {
    allDate.addEventListener('change', function () {
      form.querySelectorAll('.rep-date').forEach(function (i) { i.disabled = allDate.checked; });
    });
  }
})();
