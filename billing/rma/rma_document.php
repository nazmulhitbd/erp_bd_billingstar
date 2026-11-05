<?php
require_once __DIR__ . '/rma_docs.php';

function rma_saved_fields(array $fields)
{
    return array_values(array_filter($fields, function ($f) {
        return empty($f['nosave']) && empty($f['calc']) && $f['t'] !== 'docno';
    }));
}

function rma_bind_type(array $f)
{
    if (in_array($f['t'], ['num', 'qty'], true)) {
        return 'd';
    }
    return in_array($f['t'], ['int', 'id'], true) ? 'i' : 's';
}

/** Validate one posted value according to its field definition. Throws on invalid input. */
function rma_clean_value(array $f, $raw)
{
    $raw = is_scalar($raw) ? trim((string) $raw) : '';
    $label = $f['l'] !== '' ? $f['l'] : $f['n'];
    switch ($f['t']) {
        case 'date':
            $date = rma_date_or_null($raw);
            if ($date === false) {
                throw new RuntimeException('Enter a valid date for ' . $label . '.');
            }
            if ($date === null && !empty($f['req'])) {
                throw new RuntimeException($label . ' is required.');
            }
            return $date;
        case 'num':
            return rma_amount($raw, 2);
        case 'qty':
            return rma_amount($raw, 3);
        case 'int':
            return max(0, (int) $raw);
        case 'id':
            $id = (int) $raw;
            if ($id <= 0 && !empty($f['req'])) {
                throw new RuntimeException('Select a ' . ($label === 'complain_item_id' ? 'job (Job Ref No → Search)' : $label) . '.');
            }
            return $id > 0 ? $id : null;
        case 'select':
        case 'radio':
            $keys = array_map('strval', array_keys($f['opt']));
            if (in_array($raw, $keys, true)) {
                return $raw;
            }
            return (string) ($f['def'] ?? $keys[0]);
        default:
            $value = sales_cut($raw, (int) ($f['max'] ?? 190));
            if ($value === '' && !empty($f['req'])) {
                throw new RuntimeException($label . ' is required.');
            }
            return $value;
    }
}

function rma_field_default(array $cfg, array $f)
{
    $def = (string) ($f['def'] ?? '');
    if ($def === '@store') {
        return rma_store_default($cfg['store_purpose'] ?? 'complain_receive');
    }
    if ($def === '@user') {
        return rma_user_name();
    }
    if ($def === '' && $f['t'] === 'date' && !empty($f['req'])) {
        return date('Y-m-d');
    }
    if ($def === '' && in_array($f['t'], ['select', 'radio'], true)) {
        return (string) array_key_first($f['opt']);
    }
    return $def;
}

function rma_load_document(array $cfg, $id)
{
    $header = rma_row("SELECT * FROM {$cfg['table']} WHERE {$cfg['pk']}=?", 'i', [$id]);
    if (!$header) {
        return [null, []];
    }
    $header = rma_load_extras($cfg, $header);
    $items = $cfg['item_table']
        ? rma_rows("SELECT * FROM {$cfg['item_table']} WHERE {$cfg['pk']}=? ORDER BY line_number", 'i', [$id])
        : [];
    foreach ($items as &$item) {
        if (array_key_exists('receivable', $item)) {
            $item['adj_amount'] = (float) $item['receivable'] > 0 ? $item['receivable'] : $item['payable'];
        }
    }
    unset($item);
    return [$header, $items];
}

function rma_is_locked(array $cfg, $header)
{
    return $header && !empty($cfg['approvable']) && ($header['status'] ?? 'draft') !== 'draft';
}

function rma_save_document(array $cfg)
{
    $docId = (int) ($_POST['doc_id'] ?? 0);
    $postedHeader = is_array($_POST['h'] ?? null) ? $_POST['h'] : [];
    $header = [];
    foreach ($cfg['header'] as $f) {
        if ($f['t'] === 'docno' || !empty($f['calc'])) {
            continue;
        }
        $header[$f['n']] = rma_clean_value($f, $postedHeader[$f['n']] ?? ($f['def'] ?? ''));
    }

    $items = [];
    if ($cfg['item_table']) {
        $rawItems = json_decode((string) ($_POST['items_json'] ?? ''), true);
        if (!is_array($rawItems) || count($rawItems) < 1 || count($rawItems) > 200) {
            throw new RuntimeException('Add at least one item (up to 200 per document).');
        }
        foreach ($rawItems as $raw) {
            if (!is_array($raw)) {
                continue;
            }
            $item = [];
            foreach ($cfg['item'] as $f) {
                $item[$f['n']] = rma_clean_value($f, $raw[$f['n']] ?? ($f['def'] ?? ''));
            }
            $items[] = $item;
        }
    }
    [$header, $items] = rma_prepare($cfg, $header, $items);

    $headerCols = [];
    foreach (rma_saved_fields($cfg['header']) as $f) {
        $headerCols[$f['n']] = rma_bind_type($f);
    }
    $headerCols += rma_header_extra_columns($cfg['type']);
    $itemCols = [];
    foreach (rma_saved_fields($cfg['item']) as $f) {
        $itemCols[$f['n']] = rma_bind_type($f);
    }
    $itemCols += rma_item_extra_columns($cfg['type']);

    $conn = $GLOBALS['rma_conn'];
    $conn->begin_transaction();
    try {
        if ($docId > 0) {
            $existing = rma_row("SELECT * FROM {$cfg['table']} WHERE {$cfg['pk']}=? FOR UPDATE", 'i', [$docId]);
            if (!$existing) {
                throw new RuntimeException('Record not found.');
            }
            if (rma_is_locked($cfg, $existing)) {
                throw new RuntimeException($existing[$cfg['no']] . ' is approved and can no longer be edited.');
            }
            $docNo = $existing[$cfg['no']];
            $sets = implode(', ', array_map(function ($c) { return "$c=?"; }, array_keys($headerCols)));
            $values = array_map(function ($c) use ($header) { return $header[$c]; }, array_keys($headerCols));
            $values[] = $docId;
            rma_exec("UPDATE {$cfg['table']} SET $sets WHERE {$cfg['pk']}=?", implode('', $headerCols) . 'i', $values)->close();
            if ($cfg['item_table']) {
                rma_exec("DELETE FROM {$cfg['item_table']} WHERE {$cfg['pk']}=?", 'i', [$docId])->close();
            }
        } else {
            $docDate = (string) $header[$cfg['date']];
            $docNo = rma_doc_no($cfg['code'], (int) substr($docDate, 0, 4));
            $cols = array_merge([$cfg['no'] => 's'], $headerCols, ['created_by' => 'i', 'created_by_name' => 's']);
            $values = array_merge([$docNo], array_map(function ($c) use ($header) { return $header[$c]; }, array_keys($headerCols)), [rma_user_id(), rma_user_name()]);
            $place = implode(', ', array_fill(0, count($cols), '?'));
            rma_exec("INSERT INTO {$cfg['table']} (" . implode(', ', array_keys($cols)) . ") VALUES ($place)", implode('', $cols), $values)->close();
        $docId = (int) $conn->insert_id;
    }

    if ($cfg['item_table']) {
        $cols = array_merge([$cfg['pk'] => 'i', 'line_number' => 'i'], $itemCols);
        $place = implode(', ', array_fill(0, count($cols), '?'));
        $sql = "INSERT INTO {$cfg['item_table']} (" . implode(', ', array_keys($cols)) . ") VALUES ($place)";
    foreach ($items as $index => $item) {
        $values = array_merge([$docId, $index + 1], array_map(function ($c) use ($item) { return $item[$c]; }, array_keys($itemCols)));
        rma_exec($sql, implode('', $cols), $values)->close();
    }
}
$header[$cfg['no']] = $docNo;
rma_after_save($cfg, $docId, $header, $items);
if (!(int) ($_POST['doc_id'] ?? 0) && !empty($cfg['approvable'])) {
    notify_admin('rma/approve.php?type=' . $cfg['type'], 'Approval needed: ' . $cfg['title'] . ' ' . $docNo, (string) ($header[$cfg['party']] ?? ''));
}
$conn->commit();
} catch (Throwable $error) {
    $conn->rollback();
    throw $error;
}
bus_admin_audit('rma_save', $cfg['table'], $docId, ['doc_no' => $docNo]);
return [$docId, $docNo];
}

function rma_delete_document(array $cfg, $docId)
{
    $conn = $GLOBALS['rma_conn'];
    $conn->begin_transaction();
    try {
        $existing = rma_row("SELECT * FROM {$cfg['table']} WHERE {$cfg['pk']}=? FOR UPDATE", 'i', [$docId]);
        if (!$existing) {
            throw new RuntimeException('Record not found.');
        }
        if (rma_is_locked($cfg, $existing)) {
            throw new RuntimeException($existing[$cfg['no']] . ' is approved and cannot be deleted.');
        }
        rma_before_delete($cfg, $docId);
        rma_exec("DELETE FROM {$cfg['table']} WHERE {$cfg['pk']}=?", 'i', [$docId])->close();
        $conn->commit();
    } catch (Throwable $error) {
        $conn->rollback();
        throw $error;
    }
    bus_admin_audit('rma_delete', $cfg['table'], $docId, ['doc_no' => $existing[$cfg['no']]]);
    return $existing[$cfg['no']];
}

function rma_settle_delivery(array $cfg, $docId)
{
    $conn = $GLOBALS['rma_conn'];
    $conn->begin_transaction();
    try {
        $existing = rma_row("SELECT * FROM {$cfg['table']} WHERE {$cfg['pk']}=? FOR UPDATE", 'i', [$docId]);
        if (!$existing) {
            throw new RuntimeException('Record not found.');
        }
        if ($existing['status'] !== 'approved') {
            throw new RuntimeException('Approve ' . $existing[$cfg['no']] . ' before settling it.');
        }
        rma_exec("UPDATE rma_deliveries SET is_settled=1, settled_at=NOW() WHERE delivery_id=?", 'i', [$docId])->close();
        rma_exec(
            "UPDATE rma_complain_items ci JOIN rma_delivery_items d ON d.complain_item_id=ci.item_id "
                . "SET ci.line_status='settled' WHERE d.delivery_id=?",
            'i', [$docId]
        )->close();
        $conn->commit();
    } catch (Throwable $error) {
        $conn->rollback();
        throw $error;
    }
    return $existing[$cfg['no']];
}

/* ------------------------------------------------------------------ Rendering */

function rma_render_field(array $f, $scope, $value, $locked)
{
    $id = $scope . '_' . $f['n'];
    $nameAttr = $scope === 'h' ? ' name="h[' . rma_h($f['n']) . ']"' : '';
    $data = ' data-field="' . rma_h($f['n']) . '" data-def="' . rma_h($f['_def'] ?? '') . '"';
    $dis = $locked && $f['t'] !== 'docno' ? ' disabled' : '';
    if ($f['t'] === 'id' || $f['t'] === 'hidden') {
        return '<input type="hidden" id="' . $id . '"' . $nameAttr . $data . ' value="' . rma_h($value) . '">';
    }
    $w = max(1, min(12, (int) $f['w']));
    if ($f['t'] === 'radio') {
        $html = '<div class="rma-field w' . $w . ' rma-radios">';
        foreach ($f['opt'] as $key => $label) {
            $html .= '<label><input type="radio" name="' . ($scope === 'h' ? 'h[' . rma_h($f['n']) . ']' : $id) . '" value="' . rma_h($key) . '"'
                . ' data-radio="' . rma_h($f['n']) . '" data-scope="' . $scope . '"' . ((string) $key === (string) $value ? ' checked' : '') . $dis . '> ' . rma_h($label) . '</label>';
        }
        return $html . '<input type="hidden" id="' . $id . '"' . $data . ' data-radio-holder="1" value="' . rma_h($value) . '"></div>';
    }
    $req = !empty($f['req']) ? ' required' : '';
    $max = isset($f['max']) ? ' maxlength="' . (int) $f['max'] . '"' : '';
    $extra = '';
    if (!empty($f['dl'])) {
        $extra .= ' list="dl-' . rma_h($f['dl']) . '"';
    }
    if (!empty($f['ac'])) {
        $extra .= ' data-ac="' . rma_h($f['ac']) . '" autocomplete="off"';
    }
    if ($f['t'] === 'select') {
        $control = '<select id="' . $id . '"' . $nameAttr . $data . $dis . '>';
        foreach ($f['opt'] as $key => $label) {
            $control .= '<option value="' . rma_h($key) . '"' . ((string) $key === (string) $value ? ' selected' : '') . '>' . rma_h($label) . '</option>';
        }
        $control .= '</select>';
    } elseif ($f['t'] === 'textarea') {
        $control = '<textarea id="' . $id . '"' . $nameAttr . $data . $max . $req . $dis . '>' . rma_h($value) . '</textarea>';
    } else {
        $type = ['date' => 'date', 'num' => 'number', 'qty' => 'number', 'int' => 'number'][$f['t']] ?? 'text';
        $step = ['num' => ' step="0.01" min="0"', 'qty' => ' step="0.001" min="0"', 'int' => ' step="1" min="0"'][$f['t']] ?? '';
        $ro = $f['t'] === 'ro' ? ' readonly class="ro" tabindex="-1"' : '';
        $control = '<input type="' . $type . '" id="' . $id . '"' . $nameAttr . $data . $step . $max . $req . $ro . $extra . $dis . ' value="' . rma_h($value) . '">';
    }
    if (!empty($f['btn'])) {
        $control = '<div class="rma-with-btn">' . $control . '<button type="button" class="rma-btn" data-lookup="' . rma_h($f['btn']) . '" data-mode="' . rma_h($f['mode'] ?? '') . '" data-for="' . $id . '"' . $dis . '>Search</button></div>';
    } elseif ($f['t'] === 'docno') {
        $control = '<div class="rma-with-btn">' . $control . '<button type="button" class="rma-btn" data-docsearch="' . $id . '">Search</button></div>';
    } elseif (!empty($f['ac'])) {
        $control = '<div class="rma-ac">' . $control . '</div>';
    }
    $label = empty($f['nolabel']) && $f['l'] !== '' ? '<label for="' . $id . '">' . rma_h($f['l']) . '</label>' : '';
    return '<div class="rma-field w' . $w . ($label === '' ? ' nolabel' : '') . '">' . $label . $control . '</div>';
}

function rma_render_print(array $cfg, array $header, array $items)
{
    $auto = !empty($_GET['auto']);
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>' . rma_h($header[$cfg['no']]) . '</title><link rel="stylesheet" href="rma.css?v=1"></head><body class="rma-print">';
    echo '<button class="rma-btn noprint" onclick="window.print()">Print</button>';
    echo '<h1>' . rma_h($cfg['title']) . '</h1><div class="rma-print-meta">';
    echo '<div><b>No:</b> ' . rma_h($header[$cfg['no']]) . '</div><div><b>Status:</b> ' . rma_h(ucfirst($header['status'] ?? 'saved')) . '</div>';
    foreach ($cfg['header'] as $f) {
        if (in_array($f['t'], ['id', 'hidden', 'docno'], true) || $f['l'] === '' || ($header[$f['n']] ?? '') === '') {
            continue;
        }
        $value = $header[$f['n']];
        if (isset($f['opt'][$value])) {
            $value = $f['opt'][$value];
        }
        echo '<div><b>' . rma_h($f['l']) . ':</b> ' . nl2br(rma_h($value)) . '</div>';
    }
    echo '</div>';
    if ($cfg['item_table']) {
        $cols = $cfg['grid'];
        foreach ($cfg['serial_fields'] as $s) {
            if (!in_array($s, array_column($cols, 0), true)) {
                $cols[] = [$s, ucwords(str_replace('_', ' ', $s))];
            }
        }
        echo '<table class="rma-table"><thead><tr><th>SL</th>';
        foreach ($cols as $c) {
            echo '<th class="' . (($c[2] ?? '') === 'num' ? 'num' : '') . '">' . rma_h($c[1]) . '</th>';
        }
        echo '</tr></thead><tbody>';
        foreach ($items as $i => $item) {
            echo '<tr><td>' . ($i + 1) . '</td>';
            foreach ($cols as $c) {
                $v = $item[$c[0]] ?? '';
                echo ($c[2] ?? '') === 'num' ? '<td class="num">' . number_format((float) $v, 2) . '</td>' : '<td>' . rma_h($v) . '</td>';
            }
            echo '</tr>';
        }
        echo '</tbody></table>';
    }
    foreach ($cfg['totals'] as $col => $label) {
        echo '<p class="right"><b>' . rma_h($label) . ' ' . number_format((float) ($header[$col] ?? 0), 2) . '</b></p>';
    }
    echo '<div class="rma-sign"><span>Prepared By: ' . rma_h($header['created_by_name'] ?? '') . '</span><span>Approved By</span><span>Received By</span></div>';
    if ($auto) {
        echo '<script>window.addEventListener("load",function(){window.print()})</script>';
    }
    echo '</body></html>';
}

function rma_render_list(array $cfg)
{
    $q = sales_cut(trim((string) ($_GET['q'] ?? '')), 100);
    $like = '%' . $q . '%';
    $statusCol = !empty($cfg['approvable']) ? 'status' : "'saved'";
    $rows = rma_list_rows(
        "SELECT {$cfg['pk']} AS id, {$cfg['no']} AS doc_no, {$cfg['date']} AS doc_date, {$cfg['party']} AS party, $statusCol AS status, created_by_name "
            . "FROM {$cfg['table']} WHERE {$cfg['no']} LIKE ? OR {$cfg['party']} LIKE ? ORDER BY {$cfg['pk']} DESC",
        'ss', [$like, $like]
    );
    echo '<form class="rma-toolbar" method="get"><input type="hidden" name="view" value="list"><input name="q" value="' . rma_h($q) . '" placeholder="Number or name"><button class="rma-btn">Search</button><a class="rma-btn" href="' . rma_h($cfg['page']) . '">Back</a></form>';
    echo '<div class="rma-grid-wrap"><table class="rma-table"><thead><tr><th>SL</th><th>Job Ref No</th><th>Date</th><th>' . rma_h($cfg['party_label']) . '</th><th>Status</th><th>User Name</th><th>Open</th><th>Print</th></tr></thead><tbody>';
    if (!$rows) {
        echo '<tr><td colspan="8" class="empty">No records found.</td></tr>';
    }
    foreach ($rows as $i => $r) {
        echo '<tr><td>' . ($i + 1) . '</td><td>' . rma_h($r['doc_no']) . '</td><td>' . rma_h(date('d/m/Y', strtotime($r['doc_date']))) . '</td><td>' . rma_h($r['party'])
            . '</td><td><span class="rma-badge ' . rma_h($r['status']) . '">' . rma_h(ucfirst($r['status'])) . '</span></td><td>' . rma_h($r['created_by_name']) . '</td>'
            . '<td><a class="rma-btn" href="' . rma_h($cfg['page']) . '?id=' . (int) $r['id'] . '">Select</a></td>'
            . '<td><a class="rma-btn" target="_blank" href="' . rma_h($cfg['page']) . '?print=' . (int) $r['id'] . '">Print</a></td></tr>';
    }
    echo '</tbody></table></div>';
}

function rma_document_page($type)
{
    $cfg = rma_doc_config($type);
    $page = $cfg['page'];
    foreach (['header', 'item'] as $scope) {
        foreach ($cfg[$scope] as &$f) {
            $f['_def'] = rma_field_default($cfg, $f);
        }
        unset($f);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        rma_require_csrf($page);
        if (!rma_schema_ready()) {
            rma_flash('error', 'RMA tables are missing. Import schema.sql first.');
            rma_redirect($page);
        }
        $action = (string) ($_POST['action'] ?? 'save');
        $docId = (int) ($_POST['doc_id'] ?? 0);
        try {
            if ($action === 'delete' && $docId > 0) {
                $no = rma_delete_document($cfg, $docId);
                rma_flash('success', $no . ' deleted.');
                rma_redirect($page);
            }
            if ($action === 'settle' && $docId > 0 && !empty($cfg['settle'])) {
                $no = rma_settle_delivery($cfg, $docId);
                rma_flash('success', $no . ' settled.');
                rma_redirect($page . '?id=' . $docId);
            }
            [$docId, $no] = rma_save_document($cfg);
            rma_flash('success', $no . ' saved.');
            rma_redirect($page . '?id=' . $docId);
        } catch (Throwable $error) {
            error_log('RMA ' . $type . ' save failed: ' . $error->getMessage());
            rma_flash('error', $error->getMessage());
            rma_redirect($page . ($docId > 0 ? '?id=' . $docId : ''));
        }
    }

    $ready = rma_schema_ready();
    $id = (int) ($_GET['id'] ?? 0);
    if ($ready && isset($_GET['nav'])) {
        $sql = $_GET['nav'] === 'next'
            ? "SELECT {$cfg['pk']} AS id FROM {$cfg['table']} WHERE {$cfg['pk']}>? ORDER BY {$cfg['pk']} LIMIT 1"
            : "SELECT {$cfg['pk']} AS id FROM {$cfg['table']} WHERE ({$cfg['pk']}<? OR ?=0) ORDER BY {$cfg['pk']} DESC LIMIT 1";
        $row = $_GET['nav'] === 'next' ? rma_row($sql, 'i', [$id]) : rma_row($sql, 'ii', [$id, $id]);
        if (!$row) {
            rma_flash('error', $_GET['nav'] === 'next' ? 'This is the last record.' : 'This is the first record.');
        }
        rma_redirect($page . ($row ? '?id=' . (int) $row['id'] : ($id ? '?id=' . $id : '')));
    }
    if ($ready && isset($_GET['no'])) {
        $row = rma_row("SELECT {$cfg['pk']} AS id FROM {$cfg['table']} WHERE {$cfg['no']}=?", 's', [trim((string) $_GET['no'])]);
        if (!$row) {
            rma_flash('error', 'No record found for ' . trim((string) $_GET['no']) . '.');
        }
        rma_redirect($page . ($row ? '?id=' . (int) $row['id'] : ''));
    }
    if ($ready && isset($_GET['print'])) {
        [$header, $items] = rma_load_document($cfg, (int) $_GET['print']);
        if (!$header) {
            http_response_code(404);
            exit('Record not found.');
        }
        rma_render_print($cfg, $header, $items);
        exit;
    }

    rma_page_header($cfg['title']);
    if ($ready && ($_GET['view'] ?? '') === 'list') {
        rma_render_list($cfg);
        rma_page_footer();
        return;
    }

    [$header, $items] = $ready && $id > 0 ? rma_load_document($cfg, $id) : [null, []];
    if ($id > 0 && !$header && $ready) {
        echo '<div class="rma-alert err">Record not found.</div>';
    }
    $locked = rma_is_locked($cfg, $header);
    $docId = $header ? $id : 0;

    echo '<form method="post" id="rma-form" autocomplete="off">' . rma_csrf_field()
        . '<input type="hidden" name="action" id="rma-action" value="save"><input type="hidden" name="doc_id" id="rma-doc-id" value="' . $docId . '">'
        . '<input type="hidden" name="items_json" id="rma-items-json">';
    if ($header) {
        $status = !empty($cfg['approvable']) ? $header['status'] : 'saved';
        echo '<div class="rma-status"><span class="rma-badge ' . rma_h($status) . '">' . rma_h(ucfirst($status)) . '</span>';
        if (!empty($cfg['settle']) && !empty($header['is_settled'])) {
            echo ' <span class="rma-badge settled">Settled</span>';
        }
        if ($locked) {
            echo ' <span class="muted">Approved records are read-only.</span>';
        }
        echo '</div>';
    }
    echo '<div class="rma-grid">';
    foreach ($cfg['header'] as $f) {
        if ($f['t'] === 'docno') {
            $value = $header[$f['n']] ?? ($ready ? rma_next_doc_preview($cfg['code']) : '');
        } else {
            $value = $header ? ($header[$f['n']] ?? '') : $f['_def'];
        }
        echo rma_render_field($f, 'h', $value, $locked);
    }
    echo '</div>';
    if ($cfg['item_table']) {
        echo '<div class="rma-grid rma-line" id="rma-line">';
        foreach ($cfg['item'] as $f) {
            echo rma_render_field($f, 'i', $f['_def'], $locked);
        }
        echo '</div>';
    }

    $dis = $locked ? ' disabled' : '';
    echo '<div class="rma-toolbar">'
        . '<a class="rma-btn" href="' . rma_h($page) . '">New</a>'
        . '<a class="rma-btn" href="' . rma_h($page) . '?view=list">List View</a>'
        . '<button type="button" class="rma-btn" data-act="add"' . $dis . '>Add</button>'
        . '<button type="button" class="rma-btn" data-act="edit"' . $dis . '>Edit</button>'
        . '<button type="button" class="rma-round" data-act="prev" title="Previous">&#10094;</button>'
        . '<button type="button" class="rma-round" data-act="clear" title="Clear line">&#10073;&#10073;</button>'
        . '<button type="button" class="rma-round" data-act="next" title="Next">&#10095;</button>';
    if (!empty($cfg['settle'])) {
        echo '<button type="button" class="rma-btn" data-act="settle"' . ($header && $header['status'] === 'approved' && empty($header['is_settled']) ? '' : ' disabled') . '>Settled</button>';
    }
    if (!$cfg['item_table']) {
        echo '<button type="button" class="rma-btn" data-act="delete-doc"' . ($docId && !$locked ? '' : ' disabled') . '>Delete</button>';
    }
    echo '<button type="button" class="rma-btn" data-act="serials">Serial View</button>'
        . '<button type="button" class="rma-btn wide" data-act="preview">Preview</button>'
        . '<button type="button" class="rma-btn" data-act="print">Print</button>'
        . '</div></form>';

    $gridRows = $items;
    if (!$cfg['item_table'] && $ready) {
        // Header-only documents (Diagnosis) list recent records in the grid.
        $gridRows = [];
        foreach (rma_rows("SELECT * FROM {$cfg['table']} ORDER BY {$cfg['pk']} DESC LIMIT 50") as $r) {
            $gridRows[] = rma_load_extras($cfg, $r) + ['_id' => $r[$cfg['pk']]];
        }
    }
    echo '<div class="rma-grid-wrap"><table class="rma-table" id="rma-items"><thead></thead><tbody></tbody></table></div>';
    if ($cfg['totals']) {
        echo '<div class="rma-totals">';
        foreach ($cfg['totals'] as $col => $label) {
            echo '<span>' . rma_h($label) . ' <b id="tot-' . rma_h($col) . '">0.00</b></span>';
        }
        echo '</div>';
    }
    echo '<datalist id="dl-stores">';
    foreach ($ready ? rma_store_options() : [] as $s) {
        echo '<option value="' . rma_h($s) . '">';
    }
    echo '</datalist><datalist id="dl-problems">';
    foreach ($ready ? rma_problem_options() : [] as $p) {
        echo '<option value="' . rma_h($p) . '">';
    }
    echo '</datalist>';

    $js = [
        'type' => $type, 'page' => $page, 'docId' => $docId, 'locked' => $locked,
        'hasItems' => (bool) $cfg['item_table'], 'grid' => $cfg['grid'], 'items' => $gridRows,
        'lineRequired' => $cfg['line_required'] ?? '', 'serialFields' => $cfg['serial_fields'],
        'totals' => array_keys($cfg['totals']), 'header' => $header ? array_intersect_key($header, array_flip($cfg['serial_fields'])) : new stdClass(),
    ];
    rma_page_footer('RMA.initDocument(' . json_encode($js, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) . ');');
}
