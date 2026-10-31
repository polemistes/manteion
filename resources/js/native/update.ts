import { App } from '@capacitor/app';
import { usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import type { ComputedRef, Ref } from 'vue';
import { isNativeShell } from '@/pwa/platform';
import { isNewerVersion, latestAndroidRelease } from './release';

/**
 * Inside the Android app: the version installed, the newest one on GitHub,
 * and whether that is newer. Asks when the component mounts; on the web
 * everything stays null. `download()` hands the newest build to the
 * browser, which downloads it (another site: the app opens it outside).
 */
export function useAndroidAppUpdate(): {
    inApp: Ref<boolean>;
    installed: Ref<string | null>;
    latest: Ref<string | null>;
    checked: Ref<boolean>;
    updateAvailable: ComputedRef<boolean>;
    download: () => void;
} {
    const page = usePage();
    const inApp = ref(false);
    const installed = ref<string | null>(null);
    const latest = ref<string | null>(null);
    const checked = ref(false);

    const updateAvailable = computed(
        () =>
            installed.value !== null &&
            latest.value !== null &&
            isNewerVersion(latest.value, installed.value),
    );

    onMounted(async () => {
        inApp.value = isNativeShell();

        if (!inApp.value) {
            return;
        }

        const [info, release] = await Promise.all([
            App.getInfo().catch(() => null),
            latestAndroidRelease(page.props.androidApp.latestReleaseApi),
        ]);

        installed.value = info?.version ?? null;
        latest.value = release?.version ?? null;
        checked.value = true;
    });

    function download(): void {
        window.location.assign(page.props.androidApp.downloadUrl);
    }

    return { inApp, installed, latest, checked, updateAvailable, download };
}
