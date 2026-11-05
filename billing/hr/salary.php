<?php
/** Salary Structure: what each person earns from a date. A raise is a new row, so earlier payslips and history stay as they were. */
require_once __DIR__ . '/hr_common.php';

$self = 'salary.php';
const HR_PAY = ['basic' => 'Basic', 'house_rent' => 'House Rent', 'medical' => 'Medical', 'conveyance' => 'Conveyance', 'other_allowance' => 'Other Allowance'];
const HR_LESS = ['tax' => 'Tax', 'provident_fund' => 'Provident Fund', 'other_deduction' => 'Other Deduction'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && hr_ready($conn)) {
    $emp = (int) ($_POST['emp'] ?? 0);
    $back = $self . '?emp=' . $emp;
    sales_require_csrf($back);
    try {
        if (!sales_row($conn, 'SELECT 1 AS x FROM hr_employees WHERE employee_id=?', 'i', [$emp])) {
            throw new RuntimeException('Pick an employee.');
        }
        if (($_POST['action'] ?? '') === 'delete') {
            sales_exec($conn, 'DELETE FROM hr_salaries WHERE salary_id=? AND employee_id=?', 'ii', [(int) ($_POST['salary_id'] ?? 0), $emp])->close();
            sales_flash_redirect($back, 'success', 'Salary row deleted.');
        }
        $from = sales_valid_date($_POST['effective_from'] ?? '');
        if ($from === null) {
            throw new RuntimeException('Enter the date this salary starts.');
        }
        $amounts = [];
        foreach (array_keys(HR_PAY + HR_LESS) as $key) {
            $amounts[$key] = sales_amount($_POST[$key] ?? 0);
        }
        if ($amounts['basic'] <= 0) {
            throw new RuntimeException('Enter the basic salary.');
        }
        $mode = ($_POST['tax_mode'] ?? '') === 'slab' ? 'slab' : 'manual';
        if ($mode === 'slab') {
            if (!sales_row($conn, 'SELECT 1 AS x FROM hr_tax_slabs LIMIT 1')) {
                throw new RuntimeException('Add the tax slabs on Salary Tax first.');
            }
            $amounts['tax'] = hr_monthly_tax(hr_salary_gross($amounts));
        }
        $cols = implode(', ', array_keys($amounts));
        sales_exec($conn, "INSERT INTO hr_salaries (employee_id, effective_from, $cols, tax_mode, note, created_by) VALUES (?, ?, " . implode(', ', array_fill(0, count($amounts), '?')) . ', ?, ?, ?)
            ON DUPLICATE KEY UPDATE ' . implode(', ', array_map(fn($c) => "$c=VALUES($c)", array_keys($amounts))) . ', tax_mode=VALUES(tax_mode), note=VALUES(note)',
            'is' . str_repeat('d', count($amounts)) . 'ssi', array_merge([$emp, $from], array_values($amounts), [$mode, sales_post_string('note', 190), hr_user_id()]))->close();
        sales_flash_redirect($back, 'success', 'Salary saved.');
    } catch (RuntimeException $e) {
        sales_flash_redirect($back, 'error', $e->getMessage());
    }
}

hr_page_start($conn, 'Salary Structure', 'salary');
$emp = (int) ($_GET['emp'] ?? 0);
$e = $emp ? sales_row($conn, 'SELECT * FROM hr_employees WHERE employee_id=?', 'i', [$emp]) : null;
if ($e) {
    $history = sales_rows($conn, 'SELECT * FROM hr_salaries WHERE employee_id=? ORDER BY effective_from DESC', 'i', [$emp]);
    $last = $history[0] ?? [];
    echo '<h3 style="margin-top:0">' . sales_h($e['full_name']) . ' (' . sales_h($e['employee_code']) . ')</h3><form method="post" autocomplete="off">' . sales_csrf_field() . '<input type="hidden" name="emp" value="' . $emp . '"><div class="grid g4" style="margin-top:0">'
        . '<div class="field"><label for="effective_from">Starts On</label><input id="effective_from" type="date" name="effective_from" required value="' . date('Y-m-01') . '"></div>';
    foreach (HR_PAY + HR_LESS as $key => $label) {
        echo '<div class="field"><label for="' . $key . '">' . $label . '</label><input id="' . $key . '" type="number" min="0" step="0.01" name="' . $key . '" value="' . sales_h($last[$key] ?? 0) . '"></div>';
    }
    echo '<div class="field"><label for="tax_mode">Tax Worked Out</label><select id="tax_mode" name="tax_mode"><option value="manual">Manual: use the Tax typed here</option><option value="slab"' . (($last['tax_mode'] ?? '') === 'slab' ? ' selected' : '') . '>From the tax slabs</option></select></div>';
    echo '<div class="field"><label for="note">Note</label><input id="note" name="note" maxlength="190"></div></div><div class="toolbar" style="margin-top:10px"><button class="btn primary" name="action" value="save">Save</button><a class="btn" href="' . $self . '">Close</a></div></form>';
    echo '<section class="section"><div class="section-title">Salary history</div><div class="table-scroll"><table><thead><tr><th>Starts On</th>';
    foreach (HR_PAY + HR_LESS as $label) {
        echo '<th class="num">' . $label . '</th>';
    }
    echo '<th>Note</th><th></th></tr></thead><tbody>';
    foreach ($history as $s) {
        echo '<tr><td>' . date('d/m/Y', strtotime($s['effective_from'])) . '</td>';
        foreach (array_keys(HR_PAY + HR_LESS) as $key) {
            echo '<td class="num">' . sales_money($s[$key]) . '</td>';
        }
        echo '<td>' . sales_h($s['note']) . '</td><td><form method="post" onsubmit="return confirm(\'Delete this salary row?\')">' . sales_csrf_field() . '<input type="hidden" name="emp" value="' . $emp . '"><input type="hidden" name="salary_id" value="' . (int) $s['salary_id'] . '"><button class="btn small danger" name="action" value="delete">Delete</button></form></td></tr>';
    }
    echo ($history ? '' : '<tr><td colspan="11" class="empty">No salary set yet.</td></tr>') . '</tbody></table></div></section>';
    mgmt_page_end();
    exit;
}
$rows = sales_list_rows($conn, "SELECT e.employee_id, e.employee_code, e.full_name, e.status, COALESCE(g.designation_name, '') AS designation_name, s.effective_from, s.basic, s.house_rent, s.medical, s.conveyance, s.other_allowance, s.tax, s.provident_fund, s.other_deduction
    FROM hr_employees e LEFT JOIN hr_designations g ON g.designation_id=e.designation_id
    LEFT JOIN hr_salaries s ON s.salary_id=(SELECT x.salary_id FROM hr_salaries x WHERE x.employee_id=e.employee_id AND x.effective_from<=CURDATE() ORDER BY x.effective_from DESC LIMIT 1)
    WHERE e.status IN ('active','onboarding') ORDER BY e.full_name");
?>
<div class="table-scroll"><table><thead><tr><th>SL</th><th>Code</th><th>Name</th><th>Designation</th><th>Since</th><th class="num">Gross</th><th class="num">Deductions</th><th class="num">Net</th><th></th></tr></thead><tbody>
<?php foreach ($rows as $i => $r): $gross = array_sum(array_map(fn($k) => (float) $r[$k], array_keys(HR_PAY))); $less = array_sum(array_map(fn($k) => (float) $r[$k], array_keys(HR_LESS))); ?>
<tr><td><?= $i + 1 ?></td><td><?= sales_h($r['employee_code']) ?></td><td><?= sales_h($r['full_name']) ?></td><td><?= sales_h($r['designation_name']) ?></td><td><?= $r['effective_from'] ? date('d/m/Y', strtotime($r['effective_from'])) : '<span class="muted">Not set</span>' ?></td><td class="num"><?= sales_money($gross) ?></td><td class="num"><?= sales_money($less) ?></td><td class="num"><?= sales_money($gross - $less) ?></td><td><a class="btn small" href="<?= $self ?>?emp=<?= (int) $r['employee_id'] ?>">Set Salary</a></td></tr>
<?php endforeach; ?>
<?= $rows ? '' : '<tr><td colspan="9" class="empty">No active employees.</td></tr>' ?>
</tbody></table></div>
<?php mgmt_page_end(); ?>
<?php include "../../addon/conf/connection_close.php"; ?>
