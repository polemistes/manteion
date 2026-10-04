<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { UserCheck, Users } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import AppDialog from '@/components/ui/AppDialog.vue';
import Button from '@/components/ui/Button.vue';
import OptionCards from '@/components/ui/OptionCards.vue';
import TextField from '@/components/ui/TextField.vue';
import { store as storeGroup } from '@/routes/contact-groups';
import type { GroupType } from './people';

/** A new group: of friends, or of people you follow. */
const open = defineModel<boolean>('open', { default: false });

const { t } = useI18n();

const name = ref('');
const type = ref<string>('friend');
const error = ref<string | undefined>(undefined);
const saving = ref(false);

watch(open, (isOpen) => {
    if (isOpen) {
        name.value = '';
        type.value = 'friend';
        error.value = undefined;
    }
});

const types = computed(() => [
    {
        value: 'friend',
        title: t('people.groups.ofFriends'),
        description: t('people.groups.ofFriendsHint'),
        icon: Users,
    },
    {
        value: 'following',
        title: t('people.groups.ofFollowing'),
        description: t('people.groups.ofFollowingHint'),
        icon: UserCheck,
    },
]);

function create(): void {
    saving.value = true;
    router.post(
        storeGroup().url,
        { name: name.value.trim(), type: type.value as GroupType },
        {
            preserveScroll: true,
            preserveState: true,
            only: ['friendGroups', 'followingGroups'],
            onSuccess: () => {
                open.value = false;
            },
            onError: (errors) => {
                error.value = errors.name ?? errors.type;
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
        :title="t('people.groups.newTitle')"
        :description="t('people.groups.hint')"
    >
        <form
            id="new-group"
            class="flex flex-col gap-5"
            @submit.prevent="create"
        >
            <TextField
                v-model="name"
                :label="t('people.groups.name')"
                :placeholder="t('people.groups.newNamePlaceholder')"
                :error="error"
                required
                maxlength="255"
            />
            <OptionCards
                v-model="type"
                :options="types"
                :label="t('people.groups.kind')"
            />
        </form>
        <template #actions>
            <Button
                type="submit"
                form="new-group"
                variant="primary"
                :disabled="saving || !name.trim()"
            >
                {{ t('people.groups.create') }}
            </Button>
            <Button variant="ghost" @click="open = false">
                {{ t('ui.cancel') }}
            </Button>
        </template>
    </AppDialog>
</template>
