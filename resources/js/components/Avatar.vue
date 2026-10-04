<script setup lang="ts">
withDefaults(
    defineProps<{
        url?: string | null;
        name: string;
        size?: 'sm' | 'md' | 'lg';
    }>(),
    { url: null, size: 'sm' },
);

const sizeClasses = {
    sm: 'h-8 w-8 text-xs',
    md: 'h-16 w-16 text-lg',
    lg: 'h-28 w-28 text-3xl',
};
</script>

<template>
    <img
        v-if="url"
        :src="url"
        :alt="name"
        class="border-edge shrink-0 border object-cover"
        :class="sizeClasses[size]"
    />
    <span
        v-else
        class="border-edge bg-surface-raised flex shrink-0 items-center justify-center border font-bold"
        :class="sizeClasses[size]"
    >
        {{ name.charAt(0).toUpperCase() }}
    </span>
</template>
