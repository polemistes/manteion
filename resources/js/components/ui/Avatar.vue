<script setup lang="ts">
import { computed, ref, watch } from 'vue';

/**
 * A round photo, or two initials in the serif when there is none (or it
 * fails to load). A plain <img> so the server-rendered markup matches the
 * browser's: no initials flash while a cached photo loads. Small faces,
 * and `single` ones (partly covered, as in a group's face), get one.
 */
const props = withDefaults(
    defineProps<{
        name: string;
        src?: string | null;
        size?: number;
        ring?: boolean;
        decorative?: boolean;
        single?: boolean;
    }>(),
    { src: null, size: 40, ring: false, decorative: false, single: false },
);

const failed = ref(false);

watch(
    () => props.src,
    () => {
        failed.value = false;
    },
);

const initials = computed(() => {
    const words = props.name
        .replace(/^@/, '')
        .trim()
        .split(/\s+/)
        .filter(Boolean);
    const first = words[0]?.[0] ?? '?';
    const last = words.length > 1 ? (words[words.length - 1][0] ?? '') : '';

    // Two letters don't fit a small face (stacks, seen-by): one does.
    return (props.single || props.size < 32 ? first : first + last).toUpperCase();
});

const style = computed(() => ({
    width: `${props.size}px`,
    height: `${props.size}px`,
    fontSize: `${Math.round(props.size * 0.4)}px`,
}));
</script>

<template>
    <img
        v-if="props.src && !failed"
        :src="props.src"
        :alt="props.decorative ? '' : props.name"
        :style="style"
        loading="lazy"
        decoding="async"
        class="inline-block shrink-0 rounded-full bg-surface-2 object-cover"
        :class="
            props.ring ? 'ring-2 ring-[var(--avatar-ring,var(--color-surface))]' : ''
        "
        @error="failed = true"
    />
    <span
        v-else
        :style="style"
        :role="props.decorative ? undefined : 'img'"
        :aria-label="props.decorative ? undefined : props.name"
        :aria-hidden="props.decorative ? 'true' : undefined"
        class="inline-grid shrink-0 place-items-center rounded-full bg-surface-3 font-display leading-none font-medium text-ink-muted select-none"
        :class="
            props.ring ? 'ring-2 ring-[var(--avatar-ring,var(--color-surface))]' : ''
        "
    >
        {{ initials }}
    </span>
</template>
