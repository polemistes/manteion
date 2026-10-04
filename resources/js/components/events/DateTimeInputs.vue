<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { CircleAlert } from '@lucide/vue';
import { computed, ref, useId } from 'vue';
import { useI18n } from 'vue-i18n';
import { utcToZoned, zonedToUtc } from '@/lib/dates';

/**
 * A date and a time, as the phone's own pickers, read as wall-clock time
 * in the app's display time zone and submitted as a UTC instant in a
 * hidden input named `name`.
 */
const props = withDefaults(
    defineProps<{
        name: string;
        label: string;
        defaultValue?: string | null;
        defaultTime?: string;
        required?: boolean;
        optional?: boolean;
        error?: string;
    }>(),
    {
        defaultValue: null,
        defaultTime: '18:00',
        required: false,
        optional: false,
        error: undefined,
    },
);

const { t } = useI18n();
const page = usePage();
const id = useId();

const timeZone = computed(
    () => (page.props.timezone as string | undefined) || 'Europe/Oslo',
);

const initial = props.defaultValue
    ? utcToZoned(props.defaultValue, timeZone.value)
    : null;

const day = ref(initial?.day ?? '');
const time = ref(initial?.time ?? (props.required ? props.defaultTime : ''));

const value = computed(() => {
    if (!day.value) {
        return '';
    }

    return zonedToUtc(
        day.value,
        time.value || props.defaultTime,
        timeZone.value,
    ).toISOString();
});
</script>

<template>
    <fieldset class="flex flex-col gap-1.5">
        <legend class="mb-1.5 text-label font-semibold text-ink">
            {{ props.label }}
            <span v-if="props.optional" class="font-normal text-ink-muted"
                >({{ t('ui.optional') }})</span
            >
        </legend>
        <input type="hidden" :name="props.name" :value="value" />
        <div class="grid grid-cols-[1.6fr_1fr] gap-2">
            <input
                :id="`${id}-day`"
                v-model="day"
                type="date"
                :required="props.required"
                :aria-label="props.label"
                :aria-invalid="props.error ? 'true' : undefined"
                class="h-12 w-full rounded-md bg-surface px-3.5 text-input text-ink ring-1 ring-control ring-inset hover:ring-ink-subtle focus:ring-2 focus:ring-ink focus:outline-none"
                :class="props.error ? 'ring-2 ring-danger' : ''"
            />
            <input
                :id="`${id}-time`"
                v-model="time"
                type="time"
                :required="props.required"
                :aria-label="`${props.label} – ${t('events.form.time')}`"
                class="h-12 w-full rounded-md bg-surface px-3.5 text-input text-ink ring-1 ring-control ring-inset hover:ring-ink-subtle focus:ring-2 focus:ring-ink focus:outline-none"
            />
        </div>
        <p
            v-if="props.error"
            class="flex items-center gap-1.5 text-meta font-medium text-danger"
        >
            <CircleAlert :size="16" :stroke-width="2" aria-hidden="true" />
            {{ props.error }}
        </p>
    </fieldset>
</template>
