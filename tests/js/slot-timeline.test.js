import { describe, expect, it } from 'vitest';
import {
    affordable, cellsOf, formatX, fromUnits, hundredths, money, parseGrid, plan, playableSteps, stake, starTier, toUnits, tumble, winAmount, winTier,
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
});

describe('celebrations', () => {
    it('picks the win tier and the star colour', () => {
        expect([1499, 1500, 4000, 10_000, 500_000].map(winTier)).toEqual(['none', 'big', 'mega', 'epic', 'max']);
        expect([2, 8, 20, 100].map(starTier)).toEqual(['sky', 'emerald', 'gold', 'ruby']);
    });
});
