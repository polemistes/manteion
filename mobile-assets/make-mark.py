#!/usr/bin/env python3
"""Make the mark and every icon from the drawing of the tripod.

    python3 mobile-assets/make-mark.py ~/manteion-logo/logo_bilde2.png

The drawing is ink and paint on white paper (2368x3158 when this was
written). It is cut out of the paper, and from the cut-out come:

    resources/images/mark.webp        the mark in the pages (Mark.vue)
    resources/images/mark-dark.webp   the same with a light outline, for dark mode
    public/favicon.png, favicon.ico   the browser tab: the top of the tripod only
    public/icons/*.png                home-screen icons and the notification badge
    public/apple-touch-icon.png
    public/offline.html               its inline logo (and the app's own
                                      offline page, resources/native-shell)
    mobile-assets/logo.png            the cut-out itself, 1024 tall
    android/app/src/main/res/         launcher icons, the status-bar icon, splash screens
    ios/App/App/Assets.xcassets/      the app icon and splash screens

Run `npm run build` afterwards. Needs Pillow, numpy and scipy. New app
icons reach phones only with a new app release (android/app/build.gradle,
then a tag android-v<versionName>).
"""
import base64
import io
import pathlib
import re
import sys

import numpy as np
from PIL import Image
from scipy import ndimage as ndi

ROOT = pathlib.Path(__file__).resolve().parent.parent
WHITE, CANVAS = (255, 255, 255, 255), (247, 243, 236, 255)


def cut_out(path: str) -> Image.Image:
    """The drawing without its paper: the paper around it, the see-through
    holes (inside the rings and the horses) and the specks the brush left
    unpainted all become transparent."""
    rgb = np.asarray(Image.open(path).convert('RGB')).astype(np.float32)
    height, width, _ = rgb.shape
    lightest = rgb.min(axis=2)

    paper = lightest > 232
    labels, count = ndi.label(paper)
    border = np.unique(np.concatenate([labels[0], labels[-1], labels[:, 0], labels[:, -1]]))
    border = border[border != 0]
    outside = np.isin(labels, border)

    # Holes drawn on purpose are large and sit above the bowl; the rest of
    # the enclosed paper is specks.
    index = np.arange(1, count + 1)
    sizes = ndi.sum(paper, labels, index=index)
    centres = ndi.center_of_mass(paper, labels, index=index)
    holes = np.zeros_like(paper)
    for label, size, centre in zip(index, sizes, centres):
        if label not in border and size > 0.0001 * width * height and centre[0] < 0.24 * height:
            holes |= labels == label
    specks = paper & ~outside & ~holes

    # Along the edge, how much paper shows through decides the alpha, and
    # the paper's white is taken back out of the colour.
    clear = outside | holes
    band = ndi.binary_dilation(clear, iterations=4) & ~clear
    alpha = np.ones((height, width), np.float32)
    alpha[clear] = 0
    alpha[band] = 1 - np.clip((lightest - 60) / (232 - 60), 0, 1)[band]
    alpha[specks] = 0
    a = np.clip(alpha, 1e-3, 1)[..., None]
    colour = np.where(band[..., None], np.clip((rgb - (1 - a) * 255) / a, 0, 255), rgb)

    image = Image.fromarray(np.dstack([colour, alpha * 255]).astype(np.uint8), 'RGBA')

    return image.crop(image.getchannel('A').point(lambda v: 255 if v > 8 else 0).getbbox())


def light_ink(image: Image.Image) -> Image.Image:
    """The drawing with its ink (what is dark and colourless) drawn light:
    on a dark page the black outline would vanish and take the shapes
    with it."""
    pixels = np.asarray(image).astype(np.float32)
    value = pixels[..., :3].max(axis=2)
    chroma = value - pixels[..., :3].min(axis=2)
    ink = np.clip((140 - value) / 80, 0, 1) * np.clip((70 - chroma) / 40, 0, 1)
    pixels[..., :3] = pixels[..., :3] * (1 - ink[..., None]) + np.array([236, 228, 214], np.float32) * ink[..., None]

    return Image.fromarray(pixels.astype(np.uint8), 'RGBA')


def fit(image: Image.Image, width: int, height: int) -> Image.Image:
    copy = image.copy()
    copy.thumbnail((width, height), Image.LANCZOS)

    return copy


def on_square(image: Image.Image, size: int, background: tuple, fill: float) -> Image.Image:
    """The image centred on a square, taking `fill` of its side at most."""
    square = Image.new('RGBA', (size, size), background)
    fitted = fit(image, round(size * fill), round(size * fill))
    square.alpha_composite(fitted, ((size - fitted.width) // 2, (size - fitted.height) // 2))

    return square


def silhouette(image: Image.Image, size: int, fill: float, grow: int) -> Image.Image:
    """The shape of the drawing in white on nothing, on a square: what a
    status bar draws. `grow` thickens it first (in pixels of the full
    drawing), or its legs would be thinner than a pixel."""
    alpha = np.asarray(image.getchannel('A')) > 96
    alpha = np.pad(alpha, grow)
    alpha = ndi.binary_dilation(alpha, structure=ndi.generate_binary_structure(2, 1), iterations=grow)
    shape = fit(Image.fromarray((alpha * 255).astype(np.uint8), 'L'), round(size * fill), round(size * fill))
    square = np.zeros((size, size, 4), np.uint8)
    square[..., :3] = 255
    y, x = (size - shape.height) // 2, (size - shape.width) // 2
    square[y:y + shape.height, x:x + shape.width, 3] = np.asarray(shape)

    return Image.fromarray(square, 'RGBA')


def splash(image: Image.Image, width: int, height: int) -> Image.Image:
    """The drawing in the middle of a white screen."""
    screen = Image.new('RGBA', (width, height), WHITE)
    tall = round(min(0.2 * max(width, height), 0.36 * min(width, height)))
    fitted = fit(image, tall, tall)
    screen.alpha_composite(fitted, ((width - fitted.width) // 2, (height - fitted.height) // 2))

    return screen


def app_assets(master: Image.Image) -> None:
    """The Android and iOS apps' pictures, in the sizes each already had."""
    fit(master, 1024, 1024).save(ROOT / 'mobile-assets/logo.png', optimize=True)

    res = ROOT / 'android/app/src/main/res'
    for density, launcher in (('ldpi', 36), ('mdpi', 48), ('hdpi', 72), ('xhdpi', 96), ('xxhdpi', 144), ('xxxhdpi', 192)):
        folder = res / f'mipmap-{density}'
        on_square(master, launcher, WHITE, 0.82).save(folder / 'ic_launcher.png', optimize=True)
        # The round icon: the same, inside a white disc.
        big = launcher * 4
        distance = np.hypot(*(np.mgrid[:big, :big] - (big - 1) / 2))
        mask = Image.fromarray((distance <= big / 2).astype(np.uint8) * 255, 'L').resize((launcher, launcher), Image.LANCZOS)
        round_icon = on_square(master, launcher, WHITE, 0.74)
        round_icon.putalpha(mask)
        round_icon.save(folder / 'ic_launcher_round.png', optimize=True)
        # Adaptive icon (mipmap-anydpi-v26): a layer is one and a half
        # times the icon, the launcher cuts its own shape out of the
        # middle, and only the middle 61% is sure to survive every shape.
        layer = launcher * 9 // 4
        on_square(master, layer, (0, 0, 0, 0), 0.5).save(folder / 'ic_launcher_foreground.png', optimize=True)

    for density, size in (('mdpi', 24), ('hdpi', 36), ('xhdpi', 48), ('xxhdpi', 72), ('xxxhdpi', 96)):
        silhouette(master, size, 0.92, 26).save(res / f'drawable-{density}/ic_stat_manteion.png', optimize=True)

    for path in sorted(res.glob('drawable*/splash.png')):
        with Image.open(path) as old:
            width, height = old.size
        splash(master, width, height).save(path, optimize=True)

    ios = ROOT / 'ios/App/App/Assets.xcassets'
    on_square(master, 1024, WHITE, 0.8).convert('RGB').save(ios / 'AppIcon.appiconset/AppIcon-512@2x.png', optimize=True)
    for path in sorted((ios / 'Splash.imageset').glob('*.png')):
        with Image.open(path) as old:
            width, height, mode = *old.size, old.mode
        splash(master, width, height).convert(mode).save(path, optimize=True)


def main(source: str) -> None:
    master = cut_out(source)
    images, public = ROOT / 'resources/images', ROOT / 'public'
    images.mkdir(exist_ok=True)

    # 480 tall is three times its largest size on a phone.
    size = (round(master.width * 480 / master.height), 480)
    webp = {'quality': 92, 'method': 6, 'alpha_quality': 100}
    master.resize(size, Image.LANCZOS).save(images / 'mark.webp', 'WEBP', **webp)
    light_ink(master).resize(size, Image.LANCZOS).save(images / 'mark-dark.webp', 'WEBP', **webp)
    print(f'mark: {size[0]}x{size[1]} (Mark.vue states this size and shape)')

    # A browser tab is 16 to 32 pixels: the whole tripod would be a smudge
    # there, so the tab gets its top, the bowl with its three handles.
    top = master.crop((0, 0, master.width, master.width))
    favicon = on_square(top, 192, WHITE, 0.9)
    favicon.convert('RGB').save(public / 'favicon.png', optimize=True)
    favicon.save(public / 'favicon.ico', sizes=[(16, 16), (32, 32), (48, 48)])

    # Home-screen icons are large enough for the whole tripod.
    on_square(master, 192, WHITE, 0.86).convert('RGB').save(public / 'icons/icon-192.png', optimize=True)
    on_square(master, 512, WHITE, 0.86).convert('RGB').save(public / 'icons/icon-512.png', optimize=True)
    # Maskable: whatever shape the launcher cuts, the middle 80% survives.
    on_square(master, 512, CANVAS, 0.62).convert('RGB').save(public / 'icons/maskable-512.png', optimize=True)
    touch = on_square(master, 180, WHITE, 0.84).convert('RGB')
    touch.save(public / 'icons/apple-touch-icon-180.png', optimize=True)
    touch.save(public / 'apple-touch-icon.png', optimize=True)

    # The notification badge is a white shape: Android draws only its alpha.
    shape = fit(master, 88, 88)
    badge = np.zeros((96, 96, 2), np.uint8)
    badge[..., 0] = 255
    y, x = (96 - shape.height) // 2, (96 - shape.width) // 2
    badge[y:y + shape.height, x:x + shape.width, 1] = np.asarray(shape.getchannel('A'))
    Image.fromarray(badge, 'LA').save(public / 'icons/badge-96.png', optimize=True)

    # The offline pages (the site's, and the one built into the app) stand
    # alone, so their logo is inline.
    buffer = io.BytesIO()
    master.resize((round(master.width * 288 / master.height), 288), Image.LANCZOS).save(buffer, 'PNG', optimize=True)
    inline = 'src="data:image/png;base64,' + base64.b64encode(buffer.getvalue()).decode() + '"'
    for page in (public / 'offline.html', ROOT / 'resources/native-shell/index.html'):
        html, replaced = re.subn(r'src="data:image/png;base64,[^"]+"', inline, page.read_text())
        assert replaced == 1, f'{page.name} should hold exactly one inline picture'
        page.write_text(html)

    app_assets(master)


if __name__ == '__main__':
    if len(sys.argv) != 2:
        sys.exit(__doc__)
    main(sys.argv[1])
