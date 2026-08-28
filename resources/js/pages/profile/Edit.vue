<script setup lang="ts">
import { Form, Head, Link, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import FieldError from '@/components/FieldError.vue';
import ImageCropper from '@/components/ImageCropper.vue';
import Modal from '@/components/Modal.vue';
import PasswordField from '@/components/PasswordField.vue';
import { usePushNotifications } from '@/composables/usePushNotifications';
import { logout } from '@/routes';
import { destroy as destroyProfile } from '@/routes/profile';
import { update as updatePassword } from '@/routes/user-password';
import { update as updateProfileInformation } from '@/routes/user-profile-information';

const { t } = useI18n();

const props = defineProps<{
    user: {
        id: number;
        name: string;
        username: string;
        email: string;
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
    };
}>();

const removeAvatar = ref(false);
const deleteAccountDialogOpen = ref(false);

const page = usePage();
const { subscribe, unsubscribe } = usePushNotifications();
const pushEnabled = ref(props.user.push_notifications_enabled);

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
                        class="border border-black"
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
                    class="w-full border border-black bg-white px-3 py-2 font-sans text-sm focus:ring-1 focus:ring-black focus:outline-none"
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
                    class="w-full border border-black bg-white px-3 py-2 font-sans text-sm focus:ring-1 focus:ring-black focus:outline-none"
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
                    class="w-full border border-black bg-white px-3 py-2 font-sans text-sm focus:ring-1 focus:ring-black focus:outline-none"
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
                    class="w-full border border-black bg-white px-3 py-2 font-sans text-sm focus:ring-1 focus:ring-black focus:outline-none"
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
                        class="mt-0.5 border border-black"
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
                        class="mt-0.5 border border-black"
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
                        class="mt-0.5 border border-black"
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
                        class="mt-0.5 border border-black"
                    />
                    <span>{{ t('profile.searchable') }}</span>
                </label>
                <FieldError :message="errors.searchable" />
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
                        class="mt-0.5 border border-black"
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
                        class="mt-0.5 border border-black"
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
                        class="mt-0.5 border border-black"
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
                        class="mt-0.5 border border-black"
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
                        class="mt-0.5 border border-black"
                    />
                    <span>{{ t('profile.notifyFollowRequests') }}</span>
                </label>
                <FieldError :message="errors.notify_follow_requests" />
            </div>

            <button
                type="submit"
                :disabled="processing"
                class="w-fit border border-black bg-black px-4 py-2 text-sm font-bold text-white hover:bg-white hover:text-black disabled:cursor-not-allowed disabled:opacity-50"
            >
                {{ processing ? t('profile.saving') : t('profile.save') }}
            </button>

            <p v-if="wasSuccessful" class="text-sm font-bold">
                {{ t('profile.saved') }}
            </p>
        </Form>
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
                <p class="mt-1 text-xs text-black/70">
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
                class="w-fit border border-black bg-black px-4 py-2 text-sm font-bold text-white hover:bg-white hover:text-black disabled:cursor-not-allowed disabled:opacity-50"
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
            class="w-fit border border-black px-4 py-2 text-sm font-bold hover:bg-black hover:text-white"
        >
            {{ t('nav.logOut') }}
        </Link>
        <button
            type="button"
            class="w-fit border border-black px-4 py-2 text-sm font-bold hover:bg-black hover:text-white"
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
                    class="border border-black px-3 py-2 text-sm hover:bg-black hover:text-white"
                    @click="deleteAccountDialogOpen = false"
                >
                    {{ t('common.cancel') }}
                </button>
                <button
                    type="submit"
                    :disabled="processing"
                    class="border border-black bg-black px-3 py-2 text-sm text-white hover:bg-white hover:text-black disabled:cursor-not-allowed disabled:opacity-50"
                >
                    {{ t('profile.deleteAccount') }}
                </button>
            </div>
        </Form>
    </Modal>
</template>
