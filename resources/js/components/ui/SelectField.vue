<script setup lang="ts">
import { Check, ChevronDown, CircleAlert } from '@lucide/vue';
import {
    SelectContent,
    SelectItem,
    SelectItemIndicator,
    SelectItemText,
    SelectPortal,
    SelectRoot,
    SelectTrigger,
    SelectValue,
    SelectViewport,
} from 'reka-ui';
import { computed, useId } from 'vue';

export interface SelectOption {
    value: string;
    label: string;
    disabled?: boolean;
}

/**
 * A labelled select in the field style. With `name` it also submits inside
 * an Inertia <Form> (Reka renders a hidden native select). `compact` is
 * the 40px pill for selects in list rows (its hit area is still 44).
 */
const props = withDefaults(
    defineProps<{
        options: SelectOption[];
        label?: string;
        id?: string;
        name?: string;
        placeholder?: string;
        hint?: string;
        error?: string;
        disabled?: boolean;
        defaultValue?: string;
        hideLabel?: boolean;
        compact?: boolean;
    }>(),
    {
        label: undefined,
        id: undefined,
        name: undefined,
        placeholder: undefined,
        hint: undefined,
        error: undefined,
        disabled: false,
        defaultValue: undefined,
        hideLabel: false,
        compact: false,
    },
);

const model = defineModel<string>();

if (model.value === undefined && props.defaultValue !== undefined) {
    model.value = props.defaultValue;
}

const autoId = useId();
const triggerId = computed(() => props.id ?? autoId);
</script>

<template>
    <div class="flex flex-col gap-1.5">
        <label
            v-if="props.label"
            :for="triggerId"
            class="text-label font-semibold text-ink"
            :class="{ 'sr-only': props.hideLabel }"
        >
            {{ props.label }}
        </label>
        <SelectRoot
            v-model="model"
            :name="props.name"
            :disabled="props.disabled"
        >
            <SelectTrigger
                :id="triggerId"
                :aria-invalid="props.error ? 'true' : undefined"
                class="relative flex w-full items-center justify-between gap-2 bg-surface text-left text-ink ring-1 ring-inset hover:ring-ink-subtle focus:outline-none focus-visible:ring-2 focus-visible:ring-ink disabled:cursor-not-allowed disabled:bg-surface-2 disabled:text-ink-subtle data-[placeholder]:text-ink-subtle"
                :class="[
                    props.compact
                        ? 'h-10 rounded-full pr-3 pl-4 text-label font-medium ring-line-strong after:absolute after:inset-x-0 after:-inset-y-0.5'
                        : 'h-12 rounded-md px-3.5 text-input ring-control',
                    props.error ? 'ring-2 ring-danger' : '',
                ]"
            >
                <SelectValue :placeholder="props.placeholder" class="truncate" />
                <ChevronDown
                    :size="18"
                    :stroke-width="1.9"
                    class="shrink-0 text-ink-muted"
                    aria-hidden="true"
                />
            </SelectTrigger>
            <SelectPortal>
                <SelectContent
                    position="popper"
                    :side-offset="4"
                    class="z-50 max-h-[min(360px,var(--reka-select-content-available-height))] min-w-[var(--reka-select-trigger-width)] overflow-hidden rounded-md bg-surface p-1.5 shadow-lg ring-1 ring-line"
                >
                    <SelectViewport>
                        <SelectItem
                            v-for="option in props.options"
                            :key="option.value"
                            :value="option.value"
                            :disabled="option.disabled"
                            class="relative flex h-11 cursor-default items-center gap-3 rounded-sm px-3 text-body text-ink outline-none select-none data-[disabled]:opacity-45 data-[highlighted]:bg-surface-2"
                        >
                            <SelectItemText>{{ option.label }}</SelectItemText>
                            <SelectItemIndicator class="ml-auto">
                                <Check
                                    :size="18"
                                    :stroke-width="1.9"
                                    aria-hidden="true"
                                />
                            </SelectItemIndicator>
                        </SelectItem>
                    </SelectViewport>
                </SelectContent>
            </SelectPortal>
        </SelectRoot>
        <p v-if="props.hint" class="text-meta text-ink-muted">
            {{ props.hint }}
        </p>
        <p
            v-if="props.error"
            class="flex items-center gap-1.5 text-meta font-medium text-danger"
        >
            <CircleAlert :size="16" :stroke-width="2" aria-hidden="true" />
            {{ props.error }}
        </p>
    </div>
</template>
