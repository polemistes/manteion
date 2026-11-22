<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Check } from '@lucide/vue';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import EventForm, { EVENT_FORM_ID } from '@/components/events/EventForm.vue';
import type { EventFormValues } from '@/components/events/EventForm.vue';
import PageContainer from '@/components/shell/PageContainer.vue';
import PageHeader from '@/components/shell/PageHeader.vue';
import Button from '@/components/ui/Button.vue';
import type { CategoryOption, OrganizerSummary } from '@/events';
import { index as templatesIndex, store } from '@/routes/event-templates';

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
</script>

<template>
    <PageHeader
        :title="t('events.templates.createTitle')"
        :close="templatesIndex().url"
        :back="templatesIndex().url"
        :back-label="t('events.templates.title')"
        width="compose"
    >
        <template #actions>
            <Button :as="Link" :href="templatesIndex().url" variant="ghost">
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
                        ? t('events.templates.form.creating')
                        : t('events.templates.form.create')
                }}
            </Button>
        </template>
    </PageHeader>

    <PageContainer width="compose" class="pt-5 sm:pt-0">
        <EventForm
            v-model:processing="processing"
            :form="store.form()"
            :categories="props.categories"
            :organizers="props.organizers"
            :values="blank"
            :submit-label="t('events.templates.form.create')"
            :processing-label="t('events.templates.form.creating')"
            template
            is-new
        >
            <template #cancel>
                <Button :as="Link" :href="templatesIndex().url" variant="ghost">
                    {{ t('events.form.cancel') }}
                </Button>
            </template>
        </EventForm>
    </PageContainer>
</template>
