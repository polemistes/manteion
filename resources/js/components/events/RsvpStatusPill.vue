<script setup lang="ts">
import { Check, ChevronDown, CircleQuestionMark, Eye, X } from '@lucide/vue';
import {
    DropdownMenuItemIndicator,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
} from 'reka-ui';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import Menu from '@/components/ui/Menu.vue';
import MenuItem from '@/components/ui/MenuItem.vue';
import MenuSeparator from '@/components/ui/MenuSeparator.vue';
import type { AttendanceStatus } from '@/events';

/**
 * An answered event's status ("✓ Going ▾"): opens a menu with the three
 * answers and "Who can see my answer…". Unanswered it reads "Answer" in
 * terracotta, because it needs you.
 */
defineOptions({ inheritAttrs: false });

const emit = defineEmits<{ visibility: [] }>();

const model = defineModel<AttendanceStatus | null>({ default: null });

const { t } = useI18n();

const answered = computed(
    () => model.value !== null && model.value !== 'no_response',
);

const look = computed(() => {
    switch (model.value) {
        case 'attending':
            return {
                label: t('events.rsvp.going'),
                icon: Check,
                classes: 'bg-accent-soft text-accent-ink',
            };
        case 'interested':
            return {
                label: t('events.rsvp.maybe'),
                icon: CircleQuestionMark,
                classes: 'bg-surface-2 text-ink',
            };
        case 'not_attending':
            return {
                label: t('events.rsvp.cantGo'),
                icon: X,
                classes: 'bg-surface-2 text-ink',
            };
        default:
            return {
                label: t('events.rsvp.answer'),
                icon: null,
                classes: 'bg-accent-soft text-accent-ink',
            };
    }
});

const radioValue = computed({
    get: () => (answered.value ? (model.value as string) : ''),
    set: (next: string) => {
        if (next) {
            model.value = next as AttendanceStatus;
        }
    },
});

const items = computed(() => [
    { value: 'attending', label: t('events.rsvp.going'), icon: Check },
    {
        value: 'interested',
        label: t('events.rsvp.maybe'),
        icon: CircleQuestionMark,
    },
    { value: 'not_attending', label: t('events.rsvp.cantGo'), icon: X },
]);
</script>

<template>
    <Menu align="end">
        <template #trigger>
            <button
                type="button"
                v-bind="$attrs"
                class="relative inline-flex h-9 shrink-0 items-center gap-1.5 rounded-full pr-2.5 pl-3 text-label font-semibold transition-colors duration-120 after:absolute after:inset-x-0 after:-inset-y-1"
                :class="look.classes"
            >
                <component
                    :is="look.icon"
                    v-if="look.icon"
                    :size="16"
                    :stroke-width="2"
                    :class="model === 'attending' ? '' : 'text-ink-muted'"
                    aria-hidden="true"
                />
                {{ look.label }}
                <ChevronDown :size="16" :stroke-width="2" aria-hidden="true" />
            </button>
        </template>
        <DropdownMenuRadioGroup v-model="radioValue">
            <DropdownMenuRadioItem
                v-for="item in items"
                :key="item.value"
                :value="item.value"
                class="flex h-11 w-full cursor-default items-center gap-3 rounded-sm px-3 text-body text-ink outline-none select-none data-[highlighted]:bg-surface-2"
            >
                <component
                    :is="item.icon"
                    :size="18"
                    :stroke-width="1.9"
                    class="text-ink-muted"
                    aria-hidden="true"
                />
                <span class="flex-1">{{ item.label }}</span>
                <DropdownMenuItemIndicator>
                    <Check :size="18" :stroke-width="1.9" aria-hidden="true" />
                </DropdownMenuItemIndicator>
            </DropdownMenuRadioItem>
        </DropdownMenuRadioGroup>
        <MenuSeparator />
        <MenuItem :icon="Eye" @select="emit('visibility')">{{
            t('events.rsvp.whoCanSee')
        }}</MenuItem>
    </Menu>
</template>
