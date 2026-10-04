<script setup lang="ts">
import { LocateFixed, MapPin } from '@lucide/vue';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import markerIcon2x from 'leaflet/dist/images/marker-icon-2x.png';
import markerIcon from 'leaflet/dist/images/marker-icon.png';
import markerShadow from 'leaflet/dist/images/marker-shadow.png';
import { onMounted, onUnmounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import {
    cityFromAddress,
    countryFromAddress,
    MIN_QUERY_LENGTH,
    reverseGeocode,
    useNominatimSearch,
} from '@/composables/useNominatimSearch';
import type { NominatimResult } from '@/composables/useNominatimSearch';

// Vite bundles these as hashed URLs, which breaks Leaflet's default
// relative-path icon lookup unless we point it at the bundled files.
delete (L.Icon.Default.prototype as { _getIconUrl?: unknown })._getIconUrl;
L.Icon.Default.mergeOptions({
    iconRetinaUrl: markerIcon2x,
    iconUrl: markerIcon,
    shadowUrl: markerShadow,
});

const DEFAULT_CENTER: L.LatLngTuple = [60.472, 8.4689];
const DEFAULT_ZOOM = 5;
const PIN_ZOOM = 15;

interface LocationValue {
    latitude: number | null;
    longitude: number | null;
    city: string | null;
    country: string | null;
}

const props = withDefaults(
    defineProps<{
        initialValue?: LocationValue | null;
    }>(),
    { initialValue: null },
);

const { t } = useI18n();

const latitude = ref<number | null>(props.initialValue?.latitude ?? null);
const longitude = ref<number | null>(props.initialValue?.longitude ?? null);
const city = ref<string | null>(props.initialValue?.city ?? null);
const country = ref<string | null>(props.initialValue?.country ?? null);
const searchQuery = ref('');
// True once a result has been picked: the field then holds the chosen
// address, which is not an unanswered search.
const picked = ref(false);

const { results, searching, error, search, clearResults } =
    useNominatimSearch();

const mapContainer = ref<HTMLDivElement | null>(null);
let map: L.Map | null = null;
let marker: L.Marker | null = null;

function placeMarker(lat: number, lng: number): void {
    if (!map) {
        return;
    }

    if (marker) {
        marker.setLatLng([lat, lng]);

        return;
    }

    marker = L.marker([lat, lng], {
        draggable: true,
        icon: L.divIcon({
            className: '',
            html: '<span class="block size-6 rounded-full bg-accent ring-4 ring-surface shadow-sm"></span>',
            iconSize: [24, 24],
            iconAnchor: [12, 12],
        }),
    }).addTo(map);
    marker.on('dragend', () => {
        const position = marker?.getLatLng();

        if (position) {
            void applyCoordinates(position.lat, position.lng);
        }
    });
}

async function applyCoordinates(lat: number, lng: number): Promise<void> {
    latitude.value = lat;
    longitude.value = lng;
    placeMarker(lat, lng);

    const result = await reverseGeocode(lat, lng);

    if (result) {
        city.value = cityFromAddress(result.address);
        country.value = countryFromAddress(result.address);
    }
}

function selectResult(result: NominatimResult): void {
    const lat = parseFloat(result.lat);
    const lng = parseFloat(result.lon);

    latitude.value = lat;
    longitude.value = lng;
    city.value = cityFromAddress(result.address);
    country.value = countryFromAddress(result.address);
    searchQuery.value = result.display_name;
    picked.value = true;
    clearResults();

    placeMarker(lat, lng);
    map?.setView([lat, lng], PIN_ZOOM);
}

function onSearchInput(event: Event): void {
    const value = (event.target as HTMLInputElement).value;
    searchQuery.value = value;
    picked.value = false;
    search(value);
}

function clearLocation(): void {
    latitude.value = null;
    longitude.value = null;
    city.value = null;
    country.value = null;
    searchQuery.value = '';
    picked.value = false;
    clearResults();

    if (marker) {
        marker.remove();
        marker = null;
    }
}

onMounted(() => {
    if (!mapContainer.value) {
        return;
    }

    const hasInitialPin = latitude.value !== null && longitude.value !== null;

    map = L.map(mapContainer.value).setView(
        hasInitialPin ? [latitude.value!, longitude.value!] : DEFAULT_CENTER,
        hasInitialPin ? PIN_ZOOM : DEFAULT_ZOOM,
    );

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors',
        maxZoom: 19,
    }).addTo(map);

    if (hasInitialPin) {
        placeMarker(latitude.value!, longitude.value!);
    }

    map.on('click', (event: L.LeafletMouseEvent) => {
        void applyCoordinates(event.latlng.lat, event.latlng.lng);
    });
});

onUnmounted(() => {
    map?.remove();
    map = null;
    marker = null;
});
</script>

<template>
    <div class="flex flex-col gap-2">
        <input type="hidden" name="latitude" :value="latitude ?? ''" />
        <input type="hidden" name="longitude" :value="longitude ?? ''" />
        <input type="hidden" name="city" :value="city ?? ''" />
        <input type="hidden" name="country" :value="country ?? ''" />

        <div class="relative">
            <MapPin
                :size="18"
                :stroke-width="1.9"
                class="pointer-events-none absolute top-1/2 left-3.5 -translate-y-1/2 text-ink-muted"
                aria-hidden="true"
            />
            <input
                type="search"
                :value="searchQuery"
                :aria-label="t('location.mapLocation')"
                :placeholder="t('location.searchPlaceholder')"
                class="h-12 w-full rounded-md bg-surface pr-3.5 pl-10 text-input text-ink ring-1 ring-control ring-inset hover:ring-ink-subtle focus:ring-2 focus:ring-ink focus:outline-none [&::-webkit-search-cancel-button]:hidden"
                @input="onSearchInput"
                @keydown.enter.prevent
            />
            <!-- Leaflet's own panes and controls use z-index up to 1000, so
                 this has to clear that or the map swallows the list. -->
            <ul
                v-if="results.length"
                class="absolute z-[1001] mt-1 w-full overflow-hidden rounded-md bg-surface shadow-lg ring-1 ring-line"
            >
                <li v-for="result in results" :key="result.display_name">
                    <button
                        type="button"
                        class="flex min-h-11 w-full items-center px-3.5 py-2 text-left text-label hover:bg-surface-2"
                        @click="selectResult(result)"
                    >
                        <span class="line-clamp-2">{{
                            result.display_name
                        }}</span>
                    </button>
                </li>
            </ul>
            <p v-else-if="searching" class="mt-1.5 text-meta text-ink-muted">
                {{ t('location.searching') }}
            </p>
            <p v-else-if="error" class="mt-1.5 text-meta text-danger">
                {{ t('location.searchError') }}
            </p>
            <p
                v-else-if="
                    !picked && searchQuery.trim().length >= MIN_QUERY_LENGTH
                "
                class="mt-1.5 text-meta text-ink-muted"
            >
                {{ t('location.noResults') }}
            </p>
        </div>

        <div class="overflow-hidden rounded-lg border border-line bg-surface">
            <div
                ref="mapContainer"
                class="map-tiles h-[180px] w-full bg-surface-2"
            ></div>
            <div
                class="flex min-h-12 items-center gap-3 px-3.5 py-2 text-meta text-ink-muted"
            >
                <LocateFixed
                    :size="16"
                    :stroke-width="2"
                    class="shrink-0"
                    aria-hidden="true"
                />
                <span class="min-w-0 flex-1">
                    <template v-if="latitude !== null && longitude !== null">{{
                        [city, country].filter(Boolean).join(', ') ||
                        t('location.pinPlaced')
                    }}</template>
                    <template v-else>{{ t('location.tapToPlace') }}</template>
                </span>
                <button
                    v-if="latitude !== null || longitude !== null"
                    type="button"
                    class="min-h-11 font-semibold text-ink underline underline-offset-2"
                    @click="clearLocation"
                >
                    {{ t('location.clear') }}
                </button>
            </div>
        </div>
    </div>
</template>
