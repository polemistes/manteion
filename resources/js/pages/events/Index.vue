<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import PersonSummary from '@/components/PersonSummary.vue';
import { index as eventsIndex, create as eventsCreate } from '@/routes/events';

const { t, locale } = useI18n();

const dateFnsLocales: Record<string, string> = {
    en: 'en-GB',
    nb: 'nb-NO',
};

interface TagOption {
    id: number;
    name: string;
    slug: string;
}

interface ContactOption {
    id: number;
    name: string;
    username: string;
    avatar_url?: string | null;
}

interface GroupOption {
    id: number;
    name: string;
}

interface EventSummary {
    id: number;
    name: string;
    place: string;
    starts_at: string;
    visibility: string;
    owner: ContactOption;
    tags: TagOption[];
    my_status: string | null;
}

const props = defineProps<{
    events: EventSummary[];
    tags: TagOption[];
    friends: ContactOption[];
    following: ContactOption[];
    friendGroups: GroupOption[];
    followingGroups: GroupOption[];
    filters: {
        tags?: string[];
        relation?: string;
        contact_id?: string;
        group_id?: string;
    };
}>();

const selectedTags = ref<string[]>(props.filters.tags ?? []);
const relation = ref(props.filters.relation ?? '');
const contactId = ref(props.filters.contact_id ?? '');
const groupId = ref(props.filters.group_id ?? '');

function applyFilters(): void {
    router.get(
        eventsIndex().url,
        {
            tags: selectedTags.value,
            relation: relation.value || undefined,
            contact_id: contactId.value || undefined,
            group_id: groupId.value || undefined,
        },
        { preserveState: true, replace: true },
    );
}

function formatDate(value: string): string {
    return new Date(value).toLocaleString(
        dateFnsLocales[locale.value] ?? locale.value,
        { dateStyle: 'medium', timeStyle: 'short' },
    );
}

const statusKeys: Record<string, string> = {
    no_response: 'events.show.statusNoResponse',
    attending: 'events.show.statusAttending',
    interested: 'events.show.statusInterested',
    not_attending: 'events.show.statusNotAttending',
};

function statusLabel(status: string): string {
    return t(statusKeys[status] ?? status);
}
</script>

<template>
    <Head :title="t('events.index.title')" />

    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-xl font-bold">{{ t('events.index.title') }}</h1>
        <Link
            :href="eventsCreate().url"
            class="border border-black bg-black px-3 py-2 text-sm text-white hover:bg-white hover:text-black"
        >
            {{ t('events.index.createEvent') }}
        </Link>
    </div>

    <section class="mb-8 border-[3px] border-black p-4">
        <h2 class="mb-3 text-sm font-bold uppercase">
            {{ t('events.index.filters') }}
        </h2>

        <div class="mb-3">
            <p class="mb-1 text-xs font-bold">{{ t('events.index.tags') }}</p>
            <div class="flex flex-wrap gap-3">
                <label
                    v-for="tag in tags"
                    :key="tag.id"
                    class="flex items-center gap-1 text-sm"
                >
                    <input
                        v-model="selectedTags"
                        type="checkbox"
                        :value="tag.slug"
                        class="border border-black"
                        @change="applyFilters"
                    />
                    {{ tag.name }}
                </label>
                <span v-if="!tags.length" class="text-xs text-black/70">{{
                    t('events.index.noTags')
                }}</span>
            </div>
        </div>

        <div class="mb-3 flex flex-wrap gap-6">
            <div>
                <p class="mb-1 text-xs font-bold">
                    {{ t('events.index.ownedBy') }}
                </p>
                <select
                    v-model="relation"
                    class="border border-black bg-white px-2 py-1 text-sm"
                    @change="applyFilters"
                >
                    <option value="">
                        {{ t('events.index.ownedByAnyone') }}
                    </option>
                    <option value="friends">
                        {{ t('events.index.ownedByFriends') }}
                    </option>
                    <option value="following">
                        {{ t('events.index.ownedByFollowing') }}
                    </option>
                    <option value="both">
                        {{ t('events.index.ownedByBoth') }}
                    </option>
                </select>
            </div>

            <div>
                <p class="mb-1 text-xs font-bold">
                    {{ t('events.index.specificPerson') }}
                </p>
                <select
                    v-model="contactId"
                    class="border border-black bg-white px-2 py-1 text-sm"
                    @change="applyFilters"
                >
                    <option value="">{{ t('events.index.anyone') }}</option>
                    <optgroup :label="t('events.index.friendsGroupLabel')">
                        <option
                            v-for="friend in friends"
                            :key="friend.id"
                            :value="String(friend.id)"
                        >
                            {{ friend.name }}
                        </option>
                    </optgroup>
                    <optgroup :label="t('events.index.followingGroupLabel')">
                        <option
                            v-for="contact in following"
                            :key="contact.id"
                            :value="String(contact.id)"
                        >
                            {{ contact.name }}
                        </option>
                    </optgroup>
                </select>
            </div>

            <div>
                <p class="mb-1 text-xs font-bold">
                    {{ t('events.index.specificGroup') }}
                </p>
                <select
                    v-model="groupId"
                    class="border border-black bg-white px-2 py-1 text-sm"
                    @change="applyFilters"
                >
                    <option value="">{{ t('events.index.anyGroup') }}</option>
                    <optgroup :label="t('events.index.friendGroupsLabel')">
                        <option
                            v-for="group in friendGroups"
                            :key="group.id"
                            :value="String(group.id)"
                        >
                            {{ group.name }}
                        </option>
                    </optgroup>
                    <optgroup :label="t('events.index.followingGroupsLabel')">
                        <option
                            v-for="group in followingGroups"
                            :key="group.id"
                            :value="String(group.id)"
                        >
                            {{ group.name }}
                        </option>
                    </optgroup>
                </select>
            </div>
        </div>
    </section>

    <p v-if="!events.length" class="text-sm text-black/70">
        {{ t('events.index.noMatches') }}
    </p>

    <ul class="flex flex-col gap-3">
        <li
            v-for="event in events"
            :key="event.id"
            class="border-[3px] border-black p-4"
        >
            <Link
                :href="`/events/${event.id}`"
                class="text-base font-bold underline"
            >
                {{ event.name }}
            </Link>
            <p class="text-sm text-black/70">
                {{ formatDate(event.starts_at) }} · {{ event.place }}
            </p>
            <p class="mt-1 text-sm">
                {{ t('events.index.by') }}
                <PersonSummary v-bind="event.owner" />
            </p>
            <div class="mt-2 flex flex-wrap items-center gap-2">
                <span
                    v-for="tag in event.tags"
                    :key="tag.id"
                    class="border border-black px-2 py-0.5 text-xs"
                >
                    {{ tag.name }}
                </span>
                <span v-if="event.my_status" class="text-xs font-bold">
                    {{ t('events.index.you') }}
                    {{ statusLabel(event.my_status) }}
                </span>
            </div>
        </li>
    </ul>
</template>
