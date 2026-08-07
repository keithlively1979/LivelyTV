<?php
/**
 * admin/includes/auth.php
 * Include at the top of every admin page.
 * Starts the session and redirects to login if not authenticated.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['admin_user_id'])) {
    $login_url = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/admin') . '/admin/index.php';
    header('Location: ' . $login_url);
    exit;
}
