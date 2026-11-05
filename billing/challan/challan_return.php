<?php
/** Challan Return: goods coming back against a challan (stock in, serials back in stock). */
require_once dirname(__DIR__) . '/inventory/inv_doc_engine.php';
inv_doc_page($conn, [
    'type' => 'challan_return',
    'header' => [['mobile', 'Mobile No', 'mobile'], ['party_group', 'Customer Group', 'text'], ['party_name', 'Customer Name', 'party'], ['address', 'Address', 'text'], ['narration', 'Narration', 'text']],
    'source' => ['type' => 'challan', 'label' => 'Challan Wise', 'field' => 'Challan No', 'mode' => 'remaining'],
    'source_required' => true,
    'lines' => ['store', 'narration', 'free', 'serials'],
    'amounts' => false,
    'label' => fn($doc) => 'Challan Return',
    'buttons' => ['add_all', 'edit_details', 'serial_view', 'import'],
    'search_label' => 'Return No',
    'additional' => true,
]);
?>
<?php include "../../addon/conf/connection_close.php";  ?>
