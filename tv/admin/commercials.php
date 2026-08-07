<?php
require_once 'includes/auth.php';
require_once dirname(__DIR__) . '/dbconnect.php';

$page_title = 'Commercials';
$active_nav = 'commercials';
$message = '';
$error   = '';

$action = $_POST['action'] ?? '';

if ($action === 'add' || $action === 'edit') {
    $commercial_id       = (int)($_POST['commercial_id']       ?? 0);
    $commercial_file     = trim($_POST['commercial_file']      ?? '');
    $commercial_title    = trim($_POST['commercial_title']     ?? '');
    $commercial_duration = (int)($_POST['commercial_duration'] ?? 30);
    $commercial_mature   = isset($_POST['commercial_mature']) ? 1 : 0;

    if (!$commercial_file) {
        $error = 'File path is required.';
    } elseif ($action === 'add') {
        $stmt = $conn->prepare(
            'INSERT INTO commercials (commercial_file, commercial_title, commercial_duration, commercial_mature)
             VALUES (?,?,?,?)'
        );
        $stmt->bind_param('ssii', $commercial_file, $commercial_title, $commercial_duration, $commercial_mature);
        $stmt->execute();
        $stmt->close();
        $message = 'Commercial added.';
    } else {
        $stmt = $conn->prepare(
            'UPDATE commercials SET commercial_file=?, commercial_title=?, commercial_duration=?, commercial_mature=?
             WHERE commercial_id=?'
        );
        $stmt->bind_param('ssiii', $commercial_file, $commercial_title, $commercial_duration, $commercial_mature, $commercial_id);
        $stmt->execute();
        $stmt->close();
        $message = 'Commercial updated.';
    }
}

if ($action === 'delete') {
    $commercial_id = (int)($_POST['commercial_id'] ?? 0);
    $stmt = $conn->prepare('DELETE FROM commercials WHERE commercial_id=?');
    $stmt->bind_param('i', $commercial_id);
    $stmt->execute();
    $stmt->close();
    $message = 'Commercial deleted.';
}

$search = trim($_GET['q'] ?? '');
$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 50;
$offset = ($page - 1) * $limit;

if ($search) {
    $like = '%' . $search . '%';
    $stmt = $conn->prepare(
        'SELECT COUNT(*) FROM commercials WHERE commercial_file LIKE ? OR commercial_title LIKE ?'
    );
    $stmt->bind_param('ss', $like, $like);
} else {
    $stmt = $conn->prepare('SELECT COUNT(*) FROM commercials');
}
$stmt->execute();
$stmt->bind_result($total);
$stmt->fetch();
$stmt->close();
$total_pages = (int)ceil($total / $limit);

if ($search) {
    $stmt = $conn->prepare(
        'SELECT commercial_id, commercial_file, commercial_title, commercial_duration, commercial_mature
           FROM commercials
          WHERE commercial_file LIKE ? OR commercial_title LIKE ?
          ORDER BY commercial_title, commercial_file LIMIT ? OFFSET ?'
    );
    $stmt->bind_param('ssii', $like, $like, $limit, $offset);
} else {
    $stmt = $conn->prepare(
        'SELECT commercial_id, commercial_file, commercial_title, commercial_duration, commercial_mature
           FROM commercials ORDER BY commercial_title, commercial_file LIMIT ? OFFSET ?'
    );
    $stmt->bind_param('ii', $limit, $offset);
}
$stmt->execute();
$commercials = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$conn->close();

require_once 'includes/header.php';
?>

<div class="page-header">
    <h1>Commercials</h1>
    <button class="btn btn-primary" onclick="openModal('modal-add')">+ Add commercial</button>
</div>

<?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
<?php if ($error):   ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="card">
    <div class="card-header">
        <span class="card-title"><?= $total ?> commercial<?= $total !== 1 ? 's' : '' ?></span>
        <form method="GET" style="display:flex;gap:8px">
            <input type="text" name="q" value="<?= htmlspecialchars($search) ?>"
                   placeholder="Search title or file…" style="width:260px">
            <button type="submit" class="btn btn-secondary btn-sm">Search</button>
            <?php if ($search): ?>
                <a href="commercials.php" class="btn btn-secondary btn-sm">Clear</a>
            <?php endif; ?>
        </form>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Title</th>
                    <th style="width:90px">Duration</th>
                    <th style="width:70px">Mature</th>
                    <th>File path</th>
                    <th style="width:100px">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($commercials): ?>
                <?php foreach ($commercials as $c): ?>
                <tr>
                    <td><?= htmlspecialchars($c['commercial_title'] ?: '—') ?></td>
                    <td class="td-mono"><?= (int)$c['commercial_duration'] ?>s</td>
                    <td>
                        <?php if ($c['commercial_mature']): ?>
                            <span class="badge badge-red">Yes</span>
                        <?php else: ?>
                            <span class="badge badge-green">No</span>
                        <?php endif; ?>
                    </td>
                    <td class="td-mono" style="max-width:260px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"
                        title="<?= htmlspecialchars($c['commercial_file']) ?>">
                        <?= htmlspecialchars($c['commercial_file']) ?>
                    </td>
                    <td>
                        <div class="flex gap-2">
                            <button class="btn btn-secondary btn-sm"
                                data-c="<?= htmlspecialchars(json_encode($c), ENT_QUOTES) ?>"
                                onclick="editCommercial(this)">Edit</button>
                            <button class="btn btn-danger btn-sm"
                                onclick="confirmDelete(<?= $c['commercial_id'] ?>)">Del</button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="5" class="text-muted" style="text-align:center;padding:24px">No commercials found.</td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($total_pages > 1): ?>
    <div class="pagination">
        <?php for ($p = 1; $p <= $total_pages; $p++):
            $qs = http_build_query(array_merge($_GET, ['page' => $p]));
        ?>
            <?php if ($p === $page): ?><span class="current"><?= $p ?></span>
            <?php else: ?><a href="?<?= $qs ?>"><?= $p ?></a><?php endif; ?>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Add modal -->
<div class="modal-overlay" id="modal-add">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title">Add commercial</span>
            <button class="modal-close" onclick="closeModal('modal-add')">×</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="add">
            <div class="modal-body">
                <div class="form-group">
                    <label>Title</label>
                    <input type="text" name="commercial_title" placeholder="30-second spot">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Duration <span class="text-muted text-sm">(seconds)</span></label>
                        <input type="number" name="commercial_duration" value="30" min="1">
                    </div>
                    <div class="form-group" style="display:flex;align-items:center;gap:10px;padding-top:22px">
                        <input type="checkbox" name="commercial_mature" id="add-mature"
                               style="width:18px;height:18px;cursor:pointer">
                        <label for="add-mature" style="margin:0;cursor:pointer">Mature content</label>
                    </div>
                </div>
                <div class="form-group">
                    <label>File path</label>
                    <textarea name="commercial_file" rows="3" required
                              placeholder="/media/Commercials/ad.mp4"></textarea>
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
            <span class="modal-title">Edit commercial</span>
            <button class="modal-close" onclick="closeModal('modal-edit')">×</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="commercial_id" id="edit-commercial_id">
            <div class="modal-body">
                <div class="form-group">
                    <label>Title</label>
                    <input type="text" name="commercial_title" id="edit-commercial_title">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Duration <span class="text-muted text-sm">(seconds)</span></label>
                        <input type="number" name="commercial_duration" id="edit-commercial_duration" min="1">
                    </div>
                    <div class="form-group" style="display:flex;align-items:center;gap:10px;padding-top:22px">
                        <input type="checkbox" name="commercial_mature" id="edit-mature"
                               style="width:18px;height:18px;cursor:pointer">
                        <label for="edit-mature" style="margin:0;cursor:pointer">Mature content</label>
                    </div>
                </div>
                <div class="form-group">
                    <label>File path</label>
                    <textarea name="commercial_file" id="edit-commercial_file" rows="3" required></textarea>
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
            <span class="modal-title">Delete commercial</span>
            <button class="modal-close" onclick="closeModal('modal-delete')">×</button>
        </div>
        <div class="modal-body"><p>Delete this commercial?</p></div>
        <div class="modal-footer">
            <form method="POST">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="commercial_id" id="delete-commercial_id">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modal-delete')">Cancel</button>
                <button type="submit" class="btn btn-danger">Delete</button>
            </form>
        </div>
    </div>
</div>

<script>
function editCommercial(btn) {
    const c = JSON.parse(btn.dataset.c);
    document.getElementById('edit-commercial_id').value       = c.commercial_id;
    document.getElementById('edit-commercial_title').value    = c.commercial_title    || '';
    document.getElementById('edit-commercial_duration').value = c.commercial_duration || 30;
    document.getElementById('edit-commercial_file').value     = c.commercial_file     || '';
    document.getElementById('edit-mature').checked            = c.commercial_mature == 1;
    openModal('modal-edit');
}
function confirmDelete(id) {
    document.getElementById('delete-commercial_id').value = id;
    openModal('modal-delete');
}
</script>

<?php require_once 'includes/footer.php'; ?>
