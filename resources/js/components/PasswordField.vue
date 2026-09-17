<script setup lang="ts">
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';

defineOptions({ inheritAttrs: false });

defineProps<{
    id: string;
    name: string;
}>();

const { t } = useI18n();

const visible = ref(false);
</script>

<template>
    <div class="relative">
        <input
            :id="id"
            :name="name"
            :type="visible ? 'text' : 'password'"
            v-bind="$attrs"
            class="w-full border border-edge bg-surface-raised px-3 py-2 pr-10 font-sans text-sm focus:ring-1 focus:ring-accent focus:outline-none"
        />
        <button
            type="button"
            class="absolute inset-y-0 right-0 flex items-center px-3 text-ink/60 hover:text-ink"
            :aria-label="
                visible ? t('common.hidePassword') : t('common.showPassword')
            "
            @click="visible = !visible"
        >
            <svg
                v-if="visible"
                viewBox="0 0 24 24"
                width="18"
                height="18"
                fill="none"
                stroke="currentColor"
                stroke-width="1.75"
                stroke-linecap="round"
                stroke-linejoin="round"
            >
                <path
                    d="M17.94 17.94A10.94 10.94 0 0 1 12 20c-7 0-11-8-11-8a18.5 18.5 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"
                />
                <line x1="1" y1="1" x2="23" y2="23" />
            </svg>
            <svg
                v-else
                viewBox="0 0 24 24"
                width="18"
                height="18"
                fill="none"
                stroke="currentColor"
                stroke-width="1.75"
                stroke-linecap="round"
                stroke-linejoin="round"
            >
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8Z" />
                <circle cx="12" cy="12" r="3" />
            </svg>
        </button>
    </div>
</template>
