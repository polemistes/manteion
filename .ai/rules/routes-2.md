---
paths:
  - routes/web.php
---

# Routes 2

## /app/ belongs to Reverb
Apache proxies /app/ to Reverb's WebSocket server (deploy/apache/manteion.no-ssl.conf), so no page may live at /app or under it; the Android download page is /android for that reason. /.well-known/assetlinks.json is served by Laravel (AndroidAssetLinksController) from ANDROID_APP_CERT_SHA256 and 404s without it.

## /icons/ is Apache's unless the site takes it back
Arch's httpd.conf includes conf/extra/httpd-autoindex.conf, whose `Alias /icons/ "/usr/share/httpd/icons/"` applies to every site: public/icons (home-screen icons, the push icon and badge, the apple-touch icon) answered 404 on www.manteion.no from the move to this server (2026-10-06) until it was noticed on 2026-11-22. deploy/apache/manteion.no-ssl.conf therefore carries its own `Alias /icons/ /home/manteion/app/public/icons/` (a site's alias is matched before the server-wide one). After changing anything under public/icons, check one address from outside: a 404 page titled "Object not found!" is Apache's alias, not Laravel.
