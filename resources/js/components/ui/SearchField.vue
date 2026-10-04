<script setup lang="ts">
import { Search, X } from '@lucide/vue';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import IconButton from './IconButton.vue';

defineOptions({ inheritAttrs: false });

/** The pill search field, with a clear button while it has text. */
const props = withDefaults(
    defineProps<{
        label: string;
        placeholder?: string;
    }>(),
    { placeholder: undefined },
);

const emit = defineEmits<{ clear: [] }>();

const model = defineModel<string>({ default: '' });

const { t } = useI18n();
const input = ref<HTMLInputElement | null>(null);

function clear(): void {
    model.value = '';
    emit('clear');
    input.value?.focus();
}
</script>

<template>
    <div class="relative">
        <Search
            :size="18"
            :stroke-width="1.9"
            class="pointer-events-none absolute top-1/2 left-4 -translate-y-1/2 text-ink-muted"
            aria-hidden="true"
        />
        <input
            ref="input"
            v-model="model"
            type="search"
            :aria-label="props.label"
            :placeholder="props.placeholder ?? props.label"
            v-bind="$attrs"
            class="h-11 w-full rounded-full bg-surface pr-11 pl-11 text-input text-ink ring-1 ring-line-strong ring-inset focus:ring-2 focus:ring-ink focus:outline-none [&::-webkit-search-cancel-button]:hidden"
        />
        <IconButton
            v-if="model"
            :icon="X"
            :label="t('ui.clearSearch')"
            size="sm"
            class="absolute top-1/2 right-1 -translate-y-1/2"
            @click="clear"
        />
    </div>
</template>
