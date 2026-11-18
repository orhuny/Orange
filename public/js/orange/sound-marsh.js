/**
 * "Lucky Duck" sound pack for the crash game (the marsh scene, "luckyduck" skin): a cheerful
 * bluegrass band (banjo rolls, slap bass, washboard, a whistled tune once he's flying high)
 * over the marsh air; the effects are quacks, wing flaps and a splash, coins, a deflating
 * kazoo and the fox's blunderbuss. All synthesised.
 *
 *   const pack = window.OrangeSound.marsh(ctx, master);
 *   pack.sfx('win');                   // tick | bet | start | win | loss | crash | shot
 *   const bg = pack.ambience();        // continuous background, until bg.stop()
 *   bg.intensity(2);                   // 0 betting · 1 flying · 2 golden · 3 sunset and dusk
 *
 * The music schedules ahead on the audio clock (bg.schedule() runs every 250 ms), so it keeps
 * time in a background tab. Intensity changes take effect on the next eighth; going up also
 * brings a banjo run up into the next bar.
 */
(function () {
    'use strict';

    const midi = (n) => 440 * Math.pow(2, (n - 69) / 12);

    // Eight bars of eight eighths, I-IV-V in G: G | G | C | G | G | D | G | G.
    const G = { chord: [55, 59, 62], root: 43, fifth: 50 };
    const C = { chord: [55, 60, 64], root: 48, fifth: 55 };
    const D = { chord: [54, 57, 62], root: 50, fifth: 45 };
    const BARS = [G, G, C, G, G, D, G, G];
    // The banjo's forward roll over a chord (indexes into the chord; 'd' = the high drone string).
    const ROLL = [0, 1, 'd', 2, 1, 'd', 2, 'd'];
    const DRONE = 67;
    // The whistled tune, one note per quarter (0 = rest), once he's flying high.
    const TUNE = [
        71, 74, 76, 74, 71, 69, 67, 0,
        72, 72, 76, 72, 71, 69, 67, 0,
        71, 74, 76, 79, 78, 76, 74, 0,
        74, 71, 69, 66, 67, 0, 67, 0,
    ];
    // Eighth length per intensity: ~158 bpm while betting, up to ~222 bpm at dusk.
    const STEP = [0.19, 0.165, 0.15, 0.135];
    const LEVEL = { sfx: 1.8, ambience: 1.8 };

    function marsh(ctx, master) {
        let reverbIn = null;
        let effects = null;

        // One white-noise and one brown-noise buffer, sliced by every noisy sound.
        const white = ctx.createBuffer(1, Math.floor(ctx.sampleRate * 2), ctx.sampleRate);
        const brown = ctx.createBuffer(1, Math.floor(ctx.sampleRate * 2), ctx.sampleRate);
        {
            const w = white.getChannelData(0);
            const b = brown.getChannelData(0);
            let last = 0;
            for (let i = 0; i < w.length; i++) {
                w[i] = Math.random() * 2 - 1;
                last = (last + 0.02 * w[i]) / 1.02;
                b[i] = last * 3.5;
            }
        }

        /** An open-air room: a convolver over 1.1 s of decaying noise (a barn, not a hall). */
        function reverb() {
            if (reverbIn) return reverbIn;
            const len = Math.floor(ctx.sampleRate * 1.1);
            const ir = ctx.createBuffer(2, len, ctx.sampleRate);
            for (let ch = 0; ch < 2; ch++) {
                const d = ir.getChannelData(ch);
                for (let i = 0; i < len; i++) d[i] = (Math.random() * 2 - 1) * Math.pow(1 - i / len, 2.8);
            }
            reverbIn = ctx.createConvolver();
            reverbIn.buffer = ir;
            const ret = ctx.createGain();
            ret.gain.value = 0.35;
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

        /** A gentle bus compressor, so stacked hits never clip. */
        function glue() {
            const c = ctx.createDynamicsCompressor();
            c.threshold.value = -18;
            c.knee.value = 6;
            c.ratio.value = 8;
            c.attack.value = 0.001;
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

        /** Filtered noise (washboard, splashes, whooshes, the shot). */
        function hiss({ at, bus, gain = 0.1, dur = 0.08, type = 'highpass', freq = 6000, to = null, q = 0.8, wet = 0.15, buffer = white }) {
            const src = ctx.createBufferSource();
            const f = ctx.createBiquadFilter();
            const g = ctx.createGain();
            src.buffer = buffer;
            f.type = type;
            f.Q.value = q;
            f.frequency.setValueAtTime(freq, at);
            if (to) f.frequency.exponentialRampToValueAtTime(to, at + dur);
            env(g, at, gain, 0.003, dur);
            src.connect(f).connect(g);
            out(g, bus, wet);
            src.start(at, Math.random());
            src.stop(at + dur + 0.02);
        }

        // ---- The band (at = audio-clock time) ----

        /** Banjo: a bright, quickly dying pluck: a stack of partials through the drum head's ring. */
        function banjo(freq, { at, bus, gain = 0.06, dur = 0.32, wet = 0.15 }) {
            const head = ctx.createBiquadFilter();
            head.type = 'peaking';
            head.frequency.value = 1500;
            head.Q.value = 1.2;
            head.gain.value = 9;
            const v = voice(bus, wet);
            head.connect(v);
            [[1, 1, 1], [2, 0.6, 0.7], [3, 0.45, 0.5], [4, 0.28, 0.35], [5, 0.16, 0.25], [6.1, 0.08, 0.15]]
                .forEach(([ratio, amp, d]) => partial(freq * ratio, 'triangle', at, gain * amp, 0.002, dur * d, head));
            hiss({ at, bus, gain: gain * 0.25, dur: 0.012, freq: 4000, wet: 0 }); // the pick
        }

        /** Upright bass, slapped: a round low note with a click. */
        function bass(freq, { at, bus, gain = 0.12, dur = 0.26 }) {
            const v = voice(bus, 0.05);
            partial(freq, 'sine', at, gain, 0.004, dur, v);
            partial(freq * 2, 'triangle', at, gain * 0.35, 0.003, dur * 0.5, v);
            hiss({ at, bus, gain: gain * 0.2, dur: 0.015, type: 'bandpass', freq: 1800, q: 1.5, wet: 0 });
        }

        /** Washboard: a short bright scrape; long = the thimble run down the board. */
        function washboard({ at, bus, gain = 0.03, long = false }) {
            hiss({ at, bus, gain, dur: long ? 0.11 : 0.04, type: 'bandpass', freq: long ? 5200 : 3800, to: long ? 2600 : null, q: 2.5, wet: 0.05 });
        }

        /** A whistled note: a sine with a breathy edge and a little vibrato. */
        function whistle(freq, { at, bus, gain = 0.035, dur = 0.3, wet = 0.3 }) {
            const o = ctx.createOscillator();
            const vib = ctx.createOscillator();
            const depth = ctx.createGain();
            const g = ctx.createGain();
            o.type = 'sine';
            o.frequency.setValueAtTime(freq * 0.97, at);
            o.frequency.exponentialRampToValueAtTime(freq, at + 0.04);
            vib.frequency.value = 6;
            depth.gain.value = freq * 0.012;
            vib.connect(depth).connect(o.frequency);
            g.gain.setValueAtTime(0.0001, at);
            g.gain.exponentialRampToValueAtTime(gain, at + 0.03);
            g.gain.setValueAtTime(gain, at + dur * 0.7);
            g.gain.exponentialRampToValueAtTime(0.0001, at + dur);
            o.connect(g);
            out(g, bus, wet);
            o.start(at);
            vib.start(at);
            o.stop(at + dur + 0.05);
            vib.stop(at + dur + 0.05);
            hiss({ at, bus, gain: gain * 0.15, dur: dur * 0.8, type: 'bandpass', freq: freq * 2, q: 4, wet });
        }

        // ---- Effects ----

        /** A quack: a nasal saw dropping in pitch through two formants. `mood` < 1 is a sad one. */
        function quack({ at, bus, gain = 0.07, pitch = 1, dur = 0.16, mood = 1 }) {
            const o = ctx.createOscillator();
            const f1 = ctx.createBiquadFilter();
            const f2 = ctx.createBiquadFilter();
            const g = ctx.createGain();
            const f0 = 520 * pitch;
            o.type = 'sawtooth';
            o.frequency.setValueAtTime(f0 * (mood < 1 ? 1 : 1.15), at);
            o.frequency.exponentialRampToValueAtTime(f0 * (mood < 1 ? 0.55 : 0.8), at + dur);
            f1.type = 'bandpass';
            f1.frequency.value = 1100;
            f1.Q.value = 3;
            f2.type = 'peaking';
            f2.frequency.value = 2600;
            f2.Q.value = 2;
            f2.gain.value = 8;
            g.gain.setValueAtTime(0.0001, at);
            g.gain.exponentialRampToValueAtTime(gain, at + 0.012);
            g.gain.setValueAtTime(gain * 0.8, at + dur * 0.6);
            g.gain.exponentialRampToValueAtTime(0.0001, at + dur);
            o.connect(f1).connect(f2).connect(g);
            out(g, bus, 0.15);
            o.start(at);
            o.stop(at + dur + 0.05);
        }

        /** A wing beat: a soft low whoosh. */
        function flap({ at, bus, gain = 0.06 }) {
            hiss({ at, bus, gain, dur: 0.09, type: 'bandpass', freq: 500, to: 1400, q: 0.8, wet: 0.05, buffer: brown });
        }

        /** A splash: bright noise falling, with droplets. */
        function splash({ at, bus, gain = 0.08 }) {
            hiss({ at, bus, gain, dur: 0.35, type: 'bandpass', freq: 3000, to: 700, q: 0.7, wet: 0.25 });
            for (let i = 0; i < 5; i++) {
                const t = at + 0.08 + Math.random() * 0.3;
                partial(900 + Math.random() * 1400, 'sine', t, gain * 0.25, 0.002, 0.05, voice(bus, 0.2));
            }
        }

        /** A coin: a short metallic clink of inharmonic partials. */
        function coin({ at, bus, gain = 0.05, wet = 0.2 }) {
            const v = voice(bus, wet);
            const f = 1800 + Math.random() * 900;
            [[1, 1, 0.22], [2.76, 0.5, 0.14], [5.4, 0.25, 0.08], [8.93, 0.12, 0.05]]
                .forEach(([ratio, amp, d]) => partial(f * ratio, 'sine', at, gain * amp, 0.001, d, v));
        }

        /** Kazoo: a buzzy saw with vibrato, gliding; deflating when it glides down. */
        function kazoo(from, to, { at, bus, gain = 0.06, dur = 0.5 }) {
            const o = ctx.createOscillator();
            const vib = ctx.createOscillator();
            const depth = ctx.createGain();
            const f = ctx.createBiquadFilter();
            const g = ctx.createGain();
            o.type = 'sawtooth';
            o.frequency.setValueAtTime(from, at);
            o.frequency.exponentialRampToValueAtTime(to, at + dur);
            vib.frequency.value = 7;
            depth.gain.value = from * 0.02;
            vib.connect(depth).connect(o.frequency);
            f.type = 'bandpass';
            f.frequency.value = 1300;
            f.Q.value = 1.5;
            g.gain.setValueAtTime(0.0001, at);
            g.gain.exponentialRampToValueAtTime(gain, at + 0.03);
            g.gain.setValueAtTime(gain, at + dur * 0.8);
            g.gain.exponentialRampToValueAtTime(0.0001, at + dur);
            o.connect(f).connect(g);
            out(g, bus, 0.15);
            o.start(at);
            vib.start(at);
            o.stop(at + dur + 0.05);
            vib.stop(at + dur + 0.05);
        }

        /** The blunderbuss: a hammer click, a wide crack, a low thump and its echo off the water. */
        // The gun's own bus, built once: a soft clipper (tanh) that gives the blast its punch and
        // keeps its peak in hand, then the marsh's answer: a slapback off the far bank and the
        // echoes after it, each darker and quieter (a delay with a dark, fading feedback loop).
        let gunIn = null;
        function gun(bus) {
            if (gunIn) return gunIn;
            gunIn = ctx.createGain();
            const clip = ctx.createWaveShaper();
            const curve = new Float32Array(2048);
            for (let i = 0; i < curve.length; i++) {
                const x = (i / (curve.length - 1)) * 2 - 1;
                curve[i] = Math.tanh(2.4 * x) / Math.tanh(2.4);
            }
            clip.curve = curve;
            clip.oversample = '4x';
            const level = ctx.createGain();
            level.gain.value = 0.62;
            gunIn.connect(clip).connect(level);
            out(level, bus, 0.18);
            const delay = ctx.createDelay(1);
            delay.delayTime.value = 0.21;
            const dark = ctx.createBiquadFilter();
            dark.type = 'lowpass';
            dark.frequency.value = 1100;
            const feedback = ctx.createGain();
            feedback.gain.value = 0.36;
            const echo = ctx.createGain();
            echo.gain.value = 0.42;
            level.connect(delay).connect(dark);
            dark.connect(feedback).connect(delay);
            dark.connect(echo);
            out(echo, bus, 0.35);
            return gunIn;
        }

        /** Filtered noise into the gun bus: a burst that snaps open and dies away. */
        function burst(into, { at, dur, type, freq, to = null, q = 0.7, peak, attack = 0.0008, buffer = white }) {
            const src = ctx.createBufferSource();
            const f = ctx.createBiquadFilter();
            const g = ctx.createGain();
            src.buffer = buffer;
            f.type = type;
            f.Q.value = q;
            f.frequency.setValueAtTime(freq, at);
            if (to) f.frequency.exponentialRampToValueAtTime(to, at + dur);
            g.gain.setValueAtTime(0.0001, at);
            g.gain.exponentialRampToValueAtTime(peak, at + attack);
            g.gain.exponentialRampToValueAtTime(0.0001, at + dur);
            src.connect(f).connect(g).connect(into);
            src.start(at, Math.random());
            src.stop(at + dur + 0.02);
        }

        /** The blunderbuss, in layers: the flintlock (the hammer's snap, the priming pan's hiss),
         *  then the report (a broadband crack, the blast's body darkening as it dies, a low thump),
         *  and the rumble rolling over the marsh; the gun bus adds the punch and the echoes. The
         *  report lands 25 ms after the snap, with the flash on screen. */
        function shot({ at, bus, gain = 0.3 }) {
            const into = gun(bus);
            burst(into, { at, dur: 0.012, type: 'bandpass', freq: 3600, q: 5, peak: gain * 0.5 });
            burst(into, { at: at + 0.006, dur: 0.03, type: 'highpass', freq: 5500, peak: gain * 0.25, attack: 0.003 });
            const b = at + 0.025;
            burst(into, { at: b, dur: 0.035, type: 'highpass', freq: 1400, q: 0.5, peak: gain * 2.6 });
            burst(into, { at: b, dur: 0.38, type: 'lowpass', freq: 7000, to: 320, q: 0.6, peak: gain * 1.9, attack: 0.001 });
            const o = ctx.createOscillator();
            const g = ctx.createGain();
            o.type = 'triangle';
            o.frequency.setValueAtTime(170, b);
            o.frequency.exponentialRampToValueAtTime(40, b + 0.3);
            g.gain.setValueAtTime(0.0001, b);
            g.gain.exponentialRampToValueAtTime(gain * 2.6, b + 0.004);
            g.gain.exponentialRampToValueAtTime(0.0001, b + 0.45);
            o.connect(g).connect(into);
            o.start(b);
            o.stop(b + 0.5);
            burst(into, { at: b + 0.05, dur: 1.5, type: 'lowpass', freq: 520, to: 90, q: 0.5, peak: gain * 0.6, attack: 0.07, buffer: brown });
        }

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

        function sfx(name) {
            const t = ctx.currentTime + 0.01;
            const bus = effectsBus();
            switch (name) {
                case 'tick': // betting opens: a banjo "ding-ding-ding" up and a quack
                    [67, 71, 74, 79].forEach((n, i) => banjo(midi(n), { at: t + i * 0.06, bus, gain: 0.07 }));
                    quack({ at: t + 0.3, bus, gain: 0.05, pitch: 1.1 });
                    break;
                case 'bet': // a coin in the jar and a banjo plink
                    coin({ at: t, bus, gain: 0.07 });
                    coin({ at: t + 0.05, bus, gain: 0.05 });
                    banjo(midi(79), { at: t + 0.03, bus, gain: 0.07, dur: 0.25 });
                    break;
                case 'start': { // take-off: a quack, wing beats, a splash and a banjo run up
                    quack({ at: t, bus, gain: 0.07, pitch: 1.05 });
                    quack({ at: t + 0.18, bus, gain: 0.06, pitch: 1.2, dur: 0.12 });
                    for (let i = 0; i < 6; i++) flap({ at: t + 0.1 + i * 0.09, bus, gain: 0.05 + i * 0.006 });
                    splash({ at: t + 0.12, bus, gain: 0.08 });
                    [55, 59, 62, 67, 71, 74, 79].forEach((n, i) => banjo(midi(n), { at: t + 0.25 + i * 0.045, bus, gain: 0.06 }));
                    break;
                }
                case 'win': // KA-CHING: a register bell, coins, a happy quack and a banjo lick
                    partial(midi(96), 'sine', t, 0.08, 0.002, 0.9, voice(bus, 0.3));
                    partial(midi(103), 'sine', t + 0.06, 0.07, 0.002, 1.1, voice(bus, 0.3));
                    hiss({ at: t, bus, gain: 0.08, dur: 0.12, freq: 4000 });
                    for (let i = 0; i < 14; i++) coin({ at: t + 0.1 + Math.random() * 0.7, bus, gain: 0.03 + Math.random() * 0.03 });
                    quack({ at: t + 0.2, bus, gain: 0.06, pitch: 1.25, dur: 0.12 });
                    [67, 71, 74, 76, 79, 83].forEach((n, i) => banjo(midi(n), { at: t + 0.3 + i * 0.06, bus, gain: 0.07 }));
                    break;
                case 'loss': // the kazoo deflates
                    kazoo(midi(62), midi(60), { at: t, bus, gain: 0.06, dur: 0.25 });
                    kazoo(midi(60), midi(59), { at: t + 0.28, bus, gain: 0.06, dur: 0.25 });
                    kazoo(midi(59), midi(43), { at: t + 0.56, bus, gain: 0.06, dur: 0.9 });
                    break;
                case 'shot': // a miss: the blunderbuss (lighter than the hit), the spray whizzing past, a cheeky quack
                    shot({ at: t, bus, gain: 0.12 });
                    hiss({ at: t + 0.12, bus, gain: 0.05, dur: 0.35, type: 'bandpass', freq: 3200, to: 900, q: 3, wet: 0.2 });
                    quack({ at: t + 0.5, bus, gain: 0.045, pitch: 1.2, dur: 0.1 });
                    break;
                case 'crash': { // the shot, then (at the page's --boom-at) the hit: a poof, a sad quack, feathers and a slide down
                    shot({ at: t, bus, gain: 0.3 });
                    const hit = t + 0.18;
                    hiss({ at: hit, bus, gain: 0.12, dur: 0.4, type: 'lowpass', freq: 1400, to: 300, q: 0.6, wet: 0.3, buffer: brown }); // poof
                    quack({ at: hit + 0.08, bus, gain: 0.08, pitch: 1.3, dur: 0.22, mood: 0 });
                    for (let i = 0; i < 8; i++) hiss({ at: hit + 0.15 + i * 0.07, bus, gain: 0.02, dur: 0.12, type: 'bandpass', freq: 2400 + Math.random() * 2000, q: 1.5, wet: 0.2 }); // feathers
                    kazoo(midi(79), midi(48), { at: hit + 0.9, bus, gain: 0.05, dur: 1.1 }); // the fall
                    break;
                }
            }
        }

        // ---- Continuous background: the band in the marsh ----

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
            const comp = glue();
            dry.connect(comp).connect(master);
            wet.connect(reverb());
            const bus = { dry, wet };

            // Under the band: the marsh air, a soft breeze rising and falling.
            const air = keep(ctx.createBufferSource());
            const airFilter = keep(ctx.createBiquadFilter());
            const airGain = keep(ctx.createGain());
            air.buffer = brown;
            air.loop = true;
            airFilter.type = 'lowpass';
            airFilter.frequency.value = 700;
            airGain.gain.value = 0.018;
            air.connect(airFilter).connect(airGain);
            out(airGain, bus, 0.1);
            const gust = keep(ctx.createOscillator());
            const gustDepth = keep(ctx.createGain());
            gust.frequency.value = 0.09;
            gustDepth.gain.value = 0.01;
            gust.connect(gustDepth).connect(airGain.gain);
            air.start(t0);
            gust.start(t0);

            let level = 0;
            let runNext = false;
            let step = 0;
            let next = t0 + 0.5;
            let nextFrog = t0 + 3 + Math.random() * 4;
            let stopped = false;

            function playStep(at) {
                const s = step % 8;
                const barIndex = Math.floor(step / 8) % BARS.length;
                const bar = BARS[barIndex];

                // A banjo run up into the bar after stepping up.
                if (s === 0 && runNext) {
                    [62, 67, 71, 74].forEach((n, i) => banjo(midi(n), { at: at - 0.12 + i * 0.03, bus, gain: 0.04 }));
                    runNext = false;
                }
                // Bass: root and fifth on the beats (only the root while betting).
                if (s === 0 || (s === 4 && level >= 1)) {
                    bass(midi(s === 0 ? bar.root : bar.fifth), { at, bus, gain: 0.12 });
                }
                if (level >= 2 && s === 6) bass(midi(bar.root + 12), { at, bus, gain: 0.07, dur: 0.15 });
                // Washboard: the backbeat, then every eighth, the long scrape into each bar.
                if (s === 2 || s === 6) washboard({ at, bus, gain: level ? 0.035 : 0.02 });
                else if (level >= 2) washboard({ at, bus, gain: 0.015 });
                if (level >= 1 && s === 7) washboard({ at, bus, gain: 0.025, long: true });
                // Banjo: the roll (half of it while betting).
                if (level >= 1 || s % 2 === 0) {
                    const r = ROLL[s];
                    const note = r === 'd' ? DRONE : bar.chord[r] + (level >= 3 && s === 3 ? 12 : 0);
                    banjo(midi(note), { at, bus, gain: (r === 'd' ? 0.03 : 0.045) * (level ? 1 : 0.7) });
                }
                // The whistled tune once he's flying high, a note per quarter.
                if (level >= 2 && s % 2 === 0) {
                    const note = TUNE[(Math.floor(step / 2)) % TUNE.length];
                    if (note) whistle(midi(note + (level >= 3 ? 12 : 0)), { at, bus, gain: 0.03, dur: STEP[level] * 1.8 });
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
                // A frog now and then while the duck floats.
                if (level === 0 && nextFrog < horizon) {
                    quack({ at: nextFrog, bus, gain: 0.025, pitch: 0.32, dur: 0.12 });
                    quack({ at: nextFrog + 0.16, bus, gain: 0.02, pitch: 0.3, dur: 0.1 });
                    nextFrog += 6 + Math.random() * 7;
                }
            }

            /** 0 betting · 1 flying · 2 golden · 3 sunset and dusk. */
            function intensity(n) {
                const lv = Math.max(0, Math.min(3, Math.floor(Number(n) || 0)));
                if (lv > level && !stopped) runNext = true;
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
                setTimeout(() => { dry.disconnect(); wet.disconnect(); comp.disconnect(); }, 700);
            }

            return { stop, schedule, intensity };
        }

        return { sfx, ambience };
    }

    window.OrangeSound = Object.assign(window.OrangeSound || {}, { marsh });
})();
