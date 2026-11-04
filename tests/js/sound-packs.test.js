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
        const n = this.node({ start: () => this.started.push(n), stop: () => { n.stopped = true; }, ...extra });
        return n;
    }

    createGain() { return this.node({ gain: param(1) }); }

    createOscillator() { return this.source({ type: 'sine', frequency: param(440), detune: param(0) }); }

    createBufferSource() { return this.source({ buffer: null, loop: false }); }

    createBiquadFilter() { return this.node({ type: 'lowpass', frequency: param(350), Q: param(1), gain: param(0) }); }

    createConvolver() { return this.node({ buffer: null }); }

    createDynamicsCompressor() {
        return this.node({ threshold: param(-24), knee: param(30), ratio: param(12), attack: param(0.003), release: param(0.25) });
    }

    createBuffer(channels, length) {
        const data = Array.from({ length: channels }, () => new Float32Array(length));
        return { getChannelData: (c) => data[c] };
    }
}

let ctx;

beforeAll(async () => {
    await import('../../public/js/orange/sound-gothic.js');
    await import('../../public/js/orange/sound-jackpot.js');
});

beforeEach(() => {
    vi.useFakeTimers();
    ctx = new FakeAudioContext();
});

afterEach(() => {
    vi.useRealTimers();
});

// Each illustrated theme's pack: Gothic Nightmare ("burton") and Jackpot Reef ("jackpot").
describe.each(['gothic', 'jackpot'])('%s sound pack', (name) => {
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
