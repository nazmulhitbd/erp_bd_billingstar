<?php
PHP_SAPI === 'cli' || exit; // a developer tool: never from the web
// Stage 6b: leave, attendance, SR targets, RMA problems and a complaint, an announcement.
require __DIR__ . '/seed_lib.php';
$emp = fn($code) => sb_id('hr_employees', 'employee_id', "employee_code='$code'");
$ids = array_map('intval', array_column(sb_db()->query('SELECT employee_id FROM hr_employees')->fetch_all(MYSQLI_ASSOC), 'employee_id'));

foreach ([1 => 10, 2 => 14, 3 => 18] as $type => $days) { // casual, sick, earned leave for the year
    sb_post("hr/leave_assign.php?year=2026&type=$type", ['year' => 2026, 'type' => $type, 'action' => 'save', 'days' => array_fill_keys($ids, $days)], "leave entitlement type $type");
}
sb_post('hr/leave_applications.php', ['employee_id' => $emp('EMP-003'), 'leave_type_id' => 2, 'from_date' => '2026-11-04', 'to_date' => '2026-11-04', 'reason' => 'Fever', 'action' => 'add'], 'leave application');

foreach (['2026-11-01', '2026-11-02', '2026-11-03', '2026-11-04', '2026-11-05'] as $date) {
    $rows = [];
    foreach ($ids as $i => $id) {
        $late = ($i + (int) substr($date, -1)) % 5 === 0;
        $absent = $date === '2026-11-04' && $id === $emp('EMP-003');
        $rows[$id] = $absent ? ['status' => 'absent', 'in' => '', 'out' => '', 'note' => 'Sick leave']
            : ['status' => $late ? 'late' : 'present', 'in' => $late ? '09:25' : '08:55', 'out' => '18:05', 'note' => ''];
    }
    sb_post("hr/attendance.php?date=$date", ['date' => $date, 'department' => 0, 'action' => 'save', 'a' => $rows], "attendance $date");
}

foreach (['2026-10', '2026-11'] as $month) {
    sb_post("sales_person/sr_target.php?month=$month", ['month' => $month, 'action' => 'save', 't' => [
        1 => ['sales' => 600000, 'collection' => 400000, 'note' => 'Corporate accounts'],
        2 => ['sales' => 450000, 'collection' => 300000, 'note' => 'Dealers, Dhaka South'],
        3 => ['sales' => 350000, 'collection' => 250000, 'note' => 'Chattogram'],
    ]], "SR targets $month");
}

$p = sb_product('P-1002');
$cust = sb_party('customer', 'Riverside Electronics');
sb_post('rma/complain_receive.php', ['action' => 'save', 'doc_id' => 0, 'h' => ['ref_no' => '', 'complain_date' => '2026-11-04', 'mobile' => $cust['mobile'], 'complain_no' => '', 'customer_group' => $cust['party_group'],
    'customer_name' => $cust['party_name'], 'customer_id' => $cust['party_id'], 'address' => $cust['address'], 'attention' => 'Owner'],
    'items_json' => json_encode([['product_group' => $p['product_group'], 'brand' => $p['brand'], 'model_no' => $p['model_caption'], 'product_id' => $p['product_id'], 'description' => $p['description'],
        'sequence_type' => 'not_available', 'under_warranty' => '1', 'sales_invoice_no' => 'SI2026000007', 'sales_value' => 22400, 'sales_date' => '2026-10-20', 'sold_serial' => '', 'store_name' => 'RMA STORE',
        'delivery_date' => '2026-11-11', 'problem_name' => 'Hang Problem', 'engineer_name' => 'Imran Hossain', 'no_of_time' => 1]])], 'RMA complaint', '/[A-Z]+COM\d+ saved\./');

sb_post('hr/announcements.php', ['title' => 'Festive season stock count', 'body' => 'All stores will be counted on Thursday after closing. Please finish pending deliveries before 5 pm.',
    'to' => 'all', 'department_id' => 0, 'employee_id' => 0, 'action' => 'add'], 'announcement');
echo "done\n";
