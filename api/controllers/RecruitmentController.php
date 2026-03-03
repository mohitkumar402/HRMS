<?php
// ============================================================
// ENTERPRISE HRMS - RECRUITMENT CONTROLLER
// ============================================================

class RecruitmentController {

    // GET /api/recruitment/jobs
    public static function jobs(): void {
        AuthMiddleware::authenticate();
        AuthMiddleware::requirePermission('recruitment.view');

        $page   = max(1,(int)($_GET['page'] ?? 1));
        $limit  = min((int)($_GET['limit'] ?? DEFAULT_PAGE_SIZE), MAX_PAGE_SIZE);
        $offset = ($page-1)*$limit;
        $where  = ['1=1'];
        $params = [];

        if (!empty($_GET['status']))        { $where[] = 'jp.status = ?';        $params[] = $_GET['status']; }
        if (!empty($_GET['department_id'])) { $where[] = 'jp.department_id = ?'; $params[] = $_GET['department_id']; }
        if (!empty($_GET['search'])) {
            $s = '%' . $_GET['search'] . '%';
            $where[] = '(jp.title LIKE ? OR jp.requisition_number LIKE ?)';
            $params  = array_merge($params, [$s, $s]);
        }
        $whereStr = implode(' AND ', $where);

        $count = db()->prepare("SELECT COUNT(*) FROM job_postings jp WHERE {$whereStr}");
        $count->execute($params);
        $total = (int)$count->fetchColumn();

        $stmt = db()->prepare("SELECT jp.*, d.name as department_name, des.title as designation_title,
            l.name as location_name,
            COUNT(DISTINCT a.id) as application_count,
            COUNT(DISTINCT CASE WHEN a.stage = 'offered' THEN a.id END) as offers_count
            FROM job_postings jp
            LEFT JOIN departments d ON d.id = jp.department_id
            LEFT JOIN designations des ON des.id = jp.designation_id
            LEFT JOIN locations l ON l.id = jp.location_id
            LEFT JOIN applications a ON a.job_id = jp.id
            WHERE {$whereStr}
            GROUP BY jp.id
            ORDER BY jp.created_at DESC
            LIMIT {$limit} OFFSET {$offset}");
        $stmt->execute($params);
        Response::paginated($stmt->fetchAll(), $total, $page, $limit);
    }

    // POST /api/recruitment/jobs
    public static function createJob(): void {
        $user = AuthMiddleware::authenticate();
        AuthMiddleware::requirePermission('recruitment.manage');

        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $v = Validator::make($body, ['title' => 'required|min:3', 'description' => 'required|min:20']);
        if ($v->fails()) Response::validationError($v->errors());

        // Auto requisition number
        $last = db()->query("SELECT requisition_number FROM job_postings ORDER BY id DESC LIMIT 1")->fetch();
        $num  = $last ? (int)substr($last['requisition_number'], -3) + 1 : 1;
        $reqNum = 'REQ-' . date('Y') . '-' . str_pad($num, 3, '0', STR_PAD_LEFT);

        $fields = ['title','department_id','designation_id','location_id','employment_type',
                   'experience_min','experience_max','salary_min','salary_max','openings',
                   'description','requirements','skills_required','posted_date','closing_date',
                   'status','is_internal'];

        $data = ['requisition_number' => $reqNum, 'posted_by' => $user['id']];
        foreach ($fields as $f) {
            if (isset($body[$f])) {
                $data[$f] = $f === 'skills_required' ? json_encode($body[$f]) : ($body[$f] ?: null);
            }
        }

        $cols = implode(',', array_keys($data));
        $pl   = implode(',', array_fill(0, count($data), '?'));
        db()->prepare("INSERT INTO job_postings ({$cols}) VALUES ({$pl})")->execute(array_values($data));

        Response::created(['id' => db()->lastInsertId(), 'requisition_number' => $reqNum], 'Job posting created');
    }

    // PUT /api/recruitment/jobs/{id}
    public static function updateJob(int $id): void {
        AuthMiddleware::authenticate();
        AuthMiddleware::requirePermission('recruitment.manage');

        $job = db()->prepare("SELECT id FROM job_postings WHERE id = ?");
        $job->execute([$id]);
        if (!$job->fetch()) Response::notFound();

        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $updatable = ['title','department_id','designation_id','location_id','employment_type',
                      'experience_min','experience_max','salary_min','salary_max','openings',
                      'description','requirements','skills_required','posted_date','closing_date',
                      'status','is_internal'];

        $sets = []; $params = [];
        foreach ($updatable as $f) {
            if (array_key_exists($f, $body)) {
                $sets[]   = "{$f} = ?";
                $params[] = $f === 'skills_required' ? json_encode($body[$f]) : ($body[$f] ?: null);
            }
        }
        if (!$sets) Response::error('No fields to update');
        $params[] = $id;
        db()->prepare("UPDATE job_postings SET " . implode(',', $sets) . " WHERE id=?")->execute($params);
        Response::success(null, 'Job posting updated');
    }

    // GET /api/recruitment/jobs/{id}/applications
    public static function jobApplications(int $jobId): void {
        AuthMiddleware::authenticate();
        AuthMiddleware::requirePermission('recruitment.view');

        $stage = $_GET['stage'] ?? null;
        $where = 'a.job_id = ?';
        $params = [$jobId];
        if ($stage) { $where .= ' AND a.stage = ?'; $params[] = $stage; }

        $stmt = db()->prepare("SELECT a.*, c.first_name, c.last_name, c.email, c.phone,
            c.current_company, c.current_title, c.experience_years, c.current_ctc, c.expected_ctc,
            c.notice_period, c.resume_path, c.source, c.skills
            FROM applications a JOIN candidates c ON c.id = a.candidate_id
            WHERE {$where} ORDER BY a.applied_date DESC");
        $stmt->execute($params);

        $pipeline = db()->prepare("SELECT stage, COUNT(*) as count FROM applications WHERE job_id = ? GROUP BY stage");
        $pipeline->execute([$jobId]);

        Response::success(['applications' => $stmt->fetchAll(), 'pipeline' => $pipeline->fetchAll()]);
    }

    // PUT /api/recruitment/applications/{id}/stage
    public static function updateStage(int $id): void {
        AuthMiddleware::authenticate();
        AuthMiddleware::requirePermission('recruitment.manage');

        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $validStages = ['applied','screening','shortlisted','phone_screen','assessment','technical_round','hr_round','final_round','offered','accepted','rejected','withdrawn','on_hold'];
        if (!in_array($body['stage'] ?? '', $validStages)) Response::error('Invalid stage');

        $stmt = db()->prepare("SELECT a.*, c.first_name, c.last_name FROM applications a JOIN candidates c ON c.id = a.candidate_id WHERE a.id = ?");
        $stmt->execute([$id]);
        $app = $stmt->fetch();
        if (!$app) Response::notFound();

        db()->prepare("UPDATE applications SET stage=?, remarks=?, updated_by=? WHERE id=?")
            ->execute([$body['stage'], $body['remarks'] ?? null, AuthMiddleware::user()['id'], $id]);

        Response::success(null, "Stage updated to " . ucfirst(str_replace('_', ' ', $body['stage'])));
    }

    // POST /api/recruitment/applications/{id}/interview
    public static function scheduleInterview(int $appId): void {
        AuthMiddleware::authenticate();
        AuthMiddleware::requirePermission('recruitment.manage');

        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $v = Validator::make($body, ['scheduled_at' => 'required', 'interview_type' => 'required']);
        if ($v->fails()) Response::validationError($v->errors());

        $app = db()->prepare("SELECT a.*, c.first_name, c.last_name, c.email FROM applications a JOIN candidates c ON c.id = a.candidate_id WHERE a.id = ?");
        $app->execute([$appId]);
        $appData = $app->fetch();
        if (!$appData) Response::notFound();

        $lastRound = db()->prepare("SELECT MAX(round_number) FROM interviews WHERE application_id = ?");
        $lastRound->execute([$appId]);
        $nextRound = ((int)$lastRound->fetchColumn()) + 1;

        db()->prepare("INSERT INTO interviews (application_id, round_number, interview_type, scheduled_at, duration_mins, location, meeting_link) VALUES (?,?,?,?,?,?,?)")
            ->execute([$appId, $nextRound, $body['interview_type'], $body['scheduled_at'], $body['duration_mins'] ?? 60, $body['location'] ?? null, $body['meeting_link'] ?? null]);

        $interviewId = db()->lastInsertId();

        // Add panelists
        if (!empty($body['panelists']) && is_array($body['panelists'])) {
            foreach ($body['panelists'] as $panelist) {
                db()->prepare("INSERT INTO interview_panelists (interview_id, employee_id, is_lead) VALUES (?,?,?)")
                    ->execute([$interviewId, $panelist['employee_id'], $panelist['is_lead'] ?? 0]);
            }
        }

        Response::created(['interview_id' => $interviewId, 'round' => $nextRound], 'Interview scheduled');
    }

    // PUT /api/recruitment/interviews/{id}/feedback
    public static function submitFeedback(int $id): void {
        AuthMiddleware::authenticate();

        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        db()->prepare("UPDATE interviews SET status=?, rating=?, feedback=?, recommendation=? WHERE id=?")
            ->execute([$body['status'] ?? 'completed', $body['rating'] ?? null, $body['feedback'] ?? null, $body['recommendation'] ?? null, $id]);

        Response::success(null, 'Feedback submitted');
    }

    // POST /api/recruitment/candidates
    public static function createCandidate(): void {
        AuthMiddleware::authenticate();
        AuthMiddleware::requirePermission('recruitment.manage');

        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $v = Validator::make($body, [
            'first_name' => 'required|min:2',
            'last_name'  => 'required|min:2',
            'email'      => 'required|email',
            'phone'      => 'required|min:10',
        ]);
        if ($v->fails()) Response::validationError($v->errors());

        // Check duplicate
        $check = db()->prepare("SELECT id FROM candidates WHERE email = ?");
        $check->execute([$body['email']]);
        if ($check->fetch()) Response::error('Candidate email already exists', 409);

        $fields = ['first_name','last_name','email','phone','current_company','current_title',
                   'experience_years','current_ctc','expected_ctc','notice_period','linkedin_url','source'];
        $data = [];
        foreach ($fields as $f) { if (isset($body[$f])) $data[$f] = $body[$f] ?: null; }
        if (!empty($body['skills'])) $data['skills'] = json_encode($body['skills']);

        $cols = implode(',', array_keys($data));
        $pl   = implode(',', array_fill(0, count($data), '?'));
        db()->prepare("INSERT INTO candidates ({$cols}) VALUES ({$pl})")->execute(array_values($data));

        Response::created(['id' => db()->lastInsertId()], 'Candidate created');
    }

    // GET /api/recruitment/stats
    public static function stats(): void {
        AuthMiddleware::authenticate();
        AuthMiddleware::requirePermission('recruitment.view');

        $openJobs = db()->query("SELECT COUNT(*) FROM job_postings WHERE status='open'")->fetchColumn();
        $totalApps = db()->query("SELECT COUNT(*) FROM applications")->fetchColumn();
        $stageBreakdown = db()->query("SELECT stage, COUNT(*) as count FROM applications GROUP BY stage")->fetchAll();
        $topJobs = db()->query("SELECT jp.title, COUNT(a.id) as applications FROM job_postings jp LEFT JOIN applications a ON a.job_id = jp.id WHERE jp.status='open' GROUP BY jp.id ORDER BY applications DESC LIMIT 5")->fetchAll();
        $sourceBreakdown = db()->query("SELECT source, COUNT(*) as count FROM candidates GROUP BY source ORDER BY count DESC")->fetchAll();

        $timeToHire = db()->query("SELECT ROUND(AVG(DATEDIFF(a.updated_at, a.applied_date)), 1) as avg_days FROM applications a WHERE a.stage IN ('accepted','offered')")->fetchColumn();

        Response::success([
            'open_jobs'       => $openJobs,
            'total_applications' => $totalApps,
            'stage_breakdown' => $stageBreakdown,
            'top_jobs'        => $topJobs,
            'source_breakdown'=> $sourceBreakdown,
            'avg_time_to_hire_days' => $timeToHire,
        ]);
    }
}
