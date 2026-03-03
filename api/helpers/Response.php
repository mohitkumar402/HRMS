<?php
// ============================================================
// ENTERPRISE HRMS - API RESPONSE HELPER
// ============================================================

class Response {
    public static function json(mixed $data, int $statusCode = 200): void {
        http_response_code($statusCode);
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function success(mixed $data = null, string $message = 'Success', int $code = 200): void {
        self::json(['success' => true, 'message' => $message, 'data' => $data], $code);
    }

    public static function created(mixed $data = null, string $message = 'Created successfully'): void {
        self::success($data, $message, 201);
    }

    public static function paginated(array $data, int $total, int $page, int $limit, string $message = 'Success'): void {
        self::json([
            'success'    => true,
            'message'    => $message,
            'data'       => $data,
            'pagination' => [
                'total'       => $total,
                'page'        => $page,
                'limit'       => $limit,
                'total_pages' => (int) ceil($total / $limit),
                'has_next'    => ($page * $limit) < $total,
                'has_prev'    => $page > 1,
            ],
        ]);
    }

    public static function error(string $message, int $code = 400, mixed $errors = null): void {
        $body = ['success' => false, 'message' => $message];
        if ($errors !== null) $body['errors'] = $errors;
        self::json($body, $code);
    }

    public static function unauthorized(string $message = 'Unauthorized'): void {
        self::error($message, 401);
    }

    public static function forbidden(string $message = 'Forbidden'): void {
        self::error($message, 403);
    }

    public static function notFound(string $message = 'Resource not found'): void {
        self::error($message, 404);
    }

    public static function serverError(string $message = 'Internal server error'): void {
        self::error($message, 500);
    }

    public static function validationError(array $errors): void {
        self::error('Validation failed', 422, $errors);
    }
}

// ============================================================
// VALIDATOR
// ============================================================

class Validator {
    private array $data;
    private array $errors = [];

    public function __construct(array $data) {
        $this->data = $data;
    }

    public static function make(array $data, array $rules): self {
        $v = new self($data);
        foreach ($rules as $field => $rule) {
            $v->applyRules($field, $rule);
        }
        return $v;
    }

    private function applyRules(string $field, string $rules): void {
        $parts = explode('|', $rules);
        $value = $this->data[$field] ?? null;
        foreach ($parts as $rule) {
            [$name, $param] = array_pad(explode(':', $rule, 2), 2, null);
            switch ($name) {
                case 'required':
                    if ($value === null || $value === '') {
                        $this->errors[$field][] = ucfirst(str_replace('_', ' ', $field)) . ' is required';
                    }
                    break;
                case 'email':
                    if ($value && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                        $this->errors[$field][] = 'Invalid email format';
                    }
                    break;
                case 'min':
                    if (is_string($value) && strlen($value) < (int)$param) {
                        $this->errors[$field][] = "Minimum " . $param . " characters required";
                    } elseif (is_numeric($value) && $value < (float)$param) {
                        $this->errors[$field][] = "Minimum value is " . $param;
                    }
                    break;
                case 'max':
                    if (is_string($value) && strlen($value) > (int)$param) {
                        $this->errors[$field][] = "Maximum " . $param . " characters allowed";
                    } elseif (is_numeric($value) && $value > (float)$param) {
                        $this->errors[$field][] = "Maximum value is " . $param;
                    }
                    break;
                case 'numeric':
                    if ($value !== null && $value !== '' && !is_numeric($value)) {
                        $this->errors[$field][] = 'Must be a number';
                    }
                    break;
                case 'date':
                    if ($value && !strtotime($value)) {
                        $this->errors[$field][] = 'Invalid date format';
                    }
                    break;
                case 'in':
                    $allowed = explode(',', $param);
                    if ($value && !in_array($value, $allowed)) {
                        $this->errors[$field][] = 'Invalid value';
                    }
                    break;
            }
        }
    }

    public function fails(): bool { return !empty($this->errors); }
    public function errors(): array { return $this->errors; }
    public function validated(): array {
        $result = [];
        foreach (array_keys($this->data) as $key) {
            if (!isset($this->errors[$key])) {
                $result[$key] = $this->data[$key];
            }
        }
        return $result;
    }
}
