<?php
declare(strict_types=1);
header('Content-Type: application/json');

/**
 * Temporary one-time migration route.
 * Creates the email_verifications table if it does not already exist.
 * Does NOT run install.php and does NOT touch any other tables or rows.
 *
 * Delete this file after running it once against production.
 */

$host = getenv('DB_HOST') ?: 'localhost';
$port = getenv('DB_PORT') ?: '3306';
$name = getenv('DB_NAME') ?: '';
$user = getenv('DB_USER') ?: '';
$pass = getenv('DB_PASSWORD') ?: '';

try {
    if ($name === '' || $user === '') {
        throw new RuntimeException('Database connection is not configured.');
    }

    $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS email_verifications (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL,
            token_hash CHAR(64) NOT NULL UNIQUE,
            expires_at DATETIME NOT NULL,
            used_at DATETIME NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX(user_id),
            INDEX(expires_at)
        )"
    );

    $stmt = $pdo->prepare(
        'SELECT COUNT(*) AS cnt FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?'
    );
    $stmt->execute([$name, 'email_verifications']);
    $row = $stmt->fetch();
    $columns = (int)($row['cnt'] ?? 0);

    if ($columns === 0) {
        throw new RuntimeException('Table verification failed after creation attempt.');
    }

    echo json_encode([
        'status' => 'success',
        'message' => 'email_verifications table created/verified',
        'columns' => $columns,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to apply schema',
        'error' => $e->getMessage(),
    ]);
}
