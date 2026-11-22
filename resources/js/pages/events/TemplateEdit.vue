<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Check } from '@lucide/vue';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import EventForm, { EVENT_FORM_ID } from '@/components/events/EventForm.vue';
import type {
    EventClockTimes,
    EventFormValues,
} from '@/components/events/EventForm.vue';
import PageContainer from '@/components/shell/PageContainer.vue';
import PageHeader from '@/components/shell/PageHeader.vue';
import Button from '@/components/ui/Button.vue';
import type { CategoryOption, OrganizerSummary } from '@/events';
import { index as templatesIndex, update } from '@/routes/event-templates';

const props = defineProps<{
    template: Omit<EventFormValues, 'starts_at' | 'ends_at'> &
        EventClockTimes & {
            id: number;
            /** The organizer whose spokespersons share it. */
            organizer: OrganizerSummary | null;
        };
    categories: CategoryOption[];
}>();

const { t } = useI18n();

const processing = ref(false);

// The cover goes when the form is saved, not before: until then it is
// only marked.
const coverRemoved = ref(false);

const extraFields = computed(() => {
    const fields: Record<string, string> = {};

    if (coverRemoved.value) {
        fields.remove_image = '1';
    }

    return fields;
});
</script>

<template>
    <PageHeader
        :title="t('events.templates.editTitle')"
        :close="templatesIndex().url"
        :back="templatesIndex().url"
        :back-label="t('events.templates.title')"
        :lede="
            props.template.organizer
                ? t('events.templates.sharedWith', {
                      name: props.template.organizer.name,
                  })
                : undefined
        "
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
                        ? t('events.templates.form.saving')
                        : t('events.templates.form.save')
                }}
            </Button>
        </template>
    </PageHeader>

    <PageContainer width="compose" class="pt-5 sm:pt-0">
        <EventForm
            v-model:processing="processing"
            :form="update.form(props.template.id)"
            :categories="props.categories"
            :values="{ ...props.template, starts_at: null, ends_at: null }"
            :times="props.template"
            :organizer="props.template.organizer"
            :extra-fields="extraFields"
            :submit-label="t('events.templates.form.save')"
            :processing-label="t('events.templates.form.saving')"
            template
            @remove-image="coverRemoved = true"
        >
            <template #cancel>
                <Button :as="Link" :href="templatesIndex().url" variant="ghost">
                    {{ t('events.form.cancel') }}
                </Button>
            </template>
        </EventForm>
    </PageContainer>
</template>
