import { readFileSync } from 'node:fs';
import { beforeAll, describe, expect, it } from 'vitest';
import { COLS, ROWS, WILD, lineCells, parseGrid, plan, setGrid } from '../../public/js/orange/slot-timeline.js';

// Tutti Frutti, a paylines game: 5 × 3 reels and these 20 lines (TuttiFrutti::LINES, rules.lines).
const LINES = [
    [1, 1, 1, 1, 1], [0, 0, 0, 0, 0], [2, 2, 2, 2, 2], [0, 1, 2, 1, 0], [2, 1, 0, 1, 2],
    [0, 0, 1, 2, 2], [2, 2, 1, 0, 0], [1, 0, 0, 0, 1], [1, 2, 2, 2, 1], [0, 1, 1, 1, 0],
    [2, 1, 1, 1, 2], [1, 0, 1, 2, 1], [1, 2, 1, 0, 1], [0, 1, 0, 1, 0], [2, 1, 2, 1, 2],
    [1, 1, 0, 1, 1], [1, 1, 2, 1, 1], [0, 0, 2, 0, 0], [2, 2, 0, 2, 2], [0, 2, 2, 2, 0],
];
// Real outcomes, from the independent reference (tests/Fixtures/slot/tutti-frutti-golden.json).
const CASES = JSON.parse(readFileSync('tests/Fixtures/slot/tutti-frutti-golden.json', 'utf8'));
const hundredths = (x) => Math.round(Number(x) * 100);

beforeAll(() => setGrid(5, 3));

describe('a paylines game', () => {
    it('sets the grid from the rules, and ignores a nonsense one', () => {
        expect([COLS, ROWS]).toEqual([5, 3]);
        setGrid(0, 3);
        expect([COLS, ROWS]).toEqual([5, 3]);
    });

    it('reads each kiss\'s value off the grid, plain kisses as 1', () => {
        expect(parseGrid('AWBWC', [], [3, 1]).map((c) => c.v)).toEqual([0, 3, 0, 1, 0]);
    });

    it('knows the cells a line crosses and the run that pays', () => {
        expect(lineCells(LINES, 1, 3)).toEqual({ path: [1, 4, 7, 10, 13], run: [1, 4, 7] });
        expect(lineCells(LINES, 4, 5)).toEqual({ path: [0, 4, 8, 10, 12], run: [0, 4, 8, 10, 12] });
    });

    it('plans every paying line of a spin: its run on the grid, its multiplier and what it pays', () => {
        const withKiss = CASES.find((c) => c.outcome.spins[0].lines.some((l) => l.multiplier > 1));
        const spin = plan(withKiss.outcome, LINES).spins[0];
        const raw = withKiss.outcome.spins[0];

        expect(spin.steps).toEqual([]);
        expect(spin.lines.map((l) => l.line)).toEqual(raw.lines.map((l) => l.line));
        for (const l of spin.lines) {
            // The run starts with the line's symbol on reel 0, and the rest are that symbol or kisses.
            expect(spin.start[l.run[0]].s).toBe(l.symbol);
            expect(l.run.every((c) => [l.symbol, WILD].includes(spin.start[c].s))).toBe(true);
            expect(l.run).toHaveLength(l.count);
            expect(l.path).toHaveLength(5);
        }
        expect(spin.lineWin).toBe(spin.lines.reduce((sum, l) => sum + l.pays, 0));
        expect(spin.wildCells.every((c) => spin.start[c].s === WILD)).toBe(true);
        expect(spin.amore).toBeNull();
    });

    it('plans free spins with the amore, and the running total adds up to the bet\'s', () => {
        const bought = CASES.find((c) => c.mode === 'buy' && c.outcome.free_spins >= 10 && !c.cap);
        const p = plan(bought.outcome, LINES);

        expect(p.spins[0].awarded).toBeGreaterThanOrEqual(10);
        expect(p.spins.slice(1).every((s) => s.free && s.amore >= 1)).toBe(true);
        expect(p.spins.slice(1).map((s) => s.amore)).toEqual(bought.outcome.spins.slice(1).map((s) => s.amore));
        expect(p.spins[p.spins.length - 1].runningTotal).toBe(p.total);
        expect(p.total).toBe(hundredths(bought.outcome.total_win_x));
    });
});
