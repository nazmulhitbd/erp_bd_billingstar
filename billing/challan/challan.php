<?php
/**
 * Challan: deliver goods to a customer (stock out, with serials), directly or Sales Order Wise.
 * Edit Details changes the customer details of a saved challan without touching its goods. Under a saved challan
 * the delivery status shows what came back (Challan Return), what was billed (Sales Bill From Challan) and what is still to bill.
 */
require_once dirname(__DIR__) . '/inventory/inv_doc_engine.php';

/** Delivery status of a saved challan, with its returns and bills. */
function challan_status_panel(mysqli $db, array $cfg, $self, $doc, $readOnly)
{
    if (!$doc || !sales_tables_ready($db, ['challan_line_status'])) {
        return;
    }
    $rows = sales_rows($db, 'SELECT * FROM challan_line_status WHERE doc_id=? ORDER BY line_no', 'i', [(int) $doc['doc_id']]);
    $after = sales_rows($db, "SELECT doc_id, doc_type, doc_no, doc_date, net_amount, total_quantity, sales_invoice_id FROM inv_docs WHERE source_doc_id=? AND doc_type IN ('challan_return','challan_bill') AND status='active' ORDER BY doc_id", 'i', [(int) $doc['doc_id']]);
    $pending = array_sum(array_column($rows, 'pending_qty'));
    echo '<section class="section"><div class="section-title">Delivery Status</div><table><thead><tr><th>SL</th><th>Model No/Caption</th><th>Description</th><th>Store</th><th class="num">Delivered</th><th class="num">Returned</th><th class="num">Billed</th><th class="num">Pending Bill</th></tr></thead><tbody>';
    foreach ($rows as $r) {
        echo '<tr><td>' . (int) $r['line_no'] . '</td><td>' . sales_h(trim($r['product_code'] . ' ' . $r['model_caption'])) . '</td><td>' . sales_h($r['description']) . '</td><td>' . sales_h($r['store_name']) . '</td>'
            . '<td class="num">' . sales_qty_fmt($r['delivered_qty']) . '</td><td class="num">' . sales_qty_fmt($r['returned_qty']) . '</td><td class="num">' . sales_qty_fmt($r['billed_qty']) . '</td><td class="num"><b>' . sales_qty_fmt($r['pending_qty']) . '</b></td></tr>';
    }
    echo '</tbody></table><div class="toolbar" style="margin-top:8px">';
    if ($doc['status'] === 'active' && $pending > 0.0005) {
        echo '<a class="btn primary" href="sales_bill_from_challan.php?source_no=' . urlencode($doc['doc_no']) . '">Make Bill</a><a class="btn" href="challan_return.php?source_no=' . urlencode($doc['doc_no']) . '">Challan Return</a>';
    }
    foreach ($after as $a) {
        $isBill = $a['doc_type'] === 'challan_bill';
        echo '<a class="btn" href="' . ($isBill ? 'sales_bill_from_challan.php' : 'challan_return.php') . '?id=' . (int) $a['doc_id'] . '">' . ($isBill ? 'Bill ' : 'Return ') . sales_h($a['doc_no']) . '</a>';
    }
    echo '</div></section>';
}

inv_doc_page($conn, [
    'type' => 'challan',
    'header' => [['mobile', 'Mobile No', 'mobile'], ['party_group', 'Customer Group', 'text'], ['party_name', 'Customer Name', 'party'], ['address', 'Address', 'text'],
        ['attention', 'Attention', 'text'], ['sales_person', 'Sales Person', 'text'], ['narration', 'Narration', 'text'], ['delivery_point', 'Delivery Point', 'text']],
    'source' => ['type' => 'sales_order', 'label' => 'Sales Order Wise', 'field' => 'Order No', 'mode' => 'remaining'],
    'lines' => ['store', 'narration', 'warranty', 'free', 'serials'],
    'amounts' => false,
    'label' => fn($doc) => 'Challan',
    'buttons' => ['add_all', 'edit_details', 'serial_view', 'import'],
    'search_label' => 'Invoice NO',
    'print_label' => 'Challan',
    'last_info' => 'serial',
    'additional' => true,
    'print_title' => 'Delivery Challan',
    'panels' => 'challan_status_panel',
]);
?>
<?php include "../../addon/conf/connection_close.php";  ?>
