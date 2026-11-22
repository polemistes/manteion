<script setup lang="ts">
import { CircleAlert } from '@lucide/vue';
import { computed, ref, useId } from 'vue';
import { useI18n } from 'vue-i18n';

/**
 * A template's usual times: clock times with no day, both optional (a
 * template whose events start at a different time each time has none).
 * They are submitted as `starts_time`, `ends_time` and `ends_days_later`:
 * an end that is not later on the clock than the start falls on the next
 * day, and a template made from an event that ran over several days keeps
 * that span until the host types another end.
 */
const props = withDefaults(
    defineProps<{
        startsTime?: string | null;
        endsTime?: string | null;
        endsDaysLater?: number;
        error?: string;
    }>(),
    {
        startsTime: null,
        endsTime: null,
        endsDaysLater: 0,
        error: undefined,
    },
);

const { t } = useI18n();
const id = useId();

const start = ref(props.startsTime ?? '');
const end = ref(props.endsTime ?? '');
// The span the template came with, kept while its end is not retyped.
const keptDays = ref(props.endsTime ? props.endsDaysLater : 0);

const daysLater = computed(() => {
    if (!start.value || !end.value) {
        return 0;
    }

    return Math.max(keptDays.value, end.value > start.value ? 0 : 1);
});

function onStartInput(event: Event): void {
    start.value = (event.target as HTMLInputElement).value;

    // An end has nothing to follow without a start.
    if (!start.value) {
        end.value = '';
        keptDays.value = 0;
    }
}

function onEndInput(event: Event): void {
    end.value = (event.target as HTMLInputElement).value;
    keptDays.value = 0;
}

function clear(): void {
    start.value = '';
    end.value = '';
    keptDays.value = 0;
}

const inputClass =
    'h-12 w-full rounded-md bg-surface px-3.5 text-input text-ink ring-1 ring-control ring-inset hover:ring-ink-subtle focus:ring-2 focus:ring-ink focus:outline-none disabled:opacity-50';
</script>

<template>
    <fieldset class="flex flex-col gap-1.5">
        <legend
            class="mb-1.5 flex w-full items-center gap-2 text-label font-semibold text-ink"
        >
            <span class="min-w-0 flex-1">
                {{ t('events.templates.form.times') }}
                <span class="font-normal text-ink-muted"
                    >({{ t('ui.optional') }})</span
                >
            </span>
            <button
                v-if="start || end"
                type="button"
                class="relative shrink-0 font-semibold text-ink underline underline-offset-2 after:absolute after:-inset-x-2 after:-inset-y-3"
                :aria-label="
                    t('events.form.clearTimeOf', {
                        field: t('events.templates.form.times'),
                    })
                "
                @click="clear"
            >
                {{ t('events.form.clearTime') }}
            </button>
        </legend>
        <input type="hidden" name="ends_days_later" :value="daysLater" />
        <div class="grid grid-cols-2 gap-2">
            <label :for="`${id}-start`" class="flex flex-col gap-1">
                <span class="text-meta text-ink-muted">{{
                    t('events.form.starts')
                }}</span>
                <input
                    :id="`${id}-start`"
                    :value="start"
                    type="time"
                    name="starts_time"
                    :aria-invalid="props.error ? 'true' : undefined"
                    :class="[
                        inputClass,
                        props.error ? 'ring-2 ring-danger' : '',
                    ]"
                    @input="onStartInput"
                />
            </label>
            <label :for="`${id}-end`" class="flex flex-col gap-1">
                <span class="text-meta text-ink-muted">{{
                    t('events.form.ends')
                }}</span>
                <input
                    :id="`${id}-end`"
                    :value="end"
                    type="time"
                    name="ends_time"
                    :disabled="!start"
                    :class="inputClass"
                    @input="onEndInput"
                />
            </label>
        </div>
        <p
            v-if="props.error"
            class="flex items-center gap-1.5 text-meta font-medium text-danger"
        >
            <CircleAlert :size="16" :stroke-width="2" aria-hidden="true" />
            {{ props.error }}
        </p>
        <p class="text-meta text-ink-muted" role="status">
            <template v-if="daysLater > 0">
                {{ t('events.templates.form.endsDaysLater', daysLater) }}
            </template>
            {{ t('events.templates.form.timesHint') }}
        </p>
    </fieldset>
</template>
