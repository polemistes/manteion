<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

defineProps<{
    theme_mode: 'light' | 'dark' | 'system';
}>();
</script>

<template>
    <div>
        <Head title="Appearance" />
        <h1>Appearance</h1>
    </div>
</template>
