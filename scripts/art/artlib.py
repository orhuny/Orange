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


def frame_pieces(out, frame, width):
    """The frame cut so it fits a grid of any width without stretching its ornaments: a left
    piece, a right piece, and a plain strip the page stretches between them. The cuts fall
    where the top and bottom edges hold nothing but the frame's plain band. Measurements are
    in the frame's own pixels."""
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
    return {
        'left': out.save(resized(left, size(left)), 'frame-left.webp', quality=88),
        'right': out.save(resized(right, size(right)), 'frame-right.webp', quality=88),
        'fill': out.save(resized(fill, size(fill)), 'frame-fill.webp', quality=88),
        'px': {'w': w, 'h': h, 'x0': x0, 'x1': x1, 'y0': y0, 'y1': y1, 'cut_l': cut_l, 'cut_r': cut_r},
    }


def fade_bottom(im, rows):
    """The image fading out over its last `rows` rows."""
    alpha = im.getchannel('A')
    start = im.height - rows
    ramp = Image.new('L', (1, im.height), 255)
    for y in range(start, im.height):
        ramp.putpixel((0, y), round(255 * (im.height - 1 - y) / (im.height - 1 - start)))
    im.putalpha(ImageChops.multiply(alpha, ramp.resize(im.size)))
    return im
