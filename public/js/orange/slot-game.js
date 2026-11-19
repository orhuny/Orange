/**
 * The slot page (resources/views/games/slot.blade.php): the board, its animations, the bet
 * bar, ante, bonus buy, autoplay, turbo, the rules and the bridge to the casino page.
 *
 * Every bet is resolved on the server before anything moves here: the page only plays back
 * the outcome it is given (slot-timeline.js turns it into steps). A plain ES module.
 */
import { createGameBridge } from './bridge.js';
import {
    COLS, COST, ROWS, MULTIPLIER, SCATTER, affordable, money, plan, playableSteps, stake, starTier, toUnits, winAmount, winTier,
} from './slot-timeline.js';

const TIERS = { big: 'BIG WIN', mega: 'MEGA WIN', epic: 'EPIC WIN', max: 'MAX WIN' };
const rand = (lo, hi) => lo + Math.random() * (hi - lo);
const easeOut = (t) => 1 - Math.pow(1 - t, 3);
const linear = (t) => t;
/** An amount for the screen: money() with thousands separators. Never sent to the casino. */
const shown = (value) => money(value).replace(/^-?\d+/, (whole) => whole.replace(/\B(?=(\d{3})+(?!\d))/g, ','));

/**
 * @param {object} cfg  from the blade view: allowedOrigins, theme, rules (SlotVerifier::describe), config, embedded
 */
export function startSlot(cfg) {
    const $ = (id) => document.getElementById(id);
    const board = $('board');
    const fx = $('fx');
    const theme = cfg.theme;
    const features = cfg.rules?.features ?? { ante: false, buy: false };

    const state = {
        currency: '',
        balance: null,
        limits: null,
        steps: playableSteps(cfg.config.bet_steps ?? ['1.00'], null),
        bet: null,
        ante: false,
        turbo: false,
        quick: false,
        busy: false,
        auto: 0,
        autoStopOnFeature: true,
        cells: idleGrid(),
        els: [],
        fairness: null,
        result: null,
    };
    state.bet = state.steps.includes('1.00') ? '1.00' : state.steps[Math.min(4, state.steps.length - 1)];

    // ---- Sound -------------------------------------------------------------------------------

    // Sound effects only, while a spin plays (spin, landing, wins, free spins…). There is no
    // background music: the pack's ambience is never started.
    const sound = { ctx: null, pack: null, on: readPref('sound', true) };
    function audio() {
        if (!sound.on) return null;
        if (!sound.ctx) {
            const Ctx = window.AudioContext || window.webkitAudioContext;
            const factory = window.OrangeSound?.[theme.sound];
            if (!Ctx || !factory) return null;
            sound.ctx = new Ctx();
            const master = sound.ctx.createGain();
            master.gain.value = 0.5;
            master.connect(sound.ctx.destination);
            sound.pack = factory(sound.ctx, master);
        }
        if (sound.ctx.state === 'suspended') sound.ctx.resume();
        return sound.pack;
    }
    function sfx(name, arg) {
        if (sound.on && !state.quick) audio()?.sfx(name, arg);
    }
    function setSound(on) {
        sound.on = on;
        writePref('sound', on);
        $('sound-btn').setAttribute('aria-pressed', String(on));
    }

    // ---- Timing --------------------------------------------------------------------------------

    const speed = () => (state.quick ? 0.12 : state.turbo ? 0.5 : 1);
    const dur = (ms) => Math.max(1, Math.round(ms * speed()));
    const wait = (ms) => new Promise((r) => setTimeout(r, dur(ms)));

    function animate(el, frames, ms, opts = {}) {
        if (!el.animate) return Promise.resolve();
        const a = el.animate(frames, { duration: dur(ms), easing: 'ease-out', fill: 'forwards', ...opts, delay: dur(opts.delay ?? 0) });
        return a.finished.catch(() => {}).then(() => a);
    }

    // ---- The board -----------------------------------------------------------------------------

    const at = (cell, rowShift = 0) => `translate(${Math.floor(cell / ROWS) * 100}%, ${((cell % ROWS) + rowShift) * 100}%)`;

    function symbolEl(cell) {
        const el = document.createElement('div');
        el.className = 'sym';
        el.dataset.s = cell.s;
        if (cell.s === MULTIPLIER) {
            el.innerHTML = `<div class="star" aria-label="${cell.v}× multiplier"><img src="${theme.stars?.[starTier(cell.v)] ?? ''}" alt="" draggable="false"><b>${cell.v}×</b></div>`;
        } else {
            const img = document.createElement('img');
            img.src = theme.symbols[cell.s]?.img ?? '';
            img.alt = theme.symbols[cell.s]?.name ?? cell.s;
            img.draggable = false;
            el.append(img);
        }
        return el;
    }

    function render(cells) {
        board.querySelectorAll('.sym').forEach((e) => e.remove());
        state.els = cells.map((cell, i) => {
            const el = symbolEl(cell);
            el.style.transform = at(i);
            board.append(el);
            return el;
        });
        state.cells = cells;
    }

    async function dropOut() {
        const els = state.els.filter(Boolean);
        if (!els.length) return;
        sfx('spin');
        await Promise.all(els.map((el, i) => {
            const cell = state.els.indexOf(el);
            return animate(el, [{ transform: at(cell) }, { transform: at(cell, ROWS + 1) }], 300,
                { delay: Math.floor(cell / ROWS) * 35, easing: 'cubic-bezier(.5,0,.9,.5)' });
        }));
        els.forEach((e) => e.remove());
        state.els = [];
    }

    async function dropIn(cells) {
        board.querySelectorAll('.sym').forEach((e) => e.remove());
        state.cells = cells;
        state.els = cells.map((cell, i) => {
            const el = symbolEl(cell);
            el.style.transform = at(i, -ROWS - 1);
            board.append(el);
            return el;
        });
        const landings = [];
        for (let col = 0; col < COLS; col++) {
            const delay = col * 75;
            setTimeout(() => sfx('land', col), dur(delay + 330));
            for (let row = 0; row < ROWS; row++) {
                const i = col * ROWS + row;
                landings.push(animate(state.els[i], [
                    { transform: at(i, -ROWS - 1) },
                    { transform: at(i, 0.06), offset: 0.82 },
                    { transform: at(i) },
                ], 420, { delay: delay + (ROWS - 1 - row) * 22, easing: 'cubic-bezier(.45,.05,.55,1)' }));
            }
        }
        await Promise.all(landings);
        state.els.forEach((el, i) => settle(el, i));
        const scatters = cells.filter((c) => c.s === SCATTER).length;
        for (let n = 1; n <= scatters; n++) setTimeout(() => sfx('scatter', n), dur(n * 110));
        if (cells.some((c) => c.s === MULTIPLIER)) sfx('star');
    }

    /** Pins an element where its animation left it, and drops the animation. */
    function settle(el, cell) {
        el.style.transform = at(cell);
        el.getAnimations?.().forEach((a) => a.cancel());
    }

    function centre(cells) {
        const cols = cells.map((c) => Math.floor(c / ROWS));
        const rows = cells.map((c) => c % ROWS);
        const avg = (a) => a.reduce((s, v) => s + v, 0) / a.length;
        return { x: ((avg(cols) + 0.5) / COLS) * 100, y: ((avg(rows) + 0.5) / ROWS) * 100 };
    }

    function floatText(text, cells, cls = '') {
        const { x, y } = centre(cells);
        const el = document.createElement('div');
        el.className = `float ${cls}`;
        el.textContent = text;
        el.style.left = `${x}%`;
        el.style.top = `${y}%`;
        fx.append(el);
        animate(el, [
            { transform: 'translate(-50%,-50%) scale(.6)', opacity: 0 },
            { transform: 'translate(-50%,-60%) scale(1.1)', opacity: 1, offset: 0.2 },
            { transform: 'translate(-50%,-90%) scale(1)', opacity: 1, offset: 0.75 },
            { transform: 'translate(-50%,-120%) scale(1)', opacity: 0 },
        ], 1300).then(() => el.remove());
    }

    // ---- Effects: the particle canvas around the grid ----------------------------------------
    // One canvas reaches past the frame, so a shattered symbol's pieces fly over it. The art is
    // built by scripts/art/process-sugar-orchard.py:
    // - pieces are cut-outs, drawn normally;
    // - frost, crystals, twinkles and comets are light on transparent, drawn 'lighter' so they
    //   add up where they overlap.
    // Particle time runs at the game's speed (turbo), like every other animation.

    const canvas = $('fxc');
    const ctx = canvas.getContext('2d');
    const reducedMotion = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false;
    const images = new Map();
    const particles = [];
    let frameId = 0;
    let lastTick = 0;

    function sprite(url) {
        if (!url) return null;
        let im = images.get(url);
        if (!im) {
            im = new Image();
            im.decoding = 'async';
            im.src = url;
            images.set(url, im);
        }
        return im;
    }

    function fitCanvas() {
        const r = canvas.getBoundingClientRect();
        const dpr = Math.min(2, window.devicePixelRatio || 1);
        canvas.width = Math.max(1, Math.round(r.width * dpr));
        canvas.height = Math.max(1, Math.round(r.height * dpr));
    }

    /** A point of the grid (0..1 across, 0..1 down) in canvas pixels, with a symbol's size (cells may be wider than tall). */
    function gridPoint(fx, fy) {
        const b = board.getBoundingClientRect();
        const c = canvas.getBoundingClientRect();
        const k = canvas.width / (c.width || 1);
        return { x: (b.left - c.left + fx * b.width) * k, y: (b.top - c.top + fy * b.height) * k, size: Math.min(b.width / COLS, b.height / ROWS) * k };
    }

    const cellBox = (cell) => gridPoint((Math.floor(cell / ROWS) + 0.5) / COLS, ((cell % ROWS) + 0.5) / ROWS);

    /**
     * Adds a particle: img at (x, y), w × h px, moving (vx, vy) px/s under gravity g, turning
     * vr rad/s, scaling s0 → s1 (eased) and fading a0 → a1 from `fade` of its life, for `life` s.
     */
    function emit(p) {
        if (!p.img) return;
        particles.push({ vx: 0, vy: 0, g: 0, rot: 0, vr: 0, s0: 1, s1: 1, a0: 1, a1: 0, fade: 0, delay: 0, age: 0, light: false, ease: easeOut, ...p });
        if (!frameId) {
            lastTick = performance.now();
            frameId = requestAnimationFrame(tick);
        }
    }

    function tick(now) {
        const dt = Math.min(0.05, (now - lastTick) / 1000) / speed();
        lastTick = now;
        ctx.setTransform(1, 0, 0, 1, 0, 0);
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        for (let i = particles.length - 1; i >= 0; i--) {
            const p = particles[i];
            if (p.delay > 0) {
                p.delay -= dt;
                continue;
            }
            p.age += dt;
            const t = p.age / p.life;
            if (t >= 1) {
                particles.splice(i, 1);
                continue;
            }
            p.vy += p.g * dt;
            p.x += p.vx * dt;
            p.y += p.vy * dt;
            p.rot += p.vr * dt;
            if (!p.img.complete || !p.img.naturalWidth) continue;
            const s = p.s0 + (p.s1 - p.s0) * p.ease(t);
            const f = t <= p.fade ? 0 : (t - p.fade) / (1 - p.fade);
            ctx.globalAlpha = Math.max(0, Math.min(1, p.a0 + (p.a1 - p.a0) * f));
            ctx.globalCompositeOperation = p.light ? 'lighter' : 'source-over';
            const cos = Math.cos(p.rot) * s;
            const sin = Math.sin(p.rot) * s;
            ctx.setTransform(cos, sin, -sin, cos, p.x, p.y);
            ctx.drawImage(p.img, -p.w / 2, -p.h / 2, p.w, p.h);
        }
        ctx.setTransform(1, 0, 0, 1, 0, 0);
        ctx.globalAlpha = 1;
        ctx.globalCompositeOperation = 'source-over';
        frameId = particles.length ? requestAnimationFrame(tick) : 0;
    }

    /** Sugar frost creeps over a winning symbol before it bursts. */
    function frostOver(el) {
        if (!theme.fx?.frostCrust || state.quick) return;
        const img = document.createElement('img');
        img.className = 'frost';
        img.src = theme.fx.frostCrust;
        img.alt = '';
        el.append(img);
        animate(img, [{ opacity: 0, transform: 'scale(1.2)' }, { opacity: 1, transform: 'scale(1)' }], 170);
    }

    /** A winning symbol bursts: a frost flash and shockwave, its own pieces flying, crystals, a twinkle. */
    function shatter(cell, symbol) {
        if (state.quick || reducedMotion) return;
        const art = theme.fx ?? {};
        const { x, y, size } = cellBox(cell);
        emit({ img: sprite(art.frostBurst), x, y, w: size * 1.75, h: size * 1.75, s0: 0.3, s1: 1.15, fade: 0.3, life: 0.55, light: true, vr: rand(-1, 1) });
        emit({ img: sprite(art.frostRing), x, y, w: size * 1.5, h: size * 1.5, s0: 0.2, s1: 1.4, a0: 0.95, fade: 0.2, life: 0.6, light: true });
        for (const piece of theme.fragments?.[symbol] ?? []) {
            // Each piece flies outward from where it sits in the burst, up first, then falls.
            const dist = Math.hypot(piece.x, piece.y);
            const dir = (dist > 0.02 ? Math.atan2(piece.y, piece.x) : rand(0, Math.PI * 2)) + rand(-0.35, 0.35);
            const small = piece.w * piece.h < 0.006;
            const v = size * rand(2.2, 3.4) * (small ? 1.35 : 1) * (0.75 + dist);
            emit({
                img: sprite(piece.src), x: x + piece.x * size * 0.6, y: y + piece.y * size * 0.6, w: piece.w * size, h: piece.h * size,
                vx: Math.cos(dir) * v, vy: Math.sin(dir) * v - size * rand(1.4, 2.6), g: size * 10,
                rot: rand(-0.3, 0.3), vr: rand(-7, 7), s1: 0.8, fade: 0.55, life: rand(0.8, 1.1), ease: linear,
            });
        }
        const crystals = art.crystals ?? [];
        for (let i = 0; i < 7 && crystals.length; i++) {
            const dir = rand(0, Math.PI * 2);
            const v = size * rand(2.5, 4.5);
            emit({
                img: sprite(crystals[i % crystals.length]), x, y, w: size * 0.22, h: size * 0.22, vx: Math.cos(dir) * v, vy: Math.sin(dir) * v,
                g: size * 4, vr: rand(-6, 6), s1: 0.4, fade: 0.4, life: rand(0.45, 0.7), light: true, delay: rand(0, 0.06),
            });
        }
        emit({
            img: sprite(art.twinkle), x: x + rand(-0.25, 0.25) * size, y: y + rand(-0.25, 0.25) * size, w: size, h: size,
            s0: 0.2, s1: 1, fade: 0.4, life: 0.5, light: true, vr: 1.5, delay: 0.05,
        });
    }

    async function playStep(step, index) {
        const winners = step.removed;
        winners.forEach((c) => state.els[c].classList.add('win'));
        const amount = winAmount(state.bet, step.pays);
        floatText(shown(amount), winners);
        sfx('win', index);
        hostReact('win');
        await wait(650);

        // Frost creeps over the winners, then they burst into pieces.
        winners.forEach((c) => frostOver(state.els[c]));
        await wait(180);
        sfx('pop');
        winners.forEach((c) => shatter(c, state.cells[c].s));
        await Promise.all(winners.map((c) => animate(state.els[c], [
            { transform: at(c), opacity: 1 },
            { transform: `${at(c)} scale(1.15)`, opacity: 0 },
        ], 130)));
        winners.forEach((c) => state.els[c].remove());
        await wait(170);

        const next = new Array(COLS * ROWS);
        const falls = [];
        const moved = new Set(step.moves.map((m) => m.from));
        state.els.forEach((el, c) => {
            if (!winners.includes(c) && !moved.has(c)) next[c] = el;
        });
        for (const m of step.moves) {
            const el = state.els[m.from];
            next[m.to] = el;
            const rows = (m.to % ROWS) - (m.from % ROWS);
            falls.push(animate(el, [{ transform: at(m.from) }, { transform: at(m.to) }], 220 + rows * 45, { easing: 'cubic-bezier(.5,0,.75,1.2)' }));
        }
        for (const a of step.added) {
            const el = symbolEl({ s: a.s, v: a.v });
            const startRow = a.from - 1;
            el.style.transform = at(a.to, startRow - (a.to % ROWS));
            board.append(el);
            next[a.to] = el;
            const rows = (a.to % ROWS) - startRow;
            falls.push(animate(el, [{ transform: at(a.to, startRow - (a.to % ROWS)) }, { transform: at(a.to) }], 240 + rows * 45,
                { delay: 60, easing: 'cubic-bezier(.5,0,.75,1.2)' }));
        }
        await Promise.all(falls);
        next.forEach((el, c) => settle(el, c));
        setTimeout(() => sfx('land', 2), 0);
        if (step.added.some((a) => a.s === MULTIPLIER)) sfx('star');
        state.els = next;
        state.cells = step.cells;
    }

    async function applyMultipliers(spin) {
        const cells = spin.multiplierCells;
        cells.forEach((c) => state.els[c]?.classList.add('win'));
        sfx('stars');
        await wait(350);
        // Each star sends a comet to the middle of the grid, where their sum appears.
        if (!state.quick && !reducedMotion) {
            const target = gridPoint(0.5, 0.5);
            cells.forEach((c, i) => {
                const from = cellBox(c);
                const dx = target.x - from.x;
                const dy = target.y - from.y;
                emit({
                    img: sprite(theme.fx?.comet), x: from.x, y: from.y, w: from.size * 1.9, h: from.size * 0.95, vx: dx / 0.45, vy: dy / 0.45,
                    rot: Math.atan2(dy, dx), s0: 0.7, a1: 0.2, fade: 0.7, life: 0.45, light: true, ease: linear, delay: i * 0.08,
                });
            });
            await wait(450 + cells.length * 80);
            emit({ img: sprite(theme.fx?.frostRing), x: target.x, y: target.y, w: target.size * 2.6, h: target.size * 2.6, s0: 0.2, s1: 1.3, fade: 0.2, life: 0.6, light: true });
            for (let i = 0; i < 3; i++) {
                emit({
                    img: sprite(theme.fx?.twinkle), x: target.x + rand(-0.8, 0.8) * target.size, y: target.y + rand(-0.5, 0.5) * target.size,
                    w: target.size * 1.3, h: target.size * 1.3, s0: 0.2, s1: 1.1, fade: 0.4, life: 0.6, light: true, delay: i * 0.07,
                });
            }
        }
        floatText(`×${spin.multiplier}`, [0, COLS * ROWS - 1], 'mult');
        await wait(900);
        cells.forEach((c) => state.els[c]?.classList.remove('win'));
    }

    /** Scatters that trigger or add free spins pulse in golden rays. */
    async function highlightScatters(cells) {
        const rays = cells.map((c) => {
            const { x, y } = centre([c]);
            const img = document.createElement('img');
            img.className = 'rays-cell';
            img.src = theme.fx?.rays ?? '';
            img.alt = '';
            img.style.left = `${x}%`;
            img.style.top = `${y}%`;
            board.append(img);
            animate(img, [
                { opacity: 0, rotate: '0deg', scale: 0.5 },
                { opacity: 1, rotate: '50deg', scale: 1, offset: 0.3 },
                { opacity: 0, rotate: '150deg', scale: 1.1 },
            ], 1400, { easing: 'linear' });
            return img;
        });
        cells.forEach((c) => state.els[c]?.classList.add('win', 'scatter'));
        await wait(1200);
        cells.forEach((c) => state.els[c]?.classList.remove('win', 'scatter'));
        setTimeout(() => rays.forEach((r) => r.remove()), dur(300));
    }

    // ---- A bet, played back ----------------------------------------------------------------------

    let betWin = 0n;

    /** The win meter: counts up to a new win and lights up; its perched fruit cheer and some burst out. */
    function showWin(units) {
        const before = betWin;
        betWin = units;
        const el = $('win');
        const meter = $('win-meter');
        if (units <= 0n) {
            cancelAnimationFrame(rollTo.raf);
            el.textContent = '0.00';
            meter.classList.remove('lit', 'cheer');
            return;
        }
        meter.classList.add('lit');
        if (units > before && !state.quick) {
            rollTo(el, before, units);
            cheer(meter);
        } else {
            cancelAnimationFrame(rollTo.raf);
            el.textContent = shown(units);
        }
    }

    function rollTo(el, from, to) {
        const a = Number(from) / 1e8;
        const b = Number(to) / 1e8;
        const started = performance.now();
        const length = dur(520);
        cancelAnimationFrame(rollTo.raf);
        const step = (now) => {
            const f = Math.min(1, (now - started) / length);
            el.textContent = f >= 1 ? shown(to) : shown((a + (b - a) * easeOut(f)).toFixed(2));
            if (f < 1) rollTo.raf = requestAnimationFrame(step);
        };
        rollTo.raf = requestAnimationFrame(step);
        animate(el, [{ transform: 'scale(1)' }, { transform: 'scale(1.22)', offset: 0.35 }, { transform: 'scale(1)' }], 520);
    }

    function cheer(meter) {
        meter.classList.remove('cheer');
        void meter.offsetWidth; // restart the animation
        meter.classList.add('cheer');
        clearTimeout(cheer.timer);
        cheer.timer = setTimeout(() => meter.classList.remove('cheer'), 1800);
        if (reducedMotion) return;
        const r = meter.getBoundingClientRect();
        const pool = ['E', 'F', 'G', 'H', 'I', 'A', 'B'].map((s) => theme.symbols[s]?.img).filter(Boolean);
        for (let i = 0; i < 7 && pool.length; i++) {
            const img = document.createElement('img');
            img.className = 'pop-fruit';
            img.alt = '';
            img.src = pool[i % pool.length];
            img.style.left = `${r.left + r.width / 2}px`;
            img.style.top = `${r.top + r.height * 0.4}px`;
            document.body.append(img);
            const angle = -Math.PI / 2 + (i - 3) * 0.32 + rand(-0.12, 0.12);
            const dist = rand(95, 150);
            const dx = Math.cos(angle) * dist;
            const dy = Math.sin(angle) * dist;
            animate(img, [
                { transform: 'translate(0, 0) scale(.3) rotate(0deg)', opacity: 0 },
                { transform: `translate(${dx * 0.6}px, ${dy * 0.7}px) scale(1) rotate(${rand(-60, 60)}deg)`, opacity: 1, offset: 0.35 },
                { transform: `translate(${dx}px, ${dy + 60}px) scale(.7) rotate(${rand(-160, 160)}deg)`, opacity: 0 },
            ], rand(800, 1050), { easing: 'cubic-bezier(.2,.7,.4,1)' }).then(() => img.remove());
        }
    }

    async function playSpin(spin, alreadyCleared) {
        if (!alreadyCleared) await dropOut();
        await dropIn(spin.start);
        await wait(spin.steps.length ? 120 : 60);
        let line = 0;
        for (const [i, step] of spin.steps.entries()) {
            await playStep(step, i);
            line += step.pays;
            if (!spin.free) showWin(winAmount(state.bet, line));
        }
        if (spin.free && spin.multiplier > 0 && spin.lineWin > 0) await applyMultipliers(spin);
        if (spin.scatterWin > 0) floatText(shown(winAmount(state.bet, spin.scatterWin)), spin.scatters, 'scatter-win');
        const triggers = spin.free ? spin.retrigger > 0 : spin.awarded > 0;
        if (triggers) {
            sfx(spin.free ? 'retrigger' : 'scatter', spin.scatters.length);
            await highlightScatters(spin.scatters);
        }
        showWin(winAmount(state.bet, spin.runningTotal));
    }

    async function playOutcome(p, cleared) {
        const [base, ...free] = p.spins;
        await playSpin(base, cleared);

        if (base.awarded > 0) {
            if (state.auto > 0 && state.autoStopOnFeature) stopAuto();
            sfx('fsStart');
            hostReact('free');
            await dialog(plaque('free-spins', `<div class="huge">${base.awarded}</div>
                <p class="note">Sugar stars land in free spins and multiply every winning spin.</p>`), {
                button: 'Start', cls: 'plaque', rays: true, autoMs: state.auto > 0 || state.turbo ? 2500 : 0,
            });
            document.body.classList.add('free');
            let awarded = base.awarded;
            let fsWin = 0n;
            hud(0, awarded, 0n);
            for (const [n, spin] of free.entries()) {
                hud(n + 1, awarded, fsWin);
                state.quick = false;
                await playSpin(spin, false);
                fsWin += winAmount(state.bet, spin.win);
                hud(n + 1, awarded, fsWin);
                if (spin.retrigger > 0) {
                    awarded += spin.retrigger;
                    hud(n + 1, awarded, fsWin);
                    await dialog(plaque('free-spins', `<div class="huge">+${spin.retrigger}</div>`), { cls: 'plaque', rays: true, autoMs: 1800 });
                }
            }
            sfx('fsEnd');
            await dialog(plaque('total-win', `<div class="huge">${shown(fsWin)}</div><p class="note">${free.length} free spins</p>`),
                { button: 'Collect', cls: 'plaque', rays: true, autoMs: state.auto > 0 || state.turbo ? 2500 : 0 });
            document.body.classList.remove('free');
            $('fs-hud').hidden = true;
        }

        // Celebrated against what the player paid: a bought feature returning 94× its 100× price is no big win.
        const tier = p.maxWin ? 'max' : winTier(Math.floor((p.total * 100) / Number(COST[p.mode] ?? 100n)));
        if (tier !== 'none') await bigWin(tier, winAmount(state.bet, p.total));
    }

    function hud(n, of, win) {
        $('fs-hud').hidden = false;
        $('fs-count').textContent = `${Math.min(n, of)} / ${of}`;
        $("fs-win").textContent = shown(win);
    }

    /** A dialog on the chocolate plaque, under a banner (theme.banners). */
    function plaque(banner, inner) {
        const src = theme.banners?.[banner];
        return `${src ? `<img class="banner" src="${src}" alt="">` : ''}<div class="body">${inner}</div>`;
    }

    async function bigWin(tier, units) {
        sfx('big');
        hostReact('big');
        const banner = theme.banners?.[`${tier}-win`];
        await dialog(`${banner ? `<img class="banner" src="${banner}" alt="${TIERS[tier]}">` : `<p class="kicker">${TIERS[tier]}</p>`}<div class="huge count">0.00</div>`, {
            autoMs: 4200,
            cls: 'celebrate',
            rays: true,
            dim: true,
            onOpen(el, layer) {
                const counter = el.querySelector('.count');
                const total = Number(units) / 1e8;
                const started = performance.now();
                const length = dur(2600);
                const tick = (now) => {
                    const f = Math.min(1, (now - started) / length);
                    counter.textContent = shown(f >= 1 ? units : (total * (1 - Math.pow(1 - f, 3))).toFixed(2));
                    if (f < 1 && el.isConnected) requestAnimationFrame(tick);
                };
                requestAnimationFrame(tick);
                confetti();
                fireworks(layer, { big: 4, mega: 6, epic: 8, max: 10 }[tier] ?? 4);
            },
        });
    }

    /** Candy fireworks bursting behind the celebration. */
    function fireworks(layer, count) {
        if (reducedMotion || !theme.fx?.fireworks) return;
        for (let i = 0; i < count; i++) {
            setTimeout(() => {
                if (layer.hidden) return;
                const img = document.createElement('img');
                img.className = 'firework';
                img.src = theme.fx.fireworks;
                img.alt = '';
                img.style.left = `${rand(12, 88)}%`;
                img.style.top = `${rand(12, 68)}%`;
                img.style.width = `${rand(26, 46)}vmin`;
                layer.prepend(img); // behind the dialog
                animate(img, [
                    { opacity: 0, scale: 0.25, rotate: `${rand(-30, 30)}deg` },
                    { opacity: 1, scale: 1, offset: 0.35 },
                    { opacity: 0, scale: 1.15 },
                ], 1500).then(() => img.remove());
            }, dur(i * 420 + rand(0, 200)));
        }
    }

    /** Sprinkles, candy and the odd symbol raining down. */
    function confetti() {
        if (reducedMotion) return;
        const sprinkles = theme.fx?.sprinkles ?? [];
        const candies = Object.values(theme.symbols).map((s) => s.img).filter(Boolean);
        for (let i = 0; i < 46; i++) {
            const candy = i % 6 === 0 || !sprinkles.length;
            const img = document.createElement('img');
            img.className = 'confetti';
            img.src = candy ? candies[i % candies.length] : sprinkles[i % sprinkles.length];
            img.style.left = `${Math.random() * 100}%`;
            img.style.width = candy ? 'clamp(34px, 5vw, 58px)' : `clamp(12px, ${rand(1.2, 2.4).toFixed(2)}vw, 30px)`;
            document.body.append(img);
            animate(img, [
                { transform: `translateY(-15vh) rotate(0deg)`, opacity: 1 },
                { transform: `translateY(115vh) rotate(${Math.random() * 720 - 360}deg)`, opacity: 1 },
            ], 2200 + Math.random() * 1600, { delay: Math.random() * 900, easing: 'cubic-bezier(.3,.1,.7,1)' }).then(() => img.remove());
        }
    }

    // ---- Dialogs ------------------------------------------------------------------------------

    /**
     * Shows a dialog. Resolves with the pressed button's value ("1" for the main button, "0"
     * for cancel, or a choice's own value), or "1" when it closes itself (autoMs, a tap on a
     * dialog without buttons).
     */
    function dialog(html, { button = null, cancel = null, autoMs = 0, onOpen = null, cls = '', rays = false, dim = false } = {}) {
        return new Promise((resolve) => {
            const layer = $('overlay');
            const actions = button || cancel
                ? `<div class="actions">${cancel ? `<button class="ghost" data-v="0">${cancel}</button>` : ''}${button ? `<button class="primary" data-v="1">${button}</button>` : ''}</div>`
                : '';
            // Golden rays turn behind a feature or celebration dialog.
            layer.innerHTML = `${rays && theme.fx?.rays ? `<img class="rays" src="${theme.fx.rays}" alt="">` : ''}
                <div class="dialog ${cls}" role="dialog" aria-modal="true">${cls === 'plaque' ? html.replace(/<\/div>$/, `${actions}</div>`) : `${html}${actions}`}</div>`;
            layer.classList.toggle('dim', dim);
            layer.hidden = false;
            let done = false;
            const close = (value) => {
                if (done) return;
                done = true;
                layer.hidden = true;
                layer.innerHTML = '';
                layer.onclick = null;
                resolve(value);
            };
            layer.onclick = (e) => {
                const b = e.target.closest('button[data-v]');
                if (b) close(b.dataset.v);
                else if (!button && !cancel) close('1');
            };
            onOpen?.(layer.querySelector('.dialog'), layer);
            if (autoMs) setTimeout(() => close('1'), dur(autoMs));
            layer.querySelector('button.primary')?.focus({ preventScroll: true });
        });
    }

    function toast(text) {
        const t = $('toast');
        t.textContent = text;
        t.hidden = false;
        clearTimeout(toast.timer);
        toast.timer = setTimeout(() => { t.hidden = true; }, 3500);
    }

    // ---- Betting --------------------------------------------------------------------------------

    let pending = null;

    function placeBet(amount, mode) {
        return new Promise((resolve) => {
            const timer = setTimeout(() => {
                pending = null;
                resolve({ success: false, error: 'timeout' });
            }, 30_000);
            pending = (payload) => {
                clearTimeout(timer);
                pending = null;
                resolve(payload);
            };
            if (!bridge.send('place_bet', { amount, params: { mode } })) {
                clearTimeout(timer);
                pending = null;
                resolve({ success: false, error: 'not_connected' });
            }
        });
    }

    async function spin(mode = state.ante ? 'ante' : 'base') {
        if (state.busy) {
            state.quick = true;
            return;
        }
        const amount = stake(state.bet, mode);
        if (state.balance !== null && toUnits(state.balance) < toUnits(amount)) {
            toast('Not enough balance for this bet.');
            stopAuto();
            return;
        }
        if (!affordable(state.bet, mode, state.limits)) {
            toast('This bet is over the maximum stake.');
            stopAuto();
            return;
        }

        state.busy = true;
        state.quick = false;
        hostSpin();
        audio();
        controls();
        showWin(0n);
        if (state.balance !== null) $("balance").textContent = shown(toUnits(state.balance) - toUnits(amount));

        try {
            const clearing = dropOut();
            const result = await placeBet(amount, mode);
            await clearing;

            if (!result.success || !result.outcome) {
                await dropIn(state.cells);
                toast(errorText(result.error));
                stopAuto();
            } else {
                state.result = result;
                state.fairness = result.fairness ?? null;
                const payout = toUnits(result.payout_amount ?? '0') ?? 0n;
                if (result.player_balance) {
                    state.balance = result.player_balance;
                } else if (state.balance !== null) {
                    // A relay operator that doesn't report the balance: work it out.
                    state.balance = money(toUnits(state.balance) - toUnits(amount) + payout);
                }
                try {
                    await playOutcome(plan(result.outcome), true);
                } catch (e) {
                    // Never leave the game stuck mid-animation: the bet is settled either way.
                    console.error(e);
                    $('overlay').hidden = true;
                    document.body.classList.remove('free');
                    $('fs-hud').hidden = true;
                    render(state.cells);
                }
                showWin(payout);
            }
        } catch (e) {
            console.error(e);
            toast(errorText('unknown'));
            stopAuto();
        }

        state.busy = false;
        state.quick = false;
        balance();
        controls();
        hostRest();
        if (state.auto > 0) {
            state.auto -= 1;
            controls();
            if (state.auto > 0) setTimeout(() => spin(), dur(350));
        }
    }

    function errorText(code) {
        return ({
            insufficient_funds: 'Not enough balance for this bet.',
            timeout: 'The casino did not answer. Your balance is unchanged unless the bet appears in your history.',
            not_connected: 'Not connected to the casino yet.',
            unknown: 'Something went wrong. Please check your balance and bet history.',
        })[code] ?? `The bet was not placed (${code || 'unknown error'}).`;
    }

    function stopAuto() {
        state.auto = 0;
        controls();
    }

    // ---- The bar ----------------------------------------------------------------------------------

    function balance() {
        $("balance").textContent = state.balance === null ? "—" : shown(state.balance);
        document.querySelectorAll('[data-currency]').forEach((e) => { e.textContent = state.currency; });
    }

    function controls() {
        const connected = bridge.parentOrigin !== null;
        const i = state.steps.indexOf(state.bet);
        const mode = state.ante ? 'ante' : 'base';
        $("bet").textContent = shown(stake(state.bet, mode));
        $('bet-minus').disabled = state.busy || i <= 0;
        $('bet-plus').disabled = state.busy || i >= state.steps.length - 1;
        $('spin-btn').disabled = !connected || (state.busy && state.quick);
        $('spin-btn').classList.toggle('busy', state.busy);
        $('spin-btn').setAttribute('aria-label', state.busy ? 'Skip' : 'Spin');
        $('auto-count').textContent = state.auto > 0 ? String(state.auto) : '';
        $('auto-btn').setAttribute('aria-pressed', String(state.auto > 0));
        $('auto-btn').disabled = !connected || cfg.config.autoplay === false || (state.busy && state.auto === 0);
        $('turbo-btn').setAttribute('aria-pressed', String(state.turbo));
        $('ante-btn').setAttribute('aria-pressed', String(state.ante));
        $('ante-btn').disabled = state.busy;
        $('ante-box').hidden = !features.ante;
        const buyCost = stake(state.bet, 'buy');
        $('buy-cost').textContent = shown(buyCost);
        $('buy-box').hidden = !features.buy;
        $('buy-btn').disabled = !connected || state.busy || state.ante || !affordable(state.bet, 'buy', state.limits);
        // Say why the feature can't be bought (not while a spin is playing: that passes).
        const reason = state.ante ? 'Switch the ante bet off to buy.'
            : (!affordable(state.bet, 'buy', state.limits) ? 'Above your maximum stake: lower the bet to buy.' : '');
        $('buy-btn').title = reason;
        $('buy-note').textContent = reason;
        $('buy-note').hidden = !reason;
    }

    function stepBet(dir) {
        const i = state.steps.indexOf(state.bet);
        const j = Math.max(0, Math.min(state.steps.length - 1, i + dir));
        state.bet = state.steps[j];
        sfx('click');
        controls();
    }

    async function buy() {
        if (state.busy) return;
        const cost = stake(state.bet, 'buy');
        const ok = await dialog(plaque('free-spins', `<div class="huge">10</div>
            <p class="note">for <strong>${shown(cost)} ${state.currency}</strong> (100× your bet of ${shown(state.bet)})</p>`), { button: 'Buy', cancel: 'Cancel', cls: 'plaque' });
        if (ok === '1') spin('buy');
    }

    async function autoplay() {
        if (state.auto > 0) {
            stopAuto();
            return;
        }
        let stopOnFeature = state.autoStopOnFeature;
        const pick = await dialog(`<p class="kicker">Autoplay</p><div class="choices">${[10, 25, 50, 100].map((n) => `<button class="choice" data-v="${n}">${n}</button>`).join('')}</div>
            <label class="check"><input type="checkbox" id="auto-stop" ${stopOnFeature ? 'checked' : ''}> Stop when free spins start</label>`, {
            cancel: 'Cancel',
            onOpen(el) {
                el.querySelector('#auto-stop').addEventListener('change', (e) => { stopOnFeature = e.target.checked; });
            },
        });
        const spins = Number(pick);
        if (spins > 1) {
            state.autoStopOnFeature = stopOnFeature;
            state.auto = spins;
            controls();
            spin();
        }
    }

    function rules() {
        const r = cfg.rules;
        const bet = toUnits(state.bet);
        const amount = (x) => shown((bet * BigInt(Math.round(Number(x) * 100))) / 100n);
        const rows = (r.paytable ?? []).map((p) => `<tr><td><img src="${theme.symbols[p.symbol]?.img}" alt="${p.name}"></td>
            ${Object.values(p.pays).map((x) => `<td>${amount(x)}</td>`).join('')}</tr>`).join('');
        const tiers = Object.keys(r.paytable?.[0]?.pays ?? {});
        const scatter = Object.entries(r.scatter_pays ?? {}).map(([n, x]) => `${n}× → ${amount(x)}`).join(' · ');
        const f = state.fairness;
        dialog(`<h2>Paytable</h2><p class="note">Pays at your bet of ${shown(state.bet)} ${state.currency}. Symbols pay anywhere.</p>
            <table class="pays"><thead><tr><th></th>${tiers.map((t) => `<th>${t}</th>`).join('')}</tr></thead><tbody>${rows}</tbody></table>
            <p><img class="inline" src="${theme.symbols.S?.img}" alt=""> <strong>Scatter</strong>: ${scatter}. 4 or more anywhere award ${r.free_spins?.awarded} free spins; ${r.free_spins?.retrigger_scatters} or more in free spins add ${r.free_spins?.retrigger_awarded}.</p>
            <h3>How it plays</h3>
            <ul><li>8 or more of a symbol anywhere on the ${r.grid?.columns}×${r.grid?.rows} grid win.</li>
            <li>Winning symbols burst, the rest fall, and new ones drop in; this repeats while there are wins.</li>
            <li>In free spins, sugar stars (2× to 100×) land on the grid. When a free spin's tumbles end with a win, the stars' values add up and multiply it.</li>
            ${features.ante ? '<li>Ante bet: your stake is 1.25× the bet and free spins come about 1.75× as often.</li>' : ''}
            ${features.buy ? '<li>Buy free spins: 100× the bet starts the feature at once.</li>' : ''}
            <li>Maximum win: ${Number(r.max_win).toLocaleString()}× the bet; the round ends when it is reached.</li>
            <li>Return to player: ${(Number(r.rtp) * 100).toFixed(2)}%.</li>
            ${state.limits?.max_win ? `<li>Your casino pays at most ${shown(state.limits.max_win)} ${state.currency} per bet.</li>` : ''}</ul>
            <h3>Provably fair</h3>
            <p class="note">Every spin, tumble and free spin is drawn from your seed pair before anything moves. Rotate your seeds to reveal the server seed and verify any bet at ${cfg.verifyUrl}.</p>
            ${f ? `<p class="mono">Last bet: server seed hash ${f.server_seed_hash} · client seed ${f.client_seed} · nonce ${f.nonce}</p>` : ''}`,
        { button: 'Close', cls: 'wide' });
    }

    // ---- Game history -----------------------------------------------------------------------------
    // The casino page answers bet_history (Orange's host page does it from the player API). It
    // offers it in player_info, which shows the History button.

    let historyPending = null;

    function fetchHistory() {
        return new Promise((resolve) => {
            const timer = setTimeout(() => {
                historyPending = null;
                resolve({ success: false, error: 'timeout' });
            }, 10_000);
            historyPending = (payload) => {
                clearTimeout(timer);
                historyPending = null;
                resolve(payload);
            };
            if (!bridge.send('bet_history', {})) {
                clearTimeout(timer);
                historyPending = null;
                resolve({ success: false, error: 'not_connected' });
            }
        });
    }

    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);

    function historyRow(b) {
        const d = b.details ?? {};
        const payout = toUnits(b.payout_amount ?? '0') ?? 0n;
        const won = payout > 0n;
        const when = new Date(b.placed_at);
        const today = !Number.isNaN(when.getTime()) && when.toDateString() === new Date().toDateString();
        const time = Number.isNaN(when.getTime()) ? '' : when.toLocaleString([], today
            ? { hour: '2-digit', minute: '2-digit', second: '2-digit' }
            : { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' });
        const tags = [
            d.mode === 'ante' ? '<span class="tag">Ante</span>' : '',
            d.mode === 'buy' ? '<span class="tag">Bought</span>' : '',
            d.free_spins > 0 ? `<span class="tag fs">${Number(d.free_spins)} free spins</span>` : '',
            d.max_win ? '<span class="tag max">Max win</span>' : '',
            b.status === 'refunded' ? '<span class="tag">Refunded</span>' : '',
        ].join('');
        const times = d.win_x ?? b.multiplier;
        const f = b.fairness;
        return `<details class="hrow${won ? ' won' : ''}"><summary>
                <span class="h-time">${esc(time)}</span>
                <span class="h-bet">${shown(b.amount)} <small>${esc(b.currency)}</small>${tags}</span>
                <span class="h-win">${won ? shown(b.payout_amount) : '—'}${won && times ? `<small>×${esc(times)}</small>` : ''}</span>
            </summary>
            <dl>
                <dt>Bet ID</dt><dd>${esc(b.bet_id)}</dd>
                ${d.bet ? `<dt>Bet</dt><dd>${shown(d.bet)} ${esc(b.currency)}${d.mode && d.mode !== 'base' ? ` (${d.mode === 'buy' ? 'bought free spins' : 'ante'}: stake ${shown(b.amount)})` : ''}</dd>` : ''}
                <dt>Win</dt><dd>${won ? `${shown(b.payout_amount)} ${esc(b.currency)}` : 'No win'}</dd>
                ${f ? `<dt>Nonce</dt><dd>${esc(f.nonce)}</dd><dt>Client seed</dt><dd class="mono">${esc(f.client_seed)}</dd><dt>Server seed hash</dt><dd class="mono">${esc(f.server_seed_hash)}</dd>` : ''}
            </dl></details>`;
    }

    async function history() {
        sfx('click');
        dialog(`<h2>Game history</h2>
            <div class="hist-head"><span>Time</span><span>Bet</span><span>Win</span><span></span></div>
            <div id="hist" class="hist"><p class="note">Loading your bets…</p></div>
            <p class="hist-note">Your latest bets, newest first. Open a bet for its seeds: rotate your seeds to reveal the server seed and verify it at ${esc(cfg.verifyUrl)}.</p>`,
        { button: 'Close', cls: 'wide history' });
        const r = await fetchHistory();
        const box = document.getElementById('hist');
        if (!box) return; // closed meanwhile
        if (!r.success) {
            box.innerHTML = '<p class="note">Your history is not available right now. Please try again in a moment.</p>';
        } else if (!r.bets?.length) {
            box.innerHTML = '<p class="note">No bets on this game yet.</p>';
        } else {
            box.innerHTML = r.bets.map(historyRow).join('');
        }
    }

    // ---- Wiring ---------------------------------------------------------------------------------

    const bridge = createGameBridge({
        allowedOrigins: cfg.allowedOrigins,
        onMessage(type, payload) {
            if (type === 'player_info') {
                state.currency = payload.currency || '';
                state.balance = payload.balance || null;
                state.limits = payload.limits ?? null;
                state.steps = playableSteps(cfg.config.bet_steps ?? ['1.00'], state.limits);
                if (!state.steps.includes(state.bet)) state.bet = state.steps[0];
                $('history-btn').hidden = payload.bet_history !== true;
                $('home-btn').hidden = payload.home !== true;
                $('status').hidden = true;
                balance();
                controls();
            } else if (type === 'balance_update') {
                state.balance = payload.balance ?? state.balance;
                if (!state.busy) balance();
            } else if (type === 'bet_placed_result') {
                pending?.(payload);
            } else if (type === 'bet_history_result') {
                historyPending?.(payload);
            }
        },
    });

    $('spin-btn').addEventListener('click', () => spin());
    $('bet-minus').addEventListener('click', () => stepBet(-1));
    $('bet-plus').addEventListener('click', () => stepBet(1));
    $('ante-btn').addEventListener('click', () => {
        state.ante = !state.ante;
        sfx('click');
        controls();
    });
    $('buy-btn').addEventListener('click', buy);
    $('auto-btn').addEventListener('click', autoplay);
    $('turbo-btn').addEventListener('click', () => {
        state.turbo = !state.turbo;
        writePref('turbo', state.turbo);
        controls();
    });
    $('sound-btn').addEventListener('click', () => setSound(!sound.on));
    $('info-btn').addEventListener('click', rules);
    $('history-btn').addEventListener('click', history);
    $('home-btn').addEventListener('click', () => bridge.send('back_to_lobby'));
    board.addEventListener('click', () => { if (state.busy) state.quick = true; });
    document.addEventListener('keydown', (e) => {
        if (e.code === 'Space' && $('overlay').hidden && !$('intro').open && !e.repeat && e.target === document.body) {
            e.preventDefault();
            spin();
        }
    });

    // ---- The orchard around the grid: fireflies at night, petals drifting by day -----------------

    function scenery() {
        const flies = $('fireflies');
        for (let i = 0; i < 16; i++) {
            const f = document.createElement('i');
            f.style.left = `${rand(2, 98)}%`;
            f.style.top = `${rand(15, 95)}%`;
            f.style.setProperty('--o', rand(0.45, 0.95).toFixed(2));
            f.style.animationDelay = `${-rand(0, 9).toFixed(1)}s`;
            f.style.animationDuration = `${rand(6, 12).toFixed(1)}s`;
            flies.append(f);
        }
        const blossoms = theme.fx?.blossoms ?? [];
        const layer = $('petals');
        if (reducedMotion || !blossoms.length || !layer.animate) return;
        const drop = () => {
            if (!document.hidden && !document.body.classList.contains('free') && layer.childElementCount < 7) {
                const p = document.createElement('img');
                p.className = 'petal';
                p.alt = '';
                p.src = blossoms[Math.floor(Math.random() * blossoms.length)];
                p.style.left = `${rand(-5, 100)}%`;
                layer.append(p);
                const drift = rand(-14, 14);
                p.animate([
                    { transform: 'translate(0, 0) rotate(0deg)' },
                    { transform: `translate(${drift / 2}vw, 55vh) rotate(${rand(120, 260)}deg)`, offset: 0.5 },
                    { transform: `translate(${drift}vw, 118vh) rotate(${rand(300, 540)}deg)` },
                ], { duration: rand(11000, 17000), easing: 'linear' }).finished.catch(() => {}).then(() => p.remove());
            }
            setTimeout(drop, rand(1400, 2600));
        };
        drop();
    }

    // ---- The host: the Queen Bee beside the grid --------------------------------------------
    // Every pose is in the page (slot.blade.php, "The host"); `on` marks the one she shows and
    // the CSS crossfades them, so a switch is smooth and she never blinks out. A win makes her
    // cheer, with a springy hop and sparkles; she keeps cheering while the wins keep coming
    // (each one hops again) and settles back a little after the last. A big win: wow; free
    // spins: honey. A minute without a spin, she waits, tapping her chin, until the next spin.
    // Landscape and portrait each have her (.host, .host-peek).

    const HOST_REACTIONS = { // kind -> [pose, how long she keeps it after the last one, sparkles]
        win: ['cheer', 2600, 3],
        big: ['wow', 4400, 6],
        free: ['honey', 3200, 4],
    };
    const HOST_WAIT_MS = 60_000;
    const HOST_HOP = [ // a little crouch, a springy hop, a soft landing
        { transform: 'translateY(0) scale(1, 1)', easing: 'cubic-bezier(.3, 0, .5, 1)' },
        { transform: 'translateY(.6%) scale(1.02, .98)', offset: 0.12, easing: 'cubic-bezier(.2, .7, .4, 1)' },
        { transform: 'translateY(-6%) scale(.99, 1.015)', offset: 0.45, easing: 'cubic-bezier(.5, 0, .7, .4)' },
        { transform: 'translateY(0) scale(1.015, .985)', offset: 0.78, easing: 'ease-out' },
        { transform: 'translateY(0) scale(1, 1)' },
    ];
    const hostFigs = [...document.querySelectorAll('.host-fig')];
    let hostPose = 'idle';
    let hostBack = 0;
    let hostWait = 0;
    let hostHop = null;

    /** Shows a pose, or idle when it isn't delivered. True when it could show that pose. */
    function hostShow(pose) {
        const has = hostFigs.some((fig) => fig.dataset.pose === pose);
        const next = has ? pose : 'idle';
        if (next !== hostPose) {
            hostPose = next;
            for (const fig of hostFigs) fig.classList.toggle('on', fig.dataset.pose === next);
        }
        return has;
    }

    /** A win ('win'), a big win ('big') or free spins ('free'): its pose, a hop and sparkles. */
    function hostReact(kind) {
        if (!hostFigs.length) return;
        const [pose, keep, sparks] = HOST_REACTIONS[kind];
        if (hostShow(pose)) {
            clearTimeout(hostBack);
            hostBack = setTimeout(() => hostShow('idle'), dur(keep));
        }
        if (reducedMotion || state.quick) return;
        const bodies = [...document.querySelectorAll('.host-fig.on .host-body')];
        // A hop still in the air finishes; the next win hops again.
        if (hostHop?.playState !== 'running') {
            const hops = bodies.map((body) => body.animate?.(HOST_HOP, { duration: dur(kind === 'win' ? 640 : 820) }));
            hostHop = hops.find(Boolean) ?? null;
        }
        if (theme.fx?.twinkle) {
            for (const fig of document.querySelectorAll('.host-fig.on')) {
                for (let i = 0; i < sparks; i++) hostSpark(fig, i);
            }
        }
    }

    /** A sparkle glinting around her head and shoulders. */
    function hostSpark(fig, i) {
        const img = document.createElement('img');
        img.className = 'spark';
        img.src = theme.fx.twinkle;
        img.alt = '';
        img.style.left = `${rand(12, 88)}%`;
        img.style.top = `${rand(4, 50)}%`;
        img.style.width = `${rand(14, 26)}%`;
        fig.append(img);
        img.animate([
            { opacity: 0, transform: 'scale(.2) rotate(0deg)' },
            { opacity: 1, transform: 'scale(1.1) rotate(45deg)', offset: 0.35 },
            { opacity: 0, transform: 'scale(.3) rotate(90deg)' },
        ], { duration: dur(720), delay: dur(i * 110 + rand(0, 80)), easing: 'ease-out', fill: 'both' })
            .finished.catch(() => {}).then(() => img.remove());
    }

    /** A spin starts: she stops waiting. */
    function hostSpin() {
        clearTimeout(hostWait);
        if (hostPose === 'waiting') hostShow('idle');
    }

    /** The game is idle again: she waits after a while. */
    function hostRest() {
        clearTimeout(hostWait);
        if (hostFigs.length) hostWait = setTimeout(() => { if (!state.busy) hostShow('waiting'); }, HOST_WAIT_MS);
    }

    // The effects' art loads after the first paint, so the board shows at once.
    function preload() {
        const art = theme.fx ?? {};
        [art.frostBurst, art.frostRing, art.frostCrust, art.twinkle, art.comet, ...(art.crystals ?? [])].forEach(sprite);
        Object.values(theme.fragments ?? {}).flat().forEach((piece) => sprite(piece.src));
    }

    let fitPending = false;
    window.addEventListener('resize', () => {
        if (fitPending) return;
        fitPending = true;
        requestAnimationFrame(() => {
            fitPending = false;
            fitCanvas();
        });
    });

    state.turbo = readPref('turbo', false);
    $('sound-btn').setAttribute('aria-pressed', String(sound.on));
    fitCanvas();
    scenery();
    setTimeout(preload, 400);
    render(state.cells);
    balance();
    controls();
    hostRest();

    if (!cfg.embedded) {
        $('status').textContent = `Open ${cfg.gameName} from your casino to play.`;
    } else {
        if (!reducedMotion) {
            // The lobby reveals its frame at load, so the animation starts when it is visible.
            if (document.readyState === 'complete') playIntro();
            else window.addEventListener('load', playIntro, { once: true });
        }
        bridge.ready({ home: true });
    }

    // ---- The intro ---------------------------------------------------------------------------
    // The choreography is CSS (slot.blade.php, .intro-*); this adds the random parts (the
    // sprinkles bursting off the logo as it lands, glints on the letters, petals drifting down),
    // the jingle and the exit. A tap anywhere or Escape skips it.

    const INTRO = { land: 500, length: 3800, leave: 450, skip: 220 };

    function playIntro() {
        const intro = $('intro');
        if (state.busy || typeof intro.showModal !== 'function') return;
        intro.showModal();
        const scene = intro.querySelector('.intro-scene');
        const logo = intro.querySelector('.intro-logo');
        const timers = [];
        const later = (ms, fn) => timers.push(setTimeout(fn, ms));
        let leaving = false;
        const finish = (ms) => {
            if (leaving || !intro.open) return;
            leaving = true;
            intro.style.setProperty('--leave', `${ms}ms`);
            intro.classList.add('leaving');
            later(ms, () => intro.close());
        };
        const skip = () => finish(INTRO.skip);

        introSound(intro);
        petals(scene, 18);
        later(INTRO.land, () => sprinkleBurst(scene, logo));
        for (let i = 0; i < 8; i++) later(950 + i * 330 + rand(0, 180), () => glint(scene, logo));
        later(INTRO.length, () => finish(INTRO.leave));
        intro.addEventListener('click', skip);
        intro.addEventListener('close', () => {
            timers.forEach(clearTimeout);
            intro.removeEventListener('click', skip);
            scene.querySelectorAll('.intro-bit').forEach((el) => el.remove());
        }, { once: true });
    }

    /**
     * The jingle, only when it can sound in time with the picture: when the casino delegated
     * autoplay or the player already tapped. Otherwise the audio waits for the first spin, and
     * nothing from the intro is left queued for it.
     */
    function introSound(intro) {
        const pack = audio();
        if (!pack) return;
        const opened = performance.now();
        const play = () => {
            if (intro.open && !intro.classList.contains('leaving') && performance.now() - opened < 250) pack.sfx('intro');
        };
        if (sound.ctx.state === 'running') play();
        else sound.ctx.resume().then(play, () => {});
    }

    /** An image in the intro at (x, y) px of the scene, w px wide. */
    function introBit(scene, src, x, y, w, cls = '') {
        const img = document.createElement('img');
        img.className = `intro-bit ${cls}`;
        img.src = src;
        img.alt = '';
        img.style.cssText = `left:${x}px;top:${y}px;width:${w}px`;
        scene.append(img);
        return img;
    }

    /** The logo lands: sprinkles and sugar crystals fly off its letters and fall under gravity. */
    function sprinkleBurst(scene, logo) {
        const sprinkles = theme.fx?.sprinkles ?? [];
        const crystals = theme.fx?.crystals ?? [];
        if (!sprinkles.length) return;
        const s = scene.getBoundingClientRect();
        const l = logo.getBoundingClientRect();
        const reach = Math.max(s.width, s.height);
        const cx = l.left - s.left + l.width / 2;
        const cy = l.top - s.top + l.height / 2;
        for (let i = 0; i < 60; i++) {
            const crystal = i % 6 === 0 && crystals.length > 0;
            // Each starts on the logo and flies away from its centre, mostly sideways and up.
            const x0 = cx + rand(-0.45, 0.45) * l.width;
            const y0 = cy + rand(-0.35, 0.35) * l.height;
            const angle = Math.atan2(y0 - cy, (x0 - cx) * 0.6) + rand(-0.5, 0.5);
            const speed = reach * rand(0.3, 0.85);
            const vx = Math.cos(angle) * speed * 1.3;
            const vy = Math.sin(angle) * speed - reach * 0.35;
            const g = reach * 1.5;
            const spin = rand(-720, 720);
            const life = rand(1.3, 2.2);
            const size = reach * (crystal ? rand(0.022, 0.034) : rand(0.014, 0.026));
            const src = crystal ? crystals[i % crystals.length] : sprinkles[Math.floor(Math.random() * sprinkles.length)];
            const img = introBit(scene, src, x0, y0, size);
            const frames = [];
            for (let k = 0; k <= 10; k++) {
                const t = (life * k) / 10;
                frames.push({
                    transform: `translate(${vx * t}px, ${vy * t + (g * t * t) / 2}px) rotate(${spin * t}deg) scale(${k === 0 ? 0.3 : 1})`,
                    opacity: k < 7 ? 1 : 1 - (k - 6) / 4,
                });
            }
            img.animate(frames, { duration: life * 1000, easing: 'linear', fill: 'forwards' }).finished.then(() => img.remove(), () => {});
        }
    }

    /** A star-shaped glint on the letters. */
    function glint(scene, logo) {
        if (!theme.fx?.twinkle) return;
        const s = scene.getBoundingClientRect();
        const l = logo.getBoundingClientRect();
        const img = introBit(scene, theme.fx.twinkle, l.left - s.left + rand(0.12, 0.88) * l.width, l.top - s.top + rand(0.15, 0.8) * l.height,
            l.width * rand(0.16, 0.26));
        img.animate([
            { opacity: 0, scale: 0, rotate: '0deg' },
            { opacity: 1, scale: 1.1, rotate: '45deg', offset: 0.4 },
            { opacity: 0, scale: 0, rotate: '90deg' },
        ], { duration: 700, easing: 'ease-out', fill: 'forwards' }).finished.then(() => img.remove(), () => {});
    }

    /** Orchard blossom drifting down, some under the logo and some in front; a few already falling. */
    function petals(scene, count) {
        const blossoms = theme.fx?.blossoms ?? [];
        if (!blossoms.length) return;
        const s = scene.getBoundingClientRect();
        const reach = Math.max(s.width, s.height);
        for (let i = 0; i < count; i++) {
            const behind = i % 3 !== 0;
            const size = reach * rand(0.018, 0.03) * (behind ? 0.85 : 1.3);
            const img = introBit(scene, blossoms[i % blossoms.length], rand(0.03, 0.97) * s.width, -size, size, behind ? 'behind' : '');
            const fall = s.height + size * 2;
            const sway = s.width * rand(0.03, 0.07) * (Math.random() < 0.5 ? -1 : 1);
            const phase = rand(0, Math.PI * 2);
            const spin = rand(-300, 300);
            const life = rand(4.5, 7) * 1000;
            const frames = [];
            for (let k = 0; k <= 12; k++) {
                const f = k / 12;
                frames.push({ transform: `translate(${Math.sin(phase + f * Math.PI * 3) * sway}px, ${fall * f}px) rotate(${spin * f}deg)` });
            }
            img.animate(frames, { duration: life, delay: i < count * 0.6 ? -rand(0.1, 0.6) * life : rand(0, 1800), easing: 'linear', fill: 'both' })
                .finished.then(() => img.remove(), () => {});
        }
    }

    function readPref(key, fallback) {
        try {
            const v = localStorage.getItem(`orange.slot.${key}`);
            return v === null ? fallback : v === '1';
        } catch (_) {
            return fallback;
        }
    }

    function writePref(key, value) {
        try {
            localStorage.setItem(`orange.slot.${key}`, value ? '1' : '0');
        } catch (_) { /* private mode */ }
    }

    return { state, spin };
}

/** A pleasant resting board before the first spin (decoration only, never an outcome). */
function idleGrid() {
    return [...'GEHFA' + 'IBEGC' + 'DHSFI' + 'FCIEB' + 'AGDHE' + 'HEFIG'].map((s) => ({ s, v: 0 }));
}
