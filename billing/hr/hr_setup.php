<?php
/** HR Setup: departments, designations, leave types, holidays, and the weekly off days and late time. */
require_once __DIR__ . '/hr_common.php';

$self = 'hr_setup.php';
const HR_LISTS = [
    'department' => ['Department', 'hr_departments', 'department_id', 'department_name', 'hr_employees'],
    'designation' => ['Designation', 'hr_designations', 'designation_id', 'designation_name', 'hr_employees'],
];
$tab = in_array($_REQUEST['tab'] ?? '', ['department', 'designation', 'leave', 'holiday', 'settings', 'accounts'], true) ? $_REQUEST['tab'] : 'department';
$back = $self . '?tab=' . $tab;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && hr_ready($conn)) {
    sales_require_csrf($back);
    $action = (string) ($_POST['action'] ?? '');
    $id = (int) ($_POST['id'] ?? 0);
    try {
        if (isset(HR_LISTS[$tab])) {
            [$label, $table, $pk, $col, $used] = HR_LISTS[$tab];
            if ($action === 'delete') {
                if (sales_row($conn, "SELECT 1 AS x FROM $used WHERE $pk=? LIMIT 1", 'i', [$id])) {
                    throw new RuntimeException('People are using this ' . strtolower($label) . '. Make it inactive instead.');
                }
                sales_exec($conn, "DELETE FROM $table WHERE $pk=?", 'i', [$id])->close();
                sales_flash_redirect($back, 'success', $label . ' deleted.');
            }
            $name = sales_post_string('name', 120);
            if ($name === '') {
                throw new RuntimeException('Enter the name.');
            }
            if (sales_row($conn, "SELECT 1 AS x FROM $table WHERE $col=? AND $pk<>?", 'si', [$name, $id])) {
                throw new RuntimeException('That name is already used.');
            }
            $active = isset($_POST['is_active']) ? 1 : 0;
            $id ? sales_exec($conn, "UPDATE $table SET $col=?, is_active=? WHERE $pk=?", 'sii', [$name, $active, $id])->close()
                : sales_exec($conn, "INSERT INTO $table ($col, is_active) VALUES (?, ?)", 'si', [$name, $active])->close();
            sales_flash_redirect($back, 'success', $label . ' saved.');
        }
        if ($tab === 'leave') {
            if ($action === 'delete') {
                if (sales_row($conn, 'SELECT 1 AS x FROM hr_leave_applications WHERE leave_type_id=? UNION SELECT 1 FROM hr_leave_balances WHERE leave_type_id=? LIMIT 1', 'ii', [$id, $id])) {
                    throw new RuntimeException('This leave type has been assigned or used. Make it inactive instead.');
                }
                sales_exec($conn, 'DELETE FROM hr_leave_types WHERE leave_type_id=?', 'i', [$id])->close();
                sales_flash_redirect($back, 'success', 'Leave type deleted.');
            }
            $name = sales_post_string('name', 80);
            if ($name === '') {
                throw new RuntimeException('Enter the name.');
            }
            if (sales_row($conn, 'SELECT 1 AS x FROM hr_leave_types WHERE leave_name=? AND leave_type_id<>?', 'si', [$name, $id])) {
                throw new RuntimeException('That name is already used.');
            }
            $days = min(366.0, sales_amount($_POST['default_days'] ?? 0));
            $paid = isset($_POST['is_paid']) ? 1 : 0;
            $active = isset($_POST['is_active']) ? 1 : 0;
            $id ? sales_exec($conn, 'UPDATE hr_leave_types SET leave_name=?, default_days=?, is_paid=?, is_active=? WHERE leave_type_id=?', 'sdiii', [$name, $days, $paid, $active, $id])->close()
                : sales_exec($conn, 'INSERT INTO hr_leave_types (leave_name, default_days, is_paid, is_active) VALUES (?, ?, ?, ?)', 'sdii', [$name, $days, $paid, $active])->close();
            sales_flash_redirect($back, 'success', 'Leave type saved.');
        }
        if ($tab === 'holiday') {
            $date = sales_valid_date($_POST['holiday_date'] ?? '');
            if ($date === null) {
                throw new RuntimeException('Enter a valid date.');
            }
            if ($action === 'delete') {
                sales_exec($conn, 'DELETE FROM hr_holidays WHERE holiday_date=?', 's', [$date])->close();
                sales_flash_redirect($back, 'success', 'Holiday deleted.');
            }
            $name = sales_post_string('name', 120);
            if ($name === '') {
                throw new RuntimeException('Enter the holiday name.');
            }
            sales_exec($conn, 'INSERT INTO hr_holidays (holiday_date, holiday_name) VALUES (?, ?) ON DUPLICATE KEY UPDATE holiday_name=VALUES(holiday_name)', 'ss', [$date, $name])->close();
            sales_flash_redirect($back, 'success', 'Holiday saved.');
        }
        if ($tab === 'accounts') {
            $valid = array_map('intval', array_column(acc_accounts($conn, true, 'a.has_sub_ledger=0'), 'account_id'));
            foreach (array_keys(HR_ACCOUNTS) as $key) {
                $account = (int) ($_POST[$key] ?? 0);
                if ($account && !in_array($account, $valid, true)) {
                    throw new RuntimeException('Pick an active account.');
                }
                sales_exec($conn, 'INSERT INTO hr_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)', 'ss', [$key, (string) ($account ?: '')])->close();
            }
            sales_flash_redirect($back, 'success', 'Accounts saved.');
        }
        $off = array_values(array_intersect(array_map('strval', (array) ($_POST['weekly_off'] ?? [])), ['0', '1', '2', '3', '4', '5', '6']));
        $late = (string) ($_POST['late_after'] ?? '');
        if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $late)) {
            throw new RuntimeException('Enter the late time.');
        }
        foreach (['weekly_off' => implode(',', $off), 'late_after' => $late] as $key => $value) {
            sales_exec($conn, 'INSERT INTO hr_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)', 'ss', [$key, $value])->close();
        }
        sales_flash_redirect($back, 'success', 'Settings saved.');
    } catch (RuntimeException $e) {
        sales_flash_redirect($back, 'error', $e->getMessage());
    }
}

hr_page_start($conn, 'HR Setup', 'setup');
$tabs = ['department' => 'Department', 'designation' => 'Designation', 'leave' => 'Leave Type', 'holiday' => 'Holiday', 'settings' => 'Attendance Settings', 'accounts' => 'Salary Accounts'];
echo '<div class="toolbar" style="margin-bottom:10px">';
foreach ($tabs as $key => $label) {
    echo '<a class="btn' . ($key === $tab ? ' primary' : '') . '" href="' . $self . '?tab=' . $key . '">' . $label . '</a>';
}
echo '</div>';
$open = fn() => '<form method="post" class="grid g4" style="margin-top:0;align-items:end">' . sales_csrf_field() . '<input type="hidden" name="tab" value="' . $tab . '">';
$del = fn($field, $value, $text) => '<form method="post" style="display:inline" onsubmit="return confirm(' . sales_h(json_encode('Delete ' . $text . '?')) . ')">' . sales_csrf_field() . '<input type="hidden" name="tab" value="' . $tab . '"><input type="hidden" name="' . $field . '" value="' . sales_h($value) . '"><button class="btn small danger" name="action" value="delete">Delete</button></form>';

if (isset(HR_LISTS[$tab])) {
    [$label, $table, $pk, $col] = HR_LISTS[$tab];
    $edit = isset($_GET['edit']) ? sales_row($conn, "SELECT * FROM $table WHERE $pk=?", 'i', [(int) $_GET['edit']]) : null;
    echo $open() . '<input type="hidden" name="id" value="' . (int) ($edit[$pk] ?? 0) . '"><div class="field"><label for="name">' . $label . '</label><input id="name" name="name" maxlength="120" required value="' . sales_h($edit[$col] ?? '') . '"></div>'
        . '<div class="field"><label><input class="check" type="checkbox" name="is_active"' . ((int) ($edit['is_active'] ?? 1) ? ' checked' : '') . '> Active</label></div><div class="toolbar"><button class="btn primary" name="action" value="save">Save</button>' . ($edit ? '<a class="btn" href="' . $back . '">Close</a>' : '') . '</div></form>';
    $rows = sales_rows($conn, "SELECT t.*, (SELECT COUNT(*) FROM hr_employees e WHERE e.$pk=t.$pk) AS people FROM $table t ORDER BY t.$col");
    echo '<div class="table-scroll" style="margin-top:10px"><table><thead><tr><th>SL</th><th>' . $label . '</th><th class="num">People</th><th>Status</th><th></th></tr></thead><tbody>';
    foreach ($rows as $i => $r) {
        echo '<tr><td>' . ($i + 1) . '</td><td>' . sales_h($r[$col]) . '</td><td class="num">' . (int) $r['people'] . '</td><td>' . ((int) $r['is_active'] ? 'Active' : 'Inactive') . '</td><td class="nowrap"><a class="btn small" href="' . $back . '&edit=' . (int) $r[$pk] . '">Edit</a> ' . $del('id', $r[$pk], $r[$col]) . '</td></tr>';
    }
    echo ($rows ? '' : '<tr><td colspan="5" class="empty">Nothing added yet.</td></tr>') . '</tbody></table></div>';
}
if ($tab === 'leave') {
    $edit = isset($_GET['edit']) ? sales_row($conn, 'SELECT * FROM hr_leave_types WHERE leave_type_id=?', 'i', [(int) $_GET['edit']]) : null;
    echo $open() . '<input type="hidden" name="id" value="' . (int) ($edit['leave_type_id'] ?? 0) . '"><div class="field"><label for="name">Leave Type</label><input id="name" name="name" maxlength="80" required value="' . sales_h($edit['leave_name'] ?? '') . '"></div>'
        . '<div class="field"><label for="default_days">Days Per Year</label><input id="default_days" type="number" min="0" step="0.5" name="default_days" value="' . sales_h($edit['default_days'] ?? 0) . '"></div>'
        . '<div class="field"><label><input class="check" type="checkbox" name="is_paid"' . ((int) ($edit['is_paid'] ?? 1) ? ' checked' : '') . '> Paid</label><label><input class="check" type="checkbox" name="is_active"' . ((int) ($edit['is_active'] ?? 1) ? ' checked' : '') . '> Active</label></div>'
        . '<div class="toolbar"><button class="btn primary" name="action" value="save">Save</button>' . ($edit ? '<a class="btn" href="' . $back . '">Close</a>' : '') . '</div></form>';
    $rows = sales_rows($conn, 'SELECT * FROM hr_leave_types ORDER BY leave_name');
    echo '<div class="table-scroll" style="margin-top:10px"><table><thead><tr><th>SL</th><th>Leave Type</th><th class="num">Days Per Year</th><th>Pay</th><th>Status</th><th></th></tr></thead><tbody>';
    foreach ($rows as $i => $r) {
        echo '<tr><td>' . ($i + 1) . '</td><td>' . sales_h($r['leave_name']) . '</td><td class="num">' . (float) $r['default_days'] . '</td><td>' . ((int) $r['is_paid'] ? 'Paid' : 'Unpaid') . '</td><td>' . ((int) $r['is_active'] ? 'Active' : 'Inactive') . '</td><td class="nowrap"><a class="btn small" href="' . $back . '&edit=' . (int) $r['leave_type_id'] . '">Edit</a> ' . $del('id', $r['leave_type_id'], $r['leave_name']) . '</td></tr>';
    }
    echo '</tbody></table></div>';
}
if ($tab === 'holiday') {
    echo $open() . '<div class="field"><label for="holiday_date">Date</label><input id="holiday_date" type="date" name="holiday_date" required></div><div class="field"><label for="name">Holiday</label><input id="name" name="name" maxlength="120" required></div><div class="toolbar"><button class="btn primary" name="action" value="save">Save</button></div></form>';
    $rows = sales_rows($conn, 'SELECT * FROM hr_holidays ORDER BY holiday_date DESC');
    echo '<div class="table-scroll" style="margin-top:10px"><table><thead><tr><th>SL</th><th>Date</th><th>Holiday</th><th></th></tr></thead><tbody>';
    foreach ($rows as $i => $r) {
        echo '<tr><td>' . ($i + 1) . '</td><td>' . date('d/m/Y', strtotime($r['holiday_date'])) . '</td><td>' . sales_h($r['holiday_name']) . '</td><td>' . $del('holiday_date', $r['holiday_date'], $r['holiday_name']) . '</td></tr>';
    }
    echo ($rows ? '' : '<tr><td colspan="4" class="empty">No holidays added yet.</td></tr>') . '</tbody></table></div>';
}
if ($tab === 'accounts') {
    if (!acc_ready($conn)) {
        echo '<div class="errorbox">Accounts is not installed, so salary payments cannot be posted yet.</div>';
    } else {
        $s = hr_settings();
        $accounts = acc_accounts($conn, true, 'a.has_sub_ledger=0');
        echo '<form method="post">' . sales_csrf_field() . '<input type="hidden" name="tab" value="accounts"><div class="grid g2" style="margin-top:0">';
        foreach (HR_ACCOUNTS as $key => $label) {
            echo '<div class="field"><label for="' . $key . '">' . $label . '</label><select id="' . $key . '" name="' . $key . '">' . acc_account_options($accounts, (int) ($s[$key] ?? 0), 'Not set') . '</select></div>';
        }
        echo '</div><div class="toolbar" style="margin-top:10px"><button class="btn primary" name="action" value="save">Save</button></div></form>'
            . '<p class="footnote">When a payslip is paid, its journal debits Salary Expense and credits the cash or bank account, plus Tax, Provident Fund, Advance and Other Deductions for what was held back. A deduction needs its account only if a payslip has that deduction. With no Salary Expense account, payments are recorded here but not posted to Accounts.</p>';
    }
}
if ($tab === 'settings') {
    $s = hr_settings();
    $off = explode(',', $s['weekly_off']);
    echo '<form method="post">' . sales_csrf_field() . '<input type="hidden" name="tab" value="settings"><div class="radios"><b>Weekly Off</b>';
    foreach (['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'] as $n => $day) {
        echo '<label><input class="check" type="checkbox" name="weekly_off[]" value="' . $n . '"' . (in_array((string) $n, $off, true) ? ' checked' : '') . '> ' . $day . '</label>';
    }
    echo '</div><div class="grid g4" style="align-items:end"><div class="field"><label for="late_after">Late After</label><input id="late_after" type="time" name="late_after" required value="' . sales_h($s['late_after']) . '"></div><div class="toolbar"><button class="btn primary" name="action" value="save">Save</button></div></div></form>'
        . '<p class="footnote">Weekly off days and holidays are not counted as leave days. A portal check-in after the late time is marked Late.</p>';
}
mgmt_page_end();
?>
<?php include "../../addon/conf/connection_close.php"; ?>
