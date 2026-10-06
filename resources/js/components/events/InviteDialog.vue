<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { UserPlus } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import AppDialog from '@/components/ui/AppDialog.vue';
import Avatar from '@/components/ui/Avatar.vue';
import Button from '@/components/ui/Button.vue';
import Checkbox from '@/components/ui/Checkbox.vue';
import SearchField from '@/components/ui/SearchField.vue';
import { toast } from '@/lib/toast';
import { store as storeInvitations } from '@/routes/events/invitations';

interface Person {
    id: number;
    name: string;
    username: string;
    avatar_url: string | null;
}

/**
 * Pick friends to invite; they can answer straight away. For a spokesperson
 * of the hosting organizer the organizer's followers are offered too.
 */
const props = withDefaults(
    defineProps<{
        eventId: number;
        candidates: Person[];
        organizerName?: string | null;
    }>(),
    { organizerName: null },
);

const open = defineModel<boolean>('open', { default: false });

const { t } = useI18n();

const query = ref('');
const selected = ref<number[]>([]);
const sending = ref(false);

watch(open, (isOpen) => {
    if (isOpen) {
        selected.value = [];
        query.value = '';
    }
});

const shown = computed(() => {
    const term = query.value.trim().toLocaleLowerCase();

    return props.candidates.filter(
        (person) =>
            term === '' ||
            person.name.toLocaleLowerCase().includes(term) ||
            person.username.toLocaleLowerCase().includes(term),
    );
});

function toggle(id: number, on: boolean): void {
    selected.value = on
        ? [...selected.value, id]
        : selected.value.filter((other) => other !== id);
}

function send(): void {
    sending.value = true;
    router.post(
        storeInvitations(props.eventId).url,
        { user_ids: selected.value },
        {
            preserveScroll: true,
            onSuccess: () => {
                open.value = false;
                toast({
                    title: t('events.show.invitationsSent'),
                    icon: UserPlus,
                });
            },
            onFinish: () => {
                sending.value = false;
            },
        },
    );
}
</script>

<template>
    <AppDialog
        v-model:open="open"
        :title="t('events.show.inviteTitle')"
        :description="
            props.organizerName
                ? t('events.show.inviteHintOrganizer', {
                      name: props.organizerName,
                  })
                : t('events.show.inviteHint')
        "
        size="lg"
    >
        <p v-if="!props.candidates.length" class="text-ink-muted">
            {{ t('events.show.inviteNone') }}
        </p>
        <template v-else>
            <SearchField
                v-model="query"
                :label="t('events.show.searchFriends')"
                class="mb-3"
            />
            <ul class="max-h-[50dvh] overflow-y-auto">
                <li v-for="person in shown" :key="person.id">
                    <label
                        class="flex min-h-14 cursor-pointer items-center gap-3 rounded-md px-1 hover:bg-surface-2/55"
                    >
                        <Avatar
                            :name="person.name"
                            :src="person.avatar_url"
                            :size="40"
                            decorative
                        />
                        <span class="min-w-0 flex-1">
                            <span class="block truncate font-medium">{{
                                person.name
                            }}</span>
                            <span
                                class="block truncate text-meta text-ink-muted"
                                >@{{ person.username }}</span
                            >
                        </span>
                        <Checkbox
                            :model-value="selected.includes(person.id)"
                            :label="person.name"
                            @update:model-value="toggle(person.id, $event)"
                        />
                    </label>
                </li>
            </ul>
        </template>
        <template v-if="props.candidates.length" #actions>
            <Button
                variant="primary"
                :icon="UserPlus"
                :disabled="!selected.length || sending"
                @click="send"
            >
                {{
                    sending
                        ? t('events.show.inviteSending')
                        : t('events.show.inviteSend')
                }}
            </Button>
            <Button variant="ghost" @click="open = false">
                {{ t('common.cancel') }}
            </Button>
        </template>
    </AppDialog>
</template>
