/**
 * Tutti Frutti's own effects: the theme kit "riviera" (theme.php `kit`), on top of the shared
 * slot page (slot-game.js, see `kit` there for the hooks). Everything here is show: the outcome
 * was decided on the server and the page only plays it back.
 *
 * - The girls (the "duet" layout): the player picks two of ten (theme.girls; Bella and Marina by
 *   default) in the picker. Her girl stands on the right, her friend on the left. A line win:
 *   both cheer; the tease: her friend gets nervous; a multiplier kiss: her girl blows it; free
 *   spins: both start the party; a big win: both dance; a minute without a spin: both tease.
 * - The juice: each fruit on a paying line bursts with juice in its own colour (one white
 *   splash, tinted per fruit) and drops fly off it under gravity.
 * - The kisses: hearts fly from her girl's hand to every multiplier kiss on a paying line.
 * - Amore (free spins): every kiss that lands flies into the heart, which fills and beats.
 * - The farewell: when the free spins end, her girl blows a kiss that zooms out of the screen.
 * - The scene: the sea glitters by day; in the Neon Night heart fireworks burst over the bay.
 * - Big wins: a glittering wave sweeps the screen, confetti and fruit rain, hearts burst.
 *
 * Quick-stop (state.quick) and reduced motion skip the show; turbo speeds it up (api.dur).
 */
export function createKit(api) {
    const {
        state, theme, cfg, $, board, reducedMotion, rand, dur, wait, animate, sfx, emit, sprite, cellBox, cellOnScreen, shake, floatText, WILD, SCATTER,
    } = api;
    const art = theme.kitArt ?? {};
    const motion = () => !reducedMotion && !state.quick;
    const calm = () => !document.hidden && !state.busy && $('overlay').hidden;

    // ---- The girls -------------------------------------------------------------------------------
    // The player picks two of the theme's girls (theme.girls): her girl stands on the right and
    // blows the kisses, her friend on the left cheers and gets nervous on the tease. Every girl
    // has the same roles (idle, win, kiss, tease, dance, party, waiting); one she lacks shows her
    // idle. A girl is mirrored on the side where she would face away from the reels. The pick is
    // kept in this browser (localStorage) and is only for show: it never touches a bet.

    const GIRLS = theme.girls ?? {};
    const NAMES = Object.keys(GIRLS);
    const SIDES = ['right', 'left'];
    const PICK = 'orange.slot.tutti-frutti.girls';
    const pair = loadPair();
    let figs = [];
    const sideOf = (fig) => fig.closest('[data-host]')?.dataset.host;
    const back = {};
    let waiting = 0;

    function loadPair() {
        const fallback = { right: 'bella', left: 'marina', ...theme.pair };
        let saved = null;
        try {
            saved = JSON.parse(localStorage.getItem(PICK) ?? 'null');
        } catch (_) { /* private mode */ }
        const right = NAMES.includes(saved?.right) ? saved.right : fallback.right;
        let left = NAMES.includes(saved?.left) && saved.left !== right ? saved.left : fallback.left;
        if (left === right) left = NAMES.find((n) => n !== right) ?? left;
        return { right, left };
    }

    function savePair() {
        try {
            localStorage.setItem(PICK, JSON.stringify(pair));
        } catch (_) { /* private mode */ }
    }

    /** Whether the girl on `side` is drawn facing away from the reels there (she faces right on the right, or left on the left). */
    const mirrored = (side) => (GIRLS[pair[side]]?.faces === 'left') === (side === 'left');

    /** Puts the picked girl's poses on her side: the full-length figure and the portrait peek. */
    function dress(side) {
        const girl = GIRLS[pair[side]];
        if (!girl) return;
        const flip = mirrored(side);
        for (const holder of document.querySelectorAll(`[data-host="${side}"]`)) {
            holder.querySelectorAll('.hostess-fig').forEach((fig) => fig.remove());
            for (const [role, pose] of Object.entries(girl.poses ?? {})) {
                const fig = document.createElement('div');
                fig.className = `hostess-fig${role === 'idle' ? ' on' : ''}${flip ? ' mirror' : ''}`;
                fig.dataset.pose = role;
                fig.style.cssText = `--cx: ${flip ? 1 - pose.cx : pose.cx}; --ar: ${pose.ar}; --k: ${pose.k ?? 1}; --fy: ${pose.feet ?? 1}`;
                fig.innerHTML = `<div class="hostess-body"><img class="pose" src="${pose.src}" alt="" decoding="async"></div>`;
                holder.append(fig);
            }
        }
        figs = [...document.querySelectorAll('.hostess-fig')];
    }

    const has = (side, role) => Boolean(GIRLS[pair[side]]?.poses?.[role]);

    function show(side, role) {
        if (!has(side, role)) role = 'idle';
        for (const fig of figs) {
            if (sideOf(fig) === side) fig.classList.toggle('on', fig.dataset.pose === role);
        }
    }

    /** She takes a pose for `keep` ms (longer if she is asked again meanwhile), with a little hop. */
    function strike(side, role, keep) {
        if (!has(side, role)) return;
        show(side, role);
        clearTimeout(back[side]);
        back[side] = setTimeout(() => show(side, 'idle'), dur(keep));
        if (!motion()) return;
        for (const fig of figs) {
            if (sideOf(fig) !== side || fig.dataset.pose !== role) continue;
            fig.querySelector('.pose')?.animate?.([
                { translate: '0 0' }, { translate: '0 -3.5%', offset: 0.4 }, { translate: '0 .4%', offset: 0.8 }, { translate: '0 0' },
            ], { duration: dur(560), easing: 'ease-out' });
        }
    }

    /** A named point of the pose a girl shows (e.g. her kiss hand) on screen, from whichever copy is laid out. */
    function point(side, role, name) {
        const p = GIRLS[pair[side]]?.poses?.[role]?.[name];
        if (!p) return null;
        const x = mirrored(side) ? 1 - p.x : p.x;
        for (const fig of figs) {
            if (sideOf(fig) !== side || fig.dataset.pose !== role) continue;
            const r = fig.getBoundingClientRect();
            if (r.width > 0) return { x: r.left + x * r.width, y: r.top + p.y * r.height };
        }
        return null;
    }

    function react(kind) {
        const role = { win: 'win', big: 'dance', free: 'party' }[kind];
        if (!role) return;
        const keep = { win: 2100, big: 4600, free: 3600 }[kind];
        for (const side of SIDES) strike(side, role, keep);
        if (kind === 'free') girlsButton?.classList.add('away');
    }

    function spinStart() {
        clearTimeout(waiting);
        if (girlsButton) girlsButton.disabled = true;
        for (const side of SIDES) {
            if (figs.some((fig) => sideOf(fig) === side && fig.dataset.pose === 'waiting' && fig.classList.contains('on'))) show(side, 'idle');
        }
    }

    function rest() {
        clearTimeout(waiting);
        if (girlsButton) girlsButton.disabled = false;
        waiting = setTimeout(() => {
            if (state.busy) return;
            for (const side of SIDES) show(side, 'waiting');
        }, 60_000);
    }

    /** Two scatters are down: her friend gets nervous while the next column spins on. */
    function tease(column) {
        if (column < 0) return;
        strike('left', 'tease', 1500);
    }

    // ---- The picker: choose your girls ---------------------------------------------------------------
    // It opens before the game starts (after the intro), with the last pair picked, so a returning
    // player only taps "Let's play". Between spins the heart button, or a tap on either girl,
    // opens it again (on her side's tab).

    const girlsButton = $('girls-btn');
    girlsButton?.addEventListener('click', () => openPicker('right'));
    // A tap on a girl: change her.
    document.addEventListener('click', (e) => {
        const fig = e.target.closest?.('.hostess-fig');
        if (fig) openPicker(sideOf(fig) ?? 'right');
    });

    /** Before the first spin: once the intro has played (embedded in a casino), or at once. */
    function pickAtStart() {
        const intro = $('intro');
        let done = false;
        const open = () => {
            if (done) return;
            done = true;
            setTimeout(() => openPicker('right', true), 250);
        };
        if (cfg?.embedded && !reducedMotion && intro) {
            // playIntro() opens the intro on load; the picker follows when it closes (played or skipped).
            const watch = () => (intro.open ? intro.addEventListener('close', open, { once: true }) : open());
            if (document.readyState === 'complete') setTimeout(watch, 50);
            else window.addEventListener('load', () => setTimeout(watch, 50), { once: true });
        } else if (document.readyState === 'complete') {
            setTimeout(open, 400);
        } else {
            window.addEventListener('load', () => setTimeout(open, 400), { once: true });
        }
    }

    function openPicker(start = 'right', atStart = false) {
        if (state.busy || !NAMES.length || typeof HTMLDialogElement === 'undefined' || document.querySelector('.girls-dialog')) return;
        if (!atStart) sfx('click');
        let slot = SIDES.includes(start) ? start : 'right';
        const frame = theme.girlFrame ?? {};
        const w = frame.window ?? { x0: 0, y0: 0, x1: 1, y1: 1 };
        const dlg = document.createElement('dialog');
        dlg.className = 'girls-dialog';
        dlg.setAttribute('aria-label', 'Choose your girls');
        dlg.style.cssText = `--frame: url('${frame.src ?? ''}'); --wx0: ${w.x0 * 100}%; --wy0: ${w.y0 * 100}%; --wx1: ${(1 - w.x1) * 100}%; --wy1: ${(1 - w.y1) * 100}%`;
        dlg.innerHTML = `<h2>CHOOSE YOUR GIRLS</h2>
            <p class="girl-lead">Your girl blows the kisses, her friend cheers you on.</p>
            <div class="girl-slots" role="tablist">
                <button type="button" class="girl-slot" role="tab" data-slot="right"><small>YOUR GIRL</small><b></b></button>
                <button type="button" class="girl-slot" role="tab" data-slot="left"><small>HER FRIEND</small><b></b></button>
            </div>
            <div class="girl-grid">${NAMES.map((n) => {
                const g = GIRLS[n];
                return `<button type="button" class="girl-card" data-girl="${n}" style="--girl: ${g.colour}" aria-label="${g.name}">
                    <span class="girl-photo"><img src="${g.card}" alt="" loading="lazy" decoding="async"></span>
                    <span class="girl-name">${g.name}</span><em class="girl-badge"></em></button>`;
            }).join('')}</div>
            <p class="girl-note">Just for fun: your choice never changes the odds.</p>
            <button type="button" class="girl-done">Let's play</button>`;
        document.body.append(dlg);

        const refresh = () => {
            for (const tab of dlg.querySelectorAll('.girl-slot')) {
                tab.classList.toggle('on', tab.dataset.slot === slot);
                tab.setAttribute('aria-selected', String(tab.dataset.slot === slot));
                tab.querySelector('b').textContent = GIRLS[pair[tab.dataset.slot]]?.name ?? '';
                tab.style.setProperty('--girl', GIRLS[pair[tab.dataset.slot]]?.colour ?? '#ff3fa4');
            }
            for (const card of dlg.querySelectorAll('.girl-card')) {
                const n = card.dataset.girl;
                const as = n === pair.right ? 'right' : n === pair.left ? 'left' : '';
                card.classList.toggle('picked', as !== '');
                card.classList.toggle('active', as === slot);
                card.querySelector('.girl-badge').textContent = as === 'right' ? '♥ YOUR GIRL' : as === 'left' ? 'HER FRIEND' : '';
            }
        };
        const pick = (n) => {
            if (pair[slot] === n) return;
            const other = slot === 'right' ? 'left' : 'right';
            const swap = pair[other] === n;
            if (swap) pair[other] = pair[slot];
            pair[slot] = n;
            savePair();
            dress(slot);
            if (swap) dress(other);
            sfx('kiss');
            strike(slot, 'win', 1600);
            // Her girl picked: on to her friend (from the start, or the girl's own tab).
            if (slot === 'right' && (atStart || start === 'right')) slot = 'left';
            refresh();
        };
        dlg.addEventListener('click', (e) => {
            if (e.target === dlg) return dlg.close();
            const tab = e.target.closest('.girl-slot');
            if (tab) {
                slot = tab.dataset.slot;
                sfx('click');
                return refresh();
            }
            const card = e.target.closest('.girl-card');
            if (card) return pick(card.dataset.girl);
            if (e.target.closest('.girl-done')) dlg.close();
        });
        dlg.addEventListener('close', () => dlg.remove(), { once: true });
        refresh();
        dlg.showModal();
        dlg.querySelector('.girl-done').focus();
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

    /** Her girl blows a kiss: a heart flies to every multiplier kiss on the paying lines, which bursts. */
    async function kisses(spin, lines) {
        const cells = [...new Set(lines.flatMap((l) => l.run).filter((c) => state.cells[c]?.s === WILD && state.cells[c].v > 1))];
        strike('right', 'kiss', 1800);
        sfx('kiss', cells.length);
        if (!motion() || !cells.length) return;
        await wait(260);
        const from = point('right', 'kiss', 'palm');
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

    // ---- The farewell kiss: the free spins end with her girl blowing a kiss to the player ------------
    // A big glossy pair of lips flies from her hand toward the screen, lands with a smack and a
    // burst of hearts, then zooms past the camera and is gone. Drawn in code (SVG), so it stays
    // crisp at full screen.

    // The lips: a painted image when the art has one (kitArt.kissLips), else drawn here.
    const LIPS = art.kissLips ? `<img src="${art.kissLips}" alt="" decoding="async">` : `<svg viewBox="0 0 200 130" aria-hidden="true">
        <defs>
            <linearGradient id="lips-up" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0" stop-color="#b8002c"/><stop offset=".45" stop-color="#ff2d5e"/><stop offset=".8" stop-color="#e0063e"/><stop offset="1" stop-color="#8f0022"/>
            </linearGradient>
            <radialGradient id="lips-low" cx=".5" cy=".3" r=".75">
                <stop offset="0" stop-color="#ff6a92"/><stop offset=".35" stop-color="#f0124a"/><stop offset=".8" stop-color="#b3002e"/><stop offset="1" stop-color="#7a001c"/>
            </radialGradient>
            <filter id="lips-soft" x="-20%" y="-20%" width="140%" height="140%"><feGaussianBlur stdDeviation="2.4"/></filter>
            <filter id="lips-edge" x="-5%" y="-5%" width="110%" height="110%"><feGaussianBlur stdDeviation=".5"/></filter>
        </defs>
        <g filter="url(#lips-edge)">
            <path fill="#4d0012" d="M6 70C24 66 44 62 66 64c14 1 24 4 34 4s20-3 34-4c22-2 42 2 60 6-18 2-40 0-60 2-14 1-24 4-34 4s-20-3-34-4C46 70 24 70 6 72Z"/>
            <path fill="url(#lips-up)" d="M6 70C22 52 44 30 70 24c14-3 24 4 30 12 6-8 16-15 30-12 26 6 48 28 64 46-18-4-38-8-60-6-14 1-24 4-34 4s-20-3-34-4C44 62 24 66 6 70Z"/>
            <path fill="url(#lips-low)" d="M6 72c18-2 40 0 60 0 14 1 24 4 34 4s20-3 34-4c20 0 42-2 60 0-12 28-46 52-94 52S18 100 6 72Z"/>
        </g>
        <g fill="none" stroke="#ff9db8" stroke-opacity=".28" stroke-width="1.3" stroke-linecap="round">
            <path d="M56 34c-3 9-3 18-1 26M74 28c-2 10-2 20 0 30M126 28c2 10 2 20 0 30M144 34c3 9 3 18 1 26"/>
            <path d="M46 82c-1 9 2 19 8 26M66 84c-1 12 1 24 5 32M100 86v34M134 84c1 12-1 24-5 32M154 82c1 9-2 19-8 26"/>
        </g>
        <g fill="#fff" filter="url(#lips-soft)">
            <ellipse cx="82" cy="92" rx="26" ry="8" opacity=".55" transform="rotate(-6 82 92)"/>
            <ellipse cx="124" cy="94" rx="18" ry="6" opacity=".4" transform="rotate(8 124 94)"/>
            <ellipse cx="62" cy="40" rx="13" ry="4" opacity=".5" transform="rotate(-26 62 40)"/>
            <ellipse cx="138" cy="40" rx="12" ry="4" opacity=".42" transform="rotate(26 138 40)"/>
        </g>
        <g fill="#fff">
            <ellipse cx="76" cy="90" rx="7" ry="2.4" opacity=".9" transform="rotate(-6 76 90)"/>
            <circle cx="96" cy="94" r="1.6" opacity=".85"/>
            <ellipse cx="60" cy="39" rx="4" ry="1.4" opacity=".85" transform="rotate(-26 60 39)"/>
        </g>
    </svg>`;

    async function freeSpinsEnd() {
        if (!has('right', 'kiss')) return;
        strike('right', 'kiss', 2400);
        sfx('kiss', 2);
        if (!motion()) return;
        await wait(320);
        const girl = figs.find((fig) => sideOf(fig) === 'right' && fig.dataset.pose === 'kiss' && fig.getBoundingClientRect().width > 0)?.getBoundingClientRect();
        const from = point('right', 'kiss', 'palm') ?? (girl ? { x: girl.left + girl.width * 0.4, y: girl.top + girl.height * 0.2 } : { x: innerWidth * 0.8, y: innerHeight * 0.3 });
        const cx = innerWidth / 2;
        const cy = innerHeight * 0.46;
        const size = Math.min(innerWidth * 0.72, innerHeight * 0.66 * (200 / 130));
        const lips = document.createElement('div');
        lips.className = 'farewell-kiss';
        lips.innerHTML = LIPS;
        Object.assign(lips.style, { width: `${size}px` });
        document.body.append(lips);
        const at = (x, y, s, r) => `translate(${x}px, ${y}px) translate(-50%, -50%) scale(${s}) rotate(${r}deg)`;
        // Up out of her hand, a little arc across to the middle of the screen, a smack, then past the camera.
        const midX = from.x + (cx - from.x) * 0.55;
        const midY = Math.min(from.y, cy) - innerHeight * 0.08;
        const flight = animate(lips, [
            { transform: at(from.x, from.y, 0.06, -18), opacity: 0 },
            { transform: at(from.x, from.y, 0.12, -14), opacity: 1, offset: 0.08 },
            { transform: at(midX, midY, 0.45, -6), opacity: 1, offset: 0.34 },
            { transform: at(cx, cy, 1, 3), opacity: 1, offset: 0.58, easing: 'ease-out' },
            { transform: at(cx, cy, 0.94, 0), opacity: 1, offset: 0.64 },
            { transform: at(cx, cy, 1.02, 0), opacity: 1, offset: 0.76, easing: 'cubic-bezier(.6, 0, .9, .5)' },
            { transform: at(cx, cy, 3.6, 0), opacity: 0 },
        ], 2300, { easing: 'cubic-bezier(.35, .1, .45, 1)' });
        // The smack: hearts burst behind the lips, a little shake.
        setTimeout(() => {
            burstAt(cx, cy, size * 1.25);
            burstAt(cx, cy, size * 0.8);
            sfx('kiss', 3);
            shake(6, 420);
        }, dur(2300 * 0.6));
        await flight;
        lips.remove();
    }

    function amoreMultiplies() {
        if (!meter || !motion()) return;
        const r = meter.getBoundingClientRect();
        burstAt(r.left + r.width / 2, r.top + r.height / 2, r.width * 2.2);
        setAmore(amoreShown);
    }

    async function freeSpins(on) {
        // Amore takes the picker button's corner while the free spins play.
        girlsButton?.classList.toggle('away', on);
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

    for (const side of SIDES) dress(side);
    pickAtStart();

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

    return { scenery, preload, react, spinStart, rest, tease, lineWin, kisses, amore, amoreMultiplies, freeSpins, freeSpinsEnd, celebrate };
}
