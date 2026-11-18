/* ---------- Lucky Duck's HUD ----------
           Gold accents from the tokens; the numbers in a chunky rounded face; captions over the
           bright sky get a dark outline so they read on it. */
        /* The readout is a gradient clipped to the letters: a text-shadow would paint over it,
           so the outline is a stroke and the depth a drop-shadow of the rendered letters. */
        :root[data-theme="luckyduck"] .multiplier {
            font-family: "Arial Rounded MT Bold", "Trebuchet MS", "Segoe UI", system-ui, sans-serif; font-weight: 800;
            letter-spacing: 0;
            -webkit-text-stroke: 2px #14532d;
            text-shadow: none !important;
            filter: drop-shadow(0 4px 0 #14532d) drop-shadow(0 0 16px rgba(20, 36, 15, 0.45));
        }
        :root[data-theme="luckyduck"] :is(.history-label, .state) {
            text-shadow: 0 0 2px #14240f, 0 0 3px #14240f, 0 1px 4px #14240f;
        }
        :root[data-theme="luckyduck"] .cashout {
            box-shadow: 0 0 0 3px #14240f, 0 0 0 6px #fcd34d, 0 0 30px rgba(252, 211, 77, 0.55);
        }
        /* At ≥8x a warm pulse, not the red alarm strobe. */
        :root[data-theme="luckyduck"] .strobe { background: radial-gradient(ellipse at 50% 40%, rgba(251, 146, 60, 0.28) 0%, transparent 62%); }
