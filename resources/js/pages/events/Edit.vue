<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { Check, Trash } from '@lucide/vue';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import EventForm, { EVENT_FORM_ID } from '@/components/events/EventForm.vue';
import PageContainer from '@/components/shell/PageContainer.vue';
import PageHeader from '@/components/shell/PageHeader.vue';
import Button from '@/components/ui/Button.vue';
import { confirmAction } from '@/composables/useConfirm';
import type { CategoryOption, EventCover } from '@/events';
import { toast } from '@/lib/toast';
import { show as eventsShow, update } from '@/routes/events';
import { destroy as destroyImage } from '@/routes/events/images';

const props = defineProps<{
    event: {
        id: number;
        name: string;
        place: string | null;
        latitude: number | null;
        longitude: number | null;
        city: string | null;
        country: string | null;
        country_code: string | null;
        description: string;
        starts_at: string;
        ends_at: string | null;
        visibility: 'private' | 'public' | 'searchable';
        is_searchable: boolean;
        allow_invitee_invites: boolean;
        allow_invitee_comments: boolean;
        tag_ids: number[];
        image: EventCover | null;
    };
    categories: CategoryOption[];
}>();

const { t } = useI18n();

const processing = ref(false);

async function removeImage(): Promise<void> {
    const image = props.event.image;

    if (!image) {
        return;
    }

    const confirmed = await confirmAction({
        title: t('events.form.confirmRemoveImageTitle'),
        message: t('events.form.confirmRemoveImage'),
        confirmLabel: t('events.form.removeImage'),
    });

    if (confirmed) {
        router.delete(destroyImage([props.event.id, image.id]).url, {
            preserveScroll: true,
            onSuccess: () =>
                toast({ title: t('events.form.imageRemoved'), icon: Trash }),
        });
    }
}
</script>

<template>
    <PageHeader
        :title="t('events.form.editTitle')"
        :close="eventsShow(props.event.id).url"
        :back="eventsShow(props.event.id).url"
        :back-label="props.event.name"
        width="compose"
    >
        <template #actions>
            <Button
                :as="Link"
                :href="eventsShow(props.event.id).url"
                variant="ghost"
            >
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
                    processing ? t('events.form.saving') : t('events.form.save')
                }}
            </Button>
        </template>
    </PageHeader>

    <PageContainer width="compose" class="pt-5 sm:pt-0">
        <EventForm
            :key="props.event.image?.id ?? 'no-image'"
            v-model:processing="processing"
            :form="update.form(props.event.id)"
            :categories="props.categories"
            :values="{
                ...props.event,
                visibility:
                    props.event.visibility === 'private' ? 'private' : 'public',
            }"
            :submit-label="t('events.form.save')"
            :processing-label="t('events.form.saving')"
            @remove-image="removeImage"
        >
            <template #cancel>
                <Button
                    :as="Link"
                    :href="eventsShow(props.event.id).url"
                    variant="ghost"
                >
                    {{ t('events.form.cancel') }}
                </Button>
            </template>
        </EventForm>
    </PageContainer>
</template>
