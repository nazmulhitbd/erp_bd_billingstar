<?php
/**
 * The admin menu, as data. topmain.php draws the top menu from it and User Access Control lists its forms, so a page
 * added here is both a menu entry and a permission line: category = Access Category, group = Sub Menu, item = Form Name.
 *
 * Items are [label, url, rule]; rule is the older role check SuperAdmin menus still apply (see $legacy in topmain.php).
 * 'profile' (on a category or group) picks the permission columns of its forms, see PERM_PROFILES in addon/inc/perm.php.
 * 'registers' marks a group whose items each hold a list of reports; every report is a form of its own.
 * 'merge' on a group draws it as one menu entry of that name, opening the first of its forms the user holds; the forms
 * become tabs of one screen (access_tabs in store_admin/sa_list.php), each still a permission line of its own.
 */
function menu_catalog()
{
    $acc = fn($file) => 'accounts_info/' . $file;
    $post = fn($query) => 'accounts_info/acc_posting.php?status=draft&' . $query;
    $approve = fn($doc) => 'approve/approve.php?doc=' . $doc;

    return [
        ['label' => 'Dashboard', 'icon' => 'speedometer2', 'url' => 'dashboard/dashboard.php', 'profile' => 'page'],
        ['label' => 'Sales', 'icon' => 'cart3', 'profile' => 'doc', 'groups' => [
            ['title' => 'Sales', 'items' => [
                ['Sales Invoice / Bill', 'sales/sales_invoice/sales_invoice.php'],
                ['POS Sales', 'sales/pos_sales/pos_sales.php'],
                ['Sales Return', 'sales/sales_retrun/sales_return.php'],
            ]],
            ['title' => 'Sales Order', 'items' => [
                ['Sales Order', 'sales/sales_order/sales_order.php'],
                ['Sales Bill From Sales Order', 'sales/sal_bill_from_sal_order/sal_bill_from_sal_order.php'],
                ['Sales Order Adjust', 'sales/sales_order_adjustment/sales_order_adjustment.php'],
            ]],
            ['title' => 'Challan / Delivery', 'items' => [
                ['Challan', 'challan/challan.php'],
                ['Challan Return', 'challan/challan_return.php'],
                ['Sales Bill From Challan', 'challan/sales_bill_from_challan.php'],
            ]],
            ['title' => 'Price & History', 'profile' => 'report', 'items' => [
                ['Price Quotation', 'price_history/price_quotation.php'],
                ['Sales Price View', 'price_history/sales_price_view.php'],
                ['Sales Price MRP View', 'price_history/sales_price_mrp_view.php'],
                ['Serial History', 'price_history/serial_history.php'],
                ['Invoice Search by Mobile No.', 'price_history/invoice_search.php'],
            ]],
        ]],
        ['label' => 'Purchase & Stock', 'icon' => 'box-seam', 'profile' => 'doc', 'groups' => [
            ['title' => 'Purchase', 'items' => [
                ['Purchase Requisition', 'purchase/requisition.php'],
                ['Purchase Order', 'purchase/purchase_order.php'],
                ['Purchase', 'purchase/purchase.php'],
                ['Purchase Return', 'purchase/purchase_return.php'],
                ['Purchase Price Amendment', 'amedment/purchase_price_amendment.php'],
            ]],
            ['title' => 'Inventory', 'items' => [
                ['Stock Amendment In', 'amedment/stock_amendment_in.php'],
                ['Stock Amendment Out', 'amedment/stock_amendment_out.php'],
                ['Stock Transfer Store to Store', 'transfer/stock_transfer.php'],
                ['Transaction Lock', 'amedment/transaction_lock.php'],
            ]],
        ]],
        ['label' => 'Products', 'icon' => 'tags', 'profile' => 'setup', 'groups' => [
            ['title' => 'Product Setup', 'items' => [
                ['Product Information', 'product/product_info.php'],
                ['Product Category', 'product/product_category.php'],
                ['Product Classification', 'product/product_classification.php'],
                ['Unit Information', 'product/unit_info.php'],
                ['Transaction Category', 'product/transaction_category.php'],
            ]],
            ['title' => 'Pricing', 'items' => [
                ['Sales Price Fixing', 'product/sales_price_fixing.php'],
                ['Purchase Price Fixing', 'product/purchase_price_fixing.php'],
            ]],
            ['title' => 'Warranty', 'items' => [
                ['Warranty', 'product/warranty.php'],
                ['Periodical Warranty', 'product/periodical_warranty.php'],
            ]],
        ]],
        ['label' => 'Customers', 'icon' => 'people', 'profile' => 'setup', 'groups' => [
            ['title' => 'Customer Management', 'items' => [
                ['Customers', 'customer/customer_list.php', 'cm:0'],
                ['Customer Profile', 'customer/customer_profile.php', 'cm:1'],
                ['Credit Limit', 'customer/customer_credit.php', 'cm:0'],
                ['Customer Ledger', 'customer/customer_ledger.php', 'cm:5'],
            ]],
            ['title' => 'Traders & Suppliers', 'items' => [
                ['Credit Customer', 'trader/trader_info.php?cat=3'],
                ['Supplier Info', 'trader/trader_info.php?cat=1'],
                ['Cash Supplier', 'trader/trader_info.php?cat=5'],
                ['Trader Group', 'trader/trader_group.php'],
                ['Trader Flag Fixing', 'trader/trader_flag.php'],
                ['Trader Search', 'trader/trader_search.php'],
                ['Trader Convert', 'trader/trader_convert.php'],
            ]],
            ['title' => 'Customer Portal', 'items' => [
                ['Portal Accounts', 'customer/portal_accounts.php', 'cm:0'],
                ['Portal Requests', 'customer/portal_requests.php', 'cm:0'],
            ]],
        ]],
        ['label' => 'Sales Team', 'icon' => 'person-badge', 'profile' => 'setup', 'groups' => [
            ['title' => 'Sales Persons', 'items' => [
                ['Sales Person (SR)', 'sales_person/sr_list.php', 'sp:0'],
                ['Territory', 'sales_person/territory.php', 'sp:0'],
                ['Trader wise SR Fixing', 'trader/trader_sr.php'],
            ]],
            ['title' => 'Target & Commission', 'items' => [
                ['Target', 'sales_person/sr_target.php', 'sp:0'],
                ['Commission', 'sales_person/sr_commission.php', 'sp:0'],
            ]],
            ['title' => 'SR Portal', 'items' => [
                ['SR Portal Accounts', 'sales_person/sr_portal_accounts.php', 'sp:0'],
                ['SR Collections', 'sales_person/sr_collections.php', 'sp:0'],
            ]],
        ]],
        ['label' => 'HR', 'icon' => 'person-vcard', 'profile' => 'setup', 'groups' => [
            ['title' => 'Employees', 'items' => [
                ['Employee Onboarding & Profile', 'hr/employees.php'],
                ['Department & Designation', 'hr/hr_setup.php'],
                ['Staff Announcements', 'hr/announcements.php'],
            ]],
            ['title' => 'Attendance', 'items' => [
                ['Daily Attendance', 'hr/attendance.php'],
                ['Attendance Register', 'hr/attendance_register.php'],
            ]],
            ['title' => 'Leave', 'items' => [
                ['Leave Assign', 'hr/leave_assign.php'],
                ['Leave Applications', 'hr/leave_applications.php'],
            ]],
            ['title' => 'Salary', 'items' => [
                ['Salary Structure', 'hr/salary.php'],
                ['Salary Tax', 'hr/salary_tax.php'],
                ['Payroll & Payslips', 'hr/payroll.php'],
            ]],
        ]],
        ['label' => 'Stores', 'icon' => 'shop', 'profile' => 'setup', 'groups' => [
            ['title' => 'Store Data', 'items' => [
                ['Customers by Store', 'store_admin/store_customers.php'],
                ['Sales Persons by Store', 'store_admin/store_staff.php'],
            ]],
        ]],
        ['label' => 'Accounts', 'icon' => 'calculator', 'profile' => 'voucher', 'groups' => [
            ['title' => 'Vouchers', 'items' => [
                ['Receive Voucher', $acc('acc_rp_voucher.php?type=credit')],
                ['Payment Voucher', $acc('acc_rp_voucher.php?type=debit')],
                ['Journal Voucher', $acc('acc_voucher.php?type=journal')],
                ['Contra Voucher', $acc('acc_voucher.php?type=contra')],
                ['Voucher Posting', $acc('acc_posting.php')],
            ]],
            ['title' => 'Inter Branch', 'items' => [
                ['Inter Branch Credit Voucher', $acc('acc_rp_voucher.php?type=credit&ib=1')],
                ['Inter Branch Debit Voucher', $acc('acc_rp_voucher.php?type=debit&ib=1')],
                ['Inter Branch Journal Voucher', $acc('acc_voucher.php?type=journal&ib=1')],
            ]],
            ['title' => 'Cheques', 'items' => [
                ['Cheque Book', $acc('acc_cheque_book.php')],
                ['Cheque Treatment', $acc('acc_cheque_treatment.php')],
                ['Cheque History', $acc('acc_cheque_history.php')],
            ]],
            ['title' => 'Accounts Setup', 'profile' => 'setup', 'items' => [
                ['Accounts Group Distribution', $acc('acc_group.php')],
                ['General Accounts', $acc('acc_account.php')],
                ['Accounts Sub Ledger Group', $acc('acc_sub_ledger_group.php')],
                ['Accounts Sub Ledger', $acc('acc_sub_ledger.php')],
                ['Money Receipt Category', $acc('acc_receipt_category.php')],
                ['CC Loan Interest', $acc('acc_cc_interest.php')],
                ['Over Due Permission', $acc('acc_overdue.php')],
            ]],
        ]],
        ['label' => 'Approvals', 'icon' => 'check2-square', 'profile' => 'approve', 'groups' => [
            ['title' => 'Accounts', 'items' => [
                ['Credit Voucher', $post('vtype=credit&ib=0')],
                ['Debit Voucher', $post('vtype=debit&ib=0')],
                ['Journal Voucher', $post('vtype=journal&ib=0')],
                ['Contra Voucher', $post('vtype=contra')],
                ['Inter Branch Credit', $post('vtype=credit&ib=1')],
                ['Inter Branch Debit', $post('vtype=debit&ib=1')],
                ['Inter Branch Journal', $post('vtype=journal&ib=1')],
            ]],
            ['title' => 'Sales', 'items' => [
                ['Sales', $approve('sales')],
                ['Sales Return', $approve('sales_return')],
                ['Sales Order', $approve('sales_order')],
                ['Challan', $approve('challan')],
                ['Challan Return', $approve('challan_return')],
            ]],
            ['title' => 'Purchase & Stock', 'items' => [
                ['Requisition', $approve('requisition')],
                ['Purchase Order', $approve('purchase_order')],
                ['Purchase', $approve('purchase')],
                ['Purchase Return', $approve('purchase_return')],
                ['Purchase Price Amendment', $approve('price_amendment')],
                ['Stock Transfer', $approve('stock_transfer')],
                ['Stock Amendment In', $approve('stock_in')],
                ['Stock Amendment Out', $approve('stock_out')],
            ]],
            ['title' => 'Service (RMA)', 'items' => [
                ['RMA Complain Receive', 'rma/approve.php?type=complain'],
                ['RMA Replace In', 'rma/approve.php?type=replace_in'],
                ['RMA Replace Delivery', 'rma/approve.php?type=delivery'],
                ['RMA Replace Out', 'rma/approve.php?type=replace_out'],
                ['RMA Service Bill', 'rma/approve.php?type=service_bill'],
            ]],
        ]],
        ['label' => 'Service', 'icon' => 'tools', 'profile' => 'voucher', 'groups' => [
            ['title' => 'Service', 'items' => [
                ['RMA Complain Receive', 'rma/complain_receive.php'],
                ['RMA Diagnosis', 'rma/diagnosis.php'],
                ['RMA Item Utilize', 'rma/item_utilize.php'],
                ['RMA Service Bill', 'rma/service_bill.php'],
                ['Problem', 'rma/problem.php'],
            ]],
            ['title' => 'Replacement', 'items' => [
                ['RMA Replace In', 'rma/replace_in.php'],
                ['RMA Replace Delivery', 'rma/replace_delivery.php'],
                ['RMA Replace Out', 'rma/replace_out.php'],
                ['RMA Delivery Store Fixing', 'rma/delivery_store_fixing.php'],
            ]],
        ]],
        ['label' => 'Reports', 'icon' => 'bar-chart-line', 'profile' => 'report', 'groups' => [
            ['title' => 'Sales & Purchase', 'registers' => true, 'items' => [
                ['Sales Register', 'report/sales_register.php'],
                ['Purchase Register', 'report/purchase_register.php'],
                ['Challan Register', 'report/challan_register.php'],
            ]],
            ['title' => 'Stock', 'registers' => true, 'items' => [
                ['Stock Register', 'report/stock_register.php'],
                ['Transfer Register', 'report/transfer_register.php'],
            ]],
            ['title' => 'Accounts & Service', 'registers' => true, 'items' => [
                ['Accounts Register', $acc('acc_register.php')],
                ['RMA Service Register', 'rma/service_register.php'],
                ['RMA Stock Register', 'rma/stock_register.php'],
            ]],
        ]],
        ['label' => 'Audit', 'icon' => 'shield-lock', 'profile' => 'report', 'groups' => [
            ['title' => 'Overview', 'profile' => 'page', 'items' => [
                ['Audit Dashboard', 'audit/audit_dashboard.php'],
            ]],
            ['title' => 'Audit Trail', 'items' => [
                ['All Activity', 'audit/audit_log.php?tab=all'],
                ['Login History', 'audit/audit_log.php?tab=login'],
                ['Data Changes', 'audit/audit_log.php?tab=change'],
                ['Security Events', 'audit/audit_log.php?tab=security'],
            ]],
            ['title' => 'Setup', 'profile' => 'setup', 'items' => [
                ['Audit Settings', 'audit/audit_settings.php'],
            ]],
        ]],
        ['label' => 'Settings', 'icon' => 'gear', 'profile' => 'setup', 'groups' => [
            ['title' => 'Users & Roles', 'merge' => 'Users, Roles & Permissions', 'items' => [
                ['Users', 'user/registration.php'],
                ['Roles', 'user/role_access.php'],
                ['User Rights', 'user/user_access.php'],
                ['Store Access', 'user/user_store_fixing.php'],
                ['Ledger Access', 'user/user_ledger_fixing.php'],
            ]],
            ['title' => 'System', 'items' => [
                ['Company & Login Settings', 'user/company_settings.php'],
                ['Country, Language & Tax', 'user/globalization.php'],
                ['Document Numbering', 'user/doc_numbering.php'],
                ['Service Licence', 'user/licence.php'],
                ['User Config', 'user/create_config.php', 'uc:2'],
                ['Email & SMS', 'user/email_sms.php', 'uc:2'],
                ['Database Backup', 'user/dump_mysql.php', 'uc:3'],
            ]],
        ]],
    ];
}

/** Reports of a register as [form key => caption], read from the register's source so the list cannot drift from it. */
function menu_register_reports($url)
{
    $dir = __DIR__ . '/';
    if ($url === 'accounts_info/acc_register.php') {
        $src = (string) file_get_contents($dir . 'accounts_info/acc_common.php');
        $src = substr($src, (int) strpos($src, 'const ACC_REPORTS'));
        preg_match_all("/^\s*(\d+)\s*=>\s*\['([^']+)'/m", substr($src, 0, (int) strpos($src, '];')), $m, PREG_SET_ORDER);
        return array_column(array_map(fn($r) => ['accounts_info/acc_report.php?r=' . $r[1], $r[2]], $m), 1, 0);
    }
    preg_match_all("/^\s*'([a-z0-9_]+)'\s*=>\s*\['caption'\s*=>\s*'([^']+)'/m", (string) file_get_contents($dir . $url), $m, PREG_SET_ORDER);
    return array_column(array_map(fn($r) => [$url . '?report=' . $r[1], $r[2]], $m), 1, 0);
}

/** Pages of the customer / SR portals for the shell: [label, icon, file]. */
/** SR / Staff Portal pages of a person who does not sell (sales_representatives.staff_only). */
const MENU_STAFF_PAGES = ['index.php', 'hr.php', 'profile.php', 'notifications.php'];

function menu_portal_pages($type)
{
    return $type === 'customer'
        ? ['dir' => '../customer_portal/', 'pages' => [['Dashboard', 'speedometer2', 'index.php'], ['Invoices', 'receipt', 'invoices.php'], ['Statement', 'journal-text', 'statement.php'],
            ['Orders & Quotations', 'cart3', 'orders.php'], ['Payments', 'cash-coin', 'payments.php'], ['Warranty & Service', 'tools', 'warranty.php'], ['Requests', 'chat-dots', 'requests.php'], ['Profile', 'person', 'profile.php']]]
        : ['dir' => '../sr_portal/', 'pages' => [['Dashboard', 'speedometer2', 'index.php'], ['My Customers', 'people', 'customers.php'], ['My Sales', 'receipt', 'sales.php'], ['Book Order', 'cart3', 'orders.php'],
            ['Collections', 'cash-coin', 'collections.php'], ['Target & Commission', 'bullseye', 'performance.php'], ['Stock & Price', 'box-seam', 'stock.php'], ['My HR', 'person-vcard', 'hr.php'], ['Profile', 'person', 'profile.php']]];
}
