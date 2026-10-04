<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { Earth, Lock } from '@lucide/vue';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import CategoryPicker from '@/components/CategoryPicker.vue';
import ImageCropper from '@/components/ImageCropper.vue';
import LocationPicker from '@/components/LocationPicker.vue';
import Button from '@/components/ui/Button.vue';
import Card from '@/components/ui/Card.vue';
import OptionCards from '@/components/ui/OptionCards.vue';
import SwitchRow from '@/components/ui/SwitchRow.vue';
import TextArea from '@/components/ui/TextArea.vue';
import TextField from '@/components/ui/TextField.vue';
import type { CategoryOption, EventCover } from '@/events';
import DateTimeInputs from './DateTimeInputs.vue';

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
 * pinned to the bottom.
 */
const props = defineProps<{
    form: {
        action: string;
        method: 'get' | 'post' | 'put' | 'patch' | 'delete';
    };
    categories: CategoryOption[];
    values: EventFormValues;
    submitLabel: string;
    processingLabel: string;
    note?: string;
}>();

const emit = defineEmits<{ removeImage: [] }>();

const { t } = useI18n();

// Text fields keep their own state (see .ai/rules/js.md).
const name = ref(props.values.name);
const place = ref(props.values.place ?? '');
const description = ref(props.values.description);
const visibility = ref<string>(props.values.visibility);
const allowInvites = ref(props.values.allow_invitee_invites);
const allowComments = ref(props.values.allow_invitee_comments);

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
        v-bind="props.form"
        #default="{ errors, processing }"
        class="flex flex-col gap-7 pb-[calc(116px+env(safe-area-inset-bottom))] sm:pb-0"
    >
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
            />
            <p v-if="errors.image" class="text-meta font-medium text-danger">
                {{ errors.image }}
            </p>
        </section>

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

        <div class="grid gap-5 sm:grid-cols-2">
            <DateTimeInputs
                name="starts_at"
                :label="t('events.form.starts')"
                :default-value="props.values.starts_at"
                :error="errors.starts_at"
                required
            />
            <DateTimeInputs
                name="ends_at"
                :label="t('events.form.ends')"
                :default-value="props.values.ends_at"
                :error="errors.ends_at"
                default-time="22:00"
                optional
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

        <div class="hidden justify-end gap-2 sm:flex">
            <slot name="cancel" />
            <Button type="submit" variant="primary" :disabled="processing">
                {{ processing ? props.processingLabel : props.submitLabel }}
            </Button>
        </div>

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
