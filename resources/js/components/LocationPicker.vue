<script setup lang="ts">
import { LocateFixed, MapPin, MapPinned, Search } from '@lucide/vue';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import markerIcon2x from 'leaflet/dist/images/marker-icon-2x.png';
import markerIcon from 'leaflet/dist/images/marker-icon.png';
import markerShadow from 'leaflet/dist/images/marker-shadow.png';
import {
    computed,
    nextTick,
    onUnmounted,
    ref,
    useTemplateRef,
    watch,
} from 'vue';
import { useI18n } from 'vue-i18n';
import TextField from '@/components/ui/TextField.vue';
import {
    cityFromAddress,
    countryCodeFromAddress,
    countryFromAddress,
    MIN_QUERY_LENGTH,
    reverseGeocode,
    useNominatimSearch,
} from '@/composables/useNominatimSearch';
import type { NominatimResult } from '@/composables/useNominatimSearch';
import { usePlaces } from '@/lib/places';

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

export interface LocationValue {
    latitude: number | null;
    longitude: number | null;
    city: string | null;
    country: string | null;
    country_code: string | null;
}

/**
 * Where an event is: one field for what the place is called, and under it
 * the ways to put it on the map. The two are separate things. The name is
 * whatever the host types ("My place", a venue the map has never heard
 * of) and is never replaced. The pin comes from one of three: finding the
 * name on the map, searching for something else (usually the address), or
 * marking the spot by hand. The map shows only once one of them has been
 * asked for, and nothing typed is sent to the map's search until then.
 *
 * `v-model:name` is the name (submitted as `place`); the pin and the town
 * found for it are submitted as hidden fields and reported by `change`.
 */
const props = withDefaults(
    defineProps<{
        initialValue?: LocationValue | null;
        label: string;
        placeholder?: string;
        error?: string;
    }>(),
    { initialValue: null, placeholder: undefined, error: undefined },
);

const emit = defineEmits<{ change: [value: LocationValue] }>();

const name = defineModel<string>('name', { default: '' });

const { t, locale } = useI18n();
const { cityAndCountry } = usePlaces();

const latitude = ref<number | null>(props.initialValue?.latitude ?? null);
const longitude = ref<number | null>(props.initialValue?.longitude ?? null);
const city = ref<string | null>(props.initialValue?.city ?? null);
const country = ref<string | null>(props.initialValue?.country ?? null);
const countryCode = ref<string | null>(
    props.initialValue?.country_code ?? null,
);

const hasPin = computed(
    () => latitude.value !== null && longitude.value !== null,
);

watch([latitude, longitude, city, country, countryCode], () =>
    emit('change', {
        latitude: latitude.value,
        longitude: longitude.value,
        city: city.value,
        country: country.value,
        country_code: countryCode.value,
    }),
);

// The search box for something other than the name (an address).
const searchOpen = ref(false);
const searchQuery = ref('');
// What the results on show were found for, to say so when there are none.
const searchedFor = ref('');
const searchInput = useTemplateRef<HTMLInputElement>('searchInput');

const {
    results,
    searching,
    error: searchFailed,
    search,
    searchNow,
    clearResults,
} = useNominatimSearch(() => locale.value);

const canFindName = computed(
    () => name.value.trim().length >= MIN_QUERY_LENGTH,
);

function findName(): void {
    searchOpen.value = false;
    searchedFor.value = name.value.trim();
    searchNow(searchedFor.value);
}

async function openSearch(): Promise<void> {
    searchOpen.value = true;
    clearResults();
    searchedFor.value = '';
    await nextTick();
    searchInput.value?.focus();
}

function onSearchInput(event: Event): void {
    searchQuery.value = (event.target as HTMLInputElement).value;
    searchedFor.value = searchQuery.value.trim();
    search(searchQuery.value);
}

// The map: there once a pin exists or the host asks to mark the spot.
const mapOpen = ref(hasPin.value);
const mapContainer = useTemplateRef<HTMLDivElement>('mapContainer');
let map: L.Map | null = null;
let marker: L.Marker | null = null;

// The map sits in a page that scrolls, so it must not take the scrolling
// for itself. With a mouse the wheel scrolls the page unless Ctrl (⌘ on a
// Mac) is held, which is also what a trackpad pinch sends; a hint says so.
// On a touch screen one finger scrolls the page and two move the map.
// The wheel is caught on the box around the map, on its way down to it:
// Leaflet's own listener stays on (so it can stop the browser from zooming
// the whole page on Ctrl + wheel) and simply never hears the plain wheel.
const wheelHint = ref(false);
const zoomKey = ref('Ctrl');
let wheelHintTimer: ReturnType<typeof setTimeout> | undefined;

function onWheel(event: WheelEvent): void {
    if (event.ctrlKey || event.metaKey) {
        wheelHint.value = false;

        return;
    }

    event.stopPropagation();
    wheelHint.value = true;
    clearTimeout(wheelHintTimer);
    wheelHintTimer = setTimeout(() => (wheelHint.value = false), 1600);
}

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

/** Starts the map in its box, which must be on the page (see the maps rule). */
function startMap(): void {
    if (map || !mapContainer.value) {
        return;
    }

    const touchScreen = window.matchMedia('(pointer: coarse)').matches;

    zoomKey.value = /Mac|iPhone|iPad/.test(navigator.userAgent) ? '⌘' : 'Ctrl';

    // Zoom buttons and the credit in the user's language: Leaflet's own are
    // English ("Zoom in", "A JavaScript library for interactive maps").
    map = L.map(mapContainer.value, {
        zoomControl: false,
        // One finger is for the page; two fingers pinch and move the map.
        dragging: !touchScreen,
    }).setView(
        hasPin.value ? [latitude.value!, longitude.value!] : DEFAULT_CENTER,
        hasPin.value ? PIN_ZOOM : DEFAULT_ZOOM,
    );
    L.control
        .zoom({
            zoomInTitle: t('location.zoomIn'),
            zoomOutTitle: t('location.zoomOut'),
        })
        .addTo(map);
    map.attributionControl.setPrefix(
        '<a href="https://leafletjs.com">Leaflet</a>',
    );

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors',
        maxZoom: 19,
    }).addTo(map);

    if (hasPin.value) {
        placeMarker(latitude.value!, longitude.value!);
    }

    map.on('click', (event: L.LeafletMouseEvent) => {
        void applyCoordinates(event.latlng.lat, event.latlng.lng);
    });
}

// Once its box is on the page: at once for an event that has a pin, later
// when the host asks for the map.
watch(
    mapOpen,
    async (open) => {
        if (open) {
            await nextTick();
            startMap();
        }
    },
    { immediate: true, flush: 'post' },
);

async function applyCoordinates(lat: number, lng: number): Promise<void> {
    latitude.value = lat;
    longitude.value = lng;
    // The old pin's place is not this one's: without an answer below, the
    // pin has no city rather than the wrong one.
    city.value = null;
    country.value = null;
    countryCode.value = null;
    placeMarker(lat, lng);

    const result = await reverseGeocode(lat, lng, locale.value);

    if (result) {
        city.value = cityFromAddress(result.address);
        country.value = countryFromAddress(result.address);
        countryCode.value = countryCodeFromAddress(result.address);
    }
}

async function selectResult(result: NominatimResult): Promise<void> {
    const lat = parseFloat(result.lat);
    const lng = parseFloat(result.lon);

    latitude.value = lat;
    longitude.value = lng;
    city.value = cityFromAddress(result.address);
    country.value = countryFromAddress(result.address);
    countryCode.value = countryCodeFromAddress(result.address);
    clearResults();
    searchedFor.value = '';
    searchOpen.value = false;
    searchQuery.value = '';

    // A place found with nothing typed yet names itself; a name the host
    // gave is theirs.
    if (name.value.trim() === '') {
        name.value = (
            result.name?.trim() || result.display_name.split(',')[0]
        ).slice(0, 255);
    }

    mapOpen.value = true;
    await nextTick();
    startMap();
    placeMarker(lat, lng);
    map?.setView([lat, lng], PIN_ZOOM);
}

function markByHand(): void {
    searchOpen.value = false;
    clearResults();
    searchedFor.value = '';
    mapOpen.value = true;
}

function clearLocation(): void {
    latitude.value = null;
    longitude.value = null;
    city.value = null;
    country.value = null;
    countryCode.value = null;
    clearResults();
    searchedFor.value = '';

    if (marker) {
        marker.remove();
        marker = null;
    }

    map?.remove();
    map = null;
    mapOpen.value = false;
}

onUnmounted(() => {
    clearTimeout(wheelHintTimer);
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
        <input type="hidden" name="country_code" :value="countryCode ?? ''" />

        <TextField
            v-model="name"
            name="place"
            :label="props.label"
            :placeholder="props.placeholder"
            :error="props.error"
            optional
            maxlength="255"
        />

        <!-- The ways to put it on the map (or to move the pin). -->
        <div class="flex flex-wrap gap-x-5 gap-y-1 text-label">
            <button
                v-if="canFindName"
                type="button"
                class="inline-flex min-h-11 items-center gap-1.5 text-left font-semibold text-ink underline underline-offset-2"
                @click="findName"
            >
                <MapPin
                    :size="16"
                    :stroke-width="2"
                    class="shrink-0"
                    aria-hidden="true"
                />
                <span class="line-clamp-1">{{
                    t('location.findName', { name: name.trim() })
                }}</span>
            </button>
            <button
                type="button"
                class="inline-flex min-h-11 items-center gap-1.5 font-semibold text-ink underline underline-offset-2"
                @click="openSearch"
            >
                <Search
                    :size="16"
                    :stroke-width="2"
                    class="shrink-0"
                    aria-hidden="true"
                />
                {{ t('location.searchAddress') }}
            </button>
            <button
                v-if="!mapOpen"
                type="button"
                class="inline-flex min-h-11 items-center gap-1.5 font-semibold text-ink underline underline-offset-2"
                @click="markByHand"
            >
                <MapPinned
                    :size="16"
                    :stroke-width="2"
                    class="shrink-0"
                    aria-hidden="true"
                />
                {{ t('location.markByHand') }}
            </button>
        </div>

        <div v-if="searchOpen" class="relative">
            <Search
                :size="18"
                :stroke-width="1.9"
                class="pointer-events-none absolute top-1/2 left-3.5 -translate-y-1/2 text-ink-muted"
                aria-hidden="true"
            />
            <input
                ref="searchInput"
                type="search"
                :value="searchQuery"
                :aria-label="t('location.searchAddress')"
                :placeholder="t('location.searchPlaceholder')"
                class="h-12 w-full rounded-md bg-surface pr-3.5 pl-10 text-input text-ink ring-1 ring-control ring-inset hover:ring-ink-subtle focus:ring-2 focus:ring-ink focus:outline-none [&::-webkit-search-cancel-button]:hidden"
                @input="onSearchInput"
                @keydown.enter.prevent
            />
        </div>

        <ul
            v-if="results.length"
            class="overflow-hidden rounded-md bg-surface ring-1 ring-line"
        >
            <li
                v-for="result in results"
                :key="result.display_name"
                class="border-b border-line last:border-b-0"
            >
                <button
                    type="button"
                    class="flex min-h-11 w-full items-center px-3.5 py-2 text-left text-label hover:bg-surface-2"
                    @click="selectResult(result)"
                >
                    <span class="line-clamp-2">{{ result.display_name }}</span>
                </button>
            </li>
        </ul>
        <p v-else-if="searching" class="text-meta text-ink-muted" role="status">
            {{ t('location.searching') }}
        </p>
        <p v-else-if="searchFailed" class="text-meta text-danger" role="status">
            {{ t('location.searchError') }}
        </p>
        <p
            v-else-if="searchedFor.length >= MIN_QUERY_LENGTH"
            class="text-meta text-ink-muted"
            role="status"
        >
            {{ t('location.notFound', { name: searchedFor }) }}
        </p>

        <!-- isolate: Leaflet's panes and controls (z-index up to 1000) stay
             inside the card instead of painting over the bars and sheets. -->
        <div
            v-if="mapOpen"
            class="isolate overflow-hidden rounded-lg border border-line bg-surface"
        >
            <div class="relative" @wheel.capture="onWheel">
                <div
                    ref="mapContainer"
                    class="map-tiles h-[180px] w-full bg-surface-2"
                ></div>
                <p
                    v-if="wheelHint"
                    class="pointer-events-none absolute inset-x-0 bottom-3 z-[1000] mx-auto w-fit rounded-full bg-ink px-3 py-1 text-meta font-medium text-on-ink shadow-sm"
                    aria-hidden="true"
                >
                    {{ t('location.wheelHint', { key: zoomKey }) }}
                </p>
            </div>
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
                    <template v-if="hasPin">{{
                        cityAndCountry(city, country, countryCode) ||
                        t('location.pinPlaced')
                    }}</template>
                    <template v-else>{{ t('location.tapToPlace') }}</template>
                    <span class="hidden pointer-coarse:inline">
                        {{ t('location.twoFingers') }}
                    </span>
                </span>
                <button
                    type="button"
                    class="min-h-11 shrink-0 font-semibold text-ink underline underline-offset-2"
                    @click="clearLocation"
                >
                    {{ hasPin ? t('location.clear') : t('location.hideMap') }}
                </button>
            </div>
        </div>
    </div>
</template>
