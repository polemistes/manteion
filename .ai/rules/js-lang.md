---
paths:
  - 'resources/js/lang/**'
---

# Js Lang

## vue-i18n message syntax: escape @ and |
In vue-i18n messages `@` starts a linked message and `|` separates plural forms, so "@{username}" throws "Invalid linked format" at render time (a page error, not a build error). Write a literal at sign as {'@'} ("{'@'}{username}") and a literal pipe as {'|'}. Plural strings use "zero | one | many" or "one | many" with {n}, called as t(key, count) or t(key, { ...named }, count).
