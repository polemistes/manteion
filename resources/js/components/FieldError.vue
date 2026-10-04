<script setup lang="ts">
import { CircleAlert } from '@lucide/vue';

/** A validation message under a field. */
defineProps<{
    message?: string;
}>();
</script>

<template>
    <p
        v-if="message"
        class="mt-1.5 flex items-center gap-1.5 text-meta font-medium text-danger"
    >
        <CircleAlert :size="16" :stroke-width="2" aria-hidden="true" />
        {{ message }}
    </p>
</template>
