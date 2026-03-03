<?php
// ============================================================
// ENTERPRISE HRMS - ATTENDANCE CONTROLLER
// ============================================================

class AttendanceController {

    // POST /api/attendance/checkin
    public static function checkIn(): void {
        $user = AuthMiddleware::authenticate();
        $empId = $user['employee_id'];
        if (!$empId) Response::error('Employee profile not found');

        $today = date('Y-m-d');
        $now   = date('Y-m-d H:i:s');

        // Check if already checked in today
        $existing = db()->prepare("SELECT id, check_in, check_out FROM attendance WHERE employee_id = ? AND attendance_date = ?");
        $existing->execute([$empId, $today]);
        $record = $existing->fetch();

        if ($record && $record['check_in']) {
            Response::error('Already checked in today at ' . date('h:i A', strtotime($record['check_in'])));
        }

        $body     = json_decode(file_get_contents('php://input'), true) ?? [];
        $location = $body['location'] ?? null;
        $ip       = $_SERVER['REMOTE_ADDR'] ?? null;

        // Determine shift
        $shiftStmt = db()->prepare("SELECT s.id, s.start_time, s.grace_in_mins FROM shifts s
            JOIN shift_assignments sa ON sa.shift_id = s.id
            WHERE sa.employee_id = ? AND sa.from_date <= ? AND (sa.to_date IS NULL OR sa.to_date >= ?)
            ORDER BY sa.from_date DESC LIMIT 1");
        $shiftStmt->execute([$empId, $today, $today]);
        $shift = $shiftStmt->fetch();

        // Determine status
        $status = 'present';
        if ($shift) {
            $shiftStart = strtotime($today . ' ' . $shift['start_time']);
            $gracePeriod = $shiftStart + ($shift['grace_in_mins'] * 60);
            if (strtotime($now) > $gracePeriod) $status = 'late';
        }

        if ($record) {
            db()->prepare("UPDATE attendance SET check_in=?, status=?, check_in_location=?, check_in_ip=? WHERE id=?")
                ->execute([$now, $status, $location, $ip, $record['id']]);
            $attId = $record['id'];
        } else {
            db()->prepare("INSERT INTO attendance (employee_id, attendance_date, check_in, status, check_in_location, check_in_ip, shift_id) VALUES (?,?,?,?,?,?,?)")
                ->execute([$empId, $today, $now, $status, $location, $ip, $shift['id'] ?? null]);
            $attId = db()->lastInsertId();
        }

        Response::success([
            'attendance_id'  => $attId,
            'check_in'       => $now,
            'status'         => $status,
            'message'        => 'Checked in at ' . date('h:i A'),
        ], 'Check-in successful');
    }

    // POST /api/attendance/checkout
    public static function checkOut(): void {
        $user  = AuthMiddleware::authenticate();
        $empId = $user['employee_id'];
        if (!$empId) Response::error('Employee profile not found');

        $today = date('Y-m-d');
        $now   = date('Y-m-d H:i:s');

        $stmt = db()->prepare("SELECT * FROM attendance WHERE employee_id = ? AND attendance_date = ?");
        $stmt->execute([$empId, $today]);
        $record = $stmt->fetch();

        if (!$record || !$record['check_in']) Response::error('No check-in found for today');
        if ($record['check_out']) Response::error('Already checked out at ' . date('h:i A', strtotime($record['check_out'])));

        $body     = json_decode(file_get_contents('php://input'), true) ?? [];
        $location = $body['location'] ?? null;
        $ip       = $_SERVER['REMOTE_ADDR'] ?? null;

        $totalHours = (strtotime($now) - strtotime($record['check_in'])) / 3600;

        // Calculate overtime
        $shiftHours = 9.0; // default
        $overtimeHours = max(0, $totalHours - $shiftHours);

        db()->prepare("UPDATE attendance SET check_out=?, total_hours=?, overtime_hours=?, check_out_location=?, check_out_ip=? WHERE id=?")
            ->execute([
                $now, round($totalHours, 2), round($overtimeHours, 2),
                $location, $ip, $record['id']
            ]);

        Response::success([
            'check_out'       => $now,
            'total_hours'     => round($totalHours, 2),
            'overtime_hours'  => round($overtimeHours, 2),
            'message'         => 'Checked out at ' . date('h:i A'),
        ], 'Check-out successful');
    }

    // GET /api/attendance
    public static function index(): void {
        $user = AuthMiddleware::authenticate();
        AuthMiddleware::requirePermission('attendance.view');

        $page   = max(1, (int)($_GET['page'] ?? 1));
        $limit  = min((int)($_GET['limit'] ?? DEFAULT_PAGE_SIZE), MAX_PAGE_SIZE);
        $offset = ($page - 1) * $limit;

        $where  = ['1=1'];
        $params = [];

        // Non-admin sees only own attendance
        if (in_array($user['role_slug'], ['employee'])) {
            $where[] = 'a.employee_id = ?';
            $params[] = $user['employee_id'];
        } else {
            if (!empty($_GET['employee_id'])) { $where[] = 'a.employee_id = ?'; $params[] = $_GET['employee_id']; }
            if (!empty($_GET['department_id'])) { $where[] = 'e.department_id = ?'; $params[] = $_GET['department_id']; }
        }

        if (!empty($_GET['from_date'])) { $where[] = 'a.attendance_date >= ?'; $params[] = $_GET['from_date']; }
        if (!empty($_GET['to_date']))   { $where[] = 'a.attendance_date <= ?'; $params[] = $_GET['to_date']; }
        if (!empty($_GET['status']))    { $where[] = 'a.status = ?'; $params[] = $_GET['status']; }
        if (!empty($_GET['month'])) {
            $where[] = 'MONTH(a.attendance_date) = ?';
            $where[] = 'YEAR(a.attendance_date) = ?';
            [$year, $month] = explode('-', $_GET['month']);
            $params[] = $month; $params[] = $year;
        }

        $whereStr = implode(' AND ', $where);

        $count = db()->prepare("SELECT COUNT(*) FROM attendance a JOIN employees e ON e.id = a.employee_id WHERE {$whereStr}");
        $count->execute($params);
        $total = (int)$count->fetchColumn();

        $stmt = db()->prepare("SELECT a.*, CONCAT(e.first_name,' ',e.last_name) as employee_name, e.employee_id as emp_code,
            d.name as department_name, s.name as shift_name
            FROM attendance a
            JOIN employees e ON e.id = a.employee_id
            LEFT JOIN departments d ON d.id = e.department_id
            LEFT JOIN shifts s ON s.id = a.shift_id
            WHERE {$whereStr}
            ORDER BY a.attendance_date DESC, e.first_name ASC
            LIMIT {$limit} OFFSET {$offset}");
        $stmt->execute($params);
        Response::paginated($stmt->fetchAll(), $total, $page, $limit);
    }

    // GET /api/attendance/today
    public static function today(): void {
        $user = AuthMiddleware::authenticate();

        // Get today's summary for all active employees
        $today = date('Y-m-d');
        $stmt = db()->query("SELECT
            COUNT(DISTINCT e.id) as total_employees,
            COUNT(CASE WHEN a.status = 'present' THEN 1 END) as present,
            COUNT(CASE WHEN a.status = 'absent' OR a.id IS NULL THEN 1 END) as absent,
            COUNT(CASE WHEN a.status = 'late' THEN 1 END) as late,
            COUNT(CASE WHEN a.status = 'wfh' THEN 1 END) as wfh,
            COUNT(CASE WHEN a.status = 'leave' THEN 1 END) as on_leave,
            COUNT(CASE WHEN a.check_in IS NOT NULL AND a.check_out IS NULL THEN 1 END) as currently_in
            FROM employees e
            LEFT JOIN attendance a ON a.employee_id = e.id AND a.attendance_date = CURDATE()
            WHERE e.employment_status IN ('active','probation')");

        $summary = $stmt->fetch();

        // Recent check-ins
        $recent = db()->query("SELECT a.check_in, a.status, CONCAT(e.first_name,' ',e.last_name) as name, e.employee_id as emp_code, e.profile_photo
            FROM attendance a JOIN employees e ON e.id = a.employee_id
            WHERE a.attendance_date = CURDATE() AND a.check_in IS NOT NULL
            ORDER BY a.check_in DESC LIMIT 10")->fetchAll();

        Response::success(['summary' => $summary, 'recent_checkins' => $recent, 'date' => $today]);
    }

    // GET /api/attendance/my-status
    public static function myStatus(): void {
        $user  = AuthMiddleware::authenticate();
        $empId = $user['employee_id'];

        $today = date('Y-m-d');
        $stmt = db()->prepare("SELECT * FROM attendance WHERE employee_id = ? AND attendance_date = ?");
        $stmt->execute([$empId, $today]);
        $todayRecord = $stmt->fetch();

        // This month summary
        $monthStmt = db()->prepare("SELECT
            COUNT(*) as working_days,
            SUM(status='present') as present,
            SUM(status='absent') as absent,
            SUM(status='late') as late,
            SUM(status='wfh') as wfh,
            SUM(status='half_day') as half_day,
            SUM(status='leave') as on_leave,
            ROUND(SUM(total_hours), 1) as total_hours,
            ROUND(SUM(overtime_hours), 1) as overtime_hours
            FROM attendance
            WHERE employee_id = ? AND MONTH(attendance_date) = MONTH(CURDATE()) AND YEAR(attendance_date) = YEAR(CURDATE())");
        $monthStmt->execute([$empId]);

        Response::success(['today' => $todayRecord, 'month_summary' => $monthStmt->fetch()]);
    }

    // POST /api/attendance/regularize
    public static function regularize(): void {
        $user  = AuthMiddleware::authenticate();
        $empId = $user['employee_id'];
        $body  = json_decode(file_get_contents('php://input'), true) ?? [];

        $v = Validator::make($body, ['date' => 'required|date', 'reason' => 'required|min:10']);
        if ($v->fails()) Response::validationError($v->errors());

        $stmt = db()->prepare("SELECT id FROM attendance WHERE employee_id = ? AND attendance_date = ?");
        $stmt->execute([$empId, $body['date']]);
        $record = $stmt->fetch();

        if ($record) {
            db()->prepare("UPDATE attendance SET remarks = ? WHERE id = ?")->execute([$body['reason'], $record['id']]);
        } else {
            db()->prepare("INSERT INTO attendance (employee_id, attendance_date, status, remarks) VALUES (?,?,?,?)")
                ->execute([$empId, $body['date'], $body['status'] ?? 'present', $body['reason']]);
        }
        Response::success(null, 'Regularization request submitted');
    }

    // GET /api/attendance/report
    public static function report(): void {
        AuthMiddleware::authenticate();
        AuthMiddleware::requirePermission('attendance.view');

        $month     = $_GET['month'] ?? date('Y-m');
        [$year, $m] = explode('-', $month);
        $deptId    = $_GET['department_id'] ?? null;

        $where  = "WHERE e.employment_status IN ('active','probation')";
        $params = [];
        if ($deptId) { $where .= " AND e.department_id = ?"; $params[] = $deptId; }

        $stmt = db()->prepare("SELECT e.id, e.employee_id, e.first_name, e.last_name,
            COUNT(a.id) as records,
            SUM(a.status = 'present') as present,
            SUM(a.status = 'absent') as absent,
            SUM(a.status = 'late') as late,
            SUM(a.status = 'wfh') as wfh,
            SUM(a.status = 'leave') as on_leave,
            SUM(a.status = 'half_day') as half_day,
            ROUND(SUM(a.total_hours), 1) as total_hours,
            ROUND(SUM(a.overtime_hours), 1) as overtime_hours
            FROM employees e
            LEFT JOIN attendance a ON a.employee_id = e.id AND MONTH(a.attendance_date) = ? AND YEAR(a.attendance_date) = ?
            {$where}
            GROUP BY e.id ORDER BY e.first_name");
        $stmt->execute(array_merge([$m, $year], $params));
        Response::success($stmt->fetchAll());
    }
}
