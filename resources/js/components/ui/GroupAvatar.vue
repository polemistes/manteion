<script setup lang="ts">
import { computed } from 'vue';
import Avatar from './Avatar.vue';

interface GroupMember {
    id: number;
    name: string;
    avatar_url: string | null;
}

/**
 * A group chat's face: two members at 72% of the size, one top-left and one
 * bottom-right, ringed in the parent's background (`--avatar-ring`).
 */
const props = withDefaults(
    defineProps<{
        members: GroupMember[];
        name: string;
        size?: number;
    }>(),
    { size: 48 },
);

const inner = computed(() => Math.round(props.size * 0.72));
const shown = computed(() => props.members.slice(0, 2));

// The face behind is mostly covered: without a photo, its initial moves
// into the corner that shows, a little smaller.
const behindStyle = computed(() => ({
    padding: `0 ${Math.round(inner.value * 0.38)}px ${Math.round(inner.value * 0.38)}px 0`,
    fontSize: `${Math.round(inner.value * 0.32)}px`,
}));
</script>

<template>
    <span
        class="relative inline-block shrink-0"
        :style="{ width: `${props.size}px`, height: `${props.size}px` }"
        role="img"
        :aria-label="props.name"
    >
        <template v-if="shown.length === 2">
            <Avatar
                :name="shown[0].name"
                :src="shown[0].avatar_url"
                :size="inner"
                decorative
                single
                class="absolute top-0 left-0"
                :style="shown[0].avatar_url ? undefined : behindStyle"
            />
            <Avatar
                :name="shown[1].name"
                :src="shown[1].avatar_url"
                :size="inner"
                decorative
                single
                ring
                class="absolute right-0 bottom-0"
            />
        </template>
        <Avatar
            v-else
            :name="shown[0]?.name ?? props.name"
            :src="shown[0]?.avatar_url ?? null"
            :size="props.size"
            decorative
        />
    </span>
</template>
