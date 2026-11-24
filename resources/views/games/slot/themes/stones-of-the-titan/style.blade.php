{{-- Stones of the Titan's colours, on top of the shared slot page's styles and the throne
     layout (slot.blade.php includes this inside its <style>). The shared rules are Sugar
     Orchard's wood and candy; these turn them into obsidian, gold and nebula light. The kit's
     own layers (slot-kit-cosmos.js: the nebula, asteroids, shooting stars, the Eclipse, the
     eclipse intro, the sweep) are styled here too. --}}
        :root {
            --cream: #fbf3e2; --ink: #1a1024; --wood: #241a34; --wood-hi: #c8963c; --orange: #ffb23e; --orange-hi: #ffe39a;
            --leaf: #2fb59a; --gold: #ffd76a; --panel: rgba(10, 6, 18, .85);
            --card-buy: url('{{ $theme['cards']['buy'] ?? '' }}');
            --card-ante: url('{{ $theme['cards']['ante'] ?? '' }}');
            --card-super: url('{{ $theme['cards']['buy'] ?? '' }}');
            --tumble-art: url('{{ $theme['tumblePlaque'] ?? '' }}'); --tumble-ar: {{ $theme['tumbleRatio'] ?? 4.2 }};
        }
        html, body { background: #0b0614; }
        .bg::after { background: radial-gradient(ellipse at center, transparent 46%, rgba(6, 2, 14, .55)); }
        .lantern, #fireflies { display: none; }

        /* Crystal outcrops in the lower corners (the shared "branch" slots): they glow brighter in the Eclipse. */
        .branch { bottom: -3vh; width: clamp(150px, 17vw, 330px); }
        .branch.left { left: -1vw; }
        .branch.right { right: -1vw; }
        .branch img { transform-origin: 30% 100%; animation: crystal-glow 5.5s ease-in-out infinite alternate; filter: drop-shadow(0 10px 18px rgba(0, 0, 0, .5)); }
        body.free .branch img { filter: brightness(.6) saturate(1.3) drop-shadow(0 0 18px rgba(255, 120, 70, .55)); }
        @media (orientation: portrait) { .branch { top: auto; bottom: -2vh; width: clamp(110px, 30vw, 240px); } }
        @keyframes crystal-glow { from { filter: drop-shadow(0 10px 18px rgba(0, 0, 0, .5)) brightness(.95); }
                                  to { filter: drop-shadow(0 0 16px rgba(120, 230, 240, .45)) brightness(1.08); } }

        .logo { filter: drop-shadow(0 6px 12px rgba(0, 0, 0, .65)); }

        /* The Titan stands out from the sky, a rim-lit figure cut out against it:
           - on him: more contrast, light and colour;
           - around his silhouette, from the inside out: a bright double gold rim light (ember in
             the Eclipse), then a dark halo hugging it that separates him from the bright nebula,
             and a deep shadow under him. Static: an animated filter would repaint the whole
             figure every frame;
           - behind him: the sky darkened (the layout's shadow, deeper here), and a small warm
             glow behind his head and shoulders that breathes slowly (compositor-only). */
        /* As specific as the layout's own .throne .host-body .pose, and after it, so this wins. */
        .throne .host-body .pose, .host-peek .host-body .pose { animation: none;
                           filter: contrast(1.28) brightness(1.18) saturate(1.22) drop-shadow(0 0 1.5px rgba(255, 246, 215, 1)) drop-shadow(0 0 3px rgba(255, 205, 110, .95))
                                   drop-shadow(0 0 22px rgba(4, 2, 10, .9)) drop-shadow(0 16px 20px rgba(0, 0, 0, .6)); }
        body.free .throne .host-body .pose, body.free .host-peek .host-body .pose {
                           filter: contrast(1.28) brightness(1.18) saturate(1.22) drop-shadow(0 0 1.5px rgba(255, 225, 190, 1)) drop-shadow(0 0 3px rgba(255, 140, 80, .95))
                                   drop-shadow(0 0 22px rgba(4, 2, 10, .9)) drop-shadow(0 16px 20px rgba(0, 0, 0, .6)); }
        .throne .host::before { background: radial-gradient(closest-side, rgba(6, 3, 14, .7), rgba(6, 3, 14, .45) 58%, rgba(6, 3, 14, 0)); }
        .throne .host::after { content: ''; position: absolute; z-index: 0; pointer-events: none; border-radius: 50%; mix-blend-mode: screen;
                               left: calc(var(--bh) * var(--hk) * .405); top: 4%; width: calc(var(--bh) * var(--hk) * .46); height: 34%; translate: -50% 0;
                               background: radial-gradient(closest-side, rgba(255, 196, 100, .42), rgba(255, 150, 60, .14) 55%, rgba(255, 150, 60, 0));
                               animation: titan-aura 4.8s ease-in-out infinite; }
        body.free .throne .host::after { background: radial-gradient(closest-side, rgba(255, 120, 70, .45), rgba(200, 40, 40, .15) 55%, rgba(200, 40, 40, 0)); }
        .layout-throne .host-peek::before { content: ''; position: absolute; z-index: 0; pointer-events: none; border-radius: 50%;
                                            left: 50%; top: 2%; width: 110%; height: 120%; translate: -44% 0;
                                            background: radial-gradient(closest-side, rgba(8, 4, 16, .72), rgba(8, 4, 16, .5) 55%, rgba(8, 4, 16, 0)); }
        @keyframes titan-aura { 0%, 100% { opacity: .6; scale: .96; } 50% { opacity: 1; scale: 1.04; } }
        .throne .pedestal { filter: drop-shadow(0 18px 24px rgba(0, 0, 0, .55)); }

        /* The feature cards' words: gold on obsidian. */
        .buy-title { color: #fff6dc; text-shadow: 0 2px 0 #120a1c, 0 0 10px rgba(255, 200, 100, .35); }
        .buy-price { color: #ffe9a6; background: rgba(8, 4, 14, .72); box-shadow: inset 0 1px 2px rgba(0, 0, 0, .6), 0 0 0 1px rgba(232, 182, 80, .4); }
        .buy-icon { filter: drop-shadow(0 4px 6px rgba(0, 0, 0, .6)) drop-shadow(0 0 10px rgba(255, 210, 120, .35)); }
        .super-buy .buy-icon { filter: drop-shadow(0 4px 6px rgba(0, 0, 0, .6)) drop-shadow(0 0 14px rgba(255, 120, 70, .7)); }
        .super-buy .buy-title { color: #ffd2a8; }
        .layout-throne .side > .feature.super-buy { filter: drop-shadow(0 12px 18px rgba(0, 0, 0, .5)) hue-rotate(-28deg) saturate(1.25); }
        .layout-throne .side > .feature.super-buy .buy-icon, .layout-throne .side > .feature.super-buy .buy-text { filter: hue-rotate(28deg); }
        .feature-note { color: #f3dcb4; }
        .feature-title { color: var(--gold); text-shadow: 0 2px 0 #120a1c; }
        .ante-desc { color: #e9e0f4; }
        .toggle { border-color: rgba(232, 182, 80, .45); background: linear-gradient(180deg, #07040c, #1c1328); }
        .toggle i { background: radial-gradient(circle at 35% 30%, #fff8e2, #e6b65c 60%, #8a5a18); }
        .toggle[aria-pressed=true] { background: linear-gradient(180deg, #1aa59a, #45e6d8); border-color: rgba(200, 255, 250, .6);
                                     box-shadow: inset 0 2px 4px rgba(0, 40, 40, .35), 0 0 12px rgba(70, 230, 220, .55); }

        /* The grid: a pane of smoked obsidian glass with faint column lines, ember-dark in the Eclipse. */
        .board { background: linear-gradient(90deg, transparent calc(100% - 1px), rgba(255, 220, 150, .09) 0) 0 0 / calc(100% / 6) 100% repeat-x,
                             radial-gradient(ellipse at 50% 30%, rgba(52, 36, 78, .58), rgba(10, 6, 20, .8));
                 box-shadow: inset 0 0 0 1px rgba(255, 214, 140, .16), inset 0 14px 36px rgba(0, 0, 0, .5); }
        body.free .board { background: linear-gradient(90deg, transparent calc(100% - 1px), rgba(255, 150, 110, .1) 0) 0 0 / calc(100% / 6) 100% repeat-x,
                                       radial-gradient(ellipse at 50% 30%, rgba(80, 18, 24, .58), rgba(12, 2, 8, .84));
                           box-shadow: inset 0 0 0 1px rgba(255, 140, 100, .2), inset 0 14px 36px rgba(0, 0, 0, .55); }
        .sym > img, .sym .star, .sym .basket { filter: drop-shadow(0 5px 5px rgba(0, 0, 0, .55)); }
        .sym.win > img, .sym.win .star, .sym.win .basket { filter: drop-shadow(0 0 10px #fff1c4) drop-shadow(0 0 22px rgba(255, 190, 80, .85)); }

        /* SCATTER across the sigil's plain lower edge: obsidian outline, white-gold fill. */
        .sym .basket b { top: 85%; rotate: 0deg; font-size: 14.5cqmin; color: #140a1e; -webkit-text-stroke: .22em #140a1e;
                         filter: drop-shadow(0 .06em 0 #06030a) drop-shadow(0 0 .25em rgba(255, 200, 110, .8)); }
        .sym .basket b::after { background: linear-gradient(#ffffff 10%, #fff1bf 38%, #ffd05a 64%, #f08a1e 92%); -webkit-background-clip: text; background-clip: text; }

        /* A meteor's value, on its glowing face. */
        .sym .star b { margin-top: 4%; font-size: 25cqmin; -webkit-text-stroke: .15em #1a0a10; text-shadow: 0 .06em 0 #0a0408, 0 0 .3em rgba(255, 220, 160, .6); }
        .sym .star[data-tier=sun] b, .sym .star[data-tier=ember] b { color: #fff6d8; }

        .float { background: rgba(14, 8, 24, .86); border-color: var(--gold); }
        .float.mult { background: linear-gradient(180deg, rgba(160, 60, 30, .94), rgba(70, 16, 16, .94)); border-color: #ffe1a6; }
        .float.scatter-win { background: rgba(40, 24, 64, .88); }
        .fs-hud { background: linear-gradient(180deg, #3a1630, #170814); border-color: #ffcf7a; }

        /* The bar's figures: gold labels, white figures, a golden WIN. */
        .layout-throne .bar .meter small { color: var(--gold); text-shadow: 0 2px 0 #0a0510; }
        .layout-throne .bar .meter span, .layout-throne .bar .meter > b { text-shadow: 0 2px 0 rgba(6, 2, 12, .8); }
        .winm #win { background: linear-gradient(180deg, #fffbe6, #ffe07a 45%, #ffad2e); -webkit-background-clip: text; background-clip: text;
                     filter: drop-shadow(0 2px 0 #170a04) drop-shadow(0 0 12px rgba(255, 190, 80, .35)); }
        .round, .tool { border-color: rgba(255, 220, 150, .85); background: radial-gradient(circle at 35% 28%, #4a3a66, #1c1328 58%, #07040c);
                        box-shadow: 0 5px 12px rgba(0, 0, 0, .55), inset 0 2px 0 rgba(255, 236, 200, .25), inset 0 -3px 0 rgba(0, 0, 0, .4); }
        .tool[aria-pressed=true] { background: radial-gradient(circle at 35% 28%, #bffcf4, #36cfc2 58%, #0b6f6a); border-color: #e4fffb;
                                   box-shadow: 0 5px 12px rgba(0, 0, 0, .5), 0 0 14px rgba(70, 230, 220, .55), inset 0 2px 0 rgba(255, 255, 255, .4); }
        .tool .count { background: linear-gradient(180deg, #ffd76a, #e09a1e); border-color: #fff3c4; color: #3a2400; }
        @keyframes breathe { 50% { scale: 1.035; filter: drop-shadow(0 8px 12px rgba(0, 0, 0, .55)) drop-shadow(0 0 18px rgba(255, 200, 100, .6)); } }

        .bet-list { background: linear-gradient(180deg, rgba(36, 24, 54, .97), rgba(10, 6, 18, .98)) padding-box, linear-gradient(180deg, #ffe9a8, #b8862a) border-box; }
        .bet-list::after { background: rgb(10, 6, 18); border-color: #b8862a; }
        .bet-list.below::after { background: rgb(36, 24, 54); border-color: #ffe9a8; }
        .bet-chips button { border-color: rgba(255, 220, 150, .26); background: rgba(255, 236, 200, .07); text-shadow: 0 1px 0 rgba(0, 0, 0, .6); }
        .bet-chips button:hover { background: rgba(255, 236, 200, .18); }
        .bet-chips button[aria-pressed=true] { background: linear-gradient(180deg, #fff3c4, #ffd76a 55%, #d99a24); border-color: #fff8dc; color: #2a1a00; }

        /* Dialogs: starlit vellum pages with a gold rim; the feature plaques keep their art. */
        .overlay { background: rgba(6, 2, 14, .58); }
        .overlay.dim { background: rgba(4, 0, 10, .72); }
        .dialog { background: linear-gradient(180deg, #fffaf0, #f1e6d2); border-color: var(--wood-hi); }
        .dialog .kicker, .dialog h2, .dialog h3, table.pays th { color: #4a2a5a; }
        .dialog .huge { color: #e0962a; -webkit-text-stroke: 3px #2a1206; }
        .dialog.plaque { color: #f6ecff; }
        .dialog.plaque .huge { color: var(--gold); -webkit-text-stroke: 3px #120a1c; text-shadow: 0 4px 0 #120a1c, 0 0 26px rgba(255, 190, 80, .5); }
        .dialog.plaque .note, .dialog.plaque .kicker { color: #eadcff; }
        .dialog.plaque .note strong { color: #ffd58a; }
        .dialog.celebrate .huge { -webkit-text-stroke: 4px #120a1c; text-shadow: 0 6px 0 #120a1c, 0 0 34px rgba(255, 190, 80, .7); }
        .primary { background: linear-gradient(180deg, #ffcf5a, #e0861e); text-shadow: 0 1px 0 #6a3400; }
        .ghost { background: #e8dcc6; }
        .choice { background: #6a3c8a; }
        table.pays td { border-top-color: rgba(70, 40, 90, .15); }
        .hist-head, .h-time, .h-win small, .hrow dt { color: #6a4a7a; }
        .hrow { border-color: rgba(70, 40, 90, .16); }
        .hrow[open] { background: #fffaf2; }
        .tag { background: #eadcf4; color: #4a2a5a; }
        .toast { background: rgba(60, 14, 30, .94); }
        .intro { background: #0b0614; }

        /* ---- The kit's layers (slot-kit-cosmos.js) ---- */
        .cosmos-nebula { position: fixed; inset: 0; z-index: 0; pointer-events: none; mix-blend-mode: screen; opacity: .62; }
        .cosmos-rock, .cosmos-streak, .cosmos-dust, .cosmos-fx, .cosmos-meteor, .cosmos-sweep, .cosmos-rain { position: fixed; left: 0; top: 0; pointer-events: none; will-change: transform, opacity; }
        .cosmos-rock { position: absolute; }
        .cosmos-streak { position: absolute; z-index: 0; mix-blend-mode: screen; }
        .cosmos-dust { z-index: 3; mix-blend-mode: screen; }
        .cosmos-fx { z-index: 21; mix-blend-mode: screen; }
        .cosmos-meteor { z-index: 22; }
        .cosmos-meteor .sym { position: static; width: 100%; height: 100%; container-type: size; display: grid; place-items: center; }
        .sym.spent .star { opacity: .2; transition: opacity .2s; }
        .cosmos-sweep { z-index: 21; height: 100vh; width: auto; mix-blend-mode: screen; }
        .cosmos-rain { mix-blend-mode: screen; }
        /* Cracks of light race across a winner before it shatters. */
        .sym .cosmos-crack { position: absolute; inset: -6%; width: 112%; height: 112%; pointer-events: none; mix-blend-mode: screen; opacity: 0; z-index: 2; }

        /* The eclipse at the free-spins intro. */
        .cosmos-veil { position: fixed; inset: 0; z-index: 30; pointer-events: none; display: grid; place-items: center;
                       background: radial-gradient(circle, rgba(30, 8, 20, .4), rgba(4, 0, 8, .96) 70%); }
        .cosmos-veil > * { grid-area: 1 / 1; border-radius: 50%; }
        .cosmos-sun { background: radial-gradient(circle, #ffffff 0%, #fff4c8 40%, #ffc24a 62%, rgba(255, 150, 40, 0) 72%); box-shadow: 0 0 120px 40px rgba(255, 190, 80, .55); }
        .cosmos-moon { background: radial-gradient(circle at 40% 35%, #1a1420, #040206 70%); box-shadow: inset -8px -10px 30px rgba(0, 0, 0, .8); }
        .cosmos-corona { mix-blend-mode: screen; border-radius: 0; }

        /* The Eclipse: the free spins' running multiplier, a black sun in a gold ring. Landscape: in
           the left column, where the feature cards were; portrait: over the frame's top-left corner. */
        .eclipse { position: absolute; z-index: 8; left: calc(-1 * (var(--bh) * var(--el) + var(--gap) + var(--side) / 2)); top: 50%;
                   width: min(var(--side), calc(var(--bh) * .5)); aspect-ratio: 1; translate: -50% -50%;
                   display: grid; place-items: center; pointer-events: none; opacity: 0; scale: .6;
                   transition: opacity .6s ease, scale .7s cubic-bezier(.2, 1.5, .4, 1); }
        body.free .eclipse { opacity: 1; scale: 1; }
        .eclipse .ring { position: absolute; inset: 0; width: 100%; height: 100%; z-index: 3; filter: drop-shadow(0 8px 12px rgba(0, 0, 0, .6)); }
        .eclipse .sun { position: absolute; inset: 17%; z-index: 2; border-radius: 50%; background: radial-gradient(circle at 45% 40%, #1c1424, #030105 68%);
                        box-shadow: 0 0 0 2px rgba(255, 236, 190, .9), 0 0 18px 6px rgba(255, 200, 100, var(--halo, .4)); }
        .eclipse .corona { position: absolute; inset: -32%; width: 164%; height: 164%; z-index: 1; mix-blend-mode: screen; animation: rot 24s linear infinite;
                           opacity: var(--glow, .6); scale: var(--grow, .85); transition: opacity .8s ease, scale .8s ease; }
        .eclipse b { position: relative; z-index: 4; font: 900 calc(min(var(--side), var(--bh) * .5) * .2)/1 var(--round); color: #fff; -webkit-text-stroke: .12em #1a0a06;
                     paint-order: stroke fill; text-shadow: 0 0 .4em rgba(255, 200, 110, .9); font-variant-numeric: tabular-nums; }
        .eclipse small { position: absolute; z-index: 4; bottom: 24%; font: 800 calc(min(var(--side), var(--bh) * .5) * .065)/1 var(--num); letter-spacing: .22em; color: var(--gold);
                         text-shadow: 0 1px 0 #000; }
        .eclipse.empty b { opacity: .45; }
        .eclipse.bump b { animation: eclipse-bump .6s cubic-bezier(.2, 1.6, .4, 1); }
        @keyframes eclipse-bump { 0% { scale: 1; } 30% { scale: 1.5; } 100% { scale: 1; } }
        .eclipse[data-level="1"] { --glow: .75; --grow: .95; --halo: .5; }
        .eclipse[data-level="2"] { --glow: .95; --grow: 1.1; --halo: .7; }
        .eclipse[data-level="2"] .corona { filter: hue-rotate(-25deg) saturate(1.5); }
        .eclipse[data-level="3"] { --glow: 1; --grow: 1.28; --halo: .95; }
        .eclipse[data-level="3"] .corona { filter: brightness(1.4) saturate(.7); }
        @media (orientation: portrait) {
            .eclipse { --side: calc(var(--bw) * .34); --bh: calc(var(--bw) * 5 / 6); left: 4%; top: calc(var(--bw) * -.16); translate: 0 -50%; width: calc(var(--bw) * .26); }
        }
        @media (prefers-reduced-motion: reduce) {
            .branch img, .host-body .pose, .eclipse .corona, .throne .host::after { animation: none; }
            .cosmos-nebula { display: none; }
        }
