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

    <h1 class="mb-2 text-lg font-bold">{{ t('auth.forgotPassword.title') }}</h1>
    <p class="mb-6 text-sm text-black/70">
        {{ t('auth.forgotPassword.description') }}
    </p>

    <Form
        v-bind="email.form()"
        #default="{ errors, processing, wasSuccessful }"
        class="flex flex-col gap-4"
    >
        <div>
            <label for="email" class="mb-1 block text-sm font-bold">{{
                t('auth.forgotPassword.email')
            }}</label>
            <input
                id="email"
                type="email"
                name="email"
                required
                autofocus
                autocomplete="username"
                class="w-full border border-black bg-white px-3 py-2 font-sans text-sm focus:ring-1 focus:ring-black focus:outline-none"
            />
            <FieldError :message="errors.email" />
        </div>

        <button
            type="submit"
            :disabled="processing"
            class="w-full border border-black bg-black px-3 py-2 text-sm font-bold text-white hover:bg-white hover:text-black disabled:cursor-not-allowed disabled:opacity-50"
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

    <div class="mt-6 text-sm">
        <Link href="/login" class="underline">{{
            t('auth.forgotPassword.backToLogin')
        }}</Link>
    </div>
</template>
