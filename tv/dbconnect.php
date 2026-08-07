<?php
/**
 * dbconnect.php — database connection
 *
 * Reads credentials from a .env file located one directory above the web
 * root so Apache cannot serve it directly.
 *
 * Expected location: /var/www/.env  (i.e. ../  relative to this file's
 * directory). Adjust the path below if your layout differs.
 */

// ─── Load .env ────────────────────────────────────────────────────────────────

$env_file = dirname(__DIR__) . '/.env';

if (!is_readable($env_file)) {
    die('Configuration error: .env file not found or not readable at ' . $env_file);
}

foreach (file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    // Skip comment lines
    if (str_starts_with(trim($line), '#')) {
        continue;
    }
    // Only process lines that contain an = sign
    if (!str_contains($line, '=')) {
        continue;
    }
    [$key, $value] = explode('=', $line, 2);
    $_ENV[trim($key)] = trim($value);
}

// ─── Connect ──────────────────────────────────────────────────────────────────

$conn = new mysqli(
    $_ENV['DB_HOST'] ?? '',
    $_ENV['DB_USER'] ?? '',
    $_ENV['DB_PASS'] ?? '',
    $_ENV['DB_NAME'] ?? ''
);

mysqli_set_charset($conn, 'utf8');

if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}
