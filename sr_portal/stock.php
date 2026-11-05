<?php
/** Stock & Price: search products for the selling price, lowest allowed price, MRP and stock (no cost prices). */
require_once __DIR__ . '/sr_common.php';
$a = sr_require_login();
$q = trim(mb_substr((string) ($_GET['q'] ?? ''), 0, 60));
$inStock = !empty($_GET['in_stock']);
$rows = $q !== '' || $inStock ? sr_products($q, [], 'retail', 300) : [];
if ($inStock) {
    $rows = array_values(array_filter($rows, fn($p) => (float) $p['stock_all'] > 0.0005));
}
sr_page_start('Stock & Price', 'stock');
echo '<form method="get" class="card row"><div><label for="q">Code, model, brand or group</label><input id="q" name="q" value="' . cp_h($q) . '" autofocus></div>'
    . '<div class="auto"><label><input type="checkbox" name="in_stock" value="1"' . ($inStock ? ' checked' : '') . '> In stock only</label></div><div class="auto"><button class="btn">Search</button></div></form>';
if (!$rows) {
    echo '<p class="muted">' . ($q !== '' || $inStock ? 'No products found.' : 'Search for a product to see its price and stock.') . '</p>';
    sr_page_end();
    exit;
}
echo '<div class="scroll"><table><thead><tr><th>Code</th><th>Group / Brand</th><th>Model</th><th>Warranty</th><th class="num">Sales price</th><th class="num">Dealer</th><th class="num">Lowest</th><th class="num">MRP</th>'
    . '<th class="num">' . cp_h($rows[0]['store']) . '</th><th class="num">All stores</th></tr></thead><tbody>';
foreach ($rows as $p) {
    $s = (float) $p['stock_all'];
    echo '<tr><td>' . cp_h($p['product_code']) . '</td><td>' . cp_h(trim($p['product_group'] . ' / ' . $p['brand'], ' /')) . '</td><td>' . cp_h(trim($p['model_caption'] . ' ' . $p['description'])) . '</td><td>' . cp_h($p['warranty']) . '</td>'
        . '<td class="num">' . cp_money($p['default_unit_price']) . '</td><td class="num">' . ((float) $p['dealer_a'] ? cp_money($p['dealer_a']) : '') . '</td><td class="num">' . ((float) $p['lower_price'] ? cp_money($p['lower_price']) : '') . '</td>'
        . '<td class="num">' . ((float) $p['mrp'] ? cp_money($p['mrp']) : '') . '</td><td class="num">' . cp_qty($p['stock_store']) . '</td><td class="num"><span class="badge ' . ($s > 0.0005 ? 'ok' : 'bad') . '">' . cp_qty($s) . ' ' . cp_h($p['unit_name']) . '</span></td></tr>';
}
echo '</tbody></table></div>';
sr_page_end();
