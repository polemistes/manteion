<script setup lang="ts">
import { Form, Link } from '@inertiajs/vue3';
import { UserPlus } from '@lucide/vue';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import FieldError from '@/components/FieldError.vue';
import PageContainer from '@/components/shell/PageContainer.vue';
import PageHeader from '@/components/shell/PageHeader.vue';
import Avatar from '@/components/ui/Avatar.vue';
import Button from '@/components/ui/Button.vue';
import Card from '@/components/ui/Card.vue';
import Checkbox from '@/components/ui/Checkbox.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import SearchField from '@/components/ui/SearchField.vue';
import TextField from '@/components/ui/TextField.vue';
import { index as contactsIndex } from '@/routes/contacts';
import { index as messagesIndex, store } from '@/routes/messages';

interface Friend {
    id: number;
    name: string;
    username: string;
    avatar_url: string | null;
}

const props = defineProps<{
    friends: Friend[];
}>();

const { t } = useI18n();

const query = ref('');
const selected = ref<number[]>([]);
const groupName = ref('');

const shown = computed(() => {
    const term = query.value.trim().toLocaleLowerCase();

    return term === ''
        ? props.friends
        : props.friends.filter(
              (friend) =>
                  friend.name.toLocaleLowerCase().includes(term) ||
                  friend.username.toLocaleLowerCase().includes(term),
          );
});

function isSelected(id: number): boolean {
    return selected.value.includes(id);
}

function setSelected(id: number, value: boolean): void {
    selected.value = value
        ? [...selected.value, id]
        : selected.value.filter((other) => other !== id);
}
</script>

<template>
    <PageHeader
        :title="t('messages.create.title')"
        :close="messagesIndex().url"
        :back="messagesIndex().url"
        :back-label="t('messages.show.back')"
        width="panel"
    />

    <PageContainer width="panel" class="pt-4 sm:pt-0">
        <EmptyState
            v-if="!props.friends.length"
            :icon="UserPlus"
            :title="t('messages.create.noFriendsTitle')"
            :body="t('messages.create.noFriends')"
        >
            <Button :as="Link" :href="contactsIndex().url" :icon="UserPlus">
                {{ t('messages.create.findPeople') }}
            </Button>
        </EmptyState>

        <Form
            v-else
            v-bind="store.form()"
            #default="{ errors, processing }"
            class="flex flex-col gap-4"
        >
            <SearchField v-model="query" :label="t('messages.create.search')" />

            <Card as="fieldset">
                <legend class="sr-only">{{ t('messages.create.to') }}</legend>
                <label
                    v-for="friend in shown"
                    :key="friend.id"
                    class="group/row relative flex min-h-15 cursor-pointer items-center gap-3.5 px-4 py-3 transition-colors duration-120 hover:bg-surface-2/55"
                >
                    <span
                        class="pointer-events-none absolute top-0 right-0 left-[74px] h-px bg-line group-first-of-type/row:hidden"
                        aria-hidden="true"
                    />
                    <Avatar
                        :name="friend.name"
                        :src="friend.avatar_url"
                        :size="44"
                        decorative
                    />
                    <span class="min-w-0 flex-1">
                        <span class="block truncate font-medium">{{
                            friend.name
                        }}</span>
                        <span class="block truncate text-meta text-ink-muted"
                            >@{{ friend.username }}</span
                        >
                    </span>
                    <Checkbox
                        :model-value="isSelected(friend.id)"
                        name="user_ids[]"
                        :value="String(friend.id)"
                        :label="friend.name"
                        @update:model-value="setSelected(friend.id, $event)"
                    />
                </label>
                <p
                    v-if="!shown.length"
                    class="px-4 py-6 text-center text-ink-muted"
                >
                    {{ t('messages.create.noMatches') }}
                </p>
            </Card>
            <FieldError :message="errors.user_ids" />

            <TextField
                v-if="selected.length > 1"
                v-model="groupName"
                name="name"
                :label="t('messages.create.groupName')"
                :hint="t('messages.create.groupNameHint')"
                :error="errors.name"
                optional
                maxlength="255"
            />

            <div class="flex items-center justify-between gap-3">
                <span class="text-meta text-ink-muted">{{
                    selected.length
                        ? t('messages.create.selected', { n: selected.length })
                        : ''
                }}</span>
                <Button
                    type="submit"
                    variant="primary"
                    :disabled="processing || !selected.length"
                >
                    {{
                        processing
                            ? t('messages.create.submitting')
                            : t('messages.create.submit')
                    }}
                </Button>
            </div>
        </Form>
    </PageContainer>
</template>
