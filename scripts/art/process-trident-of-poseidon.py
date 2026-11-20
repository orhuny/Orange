"""Builds Trident of Poseidon's served art (public/images/trident-of-poseidon/) from the raw
deliveries, and writes art.json, which resources/views/games/slot/themes/trident-of-poseidon/
theme.php reads. Prompts: docs/art/trident-of-poseidon-prompts.md.

    python -I scripts/art/process-trident-of-poseidon.py [--out DIR] [--only-host]

The raw files are read from storage/app/art/trident-of-poseidon/round1/. Pillow only. Most of the art comes already transparent; the frame comes on magenta,
and light effects on black.
"""

import hashlib
import json
import shutil
import sys
from pathlib import Path

from PIL import Image

sys.path.insert(0, str(Path(__file__).resolve().parent))
from artlib import (Out, cut_pieces, dusted, fade_bottom, fit, fit_width, frame_pieces, frame_window,  # noqa: E402
                    light, resized, square, trimmed, unmagenta)

REPO = Path(__file__).resolve().parents[2]
ROUNDS = [REPO / 'storage' / 'app' / 'art' / 'trident-of-poseidon' / 'round1']
OUT = Path(sys.argv[sys.argv.index('--out') + 1]) if '--out' in sys.argv else REPO / 'public' / 'images' / 'trident-of-poseidon'

SYMBOLS = {  # model letter -> (source, served name)
    'A': ('sym-a-hippocampus', 'hippocampus'), 'B': ('sym-b-helmet', 'helmet'), 'C': ('sym-c-conch', 'conch'),
    'D': ('sym-d-amphora', 'amphora'), 'E': ('sym-e-crab', 'crab'), 'F': ('sym-f-pufferfish', 'pufferfish'),
    'G': ('sym-g-turtle', 'turtle'), 'H': ('sym-h-starfish', 'starfish'), 'I': ('sym-i-nautilus', 'nautilus'),
    'S': ('sym-s-trident', 'trident'),
}
PEARLS = ['moon', 'aqua', 'gold', 'abyss']
GLOWS = {  # source -> (served name, size of the longer side)
    'fx-splash-burst': ('splash-burst', 512), 'fx-splash-ring': ('splash-ring', 512), 'fx-whirlpool': ('whirlpool', 640),
    'fx-aqua-rays': ('rays', 640), 'fx-sunrays': ('sunrays', 1280), 'fx-water-jet': ('water-jet', 640), 'fx-twinkle': ('twinkle', 192),
}
BANNERS = ['big-win', 'mega-win', 'epic-win', 'max-win', 'free-spins', 'total-win']
# The first logo was drawn before the rename and says PEARLS OF POSEIDON: never serve it.
SUPERSEDED_LOGOS = {'e657358e0210748cddce3c840ff2a002e854586b1ef6b8cfda735069d7703ca6'}

# Poseidon's poses: (source, eye line y, the trident's tip (x, y), scale). Measured by eye on
# the 1024×1536 deliveries. Every pose is cut HOST_BELOW_EYES below its eyes (at idle's scale),
# so his head stays put when he switches; the panels hide the water column below.
HOST_POSES = {
    'idle': ('char-poseidon-idle', 232, (140, 30), 1.0),
    'point': ('char-poseidon-point', 262, (106, 62), 1.0),
    'strike': ('char-poseidon-strike', 330, (982, 940), 1.0),
    'roar': ('char-poseidon-roar', 476, (490, 22), 1.0),
    'summon': ('char-poseidon-summon', 300, (126, 70), 1.0),
    'waiting': ('char-poseidon-waiting', 282, (232, 150), 1.0),
}
HOST_BELOW_EYES = 780
HOST_FADE = 170
SYMBOL_SIZE = 320

out = Out(OUT)


def source(name):
    for folder in ROUNDS:
        path = folder / f'{name}.png'
        if path.exists():
            return path
    return None


def load(name):
    path = source(name)
    if path is None:
        raise FileNotFoundError(f'{name}.png is in neither of {", ".join(str(r) for r in ROUNDS)}')
    return Image.open(path)


def host_pose(im, eyes, tip, scale):
    """One pose: from the top of the figure (a raised trident included) down to
    HOST_BELOW_EYES below his eyes, fading out at the bottom. Also: where his body's centre is
    at that line (cx, of the width), and the trident's tip (x, y, of the image)."""
    im = dusted(im)
    x0, y0, x1, y1 = im.getchannel('A').getbbox()
    bottom = min(y1, eyes + round(HOST_BELOW_EYES / scale))
    shown = im.crop((x0, y0, x1, bottom))
    point = {'x': round((tip[0] - x0) / shown.width, 4), 'y': round((tip[1] - y0) / shown.height, 4)}
    if scale != 1:
        shown = resized(shown, (round(shown.width * scale), round(shown.height * scale)))
    alpha = shown.getchannel('A')
    total = weighted = 0
    for y in range(round(shown.height * 0.86), shown.height, 2):
        for x in range(0, shown.width, 2):
            a = alpha.getpixel((x, y))
            total += a
            weighted += a * x
    cx = weighted / total / shown.width if total else 0.5
    shown = fade_bottom(shown, HOST_FADE)
    # Served at most 900 px tall: crisp on a big screen, light enough for a phone.
    k = min(1.0, 900 / shown.height)
    h = shown.height
    if k < 1:
        shown = resized(shown, (round(shown.width * k), round(shown.height * k)))
    return shown, {'cx': round(cx, 4), 'ar': round(shown.width / shown.height, 4), 'tip': point, 'h': h}


def build_host():
    """host/: Poseidon's poses. art.json host: { pose: { src, cx, ar, k, tip: { x, y } } }.
    ar: width / height; k: its height next to idle's at the same scale (a raised trident makes
    it taller); tip: of its size."""
    if (out.root / 'host').exists():
        shutil.rmtree(out.root / 'host')
    host = {}
    for pose, (name, eyes, tip, scale) in HOST_POSES.items():
        if source(name):
            im, meta = host_pose(load(name), eyes, tip, scale)
            host[pose] = {'src': out.save(im, f'host/poseidon-{pose}.webp', quality=88), **meta}
    base = host['idle']['h'] if 'idle' in host else 1
    for meta in host.values():
        meta['k'] = round(meta.pop('h') / base, 4)
    print('host', ', '.join(f"{pose} (cx {p['cx']}, k {p['k']})" for pose, p in host.items()))
    return host


def main():
    OUT.mkdir(parents=True, exist_ok=True)
    if '--only-host' in sys.argv:
        art = json.loads((OUT / 'art.json').read_text())
        art['host'] = build_host()
        (OUT / 'art.json').write_text(json.dumps(art, indent=1) + '\n', newline='\n')
        return

    shutil.rmtree(OUT)
    OUT.mkdir(parents=True)
    art = {'symbols': {}, 'pearls': {}, 'fx': {}, 'scenery': {}, 'banners': {}}

    for letter, (name, served) in SYMBOLS.items():
        art['symbols'][letter] = out.save(square(dusted(load(name)), SYMBOL_SIZE), f'symbols/{served}.webp')
    for tier in PEARLS:
        art['pearls'][tier] = out.save(square(dusted(load(f'sym-m-pearl-{tier}')), SYMBOL_SIZE), f'symbols/pearl-{tier}.webp')
    print('symbols', len(art['symbols']) + len(art['pearls']))

    for name, (served, size) in GLOWS.items():
        art['fx'][served] = out.save(light(fit(load(name).convert('RGB'), size)), f'fx/{served}.webp', quality=86)
    art['fx']['bubbles'] = cut_pieces(out, load('fx-bubbles').convert('RGB'), 'fx/bubble', 300, 0.3, glow=True)
    art['fx']['droplets'] = cut_pieces(out, load('fx-droplets').convert('RGB'), 'fx/droplet', 300, 0.3, glow=True)
    art['fx']['fish'] = cut_pieces(out, dusted(load('fx-fish')), 'fx/fish', 2000, 0.5)
    art['fx']['treasure'] = cut_pieces(out, dusted(load('fx-treasure')), 'fx/treasure', 600, 0.4)
    surge = trimmed(dusted(load('fx-surge')))
    art['fx']['surge'] = out.save(fit_width(surge, 1600), 'fx/surge.webp', quality=84)
    print('fx', len(art['fx']['bubbles']), 'bubbles,', len(art['fx']['droplets']), 'droplets,', len(art['fx']['fish']), 'fish,', len(art['fx']['treasure']), 'treasure')

    for name in ('day', 'night'):
        out.save(load(f'bg-{name}-landscape').convert('RGB').resize((1920, 1080), Image.LANCZOS), f'bg-{name}-landscape.webp', quality=82)
        out.save(load(f'bg-{name}-portrait').convert('RGB').resize((1080, 1920), Image.LANCZOS), f'bg-{name}-portrait.webp', quality=82)
    art['scenery']['kelp'] = out.save(fit(trimmed(dusted(load('fg-kelp'))), 900), 'fg-kelp.webp')
    art['scenery']['reef'] = out.save(fit(trimmed(dusted(load('fg-reef'))), 900), 'fg-reef.webp')
    art['scenery']['jelly'] = out.save(fit(trimmed(dusted(load('fg-jelly'))), 360), 'fg-jelly.webp')
    dolphin = trimmed(dusted(load('char-dolphin')))
    art['dolphin'] = {'src': out.save(fit(dolphin, 560), 'dolphin.webp'), 'ar': round(dolphin.width / dolphin.height, 4)}

    frame = trimmed(unmagenta(load('ui-frame')))
    art['frame'] = {'src': out.save(fit(frame, 1300), 'frame.webp', quality=88), 'window': frame_window(frame), 'pieces': frame_pieces(out, frame, 1300)}
    art['spin'] = out.save(square(dusted(load('ui-spin')), 256, pad=1.0), 'spin.webp')
    art['plaque'] = out.save(fit(trimmed(dusted(load('ui-plaque'))), 1000), 'plaque.webp')
    art['tide_ring'] = out.save(square(dusted(load('ui-tide-ring')), 360, pad=1.0), 'tide-ring.webp')
    logo = source('txt-logo')
    current = logo is not None and hashlib.sha256(logo.read_bytes()).hexdigest() not in SUPERSEDED_LOGOS
    art['logo'] = out.save(fit(trimmed(dusted(Image.open(logo))), 900), 'logo.webp') if current else None
    for name in BANNERS:
        art['banners'][name] = out.save(fit(trimmed(dusted(load(f'txt-{name}'))), 1000), f'banners/{name}.webp')
    out.save(load('key-thumbnail').convert('RGB').resize((1200, 900), Image.LANCZOS), 'thumbnail.jpg')
    art['host'] = build_host()

    (OUT / 'art.json').write_text(json.dumps(art, indent=1) + '\n', newline='\n')
    print('frame window', art['frame']['window'], 'pieces', art['frame']['pieces']['px'], '· logo', 'yes' if art['logo'] else 'waiting for the TRIDENT OF POSEIDON logo')


if __name__ == '__main__':
    main()
