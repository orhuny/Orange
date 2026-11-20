"""Builds Sugar Orchard's game art (public/images/sugar-orchard) from the delivered sources.

    python -I scripts/art/process-sugar-orchard.py              (Python 3 with Pillow; no numpy)
    python -I scripts/art/process-sugar-orchard.py --out DIR    (writes elsewhere, to compare)

The sources are round 3 of docs/art/sugar-orchard-prompts.md, delivered as PNG in
storage/app/art/sugar-orchard/round3 (not in git):
- Cut-outs come with real transparency.
- Light effects come on black. Their brightness becomes their transparency (light()), so
  they composite cleanly anywhere and look added.
- Scenes are full pictures.

What it builds:
- symbols/: the nine symbols, the scatter basket and the four multiplier stars. Each is
  trimmed, centred on a square, 320 px.
- frag/: each symbol's explosion pieces, one sprite per piece.
- fx/: the frost burst, ring and crust, the twinkle, the fireworks, the golden rays, the comet,
  and the crystals (one sprite each), all as light on transparent; the sprinkles and petals.
- the backgrounds, the foreground branch and lantern, the frame, spin button, plaque, logo
  and banners. The frame also comes in pieces (left, right, a stretchable strip of line),
  so a wide grid keeps its ornaments in shape;
- thumbnail.jpg: the lobby tile, uploaded in the admin panel.
- host/: the game's host, the Queen Bee (rounds 4 and 5, from storage/app/art/sugar-orchard/round4):
  each delivered pose as her upper body, fading out at the bottom, her wing, and her worker
  bee with its wing. --only-host builds just these, into the existing art.json, in seconds.
- art.json:
  - each symbol's pieces, with where each sits in the burst (so the page throws it outward
    from there);
  - the sprite lists;
  - the frame's inner window, so the page fits the grid exactly inside it;
  - the host's poses, with where her body's centre is (so the page stands her over the panels).

The folder holds only what this builds (it starts from empty), and the hand-made
thumbnail-v*.jpg lobby tiles, which it keeps.
"""
import json
import shutil
import sys
from collections import deque
from pathlib import Path

from PIL import Image, ImageChops, ImageFilter, ImageOps

REPO = Path(__file__).resolve().parents[2]
SRC = REPO / 'storage' / 'app' / 'art' / 'sugar-orchard' / 'round3'
OUT = Path(sys.argv[sys.argv.index('--out') + 1]) if '--out' in sys.argv else REPO / 'public' / 'images' / 'sugar-orchard'

SYMBOLS = {  # model letter -> (source, file)
    'A': ('sym-a-toffee', 'toffee'),
    'B': ('sym-b-peppermint', 'peppermint'),
    'C': ('sym-c-gummy-bear', 'gummy-bear'),
    'D': ('sym-d-truffle', 'truffle'),
    'E': ('sym-e-orange', 'orange'),
    'F': ('sym-f-cherries', 'cherries'),
    'G': ('sym-g-strawberry', 'strawberry'),
    'H': ('sym-h-lemon', 'lemon'),
    'I': ('sym-i-kiwi', 'kiwi'),
    'S': ('sym-s-scatter-basket', 'basket'),
}
STARS = ['sky', 'emerald', 'gold', 'ruby']
FRAGMENTS = {'A': 'toffee', 'B': 'peppermint', 'C': 'gummy-bear', 'D': 'truffle', 'E': 'orange',
             'F': 'cherries', 'G': 'strawberry', 'H': 'lemon', 'I': 'kiwi'}
GLOWS = {  # source -> (file, size of the longer side)
    'fx-frost-burst': ('frost-burst', 512),
    'fx-frost-ring': ('frost-ring', 512),
    'fx-frost-crust': ('frost-crust', 320),
    'fx-twinkle': ('twinkle', 160),
    'fx-fireworks': ('fireworks', 768),
    'fx-golden-rays': ('golden-rays', 768),
    'fx-comet': ('comet', 512),
}
BANNERS = ['big-win', 'mega-win', 'epic-win', 'max-win', 'free-spins', 'total-win']
# The game's host, the Queen Bee (round 4, its own folder). Poses not delivered yet are skipped;
# the page falls back to idle for them.
HOST_SRC = REPO / 'storage' / 'app' / 'art' / 'sugar-orchard' / 'round4'
# The generator drew each pose at its own size and place (raised arms made her smaller, a lean
# moved her), so each is brought to idle's scale and cut at the same point of her body: a
# fixed distance below her chin. Measured by eye on each source, in its own pixels:
# - chin: her chin's y;
# - root: where her wings meet her upper back (hidden behind her);
# - scale: her size next to idle's (her height from antennae to hem, checked on her face).
HOST_POSES = {  # pose -> (source, chin y, wing root (x, y), scale)
    'idle': ('char-queen-idle-b', 304, (385, 490), 1.0),  # the picked variant
    'waiting': ('char-queen-waiting', 312, (450, 470), 1.0),
    'cheer': ('char-queen-cheer', 357, (555, 540), 1.19),
    'wow': ('char-queen-wow', 436, (505, 545), 1.1),
    'honey': ('char-queen-honey', 380, (430, 505), 1.18),
    'kiss': ('char-queen-kiss', 308, (385, 490), 1.0),  # she blows her bees off her palm on a win
}
# Where her worker bees leave her (round 5): the open palm she holds out, in the pose's own
# pixels, measured by eye. Bees only fly from a pose that has one.
HOST_PALMS = {
    'kiss': (735, 290),
    'idle': (880, 305),
}
HOST_BELOW_CHIN = 584  # px (idle's scale) shown below her chin, down to mid-thigh; the panels hide the rest
HOST_FADE = 120  # px at the bottom that fade out, so no hard edge can show
HOST_WING = 'char-queen-wing'  # one wing, root at the lower left; mirrored to sweep up and back
HOST_WING_LENGTH = 500  # px (idle's scale): the wing's height on her
# Her worker bee (round 5): a body facing right, and one wing (root at the lower left, mirrored
# like hers). In the body's pixels: where its wings meet its back, and the wing's height on it.
BEE = 'char-bee-worker'
BEE_WING = 'char-bee-wing'
BEE_WING_ROOT = (560, 450)
BEE_WING_LENGTH = 400
SYMBOL_SIZE = 320
PIECE_SCALE = 0.32  # pieces are cut at full size, then shrunk to this
WEBP = {'quality': 90, 'method': 6}


def load(name):
    return Image.open(SRC / f'{name}.png')


def trimmed(im, margin=0):
    box = im.getchannel('A').point(lambda v: 255 if v > 8 else 0).getbbox()
    x0, y0, x1, y1 = box
    return im.crop((max(0, x0 - margin), max(0, y0 - margin), min(im.width, x1 + margin), min(im.height, y1 + margin)))


FLOOR = 8  # the generator's near-black: below this a light effect is fully transparent
_LIFT = [0 if v <= FLOOR else round((v - FLOOR) * 255 / (255 - FLOOR)) for v in range(256)]


def light(im):
    """A light effect on black -> its light on transparent: the brightest channel becomes
    the alpha, the colour is un-premultiplied. Drawn normally, it then looks as if added."""
    r, g, b = (c.point(_LIFT) for c in im.convert('RGB').split())
    a = ImageChops.lighter(ImageChops.lighter(r, g), b)
    return Image.merge('RGBa', (r, g, b, a)).convert('RGBA')


def resized(im, size):
    """Resize with premultiplied alpha, so transparent edges don't darken."""
    if im.mode != 'RGBA':
        return im.resize(size, Image.LANCZOS)
    return im.convert('RGBa').resize(size, Image.LANCZOS).convert('RGBA')


def fit(im, longest):
    k = longest / max(im.size)
    return resized(im, (max(1, round(im.width * k)), max(1, round(im.height * k))))


def square(im, size, pad=1.04):
    cut = trimmed(im)
    side = int(max(cut.size) * pad)
    sq = Image.new('RGBA', (side, side))
    sq.paste(cut, ((side - cut.width) // 2, (side - cut.height) // 2))
    return resized(sq, (size, size))


def save(im, rel, **opts):
    path = OUT / rel
    path.parent.mkdir(parents=True, exist_ok=True)
    if rel.endswith('.jpg'):
        im.convert('RGB').save(path, 'JPEG', quality=86, optimize=True, progressive=True)
    else:
        im.save(path, 'WEBP', **{**WEBP, **opts})
    return rel


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


def cut_pieces(im, prefix, min_area, scale, glow=False):
    """Every separate piece of a sheet as its own sprite. Cut-outs keep only their piece's
    pixels; light effects (on black) are cropped with their black."""
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
        rel = save(resized(sprite, size), f'{prefix}-{k}.webp')
        x0, y0, x1, y1 = bbox
        sprites.append({
            'src': Path(rel).name,
            # where the piece sits in its sheet, from the centre, in sheet widths
            'x': round(((x0 + x1) / 2 - im.width / 2) / im.width, 4),
            'y': round(((y0 + y1) / 2 - im.height / 2) / im.height, 4),
            'w': round((x1 - x0) / im.width, 4),
            'h': round((y1 - y0) / im.height, 4),
        })
    return sprites


def frame_window(im):
    """The frame's inner edges: from the middle outward to the first solid pixel."""
    a = im.getchannel('A')
    w, h = im.size
    cx, cy = w // 2, h // 2
    left = next(x for x in range(cx, -1, -1) if a.getpixel((x, cy)) > 200) + 1
    right = next(x for x in range(cx, w) if a.getpixel((x, cy)) > 200)
    top = next(y for y in range(cy, -1, -1) if a.getpixel((cx, y)) > 200) + 1
    bottom = next(y for y in range(cy, h) if a.getpixel((cx, y)) > 200)
    return {'x0': round(left / w, 4), 'y0': round(top / h, 4), 'x1': round(right / w, 4), 'y1': round(bottom / h, 4)}


def frame_pieces(frame, width):
    """The frame cut so it fits a grid of any width without stretching its ornaments:
    - a left piece (corner and side ornaments);
    - a right piece;
    - a strip of plain gold line that the page stretches between them.
    The cuts fall where the top and bottom edges hold nothing but the line. Measurements
    are in the frame's own pixels."""
    a = frame.getchannel('A')
    w, h = frame.size
    win = frame_window(frame)
    x0, x1, y0, y1 = round(win['x0'] * w), round(win['x1'] * w), round(win['y0'] * h), round(win['y1'] * h)
    rows = [y for y in range(h) if y < y0 - 14 or y >= y1 + 14]  # everything but the line itself

    def plain(x):
        return sum(1 for y in rows if a.getpixel((x, y)) > 40) <= 2

    def longest_run(lo, hi):
        best, start = (0, lo, lo), None
        for x in range(lo, hi + 1):
            if x < hi and plain(x):
                start = x if start is None else start
            elif start is not None:
                best = max(best, (x - start, start, x))
                start = None
        return best

    _, l_start, _ = longest_run(x0 + 10, w // 2)
    _, _, r_end = longest_run(w // 2, x1 - 10)
    cut_l, cut_r = l_start + 4, r_end - 4
    k = width / w
    size = lambda im: (max(4, round(im.width * k)), round(im.height * k))  # noqa: E731
    left, right, fill = frame.crop((0, 0, cut_l, h)), frame.crop((cut_r, 0, w, h)), frame.crop((cut_l, 0, cut_l + 8, h))
    return {
        'left': save(resized(left, size(left)), 'frame-left.webp', quality=88),
        'right': save(resized(right, size(right)), 'frame-right.webp', quality=88),
        'fill': save(resized(fill, size(fill)), 'frame-fill.webp', quality=88),
        'px': {'w': w, 'h': h, 'x0': x0, 'x1': x1, 'y0': y0, 'y1': y1, 'cut_l': cut_l, 'cut_r': cut_r},
    }


def dusted(im):
    """The image without the generator's alpha dust (near-invisible pixels all over)."""
    im = im.convert('RGBA')
    im.putalpha(im.getchannel('A').point(lambda v: 0 if v <= FLOOR else v))
    return im


def host_pose(im, chin, root, scale, palm=None):
    """One pose of the host, at idle's scale: her upper body, from the top of the figure
    (raised hands included) down to HOST_BELOW_CHIN below her chin, fading out at the bottom.
    Also: where her body's centre is at that line (cx, of the width), so the page stands her
    over the panels whatever her arms do, her wings' root (x, y) and length (h), and the palm
    her bees leave from (x, y), of the image's size."""
    im = dusted(im)
    x0, y0, x1, y1 = im.getchannel('A').getbbox()
    bottom = min(y1, chin + round(HOST_BELOW_CHIN / scale))
    shown = im.crop((x0, y0, x1, bottom))
    wx, wy = (root[0] - x0) / shown.width, (root[1] - y0) / shown.height
    hand = {'x': round((palm[0] - x0) / shown.width, 4), 'y': round((palm[1] - y0) / shown.height, 4)} if palm else None
    if scale != 1:
        shown = resized(shown, (round(shown.width * scale), round(shown.height * scale)))
    alpha = shown.getchannel('A')

    total = weighted = 0
    for y in range(round(shown.height * 0.88), shown.height):
        for x in range(shown.width):
            a = alpha.getpixel((x, y))
            total += a
            weighted += a * x
    cx = weighted / total / shown.width if total else 0.5

    start = shown.height - HOST_FADE
    ramp = Image.new('L', (1, shown.height), 255)
    for y in range(start, shown.height):
        ramp.putpixel((0, y), round(255 * (shown.height - 1 - y) / (shown.height - 1 - start)))
    shown.putalpha(ImageChops.multiply(alpha, ramp.resize(shown.size)))
    w, h = shown.size
    wing = {'x': round(wx, 4), 'y': round(wy, 4), 'h': round(HOST_WING_LENGTH / h, 4)}
    return shown, {'cx': round(cx, 4), 'ar': round(w / h, 4), 'wing': wing, **({'palm': hand} if hand else {})}


def wing_art(source, rel, size):
    """A wing, mirrored so it sweeps up and back from the shoulders, and where its root is
    along its bottom edge (rx, of the width)."""
    im = ImageOps.mirror(trimmed(dusted(Image.open(HOST_SRC / f'{source}.png'))))
    alpha = im.getchannel('A')
    total = weighted = 0
    for y in range(im.height - 12, im.height):  # the narrow root, at the bottom
        for x in range(im.width):
            a = alpha.getpixel((x, y))
            total += a
            weighted += a * x
    rx = weighted / total / im.width if total else 1.0
    im = fit(im, size)
    return {'src': save(im, rel), 'rx': round(rx, 4), 'ar': round(im.width / im.height, 4)}


def host_bee():
    """Her worker bee: its body facing right, its wing, the wings' root on the body (x, y)
    and their height (h), of the body's size."""
    body = dusted(Image.open(HOST_SRC / f'{BEE}.png'))
    x0, y0, x1, y1 = body.getchannel('A').point(lambda v: 255 if v > 8 else 0).getbbox()
    body = body.crop((x0, y0, x1, y1))
    wing = {
        **wing_art(BEE_WING, 'host/bee-wing.webp', 200),
        'x': round((BEE_WING_ROOT[0] - x0) / body.width, 4),
        'y': round((BEE_WING_ROOT[1] - y0) / body.height, 4),
        'h': round(BEE_WING_LENGTH / body.height, 4),
    }
    ar = body.width / body.height
    return {'src': save(fit(body, 240), 'host/bee.webp'), 'ar': round(ar, 4), 'wing': wing}


def build_host():
    """host/: the host's poses, her wing and her worker bee. art.json:
    - host: { pose: { src, cx, ar, k, wing: { x, y, h }, palm?: { x, y } } }. ar: width / height;
      k: its height next to idle's, at the same scale (raised hands make it taller); wing and
      palm: of its size.
    - host_wing: { src, rx, ar }, or nothing before the wing is delivered.
    - host_bee: { src, ar, wing: { src, rx, ar, x, y, h } }, or nothing before the bee and its
      wing are delivered."""
    if (OUT / 'host').exists():
        shutil.rmtree(OUT / 'host')
    host = {}
    for pose, (source, chin, root, scale) in HOST_POSES.items():
        path = HOST_SRC / f'{source}.png'
        if path.exists():
            im, meta = host_pose(Image.open(path), chin, root, scale, HOST_PALMS.get(pose))
            host[pose] = {'src': save(im, f'host/queen-{pose}.webp'), **meta, 'h': im.height}
    base = host['idle']['h'] if 'idle' in host else 1
    for meta in host.values():
        meta['k'] = round(meta.pop('h') / base, 4)
    wing = wing_art(HOST_WING, 'host/wing.webp', 640) if (HOST_SRC / f'{HOST_WING}.png').exists() else None
    bee = host_bee() if all((HOST_SRC / f'{n}.png').exists() for n in (BEE, BEE_WING)) else None
    print('host', ', '.join(f"{pose} (cx {p['cx']}, k {p['k']})" for pose, p in host.items()), '· wing' if wing else '', '· bee' if bee else '')
    return host, wing, bee


def main():
    if '--only-host' in sys.argv:  # quick: the host alone, into the existing art.json
        art = json.loads((OUT / 'art.json').read_text())
        art['host'], art['host_wing'], art['host_bee'] = build_host()
        (OUT / 'art.json').write_text(json.dumps(art, indent=1) + '\n', newline='\n')
        return

    # The hand-made lobby tiles (thumbnail-v*.jpg) aren't built here: keep them.
    keep = {p.name: p.read_bytes() for p in OUT.glob('thumbnail-v*.jpg')} if OUT.exists() else {}
    if OUT.exists():
        shutil.rmtree(OUT)
    OUT.mkdir(parents=True)
    for name, data in keep.items():
        (OUT / name).write_bytes(data)
    art = {'symbols': {}, 'stars': {}, 'fragments': {}, 'fx': {}, 'banners': {}}

    for letter, (source, name) in SYMBOLS.items():
        art['symbols'][letter] = save(square(load(source), SYMBOL_SIZE), f'symbols/{name}.webp')
    for tier in STARS:
        art['stars'][tier] = save(square(load(f'sym-m-star-{tier}'), SYMBOL_SIZE), f'symbols/star-{tier}.webp')
    print('symbols', len(art['symbols']) + len(art['stars']))

    for letter, name in FRAGMENTS.items():
        sheet = load(f'frag-{letter.lower()}-{name}').convert('RGBA')
        art['fragments'][letter] = cut_pieces(sheet, f'frag/{letter.lower()}', min_area=900, scale=PIECE_SCALE)
        print('fragments', letter, len(art['fragments'][letter]))

    for source, (name, size) in GLOWS.items():
        art['fx'][name] = save(light(fit(load(source).convert('RGB'), size)), f'fx/{name}.webp', quality=86)
    art['fx']['crystals'] = [p['src'] for p in cut_pieces(load('fx-crystals').convert('RGB'), 'fx/crystal', 400, 0.18, glow=True)]
    art['fx']['sprinkles'] = [p['src'] for p in cut_pieces(load('fx-sprinkles').convert('RGBA'), 'fx/sprinkle', 300, 0.22)]
    art['fx']['blossoms'] = [p['src'] for p in cut_pieces(load('fx-blossoms').convert('RGBA'), 'fx/blossom', 600, 0.2)]
    print('fx', len(art['fx']['crystals']), 'crystals,', len(art['fx']['sprinkles']), 'sprinkles,', len(art['fx']['blossoms']), 'blossoms')

    for name in ('day', 'night'):
        save(load(f'bg-{name}-landscape').convert('RGB').resize((1920, 1080), Image.LANCZOS), f'bg-{name}-landscape.webp', quality=82)
        save(load(f'bg-{name}-portrait').convert('RGB').resize((1080, 1920), Image.LANCZOS), f'bg-{name}-portrait.webp', quality=82)
    save(fit(trimmed(load('fg-branch')), 900), 'fg-branch.webp')
    save(fit(trimmed(load('fg-lantern')), 360), 'fg-lantern.webp')

    frame = trimmed(load('ui-frame'))
    art['frame'] = {'src': save(fit(frame, 1300), 'frame.webp', quality=88), 'window': frame_window(frame), 'pieces': frame_pieces(frame, 1300)}
    art['spin'] = save(square(load('ui-spin'), 256, pad=1.0), 'spin.webp')
    art['plaque'] = save(fit(trimmed(load('ui-plaque')), 1000), 'plaque.webp')
    art['logo'] = save(fit(trimmed(load('txt-logo')), 900), 'logo.webp')
    for name in BANNERS:
        art['banners'][name] = save(fit(trimmed(load(f'txt-{name}')), 1000), f'banners/{name}.webp')
    save(load('key-thumbnail').convert('RGB').resize((1200, 900), Image.LANCZOS), 'thumbnail.jpg')
    art['host'], art['host_wing'], art['host_bee'] = build_host()

    (OUT / 'art.json').write_text(json.dumps(art, indent=1) + '\n', newline='\n')
    print('frame window', art['frame']['window'])


if __name__ == '__main__':
    main()
