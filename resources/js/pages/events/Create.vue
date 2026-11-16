<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Check } from '@lucide/vue';
import { ref, shallowRef } from 'vue';
import { useI18n } from 'vue-i18n';
import EventForm, { EVENT_FORM_ID } from '@/components/events/EventForm.vue';
import type { EventFormValues } from '@/components/events/EventForm.vue';
import FacebookImport from '@/components/events/FacebookImport.vue';
import type { ImportedEvent } from '@/components/events/FacebookImport.vue';
import PageContainer from '@/components/shell/PageContainer.vue';
import PageHeader from '@/components/shell/PageHeader.vue';
import Button from '@/components/ui/Button.vue';
import type { CategoryOption, OrganizerSummary } from '@/events';
import { index as eventsIndex, store } from '@/routes/events';

const props = defineProps<{
    categories: CategoryOption[];
    organizers: OrganizerSummary[];
}>();

const { t } = useI18n();

const processing = ref(false);

const blank: EventFormValues = {
    name: '',
    place: null,
    latitude: null,
    longitude: null,
    city: null,
    country: null,
    country_code: null,
    description: '',
    starts_at: null,
    ends_at: null,
    timezone: null,
    visibility: 'private',
    allow_invitee_invites: false,
    allow_invitee_comments: true,
    tag_ids: [],
    image: null,
};

// The form's fields keep their own state, so an import starts a fresh form
// (a new key) from the imported values.
const values = ref<EventFormValues>(blank);
const importedCover = shallowRef<Blob | null>(null);
const formKey = ref(0);

function startFrom(event: ImportedEvent, cover: Blob | null): void {
    values.value = { ...blank, ...event };
    importedCover.value = cover;
    formKey.value += 1;
}
</script>

<template>
    <PageHeader
        :title="t('events.form.createTitle')"
        :close="eventsIndex().url"
        :back="eventsIndex().url"
        :back-label="t('events.show.back')"
        width="compose"
    >
        <template #actions>
            <Button :as="Link" :href="eventsIndex().url" variant="ghost">
                {{ t('events.form.cancel') }}
            </Button>
            <Button
                type="submit"
                :form="EVENT_FORM_ID"
                variant="primary"
                :icon="Check"
                :disabled="processing"
            >
                {{
                    processing
                        ? t('events.form.creating')
                        : t('events.form.create')
                }}
            </Button>
        </template>
    </PageHeader>

    <PageContainer width="compose" class="pt-5 sm:pt-0">
        <FacebookImport class="mb-7 xl:max-w-[640px]" @imported="startFrom" />
        <EventForm
            :key="formKey"
            v-model:processing="processing"
            :form="store.form()"
            :categories="props.categories"
            :organizers="props.organizers"
            :values="values"
            :initial-image="importedCover"
            :submit-label="t('events.form.create')"
            :processing-label="t('events.form.creating')"
            :note="t('events.form.inviteNext')"
            is-new
        >
            <template #cancel>
                <Button :as="Link" :href="eventsIndex().url" variant="ghost">
                    {{ t('events.form.cancel') }}
                </Button>
            </template>
        </EventForm>
    </PageContainer>
</template>
