<?php
require_once 'includes/auth.php';
require_once dirname(__DIR__) . '/dbconnect.php';

$page_title = 'Schedule';
$active_nav = 'schedule';
$message = '';

$action     = $_POST['action']  ?? '';
$channel_id = (int)($_GET['ch'] ?? $_POST['channel_id'] ?? 0);

$days  = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
$hours = range(0, 23);
$mins  = [0, 30];

// ── Fetch channels from channels table ────────────────────────────────────────
$channels = [];
$r = $conn->query('SELECT channel_id, channel_name FROM channels ORDER BY channel_id');
if ($r) { while ($row = $r->fetch_assoc()) $channels[] = $row; $r->free(); }

// Default to first channel if none selected or invalid
if (!$channel_id || !in_array($channel_id, array_column($channels, 'channel_id'))) {
    $channel_id = $channels[0]['channel_id'] ?? 0;
}

$channel_name = '';
foreach ($channels as $ch) {
    if ($ch['channel_id'] === $channel_id) { $channel_name = $ch['channel_name']; break; }
}

// ── POST: save a slot ─────────────────────────────────────────────────────────
if ($action === 'save') {
    $sid     = (int)($_POST['schedule_id']   ?? 0);
    $show_id = (int)($_POST['show_id']       ?? 0);
    $dow     = (int)($_POST['schedule_dow']  ?? 0);
    $hour    = (int)($_POST['schedule_hour'] ?? 0);
    $min     = (int)($_POST['schedule_min']  ?? 0);
    $chan    = (int)($_POST['channel_id']    ?? $channel_id);

    if ($sid) {
        $stmt = $conn->prepare('UPDATE schedule SET show_id=? WHERE schedule_id=?');
        $stmt->bind_param('ii', $show_id, $sid);
    } else {
        $stmt = $conn->prepare('INSERT INTO schedule (channel_id, show_id, schedule_dow, schedule_hour, schedule_min) VALUES (?,?,?,?,?)');
        $stmt->bind_param('iiiii', $chan, $show_id, $dow, $hour, $min);
    }
    $stmt->execute();
    $stmt->close();
    $message = 'Schedule saved.';
}

if ($action === 'delete') {
    $sid = (int)($_POST['schedule_id'] ?? 0);
    $stmt = $conn->prepare('DELETE FROM schedule WHERE schedule_id=?');
    $stmt->bind_param('i', $sid);
    $stmt->execute();
    $stmt->close();
    $message = 'Slot cleared.';
}

if ($action === 'randomize') {
    $chan = (int)($_POST['channel_id'] ?? $channel_id);

    // Fetch all show IDs
    $all_show_ids = [];
    $r2 = $conn->query('SELECT show_id FROM shows');
    if ($r2) { while ($row2 = $r2->fetch_row()) $all_show_ids[] = (int)$row2[0]; $r2->free(); }

    if (!$all_show_ids) {
        $message = 'No shows in database to randomize from.';
    } else {
        // Fetch all existing schedule IDs for this channel
        $stmt = $conn->prepare('SELECT schedule_id FROM schedule WHERE channel_id=?');
        $stmt->bind_param('i', $chan);
        $stmt->execute();
        $res2 = $stmt->get_result();
        $slot_ids = [];
        while ($row2 = $res2->fetch_row()) $slot_ids[] = (int)$row2[0];
        $stmt->close();

        if ($slot_ids) {
            // Update existing slots with random shows
            $stmt = $conn->prepare('UPDATE schedule SET show_id=? WHERE schedule_id=?');
            foreach ($slot_ids as $sid) {
                $rand_show = $all_show_ids[array_rand($all_show_ids)];
                $stmt->bind_param('ii', $rand_show, $sid);
                $stmt->execute();
            }
            $stmt->close();
        } else {
            // No slots yet — insert all 336
            $stmt = $conn->prepare(
                'INSERT INTO schedule (channel_id, show_id, schedule_dow, schedule_hour, schedule_min)
                 VALUES (?,?,?,?,?)'
            );
            foreach (range(0, 6) as $dow) {
                foreach (range(0, 23) as $hour) {
                    foreach ([0, 30] as $min) {
                        $rand_show = $all_show_ids[array_rand($all_show_ids)];
                        $stmt->bind_param('iiiii', $chan, $rand_show, $dow, $hour, $min);
                        $stmt->execute();
                    }
                }
            }
            $stmt->close();
        }
        $message = 'Schedule randomized from ' . count($all_show_ids) . ' show(s).';
    }
}

// ── Fetch schedule for this channel ──────────────────────────────────────────
$schedule = [];
if ($channel_id) {
    $stmt = $conn->prepare(
        'SELECT s.schedule_id, s.schedule_dow, s.schedule_hour, s.schedule_min, s.show_id, sh.show_title
           FROM schedule s
           LEFT JOIN shows sh ON s.show_id = sh.show_id
          WHERE s.channel_id = ?'
    );
    $stmt->bind_param('i', $channel_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $schedule[$row['schedule_dow']][$row['schedule_hour']][$row['schedule_min']] = $row;
    }
    $stmt->close();
}

// ── Shows for dropdown ────────────────────────────────────────────────────────
$shows = [];
$r = $conn->query('SELECT show_id, show_title FROM shows ORDER BY show_title');
if ($r) { while ($row = $r->fetch_assoc()) $shows[] = $row; $r->free(); }

$conn->close();
require_once 'includes/header.php';
?>

<div class="page-header">
    <h1>Schedule<?= $channel_name ? ' — ' . htmlspecialchars($channel_name) : '' ?></h1>
    <?php if ($channels): ?>
    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
        <form method="GET" style="display:flex;align-items:center;gap:10px">
            <label style="font-size:13px;color:var(--text-2);margin:0">Channel:</label>
            <select name="ch" onchange="this.form.submit()" style="width:220px">
                <option value="">— Select a channel —</option>
                <?php foreach ($channels as $ch): ?>
                <option value="<?= $ch['channel_id'] ?>" <?= $ch['channel_id']===$channel_id ? 'selected':'' ?>>
                    CH <?= $ch['channel_id'] ?> — <?= htmlspecialchars($ch['channel_name']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </form>
        <?php if ($channel_id): ?>
        <button class="btn btn-secondary btn-sm" onclick="openModal('modal-randomize')">🔀 Randomize</button>
        <?php endif; ?>
        <a href="channels.php" class="btn btn-secondary btn-sm">Manage channels</a>
    </div>
    <?php else: ?>
    <a href="channels.php" class="btn btn-primary btn-sm">+ Add a channel first</a>
    <?php endif; ?>
</div>

<?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>

<?php if (!$channel_id): ?>
<div class="card">
    <div class="card-body" style="text-align:center;padding:40px;color:var(--text-2)">
        No channels exist yet. <a href="channels.php">Add a channel</a> to get started.
    </div>
</div>
<?php else: ?>

<div class="card" style="overflow-x:auto">
    <div class="card-header">
        <span class="card-title">CH <?= $channel_id ?><?= $channel_name ? ' — ' . htmlspecialchars($channel_name) : '' ?> — Weekly grid</span>
        <span class="text-sm text-muted">Click any slot to assign a show</span>
    </div>

    <table style="min-width:900px">
        <thead>
            <tr>
                <th style="width:80px">Time</th>
                <?php foreach ($days as $d): ?>
                <th><?= $d ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($hours as $h): foreach ($mins as $m): ?>
            <tr>
                <td class="td-mono" style="font-size:12px;white-space:nowrap">
                    <?= sprintf('%02d:%02d', $h, $m) ?>
                </td>
                <?php foreach (array_keys($days) as $dow): ?>
                    <?php $slot = $schedule[$dow][$h][$m] ?? null; ?>
                    <td style="padding:4px 6px;cursor:pointer"
                        onclick="openSlot(<?= $dow ?>, <?= $h ?>, <?= $m ?>, <?= $channel_id ?>, <?= $slot ? $slot['schedule_id'] : 0 ?>, <?= $slot ? $slot['show_id'] : 0 ?>)"
                        title="<?= $slot ? htmlspecialchars($slot['show_title']) : 'Empty — click to assign' ?>">
                        <?php if ($slot): ?>
                            <span class="badge badge-blue" style="font-size:10px;white-space:nowrap;max-width:110px;overflow:hidden;text-overflow:ellipsis;display:inline-block">
                                <?= htmlspecialchars($slot['show_title']) ?>
                            </span>
                        <?php else: ?>
                            <span style="color:var(--border);font-size:18px;display:block;text-align:center;line-height:1">+</span>
                        <?php endif; ?>
                    </td>
                <?php endforeach; ?>
            </tr>
        <?php endforeach; endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Randomize modal -->
<div class="modal-overlay" id="modal-randomize">
    <div class="modal" style="max-width:400px">
        <div class="modal-header">
            <span class="modal-title">Randomize schedule</span>
            <button class="modal-close" onclick="closeModal('modal-randomize')">×</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="randomize">
            <input type="hidden" name="channel_id" value="<?= $channel_id ?>">
            <div class="modal-body">
                <p>This will reassign all 336 slots on <strong>CH <?= $channel_id ?><?= $channel_name ? ' — ' . htmlspecialchars($channel_name) : '' ?></strong> to randomly selected shows from the database.</p>
                <p class="text-muted text-sm mt-1">Existing slot assignments will be overwritten. This cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modal-randomize')">Cancel</button>
                <button type="submit" class="btn btn-primary">Randomize</button>
            </div>
        </form>
    </div>
</div>

<!-- Slot edit modal -->
<div class="modal-overlay" id="modal-slot">
    <div class="modal" style="max-width:400px">
        <div class="modal-header">
            <span class="modal-title" id="slot-modal-title">Assign show</span>
            <button class="modal-close" onclick="closeModal('modal-slot')">×</button>
        </div>
        <form method="POST" id="form-slot-save">
            <input type="hidden" name="action"        value="save">
            <input type="hidden" name="channel_id"    id="slot-channel_id">
            <input type="hidden" name="schedule_id"   id="slot-schedule_id">
            <input type="hidden" name="schedule_dow"  id="slot-dow">
            <input type="hidden" name="schedule_hour" id="slot-hour">
            <input type="hidden" name="schedule_min"  id="slot-min">
            <div class="modal-body">
                <div class="form-group">
                    <label>Show</label>
                    <select name="show_id" id="slot-show_id">
                        <option value="0">— Select a show —</option>
                        <?php foreach ($shows as $s): ?>
                        <option value="<?= $s['show_id'] ?>"><?= htmlspecialchars($s['show_title']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" id="slot-delete-btn"
                    style="margin-right:auto;display:none"
                    class="btn btn-danger btn-sm"
                    onclick="submitDelete()">Clear slot</button>
                <button type="button" class="btn btn-secondary" onclick="closeModal('modal-slot')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete form — outside slot form to avoid nesting -->
<form method="POST" id="form-slot-delete" style="display:none">
    <input type="hidden" name="action"      value="delete">
    <input type="hidden" name="channel_id"  id="slot-delete-channel_id">
    <input type="hidden" name="schedule_id" id="slot-delete-id">
</form>

<?php endif; ?>

<script>
const dayNames = <?= json_encode($days) ?>;

function openSlot(dow, hour, min, chanId, schedId, showId) {
    const pad = n => String(n).padStart(2,'0');
    document.getElementById('slot-modal-title').textContent =
        `${dayNames[dow]} ${pad(hour)}:${pad(min)}`;
    document.getElementById('slot-channel_id').value  = chanId;
    document.getElementById('slot-schedule_id').value = schedId;
    document.getElementById('slot-dow').value         = dow;
    document.getElementById('slot-hour').value        = hour;
    document.getElementById('slot-min').value         = min;
    document.getElementById('slot-show_id').value     = showId || 0;

    const deleteBtn = document.getElementById('slot-delete-btn');
    if (schedId) {
        deleteBtn.style.display = 'inline-flex';
        document.getElementById('slot-delete-id').value         = schedId;
        document.getElementById('slot-delete-channel_id').value = chanId;
    } else {
        deleteBtn.style.display = 'none';
    }
    openModal('modal-slot');
}

function submitDelete() {
    if (confirm('Clear this schedule slot?')) {
        document.getElementById('form-slot-delete').submit();
    }
}
</script>

<?php require_once 'includes/footer.php'; ?>
