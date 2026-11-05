(function () {
  'use strict';

  const $ = (sel, root) => (root || document).querySelector(sel);
  const $$ = (sel, root) => Array.from((root || document).querySelectorAll(sel));
  const esc = v => String(v ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[c]));
  const money = v => (Number(v) || 0).toFixed(2);

  /* ---------------------------------------------------------------- modal */
  const modal = {
    open(title, bodyHtml, buttons) {
      const box = $('#rma-modal');
      $('#rma-modal-title').textContent = title;
      $('#rma-modal-body').innerHTML = bodyHtml;
      const foot = $('#rma-modal-foot');
      foot.innerHTML = '';
      (buttons || [{ label: 'Close' }]).forEach(b => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'rma-btn';
        btn.textContent = b.label;
        btn.addEventListener('click', () => { modal.close(); if (b.onClick) b.onClick(); });
        foot.appendChild(btn);
      });
      box.hidden = false;
      foot.querySelector('button')?.focus();
    },
    close() { $('#rma-modal').hidden = true; }
  };
  document.addEventListener('click', e => { if (e.target.closest('[data-close]') || e.target.id === 'rma-modal') modal.close(); });
  document.addEventListener('keydown', e => { if (e.key === 'Escape') modal.close(); });

  const confirmBox = (message, onYes) => modal.open('Confirm Message', '<p>' + esc(message) + '</p>', [{ label: 'Yes', onClick: onYes }, { label: 'No' }]);
  const alertBox = message => modal.open('Message', '<p>' + esc(message) + '</p>', [{ label: 'OK' }]);

  /* ---------------------------------------------------------------- lookups */
  let dropdown = null;
  function closeDropdown() { if (dropdown) { dropdown.remove(); dropdown = null; } }
  document.addEventListener('click', e => { if (dropdown && !e.target.closest('.rma-dropdown,[data-lookup],[data-ac]')) closeDropdown(); });

  function setFieldValue(el, value) {
    if (!el) return;
    if (el.dataset.radioHolder) {
      el.value = value ?? '';
      const radio = $$('input[data-radio="' + el.dataset.field + '"][data-scope="' + el.id.charAt(0) + '"]').find(r => r.value === String(value));
      if (radio) radio.checked = true;
      return;
    }
    el.value = value ?? '';
    el.dispatchEvent(new Event('change', { bubbles: true }));
  }

  function fill(row) {
    Object.keys(row).forEach(key => {
      if (key.charAt(0) === '_') return;
      ['h_', 'i_'].forEach(prefix => {
        const el = document.getElementById(prefix + key);
        if (el && !el.disabled) setFieldValue(el, row[key]);
      });
    });
    updateDuration();
  }

  function lookup(kind, mode, input) {
    const url = location.pathname + '?ajax=lookup&kind=' + encodeURIComponent(kind) + '&mode=' + encodeURIComponent(mode || '') + '&q=' + encodeURIComponent(input.value.trim());
    fetch(url, { credentials: 'same-origin' })
      .then(r => r.json())
      .then(rows => {
        closeDropdown();
        if (!Array.isArray(rows)) { alertBox(rows.error || 'Search failed.'); return; }
        dropdown = document.createElement('div');
        dropdown.className = 'rma-dropdown';
        if (!rows.length) dropdown.innerHTML = '<div class="empty">No match found.</div>';
        rows.forEach(row => {
          const b = document.createElement('button');
          b.type = 'button';
          b.textContent = row._label || '';
          b.addEventListener('click', () => { fill(row); closeDropdown(); });
          dropdown.appendChild(b);
        });
        const rect = input.getBoundingClientRect();
        dropdown.style.left = (rect.left + window.scrollX) + 'px';
        dropdown.style.top = (rect.bottom + window.scrollY) + 'px';
        dropdown.style.minWidth = Math.max(rect.width, 320) + 'px';
        document.body.appendChild(dropdown);
      })
      .catch(() => alertBox('Search failed. Check your connection and try again.'));
  }

  document.addEventListener('click', e => {
    const btn = e.target.closest('[data-lookup]');
    if (!btn) return;
    lookup(btn.dataset.lookup, btn.dataset.mode, document.getElementById(btn.dataset.for));
  });
  let acTimer = null;
  document.addEventListener('input', e => {
    const el = e.target.closest('[data-ac]');
    if (!el) return;
    clearTimeout(acTimer);
    if (el.value.trim().length < 2) { closeDropdown(); return; }
    acTimer = setTimeout(() => lookup(el.dataset.ac, '', el), 250);
  });
  document.addEventListener('change', e => {
    const r = e.target.closest('[data-radio]');
    if (r) {
      const holder = document.getElementById(r.dataset.scope + '_' + r.dataset.radio);
      if (holder) holder.value = r.value;
    }
  });

  /* ---------------------------------------------------------------- warranty duration (Complain Receive) */
  function updateDuration() {
    const out = $('#i_duration');
    const sold = $('#i_sales_date');
    const complain = $('#h_complain_date');
    if (!out || !sold || !complain) return;
    if (!sold.value || !complain.value) { out.value = '0Y-0M-0D'; return; }
    const a = new Date(sold.value + 'T00:00:00');
    const b = new Date(complain.value + 'T00:00:00');
    if (b < a) { out.value = '0Y-0M-0D'; return; }
    let y = b.getFullYear() - a.getFullYear();
    let m = b.getMonth() - a.getMonth();
    let d = b.getDate() - a.getDate();
    if (d < 0) { m -= 1; d += new Date(b.getFullYear(), b.getMonth(), 0).getDate(); }
    if (m < 0) { y -= 1; m += 12; }
    out.value = y + 'Y-' + m + 'M-' + d + 'D';
  }
  document.addEventListener('change', e => { if (['i_sales_date', 'h_complain_date'].includes(e.target.id)) updateDuration(); });

  /* ---------------------------------------------------------------- document page */
  function initDocument(cfg) {
    const items = cfg.items || [];
    let selected = -1;
    const form = $('#rma-form');
    const lineFields = $$('#rma-line [data-field]');
    const pageUrl = cfg.page;

    function compute(item) {
      if ('adjustment_type' in item) {
        const amt = Number(item.adj_amount) || 0;
        item.receivable = ['receivable', 'product_receivable'].includes(item.adjustment_type) ? amt : 0;
        item.payable = ['payable', 'product_payable'].includes(item.adjustment_type) ? amt : 0;
      }
      if ('service_charge' in item) {
        item.amount = Math.max(0, (Number(item.service_charge) || 0) + (Number(item.parts_charge) || 0) - (Number(item.discount) || 0));
      }
      return item;
    }

    function readLine() {
      const item = {};
      lineFields.forEach(el => { item[el.dataset.field] = el.value; });
      return compute(item);
    }

    function writeLine(item) {
      lineFields.forEach(el => setFieldValue(el, item[el.dataset.field] ?? el.dataset.def));
      updateDuration();
    }

    function clearLine() {
      lineFields.forEach(el => setFieldValue(el, el.dataset.def));
      selected = -1;
      render();
      updateDuration();
    }

    function render() {
      const table = $('#rma-items');
      const cols = cfg.grid;
      let head = '<tr><th>SL</th>' + cols.map(c => '<th class="' + (c[2] === 'num' ? 'num' : '') + '">' + esc(c[1]) + '</th>').join('') + '<th>Select</th><th>Delete</th></tr>';
      table.tHead.innerHTML = head;
      if (!items.length) {
        table.tBodies[0].innerHTML = '<tr><td colspan="' + (cols.length + 3) + '" class="empty">No records added yet.</td></tr>';
      } else {
        table.tBodies[0].innerHTML = items.map((it, i) => '<tr class="' + (i === selected ? 'sel' : '') + '"><td>' + (i + 1) + '</td>'
          + cols.map(c => c[2] === 'num' ? '<td class="num">' + money(it[c[0]]) + '</td>' : '<td>' + esc(it[c[0]]) + '</td>').join('')
          + '<td><button type="button" class="rma-btn" data-sel="' + i + '"' + '>Select</button></td>'
          + '<td><button type="button" class="rma-btn" data-del="' + i + '"' + (cfg.locked ? ' disabled' : '') + '>Delete</button></td></tr>').join('');
      }
      cfg.totals.forEach(col => {
        const key = col.replace('total_', '');
        const el = document.getElementById('tot-' + col);
        if (el) el.textContent = money(items.reduce((s, it) => s + (Number(it[key]) || 0), 0));
      });
    }

    function submit(action) {
      if (action === 'save' && !form.reportValidity()) return;
      $('#rma-action').value = action;
      $('#rma-items-json').value = JSON.stringify(items);
      // Disabled inputs are not posted; nothing to unlock because locked docs never submit.
      form.submit();
    }

    function lineReady(item) {
      if (cfg.lineRequired && !String(item[cfg.lineRequired] || '').trim()) return false;
      return true;
    }

    function act(name) {
      if (name === 'prev' || name === 'next') { location.href = pageUrl + '?nav=' + name + '&id=' + cfg.docId; return; }
      if (name === 'clear') { clearLine(); return; }
      if (name === 'preview' || name === 'print') {
        if (!cfg.docId) { alertBox('Save the record first.'); return; }
        window.open(pageUrl + '?print=' + cfg.docId + (name === 'print' ? '&auto=1' : ''), '_blank');
        return;
      }
      if (name === 'serials') { showSerials(); return; }
      if (cfg.locked) { alertBox('This record is approved and cannot be changed.'); return; }
      if (name === 'settle') { confirmBox('Are you sure want to settle this record?', () => submit('settle')); return; }
      if (name === 'delete-doc') { confirmBox('Are you sure want to delete this record?', () => submit('delete')); return; }

      if (!cfg.hasItems) {
        // Header-only document: Add saves a new record, Edit updates the loaded one.
        if (name === 'add') $('#rma-doc-id').value = '0';
        if (name === 'edit' && !cfg.docId) { alertBox('Select a record to edit first.'); return; }
        submit('save');
        return;
      }
      const line = readLine();
      if (name === 'add') {
        if (lineReady(line)) { items.push(line); selected = -1; }
        else if (!items.length) { alertBox('Enter the ' + cfg.lineRequired.replace(/_/g, ' ') + ' for the item first.'); return; }
      } else if (name === 'edit') {
        if (selected >= 0) {
          if (!lineReady(line)) { alertBox('Enter the ' + cfg.lineRequired.replace(/_/g, ' ') + ' for the item first.'); return; }
          items[selected] = line;
        } else if (!cfg.docId) { alertBox('Select a line in the grid to edit, or use Add.'); return; }
      }
      if (!items.length) { alertBox('Add at least one item.'); return; }
      submit('save');
    }

    function showSerials() {
      const rows = cfg.hasItems ? items : [cfg.header || {}];
      const list = [];
      rows.forEach((it, i) => cfg.serialFields.forEach(f => { if (it[f]) list.push('<tr><td>' + (i + 1) + '</td><td>' + esc(f.replace(/_/g, ' ')) + '</td><td>' + esc(it[f]) + '</td></tr>'); }));
      modal.open('Serial View', list.length
        ? '<table class="rma-table"><thead><tr><th>SL</th><th>Type</th><th>Serial</th></tr></thead><tbody>' + list.join('') + '</tbody></table>'
        : '<p>No serial numbers on this record.</p>');
    }

    document.addEventListener('click', e => {
      const a = e.target.closest('[data-act]');
      if (a && !a.disabled) { act(a.dataset.act); return; }
      const s = e.target.closest('[data-sel]');
      if (s) {
        const i = Number(s.dataset.sel);
        if (!cfg.hasItems) { location.href = pageUrl + '?id=' + items[i]._id; return; }
        selected = i;
        writeLine(items[i]);
        render();
        return;
      }
      const d = e.target.closest('[data-del]');
      if (d && !d.disabled) {
        const i = Number(d.dataset.del);
        confirmBox('Are you sure want to delete this record?', () => {
          if (!cfg.hasItems) {
            $('#rma-doc-id').value = items[i]._id;
            submit('delete');
            return;
          }
          items.splice(i, 1);
          selected = -1;
          if (cfg.docId) submit(items.length ? 'save' : 'delete');
          else render();
        });
        return;
      }
      const ds = e.target.closest('[data-docsearch]');
      if (ds) {
        const v = document.getElementById(ds.dataset.docsearch).value.trim();
        if (v) location.href = pageUrl + '?no=' + encodeURIComponent(v);
      }
    });

    items.forEach(compute);
    render();
    updateDuration();
  }

  /* ---------------------------------------------------------------- approve page */
  function initApprove() {
    const all = $('#chk-all');
    all?.addEventListener('change', () => $$('.chk-row').forEach(c => { c.checked = all.checked; }));
    const form = $('#approve-form');
    const run = ids => {
      if (!ids.length) { alertBox('Select at least one record.'); return; }
      confirmBox('Are you sure want to Approve this record?', () => {
        $('#approve-ids').value = ids.join(',');
        form.submit();
      });
    };
    document.addEventListener('click', e => {
      const one = e.target.closest('[data-approve]');
      if (one) run([one.dataset.approve]);
      if (e.target.closest('#approve-selected')) run($$('.chk-row:checked').map(c => c.value));
    });
    const allDate = $('#all-date');
    const sync = () => $$('.date-range').forEach(i => { i.disabled = allDate.checked; });
    allDate?.addEventListener('change', sync);
    if (allDate) sync();
  }

  /* ---------------------------------------------------------------- register pages */
  function initRegister() {
    const filters = $('#reg-filters');
    const chosen = () => $$('.reg-pick:checked').map(c => c.value);
    document.addEventListener('change', e => {
      if (e.target.classList.contains('reg-pick')) {
        if (e.target.checked) $$('.reg-pick').forEach(c => { if (c !== e.target) c.checked = false; });
        if (filters) filters.hidden = !chosen().length;
      }
      if (e.target.name === 'scope') {
        $$('[data-scope-for]').forEach(el => { el.hidden = !el.dataset.scopeFor.split(' ').includes(e.target.value); });
      }
    });
    const allDate = $('#all-date');
    const sync = () => $$('.date-range').forEach(i => { i.disabled = allDate.checked; });
    allDate?.addEventListener('change', sync);
    if (allDate) sync();
    document.addEventListener('click', e => {
      const b = e.target.closest('[data-report]');
      if (!b) return;
      const params = new URLSearchParams(new FormData($('#reg-form')));
      params.set('report', b.dataset.report);
      if (b.dataset.print) params.set('auto', '1');
      window.open(location.pathname + '?' + params.toString(), '_blank');
    });
  }

  window.RMA = { initDocument, initApprove, initRegister, confirm: confirmBox, alert: alertBox };
})();
