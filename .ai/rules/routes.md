---
paths:
  - 'resources/js/routes/**'
---

# Routes

## Wayfinder sub-resource routes need a default import, not a named one
For a nested resource like `admin/users/{user}/role`, Wayfinder generates `resources/js/routes/admin/users/role/index.ts` with `export default role`, but does NOT re-export `role` as a named export from `admin/users/index.ts` (only its own direct actions like `index`/`suspend` are named exports there). Import sub-resources as default imports from their own subpath instead: `import adminUsersRole from '@/routes/admin/users/role'`, not `import { role } from '@/routes/admin/users'`. `vue-tsc` catches the mismatch with TS2614.
