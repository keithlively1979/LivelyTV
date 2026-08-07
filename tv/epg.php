<?php
require_once 'dbconnect.php';

$days  = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
$hours = range(0, 23);
$mins  = [0, 30];

$current_dow  = (int)date('w');
$current_hour = (int)date('H');
$current_min  = (int)date('i') >= 30 ? 30 : 0;

// Selected day — default to today
$selected_dow = isset($_GET['day']) ? (int)$_GET['day'] : $current_dow;
$selected_dow = max(0, min(6, $selected_dow));

// Fetch all channels
$channels = [];
$r = $conn->query('SELECT DISTINCT channel_id FROM schedule ORDER BY channel_id');
if ($r) { while ($row = $r->fetch_row()) $channels[] = (int)$row[0]; $r->free(); }

// Fetch full schedule for selected day, all channels
$schedule = [];
if ($channels) {
    $stmt = $conn->prepare(
        'SELECT s.channel_id, s.schedule_hour, s.schedule_min, sh.show_title
           FROM schedule s
           LEFT JOIN shows sh ON s.show_id = sh.show_id
          WHERE s.schedule_dow = ?
          ORDER BY s.channel_id, s.schedule_hour, s.schedule_min'
    );
    $stmt->bind_param('i', $selected_dow);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $schedule[$row['channel_id']][$row['schedule_hour']][$row['schedule_min']] = $row['show_title'];
    }
    $stmt->close();
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>LivelyTV — Guide</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Barlow:wght@400;500;600;700&family=Barlow+Condensed:wght@500;600;700&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
    --primary:     #0000fe;
    --primary-dim: rgba(0,0,254,0.12);
    --now:         #fe4400;
    --now-dim:     rgba(254,68,0,0.15);
    --bg:          #0a0a12;
    --surface:     #12121e;
    --surface-2:   #1a1a2a;
    --surface-3:   #222234;
    --border:      rgba(255,255,255,0.07);
    --text:        #f0f0ff;
    --text-2:      rgba(240,240,255,0.5);
    --text-3:      rgba(240,240,255,0.25);

    --slot-w:      160px;   /* width of one 30-min slot */
    --ch-label-w:  90px;    /* left channel column */
    --row-h:       56px;    /* height of each channel row */
    --header-h:    40px;    /* time header row */
    --topbar-h:    60px;
    --font-ui:     'Barlow', sans-serif;
    --font-cond:   'Barlow Condensed', sans-serif;
}

html, body {
    height: 100%;
    background: var(--bg);
    color: var(--text);
    font-family: var(--font-ui);
    font-size: 14px;
    overflow: hidden;
}

/* ── Topbar ────────────────────────────────────────────────────────────────── */
#topbar {
    height: var(--topbar-h);
    background: var(--surface);
    border-bottom: 1px solid var(--border);
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 20px;
    position: relative;
    z-index: 30;
    flex-shrink: 0;
}

.brand {
    display: flex;
    align-items: center;
    gap: 10px;
}

.brand-logo {
    width: 30px; height: 30px;
    background: var(--primary);
    border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
}

.brand-logo svg { width: 16px; height: 16px; fill: #fff; }

.brand-name {
    font-family: var(--font-cond);
    font-size: 20px;
    font-weight: 700;
    letter-spacing: 0.05em;
    color: #fff;
    text-transform: uppercase;
}

.brand-name span { color: var(--primary); }

/* Live clock */
#clock {
    font-family: var(--font-cond);
    font-size: 22px;
    font-weight: 600;
    color: var(--text);
    letter-spacing: 0.05em;
    min-width: 80px;
    text-align: center;
}

/* Day selector */
.day-nav {
    display: flex;
    gap: 4px;
}

.day-btn {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 5px 10px;
    border-radius: 6px;
    border: 1px solid transparent;
    background: none;
    color: var(--text-2);
    font-family: var(--font-cond);
    font-size: 13px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    cursor: pointer;
    text-decoration: none;
    transition: all 0.15s;
    line-height: 1.2;
}

.day-btn .day-label { font-size: 11px; color: var(--text-3); font-weight: 500; }

.day-btn:hover {
    background: var(--surface-2);
    color: var(--text);
    text-decoration: none;
}

.day-btn.active {
    background: var(--primary-dim);
    color: #fff;
    border-color: rgba(0,0,254,0.4);
}

.day-btn.today .day-label { color: var(--now); }

/* Jump to now button */
.btn-now {
    padding: 7px 14px;
    background: var(--now);
    color: #fff;
    border: none;
    border-radius: 6px;
    font-family: var(--font-cond);
    font-size: 13px;
    font-weight: 700;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    cursor: pointer;
    transition: opacity 0.15s;
    white-space: nowrap;
}
.btn-now:hover { opacity: 0.85; }

/* ── EPG wrapper ───────────────────────────────────────────────────────────── */
#epg-outer {
    height: calc(100vh - var(--topbar-h));
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

/* Sticky top-left corner — sits above both sticky panes */
#corner {
    position: absolute;
    top: var(--topbar-h);
    left: 0;
    width: var(--ch-label-w);
    height: var(--header-h);
    background: var(--surface);
    border-right: 1px solid var(--border);
    border-bottom: 1px solid var(--border);
    z-index: 20;
    display: flex;
    align-items: center;
    justify-content: center;
}

.corner-label {
    font-family: var(--font-cond);
    font-size: 10px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.12em;
    color: var(--text-3);
}

/* Scrollable EPG body */
#epg-scroll {
    flex: 1;
    overflow: auto;
    position: relative;
    scrollbar-width: thin;
    scrollbar-color: var(--surface-3) transparent;
}

#epg-scroll::-webkit-scrollbar { width: 6px; height: 6px; }
#epg-scroll::-webkit-scrollbar-track { background: transparent; }
#epg-scroll::-webkit-scrollbar-thumb { background: var(--surface-3); border-radius: 3px; }

/* ── EPG grid ──────────────────────────────────────────────────────────────── */
#epg-grid {
    display: grid;
    /* ch-label col + one col per 30-min slot (48 slots = 24 hours) */
    grid-template-columns: var(--ch-label-w) repeat(48, var(--slot-w));
    min-width: calc(var(--ch-label-w) + 48 * var(--slot-w));
}

/* Time header row */
.time-header-spacer {
    position: sticky;
    left: 0;
    top: 0;
    background: var(--surface);
    border-right: 1px solid var(--border);
    border-bottom: 1px solid var(--border);
    height: var(--header-h);
    z-index: 15;
}

.time-cell {
    height: var(--header-h);
    position: sticky;
    top: 0;
    background: var(--surface);
    border-bottom: 1px solid var(--border);
    border-right: 1px solid rgba(255,255,255,0.04);
    display: flex;
    align-items: center;
    padding-left: 10px;
    z-index: 10;
}

.time-cell.hour-start {
    font-family: var(--font-cond);
    font-size: 13px;
    font-weight: 600;
    color: var(--text-2);
    letter-spacing: 0.04em;
    border-left: 1px solid var(--border);
}

.time-cell.half { border-left: 1px dashed rgba(255,255,255,0.04); }

.time-cell.is-now-header {
    background: var(--now-dim);
    color: var(--now) !important;
}

/* Channel label column */
.ch-label {
    position: sticky;
    left: 0;
    background: var(--surface-2);
    border-right: 1px solid var(--border);
    border-bottom: 1px solid var(--border);
    height: var(--row-h);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-direction: column;
    gap: 2px;
    z-index: 5;
    padding: 0 8px;
}

.ch-number {
    font-family: var(--font-cond);
    font-size: 20px;
    font-weight: 700;
    color: var(--primary);
    line-height: 1;
}

.ch-sub {
    font-size: 9px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    color: var(--text-3);
}

/* Programme slots */
.slot {
    height: var(--row-h);
    border-right: 1px solid var(--border);
    border-bottom: 1px solid var(--border);
    padding: 0 10px;
    display: flex;
    align-items: center;
    overflow: hidden;
    position: relative;
    cursor: default;
    transition: background 0.1s;
}

.slot:hover { background: var(--surface-3); }


.slot-title {
    font-size: 13px;
    font-weight: 500;
    color: var(--text);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 100%;
}

.slot.is-now {
    background: var(--now-dim);
}

.slot.is-now .slot-title { color: #fff; }

.slot.is-now::after {
    content: '';
    position: absolute;
    top: 0; left: 0;
    width: 2px; height: 100%;
    background: var(--now);
}

/* Hour boundary */
.slot.hour-start { border-left: 1px solid var(--border); }
.slot.half { border-left: 1px dashed rgba(255,255,255,0.04); }

/* Empty slot */
.slot.empty .slot-title { color: var(--text-3); font-style: italic; }

/* Now indicator line overlay */
#now-line {
    position: absolute;
    top: 0; bottom: 0;
    width: 2px;
    background: var(--now);
    z-index: 25;
    pointer-events: none;
    box-shadow: 0 0 8px rgba(254,68,0,0.6);
}

#now-line::before {
    content: '';
    position: absolute;
    top: 0;
    left: -4px;
    width: 10px; height: 10px;
    background: var(--now);
    border-radius: 50%;
    box-shadow: 0 0 6px rgba(254,68,0,0.8);
}

/* No channels message */
.empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    height: 100%;
    gap: 12px;
    color: var(--text-3);
    padding: 40px;
}

.empty-state .icon { font-size: 48px; }
.empty-state p { font-size: 15px; }
</style>
</head>
<body>

<!-- Corner label (absolute, above both sticky planes) -->
<div id="corner"><span class="corner-label">CH</span></div>

<!-- Topbar -->
<div id="topbar">
    <div class="brand">
        <div class="brand-logo">
            <svg viewBox="0 0 24 24"><path d="M4 6a2 2 0 012-2h12a2 2 0 012 2v12a2 2 0 01-2 2H6a2 2 0 01-2-2V6zm3 1v10l8-5-8-5z"/></svg>
        </div>
        <div class="brand-name">Lively<span>TV</span> &nbsp;Guide</div>
    </div>

    <nav class="day-nav">
        <?php
        $today = (int)date('w');
        $day_names_full = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
        foreach ($days as $i => $d):
            $classes = 'day-btn';
            if ($i === $selected_dow) $classes .= ' active';
            if ($i === $today)        $classes .= ' today';
        ?>
        <a href="?day=<?= $i ?>" class="<?= $classes ?>">
            <?= $d ?>
            <span class="day-label"><?= $i === $today ? 'Today' : $day_names_full[$i] ?></span>
        </a>
        <?php endforeach; ?>
    </nav>

    <div style="display:flex;align-items:center;gap:14px">
        <div id="clock">--:--</div>
        <button class="btn-now" onclick="scrollToNow()">▶ Now</button>
    </div>
</div>

<!-- EPG -->
<div id="epg-outer">
    <?php if (!$channels): ?>
    <div class="empty-state">
        <div class="icon">📺</div>
        <p>No channels scheduled yet.</p>
    </div>
    <?php else: ?>
    <div id="epg-scroll">
        <div id="epg-grid">

            <!-- Time header row -->
            <div class="time-header-spacer"></div>
            <?php
            $slot_index = 0;
            foreach ($hours as $h):
                foreach ($mins as $m):
                    $is_now = ($selected_dow === $current_dow && $h === $current_hour && $m === $current_min);
                    $classes = 'time-cell';
                    $classes .= $m === 0 ? ' hour-start' : ' half';
                    if ($is_now) $classes .= ' is-now-header';
            ?>
            <div class="<?= $classes ?>" data-slot="<?= $slot_index ?>">
                <?= $m === 0 ? sprintf('%02d:00', $h) : '' ?>
            </div>
            <?php $slot_index++; endforeach; endforeach; ?>

            <!-- Channel rows -->
            <?php foreach ($channels as $ch):
                $slot_index = 0;
            ?>
                <!-- Channel label -->
                <div class="ch-label">
                    <div class="ch-number"><?= $ch ?></div>
                    <div class="ch-sub">Channel</div>
                </div>

                <!-- Slots -->
                <?php foreach ($hours as $h): foreach ($mins as $m):
                    $title   = $schedule[$ch][$h][$m] ?? null;
                    $is_now  = ($selected_dow === $current_dow && $h === $current_hour && $m === $current_min);

                    $classes = 'slot';
                    $classes .= $m === 0 ? ' hour-start' : ' half';
                    if ($is_now)  $classes .= ' is-now';
                    if (!$title)  $classes .= ' empty';
                ?>
                <div class="<?= $classes ?>"
                     data-slot="<?= $slot_index ?>"
                     title="<?= htmlspecialchars($title ?? 'Unscheduled') ?> — <?= sprintf('%02d:%02d', $h, $m) ?>">
                    <span class="slot-title"><?= htmlspecialchars($title ?? '—') ?></span>
                </div>
                <?php
                    $slot_index++;
                endforeach; endforeach; ?>
            <?php endforeach; ?>

        </div><!-- /#epg-grid -->

        <!-- Now line (only shown on today) -->
        <?php if ($selected_dow === $current_dow): ?>
        <div id="now-line"></div>
        <?php endif; ?>

    </div><!-- /#epg-scroll -->
    <?php endif; ?>
</div>

<script>
const SLOT_W      = 160;   // px — must match --slot-w
const CH_LABEL_W  = 90;    // px — must match --ch-label-w
const HEADER_H    = 40;    // px — must match --header-h
const TOPBAR_H    = 60;

const currentDow  = <?= $current_dow ?>;
const currentHour = <?= $current_hour ?>;
const currentMin  = <?= $current_min ?>;
const selectedDow = <?= $selected_dow ?>;

// ── Clock ─────────────────────────────────────────────────────────────────────
function updateClock() {
    const now = new Date();
    document.getElementById('clock').textContent =
        String(now.getHours()).padStart(2,'0') + ':' +
        String(now.getMinutes()).padStart(2,'0');
}
updateClock();
setInterval(updateClock, 10000);

// ── Now line position ─────────────────────────────────────────────────────────
function positionNowLine() {
    const line = document.getElementById('now-line');
    if (!line) return;

    const now      = new Date();
    const totalMin = now.getHours() * 60 + now.getMinutes();
    const slotMin  = totalMin / 30; // fractional slot position
    const x        = CH_LABEL_W + slotMin * SLOT_W;

    line.style.left = x + 'px';
    // height covers all channel rows below header
    const grid = document.getElementById('epg-grid');
    line.style.height = (grid ? grid.offsetHeight : window.innerHeight) + 'px';
}

if (selectedDow === currentDow) {
    positionNowLine();
    setInterval(positionNowLine, 60000);
}

// ── Scroll to now ─────────────────────────────────────────────────────────────
function scrollToNow() {
    const scroller = document.getElementById('epg-scroll');
    if (!scroller) return;

    const now      = new Date();
    const totalMin = now.getHours() * 60 + now.getMinutes();
    const slotMin  = totalMin / 30;
    const x        = CH_LABEL_W + slotMin * SLOT_W;

    // Centre the current time in the viewport
    const targetX = x - (scroller.clientWidth / 2);
    scroller.scrollTo({ left: Math.max(0, targetX), behavior: 'smooth' });
}

// Auto-scroll to now on load if viewing today
if (selectedDow === currentDow) {
    window.addEventListener('load', () => {
        setTimeout(scrollToNow, 100);
    });
}
</script>
</body>
</html>
