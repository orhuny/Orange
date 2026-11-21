{{--
    The Majestic Games intro the lobby opens with, like a studio's logo before a film: the
    crown draws itself in gold, the name rises under it, and it fades into the lobby once the
    page is ready (a progress line shows if that takes longer than the animation). Games open
    with their own intros only.

    - Include it first thing in <body>: it is inline (no request but the font), so it is on
      screen at once.
    - window.orangeIntro.waitFor(promise): hold until this has settled too (the lobby: its
      first list of games), so it fades into a page that is all there.
    - window.orangeIntro.done resolves as it fades.
    - config('games.provider_intro'): enabled, sound.
--}}
@if (config('games.provider_intro.enabled', true))
<link rel="preload" href="{{ asset('fonts/sora/sora-latin.woff2') }}" as="font" type="font/woff2" crossorigin>
<style>
    @font-face { font-family: "Majestic Sora"; font-weight: 400 800; font-style: normal; font-display: block;
        src: url("{{ asset('fonts/sora/sora-latin.woff2') }}") format("woff2"); }
    .mgi { position: fixed; inset: 0; z-index: 2147483000; display: grid; place-items: center; overflow: hidden; isolation: isolate;
           background: radial-gradient(120% 90% at 50% 45%, #17153d 0%, #0a0920 46%, #020207 100%);
           color: #fff; font-family: "Majestic Sora", ui-sans-serif, system-ui, sans-serif; cursor: default; user-select: none; -webkit-user-select: none;
           transition: opacity .6s ease .05s; }
    .mgi *, .mgi *::before, .mgi *::after { box-sizing: border-box; }
    .mgi.out { opacity: 0; pointer-events: none; }
    .mgi-rays, .mgi-glow, .mgi-flash { position: absolute; left: 50%; top: 44%; translate: -50% -50%; pointer-events: none; }
    .mgi-rays { z-index: -3; width: 170vmax; height: 170vmax; opacity: 0;
                background: repeating-conic-gradient(from 0deg, rgba(253, 220, 140, .06) 0deg 4deg, transparent 4deg 13deg);
                -webkit-mask-image: radial-gradient(circle, #000 0, transparent 46%); mask-image: radial-gradient(circle, #000 0, transparent 46%);
                animation: mgi-fade 1.4s .45s ease-out forwards, mgi-spin 40s linear infinite; }
    .mgi-glow { z-index: -2; width: 80vmin; height: 80vmin; opacity: 0; scale: .6;
                background: radial-gradient(circle, rgba(99, 102, 241, .55) 0%, rgba(79, 70, 229, .22) 34%, transparent 66%);
                animation: mgi-glow 1.6s .3s cubic-bezier(.2, .8, .2, 1) forwards; }
    .mgi-flash { z-index: -1; width: 46vmin; height: 46vmin; opacity: 0;
                 background: radial-gradient(circle, rgba(255, 244, 200, .75) 0%, rgba(251, 191, 36, .28) 30%, transparent 64%);
                 animation: mgi-flash 1.1s .9s ease-out forwards; }
    .mgi-dust { position: absolute; inset: 0; z-index: -1; pointer-events: none; }
    .mgi-dust span { position: absolute; bottom: -12px; left: var(--x); width: var(--s); height: var(--s); border-radius: 50%; opacity: 0;
                     background: radial-gradient(circle, #fff7d6 0%, rgba(253, 230, 138, .6) 40%, transparent 70%);
                     animation: mgi-rise var(--t) var(--dl) linear infinite; }
    /* The vignette, over the light but under the lockup. */
    .mgi::after { content: ""; position: absolute; inset: 0; z-index: -1; pointer-events: none; box-shadow: inset 0 0 18vmin 6vmin rgba(0, 0, 0, .7); }

    .mgi-lockup { display: grid; justify-items: center; transition: transform .7s cubic-bezier(.4, 0, .2, 1), filter .7s; }
    .mgi.out .mgi-lockup { transform: scale(1.07); filter: blur(5px); }
    .mgi-crown { position: relative; width: clamp(80px, 21vmin, 190px); aspect-ratio: 64 / 60; }
    .mgi-crown svg { position: relative; display: block; width: 100%; height: 100%; overflow: visible;
                     filter: drop-shadow(0 0 .6vmin rgba(251, 191, 36, .55)) drop-shadow(0 1.2vmin 2vmin rgba(0, 0, 0, .6));
                     animation: mgi-pop .8s .78s cubic-bezier(.3, 1.6, .5, 1) both; }
    .mgi-line { fill: none; stroke: #fde68a; stroke-width: 1.1; stroke-linejoin: round; stroke-dasharray: 1; stroke-dashoffset: 1;
                animation: mgi-draw .9s .2s cubic-bezier(.6, 0, .3, 1) forwards; }
    .mgi-fill { opacity: 0; animation: mgi-in .5s .85s ease-out forwards; }
    .mgi-gem { transform-box: fill-box; transform-origin: center; transform: scale(0); animation: mgi-gem .55s var(--d) cubic-bezier(.3, 1.8, .5, 1) forwards; }
    /* A gleam across the crown, cut to its shape. */
    .mgi-gleam { position: absolute; inset: 0; pointer-events: none;
                 -webkit-mask: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 60'%3E%3Cpath d='M10 40L6.5 15L20 27L32 8L44 27L57.5 15L54 40Z'/%3E%3Crect x='9' y='43' width='46' height='8' rx='2.5'/%3E%3C/svg%3E") center / 100% 100% no-repeat;
                 mask: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 60'%3E%3Cpath d='M10 40L6.5 15L20 27L32 8L44 27L57.5 15L54 40Z'/%3E%3Crect x='9' y='43' width='46' height='8' rx='2.5'/%3E%3C/svg%3E") center / 100% 100% no-repeat;
                 background: linear-gradient(105deg, transparent 38%, rgba(255, 255, 255, .9) 50%, transparent 62%) 160% 0 / 260% 100% no-repeat;
                 animation: mgi-sweep 1s 1.95s cubic-bezier(.4, 0, .2, 1) forwards; }
    .mgi-ring { position: absolute; left: 50%; top: 52%; width: 100%; aspect-ratio: 1; border-radius: 50%; translate: -50% -50%; scale: .3; opacity: 0;
                border: .3vmin solid rgba(253, 230, 138, .85); box-shadow: 0 0 3vmin rgba(253, 230, 138, .55), inset 0 0 2vmin rgba(253, 230, 138, .35);
                animation: mgi-ring 1.1s .98s cubic-bezier(.1, .7, .3, 1) forwards; }
    .mgi-spark { position: absolute; width: 22%; aspect-ratio: 1; translate: -50% -50%; opacity: 0; pointer-events: none;
                 background: radial-gradient(circle, #fff 0%, #fff7d6 18%, transparent 60%);
                 clip-path: polygon(50% 0, 57% 43%, 100% 50%, 57% 57%, 50% 100%, 43% 57%, 0 50%, 43% 43%);
                 animation: mgi-spark .8s var(--d) ease-out forwards; }

    .mgi-title { position: relative; margin-top: clamp(14px, 3.4vmin, 32px); padding-left: .2em; font-size: clamp(26px, 7.6vmin, 64px); font-weight: 800;
                 line-height: 1; letter-spacing: .2em; white-space: nowrap; }
    .mgi-title span { display: inline-block; opacity: 0; transform: translateY(.4em) scale(.92); filter: blur(8px);
                      background: linear-gradient(180deg, #fffbea 0%, #fde68a 42%, #f59e0b 78%, #b45309 100%); -webkit-background-clip: text; background-clip: text; color: transparent;
                      animation: mgi-letter .75s calc(1.18s + var(--i) * 55ms) cubic-bezier(.2, .8, .2, 1) forwards; }
    /* The shine that crosses the name once it is up. */
    .mgi-title::after { content: attr(data-text); position: absolute; inset: 0; padding-left: .2em; color: transparent; pointer-events: none;
                        background: linear-gradient(105deg, transparent 40%, rgba(255, 255, 255, .95) 50%, transparent 60%) 170% 0 / 260% 100% no-repeat;
                        -webkit-background-clip: text; background-clip: text; animation: mgi-sweep 1.1s 2.05s cubic-bezier(.4, 0, .2, 1) forwards; }
    .mgi-sub { display: flex; align-items: center; gap: clamp(10px, 2.2vmin, 20px); margin-top: clamp(8px, 1.8vmin, 16px); opacity: 0;
               font-size: clamp(11px, 2.5vmin, 20px); font-weight: 600; letter-spacing: .62em; padding-left: .62em; color: #eef2ff;
               animation: mgi-in .7s 1.75s ease-out forwards; }
    .mgi-sub i { display: block; width: clamp(26px, 8vmin, 72px); height: 1px; transform: scaleX(0);
                 animation: mgi-line .8s 1.75s cubic-bezier(.2, .8, .2, 1) forwards; }
    .mgi-sub i:first-child { background: linear-gradient(90deg, transparent, #fbbf24); transform-origin: right; }
    .mgi-sub i:last-child { background: linear-gradient(270deg, transparent, #fbbf24); transform-origin: left; }
    .mgi-bar { width: clamp(120px, 28vmin, 230px); height: 3px; margin-top: clamp(18px, 4vmin, 36px); border-radius: 3px; overflow: hidden;
               background: rgba(255, 255, 255, .1); opacity: 0; transition: opacity .4s; }
    .mgi.waiting .mgi-bar { opacity: 1; }
    .mgi-bar i { display: block; height: 100%; border-radius: 3px; background: linear-gradient(90deg, #f59e0b, #fde68a); box-shadow: 0 0 10px #fbbf24;
                 transform-origin: left; transform: scaleX(var(--p, 0)); transition: transform .3s ease-out; }

    @keyframes mgi-fade { to { opacity: 1; } }
    @keyframes mgi-in { to { opacity: 1; } }
    @keyframes mgi-spin { to { rotate: 360deg; } }
    @keyframes mgi-glow { to { opacity: 1; scale: 1; } }
    @keyframes mgi-flash { 0% { opacity: 0; scale: .5; } 25% { opacity: 1; } 100% { opacity: 0; scale: 1.6; } }
    @keyframes mgi-draw { to { stroke-dashoffset: 0; } }
    @keyframes mgi-pop { from { transform: scale(.9); } to { transform: scale(1); } }
    @keyframes mgi-gem { to { transform: scale(1); } }
    @keyframes mgi-ring { 0% { opacity: .95; scale: .3; } 100% { opacity: 0; scale: 2.9; } }
    @keyframes mgi-spark { 0% { opacity: 0; transform: scale(0) rotate(0); } 35% { opacity: 1; transform: scale(1.1) rotate(45deg); } 100% { opacity: 0; transform: scale(.3) rotate(90deg); } }
    @keyframes mgi-letter { to { opacity: 1; transform: none; filter: none; } }
    @keyframes mgi-line { to { transform: scaleX(1); } }
    @keyframes mgi-sweep { to { background-position: -70% 0; } }
    @keyframes mgi-rise { 0% { opacity: 0; transform: translate(0, 0); } 15% { opacity: var(--o); } 100% { opacity: 0; transform: translate(var(--dx), -105vh); } }

    /* Tall screens: the width is the short side, so the lockup takes more of it. */
    @media (orientation: portrait) {
        .mgi-crown { width: clamp(80px, 27vmin, 190px); }
        .mgi-title { font-size: clamp(26px, 9.6vmin, 64px); }
        .mgi-sub { font-size: clamp(11px, 3.1vmin, 20px); }
    }
    @media (prefers-reduced-motion: reduce) {
        .mgi *, .mgi *::before, .mgi *::after { animation: none !important; }
        .mgi-line { stroke-dashoffset: 0; }
        .mgi-fill, .mgi-sub { opacity: 1; }
        .mgi-title span { opacity: 1; transform: none; filter: none; }
        .mgi-gem, .mgi-sub i { transform: none; }
        .mgi-rays { opacity: .6; }
        .mgi-glow { opacity: 1; scale: 1; }
        .mgi-ring, .mgi-spark, .mgi-flash, .mgi-dust { display: none; }
    }
</style>
<div class="mgi" id="mgi" aria-hidden="true">
    <div class="mgi-rays"></div>
    <div class="mgi-glow"></div>
    <div class="mgi-flash"></div>
    <div class="mgi-dust"></div>
    <div class="mgi-lockup">
        <div class="mgi-crown">
            <span class="mgi-ring"></span>
            <svg viewBox="0 0 64 60" aria-hidden="true">
                <defs>
                    <linearGradient id="mgi-gold" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0" stop-color="#fff6cf"/><stop offset=".35" stop-color="#fcd34d"/><stop offset=".72" stop-color="#f59e0b"/><stop offset="1" stop-color="#b45309"/>
                    </linearGradient>
                    <linearGradient id="mgi-band" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0" stop-color="#fde68a"/><stop offset=".5" stop-color="#f59e0b"/><stop offset="1" stop-color="#92400e"/>
                    </linearGradient>
                    <linearGradient id="mgi-gem" x1="0" y1="0" x2="1" y2="1">
                        <stop offset="0" stop-color="#e0e7ff"/><stop offset=".45" stop-color="#6366f1"/><stop offset="1" stop-color="#312e81"/>
                    </linearGradient>
                    <radialGradient id="mgi-pearl" cx=".35" cy=".35" r=".75">
                        <stop offset="0" stop-color="#ffffff"/><stop offset=".45" stop-color="#fde68a"/><stop offset="1" stop-color="#d97706"/>
                    </radialGradient>
                </defs>
                <g class="mgi-fill">
                    <path d="M10 40L6.5 15L20 27L32 8L44 27L57.5 15L54 40Z" fill="url(#mgi-gold)"/>
                    <path d="M12.6 37.6 10.4 21.5l10 8.9L32 13.2l11.6 17.2 10-8.9-2.2 16.1z" fill="none" stroke="rgba(255,255,255,.45)" stroke-width=".6"/>
                    <rect x="9" y="43" width="46" height="8" rx="2.5" fill="url(#mgi-band)"/>
                    <rect x="11" y="44.2" width="42" height="1.2" rx=".6" fill="rgba(255,255,255,.45)"/>
                </g>
                <path class="mgi-line" pathLength="1" d="M10 40L6.5 15L20 27L32 8L44 27L57.5 15L54 40Z"/>
                <path class="mgi-line" pathLength="1" d="M11.5 43h41a2.5 2.5 0 0 1 2.5 2.5v3a2.5 2.5 0 0 1-2.5 2.5h-41A2.5 2.5 0 0 1 9 48.5v-3A2.5 2.5 0 0 1 11.5 43z"/>
                <circle class="mgi-gem" style="--d: 1.05s" cx="6.5" cy="13" r="3.2" fill="url(#mgi-pearl)"/>
                <circle class="mgi-gem" style="--d: 1.15s" cx="32" cy="6" r="3.7" fill="url(#mgi-pearl)"/>
                <circle class="mgi-gem" style="--d: 1.25s" cx="57.5" cy="13" r="3.2" fill="url(#mgi-pearl)"/>
                <path class="mgi-gem" style="--d: 1.3s" d="M32 24.5 36.5 31 32 37.5 27.5 31z" fill="url(#mgi-gem)" stroke="#fde68a" stroke-width=".7"/>
                <circle class="mgi-gem" style="--d: 1.38s" cx="20" cy="47" r="1.9" fill="url(#mgi-gem)"/>
                <path class="mgi-gem" style="--d: 1.42s" d="M32 44.1 34.9 47 32 49.9 29.1 47z" fill="#e11d48" stroke="#fde68a" stroke-width=".5"/>
                <circle class="mgi-gem" style="--d: 1.46s" cx="44" cy="47" r="1.9" fill="url(#mgi-gem)"/>
            </svg>
            <span class="mgi-gleam"></span>
            <span class="mgi-spark" style="left: 10%; top: 21%; --d: 1.08s"></span>
            <span class="mgi-spark" style="left: 50%; top: 10%; --d: 1.18s"></span>
            <span class="mgi-spark" style="left: 90%; top: 21%; --d: 1.28s"></span>
        </div>
        <div class="mgi-title" data-text="MAJESTIC">@foreach (str_split('MAJESTIC') as $i => $letter)<span style="--i: {{ $i }}">{{ $letter }}</span>@endforeach</div>
        <div class="mgi-sub"><i></i>GAMES<i></i></div>
        <div class="mgi-bar"><i></i></div>
    </div>
</div>
<script>
    (function majesticIntro(sound) {
        const root = document.getElementById('mgi');
        const started = performance.now();
        const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        const LENGTH = reduced ? 1400 : 2900; // the animation; it holds until the page is ready
        const FADE = 600;
        const MAX_WAIT = 12000; // a slow page never keeps a player at the intro longer than this
        let resolveDone;
        // What it waits for besides the animation: the page's load, and what the page adds.
        const waits = [new Promise((resolve) => {
            if (document.readyState === 'complete') resolve();
            else window.addEventListener('load', resolve, { once: true });
        })];
        window.orangeIntro = {
            done: new Promise((resolve) => { resolveDone = resolve; }),
            waitFor(promise) { waits.push(Promise.resolve(promise).catch(() => {})); },
        };

        if (!reduced) {
            const dust = root.querySelector('.mgi-dust');
            for (let n = 0; n < 26; n++) {
                const p = document.createElement('span');
                const r = Math.random; // decoration only
                p.style.cssText = `--x:${(r() * 100).toFixed(1)}%;--s:${(2 + r() * 4).toFixed(1)}px;--t:${(3.2 + r() * 3).toFixed(2)}s;`
                    + `--dl:${(0.2 + r() * 2.4).toFixed(2)}s;--dx:${Math.round((r() - 0.5) * 80)}px;--o:${(0.35 + r() * 0.6).toFixed(2)}`;
                dust.appendChild(p);
            }
        }

        let ready = false;
        let finished = false;
        let ticker = null;
        // Ready once every wait has settled, counting any added while it waited.
        const settled = () => {
            const count = waits.length;
            return Promise.all(waits).then(() => (waits.length === count ? undefined : settled()));
        };
        const whenReady = settled().then(() => { ready = true; });
        const played = new Promise((resolve) => setTimeout(resolve, LENGTH));

        // Not ready when the animation ends: a progress line under the name until it is.
        played.then(() => {
            if (ready || finished) return;
            root.classList.add('waiting');
            let progress = 0;
            ticker = setInterval(() => {
                progress += (0.92 - progress) * 0.07;
                root.style.setProperty('--p', progress.toFixed(3));
            }, 120);
        });

        function finish() {
            if (finished) return;
            finished = true;
            clearInterval(ticker);
            root.style.setProperty('--p', '1');
            root.classList.add('out');
            setTimeout(resolveDone, FADE / 2); // the page comes in as this fades
            setTimeout(() => root.remove(), FADE + 60);
        }
        Promise.race([Promise.all([whenReady, played]), new Promise((resolve) => setTimeout(resolve, MAX_WAIT))]).then(finish);
        // Once the page is ready, a tap skips the rest.
        root.addEventListener('pointerdown', () => { if (ready) finish(); });

        if (sound && !reduced) chime();

        /**
         * The sonic logo: a rolled major chord of bells as the crown lands, sparkles with the
         * gleam. Only where the browser lets sound start (it often does not before a tap), and
         * never when the player muted a game's sound.
         */
        function chime() {
            try {
                if (localStorage.getItem('orange.audio.muted') === '1' || localStorage.getItem('orange.slot.sound') === '0') return;
            } catch (_) { /* no storage: play */ }
            const Ctx = window.AudioContext || window.webkitAudioContext;
            if (!Ctx) return;
            let ctx;
            try { ctx = new Ctx(); } catch (_) { return; }
            const close = () => ctx.close().catch(() => {});

            function play() {
                const land = ctx.currentTime + Math.max(0.03, 0.95 - (performance.now() - started) / 1000);
                const master = ctx.createGain();
                master.gain.value = 0.16;
                master.connect(ctx.destination);
                // A little room: a soft echo under everything.
                const echo = ctx.createDelay();
                const feedback = ctx.createGain();
                const wet = ctx.createGain();
                const tone = ctx.createBiquadFilter();
                echo.delayTime.value = 0.19;
                feedback.gain.value = 0.32;
                wet.gain.value = 0.3;
                tone.type = 'lowpass';
                tone.frequency.value = 3200;
                master.connect(echo).connect(tone).connect(feedback).connect(echo);
                tone.connect(wet).connect(ctx.destination);

                const bell = (freq, at, length, level, type = 'sine') => {
                    for (const [mult, gain] of [[1, 1], [2.76, 0.18]]) { // the second partial is what makes it ring like a bell
                        const osc = ctx.createOscillator();
                        const env = ctx.createGain();
                        osc.type = type;
                        osc.frequency.value = freq * mult;
                        env.gain.setValueAtTime(0.0001, at);
                        env.gain.exponentialRampToValueAtTime(level * gain, at + 0.012);
                        env.gain.exponentialRampToValueAtTime(0.0001, at + length / mult);
                        osc.connect(env).connect(master);
                        osc.start(at);
                        osc.stop(at + length + 0.05);
                    }
                };

                // A swell of air into the landing.
                const noise = ctx.createBuffer(1, ctx.sampleRate * 0.8, ctx.sampleRate);
                const data = noise.getChannelData(0);
                for (let i = 0; i < data.length; i++) data[i] = Math.random() * 2 - 1;
                const swell = ctx.createBufferSource();
                const band = ctx.createBiquadFilter();
                const swellGain = ctx.createGain();
                swell.buffer = noise;
                band.type = 'bandpass';
                band.Q.value = 1.4;
                band.frequency.setValueAtTime(500, land - 0.7);
                band.frequency.exponentialRampToValueAtTime(4200, land);
                swellGain.gain.setValueAtTime(0.0001, land - 0.7);
                swellGain.gain.exponentialRampToValueAtTime(0.35, land - 0.05);
                swellGain.gain.exponentialRampToValueAtTime(0.0001, land + 0.12);
                swell.connect(band).connect(swellGain).connect(master);
                swell.start(Math.max(ctx.currentTime, land - 0.7));
                swell.stop(land + 0.15);

                bell(164.81, land, 2.4, 0.5, 'triangle'); // E3
                [659.25, 830.61, 987.77, 1318.51].forEach((f, i) => bell(f, land + i * 0.07, 1.9, 0.42 - i * 0.05)); // E major, rolled
                [2637.02, 3135.96, 3951.07].forEach((f, i) => bell(f, land + 1.05 + i * 0.09, 0.7, 0.07)); // the gleam
                setTimeout(close, 5000);
            }

            if (ctx.state === 'running') {
                play();
                return;
            }
            const giveUp = setTimeout(close, 400);
            ctx.resume().then(() => {
                if (ctx.state !== 'running') return;
                clearTimeout(giveUp);
                play();
            }).catch(() => {});
        }
    })(@json((bool) config('games.provider_intro.sound', true)));
</script>
@endif
