<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import DateTimeField from '@/components/DateTimeField.vue';
import FieldError from '@/components/FieldError.vue';
import LocationPicker from '@/components/LocationPicker.vue';
import { store } from '@/routes/events';

const { t } = useI18n();

const props = defineProps<{
    tagSuggestions: string[];
}>();

const selectedTags = ref<string[]>([]);
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
    <Head :title="t('events.create.title')" />

    <h1 class="mb-6 text-xl font-bold">{{ t('events.create.title') }}</h1>

    <Form
        v-bind="store.form()"
        #default="{ errors, processing }"
        class="flex max-w-xl flex-col gap-4"
    >
        <div>
            <label for="name" class="mb-1 block text-sm font-bold">{{
                t('events.create.name')
            }}</label>
            <input
                id="name"
                type="text"
                name="name"
                required
                class="w-full border border-black bg-white px-3 py-2 font-sans text-sm focus:ring-1 focus:ring-black focus:outline-none"
            />
            <FieldError :message="errors.name" />
        </div>

        <div>
            <label for="place" class="mb-1 block text-sm font-bold">{{
                t('events.create.placeOptional')
            }}</label>
            <input
                id="place"
                type="text"
                name="place"
                class="w-full border border-black bg-white px-3 py-2 font-sans text-sm focus:ring-1 focus:ring-black focus:outline-none"
            />
            <FieldError :message="errors.place" />
        </div>

        <div>
            <LocationPicker />
            <FieldError :message="errors.latitude ?? errors.longitude" />
        </div>

        <div>
            <label for="description" class="mb-1 block text-sm font-bold">{{
                t('events.create.description')
            }}</label>
            <textarea
                id="description"
                name="description"
                required
                rows="4"
                class="w-full border border-black bg-white px-3 py-2 font-sans text-sm focus:ring-1 focus:ring-black focus:outline-none"
            ></textarea>
            <FieldError :message="errors.description" />
        </div>

        <div>
            <p class="mb-1 text-sm font-bold">
                {{ t('events.create.starts') }}
            </p>
            <DateTimeField name="starts_at" required />
            <FieldError :message="errors.starts_at" />
        </div>

        <div>
            <p class="mb-1 text-sm font-bold">
                {{ t('events.create.endsOptional') }}
            </p>
            <DateTimeField name="ends_at" />
            <FieldError :message="errors.ends_at" />
        </div>

        <div>
            <p class="mb-1 text-sm font-bold">{{ t('events.create.tags') }}</p>
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
                :placeholder="t('events.create.tagInputPlaceholder')"
                class="w-full border border-black bg-white px-3 py-2 font-sans text-sm focus:ring-1 focus:ring-black focus:outline-none"
                @keydown.enter.prevent="addTag(tagInput)"
            />
            <div
                v-if="props.tagSuggestions.length"
                class="mt-2 flex flex-wrap gap-2"
            >
                <button
                    v-for="suggestion in props.tagSuggestions"
                    :key="suggestion"
                    type="button"
                    class="border border-black px-2 py-0.5 text-xs hover:bg-black hover:text-white"
                    @click="addTag(suggestion)"
                >
                    + {{ suggestion }}
                </button>
            </div>
            <FieldError :message="errors.tags" />
        </div>

        <div>
            <label for="images" class="mb-1 block text-sm font-bold">{{
                t('events.create.imagesOptional')
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
                {{ t('events.create.visibility') }}
            </legend>
            <label class="mb-1 flex items-center gap-2 text-sm">
                <input
                    type="radio"
                    name="visibility"
                    value="private"
                    checked
                    class="border border-black"
                />
                {{ t('events.create.visibilityPrivate') }}
            </label>
            <label class="flex items-center gap-2 text-sm">
                <input
                    type="radio"
                    name="visibility"
                    value="public"
                    class="border border-black"
                />
                {{ t('events.create.visibilityPublic') }}
            </label>
            <FieldError :message="errors.visibility" />
        </fieldset>

        <label class="flex items-start gap-2 text-sm">
            <input type="hidden" name="allow_invitee_invites" value="0" />
            <input
                type="checkbox"
                name="allow_invitee_invites"
                value="1"
                class="mt-0.5 border border-black"
            />
            {{ t('events.create.allowInviteeInvites') }}
        </label>

        <label class="flex items-start gap-2 text-sm">
            <input type="hidden" name="allow_invitee_comments" value="0" />
            <input
                type="checkbox"
                name="allow_invitee_comments"
                value="1"
                checked
                class="mt-0.5 border border-black"
            />
            {{ t('events.create.allowInviteeComments') }}
        </label>

        <button
            type="submit"
            :disabled="processing"
            class="w-fit border border-black bg-black px-4 py-2 text-sm font-bold text-white hover:bg-white hover:text-black disabled:cursor-not-allowed disabled:opacity-50"
        >
            {{
                processing
                    ? t('events.create.submitting')
                    : t('events.create.submit')
            }}
        </button>
    </Form>
</template>
