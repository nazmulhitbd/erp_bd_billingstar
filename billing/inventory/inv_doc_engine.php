<?php
/**
 * Generic document screen for inv_docs: header + item grid + totals, with source-document loading,
 * stock and serial posting, list view, print, barcode labels, cancel and prev/next navigation.
 *
 * Each page passes a config:
 *   type        key of INV_TYPES
 *   active      menu key (defaults to type)
 *   header      [[name, label, kind, opts], ...]  kind: text | date | select | textarea | party | mobile
 *   lines       enabled line fields: store, store_to, warranty, narration, free, price, discount, serials, prev_price, auto_serial
 *   totals      subset of ['extra', 'discount', 'vat']; cash => true adds the cash amount box
 *   amounts     false hides prices everywhere (challan, requisition, transfer)
 *   source      ['type' => source doc_type | 'sales_order', 'label' => 'Purchase Order Wise', 'field' => 'Order No', 'mode' => remaining | bill | all]
 *   source_required  true when lines must come from a source document
 *   label       fn(array $doc): string, transaction name for Serial History
 *   editable    false = saved documents cannot be edited or cancelled
 *   after_save  fn(mysqli $db, int $docId, array $doc, array $lines)
 *   buttons     extra buttons: serial_view, barcode (serial labels), barcode_product (product code labels), edit_price,
 *               show_cost, show_price, payment_term, import, add_all
 *   print_title title on the printout
 *   cash_label / cash_change  label of the cash box, and whether it shows the change amount (default true)
 *   last_info   true shows Last Price and Last Serial next to Qty Available
 *   custom_label  label of the Custom Preview button
 *   search_label / narration_label  labels of the document number search box and the line narration field
 *   totals_label  label of the button that jumps to the extra / discount / VAT boxes
 *   jump        [[element id, label], ...] buttons that scroll to a panel printed by 'panels'
 *   request     fn(mysqli, array $cfg, string $self): handles extra requests before the page (may exit)
 *   panels      fn(mysqli, array $cfg, string $self, ?array $doc, bool $readOnly): HTML printed after the form
 *   on_cancel   fn(mysqli, array $doc): runs inside the cancel transaction; throw to refuse
 *   locked      fn(mysqli, array $doc): reason a saved document can no longer be edited or cancelled ('' = it can)
 *   after_details  fn(mysqli, array $doc): runs after Edit Details saved the header of a document
 *   print_label   label of the Print button
 *   additional  true adds the Additional Info box (inv_docs.additional_info, see challan/schema.sql)
 *   payment_date  true adds a Payment Date box next to the cash amount (saved as required_date)
 *   cash_extra  fn(?array $doc): HTML added after the cash amount (e.g. a Cash Receipt link)
 *   buttons also: edit_details (save header fields only, also on approved documents), edit_price_inline (edit prices in the grid)
 *   header kind 'sourceinfo': read-only box showing the source document's own source number (e.g. the sales order of a challan)
 *   last_info 'serial': show Qty Available and Last Serial only
 *   date_label  label of the document date (default Date)
 *   buttons also: extra_charges (jump to Add Extra Charges)
 *   lines also: compact_grid (grid shows SL, Product ID, Model, Quantity, Unit Price, Amount, Select, Edit, Delete)
 */
require_once __DIR__ . '/inv_common.php';

function inv_doc_parties(mysqli $db, $kind)
{
    if ($kind === 'customer') {
        return sales_rows($db, 'SELECT customer_id AS id, customer_group AS grp, customer_name AS name, mobile, COALESCE(address, \'\') AS address, attention FROM sales_customers ORDER BY customer_name LIMIT 5000');
    }
    if ($kind === 'supplier') {
        if (sales_tables_ready($db, ['traders', 'trader_categories'])) {
            return sales_rows($db, "SELECT t.trader_id AS id, COALESCE(g.group_name, '') AS grp, t.trader_name AS name, t.mobile, COALESCE(t.address, '') AS address, t.contact_person AS attention FROM traders t JOIN trader_categories c ON c.category_id=t.category_id LEFT JOIN trader_groups g ON g.group_id=t.group_id WHERE c.kind='supplier' AND t.is_active=1 ORDER BY t.trader_name LIMIT 5000");
        }
        if (sales_tables_ready($db, ['rma_suppliers'])) {
            return sales_rows($db, "SELECT supplier_id AS id, supplier_group AS grp, supplier_name AS name, mobile, COALESCE(address, '') AS address, attention FROM rma_suppliers WHERE is_active=1 ORDER BY supplier_name LIMIT 5000");
        }
    }
    return [];
}

/**
 * Lines of a source document with what is still open for this document type.
 * Returns [header, lines] or null when not found. $excludeDocId leaves the document being edited out of the used totals.
 */
function inv_source_lines(mysqli $db, array $cfg, $sourceNo, $excludeDocId = 0)
{
    $src = $cfg['source'];
    $type = $cfg['type'];
    if ($src['type'] === 'sales_order') {
        if (!sales_tables_ready($db, ['sales_orders', 'sales_order_items'])) {
            return null;
        }
        $h = sales_row($db, "SELECT order_id AS doc_id, order_no AS doc_no, customer_id AS party_id, customer_group AS party_group, customer_name AS party_name, mobile, COALESCE(address, '') AS address, attention, sales_person, delivery_point, status FROM sales_orders WHERE order_no=? OR ref_no=? ORDER BY order_id DESC LIMIT 1", 'ss', [$sourceNo, $sourceNo]);
        if (!$h || $h['status'] === 'cancelled') {
            return null;
        }
        $lines = sales_rows(
            $db,
            "SELECT i.item_id AS source_line_id, i.product_id, i.product_code, i.product_group, i.brand, i.model_caption, i.description, i.unit_name, i.warranty, i.store_name, i.quantity, 0 AS free_quantity, i.unit_price, "
                . "i.quantity - COALESCE((SELECT SUM(l.quantity) FROM inv_doc_lines l JOIN inv_docs d ON d.doc_id=l.doc_id WHERE l.source_kind='sales_order' AND l.source_line_id=i.item_id AND d.doc_type=? AND d.status='active' AND d.doc_id<>?), 0) AS remaining "
                . 'FROM sales_order_items i WHERE i.order_id=? ORDER BY i.line_number',
            'sii',
            [$type, (int) $excludeDocId, (int) $h['doc_id']]
        );
        foreach ($lines as &$l) {
            $l['source_kind'] = 'sales_order';
        }
        return [$h, $lines];
    }
    $h = sales_row($db, "SELECT * FROM inv_docs WHERE doc_type=? AND (doc_no=? OR ref_no=?) AND status='active' ORDER BY doc_id DESC LIMIT 1", 'sss', [$src['type'], $sourceNo, $sourceNo]);
    if (!$h) {
        return null;
    }
    $mode = $src['mode'] ?? 'remaining';
    $used = "COALESCE((SELECT SUM(%s) FROM inv_doc_lines c JOIN inv_docs d ON d.doc_id=c.doc_id WHERE c.source_kind='inv' AND c.source_line_id=s.line_id AND d.doc_type=%s AND d.status='active' AND d.doc_id<>?), 0)";
    if ($mode === 'bill') {
        // Returns use up free quantity first; what is left of the paid quantity can still be billed.
        $returned = sprintf($used, 'c.quantity + c.free_quantity', "'challan_return'");
        $billed = sprintf($used, 'c.quantity', '?');
        $remaining = "LEAST(s.quantity, s.quantity + s.free_quantity - $returned) - $billed";
        // Placeholders in order: returned (doc_id<>?), billed (doc_type=?, doc_id<>?), then s.doc_id=?.
        $types = 'isii';
        $params = [(int) $excludeDocId, $type, (int) $excludeDocId, (int) $h['doc_id']];
    } elseif ($mode === 'all') {
        $remaining = 's.quantity';
        $types = 'i';
        $params = [(int) $h['doc_id']];
    } else {
        $remaining = 's.quantity + s.free_quantity - ' . sprintf($used, 'c.quantity + c.free_quantity', '?');
        $types = 'sii';
        $params = [$type, (int) $excludeDocId, (int) $h['doc_id']];
    }
    $lines = sales_rows($db, "SELECT s.*, s.line_id AS source_line_id, $remaining AS remaining FROM inv_doc_lines s WHERE s.doc_id=? ORDER BY s.line_no", $types, $params);
    // Bill from challan: price comes from the sales order line when the challan was made from one, else the product price.
    if ($mode === 'bill') {
        foreach ($lines as &$l) {
            $price = null;
            if ($l['source_kind'] === 'sales_order' && sales_tables_ready($db, ['sales_order_items'])) {
                $price = sales_row($db, 'SELECT unit_price FROM sales_order_items WHERE item_id=?', 'i', [(int) $l['source_line_id']])['unit_price'] ?? null;
            }
            if ($price === null && $l['product_id']) {
                $price = sales_row($db, 'SELECT default_unit_price FROM sales_products WHERE product_id=?', 'i', [(int) $l['product_id']])['default_unit_price'] ?? 0;
            }
            $l['unit_price'] = (float) $price;
            $l['source_line_id'] = $l['line_id'];
        }
        unset($l);
    }
    foreach ($lines as &$l) {
        $l['source_kind'] = 'inv';
        $l['source_line_id'] = $l['line_id'];
        if ($mode === 'all') {
            $l['prev_price'] = $l['unit_price'];
        }
    }
    return [$h, $lines];
}

function inv_doc_page(mysqli $db, array $cfg)
{
    $type = $cfg['type'];
    $meta = INV_TYPES[$type];
    $cfg += ['active' => $type, 'lines' => [], 'totals' => [], 'amounts' => true, 'source' => null, 'source_required' => false, 'editable' => true, 'buttons' => [], 'print_title' => $meta['title'], 'cash' => false,
        'cash_label' => 'Cash Amount', 'cash_change' => true, 'last_info' => false, 'custom_label' => 'Custom Preview', 'totals_label' => 'VAT & Discount', 'jump' => [],
        'search_label' => $meta['title'] . ' No', 'narration_label' => 'Narration', 'print_label' => 'Print', 'additional' => false, 'payment_date' => false, 'date_label' => 'Date'];
    $self = basename($_SERVER['SCRIPT_NAME']);
    inv_require_ready($db, $meta['title'], $cfg['active']);
    $branchId = inv_current_branch($db);
    $userId = (int) ($GLOBALS['user_id'] ?? 0);
    $has = fn($f) => in_array($f, $cfg['lines'], true);

    if (isset($cfg['request'])) {
        ($cfg['request'])($db, $cfg, $self);
    }

    /* ---------- JSON endpoints ---------- */
    if (isset($_GET['avail'])) {
        $pid = (int) $_GET['avail'];
        $out = ['qty' => inv_available($db, $pid, trim((string) ($_GET['store'] ?? '')))];
        if ($cfg['last_info']) {
            $out['last_price'] = (float) (sales_row($db, "SELECT unit_cost FROM inv_stock_moves WHERE product_id=? AND qty_in>0 AND doc_type='purchase' ORDER BY move_id DESC LIMIT 1", 'i', [$pid])['unit_cost'] ?? 0);
            $store = trim((string) ($_GET['store'] ?? ''));
            $out['last_serial'] = in_array($meta['stock'], ['out', 'transfer'], true)
                // Issuing stock: the oldest serial still in stock in that store, the one to pick first.
                ? (string) (sales_row($db, 'SELECT serial_no FROM inv_serials WHERE product_id=? AND in_stock=1' . ($store !== '' ? ' AND store_name=?' : '') . ' ORDER BY serial_id LIMIT 1', $store !== '' ? 'is' : 'i', $store !== '' ? [$pid, $store] : [$pid])['serial_no'] ?? '')
                : (string) (sales_row($db, 'SELECT serial_no FROM inv_serials WHERE product_id=? ORDER BY serial_id DESC LIMIT 1', 'i', [$pid])['serial_no'] ?? '');
        }
        if ($cfg['last_info'] === 'serial') {
            unset($out['last_price']); // These screens show no price, so the purchase cost is not sent to them either.
        }
        sales_json($out);
    }
    if (isset($_GET['source']) && $cfg['source']) {
        $found = inv_source_lines($db, $cfg, trim((string) $_GET['source']), (int) ($_GET['exclude'] ?? 0));
        if (!$found) {
            sales_json(['error' => 'No ' . $cfg['source']['field'] . ' matches "' . trim((string) $_GET['source']) . '".'], 404);
        }
        [$h, $lines] = $found;
        sales_json(['header' => $h, 'lines' => $lines]);
    }

    /* ---------- Print and barcode ---------- */
    if (isset($_GET['print']) || isset($_GET['barcode'])) {
        $doc = sales_row($db, 'SELECT * FROM inv_docs WHERE doc_id=? AND doc_type=?', 'is', [(int) ($_GET['print'] ?? $_GET['barcode']), $type]);
        if (!$doc) {
            http_response_code(404);
            exit('Document not found.');
        }
        $lines = sales_rows($db, 'SELECT * FROM inv_doc_lines WHERE doc_id=? ORDER BY line_no', 'i', [(int) $doc['doc_id']]);
        if (isset($_GET['barcode'])) {
            inv_doc_barcodes($doc, $lines, ($_GET['mode'] ?? '') === 'product' ? 'product' : 'serial');
        } else {
            inv_doc_print($db, $cfg, $doc, $lines, !empty($_GET['custom']));
        }
        exit;
    }

    /* ---------- Save / cancel ---------- */
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $docId = (int) ($_POST['doc_id'] ?? 0);
        $back = $self . ($docId ? '?id=' . $docId : '');
        sales_require_csrf($back);
        $action = (string) ($_POST['action'] ?? 'save');
        try {
            $db->begin_transaction();
            if ($action === 'edit_details') {
                inv_doc_save_details($db, $cfg, $docId);
                $db->commit();
                sales_flash_redirect($self . '?id=' . $docId, 'success', 'Details saved.');
            }
            $old = null;
            $touched = [];
            if ($docId > 0) {
                $old = sales_row($db, 'SELECT * FROM inv_docs WHERE doc_id=? AND doc_type=? FOR UPDATE', 'is', [$docId, $type]);
                if (!$old || $old['status'] !== 'active') {
                    throw new RuntimeException('Document not found or already cancelled.');
                }
                if ($old['approval_status'] === 'approved') {
                    throw new RuntimeException($old['doc_no'] . ' is approved and can no longer be changed.');
                }
                if (!$cfg['editable']) {
                    throw new RuntimeException($old['doc_no'] . ' is final once saved. Start a new document instead.');
                }
                if (isset($cfg['locked']) && ($why = ($cfg['locked'])($db, $old)) !== '') {
                    throw new RuntimeException($why);
                }
                inv_assert_unlocked($db, $old['doc_date'], (int) $old['branch_id']);
                $child = sales_row($db, "SELECT doc_no FROM inv_docs WHERE source_doc_id=? AND status='active' AND doc_type<>? LIMIT 1", 'is', [$docId, $type]);
                if ($child) {
                    throw new RuntimeException($old['doc_no'] . ' is already used by ' . $child['doc_no'] . '. Cancel that document first.');
                }
                if ($meta['stock'] !== 'none') {
                    $touched = inv_reverse_stock($db, $docId);
                }
            }
            if ($action === 'cancel') {
                if (!$old) {
                    throw new RuntimeException('Open a saved document to cancel it.');
                }
                if (isset($cfg['on_cancel'])) {
                    ($cfg['on_cancel'])($db, $old);
                }
                sales_exec($db, "UPDATE inv_docs SET status='cancelled' WHERE doc_id=?", 'i', [$docId])->close();
                foreach ($touched as $t) {
                    if (inv_available($db, (int) $t['product_id'], (string) $t['store_name']) < -0.0005) {
                        throw new RuntimeException('Cancelling would make stock negative in ' . $t['store_name'] . ' because some of it was already issued.');
                    }
                }
                $db->commit();
                sales_flash_redirect($self . '?view=list', 'success', $old['doc_no'] . ' cancelled.');
            }

            $date = sales_valid_date($_POST['doc_date'] ?? '');
            if ($date === null) {
                throw new RuntimeException('Enter a valid date.');
            }
            $docBranch = $old ? (int) $old['branch_id'] : $branchId;
            inv_assert_unlocked($db, $date, $docBranch);
            $h = [
                'ref_no' => sales_post_string('ref_no', 80), 'party_group' => sales_post_string('party_group', 120), 'party_name' => sales_post_string('party_name', 190),
                'mobile' => sales_post_string('mobile', 40), 'address' => sales_post_string('address', 255), 'attention' => sales_post_string('attention', 190),
                'sales_person' => sales_post_string('sales_person', 140), 'delivery_point' => sales_post_string('delivery_point', 190), 'narration' => sales_post_string('narration', 500),
                'payment_term' => sales_post_string('payment_term', 2000),
            ];
            $partyId = (int) ($_POST['party_id'] ?? 0);
            $billType = ($_POST['bill_type'] ?? '') === 'Cash' ? 'Cash' : 'Credit';
            $required = sales_valid_date($_POST['required_date'] ?? '', true);
            if ($required === null) {
                throw new RuntimeException('Enter a valid required date or leave it blank.');
            }
            $usesOrderDate = in_array('order_date', array_column($cfg['header'], 0), true);
            $orderDate = $usesOrderDate ? sales_valid_date($_POST['order_date'] ?? '', true) : '';
            if ($orderDate === null) {
                throw new RuntimeException('Enter a valid order date or leave it blank.');
            }
            if ($meta['party'] !== 'none' && $h['party_name'] === '') {
                throw new RuntimeException('Enter the ' . ($meta['party'] === 'supplier' ? 'supplier' : 'customer') . ' name.');
            }

            // Source document.
            $source = null;
            $sourceLines = [];
            $sourceNo = sales_post_string('source_no', 40);
            if ($cfg['source'] && $sourceNo !== '') {
                $found = inv_source_lines($db, $cfg, $sourceNo, $docId);
                if (!$found) {
                    throw new RuntimeException('No ' . $cfg['source']['field'] . ' matches "' . $sourceNo . '".');
                }
                [$source, $srcRows] = $found;
                foreach ($srcRows as $r) {
                    $sourceLines[(int) $r['source_line_id']] = $r;
                }
            } elseif ($cfg['source_required']) {
                throw new RuntimeException('Enter the ' . $cfg['source']['field'] . '.');
            }

            // Lines.
            $raw = json_decode((string) ($_POST['lines_json'] ?? '[]'), true);
            if (!is_array($raw) || !$raw || count($raw) > 500) {
                throw new RuntimeException('Add at least one item.');
            }
            $stores = inv_stores($db, true); // perm_check_stores() has already held the user to their own stores
            $lines = [];
            $usedSource = [];
            foreach ($raw as $l) {
                $qty = sales_qty($l['quantity'] ?? 0);
                $free = $has('free') ? sales_qty($l['free_quantity'] ?? 0) : 0.0;
                $desc = sales_str($l['description'] ?? '', 255);
                if ($qty + $free <= 0 || $desc === '') {
                    continue;
                }
                $price = $cfg['amounts'] ? sales_amount($l['unit_price'] ?? 0) : 0.0;
                $prev = $has('prev_price') ? sales_amount($l['prev_price'] ?? 0) : 0.0;
                $disc = $has('discount') ? min(sales_amount($l['discount_amount'] ?? 0), round($qty * $price, 2)) : 0.0;
                $store = $has('store') ? sales_str($l['store_name'] ?? '', 140) : '';
                $storeTo = $has('store_to') ? sales_str($l['store_to'] ?? '', 140) : '';
                foreach (array_filter([$store, $storeTo]) as $s) {
                    if (!in_array($s, $stores, true)) {
                        throw new RuntimeException('Store "' . $s . '" does not exist. Add it to sales_stores or pick one from the list.');
                    }
                }
                $srcId = (int) ($l['source_line_id'] ?? 0);
                if ($srcId > 0) {
                    if (!isset($sourceLines[$srcId])) {
                        throw new RuntimeException('"' . $desc . '" does not belong to ' . ($source['doc_no'] ?? 'the source document') . '.');
                    }
                    $usedSource[$srcId] = ($usedSource[$srcId] ?? 0) + (($cfg['source']['mode'] ?? '') === 'bill' ? $qty : $qty + $free);
                    if (($cfg['source']['mode'] ?? 'remaining') !== 'all' && $usedSource[$srcId] > (float) $sourceLines[$srcId]['remaining'] + 0.0005) {
                        throw new RuntimeException('"' . $desc . '" is more than the ' . sales_qty_fmt(max(0, $sourceLines[$srcId]['remaining'])) . ' still open on ' . $source['doc_no'] . '.');
                    }
                } elseif ($cfg['source_required']) {
                    throw new RuntimeException('Every line must come from the ' . $cfg['source']['field'] . '.');
                }
                $serialList = $has('serials') ? inv_parse_serials($l['serials'] ?? []) : [];
                // A line taken from a document that carries serials (e.g. a challan being returned) may only use those serials.
                if ($srcId > 0 && $serialList && !empty($sourceLines[$srcId]['serials'])) {
                    $allowed = inv_parse_serials($sourceLines[$srcId]['serials']);
                    $foreign = array_values(array_diff($serialList, $allowed));
                    if ($foreign) {
                        throw new RuntimeException('Serial ' . $foreign[0] . ' is not on ' . $source['doc_no'] . ' for "' . $desc . '".');
                    }
                }
                $total = $has('prev_price') ? round($qty * ($price - $prev), 2) : max(0.0, round($qty * $price - $disc, 2));
                $lines[] = [
                    'product_id' => ((int) ($l['product_id'] ?? 0)) ?: null, 'product_code' => sales_str($l['product_code'] ?? '', 60), 'product_group' => sales_str($l['product_group'] ?? '', 140),
                    'brand' => sales_str($l['brand'] ?? '', 140), 'model_caption' => sales_str($l['model_caption'] ?? '', 190), 'description' => $desc, 'unit_name' => sales_str($l['unit_name'] ?? 'Pcs', 40) ?: 'Pcs',
                    'warranty' => sales_str($l['warranty'] ?? '', 100), 'store_name' => $store, 'store_to' => $storeTo, 'quantity' => $qty, 'free_quantity' => $free, 'unit_price' => $price,
                    'prev_price' => $prev, 'discount_amount' => $disc, 'line_total' => $total, 'narration' => sales_str($l['narration'] ?? '', 255),
                    'serials' => implode("\n", $serialList), 'source_kind' => $srcId > 0 ? (string) $sourceLines[$srcId]['source_kind'] : '', 'source_line_id' => $srcId ?: null,
                ];
            }
            if (!$lines) {
                throw new RuntimeException('Each line needs a description and a quantity greater than zero.');
            }
            $subtotal = round(array_sum(array_column($lines, 'line_total')), 2);
            $extra = in_array('extra', $cfg['totals'], true) ? sales_amount($_POST['extra_charges'] ?? 0) : 0.0;
            $discount = in_array('discount', $cfg['totals'], true) ? sales_amount($_POST['discount_amount'] ?? 0) : 0.0;
            $vat = in_array('vat', $cfg['totals'], true) ? sales_amount($_POST['vat_amount'] ?? 0) : 0.0;
            $net = $has('prev_price') ? $subtotal : max(0.0, round($subtotal + $extra + $vat - $discount, 2));
            $cash = $cfg['cash'] ? min(sales_amount($_POST['cash_received'] ?? 0), $net) : 0.0;
            $qtyTotal = round(array_sum(array_column($lines, 'quantity')) + array_sum(array_column($lines, 'free_quantity')), 3);

            $cols = $h + ['doc_date' => $date, 'party_id' => $partyId ?: null, 'bill_type' => $billType, 'required_date' => $required ?: null,
                'source_type' => $source ? $cfg['source']['type'] : '', 'source_doc_id' => $source ? (int) $source['doc_id'] : null, 'source_no' => $source ? $source['doc_no'] : '',
                'subtotal' => $subtotal, 'extra_charges' => $extra, 'discount_amount' => $discount, 'vat_amount' => $vat, 'net_amount' => $net, 'cash_received' => $cash, 'total_quantity' => $qtyTotal];
            if ($usesOrderDate) {
                $cols['order_date'] = $orderDate ?: null;
            }
            if ($cfg['additional']) {
                $cols['additional_info'] = inv_additional_from_post();
            }
            $bind = fn(array $values) => implode('', array_map(fn($v) => is_int($v) ? 'i' : (is_float($v) ? 'd' : 's'), $values));
            if ($old) {
                sales_exec($db, 'UPDATE inv_docs SET ' . implode(', ', array_map(fn($c) => "$c=?", array_keys($cols))) . ' WHERE doc_id=?', $bind($cols) . 'i', array_merge(array_values($cols), [$docId]))->close();
                sales_exec($db, 'DELETE FROM inv_doc_lines WHERE doc_id=?', 'i', [$docId])->close();
                $docNo = $old['doc_no'];
            } else {
                $docNo = inv_next_no($db, $meta['code'], $date);
                $cols += ['doc_type' => $type, 'doc_no' => $docNo, 'branch_id' => $branchId, 'party_type' => $meta['party'], 'created_by' => $userId];
                $docId = sales_insert($db, 'INSERT INTO inv_docs (' . implode(', ', array_keys($cols)) . ') VALUES (' . implode(', ', array_fill(0, count($cols), '?')) . ')', $bind($cols), array_values($cols));
            }
            foreach ($lines as $n => $l) {
                $row = ['doc_id' => $docId, 'line_no' => $n + 1] + $l;
                sales_insert($db, 'INSERT INTO inv_doc_lines (' . implode(', ', array_keys($row)) . ') VALUES (' . implode(', ', array_fill(0, count($row), '?')) . ')', $bind($row), array_values($row));
            }
            $doc = sales_row($db, 'SELECT * FROM inv_docs WHERE doc_id=?', 'i', [$docId]);
            $saved = sales_rows($db, 'SELECT * FROM inv_doc_lines WHERE doc_id=? ORDER BY line_no', 'i', [$docId]);
            if ($meta['stock'] !== 'none') {
                $label = isset($cfg['label']) ? ($cfg['label'])($doc) : $meta['title'];
                inv_apply_stock($db, $doc, $saved, $meta['stock'], $label, $touched);
            }
            if (isset($cfg['after_save'])) {
                ($cfg['after_save'])($db, $docId, $doc, $saved);
            }
            if (!(int) ($_POST['doc_id'] ?? 0) && $type !== 'challan_bill' && empty($meta['linked'])) {
                notify_approval_needed($type, $meta['title'], $docNo, $doc['party_name'] ?? '');
            }
            $db->commit();
            sales_flash_redirect($self . '?id=' . $docId, 'success', $docNo . ' saved.');
        } catch (Throwable $e) {
            try { $db->rollback(); } catch (Throwable $ignored) {}
            error_log($meta['title'] . ' save failed: ' . $e->getMessage());
            $_SESSION['inv_repost'] = $_POST;
            sales_flash_redirect($back, 'error', $e->getMessage());
        }
    }

    /* ---------- Page ---------- */
    $flash = sales_take_flash();
    $repost = ($flash['kind'] ?? '') === 'error' ? ($_SESSION['inv_repost'] ?? null) : null;
    unset($_SESSION['inv_repost']);
    if (isset($_GET['search'])) {
        $q = trim((string) $_GET['search']);
        $found = sales_row($db, 'SELECT doc_id FROM inv_docs WHERE doc_type=? AND (doc_no=? OR ref_no=?) ORDER BY doc_id DESC LIMIT 1', 'sss', [$type, $q, $q]);
        if ($found) {
            header('Location: ' . $self . '?id=' . (int) $found['doc_id'], true, 303);
            exit;
        }
        $flash = ['kind' => 'error', 'message' => 'No ' . $meta['title'] . ' matches "' . $q . '".'];
    }
    $view = (string) ($_GET['view'] ?? 'form');
    $doc = null;
    $lines = [];
    $id = (int) ($_GET['id'] ?? 0);
    if ($id > 0) {
        $doc = sales_row($db, 'SELECT * FROM inv_docs WHERE doc_id=? AND doc_type=?', 'is', [$id, $type]);
        if ($doc) {
            $lines = sales_rows($db, 'SELECT * FROM inv_doc_lines WHERE doc_id=? ORDER BY line_no', 'i', [$id]);
        }
    }
    $prev = sales_row($db, 'SELECT doc_id FROM inv_docs WHERE doc_type=? AND doc_id<? ORDER BY doc_id DESC LIMIT 1', 'si', [$type, $doc ? (int) $doc['doc_id'] : PHP_INT_MAX]);
    $next = $doc ? sales_row($db, 'SELECT doc_id FROM inv_docs WHERE doc_type=? AND doc_id>? ORDER BY doc_id LIMIT 1', 'si', [$type, (int) $doc['doc_id']]) : null;
    $lockReason = $doc && $doc['status'] === 'active' && isset($cfg['locked']) ? ($cfg['locked'])($db, $doc) : '';
    $readOnly = $doc && ($doc['status'] !== 'active' || $doc['approval_status'] === 'approved' || !$cfg['editable'] || $lockReason !== '');
    $h = $repost ?: ($doc ?: []);
    if ($repost && $cfg['additional']) {
        $h['additional_info'] = inv_additional_from_post($repost);
    }
    if ($repost) {
        $lines = json_decode((string) ($repost['lines_json'] ?? '[]'), true) ?: [];
    }
    foreach ($lines as &$l) {
        $l['serials'] = inv_parse_serials($l['serials'] ?? '');
    }
    unset($l);

    inv_page_start($db, $meta['title'], $cfg['active']);
    echo sales_flash_html($flash);
    echo '<fieldset class="box"><legend>' . sales_h($meta['title']) . '</legend>';
    if ($view === 'list') {
        inv_doc_list($db, $cfg, $self);
    } else {
        inv_doc_form($db, $cfg + ['lock_reason' => $lockReason], $self, $doc, $h, $readOnly, $prev, $next);
        if (isset($cfg['panels'])) {
            ($cfg['panels'])($db, $cfg, $self, $doc, (bool) $readOnly);
        }
    }
    echo '</fieldset>';
    $js = $view === 'list' ? '' : 'window.INV=' . sales_js([
        'type' => $type, 'self' => $self, 'party' => $meta['party'], 'stock' => $meta['stock'], 'lines' => $cfg['lines'], 'amounts' => $cfg['amounts'],
        'totals' => $cfg['totals'], 'cash' => $cfg['cash'], 'lastInfo' => $cfg['last_info'], 'source' => $cfg['source'], 'readOnly' => $readOnly, 'docId' => (int) ($doc['doc_id'] ?? 0),
        'products' => inv_products($db), 'parties' => inv_doc_parties($db, $meta['party']), 'stores' => inv_stores($db), 'stores_to' => inv_stores($db, true),
        'defaultStore' => inv_setting($db, 'default_store', ''), 'items' => $lines,
    ]) . ';';
    echo '</main></div><script src="../sales/sales.js"></script>' . ($js ? '<script>' . $js . '</script><script src="../inventory/inv_doc.js"></script><script src="../doc_ui.js?v=' . (int) @filemtime(dirname(__DIR__) . '/doc_ui.js') . '"></script>' : '') . '</body></html>';
}

function inv_doc_form(mysqli $db, array $cfg, $self, $doc, array $h, $readOnly, $prev, $next)
{
    $meta = INV_TYPES[$cfg['type']];
    $val = fn($k, $d = '') => sales_h($h[$k] ?? $d);
    $has = fn($f) => in_array($f, $cfg['lines'], true);
    $btn = fn($b) => in_array($b, $cfg['buttons'], true);
    $card = fn($step, $title, $text) => '<header class="sd-head"><span class="sd-num"><span>' . $step . '</span></span><div><h3>' . sales_h($title) . '</h3><p>' . sales_h($text) . '</p></div></header>';
    $party = $meta['party'] === 'supplier' ? 'Supplier' : ($meta['party'] === 'customer' ? 'Customer' : '');
    // Layout: doc_layout.css (header bar, cards in the main column, summary column); doc_ui.js fills the summary.
    echo '<form method="post" id="docform" class="si si-inv" autocomplete="off">' . sales_csrf_field() . '<input type="hidden" name="doc_id" value="' . (int) ($doc['doc_id'] ?? 0) . '"><input type="hidden" name="lines_json" id="lines_json"><input type="hidden" name="party_id" id="party_id" value="' . $val('party_id') . '">';

    // Header bar: what this is, its number and state, and moving between records.
    $state = !$doc ? ['New', 'new'] : ($doc['status'] === 'cancelled' ? ['Cancelled', 'void'] : ($doc['approval_status'] === 'approved' ? ['Approved', 'ok'] : ['Pending approval', 'wait']));
    $link = fn($id) => $id ? 'href="' . $self . '?id=' . (int) $id['doc_id'] . '"' : 'aria-disabled="true" style="opacity:.4"';
    echo '<header class="si-hero"><div class="si-hero-main"><span class="si-hero-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="' . ($meta['party'] === 'supplier' ? 'M3 4h2l2.4 11h11L21 8H6.2M9 19h.01M17 19h.01' : 'M6 3h12v18l-3-2-3 2-3-2-3 2zM9 8h6M9 12h6M9 16h3') . '"/></svg></span><div>'
        . '<h2>' . sales_h($meta['title']) . '</h2><p class="si-meta"><b>' . sales_h($doc['doc_no'] ?? inv_peek_no($db, $meta['code'])) . '</b><span class="si-status si-' . $state[1] . '">' . $state[0] . '</span>'
        . '<span>' . sales_h(inv_branch_name($db, $doc['branch_id'] ?? inv_current_branch($db))) . '</span>'
        . ($doc ? '<span>' . sales_h(date('d M Y', strtotime($doc['doc_date']))) . '</span>' : '')
        . (($cfg['lock_reason'] ?? '') !== '' ? '<span class="si-lock">' . sales_h($cfg['lock_reason']) . '</span>' : '')
        . ($readOnly && $doc && $doc['status'] === 'active' && $doc['approval_status'] !== 'approved' ? '<span>Saved documents of this type cannot be edited</span>' : '')
        . (!empty($doc['sales_invoice_id']) ? '<span><a target="_blank" href="../sales/sales_invoice/sales_invoice.php?print=' . (int) $doc['sales_invoice_id'] . '">Sales invoice</a></span>' : '')
        . '</p></div></div><div class="si-hero-tools"><div class="sd-group" role="group" aria-label="Record">'
        . '<a class="btn" href="' . $self . '">New</a><a class="btn" href="' . $self . '?view=list">List View</a>'
        . '<a class="btn round" title="Previous" ' . $link($prev) . '>&#9664;</a><a class="btn round" title="Next" ' . $link($next) . '>&#9654;</a></div></div></header>';

    echo '<div class="si-layout"><div class="si-main">';

    // 1. Party and document details.
    echo '<section class="sd-card" data-step="1">' . $card(1, $party !== '' ? $party . ' and details' : 'Details', $party !== '' ? 'Type the ' . strtolower($party) . ' name and pick from the list; check the date and reference.' : 'Check the date and reference.');
    echo '<div class="hgrid">';
    echo '<div class="field"><label for="ref_no">Ref No</label><input id="ref_no" name="ref_no" maxlength="80" value="' . $val('ref_no') . '"></div>';
    echo '<div class="field"><label for="doc_date">' . sales_h($cfg['date_label']) . '</label><input id="doc_date" type="date" name="doc_date" value="' . $val('doc_date', date('Y-m-d')) . '" required></div>';
    foreach ($cfg['header'] as [$name, $label, $kind]) {
        $span = in_array($name, ['address', 'narration', 'payment_term'], true) ? ' s6' : ($name === 'party_name' ? ' s6' : '');
        echo '<div class="field' . $span . '"><label for="' . $name . '">' . sales_h($label) . '</label>';
        if ($kind === 'mobile') {
            echo '<div class="inline"><input id="mobile" name="mobile" maxlength="40" placeholder="Mobile Number" value="' . $val('mobile') . '"><button type="button" class="btn small" id="mobile-search">Search</button></div>';
        } elseif ($kind === 'party') {
            echo '<div class="relative"><input id="party_name" name="party_name" maxlength="190" value="' . $val('party_name') . '" placeholder="' . sales_h($label) . '"><div class="suggestions" id="party-suggestions" hidden></div></div>';
        } elseif ($kind === 'select') {
            echo '<select id="' . $name . '" name="' . $name . '">';
            foreach ($cfg['header_options'][$name] ?? [] as $opt) {
                echo '<option' . (($h[$name] ?? '') === $opt ? ' selected' : '') . '>' . sales_h($opt) . '</option>';
            }
            echo '</select>';
        } elseif ($kind === 'date') {
            echo '<input id="' . $name . '" type="date" name="' . $name . '" value="' . $val($name) . '">';
        } elseif ($kind === 'textarea') {
            echo '<textarea id="' . $name . '" name="' . $name . '" maxlength="2000">' . $val($name) . '</textarea>';
        } elseif ($kind === 'sourceinfo') {
            $srcOf = $doc && $doc['source_doc_id'] ? sales_row($db, 'SELECT source_no FROM inv_docs WHERE doc_id=?', 'i', [(int) $doc['source_doc_id']]) : null;
            echo '<input id="' . $name . '" value="' . sales_h($srcOf['source_no'] ?? '') . '" readonly tabindex="-1">';
        } else {
            echo '<input id="' . $name . '" name="' . $name . '" maxlength="190" value="' . $val($name) . '" placeholder="' . sales_h($label) . '">';
        }
        echo '</div>';
    }
    if ($cfg['source']) {
        $src = $cfg['source'];
        echo '<div class="field"><label for="source_mode">Type</label><select id="source_mode"><option value="source"' . (($h['source_no'] ?? '') !== '' || $cfg['source_required'] ? ' selected' : '') . '>' . sales_h($src['label']) . '</option>' . ($cfg['source_required'] ? '' : '<option value="direct"' . (($doc && ($h['source_no'] ?? '') === '') ? ' selected' : '') . '>Direct</option>') . '</select></div>';
        echo '<div class="field"><label for="source_no">' . sales_h($src['field']) . '</label><div class="inline"><input id="source_no" name="source_no" maxlength="40" value="' . $val('source_no') . '"><button type="button" class="btn small" id="source-load"' . ($readOnly ? ' disabled' : '') . '>Load</button></div></div>';
    }
    echo '<div class="field"><label for="search_no">' . sales_h($cfg['search_label']) . '</label><div class="inline"><input id="search_no" value="' . sales_h($doc['doc_no'] ?? inv_peek_no($db, $meta['code'])) . '"><button type="button" class="btn small" onclick="location.href=\'' . $self . '?search=\'+encodeURIComponent(document.getElementById(\'search_no\').value)">Search</button></div></div>';
    if ($btn('payment_term')) {
        echo '<div class="field s12" id="payment-term-box"' . (($h['payment_term'] ?? '') === '' ? ' hidden' : '') . '><label for="payment_term">Payment Term</label><textarea id="payment_term" name="payment_term" maxlength="2000">' . $val('payment_term') . '</textarea></div>';
    }
    echo '</div></section>';

    // 3. Product entry: the fields used every time, then the Add button.
    echo '<section class="sd-card entry product-form" data-step="3">' . $card(3, 'Add products', 'Search the model' . ($has('store') ? ', pick the store' : '') . ', enter the quantity' . ($cfg['amounts'] ? ' and price' : '') . ', then press Add. Repeat for every product.');
    echo '<div class="hgrid">';
    echo '<div class="field s6"><label for="e_model">Model No/Caption</label><div class="relative"><input id="e_model" placeholder="Search model, code or name"><div class="suggestions" id="product-suggestions" hidden></div></div></div>';
    if ($has('store')) {
        echo '<div class="field"><label for="e_store">' . ($has('store_to') ? 'Store From' : 'Store') . '</label><select id="e_store"></select></div>';
    }
    if ($has('store_to')) {
        echo '<div class="field"><label for="e_store_to">Store To</label><select id="e_store_to"></select></div>';
    }
    echo '<div class="field s2"><label for="e_qty">Quantity</label><input id="e_qty" type="number" min="0" step="0.001"></div>';
    if ($has('free')) {
        echo '<div class="field s2"><label for="e_free">Free Qty</label><input id="e_free" type="number" min="0" step="0.001" value="0"></div>';
    }
    if ($has('prev_price')) {
        echo '<div class="field s2"><label for="e_prev">Previous Cost</label><input id="e_prev" type="number" step="0.01" readonly></div>';
    }
    if ($cfg['amounts']) {
        echo '<div class="field s2"><label for="e_price">' . ($has('prev_price') ? 'Current Cost' : 'Unit Price') . '</label><input id="e_price" type="number" min="0" step="0.01"></div>';
    }
    if ($has('discount')) {
        echo '<div class="field s2"><label for="e_disc">Discount</label><input id="e_disc" type="number" min="0" step="0.01" value="0"></div>';
    }
    if ($has('serials')) {
        echo '<div class="field s4"><label for="e_serial">Serial/IMEI No. <span class="muted">(one per line or comma)</span></label><textarea id="e_serial"></textarea></div>';
    }
    echo '<div class="field s2 sd-add"><label>&nbsp;</label><button type="button" class="btn primary" id="add-line"' . ($readOnly ? ' disabled' : '') . '>Add</button></div>';
    if ($btn('add_all')) {
        echo '<div class="field s2 sd-add"><label>&nbsp;</label><button type="button" class="btn" id="add-all"' . ($readOnly ? ' disabled' : '') . '>Add All</button></div>';
    }
    echo '</div>';
    echo '<div class="sd-chips" aria-live="polite"><span class="qty-info" id="qty-available"></span><span class="muted" id="cost-info"></span></div>';
    echo '<details class="sd-more"><summary>Product details' . ($has('serials') ? ' and serial options' : '') . ' <small>(filled from the product; change only if needed)</small></summary><div class="hgrid sd-details">';
    echo '<div class="field"><label for="e_group">Product Group</label><input id="e_group"></div><div class="field"><label for="e_brand">Brand</label><input id="e_brand"></div>';
    echo '<div class="field s6"><label for="e_desc">Description</label><input id="e_desc"></div>';
    if ($has('warranty')) {
        echo '<div class="field"><label for="e_warranty">Warranty</label><input id="e_warranty"></div>';
    }
    if ($has('narration')) {
        echo '<div class="field"><label for="e_narr">' . sales_h($cfg['narration_label']) . '</label><input id="e_narr"></div>';
    }
    echo '<div class="field"><label for="e_unit">Unit</label><input id="e_unit" value="Pcs"></div>';
    echo '</div>';
    if ($has('serials')) {
        echo '<div class="radios sd-serial"><label><input type="radio" name="sq" value="none" checked> Sequence Not Available</label><label><input type="radio" name="sq" value="range"> Sequence Available</label><label><input type="radio" name="sq" value="list"> Bulk Qty</label>'
            . ($has('auto_serial') ? '<label><input type="radio" name="sq" value="auto"> A.G Serial</label>' : '')
            . '<label><input class="check" type="checkbox" id="serial_clear" checked> Serial Clear</label><label><input class="check" type="checkbox" id="digit_check"> Digit Check</label><input id="digit_len" type="number" min="1" max="60" style="width:70px" title="Serial length">'
            . '<label>Serial Common <input id="s_common" class="sm"></label><label>From <input id="s_from" class="sm"></label><label>To <input id="s_to" class="sm"></label></div>';
    }
    echo '</details></section>';

    // Items: the list and the buttons that work on it.
    echo '<section class="sd-card"><header class="sd-head sd-head-bar"><div><h3>Items <span class="si-count" id="si_count_head"></span></h3><p>' . ($doc ? 'Saved lines of ' . sales_h($doc['doc_no']) . '.' : 'Products you add appear here.') . '</p></div><div class="sd-tools">';
    $change = '';
    if ($btn('edit_price_inline') && $cfg['amounts']) {
        $change .= '<button type="button" class="btn" id="edit-price"' . ($readOnly ? ' disabled' : '') . ' title="Change unit prices and discounts in the grid, then click ' . ($doc ? 'Edit' : 'Save') . '">Edit Price</button>';
    }
    if ($btn('edit_details')) {
        $change .= '<button class="btn" name="action" value="edit_details" formnovalidate' . ($doc && $doc['status'] === 'active' ? '' : ' disabled') . ' title="Save the header fields only (customer, address, narration...)">Edit Details</button>';
    }
    if ($btn('import')) {
        $change .= '<label class="btn" style="cursor:pointer"' . ($readOnly ? ' hidden' : '') . ' title="CSV columns: product code or model, quantity' . ($cfg['amounts'] ? ', unit price' : '') . ($has('serials') ? ', serials separated by |' : '') . '">Import<input type="file" id="import-file" accept=".csv,text/csv" hidden></label>';
    }
    if ($change !== '') {
        echo '<div class="sd-group" role="group" aria-label="Change">' . $change . '</div>';
    }
    if ($doc) {
        echo '<div class="sd-group" role="group" aria-label="Print"><a class="btn" target="_blank" href="' . $self . '?print=' . (int) $doc['doc_id'] . '">Preview</a><a class="btn" target="_blank" href="' . $self . '?print=' . (int) $doc['doc_id'] . '&auto=1">' . sales_h($cfg['print_label']) . '</a>'
            . '<a class="btn" target="_blank" href="' . $self . '?print=' . (int) $doc['doc_id'] . '&custom=1">' . sales_h($cfg['custom_label']) . '</a>'
            . ($btn('barcode_product') ? '<a class="btn" target="_blank" href="' . $self . '?barcode=' . (int) $doc['doc_id'] . '&mode=product">Barcode</a>' : '')
            . ($btn('barcode') ? '<a class="btn" target="_blank" href="' . $self . '?barcode=' . (int) $doc['doc_id'] . '">Barcode Serial</a>' : '')
            . ($btn('edit_price') && $doc['status'] === 'active' ? '<a class="btn" href="../amedment/purchase_price_amendment.php?source_no=' . urlencode($doc['doc_no']) . '" title="Change the cost of this saved purchase">Edit Price</a>' : '') . '</div>';
    }
    $more = '';
    if ($cfg['additional']) {
        $more .= '<button type="button" class="btn" onclick="const b=document.getElementById(\'additional-info\');b.hidden=!b.hidden">Additional Info</button>';
    }
    if ($btn('serial_view')) {
        $more .= '<button type="button" class="btn" id="serial-view">Serial View</button>';
    }
    if ($btn('show_cost')) {
        $more .= '<button type="button" class="btn" id="show-cost">Show Cost</button>';
    }
    if ($btn('show_price')) {
        $more .= '<button type="button" class="btn" id="show-price">Show Price</button>';
    }
    if ($cfg['totals']) {
        $more .= '<button type="button" class="btn" onclick="(document.getElementById(\'t_extra\')||document.getElementById(\'t_discount\'))?.focus()">' . sales_h($cfg['totals_label']) . '</button>';
    }
    if ($btn('extra_charges') && in_array('extra', $cfg['totals'], true)) {
        $more .= '<button type="button" class="btn" onclick="const e=document.getElementById(\'t_extra\');e.focus();e.select()">Additional Extra Charges</button>';
    }
    if ($btn('payment_term')) {
        $more .= '<button type="button" class="btn" onclick="const b=document.getElementById(\'payment-term-box\');b.hidden=false;document.getElementById(\'payment_term\').focus()">Payment Term</button>';
    }
    foreach ($cfg['jump'] as [$target, $label]) {
        $more .= '<button type="button" class="btn" onclick="document.getElementById(\'' . sales_h($target) . '\')?.scrollIntoView({behavior:\'smooth\'})"' . ($doc ? '' : ' disabled title="Save first"') . '>' . sales_h($label) . '</button>';
    }
    if ($more !== '') {
        echo '<div class="sd-group" role="group" aria-label="More">' . $more . '</div>';
    }
    echo '</div></header>';
    if ($cfg['additional']) {
        $info = json_decode((string) ($h['additional_info'] ?? ''), true) ?: [];
        echo '<section class="section" id="additional-info"' . ($info ? '' : ' hidden') . '><div class="section-title">Additional Info</div><div class="hgrid">';
        foreach (INV_ADDITIONAL as $k => $label) {
            echo '<div class="field' . (in_array($k, ['remarks', 'installation_address'], true) ? ' s6' : '') . '"><label for="add_' . $k . '">' . sales_h($label) . '</label><input id="add_' . $k . '" name="add_' . $k . '" maxlength="255" value="' . sales_h($info[$k] ?? '') . '"></div>';
        }
        echo '</div></section>';
    }
    echo '<div class="table-scroll grid-box"><table><thead><tr id="grid-head"></tr></thead><tbody id="grid-body"></tbody></table></div>';
    echo '</section>';
    echo '</div>';

    // Summary column: the amount, payment, Save.
    echo '<aside class="si-side"><section class="si-sum" aria-label="Summary"><div class="si-sum-top"><span data-label="' . ($has('prev_price') ? 'New amount' : 'Net payable') . '">' . ($has('prev_price') ? 'New amount' : 'Net payable') . '</span>'
        . '<b><small>' . sales_h(bs_currency_symbol()) . '</small> <span id="si_net">0.00</span></b><p><span id="si_count">0</span> item(s) &middot; <span id="si_qty">0</span> qty<span id="si_change_wrap" hidden> &middot; change <span id="si_change">0.00</span></span></p></div>'
        . '<table class="stotals" id="totals"></table></section>';
    if ($cfg['cash']) {
        echo '<section class="sd-card si-pay" data-step="4">' . $card(4, 'Payment', 'Enter the amount ' . ($meta['party'] === 'supplier' ? 'paid' : 'received') . ' now, if any.') . '<div class="hgrid si-paygrid">';
        if ($cfg['payment_date']) {
            echo '<div class="field"><label for="required_date">Payment Date</label><input id="required_date" name="required_date" type="date" value="' . $val('required_date') . '"' . ($readOnly ? ' readonly' : '') . '></div>'
                . '<div class="field sd-end"><label>&nbsp;</label><button type="button" class="btn" onclick="const d=document.getElementById(\'required_date\');d.showPicker?d.showPicker():d.focus()"' . ($readOnly ? ' disabled' : '') . '>Payment Date</button></div>';
        }
        echo '<div class="field"><label for="t_cash">' . sales_h($cfg['cash_label']) . '</label><input id="t_cash" name="cash_received" type="number" min="0" step="0.01" value="' . $val('cash_received', '0') . '"' . ($readOnly ? ' readonly' : '') . '></div>';
        echo (isset($cfg['cash_extra']) ? '<div class="field s12 si-cash-extra">' . ($cfg['cash_extra'])($doc) . '</div>' : '');
        echo '</div>' . ($cfg['cash_change'] ? '<p class="si-change">Change Amount: <b id="change-amount">0.00</b></p>' : '') . '</section>';
    }
    echo '<div class="si-actions" data-step="5"><button class="btn primary si-save" id="save-btn" name="action" value="save"' . ($readOnly ? ' disabled' : '') . '>' . ($doc ? 'Edit' : 'Save') . '</button><div class="si-actions-row"><a class="btn" href="javascript:history.back()">Close</a>';
    if ($doc && !$readOnly && $cfg['editable']) {
        echo '<button class="btn danger" name="action" value="cancel" formnovalidate onclick="return confirm(\'Cancel ' . sales_h($doc['doc_no']) . '?\')">Cancel Document</button>';
    }
    echo '</div><p class="si-hint">' . ($doc ? 'Edit saves your changes to ' . sales_h($doc['doc_no']) . '.' : 'Save first; Print and Preview open after saving.') . '</p></div></aside></div>';

    foreach (['extra' => 'extra_charges', 'discount' => 'discount_amount', 'vat' => 'vat_amount'] as $k => $col) {
        if (in_array($k, $cfg['totals'], true)) {
            echo '<input type="hidden" name="' . $col . '" id="h_' . $k . '" value="' . $val($col, '0') . '">';
        }
    }
    echo '</form>';
}

/**
 * Edit Details: save the header fields of a saved, active document (also when approved or used later),
 * without touching its lines, stock or amounts.
 */
function inv_doc_save_details(mysqli $db, array $cfg, $docId)
{
    $doc = sales_row($db, 'SELECT * FROM inv_docs WHERE doc_id=? AND doc_type=? FOR UPDATE', 'is', [(int) $docId, $cfg['type']]);
    if (!$doc || $doc['status'] !== 'active') {
        throw new RuntimeException('Open a saved, active document to edit its details.');
    }
    $limits = ['ref_no' => 80, 'party_group' => 120, 'party_name' => 190, 'mobile' => 40, 'address' => 255, 'attention' => 190, 'sales_person' => 140, 'delivery_point' => 190, 'narration' => 500, 'payment_term' => 2000];
    $cols = ['ref_no' => sales_post_string('ref_no', 80)];
    foreach ($cfg['header'] as [$name, , $kind]) {
        if (isset($limits[$name])) {
            $cols[$name] = sales_post_string($name, $limits[$name]);
        } elseif ($kind === 'date' && in_array($name, ['required_date', 'order_date'], true)) {
            $d = sales_valid_date($_POST[$name] ?? '', true);
            if ($d === null) {
                throw new RuntimeException('Enter a valid date or leave it blank.');
            }
            $cols[$name] = $d ?: null;
        }
    }
    if (INV_TYPES[$cfg['type']]['party'] !== 'none' && array_key_exists('party_name', $cols) && $cols['party_name'] === '') {
        throw new RuntimeException('Enter the ' . (INV_TYPES[$cfg['type']]['party'] === 'supplier' ? 'supplier' : 'customer') . ' name.');
    }
    if (isset($_POST['party_id'])) {
        $cols['party_id'] = ((int) $_POST['party_id']) ?: null;
    }
    if ($cfg['additional']) {
        $cols['additional_info'] = inv_additional_from_post();
    }
    $types = implode('', array_map(fn($v) => is_int($v) ? 'i' : 's', $cols));
    sales_exec($db, 'UPDATE inv_docs SET ' . implode(', ', array_map(fn($c) => "$c=?", array_keys($cols))) . ' WHERE doc_id=?', $types . 'i', array_merge(array_values($cols), [(int) $docId]))->close();
    if (isset($cfg['after_details'])) {
        ($cfg['after_details'])($db, sales_row($db, 'SELECT * FROM inv_docs WHERE doc_id=?', 'i', [(int) $docId]));
    }
}

function inv_doc_list(mysqli $db, array $cfg, $self)
{
    $from = sales_valid_date($_GET['from'] ?? '', true) ?: date('Y-m-01');
    $to = sales_valid_date($_GET['to'] ?? '', true) ?: date('Y-m-d');
    $q = trim((string) ($_GET['q'] ?? ''));
    $like = '%' . $q . '%';
    $rows = sales_list_rows($db, 'SELECT * FROM inv_docs WHERE doc_type=? AND doc_date BETWEEN ? AND ? AND (doc_no LIKE ? OR ref_no LIKE ? OR party_name LIKE ? OR mobile LIKE ? OR source_no LIKE ?)' . perm_branch_sql('branch_id') . ' ORDER BY doc_date DESC, doc_id DESC', 'ssssssss', [$cfg['type'], $from, $to, $like, $like, $like, $like, $like]);
    echo '<form method="get" class="list-search"><input type="hidden" name="view" value="list"><input class="input" type="date" name="from" value="' . sales_h($from) . '" style="max-width:160px"><input class="input" type="date" name="to" value="' . sales_h($to) . '" style="max-width:160px"><input class="input" name="q" value="' . sales_h($q) . '" placeholder="No, ref, name, mobile or source"><button class="btn primary">Search</button><a class="btn" href="' . $self . '">New</a></form>';
    echo '<div class="table-scroll" style="max-height:620px"><table><thead><tr><th>SL</th><th>No</th><th>Date</th><th>Ref No</th><th>Name</th><th>Mobile</th><th>Source</th><th class="num">Qty</th>' . ($cfg['amounts'] ? '<th class="num">Net Amount</th>' : '') . '<th>Status</th><th>Select</th><th>Print</th></tr></thead><tbody>';
    if (!$rows) {
        echo '<tr><td colspan="12" class="empty">Nothing in this period.</td></tr>';
    }
    foreach ($rows as $i => $r) {
        $status = $r['status'] === 'cancelled' ? '<span class="badge cancelled">Cancelled</span>' : ($r['approval_status'] === 'approved' ? '<span class="badge converted">Approved</span>' : '<span class="badge open">Pending</span>');
        echo '<tr><td>' . ($i + 1) . '</td><td>' . sales_h($r['doc_no']) . '</td><td>' . inv_fmt_date($r['doc_date']) . '</td><td>' . sales_h($r['ref_no']) . '</td><td>' . sales_h($r['party_name']) . '</td><td>' . sales_h($r['mobile']) . '</td><td>' . sales_h($r['source_no']) . '</td><td class="num">' . sales_qty_fmt($r['total_quantity']) . '</td>'
            . ($cfg['amounts'] ? '<td class="num">' . sales_money($r['net_amount']) . '</td>' : '') . '<td>' . $status . '</td><td><a class="btn small" href="' . $self . '?id=' . (int) $r['doc_id'] . '">Select</a></td><td><a class="btn small" target="_blank" href="' . $self . '?print=' . (int) $r['doc_id'] . '">Print</a></td></tr>';
    }
    echo '</tbody></table></div>';
}

function inv_doc_print(mysqli $db, array $cfg, array $doc, array $lines, $custom)
{
    $amounts = $cfg['amounts'];
    $has = fn($f) => in_array($f, $cfg['lines'], true);
    $title = $cfg['print_title'];
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>' . sales_h($doc['doc_no']) . '</title><style>' . INV_PRINT_CSS . '.hide-price .price,.hide-serial .serials,.hide-store .store{display:none}</style></head><body' . (!empty($_GET['auto']) ? ' onload="window.print()"' : '') . '>';
    echo '<div class="bar"><button onclick="window.print()">Print</button>';
    if ($custom) {
        echo ' <label><input type="checkbox" onchange="document.body.classList.toggle(\'hide-price\',!this.checked)" checked> Prices</label> <label><input type="checkbox" onchange="document.body.classList.toggle(\'hide-serial\',!this.checked)" checked> Serials</label> <label><input type="checkbox" onchange="document.body.classList.toggle(\'hide-store\',!this.checked)" checked> Store</label>';
    }
    echo '</div>' . inv_print_head($db, $title);
    if ($doc['status'] === 'cancelled') {
        echo '<p style="text-align:center;color:#a33;font-weight:bold">CANCELLED</p>';
    }
    $meta = ['No' => $doc['doc_no'], 'Date' => inv_fmt_date($doc['doc_date']), 'Ref No' => $doc['ref_no'], 'Branch' => inv_branch_name($db, $doc['branch_id'])];
    if ($doc['party_name'] !== '') {
        $meta[INV_TYPES[$doc['doc_type']]['party'] === 'supplier' ? 'Supplier' : (INV_TYPES[$doc['doc_type']]['party'] === 'customer' ? 'Customer' : 'Particulars')] = $doc['party_name'] . ($doc['party_group'] ? ' (' . $doc['party_group'] . ')' : '');
    }
    foreach (['mobile' => 'Mobile', 'address' => 'Address', 'attention' => 'Attention', 'sales_person' => 'Sales Person', 'delivery_point' => 'Delivery Point', 'source_no' => 'Source', 'narration' => 'Narration'] as $k => $label) {
        if ((string) $doc[$k] !== '') {
            $meta[$label] = $doc[$k];
        }
    }
    echo '<div class="meta">';
    foreach ($meta as $k => $v) {
        echo '<div><b>' . sales_h($k) . ':</b> ' . sales_h($v) . '</div>';
    }
    echo '</div><table><thead><tr><th>SL</th><th>Model No/Caption</th><th>Description</th>' . ($has('store') ? '<th class="store">' . ($has('store_to') ? 'Store From' : 'Store') . '</th>' : '') . ($has('store_to') ? '<th class="store">Store To</th>' : '')
        . '<th class="num">Qty</th>' . ($has('free') ? '<th class="num">Free</th>' : '') . '<th>Unit</th>' . ($has('prev_price') ? '<th class="num price">Previous Cost</th>' : '') . ($amounts ? '<th class="num price">' . ($has('prev_price') ? 'Current Cost' : 'Unit Price') . '</th>' : '') . ($has('discount') ? '<th class="num price">Discount</th>' : '') . ($amounts ? '<th class="num price">' . ($has('prev_price') ? 'Variation' : 'Amount') . '</th>' : '') . '</tr></thead><tbody>';
    foreach ($lines as $i => $l) {
        $serials = inv_parse_serials($l['serials']);
        echo '<tr><td>' . ($i + 1) . '</td><td>' . sales_h(trim($l['product_code'] . ' ' . $l['model_caption'])) . '</td><td>' . sales_h($l['description']) . ($l['warranty'] ? '<br><span class="muted">Warranty: ' . sales_h($l['warranty']) . '</span>' : '') . ($l['narration'] ? '<br><span class="muted">' . sales_h($l['narration']) . '</span>' : '') . ($serials ? '<div class="serials">S/N: ' . sales_h(implode(', ', $serials)) . '</div>' : '') . '</td>'
            . ($has('store') ? '<td class="store">' . sales_h($l['store_name']) . '</td>' : '') . ($has('store_to') ? '<td class="store">' . sales_h($l['store_to']) . '</td>' : '') . '<td class="num">' . sales_qty_fmt($l['quantity']) . '</td>' . ($has('free') ? '<td class="num">' . sales_qty_fmt($l['free_quantity']) . '</td>' : '') . '<td>' . sales_h($l['unit_name']) . '</td>'
            . ($has('prev_price') ? '<td class="num price">' . sales_money($l['prev_price']) . '</td>' : '') . ($amounts ? '<td class="num price">' . sales_money($l['unit_price']) . '</td>' : '') . ($has('discount') ? '<td class="num price">' . sales_money($l['discount_amount']) . '</td>' : '') . ($amounts ? '<td class="num price">' . sales_money($l['line_total']) . '</td>' : '') . '</tr>';
    }
    echo '</tbody></table>';
    echo '<table class="totals">';
    if ($has('prev_price')) {
        $prevTotal = array_sum(array_map(fn($l) => (float) $l['quantity'] * (float) $l['prev_price'], $lines));
        $newTotal = array_sum(array_map(fn($l) => (float) $l['quantity'] * (float) $l['unit_price'], $lines));
        echo '<tr><td>Previous Amount</td><td class="num">' . sales_money($prevTotal) . '</td></tr><tr><td>New Amount</td><td class="num">' . sales_money($newTotal) . '</td></tr><tr><td><b>Variation Amount</b></td><td class="num"><b>' . sales_money($newTotal - $prevTotal) . '</b></td></tr>';
    } elseif ($amounts) {
        echo '<tr class="price"><td>Total Amount</td><td class="num">' . sales_money($doc['subtotal']) . '</td></tr>';
        foreach (['extra' => ['extra_charges', 'Add Extra Charges'], 'discount' => ['discount_amount', 'Less Discount'], 'vat' => ['vat_amount', 'Add VAT & AIT']] as $k => [$col, $label]) {
            if (in_array($k, $cfg['totals'], true)) {
                echo '<tr class="price"><td>' . $label . '</td><td class="num">' . sales_money($doc[$col]) . '</td></tr>';
            }
        }
        echo '<tr class="price"><td><b>Net Payable Amount</b></td><td class="num"><b>' . sales_money($doc['net_amount']) . '</b></td></tr>';
        if ($cfg['cash']) {
            echo '<tr class="price"><td>Cash Received</td><td class="num">' . sales_money($doc['cash_received']) . '</td></tr>';
        }
    }
    echo '<tr><td>Total Quantity</td><td class="num">' . sales_qty_fmt($doc['total_quantity']) . '</td></tr></table>';
    if (!empty($doc['payment_term'])) {
        echo '<p><b>Payment Term:</b><br>' . nl2br(sales_h($doc['payment_term'])) . '</p>';
    }
    echo '<div class="sign"><div>Prepared By</div><div>Checked By</div><div>Approved By</div><div>' . (INV_TYPES[$doc['doc_type']]['party'] === 'customer' ? 'Received By' : 'Authorised By') . '</div></div></body></html>';
}

/**
 * Barcode labels (Code 128). Mode 'serial': one per serial, or per product code when a line has no serials.
 * Mode 'product': one product code label per unit.
 */
function inv_doc_barcodes(array $doc, array $lines, $mode = 'serial')
{
    $labels = [];
    foreach ($lines as $l) {
        $serials = $mode === 'serial' ? inv_parse_serials($l['serials']) : [];
        if ($serials) {
            foreach ($serials as $s) {
                $labels[] = [$s, $l['model_caption'] ?: $l['description']];
            }
        } elseif ($l['product_code'] !== '') {
            for ($i = 0; $i < (int) ceil((float) $l['quantity']); $i++) {
                $labels[] = [$l['product_code'], $l['model_caption'] ?: $l['description']];
            }
        }
    }
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Barcodes ' . sales_h($doc['doc_no']) . '</title><style>body{font:11px Arial,sans-serif;margin:10px}.bar{margin-bottom:8px}.labels{display:flex;flex-wrap:wrap;gap:6px}.label{border:1px dashed #bbb;padding:4px 6px;text-align:center;width:200px}.label svg{max-width:100%;height:50px}@media print{.bar{display:none}.label{border:0}}</style>'
        . '<script src="https://cdnjs.cloudflare.com/ajax/libs/jsbarcode/3.11.6/JsBarcode.all.min.js"></script></head><body><div class="bar"><button onclick="window.print()">Print</button> ' . count($labels) . ' label(s) for ' . sales_h($doc['doc_no']) . '</div><div class="labels">';
    foreach ($labels as $i => [$code, $name]) {
        echo '<div class="label"><div>' . sales_h($name) . '</div><svg id="b' . $i . '" data-code="' . sales_h($code) . '"></svg><div>' . sales_h($code) . '</div></div>';
    }
    echo $labels ? '' : '<p>No serials or product codes on this document.</p>';
    echo '</div><script>document.querySelectorAll("svg[data-code]").forEach(s=>{try{JsBarcode(s,s.dataset.code,{format:"CODE128",displayValue:false,height:40,margin:0})}catch(e){}});</script></body></html>';
}
