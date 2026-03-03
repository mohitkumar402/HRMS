<?php
// ============================================================
// ENTERPRISE HRMS - DASHBOARD CONTROLLER
// ============================================================

class DashboardController {

    // GET /api/dashboard
    public static function index(): void {
        $user = AuthMiddleware::authenticate();
        $roleSlug = $user['role_slug'];

        if ($roleSlug === 'employee') {
            self::employeeDashboard($user);
        } else {
            self::hrDashboard();
        }
    }

    private static function hrDashboard(): void {
        $pdo = db();

        // Employee overview
        $empOverview = $pdo->query("SELECT
            COUNT(*) as total,
            SUM(employment_status='active') as active,
            SUM(employment_status='probation') as probation,
            SUM(date_joined >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)) as new_this_month,
            SUM(last_working_day >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) AND employment_status IN ('terminated','resigned')) as exits_this_month
            FROM employees")->fetch();

        // Today's attendance
        $todayAtt = $pdo->query("SELECT
            COUNT(DISTINCT e.id) as total_employees,
            SUM(a.status='present') as present,
            SUM(a.status='late') as late,
            SUM(a.status='wfh') as wfh,
            SUM(a.status='leave') as on_leave,
            SUM(a.check_in IS NOT NULL AND a.check_out IS NULL) as currently_in
            FROM employees e
            LEFT JOIN attendance a ON a.employee_id = e.id AND a.attendance_date = CURDATE()
            WHERE e.employment_status IN ('active','probation')")->fetch();

        // Pending approvals
        $pendingLeaves = $pdo->query("SELECT COUNT(*) FROM leave_requests WHERE status='pending'")->fetchColumn();
        $openJobs      = $pdo->query("SELECT COUNT(*) FROM job_postings WHERE status='open'")->fetchColumn();
        $pendingOnboard= $pdo->query("SELECT COUNT(*) FROM onboarding_tasks WHERE status IN ('pending','in_progress')")->fetchColumn();

        // Headcount trend (last 6 months)
        $headcountTrend = $pdo->query("SELECT
            DATE_FORMAT(date_joined,'%Y-%m') as month,
            COUNT(*) as joinings
            FROM employees
            WHERE date_joined >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
            GROUP BY month ORDER BY month")->fetchAll();

        // Department headcount
        $deptCount = $pdo->query("SELECT d.name, COUNT(e.id) as count
            FROM employees e JOIN departments d ON d.id = e.department_id
            WHERE e.employment_status IN ('active','probation')
            GROUP BY d.id, d.name ORDER BY count DESC LIMIT 8")->fetchAll();

        // Gender distribution
        $genderDist = $pdo->query("SELECT gender, COUNT(*) as count FROM employees WHERE employment_status IN ('active','probation') GROUP BY gender")->fetchAll();

        // Upcoming birthdays (next 7 days)
        $birthdays = $pdo->query("SELECT first_name, last_name, employee_id, profile_photo, date_of_birth,
            DATE_FORMAT(date_of_birth, CONCAT(YEAR(CURDATE()),'-','%m-%d')) as this_year_bday
            FROM employees
            WHERE employment_status IN ('active','probation')
            AND DATE_FORMAT(date_of_birth,'%m-%d') BETWEEN DATE_FORMAT(CURDATE(),'%m-%d') AND DATE_FORMAT(DATE_ADD(CURDATE(), INTERVAL 7 DAY),'%m-%d')
            ORDER BY this_year_bday LIMIT 5")->fetchAll();

        // Work anniversaries (this month)
        $anniversaries = $pdo->query("SELECT first_name, last_name, employee_id, profile_photo, date_joined,
            YEAR(CURDATE()) - YEAR(date_joined) as years_completed
            FROM employees
            WHERE employment_status IN ('active','probation')
            AND MONTH(date_joined) = MONTH(CURDATE()) AND DAY(date_joined) >= DAY(CURDATE())
            ORDER BY DAY(date_joined) LIMIT 5")->fetchAll();

        // Recent payroll
        $lastPayroll = $pdo->query("SELECT month, year, total_employees, total_net, status, created_at
            FROM payroll_runs ORDER BY year DESC, month DESC LIMIT 3")->fetchAll();

        // Open recruitment
        $openRecruitment = $pdo->query("SELECT jp.title, jp.openings, COUNT(a.id) as applications, jp.closing_date
            FROM job_postings jp LEFT JOIN applications a ON a.job_id = jp.id
            WHERE jp.status='open' GROUP BY jp.id ORDER BY jp.created_at DESC LIMIT 5")->fetchAll();

        // Attrition (last 12 months)
        $attrition = $pdo->query("SELECT
            DATE_FORMAT(last_working_day,'%Y-%m') as month,
            COUNT(*) as exits
            FROM employees
            WHERE last_working_day >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
            GROUP BY month ORDER BY month")->fetchAll();

        Response::success([
            'employee_overview'  => $empOverview,
            'today_attendance'   => $todayAtt,
            'pending_leaves'     => $pendingLeaves,
            'open_jobs'          => $openJobs,
            'pending_onboarding' => $pendingOnboard,
            'headcount_trend'    => $headcountTrend,
            'dept_headcount'     => $deptCount,
            'gender_distribution'=> $genderDist,
            'upcoming_birthdays' => $birthdays,
            'anniversaries'      => $anniversaries,
            'recent_payroll'     => $lastPayroll,
            'open_recruitment'   => $openRecruitment,
            'attrition_trend'    => $attrition,
        ]);
    }

    private static function employeeDashboard(array $user): void {
        $empId = $user['employee_id'];
        $pdo   = db();

        // Today attendance
        $todayAtt = $pdo->prepare("SELECT * FROM attendance WHERE employee_id = ? AND attendance_date = CURDATE()");
        $todayAtt->execute([$empId]);

        // Leave balance
        $leaveBal = $pdo->prepare("SELECT lb.balance, lt.name, lt.code, lt.color FROM leave_balances lb JOIN leave_types lt ON lt.id = lb.leave_type_id WHERE lb.employee_id = ? AND lb.year = YEAR(CURDATE())");
        $leaveBal->execute([$empId]);

        // Pending leave requests
        $pendingLeaves = $pdo->prepare("SELECT COUNT(*) FROM leave_requests WHERE employee_id = ? AND status='pending'");
        $pendingLeaves->execute([$empId]);

        // My goals
        $myGoals = $pdo->prepare("SELECT id, title, achievement, status, due_date FROM goals WHERE employee_id = ? AND status != 'cancelled' ORDER BY due_date ASC LIMIT 5");
        $myGoals->execute([$empId]);

        // Notifications
        $notifs = $pdo->prepare("SELECT id, type, title, message, is_read, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
        $notifs->execute([$user['id']]);

        // This month attendance summary
        $monthSummary = $pdo->prepare("SELECT SUM(status='present') as present, SUM(status='late') as late, SUM(status='absent') as absent, SUM(status='wfh') as wfh FROM attendance WHERE employee_id = ? AND MONTH(attendance_date) = MONTH(CURDATE()) AND YEAR(attendance_date) = YEAR(CURDATE())");
        $monthSummary->execute([$empId]);

        // Payslips
        $payslips = $pdo->prepare("SELECT month, year, net_salary, status FROM payslips WHERE employee_id = ? ORDER BY year DESC, month DESC LIMIT 3");
        $payslips->execute([$empId]);

        // Upcoming training
        $training = $pdo->prepare("SELECT tp.title, tp.start_date, tp.mode, te.status FROM training_enrollments te JOIN training_programs tp ON tp.id = te.program_id WHERE te.employee_id = ? AND tp.start_date >= CURDATE() ORDER BY tp.start_date LIMIT 3");
        $training->execute([$empId]);

        Response::success([
            'today_attendance'  => $todayAtt->fetch(),
            'leave_balances'    => $leaveBal->fetchAll(),
            'pending_leaves'    => (int)$pendingLeaves->fetchColumn(),
            'my_goals'          => $myGoals->fetchAll(),
            'notifications'     => $notifs->fetchAll(),
            'month_attendance'  => $monthSummary->fetch(),
            'recent_payslips'   => $payslips->fetchAll(),
            'upcoming_training' => $training->fetchAll(),
        ]);
    }
}
