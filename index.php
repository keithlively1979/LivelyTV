<?php
/**
 * index.php — LivelyTV front-end player
 * Public, no auth required.
 */
require_once 'tv/dbconnect.php';

// Load settings
$settings = [];
$r = $conn->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('app_name','app_logo','player_theme')");
if ($r) { while ($row = $r->fetch_assoc()) $settings[$row['setting_key']] = $row['setting_value']; $r->free(); }

$app_name     = $settings['app_name']     ?? 'LivelyTV';
$app_logo     = $settings['app_logo']     ?? '';
$player_theme = $settings['player_theme'] ?? 'dark';

$conn->close();
?>
<!DOCTYPE html>
<html lang="en" data-player-theme="<?= htmlspecialchars($player_theme) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title><?= htmlspecialchars($app_name) ?></title>
<script src="https://cdn.jsdelivr.net/npm/hls.js@1.5.7/dist/hls.min.js"></script>
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

/* ── Theme definitions ───────────────────────────────────────────────────── */

/* Dark / Cinematic */
[data-player-theme="dark"] {
    --bg:          #0a0a0f;
    --surface:     #14141e;
    --surface-2:   #1e1e2c;
    --border:      rgba(255,255,255,0.08);
    --text:        #f0f0ff;
    --text-2:      rgba(240,240,255,0.55);
    --text-3:      rgba(240,240,255,0.3);
    --accent:      #0000fe;
    --accent-glow: rgba(0,0,254,0.3);
    --now-dot:     #fe4400;
    --ch-active:   rgba(0,0,254,0.2);
    --ch-hover:    rgba(255,255,255,0.05);
    --sidebar-w:   300px;
    --topbar-h:    56px;
    --font:        -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
}

/* Retro / Broadcast */
[data-player-theme="retro"] {
    --bg:          #0d0f0b;
    --surface:     #111510;
    --surface-2:   #1a1f18;
    --border:      rgba(80,255,60,0.12);
    --text:        #c8ffc0;
    --text-2:      rgba(180,255,160,0.55);
    --text-3:      rgba(180,255,160,0.3);
    --accent:      #39ff14;
    --accent-glow: rgba(57,255,20,0.25);
    --now-dot:     #ffdd00;
    --ch-active:   rgba(57,255,20,0.12);
    --ch-hover:    rgba(57,255,20,0.05);
    --sidebar-w:   300px;
    --topbar-h:    56px;
    --font:        'Courier New', Courier, monospace;
}

/* Minimal / Light */
[data-player-theme="minimal"] {
    --bg:          #f0f0f5;
    --surface:     #ffffff;
    --surface-2:   #f5f5f8;
    --border:      #e2e2ea;
    --text:        #0f0f1a;
    --text-2:      #5a5a72;
    --text-3:      #9898b0;
    --accent:      #0000fe;
    --accent-glow: rgba(0,0,254,0.15);
    --now-dot:     #fe4400;
    --ch-active:   #eeeeff;
    --ch-hover:    #f5f5f8;
    --sidebar-w:   300px;
    --topbar-h:    56px;
    --font:        -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
}

/* ── Base ────────────────────────────────────────────────────────────────── */
html, body {
    height: 100%;
    background: var(--bg);
    color: var(--text);
    font-family: var(--font);
    font-size: 14px;
    overflow: hidden;
}

/* ── Topbar ──────────────────────────────────────────────────────────────── */
#topbar {
    height: var(--topbar-h);
    background: var(--surface);
    border-bottom: 1px solid var(--border);
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 16px;
    position: fixed;
    top: 0; left: 0; right: 0;
    z-index: 50;
}

.brand {
    display: flex;
    align-items: center;
    gap: 10px;
}

.brand-logo-wrap {
    width: 32px; height: 32px;
    display: flex; align-items: center; justify-content: center;
    background: var(--accent);
    border-radius: 8px;
    flex-shrink: 0;
}

.brand-logo-wrap img { max-width: 28px; max-height: 28px; object-fit: contain; }
.brand-logo-wrap svg { width: 18px; height: 18px; fill: #fff; }

.brand-name {
    font-size: 17px;
    font-weight: 700;
    color: var(--text);
    letter-spacing: -0.3px;
}

[data-player-theme="retro"] .brand-name {
    text-transform: uppercase;
    letter-spacing: 0.12em;
    font-size: 15px;
    color: var(--accent);
    text-shadow: 0 0 8px var(--accent-glow);
}

.topbar-right {
    display: flex;
    align-items: center;
    gap: 12px;
}

#now-playing-bar {
    font-size: 13px;
    color: var(--text-2);
    max-width: 340px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

#now-playing-bar span { color: var(--text); font-weight: 500; }

.btn-guide {
    display: none;
    align-items: center;
    gap: 6px;
    padding: 7px 12px;
    background: var(--surface-2);
    border: 1px solid var(--border);
    border-radius: 6px;
    color: var(--text);
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    font-family: var(--font);
}

/* ── Layout ──────────────────────────────────────────────────────────────── */
#layout {
    display: flex;
    height: calc(100vh - var(--topbar-h));
    margin-top: var(--topbar-h);
}

/* ── Sidebar ─────────────────────────────────────────────────────────────── */
#sidebar {
    width: var(--sidebar-w);
    background: var(--surface);
    border-right: 1px solid var(--border);
    display: flex;
    flex-direction: column;
    flex-shrink: 0;
    overflow: hidden;
}

.sidebar-header {
    padding: 14px 16px 10px;
    border-bottom: 1px solid var(--border);
    flex-shrink: 0;
}

.sidebar-header-title {
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    color: var(--text-3);
}

#channel-list {
    flex: 1;
    overflow-y: auto;
    padding: 8px 0;
    scrollbar-width: thin;
    scrollbar-color: var(--border) transparent;
}

#channel-list::-webkit-scrollbar { width: 4px; }
#channel-list::-webkit-scrollbar-thumb { background: var(--border); border-radius: 2px; }

.channel-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 16px;
    cursor: pointer;
    transition: background 0.12s;
    border-left: 3px solid transparent;
    user-select: none;
}

.channel-item:hover  { background: var(--ch-hover); }

.channel-item.active {
    background: var(--ch-active);
    border-left-color: var(--accent);
}

[data-player-theme="retro"] .channel-item.active {
    border-left-color: var(--accent);
    box-shadow: inset 0 0 20px rgba(57,255,20,0.04);
}

.ch-logo {
    width: 36px; height: 36px;
    border-radius: 6px;
    object-fit: contain;
    background: var(--surface-2);
    flex-shrink: 0;
    border: 1px solid var(--border);
}

.ch-no-logo {
    width: 36px; height: 36px;
    border-radius: 6px;
    background: var(--accent);
    color: #fff;
    display: flex; align-items: center; justify-content: center;
    font-size: 13px;
    font-weight: 700;
    flex-shrink: 0;
}

[data-player-theme="retro"] .ch-no-logo {
    background: transparent;
    border: 1px solid var(--accent);
    color: var(--accent);
    text-shadow: 0 0 6px var(--accent);
}

.ch-info { min-width: 0; }

.ch-name {
    font-size: 14px;
    font-weight: 600;
    color: var(--text);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.ch-now {
    font-size: 12px;
    color: var(--text-2);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    margin-top: 2px;
}

.ch-now .now-dot {
    display: inline-block;
    width: 6px; height: 6px;
    background: var(--now-dot);
    border-radius: 50%;
    margin-right: 4px;
    vertical-align: middle;
    animation: blink 2s ease-in-out infinite;
}

@keyframes blink {
    0%,100% { opacity: 1; }
    50%      { opacity: 0.3; }
}

/* ── Video area ──────────────────────────────────────────────────────────── */
#video-area {
    flex: 1;
    background: #000;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    overflow: hidden;
}

#player {
    width: 100%;
    height: 100%;
    object-fit: contain;
}

/* Loading overlay */
#loading-overlay {
    position: absolute;
    inset: 0;
    background: rgba(0,0,0,0.7);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 16px;
    transition: opacity 0.3s;
}

#loading-overlay.hidden { opacity: 0; pointer-events: none; }

.spinner {
    width: 40px; height: 40px;
    border: 3px solid rgba(255,255,255,0.15);
    border-top-color: var(--accent);
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
}

@keyframes spin { to { transform: rotate(360deg); } }

.loading-text {
    color: rgba(255,255,255,0.7);
    font-size: 14px;
}

/* Error overlay */
#error-overlay {
    position: absolute;
    inset: 0;
    background: rgba(0,0,0,0.85);
    display: none;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 12px;
    color: #fff;
    text-align: center;
    padding: 24px;
}

#error-overlay .err-icon { font-size: 40px; }
#error-overlay .err-msg  { font-size: 15px; color: rgba(255,255,255,0.8); }

/* No channel state */
#no-channel {
    position: absolute;
    inset: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 12px;
    color: var(--text-3);
    font-size: 15px;
}

#no-channel .no-ch-icon { font-size: 48px; }

/* Retro scanlines */
[data-player-theme="retro"] #video-area::after {
    content: '';
    position: absolute;
    inset: 0;
    background: repeating-linear-gradient(
        0deg,
        transparent,
        transparent 2px,
        rgba(0,0,0,0.08) 2px,
        rgba(0,0,0,0.08) 4px
    );
    pointer-events: none;
    z-index: 5;
}

/* ── Mobile slide-up drawer ──────────────────────────────────────────────── */
#drawer-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.6);
    z-index: 100;
}

#drawer {
    position: fixed;
    bottom: 0; left: 0; right: 0;
    height: 70vh;
    background: var(--surface);
    border-radius: 16px 16px 0 0;
    display: flex;
    flex-direction: column;
    transform: translateY(100%);
    transition: transform 0.3s cubic-bezier(0.32,0.72,0,1);
    z-index: 101;
    overflow: hidden;
}

#drawer.open { transform: translateY(0); }

.drawer-handle {
    width: 36px; height: 4px;
    background: var(--border);
    border-radius: 2px;
    margin: 12px auto 8px;
    flex-shrink: 0;
}

.drawer-title {
    padding: 4px 16px 12px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    color: var(--text-3);
    border-bottom: 1px solid var(--border);
    flex-shrink: 0;
}

#drawer-channel-list {
    flex: 1;
    overflow-y: auto;
    padding: 8px 0;
    padding-bottom: env(safe-area-inset-bottom, 0);
}

/* ── Responsive ──────────────────────────────────────────────────────────── */
@media (max-width: 700px) {
    #sidebar    { display: none; }
    .btn-guide  { display: flex; }
    #drawer-overlay { display: block; opacity: 0; pointer-events: none; transition: opacity 0.3s; }
    #drawer-overlay.open { opacity: 1; pointer-events: all; }
    #now-playing-bar { display: none; }
}
</style>
</head>
<body>

<!-- Topbar -->
<div id="topbar">
    <div class="brand">
        <?php if ($app_logo): ?>
            <div class="brand-logo-wrap" style="background:transparent">
                <img src="<?= htmlspecialchars($app_logo) ?>" alt="">
            </div>
        <?php else: ?>
            <div class="brand-logo-wrap">
                <svg viewBox="0 0 24 24"><path d="M4 6a2 2 0 012-2h12a2 2 0 012 2v12a2 2 0 01-2 2H6a2 2 0 01-2-2V6zm3 1v10l8-5-8-5z"/></svg>
            </div>
        <?php endif; ?>
        <span class="brand-name"><?= htmlspecialchars($app_name) ?></span>
    </div>

    <div id="now-playing-bar">Select a channel to start watching</div>

    <div class="topbar-right">
        <button class="btn-guide" onclick="openDrawer()">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                <path d="M3 7h18M3 12h18M3 17h18"/>
                <rect x="3" y="6" width="18" height="2" rx="1"/>
                <rect x="3" y="11" width="18" height="2" rx="1"/>
                <rect x="3" y="16" width="18" height="2" rx="1"/>
            </svg>
            Guide
        </button>
    </div>
</div>

<!-- Layout -->
<div id="layout">

    <!-- Desktop sidebar -->
    <div id="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-header-title">Channels</div>
        </div>
        <div id="channel-list">
            <div style="padding:24px;text-align:center;color:var(--text-3)">Loading…</div>
        </div>
    </div>

    <!-- Video area -->
    <div id="video-area">
        <video id="player" controls playsinline></video>

        <div id="loading-overlay">
            <div class="spinner"></div>
            <div class="loading-text">Loading stream…</div>
        </div>

        <div id="error-overlay">
            <div class="err-icon">📡</div>
            <div class="err-msg" id="error-msg">Stream unavailable. Please try again.</div>
            <button onclick="retryStream()"
                    style="margin-top:8px;padding:8px 18px;background:var(--accent);color:#fff;border:none;border-radius:6px;cursor:pointer;font-size:14px">
                Retry
            </button>
        </div>

        <div id="no-channel">
            <div class="no-ch-icon">📺</div>
            <div>Select a channel to start watching</div>
        </div>
    </div>
</div>

<!-- Mobile drawer overlay -->
<div id="drawer-overlay" onclick="closeDrawer()"></div>

<!-- Mobile drawer -->
<div id="drawer">
    <div class="drawer-handle"></div>
    <div class="drawer-title">Channels</div>
    <div id="drawer-channel-list">
        <div style="padding:24px;text-align:center;color:var(--text-3)">Loading…</div>
    </div>
</div>

<script>
const API_BASE      = '/tv/api';
const NOW_POLL_MS   = 60000; // refresh now-playing every 60s

let channels        = [];
let activeChannelId = null;
let hls             = null;
let nowPlayingData  = {};
let nowPollTimer    = null;

// ── Boot ──────────────────────────────────────────────────────────────────────
async function init() {
    await loadChannels();
    await loadNowPlaying();
    startNowPlayingPoll();

    // Auto-load first channel
    if (channels.length > 0) {
        selectChannel(channels[0].id);
    }
}

// ── Channels ──────────────────────────────────────────────────────────────────
async function loadChannels() {
    try {
        const res  = await fetch(`${API_BASE}/channels.php`);
        channels   = await res.json();
    } catch (e) {
        channels = [];
    }
    renderChannelList();
}

function renderChannelList() {
    const listEl   = document.getElementById('channel-list');
    const drawerEl = document.getElementById('drawer-channel-list');

    if (!channels.length) {
        const msg = '<div style="padding:24px;text-align:center;color:var(--text-3)">No channels configured.</div>';
        listEl.innerHTML   = msg;
        drawerEl.innerHTML = msg;
        return;
    }

    const html = channels.map(ch => buildChannelItem(ch)).join('');
    listEl.innerHTML   = html;
    drawerEl.innerHTML = html;
}

function buildChannelItem(ch) {
    const nowTitle = nowPlayingData[ch.id]?.title ?? '';
    const active   = ch.id === activeChannelId ? 'active' : '';
    const logo     = ch.logo
        ? `<img class="ch-logo" src="${escHtml(ch.logo)}" alt="">`
        : `<div class="ch-no-logo">${ch.id}</div>`;
    const nowHtml  = nowTitle
        ? `<div class="ch-now"><span class="now-dot"></span>${escHtml(nowTitle)}</div>`
        : '';

    return `
        <div class="channel-item ${active}" onclick="selectChannel(${ch.id})" data-ch-id="${ch.id}">
            ${logo}
            <div class="ch-info">
                <div class="ch-name">${escHtml(ch.name)}</div>
                ${nowHtml}
            </div>
        </div>`;
}

// ── Select channel ────────────────────────────────────────────────────────────
function selectChannel(channelId) {
    const ch = channels.find(c => c.id === channelId);
    if (!ch) return;

    activeChannelId = channelId;
    closeDrawer();

    // Update active state in both lists
    document.querySelectorAll('.channel-item').forEach(el => {
        el.classList.toggle('active', parseInt(el.dataset.chId) === channelId);
    });

    // Update now-playing topbar
    updateNowPlayingBar(channelId);

    // Hide no-channel state
    document.getElementById('no-channel').style.display = 'none';

    if (!ch.stream_url) {
        showError('No stream URL configured for this channel.');
        return;
    }

    loadStream(ch.stream_url);
}

// ── HLS stream ────────────────────────────────────────────────────────────────
function loadStream(url) {
    const video = document.getElementById('player');
    showLoading(true);
    hideError();

    // Destroy existing HLS instance
    if (hls) {
        hls.destroy();
        hls = null;
    }

    if (Hls.isSupported()) {
        hls = new Hls({
            maxBufferLength:           30,
            maxMaxBufferLength:        60,
            lowLatencyMode:            false,
            enableWorker:              true,
            xhrSetup: (xhr) => {
                xhr.timeout = 10000;
            }
        });

        hls.loadSource(url);
        hls.attachMedia(video);

        hls.on(Hls.Events.MANIFEST_PARSED, () => {
            showLoading(false);
            video.play().catch(() => {});
        });

        hls.on(Hls.Events.ERROR, (event, data) => {
            if (data.fatal) {
                switch (data.type) {
                    case Hls.ErrorTypes.NETWORK_ERROR:
                        showError('Network error — stream may be offline.');
                        break;
                    case Hls.ErrorTypes.MEDIA_ERROR:
                        hls.recoverMediaError();
                        break;
                    default:
                        showError('Stream error. Check VLC is running.');
                }
            }
        });

    } else if (video.canPlayType('application/vnd.apple.mpegurl')) {
        // Safari native HLS
        video.src = url;
        video.addEventListener('loadedmetadata', () => {
            showLoading(false);
            video.play().catch(() => {});
        }, { once: true });
        video.addEventListener('error', () => {
            showError('Stream unavailable.');
        }, { once: true });
    } else {
        showError('HLS not supported in this browser.');
    }
}

function retryStream() {
    const ch = channels.find(c => c.id === activeChannelId);
    if (ch) loadStream(ch.stream_url);
}

// ── Now playing ───────────────────────────────────────────────────────────────
async function loadNowPlaying() {
    try {
        const res   = await fetch(`${API_BASE}/now_playing.php`);
        nowPlayingData = await res.json();
    } catch (e) {
        nowPlayingData = {};
    }
    updateNowPlayingBar(activeChannelId);
    refreshChannelNowPlaying();
}

function startNowPlayingPoll() {
    if (nowPollTimer) clearInterval(nowPollTimer);
    nowPollTimer = setInterval(loadNowPlaying, NOW_POLL_MS);
}

function updateNowPlayingBar(channelId) {
    const bar = document.getElementById('now-playing-bar');
    if (!channelId) {
        bar.textContent = 'Select a channel to start watching';
        return;
    }
    const ch    = channels.find(c => c.id === channelId);
    const now   = nowPlayingData[channelId];
    if (ch && now) {
        bar.innerHTML = `<span>${escHtml(ch.name)}</span> — ${escHtml(now.title)}`;
    } else if (ch) {
        bar.innerHTML = `<span>${escHtml(ch.name)}</span>`;
    }
}

function refreshChannelNowPlaying() {
    // Update now-playing text in channel list items without full re-render
    document.querySelectorAll('.channel-item').forEach(el => {
        const id  = parseInt(el.dataset.chId);
        const now = nowPlayingData[id];
        let nowEl = el.querySelector('.ch-now');

        if (now?.title) {
            if (!nowEl) {
                nowEl = document.createElement('div');
                nowEl.className = 'ch-now';
                el.querySelector('.ch-info').appendChild(nowEl);
            }
            nowEl.innerHTML = `<span class="now-dot"></span>${escHtml(now.title)}`;
        } else if (nowEl) {
            nowEl.remove();
        }
    });
}

// ── Mobile drawer ─────────────────────────────────────────────────────────────
function openDrawer() {
    document.getElementById('drawer').classList.add('open');
    document.getElementById('drawer-overlay').classList.add('open');
    document.body.style.overflow = 'hidden';
}

function closeDrawer() {
    document.getElementById('drawer').classList.remove('open');
    document.getElementById('drawer-overlay').classList.remove('open');
    document.body.style.overflow = '';
}

// ── UI helpers ────────────────────────────────────────────────────────────────
function showLoading(show) {
    document.getElementById('loading-overlay').classList.toggle('hidden', !show);
}

function showError(msg) {
    showLoading(false);
    const el = document.getElementById('error-overlay');
    document.getElementById('error-msg').textContent = msg;
    el.style.display = 'flex';
}

function hideError() {
    document.getElementById('error-overlay').style.display = 'none';
}

function escHtml(str) {
    return String(str ?? '')
        .replace(/&/g,'&amp;')
        .replace(/</g,'&lt;')
        .replace(/>/g,'&gt;')
        .replace(/"/g,'&quot;');
}

// ── Keyboard shortcuts ────────────────────────────────────────────────────────
document.addEventListener('keydown', e => {
    if (e.target.tagName === 'INPUT') return;
    const idx = channels.findIndex(c => c.id === activeChannelId);
    if (e.key === 'ArrowUp'   && idx > 0)                      selectChannel(channels[idx - 1].id);
    if (e.key === 'ArrowDown' && idx < channels.length - 1)    selectChannel(channels[idx + 1].id);
    if (e.key === 'g' || e.key === 'G')                        openDrawer();
    if (e.key === 'Escape')                                     closeDrawer();
});

// Swipe down to close drawer on mobile
let touchStartY = 0;
document.getElementById('drawer').addEventListener('touchstart', e => {
    touchStartY = e.touches[0].clientY;
}, { passive: true });
document.getElementById('drawer').addEventListener('touchend', e => {
    if (e.changedTouches[0].clientY - touchStartY > 60) closeDrawer();
}, { passive: true });

// Boot
init();
</script>
</body>
</html>
