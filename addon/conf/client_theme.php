<?php
/** Company name, titles, colours and mail settings for every page, from client_theme (Settings > Company Settings). */
$client_theme_res = mysql_query_moon("SELECT index_title, index_heading, main_title, session_idle_time, default_record_no, brand_name, slogan,
                  index_bg_color, footer_right_bg_color, body_bgcolor_color,
                  mailto, mailcc, mailbcc, mailreport, customize6, customize5, customize3, customize4
                  FROM client_theme WHERE client_theme_id=1");
$client_theme_row = ($client_theme_res ? mysql_fetch_assoc($client_theme_res) : null) ?: [];
$index_title = $client_theme_row['index_title'] ?? '';
$index_heading = $client_theme_row['index_heading'] ?? '';
$main_title = $client_theme_row['main_title'] ?? '';
$session_idle_time = $client_theme_row['session_idle_time'] ?? '';
$default_record = $client_theme_row['default_record_no'] ?? '';
$top_menu_deading = $client_theme_row['brand_name'] ?? '';
// The product was called Billing Star; an install that still carries that default shows the new name, PetaBill.
foreach (['index_title', 'index_heading', 'main_title', 'top_menu_deading'] as $brandVar) {
    if (in_array(trim((string) $$brandVar), ['', 'Billing Star'], true)) {
        $$brandVar = $brandVar === 'main_title' || $brandVar === 'index_title' ? 'PetaBill ERP' : 'PetaBill';
    }
}
$top_menu_slogan = $client_theme_row['slogan'] ?? '';
$index_bgcolor = $client_theme_row['index_bg_color'] ?? '';
$statusbar_bgcolor = $client_theme_row['footer_right_bg_color'] ?? '';
$body_bgcolor = $client_theme_row['body_bgcolor_color'] ?? '';
$mailto = $client_theme_row['mailto'] ?? '';
$mailcc = $client_theme_row['mailcc'] ?? '';
$mailbcc = $client_theme_row['mailbcc'] ?? '';
$mailreport = $client_theme_row['mailreport'] ?? '';
$brand = $client_theme_row['customize6'] ?? '';
$brandweb = $client_theme_row['customize5'] ?? '';
$cright = $client_theme_row['customize3'] ?? '';
$crightlink = $client_theme_row['customize4'] ?? '';
