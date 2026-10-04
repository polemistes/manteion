<script setup lang="ts">
import { CircleAlert } from '@lucide/vue';
import type { Component } from 'vue';
import { computed, useId } from 'vue';
import { useI18n } from 'vue-i18n';

defineOptions({ inheritAttrs: false });

/**
 * A labelled text input. Works with v-model or, inside an Inertia <Form>,
 * uncontrolled with a `name` and an optional `defaultValue` — either way it
 * keeps what was typed across re-renders (it never binds a bare :value).
 * Other attributes (name, autocomplete, required …) go to the <input>.
 */
const props = withDefaults(
    defineProps<{
        label?: string;
        id?: string;
        type?: string;
        hint?: string;
        error?: string;
        optional?: boolean;
        icon?: Component;
        headline?: boolean;
        defaultValue?: string | number | null;
        hideLabel?: boolean;
    }>(),
    {
        label: undefined,
        id: undefined,
        type: 'text',
        hint: undefined,
        error: undefined,
        optional: false,
        icon: undefined,
        headline: false,
        defaultValue: undefined,
        hideLabel: false,
    },
);

const model = defineModel<string | number | null>();

if (model.value === undefined && props.defaultValue !== undefined) {
    model.value = props.defaultValue;
}

const { t } = useI18n();
const autoId = useId();
const inputId = computed(() => props.id ?? autoId);
const describedBy = computed(
    () =>
        [
            props.hint ? `${inputId.value}-hint` : null,
            props.error ? `${inputId.value}-error` : null,
        ]
            .filter(Boolean)
            .join(' ') || undefined,
);
</script>

<template>
    <div class="flex flex-col gap-1.5">
        <label
            v-if="props.label"
            :for="inputId"
            class="text-label font-semibold text-ink"
            :class="{ 'sr-only': props.hideLabel }"
        >
            {{ props.label }}
            <span v-if="props.optional" class="font-normal text-ink-muted"
                >({{ t('ui.optional') }})</span
            >
        </label>
        <div class="relative">
            <component
                :is="props.icon"
                v-if="props.icon"
                :size="18"
                :stroke-width="1.9"
                class="pointer-events-none absolute top-1/2 left-3.5 -translate-y-1/2 text-ink-muted"
                aria-hidden="true"
            />
            <input
                :id="inputId"
                v-model="model"
                :type="props.type"
                :aria-invalid="props.error ? 'true' : undefined"
                :aria-describedby="describedBy"
                v-bind="$attrs"
                class="w-full rounded-md bg-surface px-3.5 text-ink ring-1 ring-control transition-shadow duration-120 ring-inset hover:ring-ink-subtle focus:ring-2 focus:ring-ink focus:outline-none disabled:cursor-not-allowed disabled:bg-surface-2 disabled:text-ink-subtle disabled:ring-line"
                :class="[
                    props.headline
                        ? 'h-14 font-display text-title-3'
                        : 'h-12 text-input',
                    props.icon ? 'pl-10' : '',
                    props.error ? 'ring-2 ring-danger hover:ring-danger' : '',
                ]"
            />
            <slot name="end" />
        </div>
        <p
            v-if="props.hint"
            :id="`${inputId}-hint`"
            class="text-meta text-ink-muted"
        >
            {{ props.hint }}
        </p>
        <p
            v-if="props.error"
            :id="`${inputId}-error`"
            class="flex items-center gap-1.5 text-meta font-medium text-danger"
        >
            <CircleAlert :size="16" :stroke-width="2" aria-hidden="true" />
            {{ props.error }}
        </p>
    </div>
</template>
