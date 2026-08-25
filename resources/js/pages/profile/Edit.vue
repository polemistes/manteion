<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import Avatar from '@/components/Avatar.vue';
import FieldError from '@/components/FieldError.vue';
import PasswordField from '@/components/PasswordField.vue';
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
        avatar_url: string | null;
        bio: string | null;
    };
}>();

const avatarPreview = ref<string | null>(null);
const removeAvatar = ref(false);

function onAvatarChange(event: Event): void {
    const file = (event.target as HTMLInputElement).files?.[0];

    if (file) {
        avatarPreview.value = URL.createObjectURL(file);
        removeAvatar.value = false;
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
                <div class="flex items-center gap-4">
                    <Avatar
                        :url="
                            removeAvatar
                                ? null
                                : (avatarPreview ?? props.user.avatar_url)
                        "
                        :name="props.user.name"
                        size="md"
                    />
                    <div class="flex flex-col gap-2">
                        <input
                            type="file"
                            name="avatar"
                            accept="image/*"
                            class="text-sm"
                            @change="onAvatarChange"
                        />
                        <label
                            v-if="props.user.avatar_url"
                            class="flex items-center gap-2 text-xs"
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
                    </div>
                </div>
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

    <section class="max-w-md">
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
</template>
