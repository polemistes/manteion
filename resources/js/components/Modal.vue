<script setup lang="ts">
import { onMounted, onUnmounted } from 'vue';

const props = defineProps<{
    open: boolean;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
}>();

function close(): void {
    emit('update:open', false);
}

function onKeydown(event: KeyboardEvent): void {
    if (props.open && event.key === 'Escape') {
        close();
    }
}

onMounted(() => document.addEventListener('keydown', onKeydown));
onUnmounted(() => document.removeEventListener('keydown', onKeydown));
</script>

<template>
    <div
        v-if="props.open"
        class="fixed inset-0 z-50 flex items-center justify-center bg-ink/50 p-4"
        @click.self="close"
    >
        <div
            class="w-full max-w-sm border-frame border-edge bg-surface-raised p-6"
        >
            <slot />
        </div>
    </div>
</template>
