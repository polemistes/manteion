<script lang="ts">
/** The form element's id, for submit buttons outside it (the page header's). */
export const EVENT_FORM_ID = 'event-form';
</script>

<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { Earth, Lock, UserPlus } from '@lucide/vue';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import CategoryPicker from '@/components/CategoryPicker.vue';
import ImageCropper from '@/components/ImageCropper.vue';
import LocationPicker from '@/components/LocationPicker.vue';
import type { LocationValue } from '@/components/LocationPicker.vue';
import Button from '@/components/ui/Button.vue';
import Card from '@/components/ui/Card.vue';
import OptionCards from '@/components/ui/OptionCards.vue';
import SelectField from '@/components/ui/SelectField.vue';
import SwitchRow from '@/components/ui/SwitchRow.vue';
import TextArea from '@/components/ui/TextArea.vue';
import TextField from '@/components/ui/TextField.vue';
import type { CategoryOption, EventCover, OrganizerSummary } from '@/events';
import { usePlaces } from '@/lib/places';
import DateTimeInputs from './DateTimeInputs.vue';
import EventPreviewCard from './EventPreviewCard.vue';

export interface EventFormValues {
    name: string;
    place: string | null;
    latitude: number | null;
    longitude: number | null;
    city: string | null;
    country: string | null;
    country_code: string | null;
    description: string;
    starts_at: string | null;
    ends_at: string | null;
    visibility: 'private' | 'public';
    organizer_id?: number | null;
    is_searchable?: boolean;
    allow_invitee_invites: boolean;
    allow_invitee_comments: boolean;
    tag_ids: number[];
    image: EventCover | null;
}

/**
 * The event form shared by Create and Edit, in the order guests read an
 * event: cover, name, description, when, where, categories, who can see
 * it, what guests may do. On phones the submit button sits in a bar
 * pinned to the bottom; from xl a preview of the event's card sits beside
 * the form. `processing` is true while it is being sent.
 */
const props = withDefaults(
    defineProps<{
        form: {
            action: string;
            method: 'get' | 'post' | 'put' | 'patch' | 'delete';
        };
        categories: CategoryOption[];
        values: EventFormValues;
        submitLabel: string;
        processingLabel: string;
        note?: string;
        isNew?: boolean;
        /** The organizers the user may host it as (new events only). */
        organizers?: OrganizerSummary[];
    }>(),
    { note: undefined, isNew: false, organizers: () => [] },
);

const emit = defineEmits<{ removeImage: [] }>();

const processing = defineModel<boolean>('processing', { default: false });

const { t } = useI18n();
const { cityAndCountry } = usePlaces();

// Text fields keep their own state (see .ai/rules/js.md).
const name = ref(props.values.name);
const place = ref(props.values.place ?? '');
const description = ref(props.values.description);
const visibility = ref<string>(props.values.visibility);
const allowInvites = ref(props.values.allow_invitee_invites);
const allowComments = ref(props.values.allow_invitee_comments);

// Who hosts: the person themself ('me') or one of their organizers.
const hostId = ref<string>(props.values.organizer_id?.toString() ?? 'me');
const hostOptions = computed(() => [
    { value: 'me', label: t('events.form.hostYou') },
    ...props.organizers.map((organizer) => ({
        value: String(organizer.id),
        label: organizer.name,
    })),
]);
const chosenOrganizer = computed(
    () =>
        props.organizers.find(
            (organizer) => String(organizer.id) === hostId.value,
        ) ?? null,
);

// What the preview shows, as the fields report it.
const startsAt = ref(props.values.starts_at ?? '');
const endsAt = ref(props.values.ends_at ?? '');
const cover = ref<{ url: string | null; fit: 'cover' | 'contain' }>({
    url: props.values.image?.url ?? null,
    fit:
        props.values.image?.fit ??
        (props.values.image?.shape === 'poster' ? 'contain' : 'cover'),
});
const location = ref<LocationValue>({
    latitude: props.values.latitude,
    longitude: props.values.longitude,
    city: props.values.city,
    country: props.values.country,
    country_code: props.values.country_code,
});

const previewPlace = computed(
    () =>
        place.value.trim() ||
        cityAndCountry(
            location.value.city,
            location.value.country,
            location.value.country_code,
        ) ||
        null,
);

const visibilityOptions = [
    {
        value: 'private',
        title: t('events.form.private'),
        description: t('events.form.privateHint'),
        icon: Lock,
    },
    {
        value: 'public',
        title: t('events.form.public'),
        description: t('events.form.publicHint'),
        icon: Earth,
    },
];
</script>

<template>
    <Form
        :id="EVENT_FORM_ID"
        v-bind="props.form"
        #default="{ errors }"
        class="pb-[calc(116px+env(safe-area-inset-bottom))] sm:pb-0 xl:grid xl:grid-cols-[minmax(0,1fr)_var(--spacing-aside)] xl:items-start xl:gap-12"
        @start="processing = true"
        @finish="processing = false"
    >
        <div class="flex min-w-0 flex-col gap-7">
            <section class="flex flex-col gap-1.5">
                <h2 class="text-label font-semibold text-ink">
                    {{ t('events.form.coverImage') }}
                    <span class="font-normal text-ink-muted"
                        >({{ t('ui.optional') }})</span
                    >
                </h2>
                <ImageCropper
                    name="image"
                    :aspect-ratio="1.5"
                    :output-width="1200"
                    :output-height="800"
                    :existing-url="props.values.image?.url ?? null"
                    :initial-fit="
                        props.values.image?.fit ??
                        (props.values.image?.shape === 'poster'
                            ? 'contain'
                            : 'cover')
                    "
                    allow-whole
                    :removable="props.values.image !== null"
                    @remove="emit('removeImage')"
                    @preview="cover = $event"
                />
                <p
                    v-if="errors.image"
                    class="text-meta font-medium text-danger"
                >
                    {{ errors.image }}
                </p>
            </section>

            <template v-if="props.isNew && props.organizers.length">
                <input
                    type="hidden"
                    name="organizer_id"
                    :value="chosenOrganizer?.id ?? ''"
                />
                <SelectField
                    v-model="hostId"
                    :options="hostOptions"
                    :label="t('events.form.host')"
                    :hint="t('events.form.hostHint')"
                    :error="errors.organizer_id"
                />
            </template>

            <TextField
                v-model="name"
                name="name"
                :label="t('events.form.name')"
                :placeholder="t('events.form.namePlaceholder')"
                :error="errors.name"
                headline
                required
                maxlength="255"
            />

            <TextArea
                v-model="description"
                name="description"
                :label="t('events.form.description')"
                :placeholder="t('events.form.descriptionPlaceholder')"
                :error="errors.description"
                required
                maxlength="5000"
            />

            <div class="flex flex-col gap-5">
                <DateTimeInputs
                    name="starts_at"
                    :label="t('events.form.starts')"
                    :default-value="props.values.starts_at"
                    :error="errors.starts_at"
                    required
                    @change="startsAt = $event"
                />
                <DateTimeInputs
                    name="ends_at"
                    :label="t('events.form.ends')"
                    :default-value="props.values.ends_at"
                    :error="errors.ends_at"
                    default-time="22:00"
                    optional
                    @change="endsAt = $event"
                />
            </div>

            <section class="flex flex-col gap-3">
                <TextField
                    v-model="place"
                    name="place"
                    :label="t('events.form.place')"
                    :placeholder="t('events.form.placeNamePlaceholder')"
                    :error="errors.place"
                    optional
                    maxlength="255"
                />
                <LocationPicker
                    :initial-value="{
                        latitude: props.values.latitude,
                        longitude: props.values.longitude,
                        city: props.values.city,
                        country: props.values.country,
                        country_code: props.values.country_code,
                    }"
                    @change="location = $event"
                />
            </section>

            <section>
                <h2 class="mb-2.5 text-label font-semibold text-ink">
                    {{ t('events.form.categories') }}
                </h2>
                <CategoryPicker
                    :categories="props.categories"
                    :initial-selected-ids="props.values.tag_ids"
                />
                <p
                    v-if="errors.tag_ids"
                    class="mt-1.5 text-meta font-medium text-danger"
                >
                    {{ errors.tag_ids }}
                </p>
            </section>

            <section>
                <h2 class="mb-2.5 text-label font-semibold text-ink">
                    {{ t('events.form.whoCanSee') }}
                </h2>
                <OptionCards
                    v-model="visibility"
                    name="visibility"
                    :options="visibilityOptions"
                    :label="t('events.form.whoCanSee')"
                />
                <p
                    v-if="props.values.is_searchable"
                    class="mt-2 text-meta text-ink-muted"
                >
                    {{ t('events.form.searchableNote') }}
                </p>
                <p
                    v-if="errors.visibility"
                    class="mt-1.5 text-meta font-medium text-danger"
                >
                    {{ errors.visibility }}
                </p>
            </section>

            <section>
                <h2 class="mb-2.5 text-label font-semibold text-ink">
                    {{ t('events.form.guestsCan') }}
                </h2>
                <input
                    type="hidden"
                    name="allow_invitee_invites"
                    :value="allowInvites ? '1' : '0'"
                />
                <input
                    type="hidden"
                    name="allow_invitee_comments"
                    :value="allowComments ? '1' : '0'"
                />
                <Card>
                    <SwitchRow
                        v-model="allowInvites"
                        :title="t('events.form.inviteFriends')"
                        :description="t('events.form.inviteFriendsHint')"
                    />
                    <SwitchRow
                        v-model="allowComments"
                        :title="t('events.form.comment')"
                        :description="t('events.form.commentHint')"
                    />
                </Card>
            </section>

            <div
                class="hidden items-center justify-end gap-2 border-t border-line pt-5 sm:flex"
            >
                <p v-if="props.note" class="mr-auto text-meta text-ink-muted">
                    {{ props.note }}
                </p>
                <slot name="cancel" />
                <Button type="submit" variant="primary" :disabled="processing">
                    {{ processing ? props.processingLabel : props.submitLabel }}
                </Button>
            </div>
        </div>

        <aside
            class="hidden xl:sticky xl:top-6 xl:flex xl:flex-col xl:gap-4"
            :aria-label="t('events.form.preview.title')"
        >
            <div>
                <h2 class="text-kicker text-ink-muted uppercase">
                    {{ t('events.form.preview.title') }}
                </h2>
                <p class="mt-1 text-meta text-ink-muted">
                    {{ t('events.form.preview.lede') }}
                </p>
            </div>
            <EventPreviewCard
                :name="name"
                :starts-at="startsAt || null"
                :ends-at="endsAt || null"
                :place="previewPlace"
                :cover="cover.url ? { url: cover.url, fit: cover.fit } : null"
                :visibility="visibility === 'public' ? 'public' : 'private'"
                :is-new="props.isNew"
                :organizer="chosenOrganizer"
            />
            <section
                v-if="props.isNew"
                class="flex gap-3 rounded-lg border border-line bg-surface p-4"
            >
                <span
                    class="grid size-9 shrink-0 place-items-center rounded-full bg-surface-2 text-ink-muted"
                    aria-hidden="true"
                >
                    <UserPlus :size="18" :stroke-width="1.9" />
                </span>
                <div class="min-w-0">
                    <h3 class="font-display text-title-3">
                        {{ t('events.form.next.title') }}
                    </h3>
                    <p class="mt-1 text-meta text-ink-muted">
                        {{ t('events.form.next.body') }}
                    </p>
                </div>
            </section>
        </aside>

        <!-- Phone: the submit bar. -->
        <div
            class="fixed inset-x-0 bottom-0 z-30 border-t border-line bg-surface px-4 pt-3 pb-[calc(12px+env(safe-area-inset-bottom))] sm:hidden"
        >
            <Button
                type="submit"
                variant="primary"
                size="lg"
                block
                :disabled="processing"
            >
                {{ processing ? props.processingLabel : props.submitLabel }}
            </Button>
            <p
                v-if="props.note"
                class="mt-1.5 text-center text-caption text-ink-subtle"
            >
                {{ props.note }}
            </p>
        </div>
    </Form>
</template>
