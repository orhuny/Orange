"""Shared helpers for the art build scripts (scripts/art/process-*.py): keying, trimming,
resizing with premultiplied alpha, turning light on black into light on transparent, and
cutting a sheet into its separate pieces. Pillow only.

process-sugar-orchard.py predates this module and keeps its own copies, so its output stays
exactly as it was."""

from collections import deque
from pathlib import Path

from PIL import Image, ImageChops, ImageFilter

WEBP = {'quality': 90, 'method': 6}
FLOOR = 8  # the generator's near-black: below this a light effect is fully transparent
_LIFT = [0 if v <= FLOOR else round((v - FLOOR) * 255 / (255 - FLOOR)) for v in range(256)]


class Out:
    """Where a build writes: save(im, 'fx/ring.webp') -> the path relative to the folder."""

    def __init__(self, root):
        self.root = Path(root)

    def save(self, im, rel, **opts):
        path = self.root / rel
        path.parent.mkdir(parents=True, exist_ok=True)
        if rel.endswith('.jpg'):
            im.convert('RGB').save(path, 'JPEG', quality=86, optimize=True, progressive=True)
        else:
            im.save(path, 'WEBP', **{**WEBP, **opts})
        return rel


def dusted(im):
    """The image without the generator's alpha dust (near-invisible pixels all over)."""
    im = im.convert('RGBA')
    im.putalpha(im.getchannel('A').point(lambda v: 0 if v <= FLOOR else v))
    return im


def trimmed(im, margin=0):
    box = im.getchannel('A').point(lambda v: 255 if v > 8 else 0).getbbox()
    x0, y0, x1, y1 = box
    return im.crop((max(0, x0 - margin), max(0, y0 - margin), min(im.width, x1 + margin), min(im.height, y1 + margin)))


def light(im):
    """A light effect on black -> its light on transparent: the brightest channel becomes
    the alpha, the colour is un-premultiplied. Drawn normally, it then looks as if added."""
    r, g, b = (c.point(_LIFT) for c in im.convert('RGB').split())
    a = ImageChops.lighter(ImageChops.lighter(r, g), b)
    return Image.merge('RGBa', (r, g, b, a)).convert('RGBA')


def unmagenta(im):
    """A cut-out on flat magenta (#FF00FF) -> on transparent. How magenta a pixel is
    (min(r, b) - g) sets its transparency; the half-transparent edge loses its pink."""
    r, g, b = im.convert('RGB').split()
    rb = ImageChops.darker(r, b)
    spill = ImageChops.subtract(rb, g)  # 0 for anything not magenta-ish
    alpha = spill.point(lambda v: 255 if v < 40 else 0 if v > 200 else round(255 * (200 - v) / 160))
    # Despill: pull red and blue down toward green where magenta bled in.
    r = ImageChops.subtract(r, spill.point(lambda v: v // 2 if v >= 40 else 0))
    b = ImageChops.subtract(b, spill.point(lambda v: v // 2 if v >= 40 else 0))
    return Image.merge('RGBA', (r, g, b, alpha))


def resized(im, size):
    """Resize with premultiplied alpha, so transparent edges don't darken."""
    if im.mode != 'RGBA':
        return im.resize(size, Image.LANCZOS)
    return im.convert('RGBa').resize(size, Image.LANCZOS).convert('RGBA')


def fit(im, longest):
    k = longest / max(im.size)
    return resized(im, (max(1, round(im.width * k)), max(1, round(im.height * k))))


def fit_width(im, width):
    return resized(im, (width, max(1, round(im.height * width / im.width))))


def square(im, size, pad=1.04):
    cut = trimmed(im)
    side = int(max(cut.size) * pad)
    sq = Image.new('RGBA', (side, side))
    sq.paste(cut, ((side - cut.width) // 2, (side - cut.height) // 2))
    return resized(sq, (size, size))


def pieces(mask, min_area):
    """Separate blobs of a mask (an 'L' image, on = 255), at half size for speed: a list of
    (area, bbox, label image) with the bbox and label at full size."""
    small = mask.resize((mask.width // 2, mask.height // 2), Image.BILINEAR)
    w, h = small.size
    on = [v > 96 for v in small.tobytes()]
    label = [0] * (w * h)
    found = []
    for start in range(w * h):
        if not on[start] or label[start]:
            continue
        n = len(found) + 1
        label[start] = n
        q = deque([start])
        x0 = y0 = 10**9
        x1 = y1 = -1
        area = 0
        while q:
            i = q.popleft()
            area += 1
            x, y = i % w, i // w
            x0, y0, x1, y1 = min(x0, x), min(y0, y), max(x1, x), max(y1, y)
            for j in (i - 1 if x > 0 else -1, i + 1 if x < w - 1 else -1, i - w, i + w):
                if 0 <= j < w * h and on[j] and not label[j]:
                    label[j] = n
                    q.append(j)
        found.append((area, (x0, y0, x1 + 1, y1 + 1)))
    out = []
    for n, (area, (x0, y0, x1, y1)) in enumerate(found, start=1):
        if area * 4 < min_area:
            continue
        lab = Image.new('L', (w, h))
        lab.putdata([255 if v == n else 0 for v in label])
        lab = lab.resize(mask.size, Image.NEAREST).filter(ImageFilter.MaxFilter(5))
        bbox = (max(0, x0 * 2 - 4), max(0, y0 * 2 - 4), min(mask.width, x1 * 2 + 4), min(mask.height, y1 * 2 + 4))
        out.append((area * 4, bbox, lab))
    return out


def cut_pieces(out, im, prefix, min_area, scale, glow=False):
    """Every separate piece of a sheet as its own sprite: the paths, largest first. Cut-outs
    keep only their piece's pixels; light effects (on black) are cropped with their black."""
    mask = im.convert('L').point(lambda v: 255 if v > 28 else 0) if glow else im.getchannel('A')
    sprites = []
    for k, (area, bbox, lab) in enumerate(sorted(pieces(mask, min_area), key=lambda p: -p[0]), start=1):
        if glow:
            sprite = light(im.convert('RGB').crop(bbox))
        else:
            sprite = im.copy()
            sprite.putalpha(Image.composite(im.getchannel('A'), Image.new('L', im.size), lab))
            sprite = sprite.crop(bbox)
        size = (max(1, round(sprite.width * scale)), max(1, round(sprite.height * scale)))
        sprites.append(out.save(resized(sprite, size), f'{prefix}-{k}.webp'))
    return sprites


def frame_window(im):
    """The frame's inner edges: from the middle outward to the first solid pixel, as
    fractions of the image."""
    a = im.getchannel('A')
    w, h = im.size
    cx, cy = w // 2, h // 2
    left = next(x for x in range(cx, -1, -1) if a.getpixel((x, cy)) > 200) + 1
    right = next(x for x in range(cx, w) if a.getpixel((x, cy)) > 200)
    top = next(y for y in range(cy, -1, -1) if a.getpixel((cx, y)) > 200) + 1
    bottom = next(y for y in range(cy, h) if a.getpixel((cx, y)) > 200)
    return {'x0': round(left / w, 4), 'y0': round(top / h, 4), 'x1': round(right / w, 4), 'y1': round(bottom / h, 4)}


def frame_pieces(out, frame, width, aspect=None, keep_centre=70):
    """The frame cut so it fits a grid of any width without stretching its ornaments: a left
    piece, a right piece, and a middle the page stretches between them. The cuts fall where
    the top and bottom edges hold nothing but the frame's plain band. Measurements are in the
    frame's own pixels.

    The middle is a plain 8-pixel strip, unless `aspect` gives the grid's width / height on
    the page. Then it is the frame's own middle, widened to that grid (see widened_band) so
    the page barely stretches it. keep_centre: how far either side of the centre (a crest)
    is never widened."""
    a = frame.getchannel('A')
    w, h = frame.size
    win = frame_window(frame)
    x0, x1, y0, y1 = round(win['x0'] * w), round(win['x1'] * w), round(win['y0'] * h), round(win['y1'] * h)

    # A column is plain where the top and bottom borders look the same as their neighbours':
    # no ornament starts or ends there. Measured as the change from the column before.
    def column(x):
        return [frame.getpixel((x, y))[:3] for y in list(range(0, y0)) + list(range(y1, h))]

    def change(x):
        a_, b_ = column(x - 1), column(x)
        return sum(abs(p[0] - q[0]) + abs(p[1] - q[1]) + abs(p[2] - q[2]) for p, q in zip(a_, b_)) / len(a_)

    def calmest_run(lo, hi, length=10):
        scores = [change(x) for x in range(lo, hi)]
        best = min(range(len(scores) - length), key=lambda i: sum(scores[i:i + length]))
        return lo + best, lo + best + length

    l_start, _ = calmest_run(x0 + 40, w // 2 - 20)
    _, r_end = calmest_run(w // 2 + 20, x1 - 40)
    cut_l, cut_r = l_start, r_end
    k = width / w
    size = lambda im: (max(4, round(im.width * k)), round(im.height * k))  # noqa: E731
    left, right, fill = frame.crop((0, 0, cut_l, h)), frame.crop((cut_r, 0, w, h)), frame.crop((cut_l, 0, cut_l + 8, h))
    extra = round(aspect * (y1 - y0)) - (x1 - x0) if aspect else 0
    if extra > 0:
        # Each band (above and below the window) widened on both sides of the centre, half
        # each, so the crest stays in the middle. The window between them stays transparent.
        fill = Image.new('RGBA', (cut_r - cut_l + extra, h), (0, 0, 0, 0))
        mid = w // 2
        for r0, r1 in ((0, y0), (y1, h)):
            cost = band_cost(frame.crop((0, r0, w, r1)))
            for row in cost:
                for x in range(mid - keep_centre, mid + keep_centre):
                    row[x] += 10_000
            halves = [
                widened_band(frame.crop((cut_l, r0, mid, r1)), (extra + 1) // 2, [row[cut_l:mid] for row in cost]),
                widened_band(frame.crop((mid, r0, cut_r, r1)), extra // 2, [row[mid:cut_r] for row in cost]),
            ]
            fill.paste(halves[0], (0, r0))
            fill.paste(halves[1], (halves[0].width, r0))
    return {
        'left': out.save(resized(left, size(left)), 'frame-left.webp', quality=88),
        'right': out.save(resized(right, size(right)), 'frame-right.webp', quality=88),
        'fill': out.save(resized(fill, size(fill)), 'frame-fill.webp', quality=88),
        'px': {'w': w, 'h': h, 'x0': x0, 'x1': x1, 'y0': y0, 'y1': y1, 'cut_l': cut_l, 'cut_r': cut_r},
    }


def band_cost(im):
    """How much widening each pixel of a frame band would show, per row: the change across x,
    next door and 3, 6 and 12 pixels out on each side. Low along a straight horizontal stroke
    and in plain patina, high in the middle of anything round, and highest on a pearl (bright,
    with almost no colour), which must stay round."""
    w, h = im.size
    lum = im.convert('L').filter(ImageFilter.GaussianBlur(1)).load()
    rgb = im.convert('RGB').load()

    def at(x, y):
        return lum[min(max(x, 0), w - 1), y]

    def pearl(x, y):
        r, g, b = rgb[x, y]
        return 150 if min(r, g, b) > 185 and max(r, g, b) - min(r, g, b) < 45 else 0

    return [[abs(at(x + 1, y) - at(x - 1, y)) + pearl(x, y)
             + sum(abs(at(x + r, y) - at(x, y)) + abs(at(x, y) - at(x - r, y)) for r in (3, 6, 12)) / 3
             for x in range(w)] for y in range(h)]


def widened_band(im, extra, cost, spread=6.0):
    """im with `extra` more columns, added by seam insertion: each seam runs top to bottom, one
    pixel a row and at most one column sideways, along the lowest total cost (band_cost of
    the same pixels). Each pixel on a seam gains a copy, the average of it and its right-hand
    neighbour. A seam taken makes its neighbours a little dearer (spread), so the seams share
    out the plain stretches instead of piling into one."""
    w, h = im.size
    cost = [row[:] for row in cost]
    index = [list(range(w)) for _ in range(h)]
    seams = []
    for _ in range(extra):
        n = len(index[0])
        total, back = cost[0][:], []
        for y in range(1, h):
            row, step, here = [0.0] * n, [0] * n, cost[y]
            for x in range(n):
                k = x
                if x > 0 and total[x - 1] < total[k]:
                    k = x - 1
                if x < n - 1 and total[x + 1] < total[k]:
                    k = x + 1
                row[x], step[x] = here[x] + total[k], k
            total = row
            back.append(step)
        x = min(range(n), key=total.__getitem__)
        seam = [0] * h
        for y in range(h - 1, -1, -1):
            seam[y] = index[y][x]
            del index[y][x]
            del cost[y][x]
            for d in (-2, -1, 0, 1):
                if 0 <= x + d < n - 1:
                    cost[y][x + d] += spread / (1 + abs(d + 0.5))
            if y > 0:
                x = back[y - 1][x]
        seams.append(seam)

    src = im.load()
    out = Image.new('RGBA', (w + extra, h))
    dst = out.load()
    for y in range(h):
        copies = {}
        for seam in seams:
            copies[seam[y]] = copies.get(seam[y], 0) + 1
        to = 0
        for x in range(w):
            p = src[x, y]
            dst[to, y] = p
            to += 1
            for _ in range(copies.get(x, 0)):
                q = src[min(x + 1, w - 1), y]
                dst[to, y] = tuple((a + b) // 2 for a, b in zip(p, q))
                to += 1
    return out


def fade_bottom(im, rows):
    """The image fading out over its last `rows` rows."""
    alpha = im.getchannel('A')
    start = im.height - rows
    ramp = Image.new('L', (1, im.height), 255)
    for y in range(start, im.height):
        ramp.putpixel((0, y), round(255 * (im.height - 1 - y) / (im.height - 1 - start)))
    im.putalpha(ImageChops.multiply(alpha, ramp.resize(im.size)))
    return im
