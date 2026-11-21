"""Builds Tutti Frutti's served art from the raw deliveries.

    python -I scripts/art/process-tutti-frutti.py [--out DIR]

Reads storage/app/art/tutti-frutti/round1/ (named as in docs/art/tutti-frutti-prompts.md) and
writes public/images/tutti-frutti/ plus art.json, which the theme reads
(resources/views/games/slot/themes/tutti-frutti/theme.php). Every file must be delivered: the
script stops on a missing one.

What it does:
- symbols, kisses and the scatter: trimmed, squared and shrunk once to SYMBOL_SIZE (twice the
  largest cell), with a light sharpen, so they stay crisp on high-density screens;
- glows (light on black) become light on transparent;
- juice (white liquid on black) becomes a grey, shaded liquid on transparent that the page
  tints with each fruit's colour (liquid());
- the sheets (fx-droplets, fx-confetti) are cut into single pieces;
- the frame is keyed off its magenta; its window is exactly 5:3, the reels' shape;
- each hostess's poses are trimmed and measured: the soles, the body's centre at the feet,
  the size next to her idle pose, and the points the page sends effects from (HOSTS);
- backgrounds are served at 2560 px on their long side, never stretched past it.
Pillow only (scripts/art/artlib.py)."""

import json
import shutil
import sys
from pathlib import Path

from PIL import Image, ImageChops, ImageFilter

sys.path.insert(0, str(Path(__file__).resolve().parent))
from artlib import Out, cut_pieces, dusted, fit, fit_width, frame_pieces, frame_window, light, pieces, resized, square, trimmed, unmagenta  # noqa: E402

REPO = Path(__file__).resolve().parents[2]
RAW = REPO / 'storage' / 'app' / 'art' / 'tutti-frutti' / 'round1'
OUT = Path(sys.argv[sys.argv.index('--out') + 1]) if '--out' in sys.argv else REPO / 'public' / 'images' / 'tutti-frutti'

SYMBOLS = {  # model letter -> (source, served name)
    'A': ('sym-a-peach', 'peach'), 'B': ('sym-b-pineapple', 'pineapple'), 'C': ('sym-c-dragonfruit', 'dragonfruit'),
    'D': ('sym-d-pomegranate', 'pomegranate'), 'E': ('sym-e-mango', 'mango'), 'F': ('sym-f-lime', 'lime'),
    'G': ('sym-g-blueberries', 'blueberries'), 'H': ('sym-h-fig', 'fig'), 'I': ('sym-i-lychee', 'lychee'),
    'S': ('sym-s-cocktail', 'cocktail'),
}
KISSES = {1: 'pink', 2: 'aqua', 3: 'lime', 5: 'gold'}  # a kiss's value -> its colour
GLOWS = {  # source -> (served name, longer side)
    'fx-twinkle': ('twinkle', 256), 'fx-heart-trail': ('heart-trail', 640), 'fx-heart-burst': ('heart-burst', 560),
    'fx-sunburst': ('sunburst', 640), 'fx-firework-heart': ('firework', 640),
}
BANNERS = ['big-win', 'mega-win', 'epic-win', 'max-win', 'free-spins', 'total-win']
SYMBOL_SIZE = 384
BG_SIZE = 2560
GRID_ASPECT = 5 / 3  # the reels: 5 square cells by 3, as the frame's window is drawn

# Each hostess's poses: source, and named points in the source's pixels (measured by eye on the
# 1024 x 1536 deliveries). palm: where Bella's blown kiss leaves her hand; finger: her flick.
HOSTS = {
    'marina': {
        'idle': ('char-marina-idle', {}), 'cheer': ('char-marina-cheer', {}), 'shake': ('char-marina-shake', {}),
        'dance': ('char-marina-dance', {}), 'party': ('char-marina-party', {}), 'waiting': ('char-marina-waiting', {}),
    },
    'bella': {
        'idle': ('char-bella-idle', {}), 'wink': ('char-bella-wink', {'finger': (102, 220)}), 'kiss': ('char-bella-kiss', {'palm': (265, 232)}),
        'dance': ('char-bella-dance', {}), 'party': ('char-bella-party', {}), 'waiting': ('char-bella-waiting', {}),
    },
}
HOST_HEIGHT = 1300  # served height of the tallest pose

out = Out(OUT)


def load(name):
    path = RAW / f'{name}.png'
    if not path.exists():
        sys.exit(f'missing {path}: deliver it (docs/art/tutti-frutti-prompts.md)')
    return Image.open(path)


def crisp(im):
    """A light sharpen of the colour after a shrink; the alpha edge is left as it is."""
    r, g, b, a = im.split()
    rgb = Image.merge('RGB', (r, g, b)).filter(ImageFilter.UnsharpMask(radius=1.2, percent=45, threshold=2))
    return Image.merge('RGBA', (*rgb.split(), a))


def symbol(name):
    return crisp(square(dusted(load(name)), SYMBOL_SIZE))


def liquid(im):
    """White liquid on black -> grey liquid on transparent: the brightness sets the cover (soft
    at the very edge) and the shading stays in the grey, so a tint (multiply) keeps it 3-D."""
    lum = ImageChops.lighter(ImageChops.lighter(*im.convert('RGB').split()[:2]), im.convert('RGB').split()[2])
    alpha = lum.point(lambda v: 0 if v < 14 else 255 if v > 60 else round((v - 14) * 255 / 46))
    shade = lum.point(lambda v: min(255, round(v * 1.08)))
    return Image.merge('RGBA', (shade, shade, shade, alpha))


def liquid_pieces(im, prefix, min_area, scale):
    """Every separate drop of a liquid sheet as its own sprite, largest first."""
    mask = im.convert('L').point(lambda v: 255 if v > 28 else 0)
    sprites = []
    for k, (_area, bbox, lab) in enumerate(sorted(pieces(mask, min_area), key=lambda p: -p[0]), start=1):
        sprite = liquid(im.crop(bbox))
        sprite.putalpha(ImageChops.multiply(sprite.getchannel('A'), lab.crop(bbox)))
        size = (max(1, round(sprite.width * scale)), max(1, round(sprite.height * scale)))
        sprites.append(out.save(resized(sprite, size), f'{prefix}-{k}.webp'))
    return sprites


def host_pose(im, points):
    """One full-length pose, trimmed: its image, height, the body's centre at the feet (cx), the
    feet line and its named points, all as fractions of the served image."""
    im = dusted(im)
    x0, y0, x1, y1 = im.getchannel('A').getbbox()
    shown = im.crop((x0, y0, x1, y1))
    w, h = shown.size
    solid = shown.getchannel('A').point(lambda v: 255 if v > 200 else 0)
    # The soles: the lowest row with a solid stretch of sandal in it.
    sole = next((y for y in range(h - 1, h // 2, -1) if sum(1 for x in range(0, w, 2) if solid.getpixel((x, y))) >= 10), h - 1)
    feet = (sole + 1) / h
    alpha = shown.getchannel('A')
    total = weighted = 0
    for y in range(max(0, round(h * (feet - 0.06))), min(h, round(h * feet)), 2):
        for x in range(0, w, 2):
            a = alpha.getpixel((x, y))
            total += a
            weighted += a * x
    meta = {'cx': round(weighted / total / w if total else 0.5, 4), 'ar': round(w / h, 4), 'feet': round(feet, 4), 'h': h}
    for name, (px, py) in points.items():
        meta[name] = {'x': round((px - x0) / w, 4), 'y': round((py - y0) / h, 4)}
    return shown, meta


def build_hosts():
    """host/: both hostesses. art.json hosts: { who: { pose: { src, cx, ar, k, feet, palm?, finger? } } };
    k: the pose's height next to her idle's (every pose is drawn at the same scale)."""
    hosts = {}
    for who, poses in HOSTS.items():
        measured = {pose: host_pose(load(name), points) for pose, (name, points) in poses.items()}
        tallest = max(meta['h'] for _, meta in measured.values())
        k = min(1.0, HOST_HEIGHT / tallest)
        base = measured['idle'][1]['h']
        hosts[who] = {}
        for pose, (shown, meta) in measured.items():
            if k < 1:
                shown = resized(shown, (round(shown.width * k), round(shown.height * k)))
            meta['k'] = round(meta.pop('h') / base, 4)
            hosts[who][pose] = {'src': out.save(crisp(shown), f'host/{who}-{pose}.webp', quality=88), **meta}
        print(who, ', '.join(f"{pose} (cx {p['cx']}, k {p['k']}, feet {p['feet']})" for pose, p in hosts[who].items()))
    return hosts


def main():
    if OUT.exists():
        shutil.rmtree(OUT)
    OUT.mkdir(parents=True)
    art = {'symbols': {}, 'wilds': {}, 'fx': {}, 'banners': {}}

    for letter, (name, served) in SYMBOLS.items():
        art['symbols'][letter] = out.save(symbol(name), f'symbols/{served}.webp')
    for value, colour in KISSES.items():
        art['wilds'][str(value)] = out.save(symbol(f'sym-m-kiss-{colour}'), f'symbols/kiss-{colour}.webp')

    for name, (served, size) in GLOWS.items():
        art['fx'][served] = out.save(light(fit(load(name).convert('RGB'), size)), f'fx/{served}.webp', quality=86)
    art['fx']['sweep'] = out.save(light(fit_width(load('fx-sweep').convert('RGB'), 1600)), 'fx/sweep.webp', quality=84)
    art['fx']['splash'] = out.save(liquid(fit(load('fx-splash').convert('RGB'), 512)), 'fx/splash.webp', quality=88)
    art['fx']['droplets'] = liquid_pieces(load('fx-droplets').convert('RGB'), 'fx/drop', 400, 0.5)
    art['fx']['confetti'] = cut_pieces(out, dusted(load('fx-confetti')), 'fx/confetti', 600, 0.55)

    for kind in ('day', 'night'):
        for shape in ('landscape', 'portrait'):
            bg = load(f'bg-{kind}-{shape}').convert('RGB')
            size = (BG_SIZE, round(BG_SIZE * 9 / 16)) if shape == 'landscape' else (round(BG_SIZE * 9 / 16), BG_SIZE)
            out.save(bg.resize(size, Image.LANCZOS), f'bg-{kind}-{shape}.webp', quality=84)
    art['tropical'] = out.save(crisp(fit(trimmed(dusted(load('fg-tropical'))), 1000)), 'fg-tropical.webp')
    art['podium'] = out.save(crisp(fit(trimmed(dusted(load('fg-podium'))), 900)), 'podium.webp')

    frame = trimmed(unmagenta(load('ui-frame')))
    art['frame'] = {'src': out.save(fit(frame, 1500), 'frame.webp', quality=88), 'window': frame_window(frame),
                    'pieces': frame_pieces(out, frame, 1500, aspect=GRID_ASPECT)}
    art['spin'] = out.save(crisp(square(dusted(load('ui-spin')), 320, pad=1.0)), 'spin.webp')
    art['plaque'] = out.save(fit(trimmed(dusted(load('ui-plaque'))), 1000), 'plaque.webp')
    art['buy'] = out.save(crisp(square(dusted(load('ui-buy-badge')), 360, pad=1.0)), 'buy-badge.webp')
    art['amore'] = out.save(crisp(square(dusted(load('ui-amore-heart')), 400, pad=1.0)), 'amore-heart.webp')

    for name in BANNERS:
        art['banners'][name] = out.save(fit(trimmed(dusted(load(f'txt-{name}'))), 1000), f'banners/{name}.webp')
    art['logo'] = out.save(crisp(fit(trimmed(dusted(load('txt-logo'))), 1000)), 'logo.webp')

    # The lobby tile: the key art, with the logo over its calm top-left (the prompt keeps that area for it).
    thumb = load('key-thumbnail').convert('RGB').resize((1200, 900), Image.LANCZOS)
    logo = fit(Image.open(OUT / art['logo']).convert('RGBA'), 540)
    thumb.paste(logo, (36, 28), logo)
    out.save(thumb, 'thumbnail.jpg')

    art['hosts'] = build_hosts()
    (OUT / 'art.json').write_text(json.dumps(art, indent=1) + '\n', newline='\n')
    print('frame window', art['frame']['window'], 'pieces', art['frame']['pieces']['px'])
    print(f"{len(art['fx']['droplets'])} drops, {len(art['fx']['confetti'])} confetti pieces")


if __name__ == '__main__':
    main()
