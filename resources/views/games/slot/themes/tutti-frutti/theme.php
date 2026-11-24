<?php

declare(strict_types=1);

/*
 * Tutti Frutti's look and sound (config('slots.themes'), games.config.theme = tutti-frutti):
 * a Riviera beach club at noon, and the Neon Night in free spins. A paylines game
 * (TuttiFrutti, LinesModel): the page spins 5 × 3 reels and shows the paying lines
 * (slot-game.js "Reels", "Lines"). Symbols are keyed by the model's letters; W, the kiss, is
 * the wild, one locket colour per value (`wilds`).
 *
 * The art is built by scripts/art/process-tutti-frutti.py into public/images/tutti-frutti/,
 * with art.json. The screen is the "duet" layout (resources/views/games/slot/layouts/duet.blade.php):
 * two girls full-length beside the reels, picked by the player from 10 (`girls`): her girl on
 * the right, who blows the kisses, and her friend on the left; Bella and Marina by default
 * (`pair`). The shared host code isn't used (`host` is empty): the kit,
 * public/js/orange/slot-kit-riviera.js, plays the girls and their picker, the juice, the kisses
 * and Amore. Every URL carries the file's mtime, so
 * a rebuilt file reaches players at once.
 */

$dir = public_path('images/tutti-frutti');
$img = static fn (string $path): string => $path === '' ? '' : asset("images/tutti-frutti/{$path}").'?v='.(is_file("{$dir}/{$path}") ? filemtime("{$dir}/{$path}") : 0);
/** @var array<string, mixed> $art */
$art = is_file("{$dir}/art.json") ? (array) json_decode((string) file_get_contents("{$dir}/art.json"), true) : [];
$fx = (array) ($art['fx'] ?? []);
$list = static fn (array $from, string $key): array => array_map($img, (array) ($from[$key] ?? []));
$one = static fn (string $key): string => $img((string) ($fx[$key] ?? ''));
$plaqueSize = is_file("{$dir}/plaque.webp") ? getimagesize("{$dir}/plaque.webp") : false;

// name, and the colour of its juice and its floating win.
$names = [
    'A' => ['Peach', '#ff7a45'], 'B' => ['Pineapple', '#ffc531'], 'C' => ['Dragon fruit', '#ff2d9a'],
    'D' => ['Pomegranate', '#e0193f'], 'E' => ['Mango', '#ff9a1f'], 'F' => ['Lime', '#8fe03a'],
    'G' => ['Blueberries', '#3f6bff'], 'H' => ['Fig', '#9b3cff'], 'I' => ['Lychee', '#fff1f4'],
    'S' => ['Cocktail', '#ffb3d9'],
];
$symbols = [];
foreach ($names as $letter => [$name, $colour]) {
    $symbols[$letter] = ['name' => $name, 'img' => $img((string) ($art['symbols'][$letter] ?? '')), 'colour' => $colour];
}
$frame = (array) ($art['frame'] ?? []);
$wilds = array_map($img, (array) ($art['wilds'] ?? []));
// The girls the player picks from (the kit's picker), in the picker's order: which way each
// faces, her colour, her fruit, her card and her poses by role.
/** @var array<string, array<string, mixed>> $girls */
$girls = [];
foreach ((array) ($art['girls'] ?? []) as $name => $girl) {
    $poses = [];
    foreach ((array) ($girl['poses'] ?? []) as $role => $meta) {
        $poses[$role] = ['src' => $img((string) $meta['src'])] + (array) $meta;
    }
    $girls[$name] = ['name' => ucfirst((string) $name), 'faces' => $girl['faces'] ?? 'left', 'colour' => $girl['colour'] ?? '#ff3fa4',
        'fruit' => $girl['fruit'] ?? 'A', 'card' => $img((string) ($girl['card'] ?? '')), 'poses' => $poses];
}

return [
    'layout' => 'duet',
    'sound' => 'riviera',
    // Background music: the pack's summer lounge groove (sound-riviera.js).
    'music' => true,
    'kit' => 'riviera',
    'logo' => $img((string) ($art['logo'] ?? '')),
    'backgrounds' => [
        'day' => ['landscape' => $img('bg-day-landscape.webp'), 'portrait' => $img('bg-day-portrait.webp')],
        'night' => ['landscape' => $img('bg-night-landscape.webp'), 'portrait' => $img('bg-night-portrait.webp')],
    ],
    // Tropical leaves in the lower corners (the page's "branch" slots). No lanterns.
    'scenery' => ['branch' => $img((string) ($art['tropical'] ?? '')), 'lantern' => ''],
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
    'symbols' => $symbols,
    // The kiss lockets by value: 1 plain, 2×, 3×, 5×.
    'wilds' => $wilds,
    // No multiplier symbols (M): the kisses are the wilds.
    'stars' => [],
    'tiers' => [[0, 'pink']],
    'introStars' => [$wilds['2'] ?? '', $wilds['5'] ?? ''],
    'fragments' => [],
    'idle' => 'EAF'.'HCG'.'BDI'.'GEF'.'CHA',
    // The 20 lines' colours, line 1 first (the markers on the frame and the line drawn on a win).
    'lineColours' => [
        '#ff2d9a', '#22d3ee', '#a3e635', '#ffb020', '#8b5cf6', '#ff5a5f', '#14e0a8', '#ffd84d', '#4f7dff', '#ff7ad1',
        '#5eead4', '#f97316', '#c084fc', '#84cc16', '#fb7185', '#38bdf8', '#facc15', '#e879f9', '#2dd4bf', '#f43f5e',
    ],
    // The duet: the girls, { name: { name, faces, colour, fruit, card, poses: { role: { src, cx, ar, k,
    // feet, palm? } } } }, and the pair a player starts with: her girl (right) and her friend (left).
    'host' => [],
    'girls' => $girls,
    'pair' => ['right' => 'bella', 'left' => 'marina'],
    'girlFrame' => ['src' => $img((string) ($art['girl_frame']['src'] ?? '')), 'window' => $art['girl_frame']['window'] ?? ['x0' => 0, 'y0' => 0, 'x1' => 1, 'y1' => 1]],
    'girlButton' => $img((string) ($art['girl_button'] ?? '')),
    'podium' => $img((string) ($art['podium'] ?? '')),
    'buyArt' => $img((string) ($art['buy'] ?? '')),
    'amoreArt' => $img((string) ($art['amore'] ?? '')),
    // Not at the beach club: caramel flames and dew drops (the kit's juice does that job).
    'effects' => ['flames' => false, 'dew' => false],
    'text' => [
        'freeSpins' => 'Every kiss that lands in free spins adds 1 to Amore, and Amore multiplies every free-spin win.',
        'multipliers' => [
            'Kisses are wild on reels 2 to 5. A kiss may carry 2×, 3× or 5×: the multipliers on a paying line add up and multiply it.',
            'Free spins: Amore starts at ×1. Every kiss that lands adds 1, and Amore multiplies every line win until the free spins end.',
            'Your girls: pick them before you play, and change them between spins with the GIRLS button or a tap on either girl. Just for fun: they never change the odds.',
        ],
    ],
    'fx' => [
        'frostBurst' => $one('heart-burst'),
        'frostRing' => $one('sunburst'),
        'twinkle' => $one('twinkle'),
        'fireworks' => $one('firework'),
        'rays' => $one('sunburst'),
        'comet' => $one('heart-trail'),
        'crystals' => [],
        'sprinkles' => $list($fx, 'confetti'),
    ],
    // Only the kit uses these.
    'kitArt' => [
        'splash' => $one('splash'),
        'droplets' => $list($fx, 'droplets'),
        'confetti' => $list($fx, 'confetti'),
        'sweep' => $one('sweep'),
        'firework' => $one('firework'),
        'heartTrail' => $one('heart-trail'),
        'heartBurst' => $one('heart-burst'),
        'sunburst' => $one('sunburst'),
        'twinkle' => $one('twinkle'),
    ],
];
