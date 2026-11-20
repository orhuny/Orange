{{-- Trident of Poseidon's colours, on top of the shared slot page's styles (slot.blade.php
     includes this inside its <style>). The shared rules are Sugar Orchard's wood and candy;
     these turn them into bronze, gold, mother-of-pearl and deep-sea glass. The kit's own
     layers (slot-kit-tides.js: reef, light shafts, bubbles, fish, the Tide, dolphins, the
     surge) are styled here too. --}}
        :root {
            --cream: #e9fbff; --ink: #062433; --wood: #0e4654; --wood-hi: #2a9fb0; --orange: #20d8e0; --orange-hi: #9ff7ff;
            --leaf: #18a37c; --gold: #ffd76a; --panel: rgba(2, 26, 40, .82);
        }
        html, body { background: #03202b; }
        .bg::after { background: radial-gradient(ellipse at center, transparent 48%, rgba(0, 14, 26, .5)); }

        /* Kelp in the lower corners (the shared "branch" slots), swaying from its root. */
        .branch { bottom: -4vh; width: clamp(120px, 13vw, 280px); }
        .branch.left { left: 1vw; }
        .branch.right { right: 1vw; }
        .branch img { transform-origin: 50% 100%; animation: kelp 6.5s ease-in-out infinite alternate; filter: drop-shadow(0 10px 16px rgba(0, 20, 30, .45)); }
        body.free .branch img { filter: brightness(.38) saturate(.9) hue-rotate(-10deg) drop-shadow(0 0 14px rgba(40, 230, 220, .35)); }
        @keyframes kelp { from { transform: rotate(-2.6deg) skewX(1.5deg); } to { transform: rotate(2.4deg) skewX(-1.5deg); } }

        /* Jellyfish in the Abyss (the shared "lantern" slots): they drift and pulse instead of swinging. */
        .lantern { top: 6vh; width: clamp(54px, 6vw, 110px); animation: jelly 7s ease-in-out infinite alternate; filter: drop-shadow(0 0 22px rgba(80, 240, 230, .75)); }
        .lantern.l2 { top: 26vh; } .lantern.l4 { top: 30vh; }
        @keyframes jelly { 0% { transform: translateY(0) scale(1, 1); } 40% { transform: translateY(-3vh) scale(.94, 1.06); } 100% { transform: translateY(-6vh) scale(1.03, .97); } }

        /* Glowing plankton in the Abyss (the shared fireflies). */
        .fireflies i { width: 4px; height: 4px; background: #c8fbff; box-shadow: 0 0 9px 3px rgba(60, 230, 230, .7); }

        .logo { filter: drop-shadow(0 6px 10px rgba(0, 18, 30, .6)); }

        /* Side panels: deep-sea lacquer in a gold rim. */
        .feature { background: linear-gradient(180deg, rgba(6, 52, 72, .95), rgba(2, 20, 34, .96)) padding-box,
                               linear-gradient(180deg, #fff1b8, #d9a43a 42%, #6b4a12) border-box;
                   box-shadow: 0 12px 26px rgba(0, 10, 20, .5), inset 0 1px 0 rgba(170, 250, 255, .2); }
        .buy { background: linear-gradient(180deg, #3fe6ea 0%, #13a9bb 52%, #0a6d86 100%) padding-box, linear-gradient(180deg, #fff3c4, #d9a43a) border-box;
               box-shadow: inset 0 1px 0 rgba(255, 255, 255, .55), inset 0 -3px 0 rgba(0, 50, 70, .4), 0 6px 14px rgba(0, 40, 60, .5); }
        .buy-title { text-shadow: 0 1px 0 #064456, 0 0 8px rgba(0, 60, 80, .4); }
        .buy-price { color: #fff3c0; background: rgba(2, 36, 52, .65); }
        .buy-icon { filter: drop-shadow(0 3px 3px rgba(0, 30, 45, .55)); }
        .feature-note { color: #bdeff5; }
        .ante-desc { color: #d6f3f7; }
        .toggle { border-color: rgba(160, 240, 250, .35); background: linear-gradient(180deg, #021722, #063345); }
        .toggle i { background: radial-gradient(circle at 35% 30%, #ffffff, #dff6fb 55%, #8fb8c4); }
        .toggle[aria-pressed=true] { background: linear-gradient(180deg, #0c9fb0, #3ee6ec); border-color: rgba(200, 255, 255, .6);
                                     box-shadow: inset 0 2px 4px rgba(0, 40, 50, .35), 0 0 10px rgba(60, 230, 236, .5); }

        /* The grid: a pane of sea glass, darker in the Abyss. */
        .board { background: linear-gradient(90deg, transparent calc(100% - 1px), rgba(170, 245, 255, .1) 0) 0 0 / calc(100% / 6) 100% repeat-x,
                             radial-gradient(ellipse at 50% 30%, rgba(14, 104, 128, .5), rgba(2, 34, 52, .74));
                 box-shadow: inset 0 0 0 1px rgba(160, 240, 255, .2), inset 0 12px 34px rgba(0, 10, 25, .4); }
        body.free .board { background: linear-gradient(90deg, transparent calc(100% - 1px), rgba(120, 240, 240, .1) 0) 0 0 / calc(100% / 6) 100% repeat-x,
                                       radial-gradient(ellipse at 50% 30%, rgba(4, 64, 84, .55), rgba(0, 10, 22, .8));
                           box-shadow: inset 0 0 0 1px rgba(90, 240, 230, .22), inset 0 12px 34px rgba(0, 0, 10, .5); }
        .sym > img, .sym .star, .sym .basket { filter: drop-shadow(0 4px 4px rgba(0, 16, 26, .55)); }
        .sym.win > img, .sym.win .star, .sym.win .basket { filter: drop-shadow(0 0 10px #c9fbff) drop-shadow(0 0 22px rgba(60, 230, 240, .8)); }

        /* SCATTER across the medallion's calm lower edge: navy outline, pearl-to-gold fill. */
        .sym .basket b { top: 84%; rotate: 0deg; font-size: 14.5cqmin; color: #04263a; -webkit-text-stroke: .22em #04263a;
                         filter: drop-shadow(0 .06em 0 #01121c) drop-shadow(0 0 .2em rgba(120, 250, 255, .7)); }
        .sym .basket b::after { background: linear-gradient(#ffffff 12%, #c9fbff 38%, #ffe27a 64%, #f0a92a 90%); -webkit-background-clip: text; background-clip: text; }

        /* A pearl's value, on the pearl: white with a deep-sea outline. */
        .sym .star b { margin-top: 2%; font-size: 25cqmin; -webkit-text-stroke: .15em #052d42; text-shadow: 0 .06em 0 #031a27, 0 0 .3em rgba(120, 240, 255, .55); }
        .sym .star[data-tier=abyss] b, .sym .star[data-tier=gold] b { color: #fff6cc; }

        .float { background: rgba(2, 36, 54, .84); border-color: var(--gold); }
        .float.mult { background: linear-gradient(180deg, rgba(14, 150, 170, .92), rgba(4, 70, 96, .92)); border-color: #c9fbff; }
        .float.scatter-win { background: rgba(6, 80, 96, .86); }
        .fs-hud { background: linear-gradient(180deg, #0f7f96, #064258); border-color: #9ff2ff; }

        /* The bar. */
        .meter { background: linear-gradient(180deg, rgba(6, 50, 70, .92), rgba(2, 18, 30, .94)) padding-box, linear-gradient(180deg, #fff1b8, #c9922c) border-box;
                 box-shadow: 0 6px 14px rgba(0, 10, 20, .48), inset 0 1px 0 rgba(170, 250, 255, .22); }
        .meter small { text-shadow: 0 1px 0 #02202e; }
        .meter span, .meter > b { text-shadow: 0 2px 0 rgba(0, 16, 26, .6); }
        .winm { background: linear-gradient(180deg, rgba(8, 60, 82, .94), rgba(2, 20, 32, .95)) padding-box, linear-gradient(180deg, #fff3c4, #e2b04a 45%, #6b4a12) border-box; }
        .winm #win { background: linear-gradient(180deg, #ffffff, #c9fbff 40%, #ffd76a 62%, #f2a523); -webkit-background-clip: text; background-clip: text;
                     filter: drop-shadow(0 2px 0 #022433) drop-shadow(0 0 10px rgba(80, 230, 240, .35)); }
        .winm.lit { box-shadow: 0 6px 14px rgba(0, 10, 20, .48), 0 0 22px rgba(70, 230, 240, .55), inset 0 1px 0 rgba(170, 250, 255, .3); }
        .round, .tool { border-color: rgba(255, 228, 150, .85); background: radial-gradient(circle at 35% 28%, #2fb6c6, #0b5468 58%, #022634);
                        box-shadow: 0 5px 12px rgba(0, 12, 22, .5), inset 0 2px 0 rgba(200, 255, 255, .35), inset 0 -3px 0 rgba(0, 0, 0, .3); }
        .tool[aria-pressed=true] { background: radial-gradient(circle at 35% 28%, #b8fbff, #36d6e0 58%, #0b7a8c); border-color: #e4ffff;
                                   box-shadow: 0 5px 12px rgba(0, 12, 22, .5), 0 0 14px rgba(70, 230, 240, .55), inset 0 2px 0 rgba(255, 255, 255, .4); }
        .tool .count { background: linear-gradient(180deg, #ffd76a, #e09a1e); border-color: #fff3c4; color: #3a2400; }
        @keyframes breathe { 50% { scale: 1.035; filter: drop-shadow(0 8px 12px rgba(0, 0, 0, .5)) drop-shadow(0 0 16px rgba(70, 230, 240, .6)); } }

        .bet-list { background: linear-gradient(180deg, rgba(8, 60, 82, .97), rgba(2, 20, 32, .98)) padding-box, linear-gradient(180deg, #fff1b8, #c9922c) border-box; }
        .bet-list::after { background: rgb(2, 20, 32); border-color: #c9922c; }
        .bet-list.below::after { background: rgb(8, 60, 82); border-color: #fff1b8; }
        .bet-chips button { border-color: rgba(170, 245, 255, .28); background: rgba(190, 250, 255, .08); text-shadow: 0 1px 0 rgba(0, 16, 26, .6); }
        .bet-chips button:hover { background: rgba(190, 250, 255, .2); }
        .bet-chips button[aria-pressed=true] { background: linear-gradient(180deg, #fff3c4, #ffd76a 55%, #d99a24); border-color: #fff8dc; color: #2a1a00; }

        /* Dialogs: sea-foam pages with a bronze-teal rim; the feature plaques keep their art. */
        .overlay { background: rgba(0, 14, 24, .55); }
        .overlay.dim { background: rgba(0, 8, 16, .7); }
        .dialog { background: linear-gradient(180deg, #f3feff, #d4f3f6); border-color: var(--wood-hi); }
        .dialog .kicker, .dialog h2, .dialog h3, table.pays th { color: #07506a; }
        .dialog .huge { color: #12b8c8; -webkit-text-stroke: 3px #04304a; }
        .dialog.plaque { color: #e4fbff; }
        .dialog.plaque .huge { color: var(--gold); -webkit-text-stroke: 3px #022231; text-shadow: 0 4px 0 #022231, 0 0 24px rgba(80, 230, 240, .45); }
        .dialog.plaque .note, .dialog.plaque .kicker { color: #c9f4fa; }
        .dialog.celebrate .huge { -webkit-text-stroke: 4px #022231; text-shadow: 0 6px 0 #022231, 0 0 30px rgba(80, 230, 240, .65); }
        .primary { background: linear-gradient(180deg, #3fe6ea, #0e98ae); text-shadow: 0 1px 0 #04405a; }
        .ghost { background: #c8ecf0; }
        .choice { background: #0e98ae; }
        table.pays td { border-top-color: rgba(10, 80, 100, .15); }
        .hist-head, .h-time, .h-win small, .hrow dt { color: #3d7f90; }
        .hrow { border-color: rgba(10, 80, 100, .16); }
        .hrow[open] { background: #f4feff; }
        .tag { background: #cdeef3; color: #0a4d60; }
        .toast { background: rgba(6, 60, 84, .94); }
        .intro { background: #021a26; }

        /* ---- The kit's layers (slot-kit-tides.js) ---- */
        .tides-caustics { position: fixed; inset: 0; z-index: 0; pointer-events: none; mix-blend-mode: screen; opacity: .5; transition: opacity 1.4s ease; }
        body.free .tides-caustics { opacity: .16; }
        .tides-rays { position: absolute; left: -5vw; right: -5vw; top: -4vh; height: 75vh; pointer-events: none; opacity: .5; mix-blend-mode: screen;
                      background: var(--rays) center top / 100% 100% no-repeat; transform-origin: 50% 0; animation: rays 11s ease-in-out infinite alternate; transition: opacity 1.4s ease; }
        body.free .tides-rays { opacity: 0; }
        @keyframes rays { from { transform: skewX(-4deg) scaleX(1.02); opacity: .38; } to { transform: skewX(5deg) scaleX(.98); opacity: .58; } }
        .tides-reef { position: absolute; bottom: -2vh; width: clamp(200px, 24vw, 470px); pointer-events: none; filter: drop-shadow(0 10px 16px rgba(0, 20, 30, .45)); transition: filter 1.4s ease; }
        .tides-reef.left { left: -3vw; }
        .tides-reef.right { right: -3vw; transform: scaleX(-1); }
        body.free .tides-reef { filter: brightness(.36) saturate(1.1) drop-shadow(0 0 16px rgba(60, 230, 220, .4)); }
        .tides-bubble, .tides-fish, .tides-dolphin, .tides-surge, .tides-whirl, .tides-fx, .tides-pearl { position: fixed; pointer-events: none; will-change: transform, opacity; }
        .tides-fx { left: 0; top: 0; z-index: 21; mix-blend-mode: screen; }
        .tides-pearl { left: 0; top: 0; z-index: 22; }
        .tides-pearl .sym { position: static; width: 100%; height: 100%; container-type: size; display: grid; place-items: center; }
        .sym.spent .star { opacity: .25; transition: opacity .2s; }
        .tides-bubble { left: 0; top: 0; z-index: 1; }
        .tides-fish { z-index: 1; left: 0; top: 0; filter: saturate(.9) brightness(.92); }
        body.free .tides-fish { filter: brightness(.35) drop-shadow(0 0 6px rgba(80, 240, 230, .5)); }
        .tides-dolphin { z-index: 21; left: 0; top: 0; filter: drop-shadow(0 6px 10px rgba(0, 20, 30, .45)); }
        .tides-surge { z-index: 21; left: 0; top: 0; height: 100vh; width: auto; }
        .tides-whirl { z-index: 30; left: 50%; top: 50%; width: 170vmax; aspect-ratio: 1; translate: -50% -50%; mix-blend-mode: screen; }
        .tides-veil { position: fixed; inset: 0; z-index: 29; pointer-events: none; background: radial-gradient(circle, rgba(0, 40, 60, .2), rgba(0, 8, 16, .95)); }

        /* A winner caught in a small whirlpool before it bursts. */
        .sym .swirl { position: absolute; inset: -12%; width: 124%; height: 124%; pointer-events: none; mix-blend-mode: screen; opacity: 0; }

        /* The Tide: the free spins' running multiplier, in a gold and bronze ring over the frame's top-right corner. */
        .tide { position: absolute; right: calc(var(--bh) * -.05); top: calc(var(--bh) * -.13); z-index: 8; width: calc(var(--bh) * .26); aspect-ratio: 1;
                display: grid; place-items: center; pointer-events: none; opacity: 0; scale: .6; transition: opacity .5s ease, scale .6s cubic-bezier(.2, 1.5, .4, 1); }
        body.free .tide { opacity: 1; scale: 1; }
        .tide .ring { position: absolute; inset: 0; width: 100%; height: 100%; z-index: 2; filter: drop-shadow(0 6px 10px rgba(0, 16, 26, .55)); }
        .tide .pool { position: absolute; inset: 15%; width: 70%; height: 70%; border-radius: 50%; z-index: 1; mix-blend-mode: screen; animation: rot 9s linear infinite;
                      filter: hue-rotate(var(--hue, 0deg)) brightness(var(--glow, .8)); }
        .tide::before { content: ''; position: absolute; inset: 15%; border-radius: 50%; background: radial-gradient(circle, #04394f, #011a26 70%); }
        .tide b { position: relative; z-index: 3; font: 900 calc(var(--bh) * .068)/1 var(--round); color: #fff; -webkit-text-stroke: .12em #03283a; paint-order: stroke fill;
                  text-shadow: 0 0 .4em rgba(120, 240, 255, .8); font-variant-numeric: tabular-nums; }
        .tide small { position: absolute; z-index: 3; bottom: 22%; font: 800 calc(var(--bh) * .022)/1 var(--num); letter-spacing: .2em; color: var(--gold); text-shadow: 0 1px 0 #02202e; }
        .tide.empty b { opacity: .45; }
        .tide.bump b { animation: tidebump .6s cubic-bezier(.2, 1.6, .4, 1); }
        @keyframes tidebump { 0% { scale: 1; } 30% { scale: 1.45; } 100% { scale: 1; } }
        .tide[data-level="1"] { --hue: 0deg; --glow: 1; }
        .tide[data-level="2"] { --hue: -150deg; --glow: 1.15; }
        .tide[data-level="3"] { --hue: 60deg; --glow: 1.3; }
        /* Portrait: --bh is the landscape grid's height, tiny on a phone. The meter takes its own
           from the board's width (--bw, a 6:5 grid), so its ring and digits follow the board. */
        @media (orientation: portrait) {
            .tide { --bh: calc(var(--bw) * 5 / 6); right: 0; top: calc(var(--bh) * -.21); width: calc(var(--bh) * .28); }
        }
        @media (prefers-reduced-motion: reduce) {
            .tides-rays, .branch img, .lantern, .tide .pool { animation: none; }
            .tides-caustics { display: none; }
        }
