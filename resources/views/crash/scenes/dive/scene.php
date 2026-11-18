<?php

// The dive: a submarine diving down a trench, in two illustrated skins (Jackpot Reef,
// Gothic Nightmare). The page (crash.blade.php) requires this for its skins and art; a
// closure, so nothing here lands in the page's variables.

return (static function (): array {
    // Colour skins. Each entry becomes a `:root[data-theme=…]` token block in
    // the page's <style> and a row in the top-right theme picker. The first
    // entry is the default. Water column runs shallow → mid → depth1 →
    // depth2 → abyss; hud* drive every accent/glow; panel tints the HUD
    // chrome; text/muted are body copy.
    $themes = [
        'ocean' => [
            'label' => 'Deep Ocean',
            'shallow' => '#0ea5e9', 'mid' => '#0c4a6e', 'depth1' => '#082f49', 'depth2' => '#020617', 'abyss' => '#000000',
            'hud' => '#67e8f9', 'hudBright' => '#cffafe', 'hudDark' => '#0891b2',
            'panel' => '#020617', 'text' => '#e2e8f0', 'muted' => '#94a3b8',
        ],
        'lagoon' => [
            'label' => 'Emerald Lagoon',
            'shallow' => '#14b8a6', 'mid' => '#0f766e', 'depth1' => '#134e4a', 'depth2' => '#042f2e', 'abyss' => '#010a09',
            'hud' => '#6ee7b7', 'hudBright' => '#d1fae5', 'hudDark' => '#059669',
            'panel' => '#021410', 'text' => '#ecfdf5', 'muted' => '#8fb8a8',
        ],
        'sunset' => [
            'label' => 'Coral Sunset',
            'shallow' => '#fb7185', 'mid' => '#be123c', 'depth1' => '#4c0519', 'depth2' => '#1c0310', 'abyss' => '#000000',
            'hud' => '#fdba74', 'hudBright' => '#ffedd5', 'hudDark' => '#ea580c',
            'panel' => '#170408', 'text' => '#fff1f2', 'muted' => '#b8929a',
        ],
        'midnight' => [
            'label' => 'Midnight Neon',
            'shallow' => '#7c3aed', 'mid' => '#4c1d95', 'depth1' => '#2e1065', 'depth2' => '#120626', 'abyss' => '#000000',
            'hud' => '#e879f9', 'hudBright' => '#fae8ff', 'hudDark' => '#a21caf',
            'panel' => '#0b0218', 'text' => '#f3e8ff', 'muted' => '#a58fbf',
        ],
        'arctic' => [
            'label' => 'Arctic Ice',
            'shallow' => '#7dd3fc', 'mid' => '#0ea5e9', 'depth1' => '#0369a1', 'depth2' => '#0c4a6e', 'abyss' => '#082f49',
            'hud' => '#f0f9ff', 'hudBright' => '#ffffff', 'hudDark' => '#7dd3fc',
            'panel' => '#082f49', 'text' => '#f0f9ff', 'muted' => '#a5c9de',
        ],
        // Gothic storybook skin: desaturated violet-grey water, bone-white
        // HUD, pale moon and black/white stripes. Extra decoration lives in
        // the `:root[data-theme="burton"]` block of styles-skins.blade.php.
        'burton' => [
            'label' => 'Gothic Nightmare',
            'shallow' => '#6b6480', 'mid' => '#3b3550', 'depth1' => '#221e30', 'depth2' => '#110e18', 'abyss' => '#050407',
            'hud' => '#e7e1d2', 'hudBright' => '#ffffff', 'hudDark' => '#8b7fa8',
            'panel' => '#0b0910', 'text' => '#ece6d8', 'muted' => '#9c93ad',
        ],
        // Treasure-hunt cartoon skin: sunlit turquoise reef, gold HUD, the
        // *-jackpot pictures. Extras live in the `:root[data-theme="jackpot"]`
        // blocks of styles-skins.blade.php.
        'jackpot' => [
            'label' => 'Jackpot Reef',
            'shallow' => '#22d3ee', 'mid' => '#0891b2', 'depth1' => '#0e5a7a', 'depth2' => '#083a5a', 'abyss' => '#04192e',
            'hud' => '#fcd34d', 'hudBright' => '#fff7d6', 'hudDark' => '#d97706',
            'panel' => '#05253a', 'text' => '#f0fdfa', 'muted' => '#8cc5cf',
        ],
    ];
    // Only these skins are offered (picker, CSS, ?theme=, saved choice), in
    // this order; the first is the default (and the one the intro shows to
    // players who haven't picked a skin). The rest stay defined above so they
    // can be re-enabled by adding their key here.
    $enabledThemes = ['jackpot', 'burton'];
    $themes = array_replace(array_flip($enabledThemes), array_intersect_key($themes, array_flip($enabledThemes)));

    return [
        // The page's <title> is this, then the game's name.
        'titlePrefix' => 'Deep Dive — ',
        'themes' => $themes,
        // Each skin's sound pack (js/orange/sound-*.js, loaded by sounds.blade.php); other
        // skins play the built-in underwater set.
        'packs' => ['burton' => 'gothic', 'jackpot' => 'jackpot'],
        // The page's words for a round: the status line, the crash banner and the rules.
        'text' => [
            'round' => 'dive',              // "seconds before each dive", "at any moment of the dive"
            'rise' => 'The sub dives',      // "… and the multiplier climbs from 1.00×."
            'lose' => 'the hull implodes',  // "If … before you cash out, the bet is lost."
            'bettingIn' => 'Diving in',     // the betting countdown: "Diving in 7s"
            'liftOff' => 'Pressurising…',   // betting over, the round about to start
            'running' => 'DIVING',
            'crashed' => 'IMPLODED',        // "IMPLODED @ 2.47x"
            'nextIn' => 'Next dive in',
            'awaiting' => 'Awaiting next dive…',
            'banner' => 'Hull breach',
        ],
        // The built-in intro's backdrop per skin.
        'introBg' => [
            'burton' => ['landscape' => asset('images/bg-gothic-landscape.jpg'), 'portrait' => asset('images/bg-gothic-portrait.jpg')],
            'jackpot' => [
                'landscape' => asset('images/bg-jackpot-landscape.jpg').'?v='.filemtime(public_path('images/bg-jackpot-landscape.jpg')),
                'portrait' => asset('images/bg-jackpot-portrait.jpg').'?v='.filemtime(public_path('images/bg-jackpot-portrait.jpg')),
            ],
        ],
        // Each skin's crash sprite, preloaded so it is decoded before the first crash.
        'sprites' => ['burton' => asset('images/sub-explosion.webp'), 'jackpot' => asset('images/sub-explosion-jackpot.webp').'?v='.filemtime(public_path('images/sub-explosion-jackpot.webp'))],
    ];
})();
