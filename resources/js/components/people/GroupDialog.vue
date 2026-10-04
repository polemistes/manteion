<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Trash2 } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import AppDialog from '@/components/ui/AppDialog.vue';
import Avatar from '@/components/ui/Avatar.vue';
import Button from '@/components/ui/Button.vue';
import SearchField from '@/components/ui/SearchField.vue';
import TextField from '@/components/ui/TextField.vue';
import { confirmAction } from '@/composables/useConfirm';
import { toast, toastError } from '@/lib/toast';
import {
    destroy as destroyGroup,
    update as updateGroup,
} from '@/routes/contact-groups';
import {
    destroy as removeMember,
    store as addMember,
} from '@/routes/contact-groups/members';
import type { GroupEntry, Person } from './people';

/**
 * One group: its name, who is in it (and who could be), and deleting it.
 * `candidates` are the people who may join: friends for a friend group,
 * the people you follow for a following group.
 */
const props = defineProps<{
    group: GroupEntry | null;
    candidates: Person[];
}>();

const open = defineModel<boolean>('open', { default: false });

const { t } = useI18n();

const name = ref('');
const filter = ref('');

watch(open, (isOpen) => {
    if (isOpen) {
        name.value = props.group?.name ?? '';
        filter.value = '';
    }
});

const reload = ['friendGroups', 'followingGroups', 'friends', 'following'];
const options = {
    preserveScroll: true,
    preserveState: true,
    only: reload,
    onError: () => toastError(t('settings.saveFailed')),
};

const outside = computed(() => {
    const members = new Set(props.group?.members.map((member) => member.id));
    const term = filter.value.trim().toLocaleLowerCase();

    return props.candidates.filter(
        (person) =>
            !members.has(person.id) &&
            (term === '' ||
                person.name.toLocaleLowerCase().includes(term) ||
                person.username.toLocaleLowerCase().includes(term)),
    );
});

function rename(): void {
    const next = name.value.trim();

    if (!props.group || !next || next === props.group.name) {
        return;
    }

    router.put(updateGroup(props.group.id).url, { name: next }, options);
}

function add(person: Person): void {
    if (props.group) {
        router.post(
            addMember(props.group.id).url,
            { member_user_id: person.id },
            options,
        );
    }
}

function remove(person: Person): void {
    if (props.group) {
        router.delete(
            removeMember({ contact_group: props.group.id, member: person.id })
                .url,
            options,
        );
    }
}

async function deleteGroup(): Promise<void> {
    const group = props.group;

    if (!group) {
        return;
    }

    const confirmed = await confirmAction({
        title: t('people.groups.deleteTitle', { name: group.name }),
        message: t('people.groups.deleteBody'),
        confirmLabel: t('people.groups.delete'),
    });

    if (confirmed) {
        router.delete(destroyGroup(group.id).url, {
            ...options,
            onSuccess: () => {
                open.value = false;
                toast({
                    title: t('people.groups.deleted', { name: group.name }),
                    icon: Trash2,
                });
            },
        });
    }
}
</script>

<template>
    <AppDialog
        v-model:open="open"
        :title="props.group?.name ?? ''"
        :description="t('people.groups.hint')"
        size="lg"
    >
        <form class="flex items-end gap-2" @submit.prevent="rename">
            <TextField
                v-model="name"
                class="min-w-0 flex-1"
                :label="t('people.groups.name')"
                maxlength="255"
            />
            <Button
                type="submit"
                :disabled="!name.trim() || name.trim() === props.group?.name"
            >
                {{ t('people.groups.rename') }}
            </Button>
        </form>

        <h3 class="mt-6 mb-2 text-label font-semibold text-ink">
            {{ t('people.groups.members', props.group?.members.length ?? 0) }}
        </h3>
        <ul
            v-if="props.group?.members.length"
            class="-mx-4 border-y border-line sm:mx-0 sm:overflow-hidden sm:rounded-lg sm:border"
        >
            <li
                v-for="member in props.group.members"
                :key="member.id"
                class="flex min-h-14 items-center gap-3 border-t border-line px-4 first:border-t-0"
            >
                <Avatar
                    :name="member.name"
                    :src="member.avatar_url"
                    :size="32"
                    decorative
                />
                <span class="min-w-0 flex-1 truncate font-medium text-ink">{{
                    member.name
                }}</span>
                <Button variant="ghost" size="sm" @click="remove(member)">
                    {{ t('people.groups.remove') }}
                </Button>
            </li>
        </ul>
        <p v-else class="text-meta text-ink-muted">
            {{ t('people.groups.empty') }}
        </p>

        <template v-if="props.candidates.length">
            <h3 class="mt-6 mb-2 text-label font-semibold text-ink">
                {{ t('people.groups.addPeople') }}
            </h3>
            <SearchField
                v-model="filter"
                :label="t('people.groups.filter')"
                :placeholder="t('people.groups.filter')"
            />
            <ul class="mt-2 max-h-64 overflow-y-auto">
                <li
                    v-for="person in outside"
                    :key="person.id"
                    class="flex min-h-12 items-center gap-3"
                >
                    <Avatar
                        :name="person.name"
                        :src="person.avatar_url"
                        :size="32"
                        decorative
                    />
                    <span class="min-w-0 flex-1 truncate text-ink">{{
                        person.name
                    }}</span>
                    <Button size="sm" @click="add(person)">
                        {{ t('people.groups.add') }}
                    </Button>
                </li>
            </ul>
        </template>

        <template #actions>
            <Button variant="primary" @click="open = false">
                {{ t('people.done') }}
            </Button>
            <Button variant="ghost" :icon="Trash2" @click="deleteGroup">
                {{ t('people.groups.delete') }}
            </Button>
        </template>
    </AppDialog>
</template>
