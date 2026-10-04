<script setup lang="ts">
import { Primitive } from 'reka-ui';
import type { Component } from 'vue';

/**
 * A flat surface with a hairline border. An `interactive` card is a link as
 * a whole: give the title link `after:absolute after:inset-0` (the
 * `card-link` class below does it) and put buttons inside at `relative z-10`.
 */
const props = withDefaults(
    defineProps<{
        as?: string | Component;
        interactive?: boolean;
        tone?: 'default' | 'invite';
    }>(),
    { as: 'div', interactive: false, tone: 'default' },
);
</script>

<template>
    <Primitive
        :as="props.as"
        class="overflow-hidden rounded-lg border bg-surface"
        :class="[
            props.tone === 'invite' ? 'border-accent-line' : 'border-line',
            props.interactive
                ? 'relative transition-colors duration-120 hover:border-line-strong has-[.card-link:focus-visible]:outline-2 has-[.card-link:focus-visible]:outline-offset-2 has-[.card-link:focus-visible]:outline-focus'
                : '',
        ]"
    >
        <slot />
    </Primitive>
</template>
