-- PetaBill ERP: sample data
-- Copyright (c) 2015-2026 Petacore Technology. All rights reserved. PetaBill is a trademark of Petacore Technology.
--
-- A demo electronics shop in Bangladesh (September to November 2026): products, suppliers, customers, purchases,
-- sales invoices, POS, orders, returns, quotation, challan, stock transfer and amendments, approvals, vouchers,
-- employees, attendance, leave, sales representatives and targets, a service complaint and an announcement.
-- Every company, person, brand, phone number and address here is invented. Phone numbers use 0100000xxxx (no operator uses 010) and
-- e-mail addresses use example.com, so nothing reaches a real person.
--
-- Built through the application's own forms on a fresh install, then exported (install_script loads it when
-- "Start with sample data" is ticked). It expects a freshly installed, empty database, and @sample_user set to
-- the administrator's user_id. Do not edit by hand: the numbers, stock and ledgers agree with each other.
SET @sample_user = COALESCE(@sample_user, 1);
SET FOREIGN_KEY_CHECKS = 0;
INSERT INTO `acc_sub_ledger_groups` (`slg_id`, `account_id`, `group_name`, `is_active`) VALUES
('3', '5', 'Local Suppliers', '1'),
('4', '5', 'Importers', '1'),
('5', '3', 'Dhaka Dealers', '1'),
('6', '3', 'Chattogram Dealers', '1'),
('7', '3', 'Corporate', '1'),
('8', '3', 'Retail Credit', '1');

INSERT INTO `acc_sub_ledgers` (`sub_ledger_id`, `slg_id`, `account_id`, `sub_ledger_code`, `sub_ledger_name`, `address`, `mobile`, `opening_balance`, `opening_side`, `trader_id`, `is_active`) VALUES
('1', '3', '5', 'SUP000001', 'Delta Distribution Ltd.', '8 Warehouse Lane, Tejgaon, Dhaka', '01000000101', '0.00', 'Cr', '1', '1'),
('2', '3', '5', 'SUP000002', 'Meridian Tech Supply', '22 Market Road, Motijheel, Dhaka', '01000000102', '0.00', 'Cr', '2', '1'),
('3', '4', '5', 'SUP000003', 'Blue Harbor Trading Co.', '5 Port Road, Agrabad, Chattogram', '01000000103', '0.00', 'Cr', '3', '1'),
('4', '5', '3', 'DLR000004', 'City Gadget Point', 'Shop 12, Sample Plaza, Dhanmondi, Dhaka', '01000000201', '0.00', 'Dr', '4', '1'),
('5', '6', '3', 'DLR000005', 'Riverside Electronics', '31 River View Road, Chattogram', '01000000202', '0.00', 'Dr', '5', '1'),
('6', '7', '3', 'CRC000006', 'Greenfield School', '1 School Road, Uttara, Dhaka', '01000000301', '0.00', 'Dr', '6', '1'),
('7', '7', '3', 'CRC000007', 'Sunrise Pharma Ltd.', '77 Industrial Area, Gazipur', '01000000302', '0.00', 'Dr', '7', '1'),
('8', '7', '3', 'CRC000008', 'Orchid Hotel & Resort', 'Beach Road, Cox\'s Bazar', '01000000303', '0.00', 'Dr', '8', '1'),
('9', '8', '3', 'CRC000009', 'Lakeview Clinic', '9 Lake Road, Gulshan, Dhaka', '01000000304', '0.00', 'Dr', '9', '1');

INSERT INTO `acc_voucher_lines` (`line_id`, `voucher_id`, `account_id`, `sub_ledger_id`, `debit`, `credit`, `line_narration`, `line_no`, `is_posted`, `voucher_date`, `basis_date`, `branch_id`, `transaction_mode`, `cheque_no`, `cheque_date`, `invoice_no`) VALUES
('1', '1', '2', NULL, '3000000.00', '0.00', 'Capital paid into the bank', '1', '1', '2026-09-01', '2026-09-01', '1', '', '', NULL, ''),
('2', '1', '7', NULL, '0.00', '3000000.00', 'Opening capital', '2', '1', '2026-09-01', '2026-09-01', '1', '', '', NULL, ''),
('3', '2', '2', NULL, '60000.00', '0.00', 'Against SI2026000001', '1', '1', '2026-09-30', '2026-09-30', '1', 'Bank Transfer', '', NULL, ''),
('4', '2', '3', '6', '0.00', '60000.00', 'Against SI2026000001', '2', '1', '2026-09-30', '2026-09-30', '1', 'Bank Transfer', '', NULL, ''),
('5', '3', '1', NULL, '40000.00', '0.00', 'Against SI2026000003', '1', '1', '2026-10-25', '2026-10-25', '1', 'Cash', '', NULL, ''),
('6', '3', '3', '4', '0.00', '40000.00', 'Against SI2026000003', '2', '1', '2026-10-25', '2026-10-25', '1', 'Cash', '', NULL, ''),
('7', '4', '5', '2', '500000.00', '0.00', 'Part of DESPUR2026000002', '1', '1', '2026-09-28', '2026-09-28', '1', 'Bank Transfer', '', NULL, ''),
('8', '4', '2', NULL, '0.00', '500000.00', 'Part of DESPUR2026000002', '2', '1', '2026-09-28', '2026-09-28', '1', 'Bank Transfer', '', NULL, ''),
('9', '5', '11', NULL, '35000.00', '0.00', 'Rent, October 2026', '1', '1', '2026-10-10', '2026-10-10', '1', 'Cash', '', NULL, ''),
('10', '5', '1', NULL, '0.00', '35000.00', 'Rent, October 2026', '2', '1', '2026-10-10', '2026-10-10', '1', 'Cash', '', NULL, ''),
('11', '6', '2', NULL, '30000.00', '0.00', 'Deposit', '1', '1', '2026-10-01', '2026-10-01', '1', '', '', NULL, ''),
('12', '6', '1', NULL, '0.00', '30000.00', 'Deposit', '2', '1', '2026-10-01', '2026-10-01', '1', '', '', NULL, '');

INSERT INTO `acc_vouchers` (`voucher_id`, `voucher_no`, `voucher_type`, `voucher_date`, `ref_no`, `narration`, `sales_person`, `transaction_mode`, `cheque_no`, `cheque_date`, `receipt_category_id`, `total_amount`, `status`, `source`, `created_by`, `posted_by`, `posted_at`, `branch_id`, `to_branch_id`, `is_inter_branch`) VALUES
('1', 'DESJUR2026000001', 'journal', '2026-09-01', '', 'Owner\'s opening capital', '', 'Bank Transfer', '', NULL, NULL, '3000000.00', 'posted', 'manual', @sample_user, @sample_user, '2026-11-06 20:40:46', '1', NULL, '0'),
('2', 'DESCRV2026000001', 'credit', '2026-09-30', '', 'Part payment received', '', 'Bank Transfer', '', NULL, NULL, '60000.00', 'posted', 'manual', @sample_user, @sample_user, '2026-11-06 20:40:46', '1', NULL, '0'),
('3', 'DESCRV2026000002', 'credit', '2026-10-25', '', 'Dealer payment received', '', 'Cash', '', NULL, NULL, '40000.00', 'posted', 'manual', @sample_user, @sample_user, '2026-11-06 20:40:46', '1', NULL, '0'),
('4', 'DESDRV2026000001', 'debit', '2026-09-28', '', 'Supplier payment', '', 'Bank Transfer', '', NULL, NULL, '500000.00', 'posted', 'manual', @sample_user, @sample_user, '2026-11-06 20:40:46', '1', NULL, '0'),
('5', 'DESDRV2026000002', 'debit', '2026-10-10', '', 'Shop rent for October', '', 'Cash', '', NULL, NULL, '35000.00', 'posted', 'manual', @sample_user, @sample_user, '2026-11-06 20:40:46', '1', NULL, '0'),
('6', 'DESCON2026000001', 'contra', '2026-10-01', '', 'Cash deposited to bank', '', 'Cash', '', NULL, NULL, '30000.00', 'posted', 'manual', @sample_user, @sample_user, '2026-11-06 20:40:46', '1', NULL, '0');

INSERT INTO `app_notifications` (`notification_id`, `audience`, `form_key`, `sr_id`, `user_id`, `customer_id`, `title`, `body`, `link`, `sent_by`) VALUES
('1', 'admin', 'approve/approve.php?doc=purchase', NULL, NULL, NULL, 'Approval needed: Purchase DESPUR2026000001', 'Delta Distribution Ltd.', 'billing/approve/approve.php?doc=purchase', NULL),
('2', 'admin', 'approve/approve.php?doc=purchase', NULL, NULL, NULL, 'Approval needed: Purchase DESPUR2026000002', 'Meridian Tech Supply', 'billing/approve/approve.php?doc=purchase', NULL),
('3', 'admin', 'approve/approve.php?doc=purchase', NULL, NULL, NULL, 'Approval needed: Purchase DESPUR2026000003', 'Blue Harbor Trading Co.', 'billing/approve/approve.php?doc=purchase', NULL),
('4', 'admin', 'approve/approve.php?doc=purchase_return', NULL, NULL, NULL, 'Approval needed: Purchase Return DESPRT2026000001', 'Delta Distribution Ltd.', 'billing/approve/approve.php?doc=purchase_return', NULL),
('5', 'admin', 'approve/approve.php?doc=purchase_order', NULL, NULL, NULL, 'Approval needed: Purchase Order DESPOR2026000001', 'Delta Distribution Ltd.', 'billing/approve/approve.php?doc=purchase_order', NULL),
('6', 'admin', 'approve/approve.php?doc=stock_transfer', NULL, NULL, NULL, 'Approval needed: Stock Transfer Store to Store DESSTT2026000001', '', 'billing/approve/approve.php?doc=stock_transfer', NULL),
('7', 'admin', 'approve/approve.php?doc=stock_in', NULL, NULL, NULL, 'Approval needed: Stock Amendment IN DESSAI2026000001', '', 'billing/approve/approve.php?doc=stock_in', NULL),
('8', 'admin', 'approve/approve.php?doc=stock_out', NULL, NULL, NULL, 'Approval needed: Stock Amendment OUT DESSAO2026000001', '', 'billing/approve/approve.php?doc=stock_out', NULL),
('9', 'admin', 'approve/approve.php?doc=sales', NULL, NULL, NULL, 'Approval needed: Sales Invoice SI2026000001', 'Greenfield School', 'billing/approve/approve.php?doc=sales', NULL),
('10', 'admin', 'approve/approve.php?doc=sales', NULL, NULL, NULL, 'Approval needed: Sales Invoice SI2026000002', 'Walk-in Customer', 'billing/approve/approve.php?doc=sales', NULL),
('11', 'admin', 'approve/approve.php?doc=sales', NULL, NULL, NULL, 'Approval needed: Sales Invoice SI2026000003', 'City Gadget Point', 'billing/approve/approve.php?doc=sales', NULL),
('12', 'admin', 'approve/approve.php?doc=sales', NULL, NULL, NULL, 'Approval needed: Sales Invoice SI2026000004', 'Sunrise Pharma Ltd.', 'billing/approve/approve.php?doc=sales', NULL),
('13', 'admin', 'approve/approve.php?doc=sales', NULL, NULL, NULL, 'Approval needed: Sales Invoice SI2026000005', 'Orchid Hotel & Resort', 'billing/approve/approve.php?doc=sales', NULL),
('14', 'admin', 'approve/approve.php?doc=sales', NULL, NULL, NULL, 'Approval needed: Sales Invoice SI2026000006', 'Walk-in Customer', 'billing/approve/approve.php?doc=sales', NULL),
('15', 'admin', 'approve/approve.php?doc=sales', NULL, NULL, NULL, 'Approval needed: Sales Invoice SI2026000007', 'Riverside Electronics', 'billing/approve/approve.php?doc=sales', NULL),
('16', 'admin', 'approve/approve.php?doc=sales', NULL, NULL, NULL, 'Approval needed: Sales Invoice SI2026000008', 'Lakeview Clinic', 'billing/approve/approve.php?doc=sales', NULL),
('17', 'admin', 'approve/approve.php?doc=sales', NULL, NULL, NULL, 'Approval needed: Sales Invoice SI2026000009', 'Walk-in Customer', 'billing/approve/approve.php?doc=sales', NULL),
('18', 'admin', 'approve/approve.php?doc=sales_order', NULL, NULL, NULL, 'Approval needed: Sales Order SO2026000001', 'Greenfield School', 'billing/approve/approve.php?doc=sales_order', NULL),
('19', 'admin', 'approve/approve.php?doc=sales_return', NULL, NULL, NULL, 'Approval needed: Sales Return SR2026000001', '', 'billing/approve/approve.php?doc=sales_return', NULL),
('20', 'admin', 'approve/approve.php?doc=quotation', NULL, NULL, NULL, 'Approval needed: Price Quotation DESPQI2026000001', 'Sunrise Pharma Ltd.', 'billing/approve/approve.php?doc=quotation', NULL),
('21', 'admin', 'approve/approve.php?doc=challan', NULL, NULL, NULL, 'Approval needed: Challan DESCHL2026000001', 'Lakeview Clinic', 'billing/approve/approve.php?doc=challan', NULL),
('22', 'admin', 'rma/approve.php?type=complain', NULL, NULL, NULL, 'Approval needed: Complain Received DESCOM2026000001', 'Riverside Electronics', 'billing/rma/approve.php?type=complain', NULL),
('23', 'staff', '', NULL, NULL, NULL, 'Festive season stock count', 'All stores will be counted on Thursday after closing. Please finish pending deliveries before 5 pm.', '', @sample_user),
('24', 'admin', '', NULL, @sample_user, NULL, 'Purchase approved: DESPUR2026000001', 'Delta Distribution Ltd.', '', NULL),
('25', 'admin', '', NULL, @sample_user, NULL, 'Purchase approved: DESPUR2026000002', 'Meridian Tech Supply', '', NULL),
('26', 'admin', '', NULL, @sample_user, NULL, 'Purchase approved: DESPUR2026000003', 'Blue Harbor Trading Co.', '', NULL),
('27', 'admin', '', NULL, @sample_user, NULL, 'Purchase Return approved: DESPRT2026000001', 'Delta Distribution Ltd.', '', NULL),
('28', 'admin', '', NULL, @sample_user, NULL, 'Stock Transfer Store to Store approved: DESSTT2026000001', '', '', NULL),
('29', 'admin', '', NULL, @sample_user, NULL, 'Stock Amendment IN approved: DESSAI2026000001', '', '', NULL),
('30', 'admin', '', NULL, @sample_user, NULL, 'Stock Amendment OUT approved: DESSAO2026000001', '', '', NULL),
('31', 'admin', '', NULL, @sample_user, NULL, 'Sales approved: SI2026000001', 'Greenfield School', '', NULL),
('32', 'customer', '', NULL, NULL, '3', 'Sales ready: SI2026000001', 'Amount 146,600.00', 'customer_portal/invoices.php', NULL),
('33', 'staff', '', '1', NULL, NULL, 'Sales approved: SI2026000001', 'Greenfield School', '', NULL),
('34', 'admin', '', NULL, @sample_user, NULL, 'Sales approved: SI2026000002', 'Walk-in Customer', '', NULL),
('35', 'admin', '', NULL, @sample_user, NULL, 'Sales approved: SI2026000003', 'City Gadget Point', '', NULL),
('36', 'customer', '', NULL, NULL, '1', 'Sales ready: SI2026000003', 'Amount 91,400.00', 'customer_portal/invoices.php', NULL),
('37', 'staff', '', '2', NULL, NULL, 'Sales approved: SI2026000003', 'City Gadget Point', '', NULL),
('38', 'admin', '', NULL, @sample_user, NULL, 'Sales approved: SI2026000004', 'Sunrise Pharma Ltd.', '', NULL),
('39', 'customer', '', NULL, NULL, '4', 'Sales ready: SI2026000004', 'Amount 156,900.00', 'customer_portal/invoices.php', NULL),
('40', 'staff', '', '2', NULL, NULL, 'Sales approved: SI2026000004', 'Sunrise Pharma Ltd.', '', NULL),
('41', 'admin', '', NULL, @sample_user, NULL, 'Sales approved: SI2026000005', 'Orchid Hotel & Resort', '', NULL),
('42', 'customer', '', NULL, NULL, '5', 'Sales ready: SI2026000005', 'Amount 93,600.00', 'customer_portal/invoices.php', NULL),
('43', 'staff', '', '1', NULL, NULL, 'Sales approved: SI2026000005', 'Orchid Hotel & Resort', '', NULL),
('44', 'admin', '', NULL, @sample_user, NULL, 'Sales approved: SI2026000006', 'Walk-in Customer', '', NULL),
('45', 'admin', '', NULL, @sample_user, NULL, 'Sales approved: SI2026000007', 'Riverside Electronics', '', NULL),
('46', 'customer', '', NULL, NULL, '2', 'Sales ready: SI2026000007', 'Amount 77,700.00', 'customer_portal/invoices.php', NULL),
('47', 'staff', '', '3', NULL, NULL, 'Sales approved: SI2026000007', 'Riverside Electronics', '', NULL),
('48', 'admin', '', NULL, @sample_user, NULL, 'Sales approved: SI2026000008', 'Lakeview Clinic', '', NULL),
('49', 'customer', '', NULL, NULL, '6', 'Sales ready: SI2026000008', 'Amount 72,900.00', 'customer_portal/invoices.php', NULL),
('50', 'staff', '', '1', NULL, NULL, 'Sales approved: SI2026000008', 'Lakeview Clinic', '', NULL),
('51', 'admin', '', NULL, @sample_user, NULL, 'Sales Return approved: SR2026000001', 'City Gadget Point', '', NULL),
('52', 'customer', '', NULL, NULL, '1', 'Sales Return ready: SR2026000001', 'Amount 3,100.00', '', NULL);

INSERT INTO `branches` (`branch_id`, `branch_name`, `parent_branch_id`, `address`, `is_active`) VALUES
('2', 'Chattogram Branch', NULL, '45 Sample Avenue, Chattogram', '1');

INSERT INTO `doc_counters` (`doc_type`, `period_key`, `last_number`) VALUES
('ACC_CON', '2026', '1'),
('ACC_CRV', '2026', '2'),
('ACC_DRV', '2026', '2'),
('ACC_JUR', '2026', '1'),
('INV_CHL', '2026', '1'),
('INV_POR', '2026', '1'),
('INV_PQI', '2026', '1'),
('INV_PRT', '2026', '1'),
('INV_PUR', '2026', '3'),
('INV_SAI', '2026', '1'),
('INV_SAO', '2026', '1'),
('INV_STT', '2026', '1'),
('RMA_COM', '2026', '1'),
('SI', '2026', '10'),
('SO', '2026', '1'),
('SR', '2026', '1')
ON DUPLICATE KEY UPDATE `last_number`=GREATEST(`last_number`, VALUES(`last_number`));

INSERT INTO `hr_attendance` (`attendance_id`, `employee_id`, `attendance_date`, `status`, `check_in`, `check_out`, `unpaid_days`, `application_id`, `source`, `note`, `updated_by`) VALUES
('1', '1', '2026-11-01', 'present', '08:55:00', '18:05:00', '0.0', NULL, 'office', '', @sample_user),
('2', '2', '2026-11-01', 'present', '08:55:00', '18:05:00', '0.0', NULL, 'office', '', @sample_user),
('3', '3', '2026-11-01', 'present', '08:55:00', '18:05:00', '0.0', NULL, 'office', '', @sample_user),
('4', '4', '2026-11-01', 'present', '08:55:00', '18:05:00', '0.0', NULL, 'office', '', @sample_user),
('5', '5', '2026-11-01', 'late', '09:25:00', '18:05:00', '0.0', NULL, 'office', '', @sample_user),
('6', '6', '2026-11-01', 'present', '08:55:00', '18:05:00', '0.0', NULL, 'office', '', @sample_user),
('7', '1', '2026-11-02', 'present', '08:55:00', '18:05:00', '0.0', NULL, 'office', '', @sample_user),
('8', '2', '2026-11-02', 'present', '08:55:00', '18:05:00', '0.0', NULL, 'office', '', @sample_user),
('9', '3', '2026-11-02', 'present', '08:55:00', '18:05:00', '0.0', NULL, 'office', '', @sample_user),
('10', '4', '2026-11-02', 'late', '09:25:00', '18:05:00', '0.0', NULL, 'office', '', @sample_user),
('11', '5', '2026-11-02', 'present', '08:55:00', '18:05:00', '0.0', NULL, 'office', '', @sample_user),
('12', '6', '2026-11-02', 'present', '08:55:00', '18:05:00', '0.0', NULL, 'office', '', @sample_user),
('13', '1', '2026-11-03', 'present', '08:55:00', '18:05:00', '0.0', NULL, 'office', '', @sample_user),
('14', '2', '2026-11-03', 'present', '08:55:00', '18:05:00', '0.0', NULL, 'office', '', @sample_user),
('15', '3', '2026-11-03', 'late', '09:25:00', '18:05:00', '0.0', NULL, 'office', '', @sample_user),
('16', '4', '2026-11-03', 'present', '08:55:00', '18:05:00', '0.0', NULL, 'office', '', @sample_user),
('17', '5', '2026-11-03', 'present', '08:55:00', '18:05:00', '0.0', NULL, 'office', '', @sample_user),
('18', '6', '2026-11-03', 'present', '08:55:00', '18:05:00', '0.0', NULL, 'office', '', @sample_user),
('19', '1', '2026-11-04', 'present', '08:55:00', '18:05:00', '0.0', NULL, 'office', '', @sample_user),
('20', '2', '2026-11-04', 'late', '09:25:00', '18:05:00', '0.0', NULL, 'office', '', @sample_user),
('21', '3', '2026-11-04', 'absent', NULL, NULL, '0.0', NULL, 'office', 'Sick leave', @sample_user),
('22', '4', '2026-11-04', 'present', '08:55:00', '18:05:00', '0.0', NULL, 'office', '', @sample_user),
('23', '5', '2026-11-04', 'present', '08:55:00', '18:05:00', '0.0', NULL, 'office', '', @sample_user),
('24', '6', '2026-11-04', 'present', '08:55:00', '18:05:00', '0.0', NULL, 'office', '', @sample_user),
('25', '1', '2026-11-05', 'late', '09:25:00', '18:05:00', '0.0', NULL, 'office', '', @sample_user),
('26', '2', '2026-11-05', 'present', '08:55:00', '18:05:00', '0.0', NULL, 'office', '', @sample_user),
('27', '3', '2026-11-05', 'present', '08:55:00', '18:05:00', '0.0', NULL, 'office', '', @sample_user),
('28', '4', '2026-11-05', 'present', '08:55:00', '18:05:00', '0.0', NULL, 'office', '', @sample_user),
('29', '5', '2026-11-05', 'present', '08:55:00', '18:05:00', '0.0', NULL, 'office', '', @sample_user),
('30', '6', '2026-11-05', 'late', '09:25:00', '18:05:00', '0.0', NULL, 'office', '', @sample_user);

INSERT INTO `hr_departments` (`department_id`, `department_name`, `is_active`) VALUES
('1', 'Sales', '1'),
('2', 'Accounts', '1'),
('3', 'Service', '1'),
('4', 'Store', '1');

INSERT INTO `hr_designations` (`designation_id`, `designation_name`, `is_active`) VALUES
('1', 'Branch Manager', '1'),
('2', 'Sales Officer', '1'),
('3', 'Accountant', '1'),
('4', 'Service Engineer', '1'),
('5', 'Store Keeper', '1');

INSERT INTO `hr_employees` (`employee_id`, `employee_code`, `full_name`, `father_name`, `mother_name`, `date_of_birth`, `gender`, `blood_group`, `marital_status`, `spouse_name`, `religion`, `nationality`, `nid`, `passport_no`, `driving_license`, `tin`, `mobile`, `email`, `present_address`, `permanent_address`, `emergency_name`, `emergency_mobile`, `skills`, `languages`, `department_id`, `designation_id`, `branch_id`, `reports_to`, `employment_type`, `join_date`, `confirm_date`, `exit_date`, `status`, `bank_name`, `bank_account`, `sr_id`, `created_by`) VALUES
('1', 'EMP-001', 'Rafiq Islam', '', '', NULL, 'male', '', '', '', '', 'Bangladeshi', '', '', '', '', '01000000501', 'rafiq@example.com', 'Sample address, Dhaka', '', '', '', '', '', '1', '1', '1', NULL, 'permanent', '2025-01-05', NULL, NULL, 'active', '', '', NULL, @sample_user),
('2', 'EMP-002', 'Arif Hasan', '', '', NULL, 'male', '', '', '', '', 'Bangladeshi', '', '', '', '', '01000000401', 'arif@example.com', 'Sample address, Dhaka', '', '', '', '', '', '1', '2', '1', NULL, 'permanent', '2026-01-15', NULL, NULL, 'active', '', '', '1', @sample_user),
('3', 'EMP-003', 'Nadia Karim', '', '', NULL, 'female', '', '', '', '', 'Bangladeshi', '', '', '', '', '01000000402', 'nadia@example.com', 'Sample address, Dhaka', '', '', '', '', '', '1', '2', '1', NULL, 'permanent', '2026-01-15', NULL, NULL, 'active', '', '', '2', @sample_user),
('4', 'EMP-004', 'Tanvir Ahmed', '', '', NULL, 'male', '', '', '', '', 'Bangladeshi', '', '', '', '', '01000000403', 'tanvir@example.com', 'Sample address, Dhaka', '', '', '', '', '', '1', '2', '2', NULL, 'permanent', '2026-01-15', NULL, NULL, 'active', '', '', '3', @sample_user),
('5', 'EMP-005', 'Sumaiya Rahman', '', '', NULL, 'female', '', '', '', '', 'Bangladeshi', '', '', '', '', '01000000502', 'sumaiya@example.com', 'Sample address, Dhaka', '', '', '', '', '', '2', '3', '1', NULL, 'permanent', '2025-06-01', NULL, NULL, 'active', '', '', NULL, @sample_user),
('6', 'EMP-006', 'Imran Hossain', '', '', NULL, 'male', '', '', '', '', 'Bangladeshi', '', '', '', '', '01000000503', 'imran@example.com', 'Sample address, Dhaka', '', '', '', '', '', '3', '4', '1', NULL, 'permanent', '2025-09-10', NULL, NULL, 'active', '', '', NULL, @sample_user);

INSERT INTO `hr_leave_applications` (`application_id`, `employee_id`, `leave_type_id`, `from_date`, `to_date`, `half_day`, `days`, `reason`, `status`, `applied_via`, `decided_by`, `decided_at`, `decision_note`) VALUES
('1', '3', '2', '2026-11-04', '2026-11-04', '0', '1.0', 'Fever', 'pending', 'office', NULL, NULL, '');

INSERT INTO `hr_leave_balances` (`employee_id`, `leave_type_id`, `leave_year`, `allocated`, `updated_by`) VALUES
('1', '1', '2026', '10.0', @sample_user),
('1', '2', '2026', '14.0', @sample_user),
('1', '3', '2026', '18.0', @sample_user),
('2', '1', '2026', '10.0', @sample_user),
('2', '2', '2026', '14.0', @sample_user),
('2', '3', '2026', '18.0', @sample_user),
('3', '1', '2026', '10.0', @sample_user),
('3', '2', '2026', '14.0', @sample_user),
('3', '3', '2026', '18.0', @sample_user),
('4', '1', '2026', '10.0', @sample_user),
('4', '2', '2026', '14.0', @sample_user),
('4', '3', '2026', '18.0', @sample_user),
('5', '1', '2026', '10.0', @sample_user),
('5', '2', '2026', '14.0', @sample_user),
('5', '3', '2026', '18.0', @sample_user),
('6', '1', '2026', '10.0', @sample_user),
('6', '2', '2026', '14.0', @sample_user),
('6', '3', '2026', '18.0', @sample_user);

INSERT INTO `inv_doc_lines` (`line_id`, `doc_id`, `line_no`, `product_id`, `product_code`, `product_group`, `brand`, `model_caption`, `description`, `unit_name`, `warranty`, `store_name`, `store_to`, `quantity`, `free_quantity`, `unit_price`, `prev_price`, `discount_amount`, `line_total`, `narration`, `serials`, `source_kind`, `source_line_id`) VALUES
('1', '1', '1', '1', 'P-1001', 'Smartphone', 'Nova', 'Nova X5 4/64', 'Nova X5 smartphone 4GB/64GB', 'Pcs', '1 Year', 'Main Store', '', '20.000', '0.000', '12200.00', '0.00', '0.00', '244000.00', '', '', '', NULL),
('2', '1', '2', '2', 'P-1002', 'Smartphone', 'Nova', 'Nova X7 8/128', 'Nova X7 smartphone 8GB/128GB', 'Pcs', '1 Year', 'Main Store', '', '10.000', '0.000', '19800.00', '0.00', '0.00', '198000.00', '', '', '', NULL),
('3', '1', '3', '4', 'P-1004', 'Feature Phone', 'Kivo', 'Kivo K2 Dual SIM', 'Kivo K2 dual SIM feature phone', 'Pcs', '6 Months', 'Main Store', '', '40.000', '0.000', '1280.00', '0.00', '0.00', '51200.00', '', '', '', NULL),
('4', '1', '4', '7', 'P-3001', 'Charger & Cable', 'Lumo', 'Lumo 25W Charger', 'Lumo 25W USB-C fast charger', 'Pcs', '6 Months', 'Main Store', '', '60.000', '0.000', '620.00', '0.00', '0.00', '37200.00', '', '', '', NULL),
('5', '1', '5', '8', 'P-3002', 'Charger & Cable', 'Lumo', 'Lumo USB-C Cable 1m', 'Lumo USB-C to USB-C cable, 1 metre', 'Pcs', 'No Warranty', 'Main Store', '', '100.000', '0.000', '190.00', '0.00', '0.00', '19000.00', '', '', '', NULL),
('6', '2', '1', '5', 'P-2001', 'Laptop', 'Orbit', 'Orbit Book 14', 'Orbit Book 14 laptop, Core i5, 8GB, 512GB SSD', 'Pcs', '2 Years', 'Main Store', '', '8.000', '0.000', '61000.00', '0.00', '0.00', '488000.00', '', '', '', NULL),
('7', '2', '2', '6', 'P-2002', 'Laptop', 'Zentro', 'Zentro Air 15', 'Zentro Air 15 laptop, Ryzen 5, 16GB, 512GB SSD', 'Pcs', '2 Years', 'Main Store', '', '6.000', '0.000', '71500.00', '0.00', '0.00', '429000.00', '', '', '', NULL),
('8', '2', '3', '13', 'P-5001', 'Router', 'Zentro', 'Zentro AX1800', 'Zentro AX1800 dual-band Wi-Fi 6 router', 'Pcs', '1 Year', 'Main Store', '', '15.000', '0.000', '3900.00', '0.00', '0.00', '58500.00', '', '', '', NULL),
('9', '2', '4', '14', 'P-5002', 'Router', 'Kivo', 'Kivo 4G Pocket', 'Kivo 4G pocket router', 'Pcs', '6 Months', 'Main Store', '', '15.000', '0.000', '2550.00', '0.00', '0.00', '38250.00', '', '', '', NULL),
('10', '3', '1', '11', 'P-4001', 'LED TV', 'Orbit', 'Orbit 43 Smart', 'Orbit 43 inch smart LED TV', 'Pcs', '2 Years', 'Main Store', '', '10.000', '0.000', '33500.00', '0.00', '0.00', '335000.00', '', '', '', NULL),
('11', '3', '2', '12', 'P-4002', 'LED TV', 'Orbit', 'Orbit 32 LED', 'Orbit 32 inch LED TV', 'Pcs', '1 Year', 'Main Store', '', '12.000', '0.000', '18200.00', '0.00', '0.00', '218400.00', '', '', '', NULL),
('12', '3', '3', '3', 'P-1003', 'Smartphone', 'Zentro', 'Zentro Z10 6/128', 'Zentro Z10 smartphone 6GB/128GB', 'Pcs', '1 Year', 'Main Store', '', '12.000', '0.000', '15900.00', '0.00', '0.00', '190800.00', '', '', '', NULL),
('13', '3', '4', '9', 'P-3003', 'Headphone', 'Kivo', 'Kivo Buds Pro', 'Kivo Buds Pro wireless earbuds', 'Pcs', '6 Months', 'Main Store', '', '40.000', '0.000', '1750.00', '0.00', '0.00', '70000.00', '', '', '', NULL),
('14', '3', '5', '10', 'P-3004', 'Power Bank', 'Lumo', 'Lumo 20000mAh', 'Lumo 20000mAh power bank', 'Pcs', '6 Months', 'Main Store', '', '30.000', '0.000', '1600.00', '0.00', '0.00', '48000.00', '', '', '', NULL),
('15', '4', '1', '7', 'P-3001', 'Charger & Cable', 'Lumo', 'Lumo 25W Charger', 'Lumo 25W USB-C fast charger', 'Pcs', '6 Months', 'Main Store', '', '5.000', '0.000', '620.00', '0.00', '0.00', '3100.00', '', '', 'inv', '4'),
('16', '5', '1', '1', 'P-1001', 'Smartphone', 'Nova', 'Nova X5 4/64', 'Nova X5 smartphone 4GB/64GB', 'Pcs', '1 Year', '', '', '15.000', '0.000', '12200.00', '0.00', '0.00', '183000.00', '', '', '', NULL),
('17', '5', '2', '2', 'P-1002', 'Smartphone', 'Nova', 'Nova X7 8/128', 'Nova X7 smartphone 8GB/128GB', 'Pcs', '1 Year', '', '', '10.000', '0.000', '19800.00', '0.00', '0.00', '198000.00', '', '', '', NULL),
('18', '6', '1', '1', 'P-1001', 'Smartphone', 'Nova', 'Nova X5 4/64', 'Nova X5 smartphone 4GB/64GB', 'Pcs', '1 Year', 'Main Store', 'Showroom Store', '5.000', '0.000', '0.00', '0.00', '0.00', '0.00', '', '', '', NULL),
('19', '6', '2', '9', 'P-3003', 'Headphone', 'Kivo', 'Kivo Buds Pro', 'Kivo Buds Pro wireless earbuds', 'Pcs', '6 Months', 'Main Store', 'Showroom Store', '10.000', '0.000', '0.00', '0.00', '0.00', '0.00', '', '', '', NULL),
('20', '6', '3', '12', 'P-4002', 'LED TV', 'Orbit', 'Orbit 32 LED', 'Orbit 32 inch LED TV', 'Pcs', '1 Year', 'Main Store', 'Showroom Store', '4.000', '0.000', '0.00', '0.00', '0.00', '0.00', '', '', '', NULL),
('21', '7', '1', '8', 'P-3002', 'Charger & Cable', 'Lumo', 'Lumo USB-C Cable 1m', 'Lumo USB-C to USB-C cable, 1 metre', 'Pcs', 'No Warranty', 'Main Store', '', '2.000', '0.000', '190.00', '0.00', '0.00', '380.00', '', '', '', NULL),
('22', '8', '1', '10', 'P-3004', 'Power Bank', 'Lumo', 'Lumo 20000mAh', 'Lumo 20000mAh power bank', 'Pcs', '6 Months', 'Main Store', '', '1.000', '0.000', '1600.00', '0.00', '0.00', '1600.00', '', '', '', NULL),
('23', '9', '1', '5', 'P-2001', 'Laptop', 'Orbit', 'Orbit Book 14', 'Orbit Book 14 laptop, Core i5, 8GB, 512GB SSD', 'Pcs', '', 'Main Store', '', '2.000', '0.000', '68500.00', '0.00', '0.00', '137000.00', '', '', '', NULL),
('24', '9', '2', '13', 'P-5001', 'Router', 'Zentro', 'Zentro AX1800', 'Zentro AX1800 dual-band Wi-Fi 6 router', 'Pcs', '', 'Main Store', '', '2.000', '0.000', '4800.00', '0.00', '0.00', '9600.00', '', '', '', NULL),
('25', '10', '1', '1', 'P-1001', 'Smartphone', 'Nova', 'Nova X5 4/64', 'Nova X5 smartphone 4GB/64GB', 'Pcs', '', 'Main Store', '', '1.000', '0.000', '14500.00', '0.00', '0.00', '14500.00', '', '', '', NULL),
('26', '10', '2', '7', 'P-3001', 'Charger & Cable', 'Lumo', 'Lumo 25W Charger', 'Lumo 25W USB-C fast charger', 'Pcs', '', 'Main Store', '', '1.000', '0.000', '950.00', '0.00', '0.00', '950.00', '', '', '', NULL),
('27', '11', '1', '1', 'P-1001', 'Smartphone', 'Nova', 'Nova X5 4/64', 'Nova X5 smartphone 4GB/64GB', 'Pcs', '', 'Main Store', '', '5.000', '0.000', '13900.00', '0.00', '0.00', '69500.00', '', '', '', NULL),
('28', '11', '2', '4', 'P-1004', 'Feature Phone', 'Kivo', 'Kivo K2 Dual SIM', 'Kivo K2 dual SIM feature phone', 'Pcs', '', 'Main Store', '', '10.000', '0.000', '1550.00', '0.00', '0.00', '15500.00', '', '', '', NULL),
('29', '11', '3', '8', 'P-3002', 'Charger & Cable', 'Lumo', 'Lumo USB-C Cable 1m', 'Lumo USB-C to USB-C cable, 1 metre', 'Pcs', '', 'Main Store', '', '20.000', '0.000', '320.00', '0.00', '0.00', '6400.00', '', '', '', NULL),
('30', '12', '1', '11', 'P-4001', 'LED TV', 'Orbit', 'Orbit 43 Smart', 'Orbit 43 inch smart LED TV', 'Pcs', '', 'Main Store', '', '2.000', '0.000', '38500.00', '0.00', '0.00', '77000.00', '', '', '', NULL),
('31', '12', '2', '6', 'P-2002', 'Laptop', 'Zentro', 'Zentro Air 15', 'Zentro Air 15 laptop, Ryzen 5, 16GB, 512GB SSD', 'Pcs', '', 'Main Store', '', '1.000', '0.000', '79900.00', '0.00', '0.00', '79900.00', '', '', '', NULL),
('32', '13', '1', '12', 'P-4002', 'LED TV', 'Orbit', 'Orbit 32 LED', 'Orbit 32 inch LED TV', 'Pcs', '', 'Showroom Store', '', '4.000', '0.000', '21000.00', '0.00', '0.00', '84000.00', '', '', '', NULL),
('33', '13', '2', '14', 'P-5002', 'Router', 'Kivo', 'Kivo 4G Pocket', 'Kivo 4G pocket router', 'Pcs', '', 'Main Store', '', '3.000', '0.000', '3200.00', '0.00', '0.00', '9600.00', '', '', '', NULL),
('34', '14', '1', '3', 'P-1003', 'Smartphone', 'Zentro', 'Zentro Z10 6/128', 'Zentro Z10 smartphone 6GB/128GB', 'Pcs', '', 'Main Store', '', '1.000', '0.000', '18500.00', '0.00', '0.00', '18500.00', '', '', '', NULL),
('35', '14', '2', '9', 'P-3003', 'Headphone', 'Kivo', 'Kivo Buds Pro', 'Kivo Buds Pro wireless earbuds', 'Pcs', '', 'Showroom Store', '', '1.000', '0.000', '2450.00', '0.00', '0.00', '2450.00', '', '', '', NULL),
('36', '15', '1', '2', 'P-1002', 'Smartphone', 'Nova', 'Nova X7 8/128', 'Nova X7 smartphone 8GB/128GB', 'Pcs', '', 'Main Store', '', '3.000', '0.000', '22400.00', '0.00', '0.00', '67200.00', '', '', '', NULL),
('37', '15', '2', '10', 'P-3004', 'Power Bank', 'Lumo', 'Lumo 20000mAh', 'Lumo 20000mAh power bank', 'Pcs', '', 'Main Store', '', '5.000', '0.000', '2100.00', '0.00', '0.00', '10500.00', '', '', '', NULL),
('38', '16', '1', '5', 'P-2001', 'Laptop', 'Orbit', 'Orbit Book 14', 'Orbit Book 14 laptop, Core i5, 8GB, 512GB SSD', 'Pcs', '', 'Main Store', '', '1.000', '0.000', '68500.00', '0.00', '0.00', '68500.00', '', '', '', NULL),
('39', '16', '2', '10', 'P-3004', 'Power Bank', 'Lumo', 'Lumo 20000mAh', 'Lumo 20000mAh power bank', 'Pcs', '', 'Main Store', '', '2.000', '0.000', '2200.00', '0.00', '0.00', '4400.00', '', '', '', NULL),
('40', '17', '1', '4', 'P-1004', 'Feature Phone', 'Kivo', 'Kivo K2 Dual SIM', 'Kivo K2 dual SIM feature phone', 'Pcs', '', 'Main Store', '', '2.000', '0.000', '1650.00', '0.00', '0.00', '3300.00', '', '', '', NULL),
('41', '17', '2', '8', 'P-3002', 'Charger & Cable', 'Lumo', 'Lumo USB-C Cable 1m', 'Lumo USB-C to USB-C cable, 1 metre', 'Pcs', '', 'Main Store', '', '3.000', '0.000', '350.00', '0.00', '0.00', '1050.00', '', '', '', NULL),
('42', '18', '1', '4', 'P-1004', 'Feature Phone', 'Kivo', 'Kivo K2 Dual SIM', 'Kivo K2 dual SIM feature phone', 'Pcs', '', 'Main Store', '', '2.000', '0.000', '1550.00', '0.00', '0.00', '3100.00', '', '', '', NULL),
('43', '19', '1', '5', 'P-2001', 'Laptop', 'Orbit', 'Orbit Book 14', 'Orbit Book 14 laptop, Core i5, 8GB, 512GB SSD', 'Pcs', '2 Years', '', '', '5.000', '0.000', '67500.00', '0.00', '0.00', '337500.00', '', '', '', NULL),
('44', '20', '1', '13', 'P-5001', 'Router', 'Zentro', 'Zentro AX1800', 'Zentro AX1800 dual-band Wi-Fi 6 router', 'Pcs', '1 Year', 'Main Store', '', '1.000', '0.000', '0.00', '0.00', '0.00', '0.00', '', '', '', NULL),
('45', '21', '1', '7', 'P-3001', 'Charger & Cable', 'Lumo', 'Lumo 25W Charger', 'Lumo 25W USB-C fast charger', 'Pcs', '', 'Main Store', '', '2.000', '0.000', '950.00', '0.00', '0.00', '1900.00', '', '', '', NULL),
('46', '21', '2', '8', 'P-3002', 'Charger & Cable', 'Lumo', 'Lumo USB-C Cable 1m', 'Lumo USB-C to USB-C cable, 1 metre', 'Pcs', '', 'Main Store', '', '2.000', '0.000', '350.00', '0.00', '0.00', '700.00', '', '', '', NULL);

INSERT INTO `inv_docs` (`doc_id`, `doc_type`, `doc_no`, `doc_date`, `ref_no`, `branch_id`, `party_type`, `party_id`, `party_group`, `party_name`, `mobile`, `address`, `attention`, `sales_person`, `delivery_point`, `narration`, `additional_info`, `bill_type`, `source_type`, `source_doc_id`, `source_no`, `payment_term`, `required_date`, `order_date`, `subtotal`, `extra_charges`, `discount_amount`, `vat_amount`, `net_amount`, `cash_received`, `total_quantity`, `sales_invoice_id`, `status`, `approval_status`, `approved_by`, `approved_at`, `created_by`) VALUES
('1', 'purchase', 'DESPUR2026000001', '2026-09-05', 'DD-INV-5521', '1', 'supplier', '1', 'Local Suppliers', 'Delta Distribution Ltd.', '01000000101', '8 Warehouse Lane, Tejgaon, Dhaka', '', '', '', 'Opening phone and accessory stock', NULL, 'Credit', '', NULL, '', '', NULL, '2026-09-01', '549400.00', '0.00', '0.00', '0.00', '549400.00', '200000.00', '230.000', NULL, 'active', 'approved', @sample_user, '2026-11-06 20:40:47', @sample_user),
('2', 'purchase', 'DESPUR2026000002', '2026-09-12', 'MTS-0934', '1', 'supplier', '2', 'Local Suppliers', 'Meridian Tech Supply', '01000000102', '22 Market Road, Motijheel, Dhaka', '', '', '', 'Laptops and routers', NULL, 'Credit', '', NULL, '', '', NULL, NULL, '1013750.00', '0.00', '0.00', '0.00', '1013750.00', '0.00', '44.000', NULL, 'active', 'approved', @sample_user, '2026-11-06 20:40:47', @sample_user),
('3', 'purchase', 'DESPUR2026000003', '2026-09-20', 'BH-77812', '1', 'supplier', '3', 'Importers', 'Blue Harbor Trading Co.', '01000000103', '5 Port Road, Agrabad, Chattogram', '', '', '', 'TVs, phones and audio', NULL, 'Cash', '', NULL, '', '', NULL, NULL, '862200.00', '0.00', '0.00', '0.00', '862200.00', '0.00', '104.000', NULL, 'active', 'approved', @sample_user, '2026-11-06 20:40:47', @sample_user),
('4', 'purchase_return', 'DESPRT2026000001', '2026-09-25', 'RET-01', '1', 'supplier', '1', 'Local Suppliers', 'Delta Distribution Ltd.', '01000000101', '8 Warehouse Lane, Tejgaon, Dhaka', '', '', '', 'Five chargers with damaged boxes', NULL, 'Credit', 'purchase', '1', 'DESPUR2026000001', '', NULL, NULL, '3100.00', '0.00', '0.00', '0.00', '3100.00', '0.00', '5.000', NULL, 'active', 'approved', @sample_user, '2026-11-06 20:40:47', @sample_user),
('5', 'purchase_order', 'DESPOR2026000001', '2026-10-25', 'PO-REQ-07', '1', 'supplier', '1', 'Local Suppliers', 'Delta Distribution Ltd.', '01000000101', '8 Warehouse Lane, Tejgaon, Dhaka', '', '', '', 'Restock before the festive season', NULL, 'Credit', '', NULL, '', '30 days credit', '2026-11-10', NULL, '381000.00', '0.00', '0.00', '0.00', '381000.00', '0.00', '25.000', NULL, 'active', 'pending', NULL, NULL, @sample_user),
('6', 'stock_transfer', 'DESSTT2026000001', '2026-09-22', 'TR-01', '1', 'none', NULL, '', '', '', '', '', '', '', 'Display stock for the showroom', NULL, 'Credit', '', NULL, '', '', NULL, NULL, '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '19.000', NULL, 'active', 'approved', @sample_user, '2026-11-06 20:40:47', @sample_user),
('7', 'stock_in', 'DESSAI2026000001', '2026-09-30', 'COUNT-0930', '1', 'none', NULL, '', '', '', '', '', '', '', 'Stock count: two extra cables found', NULL, 'Credit', '', NULL, '', '', NULL, NULL, '380.00', '0.00', '0.00', '0.00', '380.00', '0.00', '2.000', NULL, 'active', 'approved', @sample_user, '2026-11-06 20:40:47', @sample_user),
('8', 'stock_out', 'DESSAO2026000001', '2026-09-30', 'COUNT-0930', '1', 'none', NULL, '', '', '', '', '', '', '', 'Stock count: one power bank damaged', NULL, 'Credit', '', NULL, '', '', NULL, NULL, '1600.00', '0.00', '0.00', '0.00', '1600.00', '0.00', '1.000', NULL, 'active', 'approved', @sample_user, '2026-11-06 20:40:48', @sample_user),
('9', 'sales_invoice', 'SI2026000001', '2026-09-08', '', '1', 'customer', '3', 'Corporate', 'Greenfield School', '01000000301', '', '', '', '', '', NULL, 'Credit', '', NULL, '', NULL, NULL, NULL, '0.00', '0.00', '0.00', '0.00', '146600.00', '0.00', '4.000', '1', 'active', 'approved', @sample_user, '2026-11-06 20:40:48', @sample_user),
('10', 'sales_invoice', 'SI2026000002', '2026-09-10', '', '1', 'customer', NULL, 'Cash Customer', 'Walk-in Customer', '01000000991', '', '', '', '', '', NULL, 'Credit', '', NULL, '', NULL, NULL, NULL, '0.00', '0.00', '0.00', '0.00', '15450.00', '0.00', '2.000', '2', 'active', 'approved', @sample_user, '2026-11-06 20:40:48', @sample_user),
('11', 'sales_invoice', 'SI2026000003', '2026-09-15', '', '1', 'customer', '1', 'Dhaka Dealers', 'City Gadget Point', '01000000201', '', '', '', '', '', NULL, 'Credit', '', NULL, '', NULL, NULL, NULL, '0.00', '0.00', '0.00', '0.00', '91400.00', '0.00', '35.000', '3', 'active', 'approved', @sample_user, '2026-11-06 20:40:48', @sample_user),
('12', 'sales_invoice', 'SI2026000004', '2026-09-24', '', '1', 'customer', '4', 'Corporate', 'Sunrise Pharma Ltd.', '01000000302', '', '', '', '', '', NULL, 'Credit', '', NULL, '', NULL, NULL, NULL, '0.00', '0.00', '0.00', '0.00', '156900.00', '0.00', '3.000', '4', 'active', 'approved', @sample_user, '2026-11-06 20:40:48', @sample_user),
('13', 'sales_invoice', 'SI2026000005', '2026-10-03', '', '1', 'customer', '5', 'Corporate', 'Orchid Hotel & Resort', '01000000303', '', '', '', '', '', NULL, 'Credit', '', NULL, '', NULL, NULL, NULL, '0.00', '0.00', '0.00', '0.00', '93600.00', '0.00', '7.000', '5', 'active', 'approved', @sample_user, '2026-11-06 20:40:48', @sample_user),
('14', 'sales_invoice', 'SI2026000006', '2026-10-12', '', '1', 'customer', NULL, 'Cash Customer', 'Walk-in Customer', '01000000995', '', '', '', '', '', NULL, 'Credit', '', NULL, '', NULL, NULL, NULL, '0.00', '0.00', '0.00', '0.00', '20950.00', '0.00', '2.000', '6', 'active', 'approved', @sample_user, '2026-11-06 20:40:48', @sample_user),
('15', 'sales_invoice', 'SI2026000007', '2026-10-20', '', '1', 'customer', '2', 'Chattogram Dealers', 'Riverside Electronics', '01000000202', '', '', '', '', '', NULL, 'Credit', '', NULL, '', NULL, NULL, NULL, '0.00', '0.00', '0.00', '0.00', '77700.00', '0.00', '8.000', '7', 'active', 'approved', @sample_user, '2026-11-06 20:40:48', @sample_user),
('16', 'sales_invoice', 'SI2026000008', '2026-11-02', '', '1', 'customer', '6', 'Retail Credit', 'Lakeview Clinic', '01000000304', '', '', '', '', '', NULL, 'Credit', '', NULL, '', NULL, NULL, NULL, '0.00', '0.00', '0.00', '0.00', '72900.00', '0.00', '3.000', '8', 'active', 'approved', @sample_user, '2026-11-06 20:40:48', @sample_user),
('17', 'sales_invoice', 'SI2026000009', '2026-11-05', '', '1', 'customer', NULL, 'Cash Customer', 'Walk-in Customer', '01000000998', '', '', '', '', '', NULL, 'Credit', '', NULL, '', NULL, NULL, NULL, '0.00', '0.00', '0.00', '0.00', '4350.00', '0.00', '5.000', '9', 'active', 'pending', NULL, NULL, @sample_user),
('18', 'sales_return', 'SR2026000001', '2026-09-20', '', '1', 'customer', '1', 'Dhaka Dealers', 'City Gadget Point', '01000000201', '', '', '', '', '', NULL, 'Credit', '', NULL, '', NULL, NULL, NULL, '0.00', '0.00', '0.00', '0.00', '3100.00', '0.00', '2.000', '3', 'active', 'approved', @sample_user, '2026-11-06 20:40:48', @sample_user),
('19', 'quotation', 'DESPQI2026000001', '2026-10-30', 'Q-SP-01', '1', 'customer', '4', 'Corporate', 'Sunrise Pharma Ltd.', '01000000302', '77 Industrial Area, Gazipur', 'Procurement', 'Nadia Karim', '', 'Laptops for the new office', NULL, 'Credit', '', NULL, '', '50% advance, rest on delivery. Valid for 15 days.', NULL, NULL, '337500.00', '0.00', '0.00', '0.00', '337500.00', '0.00', '5.000', NULL, 'active', 'pending', NULL, NULL, @sample_user),
('20', 'challan', 'DESCHL2026000001', '2026-11-03', 'DC-01', '1', 'customer', '6', 'Retail Credit', 'Lakeview Clinic', '01000000304', '9 Lake Road, Gulshan, Dhaka', 'Manager', 'Arif Hasan', 'Clinic reception', 'Router for trial', NULL, 'Credit', '', NULL, '', '', NULL, NULL, '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '1.000', NULL, 'active', 'pending', NULL, NULL, @sample_user),
('21', 'sales_invoice', 'SI2026000010', '2026-11-06', 'POS', '1', 'customer', NULL, '', 'Walk-in Customer', '', '', '', '', '', '', NULL, 'Credit', '', NULL, '', NULL, NULL, NULL, '0.00', '0.00', '0.00', '0.00', '2600.00', '0.00', '4.000', '10', 'active', 'pending', NULL, NULL, @sample_user);

INSERT INTO `inv_stock_moves` (`move_id`, `product_id`, `store_name`, `branch_id`, `move_date`, `qty_in`, `qty_out`, `unit_cost`, `doc_type`, `doc_id`, `doc_no`, `line_id`) VALUES
('1', '1', 'Main Store', '1', '2026-09-05', '20.000', '0.000', '12200.00', 'purchase', '1', 'DESPUR2026000001', '1'),
('2', '2', 'Main Store', '1', '2026-09-05', '10.000', '0.000', '19800.00', 'purchase', '1', 'DESPUR2026000001', '2'),
('3', '4', 'Main Store', '1', '2026-09-05', '40.000', '0.000', '1280.00', 'purchase', '1', 'DESPUR2026000001', '3'),
('4', '7', 'Main Store', '1', '2026-09-05', '60.000', '0.000', '620.00', 'purchase', '1', 'DESPUR2026000001', '4'),
('5', '8', 'Main Store', '1', '2026-09-05', '100.000', '0.000', '190.00', 'purchase', '1', 'DESPUR2026000001', '5'),
('6', '5', 'Main Store', '1', '2026-09-12', '8.000', '0.000', '61000.00', 'purchase', '2', 'DESPUR2026000002', '6'),
('7', '6', 'Main Store', '1', '2026-09-12', '6.000', '0.000', '71500.00', 'purchase', '2', 'DESPUR2026000002', '7'),
('8', '13', 'Main Store', '1', '2026-09-12', '15.000', '0.000', '3900.00', 'purchase', '2', 'DESPUR2026000002', '8'),
('9', '14', 'Main Store', '1', '2026-09-12', '15.000', '0.000', '2550.00', 'purchase', '2', 'DESPUR2026000002', '9'),
('10', '11', 'Main Store', '1', '2026-09-20', '10.000', '0.000', '33500.00', 'purchase', '3', 'DESPUR2026000003', '10'),
('11', '12', 'Main Store', '1', '2026-09-20', '12.000', '0.000', '18200.00', 'purchase', '3', 'DESPUR2026000003', '11'),
('12', '3', 'Main Store', '1', '2026-09-20', '12.000', '0.000', '15900.00', 'purchase', '3', 'DESPUR2026000003', '12'),
('13', '9', 'Main Store', '1', '2026-09-20', '40.000', '0.000', '1750.00', 'purchase', '3', 'DESPUR2026000003', '13'),
('14', '10', 'Main Store', '1', '2026-09-20', '30.000', '0.000', '1600.00', 'purchase', '3', 'DESPUR2026000003', '14'),
('15', '7', 'Main Store', '1', '2026-09-25', '0.000', '5.000', '620.00', 'purchase_return', '4', 'DESPRT2026000001', '15'),
('16', '1', 'Main Store', '1', '2026-09-22', '0.000', '5.000', '0.00', 'stock_transfer', '6', 'DESSTT2026000001', '18'),
('17', '1', 'Showroom Store', '1', '2026-09-22', '5.000', '0.000', '0.00', 'stock_transfer', '6', 'DESSTT2026000001', '18'),
('18', '9', 'Main Store', '1', '2026-09-22', '0.000', '10.000', '0.00', 'stock_transfer', '6', 'DESSTT2026000001', '19'),
('19', '9', 'Showroom Store', '1', '2026-09-22', '10.000', '0.000', '0.00', 'stock_transfer', '6', 'DESSTT2026000001', '19'),
('20', '12', 'Main Store', '1', '2026-09-22', '0.000', '4.000', '0.00', 'stock_transfer', '6', 'DESSTT2026000001', '20'),
('21', '12', 'Showroom Store', '1', '2026-09-22', '4.000', '0.000', '0.00', 'stock_transfer', '6', 'DESSTT2026000001', '20'),
('22', '8', 'Main Store', '1', '2026-09-30', '2.000', '0.000', '190.00', 'stock_in', '7', 'DESSAI2026000001', '21'),
('23', '10', 'Main Store', '1', '2026-09-30', '0.000', '1.000', '1600.00', 'stock_out', '8', 'DESSAO2026000001', '22'),
('24', '5', 'Main Store', '1', '2026-09-08', '0.000', '2.000', '68500.00', 'sales_invoice', '9', 'SI2026000001', '23'),
('25', '13', 'Main Store', '1', '2026-09-08', '0.000', '2.000', '4800.00', 'sales_invoice', '9', 'SI2026000001', '24'),
('26', '1', 'Main Store', '1', '2026-09-10', '0.000', '1.000', '14500.00', 'sales_invoice', '10', 'SI2026000002', '25'),
('27', '7', 'Main Store', '1', '2026-09-10', '0.000', '1.000', '950.00', 'sales_invoice', '10', 'SI2026000002', '26'),
('28', '1', 'Main Store', '1', '2026-09-15', '0.000', '5.000', '13900.00', 'sales_invoice', '11', 'SI2026000003', '27'),
('29', '4', 'Main Store', '1', '2026-09-15', '0.000', '10.000', '1550.00', 'sales_invoice', '11', 'SI2026000003', '28'),
('30', '8', 'Main Store', '1', '2026-09-15', '0.000', '20.000', '320.00', 'sales_invoice', '11', 'SI2026000003', '29'),
('31', '11', 'Main Store', '1', '2026-09-24', '0.000', '2.000', '38500.00', 'sales_invoice', '12', 'SI2026000004', '30'),
('32', '6', 'Main Store', '1', '2026-09-24', '0.000', '1.000', '79900.00', 'sales_invoice', '12', 'SI2026000004', '31'),
('33', '12', 'Showroom Store', '1', '2026-10-03', '0.000', '4.000', '21000.00', 'sales_invoice', '13', 'SI2026000005', '32'),
('34', '14', 'Main Store', '1', '2026-10-03', '0.000', '3.000', '3200.00', 'sales_invoice', '13', 'SI2026000005', '33'),
('35', '3', 'Main Store', '1', '2026-10-12', '0.000', '1.000', '18500.00', 'sales_invoice', '14', 'SI2026000006', '34'),
('36', '9', 'Showroom Store', '1', '2026-10-12', '0.000', '1.000', '2450.00', 'sales_invoice', '14', 'SI2026000006', '35'),
('37', '2', 'Main Store', '1', '2026-10-20', '0.000', '3.000', '22400.00', 'sales_invoice', '15', 'SI2026000007', '36'),
('38', '10', 'Main Store', '1', '2026-10-20', '0.000', '5.000', '2100.00', 'sales_invoice', '15', 'SI2026000007', '37'),
('39', '5', 'Main Store', '1', '2026-11-02', '0.000', '1.000', '68500.00', 'sales_invoice', '16', 'SI2026000008', '38'),
('40', '10', 'Main Store', '1', '2026-11-02', '0.000', '2.000', '2200.00', 'sales_invoice', '16', 'SI2026000008', '39'),
('41', '4', 'Main Store', '1', '2026-11-05', '0.000', '2.000', '1650.00', 'sales_invoice', '17', 'SI2026000009', '40'),
('42', '8', 'Main Store', '1', '2026-11-05', '0.000', '3.000', '350.00', 'sales_invoice', '17', 'SI2026000009', '41'),
('43', '4', 'Main Store', '1', '2026-09-20', '2.000', '0.000', '1550.00', 'sales_return', '18', 'SR2026000001', '42'),
('44', '13', 'Main Store', '1', '2026-11-03', '0.000', '1.000', '0.00', 'challan', '20', 'DESCHL2026000001', '44'),
('45', '7', 'Main Store', '1', '2026-11-06', '0.000', '2.000', '950.00', 'sales_invoice', '21', 'SI2026000010', '45'),
('46', '8', 'Main Store', '1', '2026-11-06', '0.000', '2.000', '350.00', 'sales_invoice', '21', 'SI2026000010', '46');

INSERT INTO `product_categories` (`category_id`, `category_name`, `description`, `sort_order`, `is_active`, `created_by`) VALUES
('1', 'Mobile Phones', 'Smartphones and feature phones', '1', '1', NULL),
('2', 'Laptops & Computers', 'Notebooks and desktops', '2', '1', NULL),
('3', 'Accessories', 'Chargers, cables, audio, power', '3', '1', NULL),
('4', 'TV & Display', 'Televisions and monitors', '4', '1', NULL),
('5', 'Networking', 'Routers and network devices', '5', '1', NULL);

INSERT INTO `product_classifications` (`class_id`, `class_type`, `class_name`, `category_id`, `is_active`, `created_by`) VALUES
('1', 'group', 'Smartphone', '1', '1', NULL),
('2', 'group', 'Feature Phone', '1', '1', NULL),
('3', 'group', 'Laptop', '2', '1', NULL),
('4', 'group', 'Charger & Cable', '3', '1', NULL),
('5', 'group', 'Headphone', '3', '1', NULL),
('6', 'group', 'Power Bank', '3', '1', NULL),
('7', 'group', 'LED TV', '4', '1', NULL),
('8', 'group', 'Router', '5', '1', NULL),
('9', 'brand', 'Nova', NULL, '1', NULL),
('10', 'brand', 'Zentro', NULL, '1', NULL),
('11', 'brand', 'Kivo', NULL, '1', NULL),
('12', 'brand', 'Orbit', NULL, '1', NULL),
('13', 'brand', 'Lumo', NULL, '1', NULL);

INSERT INTO `purchase_payments` (`payment_id`, `doc_id`, `paid_on`, `payment_method`, `amount`, `reference`, `note`, `is_initial`, `created_by`) VALUES
('1', '1', '2026-09-05', 'cash', '200000.00', 'Cash Amount on purchase', '', '1', @sample_user);

INSERT INTO `rma_complain_items` (`item_id`, `complain_id`, `line_number`, `product_id`, `product_group`, `brand`, `model_no`, `description`, `sequence_type`, `under_warranty`, `sales_invoice_no`, `sales_value`, `sales_date`, `sold_serial`, `store_name`, `delivery_date`, `problem_id`, `problem_name`, `engineer_name`, `no_of_time`, `pre_job_ref_no`, `narration`, `qty`, `received_serial`, `amount_charges`, `need_diagnosis`, `line_status`) VALUES
('1', '1', '1', '2', 'Smartphone', 'Nova', 'Nova X7 8/128', 'Nova X7 smartphone 8GB/128GB', 'not_available', '1', 'SI2026000007', '22400.00', '2026-10-20', '', 'RMA STORE', '2026-11-11', NULL, 'Hang Problem', 'Imran Hossain', '1', '', '', '1.000', '', '0.00', '1', 'received');

INSERT INTO `rma_complains` (`complain_id`, `complain_no`, `ref_no`, `complain_date`, `customer_id`, `customer_group`, `customer_name`, `mobile`, `address`, `attention`, `status`, `approved_by`, `approved_at`, `created_by`, `created_by_name`) VALUES
('1', 'DESCOM2026000001', '', '2026-11-04', '2', 'Chattogram Dealers', 'Riverside Electronics', '01000000202', '31 River View Road, Chattogram', 'Owner', 'draft', NULL, NULL, @sample_user, 'admin');

INSERT INTO `rma_suppliers` (`supplier_id`, `supplier_group`, `supplier_name`, `mobile`, `attention`, `address`, `is_active`) VALUES
('1', 'Local Suppliers', 'Delta Distribution Ltd.', '01000000101', 'Sales Desk', '8 Warehouse Lane, Tejgaon, Dhaka', '1'),
('2', 'Local Suppliers', 'Meridian Tech Supply', '01000000102', 'Accounts', '22 Market Road, Motijheel, Dhaka', '1'),
('3', 'Importers', 'Blue Harbor Trading Co.', '01000000103', 'Import Desk', '5 Port Road, Agrabad, Chattogram', '1');

INSERT INTO `sales_customers` (`customer_id`, `customer_code`, `customer_group`, `customer_name`, `mobile`, `attention`, `address`, `bin`, `nid`, `branch_id`) VALUES
('1', 'DLR000004', 'Dhaka Dealers', 'City Gadget Point', '01000000201', 'Store Manager', 'Shop 12, Sample Plaza, Dhanmondi, Dhaka', '', '', NULL),
('2', 'DLR000005', 'Chattogram Dealers', 'Riverside Electronics', '01000000202', 'Owner', '31 River View Road, Chattogram', '', '', NULL),
('3', 'CRC000006', 'Corporate', 'Greenfield School', '01000000301', 'Admin Office', '1 School Road, Uttara, Dhaka', '', '', NULL),
('4', 'CRC000007', 'Corporate', 'Sunrise Pharma Ltd.', '01000000302', 'Procurement', '77 Industrial Area, Gazipur', '', '', NULL),
('5', 'CRC000008', 'Corporate', 'Orchid Hotel & Resort', '01000000303', 'Purchase Dept.', 'Beach Road, Cox\'s Bazar', '', '', NULL),
('6', 'CRC000009', 'Retail Credit', 'Lakeview Clinic', '01000000304', 'Manager', '9 Lake Road, Gulshan, Dhaka', '', '', NULL);

INSERT INTO `sales_invoice_items` (`item_id`, `invoice_id`, `product_id`, `product_code`, `product_group`, `brand`, `model_caption`, `description`, `product_narration`, `warranty`, `store_name`, `unit_name`, `sequence_type`, `serial_clear`, `serial_storage`, `serial_common`, `serial_from`, `serial_to`, `serial_imei`, `quantity`, `free_quantity`, `unit_price`, `unit_cost`, `discount_amount`, `line_total`, `line_number`) VALUES
('1', '1', '5', 'P-2001', 'Laptop', 'Orbit', 'Orbit Book 14', 'Orbit Book 14 laptop, Core i5, 8GB, 512GB SSD', '', '2 Years', 'Main Store', 'Pcs', 'not_available', '0', '0', '', '', '', '', '2.000', '0.000', '68500.00', '61000.00', '0.00', '137000.00', '1'),
('2', '1', '13', 'P-5001', 'Router', 'Zentro', 'Zentro AX1800', 'Zentro AX1800 dual-band Wi-Fi 6 router', '', '1 Year', 'Main Store', 'Pcs', 'not_available', '0', '0', '', '', '', '', '2.000', '0.000', '4800.00', '3900.00', '0.00', '9600.00', '2'),
('3', '2', '1', 'P-1001', 'Smartphone', 'Nova', 'Nova X5 4/64', 'Nova X5 smartphone 4GB/64GB', '', '1 Year', 'Main Store', 'Pcs', 'not_available', '0', '0', '', '', '', '', '1.000', '0.000', '14500.00', '12200.00', '0.00', '14500.00', '1'),
('4', '2', '7', 'P-3001', 'Charger & Cable', 'Lumo', 'Lumo 25W Charger', 'Lumo 25W USB-C fast charger', '', '6 Months', 'Main Store', 'Pcs', 'not_available', '0', '0', '', '', '', '', '1.000', '0.000', '950.00', '620.00', '0.00', '950.00', '2'),
('5', '3', '1', 'P-1001', 'Smartphone', 'Nova', 'Nova X5 4/64', 'Nova X5 smartphone 4GB/64GB', '', '1 Year', 'Main Store', 'Pcs', 'not_available', '0', '0', '', '', '', '', '5.000', '0.000', '13900.00', '12200.00', '0.00', '69500.00', '1'),
('6', '3', '4', 'P-1004', 'Feature Phone', 'Kivo', 'Kivo K2 Dual SIM', 'Kivo K2 dual SIM feature phone', '', '6 Months', 'Main Store', 'Pcs', 'not_available', '0', '0', '', '', '', '', '10.000', '0.000', '1550.00', '1280.00', '0.00', '15500.00', '2'),
('7', '3', '8', 'P-3002', 'Charger & Cable', 'Lumo', 'Lumo USB-C Cable 1m', 'Lumo USB-C to USB-C cable, 1 metre', '', 'No Warranty', 'Main Store', 'Pcs', 'not_available', '0', '0', '', '', '', '', '20.000', '0.000', '320.00', '190.00', '0.00', '6400.00', '3'),
('8', '4', '11', 'P-4001', 'LED TV', 'Orbit', 'Orbit 43 Smart', 'Orbit 43 inch smart LED TV', '', '2 Years', 'Main Store', 'Pcs', 'not_available', '0', '0', '', '', '', '', '2.000', '0.000', '38500.00', '33500.00', '0.00', '77000.00', '1'),
('9', '4', '6', 'P-2002', 'Laptop', 'Zentro', 'Zentro Air 15', 'Zentro Air 15 laptop, Ryzen 5, 16GB, 512GB SSD', '', '2 Years', 'Main Store', 'Pcs', 'not_available', '0', '0', '', '', '', '', '1.000', '0.000', '79900.00', '71500.00', '0.00', '79900.00', '2'),
('10', '5', '12', 'P-4002', 'LED TV', 'Orbit', 'Orbit 32 LED', 'Orbit 32 inch LED TV', '', '1 Year', 'Showroom Store', 'Pcs', 'not_available', '0', '0', '', '', '', '', '4.000', '0.000', '21000.00', '18200.00', '0.00', '84000.00', '1'),
('11', '5', '14', 'P-5002', 'Router', 'Kivo', 'Kivo 4G Pocket', 'Kivo 4G pocket router', '', '6 Months', 'Main Store', 'Pcs', 'not_available', '0', '0', '', '', '', '', '3.000', '0.000', '3200.00', '2550.00', '0.00', '9600.00', '2'),
('12', '6', '3', 'P-1003', 'Smartphone', 'Zentro', 'Zentro Z10 6/128', 'Zentro Z10 smartphone 6GB/128GB', '', '1 Year', 'Main Store', 'Pcs', 'not_available', '0', '0', '', '', '', '', '1.000', '0.000', '18500.00', '15900.00', '0.00', '18500.00', '1'),
('13', '6', '9', 'P-3003', 'Headphone', 'Kivo', 'Kivo Buds Pro', 'Kivo Buds Pro wireless earbuds', '', '6 Months', 'Showroom Store', 'Pcs', 'not_available', '0', '0', '', '', '', '', '1.000', '0.000', '2450.00', '1750.00', '0.00', '2450.00', '2'),
('14', '7', '2', 'P-1002', 'Smartphone', 'Nova', 'Nova X7 8/128', 'Nova X7 smartphone 8GB/128GB', '', '1 Year', 'Main Store', 'Pcs', 'not_available', '0', '0', '', '', '', '', '3.000', '0.000', '22400.00', '19800.00', '0.00', '67200.00', '1'),
('15', '7', '10', 'P-3004', 'Power Bank', 'Lumo', 'Lumo 20000mAh', 'Lumo 20000mAh power bank', '', '6 Months', 'Main Store', 'Pcs', 'not_available', '0', '0', '', '', '', '', '5.000', '0.000', '2100.00', '1600.00', '0.00', '10500.00', '2'),
('16', '8', '5', 'P-2001', 'Laptop', 'Orbit', 'Orbit Book 14', 'Orbit Book 14 laptop, Core i5, 8GB, 512GB SSD', '', '2 Years', 'Main Store', 'Pcs', 'not_available', '0', '0', '', '', '', '', '1.000', '0.000', '68500.00', '61000.00', '0.00', '68500.00', '1'),
('17', '8', '10', 'P-3004', 'Power Bank', 'Lumo', 'Lumo 20000mAh', 'Lumo 20000mAh power bank', '', '6 Months', 'Main Store', 'Pcs', 'not_available', '0', '0', '', '', '', '', '2.000', '0.000', '2200.00', '1600.00', '0.00', '4400.00', '2'),
('18', '9', '4', 'P-1004', 'Feature Phone', 'Kivo', 'Kivo K2 Dual SIM', 'Kivo K2 dual SIM feature phone', '', '6 Months', 'Main Store', 'Pcs', 'not_available', '0', '0', '', '', '', '', '2.000', '0.000', '1650.00', '1280.00', '0.00', '3300.00', '1'),
('19', '9', '8', 'P-3002', 'Charger & Cable', 'Lumo', 'Lumo USB-C Cable 1m', 'Lumo USB-C to USB-C cable, 1 metre', '', 'No Warranty', 'Main Store', 'Pcs', 'not_available', '0', '0', '', '', '', '', '3.000', '0.000', '350.00', '190.00', '0.00', '1050.00', '2'),
('20', '10', '7', 'P-3001', 'Charger & Cable', 'Lumo', 'Lumo 25W Charger', 'Lumo 25W USB-C fast charger', '', '6 Months', '', 'Pcs', 'not_available', '0', '0', '', '', '', '', '2.000', '0.000', '950.00', '0.00', '0.00', '1900.00', '1'),
('21', '10', '8', 'P-3002', 'Charger & Cable', 'Lumo', 'Lumo USB-C Cable 1m', 'Lumo USB-C to USB-C cable, 1 metre', '', 'No Warranty', '', 'Pcs', 'not_available', '0', '0', '', '', '', '', '2.000', '0.000', '350.00', '0.00', '0.00', '700.00', '2');

INSERT INTO `sales_invoice_payments` (`payment_id`, `invoice_id`, `payment_method`, `amount`, `reference`, `received_by`) VALUES
('1', '1', 'cash', '50000.00', 'Cash at sale', @sample_user),
('2', '2', 'cash', '15450.00', 'Cash at sale', @sample_user),
('3', '3', 'cash', '30000.00', 'Cash at sale', @sample_user),
('4', '5', 'cash', '40000.00', 'Cash at sale', @sample_user),
('5', '6', 'cash', '20950.00', 'Cash at sale', @sample_user),
('6', '7', 'cash', '25000.00', 'Cash at sale', @sample_user),
('7', '8', 'cash', '20000.00', 'Cash at sale', @sample_user),
('8', '9', 'cash', '4350.00', 'Cash at sale', @sample_user),
('9', '10', 'cash', '2600.00', '', @sample_user);

INSERT INTO `sales_invoices` (`invoice_id`, `invoice_no`, `branch_id`, `ref_no`, `sales_date`, `bill_type`, `customer_id`, `member_id`, `customer_group`, `customer_name`, `mobile`, `attention`, `address`, `delivery_point`, `create_installation`, `bin`, `nid`, `sales_person`, `sr_id`, `narration`, `additional_info`, `subtotal`, `extra_charges`, `discount_amount`, `vat_amount`, `ait_amount`, `exchange_amount`, `point_adjust`, `points_redeemed`, `points_earned`, `net_amount`, `cash_received`, `balance_amount`, `total_quantity`, `status`, `created_by`, `approval_status`, `approved_by`, `approved_at`, `stock_doc_id`) VALUES
('1', 'SI2026000001', '1', '', '2026-09-08', 'Credit', '3', NULL, 'Corporate', 'Greenfield School', '01000000301', '', '1 School Road, Uttara, Dhaka', '', '0', '', '', 'Arif Hasan', '1', '', NULL, '146600.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '146600.00', '50000.00', '96600.00', '4.000', 'posted', @sample_user, 'approved', @sample_user, '2026-11-06 20:40:48', '9'),
('2', 'SI2026000002', '1', '', '2026-09-10', 'Cash', NULL, NULL, 'Cash Customer', 'Walk-in Customer', '01000000991', '', '', '', '0', '', '', '', NULL, '', NULL, '15450.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '15450.00', '15450.00', '0.00', '2.000', 'posted', @sample_user, 'approved', @sample_user, '2026-11-06 20:40:48', '10'),
('3', 'SI2026000003', '1', '', '2026-09-15', 'Credit', '1', NULL, 'Dhaka Dealers', 'City Gadget Point', '01000000201', '', 'Shop 12, Sample Plaza, Dhanmondi, Dhaka', '', '0', '', '', 'Nadia Karim', '2', '', NULL, '91400.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '91400.00', '30000.00', '58300.00', '35.000', 'posted', @sample_user, 'approved', @sample_user, '2026-11-06 20:40:48', '11'),
('4', 'SI2026000004', '1', '', '2026-09-24', 'Credit', '4', NULL, 'Corporate', 'Sunrise Pharma Ltd.', '01000000302', '', '77 Industrial Area, Gazipur', '', '0', '', '', 'Nadia Karim', '2', '', NULL, '156900.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '156900.00', '0.00', '156900.00', '3.000', 'posted', @sample_user, 'approved', @sample_user, '2026-11-06 20:40:48', '12'),
('5', 'SI2026000005', '1', '', '2026-10-03', 'Credit', '5', NULL, 'Corporate', 'Orchid Hotel & Resort', '01000000303', '', 'Beach Road, Cox\'s Bazar', '', '0', '', '', 'Arif Hasan', '1', '', NULL, '93600.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '93600.00', '40000.00', '53600.00', '7.000', 'posted', @sample_user, 'approved', @sample_user, '2026-11-06 20:40:48', '13'),
('6', 'SI2026000006', '1', '', '2026-10-12', 'Cash', NULL, NULL, 'Cash Customer', 'Walk-in Customer', '01000000995', '', '', '', '0', '', '', '', NULL, '', NULL, '20950.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '20950.00', '20950.00', '0.00', '2.000', 'posted', @sample_user, 'approved', @sample_user, '2026-11-06 20:40:48', '14'),
('7', 'SI2026000007', '1', '', '2026-10-20', 'Credit', '2', NULL, 'Chattogram Dealers', 'Riverside Electronics', '01000000202', '', '31 River View Road, Chattogram', '', '0', '', '', 'Tanvir Ahmed', '3', '', NULL, '77700.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '77700.00', '25000.00', '52700.00', '8.000', 'posted', @sample_user, 'approved', @sample_user, '2026-11-06 20:40:48', '15'),
('8', 'SI2026000008', '1', '', '2026-11-02', 'Credit', '6', NULL, 'Retail Credit', 'Lakeview Clinic', '01000000304', '', '9 Lake Road, Gulshan, Dhaka', '', '0', '', '', 'Arif Hasan', '1', '', NULL, '72900.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '72900.00', '20000.00', '52900.00', '3.000', 'posted', @sample_user, 'approved', @sample_user, '2026-11-06 20:40:48', '16'),
('9', 'SI2026000009', '1', '', '2026-11-05', 'Cash', NULL, NULL, 'Cash Customer', 'Walk-in Customer', '01000000998', '', '', '', '0', '', '', '', NULL, '', NULL, '4350.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '4350.00', '4350.00', '0.00', '5.000', 'posted', @sample_user, 'pending', NULL, NULL, '17'),
('10', 'SI2026000010', '1', 'POS', '2026-11-06', 'Cash', NULL, NULL, '', 'Walk-in Customer', '', '', '', '', '0', '', '', 'admin', NULL, 'POS sale. Tendered 2,600.00, change 0.00.', NULL, '2600.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '2600.00', '2600.00', '0.00', '4.000', 'posted', @sample_user, 'pending', NULL, NULL, '21');

INSERT INTO `sales_order_items` (`item_id`, `order_id`, `product_id`, `product_code`, `product_group`, `brand`, `model_caption`, `description`, `store_name`, `unit_name`, `warranty`, `quantity`, `unit_price`, `discount_amount`, `line_total`, `line_number`) VALUES
('1', '1', '6', 'P-2002', 'Laptop', 'Zentro', 'Zentro Air 15', 'Zentro Air 15 laptop, Ryzen 5, 16GB, 512GB SSD', 'Main Store', 'Pcs', '2 Years', '2.000', '79900.00', '0.00', '159800.00', '1'),
('2', '1', '13', 'P-5001', 'Router', 'Zentro', 'Zentro AX1800', 'Zentro AX1800 dual-band Wi-Fi 6 router', 'Main Store', 'Pcs', '1 Year', '1.000', '4800.00', '0.00', '4800.00', '2');

INSERT INTO `sales_orders` (`order_id`, `order_no`, `branch_id`, `ref_no`, `order_date`, `delivery_date`, `bill_type`, `customer_id`, `customer_group`, `customer_name`, `mobile`, `attention`, `narration`, `additional_info`, `address`, `bin`, `nid`, `sales_person`, `sr_id`, `delivery_point`, `create_installation`, `subtotal`, `extra_charges`, `discount_amount`, `vat_ait_amount`, `net_amount`, `advance_received`, `balance_amount`, `total_quantity`, `status`, `created_by`, `approval_status`, `approved_by`, `approved_at`, `portal_account_id`) VALUES
('1', 'SO2026000001', '1', 'GS-PO-118', '2026-10-28', '2026-11-15', 'Credit', '3', 'Corporate', 'Greenfield School', '01000000301', 'Admin Office', 'Computer lab upgrade', NULL, '1 School Road, Uttara, Dhaka', '', '', 'Arif Hasan', '1', 'School campus', '0', '164600.00', '0.00', '0.00', '0.00', '164600.00', '0.00', '164600.00', '3.000', 'open', @sample_user, 'pending', NULL, NULL, NULL);

INSERT INTO `sales_products` (`product_id`, `product_code`, `category_id`, `product_group`, `brand`, `model_caption`, `description`, `unit_name`, `warranty`, `default_unit_price`, `purchase_price`, `reorder_level`, `vat_percent`, `serial_required`, `is_active`) VALUES
('1', 'P-1001', '1', 'Smartphone', 'Nova', 'Nova X5 4/64', 'Nova X5 smartphone 4GB/64GB', 'Pcs', '1 Year', '14500.00', '12200.00', '5.000', '0.00', '0', '1'),
('2', 'P-1002', '1', 'Smartphone', 'Nova', 'Nova X7 8/128', 'Nova X7 smartphone 8GB/128GB', 'Pcs', '1 Year', '22900.00', '19800.00', '3.000', '0.00', '0', '1'),
('3', 'P-1003', '1', 'Smartphone', 'Zentro', 'Zentro Z10 6/128', 'Zentro Z10 smartphone 6GB/128GB', 'Pcs', '1 Year', '18500.00', '15900.00', '3.000', '0.00', '0', '1'),
('4', 'P-1004', '1', 'Feature Phone', 'Kivo', 'Kivo K2 Dual SIM', 'Kivo K2 dual SIM feature phone', 'Pcs', '6 Months', '1650.00', '1280.00', '10.000', '0.00', '0', '1'),
('5', 'P-2001', '2', 'Laptop', 'Orbit', 'Orbit Book 14', 'Orbit Book 14 laptop, Core i5, 8GB, 512GB SSD', 'Pcs', '2 Years', '68500.00', '61000.00', '2.000', '0.00', '0', '1'),
('6', 'P-2002', '2', 'Laptop', 'Zentro', 'Zentro Air 15', 'Zentro Air 15 laptop, Ryzen 5, 16GB, 512GB SSD', 'Pcs', '2 Years', '79900.00', '71500.00', '2.000', '0.00', '0', '1'),
('7', 'P-3001', '3', 'Charger & Cable', 'Lumo', 'Lumo 25W Charger', 'Lumo 25W USB-C fast charger', 'Pcs', '6 Months', '950.00', '620.00', '20.000', '0.00', '0', '1'),
('8', 'P-3002', '3', 'Charger & Cable', 'Lumo', 'Lumo USB-C Cable 1m', 'Lumo USB-C to USB-C cable, 1 metre', 'Pcs', 'No Warranty', '350.00', '190.00', '30.000', '0.00', '0', '1'),
('9', 'P-3003', '3', 'Headphone', 'Kivo', 'Kivo Buds Pro', 'Kivo Buds Pro wireless earbuds', 'Pcs', '6 Months', '2450.00', '1750.00', '10.000', '0.00', '0', '1'),
('10', 'P-3004', '3', 'Power Bank', 'Lumo', 'Lumo 20000mAh', 'Lumo 20000mAh power bank', 'Pcs', '6 Months', '2200.00', '1600.00', '10.000', '0.00', '0', '1'),
('11', 'P-4001', '4', 'LED TV', 'Orbit', 'Orbit 43 Smart', 'Orbit 43 inch smart LED TV', 'Pcs', '2 Years', '38500.00', '33500.00', '2.000', '0.00', '0', '1'),
('12', 'P-4002', '4', 'LED TV', 'Orbit', 'Orbit 32 LED', 'Orbit 32 inch LED TV', 'Pcs', '1 Year', '21500.00', '18200.00', '2.000', '0.00', '0', '1'),
('13', 'P-5001', '5', 'Router', 'Zentro', 'Zentro AX1800', 'Zentro AX1800 dual-band Wi-Fi 6 router', 'Pcs', '1 Year', '4800.00', '3900.00', '5.000', '0.00', '0', '1'),
('14', 'P-5002', '5', 'Router', 'Kivo', 'Kivo 4G Pocket', 'Kivo 4G pocket router', 'Pcs', '6 Months', '3200.00', '2550.00', '5.000', '0.00', '0', '1');

INSERT INTO `sales_representatives` (`sr_id`, `sr_code`, `sr_name`, `mobile`, `email`, `address`, `designation`, `territory_id`, `joining_date`, `commission_basis`, `commission_rate`, `target_bonus_rate`, `is_active`, `branch_id`, `staff_only`) VALUES
('1', 'SR-01', 'Arif Hasan', '01000000401', 'arif@example.com', '', 'Senior Sales Officer', '1', '2026-01-15', 'net_sales', '1.50', '0.50', '1', NULL, '0'),
('2', 'SR-02', 'Nadia Karim', '01000000402', 'nadia@example.com', '', 'Sales Officer', '2', '2026-01-15', 'collection', '1.00', '0.50', '1', NULL, '0'),
('3', 'SR-03', 'Tanvir Ahmed', '01000000403', 'tanvir@example.com', '', 'Sales Officer', '3', '2026-01-15', 'net_sales', '1.00', '0.50', '1', NULL, '0');

INSERT INTO `sales_return_items` (`return_item_id`, `return_id`, `invoice_item_id`, `product_id`, `product_code`, `description`, `store_name`, `unit_name`, `serial_imei`, `item_condition`, `quantity`, `free_quantity`, `unit_value`, `line_total`) VALUES
('1', '1', '6', '4', 'P-1004', 'Kivo K2 dual SIM feature phone', 'Main Store', 'Pcs', '', 'good', '2.000', '0.000', '1550.0000', '3100.00');

INSERT INTO `sales_returns` (`return_id`, `return_no`, `invoice_id`, `return_date`, `customer_id`, `customer_name`, `mobile`, `reason`, `items_amount`, `deduction_amount`, `return_amount`, `due_adjusted`, `refund_amount`, `refund_method`, `refund_reference`, `total_quantity`, `status`, `created_by`, `approval_status`, `approved_by`, `approved_at`, `stock_doc_id`) VALUES
('1', 'SR2026000001', '3', '2026-09-20', '1', 'City Gadget Point', '01000000201', '', '3100.00', '0.00', '3100.00', '3100.00', '0.00', 'cash', '', '2.000', 'posted', @sample_user, 'approved', @sample_user, '2026-11-06 20:40:48', '18');

INSERT IGNORE INTO `sales_stores` (`store_name`) VALUES ('Main Store'), ('Showroom Store');

INSERT INTO `sr_targets` (`target_id`, `sr_id`, `target_month`, `sales_target`, `collection_target`, `note`, `updated_by`) VALUES
('1', '1', '2026-10-01', '600000.00', '400000.00', 'Corporate accounts', @sample_user),
('2', '2', '2026-10-01', '450000.00', '300000.00', 'Dealers, Dhaka South', @sample_user),
('3', '3', '2026-10-01', '350000.00', '250000.00', 'Chattogram', @sample_user),
('4', '1', '2026-11-01', '600000.00', '400000.00', 'Corporate accounts', @sample_user),
('5', '2', '2026-11-01', '450000.00', '300000.00', 'Dealers, Dhaka South', @sample_user),
('6', '3', '2026-11-01', '350000.00', '250000.00', 'Chattogram', @sample_user);

INSERT INTO `sr_territories` (`territory_id`, `territory_code`, `territory_name`, `region`, `parent_id`, `description`, `is_active`) VALUES
('1', 'DHK-N', 'Dhaka North', 'Dhaka', NULL, '', '1'),
('2', 'DHK-S', 'Dhaka South', 'Dhaka', NULL, '', '1'),
('3', 'CTG', 'Chattogram City', 'Chattogram', NULL, '', '1');

INSERT INTO `trader_groups` (`group_id`, `category_id`, `group_name`, `is_active`, `created_by`) VALUES
('1', '1', 'Local Suppliers', '1', @sample_user),
('2', '1', 'Importers', '1', @sample_user),
('3', '2', 'Dhaka Dealers', '1', @sample_user),
('4', '2', 'Chattogram Dealers', '1', @sample_user),
('5', '3', 'Corporate', '1', @sample_user),
('6', '3', 'Retail Credit', '1', @sample_user);

INSERT INTO `traders` (`trader_id`, `trader_code`, `category_id`, `group_id`, `trader_name`, `document`, `referrer_name`, `address`, `mobile`, `secondary_mobile`, `phone`, `fax`, `email`, `web`, `contact_person`, `owner_name`, `opening_balance`, `security_type`, `bg_cheque_no`, `bin`, `nid`, `is_active`, `sales_customer_id`, `rma_supplier_id`, `created_by`) VALUES
('1', 'SUP000001', '1', '1', 'Delta Distribution Ltd.', '', '', '8 Warehouse Lane, Tejgaon, Dhaka', '01000000101', '', '', '', 'delta@example.com', '', 'Sales Desk', '', '0.00', '', '', '', '', '1', NULL, '1', @sample_user),
('2', 'SUP000002', '1', '1', 'Meridian Tech Supply', '', '', '22 Market Road, Motijheel, Dhaka', '01000000102', '', '', '', 'meridian@example.com', '', 'Accounts', '', '0.00', '', '', '', '', '1', NULL, '2', @sample_user),
('3', 'SUP000003', '1', '2', 'Blue Harbor Trading Co.', '', '', '5 Port Road, Agrabad, Chattogram', '01000000103', '', '', '', 'blue@example.com', '', 'Import Desk', '', '0.00', '', '', '', '', '1', NULL, '3', @sample_user),
('4', 'DLR000004', '2', '3', 'City Gadget Point', '', '', 'Shop 12, Sample Plaza, Dhanmondi, Dhaka', '01000000201', '', '', '', 'city@example.com', '', 'Store Manager', '', '0.00', '', '', '', '', '1', '1', NULL, @sample_user),
('5', 'DLR000005', '2', '4', 'Riverside Electronics', '', '', '31 River View Road, Chattogram', '01000000202', '', '', '', 'riverside@example.com', '', 'Owner', '', '0.00', '', '', '', '', '1', '2', NULL, @sample_user),
('6', 'CRC000006', '3', '5', 'Greenfield School', '', '', '1 School Road, Uttara, Dhaka', '01000000301', '', '', '', 'greenfield@example.com', '', 'Admin Office', '', '0.00', '', '', '', '', '1', '3', NULL, @sample_user),
('7', 'CRC000007', '3', '5', 'Sunrise Pharma Ltd.', '', '', '77 Industrial Area, Gazipur', '01000000302', '', '', '', 'sunrise@example.com', '', 'Procurement', '', '0.00', '', '', '', '', '1', '4', NULL, @sample_user),
('8', 'CRC000008', '3', '5', 'Orchid Hotel & Resort', '', '', 'Beach Road, Cox\'s Bazar', '01000000303', '', '', '', 'orchid@example.com', '', 'Purchase Dept.', '', '0.00', '', '', '', '', '1', '5', NULL, @sample_user),
('9', 'CRC000009', '3', '6', 'Lakeview Clinic', '', '', '9 Lake Road, Gulshan, Dhaka', '01000000304', '', '', '', 'lakeview@example.com', '', 'Manager', '', '0.00', '', '', '', '', '1', '6', NULL, @sample_user);

SET FOREIGN_KEY_CHECKS = 1;
