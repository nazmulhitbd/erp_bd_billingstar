<?php
/**
 * Purchase Return: send goods from a purchase back to the supplier (stock out). Lines come from the purchase, only
 * what is still unreturned can go back, and serials must be ones received on that purchase. The return value
 * reduces what is owed on the purchase.
 */
require_once __DIR__ . '/purchase_common.php';
inv_doc_page($conn, [
    'type' => 'purchase_return',
    'header' => array_merge(PUR_SUPPLIER_HEADER, [['narration', 'Return Reason / Narration', 'text']]),
    'header_options' => ['bill_type' => ['Credit', 'Cash']],
    'source' => ['type' => 'purchase', 'label' => 'Purchase Invoice Wise', 'field' => 'Purchase Invoice No', 'mode' => 'remaining'],
    'source_required' => true,
    'lines' => ['store', 'narration', 'discount', 'serials'],
    'totals' => ['extra', 'discount', 'vat'],
    'label' => fn($doc) => 'Purchase Return',
    'buttons' => ['add_all', 'serial_view', 'import'],
    'search_label' => 'Return No',
    'narration_label' => 'Product Narration',
    'totals_label' => 'Discount & Extra',
    'custom_label' => 'Print1',
    'last_info' => true,
    'print_title' => 'Purchase Return',
] + pur_hooks(['attachments']));
?>
<?php include "../../addon/conf/connection_close.php";  ?>
