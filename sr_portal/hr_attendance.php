<?php
/** My attendance: check in and out for today, and the days of a month. */
require_once __DIR__ . '/sr_common.php';
require_once dirname(__DIR__) . '/addon/inc/hr.php';
$a = sr_require_login();
$e = sr_hr_employee($a);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $e) {
    cp_check_csrf('hr_attendance.php');
    try {
        hr_mark_self($e['employee_id'], ($_POST['action'] ?? '') === 'in');
        cp_redirect('hr_attendance.php', 'ok', ($_POST['action'] ?? '') === 'in' ? 'Checked in.' : 'Checked out.');
    } catch (RuntimeException $x) {
        cp_redirect('hr_attendance.php', 'err', $x->getMessage());
    }
}
$e = sr_hr_page_start($a, 'My Attendance', 'attendance');
$id = (int) $e['employee_id'];
$month = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string) ($_GET['month'] ?? '')) ? $_GET['month'] . '-01' : date('Y-m-01');
$today = cp_row('SELECT * FROM hr_attendance WHERE employee_id=? AND attendance_date=CURDATE()', 'i', [$id]);
echo '<div class="card"><b>Today, ' . date('d/m/Y') . '</b><p>' . ($today && $today['check_in'] ? 'Checked in ' . date('h:i A', strtotime($today['check_in'])) . ($today['check_out'] ? ', out ' . date('h:i A', strtotime($today['check_out'])) : '') : 'Not checked in yet.') . '</p>';
if ($e['status'] === 'active' && !($today && $today['application_id']) && !($today && $today['check_out'])) {
    $in = !($today && $today['check_in']);
    echo '<form method="post">' . cp_csrf_field() . '<button class="btn" name="action" value="' . ($in ? 'in' : 'out') . '">' . ($in ? 'Check In' : 'Check Out') . '</button></form>';
}
echo '</div><form method="get" class="row"><div><label for="month">Month</label><input id="month" type="month" name="month" value="' . cp_h(substr($month, 0, 7)) . '"></div><div class="auto"><button class="btn">View</button></div></form>';
$counts = hr_month_counts($id, $month);
echo '<div class="tiles tiles-gap"><div class="tile ok"><span>Present</span><b>' . $counts['present'] . '</b></div><div class="tile"><span>Late</span><b>' . $counts['late'] . '</b></div><div class="tile' . ($counts['absent_days'] ? ' bad' : '') . '"><span>Absent</span><b>' . (float) $counts['absent_days'] . '</b></div><div class="tile"><span>Leave</span><b>' . ($counts['leave'] + $counts['half_leave'] * 0.5) . '</b></div></div>';
$names = HR_ATTENDANCE + ['leave' => 'Leave', 'half_leave' => 'Half Day Leave'];
$rows = cp_rows('SELECT * FROM hr_attendance WHERE employee_id=? AND attendance_date BETWEEN ? AND LAST_DAY(?) ORDER BY attendance_date DESC', 'iss', [$id, $month, $month]);
echo '<div class="scroll"><table><tr><th>Date</th><th>Status</th><th>In</th><th>Out</th><th>Note</th></tr>';
foreach ($rows as $r) {
    echo '<tr><td>' . cp_date($r['attendance_date']) . '</td><td>' . cp_h($names[$r['status']]) . '</td><td>' . ($r['check_in'] ? date('h:i A', strtotime($r['check_in'])) : '') . '</td><td>' . ($r['check_out'] ? date('h:i A', strtotime($r['check_out'])) : '') . '</td><td>' . cp_h($r['note']) . '</td></tr>';
}
echo ($rows ? '' : '<tr><td colspan="5" class="empty">No attendance recorded in this month.</td></tr>') . '</table></div>';
sr_page_end();
