/**
 * "Jackpot Reef" sound pack for the crash game (the "jackpot" theme): sunny and a
 * little cheesy: steel drum, marimba, coins, a cash register, bubbles, a slide whistle
 * and a sad trombone, through a short bright reverb. Everything is synthesised.
 *
 *   const pack = window.OrangeSound.jackpot(ctx, master);
 *   pack.sfx('win');                   // tick | bet | start | win | loss | crash
 *   const bg = pack.ambience();        // continuous background, until bg.stop()
 *
 * Same contract as sound-gothic.js: the ambience schedules ahead on the audio clock
 * (bg.schedule() runs every 250 ms).
 */
(function () {
    'use strict';

    const midi = (n) => 440 * Math.pow(2, (n - 69) / 12);

    // A bouncy island loop at ~104 bpm, one eighth note per step, eight steps a bar:
    // C | Am | F | G, twice, with a steel-drum tune on top.
    const CHORDS = [[60, 64, 67], [57, 60, 64], [53, 57, 60], [55, 59, 62]];
    const ROOTS = [48, 45, 41, 43];
    const MELODY = [
        76, 0, 79, 0, 81, 79, 76, 0,
        72, 0, 76, 0, 74, 72, 69, 0,
        72, 0, 77, 0, 76, 74, 72, 0,
        74, 0, 71, 0, 74, 0, 79, 0,
        76, 0, 79, 0, 84, 0, 81, 79,
        81, 0, 79, 76, 0, 74, 76, 0,
        77, 0, 76, 74, 0, 72, 74, 0,
        71, 0, 74, 0, 79, 0, 0, 0,
    ];
    const EIGHTH = 0.29; // seconds
    // Measured like the Gothic pack: effects peak around -20 to -7 dBFS, the
    // background sits near -31 dBFS RMS.
    const LEVEL = { sfx: 2.0, ambience: 3.3 };

    function jackpot(ctx, master) {
        let reverbIn = null;
        let effects = null;

        /** A short, bright plate: a convolver over 1.6 s of decaying noise. */
        function reverb() {
            if (reverbIn) return reverbIn;
            const len = Math.floor(ctx.sampleRate * 1.6);
            const ir = ctx.createBuffer(2, len, ctx.sampleRate);
            for (let ch = 0; ch < 2; ch++) {
                const d = ir.getChannelData(ch);
                for (let i = 0; i < len; i++) d[i] = (Math.random() * 2 - 1) * Math.pow(1 - i / len, 2.2);
            }
            reverbIn = ctx.createConvolver();
            reverbIn.buffer = ir;
            const ret = ctx.createGain();
            ret.gain.value = 0.45;
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

        function noiseBuffer(seconds) {
            const len = Math.max(1, Math.floor(ctx.sampleRate * seconds));
            const buf = ctx.createBuffer(1, len, ctx.sampleRate);
            const d = buf.getChannelData(0);
            for (let i = 0; i < len; i++) d[i] = Math.random() * 2 - 1;
            return buf;
        }

        /** Filtered noise burst (shaker, whoosh, crack). */
        function hiss({ at, bus, gain = 0.1, dur = 0.08, type = 'highpass', freq = 6000, to = null, q = 0.8, wet = 0.15 }) {
            const src = ctx.createBufferSource();
            const f = ctx.createBiquadFilter();
            const g = ctx.createGain();
            src.buffer = noiseBuffer(dur);
            f.type = type;
            f.Q.value = q;
            f.frequency.setValueAtTime(freq, at);
            if (to) f.frequency.exponentialRampToValueAtTime(to, at + dur);
            env(g, at, gain, Math.min(0.01, dur / 4), dur);
            src.connect(f).connect(g);
            out(g, bus, wet);
            src.start(at);
            src.stop(at + dur + 0.02);
        }

        // ---- Instruments (at = audio-clock time) ----

        /** Steel drum: fundamental, a strong octave and a twelfth, with a soft mallet click. */
        function steel(freq, { at, bus, gain = 0.08, dur = 0.9, wet = 0.3 }) {
            const v = voice(bus, wet);
            partial(freq, 'sine', at, gain, 0.004, dur, v);
            partial(freq * 2, 'sine', at, gain * 0.55, 0.004, dur * 0.7, v);
            partial(freq * 3, 'sine', at, gain * 0.22, 0.003, dur * 0.4, v);
            partial(freq * 4.2, 'sine', at, gain * 0.08, 0.002, 0.08, v);
        }

        /** Marimba: a round fundamental and a quick bright fourth partial. */
        function marimba(freq, { at, bus, gain = 0.1, dur = 0.5, wet = 0.2 }) {
            const v = voice(bus, wet);
            partial(freq, 'sine', at, gain, 0.003, dur, v);
            partial(freq * 3.93, 'sine', at, gain * 0.25, 0.002, dur * 0.18, v);
        }

        /** A coin: a short metallic clink of inharmonic partials. */
        function coin({ at, bus, gain = 0.05, wet = 0.25 }) {
            const v = voice(bus, wet);
            const f = 1800 + Math.random() * 900;
            [[1, 1, 0.22], [2.76, 0.5, 0.14], [5.4, 0.25, 0.08], [8.93, 0.12, 0.05]]
                .forEach(([ratio, amp, d]) => partial(f * ratio, 'sine', at, gain * amp, 0.001, d, v));
        }

        /** A bubble: a sine that pops upward. */
        function bubble({ at, bus, gain = 0.06, wet = 0.25 }) {
            const o = ctx.createOscillator();
            const g = ctx.createGain();
            const f0 = 260 + Math.random() * 260;
            o.type = 'sine';
            o.frequency.setValueAtTime(f0, at);
            o.frequency.exponentialRampToValueAtTime(f0 * 3, at + 0.07);
            env(g, at, gain, 0.005, 0.09);
            o.connect(g);
            out(g, bus, wet);
            o.start(at);
            o.stop(at + 0.12);
        }

        /** Slide whistle: a vibrato glide. */
        function slide(from, to, { at, bus, gain = 0.06, dur = 0.9, wet = 0.2 }) {
            const o = ctx.createOscillator();
            const vib = ctx.createOscillator();
            const depth = ctx.createGain();
            const g = ctx.createGain();
            o.type = 'triangle';
            o.frequency.setValueAtTime(from, at);
            o.frequency.exponentialRampToValueAtTime(to, at + dur);
            vib.frequency.value = 7;
            depth.gain.value = from * 0.015;
            vib.connect(depth).connect(o.frequency);
            g.gain.setValueAtTime(0.0001, at);
            g.gain.exponentialRampToValueAtTime(gain, at + 0.04);
            g.gain.setValueAtTime(gain, at + dur * 0.8);
            g.gain.exponentialRampToValueAtTime(0.0001, at + dur);
            o.connect(g);
            out(g, bus, wet);
            o.start(at);
            vib.start(at);
            o.stop(at + dur + 0.05);
            vib.stop(at + dur + 0.05);
        }

        /** Muted trombone: a sawtooth through a wah filter, with vibrato. */
        function trombone(freq, { at, bus, gain = 0.07, dur = 0.35, vibrato = 0, wet = 0.2 }) {
            const o = ctx.createOscillator();
            const f = ctx.createBiquadFilter();
            const g = ctx.createGain();
            o.type = 'sawtooth';
            o.frequency.value = freq;
            f.type = 'lowpass';
            f.Q.value = 6;
            f.frequency.setValueAtTime(400, at);
            f.frequency.exponentialRampToValueAtTime(1400, at + 0.08);
            f.frequency.exponentialRampToValueAtTime(500, at + dur);
            if (vibrato) {
                const vib = ctx.createOscillator();
                const depth = ctx.createGain();
                vib.frequency.value = 5.5;
                depth.gain.value = freq * vibrato;
                vib.connect(depth).connect(o.frequency);
                vib.start(at);
                vib.stop(at + dur + 0.05);
            }
            g.gain.setValueAtTime(0.0001, at);
            g.gain.exponentialRampToValueAtTime(gain, at + 0.03);
            g.gain.setValueAtTime(gain, at + dur * 0.75);
            g.gain.exponentialRampToValueAtTime(0.0001, at + dur);
            o.connect(f).connect(g);
            out(g, bus, wet);
            o.start(at);
            o.stop(at + dur + 0.05);
        }

        /** Cartoon boom: a sine dropping into the sub-bass over a burst of rumble. */
        function boom({ at, bus, gain = 0.3 }) {
            const o = ctx.createOscillator();
            const g = ctx.createGain();
            o.type = 'sine';
            o.frequency.setValueAtTime(140, at);
            o.frequency.exponentialRampToValueAtTime(35, at + 0.6);
            env(g, at, gain, 0.005, 0.8);
            o.connect(g);
            out(g, bus, 0.2);
            o.start(at);
            o.stop(at + 0.85);
            hiss({ at, bus, gain: gain * 0.8, dur: 1.2, type: 'lowpass', freq: 400, to: 90, q: 0.5, wet: 0.3 });
        }

        // ---- Effects ----

        function effectsBus() {
            if (effects) return effects;
            const dry = ctx.createGain();
            const wet = ctx.createGain();
            dry.gain.value = LEVEL.sfx;
            wet.gain.value = LEVEL.sfx;
            dry.connect(master);
            wet.connect(reverb());
            effects = { dry, wet };
            return effects;
        }

        function sfx(name) {
            const t = ctx.currentTime + 0.01;
            const bus = effectsBus();
            switch (name) {
                case 'tick': // betting opens: a steel-drum "ding-ding"
                    steel(midi(79), { at: t, bus, gain: 0.09 });
                    steel(midi(84), { at: t + 0.12, bus, gain: 0.09 });
                    break;
                case 'bet': // a coin dropped in, and a marimba blip
                    coin({ at: t, bus, gain: 0.07 });
                    coin({ at: t + 0.05, bus, gain: 0.05 });
                    marimba(midi(84), { at: t + 0.02, bus, gain: 0.1, dur: 0.35 });
                    break;
                case 'start': // the dive: a whoosh, a marimba run down and a stream of bubbles
                    hiss({ at: t, bus, gain: 0.12, dur: 0.6, type: 'bandpass', freq: 2500, to: 300, q: 1.2 });
                    [84, 81, 77, 74, 72, 67].forEach((n, i) => marimba(midi(n), { at: t + i * 0.06, bus, gain: 0.14 }));
                    for (let i = 0; i < 7; i++) bubble({ at: t + 0.15 + i * 0.07 + Math.random() * 0.03, bus, gain: 0.05 });
                    break;
                case 'win': // ka-ching! a register bell, a shower of coins and a steel-drum fanfare
                    partial(midi(96), 'sine', t, 0.08, 0.002, 0.9, voice(bus, 0.3));
                    partial(midi(103), 'sine', t + 0.06, 0.07, 0.002, 1.1, voice(bus, 0.3));
                    hiss({ at: t, bus, gain: 0.08, dur: 0.12, freq: 4000 });
                    for (let i = 0; i < 12; i++) coin({ at: t + 0.12 + Math.random() * 0.6, bus, gain: 0.03 + Math.random() * 0.03 });
                    [72, 76, 79, 84].forEach((n, i) => steel(midi(n), { at: t + 0.2 + i * 0.08, bus, gain: 0.09 }));
                    steel(midi(88), { at: t + 0.52, bus, gain: 0.08, dur: 1.4 });
                    break;
                case 'loss': // the sad trombone: wah, wah, wah, waaah
                    [[58, 0.32], [57, 0.32], [56, 0.32]].forEach(([n, d], i) => trombone(midi(n), { at: t + i * 0.36, bus, gain: 0.1, dur: d }));
                    trombone(midi(55), { at: t + 1.08, bus, gain: 0.1, dur: 1.1, vibrato: 0.02 });
                    break;
                case 'crash': // boom, a porthole cracks, coins everywhere, and a slide whistle down
                    boom({ at: t, bus, gain: 0.32 });
                    hiss({ at: t, bus, gain: 0.18, dur: 0.15, freq: 3000 });
                    for (let i = 0; i < 22; i++) coin({ at: t + 0.05 + Math.random() * 0.9, bus, gain: 0.025 + Math.random() * 0.035 });
                    slide(1400, 180, { at: t + 0.15, bus, gain: 0.06, dur: 1.1 });
                    for (let i = 0; i < 6; i++) bubble({ at: t + 0.4 + i * 0.12, bus, gain: 0.04 });
                    break;
            }
        }

        // ---- Continuous background ----

        function ambience() {
            const nodes = [];
            const keep = (n) => (nodes.push(n), n);
            const t0 = ctx.currentTime;

            const dry = keep(ctx.createGain());
            const wet = keep(ctx.createGain());
            for (const g of [dry, wet]) {
                g.gain.setValueAtTime(0.0001, t0);
                g.gain.exponentialRampToValueAtTime(LEVEL.ambience, t0 + 2.5);
            }
            dry.connect(master);
            wet.connect(reverb());
            const bus = { dry, wet };

            // Layer 1: the sea itself, a soft low wash rising and falling like swell.
            const sea = keep(ctx.createBufferSource());
            const seaFilter = keep(ctx.createBiquadFilter());
            const seaGain = keep(ctx.createGain());
            sea.buffer = noiseBuffer(4);
            sea.loop = true;
            seaFilter.type = 'lowpass';
            seaFilter.frequency.value = 520;
            seaGain.gain.value = 0.03;
            sea.connect(seaFilter).connect(seaGain);
            out(seaGain, bus, 0.2);
            const swell = keep(ctx.createOscillator());
            const swellDepth = keep(ctx.createGain());
            swell.frequency.value = 0.12;
            swellDepth.gain.value = 0.018;
            swell.connect(swellDepth).connect(seaGain.gain);
            sea.start(t0);
            swell.start(t0);

            // Layer 2: the band (marimba bass, offbeat chords, shaker, steel-drum tune)
            // and now and then a few bubbles. Scheduled ahead on the audio clock.
            let step = 0;
            let next = t0 + 1.2;
            let nextBubbles = t0 + 4 + Math.random() * 4;
            let stopped = false;

            function schedule() {
                if (stopped) return;
                const horizon = ctx.currentTime + 1.5;
                while (next < horizon) {
                    const beat = step % 8;
                    const chord = Math.floor(step / 8) % 4;
                    // Bass on the tresillo (1, the "and" of 2, 4).
                    if (beat === 0 || beat === 3 || beat === 6) {
                        marimba(midi(ROOTS[chord] + (beat === 3 ? 7 : 0)), { at: next, bus, gain: 0.11, dur: 0.45 });
                    }
                    // Chord stabs on the offbeats.
                    if (beat === 2 || beat === 6) {
                        CHORDS[chord].forEach((n) => marimba(midi(n), { at: next, bus, gain: 0.028, dur: 0.22, wet: 0.25 }));
                    }
                    // Shaker on every eighth, the offbeats louder.
                    hiss({ at: next, bus, gain: beat % 2 ? 0.026 : 0.014, dur: 0.05, freq: 7000, wet: 0.05 });
                    // The tune, with a few notes left out each time round.
                    const note = MELODY[step % MELODY.length];
                    if (note && Math.random() > 0.1) steel(midi(note), { at: next, bus, gain: 0.06, dur: 0.7 });
                    step += 1;
                    next += EIGHTH;
                }
                if (nextBubbles < horizon) {
                    const n = 2 + Math.floor(Math.random() * 3);
                    for (let i = 0; i < n; i++) bubble({ at: nextBubbles + i * 0.09, bus, gain: 0.03 });
                    nextBubbles += 5 + Math.random() * 6;
                }
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

            return { stop, schedule };
        }

        return { sfx, ambience };
    }

    window.OrangeSound = Object.assign(window.OrangeSound || {}, { jackpot });
})();
