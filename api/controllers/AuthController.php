<?php
// ============================================================
// ENTERPRISE HRMS - AUTH CONTROLLER
// ============================================================

class AuthController {

    // POST /api/auth/login
    public static function login(): void {
        $body = json_decode(file_get_contents('php://input'), true) ?? [];

        $v = Validator::make($body, ['email' => 'required|email', 'password' => 'required|min:6']);
        if ($v->fails()) Response::validationError($v->errors());

        $email    = strtolower(trim($body['email']));
        $password = $body['password'];

        // Fetch user
        $stmt = db()->prepare("SELECT u.*, r.slug as role_slug, r.name as role_name
            FROM users u JOIN roles r ON r.id = u.role_id
            WHERE u.email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user) Response::unauthorized('Invalid credentials');

        // Check lock
        if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
            $mins = ceil((strtotime($user['locked_until']) - time()) / 60);
            Response::error("Account locked. Try again in {$mins} minutes.", 423);
        }

        if (!$user['is_active']) Response::error('Account is deactivated. Contact HR.', 403);

        // Verify password
        if (!password_verify($password, $user['password_hash'])) {
            $attempts = $user['login_attempts'] + 1;
            if ($attempts >= 5) {
                db()->prepare("UPDATE users SET login_attempts=?, locked_until=DATE_ADD(NOW(), INTERVAL 30 MINUTE) WHERE id=?")
                    ->execute([$attempts, $user['id']]);
                Response::error('Too many failed attempts. Account locked for 30 minutes.', 423);
            }
            db()->prepare("UPDATE users SET login_attempts=? WHERE id=?")->execute([$attempts, $user['id']]);
            Response::unauthorized('Invalid credentials');
        }

        // Fetch employee info
        $empStmt = db()->prepare("SELECT e.id, e.employee_id, e.first_name, e.last_name, e.work_email, e.profile_photo, e.department_id, e.designation_id, d.name as department_name, des.title as designation_title
            FROM employees e
            LEFT JOIN departments d ON d.id = e.department_id
            LEFT JOIN designations des ON des.id = e.designation_id
            WHERE e.user_id = ?");
        $empStmt->execute([$user['id']]);
        $employee = $empStmt->fetch();

        // Reset failed attempts, update last login
        db()->prepare("UPDATE users SET login_attempts=0, locked_until=NULL, last_login=NOW() WHERE id=?")
            ->execute([$user['id']]);

        // Generate tokens
        $payload = [
            'sub'       => $user['id'],
            'email'     => $user['email'],
            'role'      => $user['role_slug'],
            'emp_id'    => $employee['id'] ?? null,
        ];
        $accessToken  = JWT::generate($payload, JWT_EXPIRY);
        $refreshToken = JWT::generate(array_merge($payload, ['type' => 'refresh']), JWT_REFRESH);

        AuthMiddleware::audit('login', 'auth', $user['id']);

        Response::success([
            'access_token'  => $accessToken,
            'refresh_token' => $refreshToken,
            'expires_in'    => JWT_EXPIRY,
            'user' => [
                'id'           => $user['id'],
                'email'        => $user['email'],
                'role'         => $user['role_slug'],
                'role_name'    => $user['role_name'],
                'employee'     => $employee,
            ],
        ], 'Login successful');
    }

    // POST /api/auth/logout
    public static function logout(): void {
        $user = AuthMiddleware::authenticate();
        AuthMiddleware::audit('logout', 'auth', $user['id']);
        Response::success(null, 'Logged out successfully');
    }

    // POST /api/auth/refresh
    public static function refresh(): void {
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        if (empty($body['refresh_token'])) Response::error('Refresh token required');

        $payload = JWT::verify($body['refresh_token']);
        if (!$payload || ($payload['type'] ?? '') !== 'refresh') Response::unauthorized('Invalid refresh token');

        $newToken = JWT::generate([
            'sub'    => $payload['sub'],
            'email'  => $payload['email'],
            'role'   => $payload['role'],
            'emp_id' => $payload['emp_id'],
        ]);
        Response::success(['access_token' => $newToken, 'expires_in' => JWT_EXPIRY]);
    }

    // GET /api/auth/me
    public static function me(): void {
        $user = AuthMiddleware::authenticate();

        $stmt = db()->prepare("SELECT u.id, u.email, u.last_login, u.two_factor_enabled,
            r.name as role_name, r.slug as role_slug,
            e.id as emp_id, e.employee_id, e.first_name, e.last_name, e.profile_photo,
            e.work_phone, e.department_id, e.designation_id, e.employment_status,
            d.name as department_name, des.title as designation_title
            FROM users u
            JOIN roles r ON r.id = u.role_id
            LEFT JOIN employees e ON e.user_id = u.id
            LEFT JOIN departments d ON d.id = e.department_id
            LEFT JOIN designations des ON des.id = e.designation_id
            WHERE u.id = ?");
        $stmt->execute([$user['id']]);
        $profile = $stmt->fetch();

        // Get permissions
        $permStmt = db()->prepare("SELECT p.slug FROM permissions p
            JOIN role_permissions rp ON rp.permission_id = p.id
            WHERE rp.role_id = ?");
        $permStmt->execute([$user['role_id']]);
        $permissions = array_column($permStmt->fetchAll(), 'slug');

        Response::success([
            'user' => $profile,
            'permissions' => $permissions,
        ]);
    }

    // POST /api/auth/change-password
    public static function changePassword(): void {
        $user = AuthMiddleware::authenticate();
        $body = json_decode(file_get_contents('php://input'), true) ?? [];

        $v = Validator::make($body, [
            'current_password' => 'required',
            'new_password'     => 'required|min:8',
        ]);
        if ($v->fails()) Response::validationError($v->errors());

        $stmt = db()->prepare("SELECT password_hash FROM users WHERE id = ?");
        $stmt->execute([$user['id']]);
        $dbUser = $stmt->fetch();

        if (!password_verify($body['current_password'], $dbUser['password_hash'])) {
            Response::error('Current password is incorrect', 400);
        }

        $newHash = password_hash($body['new_password'], PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
        db()->prepare("UPDATE users SET password_hash = ? WHERE id = ?")->execute([$newHash, $user['id']]);

        AuthMiddleware::audit('change_password', 'auth', $user['id']);
        Response::success(null, 'Password changed successfully');
    }
}
