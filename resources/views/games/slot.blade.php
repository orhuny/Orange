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
    $pageTheme = array_intersect_key($theme, array_flip(['sound', 'symbols', 'stars', 'fragments', 'fx', 'banners']));

    // The frame image around the grid: the grid fills the frame's window exactly. In widths
    // and heights of the grid: where the image starts (fl, ft), its size (fw, fh), and how far
    // it reaches past the grid on each side (kx, ky), which the layout keeps free.
    $w = $theme['frame']['window'];
    $fw = 1 / max(0.01, $w['x1'] - $w['x0']);
    $fh = 1 / max(0.01, $w['y1'] - $w['y0']);
    $frameVars = sprintf(
        '--fl:%.3f%%;--ft:%.3f%%;--fw:%.3f%%;--fh:%.3f%%;--kx:%.4f;--ky:%.4f;',
        -$w['x0'] * $fw * 100, -$w['y0'] * $fh * 100, $fw * 100, $fh * 100,
        max($w['x0'], 1 - $w['x1']) * $fw, max($w['y0'], 1 - $w['y1']) * $fh,
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
        :root {
            --cream: #fff6e2; --ink: #3a1a06; --wood: #7a3f17; --wood-hi: #b8692c; --orange: #ff8a12; --orange-hi: #ffc04a;
            --leaf: #3fae49; --gold: #ffd451; --panel: rgba(46, 20, 4, .78);
            --day-l: url('{{ $theme['backgrounds']['day']['landscape'] }}'); --day-p: url('{{ $theme['backgrounds']['day']['portrait'] }}');
            --night-l: url('{{ $theme['backgrounds']['night']['landscape'] }}'); --night-p: url('{{ $theme['backgrounds']['night']['portrait'] }}');
            --spin-art: url('{{ $theme['spin'] }}'); --plaque: url('{{ $theme['plaque']['src'] }}'); --plaque-ratio: {{ $theme['plaque']['ratio'] }};
            --round: 'Arial Rounded MT Bold', 'Baloo 2', 'Trebuchet MS', system-ui, -apple-system, 'Segoe UI', sans-serif;
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
           Landscape: the logo heads the left column and the free-spins counter floats over
           the top, so the grid gets the full height. Portrait: the logo heads the page. */
        .stage { position: relative; height: 100%; display: flex; flex-direction: column; padding: max(8px, env(safe-area-inset-top)) 12px 0;
                 --side: clamp(140px, 14vw, 200px); --spin: clamp(76px, min(8.6vw, 13vh), 108px); }
        .top { position: absolute; left: 0; right: 0; top: max(8px, env(safe-area-inset-top)); z-index: 8; display: flex; justify-content: center; pointer-events: none; }
        .top .logo { display: none; }
        .logo { filter: drop-shadow(0 6px 8px rgba(40, 15, 0, .5)); }
        .play { flex: 1; min-height: 0; display: flex; align-items: center; justify-content: center; gap: clamp(10px, 2vw, 28px); }

        .side { position: relative; z-index: 4; display: flex; flex-direction: column; gap: 12px; width: var(--side); }
        .logo-side { width: 100%; height: auto; margin-bottom: 2px; }
        .play::after { content: ''; flex: none; width: var(--side); } /* keeps the board centred */

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
           The frame's ornaments get their room above and at the sides; below, they tuck behind the bar. */
        .frame { --bw: min(calc((100vw - 2 * var(--side) - 2 * clamp(10px, 2vw, 28px) - 24px) / (1 + 2 * var(--kx))),
                           calc((100dvh - var(--spin) - 40px) / (1 + 1.25 * var(--ky)) * 1.2), 1000px);
                 padding: calc(var(--bw) / 1.2 * var(--ky)) calc(var(--bw) * var(--kx)) calc(var(--bw) / 1.2 * var(--ky) * .25); flex: none; }
        .board-wrap { position: relative; width: var(--bw); aspect-ratio: 6 / 5; }
        .board { position: absolute; inset: 0; overflow: hidden; border-radius: 8px; cursor: pointer;
                 background: radial-gradient(ellipse at 50% 40%, rgba(255, 251, 240, .7), rgba(255, 236, 205, .55));
                 -webkit-backdrop-filter: blur(3px); backdrop-filter: blur(3px); box-shadow: inset 0 0 24px rgba(120, 60, 10, .25); transition: background 1.2s ease; }
        body.free .board { background: radial-gradient(ellipse at 50% 40%, rgba(40, 60, 130, .55), rgba(14, 20, 58, .62)); box-shadow: inset 0 0 28px rgba(0, 0, 30, .5); }
        .frame-art { position: absolute; left: var(--fl); top: var(--ft); width: var(--fw); height: var(--fh); z-index: 3; pointer-events: none;
                     filter: drop-shadow(0 14px 22px rgba(40, 14, 0, .45)); }
        .fxc { position: absolute; left: -40%; top: -40%; width: 180%; height: 180%; z-index: 6; pointer-events: none; }
        .sym { position: absolute; left: 0; top: 0; width: calc(100% / 6); height: calc(100% / 5); display: grid; place-items: center; z-index: 1; will-change: transform;
               container-type: size; }
        .sym > img, .sym .star { width: 90%; height: 90%; object-fit: contain; pointer-events: none; filter: drop-shadow(0 4px 3px rgba(60, 25, 0, .35)); }
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
        .rays-cell { position: absolute; width: calc(100% / 6 * 2.6); aspect-ratio: 1; translate: -50% -50%; z-index: 0; pointer-events: none; opacity: 0; }
        .float { position: absolute; padding: 4px 12px; border-radius: 999px; background: rgba(60, 22, 0, .8); color: var(--gold); font-size: clamp(16px, 2.2vw, 26px);
                 white-space: nowrap; border: 2px solid var(--gold); text-shadow: 0 2px 0 #000; z-index: 7; }
        .float.mult { font-size: clamp(30px, 4.5vw, 54px); background: rgba(120, 10, 30, .85); border-color: #ffd0d0; color: #fff; }
        .float.scatter-win { background: rgba(10, 70, 30, .82); }
        .fs-hud { white-space: nowrap; align-self: center; background: linear-gradient(180deg, #2f62c9, #1b3b86); border: 2px solid #9fc4ff; border-radius: 999px;
                  font-size: clamp(15px, 2.2vw, 22px); padding: 8px 22px; box-shadow: 0 6px 16px rgba(0, 0, 0, .4); }
        .fs-hud b { color: var(--gold); font-size: 1.15em; }

        /* ---- The bar ---- */
        .bar { position: relative; z-index: 5; flex: none; display: grid; grid-template-columns: 1fr auto auto auto 1fr; grid-template-areas: "bal bet spin win tools";
               align-items: center; gap: clamp(8px, 1.6vw, 22px); padding: 6px 14px max(8px, env(safe-area-inset-bottom)); margin: 0 -12px;
               background: linear-gradient(180deg, transparent, rgba(30, 12, 2, .82) 32%); }
        .meter { display: grid; text-align: center; min-width: 96px; }
        .meter small { font-size: 11px; letter-spacing: 1px; color: var(--gold); opacity: .9; }
        .meter span, .meter > b { font-size: clamp(16px, 1.8vw, 22px); font-variant-numeric: tabular-nums; }
        .bal { grid-area: bal; justify-self: start; text-align: left; }
        .winm { grid-area: win; }
        .winm #win { color: var(--gold); }
        .bet { grid-area: bet; display: flex; align-items: center; gap: 6px; }
        .round { width: 38px; height: 38px; border-radius: 50%; border: 2px solid var(--wood-hi); background: var(--panel); font-size: 22px; line-height: 1; padding: 0; }
        .spin { grid-area: spin; width: var(--spin); aspect-ratio: 1; border: 0; padding: 0; border-radius: 50%;
                background: var(--spin-art) center / contain no-repeat; filter: drop-shadow(0 8px 12px rgba(0, 0, 0, .5)); transition: transform .12s ease; }
        .spin:not(:disabled):hover { transform: scale(1.04); }
        .spin:not(:disabled):active { transform: scale(.95); }
        .spin.busy { animation: rot .9s linear infinite; }
        @keyframes rot { to { transform: rotate(360deg); } }
        .tools { grid-area: tools; justify-self: end; display: flex; gap: 8px; }
        .tool { position: relative; width: 40px; height: 40px; border-radius: 12px; border: 2px solid var(--wood-hi); background: var(--panel); padding: 0; display: grid; place-items: center; }
        .tool svg { width: 22px; height: 22px; }
        .tool[aria-pressed=true] { background: var(--leaf); border-color: #b7f0a0; }
        .tool .count { position: absolute; top: -8px; right: -8px; background: var(--orange); border-radius: 999px; font-size: 11px; padding: 1px 5px; }
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
        .confetti { position: fixed; top: 0; z-index: 21; pointer-events: none; }
        .toast { position: fixed; left: 50%; bottom: 118px; transform: translateX(-50%); z-index: 25; background: rgba(120, 20, 10, .92); border-radius: 12px; padding: 10px 16px; max-width: 92vw; text-align: center; }
        .status { position: fixed; left: 50%; top: 40%; transform: translate(-50%, -50%); z-index: 15; background: var(--panel); border-radius: 14px; padding: 12px 18px; text-align: center; }
        [hidden] { display: none !important; }

        @media (orientation: portrait) {
            :root { --top: clamp(40px, 9vh, 84px); }
            .stage { --spin: clamp(76px, 22vw, 96px); }
            .bg { background-image: var(--day-p); } .bg.night { background-image: var(--night-p); }
            .branch { top: 10vh; bottom: auto; width: clamp(160px, 42vw, 360px); }
            .top { position: static; flex-direction: column; align-items: center; height: var(--top); flex: none; }
            .top .logo { display: block; height: 100%; }
            body.free .top .logo { display: none; }
            .logo-side { display: none; }
            .play { flex-direction: column; gap: 6px; }
            .play::after { display: none; }
            .side { order: 2; flex-direction: row; align-items: stretch; width: min(96vw, 560px); }
            .side > .feature { flex: 1; min-width: 0; display: flex; flex-direction: column; justify-content: center; }
            .side > .feature.ante { display: grid; }
            /* The frame may run a little past the screen's edges, so the grid stays large. */
            .frame { --bw: min(calc(100vw / (1 + 2 * var(--kx)) + 8vw), calc((100dvh - var(--top) - 290px) / (1 + 1.25 * var(--ky)) * 1.2), 640px); }
            .bar { grid-template-columns: 1fr auto 1fr; grid-template-areas: "bal spin win" "bet spin tools"; row-gap: 6px; }
            .winm { justify-self: end; text-align: right; }
            .tools { display: grid; grid-template-columns: repeat(2, 34px); gap: 6px; }
            .tool { width: 34px; height: 34px; border-radius: 10px; }
            .tool svg { width: 18px; height: 18px; }
            .bet { justify-self: start; }
            .meter { min-width: 0; }
            .dialog.plaque { margin-top: 10vh; }
        }
        /* Short landscape screens (phones on their side): a smaller logo and compact panels. The
           ante's details stay in its label and in the rules. */
        @media (orientation: landscape) and (max-height: 520px) {
            .side { gap: 8px; }
            .logo-side { max-height: 17vh; width: auto; max-width: 100%; align-self: center; }
            .feature { padding: 7px; border-radius: 12px; }
            .buy { padding: 7px 8px 8px 6px; row-gap: 6px; }
            .buy-icon { width: 30px; }
            .ante-desc { display: none; }
            .feature-title { margin: 0; }
            .feature-note { margin-top: 5px; font-size: 10px; }
        }
        @media (prefers-reduced-motion: reduce) {
            .branch img, .lantern, .fireflies i, .overlay > .rays, .dialog.plaque .banner, .dialog.celebrate .banner { animation: none; }
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

<main class="stage">
    <header class="top">
        <img class="logo" src="{{ $theme['logo'] }}" alt="{{ $game?->name ?? 'Slot' }}">
        <div id="fs-hud" class="fs-hud" hidden>FREE SPINS <b id="fs-count"></b> &nbsp;·&nbsp; WIN <b id="fs-win"></b></div>
    </header>

    <section class="play">
        <aside class="side">
            <img class="logo logo-side" src="{{ $theme['logo'] }}" alt="{{ $game?->name ?? 'Slot' }}">
            <div id="buy-box" class="feature" hidden>
                <button id="buy-btn" class="buy" disabled>
                    <img class="buy-icon" src="{{ $theme['symbols']['S']['img'] }}" alt="">
                    <span class="buy-text">
                        <span class="buy-title">Buy<br>free spins</span>
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

        <div class="frame" style="{{ $frameVars }}">
            <div class="board-wrap">
                <div id="board" class="board" aria-label="Slot grid"><div id="fx" class="fx"></div></div>
                <img class="frame-art" src="{{ $theme['frame']['src'] }}" alt="">
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
        <div class="meter winm" aria-live="polite"><small>WIN</small><span id="win">0.00</span></div>
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
            <button id="info-btn" class="tool" aria-label="Paytable and rules" title="Paytable and rules">
                <svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.4" stroke-linecap="round"><circle cx="12" cy="12" r="9"/><path d="M12 11v6"/><path d="M12 7.5v.5"/></svg>
            </button>
        </div>
    </footer>
</main>

<div id="overlay" class="overlay" hidden></div>
<div id="toast" class="toast" hidden></div>
<div id="status" class="status">{{ $game ? 'Waiting for the casino…' : 'No slot game is published yet.' }}</div>

@if ($game)
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
