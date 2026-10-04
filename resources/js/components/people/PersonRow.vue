<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import Avatar from '@/components/ui/Avatar.vue';
import ListRow from '@/components/ui/ListRow.vue';
import { show as usersShow } from '@/routes/users';
import type { Person } from './people';

/**
 * A person in a People list: avatar, name and a sub line, the whole row
 * opening their profile. Buttons go in `actions`, above the link.
 */
const props = withDefaults(
    defineProps<{
        person: Person;
        sub?: string;
    }>(),
    { sub: undefined },
);
</script>

<template>
    <ListRow :inset="74" interactive>
        <template #leading>
            <Avatar
                :name="props.person.name"
                :src="props.person.avatar_url"
                :size="44"
                decorative
            />
        </template>
        <template #title>
            <Link
                :href="usersShow(props.person.id).url"
                class="after:absolute after:inset-0 focus-visible:outline-none focus-visible:after:outline-2 focus-visible:after:outline-offset-[-2px] focus-visible:after:outline-ink"
                >{{ props.person.name }}</Link
            >
        </template>
        <template v-if="$slots.sub || props.sub" #sub>
            <slot name="sub">{{ props.sub }}</slot>
        </template>
        <template v-if="$slots.actions" #trailing>
            <div class="relative z-10 flex shrink-0 items-center gap-1">
                <slot name="actions" />
            </div>
        </template>
    </ListRow>
</template>
