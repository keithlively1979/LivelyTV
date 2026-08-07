<?php
/**
 * playlist.php — unified playlist generator
 *
 * Query string parameters:
 *
 *   TV channel (schedule-driven):
 *     ?type=tv&ch=1
 *     ?type=tv&ch=2
 *
 *   Movie channel (random movie by genre/year):
 *     ?type=movie&genre=Comedy&y1=1985&y2=2024
 *
 * Replaces: channel01.php, channel02.php, movie.php
 *
 * VLC self-loop URL examples:
 *   http://tv.lively.local/tv/playlist.php?type=tv&ch=1
 *   http://tv.lively.local/tv/playlist.php?type=movie&genre=Comedy&y1=1985&y2=2024
 */

header('Content-Type: text/plain; charset=utf-8');

require 'dbconnect.php';

// ─── Constants ───────────────────────────────────────────────────────────────

const DEFAULT_BUMPERIN    = 'file:///media/Commercials/bumperin.mp4';
const DEFAULT_BUMPEROUT   = 'file:///media/Commercials/bumperout.mp4';
const COMMERCIAL_TOKEN    = '_COMMERCIAL_30';
const NAS_FILE_PREFIX     = 'FILE://LIVELY-NAS';
const NAS_LOCAL_PREFIX    = 'file:///media/plex';
const MOVIES_PATH         = 'file:///media/plex/Movies/';
const PREROLL             = 'file:///media/Commercials/preroll.mp4';
const TRAILERS_PATH       = 'file:///media/Commercials/Trailers/';
const RATING_PATH         = 'file:///media/Commercials/Rating/';
const WATERSHED_HOUR      = 21;   // R-rated movies allowed at or after this hour

// ─── Input validation ─────────────────────────────────────────────────────────

$type  = isset($_GET['type'])  ? strtolower(trim($_GET['type'])) : '';
$ch    = isset($_GET['ch'])    ? (int)$_GET['ch']                : 0;
$genre = isset($_GET['genre']) ? trim($_GET['genre'])            : 'Comedy';
$y1    = isset($_GET['y1'])    ? (int)$_GET['y1']                : 1985;
$y2    = isset($_GET['y2'])    ? (int)$_GET['y2']                : 2024;

if (!in_array($type, ['tv', 'movie'], true)) {
    http_response_code(400);
    echo "# Error: ?type= must be 'tv' or 'movie'";
    exit;
}

// ─── Route to handler ─────────────────────────────────────────────────────────

if ($type === 'tv') {
    output_tv_playlist($conn, $ch);
} else {
    output_movie_playlist($conn, $ch, $genre, $y1, $y2);
}

$conn->close();

// ─── TV playlist ──────────────────────────────────────────────────────────────

function output_tv_playlist(mysqli $conn, int $channel_id): void
{
    $e_file       = COMMERCIAL_TOKEN;
    $schedule_min = null;

    $minute = (int)date('i');
    if ($minute === 0) {
        $schedule_min = 0;
    } elseif ($minute === 30) {
        $schedule_min = 30;
    }

    if ($schedule_min !== null) {

        // Look up which show is scheduled for this slot
        $stmt = $conn->prepare(
            'SELECT s.show_id, sh.show_lastplayed, sh.show_total_episodes
               FROM schedule s
               INNER JOIN shows sh ON s.show_id = sh.show_id
              WHERE s.channel_id = ?
                AND s.schedule_dow = ?
                AND s.schedule_hour = ?
                AND s.schedule_min = ?
              LIMIT 1'
        );
        $dow  = (int)date('w');
        $hour = (int)date('H');
        $stmt->bind_param('iiii', $channel_id, $dow, $hour, $schedule_min);
        $stmt->execute();
        $stmt->bind_result($show_id, $show_lastplayed, $show_total_episodes);

        if ($stmt->fetch()) {
            if ($show_lastplayed >= $show_total_episodes) {
                $show_lastplayed = 0;
            }
            $show_play = $show_lastplayed + 1;
        } else {
            $show_id = null;
        }
        $stmt->close();

        if (isset($show_id)) {

            // Fetch the episode file, bumper settings, show title and episode title
            $stmt2 = $conn->prepare(
                'SELECT e.episode_file, e.episode_title,
                        sh.show_title, sh.show_basedir, sh.show_bumperout, sh.show_bumperin
                   FROM episodes e
                   INNER JOIN shows sh ON e.show_id = sh.show_id
                  WHERE e.show_id = ?
                    AND e.episode_index = ?
                  LIMIT 1'
            );
            $stmt2->bind_param('ii', $show_id, $show_play);
            $stmt2->execute();
            $stmt2->bind_result($episode_file, $episode_title, $show_title, $show_basedir, $show_bumperout, $show_bumperin);

            if ($stmt2->fetch()) {
                $e_file = $episode_file;

                // Resolve bumper placeholders
                $e_file = str_replace('_BUMPEROUT_', $show_bumperout ?: DEFAULT_BUMPEROUT, $e_file);
                $e_file = str_replace('_BUMPERIN_',  $show_bumperin  ?: DEFAULT_BUMPERIN,  $e_file);
                $e_file = str_replace('_BASEDIR_',   $show_basedir,                        $e_file);
                $e_file = str_replace('&apos;',      "'",                                  $e_file);
            }
            $stmt2->close();

            // Advance the play counter
            $stmt3 = $conn->prepare(
                'UPDATE shows SET show_lastplayed = ? WHERE show_id = ?'
            );
            $stmt3->bind_param('ii', $show_play, $show_id);
            $stmt3->execute();
            $stmt3->close();

            // Build log title: "Show Name — Episode Title"
            $log_title = $show_title ?? '';
            if (!empty($episode_title)) {
                $log_title .= ' — ' . $episode_title;
            }

            $chan_qs = http_build_query(['type' => 'tv', 'ch' => $channel_id]);
            write_playlog($conn, $chan_qs, $channel_id, $log_title);
        }
    }

    // Substitute any remaining commercial tokens with random commercials
    $e_file = resolve_commercials($conn, $e_file);

    // Resolve NAS path variants
    $e_file = str_replace(NAS_FILE_PREFIX, NAS_LOCAL_PREFIX, $e_file);
    $e_file = str_replace('/mnt/plex',     'file:///media/plex', $e_file);
    $e_file = str_replace('Season0',       'Season 0',           $e_file);

    $loop_url = self_url(['type' => 'tv', 'ch' => $channel_id]);

    echo "#EXTM3U\r\n" . $e_file . "\r\n" . $loop_url;
}

// ─── Movie playlist ───────────────────────────────────────────────────────────

function output_movie_playlist(mysqli $conn, int $channel_id, string $genre, int $y1, int $y2): void
{
    $after_watershed = (int)date('H') >= WATERSHED_HOUR;

    if ($after_watershed) {
        $stmt = $conn->prepare(
            "SELECT movie_file, movie_title, movie_rating
               FROM movies
              WHERE movie_genre LIKE ?
                AND movie_year BETWEEN ? AND ?
              ORDER BY RAND()
              LIMIT 1"
        );
    } else {
        $stmt = $conn->prepare(
            "SELECT movie_file, movie_title, movie_rating
               FROM movies
              WHERE movie_genre LIKE ?
                AND movie_year BETWEEN ? AND ?
                AND movie_rating <> 'R'
              ORDER BY RAND()
              LIMIT 1"
        );
    }

    $genre_like = '%' . $genre . '%';
    $stmt->bind_param('sii', $genre_like, $y1, $y2);
    $stmt->execute();
    $stmt->bind_result($movie_file, $movie_title, $movie_rating);

    if (!$stmt->fetch()) {
        http_response_code(404);
        echo '# No movies found matching the requested criteria';
        $stmt->close();
        return;
    }
    $stmt->close();

    // Resolve full movie path
    $movie_path = str_replace('_BASEDIR_', MOVIES_PATH, $movie_file);

    // ── Build playlist entries ────────────────────────────────────────────────

    $entries = [];

    // 1. Trailers — pick up to 4 random movies from the same genre/year range
    //    that have movie_trailer=1. Build the trailer filename from movie_file.
    //    Naming: <movie-basename>-trailer.<original-ext>
    $trailer_stmt = $conn->prepare(
        "SELECT movie_file
           FROM movies
          WHERE movie_genre LIKE ?
            AND movie_year BETWEEN ? AND ?
            AND movie_trailer = 1
          ORDER BY RAND()
          LIMIT 4"
    );
    $trailer_stmt->bind_param('sii', $genre_like, $y1, $y2);
    $trailer_stmt->execute();
    $trailer_rows = $trailer_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $trailer_stmt->close();

    foreach ($trailer_rows as $t) {
        $basename  = pathinfo($t['movie_file'], PATHINFO_FILENAME);
        $ext       = pathinfo($t['movie_file'], PATHINFO_EXTENSION) ?: 'mp4';
        $entries[] = TRAILERS_PATH . $basename . '-trailer.' . $ext;
    }

    // 2. Preroll
    $entries[] = PREROLL;

    // 3. MPAA rating bumper — only for G, PG, PG-13, R
    $valid_ratings = ['G', 'PG', 'PG-13', 'R'];
    $rating        = strtoupper(trim($movie_rating ?? ''));
    if (in_array($rating, $valid_ratings)) {
        $entries[] = RATING_PATH . $rating . '.mp4';
    }

    // 4. The movie itself
    $entries[] = $movie_path;

    // 5. Loop back to this channel
    $entries[] = self_url(['type' => 'movie', 'ch' => $channel_id, 'genre' => $genre, 'y1' => $y1, 'y2' => $y2]);

    // ── Log and output ────────────────────────────────────────────────────────

    $chan_qs = http_build_query(['type' => 'movie', 'ch' => $channel_id, 'genre' => $genre, 'y1' => $y1, 'y2' => $y2]);
    write_playlog($conn, $chan_qs, $channel_id, $movie_title);

    echo "#EXTM3U\r\n" . implode("\r\n", $entries);
}

// ─── Helpers ──────────────────────────────────────────────────────────────────

/**
 * Replace every _COMMERCIAL_30 token with a randomly selected commercial file.
 * Guards against an empty commercials table to prevent an infinite loop.
 */
function resolve_commercials(mysqli $conn, string $e_file): string
{
    if (!str_contains($e_file, COMMERCIAL_TOKEN)) {
        return $e_file;
    }

    $stmt = $conn->prepare(
        'SELECT commercial_file FROM commercials ORDER BY RAND() LIMIT 1'
    );

    $max_iterations = 20;
    $iterations     = 0;

    while (str_contains($e_file, COMMERCIAL_TOKEN)) {
        if (++$iterations > $max_iterations) {
            // Safety valve: replace remaining tokens with a safe fallback
            $e_file = str_replace(COMMERCIAL_TOKEN, DEFAULT_BUMPERIN, $e_file);
            break;
        }

        $stmt->execute();
        $stmt->bind_result($commercial_file);

        if (!$stmt->fetch()) {
            // No commercials in DB — replace all remaining tokens and stop
            $e_file = str_replace(COMMERCIAL_TOKEN, DEFAULT_BUMPERIN, $e_file);
            break;
        }
        $stmt->free_result();

        $e_file = substr_replace(
            $e_file,
            $commercial_file,
            strpos($e_file, COMMERCIAL_TOKEN),
            strlen(COMMERCIAL_TOKEN)
        );
    }

    $stmt->close();

    return $e_file;
}

/**
 * Build the self-referencing loop URL from the current request,
 * replacing only the supplied query string parameters.
 */
function self_url(array $params): string
{
    $base = 'http://' . $_SERVER['HTTP_HOST'] . strtok($_SERVER['REQUEST_URI'], '?');
    return $base . '?' . http_build_query($params);
}

/**
 * Insert a row into the playlog table.
 * $channel is the full query string, $channel_id is the numeric channel ID.
 */
function write_playlog(mysqli $conn, string $channel, int $channel_id, string $title): void
{
    $stmt = $conn->prepare(
        'INSERT INTO playlog (playlogdt, playlogchan, playlogchanid, playlogtitle)
         VALUES (NOW(), ?, ?, ?)'
    );
    $stmt->bind_param('sis', $channel, $channel_id, $title);
    $stmt->execute();
    $stmt->close();
}
