<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import CalendarPicker from '@/components/CalendarPicker.vue';

const props = defineProps<{
    label: string;
    modelValue: string | null;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: string | null];
}>();

const { t, locale } = useI18n();

const dateFnsLocales: Record<string, string> = {
    en: 'en-GB',
    nb: 'nb-NO',
};

const isOpen = ref(false);
const root = ref<HTMLDivElement | null>(null);

function isoToDate(iso: string): Date {
    const [year, month, day] = iso.split('-').map(Number);

    return new Date(year, month - 1, day);
}

function dateToIso(date: Date): string {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
}

function isSameDay(a: Date, b: Date): boolean {
    return (
        a.getFullYear() === b.getFullYear() &&
        a.getMonth() === b.getMonth() &&
        a.getDate() === b.getDate()
    );
}

const selectedDate = computed(() =>
    props.modelValue ? isoToDate(props.modelValue) : null,
);

const displayLabel = computed(() => {
    if (!selectedDate.value) {
        return t('calendar.noDateSelected');
    }

    const now = new Date();

    if (isSameDay(selectedDate.value, now)) {
        return t('events.index.today');
    }

    const tomorrow = new Date(now);
    tomorrow.setDate(tomorrow.getDate() + 1);

    if (isSameDay(selectedDate.value, tomorrow)) {
        return t('events.index.tomorrow');
    }

    const options: Intl.DateTimeFormatOptions = {
        weekday: 'short',
        day: 'numeric',
        month: 'short',
    };

    if (selectedDate.value.getFullYear() !== now.getFullYear()) {
        options.year = 'numeric';
    }

    return selectedDate.value.toLocaleDateString(
        dateFnsLocales[locale.value] ?? locale.value,
        options,
    );
});

function select(date: Date): void {
    emit('update:modelValue', dateToIso(date));
    isOpen.value = false;
}

function clear(): void {
    emit('update:modelValue', null);
    isOpen.value = false;
}

function toggleOpen(): void {
    isOpen.value = !isOpen.value;
}

function onDocumentClick(event: MouseEvent): void {
    if (
        isOpen.value &&
        root.value &&
        !root.value.contains(event.target as Node)
    ) {
        isOpen.value = false;
    }
}

onMounted(() => document.addEventListener('click', onDocumentClick));
onUnmounted(() => document.removeEventListener('click', onDocumentClick));
</script>

<template>
    <div ref="root" class="relative">
        <p class="text-xs mb-1 font-bold">{{ props.label }}</p>
        <button
            type="button"
            class="border-edge bg-surface-raised text-sm border px-2 py-1"
            @click="toggleOpen"
        >
            {{ displayLabel }}
        </button>

        <div
            v-if="isOpen"
            class="border-edge bg-surface-raised absolute z-20 mt-1 w-fit border p-2"
        >
            <CalendarPicker
                :model-value="selectedDate"
                @update:model-value="select"
            />
            <button
                v-if="props.modelValue"
                type="button"
                class="border-edge text-xs mt-2 w-full border px-2 py-1 hover:bg-accent hover:text-on-accent"
                @click="clear"
            >
                {{ t('calendar.clearDate') }}
            </button>
        </div>
    </div>
</template>
