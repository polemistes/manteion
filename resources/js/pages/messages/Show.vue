<script setup lang="ts">
import { Form, Head, Link, router, usePoll } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import Avatar from '@/components/Avatar.vue';
import FieldError from '@/components/FieldError.vue';
import { confirmAction } from '@/composables/useConfirm';
import { send } from '@/routes/messages';
import * as conversationMessages from '@/routes/messages/messages';
import * as conversationParticipants from '@/routes/messages/participants';

const { t, locale } = useI18n();

const dateFnsLocales: Record<string, string> = {
    en: 'en-GB',
    nb: 'nb-NO',
};

interface Person {
    id: number;
    name: string;
    username: string;
    avatar_url: string | null;
}

interface Participant extends Person {
    is_me: boolean;
}

interface MessageItem {
    id: number;
    body: string | null;
    is_deleted: boolean;
    created_at: string;
    sender: Person;
    is_mine: boolean;
}

const props = defineProps<{
    conversation: {
        id: number;
        type: 'direct' | 'group';
        title: string;
    };
    participants: Participant[];
    messages: MessageItem[];
    canAddParticipant: boolean;
    canLeave: boolean;
    addCandidates: Person[];
}>();

usePoll(4000, { only: ['messages', 'participants'] });

function formatTime(value: string): string {
    return new Date(value).toLocaleString(
        dateFnsLocales[locale.value] ?? locale.value,
        { dateStyle: 'medium', timeStyle: 'short' },
    );
}

async function deleteMessage(messageId: number): Promise<void> {
    const confirmed = await confirmAction({
        message: t('messages.show.confirmDeleteMessage'),
    });

    if (!confirmed) {
        return;
    }

    router.delete(
        conversationMessages.destroy([props.conversation.id, messageId]).url,
        { preserveScroll: true },
    );
}
</script>

<template>
    <Head :title="props.conversation.title" />

    <div class="mb-4 flex items-start justify-between gap-4">
        <h1 class="text-xl font-bold">{{ props.conversation.title }}</h1>
        <Link
            v-if="props.canLeave"
            :href="conversationParticipants.destroy(props.conversation.id).url"
            method="delete"
            as="button"
            class="shrink-0 border border-black px-3 py-2 text-sm hover:bg-black hover:text-white"
        >
            {{ t('messages.show.leave') }}
        </Link>
    </div>

    <section
        v-if="props.conversation.type === 'group'"
        class="mb-6 border-[3px] border-black p-3"
    >
        <h2 class="mb-2 text-xs font-bold uppercase">
            {{ t('messages.show.inThisConversation') }}
        </h2>
        <ul class="mb-3 flex flex-wrap gap-3">
            <li
                v-for="person in props.participants"
                :key="person.id"
                class="flex items-center gap-2 text-sm"
            >
                <Avatar :url="person.avatar_url" :name="person.name" />
                {{ person.is_me ? t('messages.show.you') : person.name }}
            </li>
        </ul>

        <Form
            v-if="props.canAddParticipant && props.addCandidates.length"
            v-bind="conversationParticipants.store.form(props.conversation.id)"
            #default="{ errors, processing }"
            class="flex gap-2"
        >
            <select
                name="user_id"
                required
                class="flex-1 border border-black bg-white px-2 py-1 font-sans text-sm"
            >
                <option
                    v-for="candidate in props.addCandidates"
                    :key="candidate.id"
                    :value="candidate.id"
                >
                    {{ candidate.name }} · @{{ candidate.username }}
                </option>
            </select>
            <button
                type="submit"
                :disabled="processing"
                class="border border-black px-2 py-1 text-xs hover:bg-black hover:text-white"
            >
                {{ t('messages.show.add') }}
            </button>
            <FieldError :message="errors.user_id" />
        </Form>
    </section>

    <ul class="mb-6 flex flex-col gap-4">
        <li v-for="message in props.messages" :key="message.id">
            <div class="mb-1 flex items-center gap-2">
                <Avatar
                    :url="message.sender.avatar_url"
                    :name="message.sender.name"
                />
                <span class="text-sm font-bold">{{
                    message.is_mine
                        ? t('messages.show.you')
                        : message.sender.name
                }}</span>
                <span class="text-xs text-black/70">{{
                    formatTime(message.created_at)
                }}</span>
                <button
                    v-if="message.is_mine && !message.is_deleted"
                    type="button"
                    class="text-xs text-black/50 hover:underline"
                    @click="deleteMessage(message.id)"
                >
                    {{ t('messages.show.delete') }}
                </button>
            </div>
            <p
                class="ml-10 font-sans text-sm whitespace-pre-line"
                :class="{ 'text-black/50 italic': message.is_deleted }"
            >
                {{
                    message.is_deleted
                        ? t('messages.show.deleted')
                        : message.body
                }}
            </p>
        </li>
        <li v-if="!props.messages.length" class="text-sm text-black/70">
            {{ t('messages.show.noMessagesYet') }}
        </li>
    </ul>

    <Form
        v-bind="send.form(props.conversation.id)"
        reset-on-success
        #default="{ errors, processing }"
        class="flex flex-col gap-2"
    >
        <textarea
            name="body"
            required
            rows="3"
            :placeholder="t('messages.show.messagePlaceholder')"
            class="w-full border border-black bg-white px-3 py-2 font-sans text-sm focus:ring-1 focus:ring-black focus:outline-none"
        ></textarea>
        <FieldError :message="errors.body" />
        <button
            type="submit"
            :disabled="processing"
            class="w-fit border border-black bg-black px-3 py-2 text-sm text-white hover:bg-white hover:text-black"
        >
            {{
                processing
                    ? t('messages.show.sending')
                    : t('messages.show.send')
            }}
        </button>
    </Form>
</template>
