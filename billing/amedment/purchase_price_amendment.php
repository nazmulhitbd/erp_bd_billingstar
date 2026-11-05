<?php
/**
 * Purchase Price Amendment: correct the cost of items on a saved purchase. Saving updates the purchase
 * lines, the purchase totals and the stock cost of those items.
 */
require_once dirname(__DIR__) . '/inventory/inv_doc_engine.php';

function inv_apply_price_amendment(mysqli $db, $docId, array $doc, array $lines)
{
    foreach ($lines as $l) {
        if (!$l['source_line_id']) {
            continue;
        }
        $price = (float) $l['unit_price'];
        sales_exec($db, "UPDATE inv_doc_lines SET unit_price=?, line_total=GREATEST(0, ROUND(quantity * ? - discount_amount, 2)) WHERE line_id=?", 'ddi', [$price, $price, (int) $l['source_line_id']])->close();
        sales_exec($db, "UPDATE inv_stock_moves SET unit_cost=? WHERE line_id=? AND doc_type='purchase'", 'di', [$price, (int) $l['source_line_id']])->close();
    }
    sales_exec(
        $db,
        'UPDATE inv_docs d SET subtotal=(SELECT COALESCE(SUM(line_total), 0) FROM inv_doc_lines WHERE doc_id=d.doc_id), net_amount=GREATEST(0, subtotal + extra_charges + vat_amount - discount_amount) WHERE d.doc_id=?',
        'i',
        [(int) $doc['source_doc_id']]
    )->close();
}

inv_doc_page($conn, [
    'type' => 'price_amendment',
    'header' => [['mobile', 'Mobile No', 'mobile'], ['party_group', 'Supplier Group', 'text'], ['party_name', 'Supplier Name', 'party'], ['address', 'Address', 'text'], ['narration', 'Narration', 'text']],
    'source' => ['type' => 'purchase', 'label' => 'Purchase Invoice Wise', 'field' => 'Purchase Invoice', 'mode' => 'all'],
    'source_required' => true,
    'lines' => ['prev_price'],
    'editable' => false,
    'after_save' => 'inv_apply_price_amendment',
]);
?>
<?php include "../../addon/conf/connection_close.php";  ?>
