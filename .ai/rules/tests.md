---
paths:
  - 'tests/**'
---

# Tests

## No GD extension — use UploadedFile::fake()->create(), not ->image()
This environment has no PHP GD extension installed (only `php` + `php-sqlite` packages). `UploadedFile::fake()->image(...)` requires GD and throws "GD extension is not installed." Use `UploadedFile::fake()->create('photo.jpg', $kilobytes, 'image/jpeg')` instead — it passes Laravel's `image`/`mimes` validation rules fine without GD since Laravel trusts the declared fake mime type for these test doubles.
