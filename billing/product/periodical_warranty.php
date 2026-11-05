<?php
/**
 * Periodical Warranty: a warranty that applies for a date range to one product, a product group or a brand
 * (e.g. a brand campaign). While a rule is active, sales screens offer its warranty; product rules beat group rules,
 * which beat brand rules.
 */
require_once __DIR__ . '/product_common.php';
pm_master_page($conn, [
    'title' => 'Periodical Warranty', 'active' => 'periodic', 'table' => 'product_periodic_warranties', 'pk' => 'pw_id', 'order' => 'from_date DESC, pw_id DESC',
    'fields' => [
        'scope' => ['Applies To', 'select', ['options' => ['product' => 'One product', 'group' => 'Product group', 'brand' => 'Brand'], 'required' => true, 'default' => 'group']],
        'scope_value' => ['Group / Brand Name', 'text', ['max' => 140]],
        'product_id' => ['Product', 'select', ['options' => fn($db) => array_column(sales_rows($db, "SELECT product_id, CONCAT_WS(' ', NULLIF(product_code, ''), COALESCE(NULLIF(model_caption, ''), description)) AS n FROM sales_products WHERE is_active=1 ORDER BY n LIMIT 3000"), 'n', 'product_id')]],
        'from_date' => ['From Date', 'date', ['required' => true]], 'to_date' => ['To Date', 'date', ['required' => true]],
        'warranty_id' => ['Warranty', 'select', ['options' => fn($db) => pm_options($db, 'warranty_id'), 'required' => true]],
        'note' => ['Note', 'textarea', ['max' => 190]], 'is_active' => ['Active', 'check'],
    ],
    'validate' => function (mysqli $db, array &$d, ?array $old) {
        if ($d['to_date'] < $d['from_date']) {
            throw new RuntimeException('To Date cannot be before From Date.');
        }
        if ($d['scope'] === 'product') {
            if (!$d['product_id']) {
                throw new RuntimeException('Pick the product.');
            }
            $d['scope_value'] = '';
        } else {
            $d['product_id'] = null;
            if ($d['scope_value'] === '' || !sales_row($db, 'SELECT 1 FROM product_classifications WHERE class_type=? AND class_name=?', 'ss', [$d['scope'], $d['scope_value']])) {
                throw new RuntimeException('Enter a ' . ($d['scope'] === 'group' ? 'product group' : 'brand') . ' from Product Classification.');
            }
        }
    },
    'list' => [['Status', fn($r) => !(int) $r['is_active'] ? 'Inactive' : ($r['to_date'] < date('Y-m-d') ? 'Ended' : ($r['from_date'] > date('Y-m-d') ? 'Upcoming' : 'Running'))]],
]);
?>
<?php include "../../addon/conf/connection_close.php";  ?>
