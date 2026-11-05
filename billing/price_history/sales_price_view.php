<?php
/**
 * SalesPriceView: per product the sales price and the Retail A-C / Dealer A-C levels, picked Group / Brand /
 * Group and Brand / Model Wise, with a printable Preview. Prices are edited in the grid; every change is logged (History).
 */
require_once __DIR__ . '/price_common.php';
ph_page($conn, 'Sales Price View', 'price_view', 'SALES PRICE',
    ['description' => ['Description', 'text'], 'stock_qty' => ['Stock', 'qty'], 'mrp' => ['MRP', 'num']],
    ['default_unit_price', 'retail_a', 'retail_b', 'retail_c', 'dealer_a', 'dealer_b', 'dealer_c'],
    'Sales Price is the price filled in on sales screens.');
?>
<?php include "../../addon/conf/connection_close.php";  ?>
