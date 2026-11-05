<?php
/** Purchase Price Fixing: set the standard purchase price, used as the cost on purchase screens until a product is bought. */
require_once __DIR__ . '/product_common.php';
pm_price_fixing_page($conn, 'purchase');
?>
<?php include "../../addon/conf/connection_close.php";  ?>
