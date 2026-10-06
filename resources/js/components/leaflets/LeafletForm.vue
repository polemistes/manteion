<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { ImagePlus, Link2, X } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import FieldError from '@/components/FieldError.vue';
import ImageCropper from '@/components/ImageCropper.vue';
import Button from '@/components/ui/Button.vue';
import SelectField from '@/components/ui/SelectField.vue';
import TextArea from '@/components/ui/TextArea.vue';
import TextField from '@/components/ui/TextField.vue';
import type { OrganizerSummary } from '@/events';
import { leafletVisibilities } from '@/leaflets';
import type { LeafletVisibility } from '@/leaflets';

/**
 * Writing or editing a leaflet: the text, who can read it, and either a
 * picture or a link (its preview is fetched once it is saved).
 */
const props = withDefaults(
    defineProps<{
        form: {
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
        /** The organizers the writer may post as (new leaflets only). */
        organizers?: OrganizerSummary[];
        /** The organizer an existing leaflet is from; that cannot change. */
        organizer?: OrganizerSummary | null;
    }>(),
    {
        body: '',
        visibility: 'friends',
        linkUrl: null,
        imageUrl: null,
        resetOnSuccess: false,
        organizers: () => [],
        organizer: null,
    },
);

const emit = defineEmits<{ saved: [] }>();

const { t } = useI18n();

// Local state behind every field (see .ai/rules/js.md).
const body = ref(props.body);
const visibility = ref<string>(props.visibility);
const linkUrl = ref(props.linkUrl ?? '');
const attachment = ref<'none' | 'image' | 'link'>(
    props.imageUrl ? 'image' : props.linkUrl ? 'link' : 'none',
);

// From whom: the writer ('me') or an organizer they speak for.
const fromId = ref<string>(props.organizer ? String(props.organizer.id) : 'me');
const fromOptions = computed(() => [
    { value: 'me', label: t('leaflets.form.hostYou') },
    ...props.organizers.map((organizer) => ({
        value: String(organizer.id),
        label: organizer.name,
    })),
]);
const chosenOrganizer = computed(
    () =>
        props.organizer ??
        props.organizers.find(
            (organizer) => String(organizer.id) === fromId.value,
        ) ??
        null,
);

// An organizer has followers, not friends: its audiences are named so,
// and "friends only" is not among them.
const visibilityOptions = computed(() =>
    leafletVisibilities
        .filter((value) => !chosenOrganizer.value || value !== 'friends')
        .map((value) => ({
            value,
            label:
                chosenOrganizer.value &&
                (value === 'friends_and_followers' || value === 'private')
                    ? t(`leaflets.organizerVisibility.${value}`)
                    : t(`leaflets.visibility.${value}`),
        })),
);

const visibilityHint = computed(() =>
    chosenOrganizer.value
        ? t(`leaflets.form.organizerVisibilityHints.${visibility.value}`, {
              name: chosenOrganizer.value.name,
          })
        : t(`leaflets.form.visibilityHints.${visibility.value}`),
);

watch(chosenOrganizer, (organizer) => {
    if (organizer && visibility.value === 'friends') {
        visibility.value = 'friends_and_followers';
    }
});

const MAX = 2000;
const remaining = computed(() => MAX - body.value.length);

function attach(kind: 'image' | 'link'): void {
    attachment.value = attachment.value === kind ? 'none' : kind;
}

function onSuccess(): void {
    emit('saved');

    if (!props.resetOnSuccess) {
        return;
    }

    body.value = '';
    fromId.value = 'me';
    visibility.value = props.visibility;
    linkUrl.value = '';
    attachment.value = 'none';
}
</script>

<template>
    <Form
        v-bind="props.form"
        :reset-on-success="props.resetOnSuccess"
        class="flex flex-col gap-5"
        @success="onSuccess"
        #default="{ errors, processing }"
    >
        <div>
            <TextArea
                v-model="body"
                name="body"
                :label="t('leaflets.form.body')"
                hide-label
                :placeholder="t('leaflets.form.bodyPlaceholder')"
                :error="errors.body"
                required
                :maxlength="MAX"
            />
            <p
                v-if="remaining < 200"
                class="mt-1 text-right text-meta tabular-nums"
                :class="remaining < 0 ? 'text-danger' : 'text-ink-muted'"
            >
                {{ remaining }}
            </p>
        </div>

        <div v-if="props.organizers.length && !props.organizer">
            <input
                type="hidden"
                name="organizer_id"
                :value="chosenOrganizer?.id ?? ''"
            />
            <SelectField
                v-model="fromId"
                :label="t('leaflets.form.host')"
                :options="fromOptions"
                :hint="t('leaflets.form.hostHint')"
                :error="errors.organizer_id"
            />
        </div>

        <div>
            <SelectField
                v-model="visibility"
                name="visibility"
                :label="t('leaflets.form.visibility')"
                :options="visibilityOptions"
                :hint="visibilityHint"
                :error="errors.visibility"
            />
        </div>

        <div class="flex flex-col gap-3">
            <div class="flex flex-wrap gap-2">
                <Button
                    size="sm"
                    :icon="attachment === 'image' ? X : ImagePlus"
                    :aria-pressed="attachment === 'image'"
                    @click="attach('image')"
                >
                    {{
                        attachment === 'image'
                            ? t('leaflets.form.removeImage')
                            : t('leaflets.form.addImage')
                    }}
                </Button>
                <Button
                    size="sm"
                    :icon="attachment === 'link' ? X : Link2"
                    :aria-pressed="attachment === 'link'"
                    @click="attach('link')"
                >
                    {{
                        attachment === 'link'
                            ? t('leaflets.form.removeLink')
                            : t('leaflets.form.addLink')
                    }}
                </Button>
            </div>

            <div v-if="attachment === 'image'">
                <ImageCropper
                    name="image"
                    :aspect-ratio="1.5"
                    :output-width="1200"
                    :output-height="800"
                    :existing-url="props.imageUrl"
                    allow-whole
                />
                <FieldError :message="errors.image" />
            </div>

            <!-- Switching away from an existing picture removes it on save. -->
            <input
                v-if="props.imageUrl && attachment !== 'image'"
                type="hidden"
                name="remove_image"
                value="1"
            />

            <TextField
                v-if="attachment === 'link'"
                v-model="linkUrl"
                type="url"
                name="link_url"
                :label="t('leaflets.form.link')"
                placeholder="https://"
                :hint="t('leaflets.form.linkHint')"
                :error="errors.link_url"
                inputmode="url"
            />
        </div>

        <div class="flex justify-end gap-2">
            <slot name="cancel" />
            <Button type="submit" variant="primary" :disabled="processing">
                {{ processing ? props.submittingLabel : props.submitLabel }}
            </Button>
        </div>
    </Form>
</template>
