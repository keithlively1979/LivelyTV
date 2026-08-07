<?php
require_once 'includes/auth.php';
require_once dirname(__DIR__) . '/dbconnect.php';
require_once 'includes/themes.php';

$page_title = 'Users';
$active_nav = 'users';
$message = '';
$error   = '';

$action = $_POST['action'] ?? '';

if ($action === 'add') {
    $user_name      = trim($_POST['user_name']      ?? '');
    $user_password  = $_POST['user_password']       ?? '';
    $user_password2 = $_POST['user_password2']      ?? '';
    $user_is_admin  = isset($_POST['user_is_admin']) ? 1 : 0;

    if (!$user_name || !$user_password) {
        $error = 'Username and password are required.';
    } elseif ($user_password !== $user_password2) {
        $error = 'Passwords do not match.';
    } elseif (strlen($user_password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } else {
        $hash = password_hash($user_password, PASSWORD_BCRYPT);
        $stmt = $conn->prepare('INSERT INTO users (user_name, user_password, user_is_admin) VALUES (?,?,?)');
        $stmt->bind_param('ssi', $user_name, $hash, $user_is_admin);
        if ($stmt->execute()) {
            $message = "User \"$user_name\" added.";
        } else {
            $error = 'Username already exists.';
        }
        $stmt->close();
    }
}

if ($action === 'change_password') {
    $user_id        = (int)($_POST['user_id']       ?? 0);
    $user_password  = $_POST['user_password']       ?? '';
    $user_password2 = $_POST['user_password2']      ?? '';

    if (!$user_password) {
        $error = 'Password is required.';
    } elseif ($user_password !== $user_password2) {
        $error = 'Passwords do not match.';
    } elseif (strlen($user_password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } else {
        $hash = password_hash($user_password, PASSWORD_BCRYPT);
        $stmt = $conn->prepare('UPDATE users SET user_password=? WHERE user_id=?');
        $stmt->bind_param('si', $hash, $user_id);
        $stmt->execute();
        $stmt->close();
        $message = 'Password updated.';
    }
}

if ($action === 'change_theme') {
    $user_id        = (int)($_POST['user_id']    ?? 0);
    $new_theme_key  = $_POST['user_theme_key']   ?? 'blue';
    // Validate theme key exists
    if (!isset(THEMES[$new_theme_key])) $new_theme_key = 'blue';

    $stmt = $conn->prepare('UPDATE users SET user_theme_key=? WHERE user_id=?');
    $stmt->bind_param('si', $new_theme_key, $user_id);
    $stmt->execute();
    $stmt->close();

    // Update session if changing own theme
    if ($user_id === (int)$_SESSION['admin_user_id']) {
        $_SESSION['admin_theme_key'] = $new_theme_key;
    }
    $message = 'Theme updated.';
}

if ($action === 'delete') {
    $user_id = (int)($_POST['user_id'] ?? 0);
    if ($user_id === (int)$_SESSION['admin_user_id']) {
        $error = 'You cannot delete your own account.';
    } else {
        $stmt = $conn->prepare('DELETE FROM users WHERE user_id=?');
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $stmt->close();
        $message = 'User deleted.';
    }
}

// Fetch users
$users = [];
$r = $conn->query('SELECT user_id, user_name, user_theme, user_theme_key, user_is_admin, user_created FROM users ORDER BY user_name');
if ($r) { while ($row = $r->fetch_assoc()) $users[] = $row; $r->free(); }

$conn->close();
require_once 'includes/header.php';
?>

<div class="page-header">
    <h1>Users</h1>
    <button class="btn btn-primary" onclick="openModal('modal-add')">+ Add user</button>
</div>

<?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
<?php if ($error):   ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="card">
    <div class="card-header">
        <span class="card-title"><?= count($users) ?> user<?= count($users) !== 1 ? 's':'' ?></span>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Username</th>
                    <th>Role</th>
                    <th>Mode</th>
                    <th>Theme</th>
                    <th>Created</th>
                    <th style="width:180px">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($users as $u): ?>
            <tr>
                <td>
                    <strong><?= htmlspecialchars($u['user_name']) ?></strong>
                    <?php if ($u['user_id'] == $_SESSION['admin_user_id']): ?>
                        <span class="badge badge-blue" style="margin-left:6px">You</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($u['user_is_admin']): ?>
                        <span class="badge badge-blue">Admin</span>
                    <?php else: ?>
                        <span class="badge badge-green" style="background:var(--surface-2);color:var(--text-2)">User</span>
                    <?php endif; ?>
                </td>
                <td>
                    <span class="badge <?= $u['user_theme']==='dark' ? 'badge-amber' : 'badge-blue' ?>">
                        <?= ucfirst($u['user_theme']) ?>
                    </span>
                </td>
                <td>
                    <?php
                        $tk = $u['user_theme_key'] ?? 'blue';
                        $tl = THEMES[$tk]['label'] ?? 'Blue';
                        $tc = THEMES[$tk]['primary'] ?? '#0000fe';
                    ?>
                    <span style="display:inline-flex;align-items:center;gap:6px">
                        <span style="width:12px;height:12px;border-radius:50%;background:<?= $tc ?>;display:inline-block;border:1px solid var(--border)"></span>
                        <?= htmlspecialchars($tl) ?>
                    </span>
                </td>
                <td class="td-mono text-sm"><?= htmlspecialchars($u['user_created']) ?></td>
                <td>
                    <div class="flex gap-2">
                        <button class="btn btn-secondary btn-sm"
                            onclick="changeTheme(<?= $u['user_id'] ?>, '<?= htmlspecialchars(addslashes($u['user_name'])) ?>', '<?= $u['user_theme_key'] ?? 'blue' ?>')">
                            Theme
                        </button>
                        <button class="btn btn-secondary btn-sm"
                            onclick="changePassword(<?= $u['user_id'] ?>, '<?= htmlspecialchars(addslashes($u['user_name'])) ?>')">
                            Password
                        </button>
                        <?php if ($u['user_id'] != $_SESSION['admin_user_id']): ?>
                        <button class="btn btn-danger btn-sm"
                            onclick="confirmDelete(<?= $u['user_id'] ?>, '<?= htmlspecialchars(addslashes($u['user_name'])) ?>')">
                            Del
                        </button>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add user modal -->
<div class="modal-overlay" id="modal-add">
    <div class="modal" style="max-width:420px">
        <div class="modal-header">
            <span class="modal-title">Add user</span>
            <button class="modal-close" onclick="closeModal('modal-add')">×</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="add">
            <div class="modal-body">
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="user_name" required autocomplete="off">
                </div>
                <div class="form-group">
                    <label>Password <span class="text-muted text-sm">(min 8 characters)</span></label>
                    <input type="password" name="user_password" required autocomplete="new-password">
                </div>
                <div class="form-group">
                    <label>Confirm password</label>
                    <input type="password" name="user_password2" required autocomplete="new-password">
                </div>
                <div class="form-group" style="display:flex;align-items:center;gap:10px">
                    <input type="checkbox" name="user_is_admin" id="add-is_admin"
                           style="width:18px;height:18px;cursor:pointer">
                    <label for="add-is_admin" style="margin:0;cursor:pointer">Administrator</label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modal-add')">Cancel</button>
                <button type="submit" class="btn btn-primary">Add user</button>
            </div>
        </form>
    </div>
</div>

<!-- Change theme modal -->
<div class="modal-overlay" id="modal-theme">
    <div class="modal" style="max-width:420px">
        <div class="modal-header">
            <span class="modal-title">Theme — <span id="theme-username"></span></span>
            <button class="modal-close" onclick="closeModal('modal-theme')">×</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="change_theme">
            <input type="hidden" name="user_id" id="theme-user_id">
            <div class="modal-body">
                <div class="form-group">
                    <label>Colour theme</label>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:4px">
                        <?php foreach (THEMES as $key => $t): ?>
                        <label style="display:flex;align-items:center;gap:10px;padding:10px 12px;border:1px solid var(--border);border-radius:var(--radius-sm);cursor:pointer;transition:border-color 0.15s"
                               onmouseover="this.style.borderColor='<?= $t['primary'] ?>'"
                               onmouseout="this.querySelector('input').checked ? null : this.style.borderColor='var(--border)'">
                            <input type="radio" name="user_theme_key" value="<?= $key ?>"
                                   style="accent-color:<?= $t['primary'] ?>">
                            <span style="width:16px;height:16px;border-radius:50%;background:<?= $t['primary'] ?>;flex-shrink:0;border:1px solid var(--border)"></span>
                            <span style="font-size:13px;font-weight:500"><?= $t['label'] ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modal-theme')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save theme</button>
            </div>
        </form>
    </div>
</div>

<!-- Change password modal -->
<div class="modal-overlay" id="modal-password">
    <div class="modal" style="max-width:420px">
        <div class="modal-header">
            <span class="modal-title">Change password — <span id="pw-username"></span></span>
            <button class="modal-close" onclick="closeModal('modal-password')">×</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="change_password">
            <input type="hidden" name="user_id" id="pw-user_id">
            <div class="modal-body">
                <div class="form-group">
                    <label>New password <span class="text-muted text-sm">(min 8 characters)</span></label>
                    <input type="password" name="user_password" required autocomplete="new-password">
                </div>
                <div class="form-group">
                    <label>Confirm password</label>
                    <input type="password" name="user_password2" required autocomplete="new-password">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modal-password')">Cancel</button>
                <button type="submit" class="btn btn-primary">Update password</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete confirm modal -->
<div class="modal-overlay" id="modal-delete">
    <div class="modal" style="max-width:380px">
        <div class="modal-header">
            <span class="modal-title">Delete user</span>
            <button class="modal-close" onclick="closeModal('modal-delete')">×</button>
        </div>
        <div class="modal-body"><p>Delete user <strong id="delete-user-name"></strong>?</p></div>
        <div class="modal-footer">
            <form method="POST">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="user_id" id="delete-user_id">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modal-delete')">Cancel</button>
                <button type="submit" class="btn btn-danger">Delete</button>
            </form>
        </div>
    </div>
</div>

<script>
function changeTheme(id, name, currentKey) {
    document.getElementById('theme-user_id').value          = id;
    document.getElementById('theme-username').textContent   = name;
    // Select current theme radio
    const radio = document.querySelector(`input[name="user_theme_key"][value="${currentKey}"]`);
    if (radio) radio.checked = true;
    openModal('modal-theme');
}
function changePassword(id, name) {
    document.getElementById('pw-user_id').value          = id;
    document.getElementById('pw-username').textContent   = name;
    openModal('modal-password');
}
function confirmDelete(id, name) {
    document.getElementById('delete-user_id').value              = id;
    document.getElementById('delete-user-name').textContent      = name;
    openModal('modal-delete');
}
</script>

<?php require_once 'includes/footer.php'; ?>
