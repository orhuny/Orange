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
    ];
    // Only these skins are offered (picker, CSS, ?theme=, saved choice).
    // The rest stay defined above so they can be re-enabled by adding
    // their key here.
    $enabledThemes = ['burton'];
    $themes = array_intersect_key($themes, array_flip($enabledThemes));
    $rgb = static fn (string $hex): string => implode(',', sscanf($hex, '#%02x%02x%02x'));

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
            'bgLandscape' => asset('images/bg-gothic-landscape.jpg'),
            'bgPortrait' => asset('images/bg-gothic-portrait.jpg'),
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
        :root[data-theme="burton"] .scene::before {
            content: ""; position: absolute; pointer-events: none;
            top: 6%; right: 12%; width: 150px; height: 150px; border-radius: 50%;
            background:
              repeating-radial-gradient(circle at 42% 44%, transparent 0 9px, rgba(90,80,110,0.22) 10px 11px),
              radial-gradient(circle at 40% 40%, #fbf8ef 0%, #e2dccb 55%, #b9b1c4 100%);
            box-shadow: 0 0 60px 18px rgba(236,230,216,0.28), 0 0 160px 40px rgba(139,127,168,0.25);
            opacity: 0.85; transition: opacity 1.5s ease;
        }
        :root[data-theme="burton"] .deep .scene::before  { opacity: 0.35; }
        :root[data-theme="burton"] .abyss .scene::before,
        :root[data-theme="burton"] .critical .scene::before { opacity: 0.12; }
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
        :root[data-theme="burton"] .scene {
            background: url("{{ asset('images/bg-gothic-landscape.jpg') }}?v=2") center bottom / cover no-repeat, var(--abyss);
        }
        :root[data-theme="burton"] .scene::after,
        :root[data-theme="burton"] .rays { display: none; }
        @media (orientation: portrait) {
            :root[data-theme="burton"] .scene {
                background: url("{{ asset('images/bg-gothic-portrait.jpg') }}") center top / cover no-repeat, var(--abyss);
            }
            :root[data-theme="burton"] .scene::before { display: none; }
        }
        /* Illustrated gothic fish + octopi replace the SVG silhouettes. */
        .gothic-art { display: none; }
        :root[data-theme="burton"] .fish > svg,
        :root[data-theme="burton"] .creature.octopus > svg,
        :root[data-theme="burton"] .creature.whale > svg,
        :root[data-theme="burton"] .creature.angler-deep > svg { display: none; }
        :root[data-theme="burton"] .fish > .gothic-art,
        :root[data-theme="burton"] .creature.octopus > .gothic-art,
        :root[data-theme="burton"] .creature.whale > .gothic-art { display: block; }
        /* Same scale-up as the whale SVG so it reads at distance. The
           cross-reverse path mirrors the creature (scaleX(-1)) while it
           swims right → left, so pre-flip the head-left art to keep the
           head leading. */
        :root[data-theme="burton"] .creature.whale > .gothic-art {
            transform: scale(-1.55, 1.55);
            animation: whale-glide 6s ease-in-out infinite;
        }
        @keyframes whale-glide {
            0%, 100% { transform: scale(-1.55, 1.55) translateY(0)    rotate(0deg); }
            50%      { transform: scale(-1.55, 1.55) translateY(-4px) rotate(1deg); }
        }
        :root[data-theme="burton"] .fish > .gothic-art { animation: bob 2.4s ease-in-out infinite; }
        :root[data-theme="burton"] .creature.octopus > .gothic-art {
            transform-origin: 50% 30%;
            animation: octopus-sway 3.6s ease-in-out infinite;
        }
        :root[data-theme="burton"] .creature.octopus.mini > .gothic-art { animation-duration: 2.4s; }
        :root[data-theme="burton"] .fish,
        :root[data-theme="burton"] .creature.octopus,
        :root[data-theme="burton"] .creature.whale { filter: brightness(0.92) drop-shadow(0 4px 8px rgba(0,0,0,0.5)); }
        /* Deep anglerfish: illustrated art with a flickering lure bulb. */
        :root[data-theme="burton"] .creature.angler-deep > .gothic-art {
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
        /* Illustrated gothic jellyfish replace the CSS glow blobs. */
        :root[data-theme="burton"] .jelly::before,
        :root[data-theme="burton"] .jelly::after { display: none; }
        :root[data-theme="burton"] .jelly > .gothic-art {
            display: block;
            filter: drop-shadow(0 0 10px rgba(236,230,216,0.45));
        }
        :root[data-theme="burton"] .deep .jelly,
        :root[data-theme="burton"] .abyss .jelly { opacity: 0.7; }
        /* Swap the cartoon hull for the illustrated gothic sub. */
        .sub-gothic { display: none; }
        :root[data-theme="burton"] .sub-body   { display: none; }
        :root[data-theme="burton"] .sub-gothic { display: inline; }
        /* Tall lantern mast: sit a little lower before the dive so it clears
           the recent-crashes bar. */
        :root[data-theme="burton"] .waiting .sub-wrap,
        :root[data-theme="burton"] .betting .sub-wrap { top: 35%; }
        :root[data-theme="burton"] .sub-wake,
        :root[data-theme="burton"] .sub-wake-core { filter: grayscale(0.8); }
        :root[data-theme="burton"] .sub-bubbles { fill: rgba(236,230,216,0.6); }
        .gothic-lantern-glow { animation: lantern-flicker 2.4s ease-in-out infinite; }
        @keyframes lantern-flicker {
            0%, 100% { opacity: 0.9; }
            40%      { opacity: 0.65; }
            55%      { opacity: 1; }
            70%      { opacity: 0.75; }
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
            animation: rise calc(var(--base) * var(--speed)) linear infinite;
        }
        .waiting  .bubble { --speed: 1.7; --drift-x: -15px; }
        .betting  .bubble { --speed: 1.3; --drift-x: -25px; }
        .running  .bubble { --speed: 0.35; --drift-x: -55vw; }
        .deep     .bubble { --speed: 0.22; --drift-x: -70vw; }
        .abyss    .bubble { --speed: 0.15; --drift-x: -85vw; }
        .critical .bubble { --speed: 0.10; --drift-x: -100vw; }
        @keyframes rise {
            0%   { transform: translate(0, 110vh) scale(0.6);  opacity: 0; }
            8%   { opacity: 0.85; }
            50%  { transform: translate(calc(var(--drift-x) * 0.5), 50vh) scale(1.0); }
            92%  { opacity: 0.7; }
            100% { transform: translate(var(--drift-x), -10vh) scale(1.15); opacity: 0; }
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
            transform: translate(-50%, -50%);
            width: 346px; height: 187px;
            /* Faster, punchier plunge — ease-in so each depth threshold feels
               like gravity pulls the sub down rather than a uniform drift. */
            transition: top 2.5s cubic-bezier(.42, 0, .35, 1), transform 0.15s ease;
            filter: drop-shadow(0 26px 36px rgba(0,0,0,0.55));
            z-index: 7;  /* always above sea life (creatures z:4, fish z:4, jellyfish, bubbles), below HUD (z:8+) */
        }
        .running .sub-wrap   { top: 55%; }
        .deep .sub-wrap      { top: 65%; }
        .abyss .sub-wrap     { top: 70%; }
        .critical .sub-wrap  { top: 72%; animation: shake 0.18s linear infinite; }
        @keyframes shake {
            0%,100% { transform: translate(-50%,-50%); }
            20%     { transform: translate(-52%,-49%); }
            50%     { transform: translate(-48%,-51%); }
            80%     { transform: translate(-51%,-50%); }
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

        /* ---------- Recent crashes history ---------- */
        .history-wrap {
            position: absolute;
            top: 18px; left: 50%;
            transform: translateX(-50%);
            display: flex; flex-direction: column; align-items: center;
            gap: 4px;
            z-index: 9;
            max-width: 96vw;
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
        .history-chip.tier-low  { background: rgba(239,68,68,0.20);  color: #fecaca; }
        .history-chip.tier-mid  { background: rgba(251,191,36,0.20); color: #fef3c7; }
        .history-chip.tier-high { background: rgba(34,197,94,0.20);  color: #bbf7d0; }
        .history-chip.tier-epic {
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
            .history-wrap { top: 14px; gap: 3px; max-width: 94vw; }
            .history-label { font-size: 9px; letter-spacing: 0.24em; }
            .history {
                gap: 4px; padding: 5px 8px;
                max-width: 94vw;
                /* Cleaner than `overflow: hidden` here — flexbox will shrink
                   each chip down rather than mid-cropping the rightmost one. */
                flex-wrap: nowrap;
            }
            .history-chip {
                font-size: 11px; padding: 3px 7px;
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
            display: inline-flex; align-items: center; gap: 9px;
            margin: 0; padding: 6px 16px 6px 12px;
            font-size: 12px; font-weight: 700; letter-spacing: 0.24em;
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
            content: ""; flex: none; width: 8px; height: 8px; border-radius: 50%;
            background: var(--dot); box-shadow: 0 0 8px var(--dot);
        }
        .betting .state { --dot: var(--gold); }
        .running .state { --dot: var(--good); }
        .running .state::before { animation: state-live 1s ease-in-out infinite; }
        .critical .state, .crashed .state { --dot: var(--warn); border-color: rgba(239,68,68,0.45); }
        @keyframes state-live { 50% { opacity: 0.35; transform: scale(0.7); } }

        /* ---------- Warnings (only critical) ---------- */
        .warnings {
            position: absolute; top: 30%; left: 50%;
            transform: translate(-50%, 0);
            display: flex; flex-wrap: wrap; justify-content: center; gap: 8px;
            width: max-content; max-width: calc(100vw - 32px);
            pointer-events: none;
            z-index: 9;
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

        /* ---------- Audio mute toggle (next to fullscreen) ---------- */
        .audio-btn {
            position: fixed;
            top: 14px; right: 56px;
            z-index: 40;
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
        .audio-btn:hover {
            background: rgba(var(--panel-rgb),0.85);
            box-shadow: 0 0 18px rgba(var(--hud-rgb),0.45);
            color: var(--hud-bright);
        }
        .audio-btn:active { transform: translateY(1px); }
        .audio-btn svg { width: 16px; height: 16px; display: block; pointer-events: none; }
        .audio-btn .audio-icon-mute { display: none; }
        .audio-btn.is-muted { color: var(--text-muted); }
        .audio-btn.is-muted .audio-icon-on { display: none; }
        .audio-btn.is-muted .audio-icon-mute { display: block; }

        /* ---------- Fullscreen toggle (top-right) ---------- */
        .fs-btn {
            position: fixed;
            top: 14px; right: 14px;
            z-index: 40;
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
        .fs-btn:hover {
            background: rgba(var(--panel-rgb),0.85);
            box-shadow: 0 0 18px rgba(var(--hud-rgb),0.45);
            color: var(--hud-bright);
        }
        .fs-btn:active { transform: translateY(1px); }
        .fs-btn svg { width: 16px; height: 16px; display: block; pointer-events: none; }
        .fs-btn .fs-icon-exit { display: none; }
        :fullscreen .fs-btn .fs-icon-enter,
        body.is-fullscreen .fs-btn .fs-icon-enter { display: none; }
        :fullscreen .fs-btn .fs-icon-exit,
        body.is-fullscreen .fs-btn .fs-icon-exit { display: block; }

        /* ---------- Theme picker (top-right, left of the audio toggle) ---------- */
        .theme-btn {
            position: fixed;
            top: 14px; right: 98px;
            z-index: 40;
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
        .theme-btn:hover,
        .theme-btn[aria-expanded="true"] {
            background: rgba(var(--panel-rgb),0.85);
            box-shadow: 0 0 18px rgba(var(--hud-rgb),0.45);
            color: var(--hud-bright);
        }
        .theme-btn:active { transform: translateY(1px); }
        .theme-btn svg { width: 16px; height: 16px; display: block; pointer-events: none; }

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
        body.embedded .operator-panel[data-mode="cashout"] .op-stepper,
        body.embedded .operator-panel[data-mode="cashout"] .op-bet-btn { display: none; }
        body.embedded .operator-panel[data-mode="place"]   .op-cashout-btn { display: none; }

        /* Hide legacy cashout button + nudge the in-game submarine HUD up. */
        body.embedded .cashout-wrap { display: none; }
        body.embedded .sub-wrap { transform: translate(-50%, calc(-50% - var(--op-bar-h) * 0.45)); }
        body.embedded .sonar    { bottom: calc(110px + var(--op-bar-h)); }

        /* Mobile (<720px) — bar wraps into rows so 360-414px screens can
           still place a bet without horizontal overflow. Layout becomes:
             row 1: balance · phase · status
             row 2: stake stepper · auto stepper
             row 3: Place Bet · AUTO BET · Cash Out
           In cashout mode the inputs row collapses (display:none on inputs),
           so the bar effectively shrinks to 2 rows + a giant Cash Out CTA. */
        @media (max-width: 719px) {
            body.embedded { --op-bar-h: 92px; }
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

            body.embedded .op-id { gap: 6px; min-width: 0; flex-shrink: 1; }
            body.embedded .op-id .op-name {
                max-width: 64px;
                overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
                font-size: 11px;
            }
            body.embedded .op-id .op-balance {
                font-size: 12px;
                max-width: 110px;
                overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
            }
            body.embedded .op-phase { font-size: 8px; padding: 2px 6px; letter-spacing: 0.18em; flex-shrink: 0; }
            /* Status forced to single-line ellipsis on the right of row 1 — it
               must not wrap onto a new row or the bar overflows --op-bar-h. */
            body.embedded .op-status {
                font-size: 9px;
                margin-left: auto;
                text-align: right;
                min-width: 0;
                flex: 1 1 0;
                max-width: 140px;
                overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
            }

            /* Bet row breaks onto its own line(s) and wraps internally. */
            body.embedded .op-bet-row {
                flex: 1 0 100%;
                flex-wrap: wrap;
                gap: 6px;
                justify-content: stretch;
            }

            /* Compact stepper inputs so [stake][auto][PLACE BET][AUTO] all fit
               on row 2 even at 360px viewport width. */
            body.embedded .op-input { font-size: 12px; padding: 4px 4px; }
            body.embedded .op-input[data-kind="stake"] { width: 42px; }
            body.embedded .op-input[data-kind="auto"]  { width: 42px; }
            body.embedded .op-stepper-auto .op-input   { width: 42px; }
            body.embedded .op-step  { padding: 0 6px; font-size: 13px; }
            body.embedded .op-clear { padding: 0 6px; font-size: 11px; }

            /* Tight bet row — stays on one line in place mode. */
            body.embedded .op-bet-row { gap: 5px; flex-wrap: nowrap; overflow: hidden; }
            body.embedded .op-btn { font-size: 10px; padding: 0 8px; height: 30px; min-width: 0; }
            body.embedded .op-bet-btn      { padding: 0 10px; flex: 0 1 auto; }
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
            body.embedded .operator-panel[data-mode="cashout"] .op-cashout-btn {
                height: 48px; font-size: 16px;
            }
            body.embedded .operator-panel[data-mode="cashout"] .op-autobet-btn {
                height: 48px;
            }
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
            body.embedded .op-cashout-btn                  { font-size: 14px; height: 40px; min-width: 180px; }
            body.embedded .op-cashout-btn .op-cashout-x    { font-size: 12px; }
            body.embedded .op-status                       { font-size: 11px; max-width: 260px; }
            body.embedded .op-last                         { padding: 5px 12px; }
            body.embedded .op-last-mult                    { font-size: 15px; }
            body.embedded .op-last-payout                  { font-size: 14px; }
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
            animation: crash-flash 0.9s ease-out forwards;
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
            animation: shockwave 1.1s cubic-bezier(0.2, 0.8, 0.4, 1) forwards;
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
            animation: frag-fly 1.4s cubic-bezier(0.15, 0.85, 0.4, 1) forwards;
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
            animation: cracks-in 1.6s ease-out forwards;
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
        .crashed .crash-banner { animation: banner-in 1.7s cubic-bezier(0.2, 0.9, 0.3, 1) forwards; }
        .crashed .crash-banner span { animation: banner-glitch 0.35s steps(1) 0.08s; }
        .crashed .crash-banner b { animation: banner-line 0.45s ease-out 0.12s forwards; }
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
        .crashed .stage { animation: stage-rumble 0.9s linear; }
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

        /* (7) Submarine: a punch on impact, then sinks away while (gothic skin)
           the hull tears in two along a jagged seam and the halves tumble apart.
           Half offsets are in the sub's own (nose-down tilted) frame, so they
           read as back → left, front → right on screen. */
        .crashed .sub-wrap {
            animation: sub-collapse 1.6s cubic-bezier(0.3, 0.1, 0.5, 1) forwards;
        }
        @keyframes sub-collapse {
            0%   { transform: translate(-50%, -50%) scale(1); opacity: 1; }
            8%   { transform: translate(-50%, -50%) scale(1.07); }
            70%  { opacity: 1; }
            100% { transform: translate(-50%, -38%) scale(0.92); opacity: 0; }
        }
        .sub-half { transition: none; }
        .crashed .sub-half-back  { animation: half-back 1.5s cubic-bezier(0.2, 0.6, 0.4, 1) forwards; }
        .crashed .sub-half-front { animation: half-front 1.5s cubic-bezier(0.2, 0.6, 0.4, 1) forwards; }
        @keyframes half-back {
            0%   { transform: none; }
            10%  { transform: translate(-6px, 2px) rotate(-4deg); }
            100% { transform: translate(-72px, 44px) rotate(-42deg); }
        }
        @keyframes half-front {
            0%   { transform: none; }
            10%  { transform: translate(6px, -2px) rotate(5deg); }
            100% { transform: translate(84px, -28px) rotate(48deg); }
        }
        .sub-half-back  { transform-origin: 112px 40px; }
        .sub-half-front { transform-origin: 114px 40px; }
        .sub-rupture { opacity: 0; transform-origin: 113px 38px; }
        .crashed .sub-rupture { animation: rupture 0.9s ease-out forwards; }
        @keyframes rupture {
            0%   { opacity: 0; transform: scale(0.4, 0.6); }
            12%  { opacity: 1; transform: scale(1.3, 1.1); }
            100% { opacity: 0; transform: scale(2.2, 1.4); }
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
        .is-sub { position: relative; display: block; width: 100%; height: auto; filter: blur(4px) brightness(0.2); }
        .intro.play .is-sub { animation: is-sub-clear 0.8s ease-out 0.3s forwards; }
        @keyframes is-sub-clear { to { filter: blur(0) brightness(1) drop-shadow(0 0 18px rgba(214, 236, 120, 0.25)); } }

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
        .is-title span {
            display: inline-block; opacity: 0; color: transparent;
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
            <img class="is-whale" src="{{ asset('images/whale-gothic.png') }}" alt="">
            <img class="is-fish is-fish-1" src="{{ asset('images/fish-gothic.png') }}" alt="">
            <img class="is-fish is-fish-2" src="{{ asset('images/fish-puffer-gothic.png') }}" alt="">
            <div class="is-layer is-pings"><i style="--n: 0"></i><i style="--n: 1"></i><i style="--n: 2"></i><i style="--n: 3.3"></i></div>
            <div class="is-layer is-sub-path">
                <div class="is-sub-fly">
                    <div class="is-sub-bob">
                        <img class="is-sub" src="{{ asset('images/sub-gothic.png') }}" width="856" height="507" alt="">
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
        // blocks the game — a load error, a reduced-motion preference or a
        // repeat visit in the same session just skips it.
        (function studioIntro(cfg) {
            const $intro = document.getElementById('intro');
            const SEEN_KEY = 'orange.intro.seen';
            const SCENE_MS = 4250; // matches the CSS timeline (warp dive ends at ~4.3s)
            const seen = () => { try { return sessionStorage.getItem(SEEN_KEY) === '1'; } catch (_) { return false; } };
            const skipReason = (cfg.oncePerSession && seen()) ? 'already shown in this session'
                : window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'device prefers reduced motion'
                : null;
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
                }).catch(finish);
                return;
            }

            // Scene: wait for the artwork (incl. the CSS background) so nothing pops in mid-animation.
            const bg = new Image();
            bg.src = portrait ? cfg.bgPortrait : cfg.bgLandscape;
            const art = [bg, ...$intro.querySelectorAll('img')];
            Promise.race([Promise.allSettled(art.map(decode)), timeout]).then(() => {
                if (finished) return;
                $intro.classList.add('play');
                timers.push(setTimeout(finish, SCENE_MS));
            }).catch(finish);
        })(@json($intro));
    </script>
    @endif

    <button id="theme-btn" class="theme-btn" type="button" title="Change theme" aria-label="Change theme"
            aria-haspopup="true" aria-expanded="false" aria-controls="theme-menu">
        <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M8 1.6C4.5 1.6 1.6 4.5 1.6 8s2.9 6.4 6.4 6.4c.7 0 1.2-.5 1.2-1.2 0-.3-.1-.6-.3-.8-.2-.2-.3-.5-.3-.8 0-.7.5-1.2 1.2-1.2h1.4c1.9 0 3.4-1.5 3.4-3.4C14.6 4.1 11.6 1.6 8 1.6Z"/>
            <circle cx="4.9" cy="7.2" r="0.9" fill="currentColor" stroke="none"/>
            <circle cx="6.9" cy="4.6" r="0.9" fill="currentColor" stroke="none"/>
            <circle cx="10.2" cy="4.8" r="0.9" fill="currentColor" stroke="none"/>
            <circle cx="12"  cy="7.6" r="0.9" fill="currentColor" stroke="none"/>
        </svg>
    </button>
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

    <button id="audio-btn" class="audio-btn" type="button" title="Toggle sound" aria-label="Toggle sound">
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

    <button id="fs-btn" class="fs-btn" type="button" title="Toggle fullscreen" aria-label="Toggle fullscreen">
        <svg class="fs-icon-enter" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M2 6V2h4M14 6V2h-4M2 10v4h4M14 10v4h-4"/>
        </svg>
        <svg class="fs-icon-exit" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M6 2v4H2M10 2v4h4M6 14v-4H2M10 14v-4h4"/>
        </svg>
    </button>

    <div id="stage" class="stage waiting">
        <!-- Filtered scene (depth darkening lives here) -->
        <div class="scene">
            <div class="rays"></div>
            <div id="particles" class="particles"></div>
            <div class="caustics"></div>
            <div id="bubbles"  class="bubbles"></div>
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
                    <img class="gothic-art" src="{{ asset('images/fish-gothic.png') }}" width="69" height="39" alt="" aria-hidden="true" loading="lazy">
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
                    <img class="gothic-art" src="{{ asset('images/fish-puffer-gothic.png') }}" width="50" height="39" alt="" aria-hidden="true" loading="lazy">
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
                    <img class="gothic-art" src="{{ asset('images/fish-angler-gothic.png') }}" width="61" height="36" alt="" aria-hidden="true" loading="lazy">
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
                    <img class="gothic-art" src="{{ asset('images/fish-gothic.png') }}" width="48" height="27" alt="" aria-hidden="true" loading="lazy">
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
                    <img class="gothic-art" src="{{ asset('images/fish-puffer-gothic.png') }}" width="52" height="40" alt="" aria-hidden="true" loading="lazy">
                </div>
            </div>

            <div id="creatures" class="creatures">
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
                    <img class="gothic-art" src="{{ asset('images/whale-gothic.png') }}" width="340" height="106" alt="" aria-hidden="true">
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
                    <span class="gothic-art" aria-hidden="true">
                        <img src="{{ asset('images/angler-deep-gothic.png') }}" width="170" height="81" alt="" loading="lazy">
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
                    <img class="gothic-art" src="{{ asset('images/octopus-gothic.png') }}" width="190" height="143" alt="" aria-hidden="true" loading="lazy">
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
                    <img class="gothic-art" src="{{ asset('images/octopus-gothic.png') }}" width="{{ (int) ($o['size'] * 1.4) }}" height="{{ (int) ($o['size'] * 1.05) }}" alt="" aria-hidden="true" loading="lazy">
                </div>
                @endforeach
            </div>

            <div class="jelly" style="left:12%; top:50%; animation-delay:-2s;"><img class="gothic-art" src="{{ asset('images/jelly-gothic.png') }}" width="60" height="86" alt="" aria-hidden="true" loading="lazy"></div>
            <div class="jelly" style="left:80%; top:35%; animation-delay:-5s;"><img class="gothic-art" src="{{ asset('images/jelly-gothic.png') }}" width="60" height="86" alt="" aria-hidden="true" loading="lazy"></div>
            <div class="jelly" style="left:25%; top:70%; animation-delay:-7s;"><img class="gothic-art" src="{{ asset('images/jelly-gothic.png') }}" width="60" height="86" alt="" aria-hidden="true" loading="lazy"></div>

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
                        {{-- Drawn as two halves meeting on a jagged seam; on a crash they tear apart. --}}
                        <defs>
                            <clipPath id="subCutBack"><path d="M-12 -40 H119 L111 -12 L121 8 L108 30 L117 52 L105 76 L114 112 H-12 Z"/></clipPath>
                            <clipPath id="subCutFront"><path d="M118.4 -40 H245 V112 H113.4 L104.4 76 L116.4 52 L107.4 30 L120.4 8 L110.4 -12 Z"/></clipPath>
                            <radialGradient id="subRuptureGlow">
                                <stop offset="0%"   stop-color="#fff7d6" stop-opacity="1"/>
                                <stop offset="35%"  stop-color="#fbbf24" stop-opacity="0.8"/>
                                <stop offset="100%" stop-color="#ef4444" stop-opacity="0"/>
                            </radialGradient>
                        </defs>
                        <g class="sub-half sub-half-back" clip-path="url(#subCutBack)">
                            <image href="{{ asset('images/sub-gothic.png') }}" x="-5" y="-32" width="232" height="137.4" preserveAspectRatio="xMidYMid meet"/>
                        </g>
                        <g class="sub-half sub-half-front">
                            <g clip-path="url(#subCutFront)">
                                <image href="{{ asset('images/sub-gothic.png') }}" x="-5" y="-32" width="232" height="137.4" preserveAspectRatio="xMidYMid meet"/>
                            </g>
                            <circle class="gothic-lantern-glow" cx="171" cy="-5" r="20" fill="url(#gothicLanternGlow)"/>
                        </g>
                        <ellipse class="sub-rupture" cx="113" cy="38" rx="16" ry="58" fill="url(#subRuptureGlow)"/>
                    </g>
                </svg>
            </div>
        </div>

        <!-- Strobe sits above the scene but below the UI -->
        <div class="strobe"></div>

        <!-- Recent crashes -->
        <div class="history-wrap" aria-label="Recent crashes">
            <div class="history-label">Recent crashes</div>
            <div id="history" class="history"></div>
        </div>

        <!-- State + multiplier with cockpit gauges flanking the readout -->
        <div class="center">
            <div id="state" class="state">Awaiting next dive…</div>
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

        <!-- Critical warnings -->
        <div class="warnings">
            <div><i>!</i>Pressure critical</div>
            <div><i>!</i>Hull damage detected</div>
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

            <div class="op-bet-row">
                <div class="op-stepper">
                    <button id="op-stake-down" class="op-step" type="button" aria-label="Decrease stake">−</button>
                    <input id="op-stake" class="op-input" data-kind="stake" type="number" step="0.01" min="0.01" value="10" inputmode="decimal" aria-label="Stake">
                    <button id="op-stake-up"   class="op-step" type="button" aria-label="Increase stake">+</button>
                </div>
                <div class="op-stepper op-stepper-auto">
                    <button id="op-auto-down" class="op-step" type="button" aria-label="Decrease auto cashout">−</button>
                    <input id="op-auto" class="op-input" data-kind="auto" type="number" step="0.01" min="1.01" placeholder="auto ×" inputmode="decimal" aria-label="Auto cashout">
                    <button id="op-auto-up"   class="op-step" type="button" aria-label="Increase auto cashout">+</button>
                    <button id="op-auto-clear" class="op-clear" type="button" aria-label="Clear auto cashout" title="Clear">×</button>
                </div>
                <button id="op-bet" class="op-btn op-bet-btn" disabled>Place Bet</button>
                <button id="op-autobet" class="op-btn op-autobet-btn" type="button"
                        data-active="false" aria-pressed="false"
                        title="When ON, automatically place a bet every round with the current stake + auto-cashout.">
                    <span class="op-autobet-label-full">AUTO BET</span>
                    <span class="op-autobet-label-short">AUTO</span>
                    <span class="op-autobet-dot" aria-hidden="true"></span>
                </button>
                <button id="op-cashout" class="op-btn op-cashout-btn" disabled>
                    Cash Out
                    <span class="op-cashout-x" id="op-cashout-x"></span>
                </button>
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

        // rising bubbles (64, varying size + base speed). --base is read by
        // the .bubble animation rule via calc(--base * --speed); the parent
        // stage class drives --speed, so the whole field accelerates upward
        // during deeper dive states without resetting individual bubbles.
        const bubbleHost = document.getElementById('bubbles');
        for (let i = 0; i < 64; i++) {
            const b = document.createElement('div');
            b.className = 'bubble';
            const size = 4 + Math.random() * 14;
            b.style.width = b.style.height = size + 'px';
            // Spawn from a wider band including offscreen-right so the leftward
            // dive drift keeps the visible area populated; without this the
            // right side empties out as soon as the sub starts moving.
            b.style.left = (-10 + Math.random() * 150) + 'vw';
            b.style.setProperty('--base', (6 + Math.random() * 10) + 's');
            b.style.animationDelay = (-Math.random() * 12) + 's';
            bubbleHost.appendChild(b);
        }

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
                f.style.animationDelay = (Math.random() * 0.18) + 's';
                f.style.width = f.style.height = (3 + Math.random() * 8) + 'px';
                debrisHost.appendChild(f);
            }
        }
        rebuildDebris();

        // ---------- Recent crash history (top chip rail) ----------
        const HISTORY_MAX = 8;
        const $history = document.getElementById('history');
        const history = @json($recentCrashes ?? []);

        function tierClassFor(cp) {
            if (cp >= 10) return 'tier-epic';
            if (cp >= 3)  return 'tier-high';
            if (cp >= 1.5) return 'tier-mid';
            return 'tier-low';
        }
        function renderHistory(highlightFirst = false) {
            $history.innerHTML = history.map((cp, i) =>
                `<span class="history-chip ${tierClassFor(cp)}${highlightFirst && i === 0 ? ' is-new' : ''}">${cp.toFixed(2)}x</span>`
            ).join('');
        }
        function pushHistory(cp) {
            history.unshift(cp);
            if (history.length > HISTORY_MAX) history.length = HISTORY_MAX;
            renderHistory(true);
        }
        renderHistory();

        const log = (msg) => {
            const line = document.createElement('div');
            line.textContent = `${new Date().toLocaleTimeString()}  ${msg}`;
            $log.prepend(line);
            while ($log.children.length > 6) $log.lastChild.remove();
        };

        let stageKey = '';
        function setStage(state) {
            stageKey = state.join(' ');
            $stage.classList.remove('waiting', 'betting', 'running', 'deep', 'abyss', 'critical', 'crashed');
            for (const s of state) $stage.classList.add(s);
        }

        function updateMultiplierUI(m) {
            $mult.textContent = m.toFixed(2) + 'x';
            // scale 1.00x → 200m / 0%, 25.00x → 1500m / red zone
            const norm = Math.min(1, Math.max(0, (m - 1) / 24));
            const pressure = Math.round(200 + norm * 1300);
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
                    log(`dive started · growth ${e.growth_rate}/s`);
                })
                .listen('.round.tick', (e) => {
                    if ($stage.classList.contains('crashed')) return;
                    const m = Number(e.multiplier ?? e.data?.multiplier ?? NaN);
                    if (!Number.isFinite(m)) return;
                    tweenMultiplier(m);
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
                        setStage(['crashed', 'critical']);
                        if (Number.isFinite(cp)) {
                            $mult.textContent = cp.toFixed(2) + 'x';
                            $state.textContent = `IMPLODED @ ${cp.toFixed(2)}x`;
                            log(`💥 imploded @ ${cp.toFixed(2)}x`);
                            pushHistory(cp);
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
                });
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
        // Audio engine — procedural SFX + 8-bit chiptune background music.
        // No external assets. AudioContext is gated behind first user gesture
        // (browser autoplay policy); a persistent mute toggle lives top-right.
        // ----------------------------------------------------------------------
        const audio = (function () {
            let ctx, master, musicTimer, musicStep = 0, musicEnabled = true, started = false;
            const STORAGE_KEY = 'orange.audio.muted';

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
                if (ambientNodes.length) return; // already running
                init();

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

            ['click', 'touchstart', 'keydown'].forEach(ev =>
                document.addEventListener(ev, unlock, { once: false, passive: true }));

            document.getElementById('audio-btn')?.addEventListener('click', (e) => {
                e.stopPropagation();
                unlock();
                setMuted(!isMuted());
            });

            // Reflect initial mute state in icon
            document.getElementById('audio-btn')?.classList.toggle('is-muted', isMuted());

            return { sfx, startMusic, stopMusic };
        })();

        // ---- Wire SFX to the round + operator-panel events ----
        window.Echo?.channel('crash')
            .listen('.round.betting_opened', () => audio.sfx('tick'))
            .listen('.round.started',        () => audio.sfx('start'))
            .listen('.round.crashed',        () => audio.sfx('crash'));

        // The operator-panel buttons exist only when embedded; guard with ?.
        document.getElementById('op-bet')    ?.addEventListener('click', () => audio.sfx('bet'));
        document.getElementById('op-cashout')?.addEventListener('click', () => audio.sfx('win'));

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
            const allowedParentOrigins = @json(array_values((array) config('embed.allowed_origins', [])));

            const $panel    = document.getElementById('op-panel');
            const $player   = document.getElementById('op-player');
            const $balance  = document.getElementById('op-balance');
            const $phaseChip = document.getElementById('op-phase');
            const $stake    = document.getElementById('op-stake');
            const $auto     = document.getElementById('op-auto');
            const $bet      = document.getElementById('op-bet');
            const $co       = document.getElementById('op-cashout');
            const $coX      = document.getElementById('op-cashout-x');
            const $autoBet  = document.getElementById('op-autobet');
            const $status   = document.getElementById('op-status');
            const $last     = document.getElementById('op-last');
            const $lastMult = document.getElementById('op-last-mult');
            const $lastPay  = document.getElementById('op-last-payout');
            $panel.setAttribute('aria-hidden', 'false');

            function showLast(kind, multiplier, payout) {
                $last.classList.add('visible');
                $last.classList.toggle('loss', kind === 'loss');
                const m = Number(multiplier);
                $lastMult.textContent = Number.isFinite(m) ? m.toFixed(2) + '×' : '—';
                $lastPay.textContent = kind === 'win' ? '+' + trimAmount(payout) : 'lost';
            }

            // Strip trailing zeros after the decimal point so "673.99000000"
            // renders as "673.99" — the operator's wallet returns 8-decimal
            // precision but the tail is just noise to the player.
            function trimAmount(value) {
                if (value === null || value === undefined) return '—';
                const s = String(value);
                return s.includes('.') ? s.replace(/0+$/, '').replace(/\.$/, '') : s;
            }

            // Spawn a "× 1.30   +10.30 EUR" toast over the stage that drifts
            // upward and fades. Re-entrant: stacking multiple cashouts will
            // overlap visually (rare in practice since only one bet/round).
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
                amtSpan.textContent = `+${trimAmount(payout)}${opState.currency ? ' ' + opState.currency : ''}`;
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
                betId: null,
                betRoundUuid: null,
                playerId: null,
                autoCashoutAt: null,       // threshold sent with the active bet
                autoCashoutFired: false,   // local guard: already asked parent to cashout
                autoBet: false,            // when true, fire a bet on every betting_opened
                lastAutoBetRound: null,    // round_uuid we last fired auto-bet for (de-dupe guard)
            };

            // Restore AUTO BET toggle across reloads. Keyed by host so a user
            // sharing the machine doesn't accidentally inherit someone else's
            // armed state; not playerId because that arrives later via postMessage.
            const AUTOBET_KEY = 'orange.embed.autobet';
            try {
                if (localStorage.getItem(AUTOBET_KEY) === '1') opState.autoBet = true;
            } catch (_) { /* privacy mode / quota */ }

            function setAutoBet(enabled, reason = '') {
                opState.autoBet = !!enabled;
                $autoBet.dataset.active = enabled ? 'true' : 'false';
                $autoBet.setAttribute('aria-pressed', enabled ? 'true' : 'false');
                try {
                    if (enabled) localStorage.setItem(AUTOBET_KEY, '1');
                    else localStorage.removeItem(AUTOBET_KEY);
                } catch (_) {}
                if (reason) setStatus(reason, enabled ? 'ok' : 'err');
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
                const canBet     = (opState.phase === 'betting' && !opState.betId && opState.parentOrigin);
                const canCashout = (opState.phase === 'running' && opState.betId && opState.betRoundUuid === opState.roundUuid);
                $bet.disabled = !canBet;
                $co.disabled  = !canCashout;
                // Single-CTA mode when a bet is held + round running: hide bet inputs, blow up cashout.
                $panel.dataset.mode = (opState.betId && opState.phase === 'running') ? 'cashout' : 'place';
                $co.classList.toggle('pulse', canCashout);
                applyPhaseChip(opState.phase);
            }

            // Mirror the in-page Echo state machine onto our buttons.
            window.addEventListener('message', (event) => {
                if (!allowedParentOrigins.includes(event.origin)) return;
                if (!event.data || typeof event.data !== 'object') return;
                const { type, payload } = event.data;
                if (!type) return;

                // Lock onto the first parent origin that talked to us.
                if (!opState.parentOrigin) opState.parentOrigin = event.origin;

                switch (type) {
                    case 'player_info':
                        opState.playerId = payload.id;
                        opState.currency = payload.currency;
                        $player.textContent = `#${payload.id} ${payload.username}`;
                        $balance.textContent = `${trimAmount(payload.balance)} ${payload.currency}`;
                        setStatus('connected to operator', 'ok');
                        updateButtons();
                        break;

                    case 'balance_update':
                        $balance.textContent = `${trimAmount(payload.balance)} ${opState.currency || ''}`.trim();
                        break;

                    case 'bet_placed_result':
                        if (payload.success) {
                            opState.betId = payload.bet_id;
                            opState.betRoundUuid = payload.round_uuid;
                            opState.autoCashoutAt = payload.auto_cashout_at ?? opState.autoCashoutAt ?? null;
                            opState.autoCashoutFired = false;
                            setStatus(`bet #${payload.bet_id} placed @ ${payload.amount}`, 'ok');
                        } else {
                            opState.autoCashoutAt = null;
                            // Pause auto-bet on failure — otherwise an insufficient-balance
                            // error (or any structural reject) would loop every round
                            // and spam the operator with rejected calls.
                            if (opState.autoBet) {
                                setAutoBet(false, `bet failed: ${payload.error || 'unknown'} — auto-bet paused`);
                            } else {
                                setStatus(`bet failed: ${payload.error || 'unknown'}`, 'err');
                            }
                        }
                        if (payload.player_balance) $balance.textContent = trimAmount(payload.player_balance) + (opState.currency ? ' ' + opState.currency : '');
                        updateButtons();
                        break;

                    case 'cashout_result':
                        if (payload.success) {
                            setStatus(`cashed out @ ${payload.multiplier}× · payout ${payload.payout}`, 'ok');
                            showLast('win', payload.multiplier, payload.payout);
                            spawnFlyWin(payload.multiplier, payload.payout);
                            opState.betId = null;
                            opState.betRoundUuid = null;
                            opState.autoCashoutAt = null;
                            opState.autoCashoutFired = false;
                        } else if (payload.error === 'round_crashed') {
                            setStatus(`round crashed — bet #${payload.bet_id} lost`, 'err');
                            showLast('loss', payload.error_message?.replace(/[^0-9.]/g, ''), null);
                            opState.betId = null;
                            opState.betRoundUuid = null;
                            opState.autoCashoutAt = null;
                            opState.autoCashoutFired = false;
                        } else if (payload.error === 'already_in_flight') {
                            /* parent already cashing out — ignore the duplicate response */
                        } else {
                            setStatus(`cashout failed: ${payload.error || 'unknown'}`, 'err');
                        }
                        if (payload.player_balance) $balance.textContent = trimAmount(payload.player_balance) + (opState.currency ? ' ' + opState.currency : '');
                        updateButtons();
                        break;

                    case 'auto_cashout_fired':
                        opState.autoCashoutFired = true;
                        setStatus(`auto-cashout @ ${Number(payload.multiplier).toFixed(2)}×…`, 'ok');
                        $co.disabled = true;
                        break;

                    case 'round_state':
                        opState.phase = payload.phase;
                        opState.roundUuid = payload.round_uuid;
                        // If a new round started while we were holding a bet from the previous one,
                        // forget it — that bet can't be cashed out against this round.
                        if (opState.betRoundUuid && opState.betRoundUuid !== payload.round_uuid) {
                            opState.betId = null;
                            opState.betRoundUuid = null;
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
                    if (opState.betRoundUuid && opState.betRoundUuid !== e.round_uuid) {
                        opState.betId = null;
                        opState.betRoundUuid = null;
                    }
                    updateButtons();

                    // AUTO BET: if armed and we don't already hold a bet for this
                    // round, fire one. lastAutoBetRound de-dupes against the rare
                    // case where betting_opened arrives twice (e.g. reconnect).
                    if (
                        opState.autoBet &&
                        !opState.betId &&
                        opState.parentOrigin &&
                        opState.lastAutoBetRound !== e.round_uuid
                    ) {
                        opState.lastAutoBetRound = e.round_uuid;
                        triggerBet();
                    }
                })
                .listen('.round.started',   () => { opState.phase = 'running';  updateButtons(); })
                .listen('.round.tick', (e) => {
                    const m = Number(e.multiplier);
                    if (Number.isFinite(m)) $coX.textContent = '@ ' + m.toFixed(2) + '×';

                    // Local auto-cashout safety net: if the parent is slow, the
                    // iframe also fires once when the threshold is crossed. The
                    // autoCashoutFired flag + parent's cashingOut guard ensure
                    // we don't double-send.
                    const at = opState.autoCashoutAt;
                    if (
                        opState.betId &&
                        opState.parentOrigin &&
                        !opState.autoCashoutFired &&
                        Number.isFinite(at) && at > 1 &&
                        m >= at
                    ) {
                        opState.autoCashoutFired = true;
                        setStatus(`auto-cashout @ ${m.toFixed(2)}×…`, 'ok');
                        $co.disabled = true;
                        postToParent({ type: 'cashout', payload: { bet_id: opState.betId } });
                    }
                })
                .listen('.round.crashed',   () => { opState.phase = 'crashed';  $coX.textContent = '—'; updateButtons(); })
                .listen('.round.settled',   () => { opState.phase = 'waiting';  opState.betId = null; opState.betRoundUuid = null; $coX.textContent = '—'; updateButtons(); });

            // Stake stepper buttons — quick − / + around the stake input.
            // Step size scales with current value so big bets aren't tedious.
            const $stakeDown = document.getElementById('op-stake-down');
            const $stakeUp   = document.getElementById('op-stake-up');
            function stepStake(direction) {
                const cur = parseFloat($stake.value);
                const base = Number.isFinite(cur) && cur > 0 ? cur : 0;
                const step = base >= 100 ? 10 : base >= 20 ? 5 : 1;
                const next = Math.max(0.01, +(base + direction * step).toFixed(2));
                $stake.value = next;
                $stake.dispatchEvent(new Event('input', { bubbles: true }));
            }
            $stakeDown?.addEventListener('click', () => stepStake(-1));
            $stakeUp  ?.addEventListener('click', () => stepStake(+1));

            // Auto cashout stepper — 0.10× per click, snaps to nearest tenth.
            // From empty, the first + jumps to 1.50× (a sensible default);
            // − below 1.01 (the upstream minimum) just clamps to 1.01.
            const $autoDown  = document.getElementById('op-auto-down');
            const $autoUp    = document.getElementById('op-auto-up');
            const $autoClear = document.getElementById('op-auto-clear');
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
            $autoDown ?.addEventListener('click', () => stepAuto(-1));
            $autoUp   ?.addEventListener('click', () => stepAuto(+1));
            $autoClear?.addEventListener('click', () => {
                $auto.value = '';
                $auto.dispatchEvent(new Event('input', { bubbles: true }));
            });

            function triggerBet() {
                if (!opState.parentOrigin) {
                    setStatus('parent not ready', 'err');
                    return;
                }
                const autoAt = $auto.value ? Number($auto.value) : null;
                opState.autoCashoutAt = Number.isFinite(autoAt) && autoAt > 1 ? autoAt : null;
                opState.autoCashoutFired = false;
                $bet.disabled = true;
                setStatus(opState.autoBet ? 'auto-bet · placing…' : 'placing bet…');
                postToParent({
                    type: 'place_bet',
                    payload: {
                        amount: $stake.value,
                        auto_cashout_at: opState.autoCashoutAt,
                        round_uuid: opState.roundUuid,
                    },
                });
            }

            $bet.addEventListener('click', triggerBet);

            $autoBet.addEventListener('click', () => {
                const next = !opState.autoBet;
                setAutoBet(next, next ? 'auto-bet armed · next round will bet' : 'auto-bet off');
                // If user armed it during the current betting window and has no
                // active bet yet, fire immediately so they don't sit out this round.
                if (
                    next &&
                    opState.phase === 'betting' &&
                    !opState.betId &&
                    opState.parentOrigin &&
                    opState.lastAutoBetRound !== opState.roundUuid
                ) {
                    opState.lastAutoBetRound = opState.roundUuid;
                    triggerBet();
                }
            });

            // Sync UI with any state restored from localStorage.
            if (opState.autoBet) {
                $autoBet.dataset.active = 'true';
                $autoBet.setAttribute('aria-pressed', 'true');
            }

            $co.addEventListener('click', () => {
                if (!opState.betId || !opState.parentOrigin) return;
                $co.disabled = true;
                setStatus('cashing out…');
                postToParent({
                    type: 'cashout',
                    payload: { bet_id: opState.betId },
                });
            });

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
