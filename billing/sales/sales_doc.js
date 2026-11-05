/* Sales Invoice/Bill and Sales Order screens. Settings come from window.SDOC (see sales_doc.php / the page). */
(function () {
  const C = window.SDOC, $ = id => document.getElementById(id), form = $('docform'), inv = C.kind === 'invoice';
  const items = (C.items || []).map(normalise), schedules = (C.schedules || []).slice(), exchanges = (C.exchanges || []).slice();
  let picked = null, selected = -1, showCost = false, showPrice = false, member = C.member, mode = C.saved ? 'view' : 'new';

  function normalise(l) {
    const serials = Array.isArray(l.serials) ? l.serials : String(l.serials || l.serial_imei || '').split(/[\r\n,]+/).map(s => s.trim()).filter(Boolean);
    return {
      item_id: Number(l.item_id || 0), product_id: Number(l.product_id || 0), product_code: l.product_code || '', product_group: l.product_group || '', brand: l.brand || '',
      model_caption: l.model_caption || '', description: l.description || '', product_narration: l.product_narration || '', warranty: l.warranty || '',
      store_name: l.store_name || '', unit_name: l.unit_name || 'Pcs', quantity: Number(l.quantity || 0), free_quantity: Number(l.free_quantity || 0),
      unit_price: Number(l.unit_price || 0), discount_amount: Number(l.discount_amount || 0), serials
    };
  }
  const lineTotal = l => Math.max(0, Math.round((l.quantity * l.unit_price - l.discount_amount) * 100) / 100);
  const product = id => C.products.find(p => Number(p.product_id) === Number(id));

  /* ---------- Modes: new, view (saved), edit (all), price (grid prices), details (header) ---------- */
  const keepOpen = ['ref_no', 'mobile', 'doc_no', 't_cash', 'pay_days', 'pay_date', 'pay_note'];
  function setMode(m) {
    mode = m;
    const header = mode === 'new' || mode === 'edit' || mode === 'details', lines = mode === 'new' || mode === 'edit';
    form.querySelectorAll('.sgrid input, .sgrid select, #additional-info input').forEach(el => { if (!keepOpen.includes(el.id)) el.disabled = C.void || !header; });
    form.querySelectorAll('.entry input, .entry select, .entry textarea, #add-line, #import-file, #x_add, #member_no, #point_adjust, #member-add, #point-add, [name=create_installation]')
      .forEach(el => { el.disabled = C.void || !lines; });
    $('save-btn').disabled = C.void || mode === 'view';
    $('save-btn').textContent = { new: 'Save', edit: 'Save', price: 'Save Price', details: 'Save Details', view: 'Save' }[mode];
    $('action').value = { price: 'edit_price', details: 'edit_details' }[mode] || 'save';
    document.querySelectorAll('[data-mode]').forEach(b => b.classList.toggle('primary', b.dataset.mode === mode));
    render();
  }
  document.querySelectorAll('[data-mode]').forEach(b => b.addEventListener('click', () => {
    if (C.void) { alert('This document is void or cancelled.'); return; }
    if (b.dataset.mode !== 'details' && C.lineLock) { alert(C.lineLock); return; }
    setMode(mode === b.dataset.mode ? 'view' : b.dataset.mode);
  }));

  /* ---------- Header: customer, searches ---------- */
  attachSuggest($('customer_name'), $('customer-suggestions'), C.customers, ['customer_name', 'mobile', 'customer_code', 'customer_group'], customerLabel, c => { fillCustomer(c); customerInfo(); });
  $('customer_name').addEventListener('input', () => { $('customer_id').value = ''; });
  async function customerInfo() {
    const id = $('customer_id').value; if (!inv || !id || !$('i_advance')) return;
    try { const j = await (await fetch(`${C.self}?customer_info=${id}`, { credentials: 'same-origin' })).json(); $('i_advance').textContent = money(j.advance) + (j.due > 0 ? `  (Due ${money(j.due)})` : ''); } catch (e) { /* info only */ }
  }
  document.querySelectorAll('[data-find]').forEach(b => b.addEventListener('click', () => {
    const field = b.dataset.find === 'ref' ? 'ref_no' : 'doc_no', v = $(field).value.trim();
    if (!v) { alert('Enter a value to search.'); return; }
    location.href = `${C.self}?${b.dataset.find}=${encodeURIComponent(v)}`;
  }));
  $('mobile-search').addEventListener('click', () => {
    const m = $('mobile').value.replace(/\D/g, ''); if (!m) { alert('Enter a mobile number.'); return; }
    const c = C.customers.find(x => String(x.mobile || '').replace(/\D/g, '').endsWith(m));
    if (c && (mode === 'new' || mode === 'edit' || mode === 'details')) { fillCustomer(c); customerInfo(); return; }
    location.href = `${C.self}?mobile=${encodeURIComponent($('mobile').value.trim())}`;
  });
  $('save-customer')?.addEventListener('click', async () => {
    const fd = new FormData(form); fd.set('action', 'save_customer');
    ['customer_name', 'customer_group', 'mobile', 'attention', 'address', 'bin', 'nid'].forEach(k => fd.set(k, $(k).value));
    try {
      const r = await fetch(C.self, { method: 'POST', body: fd, credentials: 'same-origin' }), j = await r.json();
      if (!r.ok) { alert(j.error || 'Could not save the customer.'); return; }
      $('customer_id').value = j.customer_id;
      const c = { customer_id: j.customer_id, customer_name: $('customer_name').value, customer_group: $('customer_group').value, mobile: $('mobile').value, attention: $('attention').value, address: $('address').value, bin: $('bin').value, nid: $('nid').value };
      const i = C.customers.findIndex(x => Number(x.customer_id) === Number(j.customer_id)); i >= 0 ? C.customers[i] = c : C.customers.push(c);
      alert(j.message);
    } catch (e) { alert('Could not save the customer.'); }
  });

  /* ---------- Product entry ---------- */
  const mIn = $('e_model');
  attachSuggest(mIn, $('product-suggestions'), C.products, ['model_caption', 'product_code', 'description', 'brand', 'product_group'],
    p => `${p.product_code || ''} ${p.model_caption || ''} · ${p.description} (${money(p.default_unit_price)})`.trim(), pick);
  mIn.addEventListener('input', () => { picked = null; });
  function pick(p) {
    picked = p; mIn.value = p.model_caption || p.description;
    $('e_group').value = p.product_group || ''; $('e_brand').value = p.brand || ''; $('e_desc').value = p.description || ''; $('e_unit').value = p.unit_name || 'Pcs';
    if ($('e_warranty')) $('e_warranty').value = p.warranty || '';
    $('e_price').value = Number(p.default_unit_price || p.mrp || 0);
    info(); $('e_qty').focus();
  }
  $('e_store').addEventListener('change', info);
  async function info() {
    if (!picked) return;
    try {
      const j = await (await fetch(`${C.self}?info=${picked.product_id}&store=${encodeURIComponent($('e_store').value)}&customer=${$('customer_id').value || 0}`, { credentials: 'same-origin' })).json();
      const set = (id, v) => { if ($(id)) $(id).textContent = v; };
      set('i_last', money(j.last_price)); set('i_qty', qtyFmt(j.qty)); set('i_serial', j.last_serial || ''); set('i_dealer', money(j.dealer_a)); set('i_sprice', money(j.sales_price)); set('i_rest', qtyFmt(j.rest_qty));
    } catch (e) { /* info only */ }
    extras();
  }
  function extras() {
    const p = picked;
    if ($('i_cost')) $('i_cost').textContent = p && showCost ? `Last Cost: ${money(p.last_cost)}` : '';
    if ($('i_prices')) $('i_prices').textContent = p && showPrice ? `MRP ${money(p.mrp)} · Retail ${money(p.retail_a)} · Dealer ${money(p.dealer_a)}` : '';
    else if (p && showPrice && $('i_cost')) $('i_cost').textContent += `  MRP ${money(p.mrp)} · Retail ${money(p.retail_a)}`;
  }
  $('show-cost').addEventListener('click', () => { showCost = !showCost; $('show-cost').classList.toggle('primary', showCost); extras(); render(); });
  $('show-price').addEventListener('click', () => { showPrice = !showPrice; $('show-price').classList.toggle('primary', showPrice); extras(); });

  function serialMode() { const r = document.querySelector('input[name="sq"]:checked'); return r ? r.value : 'none'; }
  function buildSerials() {
    if (!$('e_serial')) return [];
    const m = serialMode(), common = ($('s_common').value || '').trim();
    let list = ($('e_serial').value || '').split(/[\r\n,]+/).map(s => s.trim()).filter(Boolean);
    if (m === 'range') {
      const f = $('s_from').value.trim(), t = $('s_to').value.trim();
      if (f || t) {
        if (!/^\d+$/.test(f) || !/^\d+$/.test(t)) throw new Error('Serial From and To must be numbers.');
        const a = BigInt(f), b = BigInt(t); if (b < a) throw new Error('Serial To must not be below Serial From.');
        if (b - a > 5000n) throw new Error('A serial range can hold at most 5000 serials.');
        for (let n = a; n <= b; n++) list.push(common + n.toString().padStart(f.length, '0'));
      }
    }
    return [...new Set(list)];
  }
  $('add-line').addEventListener('click', () => {
    let qty = Number($('e_qty').value || 0); const free = Number($('e_free')?.value || 0), desc = $('e_desc').value.trim() || (picked ? picked.description : '');
    let serials;
    try { serials = buildSerials(); } catch (err) { alert(err.message); return; }
    if (serials.length && !(qty + free > 0)) { qty = serials.length; }
    if (!desc || !(qty > 0)) { alert('Pick a product and enter a quantity.'); return; }
    if (C.stockOn && !(picked && picked.product_id)) { alert('Pick the product from the list so stock can be tracked.'); return; }
    if (C.stockOn && !$('e_store').value) { alert('Pick a store.'); return; }
    if (serials.length && serials.length !== Math.round(qty + free)) { alert(`You entered ${serials.length} serial(s) for a quantity of ${qty + free}. They must match.`); return; }
    const p = picked || {};
    items.push(normalise({
      product_id: p.product_id, product_code: p.product_code, product_group: $('e_group').value, brand: $('e_brand').value, model_caption: mIn.value.trim(), description: desc,
      product_narration: $('e_narr')?.value || '', warranty: $('e_warranty')?.value || '', store_name: $('e_store').value, unit_name: $('e_unit').value || 'Pcs',
      quantity: qty, free_quantity: free, unit_price: Number($('e_price').value || 0), discount_amount: Number($('e_disc')?.value || 0), serials
    }));
    picked = null;
    ['e_model', 'e_group', 'e_brand', 'e_desc', 'e_narr', 'e_warranty', 'e_qty', 'e_price'].forEach(id => { if ($(id)) $(id).value = ''; });
    if ($('e_free')) $('e_free').value = 0; if ($('e_disc')) $('e_disc').value = 0; $('e_unit').value = 'Pcs';
    if ($('serial_clear')?.checked) $('e_serial').value = '';
    if ($('e_serial') && !$('serial_storage')?.checked) ['s_common', 's_from', 's_to'].forEach(id => { $(id).value = ''; });
    render(); mIn.focus();
  });

  /* ---------- Grid ---------- */
  function cols() {
    const c = [['SL'], ['Product ID'], ['Model No/Caption']];
    if (inv) c.push(['Description']);
    c.push(['Store Name']); if (inv) c.push(['Narration']);
    c.push(['Quantity', 1]); if (inv) c.push(['Free', 1]);
    c.push(['Unit Name'], ['Unit Price', 1], ['Discount', 1], ['Amount', 1]);
    if (showCost) c.push(['Cost', 1]);
    c.push(['Select'], ['Edit'], ['Delete']);
    return c;
  }
  function render() {
    const c = cols(), lines = mode === 'new' || mode === 'edit', priceEdit = mode === 'price';
    $('grid-head').innerHTML = c.map(([t, n]) => `<th${n ? ' class="num"' : ''}>${t}</th>`).join('');
    const b = $('grid-body');
    b.innerHTML = items.length ? '' : `<tr><td colspan="${c.length}" class="empty">No items yet.</td></tr>`;
    items.forEach((l, i) => {
      const cells = [i + 1, l.product_id || '', safe(l.model_caption)];
      if (inv) cells.push(safe(l.description) + (l.serials.length ? `<br><span class="muted">${l.serials.length} serial(s)</span>` : ''));
      cells.push(safe(l.store_name)); if (inv) cells.push(safe(l.product_narration));
      cells.push(qtyFmt(l.quantity)); if (inv) cells.push(qtyFmt(l.free_quantity));
      cells.push(safe(l.unit_name),
        priceEdit ? `<input type="number" min="0" step="0.01" data-price="${i}" value="${l.unit_price}">` : money(l.unit_price),
        priceEdit ? `<input type="number" min="0" step="0.01" data-disc="${i}" value="${l.discount_amount}">` : money(l.discount_amount), money(lineTotal(l)));
      if (showCost) { const p = product(l.product_id); cells.push(p ? money(Number(p.last_cost || 0) * (l.quantity + l.free_quantity)) : ''); }
      cells.push(`<button type="button" class="btn small" data-sel="${i}">Select</button>`, `<button type="button" class="btn small" data-edit="${i}" ${lines ? '' : 'disabled'}>Edit</button>`,
        `<button type="button" class="btn small danger" data-del="${i}" ${lines ? '' : 'disabled'}>Delete</button>`);
      b.insertAdjacentHTML('beforeend', `<tr${i === selected ? ' class="selected-row"' : ''}>` + cells.map((v, k) => `<td${c[k] && c[k][1] ? ' class="num"' : ''}>${v}</td>`).join('') + '</tr>');
    });
    renderSchedules(); renderExchanges(); totals();
  }
  $('grid-body').addEventListener('click', e => {
    const t = e.target.closest('button'); if (!t) return;
    if (t.dataset.sel !== undefined) { selected = selected === +t.dataset.sel ? -1 : +t.dataset.sel; render(); return; }
    if (t.dataset.del !== undefined) { items.splice(+t.dataset.del, 1); selected = -1; render(); return; }
    if (t.dataset.edit !== undefined) {
      const l = items.splice(+t.dataset.edit, 1)[0];
      picked = product(l.product_id) || (l.product_id ? { product_id: l.product_id } : null);
      mIn.value = l.model_caption || l.description; $('e_group').value = l.product_group; $('e_brand').value = l.brand; $('e_desc').value = l.description; $('e_unit').value = l.unit_name;
      $('e_store').value = l.store_name; if ($('e_narr')) $('e_narr').value = l.product_narration; if ($('e_warranty')) $('e_warranty').value = l.warranty;
      $('e_qty').value = l.quantity; if ($('e_free')) $('e_free').value = l.free_quantity; $('e_price').value = l.unit_price; if ($('e_disc')) $('e_disc').value = l.discount_amount;
      if ($('e_serial')) { $('e_serial').value = l.serials.join('\n'); if (l.serials.length) document.querySelector('input[name="sq"][value="list"]').checked = true; }
      render(); info();
    }
  });
  $('grid-body').addEventListener('input', e => {
    const p = e.target.dataset.price, d = e.target.dataset.disc;
    if (p !== undefined) items[+p].unit_price = Number(e.target.value || 0);
    if (d !== undefined) items[+d].discount_amount = Number(e.target.value || 0);
    updateNet();
  });

  /* ---------- Totals ---------- */
  const hv = k => Number($('h_' + k)?.value || 0);
  function amounts() {
    let sub = 0, qty = 0; items.forEach(l => { sub += lineTotal(l); qty += l.quantity + l.free_quantity; });
    const exchange = exchanges.reduce((s, x) => s + Number(x.amount || 0), 0);
    const before = Math.max(0, sub + hv('extra') + hv('vat') - hv('discount') - exchange);
    const pts = member && C.points ? Number($('point_adjust')?.value || 0) : 0, pointValue = Math.min(before, pts * (C.points ? C.points.value : 0));
    return { sub, qty, exchange, pointValue, net: Math.max(0, before - pointValue) };
  }
  /** Build the totals table (on render); figures are then refreshed in place by updateNet so typing keeps focus. */
  function totals() {
    const edit = mode === 'new' || mode === 'edit', box = $('totals');
    const input = (k, label) => `<tr><th>${label}</th><td><input id="t_${k}" type="number" min="0" step="0.01" value="${hv(k)}" ${edit && !C.void ? '' : 'readonly'}></td></tr>`;
    const out = (id, label, hide) => `<tr id="r_${id}"${hide ? ' hidden' : ''}><th>${label}</th><td class="num" id="tt_${id}"></td></tr>`;
    let rows = out('sub', 'Total Amount') + input('extra', 'Add Extra Charges') + input('discount', 'Less Discount') + input('vat', 'Add VAT &amp; AIT');
    if (inv) rows += out('exchange', 'Product Exchange') + out('points', 'Point Adjust', true);
    rows += out('net', 'Net Payable Amount');
    if (!inv) rows += input('advance', 'Advance Received') + out('balance', 'Balance');
    if (inv && C.saved && mode === 'view') rows += out('paid', 'Paid') + out('due', 'Due');
    rows += out('qty', 'Total Quantity') + out('change', 'Change Amount:');
    box.innerHTML = rows;
    ['extra', 'discount', 'vat', 'advance'].forEach(k => $('t_' + k)?.addEventListener('input', () => { $('h_' + k).value = $('t_' + k).value; updateNet(); }));
    updateNet();
  }
  function updateNet() {
    const a = amounts(), set = (id, v) => { if ($('tt_' + id)) $('tt_' + id).textContent = v; };
    set('sub', money(a.sub)); set('exchange', money(a.exchange)); set('points', money(a.pointValue)); set('net', money(a.net)); set('qty', qtyFmt(a.qty));
    if ($('r_points')) $('r_points').hidden = !(a.pointValue > 0);
    set('balance', money(Math.max(0, a.net - hv('advance')))); set('paid', money(C.paid)); set('due', money(C.due));
    const cash = Number($('t_cash')?.value || 0), owed = inv && C.saved && mode === 'view' ? C.due : a.net;
    set('change', money(Math.max(0, (inv ? cash : hv('advance')) - owed)));
    points(a);
    $('items_json').value = JSON.stringify(items);
    $('schedules_json').value = JSON.stringify(schedules);
    if ($('exchanges_json')) $('exchanges_json').value = JSON.stringify(exchanges);
  }
  $('t_cash')?.addEventListener('input', updateNet);
  $('point_adjust')?.addEventListener('input', updateNet);
  document.querySelectorAll('[data-focus]').forEach(b => b.addEventListener('click', () => { const el = $(b.dataset.focus); if (el) { el.focus(); el.select(); } }));

  /* ---------- Member points (invoice) ---------- */
  function points(a) {
    if (!$('p_prev')) return;
    if (!member || !C.points) { ['p_prev', 'p_earn', 'p_adj', 'p_net', 'p_cur'].forEach(id => { $(id).textContent = ''; }); $('p_name').textContent = ''; return; }
    // Current Point is the balance right now; Net Point is what it will be after this invoice is saved as shown.
    const adj = Number($('point_adjust').value || 0), earn = Math.floor(a.net / 100 * C.points.per100 * 100) / 100, prev = Number(member.balance);
    $('p_prev').textContent = prev; $('p_adj').textContent = adj; $('p_earn').textContent = earn; $('p_net').textContent = Math.round((prev - adj + earn) * 100) / 100;
    $('p_cur').textContent = C.saved && mode === 'view' ? Math.round((prev - adj + earn) * 100) / 100 : prev; $('p_name').textContent = `${member.name} (${member.member_no})`;
  }
  $('member-add')?.addEventListener('click', async () => {
    const no = $('member_no').value.trim(); if (!no) { member = null; totals(); return; }
    try {
      const r = await fetch(`${C.self}?member=${encodeURIComponent(no)}&exclude=${C.docId}`, { credentials: 'same-origin' }), j = await r.json();
      if (!r.ok) { alert(j.error || 'Member not found.'); member = null; } else { member = j; C.points = j.settings; }
    } catch (e) { alert('Could not look up the member.'); }
    totals();
  });
  $('point-add')?.addEventListener('click', () => {
    if (!member) { alert('Add the member first.'); return; }
    if (Number($('point_adjust').value || 0) > Number(member.balance)) { alert(`Only ${member.balance} points are available.`); $('point_adjust').value = member.balance; }
    totals();
  });

  /* ---------- Schedule ---------- */
  function renderSchedules() {
    const b = $('s_body'); if (!b) return;
    b.innerHTML = schedules.length ? '' : `<tr><td colspan="4" class="empty">No schedule.</td></tr>`;
    schedules.forEach((s, i) => b.insertAdjacentHTML('beforeend', inv
      ? `<tr><td>${safe(s.due_date)}</td><td>${safe(s.note)}</td><td class="num">${money(s.amount)}</td><td><button type="button" class="btn small danger" data-sdel="${i}">Delete</button></td></tr>`
      : `<tr><td>${safe(s.note)}</td><td>${safe(s.due_date)}</td><td><button type="button" class="btn small danger" data-sdel="${i}">Delete</button></td></tr>`));
  }
  document.addEventListener('click', e => { const s = e.target.closest('[data-sdel]'); if (s) { schedules.splice(+s.dataset.sdel, 1); render(); } });
  $('date-add').addEventListener('click', () => {
    let date = $('pay_date').value;
    const days = Number($('pay_days')?.value || 0), base = $('sales_date')?.value || $('order_date')?.value;
    if (!date && days > 0 && base) { const d = new Date(base + 'T00:00:00'); d.setDate(d.getDate() + days); date = d.toISOString().slice(0, 10); }
    if (!date) { alert(inv ? 'Enter the number of days or pick a date.' : 'Pick the payment date.'); return; }
    if (inv) {
      const a = amounts(), owed = (C.saved && mode === 'view' ? C.due : a.net - Number($('t_cash').value || 0)) - schedules.reduce((s, x) => s + Number(x.amount || 0), 0);
      const amount = Number(prompt('Amount due on ' + date, Math.max(0, Math.round(owed * 100) / 100)) || 0);
      if (!(amount > 0)) return;
      schedules.push({ due_date: date, amount, note: days > 0 ? days + ' days' : '' });
      $('schedule').hidden = false;
    } else {
      schedules.push({ due_date: date, note: $('pay_note').value.trim(), amount: 0 }); $('pay_note').value = '';
    }
    $('pay_date').value = ''; if ($('pay_days')) $('pay_days').value = '';
    render();
  });
  $('save-schedule')?.addEventListener('click', () => { updateNet(); $('action').value = 'save_schedule'; form.requestSubmit ? form.requestSubmit() : form.submit(); });

  /* ---------- Exchange (invoice) ---------- */
  function renderExchanges() {
    const b = $('x_body'); if (!b) return;
    b.innerHTML = exchanges.length ? '' : '<tr><td colspan="5" class="empty">No exchange.</td></tr>';
    exchanges.forEach((x, i) => b.insertAdjacentHTML('beforeend', `<tr><td>${safe(x.description)}</td><td>${safe(x.serial_no)}</td><td class="num">${qtyFmt(x.quantity)}</td><td class="num">${money(x.amount)}</td><td><button type="button" class="btn small danger" data-xdel="${i}" ${mode === 'new' || mode === 'edit' ? '' : 'disabled'}>Delete</button></td></tr>`));
  }
  document.addEventListener('click', e => { const x = e.target.closest('[data-xdel]'); if (x) { exchanges.splice(+x.dataset.xdel, 1); render(); } });
  $('x_add')?.addEventListener('click', () => {
    const d = $('x_desc').value.trim(), amount = Number($('x_amount').value || 0);
    if (!d || !(amount > 0)) { alert('Enter the exchanged item and its value.'); return; }
    exchanges.push({ description: d, serial_no: $('x_serial').value.trim(), quantity: Number($('x_qty').value || 1), amount });
    ['x_desc', 'x_serial', 'x_amount'].forEach(id => { $(id).value = ''; }); $('x_qty').value = 1; render();
  });

  /* ---------- Panels, prints, hold, import ---------- */
  document.querySelectorAll('[data-toggle]').forEach(b => b.addEventListener('click', () => {
    const el = $(b.dataset.toggle); if (!el) return;
    if (b.dataset.print) { const bills = el.querySelectorAll('[data-bill]'); if (bills.length === 1) { window.open(bills[0].href + '&auto=1', '_blank'); return; } }
    el.hidden = !el.hidden; if (!el.hidden) el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }));
  $('serial-view')?.addEventListener('click', () => {
    const list = (selected >= 0 ? [items[selected]] : items).filter(l => l.serials.length).map(l => `${l.model_caption || l.description} (${l.serials.length}):\n  ${l.serials.join(', ')}`);
    alert(list.length ? list.join('\n\n') : 'No serials' + (selected >= 0 ? ' on the selected line.' : ' on this invoice.'));
  });
  $('direct-print')?.addEventListener('click', () => {
    const f = document.createElement('iframe'); f.style.display = 'none'; f.src = `${C.self}?print=${C.docId}&auto=1`; document.body.appendChild(f);
  });
  $('cash-receipt')?.addEventListener('click', () => {
    if (!(Number($('t_cash').value || 0) > 0)) { alert('Enter the Cash Amount received.'); $('t_cash').focus(); return; }
    $('action').value = 'receipt'; form.requestSubmit ? form.requestSubmit() : form.submit();
  });
  $('hold').addEventListener('click', () => {
    let held = null; try { held = JSON.parse(localStorage.getItem(C.held) || 'null'); } catch (e) { held = null; }
    if (C.saved) { alert('Hold keeps an unsaved document aside. Open New first.'); return; }
    if (held) {
      if (!confirm('Bring back the held document? What is on screen now will be replaced.')) return;
      Object.entries(held.fields || {}).forEach(([k, v]) => { const el = form.elements[k]; if (el && (el.type !== 'hidden' || k === 'customer_id')) el.value = v; });
      items.splice(0, items.length, ...held.items.map(normalise)); schedules.splice(0, schedules.length, ...(held.schedules || [])); exchanges.splice(0, exchanges.length, ...(held.exchanges || []));
      try { localStorage.removeItem(C.held); } catch (e) { /* ignore */ }
      render(); return;
    }
    if (!items.length) { alert('Nothing to hold yet.'); return; }
    const fields = {}; new FormData(form).forEach((v, k) => { if (!/_json$|^csrf_token$|^action$/.test(k)) fields[k] = v; });
    try { localStorage.setItem(C.held, JSON.stringify({ fields, items, schedules, exchanges })); } catch (e) { alert('This browser cannot hold documents.'); return; }
    location.href = C.self;
  });
  $('import-file')?.addEventListener('change', e => {
    const f = e.target.files[0]; if (!f) return; const rd = new FileReader();
    rd.onload = () => {
      let ok = 0; const miss = [];
      String(rd.result).split(/\r?\n/).forEach((row, n) => {
        const c = row.split(',').map(x => x.trim().replace(/^"|"$/g, '')); if (!c[0] || (n === 0 && /code|model/i.test(c[0]))) return;
        const p = C.products.find(x => x.product_code === c[0] || x.model_caption === c[0]); if (!p) { miss.push(c[0]); return; }
        const serials = (c[3] || '').split('|').map(s => s.trim()).filter(Boolean);
        items.push(normalise({ ...p, quantity: Number(c[1] || serials.length || 1), unit_price: Number(c[2] || p.default_unit_price || 0), store_name: $('e_store').value || C.defaultStore, serials })); ok++;
      });
      e.target.value = ''; render(); alert(`${ok} line(s) imported.` + (miss.length ? ` Not found: ${miss.slice(0, 10).join(', ')}` : ''));
    };
    rd.readAsText(f);
  });

  form.addEventListener('submit', e => {
    const action = e.submitter && e.submitter.value === 'void' || e.submitter && e.submitter.value === 'cancel' ? e.submitter.value : $('action').value;
    if (action === 'void' || action === 'cancel') return;
    updateNet();
    if (action === 'save' && mode === 'view') { e.preventDefault(); alert('Click Edit to change this document.'); return; }
    if (action === 'save' && !items.length) { e.preventDefault(); alert('Add at least one item.'); }
  });

  setMode(mode);
  customerInfo();
})();
