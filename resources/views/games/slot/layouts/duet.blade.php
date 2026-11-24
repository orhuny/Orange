{{-- The "duet" layout (theme.php 'layout' => 'duet'), for a paylines game, on top of the shared
     slot page's styles: slot.blade.php includes it inside its <style>, before the theme's own.

     Landscape, three columns centred together:
     - left, Marina full-length on her podium; right, Bella on hers; each column --hw wide, and
       each reaching half over the frame's side band;
     - middle, the 5 × 3 reels (--ar 5 / 3, square cells) in their frame, the logo on the frame's
       top edge, the line numbers down both side bands; the buy badge on the frame's top-left
       corner, and in free spins Amore on its top-right corner.
     A bar of plain figures runs underneath: the tools at the left, BALANCE − SPIN + BET in the
     middle, the WIN at the right.

     --bh is the reels' height. Portrait: the logo at the top, the reels below it at the full
     width, each hostess's upper body over a top corner of the frame. --}}
        body.layout-duet .stage {
            --ar: 1.6667; --gap: 0px; --reach: 0; --spin: clamp(78px, 12vh, 118px);
            --hk: 1.14; /* a hostess's height, of the reels' */
            --hw: calc(var(--bh) * var(--hk) * .46); /* her column's width */
            --barh: calc(var(--spin) + 10px);
            --topk: .2; /* room above the reels for the logo, of their height */
            --ebk: calc(var(--eb) * .4);
            --bh: min(calc((100dvh - var(--barh) - 18px) / (1 + var(--topk) + var(--ebk))),
                      calc((100vw - 24px) / (var(--ar) + (var(--el) + var(--er)) * .5 + var(--hk) * .92)), 780px);
            --group: calc(var(--bh) * (var(--ar) + (var(--el) + var(--er)) * .5 + var(--hk) * .92));
        }
        @supports (width: round(down, 10.5px, 1px)) {
            body.layout-duet .stage {
                --bh: round(down, min(calc((100dvh - var(--barh) - 18px) / (1 + var(--topk) + var(--ebk))),
                                      calc((100vw - 24px) / (var(--ar) + (var(--el) + var(--er)) * .5 + var(--hk) * .92)), 780px), 3px);
            }
            .layout-duet .board-wrap { width: round(down, calc(var(--bh) * var(--ar)), 5px); }
        }
        .layout-duet .play { display: grid; grid-template-columns: var(--hw) auto var(--hw); grid-template-areas: "left frame right";
                             align-items: stretch; justify-content: center; align-content: center; column-gap: 0; }
        .layout-duet .frame { grid-area: frame; padding-top: calc(var(--bh) * var(--topk)); padding-bottom: calc(var(--bh) * var(--ebk)); }
        .layout-duet .logo-frame { height: calc(var(--bh) * .25); bottom: calc(100% - var(--bh) * .05); }

        /* ---- The reels: square cells on bright glass, five columns ---- */
        .layout-duet .sym { width: calc(100% / 5); height: calc(100% / 3); }
        .layout-duet .board { border-radius: 4px;
                              background: linear-gradient(90deg, transparent calc(100% - 2px), rgba(255, 255, 255, .55) 0) 0 0 / calc(100% / 5) 100% repeat-x,
                                          linear-gradient(180deg, rgba(255, 255, 255, .72), rgba(214, 246, 255, .58));
                              -webkit-backdrop-filter: blur(5px) saturate(1.2); backdrop-filter: blur(5px) saturate(1.2);
                              box-shadow: inset 0 0 0 1px rgba(255, 255, 255, .6), inset 0 10px 30px rgba(0, 90, 130, .18); }
        body.layout-duet.free .board { background: linear-gradient(90deg, transparent calc(100% - 2px), rgba(255, 120, 220, .22) 0) 0 0 / calc(100% / 5) 100% repeat-x,
                                                   linear-gradient(180deg, rgba(40, 14, 86, .66), rgba(14, 6, 48, .72));
                                       box-shadow: inset 0 0 0 1px rgba(255, 140, 230, .35), inset 0 10px 30px rgba(0, 0, 0, .35); }
        .layout-duet .sym > img, .layout-duet .sym .kiss, .layout-duet .sym .basket { width: 88%; height: 88%; filter: drop-shadow(0 5px 5px rgba(0, 50, 80, .28)); }
        .layout-duet.free .sym > img { filter: drop-shadow(0 0 10px rgba(255, 120, 220, .35)) drop-shadow(0 5px 5px rgba(0, 0, 0, .4)); }
        .layout-duet .sym.win > img, .layout-duet .sym.win .kiss, .layout-duet .sym.win .basket {
            animation: juicy .36s ease-in-out infinite alternate; filter: drop-shadow(0 0 12px #fff) drop-shadow(0 0 5px rgba(255, 80, 180, .9)); }
        @keyframes juicy { from { transform: scale(1, 1); } to { transform: scale(1.12, 1.08); } }

        /* The spinning strips (slot-game.js, "Reels"): a run of symbols scrolling down, blurred by speed. */
        .reel-strip { position: absolute; top: 0; height: 100%; overflow: hidden; z-index: 1; pointer-events: none; }
        .reel-run { position: absolute; left: 0; right: 0; top: 0; display: flex; flex-direction: column; }
        .reel-run img { flex: 1 1 0; min-height: 0; width: 100%; object-fit: contain; padding: 6%; filter: blur(2px) saturate(1.1); }

        /* The kiss: its locket, the multiplier on its face and WILD across its foot. */
        .sym .kiss { position: relative; display: grid; place-items: center; }
        .sym .kiss img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: contain; }
        .sym .kiss i { position: relative; margin-top: -2%; font: 900 30cqmin/1 var(--num); font-style: normal; color: #fff;
                       -webkit-text-stroke: .12em #6a0a3e; paint-order: stroke fill; text-shadow: 0 .05em 0 #4a0028; }
        .sym .kiss b { position: absolute; left: 0; right: 0; top: 70%; text-align: center; font: 900 15cqmin/1 var(--round); letter-spacing: .06em;
                       color: #fff; -webkit-text-stroke: .2em #8a0050; paint-order: stroke fill; filter: drop-shadow(0 .05em 0 #4a0028); }
        /* The cocktail's word sits on its plain gold foot. */
        .layout-duet .sym .basket b { top: 88%; rotate: 0deg; font-size: 14cqmin; }

        /* ---- Lines: the numbers down the frame's sides, and a paying line across the reels ---- */
        .line-marks { position: absolute; top: 0; bottom: 0; z-index: 7; width: calc(var(--bh) * .05); pointer-events: none; }
        .line-marks.left { right: calc(100% + var(--bh) * var(--el) * .34); }
        .line-marks.right { left: calc(100% + var(--bh) * var(--el) * .34); }
        .line-mark { position: absolute; left: 0; width: 100%; aspect-ratio: 1; translate: 0 -50%; display: grid; place-items: center; border-radius: 50%;
                     font: 800 calc(var(--bh) * .024)/1 var(--num); font-style: normal; color: #fff; text-shadow: 0 1px 0 rgba(0, 0, 0, .5);
                     background: color-mix(in srgb, var(--line) 70%, #0b2a40); box-shadow: 0 0 0 2px rgba(255, 255, 255, .85), 0 2px 4px rgba(0, 0, 0, .35);
                     opacity: .78; transition: scale .2s ease, opacity .2s ease, box-shadow .2s ease; }
        .line-mark.on { opacity: 1; scale: 1.3; background: var(--line); box-shadow: 0 0 0 2px #fff, 0 0 14px 3px var(--line); }
        .lines-svg { position: absolute; inset: 0; width: 100%; height: 100%; z-index: 4; pointer-events: none; overflow: visible; }
        .lines-svg polyline { fill: none; stroke-linejoin: round; stroke-linecap: round; vector-effect: non-scaling-stroke; }
        .lines-svg .glow { stroke-width: 18px; opacity: .55; filter: blur(5px); }
        .lines-svg .core { stroke-width: 7px; filter: drop-shadow(0 0 3px #fff) drop-shadow(0 1px 2px rgba(0, 0, 0, .45)); }
        .float.line-win { font-size: calc(var(--bh) * .06); }

        /* ---- The buy badge, on the frame's top-left corner ---- */
        .layout-duet .side { grid-area: frame; z-index: 9; align-self: start; justify-self: start; width: calc(var(--bh) * .25);
                             margin: calc(var(--bh) * (var(--topk) - .14)) 0 0 calc(var(--bh) * (var(--el) - .1)); transition: opacity .4s ease; }
        .layout-duet .side > .feature { aspect-ratio: 1; display: grid; place-items: center; padding: 22% 12% 14%; border: 0; border-radius: 50%; box-shadow: none;
                                        background: var(--buy-art, radial-gradient(#ff4fa8, #b3005e)) center / contain no-repeat;
                                        filter: drop-shadow(0 10px 14px rgba(0, 40, 70, .4)); }
        .layout-duet .buy { grid-template-columns: 1fr; grid-template-areas: "title" "price"; justify-items: center; text-align: center;
                            padding: 0; border: 0; background: none; box-shadow: none; row-gap: 4px; }
        .layout-duet .buy::before, .layout-duet .buy-icon { display: none; }
        .layout-duet .buy-title { font-size: calc(var(--bh) * .022); letter-spacing: .08em; line-height: 1.05; }
        .layout-duet .buy-price { font-size: calc(var(--bh) * .026); }
        .layout-duet .feature-note { display: none; }
        body.layout-duet.free .side { opacity: 0; pointer-events: none; visibility: hidden; transition: opacity .4s ease, visibility 0s .4s; }

        /* ---- Amore: the free spins' heart, on the frame's top-right corner ---- */
        .amore { position: absolute; z-index: 9; right: calc(var(--bh) * (var(--er) * -1 - .02)); bottom: calc(100% - var(--bh) * .05);
                 width: calc(var(--bh) * .27); aspect-ratio: 1; display: grid; place-items: center; pointer-events: none;
                 filter: drop-shadow(0 10px 14px rgba(40, 0, 40, .45)); }
        .amore[hidden] { display: none; }
        .amore img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: contain; }
        .amore-fill { position: absolute; left: 19%; top: 21%; width: 62%; height: 56%; overflow: visible; }
        #amore-level { fill: url(#amore-glow); fill: #ff3fa4; transition: y .5s cubic-bezier(.3, .7, .3, 1); filter: drop-shadow(0 0 6px #ff9ad4); }
        .amore b { position: relative; margin-top: -6%; font: 900 calc(var(--bh) * .07)/1 var(--num); color: #fff; -webkit-text-stroke: .1em #7a0040;
                   paint-order: stroke fill; text-shadow: 0 2px 0 #4a0028; }
        .amore small { position: absolute; bottom: 14%; font: 800 calc(var(--bh) * .022)/1 var(--num); letter-spacing: .16em; color: #fff;
                       text-shadow: 0 1px 0 #7a0040, 0 0 6px #ff3fa4; }
        .amore.beat { animation: amore-beat .5s ease-out; }
        @keyframes amore-beat { 0% { scale: 1; } 30% { scale: 1.22; } 60% { scale: .96; } 100% { scale: 1; } }

        /* ---- The hostesses, full-length on their podiums ---- */
        .duet { position: relative; z-index: 4; align-self: stretch; pointer-events: none; }
        .duet.left { grid-area: left; margin-right: calc(var(--bh) * var(--el) * -.5); }
        .duet.right { grid-area: right; margin-left: calc(var(--bh) * var(--er) * -.5); }
        .hostess { position: absolute; left: 0; right: 0; bottom: calc(var(--bh) * .13); height: calc(var(--bh) * var(--hk)); }
        /* Every pose stands on the same line, its feet (--fy of its height) on the podium, its body's
           centre (--cx of its width) in the middle of the column. */
        .hostess-fig { position: absolute; left: 50%; bottom: 0; height: calc(100% * var(--k, 1)); aspect-ratio: var(--ar); z-index: 1;
                       translate: calc(var(--cx) * -100%) calc((1 - var(--fy, 1)) * 100%); opacity: 0; transition: opacity .22s ease; }
        .hostess-fig.on { opacity: 1; z-index: 2; }
        .hostess-body { width: 100%; height: 100%; transform-origin: calc(var(--cx) * 100%) 100%; animation: hostess-sway 5.5s ease-in-out infinite alternate; }
        .duet.right .hostess-body { animation-delay: -2.2s; animation-duration: 6.2s; }
        .hostess-body .pose { display: block; width: 100%; height: 100%; object-fit: contain; filter: drop-shadow(0 14px 16px rgba(0, 40, 70, .35)); }
        /* A girl drawn facing away from the reels on her side is mirrored (slot-kit-riviera.js sets --cx to match). */
        .hostess-fig.mirror .pose { scale: -1 1; }
        @keyframes hostess-sway { from { transform: rotate(-.8deg) scale(1, 1); } to { transform: rotate(.9deg) scale(1.006, .994); } }
        /* The podium: its white top runs from 2% to 34% of its height, so the feet line (the bottom
           of .hostess) falls at 22%, just in front of the top's middle: she stands on it, not at its edge. */
        .podium { position: absolute; z-index: 0; left: 50%; bottom: 0; width: calc(var(--bh) * var(--hk) * .5); translate: -50% 78%;
                  filter: drop-shadow(0 12px 14px rgba(0, 40, 70, .35)); }
        /* Her shadow on the podium's top, under her feet. */
        .hostess::before { content: ''; position: absolute; z-index: 1; left: 50%; bottom: 0; width: calc(var(--bh) * var(--hk) * .3); height: calc(var(--bh) * .045);
                           translate: -50% 45%; border-radius: 50%; pointer-events: none;
                           background: radial-gradient(closest-side, rgba(40, 30, 40, .42), rgba(40, 30, 40, .22) 55%, transparent); }
        .hostess-peek { display: none; }
        body.free .hostess-body .pose { filter: drop-shadow(0 0 18px rgba(255, 70, 190, .45)) drop-shadow(0 14px 16px rgba(0, 0, 0, .45)); }

        /* ---- The bar: plain figures, centred under the reels ---- */
        .layout-duet .bar { grid-template-columns: minmax(0, 1fr) auto auto auto auto auto minmax(0, 1fr);
                            grid-template-areas: "tools bal minus spin plus betm win"; column-gap: 0; align-items: center;
                            padding: 0 max(14px, calc((100vw - var(--group)) / 2)) max(8px, env(safe-area-inset-bottom));
                            background: linear-gradient(180deg, transparent, rgba(0, 32, 56, .62) 45%); }
        .layout-duet .tools { justify-self: start; display: grid; grid-template-columns: repeat(3, max-content); gap: 6px; }
        .layout-duet .bar .meter { min-height: 0; min-width: 0; display: flex; align-items: baseline; gap: .55em; padding: 0; border: 0; border-radius: 0;
                                   background: none; box-shadow: none; text-align: left; }
        .layout-duet .bar .meter small { font-size: clamp(11px, 1vw, 14px); letter-spacing: .1em; }
        .layout-duet .bar .meter span, .layout-duet .bar .meter > b { font-size: clamp(14px, 1.45vw, 21px); }
        .layout-duet .bal { justify-self: end; margin: 0 clamp(10px, 1.2vw, 20px) 0 0; white-space: nowrap; }
        .layout-duet .bet .meter { grid-area: betm; justify-self: start; margin: 0 0 0 clamp(10px, 1.2vw, 20px); }
        .layout-duet .winm { justify-self: end; align-items: baseline; margin: 0; min-width: 0; padding: 0; }
        .layout-duet .winm small { font-size: clamp(16px, 1.8vw, 28px) !important; letter-spacing: .04em; }
        .layout-duet .winm #win { font-size: clamp(24px, 3vw, 46px); }
        .layout-duet .perch { display: none; }
        .layout-duet .winm.lit { box-shadow: none; }
        .layout-duet #bet-minus, .layout-duet #bet-plus { margin: 0 clamp(8px, 1vw, 16px); align-self: center; }
        .layout-duet .spin { margin-top: 0; }

        @media (orientation: landscape) and (max-height: 520px) {
            body.layout-duet .stage { --spin: clamp(58px, 15vh, 76px); }
        }

        @media (orientation: portrait) {
            .duet, .line-marks { display: none; }
            .layout-duet .play { display: grid; grid-template: minmax(0, 1fr) auto / minmax(0, 1fr); grid-template-areas: "frame" "side"; }
            .layout-duet .frame { padding: 0; place-items: end center; }
            .layout-duet .board-wrap { --bw: min(94cqw, calc(150cqh / (1 + 2 * var(--ky))), 720px); aspect-ratio: 5 / 3;
                                       margin-bottom: calc(var(--bw) * 3 / 5 * var(--ky)); }
            @supports (width: round(down, 10.5px, 1px)) {
                .layout-duet .board-wrap { width: round(down, var(--bw), 5px); }
            }
            .layout-duet .logo-frame { display: none; }
            /* The logo gets its own room at the top rather than spilling past it (and off the screen). */
            body.layout-duet { --top: clamp(70px, 13vh, 130px); }
            .layout-duet .top .logo { display: block; height: 100%; }
            .layout-duet .side { grid-area: side; justify-self: center; width: clamp(96px, 26vw, 140px); margin: 6px 0 0; }
            .layout-duet .buy-title { font-size: 10px; }
            .layout-duet .buy-price { font-size: 11px; }
            .amore { --bh: calc(var(--bw) * 3 / 5); right: -2%; bottom: calc(100% + var(--bw) * .01); width: calc(var(--bw) * .22); }
            /* Each hostess's top half over a top corner of the frame, the frame hiding where she is cut.
               No taller than the room above the frame, so her head stays on screen on a short
               screen (a phone under a casino's header): the logo's row, plus the frame's cell
               (100cqh) less the grid and the frame below it, which sits at the cell's bottom. */
            .hostess-peek { display: block; position: absolute; z-index: 2; bottom: calc(100% - 4px); width: 46%;
                            height: min(34vh, calc(var(--bw) * .62), calc(var(--top) + 100cqh - var(--bw) * 3 / 5 * (1 + var(--ky)) + 4px));
                            clip-path: inset(-100vh -100vw 0 -100vw); pointer-events: none; }
            .hostess-peek.left { left: -6%; }
            .hostess-peek.right { right: -6%; }
            .hostess-peek .hostess-fig { left: 50%; bottom: calc(100% - 100% / .5); height: calc(100% / .5 * var(--k, 1)); }
            .layout-duet .bar { grid-template-columns: minmax(0, 1fr) auto minmax(0, 1fr); grid-template-areas: "bal spin win" "bet spin tools";
                                padding-bottom: max(16px, env(safe-area-inset-bottom)); /* as slot.blade.php's portrait .bar */ }
            .layout-duet .bar .meter { display: grid; gap: 2px; text-align: center; }
            .layout-duet .bet .meter { min-width: 3.6em; margin: 0; }
            .layout-duet #bet-minus, .layout-duet #bet-plus, .layout-duet .bal, .layout-duet .winm { margin: 0; }
            .layout-duet .winm small { font-size: 11px !important; }
            .layout-duet .winm #win { font-size: 22px; }
        }
        @media (prefers-reduced-motion: reduce) {
            .hostess-body { animation: none; }
        }
