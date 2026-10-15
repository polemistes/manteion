import { watch } from 'vue';
import { setAppBadge } from '@/pwa/serviceWorker';
import { badgeTotal } from './badges';

/**
 * The outside of the tab says only that something is waiting: a red dot on
 * the favicon, and the installed app's icon badge. The title is left alone
 * on purpose (no "(3)" in front, no sender alternating with it): a tab
 * whose text keeps changing is distracting, and the owner asked for
 * nothing in the tab to move.
 */

/** Provisional brand accent for the favicon dot. */
const DOT_COLOR = '#C23132';

type Icon = { link: HTMLLinkElement; href: string; type: string | null };

let started = false;
let count = 0;
let icons: Icon[] | null = null;
let dottedIcon: Promise<string | null> | null = null;
let showingDot = false;

function faviconLinks(): Icon[] {
    icons ??= Array.from(
        document.querySelectorAll<HTMLLinkElement>('link[rel~="icon"]'),
    ).map((link) => ({
        link,
        href: link.href,
        type: link.getAttribute('type'),
    }));

    return icons;
}

/** The PNG favicon with a dot in its top right corner, as a data URL. */
function drawDottedIcon(): Promise<string | null> {
    dottedIcon ??= new Promise((resolve) => {
        const image = new Image();

        image.onload = () => {
            try {
                const size = 64;
                const radius = 11;
                const x = size - radius - 2;
                const y = radius + 2;
                const canvas = document.createElement('canvas');
                const context = canvas.getContext('2d');

                canvas.width = size;
                canvas.height = size;

                if (!context) {
                    resolve(null);

                    return;
                }

                context.drawImage(image, 0, 0, size, size);
                context.beginPath();
                context.arc(x, y, radius + 3, 0, 2 * Math.PI);
                context.fillStyle = '#FFFFFF';
                context.fill();
                context.beginPath();
                context.arc(x, y, radius, 0, 2 * Math.PI);
                context.fillStyle = DOT_COLOR;
                context.fill();

                resolve(canvas.toDataURL('image/png'));
            } catch {
                resolve(null);
            }
        };
        image.onerror = () => resolve(null);
        image.src =
            faviconLinks().find((icon) => icon.type === 'image/png')?.href ??
            '/favicon.png';
    });

    return dottedIcon;
}

function renderFavicon(): void {
    const wanted = count > 0;

    if (wanted === showingDot) {
        return;
    }

    showingDot = wanted;

    if (!wanted) {
        faviconLinks().forEach((icon) => {
            icon.link.href = icon.href;

            if (icon.type) {
                icon.link.type = icon.type;
            } else {
                icon.link.removeAttribute('type');
            }
        });

        return;
    }

    void drawDottedIcon().then((url) => {
        if (!url || !showingDot) {
            return;
        }

        faviconLinks().forEach((icon) => {
            icon.link.href = url;
            icon.link.type = 'image/png';
        });
    });
}

/**
 * Keeps the favicon dot and the app badge in step with the badge total.
 * Client only, once.
 */
export function setupAttention(): void {
    if (typeof window === 'undefined' || started) {
        return;
    }

    started = true;

    watch(
        badgeTotal,
        (total) => {
            count = total;
            renderFavicon();
            setAppBadge(total);
        },
        { immediate: true },
    );
}
