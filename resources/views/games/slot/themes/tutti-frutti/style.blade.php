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
        @media (orientation: portrait) {
            .branch { width: 38vw; bottom: -2vh; }
        }
