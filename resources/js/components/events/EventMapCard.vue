<script setup lang="ts">
import { Map as MapIcon } from '@lucide/vue';
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import Button from '@/components/ui/Button.vue';

/**
 * A small, still map of the event's place with a terracotta pin, and a
 * button that opens it in a maps app. The map loads in the browser only.
 */
const props = defineProps<{
    latitude: number;
    longitude: number;
    place: string;
    address?: string | null;
}>();

const { t } = useI18n();

const container = ref<HTMLElement | null>(null);
let destroy: (() => void) | null = null;

const mapUrl = `https://www.openstreetmap.org/?mlat=${props.latitude}&mlon=${props.longitude}#map=16/${props.latitude}/${props.longitude}`;

onMounted(async () => {
    const { default: L } = await import('leaflet');
    await import('leaflet/dist/leaflet.css');

    if (!container.value) {
        return;
    }

    const map = L.map(container.value, {
        center: [props.latitude, props.longitude],
        zoom: 15,
        zoomControl: false,
        attributionControl: true,
        dragging: false,
        scrollWheelZoom: false,
        doubleClickZoom: false,
        touchZoom: false,
        boxZoom: false,
        keyboard: false,
    });

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '© OpenStreetMap',
    }).addTo(map);

    L.marker([props.latitude, props.longitude], {
        icon: L.divIcon({
            className: '',
            html: '<span class="block size-5 rounded-full bg-accent ring-4 ring-surface shadow-sm"></span>',
            iconSize: [20, 20],
            iconAnchor: [10, 10],
        }),
        keyboard: false,
        interactive: false,
    }).addTo(map);

    destroy = () => map.remove();
});

onBeforeUnmount(() => destroy?.());
</script>

<template>
    <section class="overflow-hidden rounded-lg border border-line bg-surface">
        <div
            ref="container"
            class="map-tiles h-[150px] w-full bg-surface-2"
            role="img"
            :aria-label="t('events.show.mapLabel', { place: props.place })"
        />
        <div class="flex items-center gap-3 px-4 py-3">
            <div class="min-w-0 flex-1">
                <p class="truncate text-label font-semibold text-ink">
                    {{ props.place }}
                </p>
                <p
                    v-if="props.address"
                    class="truncate text-meta text-ink-muted"
                >
                    {{ props.address }}
                </p>
            </div>
            <Button
                as="a"
                :href="mapUrl"
                target="_blank"
                rel="noopener noreferrer"
                size="sm"
                :icon="MapIcon"
            >
                {{ t('events.show.openMap') }}
            </Button>
        </div>
    </section>
</template>
