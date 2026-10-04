<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import AppDialog from '@/components/ui/AppDialog.vue';
import Avatar from '@/components/ui/Avatar.vue';
import ListRow from '@/components/ui/ListRow.vue';
import SegmentedControl from '@/components/ui/SegmentedControl.vue';
import Tag from '@/components/ui/Tag.vue';
import { show as usersShow } from '@/routes/users';

interface Person {
    id: number;
    name: string;
    username: string;
    avatar_url: string | null;
}

interface Attendee {
    id: number;
    user: Person;
    status: string;
}

type Tab = 'attending' | 'interested' | 'no_response' | 'not_attending';

/**
 * "Who's coming": Going / Maybe / Invited, as a row of faces with first
 * names (the host first, tagged). "See all" opens the full guest list,
 * where the host also sees who can't go.
 */
const props = defineProps<{
    attendees: Attendee[];
    host: Person;
    viewerIsHost: boolean;
}>();

const { t } = useI18n();

const tab = ref<Tab>('attending');
const listOpen = ref(false);
const listTab = ref<Tab>('attending');

const groups = computed<Record<Tab, Person[]>>(() => {
    const byStatus = (status: Tab) =>
        props.attendees
            .filter((attendee) => attendee.status === status)
            .map((attendee) => attendee.user)
            .filter((person) => person.id !== props.host.id);

    return {
        attending: [props.host, ...byStatus('attending')],
        interested: byStatus('interested'),
        no_response: byStatus('no_response'),
        not_attending: byStatus('not_attending'),
    };
});

const segments = computed(() => [
    {
        value: 'attending',
        label: t('events.show.going'),
        count: groups.value.attending.length,
    },
    ...(groups.value.interested.length
        ? [
              {
                  value: 'interested',
                  label: t('events.show.maybe'),
                  count: groups.value.interested.length,
              },
          ]
        : []),
    ...(groups.value.no_response.length
        ? [
              {
                  value: 'no_response',
                  label: t('events.show.invited'),
                  count: groups.value.no_response.length,
              },
          ]
        : []),
]);

const listSegments = computed(() => [
    ...segments.value,
    ...(props.viewerIsHost && groups.value.not_attending.length
        ? [
              {
                  value: 'not_attending',
                  label: t('events.show.cantGo'),
                  count: groups.value.not_attending.length,
              },
          ]
        : []),
]);

// A group that empties (someone changed their answer) takes its segment
// with it: fall back to Going.
watch(segments, (next) => {
    if (!next.some((segment) => segment.value === tab.value)) {
        tab.value = 'attending';
    }
});

watch(listSegments, (next) => {
    if (!next.some((segment) => segment.value === listTab.value)) {
        listTab.value = 'attending';
    }
});

const FACES = 5;
const faces = computed(() => groups.value[tab.value].slice(0, FACES));
const rest = computed(
    () => groups.value[tab.value].length - faces.value.length,
);

function firstName(name: string): string {
    return name.split(/\s+/)[0] ?? name;
}

function openList(): void {
    listTab.value = tab.value;
    listOpen.value = true;
}
</script>

<template>
    <section>
        <div class="mb-3 flex items-center justify-between gap-3">
            <h2 class="font-display text-title-4">
                {{ t('events.show.whosComing') }}
            </h2>
            <button
                type="button"
                class="min-h-11 text-label font-semibold text-ink underline underline-offset-2"
                @click="openList"
            >
                {{ t('events.show.seeAll') }}
            </button>
        </div>
        <SegmentedControl
            v-if="segments.length > 1"
            v-model="tab"
            :segments="segments"
            :label="t('events.show.whosComing')"
            class="w-full [&>button]:flex-1"
        />
        <p
            v-if="!faces.length"
            class="text-meta text-ink-muted"
            :class="segments.length > 1 ? 'mt-4' : ''"
        >
            {{ t('events.show.noOne') }}
        </p>
        <ul
            v-else
            class="grid grid-cols-6 gap-2"
            :class="segments.length > 1 ? 'mt-4' : ''"
        >
            <li v-for="person in faces" :key="person.id">
                <Link
                    :href="usersShow(person.id).url"
                    class="flex flex-col items-center gap-1.5 text-center"
                >
                    <span class="relative">
                        <Avatar
                            :name="person.name"
                            :src="person.avatar_url"
                            :size="48"
                            decorative
                            class="max-w-full"
                        />
                        <span
                            v-if="person.id === props.host.id"
                            class="absolute -bottom-1.5 left-1/2 -translate-x-1/2 rounded-xs bg-ink px-1.5 text-kicker leading-4 whitespace-nowrap text-canvas uppercase ring-2 ring-surface"
                            >{{ t('events.show.hostTag') }}</span
                        >
                    </span>
                    <span
                        class="w-full truncate text-caption font-medium text-ink-muted"
                        >{{ firstName(person.name) }}</span
                    >
                </Link>
            </li>
            <li v-if="rest > 0">
                <button
                    type="button"
                    class="flex w-full flex-col items-center gap-1.5"
                    @click="openList"
                >
                    <span
                        class="grid size-12 place-items-center rounded-full bg-surface-2 text-label font-semibold text-ink"
                        >+{{ rest }}</span
                    >
                    <span class="text-caption font-medium text-ink-muted">{{
                        t('ui.more').toLowerCase()
                    }}</span>
                </button>
            </li>
        </ul>

        <AppDialog
            v-model:open="listOpen"
            :title="t('events.show.guestList')"
            size="lg"
        >
            <SegmentedControl
                v-if="listSegments.length > 1"
                v-model="listTab"
                :segments="listSegments"
                :label="t('events.show.guestList')"
                class="mb-4"
            />
            <div class="overflow-hidden rounded-lg border border-line">
                <ListRow
                    v-for="person in groups[listTab]"
                    :key="person.id"
                    :as="Link"
                    :href="usersShow(person.id).url"
                    interactive
                    :title="person.name"
                    :sub="`@${person.username}`"
                    :inset="74"
                >
                    <template #leading>
                        <Avatar
                            :name="person.name"
                            :src="person.avatar_url"
                            :size="44"
                            decorative
                        />
                    </template>
                    <template v-if="person.id === props.host.id" #trailing>
                        <Tag tone="host">{{ t('events.show.hostTag') }}</Tag>
                    </template>
                </ListRow>
                <p
                    v-if="!groups[listTab].length"
                    class="px-4 py-6 text-center text-ink-muted"
                >
                    {{ t('events.show.noOne') }}
                </p>
            </div>
        </AppDialog>
    </section>
</template>
