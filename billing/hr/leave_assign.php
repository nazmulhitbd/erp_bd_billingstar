<?php
/** Leave Assign: how many days of a leave type each person gets in a year. */
require_once __DIR__ . '/hr_common.php';

$self = 'leave_assign.php';
$year = (int) ($_REQUEST['year'] ?? date('Y'));
$year = $year >= 2000 && $year <= 2100 ? $year : (int) date('Y');
$type = (int) ($_REQUEST['type'] ?? 0);
$back = $self . '?' . http_build_query(['year' => $year, 'type' => $type]);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && hr_ready($conn)) {
    sales_require_csrf($back);
    try {
        if (!sales_row($conn, 'SELECT 1 AS x FROM hr_leave_types WHERE leave_type_id=?', 'i', [$type])) {
            throw new RuntimeException('Pick a leave type.');
        }
        $used = array_column(sales_rows($conn, "SELECT employee_id, SUM(days) AS d FROM hr_leave_applications WHERE leave_type_id=? AND YEAR(from_date)=? AND status IN ('approved','pending') GROUP BY employee_id", 'ii', [$type, $year]), 'd', 'employee_id');
        $conn->begin_transaction();
        foreach ((array) ($_POST['days'] ?? []) as $id => $days) {
            $days = min(366.0, sales_amount($days));
            if ($days < (float) ($used[(int) $id] ?? 0)) {
                throw new RuntimeException('You cannot assign fewer days than a person has already used or applied for.');
            }
            sales_exec($conn, 'INSERT INTO hr_leave_balances (employee_id, leave_type_id, leave_year, allocated, updated_by) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE allocated=VALUES(allocated), updated_by=VALUES(updated_by)', 'iiidi', [(int) $id, $type, $year, $days, hr_user_id()])->close();
        }
        $conn->commit();
        sales_flash_redirect($back, 'success', 'Leave assigned.');
    } catch (RuntimeException $e) {
        $conn->rollback();
        sales_flash_redirect($back, 'error', $e->getMessage());
    }
}

hr_page_start($conn, 'Leave Assign', 'assign');
$types = sales_rows($conn, 'SELECT * FROM hr_leave_types WHERE is_active=1 ORDER BY leave_name');
$current = null;
foreach ($types as $t) {
    if ((int) $t['leave_type_id'] === $type) {
        $current = $t;
    }
}
$current = $current ?? ($types[0] ?? null);
$type = (int) ($current['leave_type_id'] ?? 0);
$staff = sales_rows($conn, "SELECT e.employee_id, e.employee_code, e.full_name, COALESCE(g.designation_name, '') AS designation_name, b.allocated, COALESCE(u.d, 0) AS used FROM hr_employees e LEFT JOIN hr_designations g ON g.designation_id=e.designation_id
    LEFT JOIN hr_leave_balances b ON b.employee_id=e.employee_id AND b.leave_type_id=? AND b.leave_year=?
    LEFT JOIN (SELECT employee_id, SUM(days) AS d FROM hr_leave_applications WHERE leave_type_id=? AND YEAR(from_date)=? AND status='approved' GROUP BY employee_id) u ON u.employee_id=e.employee_id
    WHERE e.status IN ('active','onboarding') ORDER BY e.full_name", 'iiii', [$type, $year, $type, $year]);
$default = (float) ($current['default_days'] ?? 0);
?>
<form method="get" class="list-search"><input class="input" type="number" name="year" min="2000" max="2100" value="<?= $year ?>" aria-label="Year"><select class="input" name="type" aria-label="Leave type"><?= hr_options($types, 'leave_type_id', 'leave_name', $type) ?></select><button class="btn">View</button></form>
<form method="post" autocomplete="off"><?= sales_csrf_field() ?><input type="hidden" name="year" value="<?= $year ?>"><input type="hidden" name="type" value="<?= $type ?>">
<div class="toolbar" style="margin-bottom:8px"><button type="button" class="btn" id="fill">Fill Blank With <?= $default ?> Days</button><button class="btn primary" name="action" value="save">Save</button></div>
<div class="table-scroll"><table><thead><tr><th>SL</th><th>Code</th><th>Name</th><th>Designation</th><th>Days Assigned</th><th class="num">Used</th><th class="num">Left</th></tr></thead><tbody>
<?php foreach ($staff as $i => $e): ?><tr><td><?= $i + 1 ?></td><td><?= sales_h($e['employee_code']) ?></td><td><?= sales_h($e['full_name']) ?></td><td><?= sales_h($e['designation_name']) ?></td><td><input class="dd" type="number" min="0" step="0.5" name="days[<?= (int) $e['employee_id'] ?>]" value="<?= $e['allocated'] === null ? '' : (float) $e['allocated'] ?>" aria-label="Days for <?= sales_h($e['full_name']) ?>" style="max-width:110px"></td><td class="num"><?= (float) $e['used'] ?></td><td class="num"><?= (float) $e['allocated'] - (float) $e['used'] ?></td></tr><?php endforeach; ?>
<?= $staff ? '' : '<tr><td colspan="7" class="empty">No active employees.</td></tr>' ?>
</tbody></table></div></form>
<?php mgmt_page_end('document.getElementById("fill").addEventListener("click",()=>document.querySelectorAll("input.dd").forEach(i=>{if(i.value==="")i.value=' . json_encode($default) . '}));'); ?>
<?php include "../../addon/conf/connection_close.php"; ?>
