<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { Building2, ChevronRight, Plus } from '@lucide/vue';
import { useI18n } from 'vue-i18n';
import SettingsLayout from '@/components/settings/SettingsLayout.vue';
import Avatar from '@/components/ui/Avatar.vue';
import Button from '@/components/ui/Button.vue';
import Card from '@/components/ui/Card.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import ListRow from '@/components/ui/ListRow.vue';
import SectionHeader from '@/components/ui/SectionHeader.vue';
import Tag from '@/components/ui/Tag.vue';
import type { OrganizerSummary } from '@/events';
import { toastError } from '@/lib/toast';
import { create, show } from '@/routes/organizers';
import { update as answerInvitation } from '@/routes/organizers/spokespersons';

/**
 * The organizers the user speaks for, and invitations to speak for more.
 */
const props = defineProps<{
    organizers: (OrganizerSummary & {
        role: 'main' | 'spokesperson';
        listed: boolean;
        events_count: number;
    })[];
    invitations: {
        id: number;
        organizer: OrganizerSummary;
        invited_by: string | null;
    }[];
}>();

const { t } = useI18n();
const page = usePage();

function answer(organizerId: number, action: 'accept' | 'decline'): void {
    router.patch(
        answerInvitation([organizerId, page.props.auth.user.id]).url,
        { action },
        {
            preserveScroll: true,
            onError: () => toastError(t('ui.somethingWentWrong')),
        },
    );
}
</script>

<template>
    <SettingsLayout
        section="organizers"
        :title="t('organizers.title')"
        :lede="t('organizers.lede')"
    >
        <template v-if="props.invitations.length">
            <SectionHeader :title="t('organizers.invitations')" />
            <Card class="mb-6">
                <ListRow
                    v-for="invitation in props.invitations"
                    :key="invitation.id"
                    :title="invitation.organizer.name"
                    :sub="
                        invitation.invited_by
                            ? t('organizers.invitedBy', {
                                  name: invitation.invited_by,
                              })
                            : undefined
                    "
                    :inset="74"
                >
                    <template #leading>
                        <Avatar
                            :name="invitation.organizer.name"
                            :src="invitation.organizer.avatar_url"
                            :size="44"
                            decorative
                        />
                    </template>
                    <p class="mt-2 text-meta text-ink-muted">
                        {{
                            t('notifications.spokespersonTerms', {
                                organizer: invitation.organizer.name,
                            })
                        }}
                    </p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <Button
                            variant="primary"
                            size="sm"
                            @click="answer(invitation.organizer.id, 'accept')"
                        >
                            {{ t('organizers.page.accept') }}
                        </Button>
                        <Button
                            size="sm"
                            @click="answer(invitation.organizer.id, 'decline')"
                        >
                            {{ t('organizers.page.decline') }}
                        </Button>
                    </div>
                </ListRow>
            </Card>
        </template>

        <div class="mb-4 flex items-center justify-between gap-3">
            <SectionHeader :title="t('organizers.title')" class="mb-0" />
            <Button
                :as="Link"
                :href="create().url"
                :icon="Plus"
                size="sm"
                variant="primary"
            >
                {{ t('organizers.create') }}
            </Button>
        </div>

        <Card v-if="props.organizers.length">
            <ListRow
                v-for="organizer in props.organizers"
                :key="organizer.id"
                :as="Link"
                :href="show(organizer.id).url"
                interactive
                :inset="74"
            >
                <template #leading>
                    <Avatar
                        :name="organizer.name"
                        :src="organizer.avatar_url"
                        :size="44"
                        decorative
                    />
                </template>
                <template #title>
                    <span class="flex min-w-0 items-center gap-2">
                        <span class="truncate">{{ organizer.name }}</span>
                        <Tag tone="host">{{
                            t(`organizers.role.${organizer.role}`)
                        }}</Tag>
                    </span>
                </template>
                <template #sub>
                    {{ '@' + organizer.handle }} ·
                    {{ t('organizers.eventsCount', organizer.events_count) }}
                </template>
                <template #trailing>
                    <ChevronRight
                        :size="18"
                        :stroke-width="1.9"
                        class="shrink-0 text-ink-subtle"
                        aria-hidden="true"
                    />
                </template>
            </ListRow>
        </Card>
        <EmptyState
            v-else
            :icon="Building2"
            :title="t('organizers.none')"
            :body="t('organizers.noneHint')"
        />
    </SettingsLayout>
</template>
