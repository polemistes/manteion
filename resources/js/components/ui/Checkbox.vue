<script setup lang="ts">
import { Check } from '@lucide/vue';
import { CheckboxIndicator, CheckboxRoot } from 'reka-ui';

/** A 22px checkbox; checked is ink with a paper-coloured check. */
const props = withDefaults(
    defineProps<{
        id?: string;
        name?: string;
        value?: string;
        disabled?: boolean;
        label?: string;
    }>(),
    {
        id: undefined,
        name: undefined,
        value: 'on',
        disabled: false,
        label: undefined,
    },
);

const model = defineModel<boolean>({ default: false });
</script>

<template>
    <CheckboxRoot
        :id="props.id"
        v-model="model"
        :name="props.name"
        :value="props.value"
        :disabled="props.disabled"
        :aria-label="props.label"
        class="relative grid size-[22px] shrink-0 place-items-center rounded-xs bg-surface shadow-[inset_0_0_0_1.5px_var(--color-control)] transition-colors duration-120 after:absolute after:-inset-[11px] disabled:cursor-not-allowed disabled:opacity-45 data-[state=checked]:bg-ink data-[state=checked]:shadow-none"
    >
        <CheckboxIndicator class="grid place-items-center text-canvas">
            <Check :size="16" :stroke-width="3" aria-hidden="true" />
        </CheckboxIndicator>
    </CheckboxRoot>
</template>
