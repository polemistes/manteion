<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import Modal from '@/components/Modal.vue';
import { confirmAction } from '@/composables/useConfirm';
import {
    destroy as adminUsersDestroy,
    impersonate as adminUsersImpersonate,
    index as adminUsersIndex,
    suspend as adminUsersSuspend,
    unsuspend as adminUsersUnsuspend,
} from '@/routes/admin/users';
import adminUsersEmail from '@/routes/admin/users/email';
import adminUsersRole from '@/routes/admin/users/role';
import { message as messageUser } from '@/routes/moderation/users';

const { t } = useI18n();

interface AdminUser {
    id: number;
    name: string;
    username: string;
    email: string;
    role: 'member' | 'moderator' | 'administrator';
    is_suspended: boolean;
    created_at: string;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

const props = defineProps<{
    users: {
        data: AdminUser[];
        links: PaginationLink[];
    };
    q: string;
}>();

const query = ref(props.q);

function submitSearch(): void {
    router.get(
        adminUsersIndex().url,
        { q: query.value },
        { preserveState: true, replace: true },
    );
}

function updateRole(user: AdminUser, role: string): void {
    router.patch(
        adminUsersRole.update(user.id).url,
        { role },
        { preserveScroll: true },
    );
}

async function unsuspend(user: AdminUser): Promise<void> {
    const confirmed = await confirmAction({
        message: t('admin.users.confirmUnsuspend', { name: user.name }),
        confirmLabel: t('admin.users.unsuspend'),
    });

    if (confirmed) {
        router.delete(adminUsersUnsuspend(user.id).url, {
            preserveScroll: true,
        });
    }
}

async function impersonate(user: AdminUser): Promise<void> {
    const confirmed = await confirmAction({
        message: t('admin.users.confirmImpersonate', { name: user.name }),
        confirmLabel: t('admin.users.impersonate'),
    });

    if (confirmed) {
        router.post(adminUsersImpersonate(user.id).url);
    }
}

function messageUserAction(user: AdminUser): void {
    router.post(messageUser(user.id).url);
}

const suspendTarget = ref<AdminUser | null>(null);
const suspendReason = ref('');

function openSuspend(user: AdminUser): void {
    suspendTarget.value = user;
    suspendReason.value = '';
}

function submitSuspend(): void {
    if (!suspendTarget.value) {
        return;
    }

    router.post(
        adminUsersSuspend(suspendTarget.value.id).url,
        { reason: suspendReason.value || null },
        { preserveScroll: true, onSuccess: () => (suspendTarget.value = null) },
    );
}

const emailTarget = ref<AdminUser | null>(null);
const emailSubject = ref('');
const emailBody = ref('');

function openEmail(user: AdminUser): void {
    emailTarget.value = user;
    emailSubject.value = '';
    emailBody.value = '';
}

function submitEmail(): void {
    if (!emailTarget.value) {
        return;
    }

    router.post(
        adminUsersEmail.store(emailTarget.value.id).url,
        { subject: emailSubject.value, body: emailBody.value },
        { preserveScroll: true, onSuccess: () => (emailTarget.value = null) },
    );
}

const deleteTarget = ref<AdminUser | null>(null);
const deletePassword = ref('');

function openDelete(user: AdminUser): void {
    deleteTarget.value = user;
    deletePassword.value = '';
}

function submitDelete(): void {
    if (!deleteTarget.value) {
        return;
    }

    router.delete(adminUsersDestroy(deleteTarget.value.id).url, {
        data: { password: deletePassword.value },
        onSuccess: () => (deleteTarget.value = null),
    });
}
</script>

<template>
    <Head :title="t('admin.users.title')" />

    <h1 class="mb-6 text-xl font-bold">{{ t('admin.users.title') }}</h1>

    <form class="mb-6 flex gap-2" @submit.prevent="submitSearch">
        <input
            v-model="query"
            type="text"
            :placeholder="t('admin.users.searchPlaceholder')"
            class="flex-1 border border-edge bg-surface-raised px-3 py-2 font-sans text-sm focus:ring-1 focus:ring-accent focus:outline-none"
        />
        <button
            type="submit"
            class="border border-edge px-4 py-2 text-sm hover:bg-accent hover:text-on-accent"
        >
            {{ t('common.search') }}
        </button>
    </form>

    <div class="overflow-x-auto">
        <table class="w-full border-collapse text-left text-sm">
            <thead>
                <tr class="border-b-frame border-edge">
                    <th class="py-2 pr-4">{{ t('admin.users.name') }}</th>
                    <th class="py-2 pr-4">{{ t('admin.users.email') }}</th>
                    <th class="py-2 pr-4">{{ t('admin.users.role') }}</th>
                    <th class="py-2 pr-4">{{ t('admin.users.status') }}</th>
                    <th class="py-2">{{ t('admin.users.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="user in props.users.data"
                    :key="user.id"
                    class="border-b border-edge/20 align-top"
                >
                    <td class="py-2 pr-4">
                        {{ user.name }}<br />
                        <span class="text-xs text-ink/60"
                            >@{{ user.username }}</span
                        >
                    </td>
                    <td class="py-2 pr-4">{{ user.email }}</td>
                    <td class="py-2 pr-4">
                        <select
                            :value="user.role"
                            class="border border-edge bg-surface-raised px-2 py-1 font-sans text-xs"
                            @change="
                                updateRole(
                                    user,
                                    ($event.target as HTMLSelectElement).value,
                                )
                            "
                        >
                            <option value="member">
                                {{ t('admin.users.roleMember') }}
                            </option>
                            <option value="moderator">
                                {{ t('admin.users.roleModerator') }}
                            </option>
                            <option value="administrator">
                                {{ t('admin.users.roleAdministrator') }}
                            </option>
                        </select>
                    </td>
                    <td class="py-2 pr-4">
                        <span v-if="user.is_suspended" class="font-bold">{{
                            t('admin.users.suspended')
                        }}</span>
                        <span v-else>{{ t('admin.users.active') }}</span>
                    </td>
                    <td class="flex flex-wrap gap-2 py-2">
                        <button
                            type="button"
                            class="text-xs hover:underline"
                            @click="impersonate(user)"
                        >
                            {{ t('admin.users.impersonate') }}
                        </button>
                        <button
                            type="button"
                            class="text-xs hover:underline"
                            @click="messageUserAction(user)"
                        >
                            {{ t('admin.users.message') }}
                        </button>
                        <button
                            type="button"
                            class="text-xs hover:underline"
                            @click="openEmail(user)"
                        >
                            {{ t('admin.users.email') }}
                        </button>
                        <button
                            v-if="user.is_suspended"
                            type="button"
                            class="text-xs hover:underline"
                            @click="unsuspend(user)"
                        >
                            {{ t('admin.users.unsuspend') }}
                        </button>
                        <button
                            v-else
                            type="button"
                            class="text-xs hover:underline"
                            @click="openSuspend(user)"
                        >
                            {{ t('admin.users.suspend') }}
                        </button>
                        <button
                            type="button"
                            class="text-xs text-red-700 hover:underline"
                            @click="openDelete(user)"
                        >
                            {{ t('admin.users.deleteAccount') }}
                        </button>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <nav class="mt-6 flex flex-wrap gap-1">
        <template v-for="link in props.users.links" :key="link.label">
            <span
                v-if="!link.url"
                class="border border-edge px-2 py-1 text-xs opacity-40"
                v-html="link.label"
            />
            <Link
                v-else
                :href="link.url"
                preserve-scroll
                preserve-state
                class="border border-edge px-2 py-1 text-xs"
                :class="
                    link.active
                        ? 'bg-accent text-on-accent'
                        : 'hover:bg-accent hover:text-on-accent'
                "
            >
                <span v-html="link.label" />
            </Link>
        </template>
    </nav>

    <Modal :open="suspendTarget !== null" @update:open="suspendTarget = null">
        <p class="mb-2 text-sm font-bold">
            {{ t('admin.users.suspendTitle', { name: suspendTarget?.name }) }}
        </p>
        <textarea
            v-model="suspendReason"
            rows="3"
            :placeholder="t('admin.users.suspendReasonPlaceholder')"
            class="mb-4 w-full border border-edge bg-surface-raised px-3 py-2 font-sans text-sm focus:ring-1 focus:ring-accent focus:outline-none"
        ></textarea>
        <div class="flex justify-end gap-2">
            <button
                type="button"
                class="border border-edge px-3 py-2 text-sm hover:bg-accent hover:text-on-accent"
                @click="suspendTarget = null"
            >
                {{ t('common.cancel') }}
            </button>
            <button
                type="button"
                class="border border-edge bg-accent px-3 py-2 text-sm text-on-accent hover:bg-surface-raised hover:text-ink"
                @click="submitSuspend"
            >
                {{ t('admin.users.suspend') }}
            </button>
        </div>
    </Modal>

    <Modal :open="emailTarget !== null" @update:open="emailTarget = null">
        <p class="mb-2 text-sm font-bold">
            {{ t('admin.users.emailTitle', { name: emailTarget?.name }) }}
        </p>
        <input
            v-model="emailSubject"
            type="text"
            :placeholder="t('admin.users.emailSubjectPlaceholder')"
            class="mb-2 w-full border border-edge bg-surface-raised px-3 py-2 font-sans text-sm focus:ring-1 focus:ring-accent focus:outline-none"
        />
        <textarea
            v-model="emailBody"
            rows="5"
            :placeholder="t('admin.users.emailBodyPlaceholder')"
            class="mb-4 w-full border border-edge bg-surface-raised px-3 py-2 font-sans text-sm focus:ring-1 focus:ring-accent focus:outline-none"
        ></textarea>
        <div class="flex justify-end gap-2">
            <button
                type="button"
                class="border border-edge px-3 py-2 text-sm hover:bg-accent hover:text-on-accent"
                @click="emailTarget = null"
            >
                {{ t('common.cancel') }}
            </button>
            <button
                type="button"
                class="border border-edge bg-accent px-3 py-2 text-sm text-on-accent hover:bg-surface-raised hover:text-ink"
                @click="submitEmail"
            >
                {{ t('admin.users.sendEmail') }}
            </button>
        </div>
    </Modal>

    <Modal :open="deleteTarget !== null" @update:open="deleteTarget = null">
        <p class="mb-2 text-sm font-bold">
            {{
                t('admin.users.deleteAccountTitle', {
                    name: deleteTarget?.name,
                })
            }}
        </p>
        <p class="mb-4 text-sm">{{ t('admin.users.deleteAccountWarning') }}</p>
        <label class="mb-1 block text-sm font-bold">{{
            t('admin.users.yourPassword')
        }}</label>
        <input
            v-model="deletePassword"
            type="password"
            class="mb-4 w-full border border-edge bg-surface-raised px-3 py-2 font-sans text-sm focus:ring-1 focus:ring-accent focus:outline-none"
        />
        <div class="flex justify-end gap-2">
            <button
                type="button"
                class="border border-edge px-3 py-2 text-sm hover:bg-accent hover:text-on-accent"
                @click="deleteTarget = null"
            >
                {{ t('common.cancel') }}
            </button>
            <button
                type="button"
                class="border border-edge bg-accent px-3 py-2 text-sm text-on-accent hover:bg-surface-raised hover:text-ink"
                @click="submitDelete"
            >
                {{ t('admin.users.deleteAccount') }}
            </button>
        </div>
    </Modal>
</template>
