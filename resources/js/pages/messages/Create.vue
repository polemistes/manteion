<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import Avatar from '@/components/Avatar.vue';
import FieldError from '@/components/FieldError.vue';
import { store } from '@/routes/messages';

interface Friend {
    id: number;
    name: string;
    username: string;
    avatar_url: string | null;
}

defineProps<{
    friends: Friend[];
}>();

const { t } = useI18n();
</script>

<template>
    <Head :title="t('messages.create.title')" />

    <h1 class="mb-6 text-xl font-bold">{{ t('messages.create.title') }}</h1>

    <p v-if="!friends.length" class="text-sm text-black/70">
        {{ t('messages.create.noFriends') }}
    </p>

    <Form
        v-else
        v-bind="store.form()"
        #default="{ errors, processing }"
        class="flex max-w-md flex-col gap-4"
    >
        <div>
            <p class="mb-2 text-sm font-bold">{{ t('messages.create.to') }}</p>
            <ul class="flex flex-col gap-2">
                <li
                    v-for="friend in friends"
                    :key="friend.id"
                    class="flex items-center gap-2 text-sm"
                >
                    <input
                        :id="`friend-${friend.id}`"
                        type="checkbox"
                        name="user_ids[]"
                        :value="friend.id"
                        class="border border-black"
                    />
                    <label
                        :for="`friend-${friend.id}`"
                        class="flex items-center gap-2"
                    >
                        <Avatar :url="friend.avatar_url" :name="friend.name" />
                        {{ friend.name }} · @{{ friend.username }}
                    </label>
                </li>
            </ul>
            <FieldError :message="errors.user_ids" />
        </div>

        <div>
            <label for="name" class="mb-1 block text-sm font-bold">{{
                t('messages.create.groupNameOptional')
            }}</label>
            <input
                id="name"
                type="text"
                name="name"
                :placeholder="t('messages.create.groupNamePlaceholder')"
                class="w-full border border-black bg-white px-3 py-2 font-sans text-sm focus:ring-1 focus:ring-black focus:outline-none"
            />
            <FieldError :message="errors.name" />
        </div>

        <button
            type="submit"
            :disabled="processing"
            class="w-fit border border-black bg-black px-4 py-2 text-sm font-bold text-white hover:bg-white hover:text-black disabled:cursor-not-allowed disabled:opacity-50"
        >
            {{
                processing
                    ? t('messages.create.submitting')
                    : t('messages.create.submit')
            }}
        </button>
    </Form>
</template>
