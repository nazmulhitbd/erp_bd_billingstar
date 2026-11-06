<?php
PHP_SAPI === 'cli' || exit; // a developer tool: never from the web
// Stage 4: sales. Invoices (credit and cash), a POS sale, a sales order, a sales return, a quotation and a challan.
require __DIR__ . '/seed_lib.php';

function sb_lines(array $lines)
{
    $out = [];
    foreach ($lines as $l) {
        [$code, $qty, $price, $store] = $l + [3 => 'Main Store'];
        $out[] = sb_product($code) + ['quantity' => $qty, 'free_quantity' => 0, 'unit_price' => $price, 'store_name' => $store, 'discount_amount' => 0, 'product_narration' => '', 'serials' => []];
    }
    return json_encode($out);
}

/** Customer header for the sales forms: a saved customer, or a walk-in buyer typed on the form. */
function sb_customer($name, $mobile = '')
{
    if ($name === 'Walk-in Customer') {
        return ['customer_id' => '', 'customer_name' => $name, 'customer_group' => 'Cash Customer', 'mobile' => $mobile, 'address' => ''];
    }
    $p = sb_party('customer', $name);
    return ['customer_id' => $p['party_id'], 'customer_name' => $p['party_name'], 'customer_group' => $p['party_group'], 'mobile' => $p['mobile'], 'address' => $p['address']];
}

$invoices = [ // date, customer, bill type, lines, cash, method, sales person
    ['2026-09-08', 'Greenfield School', 'Credit', [['P-2001', 2, 68500], ['P-5001', 2, 4800]], 50000, 'bank', 'Arif Hasan'],
    ['2026-09-10', 'Walk-in Customer', 'Cash', [['P-1001', 1, 14500], ['P-3001', 1, 950]], 15450, 'cash', ''],
    ['2026-09-15', 'City Gadget Point', 'Credit', [['P-1001', 5, 13900], ['P-1004', 10, 1550], ['P-3002', 20, 320]], 30000, 'cash', 'Nadia Karim'],
    ['2026-09-24', 'Sunrise Pharma Ltd.', 'Credit', [['P-4001', 2, 38500], ['P-2002', 1, 79900]], 0, 'cash', 'Nadia Karim'],
    ['2026-10-03', 'Orchid Hotel & Resort', 'Credit', [['P-4002', 4, 21000, 'Showroom Store'], ['P-5002', 3, 3200]], 40000, 'bank', 'Arif Hasan'],
    ['2026-10-12', 'Walk-in Customer', 'Cash', [['P-1003', 1, 18500], ['P-3003', 1, 2450, 'Showroom Store']], 20950, 'mobile', ''],
    ['2026-10-20', 'Riverside Electronics', 'Credit', [['P-1002', 3, 22400], ['P-3004', 5, 2100]], 25000, 'cash', 'Tanvir Ahmed'],
    ['2026-11-02', 'Lakeview Clinic', 'Credit', [['P-2001', 1, 68500], ['P-3004', 2, 2200]], 20000, 'cash', 'Arif Hasan'],
    ['2026-11-05', 'Walk-in Customer', 'Cash', [['P-1004', 2, 1650], ['P-3002', 3, 350]], 4350, 'cash', ''],
];
foreach ($invoices as $i => [$date, $cust, $type, $lines, $cash, $method, $sr]) {
    sb_post('sales/sales_invoice/sales_invoice.php', sb_customer($cust, $cust === 'Walk-in Customer' ? '0100000099' . $i : '') + [
        'action' => 'save', 'invoice_id' => 0, 'items_json' => sb_lines($lines), 'schedules_json' => '[]', 'exchanges_json' => '[]', 'attention' => '', 'sales_person' => $sr, 'delivery_point' => '',
        'bin' => '', 'nid' => '', 'sales_date' => $date, 'bill_type' => $type, 'ref_no' => '', 'narration' => '', 'sq' => 'none', 'cash_received' => $cash, 'payment_method' => $method, 'member_no' => '',
        'point_adjust' => 0, 'extra_charges' => 0, 'discount_amount' => 0, 'vat_amount' => 0], "invoice $date $cust");
}

echo "Sales order\n";
sb_post('sales/sales_order/sales_order.php', sb_customer('Greenfield School') + ['action' => 'save', 'order_id' => 0, 'items_json' => sb_lines([['P-2002', 2, 79900], ['P-5001', 1, 4800]]), 'schedules_json' => '[]',
    'attention' => 'Admin Office', 'sales_person' => 'Arif Hasan', 'delivery_point' => 'School campus', 'bin' => '', 'nid' => '', 'order_date' => '2026-10-28', 'delivery_date' => '2026-11-15', 'bill_type' => 'Credit',
    'ref_no' => 'GS-PO-118', 'narration' => 'Computer lab upgrade', 'extra_charges' => 0, 'discount_amount' => 0, 'vat_amount' => 0, 'advance_received' => 0], 'sales order Greenfield');

echo "Sales return\n";
$inv = sb_db()->query("SELECT invoice_id, invoice_no FROM sales_invoices WHERE customer_name='City Gadget Point'")->fetch_row();
$item = (int) sb_val("SELECT item_id FROM sales_invoice_items WHERE invoice_id={$inv[0]} AND product_code='P-1004'");
$page = 'sales/sales_retrun/sales_return.php?invoice=' . urlencode($inv[1]);
sb_post($page, ['action' => 'save', 'invoice_id' => $inv[0], 'return_id' => 0, 'return_date' => '2026-09-20', 'line' => [$item => ['quantity' => 2, 'free_quantity' => 0, 'condition' => 'good']],
    'deduction_amount' => 0, 'due_adjusted' => 3100], 'sales return City Gadget');

echo "Quotation and challan\n";
sb_doc('price_history/price_quotation.php', sb_party('customer', 'Sunrise Pharma Ltd.') + ['ref_no' => 'Q-SP-01', 'doc_date' => '2026-10-30', 'attention' => 'Procurement', 'sales_person' => 'Nadia Karim',
    'narration' => 'Laptops for the new office', 'payment_term' => '50% advance, rest on delivery. Valid for 15 days.'], [['P-2001', 5, 67500]], 'quotation Sunrise');
sb_doc('challan/challan.php', sb_party('customer', 'Lakeview Clinic') + ['ref_no' => 'DC-01', 'doc_date' => '2026-11-03', 'attention' => 'Manager', 'sales_person' => 'Arif Hasan', 'narration' => 'Router for trial',
    'delivery_point' => 'Clinic reception', 'source_no' => ''], [['P-5001', 1, 4800]], 'challan Lakeview');

echo "POS sale\n";
$cart = [];
foreach ([['P-3001', 2, 950], ['P-3002', 2, 350]] as [$code, $q, $p]) {
    $cart[] = sb_product($code) + ['quantity' => $q, 'unit_price' => $p, 'discount_amount' => 0, 'serial_imei' => ''];
}
sb_post('sales/pos_sales/pos_sales.php', ['cart_json' => json_encode($cart), 'discount_amount' => 0, 'vat_percent' => 0, 'customer_name' => 'Walk-in Customer', 'customer_id' => '', 'mobile' => '',
    'payment_method' => 'cash', 'payment_reference' => '', 'tendered' => 2600, 'print_as' => 'none'], 'POS sale');
echo "done\n";
