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

    <h1 class="text-lg mb-6 font-bold">{{ t('auth.login.title') }}</h1>

    <Form
        v-bind="store.form()"
        #default="{ errors, processing }"
        class="flex flex-col gap-4"
    >
        <div>
            <label for="username" class="text-sm mb-1 block font-bold">{{
                t('auth.login.username')
            }}</label>
            <input
                id="username"
                type="text"
                name="username"
                required
                autofocus
                autocomplete="username"
                class="border-edge bg-surface-raised text-sm w-full border px-3 py-2 font-sans focus:ring-1 focus:ring-accent focus:outline-none"
            />
            <FieldError :message="errors.username" />
        </div>

        <div>
            <label for="password" class="text-sm mb-1 block font-bold">{{
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

        <label class="text-sm flex items-center gap-2">
            <input
                type="checkbox"
                name="remember"
                checked
                class="border-edge border"
            />
            {{ t('auth.login.remember') }}
        </label>

        <button
            type="submit"
            :disabled="processing"
            class="border-edge text-sm hover:bg-surface-raised w-full border bg-accent px-3 py-2 font-bold text-on-accent hover:text-ink disabled:cursor-not-allowed disabled:opacity-50"
        >
            {{
                processing ? t('auth.login.submitting') : t('auth.login.submit')
            }}
        </button>
    </Form>

    <div class="text-sm mt-6 flex flex-col gap-1">
        <Link :href="requestPasswordReset().url" class="underline">{{
            t('auth.login.forgotPassword')
        }}</Link>
        <Link href="/register" class="underline">{{
            t('auth.login.needAccount')
        }}</Link>
    </div>
</template>
