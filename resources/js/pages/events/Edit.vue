<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import CategoryPicker from '@/components/CategoryPicker.vue';
import DateTimeField from '@/components/DateTimeField.vue';
import FieldError from '@/components/FieldError.vue';
import ImageCropper from '@/components/ImageCropper.vue';
import LocationPicker from '@/components/LocationPicker.vue';
import { update } from '@/routes/events';
import * as eventImages from '@/routes/events/images';

interface CategoryOption {
    id: number;
    name: string;
    slug: string;
    children: { id: number; name: string; slug: string }[];
}

const { t } = useI18n();

const props = defineProps<{
    event: {
        id: number;
        name: string;
        place: string | null;
        latitude: number | null;
        longitude: number | null;
        city: string | null;
        country: string | null;
        description: string;
        starts_at: string;
        ends_at: string | null;
        visibility: string;
        is_searchable: boolean;
        allow_invitee_invites: boolean;
        allow_invitee_comments: boolean;
        tag_ids: number[];
        image: { id: number; url: string } | null;
    };
    categories: CategoryOption[];
}>();

const name = ref(props.event.name);
const place = ref(props.event.place ?? '');
const description = ref(props.event.description);
</script>

<template>
    <Head :title="t('events.edit.title')" />

    <h1 class="text-xl mb-6 font-bold">{{ t('events.edit.title') }}</h1>

    <div v-if="props.event.image" class="mb-6 flex flex-col items-start gap-1">
        <img
            :src="props.event.image.url"
            class="border-edge h-32 border object-cover"
            style="aspect-ratio: 1.5"
            alt=""
        />
        <Link
            :href="
                eventImages.destroy({
                    event: props.event.id,
                    image: props.event.image.id,
                }).url
            "
            method="delete"
            as="button"
            class="text-xs underline"
        >
            {{ t('events.edit.remove') }}
        </Link>
    </div>

    <Form
        v-bind="update.form(props.event.id)"
        #default="{ errors, processing }"
        class="flex max-w-xl flex-col gap-4"
    >
        <div>
            <label for="name" class="text-sm mb-1 block font-bold">{{
                t('events.edit.name')
            }}</label>
            <input
                id="name"
                type="text"
                name="name"
                required
                v-model="name"
                class="border-edge bg-surface-raised text-sm w-full border px-3 py-2 font-sans focus:ring-1 focus:ring-accent focus:outline-none"
            />
            <FieldError :message="errors.name" />
        </div>

        <div>
            <label for="place" class="text-sm mb-1 block font-bold">{{
                t('events.edit.placeOptional')
            }}</label>
            <input
                id="place"
                type="text"
                name="place"
                v-model="place"
                class="border-edge bg-surface-raised text-sm w-full border px-3 py-2 font-sans focus:ring-1 focus:ring-accent focus:outline-none"
            />
            <FieldError :message="errors.place" />
        </div>

        <div>
            <LocationPicker
                :initial-value="{
                    latitude: props.event.latitude,
                    longitude: props.event.longitude,
                    city: props.event.city,
                    country: props.event.country,
                }"
            />
            <FieldError :message="errors.latitude ?? errors.longitude" />
        </div>

        <div>
            <label for="description" class="text-sm mb-1 block font-bold">{{
                t('events.edit.description')
            }}</label>
            <textarea
                id="description"
                name="description"
                required
                rows="4"
                v-model="description"
                class="border-edge bg-surface-raised text-sm w-full border px-3 py-2 font-sans focus:ring-1 focus:ring-accent focus:outline-none"
            ></textarea>
            <FieldError :message="errors.description" />
        </div>

        <div>
            <p class="text-sm mb-1 font-bold">{{ t('events.edit.starts') }}</p>
            <DateTimeField
                name="starts_at"
                :initial-value="props.event.starts_at"
                required
            />
            <FieldError :message="errors.starts_at" />
        </div>

        <div>
            <p class="text-sm mb-1 font-bold">
                {{ t('events.edit.endsOptional') }}
            </p>
            <DateTimeField
                name="ends_at"
                :initial-value="props.event.ends_at"
            />
            <FieldError :message="errors.ends_at" />
        </div>

        <div>
            <p class="text-sm mb-1 font-bold">{{ t('events.edit.tags') }}</p>
            <CategoryPicker
                :categories="props.categories"
                :initial-selected-ids="props.event.tag_ids"
            />
            <FieldError :message="errors.tag_ids" />
        </div>

        <div>
            <p class="text-sm mb-1 font-bold">
                {{ t('events.edit.replaceImage') }}
            </p>
            <ImageCropper
                name="image"
                :aspect-ratio="1.5"
                :output-width="1200"
                :output-height="800"
            />
            <FieldError :message="errors.image" />
        </div>

        <fieldset class="border-frame border-edge p-3">
            <legend class="text-xs px-1 font-bold uppercase">
                {{ t('events.edit.visibility') }}
            </legend>
            <label class="text-sm mb-1 flex items-center gap-2">
                <input
                    type="radio"
                    name="visibility"
                    value="private"
                    :checked="props.event.visibility === 'private'"
                    class="border-edge border"
                />
                {{ t('events.edit.visibilityPrivate') }}
            </label>
            <label class="text-sm flex items-center gap-2">
                <input
                    type="radio"
                    name="visibility"
                    value="public"
                    :checked="props.event.visibility !== 'private'"
                    class="border-edge border"
                />
                {{ t('events.edit.visibilityPublic') }}
            </label>
            <p
                v-if="props.event.is_searchable"
                class="text-xs mt-2 text-ink/70"
            >
                {{ t('events.edit.alreadySearchable') }}
            </p>
            <FieldError :message="errors.visibility" />
        </fieldset>

        <label class="text-sm flex items-start gap-2">
            <input type="hidden" name="allow_invitee_invites" value="0" />
            <input
                type="checkbox"
                name="allow_invitee_invites"
                value="1"
                :checked="props.event.allow_invitee_invites"
                class="border-edge mt-0.5 border"
            />
            {{ t('events.edit.allowInviteeInvites') }}
        </label>

        <label class="text-sm flex items-start gap-2">
            <input type="hidden" name="allow_invitee_comments" value="0" />
            <input
                type="checkbox"
                name="allow_invitee_comments"
                value="1"
                :checked="props.event.allow_invitee_comments"
                class="border-edge mt-0.5 border"
            />
            {{ t('events.edit.allowInviteeComments') }}
        </label>

        <button
            type="submit"
            :disabled="processing"
            class="border-edge text-sm hover:bg-surface-raised w-fit border bg-accent px-4 py-2 font-bold text-on-accent hover:text-ink disabled:cursor-not-allowed disabled:opacity-50"
        >
            {{
                processing
                    ? t('events.edit.submitting')
                    : t('events.edit.submit')
            }}
        </button>
    </Form>
</template>
