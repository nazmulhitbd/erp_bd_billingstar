<?php
/** Stock Amendment IN: add stock found or opening stock, with serials (typed, a range, or auto-generated). */
require_once dirname(__DIR__) . '/inventory/inv_doc_engine.php';
inv_doc_page($conn, [
    'type' => 'stock_in',
    'header' => [['party_group', 'Group Name', 'text'], ['party_name', 'Particulars', 'text'], ['narration', 'Narration', 'text']],
    'lines' => ['store', 'serials', 'auto_serial'],
    'label' => fn($doc) => 'Stock Amendment In',
    'buttons' => ['serial_view', 'barcode', 'import'],
]);
?>
<?php include "../../addon/conf/connection_close.php";  ?>
