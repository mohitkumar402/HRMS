<?php
// ============================================================
// ENTERPRISE HRMS - EMPLOYEE CONTROLLER
// ============================================================

class EmployeeController {

    // GET /api/employees
    public static function index(): void {
        $user = AuthMiddleware::authenticate();
        AuthMiddleware::requirePermission('employees.view');

        $page   = max(1, (int)($_GET['page'] ?? 1));
        $limit  = min((int)($_GET['limit'] ?? DEFAULT_PAGE_SIZE), MAX_PAGE_SIZE);
        $offset = ($page - 1) * $limit;

        $where = ['1=1'];
        $params = [];

        if (!empty($_GET['search'])) {
            $s = '%' . $_GET['search'] . '%';
            $where[] = "(e.first_name LIKE ? OR e.last_name LIKE ? OR e.employee_id LIKE ? OR e.work_email LIKE ?)";
            $params  = array_merge($params, [$s, $s, $s, $s]);
        }
        if (!empty($_GET['department_id'])) { $where[] = 'e.department_id = ?'; $params[] = $_GET['department_id']; }
        if (!empty($_GET['designation_id'])) { $where[] = 'e.designation_id = ?'; $params[] = $_GET['designation_id']; }
        if (!empty($_GET['employment_status'])) { $where[] = 'e.employment_status = ?'; $params[] = $_GET['employment_status']; }
        if (!empty($_GET['employment_type'])) { $where[] = 'e.employment_type = ?'; $params[] = $_GET['employment_type']; }
        if (!empty($_GET['location_id'])) { $where[] = 'e.location_id = ?'; $params[] = $_GET['location_id']; }
        if (!empty($_GET['manager_id'])) { $where[] = 'e.manager_id = ?'; $params[] = $_GET['manager_id']; }

        $whereStr = implode(' AND ', $where);
        $sortBy   = in_array($_GET['sort_by'] ?? '', ['first_name','date_joined','employee_id']) ? $_GET['sort_by'] : 'e.first_name';
        $sortDir  = strtoupper($_GET['sort_dir'] ?? 'ASC') === 'DESC' ? 'DESC' : 'ASC';

        $countStmt = db()->prepare("SELECT COUNT(*) FROM employees e WHERE {$whereStr}");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $sql = "SELECT e.id, e.employee_id, e.first_name, e.last_name, e.display_name,
                e.gender, e.work_email, e.work_phone, e.personal_phone,
                e.date_joined, e.confirmation_date, e.employment_type, e.employment_status,
                e.profile_photo, e.date_of_birth,
                d.name as department_name, d.id as department_id,
                des.title as designation_title, des.id as designation_id,
                l.name as location_name, l.id as location_id,
                CONCAT(m.first_name,' ',m.last_name) as manager_name, m.id as manager_id,
                m.employee_id as manager_emp_id
                FROM employees e
                LEFT JOIN departments d ON d.id = e.department_id
                LEFT JOIN designations des ON des.id = e.designation_id
                LEFT JOIN locations l ON l.id = e.location_id
                LEFT JOIN employees m ON m.id = e.manager_id
                WHERE {$whereStr}
                ORDER BY {$sortBy} {$sortDir}
                LIMIT {$limit} OFFSET {$offset}";

        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        $employees = $stmt->fetchAll();

        Response::paginated($employees, $total, $page, $limit);
    }

    // POST /api/employees
    public static function store(): void {
        $user = AuthMiddleware::authenticate();
        AuthMiddleware::requirePermission('employees.create');

        $body = json_decode(file_get_contents('php://input'), true) ?? [];

        $v = Validator::make($body, [
            'first_name'  => 'required|min:2|max:100',
            'last_name'   => 'required|min:2|max:100',
            'work_email'  => 'required|email',
            'gender'      => 'required|in:male,female,other,prefer_not_to_say',
            'date_joined' => 'required|date',
            'password'    => 'required|min:8',
        ]);
        if ($v->fails()) Response::validationError($v->errors());

        // Check duplicate email
        $check = db()->prepare("SELECT id FROM employees WHERE work_email = ?");
        $check->execute([$body['work_email']]);
        if ($check->fetch()) Response::error('Work email already exists', 409);

        $db = Database::getInstance();
        $db->beginTransaction();
        try {
            // Create user account
            $passwordHash = password_hash($body['password'], PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
            $roleId = (int)($body['role_id'] ?? 5); // Default: Employee

            $userStmt = db()->prepare("INSERT INTO users (email, password_hash, role_id) VALUES (?, ?, ?)");
            $userStmt->execute([$body['work_email'], $passwordHash, $roleId]);
            $userId = db()->lastInsertId();

            // Generate employee ID
            $lastEmpStmt = db()->query("SELECT employee_id FROM employees ORDER BY id DESC LIMIT 1");
            $lastEmp = $lastEmpStmt->fetch();
            $nextNum = $lastEmp ? (int)substr($lastEmp['employee_id'], 3) + 1 : 1;
            $employeeId = 'EMP' . str_pad($nextNum, 4, '0', STR_PAD_LEFT);

            // Create employee
            $fields = ['employee_id','user_id','first_name','middle_name','last_name','gender',
                       'date_of_birth','nationality','marital_status','blood_group',
                       'personal_email','work_email','personal_phone','work_phone',
                       'emergency_contact_name','emergency_contact_phone','emergency_contact_relation',
                       'current_address','permanent_address',
                       'department_id','designation_id','location_id','manager_id',
                       'employment_type','employment_status','date_joined','confirmation_date','probation_end_date',
                       'pan_number','aadhar_number','passport_number','passport_expiry',
                       'pf_number','esi_number','uan_number',
                       'bank_name','bank_account_number','bank_ifsc_code','bank_branch','bio'];

            $insertData = ['employee_id' => $employeeId, 'user_id' => $userId];
            foreach ($fields as $f) {
                if ($f !== 'employee_id' && $f !== 'user_id' && isset($body[$f])) {
                    $insertData[$f] = $body[$f] ?: null;
                }
            }

            $cols    = implode(',', array_keys($insertData));
            $placeholders = implode(',', array_fill(0, count($insertData), '?'));
            db()->prepare("INSERT INTO employees ({$cols}) VALUES ({$placeholders})")->execute(array_values($insertData));
            $empId = db()->lastInsertId();

            // Log history
            db()->prepare("INSERT INTO employment_history (employee_id, department_id, designation_id, employment_type, effective_date, change_type, changed_by) VALUES (?,?,?,?,?,?,?)")
                ->execute([$empId, $body['department_id'] ?? null, $body['designation_id'] ?? null, $body['employment_type'] ?? 'full_time', $body['date_joined'], 'joining', $user['id']]);

            // Initialize leave balances for current year
            $leaveTypes = db()->query("SELECT id, annual_allocation FROM leave_types WHERE is_active = 1")->fetchAll();
            $year = date('Y');
            foreach ($leaveTypes as $lt) {
                db()->prepare("INSERT INTO leave_balances (employee_id, leave_type_id, year, allocated) VALUES (?,?,?,?)")
                    ->execute([$empId, $lt['id'], $year, $lt['annual_allocation']]);
            }

            $db->commit();

            AuthMiddleware::audit('create_employee', 'employees', $empId, null, $insertData);

            $stmt = db()->prepare("SELECT e.*, d.name as department_name, des.title as designation_title FROM employees e
                LEFT JOIN departments d ON d.id = e.department_id
                LEFT JOIN designations des ON des.id = e.designation_id WHERE e.id = ?");
            $stmt->execute([$empId]);
            Response::created($stmt->fetch(), "Employee {$employeeId} created successfully");
        } catch (Exception $e) {
            $db->rollBack();
            Response::serverError(APP_ENV === 'development' ? $e->getMessage() : 'Failed to create employee');
        }
    }

    // GET /api/employees/{id}
    public static function show(int $id): void {
        AuthMiddleware::authenticate();
        AuthMiddleware::requirePermission('employees.view');

        $stmt = db()->prepare("SELECT e.*,
            d.name as department_name, des.title as designation_title,
            l.name as location_name, l.city as location_city,
            CONCAT(m.first_name,' ',m.last_name) as manager_name, m.employee_id as manager_emp_id,
            r.slug as role_slug, r.name as role_name
            FROM employees e
            LEFT JOIN departments d ON d.id = e.department_id
            LEFT JOIN designations des ON des.id = e.designation_id
            LEFT JOIN locations l ON l.id = e.location_id
            LEFT JOIN employees m ON m.id = e.manager_id
            LEFT JOIN users u ON u.id = e.user_id
            LEFT JOIN roles r ON r.id = u.role_id
            WHERE e.id = ?");
        $stmt->execute([$id]);
        $emp = $stmt->fetch();
        if (!$emp) Response::notFound('Employee not found');

        // Education, experience
        $edu = db()->prepare("SELECT * FROM employee_education WHERE employee_id = ? ORDER BY year_from DESC");
        $edu->execute([$id]);

        $exp = db()->prepare("SELECT * FROM employee_experience WHERE employee_id = ? ORDER BY from_date DESC");
        $exp->execute([$id]);

        $docs = db()->prepare("SELECT id, document_type, document_name, expiry_date, is_verified, uploaded_at FROM employee_documents WHERE employee_id = ?");
        $docs->execute([$id]);

        // Leave balances
        $lb = db()->prepare("SELECT lb.*, lt.name as leave_type_name, lt.code as leave_code, lt.color FROM leave_balances lb JOIN leave_types lt ON lt.id = lb.leave_type_id WHERE lb.employee_id = ? AND lb.year = YEAR(CURDATE())");
        $lb->execute([$id]);

        // Current assets
        $assets = db()->prepare("SELECT a.asset_tag, a.name, a.category, aa.assigned_at FROM asset_assignments aa JOIN assets a ON a.id = aa.asset_id WHERE aa.employee_id = ? AND aa.returned_at IS NULL");
        $assets->execute([$id]);

        $emp['education']     = $edu->fetchAll();
        $emp['experience']    = $exp->fetchAll();
        $emp['documents']     = $docs->fetchAll();
        $emp['leave_balances']= $lb->fetchAll();
        $emp['assets']        = $assets->fetchAll();

        Response::success($emp);
    }

    // PUT /api/employees/{id}
    public static function update(int $id): void {
        $user = AuthMiddleware::authenticate();
        AuthMiddleware::requirePermission('employees.edit');

        $stmt = db()->prepare("SELECT * FROM employees WHERE id = ?");
        $stmt->execute([$id]);
        $emp = $stmt->fetch();
        if (!$emp) Response::notFound();

        $body = json_decode(file_get_contents('php://input'), true) ?? [];

        $updatable = ['first_name','middle_name','last_name','gender','date_of_birth','nationality',
                      'marital_status','blood_group','personal_email','personal_phone','work_phone',
                      'emergency_contact_name','emergency_contact_phone','emergency_contact_relation',
                      'current_address','permanent_address','department_id','designation_id',
                      'location_id','manager_id','employment_type','employment_status',
                      'confirmation_date','last_working_day','probation_end_date',
                      'pan_number','aadhar_number','passport_number','passport_expiry',
                      'pf_number','esi_number','uan_number',
                      'bank_name','bank_account_number','bank_ifsc_code','bank_branch','bio'];

        $updates = [];
        $params  = [];
        foreach ($updatable as $f) {
            if (array_key_exists($f, $body)) {
                $updates[] = "{$f} = ?";
                $params[]  = $body[$f] ?: null;
            }
        }
        if (empty($updates)) Response::error('No fields to update');

        $params[] = $id;
        db()->prepare("UPDATE employees SET " . implode(', ', $updates) . " WHERE id = ?")->execute($params);

        AuthMiddleware::audit('update_employee', 'employees', $id, $emp, $body);

        $stmt = db()->prepare("SELECT e.*, d.name as department_name, des.title as designation_title FROM employees e LEFT JOIN departments d ON d.id = e.department_id LEFT JOIN designations des ON des.id = e.designation_id WHERE e.id = ?");
        $stmt->execute([$id]);
        Response::success($stmt->fetch(), 'Employee updated');
    }

    // DELETE /api/employees/{id} (soft delete = terminate)
    public static function destroy(int $id): void {
        AuthMiddleware::authenticate();
        AuthMiddleware::requirePermission('employees.delete');

        db()->prepare("UPDATE employees SET employment_status = 'terminated', last_working_day = CURDATE() WHERE id = ?")
            ->execute([$id]);
        db()->prepare("UPDATE users SET is_active = 0 WHERE id = (SELECT user_id FROM employees WHERE id = ?)")
            ->execute([$id]);

        AuthMiddleware::audit('terminate_employee', 'employees', $id);
        Response::success(null, 'Employee terminated');
    }

    // GET /api/employees/stats
    public static function stats(): void {
        AuthMiddleware::authenticate();

        $stats = db()->query("SELECT
            COUNT(*) as total,
            SUM(employment_status = 'active') as active,
            SUM(employment_status = 'probation') as probation,
            SUM(employment_status = 'notice') as on_notice,
            SUM(employment_status IN ('terminated','resigned','retired')) as separated,
            SUM(employment_type = 'full_time') as full_time,
            SUM(employment_type = 'contract') as contract,
            SUM(employment_type = 'intern') as interns,
            SUM(gender = 'male') as male,
            SUM(gender = 'female') as female
            FROM employees")->fetch();

        $byDept = db()->query("SELECT d.name, COUNT(e.id) as count
            FROM employees e JOIN departments d ON d.id = e.department_id
            WHERE e.employment_status IN ('active','probation')
            GROUP BY d.id, d.name ORDER BY count DESC")->fetchAll();

        $newJoinees = db()->query("SELECT COUNT(*) FROM employees WHERE date_joined >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) AND employment_status != 'terminated'")->fetchColumn();

        $attrition = db()->query("SELECT COUNT(*) FROM employees WHERE last_working_day >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)")->fetchColumn();

        Response::success([
            'overview'    => $stats,
            'by_department' => $byDept,
            'new_joinees_30d' => $newJoinees,
            'attrition_30d'   => $attrition,
        ]);
    }

    // GET /api/employees/{id}/org-chart
    public static function orgChart(int $id): void {
        AuthMiddleware::authenticate();

        // Get employee + direct reports (2 levels)
        $stmt = db()->prepare("SELECT e.id, e.employee_id, e.first_name, e.last_name, e.profile_photo, e.manager_id, d.name as department_name, des.title as designation_title
            FROM employees e
            LEFT JOIN departments d ON d.id = e.department_id
            LEFT JOIN designations des ON des.id = e.designation_id
            WHERE e.id = ? OR e.manager_id = ?");
        $stmt->execute([$id, $id]);
        Response::success($stmt->fetchAll());
    }
}
