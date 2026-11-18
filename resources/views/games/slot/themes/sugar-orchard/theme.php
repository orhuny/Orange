<?php

declare(strict_types=1);

/*
 * Sugar Orchard's look and sound (config('slots.themes'), games.config.theme = sugar-orchard).
 * Symbols are keyed by the model's letters (SugarOrchard::SYMBOLS); M, the multiplier star,
 * is drawn by the page. Art: public/images/sugar-orchard/, prompts in docs/art/.
 */

$img = fn (string $path): string => asset("images/sugar-orchard/{$path}");

return [
    'sound' => 'orchard',
    'logo' => $img('logo.webp'),
    'backgrounds' => [
        'day' => ['landscape' => $img('bg-day-landscape.webp'), 'portrait' => $img('bg-day-portrait.webp')],
        'night' => ['landscape' => $img('bg-night-landscape.webp'), 'portrait' => $img('bg-night-portrait.webp')],
    ],
    // colour: the burst when the symbol pays.
    'symbols' => [
        'A' => ['name' => 'Toffee', 'img' => $img('symbols/toffee.webp'), 'colour' => '#f6c445'],
        'B' => ['name' => 'Peppermint', 'img' => $img('symbols/peppermint.webp'), 'colour' => '#ff3b3b'],
        'C' => ['name' => 'Gummy bear', 'img' => $img('symbols/gummy-bear.webp'), 'colour' => '#3d8bff'],
        'D' => ['name' => 'Truffle', 'img' => $img('symbols/truffle.webp'), 'colour' => '#a8642c'],
        'E' => ['name' => 'Orange', 'img' => $img('symbols/orange.webp'), 'colour' => '#ff8a1c'],
        'F' => ['name' => 'Cherries', 'img' => $img('symbols/cherries.webp'), 'colour' => '#c0172e'],
        'G' => ['name' => 'Strawberry', 'img' => $img('symbols/strawberry.webp'), 'colour' => '#ff3d4f'],
        'H' => ['name' => 'Lemon', 'img' => $img('symbols/lemon.webp'), 'colour' => '#ffe14d'],
        'I' => ['name' => 'Kiwi', 'img' => $img('symbols/kiwi.webp'), 'colour' => '#8ccf3a'],
        'S' => ['name' => 'Scatter', 'img' => $img('symbols/gumball-machine.webp'), 'colour' => '#ffd84a'],
    ],
];
