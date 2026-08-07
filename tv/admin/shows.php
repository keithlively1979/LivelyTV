<?php
require_once 'includes/auth.php';
require_once dirname(__DIR__) . '/dbconnect.php';

$page_title = 'Shows';
$active_nav = 'shows';
$message = '';
$error   = '';

// ── Handle POST actions ───────────────────────────────────────────────────────
$action = $_POST['action'] ?? '';

if ($action === 'add' || $action === 'edit') {
    $show_id              = (int)($_POST['show_id'] ?? 0);
    $show_title           = trim($_POST['show_title'] ?? '');
    $show_basedir         = trim($_POST['show_basedir'] ?? '');
    $show_bumperin        = trim($_POST['show_bumperin'] ?? '');
    $show_bumperout       = trim($_POST['show_bumperout'] ?? '');
    $show_total_episodes  = (int)($_POST['show_total_episodes'] ?? 0);
    $show_lastplayed      = (int)($_POST['show_lastplayed'] ?? 0);

    if (!$show_title) {
        $error = 'Show title is required.';
    } else {
        if ($action === 'add') {
            $stmt = $conn->prepare('INSERT INTO shows (show_title, show_basedir, show_bumperin, show_bumperout, show_total_episodes, show_lastplayed) VALUES (?,?,?,?,?,?)');
            $stmt->bind_param('ssssii', $show_title, $show_basedir, $show_bumperin, $show_bumperout, $show_total_episodes, $show_lastplayed);
            $stmt->execute();
            $stmt->close();
            $message = "Show \"$show_title\" added.";
        } else {
            $stmt = $conn->prepare('UPDATE shows SET show_title=?, show_basedir=?, show_bumperin=?, show_bumperout=?, show_total_episodes=?, show_lastplayed=? WHERE show_id=?');
            $stmt->bind_param('ssssiis', $show_title, $show_basedir, $show_bumperin, $show_bumperout, $show_total_episodes, $show_lastplayed, $show_id);

            // fix: last param should be int
            $stmt = $conn->prepare('UPDATE shows SET show_title=?, show_basedir=?, show_bumperin=?, show_bumperout=?, show_total_episodes=?, show_lastplayed=? WHERE show_id=?');
            $stmt->bind_param('ssssiii', $show_title, $show_basedir, $show_bumperin, $show_bumperout, $show_total_episodes, $show_lastplayed, $show_id);
            $stmt->execute();
            $stmt->close();
            $message = "Show updated.";
        }
    }
}

if ($action === 'delete') {
    $show_id = (int)($_POST['show_id'] ?? 0);
    $stmt = $conn->prepare('DELETE FROM shows WHERE show_id=?');
    $stmt->bind_param('i', $show_id);
    $stmt->execute();
    $stmt->close();
    $message = 'Show deleted.';
}

if ($action === 'reset_progress') {
    $show_id = (int)($_POST['show_id'] ?? 0);
    $stmt = $conn->prepare('UPDATE shows SET show_lastplayed=0 WHERE show_id=?');
    $stmt->bind_param('i', $show_id);
    $stmt->execute();
    $stmt->close();
    $message = 'Playback progress reset.';
}

// ── Fetch shows ───────────────────────────────────────────────────────────────
$search = trim($_GET['q'] ?? '');
if ($search) {
    $stmt = $conn->prepare('SELECT * FROM shows WHERE show_title LIKE ? ORDER BY show_title');
    $like = '%' . $search . '%';
    $stmt->bind_param('s', $like);
} else {
    $stmt = $conn->prepare('SELECT * FROM shows ORDER BY show_title');
}
$stmt->execute();
$shows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$conn->close();

require_once 'includes/header.php';
?>

<div class="page-header">
    <h1>Shows</h1>
    <button class="btn btn-primary" onclick="openModal('modal-add')">+ Add show</button>
</div>

<?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
<?php if ($error):   ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="card">
    <div class="card-header">
        <span class="card-title"><?= count($shows) ?> show<?= count($shows) !== 1 ? 's':'' ?></span>
        <form method="GET" style="display:flex;gap:8px;align-items:center">
            <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search shows…" style="width:220px">
            <button type="submit" class="btn btn-secondary btn-sm">Search</button>
            <?php if ($search): ?><a href="shows.php" class="btn btn-secondary btn-sm">Clear</a><?php endif; ?>
        </form>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Episodes</th>
                    <th>Progress</th>
                    <th>Base dir</th>
                    <th style="width:160px">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($shows): ?>
                <?php foreach ($shows as $s): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($s['show_title']) ?></strong></td>
                    <td><?= (int)$s['show_total_episodes'] ?></td>
                    <td>
                        <?php
                            $pct = $s['show_total_episodes'] > 0
                                ? round(($s['show_lastplayed'] / $s['show_total_episodes']) * 100)
                                : 0;
                        ?>
                        <div style="display:flex;align-items:center;gap:8px">
                            <div style="flex:1;height:6px;background:var(--border);border-radius:3px;overflow:hidden;min-width:60px">
                                <div style="width:<?= $pct ?>%;height:100%;background:var(--primary);border-radius:3px"></div>
                            </div>
                            <span class="text-sm text-muted"><?= $s['show_lastplayed'] ?>/<?= $s['show_total_episodes'] ?></span>
                        </div>
                    </td>
                    <td class="td-mono"><?= htmlspecialchars($s['show_basedir']) ?></td>
                    <td>
                        <div class="flex gap-2">
                            <button class="btn btn-secondary btn-sm"
                                onclick="editShow(<?= htmlspecialchars(json_encode($s)) ?>)">Edit</button>
                            <a href="episodes.php?show_id=<?= $s['show_id'] ?>" class="btn btn-secondary btn-sm">Episodes</a>
                            <button class="btn btn-danger btn-sm"
                                onclick="confirmDelete(<?= $s['show_id'] ?>, '<?= htmlspecialchars(addslashes($s['show_title'])) ?>')">Del</button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="5" class="text-muted" style="text-align:center;padding:24px">No shows found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add modal -->
<div class="modal-overlay" id="modal-add">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title">Add show</span>
            <button class="modal-close" onclick="closeModal('modal-add')">×</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="add">
            <div class="modal-body">
                <div class="form-group">
                    <label>Title</label>
                    <input type="text" name="show_title" required>
                </div>
                <div class="form-group">
                    <label>Base directory <span class="text-muted text-sm">(replaces _BASEDIR_ token)</span></label>
                    <input type="text" name="show_basedir">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Bumper in file</label>
                        <input type="text" name="show_bumperin">
                    </div>
                    <div class="form-group">
                        <label>Bumper out file</label>
                        <input type="text" name="show_bumperout">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Total episodes</label>
                        <input type="number" name="show_total_episodes" value="0" min="0">
                    </div>
                    <div class="form-group">
                        <label>Last played index</label>
                        <input type="number" name="show_lastplayed" value="0" min="0">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modal-add')">Cancel</button>
                <button type="submit" class="btn btn-primary">Add show</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit modal -->
<div class="modal-overlay" id="modal-edit">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title">Edit show</span>
            <button class="modal-close" onclick="closeModal('modal-edit')">×</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="show_id" id="edit-show_id">
            <div class="modal-body">
                <div class="form-group">
                    <label>Title</label>
                    <input type="text" name="show_title" id="edit-show_title" required>
                </div>
                <div class="form-group">
                    <label>Base directory</label>
                    <input type="text" name="show_basedir" id="edit-show_basedir">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Bumper in file</label>
                        <input type="text" name="show_bumperin" id="edit-show_bumperin">
                    </div>
                    <div class="form-group">
                        <label>Bumper out file</label>
                        <input type="text" name="show_bumperout" id="edit-show_bumperout">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Total episodes</label>
                        <input type="number" name="show_total_episodes" id="edit-show_total_episodes" min="0">
                    </div>
                    <div class="form-group">
                        <label>Last played index</label>
                        <input type="number" name="show_lastplayed" id="edit-show_lastplayed" min="0">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" id="reset-progress-btn"
                    class="btn btn-secondary btn-sm"
                    style="margin-right:auto"
                    onclick="submitReset()">Reset progress</button>
                <button type="button" class="btn btn-secondary" onclick="closeModal('modal-edit')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save</button>
            </div>
        </form>
    </div>
</div>

<!-- Reset form sits outside the edit form to avoid nesting -->
<form method="POST" id="form-reset-progress" style="display:none">
    <input type="hidden" name="action"  value="reset_progress">
    <input type="hidden" name="show_id" id="reset-show_id">
</form>

<!-- Delete confirm modal -->
<div class="modal-overlay" id="modal-delete">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title">Delete show</span>
            <button class="modal-close" onclick="closeModal('modal-delete')">×</button>
        </div>
        <div class="modal-body">
            <p>Are you sure you want to delete <strong id="delete-show-name"></strong>?</p>
            <p class="text-muted text-sm mt-1">This will not automatically delete associated episodes.</p>
        </div>
        <div class="modal-footer">
            <form method="POST">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="show_id" id="delete-show_id">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modal-delete')">Cancel</button>
                <button type="submit" class="btn btn-danger">Delete</button>
            </form>
        </div>
    </div>
</div>

<script>
function editShow(s) {
    document.getElementById('edit-show_id').value           = s.show_id;
    document.getElementById('edit-show_title').value        = s.show_title;
    document.getElementById('edit-show_basedir').value      = s.show_basedir   || '';
    document.getElementById('edit-show_bumperin').value     = s.show_bumperin  || '';
    document.getElementById('edit-show_bumperout').value    = s.show_bumperout || '';
    document.getElementById('edit-show_total_episodes').value = s.show_total_episodes;
    document.getElementById('edit-show_lastplayed').value   = s.show_lastplayed;
    document.getElementById('reset-show_id').value          = s.show_id;
    openModal('modal-edit');
}
function confirmDelete(id, name) {
    document.getElementById('delete-show_id').value = id;
    document.getElementById('delete-show-name').textContent = name;
    openModal('modal-delete');
}
function submitReset() {
    if (confirm('Reset playback progress to 0 for this show?')) {
        document.getElementById('form-reset-progress').submit();
    }
}
</script>

<?php require_once 'includes/footer.php'; ?>
