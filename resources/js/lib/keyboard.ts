/**
 * Tells the page when the on-screen keyboard is up, which a page cannot ask
 * directly. It shows in the visual viewport: with a text field in focus,
 * the viewport is much lower than the tallest it has been at this width.
 * That holds both where the keyboard shrinks the page (the Android app,
 * Firefox) and where it slides over it (Chrome and Safari on phones).
 *
 * While it is up, <html> carries data-keyboard="open", for the `keyboard:`
 * variant (app.css): bars pinned to the bottom of the screen hide, since
 * where the page shrinks they would ride on top of the keyboard, over the
 * very field being typed in (seen in the Android app: half the description
 * field behind "Create event"). `--keyboard-inset` is how much of the
 * page's bottom the keyboard covers where it slides over, for sheets
 * anchored there; it is 0 where the page shrinks instead. And the field in
 * focus is brought into what is left of the screen if it is not all there.
 */
const OPEN_THRESHOLD = 150;

function isTyping(element: Element | null): element is HTMLElement {
    if (!(element instanceof HTMLElement)) {
        return false;
    }

    if (element instanceof HTMLTextAreaElement || element.isContentEditable) {
        return true;
    }

    return (
        element instanceof HTMLInputElement &&
        ![
            'button',
            'checkbox',
            'color',
            'file',
            'hidden',
            'image',
            'radio',
            'range',
            'reset',
            'submit',
        ].includes(element.type)
    );
}

export function setupKeyboard(): void {
    const visual = window.visualViewport;

    if (!visual) {
        return;
    }

    const viewport: VisualViewport = visual;

    const root = document.documentElement;
    let width = window.innerWidth;
    let tallest = 0;
    let open = false;
    let settle: ReturnType<typeof setTimeout> | undefined;

    /** Scrolls the field in focus into the part of the screen still free. */
    function reveal(): void {
        // A frame later: the bars have gone and the page has its new size.
        requestAnimationFrame(() => {
            const field = document.activeElement;

            if (!open || !isTyping(field)) {
                return;
            }

            const box = field.getBoundingClientRect();
            const top =
                viewport.offsetTop +
                parseFloat(getComputedStyle(root).scrollPaddingTop || '0');
            const bottom = viewport.offsetTop + viewport.height - 12;

            if (box.top >= top && box.bottom <= bottom) {
                return;
            }

            field.scrollIntoView({
                block: box.height > bottom - top ? 'start' : 'center',
                behavior: 'smooth',
            });
        });
    }

    function update(): void {
        // Turned on its side (not the pixel or two a resize may wobble):
        // what counted as tall no longer does.
        if (Math.abs(window.innerWidth - width) > 50) {
            width = window.innerWidth;
            tallest = 0;
        }

        // In page pixels whatever the pinch zoom.
        const height = viewport.height * viewport.scale;

        tallest = Math.max(tallest, height);

        const typing = isTyping(document.activeElement);

        // With no field in focus for a moment there is no keyboard, so
        // the height now is the full one: a window made lower (split
        // screen, a tiled window) must not pass for a keyboard later.
        clearTimeout(settle);

        if (!typing) {
            settle = setTimeout(() => {
                if (!isTyping(document.activeElement)) {
                    tallest = viewport.height * viewport.scale;
                }
            }, 600);
        }

        const isOpen = typing && tallest - height > OPEN_THRESHOLD;
        const covered = isOpen
            ? Math.max(
                  0,
                  Math.round(
                      window.innerHeight - viewport.offsetTop - viewport.height,
                  ),
              )
            : 0;

        root.style.setProperty('--keyboard-inset', `${covered}px`);

        if (isOpen === open) {
            return;
        }

        open = isOpen;

        if (open) {
            root.dataset.keyboard = 'open';
            reveal();
        } else {
            delete root.dataset.keyboard;
        }
    }

    viewport.addEventListener('resize', update);
    viewport.addEventListener('scroll', update);
    document.addEventListener('focusin', () => {
        update();

        // Moving to another field with the keyboard already up.
        if (open) {
            reveal();
        }
    });
    // Once the focus has landed wherever it was going.
    document.addEventListener('focusout', () => setTimeout(update, 0));

    update();
}
