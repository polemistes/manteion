import { ref } from 'vue';

/**
 * Whether the alpha notice was dismissed on this page. Shared by the strip
 * and the sidebar card, and only ever set in the browser (the server always
 * starts from the cookie).
 */
export const alphaNoticeDismissed = ref(false);
