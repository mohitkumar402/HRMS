<?php
// ============================================================
// ENTERPRISE HRMS - JWT HELPER (No external dependencies)
// ============================================================

class JWT {
    private static string $secret;

    public static function init(string $secret): void {
        self::$secret = $secret;
    }

    // Generate access token
    public static function generate(array $payload, int $expiry = JWT_EXPIRY): string {
        $header = self::base64UrlEncode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $payload['iat'] = time();
        $payload['exp'] = time() + $expiry;
        $payload['jti'] = bin2hex(random_bytes(8));
        $encodedPayload  = self::base64UrlEncode(json_encode($payload));
        $signature       = self::base64UrlEncode(self::sign("{$header}.{$encodedPayload}"));
        return "{$header}.{$encodedPayload}.{$signature}";
    }

    // Verify and decode token
    public static function verify(string $token): ?array {
        $parts = explode('.', $token);
        if (count($parts) !== 3) return null;

        [$header, $payload, $signature] = $parts;
        $expectedSig = self::base64UrlEncode(self::sign("{$header}.{$payload}"));

        if (!hash_equals($expectedSig, $signature)) return null;

        $decoded = json_decode(self::base64UrlDecode($payload), true);
        if (!$decoded || !isset($decoded['exp']) || $decoded['exp'] < time()) return null;

        return $decoded;
    }

    // Extract from Authorization header
    public static function extractFromHeader(): ?string {
        $headers = getallheaders();
        $auth    = $headers['Authorization'] ?? $headers['authorization'] ?? '';
        if (preg_match('/^Bearer\s+(.+)$/i', $auth, $matches)) {
            return $matches[1];
        }
        return null;
    }

    private static function sign(string $data): string {
        return hash_hmac('sha256', $data, self::$secret, true);
    }

    private static function base64UrlEncode(string $data): string {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $data): string {
        return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', 3 - (3 + strlen($data)) % 4));
    }
}

JWT::init(JWT_SECRET);
