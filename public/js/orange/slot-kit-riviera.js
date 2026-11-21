/**
 * Tutti Frutti's own effects: the theme kit "riviera" (theme.php `kit`), on top of the shared
 * slot page (slot-game.js, see `kit` there for the hooks). Everything here is show: the outcome
 * was decided on the server and the page only plays it back.
 *
 * - The hostesses (the "duet" layout): Marina on the left, Bella on the right, each with her
 *   own poses (.hostess-fig, theme.hosts). A line win: Marina cheers and Bella winks; the tease:
 *   Marina shakes her shaker; a multiplier kiss: Bella blows it; free spins: both start the
 *   party; a big win: both dance; a minute without a spin: both tease the player.
 * - The juice: each fruit on a paying line bursts with juice in its own colour (one white
 *   splash, tinted per fruit) and drops fly off it under gravity.
 * - The kisses: hearts fly from Bella's hand to every multiplier kiss on a paying line.
 * - Amore (free spins): every kiss that lands flies into the heart, which fills and beats.
 * - The scene: the sea glitters by day; in the Neon Night heart fireworks burst over the bay.
 * - Big wins: a glittering wave sweeps the screen, confetti and fruit rain, hearts burst.
 *
 * Quick-stop (state.quick) and reduced motion skip the show; turbo speeds it up (api.dur).
 */
export function createKit(api) {
    const {
        state, theme, $, board, reducedMotion, rand, dur, wait, animate, sfx, emit, sprite, cellBox, cellOnScreen, shake, floatText, WILD, SCATTER,
    } = api;
    const art = theme.kitArt ?? {};
    const motion = () => !reducedMotion && !state.quick;
    const calm = () => !document.hidden && !state.busy && $('overlay').hidden;

    // ---- The hostesses ---------------------------------------------------------------------------

    const figs = [...document.querySelectorAll('.hostess-fig')];
    const hostOf = (fig) => fig.closest('[data-host]')?.dataset.host;
    const back = {};
    let waiting = 0;
    const has = (who, pose) => figs.some((fig) => hostOf(fig) === who && fig.dataset.pose === pose);

    function show(who, pose) {
        if (!has(who, pose)) pose = 'idle';
        for (const fig of figs) {
            if (hostOf(fig) === who) fig.classList.toggle('on', fig.dataset.pose === pose);
        }
    }

    /** She takes a pose for `keep` ms (longer if she is asked again meanwhile), with a little hop. */
    function strike(who, pose, keep) {
        if (!has(who, pose)) return;
        show(who, pose);
        clearTimeout(back[who]);
        back[who] = setTimeout(() => show(who, 'idle'), dur(keep));
        if (!motion()) return;
        for (const fig of figs) {
            if (hostOf(fig) !== who || fig.dataset.pose !== pose) continue;
            fig.querySelector('.pose')?.animate?.([
                { translate: '0 0' }, { translate: '0 -3.5%', offset: 0.4 }, { translate: '0 .4%', offset: 0.8 }, { translate: '0 0' },
            ], { duration: dur(560), easing: 'ease-out' });
        }
    }

    /** The point of a pose (theme.hosts[who][pose][name]) on screen, from whichever copy is laid out. */
    function point(who, pose, name) {
        const p = theme.hosts?.[who]?.[pose]?.[name];
        if (!p) return null;
        for (const fig of figs) {
            if (hostOf(fig) !== who || fig.dataset.pose !== pose) continue;
            const r = fig.getBoundingClientRect();
            if (r.width > 0) return { x: r.left + p.x * r.width, y: r.top + p.y * r.height };
        }
        return null;
    }

    function react(kind) {
        if (kind === 'win') {
            strike('marina', 'cheer', 2200);
            strike('bella', 'wink', 2000);
        } else if (kind === 'big') {
            strike('marina', 'dance', 4600);
            strike('bella', 'dance', 4600);
        } else if (kind === 'free') {
            strike('marina', 'party', 3600);
            strike('bella', 'party', 3600);
        }
    }

    function spinStart() {
        clearTimeout(waiting);
        for (const who of ['marina', 'bella']) {
            if (figs.some((fig) => hostOf(fig) === who && fig.dataset.pose === 'waiting' && fig.classList.contains('on'))) show(who, 'idle');
        }
    }

    function rest() {
        clearTimeout(waiting);
        waiting = setTimeout(() => {
            if (state.busy) return;
            show('marina', 'waiting');
            show('bella', 'waiting');
        }, 60_000);
    }

    /** Two scatters are down: Marina shakes her shaker while the next column spins on. */
    function tease(column) {
        if (column < 0) return;
        strike('marina', 'shake', 1500);
    }

    // ---- Juice -------------------------------------------------------------------------------------
    // The splash and the drops are white liquid (scripts/art: liquid()); each fruit's juice is
    // that liquid multiplied by its colour, with a little of the white screened back on top
    // for the shine. Built once per colour, when the art has loaded.

    const tints = new Map();

    function tinted(url, colour) {
        const key = `${url}|${colour}`;
        if (tints.has(key)) return tints.get(key);
        const im = sprite(url);
        if (!im || !im.complete || !im.naturalWidth) return null;
        const c = document.createElement('canvas');
        c.width = im.naturalWidth;
        c.height = im.naturalHeight;
        const g = c.getContext('2d');
        g.drawImage(im, 0, 0);
        g.globalCompositeOperation = 'multiply';
        g.fillStyle = colour;
        g.fillRect(0, 0, c.width, c.height);
        g.globalCompositeOperation = 'screen';
        g.globalAlpha = 0.32;
        g.drawImage(im, 0, 0);
        g.globalAlpha = 1;
        g.globalCompositeOperation = 'destination-in';
        g.drawImage(im, 0, 0);
        tints.set(key, c);
        return c;
    }

    const juiceOf = (symbol) => (symbol === WILD ? '#ff3fa4' : theme.symbols?.[symbol]?.colour ?? '#ffb347');

    /** A fruit bursts with juice: the splash behind it, drops flying out and falling, a glint. */
    function juice(cell, symbol) {
        if (!motion() || symbol === SCATTER) return;
        const { x, y, size } = cellBox(cell);
        const colour = juiceOf(symbol);
        const splash = tinted(art.splash, colour);
        if (splash) {
            emit({ img: splash, x, y, w: size * 1.25, h: size * 1.25, s0: 0.45, s1: 1.3, a0: 0.8, a1: 0, fade: 0.15, life: 0.5, vr: rand(-0.8, 0.8) });
        }
        const drops = art.droplets ?? [];
        for (let i = 0; i < 12 && drops.length; i++) {
            const drop = tinted(drops[i % drops.length], colour);
            if (!drop) continue;
            const dir = rand(-Math.PI, 0) + rand(-0.5, 0.5);
            const v = size * rand(1.8, 3.8);
            const d = size * rand(0.1, 0.22);
            emit({
                img: drop, x: x + rand(-0.2, 0.2) * size, y: y + rand(-0.2, 0.15) * size, w: d, h: d * (drop.height / drop.width),
                vx: Math.cos(dir) * v, vy: Math.sin(dir) * v, g: size * 9, rot: rand(-0.6, 0.6), vr: rand(-4, 4),
                s0: 0.7, s1: 1, fade: 0.55, life: rand(0.6, 0.95), ease: (t) => t, delay: rand(0, 0.08),
            });
        }
        emit({ img: sprite(art.twinkle), x: x + rand(-0.25, 0.25) * size, y: y + rand(-0.3, 0.1) * size, w: size * 0.9, h: size * 0.9,
            s0: 0.2, s1: 1, fade: 0.4, life: 0.55, light: true, vr: 1.5, delay: 0.08 });
    }

    function lineWin(spin, cells) {
        sfx('splash', cells.length);
        cells.forEach((c, i) => setTimeout(() => juice(c, state.cells[c]?.s), dur(i * 35)));
    }

    // ---- The kisses ------------------------------------------------------------------------------

    /** A heart flies from (x0, y0) to (x1, y1) (screen px) along a curve, trailing tiny hearts. */
    function heartFlight(x0, y0, x1, y1, ms, delay = 0) {
        if (!art.heartTrail) return Promise.resolve();
        const img = document.createElement('img');
        img.className = 'kiss-flight';
        img.src = art.heartTrail;
        img.alt = '';
        const len = Math.max(80, Math.min(innerWidth, innerHeight) * 0.16);
        Object.assign(img.style, { position: 'fixed', left: '0', top: '0', width: `${len}px`, height: `${len / 2}px`, zIndex: 40, pointerEvents: 'none',
            transformOrigin: '100% 50%', mixBlendMode: 'screen' });
        document.body.append(img);
        const lift = Math.min(160, Math.hypot(x1 - x0, y1 - y0) * 0.35);
        const frames = [];
        for (let k = 0; k <= 12; k++) {
            const t = k / 12;
            const x = x0 + (x1 - x0) * t;
            const y = y0 + (y1 - y0) * t - Math.sin(Math.PI * t) * lift;
            const nx = x0 + (x1 - x0) * Math.min(1, t + 0.05);
            const ny = y0 + (y1 - y0) * Math.min(1, t + 0.05) - Math.sin(Math.PI * Math.min(1, t + 0.05)) * lift;
            const angle = Math.atan2(ny - y, nx - x);
            frames.push({ transform: `translate(${x - len}px, ${y - len / 4}px) rotate(${angle}rad)`, opacity: k === 0 ? 0 : k === 12 ? 0.2 : 1 });
        }
        return animate(img, frames, ms, { easing: 'ease-in-out', delay }).then(() => img.remove());
    }

    function burstAt(x, y, size, src = art.heartBurst) {
        if (!src) return;
        const img = document.createElement('img');
        img.src = src;
        img.alt = '';
        Object.assign(img.style, { position: 'fixed', left: `${x}px`, top: `${y}px`, width: `${size}px`, translate: '-50% -50%', zIndex: 41,
            pointerEvents: 'none', mixBlendMode: 'screen' });
        document.body.append(img);
        animate(img, [{ scale: 0.3, opacity: 0 }, { scale: 1, opacity: 1, offset: 0.3 }, { scale: 1.35, opacity: 0 }], 700, { easing: 'ease-out' })
            .then(() => img.remove());
    }

    /** Bella blows a kiss: a heart flies to every multiplier kiss on the paying lines, which bursts. */
    async function kisses(spin, lines) {
        const cells = [...new Set(lines.flatMap((l) => l.run).filter((c) => state.cells[c]?.s === WILD && state.cells[c].v > 1))];
        strike('bella', 'kiss', 1800);
        sfx('kiss', cells.length);
        if (!motion() || !cells.length) return;
        await wait(260);
        const from = point('bella', 'kiss', 'palm');
        const flights = cells.map((c, i) => {
            const to = cellOnScreen(c);
            if (!from) return Promise.resolve();
            return heartFlight(from.x, from.y, to.x, to.y, 620, i * 110).then(() => {
                burstAt(to.x, to.y, to.size * 1.8);
                state.els[c]?.animate?.([{ scale: 1 }, { scale: 1.3, offset: 0.35 }, { scale: 1 }], { duration: dur(420), easing: 'ease-out' });
                sfx('amore', state.cells[c].v);
            });
        });
        await Promise.all(flights);
        const sum = lines.reduce((best, l) => Math.max(best, l.multiplier), 0);
        if (sum > 1) floatText(`×${sum}`, cells, 'mult');
        await wait(380);
    }

    // ---- Amore -------------------------------------------------------------------------------------

    const meter = $('amore');
    const level = $('amore-level');
    let amoreShown = 1;

    function setAmore(value, beat = true) {
        amoreShown = value;
        if (!meter) return;
        $('amore-x').textContent = `×${value}`;
        // The heart fills toward ×25 and glows hotter: pink, magenta from ×10, gold from ×25.
        const fill = Math.min(1, (value - 1) / 24);
        level?.setAttribute('y', String(90 - 90 * Math.max(0.08, fill)));
        level?.style.setProperty('fill', value >= 25 ? '#ffc93c' : value >= 10 ? '#ff1fa0' : '#ff6fbf');
        if (beat && !reducedMotion) {
            meter.classList.remove('beat');
            void meter.offsetWidth;
            meter.classList.add('beat');
        }
    }

    /** A free spin's kisses fly into Amore, which counts up one by one. */
    async function amore(spin) {
        if (!meter || spin.amore === null) return;
        const target = meter.getBoundingClientRect();
        const tx = target.left + target.width / 2;
        const ty = target.top + target.height / 2;
        const start = amoreShown;
        const cells = spin.wildCells;
        if (!motion()) {
            setAmore(spin.amore);
            return;
        }
        await wait(200);
        await Promise.all(cells.map((c, i) => {
            const from = cellOnScreen(c);
            return heartFlight(from.x, from.y, tx, ty, 560, i * 140).then(() => {
                setAmore(Math.min(spin.amore, start + i + 1));
                burstAt(tx, ty, target.width * 1.6);
                sfx('amore', start + i + 1);
            });
        }));
        setAmore(spin.amore, false);
    }

    function amoreMultiplies() {
        if (!meter || !motion()) return;
        const r = meter.getBoundingClientRect();
        burstAt(r.left + r.width / 2, r.top + r.height / 2, r.width * 2.2);
        setAmore(amoreShown);
    }

    async function freeSpins(on) {
        if (meter) {
            meter.hidden = !on;
            setAmore(1, on);
        }
        if (on && motion()) {
            // The sun goes down: heart fireworks open the night.
            for (let i = 0; i < 4; i++) setTimeout(firework, dur(200 + i * 380));
            await wait(900);
        }
    }

    // ---- The scene ---------------------------------------------------------------------------------

    let sky = null;

    function scenery() {
        sky = document.createElement('div');
        sky.className = 'riviera-sky';
        document.querySelector('.scenery')?.prepend(sky);
        if (reducedMotion) return;
        setTimeout(glitterLoop, 1200);
        setTimeout(fireworkLoop, 3000);
    }

    /** By day, glints come and go on the sea (the band behind and below the reels). */
    function glitterLoop() {
        setTimeout(glitterLoop, rand(350, 800));
        if (document.hidden || document.body.classList.contains('free') || !art.twinkle || !sky) return;
        const portrait = innerHeight > innerWidth;
        const img = document.createElement('img');
        img.src = art.twinkle;
        img.alt = '';
        const size = Math.min(innerWidth, innerHeight) * rand(0.025, 0.05);
        Object.assign(img.style, { left: `${rand(2, 98)}%`, top: `${portrait ? rand(40, 62) : rand(36, 60)}%`, width: `${size}px` });
        sky.append(img);
        animate(img, [{ scale: 0, opacity: 0, rotate: '0deg' }, { scale: 1, opacity: 0.9, rotate: '45deg', offset: 0.45 }, { scale: 0, opacity: 0, rotate: '90deg' }],
            rand(700, 1100), { easing: 'ease-in-out' }).then(() => img.remove());
    }

    /** In the Neon Night a heart firework bursts over the bay now and then. */
    function fireworkLoop() {
        setTimeout(fireworkLoop, rand(1800, 4200));
        if (document.hidden || !document.body.classList.contains('free')) return;
        firework();
    }

    function firework() {
        if (!art.firework || !sky) return;
        const img = document.createElement('img');
        img.src = art.firework;
        img.alt = '';
        const portrait = innerHeight > innerWidth;
        const size = Math.min(innerWidth, innerHeight) * rand(0.22, 0.38);
        Object.assign(img.style, { left: `${rand(8, 92)}%`, top: `${portrait ? rand(6, 22) : rand(8, 30)}%`, width: `${size}px` });
        sky.append(img);
        animate(img, [{ scale: 0.15, opacity: 0 }, { scale: 0.9, opacity: 1, offset: 0.25 }, { scale: 1.08, opacity: 0.85, offset: 0.6 }, { scale: 1.15, opacity: 0, translate: '0 6%' }],
            rand(1500, 2100), { easing: 'ease-out' }).then(() => img.remove());
        if (calm() || state.busy) sfx('firework');
    }

    // ---- Big wins ----------------------------------------------------------------------------------

    function celebrate(tier, dialog, layer) {
        if (reducedMotion) return;
        if (art.sweep) {
            const img = document.createElement('img');
            img.src = art.sweep;
            img.alt = '';
            const w = innerHeight * 2.4;
            Object.assign(img.style, { position: 'absolute', left: '0', top: '0', height: '100%', width: `${w}px`, mixBlendMode: 'screen', pointerEvents: 'none' });
            layer.prepend(img);
            animate(img, [
                { transform: `translateX(${-w}px)`, opacity: 1 },
                { transform: `translateX(${innerWidth * 0.15}px)`, opacity: 1, offset: 0.55 },
                { transform: `translateX(${innerWidth + 40}px)`, opacity: 0 },
            ], 2000, { easing: 'cubic-bezier(.3, .1, .5, 1)' }).then(() => img.remove());
        }
        const n = { big: 26, mega: 40, epic: 56, max: 80 }[tier] ?? 26;
        for (let i = 0; i < n; i++) setTimeout(() => !layer.hidden && confetti(layer), dur(200 + i * rand(30, 70)));
        const bursts = { big: 3, mega: 5, epic: 7, max: 10 }[tier] ?? 3;
        for (let i = 0; i < bursts; i++) {
            setTimeout(() => {
                if (layer.hidden) return;
                burstAt(rand(0.15, 0.85) * innerWidth, rand(0.15, 0.6) * innerHeight, Math.min(innerWidth, innerHeight) * rand(0.25, 0.4), art.firework);
                sfx('firework');
            }, dur(300 + i * 420));
        }
        shake(tier === 'max' ? 12 : tier === 'epic' ? 9 : 6, 700);
    }

    /** A piece of confetti, a paper parasol, a fruit slice or a gold heart coin falls behind the banner. */
    function confetti(layer) {
        const pieces = art.confetti ?? [];
        if (!pieces.length) return;
        const img = document.createElement('img');
        img.src = pieces[Math.floor(Math.random() * pieces.length)];
        img.alt = '';
        const size = Math.min(innerWidth, innerHeight) * rand(0.035, 0.07);
        Object.assign(img.style, { position: 'absolute', left: `${rand(0, 100)}%`, top: `${-size}px`, width: `${size}px`, pointerEvents: 'none' });
        layer.prepend(img);
        const drift = rand(-12, 12);
        animate(img, [
            { transform: 'translate(0, 0) rotate(0deg)' },
            { transform: `translate(${drift / 2}vw, 55vh) rotate(${rand(160, 360)}deg)`, offset: 0.5 },
            { transform: `translate(${drift}vw, 112vh) rotate(${rand(380, 720)}deg)` },
        ], rand(1800, 2800), { easing: 'linear' }).then(() => img.remove());
    }

    // ---- Start-up ------------------------------------------------------------------------------------

    function preload() {
        [art.splash, ...(art.droplets ?? []), art.heartTrail, art.heartBurst, art.firework, art.twinkle, art.sweep].forEach((url) => url && sprite(url));
        // The juice colours once the white liquid has loaded, so the first win doesn't build them mid-burst.
        setTimeout(() => {
            for (const symbol of Object.keys(theme.symbols ?? {})) {
                tinted(art.splash, juiceOf(symbol));
                (art.droplets ?? []).slice(0, 12).forEach((d) => tinted(d, juiceOf(symbol)));
            }
        }, 1500);
    }

    return { scenery, preload, react, spinStart, rest, tease, lineWin, kisses, amore, amoreMultiplies, freeSpins, celebrate };
}
