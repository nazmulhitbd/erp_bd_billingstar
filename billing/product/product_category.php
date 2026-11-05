<?php
/** Product Category: top-level kinds of goods (e.g. Computer, Mobile, Accessories). Products and product groups belong to one. */
require_once __DIR__ . '/product_common.php';
pm_master_page($conn, [
    'title' => 'Product Category', 'active' => 'category', 'table' => 'product_categories', 'pk' => 'category_id', 'order' => 'sort_order, category_name',
    'fields' => ['category_name' => ['Category Name', 'text', ['max' => 120, 'required' => true]], 'description' => ['Description', 'textarea', ['max' => 255]],
        'sort_order' => ['Sort Order', 'number'], 'is_active' => ['Active', 'check']],
    'unique' => ['category_name'],
    'list' => [['Products', fn($r) => (string) sales_row($GLOBALS['conn'], 'SELECT COUNT(*) AS n FROM sales_products WHERE category_id=?', 'i', [(int) $r['category_id']])['n']]],
    'usage' => function (mysqli $db, array $r) {
        $n = (int) sales_row($db, 'SELECT COUNT(*) AS n FROM sales_products WHERE category_id=?', 'i', [(int) $r['category_id']])['n'];
        return $n ? 'Category "' . $r['category_name'] . '" has ' . $n . ' product(s).' : '';
    },
]);
?>
<?php include "../../addon/conf/connection_close.php";  ?>
