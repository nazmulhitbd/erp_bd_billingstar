<?php
PHP_SAPI === 'cli' || exit; // a developer tool: never from the web
// Stage 6a: HR setup and employees (invented people, fake contact details).
require __DIR__ . '/seed_lib.php';
foreach (['Sales', 'Accounts', 'Service', 'Store'] as $d) {
    sb_post('hr/hr_setup.php?tab=department', ['tab' => 'department', 'id' => 0, 'name' => $d, 'is_active' => 1, 'action' => 'save'], "department $d");
}
foreach (['Branch Manager', 'Sales Officer', 'Accountant', 'Service Engineer', 'Store Keeper'] as $d) {
    sb_post('hr/hr_setup.php?tab=designation', ['tab' => 'designation', 'id' => 0, 'name' => $d, 'is_active' => 1, 'action' => 'save'], "designation $d");
}
$dep = fn($n) => sb_id('hr_departments', 'department_id', "department_name='$n'");
$des = fn($n) => sb_id('hr_designations', 'designation_id', "designation_name='$n'");
$people = [ // code, name, department, designation, branch, sr, gender, join, mobile
    ['EMP-001', 'Rafiq Islam', 'Sales', 'Branch Manager', 1, 0, 'male', '2025-01-05', '01000000501'],
    ['EMP-002', 'Arif Hasan', 'Sales', 'Sales Officer', 1, 1, 'male', '2026-01-15', '01000000401'],
    ['EMP-003', 'Nadia Karim', 'Sales', 'Sales Officer', 1, 2, 'female', '2026-01-15', '01000000402'],
    ['EMP-004', 'Tanvir Ahmed', 'Sales', 'Sales Officer', 2, 3, 'male', '2026-01-15', '01000000403'],
    ['EMP-005', 'Sumaiya Rahman', 'Accounts', 'Accountant', 1, 0, 'female', '2025-06-01', '01000000502'],
    ['EMP-006', 'Imran Hossain', 'Service', 'Service Engineer', 1, 0, 'male', '2025-09-10', '01000000503'],
];
foreach ($people as [$code, $name, $d, $g, $branch, $sr, $gender, $join, $mobile]) {
    sb_post('hr/employees.php?new=1', ['id' => 0, 'employee_code' => $code, 'full_name' => $name, 'department_id' => $dep($d), 'designation_id' => $des($g), 'branch_id' => $branch, 'reports_to' => 0,
        'employment_type' => 'permanent', 'status' => 'active', 'join_date' => $join, 'confirm_date' => '', 'exit_date' => '', 'sr_id' => $sr, 'mobile' => $mobile, 'email' => strtolower(explode(' ', $name)[0]) . '@example.com',
        'gender' => $gender, 'nationality' => 'Bangladeshi', 'present_address' => 'Sample address, Dhaka', 'action' => 'save'], "employee $name");
}
echo "done\n";
