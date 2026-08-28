<script setup lang="ts">
import { Form, Head, Link, router, usePage, usePoll } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import FieldError from '@/components/FieldError.vue';
import PersonSummary from '@/components/PersonSummary.vue';
import ReportButton from '@/components/ReportButton.vue';
import { confirmAction } from '@/composables/useConfirm';
import { staffDelete } from '@/composables/useStaffDelete';
import { destroy as destroyEvent, edit as editEvent } from '@/routes/events';
import * as eventAttendance from '@/routes/events/attendance';
import * as eventComments from '@/routes/events/comments';
import * as eventInvitations from '@/routes/events/invitations';
import * as eventSearchable from '@/routes/events/searchable';

const { t, te, locale } = useI18n();

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

interface Tag {
    id: number;
    name: string;
    slug: string;
}

interface Attendee {
    id: number;
    user: Person;
    status: string | null;
}

interface Comment {
    id: number;
    body: string;
    is_deleted: boolean;
    created_at: string;
    user: Person;
    can_delete: boolean;
    can_report: boolean;
}

const props = defineProps<{
    event: {
        id: number;
        name: string;
        place: string | null;
        city: string | null;
        country: string | null;
        description: string;
        starts_at: string;
        ends_at: string | null;
        visibility: string;
        allow_invitee_invites: boolean;
        allow_invitee_comments: boolean;
        is_searchable_paid: boolean;
        owner: Person;
        tags: Tag[];
        images: string[];
        is_owner: boolean;
        is_deleted: boolean;
        can_invite: boolean;
        can_comment: boolean;
        can_delete: boolean;
        can_report: boolean;
    };
    myAttendance: { status: string; attendance_visibility: string } | null;
    attendees: Attendee[];
    comments: Comment[];
    inviteCandidates: Person[];
}>();

// myAttendance is deliberately excluded: its <select> elements are bound via
// :value rather than v-model, so refreshing it mid-edit would silently
// discard a selection the viewer hasn't submitted yet.
usePoll(15000, { only: ['attendees', 'comments'] });

function formatDate(value: string): string {
    return new Date(value).toLocaleString(
        dateFnsLocales[locale.value] ?? locale.value,
        { dateStyle: 'medium', timeStyle: 'short' },
    );
}

const statusKeys: Record<string, string> = {
    no_response: 'events.show.statusNoResponse',
    attending: 'events.show.statusAttending',
    interested: 'events.show.statusInterested',
    not_attending: 'events.show.statusNotAttending',
};

function statusLabel(status: string): string {
    return t(statusKeys[status] ?? status);
}

function categoryLabel(tag: Tag): string {
    const key = `categories.${tag.slug}`;

    return te(key) ? t(key) : tag.name;
}

const page = usePage();
const authUserId = computed(() => page.props.auth.user.id);

async function deleteEvent(): Promise<void> {
    // Events don't offer the "leave a placeholder" choice (unlike comments
    // and messages) — deleting one, whether as the owner or as staff, just
    // deletes it.
    const confirmed = await confirmAction({
        message: t('events.show.confirmDeleteEvent'),
    });

    if (!confirmed) {
        return;
    }

    router.delete(destroyEvent(props.event.id).url);
}

async function deleteComment(comment: Comment): Promise<void> {
    if (comment.user.id !== authUserId.value) {
        const choice = await staffDelete({
            message: t('moderation.confirmDeleteContent'),
        });

        if (choice === null) {
            return;
        }

        router.delete(
            eventComments.destroy({
                event: props.event.id,
                comment: comment.id,
            }).url,
            { data: { leave_placeholder: choice === 'placeholder' } },
        );

        return;
    }

    const confirmed = await confirmAction({
        message: t('events.show.confirmDeleteComment'),
    });

    if (!confirmed) {
        return;
    }

    router.delete(
        eventComments.destroy({
            event: props.event.id,
            comment: comment.id,
        }).url,
    );
}

const locationLabel =
    props.event.place ||
    [props.event.city, props.event.country].filter(Boolean).join(', ') ||
    null;
</script>

<template>
    <Head :title="props.event.name" />

    <div class="mb-4 flex items-start justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold">{{ props.event.name }}</h1>
            <p class="text-sm text-black/70">
                {{ formatDate(props.event.starts_at) }}
                <template v-if="props.event.ends_at">
                    – {{ formatDate(props.event.ends_at) }}
                </template>
                <template v-if="locationLabel"> · {{ locationLabel }}</template>
            </p>
            <p class="mt-1 text-sm">
                {{ t('events.show.by') }}
                <PersonSummary v-bind="props.event.owner" />
            </p>
        </div>

        <div
            v-if="
                props.event.is_owner ||
                props.event.can_delete ||
                props.event.can_report
            "
            class="flex shrink-0 gap-2"
        >
            <Link
                v-if="props.event.is_owner"
                :href="editEvent(props.event.id).url"
                class="border border-black px-3 py-2 text-sm hover:bg-black hover:text-white"
            >
                {{ t('events.show.edit') }}
            </Link>
            <button
                v-if="props.event.can_delete"
                type="button"
                class="border border-black px-3 py-2 text-sm hover:bg-black hover:text-white"
                @click="deleteEvent"
            >
                {{ t('events.show.delete') }}
            </button>
            <ReportButton
                v-if="props.event.can_report"
                reportable-type="event"
                :reportable-id="props.event.id"
            />
        </div>
    </div>

    <div class="mb-4 flex flex-wrap gap-2">
        <span
            v-for="tag in props.event.tags"
            :key="tag.id"
            class="border border-black px-2 py-0.5 text-xs"
        >
            {{ categoryLabel(tag) }}
        </span>
        <span
            class="border border-black px-2 py-0.5 text-xs font-bold uppercase"
        >
            {{
                props.event.visibility === 'private'
                    ? t('events.show.visibilityLabelPrivate')
                    : t('events.show.visibilityLabelPublic')
            }}
        </span>
    </div>

    <div v-if="props.event.images.length" class="mb-6 flex flex-wrap gap-3">
        <img
            v-for="(image, index) in props.event.images"
            :key="index"
            :src="image"
            class="h-32 w-32 border border-black object-cover"
            alt=""
        />
    </div>

    <p
        class="mb-8 max-w-prose font-sans text-sm whitespace-pre-line"
        :class="{ 'text-black/50 italic': props.event.is_deleted }"
    >
        {{ props.event.description }}
    </p>

    <section
        v-if="props.event.is_owner"
        class="mb-8 border-[3px] border-black p-4"
    >
        <h2 class="mb-2 text-sm font-bold uppercase">
            {{ t('events.show.ownerTools') }}
        </h2>
        <p class="mb-2 text-sm">
            {{ t('events.show.searchable') }}
            <span class="font-bold">{{
                props.event.is_searchable_paid
                    ? t('events.show.yes')
                    : t('events.show.no')
            }}</span>
        </p>
        <Form
            v-if="!props.event.is_searchable_paid"
            v-bind="eventSearchable.store.form(props.event.id)"
            #default="{ processing }"
        >
            <button
                type="submit"
                :disabled="processing"
                class="border border-black px-3 py-2 text-sm hover:bg-black hover:text-white"
            >
                {{ t('events.show.makeSearchable') }}
            </button>
        </Form>
    </section>

    <section class="mb-8">
        <h2 class="mb-3 text-sm font-bold uppercase">
            {{ t('events.show.yourResponse') }}
        </h2>
        <Form
            v-bind="eventAttendance.update.form(props.event.id)"
            #default="{ errors, processing }"
            class="flex flex-wrap items-end gap-3"
        >
            <div>
                <label for="status" class="mb-1 block text-xs font-bold">{{
                    t('events.show.status')
                }}</label>
                <select
                    id="status"
                    name="status"
                    :value="props.myAttendance?.status ?? 'no_response'"
                    class="border border-black bg-white px-2 py-1 text-sm"
                >
                    <option value="no_response">
                        {{ t('events.show.statusNoResponse') }}
                    </option>
                    <option value="attending">
                        {{ t('events.show.statusAttending') }}
                    </option>
                    <option value="interested">
                        {{ t('events.show.statusInterested') }}
                    </option>
                    <option value="not_attending">
                        {{ t('events.show.statusNotAttending') }}
                    </option>
                </select>
                <FieldError :message="errors.status" />
            </div>

            <div>
                <label
                    for="attendance_visibility"
                    class="mb-1 block text-xs font-bold"
                >
                    {{ t('events.show.whoCanSee') }}
                </label>
                <select
                    id="attendance_visibility"
                    name="attendance_visibility"
                    :value="
                        props.myAttendance?.attendance_visibility ?? 'everyone'
                    "
                    class="border border-black bg-white px-2 py-1 text-sm"
                >
                    <option value="everyone">
                        {{ t('events.show.visibilityEveryone') }}
                    </option>
                    <option value="friends">
                        {{ t('events.show.visibilityFriends') }}
                    </option>
                    <option value="none">
                        {{ t('events.show.visibilityNone') }}
                    </option>
                </select>
                <FieldError :message="errors.attendance_visibility" />
            </div>

            <button
                type="submit"
                :disabled="processing"
                class="border border-black bg-black px-3 py-2 text-sm text-white hover:bg-white hover:text-black"
            >
                {{
                    processing
                        ? t('events.show.saving')
                        : t('events.show.saveResponse')
                }}
            </button>
        </Form>
    </section>

    <section v-if="props.event.can_invite" class="mb-8">
        <h2 class="mb-3 text-sm font-bold uppercase">
            {{ t('events.show.inviteFriends') }}
        </h2>
        <Form
            v-if="props.inviteCandidates.length"
            v-bind="eventInvitations.store.form(props.event.id)"
            #default="{ errors, processing }"
            class="flex flex-wrap items-end gap-3"
        >
            <select
                name="user_ids[]"
                multiple
                class="min-w-48 border border-black bg-white px-2 py-1 text-sm"
            >
                <option
                    v-for="friend in props.inviteCandidates"
                    :key="friend.id"
                    :value="friend.id"
                >
                    {{ friend.name }} · @{{ friend.username }}
                </option>
            </select>
            <button
                type="submit"
                :disabled="processing"
                class="border border-black px-3 py-2 text-sm hover:bg-black hover:text-white"
            >
                {{ t('events.show.invite') }}
            </button>
            <FieldError :message="errors.user_ids" />
        </Form>
        <p v-else class="text-sm text-black/70">
            {{ t('events.show.noInviteCandidates') }}
        </p>
    </section>

    <section class="mb-8">
        <h2 class="mb-3 text-sm font-bold uppercase">
            {{ t('events.show.whosOnTheList') }}
        </h2>
        <ul class="flex flex-col gap-2">
            <li
                v-for="attendee in props.attendees"
                :key="attendee.id"
                class="flex items-center justify-between border-[3px] border-black px-3 py-2 text-sm"
            >
                <PersonSummary v-bind="attendee.user" />
                <span class="text-xs font-bold">
                    {{
                        attendee.status
                            ? statusLabel(attendee.status)
                            : t('events.show.hidden')
                    }}
                </span>
            </li>
            <li v-if="!props.attendees.length" class="text-sm text-black/70">
                {{ t('events.show.noOneYet') }}
            </li>
        </ul>
    </section>

    <section>
        <h2 class="mb-3 text-sm font-bold uppercase">
            {{ t('events.show.comments') }}
        </h2>

        <ul class="mb-4 flex flex-col gap-3">
            <li
                v-for="comment in props.comments"
                :key="comment.id"
                class="border-[3px] border-black p-3"
            >
                <div class="mb-1 flex items-center justify-between gap-2">
                    <PersonSummary v-bind="comment.user" />
                    <div class="flex items-center gap-2">
                        <ReportButton
                            v-if="comment.can_report"
                            reportable-type="comment"
                            :reportable-id="comment.id"
                        />
                        <button
                            v-if="comment.can_delete && !comment.is_deleted"
                            type="button"
                            class="text-xs underline"
                            @click="deleteComment(comment)"
                        >
                            {{ t('events.show.deleteComment') }}
                        </button>
                    </div>
                </div>
                <p
                    class="font-sans text-sm whitespace-pre-line"
                    :class="{ 'text-black/50 italic': comment.is_deleted }"
                >
                    {{ comment.body }}
                </p>
                <p class="mt-1 text-xs text-black/70">
                    {{ formatDate(comment.created_at) }}
                </p>
            </li>
            <li v-if="!props.comments.length" class="text-sm text-black/70">
                {{ t('events.show.noComments') }}
            </li>
        </ul>

        <Form
            v-if="props.event.can_comment"
            v-bind="eventComments.store.form(props.event.id)"
            reset-on-success
            #default="{ errors, processing }"
            class="flex flex-col gap-2"
        >
            <textarea
                name="body"
                required
                rows="3"
                :placeholder="t('events.show.commentPlaceholder')"
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
                        ? t('events.show.posting')
                        : t('events.show.postComment')
                }}
            </button>
        </Form>
        <p v-else class="text-sm text-black/70">
            {{ t('events.show.commentsDisabled') }}
        </p>
    </section>
</template>
