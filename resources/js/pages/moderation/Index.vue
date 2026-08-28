<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
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
    type: 'message' | 'event' | 'comment';
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
}>();

function typeLabel(type: Reportable['type']): string {
    return t(`moderation.type.${type}`);
}

function dismiss(report: ModerationReport): void {
    router.patch(dismissReport(report.id).url, {}, { preserveScroll: true });
}

async function deleteContent(report: ModerationReport): Promise<void> {
    // Events don't offer the "leave a placeholder" choice (unlike comments
    // and messages) — just confirm and delete.
    if (report.reportable.type === 'event') {
        const confirmed = await confirmAction({
            message: t('events.show.confirmDeleteEvent'),
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

    <h1 class="mb-6 text-xl font-bold">{{ t('moderation.title') }}</h1>

    <p v-if="!props.reports.length" class="text-sm text-black/70">
        {{ t('moderation.noReports') }}
    </p>

    <ul class="flex flex-col gap-4">
        <li
            v-for="report in props.reports"
            :key="report.id"
            class="border-[3px] border-black p-4"
        >
            <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                <span class="text-xs font-bold uppercase">{{
                    typeLabel(report.reportable.type)
                }}</span>
                <Link :href="report.reportable.url" class="text-xs underline">{{
                    t('moderation.viewInContext')
                }}</Link>
            </div>

            <p class="mb-2 font-sans text-sm">
                {{
                    report.reportable.excerpt ?? t('moderation.alreadyDeleted')
                }}
            </p>

            <p class="mb-1 text-xs text-black/70">
                {{
                    t('moderation.byAuthor', {
                        name: report.reportable.author.name,
                    })
                }}
            </p>
            <p class="mb-1 text-xs text-black/70">
                {{
                    t('moderation.reportedByReason', {
                        name: report.reporter.name,
                        reason: report.reason ?? t('moderation.noReasonGiven'),
                    })
                }}
            </p>
            <p class="mb-3 text-xs text-black/50">
                {{ formatDate(report.created_at) }}
            </p>

            <div class="flex flex-wrap gap-2">
                <button
                    type="button"
                    class="border border-black bg-black px-3 py-1 text-xs text-white hover:bg-white hover:text-black"
                    @click="deleteContent(report)"
                >
                    {{ t('moderation.deleteContent') }}
                </button>
                <button
                    type="button"
                    class="border border-black px-3 py-1 text-xs hover:bg-black hover:text-white"
                    @click="dismiss(report)"
                >
                    {{ t('moderation.dismiss') }}
                </button>
                <button
                    type="button"
                    class="border border-black px-3 py-1 text-xs hover:bg-black hover:text-white"
                    @click="messageAuthor(report.reporter)"
                >
                    {{ t('moderation.messageReporter') }}
                </button>
                <button
                    type="button"
                    class="border border-black px-3 py-1 text-xs hover:bg-black hover:text-white"
                    @click="messageAuthor(report.reportable.author)"
                >
                    {{ t('moderation.messageReportedUser') }}
                </button>
                <button
                    type="button"
                    class="border border-black px-3 py-1 text-xs hover:bg-black hover:text-white"
                    @click="suspendAuthor(report.reportable.author)"
                >
                    {{ t('moderation.suspendUser') }}
                </button>
            </div>
        </li>
    </ul>
</template>
