<?php

// The marsh hunt (Lucky Duck): a cocky mallard flies over a marsh while a fox in a reed blind
// takes aim, and shoots him down at the crash point. One skin. The page (crash.blade.php)
// requires this for its skins and art; a closure, so nothing here lands in the page's variables.

return (static function (): array {
    // The scene's art, built by scripts/art/process-lucky-duck.py: a URL cache-busted by the
    // file's mtime, per file in public/images/lucky-duck.
    $art = static fn (string $file): string => asset("images/lucky-duck/{$file}").'?v='.filemtime(public_path("images/lucky-duck/{$file}"));

    return [
        // The page's <title> is this, then the game's name.
        'titlePrefix' => '',
        // The sound pack (js/orange/sound-marsh.js, loaded by sounds.blade.php).
        'packs' => ['luckyduck' => 'marsh'],
        // The page's words for a round: the status line, the crash banner and the rules.
        'text' => [
            'round' => 'flight',
            'rise' => 'Lucky takes off',
            'lose' => 'the fox shoots Lucky down',
            'bettingIn' => 'Take-off in',
            'liftOff' => 'Ready for take-off…',
            'running' => 'FLYING',
            'crashed' => 'SHOT DOWN',
            'nextIn' => 'Next flight in',
            'awaiting' => 'Awaiting next flight…',
            'banner' => 'Bang!',
        ],
        // The token keys every scene fills (see the page's token block): the sky runs from
        // `shallow` at the horizon to `abyss` at dusk; hud* are the gold accents, panel the
        // reed-green chrome.
        'themes' => [
            'luckyduck' => [
                'label' => 'Lucky Duck',
                'shallow' => '#bae6fd', 'mid' => '#7dd3fc', 'depth1' => '#38bdf8', 'depth2' => '#0284c7', 'abyss' => '#1e1b4b',
                'hud' => '#fcd34d', 'hudBright' => '#fef9c3', 'hudDark' => '#d97706',
                'panel' => '#14240f', 'text' => '#fefce8', 'muted' => '#b5c49a',
            ],
        ],
        'art' => $art,
        // The built-in intro's backdrop.
        'introBg' => [
            'luckyduck' => ['landscape' => $art('sky-day-landscape.jpg'), 'portrait' => $art('sky-day-portrait.jpg')],
        ],
        // The crash sprite (the duck hit), preloaded so it is decoded before the first crash.
        'sprites' => ['luckyduck' => $art('duck-hit.webp')],
    ];
})();
