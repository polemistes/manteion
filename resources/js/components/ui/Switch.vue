<script setup lang="ts">
import { Check } from '@lucide/vue';
import { SwitchRoot, SwitchThumb } from 'reka-ui';

/**
 * On/off switch. "On" is ink with a check in the thumb, never colour alone.
 * Put it at the end of a SwitchRow so the whole row toggles it.
 */
const props = withDefaults(
    defineProps<{
        id?: string;
        name?: string;
        disabled?: boolean;
        label?: string;
    }>(),
    { id: undefined, name: undefined, disabled: false, label: undefined },
);

const model = defineModel<boolean>({ default: false });
</script>

<template>
    <SwitchRoot
        :id="props.id"
        v-model="model"
        :name="props.name"
        :disabled="props.disabled"
        :aria-label="props.label"
        class="relative inline-flex h-8 w-13 shrink-0 items-center rounded-full bg-surface-2 shadow-[inset_0_0_0_1.5px_var(--color-control)] transition-colors duration-200 ease-enter disabled:cursor-not-allowed disabled:opacity-45 data-[state=checked]:bg-ink data-[state=checked]:shadow-none"
    >
        <SwitchThumb
            class="grid size-4 translate-x-2 place-items-center rounded-full bg-control transition-all duration-200 ease-enter data-[state=checked]:size-6 data-[state=checked]:translate-x-6 data-[state=checked]:bg-surface data-[state=checked]:shadow-xs"
        >
            <Check
                v-if="model"
                :size="14"
                :stroke-width="3"
                class="text-ink"
                aria-hidden="true"
            />
        </SwitchThumb>
    </SwitchRoot>
</template>
