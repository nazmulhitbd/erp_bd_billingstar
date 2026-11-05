<?php
/*
 * Document definitions for the RMA screens.
 * Field keys: n=name, l=label, t=type (text|date|num|qty|int|id|select|radio|textarea|ro), w=grid columns (of 12),
 * opt=options, btn=lookup kind for a "Search" button, ac=lookup kind for type-ahead, mode=lookup mode,
 * dl=datalist (stores|problems), def=default, req=required, nosave=display only, max=max length, nolabel=hide label.
 */
require_once __DIR__ . '/rma_common.php';

function rma_f($n, $l, $t = 'text', $w = 3, array $x = [])
{
    return ['n' => $n, 'l' => $l, 't' => $t, 'w' => $w] + $x;
}

const RMA_YES_NO = ['1' => 'Yes', '0' => 'No'];
const RMA_ADJUSTMENTS = [
    'without' => 'With Out Adjustment',
    'receivable' => 'Adjustment Receivable',
    'payable' => 'Adjustment Payable',
    'product_receivable' => 'Product With Adjustment Receivable',
    'product_payable' => 'Product With Adjustment Payable',
];

function rma_doc_configs()
{
    static $configs = null;
    if ($configs !== null) {
        return $configs;
    }
    $customerHeader = [
        rma_f('customer_group', 'Customer Group', 'text', 6, ['max' => 120]),
        rma_f('customer_name', 'Customer Name', 'text', 6, ['req' => 1, 'ac' => 'customer', 'max' => 190]),
        rma_f('customer_id', '', 'id'),
    ];
    $supplierHeader = [
        rma_f('supplier_group', 'Supplier Group', 'text', 6, ['max' => 120]),
        rma_f('supplier_name', 'Supplier Name', 'text', 6, ['req' => 1, 'ac' => 'supplier', 'max' => 190]),
        rma_f('supplier_id', '', 'id'),
    ];
    $productLine = [
        rma_f('product_group', 'Product Group', 'text', 4, ['max' => 140]),
        rma_f('brand', 'Brand', 'text', 3, ['max' => 140]),
        rma_f('model_no', 'Model No', 'text', 5, ['req' => 1, 'ac' => 'product', 'max' => 190]),
        rma_f('product_id', '', 'id'),
    ];

    $configs = [
        'complain' => [
            'title' => 'Complain Received', 'page' => 'complain_receive.php', 'code' => 'COM',
            'table' => 'rma_complains', 'pk' => 'complain_id', 'no' => 'complain_no', 'date' => 'complain_date',
            'party' => 'customer_name', 'party_label' => 'Customer Name', 'approvable' => true,
            'item_table' => 'rma_complain_items', 'store_purpose' => 'complain_receive',
            'header' => array_merge([
                rma_f('ref_no', 'Ref No', 'text', 3, ['max' => 80]),
                rma_f('complain_date', 'Complain Date', 'date', 2, ['req' => 1]),
                rma_f('mobile', 'Mobile No', 'text', 3, ['btn' => 'customer', 'max' => 40]),
                rma_f('complain_no', 'Job Ref No', 'docno', 4),
            ], $customerHeader, [
                rma_f('address', 'Address', 'text', 6, ['max' => 2000]),
                rma_f('attention', 'Attention', 'text', 6, ['max' => 190]),
            ]),
            'item' => array_merge($productLine, [
                rma_f('description', 'Item Description', 'ro', 7, ['nolabel' => 1, 'max' => 255]),
                rma_f('sequence_type', '', 'radio', 5, ['opt' => ['not_available' => 'Sequence Not Available', 'auto_generated' => 'Auto Generated Serial'], 'def' => 'not_available']),
                rma_f('under_warranty', 'Under Warranty', 'select', 2, ['opt' => RMA_YES_NO, 'def' => '1']),
                rma_f('sales_invoice_no', 'Sales Invoice No', 'text', 3, ['max' => 40]),
                rma_f('sales_value', 'Value', 'num', 2),
                rma_f('sales_date', 'Sales Date', 'date', 2),
                rma_f('duration', 'Duration(Y-M-D)', 'ro', 3, ['nosave' => 1, 'def' => '0Y-0M-0D']),
                rma_f('sold_serial', 'Sold Serial No', 'text', 4, ['btn' => 'sold_serial', 'max' => 120]),
                rma_f('store_name', 'Store Name', 'text', 4, ['dl' => 'stores', 'max' => 140, 'def' => '@store']),
                rma_f('delivery_date', 'Delivery Date', 'date', 4),
                rma_f('problem_name', 'Problem', 'text', 6, ['dl' => 'problems', 'max' => 190]),
                rma_f('engineer_name', 'Engineer Name', 'text', 6, ['max' => 140, 'def' => '@user']),
                rma_f('no_of_time', 'No Of Time', 'int', 3, ['def' => '0']),
                rma_f('pre_job_ref_no', 'Pre Job Ref No', 'text', 4, ['max' => 40]),
                rma_f('narration', 'Naration', 'text', 5, ['max' => 255]),
                rma_f('qty', 'Qty', 'qty', 3, ['def' => '1']),
                rma_f('received_serial', 'Received Serial No', 'text', 4, ['max' => 120]),
                rma_f('amount_charges', 'Amount Charges', 'num', 3),
                rma_f('need_diagnosis', 'Diagnosis', 'select', 2, ['opt' => RMA_YES_NO, 'def' => '1']),
            ]),
            'line_required' => 'model_no',
            'grid' => [['model_no', 'Model No'], ['store_name', 'Store Name'], ['qty', 'QTY', 'num'], ['amount_charges', 'Amount', 'num']],
            'serial_fields' => ['received_serial', 'sold_serial'],
        ],

        'replace_out' => [
            'title' => 'Replace Out', 'page' => 'replace_out.php', 'code' => 'REP',
            'table' => 'rma_replace_outs', 'pk' => 'replace_out_id', 'no' => 'replace_out_no', 'date' => 'replace_date',
            'party' => 'supplier_name', 'party_label' => 'Supplier Name', 'approvable' => true,
            'item_table' => 'rma_replace_out_items', 'store_purpose' => 'replace_out',
            'header' => array_merge([
                rma_f('ref_no', 'Ref No', 'text', 3, ['max' => 80]),
                rma_f('replace_date', 'Replace Date', 'date', 2, ['req' => 1]),
                rma_f('mobile', 'Mobile No', 'text', 3, ['btn' => 'supplier', 'max' => 40]),
                rma_f('replace_out_no', 'Job Ref No', 'docno', 4),
            ], $supplierHeader, [
                rma_f('address', 'Address', 'text', 6, ['max' => 2000]),
                rma_f('attention', 'Attention', 'text', 6, ['max' => 190]),
                rma_f('narration', 'Naration', 'text', 12, ['max' => 255]),
            ]),
            'item' => array_merge($productLine, [
                rma_f('complain_item_id', '', 'id'),
                rma_f('description', 'Item Description', 'ro', 7, ['nolabel' => 1, 'max' => 255]),
                rma_f('sequence_type', '', 'radio', 5, ['opt' => ['not_available' => 'Sequence Not Available', 'available' => 'Sequence Available'], 'def' => 'not_available']),
                rma_f('under_warranty', 'Under Warranty', 'select', 2, ['opt' => RMA_YES_NO, 'def' => '1']),
                rma_f('serial_common', 'Serial Commom', 'text', 3, ['max' => 120]),
                rma_f('serial_from', 'Serial From', 'text', 3, ['max' => 120]),
                rma_f('serial_to', 'Serial To', 'text', 4, ['max' => 120]),
                rma_f('no_of_time', 'No Of Time', 'int', 3, ['def' => '0']),
                rma_f('pre_job_ref_no', 'Pre Job Ref No', 'text', 4, ['max' => 40]),
                rma_f('store_name', 'Store Name', 'text', 5, ['dl' => 'stores', 'max' => 140, 'def' => '@store']),
                rma_f('problem_name', 'Problem', 'text', 5, ['dl' => 'problems', 'max' => 190]),
                rma_f('purchase_inv_no', 'Purchase Inv No', 'text', 4, ['max' => 40]),
                rma_f('purchase_date', 'Purchase Date', 'date', 3),
                rma_f('purchase_serial', 'Purcahse Serial', 'text', 4, ['btn' => 'complain_item', 'mode' => 'replace_out', 'max' => 120]),
                rma_f('out_serial', 'Out Serial No', 'text', 4, ['max' => 120]),
                rma_f('qty', 'Quantity', 'qty', 4, ['def' => '1']),
            ]),
            'line_required' => 'model_no',
            'grid' => [['model_no', 'Model No'], ['store_name', 'Store'], ['qty', 'QTY', 'num']],
            'serial_fields' => ['out_serial', 'purchase_serial'],
        ],

        'replace_in' => [
            'title' => 'Replace In', 'page' => 'replace_in.php', 'code' => 'RIN',
            'table' => 'rma_replace_ins', 'pk' => 'replace_in_id', 'no' => 'replace_in_no', 'date' => 'replace_date',
            'party' => 'supplier_name', 'party_label' => 'Supplier Name', 'approvable' => true,
            'item_table' => 'rma_replace_in_items', 'store_purpose' => 'replace_in',
            'header' => array_merge([
                rma_f('ref_no', 'Ref No', 'text', 3, ['max' => 80]),
                rma_f('replace_date', 'Replace Date', 'date', 2, ['req' => 1]),
                rma_f('mobile', 'Mobile No', 'text', 3, ['btn' => 'supplier', 'max' => 40]),
                rma_f('replace_in_no', 'Job Ref No', 'docno', 4),
            ], $supplierHeader, [
                rma_f('address', 'Address', 'text', 6, ['max' => 2000]),
                rma_f('narration', 'Naration', 'text', 6, ['max' => 255]),
            ]),
            'item' => [
                rma_f('out_job_ref_no', 'Job Ref No', 'text', 3, ['btn' => 'replace_out_item', 'max' => 40]),
                rma_f('out_product_group', 'Product Group', 'ro', 3, ['max' => 140]),
                rma_f('out_model_no', 'Model No', 'ro', 3, ['max' => 190]),
                rma_f('out_serial', 'Serial Out', 'ro', 3, ['max' => 120]),
                rma_f('replace_out_item_id', '', 'id'),
                rma_f('out_qty', '', 'hidden', 0, ['def' => '1']),
                rma_f('description', 'Item Description', 'ro', 12, ['max' => 255]),
                rma_f('adjustment_type', '', 'radio', 12, ['opt' => RMA_ADJUSTMENTS, 'def' => 'without']),
                rma_f('product_group', 'Product Group', 'text', 5, ['max' => 140]),
                rma_f('model_no', 'Model No', 'text', 7, ['req' => 1, 'ac' => 'product', 'max' => 190]),
                rma_f('product_id', '', 'id'),
                rma_f('brand', '', 'hidden', 0, ['max' => 140]),
                rma_f('store_name', 'Store Name', 'text', 6, ['dl' => 'stores', 'max' => 140, 'def' => '@store']),
                rma_f('qty', 'Qty', 'qty', 2, ['def' => '1']),
                rma_f('adj_amount', 'Adj Amount', 'num', 4, ['nosave' => 1]),
                rma_f('unit_price', 'Unit Price', 'num', 4),
                rma_f('serial_no', 'Serial No', 'text', 8, ['max' => 120]),
            ],
            'line_required' => 'model_no',
            'grid' => [['out_model_no', 'Out Model No'], ['out_serial', 'Out Serial/Qty'], ['model_no', 'Model No'], ['serial_no', 'Serial No'], ['qty', 'Qty', 'num'], ['receivable', 'Receivable', 'num'], ['payable', 'Payable', 'num']],
            'totals' => ['total_receivable' => 'Total Receivable', 'total_payable' => 'Total Payable'],
            'serial_fields' => ['out_serial', 'serial_no'],
        ],

        'delivery' => [
            'title' => 'Replacement Delivery', 'page' => 'replace_delivery.php', 'code' => 'RPD',
            'table' => 'rma_deliveries', 'pk' => 'delivery_id', 'no' => 'delivery_no', 'date' => 'delivery_date',
            'party' => 'customer_name', 'party_label' => 'Customer Name', 'approvable' => true,
            'item_table' => 'rma_delivery_items', 'store_purpose' => 'delivery', 'settle' => true,
            'header' => array_merge([
                rma_f('ref_no', 'Ref No', 'text', 3, ['max' => 80]),
                rma_f('delivery_date', 'Date', 'date', 2, ['req' => 1]),
                rma_f('mobile', 'Mobile No', 'text', 3, ['btn' => 'customer', 'max' => 40]),
                rma_f('delivery_no', 'Invoice No', 'docno', 4),
            ], $customerHeader, [
                rma_f('address', 'Address', 'text', 12, ['max' => 2000]),
            ]),
            'item' => [
                rma_f('complain_no', 'Complain No', 'text', 3, ['btn' => 'complain_item', 'mode' => 'delivery', 'max' => 40]),
                rma_f('received_product_group', 'Product Group', 'ro', 3, ['max' => 140]),
                rma_f('received_model_no', 'Model No', 'ro', 3, ['max' => 190]),
                rma_f('received_serial', 'Serial', 'ro', 3, ['max' => 120]),
                rma_f('complain_item_id', '', 'id'),
                rma_f('received_qty', '', 'hidden', 0, ['def' => '1']),
                rma_f('adjustment_type', '', 'radio', 12, ['opt' => RMA_ADJUSTMENTS, 'def' => 'without']),
                rma_f('product_group', 'Product Group', 'text', 5, ['max' => 140]),
                rma_f('model_no', 'Model No', 'text', 7, ['req' => 1, 'ac' => 'product', 'max' => 190]),
                rma_f('product_id', '', 'id'),
                rma_f('brand', '', 'hidden', 0, ['max' => 140]),
                rma_f('description', 'Item Description', 'ro', 6, ['nolabel' => 1, 'max' => 255]),
                rma_f('narration', 'Naration', 'text', 6, ['max' => 255]),
                rma_f('store_name', 'Store Name', 'text', 4, ['dl' => 'stores', 'max' => 140, 'def' => '@store']),
                rma_f('serial_no', 'Serial No', 'text', 3, ['max' => 120]),
                rma_f('delivery_qty', 'Qty', 'qty', 2, ['def' => '1']),
                rma_f('adj_amount', 'Adj Amount', 'num', 3, ['nosave' => 1]),
            ],
            'line_required' => 'model_no',
            'grid' => [['complain_no', 'Complain No'], ['received_model_no', 'Received Model No'], ['received_serial', 'Received Serial'], ['model_no', 'Delivery Model No'], ['received_qty', 'Received Qty', 'num'], ['delivery_qty', 'Delivery Qty', 'num'], ['receivable', 'Receivable', 'num'], ['payable', 'Payable', 'num']],
            'totals' => ['total_receivable' => 'Total Receivable', 'total_payable' => 'Total Payable'],
            'serial_fields' => ['received_serial', 'serial_no'],
        ],

        'service_bill' => [
            'title' => 'RMA Service Bill', 'page' => 'service_bill.php', 'code' => 'SRB',
            'table' => 'rma_service_bills', 'pk' => 'bill_id', 'no' => 'bill_no', 'date' => 'bill_date',
            'party' => 'customer_name', 'party_label' => 'Customer Name', 'approvable' => true,
            'item_table' => 'rma_service_bill_items',
            'header' => array_merge([
                rma_f('ref_no', 'Ref No', 'text', 3, ['max' => 80]),
                rma_f('bill_date', 'Bill Date', 'date', 2, ['req' => 1]),
                rma_f('mobile', 'Mobile No', 'text', 3, ['btn' => 'customer', 'max' => 40]),
                rma_f('bill_no', 'Bill No', 'docno', 4),
            ], $customerHeader, [
                rma_f('address', 'Address', 'text', 6, ['max' => 2000]),
                rma_f('narration', 'Naration', 'text', 6, ['max' => 255]),
                rma_f('subtotal', 'Sub Total', 'ro', 3, ['calc' => 1]),
                rma_f('discount_amount', 'Less Discount', 'num', 3),
                rma_f('net_amount', 'Net Amount', 'ro', 2, ['calc' => 1]),
                rma_f('paid_amount', 'Paid Amount', 'num', 2),
                rma_f('balance_amount', 'Balance', 'ro', 2, ['calc' => 1]),
            ]),
            'item' => [
                rma_f('complain_no', 'Complain No', 'text', 3, ['btn' => 'complain_item', 'mode' => 'bill', 'max' => 40]),
                rma_f('model_no', 'Model No', 'text', 5, ['max' => 190]),
                rma_f('serial_no', 'Serial No', 'text', 4, ['max' => 120]),
                rma_f('complain_item_id', '', 'id'),
                rma_f('description', 'Description', 'text', 12, ['req' => 1, 'max' => 255]),
                rma_f('service_charge', 'Service Charge', 'num', 4),
                rma_f('parts_charge', 'Parts Charge', 'num', 4),
                rma_f('discount', 'Discount', 'num', 4),
            ],
            'line_required' => 'description',
            'grid' => [['complain_no', 'Complain No'], ['model_no', 'Model No'], ['serial_no', 'Serial'], ['description', 'Description'], ['service_charge', 'Service', 'num'], ['parts_charge', 'Parts', 'num'], ['discount', 'Discount', 'num'], ['amount', 'Amount', 'num']],
            'serial_fields' => ['serial_no'],
        ],

        'utilize' => [
            'title' => 'RMA Item Utilize', 'page' => 'item_utilize.php', 'code' => 'UTL',
            'table' => 'rma_utilizes', 'pk' => 'utilize_id', 'no' => 'utilize_no', 'date' => 'utilize_date',
            'party' => 'engineer_name', 'party_label' => 'Engineer Name', 'approvable' => false,
            'item_table' => 'rma_utilize_items', 'store_purpose' => 'utilize',
            'header' => [
                rma_f('utilize_date', 'Utilize Date', 'date', 3, ['req' => 1]),
                rma_f('job_ref_no', 'Job Ref No', 'text', 5, ['btn' => 'complain_item', 'mode' => 'utilize', 'max' => 40]),
                rma_f('utilize_no', 'Utilize No', 'docno', 4),
                rma_f('complain_item_id', '', 'id'),
                rma_f('engineer_name', 'Engineer Name', 'text', 6, ['max' => 140, 'def' => '@user']),
                rma_f('narration', 'Naration', 'text', 6, ['max' => 255]),
            ],
            'item' => array_merge($productLine, [
                rma_f('store_name', 'Store Name', 'text', 4, ['dl' => 'stores', 'max' => 140, 'def' => '@store']),
                rma_f('serial_no', 'Serial No', 'text', 3, ['max' => 120]),
                rma_f('qty', 'Qty', 'qty', 2, ['def' => '1']),
                rma_f('unit_cost', 'Unit Cost', 'num', 3),
            ]),
            'line_required' => 'model_no',
            'grid' => [['model_no', 'Model No'], ['store_name', 'Store Name'], ['serial_no', 'Serial'], ['qty', 'QTY', 'num'], ['unit_cost', 'Unit Cost', 'num']],
            'serial_fields' => ['serial_no'],
        ],

        'diagnosis' => [
            'title' => 'RMA Diagonis', 'page' => 'diagnosis.php', 'code' => 'DIA',
            'table' => 'rma_diagnoses', 'pk' => 'diagnosis_id', 'no' => 'diagnosis_no', 'date' => 'diagnosis_date',
            'party' => 'engineer_name', 'party_label' => 'Engineer Name', 'approvable' => false,
            'header' => [
                rma_f('diagnosis_date', 'Diagnosis Date', 'date', 3, ['req' => 1]),
                rma_f('job_ref_no', 'Job Ref No', 'text', 5, ['btn' => 'complain_item', 'mode' => 'diagnosis', 'nosave' => 1]),
                rma_f('diagnosis_no', 'Diagnosis No', 'docno', 4),
                rma_f('complain_item_id', '', 'id', 0, ['req' => 1]),
                rma_f('customer_name', 'Customer Name', 'ro', 6, ['nosave' => 1]),
                rma_f('model_no', 'Model No', 'ro', 6, ['nosave' => 1]),
                rma_f('received_serial', 'Serial No', 'ro', 6, ['nosave' => 1]),
                rma_f('problem_name', 'Problem', 'ro', 6, ['nosave' => 1]),
                rma_f('engineer_name', 'Engineer Name', 'text', 4, ['max' => 140, 'def' => '@user']),
                rma_f('result', 'Result', 'select', 4, ['opt' => ['repairable' => 'Repairable', 'replace' => 'Replace', 'no_fault' => 'No Fault Found', 'beyond_repair' => 'Beyond Repair']]),
                rma_f('estimated_charge', 'Estimated Charge', 'num', 4),
                rma_f('findings', 'Findings', 'textarea', 12, ['max' => 2000]),
            ],
            'grid' => [['diagnosis_no', 'Diagnosis No'], ['diagnosis_date', 'Date'], ['job_ref_no', 'Job Ref No'], ['model_no', 'Model No'], ['received_serial', 'Serial'], ['result', 'Result'], ['estimated_charge', 'Charge', 'num']],
            'serial_fields' => ['received_serial'],
        ],
    ];
    return $configs;
}

function rma_doc_config($type)
{
    $configs = rma_doc_configs();
    if (!isset($configs[$type])) {
        throw new InvalidArgumentException('Unknown RMA document type.');
    }
    return $configs[$type] + ['type' => $type, 'item' => [], 'item_table' => null, 'totals' => [], 'serial_fields' => []];
}

/* ------------------------------------------------------------------ Hooks */

function rma_adjust_line(array $item)
{
    $amount = rma_amount($item['adj_amount'] ?? 0);
    $type = $item['adjustment_type'] ?? 'without';
    $item['receivable'] = in_array($type, ['receivable', 'product_receivable'], true) ? $amount : 0.0;
    $item['payable'] = in_array($type, ['payable', 'product_payable'], true) ? $amount : 0.0;
    return $item;
}

/** Fill computed columns before the header / items are written. */
function rma_prepare(array $cfg, array $header, array $items)
{
    $type = $cfg['type'];
    if ($type === 'replace_in' || $type === 'delivery') {
        foreach ($items as &$item) {
            $item = rma_adjust_line($item);
        }
        unset($item);
        $header['total_receivable'] = round(array_sum(array_column($items, 'receivable')), 2);
        $header['total_payable'] = round(array_sum(array_column($items, 'payable')), 2);
    } elseif ($type === 'service_bill') {
        $subtotal = 0.0;
        foreach ($items as &$item) {
            $item['amount'] = max(0.0, round($item['service_charge'] + $item['parts_charge'] - $item['discount'], 2));
            $subtotal += $item['amount'];
        }
        unset($item);
        $header['subtotal'] = round($subtotal, 2);
        $header['net_amount'] = max(0.0, round($subtotal - $header['discount_amount'], 2));
        $header['balance_amount'] = max(0.0, round($header['net_amount'] - $header['paid_amount'], 2));
    }
    return [$header, $items];
}

/** Extra columns written for each line that are not form fields. */
function rma_item_extra_columns($type)
{
    return [
        'replace_in' => ['receivable' => 'd', 'payable' => 'd'],
        'delivery' => ['receivable' => 'd', 'payable' => 'd'],
        'service_bill' => ['amount' => 'd'],
    ][$type] ?? [];
}

function rma_header_extra_columns($type)
{
    return [
        'replace_in' => ['total_receivable' => 'd', 'total_payable' => 'd'],
        'delivery' => ['total_receivable' => 'd', 'total_payable' => 'd'],
        'service_bill' => ['subtotal' => 'd', 'net_amount' => 'd', 'balance_amount' => 'd'],
    ][$type] ?? [];
}

/** Side effects after a save (inside the transaction). */
function rma_after_save(array $cfg, $id, array $header, array $items)
{
    $type = $cfg['type'];
    if ($type === 'utilize') {
        rma_exec("DELETE FROM rma_stock_ledger WHERE doc_type='utilize' AND doc_id=?", 'i', [$id])->close();
        foreach ($items as $item) {
            rma_ledger($header['utilize_date'], $item, 'good', 0, $item['qty'], $item['unit_cost'], $item['serial_no'], 'utilize', $id, $header['utilize_no'], $header['engineer_name']);
        }
    } elseif ($type === 'diagnosis') {
        rma_exec("UPDATE rma_complain_items SET line_status='diagnosed' WHERE item_id=? AND line_status='received'", 'i', [(int) $header['complain_item_id']])->close();
    } elseif ($type === 'replace_out' || $type === 'replace_in') {
        // Remember suppliers typed in by hand so they appear in the lookup next time.
        if (empty($header['supplier_id']) && $header['supplier_name'] !== '') {
            rma_exec(
                'INSERT INTO rma_suppliers (supplier_group, supplier_name, mobile, address, attention) VALUES (?, ?, ?, ?, ?) '
                    . 'ON DUPLICATE KEY UPDATE supplier_id=LAST_INSERT_ID(supplier_id)',
                'sssss',
                [$header['supplier_group'], $header['supplier_name'], $header['mobile'], (string) $header['address'], (string) ($header['attention'] ?? '')]
            )->close();
            $supplierId = (int) $GLOBALS['rma_conn']->insert_id;
            rma_exec("UPDATE {$cfg['table']} SET supplier_id=? WHERE {$cfg['pk']}=?", 'ii', [$supplierId, $id])->close();
        }
    }
}

function rma_before_delete(array $cfg, $id)
{
    if ($cfg['type'] === 'utilize') {
        rma_exec("DELETE FROM rma_stock_ledger WHERE doc_type='utilize' AND doc_id=?", 'i', [$id])->close();
    }
}

/** Approve a draft document: lock it, post stock and move job statuses. Runs in the caller's transaction. */
function rma_approve_document(array $cfg, $id)
{
    $header = rma_row("SELECT * FROM {$cfg['table']} WHERE {$cfg['pk']}=? FOR UPDATE", 'i', [$id]);
    if (!$header) {
        throw new RuntimeException('Record not found.');
    }
    if ($header['status'] !== 'draft') {
        throw new RuntimeException($header[$cfg['no']] . ' is already approved.');
    }
    $items = $cfg['item_table']
        ? rma_rows("SELECT * FROM {$cfg['item_table']} WHERE {$cfg['pk']}=? ORDER BY line_number", 'i', [$id])
        : [];
    if ($cfg['item_table'] && !$items) {
        throw new RuntimeException($header[$cfg['no']] . ' has no items to approve.');
    }
    $date = $header[$cfg['date']];
    $docNo = $header[$cfg['no']];
    $party = $header[$cfg['party']];
    $type = $cfg['type'];
    foreach ($items as $item) {
        if ($type === 'complain') {
            rma_ledger($date, $item, 'faulty', $item['qty'], 0, 0, $item['received_serial'], $type, $id, $docNo, $party);
        } elseif ($type === 'replace_out') {
            rma_ledger($date, $item, 'faulty', 0, $item['qty'], 0, $item['out_serial'], $type, $id, $docNo, $party);
            if ($item['complain_item_id']) {
                rma_exec("UPDATE rma_complain_items SET line_status='sent_supplier' WHERE item_id=?", 'i', [(int) $item['complain_item_id']])->close();
            }
        } elseif ($type === 'replace_in') {
            rma_ledger($date, $item, 'good', $item['qty'], 0, $item['unit_price'], $item['serial_no'], $type, $id, $docNo, $party);
            if ($item['replace_out_item_id']) {
                rma_exec("UPDATE rma_replace_out_items SET line_status='received' WHERE item_id=?", 'i', [(int) $item['replace_out_item_id']])->close();
                rma_exec(
                    "UPDATE rma_complain_items ci JOIN rma_replace_out_items ro ON ro.complain_item_id=ci.item_id "
                        . "SET ci.line_status='replaced_in' WHERE ro.item_id=?",
                    'i', [(int) $item['replace_out_item_id']]
                )->close();
            }
        } elseif ($type === 'delivery') {
            rma_ledger($date, $item, 'good', 0, $item['delivery_qty'], 0, $item['serial_no'], $type, $id, $docNo, $party);
            if ($item['complain_item_id']) {
                rma_exec("UPDATE rma_complain_items SET line_status='delivered' WHERE item_id=?", 'i', [(int) $item['complain_item_id']])->close();
            }
        }
    }
    rma_exec(
        "UPDATE {$cfg['table']} SET status='approved', approved_by=?, approved_at=NOW() WHERE {$cfg['pk']}=?",
        'ii', [rma_user_id(), $id]
    )->close();
    bus_admin_audit('rma_approve', $cfg['table'], $id, ['doc_no' => $docNo]);
    return $docNo;
}

/** Display-only values for saved records (e.g. Diagnosis shows the job it belongs to). */
function rma_load_extras(array $cfg, array $header)
{
    if ($cfg['type'] === 'diagnosis' && !empty($header['complain_item_id'])) {
        $row = rma_row(
            'SELECT c.complain_no AS job_ref_no, c.customer_name, ci.model_no, ci.received_serial, ci.problem_name '
                . 'FROM rma_complain_items ci JOIN rma_complains c ON c.complain_id=ci.complain_id WHERE ci.item_id=?',
            'i', [(int) $header['complain_item_id']]
        );
        $header = array_merge($header, $row ?: []);
    }
    return $header;
}
