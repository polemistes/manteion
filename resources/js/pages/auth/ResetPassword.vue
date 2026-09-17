<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import FieldError from '@/components/FieldError.vue';
import PasswordField from '@/components/PasswordField.vue';
import GuestLayout from '@/layouts/GuestLayout.vue';
import { update } from '@/routes/password';

defineOptions({ layout: GuestLayout });

const props = defineProps<{
    username: string | null;
    token: string;
}>();

const { t } = useI18n();
</script>

<template>
    <Head :title="t('auth.resetPassword.title')" />

    <h1 class="mb-6 text-lg font-bold">{{ t('auth.resetPassword.title') }}</h1>

    <p class="mb-6 text-sm">
        {{
            props.username
                ? t('auth.resetPassword.forAccount', {
                      username: `@${props.username}`,
                  })
                : t('auth.resetPassword.linkMayBeInvalid')
        }}
    </p>

    <Form
        v-bind="update.form()"
        #default="{ errors, processing }"
        class="flex flex-col gap-4"
    >
        <input type="hidden" name="token" :value="props.token" />
        <FieldError :message="errors.token" />

        <div>
            <label for="password" class="mb-1 block text-sm font-bold">{{
                t('auth.resetPassword.newPassword')
            }}</label>
            <PasswordField
                id="password"
                name="password"
                required
                autofocus
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
                >{{ t('auth.resetPassword.confirmNewPassword') }}</label
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
            class="w-full border border-edge bg-accent px-3 py-2 text-sm font-bold text-on-accent hover:bg-surface-raised hover:text-ink disabled:cursor-not-allowed disabled:opacity-50"
        >
            {{
                processing
                    ? t('auth.resetPassword.submitting')
                    : t('auth.resetPassword.submit')
            }}
        </button>
    </Form>
</template>
