<script setup lang="ts">
import { Eye, EyeOff } from '@lucide/vue';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import IconButton from '@/components/ui/IconButton.vue';

defineOptions({ inheritAttrs: false });

/** A password input with a show/hide toggle; other attributes go to the input. */
defineProps<{
    id: string;
    name: string;
    invalid?: boolean;
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
            :aria-invalid="invalid ? 'true' : undefined"
            v-bind="$attrs"
            class="h-12 w-full rounded-md bg-surface pr-12 pl-3.5 text-input text-ink ring-1 ring-control transition-shadow duration-120 ring-inset hover:ring-ink-subtle focus:ring-2 focus:ring-ink focus:outline-none"
            :class="invalid ? 'ring-2 ring-danger' : ''"
        />
        <IconButton
            :icon="visible ? EyeOff : Eye"
            :label="
                visible ? t('common.hidePassword') : t('common.showPassword')
            "
            :pressed="visible"
            size="sm"
            class="absolute top-1/2 right-1.5 -translate-y-1/2"
            @click="visible = !visible"
        />
    </div>
</template>
