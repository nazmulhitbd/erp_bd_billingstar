<?php
/** Warranty: warranty terms with their length, used on products, sales lines and Serial History (warranty end date). */
require_once __DIR__ . '/product_common.php';
pm_master_page($conn, [
    'title' => 'Warranty', 'active' => 'warranty', 'table' => 'product_warranties', 'pk' => 'warranty_id', 'order' => "FIELD(period_unit,'none','day','month','year'), period, warranty_name",
    'fields' => ['warranty_name' => ['Warranty Name', 'text', ['max' => 100, 'required' => true]], 'period' => ['Period', 'number'],
        'period_unit' => ['Period Unit', 'select', ['options' => ['none' => 'No warranty', 'day' => 'Day(s)', 'month' => 'Month(s)', 'year' => 'Year(s)'], 'required' => true, 'default' => 'month']],
        'terms' => ['Terms', 'textarea', ['max' => 255]], 'is_active' => ['Active', 'check']],
    'unique' => ['warranty_name'],
    'validate' => function (mysqli $db, array &$d, ?array $old) {
        if ($d['period_unit'] !== 'none' && (int) $d['period'] <= 0) {
            throw new RuntimeException('Enter the warranty period, or pick No warranty.');
        }
        if ($d['period_unit'] === 'none') {
            $d['period'] = 0;
        }
    },
    'usage' => function (mysqli $db, array $r) {
        $n = (int) sales_row($db, 'SELECT COUNT(*) AS n FROM sales_products WHERE warranty=?', 's', [$r['warranty_name']])['n']
            + (int) sales_row($db, 'SELECT COUNT(*) AS n FROM product_periodic_warranties WHERE warranty_id=?', 'i', [(int) $r['warranty_id']])['n'];
        return $n ? 'Warranty "' . $r['warranty_name'] . '" is used by products or periodical warranty rules.' : '';
    },
    'after_save' => function (mysqli $db, ?array $old, array $new) {
        if ($old && $old['warranty_name'] !== $new['warranty_name']) {
            sales_exec($db, 'UPDATE sales_products SET warranty=? WHERE warranty=?', 'ss', [$new['warranty_name'], $old['warranty_name']])->close();
        }
    },
]);
?>
<?php include "../../addon/conf/connection_close.php";  ?>
