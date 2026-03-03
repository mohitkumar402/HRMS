<?php
// ============================================================
// ENTERPRISE HRMS - PAYROLL CONTROLLER
// ============================================================

class PayrollController {

    // GET /api/payroll/runs
    public static function runs(): void {
        AuthMiddleware::authenticate();
        AuthMiddleware::requirePermission('payroll.view');

        $stmt = db()->query("SELECT pr.*,
            CONCAT(u.email) as created_by_name,
            COUNT(p.id) as payslip_count
            FROM payroll_runs pr
            LEFT JOIN users u ON u.id = pr.created_by
            LEFT JOIN payslips p ON p.payroll_run_id = pr.id
            GROUP BY pr.id ORDER BY pr.year DESC, pr.month DESC LIMIT 24");
        Response::success($stmt->fetchAll());
    }

    // POST /api/payroll/runs
    public static function createRun(): void {
        $user = AuthMiddleware::authenticate();
        AuthMiddleware::requirePermission('payroll.manage');

        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $v = Validator::make($body, ['month' => 'required|numeric', 'year' => 'required|numeric']);
        if ($v->fails()) Response::validationError($v->errors());

        $month = (int)$body['month'];
        $year  = (int)$body['year'];
        if ($month < 1 || $month > 12) Response::error('Invalid month');

        // Check existing
        $check = db()->prepare("SELECT id FROM payroll_runs WHERE month = ? AND year = ?");
        $check->execute([$month, $year]);
        if ($check->fetch()) Response::error('Payroll run already exists for this period', 409);

        db()->prepare("INSERT INTO payroll_runs (month, year, status, created_by) VALUES (?,?,?,?)")
            ->execute([$month, $year, 'draft', $user['id']]);
        $runId = db()->lastInsertId();

        // Auto-generate payslips for all active employees
        self::generatePayslips($runId, $month, $year);

        $stmt = db()->prepare("SELECT * FROM payroll_runs WHERE id = ?");
        $stmt->execute([$runId]);
        Response::created($stmt->fetch(), 'Payroll run created');
    }

    // GET /api/payroll/runs/{id}
    public static function getRun(int $id): void {
        AuthMiddleware::authenticate();
        AuthMiddleware::requirePermission('payroll.view');

        $run = db()->prepare("SELECT * FROM payroll_runs WHERE id = ?");
        $run->execute([$id]);
        $runData = $run->fetch();
        if (!$runData) Response::notFound('Payroll run not found');

        $payslips = db()->prepare("SELECT p.*,
            CONCAT(e.first_name,' ',e.last_name) as employee_name,
            e.employee_id as emp_code, e.bank_account_number, e.bank_ifsc_code,
            d.name as department_name
            FROM payslips p
            JOIN employees e ON e.id = p.employee_id
            LEFT JOIN departments d ON d.id = e.department_id
            WHERE p.payroll_run_id = ?
            ORDER BY e.first_name");
        $payslips->execute([$id]);

        $runData['payslips'] = $payslips->fetchAll();
        Response::success($runData);
    }

    // PUT /api/payroll/runs/{id}/approve
    public static function approveRun(int $id): void {
        $user = AuthMiddleware::authenticate();
        AuthMiddleware::requirePermission('payroll.approve');

        $run = db()->prepare("SELECT * FROM payroll_runs WHERE id = ?");
        $run->execute([$id]);
        $runData = $run->fetch();
        if (!$runData) Response::notFound();
        if ($runData['status'] !== 'draft') Response::error('Can only approve draft payroll runs');

        db()->prepare("UPDATE payroll_runs SET status='approved', approved_by=?, approved_at=NOW() WHERE id=?")
            ->execute([$user['id'], $id]);
        db()->prepare("UPDATE payslips SET status='paid' WHERE payroll_run_id=?")->execute([$id]);

        AuthMiddleware::audit('approve_payroll', 'payroll', $id);
        Response::success(null, 'Payroll run approved and payslips marked as paid');
    }

    // GET /api/payroll/payslip/{id}
    public static function getPayslip(int $id): void {
        $user = AuthMiddleware::authenticate();
        AuthMiddleware::requirePermission('payroll.view');

        $stmt = db()->prepare("SELECT p.*,
            CONCAT(e.first_name,' ',e.last_name) as employee_name,
            e.employee_id as emp_code, e.personal_phone, e.pan_number,
            e.uan_number, e.pf_number, e.bank_name, e.bank_account_number, e.bank_ifsc_code,
            d.name as department_name, des.title as designation_title,
            l.name as location_name,
            pr.pay_date, pr.status as run_status
            FROM payslips p
            JOIN employees e ON e.id = p.employee_id
            LEFT JOIN departments d ON d.id = e.department_id
            LEFT JOIN designations des ON des.id = e.designation_id
            LEFT JOIN locations l ON l.id = e.location_id
            JOIN payroll_runs pr ON pr.id = p.payroll_run_id
            WHERE p.id = ?");
        $stmt->execute([$id]);
        $payslip = $stmt->fetch();
        if (!$payslip) Response::notFound();

        // Employees can only view own payslips
        if ($user['role_slug'] === 'employee' && $user['employee_id'] != $payslip['employee_id']) {
            Response::forbidden();
        }

        Response::success($payslip);
    }

    // GET /api/payroll/my-payslips
    public static function myPayslips(): void {
        $user  = AuthMiddleware::authenticate();
        $empId = $user['employee_id'];

        $stmt = db()->prepare("SELECT p.id, p.month, p.year, p.gross_salary, p.total_deductions, p.net_salary,
            p.paid_days, p.working_days, p.status, pr.pay_date
            FROM payslips p JOIN payroll_runs pr ON pr.id = p.payroll_run_id
            WHERE p.employee_id = ? ORDER BY p.year DESC, p.month DESC");
        $stmt->execute([$empId]);
        Response::success($stmt->fetchAll());
    }

    // GET /api/payroll/salary-structures
    public static function salaryStructures(): void {
        AuthMiddleware::authenticate();
        AuthMiddleware::requirePermission('payroll.manage');

        $page   = max(1,(int)($_GET['page'] ?? 1));
        $limit  = min((int)($_GET['limit'] ?? DEFAULT_PAGE_SIZE), MAX_PAGE_SIZE);
        $offset = ($page-1)*$limit;

        $count = db()->query("SELECT COUNT(*) FROM salary_structures WHERE status='active'")->fetchColumn();
        $stmt  = db()->query("SELECT ss.*,
            CONCAT(e.first_name,' ',e.last_name) as employee_name, e.employee_id as emp_code, d.name as department_name
            FROM salary_structures ss
            JOIN employees e ON e.id = ss.employee_id
            LEFT JOIN departments d ON d.id = e.department_id
            WHERE ss.status = 'active' ORDER BY e.first_name LIMIT {$limit} OFFSET {$offset}");
        Response::paginated($stmt->fetchAll(), $count, $page, $limit);
    }

    // POST /api/payroll/salary-structures
    public static function storeSalaryStructure(): void {
        $user = AuthMiddleware::authenticate();
        AuthMiddleware::requirePermission('payroll.manage');

        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $v = Validator::make($body, [
            'employee_id'     => 'required|numeric',
            'effective_date'  => 'required|date',
            'ctc_annual'      => 'required|numeric',
            'basic_monthly'   => 'required|numeric',
            'gross_monthly'   => 'required|numeric',
            'net_monthly'     => 'required|numeric',
        ]);
        if ($v->fails()) Response::validationError($v->errors());

        // Supersede existing
        db()->prepare("UPDATE salary_structures SET status='superseded' WHERE employee_id = ? AND status='active'")
            ->execute([$body['employee_id']]);

        $fields = ['employee_id','effective_date','ctc_annual','basic_monthly','hra_monthly',
                   'special_allowance','pf_employee','pf_employer','esi_employee','esi_employer',
                   'professional_tax','tds_monthly','gross_monthly','net_monthly'];
        $data = [];
        foreach ($fields as $f) { if (isset($body[$f])) $data[$f] = $body[$f]; }
        $data['created_by'] = $user['id'];

        $cols = implode(',', array_keys($data));
        $pl   = implode(',', array_fill(0, count($data), '?'));
        db()->prepare("INSERT INTO salary_structures ({$cols}) VALUES ({$pl})")->execute(array_values($data));

        AuthMiddleware::audit('create_salary_structure', 'payroll', (int)db()->lastInsertId(), null, $data);
        Response::created(null, 'Salary structure saved');
    }

    // GET /api/payroll/analytics
    public static function analytics(): void {
        AuthMiddleware::authenticate();
        AuthMiddleware::requirePermission('payroll.view');

        $year = (int)($_GET['year'] ?? date('Y'));

        $monthly = db()->prepare("SELECT month, total_gross, total_deductions, total_net, total_employees FROM payroll_runs WHERE year = ? AND status IN ('approved','paid') ORDER BY month");
        $monthly->execute([$year]);

        $deptWise = db()->query("SELECT d.name, ROUND(SUM(ss.gross_monthly),2) as total_gross FROM salary_structures ss JOIN employees e ON e.id = ss.employee_id JOIN departments d ON d.id = e.department_id WHERE ss.status='active' GROUP BY d.id, d.name ORDER BY total_gross DESC")->fetchAll();

        Response::success(['monthly' => $monthly->fetchAll(), 'by_department' => $deptWise]);
    }

    // ============================================================
    // Private: Generate payslips for a run
    // ============================================================
    private static function generatePayslips(int $runId, int $month, int $year): void {
        // Get working days in month (exclude weekends + holidays)
        $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
        $workingDays = 0;
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $date = sprintf('%04d-%02d-%02d', $year, $month, $d);
            $dow  = date('N', strtotime($date));
            if ($dow >= 6) continue;
            $h = db()->prepare("SELECT id FROM holidays WHERE date = ? AND is_active=1");
            $h->execute([$date]);
            if ($h->fetch()) continue;
            $workingDays++;
        }

        $employees = db()->query("SELECT e.id, e.employment_status FROM employees e WHERE e.employment_status IN ('active','probation')")->fetchAll();

        $totalGross = $totalDed = $totalNet = 0;

        foreach ($employees as $emp) {
            // Get latest salary structure
            $ss = db()->prepare("SELECT * FROM salary_structures WHERE employee_id = ? AND status='active' AND effective_date <= ? ORDER BY effective_date DESC LIMIT 1");
            $ss->execute([$emp['id'], sprintf('%04d-%02d-01', $year, $month)]);
            $salary = $ss->fetch();
            if (!$salary) continue;

            // Get attendance for the month
            $att = db()->prepare("SELECT COUNT(*) as present, SUM(status='absent') as absent, SUM(total_hours) as hours FROM attendance WHERE employee_id=? AND MONTH(attendance_date)=? AND YEAR(attendance_date)=?");
            $att->execute([$emp['id'], $month, $year]);
            $attData = $att->fetch();
            $presentDays = max((float)$attData['present'], 0);

            // LOP = absent days
            $lopDays  = max($workingDays - $presentDays, 0);
            $paidDays = max($workingDays - $lopDays, 0);

            // Pro-rate if not full month
            $ratio = $workingDays > 0 ? $paidDays / $workingDays : 1;

            $basic = round($salary['basic_monthly'] * $ratio, 2);
            $hra   = round($salary['hra_monthly'] * $ratio, 2);
            $spec  = round($salary['special_allowance'] * $ratio, 2);
            $gross = round($salary['gross_monthly'] * $ratio, 2);

            $pfDed = round($salary['pf_employee'] * $ratio, 2);
            $esiDed= round($salary['esi_employee'] * $ratio, 2);
            $tds   = round($salary['tds_monthly'], 2);
            $pt    = round($salary['professional_tax'], 2);
            $totalDeductions = $pfDed + $esiDed + $tds + $pt;
            $net   = round($gross - $totalDeductions, 2);

            db()->prepare("INSERT INTO payslips (payroll_run_id, employee_id, month, year, working_days, present_days, lop_days, paid_days, basic, hra, special_allowance, gross_salary, pf_deduction, esi_deduction, tds_deduction, professional_tax, total_deductions, net_salary) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
                ->execute([$runId, $emp['id'], $month, $year, $workingDays, $presentDays, $lopDays, $paidDays, $basic, $hra, $spec, $gross, $pfDed, $esiDed, $tds, $pt, $totalDeductions, $net]);

            $totalGross += $gross;
            $totalDed   += $totalDeductions;
            $totalNet   += $net;
        }

        db()->prepare("UPDATE payroll_runs SET total_employees=?, total_gross=?, total_deductions=?, total_net=? WHERE id=?")
            ->execute([count($employees), round($totalGross,2), round($totalDed,2), round($totalNet,2), $runId]);
    }
}
