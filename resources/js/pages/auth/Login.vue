<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import FieldError from '@/components/FieldError.vue';
import PasswordField from '@/components/PasswordField.vue';
import GuestLayout from '@/layouts/GuestLayout.vue';
import { store } from '@/routes/login';
import { request as requestPasswordReset } from '@/routes/password';

defineOptions({ layout: GuestLayout });

const { t } = useI18n();
</script>

<template>
    <Head :title="t('auth.login.title')" />

    <h1 class="mb-6 text-lg font-bold">{{ t('auth.login.title') }}</h1>

    <Form
        v-bind="store.form()"
        #default="{ errors, processing }"
        class="flex flex-col gap-4"
    >
        <div>
            <label for="email" class="mb-1 block text-sm font-bold">{{
                t('auth.login.email')
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

        <div>
            <label for="password" class="mb-1 block text-sm font-bold">{{
                t('auth.login.password')
            }}</label>
            <PasswordField
                id="password"
                name="password"
                required
                autocomplete="current-password"
            />
            <FieldError :message="errors.password" />
        </div>

        <label class="flex items-center gap-2 text-sm">
            <input
                type="checkbox"
                name="remember"
                checked
                class="border border-black"
            />
            {{ t('auth.login.remember') }}
        </label>

        <button
            type="submit"
            :disabled="processing"
            class="w-full border border-black bg-black px-3 py-2 text-sm font-bold text-white hover:bg-white hover:text-black disabled:cursor-not-allowed disabled:opacity-50"
        >
            {{
                processing ? t('auth.login.submitting') : t('auth.login.submit')
            }}
        </button>
    </Form>

    <div class="mt-6 flex flex-col gap-1 text-sm">
        <Link :href="requestPasswordReset().url" class="underline">{{
            t('auth.login.forgotPassword')
        }}</Link>
        <Link href="/register" class="underline">{{
            t('auth.login.needAccount')
        }}</Link>
    </div>
</template>
