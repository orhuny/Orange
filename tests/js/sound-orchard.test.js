import { afterEach, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';

// The slot's own pack, in its own file so it doesn't collide with the crash packs' test.

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
        return { getChannelData: (c) => data[c], duration: length / 8000 };
    }
}

let ctx;
let pack;

beforeAll(async () => {
    await import('../../public/js/orange/sound-orchard.js');
});

beforeEach(() => {
    vi.useFakeTimers();
    ctx = new FakeAudioContext();
    pack = window.OrangeSound.orchard(ctx, ctx.createGain());
});

afterEach(() => {
    vi.useRealTimers();
});

describe('orchard sound pack', () => {
    it.each(['spin', 'land', 'win', 'buzz', 'pop','scatter', 'star', 'stars', 'retrigger', 'fsStart', 'fsEnd', 'big', 'tick', 'click', 'intro'])('plays the %s effect', (name) => {
        pack.sfx(name, 2);

        expect(ctx.started.length).toBeGreaterThan(0);
    });

    it('ignores effects it does not know', () => {
        pack.sfx('nope');

        expect(ctx.started).toHaveLength(0);
    });

    it('keeps the band playing, busier in free spins, until it is stopped', () => {
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
