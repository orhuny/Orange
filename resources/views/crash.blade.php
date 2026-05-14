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

        /* ---------- Stage / depth gradient ---------- */
        .stage {
            position: fixed; inset: 0;
            background:
              radial-gradient(ellipse at 50% -10%, rgba(255,255,255,0.18) 0%, transparent 35%),
              linear-gradient(180deg,
                  var(--shallow) 0%,
                  #0c4a6e   18%,
                  var(--depth-1) 45%,
                  var(--depth-2) 75%,
                  var(--abyss) 100%);
            transition: filter 1s ease, background 2s ease;
        }
        .stage.deep   { filter: brightness(0.55) saturate(1.1); }
        .stage.abyss  { filter: brightness(0.25) saturate(0.7) contrast(1.2); }
        .stage.critical { filter: brightness(0.2) saturate(0.6) contrast(1.4) hue-rotate(-20deg); }

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
            width: 180px; height: 90px;
            transition: top 12s linear, transform 0.15s ease;
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

        .sub-body {
            width: 100%; height: 100%;
            background: linear-gradient(180deg, #94a3b8 0%, #475569 50%, #1e293b 100%);
            border-radius: 50% 35% 35% 50% / 50% 60% 60% 50%;
            position: relative;
            box-shadow:
              inset 0 -10px 18px rgba(0,0,0,0.5),
              inset 0 5px 10px rgba(255,255,255,0.18),
              0 0 50px rgba(0,0,0,0.5);
        }
        .porthole {
            position: absolute; top: 28px; width: 22px; height: 22px;
            border-radius: 50%;
            background: radial-gradient(circle at 35% 35%, #fbbf24 0%, #b45309 70%, #1e293b 100%);
            box-shadow: 0 0 12px rgba(251,191,36,0.7), inset 0 0 4px #000;
            border: 2px solid #334155;
        }
        .p1 { left: 50px; }
        .p2 { left: 90px; }
        .fin {
            position: absolute; right: -22px; top: 25px;
            width: 30px; height: 38px;
            background: linear-gradient(135deg, #475569, #1e293b);
            clip-path: polygon(0 50%, 100% 0%, 100% 100%);
        }
        .lights {
            position: absolute; left: -18px; top: 38px;
            width: 0; height: 0;
            border-style: solid;
            border-width: 28px 30px 28px 0;
            border-color: transparent rgba(255,255,255,0.18) transparent transparent;
            filter: blur(6px);
        }
        .running .lights { border-color: transparent rgba(255,243,184,0.35) transparent transparent; }

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

        /* ---------- Multiplier ---------- */
        .center {
            position: absolute; inset: 0;
            display: flex; flex-direction: column;
            justify-content: center; align-items: center;
            pointer-events: none;
            z-index: 8;
        }
        .multiplier {
            font-family: "SF Mono", ui-monospace, monospace;
            font-size: clamp(96px, 18vw, 220px);
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
            margin-top: 10px;
            font-size: 11px; letter-spacing: 0.3em;
            color: var(--hud); opacity: 0.7;
            text-transform: uppercase;
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

        /* ---------- Implode ---------- */
        .shockwave {
            position: absolute; left: 50%; top: 50%;
            width: 4px; height: 4px;
            border-radius: 50%; background: white;
            transform: translate(-50%, -50%) scale(0);
            box-shadow: 0 0 80px white;
            opacity: 0; pointer-events: none;
            z-index: 50;
        }
        .crashed .shockwave {
            animation: implode 1s cubic-bezier(0.5, 0, 0.7, 1) forwards;
        }
        @keyframes implode {
            0%   { transform: translate(-50%, -50%) scale(0); opacity: 0; }
            10%  { transform: translate(-50%, -50%) scale(0.4); opacity: 1; }
            70%  { transform: translate(-50%, -50%) scale(60); opacity: 0.6; background: rgba(255,255,255,0.1); }
            100% { transform: translate(-50%, -50%) scale(120); opacity: 0; }
        }
        .crashed .stage { animation: glitch 0.4s linear; }
        @keyframes glitch {
            0%   { transform: translate(0); }
            20%  { transform: translate(-3px, 2px); }
            40%  { transform: translate(2px, -3px); filter: hue-rotate(40deg); }
            60%  { transform: translate(-2px, 1px); filter: hue-rotate(-30deg); }
            100% { transform: translate(0); }
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
        <div class="rays"></div>
        <div class="strobe"></div>

        <div id="particles" class="particles"></div>

        <div class="jelly" style="left:12%; top:50%; animation-delay:-2s;"></div>
        <div class="jelly" style="left:80%; top:35%; animation-delay:-5s;"></div>
        <div class="jelly" style="left:25%; top:70%; animation-delay:-7s;"></div>

        <div class="sub-wrap">
            <div class="sub-body">
                <div class="porthole p1"></div>
                <div class="porthole p2"></div>
                <div class="fin"></div>
                <div class="lights"></div>
            </div>
        </div>

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

        <!-- Center multiplier -->
        <div class="center">
            <div id="multiplier" class="multiplier">1.00x</div>
            <div id="state" class="state">Awaiting next dive…</div>
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

        <!-- Implode shockwave layer -->
        <div class="shockwave"></div>

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

        // particles
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

        function attachEcho() {
            log('Echo ready, joining channel: crash');
            window.Echo.channel('crash')
                .listen('.round.betting_opened', (e) => {
                    setStage(['betting']);
                    $mult.textContent = '1.00x';
                    $pressVal.textContent = '0'; $hullVal.textContent = '100';
                    $pressArc.setAttribute('stroke-dashoffset', '100');
                    $hullFill.style.width = '100%';
                    $state.textContent = `Betting open · ${e.betting_seconds}s window`;
                    $cashout.disabled = true;
                    $cashoutHint.textContent = 'awaiting dive';
                    log(`betting_opened ${e.round_uuid.slice(0,8)}…`);
                })
                .listen('.round.started', (e) => {
                    setStage(['running']);
                    $state.textContent = 'DIVING';
                    $cashout.disabled = false;
                    $cashoutHint.textContent = 'cash out at current x';
                    log(`dive started · growth ${e.growth_rate}/s`);
                })
                .listen('.round.tick', (e) => {
                    if ($stage.classList.contains('crashed')) return;
                    updateMultiplierUI(e.multiplier);
                })
                .listen('.round.crashed', (e) => {
                    setStage(['crashed', 'critical']);
                    $mult.textContent = e.crash_point.toFixed(2) + 'x';
                    $state.textContent = `IMPLODED @ ${e.crash_point.toFixed(2)}x`;
                    $cashout.disabled = true;
                    $cashoutHint.textContent = 'round over';
                    log(`💥 imploded @ ${e.crash_point.toFixed(2)}x`);
                })
                .listen('.round.settled', (e) => {
                    log(`settled · seed ${e.server_seed?.slice(0,12)}…`);
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
