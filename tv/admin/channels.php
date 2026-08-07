<?php
require_once 'includes/auth.php';
require_once dirname(__DIR__) . '/dbconnect.php';

$page_title = 'Channels';
$active_nav = 'channels';
$message    = '';
$error      = '';

const CH_UPLOAD_DIR = '/var/www/lively.local/tv/admin/uploads/';
const CH_UPLOAD_URL = '/tv/admin/uploads/';
const MAX_LOGO_SIZE = 512 * 1024;

$action = $_POST['action'] ?? '';

if ($action === 'add' || $action === 'edit') {
    $channel_id         = (int)($_POST['channel_id']         ?? 0);
    $channel_name       = trim($_POST['channel_name']        ?? '');
    $channel_stream_url = trim($_POST['channel_stream_url']  ?? '');
    $root_dir           = trim($_POST['root_dir']            ?? '');
    $channel_visible    = isset($_POST['channel_visible']) ? 1 : 0;

    if (!$channel_name) {
        $error = 'Channel name is required.';
    } else {
        // Handle logo upload
        $logo_value = null;
        if (!empty($_FILES['channel_logo']['name'])) {
            $file   = $_FILES['channel_logo'];
            $ext    = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $finfo  = finfo_open(FILEINFO_MIME_TYPE);
            $mime   = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            if ($file['error'] !== UPLOAD_ERR_OK) {
                $error = 'Upload error.';
            } elseif (!in_array($ext, ['jpg','jpeg','png'])) {
                $error = 'Only JPG and PNG allowed.';
            } elseif (!in_array($mime, ['image/jpeg','image/png'])) {
                $error = 'Invalid file type.';
            } elseif ($file['size'] > MAX_LOGO_SIZE) {
                $error = 'Logo must be under 512 KB.';
            } else {
                if (!is_dir(CH_UPLOAD_DIR)) mkdir(CH_UPLOAD_DIR, 0755, true);
                $safe_name = 'ch_' . ($channel_id ?: 'new') . '_' . time() . '.' . $ext;
                $dest      = CH_UPLOAD_DIR . $safe_name;
                if (move_uploaded_file($file['tmp_name'], $dest)) {
                    $logo_value = CH_UPLOAD_URL . $safe_name;
                } else {
                    $error = 'Failed to save logo.';
                }
            }
        }

        if (!$error) {
            if ($action === 'add') {
                $logo_value    = $logo_value ?? '';
                $schedule_mode = $_POST['schedule_mode'] ?? 'random'; // default | random
                $default_show  = (int)($_POST['default_show_id'] ?? 0);

                $stmt = $conn->prepare(
                    'INSERT INTO channels (channel_name, channel_stream_url, channel_logo, root_dir)
                     VALUES (?,?,?,?)'
                );
                $stmt->bind_param('ssss', $channel_name, $channel_stream_url, $logo_value, $root_dir);
                $stmt->execute();
                $new_channel_id = $conn->insert_id;
                $stmt->close();

                // Populate schedule slots
                if (true) {
                    $all_show_ids = [];
                    if ($schedule_mode === 'random') {
                        $r2 = $conn->query('SELECT show_id FROM shows');
                        if ($r2) {
                            while ($row2 = $r2->fetch_row()) $all_show_ids[] = (int)$row2[0];
                            $r2->free();
                        }
                    }

                    if ($schedule_mode === 'default' && $default_show > 0) {
                        $slot_stmt = $conn->prepare(
                            'INSERT INTO schedule (channel_id, show_id, schedule_dow, schedule_hour, schedule_min)
                             VALUES (?,?,?,?,?)'
                        );
                        foreach (range(0, 6) as $dow) {
                            foreach (range(0, 23) as $hour) {
                                foreach ([0, 30] as $min) {
                                    $slot_stmt->bind_param('iiiii', $new_channel_id, $default_show, $dow, $hour, $min);
                                    $slot_stmt->execute();
                                }
                            }
                        }
                        $slot_stmt->close();
                        $message = "Channel \"$channel_name\" added with 336 slots set to default show.";

                    } elseif ($schedule_mode === 'random' && $all_show_ids) {
                        $slot_stmt = $conn->prepare(
                            'INSERT INTO schedule (channel_id, show_id, schedule_dow, schedule_hour, schedule_min)
                             VALUES (?,?,?,?,?)'
                        );
                        foreach (range(0, 6) as $dow) {
                            foreach (range(0, 23) as $hour) {
                                foreach ([0, 30] as $min) {
                                    $rand_show = $all_show_ids[array_rand($all_show_ids)];
                                    $slot_stmt->bind_param('iiiii', $new_channel_id, $rand_show, $dow, $hour, $min);
                                    $slot_stmt->execute();
                                }
                            }
                        }
                        $slot_stmt->close();
                        $message = "Channel \"$channel_name\" added with 336 slots randomized from " . count($all_show_ids) . " show(s).";
                    } else {
                        $message = "Channel \"$channel_name\" added. No shows available to populate schedule.";
                    }
                } else {
                    $message = "Channel \"$channel_name\" added.";
                }
            } else {
                if ($logo_value !== null) {
                    $stmt = $conn->prepare(
                        'UPDATE channels SET channel_name=?, channel_stream_url=?, channel_logo=?, root_dir=?, channel_visible=?
                         WHERE channel_id=?'
                    );
                    $stmt->bind_param('ssssii', $channel_name, $channel_stream_url, $logo_value, $root_dir, $channel_visible, $channel_id);
                } else {
                    $stmt = $conn->prepare(
                        'UPDATE channels SET channel_name=?, channel_stream_url=?, root_dir=?, channel_visible=?
                         WHERE channel_id=?'
                    );
                    $stmt->bind_param('sssii', $channel_name, $channel_stream_url, $root_dir, $channel_visible, $channel_id);
                }
                $stmt->execute();
                $stmt->close();
                $message = 'Channel updated.';
            }
        }
    }
}

if ($action === 'toggle_visible') {
    $channel_id = (int)($_POST['channel_id'] ?? 0);
    $stmt = $conn->prepare('UPDATE channels SET channel_visible = NOT channel_visible WHERE channel_id=?');
    $stmt->bind_param('i', $channel_id);
    $stmt->execute();
    $stmt->close();
}

if ($action === 'remove_logo') {
    $channel_id = (int)($_POST['channel_id'] ?? 0);
    $stmt = $conn->prepare('SELECT channel_logo FROM channels WHERE channel_id=? LIMIT 1');
    $stmt->bind_param('i', $channel_id);
    $stmt->execute();
    $stmt->bind_result($old_logo);
    $stmt->fetch();
    $stmt->close();
    if ($old_logo) {
        $old_file = CH_UPLOAD_DIR . basename($old_logo);
        if (file_exists($old_file)) unlink($old_file);
    }
    $stmt = $conn->prepare("UPDATE channels SET channel_logo='' WHERE channel_id=?");
    $stmt->bind_param('i', $channel_id);
    $stmt->execute();
    $stmt->close();
    $message = 'Logo removed.';
}

if ($action === 'delete') {
    $channel_id = (int)($_POST['channel_id'] ?? 0);
    $stmt = $conn->prepare('DELETE FROM channels WHERE channel_id=?');
    $stmt->bind_param('i', $channel_id);
    $stmt->execute();
    $stmt->close();
    $message = 'Channel deleted.';
}

// Fetch channels
$channels = [];
$r = $conn->query('SELECT * FROM channels ORDER BY channel_id');
if ($r) { while ($row = $r->fetch_assoc()) $channels[] = $row; $r->free(); }

// Fetch shows for schedule population dropdown
$shows = [];
$r = $conn->query('SELECT show_id, show_title FROM shows ORDER BY show_title');
if ($r) { while ($row = $r->fetch_assoc()) $shows[] = $row; $r->free(); }

$conn->close();
require_once 'includes/header.php';
?>

<div class="page-header">
    <h1>Channels</h1>
    <button class="btn btn-primary" onclick="openModal('modal-add')">+ Add channel</button>
</div>

<?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
<?php if ($error):   ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="card">
    <div class="card-header">
        <span class="card-title"><?= count($channels) ?> channel<?= count($channels) !== 1 ? 's':'' ?></span>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th style="width:50px">ID</th>
                    <th style="width:60px">Logo</th>
                    <th>Name</th>
                    <th>Stream URL</th>
                    <th>Root dir</th>
                    <th style="width:80px">Visible</th>
                    <th style="width:100px">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($channels): ?>
                <?php foreach ($channels as $c): ?>
                <tr>
                    <td class="td-mono"><?= $c['channel_id'] ?></td>
                    <td>
                        <?php if ($c['channel_logo']): ?>
                            <img src="<?= htmlspecialchars($c['channel_logo']) ?>"
                                 style="height:28px;width:auto;object-fit:contain" alt="">
                        <?php else: ?>
                            <span class="text-muted text-sm">—</span>
                        <?php endif; ?>
                    </td>
                    <td><strong><?= htmlspecialchars($c['channel_name']) ?></strong></td>
                    <td class="td-mono" style="max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"
                        title="<?= htmlspecialchars($c['channel_stream_url']) ?>">
                        <?= htmlspecialchars($c['channel_stream_url']) ?>
                    </td>
                    <td class="td-mono" style="max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                        <?= htmlspecialchars($c['root_dir'] ?: '—') ?>
                    </td>
                    <td>
                        <form method="POST" style="margin:0">
                            <input type="hidden" name="action" value="toggle_visible">
                            <input type="hidden" name="channel_id" value="<?= $c['channel_id'] ?>">
                            <button type="submit"
                                    class="badge <?= $c['channel_visible'] ? 'badge-green' : 'badge-red' ?>"
                                    style="border:none;cursor:pointer;font-family:var(--font-sans)"
                                    title="Click to toggle">
                                <?= $c['channel_visible'] ? 'Yes' : 'No' ?>
                            </button>
                        </form>
                    </td>
                    <td>
                        <div class="flex gap-2">
                            <button class="btn btn-secondary btn-sm"
                                data-c="<?= htmlspecialchars(json_encode($c), ENT_QUOTES) ?>"
                                onclick="editChannel(this)">Edit</button>
                            <button class="btn btn-danger btn-sm"
                                onclick="confirmDelete(<?= $c['channel_id'] ?>, '<?= htmlspecialchars(addslashes($c['channel_name'])) ?>')">Del</button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="7" class="text-muted" style="text-align:center;padding:24px">
                        No channels yet. Add one to get started.
                    </td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add modal -->
<div class="modal-overlay" id="modal-add">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title">Add channel</span>
            <button class="modal-close" onclick="closeModal('modal-add')">×</button>
        </div>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="add">
            <div class="modal-body">
                <div class="form-group">
                    <label>Channel name</label>
                    <input type="text" name="channel_name" required placeholder="Classic TV">
                </div>
                <div class="form-group">
                    <label>Stream URL <span class="text-muted text-sm">(HLS .m3u8)</span></label>
                    <input type="text" name="channel_stream_url"
                           placeholder="http://tv.lively.local/media/channel01/stream.m3u8">
                </div>
                <div class="form-group">
                    <label>Root directory <span class="text-muted text-sm">(optional)</span></label>
                    <input type="text" name="root_dir" placeholder="/media/channel01">
                </div>
                <div class="form-group">
                    <label>Logo <span class="text-muted text-sm">(JPG or PNG, max 512 KB)</span></label>
                    <input type="file" name="channel_logo" accept=".jpg,.jpeg,.png"
                           style="padding:6px 10px">
                </div>

                <div style="border-top:1px solid var(--border);margin:16px 0 12px"></div>
                <div class="form-group">
                    <label>Schedule population</label>
                    <select name="schedule_mode" id="add-schedule_mode"
                            onchange="updateScheduleMode(this.value)" style="margin-bottom:10px">
                        <option value="random" selected>Randomize slots from all shows in database</option>
                        <option value="default">Fill all slots with a default show</option>
                    </select>

                    <div id="add-default-show-wrap" style="display:none">
                        <label>Default show</label>
                        <select name="default_show_id">
                            <option value="0">— Select a show —</option>
                            <?php foreach ($shows as $s): ?>
                            <option value="<?= $s['show_id'] ?>"><?= htmlspecialchars($s['show_title']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (!$shows): ?>
                        <div class="text-muted text-sm mt-1">No shows in database yet.</div>
                        <?php endif; ?>
                    </div>

                    <div id="add-random-info" style="display:block" class="text-muted text-sm">
                        <?= count($shows) ?> show<?= count($shows) !== 1 ? 's' : '' ?> available.
                        All 336 slots will be assigned randomly.
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modal-add')">Cancel</button>
                <button type="submit" class="btn btn-primary">Add channel</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit modal -->
<div class="modal-overlay" id="modal-edit">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title">Edit channel</span>
            <button class="modal-close" onclick="closeModal('modal-edit')">×</button>
        </div>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="channel_id" id="edit-channel_id">
            <div class="modal-body">
                <div class="form-group">
                    <label>Channel name</label>
                    <input type="text" name="channel_name" id="edit-channel_name" required>
                </div>
                <div class="form-group">
                    <label>Stream URL</label>
                    <input type="text" name="channel_stream_url" id="edit-channel_stream_url">
                </div>
                <div class="form-group">
                    <label>Root directory</label>
                    <input type="text" name="root_dir" id="edit-root_dir">
                </div>
                <div class="form-group" style="display:flex;align-items:center;gap:10px">
                    <input type="checkbox" name="channel_visible" id="edit-channel_visible"
                           style="width:18px;height:18px;cursor:pointer">
                    <label for="edit-channel_visible" style="margin:0;cursor:pointer">
                        Visible in frontend player
                    </label>
                </div>
                <div class="form-group">
                    <label>Logo <span class="text-muted text-sm">(leave blank to keep existing)</span></label>
                    <input type="file" name="channel_logo" accept=".jpg,.jpeg,.png"
                           style="padding:6px 10px">
                    <div id="edit-logo-preview" style="margin-top:10px;display:none;align-items:center;gap:12px">
                        <img id="edit-logo-img" src="" alt="Current logo"
                             style="max-height:36px;max-width:140px;object-fit:contain;
                                    border:1px solid var(--border);border-radius:var(--radius-sm);padding:4px">
                        <button type="button" class="btn btn-danger btn-sm"
                                onclick="submitRemoveLogo()">Remove</button>
                    </div>
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
    <div class="modal" style="max-width:380px">
        <div class="modal-header">
            <span class="modal-title">Delete channel</span>
            <button class="modal-close" onclick="closeModal('modal-delete')">×</button>
        </div>
        <div class="modal-body">
            <p>Delete <strong id="delete-channel-name"></strong>?</p>
            <p class="text-muted text-sm mt-1">This will not remove any schedule entries.</p>
        </div>
        <div class="modal-footer">
            <form method="POST">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="channel_id" id="delete-channel_id">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modal-delete')">Cancel</button>
                <button type="submit" class="btn btn-danger">Delete</button>
            </form>
        </div>
    </div>
</div>

<!-- Remove logo form — outside all modals to avoid nesting -->
<form method="POST" id="form-remove-logo" style="display:none">
    <input type="hidden" name="action"     value="remove_logo">
    <input type="hidden" name="channel_id" id="remove-logo-channel_id">
</form>

<script>
function updateScheduleMode(val) {
    document.getElementById('add-default-show-wrap').style.display = val === 'default' ? 'block' : 'none';
    document.getElementById('add-random-info').style.display       = val === 'random'  ? 'block' : 'none';
}
function editChannel(btn) {
    const c = JSON.parse(btn.dataset.c);
    document.getElementById('edit-channel_id').value         = c.channel_id;
    document.getElementById('edit-channel_name').value       = c.channel_name        || '';
    document.getElementById('edit-channel_stream_url').value = c.channel_stream_url  || '';
    document.getElementById('edit-root_dir').value           = c.root_dir            || '';
    document.getElementById('edit-channel_visible').checked  = c.channel_visible == 1;
    document.getElementById('remove-logo-channel_id').value  = c.channel_id;

    const preview = document.getElementById('edit-logo-preview');
    const img     = document.getElementById('edit-logo-img');
    if (c.channel_logo) {
        img.src               = c.channel_logo;
        preview.style.display = 'flex';
    } else {
        preview.style.display = 'none';
    }
    openModal('modal-edit');
}
function submitRemoveLogo() {
    if (confirm('Remove the channel logo?')) {
        document.getElementById('form-remove-logo').submit();
    }
}
function confirmDelete(id, name) {
    document.getElementById('delete-channel_id').value         = id;
    document.getElementById('delete-channel-name').textContent = name;
    openModal('modal-delete');
}
</script>

<?php require_once 'includes/footer.php'; ?>
