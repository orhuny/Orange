/**
 * "Jackpot Reef" sound pack for the crash game (the "jackpot" theme): lively and a
 * little cheesy. A tropical pop band (drums, bass, brass stabs, a steel-drum hook,
 * marimba) plays the background and follows the dive; the effects are coins, a cash
 * register, fanfares, bubbles, a slide whistle and a sad trombone. All synthesised.
 *
 *   const pack = window.OrangeSound.jackpot(ctx, master);
 *   pack.sfx('win');                   // tick | bet | start | win | loss | crash
 *   const bg = pack.ambience();        // continuous background, until bg.stop()
 *   bg.intensity(2);                   // 0 betting · 1 diving · 2 deep · 3 abyss
 *
 * The music schedules ahead on the audio clock (bg.schedule() runs every 250 ms), so
 * it keeps time in a background tab. Intensity changes take effect on the next step;
 * going up also brings a rising sweep and a cymbal on the next bar.
 */
(function () {
    'use strict';

    const midi = (n) => 440 * Math.pow(2, (n - 69) / 12);

    // Eight bars of sixteen sixteenths: C | G | Am | F | C | G | F | G.
    const BARS = [
        { chord: [60, 64, 67], root: 36 },
        { chord: [59, 62, 67], root: 43 },
        { chord: [57, 60, 64], root: 45 },
        { chord: [57, 60, 65], root: 41 },
        { chord: [60, 64, 67], root: 36 },
        { chord: [59, 62, 67], root: 43 },
        { chord: [57, 60, 65], root: 41 },
        { chord: [59, 62, 67], root: 43 },
    ];
    // The steel-drum hook, one entry per sixteenth (0 = rest).
    const HOOK = [
        76, 0, 79, 0, 84, 0, 0, 81, 79, 0, 76, 0, 0, 0, 0, 0,
        74, 0, 79, 0, 83, 0, 0, 81, 79, 0, 74, 0, 0, 0, 0, 0,
        72, 0, 76, 0, 81, 0, 0, 79, 76, 0, 72, 0, 0, 0, 74, 0,
        77, 0, 81, 0, 84, 0, 0, 81, 79, 0, 77, 0, 76, 0, 74, 0,
        76, 0, 79, 0, 84, 0, 86, 0, 88, 0, 0, 86, 84, 0, 0, 0,
        83, 0, 86, 0, 83, 0, 79, 0, 81, 0, 0, 79, 0, 0, 0, 0,
        81, 0, 84, 0, 81, 0, 77, 0, 79, 0, 81, 0, 79, 0, 0, 0,
        74, 0, 79, 0, 83, 0, 86, 0, 79, 0, 0, 0, 0, 0, 0, 0,
    ];
    // Bass: sixteenth → interval above the root (a bouncy, syncopated line).
    const BASS = { 0: 0, 3: 0, 6: 7, 8: 12, 10: 0, 14: 7 };
    // Kick and hi-hat patterns per intensity.
    const KICK = [[], [0, 8, 11], [0, 4, 8, 11, 12], [0, 4, 8, 11, 12, 14]];
    // Sixteenth length per intensity: ~111 bpm while betting, up to ~136 bpm in the abyss.
    const STEP = [0.135, 0.125, 0.117, 0.11];
    // Bus levels, measured: effects peak around -20 to -7 dBFS; the band sits near
    // -30 dBFS RMS, under the effects but clearly there.
    const LEVEL = { sfx: 2.0, ambience: 2.0 };

    function jackpot(ctx, master) {
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

        /** A gentle bus compressor, so stacked hits (kick + bass + brass) never clip. */
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

        /** Filtered noise (shaker, hats, claps, whooshes, rumbles). */
        function hiss({ at, bus, gain = 0.1, dur = 0.08, type = 'highpass', freq = 6000, to = null, q = 0.8, wet = 0.15, buffer = white }) {
            const src = ctx.createBufferSource();
            const f = ctx.createBiquadFilter();
            const g = ctx.createGain();
            src.buffer = buffer;
            f.type = type;
            f.Q.value = q;
            f.frequency.setValueAtTime(freq, at);
            if (to) f.frequency.exponentialRampToValueAtTime(to, at + dur);
            env(g, at, gain, Math.min(0.005, dur / 4), dur);
            src.connect(f).connect(g);
            out(g, bus, wet);
            src.start(at, Math.random() * (buffer.duration - dur - 0.05));
            src.stop(at + dur + 0.02);
        }

        // ---- Drums ----

        function kick({ at, bus, gain = 0.5 }) {
            const o = ctx.createOscillator();
            const g = ctx.createGain();
            o.type = 'sine';
            o.frequency.setValueAtTime(150, at);
            o.frequency.exponentialRampToValueAtTime(42, at + 0.11);
            env(g, at, gain, 0.002, 0.32);
            o.connect(g);
            out(g, bus, 0);
            o.start(at);
            o.stop(at + 0.36);
            hiss({ at, bus, gain: gain * 0.12, dur: 0.012, freq: 3000, wet: 0 });
        }

        function clap({ at, bus, gain = 0.14 }) {
            for (const d of [0, 0.011, 0.022]) hiss({ at: at + d, bus, gain, dur: 0.02, type: 'bandpass', freq: 1500, q: 0.9, wet: 0.3 });
            hiss({ at: at + 0.03, bus, gain: gain * 0.6, dur: 0.14, type: 'bandpass', freq: 1300, q: 0.7, wet: 0.35 });
        }

        function hat({ at, bus, gain = 0.03, open = false }) {
            hiss({ at, bus, gain, dur: open ? 0.22 : 0.035, freq: 8000, wet: 0.05 });
        }

        function cymbal({ at, bus, gain = 0.09 }) {
            hiss({ at, bus, gain, dur: 1.6, freq: 4500, wet: 0.4 });
            const v = voice(bus, 0.3);
            [3200, 4180, 5490, 7020].forEach((f, i) => partial(f, 'square', at, gain * 0.05 / (i + 1), 0.002, 0.9, v));
        }

        function tom(freq, { at, bus, gain = 0.25 }) {
            const o = ctx.createOscillator();
            const g = ctx.createGain();
            o.type = 'sine';
            o.frequency.setValueAtTime(freq, at);
            o.frequency.exponentialRampToValueAtTime(freq * 0.55, at + 0.2);
            env(g, at, gain, 0.003, 0.28);
            o.connect(g);
            out(g, bus, 0.15);
            o.start(at);
            o.stop(at + 0.32);
        }

        /** A rising noise sweep (the music stepping up). */
        function riser({ at, bus, gain = 0.07, dur = 0.9 }) {
            const src = ctx.createBufferSource();
            const f = ctx.createBiquadFilter();
            const g = ctx.createGain();
            src.buffer = white;
            f.type = 'bandpass';
            f.Q.value = 2;
            f.frequency.setValueAtTime(400, at);
            f.frequency.exponentialRampToValueAtTime(6000, at + dur);
            g.gain.setValueAtTime(0.0001, at);
            g.gain.exponentialRampToValueAtTime(gain, at + dur * 0.95);
            g.gain.exponentialRampToValueAtTime(0.0001, at + dur + 0.05);
            src.connect(f).connect(g);
            out(g, bus, 0.3);
            src.start(at, 0.1);
            src.stop(at + dur + 0.08);
        }

        // ---- Instruments (at = audio-clock time) ----

        /** Steel drum: fundamental, a strong octave and a twelfth. */
        function steel(freq, { at, bus, gain = 0.08, dur = 0.8, wet = 0.3 }) {
            const v = voice(bus, wet);
            partial(freq, 'sine', at, gain, 0.003, dur, v);
            partial(freq * 2, 'sine', at, gain * 0.55, 0.003, dur * 0.7, v);
            partial(freq * 3, 'sine', at, gain * 0.22, 0.002, dur * 0.4, v);
            partial(freq * 4.2, 'sine', at, gain * 0.08, 0.002, 0.08, v);
        }

        /** Marimba: a round fundamental and a quick bright fourth partial. */
        function marimba(freq, { at, bus, gain = 0.1, dur = 0.45, wet = 0.2 }) {
            const v = voice(bus, wet);
            partial(freq, 'sine', at, gain, 0.003, dur, v);
            partial(freq * 3.93, 'sine', at, gain * 0.25, 0.002, dur * 0.18, v);
        }

        /** Plucky synth bass: a sawtooth whose filter opens and snaps shut. */
        function bass(freq, { at, bus, gain = 0.1, dur = 0.22 }) {
            const o = ctx.createOscillator();
            const f = ctx.createBiquadFilter();
            const g = ctx.createGain();
            o.type = 'sawtooth';
            o.frequency.value = freq;
            f.type = 'lowpass';
            f.Q.value = 2.5;
            f.frequency.setValueAtTime(260, at);
            f.frequency.exponentialRampToValueAtTime(1300, at + 0.02);
            f.frequency.exponentialRampToValueAtTime(320, at + dur);
            env(g, at, gain, 0.004, dur);
            o.connect(f).connect(g);
            out(g, bus, 0.05);
            o.start(at);
            o.stop(at + dur + 0.05);
        }

        /** Brass stab: two detuned saws per note through a lowpass, short and punchy. */
        function stab(notes, { at, bus, gain = 0.05, dur = 0.18 }) {
            const f = ctx.createBiquadFilter();
            const g = ctx.createGain();
            f.type = 'lowpass';
            f.frequency.setValueAtTime(3200, at);
            f.frequency.exponentialRampToValueAtTime(1200, at + dur);
            env(g, at, gain, 0.008, dur);
            f.connect(g);
            out(g, bus, 0.25);
            const mix = ctx.createGain();
            mix.gain.value = 1 / (notes.length * 2);
            mix.connect(f);
            for (const n of notes) {
                for (const detune of [-8, 8]) {
                    const o = ctx.createOscillator();
                    o.type = 'sawtooth';
                    o.frequency.value = midi(n);
                    o.detune.value = detune;
                    o.connect(mix);
                    o.start(at);
                    o.stop(at + dur + 0.05);
                }
            }
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
        function trombone(freq, { at, bus, gain = 0.1, dur = 0.3, vibrato = 0, wet = 0.2 }) {
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
            hiss({ at, bus, gain: gain * 0.8, dur: 1.2, type: 'lowpass', freq: 400, to: 90, q: 0.5, wet: 0.3, buffer: brown });
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

        function sfx(name) {
            const t = ctx.currentTime + 0.01;
            const bus = effectsBus();
            switch (name) {
                case 'tick': // betting opens: a bright steel-drum "ding-ding-ding" and a sparkle
                    [84, 88, 91].forEach((n, i) => steel(midi(n), { at: t + i * 0.07, bus, gain: 0.08, dur: 0.6 }));
                    coin({ at: t + 0.24, bus, gain: 0.04 });
                    break;
                case 'bet': // coins in the slot and a marimba "plink-plink" up
                    coin({ at: t, bus, gain: 0.07 });
                    coin({ at: t + 0.05, bus, gain: 0.05 });
                    marimba(midi(84), { at: t + 0.02, bus, gain: 0.11, dur: 0.3 });
                    marimba(midi(91), { at: t + 0.09, bus, gain: 0.11, dur: 0.35 });
                    break;
                case 'start': { // the dive: a drum roll, then cymbal, kick and a brass hit, a whoosh and bubbles
                    const roll = [0, 0.08, 0.15, 0.21, 0.26, 0.3, 0.34, 0.37];
                    roll.forEach((d, i) => clap({ at: t + d, bus, gain: 0.05 + i * 0.012 }));
                    const hit = t + 0.42;
                    kick({ at: hit, bus, gain: 0.5 });
                    cymbal({ at: hit, bus, gain: 0.1 });
                    stab([60, 64, 67, 72], { at: hit, bus, gain: 0.08, dur: 0.35 });
                    hiss({ at: hit, bus, gain: 0.12, dur: 0.6, type: 'bandpass', freq: 2500, to: 300, q: 1.2 });
                    [84, 81, 77, 74, 72, 67].forEach((n, i) => marimba(midi(n), { at: hit + 0.05 + i * 0.05, bus, gain: 0.1 }));
                    for (let i = 0; i < 7; i++) bubble({ at: hit + 0.2 + i * 0.07 + Math.random() * 0.03, bus, gain: 0.05 });
                    break;
                }
                case 'win': // KA-CHING: register bell, a shower of coins, a fanfare up and a big brass finish
                    partial(midi(96), 'sine', t, 0.08, 0.002, 0.9, voice(bus, 0.3));
                    partial(midi(103), 'sine', t + 0.06, 0.07, 0.002, 1.1, voice(bus, 0.3));
                    hiss({ at: t, bus, gain: 0.08, dur: 0.12, freq: 4000 });
                    for (let i = 0; i < 16; i++) coin({ at: t + 0.1 + Math.random() * 0.8, bus, gain: 0.03 + Math.random() * 0.03 });
                    [72, 76, 79, 84, 88].forEach((n, i) => steel(midi(n), { at: t + 0.18 + i * 0.07, bus, gain: 0.09 }));
                    stab([64, 67, 72, 76], { at: t + 0.55, bus, gain: 0.09, dur: 0.5 });
                    kick({ at: t + 0.55, bus, gain: 0.4 });
                    cymbal({ at: t + 0.55, bus, gain: 0.09 });
                    break;
                case 'loss': // the sad trombone: wah, wah, wah, waaah
                    [58, 57, 56].forEach((n, i) => trombone(midi(n), { at: t + i * 0.3, bus, gain: 0.1, dur: 0.26 }));
                    trombone(midi(55), { at: t + 0.9, bus, gain: 0.1, dur: 0.95, vibrato: 0.02 });
                    break;
                case 'crash': { // the hull strains, a rivet pops, cracks, it crumples, then BOOM, cymbal, coins everywhere, a slide whistle down
                    const b = t + 0.45; // the implosion sprite's full flash (the page's --boom-at)
                    riser({ at: t, bus, gain: 0.05, dur: 0.45 });
                    tom(95, { at: t, bus, gain: 0.14 });
                    coin({ at: t + 0.09, bus, gain: 0.03 }); // a rivet pops
                    hiss({ at: t + 0.09, bus, gain: 0.07, dur: 0.04, freq: 5000 });
                    hiss({ at: t + 0.16, bus, gain: 0.09, dur: 0.05, freq: 4200 }); // cracks
                    hiss({ at: t + 0.23, bus, gain: 0.09, dur: 0.05, freq: 3600 }); // a porthole shatters
                    tom(70, { at: t + 0.3, bus, gain: 0.14 }); // the hull crumples
                    for (let i = 0; i < 3; i++) coin({ at: t + 0.32 + i * 0.03, bus, gain: 0.03 });
                    boom({ at: b, bus, gain: 0.32 });
                    kick({ at: b, bus, gain: 0.45 });
                    cymbal({ at: b, bus, gain: 0.1 });
                    hiss({ at: b, bus, gain: 0.18, dur: 0.15, freq: 3000 });
                    for (let i = 0; i < 22; i++) coin({ at: b + 0.05 + Math.random() * 0.9, bus, gain: 0.025 + Math.random() * 0.035 });
                    slide(1400, 180, { at: b + 0.15, bus, gain: 0.06, dur: 1.1 });
                    for (let i = 0; i < 6; i++) bubble({ at: b + 0.4 + i * 0.12, bus, gain: 0.04 });
                    break;
                }
            }
        }

        // ---- Continuous background: the band, following the dive ----

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

            // Under the band: the sea, a soft low wash rising and falling like swell.
            const sea = keep(ctx.createBufferSource());
            const seaFilter = keep(ctx.createBiquadFilter());
            const seaGain = keep(ctx.createGain());
            sea.buffer = white;
            sea.loop = true;
            seaFilter.type = 'lowpass';
            seaFilter.frequency.value = 520;
            seaGain.gain.value = 0.02;
            sea.connect(seaFilter).connect(seaGain);
            out(seaGain, bus, 0.2);
            const swell = keep(ctx.createOscillator());
            const swellDepth = keep(ctx.createGain());
            swell.frequency.value = 0.12;
            swellDepth.gain.value = 0.012;
            swell.connect(swellDepth).connect(seaGain.gain);
            sea.start(t0);
            swell.start(t0);

            let level = 0;
            let cymbalNext = false;
            let step = 0;
            let next = t0 + 0.5;
            let nextBubbles = t0 + 4 + Math.random() * 4;
            let stopped = false;

            function playStep(at) {
                const s = step % 16;
                const barIndex = Math.floor(step / 16) % BARS.length;
                const bar = BARS[barIndex];

                // A cymbal on the downbeat: each time round the loop, and after stepping up.
                if (s === 0 && (cymbalNext || (barIndex === 0 && level >= 1))) {
                    cymbal({ at, bus, gain: 0.07 });
                    cymbalNext = false;
                }
                // Drums.
                if (KICK[level].includes(s)) kick({ at, bus, gain: 0.22 });
                if (s === 4 || s === 12) clap({ at, bus, gain: level ? 0.12 : 0.05 });
                if (level >= 2 || s % 2 === 0) {
                    hat({ at, bus, gain: (s % 2 ? 0.014 : 0.026) * (level ? 1 : 0.6), open: level >= 1 && (s === 14 || (level >= 3 && s === 6)) });
                }
                if (level < 2) hiss({ at, bus, gain: s % 4 === 2 ? 0.02 : 0.009, dur: 0.04, freq: 7000, wet: 0.05 }); // shaker
                // Drum fill into the top of the loop.
                if (level >= 1 && barIndex === BARS.length - 1 && s >= 12) {
                    tom([196, 165, 131, 110][s - 12], { at, bus, gain: 0.2 });
                }
                // Bass: half the line while betting, all of it once diving.
                if (s in BASS && (level >= 1 || s === 0 || s === 8)) {
                    bass(midi(bar.root + BASS[s]), { at, bus, gain: 0.08, dur: level ? 0.2 : 0.3 });
                }
                // Chords: soft marimba while betting, brass stabs on the offbeats once diving.
                if (level === 0 && (s === 4 || s === 12)) {
                    bar.chord.forEach((n) => marimba(midi(n), { at, bus, gain: 0.03, dur: 0.3, wet: 0.25 }));
                } else if (level >= 1 && (s === 2 || s === 6 || s === 10 || s === 14)) {
                    stab(bar.chord, { at, bus, gain: 0.035, dur: 0.13 });
                }
                // Deep and below: a marimba arpeggio running in sixteenths.
                if (level >= 2) {
                    marimba(midi(bar.chord[[0, 1, 2, 1][s % 4]] + 12), { at, bus, gain: 0.03, dur: 0.18, wet: 0.15 });
                }
                // The hook (doubled an octave up in the abyss), a few notes left out each time.
                const note = HOOK[step % HOOK.length];
                if (note && Math.random() > 0.08) {
                    steel(midi(note), { at, bus, gain: level ? 0.065 : 0.05, dur: 0.6 });
                    if (level >= 3) steel(midi(note + 12), { at, bus, gain: 0.025, dur: 0.4 });
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
                if (level === 0 && nextBubbles < horizon) {
                    const n = 2 + Math.floor(Math.random() * 3);
                    for (let i = 0; i < n; i++) bubble({ at: nextBubbles + i * 0.09, bus, gain: 0.03 });
                    nextBubbles += 5 + Math.random() * 6;
                }
            }

            /** 0 betting · 1 diving · 2 deep · 3 abyss (and critical). */
            function intensity(n) {
                const lv = Math.max(0, Math.min(3, Math.floor(Number(n) || 0)));
                if (lv > level && !stopped) {
                    riser({ at: ctx.currentTime + 0.02, bus, dur: 0.8 });
                    cymbalNext = true;
                }
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

    window.OrangeSound = Object.assign(window.OrangeSound || {}, { jackpot });
})();
