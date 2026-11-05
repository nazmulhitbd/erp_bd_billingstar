<?php
/** Purchase Requisition: items a department needs. A Purchase Order can be made Requisition Wise from it. */
require_once __DIR__ . '/purchase_common.php';
inv_doc_page($conn, [
    'type' => 'requisition',
    'header' => [['sales_person', 'Requisition By', 'text'], ['delivery_point', 'Department', 'text'], ['required_date', 'Required Date', 'date'], ['narration', 'Narration', 'text']],
    'lines' => ['store', 'narration'],
    'amounts' => false,
    'buttons' => ['import'],
    'search_label' => 'Requisition No',
    'narration_label' => 'Product Narration',
    'custom_label' => 'Print1',
    'last_info' => true,
    'print_title' => 'Purchase Requisition',
] + pur_hooks(['attachments']));
?>
<?php include "../../addon/conf/connection_close.php";  ?>
