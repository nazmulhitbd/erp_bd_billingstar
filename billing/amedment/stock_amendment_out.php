<?php
/** Stock Amendment OUT: write off lost or damaged stock; serials leave stock. */
require_once dirname(__DIR__) . '/inventory/inv_doc_engine.php';
inv_doc_page($conn, [
    'type' => 'stock_out',
    'header' => [['party_group', 'Group Name', 'text'], ['party_name', 'Particulars', 'text'], ['narration', 'Narration', 'text']],
    'lines' => ['store', 'serials'],
    'label' => fn($doc) => 'Stock Amendment Out',
    'buttons' => ['serial_view', 'import'],
]);
?>
<?php include "../../addon/conf/connection_close.php";  ?>
