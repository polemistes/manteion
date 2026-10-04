<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import CategoryPicker from '@/components/CategoryPicker.vue';
import DateTimeField from '@/components/DateTimeField.vue';
import FieldError from '@/components/FieldError.vue';
import ImageCropper from '@/components/ImageCropper.vue';
import LocationPicker from '@/components/LocationPicker.vue';
import { store } from '@/routes/events';

interface CategoryOption {
    id: number;
    name: string;
    slug: string;
    children: { id: number; name: string; slug: string }[];
}

const { t } = useI18n();

const props = defineProps<{
    categories: CategoryOption[];
}>();
</script>

<template>
    <Head :title="t('events.create.title')" />

    <h1 class="text-xl mb-6 font-bold">{{ t('events.create.title') }}</h1>

    <Form
        v-bind="store.form()"
        #default="{ errors, processing }"
        class="flex max-w-xl flex-col gap-4"
    >
        <div>
            <label for="name" class="text-sm mb-1 block font-bold">{{
                t('events.create.name')
            }}</label>
            <input
                id="name"
                type="text"
                name="name"
                required
                class="border-edge bg-surface-raised text-sm w-full border px-3 py-2 font-sans focus:ring-1 focus:ring-accent focus:outline-none"
            />
            <FieldError :message="errors.name" />
        </div>

        <div>
            <label for="place" class="text-sm mb-1 block font-bold">{{
                t('events.create.placeOptional')
            }}</label>
            <input
                id="place"
                type="text"
                name="place"
                class="border-edge bg-surface-raised text-sm w-full border px-3 py-2 font-sans focus:ring-1 focus:ring-accent focus:outline-none"
            />
            <FieldError :message="errors.place" />
        </div>

        <div>
            <LocationPicker />
            <FieldError :message="errors.latitude ?? errors.longitude" />
        </div>

        <div>
            <label for="description" class="text-sm mb-1 block font-bold">{{
                t('events.create.description')
            }}</label>
            <textarea
                id="description"
                name="description"
                required
                rows="4"
                class="border-edge bg-surface-raised text-sm w-full border px-3 py-2 font-sans focus:ring-1 focus:ring-accent focus:outline-none"
            ></textarea>
            <FieldError :message="errors.description" />
        </div>

        <div>
            <p class="text-sm mb-1 font-bold">
                {{ t('events.create.starts') }}
            </p>
            <DateTimeField name="starts_at" required />
            <FieldError :message="errors.starts_at" />
        </div>

        <div>
            <p class="text-sm mb-1 font-bold">
                {{ t('events.create.endsOptional') }}
            </p>
            <DateTimeField name="ends_at" />
            <FieldError :message="errors.ends_at" />
        </div>

        <div>
            <p class="text-sm mb-1 font-bold">{{ t('events.create.tags') }}</p>
            <CategoryPicker :categories="props.categories" />
            <FieldError :message="errors.tag_ids" />
        </div>

        <div>
            <p class="text-sm mb-1 font-bold">
                {{ t('events.create.imageOptional') }}
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
                {{ t('events.create.visibility') }}
            </legend>
            <label class="text-sm mb-1 flex items-center gap-2">
                <input
                    type="radio"
                    name="visibility"
                    value="private"
                    checked
                    class="border-edge border"
                />
                {{ t('events.create.visibilityPrivate') }}
            </label>
            <label class="text-sm flex items-center gap-2">
                <input
                    type="radio"
                    name="visibility"
                    value="public"
                    class="border-edge border"
                />
                {{ t('events.create.visibilityPublic') }}
            </label>
            <FieldError :message="errors.visibility" />
        </fieldset>

        <label class="text-sm flex items-start gap-2">
            <input type="hidden" name="allow_invitee_invites" value="0" />
            <input
                type="checkbox"
                name="allow_invitee_invites"
                value="1"
                class="border-edge mt-0.5 border"
            />
            {{ t('events.create.allowInviteeInvites') }}
        </label>

        <label class="text-sm flex items-start gap-2">
            <input type="hidden" name="allow_invitee_comments" value="0" />
            <input
                type="checkbox"
                name="allow_invitee_comments"
                value="1"
                checked
                class="border-edge mt-0.5 border"
            />
            {{ t('events.create.allowInviteeComments') }}
        </label>

        <button
            type="submit"
            :disabled="processing"
            class="border-edge text-sm hover:bg-surface-raised w-fit border bg-accent px-4 py-2 font-bold text-on-accent hover:text-ink disabled:cursor-not-allowed disabled:opacity-50"
        >
            {{
                processing
                    ? t('events.create.submitting')
                    : t('events.create.submit')
            }}
        </button>
    </Form>
</template>
