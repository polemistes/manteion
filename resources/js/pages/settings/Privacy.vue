<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

defineProps<{
    privacy: {
        allow_public_follow: boolean;
        show_friends_list: boolean;
        appear_in_friends_lists: boolean;
        searchable: boolean;
    };
}>();
</script>

<template>
    <div>
        <Head title="Privacy" />
        <h1>Privacy</h1>
    </div>
</template>
