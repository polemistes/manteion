---
paths:
  - resources/js/components/shell/PageContainer.vue
---

# Shell

## Desktop content starts beside the sidebar, it is not centred
From lg the PageContainer is left-aligned (`lg:ml-0`) with a 40px gutter, 56px from 2xl, and its max-widths grow by 16px at 2xl to keep the content measure. The owner found the centred column ugly on wide screens (a 280px void between sidebar and page at 1920px, 470px at 2560px), so do not bring `mx-auto` back for desktop. Phones and tablets stay centred. Page↔aside gaps are `xl:gap-10`, the settings nav↔panel gap `md:gap-10`.
