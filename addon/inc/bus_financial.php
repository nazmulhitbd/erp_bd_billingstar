<?php
function bus_financial_refund_allocation($requested, $payable, $tax, $prior_refunds, $prior_tax_refunds)
{
    $requested = round((float) $requested, 2);
    $payable = round((float) $payable, 2);
    $tax = round((float) $tax, 2);
    $prior_refunds = round((float) $prior_refunds, 2);
    $prior_tax_refunds = round((float) $prior_tax_refunds, 2);
    $remaining = round(max(0, $payable - $prior_refunds), 2);

    if ($requested <= 0 || $payable <= 0 || $requested > $remaining || $tax < 0 || $prior_tax_refunds < 0) {
        return false;
    }

    $remaining_tax = max(0, $tax - $prior_tax_refunds);
    $tax_refund = min(round($tax * ($requested / $payable), 2), $remaining_tax, $requested);
    return array(
        'refund_amount' => $requested,
        'tax_refund_amount' => round($tax_refund, 2),
        'taxable_refund_amount' => round($requested - $tax_refund, 2),
        'remaining_refundable' => round($remaining - $requested, 2)
    );
}

function bus_financial_summary($paid_taxable, $paid_tax, $paid_cash, $refunded_taxable, $refunded_tax, $refunded_cash, $expenses)
{
    $net_taxable = round((float) $paid_taxable - (float) $refunded_taxable, 2);
    $net_tax = round((float) $paid_tax - (float) $refunded_tax, 2);
    $net_cash = round((float) $paid_cash - (float) $refunded_cash, 2);
    $operating_profit = round($net_taxable - (float) $expenses, 2);

    return array(
        'net_taxable_revenue' => $net_taxable,
        'net_tax_collected' => $net_tax,
        'net_cash' => $net_cash,
        'operating_expenses' => round((float) $expenses, 2),
        'operating_profit' => $operating_profit,
        'result_type' => $operating_profit >= 0 ? 'profit' : 'loss'
    );
}