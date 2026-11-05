<?php
/** Stock Transfer Store to Store: move quantity and serials from one store to another. */
require_once dirname(__DIR__) . '/inventory/inv_doc_engine.php';
inv_doc_page($conn, [
    'type' => 'stock_transfer',
    'header' => [['narration', 'Narration', 'text']],
    'lines' => ['store', 'store_to', 'serials'],
    'amounts' => false,
    'label' => fn($doc) => 'Stock Transfer',
    'buttons' => ['serial_view', 'show_cost', 'import'],
    'print_title' => 'Stock Transfer Store to Store',
]);
?>
<?php include "../../addon/conf/connection_close.php";  ?>
