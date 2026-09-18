<script setup lang="ts">
import { Form, Head, Link, router, usePage, usePoll } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import FieldError from '@/components/FieldError.vue';
import Modal from '@/components/Modal.vue';
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
    status: string;
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

type GuestGroup = 'attending' | 'interested' | 'no_response' | 'not_attending';

const guestTabs: GuestGroup[] = [
    'attending',
    'interested',
    'no_response',
    'not_attending',
];
const guestsOpen = ref(false);
const guestTab = ref<GuestGroup>('attending');

const guestGroups = computed<Record<GuestGroup, Attendee[]>>(() => ({
    attending: props.attendees.filter((a) => a.status === 'attending'),
    interested: props.attendees.filter((a) => a.status === 'interested'),
    no_response: props.attendees.filter((a) => a.status === 'no_response'),
    not_attending: props.attendees.filter((a) => a.status === 'not_attending'),
}));

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

    <!-- Details to the left, the image to the right from the very top;
         stacked with the image first on narrow screens. -->
    <div
        class="mb-8 grid grid-cols-1 gap-4"
        :class="{ 'md:grid-cols-2': props.event.images.length }"
    >
        <div class="min-w-0">
            <div class="mb-1 flex items-start justify-between gap-3">
                <h1 class="text-xl font-bold">{{ props.event.name }}</h1>
                <div
                    v-if="
                        props.event.can_report ||
                        (props.event.can_delete && !props.event.is_owner)
                    "
                    class="flex shrink-0 items-center gap-2"
                >
                    <ReportButton
                        v-if="props.event.can_report"
                        reportable-type="event"
                        :reportable-id="props.event.id"
                    />
                    <button
                        v-if="props.event.can_delete && !props.event.is_owner"
                        type="button"
                        class="border border-edge px-3 py-2 text-sm hover:bg-accent hover:text-on-accent"
                        @click="deleteEvent"
                    >
                        {{ t('events.show.delete') }}
                    </button>
                </div>
            </div>
            <p class="text-sm text-ink/70">
                {{ formatDate(props.event.starts_at) }}
                <template v-if="props.event.ends_at">
                    – {{ formatDate(props.event.ends_at) }}
                </template>
                <template v-if="locationLabel"> · {{ locationLabel }}</template>
            </p>
            <div
                class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm"
            >
                <span>
                    {{ t('events.show.by') }}
                    <PersonSummary v-bind="props.event.owner" />
                </span>
                <button
                    type="button"
                    class="border border-edge px-2 py-0.5 text-xs hover:bg-accent hover:text-on-accent"
                    :title="t('events.show.whosOnTheList')"
                    @click="guestsOpen = true"
                >
                    {{
                        t('events.show.guestCounts', {
                            coming: guestGroups.attending.length,
                            maybe: guestGroups.interested.length,
                            invited: guestGroups.no_response.length,
                        })
                    }}
                </button>
            </div>

            <div class="mt-3 mb-4 flex flex-wrap gap-2">
                <span
                    v-for="tag in props.event.tags"
                    :key="tag.id"
                    class="border border-edge px-2 py-0.5 text-xs"
                >
                    {{ categoryLabel(tag) }}
                </span>
                <span
                    class="border border-edge px-2 py-0.5 text-xs font-bold uppercase"
                >
                    {{
                        props.event.visibility === 'private'
                            ? t('events.show.visibilityLabelPrivate')
                            : t('events.show.visibilityLabelPublic')
                    }}
                </span>
            </div>

            <p
                class="min-w-0 rounded-card bg-surface-inset px-4 py-3 font-sans text-lg whitespace-pre-line"
                :class="{ 'text-ink/50 italic': props.event.is_deleted }"
            >
                {{ props.event.description }}
            </p>
        </div>

        <div
            v-if="props.event.images.length"
            class="order-first flex flex-col gap-3 md:order-last"
        >
            <img
                v-for="(image, index) in props.event.images"
                :key="index"
                :src="image"
                class="w-full border border-edge object-cover"
                alt=""
            />
        </div>
    </div>

    <Modal :open="guestsOpen" @update:open="guestsOpen = $event">
        <h2 class="mb-3 text-sm font-bold uppercase">
            {{ t('events.show.whosOnTheList') }}
        </h2>
        <div class="mb-3 flex flex-wrap" role="tablist">
            <button
                v-for="(group, index) in guestTabs"
                :key="group"
                type="button"
                role="tab"
                :aria-selected="guestTab === group"
                class="border border-edge px-2 py-1 text-xs"
                :class="[
                    index > 0 ? '-ml-px' : '',
                    guestTab === group
                        ? 'bg-accent text-on-accent'
                        : 'hover:bg-accent hover:text-on-accent',
                ]"
                @click="guestTab = group"
            >
                {{ t(`events.show.guestTabs.${group}`) }}
                ({{ guestGroups[group].length }})
            </button>
        </div>
        <ul class="flex max-h-80 flex-col gap-2 overflow-y-auto">
            <li
                v-for="attendee in guestGroups[guestTab]"
                :key="attendee.id"
                class="text-sm"
            >
                <PersonSummary v-bind="attendee.user" />
            </li>
            <li
                v-if="!guestGroups[guestTab].length"
                class="text-sm text-ink/70"
            >
                {{ t('events.show.noOneYet') }}
            </li>
        </ul>
        <button
            type="button"
            class="mt-4 border border-edge px-3 py-1 text-xs hover:bg-accent hover:text-on-accent"
            @click="guestsOpen = false"
        >
            {{ t('reactions.close') }}
        </button>
    </Modal>

    <!-- Your response, and for the owner their tools beside it, under the image. -->
    <div
        class="mb-8 grid grid-cols-1 gap-4"
        :class="{ 'md:grid-cols-2': props.event.is_owner }"
    >
        <section>
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
                        class="border border-edge bg-surface-raised px-2 py-1 text-sm"
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
                            props.myAttendance?.attendance_visibility ??
                            'everyone'
                        "
                        class="border border-edge bg-surface-raised px-2 py-1 text-sm"
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
                    class="border border-edge bg-accent px-3 py-2 text-sm text-on-accent hover:bg-surface-raised hover:text-ink"
                >
                    {{
                        processing
                            ? t('events.show.saving')
                            : t('events.show.saveResponse')
                    }}
                </button>
            </Form>
        </section>

        <section
            v-if="props.event.is_owner"
            class="border-frame border-edge p-4"
        >
            <h2 class="mb-3 text-sm font-bold uppercase">
                {{ t('events.show.ownerTools') }}
            </h2>
            <div class="mb-3 flex flex-wrap gap-2">
                <Link
                    :href="editEvent(props.event.id).url"
                    class="border border-edge px-3 py-2 text-sm hover:bg-accent hover:text-on-accent"
                >
                    {{ t('events.show.edit') }}
                </Link>
                <button
                    v-if="props.event.can_delete"
                    type="button"
                    class="border border-edge px-3 py-2 text-sm hover:bg-accent hover:text-on-accent"
                    @click="deleteEvent"
                >
                    {{ t('events.show.delete') }}
                </button>
            </div>
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
                    class="border border-edge px-3 py-2 text-sm hover:bg-accent hover:text-on-accent"
                >
                    {{ t('events.show.makeSearchable') }}
                </button>
            </Form>
        </section>
    </div>

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
                class="min-w-48 border border-edge bg-surface-raised px-2 py-1 text-sm"
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
                class="border border-edge px-3 py-2 text-sm hover:bg-accent hover:text-on-accent"
            >
                {{ t('events.show.invite') }}
            </button>
            <FieldError :message="errors.user_ids" />
        </Form>
        <p v-else class="text-sm text-ink/70">
            {{ t('events.show.noInviteCandidates') }}
        </p>
    </section>

    <section>
        <h2 class="mb-3 text-sm font-bold uppercase">
            {{ t('events.show.comments') }}
        </h2>

        <ul class="mb-4 flex flex-col gap-3">
            <li
                v-for="comment in props.comments"
                :key="comment.id"
                class="border-frame border-edge p-3"
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
                    :class="{ 'text-ink/50 italic': comment.is_deleted }"
                >
                    {{ comment.body }}
                </p>
                <p class="mt-1 text-xs text-ink/70">
                    {{ formatDate(comment.created_at) }}
                </p>
            </li>
            <li v-if="!props.comments.length" class="text-sm text-ink/70">
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
                class="w-full border border-edge bg-surface-raised px-3 py-2 font-sans text-sm focus:ring-1 focus:ring-accent focus:outline-none"
            ></textarea>
            <FieldError :message="errors.body" />
            <button
                type="submit"
                :disabled="processing"
                class="w-fit border border-edge bg-accent px-3 py-2 text-sm text-on-accent hover:bg-surface-raised hover:text-ink"
            >
                {{
                    processing
                        ? t('events.show.posting')
                        : t('events.show.postComment')
                }}
            </button>
        </Form>
        <p v-else class="text-sm text-ink/70">
            {{ t('events.show.commentsDisabled') }}
        </p>
    </section>
</template>
