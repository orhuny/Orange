<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Deep Dive — Orange Crash</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        :root {
            --shallow: #0ea5e9;
            --depth-1: #082f49;
            --depth-2: #020617;
            --abyss:   #000000;
            --hud:     #67e8f9;
            --warn:    #ef4444;
            --gold:    #fbbf24;
            --good:    #22c55e;
        }

        * { box-sizing: border-box; }
        html, body {
            margin: 0; padding: 0; height: 100%;
            font-family: ui-monospace, "SF Mono", Menlo, monospace;
            color: #e2e8f0;
            overflow: hidden;
            user-select: none;
        }

        /* ---------- Stage = container only, never filtered ---------- */
        .stage { position: fixed; inset: 0; background: var(--abyss); }

        /* All depth darkening lives on this layer so the HUD/multiplier
           stay readable. */
        .scene {
            position: absolute; inset: 0;
            background:
              radial-gradient(ellipse at 50% -10%, rgba(255,255,255,0.18) 0%, transparent 35%),
              linear-gradient(180deg,
                  var(--shallow) 0%,
                  #0c4a6e   18%,
                  var(--depth-1) 45%,
                  var(--depth-2) 75%,
                  var(--abyss) 100%);
            transition: filter 1s ease, background 2s ease;
            z-index: 1;
        }
        .stage.deep    .scene { filter: brightness(0.7) saturate(1.1); }
        .stage.abyss   .scene { filter: brightness(0.5) saturate(0.85) contrast(1.1); }
        .stage.critical .scene { filter: brightness(0.45) saturate(0.75) contrast(1.25) hue-rotate(-15deg); }

        /* ---------- God rays ---------- */
        .rays {
            position: absolute; inset: 0;
            background:
              repeating-linear-gradient(
                100deg,
                transparent 0 6%,
                rgba(255,255,255,0.05) 6.2% 8%,
                transparent 8.2% 12%
              );
            mix-blend-mode: screen;
            opacity: 0.7;
            transition: opacity 1.5s ease;
        }
        .deep .rays   { opacity: 0.18; }
        .abyss .rays  { opacity: 0; }

        /* ---------- Particles ---------- */
        .particles { position: absolute; inset: 0; pointer-events: none; overflow: hidden; }
        .particle {
            position: absolute;
            width: 3px; height: 3px; border-radius: 50%;
            background: rgba(255,255,255,0.7);
            animation: drift linear infinite;
        }
        @keyframes drift {
            from { transform: translateY(110vh) translateX(0); opacity: 0; }
            10%  { opacity: 0.8; }
            90%  { opacity: 0.8; }
            to   { transform: translateY(-10vh) translateX(40px); opacity: 0; }
        }

        /* ---------- Rising bubbles (always on) ---------- */
        .bubbles { position: absolute; inset: 0; pointer-events: none; overflow: hidden; z-index: 2; }
        .bubble {
            position: absolute;
            border-radius: 50%;
            background:
              radial-gradient(circle at 32% 30%, rgba(255,255,255,0.85) 0%, rgba(186,230,253,0.45) 35%, rgba(14,165,233,0.15) 70%, transparent 100%);
            box-shadow: inset 0 0 4px rgba(255,255,255,0.4);
            opacity: 0;
            animation: rise linear infinite;
        }
        @keyframes rise {
            0%   { transform: translate(0, 110vh) scale(0.6);  opacity: 0; }
            8%   { opacity: 0.85; }
            50%  { transform: translate(20px, 50vh) scale(1.0); }
            92%  { opacity: 0.7; }
            100% { transform: translate(-15px, -10vh) scale(1.1); opacity: 0; }
        }

        /* ---------- Caustic light shimmer (surface zone) ---------- */
        .caustics {
            position: absolute; inset: 0;
            background:
              radial-gradient(ellipse 50% 25% at 30% 18%, rgba(186,230,253,0.18), transparent 70%),
              radial-gradient(ellipse 60% 30% at 70% 12%, rgba(186,230,253,0.14), transparent 70%);
            mix-blend-mode: screen;
            opacity: 0.85;
            transition: opacity 1.5s ease;
            animation: shimmer 11s ease-in-out infinite;
            z-index: 3;
        }
        .deep .caustics  { opacity: 0.25; }
        .abyss .caustics, .critical .caustics { opacity: 0; }
        @keyframes shimmer {
            0%, 100% { transform: translateX(0)    scaleY(1); }
            50%      { transform: translateX(-25px) scaleY(1.06); }
        }

        /* ---------- Shallow-water tropical fish (early phase) ---------- */
        .fishes { position: absolute; inset: 0; pointer-events: none; overflow: hidden; z-index: 4; }
        .fish {
            position: absolute;
            opacity: 0.85;
            transition: opacity 1.5s ease;
            animation: swim linear infinite;
            filter: drop-shadow(0 4px 8px rgba(0,0,0,0.25));
        }
        .deep .fish, .abyss .fish, .critical .fish, .crashed .fish { opacity: 0; }
        .fish svg {
            display: block;
            animation: bob 2.4s ease-in-out infinite;
        }
        @keyframes swim {
            0%   { transform: translateX(-20vw); }
            100% { transform: translateX(120vw); }
        }
        @keyframes swim-reverse {
            0%   { transform: translateX(120vw) scaleX(-1); }
            100% { transform: translateX(-20vw) scaleX(-1); }
        }
        @keyframes bob {
            0%, 100% { transform: translateY(0); }
            50%      { transform: translateY(-6px); }
        }

        /* ---------- Distant creature silhouettes ---------- */
        .creatures { position: absolute; inset: 0; pointer-events: none; overflow: hidden; z-index: 4; }
        .creature {
            position: absolute;
            opacity: 0;
            transition: opacity 1.5s ease;
            animation: cross linear infinite;
            filter:
              blur(0.6px)
              drop-shadow(0 0 14px rgba(8,15,30,0.85))
              drop-shadow(0 0 22px rgba(103,232,249,0.18));
        }
        /* Hint at creatures even before deep zone, then ramp up. */
        .running  .creature { opacity: 0.35; }
        .deep     .creature { opacity: 0.7;  filter: blur(0.4px) drop-shadow(0 0 16px rgba(2,6,23,0.9)) drop-shadow(0 0 24px rgba(103,232,249,0.25)); }
        .abyss    .creature { opacity: 0.88; filter: blur(0.3px) drop-shadow(0 0 18px rgba(2,6,23,0.95)) drop-shadow(0 0 28px rgba(103,232,249,0.30)); }
        .critical .creature { opacity: 0.95; filter: blur(0.3px) drop-shadow(0 0 18px rgba(2,6,23,0.95)) drop-shadow(0 0 24px rgba(239,68,68,0.30)); }

        .creature svg {
            display: block;
            transform: scale(1.35);
            transform-origin: center;
        }
        @keyframes cross {
            0%   { transform: translateX(-35vw); }
            100% { transform: translateX(135vw); }
        }
        @keyframes cross-reverse {
            0%   { transform: translateX(135vw) scaleX(-1); }
            100% { transform: translateX(-35vw) scaleX(-1); }
        }

        /* ---------- Jellyfish (mid depth) ---------- */
        .jelly {
            position: absolute;
            width: 60px; height: 80px;
            opacity: 0;
            transition: opacity 1.5s ease;
            animation: float 9s ease-in-out infinite;
        }
        .deep .jelly, .abyss .jelly { opacity: 0.45; }
        .jelly::before {
            content: '';
            position: absolute; top: 0; left: 0; right: 0; height: 35px;
            background: radial-gradient(ellipse at 50% 60%, var(--hud) 0%, rgba(103,232,249,0.3) 50%, transparent 75%);
            border-radius: 50% 50% 40% 40%;
            box-shadow: 0 0 25px var(--hud);
        }
        .jelly::after {
            content: '';
            position: absolute; top: 30px; left: 25%; right: 25%; bottom: 0;
            background: linear-gradient(180deg, rgba(103,232,249,0.4) 0%, transparent 90%);
        }
        @keyframes float {
            0%, 100% { transform: translateY(0) }
            50%      { transform: translateY(-30px) }
        }

        /* ---------- Submarine ---------- */
        .sub-wrap {
            position: absolute; left: 50%; top: 30%;
            transform: translate(-50%, -50%);
            width: 288px; height: 156px;
            transition: top 12s linear, transform 0.15s ease;
            filter: drop-shadow(0 22px 30px rgba(0,0,0,0.55));
        }
        .running .sub-wrap   { top: 55%; }
        .deep .sub-wrap      { top: 65%; }
        .abyss .sub-wrap     { top: 70%; }
        .critical .sub-wrap  { top: 72%; animation: shake 0.18s linear infinite; }
        @keyframes shake {
            0%,100% { transform: translate(-50%,-50%); }
            20%     { transform: translate(-52%,-49%); }
            50%     { transform: translate(-48%,-51%); }
            80%     { transform: translate(-51%,-50%); }
        }
        .sub-svg { width: 100%; height: 100%; display: block; overflow: visible; }
        .propeller {
            transform-box: fill-box;
            transform-origin: center;
            animation: spin 0.5s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
        .running .propeller { animation-duration: 0.18s; }
        .periscope-light { opacity: 0; }
        .running .periscope-light { opacity: 0.85; }

        /* ---------- HUD ---------- */
        .hud-top {
            position: absolute; top: 0; left: 0; right: 0;
            display: flex; justify-content: space-between; align-items: flex-start;
            padding: 16px 20px;
            pointer-events: none;
            z-index: 10;
        }

        .gauge {
            display: flex; align-items: center; gap: 10px;
            padding: 10px 14px;
            background: rgba(2,6,23,0.55);
            border: 1px solid rgba(103,232,249,0.4);
            border-radius: 12px;
            backdrop-filter: blur(6px);
        }
        .gauge svg { display:block; }
        .gauge-label {
            font-size: 10px; letter-spacing: 0.18em;
            color: var(--hud); opacity: 0.8;
            text-transform: uppercase;
        }
        .gauge-value {
            font-size: 14px; font-weight: 700;
            color: var(--hud);
        }

        /* hull bar */
        .hull-bar {
            margin-top: 6px;
            width: 130px; height: 8px;
            background: rgba(255,255,255,0.08);
            border: 1px solid rgba(103,232,249,0.4);
            border-radius: 2px;
            overflow: hidden;
        }
        .hull-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--good), var(--gold), var(--warn));
            transition: width 0.2s linear;
        }

        /* ---------- Recent crashes history ---------- */
        .history-wrap {
            position: absolute;
            top: 96px; left: 50%;
            transform: translateX(-50%);
            display: flex; flex-direction: column; align-items: center;
            gap: 6px;
            z-index: 9;
            max-width: 96vw;
        }
        .history-label {
            font-size: 10px; letter-spacing: 0.32em;
            color: var(--hud); opacity: 0.7;
            text-transform: uppercase;
            text-shadow: 0 0 8px rgba(103,232,249,0.3);
        }
        .history {
            display: flex; gap: 7px;
            padding: 8px 14px;
            background: rgba(2,6,23,0.55);
            border: 1px solid rgba(103,232,249,0.25);
            border-radius: 999px;
            backdrop-filter: blur(6px);
            overflow: hidden;
        }
        .history-chip {
            font-size: 14px; font-weight: 700;
            padding: 5px 12px;
            border-radius: 999px;
            white-space: nowrap;
            letter-spacing: 0.04em;
            transition: transform 0.3s ease;
        }
        .history-chip.tier-low  { background: rgba(239,68,68,0.20);  color: #fecaca; }
        .history-chip.tier-mid  { background: rgba(251,191,36,0.20); color: #fef3c7; }
        .history-chip.tier-high { background: rgba(34,197,94,0.20);  color: #bbf7d0; }
        .history-chip.tier-epic {
            background: rgba(168,85,247,0.30);
            color: #f3e8ff;
            box-shadow: 0 0 12px rgba(168,85,247,0.45);
        }
        .history-chip.is-new { animation: chip-pop 0.5s cubic-bezier(0.34, 1.56, 0.64, 1); }
        @keyframes chip-pop {
            0%   { transform: scale(0); opacity: 0; }
            60%  { transform: scale(1.15); opacity: 1; }
            100% { transform: scale(1); }
        }
        @media (max-width: 500px) {
            .history-wrap { top: 84px; gap: 4px; }
            .history-label { font-size: 9px; letter-spacing: 0.24em; }
            .history { gap: 5px; padding: 6px 10px; }
            .history-chip { font-size: 12px; padding: 4px 9px; }
        }

        /* ---------- Multiplier (positioned between submarine and CASH OUT) ---------- */
        .center {
            position: absolute;
            left: 0; right: 0;
            bottom: 130px;
            display: flex; flex-direction: column;
            align-items: center; gap: 4px;
            pointer-events: none;
            z-index: 8;
        }
        .multiplier {
            font-family: "SF Mono", ui-monospace, monospace;
            font-size: clamp(72px, 14vw, 160px);
            font-weight: 700; line-height: 0.95;
            letter-spacing: -0.04em;
            background: linear-gradient(180deg, #cffafe 0%, var(--hud) 50%, #0891b2 100%);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            text-shadow: 0 0 40px rgba(103,232,249,0.4);
            transition: text-shadow 0.5s ease, transform 0.2s ease;
        }
        .deep .multiplier {
            background: linear-gradient(180deg, #fef3c7 0%, var(--gold) 50%, #b45309 100%);
            -webkit-background-clip: text; background-clip: text;
            text-shadow: 0 0 50px rgba(251,191,36,0.55);
        }
        .critical .multiplier {
            background: linear-gradient(180deg, #fecaca 0%, var(--warn) 50%, #7f1d1d 100%);
            -webkit-background-clip: text; background-clip: text;
            text-shadow: 0 0 70px rgba(239,68,68,0.85);
            animation: pulse 0.6s ease-in-out infinite;
        }
        .crashed .multiplier {
            color: var(--warn) !important;
            background: none !important; -webkit-background-clip: initial; background-clip: initial;
            text-shadow: 0 0 60px var(--warn);
            animation: shatter 0.6s forwards;
        }
        @keyframes pulse { 50% { transform: scale(1.04); } }
        @keyframes shatter {
            0%   { transform: scale(1); filter: blur(0); opacity: 1; }
            40%  { transform: scale(1.15); filter: blur(2px); }
            100% { transform: scale(0.6); filter: blur(8px); opacity: 0.4; }
        }
        .state {
            margin: 0;
            font-size: 13px; letter-spacing: 0.3em;
            color: var(--hud); opacity: 0.85;
            text-transform: uppercase;
            text-shadow: 0 0 10px rgba(103,232,249,0.4);
        }

        /* ---------- Warnings (only critical) ---------- */
        .warnings {
            position: absolute; top: 30%; left: 50%;
            transform: translate(-50%, 0);
            display: flex; flex-direction: column; gap: 6px;
            text-align: center;
            font-size: 11px; letter-spacing: 0.18em;
            color: var(--warn); font-weight: 700;
            opacity: 0;
            text-shadow: 0 0 12px var(--warn);
            z-index: 9;
        }
        .critical .warnings { opacity: 1; animation: blink 0.6s linear infinite; }
        @keyframes blink { 50% { opacity: 0.35; } }

        /* ---------- Cash out ---------- */
        .cashout-wrap {
            position: absolute; bottom: 32px; left: 0; right: 0;
            display: flex; justify-content: center;
            z-index: 12;
        }
        .cashout {
            padding: 18px 56px;
            font-family: inherit; font-size: 16px; font-weight: 700;
            letter-spacing: 0.25em; text-transform: uppercase;
            color: #022c22; background: var(--good);
            border: none; border-radius: 999px;
            cursor: pointer;
            box-shadow: 0 0 30px rgba(34,197,94,0.7), inset 0 -3px 0 rgba(0,0,0,0.18);
            transition: transform 0.15s ease, box-shadow 0.3s ease, opacity 0.3s ease;
        }
        .cashout:hover { transform: translateY(-2px); box-shadow: 0 0 50px rgba(34,197,94,0.9); }
        .cashout:active { transform: translateY(1px); }
        .cashout:disabled { opacity: 0.35; cursor: not-allowed; box-shadow: none; }
        .cashout small {
            display: block; font-size: 9px; letter-spacing: 0.2em;
            opacity: 0.7; margin-top: 4px; font-weight: 500;
        }

        /* ---------- Sonar ---------- */
        .sonar {
            position: absolute; bottom: 110px; left: 50%;
            transform: translateX(-50%);
            width: 220px; height: 4px;
            background: linear-gradient(90deg, transparent 0%, rgba(103,232,249,0.45) 50%, transparent 100%);
            opacity: 0.65;
            z-index: 11;
        }
        .sonar::after {
            content: '';
            position: absolute; top: -2px; left: 0;
            width: 8px; height: 8px; border-radius: 50%;
            background: var(--hud);
            box-shadow: 0 0 14px var(--hud);
            animation: sonar-blip 2.8s linear infinite;
        }
        @keyframes sonar-blip {
            0%   { left: 0; }
            100% { left: 100%; }
        }
        .critical .sonar::after { animation-duration: 0.6s; background: var(--warn); box-shadow: 0 0 14px var(--warn); }

        /* ---------- Strobes (critical) ---------- */
        .strobe {
            position: absolute; inset: 0;
            background: radial-gradient(ellipse at 50% 50%, rgba(239,68,68,0.45) 0%, transparent 60%);
            opacity: 0; pointer-events: none;
            z-index: 6;
        }
        .critical .strobe { animation: strobe 0.4s linear infinite; }
        @keyframes strobe {
            0%, 100% { opacity: 0.0; }
            50%      { opacity: 0.8; }
        }

        /* ===================================================
           CRASH / IMPLODE SEQUENCE (multi-layer)
           Layer order from back to front:
             .scene → .strobe → .shockwave → .crash-flash →
             .crash-debris → .crash-cracks → .crash-banner
           =================================================== */

        /* (1) Hard white flash — punches first, fades fast */
        .crash-flash {
            position: absolute; inset: 0;
            background: white;
            opacity: 0;
            pointer-events: none;
            z-index: 48;
        }
        .crashed .crash-flash {
            animation: crash-flash 0.9s ease-out forwards;
        }
        @keyframes crash-flash {
            0%   { opacity: 0; }
            6%   { opacity: 1; }
            18%  { opacity: 0.9; }
            55%  { opacity: 0.25; }
            100% { opacity: 0; }
        }

        /* (2) Expanding shockwave ring */
        .shockwave {
            position: absolute; left: 50%; top: 50%;
            width: 60px; height: 60px;
            border-radius: 50%;
            border: 4px solid white;
            background: radial-gradient(circle, rgba(255,255,255,0.55) 0%, transparent 65%);
            transform: translate(-50%, -50%) scale(0);
            opacity: 0;
            pointer-events: none;
            z-index: 49;
            box-shadow: 0 0 60px white, inset 0 0 60px white;
        }
        .crashed .shockwave {
            animation: shockwave 1.1s cubic-bezier(0.2, 0.8, 0.4, 1) forwards;
        }
        @keyframes shockwave {
            0%   { transform: translate(-50%, -50%) scale(0); opacity: 0; border-width: 6px; }
            10%  { opacity: 1; }
            100% { transform: translate(-50%, -50%) scale(45); opacity: 0; border-width: 1px; }
        }

        /* (3) Debris — 24 fragments shooting outward */
        .crash-debris {
            position: absolute; left: 50%; top: 50%;
            width: 1px; height: 1px;
            pointer-events: none;
            z-index: 50;
        }
        .frag {
            position: absolute; left: 0; top: 0;
            width: 6px; height: 6px;
            background: linear-gradient(135deg, #fef3c7, #f59e0b 50%, #7f1d1d);
            border-radius: 1px;
            opacity: 0;
            transform: translate(-50%, -50%);
            box-shadow: 0 0 8px rgba(251,191,36,0.7);
        }
        .crashed .frag {
            animation: frag-fly 1.4s cubic-bezier(0.15, 0.85, 0.4, 1) forwards;
        }
        @keyframes frag-fly {
            0%   { opacity: 0; transform: translate(-50%, -50%) rotate(0); }
            8%   { opacity: 1; }
            100% {
                opacity: 0;
                transform: translate(calc(-50% + var(--dx, 200px)), calc(-50% + var(--dy, 200px))) rotate(720deg);
            }
        }

        /* (4) Glass crack overlay — SVG inline, masked over the dome */
        .crash-cracks {
            position: absolute; inset: 0;
            opacity: 0;
            pointer-events: none;
            mix-blend-mode: screen;
            z-index: 52;
        }
        .crashed .crash-cracks {
            animation: cracks-in 1.6s ease-out forwards;
        }
        @keyframes cracks-in {
            0%   { opacity: 0; transform: scale(0.4); }
            12%  { opacity: 0.95; transform: scale(1); }
            65%  { opacity: 0.7; }
            100% { opacity: 0; }
        }

        /* (5) HULL BREACH banner */
        .crash-banner {
            position: absolute; left: 50%; top: 36%;
            transform: translate(-50%, 0) scale(0.5);
            opacity: 0;
            font-size: clamp(20px, 5vw, 38px);
            font-weight: 800; letter-spacing: 0.32em;
            color: var(--warn);
            text-shadow: 0 0 20px var(--warn), 0 0 40px var(--warn);
            text-transform: uppercase;
            pointer-events: none;
            white-space: nowrap;
            z-index: 53;
        }
        .crashed .crash-banner {
            animation: banner-slam 1.6s cubic-bezier(0.2, 0.9, 0.3, 1) forwards;
        }
        @keyframes banner-slam {
            0%   { opacity: 0; transform: translate(-50%, 0) scale(0.5); letter-spacing: 0.6em; }
            12%  { opacity: 1; transform: translate(-50%, 0) scale(1.1); letter-spacing: 0.32em; }
            22%  { transform: translate(-50%, 0) scale(1); }
            70%  { opacity: 1; }
            100% { opacity: 0; transform: translate(-50%, -8px) scale(0.95); }
        }

        /* (6) Stage shake — strong then settle */
        .crashed .stage { animation: stage-rumble 0.9s linear; }
        @keyframes stage-rumble {
            0%   { transform: translate(0,0); }
            10%  { transform: translate(-8px, 6px) rotate(-0.4deg); filter: hue-rotate(20deg) saturate(1.3); }
            20%  { transform: translate(7px, -5px) rotate(0.3deg); filter: hue-rotate(-30deg); }
            30%  { transform: translate(-5px, 4px) rotate(-0.2deg); }
            45%  { transform: translate(4px, -3px); }
            60%  { transform: translate(-2px, 2px); }
            80%  { transform: translate(1px, -1px); }
            100% { transform: translate(0,0); filter: none; }
        }

        /* (7) Submarine itself rolls + fades on impact */
        .crashed .sub-wrap {
            animation: sub-collapse 1.3s cubic-bezier(0.4, 0.1, 0.6, 1) forwards;
        }
        @keyframes sub-collapse {
            0%   { transform: translate(-50%, -50%) scale(1) rotate(0); opacity: 1; filter: none; }
            15%  { transform: translate(-50%, -52%) scale(0.85) rotate(-6deg); filter: brightness(2); }
            45%  { transform: translate(-50%, -44%) scale(1.4) rotate(15deg); opacity: 0.6; filter: blur(2px); }
            100% { transform: translate(-50%, -30%) scale(0.4) rotate(60deg); opacity: 0; filter: blur(8px); }
        }

        /* ---------- Log ---------- */
        .log {
            position: absolute; right: 12px; bottom: 12px;
            width: 220px; max-height: 110px; overflow: hidden;
            font-size: 9px; line-height: 1.5; color: var(--hud); opacity: 0.45;
            z-index: 10; pointer-events: none;
            text-align: right;
        }
        .log div { padding: 1px 0; }

        /* ---------- Mobile tighten ---------- */
        @media (max-width: 500px) {
            .multiplier { font-size: 22vw; }
            .gauge { padding: 8px 10px; }
            .gauge-value { font-size: 12px; }
            .hull-bar { width: 100px; }
            .cashout { padding: 16px 40px; font-size: 14px; }
            .sonar { width: 180px; bottom: 100px; }
        }
    </style>
</head>
<body>
    <div id="stage" class="stage waiting">
        <!-- Filtered scene (depth darkening lives here) -->
        <div class="scene">
            <div class="rays"></div>
            <div id="particles" class="particles"></div>
            <div class="caustics"></div>
            <div id="bubbles"  class="bubbles"></div>
            <div class="fishes">
                <!-- Orange tropical fish, left → right -->
                <div class="fish" style="left:0; top:18%; animation-duration:18s; animation-delay:-3s;">
                    <svg width="46" height="26" viewBox="0 0 46 26" xmlns="http://www.w3.org/2000/svg">
                        <ellipse cx="22" cy="13" rx="14" ry="7" fill="#f97316"/>
                        <ellipse cx="22" cy="13" rx="14" ry="7" fill="none" stroke="#7c2d12" stroke-width="0.6"/>
                        <path d="M 8 13 L 1 6 L 1 20 Z" fill="#fb923c" stroke="#7c2d12" stroke-width="0.6"/>
                        <path d="M 22 6 Q 26 2 30 6 Z" fill="#fdba74"/>
                        <line x1="14" y1="9" x2="14" y2="17" stroke="#7c2d12" stroke-width="0.7"/>
                        <line x1="20" y1="9" x2="20" y2="17" stroke="#7c2d12" stroke-width="0.7"/>
                        <circle cx="34" cy="11" r="1.6" fill="#fff"/>
                        <circle cx="34.4" cy="11.2" r="0.9" fill="#0f172a"/>
                    </svg>
                </div>

                <!-- Yellow & black striped fish, right → left -->
                <div class="fish" style="right:0; top:32%; animation: swim-reverse 22s linear infinite; animation-delay:-8s;">
                    <svg width="40" height="22" viewBox="0 0 40 22" xmlns="http://www.w3.org/2000/svg">
                        <ellipse cx="19" cy="11" rx="12" ry="6" fill="#fde047"/>
                        <ellipse cx="19" cy="11" rx="12" ry="6" fill="none" stroke="#713f12" stroke-width="0.5"/>
                        <path d="M 7 11 L 1 5 L 1 17 Z" fill="#facc15"/>
                        <rect x="13" y="5" width="2" height="12" fill="#1e293b"/>
                        <rect x="20" y="5" width="2" height="12" fill="#1e293b"/>
                        <rect x="27" y="6" width="2" height="10" fill="#1e293b"/>
                        <circle cx="29" cy="9" r="1.3" fill="#fff"/>
                        <circle cx="29.3" cy="9.2" r="0.7" fill="#0f172a"/>
                    </svg>
                </div>

                <!-- Pink fish, left → right -->
                <div class="fish" style="left:0; top:45%; animation-duration:26s; animation-delay:-15s;">
                    <svg width="38" height="22" viewBox="0 0 38 22" xmlns="http://www.w3.org/2000/svg">
                        <ellipse cx="18" cy="11" rx="11" ry="6" fill="#f472b6"/>
                        <ellipse cx="18" cy="11" rx="11" ry="6" fill="none" stroke="#831843" stroke-width="0.5"/>
                        <path d="M 7 11 L 1 5 L 1 17 Z" fill="#f9a8d4"/>
                        <path d="M 18 5 Q 22 1 26 5 Z" fill="#fbcfe8"/>
                        <circle cx="28" cy="9" r="1.4" fill="#fff"/>
                        <circle cx="28.3" cy="9.2" r="0.8" fill="#0f172a"/>
                    </svg>
                </div>

                <!-- Cyan fish, right → left, smaller -->
                <div class="fish" style="right:0; top:25%; animation: swim-reverse 16s linear infinite; animation-delay:-1s;">
                    <svg width="32" height="18" viewBox="0 0 32 18" xmlns="http://www.w3.org/2000/svg">
                        <ellipse cx="15" cy="9" rx="9" ry="5" fill="#22d3ee"/>
                        <ellipse cx="15" cy="9" rx="9" ry="5" fill="none" stroke="#155e75" stroke-width="0.5"/>
                        <path d="M 6 9 L 1 4 L 1 14 Z" fill="#67e8f9"/>
                        <circle cx="22" cy="7.5" r="1.2" fill="#fff"/>
                        <circle cx="22.3" cy="7.7" r="0.7" fill="#0f172a"/>
                    </svg>
                </div>

                <!-- Purple fish, left → right -->
                <div class="fish" style="left:0; top:55%; animation-duration:32s; animation-delay:-20s;">
                    <svg width="42" height="24" viewBox="0 0 42 24" xmlns="http://www.w3.org/2000/svg">
                        <ellipse cx="20" cy="12" rx="13" ry="7" fill="#a855f7"/>
                        <ellipse cx="20" cy="12" rx="13" ry="7" fill="none" stroke="#581c87" stroke-width="0.5"/>
                        <path d="M 7 12 L 1 5 L 1 19 Z" fill="#c084fc"/>
                        <path d="M 20 5 Q 24 1 28 5 Z" fill="#d8b4fe"/>
                        <circle cx="31" cy="10" r="1.5" fill="#fff"/>
                        <circle cx="31.3" cy="10.2" r="0.8" fill="#0f172a"/>
                    </svg>
                </div>
            </div>

            <div id="creatures" class="creatures">
                <!-- Whale silhouette, slow drift left → right (only visible in deep+) -->
                <div class="creature" style="left:0; top:55%; animation-duration:42s; animation-delay:-15s;">
                    <svg width="220" height="60" viewBox="0 0 220 60" xmlns="http://www.w3.org/2000/svg">
                        <path d="M 20 30
                                 Q 10 20 5 30
                                 Q 10 35 25 32
                                 Q 60 18 130 22
                                 Q 195 25 200 35
                                 Q 215 32 215 38
                                 Q 200 42 180 42
                                 Q 110 50 60 42
                                 Q 30 40 20 30 Z"
                              fill="rgba(2,6,23,0.95)" stroke="rgba(15,23,42,1)" stroke-width="0.6"/>
                        <ellipse cx="105" cy="13" rx="18" ry="3" fill="rgba(2,6,23,0.85)"/>
                        <circle cx="180" cy="32" r="1.8" fill="rgba(255,255,255,0.6)"/>
                    </svg>
                </div>

                <!-- Anglerfish-style silhouette, right → left -->
                <div class="creature" style="right:0; top:75%; animation: cross-reverse 56s linear infinite; animation-delay:-30s;">
                    <svg width="120" height="55" viewBox="0 0 120 55" xmlns="http://www.w3.org/2000/svg">
                        <path d="M 10 30
                                 Q 25 10 60 16
                                 Q 95 22 110 30
                                 Q 95 38 65 42
                                 Q 30 45 18 38
                                 Q 5 36 10 30 Z"
                              fill="rgba(2,6,23,0.95)" stroke="rgba(15,23,42,1)" stroke-width="0.6"/>
                        <path d="M 105 28 Q 118 22 115 32 Q 118 35 110 35 Z" fill="rgba(2,6,23,0.9)"/>
                        <circle cx="115" cy="30" r="2" fill="rgba(252,211,77,1)" filter="url(#glow)"/>
                        <circle cx="115" cy="30" r="4" fill="rgba(252,211,77,0.4)"/>
                        <circle cx="115" cy="30" r="7" fill="rgba(252,211,77,0.18)"/>
                        <circle cx="22" cy="28" r="1" fill="rgba(186,230,253,0.7)"/>
                    </svg>
                </div>

                <!-- Eel-like serpent silhouette, slow weaving (top) -->
                <div class="creature" style="left:0; top:35%; animation-duration:70s; animation-delay:-40s;">
                    <svg width="280" height="40" viewBox="0 0 280 40" xmlns="http://www.w3.org/2000/svg">
                        <path d="M 5 20
                                 Q 40 5 80 20
                                 Q 120 35 160 20
                                 Q 200 5 240 20
                                 Q 260 26 270 22
                                 L 268 25
                                 Q 240 30 200 25
                                 Q 160 30 120 25
                                 Q 80 30 40 25
                                 Q 15 22 5 20 Z"
                              fill="rgba(2,6,23,0.92)" stroke="rgba(15,23,42,1)" stroke-width="0.5"/>
                        <circle cx="20" cy="20" r="1.2" fill="rgba(255,255,255,0.55)"/>
                    </svg>
                </div>
            </div>

            <div class="jelly" style="left:12%; top:50%; animation-delay:-2s;"></div>
            <div class="jelly" style="left:80%; top:35%; animation-delay:-5s;"></div>
            <div class="jelly" style="left:25%; top:70%; animation-delay:-7s;"></div>

            <div class="sub-wrap">
                <svg class="sub-svg" viewBox="0 0 240 130" xmlns="http://www.w3.org/2000/svg">
                    <defs>
                        <linearGradient id="bodyGrad" x1="0" x2="0" y1="0" y2="1">
                            <stop offset="0"    stop-color="#5a6471"/>
                            <stop offset="0.45" stop-color="#3a4250"/>
                            <stop offset="1"    stop-color="#1c2230"/>
                        </linearGradient>
                        <linearGradient id="towerGrad" x1="0" x2="0" y1="0" y2="1">
                            <stop offset="0" stop-color="#6b7480"/>
                            <stop offset="1" stop-color="#2d3340"/>
                        </linearGradient>
                        <linearGradient id="amber" x1="0" x2="0" y1="0" y2="1">
                            <stop offset="0" stop-color="#fcd34d"/>
                            <stop offset="1" stop-color="#d97706"/>
                        </linearGradient>
                        <radialGradient id="port" cx="0.35" cy="0.35" r="0.85">
                            <stop offset="0"    stop-color="#bae6fd"/>
                            <stop offset="0.45" stop-color="#1d4ed8"/>
                            <stop offset="1"    stop-color="#0b1230"/>
                        </radialGradient>
                    </defs>

                    <!-- propeller (back / left) -->
                    <g transform="translate(20, 78)">
                        <g class="propeller">
                            <ellipse rx="12" ry="3" fill="url(#amber)" opacity="0.95"/>
                            <ellipse rx="3" ry="12" fill="url(#amber)" opacity="0.95"/>
                            <circle r="3.5" fill="#7c2d12"/>
                        </g>
                    </g>

                    <!-- yellow tail fin -->
                    <path d="M 35 78 L 35 105 L 60 86 Z" fill="url(#amber)" stroke="#7c2d12" stroke-width="0.6"/>
                    <path d="M 35 78 L 35 50  L 60 70 Z" fill="url(#amber)" stroke="#7c2d12" stroke-width="0.6"/>

                    <!-- main body -->
                    <ellipse cx="135" cy="78" rx="100" ry="34" fill="url(#bodyGrad)" stroke="#0b1220" stroke-width="1.2"/>

                    <!-- amber accent stripes -->
                    <path d="M 50 64 Q 135 50 220 70" stroke="url(#amber)" stroke-width="2.2" fill="none" opacity="0.85"/>
                    <path d="M 50 92 Q 135 106 220 90" stroke="url(#amber)" stroke-width="2.2" fill="none" opacity="0.85"/>

                    <!-- bow nose accent -->
                    <path d="M 225 70 Q 240 78 225 86 Z" fill="url(#amber)"/>

                    <!-- conning tower -->
                    <path d="M 110 50 Q 110 30 130 30 L 175 30 Q 195 30 195 50 L 195 56 L 110 56 Z"
                          fill="url(#towerGrad)" stroke="#0b1220" stroke-width="1.1"/>
                    <line x1="110" y1="42" x2="195" y2="42" stroke="url(#amber)" stroke-width="1.4" opacity="0.8"/>

                    <!-- periscope -->
                    <rect x="178" y="8" width="3" height="22" fill="#475569" rx="1"/>
                    <path d="M 179.5 8 Q 192 8 192 18" stroke="#475569" stroke-width="3" fill="none" stroke-linecap="round"/>
                    <circle class="periscope-light" cx="192" cy="18" r="2.4" fill="#fcd34d"/>

                    <!-- 3 portholes -->
                    <g>
                        <circle cx="90"  cy="78" r="13"  fill="url(#amber)"/>
                        <circle cx="90"  cy="78" r="10.5" fill="url(#port)"/>
                        <circle cx="90"  cy="64" r="0.9" fill="#7c2d12"/>
                        <circle cx="90"  cy="92" r="0.9" fill="#7c2d12"/>
                        <circle cx="76"  cy="78" r="0.9" fill="#7c2d12"/>
                        <circle cx="104" cy="78" r="0.9" fill="#7c2d12"/>
                    </g>
                    <g>
                        <circle cx="135" cy="78" r="13"  fill="url(#amber)"/>
                        <circle cx="135" cy="78" r="10.5" fill="url(#port)"/>
                        <circle cx="135" cy="64" r="0.9" fill="#7c2d12"/>
                        <circle cx="135" cy="92" r="0.9" fill="#7c2d12"/>
                        <circle cx="121" cy="78" r="0.9" fill="#7c2d12"/>
                        <circle cx="149" cy="78" r="0.9" fill="#7c2d12"/>
                    </g>
                    <g>
                        <circle cx="180" cy="78" r="13"  fill="url(#amber)"/>
                        <circle cx="180" cy="78" r="10.5" fill="url(#port)"/>
                        <circle cx="180" cy="64" r="0.9" fill="#7c2d12"/>
                        <circle cx="180" cy="92" r="0.9" fill="#7c2d12"/>
                        <circle cx="166" cy="78" r="0.9" fill="#7c2d12"/>
                        <circle cx="194" cy="78" r="0.9" fill="#7c2d12"/>
                    </g>

                    <!-- glossy top highlight -->
                    <ellipse cx="120" cy="58" rx="55" ry="4" fill="white" opacity="0.18"/>
                </svg>
            </div>
        </div>

        <!-- Strobe sits above the scene but below the UI -->
        <div class="strobe"></div>

        <!-- HUD -->
        <div class="hud-top">
            <div class="gauge" title="Pressure">
                <svg width="38" height="38" viewBox="0 0 38 38">
                    <circle cx="19" cy="19" r="16" fill="none" stroke="rgba(103,232,249,0.25)" stroke-width="2"/>
                    <circle id="pressureArc" cx="19" cy="19" r="16" fill="none"
                            stroke="var(--hud)" stroke-width="2"
                            stroke-dasharray="100 100" stroke-dashoffset="100"
                            transform="rotate(-90 19 19)" stroke-linecap="round"
                            style="transition: stroke-dashoffset 0.2s ease, stroke 0.3s ease;"/>
                    <circle cx="19" cy="19" r="2" fill="var(--hud)"/>
                </svg>
                <div>
                    <div class="gauge-label">Pressure</div>
                    <div class="gauge-value"><span id="pressureValue">0</span> bar</div>
                </div>
            </div>

            <div class="gauge" title="Hull integrity">
                <div>
                    <div class="gauge-label">Hull integrity</div>
                    <div class="gauge-value"><span id="hullValue">100</span>%</div>
                    <div class="hull-bar"><div id="hullFill" class="hull-fill" style="width:100%"></div></div>
                </div>
            </div>
        </div>

        <!-- Recent crashes -->
        <div class="history-wrap" aria-label="Recent crashes">
            <div class="history-label">Recent crashes</div>
            <div id="history" class="history"></div>
        </div>

        <!-- State + multiplier (anchored above CASH OUT, leaves the descending sub visible) -->
        <div class="center">
            <div id="state" class="state">Awaiting next dive…</div>
            <div id="multiplier" class="multiplier">1.00x</div>
        </div>

        <!-- Critical warnings -->
        <div class="warnings">
            <div>⚠ PRESSURE CRITICAL</div>
            <div>⚠ HULL DAMAGE DETECTED</div>
        </div>

        <!-- Sonar -->
        <div class="sonar"></div>

        <!-- Cash out -->
        <div class="cashout-wrap">
            <button id="cashout" class="cashout" disabled>
                CASH OUT
                <small id="cashoutHint">awaiting dive</small>
            </button>
        </div>

        <!-- Implode sequence layers -->
        <div class="shockwave"></div>
        <div class="crash-flash"></div>
        <div id="debris" class="crash-debris"></div>
        <svg class="crash-cracks" viewBox="0 0 100 100" preserveAspectRatio="none">
            <defs>
                <radialGradient id="crackGrad" cx="50%" cy="50%" r="60%">
                    <stop offset="0%" stop-color="#ffffff" stop-opacity="1"/>
                    <stop offset="50%" stop-color="#ffffff" stop-opacity="0.7"/>
                    <stop offset="100%" stop-color="#ffffff" stop-opacity="0"/>
                </radialGradient>
            </defs>
            <g stroke="url(#crackGrad)" stroke-width="0.18" fill="none" stroke-linecap="round" opacity="0.95">
                <!-- center web -->
                <path d="M50,50 L8,4"/>
                <path d="M50,50 L94,6"/>
                <path d="M50,50 L4,38"/>
                <path d="M50,50 L98,42"/>
                <path d="M50,50 L12,98"/>
                <path d="M50,50 L92,96"/>
                <path d="M50,50 L48,2"/>
                <path d="M50,50 L52,99"/>
                <!-- secondary fractures -->
                <path d="M50,50 L26,18 L32,12 L38,8"/>
                <path d="M50,50 L70,28 L78,22 L86,18"/>
                <path d="M50,50 L24,68 L18,80 L8,90"/>
                <path d="M50,50 L74,72 L82,82 L92,90"/>
                <path d="M50,50 L18,46 L8,48"/>
                <path d="M50,50 L82,54 L94,52"/>
                <!-- branches off the spokes -->
                <path d="M30,30 L18,22 M30,30 L34,18"/>
                <path d="M70,30 L84,20 M70,30 L72,16"/>
                <path d="M30,72 L20,84 M30,72 L26,86"/>
                <path d="M72,70 L86,82 M72,70 L78,86"/>
            </g>
        </svg>
        <div class="crash-banner">⚠ HULL BREACH ⚠</div>

        <!-- Activity log -->
        <div id="log" class="log"></div>
    </div>

    <script type="module">
        const $stage = document.getElementById('stage');
        const $mult = document.getElementById('multiplier');
        const $state = document.getElementById('state');
        const $log = document.getElementById('log');
        const $pressArc = document.getElementById('pressureArc');
        const $pressVal = document.getElementById('pressureValue');
        const $hullFill = document.getElementById('hullFill');
        const $hullVal = document.getElementById('hullValue');
        const $cashout = document.getElementById('cashout');
        const $cashoutHint = document.getElementById('cashoutHint');

        // rising bubbles (24, varying size + speed)
        const bubbleHost = document.getElementById('bubbles');
        for (let i = 0; i < 24; i++) {
            const b = document.createElement('div');
            b.className = 'bubble';
            const size = 4 + Math.random() * 14;
            b.style.width = b.style.height = size + 'px';
            b.style.left = (Math.random() * 100) + 'vw';
            b.style.animationDuration = (10 + Math.random() * 16) + 's';
            b.style.animationDelay = (-Math.random() * 18) + 's';
            bubbleHost.appendChild(b);
        }

        // ambient silt particles
        const particleHost = document.getElementById('particles');
        for (let i = 0; i < 50; i++) {
            const p = document.createElement('div');
            p.className = 'particle';
            p.style.left = (Math.random() * 100) + 'vw';
            p.style.animationDuration = (8 + Math.random() * 14) + 's';
            p.style.animationDelay = (-Math.random() * 16) + 's';
            p.style.opacity = (0.3 + Math.random() * 0.5);
            particleHost.appendChild(p);
        }

        // 24 hull fragments — preset random vectors, only animated when .crashed fires
        const debrisHost = document.getElementById('debris');
        function rebuildDebris() {
            debrisHost.innerHTML = '';
            for (let i = 0; i < 24; i++) {
                const f = document.createElement('div');
                f.className = 'frag';
                const angle = (i / 24) * Math.PI * 2 + (Math.random() - 0.5) * 0.5;
                const dist = 280 + Math.random() * 320;
                f.style.setProperty('--dx', Math.cos(angle) * dist + 'px');
                f.style.setProperty('--dy', Math.sin(angle) * dist + 'px');
                f.style.animationDelay = (Math.random() * 0.18) + 's';
                f.style.width = f.style.height = (3 + Math.random() * 8) + 'px';
                debrisHost.appendChild(f);
            }
        }
        rebuildDebris();

        // ---------- Recent crash history (top chip rail) ----------
        const HISTORY_MAX = 12;
        const $history = document.getElementById('history');
        const history = @json($recentCrashes ?? []);

        function tierClassFor(cp) {
            if (cp >= 10) return 'tier-epic';
            if (cp >= 3)  return 'tier-high';
            if (cp >= 1.5) return 'tier-mid';
            return 'tier-low';
        }
        function renderHistory(highlightFirst = false) {
            $history.innerHTML = history.map((cp, i) =>
                `<span class="history-chip ${tierClassFor(cp)}${highlightFirst && i === 0 ? ' is-new' : ''}">${cp.toFixed(2)}x</span>`
            ).join('');
        }
        function pushHistory(cp) {
            history.unshift(cp);
            if (history.length > HISTORY_MAX) history.length = HISTORY_MAX;
            renderHistory(true);
        }
        renderHistory();

        const log = (msg) => {
            const line = document.createElement('div');
            line.textContent = `${new Date().toLocaleTimeString()}  ${msg}`;
            $log.prepend(line);
            while ($log.children.length > 6) $log.lastChild.remove();
        };

        function setStage(state) {
            $stage.classList.remove('waiting', 'betting', 'running', 'deep', 'abyss', 'critical', 'crashed');
            for (const s of state) $stage.classList.add(s);
        }

        function updateMultiplierUI(m) {
            $mult.textContent = m.toFixed(2) + 'x';
            // scale 1.00x → 200m / 0%, 25.00x → 1500m / red zone
            const norm = Math.min(1, Math.max(0, (m - 1) / 24));
            const pressure = Math.round(200 + norm * 1300);
            const hull = Math.max(0, Math.round(100 - norm * 100));
            $pressVal.textContent = pressure;
            $hullVal.textContent = hull;
            $pressArc.setAttribute('stroke-dashoffset', String(100 - norm * 100));
            $pressArc.setAttribute('stroke', m >= 8 ? 'var(--warn)' : (m >= 3 ? 'var(--gold)' : 'var(--hud)'));
            $hullFill.style.width = hull + '%';

            const tiers = ['running'];
            if (m >= 8)  tiers.push('critical');
            else if (m >= 3.5) tiers.push('abyss');
            else if (m >= 1.8) tiers.push('deep');
            setStage(tiers);
        }

        // ---------- Countdown helper (state text only) ----------
        let countdownTimer = null;
        function cancelCountdown() {
            if (countdownTimer) { clearInterval(countdownTimer); countdownTimer = null; }
        }
        function startCountdown(seconds, label, onZero) {
            cancelCountdown();
            let remaining = Math.max(0, Math.floor(seconds));
            const render = () => {
                if (remaining <= 0) {
                    cancelCountdown();
                    if (onZero) onZero();
                    return;
                }
                $state.textContent = `${label} ${remaining}s`;
                remaining--;
            };
            render();
            countdownTimer = setInterval(render, 1000);
        }

        function resetIdleVisuals() {
            $mult.textContent = '1.00x';
            $pressVal.textContent = '0';
            $hullVal.textContent = '100';
            $pressArc.setAttribute('stroke-dashoffset', '100');
            $pressArc.setAttribute('stroke', 'var(--hud)');
            $hullFill.style.width = '100%';
        }

        function attachEcho() {
            log('Echo ready, joining channel: crash');
            window.Echo.channel('crash')
                .listen('.round.betting_opened', (e) => {
                    cancelCountdown();
                    setStage(['betting']);
                    resetIdleVisuals();
                    $cashout.disabled = true;
                    $cashoutHint.textContent = 'awaiting dive';
                    log(`betting_opened ${e.round_uuid.slice(0,8)}…`);
                    startCountdown(e.betting_seconds || 10, 'Diving in', () => {
                        $state.textContent = 'Pressurising…';
                    });
                })
                .listen('.round.started', (e) => {
                    cancelCountdown();
                    setStage(['running']);
                    $state.textContent = 'DIVING';
                    $cashout.disabled = false;
                    $cashoutHint.textContent = 'cash out at current x';
                    log(`dive started · growth ${e.growth_rate}/s`);
                })
                .listen('.round.tick', (e) => {
                    if ($stage.classList.contains('crashed')) return;
                    const m = Number(e.multiplier ?? e.data?.multiplier ?? NaN);
                    if (!Number.isFinite(m)) return;
                    updateMultiplierUI(m);
                    // Tick implies running. If user opened the page mid-round and
                    // missed betting_opened/started, sync state text + cashout button.
                    const elapsed = Number(e.elapsed_seconds ?? 0).toFixed(1);
                    $state.textContent = `DIVING · ${elapsed}s`;
                    if ($cashout.disabled) {
                        $cashout.disabled = false;
                        $cashoutHint.textContent = 'cash out at current x';
                    }
                })
                .listen('.round.crashed', (e) => {
                    try {
                        log('💥 raw: ' + JSON.stringify(e));
                        const cp = Number(e.crash_point ?? e.crashPoint ?? e.data?.crash_point ?? NaN);
                        rebuildDebris();
                        setStage(['crashed', 'critical']);
                        if (Number.isFinite(cp)) {
                            $mult.textContent = cp.toFixed(2) + 'x';
                            $state.textContent = `IMPLODED @ ${cp.toFixed(2)}x`;
                            log(`💥 imploded @ ${cp.toFixed(2)}x`);
                            pushHistory(cp);
                        } else {
                            $state.textContent = 'IMPLODED (no crash_point)';
                            log('⚠ crash_point missing in payload');
                        }
                        $cashout.disabled = true;
                        $cashoutHint.textContent = 'round over';
                    } catch (err) {
                        log('⚠ crashed handler threw: ' + err.message);
                    }
                })
                .listen('.round.settled', (e) => {
                    log(`settled · seed ${e.server_seed?.slice(0,12)}…`);
                    // Hold the IMPLODED splash for ~1.2s so the player can read
                    // the crash point, then snap back to a neutral idle and
                    // start a visible countdown until the next betting window.
                    setTimeout(() => {
                        if ($stage.classList.contains('betting') ||
                            $stage.classList.contains('running')) return;
                        setStage(['waiting']);
                        resetIdleVisuals();
                        const wait = Number(e.next_round_in_seconds ?? 5);
                        startCountdown(wait, 'Next dive in', () => {
                            $state.textContent = 'Awaiting next dive…';
                        });
                    }, 1200);
                });
        }

        if (window.Echo) attachEcho();
        else window.addEventListener('DOMContentLoaded', () => setTimeout(attachEcho, 250));

        $cashout.addEventListener('click', () => {
            $cashout.disabled = true;
            $cashoutHint.textContent = 'visual demo only — use crash:bet CLI';
            log('🎯 cash-out tapped (visual demo)');
        });
    </script>
</body>
</html>
