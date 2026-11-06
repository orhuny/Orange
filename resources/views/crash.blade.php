@php
    // Colour skins. Each entry becomes a `:root[data-theme=…]` token block in
    // the <style> below and a row in the top-right theme picker. The first
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
        // the `:root[data-theme="burton"]` block below the token loop.
        'burton' => [
            'label' => 'Gothic Nightmare',
            'shallow' => '#6b6480', 'mid' => '#3b3550', 'depth1' => '#221e30', 'depth2' => '#110e18', 'abyss' => '#050407',
            'hud' => '#e7e1d2', 'hudBright' => '#ffffff', 'hudDark' => '#8b7fa8',
            'panel' => '#0b0910', 'text' => '#ece6d8', 'muted' => '#9c93ad',
        ],
        // Treasure-hunt cartoon skin: sunlit turquoise reef, gold HUD, the
        // *-jackpot pictures. Extras live in the `:root[data-theme="jackpot"]`
        // blocks below the token loop.
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
    $rgb = static fn (string $hex): string => implode(',', sscanf($hex, '#%02x%02x%02x'));

    // Which sea life swims behind the sub (config('crash.sea_life')); a kind not set there shows.
    $seaLife = array_merge(
        ['fish' => true, 'creatures' => true, 'octopi' => true, 'jellyfish' => true],
        (array) config('crash.sea_life', []),
    );

    // Studio intro frames (see config('crash.intro')): every image in the
    // folder, in natural file-name order, as public URLs.
    $introFrames = static function (string $dir): array {
        $files = glob(public_path("images/intro/{$dir}/*.{webp,png,jpg,jpeg}"), GLOB_BRACE) ?: [];
        natsort($files);
        return array_values(array_map(fn ($f) => asset("images/intro/{$dir}/" . basename($f)), $files));
    };
    $intro = null;
    if (config('crash.intro.enabled', false)) {
        $landscape = $introFrames('landscape');
        $portrait = $introFrames('portrait');
        $intro = [
            // Uploaded frames win; otherwise the built-in scene from the game art plays.
            'mode' => ($landscape || $portrait) ? 'frames' : 'scene',
            'title' => (string) config('crash.intro.title', 'DeepDive'),
            'tagline' => (string) config('crash.intro.tagline', ''),
            'landscape' => $landscape,
            'portrait' => $portrait,
            'bg' => [
                'burton' => ['landscape' => asset('images/bg-gothic-landscape.jpg'), 'portrait' => asset('images/bg-gothic-portrait.jpg')],
                'jackpot' => [
                    'landscape' => asset('images/bg-jackpot-landscape.jpg').'?v='.filemtime(public_path('images/bg-jackpot-landscape.jpg')),
                    'portrait' => asset('images/bg-jackpot-portrait.jpg').'?v='.filemtime(public_path('images/bg-jackpot-portrait.jpg')),
                ],
            ],
            'frameMs' => (int) config('crash.intro.frame_ms', 550),
            'fadeMs' => (int) config('crash.intro.fade_ms', 400),
            'holdMs' => (array) config('crash.intro.hold_ms', []),
            'oncePerSession' => (bool) config('crash.intro.once_per_session', true),
        ];
    }
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Deep Dive — Orange Crash</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    {{-- Apply the theme before first paint so the page never flashes the
         default palette. Priority: player's saved choice → ?theme= (operator
         default on the embed URL) → first entry of $themes. --}}
    <script>
        (function () {
            var valid = @json(array_keys($themes));
            var t = null;
            try { t = localStorage.getItem('orange.ui.theme'); } catch (_) {}
            if (valid.indexOf(t) === -1) t = new URLSearchParams(location.search).get('theme');
            if (valid.indexOf(t) === -1) t = valid[0];
            document.documentElement.setAttribute('data-theme', t);
            // The skin's implosion sprite must be decoded before the first crash, not fetched during it.
            var sprites = @json(['burton' => asset('images/sub-explosion.webp'), 'jackpot' => asset('images/sub-explosion-jackpot.webp').'?v='.filemtime(public_path('images/sub-explosion-jackpot.webp'))]);
            if (sprites[t]) {
                var preload = document.createElement('link');
                preload.rel = 'preload';
                preload.as = 'image';
                preload.href = sprites[t];
                document.head.appendChild(preload);
            }
        })();
    </script>
    <style>
        /* Register dynamic CSS variables so they interpolate smoothly via
           `transition`. Without @property, custom properties switch
           instantly when the stage class changes, which makes every bubble,
           particle, and creature jump to its new trajectory at once. With
           these declarations the browser can animate the variable values
           over time and the keyframes are re-evaluated each frame with a
           smoothly drifting target — no visible frame skip on state shifts. */
        @property --drift-x {
            syntax: "<length-percentage>";
            inherits: true;
            initial-value: -15px;
        }
        @property --p-drift {
            syntax: "<length-percentage>";
            inherits: true;
            initial-value: 40px;
        }
        @property --speed {
            syntax: "<number>";
            inherits: true;
            initial-value: 1;
        }

        /* ---------- Theme tokens ----------
           Every UI colour derives from these. The `-rgb` twins feed the
           rgba() glows/borders. One block per skin in $themes; the first is
           also the bare :root default. `html[data-theme]` is set by the
           <head> bootstrap + the top-right picker. */
        @foreach ($themes as $key => $t)
        {{ $loop->first ? ':root,' : '' }} :root[data-theme="{{ $key }}"] {
            --shallow:    {{ $t['shallow'] }};
            --mid:        {{ $t['mid'] }};
            --depth-1:    {{ $t['depth1'] }};
            --depth-2:    {{ $t['depth2'] }};
            --abyss:      {{ $t['abyss'] }};
            --hud:        {{ $t['hud'] }};
            --hud-rgb:    {{ $rgb($t['hud']) }};
            --hud-bright: {{ $t['hudBright'] }};
            --hud-dark:   {{ $t['hudDark'] }};
            --panel-rgb:  {{ $rgb($t['panel']) }};
            --text:       {{ $t['text'] }};
            --text-muted: {{ $t['muted'] }};
        }
        @endforeach
        :root {
            /* Semantic colours stay fixed across skins: red = danger,
               gold = betting/deep, green = cashout/win. */
            --warn:    #ef4444;
            --gold:    #fbbf24;
            --good:    #22c55e;
        }

        /* ---------- Gothic Nightmare extras (burton skin only) ----------
           Pale spiral moon, drained colours, crooked stripes. Semantic
           colours (warn/gold/good) are untouched so game states still read. */
        :root[data-theme="burton"] .backdrop::after {
            content: ""; position: absolute; pointer-events: none;
            top: 6%; right: 12%; width: 150px; height: 150px; border-radius: 50%;
            background:
              repeating-radial-gradient(circle at 42% 44%, transparent 0 9px, rgba(90,80,110,0.22) 10px 11px),
              radial-gradient(circle at 40% 40%, #fbf8ef 0%, #e2dccb 55%, #b9b1c4 100%);
            box-shadow: 0 0 60px 18px rgba(236,230,216,0.28), 0 0 160px 40px rgba(139,127,168,0.25);
            opacity: 0.85; transition: opacity 1.5s ease;
        }
        :root[data-theme="burton"] .deep .backdrop::after  { opacity: 0.35; }
        :root[data-theme="burton"] .abyss .backdrop::after,
        :root[data-theme="burton"] .critical .backdrop::after { opacity: 0.12; }
        /* Curly silhouette hills along the bottom of the water. */
        :root[data-theme="burton"] .scene::after {
            content: ""; position: absolute; left: 0; right: 0; bottom: 0; height: 22%;
            pointer-events: none;
            background:
              radial-gradient(60% 90% at 12% 100%, #050407 58%, transparent 60%),
              radial-gradient(45% 70% at 48% 100%, #0a0810 58%, transparent 60%),
              radial-gradient(55% 85% at 86% 100%, #050407 58%, transparent 60%);
            opacity: 0.9;
        }
        :root[data-theme="burton"] .rays {
            background: repeating-linear-gradient(115deg,
                transparent 0 7%, rgba(236,230,216,0.05) 7.2% 8.4%, transparent 8.6% 14%);
        }
        :root[data-theme="burton"] .fish,
        :root[data-theme="burton"] .jelly,
        :root[data-theme="burton"] .creature { filter: grayscale(0.85) contrast(1.15) brightness(0.9); }
        :root[data-theme="burton"] .particle { background: rgba(236,230,216,0.55); }
        :root[data-theme="burton"] .gauge {
            border-radius: 4px 14px 6px 12px;
            outline: 1px dashed rgba(236,230,216,0.22); outline-offset: -6px;
            transform: rotate(-0.6deg);
        }
        :root[data-theme="burton"] .gauge-value {
            font-family: "Palatino Linotype", "Book Antiqua", Georgia, serif;
            font-size: 24px; letter-spacing: 0.01em;
        }
        :root[data-theme="burton"] .gauge:nth-child(even) { transform: rotate(0.7deg); }
        /* Painted water surface is light behind the history caption. */
        :root[data-theme="burton"] .history-label {
            color: var(--hud-bright); opacity: 1;
            text-shadow: 0 0 4px #0b0910, 0 0 10px #0b0910, 0 1px 2px #000;
        }
        :root[data-theme="burton"] .gauge-label { letter-spacing: 0.24em; }
        :root[data-theme="burton"] .multiplier {
            font-family: "Palatino Linotype", "Book Antiqua", Georgia, serif;
            font-style: italic; letter-spacing: -0.02em;
            transform: rotate(-2deg);
        }
        /* Ghostly gradient only in calm states; deep/critical keep their
           gold/red warning colours. */
        :root[data-theme="burton"] .stage:not(.deep):not(.critical):not(.crashed) .multiplier {
            background: linear-gradient(180deg, #ffffff 0%, #e7e1d2 45%, #8b7fa8 100%);
            -webkit-background-clip: text; background-clip: text;
            text-shadow: 3px 3px 0 rgba(0,0,0,0.55), 0 0 40px rgba(139,127,168,0.45);
        }
        :root[data-theme="burton"] .cashout {
            border-radius: 10px 26px 12px 24px;
            box-shadow: 0 0 0 3px #0b0910, 0 0 0 6px #ece6d8, 0 0 30px rgba(34,197,94,0.55);
        }
        /* Painted gothic backdrops: landscape art on wide screens, portrait art
           on phones. Both already carry light rays and a seabed, so the CSS
           rays and hills step aside; the portrait art also has its own moon.
           Depth darkening still comes from the .scene filter. */
        :root[data-theme="burton"] .backdrop {
            background-image: url("{{ asset('images/bg-gothic-landscape.jpg') }}?v=2");
        }
        :root[data-theme="burton"] .scene::after,
        :root[data-theme="burton"] .rays { display: none; }
        @media (orientation: portrait) {
            :root[data-theme="burton"] .backdrop {
                background-image: url("{{ asset('images/bg-gothic-portrait.jpg') }}");
                background-position: center top;
            }
            :root[data-theme="burton"] .backdrop::after { display: none; }
        }
        /* ---------- Jackpot Reef extras (jackpot skin only) ----------
           Sunlit reef backdrops (they carry their own rays and seabed), gold
           HUD from the tokens, a chunky rounded face for the numbers. */
        :root[data-theme="jackpot"] .backdrop {
            background-image: url("{{ asset('images/bg-jackpot-landscape.jpg') }}?v={{ filemtime(public_path('images/bg-jackpot-landscape.jpg')) }}");
        }
        @media (orientation: portrait) {
            :root[data-theme="jackpot"] .backdrop {
                background-image: url("{{ asset('images/bg-jackpot-portrait.jpg') }}?v={{ filemtime(public_path('images/bg-jackpot-portrait.jpg')) }}");
                background-position: center top;
            }
        }
        :root[data-theme="jackpot"] .scene::after,
        :root[data-theme="jackpot"] .rays { display: none; }
        :root[data-theme="jackpot"] .particle { background: rgba(255, 244, 200, 0.6); }
        :root[data-theme="jackpot"] .multiplier,
        :root[data-theme="jackpot"] .gauge-value,
        :root[data-theme="jackpot"] .depth-chip b {
            font-family: "Arial Rounded MT Bold", "Trebuchet MS", "Segoe UI", system-ui, sans-serif; font-weight: 800;
        }
        :root[data-theme="jackpot"] .stage:not(.deep):not(.critical):not(.crashed) .multiplier {
            text-shadow: 0 4px 0 rgba(120, 53, 15, 0.55), 0 0 36px rgba(252, 211, 77, 0.5);
        }
        /* Small captions sit on the brightest water: give them a solid dark outline. */
        :root[data-theme="jackpot"] .history-label,
        :root[data-theme="jackpot"] .depth-meter::before {
            color: #ffffff; opacity: 1;
            text-shadow: 0 0 2px #04192e, 0 0 3px #04192e, 0 1px 4px #04192e, 0 0 12px rgba(4, 25, 46, 0.9);
        }
        :root[data-theme="jackpot"] .cashout {
            box-shadow: 0 0 0 3px #05253a, 0 0 0 6px #fcd34d, 0 0 30px rgba(252, 211, 77, 0.55);
        }

        /* Illustrated skins (Gothic Nightmare, Jackpot Reef): painted fish,
           octopi, whale, anglerfish and jellyfish replace the SVG silhouettes.
           Each skin's pictures carry its .art-<skin> class; the other's stay hidden. */
        .skin-art { display: none; }
        :root[data-theme="burton"] .art-jackpot,
        :root[data-theme="jackpot"] .art-burton { display: none !important; }
        :root:not([data-theme="jackpot"]) .intro .art-jackpot { display: none; }
        :root:is([data-theme="burton"], [data-theme="jackpot"]) .fish > svg,
        :root:is([data-theme="burton"], [data-theme="jackpot"]) .creature.octopus > svg,
        :root:is([data-theme="burton"], [data-theme="jackpot"]) .creature.whale > svg,
        :root:is([data-theme="burton"], [data-theme="jackpot"]) .creature.angler-deep > svg { display: none; }
        :root:is([data-theme="burton"], [data-theme="jackpot"]) .fish > .skin-art,
        :root:is([data-theme="burton"], [data-theme="jackpot"]) .creature.octopus > .skin-art,
        :root:is([data-theme="burton"], [data-theme="jackpot"]) .creature.whale > .skin-art { display: block; }
        /* Same scale-up as the whale SVG so it reads at distance. The
           cross-reverse path mirrors the creature (scaleX(-1)) while it
           swims right → left, so pre-flip the head-left art to keep the
           head leading. */
        :root:is([data-theme="burton"], [data-theme="jackpot"]) .creature.whale > .skin-art {
            transform: scale(-1.55, 1.55);
            animation: whale-glide 6s ease-in-out infinite;
        }
        @keyframes whale-glide {
            0%, 100% { transform: scale(-1.55, 1.55) translateY(0)    rotate(0deg); }
            50%      { transform: scale(-1.55, 1.55) translateY(-4px) rotate(1deg); }
        }
        :root:is([data-theme="burton"], [data-theme="jackpot"]) .fish > .skin-art { animation: bob 2.4s ease-in-out infinite; }
        :root:is([data-theme="burton"], [data-theme="jackpot"]) .creature.octopus > .skin-art {
            transform-origin: 50% 30%;
            animation: octopus-sway 3.6s ease-in-out infinite;
        }
        :root:is([data-theme="burton"], [data-theme="jackpot"]) .creature.octopus.mini > .skin-art { animation-duration: 2.4s; }
        :root[data-theme="burton"] .fish,
        :root[data-theme="burton"] .creature.octopus,
        :root[data-theme="burton"] .creature.whale { filter: brightness(0.92) drop-shadow(0 4px 8px rgba(0,0,0,0.5)); }
        :root[data-theme="jackpot"] .fish,
        :root[data-theme="jackpot"] .creature.octopus,
        :root[data-theme="jackpot"] .creature.whale { filter: drop-shadow(0 4px 8px rgba(0,0,0,0.35)); }
        /* Deep anglerfish: illustrated art with a flickering lure glow. */
        :root:is([data-theme="burton"], [data-theme="jackpot"]) .creature.angler-deep > .skin-art {
            display: block; position: relative;
            animation: bob 3.2s ease-in-out infinite;
        }
        .creature.angler-deep img { display: block; }
        .creature.angler-deep .bulb-glow {
            position: absolute; left: 96.3%; top: 39.6%;
            width: 30px; height: 30px; margin: -15px 0 0 -15px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(255,244,194,0.9) 0%, rgba(245,224,138,0.35) 40%, transparent 70%);
            animation: lantern-flicker 2.1s ease-in-out infinite;
        }
        /* Jackpot's lure is a diamond further in: its glow sits on it, a little cooler. */
        .creature.angler-deep .art-jackpot .bulb-glow {
            left: 86.4%; top: 27.2%;
            background: radial-gradient(circle, rgba(255,255,255,0.85) 0%, rgba(165,243,252,0.35) 40%, transparent 70%);
        }
        /* Illustrated jellyfish replace the CSS glow blobs. */
        :root:is([data-theme="burton"], [data-theme="jackpot"]) .jelly::before,
        :root:is([data-theme="burton"], [data-theme="jackpot"]) .jelly::after { display: none; }
        :root:is([data-theme="burton"], [data-theme="jackpot"]) .jelly > .skin-art { display: block; }
        :root[data-theme="burton"] .jelly > .skin-art { filter: drop-shadow(0 0 10px rgba(236,230,216,0.45)); }
        :root[data-theme="jackpot"] .jelly > .skin-art { filter: drop-shadow(0 0 12px rgba(94,234,212,0.55)); }
        :root:is([data-theme="burton"], [data-theme="jackpot"]) .deep .jelly,
        :root:is([data-theme="burton"], [data-theme="jackpot"]) .abyss .jelly { opacity: 0.7; }
        /* Swap the cartoon hull for the skin's illustrated sub. */
        .sub-gothic, .sub-jackpot { display: none; }
        :root:is([data-theme="burton"], [data-theme="jackpot"]) .sub-body { display: none; }
        :root[data-theme="burton"] .sub-gothic,
        :root[data-theme="jackpot"] .sub-jackpot { display: inline; }
        /* Tall lantern mast / periscope: sit a little lower before the dive so it
           clears the recent-crashes bar and the status pill under it (short screens). */
        :root:is([data-theme="burton"], [data-theme="jackpot"]) .waiting .sub-wrap,
        :root:is([data-theme="burton"], [data-theme="jackpot"]) .betting .sub-wrap { top: max(35%, 310px); }
        :root[data-theme="burton"] .sub-wake,
        :root[data-theme="burton"] .sub-wake-core { filter: grayscale(0.8); }
        :root[data-theme="burton"] .sub-bubbles { fill: rgba(236,230,216,0.6); }
        .gothic-lantern-glow { animation: lantern-flicker 2.4s ease-in-out infinite; }
        /* The illustrated subs' propellers are separate pictures, spun about the
           shaft: seen from the side, a turning propeller is a flip through edge-on
           (eased, as a blade's height follows the cosine of its angle). Lazy before
           the dive, quick while diving, faster deep down; it stops when the sub
           implodes. --axis is the shaft's height within the picture. */
        .sub-prop {
            transform-box: fill-box; transform-origin: 50% var(--axis, 50%);
            animation: prop-spin 0.6s ease-in-out infinite;
        }
        .running .sub-prop { animation-duration: 0.22s; }
        .deep .sub-prop { animation-duration: 0.16s; }
        .abyss .sub-prop, .critical .sub-prop { animation-duration: 0.12s; }
        .crashed .sub-prop { animation-play-state: paused; }
        @keyframes prop-spin {
            0%, 100% { transform: scaleY(1); }
            50%      { transform: scaleY(-1); }
        }
        @keyframes lantern-flicker {
            0%, 100% { opacity: 0.9; }
            40%      { opacity: 0.65; }
            55%      { opacity: 1; }
            70%      { opacity: 0.75; }
        }
        .theme-opt[data-theme="jackpot"] .theme-swatch {
            background:
              radial-gradient(circle at 60% 38%, #fff7d6 0 9%, #fcd34d 11% 22%, #d97706 24%, transparent 26%),
              linear-gradient(180deg, #22d3ee 0%, #0891b2 55%, #083a5a 100%);
        }
        .theme-opt[data-theme="burton"] .theme-swatch {
            background:
              radial-gradient(circle at 60% 35%, #fbf8ef 0 22%, transparent 24%),
              repeating-linear-gradient(45deg, #3b3550 0 3px, #110e18 3px 6px);
        }

        * { box-sizing: border-box; }
        html, body {
            margin: 0; padding: 0; height: 100%;
            font-family: ui-monospace, "SF Mono", Menlo, monospace;
            color: var(--text);
            overflow: hidden;
            user-select: none;
        }

        /* ---------- Stage = container only, never filtered ---------- */
        .stage { position: fixed; inset: 0; background: var(--abyss); }

        /* All depth darkening lives on this layer so the HUD/multiplier
           stay readable. */
        .scene {
            position: absolute; inset: 0;
            background:
              radial-gradient(ellipse at 50% -10%, rgba(255,255,255,0.18) 0%, transparent 35%),
              linear-gradient(180deg,
                  var(--shallow) 0%,
                  var(--mid) 18%,
                  var(--depth-1) 45%,
                  var(--depth-2) 75%,
                  var(--abyss) 100%);
            transition: filter 1s ease, background 2s ease;
            z-index: 1;
        }
        .stage.deep    .scene { filter: brightness(0.7) saturate(1.1); }
        .stage.abyss   .scene { filter: brightness(0.5) saturate(0.85) contrast(1.1); }
        .stage.critical .scene { filter: brightness(0.45) saturate(0.75) contrast(1.25) hue-rotate(-15deg); }

        /* ---------- Painted backdrop + trench walls (illustrated skins) ----------
           The descent: the backdrop rises past the sub as it dives, and rock
           walls down both edges carry on looping for as long as the dive lasts.
           Moved only by transforms set from JS each frame (setScenery), so the
           browser composites them without repainting: smooth at any speed.

           The walls hang from the backdrop's lower edge (they move with it) and
           their strips scroll inside that, so reef and trench stay joined; both
           edges fade over only a few percent. Between rounds all of it glides
           back up to the surface. */
        .backdrop {
            position: absolute; inset: 0; pointer-events: none;
            background: no-repeat center bottom / cover;
            -webkit-mask-image: linear-gradient(to bottom, #000 95%, transparent 100%);
            mask-image: linear-gradient(to bottom, #000 95%, transparent 100%);
            will-change: transform;
        }
        /* The water below the painting continues from its lower edge (--seabed, the
           painting's own colour there) and darkens into the abyss as you descend. */
        .walls {
            position: absolute; left: 0; right: 0; top: 95%; height: 170%; pointer-events: none;
            will-change: transform;
        }
        .walls::before {
            content: ''; position: absolute; inset: 0;
            background: linear-gradient(to bottom, var(--seabed, var(--depth-2)) 3%, var(--abyss) 55%);
            -webkit-mask-image: linear-gradient(to bottom, transparent, #000 2.5%);
            mask-image: linear-gradient(to bottom, transparent, #000 2.5%);
        }
        :root[data-theme="jackpot"] { --seabed: #255c7e; }
        /* The portrait art has no floor: it ends in open water of this colour, between
           the reef cliffs, and the trench carries on from it. */
        @media (orientation: portrait) {
            :root[data-theme="jackpot"] { --seabed: #003547; }
        }
        /* Gothic's lower band alternates black hills and grey water: blend over that
           band, so the ground meets the dark instead of a cut. */
        :root[data-theme="burton"] { --seabed: #29272d; }
        :root[data-theme="burton"] .walls { top: 80%; }
        :root[data-theme="burton"] .walls::before {
            -webkit-mask-image: linear-gradient(to bottom, transparent, #000 8%);
            mask-image: linear-gradient(to bottom, transparent, #000 8%);
        }
        :root[data-theme="burton"] .backdrop {
            -webkit-mask-image: linear-gradient(to bottom, #000 80%, transparent 100%);
            mask-image: linear-gradient(to bottom, #000 80%, transparent 100%);
        }
        /* Where reef and trench meet. Diving, the painting's lower third sinks into the
           water's own colour (--seabed, where the trench water starts) as the descent gets
           going, so the floor doesn't end in a line; at rest the fog is out (opacity 0, set
           in setScenery) and the painting shows whole. The cliffs below come into view
           over a third of the screen instead of starting at full strength under the reef. */
        .seabed-fog {
            position: absolute; left: 0; right: 0; bottom: 0; height: 28%;
            background: linear-gradient(to bottom, transparent,
                color-mix(in srgb, var(--seabed, var(--depth-2)) 45%, transparent) 55%,
                var(--seabed, var(--depth-2)) 92%);
            opacity: 0;
            pointer-events: none;
            will-change: opacity;
        }
        .waiting .seabed-fog, .betting .seabed-fog { transition: opacity 1.6s cubic-bezier(.3, .7, .3, 1); }
        .wall {
            position: absolute; top: calc(-1 * var(--wall-rise, 0px)); bottom: 0;
            width: clamp(64px, 16vw, 260px); overflow: hidden;
            -webkit-mask-image: linear-gradient(to bottom, transparent, #000 var(--wall-fade, 32vh));
            mask-image: linear-gradient(to bottom, transparent, #000 var(--wall-fade, 32vh));
        }
        /* Jackpot's portrait art ends in reef cliffs, wider than the trench's. The trench
           cliffs reach up into the painting's fogged lower part and come in with the dive
           (--walls-in, from setScenery), so one cliff dissolves into the other instead of
           a dark band between them; at rest they stay out of the painting. */
        @media (orientation: portrait) {
            :root[data-theme="jackpot"] .wall { --wall-rise: 24vh; --wall-fade: 20vh; opacity: var(--walls-in, 0); }
            :root[data-theme="jackpot"] :is(.waiting, .betting) .wall { transition: opacity 1.6s cubic-bezier(.3, .7, .3, 1); }
        }
        .wall-l { left: 0; }
        .wall-r { right: 0; }
        /* One tall strip per side: the art repeats down it, and it is shifted up by
           the descent modulo one tile (its width × --tile-ratio, the art's h/w). */
        .wall b {
            position: absolute; left: 0; right: 0; top: 0; height: 400%;
            background: repeat-y 0 0 / 100% auto;
            will-change: transform;
        }
        .waiting .backdrop, .betting .backdrop,
        .waiting .walls, .betting .walls,
        .waiting .wall b, .betting .wall b { transition: transform 1.6s cubic-bezier(.3, .7, .3, 1); }
        :root[data-theme="burton"] .wall-l { --tile-ratio: 3.566; }
        :root[data-theme="burton"] .wall-r { --tile-ratio: 4.014; }
        :root[data-theme="jackpot"] .wall-l { --tile-ratio: 3.327; }
        :root[data-theme="jackpot"] .wall-r { --tile-ratio: 3.726; }
        :root[data-theme="burton"] .wall-l b { background-image: url("{{ asset('images/wall-gothic-left.webp') }}?v={{ filemtime(public_path('images/wall-gothic-left.webp')) }}"); }
        :root[data-theme="burton"] .wall-r b { background-image: url("{{ asset('images/wall-gothic-right.webp') }}?v={{ filemtime(public_path('images/wall-gothic-right.webp')) }}"); }
        :root[data-theme="jackpot"] .wall-l b { background-image: url("{{ asset('images/wall-jackpot-left.webp') }}?v={{ filemtime(public_path('images/wall-jackpot-left.webp')) }}"); }
        :root[data-theme="jackpot"] .wall-r b { background-image: url("{{ asset('images/wall-jackpot-right.webp') }}?v={{ filemtime(public_path('images/wall-jackpot-right.webp')) }}"); }

        /* ---------- God rays ---------- */
        .rays {
            position: absolute; inset: 0;
            background:
              repeating-linear-gradient(
                100deg,
                transparent 0 6%,
                rgba(255,255,255,0.05) 6.2% 8%,
                transparent 8.2% 12%
              );
            mix-blend-mode: screen;
            opacity: 0.7;
            transition: opacity 1.5s ease;
        }
        .deep .rays   { opacity: 0.18; }
        .abyss .rays  { opacity: 0; }

        /* ---------- Particles ---------- */
        .particles { position: absolute; inset: 0; pointer-events: none; overflow: hidden; }
        .particle {
            position: absolute;
            width: 3px; height: 3px; border-radius: 50%;
            background: rgba(255,255,255,0.7);
            /* --p-drift mirrors the bubble parallax so silt also streams to
               the upper-left during the dive (sub-centred camera illusion).
               Transitioned smoothly to avoid all particles snapping onto a
               new trajectory at once when the stage class changes. */
            --p-drift: 40px;
            transition: --p-drift 2s ease-out;
            animation: drift linear infinite;
        }
        .running  .particle { --p-drift: -50vw; }
        .deep     .particle { --p-drift: -70vw; }
        .abyss    .particle { --p-drift: -90vw; }
        .critical .particle { --p-drift: -110vw; }
        @keyframes drift {
            from { transform: translateY(110vh) translateX(0); opacity: 0; }
            10%  { opacity: 0.8; }
            90%  { opacity: 0.8; }
            to   { transform: translateY(-10vh) translateX(var(--p-drift)); opacity: 0; }
        }

        /* ---------- Rising bubbles (always on, speed + drift scale with depth) ---------- */
        .bubbles { position: absolute; inset: 0; pointer-events: none; overflow: hidden; z-index: 2; }
        .bubble {
            position: absolute;
            border-radius: 50%;
            background:
              radial-gradient(circle at 32% 30%, rgba(255,255,255,0.85) 0%, rgba(186,230,253,0.45) 35%, rgba(14,165,233,0.15) 70%, transparent 100%);
            box-shadow: inset 0 0 4px rgba(255,255,255,0.4);
            opacity: 0;
            /* --base is set per-bubble in JS for randomised speed variation;
               --speed is a global multiplier driven by the stage class so the
               whole bubble field accelerates upward as the sub plunges deeper.
               --drift-x adds a horizontal slide — strongly negative during dive
               states fakes the parallax of the sub moving forward-right, so
               water (and bubbles) appear to stream toward the top-left. */
            --base: 18s;
            --speed: 1;
            --drift-x: -15px;
            /* Smoothly interpolate the parallax target + speed so state
               transitions don't yank every bubble onto a new path at once. */
            transition: --drift-x 2s ease-out, --speed 2s ease-out;
            /* --layer (set per bubble in JS) is its depth: far < 1 < near. It
               scales both speed and sideways drift, which is what makes the
               layers read as parallax. */
            animation: rise calc(var(--base) * var(--speed) / var(--layer, 1)) linear infinite;
        }
        .waiting  .bubble { --speed: 1.7; --drift-x: -15px; }
        .betting  .bubble { --speed: 1.3; --drift-x: -25px; }
        .running  .bubble { --speed: 0.35; --drift-x: -55vw; }
        .deep     .bubble { --speed: 0.22; --drift-x: -70vw; }
        .abyss    .bubble { --speed: 0.15; --drift-x: -85vw; }
        .critical .bubble { --speed: 0.10; --drift-x: -100vw; }
        @keyframes rise {
            0%   { transform: translate(0, 110vh) scale(0.6);  opacity: 0; }
            8%   { opacity: var(--peak, 0.85); }
            50%  { transform: translate(calc(var(--drift-x) * var(--layer, 1) * 0.5), 50vh) scale(1.0); }
            92%  { opacity: calc(var(--peak, 0.85) * 0.82); }
            100% { transform: translate(calc(var(--drift-x) * var(--layer, 1)), -10vh) scale(1.15); opacity: 0; }
        }
        /* Depth layers. Far: many small, faint, slow bubbles (always on). */
        .bubbles-far { z-index: 1; }
        .bubbles-far .bubble {
            background: radial-gradient(circle, rgba(220, 240, 255, 0.75) 0%, rgba(186, 230, 253, 0.25) 45%, transparent 72%);
            box-shadow: none;
            --peak: 0.5;
        }
        /* While diving only: extra density in the main field, and near bubbles
           rushing past in front of the sub. They fade in and out with the dive. */
        .bubbles-dive,
        .bubbles-near { opacity: 0; transition: opacity 1.2s ease; }
        .running .bubbles-dive, .deep .bubbles-dive, .abyss .bubbles-dive, .critical .bubbles-dive,
        .running .bubbles-near, .deep .bubbles-near, .abyss .bubbles-near, .critical .bubbles-near { opacity: 1; }
        /* Near: large, soft, out-of-focus bubbles; same layer as the sub but after it. */
        .bubbles-near { z-index: 7; }
        .bubbles-near .bubble {
            background:
              radial-gradient(circle at 32% 28%, rgba(255, 255, 255, 0.85) 0 7%, transparent 16%),
              radial-gradient(circle at 50% 50%, rgba(186, 230, 253, 0.06) 0 58%, rgba(224, 242, 254, 0.35) 68%, transparent 71%);
            box-shadow: inset 0 0 14px rgba(255, 255, 255, 0.3), 0 0 10px rgba(186, 230, 253, 0.25);
            --peak: 0.8;
        }

        /* ---------- Caustic light shimmer (surface zone) ---------- */
        .caustics {
            position: absolute; inset: 0;
            background:
              radial-gradient(ellipse 50% 25% at 30% 18%, rgba(186,230,253,0.18), transparent 70%),
              radial-gradient(ellipse 60% 30% at 70% 12%, rgba(186,230,253,0.14), transparent 70%);
            mix-blend-mode: screen;
            opacity: 0.85;
            transition: opacity 1.5s ease;
            animation: shimmer 11s ease-in-out infinite;
            z-index: 3;
        }
        .deep .caustics  { opacity: 0.25; }
        .abyss .caustics, .critical .caustics { opacity: 0; }
        @keyframes shimmer {
            0%, 100% { transform: translateX(0)    scaleY(1); }
            50%      { transform: translateX(-25px) scaleY(1.06); }
        }

        /* ---------- Shallow-water tropical fish (early phase) ---------- */
        .fishes { position: absolute; inset: 0; pointer-events: none; overflow: hidden; z-index: 4; }
        .fish {
            position: absolute;
            opacity: 0.85;
            transition: opacity 1.5s ease, --speed 2.5s ease-out;
            /* Single animation — keyframe already carries the diagonal upward
               sweep so a separate parallax track is unnecessary. */
            --dur: 20s;
            --speed: 1;
            animation-name: swim;
            animation-timing-function: linear;
            animation-iteration-count: infinite;
            animation-duration: calc(var(--dur) * var(--speed));
            filter: drop-shadow(0 4px 8px rgba(0,0,0,0.25));
        }
        .fish.swim-reverse { animation-name: swim-reverse; }
        .running  .fish { --speed: 0.35; }
        .deep .fish, .abyss .fish, .critical .fish, .crashed .fish { opacity: 0; }
        .fish svg {
            display: block;
            animation: bob 2.4s ease-in-out infinite;
        }
        /* Fish swim with an upward bias during the dive — both endpoints lie
           offscreen so the loop reset never reveals a "teleport" mid-screen. */
        @keyframes swim {
            0%   { transform: translate(-20vw,  60vh); }
            100% { transform: translate(120vw, -60vh); }
        }
        @keyframes swim-reverse {
            0%   { transform: translate(120vw,  60vh) scaleX(-1); }
            100% { transform: translate(-20vw, -60vh) scaleX(-1); }
        }
        @keyframes bob {
            0%, 100% { transform: translateY(0); }
            50%      { transform: translateY(-6px); }
        }

        /* ---------- Distant creature silhouettes ---------- */
        .creatures { position: absolute; inset: 0; pointer-events: none; overflow: hidden; z-index: 4; }
        .creature {
            position: absolute;
            opacity: 0.55;             /* always visible, including waiting/betting */
            /* Transition --speed smoothly so animation-duration shifts
               gradually — abrupt --speed jumps recompute progress vs
               new duration mid-cycle, snapping the silhouette. */
            transition: opacity 1.5s ease, filter 1.5s ease, --speed 2.5s ease-out;
            /* Single animation — cross/cross-reverse/swim-up already carry the
               diagonal-upward path so creatures stream past the sub from below
               toward the upper edge. --speed accelerates the cycle during the
               dive; the path itself never resets mid-screen because both
               endpoints sit well off-screen. */
            --dur: 60s;
            --speed: 1;
            animation-name: cross;
            animation-timing-function: linear;
            animation-iteration-count: infinite;
            animation-duration: calc(var(--dur) * var(--speed));
            filter:
              blur(0.6px)
              drop-shadow(0 0 14px rgba(8,15,30,0.85))
              drop-shadow(0 0 22px rgba(var(--hud-rgb),0.18));
        }
        .creature.cross-reverse { animation-name: cross-reverse; }
        /* Visibility + speed ramp up with depth. */
        .running  .creature { opacity: 0.70; --speed: 0.40; }
        .deep     .creature { opacity: 0.82; --speed: 0.25; filter: blur(0.4px) drop-shadow(0 0 16px rgba(var(--panel-rgb),0.9))  drop-shadow(0 0 24px rgba(var(--hud-rgb),0.25)); }
        .abyss    .creature { opacity: 0.92; --speed: 0.15; filter: blur(0.3px) drop-shadow(0 0 18px rgba(var(--panel-rgb),0.95)) drop-shadow(0 0 28px rgba(var(--hud-rgb),0.30)); }
        .critical .creature { opacity: 0.96; --speed: 0.10; filter: blur(0.3px) drop-shadow(0 0 18px rgba(var(--panel-rgb),0.95)) drop-shadow(0 0 24px rgba(239,68,68,0.30)); }

        .creature svg {
            display: block;
            transform: scale(1.35);
            transform-origin: center;
        }
        /* Whale SVG was authored with head on the left, which now matches the
           cross-reverse path (right → left + upward). No horizontal flip; just
           scale up so the silhouette reads at distance. */
        .creature.whale svg { transform: scale(1.55, 1.55); }

        /* Octopus tentacles sway gently from the mantle. */
        .creature.octopus svg { transform: scale(0.78); }
        .creature.octopus .tentacles {
            transform-box: fill-box;
            transform-origin: 50% 30%;
            animation: octopus-sway 3.6s ease-in-out infinite;
        }
        @keyframes octopus-sway {
            0%, 100% { transform: rotate(-3deg) scaleY(1); }
            50%      { transform: rotate(3deg)  scaleY(1.04); }
        }
        /* Creatures stream diagonally upward across the viewport — the
           sub-descending parallax. Both endpoints are well offscreen so the
           animation reset is invisible (no mid-screen "teleport"). */
        @keyframes cross {
            0%   { transform: translate(-35vw,  70vh); }
            100% { transform: translate(135vw, -70vh); }
        }
        @keyframes cross-reverse {
            0%   { transform: translate(135vw,  70vh) scaleX(-1); }
            100% { transform: translate(-35vw, -70vh) scaleX(-1); }
        }

        /* Mini octopi — small silhouettes that swim bottom → top with a gentle
           horizontal wobble. They share the .creature opacity ramps so they
           barely show in waiting/betting and fully reveal during the dive. */
        .creature.octopus.mini svg { transform: scale(1); }
        .creature.octopus.mini .tentacles { animation-duration: 2.4s; }
        .creature.octopus.mini { animation-name: swim-up; }
        @keyframes swim-up {
            0%   { transform: translate(  20vw, 115vh) rotate(-4deg); }
            25%  { transform: translate(  10vw,  80vh) rotate( 3deg); }
            50%  { transform: translate(   0vw,  50vh) rotate(-2deg); }
            75%  { transform: translate( -10vw,  20vh) rotate( 4deg); }
            100% { transform: translate( -20vw, -30vh) rotate( 0deg); }
        }

        /* ---------- Jellyfish (mid depth) ---------- */
        .jelly {
            position: absolute;
            width: 60px; height: 80px;
            opacity: 0;
            transition: opacity 1.5s ease;
            animation: float 9s ease-in-out infinite;
        }
        .deep .jelly, .abyss .jelly { opacity: 0.45; }
        .jelly::before {
            content: '';
            position: absolute; top: 0; left: 0; right: 0; height: 35px;
            background: radial-gradient(ellipse at 50% 60%, var(--hud) 0%, rgba(var(--hud-rgb),0.3) 50%, transparent 75%);
            border-radius: 50% 50% 40% 40%;
            box-shadow: 0 0 25px var(--hud);
        }
        .jelly::after {
            content: '';
            position: absolute; top: 30px; left: 25%; right: 25%; bottom: 0;
            background: linear-gradient(180deg, rgba(var(--hud-rgb),0.4) 0%, transparent 90%);
        }
        @keyframes float {
            0%, 100% { transform: translateY(0) }
            50%      { transform: translateY(-30px) }
        }

        /* ---------- Submarine ---------- */
        .sub-wrap {
            position: absolute; left: 50%; top: 30%;
            transform: translate(-50%, calc(-50% - var(--lift, 0px)));
            width: 277px; height: 150px; /* 80% of the first 346 × 187: players found it too big */
            /* Faster, punchier plunge — ease-in so each depth threshold feels
               like gravity pulls the sub down rather than a uniform drift. */
            transition: top 2.5s cubic-bezier(.42, 0, .35, 1), transform 0.15s ease;
            filter: drop-shadow(0 26px 36px rgba(0,0,0,0.55));
            z-index: 7;  /* always above sea life (creatures z:4, fish z:4, jellyfish, bubbles), below HUD (z:8+) */
        }
        /* Diving, the sub sinks a little on screen (the scenery carries the real
           descent) but never behind the multiplier: --hud-room is the height the
           multiplier row takes at the bottom of the screen (measured in JS),
           --sink how far the tilted hull reaches below its centre at that tier. */
        :is(.running, .critical) .sub-wrap {
            top: min(var(--dip), calc(100% - var(--hud-room, 240px) - var(--sink) - 12px + var(--lift, 0px)));
        }
        .running .sub-wrap   { --dip: 50%; --sink: 64px; }
        .deep .sub-wrap      { --dip: 56%; --sink: 74px; }
        .abyss .sub-wrap     { --dip: 60%; --sink: 83px; }
        .critical .sub-wrap  { --dip: 62%; --sink: 91px; animation: shake 0.18s linear infinite; }
        /* `translate`, so the embed's lift in `transform` is kept while it shakes. */
        @keyframes shake {
            0%,100% { translate: 0 0; }
            20%     { translate: -2% 1%; }
            50%     { translate: 2% -1%; }
            80%     { translate: -1% 0; }
        }
        /* Inner SVG handles nose-down tilt independently of the shake/translate
           on the wrap. Sub bow points right (x=225 in viewBox), so positive
           rotation == nose pitching down toward the abyss. */
        .sub-svg {
            width: 100%; height: 100%; display: block; overflow: visible;
            transform: rotate(0deg);
            transform-origin: 50% 55%;
            transition: transform 1.2s cubic-bezier(.42, 0, .35, 1);
        }
        .running  .sub-svg { transform: rotate(14deg); }
        .deep     .sub-svg { transform: rotate(24deg); }
        .abyss    .sub-svg { transform: rotate(32deg); }
        .critical .sub-svg { transform: rotate(38deg); }

        .propeller {
            transform-box: fill-box;
            transform-origin: center;
            animation: spin 0.5s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
        .running  .propeller { animation-duration: 0.14s; }
        .deep     .propeller { animation-duration: 0.10s; }
        .abyss    .propeller { animation-duration: 0.08s; }
        .critical .propeller { animation-duration: 0.06s; }
        .periscope-light { opacity: 0; }
        .running .periscope-light { opacity: 0.85; }

        /* Propeller bubble trail — each bubble translates up-and-back from the
           propeller's local origin while shrinking + fading. The .sub-svg
           rotation (nose-down) carries the whole group so the trail naturally
           streams upward in screen space. */
        .sub-bubbles { opacity: 0; transition: opacity 0.6s; }
        .running  .sub-bubbles { opacity: 0.9; }
        .deep     .sub-bubbles { opacity: 1; }
        .abyss    .sub-bubbles { opacity: 1; }
        .critical .sub-bubbles { opacity: 1; }
        .prop-bubble {
            opacity: 0;
            animation: prop-bubble 1.4s ease-out infinite;
            transform-box: fill-box;
            transform-origin: center;
        }
        .running  .prop-bubble { animation-duration: 1.4s; }
        .deep     .prop-bubble { animation-duration: 1.0s; }
        .abyss    .prop-bubble { animation-duration: 0.75s; }
        .critical .prop-bubble { animation-duration: 0.55s; }

        /* Submarine wake — tapered streak trailing behind the propeller.
           Curved (bows upward) and lengthens with depth. Anchored at the
           propeller end via transform-origin so growth fans out away from
           the sub instead of stretching from the center. */
        .sub-wake, .sub-wake-core {
            opacity: 0;
            transform: scale(0.2);
            transform-origin: 100% 100%;   /* near-propeller end of the bbox */
            transform-box: fill-box;
            transition: opacity 0.4s ease, transform 0.6s ease;
        }
        .running  .sub-wake { opacity: 0.65; transform: scale(0.50); }
        .deep     .sub-wake { opacity: 0.80; transform: scale(0.85); }
        .abyss    .sub-wake { opacity: 0.92; transform: scale(1.10); }
        .critical .sub-wake { opacity: 1.00; transform: scale(1.35); animation: wake-shimmer 0.5s ease-in-out infinite; }
        @keyframes wake-shimmer {
            0%, 100% { filter: brightness(1)   blur(0.3px); }
            50%      { filter: brightness(1.4) blur(0px); }
        }

        /* Dashed core stripes — three parallel rails, each with its own pace
           so the streaming reads as turbulent water rather than a metronome. */
        .sub-wake-core {
            stroke-dasharray: 9 6;
            stroke-dashoffset: 0;
            animation: wake-scroll 0.7s linear infinite;
        }
        .running  .sub-wake-core { opacity: 0.65; transform: scale(0.55); animation-duration: 0.75s; }
        .deep     .sub-wake-core { opacity: 0.80; transform: scale(0.90); animation-duration: 0.50s; }
        .abyss    .sub-wake-core { opacity: 0.92; transform: scale(1.10); animation-duration: 0.32s; }
        .critical .sub-wake-core { opacity: 1.00; transform: scale(1.35); animation-duration: 0.20s; }

        .sub-wake-core.alt {
            stroke-dasharray: 6 9;        /* shorter dash, longer gap — staggered */
            animation-delay: -0.18s;       /* offset start so rails don't sync */
        }
        .sub-wake-core.alt-2 {
            stroke-dasharray: 4 11;
            animation-delay: -0.36s;
        }

        @keyframes wake-scroll {
            to { stroke-dashoffset: -30; } /* negative = move along path → leftward → upward post-rotation */
        }
        @keyframes prop-bubble {
            0%   { transform: translate(0, 0)         scale(0.55); opacity: 0; }
            10%  { opacity: 0.85; }
            60%  { opacity: 0.6; }
            100% { transform: translate(-36px, -42px) scale(1.25); opacity: 0; }
        }
        /* Two extra path variants so the stream looks chaotic instead of a
           single line of identical bubbles. */
        .prop-bubble.var-2 { animation-name: prop-bubble-2; }
        .prop-bubble.var-3 { animation-name: prop-bubble-3; }
        @keyframes prop-bubble-2 {
            0%   { transform: translate(0, 0)         scale(0.45); opacity: 0; }
            12%  { opacity: 0.9; }
            60%  { opacity: 0.5; }
            100% { transform: translate(-48px, -32px) scale(1.10); opacity: 0; }
        }
        @keyframes prop-bubble-3 {
            0%   { transform: translate(0, 0)         scale(0.6);  opacity: 0; }
            10%  { opacity: 0.85; }
            60%  { opacity: 0.55; }
            100% { transform: translate(-28px, -52px) scale(1.35); opacity: 0; }
        }

        /* ---------- HUD ---------- */
        .hud-top {
            position: absolute; top: 0; left: 0; right: 0;
            display: flex; justify-content: space-between; align-items: flex-start;
            padding: 16px 20px;
            pointer-events: none;
            z-index: 10;
        }

        /* ---------- Cockpit gauges (pressure | hull) flanking the multiplier ----------
           data-level (ok | warn | crit, set from JS) drives the accent colour. */
        .gauge {
            --gc: var(--hud); --gc-rgb: var(--hud-rgb);
            position: relative;
            display: flex; align-items: center; gap: 12px;
            padding: 10px 16px 10px 12px;
            background:
              radial-gradient(120% 140% at 0% 0%, rgba(var(--gc-rgb),0.18), transparent 55%),
              linear-gradient(160deg, rgba(var(--panel-rgb),0.72), rgba(var(--panel-rgb),0.92));
            border: 1px solid rgba(var(--gc-rgb),0.45);
            border-radius: 14px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.45), inset 0 1px 0 rgba(255,255,255,0.08), 0 0 18px rgba(var(--gc-rgb),0.15);
            backdrop-filter: blur(8px);
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }
        .gauge[data-level="warn"] { --gc: var(--gold); --gc-rgb: 251,191,36; }
        .gauge[data-level="crit"] { --gc: var(--warn); --gc-rgb: 239,68,68; animation: gauge-alarm 0.6s ease-in-out infinite alternate; }
        @keyframes gauge-alarm {
            to { border-color: rgba(239,68,68,0.95); box-shadow: 0 8px 24px rgba(0,0,0,0.45), inset 0 1px 0 rgba(255,255,255,0.08), 0 0 28px rgba(239,68,68,0.6); }
        }
        .crashed .gauge { filter: saturate(0.5) brightness(0.8); animation: none; }
        .gauge svg { display: block; flex: none; overflow: visible; }
        .gauge-text { min-width: 84px; }
        .gauge-hull .gauge-text { text-align: right; }
        .gauge-label {
            font-size: 10px; font-weight: 700; letter-spacing: 0.2em;
            text-transform: uppercase; color: var(--text-muted);
        }
        .gauge-value {
            font-size: 22px; font-weight: 800; line-height: 1.15;
            font-variant-numeric: tabular-nums;
            color: var(--hud-bright);
            text-shadow: 0 0 12px rgba(var(--gc-rgb),0.6), 0 2px 0 rgba(0,0,0,0.6);
            transition: color 0.3s ease;
        }
        .gauge[data-level="warn"] .gauge-value,
        .gauge[data-level="crit"] .gauge-value { color: var(--gc); }
        .gauge-value small { margin-left: 3px; font-size: 11px; font-weight: 600; color: var(--text-muted); text-shadow: none; }

        /* Pressure dial: 270° sweep with a red zone; the needle trembles while diving. */
        .dial-face { fill: rgba(0,0,0,0.4); stroke: rgba(var(--hud-rgb),0.25); stroke-width: 1; }
        .dial-track, .dial-arc { fill: none; stroke-width: 3; stroke-linecap: round; }
        .dial-track { stroke: rgba(var(--hud-rgb),0.15); stroke-dasharray: 75 100; }
        .dial-arc {
            stroke: var(--gc); stroke-dasharray: 0 100;
            filter: drop-shadow(0 0 3px var(--gc));
            transition: stroke-dasharray 0.2s linear, stroke 0.3s ease;
        }
        .dial-tick { stroke: rgba(var(--hud-rgb),0.6); stroke-width: 1.2; }
        .dial-tick.hot { stroke: var(--warn); }
        .dial-needle {
            stroke: var(--hud-bright); stroke-width: 1.8; stroke-linecap: round;
            transform-origin: 22px 22px; transform: rotate(-135deg);
            transition: transform 0.2s linear;
        }
        .dial-hub { fill: var(--gc); stroke: rgba(0,0,0,0.6); stroke-width: 1; }
        .dial-wobble { transform-origin: 22px 22px; }
        .running .dial-wobble { animation: needle-wobble 0.22s ease-in-out infinite alternate; }
        .critical .dial-wobble { animation-duration: 0.08s; }
        @keyframes needle-wobble { from { transform: rotate(-2.5deg); } to { transform: rotate(2.5deg); } }

        /* Hull: shield drains with the integrity, segmented bar shifts green → red (--hull-c from JS). */
        .hull-shield-outline { fill: rgba(0,0,0,0.4); stroke: var(--gc); stroke-width: 1.4; stroke-linejoin: round; transition: stroke 0.3s ease; }
        .hull-shield-level {
            fill: var(--hull-c, var(--good)); opacity: 0.9;
            transform-box: fill-box; transform-origin: bottom;
            transition: transform 0.2s linear, fill 0.3s ease;
        }
        .critical .gauge-hull svg { animation: hull-rattle 0.16s linear infinite; }
        @keyframes hull-rattle { 25% { transform: translate(-1px, 1px) rotate(-3deg); } 75% { transform: translate(1px, -1px) rotate(3deg); } }
        .hull-bar {
            margin: 6px 0 0 auto;
            width: 120px; height: 7px;
            background: rgba(255,255,255,0.1);
            border-radius: 2px;
            overflow: hidden;
            -webkit-mask: repeating-linear-gradient(90deg, #000 0 calc(10% - 2px), transparent calc(10% - 2px) 10%);
                    mask: repeating-linear-gradient(90deg, #000 0 calc(10% - 2px), transparent calc(10% - 2px) 10%);
        }
        .hull-fill {
            height: 100%;
            background: var(--hull-c, var(--good));
            box-shadow: 0 0 8px var(--hull-c, var(--good));
            transition: width 0.2s linear, background 0.3s ease;
        }

        /* ---------- Depth meter (left edge) ----------
           Glass tube from surface to the Challenger Deep; --p (0..1, set from JS)
           drives the needle, the travelled-depth fill and the readout chip. */
        .depth-meter {
            --p: 0; --dc: var(--hud-bright); --dc-rgb: var(--hud-rgb);
            position: absolute; left: 22px; top: 150px;
            height: clamp(200px, 46vh, 520px); width: 14px;
            pointer-events: none;
            z-index: 9;
        }
        .depth-track {
            position: absolute; inset: 0;
            border-radius: 999px;
            background: linear-gradient(180deg,
                rgba(150,220,215,0.55) 0%, rgba(60,110,140,0.5) 18%,
                rgba(40,40,80,0.55) 45%, rgba(25,15,40,0.7) 72%, rgba(90,12,24,0.8) 100%);
            border: 1px solid rgba(var(--hud-rgb),0.4);
            box-shadow: inset 2px 0 3px rgba(255,255,255,0.18), 0 4px 18px rgba(0,0,0,0.5);
        }
        .depth-fill {
            position: absolute; left: 3px; right: 3px; top: 3px;
            height: calc((100% - 6px) * var(--p));
            border-radius: 999px;
            background: linear-gradient(180deg, rgba(var(--dc-rgb),0.25), rgba(var(--dc-rgb),0.85));
            box-shadow: 0 0 10px rgba(var(--dc-rgb),0.6);
        }
        .depth-tick {
            position: absolute; left: 100%; top: var(--at);
            display: flex; align-items: center; gap: 4px;
            transform: translateY(-50%);
            font-size: 10px; font-weight: 700; letter-spacing: 0.08em;
            color: var(--text); opacity: 0.8; white-space: nowrap;
            text-shadow: 0 1px 2px #000, 0 0 6px #000;
        }
        .depth-meter::before {
            content: "DEPTH"; position: absolute; left: -4px; bottom: calc(100% + 8px);
            font-size: 9px; font-weight: 800; letter-spacing: 0.24em;
            color: var(--text-muted); text-shadow: 0 1px 2px #000;
        }
        .depth-tick::before { content: ""; width: 6px; height: 1px; background: rgba(var(--hud-rgb),0.6); }
        .depth-needle {
            position: absolute; left: -6px; top: calc(var(--p) * 100%);
            display: flex; align-items: center;
            transform: translateY(-50%);
        }
        .depth-needle::before {
            content: ""; width: 26px; height: 3px; border-radius: 2px;
            background: var(--dc); box-shadow: 0 0 8px var(--dc), 0 0 2px #000;
        }
        .depth-chip {
            margin-left: 6px; padding: 5px 10px 5px 9px;
            background: rgba(var(--panel-rgb),0.88);
            border: 1px solid rgba(var(--dc-rgb),0.6);
            border-radius: 4px 10px 10px 4px;
            box-shadow: 0 4px 14px rgba(0,0,0,0.5), 0 0 12px rgba(var(--dc-rgb),0.25);
            white-space: nowrap; line-height: 1.1;
        }
        .depth-chip b {
            display: block;
            font-size: 15px; font-weight: 800; font-variant-numeric: tabular-nums;
            color: var(--dc);
        }
        .depth-chip small {
            display: block; margin-top: 2px;
            font-size: 8px; font-weight: 700; letter-spacing: 0.18em;
            color: var(--text-muted);
        }
        :root[data-theme="burton"] .depth-chip b { font-family: "Palatino Linotype", "Book Antiqua", Georgia, serif; font-size: 17px; }
        .critical .depth-meter { --dc: var(--warn); --dc-rgb: 239,68,68; }
        .critical:not(.crashed) .depth-chip { animation: depth-alarm 0.6s ease-in-out infinite alternate; }
        @keyframes depth-alarm { to { box-shadow: 0 4px 14px rgba(0,0,0,0.5), 0 0 22px rgba(239,68,68,0.7); } }
        @media (max-width: 720px) {
            .depth-meter { left: 10px; top: 140px; width: 8px; height: clamp(160px, 34vh, 320px); }
            .depth-tick { display: none; }
            .depth-needle::before { width: 18px; }
            .depth-chip { padding: 3px 7px; }
            .depth-chip b { font-size: 12px; }
            :root[data-theme="burton"] .depth-chip b { font-size: 13px; }
            .depth-chip small { font-size: 7px; letter-spacing: 0.12em; }
        }

        /* ---------- Recent crashes history ---------- */
        /* The rail stays clear of the buttons at the top right (--ctl-reserve: their
           row's width plus margins, measured in the script): centred with that much room
           on both sides. (On phones the buttons stack down the edge, so it's narrow.) */
        :root { --ctl-reserve: 146px; }
        @media (max-width: 640px) { :root { --ctl-reserve: 46px; } }
        .history-wrap {
            position: absolute;
            top: 18px; left: 50%;
            transform: translateX(-50%);
            display: flex; flex-direction: column; align-items: center;
            gap: 4px;
            z-index: 9;
            max-width: calc(100vw - 2 * var(--ctl-reserve));
        }
        .history-wrap .history { max-width: 100%; }
        @media (max-width: 640px) {
            /* The status pill under it on one line. */
            .history-wrap .state {
                width: max-content;
                gap: 9px; padding: 7px 16px 7px 13px; font-size: 12px; letter-spacing: 0.16em;
            }
        }
        .history-label {
            font-size: 10px; letter-spacing: 0.32em;
            color: var(--hud); opacity: 0.7;
            text-transform: uppercase;
            text-shadow: 0 0 8px rgba(var(--hud-rgb),0.3), 0 1px 3px rgba(0,0,0,0.45);
        }
        .history {
            display: flex; gap: 7px;
            padding: 8px 14px;
            background: rgba(var(--panel-rgb),0.55);
            border: 1px solid rgba(var(--hud-rgb),0.25);
            border-radius: 999px;
            backdrop-filter: blur(6px);
            overflow: hidden;
        }
        .history-chip {
            font-size: 14px; font-weight: 700;
            padding: 5px 12px;
            border-radius: 999px;
            white-space: nowrap;
            letter-spacing: 0.04em;
            transition: transform 0.3s ease;
        }
        /* A chip opens its round's details. */
        button.history-chip {
            border: 0; font-family: inherit; line-height: inherit; cursor: pointer;
        }
        button.history-chip:hover { filter: brightness(1.25); }
        button.history-chip:focus-visible { outline: 2px solid var(--hud); outline-offset: 2px; }
        /* The same tier colours mark multipliers in the round details and My bets. */
        :is(.history-chip, .x-chip).tier-low  { background: rgba(239,68,68,0.20);  color: #fecaca; }
        :is(.history-chip, .x-chip).tier-mid  { background: rgba(251,191,36,0.20); color: #fef3c7; }
        :is(.history-chip, .x-chip).tier-high { background: rgba(34,197,94,0.20);  color: #bbf7d0; }
        :is(.history-chip, .x-chip).tier-epic {
            background: rgba(168,85,247,0.30);
            color: #f3e8ff;
            box-shadow: 0 0 12px rgba(168,85,247,0.45);
        }
        .history-chip.is-new { animation: chip-pop 0.5s cubic-bezier(0.34, 1.56, 0.64, 1); }
        @keyframes chip-pop {
            0%   { transform: scale(0); opacity: 0; }
            60%  { transform: scale(1.15); opacity: 1; }
            100% { transform: scale(1); }
        }
        @media (max-width: 500px) {
            .history-wrap { top: 14px; gap: 3px; }
            .history-label { font-size: 9px; letter-spacing: 0.24em; }
            .history {
                gap: 4px; padding: 5px 8px;
                /* Cleaner than `overflow: hidden` here — flexbox will shrink
                   each chip down rather than mid-cropping the rightmost one. */
                flex-wrap: nowrap;
            }
            .history-chip {
                font-size: 11px; padding: 3px 6px;
                flex: 0 0 auto;
            }
            /* Show only the most recent few — anything past the 6th gets
               clipped no matter how small the chip is, and a half-chip
               looks broken. pushHistory() uses unshift, so DOM order is
               newest→oldest; nth-child(n+7) hides the oldest. */
            .history-chip:nth-child(n+7) { display: none; }
        }

        /* ---------- Multiplier (sits low so the submarine commands the centre) ---------- */
        .center {
            position: absolute;
            left: 0; right: 0;
            bottom: 70px;
            display: flex; flex-direction: column;
            align-items: center; gap: 6px;
            pointer-events: none;
            z-index: 8;
        }
        /* In embed mode the operator HUD already eats 52px at the bottom — keep
           a steady ~16px gap above it so the multiplier doesn't crowd the bar. */
        body.embedded .center { bottom: calc(16px + var(--op-bar-h)); }

        /* Row that puts pressure | multiplier | hull on the same baseline. */
        .center-row {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: clamp(18px, 3.5vw, 44px);
            width: 100%;
        }
        @media (max-width: 720px) {
            .center-row { gap: 14px; }
            .center-row .gauge { padding: 6px 9px; }
            .center-row .gauge { gap: 8px; }
            .center-row .gauge svg { width: 32px; height: 32px; }
            .center-row .gauge-text { min-width: 60px; }
            .center-row .gauge-value { font-size: 16px; }
            .center-row .hull-bar { width: 90px; }
        }
        @media (max-width: 480px) {
            .center-row .gauge { padding: 4px 7px; }
            .center-row .gauge-label { font-size: 9px; }
            .center-row .hull-bar { width: 56px; }
            .center-row .gauge svg, .gauge-label-long { display: none; }
            .center-row .gauge-text { min-width: 0; }
            .center-row .gauge-label { letter-spacing: 0.12em; }
            .center-row .gauge-value { font-size: 15px; }
        }
        .multiplier {
            font-family: "SF Mono", ui-monospace, monospace;
            font-size: clamp(72px, 14vw, 160px);
            font-weight: 700; line-height: 0.95;
            letter-spacing: -0.04em;
            background: linear-gradient(180deg, var(--hud-bright) 0%, var(--hud) 50%, var(--hud-dark) 100%);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            text-shadow: 0 0 40px rgba(var(--hud-rgb),0.4);
            transition: text-shadow 0.5s ease, transform 0.2s ease;
        }
        .deep .multiplier {
            background: linear-gradient(180deg, #fef3c7 0%, var(--gold) 50%, #b45309 100%);
            -webkit-background-clip: text; background-clip: text;
            text-shadow: 0 0 50px rgba(251,191,36,0.55);
        }
        .critical .multiplier {
            background: linear-gradient(180deg, #fecaca 0%, var(--warn) 50%, #7f1d1d 100%);
            -webkit-background-clip: text; background-clip: text;
            text-shadow: 0 0 70px rgba(239,68,68,0.85);
            animation: pulse 0.6s ease-in-out infinite;
        }
        .crashed .multiplier {
            color: var(--warn) !important;
            background: none !important; -webkit-background-clip: initial; background-clip: initial;
            text-shadow: 0 0 60px var(--warn);
            animation: shatter 0.6s forwards;
        }
        @keyframes pulse { 50% { transform: scale(1.04); } }
        @keyframes shatter {
            0%   { transform: scale(1); opacity: 1; }
            30%  { transform: scale(1.15); }
            100% { transform: scale(0.8); opacity: 0.45; }
        }
        /* Round status pill; the dot follows the stage (gold betting, live green, red crash). */
        .state {
            --dot: var(--hud);
            display: inline-flex; align-items: center; gap: 11px;
            margin: 8px 0 0; padding: 8px 20px 8px 15px;
            font-size: 14px; font-weight: 700; letter-spacing: 0.24em;
            font-variant-numeric: tabular-nums;
            color: var(--hud-bright);
            text-transform: uppercase;
            background: rgba(var(--panel-rgb),0.6);
            border: 1px solid rgba(var(--hud-rgb),0.25);
            border-radius: 999px;
            box-shadow: 0 4px 14px rgba(0,0,0,0.35);
            text-shadow: 0 1px 2px rgba(0,0,0,0.6);
        }
        .state::before {
            content: ""; flex: none; width: 10px; height: 10px; border-radius: 50%;
            background: var(--dot); box-shadow: 0 0 8px var(--dot);
        }
        .betting .state { --dot: var(--gold); }
        .running .state { --dot: var(--good); }
        .running .state::before { animation: state-live 1s ease-in-out infinite; }
        .critical .state, .crashed .state { --dot: var(--warn); border-color: rgba(239,68,68,0.45); }
        @keyframes state-live { 50% { opacity: 0.35; transform: scale(0.7); } }

        /* ---------- Warnings (only critical) ----------
           Right under the status pill, in the top HUD: clear of the sub, which
           sits high on short screens to stay clear of the multiplier. */
        .warnings {
            display: flex; flex-wrap: wrap; justify-content: center; gap: 8px;
            margin-top: 4px; max-width: calc(100vw - 32px);
            pointer-events: none;
        }
        .warnings > div {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 6px 14px 6px 8px;
            font-size: 11px; font-weight: 800; letter-spacing: 0.18em; text-transform: uppercase;
            color: #fecaca;
            background: linear-gradient(180deg, rgba(127,29,29,0.75), rgba(69,10,10,0.8));
            border: 1px solid rgba(239,68,68,0.7);
            border-radius: 6px;
            box-shadow: 0 0 18px rgba(239,68,68,0.35), inset 0 1px 0 rgba(255,255,255,0.12);
            opacity: 0; transform: translateY(-8px);
            transition: opacity 0.25s ease, transform 0.25s ease;
        }
        .warnings i {
            display: grid; place-items: center; width: 18px; height: 18px;
            font-style: normal; font-size: 12px; font-weight: 900; color: #1a0505;
            background: var(--warn); border-radius: 4px;
            animation: warn-blink 0.6s steps(1) infinite;
        }
        .critical:not(.crashed) .warnings > div { opacity: 1; transform: none; }
        .critical:not(.crashed) .warnings > div + div { transition-delay: 0.12s; }
        @keyframes warn-blink { 50% { background: #fecaca; } }

        /* ---------- Cash out ---------- */
        .cashout-wrap {
            position: absolute; bottom: 32px; left: 0; right: 0;
            display: flex; justify-content: center;
            z-index: 12;
        }
        .cashout {
            padding: 18px 56px;
            font-family: inherit; font-size: 16px; font-weight: 700;
            letter-spacing: 0.25em; text-transform: uppercase;
            color: #022c22; background: var(--good);
            border: none; border-radius: 999px;
            cursor: pointer;
            box-shadow: 0 0 30px rgba(34,197,94,0.7), inset 0 -3px 0 rgba(0,0,0,0.18);
            transition: transform 0.15s ease, box-shadow 0.3s ease, opacity 0.3s ease;
        }
        .cashout:hover { transform: translateY(-2px); box-shadow: 0 0 50px rgba(34,197,94,0.9); }
        .cashout:active { transform: translateY(1px); }
        .cashout:disabled { opacity: 0.35; cursor: not-allowed; box-shadow: none; }
        .cashout small {
            display: block; font-size: 9px; letter-spacing: 0.2em;
            opacity: 0.7; margin-top: 4px; font-weight: 500;
        }

        /* ---------- Top-right buttons ----------
           One row (a column on phones), right to left: fullscreen, sound, theme, All
           bets, My bets. A button that isn't offered is display:none, and the rest
           close up; the recent-crashes rail keeps clear of the row (--ctl-reserve,
           measured in the script). */
        .top-controls {
            position: fixed;
            top: 14px; right: 14px;
            z-index: 40;
            display: flex; flex-direction: row-reverse; gap: 8px;
        }
        .ctl-btn {
            width: 34px; height: 34px;
            display: inline-flex; align-items: center; justify-content: center;
            background: rgba(var(--panel-rgb),0.7);
            border: 1px solid rgba(var(--hud-rgb),0.32);
            border-radius: 6px;
            color: var(--hud);
            cursor: pointer;
            backdrop-filter: blur(6px);
            box-shadow: 0 0 10px rgba(var(--hud-rgb),0.10);
            transition: box-shadow 0.18s, transform 0.12s, background 0.18s, color 0.18s;
        }
        .ctl-btn:hover,
        .ctl-btn[aria-expanded="true"] {
            background: rgba(var(--panel-rgb),0.85);
            box-shadow: 0 0 18px rgba(var(--hud-rgb),0.45);
            color: var(--hud-bright);
        }
        .ctl-btn:active { transform: translateY(1px); }
        .ctl-btn svg { width: 16px; height: 16px; display: block; pointer-events: none; }

        /* Sound */
        .audio-btn .audio-icon-mute { display: none; }
        .audio-btn.is-muted { color: var(--text-muted); }
        .audio-btn.is-muted .audio-icon-on { display: none; }
        .audio-btn.is-muted .audio-icon-mute { display: block; }

        /* Fullscreen. None to be had (the page on its own on an iPhone): the button goes. */
        .fs-btn .fs-icon-exit { display: none; }
        .no-fullscreen .fs-btn { display: none; }
        :fullscreen .fs-btn .fs-icon-enter,
        body.is-fullscreen .fs-btn .fs-icon-enter { display: none; }
        :fullscreen .fs-btn .fs-icon-exit,
        body.is-fullscreen .fs-btn .fs-icon-exit { display: block; }

        /* My bets: only when the page hosting the game answers bet_history (Orange's
           host pages say so in player_info). */
        .bets-btn { display: none; }
        .has-bets .bets-btn { display: inline-flex; }

        .theme-menu {
            position: fixed;
            top: 54px; right: 14px;
            z-index: 41;
            min-width: 200px;
            padding: 6px;
            background: rgba(var(--panel-rgb),0.9);
            border: 1px solid rgba(var(--hud-rgb),0.32);
            border-radius: 8px;
            backdrop-filter: blur(8px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.45), 0 0 18px rgba(var(--hud-rgb),0.12);
            transform-origin: 100% 0;
            animation: theme-menu-in 0.16s ease-out;
        }
        .theme-menu[hidden] { display: none; }
        @keyframes theme-menu-in {
            from { opacity: 0; transform: translateY(-4px) scale(0.97); }
            to   { opacity: 1; transform: none; }
        }
        .theme-menu-label {
            padding: 6px 10px 4px;
            font-size: 9px; letter-spacing: 0.28em; text-transform: uppercase;
            color: var(--hud); opacity: 0.7;
        }
        .theme-opt {
            display: flex; align-items: center; gap: 10px;
            width: 100%;
            padding: 7px 10px;
            background: transparent;
            border: 1px solid transparent;
            border-radius: 6px;
            color: var(--text);
            font-family: inherit; font-size: 12px; font-weight: 700;
            letter-spacing: 0.06em;
            text-align: left;
            cursor: pointer;
            transition: background 0.15s, border-color 0.15s, color 0.15s;
        }
        .theme-opt:hover { background: rgba(var(--hud-rgb),0.12); }
        .theme-opt.is-active {
            border-color: rgba(var(--hud-rgb),0.5);
            background: rgba(var(--hud-rgb),0.10);
            color: var(--hud-bright);
        }
        .theme-opt.is-active::after {
            content: '✓';
            margin-left: auto;
            color: var(--hud);
            font-size: 12px;
        }
        /* Swatch previews the skin's own colours (set inline per option) so
           every row looks right no matter which skin is active. */
        .theme-swatch {
            flex: 0 0 auto;
            width: 18px; height: 18px;
            border-radius: 50%;
            background: linear-gradient(160deg, var(--sw-top) 0%, var(--sw-deep) 100%);
            box-shadow: inset 0 0 0 2px var(--sw-hud), 0 0 6px var(--sw-hud);
        }
        /* Phones: the buttons a little smaller, stacked down the right edge (theme at the
           top, its menu opening beside it), so the recent-crashes rail can stay centred. */
        @media (max-width: 640px) {
            .top-controls { top: 12px; right: 10px; flex-direction: column; gap: 4px; }
            .ctl-btn { width: 30px; height: 30px; }
            .theme-btn { order: 1; }
            .audio-btn { order: 2; }
            .fs-btn    { order: 3; }
            .live-btn  { order: 4; }
            .bets-btn  { order: 5; }
            .theme-menu { top: 12px; right: 48px; }
        }

        /* ---------- All bets: every player's bet in the round, live ----------
           Docked at the right on wide screens, between the buttons and the multiplier
           row (--hud-room); elsewhere it opens over the game from its button. */
        .live-bets {
            position: fixed; z-index: 35;
            top: 96px; right: 14px;
            bottom: calc(var(--op-bar-h, 0px) + 14px);
            width: min(300px, calc(100vw - 28px));
            display: flex; flex-direction: column;
            background: rgba(var(--panel-rgb),0.82);
            border: 1px solid rgba(var(--hud-rgb),0.28);
            border-radius: 12px;
            backdrop-filter: blur(8px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.4);
            font-size: 12px;
            overflow: hidden;
        }
        .live-bets[hidden] { display: none; }
        @media (min-width: 1100px) and (min-height: 600px) {
            .live-bets { bottom: calc(var(--hud-room, 220px) + 12px); }
        }
        @media (max-width: 640px) {
            .live-bets { top: 120px; right: 48px; left: 10px; width: auto; bottom: calc(var(--op-bar-h, 0px) + 10px); }
        }
        .lb-top { display: flex; align-items: center; gap: 4px; padding: 8px 6px 0 8px; }
        .lb-tabs {
            flex: 1;
            display: grid; grid-template-columns: 1fr 1fr;
            padding: 3px;
            background: rgba(0,0,0,0.3);
            border-radius: 999px;
        }
        .lb-tab {
            padding: 6px 8px;
            background: transparent; border: 0; border-radius: 999px;
            color: var(--text-muted);
            font: inherit; font-size: 11px; font-weight: 700; letter-spacing: 0.06em;
            cursor: pointer;
        }
        .lb-tab[aria-selected="true"] { background: rgba(var(--hud-rgb),0.2); color: var(--hud-bright); }
        .lb-tab:focus-visible { outline: 2px solid var(--hud); outline-offset: -2px; }
        .lb-summary {
            display: flex; align-items: flex-end; justify-content: space-between; gap: 8px;
            padding: 10px 12px 7px;
        }
        .lb-summary b { display: block; font-size: 15px; color: var(--hud-bright); white-space: nowrap; }
        .lb-summary small { font-size: 10px; letter-spacing: 0.06em; color: var(--text-muted); white-space: nowrap; }
        .lb-result { display: flex; flex-direction: column; align-items: center; gap: 3px; }
        .lb-result[hidden] { display: none; }
        .lb-total { min-width: 0; text-align: right; }
        .lb-total b { overflow: hidden; text-overflow: ellipsis; }
        .lb-progress { height: 4px; margin: 0 12px 8px; background: rgba(0,0,0,0.35); border-radius: 999px; overflow: hidden; }
        .lb-progress i { display: block; width: 0; height: 100%; background: var(--good); transition: width 0.3s ease; }
        .lb-row {
            display: grid; grid-template-columns: minmax(0, 1.3fr) minmax(0, 1fr) minmax(0, 0.8fr) minmax(0, 1fr);
            align-items: center; gap: 6px;
            padding: 5px 10px;
        }
        .lb-row > :not(:first-child) { text-align: right; white-space: nowrap; }
        .lb-head { padding: 0 18px 4px; font-size: 10px; color: var(--text-muted); }
        .lb-rows {
            flex: 1;
            display: flex; flex-direction: column; gap: 3px;
            padding: 0 8px 8px;
            overflow-y: auto; overscroll-behavior: contain;
        }
        .lb-rows .lb-row {
            min-height: 30px;
            background: rgba(0,0,0,0.22);
            border: 1px solid transparent; border-radius: 8px;
        }
        .lb-rows .lb-row.is-won { background: rgba(34,197,94,0.14); border-color: rgba(34,197,94,0.3); }
        .lb-rows .lb-row.is-lost { opacity: 0.5; }
        .lb-rows .lb-row.is-mine { border-color: rgba(var(--hud-rgb),0.75); }
        .lb-rows .lb-row.flash { animation: lb-flash 0.8s ease-out; }
        @keyframes lb-flash { from { background: rgba(34,197,94,0.5); } }
        .lb-player { display: flex; align-items: center; gap: 6px; }
        .lb-player i {
            flex: 0 0 auto;
            width: 18px; height: 18px;
            display: inline-flex; align-items: center; justify-content: center;
            border-radius: 50%;
            background: hsl(var(--h, 200) 55% 42%);
            color: #fff; font-size: 10px; font-style: normal; font-weight: 700;
        }
        .lb-player span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .lb-x .x-chip { padding: 2px 6px; font-size: 11px; }
        .lb-win { color: var(--good); font-weight: 700; }
        .lb-note { padding: 14px 8px; text-align: center; font-size: 11px; color: var(--text-muted); }

        /* Where the panel isn't docked: the round's bets as circles down the right edge,
           below the buttons and above the multiplier row. A circle that cashes out steps
           inward as its multiplier grows in beside it; those that didn't grey out at the
           crash. --y is each circle's place, so a reorder glides. */
        .lb-mini {
            position: fixed; z-index: 34;
            top: 64px; right: 11px;
            bottom: calc(var(--hud-room, 220px) + 10px);
            width: 28px;
            pointer-events: none;
        }
        .lb-mini[hidden] { display: none; }
        @media (max-width: 640px) {
            .lb-mini { top: 192px; } /* below the stacked buttons */
        }
        .lb-dot {
            position: absolute; top: 0; right: 0;
            display: flex; align-items: center; gap: 4px;
            height: 28px;
            transform: translateY(var(--y, 0px));
            transition: transform 0.45s cubic-bezier(.2,.8,.2,1), opacity 0.4s ease, filter 0.4s ease;
            pointer-events: auto;
            cursor: pointer;
        }
        .lb-dot i {
            flex: 0 0 auto;
            width: 28px; height: 28px;
            display: inline-flex; align-items: center; justify-content: center;
            border-radius: 50%;
            background: hsl(var(--h, 200) 55% 42%);
            box-shadow: 0 0 0 2px rgba(255,255,255,0.75), 0 2px 6px rgba(0,0,0,0.45);
            color: #fff; font-size: 12px; font-style: normal; font-weight: 700;
            transition: box-shadow 0.3s ease;
        }
        .lb-dot b {
            max-width: 0; height: 18px; padding: 0;
            overflow: hidden; opacity: 0;
            background: rgba(22,163,74,0.92);
            border-radius: 999px;
            color: #fff; font-size: 10.5px; line-height: 18px; font-weight: 700; white-space: nowrap;
            box-shadow: 0 2px 6px rgba(0,0,0,0.4);
            transition: max-width 0.45s cubic-bezier(.2,.8,.2,1), padding 0.45s cubic-bezier(.2,.8,.2,1), opacity 0.3s ease;
        }
        .lb-dot.is-out b { max-width: 60px; padding: 0 6px; opacity: 1; }
        .lb-dot.is-out i { box-shadow: 0 0 0 2px var(--good), 0 0 12px rgba(34,197,94,0.7); }
        .lb-dot.is-mine i { box-shadow: 0 0 0 2px var(--hud), 0 0 12px rgba(var(--hud-rgb),0.6); }
        .lb-dot.is-lost { opacity: 0.4; filter: grayscale(1); }
        .lb-dot.is-new { animation: lb-dot-in 0.4s cubic-bezier(.34,1.56,.64,1); }
        .lb-dot.is-gone { opacity: 0; transform: translateY(var(--y, 0px)) scale(0.5); }
        @keyframes lb-dot-in { from { opacity: 0; transform: translateY(var(--y, 0px)) scale(0); } }
        .lb-mini-more i { background: rgba(var(--panel-rgb),0.85); color: var(--hud-bright); font-size: 10px; }
        .lb-mini-more[hidden] { display: none; }

        /* ---------- Round details and My bets: one dialog over the game ---------- */
        .sheet {
            position: fixed; inset: 0; z-index: 60;
            display: flex; align-items: center; justify-content: center;
            padding: 16px;
            background: rgba(0,0,0,0.55);
            animation: sheet-fade 0.16s ease-out;
        }
        .sheet[hidden] { display: none; }
        @keyframes sheet-fade { from { opacity: 0; } }
        .sheet-card {
            display: flex; flex-direction: column;
            width: min(600px, 100%);
            max-height: 100%;
            background: rgba(var(--panel-rgb),0.97);
            border: 1px solid rgba(var(--hud-rgb),0.32);
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.6), 0 0 24px rgba(var(--hud-rgb),0.12);
            overflow: hidden;
            animation: theme-menu-in 0.18s ease-out;
        }
        .sheet-head {
            display: flex; align-items: center; gap: 10px;
            min-height: 52px;
            padding: 9px 10px 9px 16px;
            border-bottom: 1px solid rgba(var(--hud-rgb),0.18);
        }
        .sheet-head .sheet-back:not([hidden]) + .sheet-title { margin-left: -6px; }
        .sheet-title {
            margin: 0;
            font-size: 14px; font-weight: 700; letter-spacing: 0.14em; text-transform: uppercase;
            color: var(--hud-bright);
            white-space: nowrap;
        }
        .sheet-time { font-size: 12px; color: var(--text-muted); white-space: nowrap; }
        .sheet-btn {
            flex: 0 0 auto;
            width: 32px; height: 32px;
            display: inline-flex; align-items: center; justify-content: center;
            background: transparent; border: 0; border-radius: 6px;
            color: var(--text-muted);
            cursor: pointer;
        }
        .sheet-btn:hover { color: var(--hud-bright); background: rgba(var(--hud-rgb),0.12); }
        .sheet-btn:focus-visible { outline: 2px solid var(--hud); outline-offset: -2px; }
        .sheet-btn svg { width: 16px; height: 16px; display: block; pointer-events: none; }
        .sheet-back { margin-left: -8px; }
        .sheet-close { margin-left: auto; }
        .sheet-body {
            padding: 16px;
            overflow-y: auto; overscroll-behavior: contain;
            font-size: 13px;
        }
        .sheet-note { margin: 18px 0; text-align: center; color: var(--text-muted); }
        .sheet-note .sheet-retry {
            margin-left: 8px; padding: 4px 10px;
            background: transparent; border: 1px solid rgba(var(--hud-rgb),0.4); border-radius: 6px;
            color: var(--hud); font: inherit; cursor: pointer;
        }
        .x-chip {
            display: inline-block;
            padding: 3px 9px;
            border-radius: 999px;
            font-size: 12px; font-weight: 700; letter-spacing: 0.04em;
            white-space: nowrap;
        }

        /* Round details: each input to the result, then the result worked out from them. */
        .pf-section {
            display: grid; grid-template-columns: 26px 1fr; align-items: center;
            column-gap: 10px;
            margin-bottom: 16px;
        }
        .pf-section > svg { grid-row: span 2; width: 24px; height: 24px; color: var(--hud); }
        .pf-label { font-weight: 700; }
        .pf-sub { font-size: 11px; color: var(--text-muted); }
        .pf-value {
            grid-column: 1 / -1;
            margin-top: 8px;
            padding: 10px 12px;
            background: rgba(0,0,0,0.3);
            border: 1px solid rgba(var(--hud-rgb),0.12);
            border-radius: 8px;
            font-size: 12px; line-height: 1.5;
            overflow-wrap: anywhere;
            user-select: text;
        }
        .pf-value.is-pending { color: var(--text-muted); font-style: italic; user-select: none; }
        .pf-value mark { background: rgba(var(--hud-rgb),0.25); color: var(--hud-bright); border-radius: 3px; }
        .pf-calc {
            /* The decimal is 16 digits: it gets the room. */
            display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1.35fr) minmax(0, 0.75fr);
            margin: 0; padding: 10px 12px;
            background: rgba(0,0,0,0.3);
            border: 1px solid rgba(var(--hud-rgb),0.12);
            border-radius: 8px;
            text-align: center;
        }
        .pf-calc dt { font-size: 11px; color: var(--text-muted); }
        .pf-calc dd { margin: 5px 0 0; font-weight: 700; overflow-wrap: anywhere; user-select: text; }
        .pf-calc > div + div { border-left: 1px solid rgba(var(--hud-rgb),0.12); padding-left: 8px; }
        .pf-edge { margin: 10px 2px 0; font-size: 11px; color: var(--text-muted); }
        .pf-check { margin: 12px 2px 0; font-size: 12px; }
        .pf-check.ok { color: var(--good); }
        .pf-check.bad { color: var(--warn); }
        .pf-how {
            margin-top: 16px; padding-top: 12px;
            border-top: 1px solid rgba(var(--hud-rgb),0.18);
            font-size: 12px; color: var(--text-muted);
        }
        .pf-how summary { cursor: pointer; color: var(--hud); }
        .pf-how summary:focus-visible { outline: 2px solid var(--hud); outline-offset: 2px; }
        .pf-how ol { margin: 10px 0 2px; padding-left: 18px; line-height: 1.6; }
        .pf-how li + li { margin-top: 4px; }

        /* My bets: one row a bet, newest first; a row opens its round. */
        .bets-list { display: flex; flex-direction: column; gap: 4px; }
        .bets-row {
            display: grid; grid-template-columns: 1.25fr 1fr 1fr 1fr 14px;
            align-items: center; gap: 8px;
            padding: 9px 10px;
            font-size: 12.5px;
            text-align: right;
        }
        .bets-row > :first-child { text-align: left; }
        .bets-row.is-head { padding-top: 0; padding-bottom: 4px; font-size: 11px; color: var(--text-muted); }
        button.bets-row {
            width: 100%;
            background: rgba(0,0,0,0.22);
            border: 1px solid transparent; border-radius: 8px;
            color: var(--text); font-family: inherit;
            cursor: pointer;
        }
        button.bets-row:hover { border-color: rgba(var(--hud-rgb),0.35); background: rgba(var(--hud-rgb),0.08); }
        button.bets-row:focus-visible { outline: 2px solid var(--hud); outline-offset: -2px; }
        button.bets-row:disabled { cursor: default; }
        .bets-row.is-won { background: rgba(34,197,94,0.10); }
        .bets-row .bets-when small { display: block; font-size: 10px; color: var(--text-muted); }
        .bets-row .is-muted { color: var(--text-muted); }
        .bets-row .is-win { color: var(--good); font-weight: 700; }
        .bets-row svg { width: 12px; height: 12px; color: var(--text-muted); }
        @media (max-width: 500px) {
            .sheet { padding: 10px; }
            .sheet-head { padding-left: 12px; gap: 8px; }
            .sheet-title { font-size: 13px; letter-spacing: 0.1em; }
            .sheet-body { padding: 12px; }
            .bets-row { gap: 6px; padding: 8px; font-size: 12px; grid-template-columns: 1.2fr 1fr 1fr 1fr 10px; }
            .pf-calc dd { font-size: 12px; }
        }
        /* ---------- Operator HUD bar (slim — only when /crash is iframe-embedded) ---------- */
        .operator-panel { display: none; }
        body.embedded { --op-bar-h: 52px; }
        body.embedded .operator-panel {
            display: flex;
            align-items: center;
            gap: 12px;
            position: fixed;
            left: 0; right: 0; bottom: 0;
            height: var(--op-bar-h);
            z-index: 30;
            padding: 0 14px;
            background: rgba(var(--panel-rgb),0.88);
            border-top: 1px solid rgba(var(--hud-rgb),0.28);
            box-shadow: 0 -1px 14px rgba(var(--hud-rgb),0.06);
            backdrop-filter: blur(8px);
            color: var(--text);
            font-family: ui-monospace, "SF Mono", Menlo, monospace;
            font-size: 12px;
            white-space: nowrap;
        }
        body.embedded .operator-panel::before {
            content: '';
            position: absolute;
            left: 14px; right: 14px; top: -1px; height: 1px;
            background: linear-gradient(90deg, transparent, var(--hud) 50%, transparent);
            opacity: 0.5;
        }

        /* Player identity (compact inline) */
        body.embedded .op-id {
            display: flex; align-items: center; gap: 8px;
            font-variant-numeric: tabular-nums;
        }
        body.embedded .op-id .op-name {
            font-weight: 700; letter-spacing: 0.04em;
            color: var(--text);
        }
        body.embedded .op-id .op-balance {
            color: var(--hud);
            font-weight: 700;
            text-shadow: 0 0 10px rgba(var(--hud-rgb),0.35);
        }
        body.embedded .op-sep {
            width: 1px; height: 22px;
            background: rgba(var(--hud-rgb),0.18);
            flex-shrink: 0;
        }

        /* Phase chip */
        body.embedded .op-phase {
            display: inline-flex; align-items: center;
            gap: 5px; padding: 2px 8px;
            font-size: 9px; letter-spacing: 0.24em; text-transform: uppercase;
            border: 1px solid currentColor;
            border-radius: 999px;
            color: var(--hud); opacity: 0.85;
        }
        body.embedded .op-phase::before {
            content: ''; width: 5px; height: 5px; border-radius: 50%;
            background: currentColor;
            box-shadow: 0 0 5px currentColor;
        }
        body.embedded .op-phase[data-phase="betting"] { color: var(--gold); }
        body.embedded .op-phase[data-phase="running"] { color: var(--good); }
        body.embedded .op-phase[data-phase="crashed"] { color: var(--warn); }

        /* Bet controls — inline, tight */
        body.embedded .op-bet-row {
            display: flex; align-items: center; gap: 6px;
        }
        body.embedded .op-input {
            background: rgba(var(--panel-rgb),0.6);
            color: var(--text);
            border: 1px solid rgba(var(--hud-rgb),0.28);
            border-radius: 4px;
            padding: 5px 8px;
            font-family: inherit;
            font-size: 12px; font-weight: 700;
            font-variant-numeric: tabular-nums;
            transition: border-color 0.15s, box-shadow 0.15s;
        }
        body.embedded .op-input[data-kind="stake"] { width: 64px; text-align: center; }
        body.embedded .op-input[data-kind="auto"]  { width: 60px; }

        /* Stake stepper (− input +) */
        body.embedded .op-stepper {
            display: inline-flex; align-items: stretch;
            border: 1px solid rgba(var(--hud-rgb),0.28);
            border-radius: 4px;
            overflow: hidden;
            transition: border-color 0.15s, box-shadow 0.15s;
        }
        body.embedded .op-stepper:focus-within {
            border-color: var(--hud);
            box-shadow: 0 0 0 1px var(--hud);
        }
        body.embedded .op-stepper .op-input {
            border: 0; border-radius: 0;
            background: rgba(var(--panel-rgb),0.6);
        }
        body.embedded .op-stepper .op-input:focus { box-shadow: none; }
        body.embedded .op-step {
            background: rgba(var(--panel-rgb),0.6);
            color: var(--hud);
            border: 0;
            padding: 0 10px;
            font-family: inherit; font-weight: 700; font-size: 14px;
            line-height: 1;
            cursor: pointer;
            transition: background 0.15s, color 0.15s;
            user-select: none;
        }
        body.embedded .op-step:hover  { background: rgba(var(--hud-rgb),0.18); color: var(--hud-bright); }
        body.embedded .op-step:active { background: rgba(var(--hud-rgb),0.30); }

        /* Clear (×) — sits inside the auto stepper to reset the field to empty. */
        body.embedded .op-clear {
            background: rgba(var(--panel-rgb),0.6);
            color: var(--text-muted);
            border: 0;
            border-left: 1px solid rgba(var(--hud-rgb),0.18);
            padding: 0 9px;
            font-family: inherit; font-weight: 700; font-size: 12px;
            line-height: 1;
            cursor: pointer;
            transition: background 0.15s, color 0.15s;
            user-select: none;
        }
        body.embedded .op-clear:hover  { background: rgba(239,68,68,0.18); color: #fca5a5; }
        body.embedded .op-clear:active { background: rgba(239,68,68,0.30); }
        body.embedded .op-stepper-auto .op-input { width: 56px; text-align: center; }
        body.embedded .op-input:focus {
            outline: none;
            border-color: var(--hud);
            box-shadow: 0 0 0 1px var(--hud);
        }

        body.embedded .op-btn {
            position: relative;
            display: inline-flex; align-items: center; justify-content: center;
            font-family: inherit; font-weight: 700; font-size: 11px;
            letter-spacing: 0.22em; text-transform: uppercase;
            height: 30px; padding: 0 14px;
            border: 1px solid currentColor;
            border-radius: 4px;
            background: transparent;
            cursor: pointer;
            transition: box-shadow 0.18s, opacity 0.18s;
            overflow: hidden;
        }
        body.embedded .op-btn::before {
            content: ''; position: absolute; inset: 0;
            background: currentColor; opacity: 0.08;
            transition: opacity 0.18s;
        }
        body.embedded .op-btn:hover:not(:disabled)::before { opacity: 0.16; }
        body.embedded .op-btn:disabled {
            opacity: 0.25; cursor: not-allowed; filter: saturate(0.4);
        }
        body.embedded .op-bet-btn {
            color: var(--gold);
            box-shadow: 0 0 10px rgba(251,191,36,0.20);
        }
        body.embedded .op-bet-btn:hover:not(:disabled) {
            box-shadow: 0 0 18px rgba(251,191,36,0.5);
        }
        body.embedded .op-cashout-btn {
            color: var(--good);
            box-shadow: 0 0 12px rgba(34,197,94,0.25);
            min-width: 150px;
            height: 34px; font-size: 12px;
            white-space: nowrap;
            display: inline-flex; align-items: center; justify-content: center;
        }
        body.embedded .op-cashout-btn:hover:not(:disabled) {
            box-shadow: 0 0 22px rgba(34,197,94,0.7);
        }
        body.embedded .op-cashout-btn .op-cashout-x {
            margin-left: 8px;
            font-size: 10px;
            letter-spacing: 0.12em;
            opacity: 0.8;
            font-weight: 600;
        }
        body.embedded .op-cashout-btn.pulse {
            animation: op-cashout-pulse 1.2s ease-in-out infinite;
        }
        @keyframes op-cashout-pulse {
            0%, 100% { box-shadow: 0 0 12px rgba(34,197,94,0.25); }
            50%      { box-shadow: 0 0 28px rgba(34,197,94,0.85); }
        }

        /* AUTO BET toggle — pill that fires a bet on every betting_opened
           when armed. Off state is muted; on state is loud + pulsing so the
           player can't forget money is moving in the background. */
        body.embedded .op-autobet-btn {
            background: transparent;
            border: 1px solid rgba(148,163,184,0.35);
            color: rgba(148,163,184,0.9);
            box-shadow: none;
            font-size: 10px;
            letter-spacing: 0.18em;
            padding: 4px 10px;
            height: 28px;
            display: inline-flex; align-items: center; gap: 6px;
        }
        body.embedded .op-autobet-btn .op-autobet-dot {
            width: 6px; height: 6px;
            border-radius: 50%;
            background: currentColor;
            opacity: 0.35;
        }
        /* Desktop: show full "AUTO BET" label, hide the short variant. */
        body.embedded .op-autobet-btn .op-autobet-label-short { display: none; }
        body.embedded .op-autobet-btn[data-active="true"] {
            color: var(--gold);
            border-color: rgba(251,191,36,0.55);
            background: rgba(251,191,36,0.08);
            box-shadow: 0 0 12px rgba(251,191,36,0.3);
        }
        body.embedded .op-autobet-btn[data-active="true"] .op-autobet-dot {
            opacity: 1;
            box-shadow: 0 0 6px currentColor;
            animation: op-autobet-pulse 1.6s ease-in-out infinite;
        }
        @keyframes op-autobet-pulse {
            0%, 100% { opacity: 1;   transform: scale(1); }
            50%      { opacity: 0.6; transform: scale(1.35); }
        }

        /* Floating "+payout" toast that pops over the multiplier on a
           successful cashout, drifts upward, fades out. Removed after
           the animation ends via JS `animationend`. Lives on .stage so
           it inherits the in-game coordinate space. */
        .fly-win {
            position: absolute;
            left: 50%; top: 38%;
            transform: translate(-50%, 0);
            display: flex; flex-direction: column; align-items: center;
            gap: 4px;
            pointer-events: none;
            z-index: 15;
            font-family: "SF Mono", ui-monospace, monospace;
            font-weight: 700;
            color: var(--good);
            text-shadow: 0 0 32px rgba(34,197,94,0.85), 0 0 10px rgba(34,197,94,1);
            white-space: nowrap;
            will-change: transform, opacity;
            animation: fly-win 1.7s cubic-bezier(0.22, 0.61, 0.36, 1) forwards;
        }
        .fly-win-mult   { font-size: clamp(14px, 3.4vw, 22px); letter-spacing: 0.08em; opacity: 0.85; }
        .fly-win-amount { font-size: clamp(28px, 7vw,   52px); letter-spacing: -0.01em; }
        @keyframes fly-win {
            0%   { opacity: 0; transform: translate(-50%, 30px) scale(0.6); }
            12%  { opacity: 1; transform: translate(-50%, 0)    scale(1.18); }
            25%  { opacity: 1; transform: translate(-50%, -8px) scale(1); }
            100% { opacity: 0; transform: translate(-50%, -180px) scale(0.9); }
        }
        @media (max-width: 719px) {
            .fly-win-mult   { font-size: 14px; }
            .fly-win-amount { font-size: 30px; }
        }

        /* Right-side spacer + last result pill */
        body.embedded .op-spacer { flex: 1; }
        body.embedded .op-last {
            display: none;
            align-items: baseline; gap: 8px;
            padding: 4px 10px;
            background: rgba(34,197,94,0.10);
            border: 1px solid rgba(34,197,94,0.35);
            border-radius: 4px;
            font-variant-numeric: tabular-nums;
        }
        body.embedded .op-last.visible { display: inline-flex; }
        body.embedded .op-last.loss {
            background: rgba(239,68,68,0.10);
            border-color: rgba(239,68,68,0.35);
        }
        body.embedded .op-last-label {
            font-size: 8px; letter-spacing: 0.28em; text-transform: uppercase;
            color: var(--text-muted);
        }
        body.embedded .op-last-mult {
            font-size: 13px; font-weight: 700; color: var(--good);
        }
        body.embedded .op-last.loss .op-last-mult { color: #fca5a5; }
        body.embedded .op-last-payout {
            font-size: 12px; font-weight: 700; color: var(--gold);
        }

        /* Inline status (small, fades from view) */
        body.embedded .op-status {
            font-size: 10px;
            color: var(--text-muted);
            letter-spacing: 0.04em;
            max-width: 220px; overflow: hidden; text-overflow: ellipsis;
        }
        body.embedded .op-status.err { color: #fca5a5; }
        body.embedded .op-status.ok  { color: var(--good); }

        /* When holding a bet during a running round, hide the entire
           stake/auto stepper UI (not just the inputs — empty [− +] boxes
           look broken) and the Place Bet button. Auto-bet toggle stays
           visible so the player can flip it off without waiting for the
           next window. */
        /* Steppers keep their space (just hidden) so nothing to their right
           shifts; Place Bet / Cash Out swap in place inside .op-action. */
        body.embedded .op-slot[data-mode="cashout"] .op-stepper,
        body.embedded .op-slot[data-mode="cashout"] .op-bet-btn,
        body.embedded .op-slot[data-mode="place"]   .op-cashout-btn { visibility: hidden; }
        body.embedded .op-action { display: grid; }
        body.embedded .op-action > .op-btn { grid-area: 1 / 1; width: 100%; }
        body.embedded .op-action .op-bet-btn { height: 34px; font-size: 12px; min-width: 150px; }

        /* Bet slots: one row each, the second under the first (the bar grows for it:
           body.two-bets). + adds the second, − on it takes it away. */
        body.embedded .op-slots { display: flex; flex-direction: column; gap: 6px; flex-shrink: 0; }
        body.embedded .op-slot[hidden] { display: none; }
        /* The bets keep their room; the status line gives way (it truncates). */
        body.embedded .op-status { min-width: 0; flex: 0 1 auto; }
        /* With the second bet open, the first keeps its + button's room (invisible), so
           the rows line up. A class, not [hidden]: the page's base CSS makes that !important. */
        body.embedded .op-add-slot.is-placeholder { visibility: hidden; }
        body.embedded .op-slot-toggle {
            flex: 0 0 auto;
            width: 30px; height: 30px;
            display: inline-flex; align-items: center; justify-content: center;
            background: rgba(var(--panel-rgb),0.6);
            border: 1px solid rgba(var(--hud-rgb),0.28);
            border-radius: 50%;
            color: var(--hud);
            font-family: inherit; font-size: 16px; font-weight: 700; line-height: 1;
            cursor: pointer;
            transition: background 0.15s, color 0.15s, opacity 0.15s;
        }
        body.embedded .op-slot-toggle:hover:not(:disabled) { background: rgba(var(--hud-rgb),0.18); color: var(--hud-bright); }
        body.embedded .op-slot-toggle:disabled { opacity: 0.3; cursor: not-allowed; }
        body.embedded .op-slot-toggle[hidden] { display: none; }
        body.embedded.two-bets { --op-bar-h: 100px; }

        /* Hide legacy cashout button + nudge the in-game submarine HUD up. */
        body.embedded .cashout-wrap { display: none; }
        body.embedded .sub-wrap { --lift: calc(var(--op-bar-h) * 0.45); }
        body.embedded .sonar    { bottom: calc(110px + var(--op-bar-h)); }

        /* Mobile (<720px) — bar wraps into rows so 360-414px screens can
           still place a bet without horizontal overflow. Layout becomes:
             row 1: balance · phase · status
             row 2: stake stepper · auto stepper
             row 3: Place Bet · AUTO BET · Cash Out
           In cashout mode the inputs row collapses (display:none on inputs),
           so the bar effectively shrinks to 2 rows + a giant Cash Out CTA. */
        @media (max-width: 719px) {
            body.embedded { --op-bar-h: 112px; }
            body.embedded .operator-panel {
                flex-wrap: wrap;
                white-space: normal;
                height: auto;
                min-height: var(--op-bar-h);
                gap: 4px 6px;
                padding: 5px 8px 6px;
                font-size: 11px;
                align-items: center;
            }
            /* Embedded mobile: shrink the multiplier so it doesn't fight the
               bar for vertical space. 22vw → 16vw means ~58px on a 360-width
               viewport — still the dominant element. */
            body.embedded .multiplier { font-size: 16vw; }
            /* Bottom-align the gauges with the multiplier so they extend
               UPWARD from the multiplier's baseline, never below it. With
               `align-items: center` (the default on .center-row) the hull
               gauge — which is taller than the mobile-shrunk multiplier —
               extends BELOW the baseline and collides with the operator
               bar's top edge. flex-end pins all three items' bottoms to
               the same line so the bar can never overlap them. */
            body.embedded .center-row { align-items: flex-end; }
            /* Lift the whole multiplier+gauges block higher above the bar.
               The default 16px clearance is too tight on mobile — the bar's
               actual rendered height fluctuates with status text length /
               wrapping, and gauges sit ~46px tall on the multiplier's
               baseline. 36px gives the assembly clean breathing room. */
            body.embedded .center { bottom: calc(36px + var(--op-bar-h)); }
            /* Drop visual chrome that wastes mobile pixels. The `.visible`
               variant of op-last has higher specificity in the base rules,
               so match it explicitly here or it'll stay rendered. */
            body.embedded .op-sep,
            body.embedded .op-spacer,
            body.embedded .op-last,
            body.embedded .op-last.visible { display: none !important; }
            /* Swap to the short "AUTO" label so the toggle sits next to
               PLACE BET on the same row instead of wrapping below. */
            body.embedded .op-autobet-btn .op-autobet-label-full  { display: none; }
            body.embedded .op-autobet-btn .op-autobet-label-short { display: inline; }

            /* Force two-row layout: info chunks (id/phase/status) stay on
               row 1, the bet-row (which has flex-basis:100%) wraps to row 2.
               Source order has status AFTER bet-row, so without `order:` it
               would land on a third row. */
            body.embedded .op-id     { order: 0; }
            body.embedded .op-phase  { order: 0; }
            body.embedded .op-status { order: 0; }
            body.embedded .op-bet-row { order: 1; }

            body.embedded .op-id { gap: 8px; min-width: 0; flex-shrink: 1; }
            body.embedded .op-id .op-name {
                max-width: 96px;
                overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
                font-size: 12px;
            }
            body.embedded .op-id .op-balance {
                font-size: 15px;
                max-width: 140px;
                overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
            }
            body.embedded .op-phase { font-size: 9px; padding: 3px 8px; letter-spacing: 0.16em; flex-shrink: 0; margin-left: auto; }
            /* Status gets its own full-width line under the player row so the
               bet/cashout message reads in full instead of an ellipsis at 9px.
               --op-bar-h above accounts for this extra line. */
            body.embedded .op-status {
                flex: 1 0 100%;
                font-size: 11px;
                margin-left: 0;
                text-align: left;
                min-width: 0;
                max-width: none;
                overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
            }

            /* Bet row breaks onto its own line(s) and wraps internally. */
            body.embedded .op-bet-row {
                flex: 1 0 100%;
                flex-wrap: wrap;
                gap: 6px;
                justify-content: stretch;
            }

            /* Steppers share whatever row 2 has left after AUTO + PLACE BET, so
               the stake and the "auto ×" placeholder stay readable from 360px up. */
            body.embedded .op-stepper { flex: 1 1 0; min-width: 0; }
            body.embedded .op-stepper-auto { flex-grow: 1.5; } /* has the extra × button */
            body.embedded .op-stepper .op-input,
            body.embedded .op-stepper-auto .op-input { flex: 1 1 auto; width: 0; min-width: 34px; }
            body.embedded .op-input { font-size: 12px; padding: 4px 2px; text-align: center; }
            body.embedded .op-input::placeholder { font-size: 11px; letter-spacing: 0; }
            body.embedded .op-step  { padding: 0 5px; font-size: 13px; }
            body.embedded .op-clear { padding: 0 6px; font-size: 11px; }

            /* Tight bet row — stays on one line in place mode. */
            body.embedded .op-bet-row { gap: 5px; flex-wrap: nowrap; overflow: hidden; }
            body.embedded .op-btn { font-size: 10px; padding: 0 8px; height: 30px; min-width: 0; }
            body.embedded .op-action .op-bet-btn { height: 30px; font-size: 10px; min-width: 0; padding: 0 10px; }
            body.embedded .op-action { flex: 0 0 auto; min-width: 0; }
            body.embedded .op-slot[data-mode="cashout"] .op-action { flex: 1 1 auto; }
            /* Cash Out stretches over this spot in cashout mode anyway — don't let
               its wider label size the slot while PLACE BET is showing. */
            body.embedded .op-slot[data-mode="place"] .op-action .op-cashout-btn { display: none; }
            body.embedded .op-slot[data-mode="cashout"] .op-stepper { display: none; }
            body.embedded .op-autobet-btn  {
                flex: 0 0 auto;
                padding: 0 8px; height: 30px;
                font-size: 9px; letter-spacing: 0.1em; gap: 4px;
            }
            body.embedded .op-cashout-btn  {
                flex: 1 1 auto; min-width: 0; font-size: 14px;
                white-space: nowrap;
            }
            body.embedded .op-cashout-btn .op-cashout-x { font-size: 11px; margin-left: 6px; }

            /* Cashout mode: only AUTO BET + Cash Out left on row 2. Blow up
               the Cash Out CTA so the bar's reserved height doesn't leave
               dead space, AND so the player's primary action is the most
               thumbable target. */
            body.embedded .op-slot[data-mode="cashout"] .op-cashout-btn {
                height: 48px; font-size: 16px;
            }
            body.embedded .op-slot[data-mode="cashout"] .op-autobet-btn {
                height: 48px;
            }

            /* The slots take the bet row's place (row 3), one row each. With two, a
               slot's Cash Out stays 40px high so the bar's height holds. */
            body.embedded .op-slots { order: 1; flex: 1 0 100%; gap: 5px; }
            body.embedded .op-slots > .op-bet-row { flex: 0 0 auto; }
            body.embedded .op-slot-toggle { width: 28px; height: 30px; border-radius: 6px; }
            body.embedded.two-bets { --op-bar-h: 150px; }
            body.embedded.two-bets .op-slot[data-mode="cashout"] .op-cashout-btn,
            body.embedded.two-bets .op-slot[data-mode="cashout"] .op-autobet-btn { height: 40px; font-size: 14px; }
        }

        /* Desktop — scale the wager controls + cashout CTA ~20% larger. */
        @media (min-width: 720px) {
            body.embedded                                  { --op-bar-h: 62px; }
            body.embedded .operator-panel                  { font-size: 13px; gap: 14px; padding: 0 16px; }
            body.embedded .op-id .op-name                  { font-size: 15px; }
            body.embedded .op-id .op-balance               { font-size: 14px; }
            body.embedded .op-sep                          { height: 26px; }
            body.embedded .op-phase                        { font-size: 10px; padding: 3px 9px; }
            body.embedded .op-input                        { font-size: 14px; padding: 6px 10px; }
            body.embedded .op-input[data-kind="stake"]     { width: 76px; }
            body.embedded .op-input[data-kind="auto"]      { width: 72px; }
            body.embedded .op-stepper-auto .op-input       { width: 66px; }
            body.embedded .op-step                         { font-size: 17px; padding: 0 12px; }
            body.embedded .op-clear                        { font-size: 14px; padding: 0 11px; }
            body.embedded .op-btn                          { font-size: 13px; padding: 0 18px; height: 36px; }
            body.embedded .op-cashout-btn,
            body.embedded .op-action .op-bet-btn           { font-size: 14px; height: 40px; min-width: 180px; }
            body.embedded .op-cashout-btn .op-cashout-x    { font-size: 12px; }
            body.embedded .op-status                       { font-size: 11px; max-width: 260px; }
            body.embedded .op-last                         { padding: 5px 12px; }
            body.embedded .op-last-mult                    { font-size: 15px; }
            body.embedded .op-last-payout                  { font-size: 14px; }
            body.embedded .op-slot-toggle                  { width: 36px; height: 36px; font-size: 18px; }
            body.embedded.two-bets                         { --op-bar-h: 112px; }
        }

        /* Fullscreen mode — center the HUD as a floating pill, 10% larger.
           Triggered by either the native :fullscreen pseudo-class or the
           is-fullscreen class the parent flips on via postMessage. */
        :fullscreen .operator-panel,
        body.is-fullscreen .operator-panel {
            left: 50%;
            right: auto;
            bottom: 18px;
            width: max-content;
            max-width: calc(100vw - 48px);
            justify-content: center;
            border: 1px solid rgba(var(--hud-rgb),0.32);
            border-radius: 10px;
            box-shadow:
                0 8px 28px rgba(0,0,0,0.55),
                0 0 30px rgba(var(--hud-rgb),0.16);
            transform: translateX(-50%) scale(1.1);
            transform-origin: 50% 100%;
        }
        :fullscreen .operator-panel::before,
        body.is-fullscreen .operator-panel::before {
            /* The decorative top scan-line riding the viewport edge would clip
               on rounded corners — hide it once the bar is detached. */
            display: none;
        }

        /* ---------- Sonar ---------- */
        .sonar {
            position: absolute; bottom: 110px; left: 50%;
            transform: translateX(-50%);
            width: 220px; height: 4px;
            background: linear-gradient(90deg, transparent 0%, rgba(var(--hud-rgb),0.45) 50%, transparent 100%);
            opacity: 0.65;
            z-index: 11;
        }
        .sonar::after {
            content: '';
            position: absolute; top: -2px; left: 0;
            width: 8px; height: 8px; border-radius: 50%;
            background: var(--hud);
            box-shadow: 0 0 14px var(--hud);
            animation: sonar-blip 2.8s linear infinite;
        }
        @keyframes sonar-blip {
            0%   { left: 0; }
            100% { left: 100%; }
        }
        .critical .sonar::after { animation-duration: 0.6s; background: var(--warn); box-shadow: 0 0 14px var(--warn); }

        /* ---------- Strobes (critical) ---------- */
        .strobe {
            position: absolute; inset: 0;
            background: radial-gradient(ellipse at 50% 50%, rgba(239,68,68,0.45) 0%, transparent 60%);
            opacity: 0; pointer-events: none;
            z-index: 6;
        }
        .critical .strobe { animation: strobe 0.4s linear infinite; }
        @keyframes strobe {
            0%, 100% { opacity: 0.0; }
            50%      { opacity: 0.8; }
        }

        /* ===================================================
           CRASH / IMPLODE SEQUENCE (multi-layer)
           Layer order from back to front:
             .scene → .strobe → .shockwave → .crash-flash →
             .crash-debris → .crash-cracks → .crash-banner
           =================================================== */

        /* (1) Hard white flash — punches first, fades fast */
        .crash-flash {
            position: absolute; inset: 0;
            background: white;
            opacity: 0;
            pointer-events: none;
            z-index: 48;
        }
        .crashed .crash-flash {
            animation: crash-flash 0.9s ease-out var(--boom-at) forwards;
        }
        /* The gothic sprite has its own burst glow — keep the screen flash a quick punch. */
        :root:is([data-theme="burton"], [data-theme="jackpot"]) .crashed .crash-flash {
            animation: crash-flash-soft 0.45s ease-out var(--boom-at) forwards;
        }
        @keyframes crash-flash-soft {
            0%   { opacity: 0; }
            12%  { opacity: 0.55; }
            100% { opacity: 0; }
        }
        @keyframes crash-flash {
            0%   { opacity: 0; }
            6%   { opacity: 1; }
            18%  { opacity: 0.9; }
            55%  { opacity: 0.25; }
            100% { opacity: 0; }
        }

        /* (2) Expanding shockwave ring */
        .shockwave {
            position: absolute; left: 50%; top: 50%;
            width: 60px; height: 60px;
            border-radius: 50%;
            border: 4px solid white;
            background: radial-gradient(circle, rgba(255,255,255,0.55) 0%, transparent 65%);
            transform: translate(-50%, -50%) scale(0);
            opacity: 0;
            pointer-events: none;
            z-index: 49;
            box-shadow: 0 0 60px white, inset 0 0 60px white;
        }
        .crashed .shockwave {
            animation: shockwave 1.1s cubic-bezier(0.2, 0.8, 0.4, 1) var(--boom-at) forwards;
        }
        @keyframes shockwave {
            0%   { transform: translate(-50%, -50%) scale(0); opacity: 0; border-width: 6px; }
            10%  { opacity: 1; }
            100% { transform: translate(-50%, -50%) scale(45); opacity: 0; border-width: 1px; }
        }

        /* (3) Debris — 24 fragments shooting outward */
        .crash-debris {
            position: absolute; left: 50%; top: 50%;
            width: 1px; height: 1px;
            pointer-events: none;
            z-index: 50;
        }
        .frag {
            position: absolute; left: 0; top: 0;
            width: 6px; height: 6px;
            background: linear-gradient(135deg, #fef3c7, #f59e0b 50%, #7f1d1d);
            border-radius: 1px;
            opacity: 0;
            transform: translate(-50%, -50%);
            box-shadow: 0 0 8px rgba(251,191,36,0.7);
        }
        .crashed .frag {
            animation: frag-fly 1.4s cubic-bezier(0.15, 0.85, 0.4, 1) calc(var(--boom-at) + var(--d, 0s)) forwards;
        }
        @keyframes frag-fly {
            0%   { opacity: 0; transform: translate(-50%, -50%) rotate(0); }
            8%   { opacity: 1; }
            100% {
                opacity: 0;
                transform: translate(calc(-50% + var(--dx, 200px)), calc(-50% + var(--dy, 200px))) rotate(720deg);
            }
        }

        /* (4) Glass crack overlay — SVG inline, masked over the dome */
        .crash-cracks {
            position: absolute; inset: 0;
            opacity: 0;
            pointer-events: none;
            z-index: 52;
        }
        .crashed .crash-cracks {
            animation: cracks-in 1.6s ease-out var(--boom-at) forwards;
        }
        @keyframes cracks-in {
            0%   { opacity: 0; transform: scale(0.4); }
            12%  { opacity: 0.95; transform: scale(1); }
            65%  { opacity: 0.7; }
            100% { opacity: 0; }
        }

        /* (5) HULL BREACH banner — slams in with a short glitch, holds, fades. */
        .crash-banner {
            position: absolute; left: 50%; top: 34%;
            display: flex; align-items: center; gap: clamp(10px, 2vw, 22px);
            transform: translate(-50%, 0);
            opacity: 0;
            pointer-events: none;
            white-space: nowrap;
            z-index: 53;
        }
        .crash-banner b {
            width: clamp(28px, 8vw, 110px); height: 3px;
            background: repeating-linear-gradient(-45deg, var(--warn) 0 6px, transparent 6px 10px);
            transform: scaleX(0);
        }
        .crash-banner b:first-child { transform-origin: right; }
        .crash-banner b:last-child  { transform-origin: left; }
        .crash-banner span {
            display: inline-block;
            font-family: "Palatino Linotype", "Book Antiqua", Georgia, serif;
            font-size: clamp(30px, 7vw, 72px); font-weight: 800; line-height: 1;
            letter-spacing: 0.12em; text-transform: uppercase;
            color: #ffe4e4;
            -webkit-text-stroke: 1px rgba(60,0,0,0.6);
            text-shadow: 0 3px 0 #450a0a, 0 0 24px rgba(239,68,68,0.9), 0 0 60px rgba(239,68,68,0.55);
        }
        .crashed .crash-banner { animation: banner-in 1.7s cubic-bezier(0.2, 0.9, 0.3, 1) var(--boom-at) forwards; }
        .crashed .crash-banner span { animation: banner-glitch 0.35s steps(1) calc(var(--boom-at) + 0.08s); }
        .crashed .crash-banner b { animation: banner-line 0.45s ease-out calc(var(--boom-at) + 0.12s) forwards; }
        @keyframes banner-in {
            0%   { opacity: 0; transform: translate(-50%, 0) scale(1.7); }
            10%  { opacity: 1; transform: translate(-50%, 0) scale(0.96); }
            18%  { transform: translate(-50%, 0) scale(1); }
            75%  { opacity: 1; transform: translate(-50%, 0) scale(1); }
            100% { opacity: 0; transform: translate(-50%, -10px) scale(1); }
        }
        @keyframes banner-glitch {
            0%  { transform: translate(-4px, 0); text-shadow: 4px 0 0 rgba(34,211,238,0.8), -4px 0 0 rgba(239,68,68,0.9); }
            25% { transform: translate(3px, 1px); text-shadow: -3px 0 0 rgba(34,211,238,0.8), 3px 0 0 rgba(239,68,68,0.9); }
            50% { transform: translate(-2px, -1px); }
            75% { transform: none; text-shadow: 2px 0 0 rgba(34,211,238,0.6), -2px 0 0 rgba(239,68,68,0.7); }
        }
        @keyframes banner-line { to { transform: scaleX(1); } }

        /* (6) Stage shake — strong then settle */
        .crashed .stage { animation: stage-rumble 0.9s linear var(--boom-at); }
        @keyframes stage-rumble {
            0%   { transform: translate(0,0); }
            10%  { transform: translate(-8px, 6px) rotate(-0.4deg); }
            20%  { transform: translate(7px, -5px) rotate(0.3deg); }
            30%  { transform: translate(-5px, 4px) rotate(-0.2deg); }
            45%  { transform: translate(4px, -3px); }
            60%  { transform: translate(-2px, 2px); }
            80%  { transform: translate(1px, -1px); }
            100% { transform: translate(0,0); }
        }

        /* (7) Submarine. Gothic skin: a 7-frame sprite (cracks → burst → hull torn
           in two → sinking halves) plays over the sub, tilted with it via the
           shared .sub-svg rotation. The screen flash, shockwave, debris and banner
           wait for the burst frame (--boom-at) so the sub visibly cracks first.
           `translate` (not transform) so the embed offset on .sub-wrap is kept. */
        :root { --boom-at: 0s; }
        :root[data-theme="burton"] { --boom-at: 0.17s; }
        :root[data-theme="jackpot"] { --boom-at: 0.45s; }
        .crashed .sub-wrap { animation: sub-collapse 1.7s ease-in forwards; }
        /* Jackpot's sprite runs longer (the wreck settles): fade the sub out later. */
        :root[data-theme="jackpot"] .crashed .sub-wrap { animation-duration: 2.6s; }
        @keyframes sub-collapse {
            0%, 55% { translate: 0 0; opacity: 1; }
            100%    { translate: 0 6vh; opacity: 0; }
        }
        .sub-boom { display: none; position: absolute; inset: 0; pointer-events: none; }
        /* Frame cell placed so frame 1's sub sits exactly on the in-game art. */
        .sub-boom span {
            position: absolute; left: -8.15%; top: -48.8%; width: 106%; height: 214.5%;
            background: url("{{ asset('images/sub-explosion.webp') }}") 0 0 / 700% 100% no-repeat;
            opacity: 0;
        }
        /* Jackpot's sprite: 36 frames in a 6x6 grid (the hull strains, cracks and crumples,
           the flash, a shockwave and a gas bubble round the torn hull, the burst of coins,
           bubbles and silt, then the wreck settling). */
        :root[data-theme="jackpot"] .sub-boom span {
            left: -4.83%; top: -39.17%; width: 97.79%; height: 160.54%;
            background-image: url("{{ asset('images/sub-explosion-jackpot.webp') }}?v={{ filemtime(public_path('images/sub-explosion-jackpot.webp')) }}");
            background-size: 600% 600%;
        }
        :root:is([data-theme="burton"], [data-theme="jackpot"]) .sub-boom { display: block; }
        :root[data-theme="burton"] .crashed .sub-gothic,
        :root[data-theme="jackpot"] .crashed .sub-jackpot { opacity: 0; }
        :root:is([data-theme="burton"], [data-theme="jackpot"]) .crashed .sub-boom span { opacity: 1; animation: sub-boom 1.25s step-end forwards; }
        @keyframes sub-boom {
            0%        { background-position-x: 0%; }
            5%        { background-position-x: 16.667%; }
            10%       { background-position-x: 33.333%; }
            14%       { background-position-x: 50%; }      /* burst — matches --boom-at */
            28%       { background-position-x: 66.667%; }
            44%       { background-position-x: 83.333%; }
            62%, 100% { background-position-x: 100%; }
        }
        /* Frame n (0-35) at column n % 6, row n / 6; the full flash (frame 12) at 0.45s = --boom-at. */
        :root[data-theme="jackpot"] .crashed .sub-boom span { animation: sub-boom-jackpot 2.07s step-end forwards; }
        @keyframes sub-boom-jackpot {
            0.00%          { background-position: 0.000% 0.000%; }
            2.66%          { background-position: 20.000% 0.000%; }
            4.35%          { background-position: 40.000% 0.000%; }
            6.04%          { background-position: 60.000% 0.000%; }
            7.73%          { background-position: 80.000% 0.000%; }
            9.42%          { background-position: 100.000% 0.000%; }
            11.11%         { background-position: 0.000% 20.000%; }
            12.80%         { background-position: 20.000% 20.000%; }
            14.49%         { background-position: 40.000% 20.000%; }
            16.18%         { background-position: 60.000% 20.000%; }
            17.87%         { background-position: 80.000% 20.000%; }
            19.81%         { background-position: 100.000% 20.000%; }
            21.74%         { background-position: 0.000% 40.000%; }   /* the flash: 0.45s */
            25.12%         { background-position: 20.000% 40.000%; }
            27.54%         { background-position: 40.000% 40.000%; }
            29.71%         { background-position: 60.000% 40.000%; }
            31.88%         { background-position: 80.000% 40.000%; }
            34.06%         { background-position: 100.000% 40.000%; }
            36.23%         { background-position: 0.000% 60.000%; }
            38.65%         { background-position: 20.000% 60.000%; }
            41.06%         { background-position: 40.000% 60.000%; }
            43.48%         { background-position: 60.000% 60.000%; }
            45.89%         { background-position: 80.000% 60.000%; }
            48.55%         { background-position: 100.000% 60.000%; }
            51.45%         { background-position: 0.000% 80.000%; }
            54.35%         { background-position: 20.000% 80.000%; }
            57.25%         { background-position: 40.000% 80.000%; }
            60.14%         { background-position: 60.000% 80.000%; }
            63.29%         { background-position: 80.000% 80.000%; }
            66.43%         { background-position: 100.000% 80.000%; }
            69.57%         { background-position: 0.000% 100.000%; }
            72.95%         { background-position: 20.000% 100.000%; }
            76.33%         { background-position: 40.000% 100.000%; }
            79.95%         { background-position: 60.000% 100.000%; }
            83.82%         { background-position: 80.000% 100.000%; }
            87.92%, 100%   { background-position: 100.000% 100.000%; }
        }

        /* ---------- Log ---------- */
        /* Debug-only activity feed: hidden for players, shown with ?debug=1. */
        .log {
            display: none;
            position: absolute; right: 12px; bottom: 12px;
            width: 220px; max-height: 110px; overflow: hidden;
            font-size: 9px; line-height: 1.5; color: var(--hud); opacity: 0.45;
            z-index: 10; pointer-events: none;
            text-align: right;
        }
        .log div { padding: 1px 0; }
        body.debug .log { display: block; }

        /* ---------- Mobile tighten ---------- */
        @media (max-width: 500px) {
            .multiplier { font-size: 22vw; }
            .gauge { padding: 8px 10px; }
            .gauge-value { font-size: 16px; }
            .hull-bar { width: 80px; }
            .cashout { padding: 16px 40px; font-size: 14px; }
            .sonar { width: 180px; bottom: 100px; }
        }
        /* ---------- Studio intro (config crash.intro) ----------
           Two modes: "frames" cross-fades the images in public/images/intro/,
           "scene" (no frames present) animates the game's own artwork. */
        .intro {
            position: fixed; inset: 0; z-index: 100;
            background: #000; cursor: pointer; overflow: hidden;
            transition: opacity var(--intro-fade, 600ms) ease;
        }
        .intro.done { opacity: 0; pointer-events: none; }
        .intro-frame {
            position: absolute; inset: 0; width: 100%; height: 100%;
            object-fit: cover; opacity: 0;
            transition: opacity var(--intro-fade, 400ms) ease;
        }
        .intro-frame.on { opacity: 1; }
        .intro-skip {
            position: absolute; right: 16px; bottom: 16px; z-index: 5;
            font-family: inherit; font-size: 12px; font-weight: 600; line-height: 1;
            letter-spacing: 0.12em; text-transform: uppercase;
            color: rgba(255, 255, 255, 0.55); background: rgba(0, 0, 0, 0.35);
            border: 1px solid rgba(255, 255, 255, 0.2); border-radius: 999px;
            padding: 8px 14px; cursor: pointer;
        }
        .intro-skip:hover { color: #fff; }

        /* Scene mode — a ~4.3 s sting from the game art. Every animation waits for
           .play (set once the art is decoded). Timeline: depth + sonar pings →
           sub rushes up, lantern flash → title slams in, shine → warp dive into the game. */
        .is-layer { position: absolute; inset: 0; pointer-events: none; }
        .is-stage { position: absolute; inset: 0; overflow: hidden; }
        .intro.play .is-stage { animation: is-shake 0.4s linear 1.1s, is-push 0.7s cubic-bezier(.6, 0, .9, .4) 3.6s forwards; }
        @keyframes is-shake {
            0%, 100% { transform: none; }
            20% { transform: translate(-8px, 5px); }
            40% { transform: translate(7px, -5px); }
            60% { transform: translate(-4px, 3px); }
            80% { transform: translate(2px, -2px); }
        }
        @keyframes is-push { to { transform: scale(1.5); filter: brightness(1.8) blur(3px); } }

        .is-bg {
            background: url("{{ asset('images/bg-gothic-landscape.jpg') }}") center / cover no-repeat;
            opacity: 0; transform: scale(1.3); filter: brightness(0.25);
        }
        @media (orientation: portrait) {
            .is-bg { background-image: url("{{ asset('images/bg-gothic-portrait.jpg') }}"); }
        }
        :root[data-theme="jackpot"] .is-bg { background-image: url("{{ asset('images/bg-jackpot-landscape.jpg') }}?v={{ filemtime(public_path('images/bg-jackpot-landscape.jpg')) }}"); }
        @media (orientation: portrait) {
            :root[data-theme="jackpot"] .is-bg { background-image: url("{{ asset('images/bg-jackpot-portrait.jpg') }}?v={{ filemtime(public_path('images/bg-jackpot-portrait.jpg')) }}"); }
        }
        .intro.play .is-bg { animation: is-fade-in 0.6s ease-out forwards, is-bg-drift 4.4s cubic-bezier(.2, .7, .3, 1) forwards; }
        @keyframes is-fade-in { to { opacity: 1; } }
        @keyframes is-bg-drift { to { transform: scale(1.05); filter: brightness(0.8); } }

        /* Deep-sea colour wash over the monochrome art. */
        .is-tint {
            background: radial-gradient(ellipse at 50% 0%, rgba(110, 210, 200, 0.45), transparent 65%),
                        linear-gradient(180deg, rgba(20, 70, 100, 0.5), rgba(6, 12, 30, 0.75));
            mix-blend-mode: color;
        }

        .is-rays {
            position: absolute; left: 50%; top: -10vh; width: 180vmax; height: 180vmax; pointer-events: none;
            background: repeating-conic-gradient(from 150deg at 50% 0%, rgba(225, 245, 210, 0.16) 0deg 1.5deg, transparent 1.5deg 4deg, rgba(225, 245, 210, 0.09) 4deg 7deg, transparent 7deg 13deg, rgba(225, 245, 210, 0.12) 13deg 14deg, transparent 14deg 19deg);
            -webkit-mask-image: radial-gradient(circle at 50% 0%, #000 0, transparent 45%);
            mask-image: radial-gradient(circle at 50% 0%, #000 0, transparent 45%);
            mix-blend-mode: screen; filter: blur(6px); opacity: 0; transform: translateX(-50%); transform-origin: 50% 0;
        }
        .intro.play .is-rays { animation: is-fade-in 0.8s ease-out 0.2s forwards, is-rays-sway 4s ease-in-out infinite alternate; }
        @keyframes is-rays-sway {
            from { transform: translateX(-50%) rotate(-4deg); }
            to   { transform: translateX(-50%) rotate(4deg); }
        }

        .is-motes i {
            position: absolute; left: var(--x); bottom: -10px; width: var(--s); height: var(--s);
            border-radius: 50%; background: rgba(236, 240, 210, 0.75);
            box-shadow: 0 0 6px rgba(214, 236, 120, 0.6); opacity: 0;
        }
        .intro.play .is-motes i { animation: is-mote var(--t) linear var(--d) infinite; }
        @keyframes is-mote {
            15% { opacity: 0.8; }
            85% { opacity: 0.6; }
            to  { opacity: 0; transform: translate(3vw, -110vh); }
        }

        .is-whale {
            position: absolute; top: 4%; left: 100%; width: min(40vw, 480px);
            opacity: 0; filter: blur(2px) brightness(0.35);
        }
        .intro.play .is-whale { animation: is-whale 6s linear 0.2s forwards; }
        @keyframes is-whale {
            10% { opacity: 0.3; }
            to { transform: translateX(calc(-100vw - 110%)); opacity: 0.3; }
        }

        .is-fish { position: absolute; left: -12%; width: min(7vw, 80px); filter: brightness(0.7); }
        .is-fish-1 { top: 76%; }
        .is-fish-2 { top: 42%; width: min(5vw, 56px); }
        .intro.play .is-fish-1 { animation: is-fish 1.6s cubic-bezier(.5, 0, .7, 1) 1.2s forwards; }
        .intro.play .is-fish-2 { animation: is-fish 2.2s ease-in 0.5s forwards; }
        @keyframes is-fish { to { transform: translate(125vw, -8vh); } }

        /* Sonar pings from where the sub surfaces; the last one fires with the lantern flash. */
        .is-pings, .is-sub-path { display: grid; place-items: center; padding-top: 22vh; }
        .is-pings i {
            grid-area: 1 / 1; width: 30vmin; height: 30vmin; border-radius: 50%;
            border: 2px solid rgba(214, 236, 120, 0.8);
            box-shadow: 0 0 24px rgba(214, 236, 120, 0.5), inset 0 0 24px rgba(214, 236, 120, 0.3);
            opacity: 0; transform: scale(0.1);
        }
        .intro.play .is-pings i { animation: is-ping 1.4s cubic-bezier(.1, .6, .3, 1) calc(0.15s + var(--n) * 0.28s) forwards; }
        @keyframes is-ping { 10% { opacity: 1; } to { opacity: 0; transform: scale(5); } }

        /* Submarine: rushes up from the depths with an overshoot, bobs, then dives into the game. */
        .is-sub-fly { opacity: 0; }
        .intro.play .is-sub-fly {
            animation: is-sub-in 1s cubic-bezier(.2, .9, .25, 1.25) 0.25s forwards,
                       is-sub-out 0.75s cubic-bezier(.55, 0, .85, .35) 3.6s forwards;
        }
        @keyframes is-sub-in {
            from { opacity: 0; transform: translateY(35vh) scale(0.05) rotate(-20deg); }
            30%  { opacity: 1; }
            to   { opacity: 1; transform: none; }
        }
        @keyframes is-sub-out {
            from { opacity: 1; transform: none; }
            to   { opacity: 1; transform: translate(10vw, 80vh) scale(1.6) rotate(35deg); }
        }
        .is-sub-bob { position: relative; width: min(46vw, 560px); }
        @media (orientation: portrait) { .is-sub-bob { width: min(80vw, 560px); } }
        .intro.play .is-sub-bob { animation: is-bob 1.6s ease-in-out 1.2s infinite alternate; }
        @keyframes is-bob { from { transform: translateY(-6px) rotate(-1.5deg); } to { transform: translateY(6px) rotate(1.5deg); } }
        /* Rises out of the dark: fades up from a dim silhouette, a soft glow coming up
           behind it. No CSS filter on the picture: animating one made Safari (every
           iPhone browser) draw the sub as a white box. */
        .is-sub { position: relative; display: block; width: 100%; height: auto; opacity: 0.2; }
        .intro.play .is-sub { animation: is-fade-in 0.8s ease-out 0.3s forwards; }
        .is-sub-bob::before {
            content: ''; position: absolute; inset: -8% -6%; opacity: 0;
            background: radial-gradient(closest-side, rgba(214, 236, 120, 0.25), transparent);
        }
        .intro.play .is-sub-bob::before { animation: is-fade-in 0.8s ease-out 0.3s forwards; }

        /* Headlight cone from the nose lamp, flickers on after the flash. */
        .is-beam {
            position: absolute; left: 93%; top: 52%; width: 110%; height: 90%; pointer-events: none;
            transform: translateY(-50%);
            clip-path: polygon(0 47%, 100% 0, 100% 100%, 0 53%);
            background: linear-gradient(90deg, rgba(230, 250, 170, 0.55), transparent 85%);
            mix-blend-mode: screen; filter: blur(6px); opacity: 0;
        }
        .intro.play .is-beam { animation: is-beam-on 0.6s steps(1) 1.25s forwards; }
        @keyframes is-beam-on { 0% { opacity: 0.8; } 20% { opacity: 0.1; } 40% { opacity: 0.9; } 55% { opacity: 0.3; } 70%, 100% { opacity: 1; } }

        /* Lantern: flares up with an anamorphic streak, then flickers. */
        .is-lantern {
            position: absolute; left: 75.5%; top: 15%; width: 26%; aspect-ratio: 1;
            transform: translate(-50%, -50%); border-radius: 50%;
            background: radial-gradient(circle, rgba(240, 252, 180, 1) 0 6%, rgba(214, 236, 120, 0.4) 22%, transparent 68%);
            mix-blend-mode: screen; opacity: 0;
        }
        .is-lantern::after {
            content: ''; position: absolute; left: 50%; top: 50%; width: 1200%; height: 3px;
            transform: translate(-50%, -50%) scaleX(0); opacity: 0;
            background: linear-gradient(90deg, transparent, rgba(235, 252, 180, 0.95), transparent);
        }
        .intro.play .is-lantern { animation: is-flare 0.7s ease-out 1.05s forwards, is-flicker 1.7s ease-in-out 1.8s infinite; }
        .intro.play .is-lantern::after { animation: is-streak 1s ease-out 1.05s forwards; }
        @keyframes is-flare {
            from { opacity: 0; transform: translate(-50%, -50%) scale(0.2); }
            35%  { opacity: 1; transform: translate(-50%, -50%) scale(3.2); }
            to   { opacity: 1; transform: translate(-50%, -50%) scale(1); }
        }
        @keyframes is-flicker { 0%, 100% { opacity: 1; } 45% { opacity: 0.78; } 55% { opacity: 0.95; } }
        @keyframes is-streak {
            30% { opacity: 1; transform: translate(-50%, -50%) scaleX(1); }
            to  { opacity: 0; transform: translate(-50%, -50%) scaleX(0.5); }
        }

        .is-flash {
            background: radial-gradient(circle at 50% 58%, rgba(245, 255, 210, 0.95), rgba(214, 236, 120, 0.3) 40%, transparent 75%);
            mix-blend-mode: screen; opacity: 0;
        }
        .intro.play .is-flash { animation: is-flash 0.5s ease-out 1.1s; }
        @keyframes is-flash { 15% { opacity: 1; } to { opacity: 0; } }

        /* Propeller trail + the burst of bubbles at the flash. */
        .is-bubbles { position: absolute; left: 9%; top: 72%; width: 0; height: 0; }
        .is-bubbles i, .is-burst i {
            position: absolute; width: var(--s, 8px); height: var(--s, 8px); border-radius: 50%;
            border: 1.5px solid rgba(236, 240, 216, 0.75);
            background: radial-gradient(circle at 30% 30%, rgba(255, 255, 255, 0.45), rgba(236, 230, 216, 0.05) 60%);
            opacity: 0;
        }
        .intro.play .is-bubbles i { animation: is-bubble var(--t, 1.6s) ease-out var(--d, 0s) infinite; }
        @keyframes is-bubble {
            0%   { opacity: 0; transform: translate(0, 0) scale(0.4); }
            15%  { opacity: 0.9; }
            100% { opacity: 0; transform: translate(var(--x, -60px), -110px) scale(1.2); }
        }
        .is-burst { position: absolute; left: 50%; top: 55%; width: 0; height: 0; }
        .is-burst i { margin: calc(var(--s) / -2); }
        .intro.play .is-burst i { animation: is-burst 1.1s cubic-bezier(.1, .7, .3, 1) calc(1.08s + var(--d)) forwards; }
        @keyframes is-burst {
            from { opacity: 0; transform: rotate(var(--a)) translateX(0) scale(0.3); }
            15%  { opacity: 0.95; }
            to   { opacity: 0; transform: rotate(var(--a)) translateX(var(--r)) scale(1.1); }
        }

        /* Title: letters slam in with a bounce, then a shine sweeps across. */
        .is-title {
            position: absolute; left: 0; right: 0; top: 12%; margin: 0;
            text-align: center; white-space: nowrap; line-height: 1;
            font-family: "Palatino Linotype", "Book Antiqua", Georgia, serif;
            font-weight: 700; font-size: clamp(48px, 12vw, 168px); letter-spacing: 0.02em;
            filter: drop-shadow(0 4px 0 #0b0910) drop-shadow(0 0 20px rgba(214, 236, 120, 0.5));
        }
        /* The gradient fills only a letter's box: pad it so descenders (the p) are
           painted too, and take the padding back so nothing moves. */
        .is-title span {
            display: inline-block; opacity: 0; color: transparent;
            padding: 0.1em 0 0.25em; margin: -0.1em 0 -0.25em;
            background: linear-gradient(100deg, #ece6d8 0%, #ece6d8 40%, #fffdf0 46%, #e8fb9a 50%, #fffdf0 54%, #ece6d8 60%, #c9c0ae 100%);
            background-size: 400% 100%; background-position: 100% 0;
            -webkit-background-clip: text; background-clip: text;
            -webkit-text-stroke: 1px rgba(11, 9, 16, 0.5);
        }
        .intro.play .is-title span {
            animation: is-letter 0.55s cubic-bezier(.3, 1.6, .5, 1) calc(1.3s + var(--i) * 55ms) forwards,
                       is-shine 0.9s ease-in-out calc(2.3s + var(--i) * 45ms) forwards;
        }
        @keyframes is-letter {
            from { opacity: 0; transform: translateY(-0.6em) scale(1.8) rotate(-8deg); filter: blur(8px); }
            40%  { opacity: 1; }
            to   { opacity: 1; transform: none; filter: blur(0); }
        }
        @keyframes is-shine { to { background-position: 0 0; } }
        .intro.play .is-title { animation: is-glow 0.6s ease-in-out 2.3s 4 alternate, is-out 0.5s ease-in 3.55s forwards; }
        @keyframes is-glow { to { filter: drop-shadow(0 4px 0 #0b0910) drop-shadow(0 0 40px rgba(214, 236, 120, 0.85)); } }
        @keyframes is-out { to { opacity: 0; transform: translateY(-30px) scale(1.15); filter: blur(6px); } }

        .is-tag {
            position: absolute; left: 0; right: 0; top: calc(12% + clamp(48px, 12vw, 168px) * 1.2 + 12px); margin: 0;
            display: flex; align-items: center; justify-content: center; gap: 14px;
            font: 600 clamp(11px, 1.5vw, 17px)/1 system-ui, sans-serif; letter-spacing: 0.5em;
            text-transform: uppercase; color: #d6ec78; text-shadow: 0 0 12px rgba(214, 236, 120, 0.6);
        }
        @media (orientation: portrait) {
            .is-title { top: 20%; font-size: clamp(48px, 17vw, 120px); }
            .is-tag { top: calc(20% + clamp(48px, 17vw, 120px) * 1.2 + 12px); }
        }
        .is-tag span { opacity: 0; margin-right: -0.5em; }
        .is-tag b { width: min(14vw, 140px); height: 1px; background: linear-gradient(90deg, transparent, #d6ec78); transform: scaleX(0); transform-origin: right; }
        .is-tag b:last-child { background: linear-gradient(270deg, transparent, #d6ec78); transform-origin: left; }
        .intro.play .is-tag span { animation: is-tag-in 0.6s ease-out 2s forwards; }
        .intro.play .is-tag b { animation: is-line 0.6s ease-out 2.1s forwards; }
        .intro.play .is-tag { animation: is-out 0.5s ease-in 3.55s forwards; }
        @keyframes is-tag-in { from { opacity: 0; letter-spacing: 1.2em; } to { opacity: 1; letter-spacing: 0.5em; } }
        @keyframes is-line { to { transform: scaleX(1); } }

        /* Warp lines as the sub dives into the game. */
        .is-speed {
            background: repeating-conic-gradient(from 0deg at 50% 60%, rgba(236, 240, 210, 0.25) 0 0.6deg, transparent 0.6deg 7deg);
            -webkit-mask-image: radial-gradient(circle at 50% 60%, transparent 15%, #000 60%);
            mask-image: radial-gradient(circle at 50% 60%, transparent 15%, #000 60%);
            opacity: 0;
        }
        .intro.play .is-speed { animation: is-speed 0.7s ease-in 3.6s forwards; }
        @keyframes is-speed { from { opacity: 0; transform: scale(1.4); } to { opacity: 0.9; transform: scale(1); } }

        .is-vignette { background: radial-gradient(ellipse at 50% 45%, transparent 35%, rgba(0, 0, 0, 0.85) 100%); }

        /* Jackpot Reef intro: the colourful art needs no deep-sea wash; a gold title. */
        :root[data-theme="jackpot"] .is-tint { opacity: 0.3; }
        :root[data-theme="jackpot"] .is-whale { width: min(20vw, 240px); }
        :root[data-theme="jackpot"] .is-beam,
        :root[data-theme="jackpot"] .is-lantern { display: none; }
        :root[data-theme="jackpot"] .is-title {
            font-family: "Arial Rounded MT Bold", "Trebuchet MS", "Segoe UI", system-ui, sans-serif; font-weight: 900;
            filter: drop-shadow(0 5px 0 #7c2d12) drop-shadow(0 0 22px rgba(252, 211, 77, 0.55));
        }
        :root[data-theme="jackpot"] .is-title span {
            background-image: linear-gradient(100deg, #fde68a 0%, #fcd34d 40%, #fffbe6 46%, #ffffff 50%, #fffbe6 54%, #fcd34d 60%, #d97706 100%);
            -webkit-text-stroke: 1px rgba(124, 45, 18, 0.6);
        }
        :root[data-theme="jackpot"] .intro.play .is-title { animation-name: is-glow-gold, is-out; }
        @keyframes is-glow-gold { to { filter: drop-shadow(0 5px 0 #7c2d12) drop-shadow(0 0 40px rgba(252, 211, 77, 0.9)); } }
        :root[data-theme="jackpot"] .is-tag { color: #fcd34d; text-shadow: 0 0 12px rgba(252, 211, 77, 0.6); }
        :root[data-theme="jackpot"] .is-tag b { background: linear-gradient(90deg, transparent, #fcd34d); }
        :root[data-theme="jackpot"] .is-tag b:last-child { background: linear-gradient(270deg, transparent, #fcd34d); }
        /* Gold instead of the gothic lime: sonar rings, motes, flash, a burst of coins. */
        :root[data-theme="jackpot"] .is-pings i {
            border-color: rgba(252, 211, 77, 0.85);
            box-shadow: 0 0 24px rgba(252, 211, 77, 0.5), inset 0 0 24px rgba(252, 211, 77, 0.3);
        }
        :root[data-theme="jackpot"] .is-motes i { background: rgba(255, 247, 214, 0.8); box-shadow: 0 0 6px rgba(252, 211, 77, 0.6); }
        :root[data-theme="jackpot"] .is-flash {
            background: radial-gradient(circle at 50% 58%, rgba(255, 251, 230, 0.95), rgba(252, 211, 77, 0.35) 40%, transparent 75%);
        }
        :root[data-theme="jackpot"] .is-burst i {
            border-color: rgba(180, 83, 9, 0.9);
            background: radial-gradient(circle at 35% 30%, #fffbe6 0 15%, #fcd34d 45%, #d97706 100%);
        }
        :root[data-theme="jackpot"] .is-sub-bob::before { background: radial-gradient(closest-side, rgba(252, 211, 77, 0.35), transparent); }
    </style>
</head>
<body>
    @if ($intro)
    <div id="intro" class="intro" hidden aria-hidden="true">
        @if ($intro['mode'] === 'scene')
        <div class="is-stage">
            <div class="is-layer is-bg"></div>
            <div class="is-layer is-tint"></div>
            <div class="is-rays"></div>
            <div class="is-layer is-motes">@for ($m = 0; $m < 18; $m++)<i style="--x: {{ ($m * 37) % 100 }}%; --s: {{ 2 + $m % 4 }}px; --t: {{ 2.2 + ($m % 5) * 0.45 }}s; --d: -{{ fmod($m * 0.61, 3) }}s"></i>@endfor</div>
            <img class="is-whale art-burton" src="{{ asset('images/whale-gothic.png') }}" alt="">
            <img class="is-whale art-jackpot" src="{{ asset('images/whale-jackpot.webp') }}?v={{ filemtime(public_path('images/whale-jackpot.webp')) }}" alt="">
            <img class="is-fish is-fish-1 art-burton" src="{{ asset('images/fish-gothic.png') }}" alt="">
            <img class="is-fish is-fish-1 art-jackpot" src="{{ asset('images/fish-jackpot.webp') }}" alt="">
            <img class="is-fish is-fish-2 art-burton" src="{{ asset('images/fish-puffer-gothic.png') }}" alt="">
            <img class="is-fish is-fish-2 art-jackpot" src="{{ asset('images/fish-puffer-jackpot.webp') }}" alt="">
            <div class="is-layer is-pings"><i style="--n: 0"></i><i style="--n: 1"></i><i style="--n: 2"></i><i style="--n: 3.3"></i></div>
            <div class="is-layer is-sub-path">
                <div class="is-sub-fly">
                    <div class="is-sub-bob">
                        <img class="is-sub art-burton" src="{{ asset('images/sub-gothic.png') }}" width="856" height="507" alt="">
                        <img class="is-sub art-jackpot" src="{{ asset('images/sub-jackpot.webp') }}" width="716" height="522" alt="">
                        <span class="is-beam"></span>
                        <div class="is-bubbles">
                            <i style="--s:9px;  --d:0s;    --t:1.5s; --x:-70px"></i>
                            <i style="--s:6px;  --d:0.25s; --t:1.3s; --x:-40px"></i>
                            <i style="--s:12px; --d:0.5s;  --t:1.8s; --x:-95px"></i>
                            <i style="--s:5px;  --d:0.7s;  --t:1.2s; --x:-30px"></i>
                            <i style="--s:8px;  --d:0.95s; --t:1.6s; --x:-60px"></i>
                            <i style="--s:10px; --d:1.2s;  --t:1.7s; --x:-85px"></i>
                        </div>
                        <div class="is-burst">@for ($m = 0; $m < 14; $m++)<i style="--a: {{ round($m * 360 / 14 + ($m % 3) * 9) }}deg; --r: {{ 18 + ($m * 7) % 16 }}vmin; --s: {{ 6 + ($m * 5) % 12 }}px; --d: {{ ($m % 4) * 40 }}ms"></i>@endfor</div>
                        <span class="is-lantern"></span>
                    </div>
                </div>
            </div>
            <div class="is-layer is-flash"></div>
            <h1 class="is-title" aria-label="{{ $intro['title'] }}">@foreach (mb_str_split($intro['title']) as $i => $ch)<span style="--i: {{ $i }}">{{ $ch }}</span>@endforeach</h1>
            @if ($intro['tagline'] !== '')
            <p class="is-tag"><b></b><span>{{ $intro['tagline'] }}</span><b></b></p>
            @endif
            <div class="is-layer is-speed"></div>
            <div class="is-layer is-vignette"></div>
        </div>
        @endif
        <button type="button" class="intro-skip">Skip</button>
    </div>
    <script>
        // Studio intro over the live game: either cross-fades the uploaded frames
        // or plays the built-in scene made from the game's own artwork. Never
        // blocks the game: a load error (or, if configured, a repeat visit in the
        // same session) just skips it. It plays whatever the device's
        // reduced-motion setting, like the rest of the game.
        (function studioIntro(cfg) {
            const $intro = document.getElementById('intro');
            const SEEN_KEY = 'orange.intro.seen';
            const SCENE_MS = 4250; // matches the CSS timeline (warp dive ends at ~4.3s)
            const seen = () => { try { return sessionStorage.getItem(SEEN_KEY) === '1'; } catch (_) { return false; } };
            const skipReason = (cfg.oncePerSession && seen()) ? 'already shown in this session' : null;
            if (skipReason) {
                console.info('[intro] skipped: ' + skipReason);
                $intro.remove();
                return;
            }

            const portrait = window.matchMedia('(orientation: portrait)').matches;
            const timers = [];
            let finished = false;
            function finish() {
                if (finished) return;
                finished = true;
                timers.forEach(clearTimeout);
                try { sessionStorage.setItem(SEEN_KEY, '1'); } catch (_) {}
                $intro.classList.add('done');
                setTimeout(() => $intro.remove(), cfg.fadeMs + 50);
            }

            const giveUp = (e) => { console.info('[intro] skipped: ' + (e && e.message === 'intro timeout' ? 'artwork not loaded within 5 s' : 'artwork failed to load')); finish(); };
            $intro.style.setProperty('--intro-fade', cfg.fadeMs + 'ms');
            $intro.hidden = false;
            $intro.addEventListener('click', finish);
            window.addEventListener('keydown', finish, { once: true });

            const decode = (img) => (img.decode ? img.decode() : Promise.resolve());
            // Give up after 5 s so a slow network never holds players at a black screen.
            const timeout = new Promise((_, reject) => setTimeout(() => reject(new Error('intro timeout')), 5000));

            if (cfg.mode === 'frames') {
                const urls = (portrait && cfg.portrait.length) ? cfg.portrait : (cfg.landscape.length ? cfg.landscape : cfg.portrait);
                const $skip = $intro.querySelector('.intro-skip');
                const imgs = urls.map((src) => {
                    const img = new Image();
                    img.alt = '';
                    img.className = 'intro-frame';
                    img.src = src;
                    $intro.insertBefore(img, $skip); // in order, so each new frame fades in on top
                    return img;
                });
                Promise.race([Promise.all(imgs.map(decode)), timeout]).then(() => {
                    if (finished) return;
                    let at = 0;
                    imgs.forEach((img, i) => {
                        timers.push(setTimeout(() => {
                            img.classList.add('on');
                            if (i > 0) timers.push(setTimeout(() => imgs[i - 1].classList.remove('on'), cfg.fadeMs));
                        }, at));
                        at += cfg.holdMs[i + 1] ?? cfg.frameMs;
                    });
                    timers.push(setTimeout(finish, at));
                }).catch(giveUp);
                return;
            }

            // Scene: wait for the artwork (incl. the CSS background) so nothing pops in mid-animation.
            const bg = new Image();
            const backdrop = cfg.bg[document.documentElement.getAttribute('data-theme')] ?? cfg.bg.burton;
            bg.src = portrait ? backdrop.portrait : backdrop.landscape;
            const art = [bg, ...[...$intro.querySelectorAll('img')].filter((img) => img.getClientRects().length)];
            Promise.race([Promise.allSettled(art.map(decode)), timeout]).then(() => {
                if (finished) return;
                $intro.classList.add('play');
                timers.push(setTimeout(finish, SCENE_MS));
            }).catch(giveUp);
        })(@json($intro));
    </script>
    @endif

    {{-- Top-right buttons, right to left (see .top-controls). --}}
    <div id="top-controls" class="top-controls">
        <button id="fs-btn" class="ctl-btn fs-btn" type="button" title="Toggle fullscreen" aria-label="Toggle fullscreen">
            <svg class="fs-icon-enter" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M2 6V2h4M14 6V2h-4M2 10v4h4M14 10v4h-4"/>
            </svg>
            <svg class="fs-icon-exit" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M6 2v4H2M10 2v4h4M6 14v-4H2M10 14v-4h4"/>
            </svg>
        </button>
        <button id="audio-btn" class="ctl-btn audio-btn" type="button" title="Toggle sound" aria-label="Toggle sound">
            <svg class="audio-icon-on" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M3 6h2l4-3v10L5 10H3V6Z"/>
                <path d="M11 6c0.8 0.6 1.2 1.4 1.2 2s-0.4 1.4-1.2 2"/>
                <path d="M12.6 4.4c1.4 0.9 2.2 2.2 2.2 3.6s-0.8 2.7-2.2 3.6"/>
            </svg>
            <svg class="audio-icon-mute" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M3 6h2l4-3v10L5 10H3V6Z"/>
                <path d="M11 6l4 4M15 6l-4 4"/>
            </svg>
        </button>
        <button id="theme-btn" class="ctl-btn theme-btn" type="button" title="Change theme" aria-label="Change theme"
                aria-haspopup="true" aria-expanded="false" aria-controls="theme-menu">
            <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M8 1.6C4.5 1.6 1.6 4.5 1.6 8s2.9 6.4 6.4 6.4c.7 0 1.2-.5 1.2-1.2 0-.3-.1-.6-.3-.8-.2-.2-.3-.5-.3-.8 0-.7.5-1.2 1.2-1.2h1.4c1.9 0 3.4-1.5 3.4-3.4C14.6 4.1 11.6 1.6 8 1.6Z"/>
                <circle cx="4.9" cy="7.2" r="0.9" fill="currentColor" stroke="none"/>
                <circle cx="6.9" cy="4.6" r="0.9" fill="currentColor" stroke="none"/>
                <circle cx="10.2" cy="4.8" r="0.9" fill="currentColor" stroke="none"/>
                <circle cx="12"  cy="7.6" r="0.9" fill="currentColor" stroke="none"/>
            </svg>
        </button>
        @if ($liveRounds ?? null)
        <button id="live-btn" class="ctl-btn live-btn" type="button" title="All bets" aria-label="All bets"
                aria-controls="live-bets" aria-expanded="false">
            <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <circle cx="6" cy="5.4" r="2.4"/>
                <path d="M1.8 13.4c.4-2.4 2.1-3.8 4.2-3.8s3.8 1.4 4.2 3.8"/>
                <path d="M10.6 3.2a2.3 2.3 0 0 1 0 4.4"/>
                <path d="M12 9.8c1.2.5 2 1.7 2.2 3.6"/>
            </svg>
        </button>
        @endif
        <button id="bets-btn" class="ctl-btn bets-btn" type="button" title="My bets" aria-label="My bets" aria-haspopup="dialog">
            <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M2.6 8a5.4 5.4 0 1 0 1.6-3.85"/>
                <path d="M2.4 2.4v2.8h2.8"/>
                <path d="M8 5v3.2l2.2 1.4"/>
            </svg>
        </button>
    </div>
    <div id="theme-menu" class="theme-menu" role="menu" aria-label="Theme" hidden>
        <div class="theme-menu-label">Theme</div>
        @foreach ($themes as $key => $t)
        <button type="button" class="theme-opt" role="menuitemradio" aria-checked="false" data-theme="{{ $key }}">
            <span class="theme-swatch" aria-hidden="true"
                  style="--sw-top: {{ $t['shallow'] }}; --sw-deep: {{ $t['depth2'] }}; --sw-hud: {{ $t['hud'] }};"></span>
            {{ $t['label'] }}
        </button>
        @endforeach
    </div>

    @if ($liveRounds ?? null)
    {{-- All bets: every player's bet in the round, live (config('crash.live_bets')). Filled in by the script. --}}
    <aside id="live-bets" class="live-bets" aria-label="All bets" hidden>
        <div class="lb-top">
            <div class="lb-tabs" role="tablist" aria-label="Round">
                <button type="button" class="lb-tab" role="tab" data-tab="current" aria-selected="true">All bets</button>
                <button type="button" class="lb-tab" role="tab" data-tab="previous" aria-selected="false">Previous</button>
            </div>
            <button id="live-close" class="sheet-btn" type="button" title="Close" aria-label="Close all bets">
                <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="M4 4l8 8M12 4l-8 8"/></svg>
            </button>
        </div>
        <div class="lb-summary">
            <div><b id="lb-count">0/0</b><small>cashed out</small></div>
            <div id="lb-result" class="lb-result" hidden></div>
            <div class="lb-total"><b id="lb-total">0.00</b><small id="lb-total-label">Total win</small></div>
        </div>
        <div class="lb-progress"><i id="lb-progress"></i></div>
        <div class="lb-row lb-head" aria-hidden="true"><span>Player</span><span id="lb-head-bet">Bet</span><span>X</span><span id="lb-head-win">Win</span></div>
        <div id="lb-rows" class="lb-rows" role="list"></div>
    </aside>
    {{-- Phones and narrow screens: the round's bets as circles down the right edge (a tap opens All bets). --}}
    <div id="lb-mini" class="lb-mini" aria-hidden="true" hidden><div id="lb-mini-more" class="lb-dot lb-mini-more" hidden><i></i></div></div>
    @endif

    {{-- Round details (a recent crash, or a bet's round) and My bets. Filled in by the script. --}}
    <div id="sheet" class="sheet" hidden>
        <section class="sheet-card" role="dialog" aria-modal="true" aria-labelledby="sheet-title">
            <header class="sheet-head">
                <button id="sheet-back" class="sheet-btn sheet-back" type="button" title="Back to my bets" aria-label="Back to my bets" hidden>
                    <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 3 5 8l5 5"/></svg>
                </button>
                <h2 id="sheet-title" class="sheet-title"></h2>
                <span id="sheet-pill" hidden></span>
                <span id="sheet-time" class="sheet-time"></span>
                <button id="sheet-close" class="sheet-btn sheet-close" type="button" title="Close" aria-label="Close">
                    <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="M4 4l8 8M12 4l-8 8"/></svg>
                </button>
            </header>
            <div id="sheet-body" class="sheet-body"></div>
        </section>
    </div>

    <div id="stage" class="stage waiting">
        <!-- Filtered scene (depth darkening lives here) -->
        <div class="scene">
            <div class="backdrop"><i class="seabed-fog"></i></div>
            <div class="walls" aria-hidden="true"><i class="wall wall-l"><b></b></i><i class="wall wall-r"><b></b></i></div>
            <div class="rays"></div>
            <div id="particles" class="particles"></div>
            <div id="bubbles-far" class="bubbles bubbles-far"></div>
            <div class="caustics"></div>
            <div id="bubbles"  class="bubbles"></div>
            <div id="bubbles-dive" class="bubbles bubbles-dive"></div>
            {{-- Sea life behind the sub, by kind: config('crash.sea_life'). --}}
            @if ($seaLife['fish'])
            <div class="fishes">
                <!-- Orange tropical fish, left → right -->
                <div class="fish" style="left:0; top:18%; --dur:18s; animation-delay:-3s;">
                    <svg width="46" height="26" viewBox="0 0 46 26" xmlns="http://www.w3.org/2000/svg">
                        <ellipse cx="22" cy="13" rx="14" ry="7" fill="#f97316"/>
                        <ellipse cx="22" cy="13" rx="14" ry="7" fill="none" stroke="#7c2d12" stroke-width="0.6"/>
                        <path d="M 8 13 L 1 6 L 1 20 Z" fill="#fb923c" stroke="#7c2d12" stroke-width="0.6"/>
                        <path d="M 22 6 Q 26 2 30 6 Z" fill="#fdba74"/>
                        <line x1="14" y1="9" x2="14" y2="17" stroke="#7c2d12" stroke-width="0.7"/>
                        <line x1="20" y1="9" x2="20" y2="17" stroke="#7c2d12" stroke-width="0.7"/>
                        <circle cx="34" cy="11" r="1.6" fill="#fff"/>
                        <circle cx="34.4" cy="11.2" r="0.9" fill="#0f172a"/>
                    </svg>
                    <img class="skin-art art-burton" src="{{ asset('images/fish-gothic.png') }}" width="69" height="39" alt="" aria-hidden="true" loading="lazy">
                    <img class="skin-art art-jackpot" src="{{ asset('images/fish-jackpot.webp') }}" width="54" height="50" alt="" aria-hidden="true" loading="lazy">
                </div>

                <!-- Yellow & black striped fish, right → left -->
                <div class="fish swim-reverse" style="right:0; top:32%; --dur:22s; animation-delay:-8s;">
                    <svg width="40" height="22" viewBox="0 0 40 22" xmlns="http://www.w3.org/2000/svg">
                        <ellipse cx="19" cy="11" rx="12" ry="6" fill="#fde047"/>
                        <ellipse cx="19" cy="11" rx="12" ry="6" fill="none" stroke="#713f12" stroke-width="0.5"/>
                        <path d="M 7 11 L 1 5 L 1 17 Z" fill="#facc15"/>
                        <rect x="13" y="5" width="2" height="12" fill="#1e293b"/>
                        <rect x="20" y="5" width="2" height="12" fill="#1e293b"/>
                        <rect x="27" y="6" width="2" height="10" fill="#1e293b"/>
                        <circle cx="29" cy="9" r="1.3" fill="#fff"/>
                        <circle cx="29.3" cy="9.2" r="0.7" fill="#0f172a"/>
                    </svg>
                    <img class="skin-art art-burton" src="{{ asset('images/fish-puffer-gothic.png') }}" width="50" height="39" alt="" aria-hidden="true" loading="lazy">
                    <img class="skin-art art-jackpot" src="{{ asset('images/fish-puffer-jackpot.webp') }}" width="47" height="41" alt="" aria-hidden="true" loading="lazy">
                </div>

                <!-- Pink fish, left → right -->
                <div class="fish" style="left:0; top:45%; --dur:26s; animation-delay:-15s;">
                    <svg width="38" height="22" viewBox="0 0 38 22" xmlns="http://www.w3.org/2000/svg">
                        <ellipse cx="18" cy="11" rx="11" ry="6" fill="#f472b6"/>
                        <ellipse cx="18" cy="11" rx="11" ry="6" fill="none" stroke="#831843" stroke-width="0.5"/>
                        <path d="M 7 11 L 1 5 L 1 17 Z" fill="#f9a8d4"/>
                        <path d="M 18 5 Q 22 1 26 5 Z" fill="#fbcfe8"/>
                        <circle cx="28" cy="9" r="1.4" fill="#fff"/>
                        <circle cx="28.3" cy="9.2" r="0.8" fill="#0f172a"/>
                    </svg>
                    <img class="skin-art art-burton" src="{{ asset('images/fish-angler-gothic.png') }}" width="61" height="36" alt="" aria-hidden="true" loading="lazy">
                    <img class="skin-art art-jackpot" src="{{ asset('images/fish-angler-jackpot.webp') }}" width="53" height="41" alt="" aria-hidden="true" loading="lazy">
                </div>

                <!-- Cyan fish, right → left, smaller -->
                <div class="fish swim-reverse" style="right:0; top:25%; --dur:16s; animation-delay:-1s;">
                    <svg width="32" height="18" viewBox="0 0 32 18" xmlns="http://www.w3.org/2000/svg">
                        <ellipse cx="15" cy="9" rx="9" ry="5" fill="#22d3ee"/>
                        <ellipse cx="15" cy="9" rx="9" ry="5" fill="none" stroke="#155e75" stroke-width="0.5"/>
                        <path d="M 6 9 L 1 4 L 1 14 Z" fill="#67e8f9"/>
                        <circle cx="22" cy="7.5" r="1.2" fill="#fff"/>
                        <circle cx="22.3" cy="7.7" r="0.7" fill="#0f172a"/>
                    </svg>
                    <img class="skin-art art-burton" src="{{ asset('images/fish-gothic.png') }}" width="48" height="27" alt="" aria-hidden="true" loading="lazy">
                    <img class="skin-art art-jackpot" src="{{ asset('images/fish-jackpot.webp') }}" width="37" height="35" alt="" aria-hidden="true" loading="lazy">
                </div>

                <!-- Purple fish, left → right -->
                <div class="fish" style="left:0; top:55%; --dur:32s; animation-delay:-20s;">
                    <svg width="42" height="24" viewBox="0 0 42 24" xmlns="http://www.w3.org/2000/svg">
                        <ellipse cx="20" cy="12" rx="13" ry="7" fill="#a855f7"/>
                        <ellipse cx="20" cy="12" rx="13" ry="7" fill="none" stroke="#581c87" stroke-width="0.5"/>
                        <path d="M 7 12 L 1 5 L 1 19 Z" fill="#c084fc"/>
                        <path d="M 20 5 Q 24 1 28 5 Z" fill="#d8b4fe"/>
                        <circle cx="31" cy="10" r="1.5" fill="#fff"/>
                        <circle cx="31.3" cy="10.2" r="0.8" fill="#0f172a"/>
                    </svg>
                    <img class="skin-art art-burton" src="{{ asset('images/fish-puffer-gothic.png') }}" width="52" height="40" alt="" aria-hidden="true" loading="lazy">
                    <img class="skin-art art-jackpot" src="{{ asset('images/fish-puffer-jackpot.webp') }}" width="49" height="42" alt="" aria-hidden="true" loading="lazy">
                </div>
            </div>
            @endif

            <div id="creatures" class="creatures">
                @if ($seaLife['creatures'])
                <!-- Whale silhouette, slow drift left → right -->
                <div class="creature whale cross-reverse" style="right:0; top:50%; --dur:58s; animation-delay:-15s;">
                    <svg width="340" height="120" viewBox="0 0 340 120" xmlns="http://www.w3.org/2000/svg">
                        <!-- Tail flukes (horizontal V at the back) -->
                        <path d="M 295 55
                                 Q 310 38 335 30
                                 Q 322 50 308 60
                                 Q 322 66 335 88
                                 Q 310 80 295 65 Z"
                              fill="rgba(2,6,23,0.95)" stroke="rgba(15,23,42,1)" stroke-width="0.7"/>

                        <!-- Main body -->
                        <path d="M 25 58
                                 Q 12 48 22 38
                                 Q 35 18 95 22
                                 Q 185 18 255 32
                                 Q 285 38 298 52
                                 L 298 66
                                 Q 285 78 255 84
                                 Q 185 96 95 92
                                 Q 35 100 22 78
                                 Q 12 70 25 58 Z"
                              fill="rgba(2,6,23,0.95)" stroke="rgba(15,23,42,1)" stroke-width="0.8"/>

                        <!-- Dorsal fin -->
                        <path d="M 185 22 Q 195 6 215 24 Q 205 22 195 24 Z"
                              fill="rgba(2,6,23,0.95)" stroke="rgba(15,23,42,1)" stroke-width="0.6"/>

                        <!-- Pectoral fin (under, midbody) -->
                        <path d="M 90 84 Q 100 110 135 100 Q 115 88 90 84 Z"
                              fill="rgba(2,6,23,0.85)" stroke="rgba(15,23,42,1)" stroke-width="0.5"/>

                        <!-- Mouth line -->
                        <path d="M 22 68 Q 42 74 68 70" stroke="rgba(15,23,42,0.9)" stroke-width="1.1" fill="none"/>

                        <!-- Belly seam (subtle texture) -->
                        <path d="M 60 88 Q 140 96 240 84" stroke="rgba(103,232,249,0.15)" stroke-width="1" fill="none"/>

                        <!-- Eye -->
                        <circle cx="58" cy="50" r="2.4" fill="white"/>
                        <circle cx="58.5" cy="50.4" r="1.3" fill="#0f172a"/>

                        <!-- Blowhole -->
                        <ellipse cx="92" cy="22" rx="3" ry="1.2" fill="rgba(15,23,42,0.7)"/>
                    </svg>
                    <img class="skin-art art-burton" src="{{ asset('images/whale-gothic.png') }}" width="340" height="106" alt="" aria-hidden="true">
                    <img class="skin-art art-jackpot" src="{{ asset('images/whale-jackpot.webp') }}?v={{ filemtime(public_path('images/whale-jackpot.webp')) }}" width="196" height="184" alt="" aria-hidden="true">
                </div>

                <!-- Anglerfish-style silhouette, right → left -->
                <div class="creature angler-deep cross-reverse" style="right:0; top:75%; --dur:56s; animation-delay:-30s;">
                    <svg width="120" height="55" viewBox="0 0 120 55" xmlns="http://www.w3.org/2000/svg">
                        <path d="M 10 30
                                 Q 25 10 60 16
                                 Q 95 22 110 30
                                 Q 95 38 65 42
                                 Q 30 45 18 38
                                 Q 5 36 10 30 Z"
                              fill="rgba(2,6,23,0.95)" stroke="rgba(15,23,42,1)" stroke-width="0.6"/>
                        <path d="M 105 28 Q 118 22 115 32 Q 118 35 110 35 Z" fill="rgba(2,6,23,0.9)"/>
                        <circle cx="115" cy="30" r="2" fill="rgba(252,211,77,1)" filter="url(#glow)"/>
                        <circle cx="115" cy="30" r="4" fill="rgba(252,211,77,0.4)"/>
                        <circle cx="115" cy="30" r="7" fill="rgba(252,211,77,0.18)"/>
                        <circle cx="22" cy="28" r="1" fill="rgba(186,230,253,0.7)"/>
                    </svg>
                    <span class="skin-art art-burton" aria-hidden="true">
                        <img src="{{ asset('images/angler-deep-gothic.png') }}" width="170" height="81" alt="" loading="lazy">
                        <i class="bulb-glow"></i>
                    </span>
                    <span class="skin-art art-jackpot" aria-hidden="true">
                        <img src="{{ asset('images/angler-deep-jackpot.webp') }}" width="130" height="106" alt="" loading="lazy">
                        <i class="bulb-glow"></i>
                    </span>
                </div>

                <!-- Eel-like serpent silhouette, slow weaving (top) -->
                <div class="creature cross-reverse" style="right:0; top:35%; --dur:70s; animation-delay:-40s;">
                    <svg width="280" height="40" viewBox="0 0 280 40" xmlns="http://www.w3.org/2000/svg">
                        <path d="M 5 20
                                 Q 40 5 80 20
                                 Q 120 35 160 20
                                 Q 200 5 240 20
                                 Q 260 26 270 22
                                 L 268 25
                                 Q 240 30 200 25
                                 Q 160 30 120 25
                                 Q 80 30 40 25
                                 Q 15 22 5 20 Z"
                              fill="rgba(2,6,23,0.92)" stroke="rgba(15,23,42,1)" stroke-width="0.5"/>
                        <circle cx="20" cy="20" r="1.2" fill="rgba(255,255,255,0.55)"/>
                    </svg>
                </div>
                @endif

                @if ($seaLife['octopi'])
                <!-- Octopus, slow drift right → left with swaying tentacles -->
                <div class="creature octopus cross-reverse" style="right:0; top:42%; --dur:64s; animation-delay:-25s;">
                    <svg width="220" height="200" viewBox="0 0 220 200" xmlns="http://www.w3.org/2000/svg">
                        <!-- Tentacles (animated sway via group) -->
                        <g class="tentacles">
                            <!-- 8 curling tentacles fanning out from the mantle -->
                            <path d="M 60 95 Q 35 130 30 165 Q 28 180 36 178 Q 32 170 38 160 Q 50 130 70 105 Z"
                                  fill="rgba(2,6,23,0.92)" stroke="rgba(15,23,42,1)" stroke-width="0.7"/>
                            <path d="M 78 100 Q 60 145 70 185 Q 78 188 76 180 Q 70 165 80 140 Q 88 115 90 105 Z"
                                  fill="rgba(2,6,23,0.92)" stroke="rgba(15,23,42,1)" stroke-width="0.7"/>
                            <path d="M 95 105 Q 88 155 100 195 Q 110 195 105 185 Q 96 165 105 130 Q 108 115 105 105 Z"
                                  fill="rgba(2,6,23,0.92)" stroke="rgba(15,23,42,1)" stroke-width="0.7"/>
                            <path d="M 115 105 Q 115 155 120 198 Q 128 196 124 188 Q 118 165 122 130 Q 122 115 122 105 Z"
                                  fill="rgba(2,6,23,0.94)" stroke="rgba(15,23,42,1)" stroke-width="0.7"/>
                            <path d="M 135 105 Q 142 155 138 195 Q 130 195 134 185 Q 142 165 134 130 Q 130 115 130 105 Z"
                                  fill="rgba(2,6,23,0.94)" stroke="rgba(15,23,42,1)" stroke-width="0.7"/>
                            <path d="M 152 100 Q 170 145 158 185 Q 150 188 152 178 Q 158 165 148 140 Q 142 115 142 105 Z"
                                  fill="rgba(2,6,23,0.92)" stroke="rgba(15,23,42,1)" stroke-width="0.7"/>
                            <path d="M 170 95 Q 195 130 195 165 Q 200 180 188 178 Q 192 170 188 160 Q 175 130 160 105 Z"
                                  fill="rgba(2,6,23,0.92)" stroke="rgba(15,23,42,1)" stroke-width="0.7"/>
                            <path d="M 50 85 Q 18 105 14 140 Q 12 155 22 152 Q 18 145 24 132 Q 38 110 60 95 Z"
                                  fill="rgba(2,6,23,0.9)" stroke="rgba(15,23,42,1)" stroke-width="0.7"/>
                        </g>

                        <!-- Mantle / head -->
                        <ellipse cx="110" cy="60" rx="62" ry="46" fill="rgba(2,6,23,0.96)" stroke="rgba(15,23,42,1)" stroke-width="0.9"/>

                        <!-- Eyes -->
                        <ellipse cx="88" cy="55" rx="9" ry="7" fill="white"/>
                        <ellipse cx="88" cy="55" rx="3.5" ry="6" fill="#0f172a"/>
                        <circle cx="89.5" cy="53" r="1.4" fill="white"/>
                        <ellipse cx="132" cy="55" rx="9" ry="7" fill="white"/>
                        <ellipse cx="132" cy="55" rx="3.5" ry="6" fill="#0f172a"/>
                        <circle cx="133.5" cy="53" r="1.4" fill="white"/>

                        <!-- Mantle highlight (texture) -->
                        <ellipse cx="100" cy="32" rx="32" ry="6" fill="rgba(255,255,255,0.10)"/>
                        <!-- Bioluminescent spots along mantle -->
                        <circle cx="60" cy="68" r="1.3" fill="rgba(186,230,253,0.65)"/>
                        <circle cx="160" cy="65" r="1.3" fill="rgba(186,230,253,0.65)"/>
                        <circle cx="155" cy="80" r="1" fill="rgba(186,230,253,0.5)"/>
                        <circle cx="65" cy="82" r="1" fill="rgba(186,230,253,0.5)"/>
                    </svg>
                    <img class="skin-art art-burton" src="{{ asset('images/octopus-gothic.png') }}" width="190" height="143" alt="" aria-hidden="true" loading="lazy">
                    <img class="skin-art art-jackpot" src="{{ asset('images/octopus-jackpot.webp') }}" width="162" height="168" alt="" aria-hidden="true" loading="lazy">
                </div>

                {{-- Mini octopi — five silhouettes swimming upward at random
                     horizontal lanes, with offset animation delays so they
                     never all leave the screen at once. --}}
                @foreach ([
                    ['left' => '12%', 'dur' => '16s', 'delay' => '-3s',  'size' => 56],
                    ['left' => '32%', 'dur' => '21s', 'delay' => '-12s', 'size' => 44],
                    ['left' => '54%', 'dur' => '14s', 'delay' => '-7s',  'size' => 62],
                    ['left' => '72%', 'dur' => '19s', 'delay' => '-15s', 'size' => 48],
                    ['left' => '88%', 'dur' => '23s', 'delay' => '-9s',  'size' => 40],
                ] as $o)
                <div class="creature octopus mini" style="left:{{ $o['left'] }}; top:0; --dur:{{ $o['dur'] }}; animation-delay:{{ $o['delay'] }};">
                    <svg width="{{ $o['size'] }}" height="{{ (int) ($o['size'] * 1.25) }}" viewBox="0 0 80 100" xmlns="http://www.w3.org/2000/svg">
                        <g class="tentacles">
                            <path d="M 18 38 Q  6 60 10 92" stroke="rgba(2,6,23,0.92)" stroke-width="4"   fill="none" stroke-linecap="round"/>
                            <path d="M 30 44 Q 26 70 28 96" stroke="rgba(2,6,23,0.92)" stroke-width="4"   fill="none" stroke-linecap="round"/>
                            <path d="M 42 44 Q 48 70 44 96" stroke="rgba(2,6,23,0.92)" stroke-width="4"   fill="none" stroke-linecap="round"/>
                            <path d="M 54 38 Q 68 60 62 92" stroke="rgba(2,6,23,0.92)" stroke-width="4"   fill="none" stroke-linecap="round"/>
                            <path d="M 10 30 Q -2 50  4 76" stroke="rgba(2,6,23,0.88)" stroke-width="3.5" fill="none" stroke-linecap="round"/>
                            <path d="M 62 30 Q 78 50 70 76" stroke="rgba(2,6,23,0.88)" stroke-width="3.5" fill="none" stroke-linecap="round"/>
                        </g>
                        <ellipse cx="36" cy="26" rx="26" ry="20" fill="rgba(2,6,23,0.96)" stroke="rgba(15,23,42,1)" stroke-width="0.5"/>
                        <ellipse cx="27" cy="24" rx="3.2" ry="2.6" fill="white"/>
                        <circle  cx="28" cy="23.5" r="1.2" fill="#0f172a"/>
                        <ellipse cx="45" cy="24" rx="3.2" ry="2.6" fill="white"/>
                        <circle  cx="46" cy="23.5" r="1.2" fill="#0f172a"/>
                        <circle  cx="20" cy="34" r="0.9" fill="rgba(186,230,253,0.55)"/>
                        <circle  cx="52" cy="32" r="0.9" fill="rgba(186,230,253,0.55)"/>
                    </svg>
                    <img class="skin-art art-burton" src="{{ asset('images/octopus-gothic.png') }}" width="{{ (int) ($o['size'] * 1.4) }}" height="{{ (int) ($o['size'] * 1.05) }}" alt="" aria-hidden="true" loading="lazy">
                    <img class="skin-art art-jackpot" src="{{ asset('images/octopus-jackpot.webp') }}" width="{{ (int) ($o['size'] * 1.19) }}" height="{{ (int) ($o['size'] * 1.23) }}" alt="" aria-hidden="true" loading="lazy">
                </div>
                @endforeach
                @endif
            </div>

            @if ($seaLife['jellyfish'])
            <div class="jelly" style="left:12%; top:50%; animation-delay:-2s;"><img class="skin-art art-burton" src="{{ asset('images/jelly-gothic.png') }}" width="60" height="86" alt="" aria-hidden="true" loading="lazy"><img class="skin-art art-jackpot" src="{{ asset('images/jelly-jackpot.webp') }}" width="55" height="95" alt="" aria-hidden="true" loading="lazy"></div>
            <div class="jelly" style="left:80%; top:35%; animation-delay:-5s;"><img class="skin-art art-burton" src="{{ asset('images/jelly-gothic.png') }}" width="60" height="86" alt="" aria-hidden="true" loading="lazy"><img class="skin-art art-jackpot" src="{{ asset('images/jelly-jackpot.webp') }}" width="55" height="95" alt="" aria-hidden="true" loading="lazy"></div>
            <div class="jelly" style="left:25%; top:70%; animation-delay:-7s;"><img class="skin-art art-burton" src="{{ asset('images/jelly-gothic.png') }}" width="60" height="86" alt="" aria-hidden="true" loading="lazy"><img class="skin-art art-jackpot" src="{{ asset('images/jelly-jackpot.webp') }}" width="55" height="95" alt="" aria-hidden="true" loading="lazy"></div>
            @endif

            <div class="sub-wrap">
                <svg class="sub-svg" viewBox="0 0 240 130" xmlns="http://www.w3.org/2000/svg">
                    <defs>
                        {{-- Cartoon "cardboard" submarine: flat cel colours, soft
                             top-to-bottom shading, thick ink outlines. Bow points
                             right (x~224) so the nose-down tilt / wake / bubble
                             mechanics keep working unchanged. --}}
                        <linearGradient id="hullGrad" x1="0" x2="0" y1="0" y2="1">
                            <stop offset="0"    stop-color="#ffe070"/>
                            <stop offset="0.5"  stop-color="#ffc932"/>
                            <stop offset="1"    stop-color="#f4a71d"/>
                        </linearGradient>
                        <linearGradient id="noseGrad" x1="0" x2="0" y1="0" y2="1">
                            <stop offset="0"    stop-color="#8dbde9"/>
                            <stop offset="0.5"  stop-color="#5289c9"/>
                            <stop offset="1"    stop-color="#376aa8"/>
                        </linearGradient>
                        <linearGradient id="finGrad" x1="0" x2="0" y1="0" y2="1">
                            <stop offset="0" stop-color="#5d93cf"/>
                            <stop offset="1" stop-color="#3a6ba6"/>
                        </linearGradient>
                        <radialGradient id="glass" cx="0.35" cy="0.3" r="0.9">
                            <stop offset="0"    stop-color="#eefaff"/>
                            <stop offset="0.45" stop-color="#a9dcf4"/>
                            <stop offset="1"    stop-color="#5aa4da"/>
                        </radialGradient>
                        <linearGradient id="propGrad" x1="0" x2="0" y1="0" y2="1">
                            <stop offset="0" stop-color="#f47272"/>
                            <stop offset="1" stop-color="#c92e2e"/>
                        </linearGradient>
                        <linearGradient id="wakeGrad" x1="0" y1="0" x2="-220" y2="-50" gradientUnits="userSpaceOnUse">
                            <stop offset="0%"   stop-color="#bae6fd" stop-opacity="0.70"/>
                            <stop offset="35%"  stop-color="#bae6fd" stop-opacity="0.40"/>
                            <stop offset="80%"  stop-color="#bae6fd" stop-opacity="0.10"/>
                            <stop offset="100%" stop-color="#bae6fd" stop-opacity="0"/>
                        </linearGradient>
                        {{-- Hull silhouette reused as a clip so the nose cap and
                             belly shade never bleed outside the outline. --}}
                        <clipPath id="hullClip">
                            <path d="M 44 64 C 44 44 58 34 84 33 L 156 32 C 200 32 224 46 224 64 C 224 82 200 96 156 96 L 84 95 C 58 94 44 84 44 64 Z"/>
                        </clipPath>
                    </defs>

                    <!-- wake + bubble trail: local origin at the propeller hub, behind everything -->
                    <g transform="translate(22, 64)">
                        {{-- Curved tapered wake body, bows UPWARD in local
                             frame. After the nose-down rotation this reads as a
                             fountain-style arc trailing the sub, water rising
                             past us as we descend. --}}
                        <path class="sub-wake"
                              d="M 0 -3
                                 Q -50 -22 -110 -38
                                 Q -170 -50 -210 -52
                                 Q -170 -46 -110 -32
                                 Q -50 -16 0 3 Z"
                              fill="url(#wakeGrad)"/>
                        {{-- Three parallel curved scrolling rails: a bright spine
                             plus two thinner offset arcs for layered turbulence. --}}
                        <path class="sub-wake-core"
                              d="M 0 0
                                 Q -50 -14 -110 -30
                                 Q -170 -44 -210 -48"
                              fill="none" stroke="rgba(255,255,255,0.60)" stroke-width="0.7" stroke-linecap="round"/>
                        <path class="sub-wake-core alt"
                              d="M 0 -1.6
                                 Q -50 -18 -100 -34
                                 Q -160 -47 -195 -50"
                              fill="none" stroke="rgba(186,230,253,0.55)" stroke-width="0.5" stroke-linecap="round"/>
                        <path class="sub-wake-core alt-2"
                              d="M 0 1.6
                                 Q -50 -10 -110 -26
                                 Q -170 -42 -200 -45"
                              fill="none" stroke="rgba(186,230,253,0.55)" stroke-width="0.5" stroke-linecap="round"/>

                        {{-- Bubble trail: each circle rises up-back from local (0,0),
                             staggered via per-element animation-delay so the stream
                             feels continuous. Path goes up-and-back in SVG space,
                             which rotates with the nose-down tilt. --}}
                        <g class="sub-bubbles" fill="rgba(186,230,253,0.78)" stroke="rgba(255,255,255,0.55)" stroke-width="0.4">
                            <circle class="prop-bubble"       cx="-8"  cy="0"  r="2.4" style="animation-delay: 0s;"/>
                            <circle class="prop-bubble var-2" cx="-6"  cy="-2" r="1.6" style="animation-delay: -0.10s;"/>
                            <circle class="prop-bubble var-3" cx="-9"  cy="2"  r="2.0" style="animation-delay: -0.20s;"/>
                            <circle class="prop-bubble"       cx="-7"  cy="-3" r="1.3" style="animation-delay: -0.30s;"/>
                            <circle class="prop-bubble var-2" cx="-10" cy="1"  r="2.8" style="animation-delay: -0.40s;"/>
                            <circle class="prop-bubble var-3" cx="-5"  cy="-1" r="1.1" style="animation-delay: -0.50s;"/>
                            <circle class="prop-bubble"       cx="-8"  cy="3"  r="2.2" style="animation-delay: -0.60s;"/>
                            <circle class="prop-bubble var-2" cx="-6"  cy="-4" r="1.5" style="animation-delay: -0.70s;"/>
                            <circle class="prop-bubble var-3" cx="-9"  cy="0"  r="1.9" style="animation-delay: -0.80s;"/>
                            <circle class="prop-bubble"       cx="-11" cy="-2" r="2.4" style="animation-delay: -0.90s;"/>
                            <circle class="prop-bubble var-2" cx="-4"  cy="2"  r="1.2" style="animation-delay: -1.00s;"/>
                            <circle class="prop-bubble var-3" cx="-8"  cy="-3" r="1.7" style="animation-delay: -1.10s;"/>
                            <circle class="prop-bubble"       cx="-10" cy="1"  r="2.1" style="animation-delay: -1.20s;"/>
                            <circle class="prop-bubble var-2" cx="-6"  cy="3"  r="1.4" style="animation-delay: -1.30s;"/>
                            <circle class="prop-bubble var-3" cx="-9"  cy="-1" r="2.6" style="animation-delay: -1.40s;"/>
                            <circle class="prop-bubble"       cx="-7"  cy="2"  r="1.0" style="animation-delay: -1.50s;"/>
                            <circle class="prop-bubble var-2" cx="-11" cy="0"  r="2.3" style="animation-delay: -1.60s;"/>
                            <circle class="prop-bubble var-3" cx="-5"  cy="-2" r="1.5" style="animation-delay: -1.70s;"/>
                            <circle class="prop-bubble"       cx="-8"  cy="3"  r="1.8" style="animation-delay: -1.85s;"/>
                            <circle class="prop-bubble var-2" cx="-10" cy="-3" r="2.0" style="animation-delay: -2.00s;"/>
                        </g>
                    </g>

                    {{-- Cartoon hull. Hidden on the gothic skin, which swaps in a
                         raster sub below; wake + bubbles above stay shared. --}}
                    <g class="sub-body">
                    {{-- Tail fins (swept back, tucked behind the hull) --}}
                    <path d="M 86 36 L 70 14 L 50 16 L 46 48 Z"   fill="url(#finGrad)" stroke="#1f2a37" stroke-width="2" stroke-linejoin="round"/>
                    <path d="M 86 92 L 70 114 L 50 112 L 46 80 Z" fill="url(#finGrad)" stroke="#1f2a37" stroke-width="2" stroke-linejoin="round"/>

                    {{-- Conning tower (drawn before the hull so its base tucks under the deck) --}}
                    <rect x="102" y="18" width="62" height="22" rx="7" fill="url(#hullGrad)" stroke="#1f2a37" stroke-width="2"/>

                    {{-- Hull: fat yellow capsule, big round bow --}}
                    <path d="M 44 64 C 44 44 58 34 84 33 L 156 32 C 200 32 224 46 224 64 C 224 82 200 96 156 96 L 84 95 C 58 94 44 84 44 64 Z"
                          fill="url(#hullGrad)" stroke="#1f2a37" stroke-width="2.2"/>
                    {{-- belly shade --}}
                    <path d="M 44 76 Q 130 86 224 76 L 224 100 L 44 100 Z" fill="#e0901a" opacity="0.4" clip-path="url(#hullClip)"/>
                    {{-- deck gloss --}}
                    <path d="M 62 41 Q 130 35 204 42" stroke="#fff" stroke-width="3.5" fill="none" opacity="0.35" stroke-linecap="round"/>

                    {{-- Blue nose cap with riveted seam --}}
                    <path d="M 178 20 Q 170 64 178 108 L 240 108 L 240 20 Z" fill="url(#noseGrad)" clip-path="url(#hullClip)"/>
                    <path d="M 178 20 Q 170 64 178 108" fill="none" stroke="#1f2a37" stroke-width="2" clip-path="url(#hullClip)"/>
                    <path d="M 186 44 Q 204 38 214 50" stroke="#fff" stroke-width="3" fill="none" opacity="0.45" stroke-linecap="round"/>
                    <g fill="#d19a1f" stroke="#1f2a37" stroke-width="0.7">
                        <circle cx="170.5" cy="38" r="1.5"/>
                        <circle cx="169.4" cy="51" r="1.5"/>
                        <circle cx="169"   cy="64" r="1.5"/>
                        <circle cx="169.4" cy="77" r="1.5"/>
                        <circle cx="170.5" cy="90" r="1.5"/>
                    </g>
                    {{-- hull outline redrawn on top so the cap / shade fills do not eat it --}}
                    <path d="M 44 64 C 44 44 58 34 84 33 L 156 32 C 200 32 224 46 224 64 C 224 82 200 96 156 96 L 84 95 C 58 94 44 84 44 64 Z"
                          fill="none" stroke="#1f2a37" stroke-width="2.2"/>

                    {{-- Deck rivet row --}}
                    <g fill="#d19a1f" stroke="#1f2a37" stroke-width="0.6">
                        <circle cx="64"  cy="42" r="1.3"/>
                        <circle cx="76"  cy="42" r="1.3"/>
                        <circle cx="88"  cy="42" r="1.3"/>
                        <circle cx="100" cy="42" r="1.3"/>
                        <circle cx="112" cy="42" r="1.3"/>
                        <circle cx="124" cy="42" r="1.3"/>
                        <circle cx="136" cy="42" r="1.3"/>
                        <circle cx="148" cy="42" r="1.3"/>
                        <circle cx="160" cy="42" r="1.3"/>
                    </g>

                    {{-- Three portholes --}}
                    @foreach ([86, 120, 154] as $px)
                    <g>
                        <circle cx="{{ $px }}" cy="66" r="13"  fill="#7d9bbd" stroke="#1f2a37" stroke-width="2"/>
                        <circle cx="{{ $px }}" cy="66" r="9.5" fill="url(#glass)" stroke="#33557a" stroke-width="1.2"/>
                        <ellipse cx="{{ $px - 3.5 }}" cy="62" rx="3" ry="1.8" transform="rotate(-35 {{ $px - 3.5 }} 62)" fill="#fff" opacity="0.8"/>
                        <g fill="#4a6a8e" stroke="#1f2a37" stroke-width="0.5">
                            <circle cx="{{ $px - 8.1 }}" cy="57.9" r="1.3"/>
                            <circle cx="{{ $px + 8.1 }}" cy="57.9" r="1.3"/>
                            <circle cx="{{ $px - 8.1 }}" cy="74.1" r="1.3"/>
                            <circle cx="{{ $px + 8.1 }}" cy="74.1" r="1.3"/>
                        </g>
                    </g>
                    @endforeach

                    {{-- Tower details: periscope, lens, antenna + running light, small porthole --}}
                    <path d="M 116 22 L 116 11 Q 116 6 121 6 L 131 6" fill="none" stroke="#1f2a37" stroke-width="8.5" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M 116 22 L 116 11 Q 116 6 121 6 L 131 6" fill="none" stroke="#ffc932" stroke-width="5"   stroke-linecap="round" stroke-linejoin="round"/>
                    <rect x="110.5" y="17" width="11" height="6" rx="2" fill="#f4a71d" stroke="#1f2a37" stroke-width="1.5"/>
                    <circle cx="136"   cy="6"   r="6"   fill="#1f2a37"/>
                    <circle cx="136"   cy="6"   r="4.6" fill="#7d9bbd"/>
                    <circle cx="136"   cy="6"   r="3.1" fill="url(#glass)"/>
                    <circle cx="134.9" cy="4.9" r="1"   fill="#fff" opacity="0.85"/>
                    <line x1="156" y1="18" x2="156" y2="11" stroke="#1f2a37" stroke-width="2" stroke-linecap="round"/>
                    <circle class="periscope-light" cx="156" cy="9.5" r="2.4" fill="#ef4444"/>
                    <circle cx="150" cy="26.5" r="5"   fill="#7d9bbd" stroke="#1f2a37" stroke-width="1.5"/>
                    <circle cx="150" cy="26.5" r="3.2" fill="url(#glass)" stroke="#33557a" stroke-width="0.8"/>
                    <g fill="#d19a1f" stroke="#1f2a37" stroke-width="0.6">
                        <circle cx="126" cy="29" r="1.1"/>
                        <circle cx="133" cy="29" r="1.1"/>
                        <circle cx="140" cy="29" r="1.1"/>
                    </g>

                    {{-- Stern: prop shaft, horizontal stabiliser, red 3-blade propeller --}}
                    <rect x="26" y="61.5" width="22" height="5" rx="2" fill="#3b4a5c" stroke="#1f2a37" stroke-width="1.2"/>
                    <path d="M 76 56 L 44 48 L 32 64 L 44 80 L 76 72 Z" fill="url(#finGrad)" stroke="#1f2a37" stroke-width="2" stroke-linejoin="round"/>
                    <g transform="translate(22, 64)">
                        <g class="propeller">
                            {{-- invisible disc keeps the fill-box symmetric so the
                                 3-blade rotor spins about its hub, not its bbox --}}
                            <circle r="18.5" fill="none" stroke="none"/>
                            <ellipse cx="0" cy="-9" rx="4.3" ry="9.5" fill="url(#propGrad)" stroke="#1f2a37" stroke-width="1.3"/>
                            <ellipse cx="0" cy="-9" rx="4.3" ry="9.5" fill="url(#propGrad)" stroke="#1f2a37" stroke-width="1.3" transform="rotate(120)"/>
                            <ellipse cx="0" cy="-9" rx="4.3" ry="9.5" fill="url(#propGrad)" stroke="#1f2a37" stroke-width="1.3" transform="rotate(240)"/>
                            <circle r="4" fill="#f47272" stroke="#1f2a37" stroke-width="1.3"/>
                        </g>
                    </g>
                    </g>

                    {{-- Gothic skin sub. Placed so its spiral propeller sits on the
                         wake/bubble origin (22,64); lantern glow flickers on top. --}}
                    <g class="sub-gothic" aria-hidden="true">
                        <defs>
                            <radialGradient id="gothicLanternGlow">
                                <stop offset="0%"   stop-color="#fff4c2" stop-opacity="0.85"/>
                                <stop offset="45%"  stop-color="#f5e08a" stop-opacity="0.35"/>
                                <stop offset="100%" stop-color="#f5e08a" stop-opacity="0"/>
                            </radialGradient>
                        </defs>
                        <image class="sub-prop" href="{{ asset('images/sub-gothic-prop.webp') }}?v={{ filemtime(public_path('images/sub-gothic-prop.webp')) }}" x="5.84" y="46.06" width="20.06" height="39.03" style="--axis: 51.4%" preserveAspectRatio="none"/>
                        <image href="{{ asset('images/sub-gothic-hull.webp') }}?v={{ filemtime(public_path('images/sub-gothic-hull.webp')) }}" x="-5" y="-32" width="232" height="137.4" preserveAspectRatio="xMidYMid meet"/>
                        <circle class="gothic-lantern-glow" cx="171" cy="-5" r="20" fill="url(#gothicLanternGlow)"/>
                    </g>

                    {{-- Jackpot Reef sub: its propeller hub on the same wake origin (22,64). --}}
                    <g class="sub-jackpot" aria-hidden="true">
                        <image class="sub-prop" href="{{ asset('images/sub-jackpot-prop.webp') }}?v={{ filemtime(public_path('images/sub-jackpot-prop.webp')) }}" x="14.2" y="35.53" width="12.85" height="56.42" style="--axis: 52%" preserveAspectRatio="none"/>
                        <image href="{{ asset('images/sub-jackpot-hull.webp') }}?v={{ filemtime(public_path('images/sub-jackpot-hull.webp')) }}" x="14.2" y="-34.3" width="200" height="145.8" preserveAspectRatio="xMidYMid meet"/>
                    </g>
                </svg>
                {{-- Gothic implosion sprite; shares .sub-svg so it tilts exactly like the sub. --}}
                <div class="sub-svg sub-boom" aria-hidden="true"><span></span></div>
            </div>
            <div id="bubbles-near" class="bubbles bubbles-near"></div>
        </div>

        <!-- Strobe sits above the scene but below the UI -->
        <div class="strobe"></div>

        <!-- Depth meter: surface → Challenger Deep; ticks every 2 km on a linear depth axis -->
        <div class="depth-meter" id="depth-meter" aria-hidden="true">
            <div class="depth-track"></div>
            <div class="depth-fill"></div>
            @foreach ([0, 2, 4, 6, 8, 10] as $km)
            <span class="depth-tick" style="--at: {{ round($km * 1000 / 10994 * 100, 2) }}%">{{ $km === 0 ? '0 m' : $km.' km' }}</span>
            @endforeach
            <div class="depth-needle"><div class="depth-chip"><b id="depth-value">0 m</b><small id="depth-zone">SURFACE</small></div></div>
        </div>

        <!-- Recent crashes -->
        <div class="history-wrap" aria-label="Recent crashes">
            <div class="history-label">Recent crashes</div>
            <div id="history" class="history"></div>
            {{-- Round status sits under the rail, clear of the diving sub. --}}
            <div id="state" class="state">Awaiting next dive…</div>
            <div class="warnings">
                <div><i>!</i>Pressure critical</div>
                <div><i>!</i>Hull damage detected</div>
            </div>
        </div>

        <!-- State + multiplier with cockpit gauges flanking the readout -->
        <div class="center">
            <div class="center-row">
                <div class="gauge gauge-pressure" title="Pressure" data-level="ok">
                    <svg width="44" height="44" viewBox="0 0 44 44" aria-hidden="true">
                        <circle class="dial-face" cx="22" cy="22" r="21"/>
                        <circle class="dial-track" cx="22" cy="22" r="17" pathLength="100" transform="rotate(135 22 22)"/>
                        <circle id="pressureArc" class="dial-arc" cx="22" cy="22" r="17" pathLength="100" transform="rotate(135 22 22)"/>
                        @for ($tick = 0; $tick <= 9; $tick++)
                        <line class="dial-tick{{ $tick >= 7 ? ' hot' : '' }}" x1="22" y1="8" x2="22" y2="{{ $tick % 3 === 0 ? 11.5 : 10 }}" transform="rotate({{ -135 + $tick * 30 }} 22 22)"/>
                        @endfor
                        <g class="dial-wobble"><line id="pressureNeedle" class="dial-needle" x1="22" y1="22" x2="22" y2="9"/></g>
                        <circle class="dial-hub" cx="22" cy="22" r="2.6"/>
                    </svg>
                    <div class="gauge-text">
                        <div class="gauge-label">Pressure</div>
                        <div class="gauge-value"><span id="pressureValue">0</span><small>bar</small></div>
                    </div>
                </div>

                <div id="multiplier" class="multiplier">1.00x</div>

                <div class="gauge gauge-hull" title="Hull integrity" data-level="ok">
                    <div class="gauge-text">
                        <div class="gauge-label">Hull<span class="gauge-label-long"> integrity</span></div>
                        <div class="gauge-value"><span id="hullValue">100</span><small>%</small></div>
                        <div class="hull-bar"><div id="hullFill" class="hull-fill" style="width:100%"></div></div>
                    </div>
                    <svg width="38" height="44" viewBox="0 0 24 28" aria-hidden="true">
                        <defs><clipPath id="hullShieldClip"><path d="M12 2 3 5.5v7c0 6.2 3.9 10.9 9 13 5.1-2.1 9-6.8 9-13v-7L12 2z"/></clipPath></defs>
                        <path class="hull-shield-outline" d="M12 2 3 5.5v7c0 6.2 3.9 10.9 9 13 5.1-2.1 9-6.8 9-13v-7L12 2z"/>
                        <rect id="hullLevel" class="hull-shield-level" x="0" y="2" width="24" height="24" clip-path="url(#hullShieldClip)"/>
                        <path d="M8.5 13.5l2.5 2.5 4.5-5" fill="none" stroke="rgba(0,0,0,0.55)" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
            </div>
        </div>

        <!-- Sonar -->
        <div class="sonar"></div>

        <!-- Cash out (legacy visual demo — hidden when embedded; replaced by .operator-panel) -->
        <div class="cashout-wrap">
            <button id="cashout" class="cashout" disabled>
                CASH OUT
                <small id="cashoutHint">awaiting dive</small>
            </button>
        </div>

        <!-- Operator HUD bar — slim, single-row. Buttons here do NOT call /v1/bets
             directly; they postMessage their intent to the parent operator page,
             which signs and forwards to Orange. -->
        <div class="operator-panel" id="op-panel" data-mode="place" aria-hidden="true">
            <div class="op-id">
                <span class="op-name" id="op-player">—</span>
                <span class="op-balance" id="op-balance">—</span>
            </div>

            <span class="op-sep"></span>

            <span class="op-phase" id="op-phase" data-phase="waiting">waiting</span>

            <span class="op-sep"></span>

            {{-- Bet slots: each its own stake, auto-cashout, auto-bet and bet. The second
                 is offered when the page hosting the game takes two bets a round
                 (player_info.bet_slots), like Aviator's second bet panel. --}}
            <div class="op-slots">
                @foreach ([0, 1] as $slot)
                <div class="op-bet-row op-slot" data-slot="{{ $slot }}" data-mode="place"@if ($slot === 1) hidden @endif>
                    <div class="op-stepper">
                        <button class="op-step op-stake-down" type="button" aria-label="Decrease stake{{ $slot ? ' (bet 2)' : '' }}">−</button>
                        <input class="op-input op-stake" data-kind="stake" type="number" step="0.01" min="0.01" value="10.00" inputmode="decimal" aria-label="Stake{{ $slot ? ' (bet 2)' : '' }}">
                        <button class="op-step op-stake-up" type="button" aria-label="Increase stake{{ $slot ? ' (bet 2)' : '' }}">+</button>
                    </div>
                    <div class="op-stepper op-stepper-auto">
                        <button class="op-step op-auto-down" type="button" aria-label="Decrease auto cashout{{ $slot ? ' (bet 2)' : '' }}">−</button>
                        <input class="op-input op-auto" data-kind="auto" type="number" step="0.01" min="1.01" placeholder="auto ×" inputmode="decimal" aria-label="Auto cashout{{ $slot ? ' (bet 2)' : '' }}">
                        <button class="op-step op-auto-up" type="button" aria-label="Increase auto cashout{{ $slot ? ' (bet 2)' : '' }}">+</button>
                        <button class="op-clear op-auto-clear" type="button" aria-label="Clear auto cashout{{ $slot ? ' (bet 2)' : '' }}" title="Clear">×</button>
                    </div>
                    <button class="op-btn op-autobet-btn" type="button"
                            data-active="false" aria-pressed="false"
                            title="When ON, automatically place this bet every round with its stake + auto-cashout.">
                        <span class="op-autobet-label-full">AUTO BET</span>
                        <span class="op-autobet-label-short">AUTO</span>
                        <span class="op-autobet-dot" aria-hidden="true"></span>
                    </button>
                    {{-- Place Bet and Cash Out share one slot so the button the player
                         needs is always under the cursor when the round flips. --}}
                    <div class="op-action">
                        <button class="op-btn op-bet-btn" disabled>Place Bet</button>
                        <button class="op-btn op-cashout-btn" disabled>
                            Cash Out
                            <span class="op-cashout-x"></span>
                        </button>
                    </div>
                    @if ($slot === 0)
                    <button class="op-slot-toggle op-add-slot" type="button" title="Add a second bet" aria-label="Add a second bet" hidden>+</button>
                    @else
                    <button class="op-slot-toggle op-remove-slot" type="button" title="Remove the second bet" aria-label="Remove the second bet">−</button>
                    @endif
                </div>
                @endforeach
            </div>

            <div class="op-spacer"></div>

            <div id="op-last" class="op-last">
                <span class="op-last-label">Last</span>
                <span class="op-last-mult" id="op-last-mult">—</span>
                <span class="op-last-payout" id="op-last-payout">—</span>
            </div>

            <div id="op-status" class="op-status">connecting…</div>
        </div>

        <!-- Implode sequence layers -->
        <div class="shockwave"></div>
        <div class="crash-flash"></div>
        <div id="debris" class="crash-debris"></div>
        <svg class="crash-cracks" viewBox="0 0 100 100" preserveAspectRatio="none">
            <defs>
                <radialGradient id="crackGrad" cx="50%" cy="50%" r="60%">
                    <stop offset="0%" stop-color="#ffffff" stop-opacity="1"/>
                    <stop offset="50%" stop-color="#ffffff" stop-opacity="0.7"/>
                    <stop offset="100%" stop-color="#ffffff" stop-opacity="0"/>
                </radialGradient>
            </defs>
            <g stroke="url(#crackGrad)" stroke-width="0.18" fill="none" stroke-linecap="round" opacity="0.95">
                <!-- center web -->
                <path d="M50,50 L8,4"/>
                <path d="M50,50 L94,6"/>
                <path d="M50,50 L4,38"/>
                <path d="M50,50 L98,42"/>
                <path d="M50,50 L12,98"/>
                <path d="M50,50 L92,96"/>
                <path d="M50,50 L48,2"/>
                <path d="M50,50 L52,99"/>
                <!-- secondary fractures -->
                <path d="M50,50 L26,18 L32,12 L38,8"/>
                <path d="M50,50 L70,28 L78,22 L86,18"/>
                <path d="M50,50 L24,68 L18,80 L8,90"/>
                <path d="M50,50 L74,72 L82,82 L92,90"/>
                <path d="M50,50 L18,46 L8,48"/>
                <path d="M50,50 L82,54 L94,52"/>
                <!-- branches off the spokes -->
                <path d="M30,30 L18,22 M30,30 L34,18"/>
                <path d="M70,30 L84,20 M70,30 L72,16"/>
                <path d="M30,72 L20,84 M30,72 L26,86"/>
                <path d="M72,70 L86,82 M72,70 L78,86"/>
            </g>
        </svg>
        <div class="crash-banner"><b></b><span>Hull breach</span><b></b></div>

        <!-- Activity log -->
        <div id="log" class="log"></div>
    </div>

    {{-- Each illustrated skin's own sounds (used by the audio engine below while that skin is on). --}}
    <script src="{{ asset('js/orange/sound-gothic.js') }}?v={{ filemtime(public_path('js/orange/sound-gothic.js')) }}"></script>
    <script src="{{ asset('js/orange/sound-jackpot.js') }}?v={{ filemtime(public_path('js/orange/sound-jackpot.js')) }}"></script>
    <script type="module">
        const $stage = document.getElementById('stage');
        const $mult = document.getElementById('multiplier');
        const $state = document.getElementById('state');
        const $log = document.getElementById('log');
        if (new URLSearchParams(location.search).has('debug')) document.body.classList.add('debug');
        const $pressArc = document.getElementById('pressureArc');
        const $pressVal = document.getElementById('pressureValue');
        const $hullFill = document.getElementById('hullFill');
        const $hullVal = document.getElementById('hullValue');
        const $pressNeedle = document.getElementById('pressureNeedle');
        const $hullLevel = document.getElementById('hullLevel');
        const $pressGauge = $pressArc.closest('.gauge');
        const $hullGauge = $hullFill.closest('.gauge');
        // Pressure dial sweeps 270°; hull colour runs green (120°) → red (0°).
        function setGauges(norm, hull, pressLevel) {
            $pressArc.style.strokeDasharray = (75 * norm).toFixed(2) + ' 100';
            $pressNeedle.style.transform = 'rotate(' + (-135 + 270 * norm).toFixed(1) + 'deg)';
            $pressGauge.dataset.level = pressLevel;
            $hullGauge.dataset.level = hull <= 30 ? 'crit' : (hull <= 60 ? 'warn' : 'ok');
            $hullGauge.style.setProperty('--hull-c', 'hsl(' + Math.round(hull * 1.2) + ' 80% 50%)');
            $hullLevel.style.transform = 'scaleY(' + (hull / 100) + ')';
            $hullFill.style.width = hull + '%';
        }
        const $cashout = document.getElementById('cashout');
        const $cashoutHint = document.getElementById('cashoutHint');

        // Rising bubbles in depth layers, for parallax. --base is a bubble's own
        // speed and --layer its depth (see .bubble): far ones crawl, near ones
        // rush past. The parent stage class drives --speed / --drift-x, so all
        // layers speed up together in deeper dive states without resetting a
        // bubble. Fewer on phones. Every player sees the same dive (a device's
        // reduced-motion setting doesn't change it: the dive is the game).
        const fewer = window.matchMedia('(max-width: 600px)').matches ? 0.6 : 1;
        function spawnBubbles(id, count, { min, max, layer, band }) {
            const host = document.getElementById(id);
            for (let i = 0; i < count; i++) {
                const b = document.createElement('div');
                b.className = 'bubble';
                const size = min + Math.random() * (max - min);
                b.style.width = b.style.height = size + 'px';
                // Spawn across a band reaching offscreen right (wider for faster
                // layers) so the leftward dive drift keeps the screen populated;
                // without this the right side empties as soon as the sub moves.
                b.style.left = (-10 + Math.random() * band) + 'vw';
                b.style.setProperty('--layer', layer);
                b.style.setProperty('--base', (6 + Math.random() * 10) + 's');
                b.style.animationDelay = (-Math.random() * 12) + 's';
                host.appendChild(b);
            }
        }
        spawnBubbles('bubbles', 64, { min: 4, max: 18, layer: 1, band: 150 });
        spawnBubbles('bubbles-far', Math.round(90 * fewer), { min: 2, max: 6, layer: 0.45, band: 150 });
        spawnBubbles('bubbles-dive', Math.round(90 * fewer), { min: 3, max: 14, layer: 1.2, band: 230 });
        spawnBubbles('bubbles-near', Math.round(18 * fewer), { min: 24, max: 56, layer: 2, band: 300 });

        // ambient silt particles. Spawn slightly past the right edge so the
        // strong leftward drift during dive keeps the visible area populated
        // instead of emptying out as everything streams toward upper-left.
        const particleHost = document.getElementById('particles');
        for (let i = 0; i < 50; i++) {
            const p = document.createElement('div');
            p.className = 'particle';
            p.style.left = (-5 + Math.random() * 130) + 'vw';
            p.style.animationDuration = (8 + Math.random() * 14) + 's';
            p.style.animationDelay = (-Math.random() * 16) + 's';
            p.style.opacity = (0.3 + Math.random() * 0.5);
            particleHost.appendChild(p);
        }

        // 24 hull fragments — preset random vectors, only animated when .crashed fires
        const debrisHost = document.getElementById('debris');
        function rebuildDebris() {
            debrisHost.innerHTML = '';
            for (let i = 0; i < 24; i++) {
                const f = document.createElement('div');
                f.className = 'frag';
                const angle = (i / 24) * Math.PI * 2 + (Math.random() - 0.5) * 0.5;
                const dist = 280 + Math.random() * 320;
                f.style.setProperty('--dx', Math.cos(angle) * dist + 'px');
                f.style.setProperty('--dy', Math.sin(angle) * dist + 'px');
                f.style.setProperty('--d', (Math.random() * 0.18) + 's');
                f.style.width = f.style.height = (3 + Math.random() * 8) + 'px';
                debrisHost.appendChild(f);
            }
        }
        rebuildDebris();

        // ---------- Recent crash history (top chip rail) ----------
        const HISTORY_MAX = 8;
        const $history = document.getElementById('history');
        // Newest first: { uuid, cp }. A chip with a round id opens that round's details.
        const UUID = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i;
        const history = @json($recentCrashes ?? []).map((r) => ({ uuid: r.round_uuid, cp: r.crash_point }));

        function tierClassFor(cp) {
            if (cp >= 10) return 'tier-epic';
            if (cp >= 3)  return 'tier-high';
            if (cp >= 1.5) return 'tier-mid';
            return 'tier-low';
        }
        function renderHistory(highlightFirst = false) {
            $history.innerHTML = history.map(({ uuid, cp }, i) => {
                const cls = `history-chip ${tierClassFor(cp)}${highlightFirst && i === 0 ? ' is-new' : ''}`;
                return UUID.test(uuid ?? '')
                    ? `<button type="button" class="${cls}" data-round="${uuid}" title="Round details">${cp.toFixed(2)}x</button>`
                    : `<span class="${cls}">${cp.toFixed(2)}x</span>`;
            }).join('');
            fitHistory();
        }
        $history.addEventListener('click', (e) => {
            const chip = e.target.closest('[data-round]');
            if (chip) sheet.openRound(chip.dataset.round);
        });
        // Only whole chips: the rail stays clear of the buttons at the top right, and a
        // chip that doesn't fit is left out rather than cut in half. (Offsets, not rects:
        // a new chip is still popping in, scaled.)
        function fitHistory() {
            const chips = [...$history.children];
            chips.forEach((c) => { c.style.display = ''; });
            const edge = $history.offsetLeft + $history.clientWidth - parseFloat(getComputedStyle($history).paddingRight);
            chips.forEach((c) => { if (c.offsetLeft + c.offsetWidth > edge + 0.5) c.style.display = 'none'; });
        }
        window.addEventListener('resize', fitHistory);
        document.fonts?.ready.then(fitHistory);
        function pushHistory(cp, uuid) {
            history.unshift({ uuid, cp });
            if (history.length > HISTORY_MAX) history.length = HISTORY_MAX;
            renderHistory(true);
        }
        renderHistory();

        // The rail keeps clear of the top-right buttons, whichever of them are shown:
        // their row's width plus its margin, on both sides (the rail is centred).
        const $controls = document.getElementById('top-controls');
        new ResizeObserver(() => {
            const r = $controls.getBoundingClientRect();
            const reserve = r.width + 2 * (window.innerWidth - r.right);
            document.documentElement.style.setProperty('--ctl-reserve', `${Math.ceil(reserve)}px`);
            fitHistory();
        }).observe($controls);

        // ---------- Round details and My bets (one dialog over the game) ----------
        // Round details come from the public GET /v1/rounds/{uuid}; My bets from the page
        // hosting the game (bet_history → bet_history_result), wired by the operator bridge.
        const sheet = (function roundSheet() {
            const ROUNDS_URL = @json(url('/v1/rounds'));
            const $sheet = document.getElementById('sheet');
            const $title = document.getElementById('sheet-title');
            const $pill  = document.getElementById('sheet-pill');
            const $time  = document.getElementById('sheet-time');
            const $body  = document.getElementById('sheet-body');
            const $back  = document.getElementById('sheet-back');
            const $close = document.getElementById('sheet-close');

            const settledRounds = new Map(); // uuid → details; a settled round no longer changes
            let view = null;                 // 'round' | 'bets' while open
            let token = 0;                   // answers for a view that has since changed are dropped
            let lastFocus = null;
            let bets = { request: null, list: null, pending: false, timer: null, money: (v) => String(v) };

            function el(tag, attrs = {}, ...children) {
                const node = document.createElement(tag);
                for (const [k, v] of Object.entries(attrs)) {
                    if (v === null || v === undefined || v === false) continue;
                    if (k.startsWith('on')) node.addEventListener(k.slice(2), v);
                    else if (k === 'html') node.innerHTML = v; // only ever our own markup (icons)
                    else node.setAttribute(k, v === true ? '' : v);
                }
                node.append(...children.flat().filter((c) => c !== null && c !== undefined && c !== false));
                return node;
            }
            const note = (...children) => el('p', { class: 'sheet-note' }, ...children);
            const x = (cp) => `${Number(cp).toFixed(2)}x`;
            const chip = (cp) => el('span', { class: `x-chip ${tierClassFor(Number(cp))}` }, x(cp));
            const clock = (iso, withDate = false) => {
                const d = iso ? new Date(iso) : null;
                if (!d || Number.isNaN(d.getTime())) return '';
                const time = d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
                return withDate ? `${d.toLocaleDateString([], { day: 'numeric', month: 'short' })} ${time}` : time;
            };
            const isToday = (iso) => new Date(iso).toDateString() === new Date().toDateString();

            function show(name, { back = false } = {}) {
                if ($sheet.hidden) {
                    lastFocus = document.activeElement;
                    $sheet.hidden = false;
                    $close.focus({ preventScroll: true });
                }
                view = name;
                $back.hidden = !back;
                return ++token;
            }
            function close() {
                if ($sheet.hidden) return;
                $sheet.hidden = true;
                view = null;
                token++;
                lastFocus?.focus?.({ preventScroll: true });
            }
            function head(title, cp = null, time = '') {
                $title.textContent = title;
                $pill.hidden = cp === null;
                if (cp !== null) $pill.replaceChildren(chip(cp));
                $time.textContent = time;
            }

            $close.addEventListener('click', close);
            $back.addEventListener('click', () => openBets());
            $sheet.addEventListener('click', (e) => { if (e.target === $sheet) close(); });
            document.addEventListener('keydown', (e) => { if (e.key === 'Escape') close(); });

            // ----- Round details -----
            const ICONS = {
                server: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3.5" width="18" height="7" rx="1.5"/><rect x="3" y="13.5" width="18" height="7" rx="1.5"/><path d="M7 7h.01M7 17h.01M11 7h6M11 17h6"/></svg>',
                lock:   '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="4.5" y="10.5" width="15" height="10" rx="2"/><path d="M8 10.5V7.5a4 4 0 0 1 8 0v3M12 14.5v2.5"/></svg>',
                client: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="4.5" width="16" height="11" rx="1.5"/><path d="M2 19.5h20M9 15.5l-.5 4M15 15.5l.5 4"/></svg>',
                hash:   '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2.8 4.5 6v5.5c0 4.6 3.1 8.4 7.5 9.7 4.4-1.3 7.5-5.1 7.5-9.7V6L12 2.8Z"/><path d="M10 9.5 9 15.5M15 9.5l-1 6M8.5 11.5h7.5M8 13.5h7.5"/></svg>',
            };
            function svgIcon(name) {
                const t = document.createElement('template');
                t.innerHTML = ICONS[name];
                const svg = t.content.firstElementChild;
                svg.setAttribute('aria-hidden', 'true');
                return svg;
            }
            function section(icon, label, sub, value) {
                return el('div', { class: 'pf-section' },
                    svgIcon(icon),
                    el('div', { class: 'pf-label' }, label),
                    el('div', { class: 'pf-sub' }, sub),
                    value);
            }
            const value = (text, pending = false) => el('div', { class: `pf-value${pending ? ' is-pending' : ''}` }, text);

            // The same maths as the server (ProvablyFairService), redone here from the
            // revealed seed. Needs WebCrypto, which browsers only offer in secure contexts
            // (not inside an http casino page): without it there's no check, not a failed one.
            function crashFromHmac(hmac, divisor) {
                if (parseInt(hmac.slice(0, 8), 16) % divisor === 0) return 1;
                const h = parseInt(hmac.slice(0, 13), 16);
                const e = 2 ** 52;
                return Math.max(1, Math.floor((100 * e - h) / (e - h)) / 100);
            }
            async function checkInBrowser(f) {
                const subtle = window.crypto?.subtle;
                if (!subtle || !f.server_seed || !f.verification) return null;
                try {
                    const enc = new TextEncoder();
                    const hex = (buf) => Array.from(new Uint8Array(buf), (b) => b.toString(16).padStart(2, '0')).join('');
                    const key = await subtle.importKey('raw', enc.encode(f.server_seed), { name: 'HMAC', hash: 'SHA-256' }, false, ['sign']);
                    const [hash, hmac] = await Promise.all([
                        subtle.digest('SHA-256', enc.encode(f.server_seed)).then(hex),
                        subtle.sign('HMAC', key, enc.encode(`${f.client_seed}:${f.nonce}`)).then(hex),
                    ]);
                    return hash === f.server_seed_hash
                        && hmac === f.verification.hmac
                        && crashFromHmac(hmac, f.verification.house_edge_divisor) === Number(f.verification.crash_point);
                } catch (_) {
                    return null;
                }
            }

            function renderRound(d, my) {
                const f = d.fairness ?? {};
                const v = f.verification;
                const cp = d.result?.crash_point ?? null;
                head(`Round ${d.round_id}`, cp, clock(d.crashed_at ?? d.started_at ?? d.betting_opened_at, !isToday(d.crashed_at ?? d.betting_opened_at ?? Date.now())));

                const hmac = v ? el('div', { class: 'pf-value' }, el('mark', {}, v.hmac.slice(0, 13)), v.hmac.slice(13)) : value('Worked out once the round is over', true);
                const parts = [
                    section('server', 'Server seed', f.server_seed ? 'Made by us before the round, revealed after it' : 'Made by us before the round, revealed once it is over',
                        f.server_seed ? value(f.server_seed) : value('Revealed once the round is over', true)),
                    section('lock', 'Server seed SHA-256', 'Published before betting opened, so the seed could not change',
                        value(f.server_seed_hash ?? '—')),
                    section('client', 'Client seed : nonce', 'Public, the same for every player in the round',
                        value(`${f.client_seed}:${f.nonce}`)),
                    section('hash', 'HMAC-SHA256', 'The client seed and nonce, keyed with the server seed. This is the round result', hmac),
                ];
                if (v) {
                    parts.push(el('dl', { class: 'pf-calc' },
                        el('div', {}, el('dt', {}, 'Hex'), el('dd', {}, v.hex)),
                        el('div', {}, el('dt', {}, 'Decimal'), el('dd', {}, String(v.decimal))),
                        el('div', {}, el('dt', {}, 'Result'), el('dd', {}, x(v.crash_point)))));
                    if (v.instant_crash) {
                        parts.push(el('p', { class: 'pf-edge' },
                            `The first 8 hex digits (${v.hmac.slice(0, 8)}) are a multiple of ${v.house_edge_divisor}: the round ends at 1.00x. That is the house edge.`));
                    }
                    const $check = el('p', { class: 'pf-check', 'aria-live': 'polite' });
                    parts.push($check);
                    checkInBrowser(f).then((ok) => {
                        if (my !== token || ok === null) return;
                        $check.classList.add(ok ? 'ok' : 'bad');
                        $check.textContent = ok
                            ? '✓ Checked in your browser: the seed matches the hash published before the round, and gives this result.'
                            : '✗ Your browser could not confirm this result from the seeds above.';
                    });
                }
                parts.push(el('details', { class: 'pf-how' },
                    el('summary', {}, 'How is the result worked out?'),
                    el('ol', {},
                        el('li', {}, 'Before betting opens we publish the SHA-256 of the round’s server seed, so the seed (and the result) is fixed before anyone bets.'),
                        el('li', {}, 'Once the round is over we reveal the seed: its SHA-256 matches the published one.'),
                        el('li', {}, 'HMAC-SHA256 of “client seed:nonce”, keyed with the server seed, gives the hash above.'),
                        el('li', {}, `If its first 8 hex digits are a multiple of ${v?.house_edge_divisor ?? 'N'} (the house edge), the round ends at 1.00x.`),
                        el('li', {}, 'Otherwise its first 13 hex digits, as a decimal h, give the result: floor((100 × 2^52 − h) / (2^52 − h)) / 100.'))));
                $body.replaceChildren(...parts);
            }

            async function openRound(uuid, { back = false } = {}) {
                if (!UUID.test(uuid ?? '')) return;
                const my = show('round', { back });
                const cached = settledRounds.get(uuid);
                if (cached) return renderRound(cached, my);

                head('Round');
                $body.replaceChildren(note('Loading…'));
                let d = null;
                try {
                    const r = await fetch(`${ROUNDS_URL}/${uuid}`, { headers: { Accept: 'application/json' } });
                    d = r.ok ? await r.json() : null;
                } catch (_) { /* offline: said below */ }
                if (my !== token) return;
                if (!d) {
                    $body.replaceChildren(note('Couldn’t load this round.', el('button', { class: 'sheet-retry', type: 'button', onclick: () => openRound(uuid, { back }) }, 'Retry')));
                    return;
                }
                if (d.state === 'settled' && d.fairness?.server_seed) settledRounds.set(uuid, d);
                renderRound(d, my);
            }

            // ----- My bets -----
            function renderBets() {
                const list = bets.list ?? [];
                if (list.length === 0) {
                    $body.replaceChildren(note('No bets yet. Your bets in this game will show up here.'));
                    return;
                }
                const currency = list[0].currency;
                const one = list.every((b) => b.currency === currency);
                const amount = (b, v) => one ? bets.money(v) : `${bets.money(v)} ${b.currency}`;
                const chevron = '<svg viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4.5 2.5 8 6l-3.5 3.5"/></svg>';
                const rows = list.map((b) => {
                    const won = b.status === 'won';
                    const cashout = won ? chip(b.multiplier)
                        : b.status === 'placed' ? el('span', { class: 'is-muted' }, 'in play')
                        : b.status === 'refunded' ? el('span', { class: 'is-muted' }, 'refunded')
                        : el('span', { class: 'is-muted' }, '—');
                    const win = won ? el('span', { class: 'is-win' }, amount(b, b.payout_amount))
                        : el('span', { class: 'is-muted' }, b.status === 'lost' ? amount(b, '0') : '—');
                    const when = isToday(b.placed_at) ? el('span', { class: 'bets-when' }, clock(b.placed_at))
                        : el('span', { class: 'bets-when' }, clock(b.placed_at), el('small', {}, new Date(b.placed_at).toLocaleDateString([], { day: 'numeric', month: 'short' })));
                    const opens = UUID.test(b.round_uuid ?? '');
                    return el('button', {
                        type: 'button',
                        class: `bets-row${won ? ' is-won' : ''}`,
                        disabled: !opens,
                        title: opens ? 'Round details' : null,
                        onclick: opens ? () => openRound(b.round_uuid, { back: true }) : null,
                    }, when, el('span', {}, amount(b, b.amount)), el('span', {}, cashout), win, el('span', { html: opens ? chevron : '' }));
                });
                $body.replaceChildren(el('div', { class: 'bets-list' },
                    el('div', { class: 'bets-row is-head', 'aria-hidden': 'true' },
                        el('span', {}, 'Time'), el('span', {}, one ? `Bet ${currency}` : 'Bet'), el('span', {}, 'Cash out'), el('span', {}, one ? `Win ${currency}` : 'Win'), el('span')),
                    rows));
            }
            function failBets() {
                $body.replaceChildren(note('Couldn’t load your bets.', el('button', { class: 'sheet-retry', type: 'button', onclick: () => { $body.replaceChildren(note('Loading…')); askForBets(); } }, 'Retry')));
            }
            function askForBets() {
                if (!bets.request) return;
                bets.pending = true;
                clearTimeout(bets.timer);
                bets.timer = setTimeout(() => {
                    bets.pending = false;
                    if (view === 'bets' && bets.list === null) failBets();
                }, 8000);
                bets.request();
            }
            function openBets() {
                if (!bets.request) return;
                show('bets');
                head('My bets');
                if (bets.list === null) $body.replaceChildren(note('Loading…'));
                else renderBets();
                askForBets();
            }

            return {
                openRound,
                openBets,
                /** The host answers bet_history: offer My bets. request() asks it for them. */
                enableBets({ request, money }) {
                    bets.request = request;
                    if (money) bets.money = money;
                },
                /** A bet_history_result from the host. */
                receiveBets(payload) {
                    bets.pending = false;
                    clearTimeout(bets.timer);
                    if (payload?.success) bets.list = Array.isArray(payload.bets) ? payload.bets : [];
                    if (view !== 'bets') return;
                    if (payload?.success || bets.list !== null) renderBets();
                    else failBets();
                },
                /** Something changed the player's bets (a bet, a cashout, a round over). */
                betsChanged() {
                    if (view === 'bets' && !bets.pending) askForBets();
                },
            };
        })();

        // ---------- All bets: every player's bet in the round, live ----------
        // Rows come from round.bets broadcasts. A round the page joins late, and the
        // previous one when its tab opens, are fetched once (GET /v1/rounds/{uuid}/bets).
        const liveBets = (function livePanel() {
            const $panel = document.getElementById('live-bets');
            const $btn = document.getElementById('live-btn');
            if (!$panel || !$btn) return { seen() {}, update() {}, crashed() {}, mine() {} };

            const START = @json($liveRounds ?? null);
            const ROUNDS_URL = @json(url('/v1/rounds'));
            const MAX_ROWS = 100; // the biggest bets; the counts and totals cover all of them
            const OPEN_KEY = 'orange.ui.livebets';
            const $rows = document.getElementById('lb-rows');
            const $count = document.getElementById('lb-count');
            const $total = document.getElementById('lb-total');
            const $totalLabel = document.getElementById('lb-total-label');
            const $progress = document.getElementById('lb-progress');
            const $result = document.getElementById('lb-result');
            const $headBet = document.getElementById('lb-head-bet');
            const $headWin = document.getElementById('lb-head-win');
            const $tabs = [...$panel.querySelectorAll('.lb-tab')];
            const docked = window.matchMedia('(min-width: 1100px) and (min-height: 600px)');

            const rounds = new Map(); // uuid → { rows: Map<bet id, row>, crashPoint, crashed, loaded, loading }
            let current = null;
            let previous = null;
            let tab = 'current';
            const mine = new Set();   // this player's bet ids
            const nodes = new Map();  // bet id → its row element, reused across renders
            const flashes = new Set(); // bet ids just cashed out
            let frame = 0;

            // Money with two decimals, cut (never rounded up), and thousands separators.
            function fmt(value) {
                const s = typeof value === 'number' ? value.toFixed(2) : String(value);
                const m = /^(-?)(\d+)(?:\.(\d*))?$/.exec(s);
                if (!m) return s;
                return `${m[1]}${m[2].replace(/\B(?=(\d{3})+(?!\d))/g, ',')}.${((m[3] || '') + '00').slice(0, 2)}`;
            }
            const hue = (s) => [...s].reduce((h, c) => (h * 31 + c.charCodeAt(0)) % 360, 7);

            function round(uuid) {
                if (!rounds.has(uuid)) rounds.set(uuid, { rows: new Map(), crashPoint: null, crashed: false, loaded: false, loading: false });
                return rounds.get(uuid);
            }
            function merge(r, row) {
                const before = r.rows.get(row.id);
                if (before?.multiplier && !row.multiplier) return; // a late copy from before the cash-out
                if (before && !before.multiplier && row.multiplier) flashes.add(row.id);
                r.rows.set(row.id, row);
            }
            async function load(uuid) {
                const r = round(uuid);
                if (r.loaded || r.loading) return;
                r.loading = true;
                try {
                    const res = await fetch(`${ROUNDS_URL}/${uuid}/bets`, { headers: { Accept: 'application/json' } });
                    if (res.ok) {
                        const data = await res.json();
                        for (const row of data.bets ?? []) merge(r, row);
                        if (data.state === 'crashed' || data.state === 'settled') r.crashed = true;
                        r.loaded = true;
                    }
                } catch (_) { /* offline: tried again the next time it's shown */ }
                r.loading = false;
                flashes.clear();
                schedule();
            }

            // A round the page hadn't seen is the new current one; the current one becomes
            // the previous. `fresh`: its betting just opened, so there's nothing to fetch.
            function seen(uuid, { fresh = false } = {}) {
                if (!UUID.test(uuid ?? '') || uuid === current || uuid === previous) return;
                if (current) previous = current; // else the page's start value stays
                current = uuid;
                if (fresh) round(uuid).loaded = true;
                for (const id of rounds.keys()) {
                    if (id === current || id === previous) continue;
                    for (const betId of rounds.get(id).rows.keys()) nodes.delete(betId);
                    rounds.delete(id);
                }
                schedule();
            }

            function schedule() {
                if (!frame) frame = requestAnimationFrame(render);
            }
            function rowNode(b, crashed, one) {
                let n = nodes.get(b.id);
                if (!n) {
                    n = document.createElement('div');
                    n.setAttribute('role', 'listitem');
                    n.innerHTML = '<span class="lb-player"><i></i><span></span></span><span></span><span class="lb-x"></span><span class="lb-win"></span>';
                    const [$who, , , ] = n.children;
                    $who.firstChild.textContent = (b.player || '?').slice(0, 1).toUpperCase();
                    $who.firstChild.style.setProperty('--h', hue(b.player || ''));
                    $who.lastChild.textContent = b.player;
                    nodes.set(b.id, n);
                }
                const won = !!b.multiplier;
                const key = [won, crashed, mine.has(b.id), one].join('|');
                if (n.dataset.key !== key) {
                    n.dataset.key = key;
                    n.className = `lb-row${won ? ' is-won' : ''}${crashed && !won ? ' is-lost' : ''}${mine.has(b.id) ? ' is-mine' : ''}`;
                    const [, $bet, $x, $win] = n.children;
                    $bet.textContent = one === null ? `${fmt(b.amount)} ${b.currency}` : fmt(b.amount);
                    $x.replaceChildren(...(won ? [Object.assign(document.createElement('span'), { className: `x-chip ${tierClassFor(Number(b.multiplier))}`, textContent: `${Number(b.multiplier).toFixed(2)}x` })] : []));
                    $win.textContent = won ? (one === null ? `${fmt(b.payout)} ${b.currency}` : fmt(b.payout)) : '';
                }
                if (flashes.delete(b.id)) {
                    n.classList.remove('flash');
                    void n.offsetWidth; // restart the animation
                    n.classList.add('flash');
                }
                return n;
            }
            function render() {
                frame = 0;
                if (!$panel.hidden) renderPanel();
                renderMini();
            }
            function renderPanel() {
                const uuid = tab === 'current' ? current : previous;
                const r = uuid ? round(uuid) : null;
                if (r && !r.loaded) load(uuid);

                const list = r ? [...r.rows.values()] : [];
                list.sort((a, b) => Number(b.amount) - Number(a.amount) || a.id - b.id);
                const won = list.filter((b) => b.multiplier);
                const currencies = [...new Set(list.map((b) => b.currency))];
                const one = currencies.length <= 1 ? (currencies[0] ?? '') : null; // null: several
                const totals = new Map();
                for (const b of won) totals.set(b.currency, (totals.get(b.currency) ?? 0) + Number(b.payout));

                $count.textContent = `${won.length}/${list.length}`;
                $progress.style.width = list.length ? `${(won.length / list.length) * 100}%` : '0';
                $headBet.textContent = one ? `Bet ${one}` : 'Bet';
                $headWin.textContent = one ? `Win ${one}` : 'Win';
                $total.textContent = one !== null
                    ? fmt(totals.get(one) ?? 0)
                    : [...totals].map(([c, v]) => `${fmt(v)} ${c}`).join(' · ') || '0.00';
                $totalLabel.textContent = one ? `Total win ${one}` : 'Total win';
                const cp = tab === 'previous' ? r?.crashPoint ?? history.find((h) => h.uuid === uuid)?.cp : null;
                $result.hidden = !cp;
                if (cp) {
                    $result.replaceChildren(Object.assign(document.createElement('span'), { className: `x-chip ${tierClassFor(cp)}`, textContent: `${Number(cp).toFixed(2)}x` }),
                        Object.assign(document.createElement('small'), { textContent: 'Result' }));
                }

                const children = list.slice(0, MAX_ROWS).map((b) => rowNode(b, !!r?.crashed, one));
                if (list.length === 0) {
                    children.push(Object.assign(document.createElement('div'), {
                        className: 'lb-note',
                        textContent: r && !r.loaded ? 'Loading…' : tab === 'current' ? 'No bets yet in this round.' : 'No bets in the previous round.',
                    }));
                } else if (list.length > MAX_ROWS) {
                    children.push(Object.assign(document.createElement('div'), { className: 'lb-note', textContent: `+${list.length - MAX_ROWS} smaller bets` }));
                }
                $rows.replaceChildren(...children);
            }

            // Where the panel isn't docked: the current round's bets as circles down the
            // right edge (the player's own first, then the biggest stakes; as many as fit,
            // then "+N"). One that cashes out steps inward, its multiplier beside it.
            const $mini = document.getElementById('lb-mini');
            const $miniMore = document.getElementById('lb-mini-more');
            const DOT = 28;
            const DOT_GAP = 6;
            const DOT_SLOTS = 9; // at most 8 circles and "+N": a light touch, the panel has the rest
            const dots = new Map(); // bet id → its circle
            function renderMini() {
                const shownMini = !docked.matches && $panel.hidden;
                $mini.hidden = !shownMini;
                if (!shownMini) return;
                const r = current ? round(current) : null;
                if (r && !r.loaded) load(current);

                const list = r ? [...r.rows.values()] : [];
                list.sort((a, b) => Number(mine.has(b.id)) - Number(mine.has(a.id)) || Number(b.amount) - Number(a.amount) || a.id - b.id);
                const fits = Math.max(0, Math.min(DOT_SLOTS, Math.floor(($mini.clientHeight + DOT_GAP) / (DOT + DOT_GAP))));
                const shown = list.length > fits ? list.slice(0, Math.max(0, fits - 1)) : list;

                const keep = new Set();
                shown.forEach((b, i) => {
                    let n = dots.get(b.id);
                    if (!n) {
                        n = document.createElement('div');
                        n.className = 'lb-dot is-new';
                        n.innerHTML = '<i></i><b></b>';
                        n.firstChild.textContent = (b.player || '?').slice(0, 1).toUpperCase();
                        n.firstChild.style.setProperty('--h', hue(b.player || ''));
                        n.addEventListener('animationend', () => n.classList.remove('is-new'), { once: true });
                        $mini.insertBefore(n, $miniMore);
                        dots.set(b.id, n);
                    }
                    n.style.setProperty('--y', `${i * (DOT + DOT_GAP)}px`);
                    if (b.multiplier) n.lastChild.textContent = `${Number(b.multiplier).toFixed(2)}x`;
                    n.classList.toggle('is-out', !!b.multiplier);
                    n.classList.toggle('is-lost', !!r?.crashed && !b.multiplier);
                    n.classList.toggle('is-mine', mine.has(b.id));
                    keep.add(b.id);
                });
                for (const [id, n] of dots) {
                    if (keep.has(id)) continue;
                    dots.delete(id);
                    n.classList.add('is-gone');
                    setTimeout(() => n.remove(), 400);
                }
                $miniMore.hidden = list.length === shown.length;
                $miniMore.firstChild.textContent = `+${list.length - shown.length}`;
                $miniMore.style.setProperty('--y', `${shown.length * (DOT + DOT_GAP)}px`);
            }
            $mini.addEventListener('click', () => setOpen(true));
            window.addEventListener('resize', schedule);

            function setOpen(open, remember = false) {
                $panel.hidden = !open;
                $btn.setAttribute('aria-expanded', String(open));
                // Only the docked panel's state is kept: over the game it starts closed.
                if (remember && docked.matches) {
                    try { localStorage.setItem(OPEN_KEY, open ? '1' : '0'); } catch (_) {}
                }
                schedule(); // the panel, or the circles in its place
            }
            function defaultOpen() {
                let stored = null;
                try { stored = localStorage.getItem(OPEN_KEY); } catch (_) {}
                return docked.matches && stored !== '0';
            }
            $btn.addEventListener('click', () => setOpen($panel.hidden, true));
            document.getElementById('live-close').addEventListener('click', () => setOpen(false, true));
            docked.addEventListener('change', () => setOpen(defaultOpen()));
            for (const $t of $tabs) {
                $t.addEventListener('click', () => {
                    tab = $t.dataset.tab;
                    for (const $o of $tabs) $o.setAttribute('aria-selected', String($o === $t));
                    $rows.scrollTop = 0;
                    schedule();
                });
            }

            if (UUID.test(START?.current ?? '')) current = START.current;
            if (UUID.test(START?.previous ?? '')) previous = START.previous;
            setOpen(defaultOpen());

            return {
                seen,
                /** A round.bets message. */
                update(e) {
                    seen(e.round_uuid);
                    if (e.round_uuid !== current && e.round_uuid !== previous) return;
                    const r = round(e.round_uuid);
                    for (const row of e.bets ?? []) merge(r, row);
                    schedule();
                },
                crashed(uuid, cp) {
                    if (uuid !== current && uuid !== previous) return;
                    const r = round(uuid);
                    r.crashed = true;
                    r.crashPoint = cp;
                    schedule();
                },
                /** The player's own bet, marked in the list. */
                mine(betId) {
                    mine.add(Number(betId));
                    schedule();
                },
            };
        })();

        const log = (msg) => {
            const line = document.createElement('div');
            line.textContent = `${new Date().toLocaleTimeString()}  ${msg}`;
            $log.prepend(line);
            while ($log.children.length > 6) $log.lastChild.remove();
        };

        // How much of the screen's bottom the multiplier row takes up: the diving
        // sub and the docked All bets panel stay clear of it (see the .sub-wrap diving
        // rules and .live-bets).
        const $center = document.querySelector('.center');
        const hudRoom = new ResizeObserver(() => {
            const room = $stage.getBoundingClientRect().bottom - $center.getBoundingClientRect().top;
            document.documentElement.style.setProperty('--hud-room', `${Math.round(room)}px`);
        });
        hudRoom.observe($stage);
        hudRoom.observe($center);

        let stageKey = '';
        function setStage(state) {
            stageKey = state.join(' ');
            $stage.classList.remove('waiting', 'betting', 'running', 'deep', 'abyss', 'critical', 'crashed');
            for (const s of state) $stage.classList.add(s);
        }

        // Dive progress 0..1 on a log scale up to config('crash.depth_max_multiplier'),
        // so depth, pressure and hull keep moving on any realistic round instead of
        // maxing out early. With 1000x: 2x ≈ 1.1 km, 10x ≈ 3.7 km, 100x ≈ 7.3 km.
        const DIVE_MAX_X = @json((float) config('crash.depth_max_multiplier', 1000));
        const diveProgress = (m) => Math.min(1, Math.max(0, Math.log(m) / Math.log(DIVE_MAX_X)));
        const $depthMeter = document.getElementById('depth-meter');
        const $backdrop = document.querySelector('.backdrop');
        const $seabedFog = document.querySelector('.seabed-fog');
        const $walls = document.querySelector('.walls');
        const $depthVal = document.getElementById('depth-value');
        const $depthZone = document.getElementById('depth-zone');
        const MAX_DEPTH_M = 10994;
        // How far the camera has descended, in % of the screen height, at multiplier m.
        // Distance grows with m - 1, so the speed grows with the multiplier itself (it
        // rises by e^(g·t)): ~11%/s at 1x, ~21 at 2x, ~42 at 4x. From DESCENT_CAP_X on
        // the speed holds (~74%/s) so the looping walls never strobe.
        const DESCENT_PER_X = 176;
        const DESCENT_CAP_X = 7;
        function descent(m) {
            const x = Math.max(1, m);
            if (x <= DESCENT_CAP_X) return DESCENT_PER_X * (x - 1);
            return DESCENT_PER_X * (DESCENT_CAP_X - 1 + DESCENT_CAP_X * Math.log(x / DESCENT_CAP_X));
        }

        // Moves the backdrop and the trench walls to the descent at multiplier m: the
        // walls fall by it, the reef (further away) by 80% of it until it has left the
        // screen; the walls hang from the reef's lower edge and scroll inside that.
        const $scene = document.querySelector('.scene');
        const $wallStrips = [...document.querySelectorAll('.wall b')];
        let tileHeights = null; // px per repeat of each strip's art
        function tiles() {
            tileHeights ??= $wallStrips.map((b) => b.clientWidth * (parseFloat(getComputedStyle(b.parentNode).getPropertyValue('--tile-ratio')) || 0));
            return tileHeights;
        }
        window.addEventListener('resize', () => { tileHeights = null; });
        new MutationObserver(() => { tileHeights = null; })
            .observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });
        // The jellyfish hang in the water, so the sub passes them: they rise with the
        // descent (a little slower than the walls, being further off) and, once above
        // the screen, come round again from below it, out of sight. `translate`, so
        // their bob (the float animation, on `transform`) carries on.
        const $jellies = [...document.querySelectorAll('.jelly')];
        const JELLY_PARALLAX = 0.9;
        const JELLY_WRAP_MARGIN = 140; // px beyond each edge, more than a jelly is tall
        function setScenery(m) {
            const h = $scene?.clientHeight || window.innerHeight;
            const fall = descent(m) / 100 * h;
            const rise = Math.min(1.25 * h, fall * 0.8);
            const lift = `translate3d(0, ${(-rise).toFixed(2)}px, 0)`;
            if ($backdrop) $backdrop.style.transform = lift;
            // The floor sinks into the water as soon as the reef starts rising, all the
            // way by the time it has risen an eighth of the screen.
            const sunk = Math.min(1, rise / (0.125 * h)).toFixed(3);
            if ($seabedFog) $seabedFog.style.opacity = sunk;
            if ($walls) $walls.style.setProperty('--walls-in', sunk); // where the cliffs reach into the painting
            if ($walls) $walls.style.transform = lift;
            tiles().forEach((tile, i) => {
                const y = tile > 0 ? (fall - rise) % tile : 0;
                $wallStrips[i].style.transform = `translate3d(0, ${(-y).toFixed(2)}px, 0)`;
            });
            const span = h + 2 * JELLY_WRAP_MARGIN;
            for (const $j of $jellies) {
                const home = parseFloat($j.style.top) / 100 * h; // its place at the surface
                const y = ((home - fall * JELLY_PARALLAX + JELLY_WRAP_MARGIN) % span + span) % span - JELLY_WRAP_MARGIN;
                $j.style.translate = `0 ${(y - home).toFixed(2)}px`;
            }
        }

        // The descent runs on its own clock, not on the ticks: ticks carry multipliers
        // rounded to 0.01 (early in a dive several in a row repeat the same value)
        // and can arrive late, which made the scenery stutter. The clock computes
        // m = e^(g·t) every frame; ticks only keep t in step with the server.
        const descentClock = { g: @json((float) config('crash.growth_rate', 0.06)), start: 0, target: 0, synced: 0, on: false, raf: 0 };
        function descentFrame(now) {
            if (!descentClock.on) return;
            // No tick for a while (connection lost): hold still rather than run away.
            if (now - descentClock.synced > 2500) { descentClock.on = false; return; }
            // Ease toward the server's clock by at most 0.5 ms a frame (a ~3% speed
            // change nobody sees), never in one step.
            const gap = descentClock.target - descentClock.start;
            descentClock.start += Math.max(-0.5, Math.min(0.5, gap * 0.05));
            setScenery(Math.exp(descentClock.g * Math.max(0, now - descentClock.start) / 1000));
            descentClock.raf = requestAnimationFrame(descentFrame);
        }
        function syncDescent(elapsedSeconds, growthRate) {
            if (growthRate > 0) descentClock.g = growthRate;
            const now = performance.now();
            const measured = now - Math.max(0, Number(elapsedSeconds) || 0) * 1000;
            descentClock.synced = now;
            if (!descentClock.on) {
                descentClock.start = descentClock.target = measured;
                descentClock.on = true;
                descentClock.raf = requestAnimationFrame(descentFrame);
                return;
            }
            // A gap of over a second (a device waking from sleep) is caught up at once;
            // otherwise the target is averaged so network jitter cancels out, and the
            // frames ease toward it.
            if (Math.abs(measured - descentClock.target) > 1000) {
                descentClock.start = descentClock.target = measured;
            } else {
                descentClock.target += (measured - descentClock.target) * 0.1;
            }
        }
        function stopDescent() {
            descentClock.on = false;
            cancelAnimationFrame(descentClock.raf);
        }
        const depthFmt = new Intl.NumberFormat('en-US');
        function setDepth(m) {
            const p = diveProgress(m);
            const metres = Math.round(p * MAX_DEPTH_M);
            $depthMeter.style.setProperty('--p', p.toFixed(4));
            $depthVal.textContent = depthFmt.format(metres) + ' m';
            $depthZone.textContent = metres === 0 ? 'SURFACE'
                : metres < 200 ? 'SUNLIGHT ZONE'
                : metres < 1000 ? 'TWILIGHT ZONE'
                : metres < 4000 ? 'MIDNIGHT ZONE'
                : metres < 6000 ? 'ABYSSAL ZONE'
                : 'HADAL ZONE';
        }

        function updateMultiplierUI(m) {
            $mult.textContent = m.toFixed(2) + 'x';
            setDepth(m);
            // Same dive progress as the depth meter: ~1 bar per 10 m of water.
            const norm = diveProgress(m);
            const pressure = Math.round(1 + norm * MAX_DEPTH_M / 10);
            const hull = Math.max(0, Math.round(100 - norm * 100));
            $pressVal.textContent = pressure;
            $hullVal.textContent = hull;
            setGauges(norm, hull, m >= 8 ? 'crit' : (m >= 3 ? 'warn' : 'ok'));

            const tiers = ['running'];
            if (m >= 8)  tiers.push('critical');
            else if (m >= 3.5) tiers.push('abyss');
            else if (m >= 1.8) tiers.push('deep');
            // Runs every animation frame — only touch the stage classes on a tier change.
            if (tiers.join(' ') !== stageKey) setStage(tiers);
        }

        // ---------- Countdown helper (state text only) ----------
        let countdownTimer = null;
        function cancelCountdown() {
            if (countdownTimer) { clearInterval(countdownTimer); countdownTimer = null; }
        }
        function startCountdown(seconds, label, onZero) {
            cancelCountdown();
            let remaining = Math.max(0, Math.floor(seconds));
            const render = () => {
                if (remaining <= 0) {
                    cancelCountdown();
                    if (onZero) onZero();
                    return;
                }
                $state.textContent = `${label} ${remaining}s`;
                remaining--;
            };
            render();
            countdownTimer = setInterval(render, 1000);
        }

        // Ticks land every ~100 ms; glide the readout from the shown value to each
        // new tick over the measured interval. It never runs ahead of the last
        // tick, so it can't overshoot the crash point.
        const tween = { from: 1, to: 1, start: 0, dur: 100, lastAt: 0, raf: 0 };
        function tweenValue(now) {
            const k = Math.min(1, (now - tween.start) / tween.dur);
            return tween.from + (tween.to - tween.from) * k;
        }
        function tweenFrame(now) {
            updateMultiplierUI(tweenValue(now));
            tween.raf = now - tween.start < tween.dur ? requestAnimationFrame(tweenFrame) : 0;
        }
        function tweenMultiplier(m) {
            const now = performance.now();
            const shown = tween.lastAt ? tweenValue(now) : m;
            tween.dur = tween.lastAt ? Math.min(250, Math.max(60, now - tween.lastAt)) : 100;
            Object.assign(tween, { from: shown, to: m, start: now, lastAt: now });
            if (!tween.raf) tween.raf = requestAnimationFrame(tweenFrame);
        }
        function stopTween(m) {
            cancelAnimationFrame(tween.raf);
            Object.assign(tween, { from: m, to: m, lastAt: 0, raf: 0 });
        }

        function resetIdleVisuals() {
            stopTween(1);
            stopDescent();
            setScenery(1); // glides back up to the surface (the betting state's transition)
            setDepth(1);
            rebuildDebris(); // fresh vectors for the next crash, built while nothing is animating
            $mult.textContent = '1.00x';
            $pressVal.textContent = '0';
            $hullVal.textContent = '100';
            setGauges(0, 100, 'ok');
        }

        function attachEcho() {
            log('Echo ready, joining channel: crash');
            window.Echo.channel('crash')
                .listen('.round.betting_opened', (e) => {
                    cancelCountdown();
                    setStage(['betting']);
                    resetIdleVisuals();
                    $cashout.disabled = true;
                    $cashoutHint.textContent = 'awaiting dive';
                    log(`betting_opened ${e.round_uuid.slice(0,8)}…`);
                    liveBets.seen(e.round_uuid, { fresh: true });
                    startCountdown(e.betting_seconds || 10, 'Diving in', () => {
                        $state.textContent = 'Pressurising…';
                    });
                })
                .listen('.round.started', (e) => {
                    cancelCountdown();
                    setStage(['running']);
                    $state.textContent = 'DIVING';
                    $cashout.disabled = false;
                    $cashoutHint.textContent = 'cash out at current x';
                    syncDescent(0, Number(e.growth_rate));
                    liveBets.seen(e.round_uuid);
                    log(`dive started · growth ${e.growth_rate}/s`);
                })
                .listen('.round.tick', (e) => {
                    if ($stage.classList.contains('crashed')) return;
                    const m = Number(e.multiplier ?? e.data?.multiplier ?? NaN);
                    if (!Number.isFinite(m)) return;
                    tweenMultiplier(m);
                    syncDescent(e.elapsed_seconds, NaN);
                    liveBets.seen(e.round_uuid);
                    // Tick implies running. If user opened the page mid-round and
                    // missed betting_opened/started, sync state text + cashout button.
                    const elapsed = Number(e.elapsed_seconds ?? 0).toFixed(1);
                    $state.textContent = `DIVING · ${elapsed}s`;
                    if ($cashout.disabled) {
                        $cashout.disabled = false;
                        $cashoutHint.textContent = 'cash out at current x';
                    }
                })
                .listen('.round.crashed', (e) => {
                    try {
                        log('💥 raw: ' + JSON.stringify(e));
                        const cp = Number(e.crash_point ?? e.crashPoint ?? e.data?.crash_point ?? NaN);
                        stopTween(Number.isFinite(cp) ? cp : tween.to);
                        stopDescent(); // the scenery holds where the sub imploded
                        setStage(['crashed', 'critical']);
                        if (Number.isFinite(cp)) {
                            $mult.textContent = cp.toFixed(2) + 'x';
                            setDepth(cp);
                            $state.textContent = `IMPLODED @ ${cp.toFixed(2)}x`;
                            log(`💥 imploded @ ${cp.toFixed(2)}x`);
                            pushHistory(cp, e.round_uuid);
                            liveBets.crashed(e.round_uuid, cp);
                        } else {
                            $state.textContent = 'IMPLODED (no crash_point)';
                            log('⚠ crash_point missing in payload');
                        }
                        $cashout.disabled = true;
                        $cashoutHint.textContent = 'round over';
                    } catch (err) {
                        log('⚠ crashed handler threw: ' + err.message);
                    }
                })
                .listen('.round.settled', (e) => {
                    log(`settled · seed ${e.server_seed?.slice(0,12)}…`);
                    sheet.betsChanged(); // the round's bets are settled now
                    // Hold the IMPLODED splash for ~1.2s so the player can read
                    // the crash point, then snap back to a neutral idle and
                    // start a visible countdown until the next betting window.
                    setTimeout(() => {
                        if ($stage.classList.contains('betting') ||
                            $stage.classList.contains('running')) return;
                        setStage(['waiting']);
                        resetIdleVisuals();
                        const wait = Number(e.next_round_in_seconds ?? 5);
                        startCountdown(wait, 'Next dive in', () => {
                            $state.textContent = 'Awaiting next dive…';
                        });
                    }, 1200);
                })
                .listen('.round.bets', (e) => liveBets.update(e));
        }

        if (window.Echo) attachEcho();
        else window.addEventListener('DOMContentLoaded', () => setTimeout(attachEcho, 250));

        $cashout.addEventListener('click', () => {
            $cashout.disabled = true;
            $cashoutHint.textContent = 'visual demo only — use crash:bet CLI';
            log('🎯 cash-out tapped (visual demo)');
        });

        // ---- Fullscreen toggle -------------------------------------------------
        (function fullscreenButton() {
            const $btn = document.getElementById('fs-btn');
            if (!$btn) return;

            const isEmbedded = window.self !== window.top;
            // On its own, the page can't go fullscreen where the browser has no Fullscreen
            // API for pages (iPhones: video only), so the button goes. Embedded, the operator
            // page decides (fullscreen, or where there is none, expanding the game).
            if (!isEmbedded && !(document.fullscreenEnabled || document.webkitFullscreenEnabled)) {
                document.documentElement.classList.add('no-fullscreen');
                fitHistory();
                return;
            }

            function isFullscreen() {
                return !!(document.fullscreenElement || document.webkitFullscreenElement);
            }

            function syncState() {
                document.body.classList.toggle('is-fullscreen', isFullscreen());
            }

            $btn.addEventListener('click', () => {
                // If we're inside an iframe, ask the parent to fullscreen the iframe
                // (the child can't reliably fullscreen its own document tree).
                // Important: `document.fullscreenElement` inside this iframe stays
                // null even when the iframe element itself is fullscreen on the
                // parent page — so use the parent-mirrored `is-fullscreen` class
                // on body as the source of truth here.
                if (isEmbedded) {
                    const parentSaysFullscreen = document.body.classList.contains('is-fullscreen');
                    try {
                        window.parent.postMessage(
                            { type: parentSaysFullscreen ? 'exit_fullscreen' : 'enter_fullscreen' },
                            '*'
                        );
                    } catch (_) { /* ignore */ }
                    return;
                }

                const el = document.documentElement;
                if (!isFullscreen()) {
                    (el.requestFullscreen || el.webkitRequestFullscreen)?.call(el);
                } else {
                    (document.exitFullscreen || document.webkitExitFullscreen)?.call(document);
                }
            });

            // Mirror parent-driven fullscreen so the icon flips inside the iframe too.
            window.addEventListener('message', (event) => {
                if (event.data?.type === 'fullscreen_state') {
                    document.body.classList.toggle('is-fullscreen', !!event.data.payload?.active);
                }
            });

            document.addEventListener('fullscreenchange', syncState);
            document.addEventListener('webkitfullscreenchange', syncState);
        })();

        // ---- Theme picker ------------------------------------------------------
        // Skins are `:root[data-theme]` token blocks in the <style> above. The
        // <head> bootstrap already applied the saved skin before first paint;
        // this only wires the dropdown, persistence and the active marker.
        (function themePicker() {
            const $btn  = document.getElementById('theme-btn');
            const $menu = document.getElementById('theme-menu');
            if (!$btn || !$menu) return;

            const THEME_KEY = 'orange.ui.theme';
            const $opts = [...$menu.querySelectorAll('.theme-opt')];

            function current() {
                return document.documentElement.getAttribute('data-theme') || $opts[0]?.dataset.theme;
            }

            function syncActive() {
                const active = current();
                for (const $o of $opts) {
                    const on = $o.dataset.theme === active;
                    $o.classList.toggle('is-active', on);
                    $o.setAttribute('aria-checked', on ? 'true' : 'false');
                }
            }

            function apply(theme) {
                document.documentElement.setAttribute('data-theme', theme);
                try { localStorage.setItem(THEME_KEY, theme); } catch (_) {}
                syncActive();
            }

            function open()  { $menu.hidden = false; $btn.setAttribute('aria-expanded', 'true'); }
            function close() { $menu.hidden = true;  $btn.setAttribute('aria-expanded', 'false'); }

            $btn.addEventListener('click', (e) => {
                e.stopPropagation();
                if ($menu.hidden) open(); else close();
            });
            $menu.addEventListener('click', (e) => e.stopPropagation());
            for (const $o of $opts) {
                $o.addEventListener('click', () => { apply($o.dataset.theme); close(); });
            }
            document.addEventListener('click', () => { if (!$menu.hidden) close(); });
            document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && !$menu.hidden) close(); });

            syncActive();
        })();

        // ----------------------------------------------------------------------
        // Audio engine — procedural SFX + background ambience, per theme: the
        // Gothic ("burton") and Jackpot Reef ("jackpot") skins play their own
        // packs (js/orange/sound-*.js), every other theme the underwater set
        // below. No external assets.
        // AudioContext is gated behind first user gesture (browser autoplay
        // policy); a persistent mute toggle lives top-right.
        // ----------------------------------------------------------------------
        const audio = (function () {
            let ctx, master, musicTimer, musicStep = 0, musicEnabled = true, started = false;
            const STORAGE_KEY = 'orange.audio.muted';
            let themeAmbience = null; // the running ambience of a theme pack, if any
            const PACKS = { burton: 'gothic', jackpot: 'jackpot' };
            const packs = {};

            /** The current theme's sound pack, or null for the built-in underwater set. */
            function themePack() {
                const name = PACKS[document.documentElement.getAttribute('data-theme')];
                if (!name || !window.OrangeSound?.[name]) return null;
                init();
                return packs[name] ??= window.OrangeSound[name](ctx, master);
            }

            function init() {
                if (ctx) return;
                ctx = new (window.AudioContext || window.webkitAudioContext)();
                master = ctx.createGain();
                master.gain.value = 0.35;
                master.connect(ctx.destination);
            }

            function midi(n) { return 440 * Math.pow(2, (n - 69) / 12); }

            // One-shot synth: type + freq + envelope + duration
            function tone({ freq, type = 'square', attack = 0.005, decay = 0.08, sustain = 0.0, release = 0.05, gain = 0.18, dur = 0.12, slideTo = null }) {
                if (!ctx) return;
                const t0 = ctx.currentTime;
                const osc = ctx.createOscillator();
                const g = ctx.createGain();
                osc.type = type;
                osc.frequency.setValueAtTime(freq, t0);
                if (slideTo) osc.frequency.exponentialRampToValueAtTime(slideTo, t0 + dur);

                const peak = gain;
                g.gain.setValueAtTime(0.0001, t0);
                g.gain.exponentialRampToValueAtTime(peak, t0 + attack);
                g.gain.exponentialRampToValueAtTime(Math.max(0.0001, peak * sustain + 0.0001), t0 + attack + decay);
                g.gain.exponentialRampToValueAtTime(0.0001, t0 + Math.max(attack + decay + release, dur));

                osc.connect(g).connect(master);
                osc.start(t0);
                osc.stop(t0 + dur + 0.05);
            }

            function noise({ dur = 0.12, gain = 0.10, lowPass = 1200 }) {
                if (!ctx) return;
                const t0 = ctx.currentTime;
                const bufferSize = Math.floor(ctx.sampleRate * dur);
                const buffer = ctx.createBuffer(1, bufferSize, ctx.sampleRate);
                const data = buffer.getChannelData(0);
                for (let i = 0; i < bufferSize; i++) data[i] = Math.random() * 2 - 1;
                const src = ctx.createBufferSource();
                src.buffer = buffer;
                const filter = ctx.createBiquadFilter();
                filter.type = 'lowpass';
                filter.frequency.value = lowPass;
                const g = ctx.createGain();
                g.gain.setValueAtTime(gain, t0);
                g.gain.exponentialRampToValueAtTime(0.0001, t0 + dur);
                src.connect(filter).connect(g).connect(master);
                src.start(t0);
                src.stop(t0 + dur + 0.05);
            }

            // ---- SFX library ----
            function sfx(name) {
                if (!started || master?.gain.value === 0) return;
                init();
                const pack = themePack();
                if (pack) {
                    pack.sfx(name);
                    return;
                }
                switch (name) {
                    case 'bet':       // short ascending blip
                        tone({ freq: midi(72), slideTo: midi(79), type: 'square', gain: 0.20, dur: 0.10 });
                        break;

                    case 'start':     // DIVE — multi-layer dramatic descent
                        // Layer 1: deep engine ignition swoop (sub bass slide down)
                        tone({ freq: midi(60), slideTo: midi(36), type: 'sawtooth', gain: 0.30, dur: 0.75, attack: 0.01, decay: 0.20, sustain: 0.5, release: 0.45 });
                        // Layer 2: mid-range engine harmonic (square doubles the body)
                        tone({ freq: midi(48), slideTo: midi(28), type: 'square',   gain: 0.10, dur: 0.70, attack: 0.02, decay: 0.30, sustain: 0.4, release: 0.35 });
                        // Layer 3: turbulent water rush (filtered noise burst)
                        noise({ dur: 0.55, gain: 0.18, lowPass: 1400 });
                        // Layer 4: sonar ping kicks in mid-swoop — descending sine
                        setTimeout(() => tone({ freq: midi(84), slideTo: midi(60), type: 'sine', gain: 0.14, dur: 0.40, attack: 0.005, decay: 0.10, sustain: 0.4, release: 0.30 }), 180);
                        // Layer 5: pad-like sustain tail for "we are now diving" ambient
                        setTimeout(() => tone({ freq: midi(43), type: 'triangle', gain: 0.06, dur: 0.80, attack: 0.20, decay: 0.30, sustain: 0.6, release: 0.50 }), 350);
                        break;

                    case 'win':       // coin jingle — three rising notes
                        [76, 81, 88].forEach((n, i) => setTimeout(() => tone({ freq: midi(n), type: 'square', gain: 0.22, dur: 0.14 }), i * 70));
                        break;
                    case 'loss':      // descending sad notes
                        [60, 56, 52].forEach((n, i) => setTimeout(() => tone({ freq: midi(n), type: 'square', gain: 0.18, dur: 0.18 }), i * 110));
                        break;

                    case 'crash':     // EXPLOSION — sharp impact + crackle + long boom + ring-down
                        // Layer 1: sharp click (transient — gives the punch)
                        tone({ freq: midi(96), slideTo: midi(40), type: 'square',   gain: 0.20, dur: 0.04, attack: 0.001, decay: 0.02, sustain: 0.0, release: 0.02 });
                        // Layer 2: heavy sub-bass boom slide down (the IMPACT)
                        tone({ freq: midi(48), slideTo: midi(24), type: 'sawtooth', gain: 0.36, dur: 0.90, attack: 0.005, decay: 0.30, sustain: 0.4, release: 0.55 });
                        // Layer 3: mid-range body (square — pressure crush)
                        tone({ freq: midi(40), slideTo: midi(22), type: 'square',   gain: 0.18, dur: 0.70, attack: 0.005, decay: 0.30, sustain: 0.3, release: 0.40 });
                        // Layer 4: shrapnel/cavitation crackle (mid-high noise)
                        noise({ dur: 0.25, gain: 0.20, lowPass: 3500 });
                        // Layer 5: extended low-rumble debris (long noise tail)
                        setTimeout(() => noise({ dur: 0.80, gain: 0.10, lowPass: 400 }), 30);
                        // Layer 6: metallic ring-out (two pitched echoes)
                        setTimeout(() => tone({ freq: midi(31), type: 'sine', gain: 0.10, dur: 1.20, attack: 0.05, decay: 0.30, sustain: 0.5, release: 0.85 }), 80);
                        setTimeout(() => tone({ freq: midi(35), type: 'sine', gain: 0.08, dur: 1.00, attack: 0.05, decay: 0.30, sustain: 0.5, release: 0.65 }), 160);
                        // Layer 7: dissonant secondary boom (creates explosive width)
                        setTimeout(() => tone({ freq: midi(38), slideTo: midi(20), type: 'triangle', gain: 0.14, dur: 0.65, attack: 0.005, decay: 0.25, sustain: 0.4, release: 0.40 }), 60);
                        break;

                    case 'tick':      // soft click for betting countdown
                        tone({ freq: midi(72), type: 'sine', gain: 0.08, dur: 0.04 });
                        break;
                }
            }

            // ---- Underwater ambience engine ----
            // Replaces the chiptune loop with layered procedural soundscape:
            //   1. Sub-bass drone (35–60 Hz sine cluster) — the "pressure"
            //   2. Filtered noise loop — distant water current
            //   3. Random sonar pings every 6–20 s
            //   4. Random hull creaks every 10–30 s (deeper = more frequent)
            //   5. Rare distant whale calls every 25–60 s
            // All nodes are tracked so stop/mute can cleanly tear them down.
            let ambientNodes = [];
            let ambientTimers = [];

            function addNode(n) { ambientNodes.push(n); return n; }
            function addTimer(t) { ambientTimers.push(t); return t; }

            function stopMusic() {
                if (themeAmbience) { themeAmbience.stop(); themeAmbience = null; }
                if (musicTimer) { clearTimeout(musicTimer); musicTimer = null; } // legacy guard
                ambientTimers.forEach(t => clearTimeout(t));
                ambientTimers = [];
                if (!ctx) { ambientNodes = []; return; }
                const t = ctx.currentTime;
                ambientNodes.forEach(node => {
                    try {
                        if (node.gain && node.gain.cancelScheduledValues) {
                            node.gain.cancelScheduledValues(t);
                            node.gain.exponentialRampToValueAtTime(0.0001, t + 0.4);
                        }
                        if (typeof node.stop === 'function') node.stop(t + 0.5);
                    } catch (_) { /* node already stopped */ }
                });
                ambientNodes = [];
            }

            function startMusic() {
                if (!musicEnabled) return;
                if (ambientNodes.length || themeAmbience) return; // already running
                init();
                const pack = themePack();
                if (pack) {
                    themeAmbience = pack.ambience();
                    themeAmbience.intensity?.(diveLevel());
                    return;
                }

                // === Layer 1: low drone cluster ====================================
                [37, 55.5, 74].forEach((freq, i) => {
                    const osc = ctx.createOscillator();
                    const g = ctx.createGain();
                    osc.type = i === 0 ? 'sine' : 'triangle';
                    osc.frequency.value = freq;
                    const target = [0.040, 0.020, 0.010][i];
                    const t0 = ctx.currentTime;
                    g.gain.setValueAtTime(0.0001, t0);
                    g.gain.exponentialRampToValueAtTime(target, t0 + 3.0);
                    osc.connect(g).connect(master);
                    osc.start(t0);
                    addNode(osc); addNode(g);
                });

                // === Layer 2: distant water current (filtered noise loop) ==========
                const noiseBuf = ctx.createBuffer(1, ctx.sampleRate * 4, ctx.sampleRate);
                const data = noiseBuf.getChannelData(0);
                for (let i = 0; i < data.length; i++) data[i] = Math.random() * 2 - 1;
                const noiseSrc = ctx.createBufferSource();
                noiseSrc.buffer = noiseBuf;
                noiseSrc.loop = true;
                const flowFilter = ctx.createBiquadFilter();
                flowFilter.type = 'lowpass';
                flowFilter.frequency.value = 380;
                flowFilter.Q.value = 0.5;
                const flowGain = ctx.createGain();
                const t0 = ctx.currentTime;
                flowGain.gain.setValueAtTime(0.0001, t0);
                flowGain.gain.exponentialRampToValueAtTime(0.022, t0 + 4.0);
                noiseSrc.connect(flowFilter).connect(flowGain).connect(master);
                noiseSrc.start(t0);
                addNode(noiseSrc); addNode(flowFilter); addNode(flowGain);

                // Slow LFO on the noise filter cutoff for a gently shifting current
                const lfo = ctx.createOscillator();
                const lfoDepth = ctx.createGain();
                lfo.type = 'sine';
                lfo.frequency.value = 0.08;   // very slow — ~12 s cycle
                lfoDepth.gain.value = 120;
                lfo.connect(lfoDepth).connect(flowFilter.frequency);
                lfo.start();
                addNode(lfo); addNode(lfoDepth);

                // === Layer 3–5: scheduled one-shots ================================
                schedulePing();
                scheduleCreak();
                scheduleWhale();
            }

            // -- Sonar ping (every 6–20 s) --
            function schedulePing() {
                const delay = 6000 + Math.random() * 14000;
                addTimer(setTimeout(() => {
                    if (!ambientNodes.length) return;
                    const t = ctx.currentTime;
                    const freq = 720 + Math.random() * 280;
                    const osc = ctx.createOscillator();
                    const g = ctx.createGain();
                    osc.type = 'sine';
                    osc.frequency.setValueAtTime(freq, t);
                    osc.frequency.exponentialRampToValueAtTime(freq * 0.45, t + 1.6);
                    g.gain.setValueAtTime(0.0001, t);
                    g.gain.exponentialRampToValueAtTime(0.055, t + 0.02);
                    g.gain.exponentialRampToValueAtTime(0.0001, t + 1.7);
                    osc.connect(g).connect(master);
                    osc.start(t);
                    osc.stop(t + 1.75);
                    schedulePing();
                }, delay));
            }

            // -- Hull creak / groan (every 10–30 s) --
            function scheduleCreak() {
                const delay = 10000 + Math.random() * 20000;
                addTimer(setTimeout(() => {
                    if (!ambientNodes.length) return;
                    const t = ctx.currentTime;
                    const osc = ctx.createOscillator();
                    const g = ctx.createGain();
                    const bp = ctx.createBiquadFilter();
                    bp.type = 'bandpass';
                    bp.frequency.value = 180 + Math.random() * 220;
                    bp.Q.value = 5.5;
                    osc.type = 'sawtooth';
                    const f0 = 70 + Math.random() * 60;
                    osc.frequency.setValueAtTime(f0, t);
                    osc.frequency.linearRampToValueAtTime(f0 * 1.35, t + 1.5);
                    osc.frequency.linearRampToValueAtTime(f0 * 0.9,  t + 2.6);
                    g.gain.setValueAtTime(0.0001, t);
                    g.gain.exponentialRampToValueAtTime(0.075, t + 0.3);
                    g.gain.exponentialRampToValueAtTime(0.0001, t + 2.7);
                    osc.connect(bp).connect(g).connect(master);
                    osc.start(t);
                    osc.stop(t + 2.8);
                    scheduleCreak();
                }, delay));
            }

            // -- Distant whale call (every 25–60 s) — long sine glissando --
            function scheduleWhale() {
                const delay = 25000 + Math.random() * 35000;
                addTimer(setTimeout(() => {
                    if (!ambientNodes.length) return;
                    const t = ctx.currentTime;
                    const osc = ctx.createOscillator();
                    const g = ctx.createGain();
                    osc.type = 'sine';
                    const start = 90 + Math.random() * 40;
                    const peak  = start + 60 + Math.random() * 80;
                    osc.frequency.setValueAtTime(start, t);
                    osc.frequency.exponentialRampToValueAtTime(peak, t + 1.4);
                    osc.frequency.exponentialRampToValueAtTime(start * 0.7, t + 3.4);
                    g.gain.setValueAtTime(0.0001, t);
                    g.gain.exponentialRampToValueAtTime(0.045, t + 0.5);
                    g.gain.exponentialRampToValueAtTime(0.0001, t + 3.5);
                    osc.connect(g).connect(master);
                    osc.start(t);
                    osc.stop(t + 3.6);
                    scheduleWhale();
                }, delay));
            }

            function setMuted(muted) {
                musicEnabled = !muted;
                if (master) master.gain.value = muted ? 0 : 0.35;
                try { localStorage.setItem(STORAGE_KEY, muted ? '1' : '0'); } catch (_) {}
                document.getElementById('audio-btn')?.classList.toggle('is-muted', muted);
                if (muted) stopMusic();
                else if (started) startMusic();
            }

            function isMuted() {
                try { return localStorage.getItem(STORAGE_KEY) === '1'; } catch (_) { return false; }
            }

            // First user gesture anywhere → unlock AudioContext + kick off music
            function unlock() {
                if (started) return;
                started = true;
                init();
                // Some browsers leave the context in 'suspended' even after init.
                if (ctx.state === 'suspended') ctx.resume();
                setMuted(isMuted());
                if (!isMuted()) startMusic();
            }

            // Capture phase: controls that stop their clicks (the theme picker) still unlock audio.
            ['click', 'touchstart', 'keydown'].forEach(ev =>
                document.addEventListener(ev, unlock, { capture: true, passive: true }));

            document.getElementById('audio-btn')?.addEventListener('click', (e) => {
                e.stopPropagation();
                unlock();
                setMuted(!isMuted());
            });

            // Reflect initial mute state in icon
            document.getElementById('audio-btn')?.classList.toggle('is-muted', isMuted());

            // A theme brings its own sounds: swap the background when the skin changes.
            new MutationObserver((records) => {
                const now = document.documentElement.getAttribute('data-theme');
                if (records.every((r) => r.oldValue === now)) return;
                if (started && musicEnabled) {
                    stopMusic();
                    startMusic();
                }
            }).observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'], attributeOldValue: true });

            // A pack's music may follow the dive (Jackpot's band builds as it deepens):
            // 0 betting/waiting/crashed · 1 diving · 2 deep · 3 abyss or critical.
            function diveLevel() {
                const c = document.getElementById('stage')?.classList;
                if (!c || c.contains('crashed')) return 0;
                if (c.contains('abyss') || c.contains('critical')) return 3;
                if (c.contains('deep')) return 2;
                return c.contains('running') ? 1 : 0;
            }
            const $stageForMusic = document.getElementById('stage');
            if ($stageForMusic) {
                new MutationObserver(() => themeAmbience?.intensity?.(diveLevel()))
                    .observe($stageForMusic, { attributes: true, attributeFilter: ['class'] });
            }

            return { sfx, startMusic, stopMusic };
        })();

        // ---- Wire SFX to the round + operator-panel events ----
        window.Echo?.channel('crash')
            .listen('.round.betting_opened', () => audio.sfx('tick'))
            .listen('.round.started',        () => audio.sfx('start'))
            .listen('.round.crashed',        () => audio.sfx('crash'));

        // The operator panel's buttons, in either bet slot (only shown when embedded).
        document.getElementById('op-panel')?.addEventListener('click', (e) => {
            if (e.target.closest('.op-bet-btn')) audio.sfx('bet');
            else if (e.target.closest('.op-cashout-btn')) audio.sfx('win');
        });

        // postMessage results from the parent — play feedback SFX based on outcome.
        window.addEventListener('message', (event) => {
            const { type, payload } = event.data || {};
            if (type === 'cashout_result') {
                if (payload?.success) audio.sfx('win');
                else if (payload?.error === 'round_crashed') audio.sfx('loss');
            } else if (type === 'bet_placed_result' && payload?.success === false) {
                audio.sfx('loss');
            } else if (type === 'auto_cashout_fired') {
                audio.sfx('win');
            }
        });

        // ----------------------------------------------------------------------
        // Operator embed bridge — when /crash is loaded in an iframe by an
        // operator (e.g. OrangeConsumer), expose a small overlay panel that
        // postMessages bet/cashout INTENTS to the parent window. The parent
        // owns the operator HMAC secret and forwards the signed call to Orange.
        // ----------------------------------------------------------------------
        (function operatorBridge() {
            const isEmbedded = window.self !== window.top;
            if (!isEmbedded) return;

            document.body.classList.add('embedded');

            // Origins permitted to drive this embed. Mirrors config('embed.allowed_origins').
            // Orange's own host pages (/play, /lobby) drive the game from the same origin.
            const allowedParentOrigins = [...@json(array_values((array) config('embed.allowed_origins', []))), window.location.origin];

            const $panel    = document.getElementById('op-panel');
            const $player   = document.getElementById('op-player');
            const $balance  = document.getElementById('op-balance');
            const $phaseChip = document.getElementById('op-phase');
            const $status   = document.getElementById('op-status');
            const $last     = document.getElementById('op-last');
            const $lastMult = document.getElementById('op-last-mult');
            const $lastPay  = document.getElementById('op-last-payout');
            const $addSlot  = $panel.querySelector('.op-add-slot');
            const $removeSlot = $panel.querySelector('.op-remove-slot');
            $panel.setAttribute('aria-hidden', 'false');

            function showLast(kind, multiplier, payout) {
                $last.classList.add('visible');
                $last.classList.toggle('loss', kind === 'loss');
                const m = Number(multiplier);
                $lastMult.textContent = Number.isFinite(m) ? m.toFixed(2) + '×' : '—';
                $lastPay.textContent = kind === 'win' ? '+' + money(payout) : 'lost';
            }

            // Money is always shown with two decimals: "673.99000000" → "673.99",
            // "10" → "10.00". Wallets answer with 8-decimal precision; the extra
            // digits are cut (never rounded up past what the player has).
            function money(value) {
                if (value === null || value === undefined || value === '') return '—';
                const s = String(value).trim();
                const m = /^(-?\d+)(?:\.(\d*))?$/.exec(s);
                if (!m) return Number.isFinite(Number(s)) ? Number(s).toFixed(2) : s;
                return `${m[1]}.${((m[2] || '') + '00').slice(0, 2)}`;
            }

            // Spawn a "× 1.30   +10.30 EUR" toast over the stage that drifts
            // upward and fades. Re-entrant: two bets cashing out together overlap.
            function spawnFlyWin(multiplier, payout) {
                const m = Number(multiplier);
                if (!Number.isFinite(m)) return;
                const node = document.createElement('div');
                node.className = 'fly-win';
                const multSpan = document.createElement('div');
                multSpan.className = 'fly-win-mult';
                multSpan.textContent = `× ${m.toFixed(2)}`;
                const amtSpan = document.createElement('div');
                amtSpan.className = 'fly-win-amount';
                amtSpan.textContent = `+${money(payout)}${opState.currency ? ' ' + opState.currency : ''}`;
                node.append(multSpan, amtSpan);
                $stage.appendChild(node);
                node.addEventListener('animationend', () => node.remove(), { once: true });
            }

            function applyPhaseChip(phase) {
                $phaseChip.dataset.phase = phase;
                $phaseChip.textContent = phase;
            }

            const opState = {
                parentOrigin: null,        // first valid origin we received a message from
                phase: 'waiting',          // synced to the in-page Echo state machine
                roundUuid: null,
                playerId: null,
                maxSlots: 1,               // 2 once the host takes two bets a round (player_info.bet_slots)
                placing: [],               // slots whose place_bet awaits its result, in the order sent
            };

            // A bet slot: its own stake, auto-cashout, AUTO BET and bet. Slot 0 is the bar
            // as it always was; slot 1 is the second bet.
            function makeSlot(index) {
                const $root = $panel.querySelector(`.op-slot[data-slot="${index}"]`);
                const q = (selector) => $root.querySelector(selector);
                return {
                    index,
                    $root,
                    $stake: q('.op-stake'),
                    $auto: q('.op-auto'),
                    $bet: q('.op-bet-btn'),
                    $co: q('.op-cashout-btn'),
                    $coX: q('.op-cashout-x'),
                    $autoBet: q('.op-autobet-btn'),
                    betId: null,
                    betRoundUuid: null,
                    autoCashoutAt: null,       // threshold sent with the active bet
                    autoCashoutFired: false,   // local guard: already asked parent to cashout
                    cashingOut: false,         // a cashout is on its way to the parent
                    autoBet: false,            // when true, fire a bet on every betting_opened
                    lastAutoBetRound: null,    // round_uuid we last fired auto-bet for (de-dupe guard)
                    // Restore AUTO BET across reloads. Keyed by host so a user sharing the
                    // machine doesn't inherit someone else's armed state.
                    autoBetKey: index === 0 ? 'orange.embed.autobet' : 'orange.embed.autobet.2',
                };
            }
            const slots = [makeSlot(0), makeSlot(1)];
            const SLOTS_KEY = 'orange.embed.slots';
            const twoOpen = () => !slots[1].$root.hidden;
            // Messages name the bet when there are two.
            const tag = (slot) => (twoOpen() ? `bet ${slot.index + 1} · ` : '');
            function forgetBet(slot) {
                slot.betId = null;
                slot.betRoundUuid = null;
                slot.autoCashoutAt = null;
                slot.autoCashoutFired = false;
                slot.cashingOut = false;
            }

            function setAutoBet(slot, enabled, reason = '') {
                slot.autoBet = !!enabled;
                slot.$autoBet.dataset.active = enabled ? 'true' : 'false';
                slot.$autoBet.setAttribute('aria-pressed', enabled ? 'true' : 'false');
                try {
                    if (enabled) localStorage.setItem(slot.autoBetKey, '1');
                    else localStorage.removeItem(slot.autoBetKey);
                } catch (_) { /* privacy mode / quota */ }
                if (reason) setStatus(tag(slot) + reason, enabled ? 'ok' : 'err');
            }

            // The second bet opens and closes with + / −. It closes only without a bet
            // in play, and closing it disarms its AUTO BET.
            function setTwo(open) {
                if (!open && (slots[1].betId || opState.placing.includes(slots[1]))) return;
                slots[1].$root.hidden = !open;
                $addSlot.hidden = opState.maxSlots < 2;
                // Open: + only holds its place, so the two rows line up.
                $addSlot.classList.toggle('is-placeholder', open);
                $addSlot.disabled = open;
                $addSlot.setAttribute('aria-hidden', String(open));
                document.body.classList.toggle('two-bets', open);
                if (!open && slots[1].autoBet) setAutoBet(slots[1], false);
                if (open) {
                    try { if (localStorage.getItem(slots[1].autoBetKey) === '1') setAutoBet(slots[1], true); } catch (_) {}
                }
                try { localStorage.setItem(SLOTS_KEY, open ? '2' : '1'); } catch (_) {}
                updateButtons();
            }

            function setStatus(msg, kind = '') {
                $status.className = 'op-status' + (kind ? ' ' + kind : '');
                $status.textContent = msg;
            }

            function postToParent(payload) {
                const target = opState.parentOrigin;
                if (!target || !window.parent) return;
                window.parent.postMessage(payload, target);
            }

            function updateButtons() {
                for (const slot of slots) {
                    const canBet = opState.phase === 'betting' && !slot.betId && !opState.placing.includes(slot)
                        && !!opState.parentOrigin && !slot.$root.hidden;
                    const canCashout = opState.phase === 'running' && !!slot.betId && slot.betRoundUuid === opState.roundUuid;
                    slot.$bet.disabled = !canBet;
                    slot.$co.disabled = !canCashout || slot.cashingOut;
                    // Single-CTA mode when a bet is held + round running: hide bet inputs, blow up cashout.
                    slot.$root.dataset.mode = (slot.betId && opState.phase === 'running') ? 'cashout' : 'place';
                    slot.$co.classList.toggle('pulse', canCashout && !slot.cashingOut);
                }
                $removeSlot.disabled = !!(slots[1].betId || opState.placing.includes(slots[1]));
                applyPhaseChip(opState.phase);
            }

            // Which slot a result is for. Orange's host pages echo the slot a place_bet
            // named; from a page that doesn't, results come back in the order sent.
            function slotPlaced(payload) {
                const named = Number.isInteger(payload.slot) ? slots[payload.slot] : null;
                const slot = named ?? opState.placing[0] ?? slots[0];
                const i = opState.placing.indexOf(slot);
                if (i >= 0) opState.placing.splice(i, 1);
                return slot;
            }
            const slotOfBet = (betId) => slots.find((s) => s.betId !== null && betId !== undefined && String(s.betId) === String(betId));

            // Mirror the in-page Echo state machine onto our buttons.
            window.addEventListener('message', (event) => {
                if (!allowedParentOrigins.includes(event.origin)) return;
                if (!event.data || typeof event.data !== 'object') return;
                const { type, payload } = event.data;
                if (!type) return;

                // Lock onto the first parent origin that talked to us.
                if (!opState.parentOrigin) opState.parentOrigin = event.origin;

                switch (type) {
                    case 'player_info': {
                        opState.playerId = payload.id;
                        opState.currency = payload.currency;
                        // The player's name when the page knows one (Orange's host sends the id
                        // twice when it doesn't); the id stays in the tooltip.
                        $player.textContent = payload.username && payload.username !== String(payload.id)
                            ? payload.username
                            : `#${payload.id}`;
                        $player.title = `#${payload.id}`;
                        $balance.textContent = `${money(payload.balance)} ${payload.currency}`;
                        setStatus('connected to operator', 'ok');
                        // Pages that answer bet_history say so (Orange's own host pages do).
                        if (payload.bet_history === true && !document.documentElement.classList.contains('has-bets')) {
                            sheet.enableBets({ request: () => postToParent({ type: 'bet_history', payload: {} }), money });
                            document.documentElement.classList.add('has-bets');
                            fitHistory();
                        }
                        // Pages that take two bets a round say so (Orange's own host pages do).
                        if (Number(payload.bet_slots) >= 2 && opState.maxSlots < 2) {
                            opState.maxSlots = 2;
                            let two = false;
                            try { two = localStorage.getItem(SLOTS_KEY) === '2'; } catch (_) {}
                            setTwo(two);
                        }
                        updateButtons();
                        break;
                    }

                    case 'bet_history_result':
                        sheet.receiveBets(payload);
                        break;

                    case 'balance_update':
                        $balance.textContent = `${money(payload.balance)} ${opState.currency || ''}`.trim();
                        break;

                    case 'bet_placed_result': {
                        const slot = slotPlaced(payload);
                        if (payload.success) {
                            slot.betId = payload.bet_id;
                            slot.betRoundUuid = payload.round_uuid;
                            slot.autoCashoutAt = payload.auto_cashout_at ?? slot.autoCashoutAt ?? null;
                            slot.autoCashoutFired = false;
                            slot.cashingOut = false;
                            setStatus(`${tag(slot)}bet #${payload.bet_id} placed @ ${money(payload.amount)}`, 'ok');
                        } else {
                            slot.autoCashoutAt = null;
                            // Pause auto-bet on failure — otherwise an insufficient-balance
                            // error (or any structural reject) would loop every round
                            // and spam the operator with rejected calls.
                            if (slot.autoBet) {
                                setAutoBet(slot, false, `bet failed: ${payload.error || 'unknown'} — auto-bet paused`);
                            } else {
                                setStatus(`${tag(slot)}bet failed: ${payload.error || 'unknown'}`, 'err');
                            }
                        }
                        if (payload.player_balance) $balance.textContent = money(payload.player_balance) + (opState.currency ? ' ' + opState.currency : '');
                        if (payload.success) {
                            sheet.betsChanged();
                            liveBets.mine(payload.bet_id);
                        }
                        updateButtons();
                        break;
                    }

                    case 'cashout_result': {
                        const slot = slotOfBet(payload.bet_id) ?? slots.find((s) => s.cashingOut) ?? slots[0];
                        if (payload.success) {
                            setStatus(`${tag(slot)}cashed out @ ${payload.multiplier}× · payout ${money(payload.payout)}`, 'ok');
                            showLast('win', payload.multiplier, payload.payout);
                            spawnFlyWin(payload.multiplier, payload.payout);
                            forgetBet(slot);
                        } else if (payload.error === 'round_crashed') {
                            setStatus(`${tag(slot)}round crashed — bet #${payload.bet_id} lost`, 'err');
                            showLast('loss', payload.error_message?.replace(/[^0-9.]/g, ''), null);
                            forgetBet(slot);
                        } else if (payload.error === 'already_in_flight') {
                            /* parent already cashing out — ignore the duplicate response */
                        } else {
                            slot.cashingOut = false;
                            setStatus(`${tag(slot)}cashout failed: ${payload.error || 'unknown'}`, 'err');
                        }
                        if (payload.player_balance) $balance.textContent = money(payload.player_balance) + (opState.currency ? ' ' + opState.currency : '');
                        sheet.betsChanged();
                        updateButtons();
                        break;
                    }

                    case 'auto_cashout_fired': {
                        // The bet it names, else the one with that threshold, else the first held.
                        const slot = slotOfBet(payload.bet_id)
                            ?? slots.find((s) => s.betId && s.autoCashoutAt !== null && Number(s.autoCashoutAt) === Number(payload.threshold))
                            ?? slots.find((s) => s.betId);
                        if (!slot) break;
                        slot.autoCashoutFired = true;
                        slot.cashingOut = true;
                        setStatus(`${tag(slot)}auto-cashout @ ${Number(payload.multiplier).toFixed(2)}×…`, 'ok');
                        slot.$co.disabled = true;
                        break;
                    }

                    case 'round_state':
                        opState.phase = payload.phase;
                        opState.roundUuid = payload.round_uuid;
                        // If a new round started while we were holding a bet from the previous one,
                        // forget it — that bet can't be cashed out against this round.
                        for (const slot of slots) {
                            if (slot.betRoundUuid && slot.betRoundUuid !== payload.round_uuid) forgetBet(slot);
                        }
                        updateButtons();
                        break;
                }
            });

            // Locally drive button state off the same Echo events the page is
            // already subscribed to — so the panel feels instant even before
            // a parent broadcasts round_state. (Parent re-confirms below.)
            window.Echo?.channel('crash')
                .listen('.round.betting_opened', (e) => {
                    opState.phase = 'betting';
                    opState.roundUuid = e.round_uuid;
                    opState.placing = []; // a result that never came can't hold a slot past its round
                    for (const slot of slots) {
                        if (slot.betRoundUuid && slot.betRoundUuid !== e.round_uuid) forgetBet(slot);
                    }
                    updateButtons();

                    // AUTO BET: each armed slot without a bet for this round fires one.
                    // lastAutoBetRound de-dupes against the rare case where
                    // betting_opened arrives twice (e.g. reconnect).
                    for (const slot of slots) {
                        if (
                            slot.autoBet &&
                            !slot.$root.hidden &&
                            !slot.betId &&
                            opState.parentOrigin &&
                            slot.lastAutoBetRound !== e.round_uuid
                        ) {
                            slot.lastAutoBetRound = e.round_uuid;
                            triggerBet(slot);
                        }
                    }
                })
                .listen('.round.started',   () => { opState.phase = 'running'; opState.placing = []; updateButtons(); })
                .listen('.round.tick', (e) => {
                    const m = Number(e.multiplier);
                    for (const slot of slots) {
                        if (Number.isFinite(m)) slot.$coX.textContent = '@ ' + m.toFixed(2) + '×';

                        // Local auto-cashout safety net: if the parent is slow, the
                        // iframe also fires once when the threshold is crossed. The
                        // autoCashoutFired flag + parent's cashingOut guard ensure
                        // we don't double-send.
                        const at = slot.autoCashoutAt;
                        if (
                            slot.betId &&
                            opState.parentOrigin &&
                            !slot.autoCashoutFired &&
                            Number.isFinite(at) && at > 1 &&
                            m >= at
                        ) {
                            slot.autoCashoutFired = true;
                            slot.cashingOut = true;
                            setStatus(`${tag(slot)}auto-cashout @ ${m.toFixed(2)}×…`, 'ok');
                            slot.$co.disabled = true;
                            postToParent({ type: 'cashout', payload: { bet_id: slot.betId } });
                        }
                    }
                })
                .listen('.round.crashed', () => {
                    opState.phase = 'crashed';
                    for (const slot of slots) slot.$coX.textContent = '—';
                    updateButtons();
                })
                .listen('.round.settled', () => {
                    opState.phase = 'waiting';
                    for (const slot of slots) {
                        forgetBet(slot);
                        slot.$coX.textContent = '—';
                    }
                    updateButtons();
                });

            function triggerBet(slot) {
                if (!opState.parentOrigin) {
                    setStatus('parent not ready', 'err');
                    return;
                }
                const autoAt = slot.$auto.value ? Number(slot.$auto.value) : null;
                slot.autoCashoutAt = Number.isFinite(autoAt) && autoAt > 1 ? autoAt : null;
                slot.autoCashoutFired = false;
                slot.$bet.disabled = true;
                opState.placing.push(slot);
                setStatus(tag(slot) + (slot.autoBet ? 'auto-bet · placing…' : 'placing bet…'));
                const payload = {
                    amount: money(slot.$stake.value),
                    auto_cashout_at: slot.autoCashoutAt,
                    round_uuid: opState.roundUuid,
                };
                // Named only for a host that takes two bets, which echoes it in bet_placed_result.
                if (opState.maxSlots > 1) payload.slot = slot.index;
                postToParent({ type: 'place_bet', payload });
                updateButtons();
            }

            for (const slot of slots) {
                const { $stake, $auto } = slot;

                // Stake stepper — quick − / + around the stake input.
                // Step size scales with current value so big bets aren't tedious.
                function stepStake(direction) {
                    const cur = parseFloat($stake.value);
                    const base = Number.isFinite(cur) && cur > 0 ? cur : 0;
                    const step = base >= 100 ? 10 : base >= 20 ? 5 : 1;
                    const next = Math.max(0.01, +(base + direction * step).toFixed(2));
                    $stake.value = next.toFixed(2);
                    $stake.dispatchEvent(new Event('input', { bubbles: true }));
                }
                // Stakes have two decimals, like the money they come out of: typing
                // stops at the second decimal, and the field settles on "0.00" form.
                $stake.addEventListener('input', () => {
                    const cut = $stake.value.replace(/^(\d*\.\d{2})\d+$/, '$1');
                    if (cut !== $stake.value) $stake.value = cut;
                });
                $stake.addEventListener('change', () => {
                    const v = parseFloat($stake.value);
                    if (Number.isFinite(v) && v > 0) $stake.value = money($stake.value);
                });
                slot.$root.querySelector('.op-stake-down').addEventListener('click', () => stepStake(-1));
                slot.$root.querySelector('.op-stake-up').addEventListener('click', () => stepStake(+1));

                // Auto cashout stepper — 0.10× per click, snaps to nearest tenth.
                // From empty, the first + jumps to 1.50× (a sensible default);
                // − below 1.01 (the upstream minimum) just clamps to 1.01.
                function stepAuto(direction) {
                    const cur = parseFloat($auto.value);
                    let next;
                    if (!Number.isFinite(cur) || cur <= 0) {
                        next = 1.50;
                    } else {
                        next = +(cur + direction * 0.10).toFixed(2);
                    }
                    if (next < 1.01) next = 1.01;
                    $auto.value = next;
                    $auto.dispatchEvent(new Event('input', { bubbles: true }));
                }
                slot.$root.querySelector('.op-auto-down').addEventListener('click', () => stepAuto(-1));
                slot.$root.querySelector('.op-auto-up').addEventListener('click', () => stepAuto(+1));
                slot.$root.querySelector('.op-auto-clear').addEventListener('click', () => {
                    $auto.value = '';
                    $auto.dispatchEvent(new Event('input', { bubbles: true }));
                });

                slot.$bet.addEventListener('click', () => triggerBet(slot));

                slot.$autoBet.addEventListener('click', () => {
                    const next = !slot.autoBet;
                    setAutoBet(slot, next, next ? 'auto-bet armed · next round will bet' : 'auto-bet off');
                    // If user armed it during the current betting window and has no
                    // active bet yet, fire immediately so they don't sit out this round.
                    if (
                        next &&
                        opState.phase === 'betting' &&
                        !slot.betId &&
                        !opState.placing.includes(slot) &&
                        opState.parentOrigin &&
                        slot.lastAutoBetRound !== opState.roundUuid
                    ) {
                        slot.lastAutoBetRound = opState.roundUuid;
                        triggerBet(slot);
                    }
                });

                slot.$co.addEventListener('click', () => {
                    if (!slot.betId || !opState.parentOrigin) return;
                    slot.cashingOut = true;
                    slot.$co.disabled = true;
                    setStatus(tag(slot) + 'cashing out…');
                    postToParent({
                        type: 'cashout',
                        payload: { bet_id: slot.betId },
                    });
                    updateButtons();
                });

            }
            // Sync the first bet's AUTO BET with localStorage; the second's is restored
            // when that bet opens (setTwo).
            try {
                if (localStorage.getItem(slots[0].autoBetKey) === '1') setAutoBet(slots[0], true);
            } catch (_) {}

            $addSlot.addEventListener('click', () => setTwo(true));
            $removeSlot.addEventListener('click', () => setTwo(false));
            document.getElementById('bets-btn').addEventListener('click', () => sheet.openBets());

            // Announce readiness so the parent can replay player_info / round_state.
            // We don't know the parent origin yet, so use '*' for this one beacon;
            // the parent must still verify *our* origin on its side.
            if (window.parent) {
                window.parent.postMessage({ type: 'embed_ready' }, '*');
            }

            setStatus('embed ready · waiting for player_info');
        })();
    </script>
</body>
</html>
