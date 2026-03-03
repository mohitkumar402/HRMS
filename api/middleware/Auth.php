<?php
// ============================================================
// ENTERPRISE HRMS - AUTH MIDDLEWARE
// ============================================================

class AuthMiddleware {
    private static ?array $currentUser = null;

    // Authenticate request; sets $currentUser or aborts
    public static function authenticate(): array {
        $token = JWT::extractFromHeader();
        if (!$token) Response::unauthorized('No authentication token provided');

        $payload = JWT::verify($token);
        if (!$payload) Response::unauthorized('Invalid or expired token');

        // Verify user still active in DB
        $stmt = db()->prepare("SELECT u.id, u.email, u.role_id, u.is_active, r.slug as role_slug, e.id as employee_id, e.first_name, e.last_name, e.profile_photo
            FROM users u
            LEFT JOIN roles r ON r.id = u.role_id
            LEFT JOIN employees e ON e.user_id = u.id
            WHERE u.id = ? AND u.is_active = 1");
        $stmt->execute([$payload['sub']]);
        $user = $stmt->fetch();

        if (!$user) Response::unauthorized('User account not found or inactive');

        self::$currentUser = $user;
        return $user;
    }

    // Check permission
    public static function can(string $permission): bool {
        if (!self::$currentUser) return false;
        if (self::$currentUser['role_slug'] === 'super_admin') return true;

        $stmt = db()->prepare("SELECT p.id FROM permissions p
            JOIN role_permissions rp ON rp.permission_id = p.id
            WHERE rp.role_id = ? AND p.slug = ?");
        $stmt->execute([self::$currentUser['role_id'], $permission]);
        return $stmt->fetch() !== false;
    }

    // Require permission or abort
    public static function requirePermission(string $permission): void {
        if (!self::can($permission)) Response::forbidden("Insufficient permissions: {$permission} required");
    }

    // Require one of the roles
    public static function requireRole(array $roles): void {
        if (!in_array(self::$currentUser['role_slug'] ?? '', $roles)) {
            Response::forbidden('Access denied for your role');
        }
    }

    public static function user(): ?array { return self::$currentUser; }

    // Log audit event
    public static function audit(string $action, string $module, ?int $recordId = null, ?array $old = null, ?array $new = null): void {
        try {
            $user = self::$currentUser;
            $stmt = db()->prepare("INSERT INTO audit_logs (user_id, action, module, record_id, old_values, new_values, ip_address, user_agent)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $user['id'] ?? null, $action, $module, $recordId,
                $old ? json_encode($old) : null,
                $new  ? json_encode($new)  : null,
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null,
            ]);
        } catch (Exception $e) {
            // Non-fatal: log silently
        }
    }
}
