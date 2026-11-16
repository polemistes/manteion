---
paths:
  - 'app/Http/Controllers/*ImageController.php'
  - app/Http/Controllers/Concerns/ServesPrivateImages.php
  - app/Models/EventImage.php
  - app/Models/Leaflet.php
  - app/Models/Message.php
  - app/Services/ImageProcessor.php
  - app/Services/FacebookEventImporter.php
---

# Http Controllers

## Event, leaflet and chat pictures are private: never on the public disk
Everything under /storage (the public disk) opens for anyone with the link, so only avatars live there. Event, leaflet and message pictures are stored on ImageProcessor::PRIVATE_DISK (`local`, whose built-in route refuses unsigned requests) and reach a page only through events.images.show, leaflets.image and messages.messages.image. Each of those authorises `view` on what the picture belongs to and answers through ServesPrivateImages (`Cache-Control: private, max-age=1y, immutable`). That is safe because the address names the file: a new picture gets a new random name, never a rewrite in place. Build addresses with EventImage::presentation(), Leaflet::imageUrl() and Message::imagePresentation(), never Storage::url().

## Files go when their owner goes
Foreign keys cascade rows, not files. Whoever deletes a picture's row deletes its file first: EventImage::deleteFile(), Leaflet::deleteImageFile(), Message::deleteImageFile(). Deleting an account (by the user or an admin) calls User::deleteUploadedFiles(), and deleting an organizer calls Organizer::deleteUploadedFiles(); extend those when a new kind of upload appears.

## Facebook import reads the crawler page, and only ever asks Facebook
"Copy from a Facebook event" (events.import.facebook, FacebookImport.vue on the create page) fills the new-event form; it saves nothing. Facebook serves a public event's full page to an honest bot user agent (ManteionBot); a browser-like user agent gets 400, and a private or removed event gets a sign-in page (imported as null → 404). The details are in the page's `<script type="application/json">` blocks, spread over several objects carrying the event's id; the page also embeds suggested events, so always anchor on the id, and the end time sits in an id-less object matched by its start_timestamp (0 = no end). Never request anything but Facebook hosts: links naming an id are fetched at www.facebook.com/events/{id}/, short links (fb.me/e/…, /share/…) are followed hop by hop with redirects off, and the cover is taken only from https *.fbcdn.net and handed to the browser as a data: URL (og:image's lookaside URL returns HTML, not a picture). The format is Facebook's to change: checked against live pages 2026-11-16.
