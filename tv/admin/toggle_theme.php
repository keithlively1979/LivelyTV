<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['admin_user_id'])) { http_response_code(403); exit; }

require_once dirname(__DIR__) . '/dbconnect.php';

$new_theme = ($_SESSION['admin_theme'] ?? 'light') === 'light' ? 'dark' : 'light';
$_SESSION['admin_theme'] = $new_theme;

$stmt = $conn->prepare('UPDATE users SET user_theme = ? WHERE user_id = ?');
$stmt->bind_param('si', $new_theme, $_SESSION['admin_user_id']);
$stmt->execute();
$stmt->close();
$conn->close();

$redirect = $_POST['redirect'] ?? 'dashboard.php';
// Basic open-redirect guard — only allow relative paths
if (str_starts_with($redirect, 'http') || str_contains($redirect, '//')) {
    $redirect = 'dashboard.php';
}
header('Location: ' . $redirect);
exit;
