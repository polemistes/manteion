<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { MailCheck } from '@lucide/vue';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import Button from '@/components/ui/Button.vue';
import TextField from '@/components/ui/TextField.vue';
import GuestLayout from '@/layouts/GuestLayout.vue';
import { login } from '@/routes';
import { email as sendResetLink } from '@/routes/password';

defineOptions({ layout: GuestLayout });

const { t } = useI18n();

const email = ref('');
</script>

<template>
    <Head :title="t('auth.forgotPassword.title')" />

    <h1 class="font-display text-title-1">
        {{ t('auth.forgotPassword.title') }}
    </h1>
    <p class="mt-1 text-body text-ink-muted">
        {{ t('auth.forgotPassword.description') }}
    </p>

    <Form
        v-bind="sendResetLink.form()"
        class="mt-6 flex flex-col gap-4"
        #default="{ errors, processing, wasSuccessful }"
    >
        <TextField
            v-model="email"
            name="email"
            type="email"
            :label="t('auth.forgotPassword.email')"
            :error="errors.email"
            autocomplete="email"
            required
            autofocus
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
                    ? t('auth.forgotPassword.submitting')
                    : t('auth.forgotPassword.submit')
            }}
        </Button>
        <p
            v-if="wasSuccessful"
            class="flex items-start gap-2.5 rounded-md bg-success-soft px-3.5 py-3 text-label text-success"
            role="status"
        >
            <MailCheck
                :size="18"
                :stroke-width="2"
                class="mt-px shrink-0"
                aria-hidden="true"
            />
            {{ t('auth.forgotPassword.checkEmail') }}
        </p>
    </Form>

    <p class="mt-6 text-center">
        <Link
            :href="login().url"
            class="text-label font-semibold text-ink underline underline-offset-2"
            >{{ t('auth.forgotPassword.backToLogin') }}</Link
        >
    </p>
</template>
