<script setup lang="ts">
import { Map as MapIcon } from '@lucide/vue';
import type { Map as LeafletMap } from 'leaflet';
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import Button from '@/components/ui/Button.vue';

/**
 * A small, still map of the event's place with a terracotta pin, and a
 * button that opens it in a maps app. The map loads in the browser only,
 * and only once its box has a size: a copy hidden at this breakpoint (the
 * event page has one in the column and one in the aside) waits until it
 * shows, so Leaflet never lays out tiles in an empty box.
 */
const props = defineProps<{
    latitude: number;
    longitude: number;
    place: string;
    address?: string | null;
}>();

const { t } = useI18n();

const container = ref<HTMLElement | null>(null);
let map: LeafletMap | null = null;
let observer: ResizeObserver | null = null;
let starting = false;
let unmounted = false;

const mapUrl = `https://www.openstreetmap.org/?mlat=${props.latitude}&mlon=${props.longitude}#map=16/${props.latitude}/${props.longitude}`;

async function start(element: HTMLElement): Promise<void> {
    starting = true;

    const { default: L } = await import('leaflet');
    await import('leaflet/dist/leaflet.css');

    if (unmounted) {
        return;
    }

    map = L.map(element, {
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
    // Leaflet's own credit carries an English title.
    map.attributionControl.setPrefix(
        '<a href="https://leafletjs.com">Leaflet</a>',
    );

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
}

onMounted(() => {
    const element = container.value;

    if (!element) {
        return;
    }

    observer = new ResizeObserver(() => {
        if (element.clientWidth === 0 || element.clientHeight === 0) {
            return;
        }

        if (map) {
            map.invalidateSize();
        } else if (!starting) {
            void start(element);
        }
    });
    observer.observe(element);
});

onBeforeUnmount(() => {
    unmounted = true;
    observer?.disconnect();
    map?.remove();
    map = null;
});
</script>

<template>
    <!-- isolate: Leaflet's panes and controls (z-index up to 1000) stay
         inside the card instead of painting over bars, sheets and scrims. -->
    <section
        class="isolate overflow-hidden rounded-lg border border-line bg-surface"
    >
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
