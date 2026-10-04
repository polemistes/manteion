<script setup lang="ts">
/**
 * A group of settings: a small caps label, the rows in a card, and an
 * optional note under it.
 */
const props = withDefaults(
    defineProps<{
        label?: string;
        note?: string;
        /** Rows straight in the card (switch rows, list rows), or padded content. */
        flush?: boolean;
    }>(),
    { label: undefined, note: undefined, flush: true },
);
</script>

<template>
    <section>
        <h3
            v-if="props.label"
            class="mb-2.5 text-kicker text-ink-muted uppercase"
        >
            {{ props.label }}
        </h3>
        <div
            class="overflow-hidden rounded-lg border border-line bg-surface"
            :class="props.flush ? '' : 'p-4 sm:p-5'"
        >
            <slot />
        </div>
        <p v-if="props.note" class="mt-2 px-0.5 text-meta text-ink-muted">
            {{ props.note }}
        </p>
    </section>
</template>
