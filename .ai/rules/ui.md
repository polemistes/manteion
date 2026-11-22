---
paths:
  - resources/js/components/ui/Mark.vue
---

# Ui

## The mark is the owner's drawing, a picture: regenerate it with mobile-assets/make-web-mark.py
Since 2026-11-22 the tripod mark is the owner's coloured drawing (source outside the repo, ~/manteion-logo/logo_bilde2.png), not a one-colour SVG, so skins no longer colour it and `--color-mark`/`text-mark` are gone. `python3 mobile-assets/make-web-mark.py <drawing.png>` cuts it out of its paper and writes resources/images/mark.webp, mark-dark.webp (ink drawn light; Mark.vue shows it under html[data-mode='dark']), the favicon (only the top of the tripod: the whole one is a smudge at 16px), public/icons/* and the inline logo in public/offline.html. Mark.vue is an inline block of fixed shape (233:480): give it a height and `w-auto`. Its lines are fine, so it is shown tall (56px beside the name, 51px in the 56px phone top bar, where the owner asked for it to reach almost down to the content). On the front page from 640 it stands in the line of text as the M of "Manteion": outer legs on the baseline, the middle leg 12.7% of its height below (negative bottom margin), which relies on an inline block without line boxes having its bottom margin edge as baseline; do not turn that wordmark into a flex row. The Android and iOS app icons and splash screens still carry the earlier logo (mobile-assets/logo.png, generated once with @capacitor/assets): changing them needs a new app release.
