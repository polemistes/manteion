<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

defineProps<{
    user: {
        name: string;
        username: string;
        email: string;
        bio: string | null;
        avatar_url: string | null;
    };
}>();
</script>

<template>
    <div>
        <Head title="Account" />
        <h1>Account</h1>
    </div>
</template>
