<?php
/**
 * Price Quotation: offer prices to a customer (no stock moves), laid out like the desktop screen:
 * Quotation Date, Mobile / Quotation No search, customer, product entry with Product Narration and Warranty,
 * Edit Price in the grid, Show Cost / Show Price, VAT & Discount, Additional Extra Charges, Payment Term and prints.
 * A saved quotation can be turned into a Sales Invoice (Make Sales Invoice).
 * Stored in inv_docs / inv_doc_lines with doc_type quotation (numbers like DESPQI2026000001).
 */
require_once dirname(__DIR__) . '/inventory/inv_doc_engine.php';

/** Make Sales Invoice link and the invoices already made from a saved quotation. */
function quotation_panel(mysqli $db, array $cfg, $self, $doc, $readOnly)
{
    if (!$doc) {
        return;
    }
    $invoices = sales_rows($db, "SELECT invoice_id, invoice_no, sales_date, net_amount FROM sales_invoices WHERE ref_no=? AND status='posted' ORDER BY invoice_id", 's', [$doc['doc_no']]);
    echo '<section class="section"><div class="section-title">Sales Invoice</div>';
    echo $doc['status'] !== 'active'
        ? '<p class="muted">This quotation is cancelled.</p>'
        : '<a class="btn primary" href="../sales/sales_invoice/sales_invoice.php?quotation=' . urlencode($doc['doc_no']) . '">Make Sales Invoice</a>';
    if ($invoices) {
        echo '<table style="margin-top:8px"><thead><tr><th>Invoice No</th><th>Date</th><th class="num">Net</th><th></th></tr></thead><tbody>';
        foreach ($invoices as $i) {
            echo '<tr><td>' . sales_h($i['invoice_no']) . '</td><td>' . inv_fmt_date($i['sales_date']) . '</td><td class="num">' . sales_money($i['net_amount']) . '</td><td><a class="btn small" href="../sales/sales_invoice/sales_invoice.php?id=' . (int) $i['invoice_id'] . '">Open</a></td></tr>';
        }
        echo '</tbody></table>';
    }
    echo '</section>';
}

inv_doc_page($conn, [
    'type' => 'quotation',
    'header' => [['mobile', 'Mobile No', 'mobile'], ['party_group', 'Customer Group', 'text'], ['party_name', 'Customer Name', 'party'], ['address', 'Address', 'text'],
        ['attention', 'Attention', 'text'], ['sales_person', 'Sales Person', 'text'], ['narration', 'Narration', 'text']],
    'lines' => ['narration', 'warranty', 'compact_grid'],
    'totals' => ['extra', 'discount', 'vat'],
    'buttons' => ['edit_price_inline', 'show_cost', 'show_price', 'extra_charges', 'payment_term'],
    'date_label' => 'Quotation Date',
    'search_label' => 'Quotation No',
    'narration_label' => 'Product Narration',
    'print_title' => 'Price Quotation',
    'panels' => 'quotation_panel',
]);
?>
<?php include "../../addon/conf/connection_close.php";  ?>
