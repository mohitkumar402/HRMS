<?php
// ============================================================
// ENTERPRISE HRMS - LEAVE CONTROLLER
// ============================================================

class LeaveController {

    // GET /api/leaves
    public static function index(): void {
        $user = AuthMiddleware::authenticate();

        $page   = max(1, (int)($_GET['page'] ?? 1));
        $limit  = min((int)($_GET['limit'] ?? DEFAULT_PAGE_SIZE), MAX_PAGE_SIZE);
        $offset = ($page - 1) * $limit;
        $where  = ['1=1'];
        $params = [];

        // Role-based filter
        if ($user['role_slug'] === 'employee') {
            $where[] = 'lr.employee_id = ?';
            $params[] = $user['employee_id'];
        } elseif ($user['role_slug'] === 'manager') {
            $where[] = '(lr.employee_id = ? OR lr.manager_id = ?)';
            $params[] = $user['employee_id'];
            $params[] = $user['employee_id'];
        } else {
            if (!empty($_GET['employee_id'])) { $where[] = 'lr.employee_id = ?'; $params[] = $_GET['employee_id']; }
            if (!empty($_GET['department_id'])) { $where[] = 'e.department_id = ?'; $params[] = $_GET['department_id']; }
        }

        if (!empty($_GET['status']))        { $where[] = 'lr.status = ?'; $params[] = $_GET['status']; }
        if (!empty($_GET['leave_type_id'])) { $where[] = 'lr.leave_type_id = ?'; $params[] = $_GET['leave_type_id']; }
        if (!empty($_GET['from_date']))     { $where[] = 'lr.from_date >= ?'; $params[] = $_GET['from_date']; }
        if (!empty($_GET['to_date']))       { $where[] = 'lr.to_date <= ?'; $params[] = $_GET['to_date']; }

        $whereStr = implode(' AND ', $where);

        $count = db()->prepare("SELECT COUNT(*) FROM leave_requests lr JOIN employees e ON e.id = lr.employee_id WHERE {$whereStr}");
        $count->execute($params);
        $total = (int)$count->fetchColumn();

        $stmt = db()->prepare("SELECT lr.*,
            CONCAT(e.first_name,' ',e.last_name) as employee_name, e.employee_id as emp_code, e.profile_photo,
            lt.name as leave_type_name, lt.code as leave_code, lt.color as leave_color,
            d.name as department_name,
            CONCAT(m.first_name,' ',m.last_name) as manager_name
            FROM leave_requests lr
            JOIN employees e ON e.id = lr.employee_id
            JOIN leave_types lt ON lt.id = lr.leave_type_id
            LEFT JOIN departments d ON d.id = e.department_id
            LEFT JOIN employees m ON m.id = lr.manager_id
            WHERE {$whereStr}
            ORDER BY lr.applied_on DESC
            LIMIT {$limit} OFFSET {$offset}");
        $stmt->execute($params);
        Response::paginated($stmt->fetchAll(), $total, $page, $limit);
    }

    // POST /api/leaves
    public static function apply(): void {
        $user  = AuthMiddleware::authenticate();
        $empId = $user['employee_id'];
        if (!$empId) Response::error('Employee profile not found');

        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $v = Validator::make($body, [
            'leave_type_id' => 'required|numeric',
            'from_date'     => 'required|date',
            'to_date'       => 'required|date',
            'reason'        => 'required|min:10',
        ]);
        if ($v->fails()) Response::validationError($v->errors());

        $from = $body['from_date'];
        $to   = $body['to_date'];
        if (strtotime($from) > strtotime($to)) Response::error('From date cannot be after to date');

        // Calculate business days (excluding holidays and weekends)
        $totalDays = self::calculateLeaveDays($from, $to, $body['day_type'] ?? 'full_day');
        if ($totalDays <= 0) Response::error('Invalid leave dates (no working days in selected range)');

        // Check leave balance
        $balance = db()->prepare("SELECT * FROM leave_balances WHERE employee_id = ? AND leave_type_id = ? AND year = YEAR(CURDATE())");
        $balance->execute([$empId, $body['leave_type_id']]);
        $lb = $balance->fetch();

        $leaveType = db()->prepare("SELECT * FROM leave_types WHERE id = ?");
        $leaveType->execute([$body['leave_type_id']]);
        $lt = $leaveType->fetch();
        if (!$lt || !$lt['is_active']) Response::error('Invalid leave type');

        if ($lb && $lb['balance'] < $totalDays) {
            Response::error("Insufficient leave balance. Available: {$lb['balance']}, Requested: {$totalDays}");
        }

        // Check for overlapping leave
        $overlap = db()->prepare("SELECT id FROM leave_requests WHERE employee_id = ? AND status NOT IN ('rejected','cancelled','withdrawn') AND NOT (to_date < ? OR from_date > ?)");
        $overlap->execute([$empId, $from, $to]);
        if ($overlap->fetch()) Response::error('Leave request overlaps with an existing request');

        // Get manager
        $manager = db()->prepare("SELECT manager_id FROM employees WHERE id = ?");
        $manager->execute([$empId]);
        $emp = $manager->fetch();
        $managerId = $emp['manager_id'] ?? null;

        db()->prepare("INSERT INTO leave_requests (employee_id, leave_type_id, from_date, to_date, total_days, day_type, reason, manager_id) VALUES (?,?,?,?,?,?,?,?)")
            ->execute([$empId, $body['leave_type_id'], $from, $to, $totalDays, $body['day_type'] ?? 'full_day', $body['reason'], $managerId]);

        $reqId = db()->lastInsertId();

        // Notification to manager
        if ($managerId) {
            $empName = $user['first_name'] . ' ' . $user['last_name'];
            $managerUserId = db()->prepare("SELECT user_id FROM employees WHERE id = ?")->execute([$managerId]);
            $mu = db()->prepare("SELECT user_id FROM employees WHERE id = ?");
            $mu->execute([$managerId]);
            $muRow = $mu->fetch();
            if ($muRow) {
                db()->prepare("INSERT INTO notifications (user_id, type, title, message) VALUES (?,?,?,?)")
                    ->execute([$muRow['user_id'], 'leave_request',
                        'Leave Request: ' . $lt['name'],
                        "{$empName} applied for {$totalDays} day(s) of {$lt['name']} from {$from} to {$to}"]);
            }
        }

        Response::created(['id' => $reqId, 'total_days' => $totalDays], 'Leave application submitted successfully');
    }

    // PUT /api/leaves/{id}/approve
    public static function approve(int $id): void {
        $user = AuthMiddleware::authenticate();
        AuthMiddleware::requirePermission('leave.approve');

        $stmt = db()->prepare("SELECT lr.*, lt.name as leave_type FROM leave_requests lr JOIN leave_types lt ON lt.id = lr.leave_type_id WHERE lr.id = ?");
        $stmt->execute([$id]);
        $leave = $stmt->fetch();
        if (!$leave) Response::notFound('Leave request not found');
        if ($leave['status'] !== 'pending') Response::error('Can only approve pending requests');

        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $action   = $body['action'] ?? 'approved'; // approved | rejected
        $remarks  = $body['remarks'] ?? '';

        if (!in_array($action, ['approved', 'rejected'])) Response::error('Invalid action');

        db()->prepare("UPDATE leave_requests SET status=?, manager_remarks=?, manager_id=?, manager_action_at=NOW() WHERE id=?")
            ->execute([$action, $remarks, $user['employee_id'], $id]);

        // If approved, deduct balance
        if ($action === 'approved') {
            db()->prepare("UPDATE leave_balances SET used = used + ? WHERE employee_id = ? AND leave_type_id = ? AND year = YEAR(CURDATE())")
                ->execute([$leave['total_days'], $leave['employee_id'], $leave['leave_type_id']]);

            // Update attendance records
            $from = new DateTime($leave['from_date']);
            $to   = new DateTime($leave['to_date']);
            for ($d = clone $from; $d <= $to; $d->modify('+1 day')) {
                $date = $d->format('Y-m-d');
                $checkHoliday = db()->prepare("SELECT id FROM holidays WHERE date = ?");
                $checkHoliday->execute([$date]);
                if ($checkHoliday->fetch()) continue;
                if (in_array($d->format('N'), [6,7])) continue; // weekend

                $checkAtt = db()->prepare("SELECT id FROM attendance WHERE employee_id = ? AND attendance_date = ?");
                $checkAtt->execute([$leave['employee_id'], $date]);
                if ($checkAtt->fetch()) {
                    db()->prepare("UPDATE attendance SET status = 'leave' WHERE employee_id = ? AND attendance_date = ?")
                        ->execute([$leave['employee_id'], $date]);
                } else {
                    db()->prepare("INSERT INTO attendance (employee_id, attendance_date, status) VALUES (?,?,?)")
                        ->execute([$leave['employee_id'], $date, 'leave']);
                }
            }
        }

        // Notify employee
        $empStmt = db()->prepare("SELECT user_id, first_name FROM employees WHERE id = ?");
        $empStmt->execute([$leave['employee_id']]);
        $emp = $empStmt->fetch();
        if ($emp) {
            $msg = $action === 'approved'
                ? "Your {$leave['leave_type']} request for {$leave['total_days']} day(s) has been approved."
                : "Your {$leave['leave_type']} request has been rejected. Reason: {$remarks}";
            db()->prepare("INSERT INTO notifications (user_id, type, title, message) VALUES (?,?,?,?)")
                ->execute([$emp['user_id'], "leave_{$action}", 'Leave ' . ucfirst($action), $msg]);
        }

        AuthMiddleware::audit($action === 'approved' ? 'approve_leave' : 'reject_leave', 'leaves', $id);
        Response::success(null, "Leave request {$action}");
    }

    // PUT /api/leaves/{id}/cancel
    public static function cancel(int $id): void {
        $user  = AuthMiddleware::authenticate();
        $empId = $user['employee_id'];

        $stmt = db()->prepare("SELECT * FROM leave_requests WHERE id = ? AND employee_id = ?");
        $stmt->execute([$id, $empId]);
        $leave = $stmt->fetch();
        if (!$leave) Response::notFound('Leave request not found');
        if (!in_array($leave['status'], ['pending','approved'])) Response::error('Cannot cancel this request');

        db()->prepare("UPDATE leave_requests SET status = 'cancelled' WHERE id = ?")->execute([$id]);

        // Restore balance if was approved
        if ($leave['status'] === 'approved') {
            db()->prepare("UPDATE leave_balances SET used = used - ? WHERE employee_id = ? AND leave_type_id = ? AND year = YEAR(CURDATE())")
                ->execute([$leave['total_days'], $empId, $leave['leave_type_id']]);
        }

        Response::success(null, 'Leave request cancelled');
    }

    // GET /api/leaves/balance
    public static function balance(): void {
        $user  = AuthMiddleware::authenticate();
        $empId = $_GET['employee_id'] ?? $user['employee_id'];

        $stmt = db()->prepare("SELECT lb.*, lt.name, lt.code, lt.color, lt.is_paid, lt.annual_allocation
            FROM leave_balances lb JOIN leave_types lt ON lt.id = lb.leave_type_id
            WHERE lb.employee_id = ? AND lb.year = YEAR(CURDATE())
            ORDER BY lt.name");
        $stmt->execute([$empId]);
        Response::success($stmt->fetchAll());
    }

    // GET /api/leaves/types
    public static function types(): void {
        AuthMiddleware::authenticate();
        $stmt = db()->query("SELECT * FROM leave_types WHERE is_active = 1 ORDER BY name");
        Response::success($stmt->fetchAll());
    }

    // GET /api/leaves/calendar
    public static function calendar(): void {
        AuthMiddleware::authenticate();
        $month  = $_GET['month'] ?? date('Y-m');
        [$year, $m] = explode('-', $month);

        $deptId = $_GET['department_id'] ?? null;
        $where  = "WHERE lr.status = 'approved' AND YEAR(lr.from_date) = ? AND MONTH(lr.from_date) = ?";
        $params = [$year, $m];
        if ($deptId) { $where .= " AND e.department_id = ?"; $params[] = $deptId; }

        $stmt = db()->prepare("SELECT lr.id, lr.from_date, lr.to_date, lr.total_days, lr.day_type,
            lt.name as leave_type, lt.color,
            CONCAT(e.first_name,' ',e.last_name) as employee_name, e.profile_photo
            FROM leave_requests lr
            JOIN employees e ON e.id = lr.employee_id
            JOIN leave_types lt ON lt.id = lr.leave_type_id
            {$where} ORDER BY lr.from_date");
        $stmt->execute($params);

        $holidays = db()->prepare("SELECT * FROM holidays WHERE YEAR(date) = ? AND MONTH(date) = ? AND is_active = 1");
        $holidays->execute([$year, $m]);

        Response::success(['leaves' => $stmt->fetchAll(), 'holidays' => $holidays->fetchAll()]);
    }

    private static function calculateLeaveDays(string $from, string $to, string $dayType): float {
        if ($dayType !== 'full_day') return 0.5;

        $start = new DateTime($from);
        $end   = new DateTime($to);
        $days  = 0;

        for ($d = clone $start; $d <= $end; $d->modify('+1 day')) {
            if (in_array($d->format('N'), [6,7])) continue;
            $holidayCheck = db()->prepare("SELECT id FROM holidays WHERE date = ? AND is_active = 1");
            $holidayCheck->execute([$d->format('Y-m-d')]);
            if ($holidayCheck->fetch()) continue;
            $days++;
        }
        return (float)$days;
    }
}
