-- Users, branches and form permissions (Settings > Users & Roles). MySQL 8.0+. Safe to run again.
-- Run after billing/inventory/schema.sql (branches).
--
-- admin_user keeps being the only login table for the admin shell (vsc/topmain.php). It gains an access level, a user
-- type and the Registration fields. SuperAdmin passes every check; everyone else is limited to the rows in
-- admin_user_permissions, which addon/inc/perm.php enforces on each page.
-- user_type customer / employee links a login to sales_customers.customer_id / sales_representatives.sr_id: those users
-- get the same shell around their own customer_portal / sr_portal pages.

SET @db := DATABASE();

-- Users that exist before this file runs were full administrators, so they stay SuperAdmin with every branch.
SET @first_run := (SELECT COUNT(*) = 0 FROM information_schema.columns WHERE table_schema=@db AND table_name='admin_user' AND column_name='access_level');

SET @q := IF(@first_run, "ALTER TABLE admin_user
    ADD COLUMN full_name VARCHAR(120) CHARACTER SET utf8mb4 NOT NULL DEFAULT '' AFTER user_name,
    ADD COLUMN mobile VARCHAR(30) NOT NULL DEFAULT '' AFTER email,
    ADD COLUMN access_level ENUM('SuperAdmin','Admin','Supervisor','Operator') NOT NULL DEFAULT 'Operator',
    ADD COLUMN user_type ENUM('staff','employee','customer') NOT NULL DEFAULT 'staff',
    ADD COLUMN linked_id BIGINT UNSIGNED NULL COMMENT 'employee: sales_representatives.sr_id, customer: sales_customers.customer_id',
    ADD COLUMN department VARCHAR(80) CHARACTER SET utf8mb4 NOT NULL DEFAULT '',
    ADD COLUMN date_of_birth DATE NULL,
    ADD COLUMN full_time TINYINT(1) NOT NULL DEFAULT 1 COMMENT '0: sign-in only between start_time and end_time',
    ADD COLUMN start_time TIME NULL,
    ADD COLUMN end_time TIME NULL,
    ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1,
    ADD COLUMN all_branches TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN default_branch_id INT UNSIGNED NULL,
    ADD COLUMN current_branch_id INT UNSIGNED NULL COMMENT 'Branch picked at sign-in or in the header',
    ADD KEY ix_admin_user_link (user_type, linked_id)", 'DO 0');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

SET @q := IF(@first_run, "UPDATE admin_user SET access_level='SuperAdmin', all_branches=1", 'DO 0');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

-- Sub-branches: a branch may sit under another; access to a branch includes the branches under it.
SET @q := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='branches' AND column_name='parent_branch_id') = 0,
    'ALTER TABLE branches ADD COLUMN parent_branch_id INT UNSIGNED NULL AFTER branch_name, ADD KEY ix_branches_parent (parent_branch_id), ADD CONSTRAINT fk_branch_parent FOREIGN KEY (parent_branch_id) REFERENCES branches (branch_id) ON UPDATE CASCADE ON DELETE SET NULL',
    'DO 0');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

-- Branches a user may work in (ignored when admin_user.all_branches = 1).
CREATE TABLE IF NOT EXISTS admin_user_branches (
    user_id INT NOT NULL,
    branch_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (user_id, branch_id),
    CONSTRAINT fk_user_branch_user FOREIGN KEY (user_id) REFERENCES admin_user (user_id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_user_branch_branch FOREIGN KEY (branch_id) REFERENCES branches (branch_id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One row per user, form and branch. branch_id 0 = all branches; a row for a branch replaces the all-branches row there.
-- form_key is the menu URL of the form in vsc/menu_catalog.php, e.g. sales/sales_invoice/sales_invoice.php.
CREATE TABLE IF NOT EXISTS admin_user_permissions (
    user_id INT NOT NULL,
    branch_id INT UNSIGNED NOT NULL DEFAULT 0,
    form_key VARCHAR(160) NOT NULL,
    can_view TINYINT(1) NOT NULL DEFAULT 0,
    can_add TINYINT(1) NOT NULL DEFAULT 0,
    can_edit TINYINT(1) NOT NULL DEFAULT 0,
    can_delete TINYINT(1) NOT NULL DEFAULT 0,
    can_edit_price TINYINT(1) NOT NULL DEFAULT 0,
    can_additional TINYINT(1) NOT NULL DEFAULT 0,
    can_serial_list TINYINT(1) NOT NULL DEFAULT 0,
    can_extra_charges TINYINT(1) NOT NULL DEFAULT 0,
    can_vat TINYINT(1) NOT NULL DEFAULT 0,
    can_ait TINYINT(1) NOT NULL DEFAULT 0,
    can_discount TINYINT(1) NOT NULL DEFAULT 0,
    can_preview TINYINT(1) NOT NULL DEFAULT 0,
    can_print TINYINT(1) NOT NULL DEFAULT 0,
    can_edit_after_app TINYINT(1) NOT NULL DEFAULT 0,
    can_edit_back_date TINYINT(1) NOT NULL DEFAULT 0,
    can_advance_date TINYINT(1) NOT NULL DEFAULT 0,
    can_show_cost TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Actual (purchase) price and cost columns',
    can_show_price TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Sales price columns in reports',
    can_print_before_app TINYINT(1) NOT NULL DEFAULT 0,
    can_edit_after_print TINYINT(1) NOT NULL DEFAULT 0,
    updated_by INT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, branch_id, form_key),
    CONSTRAINT fk_user_perm_user FOREIGN KEY (user_id) REFERENCES admin_user (user_id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Rights of a role, in every branch the user can open. A user's own row in admin_user_permissions replaces the role's
-- row for that form, so one person can be given more or less than their role.
CREATE TABLE IF NOT EXISTS admin_role_permissions (
    user_role_id INT NOT NULL,
    form_key VARCHAR(160) NOT NULL,
    can_view TINYINT(1) NOT NULL DEFAULT 0,
    can_add TINYINT(1) NOT NULL DEFAULT 0,
    can_edit TINYINT(1) NOT NULL DEFAULT 0,
    can_delete TINYINT(1) NOT NULL DEFAULT 0,
    can_edit_price TINYINT(1) NOT NULL DEFAULT 0,
    can_additional TINYINT(1) NOT NULL DEFAULT 0,
    can_serial_list TINYINT(1) NOT NULL DEFAULT 0,
    can_extra_charges TINYINT(1) NOT NULL DEFAULT 0,
    can_vat TINYINT(1) NOT NULL DEFAULT 0,
    can_ait TINYINT(1) NOT NULL DEFAULT 0,
    can_discount TINYINT(1) NOT NULL DEFAULT 0,
    can_preview TINYINT(1) NOT NULL DEFAULT 0,
    can_print TINYINT(1) NOT NULL DEFAULT 0,
    can_edit_after_app TINYINT(1) NOT NULL DEFAULT 0,
    can_edit_back_date TINYINT(1) NOT NULL DEFAULT 0,
    can_advance_date TINYINT(1) NOT NULL DEFAULT 0,
    can_show_cost TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Actual (purchase) price and cost columns',
    can_show_price TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Sales price columns in reports',
    can_print_before_app TINYINT(1) NOT NULL DEFAULT 0,
    can_edit_after_print TINYINT(1) NOT NULL DEFAULT 0,
    updated_by INT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_role_id, form_key),
    CONSTRAINT fk_role_perm_role FOREIGN KEY (user_role_id) REFERENCES admin_role (user_role_id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- First print of a document, for Edit After Print.
CREATE TABLE IF NOT EXISTS admin_doc_prints (
    doc_table VARCHAR(40) NOT NULL,
    doc_id BIGINT UNSIGNED NOT NULL,
    printed_by INT NULL,
    printed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (doc_table, doc_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- admin_role is still joined by the older pages. New users share this role: it opens those pages' own checks, so
-- admin_user_permissions alone decides what they can reach.
INSERT INTO admin_role (role_name, description, addby, user_config, user, role)
SELECT 'ERP User', 'Rights come from User Access Control', 'schema', '1,1,1,1', '1,1,1,1', '1,1,1,1' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM admin_role WHERE role_name='ERP User');


-- User Wise Store Fixing (vsc/user/user_store_fixing.php): which stores a user may work with. One row per rule.
-- branch_id 0 = all branches, form_key '' = all modules, store_name '' = all stores. A user with no rows is not limited;
-- once a user has a row, only what the rows leave open can be used. SuperAdmin is never limited.
CREATE TABLE IF NOT EXISTS admin_user_stores (
    rule_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT NOT NULL,
    branch_id INT UNSIGNED NOT NULL DEFAULT 0,
    form_key VARCHAR(160) NOT NULL DEFAULT '',
    store_name VARCHAR(140) NOT NULL DEFAULT '',
    created_by INT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (rule_id),
    UNIQUE KEY uq_user_store_rule (user_id, branch_id, form_key, store_name),
    CONSTRAINT fk_user_store_user FOREIGN KEY (user_id) REFERENCES admin_user (user_id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User Wise Ledger Fixing (vsc/user/user_ledger_fixing.php): which ledgers a user may work with. One row per rule.
-- branch_id 0 = all branches. scope 'all' opens every ledger (ref_id 0); 'trader_group' opens the parties of a
-- trader_groups row; 'account_group' opens every acc_accounts row under an acc_groups row and its sub groups; 'ledger'
-- opens one acc_accounts row with its parties. A user with no rows is not limited; SuperAdmin is never limited.
CREATE TABLE IF NOT EXISTS admin_user_ledgers (
    rule_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT NOT NULL,
    branch_id INT UNSIGNED NOT NULL DEFAULT 0,
    scope ENUM('all','trader_group','account_group','ledger') NOT NULL,
    ref_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
    created_by INT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (rule_id),
    UNIQUE KEY uq_user_ledger_rule (user_id, branch_id, scope, ref_id),
    CONSTRAINT fk_user_ledger_user FOREIGN KEY (user_id) REFERENCES admin_user (user_id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Notifications (addon/inc/notify.php). Office ones go to everyone who may view form_key; staff ones to one sr_id, or to all
-- staff when sr_id is NULL. link is from the project root. Who has read what is kept per reader in app_notification_reads.
CREATE TABLE IF NOT EXISTS app_notifications (
    notification_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    audience ENUM('admin','staff') NOT NULL,
    form_key VARCHAR(160) NOT NULL DEFAULT '',
    sr_id BIGINT UNSIGNED NULL,
    title VARCHAR(140) NOT NULL,
    body VARCHAR(500) NOT NULL DEFAULT '',
    link VARCHAR(190) NOT NULL DEFAULT '',
    sent_by INT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (notification_id),
    KEY ix_app_notifications_admin (audience, notification_id),
    KEY ix_app_notifications_staff (audience, sr_id, notification_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS app_notification_reads (
    notification_id BIGINT UNSIGNED NOT NULL,
    reader_type ENUM('user','sr') NOT NULL,
    reader_id BIGINT UNSIGNED NOT NULL,
    read_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (notification_id, reader_type, reader_id),
    CONSTRAINT fk_app_notification_read FOREIGN KEY (notification_id) REFERENCES app_notifications (notification_id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Notifications to one office user (user_id) and to customers (customer_id; audience 'customer' with NULL customer_id is everyone).
SET @q := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='app_notifications' AND column_name='customer_id') = 0,
    "ALTER TABLE app_notifications MODIFY audience ENUM('admin','staff','customer') NOT NULL, ADD COLUMN user_id INT NULL AFTER sr_id, ADD COLUMN customer_id BIGINT UNSIGNED NULL AFTER user_id, ADD KEY ix_app_notifications_customer (audience, customer_id, notification_id)", 'DO 0');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;
ALTER TABLE app_notification_reads MODIFY reader_type ENUM('user','sr','customer') NOT NULL;

-- One Users, Roles & Permissions screen. The old View Billing User form is gone, so rights on it move to the Users tab.
-- Rows are read through a derived table: ON DUPLICATE KEY cannot otherwise tell the source row from the existing one.
INSERT INTO admin_user_permissions (user_id, branch_id, form_key, can_view, can_add, can_edit, can_delete)
    SELECT * FROM (SELECT user_id, branch_id, 'user/registration.php' AS form_key, can_view, can_add, can_edit, can_delete FROM admin_user_permissions WHERE form_key='user/view_users.php') AS old_right
    ON DUPLICATE KEY UPDATE can_view=GREATEST(admin_user_permissions.can_view, old_right.can_view), can_add=GREATEST(admin_user_permissions.can_add, old_right.can_add),
        can_edit=GREATEST(admin_user_permissions.can_edit, old_right.can_edit), can_delete=GREATEST(admin_user_permissions.can_delete, old_right.can_delete);
DELETE FROM admin_user_permissions WHERE form_key='user/view_users.php';
INSERT INTO admin_role_permissions (user_role_id, form_key, can_view, can_add, can_edit, can_delete)
    SELECT * FROM (SELECT user_role_id, 'user/registration.php' AS form_key, can_view, can_add, can_edit, can_delete FROM admin_role_permissions WHERE form_key='user/view_users.php') AS old_right
    ON DUPLICATE KEY UPDATE can_view=GREATEST(admin_role_permissions.can_view, old_right.can_view), can_add=GREATEST(admin_role_permissions.can_add, old_right.can_add),
        can_edit=GREATEST(admin_role_permissions.can_edit, old_right.can_edit), can_delete=GREATEST(admin_role_permissions.can_delete, old_right.can_delete);
DELETE FROM admin_role_permissions WHERE form_key='user/view_users.php';

-- Service licence from PetaBill Cloud, see addon/inc/licence.php. No row means this install is not connected.
CREATE TABLE IF NOT EXISTS licence_state (
  id TINYINT NOT NULL PRIMARY KEY,
  crm_url VARCHAR(255) NOT NULL,
  service_code INT NOT NULL,
  service_key CHAR(64) NOT NULL,
  public_key TEXT NOT NULL,
  payload MEDIUMTEXT NOT NULL,
  signature TEXT NOT NULL,
  checked_at INT NOT NULL DEFAULT 0,
  attempted_at INT NOT NULL DEFAULT 0,
  last_error VARCHAR(255) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Document numbering (addon/inc/docno.php): one series per document type and a counter per period.
CREATE TABLE IF NOT EXISTS doc_series (
  doc_type VARCHAR(30) NOT NULL PRIMARY KEY,
  pattern VARCHAR(80) NOT NULL,
  reset_rule ENUM('never','yearly','fiscal','monthly','daily') NOT NULL DEFAULT 'yearly',
  start_no INT UNSIGNED NOT NULL DEFAULT 1,
  updated_by INT NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS doc_counters (
  doc_type VARCHAR(30) NOT NULL,
  period_key VARCHAR(12) NOT NULL,
  last_number BIGINT UNSIGNED NOT NULL DEFAULT 0,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (doc_type, period_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Country, Language & Tax (billing/user/globalization.php): tax rates per country and Admin wording per language.
CREATE TABLE IF NOT EXISTS bs_tax_rates (
  rate_id INT AUTO_INCREMENT PRIMARY KEY,
  country_code CHAR(2) NOT NULL,
  rate_name VARCHAR(80) NOT NULL,
  rate DECIMAL(6,3) NOT NULL DEFAULT 0,
  kind ENUM('standard','reduced','zero','exempt','additional') NOT NULL DEFAULT 'standard',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 100,
  KEY idx_country (country_code, is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS bs_ui_text (
  lang_code VARCHAR(5) NOT NULL,
  msg_key VARCHAR(190) NOT NULL,
  msg_text VARCHAR(500) NOT NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (lang_code, msg_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
