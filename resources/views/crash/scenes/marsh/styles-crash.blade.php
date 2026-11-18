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
        /* BANG!: the drawn word (bang-word.webp, 900 × 413) on a comic burst (bang-burst.webp,
           760 × 641, the banner's ::before), not the hull breach glitch. The burst spins in with
           an overshoot and pulses; the word slams in from twice its size, wobbles, shakes, holds,
           then both go, the word floating up. The banner's text stays, for screen readers. It
           sits on the shot's path, a third of the way from the gun to Lucky (--bang-x / --bang-y,
           set by the scene's script), so his hit stays in sight. */
        :root[data-theme="luckyduck"] .crash-banner {
            display: block; left: var(--bang-x, 50%); top: var(--bang-y, 30%); width: min(40vw, 300px); gap: 0;
            transform: translate(-50%, -50%);
        }
        @media (orientation: portrait) { :root[data-theme="luckyduck"] .crash-banner { width: 48vw; } }
        :root[data-theme="luckyduck"] .crash-banner b { display: none; }
        :root[data-theme="luckyduck"] .crash-banner::before {
            content: ""; position: absolute; left: 50%; top: 50%; width: 112%; aspect-ratio: 760 / 641;
            translate: -50% -50%; scale: 0;
            background: url("{{ $sceneDef['art']('bang-burst.webp') }}") center / contain no-repeat;
        }
        :root[data-theme="luckyduck"] .crash-banner span {
            position: relative; display: block; width: 100%; aspect-ratio: 900 / 413;
            font-size: 0; color: transparent; -webkit-text-stroke: 0; text-shadow: none;
            background: url("{{ $sceneDef['art']('bang-word.webp') }}") center / contain no-repeat;
        }
        :root[data-theme="luckyduck"] .crashed .crash-banner { animation: marsh-bang-banner 1.9s linear var(--boom-at) forwards; }
        @keyframes marsh-bang-banner { 0%, 80% { opacity: 1; } 100% { opacity: 0; } }
        :root[data-theme="luckyduck"] .crashed .crash-banner::before { animation: marsh-bang-burst 1.9s cubic-bezier(0.2, 0.9, 0.3, 1) var(--boom-at) forwards; }
        @keyframes marsh-bang-burst {
            0%   { scale: 0; rotate: -30deg; }
            12%  { scale: 1.14; rotate: 5deg; }
            22%  { scale: 0.95; rotate: -1deg; }
            32%  { scale: 1.03; rotate: 0deg; }
            50%  { scale: 0.99; }
            68%  { scale: 1.02; }
            80%  { scale: 1; }
            100% { scale: 1.3; rotate: 10deg; }
        }
        :root[data-theme="luckyduck"] .crashed .crash-banner span { animation: marsh-bang-word 1.9s ease-out var(--boom-at) forwards; }
        @keyframes marsh-bang-word {
            0%   { scale: 2.3; rotate: -12deg; opacity: 0; }
            7%   { opacity: 1; }
            14%  { scale: 0.86; rotate: 4deg; }
            22%  { scale: 1.08; rotate: -2deg; translate: 0 0; }
            26%  { translate: -5px 2px; }
            30%  { translate: 5px -2px; }
            34%  { translate: -3px 1px; }
            40%  { scale: 1; rotate: 0deg; translate: 0 0; }
            80%  { scale: 1; translate: 0 0; opacity: 1; }
            100% { scale: 1.08; translate: 0 -18px; opacity: 0; }
        }
