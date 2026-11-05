<?php
/**
 * Shared code for the purchase screens (Requisition, Purchase Order, Purchase, Purchase Return).
 * The screens are configurations of inventory/inv_doc_engine.php; this file adds supplier payments, the payment
 * schedule, attached files and the due summary through the engine's request / panels / after_save hooks.
 * Tables: purchase/schema.sql.
 */
require_once dirname(__DIR__) . '/inventory/inv_doc_engine.php';

const PUR_UPLOAD_DIR = __DIR__ . '/uploads';
const PUR_UPLOAD_TYPES = ['pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'csv' => 'text/csv',
    'xls' => 'application/vnd.ms-excel', 'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'doc' => 'application/msword', 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
const PUR_METHODS = ['cash' => 'Cash', 'bank' => 'Bank', 'mobile' => 'Mobile Banking', 'cheque' => 'Cheque', 'other' => 'Other'];

const PUR_SUPPLIER_HEADER = [['mobile', 'Mobile No', 'mobile'], ['bill_type', 'Bill Type', 'select'], ['party_group', 'Supplier Group', 'text'], ['party_name', 'Supplier Name', 'party'],
    ['address', 'Address', 'text']];

function pur_ready(mysqli $db)
{
    return sales_tables_ready($db, ['purchase_payments', 'purchase_schedules', 'purchase_attachments', 'purchase_due']);
}

/** Due summary of a purchase: net_amount, return_amount, paid_amount, due_amount. */
function pur_due(mysqli $db, $docId)
{
    return sales_row($db, 'SELECT * FROM purchase_due WHERE doc_id=?', 'i', [(int) $docId])
        ?? ['net_amount' => 0, 'return_amount' => 0, 'paid_amount' => 0, 'due_amount' => 0];
}

/**
 * Engine options for a purchase screen. $features: payments (Payment + Schedule + Cash Amount), attachments.
 * Merge into the inv_doc_page config.
 */
function pur_hooks(array $features)
{
    $pay = in_array('payments', $features, true);
    $attach = in_array('attachments', $features, true);
    $jump = [];
    if ($attach) {
        $jump[] = ['pur-attach', 'Attached'];
    }
    if ($pay) {
        $jump[] = ['pur-schedule', 'Schedule'];
        $jump[] = ['pur-payment', 'Payment'];
    }
    $cfg = [
        'jump' => $jump,
        'request' => fn(mysqli $db, array $cfg, $self) => pur_request($db, $cfg, $self, $features),
        'panels' => fn(mysqli $db, array $cfg, $self, $doc, $readOnly) => pur_panels($db, $cfg, $self, $doc, $features),
    ];
    if ($pay) {
        $cfg += ['cash' => true, 'cash_label' => 'Cash Amount', 'cash_change' => false, 'after_save' => 'pur_after_save', 'on_cancel' => 'pur_on_cancel'];
    }
    return $cfg;
}

/** The Cash Amount on the purchase is kept as its one "initial" payment row, and payments may not exceed what is owed. */
function pur_after_save(mysqli $db, $docId, array $doc, array $lines)
{
    if (!pur_ready($db)) {
        if ((float) $doc['cash_received'] > 0) {
            throw new RuntimeException('Purchase payment tables are not installed. Import billing/purchase/schema.sql, or save without a Cash Amount.');
        }
        return;
    }
    sales_exec($db, 'DELETE FROM purchase_payments WHERE doc_id=? AND is_initial=1', 'i', [(int) $docId])->close();
    if ((float) $doc['cash_received'] > 0) {
        sales_insert($db, "INSERT INTO purchase_payments (doc_id, paid_on, payment_method, amount, reference, is_initial, created_by) VALUES (?, ?, 'cash', ?, 'Cash Amount on purchase', 1, ?)",
            'isdi', [(int) $docId, $doc['doc_date'], (float) $doc['cash_received'], (int) ($GLOBALS['user_id'] ?? 0)]);
    }
    $due = pur_due($db, $docId);
    if ((float) $due['due_amount'] < -0.004) {
        throw new RuntimeException('Payments and returns (' . sales_money((float) $due['paid_amount'] + (float) $due['return_amount']) . ') would be more than the net amount ' . sales_money($due['net_amount']) . '. Lower the Cash Amount or delete a payment first.');
    }
}

function pur_on_cancel(mysqli $db, array $doc)
{
    if (!pur_ready($db)) {
        return;
    }
    $paid = sales_row($db, 'SELECT COUNT(*) AS n FROM purchase_payments WHERE doc_id=? AND is_initial=0', 'i', [(int) $doc['doc_id']]);
    if ((int) $paid['n'] > 0) {
        throw new RuntimeException($doc['doc_no'] . ' has payments. Delete them in the Payment section before cancelling.');
    }
    sales_exec($db, 'DELETE FROM purchase_payments WHERE doc_id=?', 'i', [(int) $doc['doc_id']])->close();
}

/** Payment, schedule and attachment actions (POST with "panel"), and attachment downloads (GET "attachment"). */
function pur_request(mysqli $db, array $cfg, $self, array $features)
{
    if (isset($_GET['attachment']) && in_array('attachments', $features, true) && pur_ready($db)) {
        $file = sales_row($db, 'SELECT a.* FROM purchase_attachments a JOIN inv_docs d ON d.doc_id=a.doc_id WHERE a.attachment_id=? AND d.doc_type=?', 'is', [(int) $_GET['attachment'], $cfg['type']]);
        $path = $file ? PUR_UPLOAD_DIR . '/' . basename($file['stored_name']) : '';
        if (!$file || !is_file($path)) {
            http_response_code(404);
            exit('File not found.');
        }
        $inline = in_array($file['mime_type'], ['application/pdf', 'image/jpeg', 'image/png'], true);
        header('Content-Type: ' . $file['mime_type']);
        header('Content-Length: ' . filesize($path));
        header('X-Content-Type-Options: nosniff');
        header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . str_replace(['"', "\r", "\n"], '', $file['original_name']) . '"');
        readfile($path);
        exit;
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['panel'])) {
        return;
    }
    $docId = (int) ($_POST['doc_id'] ?? 0);
    $panel = (string) $_POST['panel'];
    $back = $self . '?id=' . $docId . '#pur-' . preg_replace('/[^a-z]/', '', $panel);
    sales_require_csrf($back);
    $userId = (int) ($GLOBALS['user_id'] ?? 0);
    try {
        if (!pur_ready($db)) {
            throw new RuntimeException('Import billing/purchase/schema.sql first.');
        }
        $db->begin_transaction();
        $doc = sales_row($db, 'SELECT * FROM inv_docs WHERE doc_id=? AND doc_type=? FOR UPDATE', 'is', [$docId, $cfg['type']]);
        if (!$doc || $doc['status'] !== 'active') {
            throw new RuntimeException('Open a saved, active document first.');
        }
        $pay = in_array('payments', $features, true);
        $message = '';
        switch ($panel . ':' . ($_POST['op'] ?? '')) {
            case 'payment:add':
                if (!$pay) {
                    break;
                }
                $date = sales_valid_date($_POST['paid_on'] ?? '');
                $amount = sales_amount($_POST['amount'] ?? 0);
                $method = array_key_exists($_POST['payment_method'] ?? '', PUR_METHODS) ? $_POST['payment_method'] : 'cash';
                if ($date === null || $amount <= 0) {
                    throw new RuntimeException('Enter the payment date and an amount above zero.');
                }
                inv_assert_unlocked($db, $date, (int) $doc['branch_id']);
                $due = pur_due($db, $docId);
                if ($amount > (float) $due['due_amount'] + 0.004) {
                    throw new RuntimeException('Only ' . sales_money(max(0, $due['due_amount'])) . ' is due on ' . $doc['doc_no'] . '.');
                }
                sales_insert($db, 'INSERT INTO purchase_payments (doc_id, paid_on, payment_method, amount, reference, note, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)',
                    'issdssi', [$docId, $date, $method, $amount, sales_post_string('reference', 120), sales_post_string('note', 255), $userId]);
                $message = 'Payment of ' . sales_money($amount) . ' saved.';
                break;
            case 'payment:delete':
                $row = sales_row($db, 'SELECT * FROM purchase_payments WHERE payment_id=? AND doc_id=?', 'ii', [(int) ($_POST['id'] ?? 0), $docId]);
                if (!$row || (int) $row['is_initial'] === 1) {
                    throw new RuntimeException('Payment not found. Change the Cash Amount on the purchase to change the initial payment.');
                }
                inv_assert_unlocked($db, $row['paid_on'], (int) $doc['branch_id']);
                sales_exec($db, 'DELETE FROM purchase_payments WHERE payment_id=?', 'i', [(int) $row['payment_id']])->close();
                $message = 'Payment deleted.';
                break;
            case 'schedule:add':
                if (!$pay) {
                    break;
                }
                $date = sales_valid_date($_POST['due_date'] ?? '');
                $amount = sales_amount($_POST['amount'] ?? 0);
                if ($date === null || $amount <= 0) {
                    throw new RuntimeException('Enter the due date and an amount above zero.');
                }
                $scheduled = (float) sales_row($db, 'SELECT COALESCE(SUM(amount), 0) AS s FROM purchase_schedules WHERE doc_id=?', 'i', [$docId])['s'];
                $due = pur_due($db, $docId);
                if ($scheduled + $amount > (float) $due['due_amount'] + 0.004) {
                    throw new RuntimeException('The schedule would total more than the ' . sales_money(max(0, $due['due_amount'])) . ' still due.');
                }
                sales_insert($db, 'INSERT INTO purchase_schedules (doc_id, due_date, amount, note, created_by) VALUES (?, ?, ?, ?, ?)', 'isdsi', [$docId, $date, $amount, sales_post_string('note', 190), $userId]);
                $message = 'Schedule added.';
                break;
            case 'schedule:delete':
                sales_exec($db, 'DELETE FROM purchase_schedules WHERE schedule_id=? AND doc_id=?', 'ii', [(int) ($_POST['id'] ?? 0), $docId])->close();
                $message = 'Schedule removed.';
                break;
            case 'attach:add':
                $up = $_FILES['document'] ?? null;
                if (!$up || $up['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($up['tmp_name'])) {
                    throw new RuntimeException('Choose a file to attach.');
                }
                if ($up['size'] > 5242880) {
                    throw new RuntimeException('Files can be at most 5 MB.');
                }
                $ext = strtolower(pathinfo((string) $up['name'], PATHINFO_EXTENSION));
                if (!isset(PUR_UPLOAD_TYPES[$ext])) {
                    throw new RuntimeException('Attach a PDF, image, CSV, Excel or Word file.');
                }
                if (!is_dir(PUR_UPLOAD_DIR) && !mkdir(PUR_UPLOAD_DIR, 0775, true)) {
                    throw new RuntimeException('The uploads folder could not be created.');
                }
                $stored = bin2hex(random_bytes(16)) . '.' . $ext;
                if (!move_uploaded_file($up['tmp_name'], PUR_UPLOAD_DIR . '/' . $stored)) {
                    throw new RuntimeException('Could not save the file. Check that billing/purchase/uploads is writable.');
                }
                sales_insert($db, 'INSERT INTO purchase_attachments (doc_id, original_name, stored_name, mime_type, file_size, uploaded_by) VALUES (?, ?, ?, ?, ?, ?)',
                    'isssii', [$docId, sales_cut(basename((string) $up['name']), 190), $stored, PUR_UPLOAD_TYPES[$ext], (int) $up['size'], $userId]);
                $message = 'File attached.';
                break;
            case 'attach:delete':
                $file = sales_row($db, 'SELECT * FROM purchase_attachments WHERE attachment_id=? AND doc_id=?', 'ii', [(int) ($_POST['id'] ?? 0), $docId]);
                if ($file) {
                    sales_exec($db, 'DELETE FROM purchase_attachments WHERE attachment_id=?', 'i', [(int) $file['attachment_id']])->close();
                    @unlink(PUR_UPLOAD_DIR . '/' . basename($file['stored_name']));
                }
                $message = 'File removed.';
                break;
        }
        if ($message === '') {
            throw new RuntimeException('Unknown action.');
        }
        $db->commit();
        sales_flash_redirect($back, 'success', $message);
    } catch (Throwable $e) {
        try { $db->rollback(); } catch (Throwable $ignored) {}
        sales_flash_redirect($back, 'error', $e->getMessage());
    }
}

/** Small POST form for one panel action. */
function pur_form($panel, $op, $docId, $inner, $attrs = '')
{
    return '<form method="post" ' . $attrs . '>' . sales_csrf_field() . '<input type="hidden" name="panel" value="' . $panel . '"><input type="hidden" name="op" value="' . $op . '"><input type="hidden" name="doc_id" value="' . (int) $docId . '">' . $inner . '</form>';
}

function pur_panels(mysqli $db, array $cfg, $self, $doc, array $features)
{
    if (!$doc) {
        return;
    }
    if (!pur_ready($db)) {
        echo '<div class="errorbox" style="margin-top:10px">Payments, schedule and attachments need <code>billing/purchase/schema.sql</code>. Import it and reload.</div>';
        return;
    }
    $id = (int) $doc['doc_id'];
    $active = $doc['status'] === 'active';
    $del = fn($panel, $rowId, $what) => $active ? pur_form($panel, 'delete', $id, '<input type="hidden" name="id" value="' . (int) $rowId . '"><button class="btn small danger">Delete</button>', 'onsubmit="return confirm(\'Delete this ' . $what . '?\')"') : '';

    if (in_array('payments', $features, true)) {
        $due = pur_due($db, $id);
        echo '<div class="summary" style="margin-top:12px"><div>Net Payable<b>' . sales_money($due['net_amount']) . '</b></div><div>Returned<b>' . sales_money($due['return_amount']) . '</b></div>'
            . '<div>Paid<b>' . sales_money($due['paid_amount']) . '</b></div><div>Due<b class="qty-info">' . sales_money($due['due_amount']) . '</b></div></div>';

        $pays = sales_rows($db, 'SELECT * FROM purchase_payments WHERE doc_id=? ORDER BY paid_on, payment_id', 'i', [$id]);
        echo '<section class="section" id="pur-payment"><div class="section-title">Payment to Supplier</div>';
        if ($active && (float) $due['due_amount'] > 0.004) {
            echo pur_form('payment', 'add', $id, '<div class="hgrid"><div class="field s2"><label>Date</label><input type="date" name="paid_on" value="' . date('Y-m-d') . '" required></div>'
                    . '<div class="field s2"><label>Method</label><select name="payment_method">' . implode('', array_map(fn($k, $v) => '<option value="' . $k . '">' . $v . '</option>', array_keys(PUR_METHODS), PUR_METHODS)) . '</select></div>'
                    . '<div class="field s2"><label>Amount</label><input type="number" name="amount" min="0.01" step="0.01" max="' . sales_h(round((float) $due['due_amount'], 2)) . '" value="' . sales_h(round((float) $due['due_amount'], 2)) . '" required></div>'
                    . '<div class="field s2"><label>Reference / Cheque No</label><input name="reference" maxlength="120"></div><div class="field s2"><label>Note</label><input name="note" maxlength="255"></div>'
                    . '<div class="field s2"><label>&nbsp;</label><button class="btn primary">Cash Payment</button></div></div>');
        }
        echo '<div class="table-scroll" style="max-height:240px"><table><thead><tr><th>Date</th><th>Method</th><th>Reference</th><th>Note</th><th class="num">Amount</th><th></th></tr></thead><tbody>';
        if (!$pays) {
            echo '<tr><td colspan="6" class="empty">No payments yet.</td></tr>';
        }
        foreach ($pays as $p) {
            echo '<tr><td>' . inv_fmt_date($p['paid_on']) . '</td><td>' . sales_h(PUR_METHODS[$p['payment_method']] ?? $p['payment_method']) . '</td><td>' . sales_h($p['reference']) . '</td><td>' . sales_h($p['note']) . '</td>'
                . '<td class="num">' . sales_money($p['amount']) . '</td><td>' . ((int) $p['is_initial'] ? '<span class="muted">from Cash Amount</span>' : $del('payment', $p['payment_id'], 'payment')) . '</td></tr>';
        }
        echo '</tbody></table></div><p class="footnote">These are payments recorded on the purchase. Post the matching Payment Voucher in Accounts to keep the ledgers in step.</p></section>';

        $sched = sales_rows($db, 'SELECT * FROM purchase_schedules WHERE doc_id=? ORDER BY due_date, schedule_id', 'i', [$id]);
        echo '<section class="section" id="pur-schedule"><div class="section-title">Payment Schedule</div>';
        if ($active) {
            echo pur_form('schedule', 'add', $id, '<div class="hgrid"><div class="field s2"><label>Due Date</label><input type="date" name="due_date" required></div>'
                    . '<div class="field s2"><label>Amount</label><input type="number" name="amount" min="0.01" step="0.01" required></div><div class="field s4"><label>Note</label><input name="note" maxlength="190"></div>'
                    . '<div class="field s2"><label>&nbsp;</label><button class="btn">Add Schedule</button></div></div>');
        }
        echo '<table><thead><tr><th>Due Date</th><th>Note</th><th class="num">Amount</th><th></th></tr></thead><tbody>';
        if (!$sched) {
            echo '<tr><td colspan="4" class="empty">No schedule.</td></tr>';
        }
        foreach ($sched as $s) {
            $late = $s['due_date'] < date('Y-m-d') && (float) $due['due_amount'] > 0.004;
            echo '<tr><td' . ($late ? ' class="qty-info"' : '') . '>' . inv_fmt_date($s['due_date']) . '</td><td>' . sales_h($s['note']) . '</td><td class="num">' . sales_money($s['amount']) . '</td><td>' . $del('schedule', $s['schedule_id'], 'schedule line') . '</td></tr>';
        }
        echo '</tbody></table></section>';
    }

    if (in_array('attachments', $features, true)) {
        $files = sales_rows($db, 'SELECT * FROM purchase_attachments WHERE doc_id=? ORDER BY attachment_id', 'i', [$id]);
        echo '<section class="section" id="pur-attach"><div class="section-title">Attached Documents</div>';
        if ($active) {
            echo pur_form('attach', 'add', $id, '<input class="input" type="file" name="document" required accept=".' . implode(',.', array_keys(PUR_UPLOAD_TYPES)) . '"><button class="btn primary">Attach</button>', 'enctype="multipart/form-data" class="list-search"');
        }
        echo '<table><tbody>';
        if (!$files) {
            echo '<tr><td class="empty">No documents.</td></tr>';
        }
        foreach ($files as $a) {
            echo '<tr><td><a target="_blank" href="' . $self . '?attachment=' . (int) $a['attachment_id'] . '">' . sales_h($a['original_name']) . '</a></td><td class="num">' . number_format($a['file_size'] / 1024, 1) . ' KB</td><td>' . sales_h(inv_fmt_date($a['created_at'])) . '</td><td>' . $del('attach', $a['attachment_id'], 'file') . '</td></tr>';
        }
        echo '</tbody></table></section>';
    }
}
