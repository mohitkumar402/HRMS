<?php
// ============================================================
// ENTERPRISE HRMS - PERFORMANCE CONTROLLER
// ============================================================

class PerformanceController {

    // GET /api/performance/cycles
    public static function cycles(): void {
        AuthMiddleware::authenticate();
        $stmt = db()->query("SELECT * FROM performance_cycles ORDER BY period_from DESC");
        Response::success($stmt->fetchAll());
    }

    // POST /api/performance/cycles
    public static function createCycle(): void {
        AuthMiddleware::authenticate();
        AuthMiddleware::requirePermission('performance.manage');

        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $v = Validator::make($body, ['name'=>'required','period_from'=>'required|date','period_to'=>'required|date']);
        if ($v->fails()) Response::validationError($v->errors());

        db()->prepare("INSERT INTO performance_cycles (name, type, period_from, period_to, status) VALUES (?,?,?,?,?)")
            ->execute([$body['name'], $body['type'] ?? 'annual', $body['period_from'], $body['period_to'], $body['status'] ?? 'upcoming']);
        Response::created(['id' => db()->lastInsertId()], 'Cycle created');
    }

    // GET /api/performance/goals
    public static function goals(): void {
        $user = AuthMiddleware::authenticate();

        $empId = $_GET['employee_id'] ?? $user['employee_id'];
        if ($user['role_slug'] === 'employee') $empId = $user['employee_id'];

        $cycleId = $_GET['cycle_id'] ?? null;
        $where   = ['g.employee_id = ?'];
        $params  = [$empId];
        if ($cycleId) { $where[] = 'g.cycle_id = ?'; $params[] = $cycleId; }

        $stmt = db()->prepare("SELECT g.*, pc.name as cycle_name FROM goals g
            LEFT JOIN performance_cycles pc ON pc.id = g.cycle_id
            WHERE " . implode(' AND ', $where) . " ORDER BY g.due_date ASC, g.created_at DESC");
        $stmt->execute($params);
        Response::success($stmt->fetchAll());
    }

    // POST /api/performance/goals
    public static function createGoal(): void {
        $user = AuthMiddleware::authenticate();

        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $v = Validator::make($body, ['title' => 'required|min:3', 'employee_id' => 'required|numeric']);
        if ($v->fails()) Response::validationError($v->errors());

        // Employees can only set goals for themselves
        if ($user['role_slug'] === 'employee' && $body['employee_id'] != $user['employee_id']) {
            Response::forbidden('Cannot set goals for other employees');
        }

        $fields = ['employee_id','cycle_id','title','description','category','goal_type','weight','target_value','due_date','status'];
        $data = [];
        foreach ($fields as $f) { if (isset($body[$f])) $data[$f] = $body[$f] ?: null; }

        $cols = implode(',', array_keys($data));
        $pl   = implode(',', array_fill(0, count($data), '?'));
        db()->prepare("INSERT INTO goals ({$cols}) VALUES ({$pl})")->execute(array_values($data));

        Response::created(['id' => db()->lastInsertId()], 'Goal created');
    }

    // PUT /api/performance/goals/{id}
    public static function updateGoal(int $id): void {
        $user = AuthMiddleware::authenticate();

        $goal = db()->prepare("SELECT * FROM goals WHERE id = ?");
        $goal->execute([$id]);
        $g = $goal->fetch();
        if (!$g) Response::notFound();

        if ($user['role_slug'] === 'employee' && $g['employee_id'] != $user['employee_id']) {
            Response::forbidden();
        }

        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $updatable = ['title','description','category','goal_type','weight','target_value','actual_value','achievement','due_date','status'];
        $sets = []; $params = [];
        foreach ($updatable as $f) {
            if (array_key_exists($f, $body)) { $sets[] = "{$f}=?"; $params[] = $body[$f] ?: null; }
        }
        if (!$sets) Response::error('Nothing to update');
        $params[] = $id;
        db()->prepare("UPDATE goals SET " . implode(',', $sets) . " WHERE id=?")->execute($params);
        Response::success(null, 'Goal updated');
    }

    // GET /api/performance/reviews
    public static function reviews(): void {
        $user = AuthMiddleware::authenticate();

        $page   = max(1,(int)($_GET['page'] ?? 1));
        $limit  = min((int)($_GET['limit'] ?? DEFAULT_PAGE_SIZE), MAX_PAGE_SIZE);
        $offset = ($page-1)*$limit;
        $where  = ['1=1'];
        $params = [];

        if ($user['role_slug'] === 'employee') {
            $where[] = '(pr.employee_id = ? OR pr.reviewer_id = ?)';
            $params[] = $user['employee_id'];
            $params[] = $user['employee_id'];
        } else {
            if (!empty($_GET['employee_id'])) { $where[] = 'pr.employee_id = ?'; $params[] = $_GET['employee_id']; }
            if (!empty($_GET['cycle_id']))    { $where[] = 'pr.cycle_id = ?';    $params[] = $_GET['cycle_id']; }
        }
        $whereStr = implode(' AND ', $where);

        $count = db()->prepare("SELECT COUNT(*) FROM performance_reviews pr WHERE {$whereStr}");
        $count->execute($params);
        $total = (int)$count->fetchColumn();

        $stmt = db()->prepare("SELECT pr.*,
            CONCAT(e.first_name,' ',e.last_name) as employee_name, e.employee_id as emp_code,
            CONCAT(rv.first_name,' ',rv.last_name) as reviewer_name,
            pc.name as cycle_name, pc.type as cycle_type,
            d.name as department_name
            FROM performance_reviews pr
            JOIN employees e ON e.id = pr.employee_id
            JOIN employees rv ON rv.id = pr.reviewer_id
            JOIN performance_cycles pc ON pc.id = pr.cycle_id
            LEFT JOIN departments d ON d.id = e.department_id
            WHERE {$whereStr}
            ORDER BY pr.created_at DESC LIMIT {$limit} OFFSET {$offset}");
        $stmt->execute($params);
        Response::paginated($stmt->fetchAll(), $total, $page, $limit);
    }

    // POST /api/performance/reviews
    public static function createReview(): void {
        AuthMiddleware::authenticate();
        AuthMiddleware::requirePermission('performance.manage');

        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $v = Validator::make($body, [
            'employee_id'   => 'required|numeric',
            'cycle_id'      => 'required|numeric',
            'reviewer_id'   => 'required|numeric',
            'reviewer_type' => 'required',
        ]);
        if ($v->fails()) Response::validationError($v->errors());

        db()->prepare("INSERT INTO performance_reviews (employee_id, cycle_id, reviewer_id, reviewer_type) VALUES (?,?,?,?)")
            ->execute([$body['employee_id'], $body['cycle_id'], $body['reviewer_id'], $body['reviewer_type']]);

        Response::created(['id' => db()->lastInsertId()], 'Review initiated');
    }

    // PUT /api/performance/reviews/{id}/submit
    public static function submitReview(int $id): void {
        $user = AuthMiddleware::authenticate();

        $review = db()->prepare("SELECT * FROM performance_reviews WHERE id = ?");
        $review->execute([$id]);
        $r = $review->fetch();
        if (!$r) Response::notFound();

        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $updates = [];
        $params  = [];

        if ($r['reviewer_type'] === 'self' && $r['employee_id'] == $user['employee_id']) {
            $updates[] = 'self_rating = ?'; $params[] = $body['self_rating'] ?? null;
        } elseif (in_array($r['reviewer_type'], ['manager','hr'])) {
            $updates[] = 'manager_rating = ?'; $params[] = $body['manager_rating'] ?? null;
            $updates[] = 'final_rating = ?';   $params[] = $body['final_rating'] ?? null;
            $updates[] = 'performance_band = ?'; $params[] = $body['performance_band'] ?? null;
        }

        $updates[] = 'strengths = ?';   $params[] = $body['strengths'] ?? null;
        $updates[] = 'improvements = ?'; $params[] = $body['improvements'] ?? null;
        $updates[] = 'comments = ?';    $params[] = $body['comments'] ?? null;
        $updates[] = 'status = ?';      $params[] = 'submitted';
        $updates[] = 'submitted_at = NOW()';
        $params[]  = $id;

        db()->prepare("UPDATE performance_reviews SET " . implode(',', $updates) . " WHERE id=?")->execute($params);
        Response::success(null, 'Review submitted');
    }

    // GET /api/performance/stats
    public static function stats(): void {
        AuthMiddleware::authenticate();

        $cycleId = $_GET['cycle_id'] ?? null;
        $where = $cycleId ? 'WHERE pr.cycle_id = ?' : 'WHERE 1=1';
        $params = $cycleId ? [$cycleId] : [];

        $bandDist = db()->prepare("SELECT performance_band, COUNT(*) as count FROM performance_reviews pr {$where} AND performance_band IS NOT NULL GROUP BY performance_band");
        $bandDist->execute($params);

        $avgRating = db()->prepare("SELECT ROUND(AVG(final_rating), 2) as avg FROM performance_reviews pr {$where} AND final_rating IS NOT NULL");
        $avgRating->execute($params);

        $deptAvg = db()->query("SELECT d.name, ROUND(AVG(pr.final_rating),2) as avg_rating, COUNT(pr.id) as reviews
            FROM performance_reviews pr JOIN employees e ON e.id = pr.employee_id JOIN departments d ON d.id = e.department_id
            WHERE pr.final_rating IS NOT NULL GROUP BY d.id ORDER BY avg_rating DESC")->fetchAll();

        $topPerformers = db()->query("SELECT CONCAT(e.first_name,' ',e.last_name) as name, e.employee_id, pr.final_rating, pr.performance_band, d.name as department
            FROM performance_reviews pr JOIN employees e ON e.id = pr.employee_id LEFT JOIN departments d ON d.id = e.department_id
            WHERE pr.final_rating IS NOT NULL ORDER BY pr.final_rating DESC LIMIT 5")->fetchAll();

        Response::success([
            'band_distribution' => $bandDist->fetchAll(),
            'avg_rating'        => $avgRating->fetchColumn(),
            'by_department'     => $deptAvg,
            'top_performers'    => $topPerformers,
        ]);
    }
}
