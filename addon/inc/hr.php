<?php
/**
 * HR rules shared by the admin pages (billing/hr/) and the SR / Staff Portal (sr_portal/hr*.php): leave days and balances,
 * applying for and deciding leave, attendance and payslip sums. Tables: billing/hr/schema.sql. Works on whichever database
 * connection the page has (perm_db), and throws RuntimeException with a message for the person using the page.
 */
require_once __DIR__ . '/perm.php';
require_once __DIR__ . '/notify.php';

const HR_STATUS = ['onboarding' => 'Onboarding', 'active' => 'Active', 'resigned' => 'Resigned', 'terminated' => 'Terminated'];
const HR_TYPES = ['permanent' => 'Permanent', 'probation' => 'Probation', 'contract' => 'Contract', 'intern' => 'Intern'];
const HR_ATTENDANCE = ['present' => 'Present', 'late' => 'Late', 'half_day' => 'Half Day', 'absent' => 'Absent'];
const HR_ATTENDANCE_MARK = ['present' => 'P', 'late' => 'L', 'half_day' => 'H', 'absent' => 'A', 'leave' => 'LV', 'half_leave' => 'HL'];
const HR_LEAVE_STATUS = ['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'cancelled' => 'Cancelled'];
const HR_PAY_METHODS = ['bank' => 'Bank', 'cash' => 'Cash', 'mobile' => 'Mobile Banking', 'other' => 'Other'];
/** Accounts a salary posting uses, chosen on HR Setup: setting key => label. */
const HR_ACCOUNTS = ['salary_account' => 'Salary Expense', 'tax_account' => 'Salary Tax Payable', 'pf_account' => 'Provident Fund Payable', 'advance_account' => 'Staff Advance', 'other_deduction_account' => 'Other Deductions Payable'];

/*
 * The kinds of record on an employee's file. Each field is column => [label, type, size or options, required, shown in the list].
 * The admin form, its checks and the read-only portal view are all drawn from this, so a new field is one line here plus its column.
 */
const HR_RECORDS = [
    'education' => ['Education', 'hr_education', 'passing_year DESC, record_id DESC', [
        'level' => ['Level', 'select', ['SSC', 'HSC', 'Diploma', 'Bachelor', 'Master', 'PhD', 'Other'], true, true],
        'degree' => ['Degree / Exam', 'text', 120, true, true],
        'major' => ['Major / Group', 'text', 120, false, true],
        'institution' => ['Institution', 'text', 190, true, true],
        'board_university' => ['Board / University', 'text', 140, false, true],
        'passing_year' => ['Passing Year', 'year', 0, false, true],
        'result' => ['Result', 'text', 40, false, true],
    ]],
    'experience' => ['Work Experience', 'hr_experience', 'from_date DESC', [
        'company' => ['Company', 'text', 190, true, true],
        'job_title' => ['Job Title', 'text', 120, true, true],
        'from_date' => ['From', 'date', 0, true, true],
        'to_date' => ['To', 'date', 0, false, true],
        'last_salary' => ['Last Salary', 'amount', 0, false, true],
        'contact' => ['Company Contact', 'text', 140, false, false],
        'leaving_reason' => ['Reason For Leaving', 'text', 190, false, true],
        'responsibilities' => ['Responsibilities', 'text', 500, false, false],
    ]],
    'references' => ['References', 'hr_references', 'record_id', [
        'reference_name' => ['Name', 'text', 140, true, true],
        'relation' => ['Relation', 'text', 80, false, true],
        'organization' => ['Organization', 'text', 190, false, true],
        'designation' => ['Designation', 'text', 120, false, true],
        'mobile' => ['Mobile', 'text', 40, true, true],
        'email' => ['Email', 'email', 190, false, true],
        'address' => ['Address', 'text', 255, false, false],
        'verified' => ['Verified', 'check', 0, false, true],
    ]],
    'training' => ['Training & Certificates', 'hr_training', 'from_date DESC, record_id DESC', [
        'title' => ['Training / Certificate', 'text', 190, true, true],
        'institute' => ['Institute', 'text', 190, false, true],
        'from_date' => ['From', 'date', 0, false, true],
        'to_date' => ['To', 'date', 0, false, true],
        'certificate_no' => ['Certificate No', 'text', 80, false, true],
    ]],
    'family' => ['Family & Nominee', 'hr_family', 'nominee_share DESC, record_id', [
        'member_name' => ['Name', 'text', 140, true, true],
        'relation' => ['Relation', 'select', ['Father', 'Mother', 'Spouse', 'Son', 'Daughter', 'Brother', 'Sister', 'Other'], true, true],
        'date_of_birth' => ['Date of Birth', 'date', 0, false, true],
        'occupation' => ['Occupation', 'text', 120, false, true],
        'mobile' => ['Mobile', 'text', 40, false, true],
        'nominee_share' => ['Nominee Share %', 'percent', 0, false, true],
    ]],
];
const HR_DOCUMENT_TYPES = ['Photo', 'NID', 'Passport', 'CV', 'Educational Certificate', 'Experience Certificate', 'Appointment Letter', 'Training Certificate', 'Other'];
const HR_DOCUMENT_FILES = ['pdf' => ['application/pdf'], 'jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'], 'png' => ['image/png'],
    'doc' => ['application/msword', 'application/vnd.ms-office', 'application/CDFV2'],
    'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip']];
const HR_DOCUMENT_MAX = 5242880;

function hr_exec($sql, $types = '', array $params = [])
{
    $db = perm_db();
    $stmt = $db ? $db->prepare($sql) : false;
    if (!$stmt) {
        throw new RuntimeException('Database error: ' . ($db ? $db->error : 'no connection'));
    }
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $audit = audit_sql($db, $sql, $types, $params);
    if (!$stmt->execute()) {
        audit_sql_end($audit, false);
        $message = $stmt->error;
        $stmt->close();
        throw new RuntimeException('Database error: ' . $message);
    }
    audit_sql_end($audit, true, $stmt->insert_id);
    return $stmt;
}

function hr_rows($sql, $types = '', array $params = [])
{
    $stmt = hr_exec($sql, $types, $params);
    $result = $stmt->get_result();
    $rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
    return $rows;
}

function hr_row($sql, $types = '', array $params = [])
{
    return hr_rows($sql, $types, $params)[0] ?? null;
}

function hr_write($sql, $types = '', array $params = [])
{
    hr_exec($sql, $types, $params)->close();
}

/** Run $work in a transaction: everything is saved or nothing is. */
function hr_transaction(callable $work)
{
    $db = perm_db();
    $db->begin_transaction();
    try {
        $out = $work();
        $db->commit();
        return $out;
    } catch (Throwable $e) {
        $db->rollback();
        throw $e;
    }
}

function hr_settings()
{
    $out = ['weekly_off' => '5', 'late_after' => '09:30'];
    foreach (hr_rows('SELECT setting_key, setting_value FROM hr_settings') as $r) {
        $out[$r['setting_key']] = $r['setting_value'];
    }
    return $out;
}

/** Weekly off days and holidays between two dates, as date => name. */
function hr_off_days($from, $to)
{
    $weekly = array_filter(array_map('trim', explode(',', hr_settings()['weekly_off'])), 'strlen');
    $off = [];
    for ($t = strtotime($from . ' 12:00'), $end = strtotime($to . ' 12:00'); $t <= $end; $t += 86400) {
        if (in_array(date('w', $t), $weekly, true)) {
            $off[date('Y-m-d', $t)] = 'Weekly off';
        }
    }
    foreach (hr_rows('SELECT holiday_date, holiday_name FROM hr_holidays WHERE holiday_date BETWEEN ? AND ?', 'ss', [$from, $to]) as $h) {
        $off[$h['holiday_date']] = $h['holiday_name'];
    }
    return $off;
}

/** The working days of a leave: every date in the range that is not in $off. */
function hr_leave_dates($from, $to, array $off)
{
    $dates = [];
    for ($t = strtotime($from . ' 12:00'), $end = strtotime($to . ' 12:00'); $t <= $end; $t += 86400) {
        $date = date('Y-m-d', $t);
        if (!isset($off[$date])) {
            $dates[] = $date;
        }
    }
    return $dates;
}

/** Days per leave type of a year: type id => row with allocated, used, pending and left. */
function hr_leave_balances($employeeId, $year)
{
    $out = [];
    foreach (hr_rows('SELECT t.leave_type_id, t.leave_name, t.is_paid, t.is_active, COALESCE(b.allocated, 0) AS allocated FROM hr_leave_types t
        LEFT JOIN hr_leave_balances b ON b.leave_type_id=t.leave_type_id AND b.employee_id=? AND b.leave_year=? ORDER BY t.leave_name', 'ii', [(int) $employeeId, (int) $year]) as $t) {
        $out[(int) $t['leave_type_id']] = $t + ['used' => 0.0, 'pending' => 0.0];
    }
    foreach (hr_rows("SELECT leave_type_id, status, SUM(days) AS days FROM hr_leave_applications WHERE employee_id=? AND YEAR(from_date)=? AND status IN ('pending','approved') GROUP BY leave_type_id, status", 'ii', [(int) $employeeId, (int) $year]) as $r) {
        if (isset($out[(int) $r['leave_type_id']])) {
            $out[(int) $r['leave_type_id']][$r['status'] === 'approved' ? 'used' : 'pending'] = (float) $r['days'];
        }
    }
    foreach ($out as &$b) {
        $b['left'] = (float) $b['allocated'] - $b['used'] - $b['pending'];
    }
    return $out;
}

/** Record a leave application. $employee is the hr_employees row. Returns the application id. */
function hr_apply_leave(array $employee, $typeId, $from, $to, $half, $reason, $via)
{
    $id = (int) $employee['employee_id'];
    $valid = fn($d) => ($x = DateTime::createFromFormat('!Y-m-d', (string) $d)) && $x->format('Y-m-d') === $d;
    if (!$valid($from) || !$valid($to)) {
        throw new RuntimeException('Enter valid leave dates.');
    }
    if ($to < $from) {
        throw new RuntimeException('The last day cannot be before the first day.');
    }
    if (substr($from, 0, 4) !== substr($to, 0, 4)) {
        throw new RuntimeException('A leave must stay within one year. Apply for each year separately.');
    }
    if ($half && $from !== $to) {
        throw new RuntimeException('A half day leave is for one date.');
    }
    if ($from < $employee['join_date']) {
        throw new RuntimeException('The leave starts before the joining date.');
    }
    if (trim($reason) === '') {
        throw new RuntimeException('Enter the reason.');
    }
    return hr_transaction(function () use ($id, $typeId, $from, $to, $half, $reason, $via) {
        hr_row('SELECT employee_id FROM hr_employees WHERE employee_id=? FOR UPDATE', 'i', [$id]); // one application at a time per person
        $balances = hr_leave_balances($id, (int) substr($from, 0, 4));
        $type = $balances[(int) $typeId] ?? null;
        if (!$type || !(int) $type['is_active']) {
            throw new RuntimeException('Pick a leave type.');
        }
        $dates = hr_leave_dates($from, $to, hr_off_days($from, $to));
        if (!$dates) {
            throw new RuntimeException('Those dates are all weekly off days or holidays.');
        }
        $days = $half ? 0.5 : (float) count($dates);
        if ((int) $type['is_paid'] && $days > $type['left']) {
            throw new RuntimeException($type['leave_name'] . ': ' . $days . ' day(s) asked, ' . max(0, $type['left']) . ' left.');
        }
        if (hr_row("SELECT 1 AS x FROM hr_leave_applications WHERE employee_id=? AND status IN ('pending','approved') AND from_date<=? AND to_date>=? LIMIT 1", 'iss', [$id, $to, $from])) {
            throw new RuntimeException('There is already a leave application for those dates.');
        }
        $stmt = hr_exec('INSERT INTO hr_leave_applications (employee_id, leave_type_id, from_date, to_date, half_day, days, reason, applied_via) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            'iissidss', [$id, (int) $typeId, $from, $to, $half ? 1 : 0, $days, mb_substr(trim($reason), 0, 255), $via]);
        $new = (int) $stmt->insert_id;
        $stmt->close();
        if ($via === 'portal') {
            $name = hr_row('SELECT full_name FROM hr_employees WHERE employee_id=?', 'i', [$id])['full_name'];
            notify_admin('hr/leave_applications.php', 'Leave request: ' . $name, $type['leave_name'] . ', ' . $days . ' day(s) from ' . date('d/m/Y', strtotime($from)) . '. ' . trim($reason), '?status=pending');
        }
        return $new;
    });
}

/** Approve or reject a pending application. Approving writes its days into attendance. */
function hr_decide_leave($applicationId, $approve, $by, $note = '')
{
    hr_transaction(function () use ($applicationId, $approve, $by, $note) {
        $a = hr_row('SELECT a.*, t.is_paid FROM hr_leave_applications a JOIN hr_leave_types t ON t.leave_type_id=a.leave_type_id WHERE a.application_id=? FOR UPDATE', 'i', [(int) $applicationId]);
        if (!$a || $a['status'] !== 'pending') {
            throw new RuntimeException('This application is no longer pending.');
        }
        if ($approve) {
            $year = (int) substr($a['from_date'], 0, 4);
            $b = hr_leave_balances($a['employee_id'], $year)[(int) $a['leave_type_id']];
            // Its own days are counted as pending in left, so the balance only fails if the office lowered it since.
            if ((int) $a['is_paid'] && $b['left'] < 0) {
                throw new RuntimeException('Not enough ' . $b['leave_name'] . ' left. Assign more days or reject the application.');
            }
            $half = (int) $a['half_day'] === 1;
            foreach (hr_leave_dates($a['from_date'], $a['to_date'], hr_off_days($a['from_date'], $a['to_date'])) as $date) {
                $unpaid = (int) $a['is_paid'] ? 0.0 : ($half ? 0.5 : 1.0);
                hr_write("INSERT INTO hr_attendance (employee_id, attendance_date, status, unpaid_days, application_id, source, updated_by) VALUES (?, ?, ?, ?, ?, 'leave', ?)
                    ON DUPLICATE KEY UPDATE status=VALUES(status), unpaid_days=VALUES(unpaid_days), application_id=VALUES(application_id), source='leave', updated_by=VALUES(updated_by)",
                    'issdii', [(int) $a['employee_id'], $date, $half ? 'half_leave' : 'leave', $unpaid, (int) $a['application_id'], (int) $by]);
            }
        }
        hr_write('UPDATE hr_leave_applications SET status=?, decided_by=?, decided_at=NOW(), decision_note=? WHERE application_id=?', 'sisi', [$approve ? 'approved' : 'rejected', (int) $by, mb_substr($note, 0, 190), (int) $a['application_id']]);
        notify_employee($a['employee_id'], 'Leave ' . ($approve ? 'approved' : 'rejected'), date('d/m/Y', strtotime($a['from_date'])) . ' to ' . date('d/m/Y', strtotime($a['to_date'])) . ($note !== '' ? '. ' . $note : ''), 'hr_leave.php');
    });
}

/** Cancel a leave. A person can cancel their own pending one; the office can also cancel an approved one, which clears its attendance days. */
function hr_cancel_leave($applicationId, $by, $employeeId = 0)
{
    hr_transaction(function () use ($applicationId, $by, $employeeId) {
        $a = hr_row('SELECT * FROM hr_leave_applications WHERE application_id=? FOR UPDATE', 'i', [(int) $applicationId]);
        if (!$a || ($employeeId && (int) $a['employee_id'] !== (int) $employeeId) || !in_array($a['status'], $employeeId ? ['pending'] : ['pending', 'approved'], true)) {
            throw new RuntimeException('This application cannot be cancelled.');
        }
        hr_write('DELETE FROM hr_attendance WHERE application_id=?', 'i', [(int) $a['application_id']]);
        hr_write("UPDATE hr_leave_applications SET status='cancelled', decided_by=?, decided_at=NOW() WHERE application_id=?", 'ii', [(int) $by, (int) $a['application_id']]);
        if (!$employeeId) {
            notify_employee($a['employee_id'], 'Leave cancelled by the office', date('d/m/Y', strtotime($a['from_date'])) . ' to ' . date('d/m/Y', strtotime($a['to_date'])), 'hr_leave.php');
        }
    });
}

/** Portal check-in or check-out for today. Refuses a day that is on approved leave. */
function hr_mark_self($employeeId, $in)
{
    $today = date('Y-m-d');
    $now = date('H:i:s');
    $row = hr_row('SELECT * FROM hr_attendance WHERE employee_id=? AND attendance_date=?', 'is', [(int) $employeeId, $today]);
    if ($row && $row['application_id']) {
        throw new RuntimeException('Today is marked as leave.');
    }
    if ($in) {
        if ($row && $row['check_in']) {
            throw new RuntimeException('You have already checked in today.');
        }
        $status = substr($now, 0, 5) > hr_settings()['late_after'] ? 'late' : 'present';
        hr_write("INSERT INTO hr_attendance (employee_id, attendance_date, status, check_in, source) VALUES (?, ?, ?, ?, 'portal')
            ON DUPLICATE KEY UPDATE status=VALUES(status), check_in=VALUES(check_in), source='portal'", 'isss', [(int) $employeeId, $today, $status, $now]);
        return;
    }
    if (!$row || !$row['check_in']) {
        throw new RuntimeException('Check in first.');
    }
    if ($row['check_out']) {
        throw new RuntimeException('You have already checked out today.');
    }
    hr_write('UPDATE hr_attendance SET check_out=? WHERE attendance_id=?', 'si', [$now, (int) $row['attendance_id']]);
}

/** Attendance totals of a month: counts by status, plus absent and unpaid-leave days as the salary sees them. */
function hr_month_counts($employeeId, $month)
{
    $counts = array_fill_keys(array_keys(HR_ATTENDANCE_MARK), 0) + ['absent_days' => 0.0, 'unpaid_days' => 0.0];
    foreach (hr_rows('SELECT status, COUNT(*) AS n, SUM(unpaid_days) AS unpaid FROM hr_attendance WHERE employee_id=? AND attendance_date BETWEEN ? AND LAST_DAY(?) GROUP BY status', 'iss', [(int) $employeeId, $month, $month]) as $r) {
        $counts[$r['status']] = (int) $r['n'];
        $counts['unpaid_days'] += (float) $r['unpaid'];
    }
    $counts['absent_days'] = $counts['absent'] + $counts['half_day'] * 0.5;
    return $counts;
}

/** The salary row in force in a month, or null. */
function hr_salary_for($employeeId, $month)
{
    return hr_row('SELECT * FROM hr_salaries WHERE employee_id=? AND effective_from<=LAST_DAY(?) ORDER BY effective_from DESC LIMIT 1', 'is', [(int) $employeeId, $month]);
}

/**
 * A month's pay. $slip needs the salary columns, bonus and advance. A day's pay is the month's gross over the calendar days;
 * absent days, unpaid leave and days before joining or after leaving are taken off. A day with no attendance record counts as worked.
 */
function hr_payslip_sums(array $slip, array $employee, $month, array $counts)
{
    $first = strtotime($month . ' 12:00');
    $monthDays = (int) date('t', $first);
    $last = date('Y-m-t', $first);
    $gross = round((float) $slip['basic'] + (float) $slip['house_rent'] + (float) $slip['medical'] + (float) $slip['conveyance'] + (float) $slip['other_allowance'], 2);
    $missing = 0;
    if ($employee['join_date'] > $month) {
        $missing += min($monthDays, (int) ((strtotime($employee['join_date'] . ' 12:00') - $first) / 86400));
    }
    if (!empty($employee['exit_date']) && $employee['exit_date'] < $last) {
        $missing += min($monthDays, (int) ((strtotime($last . ' 12:00') - strtotime(max($employee['exit_date'], date('Y-m-d', $first - 86400)) . ' 12:00')) / 86400));
    }
    $missing = min($monthDays, $missing);
    $lost = min($monthDays, $missing + $counts['absent_days'] + $counts['unpaid_days']);
    $absence = round($gross / $monthDays * $lost, 2);
    $net = round($gross + (float) $slip['bonus'] - $absence - (float) $slip['tax'] - (float) $slip['provident_fund'] - (float) $slip['other_deduction'] - (float) $slip['advance'], 2);
    return ['gross' => $gross, 'month_days' => $monthDays, 'absent_days' => $counts['absent_days'], 'unpaid_leave_days' => $counts['unpaid_days'], 'not_employed_days' => $missing,
        'absence_deduction' => $absence, 'net_pay' => $net];
}

/** The payslip as a printable block, for the office and the portal. $e is the employee with department and designation names. */
function hr_payslip_html(array $p, array $e, array $company, callable $money)
{
    $h = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
    $earn = ['Basic' => $p['basic'], 'House Rent' => $p['house_rent'], 'Medical' => $p['medical'], 'Conveyance' => $p['conveyance'], 'Other Allowance' => $p['other_allowance'], 'Bonus' => $p['bonus']];
    $less = ['Absence & Unpaid Leave' => $p['absence_deduction'], 'Tax' => $p['tax'], 'Provident Fund' => $p['provident_fund'], 'Other Deduction' => $p['other_deduction'], 'Advance' => $p['advance']];
    $lines = fn(array $rows) => implode('', array_map(fn($k, $v) => '<tr><td>' . $k . '</td><td class="num">' . $money($v) . '</td></tr>', array_keys($rows), $rows));
    return '<div class="slip"><h2>' . $h($company['name'] ?? '') . '</h2><p class="muted">' . $h($company['address'] ?? '') . '</p><h3>Payslip for ' . date('F Y', strtotime($p['period_month'])) . '</h3>'
        . '<div class="meta"><div><b>Employee:</b> ' . $h($e['full_name']) . ' (' . $h($e['employee_code']) . ')</div><div><b>Designation:</b> ' . $h($e['designation_name'] ?? '') . '</div>'
        . '<div><b>Department:</b> ' . $h($e['department_name'] ?? '') . '</div><div><b>Status:</b> ' . ucfirst($h($p['status'])) . ($p['paid_on'] ? ' on ' . date('d/m/Y', strtotime($p['paid_on'])) : '') . '</div>'
        . '<div><b>Days in month:</b> ' . (int) $p['month_days'] . '</div><div><b>Absent / Unpaid leave / Not employed:</b> ' . (float) $p['absent_days'] . ' / ' . (float) $p['unpaid_leave_days'] . ' / ' . (int) $p['not_employed_days'] . '</div></div>'
        . '<table><thead><tr><th>Earnings</th><th class="num">Amount</th></tr></thead><tbody>' . $lines($earn) . '<tr><td><b>Total</b></td><td class="num"><b>' . $money((float) $p['gross'] + (float) $p['bonus']) . '</b></td></tr></tbody></table>'
        . '<table><thead><tr><th>Deductions</th><th class="num">Amount</th></tr></thead><tbody>' . $lines($less) . '<tr><td><b>Total</b></td><td class="num"><b>' . $money(array_sum($less)) . '</b></td></tr></tbody></table>'
        . '<p class="net"><b>Net Pay: ' . $money($p['net_pay']) . '</b></p></div>';
}

/* ---------------- Employee file ---------------- */

function hr_document_dir()
{
    return dirname(__DIR__, 2) . '/billing/hr/uploads';
}

/** The record's values from a form, cleaned and checked, as column => value. */
function hr_record_values($kind, array $post)
{
    $out = [];
    foreach (HR_RECORDS[$kind][3] as $col => [$label, $type, $size, $required]) {
        $value = is_string($post[$col] ?? null) ? trim($post[$col]) : '';
        switch ($type) {
            case 'check':
                $out[$col] = isset($post[$col]) ? 1 : 0;
                continue 2;
            case 'select':
                if ($value !== '' && !in_array($value, $size, true)) {
                    throw new RuntimeException('Pick a valid ' . $label . '.');
                }
                break;
            case 'date':
                $date = DateTime::createFromFormat('!Y-m-d', $value);
                if ($value !== '' && (!$date || $date->format('Y-m-d') !== $value)) {
                    throw new RuntimeException('Enter a valid date for ' . $label . '.');
                }
                break;
            case 'year':
                if ($value !== '' && (!ctype_digit($value) || (int) $value < 1950 || (int) $value > (int) date('Y') + 1)) {
                    throw new RuntimeException('Enter a year for ' . $label . ', such as 2015.');
                }
                break;
            case 'amount':
            case 'percent':
                if ($value !== '' && (!is_numeric($value) || (float) $value < 0 || ($type === 'percent' && (float) $value > 100))) {
                    throw new RuntimeException('Enter a valid number for ' . $label . '.');
                }
                break;
            case 'email':
                if ($value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    throw new RuntimeException('Enter a valid email address or leave it blank.');
                }
                break;
        }
        if ($required && $value === '') {
            throw new RuntimeException('Enter ' . $label . '.');
        }
        if (in_array($type, ['amount', 'percent'], true)) {
            $out[$col] = round((float) $value, 2);
        } elseif (in_array($type, ['date', 'year'], true)) {
            $out[$col] = $value === '' ? null : $value;
        } else {
            $out[$col] = mb_substr($value, 0, is_int($size) && $size ? $size : 190); // a select's size is its option list
        }
    }
    if (!empty($out['from_date']) && !empty($out['to_date']) && $out['to_date'] < $out['from_date']) {
        throw new RuntimeException('The end date cannot be before the start date.');
    }
    return $out;
}

/** Save one record, new when $recordId is 0. */
function hr_save_record($kind, $employeeId, $recordId, array $values)
{
    $table = HR_RECORDS[$kind][1];
    if ($kind === 'family') {
        $others = (float) hr_row('SELECT COALESCE(SUM(nominee_share), 0) AS s FROM hr_family WHERE employee_id=? AND record_id<>?', 'ii', [(int) $employeeId, (int) $recordId])['s'];
        if ($others + $values['nominee_share'] > 100) {
            throw new RuntimeException('Nominee shares cannot pass 100%. ' . (100 - $others) . '% is left.');
        }
    }
    $types = implode('', array_map(fn($v) => is_int($v) ? 'i' : (is_float($v) ? 'd' : 's'), $values));
    if ($recordId) {
        $sets = implode(', ', array_map(fn($c) => "$c=?", array_keys($values)));
        hr_write("UPDATE $table SET $sets WHERE record_id=? AND employee_id=?", $types . 'ii', array_merge(array_values($values), [(int) $recordId, (int) $employeeId]));
        return;
    }
    $cols = implode(', ', array_keys($values));
    hr_write("INSERT INTO $table (employee_id, $cols) VALUES (?, " . implode(', ', array_fill(0, count($values), '?')) . ')', 'i' . $types, array_merge([(int) $employeeId], array_values($values)));
}

function hr_records($kind, $employeeId)
{
    [, $table, $order] = HR_RECORDS[$kind];
    return hr_rows("SELECT * FROM $table WHERE employee_id=? ORDER BY $order", 'i', [(int) $employeeId]);
}

/** One value as a list shows it. */
function hr_record_cell(array $field, $value)
{
    $type = $field[1];
    if ($type === 'check') {
        return (int) $value ? 'Yes' : 'No';
    }
    if ($value === null || $value === '') {
        return '';
    }
    if ($type === 'date') {
        return date('d/m/Y', strtotime($value));
    }
    if ($type === 'amount') {
        return number_format((float) $value, 2);
    }
    if ($type === 'percent') {
        return (float) $value ? (float) $value . '%' : '';
    }
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/** Years and months of past jobs, in words. A job with no end date runs to the day this person joined. */
function hr_experience_total(array $jobs, $joinDate)
{
    $months = 0;
    foreach ($jobs as $job) {
        $to = new DateTime($job['to_date'] ?: $joinDate);
        $from = new DateTime($job['from_date']);
        if ($to > $from) {
            $gap = $from->diff($to);
            $months += $gap->y * 12 + $gap->m;
        }
    }
    return intdiv($months, 12) . ' year(s) ' . ($months % 12) . ' month(s)';
}

/** Check and keep an uploaded file. Returns the hr_documents id. */
function hr_save_document($employeeId, $type, $title, array $file, $by)
{
    if (!in_array($type, HR_DOCUMENT_TYPES, true)) {
        throw new RuntimeException('Pick the document type.');
    }
    $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
        throw new RuntimeException('That file is larger than the server accepts.');
    }
    if ($error !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'] ?? '')) {
        throw new RuntimeException('Choose a file.');
    }
    if ((int) $file['size'] > HR_DOCUMENT_MAX) {
        throw new RuntimeException('A file can be up to 5 MB.');
    }
    $ext = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
    $mime = (string) (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if (!isset(HR_DOCUMENT_FILES[$ext]) || !in_array($mime, HR_DOCUMENT_FILES[$ext], true)) {
        throw new RuntimeException('Upload a PDF, JPG, PNG, DOC or DOCX file.');
    }
    if ($type === 'Photo' && !str_starts_with($mime, 'image/')) {
        throw new RuntimeException('A photo must be a JPG or PNG file.');
    }
    $dir = hr_document_dir();
    if (!is_dir($dir) && !mkdir($dir, 0775, true)) {
        throw new RuntimeException('The uploads folder could not be created.');
    }
    $stored = bin2hex(random_bytes(16)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $stored)) {
        throw new RuntimeException('Could not save the file. Check that billing/hr/uploads is writable.');
    }
    $stmt = hr_exec('INSERT INTO hr_documents (employee_id, document_type, title, original_name, stored_name, mime_type, size_bytes, uploaded_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)', 'isssssii',
        [(int) $employeeId, $type, mb_substr(trim($title), 0, 190), mb_substr(basename((string) $file['name']), 0, 190), $stored, $mime, (int) $file['size'], (int) $by]);
    $id = (int) $stmt->insert_id;
    $stmt->close();
    if ($type === 'Photo') {
        foreach (hr_rows("SELECT document_id FROM hr_documents WHERE employee_id=? AND document_type='Photo' AND document_id<>?", 'ii', [(int) $employeeId, $id]) as $old) {
            hr_delete_document($old['document_id'], $employeeId); // one photo: the new one replaces it
        }
    }
    return $id;
}

/** The document id of a person's photo, or 0. */
function hr_photo_id($employeeId)
{
    return (int) (hr_row("SELECT MAX(document_id) AS id FROM hr_documents WHERE employee_id=? AND document_type='Photo'", 'i', [(int) $employeeId])['id'] ?? 0);
}

function hr_delete_document($documentId, $employeeId)
{
    $doc = hr_row('SELECT * FROM hr_documents WHERE document_id=? AND employee_id=?', 'ii', [(int) $documentId, (int) $employeeId]);
    if ($doc) {
        hr_write('DELETE FROM hr_documents WHERE document_id=?', 'i', [(int) $documentId]);
        @unlink(hr_document_dir() . '/' . basename($doc['stored_name']));
    }
}

/** Send a document of this employee to the browser, and stop. */
function hr_send_document($documentId, $employeeId)
{
    $doc = hr_row('SELECT * FROM hr_documents WHERE document_id=? AND employee_id=?', 'ii', [(int) $documentId, (int) $employeeId]);
    $path = $doc ? hr_document_dir() . '/' . basename($doc['stored_name']) : '';
    if (!$doc || !is_file($path)) {
        http_response_code(404);
        exit('Document not found.');
    }
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    $inline = str_starts_with($doc['mime_type'], 'image/') || $doc['mime_type'] === 'application/pdf';
    header('Content-Type: ' . $doc['mime_type']);
    header('Content-Length: ' . filesize($path));
    header('X-Content-Type-Options: nosniff');
    header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . str_replace(['"', "\r", "\n"], '', $doc['original_name']) . '"');
    readfile($path);
    exit;
}

/* ---------------- Salary tax ---------------- */

/** Tax on a year's income from the bands, lowest first. A band_amount of 0 takes all that is left. */
function hr_tax_on(array $slabs, $yearly)
{
    $left = (float) $yearly;
    $tax = 0.0;
    foreach ($slabs as $slab) {
        $band = (float) $slab['band_amount'];
        $part = $band > 0 ? min($left, $band) : $left;
        $tax += $part * (float) $slab['rate'] / 100;
        $left -= $part;
        if ($left <= 0) {
            break;
        }
    }
    return $tax;
}

/** A month's tax on a monthly gross: the year's tax on twelve months, shared evenly. */
function hr_monthly_tax($gross)
{
    return round(hr_tax_on(hr_rows('SELECT band_amount, rate FROM hr_tax_slabs ORDER BY sort_order, slab_id'), (float) $gross * 12) / 12, 2);
}

function hr_salary_gross(array $salary)
{
    return round((float) $salary['basic'] + (float) $salary['house_rent'] + (float) $salary['medical'] + (float) $salary['conveyance'] + (float) $salary['other_allowance'], 2);
}

/** Tax deducted from a person's approved and paid payslips in a year. */
function hr_tax_for_year($employeeId, $year)
{
    return (float) hr_row("SELECT COALESCE(SUM(tax), 0) AS t FROM hr_payslips WHERE employee_id=? AND YEAR(period_month)=? AND status IN ('approved','paid')", 'ii', [(int) $employeeId, (int) $year])['t'];
}

