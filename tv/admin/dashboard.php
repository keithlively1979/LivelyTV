<?php
require_once 'includes/auth.php';
require_once dirname(__DIR__) . '/dbconnect.php';

$page_title = 'Dashboard';
$active_nav = 'dashboard';

// Stat counts
$stats = [];
foreach ([
    'shows'       => 'SELECT COUNT(*) FROM shows',
    'episodes'    => 'SELECT COUNT(*) FROM episodes',
    'movies'      => 'SELECT COUNT(*) FROM movies',
    'commercials' => 'SELECT COUNT(*) FROM commercials',
    'scheduled'   => 'SELECT COUNT(*) FROM schedule',
    'logged'      => 'SELECT COUNT(*) FROM playlog',
] as $key => $sql) {
    $r = $conn->query($sql);
    $stats[$key] = $r ? (int)$r->fetch_row()[0] : 0;
}

// Recent play log (last 10)
$recent = [];
$r = $conn->query('SELECT playlogdt, playlogchan, playlogtitle FROM playlog ORDER BY playlogdt DESC LIMIT 10');
if ($r) {
    while ($row = $r->fetch_assoc()) $recent[] = $row;
    $r->free();
}

$conn->close();

require_once 'includes/header.php';
?>

<div class="page-header">
    <h1>Dashboard</h1>
    <span class="text-muted text-sm">Welcome back, <?= htmlspecialchars($_SESSION['admin_user_name']) ?></span>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-label">Shows</div>
        <div class="stat-value"><?= $stats['shows'] ?></div>
        <div class="stat-sub"><a href="shows.php">Manage →</a></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Episodes</div>
        <div class="stat-value"><?= $stats['episodes'] ?></div>
        <div class="stat-sub"><a href="episodes.php">Manage →</a></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Movies</div>
        <div class="stat-value"><?= $stats['movies'] ?></div>
        <div class="stat-sub"><a href="movies.php">Manage →</a></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Commercials</div>
        <div class="stat-value"><?= $stats['commercials'] ?></div>
        <div class="stat-sub"><a href="commercials.php">Manage →</a></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Schedule slots</div>
        <div class="stat-value"><?= $stats['scheduled'] ?></div>
        <div class="stat-sub"><a href="schedule.php">Edit →</a></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Plays logged</div>
        <div class="stat-value"><?= $stats['logged'] ?></div>
        <div class="stat-sub"><a href="playlog.php">View →</a></div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <span class="card-title">Recent plays</span>
        <a href="playlog.php" class="btn btn-secondary btn-sm">View all</a>
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
                <?php if ($recent): ?>
                    <?php foreach ($recent as $row): ?>
                    <tr>
                        <td class="td-mono"><?= htmlspecialchars($row['playlogdt']) ?></td>
                        <td><span class="badge badge-blue"><?= htmlspecialchars($row['playlogchan']) ?></span></td>
                        <td><?= htmlspecialchars($row['playlogtitle']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="3" class="text-muted" style="text-align:center;padding:24px">No plays logged yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
