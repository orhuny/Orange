import { describe, expect, it } from 'vitest';
import {
    affordable, cellsOf, costsFrom, formatX, fromUnits, hundredths, money, parseGrid, plan, playableSteps, stake, starTier, toUnits, tumble, winAmount, winTier,
} from '../../public/js/orange/slot-timeline.js';

// A real outcome from the server (seed orange-slot-seed-0005 / player-seed, nonce 1, base):
// three tumbles worth 7.85× in all (tests/Feature/Game/SlotPlayTest.php).
const GRID = 'AABCDEAFGAIBACDEFAGHIBCADEFGHA';

describe('tumble', () => {
    it('removes the winners, drops the rest and refills each column from the top', () => {
        const cells = parseGrid(GRID);
        const step = tumble(cells, ['A'], ['IH', 'BC', 'E', 'D', 'F', 'G']);

        expect(step.cells.map((c) => c.s).join('')).toBe('IHBCDBCEFGEIBCDDEFGHFIBCDGEFGH');
        expect(step.removed).toEqual([0, 1, 6, 9, 12, 17, 23, 29]);
        // Column 0 lost its top two: B, C, D stay put; column 1 lost rows 1 and 4.
        expect(step.moves).toContainEqual({ from: 5, to: 7 });
        expect(step.moves).toContainEqual({ from: 8, to: 9 });
        expect(step.moves.some((m) => m.from === 2)).toBe(false);
        expect(step.added.filter((a) => a.to < 5)).toEqual([
            { to: 0, s: 'I', v: 0, from: -2 },
            { to: 1, s: 'H', v: 0, from: -1 },
        ]);
    });

    it('carries multiplier values: kept ones fall with their star, new ones take theirs in order', () => {
        const cells = parseGrid('IIIIM' + 'ABCDI' + 'IIIME' + 'FGHAB' + 'CDEFG' + 'HABCD', [2, 5]);
        const step = tumble(cells, ['I'], ['EFGH', 'A', 'BCM', '', '', ''], [10]);

        expect(step.cells[4]).toEqual({ s: 'M', v: 2 });
        expect(step.cells.filter((c) => c.s === 'M').map((c) => c.v)).toEqual([2, 10, 5]);
    });

    it('refuses a fill that does not match the gaps', () => {
        expect(() => tumble(parseGrid(GRID), ['A'], ['I', '', '', '', '', ''])).toThrow();
    });
});

describe('plan', () => {
    const outcome = {
        mode: 'buy',
        spins: [
            { grid: 'SSSS' + 'ABCDEFGHI'.repeat(3).slice(0, 26), mults: [], tumbles: [], scatter_win_x: '2.50', win_x: '2.50', free_spins_awarded: 10 },
            {
                grid: 'IIIIM' + 'ABCDI' + 'IIIME' + 'FGHAB' + 'CDEFG' + 'HABCD', mults: [2, 5],
                tumbles: [{ wins: [{ symbol: 'I', count: 8, win_x: '0.65' }], fill: ['EFGH', 'A', 'BCD', '', '', ''], fill_mults: [] }],
                multiplier: 7, win_x: '4.55', retrigger: 0,
            },
        ],
        total_win_x: '7.05',
        free_spins: 1,
        max_win_reached: false,
    };

    it('lays out every spin: steps, scatters, multipliers and running totals', () => {
        const p = plan(outcome);

        expect(p.mode).toBe('buy');
        expect(p.total).toBe(705);
        expect(p.spins).toHaveLength(2);
        expect(p.spins[0]).toMatchObject({ free: false, scatters: [0, 1, 2, 3], scatterWin: 250, awarded: 10, win: 250, runningTotal: 250 });
        expect(p.spins[1]).toMatchObject({ free: true, lineWin: 65, multiplier: 7, win: 455, runningTotal: 705 });
        expect(p.spins[1].steps[0].pays).toBe(65);
        expect(p.spins[1].multiplierCells).toEqual(cellsOf(p.spins[1].end, 'M'));
        expect(p.spins[1].tide).toBeNull();
    });

    it('carries a base spin\'s multiplier and each free spin\'s tide (Trident of Poseidon)', () => {
        const p = plan({
            mode: 'base',
            spins: [
                {
                    grid: 'IIIIM' + 'IIIIM' + 'SSSSA' + 'BCDEF' + 'GHABC' + 'DEFGH', mults: [5, 3],
                    tumbles: [{ wins: [{ symbol: 'I', count: 8, win_x: '0.35' }], fill: ['ABCD', 'EFGH', '', '', '', ''], fill_mults: [] }],
                    multiplier: 8, scatter_win_x: '4.00', win_x: '6.80', free_spins_awarded: 15,
                },
                { grid: 'ABCDEFGHI'.repeat(4).slice(0, 30), mults: [], tumbles: [], multiplier: 0, tide: 5, win_x: '0.00', retrigger: 0 },
            ],
            total_win_x: '6.80',
            free_spins: 1,
            max_win_reached: false,
        });

        expect(p.spins[0]).toMatchObject({ free: false, lineWin: 35, multiplier: 8, tide: null, win: 680 });
        expect(p.spins[1]).toMatchObject({ free: true, multiplier: 0, tide: 5 });
    });
});

describe('money', () => {
    it('converts exactly', () => {
        expect(hundredths('7.85')).toBe(785);
        expect(hundredths('12')).toBe(1200);
        expect(formatX(5)).toBe('0.05');
        expect(toUnits('2.5')).toBe(250_000_000n);
        expect(fromUnits(12_500_000n)).toBe('0.125');
        expect(fromUnits(250_000_000n)).toBe('2.50');
        expect(money('701.14000000')).toBe('701.14');
        expect(money('10')).toBe('10.00');
        expect(money('0.199')).toBe('0.19');
    });

    it('prices each mode and pays wins on the bet', () => {
        expect(stake('2.00', 'base')).toBe('2.00');
        expect(stake('2.00', 'ante')).toBe('2.50');
        expect(stake('0.10', 'ante')).toBe('0.125');
        expect(stake('1.00', 'buy')).toBe('100.00');
        expect(money(winAmount('2.00', 785))).toBe('15.70');
        expect(money(winAmount('0.30', 65))).toBe('0.19');
    });

    it('keeps the bet steps inside the limits and knows when a buy is over the max bet', () => {
        const steps = ['0.20', '0.40', '1.00', '5.00', '100.00'];
        expect(playableSteps(steps, { min_bet: '0.40', max_bet: '50' })).toEqual(['0.40', '1.00', '5.00']);
        expect(playableSteps(steps, { min_bet: '500' })).toEqual(['0.20']);
        expect(playableSteps(steps, null)).toEqual(steps);
        expect(affordable('1.00', 'buy', { max_bet: '100' })).toBe(true);
        expect(affordable('2.00', 'buy', { max_bet: '100' })).toBe(false);
        expect(affordable('2.00', 'buy', {})).toBe(true);
    });

    it('prices a super buy from the published costs of the game', () => {
        const costs = costsFrom({ base: '1.00', ante: '1.25', buy: '100.00', super: '179.00' });

        expect(costs.super).toBe(17_900n);
        expect(costs.buy).toBe(10_000n);
        expect(stake('0.20', 'super', costs)).toBe('35.80');
        expect(stake('1.00', 'super', costs)).toBe('179.00');
        expect(affordable('1.00', 'super', { max_bet: '150' }, costs)).toBe(false);
        expect(affordable('0.80', 'super', { max_bet: '150' }, costs)).toBe(true);
        // Nothing published (an old page's rules): the fixed costs.
        expect(costsFrom(undefined).buy).toBe(10_000n);
        expect(costsFrom({}).super).toBeUndefined();
    });

    it('knows where the tide of a super buy starts', () => {
        const outcome = { mode: 'super', tide_start: 10, spins: [], total_win_x: '0.00', free_spins: 0, max_win_reached: false };

        expect(plan(outcome).tideStart).toBe(10);
        expect(plan({ ...outcome, mode: 'buy', tide_start: undefined }).tideStart).toBeNull();
    });
});

describe('celebrations', () => {
    it('picks the win tier and the star colour', () => {
        // MAX WIN comes from the outcome's max_win_reached, never from the amount: the cap is per game.
        expect([1499, 1500, 4000, 10_000, 500_000].map((t) => winTier(t))).toEqual(['none', 'big', 'mega', 'epic', 'epic']);
        expect([2, 8, 20, 100].map((v) => starTier(v))).toEqual(['sky', 'emerald', 'gold', 'ruby']);
        const pearls = [[100, 'abyss'], [20, 'gold'], [6, 'aqua'], [0, 'moon']];
        expect([2, 5, 6, 15, 20, 50, 100, 500].map((v) => starTier(v, pearls))).toEqual(['moon', 'moon', 'aqua', 'aqua', 'gold', 'gold', 'abyss', 'abyss']);
    });
});
