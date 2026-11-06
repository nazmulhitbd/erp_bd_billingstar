/* Charts for dashboard.php, drawn as SVG without a library: the sales trend (this period against the previous one, or
   12 months of sales and collections), two donuts with legends, and horizontal bar lists. Redrawn when the frame resizes. */
(function () {
  var D = window.DASH || {};
  var SVGNS = 'http://www.w3.org/2000/svg';
  var css = getComputedStyle(document.documentElement);
  var C1 = css.getPropertyValue('--series-1').trim() || '#2563eb';
  var C2 = css.getPropertyValue('--series-2').trim() || '#93b4f5';
  var MUTED = '#64748b', GRID = '#eef1f6', INK = '#334155';

  var MONEY = window.BS_MONEY || { symbol: '৳', lakh: true }; // the country edition (dashboard.php)
  var SYM = MONEY.symbol;
  function compact(v) {
    var a = Math.abs(v), s;
    if (MONEY.lakh && a >= 10000000) s = (a / 10000000).toFixed(a >= 100000000 ? 0 : 1) + ' Cr';
    else if (MONEY.lakh && a >= 100000) s = (a / 100000).toFixed(a >= 1000000 ? 0 : 1) + ' L';
    else if (!MONEY.lakh && a >= 1000000000) s = (a / 1000000000).toFixed(1) + ' B';
    else if (!MONEY.lakh && a >= 1000000) s = (a / 1000000).toFixed(a >= 10000000 ? 0 : 1) + ' M';
    else if (a >= 1000) s = (a / 1000).toFixed(a >= 10000 ? 0 : 1) + 'K';
    else s = String(Math.round(a));
    return (v < 0 ? '-' : '') + s.replace('.0 ', ' ').replace(/\.0K$/, 'K');
  }
  function full(v) { return SYM + ' ' + Number(v).toLocaleString(MONEY.lakh ? 'en-IN' : 'en-US', { maximumFractionDigits: 0 }); }
  function el(name, attrs, parent) {
    var n = document.createElementNS(SVGNS, name);
    for (var k in attrs) n.setAttribute(k, attrs[k]);
    if (parent) parent.appendChild(n);
    return n;
  }
  function text(parent, x, y, str, attrs) {
    var t = el('text', Object.assign({ x: x, y: y, 'font-size': 11, fill: MUTED }, attrs || {}), parent);
    t.textContent = str;
    return t;
  }
  /* Clean axis maximum and step: 1, 2 or 5 times a power of ten. */
  function niceScale(max, ticks) {
    ticks = ticks || 5;
    if (max <= 0) return { max: 1, step: 0.2 };
    var raw = max / ticks, pow = Math.pow(10, Math.floor(Math.log10(raw))), f = raw / pow;
    var step = (f <= 1 ? 1 : f <= 2 ? 2 : f <= 5 ? 5 : 10) * pow;
    return { max: Math.ceil(max / step) * step, step: step };
  }
  function clear(node) { while (node.firstChild) node.removeChild(node.firstChild); }

  /* ---------- Line chart ---------- */
  var trendBox = document.getElementById('trend');
  var trendMode = document.getElementById('trend-mode');
  var legendBox = document.getElementById('trend-legend');
  function trendData() {
    if (trendMode && trendMode.value === 'daily' && D.daily && D.daily.length) {
      return { rows: D.daily, a: 'sales', b: 'prev', aName: 'This period', bName: 'Previous period' };
    }
    return { rows: D.trend || [], a: 'sales', b: 'collected', aName: 'Sales', bName: 'Collections' };
  }
  function drawTrend() {
    if (!trendBox) return;
    var d = trendData(), rows = d.rows;
    clear(trendBox);
    legendBox.innerHTML = '<span><i></i>' + d.aName + '</span><span><i class="dashed"></i>' + d.bName + '</span>';
    if (!rows.length) { trendBox.innerHTML = '<p class="empty">No sales yet.</p>'; return; }
    var W = trendBox.clientWidth || 600, H = trendBox.clientHeight || 250;
    var pad = { l: 52, r: 14, t: 10, b: 26 };
    var max = 0;
    rows.forEach(function (r) { max = Math.max(max, r[d.a], r[d.b]); });
    var sc = niceScale(max);
    var iw = W - pad.l - pad.r, ih = H - pad.t - pad.b;
    var x = function (i) { return pad.l + (rows.length === 1 ? iw / 2 : i * iw / (rows.length - 1)); };
    var y = function (v) { return pad.t + ih - v / sc.max * ih; };
    var svg = el('svg', { viewBox: '0 0 ' + W + ' ' + H, preserveAspectRatio: 'none' }, trendBox);
    var defs = el('defs', {}, svg);
    var grad = el('linearGradient', { id: 'area', x1: 0, y1: 0, x2: 0, y2: 1 }, defs);
    el('stop', { offset: '0%', 'stop-color': C1, 'stop-opacity': 0.18 }, grad);
    el('stop', { offset: '100%', 'stop-color': C1, 'stop-opacity': 0.02 }, grad);
    for (var v = 0; v <= sc.max + 1e-9; v += sc.step) {
      el('line', { x1: pad.l, x2: W - pad.r, y1: y(v), y2: y(v), stroke: GRID }, svg);
      text(svg, pad.l - 8, y(v) + 4, SYM + compact(v), { 'text-anchor': 'end' });
    }
    var every = Math.max(1, Math.ceil(rows.length / Math.max(2, Math.floor(iw / 70))));
    rows.forEach(function (r, i) {
      if (i % every === 0 || i === rows.length - 1) text(svg, x(i), H - 6, r.label, { 'text-anchor': i === 0 ? 'start' : i === rows.length - 1 ? 'end' : 'middle' });
    });
    var path = function (key) { return rows.map(function (r, i) { return (i ? 'L' : 'M') + x(i).toFixed(1) + ' ' + y(r[key]).toFixed(1); }).join(' '); };
    el('path', { d: path(d.a) + ' L' + x(rows.length - 1) + ' ' + y(0) + ' L' + x(0) + ' ' + y(0) + ' Z', fill: 'url(#area)' }, svg);
    el('path', { d: path(d.b), fill: 'none', stroke: C2, 'stroke-width': 2, 'stroke-dasharray': '6 5', 'stroke-linejoin': 'round' }, svg);
    el('path', { d: path(d.a), fill: 'none', stroke: C1, 'stroke-width': 2.5, 'stroke-linejoin': 'round' }, svg);
    var dots = rows.length <= 31;
    rows.forEach(function (r, i) {
      if (dots) {
        el('circle', { cx: x(i), cy: y(r[d.b]), r: 2.5, fill: C2 }, svg);
        el('circle', { cx: x(i), cy: y(r[d.a]), r: 3.5, fill: C1, stroke: '#fff', 'stroke-width': 1.5 }, svg);
      }
    });
    /* Crosshair and tooltip for the nearest point. */
    var hair = el('line', { y1: pad.t, y2: pad.t + ih, stroke: '#cbd5e1', 'stroke-dasharray': '3 3', visibility: 'hidden' }, svg);
    var tip = document.createElement('div');
    tip.className = 'tip';
    tip.hidden = true;
    trendBox.appendChild(tip);
    var hit = el('rect', { x: pad.l, y: pad.t, width: iw, height: ih, fill: 'transparent' }, svg);
    function move(e) {
      var box = svg.getBoundingClientRect();
      var px = (e.clientX - box.left) * W / box.width;
      var i = Math.max(0, Math.min(rows.length - 1, Math.round((px - pad.l) / (iw / Math.max(1, rows.length - 1)))));
      var r = rows[i];
      hair.setAttribute('x1', x(i)); hair.setAttribute('x2', x(i)); hair.setAttribute('visibility', 'visible');
      tip.innerHTML = '<b>' + r.label + '</b>' + d.aName + ': ' + full(r[d.a]) + '<br>' + d.bName + ': ' + full(r[d.b]);
      tip.style.left = (x(i) * box.width / W) + 'px';
      tip.style.top = (y(Math.max(r[d.a], r[d.b])) * box.height / H) + 'px';
      tip.hidden = false;
    }
    hit.addEventListener('mousemove', move);
    hit.addEventListener('mouseleave', function () { tip.hidden = true; hair.setAttribute('visibility', 'hidden'); });
  }
  if (trendMode) trendMode.addEventListener('change', drawTrend);

  /* ---------- Donuts ---------- */
  function drawDonut(id, rows, colours, caption) {
    var box = document.getElementById(id), legend = document.getElementById(id + '-legend');
    if (!box) return;
    clear(box);
    if (legend) legend.innerHTML = '';
    var total = rows.reduce(function (s, r) { return s + Math.max(0, r.value); }, 0);
    if (!rows.length || total <= 0) { if (legend) legend.innerHTML = '<li class="empty">Nothing to show.</li>'; return; }
    var S = 200, R = 80, w = 30, c = S / 2, start = -Math.PI / 2;
    var svg = el('svg', { viewBox: '0 0 ' + S + ' ' + S }, box);
    el('circle', { cx: c, cy: c, r: R, fill: 'none', stroke: GRID, 'stroke-width': w }, svg);
    rows.forEach(function (r, i) {
      var frac = Math.max(0, r.value) / total;
      if (frac <= 0) return;
      var colour = colours[i % colours.length];
      if (frac >= 0.9999) {
        el('circle', { cx: c, cy: c, r: R, fill: 'none', stroke: colour, 'stroke-width': w }, svg);
      } else {
        var end = start + frac * Math.PI * 2, gap = rows.length > 1 ? 0.012 : 0;
        var a0 = start + gap, a1 = end - gap;
        var p = el('path', {
          d: 'M' + (c + R * Math.cos(a0)) + ' ' + (c + R * Math.sin(a0)) + ' A' + R + ' ' + R + ' 0 ' + (a1 - a0 > Math.PI ? 1 : 0) + ' 1 ' + (c + R * Math.cos(a1)) + ' ' + (c + R * Math.sin(a1)),
          fill: 'none', stroke: colour, 'stroke-width': w
        }, svg);
        el('title', {}, p).textContent = r.label + ': ' + full(r.value);
        start = end;
      }
      if (legend) {
        var li = document.createElement('li');
        li.innerHTML = '<span class="dot"></span><span class="name"></span><span class="pct"></span><span class="amt"></span>';
        li.querySelector('.dot').style.background = colour;
        li.querySelector('.name').textContent = r.label + (r.n !== undefined ? ' (' + r.n + ')' : '');
        li.querySelector('.pct').textContent = Math.round(frac * 100) + '%';
        li.querySelector('.amt').textContent = full(r.value);
        legend.appendChild(li);
      }
    });
    text(svg, c, c - 6, caption, { 'text-anchor': 'middle', 'font-size': 13 });
    text(svg, c, c + 16, SYM + compact(total), { 'text-anchor': 'middle', 'font-size': 17, 'font-weight': 600, fill: '#0f172a' });
  }

  /* ---------- Horizontal bars ---------- */
  function drawBars(id, rows, colour) {
    var box = document.getElementById(id);
    if (!box) return;
    clear(box);
    if (!rows || !rows.length) return;
    var W = box.clientWidth || 360, rowH = 32, pad = { t: 4, b: 24 };
    var labelW = Math.min(150, Math.max(90, W * 0.36)), valueW = 70;
    var H = pad.t + rows.length * rowH + pad.b;
    var max = Math.max.apply(null, rows.map(function (r) { return r.value; }));
    var sc = niceScale(max, 4);
    var iw = W - labelW - valueW - 10;
    var x = function (v) { return labelW + 10 + v / sc.max * iw; };
    var svg = el('svg', { viewBox: '0 0 ' + W + ' ' + H, height: H }, box);
    box.style.height = H + 'px';
    for (var v = 0; v <= sc.max + 1e-9; v += sc.step) {
      el('line', { x1: x(v), x2: x(v), y1: pad.t, y2: H - pad.b, stroke: GRID }, svg);
      text(svg, x(v), H - 6, SYM + compact(v), { 'text-anchor': 'middle' });
    }
    rows.forEach(function (r, i) {
      var cy = pad.t + i * rowH + rowH / 2;
      var label = r.label.length > 22 ? r.label.slice(0, 21) + '…' : r.label;
      var t = text(svg, labelW, cy + 4, label, { 'text-anchor': 'end', fill: INK, 'font-size': 12 });
      el('title', {}, t).textContent = r.label;
      var bw = Math.max(2, x(r.value) - x(0));
      var bar = el('rect', { x: x(0), y: cy - 7, width: bw, height: 14, rx: 3, fill: colour }, svg);
      el('title', {}, bar).textContent = r.label + ': ' + full(r.value);
      text(svg, x(0) + bw + 6, cy + 4, SYM + compact(r.value), { fill: INK, 'font-size': 11.5, 'font-weight': 600 });
    });
  }

  function drawAll() {
    drawTrend();
    drawDonut('channels', D.channels || [], ['#2563eb', '#60a5fa', '#cbd5e1', '#14b8a6', '#f59e0b'], 'Total');
    drawDonut('aging', D.aging || [], ['#2563eb', '#f59e0b', '#fb923c', '#ef4444'], 'Total due');
    drawBars('sellers', D.sellers, C1);
    drawBars('groups', D.groups, C1);
    try { if (window.parent && window.parent.calcHeight) window.parent.calcHeight(); } catch (e) {}
  }
  drawAll();
  var timer;
  window.addEventListener('resize', function () { clearTimeout(timer); timer = setTimeout(drawAll, 150); });

  /* ---------- Period picker: a fixed period applies at once, Custom shows the dates ---------- */
  var form = document.getElementById('period-form');
  if (form) {
    var period = document.getElementById('period');
    var range = form.querySelector('.range'), rangeText = form.querySelector('.range-text');
    period.addEventListener('change', function () {
      if (period.value === 'custom') { range.hidden = false; rangeText.hidden = true; return; }
      range.querySelectorAll('input').forEach(function (i) { i.disabled = true; });
      form.submit();
    });
  }
})();
