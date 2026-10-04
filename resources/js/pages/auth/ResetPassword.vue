<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import FieldError from '@/components/FieldError.vue';
import PasswordField from '@/components/PasswordField.vue';
import Button from '@/components/ui/Button.vue';
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

    <h1 class="font-display text-title-1">
        {{ t('auth.resetPassword.title') }}
    </h1>
    <p class="mt-1 text-body text-ink-muted">
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
        class="mt-6 flex flex-col gap-4"
        #default="{ errors, processing }"
    >
        <input type="hidden" name="token" :value="props.token" />
        <FieldError :message="errors.token" />

        <PasswordField
            name="password"
            :label="t('auth.resetPassword.newPassword')"
            :hint="t('common.passphraseHint')"
            :error="errors.password"
            autocomplete="new-password"
            required
            autofocus
        />
        <PasswordField
            name="password_confirmation"
            :label="t('auth.resetPassword.confirmNewPassword')"
            :error="errors.password_confirmation"
            autocomplete="new-password"
            required
        />
        <Button
            type="submit"
            variant="primary"
            size="lg"
            block
            :disabled="processing"
        >
            {{
                processing
                    ? t('auth.resetPassword.submitting')
                    : t('auth.resetPassword.submit')
            }}
        </Button>
    </Form>
</template>
