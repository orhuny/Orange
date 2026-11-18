"""Builds Lucky Duck's game art (public/images/lucky-duck) from the delivered sources.

    python scripts/art/process-lucky-duck.py            (needs Python 3 with numpy and Pillow)

The sources are the generated images in storage/app/art/lucky-duck (not in git): sprites on
flat magenta, skies as full pictures. For each one this keys out the magenta (and the magenta
fringe on the edges), trims it, and packs it the way the marsh scene uses it:

- skies: JPEG, as they are;
- strips (far hills, marsh, near reeds): the band with content, cut so it tiles seamlessly;
- clouds and extras: one image per sprite;
- sprite sheets (flight, take-off, hit): one strip of equal frames. The flight and
  take-off frames are lined up on the duck's head and drawn at one scale, so the duck never
  jumps between frames or between the two sheets;
- the fox, his gun arm and the logo: trimmed cut-outs.

The muzzle flash is drawn by the scene: the delivered strip is clipped.

It prints each sheet's frame size and anchors (the head, the gun's pivot and muzzle) for the
scene's CSS (resources/views/crash/scenes/marsh).
"""
import json
import os
import sys

import numpy as np
from PIL import Image

REPO = os.path.abspath(os.path.join(os.path.dirname(__file__), '..', '..'))
SRC = os.path.join(REPO, 'storage', 'app', 'art', 'lucky-duck')
OUT = os.path.join(REPO, 'public', 'images', 'lucky-duck')
EXTRA_OUT = os.path.join(SRC, 'out')  # not served: the lobby thumbnail, uploaded in the admin panel
os.makedirs(OUT, exist_ok=True)
os.makedirs(EXTRA_OUT, exist_ok=True)
# OUT holds only what this builds: start from empty, so a renamed sprite leaves nothing behind.
for stale in os.listdir(OUT):
    if stale.endswith(('.webp', '.jpg')):
        os.remove(os.path.join(OUT, stale))

report = {}


def load(name):
    return np.asarray(Image.open(os.path.join(SRC, name)).convert('RGB')).astype(np.float32)


def key(rgb, lo=60.0, hi=165.0):
    """RGBA from an image on magenta: alpha from how magenta a pixel is, colour unmixed."""
    r, g, b = rgb[..., 0], rgb[..., 1], rgb[..., 2]
    magenta = np.minimum(r, b) - g  # 255 on #FF00FF, about 0 or below on everything we draw
    alpha = np.clip((hi - magenta) / (hi - lo), 0.0, 1.0)
    alpha[alpha < 0.06] = 0.0
    # The edge pixels are a mix of the colour and the magenta: take the magenta back out.
    a = np.maximum(alpha, 1e-3)[..., None]
    k = np.array([255.0, 0.0, 255.0], dtype=np.float32)
    colour = np.clip((rgb - (1.0 - a) * k) / a, 0, 255)
    colour = np.where(alpha[..., None] > 0.02, colour, 0)
    return np.dstack([colour, alpha * 255.0])


def bbox(rgba, threshold=20):
    ys, xs = np.nonzero(rgba[..., 3] > threshold)
    if len(xs) == 0:
        return None
    return xs.min(), ys.min(), xs.max() + 1, ys.max() + 1


def trim(rgba, pad=4):
    x0, y0, x1, y1 = bbox(rgba)
    h, w = rgba.shape[:2]
    return rgba[max(0, y0 - pad):min(h, y1 + pad), max(0, x0 - pad):min(w, x1 + pad)]


def to_image(rgba):
    return Image.fromarray(np.clip(rgba, 0, 255).astype(np.uint8), 'RGBA')


def save_webp(img, name, quality=86):
    path = os.path.join(OUT, name)
    img.save(path, 'WEBP', quality=quality, method=6)
    return os.path.getsize(path)


def scale(img, factor):
    w, h = img.size
    return img.resize((max(1, round(w * factor)), max(1, round(h * factor))), Image.LANCZOS)


def cells(rgba, cols, rows):
    h, w = rgba.shape[:2]
    for r in range(rows):
        for c in range(cols):
            yield rgba[round(r * h / rows):round((r + 1) * h / rows), round(c * w / cols):round((c + 1) * w / cols)]


def components(rgba, min_area=400, threshold=60):
    """Bounding boxes of the separate shapes in a cut-out (4-connected, flood fill)."""
    mask = rgba[..., 3] > threshold
    seen = np.zeros_like(mask)
    h, w = mask.shape
    boxes = []
    for y0 in range(0, h, 3):
        for x0 in range(0, w, 3):
            if not mask[y0, x0] or seen[y0, x0]:
                continue
            stack = [(y0, x0)]
            seen[y0, x0] = True
            xs, ys, area = [x0, x0], [y0, y0], 0
            while stack:
                y, x = stack.pop()
                area += 1
                xs[0], xs[1] = min(xs[0], x), max(xs[1], x)
                ys[0], ys[1] = min(ys[0], y), max(ys[1], y)
                for ny, nx in ((y + 1, x), (y - 1, x), (y, x + 1), (y, x - 1)):
                    if 0 <= ny < h and 0 <= nx < w and mask[ny, nx] and not seen[ny, nx]:
                        seen[ny, nx] = True
                        stack.append((ny, nx))
            if area >= min_area:
                boxes.append((xs[0], ys[0], xs[1] + 1, ys[1] + 1, area))
    return boxes


def merge_boxes(boxes, gap):
    """Joins boxes closer than `gap` px (a sprite drawn in several pieces, e.g. splash drops)."""
    boxes = [list(b[:4]) for b in boxes]
    changed = True
    while changed:
        changed = False
        for i in range(len(boxes)):
            for j in range(i + 1, len(boxes)):
                a, b = boxes[i], boxes[j]
                if a[0] - gap < b[2] and b[0] - gap < a[2] and a[1] - gap < b[3] and b[1] - gap < a[3]:
                    boxes[i] = [min(a[0], b[0]), min(a[1], b[1]), max(a[2], b[2]), max(a[3], b[3])]
                    del boxes[j]
                    changed = True
                    break
            if changed:
                break
    return boxes


def head(rgba):
    """Centroid and height of the duck's emerald head (the anchor that lines frames up)."""
    r, g, b, a = (rgba[..., i] for i in range(4))
    green = (a > 200) & (g > 90) & (g > r + 35) & (g > b + 15)
    ys, xs = np.nonzero(green)
    if len(xs) < 50:
        return None
    return float(xs.mean()), float(ys.mean()), float(np.percentile(ys, 97) - np.percentile(ys, 3))


def strip_sheet(frames, anchors, out_name, cell_h, head_h, anchor_at, pad=10):
    """Frames scaled so the head is head_h px tall and placed with the head at anchor_at
    (fractions of the cell), side by side in one strip. Returns the cell size."""
    placed = []
    for f, (hx, hy, hh) in zip(frames, anchors):
        k = head_h / hh
        img = scale(to_image(f), k)
        placed.append((img, hx * k, hy * k))
    # The cell must hold every frame around the shared anchor.
    left = max(hx for _, hx, _ in placed) + pad
    right = max(img.size[0] - hx for img, hx, _ in placed) + pad
    top = max(hy for _, _, hy in placed) + pad
    bottom = max(img.size[1] - hy for img, _, hy in placed) + pad
    ax, ay = anchor_at
    cw = int(np.ceil(max(left / ax, right / (1 - ax))))
    ch = int(np.ceil(max(top / ay, bottom / (1 - ay), cell_h)))
    sheet = Image.new('RGBA', (cw * len(placed), ch), (0, 0, 0, 0))
    for i, (img, hx, hy) in enumerate(placed):
        sheet.alpha_composite(img, (int(round(i * cw + ax * cw - hx)), int(round(ay * ch - hy))))
    size = save_webp(sheet, out_name)
    return cw, ch, size


def even_sheet(frames, out_name, cell_w, cell_h, k):
    """Frames scaled by k and centred in equal cells, side by side."""
    sheet = Image.new('RGBA', (cell_w * len(frames), cell_h), (0, 0, 0, 0))
    for i, f in enumerate(frames):
        img = scale(to_image(trim(f)), k)
        sheet.alpha_composite(img, (i * cell_w + (cell_w - img.size[0]) // 2, (cell_h - img.size[1]) // 2))
    return save_webp(sheet, out_name)


def seamless(rgba, overlap):
    """Cuts a strip so its right edge runs on into its left: the last `overlap` columns and
    the first ones are joined along the vertical seam where they differ least."""
    h, w = rgba.shape[:2]
    a = rgba[:, w - overlap:]  # what comes before the seam (the strip's end)
    b = rgba[:, :overlap]      # what comes after it (the strip's start)
    cost = np.abs(a - b).sum(axis=2)
    # Dynamic programming: the cheapest top-to-bottom path, moving at most one column a row.
    acc = cost.copy()
    for y in range(1, h):
        prev = acc[y - 1]
        left = np.concatenate([[np.inf], prev[:-1]])
        right = np.concatenate([prev[1:], [np.inf]])
        acc[y] += np.minimum(np.minimum(left, prev), right)
    seam = np.zeros(h, dtype=int)
    seam[-1] = int(np.argmin(acc[-1]))
    for y in range(h - 2, -1, -1):
        x = seam[y + 1]
        lo, hi = max(0, x - 1), min(overlap, x + 2)
        seam[y] = lo + int(np.argmin(acc[y, lo:hi]))
    joined = np.empty_like(a)
    xs = np.arange(overlap)[None, :]
    t = np.clip((xs - seam[:, None] + 3) / 6.0, 0, 1)[..., None]  # a 6 px feather across the seam
    joined[:] = a * (1 - t) + b * t
    return np.concatenate([joined, rgba[:, overlap:w - overlap]], axis=1)


# --- Skies: full pictures -----------------------------------------------------------------------
for name in ['sky-day-landscape', 'sky-day-portrait', 'sky-sunset-landscape', 'sky-sunset-portrait']:
    img = Image.open(os.path.join(SRC, name + '.png')).convert('RGB')
    path = os.path.join(OUT, name + '.jpg')
    img.save(path, 'JPEG', quality=84, optimize=True, progressive=True)
    report[name] = {'size': img.size, 'bytes': os.path.getsize(path)}

# --- Strips: the band with content, tiling ------------------------------------------------------
for name, overlap in [('strip-hills', 220), ('strip-marsh', 220), ('strip-reeds-front', 220)]:
    rgba = key(load(name + '.png'))
    x0, y0, x1, y1 = bbox(rgba, threshold=8)
    band = rgba[max(0, y0 - 2):, :]  # the band runs to the bottom edge
    band = band[:, 2:-2]             # a 2 px frame some generators leave
    tile = seamless(band, overlap)
    img = to_image(tile)
    size = save_webp(img, name + '.webp', quality=84)
    report[name] = {'size': img.size, 'bytes': size}

# --- Clouds and extras: one image per sprite ----------------------------------------------------
rgba = key(load('clouds.png'))
boxes = sorted(components(rgba, min_area=3000), key=lambda b: (b[1] // 200, b[0]))
report['clouds'] = []
for i, (x0, y0, x1, y1, _) in enumerate(boxes, 1):
    img = to_image(trim(rgba[y0:y1, x0:x1]))
    if img.size[0] > 460:
        img = scale(img, 460 / img.size[0])
    report['clouds'].append({'file': f'cloud-{i}.webp', 'size': img.size, 'bytes': save_webp(img, f'cloud-{i}.webp')})

rgba = key(load('extras.png'))
rgba = rgba[4:-4, 4:-4]
h, w = rgba.shape[:2]
boxes = sorted(merge_boxes(components(rgba, min_area=300), gap=4), key=lambda b: b[0])
# A sprite cut off by the picture's edge (half a lily pad on the left) is left out.
boxes = [b for b in boxes if b[0] > 2 and b[2] < w - 2]
names = ['lily', 'clover', 'coin', 'feather', 'splash']
assert len(boxes) == len(names), f'{len(boxes)} extras, expected {len(names)}'
report['extras'] = []
for name, (x0, y0, x1, y1) in zip(names, boxes):
    img = to_image(trim(rgba[y0:y1, x0:x1]))
    report['extras'].append({'file': f'{name}.webp', 'size': img.size, 'bytes': save_webp(img, f'{name}.webp')})

# --- The duck: flight and take-off on one anchor and scale ---------------------------------------
HEAD_H = 58          # px: the head's height in the output frames (the duck is drawn ~2.5x this wide)
ANCHOR = (0.66, 0.32)  # where the head sits in a cell (the body trails left and below it)

fly = [key(c) for c in cells(load('v2/Lucky Duck Flight Cycle Sprite Sheet-1.png'), 4, 2)]
fly = [trim(f, pad=2) for f in fly]
fly_anchor = [head(f) for f in fly]
takeoff = [key(c) for c in cells(load('mallard_duck_splash_animation_sheet.png'), 4, 1)]
takeoff = [trim(f, pad=2) for f in takeoff]
takeoff_anchor = [head(f) for f in takeoff]
assert all(fly_anchor) and all(takeoff_anchor), 'a frame without a head'
# Both sheets in one cell size, so the scene swaps them without the duck moving.
cw, ch, _ = strip_sheet(fly + takeoff, fly_anchor + takeoff_anchor, '_both.webp', 0, HEAD_H, ANCHOR)
os.remove(os.path.join(OUT, '_both.webp'))
for frames, anchors, out_name in [(fly, fly_anchor, 'duck-fly.webp'), (takeoff, takeoff_anchor, 'duck-takeoff.webp')]:
    placed = Image.new('RGBA', (cw * len(frames), ch), (0, 0, 0, 0))
    for i, (f, (hx, hy, hh)) in enumerate(zip(frames, anchors)):
        k = HEAD_H / hh
        img = scale(to_image(f), k)
        placed.alpha_composite(img, (int(round(i * cw + ANCHOR[0] * cw - hx * k)), int(round(ANCHOR[1] * ch - hy * k))))
    report[out_name] = {'frames': len(frames), 'cell': (cw, ch), 'head': ANCHOR, 'bytes': save_webp(placed, out_name)}

# --- The hit: six poses, centred, at the flight's scale -------------------------------------------
hit = [trim(key(c), pad=2) for c in cells(load('mallard_mayhem_hit_to_feathers.png'), 3, 2)]
# Drawn with bigger heads than the flight, so matched on the body instead: the first pose
# (just hit, wings out) as wide as the flying duck.
fly_widths = [f.shape[1] * HEAD_H / a[2] for f, a in zip(fly, fly_anchor)]
k = float(np.median(fly_widths)) / hit[0].shape[1]
hw = int(max(f.shape[1] for f in hit) * k) + 20
hh = int(max(f.shape[0] for f in hit) * k) + 20
report['duck-hit.webp'] = {'frames': len(hit), 'cell': (hw, hh), 'bytes': even_sheet(hit, 'duck-hit.webp', hw, hh, k)}

# --- The fox: eight poses in his tub boat ------------------------------------------------------------
# Two sheets drawn alike (the same half-barrel tub): fox-boat-aim.png aims low, mid, high and
# paddles; fox-boat-fire.png fires low, mid, high and reloads. Frames: 0-2 aim, 3-5 fire,
# 6 reload, 7 paddle. Lined up and scaled on the tub's iron hoops (the same in every frame).


def despeck(f, keep=0.02):
    """Clears the specks a generator leaves between frames: shapes under `keep` of the largest."""
    boxes = components(f, min_area=1, threshold=60)
    if not boxes:
        return f
    big = max(b[4] for b in boxes)
    out = f.copy()
    for x0, y0, x1, y1, area in boxes:
        if area < big * keep:
            out[y0:y1, x0:x1, 3] = 0
    return out


def hoops(f):
    """The tub's dark slate iron hoops: their left and right edge and their lowest row."""
    r, g, b, a = (f[..., i] for i in range(4))
    slate = (a > 200) & (b > r + 8) & (b >= g) & (r < 130) & (g < 140) & (b < 175)
    slate[: int(f.shape[0] * 0.45)] = False  # the tub is in the lower half
    ys, xs = np.nonzero(slate)
    return float(np.percentile(xs, 2)), float(np.percentile(xs, 98)), float(np.percentile(ys, 98))


def bell_mouth(f):
    """The blunderbuss's mouth: the top-right end of the brass."""
    r, g, b, a = (f[..., i] for i in range(4))
    brass = (a > 200) & (r > 170) & (g > 120) & (b < 110) & (r - b > 90)
    ys, xs = np.nonzero(brass)
    score = xs - 0.5 * ys
    best = np.argsort(score)[-40:]
    return float(xs[best].mean()), float(ys[best].mean())


aim_cells = [despeck(key(c, lo=10.0, hi=110.0)) for c in cells(load('fox-boat-aim.png'), 4, 1)]
fire_cells = [despeck(key(c, lo=10.0, hi=110.0)) for c in cells(load('fox-boat-fire.png'), 4, 1)]
poses = [trim(f, pad=2) for f in aim_cells[:3] + fire_cells + [aim_cells[3]]]
FOX_W = 520  # px: the output cell's width
marks = [hoops(f) for f in poses]
width = float(np.median([r - l for l, r, _ in marks]))
scales = [width / (r - l) for l, r, _ in marks]           # every tub as wide as the median
centres = [(l + r) / 2 * k for (l, r, _), k in zip(marks, scales)]
bottoms = [y * k for (_, _, y), k in zip(marks, scales)]   # the hoops' lowest row
left = max(centres) + 4
right = max(f.shape[1] * k - c for f, k, c in zip(poses, scales, centres)) + 4
above = max(bottoms) + 4
below = max(f.shape[0] * k - y for f, k, y in zip(poses, scales, bottoms)) + 4
z = FOX_W / (left + right)
cw, ch = FOX_W, int(np.ceil((above + below) * z))
sheet = Image.new('RGBA', (cw * len(poses), ch), (0, 0, 0, 0))
mouths = []
for i, (f, k, c, y) in enumerate(zip(poses, scales, centres, bottoms)):
    img = scale(to_image(f), k * z)
    x0 = int(round((left - c) * z))
    y0 = int(round((above - y) * z))
    sheet.alpha_composite(img, (i * cw + x0, y0))
    if i < 6:  # the reload holds the gun upright, the paddling lays it down: no shot from them
        mx, my = bell_mouth(f)
        mouths.append((round((x0 + mx * k * z) / cw, 3), round((y0 + my * k * z) / ch, 3)))
report['fox-boat.webp'] = {
    'frames': len(poses), 'cell': (cw, ch), 'bytes': save_webp(sheet, 'fox-boat.webp'),
    # Fractions of a cell, per aim and fire pose: where the bell's mouth is (the scene's FOX_MUZZLE);
    # and how far down the cell the tub's waterline is.
    'muzzle': mouths, 'waterline': round(above * z / ch + 0.02, 3),
}

# The wake behind the tub: three frames that loop, lined up on their right end and water line.
wake = [trim(despeck(key(c)), pad=2) for c in cells(load('boat-wake.png'), 3, 1)]
wk = 300 / max(f.shape[1] for f in wake)
ww, wh = 300 + 8, int(max(f.shape[0] for f in wake) * wk) + 8
wsheet = Image.new('RGBA', (ww * 3, wh), (0, 0, 0, 0))
for i, f in enumerate(wake):
    img = scale(to_image(f), wk)
    wsheet.alpha_composite(img, (i * ww + ww - 4 - img.size[0], wh - 4 - img.size[1]))
report['boat-wake.webp'] = {'frames': 3, 'cell': (ww, wh), 'bytes': save_webp(wsheet, 'boat-wake.webp')}

# --- Near misses: the shot's spray, the muzzle blast, Lucky dodging ----------------------------------
spray = trim(key(load('shot-spray.png')))
img = scale(to_image(spray), 520 / spray.shape[1])
report['shot-spray.webp'] = {'size': img.size, 'bytes': save_webp(img, 'shot-spray.webp')}
blast = trim(key(load('muzzle-burst.png')))
img = scale(to_image(blast), 260 / blast.shape[1])
report['muzzle-burst.webp'] = {'size': img.size, 'bytes': save_webp(img, 'muzzle-burst.webp')}
# Flinch, barrel roll, duck under, smug wink: on the flight's head anchor, so Lucky doesn't jump
# between flying and dodging (the roll turns about his head). Drawn with bigger heads than the
# flight, so scaled on the body: the flinch and the wink (wings out) as wide as the flying duck.
dodge = [trim(key(c), pad=2) for c in cells(load('duck-dodge.png'), 2, 2)]
dodge_anchor = [head(f) for f in dodge]
assert all(dodge_anchor), 'a dodge frame without a head'
dk = float(np.median(fly_widths)) / float(np.mean([dodge[0].shape[1], dodge[3].shape[1]]))
placed = [(scale(to_image(f), dk), hx * dk, hy * dk) for f, (hx, hy, _) in zip(dodge, dodge_anchor)]
lft = max(hx for _, hx, _ in placed) + 10
rgt = max(img.size[0] - hx for img, hx, _ in placed) + 10
top = max(hy for _, _, hy in placed) + 10
bot = max(img.size[1] - hy for img, _, hy in placed) + 10
dw = int(np.ceil(max(lft / ANCHOR[0], rgt / (1 - ANCHOR[0]))))
dh = int(np.ceil(max(top / ANCHOR[1], bot / (1 - ANCHOR[1]))))
dsheet = Image.new('RGBA', (dw * len(placed), dh), (0, 0, 0, 0))
for i, (img, hx, hy) in enumerate(placed):
    dsheet.alpha_composite(img, (int(round(i * dw + ANCHOR[0] * dw - hx)), int(round(ANCHOR[1] * dh - hy))))
report['duck-dodge.webp'] = {'frames': len(dodge), 'cell': (dw, dh), 'head': ANCHOR, 'bytes': save_webp(dsheet, 'duck-dodge.webp')}

# The delivered muzzle strip (muzzle.png) is clipped flat on every frame, so it is not used:
# the scene draws the blunderbuss's flash itself (SVG) and puffs grey clouds for the smoke.

# --- BANG!: the word and the burst behind it, animated by the scene's crash styles ---------------
word = trim(key(load('bang-word.png')))
img = scale(to_image(word), 900 / word.shape[1])
report['bang-word.webp'] = {'size': img.size, 'bytes': save_webp(img, 'bang-word.webp', quality=88)}
burst = trim(key(load('bang-burst.png')))
img = scale(to_image(burst), 760 / burst.shape[1])
report['bang-burst.webp'] = {'size': img.size, 'bytes': save_webp(img, 'bang-burst.webp', quality=86)}

# --- Lucky in the water: the fall's end (feet up, sinking) and the next round's start (he pops up,
# shakes off, straightens his bandana, floats smug). Lined up on the ripple under him and drawn at
# the floating duck's scale (the take-off sheet's head height), so the swap to floating is seamless.


def ripple(f):
    """The ring of water under him: its centre and lowest row."""
    r, g, b, a = (f[..., i] for i in range(4))
    water = (a > 160) & (b > 180) & (g > 120) & (r < 140)
    ys, xs = np.nonzero(water)
    low = ys > np.percentile(ys, 50)
    return float((xs[low].min() + xs[low].max()) / 2), float(np.percentile(ys, 99))


water = [trim(despeck(key(c)), pad=2) for c in cells(load('duck-water.png'), 3, 2)]


def body_width(f):
    """How wide the duck is, leaving out the water around him."""
    r, g, b, a = (f[..., i] for i in range(4))
    body = (a > 160) & ~((b > 180) & (g > 120) & (r < 140))
    xs = np.nonzero(body.any(axis=0))[0]
    return float(xs.max() - xs.min())


# The floating take-off frame (already at the flight's scale): the last water frame (floating
# smug) is drawn as wide as it.
t0 = Image.open(os.path.join(OUT, 'duck-takeoff.webp')).convert('RGBA')
t0 = np.asarray(t0).astype(np.float32)[:, :t0.width // 4]
wk = body_width(t0) / body_width(water[5])
rip = [ripple(f) for f in water]
left = max(x * wk for x, _ in rip) + 6
right = max(f.shape[1] * wk - x * wk for f, (x, _) in zip(water, rip)) + 6
above = max(y * wk for _, y in rip) + 6
below = max(f.shape[0] * wk - y * wk for f, (_, y) in zip(water, rip)) + 6
wcw, wch = int(np.ceil(left + right)), int(np.ceil(above + below))
wsheet = Image.new('RGBA', (wcw * 6, wch), (0, 0, 0, 0))
for i, (f, (x, y)) in enumerate(zip(water, rip)):
    img = scale(to_image(f), wk)
    wsheet.alpha_composite(img, (int(round(i * wcw + left - x * wk)), int(round(above - y * wk))))
# Where the floating take-off frame's ripple is, in its cell, to put the water frames on it.
tx, ty = ripple(t0)
report['duck-water.webp'] = {
    'frames': 6, 'cell': (wcw, wch), 'bytes': save_webp(wsheet, 'duck-water.webp'),
    # The ripple's bottom centre: in a water cell, and in a take-off (flight-sized) cell.
    'anchor': (round(left / wcw, 4), round(above / wch, 4)),
    'takeoff_anchor': (round(tx / t0.shape[1], 4), round(ty / t0.shape[0], 4)),
}

# The splash where he lands: the four whole frames (2 and 5 of the sheet are cut off), on the water line.
splash = [trim(despeck(key(c)), pad=2) for i, c in enumerate(cells(load('splash.png'), 3, 2)) if i in (0, 2, 3, 5)]
sk = 280 / max(f.shape[1] for f in splash)
srip = [ripple(f) for f in splash]
sl = max(x * sk for x, _ in srip) + 6
sr = max(f.shape[1] * sk - x * sk for f, (x, _) in zip(splash, srip)) + 6
sa = max(y * sk for _, y in srip) + 6
sb = max(f.shape[0] * sk - y * sk for f, (_, y) in zip(splash, srip)) + 6
scw, sch = int(np.ceil(sl + sr)), int(np.ceil(sa + sb))
ssheet = Image.new('RGBA', (scw * 4, sch), (0, 0, 0, 0))
for i, (f, (x, y)) in enumerate(zip(splash, srip)):
    img = scale(to_image(f), sk)
    ssheet.alpha_composite(img, (int(round(i * scw + sl - x * sk)), int(round(sa - y * sk))))
report['water-splash.webp'] = {'frames': 4, 'cell': (scw, sch), 'anchor': (round(sl / scw, 4), round(sa / sch, 4)),
                         'bytes': save_webp(ssheet, 'water-splash.webp')}

# Gun smoke for every shot: six frames, puff to wisp. The last ones came out pink from the magenta:
# smoke is grey, so they lose that tint.
smoke = [trim(despeck(key(c)), pad=2) for c in cells(load('gunsmoke.png'), 3, 2)]
for i in (3, 4, 5):
    f = smoke[i]
    grey = f[..., :3].mean(axis=2, keepdims=True)
    f[..., :3] = grey * 0.85 + f[..., :3] * 0.15 + np.array([4.0, 4.0, 12.0])  # a touch of the lavender shade
gk = 200 / max(max(f.shape[0], f.shape[1]) for f in smoke)
# Lucky coming back up out of the pond, the next round: 12 small steps (bubbles, the crest breaks
# the surface, his head, the shake, his chest, the bandana, smug). Like duck-water: lined up on the
# ripple and drawn as wide as the floating take-off frame by the last (floating) frame, whose ripple
# goes on the take-off frame's.
emerge = [trim(despeck(key(c)), pad=2) for c in cells(load('duck-emerge.png'), 4, 3)]
ek = body_width(t0) / body_width(emerge[11])
erip = [ripple(f) for f in emerge]
el = max(x * ek for x, _ in erip) + 6
er = max(f.shape[1] * ek - x * ek for f, (x, _) in zip(emerge, erip)) + 6
ea = max(y * ek for _, y in erip) + 6
eb = max(f.shape[0] * ek - y * ek for f, (_, y) in zip(emerge, erip)) + 6
ecw, ech = int(np.ceil(el + er)), int(np.ceil(ea + eb))
esheet = Image.new('RGBA', (ecw * 12, ech), (0, 0, 0, 0))
for i, (f, (x, y)) in enumerate(zip(emerge, erip)):
    img = scale(to_image(f), ek)
    esheet.alpha_composite(img, (int(round(i * ecw + el - x * ek)), int(round(ea - y * ek))))
report['duck-emerge.webp'] = {
    'frames': 12, 'cell': (ecw, ech), 'anchor': (round(el / ecw, 4), round(ea / ech, 4)),
    'bytes': save_webp(esheet, 'duck-emerge.webp'),
    # Where to put a cell in the duck's 282 × 312 frame, in %: its ripple on the take-off frame's.
    'css': {
        'left': round((tx - el) / t0.shape[1] * 100, 2), 'top': round((ty - ea) / t0.shape[0] * 100, 2),
        'width': round(ecw / t0.shape[1] * 100, 2),
    },
}

report['gunsmoke.webp'] = {'frames': 6, 'cell': (210, 210), 'bytes': even_sheet(smoke, 'gunsmoke.webp', 210, 210, gk)}

# --- Dizzy: a spiral eye (spun over Lucky's eyes as he falls) and a star (circling his head) ------
rgba = key(load('dizzy.png'))
boxes = sorted(merge_boxes(components(rgba, min_area=2000), gap=6), key=lambda b: b[0])
assert len(boxes) == 2, f'{len(boxes)} dizzy sprites, expected 2'
for name, (x0, y0, x1, y1) in zip(['dizzy-eye', 'dizzy-star'], boxes):
    img = to_image(trim(rgba[y0:y1, x0:x1]))
    img = scale(img, 120 / img.size[0])
    report[f'{name}.webp'] = {'size': img.size, 'bytes': save_webp(img, f'{name}.webp')}

logo = trim(key(load('lucky_duck_winged_mascot_logo.png')))
img = scale(to_image(logo), 960 / logo.shape[1])
report['logo.webp'] = {'size': img.size, 'bytes': save_webp(img, 'logo.webp', quality=88)}

thumb = Image.open(os.path.join(SRC, 'lucky_duck_golden_sky_chase.png')).convert('RGB').resize((1024, 768), Image.LANCZOS)
thumb.save(os.path.join(EXTRA_OUT, 'lobby-thumbnail.jpg'), 'JPEG', quality=88, optimize=True, progressive=True)
report['lobby-thumbnail.jpg'] = {'size': thumb.size, 'where': os.path.join(EXTRA_OUT, 'lobby-thumbnail.jpg')}

json.dump(report, sys.stdout, indent=1, default=lambda o: o.tolist() if hasattr(o, 'tolist') else str(o))
print()
