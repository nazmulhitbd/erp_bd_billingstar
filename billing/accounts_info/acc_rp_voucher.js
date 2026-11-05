/* Receive / Payment voucher screen. Settings come from window.RP. */
(function () {
  const C = window.RP, $ = id => document.getElementById(id);
  const byKey = Object.fromEntries(C.parts.map(p => [p.key, p]));
  const rows = C.entries.map(e => {
    const p = byKey[(e.sub_ledger_id ? 's:' + e.sub_ledger_id : 'a:' + e.account_id)] || { name: 'Inactive account', sub1: '', sub2: '', account_id: e.account_id, sub_ledger_id: e.sub_ledger_id };
    return { ...p, amount: Number(e.amount), transaction_mode: e.transaction_mode || 'Cash', cheque_no: e.cheque_no || '', cheque_date: e.cheque_date || '', invoice_no: e.invoice_no || '', line_narration: e.line_narration || '' };
  });
  let picked = null;
  function showUnder() { const a = C.cashBank.find(x => String(x.id) === $('under_account').value); $('under_group').value = a ? a.group : ''; $('under_name').value = a ? a.name : ''; }
  $('under_account').addEventListener('change', () => { showUnder(); render(); }); showUnder();

  const view = { filter: f => inGroup().filter(f), slice: (a, b) => inGroup().slice(a, b) }; /* re-reads the Group Name filter on each keystroke */
  function inGroup() { const g = $('e_group').value.trim().toLowerCase(); return g ? C.parts.filter(p => String(p.sub1).toLowerCase() === g) : C.parts; }
  attachSuggest($('e_part'), $('part-suggestions'), view, ['name', 'code', 'sub1', 'sub2'], p => `${p.name} · ${p.code} (${p.sub2 || p.sub1})`, p => { picked = p; $('e_part').value = p.name; if (!$('e_group').value) $('e_group').value = p.sub1; loadInvoices(); $('e_amount').focus(); });
  $('e_part').addEventListener('input', () => { picked = null; });

  $('cheque_adjust').addEventListener('change', e => { $('cheque-row').hidden = !e.target.checked; });
  $('invoice_list').addEventListener('change', e => { $('invoice-row').hidden = !e.target.checked; loadInvoices(); });
  async function loadInvoices() {
    const sel = $('e_invoice'); sel.innerHTML = '<option value="">On account (no invoice)</option>';
    if (!$('invoice_list').checked || !picked || !picked.sub_ledger_id) return;
    try {
      const r = await fetch(`${C.self}&invoices=${picked.sub_ledger_id}`, { credentials: 'same-origin' }); const list = await r.json();
      list.forEach(i => sel.insertAdjacentHTML('beforeend', `<option value="${safe(i.invoice_no)}" data-due="${i.balance_amount}">${safe(i.invoice_no)} · due ${money(i.balance_amount)}</option>`));
      if (!list.length) sel.insertAdjacentHTML('beforeend', '<option disabled>No open invoices for this party</option>');
    } catch (e) { /* the list is optional */ }
  }
  $('e_invoice').addEventListener('change', e => { const due = e.target.selectedOptions[0]?.dataset.due; if (due && !$('e_amount').value) $('e_amount').value = Number(due).toFixed(2); });

  function render() {
    const b = $('rows'), under = $('under_name').value; let t = 0;
    b.innerHTML = rows.length ? '' : '<tr><td colspan="13" class="empty">No rows yet. Pick the Particulars and Amount, then Add.</td></tr>';
    rows.forEach((r, i) => {
      t += r.amount;
      b.insertAdjacentHTML('beforeend', `<tr><td>${i + 1}</td><td>${safe(under)}</td><td>${safe(r.transaction_mode)}</td><td>${safe(r.cheque_no)}</td><td>${safe(r.cheque_date)}</td><td>${safe(r.name)}</td><td>${safe(r.sub1)}</td><td>${safe(r.sub2)}</td><td>${safe(r.line_narration)}</td><td>${safe(r.invoice_no)}</td><td class="num">${money(r.amount)}</td>`
        + `<td><button type="button" class="btn small" data-pick="${i}" ${C.readOnly ? 'disabled' : ''}>Select</button></td><td><button type="button" class="btn small danger" data-del="${i}" ${C.readOnly ? 'disabled' : ''}>Delete</button></td></tr>`);
    });
    $('total').textContent = money(t);
    $('entries_json').value = JSON.stringify(rows.map(r => ({ account_id: r.account_id, sub_ledger_id: r.sub_ledger_id, amount: r.amount, transaction_mode: r.transaction_mode, cheque_no: r.cheque_no, cheque_date: r.cheque_date, invoice_no: r.invoice_no, line_narration: r.line_narration })));
  }
  $('add-row').addEventListener('click', () => {
    const amount = Math.round(Number($('e_amount').value || 0) * 100) / 100;
    if (!picked) { alert('Pick the Particulars from the list.'); $('e_part').focus(); return; }
    if (amount <= 0) { alert('Enter the amount.'); $('e_amount').focus(); return; }
    const cheque = $('cheque_adjust').checked, mode = cheque ? $('e_mode').value : 'Cash';
    if (mode === 'Cheque' && !$('e_cheque_no').value.trim()) { alert('Enter the cheque number.'); $('e_cheque_no').focus(); return; }
    rows.push({ ...picked, amount, transaction_mode: mode, cheque_no: cheque ? $('e_cheque_no').value.trim() : '', cheque_date: cheque ? $('e_cheque_date').value : '', invoice_no: $('invoice_list').checked ? $('e_invoice').value : '', line_narration: $('narration').value.trim() });
    picked = null; $('e_part').value = ''; $('e_amount').value = ''; $('e_cheque_no').value = ''; $('e_invoice').value = '';
    render(); $('e_part').focus();
  });
  $('rows').addEventListener('click', e => {
    const d = e.target.closest('[data-del]'); if (d) { rows.splice(+d.dataset.del, 1); render(); return; }
    const s = e.target.closest('[data-pick]'); if (!s) return;
    const r = rows.splice(+s.dataset.pick, 1)[0]; picked = byKey[r.key] || r;
    $('e_part').value = r.name; $('e_group').value = r.sub1; $('e_amount').value = r.amount.toFixed(2);
    const cheque = r.transaction_mode !== 'Cash'; $('cheque_adjust').checked = cheque; $('cheque-row').hidden = !cheque;
    if (cheque) { $('e_mode').value = r.transaction_mode; $('e_cheque_no').value = r.cheque_no; $('e_cheque_date').value = r.cheque_date; }
    if (r.invoice_no) { $('invoice_list').checked = true; $('invoice-row').hidden = false; loadInvoices().then(() => { if (![...$('e_invoice').options].some(o => o.value === r.invoice_no)) $('e_invoice').insertAdjacentHTML('beforeend', `<option>${safe(r.invoice_no)}</option>`); $('e_invoice').value = r.invoice_no; }); }
    render();
  });
  $('balance-btn').addEventListener('click', async () => {
    const out = $('balance-out'); const key = picked ? picked.key : 'a:' + $('under_account').value; const name = picked ? picked.name : $('under_name').value;
    try { const r = await fetch('acc_voucher.php?type=journal&balance=' + encodeURIComponent(key), { credentials: 'same-origin' }); const j = await r.json(); out.textContent = `${name} balance: ${j.balance}`; } catch (e) { out.textContent = 'Balance could not be loaded.'; }
  });
  $('rpform').addEventListener('submit', e => { render(); if (!rows.length) { e.preventDefault(); alert('Add at least one row.'); } });
  render();
})();
