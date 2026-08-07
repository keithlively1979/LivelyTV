<?php
require_once 'includes/auth.php';

$page_title = 'Scraper';
$active_nav = 'scraper';
require_once 'includes/header.php';
?>

<div class="page-header">
    <h1>Media Scraper</h1>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px">
    <div class="card">
        <div class="card-header"><span class="card-title">TV Shows</span></div>
        <div class="card-body">
            <p class="text-muted" style="margin-bottom:16px;font-size:14px">
                Scans the NAS for TV show directories and imports new episodes into the database.
            </p>
            <button class="btn btn-primary" onclick="runScraper('tv')">▶ Run TV scraper</button>
        </div>
    </div>
    <div class="card">
        <div class="card-header"><span class="card-title">Movies</span></div>
        <div class="card-body">
            <p class="text-muted" style="margin-bottom:16px;font-size:14px">
                Scans the NAS for movie files and imports new titles into the database.
            </p>
            <button class="btn btn-primary" onclick="runScraper('movies')">▶ Run movie scraper</button>
        </div>
    </div>
</div>

<div class="card" id="output-card" style="display:none">
    <div class="card-header">
        <span class="card-title" id="output-title">Output</span>
        <button class="btn btn-secondary btn-sm" onclick="clearOutput()">Clear</button>
    </div>
    <div class="card-body" style="padding:0">
        <pre id="scraper-output" style="
            font-family: var(--font-mono);
            font-size: 13px;
            line-height: 1.7;
            padding: 20px;
            margin: 0;
            background: var(--surface-2);
            max-height: 520px;
            overflow-y: auto;
            white-space: pre-wrap;
            word-break: break-all;
            color: var(--text);
        "></pre>
    </div>
</div>

<script>
let activeReader = null;

async function runScraper(type) {
    if (activeReader) {
        activeReader.cancel();
        activeReader = null;
    }

    const card   = document.getElementById('output-card');
    const title  = document.getElementById('output-title');
    const output = document.getElementById('scraper-output');

    card.style.display = 'block';
    title.textContent  = (type === 'tv' ? 'TV Scraper' : 'Movie Scraper') + ' — running…';
    output.textContent = '';

    // Scroll output into view
    card.scrollIntoView({ behavior: 'smooth', block: 'start' });

    try {
        const res = await fetch('scraper_run.php?type=' + type, {
            method: 'GET',
            headers: { 'Accept': 'text/plain' }
        });

        if (!res.ok || !res.body) {
            output.textContent += `\n[Error: HTTP ${res.status}]`;
            title.textContent   = 'Error';
            return;
        }

        const reader = res.body.getReader();
        activeReader = reader;
        const decoder = new TextDecoder();

        while (true) {
            const { done, value } = await reader.read();
            if (done) break;
            output.textContent += decoder.decode(value, { stream: true });
            output.scrollTop = output.scrollHeight;
        }

        title.textContent = (type === 'tv' ? 'TV Scraper' : 'Movie Scraper') + ' — done';
        activeReader = null;

    } catch (err) {
        output.textContent += '\n[Stream error: ' + err.message + ']';
        title.textContent   = 'Error';
    }
}

function clearOutput() {
    document.getElementById('scraper-output').textContent = '';
    document.getElementById('output-card').style.display  = 'none';
}
</script>

<?php require_once 'includes/footer.php'; ?>
