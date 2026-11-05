<?php
/**
 * Sales Price MRP View: per product the sales price against the MRP band (MRP, Lower Price, Higher Price), picked
 * Group / Brand / Group and Brand / Model Wise, with a printable Preview. The MRP band is edited in the grid; every
 * change is logged (History).
 */
require_once __DIR__ . '/price_common.php';
ph_page($conn, 'Sales Price MRP View', 'mrp_view', 'MRP',
    ['description' => ['Description', 'text'], 'sales_price' => ['Sales Price', 'num'], 'avg_cost' => ['Avg Cost', 'num'], 'margin' => ['Margin', 'pct']],
    ['mrp', 'lower_price', 'higher_price'],
    'Lower Price may not be above Higher Price. Margin is (Sales Price - Avg Cost) / Sales Price.');
?>
<?php include "../../addon/conf/connection_close.php";  ?>
