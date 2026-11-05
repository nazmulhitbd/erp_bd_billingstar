<?php
/**
 * Salary Tax: the tax bands, tax deducted by person and month, and a yearly certificate. A person's salary says whether their
 * tax comes from these bands or is typed in. Changing the bands updates everyone on bands. Rates are entered here, not built in,
 * because they change with each year's law; exemptions and rebates are not worked out, so use manual tax where they apply.
 */
require_once __DIR__ . '/hr_common.php';

$self = 'salary_tax.php';
$year = (int) ($_REQUEST['year'] ?? date('Y'));
$year = $year >= 2000 && $year <= 2100 ? $year : (int) date('Y');
$back = $self . '?year=' . $year;

/** Salaries on bands take the new tax. Earlier payslips keep what they had. */
function hr_refresh_slab_tax(mysqli $db)
{
    foreach (sales_rows($db, "SELECT * FROM hr_salaries WHERE tax_mode='slab'") as $s) {
        sales_exec($db, 'UPDATE hr_salaries SET tax=? WHERE salary_id=?', 'di', [hr_monthly_tax(hr_salary_gross($s)), (int) $s['salary_id']])->close();
    }
}

if (isset($_GET['cert']) && hr_ready($conn)) {
    $e = sales_row($conn, "SELECT e.*, COALESCE(g.designation_name, '') AS designation_name FROM hr_employees e LEFT JOIN hr_designations g ON g.designation_id=e.designation_id WHERE e.employee_id=?", 'i', [(int) $_GET['cert']]);
    if (!$e) {
        http_response_code(404);
        exit('Employee not found.');
    }
    $rows = sales_rows($conn, "SELECT period_month, gross, bonus, absence_deduction, tax FROM hr_payslips WHERE employee_id=? AND YEAR(period_month)=? AND status IN ('approved','paid') ORDER BY period_month", 'ii', [(int) $e['employee_id'], $year]);
    $company = inv_company($conn);
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Salary Tax Certificate</title><style>' . INV_PRINT_CSS . '</style></head><body><div class="bar"><button onclick="window.print()">Print</button></div><h2 class="pt">' . sales_h($company['name']) . '</h2><p style="text-align:center"><b>Salary Tax Certificate ' . $year . '</b></p>'
        . '<div class="meta"><div><b>Name:</b> ' . sales_h($e['full_name']) . ' (' . sales_h($e['employee_code']) . ')</div><div><b>Designation:</b> ' . sales_h($e['designation_name']) . '</div><div><b>TIN:</b> ' . sales_h($e['tin']) . '</div></div>'
        . '<table><thead><tr><th>Month</th><th class="num">Salary Earned</th><th class="num">Tax Deducted</th></tr></thead><tbody>';
    $earned = $tax = 0.0;
    foreach ($rows as $r) {
        $pay = (float) $r['gross'] + (float) $r['bonus'] - (float) $r['absence_deduction'];
        $earned += $pay;
        $tax += (float) $r['tax'];
        echo '<tr><td>' . date('F Y', strtotime($r['period_month'])) . '</td><td class="num">' . sales_money($pay) . '</td><td class="num">' . sales_money($r['tax']) . '</td></tr>';
    }
    echo ($rows ? '' : '<tr><td colspan="3" class="muted">No payslips in this year.</td></tr>') . '<tr><th>Total</th><th class="num">' . sales_money($earned) . '</th><th class="num">' . sales_money($tax) . '</th></tr></tbody></table><div class="sign"><div>Prepared By</div><div>Authorised By</div></div></body></html>';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && hr_ready($conn)) {
    sales_require_csrf($back);
    $action = (string) ($_POST['action'] ?? '');
    try {
        if ($action === 'delete') {
            sales_exec($conn, 'DELETE FROM hr_tax_slabs WHERE slab_id=?', 'i', [(int) ($_POST['slab_id'] ?? 0)])->close();
        } else {
            $rate = sales_amount($_POST['rate'] ?? 0);
            if ($rate > 100) {
                throw new RuntimeException('Enter a rate up to 100%.');
            }
            sales_exec($conn, 'INSERT INTO hr_tax_slabs (sort_order, band_amount, rate) VALUES (?, ?, ?)', 'idd', [(int) ($_POST['sort_order'] ?? 0), sales_amount($_POST['band_amount'] ?? 0), $rate])->close();
        }
        hr_refresh_slab_tax($conn);
        sales_flash_redirect($back, 'success', 'Tax slabs saved. Everyone on slabs has been updated; payslips already approved keep their tax.');
    } catch (RuntimeException $e) {
        sales_flash_redirect($back, 'error', $e->getMessage());
    }
}

hr_page_start($conn, 'Salary Tax', 'tax');
$slabs = sales_rows($conn, 'SELECT * FROM hr_tax_slabs ORDER BY sort_order, slab_id');
$next = count($slabs) + 1;
?>
<section class="section" style="border-top:0;margin-top:0;padding-top:0"><div class="section-title">Yearly tax slabs</div>
<form method="post" class="grid g4" style="margin-top:0;align-items:end" autocomplete="off"><?= sales_csrf_field() ?><input type="hidden" name="year" value="<?= $year ?>">
<div class="field"><label for="sort_order">Order</label><input id="sort_order" type="number" min="0" name="sort_order" value="<?= $next ?>"></div>
<div class="field"><label for="band_amount">Band Amount</label><input id="band_amount" type="number" min="0" step="0.01" name="band_amount" placeholder="0 = all the rest"></div>
<div class="field"><label for="rate">Rate %</label><input id="rate" type="number" min="0" max="100" step="0.01" name="rate" required></div>
<div class="toolbar"><button class="btn primary" name="action" value="add">Add</button></div></form>
<div class="table-scroll" style="margin-top:8px"><table><thead><tr><th>Order</th><th class="num">Band Amount</th><th class="num">Rate</th><th></th></tr></thead><tbody>
<?php foreach ($slabs as $s): ?><tr><td><?= (int) $s['sort_order'] ?></td><td class="num"><?= (float) $s['band_amount'] ? sales_money($s['band_amount']) : 'All the rest' ?></td><td class="num"><?= (float) $s['rate'] ?>%</td><td><form method="post" onsubmit="return confirm('Delete this slab?')"><?= sales_csrf_field() ?><input type="hidden" name="year" value="<?= $year ?>"><input type="hidden" name="slab_id" value="<?= (int) $s['slab_id'] ?>"><button class="btn small danger" name="action" value="delete">Delete</button></form></td></tr><?php endforeach; ?>
<?= $slabs ? '' : '<tr><td colspan="4" class="empty">No slabs yet. Enter the bands from the current tax rules, lowest first.</td></tr>' ?>
</tbody></table></div>
<p class="footnote">Yearly income is the monthly gross times 12, and the tax is shared evenly over 12 months. Exemptions and rebates are not worked out: use manual tax on a person&rsquo;s salary where they apply.</p></section>
<?php
$rows = sales_rows($conn, "SELECT p.employee_id, MONTH(p.period_month) AS m, SUM(p.tax) AS tax, e.full_name, e.employee_code, e.tin FROM hr_payslips p JOIN hr_employees e ON e.employee_id=p.employee_id WHERE YEAR(p.period_month)=? AND p.status IN ('approved','paid') GROUP BY p.employee_id, m, e.full_name, e.employee_code, e.tin ORDER BY e.full_name", 'i', [$year]);
$people = [];
foreach ($rows as $r) {
    $people[(int) $r['employee_id']] = ($people[(int) $r['employee_id']] ?? $r + ['months' => []]);
    $people[(int) $r['employee_id']]['months'][(int) $r['m']] = (float) $r['tax'];
}
$totals = array_fill(1, 12, 0.0);
?>
<section class="section"><div class="section-title">Tax deducted</div>
<form method="get" class="list-search"><input class="input" type="number" name="year" min="2000" max="2100" value="<?= $year ?>" aria-label="Year"><button class="btn">View</button></form>
<div class="table-scroll"><table><thead><tr><th>Name</th><th>TIN</th><?php for ($m = 1; $m <= 12; $m++): ?><th class="num"><?= date('M', mktime(0, 0, 0, $m, 1)) ?></th><?php endfor; ?><th class="num">Total</th><th></th></tr></thead><tbody>
<?php foreach ($people as $id => $p): ?><tr><td class="nowrap"><?= sales_h($p['full_name']) ?></td><td><?= sales_h($p['tin']) ?></td><?php for ($m = 1; $m <= 12; $m++): $v = $p['months'][$m] ?? 0; $totals[$m] += $v; ?><td class="num"><?= $v ? sales_money($v) : '' ?></td><?php endfor; ?><td class="num"><b><?= sales_money(array_sum($p['months'])) ?></b></td><td><a class="btn small" target="_blank" href="<?= $self ?>?cert=<?= (int) $id ?>&year=<?= $year ?>">Certificate</a></td></tr><?php endforeach; ?>
<?php if ($people): ?><tr><th colspan="2">Total</th><?php for ($m = 1; $m <= 12; $m++): ?><th class="num"><?= $totals[$m] ? sales_money($totals[$m]) : '' ?></th><?php endfor; ?><th class="num"><?= sales_money(array_sum($totals)) ?></th><th></th></tr><?php else: ?><tr><td colspan="16" class="empty">No tax deducted in this year.</td></tr><?php endif; ?>
</tbody></table></div></section>
<?php mgmt_page_end(); ?>
<?php include "../../addon/conf/connection_close.php"; ?>
