/**
 * "Stones of the Titan" sound pack for the slot: the Titan's realm in a golden nebula and, in
 * free spins, the Eclipse. Crystal bells and a slow harp-like shimmer over a vast, dark pad;
 * stone that cracks and shatters; a deep sub-boom when the Titan crushes his meteors; low war
 * drums and a brass call for the big moments; a choir rising for the Eclipse. All synthesised,
 * same shape as the other packs:
 *
 *   const pack = window.OrangeSound.cosmos(ctx, master);
 *   pack.sfx('pop');                    // spin | land | win | buzz | flame | pop | scatter | star | stars | crush |
 *                                       // multiply | retrigger | eclipse | fsStart | fsEnd | big | tick | bigEnd | click | intro
 *   pack.sfx('scatter', 3);             // the third scatter: each one rings higher
 *   pack.sfx('win', { chain: 1, x: 6, lead: 1.4 }); // a tumble paying 6× the bet, its shatter 1.4 s on:
 *                                       // the bigger the win, the more it celebrates ('pop' too)
 *   pack.sfx('big', 'mega');            // big | mega | epic | max, each bigger
 *   const bg = pack.ambience();         // the humming music, continuous until bg.stop()
 *   bg.intensity(1);                    // 0 the nebula · 1 free spins in the Eclipse
 *
 * ambience() is the background music: a man humming with echo (the theme turns it on: theme.php 'music').
 */
(function () {
    'use strict';

    const midi = (n) => 440 * Math.pow(2, (n - 69) / 12);
    const num = (v) => (Number.isFinite(Number(v)) ? Number(v) : 0);

    // Eight bars in D minor, wide and slow, like something vast turning in space:
    // Dm(add9) | B♭maj7 | Fsus2 | Csus4 | Dm(add9) | Gm7 | B♭maj7#11 | A7sus4.
    const BARS = [
        { chord: [62, 65, 69, 76], root: 38 },
        { chord: [58, 62, 65, 69], root: 34 },
        { chord: [60, 65, 67, 72], root: 41 },
        { chord: [60, 65, 67, 72], root: 36 },
        { chord: [62, 65, 69, 76], root: 38 },
        { chord: [58, 62, 65, 67], root: 43 },
        { chord: [58, 62, 64, 69], root: 34 },
        { chord: [57, 62, 64, 67], root: 33 },
    ];
    // The crystal tune in the Eclipse, one entry per eighth (0 = rest).
    const TUNE = [
        74, 0, 0, 77, 76, 0, 74, 0, 72, 0, 0, 69, 70, 0, 0, 0,
        74, 0, 77, 0, 81, 0, 79, 0, 77, 0, 0, 76, 74, 0, 0, 0,
        74, 0, 0, 77, 76, 0, 74, 0, 79, 0, 0, 77, 76, 0, 72, 0,
        74, 0, 76, 0, 77, 0, 76, 0, 73, 0, 0, 0, 74, 0, 0, 0,
    ];
    // The man's hum over the eight bars, [sixteenth it starts on, note, length in sixteenths]:
    // low and slow, sliding from note to note, with a breath at the end of each phrase.
    const HUM = [
        [0, 50, 12], [12, 53, 4],
        [16, 53, 8], [24, 50, 8],
        [32, 48, 12], [44, 45, 4],
        [48, 48, 10],
        [64, 50, 8], [72, 53, 4], [76, 55, 4],
        [80, 53, 8], [88, 50, 8],
        [96, 50, 10], [106, 48, 6],
        [112, 52, 8], [120, 45, 8],
    ];
    // Sixteenth length: ~80 bpm in the nebula, ~96 bpm in the Eclipse.
    const STEP = [0.19, 0.155];
    const LEVEL = { sfx: 2.0, ambience: 0.38 }; // the music sits about 10 dB under the effects (around -30 dB RMS)
    const OO = [330, 760, 2400];
    const AH = [730, 1150, 2650];
    const FORMANT = [[1, 5], [0.55, 7], [0.22, 9]];

    function cosmos(ctx, master) {
        let reverbIn = null;
        let effects = null;
        const nyquist = ctx.sampleRate / 2.2;

        const white = ctx.createBuffer(1, Math.floor(ctx.sampleRate * 2), ctx.sampleRate);
        {
            const w = white.getChannelData(0);
            for (let i = 0; i < w.length; i++) w[i] = Math.random() * 2 - 1;
        }

        /** Space: a long, dark tail (3 s of noise fading and closing slowly). */
        function reverb() {
            if (reverbIn) return reverbIn;
            const len = Math.floor(ctx.sampleRate * 3);
            const ir = ctx.createBuffer(2, len, ctx.sampleRate);
            for (let ch = 0; ch < 2; ch++) {
                const d = ir.getChannelData(ch);
                let lp = 0;
                for (let i = 0; i < len; i++) {
                    lp += (0.55 - 0.42 * (i / len)) * (Math.random() * 2 - 1 - lp);
                    d[i] = lp * Math.pow(1 - i / len, 2.6);
                }
            }
            reverbIn = ctx.createConvolver();
            reverbIn.buffer = ir;
            const ret = ctx.createGain();
            ret.gain.value = 0.42;
            reverbIn.connect(ret).connect(master);
            return reverbIn;
        }

        function out(node, bus, wet) {
            node.connect(bus.dry);
            if (wet > 0) {
                const send = ctx.createGain();
                send.gain.value = wet;
                node.connect(send).connect(bus.wet);
            }
        }

        function glue() {
            const c = ctx.createDynamicsCompressor();
            c.threshold.value = -18;
            c.knee.value = 6;
            c.ratio.value = 6;
            c.attack.value = 0.002;
            c.release.value = 0.2;
            return c;
        }

        function env(g, at, peak, attack, dur) {
            g.gain.setValueAtTime(0.0001, at);
            g.gain.exponentialRampToValueAtTime(peak, at + attack);
            g.gain.exponentialRampToValueAtTime(0.0001, at + dur);
        }

        function partial(freq, type, at, peak, attack, dur, dest) {
            if (freq >= nyquist) return;
            const o = ctx.createOscillator();
            const g = ctx.createGain();
            o.type = type;
            o.frequency.value = freq;
            env(g, at, peak, attack, dur);
            o.connect(g).connect(dest);
            o.start(at);
            o.stop(at + dur + 0.05);
        }

        /** An FM tone, bright as it is struck and mellowing as it rings; high notes bent less. */
        function fm(freq, ratio, index, at, peak, attack, dur, dest) {
            if (freq >= nyquist) return;
            const o = ctx.createOscillator();
            const g = ctx.createGain();
            o.type = 'sine';
            o.frequency.value = freq;
            env(g, at, peak, attack, dur);
            o.connect(g).connect(dest);
            o.start(at);
            o.stop(at + dur + 0.05);
            const bend = index * Math.min(1, 1400 / freq);
            if (bend > 0 && freq * ratio < nyquist) {
                const m = ctx.createOscillator();
                const depth = ctx.createGain();
                m.type = 'sine';
                m.frequency.value = freq * ratio;
                depth.gain.setValueAtTime(freq * bend, at);
                depth.gain.exponentialRampToValueAtTime(freq * bend * 0.06, at + dur * 0.5);
                m.connect(depth).connect(o.frequency);
                m.start(at);
                m.stop(at + dur + 0.05);
            }
        }

        function voice(bus, wet) {
            const g = ctx.createGain();
            out(g, bus, wet);
            return g;
        }

        function hiss({ at, bus, gain = 0.1, dur = 0.08, type = 'highpass', freq = 6000, to = null, q = 0.8, wet = 0.15, attack = Math.min(0.005, dur / 4) }) {
            const src = ctx.createBufferSource();
            const f = ctx.createBiquadFilter();
            const g = ctx.createGain();
            src.buffer = white;
            f.type = type;
            f.Q.value = q;
            f.frequency.setValueAtTime(freq, at);
            if (to) f.frequency.exponentialRampToValueAtTime(to, at + dur);
            env(g, at, gain, attack, dur);
            src.connect(f).connect(g);
            out(g, bus, wet);
            src.start(at, Math.random() * Math.max(0, white.duration - dur - 0.05));
            src.stop(at + dur + 0.02);
        }

        // ---- Instruments ----

        /** A crystal bell: an inharmonic FM ring with a glassy twin a hair sharp. */
        function crystal(freq, { at, bus, gain = 0.05, dur = 1.4, wet = 0.45 }) {
            const v = voice(bus, wet);
            fm(freq, 2.76, 1.1, at, gain, 0.002, dur, v);
            partial(freq * 1.004, 'sine', at, gain * 0.4, 0.002, dur * 0.9, v);
            partial(freq * 2.01, 'sine', at, gain * 0.15, 0.002, dur * 0.4, v);
        }

        /** A shimmer: a plucked, harp-like string, its overtones dying fast. */
        function shimmer(freq, { at, bus, gain = 0.06, dur = 1.1, wet = 0.35 }) {
            const v = voice(bus, wet);
            partial(freq, 'triangle', at, gain, 0.003, dur, v);
            partial(freq * 2, 'sine', at, gain * 0.35, 0.002, dur * 0.5, v);
            partial(freq * 3, 'sine', at, gain * 0.12, 0.002, dur * 0.25, v);
        }

        function run(notes, { at, bus, gap = 0.04, gain = 0.06, dur = 1.1 }) {
            notes.forEach((n, i) => shimmer(midi(n), { at: at + i * gap, bus, gain, dur }));
        }

        /** A soft crystal plink (harmonic, round): coins and the count-up. */
        function plink(freq, { at, bus, gain = 0.03, dur = 0.25, wet = 0.3 }) {
            fm(freq, 2, 0.5, at, gain, 0.003, dur, voice(bus, wet));
        }

        /** A vast, dark pad: detuned saws through a soft lowpass, swelling in slowly. */
        function pad(freqs, { at, bus, gain = 0.05, dur = 2.4, attack = dur * 0.4, wet = 0.55, bright = 750 }) {
            for (const f0 of freqs) {
                for (const cents of [-9, 9]) {
                    const o = ctx.createOscillator();
                    const f = ctx.createBiquadFilter();
                    const g = ctx.createGain();
                    o.type = 'sawtooth';
                    o.frequency.value = f0;
                    o.detune.value = cents;
                    f.type = 'lowpass';
                    f.Q.value = 0.5;
                    f.frequency.value = bright;
                    env(g, at, gain / freqs.length / 2, attack, dur);
                    o.connect(f).connect(g);
                    out(g, bus, wet);
                    o.start(at);
                    o.stop(at + dur + 0.05);
                }
            }
        }

        /** A choir: three detuned saws a note with vibrato, through formants opening "oo" to "ah". */
        function choir(freqs, { at, bus, gain = 0.06, dur = 2.4, attack = dur * 0.4, wet = 0.6 }) {
            const lfo = ctx.createOscillator();
            lfo.frequency.value = 4.6 + Math.random() * 0.8;
            lfo.start(at);
            lfo.stop(at + dur + 0.05);
            for (const f0 of freqs) {
                const mixer = ctx.createGain();
                const vib = ctx.createGain();
                const g = ctx.createGain();
                vib.gain.value = f0 * 0.005;
                lfo.connect(vib);
                for (const cents of [-12, 0, 12]) {
                    const o = ctx.createOscillator();
                    o.type = 'sawtooth';
                    o.frequency.value = f0;
                    o.detune.value = cents + (Math.random() - 0.5) * 4;
                    vib.connect(o.frequency);
                    o.connect(mixer);
                    o.start(at);
                    o.stop(at + dur + 0.05);
                }
                FORMANT.forEach(([level, q], i) => {
                    const f = ctx.createBiquadFilter();
                    const lv = ctx.createGain();
                    f.type = 'bandpass';
                    f.Q.value = q;
                    f.frequency.setValueAtTime(OO[i], at);
                    f.frequency.exponentialRampToValueAtTime(AH[i], at + Math.max(0.05, attack));
                    lv.gain.value = level;
                    mixer.connect(f).connect(lv).connect(g);
                });
                env(g, at, gain / freqs.length, attack, dur);
                out(g, bus, wet);
            }
        }

        /** Brass: a saw whose lowpass opens as it is blown, a heroic call. */
        function brass(freqs, { at, bus, gain = 0.06, dur = 0.9, wet = 0.35 }) {
            for (const f0 of freqs) {
                const o = ctx.createOscillator();
                const f = ctx.createBiquadFilter();
                const g = ctx.createGain();
                o.type = 'sawtooth';
                o.frequency.value = f0;
                f.type = 'lowpass';
                f.Q.value = 1.2;
                f.frequency.setValueAtTime(f0 * 1.2, at);
                f.frequency.exponentialRampToValueAtTime(Math.min(nyquist, f0 * 6), at + 0.12);
                f.frequency.exponentialRampToValueAtTime(f0 * 2, at + dur);
                env(g, at, gain / freqs.length, 0.04, dur);
                o.connect(f).connect(g);
                out(g, bus, wet);
                o.start(at);
                o.stop(at + dur + 0.05);
            }
        }

        function bass(freq, { at, bus, gain = 0.1, dur = 0.6 }) {
            const v = voice(bus, 0.05);
            partial(freq, 'sine', at, gain, 0.01, dur, v);
            partial(freq * 2, 'triangle', at, gain * 0.2, 0.01, dur * 0.6, v);
        }

        /** A low war drum: a deep boom with a hard skin. */
        function drum({ at, bus, gain = 0.3, freq = 58, dur = 0.6 }) {
            const o = ctx.createOscillator();
            const g = ctx.createGain();
            o.type = 'sine';
            o.frequency.setValueAtTime(freq * 2, at);
            o.frequency.exponentialRampToValueAtTime(freq, at + 0.06);
            o.frequency.exponentialRampToValueAtTime(freq * 0.82, at + dur);
            env(g, at, gain, 0.005, dur);
            o.connect(g);
            out(g, bus, 0.15);
            o.start(at);
            o.stop(at + dur + 0.05);
            hiss({ at, bus, gain: gain * 0.22, dur: 0.08, type: 'bandpass', freq: 900, q: 0.8, wet: 0.08 });
        }

        /** The sub-boom: a very low sine dropping away, felt more than heard. */
        function boom({ at, bus, gain = 0.4, from = 90, to = 32, dur = 1.1 }) {
            const o = ctx.createOscillator();
            const g = ctx.createGain();
            o.type = 'sine';
            o.frequency.setValueAtTime(from, at);
            o.frequency.exponentialRampToValueAtTime(to, at + dur);
            env(g, at, gain, 0.006, dur);
            o.connect(g);
            out(g, bus, 0.1);
            o.start(at);
            o.stop(at + dur + 0.05);
        }

        /** A stone landing: a dull knock with a gritty edge. */
        function knock(freq, { at, bus, gain = 0.1 }) {
            const o = ctx.createOscillator();
            const g = ctx.createGain();
            o.type = 'sine';
            o.frequency.setValueAtTime(freq * 1.6, at);
            o.frequency.exponentialRampToValueAtTime(freq, at + 0.04);
            env(g, at, gain, 0.003, 0.15);
            o.connect(g);
            out(g, bus, 0.08);
            o.start(at);
            o.stop(at + 0.2);
            hiss({ at, bus, gain: gain * 0.3, dur: 0.05, type: 'bandpass', freq: 1800, q: 1.2, wet: 0 });
        }

        /** Stone cracking: a few sharp, bright ticks of noise, closer together toward the break. */
        function crackle({ at, bus, n = 5, length = 0.3, gain = 0.06 }) {
            for (let i = 0; i < n; i++) {
                const k = i / Math.max(1, n - 1);
                hiss({ at: at + length * Math.sqrt(k), bus, gain: gain * (0.5 + k * 0.7), dur: 0.025, type: 'bandpass', freq: 2500 + Math.random() * 3500, q: 3, wet: 0.1 });
            }
        }

        /** Stone shattering: a crack, a burst of grit and glassy shards tinkling down. */
        function shatter({ at, bus, gain = 0.08 }) {
            hiss({ at, bus, gain: gain * 4, dur: 0.18, type: 'bandpass', freq: 3500, to: 900, q: 1.1, wet: 0.2, attack: 0.002 });
            hiss({ at, bus, gain: gain * 1.4, dur: 0.35, type: 'highpass', freq: 5000, wet: 0.25 });
            for (let i = 0; i < 4; i++) plink(2600 + Math.random() * 2400, { at: at + 0.03 + Math.random() * 0.35, bus, gain: gain * 0.4, dur: 0.18 });
        }

        /** An airy whoosh: band-passed noise sweeping from `from` to `to`, soft in. */
        function whoosh({ at, bus, gain = 0.06, dur = 0.4, from = 500, to = 2600 }) {
            hiss({ at, bus, gain, dur, type: 'bandpass', freq: from, to, q: 0.7, wet: 0.3, attack: dur * 0.4 });
        }

        /** Rising starfire for `dur` s: low noise whose filter opens as it grows. */
        function riser({ at, bus, gain = 0.07, dur = 0.6, from = 220, to = 2600 }) {
            const src = ctx.createBufferSource();
            const f = ctx.createBiquadFilter();
            const g = ctx.createGain();
            src.buffer = white;
            src.loop = true;
            f.type = 'lowpass';
            f.Q.value = 2.2;
            f.frequency.setValueAtTime(from, at);
            f.frequency.exponentialRampToValueAtTime(to, at + dur);
            g.gain.setValueAtTime(0.0001, at);
            g.gain.exponentialRampToValueAtTime(gain, at + dur);
            g.gain.exponentialRampToValueAtTime(0.0001, at + dur + 0.25);
            src.connect(f).connect(g);
            out(g, bus, 0.3);
            src.start(at, Math.random() * Math.max(0, white.duration - dur - 0.4));
            src.stop(at + dur + 0.3);
        }

        /** The war drums quickening and swelling into `to`. */
        function pulse({ at, to, bus, gain = 0.09 }) {
            let gap = 0.16;
            for (let when = at; when < to - 0.04; when += gap, gap = Math.max(0.05, gap * 0.82)) {
                const k = (when - at) / Math.max(0.05, to - at);
                drum({ at: when, bus, gain: gain * (0.35 + 0.65 * k), freq: 80 + 30 * k, dur: 0.3 });
            }
        }

        /** A strike: the drum and the sub-boom, a whoosh washing out behind them. */
        function strike({ at, bus, gain = 0.3 }) {
            drum({ at, bus, gain });
            boom({ at, bus, gain: gain * 0.9, dur: 0.9 });
            whoosh({ at, bus, gain: gain * 0.3, dur: 0.7, from: 2600, to: 400 });
        }

        /** A run of crystal bells with the shimmer under them (an octave up unless too high). */
        function fanfare(notes, { at, bus, gap = 0.08, gain = 0.08 }) {
            notes.forEach((n, i) => {
                shimmer(midi(n), { at: at + i * gap, bus, gain: gain * 1.1, dur: 1.1 });
                crystal(midi(n + 12 > 100 ? n : n + 12), { at: at + i * gap, bus, gain: gain * 0.5, dur: 1.1 });
            });
        }

        /** n coins and crystal flecks sprinkled over `length` s. */
        function flecks(n, { at, bus, length = 1, gain = 0.03 }) {
            for (let i = 0; i < n; i++) {
                plink(1700 + Math.random() * 1700, { at: at + Math.random() * length, bus, gain: gain * (0.6 + Math.random() * 0.8), dur: 0.2 });
            }
        }

        // ---- Effects ----

        function effectsBus() {
            if (effects) return effects;
            const dry = ctx.createGain();
            const wet = ctx.createGain();
            dry.gain.value = LEVEL.sfx;
            wet.gain.value = LEVEL.sfx;
            dry.connect(glue()).connect(master);
            wet.connect(reverb());
            effects = { dry, wet };
            return effects;
        }

        function sfx(name, arg = 0) {
            const t = ctx.currentTime + 0.01;
            const bus = effectsBus();
            const opts = arg && typeof arg === 'object' ? arg : {}; // 'win' and 'pop' take { chain, x, lead }
            switch (name) {
                case 'spin': { // soft, short and round: a low airy sweep and two muted crystal blips,
                    // a touch higher or lower each time (±½ semitone)
                    const k = Math.pow(2, (Math.random() - 0.5) / 12);
                    whoosh({ at: t, bus, gain: 0.05, dur: 0.42, from: 300 * k, to: 900 * k });
                    plink(midi(62) * k, { at: t + 0.06, bus, gain: 0.03, dur: 0.22, wet: 0.15 });
                    plink(midi(69) * k, { at: t + 0.18, bus, gain: 0.024, dur: 0.22, wet: 0.15 });
                    break;
                }
                case 'flame': { // a good win: starfire gathering around the winners
                    riser({ at: t, bus, gain: 0.04, dur: 0.9, from: 200, to: 1600 });
                    hiss({ at: t, bus, gain: 0.02, dur: 1, type: 'lowpass', freq: 700, wet: 0.2, attack: 0.3 });
                    break;
                }
                case 'land': { // a column lands: a stone knock, a touch higher each column
                    const col = num(arg);
                    knock(95 + col * 8, { at: t, bus, gain: 0.08 });
                    break;
                }
                case 'win': { // a tumble pays: { chain, x, lead } (or the chain alone). Every win: a
                    // shimmer run and a crystal bell, higher down a chain, the stone cracking under them.
                    // The bigger it is (x: its pay in bets), the more builds toward the shatter `lead` s
                    // on ('pop'): a second bell from 1×, a low brass swell from 2×, starfire rising into
                    // it from 5×, the war drums quickening from 10×.
                    const chain = num(opts.chain ?? (typeof arg === 'object' ? 0 : arg));
                    const x = num(opts.x);
                    const hit = t + Math.max(0.3, num(opts.lead ?? 0.8));
                    const base = 62 + Math.min(7, chain) * 2;
                    [0, 3, 7, 12, 15].forEach((d, i) => shimmer(midi(base + d), { at: t + i * 0.045, bus, gain: 0.065, dur: 0.9 }));
                    crystal(midi(base + 24), { at: t + 0.22, bus, gain: 0.035, dur: 1 });
                    crackle({ at: Math.max(t + 0.1, hit - 0.35), bus, n: 4, length: 0.3, gain: 0.04 });
                    if (x >= 1) crystal(midi(base + 31), { at: t + 0.27, bus, gain: 0.03, dur: 1.1 });
                    if (x >= 2) brass([base - 12, base - 5].map(midi), { at: t + 0.2, bus, gain: 0.05, dur: Math.max(0.6, hit - t) });
                    if (x >= 5) {
                        const from = Math.max(t, hit - 0.7);
                        riser({ at: from, bus, gain: 0.06, dur: hit - from, from: 220, to: 2600 });
                        whoosh({ at: Math.max(t, hit - 0.55), bus, gain: 0.05, dur: Math.min(0.55, hit - t), from: 400, to: 4200 });
                    }
                    if (x >= 10) {
                        pulse({ at: t + 0.25, to: hit, bus });
                        crackle({ at: t + 0.2, bus, n: 8, length: Math.max(0.2, hit - t - 0.3), gain: 0.035 });
                    }
                    break;
                }
                case 'buzz': { // something darts out: a quick swish each
                    const n = Math.max(1, Math.min(3, num(arg) || 1));
                    for (let i = 0; i < n; i++) whoosh({ at: t + i * 0.07, bus, gain: 0.025, dur: 0.28, from: 600 + i * 150, to: 2200 });
                    break;
                }
                case 'pop': { // the winners shatter: { chain, x } as for 'win'. Stone breaking and shards
                    // ringing; the bigger the win, the bigger the break: flecks from 1×; the drum and a
                    // second shatter from 2×; a strike and a crystal fanfare from 5×; from 10× the pad,
                    // the choir, a second fanfare and a shower of flecks.
                    const x = num(opts.x);
                    const k = Math.min(7, num(opts.chain)) * 2;
                    shatter({ at: t, bus, gain: 0.07 });
                    if (x >= 1) flecks(4, { at: t + 0.05, bus, length: 0.4 });
                    if (x >= 2) {
                        shatter({ at: t + 0.04, bus, gain: 0.045 });
                        drum({ at: t, bus, gain: x >= 5 ? 0.26 : 0.14 });
                        flecks(6, { at: t + 0.1, bus, length: 0.7 });
                    }
                    if (x >= 5) {
                        boom({ at: t, bus, gain: 0.3, dur: 0.8 });
                        fanfare([62, 65, 69, 74].map((n) => n + k), { at: t + 0.12, bus, gap: 0.06, gain: 0.06 });
                    }
                    if (x >= 10) {
                        pad([38, 50, 57].map((n) => midi(n + k)), { at: t, bus, gain: 0.06, dur: 1.6, attack: 0.06 });
                        choir([62, 65, 69, 74].map((n) => midi(n + k)), { at: t, bus, gain: 0.09, dur: 1.5, attack: 0.15 });
                        fanfare([74, 77, 81, 86, 89].map((n) => n + k), { at: t + 0.3, bus, gap: 0.07, gain: 0.06 });
                        flecks(16, { at: t + 0.2, bus, length: 1.6 });
                    }
                    break;
                }
                case 'scatter': { // an eclipse sigil lands: a ringing bell over a deep note, higher for each one
                    const n = Math.max(1, Math.min(6, num(arg) || 1));
                    crystal(midi([74, 77, 81, 86, 89, 93][n - 1]), { at: t, bus, gain: 0.065, dur: 1.6 });
                    shimmer(midi([62, 65, 69, 74, 77, 81][n - 1]), { at: t, bus, gain: 0.07, dur: 1.2 });
                    boom({ at: t, bus, gain: 0.12 + n * 0.02, from: 70, to: 40, dur: 0.6 });
                    break;
                }
                case 'star': // a meteor lands: a hot hiss and a glassy chime
                    hiss({ at: t, bus, gain: 0.05, dur: 0.25, type: 'bandpass', freq: 4000, to: 900, q: 1, wet: 0.2 });
                    knock(140, { at: t + 0.12, bus, gain: 0.07 });
                    crystal(midi(98), { at: t + 0.14, bus, gain: 0.04, dur: 0.8 });
                    break;
                case 'stars': // the meteors lift off for the Titan's fist: a rush up and a crystal trill
                    whoosh({ at: t, bus, gain: 0.07, dur: 0.5, from: 400, to: 4500 });
                    [98, 101, 105, 110].forEach((n, i) => crystal(midi(n), { at: t + i * 0.05, bus, gain: 0.028, dur: 0.6 }));
                    break;
                case 'crush': { // the Titan crushes them in his fist: a grinding crack and the sub-boom
                    crackle({ at: t, bus, n: 6, length: 0.12, gain: 0.07 });
                    boom({ at: t + 0.1, bus, gain: 0.45, from: 110, to: 30, dur: 1.2 });
                    drum({ at: t + 0.1, bus, gain: 0.22 });
                    shatter({ at: t + 0.12, bus, gain: 0.05 });
                    break;
                }
                case 'multiply': { // the blazing star lands on the win (arg: the multiplier): the drum, a
                    // brass chord and a crystal run; from 10× the choir and a second drum; from 25× a
                    // second fanfare, higher
                    const m = num(arg);
                    drum({ at: t, bus, gain: 0.2 });
                    brass([50, 57, 62].map(midi), { at: t, bus, gain: 0.06, dur: 1 });
                    riser({ at: t, bus, gain: 0.05, dur: 0.4, from: 300, to: 3000 });
                    fanfare([74, 77, 81, 86], { at: t + 0.4, bus, gap: 0.04, gain: 0.05 });
                    if (m >= 10) {
                        choir([50, 57, 62, 65, 69].map(midi), { at: t, bus, gain: 0.1, dur: 1.8, attack: 0.4 });
                        drum({ at: t + 0.4, bus, gain: 0.22 });
                        flecks(12, { at: t + 0.1, bus, length: 1.2 });
                    }
                    if (m >= 25) {
                        boom({ at: t + 0.45, bus, gain: 0.3 });
                        fanfare([81, 86, 89, 93], { at: t + 0.5, bus, gap: 0.06, gain: 0.06 });
                    }
                    break;
                }
                case 'retrigger': // more free spins: a crystal call and the drum
                    fanfare([81, 86, 89], { at: t, bus, gap: 0.08, gain: 0.07 });
                    drum({ at: t + 0.24, bus, gain: 0.2 });
                    break;
                case 'eclipse': // the moon slides across the star: a low drone rising, the corona flares
                    pad([38, 45, 50].map(midi), { at: t, bus, gain: 0.08, dur: 2.4, attack: 1.1 });
                    riser({ at: t + 0.3, bus, gain: 0.07, dur: 1.1, from: 150, to: 3000 });
                    strike({ at: t + 1.4, bus, gain: 0.3 });
                    choir([50, 57, 62, 65, 69].map(midi), { at: t + 1.4, bus, gain: 0.14, dur: 2.2, attack: 0.1 });
                    break;
                case 'fsStart': // free spins: a shimmer sweeps up as the choir rises, into a strike and brass
                    [62, 64, 65, 67, 69, 70, 72, 74, 76, 77, 79, 81, 82, 84, 86]
                        .forEach((n, i) => shimmer(midi(n), { at: t + i * 0.03, bus, gain: 0.05, dur: 1.2 }));
                    choir([50, 57, 62, 65, 69].map(midi), { at: t, bus, gain: 0.18, dur: 3, attack: 0.55 });
                    pad([38, 45, 50].map(midi), { at: t, bus, gain: 0.08, dur: 3, attack: 0.5 });
                    strike({ at: t + 0.45, bus, gain: 0.26 });
                    brass([50, 57, 62, 69].map(midi), { at: t + 0.45, bus, gain: 0.07, dur: 1.6 });
                    [86, 89, 93].forEach((n) => crystal(midi(n), { at: t + 0.45, bus, gain: 0.04, dur: 1.8 }));
                    break;
                case 'fsEnd': // the Eclipse passes: a shimmer cadence settling on an open chord
                    [81, 79, 77, 74].forEach((n, i) => shimmer(midi(n), { at: t + i * 0.14, bus, gain: 0.07, dur: 1 }));
                    run([62, 65, 69, 74, 77], { at: t + 0.56, bus, gap: 0.03, gain: 0.07, dur: 1.8 });
                    pad([38, 50, 57, 62].map(midi), { at: t + 0.5, bus, gain: 0.06, dur: 2.2, attack: 0.3 });
                    crystal(midi(86), { at: t + 0.62, bus, gain: 0.04, dur: 1.8 });
                    break;
                case 'big': { // a big win (arg: big | mega | epic | max): a strike, the brass call, a
                    // crystal fanfare and a shower of flecks over the pad. Mega adds the choir and a
                    // second strike and fanfare, higher; epic starfire rising and the drums quickening
                    // into a third strike; max a last boom and fanfare, higher still.
                    const level = { mega: 1, epic: 2, max: 3 }[arg] ?? 0;
                    strike({ at: t, bus, gain: 0.28 });
                    brass([50, 57, 62, 65].map(midi), { at: t, bus, gain: 0.08, dur: 1.4 });
                    fanfare([62, 65, 69, 74, 77, 81, 86], { at: t, bus, gap: 0.07, gain: 0.09 });
                    pad([38, 50, 57, 62].map(midi), { at: t, bus, gain: 0.07, dur: 2.4, attack: 0.25 });
                    flecks(18 + level * 10, { at: t + 0.1, bus, length: 1.4 + level * 0.4, gain: 0.035 });
                    if (level >= 1) {
                        choir([50, 57, 62, 65, 69].map(midi), { at: t, bus, gain: 0.1, dur: 2.6, attack: 0.5 });
                        strike({ at: t + 0.6, bus, gain: 0.24 });
                        fanfare([74, 77, 81, 86, 89], { at: t + 0.6, bus, gap: 0.07, gain: 0.08 });
                    }
                    if (level >= 2) {
                        riser({ at: t + 0.7, bus, gain: 0.06, dur: 0.6, from: 220, to: 2600 });
                        pulse({ at: t + 0.7, to: t + 1.3, bus });
                        strike({ at: t + 1.3, bus, gain: 0.22 });
                        brass([55, 62, 67, 70].map(midi), { at: t + 1.3, bus, gain: 0.07, dur: 1.4 });
                        choir([55, 62, 67, 70, 74].map(midi), { at: t + 1.3, bus, gain: 0.08, dur: 2, attack: 0.1 });
                    }
                    if (level >= 3) {
                        boom({ at: t + 1.9, bus, gain: 0.4 });
                        fanfare([86, 89, 93, 98, 101], { at: t + 1.9, bus, gap: 0.06, gain: 0.06 });
                        flecks(20, { at: t + 1.9, bus, length: 1.2, gain: 0.035 });
                    }
                    break;
                }
                case 'tick': { // the win counting up (arg: how far, 0..1): a soft crystal plink, higher as it climbs
                    const p = Math.max(0, Math.min(1, num(arg)));
                    plink(1100 + 1500 * p, { at: t, bus, gain: 0.02 + 0.016 * p, dur: 0.25 });
                    break;
                }
                case 'bigEnd': { // the count lands: a strike, the brass and choir chord and a bell
                    const level = { mega: 1, epic: 2, max: 3 }[arg] ?? 0;
                    strike({ at: t, bus, gain: 0.28 });
                    brass([50, 57, 62, 69].map(midi), { at: t, bus, gain: 0.07 + level * 0.008, dur: 1.4 });
                    choir([50, 57, 62, 65, 69].map(midi), { at: t, bus, gain: 0.11 + level * 0.01, dur: 1.4 + level * 0.3, attack: 0.08 });
                    crystal(midi(86), { at: t + 0.05, bus, gain: 0.05, dur: 1.8 });
                    flecks(8 + level * 6, { at: t + 0.05, bus, length: 0.8 });
                    break;
                }
                case 'intro': // in time with the intro: the logo falls and lands with a strike, the
                    // stones ring, the Titan appears (a low rising swell) and a crystal glints
                    whoosh({ at: t + 0.1, bus, gain: 0.06, dur: 0.4, from: 3000, to: 450 });
                    strike({ at: t + 0.5, bus, gain: 0.24 });
                    shatter({ at: t + 0.5, bus, gain: 0.05 });
                    fanfare([62, 65, 69, 74, 77, 81], { at: t + 0.5, bus, gap: 0.07, gain: 0.065 });
                    riser({ at: t + 0.7, bus, gain: 0.04, dur: 0.6, from: 200, to: 1800 });
                    brass([50, 57, 62].map(midi), { at: t + 1.2, bus, gain: 0.06, dur: 1.2 });
                    crystal(midi(98), { at: t + 1.0, bus, gain: 0.05, dur: 0.8 });
                    crystal(midi(105), { at: t + 1.5, bus, gain: 0.035, dur: 1.2 });
                    break;
                case 'click': // a button: a tiny stone tap
                    knock(300, { at: t, bus, gain: 0.04 });
                    break;
            }
        }

        // ---- The background music: a man humming in the dark, with echo ----

        /**
         * A man's hum, "mmm": a buzzing voice (a saw and a softer triangle a hair apart) through
         * a closed mouth: everything above the low harmonics muffled, the nasal resonance around
         * 270 Hz lifted, a faint buzz at 2.4 kHz. It slides up from `from` (the last note), swells
         * in, gains a slow vibrato as it holds, and fades. `send` takes it to the echo.
         */
        function hum(freq, { at, dur, bus, send, gain = 0.06, from = null, wet = 0.45 }) {
            if (freq >= nyquist) return;
            const v = ctx.createGain();
            const lp = ctx.createBiquadFilter();
            const nasal = ctx.createBiquadFilter();
            const buzz = ctx.createBiquadFilter();
            lp.type = 'lowpass';
            lp.frequency.value = Math.min(nyquist, 760);
            lp.Q.value = 0.6;
            nasal.type = 'peaking';
            nasal.frequency.value = 270;
            nasal.Q.value = 1.4;
            nasal.gain.value = 9;
            buzz.type = 'peaking';
            buzz.frequency.value = Math.min(nyquist, 2400);
            buzz.Q.value = 3;
            buzz.gain.value = 4;
            const lfo = ctx.createOscillator();
            const depth = ctx.createGain();
            lfo.frequency.value = 4.8 + Math.random() * 0.8;
            depth.gain.setValueAtTime(0.0001, at);
            depth.gain.linearRampToValueAtTime(freq * 0.004, at + Math.min(1.2, dur * 0.6));
            lfo.connect(depth);
            for (const [type, level, cents] of [['sawtooth', 1, -4], ['triangle', 0.6, 5]]) {
                const o = ctx.createOscillator();
                const g = ctx.createGain();
                o.type = type;
                o.detune.value = cents;
                o.frequency.setValueAtTime(from ?? freq, at);
                if (from && from !== freq) o.frequency.exponentialRampToValueAtTime(freq, at + 0.28);
                depth.connect(o.frequency);
                g.gain.value = level;
                o.connect(g).connect(lp);
                o.start(at);
                o.stop(at + dur + 0.95);
            }
            lp.connect(nasal).connect(buzz).connect(v);
            v.gain.setValueAtTime(0.0001, at);
            v.gain.exponentialRampToValueAtTime(gain, at + 0.42);
            v.gain.setValueAtTime(gain, at + Math.max(0.45, dur - 0.1));
            v.gain.exponentialRampToValueAtTime(0.0001, at + dur + 0.9);
            out(v, bus, wet);
            if (send) {
                const s = ctx.createGain();
                s.gain.value = 0.7;
                v.connect(s).connect(send);
            }
            lfo.start(at);
            lfo.stop(at + dur + 0.95);
        }

        /**
         * The music: a man humming a slow, low tune in D minor over a deep hummed drone, every
         * phrase answered by its echo (a long feedback delay, darker each time round) in the
         * vastness of the reverb, over the faint hum of space. In the Eclipse a second voice
         * hums a fifth above him, the war drums keep a heartbeat and crystal bells ring the tune.
         */
        function ambience() {
            const nodes = [];
            const keep = (n) => (nodes.push(n), n);
            const t0 = ctx.currentTime;

            const dry = keep(ctx.createGain());
            const wet = keep(ctx.createGain());
            for (const g of [dry, wet]) {
                g.gain.setValueAtTime(0.0001, t0);
                g.gain.exponentialRampToValueAtTime(LEVEL.ambience, t0 + 3);
            }
            dry.connect(glue()).connect(master);
            wet.connect(reverb());
            const bus = { dry, wet };

            // The echo: what goes in comes back 0.48 s later, and again, each repeat softer and
            // darker, into the music's bus (and its reverb).
            const echoIn = keep(ctx.createGain());
            const delay = keep(ctx.createDelay(2));
            const feedback = keep(ctx.createGain());
            const darker = keep(ctx.createBiquadFilter());
            const thinner = keep(ctx.createBiquadFilter());
            const echoOut = keep(ctx.createGain());
            delay.delayTime.value = 0.48;
            feedback.gain.value = 0.44;
            darker.type = 'lowpass';
            darker.frequency.value = 1500;
            thinner.type = 'highpass';
            thinner.frequency.value = 160;
            echoOut.gain.value = 0.75;
            echoIn.connect(delay);
            delay.connect(darker).connect(thinner).connect(feedback).connect(delay);
            thinner.connect(echoOut);
            out(echoOut, bus, 0.5);

            // Under the music: the hum of space, a dark wash whose filter drifts slowly.
            const space = keep(ctx.createBufferSource());
            const spaceFilter = keep(ctx.createBiquadFilter());
            const spaceGain = keep(ctx.createGain());
            const drift = keep(ctx.createOscillator());
            const driftDepth = keep(ctx.createGain());
            space.buffer = white;
            space.loop = true;
            spaceFilter.type = 'lowpass';
            spaceFilter.frequency.value = 380;
            spaceFilter.Q.value = 0.8;
            drift.frequency.value = 0.06;
            driftDepth.gain.value = 160;
            drift.connect(driftDepth).connect(spaceFilter.frequency);
            spaceGain.gain.value = 0.008;
            space.connect(spaceFilter).connect(spaceGain);
            out(spaceGain, bus, 0.4);
            space.start(t0);
            drift.start(t0);

            const length = BARS.length * 16; // the tune spans the eight bars, then starts again
            let level = 0;
            let step = 0;
            let next = t0 + 0.4;
            let last = null;
            let stopped = false;

            function playStep(at) {
                const s = step % 16;
                const bar = BARS[Math.floor(step / 16) % BARS.length];
                const barLength = STEP[level] * 16;
                // Every second bar: the deep drone, hummed on the root, two bars long; a faint pad under it.
                if (s === 0 && Math.floor(step / 16) % 2 === 0) {
                    hum(midi(bar.root + 12), { at, dur: barLength * 2, bus, send: echoIn, gain: 0.045, wet: 0.35 });
                    pad(bar.chord.map((n) => midi(n - 12)), { at, bus, gain: 0.014, dur: barLength * 2.1, attack: barLength * 0.6, bright: 520 });
                }
                // The tune: each note slides up or down from the last.
                const note = HUM.find(([start]) => start === step % length);
                if (note) {
                    const [, n, len] = note;
                    const f = midi(n);
                    hum(f, { at, dur: len * STEP[level], bus, send: echoIn, gain: 0.07, from: last });
                    if (level >= 1) hum(midi(n + 7), { at, dur: len * STEP[level], bus, send: echoIn, gain: 0.04, from: last ? last * Math.pow(2, 7 / 12) : null, wet: 0.55 });
                    last = f;
                }
                if (s === 0 && level >= 1) bass(midi(bar.root), { at, bus, gain: 0.06, dur: barLength * 0.45 });
                // The Eclipse: the war drums' heartbeat and the crystal tune, an eighth at a time.
                if (level >= 1 && (s === 0 || s === 6 || s === 10)) drum({ at, bus, gain: 0.12, freq: 52 });
                if (level >= 1 && s % 2 === 0) {
                    const bell = TUNE[(step / 2) % TUNE.length];
                    if (bell) crystal(midi(bell + 12), { at, bus, gain: 0.02, dur: 1.1 });
                }
            }

            function schedule() {
                if (stopped) return;
                const horizon = ctx.currentTime + 1.2;
                while (next < horizon) {
                    playStep(next);
                    step += 1;
                    next += STEP[level];
                }
            }

            /** 0 the nebula · 1 free spins in the Eclipse. */
            function intensity(n) {
                const lv = Math.max(0, Math.min(1, Math.floor(num(n))));
                if (lv > level && !stopped) boom({ at: ctx.currentTime + 0.02, bus, gain: 0.3 });
                level = lv;
            }

            schedule();
            const timer = setInterval(schedule, 250);

            function stop() {
                stopped = true;
                clearInterval(timer);
                const t = ctx.currentTime;
                for (const n of nodes) {
                    try {
                        if (n.gain) {
                            n.gain.cancelScheduledValues(t);
                            n.gain.setValueAtTime(Math.max(n.gain.value, 0.0001), t);
                            n.gain.exponentialRampToValueAtTime(0.0001, t + 0.5);
                        }
                        if (typeof n.stop === 'function') n.stop(t + 0.6);
                    } catch (_) { /* already stopped */ }
                }
                setTimeout(() => { dry.disconnect(); wet.disconnect(); echoOut.disconnect(); }, 700);
            }

            return { stop, schedule, intensity };
        }

        return { sfx, ambience };
    }

    window.OrangeSound = Object.assign(window.OrangeSound || {}, { cosmos });
}());
