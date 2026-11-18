/* Scene mode for Lucky Duck, ~4.3 s (the page's SCENE_MS): the sky fades in and the reeds
           rise; Lucky flaps across from the left; the logo bounces in with a flash at 1.1 s, the
           tagline at 2 s; both go at 3.55 s and the intro pushes into the game. */
        .is-layer { position: absolute; inset: 0; pointer-events: none; }
        .is-stage { position: absolute; inset: 0; overflow: hidden; background: #8fd3ff; }
        .intro.play .is-stage { animation: is-push 0.7s cubic-bezier(.6, 0, .9, .4) 3.6s forwards; }
        @keyframes is-push { to { transform: scale(1.3); filter: brightness(1.5) blur(3px); } }
        @keyframes is-fade-in { to { opacity: 1; } }
        @keyframes is-out { to { opacity: 0; } }

        .is-bg { background: url("{{ $sceneDef['art']('sky-day-landscape.jpg') }}") center bottom / cover; opacity: 0; }
        @media (orientation: portrait) {
            .is-bg { background-image: url("{{ $sceneDef['art']('sky-day-portrait.jpg') }}"); }
        }
        .intro.play .is-bg { animation: is-fade-in 0.6s ease-out forwards; }

        .is-reeds {
            position: absolute; left: 0; right: 0; bottom: -2%; height: 26%;
            background: url("{{ $sceneDef['art']('strip-reeds-front.webp') }}") 0 0 / auto 100% repeat-x;
            translate: 0 100%;
        }
        @media (orientation: portrait) { .is-reeds { height: 14%; } }
        .intro.play .is-reeds { animation: is-reeds-in 0.7s cubic-bezier(.2, .8, .3, 1) 0.2s forwards; }
        @keyframes is-reeds-in { to { translate: 0 0; } }

        .is-duck { position: absolute; left: 0; top: 48%; width: min(28vw, 280px); aspect-ratio: 282 / 312; translate: -40vw 20vh; }
        @media (orientation: portrait) { .is-duck { width: min(46vw, 260px); top: 56%; } }
        .intro.play .is-duck { animation: is-duck-fly 3.6s cubic-bezier(.35, .1, .6, .95) 0.3s forwards; }
        @keyframes is-duck-fly {
            0%   { translate: -40vw 20vh; }
            38%  { translate: 30vw -2vh; }
            70%  { translate: 62vw -8vh; }
            100% { translate: 135vw -34vh; }
        }
        .is-duck-sprite {
            position: absolute; inset: 0;
            background: url("{{ $sceneDef['art']('duck-fly.webp') }}") 0 0 / 800% 100% no-repeat;
            animation: is-flap 0.5s steps(8, jump-none) infinite;
        }
        @keyframes is-flap { to { background-position: 100% 0; } }

        .is-logo {
            position: absolute; left: 50%; top: 30%; width: min(66vw, 620px); height: auto;
            translate: -50% -50%; opacity: 0; scale: 0.2;
            filter: drop-shadow(0 10px 18px rgba(20, 36, 15, 0.45));
        }
        @media (orientation: portrait) { .is-logo { width: 88vw; top: 32%; } }
        .intro.play .is-logo { animation: is-logo-in 0.6s cubic-bezier(.3, 1.6, .5, 1) 1.1s forwards, is-logo-out 0.5s ease-in 3.55s forwards; }
        @keyframes is-logo-in { to { opacity: 1; scale: 1; } }
        @keyframes is-logo-out { from { opacity: 1; scale: 1; } to { opacity: 0; scale: 1.15; filter: blur(6px); } }

        .is-tag {
            position: absolute; left: 50%; top: calc(30% + min(17vw, 160px)); translate: -50% 0; margin: 0;
            display: flex; align-items: center; gap: 14px; white-space: nowrap;
            font: 800 clamp(13px, 2.1vw, 22px) "Arial Rounded MT Bold", "Trebuchet MS", "Segoe UI", system-ui, sans-serif;
            letter-spacing: 0.28em; text-transform: uppercase; color: #fef9c3;
            text-shadow: 0 2px 0 #14532d, 0 0 12px rgba(0, 0, 0, 0.35);
            opacity: 0;
        }
        @media (orientation: portrait) { .is-tag { top: calc(32% + 22vw); } }
        .is-tag b { width: min(12vw, 120px); height: 2px; background: linear-gradient(90deg, transparent, #fcd34d); }
        .is-tag b:last-child { background: linear-gradient(270deg, transparent, #fcd34d); }
        .intro.play .is-tag { animation: is-fade-in 0.6s ease-out 2s forwards, is-out 0.5s ease-in 3.55s forwards; }

        .is-flash { background: #fffbe6; opacity: 0; }
        .intro.play .is-flash { animation: is-flash 0.5s ease-out 1.1s; }
        @keyframes is-flash { 15% { opacity: 0.75; } to { opacity: 0; } }
