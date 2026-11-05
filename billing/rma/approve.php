<?php
require_once __DIR__ . '/rma_docs.php';

$titles = [
    'complain' => 'Complain Receive Approve',
    'replace_out' => 'Replace Out Approve',
    'replace_in' => 'Replace In Approve',
    'delivery' => 'RMA Replacement Delivery Approve',
    'service_bill' => 'RMA Service Bill Approve',
];
$type = (string) ($_GET['type'] ?? $_POST['type'] ?? '');
if (!isset($titles[$type])) {
    http_response_code(404);
    exit('Unknown approval type.');
}
$cfg = rma_doc_config($type);
$self = 'approve.php?type=' . urlencode($type);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    rma_require_csrf($self);
    $ids = array_values(array_unique(array_filter(array_map('intval', explode(',', (string) ($_POST['ids'] ?? ''))))));
    $done = [];
    $errors = [];
    foreach (array_slice($ids, 0, 200) as $id) {
        $conn = $GLOBALS['rma_conn'];
        $conn->begin_transaction();
        try {
            $head = rma_row("SELECT * FROM {$cfg['table']} WHERE {$cfg['pk']}=?", 'i', [$id]);
            $done[] = rma_approve_document($cfg, $id);
            $title = $titles[$type];
            notify_user($head['created_by'] ?? 0, $title . 'd: ' . $head[$cfg['no']], (string) $head[$cfg['party']]);
            if (!empty($head['customer_id'])) {
                notify_customer($head['customer_id'], 'Service update: ' . $head[$cfg['no']], ['complain' => 'We have received your item.', 'delivery' => 'Your replacement is being delivered.', 'service_bill' => 'Your service bill is ready.'][$type] ?? 'Your service job has been updated.', 'warranty.php');
            }
            $conn->commit();
        } catch (Throwable $error) {
            $conn->rollback();
            error_log('RMA approve failed: ' . $error->getMessage());
            $errors[] = $error->getMessage();
        }
    }
    if ($errors) {
        rma_flash('error', ($done ? 'Approved: ' . implode(', ', $done) . '. ' : '') . 'Not approved: ' . implode(' ', $errors));
    } else {
        rma_flash('success', 'Approved: ' . implode(', ', $done) . '.');
    }
    rma_redirect($self . '&' . http_build_query(array_intersect_key($_POST, array_flip(['all_date', 'from', 'to']))) . '&show=1');
}

$allDate = !isset($_GET['show']) || !empty($_GET['all_date']);
$from = rma_date_or_null($_GET['from'] ?? '') ?: null;
$to = rma_date_or_null($_GET['to'] ?? '') ?: null;
$rows = [];
if (rma_schema_ready()) {
    $where = "status='draft'";
    $types = '';
    $params = [];
    if (!$allDate && $from) {
        $where .= " AND {$cfg['date']}>=?";
        $types .= 's';
        $params[] = $from;
    }
    if (!$allDate && $to) {
        $where .= " AND {$cfg['date']}<=?";
        $types .= 's';
        $params[] = $to;
    }
    $rows = rma_list_rows(
        "SELECT {$cfg['pk']} AS id, {$cfg['no']} AS doc_no, {$cfg['date']} AS doc_date, {$cfg['party']} AS party, created_by_name "
            . "FROM {$cfg['table']} WHERE $where ORDER BY {$cfg['pk']}",
        $types, $params
    );
}

rma_page_header($titles[$type]);
?>
<form method="get" class="rma-filter">
  <input type="hidden" name="type" value="<?= rma_h($type) ?>"><input type="hidden" name="show" value="1">
  <label>ALL Date <input type="checkbox" id="all-date" name="all_date" value="1" <?= $allDate ? 'checked' : '' ?>></label>
  <label>Date From <input type="date" class="date-range" name="from" value="<?= rma_h($from) ?>"></label>
  <label>Date To <input type="date" class="date-range" name="to" value="<?= rma_h($to) ?>"></label>
  <div style="flex-basis:100%"></div>
  <button class="rma-btn">Show</button>
</form>
<form method="post" id="approve-form">
<?= rma_csrf_field() ?>
<input type="hidden" name="type" value="<?= rma_h($type) ?>"><input type="hidden" name="ids" id="approve-ids">
<input type="hidden" name="all_date" value="<?= $allDate ? '1' : '' ?>"><input type="hidden" name="from" value="<?= rma_h($from) ?>"><input type="hidden" name="to" value="<?= rma_h($to) ?>">
<div class="rma-grid-wrap"><table class="rma-table">
<thead><tr><th>SL No</th><th>Invoice No</th><th>Date</th><th><?= rma_h($cfg['party_label']) ?></th><th>User Name</th><th class="center"><input type="checkbox" id="chk-all" title="Select all"></th><th>Approve</th><th>View</th><th>Print</th></tr></thead>
<tbody>
<?php if (!$rows): ?><tr><td colspan="9" class="empty">No records waiting for approval.</td></tr><?php endif; ?>
<?php foreach ($rows as $i => $r): ?>
<tr>
  <td><?= $i + 1 ?></td><td><?= rma_h($r['doc_no']) ?></td><td><?= rma_h(date('d/m/Y', strtotime($r['doc_date']))) ?></td>
  <td><?= rma_h($r['party']) ?></td><td><?= rma_h($r['created_by_name']) ?></td>
  <td class="center"><input type="checkbox" class="chk-row" value="<?= (int) $r['id'] ?>"></td>
  <td><button type="button" class="rma-btn" data-approve="<?= (int) $r['id'] ?>">Approve</button></td>
  <td><a class="rma-btn" href="<?= rma_h($cfg['page']) ?>?id=<?= (int) $r['id'] ?>">View</a></td>
  <td><a class="rma-btn" target="_blank" href="<?= rma_h($cfg['page']) ?>?print=<?= (int) $r['id'] ?>&amp;auto=1">Print</a></td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
<div class="rma-toolbar"><button type="button" class="rma-btn" id="approve-selected">Approve</button><a class="rma-btn" href="<?= rma_h(rma_home_url()) ?>">Close</a></div>
</form>
<?php
rma_page_footer('RMA.initApprove();');
?>
<?php include "../../addon/conf/connection_close.php";  ?>
