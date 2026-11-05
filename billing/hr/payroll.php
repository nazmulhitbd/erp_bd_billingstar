<?php
/**
 * Payroll: Generate makes a draft payslip per person from their salary and the month's attendance. Bonus and advance can be
 * typed on a draft. Approve shows the payslip in the person's portal; Pay records the payment. Generate only ever touches
 * drafts, so an approved or paid slip does not change. Sales commission stays on its own page (Sales Team > Commission).
 * Paying posts a journal to Accounts from the accounts on HR Setup. Undo Payment posts the opposite journal, so nothing leaves the books.
 */
require_once __DIR__ . '/hr_common.php';

$self = 'payroll.php';
const HR_SLIP_COPY = ['basic', 'house_rent', 'medical', 'conveyance', 'other_allowance', 'tax', 'provident_fund', 'other_deduction'];
$month = hr_month($_REQUEST['month'] ?? '');
$back = $self . '?month=' . substr($month, 0, 7);

/** Employee row with the names a payslip shows. */
function hr_slip_employee(mysqli $db, $id)
{
    return sales_row($db, "SELECT e.*, COALESCE(d.department_name, '') AS department_name, COALESCE(g.designation_name, '') AS designation_name FROM hr_employees e LEFT JOIN hr_departments d ON d.department_id=e.department_id LEFT JOIN hr_designations g ON g.designation_id=e.designation_id WHERE e.employee_id=?", 'i', [(int) $id]);
}

/** Pay one approved payslip: record it and post its journal. Runs inside the caller's transaction. */
function hr_disburse(mysqli $db, array $p, $paid, $method, $reference, $accountId, array $settings)
{
    if ($p['status'] !== 'approved') {
        throw new RuntimeException('Approve the payslip before paying it.');
    }
    if ((float) $p['net_pay'] <= 0) {
        throw new RuntimeException($p['full_name'] . ' has no net pay to disburse.');
    }
    $voucher = null;
    if ((int) ($settings['salary_account'] ?? 0)) {
        $branch = acc_current_branch($db);
        acc_assert_unlocked($db, $paid, $branch);
        $bank = sales_row($db, "SELECT account_id FROM acc_accounts WHERE account_id=? AND account_type IN ('cash','bank') AND is_active=1", 'i', [(int) $accountId]);
        if (!$bank) {
            throw new RuntimeException('Pick the cash or bank account to pay from.');
        }
        $month = date('M Y', strtotime($p['period_month']));
        $note = 'Salary ' . $month . ' ' . $p['full_name'] . ' (' . $p['employee_code'] . ')';
        $mode = ['bank' => 'Bank Transfer', 'cash' => 'Cash', 'mobile' => 'Mobile Banking', 'other' => 'Adjustment'][$method];
        $line = fn($account, $debit, $credit, $text) => ['account_id' => (int) $account, 'debit' => round($debit, 2), 'credit' => round($credit, 2), 'line_narration' => $text, 'branch_id' => $branch, 'transaction_mode' => $mode];
        $earned = (float) $p['gross'] + (float) $p['bonus'] - (float) $p['absence_deduction'];
        $lines = [$line($settings['salary_account'], $earned, 0, $note), $line($accountId, 0, $p['net_pay'], 'Net pay ' . $p['employee_code'])];
        foreach (['tax' => ['tax_account', 'Tax'], 'provident_fund' => ['pf_account', 'Provident fund'], 'advance' => ['advance_account', 'Advance'], 'other_deduction' => ['other_deduction_account', 'Other deduction']] as $col => [$key, $label]) {
            if ((float) $p[$col] > 0) {
                if (!(int) ($settings[$key] ?? 0)) {
                    throw new RuntimeException('Set the ' . HR_ACCOUNTS[$key] . ' account on HR Setup first.');
                }
                $lines[] = $line($settings[$key], 0, $p[$col], $label . ' ' . $p['employee_code']);
            }
        }
        [$voucher] = acc_create_voucher($db, 'journal', $paid, $lines, ['ref_no' => $reference, 'narration' => $note, 'transaction_mode' => $mode, 'source' => 'hr_payroll'], 'posted');
    }
    sales_exec($db, "UPDATE hr_payslips SET status='paid', paid_on=?, payment_method=?, reference=?, pay_account_id=?, voucher_id=? WHERE payslip_id=?", 'sssiii', [$paid, $method, $reference, (int) $accountId ?: null, $voucher, (int) $p['payslip_id']])->close();
    notify_employee($p['employee_id'], 'Salary paid: ' . date('F Y', strtotime($p['period_month'])), 'Net pay ' . sales_money($p['net_pay']) . ' paid on ' . date('d/m/Y', strtotime($paid)) . '.', 'hr_salary.php');
    return $voucher;
}

/** Take a payment back: the journal is reversed by a new one dated $date, and the payslip returns to Approved. */
function hr_undo_payment(mysqli $db, array $p, $date)
{
    if ($p['status'] !== 'paid') {
        throw new RuntimeException('This payslip is not paid.');
    }
    if ($p['voucher_id']) {
        $branch = acc_current_branch($db);
        acc_assert_unlocked($db, $date, $branch);
        $lines = [];
        foreach (sales_rows($db, 'SELECT * FROM acc_voucher_lines WHERE voucher_id=? ORDER BY line_no', 'i', [(int) $p['voucher_id']]) as $l) {
            $lines[] = ['account_id' => (int) $l['account_id'], 'debit' => (float) $l['credit'], 'credit' => (float) $l['debit'], 'line_narration' => 'Reversal: ' . $l['line_narration'], 'branch_id' => $l['branch_id'], 'transaction_mode' => $l['transaction_mode']];
        }
        acc_create_voucher($db, 'journal', $date, $lines, ['narration' => 'Salary payment undone ' . $p['employee_code'] . ' ' . date('M Y', strtotime($p['period_month'])), 'source' => 'hr_payroll'], 'posted');
    }
    sales_exec($db, "UPDATE hr_payslips SET status='approved', paid_on=NULL, reference='', pay_account_id=NULL, voucher_id=NULL WHERE payslip_id=?", 'i', [(int) $p['payslip_id']])->close();
    notify_employee($p['employee_id'], 'Salary payment corrected: ' . date('F Y', strtotime($p['period_month'])), 'The office has taken this payment back. Your payslip shows as not yet paid.', 'hr_salary.php');
}

if (isset($_GET['advice']) && hr_ready($conn)) {
    $rows = sales_rows($conn, "SELECT p.net_pay, p.status, e.full_name, e.employee_code, e.bank_name, e.bank_account FROM hr_payslips p JOIN hr_employees e ON e.employee_id=p.employee_id WHERE p.period_month=? AND p.status IN ('approved','paid') ORDER BY e.bank_name, e.full_name", 's', [$month]);
    $company = inv_company($conn);
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Bank Advice</title><style>' . INV_PRINT_CSS . '</style></head><body><div class="bar"><button onclick="window.print()">Print</button></div><h2 class="pt">' . sales_h($company['name']) . '</h2><p style="text-align:center">Salary Bank Advice for ' . date('F Y', strtotime($month)) . '</p>'
        . '<table><thead><tr><th>SL</th><th>Code</th><th>Name</th><th>Bank</th><th>Account No</th><th class="num">Amount</th></tr></thead><tbody>';
    $total = 0.0;
    foreach ($rows as $i => $r) {
        $total += (float) $r['net_pay'];
        echo '<tr><td>' . ($i + 1) . '</td><td>' . sales_h($r['employee_code']) . '</td><td>' . sales_h($r['full_name']) . '</td><td>' . sales_h($r['bank_name']) . '</td><td>' . ($r['bank_account'] !== '' ? sales_h($r['bank_account']) : '<b>Missing</b>') . '</td><td class="num">' . sales_money($r['net_pay']) . '</td></tr>';
    }
    echo '<tr><th colspan="5">Total</th><th class="num">' . sales_money($total) . '</th></tr></tbody></table><p><b>In Word:</b> ' . sales_h(acc_words($total)) . '</p><div class="sign"><div>Prepared By</div><div>Checked By</div><div>Authorised By</div></div></body></html>';
    exit;
}

if (isset($_GET['print']) && hr_ready($conn)) {
    $p = sales_row($conn, 'SELECT * FROM hr_payslips WHERE payslip_id=?', 'i', [(int) $_GET['print']]);
    if (!$p) {
        http_response_code(404);
        exit('Payslip not found.');
    }
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Payslip</title><style>' . INV_PRINT_CSS . '.slip{max-width:680px;margin:auto}h2,h3,.slip>p{text-align:center;margin:4px 0}.net{font-size:16px;text-align:right!important;margin-top:10px}</style></head><body><div class="bar"><button onclick="window.print()">Print</button></div>'
        . hr_payslip_html($p, hr_slip_employee($conn, $p['employee_id']), inv_company($conn), 'sales_money') . '</body></html>';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && hr_ready($conn)) {
    sales_require_csrf($back);
    $action = (string) ($_POST['action'] ?? '');
    $id = (int) ($_POST['payslip_id'] ?? 0);
    try {
        if ($action === 'create') {
            $end = date('Y-m-t', strtotime($month));
            $made = 0;
            $skipped = [];
            $conn->begin_transaction();
            foreach (sales_rows($conn, "SELECT * FROM hr_employees WHERE status<>'onboarding' AND join_date<=? AND (exit_date IS NULL OR exit_date>=?)", 'ss', [$end, $month]) as $e) {
                $slip = sales_row($conn, 'SELECT * FROM hr_payslips WHERE employee_id=? AND period_month=? FOR UPDATE', 'is', [(int) $e['employee_id'], $month]);
                if ($slip && $slip['status'] !== 'draft') {
                    continue;
                }
                $salary = hr_salary_for($e['employee_id'], $month);
                if (!$salary) {
                    $skipped[] = $e['full_name'];
                    continue;
                }
                $pay = array_intersect_key($salary, array_flip(HR_SLIP_COPY));
                if ($salary['tax_mode'] === 'slab') {
                    $pay['tax'] = hr_monthly_tax(hr_salary_gross($salary));
                }
                $pay += ['bonus' => $slip['bonus'] ?? 0, 'advance' => $slip['advance'] ?? 0];
                $row = array_merge($pay, hr_payslip_sums($pay, $e, $month, hr_month_counts($e['employee_id'], $month)));
                $cols = array_keys($row);
                sales_exec($conn, 'INSERT INTO hr_payslips (employee_id, period_month, ' . implode(', ', $cols) . ', created_by) VALUES (?, ?, ' . implode(', ', array_fill(0, count($cols), '?')) . ', ?) ON DUPLICATE KEY UPDATE ' . implode(', ', array_map(fn($c) => "$c=VALUES($c)", $cols)),
                    'is' . str_repeat('d', count($cols)) . 'i', array_merge([(int) $e['employee_id'], $month], array_map('floatval', array_values($row)), [hr_user_id()]))->close();
                $made++;
            }
            $conn->commit();
            sales_flash_redirect($back, 'success', $made . ' payslip(s) generated.' . ($skipped ? ' No salary set for: ' . implode(', ', array_slice($skipped, 0, 8)) . (count($skipped) > 8 ? ' and ' . (count($skipped) - 8) . ' more' : '') . '.' : ''));
        }
        if ($action === 'edit_amounts') {
            $p = sales_row($conn, "SELECT * FROM hr_payslips WHERE payslip_id=? AND status='draft'", 'i', [$id]);
            if (!$p) {
                throw new RuntimeException('Only a draft can be changed.');
            }
            $p['bonus'] = sales_amount($_POST['bonus'] ?? 0);
            $p['advance'] = sales_amount($_POST['advance'] ?? 0);
            $e = sales_row($conn, 'SELECT * FROM hr_employees WHERE employee_id=?', 'i', [(int) $p['employee_id']]);
            $net = hr_payslip_sums($p, $e, $month, ['absent_days' => (float) $p['absent_days'], 'unpaid_days' => (float) $p['unpaid_leave_days']])['net_pay'];
            sales_exec($conn, 'UPDATE hr_payslips SET bonus=?, advance=?, net_pay=? WHERE payslip_id=?', 'dddi', [$p['bonus'], $p['advance'], $net, $id])->close();
            sales_flash_redirect($back, 'success', 'Payslip updated.');
        }
        if ($action === 'approve_all') {
            $drafts = sales_rows($conn, "SELECT employee_id FROM hr_payslips WHERE period_month=? AND status='draft'", 's', [$month]);
            sales_exec($conn, "UPDATE hr_payslips SET status='approved' WHERE period_month=? AND status='draft'", 's', [$month])->close();
            foreach ($drafts as $d) {
                notify_employee($d['employee_id'], 'Payslip ready: ' . date('F Y', strtotime($month)), 'Your payslip is ready to view.', 'hr_salary.php');
            }
            sales_flash_redirect($back, 'success', 'Payslips approved. People can see them in their portal.');
        }
        if ($action === 'edit_reopen') {
            sales_exec($conn, "UPDATE hr_payslips SET status='draft' WHERE payslip_id=? AND status='approved'", 'i', [$id])->close();
            sales_flash_redirect($back, 'success', 'Payslip back to draft.');
        }
        if ($action === 'edit_pay' || $action === 'edit_pay_all') {
            $paid = sales_valid_date($_POST['paid_on'] ?? '');
            if ($paid === null) {
                throw new RuntimeException('Enter the payment date.');
            }
            $method = isset(HR_PAY_METHODS[$_POST['payment_method'] ?? '']) ? $_POST['payment_method'] : 'bank';
            $settings = hr_settings();
            $select = 'SELECT p.*, e.full_name, e.employee_code FROM hr_payslips p JOIN hr_employees e ON e.employee_id=p.employee_id WHERE ';
            $conn->begin_transaction();
            $slips = $action === 'edit_pay' ? sales_rows($conn, $select . 'p.payslip_id=? FOR UPDATE', 'i', [$id]) : sales_rows($conn, $select . "p.period_month=? AND p.status='approved' AND p.net_pay>0 FOR UPDATE", 's', [$month]);
            if (!$slips) {
                throw new RuntimeException('There is nothing approved to pay.');
            }
            $posted = 0;
            foreach ($slips as $p) {
                $posted += hr_disburse($conn, $p, $paid, $method, sales_post_string('reference', 120), (int) ($_POST['pay_account_id'] ?? 0), $settings) ? 1 : 0;
            }
            $conn->commit();
            sales_flash_redirect($back, 'success', count($slips) . ' payment(s) recorded.' . ($posted ? ' ' . $posted . ' journal(s) posted to Accounts.' : ' Not posted to Accounts: set the Salary Accounts on HR Setup.'));
        }
        if ($action === 'edit_undo') {
            $date = sales_valid_date($_POST['undo_date'] ?? date('Y-m-d'));
            $conn->begin_transaction();
            $p = sales_row($conn, 'SELECT * FROM hr_payslips WHERE payslip_id=? FOR UPDATE', 'i', [$id]);
            if (!$p) {
                throw new RuntimeException('Payslip not found.');
            }
            hr_undo_payment($conn, $p, $date ?: date('Y-m-d'));
            $conn->commit();
            sales_flash_redirect($back, 'success', $p['voucher_id'] ? 'Payment undone. The journal has been reversed in Accounts.' : 'Payment undone.');
        }
        if ($action === 'delete') {
            sales_exec($conn, "DELETE FROM hr_payslips WHERE payslip_id=? AND status='draft'", 'i', [$id])->close();
            sales_flash_redirect($back, 'success', 'Draft deleted.');
        }
        throw new RuntimeException('Unknown action.');
    } catch (RuntimeException $e) {
        $conn->rollback();
        sales_flash_redirect($back, 'error', $e->getMessage());
    }
}

hr_page_start($conn, 'Payroll', 'payroll');
$rows = sales_rows($conn, 'SELECT p.*, e.full_name, e.employee_code FROM hr_payslips p JOIN hr_employees e ON e.employee_id=p.employee_id WHERE p.period_month=? ORDER BY e.full_name', 's', [$month]);
$totals = ['gross' => 0.0, 'net' => 0.0, 'paid' => 0.0];
foreach ($rows as $r) {
    $totals['gross'] += (float) $r['gross'];
    $totals['net'] += (float) $r['net_pay'];
    $totals['paid'] += $r['status'] === 'paid' ? (float) $r['net_pay'] : 0;
}
$hidden = sales_csrf_field() . '<input type="hidden" name="month" value="' . substr($month, 0, 7) . '">';
$methods = '';
foreach (HR_PAY_METHODS as $k => $label) {
    $methods .= '<option value="' . $k . '">' . $label . '</option>';
}
$accountReady = acc_ready($conn);
$payAccounts = $accountReady ? acc_account_options(acc_accounts($conn, true, "a.account_type IN ('cash','bank')"), 0, 'Pay from') : '<option value="0">Accounts not installed</option>';
$posting = $accountReady && (int) (hr_settings()['salary_account'] ?? 0);
?>
<form method="get" class="list-search"><input class="input" type="month" name="month" value="<?= substr($month, 0, 7) ?>" aria-label="Month"><button class="btn">View</button></form>
<form method="post" class="toolbar" style="margin-bottom:8px"><?= $hidden ?><button class="btn primary" name="action" value="create">Generate Payslips</button><button class="btn" name="action" value="approve_all" onclick="return confirm('Approve every draft of this month? People will see them in their portal.')"<?= $rows ? '' : ' disabled' ?>>Approve All</button></form>
<?php if (!$posting): ?><div class="alert warning">Salary payments will not reach Accounts until you choose the accounts on <a href="hr_setup.php?tab=accounts">HR Setup &gt; Salary Accounts</a>.</div><?php endif; ?>
<form method="post" class="inline" style="flex-wrap:wrap;margin-bottom:8px"><?= $hidden ?><input type="date" name="paid_on" value="<?= date('Y-m-d') ?>" aria-label="Paid on"><select name="pay_account_id" aria-label="Pay from"><?= $payAccounts ?></select><select name="payment_method" aria-label="Method"><?= $methods ?></select><input name="reference" maxlength="120" placeholder="Reference" aria-label="Reference" style="max-width:150px"><button class="btn primary" name="action" value="edit_pay_all" onclick="return confirm('Pay every approved payslip of this month?')">Pay All Approved</button><a class="btn" target="_blank" href="<?= $self ?>?month=<?= substr($month, 0, 7) ?>&advice=1">Bank Advice</a></form>
<div class="summary"><div>Payslips<b><?= count($rows) ?></b></div><div>Gross<b><?= sales_money($totals['gross']) ?></b></div><div>Net Payable<b><?= sales_money($totals['net']) ?></b></div><div>Paid<b><?= sales_money($totals['paid']) ?></b></div></div>
<div class="table-scroll"><table><thead><tr><th>SL</th><th>Name</th><th class="num">Gross</th><th class="num">Absent</th><th class="num">Unpaid</th><th class="num">Absence Cut</th><th>Bonus / Advance</th><th class="num">Net</th><th>Status</th><th></th></tr></thead><tbody>
<?php foreach ($rows as $i => $r): $sid = (int) $r['payslip_id']; ?>
<tr><td><?= $i + 1 ?></td><td><?= sales_h($r['full_name']) ?></td><td class="num"><?= sales_money($r['gross']) ?></td><td class="num"><?= (float) $r['absent_days'] ?></td><td class="num"><?= (float) $r['unpaid_leave_days'] ?></td><td class="num"><?= sales_money($r['absence_deduction']) ?></td>
<td class="nowrap"><?php if ($r['status'] === 'draft'): ?><form method="post" class="inline"><?= $hidden ?><input type="hidden" name="payslip_id" value="<?= $sid ?>"><input type="number" min="0" step="0.01" name="bonus" value="<?= (float) $r['bonus'] ?>" aria-label="Bonus" style="max-width:90px"><input type="number" min="0" step="0.01" name="advance" value="<?= (float) $r['advance'] ?>" aria-label="Advance" style="max-width:90px"><button class="btn small" name="action" value="edit_amounts">Update</button></form><?php else: ?><?= sales_money($r['bonus']) ?> / <?= sales_money($r['advance']) ?><?php endif; ?></td>
<td class="num"><b><?= sales_money($r['net_pay']) ?></b></td><td><span class="badge <?= ['draft' => '', 'approved' => 'open', 'paid' => 'converted'][$r['status']] ?>"><?= ucfirst($r['status']) ?></span><?= $r['paid_on'] ? '<br><span class="muted">' . date('d/m/Y', strtotime($r['paid_on'])) . '</span>' : '' ?></td>
<td class="nowrap"><a class="btn small" target="_blank" href="<?= $self ?>?print=<?= $sid ?>">Payslip</a>
<?php if ($r['status'] === 'approved'): ?><form method="post" class="inline"><?= $hidden ?><input type="hidden" name="payslip_id" value="<?= $sid ?>"><input type="date" name="paid_on" value="<?= date('Y-m-d') ?>" aria-label="Paid on"><select name="pay_account_id" aria-label="Pay from"><?= $payAccounts ?></select><select name="payment_method" aria-label="Method"><?= $methods ?></select><input name="reference" maxlength="120" placeholder="Reference" aria-label="Reference" style="max-width:110px"><button class="btn small primary" name="action" value="edit_pay">Pay</button><button class="btn small" name="action" value="edit_reopen">Reopen</button></form><?php endif; ?>
<?php if ($r['status'] === 'paid'): ?><form method="post" class="inline" onsubmit="return confirm('Undo this payment? A reversing journal will be posted.')"><?= $hidden ?><input type="hidden" name="payslip_id" value="<?= $sid ?>"><button class="btn small" name="action" value="edit_undo">Undo Payment</button></form><?php endif; ?>
<?php if ($r['status'] === 'draft'): ?><form method="post" style="display:inline"><?= $hidden ?><input type="hidden" name="payslip_id" value="<?= $sid ?>"><button class="btn small danger" name="action" value="delete">Delete</button></form><?php endif; ?></td></tr>
<?php endforeach; ?>
<?= $rows ? '' : '<tr><td colspan="10" class="empty">No payslips for this month yet. Press Generate Payslips.</td></tr>' ?>
</tbody></table></div>
<p class="footnote">A day&rsquo;s pay is the monthly gross over the days in the month. Absent days, half days, unpaid leave and days before joining or after leaving are taken off. A day with no attendance record counts as worked, so mark absences before generating.</p>
<?php mgmt_page_end(); ?>
<?php include "../../addon/conf/connection_close.php"; ?>
