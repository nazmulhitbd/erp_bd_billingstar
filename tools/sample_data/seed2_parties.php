<?php
PHP_SAPI === 'cli' || exit; // a developer tool: never from the web
// Stage 2: a second store, suppliers, dealers and credit customers (through Trader Information, which also makes their
// customer records), territories and sales representatives. Invented names; phone numbers in a clearly fake range.
require __DIR__ . '/seed_lib.php';

sb_db()->query("INSERT IGNORE INTO sales_stores (store_name) VALUES ('Showroom Store')");
echo "  ok  store Showroom Store\n";

$traders = [ // category, group, name, contact, mobile, address, opening balance
    [1, 'Local Suppliers', 'Delta Distribution Ltd.', 'Sales Desk', '01000000101', '8 Warehouse Lane, Tejgaon, Dhaka', 0],
    [1, 'Local Suppliers', 'Meridian Tech Supply', 'Accounts', '01000000102', '22 Market Road, Motijheel, Dhaka', 0],
    [1, 'Importers', 'Blue Harbor Trading Co.', 'Import Desk', '01000000103', '5 Port Road, Agrabad, Chattogram', 0],
    [2, 'Dhaka Dealers', 'City Gadget Point', 'Store Manager', '01000000201', 'Shop 12, Sample Plaza, Dhanmondi, Dhaka', 0],
    [2, 'Chattogram Dealers', 'Riverside Electronics', 'Owner', '01000000202', '31 River View Road, Chattogram', 0],
    [3, 'Corporate', 'Greenfield School', 'Admin Office', '01000000301', '1 School Road, Uttara, Dhaka', 0],
    [3, 'Corporate', 'Sunrise Pharma Ltd.', 'Procurement', '01000000302', '77 Industrial Area, Gazipur', 0],
    [3, 'Corporate', 'Orchid Hotel & Resort', 'Purchase Dept.', '01000000303', 'Beach Road, Cox\'s Bazar', 0],
    [3, 'Retail Credit', 'Lakeview Clinic', 'Manager', '01000000304', '9 Lake Road, Gulshan, Dhaka', 0],
];
$made = [];
foreach ($traders as [$cat, $group]) {
    if (!isset($made["$cat|$group"]) && !sb_val("SELECT COUNT(*) FROM trader_groups WHERE category_id=$cat AND group_name='" . sb_db()->real_escape_string($group) . "'")) {
        sb_post('trader/trader_group.php', ['group_id' => 0, 'return_to' => '', 'category_id' => $cat, 'group_name' => $group, 'is_active' => 1, 'action' => 'add'], "group $group");
    }
    $made["$cat|$group"] = 1;
}
foreach ($traders as [$cat, $group, $name, $contact, $mobile, $address, $opening]) {
    sb_post("trader/trader_info.php?cat=$cat", ['cat' => $cat, 'trader_id' => 0, 'group_name' => $group, 'trader_name' => $name, 'contact_person' => $contact, 'mobile' => $mobile,
        'address' => $address, 'email' => strtolower(preg_replace('/[^a-z]+/i', '', explode(' ', $name)[0])) . '@example.com', 'opening_balance' => $opening, 'is_active' => 1, 'action' => 'add'], "trader $name");
}

echo "Territories and sales representatives\n";
foreach ([['DHK-N', 'Dhaka North', 'Dhaka'], ['DHK-S', 'Dhaka South', 'Dhaka'], ['CTG', 'Chattogram City', 'Chattogram']] as [$code, $name, $region]) {
    sb_post('sales_person/territory.php', ['territory_id' => 0, 'territory_code' => $code, 'territory_name' => $name, 'region' => $region, 'parent_id' => 0, 'is_active' => 1, 'description' => ''], "territory $name");
}
$terr = fn($code) => sb_id('sr_territories', 'territory_id', "territory_code='$code'");
foreach ([['SR-01', 'Arif Hasan', '01000000401', 'Senior Sales Officer', 'DHK-N', 'net_sales', 1.5], ['SR-02', 'Nadia Karim', '01000000402', 'Sales Officer', 'DHK-S', 'collection', 1.0],
    ['SR-03', 'Tanvir Ahmed', '01000000403', 'Sales Officer', 'CTG', 'net_sales', 1.0]] as [$code, $name, $mobile, $desig, $t, $basis, $rate]) {
    sb_post('sales_person/sr_list.php', ['sr_id' => 0, 'sr_code' => $code, 'sr_name' => $name, 'mobile' => $mobile, 'email' => strtolower(explode(' ', $name)[0]) . '@example.com', 'designation' => $desig,
        'territory_id' => $terr($t), 'joining_date' => '2026-01-15', 'address' => '', 'commission_basis' => $basis, 'commission_rate' => $rate, 'target_bonus_rate' => 0.5, 'is_active' => 1], "SR $name");
}
echo "done\n";
