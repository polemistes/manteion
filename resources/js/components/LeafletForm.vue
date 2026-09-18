<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import FieldError from '@/components/FieldError.vue';
import ImageCropper from '@/components/ImageCropper.vue';
import { leafletVisibilities } from '@/leaflets';
import type { LeafletVisibility } from '@/leaflets';

const props = withDefaults(
    defineProps<{
        action: {
            action: string;
            method: 'get' | 'post' | 'put' | 'patch' | 'delete';
        };
        submitLabel: string;
        submittingLabel: string;
        body?: string;
        visibility?: LeafletVisibility;
        linkUrl?: string | null;
        imageUrl?: string | null;
        resetOnSuccess?: boolean;
    }>(),
    {
        body: '',
        visibility: 'friends',
        linkUrl: null,
        imageUrl: null,
        resetOnSuccess: false,
    },
);

const { t } = useI18n();

const removeImage = ref(false);
const attachment = ref<'none' | 'image' | 'link'>(
    props.imageUrl ? 'image' : props.linkUrl ? 'link' : 'none',
);
</script>

<template>
    <Form
        v-bind="props.action"
        :reset-on-success="props.resetOnSuccess"
        #default="{ errors, processing }"
        class="flex flex-col gap-3"
    >
        <div>
            <label for="leaflet-body" class="sr-only">{{
                t('leaflets.form.body')
            }}</label>
            <textarea
                id="leaflet-body"
                name="body"
                required
                rows="3"
                maxlength="2000"
                :placeholder="t('leaflets.form.bodyPlaceholder')"
                :value="props.body"
                class="w-full border border-edge bg-surface-raised px-3 py-2 font-sans text-sm focus:ring-1 focus:ring-accent focus:outline-none"
            ></textarea>
            <FieldError :message="errors.body" />
        </div>

        <div class="flex flex-wrap items-end gap-3">
            <label class="flex flex-col gap-1 text-sm">
                <span class="text-xs font-bold">{{
                    t('leaflets.form.visibility')
                }}</span>
                <select
                    name="visibility"
                    :value="props.visibility"
                    class="border border-edge bg-surface-raised px-2 py-1 font-sans text-sm"
                >
                    <option
                        v-for="option in leafletVisibilities"
                        :key="option"
                        :value="option"
                    >
                        {{ t(`leaflets.visibility.${option}`) }}
                    </option>
                </select>
            </label>

            <label class="flex flex-col gap-1 text-sm">
                <span class="text-xs font-bold">{{
                    t('leaflets.form.attachment')
                }}</span>
                <select
                    v-model="attachment"
                    class="border border-edge bg-surface-raised px-2 py-1 font-sans text-sm"
                >
                    <option value="none">
                        {{ t('leaflets.form.attachmentNone') }}
                    </option>
                    <option value="image">
                        {{ t('leaflets.form.attachmentImage') }}
                    </option>
                    <option value="link">
                        {{ t('leaflets.form.attachmentLink') }}
                    </option>
                </select>
            </label>
        </div>
        <FieldError :message="errors.visibility" />

        <div v-if="attachment === 'image'">
            <ImageCropper
                name="image"
                :aspect-ratio="1.5"
                :output-width="1200"
                :output-height="800"
                :existing-url="removeImage ? null : props.imageUrl"
            />
            <FieldError :message="errors.image" />
        </div>

        <!-- Switching away from an existing image removes it on save. -->
        <input
            v-if="props.imageUrl && attachment !== 'image'"
            type="hidden"
            name="remove_image"
            value="1"
        />

        <div v-if="attachment === 'link'">
            <label for="leaflet-link" class="mb-1 block text-xs font-bold">{{
                t('leaflets.form.link')
            }}</label>
            <input
                id="leaflet-link"
                type="url"
                name="link_url"
                :value="props.linkUrl ?? ''"
                placeholder="https://"
                class="w-full border border-edge bg-surface-raised px-3 py-2 font-sans text-sm focus:ring-1 focus:ring-accent focus:outline-none"
            />
            <FieldError :message="errors.link_url" />
        </div>

        <button
            type="submit"
            :disabled="processing"
            class="w-fit border border-edge bg-accent px-4 py-2 text-sm font-bold text-on-accent hover:bg-surface-raised hover:text-ink disabled:cursor-not-allowed disabled:opacity-50"
        >
            {{ processing ? props.submittingLabel : props.submitLabel }}
        </button>
    </Form>
</template>
