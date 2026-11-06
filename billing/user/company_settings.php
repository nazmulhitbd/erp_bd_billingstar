<?php
/**
 * Company & Login Settings: one page for what used to be Accounts Configuration and Portal Notices & Settings, plus the
 * sign-in page. Company details stay in acc_settings, so prints, reports and portals keep reading them; the product
 * name and slogan are in client_theme. The page has its own look, so it starts with class ui-common (see role_access.php).
 */
require_once dirname(__DIR__) . '/accounts_info/acc_common.php';
require_once dirname(__DIR__) . '/inventory/inv_common.php'; // inv_fmt_date
require_once dirname(__DIR__, 2) . '/addon/inc/brand.php';
acc_require_ready($conn, 'Company & Login Settings', 'config');

const CS_LOGO_DIR = 'addon/uploads/brand/';
const CS_LOGO_TYPES = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];
const CS_LOGO_MAX = 1572864;
const CS_TEXT = ['company_name' => 120, 'company_address' => 255, 'company_phone' => 60, 'company_mobile' => 60, 'company_email' => 120, 'company_website' => 190, 'company_hours' => 120, 'company_bin' => 60, 'login_title' => 120, 'login_welcome' => 500];

$self = 'company_settings.php';
$root = dirname(__DIR__, 2) . '/';
$tabs = ['company' => ['Company & Brand', 'building'], 'login' => ['Sign-in Page', 'box-arrow-in-right'], 'appearance' => ['Appearance', 'layout-sidebar'], 'notices' => ['Notices', 'megaphone'], 'portal' => ['Customer Portal', 'people'], 'accounts' => ['Accounts', 'calculator']];
$portalReady = sales_tables_ready($conn, ['portal_settings', 'portal_notices']);
if (!$portalReady) {
    unset($tabs['notices'], $tabs['portal']);
}
$tab = isset($tabs[$_GET['tab'] ?? '']) ? $_GET['tab'] : 'company';
$back = $self . '?tab=' . $tab;

$accounts = acc_accounts($conn);
$byType = fn(array $types) => array_values(array_filter($accounts, fn($a) => in_array($a['account_type'], $types, true)));
$accFields = [
    'voucher_prefix' => ['Voucher No Prefix', 'text', 6],
    'cash_account' => ['Default Cash Account', 'account', $byType(['cash'])],
    'receivable_account' => ['Customer (Receivable) Control Account', 'account', $byType(['receivable'])],
    'payable_account' => ['Supplier (Payable) Control Account', 'account', $byType(['payable'])],
    'cc_interest_account' => ['CC Loan Interest Expense Account', 'account', array_values(array_filter($accounts, fn($a) => $a['nature'] === 'expense'))],
    'cc_day_basis' => ['CC Interest Day Basis', 'select', ['365' => '365 days', '360' => '360 days']],
    'require_posting' => ['Vouchers need Posting', 'select', ['1' => 'Yes, save as draft then post', '0' => 'No, post on save']],
];

function cs_put(mysqli $db, $table, $key, $value)
{
    sales_exec($db, "INSERT INTO $table (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)", 'ss', [$key, (string) $value])->close();
}

/** Store an uploaded logo and return its project path, or '' when none was sent. */
function cs_upload_logo($root)
{
    $file = $_FILES['company_logo'] ?? null;
    if (!$file || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return '';
    }
    if ($file['error'] !== UPLOAD_ERR_OK || $file['size'] > CS_LOGO_MAX) {
        throw new RuntimeException('The logo must be smaller than 1.5 MB.');
    }
    $type = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if (!isset(CS_LOGO_TYPES[$type]) || !getimagesize($file['tmp_name'])) {
        throw new RuntimeException('The logo must be a PNG, JPG or WEBP image.');
    }
    $path = CS_LOGO_DIR . 'logo_' . bin2hex(random_bytes(6)) . '.' . CS_LOGO_TYPES[$type];
    if (!is_writable($root . CS_LOGO_DIR) || !move_uploaded_file($file['tmp_name'], $root . $path)) {
        throw new RuntimeException('The logo could not be saved: the web server cannot write to ' . CS_LOGO_DIR);
    }
    return $path;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    sales_require_csrf($back);
    $action = (string) ($_POST['action'] ?? '');
    try {
        if ($action === 'delete_notice') {
            sales_exec($conn, 'DELETE FROM portal_notices WHERE notice_id=?', 'i', [(int) ($_POST['notice_id'] ?? 0)])->close();
            sales_flash_redirect($self . '?tab=notices', 'success', 'Notice deleted.');
        }
        if ($action === 'save_notice') {
            $title = sales_post_string('title', 190);
            $body = sales_post_string('body', 4000);
            $start = sales_valid_date($_POST['starts_on'] ?? '');
            $end = sales_valid_date($_POST['ends_on'] ?? '', true);
            if ($title === '' || $body === '' || $start === null || $end === null || ($end !== '' && $end < $start)) {
                throw new RuntimeException('Enter a title, the notice text and a valid date range.');
            }
            $id = (int) ($_POST['notice_id'] ?? 0);
            $vals = [$title, $body, $start, $end ?: null, !empty($_POST['is_active']) ? 1 : 0];
            if ($id) {
                sales_exec($conn, 'UPDATE portal_notices SET title=?, body=?, starts_on=?, ends_on=?, is_active=? WHERE notice_id=?', 'ssssii', [...$vals, $id])->close();
            } else {
                if (!empty($_POST['is_active'])) {
                    notify_customer(null, $title, $body); // the Portal Notices tab is what customers see on their dashboard
                }
                sales_insert($conn, 'INSERT INTO portal_notices (title, body, starts_on, ends_on, is_active, created_by) VALUES (?, ?, ?, ?, ?, ?)', 'ssssii', [...$vals, (int) ($GLOBALS['user_id'] ?? 0)]);
            }
            sales_flash_redirect($self . '?tab=notices', 'success', 'Notice saved.');
        }
        if ($action !== 'edit_settings') {
            throw new RuntimeException('Unknown action.');
        }

        $email = sales_post_string('company_email', 120);
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Enter a valid email address.');
        }
        $website = sales_post_string('company_website', 190);
        if ($website !== '' && !preg_match('#^https?://#i', $website)) {
            $website = 'https://' . $website;
        }
        if ($website !== '' && !filter_var($website, FILTER_VALIDATE_URL)) {
            throw new RuntimeException('Enter a valid website address.');
        }
        $color = strtolower(sales_post_string('login_color', 7));
        if (!preg_match('/^#[0-9a-f]{6}$/', $color)) {
            throw new RuntimeException('Pick a valid brand colour.');
        }
        $brand = sales_post_string('brand_name', 100);
        if ($brand === '') {
            throw new RuntimeException('Enter the brand name.');
        }
        $shell = (string) ($_POST['menu_style'] ?? basename(perm_shell(), '.php'));
        if (!isset(PERM_SHELLS[$shell])) {
            throw new RuntimeException('Pick a menu layout.');
        }
        $shellChanged = $shell . '.php' !== perm_shell();
        $accValues = [];
        foreach ($accFields as $key => [$label, $type, $extra]) {
            $value = trim((string) ($_POST[$key] ?? ''));
            if ($type === 'text') {
                $value = sales_cut($value, $extra);
                if (!preg_match('/^[A-Z0-9]{1,6}$/', $value)) {
                    throw new RuntimeException('Voucher prefix must be 1 to 6 capital letters or digits.');
                }
            } elseif ($type === 'account') {
                if ($value !== '' && !in_array((int) $value, array_map('intval', array_column($extra, 'account_id')), true)) {
                    throw new RuntimeException('Pick a valid account for ' . $label . '.');
                }
            } elseif (!array_key_exists($value, $extra)) {
                throw new RuntimeException('Pick a valid value for ' . $label . '.');
            }
            $accValues[$key] = $value;
        }

        $oldLogo = acc_setting($conn, 'company_logo');
        $logo = cs_upload_logo($root);
        $logoChanged = $logo !== '' || isset($_POST['remove_logo']);

        $conn->begin_transaction();
        foreach (CS_TEXT as $key => $max) {
            $value = ['company_email' => $email, 'company_website' => $website][$key] ?? sales_post_string($key, $max);
            cs_put($conn, 'acc_settings', $key, $value);
        }
        cs_put($conn, 'acc_settings', 'login_color', $color);
        cs_put($conn, 'acc_settings', 'login_show_notices', isset($_POST['login_show_notices']) ? '1' : '0');
        cs_put($conn, 'acc_settings', 'login_show_portals', isset($_POST['login_show_portals']) ? '1' : '0');
        if ($logoChanged) {
            cs_put($conn, 'acc_settings', 'company_logo', $logo);
        }
        foreach ($accValues as $key => $value) {
            cs_put($conn, 'acc_settings', $key, $value);
        }
        sales_exec($conn, 'UPDATE client_theme SET brand_name=?, slogan=?, index_title=?, index_heading=?, main_title=?, menu_style=?', 'ssssss', [$brand, sales_post_string('slogan', 100), $brand, $brand, $brand, $shell])->close();
        if ($portalReady) {
            cs_put($conn, 'portal_settings', 'portal_title', sales_post_string('portal_title', 120));
            cs_put($conn, 'portal_settings', 'welcome_text', sales_post_string('welcome_text', 500));
            cs_put($conn, 'portal_settings', 'allow_signup', isset($_POST['allow_signup']) ? '1' : '0');
        }
        $conn->commit();
        if ($logoChanged && $oldLogo !== '' && str_starts_with($oldLogo, CS_LOGO_DIR) && is_file($root . $oldLogo)) {
            unlink($root . $oldLogo);
        }
        // A new layout takes over the whole window, not just this frame (company_settings.js).
        $taxId = trim((string) ($_POST['company_bin'] ?? ''));
        $taxNote = $taxId !== '' && !bs_tax_id_valid(bs_country_code(), $taxId) ? ' Check the ' . bs_country()['company_tax_id'] . ': it does not match the usual ' . bs_country()['name'] . ' format (' . (BS_TAX_ID_HINT[bs_country_code()] ?? '') . ').' : '';
        sales_flash_redirect($back . ($shellChanged ? '&relayout=1' : ''), 'success', ($shellChanged ? 'Settings saved. Opening the ' . strtolower(PERM_SHELLS[$shell]) . ' layout…' : 'Settings saved.') . $taxNote);
    } catch (RuntimeException $e) {
        try { $conn->rollback(); } catch (Throwable $ignored) {}
        sales_flash_redirect($back, 'error', $e->getMessage());
    }
}

$flash = sales_take_flash();
$b = brand_info();
$v = fn($key) => sales_h(acc_setting($conn, $key));
$portal = $portalReady ? array_column(sales_rows($conn, 'SELECT setting_key, setting_value FROM portal_settings'), 'setting_value', 'setting_key') : [];
$notices = $portalReady ? sales_rows($conn, 'SELECT * FROM portal_notices ORDER BY starts_on DESC, notice_id DESC LIMIT 200') : [];
$editNotice = $portalReady && isset($_GET['edit']) ? sales_row($conn, 'SELECT * FROM portal_notices WHERE notice_id=?', 'i', [(int) $_GET['edit']]) : null;
$today = date('Y-m-d');
?>
<!doctype html>
<html lang="en" class="ui-common">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Company &amp; Login Settings</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="company_settings.css?v=<?= (int) @filemtime(__DIR__ . '/company_settings.css') ?>">
</head>
<body<?= isset($_GET['relayout']) ? ' data-relayout="../' . sales_h(perm_shell()) . '"' : '' ?>>
<header class="cs-head">
  <div><h1>Company &amp; Login Settings</h1><p>Your company details, sign-in page, menu layout, notices, customer portal and accounts defaults, in one place.</p></div>
  <a class="cs-btn" href="../../index.php" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right"></i> Open sign-in page</a>
</header>
<?php if ($flash): ?><div class="cs-flash <?= $flash['kind'] === 'success' ? 'ok' : 'err' ?>" data-popup="<?= $flash['kind'] === 'success' ? 'success' : 'error' ?>" role="status"><?= sales_h($flash['message']) ?></div><?php endif; ?>
<nav class="cs-tabs" role="tablist" aria-label="Settings">
<?php foreach ($tabs as $key => [$label, $icon]): ?><button type="button" role="tab" id="tab-<?= $key ?>" data-tab="<?= $key ?>" aria-controls="panel-<?= $key ?>" aria-selected="<?= $tab === $key ? 'true' : 'false' ?>"><i class="bi bi-<?= $icon ?>"></i> <?= $label ?></button><?php endforeach; ?>
</nav>

<div class="cs-layout">
<div class="cs-main">
<form method="post" enctype="multipart/form-data" id="cs-form" autocomplete="off"><?= sales_csrf_field() ?>
<section class="cs-panel" role="tabpanel" id="panel-company" aria-labelledby="tab-company"<?= $tab === 'company' ? '' : ' hidden' ?>>
  <h2>Brand</h2>
  <div class="cs-grid">
    <div class="cs-field"><label for="brand_name">Brand name *</label><input id="brand_name" name="brand_name" maxlength="100" required value="<?= sales_h($b['brand']) ?>" data-preview="brand"><small>Top of the menu bar and the sign-in page.</small></div>
    <div class="cs-field"><label for="slogan">Slogan</label><input id="slogan" name="slogan" maxlength="100" value="<?= sales_h($b['slogan']) ?>" data-preview="slogan"></div>
    <div class="cs-field wide cs-logo">
      <label for="company_logo">Logo</label>
      <div class="cs-logo-row"><?php if ($b['logo'] !== ''): ?><img src="../../<?= sales_h($b['logo']) ?>" alt="Current logo"><?php endif; ?><input type="file" id="company_logo" name="company_logo" accept="image/png,image/jpeg,image/webp"></div>
      <small>PNG, JPG or WEBP, up to 1.5 MB. A wide logo works best.</small>
      <?php if ($b['logo'] !== ''): ?><label class="cs-check"><input type="checkbox" name="remove_logo"> Remove the logo</label><?php endif; ?>
    </div>
  </div>
  <h2>Company details</h2>
  <p class="cs-note">Shown on the sign-in page, prints, reports and portals. Leave a box empty to hide it.</p>
  <div class="cs-grid">
    <div class="cs-field"><label for="company_name">Company name</label><input id="company_name" name="company_name" maxlength="120" value="<?= $v('company_name') ?>" data-preview="company"></div>
    <div class="cs-field"><label for="company_bin"><?= sales_h(bs_country()['company_tax_id'] . ' (' . bs_country()['tax'] . ' registration)') ?></label><input id="company_bin" name="company_bin" maxlength="60" placeholder="000108157-0101" value="<?= $v('company_bin') ?>"><small>Printed on POS receipts.</small></div>
    <div class="cs-field"><label for="company_email">Email</label><input type="email" id="company_email" name="company_email" maxlength="120" value="<?= $v('company_email') ?>" data-preview="email"></div>
    <div class="cs-field"><label for="company_phone">Phone</label><input id="company_phone" name="company_phone" maxlength="60" value="<?= $v('company_phone') ?>" data-preview="phone"></div>
    <div class="cs-field"><label for="company_mobile">Mobile</label><input id="company_mobile" name="company_mobile" maxlength="60" value="<?= $v('company_mobile') ?>" data-preview="mobile"></div>
    <div class="cs-field"><label for="company_website">Website</label><input id="company_website" name="company_website" maxlength="190" placeholder="www.example.com" value="<?= $v('company_website') ?>" data-preview="website"></div>
    <div class="cs-field"><label for="company_hours">Office hours</label><input id="company_hours" name="company_hours" maxlength="120" placeholder="Sat to Thu, 9 AM to 7 PM" value="<?= $v('company_hours') ?>" data-preview="hours"></div>
    <div class="cs-field wide"><label for="company_address">Address</label><input id="company_address" name="company_address" maxlength="255" value="<?= $v('company_address') ?>" data-preview="address"></div>
  </div>
</section>

<section class="cs-panel" role="tabpanel" id="panel-login" aria-labelledby="tab-login"<?= $tab === 'login' ? '' : ' hidden' ?>>
  <h2>Sign-in page</h2>
  <div class="cs-grid">
    <div class="cs-field"><label for="login_title">Headline</label><input id="login_title" name="login_title" maxlength="120" placeholder="Company name" value="<?= $v('login_title') ?>" data-preview="title"></div>
    <div class="cs-field"><label for="login_color">Brand colour</label><input type="color" id="login_color" name="login_color" value="<?= sales_h($b['color']) ?>"><small>Text colour adjusts itself so it stays readable.</small></div>
    <div class="cs-field wide"><label for="login_welcome">Welcome text</label><textarea id="login_welcome" name="login_welcome" maxlength="500" rows="3" data-preview="welcome"><?= $v('login_welcome') ?></textarea></div>
    <label class="cs-check wide"><input type="checkbox" name="login_show_portals"<?= $b['show_portals'] ? ' checked' : '' ?>> Show the links to the customer and sales staff portals</label>
    <?php if ($portalReady): ?><label class="cs-check wide"><input type="checkbox" name="login_show_notices"<?= $b['show_notices'] ? ' checked' : '' ?>> Show today's notices on the sign-in page (anyone can read them there)</label><?php endif; ?>
  </div>
</section>

<section class="cs-panel" role="tabpanel" id="panel-appearance" aria-labelledby="tab-appearance"<?= $tab === 'appearance' ? '' : ' hidden' ?>>
  <h2>Menu layout</h2>
  <p class="cs-note">Everyone opens this layout after signing in. Both show the same menus, pages and permissions.</p>
  <div class="cs-layouts" role="radiogroup" aria-label="Menu layout">
<?php $currentShell = basename(perm_shell(), '.php'); foreach (['topmain' => ['Top menu', 'Menu categories across the top, pages in drop-down panels. Most room for wide reports.'], 'treemain' => ['Side menu', 'A dark sidebar with categories that open in place and fold down to icons. Feels like a desktop ERP.']] as $key => [$label, $about]): ?>
    <label class="cs-layout-card">
      <input type="radio" name="menu_style" value="<?= $key ?>"<?= $currentShell === $key ? ' checked' : '' ?>>
      <span class="cs-layout-pic cs-pic-<?= $key ?>" aria-hidden="true"><i></i><b></b><em></em></span>
      <span class="cs-layout-text"><b><?= sales_h($label) ?></b><small><?= sales_h($about) ?></small><code><?= $key ?>.php</code></span>
    </label>
<?php endforeach; ?>
  </div>
  <p class="cs-note">After saving, this window reloads into the chosen layout. The side menu remembers, per browser, whether it was folded to icons.</p>
</section>

<?php if ($portalReady): ?>
<section class="cs-panel" role="tabpanel" id="panel-portal" aria-labelledby="tab-portal"<?= $tab === 'portal' ? '' : ' hidden' ?>>
  <h2>Customer portal</h2>
  <div class="cs-grid">
    <div class="cs-field"><label for="portal_title">Portal title</label><input id="portal_title" name="portal_title" maxlength="120" value="<?= sales_h($portal['portal_title'] ?? '') ?>"></div>
    <div class="cs-field wide"><label for="welcome_text">Welcome text on the portal sign-in page</label><textarea id="welcome_text" name="welcome_text" maxlength="500" rows="3"><?= sales_h($portal['welcome_text'] ?? '') ?></textarea></div>
    <label class="cs-check wide"><input type="checkbox" name="allow_signup"<?= ($portal['allow_signup'] ?? '1') === '1' ? ' checked' : '' ?>> Customers can create their own portal login</label>
  </div>
  <p class="cs-note">The portal shows the phone and email from Company &amp; Brand. <a href="../../customer_portal/login.php" target="_blank" rel="noopener">Open the portal</a></p>
</section>
<?php endif; ?>

<section class="cs-panel" role="tabpanel" id="panel-accounts" aria-labelledby="tab-accounts"<?= $tab === 'accounts' ? '' : ' hidden' ?>>
  <h2>Accounts defaults</h2>
  <div class="cs-grid">
<?php foreach ($accFields as $key => [$label, $type, $extra]): $value = acc_setting($conn, $key); ?>
    <div class="cs-field"><label for="<?= $key ?>"><?= sales_h($label) ?></label>
<?php if ($type === 'text'): ?><input id="<?= $key ?>" name="<?= $key ?>" maxlength="<?= (int) $extra ?>" value="<?= sales_h($value) ?>">
<?php elseif ($type === 'account'): ?><select id="<?= $key ?>" name="<?= $key ?>"><?= acc_account_options($extra, (int) $value, 'Not set') ?></select>
<?php else: ?><select id="<?= $key ?>" name="<?= $key ?>"><?= acc_options($extra, $value) ?></select><?php endif; ?>
    </div>
<?php endforeach; ?>
  </div>
  <p class="cs-note">Voucher numbers are prefix + type + year + serial, for example <?= sales_h(acc_setting($conn, 'voucher_prefix', 'DES')) ?>JUR<?= date('Y') ?>000001.</p>
</section>
<div class="cs-save" id="cs-save"<?= $tab === 'notices' ? ' hidden' : '' ?>><span id="cs-state">No changes</span><button class="cs-btn primary" name="action" value="edit_settings">Save settings</button></div>
</form>

<?php if ($portalReady): ?>
<section class="cs-panel" role="tabpanel" id="panel-notices" aria-labelledby="tab-notices"<?= $tab === 'notices' ? '' : ' hidden' ?>>
  <h2><?= $editNotice ? 'Edit notice' : 'New notice' ?></h2>
  <p class="cs-note">Notices appear on the customer portal dashboard between these dates.</p>
  <form method="post"><?= sales_csrf_field() ?><input type="hidden" name="notice_id" value="<?= (int) ($editNotice['notice_id'] ?? 0) ?>">
  <div class="cs-grid">
    <div class="cs-field wide"><label for="title">Title *</label><input id="title" name="title" maxlength="190" required value="<?= sales_h($editNotice['title'] ?? '') ?>"></div>
    <div class="cs-field"><label for="starts_on">Show from *</label><input type="date" id="starts_on" name="starts_on" required value="<?= sales_h($editNotice['starts_on'] ?? $today) ?>"></div>
    <div class="cs-field"><label for="ends_on">Show until</label><input type="date" id="ends_on" name="ends_on" value="<?= sales_h($editNotice['ends_on'] ?? '') ?>"></div>
    <div class="cs-field wide"><label for="body">Notice *</label><textarea id="body" name="body" maxlength="4000" rows="3" required><?= sales_h($editNotice['body'] ?? '') ?></textarea></div>
    <label class="cs-check wide"><input type="checkbox" name="is_active"<?= (int) ($editNotice['is_active'] ?? 1) ? ' checked' : '' ?>> Active</label>
  </div>
  <div class="cs-row"><button class="cs-btn primary" name="action" value="save_notice"><?= $editNotice ? 'Save notice' : 'Add notice' ?></button><?php if ($editNotice): ?><a class="cs-btn" href="<?= $self ?>?tab=notices">Cancel</a><?php endif; ?></div>
  </form>
  <h2>All notices</h2>
  <div class="cs-table"><table><thead><tr><th scope="col">Title</th><th scope="col">From</th><th scope="col">Until</th><th scope="col">Status</th><th scope="col"><span class="cs-sr">Actions</span></th></tr></thead><tbody>
<?php if (!$notices): ?><tr><td colspan="5" class="cs-empty">No notices yet.</td></tr><?php endif; ?>
<?php foreach ($notices as $n): $live = (int) $n['is_active'] && $n['starts_on'] <= $today && (!$n['ends_on'] || $n['ends_on'] >= $today); ?>
  <tr><td><?= sales_h($n['title']) ?></td><td><?= inv_fmt_date($n['starts_on']) ?></td><td><?= inv_fmt_date($n['ends_on']) ?></td><td><span class="cs-badge<?= $live ? ' on' : '' ?>"><?= $live ? 'Showing' : 'Not showing' ?></span></td>
  <td class="cs-actions"><a class="cs-btn small" href="<?= $self ?>?tab=notices&amp;edit=<?= (int) $n['notice_id'] ?>">Edit</a><form method="post" data-confirm="Delete this notice?"><?= sales_csrf_field() ?><input type="hidden" name="notice_id" value="<?= (int) $n['notice_id'] ?>"><button class="cs-btn small danger" name="action" value="delete_notice">Delete</button></form></td></tr>
<?php endforeach; ?>
  </tbody></table></div>
</section>
<?php endif; ?>
</div>

<aside class="cs-preview" id="cs-preview" aria-label="Sign-in page preview"<?= in_array($tab, ['company', 'login'], true) ? '' : ' hidden' ?>>
  <h2>Preview</h2>
  <div class="cs-mini" id="cs-mini">
    <div class="cs-mini-brand"><b data-show="brand"></b><span data-show="slogan"></span></div>
    <div class="cs-mini-title" data-show="headline"></div>
    <p data-show="welcome"></p>
    <ul>
      <li data-row="address"><i class="bi bi-geo-alt"></i><span data-show="address"></span></li>
      <li data-row="phone"><i class="bi bi-telephone"></i><span data-show="phone"></span></li>
      <li data-row="mobile"><i class="bi bi-phone"></i><span data-show="mobile"></span></li>
      <li data-row="email"><i class="bi bi-envelope"></i><span data-show="email"></span></li>
      <li data-row="website"><i class="bi bi-globe2"></i><span data-show="website"></span></li>
      <li data-row="hours"><i class="bi bi-clock"></i><span data-show="hours"></span></li>
    </ul>
  </div>
</aside>
</div>
<script src="company_settings.js?v=<?= (int) @filemtime(__DIR__ . '/company_settings.js') ?>"></script>
</body>
</html>
<?php include "../../addon/conf/connection_close.php";  ?>
