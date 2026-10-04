<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

defineProps<{
    notificationSettings: {
        push: Record<string, boolean>;
        message_previews: boolean;
    };
    categories: { value: string; push_default: boolean }[];
    devices: { id: number; label: string; created_at: string }[];
}>();
</script>

<template>
    <div>
        <Head title="Notifications" />
        <h1>Notifications</h1>
    </div>
</template>
