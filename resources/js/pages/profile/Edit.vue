<script setup lang="ts">
import { Form, Head, Link, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import FieldError from '@/components/FieldError.vue';
import ImageCropper from '@/components/ImageCropper.vue';
import Modal from '@/components/Modal.vue';
import PasswordField from '@/components/PasswordField.vue';
import { usePushNotifications } from '@/composables/usePushNotifications';
import { logout } from '@/routes';
import { index as adminUsersIndex } from '@/routes/admin/users';
import { index as moderationReportsIndex } from '@/routes/moderation/reports';
import { destroy as destroyProfile } from '@/routes/profile';
import { update as updatePassword } from '@/routes/user-password';
import { update as updateProfileInformation } from '@/routes/user-profile-information';
import type { ThemeMode, ThemeStyle } from '@/theme';
import { applyTheme } from '@/theme';

const { t } = useI18n();

const props = defineProps<{
    user: {
        id: number;
        name: string;
        username: string;
        email: string;
        role: 'member' | 'moderator' | 'administrator';
        allow_public_follow: boolean;
        show_friends_list: boolean;
        appear_in_friends_lists: boolean;
        searchable: boolean;
        push_notifications_enabled: boolean;
        notify_event_invites: boolean;
        notify_messages: boolean;
        notify_friend_requests: boolean;
        notify_follow_requests: boolean;
        avatar_url: string | null;
        bio: string | null;
        theme_style: ThemeStyle;
        theme_mode: ThemeMode;
    };
}>();

const themeStyle = ref<ThemeStyle>(props.user.theme_style);
const themeMode = ref<ThemeMode>(props.user.theme_mode);

const themeStyles: ThemeStyle[] = ['classic', 'soft'];
const themeModes: ThemeMode[] = ['light', 'dark', 'system'];

// Preview the choice at once; saving the form makes it stick.
function previewTheme(): void {
    applyTheme({ style: themeStyle.value, mode: themeMode.value });
}

const removeAvatar = ref(false);
const deleteAccountDialogOpen = ref(false);

const isStaff = computed(() =>
    ['moderator', 'administrator'].includes(props.user.role),
);
const isAdministrator = computed(() => props.user.role === 'administrator');

const page = usePage();
const { hasActiveSubscription, subscribe, unsubscribe } =
    usePushNotifications();
const pushEnabled = ref(false);

onMounted(async () => {
    pushEnabled.value =
        props.user.push_notifications_enabled &&
        (await hasActiveSubscription());
});

async function onPushToggle(): Promise<void> {
    if (pushEnabled.value) {
        const vapidPublicKey = page.props.vapidPublicKey;
        const granted = vapidPublicKey
            ? await subscribe(vapidPublicKey)
            : false;

        if (!granted) {
            pushEnabled.value = false;
        }
    } else {
        await unsubscribe();
    }
}
</script>

<template>
    <Head :title="t('profile.title')" />

    <h1 class="mb-6 text-xl font-bold">{{ t('profile.title') }}</h1>

    <section class="mb-10 max-w-md">
        <h2 class="mb-4 text-sm font-bold uppercase">
            {{ t('profile.accountHeading') }}
        </h2>

        <Form
            v-bind="updateProfileInformation.form()"
            error-bag="updateProfileInformation"
            #default="{ errors, processing, wasSuccessful }"
            class="flex flex-col gap-4"
        >
            <div>
                <span class="mb-1 block text-sm font-bold">{{
                    t('profile.photo')
                }}</span>
                <ImageCropper
                    name="avatar"
                    :aspect-ratio="1"
                    :output-width="512"
                    :output-height="512"
                    :existing-url="removeAvatar ? null : props.user.avatar_url"
                />
                <label
                    v-if="props.user.avatar_url"
                    class="mt-2 flex items-center gap-2 text-xs"
                >
                    <input
                        v-model="removeAvatar"
                        type="checkbox"
                        name="remove_avatar"
                        value="1"
                        class="border border-edge"
                    />
                    {{ t('profile.removePhoto') }}
                </label>
                <FieldError :message="errors.avatar" />
            </div>

            <div>
                <label for="name" class="mb-1 block text-sm font-bold">{{
                    t('profile.name')
                }}</label>
                <input
                    id="name"
                    type="text"
                    name="name"
                    required
                    :value="props.user.name"
                    class="w-full border border-edge bg-surface-raised px-3 py-2 font-sans text-sm focus:ring-1 focus:ring-accent focus:outline-none"
                />
                <FieldError :message="errors.name" />
            </div>

            <div>
                <label for="username" class="mb-1 block text-sm font-bold">{{
                    t('profile.username')
                }}</label>
                <input
                    id="username"
                    type="text"
                    name="username"
                    required
                    :value="props.user.username"
                    class="w-full border border-edge bg-surface-raised px-3 py-2 font-sans text-sm focus:ring-1 focus:ring-accent focus:outline-none"
                />
                <FieldError :message="errors.username" />
            </div>

            <div>
                <label for="email" class="mb-1 block text-sm font-bold">{{
                    t('profile.email')
                }}</label>
                <input
                    id="email"
                    type="email"
                    name="email"
                    required
                    :value="props.user.email"
                    class="w-full border border-edge bg-surface-raised px-3 py-2 font-sans text-sm focus:ring-1 focus:ring-accent focus:outline-none"
                />
                <FieldError :message="errors.email" />
            </div>

            <div>
                <label for="bio" class="mb-1 block text-sm font-bold">{{
                    t('profile.bio')
                }}</label>
                <textarea
                    id="bio"
                    name="bio"
                    rows="3"
                    maxlength="1000"
                    :placeholder="t('profile.bioPlaceholder')"
                    :value="props.user.bio"
                    class="w-full border border-edge bg-surface-raised px-3 py-2 font-sans text-sm focus:ring-1 focus:ring-accent focus:outline-none"
                ></textarea>
                <FieldError :message="errors.bio" />
            </div>

            <div>
                <span class="mb-1 block text-sm font-bold">{{
                    t('profile.privacyHeading')
                }}</span>

                <label class="flex items-start gap-2 text-sm">
                    <input type="hidden" name="allow_public_follow" value="0" />
                    <input
                        type="checkbox"
                        name="allow_public_follow"
                        value="1"
                        :checked="props.user.allow_public_follow"
                        class="mt-0.5 border border-edge"
                    />
                    <span>{{ t('profile.allowPublicFollow') }}</span>
                </label>
                <FieldError :message="errors.allow_public_follow" />

                <label class="mt-2 flex items-start gap-2 text-sm">
                    <input type="hidden" name="show_friends_list" value="0" />
                    <input
                        type="checkbox"
                        name="show_friends_list"
                        value="1"
                        :checked="props.user.show_friends_list"
                        class="mt-0.5 border border-edge"
                    />
                    <span>{{ t('profile.showFriendsList') }}</span>
                </label>
                <FieldError :message="errors.show_friends_list" />

                <label class="mt-2 flex items-start gap-2 text-sm">
                    <input
                        type="hidden"
                        name="appear_in_friends_lists"
                        value="0"
                    />
                    <input
                        type="checkbox"
                        name="appear_in_friends_lists"
                        value="1"
                        :checked="props.user.appear_in_friends_lists"
                        class="mt-0.5 border border-edge"
                    />
                    <span>{{ t('profile.appearInFriendsLists') }}</span>
                </label>
                <FieldError :message="errors.appear_in_friends_lists" />

                <label class="mt-2 flex items-start gap-2 text-sm">
                    <input type="hidden" name="searchable" value="0" />
                    <input
                        type="checkbox"
                        name="searchable"
                        value="1"
                        :checked="props.user.searchable"
                        class="mt-0.5 border border-edge"
                    />
                    <span>{{ t('profile.searchable') }}</span>
                </label>
                <FieldError :message="errors.searchable" />
            </div>

            <div>
                <span class="mb-1 block text-sm font-bold">{{
                    t('profile.appearanceHeading')
                }}</span>

                <div class="flex flex-wrap gap-4">
                    <label class="flex flex-col gap-1 text-sm">
                        <span class="text-xs font-bold">{{
                            t('profile.themeStyle')
                        }}</span>
                        <select
                            v-model="themeStyle"
                            name="theme_style"
                            class="border border-edge bg-surface-raised px-2 py-1 font-sans text-sm"
                            @change="previewTheme"
                        >
                            <option
                                v-for="style in themeStyles"
                                :key="style"
                                :value="style"
                            >
                                {{ t(`profile.themeStyles.${style}`) }}
                            </option>
                        </select>
                    </label>

                    <label class="flex flex-col gap-1 text-sm">
                        <span class="text-xs font-bold">{{
                            t('profile.themeMode')
                        }}</span>
                        <select
                            v-model="themeMode"
                            name="theme_mode"
                            class="border border-edge bg-surface-raised px-2 py-1 font-sans text-sm"
                            @change="previewTheme"
                        >
                            <option
                                v-for="mode in themeModes"
                                :key="mode"
                                :value="mode"
                            >
                                {{ t(`profile.themeModes.${mode}`) }}
                            </option>
                        </select>
                    </label>
                </div>
                <FieldError :message="errors.theme_style" />
                <FieldError :message="errors.theme_mode" />
            </div>

            <div>
                <span class="mb-1 block text-sm font-bold">{{
                    t('profile.notificationsHeading')
                }}</span>

                <label class="flex items-start gap-2 text-sm">
                    <input
                        v-model="pushEnabled"
                        type="checkbox"
                        name="push_notifications_enabled"
                        value="1"
                        class="mt-0.5 border border-edge"
                        @change="onPushToggle"
                    />
                    <span>{{ t('profile.pushNotificationsEnabled') }}</span>
                </label>
                <FieldError :message="errors.push_notifications_enabled" />

                <label
                    class="mt-2 flex items-start gap-2 text-sm"
                    :class="{ 'opacity-50': !pushEnabled }"
                >
                    <input
                        type="hidden"
                        name="notify_event_invites"
                        value="0"
                    />
                    <input
                        type="checkbox"
                        name="notify_event_invites"
                        value="1"
                        :checked="props.user.notify_event_invites"
                        :disabled="!pushEnabled"
                        class="mt-0.5 border border-edge"
                    />
                    <span>{{ t('profile.notifyEventInvites') }}</span>
                </label>
                <FieldError :message="errors.notify_event_invites" />

                <label
                    class="mt-2 flex items-start gap-2 text-sm"
                    :class="{ 'opacity-50': !pushEnabled }"
                >
                    <input type="hidden" name="notify_messages" value="0" />
                    <input
                        type="checkbox"
                        name="notify_messages"
                        value="1"
                        :checked="props.user.notify_messages"
                        :disabled="!pushEnabled"
                        class="mt-0.5 border border-edge"
                    />
                    <span>{{ t('profile.notifyMessages') }}</span>
                </label>
                <FieldError :message="errors.notify_messages" />

                <label
                    class="mt-2 flex items-start gap-2 text-sm"
                    :class="{ 'opacity-50': !pushEnabled }"
                >
                    <input
                        type="hidden"
                        name="notify_friend_requests"
                        value="0"
                    />
                    <input
                        type="checkbox"
                        name="notify_friend_requests"
                        value="1"
                        :checked="props.user.notify_friend_requests"
                        :disabled="!pushEnabled"
                        class="mt-0.5 border border-edge"
                    />
                    <span>{{ t('profile.notifyFriendRequests') }}</span>
                </label>
                <FieldError :message="errors.notify_friend_requests" />

                <label
                    class="mt-2 flex items-start gap-2 text-sm"
                    :class="{ 'opacity-50': !pushEnabled }"
                >
                    <input
                        type="hidden"
                        name="notify_follow_requests"
                        value="0"
                    />
                    <input
                        type="checkbox"
                        name="notify_follow_requests"
                        value="1"
                        :checked="props.user.notify_follow_requests"
                        :disabled="!pushEnabled"
                        class="mt-0.5 border border-edge"
                    />
                    <span>{{ t('profile.notifyFollowRequests') }}</span>
                </label>
                <FieldError :message="errors.notify_follow_requests" />
            </div>

            <button
                type="submit"
                :disabled="processing"
                class="w-fit border border-edge bg-accent px-4 py-2 text-sm font-bold text-on-accent hover:bg-surface-raised hover:text-ink disabled:cursor-not-allowed disabled:opacity-50"
            >
                {{ processing ? t('profile.saving') : t('profile.save') }}
            </button>

            <p v-if="wasSuccessful" class="text-sm font-bold">
                {{ t('profile.saved') }}
            </p>
        </Form>
    </section>

    <section v-if="isStaff" class="mb-10 max-w-md">
        <h2 class="mb-4 text-sm font-bold uppercase">
            {{ t('profile.staffHeading') }}
        </h2>

        <div class="flex flex-wrap gap-2">
            <Link
                v-if="isAdministrator"
                :href="adminUsersIndex().url"
                class="w-fit border border-edge px-4 py-2 text-sm font-bold hover:bg-accent hover:text-on-accent"
            >
                {{ t('profile.adminPanel') }}
            </Link>
            <Link
                :href="moderationReportsIndex().url"
                class="w-fit border border-edge px-4 py-2 text-sm font-bold hover:bg-accent hover:text-on-accent"
            >
                {{ t('profile.moderationQueue') }}
            </Link>
        </div>
    </section>

    <section class="mb-10 max-w-md">
        <h2 class="mb-4 text-sm font-bold uppercase">
            {{ t('profile.passwordHeading') }}
        </h2>

        <Form
            v-bind="updatePassword.form()"
            error-bag="updatePassword"
            reset-on-success
            #default="{ errors, processing, wasSuccessful }"
            class="flex flex-col gap-4"
        >
            <div>
                <label
                    for="current_password"
                    class="mb-1 block text-sm font-bold"
                    >{{ t('profile.currentPassword') }}</label
                >
                <PasswordField
                    id="current_password"
                    name="current_password"
                    required
                    autocomplete="current-password"
                />
                <FieldError :message="errors.current_password" />
            </div>

            <div>
                <label for="password" class="mb-1 block text-sm font-bold">{{
                    t('profile.newPassword')
                }}</label>
                <PasswordField
                    id="password"
                    name="password"
                    required
                    autocomplete="new-password"
                />
                <p class="mt-1 text-xs text-ink/70">
                    {{ t('common.passphraseHint') }}
                </p>
                <FieldError :message="errors.password" />
            </div>

            <div>
                <label
                    for="password_confirmation"
                    class="mb-1 block text-sm font-bold"
                    >{{ t('profile.confirmNewPassword') }}</label
                >
                <PasswordField
                    id="password_confirmation"
                    name="password_confirmation"
                    required
                    autocomplete="new-password"
                />
                <FieldError :message="errors.password_confirmation" />
            </div>

            <button
                type="submit"
                :disabled="processing"
                class="w-fit border border-edge bg-accent px-4 py-2 text-sm font-bold text-on-accent hover:bg-surface-raised hover:text-ink disabled:cursor-not-allowed disabled:opacity-50"
            >
                {{
                    processing
                        ? t('profile.updating')
                        : t('profile.updatePassword')
                }}
            </button>

            <p v-if="wasSuccessful" class="text-sm font-bold">
                {{ t('profile.passwordUpdated') }}
            </p>
        </Form>
    </section>

    <section class="flex flex-wrap gap-2">
        <Link
            :href="logout().url"
            method="post"
            as="button"
            class="w-fit border border-edge px-4 py-2 text-sm font-bold hover:bg-accent hover:text-on-accent"
        >
            {{ t('nav.logOut') }}
        </Link>
        <button
            type="button"
            class="w-fit border border-edge px-4 py-2 text-sm font-bold hover:bg-accent hover:text-on-accent"
            @click="deleteAccountDialogOpen = true"
        >
            {{ t('profile.deleteAccount') }}
        </button>
    </section>

    <Modal v-model:open="deleteAccountDialogOpen">
        <h2 class="mb-2 text-sm font-bold uppercase">
            {{ t('profile.deleteAccount') }}
        </h2>
        <p class="mb-4 text-sm">{{ t('profile.confirmDeleteAccount') }}</p>

        <Form
            v-bind="destroyProfile.form()"
            #default="{ errors, processing }"
            class="flex flex-col gap-4"
        >
            <div>
                <label
                    for="delete_account_password"
                    class="mb-1 block text-sm font-bold"
                    >{{ t('profile.currentPassword') }}</label
                >
                <PasswordField
                    id="delete_account_password"
                    name="password"
                    required
                    autocomplete="current-password"
                />
                <FieldError :message="errors.password" />
            </div>

            <div class="flex justify-end gap-2">
                <button
                    type="button"
                    class="border border-edge px-3 py-2 text-sm hover:bg-accent hover:text-on-accent"
                    @click="deleteAccountDialogOpen = false"
                >
                    {{ t('common.cancel') }}
                </button>
                <button
                    type="submit"
                    :disabled="processing"
                    class="border border-edge bg-accent px-3 py-2 text-sm text-on-accent hover:bg-surface-raised hover:text-ink disabled:cursor-not-allowed disabled:opacity-50"
                >
                    {{ t('profile.deleteAccount') }}
                </button>
            </div>
        </Form>
    </Modal>
</template>
