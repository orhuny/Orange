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

