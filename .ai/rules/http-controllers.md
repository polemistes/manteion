---
paths:
  - 'app/Http/Controllers/*ImageController.php'
  - 'app/Http/Controllers/Concerns/ServesPrivateImages.php'
  - 'app/Models/EventImage.php'
  - 'app/Models/Leaflet.php'
  - 'app/Models/Message.php'
  - 'app/Services/ImageProcessor.php'
---

# Http Controllers

## Event, leaflet and chat pictures are private: never on the public disk
Everything under /storage (the public disk) opens for anyone with the link, so only avatars live there. Event, leaflet and message pictures are stored on ImageProcessor::PRIVATE_DISK (`local`, whose built-in route refuses unsigned requests) and reach a page only through events.images.show, leaflets.image and messages.messages.image. Each of those authorises `view` on what the picture belongs to and answers through ServesPrivateImages (`Cache-Control: private, max-age=1y, immutable`). That is safe because the address names the file: a new picture gets a new random name, never a rewrite in place. Build addresses with EventImage::presentation(), Leaflet::imageUrl() and Message::imagePresentation(), never Storage::url().

## Files go when their owner goes
Foreign keys cascade rows, not files. Whoever deletes a picture's row deletes its file first: EventImage::deleteFile(), Leaflet::deleteImageFile(), Message::deleteImageFile(). Deleting an account (by the user or an admin) calls User::deleteUploadedFiles(), and deleting an organizer calls Organizer::deleteUploadedFiles(); extend those when a new kind of upload appears.
