<script setup lang="ts">
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import CalendarPicker from '@/components/CalendarPicker.vue';

const { t, locale } = useI18n();

const dateFnsLocales: Record<string, string> = {
    en: 'en-GB',
    nb: 'nb-NO',
};

const props = withDefaults(
    defineProps<{
        name: string;
        initialValue?: string | null;
        required?: boolean;
    }>(),
    { initialValue: null, required: false },
);

function parseInitial(value: string | null): {
    date: Date | null;
    time: string;
} {
    if (!value) {
        return { date: null, time: '18:00' };
    }

    const parsed = new Date(value);

    if (Number.isNaN(parsed.getTime())) {
        return { date: null, time: '18:00' };
    }

    const hours = String(parsed.getHours()).padStart(2, '0');
    const minutes = String(parsed.getMinutes()).padStart(2, '0');

    return { date: parsed, time: `${hours}:${minutes}` };
}

const initial = parseInitial(props.initialValue);
const selectedDate = ref<Date | null>(initial.date);
const timeValue = ref<string>(initial.time);

const isoValue = computed<string>(() => {
    if (!selectedDate.value || !timeValue.value) {
        return '';
    }

    const [hours, minutes] = timeValue.value.split(':').map(Number);
    const combined = new Date(selectedDate.value);
    combined.setHours(hours, minutes, 0, 0);

    return combined.toISOString();
});

const displayDate = computed(() =>
    selectedDate.value
        ? selectedDate.value.toLocaleDateString(
              dateFnsLocales[locale.value] ?? locale.value,
              { dateStyle: 'full' },
          )
        : t('calendar.noDateSelected'),
);

function clear(): void {
    selectedDate.value = null;
}
</script>

<template>
    <div>
        <input type="hidden" :name="props.name" :value="isoValue" />
        <div class="flex flex-wrap items-start gap-4">
            <CalendarPicker v-model="selectedDate" />
            <div class="flex flex-col gap-2">
                <p class="text-sm">{{ displayDate }}</p>
                <div>
                    <label class="mb-1 block text-xs font-bold">{{
                        t('calendar.time')
                    }}</label>
                    <input
                        v-model="timeValue"
                        type="time"
                        class="border border-edge bg-surface-raised px-2 py-1 font-sans text-sm focus:ring-1 focus:ring-accent focus:outline-none"
                    />
                </div>
                <button
                    v-if="!props.required && selectedDate"
                    type="button"
                    class="w-fit text-xs underline"
                    @click="clear"
                >
                    {{ t('calendar.clearDate') }}
                </button>
            </div>
        </div>
    </div>
</template>
