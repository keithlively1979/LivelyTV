<?php
/**
 * api/channels.php
 * Returns JSON array of all channels with name, stream URL and logo.
 * Public endpoint — no auth required.
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once dirname(__DIR__) . '/dbconnect.php';

$channels = [];
$r = $conn->query(
    'SELECT channel_id, channel_name, channel_stream_url, channel_logo
       FROM channels
      WHERE channel_visible = 1
      ORDER BY channel_id'
);

if ($r) {
    while ($row = $r->fetch_assoc()) {
        $channels[] = [
            'id'         => (int)$row['channel_id'],
            'name'       => $row['channel_name'] ?: 'Channel ' . $row['channel_id'],
            'stream_url' => $row['channel_stream_url'],
            'logo'       => $row['channel_logo'],
        ];
    }
    $r->free();
}

$conn->close();
echo json_encode($channels);
