<?php
PHP_SAPI === 'cli' || exit; // a developer tool: never from the web
// Stage 1: products and their lookups, a second branch. All names are invented; no real brands or people.
require __DIR__ . '/seed_lib.php';

echo "Categories\n";
$cats = ['Mobile Phones' => 'Smartphones and feature phones', 'Laptops & Computers' => 'Notebooks and desktops', 'Accessories' => 'Chargers, cables, audio, power',
    'TV & Display' => 'Televisions and monitors', 'Networking' => 'Routers and network devices'];
$n = 0;
foreach ($cats as $name => $desc) {
    sb_post('product/product_category.php', ['id' => 0, 'category_name' => $name, 'description' => $desc, 'sort_order' => ++$n, 'is_active' => 1], "category $name");
}
$cat = fn($name) => sb_id('product_categories', 'category_id', "category_name='" . sb_db()->real_escape_string($name) . "'");

echo "Groups and brands\n";
$groups = ['Smartphone' => 'Mobile Phones', 'Feature Phone' => 'Mobile Phones', 'Laptop' => 'Laptops & Computers', 'Charger & Cable' => 'Accessories', 'Headphone' => 'Accessories',
    'Power Bank' => 'Accessories', 'LED TV' => 'TV & Display', 'Router' => 'Networking'];
foreach ($groups as $g => $c) {
    sb_post('product/product_classification.php?tab=group', ['id' => 0, 'tab' => 'group', 'class_name' => $g, 'category_id' => $cat($c), 'is_active' => 1], "group $g");
}
foreach (['Nova', 'Zentro', 'Kivo', 'Orbit', 'Lumo'] as $b) {
    sb_post('product/product_classification.php?tab=brand', ['id' => 0, 'tab' => 'brand', 'class_name' => $b, 'category_id' => '', 'is_active' => 1], "brand $b");
}

echo "Products\n";
$products = [ // code, category, group, brand, model, description, warranty, sale, purchase, reorder
    ['P-1001', 'Mobile Phones', 'Smartphone', 'Nova', 'Nova X5 4/64', 'Nova X5 smartphone 4GB/64GB', '1 Year', 14500, 12200, 5],
    ['P-1002', 'Mobile Phones', 'Smartphone', 'Nova', 'Nova X7 8/128', 'Nova X7 smartphone 8GB/128GB', '1 Year', 22900, 19800, 3],
    ['P-1003', 'Mobile Phones', 'Smartphone', 'Zentro', 'Zentro Z10 6/128', 'Zentro Z10 smartphone 6GB/128GB', '1 Year', 18500, 15900, 3],
    ['P-1004', 'Mobile Phones', 'Feature Phone', 'Kivo', 'Kivo K2 Dual SIM', 'Kivo K2 dual SIM feature phone', '6 Months', 1650, 1280, 10],
    ['P-2001', 'Laptops & Computers', 'Laptop', 'Orbit', 'Orbit Book 14', 'Orbit Book 14 laptop, Core i5, 8GB, 512GB SSD', '2 Years', 68500, 61000, 2],
    ['P-2002', 'Laptops & Computers', 'Laptop', 'Zentro', 'Zentro Air 15', 'Zentro Air 15 laptop, Ryzen 5, 16GB, 512GB SSD', '2 Years', 79900, 71500, 2],
    ['P-3001', 'Accessories', 'Charger & Cable', 'Lumo', 'Lumo 25W Charger', 'Lumo 25W USB-C fast charger', '6 Months', 950, 620, 20],
    ['P-3002', 'Accessories', 'Charger & Cable', 'Lumo', 'Lumo USB-C Cable 1m', 'Lumo USB-C to USB-C cable, 1 metre', 'No Warranty', 350, 190, 30],
    ['P-3003', 'Accessories', 'Headphone', 'Kivo', 'Kivo Buds Pro', 'Kivo Buds Pro wireless earbuds', '6 Months', 2450, 1750, 10],
    ['P-3004', 'Accessories', 'Power Bank', 'Lumo', 'Lumo 20000mAh', 'Lumo 20000mAh power bank', '6 Months', 2200, 1600, 10],
    ['P-4001', 'TV & Display', 'LED TV', 'Orbit', 'Orbit 43 Smart', 'Orbit 43 inch smart LED TV', '2 Years', 38500, 33500, 2],
    ['P-4002', 'TV & Display', 'LED TV', 'Orbit', 'Orbit 32 LED', 'Orbit 32 inch LED TV', '1 Year', 21500, 18200, 2],
    ['P-5001', 'Networking', 'Router', 'Zentro', 'Zentro AX1800', 'Zentro AX1800 dual-band Wi-Fi 6 router', '1 Year', 4800, 3900, 5],
    ['P-5002', 'Networking', 'Router', 'Kivo', 'Kivo 4G Pocket', 'Kivo 4G pocket router', '6 Months', 3200, 2550, 5],
];
foreach ($products as [$code, $c, $g, $b, $model, $desc, $war, $sale, $buy, $reorder]) {
    sb_post('product/product_info.php', ['id' => 0, 'product_code' => $code, 'category_id' => $cat($c), 'product_group' => $g, 'brand' => $b, 'model_caption' => $model, 'description' => $desc,
        'unit_name' => 'Pcs', 'warranty' => $war, 'default_unit_price' => $sale, 'purchase_price' => $buy, 'reorder_level' => $reorder, 'vat_percent' => 0, 'is_active' => 1], "product $code");
}

echo "Branch\n";
sb_post('user/branch_setup.php', ['branch_id' => 0, 'branch_name' => 'Chattogram Branch', 'parent_branch_id' => 0, 'address' => '45 Sample Avenue, Chattogram', 'is_active' => 1], 'branch Chattogram');
echo "done\n";
