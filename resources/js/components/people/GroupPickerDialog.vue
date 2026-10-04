<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import AppDialog from '@/components/ui/AppDialog.vue';
import Button from '@/components/ui/Button.vue';
import Checkbox from '@/components/ui/Checkbox.vue';
import TextField from '@/components/ui/TextField.vue';
import { toastError } from '@/lib/toast';
import { store as storeGroup } from '@/routes/contact-groups';
import {
    destroy as removeMember,
    store as addMember,
} from '@/routes/contact-groups/members';
import type { GroupEntry, GroupType, Person } from './people';

/**
 * Which of your groups someone is in, ticked on and off at once, with a
 * new group made right here (and the person put in it).
 */
const props = defineProps<{
    person: Person | null;
    groups: GroupEntry[];
    type: GroupType;
}>();

const open = defineModel<boolean>('open', { default: false });

const { t } = useI18n();

const newName = ref('');
const creating = ref(false);

watch(open, (isOpen) => {
    if (isOpen) {
        newName.value = '';
    }
});

const reload = ['friendGroups', 'followingGroups', 'friends', 'following'];

function isMember(group: GroupEntry): boolean {
    return group.members.some((member) => member.id === props.person?.id);
}

function setMember(group: GroupEntry, on: boolean): void {
    if (!props.person) {
        return;
    }

    const options = {
        preserveScroll: true,
        preserveState: true,
        only: reload,
        onError: () => toastError(t('settings.saveFailed')),
    };

    if (on) {
        router.post(
            addMember(group.id).url,
            { member_user_id: props.person.id },
            options,
        );
    } else {
        router.delete(
            removeMember({ contact_group: group.id, member: props.person.id })
                .url,
            options,
        );
    }
}

function createGroup(): void {
    const name = newName.value.trim();

    if (!name || !props.person) {
        return;
    }

    creating.value = true;
    router.post(
        storeGroup().url,
        { name, type: props.type },
        {
            preserveScroll: true,
            preserveState: true,
            only: reload,
            onSuccess: () => {
                newName.value = '';
                // The newest group of that name is the one just made.
                const created = [...props.groups]
                    .reverse()
                    .find((group) => group.name === name && !isMember(group));

                if (created) {
                    setMember(created, true);
                }
            },
            onError: () => toastError(t('settings.saveFailed')),
            onFinish: () => {
                creating.value = false;
            },
        },
    );
}
</script>

<template>
    <AppDialog
        v-model:open="open"
        :title="t('people.groups.addTitle', { name: props.person?.name ?? '' })"
        :description="t('people.groups.hint')"
    >
        <ul
            v-if="props.groups.length"
            class="-mx-4 border-y border-line sm:mx-0 sm:overflow-hidden sm:rounded-lg sm:border"
        >
            <li
                v-for="group in props.groups"
                :key="group.id"
                class="border-t border-line first:border-t-0"
            >
                <label
                    class="flex min-h-14 cursor-pointer items-center gap-3.5 px-4 hover:bg-surface-2/55"
                >
                    <Checkbox
                        :model-value="isMember(group)"
                        @update:model-value="setMember(group, $event === true)"
                    />
                    <span class="min-w-0 flex-1">
                        <span class="block truncate font-medium text-ink">{{
                            group.name
                        }}</span>
                        <span class="block text-meta text-ink-muted">{{
                            t('people.groups.count', group.members.length)
                        }}</span>
                    </span>
                </label>
            </li>
        </ul>
        <p v-else class="text-meta text-ink-muted">
            {{ t('people.groups.none') }}
        </p>

        <form class="mt-5 flex items-end gap-2" @submit.prevent="createGroup">
            <TextField
                v-model="newName"
                class="min-w-0 flex-1"
                :label="t('people.groups.newName')"
                :placeholder="t('people.groups.newNamePlaceholder')"
                maxlength="255"
            />
            <Button
                type="submit"
                :icon="Plus"
                :disabled="creating || !newName.trim()"
            >
                {{ t('people.groups.create') }}
            </Button>
        </form>

        <template #actions>
            <Button variant="primary" @click="open = false">
                {{ t('people.done') }}
            </Button>
        </template>
    </AppDialog>
</template>
