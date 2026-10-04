<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import PasswordField from '@/components/PasswordField.vue';
import Button from '@/components/ui/Button.vue';
import TextField from '@/components/ui/TextField.vue';
import GuestLayout from '@/layouts/GuestLayout.vue';
import { login } from '@/routes';
import { store } from '@/routes/register';

defineOptions({ layout: GuestLayout });

const { t } = useI18n();

// Text fields keep their own state (see .ai/rules/js.md).
const name = ref('');
const username = ref('');
const email = ref('');
</script>

<template>
    <Head :title="t('auth.register.title')" />

    <h1 class="font-display text-title-1">{{ t('auth.register.title') }}</h1>
    <p class="mt-1 text-body text-ink-muted">{{ t('auth.register.lede') }}</p>

    <Form
        v-bind="store.form()"
        class="mt-6 flex flex-col gap-4"
        #default="{ errors, processing }"
    >
        <TextField
            v-model="name"
            name="name"
            :label="t('auth.register.name')"
            :hint="t('auth.register.nameHint')"
            :error="errors.name"
            autocomplete="name"
            required
            autofocus
        />
        <TextField
            v-model="username"
            name="username"
            :label="t('auth.register.username')"
            :hint="t('auth.register.usernameHint')"
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
            :label="t('auth.register.email')"
            :error="errors.email"
            autocomplete="email"
            required
        />
        <PasswordField
            name="password"
            :label="t('auth.register.password')"
            :hint="t('common.passphraseHint')"
            :error="errors.password"
            autocomplete="new-password"
            required
        />
        <PasswordField
            name="password_confirmation"
            :label="t('auth.register.passwordConfirmation')"
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
                    ? t('auth.register.submitting')
                    : t('auth.register.submit')
            }}
        </Button>
    </Form>

    <p class="mt-6 text-center text-label text-ink-muted">
        {{ t('auth.register.haveAccount') }}
        <Link
            :href="login().url"
            class="font-semibold text-ink underline underline-offset-2"
            >{{ t('auth.register.logIn') }}</Link
        >
    </p>
</template>
