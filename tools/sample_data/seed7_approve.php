<?php
PHP_SAPI === 'cli' || exit; // a developer tool: never from the web
// Stage 7: approvals. Most documents approved; the last two invoices, the sales order and the challan stay pending.
require __DIR__ . '/seed_lib.php';
$docs = fn($type) => array_map('intval', array_column(sb_db()->query("SELECT doc_id FROM inv_docs WHERE doc_type='$type' AND approval_status='pending'")->fetch_all(MYSQLI_ASSOC), 'doc_id'));
foreach (['purchase' => $docs('purchase'), 'purchase_return' => $docs('purchase_return'), 'stock_transfer' => $docs('stock_transfer'), 'stock_in' => $docs('stock_in'), 'stock_out' => $docs('stock_out'),
    'sales' => array_map('intval', array_column(sb_db()->query("SELECT invoice_id FROM sales_invoices WHERE approval_status='pending' ORDER BY invoice_id LIMIT 8")->fetch_all(MYSQLI_ASSOC), 'invoice_id')),
    'sales_return' => array_map('intval', array_column(sb_db()->query('SELECT return_id FROM sales_returns')->fetch_all(MYSQLI_ASSOC), 'return_id'))] as $doc => $ids) {
    if ($ids) {
        sb_post("approve/approve.php?doc=$doc", ['ids' => $ids], "approve $doc (" . count($ids) . ')');
    }
}
echo "done\n";
