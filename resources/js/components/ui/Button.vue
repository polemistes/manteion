<script setup lang="ts">
import { Primitive } from 'reka-ui';
import type { Component } from 'vue';
import { computed } from 'vue';

/**
 * Pill button. `primary` (terracotta) is for the one main action in a view;
 * everything else is `secondary`. Pass an Inertia `Link` as `as` to render a
 * link that looks like a button.
 */
const props = withDefaults(
    defineProps<{
        as?: string | Component;
        variant?: 'primary' | 'secondary' | 'ghost' | 'danger';
        size?: 'sm' | 'md' | 'lg';
        icon?: Component;
        iconEnd?: Component;
        block?: boolean;
        type?: 'button' | 'submit' | 'reset';
        disabled?: boolean;
    }>(),
    {
        as: 'button',
        variant: 'secondary',
        size: 'md',
        icon: undefined,
        iconEnd: undefined,
        block: false,
        type: 'button',
        disabled: false,
    },
);

const sizes = {
    sm: 'h-9 gap-1.5 px-3.5 text-label after:absolute after:inset-x-0 after:-inset-y-1',
    md: 'h-11 gap-2 px-4.5 text-body',
    lg: 'h-13 gap-2 px-6 text-input',
};

const variants = {
    primary: 'bg-accent text-on-accent hover:bg-accent-hover',
    secondary:
        'bg-surface text-ink ring-1 ring-inset ring-line-strong hover:bg-surface-2 active:bg-surface-3',
    ghost: 'text-ink-muted hover:bg-surface-2 hover:text-ink',
    danger: 'bg-danger-soft text-danger hover:ring-1 hover:ring-inset hover:ring-danger/35',
};

const iconSizes = { sm: 16, md: 18, lg: 20 };
const iconStrokes = { sm: 2, md: 1.9, lg: 1.8 };

const isButton = computed(() => props.as === 'button');
</script>

<template>
    <Primitive
        :as="props.as"
        :type="isButton ? props.type : undefined"
        :disabled="isButton ? props.disabled : undefined"
        :aria-disabled="!isButton && props.disabled ? 'true' : undefined"
        class="relative inline-flex shrink-0 items-center justify-center rounded-full font-semibold whitespace-nowrap transition-[background-color,color,box-shadow,transform] duration-120 select-none active:scale-[.98] disabled:pointer-events-none disabled:opacity-45 aria-disabled:pointer-events-none aria-disabled:opacity-45"
        :class="[
            sizes[props.size],
            variants[props.variant],
            props.variant === 'ghost' ? 'px-3' : '',
            props.block ? 'w-full' : '',
        ]"
    >
        <component
            :is="props.icon"
            v-if="props.icon"
            :size="iconSizes[props.size]"
            :stroke-width="iconStrokes[props.size]"
            class="-ml-0.5 shrink-0"
            aria-hidden="true"
        />
        <slot />
        <component
            :is="props.iconEnd"
            v-if="props.iconEnd"
            :size="iconSizes[props.size]"
            :stroke-width="iconStrokes[props.size]"
            class="-mr-0.5 shrink-0"
            aria-hidden="true"
        />
    </Primitive>
</template>
