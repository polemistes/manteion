---
paths:
  - database/factories/UserFactory.php
---

# Factories

## Username factory value must satisfy alpha_dash
User validation (CreateNewUser, UpdateUserProfileInformation) requires `username` to pass `alpha_dash`, which rejects dots. Faker's `userName()` sometimes returns dotted values like "first.last", which caused a real intermittent test failure (profile update silently failed validation ~1 in 5 runs). The factory replaces dots with underscores: `Str::of(fake()->unique()->userName())->replace('.', '_')`. If you regenerate or change this factory field, keep it alpha_dash-safe or the same flake returns.
