<script setup lang="ts">
import type { Component } from 'vue';

/** A calm, centred "nothing here yet" with one way forward. */
const props = withDefaults(
    defineProps<{
        icon?: Component;
        title: string;
        body?: string;
    }>(),
    { icon: undefined, body: undefined },
);
</script>

<template>
    <div class="flex flex-col items-center gap-2 px-6 py-10 text-center">
        <span
            v-if="props.icon"
            class="mb-1 grid size-14 place-items-center rounded-full bg-surface-2 text-ink-muted"
        >
            <component
                :is="props.icon"
                :size="24"
                :stroke-width="1.75"
                aria-hidden="true"
            />
        </span>
        <p class="font-display text-title-3 text-pretty">{{ props.title }}</p>
        <p v-if="props.body" class="max-w-[36ch] text-ink-muted">
            {{ props.body }}
        </p>
        <div v-if="$slots.default" class="mt-3">
            <slot />
        </div>
    </div>
</template>
