<?php
/**
 * Approvals for sales and inventory documents (Approve > Sales / Purchase / Transfer).
 * ?doc= picks the document type. Approved documents can no longer be edited.
 */
require_once dirname(__DIR__) . '/inventory/inv_common.php';
inv_require_ready($conn, 'Approve', 'approve');

/** Document types: table, key and column names, list filter and print link. */
function inv_approve_docs()
{
    $docs = [
        'sales' => ['title' => 'Sales Approve', 'table' => 'sales_invoices', 'pk' => 'invoice_id', 'no' => 'invoice_no', 'date' => 'sales_date', 'name' => 'customer_name', 'amount' => 'net_amount', 'group' => 'customer_group', 'where' => "status='posted'", 'print' => '../sales/sales_invoice/sales_invoice.php?print='],
        'sales_return' => ['title' => 'Sales Return Approve', 'table' => 'sales_returns', 'pk' => 'return_id', 'no' => 'return_no', 'date' => 'return_date', 'name' => 'customer_name', 'amount' => 'return_amount', 'group' => null, 'where' => "status='posted'", 'print' => '../sales/sales_retrun/sales_return.php?print='],
        'sales_order' => ['title' => 'Sales Order Approve', 'table' => 'sales_orders', 'pk' => 'order_id', 'no' => 'order_no', 'date' => 'order_date', 'name' => 'customer_name', 'amount' => 'net_amount', 'group' => 'customer_group', 'where' => "status<>'cancelled'", 'print' => '../sales/sales_order/sales_order.php?print='],
    ];
    foreach (INV_TYPES as $type => $t) {
        if ($type === 'challan_bill' || !empty($t['linked'])) {
            continue; // Approved through Sales, as the sales invoice it creates.
        }
        $docs[$type] = ['title' => $t['title'] . ' Approve', 'table' => 'inv_docs', 'pk' => 'doc_id', 'no' => 'doc_no', 'date' => 'doc_date', 'name' => 'party_name', 'amount' => 'net_amount', 'group' => 'party_group', 'where' => "doc_type='" . $type . "' AND status='active'", 'print' => '../' . $t['url'] . '?print='];
    }
    return $docs;
}

$docs = inv_approve_docs();
$key = isset($docs[$_REQUEST['doc'] ?? '']) ? (string) $_REQUEST['doc'] : 'sales';
$d = $docs[$key];
$ready = sales_tables_ready($conn, [$d['table']]) && (bool) sales_row($conn, "SELECT COUNT(*) AS n FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name='approval_status' HAVING n>0", 's', [$d['table']]);
$self = 'approve.php?doc=' . $key;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $ready) {
    sales_require_csrf($self);
    $ids = array_values(array_unique(array_filter(array_map('intval', (array) ($_POST['ids'] ?? [])))));
    try {
        if (!$ids) {
            throw new RuntimeException('Tick at least one document to approve.');
        }
        $pending = sales_rows($conn, "SELECT * FROM {$d['table']} WHERE {$d['pk']} IN (" . implode(',', array_map('intval', $ids)) . ") AND approval_status='pending' AND {$d['where']}");
        $sql = "UPDATE {$d['table']} SET approval_status='approved', approved_by=?, approved_at=NOW() WHERE {$d['pk']} IN (" . implode(',', array_fill(0, count($ids), '?')) . ") AND approval_status='pending' AND {$d['where']}";
        $stmt = sales_exec($conn, $sql, 'i' . str_repeat('i', count($ids)), array_merge([(int) ($GLOBALS['user_id'] ?? 0)], $ids));
        $n = $stmt->affected_rows;
        $stmt->close();
        // Sales invoices and returns carry their stock movement in a linked inv_docs row; approve it with them.
        $linked = ['sales' => ['sales_invoice', 'sales_invoices', 'invoice_id'], 'sales_return' => ['sales_return', 'sales_returns', 'return_id']][$key] ?? null;
        if ($linked && sales_tables_ready($conn, ['inv_docs'])) {
            [$stockType, $table, $pk] = $linked;
            $hasLink = (bool) sales_row($conn, "SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name='stock_doc_id'", 's', [$table]);
            if ($hasLink) {
                sales_exec($conn, "UPDATE inv_docs d JOIN $table s ON s.stock_doc_id=d.doc_id SET d.approval_status='approved', d.approved_by=?, d.approved_at=NOW() WHERE d.doc_type=? AND s.$pk IN (" . implode(',', array_fill(0, count($ids), '?')) . ") AND s.approval_status='approved'",
                    'is' . str_repeat('i', count($ids)), array_merge([(int) ($GLOBALS['user_id'] ?? 0), $stockType], $ids))->close();
            }
        }
        $label = trim(str_replace(' Approve', '', $d['title']));
        foreach ($pending as $row) {
            $no = $row[$d['no']];
            notify_user($row['created_by'] ?? 0, $label . ' approved: ' . $no, (string) $row[$d['name']]);
            $customer = (int) ($row['customer_id'] ?? 0) ?: (in_array($key, ['quotation', 'challan', 'challan_return'], true) ? (int) ($row['party_id'] ?? 0) : 0);
            $page = ['sales' => 'invoices.php', 'sales_order' => 'orders.php', 'quotation' => 'orders.php'][$key] ?? '';
            if ($customer) {
                notify_customer($customer, $label . ' ready: ' . $no, 'Amount ' . sales_money($row[$d['amount']]), $page);
            }
            if ((int) ($row['sr_id'] ?? 0)) {
                notify_staff((int) $row['sr_id'], $label . ' approved: ' . $no, (string) $row[$d['name']], $key === 'sales_order' ? 'orders.php?id=' . (int) $row[$d['pk']] : '');
            }
        }
        sales_flash_redirect($self, 'success', $n . ' document(s) approved.');
    } catch (Throwable $e) {
        sales_flash_redirect($self, 'error', $e->getMessage());
    }
}

$flash = sales_take_flash();
$mode = in_array(($_GET['mode'] ?? 'all'), ['all', 'group', 'trader'], true) ? (string) ($_GET['mode'] ?? 'all') : 'all';
$term = trim((string) ($_GET['term'] ?? ''));
$allDate = !isset($_GET['from']) || !empty($_GET['all_date']);
$from = sales_valid_date($_GET['from'] ?? '', true) ?: date('Y-m-01');
$to = sales_valid_date($_GET['to'] ?? '', true) ?: date('Y-m-d');
$rows = [];
if ($ready) {
    $where = ["approval_status='pending'", $d['where']];
    $types = '';
    $params = [];
    if (!$allDate) {
        $where[] = "{$d['date']} BETWEEN ? AND ?";
        $types .= 'ss';
        array_push($params, $from, $to);
    }
    if ($mode === 'group' && $term !== '' && $d['group']) {
        $where[] = "{$d['group']} LIKE ?";
        $types .= 's';
        $params[] = '%' . $term . '%';
    }
    if ($mode === 'trader' && $term !== '') {
        $where[] = "{$d['name']} LIKE ?";
        $types .= 's';
        $params[] = '%' . $term . '%';
    }
    $rows = sales_list_rows($conn, "SELECT {$d['pk']} AS id, {$d['no']} AS no, {$d['date']} AS dt, {$d['name']} AS name, {$d['amount']} AS amount, created_by FROM {$d['table']} WHERE " . implode(' AND ', $where) . " ORDER BY {$d['date']}, {$d['pk']}", $types, $params);
}
$users = [];
if (sales_tables_ready($conn, ['admin_user'])) {
    foreach (sales_rows($conn, 'SELECT user_id, user_name FROM admin_user') as $u) {
        $users[(int) $u['user_id']] = $u['user_name'];
    }
}

inv_page_start($conn, $d['title'], 'approve');
echo sales_flash_html($flash);
?>
<fieldset class="box"><legend><?= sales_h($d['title']) ?></legend>
<form method="get" id="filter"><input type="hidden" name="doc" value="<?= sales_h($key) ?>">
<div class="list-search"><select class="input" name="doc" onchange="this.form.submit()" style="max-width:260px"><?php foreach ($docs as $k => $x): ?><option value="<?= $k ?>" <?= $k === $key ? 'selected' : '' ?>><?= sales_h($x['title']) ?></option><?php endforeach; ?></select></div>
<div class="radios"><label><input type="radio" name="mode" value="all" <?= $mode === 'all' ? 'checked' : '' ?>> All</label><?php if ($d['group']): ?><label><input type="radio" name="mode" value="group" <?= $mode === 'group' ? 'checked' : '' ?>> Group Wise</label><?php endif; ?><label><input type="radio" name="mode" value="trader" <?= $mode === 'trader' ? 'checked' : '' ?>> Trader Wise</label>
<input class="input" name="term" value="<?= sales_h($term) ?>" placeholder="Group or trader name" style="max-width:240px"></div>
<div class="list-search"><label style="align-self:center"><input class="check" type="checkbox" name="all_date" value="1" <?= $allDate ? 'checked' : '' ?>> All Date</label><label style="align-self:center">Date From</label><input class="input" type="date" name="from" value="<?= sales_h($from) ?>" style="max-width:170px"><label style="align-self:center">Date To</label><input class="input" type="date" name="to" value="<?= sales_h($to) ?>" style="max-width:170px"><button class="btn">Show</button></div>
</form>
<?php if (!$ready): ?><div class="errorbox">This document type is not installed, or its approval columns are missing. Run <code>billing/inventory/schema.sql</code> after the module's own schema.</div><?php else: ?>
<form method="post" id="approve-form"><?= sales_csrf_field() ?>
<div class="table-scroll" style="max-height:560px"><table><thead><tr><th>SL No</th><th>Invoice No</th><th>Date</th><th>Customer Name</th><th class="num">Amount</th><th>User</th><th><input class="check" type="checkbox" id="tick-all"></th><th>Approve</th><th>Preview</th><th>Print</th></tr></thead><tbody>
<?php if (!$rows): ?><tr><td colspan="10" class="empty">Nothing waiting for approval.</td></tr><?php endif; ?>
<?php foreach ($rows as $i => $r): ?><tr><td><?= $i + 1 ?></td><td><?= sales_h($r['no']) ?></td><td><?= inv_fmt_date($r['dt']) ?></td><td><?= sales_h($r['name']) ?></td><td class="num"><?= sales_money($r['amount']) ?></td><td><?= sales_h($users[(int) $r['created_by']] ?? ($r['created_by'] ? '#' . (int) $r['created_by'] : '')) ?></td>
<td><input class="check" type="checkbox" name="ids[]" value="<?= (int) $r['id'] ?>"></td><td><button class="btn small primary" name="ids[]" value="<?= (int) $r['id'] ?>" formnovalidate onclick="document.querySelectorAll('input[name=\'ids[]\']').forEach(c=>c.checked=false)">Approve</button></td>
<td><a class="btn small" target="_blank" href="<?= sales_h($d['print'] . (int) $r['id']) ?>">Preview</a></td><td><a class="btn small" target="_blank" href="<?= sales_h($d['print'] . (int) $r['id']) ?>&auto=1">Print</a></td></tr><?php endforeach; ?>
</tbody></table></div>
<div class="toolbar" style="margin-top:10px"><button class="btn primary">Approve</button><a class="btn" href="javascript:history.back()">Close</a></div>
</form>
<?php endif; ?>
</fieldset>
<?php
inv_page_end('document.getElementById("tick-all")?.addEventListener("change",e=>document.querySelectorAll("input[name=\'ids[]\']").forEach(c=>c.checked=e.target.checked));');
?>
<?php include "../../addon/conf/connection_close.php";  ?>
