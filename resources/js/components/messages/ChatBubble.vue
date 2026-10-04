<script setup lang="ts">
import { Copy, Ellipsis, Flag, SmilePlus, Trash } from '@lucide/vue';
import { computed, onBeforeUnmount } from 'vue';
import { useI18n } from 'vue-i18n';
import Avatar from '@/components/ui/Avatar.vue';
import Menu from '@/components/ui/Menu.vue';
import MenuItem from '@/components/ui/MenuItem.vue';
import MenuSeparator from '@/components/ui/MenuSeparator.vue';
import { useDates } from '@/lib/dates';
import { isEmojiOnly, linkify } from '@/lib/text';
import { BUBBLE_CORNERS, QUICK_REACTIONS } from './chat';
import type { BubblePosition, ChatMessage } from './chat';

/**
 * One message in a thread. Consecutive messages from the same person form
 * a group: corners on the sender's side tighten inside it, the name sits
 * above the first bubble (others, in groups), the face and the time beside
 * and under the last.
 */
const props = withDefaults(
    defineProps<{
        message: ChatMessage;
        position: BubblePosition;
        showName: boolean;
        showAvatar: boolean;
        reserveAvatar: boolean;
        showTime: boolean;
        /** Who has read this one (your newest message only). */
        seenBy?: { id: number; name: string; avatar_url: string | null }[];
        /** "Seen" in a direct chat, the readers' faces in a group. */
        seenAs?: 'caption' | 'faces';
    }>(),
    { seenBy: () => [], seenAs: 'caption' },
);

const emit = defineEmits<{
    react: [emoji: string];
    pickReaction: [];
    actions: [];
    copy: [];
    delete: [];
    report: [];
    retry: [];
}>();

const { t, locale } = useI18n();
const { time } = useDates();

const mine = computed(() => props.message.is_mine);

const seenLabel = computed(() =>
    t('messages.show.seenBy', {
        names: new Intl.ListFormat(locale.value === 'nb' ? 'nb' : 'en', {
            type: 'conjunction',
        }).format(props.seenBy.map((person) => person.name)),
    }),
);
const deleted = computed(() => props.message.is_deleted);
const body = computed(() => props.message.body ?? '');
const emojiOnly = computed(() => !deleted.value && isEmojiOnly(body.value));
const segments = computed(() => linkify(body.value));

const corners = computed(
    () => BUBBLE_CORNERS[mine.value ? 'mine' : 'theirs'][props.position],
);

const canAct = computed(() => !deleted.value && !props.message.pending);

/** The time (or "Seen") line under the bubble. */
const hasFoot = computed(
    () => !props.message.pending && (props.showTime || props.seenBy.length > 0),
);

// Long-press on touch opens the actions sheet.
let pressTimer: ReturnType<typeof setTimeout> | undefined;

function onPointerDown(event: PointerEvent): void {
    if (event.pointerType !== 'touch' || !canAct.value) {
        return;
    }

    pressTimer = setTimeout(() => emit('actions'), 450);
}

function cancelPress(): void {
    clearTimeout(pressTimer);
}

onBeforeUnmount(cancelPress);
</script>

<template>
    <div
        class="group/msg flex items-end gap-2"
        :class="[
            mine ? 'flex-row-reverse' : '',
            // Room under the pills for their hit area, clear of the next
            // bubble (with a time line under it, the line makes the room).
            props.message.reactions.length
                ? hasFoot
                    ? 'mb-[13px]'
                    : 'mb-[21px]'
                : '',
        ]"
    >
        <!-- Level with the bubble's foot, not the time line under it. -->
        <div
            v-if="props.reserveAvatar"
            class="w-7 shrink-0"
            :class="
                props.showTime && !props.message.pending
                    ? props.message.reactions.length
                        ? 'mb-[35px]'
                        : 'mb-5'
                    : ''
            "
        >
            <Avatar
                v-if="props.showAvatar"
                :name="props.message.sender.name"
                :src="props.message.sender.avatar_url"
                :size="28"
                decorative
            />
        </div>

        <div
            class="flex max-w-[min(78%,520px)] min-w-0 flex-col"
            :class="mine ? 'items-end' : 'items-start'"
        >
            <p
                v-if="props.showName"
                class="mb-1 px-3.5 text-caption font-semibold text-ink-muted"
            >
                {{ props.message.sender.name }}
            </p>

            <div
                class="relative flex items-center gap-1.5"
                :class="mine ? 'flex-row-reverse' : ''"
            >
                <div
                    class="relative min-w-0 animate-[rise-in_160ms_var(--ease-enter)] select-text"
                    @pointerdown="onPointerDown"
                    @pointerup="cancelPress"
                    @pointerleave="cancelPress"
                    @pointercancel="cancelPress"
                    @contextmenu="canAct && $event.preventDefault()"
                >
                    <p
                        v-if="deleted"
                        class="rounded-lg border border-dashed border-line-strong px-3.5 py-2 text-chat text-ink-subtle italic"
                        :class="corners"
                    >
                        {{ props.message.body || t('messages.show.deleted') }}
                    </p>
                    <p
                        v-else-if="emojiOnly"
                        class="px-0.5 text-[40px] leading-[48px]"
                    >
                        {{ body }}
                    </p>
                    <p
                        v-else
                        class="rounded-lg px-3.5 py-2 text-chat [overflow-wrap:anywhere] whitespace-pre-wrap"
                        :class="[
                            corners,
                            mine
                                ? 'bg-bubble-mine text-on-bubble-mine'
                                : 'bg-bubble-theirs text-ink ring-1 ring-line ring-inset',
                            props.message.pending === 'sending'
                                ? 'opacity-70'
                                : '',
                        ]"
                    >
                        <template
                            v-for="(segment, index) in segments"
                            :key="index"
                        >
                            <a
                                v-if="segment.kind === 'link'"
                                :href="segment.href"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="underline underline-offset-2"
                                >{{ segment.value }}</a
                            >
                            <template v-else>{{ segment.value }}</template>
                        </template>
                    </p>

                    <div
                        v-if="props.message.reactions.length"
                        class="absolute -bottom-[15px] z-[1] flex gap-1"
                        :class="mine ? 'left-3' : 'right-3'"
                    >
                        <button
                            v-for="reaction in props.message.reactions"
                            :key="reaction.emoji"
                            type="button"
                            :aria-pressed="reaction.reacted"
                            :aria-label="
                                t('messages.show.react', {
                                    emoji: reaction.emoji,
                                })
                            "
                            class="relative inline-flex h-6 animate-[pop-in_160ms_var(--ease-enter)] items-center gap-0.5 rounded-full px-[7px] text-caption font-semibold tabular-nums shadow-xs ring-1 after:absolute after:-inset-x-0.5 after:top-0 after:-bottom-2"
                            :class="
                                reaction.reacted
                                    ? 'bg-accent-soft text-accent-ink ring-accent-line'
                                    : 'bg-surface text-ink-muted ring-line'
                            "
                            @click="emit('react', reaction.emoji)"
                        >
                            <span class="text-[15px] leading-none">{{
                                reaction.emoji
                            }}</span>
                            <span v-if="reaction.count > 1">{{
                                reaction.count
                            }}</span>
                        </button>
                    </div>
                </div>

                <!-- Pointer devices: quick reactions and more, floating just
                     above the bubble so they take none of its width. -->
                <div
                    v-if="canAct"
                    class="invisible absolute bottom-full z-10 mb-1 flex items-center gap-0.5 rounded-full bg-surface p-0.5 opacity-0 shadow-sm ring-1 ring-line transition-opacity duration-120 group-hover/msg:visible group-hover/msg:opacity-100 group-has-[:focus-visible]/msg:visible group-has-[:focus-visible]/msg:opacity-100 has-[[data-state=open]]:visible has-[[data-state=open]]:opacity-100 pointer-coarse:hidden"
                    :class="mine ? 'right-0' : 'left-0'"
                    role="toolbar"
                    :aria-label="t('messages.show.actions')"
                >
                    <button
                        v-for="emoji in QUICK_REACTIONS"
                        :key="emoji"
                        type="button"
                        :aria-label="t('messages.show.react', { emoji })"
                        class="grid size-8 place-items-center rounded-full text-[17px] leading-none hover:bg-surface-2"
                        @click="emit('react', emoji)"
                    >
                        {{ emoji }}
                    </button>
                    <button
                        type="button"
                        :aria-label="t('messages.show.moreReactions')"
                        class="grid size-8 place-items-center rounded-full text-ink-muted hover:bg-surface-2 hover:text-ink"
                        @click="emit('pickReaction')"
                    >
                        <SmilePlus
                            :size="17"
                            :stroke-width="1.9"
                            aria-hidden="true"
                        />
                    </button>
                    <span class="mx-0.5 h-5 w-px bg-line" aria-hidden="true" />
                    <Menu :align="mine ? 'end' : 'start'">
                        <template #trigger>
                            <button
                                type="button"
                                :aria-label="t('ui.more')"
                                class="grid size-8 place-items-center rounded-full text-ink-muted hover:bg-surface-2 hover:text-ink"
                            >
                                <Ellipsis
                                    :size="17"
                                    :stroke-width="1.9"
                                    aria-hidden="true"
                                />
                            </button>
                        </template>
                        <MenuItem :icon="Copy" @select="emit('copy')">{{
                            t('messages.show.copy')
                        }}</MenuItem>
                        <template
                            v-if="
                                props.message.can_delete ||
                                props.message.can_report
                            "
                        >
                            <MenuSeparator />
                            <MenuItem
                                v-if="props.message.can_delete"
                                :icon="Trash"
                                danger
                                @select="emit('delete')"
                                >{{ t('messages.show.delete') }}</MenuItem
                            >
                            <MenuItem
                                v-if="props.message.can_report"
                                :icon="Flag"
                                @select="emit('report')"
                                >{{ t('messages.show.report') }}</MenuItem
                            >
                        </template>
                    </Menu>
                </div>
            </div>

            <p
                v-if="props.message.pending === 'failed'"
                class="mt-1 px-1 text-caption text-danger"
            >
                {{ t('messages.show.failed') }}
                <button
                    type="button"
                    class="font-semibold underline underline-offset-2"
                    @click="emit('retry')"
                >
                    {{ t('messages.show.retry') }}
                </button>
            </p>
            <p
                v-else-if="props.message.pending === 'sending'"
                class="mt-1 px-1 text-caption text-ink-subtle"
            >
                {{ t('messages.show.sending') }}
            </p>
            <p
                v-else-if="props.showTime || props.seenBy.length"
                class="flex items-center gap-1.5 px-1 text-caption text-ink-subtle"
                :class="[
                    props.message.reactions.length ? 'mt-[19px]' : 'mt-1',
                    mine ? 'justify-end' : '',
                ]"
            >
                <span>{{ time(props.message.created_at) }}</span>
                <template v-if="props.seenBy.length">
                    <span
                        v-if="props.seenAs === 'caption'"
                        :aria-label="seenLabel"
                        >· {{ t('messages.show.seen') }}</span
                    >
                    <span
                        v-else
                        class="flex items-center"
                        role="img"
                        :aria-label="seenLabel"
                        :title="seenLabel"
                    >
                        <Avatar
                            v-for="(person, index) in props.seenBy.slice(0, 3)"
                            :key="person.id"
                            :name="person.name"
                            :src="person.avatar_url"
                            :size="16"
                            ring
                            decorative
                            :class="index > 0 ? '-ml-1' : ''"
                        />
                        <span
                            v-if="props.seenBy.length > 3"
                            class="ml-1 tabular-nums"
                            aria-hidden="true"
                            >+{{ props.seenBy.length - 3 }}</span
                        >
                    </span>
                </template>
            </p>
        </div>
    </div>
</template>
