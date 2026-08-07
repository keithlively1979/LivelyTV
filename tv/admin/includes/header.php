<?php
/**
 * admin/includes/header.php
 * Outputs <html>, <head>, nav sidebar, and opens the main content wrapper.
 * Requires: $page_title (string), $active_nav (string)
 */

require_once __DIR__ . '/themes.php';

$theme      = $_SESSION['admin_theme']      ?? 'light';
$theme_key  = $_SESSION['admin_theme_key']  ?? 'blue';
$is_admin   = ($_SESSION['admin_is_admin']  ?? 0) == 1;
$tokens     = get_theme_tokens($theme_key);

// Load app name and logo from session cache (set at login / settings save)
$app_name   = $_SESSION['app_name'] ?? 'LivelyTV';
$app_logo   = $_SESSION['app_logo'] ?? '';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= $theme ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($page_title ?? 'Admin') ?> — <?= htmlspecialchars($app_name) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
/* ── Reset ──────────────────────────────────────────────────────────────── */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

/* ── Theme tokens ───────────────────────────────────────────────────────── */
:root {
    --primary:        <?= $tokens['primary'] ?>;
    --primary-dark:   <?= $tokens['primary_dark'] ?>;
    --primary-light:  <?= $tokens['primary_light'] ?>;
    --primary-muted:  <?= $tokens['primary_muted'] ?>;

    --font-sans: 'DM Sans', sans-serif;
    --font-mono: 'DM Mono', monospace;

    --radius-sm: 6px;
    --radius-md: 10px;
    --radius-lg: 16px;

    --sidebar-w: 220px;
    --topbar-h:  56px;
}

[data-theme="light"] {
    --bg:           #f0f0f5;
    --surface:      #ffffff;
    --surface-2:    #f5f5f8;
    --border:       #e2e2ea;
    --text:         #0f0f1a;
    --text-2:       #5a5a72;
    --text-3:       #9898b0;
    --nav-bg:       #ffffff;
    --nav-text:     #5a5a72;
    --nav-active:   var(--primary);
    --nav-active-bg:var(--primary-light);
    --nav-hover-bg: #f5f5f8;
    --shadow:       0 1px 3px rgba(0,0,0,0.08), 0 4px 16px rgba(0,0,0,0.05);
    --shadow-md:    0 4px 24px rgba(0,0,0,0.10);
    --danger:       #dc2626;
    --danger-bg:    #fef2f2;
    --success:      #16a34a;
    --success-bg:   #f0fdf4;
    --warning:      #d97706;
    --warning-bg:   #fffbeb;
}

[data-theme="dark"] {
    --bg:           #0d0d14;
    --surface:      #16161f;
    --surface-2:    #1e1e2a;
    --border:       #2a2a3a;
    --text:         #e8e8f0;
    --text-2:       #9898b8;
    --text-3:       #5a5a72;
    --nav-bg:       #0d0d14;
    --nav-text:     rgba(255,255,255,0.55);
    --nav-active:   #ffffff;
    --nav-active-bg:rgba(0,0,254,0.25);
    --nav-hover-bg: rgba(255,255,255,0.06);
    --shadow:       0 1px 3px rgba(0,0,0,0.3), 0 4px 16px rgba(0,0,0,0.2);
    --shadow-md:    0 4px 24px rgba(0,0,0,0.4);
    --danger:       #f87171;
    --danger-bg:    #2a1010;
    --success:      #4ade80;
    --success-bg:   #0a2010;
    --warning:      #fbbf24;
    --warning-bg:   #2a1e00;
}

/* ── Base ───────────────────────────────────────────────────────────────── */
body {
    font-family: var(--font-sans);
    font-size: 15px;
    background: var(--bg);
    color: var(--text);
    line-height: 1.6;
    min-height: 100vh;
    display: flex;
}

a { color: var(--primary); text-decoration: none; }
a:hover { text-decoration: underline; }

/* ── Sidebar ────────────────────────────────────────────────────────────── */
#sidebar {
    width: var(--sidebar-w);
    min-height: 100vh;
    background: var(--nav-bg);
    display: flex;
    flex-direction: column;
    position: fixed;
    top: 0; left: 0; bottom: 0;
    z-index: 100;
    border-right: 1px solid var(--border);
}

.sidebar-brand {
    padding: 20px 20px 16px;
    border-bottom: 1px solid var(--border);
}

.sidebar-brand .brand-name {
    font-size: 18px;
    font-weight: 600;
    color: var(--text);
    letter-spacing: -0.3px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.sidebar-brand .brand-name .dot {
    width: 8px; height: 8px;
    background: var(--primary);
    border-radius: 50%;
    animation: pulse 2s ease-in-out infinite;
}

@keyframes pulse {
    0%,100% { opacity: 1; transform: scale(1); }
    50%      { opacity: 0.5; transform: scale(0.8); }
}

.sidebar-brand .brand-sub {
    font-size: 11px;
    color: var(--text-3);
    text-transform: uppercase;
    letter-spacing: 0.1em;
    margin-top: 2px;
}

.nav-section {
    padding: 12px 0 4px;
}

.nav-section-label {
    font-size: 10px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.12em;
    color: rgba(255,255,255,0.35);
    padding: 0 20px;
    margin-bottom: 4px;
}

.nav-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 9px 20px;
    color: var(--nav-text);
    font-size: 14px;
    font-weight: 500;
    text-decoration: none;
    transition: background 0.15s, color 0.15s;
    border-left: 3px solid transparent;
}

.nav-item:hover {
    background: var(--nav-hover-bg);
    color: var(--nav-active);
    text-decoration: none;
}

.nav-item.active {
    background: var(--nav-active-bg);
    color: var(--nav-active);
    border-left-color: var(--primary);
    font-weight: 600;
}

[data-theme="dark"] .nav-item.active {
    border-left-color: var(--primary-muted);
    color: #fff;
}

[data-theme="dark"] .sidebar-user .avatar {
    background: rgba(255,255,255,0.1);
    color: #fff;
}

[data-theme="dark"] .sidebar-user .user-name { color: rgba(255,255,255,0.85); }
[data-theme="dark"] .sidebar-user .user-role  { color: rgba(255,255,255,0.4);  }
[data-theme="dark"] .sidebar-brand .brand-name { color: #fff; }
[data-theme="dark"] .sidebar-brand .brand-sub  { color: rgba(255,255,255,0.4); }
[data-theme="dark"] .sidebar-brand .brand-name .dot { background: rgba(255,255,255,0.6); }

.nav-item .nav-icon {
    font-size: 16px;
    width: 20px;
    text-align: center;
    flex-shrink: 0;
}

.sidebar-footer {
    margin-top: auto;
    border-top: 1px solid var(--border);
    padding: 12px 0;
}

.sidebar-user {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 16px;
}

.sidebar-user .avatar {
    width: 30px; height: 30px;
    background: var(--primary-light);
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 13px;
    font-weight: 600;
    color: var(--primary);
    flex-shrink: 0;
}

.sidebar-user .user-info { min-width: 0; }
.sidebar-user .user-name {
    font-size: 13px;
    font-weight: 500;
    color: var(--text);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.sidebar-user .user-role {
    font-size: 11px;
    color: var(--text-3);
}

/* ── Main area ──────────────────────────────────────────────────────────── */
#main {
    margin-left: var(--sidebar-w);
    flex: 1;
    display: flex;
    flex-direction: column;
    min-height: 100vh;
}

/* ── Topbar ─────────────────────────────────────────────────────────────── */
#topbar {
    height: var(--topbar-h);
    background: var(--surface);
    border-bottom: 1px solid var(--border);
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 28px;
    position: sticky;
    top: 0;
    z-index: 50;
    box-shadow: var(--shadow);
}

.topbar-title {
    font-size: 17px;
    font-weight: 600;
    color: var(--text);
    letter-spacing: -0.2px;
}

.topbar-actions {
    display: flex;
    align-items: center;
    gap: 12px;
}

/* Theme toggle */
.theme-toggle {
    width: 36px; height: 20px;
    background: var(--border);
    border-radius: 10px;
    border: none;
    cursor: pointer;
    position: relative;
    transition: background 0.2s;
    flex-shrink: 0;
}

.theme-toggle::after {
    content: '';
    position: absolute;
    top: 2px; left: 2px;
    width: 16px; height: 16px;
    background: #fff;
    border-radius: 50%;
    transition: transform 0.2s;
    box-shadow: 0 1px 3px rgba(0,0,0,0.2);
}

[data-theme="dark"] .theme-toggle { background: var(--primary); }
[data-theme="dark"] .theme-toggle::after { transform: translateX(16px); }

.theme-toggle-label {
    font-size: 12px;
    color: var(--text-2);
}

/* ── Content wrapper ────────────────────────────────────────────────────── */
#content {
    padding: 28px;
    flex: 1;
}

/* ── Cards ──────────────────────────────────────────────────────────────── */
.card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow);
    overflow: hidden;
}

.card-header {
    padding: 18px 24px;
    border-bottom: 1px solid var(--border);
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
}

.card-title {
    font-size: 15px;
    font-weight: 600;
    color: var(--text);
}

.card-body { padding: 24px; }

/* ── Buttons ────────────────────────────────────────────────────────────── */
.btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 16px;
    border-radius: var(--radius-sm);
    font-family: var(--font-sans);
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    border: 1px solid transparent;
    transition: all 0.15s;
    text-decoration: none;
    white-space: nowrap;
}

.btn:hover { text-decoration: none; }

.btn-primary {
    background: var(--primary);
    color: #fff;
    border-color: var(--primary);
}
.btn-primary:hover { background: var(--primary-dark); border-color: var(--primary-dark); color: #fff; }

.btn-secondary {
    background: var(--surface-2);
    color: var(--text);
    border-color: var(--border);
}
.btn-secondary:hover { background: var(--border); }

.btn-danger {
    background: var(--danger-bg);
    color: var(--danger);
    border-color: var(--danger);
}
.btn-danger:hover { background: var(--danger); color: #fff; }

.btn-sm { padding: 5px 10px; font-size: 12px; }
.btn-icon { padding: 6px 8px; }

/* ── Tables ─────────────────────────────────────────────────────────────── */
.table-wrap { overflow-x: auto; }

table {
    width: 100%;
    border-collapse: collapse;
    font-size: 14px;
}

thead th {
    background: var(--surface-2);
    padding: 10px 14px;
    text-align: left;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.07em;
    color: var(--text-2);
    border-bottom: 1px solid var(--border);
    white-space: nowrap;
}

tbody td {
    padding: 11px 14px;
    border-bottom: 1px solid var(--border);
    color: var(--text);
    vertical-align: middle;
}

tbody tr:last-child td { border-bottom: none; }
tbody tr:hover { background: var(--surface-2); }

.td-mono { font-family: var(--font-mono); font-size: 12px; color: var(--text-2); }

/* ── Forms ──────────────────────────────────────────────────────────────── */
.form-group { margin-bottom: 18px; }

label {
    display: block;
    font-size: 13px;
    font-weight: 500;
    color: var(--text-2);
    margin-bottom: 6px;
}

input[type="text"],
input[type="number"],
input[type="password"],
input[type="email"],
input[type="date"],
select,
textarea {
    width: 100%;
    padding: 9px 12px;
    background: var(--surface);
    color: var(--text);
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    font-family: var(--font-sans);
    font-size: 14px;
    transition: border-color 0.15s, box-shadow 0.15s;
    appearance: none;
}

input:focus, select:focus, textarea:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px var(--primary-light);
}

[data-theme="dark"] input:focus,
[data-theme="dark"] select:focus,
[data-theme="dark"] textarea:focus {
    box-shadow: 0 0 0 3px rgba(0,0,254,0.25);
}

textarea { resize: vertical; min-height: 80px; }

select {
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath fill='%2394a3b8' d='M6 8L0 0h12z'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 10px center;
    padding-right: 28px;
}

.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.form-row-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px; }

/* ── Badges ─────────────────────────────────────────────────────────────── */
.badge {
    display: inline-flex;
    align-items: center;
    padding: 2px 8px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}
.badge-blue   { background: var(--primary-light); color: var(--primary); }
.badge-green  { background: var(--success-bg);    color: var(--success); }
.badge-red    { background: var(--danger-bg);      color: var(--danger);  }
.badge-amber  { background: var(--warning-bg);     color: var(--warning); }

/* ── Alerts ─────────────────────────────────────────────────────────────── */
.alert {
    padding: 12px 16px;
    border-radius: var(--radius-md);
    font-size: 14px;
    margin-bottom: 20px;
    border: 1px solid;
}
.alert-success { background: var(--success-bg); color: var(--success); border-color: var(--success); }
.alert-danger  { background: var(--danger-bg);  color: var(--danger);  border-color: var(--danger);  }
.alert-info    { background: var(--primary-light); color: var(--primary); border-color: var(--primary); }

/* ── Page header ────────────────────────────────────────────────────────── */
.page-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 24px;
    flex-wrap: wrap;
    gap: 12px;
}

.page-header h1 {
    font-size: 24px;
    font-weight: 600;
    letter-spacing: -0.4px;
    color: var(--text);
}

/* ── Stat cards ─────────────────────────────────────────────────────────── */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    gap: 16px;
    margin-bottom: 28px;
}

.stat-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    padding: 20px 22px;
    box-shadow: var(--shadow);
}

.stat-label {
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: var(--text-3);
    margin-bottom: 6px;
}

.stat-value {
    font-size: 28px;
    font-weight: 600;
    color: var(--primary);
    letter-spacing: -0.5px;
    line-height: 1;
}

.stat-sub {
    font-size: 12px;
    color: var(--text-3);
    margin-top: 4px;
}

/* ── Modal ──────────────────────────────────────────────────────────────── */
.modal-overlay {
    display: none;
    position: fixed; inset: 0;
    background: rgba(0,0,0,0.5);
    z-index: 200;
    align-items: center;
    justify-content: center;
    padding: 20px;
}

.modal-overlay.open { display: flex; }

.modal {
    background: var(--surface);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-md);
    width: 100%;
    max-width: 560px;
    max-height: 90vh;
    overflow-y: auto;
    border: 1px solid var(--border);
}

.modal-header {
    padding: 20px 24px 16px;
    border-bottom: 1px solid var(--border);
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.modal-title { font-size: 16px; font-weight: 600; }

.modal-close {
    background: none; border: none;
    font-size: 20px; cursor: pointer;
    color: var(--text-2); line-height: 1;
    padding: 2px 6px;
    border-radius: var(--radius-sm);
    transition: background 0.15s;
}
.modal-close:hover { background: var(--surface-2); }

.modal-body { padding: 24px; }
.modal-footer {
    padding: 16px 24px;
    border-top: 1px solid var(--border);
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}

/* ── Drag handle ────────────────────────────────────────────────────────── */
.drag-handle {
    cursor: grab;
    color: var(--text-3);
    padding: 0 4px;
    font-size: 16px;
    user-select: none;
}
.drag-handle:active { cursor: grabbing; }
tr.dragging { opacity: 0.4; }
tr.drag-over td { border-top: 2px solid var(--primary); }

/* ── Pagination ─────────────────────────────────────────────────────────── */
.pagination {
    display: flex;
    align-items: center;
    gap: 4px;
    justify-content: center;
    padding-top: 20px;
}

.pagination a, .pagination span {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 34px; height: 34px;
    border-radius: var(--radius-sm);
    font-size: 13px;
    font-weight: 500;
    border: 1px solid var(--border);
    background: var(--surface);
    color: var(--text);
    text-decoration: none;
    transition: all 0.15s;
}

.pagination a:hover { background: var(--primary); color: #fff; border-color: var(--primary); text-decoration: none; }
.pagination span.current { background: var(--primary); color: #fff; border-color: var(--primary); }
.pagination span.dots { border: none; background: none; color: var(--text-3); }

/* ── Filters bar ────────────────────────────────────────────────────────── */
.filters-bar {
    display: flex;
    gap: 10px;
    align-items: flex-end;
    flex-wrap: wrap;
    margin-bottom: 20px;
}

.filters-bar .form-group { margin-bottom: 0; }
.filters-bar label { margin-bottom: 4px; }

/* ── Misc ───────────────────────────────────────────────────────────────── */
.text-muted { color: var(--text-2); }
.text-sm    { font-size: 12px; }
.mt-1 { margin-top: 6px; }
.mt-2 { margin-top: 12px; }
.gap-2 { gap: 8px; }
.flex { display: flex; }
.items-center { align-items: center; }

/* ── Scrollbar ──────────────────────────────────────────────────────────── */
::-webkit-scrollbar { width: 6px; height: 6px; }
::-webkit-scrollbar-track { background: transparent; }
::-webkit-scrollbar-thumb { background: var(--border); border-radius: 3px; }
::-webkit-scrollbar-thumb:hover { background: var(--text-3); }
</style>
</head>
<body>

<!-- Sidebar -->
<nav id="sidebar">
    <div class="sidebar-brand">
        <?php if ($app_logo): ?>
            <img src="<?= htmlspecialchars($app_logo) ?>" alt="<?= htmlspecialchars($app_name) ?>"
                 style="max-height:36px;max-width:160px;object-fit:contain;margin-bottom:4px">
        <?php else: ?>
            <div class="brand-name"><span class="dot"></span> <?= htmlspecialchars($app_name) ?></div>
        <?php endif; ?>
        <div class="brand-sub">Admin Panel</div>
    </div>

    <div class="nav-section">
        <div class="nav-section-label">Content</div>
        <a href="channels.php"    class="nav-item <?= ($active_nav??'')==='channels'    ? 'active':'' ?>"><span class="nav-icon">📡</span> Channels</a>
        <a href="shows.php"       class="nav-item <?= ($active_nav??'')==='shows'       ? 'active':'' ?>"><span class="nav-icon">📺</span> Shows</a>
        <a href="episodes.php"    class="nav-item <?= ($active_nav??'')==='episodes'    ? 'active':'' ?>"><span class="nav-icon">🎬</span> Episodes</a>
        <a href="movies.php"      class="nav-item <?= ($active_nav??'')==='movies'      ? 'active':'' ?>"><span class="nav-icon">🎥</span> Movies</a>
        <a href="commercials.php" class="nav-item <?= ($active_nav??'')==='commercials' ? 'active':'' ?>"><span class="nav-icon">📢</span> Commercials</a>
    </div>

    <div class="nav-section">
        <div class="nav-section-label">Scheduling</div>
        <a href="schedule.php"    class="nav-item <?= ($active_nav??'')==='schedule'    ? 'active':'' ?>"><span class="nav-icon">📅</span> Schedule</a>
        <a href="playlog.php"     class="nav-item <?= ($active_nav??'')==='playlog'     ? 'active':'' ?>"><span class="nav-icon">📋</span> Play Log</a>
    </div>

    <div class="nav-section">
        <div class="nav-section-label">Tools</div>
        <a href="scraper.php"     class="nav-item <?= ($active_nav??'')==='scraper'     ? 'active':'' ?>"><span class="nav-icon">🔍</span> Scraper</a>
        <a href="users.php"       class="nav-item <?= ($active_nav??'')==='users'       ? 'active':'' ?>"><span class="nav-icon">👤</span> Users</a>
        <?php if ($is_admin): ?>
        <a href="settings.php"    class="nav-item <?= ($active_nav??'')==='settings'    ? 'active':'' ?>"><span class="nav-icon">⚙️</span> Settings</a>
        <?php endif; ?>
        <a href="dashboard.php"   class="nav-item <?= ($active_nav??'')==='dashboard'   ? 'active':'' ?>"><span class="nav-icon">🏠</span> Dashboard</a>
    </div>

    <div class="sidebar-footer">
        <div class="sidebar-user">
            <div class="avatar"><?= strtoupper(substr($_SESSION['admin_user_name'] ?? 'A', 0, 1)) ?></div>
            <div class="user-info">
                <div class="user-name"><?= htmlspecialchars($_SESSION['admin_user_name'] ?? '') ?></div>
                <div class="user-role"><?= $is_admin ? 'Administrator' : 'User' ?></div>
            </div>
        </div>
        <a href="logout.php" class="nav-item"><span class="nav-icon">🚪</span> Log out</a>
    </div>
</nav>

<!-- Main -->
<div id="main">
    <header id="topbar">
        <div class="topbar-title"><?= htmlspecialchars($page_title ?? '') ?></div>
        <div class="topbar-actions">
            <span class="theme-toggle-label"><?= $theme === 'dark' ? 'Dark' : 'Light' ?></span>
            <form method="POST" action="toggle_theme.php" style="display:inline">
                <input type="hidden" name="redirect" value="<?= htmlspecialchars($_SERVER['REQUEST_URI']) ?>">
                <button type="submit" class="theme-toggle" title="Toggle theme" aria-label="Toggle dark mode"></button>
            </form>
        </div>
    </header>
    <div id="content">
