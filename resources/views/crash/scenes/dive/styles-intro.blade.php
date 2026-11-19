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
            background: url("{{ asset('images/deep-dive/bg-gothic-landscape.jpg') }}") center / cover no-repeat;
            opacity: 0; transform: scale(1.3); filter: brightness(0.25);
        }
        @media (orientation: portrait) {
            .is-bg { background-image: url("{{ asset('images/deep-dive/bg-gothic-portrait.jpg') }}"); }
        }
        :root[data-theme="jackpot"] .is-bg { background-image: url("{{ asset('images/deep-dive/bg-jackpot-landscape.jpg') }}?v={{ filemtime(public_path('images/deep-dive/bg-jackpot-landscape.jpg')) }}"); }
        @media (orientation: portrait) {
            :root[data-theme="jackpot"] .is-bg { background-image: url("{{ asset('images/deep-dive/bg-jackpot-portrait.jpg') }}?v={{ filemtime(public_path('images/deep-dive/bg-jackpot-portrait.jpg')) }}"); }
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
