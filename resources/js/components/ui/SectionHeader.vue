<script setup lang="ts">
import CountBadge from './CountBadge.vue';

/**
 * A feed section title in the serif, with an optional note and count,
 * followed by a hairline to the end of the row. `kicker` gives the small
 * caps label used to group settings instead.
 */
const props = withDefaults(
    defineProps<{
        title: string;
        meta?: string;
        count?: number;
        kicker?: boolean;
        as?: string;
    }>(),
    { meta: undefined, count: 0, kicker: false, as: 'h2' },
);
</script>

<template>
    <component
        :is="props.as"
        v-if="props.kicker"
        class="mt-7 mb-2.5 text-kicker text-ink-muted uppercase"
    >
        {{ props.title }}
    </component>
    <div v-else class="mt-7 mb-3 flex items-center gap-3 sm:mt-8 sm:mb-3.5">
        <component
            :is="props.as"
            class="flex min-w-0 items-baseline gap-2 font-display text-title-3 text-pretty"
        >
            <span>{{ props.title }}</span>
            <span
                v-if="props.meta"
                class="font-sans text-meta font-normal text-ink-muted"
                >{{ props.meta }}</span
            >
        </component>
        <CountBadge :count="props.count" />
        <span class="h-px min-w-6 flex-1 bg-line" aria-hidden="true" />
        <slot name="end" />
    </div>
</template>
