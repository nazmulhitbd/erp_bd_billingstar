<?php
/**
 * Languages of PetaBill ERP. PetaBill Cloud decides each licence's language (Services > the licence) and its wording
 * (ERP Countries & Languages); a person may still switch between that language and English (?lang=en / ?lang=auto).
 *
 * Pages are written in English. On the way out, every visible text that is exactly a known phrase (menu entries, buttons,
 * labels, table headings, print captions) is replaced by the language pack, addon/lang/<code>.php, and by any wording an
 * Admin saved in bs_ui_text. Scripts, styles, form values and data are never touched, so a missing phrase simply stays in
 * English. Urdu pages are laid out right to left.
 */

const BS_LANGUAGES = [
    'en' => ['English', 'English', 'ltr'],
    'bn' => ['Bangla', 'বাংলা', 'ltr'],
    'hi' => ['Hindi', 'हिन्दी', 'ltr'],
    'ur' => ['Urdu', 'اردو', 'rtl'],
    'ne' => ['Nepali', 'नेपाली', 'ltr'],
    'ms' => ['Malay', 'Bahasa Melayu', 'ltr'],
    'th' => ['Thai', 'ไทย', 'ltr'],
    'vi' => ['Vietnamese', 'Tiếng Việt', 'ltr'],
    'ar' => ['Arabic', 'العربية', 'rtl'],
];

/** The country's own language. */
const BS_COUNTRY_LANGUAGE = ['BD' => 'bn', 'IN' => 'hi', 'PK' => 'ur', 'NP' => 'ne', 'MY' => 'ms', 'TH' => 'th', 'VN' => 'vi'];

/** The language PetaBill Cloud gives this licence (English when not connected). */
function bs_licence_lang()
{
    $pack = function_exists('licence_locale') ? licence_locale() : null;
    $code = (string) ($pack['language']['code'] ?? 'en');
    return isset(BS_LANGUAGES[$code]) ? $code : 'en';
}

/** The language of this request: the licence's, or English for a person who switched to it (the only other choice). */
function bs_lang()
{
    static $lang = null;
    if ($lang !== null) {
        return $lang;
    }
    $mine = bs_licence_lang();
    return $lang = (($_COOKIE['bs_lang'] ?? '') === 'en' ? 'en' : $mine);
}

/** ?lang=xx on any page: keep the user's own choice for a year (no page reload needed: this request already uses it). */
function bs_lang_from_query()
{
    $want = is_string($_GET['lang'] ?? null) ? $_GET['lang'] : '';
    if ($want === '' || headers_sent()) {
        return;
    }
    if ($want === 'auto') {
        setcookie('bs_lang', '', ['expires' => time() - 3600, 'path' => '/', 'samesite' => 'Lax', 'httponly' => true]);
        unset($_COOKIE['bs_lang']);
    } elseif ($want === 'en' || $want === bs_licence_lang()) { // English, or back to the licence's language
        setcookie('bs_lang', $want, ['expires' => time() + 365 * 86400, 'path' => '/', 'samesite' => 'Lax', 'httponly' => true]);
        $_COOKIE['bs_lang'] = $want;
    }
}

/** English phrase => translation, for a language: the built-in pack with the Admin's own wording on top. */
function bs_lang_map($lang)
{
    static $cache = [];
    if (isset($cache[$lang])) {
        return $cache[$lang];
    }
    $map = [];
    $pack = function_exists('licence_locale') ? licence_locale() : null;
    if ($lang !== 'en' && ($pack['language']['code'] ?? '') === $lang && is_array($pack['phrases'] ?? null)) {
        return $cache[$lang] = $pack['phrases']; // PetaBill Cloud's wording, including its Admins' changes
    }
    $file = dirname(__DIR__) . '/lang/' . $lang . '.php';
    if ($lang !== 'en' && preg_match('/^[a-z]{2}$/', $lang) && is_file($file)) {
        $map = (array) require $file; // the built-in copy, until the package arrives
    }
    return $cache[$lang] = $map;
}

/** One phrase in the user's language (for PHP that builds text itself). */
function bs_t($text)
{
    return bs_lang_map(bs_lang())[$text] ?? $text;
}

/** A whole page in the user's language. Only whole text pieces and a few attributes are matched. */
function bs_translate_html($html)
{
    $lang = bs_lang();
    if ($lang === 'en' || $html === '') {
        return $html;
    }
    $map = bs_lang_map($lang);
    if (!$map) {
        return $html;
    }
    $swap = function ($text) use ($map) {
        $key = trim(html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($key === '' || !isset($map[$key])) {
            return null;
        }
        $lead = strspn($text, " \t\r\n");
        $trail = strlen($text) - strlen(rtrim($text));
        return substr($text, 0, $lead) . htmlspecialchars($map[$key], ENT_QUOTES | ENT_HTML5, 'UTF-8', false) . ($trail ? substr($text, -$trail) : '');
    };
    $parts = preg_split('~(<script\b.*?</script>|<style\b.*?</style>|<textarea\b.*?</textarea>)~is', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
    foreach ($parts as $i => $part) {
        if ($i % 2) {
            continue;
        }
        // Text between tags: "Save", "Sales Invoice / Bill", "Net Payable Amount"...
        $part = preg_replace_callback('/>([^<>]+)</u', fn($m) => '>' . ($swap($m[1]) ?? $m[1]) . '<', $part);
        // What people read in attributes: placeholders, tooltips, button captions.
        $part = preg_replace_callback('/\b(placeholder|title|aria-label|data-label)="([^"]*)"/u', fn($m) => $m[1] . '="' . ($swap($m[2]) ?? $m[2]) . '"', $part);
        $part = preg_replace_callback('/(<input\b[^>]*\btype="(?:submit|button)"[^>]*\bvalue=")([^"]*)"/iu', fn($m) => $m[1] . ($swap($m[2]) ?? $m[2]) . '"', $part);
        $parts[$i] = $part;
    }
    $html = implode('', $parts);
    // The page's language and direction, for screen readers, fonts and right-to-left scripts.
    $dir = BS_LANGUAGES[$lang][2];
    $html = preg_replace('/<html\b([^>]*?)\blang="[^"]*"/i', '<html$1lang="' . $lang . '"' . ($dir === 'rtl' ? ' dir="rtl"' : ''), $html, 1);
    if ($dir === 'rtl') { // charts, figures and codes read left to right even on a right-to-left page
        $html = preg_replace('/<\/head>/i', '<style>[dir=rtl] svg,[dir=rtl] .num,[dir=rtl] input[type=number],[dir=rtl] .dn-next{direction:ltr;unicode-bidi:isolate}</style></head>', $html, 1);
    }
    return $html;
}

if (PHP_SAPI !== 'cli') {
    bs_lang_from_query(); // a user picking a language with ?lang=xx, on any page
}
