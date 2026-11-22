<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { CalendarDays, LayoutTemplate, Pencil, Plus, Trash } from '@lucide/vue';
import { useI18n } from 'vue-i18n';
import Pagination from '@/components/Pagination.vue';
import type { PaginationSummary } from '@/components/Pagination.vue';
import PageContainer from '@/components/shell/PageContainer.vue';
import PageHeader from '@/components/shell/PageHeader.vue';
import Button from '@/components/ui/Button.vue';
import Card from '@/components/ui/Card.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import IconButton from '@/components/ui/IconButton.vue';
import ListRow from '@/components/ui/ListRow.vue';
import SectionHeader from '@/components/ui/SectionHeader.vue';
import { confirmAction } from '@/composables/useConfirm';
import type { EventTemplateSummary, OrganizerSummary } from '@/events';
import { useDates } from '@/lib/dates';
import { toast } from '@/lib/toast';
import {
    create as createTemplate,
    destroy as destroyTemplate,
    edit as editTemplate,
} from '@/routes/event-templates';
import {
    create as createEvent,
    index as eventsIndex,
    show as showEvent,
} from '@/routes/events';
import eventTemplate from '@/routes/events/template';

interface HostedEvent {
    id: number;
    name: string;
    starts_at: string;
    place: string | null;
    city: string | null;
    organizer: OrganizerSummary | null;
}

/**
 * The host's templates, each a start for a new event, and under them the
 * events they have hosted (newest first): any of those can be made into a
 * template, or used once as the start of a new event.
 */
const props = defineProps<{
    templates: EventTemplateSummary[];
    events: HostedEvent[];
    pagination: PaginationSummary;
}>();

const { t } = useI18n();
const dates = useDates();

function templateDetails(template: EventTemplateSummary): string | undefined {
    const times = template.starts_time
        ? [template.starts_time, template.ends_time].filter(Boolean).join('–')
        : null;

    return (
        [times, template.place ?? template.city, template.organizer?.name]
            .filter(Boolean)
            .join(' · ') || undefined
    );
}

function eventDetails(event: HostedEvent): string {
    return [
        dates.dateTime(event.starts_at),
        event.place ?? event.city,
        event.organizer?.name,
    ]
        .filter(Boolean)
        .join(' · ');
}

async function deleteTemplate(template: EventTemplateSummary): Promise<void> {
    const confirmed = await confirmAction({
        title: t('events.templates.confirmDeleteTitle'),
        message: t('events.templates.confirmDelete'),
        confirmLabel: t('events.templates.delete'),
    });

    if (confirmed) {
        router.delete(destroyTemplate(template.id).url, {
            preserveScroll: true,
            onSuccess: () =>
                toast({ title: t('events.templates.deleted'), icon: Trash }),
        });
    }
}

function makeTemplate(event: HostedEvent): void {
    router.post(eventTemplate.store(event.id).url);
}
</script>

<template>
    <PageHeader
        :title="t('events.templates.title')"
        :lede="t('events.templates.lede')"
        :back="eventsIndex().url"
        :back-label="t('events.show.back')"
    >
        <template #actions>
            <Button
                :as="Link"
                :href="createTemplate().url"
                :icon="Plus"
                variant="primary"
            >
                {{ t('events.templates.new') }}
            </Button>
        </template>
    </PageHeader>

    <PageContainer class="pt-5 sm:pt-0">
        <p class="mb-4 text-ink-muted sm:hidden">
            {{ t('events.templates.lede') }}
        </p>
        <Button
            :as="Link"
            :href="createTemplate().url"
            :icon="Plus"
            variant="primary"
            class="mb-5 sm:hidden"
        >
            {{ t('events.templates.new') }}
        </Button>

        <Card v-if="props.templates.length">
            <ListRow
                v-for="template in props.templates"
                :key="template.id"
                :title="template.name"
                :sub="templateDetails(template)"
                :inset="74"
            >
                <template #leading>
                    <img
                        v-if="template.cover"
                        :src="template.cover.url"
                        alt=""
                        class="size-11 shrink-0 rounded-md bg-surface-2 object-cover"
                    />
                    <span
                        v-else
                        class="grid size-11 shrink-0 place-items-center rounded-md bg-surface-2 text-ink-muted"
                        aria-hidden="true"
                    >
                        <LayoutTemplate :size="20" :stroke-width="1.8" />
                    </span>
                </template>
                <div class="mt-2.5 flex flex-wrap gap-2">
                    <Button
                        :as="Link"
                        :href="
                            createEvent({ query: { template: template.id } })
                                .url
                        "
                        size="sm"
                        :aria-label="
                            t('events.templates.useLabel', {
                                name: template.name,
                            })
                        "
                    >
                        {{ t('events.templates.use') }}
                    </Button>
                    <Button
                        :as="Link"
                        :href="editTemplate(template.id).url"
                        size="sm"
                        variant="ghost"
                        :icon="Pencil"
                        :aria-label="
                            t('events.templates.editLabel', {
                                name: template.name,
                            })
                        "
                    >
                        {{ t('events.templates.edit') }}
                    </Button>
                </div>
                <template #trailing>
                    <IconButton
                        :icon="Trash"
                        :label="
                            t('events.templates.deleteLabel', {
                                name: template.name,
                            })
                        "
                        @click="deleteTemplate(template)"
                    />
                </template>
            </ListRow>
        </Card>
        <Card v-else>
            <EmptyState
                :icon="LayoutTemplate"
                :title="t('events.templates.emptyTitle')"
                :body="t('events.templates.emptyBody')"
            />
        </Card>

        <SectionHeader :title="t('events.templates.eventsTitle')" />
        <p class="mb-3.5 text-ink-muted">
            {{
                props.events.length
                    ? t('events.templates.eventsHint')
                    : t('events.templates.eventsEmpty')
            }}
        </p>
        <Card v-if="props.events.length">
            <ListRow
                v-for="event in props.events"
                :key="event.id"
                :sub="eventDetails(event)"
            >
                <template #title>
                    <Link
                        :href="showEvent(event.id).url"
                        class="hover:underline"
                        >{{ event.name }}</Link
                    >
                </template>
                <div class="mt-2.5 flex flex-wrap gap-2">
                    <Button
                        size="sm"
                        :icon="LayoutTemplate"
                        @click="makeTemplate(event)"
                    >
                        {{ t('events.templates.makeTemplate') }}
                    </Button>
                    <Button
                        :as="Link"
                        :href="createEvent({ query: { from: event.id } }).url"
                        size="sm"
                        variant="ghost"
                        :icon="CalendarDays"
                    >
                        {{ t('events.templates.newFromEvent') }}
                    </Button>
                </div>
            </ListRow>
        </Card>
        <Pagination :pagination="props.pagination" />
    </PageContainer>
</template>
