<?php
/** Purchase Order: order from a supplier, directly or Requisition Wise. A Purchase can then be made Purchase Order Wise. */
require_once __DIR__ . '/purchase_common.php';
inv_doc_page($conn, [
    'type' => 'purchase_order',
    'header' => array_merge(PUR_SUPPLIER_HEADER, [['required_date', 'Delivery Date', 'date'], ['narration', 'Narration', 'text']]),
    'header_options' => ['bill_type' => ['Credit', 'Cash']],
    'source' => ['type' => 'requisition', 'label' => 'Requisition Wise', 'field' => 'Requisition No', 'mode' => 'remaining'],
    'lines' => ['narration', 'discount'],
    'totals' => ['extra', 'discount', 'vat'],
    'buttons' => ['show_cost', 'payment_term', 'import'],
    'search_label' => 'PO No',
    'narration_label' => 'Product Narration',
    'totals_label' => 'Discount & Extra',
    'custom_label' => 'Print1',
    'last_info' => true,
] + pur_hooks(['attachments']));
?>
<?php include "../../addon/conf/connection_close.php";  ?>
