/**
 * "Tutti Frutti" sound pack for the slot: a Riviera beach club. Marimba and steel-drum plucks,
 * juicy splashes, a kiss ("mwah") for the kisses, glass clinks for the cocktails, and a soft
 * summer lounge groove (bossa rhythm, warm bass, a marimba line in the Neon Night). All
 * synthesised, same shape as the other packs:
 *
 *   const pack = window.OrangeSound.riviera(ctx, master);
 *   pack.sfx('scatter', 2);             // spin | land | win | buzz | flame | pop | scatter | star | stars | multiply |
 *                                       // retrigger | fsStart | fsEnd | big | tick | bigEnd | click | intro, and this
 *                                       // pack's own: tease | line | splash | kiss | amore | firework
 *   pack.sfx('win', { chain: 0, x: 6 }); // a line win paying 6× the bet: the bigger, the more it celebrates
 *   pack.sfx('big', 'mega');            // big | mega | epic | max, each bigger
 *   const bg = pack.ambience();         // the lounge groove, continuous until bg.stop()
 *   bg.intensity(1);                    // 0 noon · 1 the Neon Night
 */
(function () {
    'use strict';

    const midi = (n) => 440 * Math.pow(2, (n - 69) / 12);
    const num = (v) => (Number.isFinite(Number(v)) ? Number(v) : 0);
    // A bright major-pentatonic scale for the plucks (C D E G A over two octaves).
    const SCALE = [72, 74, 76, 79, 81, 84, 86, 88, 91, 93, 96];
    // Four bars of a bossa progression: Cmaj7 | Am7 | Dm7 | G7, each { chord, root }.
    const BARS = [
        { chord: [64, 67, 71, 74], root: 36 },
        { chord: [64, 67, 69, 72], root: 33 },
        { chord: [65, 69, 72, 74], root: 38 },
        { chord: [65, 67, 71, 74], root: 31 },
    ];
    // The marimba line in the Neon Night, one entry per eighth (0 = rest), 4 bars.
    const TUNE = [
        79, 0, 76, 79, 0, 81, 79, 0, 76, 0, 72, 74, 0, 76, 0, 0,
        77, 0, 74, 77, 0, 79, 77, 0, 74, 0, 71, 72, 0, 74, 0, 0,
    ];
    const LEVEL = { sfx: 0.9, music: 0.32 };

    function riviera(ctx, master) {
        let noiseBuffer = null;
        let effects = null;

        function noise() {
            if (noiseBuffer) return noiseBuffer;
            const length = Math.floor(ctx.sampleRate * 1);
            noiseBuffer = ctx.createBuffer(1, length, ctx.sampleRate);
            const data = noiseBuffer.getChannelData(0);
            for (let i = 0; i < length; i++) data[i] = Math.random() * 2 - 1;
            return noiseBuffer;
        }

        function bus() {
            if (effects) return effects;
            effects = ctx.createGain();
            effects.gain.value = LEVEL.sfx;
            effects.connect(master);
            return effects;
        }

        /** One oscillator with a quick attack and an exponential fall. */
        function tone(freq, { at, dur = 0.3, gain = 0.1, type = 'sine', to = null, attack = 0.005, out = bus() }) {
            const osc = ctx.createOscillator();
            const env = ctx.createGain();
            osc.type = type;
            osc.frequency.setValueAtTime(freq, at);
            if (to) osc.frequency.exponentialRampToValueAtTime(to, at + dur);
            env.gain.setValueAtTime(0.0001, at);
            env.gain.exponentialRampToValueAtTime(gain, at + attack);
            env.gain.exponentialRampToValueAtTime(0.0001, at + dur);
            osc.connect(env).connect(out);
            osc.start(at);
            osc.stop(at + dur + 0.02);
        }

        /** A burst of filtered noise. */
        function hiss({ at, dur = 0.1, gain = 0.05, type = 'bandpass', freq = 2000, to = null, q = 1, out = bus() }) {
            const src = ctx.createBufferSource();
            src.buffer = noise();
            const filter = ctx.createBiquadFilter();
            filter.type = type;
            filter.frequency.setValueAtTime(freq, at);
            if (to) filter.frequency.exponentialRampToValueAtTime(to, at + dur);
            filter.Q.value = q;
            const env = ctx.createGain();
            env.gain.setValueAtTime(0.0001, at);
            env.gain.exponentialRampToValueAtTime(gain, at + 0.006);
            env.gain.exponentialRampToValueAtTime(0.0001, at + dur);
            src.connect(filter).connect(env).connect(out);
            src.start(at);
            src.stop(at + dur + 0.02);
        }

        /** A marimba note: a round sine and its bright fourth partial, dying fast. */
        function marimba(n, { at, gain = 0.09, dur = 0.45, out = bus() }) {
            tone(midi(n), { at, dur, gain, out });
            tone(midi(n) * 3.98, { at, dur: dur * 0.25, gain: gain * 0.22, out });
        }

        /** A steel-drum note: two slightly detuned partials with a soft bloom. */
        function steel(n, { at, gain = 0.07, dur = 0.7, out = bus() }) {
            tone(midi(n), { at, dur, gain, type: 'triangle', attack: 0.012, out });
            tone(midi(n) * 2.01, { at, dur: dur * 0.6, gain: gain * 0.4, attack: 0.02, out });
        }

        /** A glass clink: two high, inharmonic sines. */
        function clink(at, k = 1, gain = 0.06) {
            tone(2630 * k, { at, dur: 0.5, gain });
            tone(3920 * k, { at: at + 0.004, dur: 0.35, gain: gain * 0.6 });
        }

        /** A juicy splash: a wet whoosh falling in pitch, and a few bubbles. */
        function splash(at, size = 1) {
            hiss({ at, dur: 0.22 + 0.06 * size, gain: 0.05 + 0.015 * size, freq: 3200, to: 700, q: 0.9 });
            for (let i = 0; i < 2 + Math.min(4, size); i++) tone(600 + Math.random() * 700, { at: at + 0.03 + i * 0.03, dur: 0.07, gain: 0.025, to: 1400 + Math.random() * 600 });
        }

        /** A kiss: a short smack and a rising blip ("mwah"). */
        function kiss(at, gain = 0.07) {
            hiss({ at, dur: 0.05, gain: gain * 0.8, freq: 1700, q: 2.5 });
            tone(520, { at: at + 0.03, dur: 0.16, gain, to: 1050, type: 'sine' });
        }

        /** An arpeggio of `count` plucks up the scale from `from`. */
        function arpeggio(count, { at, gap = 0.07, from = 0, gain = 0.08, kind = marimba }) {
            for (let i = 0; i < count; i++) kind(SCALE[(from + i) % SCALE.length] + 12 * Math.floor((from + i) / SCALE.length), { at: at + i * gap, gain });
        }

        function sfx(name, arg = 0) {
            const t = ctx.currentTime + 0.01;
            const opts = arg && typeof arg === 'object' ? arg : {};
            switch (name) {
                case 'spin': { // soft, short and round: an airy swish and one muted marimba bloop
                    const k = Math.pow(2, (Math.random() - 0.5) / 12);
                    hiss({ at: t, dur: 0.22, gain: 0.018, freq: 900 * k, to: 2400 * k, q: 0.7 });
                    tone(midi(67) * k, { at: t + 0.05, dur: 0.3, gain: 0.045, to: midi(74) * k });
                    break;
                }
                case 'land': // a reel stops: a soft wooden tock, a touch higher each reel
                    tone(midi(55 + 2 * Math.min(6, num(arg))), { at: t, dur: 0.12, gain: 0.07, type: 'triangle' });
                    hiss({ at: t, dur: 0.03, gain: 0.02, freq: 2400, q: 3 });
                    break;
                case 'win': { // a line win: { x } the win in bets; more plucks, then steel and claps, the bigger it is
                    const x = num(opts.x ?? arg);
                    const count = 2 + (x >= 1 ? 1 : 0) + (x >= 2 ? 2 : 0) + (x >= 5 ? 2 : 0) + (x >= 10 ? 3 : 0);
                    arpeggio(count, { at: t, from: Math.min(4, Math.floor(x)) });
                    if (x >= 2) steel(84, { at: t + count * 0.07, gain: 0.06 });
                    if (x >= 5) for (let i = 0; i < 3; i++) hiss({ at: t + 0.1 + i * 0.12, dur: 0.06, gain: 0.04, freq: 1500, q: 1.2 });
                    break;
                }
                case 'pop': { // juice bursting: { x } as for 'win'
                    const x = num(opts.x ?? arg);
                    const bursts = 1 + (x >= 1 ? 1 : 0) + (x >= 2 ? 1 : 0) + (x >= 5 ? 1 : 0) + (x >= 10 ? 2 : 0);
                    for (let i = 0; i < bursts; i++) splash(t + i * 0.06, 1 + i);
                    break;
                }
                case 'splash': // the juice of a line's fruit (arg: how many)
                    splash(t, Math.min(4, Math.max(1, num(arg) / 2)));
                    break;
                case 'line': // one line shown on its own (arg: its number)
                    marimba(SCALE[(num(arg) - 1 + SCALE.length) % SCALE.length], { at: t, gain: 0.06, dur: 0.3 });
                    break;
                case 'buzz':
                case 'flame':
                    hiss({ at: t, dur: 0.18, gain: 0.03, freq: 1200, to: 3000 });
                    break;
                case 'scatter': // a cocktail lands: a glass clink, higher for each one
                    clink(t, 1 + 0.12 * Math.max(0, num(arg) - 1));
                    marimba(SCALE[Math.min(SCALE.length - 1, 2 + num(arg))], { at: t + 0.02, gain: 0.05 });
                    break;
                case 'tease': // two cocktails down: a shaker rattling, faster as the reel spins on
                    for (let i = 0; i < 10; i++) hiss({ at: t + i * 0.09, dur: 0.05, gain: 0.03 + i * 0.002, freq: 5200, q: 1.5 });
                    break;
                case 'star': // a kiss lands on the reels
                case 'kiss':
                    kiss(t);
                    if (num(arg) > 1) kiss(t + 0.18, 0.05);
                    break;
                case 'stars':
                case 'amore': { // a kiss joins Amore (arg: its value): a kiss and a bright note, higher as it climbs
                    kiss(t, 0.05);
                    marimba(SCALE[Math.min(SCALE.length - 1, Math.max(0, num(arg) - 1))], { at: t + 0.05, gain: 0.07 });
                    break;
                }
                case 'multiply': { // Amore or a kiss multiplies the win (arg: by how much): bigger, more
                    const m = Math.max(2, num(arg));
                    const count = 3 + Math.floor(Math.log2(m)) * 2 + (m >= 25 ? 3 : 0);
                    arpeggio(count, { at: t, gap: 0.05, kind: steel, gain: 0.06 });
                    kiss(t + count * 0.05);
                    break;
                }
                case 'retrigger': // more free spins: clinks and a steel call
                    clink(t);
                    clink(t + 0.12, 1.2);
                    arpeggio(4, { at: t + 0.2, kind: steel });
                    break;
                case 'firework': // a heart firework over the bay: a soft thump and crackle
                    tone(140, { at: t, dur: 0.3, gain: 0.05, to: 60 });
                    for (let i = 0; i < 6; i++) hiss({ at: t + 0.1 + Math.random() * 0.4, dur: 0.03, gain: 0.015, freq: 4000 + Math.random() * 3000, q: 2 });
                    break;
                case 'fsStart': // the party starts: a rising steel run into a chord and clinks
                    arpeggio(8, { at: t, gap: 0.06, kind: steel });
                    for (const n of [72, 76, 79, 84]) steel(n, { at: t + 0.55, gain: 0.05, dur: 1.2 });
                    clink(t + 0.6);
                    break;
                case 'fsEnd': // the night settles: a gentle falling marimba cadence
                    for (const [i, n] of [84, 79, 76, 72, 67].entries()) marimba(n, { at: t + i * 0.12, gain: 0.07 });
                    break;
                case 'big': { // big | mega | epic | max: more steel, claps and clinks each tier
                    const tier = { big: 1, mega: 2, epic: 3, max: 4 }[arg] ?? 1;
                    arpeggio(4 + tier * 3, { at: t, gap: 0.06, kind: steel, gain: 0.07 });
                    for (let i = 0; i < tier * 2; i++) hiss({ at: t + 0.15 + i * 0.11, dur: 0.06, gain: 0.045, freq: 1500, q: 1.2 });
                    for (let i = 0; i < tier; i++) clink(t + 0.4 + i * 0.15, 1 + i * 0.1);
                    break;
                }
                case 'tick': // the win counting up (arg: how far, 0..1)
                    marimba(SCALE[Math.min(SCALE.length - 1, Math.floor(num(arg) * SCALE.length))], { at: t, gain: 0.035, dur: 0.18 });
                    break;
                case 'bigEnd': // the count lands: a chord, clinks and a kiss
                    for (const n of [72, 76, 79, 84, 88]) steel(n, { at: t, gain: 0.05, dur: 1.4 });
                    clink(t + 0.1);
                    kiss(t + 0.3);
                    break;
                case 'click': // a button: a tiny wooden tap
                    tone(midi(79), { at: t, dur: 0.06, gain: 0.04, type: 'triangle' });
                    break;
                case 'intro': // the logo lands: a juicy splash, a marimba run and a kiss
                    splash(t, 2);
                    arpeggio(7, { at: t + 0.1, gap: 0.07 });
                    kiss(t + 0.7);
                    break;
                default:
                    break;
            }
        }

        // ---- The lounge groove ------------------------------------------------------------------
        // 112 bpm in eighths. The base: a bossa bass (root and fifth), a rim-click pattern and
        // soft chord stabs. The Neon Night (intensity 1) adds a shaker, the marimba line and a
        // brighter stab on every off-beat.

        function ambience() {
            const out = ctx.createGain();
            out.gain.value = LEVEL.music;
            out.connect(master);
            const eighth = 60 / 112 / 2;
            const RIM = [1, 0, 0, 1, 0, 0, 1, 0, 0, 0, 1, 0, 0, 1, 0, 0];
            const STAB = [0, 0, 1, 0, 0, 1, 0, 0, 0, 0, 0, 1, 0, 0, 1, 0];
            let step = 0;
            let next = ctx.currentTime + 0.1;
            let level = 0;
            let running = true;

            function play(i, at) {
                const bar = BARS[Math.floor(i / 16) % BARS.length];
                const pos = i % 16;
                if (pos === 0 || pos === 6 || pos === 8 || pos === 14) {
                    const n = pos === 6 || pos === 14 ? bar.root + 7 : bar.root;
                    tone(midi(n), { at, dur: eighth * 2.2, gain: 0.16, type: 'triangle', attack: 0.01, out });
                }
                if (RIM[pos]) hiss({ at, dur: 0.035, gain: 0.05, freq: 2600, q: 4, out });
                if (STAB[pos]) for (const n of bar.chord) tone(midi(n), { at, dur: eighth * 1.6, gain: 0.018, type: 'triangle', attack: 0.02, out });
                if (level > 0) {
                    hiss({ at, dur: 0.04, gain: pos % 2 ? 0.02 : 0.03, freq: 6500, q: 1, out });
                    hiss({ at: at + eighth / 2, dur: 0.03, gain: 0.015, freq: 7000, q: 1, out });
                    const note = TUNE[i % TUNE.length];
                    if (note) marimba(note, { at, gain: 0.045, dur: 0.4, out });
                    if (pos % 2) tone(midi(bar.chord[pos % bar.chord.length] + 12), { at, dur: 0.12, gain: 0.012, out });
                }
            }

            function schedule() {
                if (!running) return;
                while (next < ctx.currentTime + 0.6) {
                    play(step, next);
                    step++;
                    next += eighth;
                }
            }

            schedule();
            const timer = setInterval(schedule, 150);

            return {
                stop() {
                    running = false;
                    clearInterval(timer);
                    out.gain.setTargetAtTime?.(0.0001, ctx.currentTime, 0.2);
                },
                intensity(v) {
                    level = num(v) > 0 ? 1 : 0;
                },
                schedule,
            };
        }

        return { sfx, ambience };
    }

    window.OrangeSound = Object.assign(window.OrangeSound || {}, { riviera });
})();
