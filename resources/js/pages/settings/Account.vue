<script setup lang="ts">
import { Form, router } from '@inertiajs/vue3';
import { CircleCheck, KeyRound } from '@lucide/vue';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import FieldError from '@/components/FieldError.vue';
import ImageCropper from '@/components/ImageCropper.vue';
import PasswordField from '@/components/PasswordField.vue';
import SettingsGroup from '@/components/settings/SettingsGroup.vue';
import SettingsLayout from '@/components/settings/SettingsLayout.vue';
import AppDialog from '@/components/ui/AppDialog.vue';
import Button from '@/components/ui/Button.vue';
import TextArea from '@/components/ui/TextArea.vue';
import TextField from '@/components/ui/TextField.vue';
import { toast } from '@/lib/toast';
import { destroy as destroyAccount } from '@/routes/profile';
import { update as updatePassword } from '@/routes/user-password';
import { update as updateProfile } from '@/routes/user-profile-information';

/**
 * Who you are on Manteion (photo, name, username, email, a few words), the
 * password, and leaving for good.
 */
const props = defineProps<{
    user: {
        name: string;
        username: string;
        email: string;
        bio: string | null;
        avatar_url: string | null;
    };
}>();

const { t } = useI18n();

// Text fields keep their own state (see .ai/rules/js.md).
const name = ref(props.user.name);
const username = ref(props.user.username);
const email = ref(props.user.email);
const bio = ref(props.user.bio ?? '');
const removeAvatar = ref(false);

function profileSaved(): void {
    removeAvatar.value = false;
    toast({ title: t('settings.account.saved'), icon: CircleCheck });
}

function passwordSaved(): void {
    toast({ title: t('settings.account.passwordSaved'), icon: KeyRound });
}

// Deleting the account asks for the password once more.
const deleteOpen = ref(false);
const deletePassword = ref('');
const deleteError = ref<string | undefined>(undefined);
const deleting = ref(false);

function deleteAccount(): void {
    deleting.value = true;
    router.delete(destroyAccount().url, {
        data: { password: deletePassword.value },
        preserveScroll: true,
        onError: (errors) => {
            deleteError.value = errors.password;
        },
        onFinish: () => {
            deleting.value = false;
        },
    });
}
</script>

<template>
    <SettingsLayout section="account" :title="t('settings.sections.account')">
        <Form
            v-bind="updateProfile.form()"
            error-bag="updateProfileInformation"
            :options="{ preserveScroll: true }"
            class="flex flex-col gap-5"
            @success="profileSaved"
            #default="{ errors, processing }"
        >
            <section class="flex flex-col gap-2">
                <h3 class="text-label font-semibold text-ink">
                    {{ t('settings.account.photo') }}
                </h3>
                <ImageCropper
                    name="avatar"
                    round
                    :aspect-ratio="1"
                    :output-width="512"
                    :output-height="512"
                    :existing-url="removeAvatar ? null : props.user.avatar_url"
                    :removable="Boolean(props.user.avatar_url) && !removeAvatar"
                    @remove="removeAvatar = true"
                />
                <input
                    v-if="removeAvatar"
                    type="hidden"
                    name="remove_avatar"
                    value="1"
                />
                <FieldError :message="errors.avatar" />
            </section>

            <TextField
                v-model="name"
                name="name"
                :label="t('settings.account.name')"
                :error="errors.name"
                autocomplete="name"
                required
                maxlength="255"
            />
            <TextField
                v-model="username"
                name="username"
                :label="t('settings.account.username')"
                :hint="t('settings.account.usernameHint')"
                :error="errors.username"
                autocomplete="username"
                autocapitalize="none"
                spellcheck="false"
                required
            />
            <TextField
                v-model="email"
                name="email"
                type="email"
                :label="t('settings.account.email')"
                :error="errors.email"
                autocomplete="email"
                required
            />
            <TextArea
                v-model="bio"
                name="bio"
                :label="t('settings.account.bio')"
                :placeholder="t('settings.account.bioPlaceholder')"
                :error="errors.bio"
                optional
                maxlength="1000"
            />

            <div>
                <Button type="submit" variant="primary" :disabled="processing">
                    {{
                        processing
                            ? t('settings.account.saving')
                            : t('settings.account.save')
                    }}
                </Button>
            </div>
        </Form>

        <SettingsGroup :label="t('settings.account.password')" :flush="false">
            <Form
                v-bind="updatePassword.form()"
                error-bag="updatePassword"
                :options="{ preserveScroll: true }"
                reset-on-success
                class="flex flex-col gap-4"
                @success="passwordSaved"
                #default="{ errors, processing }"
            >
                <div class="flex flex-col gap-1.5">
                    <label
                        for="current_password"
                        class="text-label font-semibold text-ink"
                        >{{ t('settings.account.currentPassword') }}</label
                    >
                    <PasswordField
                        id="current_password"
                        name="current_password"
                        autocomplete="current-password"
                        :invalid="Boolean(errors.current_password)"
                    />
                    <FieldError :message="errors.current_password" />
                </div>
                <div class="flex flex-col gap-1.5">
                    <label
                        for="password"
                        class="text-label font-semibold text-ink"
                        >{{ t('settings.account.newPassword') }}</label
                    >
                    <PasswordField
                        id="password"
                        name="password"
                        autocomplete="new-password"
                        :invalid="Boolean(errors.password)"
                    />
                    <FieldError :message="errors.password" />
                </div>
                <div class="flex flex-col gap-1.5">
                    <label
                        for="password_confirmation"
                        class="text-label font-semibold text-ink"
                        >{{ t('settings.account.confirmPassword') }}</label
                    >
                    <PasswordField
                        id="password_confirmation"
                        name="password_confirmation"
                        autocomplete="new-password"
                    />
                </div>
                <div>
                    <Button type="submit" :disabled="processing">
                        {{ t('settings.account.changePassword') }}
                    </Button>
                </div>
            </Form>
        </SettingsGroup>

        <SettingsGroup :label="t('settings.account.leave')" :flush="false">
            <p class="text-body text-ink">
                {{ t('settings.account.deleteBody') }}
            </p>
            <Button
                variant="danger"
                class="mt-4"
                @click="
                    deleteError = undefined;
                    deletePassword = '';
                    deleteOpen = true;
                "
            >
                {{ t('settings.account.delete') }}
            </Button>
        </SettingsGroup>

        <AppDialog
            v-model:open="deleteOpen"
            :title="t('settings.account.deleteTitle')"
            :description="t('settings.account.deleteConfirm')"
        >
            <form
                id="delete-account"
                class="flex flex-col gap-1.5"
                @submit.prevent="deleteAccount"
            >
                <label
                    for="delete_password"
                    class="text-label font-semibold text-ink"
                    >{{ t('settings.account.currentPassword') }}</label
                >
                <PasswordField
                    id="delete_password"
                    name="password"
                    @input="
                        deletePassword = ($event.target as HTMLInputElement)
                            .value
                    "
                    autocomplete="current-password"
                    :invalid="Boolean(deleteError)"
                />
                <FieldError :message="deleteError" />
            </form>
            <template #actions>
                <Button
                    type="submit"
                    form="delete-account"
                    variant="danger"
                    :disabled="deleting || !deletePassword"
                >
                    {{ t('settings.account.deleteForGood') }}
                </Button>
                <Button variant="ghost" @click="deleteOpen = false">
                    {{ t('ui.cancel') }}
                </Button>
            </template>
        </AppDialog>
    </SettingsLayout>
</template>
