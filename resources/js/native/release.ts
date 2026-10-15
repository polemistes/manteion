/**
 * The newest Android app on GitHub (the mobile-android workflow publishes
 * each signed build as a release tagged android-v<version>), for the
 * download page and the app's update notice.
 */
export type AndroidRelease = { version: string };

/** The newest release, or null when GitHub can't say right now. */
export async function latestAndroidRelease(
    api: string,
): Promise<AndroidRelease | null> {
    try {
        const response = await fetch(api, {
            headers: { Accept: 'application/vnd.github+json' },
        });

        if (!response.ok) {
            return null;
        }

        const release = (await response.json()) as { tag_name?: unknown };
        const tag =
            typeof release.tag_name === 'string' ? release.tag_name : '';
        const version = tag.replace(/^android-v/, '');

        return /^\d+(\.\d+)*$/.test(version) ? { version } : null;
    } catch {
        return null;
    }
}

/** Whether version `a` ("1.10") is newer than `b` ("1.9"), part by part. */
export function isNewerVersion(a: string, b: string): boolean {
    const left = a.split('.').map(Number);
    const right = b.split('.').map(Number);

    for (let i = 0; i < Math.max(left.length, right.length); i++) {
        const difference = (left[i] ?? 0) - (right[i] ?? 0);

        if (difference !== 0) {
            return difference > 0;
        }
    }

    return false;
}
