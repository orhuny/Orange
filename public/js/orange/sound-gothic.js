/**
 * "Gothic Nightmare" sound pack for the crash game (the "burton" theme): a storybook
 * palette of music box, harpsichord, church bell, pipe organ and theremin, all sent
 * through a generated cathedral reverb. Everything is synthesised; no audio files.
 *
 *   const pack = window.OrangeSound.gothic(ctx, master);
 *   pack.sfx('crash');                 // tick | bet | start | win | loss | crash
 *   const bg = pack.ambience();        // continuous background, until bg.stop()
 *
 * The ambience schedules ahead on the audio clock (bg.schedule() runs every 250 ms),
 * so it keeps time even while timers are throttled in a background tab.
 */
(function () {
    'use strict';

    const midi = (n) => 440 * Math.pow(2, (n - 69) / 12);

    // A slow minor-key waltz: four bars per chord, Dm → B♭ → Gm → A, one beat per step.
    const CHORDS = [[50, 57, 62, 65], [46, 53, 58, 62], [43, 50, 55, 58], [45, 52, 57, 61]];
    const MELODY = [
        81, 0, 77, 76, 77, 74, 81, 0, 0, 0, 0, 0,
        82, 0, 77, 74, 77, 82, 81, 0, 0, 0, 0, 0,
        79, 0, 82, 81, 79, 77, 76, 0, 77, 79, 0, 0,
        76, 0, 73, 76, 79, 76, 73, 0, 0, 0, 0, 0,
    ];
    const BEAT = 0.68; // seconds (~88 bpm)
    // Bus levels, set by ear and by measurement: effects peak around -20 (tick) to -7 dBFS
    // (crash); the background sits near -31 dBFS RMS, under the effects but clearly there.
    const LEVEL = { sfx: 2.2, ambience: 3 };

    function gothic(ctx, master) {
        let reverbIn = null;
        let effects = null;

        /** Cathedral reverb: a convolver over three and a half seconds of decaying noise. */
        function reverb() {
            if (reverbIn) return reverbIn;
            const len = Math.floor(ctx.sampleRate * 3.5);
            const ir = ctx.createBuffer(2, len, ctx.sampleRate);
            for (let ch = 0; ch < 2; ch++) {
                const d = ir.getChannelData(ch);
                for (let i = 0; i < len; i++) d[i] = (Math.random() * 2 - 1) * Math.pow(1 - i / len, 3);
            }
            reverbIn = ctx.createConvolver();
            reverbIn.buffer = ir;
            const ret = ctx.createGain();
            ret.gain.value = 0.6;
            reverbIn.connect(ret).connect(master);
            return reverbIn;
        }

        /** A voice goes to the bus dry, plus a `wet` share into its reverb send. */
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

        // ---- Instruments (at = audio-clock time) ----

        /** Music box / celesta: a sine with a faint octave and a short metallic tine. */
        function celesta(freq, { at, bus, gain = 0.08, dur = 1.6, wet = 0.45 }) {
            const v = voice(bus, wet);
            partial(freq, 'sine', at, gain, 0.004, dur, v);
            partial(freq * 2, 'sine', at, gain * 0.18, 0.004, dur * 0.45, v);
            if (freq * 5.4 < ctx.sampleRate / 2.2) partial(freq * 5.4, 'sine', at, gain * 0.1, 0.002, 0.12, v);
        }

        /** Harpsichord / pizzicato: a sawtooth whose filter snaps shut. */
        function pluck(freq, { at, bus, gain = 0.1, dur = 0.5, wet = 0.25, bright = 3200 }) {
            const o = ctx.createOscillator();
            const f = ctx.createBiquadFilter();
            const g = ctx.createGain();
            o.type = 'sawtooth';
            o.frequency.value = freq;
            f.type = 'lowpass';
            f.Q.value = 2;
            f.frequency.setValueAtTime(bright, at);
            f.frequency.exponentialRampToValueAtTime(Math.max(freq * 1.5, 200), at + dur * 0.6);
            env(g, at, gain, 0.003, dur);
            o.connect(f).connect(g);
            out(g, bus, wet);
            o.start(at);
            o.stop(at + dur + 0.05);
        }

        /** Church bell: inharmonic partials (hum, prime, tierce, quint, nominal…), high ones dying first. */
        function bell(freq, { at, bus, gain = 0.1, dur = 3.5, wet = 0.6 }) {
            const v = voice(bus, wet);
            [[0.5, 0.55], [1, 1], [1.19, 0.45], [1.5, 0.35], [2, 0.5], [2.52, 0.22], [2.66, 0.18], [3.01, 0.14], [4.1, 0.08]]
                .forEach(([ratio, amp], i) => partial(freq * ratio, 'sine', at, gain * amp, 0.003, dur / (1 + i * 0.35), v));
        }

        /** Pipe organ: fundamental, octave and twelfth per note, through a soft lowpass. */
        function organ(notes, { at, bus, gain = 0.05, attack = 0.15, dur = 1.5, wet = 0.5, cutoff = 1600 }) {
            const f = ctx.createBiquadFilter();
            const g = ctx.createGain();
            f.type = 'lowpass';
            f.frequency.value = cutoff;
            g.gain.setValueAtTime(0.0001, at);
            g.gain.exponentialRampToValueAtTime(gain, at + attack);
            g.gain.setValueAtTime(gain, at + dur * 0.6);
            g.gain.exponentialRampToValueAtTime(0.0001, at + dur);
            f.connect(g);
            out(g, bus, wet);
            for (const freq of notes) {
                for (const [ratio, type, amp] of [[1, 'triangle', 1], [2, 'sine', 0.5], [3, 'sine', 0.25]]) {
                    const o = ctx.createOscillator();
                    const og = ctx.createGain();
                    o.type = type;
                    o.frequency.value = freq * ratio;
                    og.gain.value = amp / notes.length;
                    o.connect(og).connect(f);
                    o.start(at);
                    o.stop(at + dur + 0.05);
                }
            }
        }

        /** Theremin: a vibrato sine gliding between two pitches. */
        function theremin(from, to, { at, bus, gain = 0.06, dur = 1.4, wet = 0.5 }) {
            const o = ctx.createOscillator();
            const vib = ctx.createOscillator();
            const depth = ctx.createGain();
            const g = ctx.createGain();
            o.type = 'sine';
            o.frequency.setValueAtTime(from, at);
            o.frequency.exponentialRampToValueAtTime(to, at + dur);
            vib.frequency.value = 5.5;
            depth.gain.value = from * 0.012;
            vib.connect(depth).connect(o.frequency);
            g.gain.setValueAtTime(0.0001, at);
            g.gain.exponentialRampToValueAtTime(gain, at + 0.25);
            g.gain.exponentialRampToValueAtTime(0.0001, at + dur);
            o.connect(g);
            out(g, bus, wet);
            o.start(at);
            vib.start(at);
            o.stop(at + dur + 0.05);
            vib.stop(at + dur + 0.05);
        }

        /** Brown noise (deep, rumbly) of the given length. */
        function brown(seconds) {
            const len = Math.floor(ctx.sampleRate * seconds);
            const buf = ctx.createBuffer(1, len, ctx.sampleRate);
            const d = buf.getChannelData(0);
            let last = 0;
            for (let i = 0; i < len; i++) {
                last = (last + 0.02 * (Math.random() * 2 - 1)) / 1.02;
                d[i] = last * 3.5;
            }
            return buf;
        }

        /** Thunder / collapse: low-passed brown noise with a quick swell and a long tail. */
        function rumble({ at, bus, gain = 0.2, dur = 2.5, cutoff = 160, wet = 0.3 }) {
            const src = ctx.createBufferSource();
            const f = ctx.createBiquadFilter();
            const g = ctx.createGain();
            src.buffer = brown(dur);
            f.type = 'lowpass';
            f.frequency.value = cutoff;
            g.gain.setValueAtTime(0.0001, at);
            g.gain.exponentialRampToValueAtTime(gain, at + 0.08);
            g.gain.exponentialRampToValueAtTime(0.0001, at + dur);
            src.connect(f).connect(g);
            out(g, bus, wet);
            src.start(at);
            src.stop(at + dur + 0.05);
        }

        /** Creaking timber: a stick-slip sawtooth through a narrow band. */
        function creak({ at, bus, gain = 0.06, dur = 1.8, wet = 0.35 }) {
            const o = ctx.createOscillator();
            const bp = ctx.createBiquadFilter();
            const g = ctx.createGain();
            const stick = ctx.createOscillator();
            const stickDepth = ctx.createGain();
            const f0 = 55 + Math.random() * 40;
            o.type = 'sawtooth';
            o.frequency.setValueAtTime(f0, at);
            o.frequency.linearRampToValueAtTime(f0 * 1.4, at + dur * 0.55);
            o.frequency.linearRampToValueAtTime(f0 * 0.85, at + dur);
            bp.type = 'bandpass';
            bp.frequency.value = 260 + Math.random() * 200;
            bp.Q.value = 7;
            stick.type = 'square';
            stick.frequency.value = 14 + Math.random() * 10;
            stickDepth.gain.value = gain * 0.6;
            stick.connect(stickDepth).connect(g.gain);
            g.gain.setValueAtTime(0.0001, at);
            g.gain.linearRampToValueAtTime(gain, at + 0.2);
            g.gain.linearRampToValueAtTime(0.0001, at + dur);
            o.connect(bp).connect(g);
            out(g, bus, wet);
            o.start(at);
            stick.start(at);
            o.stop(at + dur + 0.05);
            stick.stop(at + dur + 0.05);
        }

        /** A short, bright crack (noise through a highpass). */
        function crack({ at, bus, gain = 0.2 }) {
            const len = Math.floor(ctx.sampleRate * 0.15);
            const buf = ctx.createBuffer(1, len, ctx.sampleRate);
            const d = buf.getChannelData(0);
            for (let i = 0; i < len; i++) d[i] = Math.random() * 2 - 1;
            const src = ctx.createBufferSource();
            const f = ctx.createBiquadFilter();
            const g = ctx.createGain();
            src.buffer = buf;
            f.type = 'highpass';
            f.frequency.value = 1800;
            env(g, at, gain, 0.002, 0.15);
            src.connect(f).connect(g);
            out(g, bus, 0.4);
            src.start(at);
        }

        // ---- Effects ----

        /** The effects' own dry/wet bus (created once). */
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
                case 'tick': // betting opens: a little music-box chime
                    celesta(midi(81), { at: t, bus, gain: 0.08 });
                    celesta(midi(86), { at: t + 0.14, bus, gain: 0.07 });
                    break;
                case 'bet': // harpsichord plink-plonk
                    pluck(midi(62), { at: t, bus, gain: 0.12 });
                    pluck(midi(69), { at: t + 0.07, bus, gain: 0.1 });
                    celesta(midi(86), { at: t + 0.07, bus, gain: 0.05, dur: 0.8 });
                    break;
                case 'start': // the dive: a tolling bell, an organ swell and a theremin wail
                    bell(midi(50), { at: t, bus, gain: 0.12, dur: 4 });
                    organ([midi(38), midi(45), midi(50), midi(53)], { at: t, bus, gain: 0.16, attack: 0.35, dur: 2.2 });
                    theremin(midi(81), midi(62), { at: t + 0.2, bus, gain: 0.06, dur: 1.6 });
                    rumble({ at: t, bus, gain: 0.12, dur: 1.2, cutoff: 300 });
                    break;
                case 'win': // a music-box flourish resolving to D major, with sparkle
                    [74, 77, 81, 86].forEach((n, i) => celesta(midi(n), { at: t + i * 0.08, bus, gain: 0.1 }));
                    [86, 90, 93].forEach((n) => celesta(midi(n), { at: t + 0.36, bus, gain: 0.07, dur: 2.2 }));
                    [98, 102, 105].forEach((n, i) => celesta(midi(n), { at: t + 0.5 + i * 0.06, bus, gain: 0.03, dur: 0.6 }));
                    break;
                case 'loss': // a music box winding down
                    [81, 80, 79, 78, 77].forEach((n, i) => celesta(midi(n) * (1 - i * 0.006), { at: t + [0, 0.13, 0.29, 0.5, 0.78][i], bus, gain: 0.08 }));
                    organ([midi(50), midi(51)], { at: t + 0.1, bus, gain: 0.06, attack: 0.3, dur: 1.5, cutoff: 700 });
                    break;
                case 'crash': // the nightmare: a cracked bell, thunder, a dissonant organ stab, groaning timber
                    crack({ at: t, bus, gain: 0.25 });
                    bell(midi(38), { at: t, bus, gain: 0.2, dur: 5 });
                    bell(midi(44), { at: t + 0.02, bus, gain: 0.07, dur: 3 });
                    organ([midi(38), midi(39), midi(44), midi(50)], { at: t, bus, gain: 0.16, attack: 0.02, dur: 1.4, cutoff: 1200 });
                    rumble({ at: t, bus, gain: 0.4, dur: 3.2 });
                    creak({ at: t + 0.3, bus, gain: 0.1 });
                    theremin(midi(74), midi(50), { at: t + 0.1, bus, gain: 0.05, dur: 1.8 });
                    break;
            }
        }

        // ---- Continuous background ----

        function ambience() {
            const nodes = [];
            const keep = (n) => (nodes.push(n), n);
            const t0 = ctx.currentTime;

            // Own dry/wet buses, so stopping fades out everything the ambience started.
            const dry = keep(ctx.createGain());
            const wet = keep(ctx.createGain());
            for (const g of [dry, wet]) {
                g.gain.setValueAtTime(0.0001, t0);
                g.gain.exponentialRampToValueAtTime(LEVEL.ambience, t0 + 3);
            }
            dry.connect(master);
            wet.connect(reverb());
            const bus = { dry, wet };

            // Layer 1: an organ/strings pad that follows the chords, its filter slowly breathing.
            const padFilter = keep(ctx.createBiquadFilter());
            const padGain = keep(ctx.createGain());
            padFilter.type = 'lowpass';
            padFilter.frequency.value = 900;
            padFilter.Q.value = 0.7;
            padGain.gain.value = 0.07;
            padFilter.connect(padGain);
            out(padGain, bus, 0.6);
            const pad = CHORDS[0].map((n) => ['triangle', 'sawtooth'].map((type, j) => {
                const o = keep(ctx.createOscillator());
                const g = ctx.createGain();
                o.type = type;
                o.frequency.value = midi(n);
                o.detune.value = j ? 7 : -4;
                g.gain.value = j ? 0.1 : 0.22;
                o.connect(g).connect(padFilter);
                o.start(t0);
                return o;
            }));
            const breath = keep(ctx.createOscillator());
            const breathDepth = keep(ctx.createGain());
            breath.frequency.value = 0.07;
            breathDepth.gain.value = 350;
            breath.connect(breathDepth).connect(padFilter.frequency);
            breath.start(t0);

            // Layer 2: wind through the rafters (band-passed noise, drifting pitch, gusts).
            const windLen = ctx.sampleRate * 4;
            const windBuf = ctx.createBuffer(1, windLen, ctx.sampleRate);
            const wd = windBuf.getChannelData(0);
            for (let i = 0; i < windLen; i++) wd[i] = Math.random() * 2 - 1;
            const wind = keep(ctx.createBufferSource());
            const windFilter = keep(ctx.createBiquadFilter());
            const windGain = keep(ctx.createGain());
            wind.buffer = windBuf;
            wind.loop = true;
            windFilter.type = 'bandpass';
            windFilter.frequency.value = 420;
            windFilter.Q.value = 0.9;
            windGain.gain.value = 0.035;
            wind.connect(windFilter).connect(windGain);
            out(windGain, bus, 0.3);
            const drift = keep(ctx.createOscillator());
            const driftDepth = keep(ctx.createGain());
            drift.frequency.value = 0.045;
            driftDepth.gain.value = 250;
            drift.connect(driftDepth).connect(windFilter.frequency);
            const gust = keep(ctx.createOscillator());
            const gustDepth = keep(ctx.createGain());
            gust.frequency.value = 0.11;
            gustDepth.gain.value = 0.02;
            gust.connect(gustDepth).connect(windGain.gain);
            wind.start(t0);
            drift.start(t0);
            gust.start(t0);

            // Layer 3: the music-box waltz over a pizzicato bass; now and then a distant bell
            // or a creak. Scheduled ahead on the audio clock.
            let step = 0;
            let next = t0 + 1.5;
            let nextBell = t0 + 18 + Math.random() * 12;
            let nextCreak = t0 + 9 + Math.random() * 10;
            let stopped = false;

            function schedule() {
                if (stopped) return;
                const horizon = ctx.currentTime + 1.5;
                while (next < horizon) {
                    const bar = Math.floor(step / 3);
                    const chord = CHORDS[Math.floor(bar / 4) % CHORDS.length];
                    if (step % 12 === 0) {
                        pad.forEach((pair, i) => pair.forEach((o) => o.frequency.setTargetAtTime(midi(chord[i]), next, 0.25)));
                    }
                    if (step % 3 === 0) {
                        pluck(midi(chord[0] - 12), { at: next, bus, gain: 0.07, dur: 0.6, wet: 0.3, bright: 900 });
                    }
                    const note = MELODY[step % MELODY.length];
                    // A few notes go missing each time round, so the loop never sounds mechanical.
                    if (note && Math.random() > 0.12) {
                        celesta(midi(note), { at: next, bus, gain: 0.07, dur: 1.8 });
                    }
                    step += 1;
                    next += BEAT;
                }
                if (nextBell < horizon) {
                    bell(midi(50), { at: nextBell, bus, gain: 0.05, dur: 4, wet: 0.8 });
                    nextBell += 25 + Math.random() * 20;
                }
                if (nextCreak < horizon) {
                    creak({ at: nextCreak, bus, gain: 0.045 });
                    nextCreak += 15 + Math.random() * 15;
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

    window.OrangeSound = Object.assign(window.OrangeSound || {}, { gothic });
})();
