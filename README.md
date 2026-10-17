# Ezra ABC: trace and draw letters and numbers (PHP / MySQL)

A full-screen, touch-first ABC and 123 game for a two-year-old on an iPad, with a small admin panel to change
the words and pictures, record your own voice, and see the drawings Ezra saves to the "fridge".

## What kids can do
- Tap ▶ on the start screen. That turns on sound and voice and switches the browser to full screen.
- Pick a letter from the big colourful grid. A ⭐ shows on letters he has finished on that iPad.
- **✏️ Trace**: follow the dots along the fat letter. A green dot pulses where to start, every dot he
  touches pops into a star with a musical note, finished lines fill in with colour, and the whole
  letter ends in confetti, a fanfare and “Hooray! You did it! Ay is for Apple!”. If he stops for a few
  seconds, a 👆 finger shows him the next line. It's very forgiving: lines can go in any order and
  about ¾ of the dots finish a line (change this under Settings → Tracing).
- **🎨 Free draw**: draw anything over a pale letter. Drawing plays notes, higher at the top of the
  screen, lower at the bottom.
- Crayons: 7 colours, 🌈 rainbow and ✨ glitter. Several fingers can draw at once.
- Tap the word card at the top (“A is for Apple 🔊”) to hear it again.
- ◀ ▶ move through the letters, **Aa** switches between big and little letters, 🧽 starts over,
  ⭐ puts the drawing on the fridge, and 🧲 on the home screen opens the fridge.
- **123 numbers**: the **ABC / 123** switch on the home screen swaps the letters for the numbers
  0 to 100 (or up to 10, 20 or 50, see Settings). Pick a group (0–10, 11–20 … 91–100), then a number.
  Numbers trace and draw just like letters. Each number has something to count (3 cars, 6 ducks…),
  the voice says “Three! Three cars!”, and finishing one shows them on the celebration card.
- **Counting**: tap the number card at the top. Up to 20, the things pop in one at a time while the
  voice counts “one, two, three…”. Above 20 it counts by tens with ten-bars (“ten, twenty, thirty,
  forty…”) and then the ones, ending on “Forty-two!”.
- Pinch-zoom, double-tap zoom, page scrolling and the long-press menu are all blocked so little
  fingers can't wander off.

## Full screen on the iPad
- In Safari, the first tap on ▶ asks for full screen. The ⛶ button in the corner switches it back on
  if he swipes out of it.
- Best option: in Safari tap Share → **Add to Home Screen**. It then opens from its own icon with
  no browser bars at all (the ⛶ button hides itself there because it isn't needed).
- To keep him inside the app, turn on **Guided Access** (Settings → Accessibility → Guided Access,
  then triple-click the side/top button while the game is open).

## Server setup (new subdomain, e.g. ezraabc.300interactive.com)
1. Create the database and user:
   ```sql
   CREATE DATABASE ezraabc CHARACTER SET utf8mb4;
   CREATE USER 'ezraabc_user'@'localhost' IDENTIFIED BY 'a-strong-password';
   GRANT ALL ON ezraabc.* TO 'ezraabc_user'@'localhost';
   ```
2. Upload this folder to the server, e.g. `/var/www/ezraabc`.
3. Point an Apache vhost at it:
   ```apache
   <VirtualHost *:80>
       ServerName ezraabc.300interactive.com
       DocumentRoot /var/www/ezraabc
       <Directory /var/www/ezraabc>
           AllowOverride All
           Require all granted
       </Directory>
   </VirtualHost>
   ```
   Then run `sudo a2enmod rewrite headers expires && sudo systemctl reload apache2`, and add HTTPS with
   `sudo certbot --apache -d ezraabc.300interactive.com`. HTTPS matters here: the admin sends a
   password, browsers only allow the 🎙️ recorder on HTTPS, and iPad full screen works best on it.
4. Copy `config.sample.php` to `config.php` and fill in the DB details.
5. Make the uploads folder writable by Apache:
   ```bash
   sudo chown -R www-data:www-data /var/www/ezraabc/uploads
   sudo chmod -R 775 /var/www/ezraabc/uploads
   ```
6. From the app folder on the server, run the installer and create your admin login:
   ```bash
   cd /var/www/ezraabc && php dev/install.php
   ```
   It creates the tables, adds the 26 letters with their words, checks the upload folders and asks for
   a username and password. It's safe to run again, and `php dev/install.php --reset-password` changes
   a forgotten password. The `dev/` folder is blocked from the web.
7. Log in at `/admin/`.

### Updating an existing install
After pulling a new version, run `php dev/install.php` again. It upgrades the database where needed
(for example, the numbers update widens the `drawings` table so numbers can go on the fridge, adds the
`numbers` table and a Hooray clip column on `letters`) and
never touches your letters, settings or drawings.

Requires PHP 8.1+ with the pdo_mysql, fileinfo and gd extensions, and MySQL 5.7+ or MariaDB 10.3+.
Nothing is loaded from other sites: the Fredoka font is in `assets/fonts/` and all sounds are made
in the browser.

## Admin panel
| Page | What it does |
|---|---|
| Letters | For each letter: the word, emoji, your own picture (shown in a circle; try **E is for Ezra** with his photo), a voice clip (🎙️ record right in the browser or upload an MP3/M4A/WAV), a 🎉 Hooray clip, and an on/off switch to hide letters he isn't ready for |
| Numbers | For each number: a voice clip (“Three! Three cars!”) and a 🎉 Hooray clip, recorded or uploaded the same way as letters |
| Fridge | Every saved drawing, newest first, with download and remove |
| Settings | Game title, numbers on/off and how high to count (10, 20, 50 or 100), start with ABC or abc, start in trace or draw, tracing difficulty, sound effects, voice on/off, **Voice** (pick the voice with ▶ Hear it, pitch, talking speed, and your own “Hooray! You did it!” clip for everything), fridge on/off, auto-save finished tracings, change password |

**Voice:** without a clip, the iPad's built-in voice says “Ay! Ay is for Apple!” or “Three! Three cars!”.
Pick it under Settings → **Voice**. Voices come from the device, so open the admin **on Ezra's iPad** to
see the iPad's voices; ▶ Hear it and ⏭ Try the next voice preview them with the pitch and speed you set.
Automatic picks the most natural one (Premium / Enhanced first, never the novelty voices). For the nicest
voices, on the iPad open Settings → Accessibility → Spoken Content → Voices → English and download
**Ava (Premium)** or **Zoe (Premium)**. A device that doesn't have the chosen voice uses Automatic.

With a clip, your recording plays instead. When he finishes tracing, the game plays the letter's or
number's own 🎉 Hooray clip, else the Hooray clip from Settings, else the voice says “Hooray! You did
it!”, and then says the letter or number (your clip if there is one). Record on the iPad or iPhone itself if you can: Safari records M4A, which
every device plays. Chrome on a computer records WebM, which plays on recent iPads (iPadOS 17+).

## What's hidden from the web
`.htaccess` answers **404** for the `dev/`, `inc/` and `.git/` folders, every dotfile (except
`.well-known/`), `config.php`, `config.sample.php`, data/backup files
(`.sql .md .log .ini .env .bak .json .zip ~` and similar) and PHP error logs. `dev/` and `inc/` also
have their own deny-all `.htaccess`. The uploads folder refuses to run any script, and saved
drawings are re-encoded with GD so only real images are written.

## Git
`.gitignore` leaves out `config.php` (your DB password), everything in `uploads/` (the folder
structure and its `.htaccess` are kept), logs, backups and OS/editor clutter. After cloning on a
server, copy `config.sample.php` to `config.php` and run `php dev/install.php`.

## Icons
All made from Ezra's photo: his face in a gold ring on the game's purple-to-blue background, with A, B, C blocks.
- `assets/icons/apple-touch-icon.png` (180×180) plus the 167 and 152 px sizes are used when you tap Share → **Add to Home Screen** on an iPad or iPhone. They're square and solid; iOS rounds the corners itself.
- `assets/icons/icon-192/512/1024.png` are used by the web-app manifest, and `icon-maskable-512.png` has extra padding so Android can crop it to any shape.
- `favicon.ico` plus `assets/icons/favicon-32.png` / `favicon-96.png` are the browser-tab icon (just his face in the gold ring).
- `assets/icons/logo-ezra.png` is the logo on the start screen, the letter grid, the admin header and the login page.

## Files
```
index.php            the game (splash, letter and number grids, trace/draw board, fridge)
manifest.php         Add to Home Screen / full-screen settings
api/drawings.php     saves drawings and lists them for the fridge
admin/               admin panel (letters, numbers, fridge, settings, login)
inc/                 shared PHP (blocked from the web)
assets/js/letters.js the stroke shapes for A–Z, a–z and the digits 0–9, number words and things to count
assets/js/game.js    the game
assets/css/, fonts/, icons/
uploads/             drawings and letter pictures/voices (scripts can't run here)
dev/install.php      command-line installer (blocked from the web)
dev/schema.sql       database tables
```
