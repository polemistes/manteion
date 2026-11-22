<script setup lang="ts">
import { Check, CircleQuestionMark, X } from '@lucide/vue';
import { RadioGroupItem, RadioGroupRoot } from 'reka-ui';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import type { AttendanceStatus } from '@/events';

/**
 * Going / Maybe / Can't go as three pills that save on tap. Your "Going"
 * is terracotta; a chosen Maybe or Can't go is ringed in ink. In a narrow
 * card the icons drop out so the words never truncate.
 */
const props = withDefaults(
    defineProps<{
        disabled?: boolean;
        label?: string;
    }>(),
    { disabled: false, label: undefined },
);

const model = defineModel<AttendanceStatus | null>({ default: null });

const { t } = useI18n();

const options = computed(() => [
    {
        value: 'attending' as const,
        label: t('events.rsvp.going'),
        icon: Check,
        checked:
            'data-[state=checked]:bg-accent data-[state=checked]:text-on-accent data-[state=checked]:ring-0',
    },
    {
        value: 'interested' as const,
        label: t('events.rsvp.maybe'),
        icon: CircleQuestionMark,
        checked:
            'data-[state=checked]:bg-surface-2 data-[state=checked]:ring-2 data-[state=checked]:ring-ink',
    },
    {
        value: 'not_attending' as const,
        label: t('events.rsvp.cantGo'),
        icon: X,
        checked:
            'data-[state=checked]:bg-surface-2 data-[state=checked]:ring-2 data-[state=checked]:ring-ink',
    },
]);

const value = computed({
    get: () =>
        model.value && model.value !== 'no_response' ? model.value : undefined,
    set: (next: string | undefined) => {
        if (next) {
            model.value = next as AttendanceStatus;
        }
    },
});
</script>

<template>
    <RadioGroupRoot
        v-model="value"
        orientation="horizontal"
        :disabled="props.disabled"
        :aria-label="props.label ?? t('events.rsvp.label')"
        class="@container/rsvp flex w-full gap-1.5"
    >
        <RadioGroupItem
            v-for="option in options"
            :key="option.value"
            :value="option.value"
            class="relative flex h-11 min-w-max flex-1 items-center justify-center gap-1.5 rounded-full bg-surface px-3 text-label font-semibold text-ink ring-1 ring-line-strong transition-[background-color,color,box-shadow] duration-200 ring-inset hover:bg-surface-2 active:scale-[.98] disabled:opacity-45 @max-[18.5rem]/rsvp:px-2"
            :class="option.checked"
        >
            <component
                :is="option.icon"
                :size="16"
                :stroke-width="2"
                class="shrink-0 @max-[18.5rem]/rsvp:hidden"
                aria-hidden="true"
            />
            {{ option.label }}
        </RadioGroupItem>
    </RadioGroupRoot>
</template>
