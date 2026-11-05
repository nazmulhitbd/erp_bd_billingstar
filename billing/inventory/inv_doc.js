/* Document screen behaviour for inv_doc_engine.php. Reads its settings from window.INV. */
(function () {
  const C = window.INV, $ = id => document.getElementById(id), has = f => C.lines.includes(f);
  const items = (C.items || []).map(normalise);
  const costDocs = ['purchase', 'purchase_order', 'stock_in', 'price_amendment', 'requisition', 'stock_out', 'stock_transfer'];
  let picked = null, editingSource = null, showCost = false, showPrice = false, priceEdit = false;

  function normalise(l) {
    return {
      product_id: Number(l.product_id || 0), product_code: l.product_code || '', product_group: l.product_group || '', brand: l.brand || '',
      model_caption: l.model_caption || '', description: l.description || '', unit_name: l.unit_name || 'Pcs', warranty: l.warranty || '',
      store_name: l.store_name || '', store_to: l.store_to || '', quantity: Number(l.quantity || 0), free_quantity: Number(l.free_quantity || 0),
      unit_price: Number(l.unit_price || 0), prev_price: Number(l.prev_price || 0), discount_amount: Number(l.discount_amount || 0),
      narration: l.narration || '', serials: Array.isArray(l.serials) ? l.serials : String(l.serials || '').split(/[\r\n,]+/).map(s => s.trim()).filter(Boolean),
      source_kind: l.source_kind || '', source_line_id: Number(l.source_line_id || 0)
    };
  }
  const lineTotal = l => has('prev_price') ? Math.round(l.quantity * (l.unit_price - l.prev_price) * 100) / 100 : Math.max(0, Math.round((l.quantity * l.unit_price - l.discount_amount) * 100) / 100);

  /* ---------- Stores ---------- */
  ['e_store', 'e_store_to'].forEach(id => {
    const sel = $(id); if (!sel) return;
    sel.innerHTML = '<option value="">Pick store</option>' + (id === 'e_store_to' ? C.stores_to : C.stores).map(s => `<option>${safe(s)}</option>`).join('');
    if (id === 'e_store' && C.defaultStore && C.stores.includes(C.defaultStore)) sel.value = C.defaultStore;
    sel.addEventListener('change', availability);
  });

  /* ---------- Product picker ---------- */
  const mIn = $('e_model');
  attachSuggest(mIn, $('product-suggestions'), C.products, ['model_caption', 'product_code', 'description', 'brand', 'product_group'],
    p => `${p.product_code || ''} ${p.model_caption || ''} · ${p.description}`.trim(), pickProduct);
  mIn.addEventListener('input', () => { picked = null; });
  function pickProduct(p) {
    picked = p; mIn.value = p.model_caption || p.description;
    $('e_group').value = p.product_group || ''; $('e_brand').value = p.brand || ''; $('e_desc').value = p.description || ''; $('e_unit').value = p.unit_name || 'Pcs';
    if ($('e_warranty')) $('e_warranty').value = p.warranty || '';
    if ($('e_price') && !has('prev_price')) $('e_price').value = costDocs.includes(C.type) ? Number(p.last_cost || 0) : Number(p.mrp || p.default_unit_price || 0);
    availability(); info(); $('e_qty').focus();
  }
  async function availability() {
    const out = $('qty-available'); if (!out) return;
    if (!picked || C.stock === 'none' && !['challan_bill', 'quotation'].includes(C.type)) { out.textContent = ''; return; }
    const store = $('e_store') ? $('e_store').value : '';
    try { const r = await fetch(`${C.self}?avail=${picked.product_id}&store=${encodeURIComponent(store)}`, { credentials: 'same-origin' }); const j = await r.json();
      out.textContent = 'Qty Available: ' + qtyFmt(j.qty) + (C.lastInfo === 'serial' ? `   Last Serial: ${j.last_serial || '-'}` : (C.lastInfo ? `   Last Price: ${money(j.last_price)}   Last Serial: ${j.last_serial || '-'}` : '')); } catch (e) { out.textContent = ''; }
  }
  function info() {
    const out = $('cost-info'); if (!out) return; const p = picked;
    out.textContent = !p ? '' : [showCost ? 'Last Cost: ' + money(p.last_cost) : '', showPrice ? `MRP ${money(p.mrp)} · Retail ${money(p.retail_a)} · Dealer ${money(p.dealer_a)}` : ''].filter(Boolean).join(' · ');
  }
  $('show-cost')?.addEventListener('click', () => { showCost = !showCost; info(); });
  $('show-price')?.addEventListener('click', () => { showPrice = !showPrice; info(); });

  /* ---------- Party picker ---------- */
  function fillParty(p) {
    $('party_id').value = p.id || '';
    const set = (id, v) => { if ($(id) && v !== undefined) $(id).value = v || ''; };
    set('party_name', p.name); set('party_group', p.grp); set('mobile', p.mobile); set('address', p.address); set('attention', p.attention);
  }
  if ($('party_name') && C.parties.length) {
    attachSuggest($('party_name'), $('party-suggestions'), C.parties, ['name', 'mobile', 'grp'], p => `${p.name} · ${p.mobile || ''}`, fillParty);
    $('party_name').addEventListener('input', () => { $('party_id').value = ''; });
  }
  $('mobile-search')?.addEventListener('click', () => {
    const m = $('mobile').value.trim(); if (!m) { alert('Enter a mobile number.'); return; }
    const p = C.parties.find(x => String(x.mobile || '').replace(/\D/g, '').endsWith(m.replace(/\D/g, '')));
    p ? fillParty(p) : alert('No ' + (C.party === 'supplier' ? 'supplier' : 'customer') + ' with this mobile number.');
  });

  /* ---------- Serials ---------- */
  function serialMode() { const r = document.querySelector('input[name="sq"]:checked'); return r ? r.value : 'none'; }
  function buildSerials(qty) {
    const mode = serialMode(), common = ($('s_common')?.value || '').trim();
    let list = [];
    if (mode === 'list') list = ($('e_serial').value || '').split(/[\r\n,]+/).map(s => s.trim()).filter(Boolean);
    if (mode === 'range') {
      const f = ($('s_from').value || '').trim(), t = ($('s_to').value || '').trim();
      if (!/^\d+$/.test(f) || !/^\d+$/.test(t)) throw new Error('Serial From and To must be numbers.');
      const a = BigInt(f), b = BigInt(t); if (b < a) throw new Error('Serial To must not be below Serial From.');
      if (b - a > 5000n) throw new Error('A serial range can hold at most 5000 serials.');
      for (let n = a; n <= b; n++) list.push(common + n.toString().padStart(f.length, '0'));
    }
    if (mode === 'auto') {
      if (!(qty > 0)) throw new Error('Enter the quantity to generate serials for.');
      const d = new Date(), stamp = d.getFullYear().toString().slice(2) + String(d.getMonth() + 1).padStart(2, '0') + String(d.getDate()).padStart(2, '0') + String(d.getHours()).padStart(2, '0') + String(d.getMinutes()).padStart(2, '0') + String(d.getSeconds()).padStart(2, '0');
      for (let i = 1; i <= qty; i++) list.push((common || 'AG') + stamp + String(i).padStart(4, '0'));
    }
    list = [...new Set(list)];
    if ($('digit_check')?.checked) {
      const len = Number($('digit_len').value || 0), bad = list.find(s => s.length !== len);
      if (len > 0 && bad) throw new Error(`Serial ${bad} is not ${len} characters long.`);
    }
    return list;
  }

  /* ---------- Grid ---------- */
  const compact = has('compact_grid');
  let selected = -1;
  const cols = [['SL'], ['Product ID'], ['Model No/Caption']]; if (!compact) cols.push(['Description']);
  if (has('store')) cols.push(['Store Name']); if (has('store_to')) cols.push(['Store Name To']);
  if (has('source_col') && C.source) cols.push([C.source.field]);
  if (has('narration') && !compact) cols.push(['Narration']); cols.push(['Quantity', 1]); if (has('free')) cols.push(['Free', 1]); cols.push(['Unit Name']);
  if (has('prev_price')) cols.push(['Previous Cost', 1]); if (C.amounts) cols.push([has('prev_price') ? 'Current Cost' : 'Unit Price', 1]);
  if (has('prev_price')) cols.push(['Variation Cost', 1]); if (has('discount')) cols.push(['Discount', 1]); if (C.amounts) cols.push(['Amount', 1]);
  cols.push(['Select']); if (compact) cols.push(['Edit']); cols.push(['Delete']);
  $('grid-head').innerHTML = cols.map(([c, n]) => `<th${n ? ' class="num"' : ''}>${c}</th>`).join('');

  function render() {
    const b = $('grid-body');
    b.innerHTML = items.length ? '' : `<tr><td colspan="${cols.length}" class="empty">No items yet.</td></tr>`;
    items.forEach((l, i) => {
      const cells = [i + 1, safe(l.product_id || ''), safe(l.model_caption || l.description)];
      if (!compact) cells.push(safe(l.description) + (l.serials.length ? `<br><span class="muted">${l.serials.length} serial(s)</span>` : '') + (l.source_line_id ? ' <span class="badge">from source</span>' : ''));
      if (has('store')) cells.push(safe(l.store_name)); if (has('store_to')) cells.push(safe(l.store_to));
      if (has('source_col') && C.source) cells.push(l.source_line_id ? safe($('source_no')?.value || '') : '');
      if (has('narration') && !compact) cells.push(safe(l.narration));
      cells.push(`<span class="num">${qtyFmt(l.quantity)}</span>`); if (has('free')) cells.push(qtyFmt(l.free_quantity)); cells.push(safe(l.unit_name));
      // Edit Price: unit price and discount become inputs in the grid.
      const priceCell = priceEdit && !C.readOnly ? `<input type="number" min="0" step="0.01" data-price="${i}" value="${l.unit_price}" style="width:100px;text-align:right">` : money(l.unit_price);
      const discCell = priceEdit && !C.readOnly ? `<input type="number" min="0" step="0.01" data-disc="${i}" value="${l.discount_amount}" style="width:90px;text-align:right">` : money(l.discount_amount);
      if (has('prev_price')) cells.push(money(l.prev_price)); if (C.amounts) cells.push(priceCell); if (has('prev_price')) cells.push(money(l.unit_price - l.prev_price));
      if (has('discount')) cells.push(discCell); if (C.amounts) cells.push(`<span data-total="${i}">${money(lineTotal(l))}</span>`);
      // Compact grid: Select marks the row, Edit loads it into the entry row; otherwise Select loads it.
      if (compact) cells.push(`<button type="button" class="btn small" data-mark="${i}">Select</button>`);
      cells.push(`<button type="button" class="btn small" data-pick="${i}" ${C.readOnly ? 'disabled' : ''}>${compact ? 'Edit' : 'Select'}</button>`, `<button type="button" class="btn small danger" data-del="${i}" ${C.readOnly ? 'disabled' : ''}>Delete</button>`);
      b.insertAdjacentHTML('beforeend', (i === selected ? '<tr class="selected-row">' : '<tr>') + cells.map((c, k) => `<td${cols[k] && cols[k][1] ? ' class="num"' : ''}>${c}</td>`).join('') + '</tr>');
    });
    totals();
  }
  $('grid-body').addEventListener('input', e => {
    const p = e.target.dataset.price, d = e.target.dataset.disc, i = p !== undefined ? +p : (d !== undefined ? +d : -1);
    if (i < 0) return;
    if (p !== undefined) items[i].unit_price = Number(e.target.value || 0); else items[i].discount_amount = Number(e.target.value || 0);
    const t = document.querySelector(`[data-total="${i}"]`); if (t) t.textContent = money(lineTotal(items[i]));
    $('lines_json').value = JSON.stringify(items); updateNet();
  });
  $('edit-price')?.addEventListener('click', () => { priceEdit = !priceEdit; $('edit-price').classList.toggle('primary', priceEdit); render(); });
  $('grid-body').addEventListener('click', e => {
    const m = e.target.closest('[data-mark]'); if (m) { selected = selected === +m.dataset.mark ? -1 : +m.dataset.mark; render(); return; }
    const d = e.target.closest('[data-del]'); if (d) { items.splice(+d.dataset.del, 1); selected = -1; render(); return; }
    const s = e.target.closest('[data-pick]'); if (!s) return;
    const l = items.splice(+s.dataset.pick, 1)[0];
    picked = C.products.find(p => Number(p.product_id) === l.product_id) || (l.product_id ? { product_id: l.product_id } : null);
    editingSource = l.source_line_id ? { source_kind: l.source_kind, source_line_id: l.source_line_id } : null;
    mIn.value = l.model_caption || l.description; $('e_group').value = l.product_group; $('e_brand').value = l.brand; $('e_desc').value = l.description; $('e_unit').value = l.unit_name;
    if ($('e_store')) $('e_store').value = l.store_name; if ($('e_store_to')) $('e_store_to').value = l.store_to; if ($('e_warranty')) $('e_warranty').value = l.warranty; if ($('e_narr')) $('e_narr').value = l.narration;
    $('e_qty').value = l.quantity; if ($('e_free')) $('e_free').value = l.free_quantity; if ($('e_price')) $('e_price').value = l.unit_price; if ($('e_prev')) $('e_prev').value = l.prev_price; if ($('e_disc')) $('e_disc').value = l.discount_amount;
    if ($('e_serial')) { $('e_serial').value = l.serials.join('\n'); if (l.serials.length) document.querySelector('input[name="sq"][value="list"]').checked = true; }
    render(); availability(); $('e_qty').focus();
  });

  $('add-line')?.addEventListener('click', () => {
    let qty = Number($('e_qty').value || 0); const free = Number($('e_free')?.value || 0), desc = $('e_desc').value.trim() || (picked ? picked.description : '');
    let serials = [];
    try { serials = has('serials') ? buildSerials(qty + free) : []; } catch (err) { alert(err.message); return; }
    if (serials.length && !(qty + free > 0)) { qty = serials.length; $('e_qty').value = qty; }
    if (!desc || qty + free <= 0) { alert('Pick a product and enter a quantity.'); return; }
    if (C.stock !== 'none' && !(picked && picked.product_id)) { alert('Pick the product from the list so stock can be tracked.'); return; }
    if (has('store') && C.stock !== 'none' && !$('e_store').value) { alert('Pick a store.'); return; }
    if (has('store_to') && (!$('e_store_to').value || $('e_store_to').value === $('e_store').value)) { alert('Pick a different Store To.'); return; }
    if (serials.length && serials.length !== Math.round(qty + free)) { alert(`You entered ${serials.length} serial(s) for a quantity of ${qty + free}. They must match.`); return; }
    const p = picked || {};
    items.push(normalise({
      product_id: p.product_id || 0, product_code: p.product_code || '', product_group: $('e_group').value, brand: $('e_brand').value, model_caption: mIn.value.trim(),
      description: desc, unit_name: $('e_unit').value || 'Pcs', warranty: $('e_warranty')?.value || '', store_name: $('e_store')?.value || '', store_to: $('e_store_to')?.value || '',
      quantity: qty, free_quantity: free, unit_price: Number($('e_price')?.value || 0), prev_price: Number($('e_prev')?.value || 0), discount_amount: Number($('e_disc')?.value || 0),
      narration: $('e_narr')?.value || '', serials, ...(editingSource || {})
    }));
    picked = null; editingSource = null;
    ['e_model', 'e_group', 'e_brand', 'e_desc', 'e_warranty', 'e_narr', 'e_qty', 'e_price', 'e_prev'].forEach(id => { if ($(id)) $(id).value = ''; });
    if ($('e_free')) $('e_free').value = 0; if ($('e_disc')) $('e_disc').value = 0; $('e_unit').value = 'Pcs';
    if ($('serial_clear')?.checked) ['e_serial', 's_from', 's_to'].forEach(id => { if ($(id)) $(id).value = ''; });
    $('qty-available') && ($('qty-available').textContent = ''); render(); mIn.focus();
  });

  /* ---------- Totals ---------- */
  const tVal = k => Number($('t_' + k)?.value || $('h_' + k)?.value || 0);
  function totals() {
    const box = $('totals'); let sub = 0, qty = 0, prevAmt = 0, newAmt = 0;
    items.forEach(l => { sub += lineTotal(l); qty += l.quantity + l.free_quantity; prevAmt += l.quantity * l.prev_price; newAmt += l.quantity * l.unit_price; });
    const keep = {}; ['extra', 'discount', 'vat'].forEach(k => keep[k] = tVal(k));
    let rows = '';
    if (has('prev_price')) rows = `<tr><th>Previous Amount</th><td class="num">${money(prevAmt)}</td></tr><tr><th>New Amount</th><td class="num">${money(newAmt)}</td></tr><tr><th>Variation Amount</th><td class="num">${money(newAmt - prevAmt)}</td></tr>`;
    else if (C.amounts) {
      rows = `<tr><th>Total Amount</th><td class="num">${money(sub)}</td></tr>`;
      const labels = { extra: 'Add Extra Charges', discount: 'Less Discount', vat: 'Add VAT & AIT' };
      C.totals.forEach(k => rows += `<tr><th>${labels[k]}</th><td><input id="t_${k}" type="number" min="0" step="0.01" value="${keep[k]}" ${C.readOnly ? 'readonly' : ''}></td></tr>`);
      const net = Math.max(0, sub + keep.extra + keep.vat - keep.discount);
      rows += `<tr><th>Net Payable Amount</th><td class="num"><b id="t_net">${money(net)}</b></td></tr>`;
    }
    rows += `<tr><th>Total Quantity</th><td class="num">${qtyFmt(qty)}</td></tr>`;
    box.innerHTML = rows;
    C.totals.forEach(k => $('t_' + k)?.addEventListener('input', () => { $('h_' + k).value = $('t_' + k).value; updateNet(); }));
    updateNet();
    $('lines_json').value = JSON.stringify(items);
  }
  function updateNet() {
    if (!C.amounts || has('prev_price')) return;
    let sub = 0; items.forEach(l => sub += lineTotal(l));
    const net = Math.max(0, sub + tVal('extra') + tVal('vat') - tVal('discount'));
    if ($('t_net')) $('t_net').textContent = money(net);
    if ($('change-amount')) $('change-amount').textContent = money(Math.max(0, Number($('t_cash').value || 0) - net));
  }
  $('t_cash')?.addEventListener('input', updateNet);

  /* ---------- Source document ---------- */
  const modeSel = $('source_mode');
  function syncMode() { if (!modeSel) return; const direct = modeSel.value === 'direct'; $('source_no').disabled = direct; $('source-load').disabled = direct || C.readOnly; if (direct) $('source_no').value = ''; }
  modeSel?.addEventListener('change', syncMode); syncMode();
  async function loadSource() {
    const no = $('source_no').value.trim(); if (!no) { alert('Enter the ' + C.source.field + '.'); $('source_no').focus(); return; }
    try {
      const r = await fetch(`${C.self}?source=${encodeURIComponent(no)}&exclude=${C.docId}`, { credentials: 'same-origin' }); const j = await r.json();
      if (!r.ok) { alert(j.error || 'Could not load.'); return; }
      const h = j.header; $('source_no').value = h.doc_no;
      if (C.party !== 'none' && !$('party_name').value) fillParty({ id: h.party_id, name: h.party_name, grp: h.party_group, mobile: h.mobile, address: h.address, attention: h.attention });
      ['sales_person', 'delivery_point'].forEach(k => { if ($(k) && !$(k).value && h[k]) $(k).value = h[k]; });
      if ($('source_order')) $('source_order').value = h.source_no || '';
      for (let i = items.length - 1; i >= 0; i--) if (items[i].source_line_id) items.splice(i, 1);
      let added = 0;
      j.lines.forEach(l => {
        const open = Math.round(Number(l.remaining) * 1000) / 1000; if (!(open > 0)) return;
        const row = normalise(l); row.quantity = open; row.free_quantity = 0; row.discount_amount = 0;
        // Keep the source's serials when the whole line is taken (e.g. billing or returning a full challan line); otherwise enter the ones being moved.
        if (!has('serials') || row.serials.length !== Math.round(open)) row.serials = [];
        if (C.source.mode === 'all') { row.prev_price = Number(l.unit_price); }
        if (C.stock !== 'none' && !row.store_name) row.store_name = C.defaultStore || '';
        items.push(row); added++;
      });
      render(); if (!added) alert('Nothing is left open on ' + h.doc_no + '.');
    } catch (e) { alert('Could not load the source document.'); }
  }
  $('source-load')?.addEventListener('click', loadSource);
  // Opened with ?source_no= (e.g. Edit Price on a purchase): load that document straight away.
  const preset = new URLSearchParams(location.search).get('source_no');
  if (preset && $('source_no') && !C.docId && !C.readOnly) { if (modeSel) { modeSel.value = 'source'; syncMode(); } $('source_no').value = preset; loadSource(); }
  $('add-all')?.addEventListener('click', loadSource);

  /* ---------- Serial view, import, submit ---------- */
  $('serial-view')?.addEventListener('click', () => {
    const lines = items.filter(l => l.serials.length).map(l => `${l.model_caption || l.description} (${l.serials.length}):\n  ${l.serials.join(', ')}`);
    alert(lines.length ? lines.join('\n\n') : 'No serials on this document.');
  });
  $('import-file')?.addEventListener('change', e => {
    const f = e.target.files[0]; if (!f) return; const rd = new FileReader();
    rd.onload = () => {
      let ok = 0; const miss = [];
      String(rd.result).split(/\r?\n/).forEach((row, n) => {
        const c = row.split(',').map(x => x.trim().replace(/^"|"$/g, '')); if (!c[0] || (n === 0 && /code|model/i.test(c[0]))) return;
        const p = C.products.find(x => x.product_code === c[0] || x.model_caption === c[0]); if (!p) { miss.push(c[0]); return; }
        const serials = (C.amounts ? c[3] : c[2] || '').split('|').map(s => s.trim()).filter(Boolean);
        items.push(normalise({ ...p, quantity: Number(c[1] || serials.length || 0), unit_price: C.amounts ? Number(c[2] || p.last_cost || p.default_unit_price || 0) : 0, store_name: $('e_store')?.value || C.defaultStore || '', store_to: $('e_store_to')?.value || '', serials })); ok++;
      });
      render(); e.target.value = ''; alert(`${ok} line(s) imported.` + (miss.length ? ` Not found: ${miss.slice(0, 10).join(', ')}` : ''));
    };
    rd.readAsText(f);
  });
  $('docform').addEventListener('submit', e => {
    if (e.submitter && e.submitter.value === 'cancel') return;
    totals();
    if (!items.length) { e.preventDefault(); alert('Add at least one item.'); }
  });
  render();
})();
