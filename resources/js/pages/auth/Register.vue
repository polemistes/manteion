<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import FieldError from '@/components/FieldError.vue';
import PasswordField from '@/components/PasswordField.vue';
import GuestLayout from '@/layouts/GuestLayout.vue';
import { store } from '@/routes/register';

defineOptions({ layout: GuestLayout });

const { t } = useI18n();
</script>

<template>
    <Head :title="t('auth.register.title')" />

    <h1 class="text-lg mb-6 font-bold">{{ t('auth.register.title') }}</h1>

    <Form
        v-bind="store.form()"
        #default="{ errors, processing }"
        class="flex flex-col gap-4"
    >
        <div>
            <label for="name" class="text-sm mb-1 block font-bold">{{
                t('auth.register.name')
            }}</label>
            <input
                id="name"
                type="text"
                name="name"
                required
                autofocus
                autocomplete="name"
                class="border-edge bg-surface-raised text-sm w-full border px-3 py-2 font-sans focus:ring-1 focus:ring-accent focus:outline-none"
            />
            <FieldError :message="errors.name" />
        </div>

        <div>
            <label for="username" class="text-sm mb-1 block font-bold">{{
                t('auth.register.username')
            }}</label>
            <input
                id="username"
                type="text"
                name="username"
                required
                autocomplete="username"
                class="border-edge bg-surface-raised text-sm w-full border px-3 py-2 font-sans focus:ring-1 focus:ring-accent focus:outline-none"
            />
            <FieldError :message="errors.username" />
        </div>

        <div>
            <label for="email" class="text-sm mb-1 block font-bold">{{
                t('auth.register.email')
            }}</label>
            <input
                id="email"
                type="email"
                name="email"
                required
                autocomplete="email"
                class="border-edge bg-surface-raised text-sm w-full border px-3 py-2 font-sans focus:ring-1 focus:ring-accent focus:outline-none"
            />
            <FieldError :message="errors.email" />
        </div>

        <div>
            <label for="password" class="text-sm mb-1 block font-bold">{{
                t('auth.register.password')
            }}</label>
            <PasswordField
                id="password"
                name="password"
                required
                autocomplete="new-password"
            />
            <p class="text-xs mt-1 text-ink/70">
                {{ t('common.passphraseHint') }}
            </p>
            <FieldError :message="errors.password" />
        </div>

        <div>
            <label
                for="password_confirmation"
                class="text-sm mb-1 block font-bold"
                >{{ t('auth.register.passwordConfirmation') }}</label
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
            class="border-edge text-sm hover:bg-surface-raised w-full border bg-accent px-3 py-2 font-bold text-on-accent hover:text-ink disabled:cursor-not-allowed disabled:opacity-50"
        >
            {{
                processing
                    ? t('auth.register.submitting')
                    : t('auth.register.submit')
            }}
        </button>
    </Form>

    <div class="text-sm mt-6">
        <Link href="/login" class="underline">{{
            t('auth.register.haveAccount')
        }}</Link>
    </div>
</template>
