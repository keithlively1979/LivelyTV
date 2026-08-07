<?php
require_once 'includes/auth.php';
require_once dirname(__DIR__) . '/dbconnect.php';

$page_title = 'Play Log';
$active_nav = 'playlog';

// ── Filters ───────────────────────────────────────────────────────────────────
$search  = trim($_GET['q']       ?? '');
$channel = trim($_GET['channel'] ?? '');
$date_from = trim($_GET['from']  ?? '');
$date_to   = trim($_GET['to']    ?? '');
$page    = max(1, (int)($_GET['page'] ?? 1));
$limit   = 100;
$offset  = ($page - 1) * $limit;

$where  = ['1=1'];
$params = [];
$types  = '';

if ($search)    { $where[] = 'playlogtitle LIKE ?';  $params[] = '%'.$search.'%';  $types .= 's'; }
if ($channel)   { $where[] = 'playlogchan LIKE ?';   $params[] = '%'.$channel.'%'; $types .= 's'; }
if ($date_from) { $where[] = 'DATE(playlogdt) >= ?'; $params[] = $date_from;       $types .= 's'; }
if ($date_to)   { $where[] = 'DATE(playlogdt) <= ?'; $params[] = $date_to;         $types .= 's'; }

$where_sql = implode(' AND ', $where);

// Total count
$stmt = $conn->prepare("SELECT COUNT(*) FROM playlog WHERE $where_sql");
if ($params) $stmt->bind_param($types, ...$params);
$stmt->execute();
$stmt->bind_result($total);
$stmt->fetch();
$stmt->close();
$total_pages = (int)ceil($total / $limit);

// Rows
$stmt = $conn->prepare("SELECT playlogdt, playlogchan, playlogtitle FROM playlog WHERE $where_sql ORDER BY playlogdt DESC LIMIT ? OFFSET ?");
$all_params = array_merge($params, [$limit, $offset]);
$all_types  = $types . 'ii';
$stmt->bind_param($all_types, ...$all_params);
$stmt->execute();
$logs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Distinct channels for filter dropdown
$channels = [];
$r = $conn->query('SELECT DISTINCT playlogchan FROM playlog ORDER BY playlogchan');
if ($r) { while ($row = $r->fetch_row()) $channels[] = $row[0]; $r->free(); }

// Clear log action
if (isset($_POST['action']) && $_POST['action'] === 'clear_log') {
    $conn->query('TRUNCATE TABLE playlog');
    $conn->close();
    header('Location: playlog.php');
    exit;
}

$conn->close();
require_once 'includes/header.php';
?>

<div class="page-header">
    <h1>Play Log</h1>
    <button class="btn btn-danger btn-sm" onclick="openModal('modal-clear')">Clear log</button>
</div>

<form method="GET">
<div class="filters-bar">
    <div class="form-group">
        <label>Title</label>
        <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search title…" style="width:200px">
    </div>
    <div class="form-group">
        <label>Channel</label>
        <select name="channel" style="width:220px">
            <option value="">All channels</option>
            <?php foreach ($channels as $ch): ?>
            <option value="<?= htmlspecialchars($ch) ?>" <?= $channel===$ch?'selected':'' ?>><?= htmlspecialchars($ch) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-group">
        <label>From</label>
        <input type="date" name="from" value="<?= htmlspecialchars($date_from) ?>" style="width:150px">
    </div>
    <div class="form-group">
        <label>To</label>
        <input type="date" name="to" value="<?= htmlspecialchars($date_to) ?>" style="width:150px">
    </div>
    <button type="submit" class="btn btn-primary">Filter</button>
    <a href="playlog.php" class="btn btn-secondary">Reset</a>
</div>
</form>

<div class="card">
    <div class="card-header">
        <span class="card-title"><?= number_format($total) ?> entr<?= $total !== 1 ? 'ies' : 'y' ?></span>
        <?php if ($total_pages > 1): ?>
        <span class="text-sm text-muted">Page <?= $page ?> of <?= $total_pages ?></span>
        <?php endif; ?>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Date / Time</th>
                    <th>Channel</th>
                    <th>Title</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($logs): ?>
                <?php foreach ($logs as $row): ?>
                <tr>
                    <td class="td-mono"><?= htmlspecialchars($row['playlogdt']) ?></td>
                    <td><span class="badge badge-blue" style="font-size:10px"><?= htmlspecialchars($row['playlogchan']) ?></span></td>
                    <td><?= htmlspecialchars($row['playlogtitle']) ?></td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="3" class="text-muted" style="text-align:center;padding:24px">No entries match the current filters.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($total_pages > 1): ?>
    <div class="pagination">
        <?php
        $start = max(1, $page - 4);
        $end   = min($total_pages, $page + 4);
        if ($start > 1): ?>
            <a href="?<?= http_build_query(array_merge($_GET, ['page'=>1])) ?>">1</a>
            <?php if ($start > 2): ?><span class="dots">…</span><?php endif; ?>
        <?php endif; ?>

        <?php for ($p = $start; $p <= $end; $p++):
            $qs = http_build_query(array_merge($_GET, ['page' => $p]));
        ?>
            <?php if ($p === $page): ?><span class="current"><?= $p ?></span>
            <?php else: ?><a href="?<?= $qs ?>"><?= $p ?></a><?php endif; ?>
        <?php endfor; ?>

        <?php if ($end < $total_pages): ?>
            <?php if ($end < $total_pages - 1): ?><span class="dots">…</span><?php endif; ?>
            <a href="?<?= http_build_query(array_merge($_GET, ['page'=>$total_pages])) ?>"><?= $total_pages ?></a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Clear confirm modal -->
<div class="modal-overlay" id="modal-clear">
    <div class="modal" style="max-width:380px">
        <div class="modal-header">
            <span class="modal-title">Clear play log</span>
            <button class="modal-close" onclick="closeModal('modal-clear')">×</button>
        </div>
        <div class="modal-body">
            <p>This will permanently delete all <?= number_format($total) ?> log entries. This cannot be undone.</p>
        </div>
        <div class="modal-footer">
            <form method="POST">
                <input type="hidden" name="action" value="clear_log">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modal-clear')">Cancel</button>
                <button type="submit" class="btn btn-danger">Clear all entries</button>
            </form>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
