---
paths:
  - 'app/Http/Controllers/*ImageController.php'
  - app/Http/Controllers/PictureController.php
  - app/Http/Controllers/Concerns/ServesPrivateImages.php
  - app/Models/EventImage.php
  - app/Models/Leaflet.php
  - app/Models/Message.php
  - app/Models/Picture.php
  - app/Models/Concerns/HasPictures.php
  - app/Http/Requests/Concerns/ValidatesPictures.php
  - app/Services/ImageProcessor.php
  - app/Services/FacebookEventImporter.php
  - app/Services/TimeZones.php
  - app/Services/EventStartingPoints.php
---

# Http Controllers

## Event, leaflet, comment and chat pictures are private: never on the public disk
Everything under /storage (the public disk) opens for anyone with the link, so only avatars live there. Event covers and the pictures on leaflets, messages and event comments are stored on ImageProcessor::PRIVATE_DISK (`local`, whose built-in route refuses unsigned requests) and reach a page only through events.images.show (covers) and pictures.show (everything else). Each authorises `view` on what the picture belongs to (the event; the leaflet, the message's conversation, the comment's event) and answers through ServesPrivateImages (`Cache-Control: private, max-age=1y, immutable`). That is safe because the address names the file: a new picture gets a new random name, never a rewrite in place. Build addresses with EventImage::presentation() and Picture::presentation() (via HasPictures::picturesPresentation()), never Storage::url().

## A message, a leaflet and an event comment carry up to ten pictures, in one table
Since 2026-11-22 they share `pictures` (App\Models\Picture, trait App\Models\Concerns\HasPictures: pictures(), addPictures(), deletePictures(), picturesPresentation()). A picture belongs to exactly one owner through three nullable foreign keys (message_id, leaflet_id, event_comment_id), not a polymorphic pair, so the database deletes its row with its owner whichever way the owner goes. Each has a smaller copy (thumb_path, 640px, null when the picture is small itself) that grids show; one picture alone is shown from the full file. Requests take `pictures[]` (Concerns\ValidatesPictures, Picture::MAX_PER_POST = 10); the browser shrinks each to 1600px and under 800 KB first, because production PHP takes 10 MB a request (deploy/php-fpm). A leaflet has pictures or a link, never both. Leaflet replies have no pictures. An event's cover is still an EventImage.

## Files go when their owner goes
Foreign keys cascade rows, not files. Deleting a Picture through its model deletes its files (and deleting a message's, leaflet's or comment's model deletes its pictures first, HasPictures), but a row the database removes in a cascade leaves its files behind: whoever deletes owners in bulk calls Picture::forgetFiles($query) first. That is EventController::destroy (the comments on the event), User::deleteUploadedFiles() (its leaflets, messages and comments, and other people's comments on its events) and Organizer::deleteUploadedFiles(); EventImage::deleteFile() does the same for covers. Extend those when a new kind of upload or a new cascade appears.

## Facebook import reads the crawler page, and only ever asks Facebook
"Copy from a Facebook event" (events.import.facebook, FacebookImport.vue on the create page) fills the new-event form; it saves nothing. Facebook serves a public event's full page to an honest bot user agent (ManteionBot); a browser-like user agent gets 400, and a private or removed event gets a sign-in page (imported as null → 404). The details are in the page's `<script type="application/json">` blocks, spread over several objects carrying the event's id; the page also embeds suggested events, so always anchor on the id, and the end time sits in an id-less object matched by its start_timestamp (0 = no end). Never request anything but Facebook hosts: links naming an id are fetched at www.facebook.com/events/{id}/, short links (fb.me/e/…, /share/…) are followed hop by hop with redirects off, and the cover is taken only from https *.fbcdn.net and handed to the browser as a data: URL (og:image's lookaside URL returns HTML, not a picture). The format is Facebook's to change: checked against live pages 2026-11-16.

## Two time zones: the viewer's for reading, the event's for typing and "local time"
Instants are stored in UTC. A person reads them in their own zone: users.timezone, reported by the device itself (lib/timeZone.ts → time-zone.update on app start when it differs from the shared `timezone` prop, then a reload), so the server render, "today" and day filters agree with the browser; never convert with the browser's zone directly. An event has its own zone (events.timezone, Event::localTimeZone()): the one at its pin (TimeZones::forPlace: the country's zone, or in a country with several the nearest reference city, no border map), else its host's when made. The event form types times as local to the place (DateTimeInputs takes the zone; moving the pin keeps the clock time and moves the instant) and sends `timezone`. Display is the viewer's time first, plus dates.elsewhere() ("20:00 local time", the LocalTime chip) only when the two clocks differ at that moment; compare clocks with sameClock(), never zone names (Oslo and Amsterdam are the same time). An event without an end is over at the end of its day in the event's zone, on both server and client.

## Event templates are copies with clock times; a new event starts from one, or from an earlier event
An event template (App\Models\EventTemplate, /events/templates, owner asked for it 2026-11-22) is its own row, never a pointer to an event: all the event form's fields except the day, with `starts_time`/`ends_time` ("19:00") and `ends_days_later` in the template's zone, both optional. One with an `organizer_id` is shared by that organizer's spokespersons (EventTemplate::isUsableBy mirrors Event::isHostedBy; a spokesperson who leaves hands theirs to the main one). The new-event page takes `?template=` or `?from=` (an event the user hosts) and gets `startFrom` (EventStartingPoints): the form opens with the times filled in and the day empty (DateTimeInputs `presetTime`/`presetDaysLater`), so the host must pick a day. Covers are copied as files on the server, never re-uploaded through the cropper (a weekly event would be re-encoded every week): the form sends `cover_from` ("template:5", "event:12"), EventController::copyCoverTo authorises and copies it unless a file was uploaded. A template's cover is columns on the template (image_path…), served by event-templates.cover; extend User/Organizer::deleteUploadedFiles when that changes. Templates are edited with EventForm in `template` mode (TemplateTimeInputs instead of the date pickers). The zone is only carried over when the pin or the host's choice decided it (EventStartingPoints::formValues).
