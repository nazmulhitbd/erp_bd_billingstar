<?php
function bus_tax_active_rule($journey_date)
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $journey_date)) {
        return false;
    }
    $date_sql = mysql_real_escape_string($journey_date);
    $result = mysql_query("SELECT tax_rule_id, tax_code, tax_name, tax_type, rate_percent, price_mode, authority_reference
        FROM bus_tax_rules
        WHERE status='Active' AND effective_from<='$date_sql'
            AND (effective_to IS NULL OR effective_to>='$date_sql')
        ORDER BY effective_from DESC, tax_rule_id DESC LIMIT 1");
    return $result ? mysql_fetch_assoc($result) : false;
}

function bus_tax_quote($fare_total, $rule)
{
    $fare_total = round(max(0, (float) $fare_total), 2);
    if (!is_array($rule)) {
        return array('available' => false, 'taxable_amount' => $fare_total, 'tax_amount' => 0.00, 'payable_amount' => $fare_total);
    }

    $rate = max(0, min(100, (float) ($rule['rate_percent'] ?? 0)));
    if (($rule['price_mode'] ?? '') === 'inclusive') {
        $taxable = $rate === 0.0 ? $fare_total : round($fare_total / (1 + ($rate / 100)), 2);
        $tax = round($fare_total - $taxable, 2);
        $payable = $fare_total;
    } else {
        $taxable = $fare_total;
        $tax = round($taxable * $rate / 100, 2);
        $payable = round($taxable + $tax, 2);
    }
    return array('available' => true, 'taxable_amount' => $taxable, 'tax_amount' => $tax, 'payable_amount' => $payable);
}

function bus_tax_invoice_reference($ticket_code)
{
    $date_code = date('Ymd');
    $booking_code = strtoupper(substr(hash('sha256', (string) $ticket_code), 0, 12));
    return 'BDVAT-' . $date_code . '-' . $booking_code;
}