---
paths:
  - routes/web.php
---

# Routes 2

## /app/ belongs to Reverb
Apache proxies /app/ to Reverb's WebSocket server (deploy/apache/manteion.no-ssl.conf), so no page may live at /app or under it; the Android download page is /android for that reason. /.well-known/assetlinks.json is served by Laravel (AndroidAssetLinksController) from ANDROID_APP_CERT_SHA256 and 404s without it.
