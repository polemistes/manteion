<script setup lang="ts">
import { useId } from 'vue';
import Switch from './Switch.vue';

/**
 * A full-width row with a title, an optional description and a switch at
 * the end. The whole row is the target (a <label> for the switch).
 */
const props = withDefaults(
    defineProps<{
        title: string;
        description?: string;
        name?: string;
        disabled?: boolean;
        divider?: boolean;
    }>(),
    { description: undefined, name: undefined, disabled: false, divider: true },
);

const model = defineModel<boolean>({ default: false });
const id = useId();
</script>

<template>
    <label
        :for="id"
        class="group/row relative flex min-h-15 items-center gap-3.5 px-4 py-3 transition-colors duration-120"
        :class="
            props.disabled
                ? 'cursor-not-allowed opacity-45'
                : 'cursor-pointer hover:bg-surface-2/55'
        "
    >
        <span
            v-if="props.divider"
            class="pointer-events-none absolute top-0 right-0 left-4 h-px bg-line group-first/row:hidden"
            aria-hidden="true"
        />
        <span class="min-w-0 flex-1">
            <span class="block font-medium text-ink">{{ props.title }}</span>
            <span
                v-if="props.description"
                class="mt-0.5 block text-meta text-ink-muted"
                >{{ props.description }}</span
            >
        </span>
        <Switch
            :id="id"
            v-model="model"
            :name="props.name"
            :disabled="props.disabled"
        />
    </label>
</template>
