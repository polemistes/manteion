<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { Check, ChevronDown, LayoutTemplate } from '@lucide/vue';
import { computed, ref, shallowRef } from 'vue';
import { useI18n } from 'vue-i18n';
import EventForm, { EVENT_FORM_ID } from '@/components/events/EventForm.vue';
import type {
    EventClockTimes,
    EventFormValues,
} from '@/components/events/EventForm.vue';
import FacebookImport from '@/components/events/FacebookImport.vue';
import type { ImportedEvent } from '@/components/events/FacebookImport.vue';
import PageContainer from '@/components/shell/PageContainer.vue';
import PageHeader from '@/components/shell/PageHeader.vue';
import Button from '@/components/ui/Button.vue';
import Menu from '@/components/ui/Menu.vue';
import MenuItem from '@/components/ui/MenuItem.vue';
import MenuSeparator from '@/components/ui/MenuSeparator.vue';
import type {
    CategoryOption,
    EventTemplateSummary,
    OrganizerSummary,
} from '@/events';
import { index as templatesIndex } from '@/routes/event-templates';
import { create, index as eventsIndex, store } from '@/routes/events';

/**
 * What the new event starts from (App\Services\EventStartingPoints): a
 * template or an earlier event, with everything but the day.
 */
interface StartingPoint extends EventClockTimes {
    source: { type: 'template' | 'event'; id: number; name: string };
    /** Names the cover for the server to copy, or null without one. */
    cover_from: string | null;
    values: Omit<EventFormValues, 'starts_at' | 'ends_at'>;
}

const props = defineProps<{
    categories: CategoryOption[];
    organizers: OrganizerSummary[];
    templates: EventTemplateSummary[];
    startFrom: StartingPoint | null;
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
// (a new key) from the imported values. A template or an earlier event
// arrives with the page, which is new each time one is picked.
const values = ref<EventFormValues>(
    props.startFrom
        ? {
              ...blank,
              ...props.startFrom.values,
              starts_at: null,
              ends_at: null,
          }
        : blank,
);
const times = ref<EventClockTimes | null>(
    props.startFrom
        ? {
              starts_time: props.startFrom.starts_time,
              ends_time: props.startFrom.ends_time,
              ends_days_later: props.startFrom.ends_days_later,
          }
        : null,
);
// The cover the server copies for the new event, until the host removes it.
const coverFrom = ref(props.startFrom?.cover_from ?? null);
const started = ref(props.startFrom?.source ?? null);
const importedCover = shallowRef<Blob | null>(null);
const formKey = ref(0);

const extraFields = computed(() => {
    const fields: Record<string, string> = {};

    if (coverFrom.value) {
        fields.cover_from = coverFrom.value;
    }

    return fields;
});

function startFromImport(event: ImportedEvent, cover: Blob | null): void {
    values.value = { ...blank, ...event };
    times.value = null;
    coverFrom.value = null;
    started.value = null;
    importedCover.value = cover;
    formKey.value += 1;
}

function useTemplate(id: number): void {
    router.visit(create({ query: { template: id } }).url);
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
        <div class="mb-7 flex flex-col xl:max-w-[640px]">
            <Menu v-if="props.templates.length" align="start">
                <template #trigger>
                    <button
                        type="button"
                        class="inline-flex min-h-11 items-center gap-1.5 self-start text-label font-semibold text-ink-muted underline underline-offset-2 hover:text-ink"
                    >
                        <LayoutTemplate
                            :size="16"
                            :stroke-width="2"
                            aria-hidden="true"
                        />
                        {{ t('events.startFrom.open') }}
                        <ChevronDown
                            :size="16"
                            :stroke-width="2"
                            aria-hidden="true"
                        />
                    </button>
                </template>
                <MenuItem
                    v-for="template in props.templates"
                    :key="template.id"
                    :hint="template.starts_time ?? undefined"
                    @select="useTemplate(template.id)"
                    >{{ template.name }}</MenuItem
                >
                <MenuSeparator />
                <MenuItem @select="router.visit(templatesIndex().url)">{{
                    t('events.startFrom.manage')
                }}</MenuItem>
            </Menu>
            <Link
                v-else
                :href="templatesIndex().url"
                class="inline-flex min-h-11 items-center gap-1.5 self-start text-label font-semibold text-ink-muted underline underline-offset-2 hover:text-ink"
            >
                <LayoutTemplate
                    :size="16"
                    :stroke-width="2"
                    aria-hidden="true"
                />
                {{ t('events.startFrom.manage') }}
            </Link>
            <p
                v-if="started"
                class="mt-1 mb-2.5 flex items-start gap-1.5 text-meta text-ink-muted"
                role="status"
            >
                <Check
                    :size="16"
                    :stroke-width="2"
                    class="mt-0.5 shrink-0"
                    aria-hidden="true"
                />
                {{
                    started.type === 'template'
                        ? t('events.startFrom.fromTemplate', {
                              name: started.name,
                          })
                        : t('events.startFrom.fromEvent', {
                              name: started.name,
                          })
                }}
            </p>
            <FacebookImport @imported="startFromImport" />
        </div>
        <EventForm
            :key="formKey"
            v-model:processing="processing"
            :form="store.form()"
            :categories="props.categories"
            :organizers="props.organizers"
            :values="values"
            :times="times"
            :extra-fields="extraFields"
            :initial-image="importedCover"
            :starts-changed="formKey > 0"
            :submit-label="t('events.form.create')"
            :processing-label="t('events.form.creating')"
            :note="t('events.form.inviteNext')"
            is-new
            @remove-image="coverFrom = null"
        >
            <template #cancel>
                <Button :as="Link" :href="eventsIndex().url" variant="ghost">
                    {{ t('events.form.cancel') }}
                </Button>
            </template>
        </EventForm>
    </PageContainer>
</template>
