"""Builds Sugar Orchard's game art (public/images/sugar-orchard) from the delivered sources.

    python -I scripts/art/process-sugar-orchard.py              (Python 3 with Pillow; no numpy)
    python -I scripts/art/process-sugar-orchard.py --out DIR    (writes elsewhere, to compare)

The sources are the generated images in storage/app/art/sugar-orchard (not in git), named as
in docs/art/sugar-orchard-prompts.md. Cut-outs come on flat magenta. For each one this keys out
the magenta, removes its pink fringe and the generator's grey leftovers, then trims it:
- symbols: centred on a square, 256 px transparent WebP (symbols/);
- logo: 800 px wide transparent WebP;
- backgrounds: WebP, as delivered (16:9 and 9:16, day and night);
- thumbnail: the lobby tile, a 4:3 JPEG. It is uploaded in the admin panel, not served from here.

Today's art comes from round 1 (sources 01-26). Round 3 replaces it; extend the tables below
when its sources arrive in storage/app/art/sugar-orchard/round3/.
"""
import sys
from collections import deque
from pathlib import Path

from PIL import Image

REPO = Path(__file__).resolve().parents[2]
SRC = REPO / 'storage' / 'app' / 'art' / 'sugar-orchard'
OUT = Path(sys.argv[sys.argv.index('--out') + 1]) if '--out' in sys.argv else REPO / 'public' / 'images' / 'sugar-orchard'

# source -> (symbol file, remove grey generator leftovers, magenta inside the subject is a highlight)
SYMBOLS = {
    '02-toffee.png': ('toffee', False, False),
    '03-peppermint.png': ('peppermint', False, False),
    '04-gummy-bear.png': ('gummy-bear', False, False),
    '05-truffle.png': ('truffle', False, False),
    '06-orange.png': ('orange', True, False),
    '07-cherries.png': ('cherries', True, False),
    '08-strawberry.png': ('strawberry', True, False),
    '09-lemon.png': ('lemon', True, False),
    '10-kiwi.png': ('kiwi', False, False),
    '11-scatter.png': ('gumball-machine', False, True),
}
BACKGROUNDS = {
    '16-bg-day-landscape.jpg': 'bg-day-landscape',
    '17-bg-day-portrait.jpg': 'bg-day-portrait',
    '18-bg-night-landscape.jpg': 'bg-night-landscape',
    '19-bg-night-portrait.jpg': 'bg-night-portrait',
}
LOGO = '23-logo.png'
THUMBNAIL = '24-thumbnail.jpg'

SYMBOL_WORK, SYMBOL_SIZE, LOGO_WIDTH = 512, 256, 800
LO, HI = 60, 170  # magenta strength: below LO is the subject, above HI the background


def magenta(r, g, b):
    return max(0, min(r, b) - g)


def key(im, grey_cleanup=False, highlight=False):
    """An RGB image on flat magenta -> the subject on transparent, trimmed."""
    w, h = im.size
    px = im.load()
    alpha = [[0] * w for _ in range(h)]
    for y in range(h):
        for x in range(w):
            m = magenta(*px[x, y])
            alpha[y][x] = 255 if m <= LO else 0 if m >= HI else int(255 * (HI - m) / (HI - LO))

    # The background: transparent-ish pixels reachable from the border.
    bg = [[False] * w for _ in range(h)]
    q = deque((x, y) for x in range(w) for y in (0, h - 1)) + deque((x, y) for y in range(h) for x in (0, w - 1))
    while q:
        x, y = q.popleft()
        if not (0 <= x < w and 0 <= y < h) or bg[y][x] or alpha[y][x] > 128:
            continue
        bg[y][x] = True
        q.extend(((x + 1, y), (x - 1, y), (x, y + 1), (x, y - 1)))

    if highlight:
        # Magenta inside the subject is a specular highlight the generator coloured wrongly: make it white.
        for y in range(h):
            for x in range(w):
                if alpha[y][x] < 255 and not bg[y][x]:
                    a = alpha[y][x] / 255
                    r, g, b = px[x, y]
                    px[x, y] = (int(r * a + 255 * (1 - a)), int(g * a + 245 * (1 - a)), int(b * a + 235 * (1 - a)))
                    alpha[y][x] = 255

    if grey_cleanup:
        remove_grey(px, w, h, alpha, bg)

    out = Image.new('RGBA', (w, h))
    op = out.load()
    for y in range(h):
        for x in range(w):
            a = alpha[y][x]
            if bg[y][x] and a < 40:
                a = 0
            r, g, b = px[x, y]
            spill = magenta(r, g, b)
            if spill > 30 and a < 255 or spill > 60:
                r, b = max(g, r - spill), max(g, b - spill)  # pull the pink fringe back to neutral
            op[x, y] = (r, g, b, a)

    return out.crop(out.getchannel('A').point(lambda v: 255 if v > 12 else 0).getbbox())


def remove_grey(px, w, h, alpha, bg):
    """The generator's own light-grey background, left around (and between) some fruit."""
    def grey(x, y):
        r, g, b = px[x, y]
        return max(r, g, b) - min(r, g, b) < 22 and 160 < (r + g + b) / 3 < 252

    q = deque((x, y) for y in range(h) for x in range(w) if bg[y][x])
    seen = [[False] * w for _ in range(h)]
    while q:
        x, y = q.popleft()
        for nx, ny in ((x + 1, y), (x - 1, y), (x, y + 1), (x, y - 1)):
            if 0 <= nx < w and 0 <= ny < h and not bg[ny][nx] and not seen[ny][nx] and grey(nx, ny):
                seen[ny][nx] = True
                alpha[ny][nx] = 0
                q.append((nx, ny))

    def dull(x, y):  # the generator's grey, darker than any specular highlight
        r, g, b = px[x, y]
        return max(r, g, b) - min(r, g, b) < 22 and 185 < (r + g + b) / 3 < 226

    # Larger grey patches the subject encloses (between the cherry stems): no symbol has flat grey areas of its own.
    for sy in range(h):
        for sx in range(w):
            if seen[sy][sx] or bg[sy][sx] or not dull(sx, sy):
                continue
            patch, q = [], deque([(sx, sy)])
            seen[sy][sx] = True
            while q:
                x, y = q.popleft()
                patch.append((x, y))
                for nx, ny in ((x + 1, y), (x - 1, y), (x, y + 1), (x, y - 1)):
                    if 0 <= nx < w and 0 <= ny < h and not seen[ny][nx] and not bg[ny][nx] and dull(nx, ny):
                        seen[ny][nx] = True
                        q.append((nx, ny))
            if len(patch) >= 150:
                for x, y in patch:
                    alpha[y][x] = 0
                    bg[y][x] = True


def resized(im, size):
    """Resize with premultiplied alpha, so transparent edges don't darken."""
    return im.convert('RGBa').resize(size, Image.LANCZOS).convert('RGBA')


def main():
    (OUT / 'symbols').mkdir(parents=True, exist_ok=True)

    for source, (name, grey_cleanup, highlight) in SYMBOLS.items():
        im = Image.open(SRC / source).convert('RGB').resize((SYMBOL_WORK, SYMBOL_WORK), Image.LANCZOS)
        cut = key(im, grey_cleanup, highlight)
        side = int(max(cut.size) * 1.06)
        square = Image.new('RGBA', (side, side))
        square.paste(cut, ((side - cut.width) // 2, (side - cut.height) // 2))
        resized(square, (SYMBOL_SIZE, SYMBOL_SIZE)).save(OUT / 'symbols' / f'{name}.webp', 'WEBP', quality=92, method=6)
        print('symbol', name)

    logo = key(Image.open(SRC / LOGO).convert('RGB'))
    logo = resized(logo, (LOGO_WIDTH, round(logo.height * LOGO_WIDTH / logo.width)))
    logo.save(OUT / 'logo.webp', 'WEBP', quality=92, method=6)
    print('logo', logo.size)

    for source, name in BACKGROUNDS.items():
        Image.open(SRC / source).convert('RGB').save(OUT / f'{name}.webp', 'WEBP', quality=82, method=6)
        print('background', name)

    Image.open(SRC / THUMBNAIL).convert('RGB').save(OUT / 'thumbnail.jpg', 'JPEG', quality=88, optimize=True, progressive=True)
    print('thumbnail')


if __name__ == '__main__':
    main()
