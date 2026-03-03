<?php
// ============================================================
// ENTERPRISE HRMS - API ROUTER (index.php)
// ============================================================

// Autoload config & helpers
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/helpers/JWT.php';
require_once __DIR__ . '/helpers/Response.php';
require_once __DIR__ . '/middleware/Auth.php';
require_once __DIR__ . '/controllers/AuthController.php';
require_once __DIR__ . '/controllers/EmployeeController.php';
require_once __DIR__ . '/controllers/AttendanceController.php';
require_once __DIR__ . '/controllers/LeaveController.php';
require_once __DIR__ . '/controllers/PayrollController.php';
require_once __DIR__ . '/controllers/RecruitmentController.php';
require_once __DIR__ . '/controllers/PerformanceController.php';
require_once __DIR__ . '/controllers/DashboardController.php';
require_once __DIR__ . '/controllers/ReportController.php';

// CORS Headers
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, PATCH, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Max-Age: 86400');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Parse path
$requestUri    = $_SERVER['REQUEST_URI'] ?? '/';
$basePath      = parse_url($requestUri, PHP_URL_PATH);
$scriptDir     = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
$path          = '/' . ltrim(substr($basePath, strlen($scriptDir)), '/');
$path          = rtrim($path, '/') ?: '/';
$method        = $_SERVER['REQUEST_METHOD'];
$segments      = array_values(array_filter(explode('/', trim($path, '/'))));

// ============================================================
// ROUTE DISPATCHER
// ============================================================
try {
    $r0 = $segments[0] ?? '';
    $r1 = $segments[1] ?? '';
    $r2 = isset($segments[2]) ? (int)$segments[2] : 0;
    $r3 = $segments[3] ?? '';
    $r4 = $segments[4] ?? '';

    // Health check
    if ($path === '/health' && $method === 'GET') {
        Response::success(['status' => 'ok', 'version' => APP_VERSION, 'timestamp' => date('c')]);
    }

    // ── AUTH ─────────────────────────────────────────────────
    if ($r0 === 'auth') {
        match([$method, $r1]) {
            ['POST', 'login']           => AuthController::login(),
            ['POST', 'logout']          => AuthController::logout(),
            ['POST', 'refresh']         => AuthController::refresh(),
            ['GET',  'me']              => AuthController::me(),
            ['POST', 'change-password'] => AuthController::changePassword(),
            default                     => Response::notFound("Auth route not found"),
        };
    }

    // ── DASHBOARD ────────────────────────────────────────────
    if ($r0 === 'dashboard') {
        DashboardController::index();
    }

    // ── EMPLOYEES ────────────────────────────────────────────
    if ($r0 === 'employees') {
        if ($r1 === 'stats' && $method === 'GET') EmployeeController::stats();
        elseif ($r1 === '' && $method === 'GET') EmployeeController::index();
        elseif ($r1 === '' && $method === 'POST') EmployeeController::store();
        elseif (is_numeric($r1) && $r3 === 'org-chart' && $method === 'GET') EmployeeController::orgChart((int)$r1);
        elseif (is_numeric($r1) && $method === 'GET')    EmployeeController::show((int)$r1);
        elseif (is_numeric($r1) && $method === 'PUT')    EmployeeController::update((int)$r1);
        elseif (is_numeric($r1) && $method === 'DELETE') EmployeeController::destroy((int)$r1);
        else Response::notFound();
    }

    // ── ATTENDANCE ───────────────────────────────────────────
    if ($r0 === 'attendance') {
        if ($r1 === 'checkin'  && $method === 'POST') AttendanceController::checkIn();
        elseif ($r1 === 'checkout' && $method === 'POST') AttendanceController::checkOut();
        elseif ($r1 === 'today' && $method === 'GET') AttendanceController::today();
        elseif ($r1 === 'my-status' && $method === 'GET') AttendanceController::myStatus();
        elseif ($r1 === 'report' && $method === 'GET') AttendanceController::report();
        elseif ($r1 === 'regularize' && $method === 'POST') AttendanceController::regularize();
        elseif ($r1 === '' && $method === 'GET') AttendanceController::index();
        else Response::notFound();
    }

    // ── LEAVES ───────────────────────────────────────────────
    if ($r0 === 'leaves') {
        if ($r1 === 'balance' && $method === 'GET') LeaveController::balance();
        elseif ($r1 === 'types' && $method === 'GET') LeaveController::types();
        elseif ($r1 === 'calendar' && $method === 'GET') LeaveController::calendar();
        elseif ($r1 === '' && $method === 'GET') LeaveController::index();
        elseif ($r1 === '' && $method === 'POST') LeaveController::apply();
        elseif (is_numeric($r1) && $r3 === 'approve' && $method === 'PUT') LeaveController::approve((int)$r1);
        elseif (is_numeric($r1) && $r3 === 'cancel' && $method === 'PUT') LeaveController::cancel((int)$r1);
        else Response::notFound();
    }

    // ── PAYROLL ──────────────────────────────────────────────
    if ($r0 === 'payroll') {
        if ($r1 === 'runs' && $r2 === 0 && $method === 'GET')  PayrollController::runs();
        elseif ($r1 === 'runs' && $r2 === 0 && $method === 'POST') PayrollController::createRun();
        elseif ($r1 === 'runs' && $r2 > 0   && $r3 === 'approve' && $method === 'PUT') PayrollController::approveRun($r2);
        elseif ($r1 === 'runs' && $r2 > 0   && $method === 'GET') PayrollController::getRun($r2);
        elseif ($r1 === 'payslip' && $r2 > 0 && $method === 'GET') PayrollController::getPayslip($r2);
        elseif ($r1 === 'my-payslips' && $method === 'GET') PayrollController::myPayslips();
        elseif ($r1 === 'salary-structures' && $method === 'GET') PayrollController::salaryStructures();
        elseif ($r1 === 'salary-structures' && $method === 'POST') PayrollController::storeSalaryStructure();
        elseif ($r1 === 'analytics' && $method === 'GET') PayrollController::analytics();
        else Response::notFound();
    }

    // ── RECRUITMENT ──────────────────────────────────────────
    if ($r0 === 'recruitment') {
        if ($r1 === 'jobs' && $r2 === 0 && $method === 'GET')  RecruitmentController::jobs();
        elseif ($r1 === 'jobs' && $r2 === 0 && $method === 'POST') RecruitmentController::createJob();
        elseif ($r1 === 'jobs' && $r2 > 0 && $method === 'PUT') RecruitmentController::updateJob($r2);
        elseif ($r1 === 'jobs' && $r2 > 0 && $r3 === 'applications' && $method === 'GET') RecruitmentController::jobApplications($r2);
        elseif ($r1 === 'applications' && $r2 > 0 && $r3 === 'stage' && $method === 'PUT') RecruitmentController::updateStage($r2);
        elseif ($r1 === 'applications' && $r2 > 0 && $r3 === 'interview' && $method === 'POST') RecruitmentController::scheduleInterview($r2);
        elseif ($r1 === 'interviews' && $r2 > 0 && $r3 === 'feedback' && $method === 'PUT') RecruitmentController::submitFeedback($r2);
        elseif ($r1 === 'candidates' && $method === 'POST') RecruitmentController::createCandidate();
        elseif ($r1 === 'stats' && $method === 'GET') RecruitmentController::stats();
        else Response::notFound();
    }

    // ── PERFORMANCE ──────────────────────────────────────────
    if ($r0 === 'performance') {
        if ($r1 === 'cycles' && $method === 'GET')  PerformanceController::cycles();
        elseif ($r1 === 'cycles' && $method === 'POST') PerformanceController::createCycle();
        elseif ($r1 === 'goals' && $method === 'GET')  PerformanceController::goals();
        elseif ($r1 === 'goals' && $method === 'POST') PerformanceController::createGoal();
        elseif ($r1 === 'goals' && $r2 > 0 && $method === 'PUT') PerformanceController::updateGoal($r2);
        elseif ($r1 === 'reviews' && $method === 'GET')  PerformanceController::reviews();
        elseif ($r1 === 'reviews' && $method === 'POST') PerformanceController::createReview();
        elseif ($r1 === 'reviews' && $r2 > 0 && $r3 === 'submit' && $method === 'PUT') PerformanceController::submitReview($r2);
        elseif ($r1 === 'stats' && $method === 'GET') PerformanceController::stats();
        else Response::notFound();
    }

    // ── REPORTS & MISC ────────────────────────────────────────
    if ($r0 === 'reports') {
        match([$method, $r1]) {
            ['GET', 'headcount']         => ReportController::headcount(),
            ['GET', 'attrition']         => ReportController::attrition(),
            ['GET', 'attendance-summary']=> ReportController::attendanceSummary(),
            ['GET', 'leave-summary']     => ReportController::leaveSummary(),
            ['GET', 'payroll-summary']   => ReportController::payrollSummary(),
            ['GET', 'diversity']         => ReportController::diversity(),
            ['GET', 'recruitment']       => ReportController::recruitment(),
            default                      => Response::notFound(),
        };
    }

    if ($r0 === 'departments' && $method === 'GET') ReportController::departments();
    if ($r0 === 'locations'   && $method === 'GET') ReportController::locations();
    if ($r0 === 'settings'    && $method === 'GET') ReportController::getSettings();
    if ($r0 === 'settings'    && $method === 'PUT') ReportController::updateSettings();
    if ($r0 === 'notifications' && $method === 'GET') ReportController::notifications();
    if ($r0 === 'notifications' && $method === 'PUT') ReportController::markRead();

    Response::notFound('API endpoint not found');

} catch (PDOException $e) {
    error_log('DB Error: ' . $e->getMessage());
    Response::serverError(APP_ENV === 'development' ? $e->getMessage() : 'A database error occurred');
} catch (Exception $e) {
    error_log('Error: ' . $e->getMessage());
    Response::serverError(APP_ENV === 'development' ? $e->getMessage() : 'An unexpected error occurred');
}
