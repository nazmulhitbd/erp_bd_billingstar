<?php
/** Attendance Register: one person per row, one day per column, with the month's totals. */
require_once __DIR__ . '/hr_common.php';

hr_page_start($conn, 'Attendance Register', 'register');
$month = hr_month($_GET['month'] ?? '');
$end = date('Y-m-t', strtotime($month));
$department = (int) ($_GET['department'] ?? 0);
$days = (int) date('t', strtotime($month));
$off = hr_off_days($month, $end);
$staff = sales_rows($conn, "SELECT e.* FROM hr_employees e WHERE e.status<>'onboarding' AND e.join_date<=? AND (e.exit_date IS NULL OR e.exit_date>=?) AND (?=0 OR e.department_id=?) ORDER BY e.full_name", 'ssii', [$end, $month, $department, $department]);
$grid = [];
foreach (sales_rows($conn, 'SELECT employee_id, DAY(attendance_date) AS d, status FROM hr_attendance WHERE attendance_date BETWEEN ? AND ?', 'ss', [$month, $end]) as $r) {
    $grid[(int) $r['employee_id']][(int) $r['d']] = $r['status'];
}
$departments = sales_rows($conn, 'SELECT department_id, department_name FROM hr_departments ORDER BY department_name');
?>
<form method="get" class="list-search"><input class="input" type="month" name="month" value="<?= substr($month, 0, 7) ?>" aria-label="Month"><select class="input" name="department" aria-label="Department"><?= hr_options($departments, 'department_id', 'department_name', $department, 'All departments') ?></select><button class="btn">View</button></form>
<p class="footnote">P Present · L Late · H Half Day · A Absent · LV Leave · HL Half Day Leave · grey column: weekly off or holiday</p>
<div class="table-scroll"><table><thead><tr><th>Name</th><?php for ($d = 1; $d <= $days; $d++): ?><th class="center" style="text-align:center<?= isset($off[date('Y-m-', strtotime($month)) . str_pad((string) $d, 2, '0', STR_PAD_LEFT)]) ? ';background:#ddd!important' : '' ?>"><?= $d ?></th><?php endfor; ?><th class="num">P</th><th class="num">L</th><th class="num">A</th><th class="num">LV</th></tr></thead><tbody>
<?php foreach ($staff as $e): $row = $grid[(int) $e['employee_id']] ?? []; $count = array_count_values($row); ?>
<tr><td class="nowrap"><?= sales_h($e['full_name']) ?></td><?php for ($d = 1; $d <= $days; $d++): ?><td style="text-align:center"><?= isset($row[$d]) ? HR_ATTENDANCE_MARK[$row[$d]] : '' ?></td><?php endfor; ?>
<td class="num"><?= ($count['present'] ?? 0) ?></td><td class="num"><?= ($count['late'] ?? 0) ?></td><td class="num"><?= ($count['absent'] ?? 0) + ($count['half_day'] ?? 0) * 0.5 ?></td><td class="num"><?= ($count['leave'] ?? 0) + ($count['half_leave'] ?? 0) * 0.5 ?></td></tr>
<?php endforeach; ?>
<?= $staff ? '' : '<tr><td colspan="' . ($days + 5) . '" class="empty">Nobody on the books in this month.</td></tr>' ?>
</tbody></table></div>
<?php mgmt_page_end(); ?>
<?php include "../../addon/conf/connection_close.php"; ?>
