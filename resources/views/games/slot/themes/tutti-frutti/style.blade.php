{{-- Tutti Frutti's own touches (themes/tutti-frutti/theme.php), after the duet layout. --}}
        html, body { background: #0b5a7d; }
        body { --buy-art: url('{{ $theme['buyArt'] ?? '' }}'); }
        /* Bright noon: only a whisper of vignette, so the sea stays crisp. */
        .bg::after { background: radial-gradient(ellipse at center, transparent 62%, rgba(0, 40, 70, .22)); }
        body.free .bg::after { background: radial-gradient(ellipse at center, transparent 48%, rgba(20, 0, 46, .42)); }
        /* The tropical leaves in the lower corners; under the neon they glow pink. */
        .branch { width: clamp(200px, 20vw, 430px); bottom: -5vh; }
        .branch.left { left: -3vw; }
        .branch.right { right: -3vw; }
        .branch img { filter: drop-shadow(0 12px 14px rgba(0, 40, 60, .3)); }
        body.free .branch img { filter: brightness(.55) saturate(1.25) drop-shadow(0 0 18px rgba(255, 60, 200, .4)); }
        .fs-hud { background: linear-gradient(180deg, rgba(255, 60, 170, .85), rgba(140, 20, 120, .85)); border-color: rgba(255, 200, 240, .7); }
        /* The kit's sea glitter and the night's heart fireworks, behind the stage. */
        .riviera-sky { position: fixed; inset: 0; pointer-events: none; z-index: 0; overflow: hidden; }
        .riviera-sky img { position: absolute; translate: -50% -50%; mix-blend-mode: screen; }
        .stage { z-index: 1; }
        /* ---- The girl picker (slot-kit-riviera.js) ---- */
        .girls-btn { position: absolute; z-index: 9; right: calc(var(--bh) * (var(--er) - .1)); bottom: calc(100% - var(--bh) * .07); width: calc(var(--bh) * .17);
                     aspect-ratio: 1; padding: 0; border: 0; border-radius: 50%; cursor: pointer; background: var(--girl-button) center / contain no-repeat;
                     filter: drop-shadow(0 8px 12px rgba(0, 40, 70, .4)); transition: scale .2s ease, opacity .3s ease; animation: girls-beat 2.8s ease-in-out infinite; }
        .girls-btn:hover:not(:disabled) { scale: 1.08; }
        .girls-btn:disabled { opacity: .5; cursor: default; animation: none; }
        .girls-btn.away { opacity: 0; pointer-events: none; }
        @keyframes girls-beat { 0%, 70%, 100% { scale: 1; } 78% { scale: 1.1; } 86% { scale: .97; } }
        .girls-dialog { width: min(1040px, 94vw); max-height: 92dvh; overflow: auto; padding: clamp(14px, 2.4vw, 28px); border: 3px solid #f6c64b; border-radius: 26px;
                        color: #fff; text-align: center; font: 600 14px/1.3 var(--round);
                        background: radial-gradient(ellipse at 50% 0%, rgba(255, 255, 255, .18), transparent 60%), linear-gradient(180deg, #0f8bb0, #0a4f72 60%, #083a58);
                        box-shadow: 0 0 0 6px rgba(255, 255, 255, .18), 0 30px 80px rgba(0, 0, 0, .55); }
        .girls-dialog::backdrop { background: rgba(4, 20, 36, .62); backdrop-filter: blur(3px); }
        .girls-dialog h2 { margin: 0 0 10px; font: 900 clamp(20px, 3vw, 34px)/1 var(--num); letter-spacing: .08em; color: #fff;
                           text-shadow: 0 3px 0 #b8005c, 0 0 18px rgba(255, 80, 180, .6); }
        .girl-slots { display: flex; justify-content: center; gap: 10px; margin-bottom: 14px; }
        .girl-slot { min-width: 9.5em; padding: 7px 16px; border: 2px solid rgba(255, 255, 255, .35); border-radius: 999px; background: rgba(0, 0, 0, .25); color: #fff; }
        .girl-slot small { display: block; font-size: 11px; letter-spacing: .14em; opacity: .8; }
        .girl-slot b { font-size: 17px; }
        .girl-slot.on { border-color: #fff; background: color-mix(in srgb, var(--girl) 70%, #000); box-shadow: 0 0 14px var(--girl); }
        .girl-grid { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: clamp(8px, 1.4vw, 16px); }
        .girl-card { position: relative; aspect-ratio: 1086 / 1448; padding: 0; border: 0; background: none; cursor: pointer;
                     transition: scale .18s ease, filter .18s ease; filter: drop-shadow(0 6px 10px rgba(0, 0, 0, .35)); }
        /* The frame art over her photo; the name, fruit and badge over the frame. */
        .girl-card::after { content: ''; position: absolute; z-index: 1; inset: 0; background: var(--frame) center / 100% 100% no-repeat; pointer-events: none; }
        .girl-card > :not(.girl-photo) { z-index: 2; }
        .girl-card:hover { scale: 1.04; }
        .girl-photo { position: absolute; z-index: 0; left: var(--wx0); right: var(--wx1); top: var(--wy0); bottom: var(--wy1); overflow: hidden;
                      border-radius: 50% 50% 6px 6px / 30% 30% 6px 6px;
                      background: radial-gradient(circle at 50% 35%, color-mix(in srgb, var(--girl) 55%, #fff), color-mix(in srgb, var(--girl) 70%, #072a40)); }
        .girl-photo img { width: 100%; height: 100%; object-fit: cover; object-position: 50% 15%; }
        .girl-name { position: absolute; left: 22%; right: 22%; bottom: 4.2%; font: 900 clamp(11px, 1.35vw, 17px)/1 var(--num); letter-spacing: .06em; color: #5a3000;
                     text-transform: uppercase; text-shadow: 0 1px 0 rgba(255, 255, 255, .6); }
        .girl-badge { position: absolute; left: 50%; top: 2%; translate: -50% 0; white-space: nowrap; font: 900 11px/1 var(--num); font-style: normal; letter-spacing: .08em;
                      padding: 4px 8px; border-radius: 999px; background: var(--girl); color: #fff; text-shadow: 0 1px 0 rgba(0, 0, 0, .4); opacity: 0; }
        .girl-card.picked .girl-badge { opacity: 1; }
        .girl-card.picked { filter: drop-shadow(0 0 10px var(--girl)) drop-shadow(0 6px 10px rgba(0, 0, 0, .35)); }
        .girl-card.active { scale: 1.06; filter: drop-shadow(0 0 16px var(--girl)) drop-shadow(0 0 4px #fff); }
        .girl-note { margin: 14px 0 10px; font-size: 13px; opacity: .85; }
        .girl-done { padding: 10px 34px; border: 0; border-radius: 999px; font: 900 18px/1 var(--num); letter-spacing: .06em; color: #fff;
                     background: linear-gradient(180deg, #ff5bb5, #d4007a); box-shadow: 0 4px 0 #8a004f, 0 8px 18px rgba(0, 0, 0, .35); }
        @media (orientation: portrait) {
            .branch { width: 38vw; bottom: -2vh; }
            .girls-btn { --bh: calc(var(--bw) * 3 / 5); right: 3%; bottom: auto; top: calc(100% + var(--bw) * .03); width: calc(var(--bw) * .17); }
            .girl-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        }
        @media (orientation: landscape) and (max-height: 520px) {
            .girl-grid { grid-template-columns: repeat(5, minmax(0, 1fr)); max-width: 640px; margin: 0 auto; }
            .girls-dialog h2 { font-size: 18px; margin-bottom: 6px; }
            .girl-note { margin: 8px 0 6px; }
        }
