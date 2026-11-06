/* Behaviour for both admin layouts: topmain.php (top menu: category panels that open on click, hover and keyboard) and
   treemain.php (body.shell-side: a sidebar whose categories open under themselves, collapse to icons and slide in on
   phones). Shared: user menu, breadcrumb, current-page highlight, reopening the last page after a reload, the page finder. */
(function () {
  var nav = document.getElementById('navigation');
  var crumb = document.getElementById('breadcrumb');
  var frame = document.getElementById('frm_main');
  var navToggle = document.querySelector('.nav-toggle');
  var userMenu = document.querySelector('.user-menu');
  var desktop = window.matchMedia('(min-width: 992px) and (hover: hover)');
  var wide = window.matchMedia('(min-width: 992px)');
  var side = document.body.classList.contains('shell-side');
  var backdrop = document.querySelector('.side-backdrop');
  var LAST_PAGE = 'vsc_last_page';

  function store(key, value) { try { sessionStorage.setItem(key, value); } catch (e) {} }
  function read(key) { try { return sessionStorage.getItem(key); } catch (e) { return null; } }

  /* ---------- Sidebar: collapse to icons (kept per browser), drawer on phones ---------- */
  var root = document.documentElement;
  function setCollapsed(on) {
    root.classList.toggle('nav-collapsed', on);
    try { localStorage.setItem('shell_side_collapsed', on ? '1' : '0'); } catch (e) {}
    setTimeout(function () { if (typeof calcHeight === 'function') calcHeight(); }, 220);
  }
  function setDrawer(open) {
    nav.classList.toggle('open', open);
    if (backdrop) backdrop.hidden = !open;
    navToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
  }
  if (side) {
    var collapseBtn = document.querySelector('.side-collapse');
    if (collapseBtn) collapseBtn.addEventListener('click', function () { setCollapsed(!root.classList.contains('nav-collapsed')); });
    if (backdrop) backdrop.addEventListener('click', function () { setDrawer(false); });
  }

  /* ---------- Category panels ---------- */
  function placePanel(cat) {
    var panel = cat.querySelector('.nav-panel');
    if (side) return; // the sidebar opens a category in place
    if (!panel || !desktop.matches) { if (panel) panel.style.left = ''; return; }
    var navBox = nav.getBoundingClientRect();
    var btnBox = cat.querySelector('.nav-top').getBoundingClientRect();
    panel.style.left = '0px';
    var width = panel.offsetWidth;
    var left = Math.min(btnBox.left - navBox.left, navBox.width - width - 12);
    panel.style.left = Math.max(12, left) + 'px';
  }
  function setOpen(cat, open) {
    cat.classList.toggle('open', open);
    var btn = cat.querySelector('.nav-top');
    if (btn && btn.tagName === 'BUTTON') btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    if (open) placePanel(cat);
  }
  function closeAll(except) {
    nav.querySelectorAll('.nav-cat.open').forEach(function (c) { if (c !== except) setOpen(c, false); });
  }

  nav.querySelectorAll('.nav-cat.has-panel').forEach(function (cat) {
    var timer;
    if (side) return; // no hover panels in the sidebar
    cat.addEventListener('mouseenter', function () {
      if (!desktop.matches) return;
      clearTimeout(timer);
      timer = setTimeout(function () { closeAll(cat); setOpen(cat, true); }, 90);
    });
    cat.addEventListener('mouseleave', function () {
      if (!desktop.matches) return;
      clearTimeout(timer);
      timer = setTimeout(function () { setOpen(cat, false); }, 180);
    });
  });

  nav.addEventListener('click', function (e) {
    var btn = e.target.closest('button.nav-top');
    if (btn) {
      var cat = btn.parentElement;
      var opening = !cat.classList.contains('open');
      if (side && root.classList.contains('nav-collapsed') && wide.matches) { setCollapsed(false); opening = true; } // icons only: widen first
      closeAll(cat);
      setOpen(cat, opening);
      if (opening && (side || !desktop.matches)) cat.scrollIntoView({ block: 'nearest' });
      return;
    }
    var link = e.target.closest('a[target="frm_main"]');
    if (link) {
      opened(link);
    }
  });

  /* Keyboard: arrows move between categories and inside an open panel. */
  nav.addEventListener('keydown', function (e) {
    var tops = Array.prototype.slice.call(nav.querySelectorAll('.nav-top'));
    var i = tops.indexOf(document.activeElement);
    if (side) return; // the sidebar is a plain list: Tab moves through it
    if (i !== -1 && (e.key === 'ArrowRight' || e.key === 'ArrowLeft')) {
      e.preventDefault();
      var next = tops[(i + (e.key === 'ArrowRight' ? 1 : -1) + tops.length) % tops.length];
      var wasOpen = nav.querySelector('.nav-cat.open');
      closeAll();
      next.focus();
      if (wasOpen && next.parentElement.classList.contains('has-panel')) setOpen(next.parentElement, true);
      return;
    }
    if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
      var cat = document.activeElement.closest('.nav-cat.has-panel');
      if (!cat) return;
      e.preventDefault();
      if (!cat.classList.contains('open')) setOpen(cat, true);
      var links = Array.prototype.slice.call(cat.querySelectorAll('.nav-panel a'));
      var j = links.indexOf(document.activeElement);
      var to = j === -1 ? (e.key === 'ArrowDown' ? 0 : links.length - 1) : j + (e.key === 'ArrowDown' ? 1 : -1);
      if (to < 0) { cat.querySelector('.nav-top').focus(); return; }
      if (links[Math.min(to, links.length - 1)]) links[Math.min(to, links.length - 1)].focus();
    }
  });

  document.addEventListener('click', function (e) {
    if (!side && !nav.contains(e.target)) closeAll();
    if (userMenu && !userMenu.contains(e.target)) userMenu.classList.remove('open');
  });
  /* Clicks inside the page frame do not reach this document; the window losing focus means one happened. */
  window.addEventListener('blur', function () { if (!side) closeAll(); if (userMenu) userMenu.classList.remove('open'); });
  window.addEventListener('resize', function () { var c = nav.querySelector('.nav-cat.open'); if (c) placePanel(c); });

  navToggle.addEventListener('click', function () {
    if (side && wide.matches) { setCollapsed(!root.classList.contains('nav-collapsed')); return; }
    if (side) { setDrawer(!nav.classList.contains('open')); return; }
    var open = nav.classList.toggle('open');
    navToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
  });

  if (userMenu) {
    var chip = userMenu.querySelector('.user-chip');
    chip.addEventListener('click', function () {
      var open = userMenu.classList.toggle('open');
      chip.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
    userMenu.addEventListener('click', function (e) {
      var link = e.target.closest('a[target="frm_main"]');
      if (link) { userMenu.classList.remove('open'); opened(link); }
    });
  }

  /* ---------- Current page, breadcrumb, last page ---------- */
  /* Menu links are tokens, so the frame's real path cannot be matched to them: remember the link that opened the page. */
  var opening = null;
  var currentLink = null;
  var currentPath = '';
  function linkFor(href) {
    var found = null;
    nav.querySelectorAll('a[target="frm_main"]:not(.brand)').forEach(function (a) { if (!found && a.getAttribute('href') === href) found = a; }); // the sidebar's logo is not a menu entry
    return found;
  }
  function markCurrent(link) {
    nav.querySelectorAll('.current').forEach(function (el) { el.classList.remove('current'); });
    if (!link) return;
    if (link.closest('.nav-panel')) link.classList.add('current');
    var cat = link.closest('.nav-cat');
    if (cat) cat.classList.add('current');
    if (side && cat && cat.classList.contains('has-panel') && !cat.classList.contains('open')) { closeAll(cat); setOpen(cat, true); } // show where the page lives
    setCrumb(link.getAttribute('data-path') || link.textContent.trim());
  }
  /* The one page title / path above the frame: "Category › Group" muted, page name bold. */
  function setCrumb(path) {
    var parts = String(path).split(' › ');
    crumb.textContent = '';
    if (parts.length > 1) {
      var where = document.createElement('span');
      where.className = 'crumb-path';
      where.textContent = parts.slice(0, -1).join(' › ') + ' ›';
      crumb.appendChild(where);
    }
    var name = document.createElement('b');
    name.className = 'crumb-page';
    name.textContent = parts[parts.length - 1];
    crumb.appendChild(name);
  }
  function opened(link) {
    var href = link.getAttribute('href');
    opening = link;
    var inMenu = linkFor(href);
    markCurrent(inMenu || link);
    if (!inMenu) setCrumb(link.getAttribute('data-path') || link.textContent.trim());
    store(LAST_PAGE, href);
    if (document.activeElement && document.activeElement.blur) document.activeElement.blur(); // CSS :focus-within would keep the panel open
    if (side) {
      setDrawer(false);
    } else {
      closeAll();
      nav.classList.remove('open');
      navToggle.setAttribute('aria-expanded', 'false');
    }
  }
  document.querySelector('.brand').addEventListener('click', function () { opened(this); });

  /* ---------- Notifications bell. The feed never counts as activity, so it cannot hold an idle session open. ---------- */
  var bell = document.getElementById('bell');
  var refreshBell = function () {};
  if (bell) {
    var bellCount = bell.querySelector('.bell-count');
    var bellList = bell.querySelector('.bell-list');
    var bellBtn = bell.querySelector('.bell-btn');
    var bellTimer = null;
    var drawBell = function (data) {
      bellCount.hidden = !data.unread;
      bellCount.textContent = data.unread > 99 ? '99+' : String(data.unread);
      bellBtn.setAttribute('aria-label', 'Notifications' + (data.unread ? ', ' + data.unread + ' unread' : ''));
      bellList.textContent = '';
      if (!data.items.length) {
        var none = document.createElement('div');
        none.className = 'bell-empty';
        none.textContent = data.installed === false ? 'Notifications are not installed: import billing/user/schema.sql.' : 'No notifications';
        bellList.appendChild(none);
      }
      data.items.forEach(function (item) {
        var row = document.createElement('a');
        row.href = 'user/notifications.php?open=' + item.id;
        row.target = 'frm_main';
        row.className = item.unread ? 'new' : '';
        var title = document.createElement('b');
        title.textContent = item.title;
        var note = document.createElement('span');
        note.textContent = (item.body ? item.body + ' · ' : '') + item.age;
        row.appendChild(title);
        row.appendChild(note);
        bellList.appendChild(row);
      });
    };
    refreshBell = function () {
      fetch(bell.getAttribute('data-feed'), { credentials: 'same-origin' }).then(function (r) {
        if (r.status === 401) { clearInterval(bellTimer); return null; }
        return r.ok ? r.json() : null;
      }).then(function (data) {
        if (data && data.locked) { location.reload(); return; } // the shell then shows the lock screen
        if (data) drawBell(data);
      }).catch(function () {});
    };
    bellBtn.addEventListener('click', function () {
      bellBtn.setAttribute('aria-expanded', bell.classList.toggle('open') ? 'true' : 'false');
    });
    document.addEventListener('click', function (e) {
      if (!bell.contains(e.target) || e.target.closest('a')) {
        bell.classList.remove('open');
        bellBtn.setAttribute('aria-expanded', 'false');
      }
    });
    bellTimer = setInterval(refreshBell, 60000);
    refreshBell();
  }

  /* ---------- Common page design inside the frame ---------- */
  var cssMeta = document.querySelector('meta[name="ui-common-css"]');
  var COMMON_CSS = new URL(cssMeta ? cssMeta.content : 'ui-common.css', location.href).href;
  /* The page name: its menu entry, else its own heading or fieldset legend, else its <title>. */
  function pageTitle(doc, heading, here) {
    var link = opening || (currentLink && here === currentPath ? currentLink : null);
    if (link) return link.textContent.trim();
    return heading ? heading.textContent.trim() : (doc.title || '').trim();
  }
  /* Put the whole page in the one block (ui-common.css). RMA pages already draw it; scripts stay where they ran. */
  function wrapPage(doc) {
    var body = doc.body;
    if (!body || body.tagName !== 'BODY' || !body.children.length || body.classList.contains('rma-print') || doc.querySelector('.rma-box')) return;
    var page = doc.createElement('div');
    var block = doc.createElement('fieldset');
    var legend = doc.createElement('legend');
    var inner = doc.createElement('div');
    page.className = 'ui-page';
    block.className = 'ui-block';
    inner.className = 'ui-block-inner';
    Array.prototype.forEach.call(Array.prototype.slice.call(body.childNodes), function (n) {
      if (!/^(SCRIPT|STYLE|LINK|NOSCRIPT)$/.test(n.nodeName)) inner.appendChild(n);
    });
    block.appendChild(legend);
    block.appendChild(inner);
    page.appendChild(block);
    body.insertBefore(page, body.firstChild);
    var boxes = inner.querySelectorAll('fieldset.box');
    var heading = inner.querySelector('.titlebar h1, h1, h2');
    if (heading && heading.closest('table, form, .si-hero')) heading = null; // a document's own header bar keeps its title
    if (boxes.length === 1) {
      boxes[0].classList.add('ui-flat');
      heading = heading || boxes[0].querySelector('legend');
    }
    var here = '';
    try { here = doc.defaultView.location.pathname; } catch (e) {}
    var title = pageTitle(doc, heading, here);
    legend.textContent = title;
    legend.hidden = !title;
    var plain = function (text) { return text.toLowerCase().replace(/[^a-z0-9]/g, ''); };
    if (heading && plain(heading.textContent) === plain(title)) heading.style.display = 'none'; // the block's title says it
  }
  /* Narrow screens: a data table scrolls inside its own box, a form laid out with a table stacks (ui-common.css). */
  function fitTables(doc) {
    doc.querySelectorAll('table').forEach(function (table) {
      if (table.parentNode.closest('table, .table-scroll, .table-responsive, .rma-grid-wrap, .box-body, .ui-table-scroll') || table.classList.contains('Navigator')) return;
      if (table.querySelector('th') || table.classList.contains('table')) {
        var box = doc.createElement('div');
        box.className = 'ui-table-scroll';
        table.parentNode.insertBefore(box, table);
        box.appendChild(table);
      } else if (table.querySelector('input, select, textarea')) {
        table.classList.add('ui-form-table');
      }
    });
  }
  /* ---------- Record tables and table forms: one look on every page (ui-common.css, "Record tables, polished") ---------- */
  var STATUS = {
    good: /^(active|approved|posted|paid|completed?|delivered|converted|received|verified|cleared|settled|enabled|in stock|done|billed|open for sale)$/i,
    wait: /^(pending|draft|open|partial(ly paid)?|waiting|due|hold|on hold|in progress|processing|unverified|pending approval|not billed)$/i,
    bad: /^(inactive|void|voided|cancel+ed|rejected|blocked|closed|expired|dishono(u)?red|disabled|overdue|unpaid|out of stock|returned|locked)$/i
  };
  var ACTION = /^(edit( profile| details| price)?|view|details?|delete|remove|print|preview|open|approve|reject|receipt|ledger|select|show|download|cancel|restore|reset|reply)$/i;
  var NUMERIC_HEAD = /amount|qty|quantity|price|total|balance|due|owes|owed|limit|rate|value|cost|stock|discount|vat|ait|tax|paid|debit|credit|charge|salary|payable|receivable|commission|target|point/i;
  var NOT_NUMERIC_HEAD = /mobile|phone|code|^no\b|no\.?$|id$|nid|bin|serial|imei|year|date|time|nr|sl|#|account no|invoice|ref/i;
  function cellText(td) { return (td.textContent || '').replace(/\s+/g, ' ').trim(); }
  function polishTables(doc) {
    if (/[?&](print|receipt|challan|export|auto)=/.test(doc.defaultView.location.search) || doc.body.classList.contains('rma-print')) return;
    doc.querySelectorAll('table').forEach(function (table) {
      if (table.classList.contains('ui-records') || table.closest('.stotals, .totals, .Navigator, .no-ui, .bsd-backdrop, .dash, .sd-line')) return;
      var head = table.querySelector('thead tr, tr.WUIrsheader') || (table.rows[0] && table.rows[0].querySelector('th') ? table.rows[0] : null);
      if (!head || head.cells.length < 3) {
        if (table.classList.contains('ui-form-table')) polishFormTable(table);
        return;
      }
      var body = Array.prototype.filter.call(table.rows, function (r) { return r !== head && !r.closest('thead') && !r.classList.contains('WUIrsheader') && r.cells.length >= head.cells.length - 1; });
      if (!body.length) return;
      table.classList.add('ui-records');
      var heads = Array.prototype.map.call(head.cells, cellText);
      heads.forEach(function (h, c) {
        var col = body.map(function (r) { return r.cells[c]; }).filter(Boolean);
        var cell = head.cells[c];
        // A column of tick boxes, or the row number
        if (col.length && col.every(function (td) { return td.querySelector('input[type=checkbox]') && cellText(td) === ''; })) { cell.classList.add('ui-col-check'); col.forEach(function (td) { td.classList.add('ui-col-check'); }); return; }
        if (/^(nr|sl|#|no\.?|serial no\.?|s\.?l\.?)$/i.test(h)) { cell.classList.add('ui-col-nr'); col.forEach(function (td) { td.classList.add('ui-col-nr'); }); return; }
        // Amounts and quantities line up on the right
        var texts = col.map(cellText).filter(function (t) { return t !== ''; });
        var numbers = texts.filter(function (t) { return /^[-(]?(?:[৳$₹₫฿]|RM|Rs|रु)?\s?[\d,]+(\.\d+)?\)?%?$/.test(t); });
        if (texts.length && numbers.length / texts.length >= 0.8 && !NOT_NUMERIC_HEAD.test(h) && (NUMERIC_HEAD.test(h) || numbers.some(function (t) { return /[.,]/.test(t); }))) {
          cell.classList.add('num'); col.forEach(function (td) { td.classList.add('num'); });
        }
        var statusCol = /status|state|approval|stage/i.test(h);
        col.forEach(function (td) {
          // Words such as Active or Pending become badges
          if (!td.children.length) {
            var t = cellText(td);
            if (t && t.length <= 24) {
              var tone = STATUS.good.test(t) || statusCol && /^yes$/i.test(t) ? 'good' : STATUS.wait.test(t) ? 'wait' : STATUS.bad.test(t) || statusCol && /^no$/i.test(t) ? 'bad' : statusCol ? 'plain' : '';
              if (tone) { var pill = doc.createElement('span'); pill.className = 'ui-pill ui-pill-' + tone; pill.textContent = t; td.textContent = ''; td.appendChild(pill); }
            }
          }
          // Edit, View, Delete… become small action buttons
          td.querySelectorAll('a, button, input[type=submit], input[type=button]').forEach(function (a) {
            var label = (a.value || a.textContent || '').replace(/\s+/g, ' ').trim();
            if (ACTION.test(label) && !a.closest('.btn-group')) {
              a.classList.add('ui-act');
              if (/^(delete|remove|reject|cancel)$/i.test(label)) a.classList.add('ui-act-danger');
            }
          });
        });
      });
      addFilter(doc, table, body);
    });
    doc.querySelectorAll('table.ui-form-table').forEach(function (t) { if (!t.classList.contains('ui-form-done')) polishFormTable(t); });
    try { if (doc.defaultView.BSButtons) doc.defaultView.BSButtons.refresh(); } catch (e) {} // row actions just marked: their icons (addon/dialog/buttons.js)
  }
  /* "Find in this list" above a long list that has no search of its own and no pager. */
  function addFilter(doc, table, rows) {
    if (rows.length < 10 || doc.querySelector('.list-pager, tr.list-off, .search-bar, .list-search, .rma-filter, .ui-tablebar') || doc.querySelector('form[method=get] input[type=search], form[method=get] input[type=text]')) return;
    var bar = doc.createElement('div');
    bar.className = 'ui-tablebar';
    bar.innerHTML = '<label class="ui-find"><span aria-hidden="true">&#9906;</span><input type="search" placeholder="Find in this list…" aria-label="Find in this list"></label><span class="ui-count"></span>';
    var input = bar.querySelector('input'), count = bar.querySelector('.ui-count');
    var show = function (n) { count.textContent = n === rows.length ? rows.length + ' records' : n + ' of ' + rows.length + ' records'; };
    show(rows.length);
    input.addEventListener('input', function () {
      var words = input.value.toLowerCase().split(/\s+/).filter(Boolean), n = 0;
      rows.forEach(function (r) {
        var hit = words.every(function (w) { return r.textContent.toLowerCase().indexOf(w) !== -1; });
        r.classList.toggle('ui-filtered', !hit);
        if (hit) n++;
      });
      show(n);
      if (typeof calcHeight === 'function') calcHeight();
    });
    var anchor = table.closest('.table-scroll, .ui-table-scroll, .box-body, .table-responsive') || table;
    anchor.parentNode.insertBefore(bar, anchor);
  }
  /* An older form laid out in a table: label cells and field cells line up; required fields get a mark. */
  function polishFormTable(table) {
    table.classList.add('ui-form-done');
    Array.prototype.forEach.call(table.rows, function (row) {
      Array.prototype.forEach.call(row.cells, function (td) {
        var fields = td.querySelectorAll('input:not([type=hidden]):not([type=checkbox]):not([type=radio]):not([type=submit]):not([type=button]), select, textarea');
        if (fields.length === 1 && !td.querySelector('button, input[type=submit], input[type=button]')) td.classList.add('ui-field-cell');
        else if (!fields.length && cellText(td) && cellText(td).length < 40 && !td.querySelector('button, input, a')) {
          td.classList.add('ui-label-cell');
          var next = td.nextElementSibling;
          if (next && next.querySelector('[required]')) td.classList.add('ui-required');
        }
      });
    });
  }

  function dressPage(doc) {
    if (!doc || !doc.documentElement || doc.documentElement.classList.contains('ui-common')) return;
    var head = doc.head || doc.getElementsByTagName('head')[0];
    if (head) {
      var css = doc.createElement('link');
      css.rel = 'stylesheet';
      css.href = COMMON_CSS;
      head.appendChild(css);
    }
    doc.documentElement.classList.add('ui-common');
    wrapPage(doc);
    fitTables(doc);
    polishTables(doc);
    var pager = document.querySelector('meta[name="list-pager-js"]');
    if (pager && doc.body) {
      var script = doc.createElement('script');
      script.src = new URL(pager.content, location.href).href;
      doc.body.appendChild(script);
    }
    /* Older pages: "You are here: /Resellers I" path and the page heading inside ol.breadcrumb. */
    doc.querySelectorAll('ol.breadcrumb').forEach(function (ol) {
      var path = ol.querySelector('#LabelURHere, #LabelURHereSmall');
      var lead = Array.prototype.find.call(ol.querySelectorAll('span, b, strong'), function (el) { return /^\s*You are here/i.test(el.textContent); });
      if (!path && !lead) return;
      if (lead) lead.classList.add('ui-hide');
      Array.prototype.forEach.call(ol.childNodes, function (n) { if (n.nodeType === 3) n.nodeValue = ''; });
      ol.querySelectorAll('h1, h2, h3').forEach(function (h) { if (h.querySelector('#LabelURHere') || h.id === 'LabelURHere') h.classList.add('ui-hide'); });
      var rest = Array.prototype.filter.call(ol.children, function (c) { return !c.classList.contains('ui-hide') && c.offsetParent !== null && c.textContent.trim() !== ''; });
      if (!rest.length) ol.classList.add('ui-hide');
    });
  }
  /* Keep the frame as tall as the page when a phone rotates, the window resizes or the page's content changes. */
  var fitFrame = function () { if (typeof calcHeight === 'function') calcHeight(); };
  var pageWatch = window.ResizeObserver ? new ResizeObserver(fitFrame) : null;
  window.addEventListener('resize', fitFrame);
  frame.addEventListener('load', function () {
    var doc = null;
    try { doc = frame.contentDocument; } catch (e) { return; } // another site: leave it alone
    dressPage(doc);
    refreshBell(); // the page just opened may have read or raised a notification
    if (pageWatch) {
      pageWatch.disconnect();
      if (doc.body) pageWatch.observe(doc.querySelector('.ui-page, .rma-wrap') || doc.body);
    }
    if (opening) window.scrollTo(0, 0);
    /* A page reached from a link inside another page: use its own title in the bar. */
    var here = '';
    try { here = frame.contentWindow.location.pathname; } catch (e) {}
    if (opening) {
      currentLink = opening;
      currentPath = here;
      opening = null;
    }
    if (currentLink && here === currentPath) {
      markCurrent(currentLink);
    } else if (doc && doc.title && doc.title.trim()) {
      var cat = nav.querySelector('.nav-cat.current');
      setCrumb((cat ? cat.querySelector('.nav-top').textContent.trim() + ' › ' : '') + doc.title.trim());
    }
    if (typeof calcHeight === 'function') calcHeight();
  });
  dressPage((function () { try { return frame.contentDocument; } catch (e) { return null; } })());

  var last = read(LAST_PAGE);
  if (last && linkFor(last)) {
    opening = linkFor(last);
    frame.src = last;
    markCurrent(opening);
  } else {
    opening = linkFor(frame.getAttribute('src'));
    markCurrent(opening);
  }

  /* ---------- Page finder ---------- */
  var input = document.getElementById('menu-search');
  var results = document.getElementById('menu-results');
  var pages = Array.prototype.map.call(nav.querySelectorAll('a[target="frm_main"]:not(.brand)'), function (a) {
    var path = (a.getAttribute('data-path') || a.textContent).split(' › ');
    return { link: a, label: path[path.length - 1], where: path.slice(0, -1).join(' › '), text: path.join(' ').toLowerCase() };
  });
  var active = -1;

  function hideResults() { results.hidden = true; active = -1; }
  function show(list) {
    results.innerHTML = '';
    if (!list.length) {
      results.innerHTML = '<li class="none">No page matches.</li>';
    }
    list.slice(0, 15).forEach(function (p) {
      var li = document.createElement('li');
      var a = document.createElement('a');
      a.href = p.link.getAttribute('href');
      a.target = 'frm_main';
      a.textContent = p.label;
      var small = document.createElement('small');
      small.textContent = p.where;
      a.appendChild(small);
      a.addEventListener('click', function () { opened(p.link); input.value = ''; hideResults(); input.blur(); });
      li.appendChild(a);
      results.appendChild(li);
    });
    results.hidden = false;
    active = -1;
  }
  input.addEventListener('input', function () {
    var words = input.value.toLowerCase().split(/\s+/).filter(Boolean);
    if (!words.length) { hideResults(); return; }
    show(pages.filter(function (p) { return words.every(function (w) { return p.text.indexOf(w) !== -1; }); }));
  });
  input.addEventListener('keydown', function (e) {
    var links = results.querySelectorAll('a');
    if (results.hidden || !links.length) return;
    if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
      e.preventDefault();
      active = (active + (e.key === 'ArrowDown' ? 1 : -1) + links.length) % links.length;
      links.forEach(function (l, i) { l.classList.toggle('active', i === active); });
    } else if (e.key === 'Enter') {
      e.preventDefault();
      links[Math.max(active, 0)].click();
    }
  });
  document.addEventListener('click', function (e) { if (!e.target.closest('.menu-search')) hideResults(); });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') { if (side) { setDrawer(false); } else { closeAll(); } hideResults(); if (userMenu) userMenu.classList.remove('open'); }
    if (e.key === '/' && document.activeElement !== input && !/INPUT|TEXTAREA|SELECT/.test(document.activeElement.tagName)) {
      e.preventDefault();
      input.focus();
    }
  });
})();
