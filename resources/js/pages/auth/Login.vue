<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import PasswordField from '@/components/PasswordField.vue';
import Button from '@/components/ui/Button.vue';
import Checkbox from '@/components/ui/Checkbox.vue';
import TextField from '@/components/ui/TextField.vue';
import GuestLayout from '@/layouts/GuestLayout.vue';
import { register } from '@/routes';
import { store } from '@/routes/login';
import { request as requestPasswordReset } from '@/routes/password';

defineOptions({ layout: GuestLayout });

const { t } = useI18n();

const username = ref('');
const remember = ref(true);
</script>

<template>
    <Head :title="t('auth.login.title')" />

    <h1 class="font-display text-title-1">{{ t('auth.login.title') }}</h1>
    <p class="mt-1 text-body text-ink-muted">{{ t('auth.login.lede') }}</p>

    <Form
        v-bind="store.form()"
        class="mt-6 flex flex-col gap-4"
        #default="{ errors, processing }"
    >
        <TextField
            v-model="username"
            name="username"
            :label="t('auth.login.username')"
            :error="errors.username"
            autocomplete="username"
            autocapitalize="none"
            spellcheck="false"
            required
            autofocus
        />
        <PasswordField
            name="password"
            :label="t('auth.login.password')"
            :error="errors.password"
            autocomplete="current-password"
            required
        />
        <div
            class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2"
        >
            <label
                class="flex min-h-11 cursor-pointer items-center gap-2.5 text-label text-ink"
            >
                <Checkbox v-model="remember" name="remember" value="1" />
                {{ t('auth.login.remember') }}
            </label>
            <Link
                :href="requestPasswordReset().url"
                class="text-label font-semibold text-ink underline underline-offset-2"
            >
                {{ t('auth.login.forgotPassword') }}
            </Link>
        </div>
        <Button
            type="submit"
            variant="primary"
            size="lg"
            block
            :disabled="processing"
        >
            {{
                processing ? t('auth.login.submitting') : t('auth.login.submit')
            }}
        </Button>
    </Form>

    <p class="mt-6 text-center text-label text-ink-muted">
        {{ t('auth.login.noAccount') }}
        <Link
            :href="register().url"
            class="font-semibold text-ink underline underline-offset-2"
            >{{ t('auth.login.register') }}</Link
        >
    </p>
</template>
