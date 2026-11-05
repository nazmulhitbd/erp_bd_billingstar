<?php
/** Accounts Register: pick the period and party, then preview or print any accounts report. */
require_once __DIR__ . '/acc_common.php';
acc_require_ready($conn, 'Accounts Register', 'register');

$slgs = sales_rows($conn, 'SELECT s.slg_id, s.group_name, a.account_name FROM acc_sub_ledger_groups s JOIN acc_accounts a ON a.account_id=s.account_id WHERE s.is_active=1 ORDER BY s.group_name');
$accounts = perm_filter_ledgers(acc_accounts($conn, false));
$parties = perm_filter_ledgers(sales_rows($conn, 'SELECT sub_ledger_id, account_id, slg_id, sub_ledger_code, sub_ledger_name FROM acc_sub_ledgers ORDER BY sub_ledger_name'));

acc_page_start('Accounts Register', 'register');
?>
<fieldset class="box"><legend>Accounts Register</legend>
<form id="filters" class="grid g5" style="margin-top:0" onsubmit="return false">
<div class="field"><label><input class="check" type="checkbox" name="all" value="1" id="all"> All Date</label><label for="from">Date From</label><input type="date" id="from" name="from" value="<?= date('Y-m-01') ?>"></div>
<div class="field"><label>&nbsp;</label><label for="to">Date To</label><input type="date" id="to" name="to" value="<?= date('Y-m-d') ?>"></div>
<div class="field"><label>&nbsp;</label><label for="basis">Date Basis</label><select id="basis" name="basis"><option value="voucher">Voucher Date Wise</option><option value="cheque">Cheque Date Wise</option></select></div>
<div class="field"><label>&nbsp;</label><label for="slg">Group Name</label><select id="slg" name="slg"><option value="">All groups</option><?php foreach ($slgs as $g): ?><option value="<?= (int) $g['slg_id'] ?>"><?= sales_h($g['group_name'] . ' (' . $g['account_name'] . ')') ?></option><?php endforeach; ?></select></div>
<div class="field"><label>&nbsp;</label><label for="party">Name</label><select id="party" name="party"><option value="">Pick account or party</option>
<optgroup label="Parties (sub ledgers)" id="party-subs"><?php foreach ($parties as $p): ?><option value="s:<?= (int) $p['sub_ledger_id'] ?>" data-slg="<?= (int) $p['slg_id'] ?>"><?= sales_h($p['sub_ledger_name'] . ' (' . $p['sub_ledger_code'] . ')') ?></option><?php endforeach; ?></optgroup>
<optgroup label="General accounts"><?php foreach ($accounts as $a): ?><option value="a:<?= (int) $a['account_id'] ?>"><?= sales_h($a['account_code'] . ' - ' . $a['account_name']) ?></option><?php endforeach; ?></optgroup></select></div>
</form>
<div class="toolbar" style="margin:10px 0"><button type="button" class="btn" id="preview-ticked">Open ticked reports in new tabs</button><span class="muted" style="align-self:center">Accounts Ledger and Ledger Details need a Name. Due lists use the Group Name.</span></div>
<section id="preview" class="section" hidden>
<div class="toolbar" style="align-items:center"><b id="preview-title"></b><span id="preview-status" class="muted"></span><span style="flex:1"></span>
<button type="button" class="btn primary" id="preview-print">Print</button><a class="btn" id="preview-tab" target="_blank" rel="noopener">Open in new tab</a><button type="button" class="btn" id="preview-close">Close preview</button></div>
<iframe id="preview-frame" title="Report preview" style="width:100%;height:75vh;border:1px solid var(--line);background:#fff;margin-top:8px"></iframe>
</section>
<div class="table-scroll" style="max-height:640px"><table><thead><tr><th>SL No</th><th>Caption</th><th></th><th>Report SL</th><th>Preview</th><th>Print</th></tr></thead><tbody>
<?php foreach (ACC_REPORTS as $n => [$caption, $needs]): if (!perm_can('accounts_info/acc_report.php?r=' . $n, 'view')) { continue; } ?><tr><td><?= $n ?></td><td><?= sales_h($caption) ?></td><td><input class="check" type="checkbox" data-r="<?= $n ?>"></td><td><?= $n ?></td>
<td><button type="button" class="btn small" data-open="<?= $n ?>" data-needs="<?= $needs ?>" data-caption="<?= sales_h($caption) ?>">Print Preview</button></td><td><button type="button" class="btn small" data-open="<?= $n ?>" data-needs="<?= $needs ?>" data-caption="<?= sales_h($caption) ?>" data-print="1">Print</button></td></tr><?php endforeach; ?>
</tbody></table></div>
</fieldset>
<?php
acc_page_end('const f=document.getElementById("filters"),pane=document.getElementById("preview"),frame=document.getElementById("preview-frame"),statusEl=document.getElementById("preview-status");
let printWhenLoaded=false,startedAt=0;
function reportUrl(n){const p=new URLSearchParams(new FormData(f));p.set("r",n);return "acc_report.php?"+p.toString()}
function partyMissing(needs){if(needs==="party"&&!document.getElementById("party").value){alert("Pick a Name (account or party) for this report.");document.getElementById("party").focus();return true}return false}
/* Show the report inside this page; with print=true, print it as soon as it has loaded. Named showReport so it never shadows window.open. */
function showReport(n,needs,caption,print){if(partyMissing(needs))return;const u=reportUrl(n);
 pane.hidden=false;document.getElementById("preview-title").textContent=n+". "+caption;statusEl.textContent="Loading...";document.getElementById("preview-tab").href=u;
 printWhenLoaded=!!print;startedAt=performance.now();frame.src=u;pane.scrollIntoView({behavior:"smooth",block:"start"})}
frame.addEventListener("load",()=>{if(!frame.src||frame.src==="about:blank")return;statusEl.textContent="Loaded in "+((performance.now()-startedAt)/1000).toFixed(1)+" s";
 if(printWhenLoaded){printWhenLoaded=false;try{frame.contentWindow.focus();frame.contentWindow.print()}catch(err){window.open(frame.src+"&auto=1","_blank")}}});
document.getElementById("preview-print").addEventListener("click",()=>{try{frame.contentWindow.focus();frame.contentWindow.print()}catch(err){window.open(frame.src+"&auto=1","_blank")}});
document.getElementById("preview-close").addEventListener("click",()=>{pane.hidden=true;frame.src="about:blank"});
document.querySelectorAll("[data-open]").forEach(b=>b.addEventListener("click",()=>showReport(b.dataset.open,b.dataset.needs,b.dataset.caption,b.dataset.print)));
document.getElementById("preview-ticked").addEventListener("click",()=>{const t=[...document.querySelectorAll("[data-r]:checked")];if(!t.length){alert("Tick one or more reports.");return}
 t.forEach(c=>{const b=document.querySelector(`[data-open="${c.dataset.r}"]`);if(!partyMissing(b.dataset.needs))window.open(reportUrl(c.dataset.r),"_blank")})});
document.getElementById("all").addEventListener("change",e=>{document.getElementById("from").disabled=e.target.checked;document.getElementById("to").disabled=e.target.checked});
document.getElementById("slg").addEventListener("change",e=>{const g=e.target.value;document.querySelectorAll("#party-subs option").forEach(o=>{o.hidden=g!==""&&o.dataset.slg!==g});const sel=document.getElementById("party");if(sel.selectedOptions[0]?.hidden)sel.value=""});');
?>
<?php include "../../addon/conf/connection_close.php";  ?>
