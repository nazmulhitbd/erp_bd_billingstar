<?php
PHP_SAPI === 'cli' || exit; // a developer tool: never from the web
// Stage 3: stock in. Purchases (credit and cash), a purchase order, a purchase return, a transfer and amendments.
require __DIR__ . '/seed_lib.php';

[, $p1] = sb_doc('purchase/purchase.php', sb_party('supplier', 'Delta Distribution Ltd.') + ['ref_no' => 'DD-INV-5521', 'doc_date' => '2026-09-05', 'bill_type' => 'Credit', 'order_date' => '2026-09-01', 'narration' => 'Opening phone and accessory stock', 'cash_received' => 200000],
    [['P-1001', 20, 12200], ['P-1002', 10, 19800], ['P-1004', 40, 1280], ['P-3001', 60, 620], ['P-3002', 100, 190]], 'purchase from Delta');
sb_doc('purchase/purchase.php', sb_party('supplier', 'Meridian Tech Supply') + ['ref_no' => 'MTS-0934', 'doc_date' => '2026-09-12', 'bill_type' => 'Credit', 'order_date' => '', 'narration' => 'Laptops and routers', 'cash_received' => 0],
    [['P-2001', 8, 61000], ['P-2002', 6, 71500], ['P-5001', 15, 3900], ['P-5002', 15, 2550]], 'purchase from Meridian');
sb_doc('purchase/purchase.php', sb_party('supplier', 'Blue Harbor Trading Co.') + ['ref_no' => 'BH-77812', 'doc_date' => '2026-09-20', 'bill_type' => 'Cash', 'order_date' => '', 'narration' => 'TVs, phones and audio', 'cash_received' => 0],
    [['P-4001', 10, 33500], ['P-4002', 12, 18200], ['P-1003', 12, 15900], ['P-3003', 40, 1750], ['P-3004', 30, 1600]], 'purchase from Blue Harbor');

// Return 5 chargers of the first purchase: each line names the purchase line it comes from.
$line = (int) sb_val("SELECT l.line_id FROM inv_doc_lines l JOIN inv_docs d ON d.doc_id=l.doc_id WHERE d.doc_no='$p1' AND l.product_code='P-3001'");
sb_doc('purchase/purchase_return.php', sb_party('supplier', 'Delta Distribution Ltd.') + ['ref_no' => 'RET-01', 'doc_date' => '2026-09-25', 'bill_type' => 'Credit', 'narration' => 'Five chargers with damaged boxes', 'source_no' => $p1],
    [['P-3001', 5, 620, 'Main Store', ['source_line_id' => $line]]], 'purchase return to Delta');

sb_doc('purchase/purchase_order.php', sb_party('supplier', 'Delta Distribution Ltd.') + ['ref_no' => 'PO-REQ-07', 'doc_date' => '2026-10-25', 'bill_type' => 'Credit', 'required_date' => '2026-11-10', 'narration' => 'Restock before the festive season', 'payment_term' => '30 days credit'],
    [['P-1001', 15, 12200], ['P-1002', 10, 19800]], 'purchase order to Delta');

sb_doc('transfer/stock_transfer.php', ['ref_no' => 'TR-01', 'doc_date' => '2026-09-22', 'narration' => 'Display stock for the showroom'],
    [['P-1001', 5, 12200, 'Main Store', ['store_to' => 'Showroom Store']], ['P-3003', 10, 1750, 'Main Store', ['store_to' => 'Showroom Store']], ['P-4002', 4, 18200, 'Main Store', ['store_to' => 'Showroom Store']]], 'transfer to showroom');

sb_doc('amedment/stock_amendment_in.php', ['ref_no' => 'COUNT-0930', 'doc_date' => '2026-09-30', 'party_group' => '', 'party_name' => '', 'narration' => 'Stock count: two extra cables found'],
    [['P-3002', 2, 190]], 'stock amendment in');
sb_doc('amedment/stock_amendment_out.php', ['ref_no' => 'COUNT-0930', 'doc_date' => '2026-09-30', 'party_group' => '', 'party_name' => '', 'narration' => 'Stock count: one power bank damaged'],
    [['P-3004', 1, 1600]], 'stock amendment out');
echo "done\n";
