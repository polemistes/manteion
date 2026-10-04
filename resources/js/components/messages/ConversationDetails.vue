<script setup lang="ts">
import { LogOut, UserPlus } from '@lucide/vue';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import AppDialog from '@/components/ui/AppDialog.vue';
import Avatar from '@/components/ui/Avatar.vue';
import Button from '@/components/ui/Button.vue';
import Card from '@/components/ui/Card.vue';
import ListRow from '@/components/ui/ListRow.vue';
import SelectField from '@/components/ui/SelectField.vue';
import SwitchRow from '@/components/ui/SwitchRow.vue';
import type { ConversationParticipantSummary } from '@/types';

interface Candidate {
    id: number;
    name: string;
    username: string;
}

/** Who is in the chat, mute, add someone, leave. */
const props = defineProps<{
    title: string;
    participants: ConversationParticipantSummary[];
    muted: boolean;
    canAddParticipant: boolean;
    canLeave: boolean;
    addCandidates: Candidate[];
}>();

const emit = defineEmits<{
    mute: [muted: boolean];
    add: [userId: number];
    leave: [];
}>();

const open = defineModel<boolean>('open', { default: false });

const { t } = useI18n();

const muted = computed({
    get: () => props.muted,
    set: (value: boolean) => emit('mute', value),
});

const candidate = ref<string>();

const candidateOptions = computed(() =>
    props.addCandidates.map((person) => ({
        value: String(person.id),
        label: `${person.name} · @${person.username}`,
    })),
);

function add(): void {
    if (candidate.value) {
        emit('add', Number(candidate.value));
        candidate.value = undefined;
    }
}
</script>

<template>
    <AppDialog v-model:open="open" :title="props.title" size="lg">
        <Card>
            <SwitchRow
                v-model="muted"
                :title="t('messages.show.mute')"
                :description="t('messages.show.muteHint')"
            />
        </Card>

        <h3 class="mt-6 mb-2 text-kicker text-ink-muted uppercase">
            {{ t('messages.show.people') }}
        </h3>
        <Card>
            <ListRow
                v-for="person in props.participants"
                :key="person.id"
                :title="person.is_me ? t('messages.show.you') : person.name"
                :sub="`@${person.username}`"
                :inset="74"
            >
                <template #leading>
                    <Avatar
                        :name="person.name"
                        :src="person.avatar_url"
                        :size="44"
                        decorative
                    />
                </template>
            </ListRow>
        </Card>

        <div
            v-if="props.canAddParticipant && props.addCandidates.length"
            class="mt-4 flex items-end gap-2"
        >
            <SelectField
                v-model="candidate"
                :label="t('messages.show.addPerson')"
                :options="candidateOptions"
                :placeholder="t('messages.show.addPerson')"
                class="min-w-0 flex-1"
            />
            <Button :icon="UserPlus" :disabled="!candidate" @click="add">
                {{ t('messages.show.add') }}
            </Button>
        </div>

        <template v-if="props.canLeave" #actions>
            <Button variant="danger" :icon="LogOut" @click="emit('leave')">
                {{ t('messages.show.leave') }}
            </Button>
        </template>
    </AppDialog>
</template>
