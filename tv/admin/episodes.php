<?php
require_once 'includes/auth.php';
require_once dirname(__DIR__) . '/dbconnect.php';

$page_title = 'Episodes';
$active_nav = 'episodes';
$message = '';
$error   = '';

// ── Reorder via AJAX ──────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['CONTENT_TYPE'])
    && str_contains($_SERVER['CONTENT_TYPE'], 'application/json')) {
    $data  = json_decode(file_get_contents('php://input'), true);
    $order = $data['order'] ?? [];
    foreach ($order as $index => $episode_id) {
        $stmt = $conn->prepare('UPDATE episodes SET episode_index=? WHERE episode_id=?');
        $new_index = $index + 1;
        $stmt->bind_param('ii', $new_index, $episode_id);
        $stmt->execute();
        $stmt->close();
    }
    echo json_encode(['ok' => true]);
    $conn->close();
    exit;
}

// ── Handle POST actions ───────────────────────────────────────────────────────
$action   = $_POST['action']  ?? '';
$show_id  = (int)($_POST['show_id'] ?? $_GET['show_id'] ?? 0);

if ($action === 'add' || $action === 'edit') {
    $episode_id       = (int)($_POST['episode_id']       ?? 0);
    $episode_file     = trim($_POST['episode_file']      ?? '');
    $episode_index    = (int)($_POST['episode_index']    ?? 0);
    $episode_title    = trim($_POST['episode_title']     ?? '');
    $episode_summary  = trim($_POST['episode_summary']   ?? '');
    $episode_duration = (int)($_POST['episode_duration'] ?? 0);
    $episode_show     = (int)($_POST['show_id']          ?? 0);

    if (!$episode_file) {
        $error = 'Episode file path is required.';
    } else {
        if ($action === 'add') {
            $stmt = $conn->prepare(
                'INSERT INTO episodes (show_id, episode_file, episode_index, episode_title, episode_summary, episode_duration)
                 VALUES (?,?,?,?,?,?)'
            );
            $stmt->bind_param('isissi', $episode_show, $episode_file, $episode_index, $episode_title, $episode_summary, $episode_duration);
            $stmt->execute();
            $stmt->close();
            $message = 'Episode added.';
        } else {
            $stmt = $conn->prepare(
                'UPDATE episodes SET show_id=?, episode_file=?, episode_index=?, episode_title=?, episode_summary=?, episode_duration=?
                 WHERE episode_id=?'
            );
            // i=episode_show, s=episode_file, i=episode_index, s=episode_title, s=episode_summary, i=episode_duration, i=episode_id
            $stmt->bind_param('isissii', $episode_show, $episode_file, $episode_index, $episode_title, $episode_summary, $episode_duration, $episode_id);
            $stmt->execute();
            $stmt->close();
            $message = 'Episode updated.';
        }
    }
}

if ($action === 'delete') {
    $episode_id = (int)($_POST['episode_id'] ?? 0);
    $stmt = $conn->prepare('DELETE FROM episodes WHERE episode_id=?');
    $stmt->bind_param('i', $episode_id);
    $stmt->execute();
    $stmt->close();
    $message = 'Episode deleted.';
}

// ── Fetch shows for dropdown ──────────────────────────────────────────────────
$all_shows = [];
$r = $conn->query('SELECT show_id, show_title FROM shows ORDER BY show_title');
if ($r) { while ($row = $r->fetch_assoc()) $all_shows[] = $row; $r->free(); }

// ── Fetch episodes ────────────────────────────────────────────────────────────
$episodes   = [];
$show_title = '';

if ($show_id) {
    $stmt = $conn->prepare('SELECT show_title FROM shows WHERE show_id=? LIMIT 1');
    $stmt->bind_param('i', $show_id);
    $stmt->execute();
    $stmt->bind_result($show_title);
    $stmt->fetch();
    $stmt->close();

    $stmt = $conn->prepare('SELECT episode_id, episode_index, episode_title, episode_summary, episode_duration, episode_file FROM episodes WHERE show_id=? ORDER BY episode_index');
    $stmt->bind_param('i', $show_id);
    $stmt->execute();
    $episodes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

$conn->close();

// ── Duration options (30-min increments up to 6 hours, stored in seconds) ────
function duration_options(int $selected = 0): string {
    $html = '<option value="0"' . ($selected === 0 ? ' selected' : '') . '>— Unknown —</option>';
    for ($mins = 30; $mins <= 360; $mins += 30) {
        $seconds = $mins * 60;
        $label   = $mins < 60
            ? "{$mins} min"
            : ($mins % 60 === 0
                ? ($mins / 60) . ' hr'
                : floor($mins / 60) . ' hr ' . ($mins % 60) . ' min');
        $sel  = $selected === $seconds ? ' selected' : '';
        $html .= "<option value=\"{$seconds}\"{$sel}>{$label}</option>";
    }
    return $html;
}

require_once 'includes/header.php';
?>

<div class="page-header">
    <h1>Episodes<?= $show_title ? ' — ' . htmlspecialchars($show_title) : '' ?></h1>
    <?php if ($show_id): ?>
        <div class="flex gap-2">
            <a href="shows.php" class="btn btn-secondary">← Shows</a>
            <button class="btn btn-primary" onclick="openModal('modal-add')">+ Add episode</button>
        </div>
    <?php endif; ?>
</div>

<?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
<?php if ($error):   ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<?php if (!$show_id): ?>
<!-- Show picker when no show is selected -->
<div class="card">
    <div class="card-body">
        <p class="text-muted" style="margin-bottom:16px">Select a show to manage its episodes.</p>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:12px">
            <?php foreach ($all_shows as $s): ?>
            <a href="episodes.php?show_id=<?= $s['show_id'] ?>" class="card" style="padding:16px;text-decoration:none;display:block;transition:border-color 0.15s" onmouseover="this.style.borderColor='var(--primary)'" onmouseout="this.style.borderColor='var(--border)'">
                <div style="font-weight:600;color:var(--text)"><?= htmlspecialchars($s['show_title']) ?></div>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php else: ?>

<div class="card">
    <div class="card-header">
        <span class="card-title"><?= count($episodes) ?> episode<?= count($episodes) !== 1 ? 's':'' ?></span>
        <span class="text-sm text-muted">Drag rows to reorder. Order is saved automatically.</span>
    </div>
    <div class="table-wrap">
        <table id="episode-table">
            <thead>
                <tr>
                    <th style="width:32px"></th>
                    <th style="width:50px">#</th>
                    <th>Title</th>
                    <th style="width:70px">Duration</th>
                    <th>File path</th>
                    <th style="width:120px">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($episodes): ?>
                <?php foreach ($episodes as $ep): ?>
                <tr data-id="<?= $ep['episode_id'] ?>" draggable="true">
                    <td><span class="drag-handle" title="Drag to reorder">⠿</span></td>
                    <td class="td-mono"><?= (int)$ep['episode_index'] ?></td>
                    <td><?= htmlspecialchars($ep['episode_title'] ?: '—') ?></td>
                    <td class="td-mono"><?php
                        $d = (int)$ep['episode_duration'];
                        if (!$d) { echo '—'; }
                        elseif ($d < 3600) { echo ($d / 60) . ' min'; }
                        elseif ($d % 3600 === 0) { echo ($d / 3600) . ' hr'; }
                        else { echo floor($d/3600) . ' hr ' . (($d % 3600) / 60) . ' min'; }
                    ?></td>
                    <td class="td-mono" style="word-break:break-all"><?= htmlspecialchars($ep['episode_file']) ?></td>
                    <td>
                        <div class="flex gap-2">
                            <button class="btn btn-secondary btn-sm"
                                data-ep="<?= htmlspecialchars(json_encode($ep), ENT_QUOTES) ?>"
                                onclick="editEpisode(this)">Edit</button>
                            <button class="btn btn-danger btn-sm"
                                onclick="confirmDelete(<?= $ep['episode_id'] ?>)">Del</button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="6" class="text-muted" style="text-align:center;padding:24px">No episodes yet. Add one above.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add modal -->
<div class="modal-overlay" id="modal-add">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title">Add episode</span>
            <button class="modal-close" onclick="closeModal('modal-add')">×</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="add">
            <input type="hidden" name="show_id" value="<?= $show_id ?>">
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group">
                        <label>Episode index <span class="text-muted text-sm">(play order)</span></label>
                        <input type="number" name="episode_index" value="<?= count($episodes) + 1 ?>" min="1">
                    </div>
                    <div class="form-group">
                        <label>Duration</label>
                        <select name="episode_duration">
                            <?= duration_options(0) ?>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label>Title</label>
                    <input type="text" name="episode_title" placeholder="S01E01 — Pilot">
                </div>
                <div class="form-group">
                    <label>Summary</label>
                    <textarea name="episode_summary" rows="3"></textarea>
                </div>
                <div class="form-group">
                    <label>File path</label>
                    <input type="text" name="episode_file" required placeholder="_BASEDIR_/Season 1/S01E01.mkv">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modal-add')">Cancel</button>
                <button type="submit" class="btn btn-primary">Add</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit modal -->
<div class="modal-overlay" id="modal-edit">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title">Edit episode</span>
            <button class="modal-close" onclick="closeModal('modal-edit')">×</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="show_id" value="<?= $show_id ?>">
            <input type="hidden" name="episode_id" id="edit-episode_id">
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group">
                        <label>Episode index</label>
                        <input type="number" name="episode_index" id="edit-episode_index" min="1">
                    </div>
                    <div class="form-group">
                        <label>Duration</label>
                        <select name="episode_duration" id="edit-episode_duration">
                            <?= duration_options(0) ?>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label>Title</label>
                    <input type="text" name="episode_title" id="edit-episode_title">
                </div>
                <div class="form-group">
                    <label>Summary</label>
                    <textarea name="episode_summary" id="edit-episode_summary" rows="3"></textarea>
                </div>
                <div class="form-group">
                    <label>File path</label>
                    <input type="text" name="episode_file" id="edit-episode_file" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modal-edit')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete confirm modal -->
<div class="modal-overlay" id="modal-delete">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title">Delete episode</span>
            <button class="modal-close" onclick="closeModal('modal-delete')">×</button>
        </div>
        <div class="modal-body"><p>Are you sure you want to delete this episode?</p></div>
        <div class="modal-footer">
            <form method="POST">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="show_id" value="<?= $show_id ?>">
                <input type="hidden" name="episode_id" id="delete-episode_id">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modal-delete')">Cancel</button>
                <button type="submit" class="btn btn-danger">Delete</button>
            </form>
        </div>
    </div>
</div>

<script>
initDragSort('episode-table', 'episodes.php?show_id=<?= $show_id ?>');

function editEpisode(btn) {
    const ep = JSON.parse(btn.dataset.ep);
    document.getElementById('edit-episode_id').value       = ep.episode_id;
    document.getElementById('edit-episode_index').value    = ep.episode_index;
    document.getElementById('edit-episode_title').value    = ep.episode_title    || '';
    document.getElementById('edit-episode_summary').value  = ep.episode_summary  || '';
    document.getElementById('edit-episode_duration').value = ep.episode_duration || 0;
    document.getElementById('edit-episode_file').value     = ep.episode_file;
    openModal('modal-edit');
}
function confirmDelete(id) {
    document.getElementById('delete-episode_id').value = id;
    openModal('modal-delete');
}
</script>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
