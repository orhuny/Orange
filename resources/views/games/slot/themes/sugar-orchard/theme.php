<?php

declare(strict_types=1);

/*
 * Sugar Orchard's look and sound (config('slots.themes'), games.config.theme = sugar-orchard).
 * Symbols are keyed by the model's letters (SugarOrchard::SYMBOLS); M, the multiplier star,
 * comes in four colours by value (slot-timeline.js starTier) and the page prints its value.
 *
 * The art is built by scripts/art/process-sugar-orchard.py into public/images/sugar-orchard/,
 * with art.json: each symbol's explosion pieces, the sprite lists and the frame's window.
 * Every URL carries the file's mtime, so a rebuilt file reaches players at once.
 */

$dir = public_path('images/sugar-orchard');
$img = static fn (string $path): string => asset("images/sugar-orchard/{$path}").'?v='.(is_file("{$dir}/{$path}") ? filemtime("{$dir}/{$path}") : 0);
/** @var array<string, mixed> $art */
$art = is_file("{$dir}/art.json") ? (array) json_decode((string) file_get_contents("{$dir}/art.json"), true) : [];
$fx = (array) ($art['fx'] ?? []);
$sprites = static fn (string $key): array => array_map(static fn (string $file): string => $img("fx/{$file}"), (array) ($fx[$key] ?? []));
$plaqueSize = is_file("{$dir}/plaque.webp") ? getimagesize("{$dir}/plaque.webp") : false;

$names = [
    'A' => ['Toffee', '#f6c445'], 'B' => ['Peppermint', '#ff3b3b'], 'C' => ['Gummy bear', '#52d14a'],
    'D' => ['Truffle', '#a8642c'], 'E' => ['Orange', '#ff8a1c'], 'F' => ['Cherries', '#c0172e'],
    'G' => ['Strawberry', '#ff3d4f'], 'H' => ['Lemon', '#ffe14d'], 'I' => ['Kiwi', '#8ccf3a'],
    'S' => ['Scatter', '#ffd84a'],
];
$symbols = [];
foreach ($names as $letter => [$name, $colour]) {
    $symbols[$letter] = ['name' => $name, 'img' => $img((string) ($art['symbols'][$letter] ?? '')), 'colour' => $colour];
}

return [
    'sound' => 'orchard',
    'logo' => $img('logo.webp'),
    'backgrounds' => [
        'day' => ['landscape' => $img('bg-day-landscape.webp'), 'portrait' => $img('bg-day-portrait.webp')],
        'night' => ['landscape' => $img('bg-night-landscape.webp'), 'portrait' => $img('bg-night-portrait.webp')],
    ],
    // In front of the background: a branch in each lower corner; lanterns at night.
    'scenery' => ['branch' => $img('fg-branch.webp'), 'lantern' => $img('fg-lantern.webp')],
    // The grid sits exactly in the frame's window (fractions of the frame image).
    // Portrait draws it whole; landscape in pieces around a wider grid (left, right, and a
    // strip of line stretched between them). px: the frame's own pixels.
    'frame' => [
        'src' => $img('frame.webp'),
        'window' => $art['frame']['window'] ?? ['x0' => 0, 'y0' => 0, 'x1' => 1, 'y1' => 1],
        'pieces' => [
            'left' => $img('frame-left.webp'),
            'right' => $img('frame-right.webp'),
            'fill' => $img('frame-fill.webp'),
            'px' => $art['frame']['pieces']['px'] ?? ['w' => 1, 'h' => 1, 'x0' => 0, 'x1' => 1, 'y0' => 0, 'y1' => 1, 'cut_l' => 0, 'cut_r' => 1],
        ],
    ],
    'spin' => $img('spin.webp'),
    'plaque' => ['src' => $img('plaque.webp'), 'ratio' => $plaqueSize ? round($plaqueSize[0] / $plaqueSize[1], 4) : 1.78],
    'banners' => array_map($img, (array) ($art['banners'] ?? [])),
    // colour: the float text and sparks when the symbol pays.
    'symbols' => $symbols,
    'stars' => array_map($img, (array) ($art['stars'] ?? [])),
    // Each symbol's explosion: its pieces, where each sits in the burst (x, y from the centre)
    // and its size (w, h), in widths of the symbol's square.
    'fragments' => array_map(
        static fn (array $pieces): array => array_map(static fn (array $p): array => ['src' => $img("frag/{$p['src']}")] + $p, $pieces),
        (array) ($art['fragments'] ?? []),
    ),
    // The host, the Queen Bee: { pose: { src, cx, ar, k, wing, palm? } } for idle, waiting, cheer,
    // wow, honey and kiss. cx: her body's centre (of the width); ar: width / height; k: its height
    // next to idle's at the same scale; wing: her wings' root (x, y) and length (h); palm: where
    // her bees leave her hand (x, y), all of the image's size.
    'host' => array_map(
        static fn (array $pose): array => ['src' => $img((string) $pose['src'])] + $pose,
        (array) ($art['host'] ?? []),
    ),
    // Her wing, drawn twice behind her and fluttered by the page: { src, rx, ar }, rx: its root
    // along its bottom edge (of the width).
    'hostWing' => isset($art['host_wing']['src']) ? ['src' => $img((string) $art['host_wing']['src'])] + $art['host_wing'] : null,
    // Her worker bee, which the page flies around the grid: { src, ar, wing: { src, rx, ar, x, y, h } }.
    // ar: the body's width / height; wing: its art, its root along its bottom edge (rx, of its
    // width) and shape (ar), and where it sits on the body (x, y) and its height (h), of the body.
    'hostBee' => isset($art['host_bee']['src'], $art['host_bee']['wing']['src'])
        ? ['src' => $img((string) $art['host_bee']['src']), 'wing' => ['src' => $img((string) $art['host_bee']['wing']['src'])] + $art['host_bee']['wing']] + $art['host_bee']
        : null,
    'fx' => [
        'frostBurst' => $img((string) ($fx['frost-burst'] ?? '')),
        'frostRing' => $img((string) ($fx['frost-ring'] ?? '')),
        'frostCrust' => $img((string) ($fx['frost-crust'] ?? '')),
        'twinkle' => $img((string) ($fx['twinkle'] ?? '')),
        'fireworks' => $img((string) ($fx['fireworks'] ?? '')),
        'rays' => $img((string) ($fx['golden-rays'] ?? '')),
        'comet' => $img((string) ($fx['comet'] ?? '')),
        'crystals' => $sprites('crystals'),
        'sprinkles' => $sprites('sprinkles'),
        'blossoms' => $sprites('blossoms'),
    ],
];
