<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { ImagePlus, Link2, X } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { useTemplateRef } from 'vue';
import { useI18n } from 'vue-i18n';
import FieldError from '@/components/FieldError.vue';
import PictureStrip from '@/components/PictureStrip.vue';
import Button from '@/components/ui/Button.vue';
import SelectField from '@/components/ui/SelectField.vue';
import TextArea from '@/components/ui/TextArea.vue';
import TextField from '@/components/ui/TextField.vue';
import { fillFileInput, usePictures } from '@/composables/usePictures';
import type { OrganizerSummary } from '@/events';
import { leafletVisibilities } from '@/leaflets';
import type { LeafletVisibility } from '@/leaflets';
import { MAX_PICTURES } from '@/pictures';
import type { Picture } from '@/pictures';

/**
 * Writing or editing a leaflet: the text, who can read it, and either
 * pictures or a link (its preview is fetched once it is saved). Pictures
 * are picked several at a time and shown as a row of tiles; each can be
 * taken off again, those already on a leaflet being edited too (they go
 * when it is saved). Turning to a link takes them all off.
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
        /** The pictures already on a leaflet being edited. */
        existingPictures?: Picture[];
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
        existingPictures: () => [],
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
    props.existingPictures.length ? 'image' : props.linkUrl ? 'link' : 'none',
);

// Pictures: those already there and not taken off, then the new ones.
const removedIds = ref<number[]>([]);
const kept = computed(() =>
    props.existingPictures.filter(
        (picture) => !removedIds.value.includes(picture.id),
    ),
);
const { pictures, preparing, add, remove, clear } = usePictures({
    room: () => MAX_PICTURES - kept.value.length,
});
const picker = useTemplateRef<HTMLInputElement>('picker');
const filesInput = useTemplateRef<HTMLInputElement>('filesInput');

// The form is sent the ordinary way: the new pictures ride in a file input.
watch(pictures, (next) => fillFileInput(filesInput.value, next), {
    flush: 'post',
});

const tiles = computed(() => [
    ...kept.value.map((picture) => ({
        key: `kept-${picture.id}`,
        url: picture.thumb_url,
    })),
    ...pictures.value.map((picture) => ({
        key: picture.previewUrl,
        url: picture.previewUrl,
    })),
]);

function removeTile(index: number): void {
    if (index < kept.value.length) {
        removedIds.value = [...removedIds.value, kept.value[index].id];
    } else {
        remove(index - kept.value.length);
    }
}

async function onPicked(event: Event): Promise<void> {
    const input = event.target as HTMLInputElement;
    const files = [...(input.files ?? [])];

    // So the same file can be picked again later.
    input.value = '';

    if (files.length > 0) {
        attachment.value = 'image';
    }

    await add(files);
}

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

    // Pictures only count while they are what is attached.
    if (attachment.value !== 'image') {
        clear();
        removedIds.value = props.existingPictures.map((picture) => picture.id);
    } else {
        removedIds.value = [];
        picker.value?.click();
    }
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
    clear();
    removedIds.value = [];
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

            <input
                ref="picker"
                type="file"
                accept="image/*"
                multiple
                class="hidden"
                tabindex="-1"
                @change="onPicked"
            />
            <input
                ref="filesInput"
                type="file"
                name="pictures[]"
                multiple
                class="hidden"
                tabindex="-1"
            />
            <!-- The pictures taken off go when the leaflet is saved. -->
            <input
                v-for="id in removedIds"
                :key="id"
                type="hidden"
                name="remove_pictures[]"
                :value="id"
            />

            <div v-if="attachment === 'image'">
                <PictureStrip
                    :pictures="tiles"
                    :addable="tiles.length < MAX_PICTURES"
                    :disabled="preparing"
                    @remove="removeTile"
                    @add="picker?.click()"
                />
                <FieldError
                    :message="
                        errors.pictures ??
                        Object.entries(errors).find(([key]) =>
                            key.startsWith('pictures.'),
                        )?.[1]
                    "
                />
            </div>

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
            <Button
                type="submit"
                variant="primary"
                :disabled="processing || preparing"
            >
                {{ processing ? props.submittingLabel : props.submitLabel }}
            </Button>
        </div>
    </Form>
</template>
