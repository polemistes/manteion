import { nextTick, onMounted, watch } from 'vue';
import type { Ref, WatchSource } from 'vue';

/**
 * Keeps the pressed chip of a sideways-scrolling chip row in view — a phone
 * shows only the first few, so a page opened on the Requests tab would
 * otherwise hide which one is on. On mount, and whenever `source` changes.
 */
export function usePressedChipInView(
    row: Ref<HTMLElement | null>,
    source: WatchSource,
): void {
    function reveal(): void {
        row.value
            ?.querySelector<HTMLElement>('[aria-pressed="true"]')
            ?.scrollIntoView({ block: 'nearest', inline: 'nearest' });
    }

    onMounted(reveal);
    watch(source, () => nextTick(reveal));
}
