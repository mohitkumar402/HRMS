-- ============================================================
-- ENTERPRISE HRMS - SEED DATA
-- ============================================================

USE `hrms_db`;

-- ============================================================
-- ROLES
-- ============================================================
INSERT INTO `roles` (`name`, `slug`, `description`, `is_system`) VALUES
('Super Admin', 'super_admin', 'Full system access', 1),
('HR Manager', 'hr_manager', 'Full HR module access', 1),
('HR Executive', 'hr_executive', 'Limited HR access', 1),
('Manager', 'manager', 'Team manager access', 1),
('Employee', 'employee', 'Self-service access', 1),
('Finance', 'finance', 'Payroll and finance access', 1),
('Recruiter', 'recruiter', 'Recruitment module access', 1);

-- ============================================================
-- PERMISSIONS
-- ============================================================
INSERT INTO `permissions` (`module`, `action`, `slug`) VALUES
-- Employees
('employees','view','employees.view'),
('employees','create','employees.create'),
('employees','edit','employees.edit'),
('employees','delete','employees.delete'),
-- Attendance
('attendance','view','attendance.view'),
('attendance','manage','attendance.manage'),
-- Leave
('leave','view','leave.view'),
('leave','apply','leave.apply'),
('leave','approve','leave.approve'),
-- Payroll
('payroll','view','payroll.view'),
('payroll','manage','payroll.manage'),
('payroll','approve','payroll.approve'),
-- Recruitment
('recruitment','view','recruitment.view'),
('recruitment','manage','recruitment.manage'),
-- Performance
('performance','view','performance.view'),
('performance','manage','performance.manage'),
-- Reports
('reports','view','reports.view'),
('reports','export','reports.export'),
-- Settings
('settings','view','settings.view'),
('settings','manage','settings.manage');

-- Super Admin gets all permissions
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 1, id FROM `permissions`;

-- HR Manager permissions
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 2, id FROM `permissions` WHERE `module` IN ('employees','attendance','leave','payroll','recruitment','performance','reports');

-- HR Executive
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 3, id FROM `permissions` WHERE `module` IN ('employees','attendance','leave','recruitment') AND `action` IN ('view','create','edit');

-- Manager
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 4, id FROM `permissions` WHERE (`module` = 'employees' AND `action` = 'view')
   OR (`module` = 'attendance' AND `action` = 'view')
   OR (`module` = 'leave' AND `action` IN ('view','approve'))
   OR (`module` = 'performance' AND `action` IN ('view','manage'))
   OR (`module` = 'reports' AND `action` = 'view');

-- Employee
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 5, id FROM `permissions` WHERE (`module` = 'leave' AND `action` IN ('view','apply'))
   OR (`module` = 'attendance' AND `action` = 'view')
   OR (`module` = 'payroll' AND `action` = 'view')
   OR (`module` = 'performance' AND `action` = 'view');

-- ============================================================
-- DEPARTMENTS
-- ============================================================
INSERT INTO `departments` (`name`, `code`, `description`, `cost_center`) VALUES
('Human Resources', 'HR', 'HR Department', 'CC-001'),
('Information Technology', 'IT', 'IT Department', 'CC-002'),
('Finance & Accounts', 'FIN', 'Finance Department', 'CC-003'),
('Sales & Marketing', 'SALES', 'Sales Department', 'CC-004'),
('Operations', 'OPS', 'Operations Department', 'CC-005'),
('Legal & Compliance', 'LEGAL', 'Legal Department', 'CC-006'),
('Product Development', 'PROD', 'Product Department', 'CC-007'),
('Customer Success', 'CS', 'Customer Success', 'CC-008');

-- ============================================================
-- DESIGNATIONS
-- ============================================================
INSERT INTO `designations` (`title`, `code`, `department_id`, `level`) VALUES
-- HR
('Chief Human Resources Officer', 'CHRO', 1, 8),
('HR Manager', 'HRM', 1, 5),
('HR Executive', 'HRE', 1, 3),
('HR Recruiter', 'HRR', 1, 2),
-- IT
('Chief Technology Officer', 'CTO', 2, 8),
('Engineering Manager', 'EM', 2, 5),
('Senior Software Engineer', 'SSE', 2, 4),
('Software Engineer', 'SE', 2, 3),
('Junior Software Engineer', 'JSE', 2, 2),
-- Finance
('Chief Financial Officer', 'CFO', 3, 8),
('Finance Manager', 'FM', 3, 5),
('Accountant', 'ACC', 3, 3),
-- Sales
('VP Sales', 'VP_SALES', 4, 7),
('Sales Manager', 'SM', 4, 5),
('Sales Executive', 'SX', 4, 3),
-- Product
('Product Manager', 'PM', 7, 5),
('Product Designer', 'PD', 7, 3),
-- Operations
('COO', 'COO', 5, 8),
('Operations Manager', 'OM', 5, 5),
('Operations Executive', 'OE', 5, 3);

-- ============================================================
-- LOCATIONS
-- ============================================================
INSERT INTO `locations` (`name`, `code`, `address`, `city`, `state`, `country`, `pin_code`, `timezone`) VALUES
('Mumbai HQ', 'MUM', 'Plot 12, BKC, Bandra Kurla Complex', 'Mumbai', 'Maharashtra', 'India', '400051', 'Asia/Kolkata'),
('Delhi Office', 'DEL', '3rd Floor, Connaught Place', 'New Delhi', 'Delhi', 'India', '110001', 'Asia/Kolkata'),
('Bangalore Office', 'BLR', 'Whitefield, ITPL Road', 'Bangalore', 'Karnataka', 'India', '560066', 'Asia/Kolkata'),
('Hyderabad Office', 'HYD', 'Hitech City, Madhapur', 'Hyderabad', 'Telangana', 'India', '500081', 'Asia/Kolkata'),
('Remote', 'REM', 'Work From Home', 'N/A', 'N/A', 'India', '000000', 'Asia/Kolkata');

-- ============================================================
-- USERS (Admin first)
-- ============================================================
-- Password for all: Admin@123 (bcrypt hash)
INSERT INTO `users` (`email`, `password_hash`, `role_id`, `is_active`) VALUES
('admin@hrms.com', '$2y$12$LKb6h3KLZ9dG.VoHPSmIpOPrLN6KPvLNwNfF.S4mRILGvp7q5MFKW', 1, 1),
('hr@hrms.com', '$2y$12$LKb6h3KLZ9dG.VoHPSmIpOPrLN6KPvLNwNfF.S4mRILGvp7q5MFKW', 2, 1),
('hr2@hrms.com', '$2y$12$LKb6h3KLZ9dG.VoHPSmIpOPrLN6KPvLNwNfF.S4mRILGvp7q5MFKW', 3, 1),
('manager@hrms.com', '$2y$12$LKb6h3KLZ9dG.VoHPSmIpOPrLN6KPvLNwNfF.S4mRILGvp7q5MFKW', 4, 1),
('john.doe@hrms.com', '$2y$12$LKb6h3KLZ9dG.VoHPSmIpOPrLN6KPvLNwNfF.S4mRILGvp7q5MFKW', 5, 1),
('jane.smith@hrms.com', '$2y$12$LKb6h3KLZ9dG.VoHPSmIpOPrLN6KPvLNwNfF.S4mRILGvp7q5MFKW', 5, 1),
('raj.kumar@hrms.com', '$2y$12$LKb6h3KLZ9dG.VoHPSmIpOPrLN6KPvLNwNfF.S4mRILGvp7q5MFKW', 5, 1),
('priya.sharma@hrms.com', '$2y$12$LKb6h3KLZ9dG.VoHPSmIpOPrLN6KPvLNwNfF.S4mRILGvp7q5MFKW', 5, 1),
('finance@hrms.com', '$2y$12$LKb6h3KLZ9dG.VoHPSmIpOPrLN6KPvLNwNfF.S4mRILGvp7q5MFKW', 6, 1),
('recruiter@hrms.com', '$2y$12$LKb6h3KLZ9dG.VoHPSmIpOPrLN6KPvLNwNfF.S4mRILGvp7q5MFKW', 7, 1);

-- ============================================================
-- EMPLOYEES
-- ============================================================
INSERT INTO `employees` (
  `employee_id`,`user_id`,`first_name`,`last_name`,`gender`,`date_of_birth`,`work_email`,
  `personal_phone`,`department_id`,`designation_id`,`location_id`,`manager_id`,
  `employment_type`,`employment_status`,`date_joined`,`confirmation_date`,
  `pan_number`,`bank_name`,`bank_account_number`,`bank_ifsc_code`
) VALUES
('EMP0001',1,'Admin','Super','male','1985-01-15','admin@hrms.com','9876543210',1,2,1,NULL,'full_time','active','2020-01-01','2020-04-01','ABCDE1234F','HDFC Bank','12345678901','HDFC0001234'),
('EMP0002',2,'Ravi','Verma','male','1988-03-20','hr@hrms.com','9876543211',1,2,1,1,'full_time','active','2020-02-01','2020-05-01','BCDEF2345G','ICICI Bank','23456789012','ICIC0001234'),
('EMP0003',3,'Meena','Patel','female','1990-07-10','hr2@hrms.com','9876543212',1,3,1,2,'full_time','active','2021-01-15','2021-04-15','CDEFG3456H','SBI','34567890123','SBIN0001234'),
('EMP0004',4,'Arjun','Nair','male','1986-11-25','manager@hrms.com','9876543213',2,6,3,1,'full_time','active','2019-06-01','2019-09-01','DEFGH4567I','Axis Bank','45678901234','UTIB0001234'),
('EMP0005',5,'John','Doe','male','1992-05-14','john.doe@hrms.com','9876543214',2,8,3,4,'full_time','active','2022-03-01','2022-06-01','EFGHI5678J','HDFC Bank','56789012345','HDFC0005678'),
('EMP0006',6,'Jane','Smith','female','1993-08-22','jane.smith@hrms.com','9876543215',2,8,3,4,'full_time','active','2022-05-15','2022-08-15','FGHIJ6789K','ICICI Bank','67890123456','ICIC0005678'),
('EMP0007',7,'Raj','Kumar','male','1991-12-30','raj.kumar@hrms.com','9876543216',4,15,1,1,'full_time','active','2021-08-01','2021-11-01','GHIJK7890L','SBI','78901234567','SBIN0005678'),
('EMP0008',8,'Priya','Sharma','female','1994-04-05','priya.sharma@hrms.com','9876543217',7,17,3,1,'full_time','active','2023-01-10','2023-04-10','HIJKL8901M','HDFC Bank','89012345678','HDFC0009012'),
('EMP0009',9,'Kiran','Reddy','male','1987-09-18','finance@hrms.com','9876543218',3,12,1,1,'full_time','active','2020-04-01','2020-07-01','IJKLM9012N','Axis Bank','90123456789','UTIB0009012'),
('EMP0010',10,'Amit','Joshi','male','1989-02-28','recruiter@hrms.com','9876543219',1,4,1,2,'full_time','active','2021-10-01','2022-01-01','JKLMN0123O','ICICI Bank','01234567890','ICIC0009012');

-- Update department heads
UPDATE `departments` SET `head_emp_id` = 2 WHERE `code` = 'HR';
UPDATE `departments` SET `head_emp_id` = 4 WHERE `code` = 'IT';
UPDATE `departments` SET `head_emp_id` = 9 WHERE `code` = 'FIN';
UPDATE `departments` SET `head_emp_id` = 7 WHERE `code` = 'SALES';

-- ============================================================
-- SHIFTS
-- ============================================================
INSERT INTO `shifts` (`name`, `code`, `start_time`, `end_time`, `grace_in_mins`, `break_mins`, `shift_type`) VALUES
('General Shift', 'GEN', '09:00:00', '18:00:00', 15, 60, 'general'),
('Morning Shift', 'MOR', '06:00:00', '14:00:00', 10, 30, 'morning'),
('Evening Shift', 'EVE', '14:00:00', '22:00:00', 10, 30, 'evening'),
('Night Shift', 'NGT', '22:00:00', '06:00:00', 10, 30, 'night'),
('Flexible Shift', 'FLEX', '08:00:00', '17:00:00', 30, 60, 'general');

-- Assign shifts to employees
INSERT INTO `shift_assignments` (`employee_id`, `shift_id`, `from_date`) VALUES
(1,1,'2020-01-01'),(2,1,'2020-02-01'),(3,1,'2021-01-15'),
(4,1,'2019-06-01'),(5,1,'2022-03-01'),(6,1,'2022-05-15'),
(7,1,'2021-08-01'),(8,5,'2023-01-10'),(9,1,'2020-04-01'),(10,1,'2021-10-01');

-- ============================================================
-- HOLIDAYS (2025)
-- ============================================================
INSERT INTO `holidays` (`name`, `date`, `type`) VALUES
('New Year Day', '2025-01-01', 'national'),
('Republic Day', '2025-01-26', 'national'),
('Holi', '2025-03-14', 'national'),
('Good Friday', '2025-04-18', 'national'),
('Eid ul-Fitr', '2025-03-31', 'national'),
('Ambedkar Jayanti', '2025-04-14', 'national'),
('Maharashtra Day', '2025-05-01', 'regional'),
('Independence Day', '2025-08-15', 'national'),
('Ganesh Chaturthi', '2025-08-27', 'regional'),
('Gandhi Jayanti', '2025-10-02', 'national'),
('Dussehra', '2025-10-02', 'national'),
('Diwali', '2025-10-20', 'national'),
('Diwali Holiday', '2025-10-21', 'national'),
('Christmas', '2025-12-25', 'national'),
('New Year Eve', '2025-12-31', 'optional');

-- ============================================================
-- LEAVE TYPES
-- ============================================================
INSERT INTO `leave_types` (`name`, `code`, `annual_allocation`, `carry_forward_limit`, `is_paid`, `color`) VALUES
('Casual Leave', 'CL', 12, 3, 1, '#3B82F6'),
('Sick Leave', 'SL', 12, 0, 1, '#EF4444'),
('Earned Leave', 'EL', 21, 10, 1, '#10B981'),
('Maternity Leave', 'ML', 182, 0, 1, '#EC4899'),
('Paternity Leave', 'PL', 15, 0, 1, '#8B5CF6'),
('Loss of Pay', 'LOP', 0, 0, 0, '#6B7280'),
('Comp Off', 'CO', 0, 5, 1, '#F59E0B'),
('Bereavement Leave', 'BL', 3, 0, 1, '#374151');

-- Leave balances for current year
INSERT INTO `leave_balances` (`employee_id`, `leave_type_id`, `year`, `allocated`, `used`, `carry_forward`) VALUES
(1,1,2025,12,2,0),(1,2,2025,12,1,0),(1,3,2025,21,3,5),
(2,1,2025,12,3,0),(2,2,2025,12,2,0),(2,3,2025,21,5,3),
(3,1,2025,12,1,0),(3,2,2025,12,0,0),(3,3,2025,21,2,4),
(4,1,2025,12,4,0),(4,2,2025,12,1,0),(4,3,2025,21,8,2),
(5,1,2025,12,2,0),(5,2,2025,12,3,0),(5,3,2025,21,4,0),
(6,1,2025,12,5,0),(6,2,2025,12,1,0),(6,3,2025,21,3,0),
(7,1,2025,12,3,0),(7,2,2025,12,2,0),(7,3,2025,21,6,1),
(8,1,2025,12,1,0),(8,2,2025,12,0,0),(8,3,2025,21,2,0),
(9,1,2025,12,2,0),(9,2,2025,12,1,0),(9,3,2025,21,4,3),
(10,1,2025,12,1,0),(10,2,2025,12,0,0),(10,3,2025,21,1,0);

-- ============================================================
-- SALARY STRUCTURES
-- ============================================================
INSERT INTO `salary_structures` (
  `employee_id`,`effective_date`,`ctc_annual`,`basic_monthly`,`hra_monthly`,
  `special_allowance`,`pf_employee`,`pf_employer`,`professional_tax`,`tds_monthly`,
  `gross_monthly`,`net_monthly`,`status`
) VALUES
(1,'2024-04-01',2400000,100000,40000,60000,12000,12000,2500,18000,200000,167500,'active'),
(2,'2024-04-01',1800000,75000,30000,45000,9000,9000,2500,12000,150000,126500,'active'),
(3,'2024-04-01',900000,37500,15000,22500,4500,4500,2500,3500,75000,64500,'active'),
(4,'2024-04-01',2100000,87500,35000,52500,10500,10500,2500,15000,175000,147000,'active'),
(5,'2024-04-01',1200000,50000,20000,30000,6000,6000,2500,7000,100000,84500,'active'),
(6,'2024-04-01',1080000,45000,18000,27000,5400,5400,2500,5500,90000,76600,'active'),
(7,'2024-04-01',1500000,62500,25000,37500,7500,7500,2500,10000,125000,105000,'active'),
(8,'2024-04-01',960000,40000,16000,24000,4800,4800,2500,4000,80000,68700,'active'),
(9,'2024-04-01',1440000,60000,24000,36000,7200,7200,2500,9000,120000,101300,'active'),
(10,'2024-04-01',840000,35000,14000,21000,4200,4200,2500,2500,70000,60800,'active');

-- ============================================================
-- SAMPLE ATTENDANCE (Last 7 days for active employees)
-- ============================================================
INSERT INTO `attendance` (`employee_id`,`attendance_date`,`check_in`,`check_out`,`total_hours`,`status`,`shift_id`) VALUES
(1,CURDATE()-INTERVAL 6 DAY,CONCAT(CURDATE()-INTERVAL 6 DAY,' 09:05:00'),CONCAT(CURDATE()-INTERVAL 6 DAY,' 18:10:00'),9.08,'present',1),
(1,CURDATE()-INTERVAL 5 DAY,CONCAT(CURDATE()-INTERVAL 5 DAY,' 09:00:00'),CONCAT(CURDATE()-INTERVAL 5 DAY,' 18:30:00'),9.50,'present',1),
(1,CURDATE()-INTERVAL 4 DAY,NULL,NULL,0,'absent',1),
(1,CURDATE()-INTERVAL 3 DAY,CONCAT(CURDATE()-INTERVAL 3 DAY,' 09:15:00'),CONCAT(CURDATE()-INTERVAL 3 DAY,' 18:00:00'),8.75,'late',1),
(1,CURDATE()-INTERVAL 2 DAY,CONCAT(CURDATE()-INTERVAL 2 DAY,' 09:00:00'),CONCAT(CURDATE()-INTERVAL 2 DAY,' 18:00:00'),9.00,'present',1),
(5,CURDATE()-INTERVAL 6 DAY,CONCAT(CURDATE()-INTERVAL 6 DAY,' 09:10:00'),CONCAT(CURDATE()-INTERVAL 6 DAY,' 18:20:00'),9.17,'present',1),
(5,CURDATE()-INTERVAL 5 DAY,CONCAT(CURDATE()-INTERVAL 5 DAY,' 09:00:00'),CONCAT(CURDATE()-INTERVAL 5 DAY,' 18:00:00'),9.00,'present',1),
(5,CURDATE()-INTERVAL 2 DAY,CONCAT(CURDATE()-INTERVAL 2 DAY,' 09:00:00'),CONCAT(CURDATE()-INTERVAL 2 DAY,' 18:00:00'),9.00,'wfh',1);

-- ============================================================
-- PAYROLL COMPONENTS
-- ============================================================
INSERT INTO `payroll_components` (`name`, `code`, `type`, `calculation`, `formula`, `is_taxable`) VALUES
('Basic Salary', 'BASIC', 'earning', 'percentage_of_ctc', '50', 1),
('House Rent Allowance', 'HRA', 'earning', 'percentage_of_basic', '40', 1),
('Special Allowance', 'SPEC', 'earning', 'formula', 'CTC - BASIC - HRA - PF_EMP - ESI_EMP', 1),
('Provident Fund (Employee)', 'PF_EMP', 'deduction', 'percentage_of_basic', '12', 0),
('Provident Fund (Employer)', 'PF_EMPR', 'benefit', 'percentage_of_basic', '12', 0),
('ESI (Employee)', 'ESI_EMP', 'deduction', 'percentage_of_basic', '0.75', 0),
('ESI (Employer)', 'ESI_EMPR', 'benefit', 'percentage_of_basic', '3.25', 0),
('Professional Tax', 'PT', 'deduction', 'fixed', '200', 0),
('TDS / Income Tax', 'TDS', 'tax', 'formula', 'As per IT slabs', 1),
('Performance Bonus', 'BONUS', 'earning', 'fixed', '0', 1),
('Travel Allowance', 'TA', 'earning', 'fixed', '1500', 0),
('Medical Allowance', 'MA', 'earning', 'fixed', '1250', 0);

-- ============================================================
-- SAMPLE JOB POSTINGS
-- ============================================================
INSERT INTO `job_postings` (`requisition_number`,`title`,`department_id`,`designation_id`,`location_id`,`employment_type`,`experience_min`,`experience_max`,`salary_min`,`salary_max`,`openings`,`description`,`requirements`,`skills_required`,`posted_date`,`closing_date`,`status`,`posted_by`) VALUES
('REQ-2025-001','Senior Software Engineer',2,7,3,'full_time',3,6,800000,1500000,2,'We are looking for a Senior Software Engineer to join our growing tech team. You will be responsible for designing, developing, and maintaining high-quality software solutions.','3-6 years of experience in software development. Strong knowledge of algorithms and data structures. Experience with agile methodologies.','["Node.js","React","MySQL","Docker","AWS"]','2025-01-15','2025-03-15','open',3),
('REQ-2025-002','HR Executive',1,3,1,'full_time',1,3,400000,700000,1,'Join our HR team as HR Executive. Handle recruitment, onboarding, employee relations, and HR operations.','1-3 years of HR experience. Knowledge of labor laws. Good communication skills.','["Recruitment","HRIS","Communication","Excel"]','2025-01-20','2025-02-28','open',2),
('REQ-2025-003','Sales Manager',4,14,1,'full_time',5,10,1000000,1800000,1,'Lead our sales team to drive revenue growth. Manage a team of sales executives and develop sales strategies.','5+ years in sales with 2+ years in management. Proven track record of achieving targets.','["B2B Sales","Team Management","CRM","Negotiation"]','2025-02-01','2025-04-01','open',2),
('REQ-2025-004','Product Designer',7,17,3,'full_time',2,5,700000,1200000,1,'Creative Product Designer needed to design user-centric digital products.','2-5 years UI/UX experience. Portfolio required.','["Figma","UX Research","Prototyping","Design Systems"]','2025-02-05','2025-03-31','open',2);

-- ============================================================
-- SAMPLE CANDIDATES
-- ============================================================
INSERT INTO `candidates` (`first_name`,`last_name`,`email`,`phone`,`current_company`,`current_title`,`experience_years`,`current_ctc`,`expected_ctc`,`notice_period`,`source`,`skills`) VALUES
('Rohit','Malhotra','rohit.m@gmail.com','9811223344','TechCorp','Software Engineer',3,800000,1200000,30,'linkedin','["Node.js","React","MySQL"]'),
('Sneha','Iyer','sneha.i@gmail.com','9822334455','WebSolutions','Frontend Developer',4,750000,1100000,45,'job_portal','["React","Vue.js","CSS","Figma"]'),
('Vikram','Singh','vikram.s@gmail.com','9833445566','StartupXYZ','Full Stack Developer',5,1000000,1500000,60,'referral','["Node.js","React","AWS","Docker"]'),
('Anita','Desai','anita.d@gmail.com','9844556677','N/A','Fresher',0.5,0,600000,0,'campus','["Python","React","SQL"]'),
('Suresh','Pillai','suresh.p@gmail.com','9855667788','OldCorp','HR Executive',2,450000,650000,30,'direct','["Recruitment","Excel","Communication"]');

-- ============================================================
-- SAMPLE APPLICATIONS
-- ============================================================
INSERT INTO `applications` (`job_id`,`candidate_id`,`stage`,`score`) VALUES
(1,1,'technical_round',75),
(1,3,'final_round',88),
(1,4,'screening',60),
(2,5,'hr_round',80),
(4,2,'shortlisted',72),
(3,1,'phone_screen',65);

-- ============================================================
-- PERFORMANCE CYCLES
-- ============================================================
INSERT INTO `performance_cycles` (`name`,`type`,`period_from`,`period_to`,`status`) VALUES
('Annual Review 2024-25', 'annual', '2024-04-01', '2025-03-31', 'active'),
('H1 Review 2025', 'semi_annual', '2025-04-01', '2025-09-30', 'upcoming'),
('Q1 Review 2025', 'quarterly', '2025-01-01', '2025-03-31', 'active');

-- ============================================================
-- SAMPLE GOALS
-- ============================================================
INSERT INTO `goals` (`employee_id`,`cycle_id`,`title`,`description`,`category`,`goal_type`,`weight`,`target_value`,`due_date`,`status`) VALUES
(5,1,'Deliver 3 major features','Complete and ship 3 major product features with zero critical bugs','individual','kpi',30,'3 features delivered','2025-03-31','active'),
(5,1,'Reduce bug count by 50%','Reduce open bug count from 100 to 50','individual','kpi',20,'50% reduction','2025-03-31','active'),
(5,1,'Complete AWS Certification','Obtain AWS Developer Associate certification','individual','okr',15,'Certificate obtained','2025-03-31','active'),
(5,3,'Q1 Sprint Velocity','Achieve 95% sprint velocity in Q1','individual','kpi',20,'95% velocity','2025-03-31','active'),
(7,1,'Achieve Sales Target','Meet 100% of annual sales quota','individual','kpi',40,'₹2Cr revenue','2025-03-31','active'),
(7,1,'Customer Acquisition','Acquire 50 new enterprise clients','individual','kpi',30,'50 new clients','2025-03-31','active');

-- ============================================================
-- TRAINING PROGRAMS
-- ============================================================
INSERT INTO `training_programs` (`title`,`description`,`category`,`mode`,`duration_hours`,`trainer`,`cost`,`max_participants`,`start_date`,`end_date`,`status`) VALUES
('React Advanced Patterns','Advanced React patterns and best practices','technical','online',20,'Internal Team',0,30,'2025-02-01','2025-02-28','ongoing'),
('Leadership Excellence','Leadership skills for managers','leadership','offline',16,'External Trainer',5000,20,'2025-03-01','2025-03-02','planned'),
('POSH Awareness','Prevention of Sexual Harassment at Workplace','compliance','online',4,'HR Team',0,100,'2025-01-15','2025-01-15','completed'),
('Agile & Scrum','Agile methodology and Scrum practices','technical','blended',12,'External Trainer',3000,25,'2025-02-15','2025-02-16','planned');

-- ============================================================
-- SETTINGS
-- ============================================================
INSERT INTO `settings` (`key`, `value`, `type`, `group`, `description`) VALUES
('company_name', 'TechVision Pvt Ltd', 'string', 'general', 'Company name'),
('company_logo', '', 'string', 'general', 'Logo path'),
('company_email', 'contact@techvision.com', 'string', 'general', 'Company email'),
('company_phone', '+91-22-4567-8900', 'string', 'general', 'Company phone'),
('company_address', 'Plot 12, BKC, Mumbai 400051', 'string', 'general', 'Company address'),
('company_website', 'https://techvision.com', 'string', 'general', 'Website URL'),
('financial_year_start', '04', 'string', 'payroll', 'Financial year start month'),
('payroll_day', '25', 'number', 'payroll', 'Payroll processing day'),
('pf_applicable', 'true', 'boolean', 'payroll', 'PF applicable'),
('esi_applicable', 'true', 'boolean', 'payroll', 'ESI applicable'),
('working_days', '5', 'number', 'attendance', 'Working days per week (5 or 6)'),
('work_hours_per_day', '9', 'number', 'attendance', 'Standard work hours'),
('late_mark_minutes', '15', 'number', 'attendance', 'Grace period for late mark'),
('leave_year_start', '01', 'string', 'leave', 'Leave year start month'),
('leave_auto_approve_days', '3', 'number', 'leave', 'Auto-approve if manager not actioned in days'),
('currency', 'INR', 'string', 'general', 'Default currency'),
('date_format', 'DD/MM/YYYY', 'string', 'general', 'Display date format'),
('timezone', 'Asia/Kolkata', 'string', 'general', 'Default timezone'),
('email_notifications', 'true', 'boolean', 'notifications', 'Enable email notifications'),
('smtp_host', 'smtp.gmail.com', 'string', 'email', 'SMTP host'),
('smtp_port', '587', 'number', 'email', 'SMTP port'),
('smtp_from', 'noreply@techvision.com', 'string', 'email', 'From email address');
