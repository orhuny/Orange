<?php

declare(strict_types=1);

/*
 * Trident of Poseidon's look and sound (config('slots.themes'), games.config.theme =
 * trident-of-poseidon). Symbols are keyed by the model's letters (TridentOfPoseidon::SYMBOLS);
 * M, the pearl, comes in four colours by value (`tiers`) and the page prints its value.
 *
 * The art is built by scripts/art/process-trident-of-poseidon.py into
 * public/images/trident-of-poseidon/, with art.json. The shared page plays it like Sugar
 * Orchard's (the fx keys keep the page's names: frostBurst is the splash, comet the water jet);
 * what only this sea has is in the kit, public/js/orange/slot-kit-tides.js (`kit`, `kitArt`).
 * Every URL carries the file's mtime, so a rebuilt file reaches players at once.
 */

$dir = public_path('images/trident-of-poseidon');
$img = static fn (string $path): string => $path === '' ? '' : asset("images/trident-of-poseidon/{$path}").'?v='.(is_file("{$dir}/{$path}") ? filemtime("{$dir}/{$path}") : 0);
/** @var array<string, mixed> $art */
$art = is_file("{$dir}/art.json") ? (array) json_decode((string) file_get_contents("{$dir}/art.json"), true) : [];
$fx = (array) ($art['fx'] ?? []);
$list = static fn (string $key): array => array_map($img, (array) ($fx[$key] ?? []));
$one = static fn (string $key): string => $img((string) ($fx[$key] ?? ''));
$plaqueSize = is_file("{$dir}/plaque.webp") ? getimagesize("{$dir}/plaque.webp") : false;

$names = [
    'A' => ['Hippocampus', '#ffd35a'], 'B' => ['Sea-war helmet', '#ff6a3d'], 'C' => ['Conch horn', '#ffb35c'],
    'D' => ['Amphora', '#5aa8ff'], 'E' => ['Crab', '#ff4b3a'], 'F' => ['Pufferfish', '#ffd84a'],
    'G' => ['Sea turtle', '#5fe08a'], 'H' => ['Starfish', '#ff9a2e'], 'I' => ['Nautilus', '#4fe6e0'],
    'S' => ['Scatter', '#7ff3ff'],
];
$symbols = [];
foreach ($names as $letter => [$name, $colour]) {
    $symbols[$letter] = ['name' => $name, 'img' => $img((string) ($art['symbols'][$letter] ?? '')), 'colour' => $colour];
}
$frame = (array) ($art['frame'] ?? []);

return [
    'sound' => 'tides',
    'kit' => 'tides',
    'logo' => $img((string) ($art['logo'] ?? '')),
    'backgrounds' => [
        'day' => ['landscape' => $img('bg-day-landscape.webp'), 'portrait' => $img('bg-day-portrait.webp')],
        'night' => ['landscape' => $img('bg-night-landscape.webp'), 'portrait' => $img('bg-night-portrait.webp')],
    ],
    // In front of the background: kelp in each lower corner (the page's "branch" slots), and
    // jellyfish in the Abyss (its "lantern" slots). The kit adds the reef, light shafts, fish and bubbles.
    'scenery' => ['branch' => $img((string) ($art['scenery']['kelp'] ?? '')), 'lantern' => $img((string) ($art['scenery']['jelly'] ?? ''))],
    'frame' => [
        'src' => $img((string) ($frame['src'] ?? '')),
        'window' => $frame['window'] ?? ['x0' => 0, 'y0' => 0, 'x1' => 1, 'y1' => 1],
        'pieces' => [
            'left' => $img((string) ($frame['pieces']['left'] ?? '')),
            'right' => $img((string) ($frame['pieces']['right'] ?? '')),
            'fill' => $img((string) ($frame['pieces']['fill'] ?? '')),
            'px' => $frame['pieces']['px'] ?? ['w' => 1, 'h' => 1, 'x0' => 0, 'x1' => 1, 'y0' => 0, 'y1' => 1, 'cut_l' => 0, 'cut_r' => 1],
        ],
    ],
    'spin' => $img((string) ($art['spin'] ?? '')),
    'plaque' => ['src' => $img((string) ($art['plaque'] ?? '')), 'ratio' => $plaqueSize ? round($plaqueSize[0] / $plaqueSize[1], 4) : 1.78],
    'banners' => array_map($img, (array) ($art['banners'] ?? [])),
    // colour: the float text and sparks when the symbol pays.
    'symbols' => $symbols,
    // The pearls, by value: [from, colour], highest first (slot-timeline.js starTier).
    'stars' => array_map($img, (array) ($art['pearls'] ?? [])),
    'tiers' => [[100, 'abyss'], [20, 'gold'], [6, 'aqua'], [0, 'moon']],
    'fragments' => [],
    // Poseidon: { pose: { src, cx, ar, k, tip } } for idle, point, strike, roar, summon and
    // waiting. tip: his trident's tip (x, y, of the image), where the water jets start.
    'host' => array_map(
        static fn (array $pose): array => ['src' => $img((string) $pose['src'])] + $pose,
        (array) ($art['host'] ?? []),
    ),
    'hostReactions' => [
        'win' => ['point', 2600, 3],
        'big' => ['roar', 4600, 6],
        'free' => ['summon', 3400, 4],
        'strike' => ['strike', 1500, 3],
    ],
    // Not in this sea: caramel flames and dew drops (the kit's water does that job).
    'effects' => ['flames' => false, 'dew' => false],
    // A winning tumble takes a little longer: the whirlpools gather the winners before they burst.
    'pace' => ['hold' => 760, 'frost' => 300, 'after' => 220],
    'text' => [
        // Measured: free spins come 1 in 380 spins, 1 in 184 with the ante (docs/slots/trident-of-poseidon-maths.md).
        'anteOften' => 'about twice as often',
        'freeSpins' => 'Pearls that land in a winning free spin join the Tide, and the Tide multiplies every winning free spin after.',
    ],
    'fx' => [
        'frostBurst' => $one('splash-burst'),
        'frostRing' => $one('splash-ring'),
        'twinkle' => $one('twinkle'),
        'fireworks' => $one('splash-burst'),
        'rays' => $one('rays'),
        'comet' => $one('water-jet'),
        'crystals' => $list('droplets'),
        'sprinkles' => $list('treasure'),
    ],
    // Only the kit uses these.
    'kitArt' => [
        'whirlpool' => $one('whirlpool'),
        'sunrays' => $one('sunrays'),
        'surge' => $one('surge'),
        'bubbles' => $list('bubbles'),
        'fish' => $list('fish'),
        'reef' => $img((string) ($art['scenery']['reef'] ?? '')),
        'dolphin' => isset($art['dolphin']['src']) ? ['src' => $img((string) $art['dolphin']['src']), 'ar' => $art['dolphin']['ar']] : null,
        'tideRing' => $img((string) ($art['tide_ring'] ?? '')),
    ],
];
