<?php
/**
 * api/now_playing.php
 * Returns JSON object of current title per channel_id.
 * Optionally filter by ?ch=1 for a single channel.
 * Public endpoint — no auth required.
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once dirname(__DIR__) . '/dbconnect.php';

$ch = isset($_GET['ch']) ? (int)$_GET['ch'] : null;

// Fetch most recent playlog entry per channel
if ($ch !== null) {
    $stmt = $conn->prepare(
        'SELECT playlogchanid, playlogtitle, playlogdt
           FROM playlog
          WHERE playlogchanid = ?
          ORDER BY playlogdt DESC
          LIMIT 1'
    );
    $stmt->bind_param('i', $ch);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    // One row per channel — most recent entry for each
    $result = [];
    $r = $conn->query(
        'SELECT p.playlogchanid, p.playlogtitle, p.playlogdt
           FROM playlog p
           INNER JOIN (
               SELECT playlogchanid, MAX(playlogdt) AS maxdt
                 FROM playlog
                WHERE playlogchanid > 0
                GROUP BY playlogchanid
           ) latest ON p.playlogchanid = latest.playlogchanid
                    AND p.playlogdt    = latest.maxdt
          ORDER BY p.playlogchanid'
    );
    if ($r) {
        while ($row = $r->fetch_assoc()) $result[] = $row;
        $r->free();
    }
}

$conn->close();

// Key by channel ID for easy JS lookup
$output = [];
foreach ($result as $row) {
    $output[(int)$row['playlogchanid']] = [
        'title' => $row['playlogtitle'],
        'since' => $row['playlogdt'],
    ];
}

echo json_encode($output);
