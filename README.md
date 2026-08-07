# LivelyTV

A self-hosted simulated live TV system that uses Apache, MySQL, PHP, and VLC to generate HLS streams from a scheduled playlist, with a full admin panel, frontend player, and EPG guide.

## Requirements

- Apache with mod_rewrite
- PHP 8.0+
- MySQL 8.0+
- VLC (on the streaming machine)
- HLS.js (loaded via CDN in the frontend player)

## Directory Structure

```
/var/www/
├── .env                          ← credentials (above web root)
└── lively.local/
    ├── index.php                 ← frontend player
    └── tv/
        ├── dbconnect.php         ← reads from .env
        ├── playlist.php          ← unified playlist generator
        ├── epg.php               ← read-only EPG guide
        ├── tv.sql                ← complete database schema
        ├── api/
        │   ├── channels.php      ← returns visible channel list (JSON)
        │   └── now_playing.php   ← returns current show per channel (JSON)
        └── admin/
            ├── index.php         ← login
            ├── dashboard.php
            ├── shows.php
            ├── episodes.php
            ├── movies.php
            ├── commercials.php
            ├── channels.php
            ├── schedule.php
            ├── playlog.php
            ├── scraper.php
            ├── scraper_run.php
            ├── settings.php
            ├── users.php
            ├── logout.php
            ├── toggle_theme.php
            └── includes/
                ├── auth.php
                ├── header.php
                ├── footer.php
                └── themes.php
```

## Installation

### 1. Database

Import the schema:
```bash
mysql -u root -p -e "CREATE DATABASE tv CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;"
mysql -u root -p tv < tv/tv.sql
```

### 2. Environment file

Create `/var/www/.env` (one level above the web root):
```
DB_HOST=localhost
DB_USER=your_db_user
DB_PASS=your_db_password
DB_NAME=tv
```

Set permissions:
```bash
sudo chown youruser:www-data /var/www/.env
sudo chmod 640 /var/www/.env
```

### 3. Uploads directory

```bash
mkdir -p /var/www/lively.local/tv/admin/uploads
sudo chown www-data:www-data /var/www/lively.local/tv/admin/uploads
chmod 755 /var/www/lively.local/tv/admin/uploads
```

### 4. First login

Navigate to `http://your-host/tv/admin/` and log in with:
- Username: `admin`
- Password: `admin`

**Change the password immediately** via the Users page, or generate a hash first:
```bash
php -r "echo password_hash('yourpassword', PASSWORD_BCRYPT);"
```
Then update: `UPDATE users SET user_password='<hash>' WHERE user_name='admin';`

### 5. Configure channels

Go to Admin → Channels and add each channel with its name, HLS stream URL, and optionally a logo. The stream URL should point to the VLC-generated HLS index, e.g.:
```
http://tv.lively.local/media/channel01/stream.m3u8
```

### 6. Configure content paths

Go to Admin → Settings → Content paths and add the directories the scraper should scan, flagged as either TV or Movies type.

### 7. VLC command

TV channel:
```bash
cvlc \
  --input-repeat=-1 \
  --network-caching=5000 \
  --video-filter="canvas{width=1280,height=720,aspect=16:9}" \
  --sout-mux-caching=1500 \
  "http://tv.lively.local/tv/playlist.php?type=tv&ch=1" \
  --sout "#transcode{fps=24,vcodec=h264,vb=4000,venc=x264{profile=baseline,level=30,keyint=48,bframes=0},acodec=mp4a,ab=192,channels=2}:gather:std{access=livehttp{seglen=6,delsegs=true,numsegs=10,index=/media/channel01/stream.m3u8,index-url=http://tv.lively.local/tv/channel01/stream-######.ts},mux=ts{use-key-frames,pid-video=100,pid-audio=200},dst=/media/channel01/stream-######.ts}" \
  vlc://quit
```

Movie channel:
```bash
cvlc \
  --input-repeat=-1 \
  --network-caching=5000 \
  "http://tv.lively.local/tv/playlist.php?type=movie&ch=3&genre=Comedy&y1=1985&y2=2024" \
  --sout "#transcode{...}:gather:std{...}" \
  vlc://quit
```

## File path conventions

All media is served from `/media` using `file:///` URIs in playlists:

| Content | Path |
|---------|------|
| TV Shows | `/media/plex/TV Shows/<Show>/<Season>/` |
| Movies | `/media/plex/Movies/<Title (Year)>/` |
| Preroll | `/media/Commercials/preroll.mp4` |
| Trailers | `/media/Commercials/Trailers/<movie-basename>-trailer.<ext>` |
| Rating bumpers | `/media/Commercials/Rating/G.mp4`, `PG.mp4`, `PG-13.mp4`, `R.mp4` |
| Bumpers in/out | `/media/Commercials/bumperin.mp4`, `bumperout.mp4` |

Database episode paths use the `_BASEDIR_` token which is resolved at playlist generation time, e.g. `_BASEDIR_/Season 1/S01E01.mkv`.

## Movie channel playlist sequence

1. Up to 4 trailers from genre/year-matched movies with `movie_trailer = 1`
2. Preroll
3. MPAA rating bumper (G, PG, PG-13, or R only — others skipped)
4. The movie
5. Loop back to playlist.php

## Admin features

- **Shows / Episodes / Movies / Commercials** — full CRUD with inline edit modals
- **Channels** — manage HLS stream URLs, logos, visibility in frontend player, and auto-populate schedules on creation
- **Schedule** — 7-day × 24-hour × 2-slot weekly grid editor per channel, with randomize option
- **Play Log** — filterable, paginated log of every show and movie played
- **Scraper** — scans content paths and imports new shows, episodes, and movies
- **Settings** — app name, logo upload, player theme (Cinematic / Broadcast / Minimal)
- **Users** — multiple accounts with bcrypt passwords, per-user colour theme and light/dark mode

## Colour themes (admin)

Blue, Indigo, Red, Green, Orange, Monochrome, Cyber — selectable per user via the Users page.

## Player themes (frontend)

Cinematic (dark), Broadcast (retro CRT), Minimal (light) — selectable globally via Settings.

## License

This project is licensed under the [GNU General Public License v3.0 (GPL-3.0)](https://www.gnu.org/licenses/gpl-3.0.html).
