---
paths:
  - 'tests/**'
---

# Tests

## No GD extension — use UploadedFile::fake()->create(), not ->image()
This environment has no PHP GD extension installed (only `php` + `php-sqlite` packages). `UploadedFile::fake()->image(...)` requires GD and throws "GD extension is not installed." Use `UploadedFile::fake()->create('photo.jpg', $kilobytes, 'image/jpeg')` instead — it passes Laravel's `image`/`mimes` validation rules fine without GD since Laravel trusts the declared fake mime type for these test doubles.

## GD extension is now installed — real fake images are available
`php-gd` was installed 2026-08 (previously absent, which is why `UploadedFile::fake()->create('photo.jpg', $kb, 'image/jpeg')` was the documented workaround for image uploads in tests — that call produces placeholder bytes, not a real decodable image).

GD is now present, so `UploadedFile::fake()->image('photo.jpg')` works and should be preferred for any test whose upload ends up passed through `App\Services\ImageProcessor` (avatar and event-photo uploads) — that service decodes the file with `imagecreatefromjpeg`/`imagecreatefrompng`/`imagecreatefromwebp` to strip EXIF, and `->create()`'s placeholder bytes fail to decode (`gd-jpeg: ... Empty input file`), producing a 500 rather than a normal validation failure.

`->create()` is still fine for anything that only needs to satisfy Laravel's `image`/`mimes` validation without being actually decoded.
