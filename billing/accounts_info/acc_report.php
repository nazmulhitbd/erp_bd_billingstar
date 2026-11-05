<?php
/**
 * Accounts reports opened from the Accounts Register. Every report counts posted vouchers only.
 * Query: r (report number), all / from / to, basis (voucher|cheque), slg (sub ledger group), party (a:ID account or s:ID sub ledger), auto (print).
 */
require_once __DIR__ . '/acc_common.php';
if (!acc_ready($conn)) {
    exit('Accounts tables are not installed. Import billing/accounts_info/schema.sql.');
}

$r = (int) ($_GET['r'] ?? 0);
if (!isset(ACC_REPORTS[$r])) {
    http_response_code(404);
    exit('Unknown report.');
}
[$from, $to, $basis, $all] = acc_period_from_request($_GET);
$slg = (int) ($_GET['slg'] ?? 0);
[$partyKind, $partyId] = array_pad(explode(':', (string) ($_GET['party'] ?? '')), 2, '0');
$partyId = (int) $partyId;
$d = acc_date_expr($basis);
$ld = acc_line_date($basis);

$accounts = [];
foreach (acc_accounts($conn, false) as $a) {
    $accounts[(int) $a['account_id']] = $a;
}
$m = fn($v) => sales_money($v);
$num = fn($v) => '<td class="num">' . sales_money($v) . '</td>';
$e = fn($v) => sales_h($v);

/* ---------- Shared pieces ---------- */

function rep_vouchers(mysqli $db, $where, $types, array $params, $d)
{
    return sales_rows($db, "SELECT v.*, $d AS d FROM acc_vouchers v WHERE v.status='posted' AND $where ORDER BY d, v.voucher_id", $types, $params);
}

/** All lines of the given vouchers: [voucher_id => [line, ...]] with display names. */
function rep_lines(mysqli $db, array $voucherIds)
{
    $voucherIds = array_values(array_unique(array_map('intval', $voucherIds)));
    if (!$voucherIds) {
        return [];
    }
    $out = [];
    foreach (array_chunk($voucherIds, 500) as $chunk) {
        $rows = sales_rows(
            $db,
            'SELECT l.*, a.account_name, a.account_code, a.account_type, s.sub_ledger_name FROM acc_voucher_lines l JOIN acc_accounts a ON a.account_id=l.account_id LEFT JOIN acc_sub_ledgers s ON s.sub_ledger_id=l.sub_ledger_id WHERE l.voucher_id IN (' . implode(',', array_fill(0, count($chunk), '?')) . ') ORDER BY l.voucher_id, l.line_no',
            str_repeat('i', count($chunk)),
            $chunk
        );
        foreach ($rows as $row) {
            $row['name'] = $row['sub_ledger_name'] ? $row['sub_ledger_name'] : $row['account_name'];
            $out[(int) $row['voucher_id']][] = $row;
        }
    }
    return $out;
}

function rep_mode($v)
{
    return match ($v['voucher_type']) {
        'debit' => 'Paid By ' . ($v['transaction_mode'] === 'Cheque' ? 'Cheque/Honour' : $v['transaction_mode']),
        'credit' => 'Received By ' . ($v['transaction_mode'] === 'Cheque' ? 'Cheque/Honour' : $v['transaction_mode']),
        'contra' => 'Contra (' . $v['transaction_mode'] . ')',
        default => 'Journal',
    };
}

/** Tree rows for groups of the given natures, with each account's amount; returns [html rows, total]. */
function rep_tree(array $tree, array $accounts, array $amounts, array $natures, $showZero = false)
{
    $groupTotal = [];
    foreach ($accounts as $id => $a) {
        if (!in_array($a['nature'], $natures, true)) {
            continue;
        }
        for ($g = (int) $a['group_id']; $g > 0; $g = (int) ($tree[$g]['parent_id'] ?? 0)) {
            $groupTotal[$g] = ($groupTotal[$g] ?? 0) + ($amounts[$id] ?? 0);
        }
    }
    $html = '';
    $total = 0.0;
    foreach ($tree as $gid => $g) {
        if (!in_array($g['nature'], $natures, true) || (!$showZero && abs($groupTotal[$gid] ?? 0) < 0.005)) {
            continue;
        }
        if ((int) $g['depth'] === 0) {
            $total += $groupTotal[$gid] ?? 0;
        }
        $pad = 'padding-left:' . (6 + 18 * (int) $g['depth']) . 'px';
        $html .= '<tr class="grp"><td style="' . $pad . '">' . sales_h($g['group_name']) . '</td><td></td><td class="num">' . sales_money($groupTotal[$gid] ?? 0) . '</td></tr>';
        foreach ($accounts as $id => $a) {
            if ((int) $a['group_id'] === $gid && ($showZero || abs($amounts[$id] ?? 0) >= 0.005)) {
                $html .= '<tr><td style="padding-left:' . (24 + 18 * (int) $g['depth']) . 'px">' . sales_h($a['account_code'] . ' - ' . $a['account_name']) . '</td><td class="num">' . sales_money($amounts[$id] ?? 0) . '</td><td></td></tr>';
            }
        }
    }
    return [$html, $total];
}

/** Due rows for parties under control accounts, as of $to. */
function rep_dues(mysqli $db, array $accounts, $to, $basis, $slg, $partyKind, $partyId)
{
    $controls = [];
    if ($partyKind === 'a' && isset($accounts[$partyId]) && (int) $accounts[$partyId]['has_sub_ledger']) {
        $controls = [$partyId];
    } else {
        foreach ($accounts as $id => $a) {
            if ($a['account_type'] === 'receivable' && (int) $a['has_sub_ledger']) {
                $controls[] = $id;
            }
        }
    }
    if (!$controls) {
        return [];
    }
    $mov = acc_sub_ledger_movements($db, '1900-01-01', $to, $basis);
    $sql = 'SELECT s.*, g.group_name FROM acc_sub_ledgers s JOIN acc_sub_ledger_groups g ON g.slg_id=s.slg_id WHERE s.account_id IN (' . implode(',', array_map('intval', $controls)) . ')';
    $rows = $slg > 0 ? sales_rows($db, $sql . ' AND s.slg_id=? ORDER BY g.group_name, s.sub_ledger_name', 'i', [$slg]) : sales_rows($db, $sql . ' ORDER BY g.group_name, s.sub_ledger_name');
    $limits = [];
    if (sales_tables_ready($db, ['trader_flags'])) {
        foreach (sales_rows($db, 'SELECT trader_id, allow_credit, credit_limit FROM trader_flags') as $f) {
            $limits[(int) $f['trader_id']] = (int) $f['allow_credit'] ? (float) $f['credit_limit'] : 0.0;
        }
    }
    $permits = [];
    foreach (sales_rows($db, 'SELECT sub_ledger_id, SUM(permitted_amount) amt, MAX(valid_to) until FROM acc_overdue_permissions WHERE is_active=1 AND valid_from<=? AND valid_to>=? GROUP BY sub_ledger_id', 'ss', [$to, $to]) as $p) {
        $permits[(int) $p['sub_ledger_id']] = $p;
    }
    $out = [];
    foreach ($rows as $s) {
        $mv = $mov[(int) $s['sub_ledger_id']] ?? ['open' => 0, 'dr' => 0, 'cr' => 0, 'last_cr' => null];
        $nature = $accounts[(int) $s['account_id']]['nature'];
        $due = acc_natural($nature, $mv['open'] + $mv['dr'] - $mv['cr']);
        if ($due < 0.005) {
            continue;
        }
        $limit = $s['trader_id'] !== null ? ($limits[(int) $s['trader_id']] ?? 0.0) : 0.0;
        $permit = $permits[(int) $s['sub_ledger_id']] ?? null;
        $s += ['opening' => acc_natural($nature, acc_signed_opening($s['opening_balance'], $s['opening_side'])), 'dr' => $mv['dr'], 'cr' => $mv['cr'], 'due' => $due, 'last_cr' => $mv['last_cr'], 'limit' => $limit, 'permit' => $permit,
            'over' => $limit > 0 && $due > $limit + (float) ($permit['amt'] ?? 0) + 0.005];
        $out[] = $s;
    }
    return $out;
}

/* ---------- Build the report body ---------- */
[$caption] = ACC_REPORTS[$r];
$title = $caption;
$sub = '';
ob_start();

switch ($r) {
    case 1:
    case 3:
        $sl = null;
        if ($partyKind === 's') {
            $sl = sales_row($conn, 'SELECT * FROM acc_sub_ledgers WHERE sub_ledger_id=?', 'i', [$partyId]);
            $accountId = (int) ($sl['account_id'] ?? 0);
        } else {
            $accountId = $partyId;
        }
        $acc = $accounts[$accountId] ?? null;
        if (!$acc) {
            echo '<p class="warn">Pick a Name (account or party) in the Accounts Register.</p>';
            break;
        }
        if ($sl) {
            $title = ['payable' => 'Creditors Ledger', 'receivable' => 'Debtors Ledger'][$acc['account_type']] ?? 'Party Ledger';
            $title .= $r === 3 ? ' Details' : '';
            $mv = acc_sub_ledger_movements($conn, $from, $to, $basis, (int) $sl['sub_ledger_id'])[(int) $sl['sub_ledger_id']] ?? ['open' => 0];
            $sub = '<div class="party"><b>Accounts Name:</b> <span><b>' . $e($sl['sub_ledger_name']) . '</b><br>' . $e($sl['address']) . '<br>' . $e($sl['mobile']) . '</span></div>';
        } else {
            $mv = acc_account_movements($conn, $from, $to, $basis, $accountId)[$accountId] ?? ['open' => 0];
            $sub = '<div class="party"><b>Accounts Name:</b> <span><b>' . $e($acc['account_code'] . ' - ' . $acc['account_name']) . '</b><br>' . $e($acc['group_name']) . ($acc['bank_account_no'] ? '<br>' . $e($acc['bank_name'] . ' ' . $acc['bank_account_no']) : '') . '</span></div>';
        }
        $isPayable = $acc['account_type'] === 'payable';
        // Filter on the line's own indexed columns first, then fetch only the matching vouchers.
        $sql = "SELECT v.voucher_id, v.voucher_no, v.voucher_type, v.ref_no, v.narration, COALESCE(NULLIF(l.transaction_mode, ''), v.transaction_mode) AS transaction_mode, COALESCE(NULLIF(l.cheque_no, ''), v.cheque_no) AS cheque_no, COALESCE(l.cheque_date, v.cheque_date) AS cheque_date, $ld AS d, l.debit, l.credit, l.line_narration "
            . 'FROM acc_voucher_lines l JOIN acc_vouchers v ON v.voucher_id=l.voucher_id WHERE l.is_posted=1 AND l.account_id=? ' . ($sl ? 'AND l.sub_ledger_id=? ' : '')
            . "AND $ld BETWEEN ? AND ? ORDER BY d, l.voucher_id, l.line_no";
        $rows = $sl ? sales_rows($conn, $sql, 'iiss', [$accountId, (int) $sl['sub_ledger_id'], $from, $to]) : sales_rows($conn, $sql, 'iss', [$accountId, $from, $to]);
        $lines = rep_lines($conn, array_column($rows, 'voucher_id'));
        $balance = acc_natural($acc['nature'], $mv['open']);
        $tdr = $tcr = 0.0;
        echo '<table><thead><tr><th>Date</th><th>Type</th><th>Transaction Mode</th><th>A/C Name</th><th>Narration</th><th>Chq No.</th><th>Ref No.</th><th>Voucher No.</th><th class="num">' . ($isPayable ? 'Payment' : 'Debit') . '</th><th class="num">' . ($isPayable ? 'Received' : 'Credit') . '</th><th class="num">Balance</th></tr></thead><tbody>';
        echo '<tr><td>' . acc_fmt_date($all ? '' : $from) . '</td><td></td><td>Balance Forward</td><td colspan="5"></td>' . $num(0) . $num(0) . $num($balance) . '</tr>';
        foreach ($rows as $row) {
            $tdr += (float) $row['debit'];
            $tcr += (float) $row['credit'];
            $balance += acc_natural($acc['nature'], (float) $row['debit'] - (float) $row['credit']);
            $others = [];
            foreach ($lines[(int) $row['voucher_id']] ?? [] as $l) {
                $same = (int) $l['account_id'] === $accountId && (!$sl || (int) $l['sub_ledger_id'] === (int) $sl['sub_ledger_id']);
                if (!$same) {
                    $others[] = $l['name'];
                }
            }
            echo '<tr><td>' . acc_fmt_date($row['d']) . '</td><td>' . $e(ACC_VOUCHER_TYPES[$row['voucher_type']]['kind']) . '</td><td>' . $e(rep_mode($row)) . '</td><td>' . $e(implode(' / ', array_unique($others))) . '</td><td>' . $e($row['line_narration'] ?: $row['narration']) . '</td><td>' . $e($row['cheque_no']) . '</td><td>' . $e($row['ref_no']) . '</td><td>' . $e($row['voucher_no']) . '</td>' . $num($row['debit']) . $num($row['credit']) . $num($balance) . '</tr>';
            if ($r === 3) {
                echo '<tr class="detail"><td></td><td colspan="10"><table class="inner">';
                foreach ($lines[(int) $row['voucher_id']] ?? [] as $l) {
                    echo '<tr><td>' . $e($l['name']) . ($l['sub_ledger_name'] ? ' <span class="muted">(' . $e($l['account_name']) . ')</span>' : '') . '</td><td>' . $e($l['line_narration']) . '</td><td class="num">' . ((float) $l['debit'] > 0 ? 'Dr ' . $m($l['debit']) : '') . '</td><td class="num">' . ((float) $l['credit'] > 0 ? 'Cr ' . $m($l['credit']) : '') . '</td></tr>';
                }
                echo '</table></td></tr>';
            }
        }
        if (!$rows) {
            echo '<tr><td colspan="11" class="muted">No posted transactions in this period.</td></tr>';
        }
        echo '</tbody><tfoot><tr><td colspan="8" class="num">Grand Total :</td><td class="num dbl">' . $m($tdr) . '</td><td class="num dbl">' . $m($tcr) . '</td><td></td></tr>'
            . '<tr><td colspan="10" class="num"><b>Closing Balance :</b></td><td class="num dbl"><b>' . $m($balance) . '</b></td></tr></tfoot></table>';
        // Pending (unposted) vouchers for this account or party, like post-dated cheques.
        $psql = "SELECT v.voucher_no, v.created_at, v.narration, v.cheque_date, v.cheque_no, l.debit + l.credit AS amount FROM acc_voucher_lines l JOIN acc_vouchers v ON v.voucher_id=l.voucher_id WHERE v.status='draft' AND l.account_id=? " . ($sl ? 'AND l.sub_ledger_id=? ' : '') . 'ORDER BY v.voucher_date';
        $pending = $sl ? sales_rows($conn, $psql, 'ii', [$accountId, (int) $sl['sub_ledger_id']]) : sales_rows($conn, $psql, 'i', [$accountId]);
        echo '<h4 class="pending-title">Pending (not posted) vouchers</h4><table class="pending"><thead><tr><th>VoucherNo</th><th>Entry Date</th><th>Description</th><th>Cheque Date</th><th>Cheque No</th><th class="num">Amount</th></tr></thead><tbody>';
        foreach ($pending as $p) {
            echo '<tr><td>' . $e($p['voucher_no']) . '</td><td>' . acc_fmt_date($p['created_at']) . '</td><td>' . $e($p['narration']) . '</td><td>' . acc_fmt_date($p['cheque_date']) . '</td><td>' . $e($p['cheque_no']) . '</td>' . $num($p['amount']) . '</tr>';
        }
        echo $pending ? '' : '<tr><td colspan="6" class="muted">None.</td></tr>';
        echo '</tbody></table>';
        break;

    case 2:
    case 14:
        $dues = rep_dues($conn, $accounts, $to, $basis, $slg, $partyKind, $partyId);
        $sub = '<p class="center">Due as on ' . acc_fmt_date($all ? date('Y-m-d') : $to) . '</p>';
        echo '<table><thead><tr><th>SL</th><th>Code</th><th>Name</th>' . ($r === 2 ? '<th>Group</th>' : '') . '<th>Mobile</th><th class="num">Opening</th><th class="num">Debit</th><th class="num">Credit</th><th class="num">Due</th><th>Last Received</th><th class="num">Credit Limit</th><th>Over Due Permission</th></tr></thead><tbody>';
        $grand = 0.0;
        $groupSum = 0.0;
        $lastGroup = null;
        $cols = $r === 2 ? 12 : 11;
        foreach ($dues as $i => $s) {
            if ($r === 14 && $s['group_name'] !== $lastGroup) {
                if ($lastGroup !== null) {
                    echo '<tr class="grp"><td colspan="' . ($cols - 4) . '" class="num">' . $e($lastGroup) . ' total</td>' . $num($groupSum) . '<td colspan="3"></td></tr>';
                }
                echo '<tr class="grp"><td colspan="' . $cols . '">' . $e($s['group_name']) . '</td></tr>';
                $lastGroup = $s['group_name'];
                $groupSum = 0.0;
            }
            $grand += $s['due'];
            $groupSum += $s['due'];
            echo '<tr' . ($s['over'] ? ' class="over"' : '') . '><td>' . ($i + 1) . '</td><td>' . $e($s['sub_ledger_code']) . '</td><td>' . $e($s['sub_ledger_name']) . '</td>' . ($r === 2 ? '<td>' . $e($s['group_name']) . '</td>' : '') . '<td>' . $e($s['mobile']) . '</td>'
                . $num($s['opening']) . $num($s['dr']) . $num($s['cr']) . '<td class="num"><b>' . $m($s['due']) . '</b></td><td>' . acc_fmt_date($s['last_cr']) . '</td><td class="num">' . ($s['limit'] > 0 ? $m($s['limit']) : '') . '</td>'
                . '<td>' . ($s['permit'] ? $m($s['permit']['amt']) . ' until ' . acc_fmt_date($s['permit']['until']) : '') . ($s['over'] ? ' <b>Over limit</b>' : '') . '</td></tr>';
        }
        if ($r === 14 && $lastGroup !== null) {
            echo '<tr class="grp"><td colspan="' . ($cols - 4) . '" class="num">' . $e($lastGroup) . ' total</td>' . $num($groupSum) . '<td colspan="3"></td></tr>';
        }
        echo $dues ? '' : '<tr><td colspan="' . $cols . '" class="muted">No dues.</td></tr>';
        echo '</tbody><tfoot><tr><td colspan="' . ($cols - 4) . '" class="num">Total Due</td><td class="num dbl">' . $m($grand) . '</td><td colspan="3"></td></tr></tfoot></table>';
        echo $r === 14 ? '<p class="muted">Branches are the Accounts Sub Ledger Groups.</p>' : '';
        break;

    case 4:
        $vouchers = rep_vouchers($conn, "$d BETWEEN ? AND ?", 'ss', [$from, $to], $d);
        $lines = rep_lines($conn, array_column($vouchers, 'voucher_id'));
        echo '<table><thead><tr><th>Date</th><th>Voucher No</th><th>Type</th><th>Particulars</th><th>Narration</th><th class="num">Debit</th><th class="num">Credit</th></tr></thead><tbody>';
        $tdr = $tcr = 0.0;
        foreach ($vouchers as $v) {
            $first = true;
            foreach ($lines[(int) $v['voucher_id']] ?? [] as $l) {
                $tdr += (float) $l['debit'];
                $tcr += (float) $l['credit'];
                echo '<tr' . ($first ? ' class="first"' : '') . '><td>' . ($first ? acc_fmt_date($v['d']) : '') . '</td><td>' . ($first ? $e($v['voucher_no']) : '') . '</td><td>' . ($first ? $e(ACC_VOUCHER_TYPES[$v['voucher_type']]['kind']) : '') . '</td><td>' . $e($l['name']) . '</td><td>' . $e($l['line_narration'] ?: ($first ? $v['narration'] : '')) . '</td><td class="num">' . ((float) $l['debit'] > 0 ? $m($l['debit']) : '') . '</td><td class="num">' . ((float) $l['credit'] > 0 ? $m($l['credit']) : '') . '</td></tr>';
                $first = false;
            }
        }
        echo $vouchers ? '' : '<tr><td colspan="7" class="muted">No posted vouchers in this period.</td></tr>';
        echo '</tbody><tfoot><tr><td colspan="5" class="num">Total</td><td class="num dbl">' . $m($tdr) . '</td><td class="num dbl">' . $m($tcr) . '</td></tr></tfoot></table>';
        break;

    case 5:
    case 6:
        $mov = acc_account_movements($conn, $from, $to, $basis);
        $types = $r === 5 ? ['bank'] : ['cash', 'bank'];
        echo '<table><thead><tr><th>Code</th><th>Account</th><th>Type</th><th>Bank A/C No</th><th class="num">Opening</th><th class="num">Received (Dr)</th><th class="num">Paid (Cr)</th><th class="num">Closing</th></tr></thead><tbody>';
        $t = ['open' => 0.0, 'dr' => 0.0, 'cr' => 0.0];
        foreach ($accounts as $id => $a) {
            if (!in_array($a['account_type'], $types, true)) {
                continue;
            }
            $mv = $mov[$id] ?? ['open' => 0, 'dr' => 0, 'cr' => 0];
            foreach ($t as $k => $v) {
                $t[$k] += $mv[$k];
            }
            echo '<tr><td>' . $e($a['account_code']) . '</td><td>' . $e($a['account_name']) . '</td><td>' . ucfirst($a['account_type']) . '</td><td>' . $e(trim($a['bank_name'] . ' ' . $a['bank_account_no'])) . '</td>' . $num($mv['open']) . $num($mv['dr']) . $num($mv['cr']) . $num($mv['open'] + $mv['dr'] - $mv['cr']) . '</tr>';
        }
        echo '</tbody><tfoot><tr><td colspan="4" class="num">Total</td>' . $num($t['open']) . $num($t['dr']) . $num($t['cr']) . '<td class="num dbl">' . $m($t['open'] + $t['dr'] - $t['cr']) . '</td></tr></tfoot></table>';
        break;

    case 7:
    case 8:
    case 9:
        $vt = [7 => 'credit', 8 => 'debit', 9 => 'journal'][$r];
        $vouchers = rep_vouchers($conn, "v.voucher_type=? AND $d BETWEEN ? AND ?", 'sss', [$vt, $from, $to], $d);
        $lines = rep_lines($conn, array_column($vouchers, 'voucher_id'));
        echo '<table><thead><tr><th>Date</th><th>Voucher No</th><th>Particulars</th><th>Narration</th><th>Mode</th><th>Chq No.</th><th class="num">Amount</th></tr></thead><tbody>';
        $total = $day = 0.0;
        $lastDate = null;
        foreach ($vouchers as $v) {
            if ($lastDate !== null && $v['d'] !== $lastDate) {
                echo '<tr class="grp"><td colspan="6" class="num">' . acc_fmt_date($lastDate) . ' total</td>' . $num($day) . '</tr>';
                $day = 0.0;
            }
            $lastDate = $v['d'];
            $parts = [];
            foreach ($lines[(int) $v['voucher_id']] ?? [] as $l) {
                $cash = in_array($l['account_type'], ['cash', 'bank'], true);
                if ($vt === 'journal') {
                    $parts[] = ((float) $l['debit'] > 0 ? 'Dr ' : 'Cr ') . $l['name'];
                } elseif (!$cash) {
                    $parts[] = $l['name'];
                }
            }
            $total += (float) $v['total_amount'];
            $day += (float) $v['total_amount'];
            echo '<tr><td>' . acc_fmt_date($v['d']) . '</td><td>' . $e($v['voucher_no']) . '</td><td>' . $e(implode(', ', $parts)) . '</td><td>' . $e($v['narration']) . '</td><td>' . $e($v['transaction_mode']) . '</td><td>' . $e($v['cheque_no']) . '</td>' . $num($v['total_amount']) . '</tr>';
        }
        if ($lastDate !== null) {
            echo '<tr class="grp"><td colspan="6" class="num">' . acc_fmt_date($lastDate) . ' total</td>' . $num($day) . '</tr>';
        }
        echo $vouchers ? '' : '<tr><td colspan="7" class="muted">No posted vouchers in this period.</td></tr>';
        echo '</tbody><tfoot><tr><td colspan="6" class="num">Grand Total</td><td class="num dbl">' . $m($total) . '</td></tr></tfoot></table>';
        break;

    case 10:
    case 22:
        $mov = acc_account_movements($conn, $from, $to, $basis);
        $amounts = [];
        foreach ($accounts as $id => $a) {
            $amounts[$id] = acc_natural($a['nature'], ($mov[$id]['dr'] ?? 0) - ($mov[$id]['cr'] ?? 0));
        }
        $income = $expense = 0.0;
        if ($r === 10) {
            foreach (['income' => 'Income', 'expense' => 'Expenses'] as $nature => $label) {
                echo '<h4>' . $label . '</h4><table><tbody>';
                $sum = 0.0;
                foreach ($accounts as $id => $a) {
                    if ($a['nature'] === $nature && abs($amounts[$id]) >= 0.005) {
                        $sum += $amounts[$id];
                        echo '<tr><td>' . $e($a['account_code'] . ' - ' . $a['account_name']) . '</td><td class="muted">' . $e($a['group_name']) . '</td>' . $num($amounts[$id]) . '</tr>';
                    }
                }
                echo '<tr class="grp"><td colspan="2">Total ' . $label . '</td><td class="num dbl">' . $m($sum) . '</td></tr></tbody></table>';
                if ($nature === 'income') {
                    $income = $sum;
                } else {
                    $expense = $sum;
                }
            }
        } else {
            $tree = acc_group_tree($conn);
            foreach (['income' => 'Income', 'expense' => 'Expenses'] as $nature => $label) {
                [$html, $sum] = rep_tree($tree, $accounts, $amounts, [$nature]);
                echo '<h4>' . $label . '</h4><table><thead><tr><th>Particulars</th><th class="num">Account</th><th class="num">Group Total</th></tr></thead><tbody>' . $html . '<tr class="grp"><td colspan="2">Total ' . $label . '</td><td class="num dbl">' . $m($sum) . '</td></tr></tbody></table>';
                if ($nature === 'income') {
                    $income = $sum;
                } else {
                    $expense = $sum;
                }
            }
        }
        $net = $income - $expense;
        echo '<table class="result"><tr><td><b>' . ($net >= 0 ? 'Net Profit' : 'Net Loss') . '</b></td><td class="num dbl"><b>' . $m(abs($net)) . '</b></td></tr></table>';
        break;

    case 11:
        $mov = acc_account_movements($conn, $from, $to, $basis);
        $tree = acc_group_tree($conn);
        $amounts = [];
        $pl = 0.0;
        foreach ($accounts as $id => $a) {
            $closing = ($mov[$id]['open'] ?? 0) + ($mov[$id]['dr'] ?? 0) - ($mov[$id]['cr'] ?? 0);
            $amounts[$id] = acc_natural($a['nature'], $closing);
            if (in_array($a['nature'], ['income', 'expense'], true)) {
                $pl += $a['nature'] === 'income' ? $amounts[$id] : -$amounts[$id];
            }
        }
        $sub = '<p class="center">As on ' . acc_fmt_date($all ? date('Y-m-d') : $to) . '</p>';
        [$assetHtml, $assets] = rep_tree($tree, $accounts, $amounts, ['asset']);
        [$liabHtml, $liabilities] = rep_tree($tree, $accounts, $amounts, ['liability', 'equity']);
        echo '<div class="two"><div><h4>Assets</h4><table><tbody>' . $assetHtml . '<tr class="grp"><td colspan="2">Total Assets</td><td class="num dbl">' . $m($assets) . '</td></tr></tbody></table></div>';
        echo '<div><h4>Liabilities &amp; Equity</h4><table><tbody>' . $liabHtml . '<tr class="grp"><td>Profit &amp; Loss Account</td><td></td><td class="num">' . $m($pl) . '</td></tr><tr class="grp"><td colspan="2">Total Liabilities &amp; Equity</td><td class="num dbl">' . $m($liabilities + $pl) . '</td></tr></tbody></table></div></div>';
        if (abs($assets - $liabilities - $pl) > 0.01) {
            echo '<p class="warn">Assets and Liabilities differ by ' . $m($assets - $liabilities - $pl) . '. Check the opening balances in General Accounts and Sub Ledgers.</p>';
        }
        break;

    case 12:
    case 13:
        $mov = acc_account_movements($conn, $from, $to, $basis);
        if ($r === 12) {
            $sub = '<p class="center">As on ' . acc_fmt_date($all ? date('Y-m-d') : $to) . '</p>';
            echo '<table><thead><tr><th>Code</th><th>Account</th><th>Group</th><th class="num">Debit</th><th class="num">Credit</th></tr></thead><tbody>';
        } else {
            echo '<table><thead><tr><th>Code</th><th>Account</th><th class="num">Opening Dr</th><th class="num">Opening Cr</th><th class="num">Period Debit</th><th class="num">Period Credit</th><th class="num">Closing Dr</th><th class="num">Closing Cr</th></tr></thead><tbody>';
        }
        $t = array_fill(0, 6, 0.0);
        $lastGroup = null;
        foreach ($accounts as $id => $a) {
            $mv = $mov[$id] ?? ['open' => 0, 'dr' => 0, 'cr' => 0];
            $closing = $mv['open'] + $mv['dr'] - $mv['cr'];
            if (abs($closing) < 0.005 && abs($mv['dr']) < 0.005 && abs($mv['cr']) < 0.005 && abs($mv['open']) < 0.005) {
                continue;
            }
            if ($r === 12) {
                $t[0] += max(0, $closing);
                $t[1] += max(0, -$closing);
                echo '<tr><td>' . $e($a['account_code']) . '</td><td>' . $e($a['account_name']) . '</td><td>' . $e($a['group_name']) . '</td><td class="num">' . ($closing > 0 ? $m($closing) : '') . '</td><td class="num">' . ($closing < 0 ? $m(-$closing) : '') . '</td></tr>';
            } else {
                if ($a['group_name'] !== $lastGroup) {
                    echo '<tr class="grp"><td colspan="8">' . $e($a['group_name']) . '</td></tr>';
                    $lastGroup = $a['group_name'];
                }
                $vals = [max(0, $mv['open']), max(0, -$mv['open']), $mv['dr'], $mv['cr'], max(0, $closing), max(0, -$closing)];
                foreach ($vals as $k => $v) {
                    $t[$k] += $v;
                }
                echo '<tr><td>' . $e($a['account_code']) . '</td><td>' . $e($a['account_name']) . '</td>' . implode('', array_map($num, $vals)) . '</tr>';
            }
        }
        echo $r === 12
            ? '</tbody><tfoot><tr><td colspan="3" class="num">Total</td><td class="num dbl">' . $m($t[0]) . '</td><td class="num dbl">' . $m($t[1]) . '</td></tr></tfoot></table>'
            : '</tbody><tfoot><tr><td colspan="2" class="num">Total</td>' . implode('', array_map(fn($v) => '<td class="num dbl">' . sales_money($v) . '</td>', $t)) . '</tr></tfoot></table>';
        $diff = $r === 12 ? $t[0] - $t[1] : $t[4] - $t[5];
        if (abs($diff) > 0.01) {
            echo '<p class="warn">Debit and credit differ by ' . $m($diff) . ' because opening balances do not balance.</p>';
        }
        break;

    case 15:
    case 16:
        if (!sales_tables_ready($conn, ['sales_invoice_payments', 'sales_invoices'])) {
            echo '<p class="warn">The sales module tables are not installed.</p>';
            break;
        }
        $pays = sales_rows($conn, "SELECT DATE(p.paid_at) AS d, p.payment_method, p.amount, p.reference, i.invoice_no, i.customer_name, i.mobile FROM sales_invoice_payments p JOIN sales_invoices i ON i.invoice_id=p.invoice_id WHERE i.status='posted' AND p.paid_at >= ? AND p.paid_at < DATE_ADD(?, INTERVAL 1 DAY) ORDER BY p.paid_at, p.payment_id", 'ss', [$from, $to]);
        $total = 0.0;
        if ($r === 15) {
            $byDay = [];
            foreach ($pays as $p) {
                $byDay[$p['d']][$p['payment_method']] = ($byDay[$p['d']][$p['payment_method']] ?? 0) + (float) $p['amount'];
            }
            $methods = ['cash' => 'Cash', 'bank' => 'Bank', 'mobile' => 'Mobile', 'other' => 'Other / Advance'];
            $colTotal = array_fill_keys(array_keys($methods), 0.0);
            echo '<table><thead><tr><th>Date</th>' . implode('', array_map(fn($l) => '<th class="num">' . $l . '</th>', $methods)) . '<th class="num">Total</th></tr></thead><tbody>';
            foreach ($byDay as $day => $vals) {
                $rowTotal = array_sum($vals);
                $total += $rowTotal;
                echo '<tr><td>' . acc_fmt_date($day) . '</td>';
                foreach ($methods as $k => $l) {
                    $colTotal[$k] += $vals[$k] ?? 0;
                    echo $num($vals[$k] ?? 0);
                }
                echo $num($rowTotal) . '</tr>';
            }
            echo $byDay ? '' : '<tr><td colspan="6" class="muted">No sales receipts in this period.</td></tr>';
            echo '</tbody><tfoot><tr><td class="num">Total</td>' . implode('', array_map($num, $colTotal)) . '<td class="num dbl">' . $m($total) . '</td></tr></tfoot></table>';
        } else {
            echo '<table><thead><tr><th>Date</th><th>Invoice</th><th>Customer</th><th>Mobile</th><th>Method</th><th>Reference</th><th class="num">Amount</th></tr></thead><tbody>';
            foreach ($pays as $p) {
                $total += (float) $p['amount'];
                echo '<tr><td>' . acc_fmt_date($p['d']) . '</td><td>' . $e($p['invoice_no']) . '</td><td>' . $e($p['customer_name']) . '</td><td>' . $e($p['mobile']) . '</td><td>' . ucfirst($e($p['payment_method'])) . '</td><td>' . $e($p['reference']) . '</td>' . $num($p['amount']) . '</tr>';
            }
            echo $pays ? '' : '<tr><td colspan="7" class="muted">No sales receipts in this period.</td></tr>';
            echo '</tbody><tfoot><tr><td colspan="6" class="num">Total</td><td class="num dbl">' . $m($total) . '</td></tr></tfoot></table>';
        }
        echo '<p class="muted">From payments recorded on sales invoices, POS sales and bills from orders.</p>';
        break;

    case 17:
        $tree = acc_group_tree($conn);
        $subs = [];
        foreach (sales_rows($conn, 'SELECT account_id, COUNT(*) n FROM acc_sub_ledgers GROUP BY account_id') as $s) {
            $subs[(int) $s['account_id']] = (int) $s['n'];
        }
        echo '<table><thead><tr><th>Code</th><th>Group / Account</th><th>Nature</th><th>Type</th><th class="num">Sub Ledgers</th><th class="num">Opening</th><th>Active</th></tr></thead><tbody>';
        foreach ($tree as $gid => $g) {
            echo '<tr class="grp"><td>' . $e($g['group_code']) . '</td><td style="padding-left:' . (6 + 18 * $g['depth']) . 'px">' . $e($g['group_name']) . '</td><td>' . $e(ACC_NATURES[$g['nature']]) . '</td><td colspan="3"></td><td>' . ((int) $g['is_active'] ? 'Yes' : 'No') . '</td></tr>';
            foreach ($accounts as $id => $a) {
                if ((int) $a['group_id'] === $gid) {
                    echo '<tr><td>' . $e($a['account_code']) . '</td><td style="padding-left:' . (24 + 18 * $g['depth']) . 'px">' . $e($a['account_name']) . '</td><td></td><td>' . $e(ACC_TYPES[$a['account_type']]) . '</td><td class="num">' . ($subs[$id] ?? '') . '</td><td class="num">' . $m($a['opening_balance']) . ' ' . $a['opening_side'] . '</td><td>' . ((int) $a['is_active'] ? 'Yes' : 'No') . '</td></tr>';
                }
            }
        }
        echo '</tbody></table>';
        break;

    case 18:
    case 19:
        if (!sales_tables_ready($conn, ['traders', 'trader_categories'])) {
            echo '<p class="warn">The Trader Info module is not installed (billing/trader/schema.sql).</p>';
            break;
        }
        $mov = acc_sub_ledger_movements($conn, '1900-01-01', $to, $basis);
        $links = [];
        foreach (sales_rows($conn, 'SELECT s.sub_ledger_id, s.trader_id, a.account_id FROM acc_sub_ledgers s JOIN acc_accounts a ON a.account_id=s.account_id WHERE s.trader_id IS NOT NULL') as $s) {
            $links[(int) $s['trader_id']] = $s;
        }
        $traders = sales_rows($conn, "SELECT t.*, c.category_name, c.kind, COALESCE(g.group_name, '') AS group_name FROM traders t JOIN trader_categories c ON c.category_id=t.category_id LEFT JOIN trader_groups g ON g.group_id=t.group_id" . ($r === 18 ? " WHERE c.kind='supplier'" : '') . ' ORDER BY c.sort_order, t.trader_name');
        echo '<table><thead><tr><th>SL</th><th>Code</th><th>Name</th><th>Category</th><th>Group</th><th>Mobile</th><th>Contact</th><th>Address</th><th class="num">Ledger Balance</th></tr></thead><tbody>';
        foreach ($traders as $i => $t) {
            $link = $links[(int) $t['trader_id']] ?? null;
            $bal = '';
            if ($link) {
                $mv = $mov[(int) $link['sub_ledger_id']] ?? ['open' => 0, 'dr' => 0, 'cr' => 0];
                $bal = acc_drcr($mv['open'] + $mv['dr'] - $mv['cr']);
            }
            echo '<tr><td>' . ($i + 1) . '</td><td>' . $e($t['trader_code']) . '</td><td>' . $e($t['trader_name']) . '</td><td>' . $e($t['category_name']) . '</td><td>' . $e($t['group_name']) . '</td><td>' . $e($t['mobile']) . '</td><td>' . $e($t['contact_person']) . '</td><td>' . $e($t['address']) . '</td><td class="num">' . ($bal !== '' ? $bal : '<span class="muted">No sub ledger</span>') . '</td></tr>';
        }
        echo $traders ? '' : '<tr><td colspan="9" class="muted">No traders.</td></tr>';
        echo '</tbody></table><p class="muted">Balances come from the linked Accounts Sub Ledger. Use "Import from Trader Info" on the Accounts Sub Ledger page to link traders.</p>';
        break;

    case 20:
    case 21:
        $nature = $r === 20 ? 'income' : 'expense';
        // Only the income (or expense) accounts, so each one is an index range on (is_posted, account_id, date).
        $ids = array_keys(array_filter($accounts, fn($a) => $a['nature'] === $nature));
        $rows = !$ids ? [] : sales_rows(
            $conn,
            "SELECT $ld AS d, v.voucher_no, v.narration, l.line_narration, l.account_id, l.debit, l.credit FROM acc_voucher_lines l JOIN acc_vouchers v ON v.voucher_id=l.voucher_id "
                . 'WHERE l.is_posted=1 AND l.account_id IN (' . implode(',', array_map('intval', $ids)) . ") AND $ld BETWEEN ? AND ? ORDER BY d, l.voucher_id, l.line_no",
            'ss',
            [$from, $to]
        );
        echo '<table><thead><tr><th>Date</th><th>Voucher No</th><th>Account</th><th>Narration</th><th class="num">Amount</th></tr></thead><tbody>';
        $total = $day = 0.0;
        $lastDate = null;
        $byAccount = [];
        foreach ($rows as $row) {
            if ($lastDate !== null && $row['d'] !== $lastDate) {
                echo '<tr class="grp"><td colspan="4" class="num">' . acc_fmt_date($lastDate) . ' total</td>' . $num($day) . '</tr>';
                $day = 0.0;
            }
            $lastDate = $row['d'];
            $amount = acc_natural($nature, (float) $row['debit'] - (float) $row['credit']);
            $total += $amount;
            $day += $amount;
            $byAccount[(int) $row['account_id']] = ($byAccount[(int) $row['account_id']] ?? 0) + $amount;
            echo '<tr><td>' . acc_fmt_date($row['d']) . '</td><td>' . $e($row['voucher_no']) . '</td><td>' . $e($accounts[(int) $row['account_id']]['account_name']) . '</td><td>' . $e($row['line_narration'] ?: $row['narration']) . '</td>' . $num($amount) . '</tr>';
        }
        if ($lastDate !== null) {
            echo '<tr class="grp"><td colspan="4" class="num">' . acc_fmt_date($lastDate) . ' total</td>' . $num($day) . '</tr>';
        }
        echo $rows ? '' : '<tr><td colspan="5" class="muted">Nothing in this period.</td></tr>';
        echo '</tbody><tfoot><tr><td colspan="4" class="num">Grand Total</td><td class="num dbl">' . $m($total) . '</td></tr></tfoot></table>';
        if ($byAccount) {
            echo '<h4>Summary by account</h4><table style="width:auto"><tbody>';
            foreach ($byAccount as $id => $amt) {
                echo '<tr><td>' . $e($accounts[$id]['account_name']) . '</td>' . $num($amt) . '</tr>';
            }
            echo '</tbody></table>';
        }
        break;
}
$body = ob_get_clean();

$period = $all ? 'All Dates' : acc_fmt_date($from) . ' To ' . acc_fmt_date($to);
if (in_array($r, [11, 12, 2, 14], true)) {
    $period = '';
}
?><!doctype html><html lang="en"><head><meta charset="utf-8"><title><?= $e($title) ?></title>
<style>
body{font:12px Arial,Helvetica,sans-serif;color:#111;margin:22px}
.head{display:flex;justify-content:space-between;align-items:flex-start;margin-left:150px}.co b{font-size:19px}.contact{font-size:12px;font-weight:bold;min-width:180px}
h2{text-align:center;margin:14px 0 4px;font-size:18px}.center,.period{text-align:center;font-weight:bold;margin:4px 0 12px}
.party{display:flex;gap:18px;margin:6px 0 10px}.party span{line-height:1.6}
table{width:100%;border-collapse:collapse;margin-bottom:10px}th,td{border:1px solid #bbb;padding:4px 6px;text-align:left;vertical-align:top}th{background:#f2f2f2}
.num{text-align:right;white-space:nowrap}tfoot td{border:0;font-weight:bold}.dbl{border-bottom:3px double #000!important}
tr.grp td{background:#f7f7f7;font-weight:bold}tr.over td{background:#fff0f0}tr.first td{border-top:2px solid #888}
table.inner{margin:0;width:100%}table.inner td{border:0;border-bottom:1px dotted #ccc;padding:2px 6px}tr.detail td{background:#fafafa}
.pending{width:80%;margin:30px auto 0}.pending th,.pending td{border:0;border-bottom:1px solid #000}.pending-title{width:80%;margin:30px auto 0}
.muted{color:#777}.warn{color:#a33;font-weight:bold}.two{display:grid;grid-template-columns:1fr 1fr;gap:16px}.result{width:50%;margin-left:auto}
.bar{margin-bottom:10px}@media print{.bar{display:none}body{margin:8mm}}
</style></head><body<?= !empty($_GET['auto']) ? ' onload="window.print()"' : '' ?>>
<div class="bar"><button onclick="window.print()">Print</button> <button onclick="window.close()">Close</button></div>
<div class="head"><div class="co"><b><?= $e(acc_setting($conn, 'company_name')) ?></b><br><?= $e(acc_setting($conn, 'company_address')) ?></div><div class="contact">Phone : <?= $e(acc_setting($conn, 'company_phone')) ?><br>Email : <?= $e(acc_setting($conn, 'company_email')) ?></div></div>
<h2><?= $e($title) ?></h2><?php if ($period !== ''): ?><div class="period"><?= $e($period) ?><?= $basis === 'cheque' ? ' (cheque date wise)' : '' ?></div><?php endif; ?>
<?= $sub ?>
<?= $body ?>
</body></html>
<?php include "../../addon/conf/connection_close.php";  ?>
