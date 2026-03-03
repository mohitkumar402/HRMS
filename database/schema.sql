-- ============================================================
-- ENTERPRISE HRMS - COMPLETE DATABASE SCHEMA
-- Version: 1.0.0 | Engine: InnoDB | Charset: utf8mb4
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";
SET foreign_key_checks = 0;

CREATE DATABASE IF NOT EXISTS `hrms_db`
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;

USE `hrms_db`;

-- ============================================================
-- SECTION 1: RBAC - ROLES & PERMISSIONS
-- ============================================================

CREATE TABLE `roles` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name`        VARCHAR(100) NOT NULL UNIQUE,
  `slug`        VARCHAR(100) NOT NULL UNIQUE,
  `description` TEXT,
  `is_system`   TINYINT(1) DEFAULT 0,
  `created_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE `permissions` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `module`      VARCHAR(100) NOT NULL,
  `action`      VARCHAR(100) NOT NULL,
  `slug`        VARCHAR(200) NOT NULL UNIQUE,
  `description` TEXT,
  UNIQUE KEY `module_action` (`module`,`action`)
) ENGINE=InnoDB;

CREATE TABLE `role_permissions` (
  `role_id`       INT UNSIGNED NOT NULL,
  `permission_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`role_id`,`permission_id`),
  FOREIGN KEY (`role_id`)       REFERENCES `roles`(`id`)       ON DELETE CASCADE,
  FOREIGN KEY (`permission_id`) REFERENCES `permissions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- SECTION 2: USERS & AUTHENTICATION
-- ============================================================

CREATE TABLE `users` (
  `id`                   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `email`                VARCHAR(255) NOT NULL UNIQUE,
  `password_hash`        VARCHAR(255) NOT NULL,
  `role_id`              INT UNSIGNED NOT NULL,
  `is_active`            TINYINT(1) DEFAULT 1,
  `last_login`           TIMESTAMP NULL,
  `login_attempts`       TINYINT DEFAULT 0,
  `locked_until`         TIMESTAMP NULL,
  `password_reset_token` VARCHAR(255) NULL,
  `reset_token_expires`  TIMESTAMP NULL,
  `two_factor_secret`    VARCHAR(255) NULL,
  `two_factor_enabled`   TINYINT(1) DEFAULT 0,
  `remember_token`       VARCHAR(255) NULL,
  `created_at`           TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at`           TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`)
) ENGINE=InnoDB;

CREATE TABLE `audit_logs` (
  `id`          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id`     INT UNSIGNED NULL,
  `action`      VARCHAR(200) NOT NULL,
  `module`      VARCHAR(100) NOT NULL,
  `record_id`   INT UNSIGNED NULL,
  `old_values`  JSON NULL,
  `new_values`  JSON NULL,
  `ip_address`  VARCHAR(45) NULL,
  `user_agent`  TEXT NULL,
  `created_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  INDEX `idx_module_record` (`module`,`record_id`),
  INDEX `idx_user_action`   (`user_id`,`action`),
  INDEX `idx_created`       (`created_at`)
) ENGINE=InnoDB;

-- ============================================================
-- SECTION 3: ORGANIZATION STRUCTURE
-- ============================================================

CREATE TABLE `departments` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name`        VARCHAR(150) NOT NULL,
  `code`        VARCHAR(20)  NOT NULL UNIQUE,
  `parent_id`   INT UNSIGNED NULL,
  `head_emp_id` INT UNSIGNED NULL,
  `description` TEXT,
  `cost_center` VARCHAR(50) NULL,
  `is_active`   TINYINT(1) DEFAULT 1,
  `created_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`parent_id`) REFERENCES `departments`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE `designations` (
  `id`             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `title`          VARCHAR(150) NOT NULL,
  `code`           VARCHAR(20)  NOT NULL UNIQUE,
  `department_id`  INT UNSIGNED NULL,
  `level`          TINYINT DEFAULT 1 COMMENT '1=Entry,2=Mid,3=Senior,4=Lead,5=Manager,6=Director,7=VP,8=C-Level',
  `description`    TEXT,
  `is_active`      TINYINT(1) DEFAULT 1,
  `created_at`     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE `locations` (
  `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name`       VARCHAR(150) NOT NULL,
  `code`       VARCHAR(20)  NOT NULL UNIQUE,
  `address`    TEXT,
  `city`       VARCHAR(100),
  `state`      VARCHAR(100),
  `country`    VARCHAR(100) DEFAULT 'India',
  `pin_code`   VARCHAR(20),
  `timezone`   VARCHAR(50)  DEFAULT 'Asia/Kolkata',
  `is_active`  TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================
-- SECTION 4: EMPLOYEE MASTER
-- ============================================================

CREATE TABLE `employees` (
  `id`                   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `employee_id`          VARCHAR(20) NOT NULL UNIQUE COMMENT 'EMP0001',
  `user_id`              INT UNSIGNED NOT NULL UNIQUE,
  `first_name`           VARCHAR(100) NOT NULL,
  `middle_name`          VARCHAR(100) NULL,
  `last_name`            VARCHAR(100) NOT NULL,
  `display_name`         VARCHAR(200) GENERATED ALWAYS AS (CONCAT(`first_name`,' ',`last_name`)) STORED,
  `gender`               ENUM('male','female','other','prefer_not_to_say') NOT NULL,
  `date_of_birth`        DATE NULL,
  `nationality`          VARCHAR(100) NULL,
  `marital_status`       ENUM('single','married','divorced','widowed') DEFAULT 'single',
  `blood_group`          ENUM('A+','A-','B+','B-','AB+','AB-','O+','O-') NULL,
  `personal_email`       VARCHAR(255) NULL,
  `work_email`           VARCHAR(255) NOT NULL UNIQUE,
  `personal_phone`       VARCHAR(20) NULL,
  `work_phone`           VARCHAR(20) NULL,
  `emergency_contact_name`   VARCHAR(150) NULL,
  `emergency_contact_phone`  VARCHAR(20) NULL,
  `emergency_contact_relation` VARCHAR(50) NULL,
  -- Address
  `current_address`      TEXT NULL,
  `permanent_address`    TEXT NULL,
  -- Employment Details
  `department_id`        INT UNSIGNED NULL,
  `designation_id`       INT UNSIGNED NULL,
  `location_id`          INT UNSIGNED NULL,
  `manager_id`           INT UNSIGNED NULL,
  `employment_type`      ENUM('full_time','part_time','contract','intern','consultant') DEFAULT 'full_time',
  `employment_status`    ENUM('active','probation','notice','terminated','resigned','retired') DEFAULT 'probation',
  `date_joined`          DATE NOT NULL,
  `confirmation_date`    DATE NULL,
  `last_working_day`     DATE NULL,
  `probation_end_date`   DATE NULL,
  -- Identity
  `pan_number`           VARCHAR(20) NULL,
  `aadhar_number`        VARCHAR(20) NULL,
  `passport_number`      VARCHAR(20) NULL,
  `passport_expiry`      DATE NULL,
  `pf_number`            VARCHAR(30) NULL,
  `esi_number`           VARCHAR(30) NULL,
  `uan_number`           VARCHAR(30) NULL,
  -- Bank
  `bank_name`            VARCHAR(150) NULL,
  `bank_account_number`  VARCHAR(30) NULL,
  `bank_ifsc_code`       VARCHAR(20) NULL,
  `bank_branch`          VARCHAR(150) NULL,
  -- Profile
  `profile_photo`        VARCHAR(500) NULL,
  `bio`                  TEXT NULL,
  `skills`               JSON NULL,
  `linkedin_url`         VARCHAR(500) NULL,
  -- Metadata
  `created_at`           TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at`           TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`)       REFERENCES `users`(`id`)       ON DELETE RESTRICT,
  FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`designation_id`) REFERENCES `designations`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`location_id`)   REFERENCES `locations`(`id`)   ON DELETE SET NULL,
  FOREIGN KEY (`manager_id`)    REFERENCES `employees`(`id`)   ON DELETE SET NULL,
  INDEX `idx_emp_status`  (`employment_status`),
  INDEX `idx_emp_dept`    (`department_id`),
  INDEX `idx_emp_manager` (`manager_id`)
) ENGINE=InnoDB;

-- Update department head FK after employees table is created
ALTER TABLE `departments`
  ADD FOREIGN KEY (`head_emp_id`) REFERENCES `employees`(`id`) ON DELETE SET NULL;

CREATE TABLE `employee_documents` (
  `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `employee_id`   INT UNSIGNED NOT NULL,
  `document_type` ENUM('offer_letter','appointment_letter','increment_letter','appraisal_letter',
                        'id_proof','address_proof','education_certificate','experience_letter',
                        'nda','policy_acknowledgement','other') NOT NULL,
  `document_name` VARCHAR(255) NOT NULL,
  `file_path`     VARCHAR(500) NOT NULL,
  `file_size`     INT UNSIGNED NULL,
  `mime_type`     VARCHAR(100) NULL,
  `expiry_date`   DATE NULL,
  `is_verified`   TINYINT(1) DEFAULT 0,
  `verified_by`   INT UNSIGNED NULL,
  `uploaded_at`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`employee_id`) REFERENCES `employees`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`verified_by`) REFERENCES `users`(`id`)     ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE `employment_history` (
  `id`             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `employee_id`    INT UNSIGNED NOT NULL,
  `department_id`  INT UNSIGNED NULL,
  `designation_id` INT UNSIGNED NULL,
  `employment_type` ENUM('full_time','part_time','contract','intern','consultant') NULL,
  `effective_date` DATE NOT NULL,
  `change_type`    ENUM('joining','promotion','demotion','transfer','department_change',
                        'designation_change','termination','resignation','rehire') NOT NULL,
  `previous_value` JSON NULL,
  `new_value`      JSON NULL,
  `remarks`        TEXT NULL,
  `changed_by`     INT UNSIGNED NULL,
  `created_at`     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`employee_id`) REFERENCES `employees`(`id`) ON DELETE CASCADE,
  INDEX `idx_emp_history` (`employee_id`,`effective_date`)
) ENGINE=InnoDB;

CREATE TABLE `employee_education` (
  `id`               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `employee_id`      INT UNSIGNED NOT NULL,
  `degree`           VARCHAR(150) NOT NULL,
  `field_of_study`   VARCHAR(150) NULL,
  `institution`      VARCHAR(255) NOT NULL,
  `year_from`        YEAR NOT NULL,
  `year_to`          YEAR NULL,
  `grade_percentage` DECIMAL(5,2) NULL,
  `is_highest`       TINYINT(1) DEFAULT 0,
  FOREIGN KEY (`employee_id`) REFERENCES `employees`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE `employee_experience` (
  `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `employee_id`  INT UNSIGNED NOT NULL,
  `company_name` VARCHAR(255) NOT NULL,
  `designation`  VARCHAR(150) NULL,
  `from_date`    DATE NOT NULL,
  `to_date`      DATE NULL,
  `is_current`   TINYINT(1) DEFAULT 0,
  `description`  TEXT NULL,
  FOREIGN KEY (`employee_id`) REFERENCES `employees`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- SECTION 5: ATTENDANCE & TIME
-- ============================================================

CREATE TABLE `shifts` (
  `id`              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name`            VARCHAR(100) NOT NULL,
  `code`            VARCHAR(20)  NOT NULL UNIQUE,
  `start_time`      TIME NOT NULL,
  `end_time`        TIME NOT NULL,
  `grace_in_mins`   SMALLINT DEFAULT 15,
  `break_mins`      SMALLINT DEFAULT 30,
  `total_hours`     DECIMAL(4,2) GENERATED ALWAYS AS (
                      TIMESTAMPDIFF(MINUTE, start_time, end_time) / 60.0
                    ) STORED,
  `shift_type`      ENUM('general','morning','evening','night','rotational') DEFAULT 'general',
  `is_active`       TINYINT(1) DEFAULT 1,
  `created_at`      TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE `shift_assignments` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `employee_id` INT UNSIGNED NOT NULL,
  `shift_id`    INT UNSIGNED NOT NULL,
  `from_date`   DATE NOT NULL,
  `to_date`     DATE NULL,
  `created_by`  INT UNSIGNED NULL,
  `created_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`employee_id`) REFERENCES `employees`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`shift_id`)    REFERENCES `shifts`(`id`)    ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE `holidays` (
  `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name`         VARCHAR(150) NOT NULL,
  `date`         DATE NOT NULL,
  `type`         ENUM('national','regional','optional','restricted') DEFAULT 'national',
  `description`  TEXT NULL,
  `year`         YEAR GENERATED ALWAYS AS (YEAR(`date`)) STORED,
  `is_active`    TINYINT(1) DEFAULT 1,
  INDEX `idx_holiday_date` (`date`)
) ENGINE=InnoDB;

CREATE TABLE `attendance` (
  `id`               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `employee_id`      INT UNSIGNED NOT NULL,
  `attendance_date`  DATE NOT NULL,
  `check_in`         DATETIME NULL,
  `check_out`        DATETIME NULL,
  `total_hours`      DECIMAL(5,2) NULL,
  `overtime_hours`   DECIMAL(5,2) DEFAULT 0,
  `status`           ENUM('present','absent','half_day','late','wfh','on_duty','holiday','week_off','leave') DEFAULT 'absent',
  `check_in_location`  VARCHAR(200) NULL,
  `check_out_location` VARCHAR(200) NULL,
  `check_in_ip`        VARCHAR(45) NULL,
  `check_out_ip`       VARCHAR(45) NULL,
  `shift_id`           INT UNSIGNED NULL,
  `remarks`            TEXT NULL,
  `is_regularized`     TINYINT(1) DEFAULT 0,
  `regularized_by`     INT UNSIGNED NULL,
  `created_at`         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at`         TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `emp_date` (`employee_id`,`attendance_date`),
  FOREIGN KEY (`employee_id`) REFERENCES `employees`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`shift_id`)    REFERENCES `shifts`(`id`)    ON DELETE SET NULL,
  INDEX `idx_att_date`   (`attendance_date`),
  INDEX `idx_att_status` (`status`)
) ENGINE=InnoDB;

CREATE TABLE `overtime_requests` (
  `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `employee_id`  INT UNSIGNED NOT NULL,
  `date`         DATE NOT NULL,
  `start_time`   TIME NOT NULL,
  `end_time`     TIME NOT NULL,
  `hours`        DECIMAL(4,2) NOT NULL,
  `reason`       TEXT NOT NULL,
  `status`       ENUM('pending','approved','rejected') DEFAULT 'pending',
  `approved_by`  INT UNSIGNED NULL,
  `approved_at`  TIMESTAMP NULL,
  `created_at`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`employee_id`) REFERENCES `employees`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- SECTION 6: LEAVE MANAGEMENT
-- ============================================================

CREATE TABLE `leave_types` (
  `id`                   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name`                 VARCHAR(100) NOT NULL,
  `code`                 VARCHAR(20)  NOT NULL UNIQUE,
  `annual_allocation`    DECIMAL(5,1) NOT NULL DEFAULT 0,
  `carry_forward_limit`  DECIMAL(5,1) DEFAULT 0,
  `encashable_limit`     DECIMAL(5,1) DEFAULT 0,
  `is_paid`              TINYINT(1) DEFAULT 1,
  `requires_document`    TINYINT(1) DEFAULT 0,
  `min_notice_days`      TINYINT DEFAULT 0,
  `max_consecutive_days` SMALLINT NULL,
  `applicable_gender`    ENUM('all','male','female') DEFAULT 'all',
  `color`                VARCHAR(20) DEFAULT '#3B82F6',
  `description`          TEXT NULL,
  `is_active`            TINYINT(1) DEFAULT 1,
  `created_at`           TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE `leave_balances` (
  `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `employee_id`   INT UNSIGNED NOT NULL,
  `leave_type_id` INT UNSIGNED NOT NULL,
  `year`          YEAR NOT NULL,
  `allocated`     DECIMAL(5,1) DEFAULT 0,
  `used`          DECIMAL(5,1) DEFAULT 0,
  `carry_forward` DECIMAL(5,1) DEFAULT 0,
  `encashed`      DECIMAL(5,1) DEFAULT 0,
  `balance`       DECIMAL(5,1) GENERATED ALWAYS AS (`allocated` + `carry_forward` - `used` - `encashed`) STORED,
  `updated_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `emp_type_year` (`employee_id`,`leave_type_id`,`year`),
  FOREIGN KEY (`employee_id`)   REFERENCES `employees`(`id`)   ON DELETE CASCADE,
  FOREIGN KEY (`leave_type_id`) REFERENCES `leave_types`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE `leave_requests` (
  `id`              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `employee_id`     INT UNSIGNED NOT NULL,
  `leave_type_id`   INT UNSIGNED NOT NULL,
  `from_date`       DATE NOT NULL,
  `to_date`         DATE NOT NULL,
  `total_days`      DECIMAL(5,1) NOT NULL,
  `day_type`        ENUM('full_day','first_half','second_half') DEFAULT 'full_day',
  `reason`          TEXT NOT NULL,
  `document_path`   VARCHAR(500) NULL,
  `status`          ENUM('pending','approved','rejected','cancelled','withdrawn') DEFAULT 'pending',
  `applied_on`      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `manager_id`      INT UNSIGNED NULL,
  `manager_remarks` TEXT NULL,
  `manager_action_at` TIMESTAMP NULL,
  `hr_remarks`      TEXT NULL,
  `hr_action_at`    TIMESTAMP NULL,
  `is_lop`          TINYINT(1) DEFAULT 0 COMMENT 'Loss Of Pay',
  FOREIGN KEY (`employee_id`)   REFERENCES `employees`(`id`)   ON DELETE CASCADE,
  FOREIGN KEY (`leave_type_id`) REFERENCES `leave_types`(`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`manager_id`)    REFERENCES `employees`(`id`)   ON DELETE SET NULL,
  INDEX `idx_leave_status`  (`status`),
  INDEX `idx_leave_emp_date`(`employee_id`,`from_date`)
) ENGINE=InnoDB;

-- ============================================================
-- SECTION 7: PAYROLL MANAGEMENT
-- ============================================================

CREATE TABLE `payroll_components` (
  `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name`          VARCHAR(150) NOT NULL,
  `code`          VARCHAR(30)  NOT NULL UNIQUE,
  `type`          ENUM('earning','deduction','tax','benefit','reimbursement') NOT NULL,
  `calculation`   ENUM('fixed','percentage_of_ctc','percentage_of_basic','formula') DEFAULT 'fixed',
  `formula`       TEXT NULL COMMENT 'Formula or percentage value',
  `is_taxable`    TINYINT(1) DEFAULT 1,
  `is_mandatory`  TINYINT(1) DEFAULT 0,
  `is_active`     TINYINT(1) DEFAULT 1,
  `description`   TEXT NULL,
  `created_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE `salary_structures` (
  `id`                   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `employee_id`          INT UNSIGNED NOT NULL,
  `effective_date`       DATE NOT NULL,
  `ctc_annual`           DECIMAL(15,2) NOT NULL,
  `ctc_monthly`          DECIMAL(15,2) GENERATED ALWAYS AS (`ctc_annual` / 12) STORED,
  `basic_monthly`        DECIMAL(15,2) NOT NULL,
  `hra_monthly`          DECIMAL(15,2) DEFAULT 0,
  `special_allowance`    DECIMAL(15,2) DEFAULT 0,
  `pf_employee`          DECIMAL(15,2) DEFAULT 0,
  `pf_employer`          DECIMAL(15,2) DEFAULT 0,
  `esi_employee`         DECIMAL(15,2) DEFAULT 0,
  `esi_employer`         DECIMAL(15,2) DEFAULT 0,
  `professional_tax`     DECIMAL(15,2) DEFAULT 0,
  `tds_monthly`          DECIMAL(15,2) DEFAULT 0,
  `gross_monthly`        DECIMAL(15,2) NOT NULL,
  `net_monthly`          DECIMAL(15,2) NOT NULL,
  `components`           JSON NULL COMMENT 'Additional components',
  `status`               ENUM('active','superseded') DEFAULT 'active',
  `created_by`           INT UNSIGNED NULL,
  `created_at`           TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`employee_id`) REFERENCES `employees`(`id`) ON DELETE CASCADE,
  INDEX `idx_salary_emp_date` (`employee_id`,`effective_date`)
) ENGINE=InnoDB;

CREATE TABLE `payroll_runs` (
  `id`              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `month`           TINYINT NOT NULL,
  `year`            YEAR NOT NULL,
  `pay_date`        DATE NULL,
  `status`          ENUM('draft','processing','approved','paid','cancelled') DEFAULT 'draft',
  `total_employees` INT DEFAULT 0,
  `total_gross`     DECIMAL(15,2) DEFAULT 0,
  `total_deductions`DECIMAL(15,2) DEFAULT 0,
  `total_net`       DECIMAL(15,2) DEFAULT 0,
  `remarks`         TEXT NULL,
  `created_by`      INT UNSIGNED NULL,
  `approved_by`     INT UNSIGNED NULL,
  `approved_at`     TIMESTAMP NULL,
  `created_at`      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `month_year` (`month`,`year`),
  FOREIGN KEY (`created_by`)  REFERENCES `users`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`approved_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE `payslips` (
  `id`               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `payroll_run_id`   INT UNSIGNED NOT NULL,
  `employee_id`      INT UNSIGNED NOT NULL,
  `month`            TINYINT NOT NULL,
  `year`             YEAR NOT NULL,
  `working_days`     DECIMAL(4,1) NOT NULL,
  `present_days`     DECIMAL(4,1) DEFAULT 0,
  `lop_days`         DECIMAL(4,1) DEFAULT 0,
  `paid_days`        DECIMAL(4,1) DEFAULT 0,
  `basic`            DECIMAL(15,2) DEFAULT 0,
  `hra`              DECIMAL(15,2) DEFAULT 0,
  `special_allowance`DECIMAL(15,2) DEFAULT 0,
  `other_earnings`   JSON NULL,
  `gross_salary`     DECIMAL(15,2) NOT NULL,
  `pf_deduction`     DECIMAL(15,2) DEFAULT 0,
  `esi_deduction`    DECIMAL(15,2) DEFAULT 0,
  `tds_deduction`    DECIMAL(15,2) DEFAULT 0,
  `professional_tax` DECIMAL(15,2) DEFAULT 0,
  `loan_deduction`   DECIMAL(15,2) DEFAULT 0,
  `other_deductions` JSON NULL,
  `total_deductions` DECIMAL(15,2) NOT NULL,
  `net_salary`       DECIMAL(15,2) NOT NULL,
  `status`           ENUM('draft','paid') DEFAULT 'draft',
  `remarks`          TEXT NULL,
  `created_at`       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `run_employee` (`payroll_run_id`,`employee_id`),
  FOREIGN KEY (`payroll_run_id`) REFERENCES `payroll_runs`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`employee_id`)    REFERENCES `employees`(`id`)    ON DELETE RESTRICT,
  INDEX `idx_payslip_emp` (`employee_id`,`year`,`month`)
) ENGINE=InnoDB;

CREATE TABLE `expense_claims` (
  `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `employee_id`  INT UNSIGNED NOT NULL,
  `claim_date`   DATE NOT NULL,
  `category`     ENUM('travel','accommodation','meals','communication','training','other') NOT NULL,
  `amount`       DECIMAL(10,2) NOT NULL,
  `description`  TEXT NOT NULL,
  `receipt_path` VARCHAR(500) NULL,
  `status`       ENUM('pending','approved','rejected','paid') DEFAULT 'pending',
  `approved_by`  INT UNSIGNED NULL,
  `approved_at`  TIMESTAMP NULL,
  `payroll_run_id` INT UNSIGNED NULL,
  `created_at`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`employee_id`) REFERENCES `employees`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- SECTION 8: RECRUITMENT & ATS
-- ============================================================

CREATE TABLE `job_postings` (
  `id`                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `requisition_number`  VARCHAR(30) NOT NULL UNIQUE,
  `title`               VARCHAR(200) NOT NULL,
  `department_id`       INT UNSIGNED NULL,
  `designation_id`      INT UNSIGNED NULL,
  `location_id`         INT UNSIGNED NULL,
  `employment_type`     ENUM('full_time','part_time','contract','intern') DEFAULT 'full_time',
  `experience_min`      DECIMAL(3,1) DEFAULT 0,
  `experience_max`      DECIMAL(3,1) NULL,
  `salary_min`          DECIMAL(12,2) NULL,
  `salary_max`          DECIMAL(12,2) NULL,
  `openings`            SMALLINT DEFAULT 1,
  `description`         LONGTEXT NOT NULL,
  `requirements`        LONGTEXT NULL,
  `skills_required`     JSON NULL,
  `posted_date`         DATE NULL,
  `closing_date`        DATE NULL,
  `status`              ENUM('draft','open','paused','closed','cancelled') DEFAULT 'draft',
  `posted_by`           INT UNSIGNED NULL,
  `approved_by`         INT UNSIGNED NULL,
  `approved_at`         TIMESTAMP NULL,
  `is_internal`         TINYINT(1) DEFAULT 0,
  `created_at`          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`department_id`)  REFERENCES `departments`(`id`)  ON DELETE SET NULL,
  FOREIGN KEY (`designation_id`) REFERENCES `designations`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`location_id`)    REFERENCES `locations`(`id`)    ON DELETE SET NULL,
  INDEX `idx_job_status` (`status`)
) ENGINE=InnoDB;

CREATE TABLE `candidates` (
  `id`             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `first_name`     VARCHAR(100) NOT NULL,
  `last_name`      VARCHAR(100) NOT NULL,
  `email`          VARCHAR(255) NOT NULL UNIQUE,
  `phone`          VARCHAR(20) NOT NULL,
  `current_company`VARCHAR(200) NULL,
  `current_title`  VARCHAR(200) NULL,
  `experience_years` DECIMAL(3,1) DEFAULT 0,
  `current_ctc`    DECIMAL(12,2) NULL,
  `expected_ctc`   DECIMAL(12,2) NULL,
  `notice_period`  TINYINT NULL COMMENT 'In days',
  `resume_path`    VARCHAR(500) NULL,
  `linkedin_url`   VARCHAR(500) NULL,
  `skills`         JSON NULL,
  `source`         ENUM('job_portal','linkedin','referral','campus','direct','agency','other') DEFAULT 'direct',
  `referred_by`    INT UNSIGNED NULL,
  `created_at`     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`referred_by`) REFERENCES `employees`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE `applications` (
  `id`             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `job_id`         INT UNSIGNED NOT NULL,
  `candidate_id`   INT UNSIGNED NOT NULL,
  `applied_date`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `stage`          ENUM('applied','screening','shortlisted','phone_screen','assessment',
                        'technical_round','hr_round','final_round','offered','accepted',
                        'rejected','withdrawn','on_hold') DEFAULT 'applied',
  `cover_letter`   TEXT NULL,
  `is_internal`    TINYINT(1) DEFAULT 0,
  `score`          TINYINT NULL COMMENT '0-100',
  `remarks`        TEXT NULL,
  `rejected_reason`VARCHAR(500) NULL,
  `updated_by`     INT UNSIGNED NULL,
  `updated_at`     TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `job_candidate` (`job_id`,`candidate_id`),
  FOREIGN KEY (`job_id`)       REFERENCES `job_postings`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`candidate_id`) REFERENCES `candidates`(`id`)   ON DELETE CASCADE,
  INDEX `idx_app_stage` (`stage`)
) ENGINE=InnoDB;

CREATE TABLE `interviews` (
  `id`              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `application_id`  INT UNSIGNED NOT NULL,
  `round_number`    TINYINT NOT NULL DEFAULT 1,
  `interview_type`  ENUM('phone','video','in_person','technical','hr','panel') DEFAULT 'in_person',
  `scheduled_at`    DATETIME NOT NULL,
  `duration_mins`   SMALLINT DEFAULT 60,
  `location`        VARCHAR(200) NULL,
  `meeting_link`    VARCHAR(500) NULL,
  `status`          ENUM('scheduled','completed','cancelled','no_show','rescheduled') DEFAULT 'scheduled',
  `rating`          TINYINT NULL COMMENT '1-5',
  `feedback`        TEXT NULL,
  `recommendation`  ENUM('strong_yes','yes','neutral','no','strong_no') NULL,
  `created_at`      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`application_id`) REFERENCES `applications`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE `interview_panelists` (
  `interview_id` INT UNSIGNED NOT NULL,
  `employee_id`  INT UNSIGNED NOT NULL,
  `is_lead`      TINYINT(1) DEFAULT 0,
  `feedback`     TEXT NULL,
  `rating`       TINYINT NULL,
  PRIMARY KEY (`interview_id`,`employee_id`),
  FOREIGN KEY (`interview_id`) REFERENCES `interviews`(`id`)  ON DELETE CASCADE,
  FOREIGN KEY (`employee_id`)  REFERENCES `employees`(`id`)   ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE `offer_letters` (
  `id`              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `application_id`  INT UNSIGNED NOT NULL UNIQUE,
  `offered_ctc`     DECIMAL(12,2) NOT NULL,
  `designation_id`  INT UNSIGNED NULL,
  `joining_date`    DATE NOT NULL,
  `offer_date`      DATE NOT NULL,
  `expiry_date`     DATE NOT NULL,
  `status`          ENUM('draft','sent','accepted','declined','expired','revoked') DEFAULT 'draft',
  `accepted_at`     TIMESTAMP NULL,
  `letter_path`     VARCHAR(500) NULL,
  `created_at`      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`application_id`) REFERENCES `applications`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- SECTION 9: PERFORMANCE MANAGEMENT
-- ============================================================

CREATE TABLE `performance_cycles` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name`        VARCHAR(150) NOT NULL,
  `type`        ENUM('annual','semi_annual','quarterly','monthly','probation') DEFAULT 'annual',
  `period_from` DATE NOT NULL,
  `period_to`   DATE NOT NULL,
  `status`      ENUM('upcoming','active','completed','cancelled') DEFAULT 'upcoming',
  `created_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE `goals` (
  `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `employee_id`  INT UNSIGNED NOT NULL,
  `cycle_id`     INT UNSIGNED NULL,
  `title`        VARCHAR(255) NOT NULL,
  `description`  TEXT NULL,
  `category`     ENUM('individual','team','department','company') DEFAULT 'individual',
  `goal_type`    ENUM('kpi','okr','project','development') DEFAULT 'kpi',
  `weight`       DECIMAL(5,2) DEFAULT 0 COMMENT 'Weight percentage',
  `target_value` VARCHAR(200) NULL,
  `actual_value` VARCHAR(200) NULL,
  `achievement`  DECIMAL(5,2) NULL COMMENT 'Achievement percentage',
  `due_date`     DATE NULL,
  `status`       ENUM('draft','active','completed','cancelled') DEFAULT 'draft',
  `created_at`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`employee_id`) REFERENCES `employees`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`cycle_id`)    REFERENCES `performance_cycles`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE `performance_reviews` (
  `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `employee_id`   INT UNSIGNED NOT NULL,
  `cycle_id`      INT UNSIGNED NOT NULL,
  `reviewer_id`   INT UNSIGNED NOT NULL,
  `reviewer_type` ENUM('self','manager','peer','subordinate','hr') NOT NULL,
  `status`        ENUM('pending','in_progress','submitted','accepted','calibrated') DEFAULT 'pending',
  `self_rating`   DECIMAL(3,1) NULL,
  `manager_rating`DECIMAL(3,1) NULL,
  `final_rating`  DECIMAL(3,1) NULL,
  `performance_band` ENUM('exceptional','exceeds','meets','below','unacceptable') NULL,
  `strengths`     TEXT NULL,
  `improvements`  TEXT NULL,
  `comments`      TEXT NULL,
  `submitted_at`  TIMESTAMP NULL,
  `created_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`employee_id`) REFERENCES `employees`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`cycle_id`)    REFERENCES `performance_cycles`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`reviewer_id`) REFERENCES `employees`(`id`) ON DELETE RESTRICT,
  INDEX `idx_review_cycle_emp` (`cycle_id`,`employee_id`)
) ENGINE=InnoDB;

-- ============================================================
-- SECTION 10: LEARNING & DEVELOPMENT
-- ============================================================

CREATE TABLE `training_programs` (
  `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `title`         VARCHAR(255) NOT NULL,
  `description`   TEXT NULL,
  `category`      ENUM('technical','soft_skills','compliance','leadership','product','other') NOT NULL,
  `mode`          ENUM('online','offline','blended','self_paced') DEFAULT 'online',
  `duration_hours`DECIMAL(5,1) DEFAULT 0,
  `trainer`       VARCHAR(200) NULL,
  `cost`          DECIMAL(10,2) DEFAULT 0,
  `max_participants` SMALLINT NULL,
  `start_date`    DATE NULL,
  `end_date`      DATE NULL,
  `status`        ENUM('planned','ongoing','completed','cancelled') DEFAULT 'planned',
  `material_link` VARCHAR(500) NULL,
  `created_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE `training_enrollments` (
  `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `program_id`    INT UNSIGNED NOT NULL,
  `employee_id`   INT UNSIGNED NOT NULL,
  `enrolled_at`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `status`        ENUM('enrolled','in_progress','completed','failed','dropped') DEFAULT 'enrolled',
  `score`         DECIMAL(5,2) NULL,
  `completed_at`  TIMESTAMP NULL,
  `certificate_path` VARCHAR(500) NULL,
  `feedback`      TEXT NULL,
  UNIQUE KEY `program_employee` (`program_id`,`employee_id`),
  FOREIGN KEY (`program_id`)  REFERENCES `training_programs`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`employee_id`) REFERENCES `employees`(`id`)         ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- SECTION 11: ONBOARDING & OFFBOARDING
-- ============================================================

CREATE TABLE `onboarding_tasks` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `employee_id` INT UNSIGNED NOT NULL,
  `task_name`   VARCHAR(255) NOT NULL,
  `category`    ENUM('documents','it_setup','induction','training','compliance','other') DEFAULT 'other',
  `assigned_to` INT UNSIGNED NULL,
  `due_date`    DATE NULL,
  `status`      ENUM('pending','in_progress','completed','skipped') DEFAULT 'pending',
  `notes`       TEXT NULL,
  `completed_at` TIMESTAMP NULL,
  `completed_by` INT UNSIGNED NULL,
  `created_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`employee_id`) REFERENCES `employees`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE `exit_interviews` (
  `id`                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `employee_id`       INT UNSIGNED NOT NULL UNIQUE,
  `resignation_date`  DATE NOT NULL,
  `last_working_day`  DATE NOT NULL,
  `reason_primary`    ENUM('better_opportunity','compensation','work_environment','personal',
                          'relocation','higher_studies','health','other') NOT NULL,
  `reason_detail`     TEXT NULL,
  `would_rejoin`      TINYINT(1) NULL,
  `rating_management` TINYINT NULL,
  `rating_culture`    TINYINT NULL,
  `rating_growth`     TINYINT NULL,
  `rating_compensation` TINYINT NULL,
  `overall_rating`    TINYINT NULL,
  `suggestions`       TEXT NULL,
  `conducted_by`      INT UNSIGNED NULL,
  `conducted_at`      TIMESTAMP NULL,
  `created_at`        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`employee_id`) REFERENCES `employees`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- SECTION 12: ASSETS MANAGEMENT
-- ============================================================

CREATE TABLE `assets` (
  `id`             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `asset_tag`      VARCHAR(50) NOT NULL UNIQUE,
  `name`           VARCHAR(200) NOT NULL,
  `category`       ENUM('laptop','desktop','phone','accessory','vehicle','furniture','software','other') NOT NULL,
  `brand`          VARCHAR(100) NULL,
  `model`          VARCHAR(100) NULL,
  `serial_number`  VARCHAR(100) NULL,
  `purchase_date`  DATE NULL,
  `purchase_cost`  DECIMAL(12,2) NULL,
  `warranty_expiry`DATE NULL,
  `status`         ENUM('available','assigned','under_repair','retired','lost') DEFAULT 'available',
  `condition`      ENUM('new','good','fair','poor') DEFAULT 'good',
  `location_id`    INT UNSIGNED NULL,
  `notes`          TEXT NULL,
  `created_at`     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`location_id`) REFERENCES `locations`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE `asset_assignments` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `asset_id`    INT UNSIGNED NOT NULL,
  `employee_id` INT UNSIGNED NOT NULL,
  `assigned_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `returned_at` TIMESTAMP NULL,
  `condition_at_assign` ENUM('new','good','fair','poor') DEFAULT 'good',
  `condition_at_return` ENUM('new','good','fair','poor') NULL,
  `notes`       TEXT NULL,
  `assigned_by` INT UNSIGNED NULL,
  FOREIGN KEY (`asset_id`)    REFERENCES `assets`(`id`)    ON DELETE CASCADE,
  FOREIGN KEY (`employee_id`) REFERENCES `employees`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- SECTION 13: NOTIFICATIONS
-- ============================================================

CREATE TABLE `notifications` (
  `id`          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id`     INT UNSIGNED NOT NULL,
  `type`        VARCHAR(100) NOT NULL,
  `title`       VARCHAR(255) NOT NULL,
  `message`     TEXT NOT NULL,
  `data`        JSON NULL,
  `is_read`     TINYINT(1) DEFAULT 0,
  `read_at`     TIMESTAMP NULL,
  `channel`     ENUM('in_app','email','sms') DEFAULT 'in_app',
  `created_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `idx_notif_user_read` (`user_id`,`is_read`),
  INDEX `idx_notif_created`   (`created_at`)
) ENGINE=InnoDB;

-- ============================================================
-- SECTION 14: SYSTEM SETTINGS
-- ============================================================

CREATE TABLE `settings` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `key`         VARCHAR(200) NOT NULL UNIQUE,
  `value`       TEXT NULL,
  `type`        ENUM('string','number','boolean','json') DEFAULT 'string',
  `group`       VARCHAR(100) DEFAULT 'general',
  `description` TEXT NULL,
  `updated_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================
-- INDEXES FOR PERFORMANCE
-- ============================================================

CREATE INDEX `idx_emp_name`   ON `employees` (`first_name`, `last_name`);
CREATE INDEX `idx_emp_joined` ON `employees` (`date_joined`);
CREATE INDEX `idx_att_month`  ON `attendance` (`attendance_date`);

SET foreign_key_checks = 1;
