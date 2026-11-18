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
const STAR_COLOURS = {
    sky: ['#bfe9ff', '#38a8f0', '#0c5d9c'],
    emerald: ['#c8ffd5', '#2fbf63', '#0d6b33'],
    gold: ['#fff2b0', '#f5b81c', '#9a5f00'],
    ruby: ['#ffc2c2', '#e3203b', '#7a0716'],
};

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

    const sound = { ctx: null, pack: null, music: null, on: readPref('sound', true) };
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
        if (!sound.music) sound.music = sound.pack.ambience();
        return sound.pack;
    }
    function sfx(name, arg) {
        if (sound.on && !state.quick) audio()?.sfx(name, arg);
    }
    function music(level) {
        sound.music?.intensity(level);
    }
    function setSound(on) {
        sound.on = on;
        writePref('sound', on);
        $('sound-btn').setAttribute('aria-pressed', String(on));
        if (!on && sound.music) {
            sound.music.stop();
            sound.music = null;
        } else if (on) {
            audio();
        }
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
            el.innerHTML = starSvg(cell.v);
        } else {
            const img = document.createElement('img');
            img.src = theme.symbols[cell.s]?.img ?? '';
            img.alt = theme.symbols[cell.s]?.name ?? cell.s;
            img.draggable = false;
            el.append(img);
        }
        return el;
    }

    function starSvg(value) {
        const [hi, mid, lo] = STAR_COLOURS[starTier(value)];
        const id = `g${Math.random().toString(36).slice(2, 8)}`;
        const pts = [];
        for (let i = 0; i < 10; i++) {
            const r = i % 2 ? 21 : 46;
            const a = Math.PI / 2 + (i * Math.PI) / 5;
            pts.push(`${(50 + r * Math.cos(a)).toFixed(1)},${(52 - r * Math.sin(a)).toFixed(1)}`);
        }
        return `<svg viewBox="0 0 100 100" class="star" aria-label="${value}× multiplier">
            <defs><radialGradient id="${id}" cx="40%" cy="35%" r="70%"><stop offset="0" stop-color="${hi}"/><stop offset=".55" stop-color="${mid}"/><stop offset="1" stop-color="${lo}"/></radialGradient></defs>
            <polygon points="${pts.join(' ')}" fill="url(#${id})" stroke="#ffe39a" stroke-width="4" stroke-linejoin="round"/>
            <ellipse cx="40" cy="30" rx="13" ry="6" fill="#fff" opacity=".45"/>
            <text x="50" y="62" text-anchor="middle" class="star-n">${value}×</text></svg>`;
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

    function burst(cell, colour) {
        const { x, y } = centre([cell]);
        for (let i = 0; i < 9; i++) {
            const p = document.createElement('i');
            p.className = 'spark';
            p.style.left = `${x}%`;
            p.style.top = `${y}%`;
            p.style.background = i % 3 ? colour : '#fff6d8';
            fx.append(p);
            const a = (i / 9) * Math.PI * 2 + Math.random() * 0.5;
            const d = 40 + Math.random() * 45;
            animate(p, [
                { transform: 'translate(-50%,-50%) scale(1)', opacity: 1 },
                { transform: `translate(calc(-50% + ${Math.cos(a) * d}px), calc(-50% + ${Math.sin(a) * d}px)) scale(.2)`, opacity: 0 },
            ], 520, { easing: 'cubic-bezier(.2,.7,.3,1)' }).then(() => p.remove());
        }
    }

    async function playStep(step, index) {
        const winners = step.removed;
        winners.forEach((c) => state.els[c].classList.add('win'));
        const amount = winAmount(state.bet, step.pays);
        floatText(`${money(amount)}`, winners);
        sfx('win', index);
        await wait(700);

        sfx('pop');
        await Promise.all(winners.map((c) => {
            burst(c, theme.symbols[state.cells[c].s]?.colour ?? '#fff');
            return animate(state.els[c], [
                { transform: at(c), opacity: 1 },
                { transform: `${at(c)} scale(1.25)`, opacity: 1, offset: 0.35 },
                { transform: `${at(c)} scale(0)`, opacity: 0 },
            ], 300);
        }));
        winners.forEach((c) => state.els[c].remove());

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
        floatText(`×${spin.multiplier}`, cells, 'mult');
        await wait(900);
        cells.forEach((c) => state.els[c]?.classList.remove('win'));
    }

    async function highlightScatters(cells) {
        cells.forEach((c) => state.els[c]?.classList.add('win', 'scatter'));
        await wait(1100);
        cells.forEach((c) => state.els[c]?.classList.remove('win', 'scatter'));
    }

    // ---- A bet, played back ----------------------------------------------------------------------

    let betWin = 0n;

    function showWin(units) {
        betWin = units;
        $('win').textContent = units > 0n ? money(units) : '0.00';
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
        if (spin.scatterWin > 0) floatText(money(winAmount(state.bet, spin.scatterWin)), spin.scatters, 'scatter-win');
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
            await dialog(`<p class="kicker">You won</p><div class="huge">${base.awarded}</div><p class="kicker">FREE SPINS</p>
                <p class="note">Sugar stars land in free spins and multiply every winning spin.</p>`, { button: 'Start', autoMs: state.auto > 0 || state.turbo ? 2500 : 0 });
            document.body.classList.add('free');
            music(1);
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
                    await dialog(`<div class="huge">+${spin.retrigger}</div><p class="kicker">FREE SPINS</p>`, { autoMs: 1800 });
                }
            }
            sfx('fsEnd');
            await dialog(`<p class="kicker">Free spins win</p><div class="huge">${money(fsWin)}</div><p class="note">${free.length} free spins</p>`,
                { button: 'Collect', autoMs: state.auto > 0 || state.turbo ? 2500 : 0 });
            document.body.classList.remove('free');
            $('fs-hud').hidden = true;
            music(0);
        }

        // Celebrated against what the player paid: a bought feature returning 94× its 100× price is no big win.
        const tier = p.maxWin ? 'max' : winTier(Math.floor((p.total * 100) / Number(COST[p.mode] ?? 100n)));
        if (tier !== 'none') await bigWin(tier, winAmount(state.bet, p.total));
    }

    function hud(n, of, win) {
        $('fs-hud').hidden = false;
        $('fs-count').textContent = `${Math.min(n, of)} / ${of}`;
        $('fs-win').textContent = money(win);
    }

    async function bigWin(tier, units) {
        sfx('big');
        await dialog(`<div class="tier tier-${tier}">${TIERS[tier]}</div><div class="huge count">0.00</div>`, {
            autoMs: 3800,
            onOpen(el) {
                const counter = el.querySelector('.count');
                const total = Number(units) / 1e8;
                const started = performance.now();
                const length = dur(2600);
                const tick = (now) => {
                    const f = Math.min(1, (now - started) / length);
                    counter.textContent = f >= 1 ? money(units) : (total * (1 - Math.pow(1 - f, 3))).toFixed(2);
                    if (f < 1 && el.isConnected) requestAnimationFrame(tick);
                };
                requestAnimationFrame(tick);
                confetti();
            },
        });
    }

    function confetti() {
        const pool = Object.values(theme.symbols).map((s) => s.img).filter(Boolean);
        for (let i = 0; i < 26; i++) {
            const img = document.createElement('img');
            img.className = 'confetti';
            img.src = pool[i % pool.length];
            img.style.left = `${Math.random() * 100}%`;
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
    function dialog(html, { button = null, cancel = null, autoMs = 0, onOpen = null, cls = '' } = {}) {
        return new Promise((resolve) => {
            const layer = $('overlay');
            layer.innerHTML = `<div class="dialog ${cls}" role="dialog" aria-modal="true">${html}
                ${button || cancel ? `<div class="actions">${cancel ? `<button class="ghost" data-v="0">${cancel}</button>` : ''}${button ? `<button class="primary" data-v="1">${button}</button>` : ''}</div>` : ''}</div>`;
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
            onOpen?.(layer.firstElementChild);
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
        audio();
        controls();
        showWin(0n);
        if (state.balance !== null) $('balance').textContent = money(toUnits(state.balance) - toUnits(amount));

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
            if (result.player_balance) state.balance = result.player_balance;
            await playOutcome(plan(result.outcome), true);
            showWin(toUnits(result.payout_amount ?? '0') ?? 0n);
        }

        state.busy = false;
        state.quick = false;
        balance();
        controls();
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
        })[code] ?? `The bet was not placed (${code || 'unknown error'}).`;
    }

    function stopAuto() {
        state.auto = 0;
        controls();
    }

    // ---- The bar ----------------------------------------------------------------------------------

    function balance() {
        $('balance').textContent = state.balance === null ? '—' : money(state.balance);
        document.querySelectorAll('[data-currency]').forEach((e) => { e.textContent = state.currency; });
    }

    function controls() {
        const connected = bridge.parentOrigin !== null;
        const i = state.steps.indexOf(state.bet);
        const mode = state.ante ? 'ante' : 'base';
        $('bet').textContent = money(stake(state.bet, mode));
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
        $('buy-cost').textContent = money(buyCost);
        $('buy-box').hidden = !features.buy;
        $('buy-btn').disabled = !connected || state.busy || state.ante || !affordable(state.bet, 'buy', state.limits);
        $('buy-btn').title = state.ante ? 'Switch the ante bet off to buy the feature.' : (!affordable(state.bet, 'buy', state.limits) ? 'Over the maximum stake at this bet.' : '');
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
        const ok = await dialog(`<p class="kicker">Buy free spins</p><div class="huge">10</div><p class="kicker">FREE SPINS</p>
            <p class="note">for <strong>${money(cost)} ${state.currency}</strong> (100× your bet of ${money(state.bet)})</p>`, { button: 'Buy', cancel: 'Cancel' });
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
        const amount = (x) => money((bet * BigInt(Math.round(Number(x) * 100))) / 100n);
        const rows = (r.paytable ?? []).map((p) => `<tr><td><img src="${theme.symbols[p.symbol]?.img}" alt="${p.name}"></td>
            ${Object.values(p.pays).map((x) => `<td>${amount(x)}</td>`).join('')}</tr>`).join('');
        const tiers = Object.keys(r.paytable?.[0]?.pays ?? {});
        const scatter = Object.entries(r.scatter_pays ?? {}).map(([n, x]) => `${n}× → ${amount(x)}`).join(' · ');
        const f = state.fairness;
        dialog(`<h2>Paytable</h2><p class="note">Pays at your bet of ${money(state.bet)} ${state.currency}. Symbols pay anywhere.</p>
            <table class="pays"><thead><tr><th></th>${tiers.map((t) => `<th>${t}</th>`).join('')}</tr></thead><tbody>${rows}</tbody></table>
            <p><img class="inline" src="${theme.symbols.S?.img}" alt=""> <strong>Scatter</strong>: ${scatter}. 4 or more anywhere award ${r.free_spins?.awarded} free spins; ${r.free_spins?.retrigger_scatters} or more in free spins add ${r.free_spins?.retrigger_awarded}.</p>
            <h3>How it plays</h3>
            <ul><li>8 or more of a symbol anywhere on the ${r.grid?.columns}×${r.grid?.rows} grid win.</li>
            <li>Winning symbols burst, the rest fall, and new ones drop in; this repeats while there are wins.</li>
            <li>In free spins, sugar stars (2× to 100×) land on the grid. When a free spin's tumbles end with a win, the stars' values add up and multiply it.</li>
            ${features.ante ? '<li>Ante bet: your stake is 1.25× the bet and free spins come about twice as often.</li>' : ''}
            ${features.buy ? '<li>Buy free spins: 100× the bet starts the feature at once.</li>' : ''}
            <li>Maximum win: ${Number(r.max_win).toLocaleString()}× the bet; the round ends when it is reached.</li>
            <li>Return to player: ${(Number(r.rtp) * 100).toFixed(2)}%.</li>
            ${state.limits?.max_win ? `<li>Your casino pays at most ${money(state.limits.max_win)} ${state.currency} per bet.</li>` : ''}</ul>
            <h3>Provably fair</h3>
            <p class="note">Every spin, tumble and free spin is drawn from your seed pair before anything moves. Rotate your seeds to reveal the server seed and verify any bet at ${cfg.verifyUrl}.</p>
            ${f ? `<p class="mono">Last bet: server seed hash ${f.server_seed_hash} · client seed ${f.client_seed} · nonce ${f.nonce}</p>` : ''}`,
        { button: 'Close', cls: 'wide' });
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
                $('status').hidden = true;
                balance();
                controls();
            } else if (type === 'balance_update') {
                state.balance = payload.balance ?? state.balance;
                if (!state.busy) balance();
            } else if (type === 'bet_placed_result') {
                pending?.(payload);
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
    board.addEventListener('click', () => { if (state.busy) state.quick = true; });
    document.addEventListener('keydown', (e) => {
        if (e.code === 'Space' && $('overlay').hidden && !e.repeat && e.target === document.body) {
            e.preventDefault();
            spin();
        }
    });

    state.turbo = readPref('turbo', false);
    $('sound-btn').setAttribute('aria-pressed', String(sound.on));
    render(state.cells);
    balance();
    controls();

    if (!cfg.embedded) {
        $('status').textContent = `Open ${cfg.gameName} from your casino to play.`;
    } else {
        bridge.ready();
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
