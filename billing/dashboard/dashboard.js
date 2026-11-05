/* Charts for dashboard.php: a two-series line chart with crosshair tooltip, and horizontal bar lists. */
(function () {
  var D = window.DASH || {};
  var SVGNS = 'http://www.w3.org/2000/svg';
  var css = getComputedStyle(document.documentElement);
  var colours = [css.getPropertyValue('--series-1').trim(), css.getPropertyValue('--series-2').trim()];

  function compact(v) {
    var a = Math.abs(v), s;
    if (a >= 10000000) s = (a / 10000000).toFixed(a >= 100000000 ? 0 : 1) + ' Cr';
    else if (a >= 100000) s = (a / 100000).toFixed(a >= 1000000 ? 0 : 1) + ' L';
    else if (a >= 1000) s = (a / 1000).toFixed(a >= 10000 ? 0 : 1) + 'K';
    else s = String(Math.round(a));
    return (v < 0 ? '-' : '') + s.replace('.0 ', ' ').replace(/\.0K$/, 'K');
  }
  function full(v) { return '৳' + Number(v).toLocaleString('en-IN', { maximumFractionDigits: 0 }); }
  function el(name, attrs, parent) {
    var n = document.createElementNS(SVGNS, name);
    for (var k in attrs) n.setAttribute(k, attrs[k]);
    if (parent) parent.appendChild(n);
    return n;
  }
  /* Clean axis maximum and step: 1, 2 or 5 times a power of ten. */
  function niceScale(max) {
    if (max <= 0) return { max: 1, step: 0.25 };
    var raw = max / 4, pow = Math.pow(10, Math.floor(Math.log10(raw))), f = raw / pow;
    var step = (f <= 1 ? 1 : f <= 2 ? 2 : f <= 5 ? 5 : 10) * pow;
    return { max: Math.ceil(max / step) * step, step: step };
  }

  /* ---------- Line chart ---------- */
  function lineChart(box, rows, series) {
    box.innerHTML = '';
    var W = box.clientWidth, H = box.clientHeight;
    var m = { top: 12, right: 74, bottom: 26, left: 52 };
    var iw = W - m.left - m.right, ih = H - m.top - m.bottom;
    var maxV = 0;
    rows.forEach(function (r) { series.forEach(function (s) { maxV = Math.max(maxV, r[s.key]); }); });
    var sc = niceScale(maxV);
    var x = function (i) { return m.left + (rows.length === 1 ? iw / 2 : i * iw / (rows.length - 1)); };
    var y = function (v) { return m.top + ih - v / sc.max * ih; };
    var svg = el('svg', { viewBox: '0 0 ' + W + ' ' + H, 'aria-hidden': 'true' }, box);
    for (var t = 0; t <= sc.max + 1e-9; t += sc.step) {
      el('line', { x1: m.left, x2: m.left + iw, y1: y(t), y2: y(t), class: t === 0 ? 'baseline' : 'grid-line' }, svg);
      el('text', { x: m.left - 8, y: y(t) + 4, 'text-anchor': 'end', class: 'tick' }, svg).textContent = compact(t);
    }
    var every = Math.ceil(rows.length / Math.max(2, Math.floor(iw / 64)));
    rows.forEach(function (r, i) {
      if (i % every === 0 || i === rows.length - 1) el('text', { x: x(i), y: H - 6, 'text-anchor': 'middle', class: 'tick' }, svg).textContent = r.label;
    });
    var ends = [];
    series.forEach(function (s, si) {
      var d = rows.map(function (r, i) { return (i ? 'L' : 'M') + x(i).toFixed(1) + ' ' + y(r[s.key]).toFixed(1); }).join(' ');
      el('path', { d: d, fill: 'none', stroke: colours[si], 'stroke-width': 2, 'stroke-linejoin': 'round', 'stroke-linecap': 'round' }, svg);
      var last = rows[rows.length - 1];
      el('circle', { cx: x(rows.length - 1), cy: y(last[s.key]), r: 4, fill: colours[si], stroke: '#fff', 'stroke-width': 2 }, svg);
      ends.push({ y: y(last[s.key]), text: compact(last[s.key]) });
    });
    /* Direct end labels only when they do not collide; the legend and tooltip carry identity otherwise. */
    if (ends.length < 2 || Math.abs(ends[0].y - ends[1].y) >= 14) {
      ends.forEach(function (e) { el('text', { x: m.left + iw + 9, y: e.y + 4, class: 'end-label' }, svg).textContent = e.text; });
    }
    var cross = el('line', { y1: m.top, y2: m.top + ih, class: 'crosshair', visibility: 'hidden' }, svg);
    var tip = document.createElement('div');
    tip.className = 'tip';
    tip.hidden = true;
    box.appendChild(tip);
    var hit = el('rect', { x: m.left - 10, y: m.top, width: iw + 20, height: ih, fill: 'transparent', tabindex: 0 }, svg);
    var focusIndex = rows.length - 1;
    function show(i) {
      var cx = x(i);
      cross.setAttribute('x1', cx); cross.setAttribute('x2', cx); cross.setAttribute('visibility', 'visible');
      tip.textContent = '';
      var title = document.createElement('div'); title.className = 'tip-title'; title.textContent = rows[i].label; tip.appendChild(title);
      series.forEach(function (s, si) {
        var row = document.createElement('div'); row.className = 'row';
        var key = document.createElement('i'); key.className = 'key s' + (si + 1); row.appendChild(key);
        var val = document.createElement('b'); val.textContent = full(rows[i][s.key]); row.appendChild(val);
        var name = document.createElement('span'); name.textContent = s.label; row.appendChild(name);
        tip.appendChild(row);
      });
      tip.hidden = false;
      var left = cx + 14;
      if (left + tip.offsetWidth > W) left = cx - tip.offsetWidth - 14;
      tip.style.left = left + 'px';
      tip.style.top = m.top + 'px';
    }
    function hide() { cross.setAttribute('visibility', 'hidden'); tip.hidden = true; }
    hit.addEventListener('pointermove', function (e) {
      var bx = svg.getBoundingClientRect(), px = (e.clientX - bx.left) * (W / bx.width);
      focusIndex = Math.max(0, Math.min(rows.length - 1, Math.round((px - m.left) / (iw / Math.max(1, rows.length - 1)))));
      show(focusIndex);
    });
    hit.addEventListener('pointerleave', hide);
    hit.addEventListener('focus', function () { show(focusIndex); });
    hit.addEventListener('blur', hide);
    hit.addEventListener('keydown', function (e) {
      if (e.key === 'ArrowLeft' || e.key === 'ArrowRight') {
        e.preventDefault();
        focusIndex = Math.max(0, Math.min(rows.length - 1, focusIndex + (e.key === 'ArrowRight' ? 1 : -1)));
        show(focusIndex);
      }
    });
  }

  /* ---------- Horizontal bars (single series) ---------- */
  function bars(box, rows, describe) {
    if (!rows.length) return;
    box.innerHTML = '';
    var max = Math.max.apply(null, rows.map(function (r) { return r.value; })) || 1;
    var tip = document.createElement('div'); tip.className = 'tip'; tip.hidden = true;
    rows.forEach(function (r) {
      var row = document.createElement('div'); row.className = 'bar-row'; row.tabIndex = 0;
      var label = document.createElement('div'); label.className = 'bar-label'; label.textContent = r.label; label.title = r.label;
      var track = document.createElement('div'); track.className = 'bar-track';
      var bar = document.createElement('div'); bar.className = 'bar';
      bar.style.width = 'calc((100% - 80px) * ' + (r.value / max).toFixed(4) + ')';
      var val = document.createElement('div'); val.className = 'bar-value'; val.textContent = '৳' + compact(r.value);
      track.appendChild(bar); track.appendChild(val);
      row.appendChild(label); row.appendChild(track);
      function show() {
        tip.textContent = '';
        var t = document.createElement('div'); t.className = 'tip-title'; t.textContent = r.label; tip.appendChild(t);
        var line = document.createElement('div'); line.className = 'row';
        var b = document.createElement('b'); b.textContent = full(r.value); line.appendChild(b);
        var s = document.createElement('span'); s.textContent = describe(r); line.appendChild(s);
        tip.appendChild(line);
        tip.hidden = false;
        tip.style.left = Math.min(row.offsetLeft + bar.offsetLeft + bar.offsetWidth + 70, box.clientWidth - tip.offsetWidth) + 'px';
        var above = row.offsetTop - tip.offsetHeight - 4;
        tip.style.top = (above >= 0 ? above : row.offsetTop + row.offsetHeight + 4) + 'px'; /* below the row when it would leave the card */
      }
      row.addEventListener('pointerenter', show);
      row.addEventListener('focus', show);
      row.addEventListener('pointerleave', function () { tip.hidden = true; });
      row.addEventListener('blur', function () { tip.hidden = true; });
      box.appendChild(row);
    });
    box.appendChild(tip);
  }

  /* ---------- Table view of the trend ---------- */
  function trendTable(holder, rows) {
    var table = document.createElement('table'); table.className = 't';
    var head = table.createTHead().insertRow();
    ['Month', 'Sales', 'Collections', 'Collected %'].forEach(function (h, i) { var th = document.createElement('th'); th.textContent = h; if (i) th.className = 'num'; head.appendChild(th); });
    var body = table.createTBody();
    rows.forEach(function (r) {
      var tr = body.insertRow();
      [r.label, full(r.sales), full(r.collected), r.sales ? Math.round(r.collected / r.sales * 100) + '%' : '–'].forEach(function (v, i) { var td = tr.insertCell(); td.textContent = v; if (i) td.className = 'num'; });
    });
    holder.appendChild(table);
  }

  function draw() {
    var trend = document.getElementById('trend');
    if (trend && D.trend && D.trend.length) lineChart(trend, D.trend, [{ key: 'sales', label: 'Sales' }, { key: 'collected', label: 'Collections' }]);
  }
  draw();
  var aging = document.getElementById('aging');
  if (aging) bars(aging, D.aging || [], function (r) { return r.n + ' invoice' + (r.n === 1 ? '' : 's'); });
  var products = document.getElementById('products');
  if (products) bars(products, D.products || [], function (r) { return Number(r.qty).toLocaleString('en-IN') + ' sold' + (r.model ? ' \u00B7 ' + r.model : ''); });
  var toggle = document.getElementById('toggle-table');
  if (toggle) toggle.addEventListener('click', function () {
    var holder = document.getElementById('trend-table');
    if (!holder.firstChild) trendTable(holder, D.trend);
    holder.hidden = !holder.hidden;
    toggle.textContent = holder.hidden ? 'Show table' : 'Hide table';
    toggle.setAttribute('aria-expanded', String(!holder.hidden));
    if (window.parent && window.parent.calcHeight) try { window.parent.calcHeight(); } catch (e) {}
  });
  var timer;
  window.addEventListener('resize', function () { clearTimeout(timer); timer = setTimeout(draw, 150); });
})();
