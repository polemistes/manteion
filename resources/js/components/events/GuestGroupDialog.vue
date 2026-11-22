<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Users } from '@lucide/vue';
import { ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import AppDialog from '@/components/ui/AppDialog.vue';
import Button from '@/components/ui/Button.vue';
import TextField from '@/components/ui/TextField.vue';
import { toast } from '@/lib/toast';
import eventGuestGroup from '@/routes/events/guest-group';

/**
 * Keeps an event's guests as a group of friends, for its host to invite
 * all at once another time (the invite dialog offers the groups). Only
 * the guests who are the host's friends go in: `count` of them.
 */
const props = defineProps<{
    eventId: number;
    eventName: string;
    count: number;
}>();

const open = defineModel<boolean>('open', { default: false });

const { t } = useI18n();

const name = ref(props.eventName);
const error = ref<string | undefined>(undefined);
const saving = ref(false);

watch(open, (isOpen) => {
    if (isOpen) {
        name.value = props.eventName;
        error.value = undefined;
    }
});

function save(): void {
    saving.value = true;
    router.post(
        eventGuestGroup.store(props.eventId).url,
        { name: name.value.trim() },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                open.value = false;
                toast({ title: t('events.show.guestGroupSaved'), icon: Users });
            },
            onError: (errors) => {
                error.value = errors.name;
            },
            onFinish: () => {
                saving.value = false;
            },
        },
    );
}
</script>

<template>
    <AppDialog
        v-model:open="open"
        :title="t('events.show.guestGroupTitle')"
        :description="`${t('events.show.guestGroupCount', props.count)} ${t('events.show.guestGroupHint')}`"
    >
        <form id="guest-group" @submit.prevent="save">
            <TextField
                v-model="name"
                :label="t('events.show.guestGroupName')"
                :error="error"
                required
                maxlength="255"
            />
        </form>
        <template #actions>
            <Button
                type="submit"
                form="guest-group"
                variant="primary"
                :disabled="saving || !name.trim()"
            >
                {{ t('events.show.guestGroupSave') }}
            </Button>
            <Button variant="ghost" @click="open = false">
                {{ t('common.cancel') }}
            </Button>
        </template>
    </AppDialog>
</template>
