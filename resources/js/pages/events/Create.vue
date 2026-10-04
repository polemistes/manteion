<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Check } from '@lucide/vue';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import EventForm, { EVENT_FORM_ID } from '@/components/events/EventForm.vue';
import PageContainer from '@/components/shell/PageContainer.vue';
import PageHeader from '@/components/shell/PageHeader.vue';
import Button from '@/components/ui/Button.vue';
import type { CategoryOption } from '@/events';
import { index as eventsIndex, store } from '@/routes/events';

const props = defineProps<{
    categories: CategoryOption[];
}>();

const { t } = useI18n();

const processing = ref(false);
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
        <EventForm
            v-model:processing="processing"
            :form="store.form()"
            :categories="props.categories"
            :values="{
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
                visibility: 'private',
                allow_invitee_invites: false,
                allow_invitee_comments: true,
                tag_ids: [],
                image: null,
            }"
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
