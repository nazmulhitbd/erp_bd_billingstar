<?php
require_once __DIR__ . '/rma_common.php';

$page = 'delivery_store_fixing.php';
$purposes = [
    'complain_receive' => 'Complain Receive Store',
    'replace_out' => 'Replace Out Store',
    'replace_in' => 'Replace In Store',
    'delivery' => 'Replacement Delivery Store',
    'utilize' => 'Item Utilize Store',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    rma_require_csrf($page);
    $stores = rma_store_options();
    try {
        $conn = $GLOBALS['rma_conn'];
        $conn->begin_transaction();
        foreach ($purposes as $key => $label) {
            $store = rma_post('store_' . $key, 140);
            if ($store === '') {
                throw new RuntimeException('Select a store for ' . $label . '.');
            }
            if (!in_array($store, $stores, true)) {
                if (strtoupper($store) !== 'RMA STORE' && ($limit = licence_limit_error('warehouses')) !== '') { // a new store is one more warehouse
                    throw new RuntimeException($limit);
                }
                rma_exec('INSERT IGNORE INTO sales_stores (store_name) VALUES (?)', 's', [$store])->close();
            }
            rma_exec(
                'INSERT INTO rma_store_settings (purpose, store_name, updated_by) VALUES (?, ?, ?) '
                    . 'ON DUPLICATE KEY UPDATE store_name=VALUES(store_name), updated_by=VALUES(updated_by)',
                'ssi', [$key, $store, rma_user_id()]
            )->close();
        }
        $conn->commit();
        rma_flash('success', 'Store settings saved.');
    } catch (Throwable $error) {
        $GLOBALS['rma_conn']->rollback();
        rma_flash('error', $error->getMessage());
    }
    rma_redirect($page);
}

rma_page_header('RMA Delivery Store Fixing');
$ready = rma_schema_ready();
$stores = $ready ? rma_store_options() : [];
?>
<p class="muted">Choose the default store for each RMA operation. The selected store is pre-filled in the Store Name field of that screen.</p>
<form method="post" autocomplete="off">
<?= rma_csrf_field() ?>
<div class="rma-grid">
<?php foreach ($purposes as $key => $label): $current = $ready ? rma_store_default($key) : ''; ?>
  <div class="rma-field w6"><label for="s-<?= $key ?>" style="min-width:230px"><?= rma_h($label) ?></label>
  <input id="s-<?= $key ?>" name="store_<?= $key ?>" list="dl-stores" maxlength="140" value="<?= rma_h($current) ?>" required></div>
  <div class="w6"></div>
<?php endforeach; ?>
</div>
<datalist id="dl-stores"><?php foreach ($stores as $s): ?><option value="<?= rma_h($s) ?>"><?php endforeach; ?></datalist>
<div class="rma-toolbar"><button class="rma-btn"<?= $ready ? '' : ' disabled' ?>>Save</button><a class="rma-btn" href="<?= rma_h(rma_home_url()) ?>">Close</a></div>
</form>
<?php
rma_page_footer();
?>
<?php include "../../addon/conf/connection_close.php";  ?>
