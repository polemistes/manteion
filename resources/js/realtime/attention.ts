import { watch } from 'vue';
import { setAppBadge } from '@/pwa/serviceWorker';
import type { IncomingMessage } from '@/types';
import { onIncomingMessage } from './activity';
import { badgeTotal } from './badges';

/** The "(3) " this module puts in front of the page's own title. */
const COUNT_PREFIX = /^\(\d+\+?\) /;

/** How long each of the two titles shows while a hidden tab calls for attention. */
const ALTERNATE_MS = 1500;

/** Provisional brand accent for the favicon dot. */
const DOT_COLOR = '#C23132';

type Icon = { link: HTMLLinkElement; href: string; type: string | null };

let started = false;
let count = 0;
let baseTitle = '';
let writtenTitle = '';
let alertText: string | null = null;
let showingAlert = false;
let alertTimer: ReturnType<typeof setInterval> | undefined;
let icons: Icon[] | null = null;
let dottedIcon: Promise<string | null> | null = null;
let showingDot = false;

/** What document.title reads back: whitespace collapsed and trimmed. */
function asTitle(text: string): string {
    return text.replace(/\s+/g, ' ').trim();
}

function countedTitle(): string {
    return count > 0
        ? `(${count > 99 ? '99+' : count}) ${baseTitle}`
        : baseTitle;
}

function renderTitle(): void {
    const title = asTitle(
        showingAlert && alertText ? alertText : countedTitle(),
    );

    writtenTitle = title;

    if (document.title !== title) {
        document.title = title;
    }
}

/**
 * Inertia replaces the <title> on every visit; take the new one as the base
 * and put the count back in front of it.
 */
function onHeadChange(): void {
    const current = document.title;

    if (current === writtenTitle) {
        return;
    }

    baseTitle = current.replace(COUNT_PREFIX, '');
    renderTitle();
}

function alertFor(message: IncomingMessage): string {
    const sender =
        message.conversation_type === 'group'
            ? `${message.sender.name} (${message.conversation_title})`
            : message.sender.name;
    const text = asTitle(`${sender}: ${message.preview}`);

    return text.length > 60 ? `${text.slice(0, 59)}…` : text;
}

function startAlert(text: string): void {
    alertText = text;
    showingAlert = true;
    renderTitle();

    alertTimer ??= setInterval(() => {
        showingAlert = !showingAlert;
        renderTitle();
    }, ALTERNATE_MS);
}

function stopAlert(): void {
    clearInterval(alertTimer);
    alertTimer = undefined;
    alertText = null;
    showingAlert = false;
    renderTitle();
}

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
 * Keeps the outside of the tab in step with the badge total: "(n) " in front
 * of the title, a dot on the favicon and the installed app's icon badge
 * while n > 0. While the tab is hidden, an incoming message makes the title
 * alternate with "{sender}: {preview}" until the tab is shown again.
 * Client only, once.
 */
export function setupAttention(): void {
    if (typeof window === 'undefined' || started) {
        return;
    }

    started = true;
    baseTitle = document.title.replace(COUNT_PREFIX, '');
    writtenTitle = document.title;

    new MutationObserver(onHeadChange).observe(document.head, {
        childList: true,
        subtree: true,
        characterData: true,
    });

    watch(
        badgeTotal,
        (total) => {
            count = total;
            renderTitle();
            renderFavicon();
            setAppBadge(total);
        },
        { immediate: true },
    );

    onIncomingMessage((message) => {
        if (document.visibilityState === 'hidden') {
            startAlert(alertFor(message));
        }
    });

    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible' && alertText !== null) {
            stopAlert();
        }
    });
}
