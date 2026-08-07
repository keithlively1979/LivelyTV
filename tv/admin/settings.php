<?php
require_once 'includes/auth.php';
require_once dirname(__DIR__) . '/dbconnect.php';

// Admins only
if (empty($_SESSION['admin_is_admin'])) {
    header('Location: dashboard.php');
    exit;
}

$page_title = 'Settings';
$active_nav = 'settings';
$message    = '';
$error      = '';

const UPLOAD_DIR    = '/var/www/lively.local/tv/admin/uploads/';
const UPLOAD_URL    = '/tv/admin/uploads/';
const MAX_LOGO_SIZE = 512 * 1024; // 512 KB

// ── Handle POST actions ───────────────────────────────────────────────────────
$action = $_POST['action'] ?? '';

if ($action === 'save_app') {
    $app_name     = trim($_POST['app_name']     ?? '');
    $player_theme = trim($_POST['player_theme'] ?? 'dark');
    if (!in_array($player_theme, ['dark','retro','minimal'])) $player_theme = 'dark';

    if (!$app_name) {
        $error = 'App name cannot be empty.';
    } else {
        // Handle logo upload
        $logo_value = null;

        if (!empty($_FILES['app_logo']['name'])) {
            $file     = $_FILES['app_logo'];
            $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed  = ['jpg', 'jpeg', 'png'];
            $finfo    = finfo_open(FILEINFO_MIME_TYPE);
            $mime     = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            $allowed_mimes = ['image/jpeg', 'image/png'];

            if ($file['error'] !== UPLOAD_ERR_OK) {
                $error = 'Upload error — please try again.';
            } elseif (!in_array($ext, $allowed)) {
                $error = 'Only JPG and PNG files are allowed.';
            } elseif (!in_array($mime, $allowed_mimes)) {
                $error = 'File type not permitted.';
            } elseif ($file['size'] > MAX_LOGO_SIZE) {
                $error = 'Logo must be under 512 KB.';
            } else {
                if (!is_dir(UPLOAD_DIR)) {
                    mkdir(UPLOAD_DIR, 0755, true);
                }
                // Delete old logo if exists
                $old = $conn->query("SELECT setting_value FROM settings WHERE setting_key='app_logo' LIMIT 1");
                if ($old) {
                    $old_row = $old->fetch_assoc();
                    $old->free();
                    if (!empty($old_row['setting_value'])) {
                        $old_file = UPLOAD_DIR . basename($old_row['setting_value']);
                        if (file_exists($old_file)) unlink($old_file);
                    }
                }
                // Save new logo with sanitized filename
                $safe_name  = 'logo_' . time() . '.' . $ext;
                $dest       = UPLOAD_DIR . $safe_name;
                if (move_uploaded_file($file['tmp_name'], $dest)) {
                    $logo_value = UPLOAD_URL . $safe_name;
                } else {
                    $error = 'Failed to save uploaded file.';
                }
            }
        }

        if (!$error) {
            // Upsert app_name
            $stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('app_name', ?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)");
            $stmt->bind_param('s', $app_name);
            $stmt->execute();
            $stmt->close();

            // Upsert app_logo if a new one was uploaded
            if ($logo_value !== null) {
                $stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('app_logo', ?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)");
                $stmt->bind_param('s', $logo_value);
                $stmt->execute();
                $stmt->close();
                $_SESSION['app_logo'] = $logo_value;
            }

            $_SESSION['app_name'] = $app_name;

            // Upsert player_theme
            $stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('player_theme', ?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)");
            $stmt->bind_param('s', $player_theme);
            $stmt->execute();
            $stmt->close();

            $message = 'App settings saved.';
        }
    }
}

if ($action === 'remove_logo') {
    $old = $conn->query("SELECT setting_value FROM settings WHERE setting_key='app_logo' LIMIT 1");
    if ($old) {
        $old_row = $old->fetch_assoc();
        $old->free();
        if (!empty($old_row['setting_value'])) {
            $old_file = UPLOAD_DIR . basename($old_row['setting_value']);
            if (file_exists($old_file)) unlink($old_file);
        }
    }
    $stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('app_logo','') ON DUPLICATE KEY UPDATE setting_value=''");
    $stmt->execute();
    $stmt->close();
    $_SESSION['app_logo'] = '';
    $message = 'Logo removed.';
}

if ($action === 'add_path') {
    $path_label   = trim($_POST['path_label']   ?? '');
    $path_dir     = trim($_POST['path_dir']     ?? '');
    $path_type    = $_POST['path_type'] === 'movies' ? 'movies' : 'tv';
    $path_enabled = 1;

    if (!$path_dir) {
        $error = 'Path directory is required.';
    } else {
        $stmt = $conn->prepare('INSERT INTO content_paths (path_label, path_dir, path_type, path_enabled) VALUES (?,?,?,1)');
        $stmt->bind_param('sss', $path_label, $path_dir, $path_type);
        $stmt->execute();
        $stmt->close();
        $message = 'Content path added.';
    }
}

if ($action === 'edit_path') {
    $path_id      = (int)($_POST['path_id']     ?? 0);
    $path_label   = trim($_POST['path_label']   ?? '');
    $path_dir     = trim($_POST['path_dir']     ?? '');
    $path_type    = $_POST['path_type'] === 'movies' ? 'movies' : 'tv';
    $path_enabled = isset($_POST['path_enabled']) ? 1 : 0;

    if (!$path_dir) {
        $error = 'Path directory is required.';
    } else {
        $stmt = $conn->prepare('UPDATE content_paths SET path_label=?, path_dir=?, path_type=?, path_enabled=? WHERE path_id=?');
        $stmt->bind_param('sssii', $path_label, $path_dir, $path_type, $path_enabled, $path_id);
        $stmt->execute();
        $stmt->close();
        $message = 'Content path updated.';
    }
}

if ($action === 'delete_path') {
    $path_id = (int)($_POST['path_id'] ?? 0);
    $stmt = $conn->prepare('DELETE FROM content_paths WHERE path_id=?');
    $stmt->bind_param('i', $path_id);
    $stmt->execute();
    $stmt->close();
    $message = 'Content path deleted.';
}

if ($action === 'toggle_path') {
    $path_id = (int)($_POST['path_id'] ?? 0);
    $stmt = $conn->prepare('UPDATE content_paths SET path_enabled = NOT path_enabled WHERE path_id=?');
    $stmt->bind_param('i', $path_id);
    $stmt->execute();
    $stmt->close();
}

// ── Fetch current settings ────────────────────────────────────────────────────
$settings = [];
$r = $conn->query("SELECT setting_key, setting_value FROM settings");
if ($r) { while ($row = $r->fetch_assoc()) $settings[$row['setting_key']] = $row['setting_value']; $r->free(); }

$app_name_val    = $settings['app_name']     ?? 'LivelyTV';
$app_logo_val    = $settings['app_logo']     ?? '';
$player_theme_val = $settings['player_theme'] ?? 'dark';

// Fetch content paths
$paths = [];
$r = $conn->query('SELECT * FROM content_paths ORDER BY path_type, path_label');
if ($r) { while ($row = $r->fetch_assoc()) $paths[] = $row; $r->free(); }

$conn->close();
require_once 'includes/header.php';
?>

<div class="page-header">
    <h1>Settings</h1>
</div>

<?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
<?php if ($error):   ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<!-- ── App settings ─────────────────────────────────────────────────────────── -->
<div class="card" style="margin-bottom:24px">
    <div class="card-header">
        <span class="card-title">Application</span>
    </div>
    <div class="card-body">
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="save_app">
            <div class="form-row">
                <div class="form-group">
                    <label>App name</label>
                    <input type="text" name="app_name" value="<?= htmlspecialchars($app_name_val) ?>" required>
                    <div class="text-muted text-sm mt-1">Displayed in the sidebar and browser tab.</div>
                </div>
                <div class="form-group">
                    <label>Logo <span class="text-muted text-sm">(JPG or PNG, max 512 KB)</span></label>
                    <input type="file" name="app_logo" accept=".jpg,.jpeg,.png"
                           style="padding:6px 10px">
                    <?php if ($app_logo_val): ?>
                    <div style="margin-top:10px;display:flex;align-items:center;gap:12px">
                        <img src="<?= htmlspecialchars($app_logo_val) ?>" alt="Current logo"
                             style="max-height:40px;max-width:160px;object-fit:contain;
                                    border:1px solid var(--border);border-radius:var(--radius-sm);padding:4px">
                        <button type="button" class="btn btn-danger btn-sm"
                                onclick="submitRemoveAppLogo()">Remove logo</button>
                    </div>
                    <?php else: ?>
                    <div class="text-muted text-sm mt-1">No logo set — app name will be shown as text.</div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="form-group" style="margin-top:20px">
                <label>Player theme</label>
                <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-top:6px" id="theme-picker">
                    <?php
                    $pt_options = [
                        'dark'    => ['label'=>'Cinematic',  'desc'=>'Dark, near-black — Plex style',      'icon'=>'🎬'],
                        'retro'   => ['label'=>'Broadcast',  'desc'=>'CRT aesthetic, channel numbers',      'icon'=>'📺'],
                        'minimal' => ['label'=>'Minimal',    'desc'=>'Light, clean, modern streaming look', 'icon'=>'✨'],
                    ];
                    foreach ($pt_options as $key => $opt):
                        $checked = $player_theme_val === $key;
                    ?>
                    <label class="theme-option <?= $checked ? 'theme-option-active' : '' ?>"
                           data-key="<?= $key ?>">
                        <input type="radio" name="player_theme" value="<?= $key ?>"
                               <?= $checked ? 'checked' : '' ?>
                               style="position:absolute;opacity:0;pointer-events:none"
                               onchange="updateThemeOptions()">
                        <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px">
                            <span style="font-size:18px"><?= $opt['icon'] ?></span>
                            <strong style="font-size:13px"><?= $opt['label'] ?></strong>
                        </div>
                        <span style="font-size:12px;color:var(--text-2)"><?= $opt['desc'] ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <style>
            .theme-option {
                display: flex;
                flex-direction: column;
                gap: 4px;
                padding: 14px;
                border: 2px solid var(--border);
                border-radius: var(--radius-md);
                cursor: pointer;
                transition: border-color 0.15s, background 0.15s;
                position: relative;
            }
            .theme-option:hover { border-color: var(--primary); }
            .theme-option-active {
                border-color: var(--primary);
                background: var(--primary-light);
            }
            </style>
            <script>
            function updateThemeOptions() {
                document.querySelectorAll('.theme-option').forEach(label => {
                    const radio = label.querySelector('input[type=radio]');
                    label.classList.toggle('theme-option-active', radio.checked);
                });
            }
            document.querySelectorAll('.theme-option').forEach(label => {
                label.addEventListener('click', () => {
                    const radio = label.querySelector('input[type=radio]');
                    radio.checked = true;
                    updateThemeOptions();
                });
            });
            </script>
            <button type="submit" class="btn btn-primary">Save app settings</button>
        </form>
    </div><!-- /.card-body -->
</div><!-- /.card -->

<!-- ── Content paths ────────────────────────────────────────────────────────── -->
<div class="card">
    <div class="card-header">
        <span class="card-title">Content paths</span>
        <button class="btn btn-primary btn-sm" onclick="openModal('modal-add-path')">+ Add path</button>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Label</th>
                    <th style="width:80px">Type</th>
                    <th>Directory</th>
                    <th style="width:80px">Enabled</th>
                    <th style="width:100px">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($paths): ?>
                <?php foreach ($paths as $p): ?>
                <tr>
                    <td><?= htmlspecialchars($p['path_label'] ?: '—') ?></td>
                    <td>
                        <span class="badge <?= $p['path_type']==='movies' ? 'badge-amber' : 'badge-blue' ?>">
                            <?= $p['path_type'] === 'movies' ? 'Movies' : 'TV' ?>
                        </span>
                    </td>
                    <td class="td-mono"><?= htmlspecialchars($p['path_dir']) ?></td>
                    <td>
                        <form method="POST" style="margin:0">
                            <input type="hidden" name="action" value="toggle_path">
                            <input type="hidden" name="path_id" value="<?= $p['path_id'] ?>">
                            <button type="submit" class="badge <?= $p['path_enabled'] ? 'badge-green' : 'badge-red' ?>"
                                    style="border:none;cursor:pointer;font-family:var(--font-sans)">
                                <?= $p['path_enabled'] ? 'Yes' : 'No' ?>
                            </button>
                        </form>
                    </td>
                    <td>
                        <div class="flex gap-2">
                            <button class="btn btn-secondary btn-sm"
                                data-p="<?= htmlspecialchars(json_encode($p), ENT_QUOTES) ?>"
                                onclick="editPath(this)">Edit</button>
                            <button class="btn btn-danger btn-sm"
                                onclick="confirmDeletePath(<?= $p['path_id'] ?>)">Del</button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="5" class="text-muted" style="text-align:center;padding:24px">
                        No content paths configured. Add one to enable scraper discovery.
                    </td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add path modal -->
<div class="modal-overlay" id="modal-add-path">
    <div class="modal" style="max-width:480px">
        <div class="modal-header">
            <span class="modal-title">Add content path</span>
            <button class="modal-close" onclick="closeModal('modal-add-path')">×</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="add_path">
            <div class="modal-body">
                <div class="form-group">
                    <label>Label <span class="text-muted text-sm">(optional)</span></label>
                    <input type="text" name="path_label" placeholder="NAS TV Shows">
                </div>
                <div class="form-group">
                    <label>Type</label>
                    <select name="path_type">
                        <option value="tv">TV Shows</option>
                        <option value="movies">Movies</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Directory</label>
                    <input type="text" name="path_dir" required placeholder="/mnt/plex/TV Shows">
                    <div class="text-muted text-sm mt-1">Full path as accessible from the server running the scraper.</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modal-add-path')">Cancel</button>
                <button type="submit" class="btn btn-primary">Add path</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit path modal -->
<div class="modal-overlay" id="modal-edit-path">
    <div class="modal" style="max-width:480px">
        <div class="modal-header">
            <span class="modal-title">Edit content path</span>
            <button class="modal-close" onclick="closeModal('modal-edit-path')">×</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="edit_path">
            <input type="hidden" name="path_id" id="edit-path_id">
            <div class="modal-body">
                <div class="form-group">
                    <label>Label</label>
                    <input type="text" name="path_label" id="edit-path_label">
                </div>
                <div class="form-group">
                    <label>Type</label>
                    <select name="path_type" id="edit-path_type">
                        <option value="tv">TV Shows</option>
                        <option value="movies">Movies</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Directory</label>
                    <input type="text" name="path_dir" id="edit-path_dir" required>
                </div>
                <div class="form-group" style="display:flex;align-items:center;gap:10px">
                    <input type="checkbox" name="path_enabled" id="edit-path_enabled"
                           style="width:18px;height:18px;cursor:pointer">
                    <label for="edit-path_enabled" style="margin:0;cursor:pointer">Enabled</label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modal-edit-path')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete path confirm modal -->
<div class="modal-overlay" id="modal-delete-path">
    <div class="modal" style="max-width:380px">
        <div class="modal-header">
            <span class="modal-title">Delete path</span>
            <button class="modal-close" onclick="closeModal('modal-delete-path')">×</button>
        </div>
        <div class="modal-body"><p>Delete this content path? The scraper will no longer discover content here.</p></div>
        <div class="modal-footer">
            <form method="POST">
                <input type="hidden" name="action" value="delete_path">
                <input type="hidden" name="path_id" id="delete-path_id">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modal-delete-path')">Cancel</button>
                <button type="submit" class="btn btn-danger">Delete</button>
            </form>
        </div>
    </div>
</div>

<script>
function editPath(btn) {
    const p = JSON.parse(btn.dataset.p);
    document.getElementById('edit-path_id').value      = p.path_id;
    document.getElementById('edit-path_label').value   = p.path_label   || '';
    document.getElementById('edit-path_type').value    = p.path_type;
    document.getElementById('edit-path_dir').value     = p.path_dir;
    document.getElementById('edit-path_enabled').checked = p.path_enabled == 1;
    openModal('modal-edit-path');
}
function confirmDeletePath(id) {
    document.getElementById('delete-path_id').value = id;
    openModal('modal-delete-path');
}
</script>

<!-- Remove app logo form — outside all other forms to avoid nesting -->
<form method="POST" id="form-remove-app-logo" style="display:none">
    <input type="hidden" name="action" value="remove_logo">
</form>

<script>
function submitRemoveAppLogo() {
    if (confirm('Remove the app logo?')) {
        document.getElementById('form-remove-app-logo').submit();
    }
}
</script>

<?php require_once 'includes/footer.php'; ?>
