/**
 * The slot page's pure logic: turns a bet's outcome (SlotEngine, see SpinResolver for the
 * shape) into the steps the board animates, and does the page's money arithmetic exactly.
 * No DOM here, so Vitest covers it (tests/js/slot-timeline.test.js).
 *
 * Cells are numbered column by column, row 0 at the top: cell = column × rows + row, as on
 * the server. A plain ES module (no build step).
 */

export const COLS = 6;
export const ROWS = 5;
export const SCATTER = 'S';
export const MULTIPLIER = 'M';

/** "12.50" → 1250 (hundredths). */
export function hundredths(x) {
    const m = /^(\d+)(?:\.(\d{1,2}))?$/.exec(String(x ?? '0').trim());
    if (!m) return 0;
    return Number(m[1]) * 100 + Number(((m[2] || '') + '00').slice(0, 2));
}

/** 1250 → "12.50". */
export function formatX(h) {
    return `${Math.floor(h / 100)}.${String(h % 100).padStart(2, '0')}`;
}

/** A grid string and its multiplier values → cells [{ s, v }]. */
export function parseGrid(grid, mults = []) {
    let next = 0;
    return [...grid].map((s) => ({ s, v: s === MULTIPLIER ? (mults[next++] ?? 0) : 0 }));
}

/**
 * Applies one tumble: the winning symbols go, the rest fall, the gaps refill from the top.
 * Returns the new cells and what moved: `removed` (cells that pop), `moves` (survivors,
 * from → to), `added` (new cells: where they land and how many rows above the board they
 * start, so a column's new cells fall in together).
 */
export function tumble(cells, winning, fill, fillMults = []) {
    const gone = new Set(winning);
    const removed = [];
    const moves = [];
    const added = [];
    const next = new Array(cells.length);
    let multIndex = 0;

    for (let col = 0; col < COLS; col++) {
        const kept = [];
        for (let row = 0; row < ROWS; row++) {
            const cell = col * ROWS + row;
            if (gone.has(cells[cell].s)) removed.push(cell);
            else kept.push(cell);
        }
        const fresh = [...(fill[col] ?? '')];
        if (fresh.length !== ROWS - kept.length) {
            throw new Error(`Column ${col}: ${ROWS - kept.length} gaps but ${fresh.length} new symbols.`);
        }
        fresh.forEach((s, i) => {
            const to = col * ROWS + i;
            const v = s === MULTIPLIER ? (fillMults[multIndex++] ?? 0) : 0;
            next[to] = { s, v };
            added.push({ to, s, v, from: i - fresh.length });
        });
        kept.forEach((from, i) => {
            const to = col * ROWS + fresh.length + i;
            next[to] = cells[from];
            if (from !== to) moves.push({ from, to });
        });
    }

    return { cells: next, removed, moves, added };
}

/** Indices of the cells holding a symbol. */
export function cellsOf(cells, symbol) {
    return cells.reduce((out, c, i) => (c.s === symbol ? [...out, i] : out), []);
}

/**
 * The whole animation plan of a bet. Amounts stay in hundredths of the bet.
 *
 * @returns {{ mode: string, spins: object[], total: number, freeSpins: number, maxWin: boolean }}
 */
export function plan(outcome) {
    let running = 0;
    const spins = (outcome.spins ?? []).map((spin, index) => {
        let cells = parseGrid(spin.grid, spin.mults);
        const start = cells;
        let spinLine = 0;
        const steps = (spin.tumbles ?? []).map((t) => {
            const pays = t.wins.reduce((sum, w) => sum + hundredths(w.win_x), 0);
            spinLine += pays;
            const step = tumble(cells, t.wins.map((w) => w.symbol), t.fill, t.fill_mults);
            const out = { wins: t.wins, pays, before: cells, ...step };
            cells = step.cells;
            return out;
        });
        const free = index > 0;
        const win = hundredths(spin.win_x);
        running += win;
        return {
            index,
            free,
            start,
            steps,
            end: cells,
            lineWin: spinLine,
            scatters: cellsOf(cells, SCATTER),
            scatterWin: hundredths(spin.scatter_win_x ?? '0'),
            // What multiplied the spin's line win: its multiplier sum (free spins, or the base game of a
            // model with base multipliers); with a tide, the tide after this spin (null without one).
            multiplier: spin.multiplier ?? 0,
            tide: spin.tide ?? null,
            multiplierCells: cellsOf(cells, MULTIPLIER),
            win,
            runningTotal: running,
            awarded: spin.free_spins_awarded ?? 0,
            retrigger: spin.retrigger ?? 0,
        };
    });

    return {
        mode: outcome.mode ?? 'base',
        spins,
        total: hundredths(outcome.total_win_x),
        freeSpins: outcome.free_spins ?? 0,
        maxWin: outcome.max_win_reached === true,
    };
}

/** How a total win (hundredths of the bet) is celebrated. */
export function winTier(total) {
    // MAX WIN is only for a bet that hit its cap (plan().maxWin): the cap is per game.
    if (total >= 10_000) return 'epic';
    if (total >= 4_000) return 'mega';
    if (total >= 1_500) return 'big';
    return 'none';
}

// ---- Money: exact decimals as BigInt in 1e-8 units -----------------------------------------

const SCALE = 100_000_000n;

/** "2.5" → 250000000n; anything else → null. */
export function toUnits(value) {
    const m = /^(\d+)(?:\.(\d{0,8}))?$/.exec(String(value ?? '').trim());
    if (!m) return null;
    return BigInt(m[1]) * SCALE + BigInt(((m[2] || '') + '00000000').slice(0, 8));
}

/** Units → a decimal string with at least 2 and at most 8 decimals ("2.50", "0.125"). */
export function fromUnits(units) {
    const whole = units / SCALE;
    let frac = String(units % SCALE).padStart(8, '0').replace(/0+$/, '');
    if (frac.length < 2) frac = frac.padEnd(2, '0');
    return `${whole}.${frac}`;
}

/** Money for display: two decimals, cut not rounded ("701.14000000" → "701.14"). */
export function money(value) {
    const units = typeof value === 'bigint' ? value : toUnits(value);
    if (units === null) return value === null || value === undefined ? '' : String(value);
    return `${units / SCALE}.${String((units % SCALE) / 1_000_000n).padStart(2, '0')}`;
}

/** What a mode's stake costs for a bet: base 1×, ante 1.25×, buy 100×. */
export const COST = Object.freeze({ base: 100n, ante: 125n, buy: 10_000n });

export function stake(bet, mode) {
    return fromUnits((toUnits(bet) * COST[mode]) / 100n);
}

/** A win in hundredths of the bet, as money for that bet. */
export function winAmount(bet, h) {
    return (toUnits(bet) * BigInt(h)) / 100n;
}

/**
 * The bet steps a player may pick: within the limits for the normal spin (min_bet ≤ bet,
 * bet ≤ max_bet), keeping the configured order. Limits are decimal strings or null.
 */
export function playableSteps(steps, limits = {}) {
    const min = limits?.min_bet ? toUnits(limits.min_bet) : null;
    const max = limits?.max_bet ? toUnits(limits.max_bet) : null;
    const ok = steps.filter((s) => {
        const u = toUnits(s);
        return u !== null && (min === null || u >= min) && (max === null || u <= max);
    });
    return ok.length ? ok : steps.slice(0, 1);
}

/** Whether a mode's stake for this bet fits under the max bet. */
export function affordable(bet, mode, limits = {}) {
    const max = limits?.max_bet ? toUnits(limits.max_bet) : null;
    return max === null || toUnits(stake(bet, mode)) <= max;
}

/** Sugar Orchard's star colours: [from value, tier], highest first. */
export const STAR_TIERS = Object.freeze([[100, 'ruby'], [20, 'gold'], [8, 'emerald'], [0, 'sky']]);

/** A multiplier's colour tier for its value; a theme may give its own tiers (theme.tiers). */
export function starTier(value, tiers = STAR_TIERS) {
    return (tiers.find(([from]) => value >= from) ?? tiers[tiers.length - 1])[1];
}
