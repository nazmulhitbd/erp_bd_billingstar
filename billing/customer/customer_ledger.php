<?php
/**
 * Customer Ledger: opening balance, then every sale, receipt, sales return and refund with a running balance, for a
 * date range; plus the unpaid invoices by age. Printable.
 */
require_once __DIR__ . '/customer_common.php';

$self = 'customer_ledger.php';
mgmt_require(cm_can($conn, 'ledger'), 'viewing customer ledgers');
$id = (int) ($_GET['id'] ?? 0);
$all = !empty($_GET['all_date']);
$from = $all ? '1000-01-01' : (sales_valid_date($_GET['from'] ?? '', true) ?: date('Y-m-01'));
$to = $all ? '9999-12-31' : (sales_valid_date($_GET['to'] ?? '', true) ?: date('Y-m-d'));
$c = $id && cm_ready($conn) ? cm_customer($conn, $id) : null;

/** The statement table (page and print). */
function ledger_table(array $c, $opening, array $rows, $closing, $dr, $cr)
{
    $html = '<table><thead><tr><th>Date</th><th>Doc No</th><th>Particulars</th><th>Ref / Invoice</th><th class="num">Debit</th><th class="num">Credit</th><th class="num">Balance</th></tr></thead><tbody>'
        . '<tr><td></td><td></td><td><i>Opening Balance</i></td><td></td><td></td><td></td><td class="num"><b>' . sales_money($opening) . '</b></td></tr>';
    foreach ($rows as $r) {
        $doc = $r['sort_order'] == 1 ? '<a href="../sales/sales_invoice/sales_invoice.php?id=' . (int) $r['ref_id'] . '">' . sales_h($r['doc_no']) . '</a>' : sales_h($r['doc_no']);
        $html .= '<tr><td>' . inv_fmt_date($r['entry_date']) . '</td><td>' . $doc . '</td><td>' . sales_h($r['entry_type']) . '</td><td>' . sales_h($r['ref_no']) . '</td>'
            . '<td class="num">' . ((float) $r['debit'] ? sales_money($r['debit']) : '') . '</td><td class="num">' . ((float) $r['credit'] ? sales_money($r['credit']) : '') . '</td><td class="num">' . sales_money($r['balance']) . '</td></tr>';
    }
    return $html . '<tr class="grand"><td colspan="4"><b>Total / Closing Balance</b></td><td class="num"><b>' . sales_money($dr) . '</b></td><td class="num"><b>' . sales_money($cr) . '</b></td><td class="num"><b>' . sales_money($closing) . '</b></td></tr></tbody></table>';
}

if ($c && isset($_GET['print'])) {
    [$opening, $rows, $closing, $dr, $cr] = cm_ledger($conn, $c, $from, $to);
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Ledger ' . sales_h($c['customer_name']) . '</title><style>' . INV_PRINT_CSS . 'tr.grand td{background:#eee}</style></head><body onload="window.print()">'
        . '<div class="bar"><button onclick="window.print()">Print</button></div>' . inv_print_head($conn, 'Customer Ledger')
        . '<div class="meta"><div><b>Customer:</b> ' . sales_h($c['customer_name']) . ' (' . sales_h($c['trader_code'] ?: $c['customer_code']) . ')</div><div><b>Mobile:</b> ' . sales_h($c['mobile']) . '</div>'
        . '<div><b>Address:</b> ' . sales_h($c['address']) . '</div><div><b>Period:</b> ' . ($all ? 'All dates' : inv_fmt_date($from) . ' to ' . inv_fmt_date($to)) . '</div>'
        . '<div><b>Credit Limit:</b> ' . ((int) $c['allow_credit'] ? sales_money($c['credit_limit']) : 'No credit') . '</div><div><b>Printed:</b> ' . date('d/m/Y h:i A') . '</div></div>'
        . ledger_table($c, $opening, $rows, $closing, $dr, $cr) . '<div class="sign"><div>Customer</div><div></div><div></div><div>Authorised By</div></div></body></html>';
    exit;
}

cm_page_start($conn, 'Customer Ledger', 'ledger');
$customers = sales_rows($conn, 'SELECT customer_id, customer_name, mobile FROM sales_customers ORDER BY customer_name LIMIT 5000');
?>
<form method="get" class="list-search" style="align-items:center">
<select class="input" name="id" style="max-width:340px" required><option value="">Pick a customer</option><?php foreach ($customers as $o): ?><option value="<?= (int) $o['customer_id'] ?>"<?= $id === (int) $o['customer_id'] ? ' selected' : '' ?>><?= sales_h($o['customer_name'] . ($o['mobile'] ? ' · ' . $o['mobile'] : '')) ?></option><?php endforeach; ?></select>
<label class="nowrap"><input class="check" type="checkbox" name="all_date" value="1"<?= $all ? ' checked' : '' ?>> All Date</label>
<input class="input" type="date" name="from" value="<?= $all ? date('Y-m-01') : sales_h($from) ?>" style="max-width:160px"><input class="input" type="date" name="to" value="<?= $all ? date('Y-m-d') : sales_h($to) ?>" style="max-width:160px">
<button class="btn primary">Show</button>
<?php if ($c): ?><a class="btn" target="_blank" href="<?= $self ?>?<?= sales_h(http_build_query(['id' => $id, 'from' => $from, 'to' => $to, 'all_date' => $all ? 1 : null, 'print' => 1])) ?>">Print</a><?php endif; ?>
</form>
<?php
if ($c) {
    [$opening, $rows, $closing, $dr, $cr] = cm_ledger($conn, $c, $from, $to);
    $aging = cm_aging($conn, $id);
    [$label, $badge] = cm_status($c);
    echo '<div class="summary"><div>Customer<b><a href="customer_profile.php?id=' . $id . '">' . sales_h($c['customer_name']) . '</a></b></div><div>Opening<b>' . sales_money($opening) . '</b></div>'
        . '<div>Debit<b>' . sales_money($dr) . '</b></div><div>Credit<b>' . sales_money($cr) . '</b></div><div>Closing<b class="qty-info">' . sales_money($closing) . '</b></div>'
        . '<div>Due Now<b>' . sales_money($c['balance']) . '</b></div><div>Status<b><span class="badge ' . $badge . '">' . sales_h($label) . '</span></b></div></div>';
    echo '<div class="table-scroll" style="max-height:560px;margin-top:8px">' . ledger_table($c, $opening, $rows, $closing, $dr, $cr) . '</div>';
    echo '<section class="section"><div class="section-title">Unpaid invoices by age</div><table><thead><tr>' . implode('', array_map(fn($k) => '<th class="num">' . sales_h($k) . '</th>', array_keys($aging))) . '<th class="num">Total</th></tr></thead><tbody><tr>'
        . implode('', array_map(fn($a) => '<td class="num">' . sales_money($a) . '</td>', $aging)) . '<td class="num"><b>' . sales_money(array_sum($aging)) . '</b></td></tr></tbody></table>'
        . ((float) $c['opening_balance'] ? '<p class="footnote">The opening balance of ' . sales_money($c['opening_balance']) . ' is not in the age table.</p>' : '') . '</section>';
    echo '<p class="footnote">Sales are debits; receipts and sales returns are credits; refunds are debits. Receipts posted only as Accounts vouchers are in the Accounts Register.</p>';
}
mgmt_page_end();
?>
<?php include "../../addon/conf/connection_close.php";  ?>
