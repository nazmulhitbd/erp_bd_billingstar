<?php
/**
 * Company and brand details for the public sign-in page and the shell header. Company details stay in acc_settings
 * (prints, reports and the portals already read them), the product name and slogan in client_theme. Edited on
 * Settings > Company & Login Settings (billing/user/company_settings.php).
 */
require_once __DIR__ . '/perm.php';

const BRAND_DEFAULT_COLOR = '#2563eb';

function brand_info()
{
    static $b = null;
    if ($b !== null) {
        return $b;
    }
    $s = array_column(perm_q("SELECT setting_key, setting_value FROM acc_settings WHERE setting_key LIKE 'company\\_%' OR setting_key LIKE 'login\\_%'"), 'setting_value', 'setting_key');
    $theme = perm_q('SELECT brand_name, slogan FROM client_theme LIMIT 1')[0] ?? [];
    $get = fn($key) => trim((string) ($s[$key] ?? ''));
    $color = preg_match('/^#[0-9a-f]{6}$/i', $get('login_color')) ? $get('login_color') : BRAND_DEFAULT_COLOR;
    $logo = $get('company_logo');
    $b = [
        'brand' => trim((string) ($theme['brand_name'] ?? '')) ?: 'Billing Star',
        'slogan' => trim((string) ($theme['slogan'] ?? '')),
        'company' => $get('company_name'),
        'address' => $get('company_address'),
        'phone' => $get('company_phone'),
        'mobile' => $get('company_mobile'),
        'email' => $get('company_email'),
        'website' => $get('company_website'),
        'hours' => $get('company_hours'),
        'bin' => $get('company_bin'),
        'logo' => $logo !== '' && is_file(dirname(__DIR__, 2) . '/' . $logo) ? $logo : '',
        'title' => $get('login_title'),
        'welcome' => $get('login_welcome'),
        'color' => $color,
        'color_ink' => brand_ink($color),
        'show_notices' => $get('login_show_notices') === '1',
        'show_portals' => $get('login_show_portals') !== '0',
    ];
    return $b;
}

/** Dark or white text, whichever reads better on the brand colour. */
function brand_ink($hex)
{
    $channel = function ($v) {
        $v /= 255;
        return $v <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4;
    };
    [$r, $g, $b] = array_map('hexdec', str_split(substr($hex, 1), 2));
    $l = 0.2126 * $channel($r) + 0.7152 * $channel($g) + 0.0722 * $channel($b);
    return (1.05 / ($l + 0.05)) >= 4.5 ? '#ffffff' : '#0f172a';
}

/** Notices live today, for the sign-in page when that option is on. */
function brand_notices()
{
    return perm_q('SELECT title, body FROM portal_notices WHERE is_active=1 AND starts_on<=CURDATE() AND (ends_on IS NULL OR ends_on>=CURDATE()) ORDER BY starts_on DESC, notice_id DESC LIMIT 3');
}
