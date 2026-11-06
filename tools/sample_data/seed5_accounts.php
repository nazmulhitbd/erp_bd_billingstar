<?php
PHP_SAPI === 'cli' || exit; // a developer tool: never from the web
// Stage 5: accounts. Sub ledgers for the traders, then posted vouchers: opening capital, receipts, payments and a contra.
require __DIR__ . '/seed_lib.php';

sb_post('accounts_info/acc_sub_ledger.php', ['action' => 'import_traders'], 'sub ledgers from traders');
$slg = function ($name) {
    return (int) sb_val("SELECT sub_ledger_id FROM acc_sub_ledgers WHERE sub_ledger_name='" . sb_db()->real_escape_string($name) . "'");
};
$acc = fn($code) => (int) sb_val("SELECT account_id FROM acc_accounts WHERE account_code='$code'");

$journal = function ($type, $date, $narr, $mode, array $lines, $label) use ($acc) {
    $rows = [];
    foreach ($lines as [$code, $sub, $dr, $cr, $text]) {
        $rows[] = ['account_id' => $acc($code), 'sub_ledger_id' => $sub ?: '', 'debit' => $dr, 'credit' => $cr, 'line_narration' => $text];
    }
    sb_post("accounts_info/acc_voucher.php?type=$type", ['type' => $type, 'voucher_id' => 0, 'lines_json' => json_encode($rows), 'ref_no' => '', 'voucher_date' => $date, 'sales_person' => '',
        'narration' => $narr, 'transaction_mode' => $mode, 'cheque_no' => '', 'cheque_date' => '', 'action' => 'post'], $label);
};
$rp = function ($type, $date, $under, $narr, array $entries, $label) use ($acc) {
    $rows = [];
    foreach ($entries as [$code, $sub, $amount, $mode, $text]) {
        $rows[] = ['account_id' => $acc($code), 'sub_ledger_id' => $sub ?: '', 'amount' => $amount, 'line_narration' => $text, 'transaction_mode' => $mode, 'cheque_no' => '', 'cheque_date' => '', 'invoice_no' => ''];
    }
    sb_post("accounts_info/acc_rp_voucher.php?type=$type", ['type' => $type, 'ib' => '', 'voucher_id' => 0, 'entries_json' => json_encode($rows), 'ref_no' => '', 'voucher_date' => $date,
        'under_account' => $acc($under), 'sales_person' => '', 'narration' => $narr, 'action' => 'post'], $label);
};

$journal('journal', '2026-09-01', 'Owner\'s opening capital', 'Bank Transfer', [['1002', 0, 3000000, 0, 'Capital paid into the bank'], ['3001', 0, 0, 3000000, 'Opening capital']], 'journal opening capital');
$rp('credit', '2026-09-30', '1002', 'Part payment received', [['1101', $slg('Greenfield School'), 60000, 'Bank Transfer', 'Against SI2026000001']], 'receipt Greenfield');
$rp('credit', '2026-10-25', '1001', 'Dealer payment received', [['1101', $slg('City Gadget Point'), 40000, 'Cash', 'Against SI2026000003']], 'receipt City Gadget');
$rp('debit', '2026-09-28', '1002', 'Supplier payment', [['2001', $slg('Meridian Tech Supply'), 500000, 'Bank Transfer', 'Part of DESPUR2026000002']], 'payment Meridian');
$rp('debit', '2026-10-10', '1001', 'Shop rent for October', [['5101', 0, 35000, 'Cash', 'Rent, October 2026']], 'payment rent');
$journal('contra', '2026-10-01', 'Cash deposited to bank', 'Cash', [['1002', 0, 30000, 0, 'Deposit'], ['1001', 0, 0, 30000, 'Deposit']], 'contra cash to bank');
echo "done\n";
