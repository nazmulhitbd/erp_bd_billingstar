-- HR: onboarding, employee profiles, attendance, leave and salary for every SR, staff member and employee.
-- MySQL 8.0+. Safe to run again. Run after vsc/trader/schema.sql (sales_representatives), vsc/inventory/schema.sql
-- (branches), vsc/sales_person/schema.sql and sr_portal/schema.sql.
--
-- hr_employees is the one list of people. sr_id links a person to sales_representatives: that row gives them an SR / Staff
-- Portal login. Someone who does not sell gets a row with staff_only=1, which keeps them out of the Sales Person lists.

SET @db := DATABASE();

SET @q := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='sales_representatives' AND column_name='staff_only') = 0,
    "ALTER TABLE sales_representatives ADD COLUMN staff_only TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1: office staff with a portal login, not a sales person'", 'DO 0');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

CREATE TABLE IF NOT EXISTS hr_settings (
    setting_key VARCHAR(40) NOT NULL,
    setting_value VARCHAR(190) NOT NULL DEFAULT '',
    PRIMARY KEY (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- weekly_off: PHP weekday numbers, 0 Sunday to 6 Saturday, comma separated. late_after: check-in later than this is Late.
INSERT IGNORE INTO hr_settings (setting_key, setting_value) VALUES ('weekly_off', '5'), ('late_after', '09:30');

CREATE TABLE IF NOT EXISTS hr_departments (
    department_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    department_name VARCHAR(120) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (department_id),
    UNIQUE KEY uq_hr_departments_name (department_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS hr_designations (
    designation_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    designation_name VARCHAR(120) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (designation_id),
    UNIQUE KEY uq_hr_designations_name (designation_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS hr_holidays (
    holiday_date DATE NOT NULL,
    holiday_name VARCHAR(120) NOT NULL,
    PRIMARY KEY (holiday_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS hr_employees (
    employee_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    employee_code VARCHAR(20) NOT NULL,
    full_name VARCHAR(140) NOT NULL,
    father_name VARCHAR(140) NOT NULL DEFAULT '',
    mother_name VARCHAR(140) NOT NULL DEFAULT '',
    date_of_birth DATE NULL,
    gender ENUM('','male','female','other') NOT NULL DEFAULT '',
    blood_group VARCHAR(5) NOT NULL DEFAULT '',
    nid VARCHAR(40) NOT NULL DEFAULT '',
    mobile VARCHAR(40) NOT NULL DEFAULT '',
    email VARCHAR(190) NOT NULL DEFAULT '',
    present_address VARCHAR(255) NOT NULL DEFAULT '',
    permanent_address VARCHAR(255) NOT NULL DEFAULT '',
    emergency_name VARCHAR(140) NOT NULL DEFAULT '',
    emergency_mobile VARCHAR(40) NOT NULL DEFAULT '',
    department_id INT UNSIGNED NULL,
    designation_id INT UNSIGNED NULL,
    branch_id INT UNSIGNED NULL,
    reports_to BIGINT UNSIGNED NULL,
    employment_type ENUM('permanent','probation','contract','intern') NOT NULL DEFAULT 'permanent',
    join_date DATE NOT NULL,
    confirm_date DATE NULL,
    exit_date DATE NULL,
    status ENUM('onboarding','active','resigned','terminated') NOT NULL DEFAULT 'onboarding',
    bank_name VARCHAR(120) NOT NULL DEFAULT '',
    bank_account VARCHAR(60) NOT NULL DEFAULT '',
    sr_id BIGINT UNSIGNED NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (employee_id),
    UNIQUE KEY uq_hr_employees_code (employee_code),
    UNIQUE KEY uq_hr_employees_sr (sr_id),
    KEY ix_hr_employees_name (full_name),
    KEY ix_hr_employees_status (status),
    CONSTRAINT fk_hr_emp_department FOREIGN KEY (department_id) REFERENCES hr_departments (department_id) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_hr_emp_designation FOREIGN KEY (designation_id) REFERENCES hr_designations (designation_id) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_hr_emp_manager FOREIGN KEY (reports_to) REFERENCES hr_employees (employee_id) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_hr_emp_sr FOREIGN KEY (sr_id) REFERENCES sales_representatives (sr_id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Onboarding checklist of a person.
CREATE TABLE IF NOT EXISTS hr_onboarding_tasks (
    employee_id BIGINT UNSIGNED NOT NULL,
    task_key VARCHAR(30) NOT NULL,
    done_by BIGINT UNSIGNED NULL,
    done_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (employee_id, task_key),
    CONSTRAINT fk_hr_task_emp FOREIGN KEY (employee_id) REFERENCES hr_employees (employee_id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Salary of a person from a date. The latest row on or before a month's last day is that month's salary, so raises keep history.
CREATE TABLE IF NOT EXISTS hr_salaries (
    salary_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    employee_id BIGINT UNSIGNED NOT NULL,
    effective_from DATE NOT NULL,
    basic DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    house_rent DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    medical DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    conveyance DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    other_allowance DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    tax DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    provident_fund DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    other_deduction DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    note VARCHAR(190) NOT NULL DEFAULT '',
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (salary_id),
    UNIQUE KEY uq_hr_salaries (employee_id, effective_from),
    CONSTRAINT fk_hr_salary_emp FOREIGN KEY (employee_id) REFERENCES hr_employees (employee_id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS hr_leave_types (
    leave_type_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    leave_name VARCHAR(80) NOT NULL,
    default_days DECIMAL(6,1) NOT NULL DEFAULT 0.0 COMMENT 'Days per year, suggested on Leave Assign',
    is_paid TINYINT(1) NOT NULL DEFAULT 1 COMMENT '0: no balance needed, and the days are taken off the salary',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (leave_type_id),
    UNIQUE KEY uq_hr_leave_types_name (leave_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT IGNORE INTO hr_leave_types (leave_name, default_days, is_paid) VALUES ('Casual Leave', 10, 1), ('Sick Leave', 14, 1), ('Earned Leave', 15, 1), ('Unpaid Leave', 0, 0);

-- Days of a leave type given to a person for a year. Days used come from the approved applications.
CREATE TABLE IF NOT EXISTS hr_leave_balances (
    employee_id BIGINT UNSIGNED NOT NULL,
    leave_type_id INT UNSIGNED NOT NULL,
    leave_year SMALLINT UNSIGNED NOT NULL,
    allocated DECIMAL(6,1) NOT NULL DEFAULT 0.0,
    updated_by BIGINT UNSIGNED NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (employee_id, leave_type_id, leave_year),
    CONSTRAINT fk_hr_bal_emp FOREIGN KEY (employee_id) REFERENCES hr_employees (employee_id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_hr_bal_type FOREIGN KEY (leave_type_id) REFERENCES hr_leave_types (leave_type_id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS hr_leave_applications (
    application_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    employee_id BIGINT UNSIGNED NOT NULL,
    leave_type_id INT UNSIGNED NOT NULL,
    from_date DATE NOT NULL,
    to_date DATE NOT NULL,
    half_day TINYINT(1) NOT NULL DEFAULT 0,
    days DECIMAL(6,1) NOT NULL,
    reason VARCHAR(255) NOT NULL DEFAULT '',
    status ENUM('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
    applied_via ENUM('portal','office') NOT NULL DEFAULT 'portal',
    decided_by BIGINT UNSIGNED NULL,
    decided_at DATETIME NULL,
    decision_note VARCHAR(190) NOT NULL DEFAULT '',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (application_id),
    KEY ix_hr_leave_emp (employee_id, from_date),
    KEY ix_hr_leave_status (status, from_date),
    CONSTRAINT fk_hr_leave_emp FOREIGN KEY (employee_id) REFERENCES hr_employees (employee_id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_hr_leave_type FOREIGN KEY (leave_type_id) REFERENCES hr_leave_types (leave_type_id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One row per person per day. application_id marks a day written by an approved leave; only cancelling the leave changes it.
CREATE TABLE IF NOT EXISTS hr_attendance (
    attendance_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    employee_id BIGINT UNSIGNED NOT NULL,
    attendance_date DATE NOT NULL,
    status ENUM('present','late','half_day','absent','leave','half_leave') NOT NULL,
    check_in TIME NULL,
    check_out TIME NULL,
    unpaid_days DECIMAL(3,1) NOT NULL DEFAULT 0.0 COMMENT 'Part of the day taken off the salary',
    application_id BIGINT UNSIGNED NULL,
    source ENUM('office','portal','leave') NOT NULL DEFAULT 'office',
    note VARCHAR(190) NOT NULL DEFAULT '',
    updated_by BIGINT UNSIGNED NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (attendance_id),
    UNIQUE KEY uq_hr_attendance (employee_id, attendance_date),
    KEY ix_hr_attendance_date (attendance_date),
    KEY ix_hr_attendance_leave (application_id),
    CONSTRAINT fk_hr_att_emp FOREIGN KEY (employee_id) REFERENCES hr_employees (employee_id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_hr_att_leave FOREIGN KEY (application_id) REFERENCES hr_leave_applications (application_id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One payslip per person per month (period_month is the first day). Draft is refreshed by Generate; a person sees Approved and Paid only.
CREATE TABLE IF NOT EXISTS hr_payslips (
    payslip_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    employee_id BIGINT UNSIGNED NOT NULL,
    period_month DATE NOT NULL,
    basic DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    house_rent DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    medical DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    conveyance DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    other_allowance DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    bonus DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    gross DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Monthly pay before bonus',
    tax DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    provident_fund DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    other_deduction DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    advance DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    month_days TINYINT UNSIGNED NOT NULL DEFAULT 30,
    absent_days DECIMAL(5,1) NOT NULL DEFAULT 0.0,
    unpaid_leave_days DECIMAL(5,1) NOT NULL DEFAULT 0.0,
    not_employed_days TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Before joining or after leaving',
    absence_deduction DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    net_pay DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    status ENUM('draft','approved','paid') NOT NULL DEFAULT 'draft',
    paid_on DATE NULL,
    payment_method ENUM('cash','bank','mobile','other') NOT NULL DEFAULT 'bank',
    reference VARCHAR(120) NOT NULL DEFAULT '',
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (payslip_id),
    UNIQUE KEY uq_hr_payslips (employee_id, period_month),
    KEY ix_hr_payslips_month (period_month, status),
    CONSTRAINT fk_hr_payslip_emp FOREIGN KEY (employee_id) REFERENCES hr_employees (employee_id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Full employee record: more profile fields, then one table per kind of record (see HR_RECORDS in addon/inc/hr.php).
SET @q := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='hr_employees' AND column_name='marital_status') = 0,
    "ALTER TABLE hr_employees
        ADD COLUMN marital_status ENUM('','single','married','other') NOT NULL DEFAULT '' AFTER blood_group,
        ADD COLUMN spouse_name VARCHAR(140) NOT NULL DEFAULT '' AFTER marital_status,
        ADD COLUMN religion VARCHAR(40) NOT NULL DEFAULT '' AFTER spouse_name,
        ADD COLUMN nationality VARCHAR(60) NOT NULL DEFAULT '' AFTER religion,
        ADD COLUMN passport_no VARCHAR(40) NOT NULL DEFAULT '' AFTER nid,
        ADD COLUMN driving_license VARCHAR(40) NOT NULL DEFAULT '' AFTER passport_no,
        ADD COLUMN tin VARCHAR(40) NOT NULL DEFAULT '' AFTER driving_license,
        ADD COLUMN skills VARCHAR(255) NOT NULL DEFAULT '' AFTER emergency_mobile,
        ADD COLUMN languages VARCHAR(190) NOT NULL DEFAULT '' AFTER skills", 'DO 0');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

CREATE TABLE IF NOT EXISTS hr_education (
    record_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    employee_id BIGINT UNSIGNED NOT NULL,
    level VARCHAR(20) NOT NULL,
    degree VARCHAR(120) NOT NULL,
    institution VARCHAR(190) NOT NULL,
    board_university VARCHAR(140) NOT NULL DEFAULT '',
    major VARCHAR(120) NOT NULL DEFAULT '',
    passing_year SMALLINT UNSIGNED NULL,
    result VARCHAR(40) NOT NULL DEFAULT '',
    PRIMARY KEY (record_id),
    KEY ix_hr_education_emp (employee_id),
    CONSTRAINT fk_hr_education_emp FOREIGN KEY (employee_id) REFERENCES hr_employees (employee_id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Jobs before this one. A blank to_date means they were still working there when they applied.
CREATE TABLE IF NOT EXISTS hr_experience (
    record_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    employee_id BIGINT UNSIGNED NOT NULL,
    company VARCHAR(190) NOT NULL,
    job_title VARCHAR(120) NOT NULL,
    from_date DATE NOT NULL,
    to_date DATE NULL,
    last_salary DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    responsibilities VARCHAR(500) NOT NULL DEFAULT '',
    leaving_reason VARCHAR(190) NOT NULL DEFAULT '',
    contact VARCHAR(140) NOT NULL DEFAULT '',
    PRIMARY KEY (record_id),
    KEY ix_hr_experience_emp (employee_id),
    CONSTRAINT fk_hr_experience_emp FOREIGN KEY (employee_id) REFERENCES hr_employees (employee_id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS hr_references (
    record_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    employee_id BIGINT UNSIGNED NOT NULL,
    reference_name VARCHAR(140) NOT NULL,
    relation VARCHAR(80) NOT NULL DEFAULT '',
    organization VARCHAR(190) NOT NULL DEFAULT '',
    designation VARCHAR(120) NOT NULL DEFAULT '',
    mobile VARCHAR(40) NOT NULL,
    email VARCHAR(190) NOT NULL DEFAULT '',
    address VARCHAR(255) NOT NULL DEFAULT '',
    verified TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'The office has spoken to this person',
    PRIMARY KEY (record_id),
    KEY ix_hr_references_emp (employee_id),
    CONSTRAINT fk_hr_references_emp FOREIGN KEY (employee_id) REFERENCES hr_employees (employee_id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS hr_training (
    record_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    employee_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(190) NOT NULL,
    institute VARCHAR(190) NOT NULL DEFAULT '',
    from_date DATE NULL,
    to_date DATE NULL,
    certificate_no VARCHAR(80) NOT NULL DEFAULT '',
    PRIMARY KEY (record_id),
    KEY ix_hr_training_emp (employee_id),
    CONSTRAINT fk_hr_training_emp FOREIGN KEY (employee_id) REFERENCES hr_employees (employee_id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Family members and nominees. A nominee's share of what is payable to them, in percent; a person's shares cannot pass 100.
CREATE TABLE IF NOT EXISTS hr_family (
    record_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    employee_id BIGINT UNSIGNED NOT NULL,
    member_name VARCHAR(140) NOT NULL,
    relation VARCHAR(40) NOT NULL,
    date_of_birth DATE NULL,
    occupation VARCHAR(120) NOT NULL DEFAULT '',
    mobile VARCHAR(40) NOT NULL DEFAULT '',
    nominee_share DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    PRIMARY KEY (record_id),
    KEY ix_hr_family_emp (employee_id),
    CONSTRAINT fk_hr_family_emp FOREIGN KEY (employee_id) REFERENCES hr_employees (employee_id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Scanned papers. Files are in vsc/hr/uploads under a random name and are only served after login.
CREATE TABLE IF NOT EXISTS hr_documents (
    document_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    employee_id BIGINT UNSIGNED NOT NULL,
    document_type VARCHAR(40) NOT NULL,
    title VARCHAR(190) NOT NULL DEFAULT '',
    original_name VARCHAR(190) NOT NULL,
    stored_name VARCHAR(80) NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    size_bytes INT UNSIGNED NOT NULL DEFAULT 0,
    uploaded_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (document_id),
    KEY ix_hr_documents_emp (employee_id),
    CONSTRAINT fk_hr_documents_emp FOREIGN KEY (employee_id) REFERENCES hr_employees (employee_id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Salary tax and disbursement. A person's photo is their latest hr_documents row of type Photo, so it needs no table.
-- Bands are worked from the lowest sort_order up: band_amount of yearly income at rate percent; band_amount 0 means all the rest.
CREATE TABLE IF NOT EXISTS hr_tax_slabs (
    slab_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    band_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    rate DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    PRIMARY KEY (slab_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @q := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='hr_salaries' AND column_name='tax_mode') = 0,
    "ALTER TABLE hr_salaries ADD COLUMN tax_mode ENUM('manual','slab') NOT NULL DEFAULT 'manual' AFTER other_allowance", 'DO 0');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

-- pay_account_id: the cash or bank account paid from. voucher_id: the journal in Accounts, cleared if the payment is undone.
SET @q := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='hr_payslips' AND column_name='voucher_id') = 0,
    "ALTER TABLE hr_payslips ADD COLUMN pay_account_id BIGINT UNSIGNED NULL AFTER reference, ADD COLUMN voucher_id BIGINT UNSIGNED NULL AFTER pay_account_id, ADD KEY ix_hr_payslips_voucher (voucher_id)", 'DO 0');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;
