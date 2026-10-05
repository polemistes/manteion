# Running Manteion on the server

Manteion runs at **https://manteion.no** on the Arch Linux server (its own name is aldebaran.no), next to Nextcloud and Varians. Four processes make it work:

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

## Reaching the server over SSH

At home, SSH goes straight to the server on port 22. From anywhere else, port 22 doesn't get through, so the router forwards port 2222 to the server's port 22 instead (the server itself still listens on 22 only). On the development machine, `~/.ssh/config` picks the port, so that `ssh proteus@manteion.no` and `deploy/push.sh` work in both places. All the server's names reach the same machine, with the same key:

```
# Home is where the server answers on its own address; elsewhere, go through 2222.
Match host manteion.no,varians.eu,aldebaran.no !exec "timeout 1 bash -c '</dev/tcp/192.168.0.141/22' 2>/dev/null"
    Port 2222

# One server, one key, whichever name and port it is reached by.
Host manteion.no varians.eu aldebaran.no
    HostKeyAlias aldebaran.no
```

## Setting up, once

As root (with `sudo`), except where it says otherwise.

1. **The address.** At domene.shop, `manteion.no` and `www.manteion.no` point to the server: A records with the value `91.149.51.130`, and no AAAA records (the server has no IPv6 address, so an AAAA record would send IPv6 visitors somewhere else). Check from anywhere with `getent ahosts manteion.no www.manteion.no`.

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

5. **The settings.** From the development machine, copy the example over: `scp deploy/manteion.env.example proteus@manteion.no:` (mind the colon at the end). Then, on the server, put it in place, owned by manteion with mode 600 (it will hold the keys), and fill it in:

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
    cp /home/manteion/app/deploy/apache/manteion.no.conf /etc/httpd/conf/conf.d/
    apachectl configtest && systemctl reload httpd
    certbot certonly --apache -d manteion.no -d www.manteion.no --deploy-hook "systemctl reload httpd"
    ```

    Then the port 443 site:

    ```sh
    cp /home/manteion/app/deploy/apache/manteion.no-ssl.conf /etc/httpd/conf/conf.d/
    apachectl configtest && systemctl reload httpd
    ```

    The site also asks for HTTP/2 and compression, which make a big difference over the 5G line (one connection for the many small built files, and text at a third of its size). Switch the two modules on in `/etc/httpd/conf/httpd.conf`, by removing the `#` in front of `LoadModule deflate_module modules/mod_deflate.so` and `LoadModule http2_module modules/mod_http2.so`, then `apachectl configtest && systemctl reload httpd`. Until then the site works without them.

    www.manteion.no sends everyone on to manteion.no, so the site has one address. certbot's timer renews the certificate and reloads Apache. The modules the site uses (`proxy`, `proxy_http`, `proxy_fcgi`, `rewrite`, `headers`, `ssl`) are already on for the other sites.

11. **Backups.**

    ```sh
    cp /home/manteion/app/deploy/systemd/manteion-backup.{service,timer} /etc/systemd/system/
    systemctl daemon-reload
    systemctl enable --now manteion-backup.timer
    ```

    The backups are on the same disk as the data, so copy `/home/manteion/backups` to another machine as well.

12. **Check.** https://manteion.no should show the front page, and the browser's console should show no WebSocket errors once you are logged in. The test push in Settings → Notifications checks the queue worker and push in one go.

## Bringing the current site over

The site on domene.shop keeps its data in one SQLite file (`~/app/database/database.sqlite`, unless `DB_DATABASE` in `~/app/.env` says otherwise) and its pictures in `~/app/storage/app/public`. Do this between steps 5 and 7, from the development machine, with your domene.shop user in place of `USER`:

```sh
# Stop changes on the old site while the copy is taken.
ssh USER@login.domeneshop.no 'cd app && php artisan down'

# Copy the database and the pictures to this machine, then on to the server.
scp USER@login.domeneshop.no:app/database/database.sqlite /tmp/manteion.sqlite
rsync -a USER@login.domeneshop.no:app/storage/app/public/ /tmp/manteion-uploads/
ssh proteus@manteion.no 'sudo -n -u manteion tee /home/manteion/database/manteion.sqlite >/dev/null && sudo -n -u manteion chmod 600 /home/manteion/database/manteion.sqlite' < /tmp/manteion.sqlite
rsync -a --rsync-path='sudo -n -u manteion rsync' /tmp/manteion-uploads/ proteus@manteion.no:/home/manteion/storage/app/public/
ssh proteus@manteion.no "sudo -n -u manteion sqlite3 /home/manteion/database/manteion.sqlite 'PRAGMA journal_mode=WAL;'"
```

The first release (step 7) migrates the copy to the new version. It keeps the accounts, friendships, follows, events, messages, leaflets and pictures. It clears the old activity notifications and resets everyone's notification settings to the new defaults.

Then, once the release is current, clear what belonged to the old version: its logins, its cache and queue, and its push subscriptions, which its own service worker made and which carry nothing of what the new version keeps per device.

```sh
ssh proteus@manteion.no "sudo -n -u manteion sqlite3 /home/manteion/database/manteion.sqlite 'DELETE FROM push_subscriptions; DELETE FROM sessions; DELETE FROM cache; DELETE FROM cache_locks; DELETE FROM jobs;'"
```

Since manteion.no points to this server, the old site gets no more visitors; leave it in maintenance mode as a fallback copy until the web hotel is given up.

The testers keep the address and log in with the same username and password. Push has to be turned on again on each device (Settings → Notifications). An app on the home screen keeps working: it belongs to the address.

## Deploying a new version

On the development machine, with everything committed: `deploy/push.sh`. It copies the commit over as a new release, installs it, saves a copy of the database, migrates it (the site shows a maintenance page for those seconds), switches `app` to the new release, reloads PHP and restarts the queue worker and Reverb. Open pages reconnect by themselves.

To go back to an earlier release:

```sh
ssh proteus@manteion.no "sudo -n -u manteion ln -sfn /home/manteion/releases/<earlier> /home/manteion/app && sudo -n systemctl reload php-fpm && sudo -n systemctl restart manteion-queue manteion-reverb"
```

Migrations are not undone by going back. If one has to be, restore the copy taken before that deploy, from `backups/before-deploy/`, as below.

## Looking after it

| | |
|---|---|
| `systemctl status php-fpm manteion-queue manteion-reverb` | is it running |
| `sudo -u manteion tail -n 50 /home/manteion/storage/logs/laravel-$(date +%F).log` | the application's log |
| `journalctl -u manteion-queue -u manteion-reverb -e` | the queue worker and Reverb |
| `/var/log/httpd/manteion.no-*_log` | Apache's log of the site |
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
