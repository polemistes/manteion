<script setup lang="ts">
import { Primitive } from 'reka-ui';
import type { Component } from 'vue';
import { computed } from 'vue';
import CountBadge from './CountBadge.vue';

/**
 * A round, icon-only button with a 44px target. `label` is required: it is
 * the accessible name. `create` is the phone top bar's terracotta "+".
 */
const props = withDefaults(
    defineProps<{
        label: string;
        icon: Component;
        as?: string | Component;
        variant?: 'default' | 'muted' | 'create' | 'floating';
        size?: 'md' | 'sm';
        badge?: number;
        badgeLabel?: string;
        type?: 'button' | 'submit' | 'reset';
        disabled?: boolean;
        pressed?: boolean;
    }>(),
    {
        as: 'button',
        variant: 'default',
        size: 'md',
        badge: 0,
        badgeLabel: undefined,
        type: 'button',
        disabled: false,
        pressed: undefined,
    },
);

const isButton = computed(() => props.as === 'button');

const variants = {
    default: 'text-ink hover:bg-surface-2 active:bg-surface-3',
    muted: 'text-ink-muted hover:bg-surface-2 hover:text-ink',
    create: 'group',
    floating: 'bg-surface/92 text-ink shadow-sm hover:bg-surface',
};
</script>

<template>
    <Primitive
        :as="props.as"
        :type="isButton ? props.type : undefined"
        :disabled="isButton ? props.disabled : undefined"
        :aria-label="props.label"
        :aria-pressed="props.pressed"
        :title="props.label"
        class="grid shrink-0 place-items-center rounded-full transition-[background-color,color,transform] duration-120 active:scale-[.98] disabled:pointer-events-none disabled:opacity-45"
        :class="[
            props.size === 'sm'
                ? 'size-9 text-ink-muted hover:bg-surface-2 hover:text-ink'
                : 'size-11',
            props.size === 'sm' ? '' : variants[props.variant],
        ]"
    >
        <!-- Positioning lives on this inner box, so callers may place the
             button itself with absolute or fixed. -->
        <span
            class="relative grid size-full place-items-center"
            :class="props.size === 'sm' ? 'after:absolute after:-inset-1' : ''"
        >
            <span
                v-if="props.variant === 'create'"
                class="grid size-9 place-items-center rounded-full bg-accent text-on-accent transition-colors duration-120 group-hover:bg-accent-hover"
            >
                <component
                    :is="props.icon"
                    :size="20"
                    :stroke-width="2.25"
                    aria-hidden="true"
                />
            </span>
            <component
                :is="props.icon"
                v-else
                :size="props.size === 'sm' ? 18 : 22"
                :stroke-width="props.size === 'sm' ? 1.9 : 1.75"
                aria-hidden="true"
            />
            <CountBadge
                :count="props.badge"
                :label="props.badgeLabel"
                ring
                class="absolute top-1 right-[3px]"
            />
        </span>
    </Primitive>
</template>
