<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import Avatar from '@/components/Avatar.vue';

withDefaults(
    defineProps<{
        id: number;
        name: string;
        username: string;
        avatar_url?: string | null;
        size?: 'sm' | 'md' | 'lg';
    }>(),
    { avatar_url: null, size: 'sm' },
);
</script>

<template>
    <Link
        :href="`/users/${id}`"
        class="inline-flex max-w-full min-w-0 items-center gap-2 hover:underline"
    >
        <Avatar :url="avatar_url" :name="name" :size="size" class="shrink-0" />
        <span class="min-w-0 truncate">{{ name }} · @{{ username }}</span>
    </Link>
</template>
