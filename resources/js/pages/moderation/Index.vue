<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import Pagination from '@/components/Pagination.vue';
import type { PaginationSummary } from '@/components/Pagination.vue';
import { confirmAction } from '@/composables/useConfirm';
import { staffDelete } from '@/composables/useStaffDelete';
import { suspend as adminUsersSuspend } from '@/routes/admin/users';
import { update as dismissReport } from '@/routes/moderation/reports';
import { message as messageUser } from '@/routes/moderation/users';

const { t, locale } = useI18n();

const dateFnsLocales: Record<string, string> = {
    en: 'en-GB',
    nb: 'nb-NO',
};

function formatDate(value: string): string {
    return new Date(value).toLocaleString(
        dateFnsLocales[locale.value] ?? locale.value,
        { dateStyle: 'medium', timeStyle: 'short' },
    );
}

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

function typeLabel(type: Reportable['type']): string {
    return t(`moderation.type.${type}`);
}

function dismiss(report: ModerationReport): void {
    router.patch(dismissReport(report.id).url, {}, { preserveScroll: true });
}

async function deleteContent(report: ModerationReport): Promise<void> {
    // Events and leaflets don't offer the "leave a placeholder" choice
    // (unlike comments and messages) — just confirm and delete.
    if (
        report.reportable.type === 'event' ||
        report.reportable.type === 'leaflet' ||
        report.reportable.type === 'leaflet_response'
    ) {
        const confirmed = await confirmAction({
            message: t(
                report.reportable.type === 'event'
                    ? 'events.show.confirmDeleteEvent'
                    : report.reportable.type === 'leaflet'
                      ? 'leaflets.card.confirmDelete'
                      : 'leaflets.responses.confirmDelete',
            ),
        });

        if (confirmed) {
            router.delete(report.reportable.delete_url, {
                preserveScroll: true,
            });
        }

        return;
    }

    const choice = await staffDelete({
        message: t('moderation.confirmDeleteContent'),
    });

    if (choice === null) {
        return;
    }

    router.delete(report.reportable.delete_url, {
        data: { leave_placeholder: choice === 'placeholder' },
        preserveScroll: true,
    });
}

function messageAuthor(user: ReportedUser): void {
    router.post(messageUser(user.id).url);
}

async function suspendAuthor(user: ReportedUser): Promise<void> {
    router.post(adminUsersSuspend(user.id).url, {}, { preserveScroll: true });
}
</script>

<template>
    <Head :title="t('moderation.title')" />

    <h1 class="text-xl mb-6 font-bold">{{ t('moderation.title') }}</h1>

    <p v-if="!props.reports.length" class="text-sm text-ink/70">
        {{ t('moderation.noReports') }}
    </p>

    <ul class="flex flex-col gap-4">
        <li
            v-for="report in props.reports"
            :key="report.id"
            class="border-frame border-edge p-4"
        >
            <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                <span class="text-xs font-bold uppercase">{{
                    typeLabel(report.reportable.type)
                }}</span>
                <Link :href="report.reportable.url" class="text-xs underline">{{
                    t('moderation.viewInContext')
                }}</Link>
            </div>

            <p class="text-sm mb-2 font-sans">
                {{
                    report.reportable.excerpt ?? t('moderation.alreadyDeleted')
                }}
            </p>

            <p class="text-xs mb-1 text-ink/70">
                {{
                    t('moderation.byAuthor', {
                        name: report.reportable.author.name,
                    })
                }}
            </p>
            <p class="text-xs mb-1 text-ink/70">
                {{
                    t('moderation.reportedByReason', {
                        name: report.reporter.name,
                        reason: report.reason ?? t('moderation.noReasonGiven'),
                    })
                }}
            </p>
            <p class="text-xs mb-3 text-ink/50">
                {{ formatDate(report.created_at) }}
            </p>

            <div class="flex flex-wrap gap-2">
                <button
                    type="button"
                    class="border-edge text-xs hover:bg-surface-raised border bg-accent px-3 py-1 text-on-accent hover:text-ink"
                    @click="deleteContent(report)"
                >
                    {{ t('moderation.deleteContent') }}
                </button>
                <button
                    type="button"
                    class="border-edge text-xs border px-3 py-1 hover:bg-accent hover:text-on-accent"
                    @click="dismiss(report)"
                >
                    {{ t('moderation.dismiss') }}
                </button>
                <button
                    type="button"
                    class="border-edge text-xs border px-3 py-1 hover:bg-accent hover:text-on-accent"
                    @click="messageAuthor(report.reporter)"
                >
                    {{ t('moderation.messageReporter') }}
                </button>
                <button
                    type="button"
                    class="border-edge text-xs border px-3 py-1 hover:bg-accent hover:text-on-accent"
                    @click="messageAuthor(report.reportable.author)"
                >
                    {{ t('moderation.messageReportedUser') }}
                </button>
                <button
                    type="button"
                    class="border-edge text-xs border px-3 py-1 hover:bg-accent hover:text-on-accent"
                    @click="suspendAuthor(report.reportable.author)"
                >
                    {{ t('moderation.suspendUser') }}
                </button>
            </div>
        </li>
    </ul>

    <Pagination
        :pagination="props.pagination"
        :only="['reports', 'pagination']"
    />
</template>
