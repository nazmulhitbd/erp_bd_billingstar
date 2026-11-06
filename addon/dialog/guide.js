/*
 * Form guide for PetaBill entry pages. Loaded on every page with dialog.js (bs_dialog_start in addon/inc/security.php).
 *  - A step bar above the form: what to fill first, then next. Steps turn green when done; the next one is marked.
 *  - "How to fill this" opens on every new record (until "Don't show this again"); the Guide button opens it any time.
 *  - Recommended order: pressing Add or Save before an earlier step is done asks "Fill it first" or "Continue anyway".
 *  - Examples in empty fields ("e.g. …") and a drop-down of suggestions: the page's usual values plus what was typed before
 *    on this computer (only for plain text such as narration, group or delivery point; never names, numbers or passwords).
 * Pages are described in GUIDES below. A step or rule whose fields are not on a page is left out, so one description serves
 * every screen that shares the same fields (the inventory document screens, for example). Other pages with a form get a
 * Guide button listing their required fields.
 */
(function () {
  'use strict';
  if (window.BSGuide) return;

  /* ---------------------------------------------------------------- page descriptions */
  var SALES_EXAMPLES = {
    '#mobile': 'e.g. 01711-123456', '#customer_name': 'e.g. Rahim Traders', '#customer_group': 'e.g. Dealer',
    '#attention': 'e.g. Mr. Karim, Accounts', '#address': 'e.g. House 12, Road 5, Dhanmondi, Dhaka',
    '#delivery_point': 'e.g. Customer shop, Mirpur 10', '#narration': 'e.g. Delivered with 1 year warranty',
    '#ref_no': "e.g. customer's PO-2231", '#bin': 'e.g. 000108157-0101', '#nid': 'e.g. 1990123456789',
    '#sales_person': 'e.g. Karim Rahman', '#e_model': 'Type a model, code or name', '#e_qty': 'e.g. 1',
    '#e_price': 'e.g. 45000', '#e_warranty': 'e.g. 1 year', '#e_serial': 'One serial per line', '#member_no': 'e.g. M-1024'
  };
  var SALES_LISTS = { '#customer_group': ['Dealer', 'Retailer', 'Corporate', 'Walk-in'], '#e_unit': ['Pcs', 'Box', 'Set', 'Pair', 'Kg'], '#e_warranty': ['No warranty', '6 months', '1 year', '2 years', '3 years'] };
  var SALES_REMEMBER = ['#customer_group', '#attention', '#delivery_point', '#narration', '#e_warranty', '#e_unit'];
  var HAS_ITEMS = { rows: '#grid-body tr' };

  var GUIDES = [
    {
      match: /\/sales\/sales_invoice\/sales_invoice\.php$/, key: 'sales-invoice', title: 'Sales Invoice', form: '#docform',
      intro: 'Fill the invoice in this order. Each step turns green when it is done.',
      steps: [
        { title: 'Customer', text: 'Search by mobile number, or type the name and pick the customer from the list. A saved customer fills the rest.', focus: '#mobile', done: ['#customer_name'] },
        { title: 'Invoice details', text: 'Check the date and the bill type: Cash, or Credit for customers who pay later.', focus: '#sales_date', done: ['#sales_date', '#bill_type'] },
        { title: 'Products', text: 'Search the model, pick the store, enter quantity and price, then press Add. Repeat for every product.', focus: '#e_model', done: [HAS_ITEMS] },
        { title: 'Payment', text: 'Enter the cash received. For credit, add the payment day or a schedule.', focus: '#t_cash', optional: true, done: ['#t_cash'] },
        { title: 'Save', text: 'Press Save, then print the invoice or the challan.', focus: '#save-btn' }
      ],
      rules: [
        { on: '#add-line', need: ['#customer_name'], step: 0, text: 'Pick the customer before adding products, so the right price and credit limit apply.' },
        { on: '#save-btn', need: [HAS_ITEMS], step: 2, text: 'Add at least one product before saving.' },
        { on: '#save-btn', need: ['#customer_name'], step: 0, text: 'The invoice has no customer. Pick one, or continue for a walk-in sale.' }
      ],
      examples: SALES_EXAMPLES, lists: SALES_LISTS, remember: SALES_REMEMBER
    },
    {
      match: /\/sales\/sales_order\/sales_order\.php$/, key: 'sales-order', title: 'Sales Order', form: '#docform',
      intro: 'Book the order in this order. Stock leaves the store only when the order is billed.',
      steps: [
        { title: 'Customer', text: 'Search by mobile number, or type the name and pick the customer.', focus: '#mobile', done: ['#customer_name'] },
        { title: 'Dates', text: 'Order date, and the delivery date the customer expects.', focus: '#order_date', done: ['#order_date', '#delivery_date'] },
        { title: 'Products', text: 'Search the model, enter quantity and price, then press Add for every product.', focus: '#e_model', done: [HAS_ITEMS] },
        { title: 'Advance', text: 'Enter any advance the customer paid, in the summary on the right.', focus: '#t_advance', optional: true, done: ['#t_advance'] },
        { title: 'Save', text: 'Press Save. Bill it later from Sales Bill From Sales Order.', focus: '#save-btn' }
      ],
      rules: [
        { on: '#add-line', need: ['#customer_name'], step: 0, text: 'Pick the customer before adding products.' },
        { on: '#save-btn', need: [HAS_ITEMS], step: 2, text: 'Add at least one product before saving.' }
      ],
      examples: Object.assign({ '#delivery_date': '' }, SALES_EXAMPLES), lists: SALES_LISTS, remember: SALES_REMEMBER
    },
    {
      // Purchase, Purchase Order, Requisition, Challan, Transfer, Quotation, Stock Amendment… (inventory/inv_doc_engine.php)
      match: /\/(purchase|challan|transfer|amedment|price_history)\/[a-z_]+\.php$/, key: 'inv-doc', title: '', form: '#docform', needs: '#lines_json',
      intro: 'Fill the document in this order. Each step turns green when it is done.',
      steps: [
        { title: 'Party', text: 'Pick the supplier or customer from the list as you type.', focus: '#party_name', done: ['#party_name'] },
        { title: 'Date and reference', text: 'Check the date; add their reference number if they gave one.', focus: '#doc_date', done: ['#doc_date'] },
        { title: 'Products', text: 'Search the model, pick the store, enter quantity and price, then press Add for every product.', focus: '#e_model', done: [{ rows: '#grid-body tr, #lines tbody tr, table.lines tbody tr' }] },
        { title: 'Payment', text: 'Enter the amount paid now, if any.', focus: '#t_cash', optional: true, done: ['#t_cash'] },
        { title: 'Save', text: 'Press Save. It may need approval before stock moves.', focus: 'button[type=submit].primary, #save-btn' }
      ],
      rules: [
        { on: '#add-line, #add', need: ['#party_name'], step: 0, text: 'Pick the party first, so their last prices show.' }
      ],
      examples: {
        '#party_name': 'Type a name and pick from the list', '#ref_no': 'e.g. their invoice INV-5521', '#mobile': 'e.g. 01711-123456',
        '#e_model': 'Type a model, code or name', '#e_qty': 'e.g. 10', '#e_price': 'e.g. 4200', '#narration': 'e.g. Received in good condition',
        '#payment_term': 'e.g. 30 days after delivery', '#e_serial': 'One serial per line'
      },
      lists: { '#payment_term': ['Cash on delivery', '15 days', '30 days', '45 days'], '#e_unit': ['Pcs', 'Box', 'Set', 'Kg'] },
      remember: ['#narration', '#payment_term', '#e_unit', '#e_warranty']
    }
  ];

  /* ---------------------------------------------------------------- helpers */
  var D = function () { return window.BSDialog; };
  function $(sel) { try { return document.querySelector(sel); } catch (e) { return null; } }
  function present(sel) { return typeof sel === 'string' ? !!$(sel) : !!$(sel.rows); }
  function filled(sel) {
    if (typeof sel !== 'string') {
      var rows = document.querySelectorAll(sel.rows), n = 0;
      rows.forEach(function (r) { if (!r.querySelector('td.empty') && r.offsetParent !== null) n++; });
      return n > 0;
    }
    var el = $(sel);
    return !!el && String(el.value || '').trim() !== '' && !(el.type === 'number' && Number(el.value) === 0 && el.id === 't_cash');
  }
  function labelFor(el) {
    if (!el) return '';
    var l = el.id && document.querySelector('label[for="' + el.id + '"]');
    if (!l) l = el.closest('label');
    return l ? l.textContent.replace(/\s+/g, ' ').trim().replace(/[:*]$/, '') : (el.getAttribute('placeholder') || el.name || '');
  }
  function flash(el) {
    if (!el) return;
    try { el.scrollIntoView({ block: 'center', behavior: 'smooth' }); } catch (e) { el.scrollIntoView(); }
    if (el.focus && !el.disabled) el.focus({ preventScroll: true });
    el.classList.remove('bsg-pulse');
    void el.offsetWidth;
    el.classList.add('bsg-pulse');
    setTimeout(function () { el.classList.remove('bsg-pulse'); }, 1800);
  }

  /* ---------------------------------------------------------------- examples and suggestion lists */
  var SAFE = /narration|remark|note|group|delivery|attention|description|brand|warranty|unit|designation|term|purpose|reason/i;
  var NEVER = /pass|otp|token|csrf|nid|bin|mobile|phone|email|card|account_no|name$/i;
  function historyKey(el) { return 'bsg_hist:' + location.pathname.replace(/[^a-z_\/]/gi, '') + ':' + (el.id || el.name); }
  function readHistory(el) { try { return JSON.parse(localStorage.getItem(historyKey(el)) || '[]'); } catch (e) { return []; } }
  function attachList(el, values) {
    values = values.filter(function (v, i) { return v && values.indexOf(v) === i; }).slice(0, 12);
    if (!values.length || el.getAttribute('list')) return;
    var id = 'bsg-dl-' + (el.id || el.name);
    var dl = document.getElementById(id) || document.createElement('datalist');
    dl.id = id;
    dl.innerHTML = '';
    values.forEach(function (v) { var o = document.createElement('option'); o.value = v; dl.appendChild(o); });
    document.body.appendChild(dl);
    el.setAttribute('list', id);
  }
  function setupFields(g) {
    var ex = (g && g.examples) || {}, lists = (g && g.lists) || {}, keep = (g && g.remember) || [];
    Object.keys(ex).forEach(function (sel) {
      var el = $(sel);
      if (!el || !ex[sel] || el.type === 'date' || el.tagName === 'SELECT') return;
      var ph = el.getAttribute('placeholder') || '';
      // Replace a placeholder that only repeats the label ("Mobile Number") with a real example.
      if (!ph || ph.toLowerCase().replace(/\s+/g, '') === labelFor(el).toLowerCase().replace(/\s+/g, '') || /^(mobile number|customer name|attention|address|sales person)$/i.test(ph)) {
        el.setAttribute('placeholder', ex[sel]);
      }
    });
    var remembered = [];
    keep.forEach(function (sel) { var el = $(sel); if (el) remembered.push(el); });
    // Plain-text fields of any form are remembered too, but never personal details.
    document.querySelectorAll('form input[type=text], form input:not([type])').forEach(function (el) {
      var key = (el.id || '') + ' ' + (el.name || '');
      if (SAFE.test(key) && !NEVER.test(key) && remembered.indexOf(el) === -1) remembered.push(el);
    });
    remembered.forEach(function (el) {
      var sel = el.id ? '#' + el.id : '';
      attachList(el, (lists[sel] || []).concat(readHistory(el)));
    });
    Object.keys(lists).forEach(function (sel) { var el = $(sel); if (el && remembered.indexOf(el) === -1) attachList(el, lists[sel]); });
    document.addEventListener('submit', function () {
      remembered.forEach(function (el) {
        var v = String(el.value || '').trim();
        if (!v || v.length > 120) return;
        var h = readHistory(el).filter(function (x) { return x !== v; });
        h.unshift(v);
        try { localStorage.setItem(historyKey(el), JSON.stringify(h.slice(0, 8))); } catch (e) {}
      });
    }, true);
  }

  /* ---------------------------------------------------------------- steps */
  function stepDone(s) { return !!(s.done && s.done.length && s.done.every(filled)); }
  /** The first required step not done yet; when all are done, the last step (Save). */
  function nextStep(steps) {
    for (var i = 0; i < steps.length; i++) {
      if (steps[i].done && !steps[i].optional && !stepDone(steps[i])) return i;
    }
    return steps.length - 1;
  }
  function buildBar(g, steps, form) {
    var bar = document.createElement('nav');
    bar.className = 'bsg-bar';
    bar.setAttribute('aria-label', 'Steps');
    var help = document.createElement('button');
    help.type = 'button';
    help.className = 'bsg-help';
    help.innerHTML = '<span aria-hidden="true">?</span> How to fill';
    help.addEventListener('click', function () { openGuide(g, steps, false); });
    steps.forEach(function (st, i) {
      var b = document.createElement('button');
      b.type = 'button';
      b.className = 'bsg-step';
      b.innerHTML = '<i>' + (i + 1) + '</i><span></span>';
      b.querySelector('span').textContent = st.title + (st.optional ? ' (if any)' : '');
      b.title = st.text;
      b.addEventListener('click', function () { flash($(st.focus) || $(st.fields && st.fields[0])); });
      bar.appendChild(b);
    });
    var keys = document.createElement('span');
    keys.className = 'bsg-keys';
    keys.innerHTML = '<kbd>Enter</kbd> next field &middot; <kbd>Ctrl</kbd>+<kbd>S</kbd> save';
    bar.appendChild(keys);
    bar.appendChild(help);
    form.parentNode.insertBefore(bar, form);
    return bar;
  }
  function refresh(bar, steps) {
    var n = nextStep(steps);
    bar.querySelectorAll('.bsg-step').forEach(function (b, i) {
      var done = stepDone(steps[i]);
      b.classList.toggle('done', done);
      b.classList.toggle('next', i === n && !done);
      b.querySelector('i').textContent = done ? '✓' : String(i + 1);
    });
    document.querySelectorAll('[data-step]').forEach(function (sec) {
      var i = Number(sec.getAttribute('data-step')) - 1;
      if (!steps[i]) return;
      sec.classList.toggle('bsg-sec-done', stepDone(steps[i]));
      sec.classList.toggle('bsg-sec-next', i === n && !stepDone(steps[i]));
    });
  }
  function openGuide(g, steps, auto) {
    if (!D()) return;
    var opts = {
      type: 'tip', title: 'How to fill: ' + (g.title || document.title || 'this page'), message: g.intro,
      steps: steps.map(function (st) { return { title: st.title + (st.optional ? ' (if any)' : ''), text: st.text, done: stepDone(st) }; }),
      ok: 'Start'
    };
    if (auto) opts.remember = 'guide-' + g.key;
    D().show(opts).then(function (go) {
      if (go) { var st = steps[nextStep(steps)]; flash($(st.focus)); }
    });
  }

  /* ---------------------------------------------------------------- recommended order */
  function setupRules(g, steps) {
    var rules = (g.rules || []).filter(function (r) { return $(r.on) && r.need.every(present); });
    var pass = false;
    document.addEventListener('click', function (e) {
      if (pass || !D()) return;
      var hit = null;
      for (var i = 0; i < rules.length; i++) {
        var target = e.target.closest && e.target.closest(rules[i].on);
        if (target && !target.disabled && !rules[i].need.every(filled)) { hit = rules[i]; break; }
      }
      if (!hit) return;
      e.preventDefault();
      e.stopImmediatePropagation();
      var btn = e.target.closest(hit.on);
      var st = steps[hit.step] || {};
      D().show({ type: 'tip', title: 'Recommended: ' + (st.title || 'an earlier step') + ' first', message: hit.text, ask: true, ok: 'Fill it first', cancel: 'Continue anyway' })
        .then(function (fill) {
          if (fill === true) { var need = hit.need.find(function (n) { return !filled(n); }); flash(typeof need === 'string' ? $(need) : $(st.focus)); }
          else if (fill === false) { pass = true; try { btn.click(); } finally { pass = false; } }
        });
    }, true);
  }

  /* ---------------------------------------------------------------- pages with no description: their required fields */
  function autoGuide() {
    var form = Array.prototype.find.call(document.forms, function (f) { return f.querySelectorAll('[required]').length >= 2; });
    if (!form) return;
    var names = Array.prototype.map.call(form.querySelectorAll('[required]'), labelFor).filter(Boolean);
    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'bsg-fab';
    btn.innerHTML = '<span aria-hidden="true">?</span> Guide';
    btn.addEventListener('click', function () {
      D().show({ type: 'tip', title: 'How to fill this page', message: 'Fill these first; the rest is optional.', steps: names.slice(0, 8).concat(['Press Save']), ok: 'OK' });
    });
    document.body.appendChild(btn);
  }

  function start() {
    var g = GUIDES.find(function (x) { return x.match.test(location.pathname); });
    var form = g && $(g.form);
    if (!g || !form || (g.needs && !$(g.needs))) { setupFields(null); autoGuide(); return; }
    g.title = g.title || (document.querySelector('legend, h1') || {}).textContent || document.title;
    var steps = g.steps.filter(function (st) { return $(st.focus) || (st.done || []).some(present); });
    if (!steps.length) return;
    setupFields(g);
    var bar = buildBar(g, steps, form);
    var update = function () { refresh(bar, steps); };
    update();
    form.addEventListener('input', update);
    form.addEventListener('change', update);
    if (window.MutationObserver) new MutationObserver(update).observe(form, { childList: true, subtree: true });
    setupRules(g, steps);
    var isNew = !/[?&](id|edit|view|print)=/.test(location.search);
    if (isNew && D() && !D().remembered('guide-' + g.key)) setTimeout(function () { openGuide(g, steps, true); }, 350);
    window.BSGuide.page = { guide: g, steps: steps, open: function () { openGuide(g, steps, false); } };
  }

  window.BSGuide = { guides: GUIDES };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start);
  else start();
})();
