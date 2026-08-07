<?php
require_once 'includes/auth.php';
require_once dirname(__DIR__) . '/dbconnect.php';

$page_title = 'Movies';
$active_nav = 'movies';
$message = '';
$error   = '';

$action = $_POST['action'] ?? '';

if ($action === 'add' || $action === 'edit') {
    $movie_id       = (int)($_POST['movie_id']       ?? 0);
    $movie_title    = trim($_POST['movie_title']     ?? '');
    $movie_file     = trim($_POST['movie_file']      ?? '');
    $movie_year     = (int)($_POST['movie_year']     ?? 0);
    $movie_genre    = trim($_POST['movie_genre']     ?? '');
    $movie_rating   = trim($_POST['movie_rating']    ?? '');
    $movie_summary  = trim($_POST['movie_summary']   ?? '');
    $movie_trailer  = isset($_POST['movie_trailer']) ? 1 : 0;
    $dur_hours      = (int)($_POST['dur_hours']      ?? 0);
    $dur_minutes    = (int)($_POST['dur_minutes']    ?? 0);
    $movie_duration = (($dur_hours * 3600) + ($dur_minutes * 60)) * 1000;

    if (!$movie_title || !$movie_file) {
        $error = 'Title and file path are required.';
    } else {
        if ($action === 'add') {
            $stmt = $conn->prepare(
                'INSERT INTO movies (movie_title, movie_file, movie_year, movie_genre, movie_rating, movie_summary, movie_duration, movie_trailer)
                 VALUES (?,?,?,?,?,?,?,?)'
            );
            // s=title, s=file, i=year, s=genre, s=rating, s=summary, i=duration, i=trailer
            $stmt->bind_param('ssisssii', $movie_title, $movie_file, $movie_year, $movie_genre, $movie_rating, $movie_summary, $movie_duration, $movie_trailer);
            $stmt->execute();
            $stmt->close();
            $message = "Movie \"$movie_title\" added.";
        } else {
            $stmt = $conn->prepare(
                'UPDATE movies SET movie_title=?, movie_file=?, movie_year=?, movie_genre=?, movie_rating=?, movie_summary=?, movie_duration=?, movie_trailer=?
                 WHERE movie_id=?'
            );
            // s=title, s=file, i=year, s=genre, s=rating, s=summary, i=duration, i=trailer, i=id
            $stmt->bind_param('ssisssiii', $movie_title, $movie_file, $movie_year, $movie_genre, $movie_rating, $movie_summary, $movie_duration, $movie_trailer, $movie_id);
            $stmt->execute();
            $stmt->close();
            $message = 'Movie updated.';
        }
    }
}

if ($action === 'delete') {
    $movie_id = (int)($_POST['movie_id'] ?? 0);
    $stmt = $conn->prepare('DELETE FROM movies WHERE movie_id=?');
    $stmt->bind_param('i', $movie_id);
    $stmt->execute();
    $stmt->close();
    $message = 'Movie deleted.';
}

// ── Filters + pagination ──────────────────────────────────────────────────────
$search = trim($_GET['q']      ?? '');
$genre  = trim($_GET['genre']  ?? '');
$rating = trim($_GET['rating'] ?? '');
$year1  = (int)($_GET['y1']    ?? 0);
$year2  = (int)($_GET['y2']    ?? 0);
$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 50;
$offset = ($page - 1) * $limit;

$where  = ['1=1'];
$params = [];
$types  = '';

if ($search) { $where[] = 'movie_title LIKE ?'; $params[] = '%'.$search.'%'; $types .= 's'; }
if ($genre)  { $where[] = 'movie_genre LIKE ?';  $params[] = '%'.$genre.'%';  $types .= 's'; }
if ($rating) { $where[] = 'movie_rating = ?';     $params[] = $rating;         $types .= 's'; }
if ($year1)  { $where[] = 'movie_year >= ?';      $params[] = $year1;          $types .= 'i'; }
if ($year2)  { $where[] = 'movie_year <= ?';      $params[] = $year2;          $types .= 'i'; }

$where_sql = implode(' AND ', $where);

$count_stmt = $conn->prepare("SELECT COUNT(*) FROM movies WHERE $where_sql");
if ($params) $count_stmt->bind_param($types, ...$params);
$count_stmt->execute();
$count_stmt->bind_result($total);
$count_stmt->fetch();
$count_stmt->close();
$total_pages = (int)ceil($total / $limit);

$stmt = $conn->prepare("SELECT * FROM movies WHERE $where_sql ORDER BY movie_title LIMIT ? OFFSET ?");
$all_params   = array_merge($params, [$limit, $offset]);
$all_types    = $types . 'ii';
$stmt->bind_param($all_types, ...$all_params);
$stmt->execute();
$movies = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Genre list for filter dropdown
$genres = [];
$r = $conn->query('SELECT DISTINCT movie_genre FROM movies WHERE movie_genre != "" ORDER BY movie_genre');
if ($r) { while ($row = $r->fetch_row()) $genres[] = $row[0]; $r->free(); }

$conn->close();

// ── Duration display helper (value stored in milliseconds) ───────────────────
function format_duration(int $ms): string {
    if (!$ms) return '—';
    $total_secs = intdiv($ms, 1000);
    $h = intdiv($total_secs, 3600);
    $m = intdiv($total_secs % 3600, 60);
    if ($h && $m) return "{$h}h {$m}m";
    if ($h)       return "{$h}h";
    return "{$m}m";
}

require_once 'includes/header.php';
?>

<div class="page-header">
    <h1>Movies</h1>
    <button class="btn btn-primary" onclick="openModal('modal-add')">+ Add movie</button>
</div>

<?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
<?php if ($error):   ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<form method="GET">
<div class="filters-bar">
    <div class="form-group">
        <label>Search</label>
        <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Title…" style="width:200px">
    </div>
    <div class="form-group">
        <label>Genre</label>
        <select name="genre" style="width:150px">
            <option value="">All genres</option>
            <?php foreach ($genres as $g): ?>
            <option value="<?= htmlspecialchars($g) ?>" <?= $genre===$g?'selected':'' ?>><?= htmlspecialchars($g) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-group">
        <label>Rating</label>
        <select name="rating" style="width:100px">
            <option value="">Any</option>
            <?php foreach (['G','PG','PG-13','R','NR'] as $rt): ?>
            <option value="<?= $rt ?>" <?= $rating===$rt?'selected':'' ?>><?= $rt ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-group">
        <label>Year from</label>
        <input type="number" name="y1" value="<?= $year1 ?: '' ?>" style="width:90px" placeholder="1980">
    </div>
    <div class="form-group">
        <label>Year to</label>
        <input type="number" name="y2" value="<?= $year2 ?: '' ?>" style="width:90px" placeholder="2024">
    </div>
    <button type="submit" class="btn btn-primary">Filter</button>
    <a href="movies.php" class="btn btn-secondary">Reset</a>
</div>
</form>

<div class="card">
    <div class="card-header">
        <span class="card-title"><?= $total ?> movie<?= $total !== 1 ? 's':'' ?></span>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Year</th>
                    <th>Genre</th>
                    <th>Rating</th>
                    <th style="width:70px">Duration</th>
                    <th style="width:70px">Trailer</th>
                    <th>File</th>
                    <th style="width:100px">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($movies): ?>
                <?php foreach ($movies as $m): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($m['movie_title']) ?></strong></td>
                    <td><?= (int)$m['movie_year'] ?></td>
                    <td><?= htmlspecialchars($m['movie_genre']) ?></td>
                    <td>
                        <?php
                        $badge = match($m['movie_rating']) {
                            'R'     => 'badge-red',
                            'PG-13' => 'badge-amber',
                            'PG'    => 'badge-blue',
                            'G'     => 'badge-green',
                            default => 'badge-blue',
                        };
                        ?>
                        <span class="badge <?= $badge ?>"><?= htmlspecialchars($m['movie_rating']) ?></span>
                    </td>
                    <td class="td-mono"><?= format_duration((int)$m['movie_duration']) ?></td>
                    <td>
                        <?php if ($m['movie_trailer']): ?>
                            <span class="badge badge-green">Yes</span>
                        <?php else: ?>
                            <span class="badge" style="background:var(--surface-2);color:var(--text-3)">No</span>
                        <?php endif; ?>
                    </td>
                    <td class="td-mono" style="max-width:240px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"
                        title="<?= htmlspecialchars($m['movie_file']) ?>"><?= htmlspecialchars($m['movie_file']) ?></td>
                    <td>
                        <div class="flex gap-2">
                            <button class="btn btn-secondary btn-sm"
                                data-m="<?= htmlspecialchars(json_encode($m), ENT_QUOTES) ?>"
                                onclick="editMovie(this)">Edit</button>
                            <button class="btn btn-danger btn-sm"
                                onclick="confirmDelete(<?= $m['movie_id'] ?>, '<?= htmlspecialchars(addslashes($m['movie_title'])) ?>')">Del</button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="8" class="text-muted" style="text-align:center;padding:24px">No movies match the current filters.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($total_pages > 1): ?>
    <div class="pagination">
        <?php for ($p = 1; $p <= $total_pages; $p++):
            $qs = http_build_query(array_merge($_GET, ['page' => $p]));
        ?>
            <?php if ($p === $page): ?>
                <span class="current"><?= $p ?></span>
            <?php else: ?>
                <a href="?<?= $qs ?>"><?= $p ?></a>
            <?php endif; ?>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Add modal -->
<div class="modal-overlay" id="modal-add">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title">Add movie</span>
            <button class="modal-close" onclick="closeModal('modal-add')">×</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="add">
            <div class="modal-body">
                <div class="form-group">
                    <label>Title</label>
                    <input type="text" name="movie_title" required>
                </div>
                <div class="form-row-3">
                    <div class="form-group">
                        <label>Year</label>
                        <input type="number" name="movie_year" min="1900" max="2099">
                    </div>
                    <div class="form-group">
                        <label>Genre</label>
                        <input type="text" name="movie_genre" placeholder="Comedy">
                    </div>
                    <div class="form-group">
                        <label>Rating</label>
                        <select name="movie_rating">
                            <option value="NR">NR</option>
                            <option value="G">G</option>
                            <option value="PG">PG</option>
                            <option value="PG-13">PG-13</option>
                            <option value="R">R</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label>Duration</label>
                    <div style="display:flex;align-items:center;gap:8px">
                        <input type="number" name="dur_hours" value="0" min="0" max="23" style="width:70px">
                        <span style="color:var(--text-2);font-size:13px">hr</span>
                        <input type="number" name="dur_minutes" value="0" min="0" max="59" style="width:70px">
                        <span style="color:var(--text-2);font-size:13px">min</span>
                    </div>
                </div>
                <div class="form-group">
                    <label>Summary</label>
                    <textarea name="movie_summary" rows="3"></textarea>
                </div>
                <div class="form-group" style="display:flex;align-items:center;gap:10px">
                    <input type="checkbox" name="movie_trailer" id="add-movie_trailer"
                           style="width:18px;height:18px;cursor:pointer">
                    <label for="add-movie_trailer" style="margin:0;cursor:pointer">Trailer available</label>
                </div>
                <div class="form-group">
                    <label>File path</label>
                    <input type="text" name="movie_file" required placeholder="_BASEDIR_/Movie Title (1985)/movie.mkv">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modal-add')">Cancel</button>
                <button type="submit" class="btn btn-primary">Add movie</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit modal -->
<div class="modal-overlay" id="modal-edit">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title">Edit movie</span>
            <button class="modal-close" onclick="closeModal('modal-edit')">×</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="movie_id" id="edit-movie_id">
            <div class="modal-body">
                <div class="form-group">
                    <label>Title</label>
                    <input type="text" name="movie_title" id="edit-movie_title" required>
                </div>
                <div class="form-row-3">
                    <div class="form-group">
                        <label>Year</label>
                        <input type="number" name="movie_year" id="edit-movie_year" min="1900" max="2099">
                    </div>
                    <div class="form-group">
                        <label>Genre</label>
                        <input type="text" name="movie_genre" id="edit-movie_genre">
                    </div>
                    <div class="form-group">
                        <label>Rating</label>
                        <select name="movie_rating" id="edit-movie_rating">
                            <option value="NR">NR</option>
                            <option value="G">G</option>
                            <option value="PG">PG</option>
                            <option value="PG-13">PG-13</option>
                            <option value="R">R</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label>Duration</label>
                    <div style="display:flex;align-items:center;gap:8px">
                        <input type="number" name="dur_hours" id="edit-dur_hours" value="0" min="0" max="23" style="width:70px">
                        <span style="color:var(--text-2);font-size:13px">hr</span>
                        <input type="number" name="dur_minutes" id="edit-dur_minutes" value="0" min="0" max="59" style="width:70px">
                        <span style="color:var(--text-2);font-size:13px">min</span>
                    </div>
                </div>
                <div class="form-group">
                    <label>Summary</label>
                    <textarea name="movie_summary" id="edit-movie_summary" rows="3"></textarea>
                </div>
                <div class="form-group" style="display:flex;align-items:center;gap:10px">
                    <input type="checkbox" name="movie_trailer" id="edit-movie_trailer"
                           style="width:18px;height:18px;cursor:pointer">
                    <label for="edit-movie_trailer" style="margin:0;cursor:pointer">Trailer available</label>
                </div>
                <div class="form-group">
                    <label>File path</label>
                    <input type="text" name="movie_file" id="edit-movie_file" required>
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
            <span class="modal-title">Delete movie</span>
            <button class="modal-close" onclick="closeModal('modal-delete')">×</button>
        </div>
        <div class="modal-body"><p>Delete <strong id="delete-movie-name"></strong>?</p></div>
        <div class="modal-footer">
            <form method="POST">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="movie_id" id="delete-movie_id">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modal-delete')">Cancel</button>
                <button type="submit" class="btn btn-danger">Delete</button>
            </form>
        </div>
    </div>
</div>

<script>
function editMovie(btn) {
    const m = JSON.parse(btn.dataset.m);
    const totalSecs = Math.floor((parseInt(m.movie_duration) || 0) / 1000);
    const hours     = Math.floor(totalSecs / 3600);
    const minutes   = Math.floor((totalSecs % 3600) / 60);

    document.getElementById('edit-movie_id').value       = m.movie_id;
    document.getElementById('edit-movie_title').value    = m.movie_title    || '';
    document.getElementById('edit-movie_year').value     = m.movie_year     || '';
    document.getElementById('edit-movie_genre').value    = m.movie_genre    || '';
    document.getElementById('edit-movie_rating').value   = m.movie_rating   || 'NR';
    document.getElementById('edit-dur_hours').value      = hours;
    document.getElementById('edit-dur_minutes').value    = minutes;
    document.getElementById('edit-movie_summary').value  = m.movie_summary  || '';
    document.getElementById('edit-movie_trailer').checked = m.movie_trailer == 1;
    document.getElementById('edit-movie_file').value     = m.movie_file     || '';
    openModal('modal-edit');
}
function confirmDelete(id, name) {
    document.getElementById('delete-movie_id').value = id;
    document.getElementById('delete-movie-name').textContent = name;
    openModal('modal-delete');
}
</script>

<?php require_once 'includes/footer.php'; ?>
