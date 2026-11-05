<?php
/** My HR: the person's profile as the office has it, with leave and this month's attendance at a glance. */
require_once __DIR__ . '/sr_common.php';
require_once dirname(__DIR__) . '/addon/inc/hr.php';
$a = sr_require_login();
$e = sr_hr_page_start($a, 'My HR', 'hr');
$id = (int) $e['employee_id'];
$counts = hr_month_counts($id, date('Y-m-01'));
$left = array_sum(array_map(fn($b) => (int) $b['is_paid'] ? $b['left'] : 0, hr_leave_balances($id, (int) date('Y'))));
$pending = (int) cp_row("SELECT COUNT(*) AS n FROM hr_leave_applications WHERE employee_id=? AND status='pending'", 'i', [$id])['n'];
echo '<div class="tiles"><div class="tile ok"><span>Present this month</span><b>' . ($counts['present'] + $counts['late']) . '</b></div><div class="tile' . ($counts['absent_days'] ? ' bad' : '') . '"><span>Absent this month</span><b>' . (float) $counts['absent_days'] . '</b></div>'
    . '<div class="tile"><span>Leave days left</span><b>' . (float) $left . '</b></div><div class="tile"><span>Leave waiting for approval</span><b>' . $pending . '</b></div></div>';
$photo = hr_photo_id($id);
if ($photo) {
    echo '<img class="hr-photo" src="hr_records.php?doc=' . $photo . '" alt="My photo">';
}
$rows = ['Employee Code' => $e['employee_code'], 'Name' => $e['full_name'], 'Designation' => $e['designation_name'], 'Department' => $e['department_name'], 'Reports To' => $e['manager_name'], 'Type' => HR_TYPES[$e['employment_type']], 'Status' => HR_STATUS[$e['status']],
    'Joining Date' => cp_date($e['join_date']), 'Mobile' => $e['mobile'], 'Email' => $e['email'], 'Date of Birth' => $e['date_of_birth'] ? cp_date($e['date_of_birth']) : '', 'Blood Group' => $e['blood_group'], 'Marital Status' => ucfirst($e['marital_status']), 'Spouse' => $e['spouse_name'], 'Nationality' => $e['nationality'], 'Religion' => $e['religion'], 'NID' => $e['nid'], 'Passport No' => $e['passport_no'], 'Driving License' => $e['driving_license'], 'TIN' => $e['tin'],
    'Present Address' => $e['present_address'], 'Permanent Address' => $e['permanent_address'], 'Emergency Contact' => trim($e['emergency_name'] . ' ' . $e['emergency_mobile']), 'Skills' => $e['skills'], 'Languages' => $e['languages'], 'Bank' => trim($e['bank_name'] . ' ' . $e['bank_account'])];
echo '<div class="card meta">';
foreach ($rows as $label => $value) {
    echo '<div><b>' . $label . ':</b> ' . cp_h($value) . '</div>';
}
echo '</div><p class="muted">If anything here is wrong, please tell the office.</p>';
sr_page_end();
