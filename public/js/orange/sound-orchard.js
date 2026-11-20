/**
 * "Sugar Orchard" sound pack for the slot: sunny and sweet. A little orchard band (marimba,
 * pizzicato strings, a glockenspiel tune, upright-ish bass, shaker and claps) plays the
 * background; the effects are wooden thunks, juicy pops, chimes, bells and fanfares.
 * All synthesised, same shape as the crash packs:
 *
 *   const pack = window.OrangeSound.orchard(ctx, master);
 *   pack.sfx('pop');                    // spin | land | win | buzz | flame | pop | scatter | star | stars |
 *                                       // retrigger | fsStart | fsEnd | big | tick | click | intro
 *   pack.sfx('scatter', 3);             // the third scatter: each one rings higher
 *   const bg = pack.ambience();         // continuous background, until bg.stop()
 *   bg.intensity(1);                    // 0 base game · 1 free spins
 *
 * The music schedules ahead on the audio clock (bg.schedule() runs every 250 ms).
 */
(function () {
    'use strict';

    const midi = (n) => 440 * Math.pow(2, (n - 69) / 12);

    // Eight bars of sixteen sixteenths in F major: F | C | Dm | Bb | F | C | Bb C | F.
    const BARS = [
        { chord: [65, 69, 72], root: 41 },
        { chord: [64, 67, 72], root: 36 },
        { chord: [62, 65, 69], root: 38 },
        { chord: [62, 65, 70], root: 46 },
        { chord: [65, 69, 72], root: 41 },
        { chord: [64, 67, 72], root: 36 },
        { chord: [65, 70, 74], root: 46 },
        { chord: [65, 69, 72], root: 41 },
    ];
    // The glockenspiel tune, one entry per eighth (0 = rest); played in free spins.
    const TUNE = [
        77, 0, 81, 84, 0, 81, 79, 0, 76, 0, 79, 84, 0, 79, 76, 0,
        74, 0, 77, 81, 0, 77, 74, 0, 74, 0, 77, 82, 0, 81, 79, 0,
        77, 0, 81, 84, 0, 86, 84, 0, 76, 0, 79, 84, 0, 88, 84, 0,
        82, 0, 81, 79, 0, 77, 76, 0, 77, 0, 0, 0, 0, 0, 0, 0,
    ];
    // Bass: sixteenth → interval above the root (a walking, bouncy line).
    const BASS = { 0: 0, 4: 7, 6: 12, 8: 0, 12: 7, 14: 5 };
    // Sixteenth length: ~104 bpm in the base game, ~122 bpm in free spins.
    const STEP = [0.144, 0.123];
    const LEVEL = { sfx: 2.0, ambience: 1.6 };

    function orchard(ctx, master) {
        let reverbIn = null;
        let effects = null;

        const white = ctx.createBuffer(1, Math.floor(ctx.sampleRate * 2), ctx.sampleRate);
        {
            const w = white.getChannelData(0);
            for (let i = 0; i < w.length; i++) w[i] = Math.random() * 2 - 1;
        }

        /** A warm room: a convolver over 1.4 s of decaying noise. */
        function reverb() {
            if (reverbIn) return reverbIn;
            const len = Math.floor(ctx.sampleRate * 1.4);
            const ir = ctx.createBuffer(2, len, ctx.sampleRate);
            for (let ch = 0; ch < 2; ch++) {
                const d = ir.getChannelData(ch);
                for (let i = 0; i < len; i++) d[i] = (Math.random() * 2 - 1) * Math.pow(1 - i / len, 2.6);
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
            if (freq >= ctx.sampleRate / 2.2) return;
            const o = ctx.createOscillator();
            const g = ctx.createGain();
            o.type = type;
            o.frequency.value = freq;
            env(g, at, peak, attack, dur);
            o.connect(g).connect(dest);
            o.start(at);
            o.stop(at + dur + 0.05);
        }

        function voice(bus, wet) {
            const g = ctx.createGain();
            out(g, bus, wet);
            return g;
        }

        function hiss({ at, bus, gain = 0.1, dur = 0.08, type = 'highpass', freq = 6000, to = null, q = 0.8, wet = 0.15 }) {
            const src = ctx.createBufferSource();
            const f = ctx.createBiquadFilter();
            const g = ctx.createGain();
            src.buffer = white;
            f.type = type;
            f.Q.value = q;
            f.frequency.setValueAtTime(freq, at);
            if (to) f.frequency.exponentialRampToValueAtTime(to, at + dur);
            env(g, at, gain, Math.min(0.005, dur / 4), dur);
            src.connect(f).connect(g);
            out(g, bus, wet);
            src.start(at, Math.random() * (white.duration - dur - 0.05));
            src.stop(at + dur + 0.02);
        }

        // ---- Instruments ----

        function marimba(freq, { at, bus, gain = 0.1, dur = 0.45, wet = 0.2 }) {
            const v = voice(bus, wet);
            partial(freq, 'sine', at, gain, 0.003, dur, v);
            partial(freq * 3.93, 'sine', at, gain * 0.25, 0.002, dur * 0.18, v);
        }

        /** Glockenspiel: bright, bell-like inharmonic partials. */
        function glock(freq, { at, bus, gain = 0.06, dur = 0.9, wet = 0.35 }) {
            const v = voice(bus, wet);
            partial(freq, 'sine', at, gain, 0.002, dur, v);
            partial(freq * 2.76, 'sine', at, gain * 0.35, 0.002, dur * 0.5, v);
            partial(freq * 5.4, 'sine', at, gain * 0.12, 0.001, dur * 0.25, v);
        }

        /** Pizzicato string: a filtered triangle plucked short. */
        function pizz(freq, { at, bus, gain = 0.07, dur = 0.22, wet = 0.2 }) {
            const o = ctx.createOscillator();
            const f = ctx.createBiquadFilter();
            const g = ctx.createGain();
            o.type = 'triangle';
            o.frequency.value = freq;
            f.type = 'lowpass';
            f.frequency.setValueAtTime(freq * 6, at);
            f.frequency.exponentialRampToValueAtTime(freq * 1.5, at + dur);
            env(g, at, gain, 0.003, dur);
            o.connect(f).connect(g);
            out(g, bus, wet);
            o.start(at);
            o.stop(at + dur + 0.05);
        }

        function bass(freq, { at, bus, gain = 0.12, dur = 0.26 }) {
            const v = voice(bus, 0.05);
            partial(freq, 'sine', at, gain, 0.004, dur, v);
            partial(freq * 2, 'triangle', at, gain * 0.25, 0.004, dur * 0.6, v);
        }

        function kick({ at, bus, gain = 0.3 }) {
            const o = ctx.createOscillator();
            const g = ctx.createGain();
            o.type = 'sine';
            o.frequency.setValueAtTime(130, at);
            o.frequency.exponentialRampToValueAtTime(45, at + 0.1);
            env(g, at, gain, 0.002, 0.25);
            o.connect(g);
            out(g, bus, 0);
            o.start(at);
            o.stop(at + 0.3);
        }

        function clap({ at, bus, gain = 0.1 }) {
            for (const d of [0, 0.01, 0.021]) hiss({ at: at + d, bus, gain, dur: 0.02, type: 'bandpass', freq: 1500, q: 0.9, wet: 0.3 });
            hiss({ at: at + 0.03, bus, gain: gain * 0.5, dur: 0.12, type: 'bandpass', freq: 1300, q: 0.7, wet: 0.35 });
        }

        function cymbal({ at, bus, gain = 0.07 }) {
            hiss({ at, bus, gain, dur: 1.4, freq: 5000, wet: 0.4 });
        }

        /** A wooden thunk: a low knock with a woody body. */
        function thunk(freq, { at, bus, gain = 0.12 }) {
            const v = voice(bus, 0.1);
            partial(freq, 'sine', at, gain, 0.002, 0.12, v);
            partial(freq * 2.3, 'triangle', at, gain * 0.3, 0.001, 0.05, v);
            hiss({ at, bus, gain: gain * 0.25, dur: 0.03, type: 'bandpass', freq: 900, q: 1.5, wet: 0 });
        }

        /** A juicy pop: a sine that jumps upward with a wet click. */
        function pop({ at, bus, gain = 0.08 }) {
            const o = ctx.createOscillator();
            const g = ctx.createGain();
            const f0 = 380 + Math.random() * 300;
            o.type = 'sine';
            o.frequency.setValueAtTime(f0, at);
            o.frequency.exponentialRampToValueAtTime(f0 * 2.6, at + 0.06);
            env(g, at, gain, 0.003, 0.09);
            o.connect(g);
            out(g, bus, 0.2);
            o.start(at);
            o.stop(at + 0.12);
            hiss({ at, bus, gain: gain * 0.4, dur: 0.02, freq: 3000, wet: 0 });
        }

        function coin({ at, bus, gain = 0.04 }) {
            const v = voice(bus, 0.25);
            const f = 2000 + Math.random() * 800;
            [[1, 1, 0.2], [2.76, 0.5, 0.12], [5.4, 0.25, 0.07]].forEach(([r, a, d]) => partial(f * r, 'sine', at, gain * a, 0.001, d, v));
        }

        function whoosh({ at, bus, gain = 0.08, dur = 0.35, from = 400, to = 3000 }) {
            hiss({ at, bus, gain, dur, type: 'bandpass', freq: from, to, q: 1.2, wet: 0.2 });
        }

        /** A slot machine's heavy button: the latch's sharp click, the thud of the mechanism, the latch springing back. */
        function clack({ at, bus, gain = 0.16 }) {
            hiss({ at, bus, gain: gain * 0.7, dur: 0.012, freq: 5200, wet: 0.04 });
            thunk(92, { at: at + 0.01, bus, gain });
            partial(46, 'sine', at + 0.01, gain * 0.9, 0.003, 0.14, voice(bus, 0.05));
            hiss({ at: at + 0.06, bus, gain: gain * 0.4, dur: 0.018, type: 'bandpass', freq: 1900, q: 2.5, wet: 0.05 });
        }

        /** The reels' motor: a low geared hum that spins up, holds, and winds down as they slow. */
        function motor({ at, bus, gain = 0.05, dur = 0.85 }) {
            const o = ctx.createOscillator();
            const f = ctx.createBiquadFilter();
            const g = ctx.createGain();
            o.type = 'sawtooth';
            o.frequency.setValueAtTime(42, at);
            o.frequency.exponentialRampToValueAtTime(118, at + 0.28);
            o.frequency.exponentialRampToValueAtTime(90, at + dur);
            f.type = 'lowpass';
            f.frequency.value = 650;
            f.Q.value = 2.5;
            g.gain.setValueAtTime(0.0001, at);
            g.gain.exponentialRampToValueAtTime(gain, at + 0.12);
            g.gain.setValueAtTime(gain, at + dur * 0.6);
            g.gain.exponentialRampToValueAtTime(0.0001, at + dur);
            o.connect(f).connect(g);
            out(g, bus, 0.08);
            o.start(at);
            o.stop(at + dur + 0.05);
        }

        /** A bee flying past: a reedy hum that trembles with its wings and bends as it goes by. */
        function buzz({ at, bus, gain = 0.03, dur = 0.55, freq = 220 }) {
            const o = ctx.createOscillator();
            const lfo = ctx.createOscillator();
            const depth = ctx.createGain();
            const f = ctx.createBiquadFilter();
            const g = ctx.createGain();
            o.type = 'sawtooth';
            o.frequency.setValueAtTime(freq * 0.9, at);
            o.frequency.exponentialRampToValueAtTime(freq * 1.12, at + dur * 0.45);
            o.frequency.exponentialRampToValueAtTime(freq * 0.86, at + dur);
            lfo.frequency.value = 24 + Math.random() * 10;
            depth.gain.value = freq * 0.05;
            lfo.connect(depth).connect(o.frequency);
            f.type = 'bandpass';
            f.frequency.value = 1100;
            f.Q.value = 1.4;
            env(g, at, gain, dur * 0.3, dur);
            o.connect(f).connect(g);
            out(g, bus, 0.12);
            o.start(at);
            lfo.start(at);
            o.stop(at + dur + 0.05);
            lfo.stop(at + dur + 0.05);
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

        function fanfare(notes, { at, bus, gap = 0.09, gain = 0.08 }) {
            notes.forEach((n, i) => {
                glock(midi(n + 12), { at: at + i * gap, bus, gain });
                marimba(midi(n), { at: at + i * gap, bus, gain: gain * 1.2 });
            });
        }

        function sfx(name, arg = 0) {
            const t = ctx.currentTime + 0.01;
            const bus = effectsBus();
            switch (name) {
                case 'spin': { // like a real machine: the button's heavy clack, then the reels' motor
                    // whirring up under a ratchet that ticks faster and faster as they gather speed
                    clack({ at: t, bus });
                    motor({ at: t + 0.05, bus });
                    let at = t + 0.09;
                    for (let gap = 0.075; at < t + 0.85; gap = Math.max(0.026, gap * 0.9)) {
                        hiss({ at, bus, gain: 0.05 * (0.7 + Math.random() * 0.3), dur: 0.012, type: 'bandpass', freq: 2600 + Math.random() * 600, q: 3, wet: 0.05 });
                        at += gap;
                    }
                    whoosh({ at: t + 0.1, bus, gain: 0.035, dur: 0.5, from: 1800, to: 500 });
                    break;
                }
                case 'flame': { // a good win catches fire: a roaring whoosh, and burning sugar crackling
                    whoosh({ at: t, bus, gain: 0.09, dur: 0.6, from: 300, to: 1400 });
                    hiss({ at: t, bus, gain: 0.05, dur: 1.1, type: 'lowpass', freq: 900, wet: 0.1 });
                    for (let i = 0; i < 14; i++) {
                        hiss({ at: t + 0.05 + Math.random() * 1.1, bus, gain: 0.03 + Math.random() * 0.04, dur: 0.008 + Math.random() * 0.01, freq: 3000 + Math.random() * 3000, wet: 0.05 });
                    }
                    break;
                }
                case 'land': // one column lands: a wooden thunk, a touch higher each column
                    thunk(150 + Number(arg || 0) * 12, { at: t, bus, gain: 0.11 });
                    break;
                case 'win': { // a tumble pays: a marimba run up and a sparkle
                    const base = 72 + Math.min(7, Number(arg || 0)) * 2;
                    [0, 4, 7, 12].forEach((d, i) => marimba(midi(base + d), { at: t + i * 0.05, bus, gain: 0.1, dur: 0.35 }));
                    glock(midi(base + 24), { at: t + 0.2, bus, gain: 0.05 });
                    break;
                }
                case 'buzz': { // the Queen Bee's bees fly out: a little swarm, a voice for every few bees
                    const n = Math.max(1, Math.min(3, Number(arg || 1)));
                    for (let i = 0; i < n; i++) buzz({ at: t + i * 0.07, bus, freq: 190 + i * 41 + Math.random() * 20 });
                    break;
                }
                case 'pop': // the winners burst
                    for (let i = 0; i < 5; i++) pop({ at: t + i * 0.025 + Math.random() * 0.02, bus, gain: 0.07 });
                    break;
                case 'scatter': { // a basket lands: a bell, higher for each one on screen
                    const n = Math.max(1, Math.min(6, Number(arg || 1)));
                    glock(midi([72, 76, 79, 84, 88, 91][n - 1]), { at: t, bus, gain: 0.1, dur: 1.2 });
                    marimba(midi([60, 64, 67, 72, 76, 79][n - 1]), { at: t, bus, gain: 0.1 });
                    break;
                }
                case 'star': // a multiplier star lands: a glassy chime
                    glock(midi(96), { at: t, bus, gain: 0.06, dur: 0.7 });
                    glock(midi(101), { at: t + 0.05, bus, gain: 0.04, dur: 0.6 });
                    break;
                case 'stars': // the stars fly to the win and multiply it
                    whoosh({ at: t, bus, gain: 0.08, dur: 0.45, from: 500, to: 5000 });
                    fanfare([72, 76, 79, 84], { at: t + 0.4, bus, gap: 0.06, gain: 0.07 });
                    cymbal({ at: t + 0.6, bus, gain: 0.06 });
                    break;
                case 'retrigger':
                    fanfare([79, 84, 88], { at: t, bus, gap: 0.08 });
                    clap({ at: t + 0.24, bus });
                    break;
                case 'fsStart': // free spins: a drum roll and a fanfare
                    for (let i = 0; i < 8; i++) clap({ at: t + i * 0.05, bus, gain: 0.04 + i * 0.01 });
                    kick({ at: t + 0.42, bus, gain: 0.35 });
                    cymbal({ at: t + 0.42, bus, gain: 0.08 });
                    fanfare([65, 69, 72, 77, 81, 84], { at: t + 0.42, bus, gap: 0.08, gain: 0.09 });
                    break;
                case 'fsEnd': // a cadence home
                    fanfare([72, 70, 69, 65], { at: t, bus, gap: 0.14, gain: 0.08 });
                    break;
                case 'big': { // a big win: fanfare, cymbal and a shower of coins
                    kick({ at: t, bus, gain: 0.35 });
                    cymbal({ at: t, bus, gain: 0.09 });
                    fanfare([65, 69, 72, 77, 81, 84, 89], { at: t, bus, gap: 0.07, gain: 0.1 });
                    for (let i = 0; i < 18; i++) coin({ at: t + 0.1 + Math.random() * 1.4, bus, gain: 0.03 + Math.random() * 0.03 });
                    break;
                }
                case 'tick': // the win counting up
                    coin({ at: t, bus, gain: 0.025 });
                    break;
                case 'intro': // in time with the intro: the logo falls and lands, fruit pops up, stars chime,
                    // the Queen Bee flies in (a soft rising whoosh) and winks (a high chime)
                    whoosh({ at: t + 0.1, bus, gain: 0.07, dur: 0.4, from: 3200, to: 450 });
                    whoosh({ at: t + 0.7, bus, gain: 0.04, dur: 0.55, from: 600, to: 2600 });
                    glock(midi(103), { at: t + 1.5, bus, gain: 0.045, dur: 0.9 });
                    kick({ at: t + 0.5, bus, gain: 0.35 });
                    cymbal({ at: t + 0.5, bus, gain: 0.08 });
                    fanfare([65, 69, 72, 77, 81, 84], { at: t + 0.5, bus, gap: 0.07, gain: 0.09 });
                    [0.7, 0.78, 0.86, 0.94].forEach((at) => pop({ at: t + at, bus, gain: 0.08 }));
                    glock(midi(96), { at: t + 1.0, bus, gain: 0.06, dur: 0.8 });
                    glock(midi(101), { at: t + 1.08, bus, gain: 0.05, dur: 0.8 });
                    [77, 81, 84, 89].forEach((n) => marimba(midi(n), { at: t + 1.3, bus, gain: 0.07, dur: 0.6 }));
                    glock(midi(101), { at: t + 1.3, bus, gain: 0.04, dur: 1.2 });
                    break;
                case 'click':
                    thunk(420, { at: t, bus, gain: 0.05 });
                    break;
            }
        }

        // ---- The background band ----

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

            // Under the band: a soft summer-breeze wash.
            const air = keep(ctx.createBufferSource());
            const airFilter = keep(ctx.createBiquadFilter());
            const airGain = keep(ctx.createGain());
            air.buffer = white;
            air.loop = true;
            airFilter.type = 'bandpass';
            airFilter.frequency.value = 900;
            airFilter.Q.value = 0.4;
            airGain.gain.value = 0.006;
            air.connect(airFilter).connect(airGain);
            out(airGain, bus, 0.3);
            air.start(t0);

            let level = 0;
            let step = 0;
            let next = t0 + 0.3;
            let stopped = false;

            function playStep(at) {
                const s = step % 16;
                const bar = BARS[Math.floor(step / 16) % BARS.length];
                // Shaker on every eighth; claps on 2 and 4 in free spins.
                if (s % 2 === 0) hiss({ at, bus, gain: s % 4 === 2 ? 0.016 : 0.008, dur: 0.04, freq: 7000, wet: 0.05 });
                if (level >= 1 && (s === 4 || s === 12)) clap({ at, bus, gain: 0.06 });
                if (level >= 1 && (s === 0 || s === 8)) kick({ at, bus, gain: 0.16 });
                if (s in BASS) bass(midi(bar.root + BASS[s]), { at, bus, gain: 0.09 });
                // Pizzicato chords on the offbeats; a marimba arpeggio running underneath.
                if (s === 2 || s === 6 || s === 10 || s === 14) bar.chord.forEach((n) => pizz(midi(n), { at, bus, gain: 0.025 }));
                if (s % 2 === 0) marimba(midi(bar.chord[[0, 1, 2, 1][(s / 2) % 4]] + 12), { at, bus, gain: level ? 0.035 : 0.028, dur: 0.25, wet: 0.2 });
                // Free spins: the glockenspiel tune, an eighth at a time.
                if (level >= 1 && s % 2 === 0) {
                    const note = TUNE[(step / 2) % TUNE.length];
                    if (note) glock(midi(note), { at, bus, gain: 0.04, dur: 0.6 });
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

            /** 0 base game · 1 free spins. */
            function intensity(n) {
                const lv = Math.max(0, Math.min(1, Math.floor(Number(n) || 0)));
                if (lv > level && !stopped) cymbal({ at: ctx.currentTime + 0.02, bus, gain: 0.06 });
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

    window.OrangeSound = Object.assign(window.OrangeSound || {}, { orchard });
})();
