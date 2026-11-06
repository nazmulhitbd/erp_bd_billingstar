<?php
/**
 * Product Information: the product list every screen sells and buys from (sales_products). Code is made automatically
 * when left blank; group, brand, unit and warranty come from their setup lists. A product already used on a document
 * cannot be deleted (make it inactive). Sales and purchase prices can also be set here or in bulk with Price Fixing.
 */
require_once __DIR__ . '/product_common.php';
pm_master_page($conn, [
    'title' => 'Product Information', 'active' => 'product', 'table' => 'sales_products', 'pk' => 'product_id', 'order' => 'product_group, brand, model_caption, description',
    'search' => ['product_code', 'model_caption', 'description', 'product_group', 'brand'],
    'list_fields' => ['product_code', 'product_group', 'brand', 'model_caption', 'description', 'unit_name', 'warranty', 'default_unit_price', 'purchase_price', 'serial_required', 'is_active'],
    'fields' => [
        'product_code' => ['Product Code', 'text', ['max' => 60, 'placeholder' => 'Auto: ' . docno_peek($conn, 'PRODUCT')]],
        'category_id' => ['Product Category', 'select', ['options' => fn($db) => pm_options($db, 'category')]],
        'product_group' => ['Product Group', 'select', ['options' => fn($db) => pm_options($db, 'group'), 'required' => true]],
        'brand' => ['Brand', 'select', ['options' => fn($db) => pm_options($db, 'brand')]],
        'model_caption' => ['Model No/Caption', 'text', ['max' => 190, 'required' => true]],
        'description' => ['Description', 'textarea', ['max' => 255, 'required' => true]],
        'unit_name' => ['Unit Name', 'select', ['options' => fn($db) => pm_options($db, 'unit'), 'required' => true, 'default' => 'Pcs']],
        'warranty' => ['Warranty', 'select', ['options' => fn($db) => pm_options($db, 'warranty')]],
        'default_unit_price' => ['Sales Price', 'number', ['step' => '0.01']],
        'purchase_price' => ['Purchase Price', 'number', ['step' => '0.01']],
        'reorder_level' => ['Reorder Level', 'number', ['step' => '0.001']],
        'vat_percent' => ['VAT %', 'number', ['step' => '0.01']],
        'serial_required' => ['Serial Required', 'check', ['default' => 0]],
        'is_active' => ['Active', 'check'],
    ],
    'validate' => function (mysqli $db, array &$d, ?array $old) {
        foreach (['product_group', 'brand', 'unit_name', 'warranty'] as $k) {
            $d[$k] = (string) ($d[$k] ?? '');
        }
        if ($d['vat_percent'] > 100) {
            throw new RuntimeException('VAT % cannot be more than 100.');
        }
        if ($d['product_code'] === '') {
            // Next free code like P000123, from the highest number used so far.
            $d['product_code'] = docno_next($db, 'PRODUCT'); // Settings > Document Numbering; never a duplicate
        } elseif (sales_row($db, 'SELECT 1 FROM sales_products WHERE product_code=? AND product_id<>?', 'si', [$d['product_code'], (int) ($old['product_id'] ?? 0)])) {
            throw new RuntimeException('Product code "' . $d['product_code'] . '" is already used.');
        }
    },
    'usage' => function (mysqli $db, array $r) {
        $id = (int) $r['product_id'];
        foreach (['inv_doc_lines' => 'stock and purchase documents', 'inv_stock_moves' => 'stock', 'sales_invoice_items' => 'sales invoices', 'sales_order_items' => 'sales orders'] as $table => $what) {
            if (sales_tables_ready($db, [$table]) && sales_row($db, "SELECT 1 FROM $table WHERE product_id=? LIMIT 1", 'i', [$id])) {
                return '"' . ($r['model_caption'] ?: $r['description']) . '" is used on ' . $what . '.';
            }
        }
        return '';
    },
    'after_save' => function (mysqli $db, ?array $old, array $new) {
        // Price changes made here are logged like the price screens.
        foreach (['default_unit_price', 'purchase_price'] as $field) {
            if ($old && abs((float) $old[$field] - (float) $new[$field]) > 0.004) {
                sales_insert($db, 'INSERT INTO inv_price_history (product_id, price_field, old_value, new_value, changed_by, changed_by_name) VALUES (?, ?, ?, ?, ?, ?)', 'isddis',
                    [(int) $new['product_id'], $field, (float) $old[$field], (float) $new[$field], (int) ($GLOBALS['user_id'] ?? 0), sales_cut((string) ($GLOBALS['user_name'] ?? ''), 120)]);
            }
        }
    },
]);
?>
<?php include "../../addon/conf/connection_close.php";  ?>
