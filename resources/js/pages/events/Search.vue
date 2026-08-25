<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import PersonSummary from '@/components/PersonSummary.vue';
import { search } from '@/routes/events';

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

interface EventSummary {
    id: number;
    name: string;
    place: string;
    starts_at: string;
    owner: {
        id: number;
        name: string;
        username: string;
        avatar_url: string | null;
    };
    tags: TagOption[];
}

const props = defineProps<{
    q: string;
    tags: TagOption[];
    events: EventSummary[];
}>();

const query = ref(props.q);

function submit(): void {
    router.get(
        search().url,
        { q: query.value },
        { preserveState: true, replace: true },
    );
}

function formatDate(value: string): string {
    return new Date(value).toLocaleString(
        dateFnsLocales[locale.value] ?? locale.value,
        { dateStyle: 'medium', timeStyle: 'short' },
    );
}
</script>

<template>
    <Head :title="t('events.search.title')" />

    <h1 class="mb-6 text-xl font-bold">{{ t('events.search.title') }}</h1>

    <form class="mb-6 flex gap-2" @submit.prevent="submit">
        <input
            v-model="query"
            type="text"
            :placeholder="t('events.search.placeholder')"
            autofocus
            class="flex-1 border border-black bg-white px-3 py-2 font-sans text-sm focus:ring-1 focus:ring-black focus:outline-none"
        />
        <button
            type="submit"
            class="border border-black px-4 py-2 text-sm hover:bg-black hover:text-white"
        >
            {{ t('events.search.search') }}
        </button>
    </form>

    <p v-if="q && !events.length" class="text-sm text-black/70">
        {{ t('events.search.noResults', { query: q }) }}
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
                {{ t('events.search.by') }}
                <PersonSummary v-bind="event.owner" />
            </p>
            <div class="mt-2 flex flex-wrap gap-2">
                <span
                    v-for="tag in event.tags"
                    :key="tag.id"
                    class="border border-black px-2 py-0.5 text-xs"
                >
                    {{ tag.name }}
                </span>
            </div>
        </li>
    </ul>
</template>
