<?php
/** Warranty & Service: items bought with serials and warranty end date, and the customer's service (RMA) tickets with their status. */
require_once __DIR__ . '/portal_common.php';
$a = cp_require_login();
$cid = (int) $a['customer_id'];
$items = cp_rows("SELECT i.model_caption, i.description, i.serial_imei, i.warranty, i.quantity, v.invoice_id, v.invoice_no, v.sales_date FROM sales_invoice_items i JOIN sales_invoices v ON v.invoice_id=i.invoice_id
    WHERE v.customer_id=? AND v.status='posted' AND (i.warranty<>'' OR COALESCE(i.serial_imei, '')<>'') ORDER BY v.sales_date DESC, i.item_id LIMIT 500", 'i', [$cid]);
$tickets = cp_table_exists('rma_complains') ? cp_rows("SELECT c.complain_no, c.complain_date, ci.model_no, ci.received_serial, ci.problem_name, ci.line_status, ci.delivery_date FROM rma_complain_items ci JOIN rma_complains c ON c.complain_id=ci.complain_id
    WHERE c.status='approved' AND (c.customer_id=? OR (c.customer_id IS NULL AND RIGHT(REGEXP_REPLACE(c.mobile, '[^0-9]', ''), 10)=?)) ORDER BY c.complain_date DESC LIMIT 200", 'is', [$cid, cp_phone_key($a['mobile']) ?: 'none']) : [];
$q = trim((string) ($_GET['serial'] ?? ''));
cp_page_start('Warranty & Service', 'warranty');
echo '<form method="get" class="card row"><div><label for="serial">Find a serial / IMEI from your purchases</label><input id="serial" name="serial" value="' . cp_h($q) . '"></div><div class="auto"><button class="btn">Find</button></div></form>';
echo '<h2>Your items</h2><div class="scroll"><table><thead><tr><th>Bought</th><th>Item</th><th>Serial / IMEI</th><th>Warranty</th><th>Warranty until</th><th>Status</th><th>Invoice</th></tr></thead><tbody>';
$shown = 0;
foreach ($items as $it) {
    $serials = array_values(array_filter(array_map('trim', preg_split('/[\r\n,]+/', (string) $it['serial_imei'])), 'strlen'));
    if ($q !== '' && !array_filter($serials, fn($s) => stripos($s, $q) !== false)) {
        continue;
    }
    $shown++;
    $end = cp_warranty_end($it['warranty'], $it['sales_date']);
    $state = $end === null ? '' : ($end >= date('Y-m-d') ? '<span class="badge ok">In warranty</span>' : '<span class="badge bad">Expired</span>');
    echo '<tr><td>' . cp_date($it['sales_date']) . '</td><td>' . cp_h(trim($it['model_caption'] . ' ' . $it['description'])) . '</td><td>' . cp_h(implode(', ', $serials)) . '</td><td>' . cp_h($it['warranty']) . '</td>'
        . '<td>' . ($end ? cp_date($end) : '') . '</td><td>' . $state . '</td><td><a href="invoices.php?id=' . (int) $it['invoice_id'] . '">' . cp_h($it['invoice_no']) . '</a></td></tr>';
}
echo $shown ? '' : '<tr><td colspan="7" class="empty">' . ($q !== '' ? 'No item of yours has that serial.' : 'No items with a warranty or serial.') . '</td></tr>';
echo '</tbody></table></div>';
$labels = ['received' => ['Received', 'warn'], 'diagnosed' => ['Checked', 'warn'], 'sent_supplier' => ['Sent to supplier', 'warn'], 'replaced_in' => ['Replacement ready', 'ok'], 'delivered' => ['Delivered', 'ok'], 'settled' => ['Settled', 'ok']];
echo '<h2>Service tickets</h2><div class="scroll"><table><thead><tr><th>Date</th><th>Job No</th><th>Item</th><th>Serial</th><th>Problem</th><th>Status</th><th>Delivery date</th></tr></thead><tbody>';
echo $tickets ? '' : '<tr><td colspan="7" class="empty">No service tickets.</td></tr>';
foreach ($tickets as $t) {
    [$label, $badge] = $labels[$t['line_status']] ?? [ucfirst((string) $t['line_status']), ''];
    echo '<tr><td>' . cp_date($t['complain_date']) . '</td><td>' . cp_h($t['complain_no']) . '</td><td>' . cp_h($t['model_no']) . '</td><td>' . cp_h($t['received_serial']) . '</td><td>' . cp_h($t['problem_name']) . '</td>'
        . '<td><span class="badge ' . $badge . '">' . cp_h($label) . '</span></td><td>' . cp_date($t['delivery_date']) . '</td></tr>';
}
echo '</tbody></table></div><p><a href="requests.php?new=support">Report a problem with an item</a></p>';
cp_page_end();
