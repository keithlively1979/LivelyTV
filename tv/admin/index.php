<?php
if (session_status() === PHP_SESSION_NONE) session_start();

if (!empty($_SESSION['admin_user_id'])) {
    header('Location: dashboard.php');
    exit;
}

require_once dirname(__DIR__) . '/dbconnect.php';

$error = '';

// Load app name and logo for the login screen
$app_name  = 'LivelyTV';
$app_logo  = '';
$r = $conn->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('app_name','app_logo')");
if ($r) {
    while ($row = $r->fetch_assoc()) {
        if ($row['setting_key'] === 'app_name') $app_name = $row['setting_value'];
        if ($row['setting_key'] === 'app_logo')  $app_logo  = $row['setting_value'];
    }
    $r->free();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username && $password) {
        $stmt = $conn->prepare('SELECT user_id, user_name, user_password, user_theme, user_theme_key, user_is_admin FROM users WHERE user_name = ? LIMIT 1');
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $stmt->bind_result($uid, $uname, $uhash, $utheme, $utheme_key, $u_is_admin);

        if ($stmt->fetch() && password_verify($password, $uhash)) {
            $_SESSION['admin_user_id']   = $uid;
            $_SESSION['admin_user_name'] = $uname;
            $_SESSION['admin_theme']     = $utheme;
            $_SESSION['admin_theme_key'] = $utheme_key ?? 'blue';
            $_SESSION['admin_is_admin']  = $u_is_admin ?? 0;
            $stmt->close();

            // Load global app settings into session
            $sr = $conn->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('app_name','app_logo')");
            if ($sr) {
                while ($row = $sr->fetch_assoc()) {
                    $_SESSION[$row['setting_key']] = $row['setting_value'];
                }
                $sr->free();
            }
            $conn->close();
            header('Location: dashboard.php');
            exit;
        }
        $error = 'Invalid username or password.';
        $stmt->close();
    } else {
        $error = 'Please enter both username and password.';
    }
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sign in — LivelyTV Admin</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
    --primary: #0000fe;
    --primary-dark: #0000cc;
    --primary-light: #e6e6ff;
    --font-sans: 'DM Sans', sans-serif;
    --radius-md: 10px;
    --radius-lg: 16px;
}

body {
    font-family: var(--font-sans);
    background: #f5f5f8;
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
}

.login-wrap {
    width: 100%;
    max-width: 400px;
}

.login-brand {
    text-align: center;
    margin-bottom: 32px;
}

.login-brand .logo {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 52px;
    min-height: 52px;
    background: <?php echo $app_logo ? 'transparent' : 'var(--primary)'; ?>;
    border-radius: 14px;
    margin-bottom: 14px;
    box-shadow: <?php echo $app_logo ? 'none' : '0 4px 20px rgba(0,0,254,0.3)'; ?>;
}

.login-brand .logo svg { width: 26px; height: 26px; fill: #fff; }

.login-brand h1 {
    font-size: 22px;
    font-weight: 600;
    color: #0f0f1a;
    letter-spacing: -0.3px;
}

.login-brand p {
    font-size: 14px;
    color: #5a5a72;
    margin-top: 4px;
}

.login-card {
    background: #fff;
    border: 1px solid #e2e2ea;
    border-radius: var(--radius-lg);
    padding: 32px;
    box-shadow: 0 4px 24px rgba(0,0,0,0.07);
}

.form-group { margin-bottom: 18px; }

label {
    display: block;
    font-size: 13px;
    font-weight: 500;
    color: #5a5a72;
    margin-bottom: 6px;
}

input {
    width: 100%;
    padding: 10px 13px;
    background: #fff;
    color: #0f0f1a;
    border: 1px solid #e2e2ea;
    border-radius: var(--radius-md);
    font-family: var(--font-sans);
    font-size: 15px;
    transition: border-color 0.15s, box-shadow 0.15s;
}

input:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px var(--primary-light);
}

.btn-login {
    width: 100%;
    padding: 11px;
    background: var(--primary);
    color: #fff;
    border: none;
    border-radius: var(--radius-md);
    font-family: var(--font-sans);
    font-size: 15px;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.15s, transform 0.1s;
    margin-top: 6px;
}

.btn-login:hover  { background: var(--primary-dark); }
.btn-login:active { transform: scale(0.98); }

.alert-danger {
    background: #fef2f2;
    color: #dc2626;
    border: 1px solid #dc2626;
    border-radius: var(--radius-md);
    padding: 10px 14px;
    font-size: 14px;
    margin-bottom: 18px;
}
</style>
</head>
<body>
<div class="login-wrap">
    <div class="login-brand">
        <div class="logo">
            <?php if ($app_logo): ?>
                <img src="<?= htmlspecialchars($app_logo) ?>" alt="<?= htmlspecialchars($app_name) ?>"
                     style="max-height:40px;max-width:160px;object-fit:contain">
            <?php else: ?>
                <svg viewBox="0 0 24 24"><path d="M4 6a2 2 0 012-2h12a2 2 0 012 2v12a2 2 0 01-2 2H6a2 2 0 01-2-2V6zm3 1v10l8-5-8-5z"/></svg>
            <?php endif; ?>
        </div>
        <h1><?= htmlspecialchars($app_name) ?></h1>
        <p>Admin Panel — sign in to continue</p>
    </div>

    <div class="login-card">
        <?php if ($error): ?>
            <div class="alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username"
                       value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                       autocomplete="username" autofocus>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" autocomplete="current-password">
            </div>
            <button type="submit" class="btn-login">Sign in</button>
        </form>
    </div>
</div>
</body>
</html>
