/* (7) Lucky Duck. The fox fires at the crash (the muzzle flash and recoil at 0s, in the scene's
           styles); the shot lands at --boom-at, and the screen flash, the ring, the feathers and the
           banner centre on Lucky (--hero-x / --hero-y, set on the stage by the scene's script). */
        :root[data-theme="luckyduck"] { --boom-at: 0.18s; }
        :root[data-theme="luckyduck"] .crashed .crash-flash { animation: crash-flash-soft 0.4s ease-out var(--boom-at) forwards; }
        :root[data-theme="luckyduck"] .shockwave,
        :root[data-theme="luckyduck"] .crash-debris { left: var(--hero-x, 50%); top: var(--hero-y, 40%); }
        :root[data-theme="luckyduck"] .shockwave { border-color: #fff7d6; box-shadow: 0 0 30px #fde68a, inset 0 0 30px #fde68a; }
        :root[data-theme="luckyduck"] .crashed .shockwave { animation: marsh-ring 0.8s cubic-bezier(0.2, 0.8, 0.4, 1) var(--boom-at) forwards; }
        @keyframes marsh-ring {
            0%   { transform: translate(-50%, -50%) scale(0); opacity: 0; border-width: 6px; }
            10%  { opacity: 0.9; }
            100% { transform: translate(-50%, -50%) scale(9); opacity: 0; border-width: 1px; }
        }
        /* The debris is feathers: they burst out, then drift down. */
        :root[data-theme="luckyduck"] .frag {
            width: 16px !important; height: 20px !important;
            background: url("{{ $sceneDef['art']('feather.webp') }}") center / contain no-repeat;
            border-radius: 0; box-shadow: none;
        }
        :root[data-theme="luckyduck"] .crashed .frag { animation: marsh-feather 2.1s cubic-bezier(0.1, 0.7, 0.5, 1) calc(var(--boom-at) + var(--d, 0s)) forwards; }
        @keyframes marsh-feather {
            0%   { opacity: 0; transform: translate(-50%, -50%) rotate(0); }
            8%   { opacity: 1; }
            45%  { transform: translate(calc(-50% + var(--dx) * 0.45), calc(-50% + var(--dy) * 0.35)) rotate(200deg); }
            100% { opacity: 0; transform: translate(calc(-50% + var(--dx) * 0.6), calc(-50% + var(--dy) * 0.35 + 160px)) rotate(400deg); }
        }
        /* BANG!: a fat cartoon word in gold, not the hull breach glitch. */
        :root[data-theme="luckyduck"] .crash-banner { top: 26%; }
        :root[data-theme="luckyduck"] .crash-banner b { background: repeating-linear-gradient(-45deg, #fcd34d 0 6px, transparent 6px 10px); }
        :root[data-theme="luckyduck"] .crash-banner span {
            font-family: "Arial Rounded MT Bold", "Trebuchet MS", "Segoe UI", system-ui, sans-serif;
            font-size: clamp(40px, 9vw, 96px); font-weight: 900; letter-spacing: 0.04em;
            color: #fde047; -webkit-text-stroke: 2px #14532d;
            text-shadow: 0 5px 0 #14532d, 0 0 26px rgba(253, 224, 71, 0.75);
        }
        :root[data-theme="luckyduck"] .crashed .crash-banner span { animation: marsh-bang 0.5s cubic-bezier(0.3, 1.6, 0.5, 1) var(--boom-at); }
        @keyframes marsh-bang { from { transform: scale(0.3) rotate(-8deg); } to { transform: none; } }
