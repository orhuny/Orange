/* ---------- Sonar ---------- */
        .sonar {
            position: absolute; bottom: 110px; left: 50%;
            transform: translateX(-50%);
            width: 220px; height: 4px;
            background: linear-gradient(90deg, transparent 0%, rgba(var(--hud-rgb),0.45) 50%, transparent 100%);
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

