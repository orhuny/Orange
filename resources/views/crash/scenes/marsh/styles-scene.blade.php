/* ---------- The marsh (Lucky Duck) ----------
           Layers, back to front: the sky (day, gold, sunset, dusk: .marsh[data-sky], set by
           the script from the multiplier), stars, clouds, far hills, the water, Lucky, the
           near reeds, the fox. The strips and clouds scroll left as Lucky flies (the script
           translates them); the shared tier filters on .scene don't apply here, the sky does
           the lighting. */
        :root .stage .scene.marsh {
            /* Under the bet bar (when embedded) the marsh's reeds carry on, so no sky shows. */
            background: linear-gradient(to top, #3f7d2c 0, #3f7d2c var(--op-bar-h, 0px), #8fd3ff var(--op-bar-h, 0px));
            filter: none; overflow: hidden;
        }
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
           repeat, and the script slides it left by the distance flown, modulo one repeat. The
           ground stands on the bet bar (--op-bar-h, when embedded), so the pond is never under it. */
        .marsh .strip { position: absolute; left: 0; right: 0; bottom: 0; overflow: hidden; pointer-events: none; }
        .marsh .strip b {
            position: absolute; left: 0; top: 0; bottom: 0; width: 400%;
            background-repeat: repeat-x; background-size: auto 100%; background-position: 0 0;
            will-change: transform;
        }
        .marsh .strip-hills { height: 24%; bottom: calc(var(--op-bar-h, 0px) + 24%); z-index: 3; }
        .marsh .strip-hills b { background-image: url("{{ $sceneDef['art']('strip-hills.webp') }}"); }
        .marsh .strip-marsh { height: 34%; bottom: var(--op-bar-h, 0px); z-index: 4; }
        .marsh .strip-marsh b { background-image: url("{{ $sceneDef['art']('strip-marsh.webp') }}"); }
        .marsh .strip-reeds { height: 17%; bottom: calc(var(--op-bar-h, 0px) - 1%); z-index: 7; }
        .marsh .strip-reeds b { background-image: url("{{ $sceneDef['art']('strip-reeds-front.webp') }}"); }
        @media (orientation: portrait) {
            .marsh { --duck-w: clamp(118px, 36vw, 210px); }
            /* The hills reach down behind the marsh's bank, so no sky shows between them. */
            .marsh .strip-hills { height: 13%; bottom: calc(var(--op-bar-h, 0px) + 14%); }
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

        .marsh :is([data-pose="fly"], [data-pose="hit"], [data-pose^="dodge"]) .duck-takeoff { display: none; }
        .marsh :is([data-pose="fly"], [data-pose="hit"]) .duck-fly { display: block; animation: marsh-flap 0.56s steps(8, jump-none) infinite; }
        @keyframes marsh-flap { from { background-position: 0 0; } to { background-position: 100% 0; } }
        .deep .marsh .duck-fly     { animation-duration: 0.48s; }
        .abyss .marsh .duck-fly    { animation-duration: 0.42s; }
        .critical .marsh .duck-fly { animation-duration: 0.36s; }
        .marsh [data-pose="fly"] .duck-bob { animation: marsh-glide 1.5s ease-in-out infinite alternate; }
        @keyframes marsh-glide { from { translate: 0 -5px; rotate: -3deg; } to { translate: 0 5px; rotate: 1deg; } }

        /* Dodging a shot: a flinch, then under it (dip) or over it (a barrel roll), and a wink;
           .duck-dodge is drawn on the flight's head anchor and scale (301 × 456 a cell against
           the flight's 282 × 312, the head at 66% / 32% of both), so Lucky doesn't jump. */
        .marsh .duck-dodge {
            inset: auto; left: -4.45%; top: -14.77%; width: 106.74%; aspect-ratio: 301 / 456;
            background-image: url("{{ $sceneDef['art']('duck-dodge.webp') }}"); background-size: 400% 100%;
            display: none;
        }
        .marsh [data-pose^="dodge"] .duck-fly { display: none; }
        .marsh [data-pose^="dodge"] .duck-dodge { display: block; }
        .marsh [data-pose="dodge-dip"] .duck-dodge { animation: marsh-dodge-dip 0.95s step-end forwards; }
        .marsh [data-pose="dodge-roll"] .duck-dodge { animation: marsh-dodge-roll 0.95s step-end forwards; }
        @keyframes marsh-dodge-dip  { 0% { background-position: 0 0; } 16% { background-position: 66.667% 0; } 52%, 100% { background-position: 100% 0; } }
        @keyframes marsh-dodge-roll { 0% { background-position: 0 0; } 16% { background-position: 33.333% 0; } 52%, 100% { background-position: 100% 0; } }
        .marsh [data-pose="dodge-dip"] .duck-bob { animation: marsh-dip 0.95s ease-out; }
        .marsh [data-pose="dodge-roll"] .duck-bob { animation: marsh-hop 0.95s ease-out; }
        @keyframes marsh-dip { 30% { translate: 0 24px; } 60% { translate: 0 8px; } }
        @keyframes marsh-hop { 30% { translate: 0 -24px; } 60% { translate: 0 -8px; } }

        /* Hit: the flight art goes at the shot (--boom-at), the six poses play (shock, smoke,
           soot and stars, the tumble, the fall, the feathers) and Lucky drops out of the sky. */
        .marsh [data-pose="hit"] .duck-bob { animation: marsh-vanish 0s linear var(--boom-at) forwards; }
        @keyframes marsh-vanish { to { opacity: 0; } }
        .marsh [data-pose="hit"] .duck-hit { animation: marsh-show 0s linear var(--boom-at) forwards, marsh-duck-hit 2s step-end var(--boom-at) forwards, marsh-vanish 0s linear var(--land-at) forwards; }
        @keyframes marsh-show { to { opacity: 1; } }
        @keyframes marsh-duck-hit {
            0%        { background-position: 0 0; }
            12%       { background-position: 20% 0; }
            30%       { background-position: 40% 0; }
            58%       { background-position: 60% 0; }
            72%       { background-position: 80% 0; }
            86%, 100% { background-position: 100% 0; }
        }
        /* As he tumbles and falls (hit poses 3-5) his spiral eyes spin: a spinning eye over each
           drawn one (where they are in each pose, in % of a hit cell), and stars circle his head
           in the tumble and the fall. On the hit poses' clock (marsh-duck-hit: 2 s from --boom-at). */
        .marsh .dizzy, .marsh .orbit { position: absolute; translate: -50% -50%; opacity: 0; }
        .marsh .dizzy {
            aspect-ratio: 120 / 126;
            background: url("{{ $sceneDef['art']('dizzy-eye.webp') }}") center / contain no-repeat;
        }
        .marsh [data-pose="hit"] .dizzy-1 { animation: marsh-dizzy-1 2s step-end var(--boom-at) forwards, marsh-spin 0.45s linear infinite; }
        .marsh [data-pose="hit"] .dizzy-2 { animation: marsh-dizzy-2 2s step-end var(--boom-at) forwards, marsh-spin 0.45s linear infinite reverse; }
        @keyframes marsh-spin { to { rotate: 360deg; } }
        @keyframes marsh-dizzy-1 {
            0%      { opacity: 0; left: 68.8%; top: 36.8%; width: 10%; }
            30%     { opacity: 1; left: 68.8%; top: 36.8%; width: 10%; }
            58%     { opacity: 1; left: 63.9%; top: 74.5%; width: 8.5%; }
            72%     { opacity: 1; left: 76.7%; top: 74.3%; width: 9%; }
            86%, 100% { opacity: 0; }
        }
        @keyframes marsh-dizzy-2 {
            0%      { opacity: 0; left: 75.1%; top: 33.7%; width: 6.5%; }
            30%     { opacity: 1; left: 75.1%; top: 33.7%; width: 6.5%; }
            58%     { opacity: 1; left: 71%; top: 74.7%; width: 6%; }
            72%     { opacity: 1; left: 83.3%; top: 78.5%; width: 6%; }
            86%, 100% { opacity: 0; }
        }
        .marsh .orbit { width: 36%; aspect-ratio: 2.2; }
        .marsh [data-pose="hit"] .orbit { animation: marsh-orbit-at 2s step-end var(--boom-at) forwards; }
        @keyframes marsh-orbit-at {
            0%        { opacity: 0; left: 66%; top: 80%; }
            58%       { opacity: 1; left: 66%; top: 82%; }
            72%       { opacity: 1; left: 80%; top: 70%; }
            86%, 100% { opacity: 0; }
        }
        .marsh .orbit b {
            position: absolute; left: 50%; top: 50%; width: 26%; aspect-ratio: 120 / 116; translate: -50% -50%;
            background: url("{{ $sceneDef['art']('dizzy-star.webp') }}") center / contain no-repeat;
            animation: marsh-orbit 0.9s linear infinite;
        }
        .marsh .orbit b:nth-child(2) { animation-delay: -0.3s; }
        .marsh .orbit b:nth-child(3) { animation-delay: -0.6s; }
        /* Round an ellipse; smaller at the back (the top), as if behind his head. */
        @keyframes marsh-orbit {
            0%    { left: 100%; top: 50%;  scale: 0.85; }
            12.5% { left: 85%;  top: 85%;  scale: 1; }
            25%   { left: 50%;  top: 100%; scale: 1.1; }
            37.5% { left: 15%;  top: 85%;  scale: 1; }
            50%   { left: 0%;   top: 50%;  scale: 0.85; }
            62.5% { left: 15%;  top: 15%;  scale: 0.7; }
            75%   { left: 50%;  top: 0%;   scale: 0.6; }
            87.5% { left: 85%;  top: 15%;  scale: 0.7; }
            100%  { left: 100%; top: 50%;  scale: 0.85; }
        }

        .marsh [data-pose="hit"] .duck-fall { animation: marsh-drop 0.55s cubic-bezier(0.55, 0, 1, 0.45) calc(var(--boom-at) + 1.15s) forwards; }
        @keyframes marsh-drop { to { transform: translate(var(--fall-x, 0px), var(--fall-y, 60vh)); } }

        /* In the pond (duck-water.webp: 6 frames, 149 × 95 a cell at the flight's scale, put so its
           ripple sits where the floating take-off frame's does). He lands at --land-at with a splash,
           feet up and twitching, then sinks; the next round he pops up, shakes off, straightens his
           bandana and floats smug (emerge), which is where the floating idle frame takes over. */
        .marsh { --land-at: calc(var(--boom-at) + 1.7s); }
        .marsh .duck-water {
            inset: auto; left: 35.34%; top: 39.74%; width: 52.84%; aspect-ratio: 149 / 95;
            background-image: url("{{ $sceneDef['art']('duck-water.webp') }}"); background-size: 600% 100%;
            opacity: 0;
        }
        .marsh [data-pose="hit"] .duck-water {
            animation: marsh-show 0s linear var(--land-at) forwards, marsh-sink 1.4s step-end var(--land-at) forwards, marsh-twitch 0.18s linear var(--land-at) 7;
        }
        @keyframes marsh-sink { 0% { background-position: 0 0; } 100% { background-position: 20% 0; } }
        @keyframes marsh-twitch { 50% { rotate: 3deg; translate: 0 -1px; } }
        .marsh [data-pose="emerge"] .duck-water { opacity: 1; animation: marsh-emerge 1.8s step-end forwards; }
        @keyframes marsh-emerge {
            0%   { background-position: 40% 0; }
            25%  { background-position: 60% 0; }
            50%  { background-position: 80% 0; }
            75%, 100% { background-position: 100% 0; }
        }
        .marsh [data-pose="emerge"] :is(.duck-takeoff, .duck-fly, .duck-dodge) { display: none; }
        /* The splash (water-splash.webp: 4 frames, 292 × 188 a cell), as wide as Lucky, on the water line. */
        .marsh .water-splash {
            inset: auto; left: 12.6%; top: 14.3%; width: 100%; aspect-ratio: 292 / 188;
            background-image: url("{{ $sceneDef['art']('water-splash.webp') }}"); background-size: 400% 100%;
            opacity: 0;
        }
        .marsh [data-pose="hit"] .water-splash { animation: marsh-splash 0.7s step-end var(--land-at) forwards; }
        @keyframes marsh-splash {
            0%   { opacity: 1; background-position: 0 0; }
            20%  { background-position: 33.333% 0; }
            45%  { background-position: 66.667% 0; }
            70%  { background-position: 100% 0; }
            100% { opacity: 0; background-position: 100% 0; }
        }

        /* ---------- The fox ----------
           In his tub boat (fox-boat.webp: 8 poses, 520 × 336 a cell, the waterline at 95%): aiming
           low, mid, high; firing at those; reloading; paddling. The script picks the pose
           (background-position) and puts the muzzle at its bell (--mx / --my). He floats bottom
           left, on the bet bar when the game is embedded (--op-bar-h), bobbing; the wake streams
           out behind the boat while Lucky flies. */
        .marsh .fox {
            position: absolute; left: 0; bottom: calc(var(--op-bar-h, 0px) + 0.5%); z-index: 8;
            width: clamp(190px, 22vw, 340px); aspect-ratio: 520 / 336;
            --mx: 81.9%; --my: 43.2%;
        }
        @media (orientation: portrait) {
            .marsh .fox { width: clamp(140px, 40vw, 230px); }
        }
        .marsh .boat { position: absolute; inset: 0; transform-origin: 50% 95%; animation: marsh-boat 2.4s ease-in-out infinite alternate; }
        @keyframes marsh-boat { from { translate: 0 -2px; rotate: -1.5deg; } to { translate: 0 2px; rotate: 1.5deg; } }
        .marsh .fox-sprite {
            position: absolute; inset: 0;
            background: url("{{ $sceneDef['art']('fox-boat.webp') }}") 0 0 / 800% 100% no-repeat;
        }
        .marsh .wake {
            position: absolute; right: 78%; bottom: 1%; width: 70%; aspect-ratio: 308 / 53;
            background: url("{{ $sceneDef['art']('boat-wake.webp') }}") 0 0 / 300% 100% no-repeat;
            opacity: 0; transition: opacity 0.6s ease;
        }
        .running .marsh .wake { opacity: 1; animation: marsh-wake 0.5s steps(3, jump-none) infinite; }
        @keyframes marsh-wake { to { background-position: 100% 0; } }
        .marsh .muzzle { position: absolute; left: var(--mx); top: var(--my); width: 0; height: 0; }
        /* Every shot: a blast at the bell. The one that hits (the crash): a bigger blast and a kick back. */
        .marsh .muzzle .blast { position: absolute; left: 0; top: 0; width: clamp(46px, 5.5vw, 84px); height: auto; translate: -30% -50%; opacity: 0; }
        .marsh .muzzle .blast.pop { animation: marsh-blast 0.32s ease-out; }
        @keyframes marsh-blast { 0% { opacity: 1; scale: 0.3; } 35% { opacity: 1; scale: 1.15; } 100% { opacity: 0; scale: 1.35; } }
        .crashed .marsh .muzzle .blast { animation: marsh-blast-big 0.45s ease-out forwards; }
        @keyframes marsh-blast-big { 0% { opacity: 1; scale: 0.4; } 35% { opacity: 1; scale: 1.7; } 100% { opacity: 0; scale: 2; } }
        .crashed .marsh .fox { animation: marsh-recoil 0.42s ease-out; }
        @keyframes marsh-recoil { 22% { translate: -12px 5px; } }

        /* Gun smoke, from the bell on every shot (gunsmoke.webp: 6 frames, puff to wisp), drifting up. */
        .marsh .puff {
            position: absolute; left: var(--x); top: var(--y); width: clamp(56px, 7vw, 110px); aspect-ratio: 1;
            translate: -22% -62%;
            background: url("{{ $sceneDef['art']('gunsmoke.webp') }}") 0 0 / 600% 100% no-repeat;
            animation: marsh-puff 1.2s step-end forwards, marsh-puff-drift 1.2s ease-out forwards;
        }
        @keyframes marsh-puff {
            0%   { background-position: 0 0; }
            12%  { background-position: 20% 0; }
            28%  { background-position: 40% 0; }
            48%  { background-position: 60% 0; }
            68%  { background-position: 80% 0; }
            86%, 100% { background-position: 100% 0; }
        }
        @keyframes marsh-puff-drift { 80% { opacity: 1; } to { transform: translate(26px, -34px); opacity: 0; } }

        /* A missed shot: the spray flies from the bell along --a, past Lucky, off the screen. */
        .marsh .shots { position: absolute; inset: 0; pointer-events: none; z-index: 9; }
        .marsh .spray {
            position: absolute; left: var(--x); top: var(--y); width: clamp(84px, 10vw, 160px); height: auto;
            transform-origin: 0 50%; transform: rotate(var(--a)) translate(0, -50%);
            animation: marsh-spray 0.62s cubic-bezier(0.2, 0.6, 0.6, 1) forwards;
        }
        @keyframes marsh-spray {
            0%   { transform: rotate(var(--a)) translate(0, -50%) scale(0.4); opacity: 1; }
            80%  { opacity: 1; }
            100% { transform: rotate(var(--a)) translate(var(--reach), -50%) scale(1); opacity: 0; }
        }

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
