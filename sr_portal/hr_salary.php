<?php
/** My salary: payslips the office has approved. ?slip= opens one, for that person only. */
require_once __DIR__ . '/sr_common.php';
require_once dirname(__DIR__) . '/addon/inc/hr.php';
$a = sr_require_login();
$e = sr_hr_page_start($a, 'My Salary', 'salary');
$id = (int) $e['employee_id'];
if (isset($_GET['slip'])) {
    $p = cp_row("SELECT * FROM hr_payslips WHERE payslip_id=? AND employee_id=? AND status IN ('approved','paid')", 'ii', [(int) $_GET['slip'], $id]);
    if ($p) {
        echo '<div class="noprint tabs"><a class="btn light small" href="hr_salary.php">Back</a> <button class="btn small" data-print>Print</button></div><div class="card">' . hr_payslip_html($p, $e, sr_company(), 'cp_money') . '</div>';
        sr_page_end();
        exit;
    }
    echo '<div class="alert err">Payslip not found.</div>';
}
$s = hr_salary_for($id, date('Y-m-01'));
if ($s) {
    $gross = (float) $s['basic'] + (float) $s['house_rent'] + (float) $s['medical'] + (float) $s['conveyance'] + (float) $s['other_allowance'];
    $less = (float) $s['tax'] + (float) $s['provident_fund'] + (float) $s['other_deduction'];
    echo '<div class="tiles"><div class="tile"><span>Monthly gross</span><b>' . cp_money($gross) . '</b></div><div class="tile"><span>Regular deductions</span><b>' . cp_money($less) . '</b></div><div class="tile ok"><span>Monthly net</span><b>' . cp_money($gross - $less) . '</b></div><div class="tile"><span>Tax deducted in ' . date('Y') . '</span><b>' . cp_money(hr_tax_for_year($id, (int) date('Y'))) . '</b></div></div>';
}
$rows = cp_rows("SELECT * FROM hr_payslips WHERE employee_id=? AND status IN ('approved','paid') ORDER BY period_month DESC", 'i', [$id]);
echo '<div class="scroll"><table><tr><th>Month</th><th class="num">Gross</th><th class="num">Net Pay</th><th>Status</th><th>Paid On</th><th></th></tr>';
foreach ($rows as $r) {
    echo '<tr><td>' . date('F Y', strtotime($r['period_month'])) . '</td><td class="num">' . cp_money($r['gross']) . '</td><td class="num">' . cp_money($r['net_pay']) . '</td><td><span class="badge ' . ($r['status'] === 'paid' ? 'ok' : 'warn') . '">' . ucfirst($r['status']) . '</span></td><td>' . ($r['paid_on'] ? cp_date($r['paid_on']) : '') . '</td><td><a class="btn small" href="hr_salary.php?slip=' . (int) $r['payslip_id'] . '">View</a></td></tr>';
}
echo ($rows ? '' : '<tr><td colspan="6" class="empty">No payslips yet.</td></tr>') . '</table></div>';
sr_page_end();
