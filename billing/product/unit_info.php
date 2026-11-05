<?php
/** Unit Information: units products are counted in (Pcs, Box, Kg ...). Renaming a unit renames it on its products. */
require_once __DIR__ . '/product_common.php';
pm_master_page($conn, [
    'title' => 'Unit Information', 'active' => 'unit', 'table' => 'product_units', 'pk' => 'unit_id', 'order' => 'unit_name',
    'fields' => ['unit_name' => ['Unit Name', 'text', ['max' => 40, 'required' => true]], 'short_name' => ['Short Name', 'text', ['max' => 20]],
        'allow_decimal' => ['Allow Decimal Quantity', 'check', ['default' => 0]], 'is_active' => ['Active', 'check']],
    'unique' => ['unit_name'],
    'usage' => function (mysqli $db, array $r) {
        $n = (int) sales_row($db, 'SELECT COUNT(*) AS n FROM sales_products WHERE unit_name=?', 's', [$r['unit_name']])['n'];
        return $n ? 'Unit "' . $r['unit_name'] . '" is used by ' . $n . ' product(s).' : '';
    },
    'after_save' => function (mysqli $db, ?array $old, array $new) {
        if ($old && $old['unit_name'] !== $new['unit_name']) {
            sales_exec($db, 'UPDATE sales_products SET unit_name=? WHERE unit_name=?', 'ss', [$new['unit_name'], $old['unit_name']])->close();
        }
    },
]);
?>
<?php include "../../addon/conf/connection_close.php";  ?>
