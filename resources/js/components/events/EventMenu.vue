<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import {
    CalendarPlus,
    Ellipsis,
    Flag,
    LayoutTemplate,
    Pencil,
    Search,
    Trash,
    UserPlus,
} from '@lucide/vue';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import IconButton from '@/components/ui/IconButton.vue';
import Menu from '@/components/ui/Menu.vue';
import MenuItem from '@/components/ui/MenuItem.vue';
import MenuSeparator from '@/components/ui/MenuSeparator.vue';
import { create as createEvent, edit as editEvent } from '@/routes/events';
import eventTemplate from '@/routes/events/template';

/**
 * The "⋯" menu of an event's page: edit, invite, start a new event from
 * it or save it as a template (its hosts), make searchable, report and
 * delete, each only for those who may. The page shows it in three
 * places (the phone's top bar, over the cover on a phone, and beside Share
 * from 640), so the items live here once; `floating` draws the button for
 * sitting on a picture. Renders nothing when there is nothing to offer.
 */
const props = withDefaults(
    defineProps<{
        event: {
            id: number;
            visibility: 'private' | 'public' | 'searchable';
            is_owner: boolean;
            is_searchable_paid: boolean;
            can_invite: boolean;
            can_delete: boolean;
            can_report: boolean;
        };
        floating?: boolean;
    }>(),
    { floating: false },
);

const emit = defineEmits<{
    invite: [];
    searchable: [];
    report: [];
    delete: [];
}>();

const { t } = useI18n();

const canMakeSearchable = computed(
    () =>
        props.event.is_owner &&
        props.event.visibility === 'public' &&
        !props.event.is_searchable_paid,
);

const hasItems = computed(
    () =>
        props.event.is_owner ||
        props.event.can_invite ||
        props.event.can_delete ||
        props.event.can_report,
);
</script>

<template>
    <Menu v-if="hasItems">
        <template #trigger>
            <IconButton
                :icon="Ellipsis"
                :label="t('events.show.more')"
                :variant="props.floating ? 'floating' : undefined"
            />
        </template>
        <MenuItem
            v-if="props.event.is_owner"
            :icon="Pencil"
            @select="router.visit(editEvent(props.event.id).url)"
            >{{ t('events.show.edit') }}</MenuItem
        >
        <MenuItem
            v-if="props.event.can_invite"
            :icon="UserPlus"
            @select="emit('invite')"
            >{{ t('events.show.invite') }}</MenuItem
        >
        <template v-if="props.event.is_owner">
            <MenuItem
                :icon="CalendarPlus"
                @select="
                    router.visit(
                        createEvent({ query: { from: props.event.id } }).url,
                    )
                "
                >{{ t('events.show.newFromThis') }}</MenuItem
            >
            <MenuItem
                :icon="LayoutTemplate"
                @select="router.post(eventTemplate.store(props.event.id).url)"
                >{{ t('events.show.saveAsTemplate') }}</MenuItem
            >
        </template>
        <MenuItem
            v-if="canMakeSearchable"
            :icon="Search"
            @select="emit('searchable')"
            >{{ t('events.show.makeSearchable') }}</MenuItem
        >
        <MenuItem
            v-if="props.event.can_report"
            :icon="Flag"
            @select="emit('report')"
            >{{ t('events.show.report') }}</MenuItem
        >
        <template v-if="props.event.can_delete">
            <MenuSeparator />
            <MenuItem :icon="Trash" danger @select="emit('delete')">{{
                t('events.show.delete')
            }}</MenuItem>
        </template>
    </Menu>
</template>
