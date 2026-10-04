<script setup lang="ts">
import { computed } from 'vue';
import Avatar from './Avatar.vue';

interface StackPerson {
    id: number;
    name: string;
    avatar_url?: string | null;
}

/**
 * Up to four overlapping faces, then "+N" for the rest. `total` is the full
 * count when only some people were sent. The ring takes `--avatar-ring`
 * (the parent's background; surface by default).
 */
const props = withDefaults(
    defineProps<{
        people: StackPerson[];
        total?: number;
        size?: number;
        max?: number;
    }>(),
    { total: undefined, size: 28, max: 4 },
);

const shown = computed(() => props.people.slice(0, props.max));
const rest = computed(
    () => (props.total ?? props.people.length) - shown.value.length,
);
const overlap = computed(() =>
    props.size <= 16 ? -4 : props.size < 28 ? -6 : -8,
);
</script>

<template>
    <span class="inline-flex items-center" aria-hidden="true">
        <Avatar
            v-for="(person, index) in shown"
            :key="person.id"
            :name="person.name"
            :src="person.avatar_url ?? null"
            :size="props.size"
            ring
            decorative
            :style="index > 0 ? { marginLeft: `${overlap}px` } : undefined"
        />
        <span
            v-if="rest > 0"
            class="inline-grid shrink-0 place-items-center rounded-full bg-surface-2 text-kicker tracking-normal text-ink-muted ring-2 ring-[var(--avatar-ring,var(--color-surface))] tabular-nums"
            :style="{
                width: `${props.size}px`,
                height: `${props.size}px`,
                marginLeft: shown.length ? `${overlap}px` : undefined,
            }"
        >
            +{{ rest }}
        </span>
    </span>
</template>
