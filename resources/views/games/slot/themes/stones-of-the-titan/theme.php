<?php

declare(strict_types=1);

/*
 * Stones of the Titan's look and sound (config('slots.themes'), games.config.theme =
 * stones-of-the-titan). Symbols are keyed by the model's letters (StonesOfTheTitan::SYMBOLS);
 * M, the meteor, comes in four colours by value (`tiers`) and the page prints its value.
 *
 * The art is built by scripts/art/process-stones-of-the-titan.py into
 * public/images/stones-of-the-titan/, with art.json; until a file is delivered the script
 * draws a placeholder in its place. The screen is the "throne" layout
 * (resources/views/games/slot/layouts/throne.blade.php). The shared page plays it like the
 * other slots (the fx keys keep the page's names: frostBurst is the shatter, comet the
 * meteor's trail); what only the Titan's cosmos has is in the kit,
 * public/js/orange/slot-kit-cosmos.js (`kit`, `kitArt`). Every URL carries the file's mtime,
 * so a rebuilt file reaches players at once.
 */

$dir = public_path('images/stones-of-the-titan');
$img = static fn (string $path): string => $path === '' ? '' : asset("images/stones-of-the-titan/{$path}").'?v='.(is_file("{$dir}/{$path}") ? filemtime("{$dir}/{$path}") : 0);
/** @var array<string, mixed> $art */
$art = is_file("{$dir}/art.json") ? (array) json_decode((string) file_get_contents("{$dir}/art.json"), true) : [];
$fx = (array) ($art['fx'] ?? []);
$list = static fn (array $from, string $key): array => array_map($img, (array) ($from[$key] ?? []));
$one = static fn (string $key): string => $img((string) ($fx[$key] ?? ''));
$plaqueSize = is_file("{$dir}/plaque.webp") ? getimagesize("{$dir}/plaque.webp") : false;
$tumbleSize = is_file("{$dir}/tumble.webp") ? getimagesize("{$dir}/tumble.webp") : false;

$names = [
    'A' => ['Caged star', '#ffd35a'], 'B' => ['Orrery', '#5a8dff'], 'C' => ["Titan's shield", '#ff4a5a'],
    'D' => ['Sword in the meteorite', '#4ce08a'], 'E' => ['Ammonite', '#ff9a3c'], 'F' => ['Sun geode', '#ffd23f'],
    'G' => ['Amethyst', '#b77bff'], 'H' => ['Runestone', '#3ff0e0'], 'I' => ['Comet shard', '#e4f6ff'],
    'S' => ['Scatter', '#ffe9a6'],
];
$symbols = [];
foreach ($names as $letter => [$name, $colour]) {
    $symbols[$letter] = ['name' => $name, 'img' => $img((string) ($art['symbols'][$letter] ?? '')), 'colour' => $colour];
}
$frame = (array) ($art['frame'] ?? []);
$meteors = array_map($img, (array) ($art['meteors'] ?? []));

return [
    'layout' => 'throne',
    'sound' => 'cosmos',
    'kit' => 'cosmos',
    'logo' => $img((string) ($art['logo'] ?? '')),
    'backgrounds' => [
        'day' => ['landscape' => $img('bg-day-landscape.webp'), 'portrait' => $img('bg-day-portrait.webp')],
        'night' => ['landscape' => $img('bg-night-landscape.webp'), 'portrait' => $img('bg-night-portrait.webp')],
    ],
    // Crystal outcrops in the lower corners (the page's "branch" slots). No lanterns: the kit's
    // asteroids drift instead.
    'scenery' => ['branch' => $img((string) ($art['scenery']['crystals'] ?? '')), 'lantern' => ''],
    'pedestal' => $img((string) ($art['pedestal'] ?? '')),
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
    // The throne layout's art: the tumble-win plaque and the feature cards.
    'tumblePlaque' => $img((string) ($art['tumble'] ?? '')),
    'tumbleRatio' => $tumbleSize ? round($tumbleSize[0] / $tumbleSize[1], 4) : 4.2,
    'cards' => array_map($img, (array) ($art['cards'] ?? [])),
    'banners' => array_map($img, (array) ($art['banners'] ?? [])),
    // colour: the float text and sparks when the symbol pays.
    'symbols' => $symbols,
    // The meteors, by value: [from, colour], highest first (slot-timeline.js starTier).
    'stars' => $meteors,
    'tiers' => [[100, 'sun'], [20, 'ember'], [6, 'jade'], [0, 'ice']],
    'introStars' => [$meteors['ember'] ?? '', $meteors['sun'] ?? ''],
    'fragments' => [],
    // The Titan: { pose: { src, cx, ar, k, feet, palm?, fist?, star?, finger? } } for idle,
    // command, crush, roar, summon and waiting, full length (the throne layout stands him on
    // his rock by `feet`). The points are where the kit's effects leave or reach his hands.
    'host' => array_map(
        static fn (array $pose): array => ['src' => $img((string) $pose['src'])] + $pose,
        (array) ($art['host'] ?? []),
    ),
    'hostReactions' => [
        'win' => ['command', 2400, 3],
        'big' => ['roar', 4800, 6],
        'free' => ['summon', 3600, 5],
        'crush' => ['crush', 1700, 4],
    ],
    // Not in the cosmos: caramel flames and dew drops (the kit's starfire does that job).
    'effects' => ['flames' => false, 'dew' => false],
    // A winning tumble takes its time: cracks race across the winners before they shatter.
    'pace' => ['hold' => 720, 'frost' => 360, 'after' => 240],
    'text' => [
        // The same tables as Trident of Poseidon: free spins come 1 in 380 spins, 1 in 184 with the ante.
        'anteOften' => 'about twice as often',
        'freeSpins' => 'Meteors that land in a winning free spin join the Eclipse, and the Eclipse multiplies every winning free spin after.',
        'tideName' => 'The Eclipse',
        'superBuy' => 'Super Eclipse',
        'multipliers' => [
            'Meteors (2× to 100×) land in the base game too. When a spin\'s tumbles end with a win, the meteors on the grid add up and multiply it.',
            'In free spins meteors reach 500×. The meteors of every winning free spin join the Eclipse, and from then on the Eclipse multiplies every winning free spin.',
        ],
        'headlines' => [['THE ECLIPSE', 'MULTIPLIES EVERY WIN']],
    ],
    'fx' => [
        'frostBurst' => $one('shatter-burst'),
        'frostRing' => $one('shockwave'),
        'twinkle' => $one('twinkle'),
        'fireworks' => $one('shatter-burst'),
        'rays' => $one('corona'),
        'comet' => $one('meteor-trail'),
        'crystals' => $list($fx, 'embers'),
        'sprinkles' => $list($fx, 'treasure'),
    ],
    // Only the kit uses these.
    'kitArt' => [
        'cracks' => $one('cracks'),
        'vortex' => $one('vortex'),
        'corona' => $one('corona'),
        'sweep' => $one('sweep'),
        'stardust' => $list($fx, 'stardust'),
        'asteroids' => $list((array) ($art['scenery'] ?? []), 'asteroids'),
        'eclipseRing' => $img((string) ($art['eclipse_ring'] ?? '')),
    ],
];
