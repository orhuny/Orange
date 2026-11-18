/* ---------- Cockpit gauges (pressure | hull) flanking the multiplier ----------
           data-level (ok | warn | crit, set from JS) drives the accent colour. */
        .gauge {
            --gc: var(--hud); --gc-rgb: var(--hud-rgb);
            position: relative;
            display: flex; align-items: center; gap: 12px;
            padding: 10px 16px 10px 12px;
            background:
              radial-gradient(120% 140% at 0% 0%, rgba(var(--gc-rgb),0.18), transparent 55%),
              linear-gradient(160deg, rgba(var(--panel-rgb),0.72), rgba(var(--panel-rgb),0.92));
            border: 1px solid rgba(var(--gc-rgb),0.45);
            border-radius: 14px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.45), inset 0 1px 0 rgba(255,255,255,0.08), 0 0 18px rgba(var(--gc-rgb),0.15);
            backdrop-filter: blur(8px);
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }
        .gauge[data-level="warn"] { --gc: var(--gold); --gc-rgb: 251,191,36; }
        .gauge[data-level="crit"] { --gc: var(--warn); --gc-rgb: 239,68,68; animation: gauge-alarm 0.6s ease-in-out infinite alternate; }
        @keyframes gauge-alarm {
            to { border-color: rgba(239,68,68,0.95); box-shadow: 0 8px 24px rgba(0,0,0,0.45), inset 0 1px 0 rgba(255,255,255,0.08), 0 0 28px rgba(239,68,68,0.6); }
        }
        .crashed .gauge { filter: saturate(0.5) brightness(0.8); animation: none; }
        .gauge svg { display: block; flex: none; overflow: visible; }
        .gauge-text { min-width: 84px; }
        .gauge-hull .gauge-text { text-align: right; }
        .gauge-label {
            font-size: 10px; font-weight: 700; letter-spacing: 0.2em;
            text-transform: uppercase; color: var(--text-muted);
        }
        .gauge-value {
            font-size: 22px; font-weight: 800; line-height: 1.15;
            font-variant-numeric: tabular-nums;
            color: var(--hud-bright);
            text-shadow: 0 0 12px rgba(var(--gc-rgb),0.6), 0 2px 0 rgba(0,0,0,0.6);
            transition: color 0.3s ease;
        }
        .gauge[data-level="warn"] .gauge-value,
        .gauge[data-level="crit"] .gauge-value { color: var(--gc); }
        .gauge-value small { margin-left: 3px; font-size: 11px; font-weight: 600; color: var(--text-muted); text-shadow: none; }

        /* Pressure dial: 270° sweep with a red zone; the needle trembles while diving. */
        .dial-face { fill: rgba(0,0,0,0.4); stroke: rgba(var(--hud-rgb),0.25); stroke-width: 1; }
        .dial-track, .dial-arc { fill: none; stroke-width: 3; stroke-linecap: round; }
        .dial-track { stroke: rgba(var(--hud-rgb),0.15); stroke-dasharray: 75 100; }
        .dial-arc {
            stroke: var(--gc); stroke-dasharray: 0 100;
            filter: drop-shadow(0 0 3px var(--gc));
            transition: stroke-dasharray 0.2s linear, stroke 0.3s ease;
        }
        .dial-tick { stroke: rgba(var(--hud-rgb),0.6); stroke-width: 1.2; }
        .dial-tick.hot { stroke: var(--warn); }
        .dial-needle {
            stroke: var(--hud-bright); stroke-width: 1.8; stroke-linecap: round;
            transform-origin: 22px 22px; transform: rotate(-135deg);
            transition: transform 0.2s linear;
        }
        .dial-hub { fill: var(--gc); stroke: rgba(0,0,0,0.6); stroke-width: 1; }
        .dial-wobble { transform-origin: 22px 22px; }
        .running .dial-wobble { animation: needle-wobble 0.22s ease-in-out infinite alternate; }
        .critical .dial-wobble { animation-duration: 0.08s; }
        @keyframes needle-wobble { from { transform: rotate(-2.5deg); } to { transform: rotate(2.5deg); } }

        /* Hull: shield drains with the integrity, segmented bar shifts green → red (--hull-c from JS). */
        .hull-shield-outline { fill: rgba(0,0,0,0.4); stroke: var(--gc); stroke-width: 1.4; stroke-linejoin: round; transition: stroke 0.3s ease; }
        .hull-shield-level {
            fill: var(--hull-c, var(--good)); opacity: 0.9;
            transform-box: fill-box; transform-origin: bottom;
            transition: transform 0.2s linear, fill 0.3s ease;
        }
        .critical .gauge-hull svg { animation: hull-rattle 0.16s linear infinite; }
        @keyframes hull-rattle { 25% { transform: translate(-1px, 1px) rotate(-3deg); } 75% { transform: translate(1px, -1px) rotate(3deg); } }
        .hull-bar {
            margin: 6px 0 0 auto;
            width: 120px; height: 7px;
            background: rgba(255,255,255,0.1);
            border-radius: 2px;
            overflow: hidden;
            -webkit-mask: repeating-linear-gradient(90deg, #000 0 calc(10% - 2px), transparent calc(10% - 2px) 10%);
                    mask: repeating-linear-gradient(90deg, #000 0 calc(10% - 2px), transparent calc(10% - 2px) 10%);
        }
        .hull-fill {
            height: 100%;
            background: var(--hull-c, var(--good));
            box-shadow: 0 0 8px var(--hull-c, var(--good));
            transition: width 0.2s linear, background 0.3s ease;
        }

        /* ---------- Depth meter (left edge) ----------
           Glass tube from surface to the Challenger Deep; --p (0..1, set from JS)
           drives the needle, the travelled-depth fill and the readout chip. */
        .depth-meter {
            --p: 0; --dc: var(--hud-bright); --dc-rgb: var(--hud-rgb);
            position: absolute; left: 22px; top: 150px;
            height: clamp(200px, 46vh, 520px); width: 14px;
            pointer-events: none;
            z-index: 9;
        }
        .depth-track {
            position: absolute; inset: 0;
            border-radius: 999px;
            background: linear-gradient(180deg,
                rgba(150,220,215,0.55) 0%, rgba(60,110,140,0.5) 18%,
                rgba(40,40,80,0.55) 45%, rgba(25,15,40,0.7) 72%, rgba(90,12,24,0.8) 100%);
            border: 1px solid rgba(var(--hud-rgb),0.4);
            box-shadow: inset 2px 0 3px rgba(255,255,255,0.18), 0 4px 18px rgba(0,0,0,0.5);
        }
        .depth-fill {
            position: absolute; left: 3px; right: 3px; top: 3px;
            height: calc((100% - 6px) * var(--p));
            border-radius: 999px;
            background: linear-gradient(180deg, rgba(var(--dc-rgb),0.25), rgba(var(--dc-rgb),0.85));
            box-shadow: 0 0 10px rgba(var(--dc-rgb),0.6);
        }
        .depth-tick {
            position: absolute; left: 100%; top: var(--at);
            display: flex; align-items: center; gap: 4px;
            transform: translateY(-50%);
            font-size: 10px; font-weight: 700; letter-spacing: 0.08em;
            color: var(--text); opacity: 0.8; white-space: nowrap;
            text-shadow: 0 1px 2px #000, 0 0 6px #000;
        }
        .depth-meter::before {
            content: "DEPTH"; position: absolute; left: -4px; bottom: calc(100% + 8px);
            font-size: 9px; font-weight: 800; letter-spacing: 0.24em;
            color: var(--text-muted); text-shadow: 0 1px 2px #000;
        }
        .depth-tick::before { content: ""; width: 6px; height: 1px; background: rgba(var(--hud-rgb),0.6); }
        .depth-needle {
            position: absolute; left: -6px; top: calc(var(--p) * 100%);
            display: flex; align-items: center;
            transform: translateY(-50%);
        }
        .depth-needle::before {
            content: ""; width: 26px; height: 3px; border-radius: 2px;
            background: var(--dc); box-shadow: 0 0 8px var(--dc), 0 0 2px #000;
        }
        .depth-chip {
            margin-left: 6px; padding: 5px 10px 5px 9px;
            background: rgba(var(--panel-rgb),0.88);
            border: 1px solid rgba(var(--dc-rgb),0.6);
            border-radius: 4px 10px 10px 4px;
            box-shadow: 0 4px 14px rgba(0,0,0,0.5), 0 0 12px rgba(var(--dc-rgb),0.25);
            white-space: nowrap; line-height: 1.1;
        }
        .depth-chip b {
            display: block;
            font-size: 15px; font-weight: 800; font-variant-numeric: tabular-nums;
            color: var(--dc);
        }
        .depth-chip small {
            display: block; margin-top: 2px;
            font-size: 8px; font-weight: 700; letter-spacing: 0.18em;
            color: var(--text-muted);
        }
        :root[data-theme="burton"] .depth-chip b { font-family: "Palatino Linotype", "Book Antiqua", Georgia, serif; font-size: 17px; }
        .critical .depth-meter { --dc: var(--warn); --dc-rgb: 239,68,68; }
        .critical:not(.crashed) .depth-chip { animation: depth-alarm 0.6s ease-in-out infinite alternate; }
        @keyframes depth-alarm { to { box-shadow: 0 4px 14px rgba(0,0,0,0.5), 0 0 22px rgba(239,68,68,0.7); } }
        @media (max-width: 720px) {
            .depth-meter { left: 10px; top: 140px; width: 8px; height: clamp(160px, 34vh, 320px); }
            .depth-tick { display: none; }
            .depth-needle::before { width: 18px; }
            .depth-chip { padding: 3px 7px; }
            .depth-chip b { font-size: 12px; }
            :root[data-theme="burton"] .depth-chip b { font-size: 13px; }
            .depth-chip small { font-size: 7px; letter-spacing: 0.12em; }
        }

