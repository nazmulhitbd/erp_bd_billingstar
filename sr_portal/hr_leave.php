<?php
/** My leave: what is left this year, apply, and follow or cancel an application. */
require_once __DIR__ . '/sr_common.php';
require_once dirname(__DIR__) . '/addon/inc/hr.php';
$a = sr_require_login();
$e = sr_hr_employee($a);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $e) {
    cp_check_csrf('hr_leave.php');
    try {
        if (($_POST['action'] ?? '') === 'cancel') {
            hr_cancel_leave((int) ($_POST['application_id'] ?? 0), 0, $e['employee_id']); // only their own, and only while pending
            cp_redirect('hr_leave.php', 'ok', 'Application cancelled.');
        }
        if ($e['status'] !== 'active') {
            throw new RuntimeException('Leave can be applied for once you are active. Please ask the office.');
        }
        hr_apply_leave($e, (int) ($_POST['leave_type_id'] ?? 0), (string) ($_POST['from_date'] ?? ''), (string) ($_POST['to_date'] ?? ''), isset($_POST['half_day']), (string) ($_POST['reason'] ?? ''), 'portal');
        cp_redirect('hr_leave.php', 'ok', 'Your application has been sent to the office.');
    } catch (RuntimeException $x) {
        cp_redirect('hr_leave.php', 'err', $x->getMessage());
    }
}
$e = sr_hr_page_start($a, 'My Leave', 'leave');
$id = (int) $e['employee_id'];
$year = (int) date('Y');
$balances = hr_leave_balances($id, $year);
echo '<h2>Leave in ' . $year . '</h2><div class="scroll"><table><tr><th>Leave</th><th class="num">Assigned</th><th class="num">Used</th><th class="num">Waiting</th><th class="num">Left</th></tr>';
foreach ($balances as $b) {
    if (!(int) $b['is_active']) {
        continue;
    }
    $paid = (int) $b['is_paid'];
    echo '<tr><td>' . cp_h($b['leave_name']) . '</td><td class="num">' . ($paid ? (float) $b['allocated'] : '-') . '</td><td class="num">' . (float) $b['used'] . '</td><td class="num">' . (float) $b['pending'] . '</td><td class="num">' . ($paid ? (float) $b['left'] : 'Unpaid') . '</td></tr>';
}
echo '</table></div><h2>Apply for leave</h2><form method="post" class="card grid">' . cp_csrf_field() . '<div><label for="leave_type_id">Leave</label><select id="leave_type_id" name="leave_type_id" required>';
foreach ($balances as $tid => $b) {
    if ((int) $b['is_active']) {
        echo '<option value="' . (int) $tid . '">' . cp_h($b['leave_name']) . '</option>';
    }
}
echo '</select></div><div class="row"><div><label for="from_date">First day</label><input id="from_date" type="date" name="from_date" required></div><div><label for="to_date">Last day</label><input id="to_date" type="date" name="to_date" required></div></div>'
    . '<div><label class="check-line"><input type="checkbox" name="half_day"> Half day (one date only)</label></div><div><label for="reason">Reason</label><input id="reason" name="reason" maxlength="255" required></div><div><button class="btn" name="action" value="apply">Apply</button></div></form>';
$rows = cp_rows('SELECT a.*, t.leave_name FROM hr_leave_applications a JOIN hr_leave_types t ON t.leave_type_id=a.leave_type_id WHERE a.employee_id=? ORDER BY a.application_id DESC', 'i', [$id]);
echo '<h2>My applications</h2><div class="scroll"><table><tr><th>Leave</th><th>From</th><th>To</th><th class="num">Days</th><th>Reason</th><th>Status</th><th></th></tr>';
foreach ($rows as $r) {
    $badge = ['pending' => 'warn', 'approved' => 'ok', 'rejected' => 'bad', 'cancelled' => ''][$r['status']];
    echo '<tr><td>' . cp_h($r['leave_name']) . '</td><td>' . cp_date($r['from_date']) . '</td><td>' . cp_date($r['to_date']) . '</td><td class="num">' . (float) $r['days'] . '</td><td>' . cp_h($r['reason']) . '</td><td><span class="badge ' . $badge . '">' . HR_LEAVE_STATUS[$r['status']] . '</span>' . ($r['decision_note'] !== '' ? '<br><span class="muted">' . cp_h($r['decision_note']) . '</span>' : '') . '</td><td>';
    if ($r['status'] === 'pending') {
        echo '<form method="post" class="inline">' . cp_csrf_field() . '<input type="hidden" name="application_id" value="' . (int) $r['application_id'] . '"><button class="btn small danger" name="action" value="cancel" data-confirm="Cancel this application?">Cancel</button></form>';
    }
    echo '</td></tr>';
}
echo ($rows ? '' : '<tr><td colspan="7" class="empty">No applications yet.</td></tr>') . '</table></div>';
sr_page_end();
