/**
 * "Trident of Poseidon" sound pack for the slot: the sunlit shallows and, in free spins, the
 * glowing abyss. Harp plucks and glass bells over a deep, warm pad; water everywhere (splashes,
 * bubbles, a rising swell); a soft low drum and an airy whoosh for every strike; and a choir
 * rising out of the deep for free spins. All synthesised, same shape as the other packs:
 *
 *   const pack = window.OrangeSound.tides(ctx, master);
 *   pack.sfx('pop');                    // spin | land | win | buzz | flame | pop | scatter | star | stars |
 *                                       // multiply | retrigger | fsStart | fsEnd | big | tick | bigEnd | click | intro
 *   pack.sfx('scatter', 3);             // the third scatter: each one rings higher
 *   pack.sfx('win', { chain: 1, x: 6, lead: 1.4 }); // a tumble paying 6× the bet, its burst 1.4 s on:
 *                                       // the bigger the win, the more it celebrates ('pop' too)
 *   pack.sfx('big', 'mega');            // big | mega | epic | max, each bigger
 *   const bg = pack.ambience();         // continuous background, until bg.stop()
 *   bg.intensity(1);                    // 0 the shallows · 1 free spins in the abyss
 *
 * The music schedules ahead on the audio clock (bg.schedule() runs every 250 ms).
 */
(function () {
    'use strict';

    const midi = (n) => 440 * Math.pow(2, (n - 69) / 12);
    const num = (v) => (Number.isFinite(Number(v)) ? Number(v) : 0);

    // Eight bars of sixteen sixteenths in D major, open and slow like light through water:
    // Dmaj9 | Bm7 | Gmaj7 | Asus2 | Dmaj9 | F#m7 | Gmaj7#11 | A6/9.
    const BARS = [
        { chord: [62, 66, 69, 73], root: 38 },
        { chord: [59, 62, 66, 69], root: 35 },
        { chord: [62, 66, 67, 71], root: 43 },
        { chord: [62, 64, 69, 71], root: 45 },
        { chord: [62, 66, 69, 73], root: 38 },
        { chord: [64, 66, 69, 73], root: 42 },
        { chord: [61, 66, 67, 71], root: 43 },
        { chord: [61, 64, 69, 71], root: 45 },
    ];
    // The glass-bell tune, one entry per eighth (0 = rest); played in free spins.
    const TUNE = [
        81, 0, 0, 78, 76, 0, 74, 0, 78, 0, 0, 74, 73, 0, 71, 0,
        74, 0, 78, 0, 79, 0, 81, 0, 83, 0, 0, 81, 76, 0, 0, 0,
        81, 0, 0, 78, 76, 0, 74, 0, 76, 0, 0, 73, 69, 0, 73, 0,
        74, 0, 78, 0, 80, 0, 78, 0, 76, 0, 0, 0, 73, 0, 0, 0,
    ];
    // The harp's turn over the chord, sixteenth → chord tone (eighths only in the shallows).
    const ARP = [0, 1, 2, 3, 2, 1, 2, 3, 0, 1, 2, 3, 2, 3, 2, 1];
    // Bass: sixteenth → interval above the root (slow, like a tide).
    const BASS = { 0: 0, 10: 7 };
    // Sixteenth length: ~83 bpm in the shallows, ~100 bpm in free spins.
    const STEP = [0.18, 0.15];
    const LEVEL = { sfx: 2.0, ambience: 1.6 };
    // Vowel formants (Hz) for the choir: an "oo" that opens to an "ah" as it swells; [level, Q] each.
    const OO = [330, 760, 2400];
    const AH = [730, 1150, 2650];
    const FORMANT = [[1, 5], [0.55, 7], [0.22, 9]];

    function tides(ctx, master) {
        let reverbIn = null;
        let effects = null;
        const nyquist = ctx.sampleRate / 2.2;

        const white = ctx.createBuffer(1, Math.floor(ctx.sampleRate * 2), ctx.sampleRate);
        {
            const w = white.getChannelData(0);
            for (let i = 0; i < w.length; i++) w[i] = Math.random() * 2 - 1;
        }

        /** Deep water: a convolver over 2 s of noise that fades slowly and darkens as it goes. */
        function reverb() {
            if (reverbIn) return reverbIn;
            const len = Math.floor(ctx.sampleRate * 2);
            const ir = ctx.createBuffer(2, len, ctx.sampleRate);
            for (let ch = 0; ch < 2; ch++) {
                const d = ir.getChannelData(ch);
                let lp = 0;
                for (let i = 0; i < len; i++) {
                    lp += (0.6 - 0.45 * (i / len)) * (Math.random() * 2 - 1 - lp); // a one-pole lowpass, closing
                    d[i] = lp * Math.pow(1 - i / len, 2.2);
                }
            }
            reverbIn = ctx.createConvolver();
            reverbIn.buffer = ir;
            const ret = ctx.createGain();
            ret.gain.value = 0.4;
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

        /**
         * An FM tone: a sine bent by another at `ratio` × its pitch, bright as it is struck and
         * mellowing as it rings (the bend dies away faster than the note). High notes are bent
         * less, so nothing turns shrill.
         */
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

        /** Harp: a plucked string, its overtones dying faster than the note. */
        function harp(freq, { at, bus, gain = 0.07, dur = 1.1, wet = 0.3 }) {
            const v = voice(bus, wet);
            partial(freq, 'triangle', at, gain, 0.003, dur, v);
            partial(freq * 2, 'sine', at, gain * 0.4, 0.002, dur * 0.55, v);
            partial(freq * 3, 'sine', at, gain * 0.15, 0.002, dur * 0.3, v);
            partial(freq * 4.01, 'sine', at, gain * 0.07, 0.001, dur * 0.18, v);
        }

        /** A few harp strings swept quickly, low to high. */
        function strum(notes, { at, bus, gap = 0.025, gain = 0.06, dur = 1.2 }) {
            notes.forEach((n, i) => harp(midi(n), { at: at + i * gap, bus, gain, dur }));
        }

        /** A glass bell: an inharmonic FM ring, a glassy octave and a twin a hair sharp for shimmer. */
        function glass(freq, { at, bus, gain = 0.05, dur = 1.2, wet = 0.4 }) {
            const v = voice(bus, wet);
            fm(freq, 3.5, 0.9, at, gain, 0.002, dur, v);
            partial(freq * 2, 'sine', at, gain * 0.2, 0.002, dur * 0.45, v);
            partial(freq * 1.003, 'sine', at, gain * 0.4, 0.002, dur * 0.9, v);
        }

        /** A soft glassy plink: a short, round FM note (marimba-like, harmonic). */
        function plink(freq, { at, bus, gain = 0.03, dur = 0.25, wet = 0.3 }) {
            fm(freq, 2, 0.5, at, gain, 0.003, dur, voice(bus, wet));
        }

        /** A deep, warm pad: detuned saws through a soft, low lowpass, swelling in slowly. */
        function pad(freqs, { at, bus, gain = 0.05, dur = 2.4, attack = dur * 0.4, wet = 0.5, bright = 800 }) {
            for (const f0 of freqs) {
                for (const cents of [-8, 8]) {
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

        /**
         * A choir out of the deep: three detuned saws a note with a slow vibrato, through vowel
         * formants that open from "oo" to "ah" as it swells.
         */
        function choir(freqs, { at, bus, gain = 0.06, dur = 2.4, attack = dur * 0.4, wet = 0.6 }) {
            const lfo = ctx.createOscillator();
            lfo.frequency.value = 4.8 + Math.random() * 0.8;
            lfo.start(at);
            lfo.stop(at + dur + 0.05);
            for (const f0 of freqs) {
                const mix = ctx.createGain();
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
                    o.connect(mix);
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
                    mix.connect(f).connect(lv).connect(g);
                });
                env(g, at, gain / freqs.length, attack, dur);
                out(g, bus, wet);
            }
        }

        function bass(freq, { at, bus, gain = 0.1, dur = 0.6 }) {
            const v = voice(bus, 0.05);
            partial(freq, 'sine', at, gain, 0.01, dur, v);
            partial(freq * 2, 'triangle', at, gain * 0.2, 0.01, dur * 0.6, v);
        }

        /** A gentle low drum: a deep, soft boom with a muffled skin. */
        function drum({ at, bus, gain = 0.3, freq = 62, dur = 0.6 }) {
            const o = ctx.createOscillator();
            const g = ctx.createGain();
            o.type = 'sine';
            o.frequency.setValueAtTime(freq * 1.8, at);
            o.frequency.exponentialRampToValueAtTime(freq, at + 0.07);
            o.frequency.exponentialRampToValueAtTime(freq * 0.85, at + dur);
            env(g, at, gain, 0.006, dur);
            o.connect(g);
            out(g, bus, 0.12);
            o.start(at);
            o.stop(at + dur + 0.05);
            hiss({ at, bus, gain: gain * 0.15, dur: 0.1, type: 'lowpass', freq: 500, wet: 0.05 });
        }

        /** A soft, muffled thud: something settling on the sea floor. */
        function thud(freq, { at, bus, gain = 0.1 }) {
            const o = ctx.createOscillator();
            const g = ctx.createGain();
            o.type = 'sine';
            o.frequency.setValueAtTime(freq * 1.5, at);
            o.frequency.exponentialRampToValueAtTime(freq, at + 0.05);
            env(g, at, gain, 0.004, 0.16);
            o.connect(g);
            out(g, bus, 0.08);
            o.start(at);
            o.stop(at + 0.2);
            hiss({ at, bus, gain: gain * 0.2, dur: 0.06, type: 'lowpass', freq: 700, wet: 0 });
        }

        /** An airy whoosh: band-passed noise sweeping from `from` to `to`, soft in. */
        function whoosh({ at, bus, gain = 0.06, dur = 0.4, from = 500, to = 2600 }) {
            hiss({ at, bus, gain, dur, type: 'bandpass', freq: from, to, q: 0.7, wet: 0.25, attack: dur * 0.4 });
        }

        /** A strike: the low drum, with an airy whoosh washing out behind it. */
        function strike({ at, bus, gain = 0.3 }) {
            drum({ at, bus, gain });
            whoosh({ at, bus, gain: gain * 0.35, dur: 0.7, from: 2600, to: 500 });
        }

        /**
         * A splash: a burst of band-passed noise, its filter dropping fast, with a fizz on top.
         * (A band of noise is far quieter than a tone at the same gain, hence the ×5.)
         */
        function splash({ at, bus, gain = 0.08, dur = 0.35, from = 3200, to = 500 }) {
            hiss({ at, bus, gain: gain * 5, dur, type: 'bandpass', freq: from, to, q: 0.9, wet: 0.3, attack: 0.004 });
            hiss({ at, bus, gain, dur: dur * 0.6, freq: 5000, wet: 0.2 });
        }

        /** A bubble: a short, round blip gliding quickly up (`glide` × its pitch). */
        function bubble(f0, { at, bus, gain = 0.04, dur = 0.09, glide = 2.4, wet = 0.2 }) {
            const o = ctx.createOscillator();
            const g = ctx.createGain();
            o.type = 'sine';
            o.frequency.setValueAtTime(f0, at);
            o.frequency.exponentialRampToValueAtTime(f0 * glide, at + dur);
            env(g, at, gain, 0.006, dur);
            o.connect(g);
            out(g, bus, wet);
            o.start(at);
            o.stop(at + dur + 0.05);
        }

        /** n bubbles rising over `length` s, between lo and hi Hz. */
        function bubbles(n, { at, bus, length = 0.6, gain = 0.03, lo = 400, hi = 1400 }) {
            for (let i = 0; i < n; i++) {
                bubble(lo * Math.pow(hi / lo, Math.random()), {
                    at: at + Math.random() * length, bus, gain: gain * (0.6 + Math.random() * 0.8), dur: 0.05 + Math.random() * 0.07,
                });
            }
        }

        /** The sea's coins: n glassy droplets sprinkled over `length` s. */
        function droplets(n, { at, bus, length = 1, gain = 0.03 }) {
            for (let i = 0; i < n; i++) {
                plink(1800 + Math.random() * 1500, { at: at + Math.random() * length, bus, gain: gain * (0.6 + Math.random() * 0.8), dur: 0.2 });
            }
        }

        /** The water rising for `dur` s: low noise whose filter opens as it grows, gone at the top. */
        function swell({ at, bus, gain = 0.07, dur = 0.6, from = 250, to = 2400 }) {
            const src = ctx.createBufferSource();
            const f = ctx.createBiquadFilter();
            const g = ctx.createGain();
            src.buffer = white;
            src.loop = true;
            f.type = 'lowpass';
            f.Q.value = 2;
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

        /** A soft swirl of water: band-passed noise whose filter rises and settles back, slow in and out. */
        function swirl({ at, bus, gain = 0.03, dur = 0.45, lo = 400, hi = 1100 }) {
            const src = ctx.createBufferSource();
            const f = ctx.createBiquadFilter();
            const g = ctx.createGain();
            src.buffer = white;
            f.type = 'bandpass';
            f.Q.value = 1.1;
            f.frequency.setValueAtTime(lo, at);
            f.frequency.exponentialRampToValueAtTime(hi, at + dur * 0.35);
            f.frequency.exponentialRampToValueAtTime(lo * 0.75, at + dur);
            g.gain.setValueAtTime(0.0001, at);
            g.gain.exponentialRampToValueAtTime(gain, at + dur * 0.3);
            g.gain.linearRampToValueAtTime(gain * 0.5, at + dur * 0.65);
            g.gain.linearRampToValueAtTime(0, at + dur);
            src.connect(f).connect(g);
            out(g, bus, 0.1);
            src.start(at, Math.random() * Math.max(0, white.duration - dur - 0.05));
            src.stop(at + dur + 0.02);
        }

        /** The deep drums, quickening and swelling into `to` (the hit they lead to). */
        function pulse({ at, to, bus, gain = 0.09 }) {
            let gap = 0.16;
            for (let when = at; when < to - 0.04; when += gap, gap = Math.max(0.05, gap * 0.82)) {
                const k = (when - at) / Math.max(0.05, to - at);
                drum({ at: when, bus, gain: gain * (0.35 + 0.65 * k), freq: 90 + 30 * k, dur: 0.3 });
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

        /** A harp run with a glass bell on every note (an octave up, unless that is too high). */
        function fanfare(notes, { at, bus, gap = 0.08, gain = 0.08 }) {
            notes.forEach((n, i) => {
                harp(midi(n), { at: at + i * gap, bus, gain: gain * 1.1, dur: 1.1 });
                glass(midi(n + 12 > 100 ? n : n + 12), { at: at + i * gap, bus, gain: gain * 0.5, dur: 1 });
            });
        }

        function sfx(name, arg = 0) {
            const t = ctx.currentTime + 0.01;
            const bus = effectsBus();
            const opts = arg && typeof arg === 'object' ? arg : {}; // 'win' and 'pop' take { chain, x, lead }
            switch (name) {
                case 'spin': { // soft, short and round, never tiring: a swirl of water and two low
                    // bubbles, no bells; a touch higher or lower each time (±½ semitone)
                    const k = Math.pow(2, (Math.random() - 0.5) / 12);
                    swirl({ at: t, bus, gain: 0.085, dur: 0.5, lo: 380 * k, hi: 1050 * k });
                    bubble(170 * k, { at: t + 0.05, bus, gain: 0.045, dur: 0.15, glide: 1.7, wet: 0.1 });
                    bubble(240 * k, { at: t + 0.19, bus, gain: 0.035, dur: 0.13, glide: 1.7, wet: 0.1 });
                    break;
                }
                case 'flame': { // a good win: a whirlpool churns, gathering the winners, bubbles boiling up
                    whoosh({ at: t, bus, gain: 0.05, dur: 0.6, from: 300, to: 1400 });
                    hiss({ at: t, bus, gain: 0.03, dur: 1.1, type: 'lowpass', freq: 600, wet: 0.15, attack: 0.3 });
                    bubbles(14, { at: t + 0.05, bus, length: 1.05, gain: 0.02, lo: 300, hi: 1200 });
                    break;
                }
                case 'land': { // one column lands: a soft, muffled thud and a tiny bubble, a touch higher each column
                    const col = num(arg);
                    thud(110 + col * 9, { at: t, bus, gain: 0.075 });
                    bubble(520 + col * 45, { at: t + 0.03, bus, gain: 0.012, dur: 0.06 });
                    break;
                }
                case 'win': { // a tumble pays: { chain, x, lead } (or the chain alone). Every win: a
                    // harp run up and a glass bell, higher down a chain of tumbles. The bigger it is
                    // (x: its pay in bets), the more builds up toward the burst, `lead` s on ('pop'):
                    // a bell and bubbles from 1×, a low strum and a splash from 2×, the water rising
                    // into it from 5×, the deep drums quickening from 10×.
                    const chain = num(opts.chain ?? (typeof arg === 'object' ? 0 : arg));
                    const x = num(opts.x);
                    const hit = t + Math.max(0.3, num(opts.lead ?? 0.8));
                    const base = 62 + Math.min(7, chain) * 2;
                    [0, 7, 12, 16, 19].forEach((d, i) => harp(midi(base + d), { at: t + i * 0.045, bus, gain: 0.07, dur: 0.9 }));
                    glass(midi(base + 24), { at: t + 0.22, bus, gain: 0.035, dur: 0.9 });
                    bubble(480 + Math.random() * 160, { at: t + 0.04, bus, gain: 0.025 });
                    if (x >= 1) {
                        glass(midi(base + 31), { at: t + 0.27, bus, gain: 0.03, dur: 1.1 });
                        bubbles(2, { at: t + 0.1, bus, length: 0.25, gain: 0.025 });
                    }
                    if (x >= 2) {
                        strum([base - 12, base - 5, base], { at: t + 0.2, bus, gain: 0.06, dur: 1 });
                        splash({ at: t + 0.2, bus, gain: 0.045, dur: 0.3 });
                    }
                    if (x >= 5) {
                        const from = Math.max(t, hit - 0.7);
                        swell({ at: from, bus, gain: 0.06, dur: hit - from, from: 220, to: 2600 });
                        whoosh({ at: Math.max(t, hit - 0.55), bus, gain: 0.05, dur: Math.min(0.55, hit - t), from: 400, to: 4200 });
                    }
                    if (x >= 10) {
                        pulse({ at: t + 0.25, to: hit, bus });
                        bubbles(8, { at: t + 0.2, bus, length: Math.max(0.1, hit - t - 0.3), gain: 0.025, lo: 300, hi: 1600 });
                    }
                    break;
                }
                case 'buzz': { // Poseidon's creatures dart out: a quick swish and a trail of bubbles each
                    const n = Math.max(1, Math.min(3, num(arg) || 1));
                    for (let i = 0; i < n; i++) {
                        const at = t + i * 0.07;
                        whoosh({ at, bus, gain: 0.025, dur: 0.28, from: 600 + i * 150, to: 2200 });
                        bubbles(2, { at: at + 0.05, bus, length: 0.2, gain: 0.02, lo: 500, hi: 1300 });
                    }
                    break;
                }
                case 'pop': { // the winners burst: { chain, x } as for 'win'. A splash and bubbles; the
                    // bigger the win, the bigger the burst: droplets from 1×; a second splash and the
                    // drum from 2×; a strike and a harp-and-bell fanfare from 5×; from 10× the deep pad
                    // and the choir, a second fanfare and a shower of droplets.
                    const x = num(opts.x);
                    const k = Math.min(7, num(opts.chain)) * 2; // in the key of the chain's run
                    splash({ at: t, bus, gain: 0.07, dur: 0.3 });
                    bubbles(4, { at: t, bus, length: 0.15, gain: 0.05, lo: 450, hi: 1100 });
                    if (x >= 1) droplets(4, { at: t + 0.05, bus, length: 0.4 });
                    if (x >= 2) {
                        splash({ at: t + 0.03, bus, gain: 0.04, dur: 0.55, from: 2000, to: 300 });
                        drum({ at: t, bus, gain: x >= 5 ? 0.28 : 0.14 }); // from 5× the strike's drum
                        droplets(6, { at: t + 0.1, bus, length: 0.7 });
                    }
                    if (x >= 5) {
                        whoosh({ at: t, bus, gain: 0.09, dur: 0.7, from: 2600, to: 500 }); // ...and its whoosh
                        fanfare([69, 74, 78, 81].map((n) => n + k), { at: t + 0.02, bus, gap: 0.06, gain: 0.065 });
                        droplets(10, { at: t + 0.15, bus, length: 1 });
                    }
                    if (x >= 10) {
                        pad([38, 50, 57].map((n) => midi(n + k)), { at: t, bus, gain: 0.06, dur: 1.6, attack: 0.06 });
                        choir([62, 66, 69, 74].map((n) => midi(n + k)), { at: t, bus, gain: 0.09, dur: 1.5, attack: 0.15 });
                        fanfare([74, 78, 81, 86, 90].map((n) => n + k), { at: t + 0.3, bus, gap: 0.07, gain: 0.065 });
                        splash({ at: t + 0.3, bus, gain: 0.045, dur: 0.6 });
                        droplets(16, { at: t + 0.2, bus, length: 1.6 });
                    }
                    break;
                }
                case 'scatter': { // a scatter lands: a shimmering bell over a harp, higher for each one on screen
                    const n = Math.max(1, Math.min(6, num(arg) || 1));
                    glass(midi([74, 78, 81, 86, 90, 93][n - 1]), { at: t, bus, gain: 0.065, dur: 1.4 });
                    harp(midi([62, 66, 69, 74, 78, 81][n - 1]), { at: t, bus, gain: 0.075, dur: 1.2 });
                    harp(midi([69, 73, 76, 81, 85, 88][n - 1]), { at: t + 0.04, bus, gain: 0.045, dur: 1 });
                    break;
                }
                case 'star': // a pearl lands in its shell: a glassy chime and a bubble
                    glass(midi(98), { at: t, bus, gain: 0.05, dur: 0.8 });
                    glass(midi(105), { at: t + 0.05, bus, gain: 0.03, dur: 0.7 });
                    bubble(650, { at: t + 0.02, bus, gain: 0.03 });
                    break;
                case 'stars': // the pearls set off for the win: a rush up, a glassy trill and bubbles rising
                    whoosh({ at: t, bus, gain: 0.07, dur: 0.45, from: 500, to: 4500 });
                    [98, 102, 105, 110].forEach((n, i) => glass(midi(n), { at: t + i * 0.05, bus, gain: 0.03, dur: 0.6 }));
                    bubbles(6, { at: t, bus, length: 0.4, gain: 0.03, lo: 500, hi: 1600 });
                    break;
                case 'multiply': { // their total lands on the win (arg: the multiplier): a surge, the
                    // drum and a bright chord, the water rising into a bell; from 10× the choir, a
                    // second drum and droplets; from 25× a splash and a second fanfare, higher
                    const m = num(arg);
                    drum({ at: t, bus, gain: 0.2 });
                    strum([62, 69, 74, 78, 81], { at: t, bus, gap: 0.02, gain: 0.05, dur: 1.4 });
                    swell({ at: t, bus, gain: 0.055, dur: 0.45, from: 300, to: 3000 });
                    fanfare([74, 78, 81, 86], { at: t + 0.45, bus, gap: 0.04, gain: 0.05 });
                    if (m >= 10) {
                        choir([50, 57, 62, 66, 69].map(midi), { at: t, bus, gain: 0.1, dur: 1.8, attack: 0.4 });
                        drum({ at: t + 0.45, bus, gain: 0.22 });
                        droplets(12, { at: t + 0.1, bus, length: 1.2 });
                    }
                    if (m >= 25) {
                        splash({ at: t + 0.5, bus, gain: 0.05, dur: 0.6 });
                        fanfare([81, 86, 90, 93], { at: t + 0.5, bus, gap: 0.06, gain: 0.06 });
                    }
                    break;
                }
                case 'retrigger': // more free spins: a harp-and-bell call and a splash
                    fanfare([81, 86, 90], { at: t, bus, gap: 0.08, gain: 0.07 });
                    splash({ at: t + 0.24, bus, gain: 0.06, dur: 0.4 });
                    break;
                case 'fsStart': // free spins: the sea opens: a harp glissando sweeps up as a choir rises
                    // out of the deep, into a strike and a ringing chord
                    [62, 64, 66, 67, 69, 71, 73, 74, 76, 78, 79, 81, 83, 85, 86]
                        .forEach((n, i) => harp(midi(n), { at: t + i * 0.03, bus, gain: 0.05, dur: 1.2 }));
                    choir([50, 57, 62, 66, 69].map(midi), { at: t, bus, gain: 0.18, dur: 3, attack: 0.55 });
                    pad([38, 45, 50].map(midi), { at: t, bus, gain: 0.08, dur: 3, attack: 0.5 });
                    swell({ at: t, bus, gain: 0.05, dur: 0.45, from: 200, to: 2200 });
                    strike({ at: t + 0.45, bus, gain: 0.25 });
                    splash({ at: t + 0.45, bus, gain: 0.05, dur: 0.6 });
                    [86, 90, 93].forEach((n) => glass(midi(n), { at: t + 0.45, bus, gain: 0.04, dur: 1.8 }));
                    bubbles(10, { at: t + 0.5, bus, length: 1.2, gain: 0.025 });
                    break;
                case 'fsEnd': // back to the shallows: a harp cadence settling on a warm chord
                    [81, 79, 76, 73].forEach((n, i) => harp(midi(n), { at: t + i * 0.14, bus, gain: 0.07, dur: 1 }));
                    strum([62, 66, 69, 74, 78], { at: t + 0.56, bus, gap: 0.03, gain: 0.07, dur: 1.8 });
                    pad([38, 50, 57, 62].map(midi), { at: t + 0.5, bus, gain: 0.06, dur: 2.2, attack: 0.3 });
                    glass(midi(86), { at: t + 0.62, bus, gain: 0.04, dur: 1.8 });
                    break;
                case 'big': { // a big win (arg: big | mega | epic | max): a strike, a splash, a fanfare and a
                    // shower of droplets over the deep pad. Mega adds the choir and a second strike and
                    // fanfare, higher; epic the water rising and the drums quickening into a third strike;
                    // max a last splash and fanfare, higher still.
                    const level = { mega: 1, epic: 2, max: 3 }[arg] ?? 0;
                    strike({ at: t, bus, gain: 0.27 });
                    splash({ at: t, bus, gain: 0.06, dur: 0.6 });
                    fanfare([62, 66, 69, 74, 78, 81, 86], { at: t, bus, gap: 0.07, gain: 0.09 });
                    pad([38, 50, 57, 62].map(midi), { at: t, bus, gain: 0.07, dur: 2.4, attack: 0.25 });
                    droplets(18 + level * 10, { at: t + 0.1, bus, length: 1.4 + level * 0.4, gain: 0.035 });
                    if (level >= 1) {
                        choir([50, 57, 62, 66, 69].map(midi), { at: t, bus, gain: 0.1, dur: 2.6, attack: 0.5 });
                        strike({ at: t + 0.6, bus, gain: 0.24 });
                        fanfare([74, 78, 81, 86, 90], { at: t + 0.6, bus, gap: 0.07, gain: 0.08 });
                    }
                    if (level >= 2) {
                        swell({ at: t + 0.7, bus, gain: 0.06, dur: 0.6, from: 220, to: 2600 });
                        pulse({ at: t + 0.7, to: t + 1.3, bus });
                        strike({ at: t + 1.3, bus, gain: 0.22 });
                        choir([55, 62, 67, 71, 74].map(midi), { at: t + 1.3, bus, gain: 0.08, dur: 2, attack: 0.1 });
                        strum([55, 62, 67, 71, 74, 79], { at: t + 1.3, bus, gap: 0.025, gain: 0.05, dur: 1.4 });
                    }
                    if (level >= 3) {
                        splash({ at: t + 1.9, bus, gain: 0.05, dur: 0.7 });
                        fanfare([86, 90, 93, 98, 102], { at: t + 1.9, bus, gap: 0.06, gain: 0.06 });
                        droplets(20, { at: t + 1.9, bus, length: 1.2, gain: 0.035 });
                    }
                    break;
                }
                case 'tick': { // the win counting up (arg: how far, 0..1): a soft glassy plink, higher as it climbs
                    const p = Math.max(0, Math.min(1, num(arg)));
                    plink(1100 + 1500 * p, { at: t, bus, gain: 0.02 + 0.016 * p, dur: 0.25 });
                    break;
                }
                case 'bigEnd': { // the count lands on the win (arg: its tier): a strike, a splash, the choir's chord and a bell
                    const level = { mega: 1, epic: 2, max: 3 }[arg] ?? 0;
                    strike({ at: t, bus, gain: 0.28 });
                    splash({ at: t, bus, gain: 0.05 + level * 0.008, dur: 0.6 });
                    choir([50, 57, 62, 66, 69].map(midi), { at: t, bus, gain: 0.11 + level * 0.01, dur: 1.4 + level * 0.3, attack: 0.08 });
                    strum([62, 66, 69, 74], { at: t, bus, gap: 0.02, gain: 0.06, dur: 1.4 });
                    glass(midi(86), { at: t + 0.05, bus, gain: 0.05, dur: 1.8 });
                    droplets(8 + level * 6, { at: t + 0.05, bus, length: 0.8 });
                    break;
                }
                case 'intro': // in time with the intro: the logo falls and lands with a strike and a splash,
                    // bubbles rise, pearls chime, Poseidon appears (a soft rising whoosh) and a high chime glints
                    whoosh({ at: t + 0.1, bus, gain: 0.06, dur: 0.4, from: 3000, to: 450 });
                    strike({ at: t + 0.5, bus, gain: 0.22 });
                    splash({ at: t + 0.5, bus, gain: 0.045, dur: 0.6 });
                    fanfare([62, 66, 69, 74, 78, 81], { at: t + 0.5, bus, gap: 0.07, gain: 0.065 });
                    bubbles(8, { at: t + 0.55, bus, length: 0.7, gain: 0.03 });
                    whoosh({ at: t + 0.7, bus, gain: 0.035, dur: 0.6, from: 500, to: 2400 });
                    glass(midi(98), { at: t + 1.0, bus, gain: 0.05, dur: 0.8 });
                    glass(midi(103), { at: t + 1.08, bus, gain: 0.04, dur: 0.8 });
                    strum([74, 78, 81, 86], { at: t + 1.3, bus, gap: 0.03, gain: 0.06, dur: 1.2 });
                    glass(midi(105), { at: t + 1.5, bus, gain: 0.035, dur: 1.2 });
                    break;
                case 'click': // a button: a tiny tap and a bubble
                    thud(280, { at: t, bus, gain: 0.035 });
                    bubble(480, { at: t, bus, gain: 0.03, dur: 0.07, wet: 0.05 });
                    break;
            }
        }

        // ---- The background music ----

        function ambience() {
            const nodes = [];
            const keep = (n) => (nodes.push(n), n);
            const t0 = ctx.currentTime;

            const dry = keep(ctx.createGain());
            const wet = keep(ctx.createGain());
            for (const g of [dry, wet]) {
                g.gain.setValueAtTime(0.0001, t0);
                g.gain.exponentialRampToValueAtTime(LEVEL.ambience, t0 + 2);
            }
            dry.connect(glue()).connect(master);
            wet.connect(reverb());
            const bus = { dry, wet };

            // Under the music: the sea itself, a slow wash whose filter drifts like the swell.
            const sea = keep(ctx.createBufferSource());
            const seaFilter = keep(ctx.createBiquadFilter());
            const seaGain = keep(ctx.createGain());
            const drift = keep(ctx.createOscillator());
            const driftDepth = keep(ctx.createGain());
            sea.buffer = white;
            sea.loop = true;
            seaFilter.type = 'lowpass';
            seaFilter.frequency.value = 520;
            seaFilter.Q.value = 0.7;
            drift.frequency.value = 0.09;
            driftDepth.gain.value = 220;
            drift.connect(driftDepth).connect(seaFilter.frequency);
            seaGain.gain.value = 0.012;
            sea.connect(seaFilter).connect(seaGain);
            out(seaGain, bus, 0.4);
            sea.start(t0);
            drift.start(t0);

            let level = 0;
            let step = 0;
            let next = t0 + 0.3;
            let stopped = false;

            function playStep(at) {
                const s = step % 16;
                const bar = BARS[Math.floor(step / 16) % BARS.length];
                const barLength = STEP[level] * 16;
                // Each bar: the deep pad (and in free spins, the choir) swells in under the chord.
                if (s === 0) {
                    pad(bar.chord.map((n) => midi(n - 12)), { at, bus, gain: 0.03, dur: barLength * 1.1, attack: barLength * 0.35, bright: 700 });
                    if (level >= 1) choir(bar.chord.map(midi), { at, bus, gain: 0.04, dur: barLength * 1.05, attack: barLength * 0.4 });
                }
                if (s in BASS) bass(midi(bar.root + BASS[s]), { at, bus, gain: 0.07, dur: barLength * 0.4 });
                // The harp turns over the chord on the eighths (every sixteenth in free spins).
                if (s % 2 === 0 || level >= 1) {
                    harp(midi(bar.chord[ARP[s]] + 12), { at, bus, gain: s % 4 === 0 ? 0.03 : 0.022, dur: 1, wet: 0.35 });
                }
                // Now and then a bubble drifts up.
                if (s === 6 || s === 13) bubble(500 + Math.random() * 700, { at, bus, gain: 0.012, dur: 0.08 });
                // Free spins: the deep drum's heartbeat and the glass-bell tune, an eighth at a time.
                if (level >= 1 && (s === 0 || s === 6)) drum({ at, bus, gain: 0.12, freq: 55 });
                if (level >= 1 && s % 2 === 0) {
                    const note = TUNE[(step / 2) % TUNE.length];
                    if (note) glass(midi(note + 12), { at, bus, gain: 0.025, dur: 1 });
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

            /** 0 the shallows · 1 free spins in the abyss. */
            function intensity(n) {
                const lv = Math.max(0, Math.min(1, Math.floor(num(n))));
                if (lv > level && !stopped) splash({ at: ctx.currentTime + 0.02, bus, gain: 0.05, dur: 0.9, from: 1800, to: 250 });
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
                setTimeout(() => { dry.disconnect(); wet.disconnect(); }, 700);
            }

            return { stop, schedule, intensity };
        }

        return { sfx, ambience };
    }

    window.OrangeSound = Object.assign(window.OrangeSound || {}, { tides });
})();
