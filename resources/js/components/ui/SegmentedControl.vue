<script setup lang="ts">
import { ToggleGroupItem, ToggleGroupRoot } from 'reka-ui';
import type { Component } from 'vue';

export interface Segment {
    value: string;
    label: string;
    count?: number;
    icon?: Component;
    iconOnly?: boolean;
}

/**
 * Two or three mutually exclusive options on a soft track (Going / Maybe /
 * Invited, All / Unread / Groups). Always keeps one selected. For more
 * options, or labels too long in Norwegian, use FilterChips.
 */
const props = defineProps<{
    segments: Segment[];
    label: string;
}>();

const model = defineModel<string>({ required: true });

function select(value: unknown): void {
    if (typeof value === 'string' && value !== '') {
        model.value = value;
    }
}
</script>

<template>
    <ToggleGroupRoot
        type="single"
        :model-value="model"
        :aria-label="props.label"
        class="inline-flex max-w-full gap-0.5 rounded-full bg-surface-2 p-[3px]"
        @update:model-value="select"
    >
        <ToggleGroupItem
            v-for="segment in props.segments"
            :key="segment.value"
            :value="segment.value"
            :aria-label="segment.iconOnly ? segment.label : undefined"
            :title="segment.iconOnly ? segment.label : undefined"
            class="group relative inline-flex h-[38px] items-center justify-center gap-1.5 rounded-full text-label font-semibold whitespace-nowrap text-ink-muted transition-[background-color,color,box-shadow] duration-200 after:absolute after:inset-x-0 after:-inset-y-[3px] hover:text-ink data-[state=on]:bg-surface data-[state=on]:text-ink data-[state=on]:shadow-sm"
            :class="segment.iconOnly ? 'w-11' : 'px-3.5'"
        >
            <component
                :is="segment.icon"
                v-if="segment.icon"
                :size="segment.iconOnly ? 20 : 16"
                :stroke-width="segment.iconOnly ? 1.8 : 2"
                aria-hidden="true"
            />
            <span v-if="!segment.iconOnly">{{ segment.label }}</span>
            <span
                v-if="segment.count !== undefined"
                class="inline-flex h-[18px] min-w-[18px] items-center justify-center rounded-full bg-surface-3 px-[5px] text-kicker leading-[18px] tracking-normal text-ink tabular-nums group-data-[state=on]:bg-surface-2"
            >
                {{ segment.count }}
            </span>
        </ToggleGroupItem>
    </ToggleGroupRoot>
</template>
