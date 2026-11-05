<?php
/**
 * Serial History: every movement of a serial / IMEI, one row each (purchase, challan, transfer, sales, returns,
 * amendments): document, branch, dates, whether the serial is in stock now, party, model and store. Like finds
 * serials containing the text. Preview / Print / View open the document; Link jumps to where the serial went next
 * (Out) or came back (In). Clicking a serial shows its full timeline and warranty.
 */
require_once dirname(__DIR__) . '/inventory/inv_common.php';

$self = 'serial_history.php';
inv_require_ready($conn, 'Serial History', 'serial_history');
$q = sales_cut(trim((string) ($_GET['serial'] ?? '')), 120);
$like = !isset($_GET['serial']) || !empty($_GET['like']);

/**
 * Links of the document behind a stock movement: [view, preview, print] ('' when there is none).
 * Sales invoices and returns open on their own screens.
 */
function sh_links(mysqli $db, $docType, $docId)
{
    static $cache = [];
    $key = $docType . ':' . $docId;
    if (isset($cache[$key])) {
        return $cache[$key];
    }
    $view = $print = '';
    if ($docType === 'sales_invoice') {
        $inv = sales_row($db, 'SELECT sales_invoice_id FROM inv_docs WHERE doc_id=?', 'i', [(int) $docId]);
        if ($inv && $inv['sales_invoice_id']) {
            $view = '../sales/sales_invoice/sales_invoice.php?id=' . (int) $inv['sales_invoice_id'];
            $print = '../sales/sales_invoice/sales_invoice.php?print=' . (int) $inv['sales_invoice_id'];
        }
    } elseif ($docType === 'sales_return') {
        $ret = sales_tables_ready($db, ['sales_returns']) ? sales_row($db, 'SELECT return_id FROM sales_returns WHERE stock_doc_id=?', 'i', [(int) $docId]) : null;
        if ($ret) {
            $view = $print = '../sales/sales_retrun/sales_return.php?print=' . (int) $ret['return_id'];
        }
    } elseif (isset(INV_TYPES[$docType])) {
        $view = '../' . INV_TYPES[$docType]['url'] . '?id=' . (int) $docId;
        $print = '../' . INV_TYPES[$docType]['url'] . '?print=' . (int) $docId;
    }
    return $cache[$key] = [$view, $print, $print !== '' ? $print . '&auto=1' : ''];
}

/** Warranty end date from a warranty text like "1 Year", "6 Months", "90 Days" and the sale date; null when unknown. */
function sh_warranty_end($warranty, $soldOn)
{
    if (!preg_match('/(\d+)\s*(day|month|mon|year|yr)/i', (string) $warranty, $m) || !$soldOn) {
        return null;
    }
    $unit = strtolower($m[2]);
    $spec = 'P' . (int) $m[1] . (str_starts_with($unit, 'd') ? 'D' : (str_starts_with($unit, 'y') ? 'Y' : 'M'));
    return (new DateTime(substr($soldOn, 0, 10)))->add(new DateInterval($spec))->format('Y-m-d');
}

/** Invoices whose serial list holds this exact number (also sales made before stock tracking). */
function sh_invoices_with(mysqli $db, $serial)
{
    if (!sales_tables_ready($db, ['sales_invoice_items'])) {
        return [];
    }
    return sales_rows($db, "SELECT i.*, v.invoice_id, v.invoice_no, v.ref_no, v.sales_date, v.created_at AS entry_at, v.customer_name, v.mobile, v.status, v.branch_id, v.stock_doc_id
        FROM sales_invoice_items i JOIN sales_invoices v ON v.invoice_id=i.invoice_id
        WHERE CONCAT('\n', REPLACE(REPLACE(REPLACE(i.serial_imei, '\r', ''), ', ', '\n'), ',', '\n'), '\n') LIKE ? ORDER BY v.sales_date DESC, v.invoice_id DESC", 's', ['%' . "\n" . $serial . "\n" . '%']);
}

inv_page_start($conn, 'Serial History', 'serial_history');
echo '<fieldset class="box"><legend>Serial History</legend>';
echo '<form method="get" class="list-search" style="align-items:center"><label class="nowrap"><b>Serial</b> <input class="input" name="serial" value="' . sales_h($q) . '" autofocus style="width:240px"></label>'
    . '<label class="nowrap"><b>Like</b> <input class="check" type="checkbox" name="like" value="1"' . ($like ? ' checked' : '') . '></label><button class="btn primary">Search</button></form>';

/* ---------- One serial: full timeline and warranty (?view=serial_id) ---------- */
if (isset($_GET['view'])) {
    $s = sales_row($conn, 'SELECT s.*, p.product_code, p.product_group, p.brand, p.model_caption, p.description FROM inv_serials s JOIN sales_products p ON p.product_id=s.product_id WHERE s.serial_id=?', 'i', [(int) $_GET['view']]);
    if ($s) {
        $moves = sales_rows($conn, 'SELECT * FROM inv_serial_moves WHERE serial_id=? ORDER BY move_date, serial_move_id', 'i', [(int) $s['serial_id']]);
        echo '<section class="section"><div class="summary"><div>Serial / IMEI<b>' . sales_h($s['serial_no']) . '</b></div><div>Product<b>' . sales_h(trim($s['product_code'] . ' ' . ($s['model_caption'] ?: $s['description']))) . '</b></div>'
            . '<div>Group / Brand<b>' . sales_h(trim($s['product_group'] . ' / ' . $s['brand'], ' /')) . '</b></div><div>Status<b class="' . ((int) $s['in_stock'] ? '' : 'qty-info') . '">' . ((int) $s['in_stock'] ? 'In stock · ' . sales_h($s['store_name']) : 'Out of stock') . '</b></div>'
            . '<div>Movements<b>' . count($moves) . '</b></div></div><table style="margin-top:8px"><thead><tr><th>SL</th><th>Date</th><th>Transaction</th><th>Doc No</th><th>Party</th><th>Store</th><th>In / Out</th></tr></thead><tbody>';
        foreach ($moves as $i => $m) {
            [$view] = sh_links($conn, $m['doc_type'], (int) $m['doc_id']);
            echo '<tr><td>' . ($i + 1) . '</td><td>' . inv_fmt_date($m['move_date']) . '</td><td>' . sales_h($m['transaction_label'] ?: (INV_TYPES[$m['doc_type']]['title'] ?? $m['doc_type'])) . '</td><td>' . ($view ? '<a href="' . sales_h($view) . '">' . sales_h($m['doc_no']) . '</a>' : sales_h($m['doc_no'])) . '</td>'
                . '<td>' . sales_h($m['party_name']) . '</td><td>' . sales_h($m['store_name']) . '</td><td>' . ($m['direction'] === 'in' ? '<span class="badge converted">In</span>' : '<span class="badge cancelled">Out</span>') . '</td></tr>';
        }
        echo '</tbody></table>';
        $sold = sh_invoices_with($conn, $s['serial_no']);
        if ($sold) {
            echo '<div class="section-title" style="margin-top:10px">Warranty</div><table><thead><tr><th>Sold On</th><th>Invoice No</th><th>Customer</th><th>Mobile</th><th>Warranty</th><th>Warranty Until</th><th>Status</th></tr></thead><tbody>';
            foreach ($sold as $r) {
                $end = sh_warranty_end($r['warranty'], $r['sales_date']);
                $state = $r['status'] !== 'posted' ? '<span class="badge cancelled">Invoice void</span>' : ($end === null ? '' : ($end >= date('Y-m-d') ? '<span class="badge converted">In warranty</span>' : '<span class="badge cancelled">Expired</span>'));
                echo '<tr><td>' . inv_fmt_date($r['sales_date']) . '</td><td><a href="../sales/sales_invoice/sales_invoice.php?id=' . (int) $r['invoice_id'] . '">' . sales_h($r['invoice_no']) . '</a></td><td>' . sales_h($r['customer_name']) . '</td><td>' . sales_h($r['mobile']) . '</td>'
                    . '<td>' . sales_h($r['warranty']) . '</td><td>' . ($end ? inv_fmt_date($end) : '') . '</td><td>' . $state . '</td></tr>';
            }
            echo '</tbody></table>';
        }
        echo '</section>';
    }
}

/* ---------- Search grid ---------- */
if ($q !== '') {
    $moves = sales_rows(
        $conn,
        'SELECT m.*, s.serial_id, s.serial_no, s.in_stock, p.model_caption, p.description FROM inv_serial_moves m JOIN inv_serials s ON s.serial_id=m.serial_id JOIN sales_products p ON p.product_id=s.product_id '
            . 'WHERE ' . ($like ? 's.serial_no LIKE ?' : 's.serial_no=?') . ' ORDER BY s.serial_no, m.move_date, m.serial_move_id LIMIT 2000',
        's',
        [$like ? '%' . $q . '%' : $q]
    );
    // Next movement of the same serial after each row, for the Out / In link.
    $bySerial = [];
    foreach ($moves as $m) {
        $bySerial[(int) $m['serial_id']][] = $m;
    }
    $rows = [];
    foreach ($bySerial as $list) {
        foreach ($list as $i => $m) {
            $next = null;
            for ($j = $i + 1; $j < count($list); $j++) {
                if ($list[$j]['direction'] !== $m['direction']) {
                    $next = $list[$j];
                    break;
                }
            }
            $rows[] = $m + ['next' => $next];
        }
    }
    // Sales of an exact serial that never went through stock (made before stock tracking).
    $legacy = $like ? [] : array_filter(sh_invoices_with($conn, $q), fn($r) => empty($r['stock_doc_id']));

    echo '<div class="table-scroll" style="max-height:640px;margin-top:8px"><table><thead><tr><th>SL</th><th>Invoice No</th><th>Ref No</th><th>Branch Name</th><th>Serial</th><th>Date</th><th>Entry Date</th><th>Stock Available</th><th>Transaction</th><th>Name</th><th>Mobile</th><th>Model No</th><th>StoreName</th><th>Preview</th><th>Print</th><th>View</th><th>Link</th></tr></thead><tbody>';
    if (!$rows && !$legacy) {
        echo '<tr><td colspan="17" class="empty">No serial ' . ($like ? 'contains' : 'is') . ' "' . sales_h($q) . '".</td></tr>';
    }
    $btn = fn($href, $label, $target = '') => $href !== '' ? '<a class="btn small" href="' . sales_h($href) . '"' . ($target ? ' target="' . $target . '"' : '') . '>' . $label . '</a>' : '<button type="button" class="btn small" disabled>' . $label . '</button>';
    $sl = 0;
    foreach ($rows as $m) {
        [$view, $preview, $print] = sh_links($conn, $m['doc_type'], (int) $m['doc_id']);
        $link = '';
        if ($m['next']) {
            [$nextView] = sh_links($conn, $m['next']['doc_type'], (int) $m['next']['doc_id']);
            $link = $btn($nextView, $m['direction'] === 'in' ? 'Out' : 'In');
        } else {
            $link = '<button type="button" class="btn small" disabled title="No later movement">' . ($m['direction'] === 'in' ? 'Out' : 'In') . '</button>';
        }
        echo '<tr><td>' . ++$sl . '</td><td>' . sales_h($m['doc_no']) . '</td><td>' . sales_h($m['ref_no']) . '</td><td>' . sales_h(inv_branch_name($conn, $m['branch_id'])) . '</td>'
            . '<td><a href="' . $self . '?serial=' . urlencode($q) . ($like ? '&like=1' : '') . '&view=' . (int) $m['serial_id'] . '" title="Timeline and warranty">' . sales_h($m['serial_no']) . '</a></td>'
            . '<td>' . inv_fmt_date($m['move_date']) . '</td><td class="nowrap">' . sales_h(date('d/m/Y h:i A', strtotime($m['created_at']))) . '</td><td>' . ((int) $m['in_stock'] ? 'Yes' : 'No') . '</td>'
            . '<td>' . sales_h($m['transaction_label'] ?: (INV_TYPES[$m['doc_type']]['title'] ?? $m['doc_type'])) . ' <span class="muted">(' . ($m['direction'] === 'in' ? 'In' : 'Out') . ')</span></td>'
            . '<td>' . sales_h($m['party_name']) . '</td><td>' . sales_h($m['mobile']) . '</td><td>' . sales_h($m['model_caption'] ?: $m['description']) . '</td><td>' . sales_h($m['store_name']) . '</td>'
            . '<td>' . $btn($preview, 'Preview', '_blank') . '</td><td>' . $btn($print, 'Print', '_blank') . '</td><td>' . $btn($view, 'View') . '</td><td>' . $link . '</td></tr>';
    }
    foreach ($legacy as $r) {
        echo '<tr><td>' . ++$sl . '</td><td>' . sales_h($r['invoice_no']) . '</td><td>' . sales_h($r['ref_no']) . '</td><td>' . sales_h(inv_branch_name($conn, $r['branch_id'] ?? 0)) . '</td><td>' . sales_h($q) . '</td>'
            . '<td>' . inv_fmt_date($r['sales_date']) . '</td><td class="nowrap">' . sales_h(date('d/m/Y h:i A', strtotime($r['entry_at']))) . '</td><td>No</td><td>Sales <span class="muted">(no stock record)</span></td>'
            . '<td>' . sales_h($r['customer_name']) . '</td><td>' . sales_h($r['mobile']) . '</td><td>' . sales_h($r['model_caption'] ?: $r['description']) . '</td><td>' . sales_h($r['store_name']) . '</td>'
            . '<td>' . $btn('../sales/sales_invoice/sales_invoice.php?print=' . (int) $r['invoice_id'], 'Preview', '_blank') . '</td><td>' . $btn('../sales/sales_invoice/sales_invoice.php?print=' . (int) $r['invoice_id'] . '&auto=1', 'Print', '_blank') . '</td>'
            . '<td>' . $btn('../sales/sales_invoice/sales_invoice.php?id=' . (int) $r['invoice_id'], 'View') . '</td><td></td></tr>';
    }
    echo '</tbody></table></div>';
    if (count($moves) === 2000) {
        echo '<p class="footnote">Showing the first 2000 movements. Type more of the serial to narrow the search.</p>';
    }
}
echo '</fieldset>';
inv_page_end();
?>
<?php include "../../addon/conf/connection_close.php";  ?>
