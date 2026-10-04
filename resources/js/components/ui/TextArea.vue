<script setup lang="ts">
import { CircleAlert } from '@lucide/vue';
import { computed, nextTick, onMounted, ref, useId, watch } from 'vue';
import { useI18n } from 'vue-i18n';

defineOptions({ inheritAttrs: false });

/**
 * A labelled textarea that grows with its text up to `maxRows`, then
 * scrolls. Same v-model / uncontrolled behaviour as TextField.
 */
const props = withDefaults(
    defineProps<{
        label?: string;
        id?: string;
        hint?: string;
        error?: string;
        optional?: boolean;
        defaultValue?: string | null;
        maxRows?: number;
        hideLabel?: boolean;
        bare?: boolean;
    }>(),
    {
        label: undefined,
        id: undefined,
        hint: undefined,
        error: undefined,
        optional: false,
        defaultValue: undefined,
        maxRows: 12,
        hideLabel: false,
        bare: false,
    },
);

const model = defineModel<string | null>();

if (model.value === undefined && props.defaultValue !== undefined) {
    model.value = props.defaultValue;
}

const { t } = useI18n();
const autoId = useId();
const inputId = computed(() => props.id ?? autoId);
const element = ref<HTMLTextAreaElement | null>(null);
const needsFallback = ref(false);

const describedBy = computed(
    () =>
        [
            props.hint ? `${inputId.value}-hint` : null,
            props.error ? `${inputId.value}-error` : null,
        ]
            .filter(Boolean)
            .join(' ') || undefined,
);

/** For browsers without `field-sizing: content`: size to the content. */
function resize(): void {
    const el = element.value;

    if (!needsFallback.value || !el) {
        return;
    }

    el.style.height = 'auto';
    el.style.height = `${el.scrollHeight + 2}px`;
}

onMounted(() => {
    needsFallback.value = !CSS.supports('field-sizing', 'content');
    resize();
});

watch(model, () => nextTick(resize));

defineExpose({
    focus: () => element.value?.focus(),
    element,
});
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
        <textarea
            :id="inputId"
            ref="element"
            v-model="model"
            :aria-invalid="props.error ? 'true' : undefined"
            :aria-describedby="describedBy"
            v-bind="$attrs"
            class="w-full resize-none text-input text-ink [field-sizing:content] focus:outline-none"
            :class="[
                props.bare
                    ? 'bg-transparent'
                    : 'min-h-30 rounded-md bg-surface px-3.5 py-3 ring-1 ring-control transition-shadow duration-120 ring-inset hover:ring-ink-subtle focus:ring-2 focus:ring-ink',
                props.error ? 'ring-2 ring-danger' : '',
            ]"
            :style="{
                maxHeight: `calc(var(--text-input--line-height) * ${props.maxRows} + 1.5rem)`,
            }"
            @input="resize"
        ></textarea>
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
