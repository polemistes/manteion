<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import Avatar from '@/components/ui/Avatar.vue';
import { show as usersShow } from '@/routes/users';

/** A person as a link to their profile: face, name and, optionally, @handle. */
const props = withDefaults(
    defineProps<{
        id: number;
        name: string;
        username: string;
        avatar_url?: string | null;
        size?: 'sm' | 'md' | 'lg';
        showUsername?: boolean;
    }>(),
    { avatar_url: null, size: 'sm', showUsername: true },
);

const avatarSizes = { sm: 28, md: 40, lg: 64 };
</script>

<template>
    <Link
        :href="usersShow(props.id).url"
        class="inline-flex max-w-full min-w-0 items-center gap-2.5 rounded-full hover:underline"
    >
        <Avatar
            :name="props.name"
            :src="props.avatar_url"
            :size="avatarSizes[props.size]"
            decorative
        />
        <span class="min-w-0 truncate">
            <span class="font-semibold text-ink">{{ props.name }}</span>
            <span v-if="props.showUsername" class="text-ink-muted">
                @{{ props.username }}</span
            >
        </span>
    </Link>
</template>
