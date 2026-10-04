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

// Local state behind every field: a bare :value binding is re-applied on
// each re-render (Vue always patches `value`), which would wipe what the
// user typed as soon as they changed the attachment kind.
const body = ref(props.body);
const visibility = ref<LeafletVisibility>(props.visibility);
const linkUrl = ref(props.linkUrl ?? '');
const removeImage = ref(false);
const attachment = ref<'none' | 'image' | 'link'>(
    props.imageUrl ? 'image' : props.linkUrl ? 'link' : 'none',
);

function onSuccess(): void {
    if (!props.resetOnSuccess) {
        return;
    }

    body.value = '';
    visibility.value = props.visibility;
    linkUrl.value = '';
    attachment.value = 'none';
}
</script>

<template>
    <Form
        v-bind="props.action"
        :reset-on-success="props.resetOnSuccess"
        #default="{ errors, processing }"
        @success="onSuccess"
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
                v-model="body"
                :placeholder="t('leaflets.form.bodyPlaceholder')"
                class="border-edge bg-surface-raised text-sm w-full border px-3 py-2 font-sans focus:ring-1 focus:ring-accent focus:outline-none"
            ></textarea>
            <FieldError :message="errors.body" />
        </div>

        <div class="flex flex-wrap items-end gap-3">
            <label class="text-sm flex flex-col gap-1">
                <span class="text-xs font-bold">{{
                    t('leaflets.form.visibility')
                }}</span>
                <select
                    v-model="visibility"
                    name="visibility"
                    class="border-edge bg-surface-raised text-sm border px-2 py-1 font-sans"
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

            <label class="text-sm flex flex-col gap-1">
                <span class="text-xs font-bold">{{
                    t('leaflets.form.attachment')
                }}</span>
                <select
                    v-model="attachment"
                    class="border-edge bg-surface-raised text-sm border px-2 py-1 font-sans"
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
            <label for="leaflet-link" class="text-xs mb-1 block font-bold">{{
                t('leaflets.form.link')
            }}</label>
            <input
                id="leaflet-link"
                v-model="linkUrl"
                type="url"
                name="link_url"
                placeholder="https://"
                class="border-edge bg-surface-raised text-sm w-full border px-3 py-2 font-sans focus:ring-1 focus:ring-accent focus:outline-none"
            />
            <FieldError :message="errors.link_url" />
        </div>

        <button
            type="submit"
            :disabled="processing"
            class="border-edge text-sm hover:bg-surface-raised w-fit border bg-accent px-4 py-2 font-bold text-on-accent hover:text-ink disabled:cursor-not-allowed disabled:opacity-50"
        >
            {{ processing ? props.submittingLabel : props.submitLabel }}
        </button>
    </Form>
</template>
