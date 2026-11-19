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
        :root[data-theme="burton"] .wall-l b { background-image: url("{{ asset('images/deep-dive/wall-gothic-left.webp') }}?v={{ filemtime(public_path('images/deep-dive/wall-gothic-left.webp')) }}"); }
        :root[data-theme="burton"] .wall-r b { background-image: url("{{ asset('images/deep-dive/wall-gothic-right.webp') }}?v={{ filemtime(public_path('images/deep-dive/wall-gothic-right.webp')) }}"); }
        :root[data-theme="jackpot"] .wall-l b { background-image: url("{{ asset('images/deep-dive/wall-jackpot-left.webp') }}?v={{ filemtime(public_path('images/deep-dive/wall-jackpot-left.webp')) }}"); }
        :root[data-theme="jackpot"] .wall-r b { background-image: url("{{ asset('images/deep-dive/wall-jackpot-right.webp') }}?v={{ filemtime(public_path('images/deep-dive/wall-jackpot-right.webp')) }}"); }

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

