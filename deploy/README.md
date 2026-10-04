# Running Manteion on the server

Manteion runs on the Arch Linux server at aldebaran.no, next to Nextcloud and Varians, at **https://manteion.aldebaran.no** for now. Four processes make it work:

| | |
|---|---|
| Apache | holds ports 80 and 443 for every site; serves Manteion's built files from `public/`, hands PHP to Manteion's PHP-FPM pool, and passes the WebSocket connections (`/app/…`) on to Reverb |
| PHP-FPM, pool `manteion` | runs Laravel for each request, as the manteion user (the current PHP, 8.5; Nextcloud keeps php-legacy) |
| `manteion-queue` | sends web push, mail and link previews in the background |
| `manteion-reverb` | the WebSocket server for live messages, badges and activity; listens only on the server itself (127.0.0.1:8080) |

The data is one SQLite file and a folder of uploaded pictures:

```
/home/manteion/
  app -> releases/<newest>     the current release
  releases/<time>-<commit>/    the last five releases, as deployed
  storage/                     uploads (app/public), logs, Laravel's caches; shared by all releases
  database/manteion.sqlite     the database
  backups/<date>/              nightly: the database and the uploads, two weeks
  backups/before-deploy/       the database as it was before each of the last ten deploys
  manteion.env                 the settings (never in git)
```

## Setting up, once

As root (with `sudo`), except where it says otherwise.

1. **The address.** At domene.shop, in the DNS for aldebaran.no, add an A record: name `manteion`, value `91.149.51.130` (the server). Check it from anywhere with `getent hosts manteion.aldebaran.no`.

2. **PHP.** `pacman -S --needed php php-fpm php-gd php-sqlite composer`

   This is the current PHP, installed next to php-legacy. They are separate packages with their own settings (`/etc/php` and `/etc/php-legacy`) and their own services, so Nextcloud is not affected. Then turn on the extensions Manteion uses: in `/etc/php/php.ini`, remove the `;` in front of `extension=curl`, `extension=gd`, `extension=iconv`, `extension=pdo_sqlite` and `extension=zip`. `php -m` lists what is on.

3. **The manteion user and its folders.** Manteion runs as its own user, which owns its files and nothing else. Apache (user `http`) may enter the folder to read the public files; other users may not.

   ```sh
   useradd --system --create-home --home-dir /home/manteion --shell /usr/bin/nologin manteion
   chgrp http /home/manteion && chmod 750 /home/manteion
   sudo -u manteion mkdir -p /home/manteion/{releases,database,backups} /home/manteion/storage/{app/public,app/private,framework/cache/data,framework/sessions,framework/views,logs}
   sudo -u manteion chmod 700 /home/manteion/database /home/manteion/backups
   ```

4. **The deploy rule.** `visudo -f /etc/sudoers.d/manteion`, with the contents of `sudoers-manteion`. It lets proteus act as manteion, reload PHP and restart Manteion's two services without a password, and nothing more. From here on, the remaining app-level steps can be done over SSH as proteus.

5. **The settings.** From the development machine, copy the example over: `scp deploy/manteion.env.example proteus@aldebaran.no:`. Then, on the server, put it in place, owned by manteion with mode 600 (it will hold the keys), and fill it in:

   ```sh
   install -o manteion -g manteion -m 600 /home/proteus/manteion.env.example /home/manteion/manteion.env
   rm /home/proteus/manteion.env.example
   nano /home/manteion/manteion.env
   ```

   The comments in the file say where each value comes from: some from the current site's `.env` on domene.shop, the Reverb ones made with `openssl rand`.

6. **The data.** Either bring the current site over (see below), or start empty:

   ```sh
   sudo -u manteion touch /home/manteion/database/manteion.sqlite
   sudo -u manteion chmod 600 /home/manteion/database/manteion.sqlite
   sudo -u manteion sqlite3 /home/manteion/database/manteion.sqlite 'PRAGMA journal_mode=WAL;'
   ```

   The last line switches SQLite to its write-ahead log, so that pages can read while the queue worker writes. It is stored in the file, so it is done once.

7. **The first release.** On the development machine: `deploy/push.sh`. It copies the commit over as a new release, installs it, migrates the database and makes the release current. The services are not installed yet, so it stops there.

8. **PHP-FPM.** Start the current PHP's FPM with Manteion's pool:

   ```sh
   cp /home/manteion/app/deploy/php-fpm/manteion.conf /etc/php/php-fpm.d/
   systemctl enable --now php-fpm
   ```

   The `www` pool that comes with the package is not used; it can stay.

9. **The services.**

   ```sh
   cp /home/manteion/app/deploy/systemd/manteion-{queue,reverb}.service /etc/systemd/system/
   systemctl daemon-reload
   systemctl enable --now manteion-queue manteion-reverb
   ```

10. **Apache and the certificate.** Copy the port 80 site, check, and reload:

    ```sh
    cp /home/manteion/app/deploy/apache/manteion.aldebaran.no.conf /etc/httpd/conf/conf.d/
    apachectl configtest && systemctl reload httpd
    certbot certonly --apache -d manteion.aldebaran.no --deploy-hook "systemctl reload httpd"
    ```

    Then the port 443 site:

    ```sh
    cp /home/manteion/app/deploy/apache/manteion.aldebaran.no-ssl.conf /etc/httpd/conf/conf.d/
    apachectl configtest && systemctl reload httpd
    ```

    certbot's timer renews the certificate and reloads Apache. The modules the site uses (`proxy`, `proxy_http`, `proxy_fcgi`, `rewrite`, `headers`, `ssl`) are already on for the other sites.

11. **Backups.**

    ```sh
    cp /home/manteion/app/deploy/systemd/manteion-backup.{service,timer} /etc/systemd/system/
    systemctl daemon-reload
    systemctl enable --now manteion-backup.timer
    ```

    The backups are on the same disk as the data, so copy `/home/manteion/backups` to another machine as well.

12. **Check.** https://manteion.aldebaran.no should show the front page, and the browser's console should show no WebSocket errors once you are logged in. The test push in Settings → Notifications checks the queue worker and push in one go.

## Bringing the current site over

The site on domene.shop keeps its data in one SQLite file (`~/app/database/database.sqlite`, unless `DB_DATABASE` in `~/app/.env` says otherwise) and its pictures in `~/app/storage/app/public`. Do this between steps 5 and 7, from the development machine, with your domene.shop user in place of `USER`:

```sh
# Stop changes on the old site while the copy is taken.
ssh USER@login.domeneshop.no 'cd app && php artisan down'

# Copy the database and the pictures to this machine, then on to the server.
scp USER@login.domeneshop.no:app/database/database.sqlite /tmp/manteion.sqlite
rsync -a USER@login.domeneshop.no:app/storage/app/public/ /tmp/manteion-uploads/
ssh proteus@aldebaran.no 'sudo -n -u manteion tee /home/manteion/database/manteion.sqlite >/dev/null && sudo -n -u manteion chmod 600 /home/manteion/database/manteion.sqlite' < /tmp/manteion.sqlite
rsync -a --rsync-path='sudo -n -u manteion rsync' /tmp/manteion-uploads/ proteus@aldebaran.no:/home/manteion/storage/app/public/
ssh proteus@aldebaran.no "sudo -n -u manteion sqlite3 /home/manteion/database/manteion.sqlite 'PRAGMA journal_mode=WAL;'"
```

The first release (step 7) migrates the copy to the new version. It keeps the accounts, friendships, follows, events, messages, leaflets and pictures. It clears the old activity notifications and resets everyone's notification settings to the new defaults.

Then, once the release is current, forget what belonged to the old address: browsers tie push subscriptions and logins to the address, and the old site's cache and queue mean nothing here.

```sh
ssh proteus@aldebaran.no "sudo -n -u manteion sqlite3 /home/manteion/database/manteion.sqlite 'DELETE FROM push_subscriptions; DELETE FROM sessions; DELETE FROM cache; DELETE FROM cache_locks; DELETE FROM jobs;'"
```

When the new site works, send the old site's visitors on to it:

```sh
ssh USER@login.domeneshop.no 'cd app && php artisan down --redirect=https://manteion.aldebaran.no'
```

The testers log in with the same username and password. Push has to be turned on again on each device (Settings → Notifications), and the app added to the home screen again.

## Deploying a new version

On the development machine, with everything committed: `deploy/push.sh`. It copies the commit over as a new release, installs it, saves a copy of the database, migrates it (the site shows a maintenance page for those seconds), switches `app` to the new release, reloads PHP and restarts the queue worker and Reverb. Open pages reconnect by themselves.

To go back to an earlier release:

```sh
ssh proteus@aldebaran.no "sudo -n -u manteion ln -sfn /home/manteion/releases/<earlier> /home/manteion/app && sudo -n systemctl reload php-fpm && sudo -n systemctl restart manteion-queue manteion-reverb"
```

Migrations are not undone by going back. If one has to be, restore the copy taken before that deploy, from `backups/before-deploy/`, as below.

## Moving to manteion.no

The data and the settings stay; the address changes.

1. **DNS** at domene.shop: point `manteion.no` and `www.manteion.no` to `91.149.51.130` with A records, and **remove their AAAA records**, which point to domene.shop (the server has no IPv6 address, and IPv6 visitors would otherwise still reach the old host).
2. **Apache:** copy the two site files with `manteion.no` in their names and in `ServerName`, with `ServerAlias www.manteion.no` added; then `certbot certonly --apache -d manteion.no -d www.manteion.no --deploy-hook "systemctl reload httpd"` before the port 443 file goes in. Change `manteion.aldebaran.no-ssl.conf` to send everything on to `https://manteion.no` (as the port 80 file does), so old links keep working.
3. **Settings:** in `manteion.env`, `APP_URL=https://manteion.no`, `REVERB_PUBLIC_HOST=manteion.no` and `REVERB_ALLOWED_ORIGINS=manteion.no`. Then `deploy/push.sh` (it rebuilds the cached settings), or `sudo -n -u manteion php /home/manteion/app/artisan optimize` and the two restarts.
4. **The testers** log in again, turn push on again and add the app to the home screen again: browsers keep all three per address.

## Looking after it

| | |
|---|---|
| `systemctl status php-fpm manteion-queue manteion-reverb` | is it running |
| `sudo -u manteion tail -n 50 /home/manteion/storage/logs/laravel-$(date +%F).log` | the application's log |
| `journalctl -u manteion-queue -u manteion-reverb -e` | the queue worker and Reverb |
| `/var/log/httpd/manteion.aldebaran.no-*_log` | Apache's log of the site |
| `systemctl list-timers manteion-backup.timer` | when the last and next backups ran |

To restore a backup, put the site in maintenance mode and stop the two services, so that nothing writes meanwhile; restore; then start it all again:

```sh
sudo -u manteion php /home/manteion/app/artisan down
systemctl stop manteion-queue manteion-reverb
sudo -u manteion sqlite3 /home/manteion/database/manteion.sqlite ".restore '/home/manteion/backups/<date>/manteion.sqlite'"
sudo -u manteion rsync -a --delete /home/manteion/backups/<date>/uploads/ /home/manteion/storage/app/public/
systemctl start manteion-queue manteion-reverb
sudo -u manteion php /home/manteion/app/artisan up
```

Use sqlite3's `.restore` rather than copying the file over: the database keeps a write-ahead log beside it, which a plain copy would leave behind and mismatched.

`/home/manteion` is closed to other users, so run commands as manteion with absolute paths (`sudo -u manteion php /home/manteion/app/artisan …`), rather than after a `cd` into it.
