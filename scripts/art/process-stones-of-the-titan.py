"""Builds Stones of the Titan's served art (public/images/stones-of-the-titan/) from the raw
deliveries, and writes art.json, which resources/views/games/slot/themes/stones-of-the-titan/
theme.php reads. Prompts: docs/art/stones-of-the-titan-prompts.md.

    python -I scripts/art/process-stones-of-the-titan.py [--out DIR] [--only-host]

The raw files are read from storage/app/art/stones-of-the-titan/round1/, under the names in
the prompts doc. Most come transparent; the frame comes on magenta, light effects on black.

Until a file is delivered, a placeholder drawn here takes its place at the same served path
(art.json "placeholders" lists them), so the game is playable before the art is done and each
delivery replaces its placeholder on the next build. Pillow only.
"""

import json
import math
import random
import shutil
import sys
from pathlib import Path

from PIL import Image, ImageDraw, ImageFilter, ImageFont

sys.path.insert(0, str(Path(__file__).resolve().parent))
from artlib import (Out, cut_pieces, dusted, fit, fit_width, frame_pieces, frame_window,  # noqa: E402
                    light, resized, square, trimmed, unmagenta)

REPO = Path(__file__).resolve().parents[2]
ROUNDS = [REPO / 'storage' / 'app' / 'art' / 'stones-of-the-titan' / 'round1']
OUT = Path(sys.argv[sys.argv.index('--out') + 1]) if '--out' in sys.argv else REPO / 'public' / 'images' / 'stones-of-the-titan'

SYMBOLS = {  # model letter -> (source, served name, the placeholder's colour)
    'A': ('sym-a-caged-star', 'caged-star', (255, 214, 96)), 'B': ('sym-b-orrery', 'orrery', (64, 120, 255)),
    'C': ('sym-c-shield', 'shield', (214, 36, 58)), 'D': ('sym-d-sword', 'sword', (30, 196, 110)),
    'E': ('sym-e-ammonite', 'ammonite', (240, 122, 42)), 'F': ('sym-f-geode', 'geode', (255, 201, 58)),
    'G': ('sym-g-amethyst', 'amethyst', (155, 77, 255)), 'H': ('sym-h-runestone', 'runestone', (46, 224, 208)),
    'I': ('sym-i-comet', 'comet', (222, 244, 255)), 'S': ('sym-s-eclipse', 'eclipse', (255, 224, 138)),
}
METEORS = {'ice': (127, 214, 255), 'jade': (62, 232, 160), 'ember': (255, 96, 42), 'sun': (255, 241, 176)}
GLOWS = {  # source -> (served name, size of the longer side)
    'fx-shatter-burst': ('shatter-burst', 512), 'fx-shockwave': ('shockwave', 512), 'fx-cracks': ('cracks', 384),
    'fx-vortex': ('vortex', 512), 'fx-corona': ('corona', 640), 'fx-meteor-trail': ('meteor-trail', 640), 'fx-twinkle': ('twinkle', 192),
}
SHEETS = {  # source -> (served prefix, art.json key, min piece area, scale, light on black)
    'fx-stardust': ('fx/stardust', 'stardust', 300, 0.3, True), 'fx-embers': ('fx/ember', 'embers', 300, 0.3, True),
    'fx-treasure': ('fx/treasure', 'treasure', 600, 0.4, False), 'fg-asteroids': ('fg/asteroid', 'asteroids', 2000, 0.5, False),
}
BANNERS = ['big-win', 'mega-win', 'epic-win', 'max-win', 'free-spins', 'total-win']
# The landscape grid's width / height on the page (--ar in resources/views/games/slot.blade.php).
GRID_ASPECT = 1.45
SYMBOL_SIZE = 320

# The Titan's poses, full length. Per pose, points measured by eye on the delivery, in its own
# pixels, where the kit needs him (the soles are found from the alpha; a 'feet' y overrides them): palm (the open hand the stones
# circle over), fist (the crush), star (the sceptre's caged star), finger (his pointing hand).
# Measured on the 1024×1536 deliveries of round 1; a pose not yet delivered has none (the page
# then falls back to another pose's point, or the figure's upper body). A regenerated pose needs
# its points measured again.
HOST_POSES = {
    'idle': ('char-titan-idle', {'palm': (870, 400), 'star': (120, 160), 'head': (440, 250)}),
    'command': ('char-titan-command', {'finger': (25, 325), 'star': (860, 280), 'head': (560, 270)}),
    'crush': ('char-titan-crush', {'fist': (850, 200), 'star': (120, 470), 'head': (480, 200)}),
    'roar': ('char-titan-roar', {'star': (290, 110), 'fist': (560, 680), 'head': (560, 430)}),
    'summon': ('char-titan-summon', {'palm': (930, 400), 'star': (110, 270), 'head': (560, 360)}),
    'waiting': ('char-titan-waiting', {'star': (300, 300), 'head': (470, 220)}),
}
HOST_HEIGHT = 1100  # served height of the tallest pose

out = Out(OUT)
placeholders = []
rng = random.Random(20261121)


def source(name):
    for folder in ROUNDS:
        path = folder / f'{name}.png'
        if path.exists():
            return path
    return None


def load(name, placeholder):
    """The delivery, or its placeholder (recorded in art.json) until it arrives."""
    path = source(name)
    if path is not None:
        return Image.open(path)
    placeholders.append(name)
    return placeholder()


# ---- Placeholder drawing --------------------------------------------------------------------

def font(size):
    for name in ('arialbd.ttf', 'Arial Bold.ttf', 'DejaVuSans-Bold.ttf'):
        for folder in ('C:/Windows/Fonts', '/usr/share/fonts/truetype/dejavu', '/Library/Fonts'):
            try:
                return ImageFont.truetype(f'{folder}/{name}', size)
            except OSError:
                continue
    return ImageFont.load_default()


def mix(a, b, t):
    return tuple(round(x + (y - x) * t) for x, y in zip(a, b))


def radial(size, inner, outer, power=1.0):
    """An RGBA disc: inner colour at the centre fading to outer at the rim, transparent outside."""
    w, h = size
    # radial_gradient is 0 at the centre and 255 at the middle of an edge (the rim, d = 1).
    g = Image.radial_gradient('L').resize((w, h), Image.BILINEAR)
    d = [min(1.0, v / 255) ** power for v in range(256)]
    bands = [g.point([round(i + (o - i) * d[v]) for v in range(256)]) for i, o in zip(inner, outer)]
    alpha = g.point([255 if v < 254 else 0 for v in range(256)])
    return Image.merge('RGBA', (*bands, alpha))


def glow_dot(draw_on, cx, cy, r, colour, strength=1.0):
    """Adds a soft round glow (for light effects drawn on black)."""
    layer = Image.new('RGB', draw_on.size)
    d = ImageDraw.Draw(layer)
    for k in range(12, 0, -1):
        f = k / 12
        c = tuple(round(v * strength * (1 - f) ** 1.6) for v in colour)
        d.ellipse((cx - r * f, cy - r * f, cx + r * f, cy + r * f), fill=c)
    draw_on.paste(Image.composite(layer, draw_on, layer.convert('L').point(lambda v: 255 if v else 0)), (0, 0))


def lighten(base, layer):
    from PIL import ImageChops
    return ImageChops.lighter(base, layer)


def ph_symbol(letter, colour):
    def draw():
        s = 640
        im = Image.new('RGBA', (s, s))
        dark = mix(colour, (10, 6, 16), 0.72)
        if letter == 'S':
            rays = Image.new('RGBA', (s, s))
            d = ImageDraw.Draw(rays)
            for i in range(8):
                a = i * math.pi / 4
                pts = [(s / 2 + math.cos(a) * s * .47, s / 2 + math.sin(a) * s * .47),
                       (s / 2 + math.cos(a + .2) * s * .3, s / 2 + math.sin(a + .2) * s * .3),
                       (s / 2 + math.cos(a - .2) * s * .3, s / 2 + math.sin(a - .2) * s * .3)]
                d.polygon(pts, fill=(255, 200, 80, 255))
            im.alpha_composite(rays)
            im.alpha_composite(radial((int(s * .7), int(s * .7)), (255, 236, 160), (170, 110, 20), 1.6), (int(s * .15), int(s * .15)))
            corona = radial((int(s * .46), int(s * .46)), (255, 255, 240), (255, 190, 60), .5)
            im.alpha_composite(corona, (int(s * .27), int(s * .27)))
            ImageDraw.Draw(im).ellipse((s * .31, s * .31, s * .69, s * .69), fill=(6, 4, 10, 255))
            return im
        im.alpha_composite(radial((int(s * .84), int(s * .84)), mix(colour, (255, 255, 255), .45), dark, 1.3), (int(s * .08), int(s * .08)))
        d = ImageDraw.Draw(im)
        d.ellipse((s * .08, s * .08, s * .92, s * .92), outline=(255, 214, 120, 255), width=14)
        f = font(int(s * .42))
        d.text((s / 2, s / 2), letter, font=f, anchor='mm', fill=(255, 255, 255, 255), stroke_width=10, stroke_fill=(20, 10, 30, 255))
        return im
    return draw


def ph_meteor(colour):
    def draw():
        s = 640
        im = Image.new('RGBA', (s, s))
        d = ImageDraw.Draw(im)
        for i in range(9):  # the crown of starfire, up to the right
            a = -math.pi * .75 + i * .2
            x, y = s / 2 + math.cos(a) * s * .3, s / 2 + math.sin(a) * s * .3
            d.ellipse((x - s * .1, y - s * .16, x + s * .1, y + s * .1), fill=(*mix(colour, (255, 255, 255), .3), 200))
        im = im.filter(ImageFilter.GaussianBlur(14))
        im.alpha_composite(radial((int(s * .76), int(s * .76)), (64, 52, 60), (18, 14, 20), 1.0), (int(s * .12), int(s * .12)))
        im.alpha_composite(radial((int(s * .6), int(s * .6)), mix(colour, (255, 255, 255), .55), colour, .9), (int(s * .2), int(s * .2)))
        return im
    return draw


def ph_glow(kind):
    def draw():
        s = 1024
        im = Image.new('RGB', (s * 2, s) if kind == 'meteor-trail' else (s * 21 // 9, s) if kind == 'sweep' else (s, s))
        d = ImageDraw.Draw(im)
        w, h = im.size
        gold, teal, white = (255, 200, 90), (60, 220, 230), (255, 250, 230)
        if kind == 'shatter-burst':
            for _ in range(140):
                a = rng.uniform(0, math.tau)
                r = rng.uniform(.1, .45) * s
                d.line((w / 2, h / 2, w / 2 + math.cos(a) * r, h / 2 + math.sin(a) * r), fill=rng.choice((gold, teal)), width=rng.randint(2, 6))
            im = im.filter(ImageFilter.GaussianBlur(6))
            glow_dot(im, w / 2, h / 2, s * .28, white)
        elif kind == 'shockwave':
            for k in range(14):
                r = s * (.4 - k * .012)
                c = mix(white, (0, 0, 0), k / 14)
                d.ellipse((w / 2 - r, h / 2 - r, w / 2 + r, h / 2 + r), outline=c, width=6)
            im = im.filter(ImageFilter.GaussianBlur(3))
        elif kind == 'cracks':
            def crack(x, y, a, length, width):
                if length < 12 or width < 1:
                    return
                x2, y2 = x + math.cos(a) * length, y + math.sin(a) * length
                d.line((x, y, x2, y2), fill=white if width > 3 else gold, width=width)
                crack(x2, y2, a + rng.uniform(-.5, .5), length * .72, width - 1)
                if rng.random() < .45:
                    crack(x2, y2, a + rng.choice((-1, 1)) * rng.uniform(.5, 1.1), length * .55, width - 2)
            for i in range(7):
                crack(w / 2, h / 2, i * math.tau / 7 + rng.uniform(-.3, .3), s * .14, 7)
            im = lighten(im.filter(ImageFilter.GaussianBlur(5)), im)
        elif kind == 'vortex':
            for arm in range(4):
                pts = [(w / 2 + math.cos(t * 5 + arm * math.pi / 2) * s * .45 * (1 - t), h / 2 + math.sin(t * 5 + arm * math.pi / 2) * s * .45 * (1 - t))
                       for t in [i / 80 for i in range(80)]]
                d.line(pts, fill=gold if arm % 2 else teal, width=10)
            im = lighten(im.filter(ImageFilter.GaussianBlur(10)), im.filter(ImageFilter.GaussianBlur(2)))
            ImageDraw.Draw(im).ellipse((w / 2 - s * .07, h / 2 - s * .07, w / 2 + s * .07, h / 2 + s * .07), fill=(0, 0, 0))
        elif kind == 'corona':
            for _ in range(90):
                a = rng.uniform(0, math.tau)
                d.line((w / 2 + math.cos(a) * s * .18, h / 2 + math.sin(a) * s * .18, w / 2 + math.cos(a) * s * rng.uniform(.3, .48),
                        h / 2 + math.sin(a) * s * rng.uniform(.3, .48)), fill=rng.choice((gold, (255, 140, 110))), width=rng.randint(3, 8))
            im = im.filter(ImageFilter.GaussianBlur(9))
            d = ImageDraw.Draw(im)
            d.ellipse((w / 2 - s * .2, h / 2 - s * .2, w / 2 + s * .2, h / 2 + s * .2), outline=white, width=14)
            im = im.filter(ImageFilter.GaussianBlur(2))
            ImageDraw.Draw(im).ellipse((w / 2 - s * .185, h / 2 - s * .185, w / 2 + s * .185, h / 2 + s * .185), fill=(0, 0, 0))
        elif kind == 'meteor-trail':
            for i in range(60):
                t = i / 60
                r = h * (.04 + .12 * t)
                c = mix((0, 0, 0), gold if i % 2 else teal, t)
                d.ellipse((w * (.1 + .78 * t) - r, h / 2 - r, w * (.1 + .78 * t) + r, h / 2 + r), fill=c)
            im = im.filter(ImageFilter.GaussianBlur(12))
            glow_dot(im, w * .88, h / 2, h * .2, white)
        elif kind == 'twinkle':
            d.line((w / 2, h * .05, w / 2, h * .95), fill=white, width=6)
            d.line((w * .05, h / 2, w * .95, h / 2), fill=white, width=6)
            im = lighten(im.filter(ImageFilter.GaussianBlur(4)), im)
            glow_dot(im, w / 2, h / 2, s * .12, white)
        elif kind == 'sweep':
            for _ in range(400):
                x = rng.uniform(0, w * .8)
                y = rng.uniform(0, h)
                r = rng.uniform(20, 120) * (x / w + .3)
                d.ellipse((x - r, y - r, x + r, y + r), fill=mix((0, 0, 0), rng.choice((gold, (255, 150, 60), teal)), x / w * .6 + .2))
            im = im.filter(ImageFilter.GaussianBlur(28))
            for _ in range(500):
                x, y = rng.uniform(0, w * .8), rng.uniform(0, h)
                ImageDraw.Draw(im).ellipse((x - 2, y - 2, x + 2, y + 2), fill=white)
        return im
    return draw


def ph_sheet(kind):
    def draw():
        s = 1024
        glow = kind in ('stardust', 'embers')
        im = Image.new('RGB' if glow else 'RGBA', (s, s))
        d = ImageDraw.Draw(im)
        for row in range(6):
            for col in range(6):
                if kind == 'asteroids' and (row + col) % 3:
                    continue
                cx, cy = (col + .5) * s / 6 + rng.uniform(-20, 20), (row + .5) * s / 6 + rng.uniform(-20, 20)
                r = rng.uniform(.2, .38) * s / 6 * (2.2 if kind == 'asteroids' else 1)
                if kind == 'stardust':
                    d.ellipse((cx - r * .3, cy - r * .3, cx + r * .3, cy + r * .3), fill=rng.choice(((255, 250, 230), (255, 214, 120), (90, 230, 240))))
                elif kind == 'embers':
                    pts = [(cx + math.cos(a) * r * rng.uniform(.3, .6), cy + math.sin(a) * r * rng.uniform(.3, .6)) for a in [k * math.tau / 5 for k in range(5)]]
                    d.polygon(pts, fill=rng.choice(((255, 220, 140), (255, 150, 60), (90, 230, 240))))
                elif kind == 'treasure':
                    if (row + col) % 3:
                        d.ellipse((cx - r, cy - r * rng.uniform(.4, 1), cx + r, cy + r * rng.uniform(.4, 1)), fill=(240, 190, 60, 255), outline=(140, 90, 10, 255), width=4)
                    else:
                        c = rng.choice(((255, 190, 70), (155, 77, 255), (46, 224, 208), (222, 244, 255)))
                        pts = [(cx + math.cos(a) * r * rng.uniform(.5, .9), cy + math.sin(a) * r * rng.uniform(.5, .9)) for a in [k * math.tau / 6 for k in range(6)]]
                        d.polygon(pts, fill=(*c, 255))
                else:  # asteroids
                    pts = [(cx + math.cos(a) * r * rng.uniform(.7, 1), cy + math.sin(a) * r * rng.uniform(.7, 1)) for a in [k * math.tau / 9 for k in range(9)]]
                    d.polygon(pts, fill=(30, 26, 34, 255), outline=(200, 150, 60, 255), width=3)
        return im.filter(ImageFilter.GaussianBlur(3)) if glow else im
    return draw


def ph_background(night, portrait):
    def draw():
        w, h = (1080, 1920) if portrait else (1920, 1080)
        top, mid, low = ((40, 8, 24), (120, 20, 30), (6, 4, 14)) if night else ((70, 44, 110), (210, 140, 90), (20, 40, 70))
        im = Image.new('RGB', (w, h))
        d = ImageDraw.Draw(im)
        for y in range(h):
            t = y / h
            d.line((0, y, w, y), fill=mix(top, mid, t / .55) if t < .55 else mix(mid, low, (t - .55) / .45))
        clouds = Image.new('RGB', (w, h))
        cd = ImageDraw.Draw(clouds)
        for _ in range(26):
            x, y, r = rng.uniform(0, w), rng.uniform(0, h * .8), rng.uniform(.08, .22) * w
            c = rng.choice(((200, 40, 60), (120, 20, 80), (255, 160, 60))) if night else rng.choice(((255, 190, 110), (60, 200, 210), (230, 120, 150)))
            cd.ellipse((x - r, y - r * .55, x + r, y + r * .55), fill=c)
        im = Image.blend(im, clouds.filter(ImageFilter.GaussianBlur(90)), .35)
        d = ImageDraw.Draw(im)
        for _ in range(900):
            x, y = rng.uniform(0, w), rng.uniform(0, h)
            b = rng.randint(140, 255)
            r = rng.choice((1, 1, 1, 2))
            d.ellipse((x - r, y - r, x + r, y + r), fill=(b, b, min(255, b + 20)))
        if night:  # the black sun over the host's column
            cx, cy, r = (w * .8, h * .14, w * .12) if portrait else (w * .84, h * .3, h * .17)
            glow_dot(im, cx, cy, r * 2.2, (255, 170, 60))
            ImageDraw.Draw(im).ellipse((cx - r, cy - r, cx + r, cy + r), fill=(4, 2, 6), outline=(255, 236, 190), width=6)
        else:  # the ringed planet
            cx, cy, r = (w * .2, h * .1, w * .14) if portrait else (w * .14, h * .2, h * .16)
            ImageDraw.Draw(im).ellipse((cx - r, cy - r, cx + r, cy + r), fill=(210, 180, 120))
            ImageDraw.Draw(im).ellipse((cx - r * 1.9, cy - r * .35, cx + r * 1.9, cy + r * .35), outline=(240, 220, 170), width=5)
        return im
    return draw


def ph_panel(size, glow, radius=0.12, window=False):
    """A dark obsidian panel in a gold rim; window: a magenta 6:5 opening (the frame)."""
    def draw():
        w, h = size
        im = Image.new('RGBA', (w, h), (255, 0, 255, 255) if window else (0, 0, 0, 0))
        d = ImageDraw.Draw(im)
        r = round(min(w, h) * radius)
        d.rounded_rectangle((0, 0, w - 1, h - 1), radius=r, fill=(14, 10, 20, 255), outline=(232, 182, 80, 255), width=max(4, w // 90))
        d.rounded_rectangle((w * .025, h * .04, w * .975, h * .96), radius=r * .8, outline=(120, 80, 24, 255), width=max(2, w // 200))
        if glow:
            g = radial((int(w * .9), int(h * .6)), (*glow,), (14, 10, 20), 1.4)
            g.putalpha(g.getchannel('A').point(lambda v: v * 3 // 5))
            im.alpha_composite(g, (int(w * .05), int(h * .38)))
        if window:
            ww = w * .84
            wh = ww * 5 / 6
            x0, y0 = (w - ww) / 2, (h - wh) / 2
            d.rectangle((x0, y0, x0 + ww, y0 + wh), fill=(255, 0, 255, 255))
            for i in range(14):  # constellation studs on the band
                x = x0 + ww * (i + .5) / 14
                d.ellipse((x - 5, y0 * .45 - 5, x + 5, y0 * .45 + 5), fill=(255, 220, 140, 255))
        return im
    return draw


def ph_ring():
    s = 720
    im = Image.new('RGBA', (s, s))
    d = ImageDraw.Draw(im)
    for i in range(8):
        a = i * math.pi / 4 - math.pi / 2
        d.polygon([(s / 2 + math.cos(a) * s * .49, s / 2 + math.sin(a) * s * .49), (s / 2 + math.cos(a + .14) * s * .4, s / 2 + math.sin(a + .14) * s * .4),
                   (s / 2 + math.cos(a - .14) * s * .4, s / 2 + math.sin(a - .14) * s * .4)], fill=(240, 190, 70, 255))
    d.ellipse((s * .09, s * .09, s * .91, s * .91), fill=(232, 182, 80, 255))
    d.ellipse((s * .13, s * .13, s * .87, s * .87), fill=(40, 34, 44, 255))
    d.ellipse((s * .16, s * .16, s * .84, s * .84), fill=(0, 0, 0, 0))
    return im


def ph_spin():
    s = 512
    im = radial((s, s), (60, 40, 90), (12, 8, 20), 1.0)
    d = ImageDraw.Draw(im)
    d.ellipse((4, 4, s - 4, s - 4), outline=(232, 182, 80, 255), width=22)
    d.arc((s * .28, s * .28, s * .72, s * .72), 200, 340, fill=(255, 255, 255, 255), width=30)
    d.arc((s * .28, s * .28, s * .72, s * .72), 20, 160, fill=(255, 255, 255, 255), width=30)
    d.polygon([(s * .68, s * .3), (s * .78, s * .43), (s * .62, s * .44)], fill=(255, 255, 255, 255))
    d.polygon([(s * .32, s * .7), (s * .22, s * .57), (s * .38, s * .56)], fill=(255, 255, 255, 255))
    return im


def ph_rock(crystals):
    def draw():
        w, h = (1200, 800)
        im = Image.new('RGBA', (w, h))
        d = ImageDraw.Draw(im)
        if crystals:  # an outcrop entering from the lower-left corner
            d.polygon([(0, h), (0, h * .55), (w * .25, h * .62), (w * .5, h * .8), (w * .62, h)], fill=(24, 20, 28, 255))
            for i in range(7):
                x = w * (.05 + i * .07)
                c = ((46, 224, 208), (255, 190, 70), (155, 77, 255))[i % 3]
                top = h * rng.uniform(.1, .45)
                d.polygon([(x, h * .7), (x + w * .025, top), (x + w * .05, h * .7)], fill=(*c, 255), outline=(255, 255, 255, 200))
            return im
        d.polygon([(w * .06, h * .22), (w * .94, h * .22), (w * .82, h * .42), (w * .6, h * .72), (w * .5, h * .98), (w * .38, h * .7), (w * .18, h * .42)],
                  fill=(22, 18, 26, 255))
        d.ellipse((w * .06, h * .12, w * .94, h * .32), fill=(42, 36, 48, 255), outline=(232, 182, 80, 255), width=4)
        for _ in range(10):
            x = rng.uniform(w * .25, w * .75)
            d.line((x, h * .35, x + rng.uniform(-60, 60), h * .6), fill=(255, 210, 120, 255), width=3)
        return im
    return draw


def ph_host(pose):
    """A rough stand-in figure (head, torso, cloak, arms, legs, sceptre) in the pose's gesture, 2:3."""
    arms = {  # (left hand, right hand) in units of the figure; his left hand is on the image's left
        'idle': ((.3, .36), (.72, .5)), 'command': ((.02, .3), (.72, .5)), 'crush': ((.3, .14), (.72, .58)),
        'roar': ((.36, .38), (.66, .02)), 'summon': ((.04, .32), (.96, .32)), 'waiting': ((.46, .44), (.56, .44)),
    }[pose]

    def draw():
        w, h = 1024, 1536
        im = Image.new('RGBA', (w, h))
        d = ImageDraw.Draw(im)
        obsidian, crack, gold = (26, 22, 32, 255), (255, 214, 130, 255), (232, 182, 80, 255)
        sx, sy = lambda u: w * (.1 + u * .8), lambda v: h * (.04 + v * .93)
        d.polygon([(sx(.3), sy(.2)), (sx(.7), sy(.2)), (sx(.86), sy(.96)), (sx(.14), sy(.96))], fill=(20, 30, 80, 255))  # cloak
        neb = Image.new('RGBA', (w, h))
        ImageDraw.Draw(neb).ellipse((sx(.32), sy(-.04), sx(.72), sy(.2)), fill=(60, 140, 190, 200))
        im.alpha_composite(neb.filter(ImageFilter.GaussianBlur(30)))
        d.polygon([(sx(.34), sy(.18)), (sx(.66), sy(.18)), (sx(.6), sy(.5)), (sx(.4), sy(.5))], fill=obsidian)  # torso
        d.polygon([(sx(.4), sy(.5)), (sx(.49), sy(.5)), (sx(.47), sy(1)), (sx(.38), sy(1))], fill=obsidian)  # legs
        d.polygon([(sx(.51), sy(.5)), (sx(.6), sy(.5)), (sx(.62), sy(1)), (sx(.53), sy(1))], fill=obsidian)
        d.ellipse((sx(.42), sy(.02), sx(.58), sy(.16)), fill=obsidian)  # head
        d.ellipse((sx(.45), sy(.07), sx(.48), sy(.09)), fill=(255, 250, 220, 255))
        d.ellipse((sx(.52), sy(.07), sx(.55), sy(.09)), fill=(255, 250, 220, 255))
        for i in range(7):  # the halo of shards
            a = i * math.tau / 7
            x, y = sx(.5 + math.cos(a) * .13), sy(.0 + math.sin(a) * .025)
            d.polygon([(x - 10, y), (x, y - 14), (x + 12, y + 2), (x, y + 10)], fill=gold)
        for (hx, hy), shoulder in zip(arms, (.36, .64)):  # arms
            d.line((sx(shoulder), sy(.21), sx(hx), sy(hy)), fill=obsidian, width=58)
            d.ellipse((sx(hx) - 36, sy(hy) - 36, sx(hx) + 36, sy(hy) + 36), fill=obsidian)
        rx, ry = arms[1]  # the sceptre in his right hand
        d.line((sx(rx), sy(ry) - h * .3, sx(rx), sy(ry) + h * .32), fill=gold, width=16)
        glow_star = radial((90, 90), (255, 255, 240), (255, 190, 60), .6)
        im.alpha_composite(glow_star, (int(sx(rx) - 45), int(sy(ry) - h * .3 - 45)))
        for _ in range(26):  # the cosmic cracks
            x0, y0 = sx(rng.uniform(.38, .62)), sy(rng.uniform(.2, .95))
            d.line((x0, y0, x0 + rng.uniform(-40, 40), y0 + rng.uniform(10, 60)), fill=crack, width=3)
        d.rectangle((sx(.38), sy(.48), sx(.62), sy(.52)), fill=gold)  # belt
        d.ellipse((sx(.47), sy(.47), sx(.53), sy(.53)), fill=(10, 8, 14, 255), outline=gold, width=5)
        return im
    return draw


def ph_text(words, size=(1500, 500), two_lines=None):
    def draw():
        w, h = size
        im = Image.new('RGBA', (w, h))
        d = ImageDraw.Draw(im)
        lines = two_lines or [words]
        f = [font(int(h * (.2 if two_lines and i == 0 else .4 if two_lines else .62))) for i in range(len(lines))]
        ys = [h * .3, h * .7] if two_lines else [h / 2]
        for line, fy, ff in zip(lines, ys, f):
            d.text((w / 2, fy), line, font=ff, anchor='mm', fill=(255, 214, 96, 255), stroke_width=max(6, h // 40), stroke_fill=(30, 16, 6, 255))
        return im
    return draw


def ph_thumbnail(art_bg, logo):
    im = Image.open(art_bg).convert('RGB').resize((1200, 900))
    lg = Image.open(logo).convert('RGBA')
    lg = fit(lg, 900)
    im.paste(lg, ((1200 - lg.width) // 2, 60), lg)
    return im


# ---- The Titan ------------------------------------------------------------------------------

def host_pose(im, points):
    """One full-length pose, trimmed: its size, the body's centre at the feet (cx), the feet line
    and its named points, all as fractions of the served image."""
    im = dusted(im)
    x0, y0, x1, y1 = im.getchannel('A').getbbox()
    shown = im.crop((x0, y0, x1, y1))
    w, h = shown.size
    alpha = shown.getchannel('A')
    # The soles: the lowest row with a solid stretch of boot in it (a stated line overrides it).
    if points.get('feet'):
        feet = (points['feet'] - y0) / h
    else:
        solid = alpha.point(lambda v: 255 if v > 200 else 0)
        sole = next((y for y in range(h - 1, h // 2, -1) if sum(1 for x in range(0, w, 2) if solid.getpixel((x, y))) >= 12), h - 1)
        feet = (sole + 1) / h
    total = weighted = 0
    for y in range(max(0, round(h * (feet - 0.06))), min(h, round(h * feet)), 2):
        for x in range(0, w, 2):
            a = alpha.getpixel((x, y))
            total += a
            weighted += a * x
    meta = {'cx': round(weighted / total / w if total else 0.5, 4), 'ar': round(w / h, 4), 'feet': round(feet, 4), 'h': h}
    for name in ('palm', 'fist', 'star', 'finger', 'head'):
        if points.get(name):
            px, py = points[name]
            meta[name] = {'x': round((px - x0) / w, 4), 'y': round((py - y0) / h, 4)}
    return shown, meta


def build_host():
    """host/: the Titan's poses. art.json host: { pose: { src, cx, ar, k, feet, palm?, fist?,
    star?, finger? } }. k: its height next to idle's (every pose is drawn at the same scale)."""
    if (out.root / 'host').exists():
        shutil.rmtree(out.root / 'host')
    host = {}
    # Once the real Titan is in, a pose not yet delivered is left out (the page shows idle for
    # it) rather than mixing a drawn stand-in with him.
    real = source(HOST_POSES['idle'][0]) is not None
    for pose, (name, points) in HOST_POSES.items():
        delivered = source(name) is not None
        if real and not delivered:
            placeholders.append(name)
            continue
        im = load(name, ph_host(pose))
        if not delivered:
            points = {'feet': 1460}
        shown, meta = host_pose(im, points)
        host[pose] = {'shown': shown, **meta}
    tallest = max(p['h'] for p in host.values())
    k = min(1.0, HOST_HEIGHT / tallest)
    base = host['idle']['h']
    for pose, meta in host.items():
        shown = meta.pop('shown')
        if k < 1:
            shown = resized(shown, (round(shown.width * k), round(shown.height * k)))
        meta['k'] = round(meta.pop('h') / base, 4)
        host[pose] = {'src': out.save(shown, f'host/titan-{pose}.webp', quality=88), **meta}
    print('host', ', '.join(f"{pose} (cx {p['cx']}, k {p['k']})" for pose, p in host.items()))
    return host


# ---- The build ------------------------------------------------------------------------------

def main():
    OUT.mkdir(parents=True, exist_ok=True)
    if '--only-host' in sys.argv:
        art = json.loads((OUT / 'art.json').read_text())
        placeholders.extend(p for p in art.get('placeholders', []) if not p.startswith('char-titan-'))
        art['host'] = build_host()
        art['placeholders'] = sorted(set(placeholders))
        (OUT / 'art.json').write_text(json.dumps(art, indent=1) + '\n', newline='\n')
        return

    shutil.rmtree(OUT)
    OUT.mkdir(parents=True)
    art = {'symbols': {}, 'meteors': {}, 'fx': {}, 'scenery': {}, 'cards': {}, 'banners': {}}

    for letter, (name, served, colour) in SYMBOLS.items():
        art['symbols'][letter] = out.save(square(dusted(load(name, ph_symbol(letter, colour))), SYMBOL_SIZE), f'symbols/{served}.webp')
    for tier, colour in METEORS.items():
        art['meteors'][tier] = out.save(square(dusted(load(f'sym-m-meteor-{tier}', ph_meteor(colour))), SYMBOL_SIZE), f'symbols/meteor-{tier}.webp')

    for name, (served, size) in GLOWS.items():
        art['fx'][served] = out.save(light(fit(load(name, ph_glow(served)).convert('RGB'), size)), f'fx/{served}.webp', quality=86)
    sweep = load('fx-sweep', ph_glow('sweep')).convert('RGB')
    art['fx']['sweep'] = out.save(light(fit_width(sweep, 1600)), 'fx/sweep.webp', quality=84)
    for name, (prefix, key, area, scale, glow) in SHEETS.items():
        sheet = load(name, ph_sheet(name.split('-', 1)[1]))
        target = art['scenery'] if name.startswith('fg-') else art['fx']
        target[key] = cut_pieces(out, sheet.convert('RGB') if glow else dusted(sheet), prefix, area, scale, glow=glow)

    for kind in ('day', 'night'):
        for shape in ('landscape', 'portrait'):
            size = (1920, 1080) if shape == 'landscape' else (1080, 1920)
            bg = load(f'bg-{kind}-{shape}', ph_background(kind == 'night', shape == 'portrait')).convert('RGB')
            out.save(bg.resize(size, Image.LANCZOS), f'bg-{kind}-{shape}.webp', quality=82)
    art['scenery']['crystals'] = out.save(fit(trimmed(dusted(load('fg-crystals', ph_rock(True)))), 900), 'fg-crystals.webp')
    art['pedestal'] = out.save(fit(trimmed(dusted(load('fg-pedestal', ph_rock(False)))), 900), 'pedestal.webp')

    frame = trimmed(unmagenta(load('ui-frame', ph_panel((1500, 1200), None, .05, window=True))))
    # The delivered frame's middle is widened to the grid (seam insertion); the plain placeholder's is a stretched strip.
    aspect = None if 'ui-frame' in placeholders else GRID_ASPECT
    art['frame'] = {'src': out.save(fit(frame, 1300), 'frame.webp', quality=88), 'window': frame_window(frame),
                    'pieces': frame_pieces(out, frame, 1300, aspect=aspect)}
    art['spin'] = out.save(square(dusted(load('ui-spin', ph_spin)), 256, pad=1.0), 'spin.webp')
    art['plaque'] = out.save(fit(trimmed(dusted(load('ui-plaque', ph_panel((1600, 900), (40, 70, 160))))), 1000), 'plaque.webp')
    art['tumble'] = out.save(fit(trimmed(dusted(load('ui-tumble-plaque', ph_panel((1600, 380), (150, 30, 40), .3)))), 900), 'tumble.webp')
    art['cards']['buy'] = out.save(fit(trimmed(dusted(load('ui-card-buy', ph_panel((750, 1000), (255, 170, 60))))), 520), 'card-buy.webp')
    art['cards']['ante'] = out.save(fit(trimmed(dusted(load('ui-card-ante', ph_panel((750, 1000), (40, 210, 200))))), 520), 'card-ante.webp')
    art['eclipse_ring'] = out.save(square(dusted(load('ui-eclipse-ring', ph_ring)), 360, pad=1.0), 'eclipse-ring.webp')

    for name in BANNERS:
        art['banners'][name] = out.save(fit(trimmed(dusted(load(f'txt-{name}', ph_text(name.replace('-', ' ').upper())))), 1000), f'banners/{name}.webp')
    art['logo'] = out.save(fit(trimmed(dusted(load('txt-logo', ph_text('', (1600, 800), ['STONES OF THE', 'TITAN'])))), 900), 'logo.webp')

    if source('key-thumbnail'):
        out.save(Image.open(source('key-thumbnail')).convert('RGB').resize((1200, 900), Image.LANCZOS), 'thumbnail.jpg')
    else:
        placeholders.append('key-thumbnail')
        out.save(ph_thumbnail(OUT / 'bg-day-landscape.webp', OUT / 'logo.webp'), 'thumbnail.jpg')
    art['host'] = build_host()

    art['placeholders'] = sorted(set(placeholders))
    (OUT / 'art.json').write_text(json.dumps(art, indent=1) + '\n', newline='\n')
    print('frame window', art['frame']['window'], 'pieces', art['frame']['pieces']['px'])
    print(f'{len(art["placeholders"])} placeholders:', ', '.join(art['placeholders']) or 'none, all art delivered')


if __name__ == '__main__':
    main()
