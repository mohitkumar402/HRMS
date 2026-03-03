<?php
// ============================================================
// ENTERPRISE HRMS - REPORTS CONTROLLER
// ============================================================

class ReportController {

    // GET /api/reports/headcount
    public static function headcount(): void {
        AuthMiddleware::authenticate();
        AuthMiddleware::requirePermission('reports.view');

        $asOf = $_GET['as_of'] ?? date('Y-m-d');

        $data = db()->prepare("SELECT
            d.name as department,
            des.title as designation,
            e.employment_type,
            e.gender,
            l.name as location,
            COUNT(e.id) as count
            FROM employees e
            LEFT JOIN departments d ON d.id = e.department_id
            LEFT JOIN designations des ON des.id = e.designation_id
            LEFT JOIN locations l ON l.id = e.location_id
            WHERE e.employment_status IN ('active','probation') AND e.date_joined <= ?
            GROUP BY d.id, des.id, e.employment_type, e.gender, l.id
            ORDER BY d.name, des.title");
        $data->execute([$asOf]);
        Response::success($data->fetchAll());
    }

    // GET /api/reports/attrition
    public static function attrition(): void {
        AuthMiddleware::authenticate();
        AuthMiddleware::requirePermission('reports.view');

        $year = (int)($_GET['year'] ?? date('Y'));

        $monthly = db()->prepare("SELECT
            MONTH(last_working_day) as month,
            COUNT(*) as exits,
            SUM(employment_status='resigned') as resignations,
            SUM(employment_status='terminated') as terminations,
            SUM(employment_status='retired') as retirements
            FROM employees
            WHERE YEAR(last_working_day) = ? AND last_working_day IS NOT NULL
            GROUP BY MONTH(last_working_day) ORDER BY month");
        $monthly->execute([$year]);

        $byDept = db()->prepare("SELECT d.name as department, COUNT(e.id) as exits
            FROM employees e JOIN departments d ON d.id = e.department_id
            WHERE YEAR(e.last_working_day) = ?
            GROUP BY d.id ORDER BY exits DESC");
        $byDept->execute([$year]);

        $totalAtStart = db()->prepare("SELECT COUNT(*) FROM employees WHERE date_joined <= ? AND (last_working_day IS NULL OR last_working_day > ?)")
            ->execute(["{$year}-01-01", "{$year}-01-01"]);

        $totalExits = db()->prepare("SELECT COUNT(*) FROM employees WHERE YEAR(last_working_day) = ?")->execute([$year]);

        Response::success([
            'monthly'   => $monthly->fetchAll(),
            'by_department' => $byDept->fetchAll(),
        ]);
    }

    // GET /api/reports/attendance-summary
    public static function attendanceSummary(): void {
        AuthMiddleware::authenticate();
        AuthMiddleware::requirePermission('reports.view');

        $month  = $_GET['month'] ?? date('Y-m');
        [$year, $m] = explode('-', $month);
        $deptId = $_GET['department_id'] ?? null;

        $where = "WHERE e.employment_status IN ('active','probation')";
        $params = [];
        if ($deptId) { $where .= " AND e.department_id = ?"; $params[] = $deptId; }

        $stmt = db()->prepare("SELECT
            e.employee_id, CONCAT(e.first_name,' ',e.last_name) as name,
            d.name as department,
            COUNT(a.id) as total_records,
            SUM(a.status='present') as present,
            SUM(a.status='absent') as absent,
            SUM(a.status='late') as late,
            SUM(a.status='half_day') as half_day,
            SUM(a.status='wfh') as wfh,
            SUM(a.status='leave') as on_leave,
            SUM(a.status='holiday') as holidays,
            ROUND(SUM(a.total_hours), 1) as total_hours,
            ROUND(SUM(a.overtime_hours), 1) as overtime
            FROM employees e
            LEFT JOIN departments d ON d.id = e.department_id
            LEFT JOIN attendance a ON a.employee_id = e.id AND MONTH(a.attendance_date)=? AND YEAR(a.attendance_date)=?
            {$where}
            GROUP BY e.id ORDER BY e.first_name");
        $stmt->execute(array_merge([$m, $year], $params));
        Response::success($stmt->fetchAll());
    }

    // GET /api/reports/leave-summary
    public static function leaveSummary(): void {
        AuthMiddleware::authenticate();
        AuthMiddleware::requirePermission('reports.view');

        $year   = (int)($_GET['year'] ?? date('Y'));
        $deptId = $_GET['department_id'] ?? null;

        $where = "WHERE lb.year = ?";
        $params = [$year];
        if ($deptId) { $where .= " AND e.department_id = ?"; $params[] = $deptId; }

        $stmt = db()->prepare("SELECT e.employee_id, CONCAT(e.first_name,' ',e.last_name) as name,
            d.name as department,
            lt.name as leave_type, lt.code,
            lb.allocated, lb.carry_forward, lb.used, lb.balance
            FROM leave_balances lb
            JOIN employees e ON e.id = lb.employee_id
            JOIN leave_types lt ON lt.id = lb.leave_type_id
            LEFT JOIN departments d ON d.id = e.department_id
            {$where}
            ORDER BY e.first_name, lt.name");
        $stmt->execute($params);
        Response::success($stmt->fetchAll());
    }

    // GET /api/reports/payroll-summary
    public static function payrollSummary(): void {
        AuthMiddleware::authenticate();
        AuthMiddleware::requirePermission('payroll.view');

        $year = (int)($_GET['year'] ?? date('Y'));

        $stmt = db()->prepare("SELECT pr.month, pr.year, pr.total_employees, pr.total_gross, pr.total_deductions, pr.total_net, pr.status, pr.pay_date
            FROM payroll_runs pr WHERE pr.year = ? ORDER BY pr.month");
        $stmt->execute([$year]);
        $runs = $stmt->fetchAll();

        $componentSummary = db()->prepare("SELECT d.name as department,
            ROUND(SUM(p.basic)) as total_basic, ROUND(SUM(p.hra)) as total_hra,
            ROUND(SUM(p.gross_salary)) as total_gross, ROUND(SUM(p.net_salary)) as total_net,
            ROUND(SUM(p.pf_deduction)) as total_pf, ROUND(SUM(p.tds_deduction)) as total_tds
            FROM payslips p
            JOIN employees e ON e.id = p.employee_id
            JOIN payroll_runs pr ON pr.id = p.payroll_run_id
            LEFT JOIN departments d ON d.id = e.department_id
            WHERE pr.year = ?
            GROUP BY d.id ORDER BY total_gross DESC");
        $componentSummary->execute([$year]);

        Response::success(['monthly' => $runs, 'by_department' => $componentSummary->fetchAll()]);
    }

    // GET /api/reports/diversity
    public static function diversity(): void {
        AuthMiddleware::authenticate();
        AuthMiddleware::requirePermission('reports.view');

        $genderByDept = db()->query("SELECT d.name as department, e.gender, COUNT(*) as count
            FROM employees e JOIN departments d ON d.id = e.department_id
            WHERE e.employment_status IN ('active','probation')
            GROUP BY d.id, e.gender ORDER BY d.name")->fetchAll();

        $ageGroups = db()->query("SELECT
            CASE
                WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) < 25 THEN 'Under 25'
                WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) < 35 THEN '25-34'
                WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) < 45 THEN '35-44'
                WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) < 55 THEN '45-54'
                ELSE '55+'
            END as age_group, COUNT(*) as count
            FROM employees WHERE employment_status IN ('active','probation') AND date_of_birth IS NOT NULL
            GROUP BY age_group ORDER BY age_group")->fetchAll();

        $tenureGroups = db()->query("SELECT
            CASE
                WHEN TIMESTAMPDIFF(YEAR, date_joined, CURDATE()) < 1 THEN 'Less than 1 year'
                WHEN TIMESTAMPDIFF(YEAR, date_joined, CURDATE()) < 3 THEN '1-3 years'
                WHEN TIMESTAMPDIFF(YEAR, date_joined, CURDATE()) < 5 THEN '3-5 years'
                WHEN TIMESTAMPDIFF(YEAR, date_joined, CURDATE()) < 10 THEN '5-10 years'
                ELSE '10+ years'
            END as tenure, COUNT(*) as count
            FROM employees WHERE employment_status IN ('active','probation')
            GROUP BY tenure ORDER BY MIN(TIMESTAMPDIFF(YEAR, date_joined, CURDATE()))")->fetchAll();

        Response::success([
            'gender_by_department' => $genderByDept,
            'age_groups'           => $ageGroups,
            'tenure_groups'        => $tenureGroups,
        ]);
    }

    // GET /api/reports/recruitment
    public static function recruitment(): void {
        AuthMiddleware::authenticate();
        AuthMiddleware::requirePermission('reports.view');

        $year = (int)($_GET['year'] ?? date('Y'));

        $funnel = db()->query("SELECT stage, COUNT(*) as count FROM applications GROUP BY stage ORDER BY FIELD(stage,'applied','screening','shortlisted','phone_screen','assessment','technical_round','hr_round','final_round','offered','accepted','rejected')")->fetchAll();
        $source = db()->query("SELECT c.source, COUNT(*) as count FROM applications a JOIN candidates c ON c.id = a.candidate_id GROUP BY c.source ORDER BY count DESC")->fetchAll();

        $monthly = db()->prepare("SELECT MONTH(applied_date) as month, COUNT(*) as applications, SUM(stage='accepted') as hired FROM applications WHERE YEAR(applied_date) = ? GROUP BY MONTH(applied_date) ORDER BY month");
        $monthly->execute([$year]);

        Response::success(['funnel' => $funnel, 'source' => $source, 'monthly' => $monthly->fetchAll()]);
    }

    // GET /api/departments
    public static function departments(): void {
        AuthMiddleware::authenticate();
        $stmt = db()->query("SELECT d.*, CONCAT(e.first_name,' ',e.last_name) as head_name, COUNT(emp.id) as employee_count FROM departments d LEFT JOIN employees e ON e.id = d.head_emp_id LEFT JOIN employees emp ON emp.department_id = d.id AND emp.employment_status IN ('active','probation') WHERE d.is_active = 1 GROUP BY d.id ORDER BY d.name");
        Response::success($stmt->fetchAll());
    }

    // GET /api/locations
    public static function locations(): void {
        AuthMiddleware::authenticate();
        $stmt = db()->query("SELECT * FROM locations WHERE is_active=1 ORDER BY name");
        Response::success($stmt->fetchAll());
    }

    // GET /api/settings
    public static function getSettings(): void {
        AuthMiddleware::authenticate();
        $stmt = db()->query("SELECT `key`, `value`, `type`, `group` FROM settings ORDER BY `group`, `key`");
        $settings = [];
        foreach ($stmt->fetchAll() as $row) {
            $settings[$row['group']][$row['key']] = match($row['type']) {
                'number'  => (float)$row['value'],
                'boolean' => $row['value'] === 'true',
                'json'    => json_decode($row['value'], true),
                default   => $row['value'],
            };
        }
        Response::success($settings);
    }

    // PUT /api/settings
    public static function updateSettings(): void {
        AuthMiddleware::authenticate();
        AuthMiddleware::requirePermission('settings.manage');

        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $stmt = db()->prepare("UPDATE settings SET value = ? WHERE `key` = ?");
        foreach ($body as $key => $value) {
            $stmt->execute([is_array($value) ? json_encode($value) : (string)$value, $key]);
        }
        Response::success(null, 'Settings updated');
    }

    // GET /api/notifications
    public static function notifications(): void {
        $user = AuthMiddleware::authenticate();
        $stmt = db()->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 50");
        $stmt->execute([$user['id']]);
        Response::success($stmt->fetchAll());
    }

    // PUT /api/notifications/read
    public static function markRead(): void {
        $user = AuthMiddleware::authenticate();
        $body = json_decode(file_get_contents('php://input'), true) ?? [];

        if (!empty($body['ids'])) {
            $placeholders = implode(',', array_fill(0, count($body['ids']), '?'));
            db()->prepare("UPDATE notifications SET is_read=1, read_at=NOW() WHERE id IN ({$placeholders}) AND user_id=?")
                ->execute(array_merge($body['ids'], [$user['id']]));
        } else {
            db()->prepare("UPDATE notifications SET is_read=1, read_at=NOW() WHERE user_id=?")->execute([$user['id']]);
        }
        Response::success(null, 'Marked as read');
    }
}
