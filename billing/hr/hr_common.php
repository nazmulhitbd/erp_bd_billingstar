<?php
/**
 * Shared code for the HR pages. The rules themselves are in addon/inc/hr.php, which the SR / Staff Portal uses too.
 * Who may use what comes from the HR forms in User Access Control (billing/menu_catalog.php, enforced by perm_guard).
 */
require_once dirname(__DIR__) . '/trader/trader_common.php';
require_once dirname(__DIR__) . '/inventory/inv_common.php';
require_once dirname(__DIR__) . '/accounts_info/acc_common.php';
require_once dirname(__DIR__, 2) . '/addon/inc/hr.php';

const HR_MENU = ['employees' => ['employees.php', 'Employees'], 'setup' => ['hr_setup.php', 'Setup'], 'announce' => ['announcements.php', 'Announcements'], 'attendance' => ['attendance.php', 'Daily Attendance'], 'register' => ['attendance_register.php', 'Attendance Register'],
    'assign' => ['leave_assign.php', 'Leave Assign'], 'leave' => ['leave_applications.php', 'Leave Applications'], 'salary' => ['salary.php', 'Salary Structure'], 'tax' => ['salary_tax.php', 'Salary Tax'], 'payroll' => ['payroll.php', 'Payroll']];
const HR_TABLES = ['hr_settings', 'hr_departments', 'hr_designations', 'hr_holidays', 'hr_employees', 'hr_onboarding_tasks', 'hr_salaries', 'hr_leave_types', 'hr_leave_balances', 'hr_leave_applications', 'hr_attendance', 'hr_payslips',
    'hr_education', 'hr_experience', 'hr_references', 'hr_training', 'hr_family', 'hr_documents', 'hr_tax_slabs'];

function hr_ready(mysqli $db)
{
    return sales_tables_ready($db, HR_TABLES);
}

function hr_page_start(mysqli $db, $title, $active)
{
    mgmt_page_start('HR', HR_MENU, $title, $active);
    if (!hr_ready($db)) {
        echo '<div class="errorbox"><strong>HR is not installed.</strong><br>Import <code>billing/hr/schema.sql</code> (after the trader, inventory, sales person and SR portal schemas), then reload this page.</div>';
        mgmt_page_end();
        exit;
    }
    echo sales_flash_html(sales_take_flash());
}

function hr_user_id()
{
    return (int) ($GLOBALS['user_id'] ?? 0);
}

/** First day of the month from ?month=YYYY-MM (default this month). */
function hr_month($value)
{
    return preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string) $value) ? $value . '-01' : date('Y-m-01');
}

/** People on the books on a date, with names of their department and designation. */
function hr_staff(mysqli $db, $date = null, $departmentId = 0)
{
    $date = $date ?? date('Y-m-d');
    return sales_rows($db, "SELECT e.*, COALESCE(d.department_name, '') AS department_name, COALESCE(g.designation_name, '') AS designation_name FROM hr_employees e
        LEFT JOIN hr_departments d ON d.department_id=e.department_id LEFT JOIN hr_designations g ON g.designation_id=e.designation_id
        WHERE e.status<>'onboarding' AND e.join_date<=? AND (e.exit_date IS NULL OR e.exit_date>=?) AND (?=0 OR e.department_id=?) ORDER BY e.full_name", 'ssii', [$date, $date, (int) $departmentId, (int) $departmentId]);
}

function hr_options(array $rows, $id, $name, $selected, $blank = null)
{
    $html = $blank !== null ? '<option value="0">' . sales_h($blank) . '</option>' : '';
    foreach ($rows as $r) {
        $html .= '<option value="' . (int) $r[$id] . '"' . ((int) $selected === (int) $r[$id] ? ' selected' : '') . '>' . sales_h($r[$name]) . '</option>';
    }
    return $html;
}

function hr_next_code(mysqli $db)
{
    $n = (int) (sales_row($db, 'SELECT COALESCE(MAX(employee_id), 0) AS n FROM hr_employees')['n'] ?? 0) + 1;
    do {
        $code = 'EMP' . str_pad((string) $n++, 5, '0', STR_PAD_LEFT);
    } while (sales_row($db, 'SELECT 1 AS x FROM hr_employees WHERE employee_code=?', 's', [$code]));
    return $code;
}
