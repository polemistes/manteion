<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import type { Component } from 'vue';

export interface SegmentLink {
    href: string;
    label: string;
    icon?: Component;
    active: boolean;
    iconOnly?: boolean;
}

/** The segmented look for navigation between views (List / Calendar). */
const props = defineProps<{
    links: SegmentLink[];
    label: string;
}>();
</script>

<template>
    <nav
        :aria-label="props.label"
        class="inline-flex shrink-0 gap-0.5 rounded-full bg-surface-2 p-[3px]"
    >
        <Link
            v-for="link in props.links"
            :key="link.href"
            :href="link.href"
            :aria-current="link.active ? 'page' : undefined"
            :aria-label="link.iconOnly ? link.label : undefined"
            :title="link.iconOnly ? link.label : undefined"
            class="relative inline-flex h-[38px] items-center justify-center gap-1.5 rounded-full text-label font-semibold whitespace-nowrap transition-[background-color,color,box-shadow] duration-200 after:absolute after:inset-x-0 after:-inset-y-[3px]"
            :class="[
                link.active
                    ? 'bg-surface text-ink shadow-sm'
                    : 'text-ink-muted hover:text-ink',
                link.iconOnly ? 'w-11' : 'px-3.5',
            ]"
        >
            <component
                :is="link.icon"
                v-if="link.icon"
                :size="link.iconOnly ? 20 : 16"
                :stroke-width="link.iconOnly ? 1.8 : 2"
                aria-hidden="true"
            />
            <span v-if="!link.iconOnly">{{ link.label }}</span>
        </Link>
    </nav>
</template>
