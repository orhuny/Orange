@inject('engines', 'App\Domain\Game\Engines\EngineRegistry')
@php
    /** @var \App\Domain\Game\Models\Game|null $game */
    $themeKey = (string) ($config['theme'] ?? 'sugar-orchard');
    $theme = require resource_path("views/games/slot/themes/{$themeKey}/theme.php");
    $described = $game ? $engines->for('slot')->verifier()->describe($game) : null;
    // The page needs the paytable and rules, not the weight tables (those are on /v1/fairness/{code}).
    $rules = $described ? array_intersect_key($described, array_flip(['rtp', 'grid', 'paytable', 'scatter_pays', 'free_spins', 'max_win', 'costs', 'features'])) : null;
    $v = fn (string $path): string => asset($path).'?v='.filemtime(public_path($path));
    // What the script needs of the theme (@json splits its argument on commas: pass one variable).
    $pageTheme = array_intersect_key($theme, array_flip(['sound', 'symbols', 'stars', 'fragments', 'fx', 'banners', 'host', 'hostWing']));
    // The host (the Queen Bee) starts in her idle pose; slot-game.js swaps the pose and its variables.
    $hostPoses = (array) ($theme['host'] ?? []);
    $host = $hostPoses['idle'] ?? null;
    $hostWing = $theme['hostWing'] ?? null;
    $hostStyle = static fn (array $p): string => sprintf('--cx: %s; --ar: %s; --k: %s; --wx: %s; --wy: %s; --wh: %s', $p['cx'], $p['ar'], $p['k'] ?? 1,
        $p['wing']['x'] ?? 0, $p['wing']['y'] ?? 0, $p['wing']['h'] ?? 0).($hostWing ? "; --rx: {$hostWing['rx']}; --war: {$hostWing['ar']}" : '');

    // The frame image around the grid: the grid fills the frame's window exactly. In widths
    // and heights of the grid: where the image starts (fl, ft), its size (fw, fh), and the
    // vertical space it needs beyond the grid (ky).
    $w = $theme['frame']['window'];
    $fw = 1 / max(0.01, $w['x1'] - $w['x0']);
    $fh = 1 / max(0.01, $w['y1'] - $w['y0']);
    $frameVars = sprintf(
        '--fl:%.3f%%;--ft:%.3f%%;--fw:%.3f%%;--fh:%.3f%%;--ky:%.4f;',
        -$w['x0'] * $fw * 100, -$w['y0'] * $fh * 100, $fw * 100, $fh * 100,
        max($w['y0'], 1 - $w['y1']) * $fh,
    );
    // Landscape: the frame's pieces, in heights of the grid (its window's height). How far it
    // reaches past the grid (et, eb, el, er), each piece's width (lw, rw) and height (ph), and
    // where the stretched strip starts and ends inside the grid (ml, mr).
    $p = $theme['frame']['pieces']['px'];
    $winH = max(1, $p['y1'] - $p['y0']);
    $frameVars .= sprintf(
        '--et:%.4f;--eb:%.4f;--el:%.4f;--er:%.4f;--lw:%.4f;--rw:%.4f;--ph:%.4f;--ml:%.4f;--mr:%.4f;--frame-fill:url(\'%s\');',
        $p['y0'] / $winH, ($p['h'] - $p['y1']) / $winH, $p['x0'] / $winH, ($p['w'] - $p['x1']) / $winH,
        $p['cut_l'] / $winH, ($p['w'] - $p['cut_r']) / $winH, $p['h'] / $winH,
        ($p['cut_l'] - $p['x0']) / $winH, ($p['x1'] - $p['cut_r']) / $winH, $theme['frame']['pieces']['fill'],
    );
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>{{ $game?->name ?? 'Slot' }}</title>
    {{-- slot-game.js imports these two; map them to their versioned URLs so a deploy never pairs new and old files. --}}
    <script type="importmap">
        {"imports": {
            @json(asset('js/orange/bridge.js')): @json($v('js/orange/bridge.js')),
            @json(asset('js/orange/slot-timeline.js')): @json($v('js/orange/slot-timeline.js'))
        }}
    </script>
    <link rel="preload" as="image" href="{{ $theme['frame']['src'] }}">
    <style>
        @font-face { font-family: "Slot Sora"; font-weight: 400 800; font-style: normal; font-display: swap;
            src: url("{{ asset('fonts/sora/sora-latin-ext.woff2') }}") format("woff2");
            unicode-range: U+0100-02BA, U+02BD-02C5, U+02C7-02CC, U+02CE-02D7, U+02DD-02FF, U+0304, U+0308, U+0329, U+1D00-1DBF, U+1E00-1E9F, U+1EF2-1EFF, U+2020, U+20A0-20AB, U+20AD-20C0, U+2113, U+2C60-2C7F, U+A720-A7FF; }
        @font-face { font-family: "Slot Sora"; font-weight: 400 800; font-style: normal; font-display: swap;
            src: url("{{ asset('fonts/sora/sora-latin.woff2') }}") format("woff2");
            unicode-range: U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+0304, U+0308, U+0329, U+2000-206F, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD; }
        :root {
            --cream: #fff6e2; --ink: #3a1a06; --wood: #7a3f17; --wood-hi: #b8692c; --orange: #ff8a12; --orange-hi: #ffc04a;
            --leaf: #3fae49; --gold: #ffd451; --panel: rgba(46, 20, 4, .78);
            --day-l: url('{{ $theme['backgrounds']['day']['landscape'] }}'); --day-p: url('{{ $theme['backgrounds']['day']['portrait'] }}');
            --night-l: url('{{ $theme['backgrounds']['night']['landscape'] }}'); --night-p: url('{{ $theme['backgrounds']['night']['portrait'] }}');
            --spin-art: url('{{ $theme['spin'] }}'); --plaque: url('{{ $theme['plaque']['src'] }}'); --plaque-ratio: {{ $theme['plaque']['ratio'] }};
            --round: 'Arial Rounded MT Bold', 'Baloo 2', 'Trebuchet MS', system-ui, -apple-system, 'Segoe UI', sans-serif;
            --num: 'Slot Sora', 'Arial Rounded MT Bold', system-ui, -apple-system, 'Segoe UI', sans-serif; /* the bar's figures and labels */
            --top: clamp(44px, 11vh, 104px);
        }
        * { box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        html, body { margin: 0; height: 100%; overflow: hidden; background: #2b1405; color: var(--cream); font: 600 14px/1.25 var(--round); user-select: none; }
        button { font: inherit; color: inherit; cursor: pointer; }
        button:disabled { cursor: not-allowed; opacity: .45; }

        /* ---- The orchard: day, and night for free spins, with what sways in front of it ---- */
        .bg { position: fixed; inset: 0; background: center / cover no-repeat var(--day-l); transition: opacity 1.2s ease; }
        .bg.night { background-image: var(--night-l); opacity: 0; }
        body.free .bg.night { opacity: 1; }
        .bg::after { content: ''; position: absolute; inset: 0; background: radial-gradient(ellipse at center, transparent 50%, rgba(30, 10, 0, .4)); }
        .scenery { position: fixed; inset: 0; pointer-events: none; overflow: hidden; }
        .branch { position: absolute; bottom: -3vh; width: clamp(200px, 25vw, 520px); }
        .branch.left { left: -2vw; }
        .branch.right { right: -2vw; transform: scaleX(-1); }
        .branch img { display: block; width: 100%; transform-origin: 0 80%; animation: sway 7s ease-in-out infinite alternate;
                      filter: drop-shadow(0 12px 14px rgba(40, 15, 0, .35)); transition: filter 1.2s ease; }
        .branch.right img { animation-duration: 8.5s; animation-delay: -3s; }
        body.free .branch img { filter: brightness(.42) saturate(.75) drop-shadow(0 12px 14px rgba(0, 0, 20, .5)); }
        @keyframes sway { from { transform: rotate(-1.4deg); } to { transform: rotate(1.8deg); } }
        .lantern { position: absolute; top: -1vh; width: clamp(44px, 5vw, 92px); opacity: 0; transition: opacity 1.4s ease; transform-origin: 50% 0;
                   animation: swing 5s ease-in-out infinite alternate; filter: drop-shadow(0 0 22px rgba(255, 170, 60, .85)); }
        .lantern.l1 { left: 4vw; } .lantern.l2 { left: 13vw; top: -5vh; width: clamp(34px, 3.6vw, 66px); animation-delay: -2s; }
        .lantern.l3 { right: 5vw; animation-delay: -1s; } .lantern.l4 { right: 14vw; top: -6vh; width: clamp(32px, 3.2vw, 60px); animation-delay: -3.5s; }
        body.free .lantern { opacity: 1; }
        @keyframes swing { from { transform: rotate(-4deg); } to { transform: rotate(4deg); } }
        .fireflies i { position: absolute; width: 6px; height: 6px; border-radius: 50%; background: #fff2a8; box-shadow: 0 0 10px 4px rgba(255, 210, 90, .75);
                       opacity: 0; transition: opacity 1.5s; animation: firefly 9s ease-in-out infinite alternate; }
        body.free .fireflies i { opacity: var(--o, .8); }
        @keyframes firefly { 0% { transform: translate(0, 0); } 50% { transform: translate(3vw, -4vh); } 100% { transform: translate(-2vw, -8vh); } }
        .petal { position: absolute; top: -8vh; width: clamp(18px, 2vw, 34px); opacity: .95; }

        /* ---- Layout ----
           Landscape:
           - a wide grid (--ar, cells about 1.2× wider than tall), about three quarters of the
             screen's height;
           - the logo straddles the frame's top edge;
           - the feature panels sit beside the grid, and the two are centred together;
           - a slim bar below, with the spin button under the grid's right corner, overlapping it.
           --bh is the grid's height; everything else follows from it. Portrait: see the media
           query at the end. */
        .stage { position: relative; height: 100%; display: flex; flex-direction: column; padding: max(8px, env(safe-area-inset-top)) 12px 0;
                 --ar: 1.45; --side: clamp(172px, 12vw, 220px); --gap: clamp(12px, 1.6vw, 28px); --spin: clamp(84px, 15vh, 140px);
                 --topk: max(var(--et), .17);
                 --fwk: calc(var(--ar) + var(--el) + var(--er));
                 --bh: min(calc((100dvh - var(--spin) * .62 - 30px) / (1 + var(--topk))), calc((100vw - var(--side) - var(--gap) - 24px) / var(--fwk)), 900px);
                 --group: calc(var(--side) + var(--gap) + var(--bh) * var(--fwk)); }
        .top { position: absolute; left: 0; right: 0; top: max(8px, env(safe-area-inset-top)); z-index: 8; display: flex; justify-content: center; pointer-events: none; }
        .top .logo { display: none; }
        .logo { filter: drop-shadow(0 6px 8px rgba(40, 15, 0, .5)); }
        .logo-frame { position: absolute; left: 50%; bottom: calc(100% - var(--bh) * .03); height: calc(var(--bh) * .2); translate: -50% 0; z-index: 7; pointer-events: none; }
        body.free .logo-frame { display: none; }
        .play { flex: 1; min-height: 0; display: flex; align-items: center; justify-content: center; gap: var(--gap); }

        .side { position: relative; z-index: 4; display: flex; flex-direction: column; gap: 12px; width: var(--side); }

        /* ---- The host, the Queen Bee (slot-game.js, "The host") ----
           Landscape: she stands above the feature panels, which move down and hide her skirt;
           her raised hand presents the grid, over the frame's ornament. Her art is her upper
           body, fading out at the bottom (scripts/art/process-sugar-orchard.py). Per pose:
           - --cx: her body's centre, so she stands centred over the panels whatever her arms do;
           - --k: the pose's height next to idle's at the same scale (raised hands reach higher),
             every pose ending at the same point of her body, so her size never jumps;
           - --wx/--wy/--wh: her wings' root and length; --rx/--war: the wing's own root and shape.
           Every pose is in the page, stacked; the one she shows has `on`. A new pose fades in
           fast on top with a little spring, the old one fades out slower beneath it, so she never
           blinks out. She hovers and sways (.host-body, so the sway never fights the switch);
           her wings flutter from their root, behind her, faster when she's excited.
           Portrait: she peeks over the frame's top-left corner, the frame hiding the cut. */
        .side.with-host { align-self: stretch; justify-content: flex-end; }
        .host { position: relative; flex: 1; min-height: 0; z-index: -1; pointer-events: none;
                margin-bottom: calc(var(--bh) * -.09 - 12px); animation: host-hover 3.4s ease-in-out infinite; }
        .host-fig { position: absolute; left: 50%; bottom: 0; height: calc(min(100%, var(--bh) * .66) * var(--k, 1)); aspect-ratio: var(--ar);
                    translate: calc(var(--cx) * -100%) 0; transform-origin: calc(var(--cx) * 100%) 100%;
                    opacity: 0; transform: scale(.95); transition: opacity .32s ease-in, transform .32s ease-in; }
        .host-fig.on { z-index: 1; opacity: 1; transform: none; transition: opacity .14s ease-out, transform .45s cubic-bezier(.2, 1.6, .45, 1); }
        .host-fig:not(.on) .wing { animation-play-state: paused; }
        .host-body { position: absolute; inset: 0; transform-origin: calc(var(--cx) * 100%) 100%; animation: host-sway 5.5s ease-in-out infinite; }
        .host-fig .pose { position: relative; z-index: 1; display: block; width: 100%; height: 100%; filter: drop-shadow(0 8px 14px rgba(30, 10, 0, .45)); }
        .host-fig .spark { position: absolute; z-index: 2; translate: -50% -50%; pointer-events: none; }
        .host-fig .wing { position: absolute; z-index: 0; left: calc(var(--wx) * 100%); top: calc(var(--wy) * 100%); height: calc(var(--wh) * 100%);
                          aspect-ratio: var(--war); translate: calc(var(--rx) * -100%) -100%; transform-origin: calc(var(--rx) * 100%) 100%;
                          rotate: var(--wr); animation: wing-flutter .32s ease-in-out infinite alternate; }
        .host-fig .wing.near { --wr: -6deg; filter: drop-shadow(0 0 10px rgba(255, 210, 120, .45)); }
        .host-fig .wing.far { --wr: 22deg; height: calc(var(--wh) * 82%); filter: brightness(.8); animation-delay: -.16s; }
        .host-fig:is([data-pose=cheer], [data-pose=wow]) .wing { animation-duration: .17s; }
        .host-peek { display: none; }
        @keyframes host-sway { 0%, 100% { rotate: -.7deg; } 50% { rotate: .7deg; scale: 1.012; } }
        @keyframes host-hover { 50% { translate: 0 -.8vmin; } }
        @keyframes wing-flutter { from { rotate: var(--wr); scale: 1 1; } to { rotate: calc(var(--wr) - 9deg); scale: .8 1; } }

        /* ---- Buy free spins and the ante bet: chocolate glass in a gold rim ---- */
        .feature { position: relative; border-radius: 16px; padding: 10px; border: 2px solid transparent;
                   background: linear-gradient(180deg, rgba(78, 37, 12, .95), rgba(34, 13, 3, .95)) padding-box,
                               linear-gradient(180deg, #ffe9a8, #d18b2c 42%, #7a4210) border-box;
                   box-shadow: 0 12px 26px rgba(0, 0, 0, .45), inset 0 1px 0 rgba(255, 226, 170, .22); }
        .buy { position: relative; width: 100%; display: grid; grid-template-columns: auto 1fr; grid-template-areas: "icon title" "price price";
               align-items: center; column-gap: 8px; row-gap: 8px; padding: 9px 10px 10px 8px;
               border-radius: 12px; border: 1.5px solid transparent; text-align: left; overflow: hidden; transition: transform .12s ease, filter .2s ease;
               background: linear-gradient(180deg, #ffae3c 0%, #f4710d 52%, #c94a05 100%) padding-box, linear-gradient(180deg, #fff1c4, #e3a23e) border-box;
               box-shadow: inset 0 1px 0 rgba(255, 255, 255, .55), inset 0 -3px 0 rgba(110, 35, 0, .35), 0 6px 14px rgba(120, 40, 0, .45); }
        .buy::before { content: ''; position: absolute; left: 5%; right: 5%; top: 3px; height: 42%; border-radius: 10px 10px 50% 50%; pointer-events: none;
                       background: linear-gradient(180deg, rgba(255, 255, 255, .42), rgba(255, 255, 255, 0)); }
        .buy-icon { grid-area: icon; width: clamp(34px, 3.2vw, 50px); aspect-ratio: 1; object-fit: contain; filter: drop-shadow(0 3px 3px rgba(80, 25, 0, .5)); }
        .buy-text { display: contents; }
        .buy-title { grid-area: title; font-size: clamp(11px, 1vw, 13px); line-height: 1.12; letter-spacing: .08em; text-transform: uppercase; color: #fff;
                     text-shadow: 0 1px 0 #8a3200, 0 0 8px rgba(120, 40, 0, .35); }
        .buy-price { grid-area: price; justify-self: stretch; text-align: center; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; border-radius: 999px; padding: 4px 8px;
                     font-size: clamp(12px, 1.2vw, 16px); color: #ffe7a0; background: rgba(46, 16, 0, .62); box-shadow: inset 0 1px 2px rgba(0, 0, 0, .45); font-variant-numeric: tabular-nums; }
        .buy:not(:disabled):hover { transform: translateY(-1px); filter: brightness(1.06); }
        .buy:not(:disabled):active { transform: translateY(1px); }
        .buy:disabled { opacity: 1; filter: grayscale(.85) brightness(.7) contrast(.92); }
        .feature-note { margin: 8px 2px 0; font-size: 11px; line-height: 1.3; font-weight: 500; color: #ffd9a6; text-align: center; }
        .ante { display: grid; grid-template-columns: 1fr auto; align-items: center; gap: 10px; }
        .feature-title { display: block; margin-bottom: 3px; font-size: 13px; letter-spacing: .1em; text-transform: uppercase; color: var(--gold); text-shadow: 0 1px 0 #3a1400; }
        .ante-desc { display: block; font-size: 11.5px; line-height: 1.35; font-weight: 500; color: #f6e2c4; }
        .toggle { position: relative; flex: none; width: 50px; height: 28px; padding: 0; border-radius: 999px; border: 1px solid rgba(255, 214, 150, .35);
                  background: linear-gradient(180deg, #1e0b02, #3a1806); box-shadow: inset 0 2px 4px rgba(0, 0, 0, .6); transition: background .2s, box-shadow .2s; }
        .toggle i { position: absolute; top: 3px; left: 3px; width: 20px; height: 20px; border-radius: 50%; box-shadow: 0 2px 4px rgba(0, 0, 0, .5);
                    background: radial-gradient(circle at 35% 30%, #fff7dc, #e6b65c 60%, #9c6420); transition: transform .22s cubic-bezier(.3, 1.4, .5, 1); }
        .toggle[aria-pressed=true] { background: linear-gradient(180deg, #2f9a3c, #5fd36a); border-color: rgba(190, 255, 190, .6);
                                     box-shadow: inset 0 2px 4px rgba(0, 40, 0, .35), 0 0 10px rgba(95, 211, 106, .45); }
        .toggle[aria-pressed=true] i { transform: translateX(22px); background: radial-gradient(circle at 35% 30%, #fff, #f2f2f2 60%, #c9c9c9); }
        .toggle:disabled { opacity: .55; }

        /* ---- The grid in its frame ----
           Landscape: the frame in pieces scaled to the grid's height, so its ornaments keep
           their shape at any width:
           - the left piece, its left edge on the grid's;
           - the right piece, on the grid's right edge;
           - a strip of plain line stretched between them (under the logo).
           The ornaments get their room above and at the sides; below, they tuck behind the bar. */
        .frame { flex: none; padding: calc(var(--bh) * var(--topk)) calc(var(--bh) * var(--er)) calc(var(--bh) * .02) calc(var(--bh) * var(--el)); }
        .board-wrap { position: relative; width: calc(var(--bh) * var(--ar)); height: var(--bh); }
        /* Whole pixels: the grid's height a multiple of 5 and its width of 6, so every cell
           starts on a pixel and the symbols are never resampled between pixels. */
        @supports (width: round(down, 10.5px, 1px)) {
            .stage { --bh: round(down, min(calc((100dvh - var(--spin) * .62 - 30px) / (1 + var(--topk))), calc((100vw - var(--side) - var(--gap) - 24px) / var(--fwk)), 900px), 5px); }
            .board-wrap { width: round(down, calc(var(--bh) * var(--ar)), 6px); }
        }
        .fp { position: absolute; top: calc(var(--bh) * var(--et) * -1); height: calc(var(--bh) * var(--ph)); z-index: 3; pointer-events: none;
              filter: drop-shadow(0 14px 22px rgba(40, 14, 0, .45)); }
        .fp-l { left: calc(var(--bh) * var(--el) * -1); width: calc(var(--bh) * var(--lw)); }
        .fp-r { right: calc(var(--bh) * var(--er) * -1); width: calc(var(--bh) * var(--rw)); }
        .fp-m { left: calc(var(--bh) * var(--ml) - 1px); right: calc(var(--bh) * var(--mr) - 1px); background: var(--frame-fill) 0 0 / 100% 100% no-repeat; }
        .frame-art { display: none; }
        /* Deep candy glass behind the symbols, with faint column lines: the fruit stands out
           sharply against it. */
        .board { position: absolute; inset: 0; overflow: hidden; border-radius: 8px; cursor: pointer;
                 background: linear-gradient(90deg, transparent calc(100% - 1px), rgba(255, 226, 170, .1) 0) 0 0 / calc(100% / 6) 100% repeat-x,
                             radial-gradient(ellipse at 50% 35%, rgba(98, 47, 17, .6), rgba(36, 13, 3, .76));
                 -webkit-backdrop-filter: blur(6px) saturate(1.1); backdrop-filter: blur(6px) saturate(1.1);
                 box-shadow: inset 0 0 0 1px rgba(255, 214, 150, .18), inset 0 12px 34px rgba(0, 0, 0, .38); transition: background 1.2s ease; }
        body.free .board { background: linear-gradient(90deg, transparent calc(100% - 1px), rgba(180, 210, 255, .1) 0) 0 0 / calc(100% / 6) 100% repeat-x,
                                       radial-gradient(ellipse at 50% 35%, rgba(36, 52, 124, .58), rgba(9, 13, 42, .76));
                           box-shadow: inset 0 0 0 1px rgba(170, 200, 255, .2), inset 0 12px 34px rgba(0, 0, 20, .45); }
        .frame-art { position: absolute; left: var(--fl); top: var(--ft); width: var(--fw); height: var(--fh); z-index: 3; pointer-events: none;
                     filter: drop-shadow(0 14px 22px rgba(40, 14, 0, .45)); }
        .fxc { position: absolute; left: -40%; top: -40%; width: 180%; height: 180%; z-index: 6; pointer-events: none; }
        /* No will-change: a symbol at rest is drawn at full quality, not as a GPU layer. */
        .sym { position: absolute; left: 0; top: 0; width: calc(100% / 6); height: calc(100% / 5); display: grid; place-items: center; z-index: 1;
               container-type: size; }
        .sym > img, .sym .star { width: 90%; height: 90%; object-fit: contain; pointer-events: none; filter: drop-shadow(0 4px 4px rgba(20, 8, 0, .5)); }
        .sym[data-s=S] > img { width: 98%; height: 98%; }
        .sym .star { position: relative; display: grid; place-items: center; }
        .sym .star img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: contain; }
        .sym .star b { position: relative; margin-top: 6%; font: 900 26cqmin/1 var(--round); color: #fff; -webkit-text-stroke: .14em #3d1600; paint-order: stroke fill;
                       text-shadow: 0 .06em 0 #3d1600; letter-spacing: -.02em; }
        .sym .frost { position: absolute; inset: 3%; width: 94%; height: 94%; opacity: 0; pointer-events: none; }
        .sym.win > img, .sym.win .star { animation: pulse .42s ease-in-out infinite alternate; filter: drop-shadow(0 0 10px #fff7b0) drop-shadow(0 0 4px #ffae00); }
        .sym.scatter > img { animation-duration: .3s; }
        @keyframes pulse { from { transform: scale(1); } to { transform: scale(1.12); } }
        .fx { position: absolute; inset: 0; pointer-events: none; z-index: 5; }
        .rays-cell { position: absolute; height: calc(100% / 5 * 2.4); aspect-ratio: 1; translate: -50% -50%; z-index: 0; pointer-events: none; opacity: 0; }
        .float { position: absolute; padding: 4px 12px; border-radius: 999px; background: rgba(60, 22, 0, .8); color: var(--gold); font-size: clamp(16px, 2.2vw, 26px);
                 white-space: nowrap; border: 2px solid var(--gold); text-shadow: 0 2px 0 #000; z-index: 7; }
        .float.mult { font-size: clamp(30px, 4.5vw, 54px); background: rgba(120, 10, 30, .85); border-color: #ffd0d0; color: #fff; }
        .float.scatter-win { background: rgba(10, 70, 30, .82); }
        .fs-hud { white-space: nowrap; align-self: center; background: linear-gradient(180deg, #2f62c9, #1b3b86); border: 2px solid #9fc4ff; border-radius: 999px;
                  font-size: clamp(15px, 2.2vw, 22px); padding: 8px 22px; box-shadow: 0 6px 16px rgba(0, 0, 0, .4); }
        .fs-hud b { color: var(--gold); font-size: 1.15em; }

        /* ---- The bar ----
           Landscape: one slim row, its ends lined up with the panel-and-grid group: tools,
           balance and bet on the left; the win in the middle; − SPIN + under the grid's right
           corner, the spin button reaching up over the grid. */
        .bar { position: relative; z-index: 5; flex: none; display: grid; align-items: center;
               grid-template-columns: auto auto auto 1fr auto auto auto; grid-template-areas: "tools bal betm win minus spin plus";
               column-gap: clamp(10px, 1.4vw, 22px); margin: 0 -12px;
               padding: 0 max(14px, calc((100vw - var(--group)) / 2)) max(8px, env(safe-area-inset-bottom));
               background: linear-gradient(180deg, transparent, rgba(30, 12, 2, .8) 45%); }
        /* Meters: chocolate pills in a gold rim, bold figures. */
        .meter { position: relative; display: grid; gap: 2px; text-align: center; min-width: 96px; padding: 6px 14px 7px; border-radius: 14px; border: 1.5px solid transparent;
                 background: linear-gradient(180deg, rgba(78, 37, 12, .9), rgba(30, 11, 2, .92)) padding-box, linear-gradient(180deg, #ffe7a3, #b8741f) border-box;
                 box-shadow: 0 6px 14px rgba(0, 0, 0, .42), inset 0 1px 0 rgba(255, 230, 180, .25); }
        .meter small { font: 800 10px/1 var(--num); letter-spacing: .16em; color: var(--gold); text-shadow: 0 1px 0 #3a1400; }
        .meter span, .meter > b { font: 800 clamp(16px, 1.6vw, 23px)/1.1 var(--num); color: #fff; letter-spacing: -.01em; font-variant-numeric: tabular-nums;
                                  text-shadow: 0 2px 0 rgba(30, 10, 0, .55); white-space: nowrap; }
        .bal { grid-area: bal; justify-self: start; }
        .bet { display: contents; }
        .bet .meter { grid-area: betm; }
        #bet-minus { grid-area: minus; } #bet-plus { grid-area: plus; }
        /* The win: big golden figures, with fruit perched on top that bob, and cheer at a win. */
        .winm { grid-area: win; justify-self: center; min-width: clamp(150px, 15vw, 230px); padding: 6px 24px 8px; overflow: visible;
                background: linear-gradient(180deg, rgba(92, 44, 14, .92), rgba(34, 12, 2, .94)) padding-box, linear-gradient(180deg, #fff1c4, #e3a23e 45%, #8a4d12) border-box; }
        .winm #win { font-size: clamp(22px, 2.4vw, 34px); line-height: 1.05; color: transparent; text-shadow: none;
                     background: linear-gradient(180deg, #fffbe0, #ffd34d 52%, #f08a00); -webkit-background-clip: text; background-clip: text;
                     filter: drop-shadow(0 2px 0 #4a1e00) drop-shadow(0 0 10px rgba(255, 190, 60, .3)); display: inline-block; }
        .winm.lit { box-shadow: 0 6px 14px rgba(0, 0, 0, .42), 0 0 22px rgba(255, 196, 70, .55), inset 0 1px 0 rgba(255, 230, 180, .3); }
        .perch { position: absolute; right: -10px; top: 0; translate: 0 -60%; display: flex; align-items: flex-end; gap: 0; pointer-events: none; }
        .perch img { width: clamp(24px, 2vw, 34px); aspect-ratio: 1; object-fit: contain; transform-origin: 50% 100%; filter: drop-shadow(0 3px 2px rgba(20, 8, 0, .5));
                     animation: hop 2.6s ease-in-out infinite; }
        .perch img:nth-child(2) { width: clamp(28px, 2.3vw, 40px); animation-delay: -.9s; }
        .perch img:nth-child(3) { animation-delay: -1.7s; }
        @keyframes hop { 0%, 100% { transform: translateY(0) rotate(0deg); } 30% { transform: translateY(-4px) rotate(-5deg); } 60% { transform: translateY(0) rotate(4deg) scaleY(.94); } }
        .winm.cheer .perch img { animation: cheer .42s ease-in-out 4; }
        @keyframes cheer { 50% { transform: translateY(-14px) rotate(10deg) scale(1.12); } }
        .pop-fruit { position: fixed; z-index: 9; pointer-events: none; width: clamp(32px, 2.7vw, 46px); translate: -50% -50%; filter: drop-shadow(0 4px 3px rgba(20, 8, 0, .5)); }

        /* Buttons: glossy candy. */
        .round, .tool { position: relative; display: grid; place-items: center; aspect-ratio: 1; padding: 0; border-radius: 50%; color: #fff; line-height: 1;
                        border: 2px solid rgba(255, 220, 160, .85); background: radial-gradient(circle at 35% 28%, #a8521c, #5a250a 58%, #2c0f02);
                        box-shadow: 0 5px 12px rgba(0, 0, 0, .45), inset 0 2px 0 rgba(255, 236, 190, .35), inset 0 -3px 0 rgba(0, 0, 0, .3);
                        transition: transform .12s ease, filter .15s ease; }
        .round { width: clamp(40px, 3.3vw, 50px); font: 800 clamp(22px, 2vw, 28px)/1 var(--num); text-shadow: 0 2px 0 rgba(0, 0, 0, .4); }
        .tool { width: clamp(38px, 2.9vw, 46px); }
        :is(.round, .tool):not(:disabled):hover { transform: translateY(-2px); filter: brightness(1.12); }
        :is(.round, .tool):not(:disabled):active { transform: translateY(1px); }
        .spin { grid-area: spin; width: var(--spin); aspect-ratio: 1; border: 0; padding: 0; border-radius: 50%; margin-top: calc(var(--spin) * -.38);
                background: var(--spin-art) center / contain no-repeat; filter: drop-shadow(0 8px 12px rgba(0, 0, 0, .5)); transition: transform .12s ease; }
        .spin:not(:disabled):not(.busy) { animation: breathe 2.8s ease-in-out infinite; }
        @keyframes breathe { 50% { scale: 1.035; filter: drop-shadow(0 8px 12px rgba(0, 0, 0, .5)) drop-shadow(0 0 14px rgba(255, 170, 60, .55)); } }
        .spin:not(:disabled):hover { transform: scale(1.04); }
        .spin:not(:disabled):active { transform: scale(.95); }
        .spin.busy { animation: rot .9s linear infinite; }
        @keyframes rot { to { transform: rotate(360deg); } }
        .tools { grid-area: tools; display: flex; gap: 8px; }
        .tool svg { width: 46%; height: 46%; filter: drop-shadow(0 1px 0 rgba(0, 0, 0, .4)); }
        .tool[aria-pressed=true] { background: radial-gradient(circle at 35% 28%, #8ff09a, #34a845 58%, #145a1f); border-color: #d9ffd2;
                                   box-shadow: 0 5px 12px rgba(0, 0, 0, .45), 0 0 14px rgba(95, 211, 106, .5), inset 0 2px 0 rgba(255, 255, 255, .4); }
        .tool .count { position: absolute; top: -7px; right: -7px; min-width: 20px; padding: 2px 5px; border-radius: 999px; font: 800 11px/1.2 var(--num);
                       background: linear-gradient(180deg, #ffb340, #e2600a); border: 1.5px solid #fff1c4; }
        .tool .count:empty { display: none; }

        /* ---- Dialogs: plain, on the plaque, and the celebration ---- */
        .overlay { position: fixed; inset: 0; z-index: 20; display: grid; place-items: center; background: rgba(20, 8, 0, .55); padding: 16px; overflow: hidden; }
        .overlay[hidden] { display: none; }
        .overlay.dim { background: rgba(10, 4, 0, .68); }
        .dialog { position: relative; min-width: min(320px, 92vw); max-width: min(520px, 94vw); max-height: 88vh; overflow: auto; text-align: center; padding: 22px 24px; border-radius: 22px;
                  background: linear-gradient(180deg, #fff8e8, #ffe7bf); color: var(--ink); border: 4px solid var(--wood-hi); box-shadow: 0 16px 40px rgba(0, 0, 0, .5); animation: pop .35s cubic-bezier(.2, 1.4, .4, 1); }
        .dialog.wide { max-width: min(680px, 96vw); text-align: left; }
        @keyframes pop { from { transform: scale(.6); opacity: 0; } }
        .dialog .kicker { margin: 0; font-size: 18px; letter-spacing: 1px; color: #8a3b00; }
        .dialog .huge { font-size: clamp(44px, 9vw, 84px); line-height: 1.05; color: var(--orange); -webkit-text-stroke: 3px #5a2000; paint-order: stroke fill; font-variant-numeric: tabular-nums; }
        .dialog .note { font-size: 14px; opacity: .85; }
        .dialog h2, .dialog h3 { margin: 12px 0 6px; color: #8a3b00; }
        .dialog ul { padding-left: 20px; margin: 6px 0; } .dialog li { margin: 4px 0; font-weight: 500; }
        .dialog .mono { font: 500 11px/1.4 ui-monospace, monospace; word-break: break-all; opacity: .8; }
        .dialog.plaque { width: min(560px, 92vw); max-width: none; max-height: none; aspect-ratio: var(--plaque-ratio); overflow: visible; padding: 0; border: 0; border-radius: 0;
                         background: var(--plaque) center / 100% 100% no-repeat; box-shadow: none; filter: drop-shadow(0 18px 30px rgba(0, 0, 0, .55));
                         display: flex; flex-direction: column; align-items: center; justify-content: center; color: #fff1d0; margin-top: 8vh; }
        .dialog.plaque .banner { position: absolute; left: 50%; top: 0; width: 96%; translate: -50% -62%; pointer-events: none;
                                 filter: drop-shadow(0 8px 10px rgba(0, 0, 0, .5)); animation: bob 1.8s ease-in-out infinite alternate; }
        @keyframes bob { from { transform: translateY(0) scale(1); } to { transform: translateY(-4%) scale(1.03); } }
        .dialog.plaque .body { padding: 9% 14% 5%; }
        .dialog.plaque .huge { color: var(--gold); -webkit-text-stroke: 3px #3a1400; text-shadow: 0 4px 0 #3a1400; }
        .dialog.plaque .note, .dialog.plaque .kicker { color: #ffe6bd; opacity: 1; }
        .dialog.plaque .actions { margin-top: 8px; }
        .dialog.celebrate { background: none; border: 0; box-shadow: none; overflow: visible; max-width: none; padding: 0; color: #fff; }
        .dialog.celebrate .banner { display: block; width: min(760px, 92vw); margin: 0 auto; filter: drop-shadow(0 10px 18px rgba(0, 0, 0, .55));
                                    animation: pop .5s cubic-bezier(.2, 1.5, .4, 1), bob 1.4s .5s ease-in-out infinite alternate; }
        .dialog.celebrate .huge { color: var(--gold); -webkit-text-stroke: 4px #3a1400; text-shadow: 0 6px 0 #3a1400, 0 0 30px rgba(255, 200, 80, .6); margin-top: -1vh; }
        .overlay > .rays { position: absolute; left: 50%; top: 50%; width: min(150vmin, 1400px); aspect-ratio: 1; translate: -50% -50%; pointer-events: none;
                           animation: turn 16s linear infinite, fadein .6s ease-out; opacity: .9; }
        @keyframes turn { to { rotate: 360deg; } }
        @keyframes fadein { from { opacity: 0; } }
        .overlay > .firework { position: absolute; pointer-events: none; translate: -50% -50%; }
        .actions { display: flex; gap: 10px; justify-content: center; margin-top: 16px; }
        .actions button, .choice { border: 0; border-radius: 12px; padding: 10px 22px; font-size: 16px; }
        .primary { background: linear-gradient(180deg, #ffb340, #f0700a); color: #fff; text-shadow: 0 1px 0 #8a3000; box-shadow: inset 0 2px 0 rgba(255, 255, 255, .4), 0 4px 10px rgba(0, 0, 0, .35); }
        .ghost { background: #ead2a8; color: var(--ink); }
        .choices { display: flex; gap: 8px; justify-content: center; margin: 12px 0; }
        .choice { background: var(--leaf); color: #fff; min-width: 58px; }
        .check { display: flex; gap: 8px; align-items: center; justify-content: center; font-weight: 500; }
        table.pays { width: 100%; border-collapse: collapse; font-variant-numeric: tabular-nums; }
        table.pays th { font-size: 12px; color: #8a3b00; } table.pays td { text-align: center; padding: 2px 4px; border-top: 1px solid rgba(120, 60, 10, .15); }
        table.pays img { width: 44px; height: 44px; object-fit: contain; vertical-align: middle; }
        img.inline { width: 30px; height: 30px; vertical-align: middle; object-fit: contain; }
        /* Game history: one row per bet, newest first; a row opens to its details and seeds. */
        .dialog.history { width: min(680px, 96vw); }
        .hist-head, .hrow summary { display: grid; grid-template-columns: minmax(84px, auto) 1fr auto 18px; gap: 10px; align-items: center; }
        .hist-head { padding: 0 12px 4px; font: 800 10px/1 var(--num); letter-spacing: .12em; text-transform: uppercase; color: #8a5a2b; }
        .hist-head span:nth-child(3) { text-align: right; }
        .hist { display: grid; gap: 6px; margin-top: 4px; max-height: min(58vh, 520px); overflow: auto; padding-right: 2px; }
        .hrow { border-radius: 12px; background: rgba(255, 255, 255, .6); border: 1px solid rgba(120, 60, 10, .16); }
        .hrow[open] { background: #fffaf0; box-shadow: 0 4px 12px rgba(90, 40, 0, .12); }
        .hrow summary { padding: 9px 12px; cursor: pointer; list-style: none; font-variant-numeric: tabular-nums; }
        .hrow summary::-webkit-details-marker { display: none; }
        .hrow summary::after { content: '▾'; color: #b07a44; transition: transform .2s; }
        .hrow[open] summary::after { transform: rotate(180deg); }
        .h-time { font-size: 12px; font-weight: 600; color: #8a5a2b; line-height: 1.25; }
        .h-bet { font: 800 15px/1.3 var(--num); color: var(--ink); }
        .h-win { font: 800 15px/1.2 var(--num); color: #a07850; text-align: right; }
        .hrow.won .h-win { color: #1f8a3e; }
        .h-win small { display: block; font-size: 11px; font-weight: 700; color: #8a5a2b; }
        .tag { display: inline-block; margin-left: 6px; padding: 2px 7px; border-radius: 999px; vertical-align: 2px;
               font: 800 9.5px/1.2 var(--num); letter-spacing: .06em; text-transform: uppercase; background: #f3d9ae; color: #6a3510; }
        .tag.fs { background: #d8e6ff; color: #1b3b86; }
        .tag.max { background: linear-gradient(180deg, #ffe58a, #ffbf2e); color: #5a2600; }
        .hrow dl { display: grid; grid-template-columns: auto 1fr; gap: 4px 14px; margin: 0; padding: 2px 12px 12px; font-size: 12px; }
        .hrow dt { color: #8a5a2b; font-weight: 600; }
        .hrow dd { margin: 0; font-weight: 600; word-break: break-all; }
        .hrow dd.mono { font: 500 11px/1.4 ui-monospace, monospace; }
        .hist-note { margin: 10px 0 0; font-size: 12px; opacity: .8; }
        .confetti { position: fixed; top: 0; z-index: 21; pointer-events: none; }
        .toast { position: fixed; left: 50%; bottom: 118px; transform: translateX(-50%); z-index: 25; background: rgba(120, 20, 10, .92); border-radius: 12px; padding: 10px 16px; max-width: 92vw; text-align: center; }
        .status { position: fixed; left: 50%; top: 40%; transform: translate(-50%, -50%); z-index: 15; background: var(--panel); border-radius: 14px; padding: 12px 18px; text-align: center; }

        /* The intro, about 4 s (slot-game.js playIntro adds the sprinkles, glints, petals and the exit):
           0 s the orchard zooms in from dusk · .1 s the logo drops · .5 s it lands: shake, frost flash,
           shockwave, rays · .66 s the fruit pops up, centre first · 1 s two stars spin in ·
           1.25 s a shine sweeps the logo · 1.6 s the fruit hops in a wave. */
        .intro { position: fixed; inset: 0; width: 100%; height: 100%; max-width: none; max-height: none;
                 margin: 0; padding: 0; border: 0; color: var(--cream); overflow: hidden; background: #24140a; }
        .intro::backdrop { background: transparent; }
        .intro[open] { display: block; }
        .intro-scene { position: absolute; inset: -16px; padding: 32px; display: grid; place-items: center; overflow: hidden;
                       animation: intro-shake .34s .5s linear both; }
        .intro-bg { position: absolute; inset: 0; z-index: 0;
                    background: radial-gradient(ellipse at center, rgba(26, 27, 8, .18), rgba(23, 12, 4, .8)), var(--day-l) center / cover no-repeat;
                    animation: intro-zoom 4.4s cubic-bezier(.2, .6, .3, 1) both; }
        .intro-art { position: relative; z-index: 1; width: min(84vw, 110vh, 620px); display: grid; justify-items: center; gap: 1vmin; }
        .intro-head { position: relative; width: 100%; }
        .intro-rays { position: absolute; left: 50%; top: 50%; z-index: -1; width: min(170vmin, 1500px); max-width: none; aspect-ratio: 1;
                      translate: -50% -50%; pointer-events: none; animation: intro-rays 1s .45s ease-out both, turn 18s linear infinite; }
        .intro-flash, .intro-ring { position: absolute; left: 50%; top: 52%; translate: -50% -50%; pointer-events: none; opacity: 0; }
        .intro-flash { width: 110%; animation: intro-flash .8s .48s ease-out both; }
        .intro-ring { width: 46%; animation: intro-ring .75s .5s cubic-bezier(.1, .6, .3, 1) both; }
        .intro-logo { position: relative; z-index: 1; animation: intro-breathe 2.4s 1.5s ease-in-out infinite; }
        .intro-logo img { width: 100%; display: block; transform-origin: 50% 85%; animation: intro-drop .8s .1s both; }
        /* A band of light crossing the letters only (masked by the logo itself). */
        .intro-logo::after { content: ''; position: absolute; inset: 0; pointer-events: none; mix-blend-mode: plus-lighter;
                             background: linear-gradient(110deg, transparent 40%, rgba(255, 250, 225, .75) 50%, transparent 60%) 100% 0 / 300% 100% no-repeat;
                             -webkit-mask: var(--logo) center / 100% 100% no-repeat; mask: var(--logo) center / 100% 100% no-repeat;
                             animation: intro-shine 1.5s 1.25s ease-in-out 2; }
        .intro-star { position: absolute; z-index: 2; top: 3%; width: 13%; pointer-events: none; --dir: -1;
                      filter: drop-shadow(0 6px 8px rgba(40, 15, 0, .55));
                      animation: intro-star-in .7s 1s cubic-bezier(.2, 1.6, .4, 1) both, intro-star-bob 2.6s 1.7s ease-in-out infinite; }
        .intro-star.l { left: 4%; }
        .intro-star.r { right: 4%; top: 13%; --dir: 1; animation-delay: 1.08s, 1.78s; }
        .intro-fruit { display: flex; align-items: end; justify-content: center; margin-top: -1vmin; }
        .intro-fruit span { width: calc(clamp(36px, 14vmin, 112px) * var(--s)); margin: 0 -.3vmin; transform-origin: 50% 100%;
                            animation: intro-pop .6s calc(.66s + var(--i) * .08s) cubic-bezier(.25, 1.7, .45, 1) both; }
        .intro-fruit img { display: block; width: 100%; aspect-ratio: 1; object-fit: contain; transform-origin: 50% 100%;
                           filter: drop-shadow(0 8px 7px rgba(35, 14, 0, .6));
                           animation: intro-hop 1.8s calc(1.6s + var(--n) * .1s) ease-in-out infinite; }
        /* Added by playIntro: the sprinkle burst, glints and petals (.behind: under the logo). */
        .intro-bit { position: absolute; z-index: 3; translate: -50% -50%; pointer-events: none; }
        .intro-bit.behind { z-index: 0; }
        .intro-skip { position: absolute; z-index: 4; right: max(12px, env(safe-area-inset-right));
                      bottom: max(12px, env(safe-area-inset-bottom)); min-height: 44px; padding: 0 14px;
                      border: 1px solid rgba(255, 223, 167, .5); border-radius: 999px; background: #2e1409; }
        .intro.leaving { pointer-events: none; animation: intro-leave var(--leave, .45s) ease-in both; }
        .intro.leaving .intro-art { animation: intro-through var(--leave, .45s) ease-in both; }
        @keyframes intro-zoom { from { transform: scale(1.22); opacity: .2; } 30% { opacity: 1; } to { transform: scale(1.02); } }
        @keyframes intro-shake { 0%, 100% { translate: 0 0; } 15% { translate: -8px 6px; } 35% { translate: 7px -5px; }
                                 55% { translate: -5px 3px; } 75% { translate: 3px -2px; } }
        @keyframes intro-drop {
            0% { transform: translateY(-75vh) scale(.9, 1.12); animation-timing-function: cubic-bezier(.55, 0, .9, .45); }
            50% { transform: translateY(0) scale(1.14, .84); animation-timing-function: cubic-bezier(.2, .7, .4, 1); }
            68% { transform: translateY(-5%) scale(.95, 1.06); animation-timing-function: ease-in-out; }
            84% { transform: translateY(0) scale(1.03, .97); }
            100% { transform: none; }
        }
        @keyframes intro-flash { from { opacity: 0; scale: .3; } 18% { opacity: 1; } to { opacity: 0; scale: 1.5; } }
        @keyframes intro-ring { from { opacity: 0; scale: .2; } 6% { opacity: 1; } to { opacity: 0; scale: 3; } }
        @keyframes intro-rays { from { opacity: 0; scale: .3; } to { opacity: .85; scale: 1; } }
        @keyframes intro-breathe { 50% { scale: 1.03; } }
        @keyframes intro-shine { from { background-position: 100% 0; } 55%, to { background-position: 0 0; } }
        @keyframes intro-star-in { from { opacity: 0; scale: 0; rotate: calc(220deg * var(--dir)); } }
        @keyframes intro-star-bob { 0%, 100% { rotate: 0deg; translate: 0 0; } 25% { rotate: 9deg; translate: 0 -7%; }
                                    50% { translate: 0 0; } 75% { rotate: -9deg; translate: 0 -7%; } }
        @keyframes intro-pop { from { opacity: 0; transform: translateY(70%) scale(.3) rotate(calc(-24deg + var(--n) * 8deg)); } }
        @keyframes intro-hop {
            0%, 46%, 100% { transform: none; }
            10% { transform: translateY(2%) scale(1.08, .9); }
            22% { transform: translateY(-24%) scale(.94, 1.07) rotate(-6deg); }
            34% { transform: translateY(0) scale(1.1, .9); }
            40% { transform: translateY(-3%) scale(.98, 1.02); }
        }
        @keyframes intro-leave { to { opacity: 0; } }
        @keyframes intro-through { to { transform: scale(1.14); } }

        [hidden] { display: none !important; }
        @media (orientation: landscape) and (max-width: 760px), (orientation: landscape) and (pointer: coarse) {
            .tools { display: grid; grid-template-columns: repeat(3, max-content); }
            .bar { grid-template-columns: auto auto 1fr auto auto auto auto;
                   grid-template-areas: "bal betm win minus spin plus tools"; }
        }

        @media (orientation: portrait) {
            :root { --top: clamp(36px, 7vh, 68px); }
            .stage { --spin: clamp(64px, 19vw, 88px); }
            .bg { background-image: var(--day-p); } .bg.night { background-image: var(--night-p); }
            .intro-bg { background-image: radial-gradient(ellipse at center, rgba(26, 27, 8, .18), rgba(23, 12, 4, .8)), var(--day-p); }
            .branch { top: 10vh; bottom: auto; width: clamp(160px, 42vw, 360px); }
            .top { position: static; flex-direction: column; align-items: center; height: var(--top); flex: none; }
            .top .logo { display: block; height: 100%; }
            body.free .top .logo { display: none; }
            .logo-frame { display: none; }
            .play { display: grid; grid-template: minmax(0, 1fr) auto / minmax(0, 1fr); justify-items: center; gap: 4px; }
            .side { order: 2; flex-direction: row; justify-content: center; gap: 6px; width: min(100%, 440px); }
            .side > .feature { flex: 1; min-width: 0; max-width: 220px; display: flex; flex-direction: column; justify-content: center; }
            .side > .feature.ante { display: grid; }
            .side .host { display: none; }
            .side.with-host { align-self: auto; }
            /* Her head and shoulders over the frame's top-left corner; the rail hides the cut. The
               box shows the top 62% of idle, cut only at the bottom (raised hands and wings rise
               above it); every pose ends at the same point below it, as in landscape. */
            .host-peek { display: block; position: absolute; z-index: 2; left: 2%; bottom: calc(100% - 3px); width: 60%;
                         height: clamp(60px, 14vh, 160px); clip-path: inset(-100vh -100vw 0 -100vw); pointer-events: none; }
            .host-peek .host-fig { left: 0; bottom: calc(100% - 100% / .62); height: calc(100% / .62 * var(--k, 1)); translate: none;
                                   transform-origin: 40% 100%; }
            .feature { padding: 5px; border-radius: 11px; }
            .buy { min-height: 44px; padding: 4px 5px; grid-template-areas: "icon title" "icon price"; column-gap: 4px; row-gap: 2px; border-radius: 8px; }
            .buy-icon { width: clamp(20px, 6vw, 32px); }
            .buy-title { font-size: 10px; letter-spacing: .04em; }
            .buy-title br { display: none; }
            .buy-price { padding: 1px 3px; font-size: 11px; }
            .feature-note { margin: 3px 0 0; font-size: 10px; line-height: 1.15; }
            .ante { gap: 4px; }
            .feature-title { margin-bottom: 2px; font-size: 11px; letter-spacing: .05em; }
            .ante-desc { font-size: 10px; line-height: 1.2; }
            .toggle { width: 40px; height: 24px; }
            .toggle i { width: 16px; height: 16px; }
            .toggle[aria-pressed=true] i { transform: translateX(16px); }
            /* Size the 6:5 grid from the space left after the logo, features and controls. */
            .frame { container-type: size; display: grid; place-items: center; align-self: stretch; width: 100%; padding: 0; }
            .board-wrap { --bw: min(98cqw, calc(120cqh / (1 + 2 * var(--ky))), 640px);
                          width: var(--bw); height: auto; aspect-ratio: 6 / 5; }
            .fp { display: none; }
            .frame-art { display: block; }
            .bar { grid-template-columns: minmax(0, 1fr) auto minmax(0, 1fr); grid-template-areas: "bal spin win" "bet spin tools";
                   row-gap: 5px; column-gap: 6px; margin-inline: auto; width: 100%; max-width: 560px; padding: 6px 2px max(8px, env(safe-area-inset-bottom)); }
            .spin { margin-top: 0; justify-self: center; }
            .winm { justify-self: end; text-align: right; }
            .tools { --tool-size: clamp(30px, 8vw, 36px); justify-self: end; justify-content: center; display: flex; flex-wrap: wrap;
                     width: calc(3 * var(--tool-size) + 8px); gap: 4px; }
            .tool { width: var(--tool-size); }
            .tool svg { width: 18px; height: 18px; }
            .bet { grid-area: bet; justify-self: start; display: flex; align-items: center; gap: 3px; max-width: 100%; }
            .bet .meter { padding-inline: 3px; }
            .bet .meter > b, .bal span, .winm #win { white-space: normal; overflow-wrap: anywhere; }
            .round { width: clamp(26px, 7.5vw, 36px); flex: none; }
            .meter { min-width: 0; padding: 5px 10px 6px; }
            .meter span, .meter > b { font-size: clamp(12px, 3.5vw, 16px); }
            .bal { max-width: 100%; padding-inline: 5px; }
            .winm { max-width: 100%; padding: 5px 12px 6px; }
            .winm #win { font-size: 20px; }
            .perch { right: 0; }
            .perch img, .perch img:nth-child(2) { width: 22px; }
            .dialog.plaque { margin-top: 10vh; }
            @supports (width: round(down, 10.5px, 1px)) {
                .board-wrap { width: round(down, var(--bw), 6px); }
            }
        }
        /* Short landscape screens (phones on their side): a smaller logo and compact panels. The
           ante's details stay in its label and in the rules. */
        @media (orientation: landscape) and (max-height: 520px) {
            .side { gap: 8px; }
            .feature { padding: 7px; border-radius: 12px; }
            .buy { padding: 7px 8px 8px 6px; row-gap: 6px; }
            .buy-icon { width: 30px; }
            .ante-desc { display: none; }
            .feature-title { margin: 0; }
            .feature-note { margin-top: 5px; font-size: 10px; }
            .tools { gap: 5px; }
            .tool { width: 32px; }
            .tool svg { width: 17px; height: 17px; }
            .meter { min-width: 0; }
            .meter { padding: 4px 9px 5px; }
            .meter span, .meter > b { font-size: 14px; }
            .winm { min-width: 120px; padding: 4px 14px 6px; }
            .winm #win { font-size: 19px; }
            .perch { right: -16px; }
            .perch img, .perch img:nth-child(2) { width: 20px; }
            .round { width: 34px; }
        }
        @media (orientation: landscape) and (max-width: 650px) {
            .bar { column-gap: 5px; padding-inline: 8px; }
            .winm { min-width: 0; padding-inline: 8px; }
        }
        @media (prefers-reduced-motion: reduce) {
            .branch img, .lantern, .fireflies i, .overlay > .rays, .dialog.plaque .banner, .dialog.celebrate .banner, .perch img, .spin:not(.busy), .host, .host-body, .host-fig .wing { animation: none; }
            .host-fig { transform: none; }
        }
    </style>
    <script src="{{ $v("js/orange/sound-{$theme['sound']}.js") }}"></script>
</head>
<body>
<div class="bg day"></div>
<div class="bg night"></div>
<div class="scenery" aria-hidden="true">
    <div class="branch left"><img src="{{ $theme['scenery']['branch'] }}" alt=""></div>
    <div class="branch right"><img src="{{ $theme['scenery']['branch'] }}" alt=""></div>
    @foreach (['l1', 'l2', 'l3', 'l4'] as $l)
        <img class="lantern {{ $l }}" src="{{ $theme['scenery']['lantern'] }}" alt="">
    @endforeach
    <div id="petals"></div>
    <div id="fireflies" class="fireflies"></div>
</div>

<main class="stage" style="{{ $frameVars }}">
    <header class="top">
        <img class="logo" src="{{ $theme['logo'] }}" alt="{{ $game?->name ?? 'Slot' }}">
        <div id="fs-hud" class="fs-hud" hidden>FREE SPINS <b id="fs-count"></b> &nbsp;·&nbsp; WIN <b id="fs-win"></b></div>
    </header>

    <section class="play">
        <aside @class(['side', 'with-host' => $host])>
            @if ($host)
                <div class="host" aria-hidden="true">
                    @include('games.slot.host-figs')
                </div>
            @endif
            <div id="buy-box" class="feature" hidden>
                <button id="buy-btn" class="buy" disabled>
                    <img class="buy-icon" src="{{ $theme['symbols']['S']['img'] }}" alt="">
                    <span class="buy-text">
                        <span class="buy-title">Buy<br> free spins</span>
                        <span class="buy-price"><span id="buy-cost">—</span> <span data-currency></span></span>
                    </span>
                </button>
                <p id="buy-note" class="feature-note" hidden></p>
            </div>
            <div id="ante-box" class="feature ante" hidden>
                <div>
                    <span class="feature-title">Ante bet</span>
                    <span class="ante-desc">Free spins about 1.75× as often</span>
                    <span class="ante-desc">Stake +25%</span>
                </div>
                <button id="ante-btn" class="toggle" aria-pressed="false" aria-label="Ante bet: free spins about 1.75× as often, for a 25% higher stake"><i></i></button>
            </div>
        </aside>

        <div class="frame">
            <div class="board-wrap">
                <div id="board" class="board" aria-label="Slot grid"><div id="fx" class="fx"></div></div>
                <img class="frame-art" src="{{ $theme['frame']['src'] }}" alt="">
                <img class="fp fp-l" src="{{ $theme['frame']['pieces']['left'] }}" alt="">
                <div class="fp fp-m"></div>
                <img class="fp fp-r" src="{{ $theme['frame']['pieces']['right'] }}" alt="">
                <img class="logo logo-frame" src="{{ $theme['logo'] }}" alt="">
                @if ($host)
                    <div class="host-peek" aria-hidden="true">
                        @include('games.slot.host-figs')
                    </div>
                @endif
                <canvas id="fxc" class="fxc"></canvas>
            </div>
        </div>
    </section>

    <footer class="bar">
        <div class="meter bal"><small>BALANCE</small><span><span id="balance">—</span> <span data-currency></span></span></div>
        <div class="bet">
            <button id="bet-minus" class="round" aria-label="Lower bet">−</button>
            <div class="meter"><small>BET</small><b id="bet">—</b></div>
            <button id="bet-plus" class="round" aria-label="Raise bet">+</button>
        </div>
        <button id="spin-btn" class="spin" aria-label="Spin" disabled></button>
        <div id="win-meter" class="meter winm" aria-live="polite">
            <span class="perch" aria-hidden="true">
                <img src="{{ $theme['symbols']['G']['img'] }}" alt=""><img src="{{ $theme['symbols']['E']['img'] }}" alt=""><img src="{{ $theme['symbols']['F']['img'] }}" alt="">
            </span>
            <small>WIN</small><span id="win">0.00</span>
        </div>
        <div class="tools">
            <button id="auto-btn" class="tool" aria-pressed="false" aria-label="Autoplay" title="Autoplay">
                <svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 2l4 4-4 4"/><path d="M3 11v-1a4 4 0 0 1 4-4h14"/><path d="M7 22l-4-4 4-4"/><path d="M21 13v1a4 4 0 0 1-4 4H3"/></svg>
                <span id="auto-count" class="count"></span>
            </button>
            <button id="turbo-btn" class="tool" aria-pressed="false" aria-label="Turbo" title="Turbo">
                <svg viewBox="0 0 24 24" fill="#fff"><path d="M13 2 4 14h7l-1 8 9-12h-7z"/></svg>
            </button>
            <button id="sound-btn" class="tool" aria-pressed="true" aria-label="Sound" title="Sound">
                <svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 5 6 9H2v6h4l5 4z" fill="#fff"/><path d="M15.5 8.5a5 5 0 0 1 0 7"/><path d="M19 5a10 10 0 0 1 0 14"/></svg>
            </button>
            <button id="history-btn" class="tool" aria-label="Game history" title="Game history" hidden>
                <svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round"><path d="M3.5 12a8.5 8.5 0 1 0 2.6-6.1"/><path d="M3 4.5V9h4.5"/><path d="M12 7.5V12l3 2"/></svg>
            </button>
            <button id="info-btn" class="tool" aria-label="Paytable and rules" title="Paytable and rules">
                <svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.4" stroke-linecap="round"><circle cx="12" cy="12" r="9"/><path d="M12 11v6"/><path d="M12 7.5v.5"/></svg>
            </button>
            <button id="home-btn" type="button" class="tool" aria-label="Back to lobby" title="Back to lobby" hidden>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m3 10 9-7 9 7M5 9v12h5v-6h4v6h5V9"/></svg>
            </button>
        </div>
    </footer>
</main>

<div id="overlay" class="overlay" hidden></div>
<div id="toast" class="toast" hidden></div>
<div id="status" class="status">{{ $game ? 'Waiting for the casino…' : 'No slot game is published yet.' }}</div>

@if ($game)
{{-- The intro: the logo drops in and bursts into sprinkles, fruit pops up and hops (slot-game.js playIntro). --}}
<dialog id="intro" class="intro" aria-label="{{ $game->name }}">
    <div class="intro-scene" aria-hidden="true">
        <div class="intro-bg"></div>
        <div class="intro-art">
            <div class="intro-head">
                <img class="intro-rays" src="{{ $theme['fx']['rays'] }}" alt="">
                <img class="intro-flash" src="{{ $theme['fx']['frostBurst'] }}" alt="">
                <img class="intro-ring" src="{{ $theme['fx']['frostRing'] }}" alt="">
                <div class="intro-logo" style="--logo: url('{{ $theme['logo'] }}')"><img class="logo" src="{{ $theme['logo'] }}" alt=""></div>
                <img class="intro-star l" src="{{ $theme['stars']['gold'] ?? '' }}" alt="">
                <img class="intro-star r" src="{{ $theme['stars']['ruby'] ?? '' }}" alt="">
            </div>
            <div class="intro-fruit">
                {{-- --i: the order they pop up in (centre first); --s: size. --}}
                @foreach ([['F', 3, .78], ['H', 2, .88], ['G', 1, .96], ['E', 0, 1.18], ['B', 1, .96], ['C', 2, .88], ['I', 3, .78]] as $n => [$letter, $order, $size])
                    <span style="--i: {{ $order }}; --n: {{ $n }}; --s: {{ $size }}"><img src="{{ $theme['symbols'][$letter]['img'] }}" alt=""></span>
                @endforeach
            </div>
        </div>
    </div>
    <button class="intro-skip" type="button" autofocus>Skip intro</button>
</dialog>
<script type="module">
    import { startSlot } from @json($v('js/orange/slot-game.js'));

    startSlot({
        // Operators embed this page directly (relay play); Orange's host pages do from our own origin.
        allowedOrigins: [...@json($allowedOrigins), window.location.origin],
        embedded: window.parent !== window,
        gameName: @json($game->name),
        verifyUrl: @json(url("/v1/fairness/{$game->code}/verify")),
        theme: @json($pageTheme),
        config: @json($config),
        rules: @json($rules),
    });
</script>
@endif
</body>
</html>
