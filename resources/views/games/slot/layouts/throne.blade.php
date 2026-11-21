{{-- The "throne" layout (theme.php 'layout' => 'throne'), on top of the shared slot page's
     styles: slot.blade.php includes it inside its <style>, before the theme's own style.

     Landscape, three columns centred together:
     - left, the logo over the feature cards (buy, super buy, ante), centred on the grid; in free
       spins the cards make way for what the theme's kit puts there (Stones of the Titan: the
       Eclipse);
     - middle, the grid in its frame, as large as the screen allows: the tumble-win plaque sits
       in the frame's top band, and the bar tucks over its bottom band;
     - right, the host alone, standing full-length on his rock (--hk of the grid's height), his
       body's centre a fixed distance from the grid's right edge: every pose stands over the
       frame's right border and the space beyond, never over the grid.
     A bar of plain figures runs underneath: the tools under the cards, the WIN centred under
     the grid, and BALANCE − SPIN + BET together at the right, under the host.

     --bh is the grid's height, as in the classic layout. The host's span from the grid's right
     edge is .74 of his height: the widest a pose reaches either side of his body's centre.
     Portrait: the classic portrait, with the plaque above the grid and the host's head and
     shoulders over the frame's top-right corner. --}}
        body.layout-throne .stage {
            --side: clamp(140px, 11vw, 210px); --reach: 0; --spin: clamp(78px, 12vh, 118px);
            --hk: 1.2; /* the host's height, of the grid's */
            --barh: calc(var(--spin) + 10px);
            --plq: .13; /* the plaque's height, of the grid's */
            --topk: calc(max(var(--et), .06) + .02); /* one calc: the shared .frame padding multiplies it */
            --ebk: calc(var(--eb) * .35); /* the bottom band shown above the bar */
            --bh: min(calc((100dvh - var(--barh) - 18px) / (1 + var(--topk) + var(--ebk))),
                      calc((100vw - var(--side) - var(--gap) - 24px) / (var(--el) + var(--ar) + var(--hk) * .74)), 900px);
            --group: calc(var(--side) + var(--gap) + var(--bh) * (var(--el) + var(--ar) + var(--hk) * .74));
        }
        @supports (width: round(down, 10.5px, 1px)) {
            body.layout-throne .stage {
                --bh: round(down, min(calc((100dvh - var(--barh) - 18px) / (1 + var(--topk) + var(--ebk))),
                                      calc((100vw - var(--side) - var(--gap) - 24px) / (var(--el) + var(--ar) + var(--hk) * .74)), 900px), 5px);
            }
        }
        /* One row, as tall as the frame and centred: the throne column stretches to the frame's height. */
        .layout-throne .play { display: grid; grid-template-columns: var(--side) auto calc(var(--bh) * (var(--hk) * .74 - var(--er)) - var(--gap));
                               align-items: center; justify-content: center; align-content: center; column-gap: var(--gap); }
        .layout-throne .logo-frame { display: none; }
        .layout-throne .frame { padding-top: calc(var(--bh) * (var(--topk))); padding-bottom: calc(var(--bh) * (var(--ebk))); }

        /* ---- The feature cards: tall art cards (--card-buy / --card-ante from the theme), the
           words and the switch on top. In free spins they fade away. ---- */
        .layout-throne .side { align-self: center; gap: clamp(10px, 1.6vh, 18px); transition: opacity .5s ease; }
        .logo-side { display: none; }
        .layout-throne .logo-side { display: block; width: 130%; max-height: calc(var(--bh) * .34); margin: 0 -15% clamp(0px, .4vh, 6px); object-fit: contain; }
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

        /* ---- The tumble-win plaque, in the frame's top band (art: --tumble-art) ---- */
        .tumble { position: absolute; z-index: 7; left: 50%; bottom: calc(100% + var(--bh) * max(.03, var(--et) - var(--plq) - .03)); translate: -50% 0;
                  height: calc(var(--bh) * var(--plq)); aspect-ratio: var(--tumble-ar, 4.2); display: grid; place-content: center; justify-items: center; gap: 1%;
                  background: var(--tumble-art, linear-gradient(180deg, #2a1d3a, #0b0612)) center / 100% 100% no-repeat; pointer-events: none;
                  filter: drop-shadow(0 6px 10px rgba(0, 0, 0, .55)); }
        .tumble small { font: 800 calc(var(--bh) * .024)/1 var(--num); letter-spacing: .16em; white-space: nowrap; color: var(--gold); text-shadow: 0 1px 0 rgba(0, 0, 0, .7); }
        .tumble b { min-height: 1em; font: 900 calc(var(--bh) * .05)/1 var(--num); color: #fff; font-variant-numeric: tabular-nums; white-space: nowrap;
                    text-shadow: 0 2px 0 rgba(0, 0, 0, .65), 0 0 12px rgba(255, 210, 120, .35); }
        .tumble.lit b { color: transparent; background: linear-gradient(180deg, #fffbe6, #ffe07a 48%, #ffad2e); -webkit-background-clip: text; background-clip: text;
                        filter: drop-shadow(0 2px 0 rgba(0, 0, 0, .7)) drop-shadow(0 0 10px rgba(255, 200, 90, .5)); }

        /* ---- The throne: the host full-length on his rock, from the grid's right edge on ---- */
        .throne { position: relative; z-index: 4; align-self: stretch; margin-left: calc(-1 * (var(--bh) * var(--er) + var(--gap))); pointer-events: none; }
        .throne .host { position: absolute; left: 0; right: 0; bottom: calc(var(--bh) * .02); height: calc(var(--bh) * var(--hk)); margin: 0; flex: none; z-index: 1;
                        animation: host-hover 4.2s ease-in-out infinite; }
        /* Every pose stands on the same line, its feet (--fy of its height) on the rock, its
           body's centre (--cx of its width) .405 of his height from the grid's edge. */
        .throne .host-fig { left: calc(var(--bh) * var(--hk) * .405); bottom: 0; height: calc(100% * var(--k, 1)); aspect-ratio: var(--ar);
                            translate: calc(var(--cx) * -100%) calc((1 - var(--fy, 1)) * 100%); transform-origin: calc(var(--cx) * 100%) calc(var(--fy, 1) * 100%); }
        .throne .host-body { transform-origin: calc(var(--cx) * 100%) calc(var(--fy, 1) * 100%); animation-duration: 6.5s; }
        .throne .host-body .pose { filter: drop-shadow(0 12px 18px rgba(0, 0, 0, .5)); }
        .throne .pedestal { position: absolute; z-index: 0; left: calc(var(--bh) * var(--hk) * .405); bottom: 0; width: calc(var(--bh) * var(--hk) * .62); translate: -50% 30%; }
        /* A soft shadow behind him: the busy sky dims there, so he stands out from it. */
        .throne .host::before { content: ''; position: absolute; z-index: 0; left: calc(var(--bh) * var(--hk) * .405); bottom: 2%; width: calc(var(--bh) * var(--hk) * .9);
                                height: 100%; translate: -50% 0; border-radius: 50%; pointer-events: none;
                                background: radial-gradient(closest-side, rgba(6, 3, 14, .6), rgba(6, 3, 14, .38) 58%, rgba(6, 3, 14, 0)); }

        /* ---- The bar: plain figures, no pills ----
           Its columns follow the cards-grid-host group above it: the tools under the cards;
           BALANCE − SPIN + BET together under the grid, the spin button exactly under its
           centre (the balance and the bet each get half of what is left either side), the
           balance right beside the spin button; the WIN under the host. */
        .layout-throne .bar { grid-template-columns: calc(var(--side) + var(--gap) + var(--bh) * var(--el)) minmax(0, 1fr) auto auto auto minmax(0, 1fr)
                                                     calc(var(--bh) * var(--hk) * .74);
                              grid-template-areas: "tools bal minus spin plus betm win";
                              column-gap: 0; align-items: center;
                              padding: 0 max(14px, calc((100vw - var(--group)) / 2)) max(8px, env(safe-area-inset-bottom));
                              background: linear-gradient(180deg, transparent, rgba(4, 2, 10, .78) 40%); }
        .layout-throne .tools { justify-self: start; display: grid; grid-template-columns: repeat(3, max-content); gap: 6px; }
        .layout-throne .bar .meter { min-height: 0; min-width: 0; display: flex; align-items: baseline; gap: .55em; padding: 0; border: 0; border-radius: 0;
                                     background: none; box-shadow: none; text-align: left; }
        .layout-throne .bar .meter small { font-size: clamp(11px, 1vw, 14px); letter-spacing: .1em; }
        .layout-throne .bar .meter span, .layout-throne .bar .meter > b { font-size: clamp(14px, 1.45vw, 21px); }
        .layout-throne .bal { justify-self: end; margin: 0; white-space: nowrap; }
        .layout-throne .bet .meter { grid-area: betm; justify-self: start; margin: 0 0 0 clamp(10px, 1.2vw, 20px); }
        .layout-throne .winm { justify-self: center; align-items: baseline; margin: 0 clamp(10px, 1.2vw, 20px); min-width: 0; padding: 0; }
        .layout-throne .winm small { font-size: clamp(18px, 2.2vw, 34px) !important; letter-spacing: .04em; }
        .layout-throne .winm #win { font-size: clamp(26px, 3.4vw, 52px); }
        .layout-throne .perch { display: none; }
        .layout-throne .winm.lit { box-shadow: none; }
        .layout-throne #bet-minus, .layout-throne #bet-plus { margin: 0 clamp(8px, 1vw, 16px); align-self: center; }
        .layout-throne .bal { margin-right: clamp(10px, 1.2vw, 20px); }
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
            .throne, .layout-throne .logo-side { display: none; }
            .layout-throne .play { display: grid; grid-template: minmax(0, 1fr) auto / minmax(0, 1fr); }
            .layout-throne .side { flex-direction: row; align-self: auto; gap: 6px; }
            /* Low cards, so the grid sits lower and the Titan gets more room above it. */
            .layout-throne .side > .feature { padding: 7% 5% 6%; }
            .layout-throne .buy { row-gap: 1px; }
            .layout-throne .buy-icon { width: clamp(18px, 6vw, 28px); }
            .layout-throne .buy-title { font-size: 10px; letter-spacing: .04em; }
            .layout-throne .buy-price { font-size: 11px; }
            body.layout-throne.free .side { visibility: hidden; }
            /* Above the grid, clear of the host over the right corner. */
            .tumble { --bh: calc(var(--bw) * 5 / 6); bottom: calc(100% + var(--bw) * .01); height: calc(var(--bw) * .13); left: 38%; }
            /* The room above the grid is the Titan's: the logo moves to the top left, and the
               board sits at the bottom of its room (its bottom band just above the cards). */
            .layout-throne .top { align-items: flex-start; padding-left: 2vw; }
            .layout-throne .frame { place-items: end center; }
            /* A slightly narrower grid than the classic phone layout's (92% of the width), so
               the frame sits lower and more of the Titan shows above it. */
            .layout-throne .board-wrap { --bw: min(92cqw, calc(120cqh / (1 + 2 * var(--ky))), 640px); margin-bottom: calc(var(--bw) * 5 / 6 * var(--ky)); }
            @supports (width: round(down, 10.5px, 1px)) {
                .layout-throne .board-wrap { width: round(down, var(--bw), 6px); }
            }
            /* His top half behind the frame's top-right corner, facing the grid, from the top of
               the screen down to the grid's top edge (the room above the board, its container's
               height less the board and its bottom band, plus the logo's row). The frame's top
               band hides where he is cut, so he seems to stand behind the grid. */
            .layout-throne .host-peek { left: auto; right: -10%; width: 66%;
                                        height: min(50vh, calc(100cqh - var(--bw) * 5 / 6 * (1 + var(--ky)) + var(--top))); }
            .layout-throne .host-peek .host-fig { left: auto; right: 0; bottom: calc(100% - 100% / .5); height: calc(100% / .5 * var(--k, 1));
                                                   translate: none; transform-origin: 60% 100%; }
            .layout-throne .bar { grid-template-columns: minmax(0, 1fr) auto minmax(0, 1fr); grid-template-areas: "bal spin win" "bet spin tools"; }
            .layout-throne .bar .meter { display: grid; gap: 2px; text-align: center; }
            .layout-throne .bet .meter { min-width: 3.6em; margin: 0; }
            .layout-throne #bet-minus, .layout-throne #bet-plus, .layout-throne .bal, .layout-throne .winm { margin: 0; }
            .layout-throne .bet .meter > b { white-space: nowrap; overflow-wrap: normal; }
            .layout-throne .winm small { font-size: 11px !important; }
            .layout-throne .winm #win { font-size: 22px; }
        }
        @media (prefers-reduced-motion: reduce) {
            .throne .host { animation: none; }
        }
