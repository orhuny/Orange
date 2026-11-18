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

# --- The fox: four poses in his blind (aiming low, mid, high; firing) -----------------------------
# The fox has no pink: a stricter key takes out any magenta smudged into the reeds. The blind is
# the same in every pose, so the frames are lined up on it: its planks' bottom edge and centre.
poses = [trim(key(c, lo=10.0, hi=110.0), pad=2) for c in cells(load('fox-aim.png'), 4, 1)]
FOX_W = 520  # px: the output cell's width


def blind_centre(f):
    a = f[..., 3] > 128
    band = a[int(a.shape[0] * 0.92):]  # the planks along the bottom
    xs = np.nonzero(band.any(axis=0))[0]
    return (xs.min() + xs.max()) / 2


def bell_mouth(f):
    """The blunderbuss's mouth: the top-right end of the brass (the hat's buckle is further left)."""
    r, g, b, a = (f[..., i] for i in range(4))
    brass = (a > 200) & (r > 170) & (g > 120) & (b < 110) & (r - b > 90)
    ys, xs = np.nonzero(brass)
    score = xs - 0.5 * ys
    best = np.argsort(score)[-40:]
    return float(xs[best].mean()), float(ys[best].mean())


centres = [blind_centre(f) for f in poses]
left = max(c for c in centres) + 4
right = max(f.shape[1] - c for f, c in zip(poses, centres)) + 4
height = max(f.shape[0] for f in poses) + 4
k = FOX_W / (left + right)
cw, ch = FOX_W, int(np.ceil(height * k))
sheet = Image.new('RGBA', (cw * len(poses), ch), (0, 0, 0, 0))
mouths = []
for i, (f, c) in enumerate(zip(poses, centres)):
    img = scale(to_image(f), k)
    x0 = int(round(left * k - c * k))
    y0 = ch - img.size[1]  # sitting on the cell's bottom edge
    sheet.alpha_composite(img, (i * cw + x0, y0))
    mx, my = bell_mouth(f)
    mouths.append((round((x0 + mx * k) / cw, 3), round((y0 + my * k) / ch, 3)))
report['fox-aim.webp'] = {
    'frames': len(poses), 'cell': (cw, ch), 'bytes': save_webp(sheet, 'fox-aim.webp'),
    # Fractions of a cell, per pose: where the bell's mouth is (the scene's FOX_MUZZLE).
    'muzzle': mouths,
}

# The delivered muzzle strip (muzzle.png) is clipped flat on every frame, so it is not used:
# the scene draws the blunderbuss's flash itself (SVG) and puffs grey clouds for the smoke.

# --- BANG!: the word and the burst behind it, animated by the scene's crash styles ---------------
word = trim(key(load('bang-word.png')))
img = scale(to_image(word), 900 / word.shape[1])
report['bang-word.webp'] = {'size': img.size, 'bytes': save_webp(img, 'bang-word.webp', quality=88)}
burst = trim(key(load('bang-burst.png')))
img = scale(to_image(burst), 760 / burst.shape[1])
report['bang-burst.webp'] = {'size': img.size, 'bytes': save_webp(img, 'bang-burst.webp', quality=86)}

logo = trim(key(load('lucky_duck_winged_mascot_logo.png')))
img = scale(to_image(logo), 960 / logo.shape[1])
report['logo.webp'] = {'size': img.size, 'bytes': save_webp(img, 'logo.webp', quality=88)}

thumb = Image.open(os.path.join(SRC, 'lucky_duck_golden_sky_chase.png')).convert('RGB').resize((1024, 768), Image.LANCZOS)
thumb.save(os.path.join(EXTRA_OUT, 'lobby-thumbnail.jpg'), 'JPEG', quality=88, optimize=True, progressive=True)
report['lobby-thumbnail.jpg'] = {'size': thumb.size, 'where': os.path.join(EXTRA_OUT, 'lobby-thumbnail.jpg')}

json.dump(report, sys.stdout, indent=1, default=lambda o: o.tolist() if hasattr(o, 'tolist') else str(o))
print()
