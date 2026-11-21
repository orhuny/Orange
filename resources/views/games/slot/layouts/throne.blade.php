{{-- The "throne" layout (theme.php 'layout' => 'throne'), on top of the shared slot page's
     styles: slot.blade.php includes it inside its <style>, before the theme's own style.

     Landscape, three columns centred together:
     - left, the feature cards (buy, super buy, ante), centred on the grid; in free spins they
       make way for what the theme's kit puts there (Stones of the Titan: the Eclipse);
     - middle, the grid in its frame, with the tumble-win plaque on the frame's top edge;
     - right, the logo, and under it the host standing full-length on his rock, facing the grid
       and overlapping the frame a little.
     A bar of plain figures runs underneath: the tools and BALANCE / BET at the left, the WIN in
     the middle under the grid, − SPIN + at the right under the host.

     --bh is the grid's height, as in the classic layout; --tk is the host's column, of it.
     Portrait: the classic portrait, with the plaque above the grid and the host's head and
     shoulders over the frame's top-right corner. --}}
        body.layout-throne .stage {
            --side: clamp(148px, 12vw, 228px); --tk: .62; --reach: 0; --spin: clamp(78px, 12vh, 118px);
            --barh: calc(var(--spin) + 10px);
            --plq: .13; /* the plaque's height, of the grid's */
            --topk: max(var(--et), .06) + var(--plq) * .72;
            --bh: min(calc((100dvh - var(--barh) - 18px) / (1 + var(--topk) + var(--eb))),
                      calc((100vw - var(--side) - var(--gap) * 2 - 28px) / (var(--fwk) + var(--tk))), 900px);
            --group: calc(var(--side) + var(--gap) * 2 + var(--bh) * (var(--fwk) + var(--tk)));
        }
        @supports (width: round(down, 10.5px, 1px)) {
            body.layout-throne .stage {
                --bh: round(down, min(calc((100dvh - var(--barh) - 18px) / (1 + var(--topk) + var(--eb))),
                                      calc((100vw - var(--side) - var(--gap) * 2 - 28px) / (var(--fwk) + var(--tk))), 900px), 5px);
            }
        }
        /* One row, as tall as the frame and centred: the throne column stretches to the frame's height. */
        .layout-throne .play { display: grid; grid-template-columns: var(--side) auto calc(var(--bh) * var(--tk)); align-items: center; justify-content: center;
                               align-content: center; column-gap: var(--gap); }
        .layout-throne .logo-frame { display: none; }
        .layout-throne .frame { padding-top: calc(var(--bh) * (var(--topk))); padding-bottom: calc(var(--bh) * var(--eb) * .5); }

        /* ---- The feature cards: tall art cards (--card-buy / --card-ante from the theme), the
           words and the switch on top. In free spins they fade away. ---- */
        .layout-throne .side { align-self: center; gap: clamp(10px, 1.6vh, 18px); transition: opacity .5s ease; }
        .layout-throne .side > .feature { border: 0; border-radius: 0; padding: 14% 9% 12%; box-shadow: none;
                                          background: var(--card-buy, linear-gradient(180deg, #1a1426, #07040c)) center / 100% 100% no-repeat;
                                          filter: drop-shadow(0 12px 18px rgba(0, 0, 0, .5)); }
        .layout-throne .side > .feature.ante { background-image: var(--card-ante, var(--card-buy)); }
        .layout-throne .side > .feature.super-buy { background-image: var(--card-super, var(--card-buy)); }
        .layout-throne .buy { grid-template-columns: 1fr; grid-template-areas: "icon" "title" "price"; justify-items: center; text-align: center; padding: 0;
                              border: 0; background: none; box-shadow: none; row-gap: 6px; }
        .layout-throne .buy::before { display: none; }
        .layout-throne .buy-icon { width: clamp(38px, 3.6vw, 62px); }
        .layout-throne .buy-title { font-size: clamp(12px, 1.05vw, 15px); letter-spacing: .1em; }
        .layout-throne .buy-price { font-size: clamp(14px, 1.35vw, 19px); }
        .layout-throne .ante { grid-template-columns: 1fr; justify-items: center; text-align: center; }
        body.layout-throne.free .side > .feature { opacity: 0; pointer-events: none; visibility: hidden; transition: opacity .4s ease, visibility 0s .4s; }

        /* ---- The tumble-win plaque, on the frame's top edge (art: --tumble-art) ---- */
        .tumble { position: absolute; z-index: 7; left: 50%; bottom: calc(100% + var(--bh) * var(--et) * .55); translate: -50% 0;
                  height: calc(var(--bh) * var(--plq)); aspect-ratio: var(--tumble-ar, 4.2); display: grid; place-content: center; justify-items: center; gap: 1%;
                  background: var(--tumble-art, linear-gradient(180deg, #2a1d3a, #0b0612)) center / 100% 100% no-repeat; pointer-events: none;
                  filter: drop-shadow(0 6px 10px rgba(0, 0, 0, .55)); }
        .tumble small { font: 800 calc(var(--bh) * .024)/1 var(--num); letter-spacing: .16em; white-space: nowrap; color: var(--gold); text-shadow: 0 1px 0 rgba(0, 0, 0, .7); }
        .tumble b { min-height: 1em; font: 900 calc(var(--bh) * .05)/1 var(--num); color: #fff; font-variant-numeric: tabular-nums; white-space: nowrap;
                    text-shadow: 0 2px 0 rgba(0, 0, 0, .65), 0 0 12px rgba(255, 210, 120, .35); }
        .tumble.lit b { color: transparent; background: linear-gradient(180deg, #fffbe6, #ffe07a 48%, #ffad2e); -webkit-background-clip: text; background-clip: text;
                        filter: drop-shadow(0 2px 0 rgba(0, 0, 0, .7)) drop-shadow(0 0 10px rgba(255, 200, 90, .5)); }

        /* ---- The throne: the logo, and the host full-length on his rock ---- */
        .throne { position: relative; z-index: 4; align-self: stretch; margin-left: calc(var(--bh) * -.07); pointer-events: none; }
        .logo-throne { position: absolute; z-index: 3; left: 50%; top: calc(var(--bh) * -.02); width: calc(var(--bh) * var(--tk) * 1.08); max-height: calc(var(--bh) * .3);
                       translate: -50% 0; object-fit: contain; }
        body.free .logo-throne { opacity: .9; }
        .throne .host { position: absolute; left: 0; right: 0; top: calc(var(--bh) * .16); bottom: calc(var(--bh) * (var(--eb) * .5 + .08)); margin: 0; flex: none; z-index: 1;
                        animation: host-hover 4.2s ease-in-out infinite; }
        /* Every pose stands on the same line: its feet (--fy of its height) on the rock. */
        .throne .host-fig { left: 50%; bottom: 0; height: calc(100% * var(--k, 1)); aspect-ratio: var(--ar);
                            translate: calc(var(--cx) * -100%) calc((1 - var(--fy, 1)) * 100%); transform-origin: calc(var(--cx) * 100%) calc(var(--fy, 1) * 100%); }
        .throne .host-body { transform-origin: calc(var(--cx) * 100%) calc(var(--fy, 1) * 100%); animation-duration: 6.5s; }
        .throne .host-body .pose { filter: drop-shadow(0 12px 18px rgba(0, 0, 0, .5)); }
        .throne .pedestal { position: absolute; z-index: 0; left: 50%; bottom: 0; width: min(110%, calc(var(--bh) * .72)); translate: -50% 30%; }

        /* ---- The bar: plain figures, no pills ---- */
        .layout-throne .bar { grid-template-columns: auto auto minmax(0, 1fr) auto minmax(0, 1fr) auto auto auto;
                              grid-template-areas: "tools bal . win . minus spin plus" "tools betm . win . minus spin plus";
                              column-gap: 0; row-gap: 2px; align-items: center;
                              padding: 0 max(14px, calc((100vw - var(--group)) / 2)) max(8px, env(safe-area-inset-bottom));
                              background: linear-gradient(180deg, transparent, rgba(4, 2, 10, .78) 40%); }
        .layout-throne .tools { margin-right: clamp(10px, 1.4vw, 22px); display: grid; grid-template-columns: repeat(3, max-content); gap: 6px; }
        .layout-throne .bar .meter { min-height: 0; min-width: 0; display: flex; align-items: baseline; gap: .55em; padding: 0; border: 0; border-radius: 0;
                                     background: none; box-shadow: none; text-align: left; }
        .layout-throne .bar .meter small { font-size: clamp(11px, 1vw, 14px); letter-spacing: .1em; }
        .layout-throne .bar .meter span, .layout-throne .bar .meter > b { font-size: clamp(14px, 1.45vw, 21px); }
        .layout-throne .bal { justify-self: start; align-self: end; }
        .layout-throne .bet .meter { grid-area: betm; justify-self: start; align-self: start; margin: 0; }
        .layout-throne .winm { justify-self: center; align-items: baseline; margin: 0; min-width: 0; padding: 0; }
        .layout-throne .winm small { font-size: clamp(18px, 2.2vw, 34px) !important; letter-spacing: .04em; }
        .layout-throne .winm #win { font-size: clamp(26px, 3.4vw, 52px); }
        .layout-throne .perch { display: none; }
        .layout-throne .winm.lit { box-shadow: none; }
        .layout-throne #bet-minus, .layout-throne #bet-plus { margin: 0 clamp(8px, 1vw, 16px); align-self: center; }
        .layout-throne .spin { margin-top: 0; }

        /* Phones on their side: the tools in a row, everything a little smaller. */
        @media (orientation: landscape) and (max-height: 520px) {
            body.layout-throne .stage { --side: clamp(118px, 17vw, 170px); --spin: clamp(58px, 15vh, 76px); }
            .layout-throne .side > .feature { padding: 11% 8% 9%; }
            .layout-throne .buy-icon { width: 30px; }
            .layout-throne .tools { grid-template-columns: repeat(3, max-content); }
        }
        @media (orientation: landscape) and (max-width: 760px), (orientation: landscape) and (pointer: coarse) {
            .layout-throne .bar .tools { margin-left: 0; }
        }

        @media (orientation: portrait) {
            .throne { display: none; }
            .layout-throne .play { display: grid; grid-template: minmax(0, 1fr) auto / minmax(0, 1fr); }
            .layout-throne .side { flex-direction: row; align-self: auto; gap: 6px; }
            .layout-throne .side > .feature { padding: 9% 5% 8%; }
            .layout-throne .buy { row-gap: 2px; }
            .layout-throne .buy-icon { width: clamp(22px, 7vw, 34px); }
            .layout-throne .buy-title { font-size: 10px; letter-spacing: .04em; }
            .layout-throne .buy-price { font-size: 11px; }
            body.layout-throne.free .side { visibility: hidden; }
            /* Above the grid, clear of the host over the right corner. */
            .tumble { --bh: calc(var(--bw) * 5 / 6); bottom: calc(100% + var(--bw) * .01); height: calc(var(--bw) * .13); left: 38%; }
            /* His head and shoulders over the frame's top-right corner, facing the grid. */
            .layout-throne .host-peek { left: auto; right: -12%; width: 60%; height: clamp(70px, 17vh, 190px); }
            .layout-throne .host-peek .host-fig { left: auto; right: 0; bottom: calc(100% - 100% / .42); height: calc(100% / .42 * var(--k, 1));
                                                   translate: none; transform-origin: 60% 100%; }
            .layout-throne .bar { grid-template-columns: minmax(0, 1fr) auto minmax(0, 1fr); grid-template-areas: "bal spin win" "bet spin tools"; }
            .layout-throne .bar .meter { display: grid; gap: 2px; text-align: center; }
            .layout-throne .bet .meter { min-width: 3.6em; }
            .layout-throne .bet .meter > b { white-space: nowrap; overflow-wrap: normal; }
            .layout-throne .winm small { font-size: 11px !important; }
            .layout-throne .winm #win { font-size: 22px; }
        }
        @media (prefers-reduced-motion: reduce) {
            .throne .host { animation: none; }
        }
