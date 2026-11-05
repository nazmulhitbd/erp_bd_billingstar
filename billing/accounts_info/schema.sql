-- Accounts Information module, MySQL 8.0+. Safe to run more than once.
-- Chart of accounts, sub ledgers, vouchers, settings, CC loan interest and over-due permissions.

CREATE TABLE IF NOT EXISTS acc_settings (
    setting_key VARCHAR(60) NOT NULL,
    setting_value VARCHAR(255) NOT NULL DEFAULT '',
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS acc_sequences (
    doc_code VARCHAR(10) NOT NULL,
    seq_year SMALLINT UNSIGNED NOT NULL,
    last_number BIGINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (doc_code, seq_year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- "Accounts Group Distribution": the group tree of the chart of accounts.
CREATE TABLE IF NOT EXISTS acc_groups (
    group_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    parent_id BIGINT UNSIGNED NULL,
    group_code VARCHAR(20) NOT NULL DEFAULT '',
    group_name VARCHAR(120) NOT NULL,
    nature ENUM('asset','liability','equity','income','expense') NOT NULL,
    sort_order SMALLINT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (group_id),
    UNIQUE KEY uq_acc_groups_name (parent_id, group_name),
    KEY ix_acc_groups_parent (parent_id),
    CONSTRAINT fk_acc_group_parent FOREIGN KEY (parent_id) REFERENCES acc_groups (group_id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- "General Accounts": ledger accounts. Control accounts (has_sub_ledger=1) carry party sub ledgers.
CREATE TABLE IF NOT EXISTS acc_accounts (
    account_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    group_id BIGINT UNSIGNED NOT NULL,
    account_code VARCHAR(20) NOT NULL,
    account_name VARCHAR(150) NOT NULL,
    account_type ENUM('general','cash','bank','receivable','payable','loan') NOT NULL DEFAULT 'general',
    has_sub_ledger TINYINT(1) NOT NULL DEFAULT 0,
    bank_name VARCHAR(120) NOT NULL DEFAULT '',
    bank_account_no VARCHAR(60) NOT NULL DEFAULT '',
    opening_balance DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    opening_side ENUM('Dr','Cr') NOT NULL DEFAULT 'Dr',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (account_id),
    UNIQUE KEY uq_acc_accounts_code (account_code),
    UNIQUE KEY uq_acc_accounts_name (account_name),
    KEY ix_acc_accounts_group (group_id),
    CONSTRAINT fk_acc_account_group FOREIGN KEY (group_id) REFERENCES acc_groups (group_id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- "Accounts Sub Ledger Group": area or branch groups of parties under a control account.
CREATE TABLE IF NOT EXISTS acc_sub_ledger_groups (
    slg_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    account_id BIGINT UNSIGNED NOT NULL,
    group_name VARCHAR(120) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (slg_id),
    UNIQUE KEY uq_acc_slg_name (account_id, group_name),
    CONSTRAINT fk_acc_slg_account FOREIGN KEY (account_id) REFERENCES acc_accounts (account_id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- "Accounts Sub Ledger": one row per party (customer, supplier, employee ...).
CREATE TABLE IF NOT EXISTS acc_sub_ledgers (
    sub_ledger_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    slg_id BIGINT UNSIGNED NOT NULL,
    account_id BIGINT UNSIGNED NOT NULL,
    sub_ledger_code VARCHAR(30) NOT NULL,
    sub_ledger_name VARCHAR(190) NOT NULL,
    address VARCHAR(255) NOT NULL DEFAULT '',
    mobile VARCHAR(40) NOT NULL DEFAULT '',
    opening_balance DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    opening_side ENUM('Dr','Cr') NOT NULL DEFAULT 'Dr',
    trader_id BIGINT UNSIGNED NULL COMMENT 'Link to traders.trader_id when imported',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (sub_ledger_id),
    UNIQUE KEY uq_acc_sub_ledgers_code (sub_ledger_code),
    UNIQUE KEY uq_acc_sub_ledgers_trader (trader_id),
    KEY ix_acc_sub_ledgers_account (account_id),
    KEY ix_acc_sub_ledgers_name (sub_ledger_name),
    CONSTRAINT fk_acc_sl_group FOREIGN KEY (slg_id) REFERENCES acc_sub_ledger_groups (slg_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_acc_sl_account FOREIGN KEY (account_id) REFERENCES acc_accounts (account_id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- "MoneyReceiptCategory": receipt types used on credit (receipt) vouchers.
CREATE TABLE IF NOT EXISTS acc_money_receipt_categories (
    category_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    category_name VARCHAR(120) NOT NULL,
    account_id BIGINT UNSIGNED NULL COMMENT 'Default account credited',
    note VARCHAR(255) NOT NULL DEFAULT '',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (category_id),
    UNIQUE KEY uq_acc_mrc_name (category_name),
    CONSTRAINT fk_acc_mrc_account FOREIGN KEY (account_id) REFERENCES acc_accounts (account_id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Vouchers. Only status='posted' vouchers count in the reports.
CREATE TABLE IF NOT EXISTS acc_vouchers (
    voucher_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    voucher_no VARCHAR(40) NOT NULL,
    voucher_type ENUM('debit','credit','journal','contra') NOT NULL,
    voucher_date DATE NOT NULL,
    ref_no VARCHAR(80) NOT NULL DEFAULT '',
    narration VARCHAR(500) NOT NULL DEFAULT '',
    sales_person VARCHAR(140) NOT NULL DEFAULT '',
    transaction_mode ENUM('Cash','Cheque','Bank Transfer','Mobile Banking','Adjustment') NOT NULL DEFAULT 'Cash',
    cheque_no VARCHAR(40) NOT NULL DEFAULT '',
    cheque_date DATE NULL,
    receipt_category_id BIGINT UNSIGNED NULL,
    total_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    status ENUM('draft','posted','cancelled') NOT NULL DEFAULT 'draft',
    source VARCHAR(30) NOT NULL DEFAULT 'manual',
    branch_id INT UNSIGNED NULL,
    to_branch_id INT UNSIGNED NULL,
    is_inter_branch TINYINT(1) NOT NULL DEFAULT 0,
    created_by BIGINT UNSIGNED NULL,
    posted_by BIGINT UNSIGNED NULL,
    posted_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (voucher_id),
    UNIQUE KEY uq_acc_vouchers_no (voucher_no),
    KEY ix_acc_vouchers_date (voucher_date),
    KEY ix_acc_vouchers_status (status, voucher_type),
    KEY ix_acc_vouchers_status_date (status, voucher_date),
    CONSTRAINT fk_acc_voucher_mrc FOREIGN KEY (receipt_category_id) REFERENCES acc_money_receipt_categories (category_id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS acc_voucher_lines (
    line_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    voucher_id BIGINT UNSIGNED NOT NULL,
    account_id BIGINT UNSIGNED NOT NULL,
    sub_ledger_id BIGINT UNSIGNED NULL,
    debit DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    credit DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    line_narration VARCHAR(255) NOT NULL DEFAULT '',
    line_no SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    -- Copies of the voucher's status and dates, kept in step by acc_sync_lines() in acc_common.php,
    -- so report totals read one covering index instead of joining every line to its voucher.
    is_posted TINYINT(1) NOT NULL DEFAULT 0,
    voucher_date DATE NULL,
    basis_date DATE NULL COMMENT 'Line cheque date, else voucher cheque date, else voucher date',
    branch_id INT UNSIGNED NULL,
    transaction_mode VARCHAR(20) NOT NULL DEFAULT '',
    cheque_no VARCHAR(40) NOT NULL DEFAULT '',
    cheque_date DATE NULL,
    invoice_no VARCHAR(40) NOT NULL DEFAULT '',
    PRIMARY KEY (line_id),
    KEY ix_acc_lines_voucher (voucher_id),
    KEY ix_acc_lines_account (account_id),
    KEY ix_acc_lines_sub_ledger (sub_ledger_id),
    KEY ix_acc_l_post_acct (is_posted, account_id, voucher_date, debit, credit),
    KEY ix_acc_l_post_sub (is_posted, sub_ledger_id, voucher_date, debit, credit),
    KEY ix_acc_l_post_acct_b (is_posted, account_id, basis_date, debit, credit),
    KEY ix_acc_l_post_sub_b (is_posted, sub_ledger_id, basis_date, debit, credit),
    CONSTRAINT fk_acc_line_voucher FOREIGN KEY (voucher_id) REFERENCES acc_vouchers (voucher_id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_acc_line_account FOREIGN KEY (account_id) REFERENCES acc_accounts (account_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_acc_line_sub_ledger FOREIGN KEY (sub_ledger_id) REFERENCES acc_sub_ledgers (sub_ledger_id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- "Document Attach" on the voucher screen.
CREATE TABLE IF NOT EXISTS acc_voucher_attachments (
    attachment_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    voucher_id BIGINT UNSIGNED NOT NULL,
    original_name VARCHAR(190) NOT NULL,
    stored_name VARCHAR(80) NOT NULL,
    mime_type VARCHAR(100) NOT NULL DEFAULT 'application/octet-stream',
    file_size INT UNSIGNED NOT NULL DEFAULT 0,
    uploaded_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (attachment_id),
    UNIQUE KEY uq_acc_voucher_attachments_stored (stored_name),
    KEY ix_acc_voucher_attachments_voucher (voucher_id),
    CONSTRAINT fk_acc_voucher_attachment FOREIGN KEY (voucher_id) REFERENCES acc_vouchers (voucher_id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- "CC Loan Interest": annual rate history per cash-credit loan account.
CREATE TABLE IF NOT EXISTS acc_cc_loan_rates (
    rate_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    account_id BIGINT UNSIGNED NOT NULL,
    annual_rate DECIMAL(6,3) NOT NULL,
    effective_from DATE NOT NULL,
    note VARCHAR(255) NOT NULL DEFAULT '',
    PRIMARY KEY (rate_id),
    UNIQUE KEY uq_acc_cc_rate (account_id, effective_from),
    CONSTRAINT fk_acc_cc_rate_account FOREIGN KEY (account_id) REFERENCES acc_accounts (account_id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Each posted interest calculation, so the same days are never charged twice.
CREATE TABLE IF NOT EXISTS acc_cc_interest_runs (
    run_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    account_id BIGINT UNSIGNED NOT NULL,
    period_from DATE NOT NULL,
    period_to DATE NOT NULL,
    day_basis SMALLINT UNSIGNED NOT NULL DEFAULT 365,
    interest_amount DECIMAL(15,2) NOT NULL,
    voucher_id BIGINT UNSIGNED NOT NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (run_id),
    KEY ix_acc_cc_runs_account (account_id, period_from, period_to),
    CONSTRAINT fk_acc_cc_run_account FOREIGN KEY (account_id) REFERENCES acc_accounts (account_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_acc_cc_run_voucher FOREIGN KEY (voucher_id) REFERENCES acc_vouchers (voucher_id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- "Over Due Permission": lets a party stay over its due or credit limit until a date.
CREATE TABLE IF NOT EXISTS acc_overdue_permissions (
    permission_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    sub_ledger_id BIGINT UNSIGNED NOT NULL,
    permitted_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    valid_from DATE NOT NULL,
    valid_to DATE NOT NULL,
    reason VARCHAR(255) NOT NULL DEFAULT '',
    approved_by BIGINT UNSIGNED NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (permission_id),
    KEY ix_acc_odp_sub_ledger (sub_ledger_id, valid_to),
    CONSTRAINT fk_acc_odp_sub_ledger FOREIGN KEY (sub_ledger_id) REFERENCES acc_sub_ledgers (sub_ledger_id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Branches, shared with vsc/inventory. Inter branch vouchers move amounts between two branches.
CREATE TABLE IF NOT EXISTS branches (
    branch_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    branch_name VARCHAR(120) NOT NULL,
    address VARCHAR(255) NOT NULL DEFAULT '',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (branch_id),
    UNIQUE KEY uq_branches_name (branch_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT IGNORE INTO branches (branch_id, branch_name) VALUES (1, 'Head Office');

-- "Cheque Book": leaves of a bank account's cheque book. A book must be approved before its leaves are used.
CREATE TABLE IF NOT EXISTS acc_cheque_books (
    book_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    account_id BIGINT UNSIGNED NOT NULL,
    book_no VARCHAR(40) NOT NULL,
    leaf_prefix VARCHAR(20) NOT NULL DEFAULT '',
    leaf_from BIGINT UNSIGNED NOT NULL,
    leaf_to BIGINT UNSIGNED NOT NULL,
    leaf_digits TINYINT UNSIGNED NOT NULL DEFAULT 0,
    issue_date DATE NOT NULL,
    note VARCHAR(255) NOT NULL DEFAULT '',
    approval_status ENUM('pending','approved') NOT NULL DEFAULT 'pending',
    approved_by BIGINT UNSIGNED NULL,
    approved_at DATETIME NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (book_id),
    UNIQUE KEY uq_acc_cheque_books (account_id, book_no),
    CONSTRAINT fk_acc_cheque_book_account FOREIGN KEY (account_id) REFERENCES acc_accounts (account_id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS acc_cheque_leaves (
    leaf_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    book_id BIGINT UNSIGNED NOT NULL,
    account_id BIGINT UNSIGNED NOT NULL,
    leaf_no VARCHAR(40) NOT NULL,
    status ENUM('unused','used','cancelled') NOT NULL DEFAULT 'unused',
    voucher_id BIGINT UNSIGNED NULL,
    PRIMARY KEY (leaf_id),
    UNIQUE KEY uq_acc_cheque_leaf (account_id, leaf_no),
    KEY ix_acc_cheque_leaves_book (book_id),
    CONSTRAINT fk_acc_cheque_leaf_book FOREIGN KEY (book_id) REFERENCES acc_cheque_books (book_id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Cheque register: every cheque received or issued on a voucher, and what happened to it ("Cheque Treatment").
CREATE TABLE IF NOT EXISTS acc_cheques (
    cheque_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    direction ENUM('received','issued') NOT NULL,
    voucher_id BIGINT UNSIGNED NOT NULL,
    line_id BIGINT UNSIGNED NULL,
    bank_account_id BIGINT UNSIGNED NOT NULL,
    party_account_id BIGINT UNSIGNED NOT NULL,
    party_sub_ledger_id BIGINT UNSIGNED NULL,
    party_name VARCHAR(190) NOT NULL DEFAULT '',
    cheque_no VARCHAR(40) NOT NULL,
    cheque_date DATE NULL,
    amount DECIMAL(15,2) NOT NULL,
    status ENUM('pending','honoured','dishonoured','cancelled') NOT NULL DEFAULT 'pending',
    status_date DATE NULL,
    reversal_voucher_id BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (cheque_id),
    KEY ix_acc_cheques_no (cheque_no),
    KEY ix_acc_cheques_status (status, cheque_date),
    KEY ix_acc_cheques_voucher (voucher_id),
    CONSTRAINT fk_acc_cheque_voucher FOREIGN KEY (voucher_id) REFERENCES acc_vouchers (voucher_id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS acc_cheque_events (
    event_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    cheque_id BIGINT UNSIGNED NOT NULL,
    event_date DATE NOT NULL,
    status VARCHAR(20) NOT NULL,
    note VARCHAR(255) NOT NULL DEFAULT '',
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (event_id),
    KEY ix_acc_cheque_events (cheque_id),
    CONSTRAINT fk_acc_cheque_event FOREIGN KEY (cheque_id) REFERENCES acc_cheques (cheque_id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Upgrade an existing install (safe to run again) ----------
-- Adds the report columns and indexes to databases created before they existed, then fills them.
SET @db := DATABASE();

SET @q := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='acc_voucher_lines' AND column_name='is_posted') = 0,
    'ALTER TABLE acc_voucher_lines ADD COLUMN is_posted TINYINT(1) NOT NULL DEFAULT 0, ADD COLUMN voucher_date DATE NULL, ADD COLUMN basis_date DATE NULL',
    'DO 0');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

SET @q := IF((SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=@db AND table_name='acc_voucher_lines' AND index_name='ix_acc_l_post_acct') = 0,
    'ALTER TABLE acc_voucher_lines ADD KEY ix_acc_l_post_acct (is_posted, account_id, voucher_date, debit, credit), ADD KEY ix_acc_l_post_sub (is_posted, sub_ledger_id, voucher_date, debit, credit), ADD KEY ix_acc_l_post_acct_b (is_posted, account_id, basis_date, debit, credit), ADD KEY ix_acc_l_post_sub_b (is_posted, sub_ledger_id, basis_date, debit, credit)',
    'DO 0');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

SET @q := IF((SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=@db AND table_name='acc_vouchers' AND index_name='ix_acc_vouchers_status_date') = 0,
    'ALTER TABLE acc_vouchers ADD KEY ix_acc_vouchers_status_date (status, voucher_date)',
    'DO 0');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

SET @q := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='acc_voucher_lines' AND column_name='branch_id') = 0,
    "ALTER TABLE acc_voucher_lines ADD COLUMN branch_id INT UNSIGNED NULL, ADD COLUMN transaction_mode VARCHAR(20) NOT NULL DEFAULT '', ADD COLUMN cheque_no VARCHAR(40) NOT NULL DEFAULT '', ADD COLUMN cheque_date DATE NULL, ADD COLUMN invoice_no VARCHAR(40) NOT NULL DEFAULT ''",
    'DO 0');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

SET @q := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='acc_vouchers' AND column_name='is_inter_branch') = 0,
    'ALTER TABLE acc_vouchers ADD COLUMN branch_id INT UNSIGNED NULL, ADD COLUMN to_branch_id INT UNSIGNED NULL, ADD COLUMN is_inter_branch TINYINT(1) NOT NULL DEFAULT 0',
    'DO 0');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

-- Fill or repair the copied columns for every line.
UPDATE acc_voucher_lines l JOIN acc_vouchers v ON v.voucher_id = l.voucher_id
SET l.is_posted = (v.status = 'posted'), l.voucher_date = v.voucher_date, l.basis_date = COALESCE(l.cheque_date, v.cheque_date, v.voucher_date)
WHERE l.is_posted <> (v.status = 'posted') OR NOT (l.voucher_date <=> v.voucher_date) OR NOT (l.basis_date <=> COALESCE(l.cheque_date, v.cheque_date, v.voucher_date));

-- ---------- Starter chart of accounts (skipped when the ids already exist) ----------
INSERT IGNORE INTO acc_groups (group_id, parent_id, group_code, group_name, nature, sort_order) VALUES
    (1, NULL, '1', 'Assets', 'asset', 1),
    (7, NULL, '2', 'Liabilities', 'liability', 2),
    (11, NULL, '3', 'Equity', 'equity', 3),
    (12, NULL, '4', 'Income', 'income', 4),
    (15, NULL, '5', 'Expenses', 'expense', 5);
INSERT IGNORE INTO acc_groups (group_id, parent_id, group_code, group_name, nature, sort_order) VALUES
    (2, 1, '11', 'Current Assets', 'asset', 1),
    (6, 1, '12', 'Fixed Assets', 'asset', 2),
    (8, 7, '21', 'Current Liabilities', 'liability', 1),
    (10, 7, '22', 'Loans', 'liability', 2),
    (13, 12, '41', 'Sales Income', 'income', 1),
    (14, 12, '42', 'General Income', 'income', 2),
    (16, 15, '51', 'Cost of Sales', 'expense', 1),
    (17, 15, '52', 'General Expenses', 'expense', 2),
    (18, 15, '53', 'Finance Cost', 'expense', 3);
INSERT IGNORE INTO acc_groups (group_id, parent_id, group_code, group_name, nature, sort_order) VALUES
    (3, 2, '111', 'Cash & Bank', 'asset', 1),
    (4, 2, '112', 'Accounts Receivable', 'asset', 2),
    (5, 2, '113', 'Inventory', 'asset', 3),
    (9, 8, '211', 'Accounts Payable', 'liability', 1);

INSERT IGNORE INTO acc_accounts (account_id, group_id, account_code, account_name, account_type, has_sub_ledger) VALUES
    (1, 3, '1001', 'Cash In Hand', 'cash', 0),
    (2, 3, '1002', 'Main Bank Account', 'bank', 0),
    (3, 4, '1101', 'Sundry Debtors', 'receivable', 1),
    (4, 5, '1201', 'Inventory', 'general', 0),
    (5, 9, '2001', 'Sundry Creditors', 'payable', 1),
    (6, 10, '2101', 'CC Loan', 'loan', 0),
    (7, 11, '3001', 'Capital', 'general', 0),
    (8, 13, '4001', 'Sales', 'general', 0),
    (9, 14, '4101', 'Other Income', 'general', 0),
    (10, 16, '5001', 'Purchase', 'general', 0),
    (11, 17, '5101', 'Office Expenses', 'general', 0),
    (12, 17, '5102', 'Salary', 'general', 0),
    (13, 18, '5201', 'CC Loan Interest', 'general', 0);

INSERT IGNORE INTO acc_sub_ledger_groups (slg_id, account_id, group_name) VALUES
    (1, 3, 'General Customers'),
    (2, 5, 'General Suppliers');

INSERT IGNORE INTO acc_money_receipt_categories (category_id, category_name, account_id) VALUES
    (1, 'Customer Collection', 3),
    (2, 'Other Income', 9);

INSERT IGNORE INTO acc_settings (setting_key, setting_value) VALUES
    ('company_name', 'DEMO ERP'),
    ('company_address', 'DHAKA-1205'),
    ('company_phone', ''),
    ('company_email', ''),
    ('voucher_prefix', 'DES'),
    ('cash_account', '1'),
    ('receivable_account', '3'),
    ('payable_account', '5'),
    ('cc_interest_account', '13'),
    ('cc_day_basis', '365'),
    ('require_posting', '1');

-- BIN printed on POS receipts (Company & Login Settings), and two-step sign-in per group (Settings > User Config):
-- off, sms, email or both, read by addon/inc/two_step.php. Off until someone turns it on.
INSERT IGNORE INTO acc_settings (setting_key, setting_value) VALUES
    ('company_bin', ''),
    ('tfa_staff', 'off'),
    ('tfa_employee', 'off'),
    ('tfa_customer', 'off'),
    ('tfa_minutes', '5');
