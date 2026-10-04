<script setup lang="ts">
import { Eye, EyeOff, Users } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import AppDialog from '@/components/ui/AppDialog.vue';
import Button from '@/components/ui/Button.vue';
import OptionCards from '@/components/ui/OptionCards.vue';
import type { AttendanceVisibility } from '@/events';

/** "Who can see your answer?" — three option cards; Done saves. */
const props = defineProps<{
    host: string;
    visibility: AttendanceVisibility;
}>();

const emit = defineEmits<{ save: [visibility: AttendanceVisibility] }>();

const open = defineModel<boolean>('open', { default: false });

const { t } = useI18n();

const choice = ref<string>(props.visibility);

watch(open, (isOpen) => {
    if (isOpen) {
        choice.value = props.visibility;
    }
});

const options = computed(() => [
    {
        value: 'everyone',
        title: t('events.rsvp.everyone'),
        description: t('events.rsvp.everyoneHint', { host: props.host }),
        icon: Eye,
    },
    {
        value: 'friends',
        title: t('events.rsvp.friends'),
        description: t('events.rsvp.friendsHint'),
        icon: Users,
    },
    {
        value: 'none',
        title: t('events.rsvp.none'),
        description: t('events.rsvp.noneHint', { host: props.host }),
        icon: EyeOff,
    },
]);

function done(): void {
    open.value = false;

    if (choice.value !== props.visibility) {
        emit('save', choice.value as AttendanceVisibility);
    }
}
</script>

<template>
    <AppDialog
        v-model:open="open"
        :title="t('events.rsvp.visibilityTitle')"
        :description="t('events.rsvp.hostAlways', { host: props.host })"
    >
        <OptionCards
            v-model="choice"
            :options="options"
            :label="t('events.rsvp.visibilityTitle')"
        />
        <template #actions>
            <Button variant="primary" @click="done">
                {{ t('events.rsvp.done') }}
            </Button>
            <Button variant="ghost" @click="open = false">
                {{ t('common.cancel') }}
            </Button>
        </template>
    </AppDialog>
</template>
