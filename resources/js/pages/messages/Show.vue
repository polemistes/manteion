<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    ArrowDown,
    Bell,
    BellOff,
    CheckCheck,
    ChevronLeft,
    Copy,
    Flag,
    Info,
    SmilePlus,
    Trash,
} from '@lucide/vue';
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import EmojiPicker from '@/components/EmojiPicker.vue';
import { QUICK_REACTIONS } from '@/components/messages/chat';
import type { BubblePosition, ChatMessage } from '@/components/messages/chat';
import ChatBubble from '@/components/messages/ChatBubble.vue';
import Composer from '@/components/messages/Composer.vue';
import ConversationDetails from '@/components/messages/ConversationDetails.vue';
import ConversationList from '@/components/messages/ConversationList.vue';
import AppDialog from '@/components/ui/AppDialog.vue';
import Avatar from '@/components/ui/Avatar.vue';
import Button from '@/components/ui/Button.vue';
import CountBadge from '@/components/ui/CountBadge.vue';
import GroupAvatar from '@/components/ui/GroupAvatar.vue';
import IconButton from '@/components/ui/IconButton.vue';
import { confirmAction } from '@/composables/useConfirm';
import { report } from '@/composables/useReport';
import { staffDelete } from '@/composables/useStaffDelete';
import { useDates } from '@/lib/dates';
import { toast, toastError } from '@/lib/toast';
import {
    onIncomingMessage,
    requestJson,
    useBadges,
    useConversationChannel,
} from '@/realtime';
import {
    index as messagesIndex,
    mute as muteRoute,
    send as sendRoute,
} from '@/routes/messages';
import { seen as markMessageSeen } from '@/routes/messages/messages';
import {
    destroy as destroyMessageRoute,
    index as messagesPageRoute,
} from '@/routes/messages/messages';
import {
    destroy as leaveRoute,
    store as addParticipantRoute,
} from '@/routes/messages/participants';
import { store as reactionsStore } from '@/routes/reactions';
import type {
    ConversationListItem,
    ConversationParticipantSummary,
    MessageSummary,
    ReactionSummary,
} from '@/types';

interface Candidate {
    id: number;
    name: string;
    username: string;
    avatar_url: string | null;
}

const props = defineProps<{
    conversation: {
        id: number;
        type: 'direct' | 'group';
        title: string;
        avatar_url: string | null;
        muted: boolean;
    };
    participants: ConversationParticipantSummary[];
    messages: MessageSummary[];
    hasOlder: boolean;
    canAddParticipant: boolean;
    canLeave: boolean;
    addCandidates: Candidate[];
    conversations: ConversationListItem[];
}>();

const { t } = useI18n();
const page = usePage();
const dates = useDates();
const { badges } = useBadges();

const isGroup = computed(() => props.conversation.type === 'group');
const others = computed(() =>
    props.participants.filter((person) => !person.is_me),
);

// When each of the others last read the conversation: from the page, then
// live as they read.
const readTimes = ref(new Map<number, string | null>());

watch(
    others,
    (people) => {
        readTimes.value = new Map(
            people.map((person) => [person.id, person.last_read_at]),
        );
    },
    { immediate: true },
);

function read(userId: number, readAt: string | null): void {
    readTimes.value = new Map(readTimes.value).set(userId, readAt);
}

const subtitle = computed(() => {
    if (!isGroup.value) {
        const other = others.value[0];

        return other ? `@${other.username}` : '';
    }

    const names = others.value.slice(0, 3).map((person) => person.name);

    return others.value.length > 3
        ? t('messages.show.membersMore', {
              names: names.join(', '),
              n: others.value.length - 3,
          })
        : t('messages.show.members', { names: names.join(', ') });
});

// Messages beyond the page's own: history loaded on request, live ones,
// and any the user changed (reactions, deletes). They win over the props.
const extra = ref(new Map<number, ChatMessage>());
const pending = ref<ChatMessage[]>([]);
const hasOlder = ref(props.hasOlder);
const loadingOlder = ref(false);
const muted = ref(props.conversation.muted);

const messages = computed<ChatMessage[]>(() => {
    const byId = new Map<number, ChatMessage>();

    for (const message of props.messages) {
        byId.set(message.id, message);
    }

    for (const [id, message] of extra.value) {
        byId.set(id, message);
    }

    return [...byId.values()].sort((a, b) => a.id - b.id).concat(pending.value);
});

// Under your message when it is the newest: who has read it since. In a
// direct chat that is "Seen"; in a group, their faces.
const seenBy = computed(() => {
    const last = messages.value.at(-1);

    if (!last || !last.is_mine || last.pending || last.is_deleted) {
        return [];
    }

    const sentAt = Date.parse(last.created_at);

    return others.value.filter((person) => {
        const readAt = readTimes.value.get(person.id);

        return (
            readAt !== null &&
            readAt !== undefined &&
            Date.parse(readAt) >= sentAt
        );
    });
});

const newestId = computed(() =>
    messages.value.reduce(
        (max, message) => (message.pending ? max : Math.max(max, message.id)),
        0,
    ),
);

interface MessageGroup {
    key: string;
    mine: boolean;
    messages: ChatMessage[];
}

interface MessageDay {
    key: string;
    label: string;
    groups: MessageGroup[];
}

const GROUP_GAP_MS = 5 * 60 * 1000;

// Consecutive messages from one person within five minutes on the same day.
const days = computed<MessageDay[]>(() => {
    const result: MessageDay[] = [];

    for (const message of messages.value) {
        const dayKey = dates.dayKey(message.created_at);
        let day = result[result.length - 1];

        if (!day || day.key !== dayKey) {
            day = {
                key: dayKey,
                label: dates.dayDivider(message.created_at),
                groups: [],
            };
            result.push(day);
        }

        const group = day.groups[day.groups.length - 1];
        const last = group?.messages[group.messages.length - 1];

        if (
            group &&
            last &&
            last.sender.id === message.sender.id &&
            new Date(message.created_at).getTime() -
                new Date(last.created_at).getTime() <
                GROUP_GAP_MS
        ) {
            group.messages.push(message);
        } else {
            day.groups.push({
                key: message.tempId ?? String(message.id),
                mine: message.is_mine,
                messages: [message],
            });
        }
    }

    return result;
});

function positionIn(group: MessageGroup, index: number): BubblePosition {
    if (group.messages.length === 1) {
        return 'single';
    }

    if (index === 0) {
        return 'first';
    }

    return index === group.messages.length - 1 ? 'last' : 'middle';
}

// Scrolling: open at the newest message, follow new ones while the reader
// is at the bottom, otherwise offer a "New messages" jump.
const scroller = ref<HTMLElement | null>(null);
const unseenBelow = ref(0);

function nearBottom(): boolean {
    const el = scroller.value;

    return !el || el.scrollHeight - el.scrollTop - el.clientHeight < 120;
}

function scrollToBottom(smooth = false): void {
    const el = scroller.value;

    if (el) {
        el.scrollTo({
            top: el.scrollHeight,
            behavior: smooth ? 'smooth' : 'auto',
        });
    }

    unseenBelow.value = 0;
}

function onScroll(): void {
    if (nearBottom()) {
        unseenBelow.value = 0;
    }

    if (
        (scroller.value?.scrollTop ?? 0) < 240 &&
        hasOlder.value &&
        !loadingOlder.value
    ) {
        void loadOlder();
    }
}

onMounted(() => scrollToBottom());

function setExtra(message: ChatMessage): void {
    const next = new Map(extra.value);

    next.set(message.id, message);
    extra.value = next;
}

function current(id: number): ChatMessage | undefined {
    return messages.value.find((message) => message.id === id);
}

async function loadOlder(): Promise<void> {
    const oldest = messages.value.find((message) => !message.pending);

    if (!oldest || loadingOlder.value) {
        return;
    }

    loadingOlder.value = true;

    const el = scroller.value;
    const heightBefore = el?.scrollHeight ?? 0;

    try {
        const data = await requestJson<{
            messages: MessageSummary[];
            has_older: boolean;
        }>(
            messagesPageRoute(props.conversation.id, {
                query: { before: oldest.id },
            }),
        );

        if (data) {
            const next = new Map(extra.value);

            for (const message of data.messages) {
                next.set(message.id, message);
            }

            extra.value = next;
            hasOlder.value = data.has_older;
            await nextTick();

            if (el) {
                el.scrollTop += el.scrollHeight - heightBefore;
            }
        }
    } catch {
        toastError(t('ui.somethingWentWrong'));
    } finally {
        loadingOlder.value = false;
    }
}

function arrived(message: MessageSummary): void {
    const followBottom = nearBottom() || message.is_mine;

    setExtra(message);

    if (!message.is_mine && document.visibilityState === 'visible') {
        markRead();
    }

    void nextTick(() => {
        if (followBottom) {
            scrollToBottom(true);
        } else {
            unseenBelow.value++;
        }
    });
}

const { typingUsers, whisperTyping, markRead } = useConversationChannel(
    () => props.conversation.id,
    {
        onMessage: arrived,
        onUpdate: (message) => setExtra(message),
        onRead: read,
        onParticipants: () =>
            router.reload({
                only: [
                    'participants',
                    'addCandidates',
                    'conversation',
                    'canLeave',
                    'canAddParticipant',
                ],
            }),
        lastMessageId: () => newestId.value,
    },
);

const typingLabel = computed(() => {
    if (!typingUsers.value.length) {
        return '';
    }

    return typingUsers.value.length === 1
        ? t('messages.show.typingOne', { name: typingUsers.value[0].name })
        : t('messages.show.typingMany');
});

// The list beside the thread (from 768px) follows new messages too.
let listTimer: ReturnType<typeof setTimeout> | undefined;

function reloadList(): void {
    clearTimeout(listTimer);
    listTimer = setTimeout(
        () => router.reload({ only: ['conversations'] }),
        400,
    );
}

onIncomingMessage(reloadList);

// Sending: the message shows at once, then is swapped for the server's.
let pendingSeq = 0;

async function send(body: string, retryOf?: ChatMessage): Promise<void> {
    const me = page.props.auth.user;
    const tempId = retryOf?.tempId ?? `pending-${++pendingSeq}`;
    const draft: ChatMessage = {
        id: -pendingSeq,
        body,
        is_deleted: false,
        created_at: new Date().toISOString(),
        sender: {
            id: me.id,
            name: me.name,
            username: me.username,
            avatar_url: me.avatar_url,
        },
        is_mine: true,
        can_delete: false,
        can_report: false,
        reactions: [],
        seen_by: [],
        pending: 'sending',
        tempId,
    };

    pending.value = retryOf
        ? pending.value.map((item) => (item.tempId === tempId ? draft : item))
        : [...pending.value, draft];
    void nextTick(() => scrollToBottom(true));

    try {
        const response = await requestJson<{ message: MessageSummary }>(
            sendRoute(props.conversation.id),
            { body },
        );

        pending.value = pending.value.filter((item) => item.tempId !== tempId);

        if (response?.message) {
            setExtra(response.message);
        }

        reloadList();
    } catch {
        pending.value = pending.value.map((item) =>
            item.tempId === tempId ? { ...item, pending: 'failed' } : item,
        );
    }
}

function retry(message: ChatMessage): void {
    void send(message.body ?? '', message);
}

// One reaction per person: the same emoji again takes it back.
function toggled(
    reactions: ReactionSummary[],
    emoji: string,
): ReactionSummary[] {
    const mine = reactions.find((reaction) => reaction.reacted);
    const next = reactions.map((reaction) => ({ ...reaction }));

    if (mine) {
        const own = next.find((reaction) => reaction.emoji === mine.emoji)!;

        own.count--;
        own.reacted = false;
    }

    if (!mine || mine.emoji !== emoji) {
        const existing = next.find((reaction) => reaction.emoji === emoji);

        if (existing) {
            existing.count++;
            existing.reacted = true;
        } else {
            next.push({ emoji, count: 1, reacted: true });
        }
    }

    return next.filter((reaction) => reaction.count > 0);
}

async function react(message: ChatMessage, emoji: string): Promise<void> {
    if (message.pending || message.is_deleted) {
        return;
    }

    const before = message;

    setExtra({ ...message, reactions: toggled(message.reactions, emoji) });

    try {
        const response = await requestJson<{ reactions: ReactionSummary[] }>(
            reactionsStore(),
            {
                reactable_type: 'message',
                reactable_id: message.id,
                emoji,
            },
        );
        const latest = current(message.id);

        if (response?.reactions && latest) {
            setExtra({ ...latest, reactions: response.reactions });
        }
    } catch {
        setExtra(before);
        toastError(t('ui.somethingWentWrong'));
    }
}

// A deliberate "I have seen this" on someone else's message, shown under
// it to everyone whatever the privacy switches say.
async function markSeen(message: ChatMessage): Promise<void> {
    const me = page.props.auth.user;

    if (
        message.pending ||
        message.is_deleted ||
        message.is_mine ||
        message.seen_by.some((person) => person.id === me.id)
    ) {
        return;
    }

    const before = message;

    setExtra({
        ...message,
        seen_by: [
            ...message.seen_by,
            { id: me.id, name: me.name, avatar_url: me.avatar_url },
        ],
    });

    try {
        const response = await requestJson<{ message: MessageSummary }>(
            markMessageSeen([props.conversation.id, message.id]),
            {},
        );
        const latest = current(message.id);

        if (response?.message && latest) {
            setExtra({ ...latest, seen_by: response.message.seen_by });
        }

        // Nothing shows under the message for you (the mark is for the
        // others), so say that it went through.
        toast({
            title: t('messages.show.markedSeen', { name: message.sender.name }),
            icon: CheckCheck,
        });
    } catch {
        setExtra(before);
        toastError(t('ui.somethingWentWrong'));
    }
}

async function remove(message: ChatMessage): Promise<void> {
    let leavePlaceholder = false;

    if (!message.is_mine) {
        const choice = await staffDelete({
            message: t('moderation.confirmDeleteContent'),
        });

        if (choice === null) {
            return;
        }

        leavePlaceholder = choice === 'placeholder';
    } else {
        const confirmed = await confirmAction({
            title: t('messages.show.confirmDeleteTitle'),
            message: t('messages.show.confirmDelete'),
            confirmLabel: t('messages.show.delete'),
        });

        if (!confirmed) {
            return;
        }
    }

    const before = message;

    setExtra({
        ...message,
        body: null,
        is_deleted: true,
        can_delete: false,
        can_report: false,
        reactions: [],
    });

    try {
        await requestJson(
            destroyMessageRoute([props.conversation.id, message.id]),
            { leave_placeholder: leavePlaceholder },
        );
    } catch {
        setExtra(before);
        toastError(t('ui.somethingWentWrong'));
    }
}

async function copy(message: ChatMessage): Promise<void> {
    try {
        await navigator.clipboard.writeText(message.body ?? '');
        toast({ title: t('messages.show.copied'), icon: Copy });
    } catch {
        toastError(t('ui.somethingWentWrong'));
    }
}

// Touch: a long press opens the actions sheet.
const actionsFor = ref<ChatMessage | null>(null);
const actionsOpen = computed({
    get: () => actionsFor.value !== null,
    set: (open: boolean) => {
        if (!open) {
            actionsFor.value = null;
        }
    },
});

function actOnSheet(action: (message: ChatMessage) => void): void {
    const message = actionsFor.value;

    actionsFor.value = null;

    if (message) {
        action(message);
    }
}

// The full emoji picker, for a reaction that isn't one of the quick five.
const pickerFor = ref<ChatMessage | null>(null);
const pickerOpen = computed({
    get: () => pickerFor.value !== null,
    set: (open: boolean) => {
        if (!open) {
            pickerFor.value = null;
        }
    },
});

function pickFromSheet(): void {
    pickerFor.value = actionsFor.value;
    actionsFor.value = null;
}

function onPicked(emoji: string): void {
    if (pickerFor.value) {
        void react(pickerFor.value, emoji);
    }
}

// Details: mute, people, add, leave.
const detailsOpen = ref(false);

async function setMuted(value: boolean): Promise<void> {
    const before = muted.value;

    muted.value = value;

    try {
        await requestJson(muteRoute(props.conversation.id), { muted: value });
        toast({
            title: value
                ? t('messages.show.mutedToast')
                : t('messages.show.unmutedToast'),
            icon: value ? BellOff : Bell,
        });
    } catch {
        muted.value = before;
        toastError(t('ui.somethingWentWrong'));
    }
}

function addParticipant(userId: number): void {
    router.post(
        addParticipantRoute(props.conversation.id).url,
        { user_id: userId },
        { preserveScroll: true, preserveState: true },
    );
}

async function leave(): Promise<void> {
    detailsOpen.value = false;

    const confirmed = await confirmAction({
        title: t('messages.show.confirmLeaveTitle'),
        message: t('messages.show.confirmLeave'),
        confirmLabel: t('messages.show.leave'),
    });

    if (confirmed) {
        router.delete(leaveRoute(props.conversation.id).url);
    }
}
</script>

<template>
    <Head :title="props.conversation.title" />

    <div class="flex h-dvh">
        <aside
            class="hidden min-h-0 w-[300px] shrink-0 flex-col border-r border-line md:flex lg:w-[360px]"
        >
            <ConversationList
                :conversations="props.conversations"
                :active-id="props.conversation.id"
            />
        </aside>

        <section class="relative flex min-w-0 flex-1 flex-col">
            <header
                class="flex-none border-b border-line bg-canvas pt-[env(safe-area-inset-top)]"
            >
                <div
                    class="flex h-15 items-center gap-1 px-1 md:h-[68px] md:gap-3 md:px-5 lg:h-[72px]"
                >
                    <Link
                        :href="messagesIndex().url"
                        :aria-label="t('messages.show.back')"
                        class="flex h-11 min-w-11 shrink-0 items-center gap-0.5 rounded-full pr-1.5 pl-1 text-ink hover:bg-surface-2 md:hidden"
                    >
                        <ChevronLeft
                            :size="22"
                            :stroke-width="1.75"
                            aria-hidden="true"
                        />
                        <CountBadge :count="badges.messages" tone="neutral" />
                    </Link>
                    <GroupAvatar
                        v-if="isGroup && others.length > 1"
                        :members="others"
                        :name="props.conversation.title"
                        :size="40"
                        class="[--avatar-ring:var(--color-canvas)]"
                    />
                    <Avatar
                        v-else
                        :name="props.conversation.title"
                        :src="
                            props.conversation.avatar_url ??
                            others[0]?.avatar_url ??
                            null
                        "
                        :size="40"
                        decorative
                    />
                    <div class="min-w-0 flex-1 px-2 md:px-0">
                        <h1 class="truncate font-semibold text-ink">
                            {{ props.conversation.title }}
                        </h1>
                        <p class="truncate text-meta text-ink-muted">
                            <span v-if="typingLabel" class="text-accent-ink">{{
                                typingLabel
                            }}</span>
                            <template v-else>{{ subtitle }}</template>
                        </p>
                    </div>
                    <BellOff
                        v-if="muted"
                        :size="18"
                        :stroke-width="1.9"
                        class="shrink-0 text-ink-subtle"
                        :aria-label="t('messages.index.muted')"
                    />
                    <IconButton
                        :icon="Info"
                        :label="t('messages.show.details')"
                        @click="detailsOpen = true"
                    />
                </div>
            </header>

            <div
                ref="scroller"
                class="min-h-0 flex-1 overflow-y-auto overscroll-contain"
                @scroll.passive="onScroll"
            >
                <div
                    class="mx-auto flex min-h-full max-w-thread flex-col justify-end px-3 pt-4 pb-4 sm:px-6"
                >
                    <div v-if="hasOlder" class="mb-2 flex justify-center">
                        <Button
                            size="sm"
                            :disabled="loadingOlder"
                            @click="loadOlder"
                        >
                            {{
                                loadingOlder
                                    ? t('messages.show.loadingOlder')
                                    : t('messages.show.loadOlder')
                            }}
                        </Button>
                    </div>

                    <p
                        v-if="!messages.length"
                        class="py-12 text-center font-display text-input text-ink-muted italic"
                    >
                        {{ t('messages.show.noMessagesYet') }}
                    </p>

                    <section v-for="day in days" :key="day.key">
                        <h2
                            class="mt-[22px] mb-2 flex items-center gap-3 font-display text-input font-normal text-ink-muted italic"
                        >
                            <span
                                class="h-px flex-1 bg-line"
                                aria-hidden="true"
                            />
                            {{ day.label }}
                            <span
                                class="h-px flex-1 bg-line"
                                aria-hidden="true"
                            />
                        </h2>
                        <div
                            v-for="group in day.groups"
                            :key="group.key"
                            class="mt-3.5 flex flex-col gap-[3px]"
                        >
                            <ChatBubble
                                v-for="(message, index) in group.messages"
                                :key="message.tempId ?? message.id"
                                :message="message"
                                :position="positionIn(group, index)"
                                :show-name="
                                    isGroup && !group.mine && index === 0
                                "
                                :show-avatar="
                                    isGroup &&
                                    !group.mine &&
                                    index === group.messages.length - 1
                                "
                                :reserve-avatar="isGroup && !group.mine"
                                :seen-by="
                                    message === messages.at(-1) ? seenBy : []
                                "
                                :seen-as="isGroup ? 'faces' : 'caption'"
                                :show-time="index === group.messages.length - 1"
                                @react="react(message, $event)"
                                @pick-reaction="pickerFor = message"
                                @actions="actionsFor = message"
                                @copy="copy(message)"
                                @delete="remove(message)"
                                @report="report('message', message.id)"
                                @retry="retry(message)"
                                @mark-seen="markSeen(message)"
                            />
                        </div>
                    </section>

                    <div
                        v-if="typingUsers.length"
                        class="mt-3.5 flex items-end gap-2"
                    >
                        <span v-if="isGroup" class="w-7 shrink-0" />
                        <div
                            class="flex h-10 items-center gap-1 rounded-lg bg-bubble-theirs px-4 ring-1 ring-line ring-inset"
                            aria-hidden="true"
                        >
                            <span
                                class="size-[7px] animate-typing rounded-full bg-ink-subtle"
                            />
                            <span
                                class="size-[7px] animate-typing rounded-full bg-ink-subtle [animation-delay:150ms]"
                            />
                            <span
                                class="size-[7px] animate-typing rounded-full bg-ink-subtle [animation-delay:300ms]"
                            />
                        </div>
                    </div>
                    <p class="sr-only" aria-live="polite">{{ typingLabel }}</p>
                </div>
            </div>

            <button
                v-if="unseenBelow > 0"
                type="button"
                class="absolute bottom-[calc(80px+env(safe-area-inset-bottom))] left-1/2 z-10 inline-flex h-9 -translate-x-1/2 items-center gap-1.5 rounded-full bg-surface px-3.5 text-label font-semibold text-ink shadow-lg ring-1 ring-line"
                @click="scrollToBottom(true)"
            >
                <ArrowDown :size="16" :stroke-width="2" aria-hidden="true" />
                {{ t('messages.show.newMessages') }}
            </button>

            <Composer @send="send" @typing="whisperTyping" />
        </section>
    </div>

    <ConversationDetails
        v-model:open="detailsOpen"
        :title="props.conversation.title"
        :participants="props.participants"
        :muted="muted"
        :can-add-participant="props.canAddParticipant"
        :can-leave="props.canLeave"
        :add-candidates="props.addCandidates"
        @mute="setMuted"
        @add="addParticipant"
        @leave="leave"
    />

    <AppDialog
        v-model:open="actionsOpen"
        :title="t('messages.show.actions')"
        hide-title
    >
        <div class="flex items-center justify-between gap-1 pb-3">
            <button
                v-for="emoji in QUICK_REACTIONS"
                :key="emoji"
                type="button"
                :aria-label="t('messages.show.react', { emoji })"
                class="grid size-12 place-items-center rounded-full bg-surface-2 text-[24px] leading-none"
                @click="actOnSheet((message) => react(message, emoji))"
            >
                {{ emoji }}
            </button>
            <button
                type="button"
                :aria-label="t('messages.show.moreReactions')"
                class="grid size-12 place-items-center rounded-full bg-surface-2 text-ink-muted"
                @click="pickFromSheet"
            >
                <SmilePlus :size="22" :stroke-width="1.8" aria-hidden="true" />
            </button>
        </div>
        <div class="border-t border-line pt-2">
            <button
                v-if="
                    actionsFor && !actionsFor.is_mine && !actionsFor.is_deleted
                "
                type="button"
                class="flex h-12 w-full items-center gap-3 rounded-md px-3 text-left hover:bg-surface-2 disabled:pointer-events-none disabled:opacity-45"
                :disabled="
                    actionsFor.seen_by.some(
                        (person) => person.id === page.props.auth.user.id,
                    )
                "
                @click="actOnSheet(markSeen)"
            >
                <CheckCheck
                    :size="18"
                    class="text-ink-muted"
                    aria-hidden="true"
                />
                {{
                    t('messages.show.markSeen', {
                        name: actionsFor.sender.name,
                    })
                }}
            </button>
            <button
                type="button"
                class="flex h-12 w-full items-center gap-3 rounded-md px-3 text-left hover:bg-surface-2"
                @click="actOnSheet(copy)"
            >
                <Copy :size="18" class="text-ink-muted" aria-hidden="true" />
                {{ t('messages.show.copy') }}
            </button>
            <button
                v-if="actionsFor?.can_report"
                type="button"
                class="flex h-12 w-full items-center gap-3 rounded-md px-3 text-left hover:bg-surface-2"
                @click="actOnSheet((message) => report('message', message.id))"
            >
                <Flag :size="18" class="text-ink-muted" aria-hidden="true" />
                {{ t('messages.show.report') }}
            </button>
            <button
                v-if="actionsFor?.can_delete"
                type="button"
                class="flex h-12 w-full items-center gap-3 rounded-md px-3 text-left text-danger hover:bg-surface-2"
                @click="actOnSheet(remove)"
            >
                <Trash :size="18" aria-hidden="true" />
                {{ t('messages.show.delete') }}
            </button>
        </div>
    </AppDialog>

    <EmojiPicker v-model:open="pickerOpen" @select="onPicked" />
</template>
