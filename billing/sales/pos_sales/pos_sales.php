<?php
/**
 * POS Sales: fast counter sale. Each sale is saved as a normal sales invoice
 * (ref_no = 'POS', bill type Cash) so it shows in the invoice list and can be returned.
 * With sales stock on, the goods leave the default store.
 * Tables: billing/sales/schema.sql; stock needs inventory/schema.sql and sales/schema_v2.sql.
 * The receipt header comes from Company & Login Settings; heading, VAT and footer from User Config (inv_settings pos_*).
 */
require_once dirname(__DIR__) . '/sales_common.php';
require_once dirname(__DIR__) . '/sales_stock.php';
require_once dirname(__DIR__, 3) . '/addon/inc/brand.php';

$self = 'pos_sales.php';
$schemaReady = sales_tables_ready($conn, ['sales_customers', 'sales_products', 'sales_invoices', 'sales_invoice_items', 'sales_invoice_payments', 'sales_invoice_sequences']);
$settingsReady = sales_tables_ready($conn, ['inv_settings']);
$posSetting = fn($key, $default) => $settingsReady ? inv_setting($conn, $key, $default) : $default;
$vatInclusive = $posSetting('pos_vat_mode', 'inclusive') !== 'exclusive';
$defaultVat = min(100.0, max(0.0, (float) $posSetting('pos_vat_percent', '0')));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    sales_require_csrf($self);
    if (!$schemaReady) {
        sales_flash_redirect($self, 'error', 'Sales tables are missing. Import billing/sales/schema.sql first.');
    }
    try {
        $cart = json_decode((string) ($_POST['cart_json'] ?? ''), true);
        if (!is_array($cart) || !$cart || count($cart) > 300) {
            throw new RuntimeException('The cart is empty.');
        }
        $lines = [];
        $subtotal = 0.0;
        $totalQty = 0.0;
        foreach ($cart as $c) {
            $description = sales_str($c['description'] ?? '', 255);
            $qty = sales_qty($c['quantity'] ?? 0);
            if ($description === '' || $qty <= 0) {
                continue;
            }
            $price = sales_amount($c['unit_price'] ?? 0);
            $disc = min(sales_amount($c['discount_amount'] ?? 0), round($qty * $price, 2));
            $total = round($qty * $price - $disc, 2);
            $subtotal += $total;
            $totalQty += $qty;
            $lines[] = [
                'product_id' => ((int) ($c['product_id'] ?? 0)) > 0 ? (int) $c['product_id'] : null,
                'product_code' => sales_str($c['product_code'] ?? '', 60),
                'product_group' => sales_str($c['product_group'] ?? '', 140),
                'brand' => sales_str($c['brand'] ?? '', 140),
                'model_caption' => sales_str($c['model_caption'] ?? '', 190),
                'description' => $description,
                'warranty' => sales_str($c['warranty'] ?? '', 100),
                'unit_name' => sales_str($c['unit_name'] ?? 'Pcs', 40) ?: 'Pcs',
                'serial_imei' => sales_str($c['serial_imei'] ?? '', 255),
                'quantity' => $qty,
                'unit_price' => $price,
                'discount_amount' => $disc,
                'line_total' => $total,
            ];
        }
        if (!$lines) {
            throw new RuntimeException('Every cart line needs a quantity greater than zero.');
        }
        $subtotal = round($subtotal, 2);
        $discount = sales_amount($_POST['discount_amount'] ?? 0);
        if ($discount > $subtotal) {
            throw new RuntimeException('Discount cannot be more than the subtotal.');
        }
        $vatPercent = min(100.0, sales_amount($_POST['vat_percent'] ?? 0));
        // Included VAT is the part of the price that is tax; otherwise VAT is added on top.
        $taxable = $subtotal - $discount;
        $vat = round($vatInclusive ? $taxable * $vatPercent / (100 + $vatPercent) : $taxable * $vatPercent / 100, 2);
        $net = round($vatInclusive ? $taxable : $taxable + $vat, 2);
        $tendered = sales_amount($_POST['tendered'] ?? 0);
        if ($tendered + 0.004 < $net) {
            throw new RuntimeException('Amount received (' . sales_money($tendered) . ') is less than the total (' . sales_money($net) . ').');
        }
        $method = sales_payment_method('payment_method');
        $customerId = (int) ($_POST['customer_id'] ?? 0);
        $customerId = $customerId > 0 ? $customerId : null;
        $customerName = sales_post_string('customer_name', 190) ?: 'Walk-in Customer';
        $mobile = sales_post_string('mobile', 40);
        $address = $customerId ? (string) (sales_row($conn, 'SELECT address FROM sales_customers WHERE customer_id=?', 'i', [$customerId])['address'] ?? '') : '';
        $userId = (int) ($GLOBALS['user_id'] ?? 0);
        $userName = sales_cut((string) ($GLOBALS['user_name'] ?? ''), 140);
        $today = date('Y-m-d');
        $narration = 'POS sale. Tendered ' . sales_money($tendered) . ', change ' . sales_money($tendered - $net) . '.';

        $conn->begin_transaction();
        $invoiceNo = sales_next_number($conn, 'SI', (int) date('Y'), 'sales_invoice_sequences');
        $invoiceId = sales_insert(
            $conn,
            "INSERT INTO sales_invoices (invoice_no, ref_no, sales_date, bill_type, customer_id, customer_name, mobile, address, sales_person, narration, subtotal, discount_amount, vat_amount, net_amount, cash_received, balance_amount, total_quantity, created_by) "
                . "VALUES (?, 'POS', ?, 'Cash', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?)",
            'ssi' . str_repeat('s', 5) . str_repeat('d', 6) . 'i',
            [$invoiceNo, $today, $customerId, $customerName, $mobile, $address, $userName, $narration, $subtotal, $discount, $vat, $net, $net, round($totalQty, 3), $userId]
        );
        foreach ($lines as $n => $l) {
            sales_insert(
                $conn,
                'INSERT INTO sales_invoice_items (invoice_id, product_id, product_code, product_group, brand, model_caption, description, warranty, unit_name, serial_imei, quantity, unit_price, discount_amount, line_total, line_number) '
                    . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                'ii' . str_repeat('s', 8) . 'dddd' . 'i',
                [$invoiceId, $l['product_id'], $l['product_code'], $l['product_group'], $l['brand'], $l['model_caption'], $l['description'], $l['warranty'], $l['unit_name'], $l['serial_imei'], $l['quantity'], $l['unit_price'], $l['discount_amount'], $l['line_total'], $n + 1]
            );
        }
        if ($net > 0) {
            sales_insert($conn, 'INSERT INTO sales_invoice_payments (invoice_id, payment_method, amount, reference, received_by) VALUES (?, ?, ?, ?, ?)', 'isdsi', [$invoiceId, $method, $net, sales_post_string('payment_reference', 120), $userId]);
        }
        // Counter sales leave stock from the default store (Settings: inv_settings default_store) when sales stock is on.
        if (sales_tables_ready($conn, ['sales_members'])) {
            sales_exec($conn, 'UPDATE sales_invoices SET branch_id=? WHERE invoice_id=?', 'ii', [inv_current_branch($conn), $invoiceId])->close();
            sales_stock_post_invoice($conn, $invoiceId);
        }
        $conn->commit();
        $_SESSION['pos_tendered'][$invoiceId] = $tendered;
        sales_flash_redirect($self . '?receipt=' . $invoiceId, 'success', 'Sale ' . $invoiceNo . ' saved. Change: ' . sales_money($tendered - $net));
    } catch (Throwable $e) {
        try { $conn->rollback(); } catch (Throwable $ignored) {}
        error_log('POS sale failed: ' . $e->getMessage());
        sales_flash_redirect($self, 'error', $e->getMessage());
    }
}

/* ---------- 80 mm receipt, laid out like a Mushak-6.3 counter receipt ---------- */
if (isset($_GET['print']) && $schemaReady) {
    $inv = sales_row($conn, 'SELECT * FROM sales_invoices WHERE invoice_id=?', 'i', [(int) $_GET['print']]);
    if (!$inv) {
        http_response_code(404);
        exit('Sale not found.');
    }
    $items = sales_rows($conn, 'SELECT * FROM sales_invoice_items WHERE invoice_id=? ORDER BY line_number', 'i', [(int) $inv['invoice_id']]);
    $methods = array_column(sales_rows($conn, 'SELECT DISTINCT payment_method FROM sales_invoice_payments WHERE invoice_id=?', 'i', [(int) $inv['invoice_id']]), 'payment_method');
    $tendered = (float) ($_SESSION['pos_tendered'][(int) $inv['invoice_id']] ?? $inv['cash_received']);
    $b = brand_info();
    $heading = $posSetting('pos_receipt_heading', 'MUSHAK-6.3');
    $footer = $posSetting('pos_receipt_footer', '');
    $phones = implode(', ', array_filter([$b['phone'], $b['mobile']]));
    // The VAT rate is not stored: work it back from the amounts (included VAT leaves net equal to subtotal less discount).
    $taxable = (float) $inv['subtotal'] - (float) $inv['discount_amount'];
    $vat = (float) $inv['vat_amount'];
    $base = $vat > 0 && abs((float) $inv['net_amount'] - $taxable) < 0.005 ? $taxable - $vat : $taxable;
    $vatRate = $base > 0 ? rtrim(rtrim(number_format($vat / $base * 100, 2), '0'), '.') : '0';
    $payMode = implode(', ', array_map(fn($m) => ['cash' => 'Cash', 'bank' => 'Card / Bank', 'mobile' => 'Mobile', 'other' => 'Other'][$m] ?? ucfirst($m), $methods)) ?: 'Cash';
    $qty = fn($q) => rtrim(rtrim(number_format((float) $q, 3, '.', ''), '0'), '.');
    ?><!doctype html><html lang="en"><head><meta charset="utf-8"><title><?= sales_h($inv['invoice_no']) ?></title>
    <style>@page{size:80mm auto;margin:3mm}body{font:12px/1.35 Arial,Helvetica,sans-serif;width:74mm;margin:0 auto;color:#000}.c{text-align:center}.r{text-align:right}
    .logo{display:block;max-width:40mm;max-height:18mm;margin:0 auto 4px;filter:grayscale(1)}.head{font-size:12px;margin-top:2px}.name{font-size:16px;font-weight:bold;margin:1px 0}.bin{font-weight:bold;margin-top:3px}
    .meta{margin-top:6px}.meta div{display:flex;justify-content:space-between;gap:6px}.meta b{font-size:13px}table{width:100%;border-collapse:collapse;margin-top:6px}td,th{padding:1px 2px;vertical-align:top}
    .items th{border-top:1px solid #000;border-bottom:1px solid #000;font-weight:normal;font-size:13px;text-align:right}.items th:first-child{text-align:left;border-left:1px solid #000}.items th:last-child{border-right:1px solid #000}
    .items tbody tr:last-child td{padding-bottom:4px}.sum{border-top:1px solid #000}.sum td{font-weight:bold;font-size:13px;padding-top:3px}.sum td.r{font-weight:normal}.sum .net td{border-bottom:1px solid #000;padding-bottom:3px}.sum .net td.r{font-weight:bold}
    .paid td{font-weight:bold;font-size:13px;padding-top:4px}.paid td.r{font-weight:normal;font-size:15px}.foot{margin-top:8px;font-size:11px}@media print{button{display:none}}</style></head>
    <body onload="window.print()"><button onclick="window.print()">Print</button>
    <?php if ($b['logo'] !== ''): ?><img class="logo" src="../../../<?= sales_h($b['logo']) ?>" alt=""><?php endif; ?>
    <div class="c">
    <?php if ($heading !== ''): ?><div class="head"><?= sales_h($heading) ?></div><?php endif; ?>
    <div class="name"><?= sales_h($b['company'] !== '' ? $b['company'] : $b['brand']) ?></div>
    <?php if ($b['address'] !== ''): ?><div><?= nl2br(sales_h($b['address'])) ?></div><?php endif; ?>
    <?php if ($phones !== ''): ?><div>Phone: <?= sales_h($phones) ?></div><?php endif; ?>
    <?php if ($b['bin'] !== ''): ?><div class="bin">BIN : <?= sales_h($b['bin']) ?></div><?php endif; ?>
    </div>
    <div class="meta">
    <div><b>Invoice No.: <?= sales_h($inv['invoice_no']) ?></b></div>
    <div><span>Sale Time: <?= sales_h(date('d-M-Y g:i a', strtotime((string) $inv['created_at']))) ?></span><span>Cashier: <?= sales_h($inv['sales_person']) ?></span></div>
    <div><span>Customer Name: <?= sales_h($inv['customer_name']) ?></span></div>
    <div><span>Phone: <?= sales_h($inv['mobile']) ?></span></div>
    <div><span>Address: <?= sales_h($inv['address'] ?? '') ?></span></div>
    </div>
    <table class="items"><thead><tr><th>Product</th><th>Qty</th><th>RPU</th><th>Value</th></tr></thead><tbody>
    <?php foreach ($items as $it): ?><tr><td><?= sales_h($it['description']) ?><?= (float) $it['discount_amount'] > 0 ? '<br><small>Less ' . sales_money($it['discount_amount']) . '</small>' : '' ?></td><td class="r"><?= $qty($it['quantity']) ?></td><td class="r"><?= $qty($it['unit_price']) ?></td><td class="r"><?= sales_money($it['line_total']) ?></td></tr><?php endforeach; ?>
    </tbody></table>
    <table class="sum"><tr><td>Item Amount :</td><td class="r"><?= sales_money($inv['subtotal']) ?></td></tr>
    <?php if ((float) $inv['discount_amount'] > 0): ?><tr><td>Discount :</td><td class="r">-<?= sales_money($inv['discount_amount']) ?></td></tr><?php endif; ?>
    <?php if ($vat > 0): ?><tr><td>VAT @<?= $vatRate ?>% :</td><td class="r"><?= sales_money($vat) ?></td></tr><?php endif; ?>
    <tr class="net"><td>Net Amount :</td><td class="r"><?= sales_money($inv['net_amount']) ?></td></tr></table>
    <table class="paid"><tr><td>Paid :</td><td class="r"><?= sales_money($tendered) ?></td></tr>
    <?php if ($tendered - (float) $inv['net_amount'] > 0.004): ?><tr><td>Change :</td><td class="r"><?= sales_money($tendered - (float) $inv['net_amount']) ?></td></tr><?php endif; ?>
    <tr><td>Pay Mode :</td><td class="r" style="font-size:13px"><?= sales_h($payMode) ?></td></tr></table>
    <?php if ($footer !== ''): ?><div class="c foot"><?= sales_h($footer) ?></div><?php endif; ?>
    </body></html><?php
    exit;
}

$flash = sales_take_flash();
$receiptId = (int) ($_GET['receipt'] ?? 0);
$products = $schemaReady ? sales_products($conn) : [];
$customers = $schemaReady ? sales_customers($conn) : [];
$today = ['n' => 0, 'total' => 0];
$recent = [];
if ($schemaReady) {
    $today = sales_row($conn, "SELECT COUNT(*) AS n, COALESCE(SUM(net_amount),0) AS total FROM sales_invoices WHERE ref_no='POS' AND status='posted' AND sales_date=CURDATE()") ?: $today;
    $recent = sales_rows($conn, "SELECT invoice_id, invoice_no, customer_name, net_amount, created_at FROM sales_invoices WHERE ref_no='POS' AND status='posted' AND sales_date=CURDATE() ORDER BY invoice_id DESC LIMIT 15");
}

sales_page_start('POS Sales', 'pos');
?>
<div class="titlebar"><h1>POS Sales</h1><div class="toolbar"><span class="badge">Today: <?= (int) $today['n'] ?> sales, <?= sales_money($today['total']) ?></span><?php if ($receiptId > 0): ?><a class="btn primary" target="_blank" href="<?= $self ?>?print=<?= $receiptId ?>">Print receipt</a><a class="btn" target="_blank" href="../sales_invoice/sales_invoice.php?print=<?= $receiptId ?>">A4 invoice</a><?php endif; ?></div></div>
<?= sales_flash_html($flash) ?>
<?php if (!$schemaReady): ?>
<?= sales_schema_missing_html('billing/sales/schema.sql') ?>
<?php else: ?>
<form method="post" id="pos-form" autocomplete="off">
<?= sales_csrf_field() ?><input type="hidden" name="cart_json" id="cart_json">
<div class="bottom" style="margin-top:0;grid-template-columns:minmax(300px,1fr) 360px">
<div>
<div class="grid g3" style="margin-top:0">
<div class="field" style="grid-column:span 2"><label for="scan">Scan code or search product (Enter adds)</label><div class="relative"><input id="scan" placeholder="Product code, model or name" autofocus><div class="suggestions" id="scan-suggestions" hidden></div></div></div>
<div class="field"><label for="scan_qty">Qty</label><input id="scan_qty" type="number" min="0.001" step="0.001" value="1"></div>
</div>
<div class="table-scroll" style="max-height:520px"><table><thead><tr><th>SL</th><th>Item</th><th style="width:90px">Qty</th><th style="width:110px">Price</th><th style="width:100px">Discount</th><th class="num">Amount</th><th></th></tr></thead><tbody id="cart-body"></tbody></table></div>
<?php if ($recent): ?><div class="section"><div class="section-title">Today's recent POS sales</div><table><tbody><?php foreach ($recent as $r): ?><tr><td><?= sales_h($r['invoice_no']) ?></td><td><?= sales_h(substr((string) $r['created_at'], 11, 5)) ?></td><td><?= sales_h($r['customer_name']) ?></td><td class="num"><?= sales_money($r['net_amount']) ?></td><td><a class="btn small" target="_blank" href="<?= $self ?>?print=<?= (int) $r['invoice_id'] ?>">Receipt</a></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
</div>
<div>
<div class="section" style="margin-top:0"><div class="field"><label for="customer_name">Customer</label><div class="relative"><input id="customer_name" name="customer_name" value="Walk-in Customer" maxlength="190"><div class="suggestions" id="customer-suggestions" hidden></div></div><input type="hidden" id="customer_id" name="customer_id"></div>
<div class="field" style="margin-top:6px"><label for="mobile">Mobile</label><input id="mobile" name="mobile" maxlength="40"></div></div>
<div class="totals" style="margin-top:10px">
<div><span>Items</span><strong id="t_items">0</strong></div>
<div><span>Subtotal</span><strong id="t_sub">0.00</strong></div>
<div><label for="discount_amount">Discount</label><input id="discount_amount" name="discount_amount" type="number" min="0" step="0.01" value="0"></div>
<div><label for="vat_percent">VAT %<?= $vatInclusive ? ' (included)' : '' ?></label><input id="vat_percent" name="vat_percent" type="number" min="0" max="100" step="0.01" value="<?= sales_h(rtrim(rtrim(number_format($defaultVat, 2, '.', ''), '0'), '.')) ?>"></div>
<div><span>VAT<?= $vatInclusive ? ' (in price)' : '' ?></span><strong id="t_vat">0.00</strong></div>
<div class="grand" style="font-size:20px"><span>Total</span><strong id="t_net">0.00</strong></div>
<div><label for="payment_method">Payment</label><select id="payment_method" name="payment_method" class="input" style="width:130px"><option value="cash">Cash</option><option value="bank">Card / Bank</option><option value="mobile">Mobile</option><option value="other">Other</option></select></div>
<div><label for="payment_reference">Reference</label><input id="payment_reference" name="payment_reference" maxlength="120" style="text-align:left"></div>
<div><label for="tendered">Received</label><input id="tendered" name="tendered" type="number" min="0" step="0.01" value="0"></div>
<div class="grand"><span>Change</span><strong id="t_change">0.00</strong></div>
</div>
<div class="toolbar" style="margin-top:10px"><button class="btn primary" style="flex:1;padding:12px;font-size:16px">Pay &amp; Save (F9)</button><button type="button" class="btn" id="exact">Exact</button><button type="button" class="btn danger" id="clear-cart">Clear</button></div>
</div></div>
</form>
<?php endif; ?>
<?php
$script = $schemaReady ? '
const products=' . sales_js($products) . ',customers=' . sales_js($customers) . ',vatInclusive=' . ($vatInclusive ? 'true' : 'false') . ';const cart=[];let tenderedTouched=false;
const scan=document.getElementById("scan");
function addProduct(p,q){q=q||Number(document.getElementById("scan_qty").value||1);const ex=cart.find(c=>c.product_id&&c.product_id==p.product_id&&!c.serial_imei);
 if(ex)ex.quantity=Math.round((ex.quantity+q)*1000)/1000;else cart.push({product_id:p.product_id||0,product_code:p.product_code||"",product_group:p.product_group||"",brand:p.brand||"",model_caption:p.model_caption||"",description:p.description,warranty:p.warranty||"",unit_name:p.unit_name||"Pcs",quantity:q,unit_price:Number(p.default_unit_price||0),discount_amount:0,serial_imei:""});
 scan.value="";document.getElementById("scan_qty").value=1;render();scan.focus()}
attachSuggest(scan,document.getElementById("scan-suggestions"),products,["product_code","description","model_caption","brand"],productLabel,p=>addProduct(p));
scan.addEventListener("keydown",e=>{if(e.key!=="Enter")return;e.preventDefault();const t=scan.value.trim().toLowerCase();if(!t)return;
 const exact=products.find(p=>String(p.product_code||"").toLowerCase()===t);if(exact){addProduct(exact);document.getElementById("scan-suggestions").hidden=true;return}
 const m=products.filter(p=>[p.description,p.model_caption,p.product_code].some(v=>String(v||"").toLowerCase().includes(t)));if(m.length===1){addProduct(m[0]);document.getElementById("scan-suggestions").hidden=true}});
attachSuggest(document.getElementById("customer_name"),document.getElementById("customer-suggestions"),customers,["customer_name","mobile","customer_code"],customerLabel,fillCustomer);
document.getElementById("customer_name").addEventListener("input",()=>{document.getElementById("customer_id").value=""});
function totals(){let sub=0,n=0;cart.forEach(c=>{c.line_total=Math.max(0,Math.round((c.quantity*c.unit_price-c.discount_amount)*100)/100);sub+=c.line_total;n+=c.quantity});
 const disc=Math.min(num("discount_amount"),sub),p=num("vat_percent"),vat=Math.round((sub-disc)*p*100/(vatInclusive?100+p:100))/100,net=Math.round((sub-disc+(vatInclusive?0:vat))*100)/100;
 if(!tenderedTouched)document.getElementById("tendered").value=money(net);
 document.getElementById("t_items").textContent=qtyFmt(n).replace(/\\.?0+$/,"");document.getElementById("t_sub").textContent=money(sub);document.getElementById("t_vat").textContent=money(vat);document.getElementById("t_net").textContent=money(net);
 document.getElementById("t_change").textContent=money(Math.max(0,num("tendered")-net));document.getElementById("cart_json").value=JSON.stringify(cart);return net}
function render(){const b=document.getElementById("cart-body");b.innerHTML=cart.length?"":"<tr><td colspan=7 class=empty>Scan or search a product to start a sale.</td></tr>";
 cart.forEach((c,i)=>b.insertAdjacentHTML("beforeend",`<tr><td>${i+1}</td><td>${safe(c.description)}<br><span class=muted>${safe(c.product_code)} ${safe(c.model_caption)}</span></td><td><input type=number min=0.001 step=0.001 value="${c.quantity}" data-i="${i}" data-f="quantity"></td><td><input type=number min=0 step=0.01 value="${c.unit_price}" data-i="${i}" data-f="unit_price"></td><td><input type=number min=0 step=0.01 value="${c.discount_amount}" data-i="${i}" data-f="discount_amount"></td><td class=num data-lt="${i}">${money(c.line_total)}</td><td><button type=button class="btn small danger" data-rm="${i}">X</button></td></tr>`));
 totals();cart.forEach((c,i)=>{const el=document.querySelector(`[data-lt="${i}"]`);if(el)el.textContent=money(c.line_total)})}
document.getElementById("cart-body").addEventListener("input",e=>{const el=e.target.closest("[data-f]");if(!el)return;cart[+el.dataset.i][el.dataset.f]=Math.max(0,Number(el.value||0));totals();document.querySelector(`[data-lt="${el.dataset.i}"]`).textContent=money(cart[+el.dataset.i].line_total)});
document.getElementById("cart-body").addEventListener("click",e=>{const r=e.target.closest("[data-rm]");if(r){cart.splice(+r.dataset.rm,1);render()}});
["discount_amount","vat_percent"].forEach(id=>document.getElementById(id).addEventListener("input",totals));
document.getElementById("tendered").addEventListener("input",()=>{tenderedTouched=true;totals()});
document.getElementById("exact").addEventListener("click",()=>{tenderedTouched=false;totals()});
document.getElementById("clear-cart").addEventListener("click",()=>{if(cart.length&&confirm("Clear the cart?")){cart.length=0;tenderedTouched=false;render()}});
document.getElementById("pos-form").addEventListener("submit",e=>{const net=totals();if(!cart.length){e.preventDefault();alert("The cart is empty.");return}if(num("tendered")+0.004<net){e.preventDefault();alert("Amount received is less than the total.")}});
document.addEventListener("keydown",e=>{if(e.key==="F9"){e.preventDefault();document.getElementById("pos-form").requestSubmit()}});
render();' . ($receiptId > 0 ? 'window.open("' . $self . '?print=' . $receiptId . '","_blank");' : '') : '';
sales_page_end($script);
?>
<?php include "../../../addon/conf/connection_close.php";  ?>
