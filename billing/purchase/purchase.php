<?php
/**
 * Purchase: receive goods from a supplier into a store (stock in, with serials), directly or Purchase Order Wise.
 * Cash Amount, Payment and Schedule record what is paid and owed to the supplier; Attached keeps the supplier invoice.
 */
require_once __DIR__ . '/purchase_common.php';
inv_doc_page($conn, [
    'type' => 'purchase',
    'header' => array_merge(PUR_SUPPLIER_HEADER, [['order_date', 'Order Date', 'date'], ['narration', 'Narration', 'text']]),
    'header_options' => ['bill_type' => ['Credit', 'Cash']],
    'source' => ['type' => 'purchase_order', 'label' => 'Purchase Order Wise', 'field' => 'PO No', 'mode' => 'remaining'],
    'lines' => ['store', 'narration', 'discount', 'serials', 'auto_serial'],
    'totals' => ['extra', 'discount', 'vat'],
    'label' => fn($doc) => $doc['bill_type'] . ' Purchase',
    'buttons' => ['serial_view', 'edit_price', 'barcode_product', 'barcode', 'import'],
    'search_label' => 'Invoice No',
    'narration_label' => 'Product Narration',
    'totals_label' => 'Discount & Extra',
    'custom_label' => 'Print1',
    'last_info' => true,
    'print_title' => 'Purchase Invoice',
] + pur_hooks(['payments', 'attachments']));
?>
<?php include "../../addon/conf/connection_close.php";  ?>
