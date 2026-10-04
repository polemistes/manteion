<script setup lang="ts">
import { ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import AppDialog from '@/components/ui/AppDialog.vue';
import Button from '@/components/ui/Button.vue';
import type { CategoryOption, ContactOption, GroupOption } from '@/events';
import { useDates } from '@/lib/dates';
import EventFiltersForm from './EventFiltersForm.vue';
import type { EventFilterState } from './EventFiltersForm.vue';
import { emptyState } from './filters';

/** The filters in a sheet (phone, tablet): changes apply with "Show events". */
const props = withDefaults(
    defineProps<{
        state: EventFilterState;
        categories: CategoryOption[];
        friends: ContactOption[];
        following: ContactOption[];
        friendGroups: GroupOption[];
        followingGroups: GroupOption[];
        withPlaceAndDates?: boolean;
    }>(),
    { withPlaceAndDates: true },
);

const emit = defineEmits<{ apply: [state: EventFilterState] }>();

const open = defineModel<boolean>('open', { default: false });

const { t } = useI18n();
const { todayKey } = useDates();

const draft = ref<EventFilterState>({ ...props.state });

watch(open, (isOpen) => {
    if (isOpen) {
        draft.value = { ...props.state };
    }
});

function apply(): void {
    open.value = false;
    emit('apply', draft.value);
}

function clear(): void {
    draft.value = emptyState(todayKey());
}
</script>

<template>
    <AppDialog v-model:open="open" :title="t('events.filters.title')" size="lg">
        <EventFiltersForm
            v-model="draft"
            :categories="props.categories"
            :friends="props.friends"
            :following="props.following"
            :friend-groups="props.friendGroups"
            :following-groups="props.followingGroups"
            :with-place-and-dates="props.withPlaceAndDates"
        />
        <template #actions>
            <Button variant="primary" @click="apply">
                {{ t('events.filters.show') }}
            </Button>
            <Button variant="ghost" @click="clear">
                {{ t('events.filters.clear') }}
            </Button>
        </template>
    </AppDialog>
</template>
