<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import FieldError from '@/components/FieldError.vue';
import GuestLayout from '@/layouts/GuestLayout.vue';
import { email } from '@/routes/password';

defineOptions({ layout: GuestLayout });

const { t } = useI18n();
</script>

<template>
    <Head :title="t('auth.forgotPassword.title')" />

    <h1 class="text-lg mb-2 font-bold">{{ t('auth.forgotPassword.title') }}</h1>
    <p class="text-sm mb-6 text-ink/70">
        {{ t('auth.forgotPassword.description') }}
    </p>

    <Form
        v-bind="email.form()"
        #default="{ errors, processing, wasSuccessful }"
        class="flex flex-col gap-4"
    >
        <div>
            <label for="email" class="text-sm mb-1 block font-bold">{{
                t('auth.forgotPassword.email')
            }}</label>
            <input
                id="email"
                type="email"
                name="email"
                required
                autofocus
                autocomplete="username"
                class="border-edge bg-surface-raised text-sm w-full border px-3 py-2 font-sans focus:ring-1 focus:ring-accent focus:outline-none"
            />
            <FieldError :message="errors.email" />
        </div>

        <button
            type="submit"
            :disabled="processing"
            class="border-edge text-sm hover:bg-surface-raised w-full border bg-accent px-3 py-2 font-bold text-on-accent hover:text-ink disabled:cursor-not-allowed disabled:opacity-50"
        >
            {{
                processing
                    ? t('auth.forgotPassword.submitting')
                    : t('auth.forgotPassword.submit')
            }}
        </button>

        <p v-if="wasSuccessful" class="text-sm font-bold">
            {{ t('auth.forgotPassword.checkEmail') }}
        </p>
    </Form>

    <div class="text-sm mt-6">
        <Link href="/login" class="underline">{{
            t('auth.forgotPassword.backToLogin')
        }}</Link>
    </div>
</template>
