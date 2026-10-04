<script setup lang="ts">
import { RadioGroupItem, RadioGroupRoot } from 'reka-ui';
import type { Component } from 'vue';

export interface OptionCard {
    value: string;
    title: string;
    description?: string;
    icon?: Component;
}

/**
 * A radio group drawn as cards (title, description, a radio at the end).
 * With `name` it submits inside an Inertia <Form>.
 */
const props = withDefaults(
    defineProps<{
        options: OptionCard[];
        name?: string;
        label?: string;
        disabled?: boolean;
    }>(),
    { name: undefined, label: undefined, disabled: false },
);

const model = defineModel<string>();
</script>

<template>
    <RadioGroupRoot
        v-model="model"
        :name="props.name"
        :disabled="props.disabled"
        :aria-label="props.label"
        class="flex flex-col gap-2"
    >
        <RadioGroupItem
            v-for="option in props.options"
            :key="option.value"
            :value="option.value"
            class="group flex min-h-16 w-full items-center gap-3.5 rounded-md bg-surface px-3.5 py-2.5 text-left ring-1 ring-line-strong transition-shadow duration-120 ring-inset hover:bg-surface-2/55 disabled:cursor-not-allowed disabled:opacity-45 data-[state=checked]:ring-2 data-[state=checked]:ring-ink"
        >
            <component
                :is="option.icon"
                v-if="option.icon"
                :size="20"
                :stroke-width="1.8"
                class="shrink-0 text-ink-muted"
                aria-hidden="true"
            />
            <span class="min-w-0 flex-1">
                <span class="block font-semibold text-ink">{{
                    option.title
                }}</span>
                <span
                    v-if="option.description"
                    class="mt-0.5 block text-meta text-ink-muted"
                    >{{ option.description }}</span
                >
            </span>
            <span
                class="grid size-[22px] shrink-0 place-items-center rounded-full shadow-[inset_0_0_0_1.5px_var(--color-control)] group-data-[state=checked]:shadow-[inset_0_0_0_2px_var(--color-ink)]"
                aria-hidden="true"
            >
                <span
                    class="size-2.5 rounded-full bg-ink opacity-0 transition-opacity duration-120 group-data-[state=checked]:opacity-100"
                />
            </span>
        </RadioGroupItem>
    </RadioGroupRoot>
</template>
