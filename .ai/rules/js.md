---
paths:
  - 'resources/js/**'
---

# Js

## Editable fields need v-model with local state, never a bare :value binding
Vue re-applies the `value` prop on every re-render even when it did not change (patchElement special-cases the key `value`). So an `<input :value="props.user.name">` inside a component that has any other reactive state is wiped back to the prop as soon as that state changes (a checkbox toggle, a v-if switch, a usePoll refresh). This silently lost typed profile edits and leaflet text. Pattern: `const name = ref(props.user.name)` + `v-model="name"`, and reset the refs yourself in an `@success` handler when the form uses reset-on-success. `:value` with an `@input`/`@change` handler that writes the same ref back (a controlled input) is fine, as are hidden inputs.
