/**
 * Stones of the Titan's own effects: the theme kit "cosmos" (theme.php `kit`), on top of the
 * shared slot page (slot-game.js, see `kit` there for the hooks). Everything here is show:
 * the outcome was decided on the server and the page only plays it back.
 *
 * - The cosmos: a drifting nebula with twinkling stars (a small WebGL shader), asteroids
 *   floating by, a shooting star now and then, stardust falling from the Titan's rock.
 * - A win: glowing cracks race across each winner, and it shatters into shards of its own
 *   stone that spiral into a tiny black hole, which closes with a glint.
 * - Meteors: the Titan raises his fist, the meteors streak off the grid into it, he crushes
 *   them and hurls one blazing star into the win (base game) or into the Eclipse (free spins),
 *   the running multiplier, whose corona grows with it.
 * - Free spins: the moon slides across the star, the corona flares, and the scene turns to the
 *   Eclipse.
 * - Big wins: a torrent of starfire sweeps the screen and meteors rain behind the banner.
 *
 * Quick-stop (state.quick) and reduced motion skip the show; turbo speeds it up (api.dur).
 */
export function createKit(api) {
    const {
        state, theme, $, board, reducedMotion, rand, dur, wait, animate, sfx, emit, sprite, cellBox, cellOnScreen,
        shake, floatText, hostReact, hostPoint, COLS, ROWS, starTier,
    } = api;
    const art = theme.kitArt ?? {};
    const scenery = document.querySelector('.scenery');
    const motion = () => !reducedMotion && !state.quick;
    const calm = () => !document.hidden && !state.busy && $('overlay').hidden;

    // ---- The cosmos ------------------------------------------------------------------------

    function cosmos() {
        nebula();
        if (reducedMotion) return;
        setTimeout(asteroidLoop, 800);
        setTimeout(shootingStarLoop, rand(4000, 9000));
        stardustLoop();
    }

    /**
     * The nebula drifts: soft clouds of gold, rose and teal light (crimson and gold in the
     * Eclipse) slide slowly over the background, and stars twinkle at three depths. Drawn at a
     * quarter of the pixels at about 30 fps and screened over the background; none without
     * WebGL or with reduced motion.
     */
    function nebula() {
        if (reducedMotion) return;
        const c = document.createElement('canvas');
        c.className = 'cosmos-nebula';
        const gl = c.getContext('webgl', { alpha: false, antialias: false, premultipliedAlpha: false });
        if (!gl) return;
        document.querySelector('.bg.night')?.after(c);
        const vs = 'attribute vec2 p; void main() { gl_Position = vec4(p, 0.0, 1.0); }';
        const fs = `precision mediump float;
            uniform vec2 res; uniform float t; uniform float eclipse;
            float hash(vec2 p) { return fract(sin(dot(p, vec2(127.1, 311.7))) * 43758.5453); }
            float noise(vec2 p) {
                vec2 i = floor(p); vec2 f = fract(p); f = f * f * (3.0 - 2.0 * f);
                return mix(mix(hash(i), hash(i + vec2(1.0, 0.0)), f.x), mix(hash(i + vec2(0.0, 1.0)), hash(i + vec2(1.0, 1.0)), f.x), f.y);
            }
            float fbm(vec2 p) { float v = 0.0; float a = 0.5; for (int k = 0; k < 5; k++) { v += a * noise(p); p = p * 2.03 + 3.1; a *= 0.5; } return v; }
            float stars(vec2 uv, float scale, float speed) {
                vec2 g = uv * scale + vec2(t * speed, 0.0);
                vec2 id = floor(g); vec2 f = fract(g) - 0.5;
                float h = hash(id);
                float on = step(0.93, h);
                float tw = 0.55 + 0.45 * sin(t * (1.5 + h * 3.0) + h * 40.0);
                return on * tw * smoothstep(0.09, 0.0, length(f + (vec2(hash(id + 1.7), hash(id + 2.9)) - 0.5) * 0.6));
            }
            void main() {
                vec2 uv = gl_FragCoord.xy / res.y;
                float n = fbm(uv * 1.6 + vec2(t * 0.012, t * 0.006));
                float m = fbm(uv * 3.1 - vec2(t * 0.02, -t * 0.008) + n);
                float cloud = smoothstep(0.42, 0.95, n * 0.65 + m * 0.55);
                vec3 day = mix(vec3(1.0, 0.72, 0.38), vec3(0.3, 0.85, 0.95), smoothstep(0.3, 0.8, m));
                vec3 night = mix(vec3(1.0, 0.25, 0.18), vec3(1.0, 0.7, 0.3), smoothstep(0.3, 0.8, m));
                vec3 col = mix(day, night, eclipse) * cloud * 0.55;
                col += vec3(1.0, 0.95, 0.85) * (stars(uv, 60.0, 0.15) * 0.8 + stars(uv, 110.0, 0.08) * 0.55 + stars(uv, 190.0, 0.04) * 0.4);
                gl_FragColor = vec4(col, 1.0);
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
        const uEclipse = gl.getUniformLocation(prog, 'eclipse');
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
        let eclipse = 0;
        const frame = (now) => {
            requestAnimationFrame(frame);
            if (document.hidden || now - last < 33) return;
            last = now;
            // Eases into the Eclipse's colours over about a second and a half.
            eclipse += ((document.body.classList.contains('free') ? 1 : 0) - eclipse) * 0.04;
            gl.uniform1f(uT, (now - started) / 1000);
            gl.uniform1f(uEclipse, eclipse);
            gl.drawArrays(gl.TRIANGLE_STRIP, 0, 4);
        };
        requestAnimationFrame(frame);
    }

    /** Asteroids float across at different depths, turning slowly; a few at a time. */
    function asteroidLoop() {
        setTimeout(asteroidLoop, rand(3500, 7000));
        const pool = art.asteroids ?? [];
        if (!pool.length || document.hidden || document.querySelectorAll('.cosmos-rock').length > 5) return;
        const depth = rand(0.25, 1);
        const rock = document.createElement('img');
        rock.className = 'cosmos-rock';
        rock.src = pool[Math.floor(Math.random() * pool.length)];
        rock.alt = '';
        const size = 24 + depth * rand(40, 110);
        rock.style.width = `${size}px`;
        rock.style.opacity = String(0.35 + depth * 0.55);
        rock.style.filter = `blur(${((1 - depth) * 2.2).toFixed(1)}px) brightness(${(0.55 + depth * 0.45).toFixed(2)})`;
        rock.style.zIndex = depth > 0.75 ? '3' : '0';
        scenery?.append(rock);
        const right = Math.random() < 0.5;
        const y0 = rand(0.05, 0.85) * innerHeight;
        const y1 = y0 + rand(-0.15, 0.15) * innerHeight;
        const from = right ? -size - 20 : innerWidth + 20;
        const to = right ? innerWidth + 20 : -size - 20;
        const turn = rand(-200, 200);
        rock.animate([
            { transform: `translate(${from}px, ${y0}px) rotate(0deg)` },
            { transform: `translate(${to}px, ${y1}px) rotate(${turn}deg)` },
        ], { duration: (60000 - depth * 30000) * rand(0.8, 1.2), easing: 'linear' }).finished.catch(() => {}).then(() => rock.remove());
    }

    /** Now and then a shooting star streaks across the upper sky. */
    function shootingStarLoop() {
        setTimeout(shootingStarLoop, rand(7000, 15000));
        const src = theme.fx?.comet;
        if (!src || !calm()) return;
        const img = document.createElement('img');
        img.className = 'cosmos-streak';
        img.src = src;
        img.alt = '';
        const len = rand(0.14, 0.26) * innerWidth;
        Object.assign(img.style, { width: `${len}px`, height: `${len / 2}px` });
        scenery?.append(img);
        const leftward = Math.random() < 0.5;
        const x0 = leftward ? innerWidth * rand(0.6, 1) : innerWidth * rand(0, 0.4);
        const y0 = innerHeight * rand(0.02, 0.3);
        const angle = (leftward ? 180 - rand(12, 28) : rand(12, 28)) * (Math.PI / 180);
        const dist = innerWidth * rand(0.35, 0.6);
        const at = (d, s) => `translate(${x0 + Math.cos(angle) * d - len}px, ${y0 + Math.sin(angle) * d - len / 4}px) rotate(${angle}rad) scaleX(${s})`;
        img.style.transformOrigin = '100% 50%';
        img.animate([
            { transform: at(0, 0.2), opacity: 0 },
            { transform: at(dist * 0.3, 1), opacity: 1, offset: 0.25 },
            { transform: at(dist, 0.6), opacity: 0 },
        ], { duration: rand(1100, 1600), easing: 'cubic-bezier(.3, .1, .6, 1)' }).finished.catch(() => {}).then(() => img.remove());
    }

    /** Stardust drifts down from the Titan's floating rock. */
    function stardustLoop() {
        const pool = art.stardust ?? [];
        if (!pool.length) return;
        const fall = () => {
            setTimeout(fall, rand(260, 520));
            const rock = document.querySelector('.throne .pedestal')?.getBoundingClientRect();
            if (document.hidden || !rock?.width || document.querySelectorAll('.cosmos-dust').length > 18) return;
            const dot = document.createElement('img');
            dot.className = 'cosmos-dust';
            dot.src = pool[Math.floor(Math.random() * pool.length)];
            dot.alt = '';
            const size = rand(5, 13);
            dot.style.width = `${size}px`;
            document.body.append(dot);
            const x = rock.left + rock.width * rand(0.22, 0.78);
            const y = rock.top + rock.height * rand(0.3, 0.55);
            const drop = rand(0.1, 0.3) * innerHeight;
            const sway = rand(-18, 18);
            dot.animate([
                { transform: `translate(${x}px, ${y}px) scale(.6)`, opacity: 0 },
                { transform: `translate(${x + sway * 0.4}px, ${y + drop * 0.25}px) scale(1)`, opacity: 1, offset: 0.2 },
                { transform: `translate(${x + sway}px, ${y + drop}px) scale(.4)`, opacity: 0 },
            ], { duration: rand(2400, 4200), easing: 'ease-in' }).finished.catch(() => {}).then(() => dot.remove());
        };
        fall();
    }

    // ---- A win: cracks, then each winner shatters into shards of its own stone ---------------

    const shardSets = new Map(); // symbol letter -> [{ cv, ox, oy, w, h }] (offsets and sizes of the symbol's size)

    /**
     * The symbol's art cut into shards: sectors around a jittered centre, in two or three rings,
     * each painted on its own little canvas. Shards with almost nothing on them are left out.
     */
    function shards(letter) {
        if (shardSets.has(letter)) return shardSets.get(letter);
        shardSets.set(letter, []);
        const url = theme.symbols?.[letter]?.img;
        if (!url) return [];
        const im = new Image();
        im.decoding = 'async';
        im.onload = () => {
            const n = 150;
            const src = document.createElement('canvas');
            src.width = src.height = n;
            const g = src.getContext('2d', { willReadFrequently: true });
            if (!g) return;
            g.drawImage(im, 0, 0, n, n);
            let alpha;
            try {
                alpha = g.getImageData(0, 0, n, n).data;
            } catch {
                return; // a cross-origin image can't be read: no shards, the burst still shows
            }
            const cx = n * rand(0.44, 0.56);
            const cy = n * rand(0.44, 0.56);
            const sectors = Math.floor(rand(7, 10));
            const cuts = Array.from({ length: sectors }, (_, i) => ((i + rand(-0.3, 0.3)) / sectors) * Math.PI * 2);
            const rings = [0, n * rand(0.16, 0.22), n * rand(0.34, 0.42), n * 0.8];
            const out = [];
            for (let s = 0; s < sectors; s++) {
                const a0 = cuts[s];
                const a1 = s + 1 < sectors ? cuts[s + 1] : cuts[0] + Math.PI * 2;
                for (let r = 0; r < rings.length - 1; r++) {
                    const pts = [];
                    for (const [a, rad] of [[a0, rings[r]], [a1, rings[r]], [a1, rings[r + 1]], [(a0 + a1) / 2, rings[r + 1] * rand(1, 1.08)], [a0, rings[r + 1]]]) {
                        pts.push([cx + Math.cos(a) * rad, cy + Math.sin(a) * rad]);
                    }
                    const xs = pts.map((p) => p[0]);
                    const ys = pts.map((p) => p[1]);
                    const x0 = Math.max(0, Math.floor(Math.min(...xs)));
                    const y0 = Math.max(0, Math.floor(Math.min(...ys)));
                    const x1 = Math.min(n, Math.ceil(Math.max(...xs)));
                    const y1 = Math.min(n, Math.ceil(Math.max(...ys)));
                    if (x1 - x0 < 3 || y1 - y0 < 3) continue;
                    let solid = 0;
                    for (let y = y0; y < y1; y += 2) for (let x = x0; x < x1; x += 2) if (alpha[(y * n + x) * 4 + 3] > 120) solid++;
                    if (solid < 6) continue;
                    const cv = document.createElement('canvas');
                    cv.width = x1 - x0;
                    cv.height = y1 - y0;
                    const c2 = cv.getContext('2d');
                    c2.beginPath();
                    pts.forEach(([x, y], i) => (i ? c2.lineTo(x - x0, y - y0) : c2.moveTo(x - x0, y - y0)));
                    c2.closePath();
                    c2.clip();
                    c2.drawImage(src, -x0, -y0);
                    // A bright rim: the shard's broken edge catches the light.
                    c2.strokeStyle = 'rgba(255, 236, 180, .55)';
                    c2.lineWidth = 1.5;
                    c2.stroke();
                    out.push({ cv, ox: ((x0 + x1) / 2 - n / 2) / n, oy: ((y0 + y1) / 2 - n / 2) / n, w: (x1 - x0) / n, h: (y1 - y0) / n });
                }
            }
            shardSets.set(letter, out);
        };
        im.src = url;
        return [];
    }

    /** Glowing cracks race across the winners while they light up. */
    function winStart(cells, step, pace) {
        if (!motion() || !art.cracks) return;
        for (const cell of cells) {
            const el = state.els[cell];
            if (!el) continue;
            const crack = document.createElement('img');
            crack.className = 'cosmos-crack';
            crack.src = art.cracks;
            crack.alt = '';
            crack.style.rotate = `${Math.floor(rand(0, 360))}deg`;
            el.append(crack);
            animate(crack, [
                { opacity: 0, transform: 'scale(.15)' },
                { opacity: 0.55, transform: 'scale(.55)', offset: 0.45 },
                { opacity: 1, transform: 'scale(1.05)' },
            ], pace.hold + pace.frost, { easing: 'cubic-bezier(.5, 0, .8, .6)' });
        }
    }

    /** The winners tremble and brighten, just before they shatter. */
    function charge(cells, ms) {
        if (!motion()) return;
        for (const cell of cells) {
            const img = state.els[cell]?.querySelector(':scope > img, .star, .basket');
            if (!img) continue;
            const j = () => `${rand(-2.5, 2.5).toFixed(1)}px ${rand(-2.5, 2.5).toFixed(1)}px`;
            animate(img, [
                { translate: '0 0', filter: 'brightness(1)' },
                { translate: j(), offset: 0.25 }, { translate: j(), offset: 0.5 }, { translate: j(), offset: 0.75 },
                { translate: '0 0', filter: 'brightness(1.6)' },
            ], ms, { easing: 'linear' });
        }
    }

    /** A winner shatters: a flash and a ring, its shards fly out and spiral into a tiny black hole. */
    function burst(cell, symbol) {
        if (!motion()) return;
        const { x, y, size } = cellBox(cell);
        const flash = sprite(theme.fx?.frostBurst);
        if (flash) emit({ img: flash, x, y, w: size * 1.2, h: size * 1.2, s0: 0.3, s1: 1.05, a0: 0.85, fade: 0.2, life: 0.38, light: true });
        const ring = sprite(theme.fx?.frostRing);
        if (ring) emit({ img: ring, x, y, w: size * 1.4, h: size * 1.4, s0: 0.2, s1: 1.35, a0: 0.8, fade: 0.2, life: 0.55, light: true });
        const hole = sprite(art.vortex);
        if (hole) {
            emit({ img: hole, x, y, w: size * 1.3, h: size * 1.3, s0: 0.15, s1: 1, a0: 1, a1: 0, fade: 0.65, life: 1.15, vr: -7, light: true, ease: (t) => Math.sin(t * Math.PI / 2) });
        }
        const pieces = shardSets.get(symbol) ?? shards(symbol);
        for (const [i, piece] of pieces.entries()) {
            const out = Math.atan2(piece.oy, piece.ox) + rand(-0.35, 0.35);
            const r0 = Math.hypot(piece.ox, piece.oy) * size * 0.9;
            const reach = size * rand(0.45, 0.95);
            const turns = rand(1.2, 2.1) * (i % 2 ? 1 : -1);
            const spin = rand(-9, 9);
            const life = rand(0.95, 1.25);
            emit({
                img: piece.cv, x, y, w: piece.w * size * 0.9, h: piece.h * size * 0.9, life, s0: 1, s1: 0.05, ease: (t) => (t < 0.45 ? 0 : ((t - 0.45) / 0.55) ** 1.6),
                fade: 0.85,
                move(p) {
                    const t = p.age / life;
                    // Out for the first third, then pulled round and in.
                    const r = t < 0.35 ? r0 + reach * Math.sin((t / 0.35) * Math.PI / 2) : (r0 + reach) * (1 - ((t - 0.35) / 0.65) ** 1.4);
                    const a = out + (t < 0.35 ? 0 : turns * ((t - 0.35) / 0.65) ** 1.2 * Math.PI);
                    p.x = x + Math.cos(a) * r;
                    p.y = y + Math.sin(a) * r;
                    p.rot = spin * t;
                },
            });
        }
        const sparks = theme.fx?.crystals ?? [];
        for (let i = 0; i < 7 && sparks.length; i++) {
            const a = rand(0, Math.PI * 2);
            const v = size * rand(1.6, 3.4);
            emit({ img: sprite(sparks[i % sparks.length]), x, y, w: size * 0.12, h: size * 0.12, vx: Math.cos(a) * v, vy: Math.sin(a) * v - size, g: size * 5,
                vr: rand(-8, 8), life: rand(0.5, 0.8), fade: 0.4, light: true });
        }
        const glint = sprite(theme.fx?.twinkle);
        if (glint) emit({ img: glint, x, y, w: size * 0.9, h: size * 0.9, s0: 0.1, s1: 1, a0: 1, a1: 0, fade: 0.3, life: 0.45, delay: 1.0, vr: 3, light: true });
    }

    // ---- Meteors: the Titan crushes them, and hurls a star into the win or the Eclipse ----------

    let eclipseEl = null;

    function eclipseMeter() {
        if (eclipseEl || !art.eclipseRing) return eclipseEl;
        const wrap = board.closest('.board-wrap');
        if (!wrap) return null;
        eclipseEl = document.createElement('div');
        eclipseEl.className = 'eclipse empty';
        eclipseEl.setAttribute('aria-live', 'polite');
        eclipseEl.innerHTML = `<img class="corona" src="${art.corona ?? ''}" alt=""><i class="sun"></i><img class="ring" src="${art.eclipseRing}" alt=""><b>—</b><small>ECLIPSE</small>`;
        wrap.append(eclipseEl);
        return eclipseEl;
    }

    function showEclipse(value, bump = false) {
        const el = eclipseMeter();
        if (!el) return;
        el.classList.toggle('empty', !value);
        el.querySelector('b').textContent = value ? `×${value}` : '—';
        el.dataset.level = value >= 200 ? '3' : value >= 50 ? '2' : value > 0 ? '1' : '0';
        el.setAttribute('aria-label', value ? `Eclipse ×${value}` : 'Eclipse');
        if (bump) {
            el.classList.remove('bump');
            void el.offsetWidth;
            el.classList.add('bump');
        }
    }

    /**
     * Where the Titan's crushing fist is on screen: the crush pose's fist, or (that pose not yet
     * drawn) his open palm at rest; failing those his upper body, or the grid's centre.
     */
    function fistOnScreen() {
        const fist = hostPoint('crush', 'fist') ?? hostPoint('idle', 'palm') ?? hostPoint('crush', 'head');
        if (fist) return fist;
        const fig = [...document.querySelectorAll('.host-fig.on')].map((f) => f.getBoundingClientRect()).find((r) => r.width);
        if (fig) return { x: fig.left + fig.width * 0.36, y: fig.top + fig.height * 0.16 };
        const r = board.getBoundingClientRect();
        return { x: r.left + r.width / 2, y: r.top + r.height / 2 };
    }

    /** A streak of starfire from (x0, y0) to (x1, y1), on screen. */
    function streak(x0, y0, x1, y1, delay = 0, ms = 560) {
        const src = theme.fx?.comet;
        if (!src) return;
        const img = document.createElement('img');
        img.className = 'cosmos-fx';
        img.src = src;
        img.alt = '';
        const len = Math.hypot(x1 - x0, y1 - y0);
        Object.assign(img.style, { width: `${len}px`, height: `${Math.max(34, len * 0.2)}px`, transformOrigin: '0 50%' });
        document.body.append(img);
        const base = `translate(${x0}px, ${y0}px) translateY(-50%) rotate(${Math.atan2(y1 - y0, x1 - x0)}rad)`;
        animate(img, [
            { transform: `${base} scaleX(0)`, opacity: 1 },
            { transform: `${base} scaleX(1)`, opacity: 1, offset: 0.6 },
            { transform: `${base} scaleX(1)`, opacity: 0 },
        ], ms, { delay, easing: 'ease-in' }).then(() => img.remove());
    }

    /** A light (art) flaring at (x, y) on screen, `size` px. */
    function flare(src, x, y, size, ms = 520, from = 0.2, to = 1.4) {
        if (!src) return;
        const img = document.createElement('img');
        img.className = 'cosmos-fx';
        img.src = src;
        img.alt = '';
        Object.assign(img.style, { width: `${size}px`, height: `${size}px` });
        document.body.append(img);
        const at = `translate(${x - size / 2}px, ${y - size / 2}px)`;
        animate(img, [{ transform: `${at} scale(${from})`, opacity: 1 }, { transform: `${at} scale(${to})`, opacity: 0 }], ms).then(() => img.remove());
    }

    /** A meteor lifts off its cell and streaks to (tx, ty) on screen. */
    async function fly(cell, tx, ty, delay) {
        const value = state.cells[cell]?.v ?? 0;
        const from = cellOnScreen(cell);
        const el = document.createElement('div');
        el.className = 'cosmos-meteor';
        el.style.width = `${from.size}px`;
        el.style.height = `${from.size}px`;
        el.innerHTML = `<div class="sym"><div class="star"><img src="${theme.stars?.[starTier(value)] ?? ''}" alt=""><b>${value}×</b></div></div>`;
        document.body.append(el);
        state.els[cell]?.classList.add('spent');
        const mx = (from.x + tx) / 2 + rand(-40, 40);
        const my = Math.min(from.y, ty) - from.size * 0.9;
        const at = (x, y, s) => `translate(${x - from.size / 2}px, ${y - from.size / 2}px) scale(${s})`;
        streak(from.x, from.y, tx, ty, delay + 120, 620);
        await animate(el, [
            { transform: at(from.x, from.y, 1), opacity: 1 },
            { transform: at(from.x, from.y - from.size * 0.3, 1.18), opacity: 1, offset: 0.2 },
            { transform: at(mx, my, 0.85), opacity: 1, offset: 0.6 },
            { transform: at(tx, ty, 0.3), opacity: 0.25 },
        ], 720, { delay, easing: 'cubic-bezier(.5, 0, .7, 1)' });
        el.remove();
    }

    /** The blazing star the Titan hurls from his fist to (tx, ty), on screen. */
    async function hurl(x0, y0, tx, ty) {
        const el = document.createElement('img');
        el.className = 'cosmos-fx cosmos-star';
        el.src = art.corona ?? theme.fx?.frostBurst ?? '';
        el.alt = '';
        const size = Math.min(160, innerWidth * 0.1);
        Object.assign(el.style, { width: `${size}px`, height: `${size}px` });
        document.body.append(el);
        streak(x0, y0, tx, ty, 40, 520);
        const at = (x, y, s) => `translate(${x - size / 2}px, ${y - size / 2}px) scale(${s}) rotate(${s * 160}deg)`;
        await animate(el, [{ transform: at(x0, y0, 0.4), opacity: 1 }, { transform: at(tx, ty, 0.9), opacity: 1 }], 480, { easing: 'cubic-bezier(.6, 0, .9, .5)' });
        el.remove();
    }

    /**
     * A spin's meteors. The base game: they streak into the Titan's fist, he crushes them, and
     * the star he hurls lands on the tumble plaque (or the grid) with their sum. Free spins:
     * the star flies into the Eclipse, and the Eclipse multiplies the spin (also when no new
     * meteor came).
     */
    async function multipliers(spin) {
        const tide = spin.tide !== null && spin.tide !== undefined;
        const factor = tide ? spin.tide : spin.multiplier;
        const meteors = spin.multiplier > 0 ? spin.multiplierCells : [];
        const targetEl = tide ? eclipseMeter() : (document.querySelector('.tumble') ?? board);
        const target = (() => {
            const r = (targetEl ?? board).getBoundingClientRect();
            return r.width ? { x: r.left + r.width / 2, y: r.top + r.height / 2 } : (() => {
                const b = board.getBoundingClientRect();
                return { x: b.left + b.width / 2, y: b.top + b.height / 2 };
            })();
        })();

        if (meteors.length) {
            meteors.forEach((c) => state.els[c]?.classList.add('win'));
            hostReact('crush');
            sfx('stars');
            await wait(260);
            if (motion()) {
                const fist = fistOnScreen();
                await Promise.all(meteors.map((c, i) => fly(c, fist.x, fist.y, i * 90)));
                // The crush: light bursts from his fist.
                flare(theme.fx?.frostBurst, fist.x, fist.y, Math.min(260, innerWidth * 0.16), 600, 0.3, 1.6);
                flare(theme.fx?.frostRing, fist.x, fist.y, Math.min(300, innerWidth * 0.2), 620, 0.2, 1.8);
                sfx('crush', factor);
                shake(4, 260);
                await wait(220);
                await hurl(fist.x, fist.y, target.x, target.y);
            } else {
                await wait(300);
            }
            meteors.forEach((c) => state.els[c]?.classList.remove('win', 'spent'));
        }

        if (tide) showEclipse(spin.tide, true);
        if (motion()) {
            flare(art.corona, target.x, target.y, Math.min(320, innerWidth * 0.22), 700, 0.3, 1.5);
            flare(theme.fx?.frostRing, target.x, target.y, Math.min(260, innerWidth * 0.18), 560);
        }
        shake(meteors.length ? 6 : 3);
        sfx('multiply', factor);
        floatText(`×${factor}`, [0, COLS * ROWS - 1], 'mult');
        await wait(meteors.length ? 900 : 650);
    }

    // ---- Free spins: the Eclipse -----------------------------------------------------------------

    async function freeSpins(on, plan) {
        if (on) showEclipse(plan?.tideStart ?? 0);
        if (!motion()) return;
        const veil = document.createElement('div');
        veil.className = 'cosmos-veil';
        document.body.append(veil);
        if (!on) {
            await animate(veil, [{ opacity: 0 }, { opacity: 0.85, offset: 0.4 }, { opacity: 0 }], 1000, { easing: 'ease-in-out' });
            veil.remove();
            return;
        }
        // The star, the moon sliding across it, the corona flaring out, then light pouring back.
        const size = Math.min(innerWidth, innerHeight) * 0.42;
        const sun = document.createElement('div');
        sun.className = 'cosmos-sun';
        const moon = document.createElement('div');
        moon.className = 'cosmos-moon';
        const corona = document.createElement('img');
        corona.className = 'cosmos-corona';
        corona.src = art.corona ?? '';
        corona.alt = '';
        for (const el of [sun, moon]) Object.assign(el.style, { width: `${size}px`, height: `${size}px` });
        Object.assign(corona.style, { width: `${size * 2.6}px`, height: `${size * 2.6}px` });
        veil.append(corona, sun, moon);
        sfx('eclipse');
        animate(veil, [{ opacity: 0 }, { opacity: 1, offset: 0.12 }, { opacity: 1, offset: 0.82 }, { opacity: 0 }], 2300, { easing: 'ease-in-out' });
        animate(sun, [{ scale: 0.6, opacity: 0 }, { scale: 1, opacity: 1, offset: 0.2 }, { scale: 1, opacity: 1 }], 2300);
        animate(moon, [
            { translate: `${-size * 1.3}px 0`, opacity: 0 },
            { translate: `${-size * 1.1}px 0`, opacity: 1, offset: 0.15 },
            { translate: '0 0', opacity: 1, offset: 0.55 },
            { translate: '0 0', opacity: 1 },
        ], 2300, { easing: 'cubic-bezier(.4, 0, .3, 1)' });
        animate(corona, [
            { scale: 0.3, opacity: 0, rotate: '0deg' },
            { scale: 0.4, opacity: 0, rotate: '20deg', offset: 0.5 },
            { scale: 1.15, opacity: 1, rotate: '60deg', offset: 0.68 },
            { scale: 1.5, opacity: 0, rotate: '110deg' },
        ], 2300, { easing: 'ease-out' });
        setTimeout(() => shake(10, 600), dur(2300 * 0.6));
        await wait(2300 * 0.78);
        setTimeout(() => veil.remove(), dur(700));
    }

    // ---- Big wins: a torrent of starfire and a meteor shower ------------------------------------

    function celebrate(tier, dialog, layer) {
        if (reducedMotion) return;
        if (art.sweep) {
            const img = document.createElement('img');
            img.className = 'cosmos-sweep';
            img.src = art.sweep;
            img.alt = '';
            layer.prepend(img); // behind the banner
            const w = innerHeight * 2.4;
            img.style.width = `${w}px`;
            animate(img, [
                { transform: `translateX(${-w}px)`, opacity: 1 },
                { transform: `translateX(${innerWidth * 0.15}px)`, opacity: 1, offset: 0.55 },
                { transform: `translateX(${innerWidth + 40}px)`, opacity: 0 },
            ], 2000, { easing: 'cubic-bezier(.3, .1, .5, 1)' }).then(() => img.remove());
        }
        const n = { big: 8, mega: 12, epic: 16, max: 22 }[tier] ?? 8;
        for (let i = 0; i < n; i++) setTimeout(() => !layer.hidden && rainMeteor(layer), dur(300 + i * rand(120, 220)));
        const r = dialog.getBoundingClientRect();
        for (let i = 0; i < 3; i++) {
            setTimeout(() => !layer.hidden && flare(theme.fx?.frostRing, r.left + r.width / 2, r.top + r.height / 2, Math.max(r.width, 300) * 1.3, 900, 0.3, 1.6), dur(200 + i * 500));
        }
    }

    /** A meteor falls diagonally behind the banner. */
    function rainMeteor(layer) {
        const src = theme.fx?.comet;
        if (!src) return;
        const img = document.createElement('img');
        img.className = 'cosmos-rain';
        img.src = src;
        img.alt = '';
        const len = rand(0.18, 0.34) * Math.max(innerWidth, innerHeight);
        Object.assign(img.style, { width: `${len}px`, height: `${len / 2}px`, transformOrigin: '100% 50%' });
        layer.prepend(img);
        const angle = rand(115, 140) * (Math.PI / 180); // down and to the left
        const x0 = innerWidth * rand(0.3, 1.1);
        const y0 = -len * 0.2;
        const d = Math.max(innerWidth, innerHeight) * 1.2;
        const at = (k) => `translate(${x0 + Math.cos(angle) * d * k - len}px, ${y0 + Math.sin(angle) * d * k - len / 4}px) rotate(${angle}rad)`;
        animate(img, [{ transform: at(0), opacity: 0 }, { transform: at(0.2), opacity: 1, offset: 0.15 }, { transform: at(1), opacity: 0.8 }],
            rand(900, 1400), { easing: 'linear' }).then(() => img.remove());
    }

    // ---- Start-up ------------------------------------------------------------------------------------

    function preload() {
        [art.cracks, art.vortex, art.corona, theme.fx?.comet, theme.fx?.frostBurst, theme.fx?.frostRing].forEach(sprite);
        Object.keys(theme.symbols ?? {}).forEach(shards);
    }

    return { scenery: cosmos, preload, winStart, charge, burst, multipliers, freeSpins, celebrate };
}
