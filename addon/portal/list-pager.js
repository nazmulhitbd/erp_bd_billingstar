/*
 * Pager under every long list. A list paged by the server (list_page_rows in billing/sales/helpers.php sets window.LIST_PAGER)
 * gets links to ?pg= and ?pg_size=. Any other long table is paged here: its rows are all loaded and only hidden on screen,
 * so printing still gives every row. Entry grids are left alone: rows there hold fields people are typing in.
 */
(function () {
  if (window.listPagerLoaded) return;
  window.listPagerLoaded = true;
  var KEY = 'billing_star_list_size';
  var SIZES = [25, 50, 100, 250, 500];

  function saved() {
    try { return parseInt(localStorage.getItem(KEY), 10) || 25; } catch (e) { return 25; }
  }
  function remember(size) {
    try { localStorage.setItem(KEY, String(size)); } catch (e) {}
  }
  function rowsOf(table) {
    var body = table.tBodies[0];
    return body ? Array.prototype.slice.call(body.rows) : [];
  }
  /* The box to hang the pager under: the scrolling wrapper when there is one. */
  function anchor(table) {
    return table.closest('.table-scroll, .rma-grid-wrap, .ui-table-scroll, .box-body, .scroll') || table;
  }
  function button(label, title, target, go) {
    var el = document.createElement(target === null ? 'span' : 'a');
    el.textContent = label;
    el.title = title;
    if (target === null) {
      el.className = 'off';
    } else {
      el.href = go.url ? go.url(target) : '#';
      el.addEventListener('click', function (e) {
        if (go.show) { e.preventDefault(); go.show(target); }
      });
    }
    return el;
  }
  /* state: page, pages (null when unknown), size, total (null when unknown), more, sizes */
  function drawBar(bar, state, go) {
    bar.textContent = '';
    bar.className = 'list-pager';
    var pick = document.createElement('label');
    pick.textContent = 'Records ';
    var select = document.createElement('select');
    select.setAttribute('aria-label', 'Records per page');
    state.sizes.forEach(function (n) {
      var o = document.createElement('option');
      o.value = n;
      o.textContent = n === 0 ? 'All' : n;
      o.selected = n === state.size;
      select.appendChild(o);
    });
    select.addEventListener('change', function () { go.size(parseInt(select.value, 10)); });
    pick.appendChild(select);
    bar.appendChild(pick);

    var count = document.createElement('span');
    count.className = 'count';
    var first = state.total === 0 ? 0 : (state.page - 1) * state.size + 1;
    var last = state.page * state.size;
    if (state.size === 0) {
      count.textContent = 'All ' + state.total + ' records';
    } else if (state.total === null) {
      count.textContent = 'Page ' + state.page;
    } else {
      count.textContent = 'Page ' + state.page + ' of ' + state.pages + ' (' + first + '-' + Math.min(last, state.total) + ' of ' + state.total + ' records)';
    }
    bar.appendChild(count);

    var nav = document.createElement('span');
    nav.className = 'nav';
    var hasNext = state.pages === null ? state.more : state.page < state.pages;
    nav.appendChild(button('«', 'First page', state.page > 1 ? 1 : null, go));
    nav.appendChild(button('‹', 'Previous page', state.page > 1 ? state.page - 1 : null, go));
    nav.appendChild(button('›', 'Next page', hasNext ? state.page + 1 : null, go));
    if (state.pages !== null) nav.appendChild(button('»', 'Last page', hasNext ? state.pages : null, go));
    bar.appendChild(nav);
  }

  /* ---------- Paged by the server ---------- */
  function serverPager(info) {
    var main = null;
    var most = 0;
    document.querySelectorAll('table').forEach(function (table) {
      var n = rowsOf(table).length;
      if (n > most && !table.parentNode.closest('table')) { main = table; most = n; }
    });
    if (!main) return null;
    var offset = (info.page - 1) * info.size;
    var head = main.tHead && main.tHead.rows[0] && main.tHead.rows[0].cells[0];
    if (offset && head && /^(sl|sl no|nr|#)\.?$/i.test(head.textContent.trim())) {
      rowsOf(main).forEach(function (row) {
        var cell = row.cells[0];
        var n = cell && parseInt(cell.textContent, 10);
        if (n) cell.textContent = cell.textContent.replace(/\d+/, String(n + offset));
      });
    }
    var link = function (page, size) {
      var url = new URL(location.href);
      url.searchParams.set('pg', page);
      url.searchParams.set('pg_size', size);
      return url.pathname + url.search;
    };
    var bar = document.createElement('div');
    drawBar(bar, { page: info.page, pages: info.total === null ? null : Math.max(1, Math.ceil(info.total / info.size)), size: info.size, total: info.total, more: info.more, sizes: info.sizes }, {
      url: function (page) { return link(page, info.size); },
      size: function (size) { remember(size); location.href = link(1, size); }
    });
    anchor(main).insertAdjacentElement('afterend', bar);
    return main;
  }

  /* ---------- Paged here ---------- */
  function browserPager(table) {
    var rows = rowsOf(table);
    var size = saved();
    var page = 1;
    var bar = document.createElement('div');
    var show = function (target) {
      var pages = size === 0 ? 1 : Math.max(1, Math.ceil(rows.length / size));
      page = Math.min(target, pages);
      rows.forEach(function (row, i) {
        row.classList.toggle('list-off', size !== 0 && (i < (page - 1) * size || i >= page * size));
      });
      drawBar(bar, { page: page, pages: pages, size: size, total: rows.length, more: false, sizes: SIZES.concat(0) }, {
        show: show,
        size: function (next) { size = next; remember(next); show(1); }
      });
    };
    anchor(table).insertAdjacentElement('afterend', bar);
    show(1);
  }

  function pageable(table) {
    if (table.parentNode.closest('table') || table.matches('.Navigator, .totals, .stotals, .mini, .dataTable, .ui-form-table')) return false;
    var body = table.tBodies[0];
    if (!body || body.id || table.tBodies.length > 1 || body.rows.length <= 25) return false;
    return !table.querySelector('input:not([type="hidden"]), select, textarea');
  }

  function start() {
    if (document.querySelector('table.Navigator')) return; // Store pages have their own pager
    var main = window.LIST_PAGER ? serverPager(window.LIST_PAGER) : null;
    document.querySelectorAll('table').forEach(function (table) {
      if (table !== main && pageable(table)) browserPager(table);
    });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start);
  else start();
})();
