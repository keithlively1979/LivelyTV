# LivelyTV

A self-hosted simulated live TV system for a local network. LivelyTV turns a media library into scheduled "channels" — complete with commercials, bumpers, movie trailers, and an EPG — streamed over HLS.

Built with Apache, MySQL, PHP, and VLC.

## Features

- **Simulated linear TV** — channels play a continuous, scheduled stream instead of on-demand playback
- **TV and movie channels** — TV channels follow a weekly show/episode schedule; movie channels serve genre- and year-matched content with trailers, prerolls, and MPAA rating bumpers
- **Admin panel** — manage shows, episodes, movies, commercials, channels, schedule, users, and settings
- **Weekly schedule grid** — 7-day × 24-hour × 2-slot grid per channel, with manual assignment or randomization
- **Frontend player** — HLS.js-based public player with a channel guide sidebar, live now-playing info, and multiple visual themes
- **EPG guide** — read-only weekly grid guide with a live now-line and day selector
- **Multi-user admin accounts** — bcrypt-hashed passwords, per-user light/dark mode and color theme
- **Content scraper** — populates the library from configurable source directories

## Tech stack

- PHP (no framework)
- MySQL
- Apache
- VLC (HLS stream generation)
- HLS.js (frontend playback)
- Vanilla JS/CSS in the admin panel and player

## Project structure

```
/var/www/
├── .env                      # DB credentials, not committed
└── lively.local/
    └── tv/
        ├── dbconnect.php     # reads .env, provides shared DB connection
        ├── playlist.php      # generates the HLS playlist per channel
        ├── epg.php           # public EPG guide
        ├── tv.sql             # full database schema
        ├── api/
        │   ├── channels.php       # visible channels list
        │   └── now_playing.php    # now-playing info per channel
        └── admin/
            ├── index.php          # login
            ├── logout.php
            ├── toggle_theme.php
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
            └── includes/
                ├── auth.php
                ├── header.php
                ├── footer.php
                └── themes.php
```
The public frontend player lives at `/var/www/lively.local/index.php`.

## Setup

1. Clone this repo into `/var/www/lively.local/`.
2. Create `/var/www/.env` (above the web root) with your database credentials — see `.env.example`.
3. Set ownership/permissions on `.env` so Apache can read it but it isn't web-accessible:
   ```bash
   sudo chown www-data:www-data /var/www/.env
   sudo chmod 640 /var/www/.env
   ```
4. Import the schema:
   ```bash
   mysql -u youruser -p your_database < tv/tv.sql
   ```
5. Point your media library paths — TV shows and movies live under `/media/plex`, commercials/prerolls/trailers/bumpers under `/media/Commercials`. Update paths in `content_paths` (via the admin Settings page) to match your library.
6. Create your first admin user (via the `users` table or a setup script) and log in at `/tv/admin/`.
7. Configure VLC to read the generated HLS playlists from `playlist.php` per channel.

## Database

Full schema in [`tv.sql`](tv.sql). Key tables: `channels`, `commercials`, `shows`, `episodes`, `movies`, `schedule`, `playlog`, `settings`, `content_paths`, `users`.

## Security notes

- Database credentials are kept out of version control entirely — see `.env.example` and `.gitignore`.
- All SQL queries use prepared statements.
- Passwords are hashed with bcrypt (minimum 8 characters).

## License

_Add a license of your choice (e.g. MIT) if you plan to make this repo public._
