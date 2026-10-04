<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import {
    Ellipsis,
    Eye,
    Mail,
    MessageCircle,
    ShieldBan,
    ShieldCheck,
    Trash,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import Pagination from '@/components/Pagination.vue';
import PasswordField from '@/components/PasswordField.vue';
import PageContainer from '@/components/shell/PageContainer.vue';
import PageHeader from '@/components/shell/PageHeader.vue';
import AppDialog from '@/components/ui/AppDialog.vue';
import Avatar from '@/components/ui/Avatar.vue';
import Button from '@/components/ui/Button.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import Menu from '@/components/ui/Menu.vue';
import MenuItem from '@/components/ui/MenuItem.vue';
import MenuSeparator from '@/components/ui/MenuSeparator.vue';
import SearchField from '@/components/ui/SearchField.vue';
import SelectField from '@/components/ui/SelectField.vue';
import Tag from '@/components/ui/Tag.vue';
import TextArea from '@/components/ui/TextArea.vue';
import TextField from '@/components/ui/TextField.vue';
import { confirmAction } from '@/composables/useConfirm';
import { toast, toastError } from '@/lib/toast';
import {
    destroy as destroyUser,
    impersonate as impersonateUser,
    index as adminUsersIndex,
    suspend as suspendUser,
    unsuspend as unsuspendUser,
} from '@/routes/admin/users';
import adminUsersEmail from '@/routes/admin/users/email';
import adminUsersRole from '@/routes/admin/users/role';
import { message as messageUser } from '@/routes/moderation/users';
import { index as settingsIndex } from '@/routes/settings';

/**
 * Administration: every account, found by name, username or email, with
 * its role and standing, and what staff can do about it.
 */
interface AdminUser {
    id: number;
    name: string;
    username: string;
    email: string;
    avatar_url: string | null;
    role: 'member' | 'moderator' | 'administrator';
    is_suspended: boolean;
    created_at: string;
}

const props = defineProps<{
    users: {
        data: AdminUser[];
        current_page: number;
        last_page: number;
        total: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    q: string;
}>();

const { t } = useI18n();
const page = usePage();

const myId = computed(() => page.props.auth.user.id);

const query = ref(props.q);
let timer: ReturnType<typeof setTimeout> | undefined;

watch(query, (value) => {
    clearTimeout(timer);
    timer = setTimeout(() => {
        router.get(
            adminUsersIndex().url,
            value.trim() ? { q: value.trim() } : {},
            { preserveState: true, replace: true },
        );
    }, 350);
});

const pagination = computed(() => ({
    current_page: props.users.current_page,
    last_page: props.users.last_page,
    total: props.users.total,
    previous_url: props.users.prev_page_url,
    next_url: props.users.next_page_url,
}));

const roles = computed(() => [
    { value: 'member', label: t('admin.users.roleMember') },
    { value: 'moderator', label: t('admin.users.roleModerator') },
    { value: 'administrator', label: t('admin.users.roleAdministrator') },
]);

const options = {
    preserveScroll: true,
    onError: () => toastError(t('settings.saveFailed')),
};

function setRole(user: AdminUser, role: string | undefined): void {
    if (role && role !== user.role) {
        router.patch(
            adminUsersRole.update(user.id).url,
            { role },
            {
                ...options,
                onSuccess: () =>
                    toast({
                        title: t('admin.users.roleSaved', { name: user.name }),
                    }),
            },
        );
    }
}

async function impersonate(user: AdminUser): Promise<void> {
    const confirmed = await confirmAction({
        title: t('admin.users.impersonateTitle', { name: user.name }),
        message: t('admin.users.confirmImpersonate', { name: user.name }),
        confirmLabel: t('admin.users.impersonate'),
        tone: 'primary',
    });

    if (confirmed) {
        router.post(impersonateUser(user.id).url);
    }
}

async function unsuspend(user: AdminUser): Promise<void> {
    const confirmed = await confirmAction({
        title: t('admin.users.unsuspendTitle', { name: user.name }),
        message: t('admin.users.confirmUnsuspend', { name: user.name }),
        confirmLabel: t('admin.users.unsuspend'),
        tone: 'primary',
    });

    if (confirmed) {
        router.delete(unsuspendUser(user.id).url, options);
    }
}

// Suspending, emailing and deleting each ask for a little more.
const target = ref<AdminUser | null>(null);
const dialog = ref<'suspend' | 'email' | 'delete' | null>(null);
const reason = ref('');
const subject = ref('');
const body = ref('');
const password = ref('');
const errors = ref<Record<string, string>>({});
const sending = ref(false);

function open(kind: 'suspend' | 'email' | 'delete', user: AdminUser): void {
    target.value = user;
    reason.value = '';
    subject.value = '';
    body.value = '';
    password.value = '';
    errors.value = {};
    dialog.value = kind;
}

const dialogOpen = computed({
    get: () => dialog.value !== null,
    set: (isOpen: boolean) => {
        if (!isOpen) {
            dialog.value = null;
        }
    },
});

function submit(): void {
    const user = target.value;

    if (!user || !dialog.value) {
        return;
    }

    const done = (title: string) => ({
        preserveScroll: true,
        onSuccess: () => {
            dialog.value = null;
            toast({ title });
        },
        onError: (next: Record<string, string>) => {
            errors.value = next;
        },
        onFinish: () => {
            sending.value = false;
        },
    });

    sending.value = true;

    if (dialog.value === 'suspend') {
        router.post(
            suspendUser(user.id).url,
            { reason: reason.value.trim() || null },
            done(t('admin.users.suspendedToast', { name: user.name })),
        );
    } else if (dialog.value === 'email') {
        router.post(
            adminUsersEmail.store(user.id).url,
            { subject: subject.value, body: body.value },
            done(t('admin.users.emailSent', { name: user.name })),
        );
    } else {
        router.delete(destroyUser(user.id).url, {
            data: { password: password.value },
            ...done(t('admin.users.deleted', { name: user.name })),
        });
    }
}
</script>

<template>
    <PageHeader
        :title="t('admin.users.title')"
        :lede="t('admin.users.lede')"
        :back="settingsIndex().url"
        :back-label="t('settings.title')"
        width="wide"
    />

    <PageContainer width="wide" class="pt-4 pb-16 sm:pt-0">
        <SearchField
            v-model="query"
            :label="t('admin.users.searchPlaceholder')"
            :placeholder="t('admin.users.searchPlaceholder')"
            class="sm:max-w-[28rem]"
        />
        <p class="mt-3 text-meta text-ink-muted">
            {{ t('admin.users.count', props.users.total) }}
        </p>

        <ul
            v-if="props.users.data.length"
            class="mt-3 overflow-hidden rounded-lg border border-line bg-surface"
        >
            <li
                v-for="user in props.users.data"
                :key="user.id"
                class="flex flex-wrap items-center gap-x-4 gap-y-3 border-t border-line px-4 py-3.5 first:border-t-0"
            >
                <div class="flex min-w-0 flex-1 basis-64 items-center gap-3.5">
                    <Avatar
                        :name="user.name"
                        :src="user.avatar_url"
                        :size="40"
                        decorative
                    />
                    <div class="min-w-0">
                        <p class="flex flex-wrap items-center gap-x-2 gap-y-1">
                            <span class="truncate font-semibold text-ink">{{
                                user.name
                            }}</span>
                            <Tag v-if="user.is_suspended" tone="host">{{
                                t('admin.users.suspended')
                            }}</Tag>
                        </p>
                        <p class="truncate text-meta text-ink-muted">
                            {{ '@' + user.username }} · {{ user.email }}
                        </p>
                    </div>
                </div>
                <div class="flex shrink-0 items-center gap-2 pl-[54px] sm:pl-0">
                    <SelectField
                        class="w-44"
                        :model-value="user.role"
                        :options="roles"
                        :label="t('admin.users.roleFor', { name: user.name })"
                        hide-label
                        :disabled="user.id === myId"
                        @update:model-value="setRole(user, $event)"
                    />
                    <Menu v-if="user.id !== myId">
                        <template #trigger>
                            <button
                                type="button"
                                :aria-label="
                                    t('admin.users.actionsFor', {
                                        name: user.name,
                                    })
                                "
                                class="grid size-11 place-items-center rounded-full text-ink-muted hover:bg-surface-2 hover:text-ink"
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
                            @select="router.post(messageUser(user.id).url)"
                        >
                            {{ t('admin.users.message') }}
                        </MenuItem>
                        <MenuItem :icon="Mail" @select="open('email', user)">
                            {{ t('admin.users.emailAction') }}
                        </MenuItem>
                        <MenuItem :icon="Eye" @select="impersonate(user)">
                            {{ t('admin.users.impersonate') }}
                        </MenuItem>
                        <MenuItem
                            v-if="
                                user.is_suspended &&
                                user.role !== 'administrator'
                            "
                            :icon="ShieldCheck"
                            @select="unsuspend(user)"
                        >
                            {{ t('admin.users.unsuspend') }}
                        </MenuItem>
                        <MenuItem
                            v-else-if="user.role !== 'administrator'"
                            :icon="ShieldBan"
                            @select="open('suspend', user)"
                        >
                            {{ t('admin.users.suspendAction') }}
                        </MenuItem>
                        <MenuSeparator />
                        <MenuItem
                            :icon="Trash"
                            danger
                            @select="open('delete', user)"
                        >
                            {{ t('admin.users.deleteAction') }}
                        </MenuItem>
                    </Menu>
                    <!-- Your own row has no actions: keep its select in line. -->
                    <span v-else class="size-11" aria-hidden="true" />
                </div>
            </li>
        </ul>
        <EmptyState v-else :title="t('admin.users.none')" />

        <Pagination :pagination="pagination" />
    </PageContainer>

    <AppDialog
        v-model:open="dialogOpen"
        :title="
            dialog === 'suspend'
                ? t('admin.users.suspendTitle', { name: target?.name ?? '' })
                : dialog === 'email'
                  ? t('admin.users.emailTitle', { name: target?.name ?? '' })
                  : t('admin.users.deleteAccountTitle', {
                        name: target?.name ?? '',
                    })
        "
        :description="
            dialog === 'delete'
                ? t('admin.users.deleteAccountWarning')
                : dialog === 'suspend'
                  ? t('admin.users.suspendHint')
                  : undefined
        "
    >
        <form
            id="admin-user-dialog"
            class="flex flex-col gap-4"
            @submit.prevent="submit"
        >
            <TextArea
                v-if="dialog === 'suspend'"
                v-model="reason"
                :label="t('admin.users.reason')"
                :placeholder="t('admin.users.suspendReasonPlaceholder')"
                :error="errors.reason"
                optional
            />
            <template v-else-if="dialog === 'email'">
                <TextField
                    v-model="subject"
                    :label="t('admin.users.emailSubjectPlaceholder')"
                    :error="errors.subject"
                    required
                />
                <TextArea
                    v-model="body"
                    :label="t('admin.users.emailBodyPlaceholder')"
                    :error="errors.body"
                    required
                />
            </template>
            <PasswordField
                v-else-if="dialog === 'delete'"
                name="password"
                :label="t('admin.users.yourPassword')"
                :error="errors.password"
                autocomplete="current-password"
                required
                @input="password = ($event.target as HTMLInputElement).value"
            />
        </form>
        <template #actions>
            <Button
                type="submit"
                form="admin-user-dialog"
                :variant="dialog === 'email' ? 'primary' : 'danger'"
                :disabled="sending"
            >
                {{
                    dialog === 'suspend'
                        ? t('admin.users.suspend')
                        : dialog === 'email'
                          ? t('admin.users.sendEmail')
                          : t('admin.users.deleteAccount')
                }}
            </Button>
            <Button variant="ghost" @click="dialog = null">
                {{ t('ui.cancel') }}
            </Button>
        </template>
    </AppDialog>
</template>
