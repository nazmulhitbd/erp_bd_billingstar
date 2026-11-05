<?php
/**
 * Product Classification: the product groups (with their category) and brands products are filed under.
 * Renaming one renames it on every product too; one in use cannot be deleted.
 */
require_once __DIR__ . '/product_common.php';
$col = fn(array $r) => $r['class_type'] === 'group' ? 'product_group' : 'brand';
pm_master_page($conn, [
    'title' => 'Product Classification', 'active' => 'classification', 'table' => 'product_classifications', 'pk' => 'class_id', 'order' => 'class_name',
    'fixed' => ['class_type', ['group' => 'Product Group', 'brand' => 'Brand']],
    'fields' => ['class_name' => ['Name', 'text', ['max' => 140, 'required' => true]], 'category_id' => ['Product Category (groups)', 'select', ['options' => fn($db) => pm_options($db, 'category')]],
        'is_active' => ['Active', 'check']],
    'unique' => ['class_type', 'class_name'],
    'list' => [['Products', fn($r) => (string) sales_row($GLOBALS['conn'], 'SELECT COUNT(*) AS n FROM sales_products WHERE ' . ($r['class_type'] === 'group' ? 'product_group' : 'brand') . '=?', 's', [$r['class_name']])['n']]],
    'usage' => function (mysqli $db, array $r) use ($col) {
        $n = (int) sales_row($db, 'SELECT COUNT(*) AS n FROM sales_products WHERE ' . $col($r) . '=?', 's', [$r['class_name']])['n'];
        return $n ? '"' . $r['class_name'] . '" is used by ' . $n . ' product(s).' : '';
    },
    'after_save' => function (mysqli $db, ?array $old, array $new) use ($col) {
        if ($old && $old['class_name'] !== $new['class_name']) {
            sales_exec($db, 'UPDATE sales_products SET ' . $col($new) . '=? WHERE ' . $col($new) . '=?', 'ss', [$new['class_name'], $old['class_name']])->close();
            if ($new['class_type'] === 'group' || $new['class_type'] === 'brand') {
                sales_exec($db, 'UPDATE product_periodic_warranties SET scope_value=? WHERE scope=? AND scope_value=?', 'sss', [$new['class_name'], $new['class_type'], $old['class_name']])->close();
            }
        }
    },
]);
?>
<?php include "../../addon/conf/connection_close.php";  ?>
