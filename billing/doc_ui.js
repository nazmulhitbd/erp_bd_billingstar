/* Document screens (doc_layout.css): fills the summary column and labels the item cells. The screen's own script
   (sales/sales_doc.js or inventory/inv_doc.js) keeps all the logic; this only presents what it shows in #totals and
   #grid-body: the payable amount (lakh grouping), item count, quantity and change, and a column name on every cell
   so the items can show as cards on a narrow screen. */
(function () {
  'use strict';
  var $ = function (id) { return document.getElementById(id); };
  var head = $('grid-head'), body = $('grid-body'), totals = $('totals');
  if (!body || !totals || !document.querySelector('form.si')) return;

  function text(el) { return el ? el.textContent.trim() : ''; }
  function grouped(v) {
    var n = Number(String(v || '0').replace(/,/g, ''));
    return isFinite(n) ? n.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : String(v);
  }
  /** A figure from #totals: by id, or by the label in its row. */
  function figure(ids, label) {
    for (var i = 0; i < ids.length; i++) { if ($(ids[i])) return text($(ids[i])); }
    var hit = '';
    totals.querySelectorAll('tr').forEach(function (tr) {
      if (!hit && tr.cells[0] && label.test(text(tr.cells[0]))) hit = tr.cells[1] ? (tr.cells[1].querySelector('input') ? tr.cells[1].querySelector('input').value : text(tr.cells[1])) : '';
    });
    return hit;
  }
  function summary() {
    var n = 0;
    body.querySelectorAll('tr').forEach(function (r) { if (!r.querySelector('td.empty')) n++; });
    var net = figure(['tt_net', 't_net'], /net payable|^new amount/i);
    if ($('si_net')) {
      $('si_net').textContent = net === '' ? (figure(['tt_qty'], /total quantity/i) || '0') : grouped(net);
      var cur = $('si_net').previousElementSibling;
      if (cur && cur.tagName === 'SMALL') cur.hidden = net === '';
      var cap = document.querySelector('.si-sum-top > span');
      if (cap) cap.textContent = net === '' ? 'Total quantity' : (cap.getAttribute('data-label') || 'Net payable');
    }
    if ($('si_qty')) $('si_qty').textContent = figure(['tt_qty'], /total quantity/i) || '0';
    if ($('si_count')) $('si_count').textContent = String(n);
    if ($('si_count_head')) $('si_count_head').textContent = n ? '(' + n + ')' : '';
    var change = figure(['tt_change', 'change-amount'], /change amount/i), wrap = $('si_change_wrap');
    if (wrap) { wrap.hidden = !change || Number(change.replace(/,/g, '')) <= 0; $('si_change').textContent = change; }
  }
  function label() {
    if (!head) return;
    var names = Array.prototype.map.call(head.querySelectorAll('th'), function (th) { return th.textContent.trim(); });
    body.querySelectorAll('tr').forEach(function (tr) {
      Array.prototype.forEach.call(tr.cells, function (td, i) {
        if (!names[i]) return;
        td.setAttribute('data-label', names[i]);
        td.classList.add('si-col-' + names[i].toLowerCase().replace(/[^a-z]+/g, '-').replace(/^-|-$/g, ''));
      });
    });
  }
  var queued = false;
  function update() {
    if (queued) return;
    queued = true;
    requestAnimationFrame(function () { queued = false; label(); summary(); });
  }
  var watch = { childList: true, subtree: true, characterData: true };
  new MutationObserver(update).observe(body, watch);
  new MutationObserver(update).observe(totals, watch);
  document.addEventListener('input', update);
  update();
})();
