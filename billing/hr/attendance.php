<?php
/** Daily Attendance: mark everyone for a date. Days from an approved leave are shown but cannot be changed here. */
require_once __DIR__ . '/hr_common.php';

$self = 'attendance.php';
$date = sales_valid_date($_REQUEST['date'] ?? date('Y-m-d')) ?: date('Y-m-d');
$department = (int) ($_REQUEST['department'] ?? 0);
$back = $self . '?' . http_build_query(['date' => $date, 'department' => $department]);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && hr_ready($conn)) {
    sales_require_csrf($back);
    try {
        $staff = array_column(hr_staff($conn, $date, $department), 'employee_id');
        $time = fn($v) => preg_match('/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/', (string) $v) ? $v : null;
        $saved = 0;
        $conn->begin_transaction();
        foreach ((array) ($_POST['a'] ?? []) as $id => $row) {
            $id = (int) $id;
            $status = (string) ($row['status'] ?? '');
            if (!in_array((string) $id, array_map('strval', $staff), true)) {
                continue;
            }
            if (!isset(HR_ATTENDANCE[$status])) {
                sales_exec($conn, 'DELETE FROM hr_attendance WHERE employee_id=? AND attendance_date=? AND application_id IS NULL', 'is', [$id, $date])->close(); // blank clears the day
                continue;
            }
            sales_exec($conn, "INSERT INTO hr_attendance (employee_id, attendance_date, status, check_in, check_out, note, source, updated_by) VALUES (?, ?, ?, ?, ?, ?, 'office', ?)
                ON DUPLICATE KEY UPDATE status=IF(application_id IS NULL, VALUES(status), status), check_in=IF(application_id IS NULL, VALUES(check_in), check_in), check_out=IF(application_id IS NULL, VALUES(check_out), check_out),
                note=IF(application_id IS NULL, VALUES(note), note), source=IF(application_id IS NULL, 'office', source), updated_by=VALUES(updated_by)", 'isssssi',
                [$id, $date, $status, $time($row['in'] ?? ''), $time($row['out'] ?? ''), sales_cut(trim((string) ($row['note'] ?? '')), 190), hr_user_id()])->close();
            $saved++;
        }
        $conn->commit();
        sales_flash_redirect($back, 'success', 'Attendance saved for ' . $saved . ' person(s).');
    } catch (RuntimeException $e) {
        $conn->rollback();
        sales_flash_redirect($back, 'error', $e->getMessage());
    }
}

hr_page_start($conn, 'Daily Attendance', 'attendance');
$staff = hr_staff($conn, $date, $department);
$marks = array_column(sales_rows($conn, 'SELECT * FROM hr_attendance WHERE attendance_date=?', 's', [$date]), null, 'employee_id');
$off = hr_off_days($date, $date)[$date] ?? '';
$departments = sales_rows($conn, 'SELECT department_id, department_name FROM hr_departments ORDER BY department_name');
?>
<form method="get" class="list-search"><input class="input" type="date" name="date" value="<?= sales_h($date) ?>" aria-label="Date"><select class="input" name="department" aria-label="Department"><?= hr_options($departments, 'department_id', 'department_name', $department, 'All departments') ?></select><button class="btn">View</button></form>
<?php if ($off !== ''): ?><div class="alert info"><?= sales_h($off) ?>: you can still mark anyone who worked.</div><?php endif; ?>
<form method="post" autocomplete="off"><?= sales_csrf_field() ?><input type="hidden" name="date" value="<?= sales_h($date) ?>"><input type="hidden" name="department" value="<?= $department ?>">
<div class="toolbar" style="margin-bottom:8px"><button type="button" class="btn" id="all-present">All Present</button><button class="btn primary" name="action" value="save">Save</button></div>
<div class="table-scroll"><table><thead><tr><th>SL</th><th>Code</th><th>Name</th><th>Designation</th><th>Status</th><th>In</th><th>Out</th><th>Note</th></tr></thead><tbody>
<?php foreach ($staff as $i => $e): $id = (int) $e['employee_id']; $m = $marks[$id] ?? []; $leave = !empty($m['application_id']); ?>
<tr><td><?= $i + 1 ?></td><td><?= sales_h($e['employee_code']) ?></td><td><?= sales_h($e['full_name']) ?></td><td><?= sales_h($e['designation_name']) ?></td>
<?php if ($leave): ?><td colspan="4"><span class="badge open"><?= $m['status'] === 'half_leave' ? 'Half day leave' : 'On leave' ?></span></td>
<?php else: ?><td><select name="a[<?= $id ?>][status]" class="st" aria-label="Status of <?= sales_h($e['full_name']) ?>"><option value="">Not marked</option><?php foreach (HR_ATTENDANCE as $k => $label): ?><option value="<?= $k ?>"<?= ($m['status'] ?? '') === $k ? ' selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></td>
<td><input type="time" name="a[<?= $id ?>][in]" value="<?= sales_h(substr((string) ($m['check_in'] ?? ''), 0, 5)) ?>" aria-label="In"></td><td><input type="time" name="a[<?= $id ?>][out]" value="<?= sales_h(substr((string) ($m['check_out'] ?? ''), 0, 5)) ?>" aria-label="Out"></td><td><input name="a[<?= $id ?>][note]" maxlength="190" value="<?= sales_h($m['note'] ?? '') ?>" aria-label="Note"></td><?php endif; ?></tr>
<?php endforeach; ?>
<?= $staff ? '' : '<tr><td colspan="8" class="empty">Nobody on the books on this date.</td></tr>' ?>
</tbody></table></div></form>
<?php mgmt_page_end('document.getElementById("all-present").addEventListener("click",()=>document.querySelectorAll("select.st").forEach(s=>{if(!s.value)s.value="present"}));'); ?>
<?php include "../../addon/conf/connection_close.php"; ?>
