<?php
/** Sales Price Fixing: set the sales price, MRP or a Retail / Dealer level for a group, brand or model at once (see product_common.php). */
require_once __DIR__ . '/product_common.php';
pm_price_fixing_page($conn, 'sales');
?>
<?php include "../../addon/conf/connection_close.php";  ?>
