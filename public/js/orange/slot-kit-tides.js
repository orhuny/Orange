/**
 * Trident of Poseidon's own effects: the theme kit "tides" (theme.php `kit`), on top of the
 * shared slot page (slot-game.js, see `kit` there for the hooks). Everything here is show:
 * the outcome was decided on the server and the page only plays it back.
 *
 * - The sea: moving caustic light (a small WebGL shader), swaying light shafts, the reef,
 *   rising bubbles and a school of fish now and then.
 * - A win: small whirlpools gather the winners, which dissolve into droplets of their own
 *   colours that turn into bubbles.
 * - Pearls: Poseidon strikes, jets of water run from his trident to each pearl, and the pearls
 *   fly to the win (base game) or into the Tide (free spins), the running multiplier.
 * - Free spins: a whirlpool opens across the screen and a dolphin leaps through.
 * - Big wins: a surge of water sweeps the screen and dolphins circle the banner.
 *
 * Quick-stop (state.quick) and reduced motion skip the show; turbo speeds it up (api.dur).
 */
export function createKit(api) {
    const {
        state, theme, $, board, stage, reducedMotion, rand, dur, wait, animate, sfx, emit, sprite, cellBox, cellOnScreen,
        shake, floatText, hostReact, hostTip, COLS, ROWS, starTier,
    } = api;
    const art = theme.kitArt ?? {};
    const scenery = document.querySelector('.scenery');
    const motion = () => !reducedMotion && !state.quick;
    const calm = () => !document.hidden && !state.busy && $('overlay').hidden;

    // ---- The sea ---------------------------------------------------------------------------

    function sea() {
        caustics();
        if (art.sunrays) {
            const rays = document.createElement('div');
            rays.className = 'tides-rays';
            rays.style.setProperty('--rays', `url("${art.sunrays}")`);
            scenery?.prepend(rays);
        }
        if (art.reef) {
            for (const side of ['left', 'right']) {
                const reef = document.createElement('img');
                reef.className = `tides-reef ${side}`;
                reef.src = art.reef;
                reef.alt = '';
                scenery?.append(reef);
            }
        }
        if (reducedMotion) return;
        bubbleLoop();
        setTimeout(fishLoop, rand(6000, 12000));
    }

    /**
     * Caustic light: bright, slowly warping filaments, as sunlight makes through a rippling
     * surface, stronger near the top. Drawn small (a quarter of the pixels) at about 30 fps
     * and screened over the background; none without WebGL or with reduced motion.
     */
    function caustics() {
        if (reducedMotion) return;
        const c = document.createElement('canvas');
        c.className = 'tides-caustics';
        const gl = c.getContext('webgl', { alpha: false, antialias: false, premultipliedAlpha: false });
        if (!gl) return;
        document.querySelector('.bg.night')?.after(c);
        const vs = 'attribute vec2 p; void main() { gl_Position = vec4(p, 0.0, 1.0); }';
        const fs = `precision mediump float;
            uniform vec2 res; uniform float t;
            float web(vec2 p, float s, float speed) {
                p *= s;
                p += vec2(sin(p.y * 0.9 + t * 0.6 * speed), cos(p.x * 0.8 - t * 0.45 * speed)) * 0.9;
                p += vec2(cos(p.y * 1.7 - t * 0.35 * speed), sin(p.x * 1.5 + t * 0.5 * speed)) * 0.45;
                return pow(1.0 - abs(sin(p.x) * sin(p.y)), 10.0);
            }
            void main() {
                vec2 uv = gl_FragCoord.xy / res.y;
                float c = web(uv, 6.0, 1.0) * 0.65 + web(uv + vec2(3.1, 1.7), 10.0, 1.3) * 0.45;
                float depth = smoothstep(-0.1, 1.0, gl_FragCoord.y / res.y);
                gl_FragColor = vec4(vec3(0.55, 0.95, 1.0) * c * (0.25 + 0.75 * depth), 1.0);
            }`;
        const shader = (type, src) => {
            const s = gl.createShader(type);
            gl.shaderSource(s, src);
            gl.compileShader(s);
            return gl.getShaderParameter(s, gl.COMPILE_STATUS) ? s : null;
        };
        const v = shader(gl.VERTEX_SHADER, vs);
        const f = shader(gl.FRAGMENT_SHADER, fs);
        if (!v || !f) {
            c.remove();
            return;
        }
        const prog = gl.createProgram();
        gl.attachShader(prog, v);
        gl.attachShader(prog, f);
        gl.linkProgram(prog);
        if (!gl.getProgramParameter(prog, gl.LINK_STATUS)) {
            c.remove();
            return;
        }
        gl.useProgram(prog);
        gl.bindBuffer(gl.ARRAY_BUFFER, gl.createBuffer());
        gl.bufferData(gl.ARRAY_BUFFER, new Float32Array([-1, -1, 1, -1, -1, 1, 1, 1]), gl.STATIC_DRAW);
        const loc = gl.getAttribLocation(prog, 'p');
        gl.enableVertexAttribArray(loc);
        gl.vertexAttribPointer(loc, 2, gl.FLOAT, false, 0, 0);
        const uRes = gl.getUniformLocation(prog, 'res');
        const uT = gl.getUniformLocation(prog, 't');
        const fitGl = () => {
            c.width = Math.max(1, Math.round(innerWidth / 2));
            c.height = Math.max(1, Math.round(innerHeight / 2));
            c.style.width = '100%';
            c.style.height = '100%';
            gl.viewport(0, 0, c.width, c.height);
            gl.uniform2f(uRes, c.width, c.height);
        };
        fitGl();
        addEventListener('resize', fitGl);
        const started = performance.now();
        let last = 0;
        const frame = (now) => {
            requestAnimationFrame(frame);
            if (document.hidden || now - last < 33) return;
            last = now;
            gl.uniform1f(uT, (now - started) / 1000);
            gl.drawArrays(gl.TRIANGLE_STRIP, 0, 4);
        };
        requestAnimationFrame(frame);
    }

    /** Bubbles rise from the sea floor, more of them in the Abyss, and some from Poseidon's water. */
    function bubbleLoop() {
        const pool = art.bubbles ?? [];
        if (!pool.length) return;
        const rise = () => {
            setTimeout(rise, rand(260, 640));
            if (document.hidden || document.querySelectorAll('.tides-bubble').length > 26) return;
            const host = Math.random() < 0.3 ? document.querySelector('.host-fig.on')?.getBoundingClientRect() : null;
            const x = host?.width ? rand(host.left + host.width * 0.25, host.right - host.width * 0.25) : rand(0, innerWidth);
            const y0 = host?.width ? host.bottom - host.height * 0.05 : innerHeight + 20;
            bubble(x, y0, rand(6, 26), rand(7000, 13000), innerHeight * rand(0.7, 1.15));
        };
        rise();
    }

    /** One bubble rising from (x, y) by `height` px, wobbling, over ms. */
    function bubble(x, y, size, ms, height, layer = scenery) {
        const pool = art.bubbles ?? [];
        if (!pool.length || !layer) return;
        const b = document.createElement('img');
        b.className = 'tides-bubble';
        b.src = pool[Math.floor(Math.random() * pool.length)];
        b.alt = '';
        b.style.width = `${size}px`;
        layer.append(b);
        const wob = rand(8, 22);
        const frames = [0, 0.25, 0.5, 0.75, 1].map((f, i) => ({
            transform: `translate(${x + Math.sin(i * 1.7) * wob * f}px, ${y - height * f}px) scale(${0.7 + f * 0.4})`,
            opacity: i === 0 ? 0 : i === 4 ? 0 : 0.9,
        }));
        b.animate(frames, { duration: ms, easing: 'cubic-bezier(.35, .1, .6, 1)' }).finished.catch(() => {}).then(() => b.remove());
    }

    /** Now and then a school of fish crosses behind the grid, in formation. */
    function fishLoop() {
        setTimeout(fishLoop, rand(16000, 32000));
        const pool = art.fish ?? [];
        if (!pool.length || !calm()) return;
        const right = Math.random() < 0.5; // swims to the right (the art faces right)
        const n = Math.floor(rand(6, 12));
        const band = rand(0.18, 0.62) * innerHeight;
        const ms = rand(10000, 15000);
        const kind = pool[Math.floor(Math.random() * pool.length)];
        for (let i = 0; i < n; i++) {
            const f = document.createElement('img');
            f.className = 'tides-fish';
            f.src = Math.random() < 0.7 ? kind : pool[Math.floor(Math.random() * pool.length)];
            f.alt = '';
            const w = rand(34, 64) * (innerWidth > 900 ? 1 : 0.7);
            f.style.width = `${w}px`;
            scenery?.append(f);
            const dy = band + rand(-0.07, 0.07) * innerHeight;
            const lag = rand(0, 900) + i * 120;
            const from = right ? -120 - rand(0, 140) : innerWidth + 120 + rand(0, 140);
            const to = right ? innerWidth + 160 : -160;
            const face = right ? 1 : -1;
            const bob = rand(10, 26);
            f.animate([0, 0.25, 0.5, 0.75, 1].map((t, k) => ({
                transform: `translate(${from + (to - from) * t}px, ${dy + Math.sin(k * 1.6 + i) * bob}px) scaleX(${face}) rotate(${Math.cos(k * 1.6 + i) * 6 * face}deg)`,
            })), { duration: ms, delay: lag, easing: 'linear', fill: 'both' }).finished.catch(() => {}).then(() => f.remove());
        }
    }

    // ---- A win: whirlpools, then each winner dissolves into its own colours -----------------

    const colours = new Map(); // symbol letter -> [{ x, y, c }] opaque samples of its art

    /** The symbol's art, sampled small: where its opaque pixels are and their colours. */
    function sample(letter) {
        if (colours.has(letter)) return colours.get(letter);
        const url = theme.symbols?.[letter]?.img;
        colours.set(letter, []);
        if (!url) return [];
        const im = new Image();
        im.decoding = 'async';
        im.onload = () => {
            const n = 30;
            const c = document.createElement('canvas');
            c.width = c.height = n;
            const g = c.getContext('2d', { willReadFrequently: true });
            if (!g) return;
            g.drawImage(im, 0, 0, n, n);
            let data;
            try {
                data = g.getImageData(0, 0, n, n).data;
            } catch {
                return; // a cross-origin image can't be read: no dissolve, the splash still shows
            }
            const out = [];
            for (let i = 0; i < n * n; i++) {
                if (data[i * 4 + 3] > 150) out.push({ x: (i % n) / n - 0.5, y: Math.floor(i / n) / n - 0.5, c: `rgb(${data[i * 4]}, ${data[i * 4 + 1]}, ${data[i * 4 + 2]})` });
            }
            colours.set(letter, out);
        };
        im.src = url;
        return [];
    }

    /** The whirlpools start as the winners light up and turn until they burst. */
    function winStart(cells, step, pace) {
        if (!motion() || !art.whirlpool) return;
        for (const cell of cells) {
            const el = state.els[cell];
            if (!el) continue;
            const swirl = document.createElement('img');
            swirl.className = 'swirl';
            swirl.src = art.whirlpool;
            swirl.alt = '';
            el.append(swirl);
            animate(swirl, [
                { opacity: 0, transform: 'rotate(0deg) scale(1.25)' },
                { opacity: 0.85, transform: 'rotate(260deg) scale(1)', offset: 0.55 },
                { opacity: 1, transform: 'rotate(560deg) scale(.7)' },
            ], pace.hold + pace.frost, { easing: 'cubic-bezier(.4, 0, .7, 1)' });
        }
    }

    /** The whirlpools pull the winners in, just before they burst. */
    function charge(cells, ms) {
        if (!motion()) return;
        for (const cell of cells) {
            const img = state.els[cell]?.querySelector(':scope > img, .star, .basket');
            if (img) animate(img, [{ scale: 1, rotate: '0deg' }, { scale: 0.82, rotate: '-14deg' }], ms, { easing: 'ease-in' });
        }
    }

    /** A winner dissolves: droplets of its own colours burst out, slow in the water, and rise as bubbles. */
    function burst(cell, symbol) {
        if (!motion()) return;
        const dots = colours.get(symbol) ?? sample(symbol);
        const { x, y, size } = cellBox(cell);
        const n = Math.min(dots.length, size > 70 ? 90 : 60);
        for (let i = 0; i < n; i++) {
            const d = dots[Math.floor(Math.random() * dots.length)];
            const x0 = x + d.x * size * 0.9;
            const y0 = y + d.y * size * 0.9;
            const out = Math.atan2(d.y, d.x) + rand(-0.5, 0.5);
            const v = size * rand(1.2, 3.4);
            const vx = Math.cos(out) * v;
            const vy = Math.sin(out) * v;
            const rise = size * rand(0.5, 1.1);
            const r = size * rand(0.022, 0.05);
            emit({
                draw: drawDrop, colour: d.c, x: x0, y: y0, w: r, h: r, life: rand(0.8, 1.35), fade: 0.45, s0: 1, s1: 0.75, ease: (t) => t,
                move(p) {
                    const k = 3.4; // the water slows them fast
                    const a = p.age;
                    const drag = (1 - Math.exp(-k * a)) / k;
                    p.x = x0 + vx * drag + Math.sin(a * 9 + i) * size * 0.02;
                    p.y = y0 + vy * drag - rise * a * a;
                },
            });
        }
    }

    /** A droplet: its colour, turning into a bubble's bright rim as it rises. */
    function drawDrop(ctx, p, s, t) {
        const r = Math.max(0.5, p.w * s);
        ctx.beginPath();
        ctx.arc(p.x, p.y, r, 0, Math.PI * 2);
        if (t < 0.55) {
            ctx.fillStyle = p.colour;
            ctx.fill();
        } else {
            ctx.lineWidth = Math.max(0.8, r * 0.28);
            ctx.strokeStyle = 'rgba(210, 250, 255, .9)';
            ctx.stroke();
        }
    }

    // ---- Pearls: Poseidon strikes, and they fly to the win or into the Tide --------------------

    let tideEl = null;

    function tideMeter() {
        if (tideEl || !art.tideRing) return tideEl;
        const wrap = board.closest('.board-wrap');
        if (!wrap) return null;
        tideEl = document.createElement('div');
        tideEl.className = 'tide empty';
        tideEl.setAttribute('aria-live', 'polite');
        tideEl.innerHTML = `<img class="pool" src="${art.whirlpool ?? ''}" alt=""><img class="ring" src="${art.tideRing}" alt=""><b>—</b><small>TIDE</small>`;
        wrap.append(tideEl);
        return tideEl;
    }

    function showTide(value, bump = false) {
        const el = tideMeter();
        if (!el) return;
        el.classList.toggle('empty', !value);
        el.querySelector('b').textContent = value ? `×${value}` : '—';
        el.dataset.level = value >= 200 ? '3' : value >= 50 ? '2' : value > 0 ? '1' : '0';
        el.setAttribute('aria-label', value ? `Tide ×${value}` : 'Tide');
        if (bump) {
            el.classList.remove('bump');
            void el.offsetWidth;
            el.classList.add('bump');
        }
    }

    /** A jet of water from (x0, y0) to (x1, y1), on screen. */
    function jet(x0, y0, x1, y1, delay = 0) {
        const src = theme.fx?.comet;
        if (!src) return;
        const img = document.createElement('img');
        img.className = 'tides-fx';
        img.src = src;
        img.alt = '';
        const len = Math.hypot(x1 - x0, y1 - y0);
        Object.assign(img.style, { width: `${len}px`, height: `${Math.max(36, len * 0.16)}px`, transformOrigin: '0 50%' });
        document.body.append(img);
        const base = `translate(${x0}px, ${y0}px) translateY(-50%) rotate(${Math.atan2(y1 - y0, x1 - x0)}rad)`;
        animate(img, [
            { transform: `${base} scaleX(0)`, opacity: 1 },
            { transform: `${base} scaleX(1)`, opacity: 1, offset: 0.55 },
            { transform: `${base} scaleX(1)`, opacity: 0 },
        ], 520, { delay, easing: 'ease-out' }).then(() => img.remove());
    }

    /** A pearl lifts out of its clam and flies to (tx, ty) on screen. */
    async function fly(cell, tx, ty, delay) {
        const value = state.cells[cell]?.v ?? 0;
        const from = cellOnScreen(cell);
        const el = document.createElement('div');
        el.className = 'tides-pearl';
        el.style.width = `${from.size}px`;
        el.style.height = `${from.size}px`;
        el.innerHTML = `<div class="sym"><div class="star"><img src="${theme.stars?.[starTier(value)] ?? ''}" alt=""><b>${value}×</b></div></div>`;
        document.body.append(el);
        state.els[cell]?.classList.add('spent');
        const mx = (from.x + tx) / 2 + rand(-60, 60);
        const my = Math.min(from.y, ty) - from.size * 1.2;
        const at = (x, y, s) => `translate(${x - from.size / 2}px, ${y - from.size / 2}px) scale(${s})`;
        await animate(el, [
            { transform: at(from.x, from.y, 1), opacity: 1 },
            { transform: at(from.x, from.y - from.size * 0.25, 1.15), opacity: 1, offset: 0.18 },
            { transform: at(mx, my, 0.9), opacity: 1, offset: 0.6 },
            { transform: at(tx, ty, 0.35), opacity: 0.2 },
        ], 760, { delay, easing: 'cubic-bezier(.45, 0, .55, 1)' });
        el.remove();
        splashAt(tx, ty, from.size);
    }

    /** A ring of water where something lands, on screen. */
    function splashAt(x, y, size) {
        const src = theme.fx?.frostRing;
        if (!src) return;
        const img = document.createElement('img');
        img.className = 'tides-fx';
        img.src = src;
        img.alt = '';
        Object.assign(img.style, { width: `${size * 1.6}px`, height: `${size * 1.6}px` });
        document.body.append(img);
        const at = `translate(${x - size * 0.8}px, ${y - size * 0.8}px)`;
        animate(img, [{ transform: `${at} scale(.2)`, opacity: 1 }, { transform: `${at} scale(1.3)`, opacity: 0 }], 520).then(() => img.remove());
    }

    /**
     * A spin's multipliers. The base game: its pearls fly to the middle of the grid and their
     * sum multiplies the spin. Free spins: they fly into the Tide, and the Tide multiplies the
     * spin (also when no new pearl came).
     */
    async function multipliers(spin) {
        const tide = spin.tide !== null && spin.tide !== undefined;
        const factor = tide ? spin.tide : spin.multiplier;
        const pearls = spin.multiplier > 0 ? spin.multiplierCells : [];
        const target = (() => {
            const el = tide ? tideMeter() : null;
            const r = (el ?? board).getBoundingClientRect();
            return { x: r.left + r.width / 2, y: r.top + r.height / 2 };
        })();

        if (pearls.length) {
            pearls.forEach((c) => state.els[c]?.classList.add('win'));
            hostReact('strike');
            sfx('stars');
            await wait(240);
            if (motion()) {
                const tip = hostTip('strike');
                pearls.forEach((c, i) => {
                    const to = cellOnScreen(c);
                    if (tip) jet(tip.x, tip.y, to.x, to.y, i * 70);
                });
                await wait(tip ? 380 : 0);
                await Promise.all(pearls.map((c, i) => fly(c, target.x, target.y, i * 90)));
            } else {
                await wait(300);
            }
            pearls.forEach((c) => state.els[c]?.classList.remove('win', 'spent'));
        }

        if (tide) showTide(spin.tide, true);
        shake(pearls.length ? 5 : 3);
        sfx('multiply', factor);
        floatText(`×${factor}`, [0, COLS * ROWS - 1], 'mult');
        await wait(pearls.length ? 900 : 650);
    }

    // ---- Free spins: down into the Abyss -------------------------------------------------------

    async function freeSpins(on) {
        if (on) showTide(0);
        if (!motion() || !art.whirlpool) return;
        const veil = document.createElement('div');
        veil.className = 'tides-veil';
        const whirl = document.createElement('img');
        whirl.className = 'tides-whirl';
        whirl.src = art.whirlpool;
        whirl.alt = '';
        document.body.append(veil, whirl);
        const ms = on ? 1500 : 1100;
        animate(veil, [{ opacity: 0 }, { opacity: 1, offset: 0.45 }, { opacity: 0 }], ms, { easing: 'ease-in-out' });
        const done = animate(whirl, [
            { opacity: 0, transform: 'rotate(0deg) scale(.15)' },
            { opacity: 1, transform: 'rotate(240deg) scale(.8)', offset: 0.45 },
            { opacity: 0, transform: 'rotate(520deg) scale(1.6)' },
        ], ms, { easing: 'cubic-bezier(.5, 0, .5, 1)' });
        if (on) leap(rand(0.35, 0.6));
        await wait(ms * 0.55);
        done.then(() => {
            veil.remove();
            whirl.remove();
        });
    }

    /** A dolphin leaps across the screen at height y (of the screen's height). */
    function leap(y) {
        const d = art.dolphin;
        if (!d?.src) return;
        const img = document.createElement('img');
        img.className = 'tides-dolphin';
        img.src = d.src;
        img.alt = '';
        const w = Math.min(420, innerWidth * 0.32);
        img.style.width = `${w}px`;
        document.body.append(img);
        const h = w / (d.ar || 1.5);
        const y0 = innerHeight * y;
        const pts = [0, 0.25, 0.5, 0.75, 1].map((t) => ({
            x: -w + (innerWidth + w * 2) * t,
            y: y0 - Math.sin(t * Math.PI) * innerHeight * 0.22,
            r: Math.cos(t * Math.PI) * -28,
        }));
        animate(img, pts.map((p) => ({ transform: `translate(${p.x}px, ${p.y - h / 2}px) rotate(${p.r}deg)` })), 1700, { easing: 'linear' }).then(() => img.remove());
        for (let i = 0; i < 12; i++) setTimeout(() => bubble(innerWidth * (i / 12), y0, rand(8, 20), rand(1800, 2600), innerHeight * 0.3, document.body), dur(i * 120));
    }

    // ---- Big wins: a surge of water and the dolphins --------------------------------------------

    function celebrate(tier, dialog, layer) {
        if (reducedMotion) return;
        if (art.surge) {
            const img = document.createElement('img');
            img.className = 'tides-surge';
            img.src = art.surge;
            img.alt = '';
            layer.prepend(img); // behind the banner
            const w = innerHeight * 2.3;
            img.style.width = `${w}px`;
            animate(img, [
                { transform: `translateX(${-w}px)`, opacity: 0.95 },
                { transform: `translateX(${innerWidth * 0.2}px)`, opacity: 0.95, offset: 0.55 },
                { transform: `translateX(${innerWidth + 40}px)`, opacity: 0 },
            ], 1900, { easing: 'cubic-bezier(.3, .1, .5, 1)' }).then(() => img.remove());
            shake({ big: 6, mega: 8, epic: 10, max: 12 }[tier] ?? 6, 700);
        }
        const n = { big: 2, mega: 3, epic: 4, max: 5 }[tier] ?? 2;
        for (let i = 0; i < n; i++) setTimeout(() => !layer.hidden && circle(dialog, i, n), dur(500 + i * 380));
        for (let i = 0; i < 26; i++) {
            setTimeout(() => bubble(rand(0, innerWidth), innerHeight + 20, rand(10, 34), rand(2600, 4200), innerHeight * rand(0.8, 1.2), document.body), dur(rand(0, 1800)));
        }
    }

    /** A dolphin swims a loop around the banner. */
    function circle(dialog, i, n) {
        const d = art.dolphin;
        if (!d?.src) return;
        const r = dialog.getBoundingClientRect();
        const img = document.createElement('img');
        img.className = 'tides-dolphin';
        img.src = d.src;
        img.alt = '';
        const w = Math.min(300, Math.max(140, r.width * 0.3));
        const h = w / (d.ar || 1.5);
        img.style.width = `${w}px`;
        document.body.append(img);
        const cx = r.left + r.width / 2;
        const cy = r.top + r.height / 2;
        const rx = r.width * 0.62 + w * 0.2;
        const ry = r.height * 0.75 + h * 0.4;
        const start = (i / n) * Math.PI * 2;
        const dir = i % 2 ? -1 : 1;
        const frames = [];
        for (let k = 0; k <= 24; k++) {
            const a = start + dir * (k / 24) * Math.PI * 2;
            // The art faces right; it heads along the tangent of the loop.
            const tangent = Math.atan2(Math.cos(a) * ry * dir, -Math.sin(a) * rx * dir);
            const flip = Math.cos(tangent) < 0 ? -1 : 1;
            frames.push({
                transform: `translate(${cx + Math.cos(a) * rx - w / 2}px, ${cy + Math.sin(a) * ry - h / 2}px) rotate(${flip < 0 ? tangent + Math.PI : tangent}rad) scaleX(${flip})`,
                opacity: k === 0 || k === 24 ? 0 : 1,
            });
        }
        animate(img, frames, 2600, { easing: 'linear' }).then(() => img.remove());
    }

    // ---- Start-up ------------------------------------------------------------------------------------

    function preload() {
        [art.whirlpool, theme.fx?.comet, theme.fx?.frostRing, ...(art.bubbles ?? [])].forEach(sprite);
        Object.keys(theme.symbols ?? {}).forEach(sample);
    }

    return { scenery: sea, preload, winStart, charge, burst, multipliers, freeSpins, celebrate };
}
