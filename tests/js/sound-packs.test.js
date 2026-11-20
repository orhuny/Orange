import { afterEach, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';

/** Just enough of the Web Audio API to run the pack (jsdom has none). */
function param(value = 0) {
    const p = { value };
    for (const m of ['setValueAtTime', 'exponentialRampToValueAtTime', 'linearRampToValueAtTime', 'setTargetAtTime', 'cancelScheduledValues']) {
        p[m] = () => p;
    }
    return p;
}

class FakeAudioContext {
    constructor() {
        this.currentTime = 0;
        this.sampleRate = 8000;
        this.started = [];
        this.destination = this.node();
    }

    node(extra = {}) {
        return { connect: (dest) => dest, disconnect() {}, ...extra };
    }

    source(extra) {
        const n = this.node({
            start: (when = 0) => { n.startAt = when; this.started.push(n); },
            stop: (when = 0) => { n.stopped = true; n.stopAt = when; },
            ...extra,
        });
        return n;
    }

    createGain() { return this.node({ gain: param(1) }); }

    createOscillator() { return this.source({ type: 'sine', frequency: param(440), detune: param(0) }); }

    createBufferSource() { return this.source({ buffer: null, loop: false }); }

    createBiquadFilter() { return this.node({ type: 'lowpass', frequency: param(350), Q: param(1), gain: param(0) }); }

    createConvolver() { return this.node({ buffer: null }); }

    createWaveShaper() { return this.node({ curve: null, oversample: 'none' }); }

    createDelay() { return this.node({ delayTime: param(0) }); }

    createDynamicsCompressor() {
        return this.node({ threshold: param(-24), knee: param(30), ratio: param(12), attack: param(0.003), release: param(0.25) });
    }

    createBuffer(channels, length, sampleRate = this.sampleRate) {
        const data = Array.from({ length: channels }, () => new Float32Array(length));
        return { getChannelData: (c) => data[c], duration: length / sampleRate };
    }
}

let ctx;

beforeAll(async () => {
    await import('../../public/js/orange/sound-gothic.js');
    await import('../../public/js/orange/sound-jackpot.js');
    await import('../../public/js/orange/sound-marsh.js');
    await import('../../public/js/orange/sound-orchard.js');
    await import('../../public/js/orange/sound-tides.js');
});

beforeEach(() => {
    vi.useFakeTimers();
    ctx = new FakeAudioContext();
});

afterEach(() => {
    vi.useRealTimers();
});

// Each illustrated skin's pack: Gothic Nightmare ("burton"), Jackpot Reef ("jackpot") and
// Lucky Duck's marsh ("luckyduck").
describe.each(['gothic', 'jackpot', 'marsh'])('%s sound pack', (name) => {
    let pack;

    beforeEach(() => {
        pack = window.OrangeSound[name](ctx, ctx.createGain());
    });

    it.each(['tick', 'bet', 'start', 'win', 'loss', 'crash'])('plays the %s effect', (name) => {
        pack.sfx(name);

        expect(ctx.started.length).toBeGreaterThan(0);
    });

    it('ignores effects it does not know', () => {
        pack.sfx('nope');

        expect(ctx.started).toHaveLength(0);
    });

    it('keeps a background playing, scheduled ahead, until it is stopped', () => {
        const background = pack.ambience();
        const atStart = ctx.started.length;

        ctx.currentTime = 10;
        vi.advanceTimersByTime(1000);
        const afterTenSeconds = ctx.started.length;
        expect(afterTenSeconds).toBeGreaterThan(atStart); // the waltz kept going
        // Notes end themselves; the pad, wind and their modulators run on (the continuous bed).
        expect(ctx.started.some((n) => !n.stopped)).toBe(true);

        background.stop();
        ctx.currentTime = 20;
        vi.advanceTimersByTime(1000);

        expect(ctx.started).toHaveLength(afterTenSeconds); // nothing new after stop()
        expect(ctx.started.every((n) => n.stopped)).toBe(true);
    });
});

describe('jackpot music', () => {
    it('builds as the dive deepens and calms down again', () => {
        const background = window.OrangeSound.jackpot(ctx, ctx.createGain()).ambience();
        const notesIn = (seconds) => {
            const before = ctx.started.length;
            ctx.currentTime += seconds;
            vi.advanceTimersByTime(seconds * 1000);
            return ctx.started.length - before;
        };

        notesIn(2);
        const betting = notesIn(4);
        background.intensity(3);
        notesIn(2);
        const abyss = notesIn(4);
        background.intensity(0);
        notesIn(2);
        const calmAgain = notesIn(4);

        expect(abyss).toBeGreaterThan(betting * 1.5); // drums, arpeggio, faster tempo
        expect(calmAgain).toBeLessThan(abyss);
        background.stop();
    });
});

describe('marsh effects', () => {
    it('plays a missed shot while Lucky flies', () => {
        window.OrangeSound.marsh(ctx, ctx.createGain()).sfx('shot');

        expect(ctx.started.length).toBeGreaterThan(0);
    });
});

describe('marsh music', () => {
    it('builds as Lucky flies higher and calms down again', () => {
        const background = window.OrangeSound.marsh(ctx, ctx.createGain()).ambience();
        const notesIn = (seconds) => {
            const before = ctx.started.length;
            ctx.currentTime += seconds;
            vi.advanceTimersByTime(seconds * 1000);
            return ctx.started.length - before;
        };

        notesIn(2);
        const betting = notesIn(4);
        background.intensity(3);
        notesIn(2);
        const dusk = notesIn(4);
        background.intensity(0);
        notesIn(2);
        const calmAgain = notesIn(4);

        expect(dusk).toBeGreaterThan(betting * 1.5); // the full roll, washboard, the whistled tune, faster
        expect(calmAgain).toBeLessThan(dusk);
        background.stop();
    });
});

// The slot skins' packs: Sugar Orchard ("orchard") and Trident of Poseidon ("tides"). The slot page
// plays the same effects on either, so both answer to the same calls.
const SLOT_EFFECTS = ['spin', 'land', 'win', 'buzz', 'flame', 'pop', 'scatter', 'star', 'stars', 'multiply', 'retrigger',
    'fsStart', 'fsEnd', 'big', 'tick', 'bigEnd', 'click', 'intro'];

describe.each(['orchard', 'tides'])('%s slot sound pack', (packName) => {
    let pack;

    beforeEach(() => {
        pack = window.OrangeSound[packName](ctx, ctx.createGain());
    });

    /** How many voices (oscillators and noise bursts) an effect starts. */
    const voices = (name, arg) => {
        ctx.started = [];
        pack.sfx(name, arg);
        return ctx.started.length;
    };

    it.each(SLOT_EFFECTS)('plays the %s effect', (name) => {
        pack.sfx(name, 2);

        expect(ctx.started.length).toBeGreaterThan(0);
    });

    it('plays every effect whatever the page passes it', () => {
        const args = [undefined, 0, 7, 0.5, 'max', null, {}, { chain: 9, x: 50, lead: 0.1 }];

        for (const name of SLOT_EFFECTS) {
            for (const arg of args) expect(() => pack.sfx(name, arg)).not.toThrow();
        }
    });

    it.each(['win', 'pop'])('celebrates a bigger win with more on %s', (name) => {
        const counts = [0.5, 1, 2, 5, 10].map((x) => voices(name, { chain: 1, x, lead: 1.2 }));

        for (let i = 1; i < counts.length; i++) expect(counts[i]).toBeGreaterThan(counts[i - 1]);
    });

    it('celebrates a bigger multiplier and each big-win tier with more', () => {
        expect(voices('multiply', 10)).toBeGreaterThan(voices('multiply', 3));
        expect(voices('multiply', 25)).toBeGreaterThan(voices('multiply', 10));
        const tiers = ['big', 'mega', 'epic', 'max'].map((tier) => voices('big', tier));
        for (let i = 1; i < tiers.length; i++) expect(tiers[i]).toBeGreaterThan(tiers[i - 1]);
    });

    it('keeps the spin short and light: about half a second, lighter than any win', () => {
        ctx.currentTime = 3;
        const spin = voices('spin');
        const ends = ctx.started.map((n) => n.stopAt - ctx.currentTime);

        expect(Math.max(...ends)).toBeLessThanOrEqual(0.65);
        expect(spin).toBeLessThan(voices('win', { chain: 0, x: 0.5, lead: 1 }));
    });

    it('ignores effects it does not know', () => {
        pack.sfx('nope');

        expect(ctx.started).toHaveLength(0);
    });

    it('keeps the background playing, busier in free spins, until it is stopped', () => {
        const background = pack.ambience();
        const notesIn = (seconds) => {
            const before = ctx.started.length;
            ctx.currentTime += seconds;
            vi.advanceTimersByTime(seconds * 1000);
            return ctx.started.length - before;
        };

        notesIn(2);
        const base = notesIn(4);
        background.intensity(1);
        notesIn(2);
        const free = notesIn(4);
        expect(base).toBeGreaterThan(0);
        expect(free).toBeGreaterThan(base * 1.2);

        background.stop();
        const after = ctx.started.length;
        notesIn(2);
        expect(ctx.started).toHaveLength(after);
        expect(ctx.started.every((n) => n.stopped)).toBe(true);
    });
});
