<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import {
    BellOff,
    Ellipsis,
    FolderPlus,
    MessageCircle,
    User,
    UserMinus,
    UserPlus,
} from '@lucide/vue';
import { useI18n } from 'vue-i18n';
import IconButton from '@/components/ui/IconButton.vue';
import Menu from '@/components/ui/Menu.vue';
import MenuItem from '@/components/ui/MenuItem.vue';
import MenuSeparator from '@/components/ui/MenuSeparator.vue';
import { show as usersShow } from '@/routes/users';
import { messagePerson } from './people';
import type { Person } from './people';

/**
 * What you can do about one person, by how you know them: message them,
 * put them in a group, mute some of their events, see their profile, and
 * end it (unfriend, unfollow, or remove them as a follower).
 */
const props = withDefaults(
    defineProps<{
        person: Person;
        kind: 'friend' | 'following' | 'follower';
        /** What is muted so far, shown beside "Mute categories…". */
        mutedHint?: string;
        canFollowBack?: boolean;
        /** Messages are between friends only. */
        canMessage?: boolean;
    }>(),
    { mutedHint: undefined, canFollowBack: false, canMessage: false },
);

const emit = defineEmits<{
    addToGroup: [];
    mute: [];
    followBack: [];
    remove: [];
}>();

const { t } = useI18n();
</script>

<template>
    <Menu>
        <template #trigger>
            <IconButton
                :icon="Ellipsis"
                :label="t('people.menu.label', { name: props.person.name })"
                size="sm"
            />
        </template>
        <MenuItem
            v-if="props.canMessage"
            :icon="MessageCircle"
            @select="messagePerson(props.person)"
        >
            {{ t('people.menu.message') }}
        </MenuItem>
        <MenuItem
            v-if="props.kind === 'follower' && props.canFollowBack"
            :icon="UserPlus"
            @select="emit('followBack')"
        >
            {{ t('people.menu.followBack') }}
        </MenuItem>
        <MenuItem
            v-if="props.kind !== 'follower'"
            :icon="FolderPlus"
            @select="emit('addToGroup')"
        >
            {{ t('people.menu.addToGroup') }}
        </MenuItem>
        <MenuItem
            v-if="props.kind !== 'follower'"
            :icon="BellOff"
            :hint="props.mutedHint"
            @select="emit('mute')"
        >
            {{ t('people.menu.mute') }}
        </MenuItem>
        <MenuItem
            :icon="User"
            @select="router.visit(usersShow(props.person.id).url)"
        >
            {{ t('people.menu.profile') }}
        </MenuItem>
        <MenuSeparator />
        <MenuItem :icon="UserMinus" danger @select="emit('remove')">
            {{
                props.kind === 'friend'
                    ? t('people.menu.unfriend')
                    : props.kind === 'following'
                      ? t('people.menu.unfollow')
                      : t('people.menu.removeFollower')
            }}
        </MenuItem>
    </Menu>
</template>
