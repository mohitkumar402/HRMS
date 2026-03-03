<?php
// ============================================================
// ENTERPRISE HRMS - ONE-CLICK DATABASE SETUP
// Run this once: http://localhost/HRMs/database/setup.php
// ============================================================

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_PORT', 3306);

$log = [];

function log_msg($msg, $type = 'info') {
    global $log;
    $log[] = ['type' => $type, 'msg' => $msg];
    echo "<div style='color:" . ($type === 'error' ? '#dc2626' : ($type === 'success' ? '#16a34a' : '#374151')) . ";padding:4px 0;font-family:monospace;font-size:14px;'>".
         ($type === 'success' ? '✅ ' : ($type === 'error' ? '❌ ' : '→ ')) . htmlspecialchars($msg) . "</div>";
    flush();
    ob_flush();
}

?><!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>HRMS Database Setup</title>
<style>
  body { font-family: -apple-system, sans-serif; background: #F8FAFC; padding: 2rem; max-width: 720px; margin: 0 auto; }
  .card { background: #fff; border-radius: 12px; padding: 2rem; box-shadow: 0 2px 8px rgba(0,0,0,.08); }
  h1 { color: #4F46E5; font-size: 1.5rem; margin-bottom: 1.5rem; }
  .btn { display: inline-block; background: #4F46E5; color: #fff; padding: 0.75rem 2rem; border-radius: 8px; text-decoration: none; font-weight: 600; margin-top: 1.5rem; cursor: pointer; }
</style>
</head>
<body>
<div class="card">
<h1>⚙️ Enterprise HRMS — Database Setup</h1>

<?php
ob_start();

try {
    // Connect without DB
    $pdo = new PDO("mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";charset=utf8mb4", DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    log_msg("Connected to MySQL server", 'success');

    // Create database
    $pdo->exec("CREATE DATABASE IF NOT EXISTS hrms_db DEFAULT CHARACTER SET utf8mb4 DEFAULT COLLATE utf8mb4_unicode_ci");
    log_msg("Database 'hrms_db' ready", 'success');
    $pdo->exec("USE hrms_db");

    // Run schema
    $schema = file_get_contents(__DIR__ . '/schema.sql');
    if (!$schema) throw new Exception("schema.sql not found");

    // Split and execute statements
    $statements = array_filter(array_map('trim', explode(';', $schema)));
    $executed = 0;
    foreach ($statements as $stmt) {
        if (!$stmt || strpos($stmt, '--') === 0) continue;
        if (strlen(trim(str_replace(["\n","\r","\t"," "], '', $stmt))) < 3) continue;
        try {
            $pdo->exec($stmt);
            $executed++;
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'already exists') === false &&
                strpos($e->getMessage(), 'Duplicate') === false) {
                log_msg("Warning: " . substr($e->getMessage(), 0, 100), 'info');
            }
        }
    }
    log_msg("Schema executed ({$executed} statements)", 'success');

    // Check if already seeded
    $check = $pdo->query("SELECT COUNT(*) FROM roles")->fetchColumn();
    if ($check > 0) {
        log_msg("Database already has data — skipping seed", 'info');
    } else {
        $seed = file_get_contents(__DIR__ . '/seed.sql');
        if ($seed) {
            $seedStmts = array_filter(array_map('trim', explode(';', $seed)));
            $seeded = 0;
            foreach ($seedStmts as $stmt) {
                if (!$stmt || strpos($stmt, '--') === 0) continue;
                if (strlen(trim(str_replace(["\n","\r","\t"," "], '', $stmt))) < 3) continue;
                try { $pdo->exec($stmt); $seeded++; } catch (PDOException $e) {
                    log_msg("Seed warning: " . substr($e->getMessage(), 0, 80), 'info');
                }
            }
            log_msg("Seed data inserted ({$seeded} statements)", 'success');
        }
    }

    // Verify
    $tables  = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    $empCount = $pdo->query("SELECT COUNT(*) FROM employees")->fetchColumn();
    $userCount= $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();

    log_msg("Tables created: " . count($tables), 'success');
    log_msg("Users: {$userCount} | Employees: {$empCount}", 'success');

    echo "<div style='margin-top:1.5rem;padding:1rem;background:#F0FDF4;border-radius:8px;border:1px solid #BBF7D0;'>";
    echo "<h3 style='color:#15803D;margin:0 0 8px'>🎉 Setup Complete!</h3>";
    echo "<p style='margin:4px 0;font-size:14px;color:#374151;'><strong>Login URL:</strong> <a href='../index.html'>http://localhost/HRMs/</a></p>";
    echo "<p style='margin:4px 0;font-size:14px;'><strong>Email:</strong> admin@hrms.com</p>";
    echo "<p style='margin:4px 0;font-size:14px;'><strong>Password:</strong> Admin@123</p>";
    echo "<hr style='margin:8px 0;border-color:#BBF7D0;'>";
    echo "<p style='margin:4px 0;font-size:13px;color:#4B5563;'>Other accounts: hr@hrms.com, manager@hrms.com, john.doe@hrms.com (same password)</p>";
    echo "</div>";
    echo "<a href='../index.html' class='btn'>🚀 Go to HRMS</a>";

} catch (Exception $e) {
    log_msg("Setup FAILED: " . $e->getMessage(), 'error');
    echo "<div style='margin-top:1rem;padding:1rem;background:#FEF2F2;border-radius:8px;border:1px solid #FECACA;'>";
    echo "<h3 style='color:#DC2626;margin:0 0 8px'>Setup Failed</h3>";
    echo "<p style='font-size:14px;color:#374151;'>Please check:<br>1. XAMPP MySQL is running<br>2. MySQL root password is correct (empty by default)<br>3. Port 3306 is accessible</p>";
    echo "</div>";
}
?>
</div>
</body>
</html>
