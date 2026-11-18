/* ---------- The marsh (Lucky Duck) ----------
           Layers, back to front: the sky (day, gold, sunset, dusk: .marsh[data-sky], set by
           the script from the multiplier), stars, clouds, far hills, the water, Lucky, the
           near reeds, the fox. The strips and clouds scroll left as Lucky flies (the script
           translates them); the shared tier filters on .scene don't apply here, the sky does
           the lighting. */
        :root .stage .scene.marsh { background: #8fd3ff; filter: none; overflow: hidden; }
        .marsh {
            --duck-w: clamp(118px, 14vw, 210px);
            --art-dim: none;
        }
        .marsh .sky { position: absolute; inset: 0; background-size: cover; background-position: center bottom; transition: opacity 2.5s ease; }
        .marsh .sky-day { background-image: url("{{ $sceneDef['art']('sky-day-landscape.jpg') }}"); }
        .marsh .sky-sunset { background-image: url("{{ $sceneDef['art']('sky-sunset-landscape.jpg') }}"); opacity: 0; }
        @media (orientation: portrait) {
            .marsh .sky-day { background-image: url("{{ $sceneDef['art']('sky-day-portrait.jpg') }}"); }
            .marsh .sky-sunset { background-image: url("{{ $sceneDef['art']('sky-sunset-portrait.jpg') }}"); }
        }
        /* Golden afternoon: warm light low in the sky. Dusk: night falls over the sunset. */
        .marsh .sky-glow { background: linear-gradient(180deg, rgba(255, 200, 110, 0) 15%, rgba(255, 176, 84, 0.42) 75%, rgba(255, 150, 70, 0.5) 100%); opacity: 0; }
        .marsh .sky-dusk { background: linear-gradient(180deg, #0a0a2c 0%, rgba(34, 22, 84, 0.9) 40%, rgba(110, 52, 104, 0.45) 80%, rgba(160, 80, 90, 0.25) 100%); opacity: 0; }
        .marsh[data-sky="gold"] .sky-glow { opacity: 1; }
        .marsh:is([data-sky="sunset"], [data-sky="dusk"]) .sky-sunset { opacity: 1; }
        .marsh[data-sky="dusk"] .sky-dusk { opacity: 0.85; }
        /* The land and the characters take the light of the sky they are under. */
        .marsh[data-sky="gold"]   { --art-dim: saturate(1.08) sepia(0.12); }
        .marsh[data-sky="sunset"] { --art-dim: brightness(0.94) saturate(1.05) sepia(0.22); }
        .marsh[data-sky="dusk"]   { --art-dim: brightness(0.64) saturate(0.8) hue-rotate(-8deg); }
        .marsh :is(.strip, .cloud, .fox, .duck-bob) { filter: var(--art-dim); transition: filter 2.5s ease; }

        .marsh .stars { position: absolute; inset: 0 0 45% 0; opacity: 0; transition: opacity 2.5s ease; pointer-events: none; }
        .marsh[data-sky="dusk"] .stars { opacity: 1; }
        .marsh .star {
            position: absolute; width: var(--s, 3px); height: var(--s, 3px); border-radius: 50%;
            background: #fffbe6; box-shadow: 0 0 6px rgba(255, 251, 230, 0.9);
            animation: marsh-twinkle var(--t, 2s) ease-in-out var(--d, 0s) infinite alternate;
        }
        @keyframes marsh-twinkle { from { opacity: 0.25; } to { opacity: 1; } }

        /* Clouds: the script moves each by its own --k (further ones slower) and wraps it round. */
        .marsh .clouds { position: absolute; inset: 0; pointer-events: none; }
        .marsh .cloud {
            position: absolute; left: 0; top: var(--y); width: calc(var(--w) * 1vw); height: auto;
            translate: calc(var(--x) * 1vw) 0; will-change: translate; user-select: none;
        }
        @media (orientation: portrait) { .marsh .cloud { width: calc(var(--w) * 1.7vw); } }

        /* Strips: a band of art repeated sideways; <b> is wider than the screen by more than one
           repeat, and the script slides it left by the distance flown, modulo one repeat. */
        .marsh .strip { position: absolute; left: 0; right: 0; bottom: 0; overflow: hidden; pointer-events: none; }
        .marsh .strip b {
            position: absolute; left: 0; top: 0; bottom: 0; width: 400%;
            background-repeat: repeat-x; background-size: auto 100%; background-position: 0 0;
            will-change: transform;
        }
        .marsh .strip-hills { height: 24%; bottom: 24%; z-index: 3; }
        .marsh .strip-hills b { background-image: url("{{ $sceneDef['art']('strip-hills.webp') }}"); }
        .marsh .strip-marsh { height: 34%; z-index: 4; }
        .marsh .strip-marsh b { background-image: url("{{ $sceneDef['art']('strip-marsh.webp') }}"); }
        .marsh .strip-reeds { height: 17%; bottom: -1%; z-index: 7; }
        .marsh .strip-reeds b { background-image: url("{{ $sceneDef['art']('strip-reeds-front.webp') }}"); }
        @media (orientation: portrait) {
            .marsh { --duck-w: clamp(118px, 36vw, 210px); }
            /* The hills reach down behind the marsh's bank, so no sky shows between them. */
            .marsh .strip-hills { height: 13%; bottom: 14%; }
            .marsh .strip-marsh { height: 23%; }
            .marsh .strip-reeds { height: 10%; }
        }

        /* ---------- Lucky ----------
           .duck-wrap is placed by the script; data-pose picks the art:
           idle (floats and preens) → lift (the take-off) → fly (the flap cycle) → hit. */
        .marsh .duck-wrap {
            position: absolute; left: 0; top: 0; z-index: 6;
            width: var(--duck-w); aspect-ratio: 282 / 312;
            will-change: transform;
        }
        .marsh :is(.duck-fall, .duck-bob) { position: absolute; inset: 0; }
        .marsh .duck-sprite { position: absolute; inset: 0; background-repeat: no-repeat; background-position: 0 0; }
        .marsh .duck-takeoff { background-image: url("{{ $sceneDef['art']('duck-takeoff.webp') }}"); background-size: 400% 100%; }
        .marsh .duck-fly { background-image: url("{{ $sceneDef['art']('duck-fly.webp') }}"); background-size: 800% 100%; display: none; }
        /* The hit poses are drawn on their own cells (245 × 239), centred on Lucky. */
        .marsh .duck-hit {
            inset: auto; left: 50%; top: 52%; width: 87%; aspect-ratio: 245 / 239; translate: -50% -50%;
            background-image: url("{{ $sceneDef['art']('duck-hit.webp') }}"); background-size: 600% 100%;
            opacity: 0;
        }

        .marsh .duck-wrap[data-pose="idle"] { animation: marsh-duck-in 0.8s ease-out; }
        @keyframes marsh-duck-in { from { opacity: 0; } }
        .marsh [data-pose="idle"] .duck-takeoff { animation: marsh-duck-idle 3.4s step-end infinite; }
        @keyframes marsh-duck-idle {
            0%   { background-position: 0 0; }
            64%  { background-position: 33.333% 0; } /* a preen now and then */
            84%  { background-position: 0 0; }
        }
        .marsh [data-pose="idle"] .duck-bob { animation: marsh-float 1.9s ease-in-out infinite alternate; }
        @keyframes marsh-float { from { translate: 0 -2px; rotate: -1.5deg; } to { translate: 0 3px; rotate: 1.5deg; } }

        .marsh [data-pose="lift"] .duck-takeoff { animation: marsh-duck-lift 0.5s step-end forwards; }
        @keyframes marsh-duck-lift { 0% { background-position: 66.667% 0; } 50%, 100% { background-position: 100% 0; } }

        .marsh :is([data-pose="fly"], [data-pose="hit"]) .duck-takeoff { display: none; }
        .marsh :is([data-pose="fly"], [data-pose="hit"]) .duck-fly { display: block; animation: marsh-flap 0.56s steps(8, jump-none) infinite; }
        @keyframes marsh-flap { from { background-position: 0 0; } to { background-position: 100% 0; } }
        .deep .marsh .duck-fly     { animation-duration: 0.48s; }
        .abyss .marsh .duck-fly    { animation-duration: 0.42s; }
        .critical .marsh .duck-fly { animation-duration: 0.36s; }
        .marsh [data-pose="fly"] .duck-bob { animation: marsh-glide 1.5s ease-in-out infinite alternate; }
        @keyframes marsh-glide { from { translate: 0 -5px; rotate: -3deg; } to { translate: 0 5px; rotate: 1deg; } }

        /* Hit: the flight art goes at the shot (--boom-at), the six poses play (shock, smoke,
           soot and stars, the tumble, the fall, the feathers) and Lucky drops out of the sky. */
        .marsh [data-pose="hit"] .duck-bob { animation: marsh-vanish 0s linear var(--boom-at) forwards; }
        @keyframes marsh-vanish { to { opacity: 0; } }
        .marsh [data-pose="hit"] .duck-hit { animation: marsh-show 0s linear var(--boom-at) forwards, marsh-duck-hit 2s step-end var(--boom-at) forwards; }
        @keyframes marsh-show { to { opacity: 1; } }
        @keyframes marsh-duck-hit {
            0%        { background-position: 0 0; }
            12%       { background-position: 20% 0; }
            30%       { background-position: 40% 0; }
            58%       { background-position: 60% 0; }
            72%       { background-position: 80% 0; }
            86%, 100% { background-position: 100% 0; }
        }
        .marsh [data-pose="hit"] .duck-fall { animation: marsh-drop 1.25s cubic-bezier(0.55, 0, 1, 0.45) calc(var(--boom-at) + 1.15s) forwards; }
        @keyframes marsh-drop { to { transform: translate(-4vw, 80vh) rotate(-40deg); } }

        /* ---------- The fox ----------
           His arm (behind him: the blind hides the sleeve and the paws) turns by --aim, set by the
           script, about the sleeve's end (1.5% / 67.7% of the arm), placed low inside the blind;
           the flash and the smoke come out of the bell (99.3% / 21.6%). */
        .marsh .fox {
            position: absolute; left: -1%; bottom: -1%; z-index: 8;
            width: clamp(210px, 25vw, 400px); aspect-ratio: 640 / 361;
        }
        @media (orientation: portrait) { .marsh .fox { width: clamp(150px, 40vw, 260px); } }
        .marsh .fox-body { position: absolute; inset: 0; width: 100%; height: 100%; user-select: none; }
        .marsh .fox-arm {
            position: absolute; left: 58%; top: 82%; width: 86%; aspect-ratio: 600 / 206;
            transform-origin: 1.5% 67.7%; translate: -1.5% -67.7%; rotate: var(--aim, -12deg);
        }
        .marsh .fox-gun { display: block; width: 100%; height: 100%; user-select: none; }
        .marsh .muzzle { position: absolute; left: 99.3%; top: 21.6%; width: 0; height: 0; }
        .marsh .muzzle .flash { position: absolute; left: -14px; top: -48px; width: 96px; height: 96px; opacity: 0; overflow: visible; }
        .marsh .muzzle .smoke { position: absolute; left: -16px; top: -34px; width: 78px; opacity: 0; filter: grayscale(1) brightness(0.86) contrast(0.9); }
        .crashed .marsh .muzzle .flash { animation: marsh-flash 0.32s ease-out forwards; }
        @keyframes marsh-flash { 0% { opacity: 1; scale: 0.3; } 35% { opacity: 1; scale: 1.25; } 100% { opacity: 0; scale: 1.5; } }
        .crashed .marsh .muzzle .smoke { animation: marsh-smoke 1.7s ease-out var(--d) forwards; }
        @keyframes marsh-smoke {
            0%   { opacity: 0; translate: 0 0; scale: 0.3; }
            14%  { opacity: 0.95; }
            100% { opacity: 0; translate: var(--dx) var(--dy); scale: 1.6; }
        }
        .crashed .marsh .fox-arm { animation: marsh-recoil 0.42s ease-out; }
        @keyframes marsh-recoil { 22% { translate: calc(-1.5% - 16px) calc(-67.7% + 7px); } }

        /* ---------- Cash-out loot: coins and golden feathers burst from Lucky ---------- */
        .marsh .loot { position: absolute; inset: 0; pointer-events: none; z-index: 9; }
        .marsh .loot img {
            position: absolute; left: var(--x); top: var(--y); width: var(--s, 30px); height: auto;
            translate: -50% -50%; opacity: 0;
            animation: marsh-loot 1.3s cubic-bezier(0.2, 0.7, 0.4, 1) var(--d, 0s) forwards;
        }
        @keyframes marsh-loot {
            0%   { opacity: 0; transform: translate(0, 0) scale(0.4) rotate(0); }
            12%  { opacity: 1; }
            70%  { opacity: 1; }
            100% { opacity: 0; transform: translate(var(--dx), var(--dy)) scale(1) rotate(var(--r)); }
        }
