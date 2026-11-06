<?php
PHP_SAPI === 'cli' || exit; // a developer tool: never from the web
// HTTP helper for building the sample data through the real forms of the sample-build copy, signed in as its admin.
const SB = 'http://localhost/bs_sample_build/billing/';
$GLOBALS['jar'] = sys_get_temp_dir() . '/petabill_sample.jar';

function sb_req($page, ?array $post = null, &$finalUrl = null)
{
    $ch = curl_init(str_starts_with($page, 'http') ? $page : SB . $page);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEFILE => $GLOBALS['jar'], CURLOPT_COOKIEJAR => $GLOBALS['jar'], CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 120]);
    if ($post !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
    }
    $html = (string) curl_exec($ch);
    $finalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($code >= 500 || preg_match('/Fatal error|Uncaught/', $html)) {
        throw new RuntimeException("HTTP $code on $page: " . mb_substr(strip_tags($html), 0, 300));
    }
    return $html;
}

/** The page's form check (csrf_token), read from the page itself. */
function sb_token($page)
{
    $html = sb_req($page);
    if (!preg_match('/name="csrf_token" value="([0-9a-f]+)"/', $html, $m)) {
        throw new RuntimeException("No csrf_token on $page");
    }
    return $m[1];
}

/** Post a form and require the green banner. Returns [message, final url, html]. */
function sb_post($page, array $fields, $label = '', $okPattern = '')
{
    $fields['csrf_token'] ??= sb_token($page);
    $html = sb_req($page, $fields, $url);
    if (preg_match('/<div class="alert (?:alert-)?(success|error|danger)[^"]*"[^>]*>(.*?)<\/div>/s', $html, $m)) {
        $msg = html_entity_decode(strip_tags($m[2]), ENT_QUOTES);
        if ($m[1] !== 'success') {
            throw new RuntimeException(($label ?: $page) . ' refused: ' . $msg);
        }
        echo '  ok  ', $label ?: $page, ' - ', mb_substr($msg, 0, 90), "\n";
        return [$msg, $url, $html];
    }
    if ($okPattern !== '' && preg_match($okPattern, strip_tags($html), $m)) {
        echo '  ok  ', $label ?: $page, ' - ', $m[0], "\n";
        return [$m[0], $url, $html];
    }
    throw new RuntimeException(($label ?: $page) . ': no result banner. ' . mb_substr(trim(preg_replace('/\s+/', ' ', strip_tags($html))), 0, 300));
}

function sb_db()
{
    static $db = null;
    return $db ??= new mysqli('127.0.0.1', 'bsapp', 'SampleBuild2026x', 'bs_sample', 3399);
}

function sb_val($sql)
{
    return sb_db()->query($sql)->fetch_row()[0] ?? null;
}

function sb_id($table, $idCol, $where)
{
    $id = (int) sb_val("SELECT $idCol FROM $table WHERE $where ORDER BY $idCol DESC LIMIT 1");
    if (!$id) {
        throw new RuntimeException("Not found: $table WHERE $where");
    }
    return $id;
}

/** A product's details as the line picker fills them. */
function sb_product($code)
{
    $r = sb_db()->query("SELECT product_id, product_code, product_group, brand, model_caption, description, unit_name, warranty FROM sales_products WHERE product_code='" . sb_db()->real_escape_string($code) . "'")->fetch_assoc();
    if (!$r) {
        throw new RuntimeException("No product $code");
    }
    return $r;
}

/** Save a stock document (inv_doc_engine pages). $lines: [code, qty, price, store, extra fields]. Returns [doc_id, doc_no]. */
function sb_doc($page, array $head, array $lines, $label)
{
    $json = [];
    foreach ($lines as $l) {
        [$code, $qty, $price, $store] = $l + [3 => 'Main Store'];
        $json[] = sb_product($code) + ['quantity' => $qty, 'free_quantity' => 0, 'unit_price' => $price, 'store_name' => $store, 'discount_amount' => 0, 'narration' => ''] + ($l[4] ?? []);
    }
    sb_post($page, $head + ['doc_id' => 0, 'lines_json' => json_encode($json), 'action' => 'save', 'extra_charges' => 0, 'discount_amount' => 0, 'vat_amount' => 0, 'sq' => 'none'], $label);
    $r = sb_db()->query("SELECT doc_id, doc_no FROM inv_docs ORDER BY doc_id DESC LIMIT 1")->fetch_row();
    return [(int) $r[0], $r[1]];
}

/** Header fields for a supplier or customer document. */
function sb_party($kind, $name)
{
    $e = sb_db()->real_escape_string($name);
    $r = $kind === 'supplier'
        ? sb_db()->query("SELECT t.trader_id AS id, COALESCE(g.group_name,'') AS grp, t.trader_name AS name, t.mobile, t.address FROM traders t LEFT JOIN trader_groups g ON g.group_id=t.group_id WHERE t.trader_name='$e'")->fetch_assoc()
        : sb_db()->query("SELECT customer_id AS id, customer_group AS grp, customer_name AS name, mobile, address FROM sales_customers WHERE customer_name='$e'")->fetch_assoc();
    if (!$r) {
        throw new RuntimeException("No $kind $name");
    }
    return ['party_id' => $r['id'], 'party_group' => $r['grp'], 'party_name' => $r['name'], 'mobile' => (string) $r['mobile'], 'address' => (string) $r['address']];
}
