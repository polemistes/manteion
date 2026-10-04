<script setup lang="ts">
import { Form, Link, router, usePage } from '@inertiajs/vue3';
import { Ellipsis, Flag, Heart, SendHorizontal, Trash } from '@lucide/vue';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import Avatar from '@/components/ui/Avatar.vue';
import Menu from '@/components/ui/Menu.vue';
import MenuItem from '@/components/ui/MenuItem.vue';
import Tag from '@/components/ui/Tag.vue';
import TextArea from '@/components/ui/TextArea.vue';
import { confirmAction } from '@/composables/useConfirm';
import { report } from '@/composables/useReport';
import { staffDelete } from '@/composables/useStaffDelete';
import { useDates } from '@/lib/dates';
import { linkify } from '@/lib/text';
import { toastError } from '@/lib/toast';
import type { ReactionSummary } from '@/reactions';
import { requestJson } from '@/realtime';
import * as eventComments from '@/routes/events/comments';
import { store as reactionsStore } from '@/routes/reactions';
import { show as usersShow } from '@/routes/users';

interface Person {
    id: number;
    name: string;
    username: string;
    avatar_url: string | null;
}

export interface EventCommentItem {
    id: number;
    body: string;
    is_deleted: boolean;
    created_at: string;
    user: Person;
    can_delete: boolean;
    can_report: boolean;
    reactions: ReactionSummary[];
}

/**
 * The event's comments: who, when, what, a like (heart) and Reply, which
 * starts an answer with their @name. The composer posts in place.
 */
const props = defineProps<{
    eventId: number;
    hostId: number;
    comments: EventCommentItem[];
    canComment: boolean;
}>();

const { t } = useI18n();
const page = usePage();
const dates = useDates();

const text = ref('');
const field = ref<InstanceType<typeof TextArea> | null>(null);

// Likes change at once; the server's answer replaces them.
const likes = ref(new Map<number, ReactionSummary[]>());

function reactionsOf(comment: EventCommentItem): ReactionSummary[] {
    return likes.value.get(comment.id) ?? comment.reactions;
}

function heart(comment: EventCommentItem): { count: number; mine: boolean } {
    const reactions = reactionsOf(comment);
    const count = reactions.reduce(
        (total, reaction) => total + reaction.count,
        0,
    );

    return { count, mine: reactions.some((reaction) => reaction.reacted) };
}

async function toggleLike(comment: EventCommentItem): Promise<void> {
    const before = reactionsOf(comment);
    const { mine } = heart(comment);
    const next = mine
        ? before
              .map((reaction) =>
                  reaction.reacted
                      ? {
                            ...reaction,
                            count: reaction.count - 1,
                            reacted: false,
                        }
                      : reaction,
              )
              .filter((reaction) => reaction.count > 0)
        : [...before, { emoji: '❤️', count: 1, reacted: true }];

    likes.value = new Map(likes.value).set(comment.id, next);

    try {
        const response = await requestJson<{ reactions: ReactionSummary[] }>(
            reactionsStore(),
            {
                reactable_type: 'comment',
                reactable_id: comment.id,
                emoji: '❤️',
            },
        );

        if (response?.reactions) {
            likes.value = new Map(likes.value).set(
                comment.id,
                response.reactions,
            );
        }
    } catch {
        likes.value = new Map(likes.value).set(comment.id, before);
        toastError(t('ui.somethingWentWrong'));
    }
}

function reply(comment: EventCommentItem): void {
    text.value = `@${comment.user.username} `;
    field.value?.focus();
}

async function remove(comment: EventCommentItem): Promise<void> {
    const destroyUrl = eventComments.destroy([props.eventId, comment.id]).url;

    if (
        comment.user.id !== page.props.auth.user.id &&
        page.props.auth.user.role !== 'member'
    ) {
        const choice = await staffDelete({
            message: t('moderation.confirmDeleteContent'),
        });

        if (choice !== null) {
            router.delete(destroyUrl, {
                preserveScroll: true,
                data: { leave_placeholder: choice === 'placeholder' },
            });
        }

        return;
    }

    const confirmed = await confirmAction({
        title: t('events.show.confirmDeleteCommentTitle'),
        message: t('events.show.confirmDeleteComment'),
        confirmLabel: t('events.show.deleteComment'),
    });

    if (confirmed) {
        router.delete(destroyUrl, { preserveScroll: true });
    }
}
</script>

<template>
    <section>
        <h2 class="mb-4 flex items-baseline gap-2 font-display text-title-3">
            {{ t('events.show.comments') }}
            <span
                v-if="props.comments.length"
                class="font-sans text-meta font-normal text-ink-muted"
                >{{ props.comments.length }}</span
            >
        </h2>

        <p
            v-if="!props.comments.length"
            class="mb-5 font-display text-input text-ink-muted italic"
        >
            {{ t('events.show.noComments') }}
        </p>

        <ul class="mb-5 flex flex-col gap-5">
            <li
                v-for="comment in props.comments"
                :key="comment.id"
                class="flex gap-3"
            >
                <Link :href="usersShow(comment.user.id).url" class="shrink-0">
                    <Avatar
                        :name="comment.user.name"
                        :src="comment.user.avatar_url"
                        :size="36"
                    />
                </Link>
                <div class="min-w-0 flex-1">
                    <p class="flex flex-wrap items-center gap-x-2 gap-y-0.5">
                        <Link
                            :href="usersShow(comment.user.id).url"
                            class="text-label font-semibold text-ink hover:underline"
                            >{{ comment.user.name }}</Link
                        >
                        <Tag
                            v-if="comment.user.id === props.hostId"
                            tone="host"
                            >{{ t('events.show.hostTag') }}</Tag
                        >
                        <span class="text-meta text-ink-subtle">{{
                            dates.ago(comment.created_at)
                        }}</span>
                    </p>
                    <p
                        class="mt-0.5 whitespace-pre-line text-ink"
                        :class="
                            comment.is_deleted ? 'text-ink-subtle italic' : ''
                        "
                    >
                        <template v-if="comment.is_deleted && !comment.body">{{
                            t('events.show.deletedComment')
                        }}</template>
                        <template
                            v-for="(segment, index) in linkify(comment.body)"
                            v-else
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
                        v-if="!comment.is_deleted"
                        class="mt-1 -ml-2 flex items-center gap-1"
                    >
                        <button
                            v-if="props.canComment"
                            type="button"
                            class="h-9 rounded-full px-2 text-label font-semibold text-ink-muted hover:bg-surface-2 hover:text-ink"
                            @click="reply(comment)"
                        >
                            {{ t('events.show.reply') }}
                        </button>
                        <button
                            type="button"
                            :aria-pressed="heart(comment).mine"
                            :aria-label="
                                heart(comment).mine
                                    ? t('events.show.unlike')
                                    : t('events.show.like')
                            "
                            class="inline-flex h-9 min-w-11 items-center justify-center gap-1 rounded-full px-2 text-label font-semibold tabular-nums hover:bg-surface-2"
                            :class="
                                heart(comment).mine
                                    ? 'text-accent-ink'
                                    : 'text-ink-muted'
                            "
                            @click="toggleLike(comment)"
                        >
                            <Heart
                                :size="16"
                                :stroke-width="2"
                                :fill="
                                    heart(comment).mine
                                        ? 'currentColor'
                                        : 'none'
                                "
                                aria-hidden="true"
                            />
                            <span v-if="heart(comment).count">{{
                                heart(comment).count
                            }}</span>
                        </button>
                        <Menu
                            v-if="comment.can_delete || comment.can_report"
                            align="start"
                        >
                            <template #trigger>
                                <button
                                    type="button"
                                    :aria-label="
                                        t('events.show.commentActions')
                                    "
                                    class="grid size-9 place-items-center rounded-full text-ink-subtle hover:bg-surface-2 hover:text-ink"
                                >
                                    <Ellipsis
                                        :size="16"
                                        :stroke-width="2"
                                        aria-hidden="true"
                                    />
                                </button>
                            </template>
                            <MenuItem
                                v-if="comment.can_report"
                                :icon="Flag"
                                @select="report('comment', comment.id)"
                                >{{ t('events.show.report') }}</MenuItem
                            >
                            <MenuItem
                                v-if="comment.can_delete"
                                :icon="Trash"
                                danger
                                @select="remove(comment)"
                                >{{ t('events.show.deleteComment') }}</MenuItem
                            >
                        </Menu>
                    </div>
                </div>
            </li>
        </ul>

        <Form
            v-if="props.canComment"
            v-bind="eventComments.store.form(props.eventId)"
            reset-on-success
            :options="{ preserveScroll: true }"
            #default="{ errors, processing }"
            class="flex items-start gap-3"
            @success="text = ''"
        >
            <Avatar
                :name="page.props.auth.user.name"
                :src="page.props.auth.user.avatar_url"
                :size="36"
                decorative
                class="mt-1"
            />
            <div class="min-w-0 flex-1">
                <div
                    class="flex min-h-11 items-end rounded-xl bg-surface pr-1 pl-4 ring-1 ring-line-strong ring-inset focus-within:ring-2 focus-within:ring-ink"
                >
                    <div class="min-w-0 flex-1">
                        <TextArea
                            ref="field"
                            v-model="text"
                            name="body"
                            :label="t('events.show.commentPlaceholder')"
                            hide-label
                            bare
                            :max-rows="8"
                            rows="1"
                            required
                            maxlength="2000"
                            :placeholder="t('events.show.commentPlaceholder')"
                            class="py-2.5"
                        />
                    </div>
                    <button
                        type="submit"
                        :disabled="processing || !text.trim()"
                        :aria-label="t('events.show.send')"
                        class="mb-0.5 grid size-10 shrink-0 place-items-center rounded-full text-ink hover:bg-surface-2 disabled:text-ink-subtle"
                    >
                        <SendHorizontal
                            :size="20"
                            :stroke-width="1.8"
                            aria-hidden="true"
                        />
                    </button>
                </div>
                <p
                    v-if="errors.body"
                    class="mt-1.5 text-meta font-medium text-danger"
                >
                    {{ errors.body }}
                </p>
            </div>
        </Form>
        <p v-else class="text-meta text-ink-muted">
            {{ t('events.show.commentsDisabled') }}
        </p>
    </section>
</template>
