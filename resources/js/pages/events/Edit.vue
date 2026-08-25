<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import DateTimeField from '@/components/DateTimeField.vue';
import FieldError from '@/components/FieldError.vue';
import { update } from '@/routes/events';
import * as eventImages from '@/routes/events/images';

const { t } = useI18n();

const props = defineProps<{
    event: {
        id: number;
        name: string;
        place: string;
        description: string;
        starts_at: string;
        ends_at: string | null;
        visibility: string;
        is_searchable: boolean;
        allow_invitee_invites: boolean;
        allow_invitee_comments: boolean;
        tags: string[];
        images: { id: number; url: string }[];
    };
}>();

const selectedTags = ref<string[]>([...props.event.tags]);
const tagInput = ref('');

function addTag(name: string): void {
    const trimmed = name.trim();

    if (trimmed !== '' && !selectedTags.value.includes(trimmed)) {
        selectedTags.value.push(trimmed);
    }

    tagInput.value = '';
}

function removeTag(name: string): void {
    selectedTags.value = selectedTags.value.filter((tag) => tag !== name);
}
</script>

<template>
    <Head :title="t('events.edit.title')" />

    <h1 class="mb-6 text-xl font-bold">{{ t('events.edit.title') }}</h1>

    <div v-if="props.event.images.length" class="mb-6 flex flex-wrap gap-3">
        <div
            v-for="image in props.event.images"
            :key="image.id"
            class="flex flex-col items-center gap-1"
        >
            <img
                :src="image.url"
                class="h-24 w-24 border border-black object-cover"
                alt=""
            />
            <Link
                :href="
                    eventImages.destroy({
                        event: props.event.id,
                        image: image.id,
                    }).url
                "
                method="delete"
                as="button"
                class="text-xs underline"
            >
                {{ t('events.edit.remove') }}
            </Link>
        </div>
    </div>

    <Form
        v-bind="update.form(props.event.id)"
        #default="{ errors, processing }"
        class="flex max-w-xl flex-col gap-4"
    >
        <div>
            <label for="name" class="mb-1 block text-sm font-bold">{{
                t('events.edit.name')
            }}</label>
            <input
                id="name"
                type="text"
                name="name"
                required
                :value="props.event.name"
                class="w-full border border-black bg-white px-3 py-2 font-sans text-sm focus:ring-1 focus:ring-black focus:outline-none"
            />
            <FieldError :message="errors.name" />
        </div>

        <div>
            <label for="place" class="mb-1 block text-sm font-bold">{{
                t('events.edit.place')
            }}</label>
            <input
                id="place"
                type="text"
                name="place"
                required
                :value="props.event.place"
                class="w-full border border-black bg-white px-3 py-2 font-sans text-sm focus:ring-1 focus:ring-black focus:outline-none"
            />
            <FieldError :message="errors.place" />
        </div>

        <div>
            <label for="description" class="mb-1 block text-sm font-bold">{{
                t('events.edit.description')
            }}</label>
            <textarea
                id="description"
                name="description"
                required
                rows="4"
                :value="props.event.description"
                class="w-full border border-black bg-white px-3 py-2 font-sans text-sm focus:ring-1 focus:ring-black focus:outline-none"
            ></textarea>
            <FieldError :message="errors.description" />
        </div>

        <div>
            <p class="mb-1 text-sm font-bold">{{ t('events.edit.starts') }}</p>
            <DateTimeField
                name="starts_at"
                :initial-value="props.event.starts_at"
                required
            />
            <FieldError :message="errors.starts_at" />
        </div>

        <div>
            <p class="mb-1 text-sm font-bold">
                {{ t('events.edit.endsOptional') }}
            </p>
            <DateTimeField
                name="ends_at"
                :initial-value="props.event.ends_at"
            />
            <FieldError :message="errors.ends_at" />
        </div>

        <div>
            <p class="mb-1 text-sm font-bold">{{ t('events.edit.tags') }}</p>
            <div class="mb-2 flex flex-wrap gap-2">
                <span
                    v-for="tag in selectedTags"
                    :key="tag"
                    class="flex items-center gap-1 border border-black px-2 py-0.5 text-xs"
                >
                    <input type="hidden" name="tags[]" :value="tag" />
                    {{ tag }}
                    <button
                        type="button"
                        class="font-bold"
                        @click="removeTag(tag)"
                    >
                        ×
                    </button>
                </span>
            </div>
            <input
                v-model="tagInput"
                type="text"
                :placeholder="t('events.edit.tagInputPlaceholder')"
                class="w-full border border-black bg-white px-3 py-2 font-sans text-sm focus:ring-1 focus:ring-black focus:outline-none"
                @keydown.enter.prevent="addTag(tagInput)"
            />
            <FieldError :message="errors.tags" />
        </div>

        <div>
            <label for="images" class="mb-1 block text-sm font-bold">{{
                t('events.edit.addMoreImages')
            }}</label>
            <input
                id="images"
                type="file"
                name="images[]"
                multiple
                accept="image/*"
                class="w-full border border-black bg-white px-3 py-2 font-sans text-sm"
            />
            <FieldError :message="errors.images" />
        </div>

        <fieldset class="border-[3px] border-black p-3">
            <legend class="px-1 text-xs font-bold uppercase">
                {{ t('events.edit.visibility') }}
            </legend>
            <label class="mb-1 flex items-center gap-2 text-sm">
                <input
                    type="radio"
                    name="visibility"
                    value="private"
                    :checked="props.event.visibility === 'private'"
                    class="border border-black"
                />
                {{ t('events.edit.visibilityPrivate') }}
            </label>
            <label class="flex items-center gap-2 text-sm">
                <input
                    type="radio"
                    name="visibility"
                    value="public"
                    :checked="props.event.visibility !== 'private'"
                    class="border border-black"
                />
                {{ t('events.edit.visibilityPublic') }}
            </label>
            <p
                v-if="props.event.is_searchable"
                class="mt-2 text-xs text-black/70"
            >
                {{ t('events.edit.alreadySearchable') }}
            </p>
            <FieldError :message="errors.visibility" />
        </fieldset>

        <label class="flex items-start gap-2 text-sm">
            <input type="hidden" name="allow_invitee_invites" value="0" />
            <input
                type="checkbox"
                name="allow_invitee_invites"
                value="1"
                :checked="props.event.allow_invitee_invites"
                class="mt-0.5 border border-black"
            />
            {{ t('events.edit.allowInviteeInvites') }}
        </label>

        <label class="flex items-start gap-2 text-sm">
            <input type="hidden" name="allow_invitee_comments" value="0" />
            <input
                type="checkbox"
                name="allow_invitee_comments"
                value="1"
                :checked="props.event.allow_invitee_comments"
                class="mt-0.5 border border-black"
            />
            {{ t('events.edit.allowInviteeComments') }}
        </label>

        <button
            type="submit"
            :disabled="processing"
            class="w-fit border border-black bg-black px-4 py-2 text-sm font-bold text-white hover:bg-white hover:text-black disabled:cursor-not-allowed disabled:opacity-50"
        >
            {{
                processing
                    ? t('events.edit.submitting')
                    : t('events.edit.submit')
            }}
        </button>
    </Form>
</template>
