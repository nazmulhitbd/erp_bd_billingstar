<?php
/** Leave Applications: approve or reject what people applied for in the portal, or apply for someone from the office. */
require_once __DIR__ . '/hr_common.php';

$self = 'leave_applications.php';
$status = isset(HR_LEAVE_STATUS[$_REQUEST['status'] ?? '']) ? $_REQUEST['status'] : '';
$back = $self . ($status !== '' ? '?status=' . $status : '');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && hr_ready($conn)) {
    sales_require_csrf($back);
    $action = (string) ($_POST['action'] ?? '');
    $id = (int) ($_POST['application_id'] ?? 0);
    try {
        if ($action === 'add') {
            $e = sales_row($conn, 'SELECT * FROM hr_employees WHERE employee_id=?', 'i', [(int) ($_POST['employee_id'] ?? 0)]);
            if (!$e) {
                throw new RuntimeException('Pick an employee.');
            }
            hr_apply_leave($e, (int) ($_POST['leave_type_id'] ?? 0), (string) ($_POST['from_date'] ?? ''), (string) ($_POST['to_date'] ?? ''), isset($_POST['half_day']), (string) ($_POST['reason'] ?? ''), 'office');
            sales_flash_redirect($back, 'success', 'Application added. Approve it below.');
        }
        if ($action === 'approve' || $action === 'reject') {
            hr_decide_leave($id, $action === 'approve', hr_user_id(), sales_post_string('note', 190));
            sales_flash_redirect($back, 'success', $action === 'approve' ? 'Leave approved.' : 'Leave rejected.');
        }
        if ($action === 'cancel') {
            hr_cancel_leave($id, hr_user_id());
            sales_flash_redirect($back, 'success', 'Leave cancelled.');
        }
        throw new RuntimeException('Unknown action.');
    } catch (RuntimeException $e) {
        sales_flash_redirect($back, 'error', $e->getMessage());
    }
}

hr_page_start($conn, 'Leave Applications', 'leave');
$people = sales_rows($conn, "SELECT employee_id, CONCAT(full_name, ' (', employee_code, ')') AS name FROM hr_employees WHERE status='active' ORDER BY full_name");
$types = sales_rows($conn, 'SELECT leave_type_id, leave_name FROM hr_leave_types WHERE is_active=1 ORDER BY leave_name');
$where = $status !== '' ? 'a.status=?' : '1=1';
$rows = sales_list_rows($conn, "SELECT a.*, e.full_name, e.employee_code, t.leave_name, t.is_paid FROM hr_leave_applications a JOIN hr_employees e ON e.employee_id=a.employee_id JOIN hr_leave_types t ON t.leave_type_id=a.leave_type_id WHERE $where ORDER BY a.status='pending' DESC, a.application_id DESC", $status !== '' ? 's' : '', $status !== '' ? [$status] : []);
?>
<details class="section" style="border-top:0;margin-top:0;padding-top:0"><summary>Apply for someone</summary>
<form method="post" class="grid g4" autocomplete="off"><?= sales_csrf_field() ?>
<div class="field"><label for="employee_id">Employee</label><select id="employee_id" name="employee_id"><?= hr_options($people, 'employee_id', 'name', 0, 'Select') ?></select></div>
<div class="field"><label for="leave_type_id">Leave Type</label><select id="leave_type_id" name="leave_type_id"><?= hr_options($types, 'leave_type_id', 'leave_name', 0) ?></select></div>
<div class="field"><label for="from_date">From</label><input id="from_date" type="date" name="from_date" required></div>
<div class="field"><label for="to_date">To</label><input id="to_date" type="date" name="to_date" required></div>
<div class="field"><label for="reason">Reason</label><input id="reason" name="reason" maxlength="255" required></div>
<div class="field"><label><input class="check" type="checkbox" name="half_day"> Half day</label></div>
<div class="toolbar"><button class="btn primary" name="action" value="add">Add</button></div></form></details>
<form method="get" class="list-search"><select class="input" name="status" aria-label="Status"><option value="">All</option><?php foreach (HR_LEAVE_STATUS as $k => $label): ?><option value="<?= $k ?>"<?= $status === $k ? ' selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select><button class="btn">View</button></form>
<div class="table-scroll"><table><thead><tr><th>SL</th><th>Employee</th><th>Leave</th><th>From</th><th>To</th><th class="num">Days</th><th>Reason</th><th>Applied</th><th>Status</th><th></th></tr></thead><tbody>
<?php foreach ($rows as $i => $r): $badge = ['pending' => 'open', 'approved' => 'converted', 'rejected' => 'cancelled', 'cancelled' => ''][$r['status']]; ?>
<tr><td><?= $i + 1 ?></td><td><?= sales_h($r['full_name']) ?></td><td><?= sales_h($r['leave_name']) ?><?= (int) $r['is_paid'] ? '' : ' <span class="muted">(unpaid)</span>' ?></td><td><?= date('d/m/Y', strtotime($r['from_date'])) ?></td><td><?= date('d/m/Y', strtotime($r['to_date'])) ?></td><td class="num"><?= (float) $r['days'] ?></td><td><?= sales_h($r['reason']) ?></td><td><?= $r['applied_via'] === 'portal' ? 'Portal' : 'Office' ?></td><td><span class="badge <?= $badge ?>"><?= HR_LEAVE_STATUS[$r['status']] ?></span><?= $r['decision_note'] !== '' ? '<br><span class="muted">' . sales_h($r['decision_note']) . '</span>' : '' ?></td>
<td class="nowrap"><?php if ($r['status'] === 'pending' || $r['status'] === 'approved'): ?><form method="post" class="inline"><?= sales_csrf_field() ?><input type="hidden" name="application_id" value="<?= (int) $r['application_id'] ?>"><?php if ($r['status'] === 'pending'): ?><input name="note" maxlength="190" placeholder="Note" aria-label="Note" style="max-width:120px"><button class="btn small primary" name="action" value="approve">Approve</button><button class="btn small" name="action" value="reject">Reject</button><?php endif; ?><button class="btn small danger" name="action" value="cancel" onclick="return confirm('Cancel this leave?')">Cancel</button></form><?php endif; ?></td></tr>
<?php endforeach; ?>
<?= $rows ? '' : '<tr><td colspan="10" class="empty">No applications.</td></tr>' ?>
</tbody></table></div>
<?php mgmt_page_end(); ?>
<?php include "../../addon/conf/connection_close.php"; ?>
