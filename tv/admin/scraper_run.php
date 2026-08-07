<?php
/**
 * scraper_run.php
 * Runs the appropriate scraper script and streams output to the browser.
 * Called via fetch() from scraper.php — not accessed directly.
 */

if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['admin_user_id'])) { http_response_code(403); exit; }

$type = $_GET['type'] ?? '';
if (!in_array($type, ['tv', 'movies'], true)) {
    http_response_code(400);
    echo 'Invalid type.';
    exit;
}

// Disable output buffering so lines stream to the browser immediately
while (ob_get_level()) ob_end_clean();

header('Content-Type: text/plain; charset=utf-8');
header('X-Accel-Buffering: no'); // tells nginx not to buffer
header('Cache-Control: no-cache');

// Flush headers
flush();

require_once dirname(__DIR__) . '/dbconnect.php';

if ($type === 'tv') {
    run_tv_scraper($conn);
} else {
    run_movie_scraper($conn);
}

$conn->close();

// ─── TV scraper ───────────────────────────────────────────────────────────────

function run_tv_scraper(mysqli $conn): void
{
    // Fetch all enabled TV paths from settings
    $paths = [];
    $r = $conn->query("SELECT path_dir, path_label FROM content_paths WHERE path_type='tv' AND path_enabled=1");
    if ($r) { while ($row = $r->fetch_assoc()) $paths[] = $row; $r->free(); }

    if (!$paths) {
        out("No enabled TV paths configured.");
        out("Add content paths in Settings → Content paths.");
        return;
    }

    foreach ($paths as $path_row) {
        $base_path = rtrim($path_row['path_dir'], '/');
        $label     = $path_row['path_label'] ?: $base_path;

        out("TV Scraper — $label");
        out("Scanning: $base_path");
        out(str_repeat('─', 50));

        if (!is_dir($base_path)) {
            out("ERROR: Path not found or not accessible: $base_path");
            out("Check the path is correct and the NAS is mounted.");
            out('');
            continue;
        }

        $shows = array_filter(scandir($base_path), fn($d) =>
            !in_array($d, ['.','..']) && is_dir("$base_path/$d")
        );

        $added_shows = 0;
        $added_eps   = 0;

        foreach ($shows as $show_dir) {
            $show_path = "$base_path/$show_dir";

            $stmt = $conn->prepare('SELECT show_id, show_total_episodes FROM shows WHERE show_title=? LIMIT 1');
            $stmt->bind_param('s', $show_dir);
            $stmt->execute();
            $stmt->bind_result($show_id, $total_eps);
            $exists = $stmt->fetch();
            $stmt->close();

            if (!$exists) {
                $stmt = $conn->prepare('INSERT INTO shows (show_title, show_basedir, show_desc, show_bumperout, show_bumperin, show_total_episodes, show_lastplayed) VALUES (?,?,0,"","",0,0)');
                $show_basedir = $base_path . '/' . $show_dir;
                $stmt->bind_param('ss', $show_dir, $show_basedir);
                $stmt->execute();
                $show_id = $conn->insert_id;
                $stmt->close();
                out("+ Show added: $show_dir (ID: $show_id)");
                $added_shows++;
            } else {
                out("  Show exists: $show_dir (ID: $show_id)");
            }

            $episode_index = 1;
            $seasons = array_filter(scandir($show_path), fn($d) =>
                !in_array($d, ['.','..']) && is_dir("$show_path/$d") && str_starts_with($d, 'Season')
            );
            natsort($seasons);

            $ep_count = 0;
            foreach ($seasons as $season_dir) {
                $season_path = "$show_path/$season_dir";
                $files = array_filter(scandir($season_path), fn($f) =>
                    in_array(strtolower(pathinfo($f, PATHINFO_EXTENSION)), ['mkv','mp4','avi','m4v'])
                );
                natsort($files);

                foreach ($files as $file) {
                    $ep_file = "_BASEDIR_/$season_dir/$file";

                    $stmt = $conn->prepare('SELECT episode_id FROM episodes WHERE show_id=? AND episode_file=? LIMIT 1');
                    $stmt->bind_param('is', $show_id, $ep_file);
                    $stmt->execute();
                    $stmt->bind_result($ep_id);
                    $ep_exists = $stmt->fetch();
                    $stmt->close();

                    if (!$ep_exists) {
                        $ep_title = pathinfo($file, PATHINFO_FILENAME);
                        $stmt = $conn->prepare('INSERT INTO episodes (show_id, episode_file, episode_index, episode_title, episode_summary, episode_duration) VALUES (?,?,?,?,?,0)');
                        $stmt->bind_param('isiss', $show_id, $ep_file, $episode_index, $ep_title, $ep_title);
                        $stmt->execute();
                        $stmt->close();
                        out("    + Episode: $season_dir/$file (index $episode_index)");
                        $added_eps++;
                    }
                    $episode_index++;
                    $ep_count++;
                }
            }

            $stmt = $conn->prepare('UPDATE shows SET show_total_episodes=? WHERE show_id=?');
            $stmt->bind_param('ii', $ep_count, $show_id);
            $stmt->execute();
            $stmt->close();
        }

        out(str_repeat('─', 50));
        out("Done. Added $added_shows show(s), $added_eps episode(s).");
        out('');
    }
}

// ─── Movie scraper ────────────────────────────────────────────────────────────

function run_movie_scraper(mysqli $conn): void
{
    // Fetch all enabled movie paths from settings
    $paths = [];
    $r = $conn->query("SELECT path_dir, path_label FROM content_paths WHERE path_type='movies' AND path_enabled=1");
    if ($r) { while ($row = $r->fetch_assoc()) $paths[] = $row; $r->free(); }

    if (!$paths) {
        out("No enabled movie paths configured.");
        out("Add content paths in Settings → Content paths.");
        return;
    }

    foreach ($paths as $path_row) {
        $base_path = rtrim($path_row['path_dir'], '/');
        $label     = $path_row['path_label'] ?: $base_path;

        out("Movie Scraper — $label");
        out("Scanning: $base_path");
        out(str_repeat('─', 50));

        if (!is_dir($base_path)) {
            out("ERROR: Path not found or not accessible: $base_path");
            out("Check the path is correct and the NAS is mounted.");
            out('');
            continue;
        }

        $dirs = array_filter(scandir($base_path), fn($d) =>
            !in_array($d, ['.','..']) && is_dir("$base_path/$d")
        );

        $added = 0;

        foreach ($dirs as $movie_dir) {
            $movie_path = "$base_path/$movie_dir";

            $year  = 0;
            $title = $movie_dir;
            if (preg_match('/^(.+?)\s*\((\d{4})\)$/', $movie_dir, $m)) {
                $title = trim($m[1]);
                $year  = (int)$m[2];
            }

            $video_file = null;
            foreach (scandir($movie_path) as $f) {
                if (in_array(strtolower(pathinfo($f, PATHINFO_EXTENSION)), ['mkv','mp4','avi','m4v'])) {
                    $video_file = $f;
                    break;
                }
            }

            if (!$video_file) {
                out("  SKIP (no video): $movie_dir");
                continue;
            }

            $ep_file = "_BASEDIR_/$movie_dir/$video_file";

            $stmt = $conn->prepare('SELECT movie_id FROM movies WHERE movie_title=? LIMIT 1');
            $stmt->bind_param('s', $title);
            $stmt->execute();
            $stmt->bind_result($movie_id);
            $exists = $stmt->fetch();
            $stmt->close();

            if (!$exists) {
                $empty = '';
                $nr    = 'NR';
                $stmt  = $conn->prepare('INSERT INTO movies (movie_title, movie_file, movie_year, movie_genre, movie_rating, movie_summary, movie_duration) VALUES (?,?,?,?,?,?,0)');
                $stmt->bind_param('ssisss', $title, $ep_file, $year, $empty, $nr, $empty);
                $stmt->execute();
                $stmt->close();
                out("+ Movie added: $title ($year)");
                $added++;
            } else {
                out("  Exists: $title");
            }
        }

        out(str_repeat('─', 50));
        out("Done. Added $added new movie(s).");
        out('');
    }
}

// ─── Helper ───────────────────────────────────────────────────────────────────

function out(string $line): void
{
    echo $line . "\n";
    flush();
}
