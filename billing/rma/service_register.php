<?php
require_once __DIR__ . '/rma_report.php';

$complainBase = 'SELECT c.complain_no, c.complain_date AS doc_date, c.customer_group AS party_group, c.customer_name AS party, c.mobile, '
    . 'ci.product_group, ci.brand, ci.model_no, ci.received_serial AS serial_no, ci.problem_name, ci.engineer_name, ci.qty, ci.amount_charges, '
    . 'ci.delivery_date, ci.line_status, ci.under_warranty '
    . "FROM rma_complain_items ci JOIN rma_complains c ON c.complain_id=ci.complain_id WHERE c.status='approved'";
$outBase = 'SELECT r.replace_out_no AS doc_no, r.replace_date AS doc_date, r.supplier_group AS party_group, r.supplier_name AS party, '
    . 'ri.product_group, ri.brand, ri.model_no, ri.out_serial AS serial_no, ri.problem_name, ri.qty, ri.pre_job_ref_no, ri.line_status '
    . "FROM rma_replace_out_items ri JOIN rma_replace_outs r ON r.replace_out_id=ri.replace_out_id WHERE r.status='approved'";
$inBase = 'SELECT r.replace_in_no AS doc_no, r.replace_date AS doc_date, r.supplier_group AS party_group, r.supplier_name AS party, '
    . 'ii.out_job_ref_no, ii.out_model_no, ii.out_serial, ii.model_no, ii.serial_no, ii.qty, ii.unit_price, ii.receivable, ii.payable '
    . "FROM rma_replace_in_items ii JOIN rma_replace_ins r ON r.replace_in_id=ii.replace_in_id WHERE r.status='approved'";
$deliveryBase = 'SELECT d.delivery_no AS doc_no, d.delivery_date AS doc_date, d.customer_group AS party_group, d.customer_name AS party, '
    . "di.complain_no, di.received_model_no, di.received_serial, di.model_no, di.serial_no, di.delivery_qty, di.receivable, di.payable, IF(d.is_settled=1,'Yes','No') AS settled "
    . "FROM rma_delivery_items di JOIN rma_deliveries d ON d.delivery_id=di.delivery_id WHERE d.status='approved'";
$billBase = 'SELECT b.bill_no AS doc_no, b.bill_date AS doc_date, b.customer_group AS party_group, b.customer_name AS party, b.mobile, '
    . 'b.subtotal, b.discount_amount, b.net_amount, b.paid_amount, b.balance_amount '
    . "FROM rma_service_bills b WHERE b.status='approved'";
$diagBase = 'SELECT d.diagnosis_no AS doc_no, d.diagnosis_date AS doc_date, c.customer_group AS party_group, c.customer_name AS party, '
    . 'c.complain_no, ci.model_no, ci.received_serial AS serial_no, d.engineer_name, d.result, d.estimated_charge '
    . 'FROM rma_diagnoses d JOIN rma_complain_items ci ON ci.item_id=d.complain_item_id JOIN rma_complains c ON c.complain_id=ci.complain_id WHERE 1=1';
$utilBase = 'SELECT u.utilize_no AS doc_no, u.utilize_date AS doc_date, u.engineer_name AS party_group, u.engineer_name AS party, u.job_ref_no, '
    . 'ui.model_no, ui.store_name, ui.serial_no, ui.qty, ui.unit_cost, ui.qty*ui.unit_cost AS line_value '
    . 'FROM rma_utilize_items ui JOIN rma_utilizes u ON u.utilize_id=ui.utilize_id WHERE 1=1';

$pendingCols = ['complain_no' => ['Job Ref No'], 'doc_date' => ['Date'], 'party' => ['Customer'], 'mobile' => ['Mobile'], 'model_no' => ['Model No'],
    'serial_no' => ['Serial'], 'problem_name' => ['Problem'], 'delivery_date' => ['Delivery Date'], 'line_status' => ['Status'], 'qty' => ['Qty', 'qty']];
$complainCols = ['complain_no' => ['Job Ref No'], 'doc_date' => ['Date'], 'party' => ['Customer'], 'model_no' => ['Model No'], 'serial_no' => ['Serial'],
    'problem_name' => ['Problem'], 'engineer_name' => ['Engineer'], 'qty' => ['Qty', 'qty'], 'amount_charges' => ['Charges', 'num']];
$outCols = ['doc_no' => ['Job Ref No'], 'doc_date' => ['Date'], 'party' => ['Supplier'], 'model_no' => ['Model No'], 'serial_no' => ['Out Serial'],
    'problem_name' => ['Problem'], 'pre_job_ref_no' => ['Complain No'], 'line_status' => ['Status'], 'qty' => ['Qty', 'qty']];
$inCols = ['doc_no' => ['Job Ref No'], 'doc_date' => ['Date'], 'party' => ['Supplier'], 'out_job_ref_no' => ['Replace Out No'], 'out_model_no' => ['Out Model'],
    'out_serial' => ['Out Serial'], 'model_no' => ['In Model'], 'serial_no' => ['In Serial'], 'qty' => ['Qty', 'qty'], 'receivable' => ['Receivable', 'num'], 'payable' => ['Payable', 'num']];
$deliveryCols = ['doc_no' => ['Invoice No'], 'doc_date' => ['Date'], 'party' => ['Customer'], 'complain_no' => ['Complain No'], 'received_model_no' => ['Received Model'],
    'received_serial' => ['Received Serial'], 'model_no' => ['Delivery Model'], 'serial_no' => ['Delivery Serial'], 'delivery_qty' => ['Qty', 'qty'],
    'receivable' => ['Receivable', 'num'], 'payable' => ['Payable', 'num'], 'settled' => ['Settled']];

$reports = [
    'pending_customer' => ['caption' => 'Customer Wise Pending Delivery', 'sql' => $complainBase . " AND ci.line_status NOT IN ('delivered','settled')", 'order' => 'party, doc_date', 'group' => 'party', 'cols' => $pendingCols, 'sum' => ['qty']],
    'pending_product' => ['caption' => 'Product Wise Pending Delivery', 'sql' => $complainBase . " AND ci.line_status NOT IN ('delivered','settled')", 'order' => 'model_no, doc_date', 'group' => 'model_no', 'cols' => $pendingCols, 'sum' => ['qty']],
    'receivable_supplier' => ['caption' => 'Supplier Wise Replace Receivable', 'sql' => $outBase . " AND ri.line_status='sent'", 'order' => 'party, doc_date', 'group' => 'party', 'cols' => $outCols, 'sum' => ['qty']],
    'receivable_product' => ['caption' => 'Product Wise Replace Receivable', 'sql' => $outBase . " AND ri.line_status='sent'", 'order' => 'model_no, doc_date', 'group' => 'model_no', 'cols' => $outCols, 'sum' => ['qty']],
    'complain_customer' => ['caption' => 'Customer Wise Complain Receive', 'sql' => $complainBase, 'order' => 'party, doc_date', 'group' => 'party', 'cols' => $complainCols, 'sum' => ['qty', 'amount_charges']],
    'complain_date' => ['caption' => 'Date Wise Complain Receive', 'sql' => $complainBase, 'order' => 'doc_date, complain_no', 'group' => 'doc_date', 'cols' => $complainCols, 'sum' => ['qty', 'amount_charges']],
    'complain_product' => ['caption' => 'Product Wise Complain Receive', 'sql' => $complainBase, 'order' => 'model_no, doc_date', 'group' => 'model_no', 'cols' => $complainCols, 'sum' => ['qty', 'amount_charges']],
    'out_supplier' => ['caption' => 'Supplier Wise Replace Out', 'sql' => $outBase, 'order' => 'party, doc_date', 'group' => 'party', 'cols' => $outCols, 'sum' => ['qty']],
    'out_date' => ['caption' => 'Date Wise Replace Out', 'sql' => $outBase, 'order' => 'doc_date, doc_no', 'group' => 'doc_date', 'cols' => $outCols, 'sum' => ['qty']],
    'out_product' => ['caption' => 'Product Wise Replace Out', 'sql' => $outBase, 'order' => 'model_no, doc_date', 'group' => 'model_no', 'cols' => $outCols, 'sum' => ['qty']],
    'in_supplier' => ['caption' => 'Supplier Wise Replace In', 'sql' => $inBase, 'order' => 'party, doc_date', 'group' => 'party', 'cols' => $inCols, 'sum' => ['qty', 'receivable', 'payable']],
    'in_date' => ['caption' => 'Date Wise Replace In', 'sql' => $inBase, 'order' => 'doc_date, doc_no', 'group' => 'doc_date', 'cols' => $inCols, 'sum' => ['qty', 'receivable', 'payable']],
    'delivery_customer' => ['caption' => 'Customer Wise Replacement Delivery', 'sql' => $deliveryBase, 'order' => 'party, doc_date', 'group' => 'party', 'cols' => $deliveryCols, 'sum' => ['delivery_qty', 'receivable', 'payable']],
    'delivery_date' => ['caption' => 'Date Wise Replacement Delivery', 'sql' => $deliveryBase, 'order' => 'doc_date, doc_no', 'group' => 'doc_date', 'cols' => $deliveryCols, 'sum' => ['delivery_qty', 'receivable', 'payable']],
    'service_bill' => ['caption' => 'Customer Wise Service Bill', 'sql' => $billBase, 'order' => 'party, doc_date', 'group' => 'party',
        'cols' => ['doc_no' => ['Bill No'], 'doc_date' => ['Date'], 'party' => ['Customer'], 'mobile' => ['Mobile'], 'subtotal' => ['Sub Total', 'num'], 'discount_amount' => ['Discount', 'num'],
            'net_amount' => ['Net', 'num'], 'paid_amount' => ['Paid', 'num'], 'balance_amount' => ['Balance', 'num']],
        'sum' => ['subtotal', 'discount_amount', 'net_amount', 'paid_amount', 'balance_amount']],
    'diagnosis' => ['caption' => 'Date Wise Diagnosis', 'sql' => $diagBase, 'order' => 'doc_date, doc_no', 'group' => 'doc_date',
        'cols' => ['doc_no' => ['Diagnosis No'], 'doc_date' => ['Date'], 'complain_no' => ['Job Ref No'], 'party' => ['Customer'], 'model_no' => ['Model No'], 'serial_no' => ['Serial'],
            'engineer_name' => ['Engineer'], 'result' => ['Result'], 'estimated_charge' => ['Est. Charge', 'num']],
        'sum' => ['estimated_charge']],
    'utilize' => ['caption' => 'Engineer Wise Item Utilize', 'sql' => $utilBase, 'order' => 'party, doc_date', 'group' => 'party',
        'cols' => ['doc_no' => ['Utilize No'], 'doc_date' => ['Date'], 'job_ref_no' => ['Job Ref No'], 'model_no' => ['Model No'], 'store_name' => ['Store'], 'serial_no' => ['Serial'],
            'qty' => ['Qty', 'qty'], 'unit_cost' => ['Unit Cost', 'num'], 'line_value' => ['Value', 'num']],
        'sum' => ['qty', 'line_value']],
];

if (isset($_GET['report']) && isset($reports[$_GET['report']]) && rma_schema_ready()) {
    $r = $reports[$_GET['report']];
    [$from, $to, $dateLabel] = rma_report_dates();
    $scope = (string) ($_GET['scope'] ?? 'all');
    $sql = 'SELECT * FROM (' . $r['sql'] . ') x WHERE doc_date BETWEEN ? AND ?';
    $types = 'ss';
    $params = [$from, $to];
    $scopeLabel = 'All';
    if ($scope === 'group' && trim((string) ($_GET['party_group'] ?? '')) !== '') {
        $sql .= ' AND party_group=?';
        $types .= 's';
        $params[] = trim((string) $_GET['party_group']);
        $scopeLabel = 'Group: ' . $params[2];
    } elseif ($scope === 'trader' && trim((string) ($_GET['trader'] ?? '')) !== '') {
        $sql .= ' AND party LIKE ?';
        $types .= 's';
        $params[] = '%' . trim((string) $_GET['trader']) . '%';
        $scopeLabel = 'Trader: ' . trim((string) $_GET['trader']);
    }
    $rows = rma_rows($sql . ' ORDER BY ' . $r['order'], $types, $params);
    foreach ($rows as &$row) {
        foreach (['doc_date', 'delivery_date'] as $d) {
            if (!empty($row[$d])) {
                $row[$d] = date('d/m/Y', strtotime($row[$d]));
            }
        }
        if (isset($row['line_status'])) {
            $row['line_status'] = ucwords(str_replace('_', ' ', $row['line_status']));
        }
        if (isset($row['result'])) {
            $row['result'] = ucwords(str_replace('_', ' ', $row['result']));
        }
    }
    unset($row);
    rma_report_render('RMA ' . $r['caption'], $scopeLabel . ' · ' . $dateLabel, $r['cols'], $rows, $r['group'], $r['sum']);
    exit;
}

rma_page_header('RMA Service Register');
$today = date('Y-m-d');
?>
<form id="reg-form" onsubmit="return false">
  <div class="rma-filter">
    <label><input type="checkbox" id="all-date" name="all_date" value="1"> All Date</label>
    <label>Date From <input type="date" class="date-range" name="from" value="<?= $today ?>"></label>
    <label>Date To <input type="date" class="date-range" name="to" value="<?= $today ?>"></label>
  </div>
  <div id="reg-filters" hidden>
    <div class="rma-filter">
      <label><input type="radio" name="scope" value="all" checked> All</label>
      <label><input type="radio" name="scope" value="group"> Group Wise</label>
      <label><input type="radio" name="scope" value="trader"> Trader Wise</label>
      <label data-scope-for="group" hidden>Customer / Supplier Group <input type="text" name="party_group"></label>
      <label data-scope-for="trader" hidden>Customer / Supplier Name <input type="text" name="trader"></label>
    </div>
  </div>
</form>
<?php
rma_report_picker($reports);
rma_page_footer('RMA.initRegister();');
?>
<?php include "../../addon/conf/connection_close.php";  ?>
