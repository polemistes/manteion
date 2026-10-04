<script setup lang="ts">
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

    marker = L.marker([lat, lng], { draggable: true }).addTo(map);
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
    <div>
        <input type="hidden" name="latitude" :value="latitude ?? ''" />
        <input type="hidden" name="longitude" :value="longitude ?? ''" />
        <input type="hidden" name="city" :value="city ?? ''" />
        <input type="hidden" name="country" :value="country ?? ''" />

        <p class="text-sm mb-1 font-bold">{{ t('location.mapLocation') }}</p>

        <div class="relative mb-2">
            <input
                type="text"
                :value="searchQuery"
                :placeholder="t('location.searchPlaceholder')"
                class="border-edge bg-surface-raised text-sm w-full border px-3 py-2 font-sans focus:ring-1 focus:ring-accent focus:outline-none"
                @input="onSearchInput"
                @keydown.enter.prevent
            />
            <!-- Leaflet's own panes/controls use z-index up to 1000, so this
                 has to clear that or the map swallows the dropdown. -->
            <ul
                v-if="results.length"
                class="border-edge bg-surface-raised absolute z-[1001] mt-1 w-full border"
            >
                <li v-for="result in results" :key="result.display_name">
                    <button
                        type="button"
                        class="text-xs block w-full px-3 py-2 text-left hover:bg-accent hover:text-on-accent"
                        @click="selectResult(result)"
                    >
                        {{ result.display_name }}
                    </button>
                </li>
            </ul>
            <p v-else-if="searching" class="text-xs mt-1 text-ink/70">
                {{ t('location.searching') }}
            </p>
            <p v-else-if="error" class="text-xs mt-1 text-ink/70">
                {{ t('location.searchError') }}
            </p>
            <p
                v-else-if="
                    !picked && searchQuery.trim().length >= MIN_QUERY_LENGTH
                "
                class="text-xs mt-1 text-ink/70"
            >
                {{ t('location.noResults') }}
            </p>
        </div>

        <div ref="mapContainer" class="border-edge h-64 w-full border"></div>

        <div class="text-xs mt-2 flex items-center gap-3">
            <span v-if="latitude !== null && longitude !== null">
                {{ [city, country].filter(Boolean).join(', ') }}
            </span>
            <button
                v-if="latitude !== null || longitude !== null"
                type="button"
                class="underline"
                @click="clearLocation"
            >
                {{ t('location.clear') }}
            </button>
        </div>
    </div>
</template>
