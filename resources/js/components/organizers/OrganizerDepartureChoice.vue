<script lang="ts">
/** One organizer a person speaks for (App\Services\OrganizerDepartures::choiceFor). */
export interface OrganizerDeparture {
    id: number;
    name: string;
    is_main: boolean;
    /** The others who speak for it, the longest-serving first. */
    others: { id: number; name: string }[];
    /** Who receives what a spokesperson who is not the main one made. */
    receiver: { id: number; name: string } | null;
    made: { events: number; leaflets: number; templates: number };
}

/**
 * What the person decided for it, as the server takes it. (A type, not an
 * interface, so a request can carry it as form data.)
 */
export type DepartureDecision = {
    hand_over: boolean;
    successor_id: number | null;
};

/** Hand over, and for a main spokesperson, to the longest-serving other. */
export function defaultDecision(
    organizer: OrganizerDeparture,
): DepartureDecision {
    return {
        hand_over: true,
        successor_id: organizer.is_main
            ? (organizer.others[0]?.id ?? null)
            : null,
    };
}
</script>

<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import OptionCards from '@/components/ui/OptionCards.vue';
import SelectField from '@/components/ui/SelectField.vue';

/**
 * What a spokesperson decides when they stop speaking for an organizer
 * (stepping down, or deleting their account): a main spokesperson, who
 * takes over; and for anyone who made events, leaflets or templates for
 * it, whether those are handed to the main spokesperson or deleted.
 * Someone who is its only spokesperson has nothing to decide: `alone`
 * slot says what happens then.
 */
const props = defineProps<{
    organizer: OrganizerDeparture;
}>();

const decision = defineModel<DepartureDecision>({ required: true });

const { t } = useI18n();

const madeAnything = computed(
    () =>
        props.organizer.made.events +
            props.organizer.made.leaflets +
            props.organizer.made.templates >
        0,
);

/** "3 events, 1 leaflet, 2 templates", leaving out what there is none of. */
const what = computed(() =>
    (['events', 'leaflets', 'templates'] as const)
        .filter((kind) => props.organizer.made[kind] > 0)
        .map((kind) =>
            t(`organizers.departure.${kind}`, props.organizer.made[kind]),
        )
        .join(', '),
);

const successor = computed({
    get: () => String(decision.value.successor_id ?? ''),
    set: (id: string) => {
        decision.value = { ...decision.value, successor_id: Number(id) };
    },
});

const receiverName = computed(() =>
    props.organizer.is_main
        ? (props.organizer.others.find(
              (other) => other.id === decision.value.successor_id,
          )?.name ?? '')
        : (props.organizer.receiver?.name ?? ''),
);

const fate = computed({
    get: () => (decision.value.hand_over ? 'hand' : 'delete'),
    set: (next: string) => {
        decision.value = { ...decision.value, hand_over: next === 'hand' };
    },
});

const fates = computed(() => [
    {
        value: 'hand',
        title: t('organizers.departure.handOver'),
        description: t('organizers.departure.handOverHint', {
            name: receiverName.value,
            what: what.value,
        }),
    },
    {
        value: 'delete',
        title: t('organizers.departure.delete'),
        description: t('organizers.departure.deleteHint', {
            what: what.value,
        }),
    },
]);
</script>

<template>
    <div class="flex flex-col gap-4">
        <slot v-if="!props.organizer.others.length" name="alone" />
        <template v-else>
            <SelectField
                v-if="props.organizer.is_main"
                v-model="successor"
                :options="
                    props.organizer.others.map((other) => ({
                        value: String(other.id),
                        label: other.name,
                    }))
                "
                :label="t('organizers.departure.successor')"
                :hint="t('organizers.departure.successorHint')"
            />
            <div v-if="madeAnything" class="flex flex-col gap-1.5">
                <p class="text-label font-semibold text-ink">
                    {{ t('organizers.departure.whatYouMade') }}
                </p>
                <OptionCards
                    v-model="fate"
                    :options="fates"
                    :label="t('organizers.departure.whatYouMade')"
                />
            </div>
            <p v-else class="text-meta text-ink-muted">
                {{ t('organizers.departure.nothingMade') }}
            </p>
        </template>
    </div>
</template>
