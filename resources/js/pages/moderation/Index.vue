<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import {
    Ellipsis,
    ExternalLink,
    MessageCircle,
    ShieldBan,
    ShieldCheck,
    Trash,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import Pagination from '@/components/Pagination.vue';
import type { PaginationSummary } from '@/components/Pagination.vue';
import PageContainer from '@/components/shell/PageContainer.vue';
import PageHeader from '@/components/shell/PageHeader.vue';
import AppDialog from '@/components/ui/AppDialog.vue';
import Button from '@/components/ui/Button.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import Menu from '@/components/ui/Menu.vue';
import MenuItem from '@/components/ui/MenuItem.vue';
import Tag from '@/components/ui/Tag.vue';
import TextArea from '@/components/ui/TextArea.vue';
import { confirmAction } from '@/composables/useConfirm';
import { staffDelete } from '@/composables/useStaffDelete';
import { useDates } from '@/lib/dates';
import { toast, toastError } from '@/lib/toast';
import { suspend as suspendUser } from '@/routes/admin/users';
import { update as dismissReport } from '@/routes/moderation/reports';
import { message as messageUser } from '@/routes/moderation/users';
import { index as settingsIndex } from '@/routes/settings';

/**
 * The reports waiting for staff: what was reported, by whom and why, with
 * the way to it, and deleting it, dismissing the report, writing to either
 * person or suspending the author.
 */
interface ReportedUser {
    id: number;
    name: string;
    username: string;
}

interface Reportable {
    type: 'message' | 'event' | 'comment' | 'leaflet' | 'leaflet_response';
    excerpt: string | null;
    is_deleted: boolean;
    author: ReportedUser;
    url: string;
    delete_url: string;
}

interface ModerationReport {
    id: number;
    reason: string | null;
    created_at: string;
    reporter: ReportedUser;
    reportable: Reportable;
}

const props = defineProps<{
    reports: ModerationReport[];
    pagination: PaginationSummary;
}>();

const { t } = useI18n();
const dates = useDates();

const options = {
    preserveScroll: true,
    onError: () => toastError(t('settings.saveFailed')),
};

function dismiss(report: ModerationReport): void {
    router.patch(
        dismissReport(report.id).url,
        {},
        {
            ...options,
            onSuccess: () => toast({ title: t('moderation.dismissed') }),
        },
    );
}

async function deleteContent(report: ModerationReport): Promise<void> {
    // Events and leaflets offer no "leave a placeholder" choice, unlike
    // comments and messages: just confirm and delete.
    if (
        report.reportable.type === 'event' ||
        report.reportable.type === 'leaflet' ||
        report.reportable.type === 'leaflet_response'
    ) {
        const confirmed = await confirmAction({
            title: t('moderation.deleteTitle', {
                type: t(`moderation.type.${report.reportable.type}`),
            }),
            message: t('moderation.deleteBody'),
            confirmLabel: t('moderation.deleteContent'),
        });

        if (confirmed) {
            router.delete(report.reportable.delete_url, options);
        }

        return;
    }

    const choice = await staffDelete({
        message: t('moderation.confirmDeleteContent'),
    });

    if (choice !== null) {
        router.delete(report.reportable.delete_url, {
            ...options,
            data: { leave_placeholder: choice === 'placeholder' },
        });
    }
}

function write(user: ReportedUser): void {
    router.post(messageUser(user.id).url);
}

// Suspending asks for a reason, which other staff see.
const suspendFor = ref<ReportedUser | null>(null);
const reason = ref('');
const suspending = ref(false);

const suspendOpen = computed({
    get: () => suspendFor.value !== null,
    set: (isOpen: boolean) => {
        if (!isOpen) {
            suspendFor.value = null;
        }
    },
});

function openSuspend(user: ReportedUser): void {
    reason.value = '';
    suspendFor.value = user;
}

function suspend(): void {
    const user = suspendFor.value;

    if (!user) {
        return;
    }

    suspending.value = true;
    router.post(
        suspendUser(user.id).url,
        { reason: reason.value.trim() || null },
        {
            ...options,
            onSuccess: () => {
                suspendFor.value = null;
                toast({
                    title: t('admin.users.suspendedToast', { name: user.name }),
                    icon: ShieldBan,
                });
            },
            onFinish: () => {
                suspending.value = false;
            },
        },
    );
}
</script>

<template>
    <PageHeader
        :title="t('moderation.title')"
        :lede="t('moderation.lede')"
        :back="settingsIndex().url"
        :back-label="t('settings.title')"
    />

    <PageContainer class="pt-4 pb-16 sm:pt-0">
        <ul v-if="props.reports.length" class="flex flex-col gap-4">
            <li
                v-for="report in props.reports"
                :key="report.id"
                class="rounded-lg border border-line bg-surface p-4 sm:p-5"
            >
                <div class="flex items-start gap-3">
                    <div class="min-w-0 flex-1">
                        <p class="flex flex-wrap items-center gap-2">
                            <Tag tone="host">{{
                                t(`moderation.type.${report.reportable.type}`)
                            }}</Tag>
                            <span class="text-meta text-ink-muted">{{
                                t('moderation.reportedBy', {
                                    name: report.reporter.name,
                                    ago: dates.ago(report.created_at),
                                })
                            }}</span>
                        </p>
                        <p class="mt-1 text-meta text-ink-muted">
                            {{
                                t('moderation.byAuthor', {
                                    name: report.reportable.author.name,
                                })
                            }}
                        </p>
                    </div>
                    <Menu>
                        <template #trigger>
                            <button
                                type="button"
                                :aria-label="t('moderation.more')"
                                class="grid size-11 shrink-0 place-items-center rounded-full text-ink-muted hover:bg-surface-2 hover:text-ink"
                            >
                                <Ellipsis
                                    :size="20"
                                    :stroke-width="2"
                                    aria-hidden="true"
                                />
                            </button>
                        </template>
                        <MenuItem
                            :icon="MessageCircle"
                            @select="write(report.reporter)"
                        >
                            {{
                                t('moderation.writeTo', {
                                    name: report.reporter.name,
                                })
                            }}
                        </MenuItem>
                        <MenuItem
                            :icon="MessageCircle"
                            @select="write(report.reportable.author)"
                        >
                            {{
                                t('moderation.writeTo', {
                                    name: report.reportable.author.name,
                                })
                            }}
                        </MenuItem>
                        <MenuItem
                            :icon="ShieldBan"
                            danger
                            @select="openSuspend(report.reportable.author)"
                        >
                            {{
                                t('moderation.suspendName', {
                                    name: report.reportable.author.name,
                                })
                            }}
                        </MenuItem>
                    </Menu>
                </div>

                <blockquote
                    class="mt-3 rounded-r-sm border-l-2 border-line-strong bg-canvas px-3.5 py-2.5 whitespace-pre-line text-ink"
                    :class="
                        report.reportable.excerpt
                            ? ''
                            : 'font-display text-ink-muted italic'
                    "
                >
                    {{
                        report.reportable.excerpt ??
                        t('moderation.alreadyDeleted')
                    }}
                </blockquote>

                <p class="mt-3 text-body text-ink">
                    <span class="font-semibold">{{
                        t('moderation.reason')
                    }}</span>
                    {{ report.reason ?? t('moderation.noReasonGiven') }}
                </p>

                <div class="mt-4 flex flex-wrap items-center gap-2">
                    <Button
                        v-if="report.reportable.excerpt"
                        variant="danger"
                        size="sm"
                        :icon="Trash"
                        @click="deleteContent(report)"
                    >
                        {{ t('moderation.deleteContent') }}
                    </Button>
                    <Button
                        size="sm"
                        :icon="ShieldCheck"
                        @click="dismiss(report)"
                    >
                        {{ t('moderation.dismiss') }}
                    </Button>
                    <Button
                        :as="Link"
                        :href="report.reportable.url"
                        variant="ghost"
                        size="sm"
                        :icon="ExternalLink"
                    >
                        {{ t('moderation.viewInContext') }}
                    </Button>
                </div>
            </li>
        </ul>
        <EmptyState
            v-else
            :icon="ShieldCheck"
            :title="t('moderation.noReportsTitle')"
            :body="t('moderation.noReports')"
        />

        <Pagination
            :pagination="props.pagination"
            :only="['reports', 'pagination']"
        />
    </PageContainer>

    <AppDialog
        v-model:open="suspendOpen"
        :title="t('admin.users.suspendTitle', { name: suspendFor?.name ?? '' })"
        :description="t('admin.users.suspendHint')"
    >
        <form id="suspend-author" @submit.prevent="suspend">
            <TextArea
                v-model="reason"
                :label="t('admin.users.reason')"
                :placeholder="t('admin.users.suspendReasonPlaceholder')"
                optional
            />
        </form>
        <template #actions>
            <Button
                type="submit"
                form="suspend-author"
                variant="danger"
                :disabled="suspending"
            >
                {{ t('admin.users.suspend') }}
            </Button>
            <Button variant="ghost" @click="suspendFor = null">
                {{ t('ui.cancel') }}
            </Button>
        </template>
    </AppDialog>
</template>
