<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="referrer" content="no-referrer">
    <title>Games</title>
    @include('play.bridge-importmap')
    <style>
        /* Display type: Sora, served from public/fonts/sora (SIL Open Font License). */
        @font-face { font-family: "Lobby Sora"; font-weight: 400 800; font-style: normal; font-display: swap;
            src: url("{{ asset('fonts/sora/sora-latin-ext.woff2') }}") format("woff2");
            unicode-range: U+0100-02BA, U+02BD-02C5, U+02C7-02CC, U+02CE-02D7, U+02DD-02FF, U+0304, U+0308, U+0329, U+1D00-1DBF, U+1E00-1E9F, U+1EF2-1EFF, U+2020, U+20A0-20AB, U+20AD-20C0, U+2113, U+2C60-2C7F, U+A720-A7FF; }
        @font-face { font-family: "Lobby Sora"; font-weight: 400 800; font-style: normal; font-display: swap;
            src: url("{{ asset('fonts/sora/sora-latin.woff2') }}") format("woff2");
            unicode-range: U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+0304, U+0308, U+0329, U+2000-206F, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD; }

        /* The operator's colours (Operator::lobbyTheme) drive everything; the rest is mixed from them. */
        :root {
            --primary: {{ $theme['primary'] }}; --accent: {{ $theme['accent'] }}; --bg: {{ $theme['background'] }};
            --surface: color-mix(in srgb, var(--bg) 93%, #fff);
            --surface-2: color-mix(in srgb, var(--bg) 87%, #fff);
            --surface-3: color-mix(in srgb, var(--bg) 80%, #fff);
            --line: rgba(255, 255, 255, .075);
            --line-strong: rgba(255, 255, 255, .14);
            --text: #f4f6fa; --muted: color-mix(in srgb, #fff 60%, var(--bg)); --faint: color-mix(in srgb, #fff 40%, var(--bg));
            --on-primary: #0b0f14;
            --radius: 16px; --ease: cubic-bezier(.2, .7, .2, 1);
            --display: "Lobby Sora", Inter, ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            --cap: 54px; /* a tile's caption */
            color-scheme: dark;
        }
        * { box-sizing: border-box; }
        html { background: var(--bg); }
        html, body { margin: 0; min-height: 100%; color: var(--text);
                     font: 15px/1.45 Inter, ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
                     -webkit-font-smoothing: antialiased; -moz-osx-font-smoothing: grayscale; }
        /* Ambient light from the operator's colours, behind everything. */
        body::before { content: ""; position: fixed; inset: 0; z-index: -1; pointer-events: none;
                       background: radial-gradient(1100px 560px at 10% -12%, color-mix(in srgb, var(--primary) 14%, transparent), transparent 62%),
                                   radial-gradient(900px 520px at 100% -8%, color-mix(in srgb, var(--accent) 8%, transparent), transparent 60%); }
        button { font: inherit; color: inherit; }
        svg { display: block; flex: none; }
        [hidden] { display: none !important; }
        .wrap { max-width: 1320px; margin: 0 auto; padding: 0 24px; }

        /* ---------- Top bar ---------- */
        .topbar { position: sticky; top: 0; z-index: 10; background: color-mix(in srgb, var(--bg) 80%, transparent);
                  backdrop-filter: saturate(1.4) blur(18px); -webkit-backdrop-filter: saturate(1.4) blur(18px); border-bottom: 1px solid var(--line); }
        .topbar-row { position: relative; display: grid; grid-template-columns: auto minmax(0, 1fr) auto; align-items: center; gap: 32px; height: 72px; }
        .brand { display: flex; align-items: center; gap: 10px; min-width: 0; }
        .brand img { height: 32px; max-width: 170px; object-fit: contain; }
        .mark { height: 34px; width: 34px; }
        .wordmark { display: inline-flex; align-items: baseline; gap: 5px; font-family: var(--display); font-size: 17px; letter-spacing: -.015em; white-space: nowrap; }
        .wordmark b { font-weight: 700; }
        .wordmark span { font-weight: 500; opacity: .6; }

        .tabs { display: flex; align-self: stretch; gap: 2px; overflow-x: auto; scrollbar-width: none; }
        .tabs::-webkit-scrollbar { display: none; }
        .tab { position: relative; display: inline-flex; align-items: center; gap: 8px; padding: 0 14px; border: 0; background: none; cursor: pointer;
               color: var(--muted); font-size: 14.5px; font-weight: 600; white-space: nowrap; transition: color .2s; }
        .tab:hover { color: var(--text); }
        .tab[aria-pressed=true] { color: var(--text); }
        .tab[aria-pressed=true] svg { color: var(--primary); }
        .tab[aria-pressed=true]::after { content: ""; position: absolute; left: 14px; right: 14px; bottom: 0; height: 2px; border-radius: 2px 2px 0 0;
                                         background: var(--primary); box-shadow: 0 0 14px var(--primary); }
        .tab:focus-visible { outline: 2px solid var(--accent); outline-offset: -6px; border-radius: 10px; }

        .tools { display: flex; align-items: center; gap: 12px; }
        .search { position: relative; display: flex; align-items: center; width: 280px; }
        .search > svg { position: absolute; left: 13px; color: var(--muted); pointer-events: none; }
        .search input { width: 100%; height: 42px; padding: 0 40px 0 40px; border-radius: 12px; border: 0; outline: none; font-size: 14.5px;
                        background: var(--surface); color: var(--text); box-shadow: inset 0 0 0 1px var(--line); transition: box-shadow .15s, background .15s; }
        .search input::placeholder { color: var(--muted); }
        .search input:hover { background: var(--surface-2); }
        .search input:focus { background: var(--surface-2); box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--primary) 70%, transparent), 0 0 0 4px color-mix(in srgb, var(--primary) 18%, transparent); }
        .search input::-webkit-search-cancel-button { display: none; }
        .search kbd { position: absolute; right: 10px; font: 600 11px/1 inherit; color: var(--muted); border: 1px solid var(--line-strong); border-radius: 6px; padding: 4px 7px; }
        .search input:focus ~ kbd, .search input:not(:placeholder-shown) ~ kbd { display: none; }
        .search-close { display: none; }
        .icon-btn { display: none; width: 42px; height: 42px; place-items: center; border: 0; border-radius: 12px; cursor: pointer;
                    background: var(--surface); box-shadow: inset 0 0 0 1px var(--line); color: var(--text); }

        .balance { display: flex; align-items: center; gap: 10px; height: 44px; padding: 0 16px 0 6px; border-radius: 13px; flex-shrink: 0;
                   background: linear-gradient(180deg, var(--surface-2), var(--surface)); box-shadow: inset 0 0 0 1px var(--line), inset 0 1px 0 rgba(255,255,255,.05); }
        .balance-icon { width: 32px; height: 32px; border-radius: 9px; display: grid; place-items: center;
                        background: color-mix(in srgb, var(--primary) 18%, transparent); color: var(--primary); }
        .balance small { display: block; font-size: 10px; line-height: 1.1; letter-spacing: .12em; text-transform: uppercase; color: var(--muted); font-weight: 700; }
        .balance strong { display: block; font: 700 15px/1.25 var(--display); font-variant-numeric: tabular-nums; white-space: nowrap; }
        .balance em { font-style: normal; font-weight: 600; font-size: 12px; color: var(--muted); margin-left: 3px; }

        /* ---------- Content ---------- */
        #content { padding-top: 28px; min-height: 60vh; }
        .section { margin-top: 48px; }
        .section-head { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 16px; }
        .section-title { display: flex; align-items: center; gap: 12px; min-width: 0; }
        .section-icon { width: 34px; height: 34px; border-radius: 10px; display: grid; place-items: center; flex: none; color: var(--primary);
                        background: color-mix(in srgb, var(--primary) 13%, transparent); box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--primary) 24%, transparent); }
        .section-head h2 { margin: 0; font: 700 21px/1.2 var(--display); letter-spacing: -.015em; white-space: nowrap; }
        .section-head .note { color: var(--muted); font-size: 13.5px; white-space: nowrap; }
        .section-tools { display: flex; align-items: center; gap: 8px; flex: none; }
        .link { display: inline-flex; align-items: center; gap: 4px; height: 34px; padding: 0 6px 0 12px; border: 0; background: none; border-radius: 10px;
                color: var(--muted); font-size: 13.5px; font-weight: 600; cursor: pointer; transition: color .15s, background .15s; }
        .link:hover { color: var(--text); background: var(--surface); }
        .arrow { width: 34px; height: 34px; border-radius: 50%; display: grid; place-items: center; border: 0; cursor: pointer; color: var(--text);
                 background: var(--surface); box-shadow: inset 0 0 0 1px var(--line); transition: background .15s, opacity .15s; }
        .arrow:hover:not(:disabled) { background: var(--surface-3); }
        .arrow:disabled { opacity: .35; cursor: default; }

        /* Featured carousel */
        .hero { position: relative; border-radius: 24px; overflow: hidden; background: var(--surface); isolation: isolate;
                box-shadow: 0 0 0 1px var(--line), 0 40px 90px -45px rgba(0,0,0,.9); }
        .slides { display: grid; touch-action: pan-y; }
        .slide { grid-area: 1 / 1; position: relative; isolation: isolate; display: flex; align-items: center; min-height: 480px;
                 opacity: 0; visibility: hidden; transition: opacity .8s var(--ease), visibility 0s .8s; }
        .slide.active { opacity: 1; visibility: visible; z-index: 1; transition: opacity .8s var(--ease); }
        .slide-amb { position: absolute; inset: -90px; z-index: -3; background-size: cover; background-position: center;
                     filter: blur(70px) saturate(1.5); opacity: .5; transform: scale(1.15); }
        .slide-art { position: absolute; top: 0; right: 0; bottom: 0; aspect-ratio: 4 / 3; max-width: 68%; z-index: -2; overflow: hidden; container-type: inline-size;
                     -webkit-mask-image: linear-gradient(90deg, transparent 0, #000 30%); mask-image: linear-gradient(90deg, transparent 0, #000 30%); }
        .slide-art img { width: 100%; height: 100%; object-fit: cover; object-position: center top; display: block; transform: scale(1.06); transform-origin: 60% 40%; transition: transform 9s linear; }
        .slide.active .slide-art img { transform: scale(1); }
        .slide-shade { position: absolute; inset: 0; z-index: -1;
                       background: linear-gradient(90deg, color-mix(in srgb, var(--bg) 92%, transparent) 0%, color-mix(in srgb, var(--bg) 60%, transparent) 36%, transparent 60%),
                                   linear-gradient(0deg, color-mix(in srgb, var(--bg) 80%, transparent) 0, transparent 32%); }
        .slide-body { position: relative; padding: 44px 56px 104px; max-width: 600px; }
        .slide-body > * { opacity: 0; transform: translateY(16px); transition: opacity .7s var(--ease), transform .7s var(--ease); }
        .slide.active .slide-body > * { opacity: 1; transform: none; }
        .slide.active .slide-body > :nth-child(2) { transition-delay: .08s; }
        .slide.active .slide-body > :nth-child(3) { transition-delay: .16s; }
        .slide.active .slide-body > :nth-child(4) { transition-delay: .24s; }
        .eyebrow { display: flex; align-items: center; flex-wrap: wrap; gap: 10px; font-size: 13px; color: var(--muted); font-weight: 600; }
        .eyebrow .tag { display: inline-flex; align-items: center; gap: 6px; height: 26px; padding: 0 10px; border-radius: 8px; font-size: 11.5px; font-weight: 800;
                        letter-spacing: .1em; text-transform: uppercase; color: var(--on-primary); background: var(--primary);
                        box-shadow: 0 6px 20px -6px color-mix(in srgb, var(--primary) 80%, transparent); }
        .eyebrow .with-icon { display: inline-flex; align-items: center; gap: 6px; }
        .slide-title { margin: 16px 0 0; font: 800 clamp(34px, 4.4vw, 60px)/1 var(--display); letter-spacing: -.03em; text-wrap: balance;
                       text-shadow: 0 6px 40px rgba(0,0,0,.45); }
        .stats { display: flex; margin: 24px 0 30px; padding: 0; }
        .stat { padding: 0 24px; border-left: 1px solid var(--line-strong); }
        .stat:first-child { padding-left: 0; border-left: 0; }
        .stat dt { font-size: 11px; letter-spacing: .12em; text-transform: uppercase; color: var(--muted); font-weight: 700; }
        .stat dd { margin: 5px 0 0; font: 700 23px/1.1 var(--display); font-variant-numeric: tabular-nums; white-space: nowrap; }
        .stat.hi dd { background: linear-gradient(180deg, #fff 10%, color-mix(in srgb, var(--primary) 85%, #fff)); -webkit-background-clip: text; background-clip: text; color: transparent; }
        .cta { display: flex; flex-wrap: wrap; gap: 12px; }
        .btn-primary { display: inline-flex; align-items: center; gap: 10px; height: 52px; padding: 0 28px 0 24px; border: 0; border-radius: 14px; cursor: pointer;
                       background: linear-gradient(180deg, color-mix(in srgb, var(--primary) 88%, #fff), var(--primary) 60%);
                       color: var(--on-primary); font-weight: 800; font-size: 15.5px; letter-spacing: .01em;
                       box-shadow: inset 0 1px 0 rgba(255,255,255,.35), 0 14px 34px -10px color-mix(in srgb, var(--primary) 75%, transparent);
                       transition: transform .2s var(--ease), filter .2s, box-shadow .2s; }
        .btn-primary:hover { transform: translateY(-2px); filter: brightness(1.06); box-shadow: inset 0 1px 0 rgba(255,255,255,.35), 0 18px 40px -10px color-mix(in srgb, var(--primary) 85%, transparent); }
        .btn-primary:disabled { opacity: .5; cursor: not-allowed; transform: none; }
        .btn-glass { display: inline-flex; align-items: center; gap: 8px; height: 52px; padding: 0 22px; border: 0; border-radius: 14px; cursor: pointer;
                     font-weight: 700; font-size: 15px; background: rgba(255,255,255,.08); box-shadow: inset 0 0 0 1px var(--line-strong);
                     backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px); transition: background .2s; }
        .btn-glass:hover { background: rgba(255,255,255,.14); }
        .btn-primary:focus-visible, .btn-glass:focus-visible { outline: 2px solid var(--accent); outline-offset: 3px; }
        .hero-nav { position: absolute; left: 56px; right: 36px; bottom: 28px; z-index: 2; display: flex; align-items: flex-end; gap: 24px; }
        .hero-tabs { display: flex; gap: 14px; flex: 1; max-width: 680px; }
        .hero-tab { flex: 1; min-width: 0; padding: 6px 0 0; border: 0; background: none; text-align: left; cursor: pointer; color: var(--muted); }
        .hero-tab-name { display: block; margin-bottom: 9px; font-size: 12.5px; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; transition: color .2s; }
        .hero-tab.active .hero-tab-name, .hero-tab:hover .hero-tab-name { color: var(--text); }
        .hero-bar { display: block; height: 3px; border-radius: 3px; background: rgba(255,255,255,.16); overflow: hidden; }
        .hero-bar i { display: block; height: 100%; background: var(--primary); transform: scaleX(0); transform-origin: left; }
        .hero-tab.done .hero-bar i { transform: scaleX(1); opacity: .45; }
        .hero-tab.active .hero-bar i { animation: fill 7s linear forwards; }
        .hero:hover .hero-tab.active .hero-bar i, .hero:has(:focus-visible) .hero-tab.active .hero-bar i { animation-play-state: paused; }
        @keyframes fill { from { transform: scaleX(0); } to { transform: scaleX(1); } }
        .hero-arrows { display: flex; gap: 8px; }
        .glass-btn { width: 42px; height: 42px; border-radius: 50%; display: grid; place-items: center; border: 0; cursor: pointer; color: var(--text);
                     background: color-mix(in srgb, var(--bg) 50%, transparent); box-shadow: inset 0 0 0 1px var(--line-strong);
                     backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px); transition: background .2s; }
        .glass-btn:hover { background: color-mix(in srgb, var(--bg) 25%, rgba(255,255,255,.12)); }

        /* Game cards */
        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(196px, 1fr)); gap: 20px 18px; }
        .row { display: grid; grid-auto-flow: column; grid-auto-columns: 256px; gap: 18px; overflow-x: auto; overscroll-behavior-x: contain;
               scroll-snap-type: x proximity; scrollbar-width: none; padding: 10px 4px 24px; margin: -10px -4px -24px; scroll-padding: 0 4px; }
        .row::-webkit-scrollbar { display: none; }
        .row > * { scroll-snap-align: start; }
        .tile { display: block; width: 100%; padding: 0; border: 0; background: none; text-align: left; cursor: pointer; border-radius: var(--radius); }
        .media { position: relative; aspect-ratio: 4 / 3; border-radius: var(--radius); overflow: hidden; background: var(--surface-2); isolation: isolate;
                 container-type: inline-size; box-shadow: 0 0 0 1px var(--line), 0 12px 28px -18px rgba(0,0,0,.8);
                 transition: transform .3s var(--ease), box-shadow .3s var(--ease); }
        .media > img { width: 100%; height: 100%; object-fit: cover; display: block; transition: transform .5s var(--ease); }
        /* A glint that crosses the art on hover. */
        .media::after { content: ""; position: absolute; inset: 0; z-index: 3; pointer-events: none; transform: translateX(-120%);
                        background: linear-gradient(110deg, transparent 35%, rgba(255,255,255,.22) 50%, transparent 65%); }
        .play { position: absolute; inset: 0; z-index: 2; display: grid; place-items: center; opacity: 0; transition: opacity .25s;
                background: radial-gradient(circle at center, rgba(0,0,0,.15), rgba(0,0,0,.55)); }
        .play i { width: 56px; height: 56px; border-radius: 50%; display: grid; place-items: center; background: var(--primary); color: var(--on-primary);
                  transform: scale(.8); transition: transform .3s var(--ease);
                  box-shadow: 0 0 0 6px color-mix(in srgb, var(--primary) 28%, transparent), 0 10px 30px rgba(0,0,0,.5); }
        .play i svg { margin-left: 3px; }
        .tile:hover .media, .tile:focus-visible .media, .rank:hover .media, .rank:focus-visible .media {
            transform: translateY(-6px);
            box-shadow: 0 0 0 1px color-mix(in srgb, var(--primary) 65%, transparent), 0 22px 44px -18px color-mix(in srgb, var(--primary) 55%, rgba(0,0,0,.8)); }
        .tile:hover .media > img, .rank:hover .media > img { transform: scale(1.07); }
        .tile:hover .media::after, .rank:hover .media::after { transform: translateX(120%); transition: transform .8s var(--ease); }
        .tile:hover .play, .tile:focus-visible .play, .rank:hover .play, .rank:focus-visible .play { opacity: 1; }
        .tile:hover .play i, .tile:focus-visible .play i, .rank:hover .play i, .rank:focus-visible .play i { transform: scale(1); }
        .tile:focus-visible, .rank:focus-visible { outline: none; }
        .tile:focus-visible .media, .rank:focus-visible .media { outline: 2px solid var(--accent); outline-offset: 3px; }
        .badge { position: absolute; top: 10px; left: 10px; z-index: 2; display: inline-flex; align-items: center; gap: 4px; height: 22px; padding: 0 8px;
                 border-radius: 7px; font-size: 10.5px; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; }
        .badge-hot { color: #fff; background: linear-gradient(135deg, #ff5a3d, #ff1f6b); box-shadow: 0 6px 16px -6px rgba(255, 50, 80, .8); }
        .badge-maint { color: #e5e7eb; background: rgba(0,0,0,.7); backdrop-filter: blur(6px); }
        .rtp-chip { position: absolute; top: 10px; right: 10px; z-index: 2; height: 22px; padding: 0 8px; border-radius: 7px; display: inline-flex; align-items: center;
                    font-size: 11px; font-weight: 700; font-variant-numeric: tabular-nums; color: #fff; background: rgba(0,0,0,.6); backdrop-filter: blur(6px);
                    opacity: 0; transform: translateY(-4px); transition: opacity .25s, transform .25s var(--ease); }
        .tile:hover .rtp-chip, .rank:hover .rtp-chip { opacity: 1; transform: none; }
        .tile:disabled, .rank:disabled { cursor: not-allowed; }
        .tile:disabled .media, .rank:disabled .media { filter: grayscale(.75); opacity: .65; }
        .tile:disabled:hover .media, .rank:disabled:hover .media { transform: none; box-shadow: 0 0 0 1px var(--line); }
        .tile:disabled .play, .rank:disabled .play, .tile:disabled .rtp-chip, .rank:disabled .rtp-chip { display: none; }
        .tile:disabled:hover .media::after, .rank:disabled:hover .media::after { transform: translateX(-120%); }
        .caption { height: var(--cap); padding: 11px 2px 0; }
        .caption b { display: block; font-size: 15px; font-weight: 650; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; transition: color .2s; }
        .caption small { display: block; margin-top: 2px; font-size: 13px; color: var(--muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .tile:hover .caption b, .rank:hover .caption b { color: #fff; }

        /* A designed cover for games that have no art yet: their colour, their kind's icon, their name. */
        .cover { position: absolute; inset: 0; display: grid; place-content: center; justify-items: center; gap: 5cqi; padding: 8cqi; text-align: center;
                 overflow: hidden; isolation: isolate;
                 background: radial-gradient(circle at 50% 36%, hsl(var(--h) 95% 58% / .5), transparent 42%),
                             radial-gradient(120% 80% at 50% 125%, hsl(calc(var(--h) + 35) 90% 50% / .4), transparent 62%),
                             linear-gradient(160deg, hsl(var(--h) 55% 21%), hsl(calc(var(--h) - 18) 60% 7%)); }
        .cover::before { content: ""; position: absolute; inset: -60%; z-index: -1;
                         background: repeating-conic-gradient(from 0deg at 50% 50%, hsl(var(--h) 100% 85% / .09) 0deg 5deg, transparent 5deg 15deg);
                         -webkit-mask-image: radial-gradient(circle at 50% 50%, #000 0, transparent 46%); mask-image: radial-gradient(circle at 50% 50%, #000 0, transparent 46%); }
        .cover::after { content: ""; position: absolute; inset: 0; z-index: -1; box-shadow: inset 0 0 0 1px hsl(var(--h) 100% 80% / .12), inset 0 -40px 60px -30px rgba(0,0,0,.6);
                        background: radial-gradient(hsl(var(--h) 100% 90% / .12) 1px, transparent 1.6px) 0 0 / 14px 14px;
                        -webkit-mask-image: linear-gradient(180deg, transparent 40%, #000); mask-image: linear-gradient(180deg, transparent 40%, #000); }
        .cover-icon { color: hsl(var(--h) 100% 90%); filter: drop-shadow(0 0 14px hsl(var(--h) 100% 60% / .9)); }
        .cover-icon svg { width: 24cqi; height: 24cqi; }
        .cover-name { max-width: 92%; font: 800 10.5cqi/.95 var(--display); letter-spacing: -.01em; text-transform: uppercase; color: #fff; text-wrap: balance;
                      text-shadow: 0 2px 0 hsl(var(--h) 70% 22%), 0 0 26px hsl(var(--h) 100% 60% / .65); }

        /* Top games: big rank numerals behind the tiles. */
        .row-rank { grid-auto-columns: 330px; }
        .rank { position: relative; display: block; padding: 0 0 0 72px; border: 0; background: none; text-align: left; cursor: pointer; color: inherit; border-radius: var(--radius); }
        .rank.wide { padding-left: 118px; }
        .rank-num { position: absolute; left: 0; bottom: calc(var(--cap) - 8px); z-index: 0; font: 800 150px/.8 var(--display); letter-spacing: -.07em;
                    color: var(--bg); -webkit-text-stroke: 2px rgba(255,255,255,.3); user-select: none; transition: -webkit-text-stroke-color .25s; }
        .rank:hover .rank-num, .rank:focus-visible .rank-num { -webkit-text-stroke-color: var(--primary); }
        .rank-body { position: relative; z-index: 1; }

        /* Continue playing */
        .row-resume { grid-auto-columns: 292px; gap: 14px; }
        .resume { display: flex; align-items: center; gap: 14px; width: 100%; padding: 8px 14px 8px 8px; border: 0; border-radius: 16px; cursor: pointer; text-align: left;
                  background: var(--surface); box-shadow: inset 0 0 0 1px var(--line); transition: background .2s, box-shadow .2s, transform .25s var(--ease); }
        .resume:hover { background: var(--surface-2); box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--primary) 45%, transparent); transform: translateY(-2px); }
        .resume:focus-visible { outline: 2px solid var(--accent); outline-offset: 2px; }
        .resume-thumb { position: relative; width: 88px; aspect-ratio: 4 / 3; border-radius: 10px; overflow: hidden; flex: none; container-type: inline-size; background: var(--surface-2); }
        .resume-thumb img { width: 100%; height: 100%; object-fit: cover; display: block; }
        .resume-thumb .cover-name { display: none; }
        .resume-text { flex: 1; min-width: 0; }
        .resume-text b { display: block; font-size: 14.5px; font-weight: 650; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .resume-text small { display: block; margin-top: 2px; font-size: 12.5px; color: var(--muted); }
        .resume-go { width: 34px; height: 34px; border-radius: 50%; display: grid; place-items: center; flex: none; color: var(--primary);
                     background: color-mix(in srgb, var(--primary) 15%, transparent); transition: background .2s, color .2s; }
        .resume-go svg { margin-left: 2px; }
        .resume:hover .resume-go { background: var(--primary); color: var(--on-primary); }

        /* Category page header */
        .page-head { display: flex; align-items: center; gap: 18px; padding: 28px 30px; margin-bottom: 28px; border-radius: 22px; overflow: hidden; position: relative;
                     background: radial-gradient(500px 220px at 0% 0%, color-mix(in srgb, var(--primary) 22%, transparent), transparent 70%), var(--surface);
                     box-shadow: inset 0 0 0 1px var(--line); }
        .page-icon { width: 60px; height: 60px; border-radius: 18px; display: grid; place-items: center; flex: none; color: var(--on-primary);
                     background: linear-gradient(160deg, color-mix(in srgb, var(--primary) 85%, #fff), var(--primary));
                     box-shadow: inset 0 1px 0 rgba(255,255,255,.35), 0 14px 30px -12px color-mix(in srgb, var(--primary) 80%, transparent); }
        .page-head > :not(.page-art) { position: relative; z-index: 1; }
        /* The category's own art along the right of its header. */
        .page-art { position: absolute; top: 0; right: 0; bottom: 0; display: flex; gap: 3px; opacity: .9;
                    -webkit-mask-image: linear-gradient(90deg, transparent, #000 45%); mask-image: linear-gradient(90deg, transparent, #000 45%); }
        .page-art img { height: 100%; aspect-ratio: 4 / 3; object-fit: cover; display: block; }
        .page-head h1 { margin: 0; font: 800 30px/1.1 var(--display); letter-spacing: -.02em; }
        .page-head p { margin: 4px 0 0; color: var(--muted); }

        /* Loading placeholders */
        .skeleton { background: linear-gradient(100deg, var(--surface) 30%, var(--surface-2) 50%, var(--surface) 70%) 0 0 / 300% 100%; animation: shimmer 1.4s linear infinite; border-radius: var(--radius); }
        .skeleton.hero-skel { height: 480px; border-radius: 24px; }
        .skeleton.tile-skel { aspect-ratio: 4 / 3; }
        .skeleton.tab-skel { width: 92px; height: 18px; border-radius: 6px; align-self: center; margin: 0 10px; }
        .skeleton.line { height: 12px; margin-top: 12px; width: 60%; border-radius: 6px; }
        .skeleton.title { height: 22px; width: 220px; border-radius: 7px; margin-bottom: 18px; }
        @keyframes shimmer { to { background-position: -150% 0; } }

        /* Sections rise in, one after another, the first time the lobby shows. */
        .reveal { animation: rise .7s var(--ease) both; animation-delay: calc(var(--i) * 70ms); }
        @keyframes rise { from { opacity: 0; transform: translateY(18px); } }

        .empty { display: grid; place-items: center; text-align: center; gap: 10px; padding: 72px 16px; color: var(--muted); }
        .empty-icon { width: 72px; height: 72px; border-radius: 22px; display: grid; place-items: center; margin-bottom: 6px; color: var(--muted);
                      background: var(--surface); box-shadow: inset 0 0 0 1px var(--line); }
        .empty strong { color: var(--text); font: 700 18px/1.2 var(--display); }

        /* ---------- Footer ---------- */
        .foot { margin-top: 72px; }
        .foot-inner { padding: 32px 0 40px; border-top: 1px solid var(--line); }
        .trust { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 16px; }
        .trust-item { display: flex; align-items: center; gap: 14px; padding: 16px 18px; border-radius: 16px; background: var(--surface); box-shadow: inset 0 0 0 1px var(--line); }
        .trust-icon { width: 42px; height: 42px; border-radius: 12px; display: grid; place-items: center; flex: none; color: var(--primary);
                      background: color-mix(in srgb, var(--primary) 13%, transparent); font: 800 13px/1 var(--display); letter-spacing: -.02em; }
        .trust-item b { display: block; font-size: 14.5px; font-weight: 700; }
        .trust-item div span { display: block; margin-top: 1px; font-size: 13px; color: var(--muted); }
        .foot-bottom { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; margin-top: 24px; font-size: 12.5px; color: var(--faint); }
        .powered { display: inline-flex; align-items: center; gap: 8px; color: var(--muted); }
        .powered .mark { width: 22px; height: 22px; }
        .powered .wordmark { font-size: 13.5px; color: var(--text); }

        /* ---------- Game view ---------- */
        #game { position: fixed; inset: 0; display: none; flex-direction: column; background: #000; z-index: 20; }
        #game.shown { display: flex; }
        .gamebar { display: grid; grid-template-columns: 1fr auto 1fr; align-items: center; gap: 12px; height: 56px; padding: 0 10px;
                   background: color-mix(in srgb, var(--bg) 92%, #000); border-bottom: 1px solid var(--line); }
        .btn-ghost { justify-self: start; display: inline-flex; align-items: center; gap: 6px; height: 38px; padding: 0 14px 0 8px; border: 0; border-radius: 11px; cursor: pointer;
                     background: var(--surface); box-shadow: inset 0 0 0 1px var(--line); font-size: 14px; font-weight: 650; transition: background .15s; }
        .btn-ghost:hover { background: var(--surface-2); }
        .gamebar strong { font: 700 15px/1 var(--display); letter-spacing: -.01em; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .gamebar .balance { justify-self: end; height: 38px; padding-right: 12px; }
        .gamebar .balance-icon { width: 28px; height: 28px; }
        .gamebar .balance small { display: none; }
        .stage { position: relative; flex: 1; }
        /* The operator page expanded the game (fullscreen, or where there is none, as on
           iPhones, filling the window): the bar steps aside too. */
        body.game-expanded .gamebar { display: none; }
        /* Games with a Home control handle lobby navigation themselves. */
        body.game-has-home .gamebar { display: none; }
        .stage iframe { position: absolute; inset: 0; width: 100%; height: 100%; border: 0; }
        /* While a game loads: its art, its name and a progress line. */
        .loader { position: absolute; inset: 0; display: grid; place-items: center; overflow: hidden; isolation: isolate; background: var(--bg);
                  transition: opacity .45s var(--ease), visibility 0s; }
        .loader.gone { opacity: 0; visibility: hidden; pointer-events: none; transition: opacity .45s var(--ease), visibility 0s .45s; }
        .loader-bg { position: absolute; inset: -90px; z-index: -1; background-size: cover; background-position: center; filter: blur(60px) saturate(1.4); opacity: .45; }
        .loader-card { display: grid; justify-items: center; gap: 16px; padding: 20px; text-align: center; }
        .loader-art { position: relative; width: min(300px, 64vw); aspect-ratio: 4 / 3; border-radius: 20px; overflow: hidden; container-type: inline-size;
                      box-shadow: 0 0 0 1px var(--line-strong), 0 40px 80px -30px rgba(0,0,0,.9); animation: float 3.2s ease-in-out infinite; }
        .loader-art img { width: 100%; height: 100%; object-fit: cover; display: block; }
        .loader-art::after { content: ""; position: absolute; inset: 0; background: linear-gradient(110deg, transparent 35%, rgba(255,255,255,.25) 50%, transparent 65%);
                             animation: glint 2.4s var(--ease) infinite; }
        .loader-name { margin-top: 6px; color: #fff; font: 800 22px/1.15 var(--display); letter-spacing: -.02em; }
        .loader-track { width: 200px; height: 4px; border-radius: 4px; background: rgba(255,255,255,.12); overflow: hidden; }
        .loader-track i { display: block; width: 40%; height: 100%; border-radius: 4px; background: var(--primary); box-shadow: 0 0 12px var(--primary);
                          animation: sweep 1.25s var(--ease) infinite; }
        .loader small { display: inline-flex; align-items: center; gap: 6px; color: var(--muted); font-size: 12.5px; font-weight: 600; }
        @keyframes float { 50% { transform: translateY(-8px); } }
        @keyframes glint { from { transform: translateX(-120%); } 60%, to { transform: translateX(120%); } }
        @keyframes sweep { from { transform: translateX(-110%); } to { transform: translateX(260%); } }

        /* ---------- Ended session ---------- */
        .expired { display: none; position: fixed; inset: 0; z-index: 30; align-items: center; justify-content: center; padding: 20px; background: var(--bg); }
        .expired.shown { display: flex; }
        .expired .card { max-width: 400px; text-align: center; padding: 40px 32px; border-radius: 24px; background: var(--surface); box-shadow: inset 0 0 0 1px var(--line), 0 40px 80px -40px #000; }
        .expired .icon { width: 60px; height: 60px; margin: 0 auto 18px; border-radius: 18px; display: grid; place-items: center;
                         background: color-mix(in srgb, var(--primary) 16%, transparent); color: var(--primary); }
        .expired h2 { margin: 0 0 8px; font: 700 21px/1.25 var(--display); letter-spacing: -.01em; }
        .expired p { margin: 0; color: var(--muted); }

        @media (max-width: 1100px) {
            .slide-art { max-width: 62%; }
            .row-rank { grid-auto-columns: 300px; }
            /* The search box folds into a button, leaving the categories room; it opens over the bar. */
            .topbar-row { gap: 20px; }
            .search { display: none; }
            .icon-btn { display: grid; }
            .topbar.searching .search { display: flex; position: absolute; left: 0; right: 0; top: 15px; width: auto; z-index: 3; }
            .topbar.searching .search input { padding-right: 48px; }
            .topbar.searching .search kbd { display: none; }
            .topbar.searching .search-close { display: grid; position: absolute; right: 4px; width: 36px; height: 36px; place-items: center; border: 0; background: none; color: var(--muted); cursor: pointer; }
            .topbar.searching .brand, .topbar.searching .tabs, .topbar.searching .tools > :not(.search) { visibility: hidden; }
        }
        @media (max-width: 900px) {
            .wrap { padding: 0 16px; }
            .topbar-row { grid-template-columns: minmax(0, 1fr) auto; grid-template-areas: "brand tools" "nav nav"; height: auto; column-gap: 12px; row-gap: 0; }
            .brand { grid-area: brand; height: 60px; }
            .tools { grid-area: tools; gap: 8px; }
            .tabs { grid-area: nav; gap: 8px; margin: 0 -16px; padding: 0 16px 12px; }
            .tab { height: 36px; padding: 0 14px; border-radius: 999px; font-size: 14px; box-shadow: inset 0 0 0 1px var(--line); }
            .tab[aria-pressed=true] { background: var(--primary); color: var(--on-primary); box-shadow: none; }
            .tab[aria-pressed=true] svg { color: inherit; }
            .tab[aria-pressed=true]::after { display: none; }
            .topbar.searching .search { top: 9px; }
            /* The categories stay usable while searching on a phone (they sit on their own row). */
            .topbar.searching .tabs { visibility: visible; }
            .balance { height: 42px; padding-right: 12px; }
            .balance small { display: none; }
            .balance-icon { width: 30px; height: 30px; }
        }
        @media (max-width: 760px) {
            #content { padding-top: 14px; }
            .section { margin-top: 36px; }
            .section-head h2 { font-size: 18px; }
            .section-head .note, .section-tools .arrow { display: none; }
            .hero { border-radius: 20px; }
            .slide { flex-direction: column; align-items: stretch; min-height: 0; }
            .slide-art { position: relative; width: 100%; max-width: none; aspect-ratio: 4 / 3;
                         -webkit-mask-image: linear-gradient(180deg, #000 50%, transparent 96%); mask-image: linear-gradient(180deg, #000 50%, transparent 96%); }
            .slide-shade { background: linear-gradient(0deg, color-mix(in srgb, var(--bg) 70%, transparent) 0, transparent 45%); }
            .slide-body { margin-top: -36px; padding: 0 20px 62px; max-width: none; }
            .slide-title { font-size: 32px; margin-top: 12px; }
            .stats { margin: 18px 0 22px; }
            .stat { padding: 0 16px; }
            .stat dd { font-size: 18px; }
            .cta .btn-primary { flex: 1; justify-content: center; }
            .cta .btn-glass { display: none; }
            .hero-nav { left: 20px; right: 20px; bottom: 22px; }
            .hero-tabs { gap: 8px; }
            .hero-tab-name, .hero-arrows { display: none; }
            .grid { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px 12px; }
            .row { grid-auto-columns: 64%; gap: 12px; margin: -10px -16px -24px; padding: 10px 16px 24px; scroll-padding: 0 16px; }
            .row-rank { grid-auto-columns: 80%; }
            .row-resume { grid-auto-columns: 82%; }
            .rank { padding-left: 54px; }
            .rank.wide { padding-left: 92px; }
            .rank-num { font-size: 118px; }
            .caption b { font-size: 14px; }
            .caption small { font-size: 12.5px; }
            .page-head { padding: 20px; gap: 14px; }
            .page-icon { width: 48px; height: 48px; border-radius: 14px; }
            .page-art { opacity: .45; }
            .page-head h1 { font-size: 24px; }
            .trust { grid-template-columns: 1fr; gap: 10px; }
            .foot { margin-top: 56px; }
            .foot-inner { padding-top: 24px; }
            .gamebar .balance { display: none; }
        }
        @media (max-width: 480px) {
            .brand { overflow: hidden; }
            .wordmark span, .tools .balance-icon { display: none; }
            .balance { padding-left: 12px; }
        }
        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { transition: none !important; animation: none !important; }
            .hero-tab.active .hero-bar i { transform: scaleX(1); }
        }
    </style>
</head>
<body>
    {{-- The provider's crown, used where the operator has no logo of its own and in the footer. --}}
    <svg width="0" height="0" style="position:absolute" aria-hidden="true">
        <defs>
            <linearGradient id="mg-bg" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#4f46e5"/><stop offset="1" stop-color="#312e81"/></linearGradient>
            <linearGradient id="mg-gold" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#fde68a"/><stop offset="1" stop-color="#f59e0b"/></linearGradient>
            <symbol id="mg-mark" viewBox="0 0 32 32">
                <rect width="32" height="32" rx="8" fill="url(#mg-bg)"/>
                <path d="M8.5 20.5V12.2l4.2 3.6L16 9.9l3.3 5.9 4.2-3.6v8.3z" fill="url(#mg-gold)"/>
                <rect x="8.5" y="21.8" width="15" height="2.4" rx="1.2" fill="url(#mg-gold)"/>
                <circle cx="8.5" cy="11.4" r="1.4" fill="#fde68a"/><circle cx="16" cy="8.9" r="1.5" fill="#fde68a"/><circle cx="23.5" cy="11.4" r="1.4" fill="#fde68a"/>
            </symbol>
        </defs>
    </svg>

    <div id="lobby">
        <header class="topbar" id="topbar">
            <div class="wrap topbar-row">
                <div class="brand">
                    @if ($theme['logo_url'])
                        <img src="{{ $theme['logo_url'] }}" alt="" id="logo">
                    @else
                        <svg class="mark" aria-hidden="true"><use href="#mg-mark"/></svg>
                        <span class="wordmark"><b>Majestic</b><span>Games</span></span>
                    @endif
                </div>
                <nav class="tabs" id="tabs" aria-label="Categories"></nav>
                <div class="tools">
                    <label class="search">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                        <input type="search" id="search" placeholder="Search games" aria-label="Search games" autocomplete="off">
                        <kbd aria-hidden="true">/</kbd>
                        <button type="button" class="search-close" id="search-close" aria-label="Close search"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg></button>
                    </label>
                    <button type="button" class="icon-btn" id="search-toggle" aria-label="Search games"><svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg></button>
                    <div class="balance" id="balance" hidden>
                        <span class="balance-icon" aria-hidden="true"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 7V5.5A1.5 1.5 0 0 0 15.5 4H6a3 3 0 0 0-3 3v10a3 3 0 0 0 3 3h12.5a2.5 2.5 0 0 0 2.5-2.5v-8A2.5 2.5 0 0 0 18.5 7H6"/><circle cx="16.5" cy="13.5" r="1.2" fill="currentColor"/></svg></span>
                        <span><small>Balance</small><strong id="balance-amount"></strong></span>
                    </div>
                </div>
            </div>
        </header>
        <main class="wrap" id="content" aria-live="polite"></main>
        <footer class="wrap foot"><div class="foot-inner">
            <div class="trust">
                <div class="trust-item">
                    <span class="trust-icon" aria-hidden="true"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l7.5 3v5.5c0 4.6-3.1 8.4-7.5 9.5-4.4-1.1-7.5-4.9-7.5-9.5V6z"/><path d="m8.8 12 2.2 2.2 4.4-4.4"/></svg></span>
                    <div><b>Provably fair</b><span>Every result can be verified</span></div>
                </div>
                <div class="trust-item">
                    <span class="trust-icon" aria-hidden="true"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M13 2.5 4.5 13.5H12l-1 8 8.5-11H12z"/></svg></span>
                    <div><b>Instant play</b><span>Any device, nothing to install</span></div>
                </div>
                <div class="trust-item">
                    <span class="trust-icon" aria-hidden="true">18+</span>
                    <div><b>Play responsibly</b><span>Know your limits. Adults only.</span></div>
                </div>
            </div>
            <div class="foot-bottom">
                <span>Gambling can be addictive. Please play responsibly.</span>
                <span class="powered">Powered by <svg class="mark" aria-hidden="true"><use href="#mg-mark"/></svg><span class="wordmark"><b>Majestic</b><span>Games</span></span></span>
            </div>
        </div></footer>
    </div>

    <div id="game" aria-label="Game">
        <div class="gamebar">
            <button id="back" class="btn-ghost"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>Lobby</button>
            <strong id="game-name"></strong>
            <div class="balance" id="game-balance" hidden>
                <span class="balance-icon" aria-hidden="true"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 7V5.5A1.5 1.5 0 0 0 15.5 4H6a3 3 0 0 0-3 3v10a3 3 0 0 0 3 3h12.5a2.5 2.5 0 0 0 2.5-2.5v-8A2.5 2.5 0 0 0 18.5 7H6"/><circle cx="16.5" cy="13.5" r="1.2" fill="currentColor"/></svg></span>
                <span><small>Balance</small><strong id="game-balance-amount"></strong></span>
            </div>
        </div>
        <div class="stage">
            <iframe id="frame" title="Game" allow="fullscreen; autoplay"></iframe>
            <div class="loader gone" id="spinner" aria-hidden="true">
                <div class="loader-bg" id="loader-bg"></div>
                <div class="loader-card">
                    <div class="loader-art" id="loader-art"></div>
                    <div class="loader-name" id="loader-name"></div>
                    <div class="loader-track"><i></i></div>
                    <small><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l7.5 3v5.5c0 4.6-3.1 8.4-7.5 9.5-4.4-1.1-7.5-4.9-7.5-9.5V6z"/><path d="m8.8 12 2.2 2.2 4.4-4.4"/></svg>Provably fair</small>
                </div>
            </div>
        </div>
    </div>

    <div class="expired" id="expired" role="alert">
        <div class="card">
            <div class="icon"><svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg></div>
            <h2 id="expired-title">This session has ended</h2>
            <p>Please open the games again from the casino.</p>
        </div>
    </div>

    <script type="module">
        import { startPlayHost } from @json(asset('js/orange/play-host.js').'?v='.filemtime(public_path('js/orange/play-host.js')));

        const $ = (id) => document.getElementById(id);
        let lobby = null; // null until the first answer: placeholders show meanwhile
        let tab = 'all';
        let firstPaint = true; // sections rise in only the first time
        let currency = '';

        // Line icons (24×24, stroked). Static markup only: never data.
        const ICONS = {
            home: '<path d="M3.5 10.5 12 4l8.5 6.5V19a1.5 1.5 0 0 1-1.5 1.5h-4.5v-6h-5v6H5A1.5 1.5 0 0 1 3.5 19z"/>',
            slots: '<rect x="2.5" y="5" width="19" height="14" rx="3"/><path d="M8.83 5v14M15.17 5v14"/><path d="M4.6 10h2.2l-1.3 4M11 10h2.2l-1.3 4M17.4 10h2.2l-1.3 4"/>',
            crash: '<path d="M3 17.5 9 11.5l4 4 8-8"/><path d="M15 7.5h6v6"/>',
            dice: '<rect x="3.5" y="3.5" width="17" height="17" rx="4"/><circle cx="8.5" cy="8.5" r="1.3" fill="currentColor" stroke="none"/><circle cx="12" cy="12" r="1.3" fill="currentColor" stroke="none"/><circle cx="15.5" cy="15.5" r="1.3" fill="currentColor" stroke="none"/>',
            cards: '<rect x="3" y="4.5" width="12" height="16" rx="2"/><path d="M18 6.5 21 7.4a1.5 1.5 0 0 1 1 1.8l-3.3 11.2"/><path d="M9 9.5c1.5 1.5 3 2.6 3 4a2 2 0 0 1-3 1.7 2 2 0 0 1-3-1.7c0-1.4 1.5-2.5 3-4z"/>',
            live: '<circle cx="12" cy="12" r="2.5"/><path d="M7.8 7.8a6 6 0 0 0 0 8.4M16.2 7.8a6 6 0 0 1 0 8.4M4.9 4.9a10 10 0 0 0 0 14.2M19.1 4.9a10 10 0 0 1 0 14.2"/>',
            bolt: '<path d="M13 2.5 4.5 13.5H12l-1 8 8.5-11H12z"/>',
            trophy: '<path d="M7.5 4h9v5.5a4.5 4.5 0 0 1-9 0z"/><path d="M7.5 6H4.5a3 3 0 0 0 3.2 4M16.5 6h3a3 3 0 0 1-3.2 4M12 14v3.5M8 20.5h8"/>',
            game: '<path d="M7 7.5h10a5 5 0 0 1 5 5v1.5a3.5 3.5 0 0 1-6.3 2.1L14.5 14.5h-5l-1.2 1.6A3.5 3.5 0 0 1 2 14v-1.5a5 5 0 0 1 5-5z"/><path d="M7.5 11v3M6 12.5h3"/><circle cx="16" cy="12" r=".9" fill="currentColor" stroke="none"/><circle cx="18" cy="14" r=".9" fill="currentColor" stroke="none"/>',
            flame: '<path fill="currentColor" stroke="none" d="M12 22c4.2 0 7-2.8 7-6.9 0-3.5-2.3-5.6-3.8-8.2-.5 1.9-1.5 3.1-2.8 3.6.4-3.4-1.2-6.5-4.1-8.5.2 3.9-3.3 6.3-3.3 10.9C5 19.3 7.8 22 12 22z"/>',
            fire: '<path d="M12 21.5c4 0 6.5-2.6 6.5-6.4 0-3.3-2.2-5.3-3.6-7.7-.5 1.8-1.4 2.9-2.6 3.4.4-3.2-1.1-6.1-3.8-8 .2 3.7-3 5.9-3 10.2 0 4 2.5 8.5 6.5 8.5z"/>',
            history: '<path d="M3.5 12a8.5 8.5 0 1 0 2.5-6"/><path d="M3.5 4.5V9H8"/><path d="M12 8v4.2l3 1.8"/>',
            grid: '<rect x="3.5" y="3.5" width="7" height="7" rx="2"/><rect x="13.5" y="3.5" width="7" height="7" rx="2"/><rect x="3.5" y="13.5" width="7" height="7" rx="2"/><rect x="13.5" y="13.5" width="7" height="7" rx="2"/>',
            shield: '<path d="M12 3l7.5 3v5.5c0 4.6-3.1 8.4-7.5 9.5-4.4-1.1-7.5-4.9-7.5-9.5V6z"/><path d="m8.8 12 2.2 2.2 4.4-4.4"/>',
            wrench: '<path d="M14.5 6.5a4 4 0 0 0 5 5L12 19a2.1 2.1 0 0 1-3-3z"/><path d="M14.5 6.5 17 4a4 4 0 0 1 3 3l-2.5 2.5"/>',
            search: '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
            left: '<path d="m15 18-6-6 6-6"/>',
            right: '<path d="m9 18 6-6-6-6"/>',
        };
        const icon = (name, size = 18) => `<svg width="${size}" height="${size}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${ICONS[name] ?? ICONS.game}</svg>`;
        const PLAY_ICON = '<svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M8 5.5v13a1 1 0 0 0 1.5.86l10.4-6.5a1 1 0 0 0 0-1.72L9.5 4.64A1 1 0 0 0 8 5.5Z"/></svg>';
        const PLAY_SMALL = PLAY_ICON.replace(/width="22" height="22"/, 'width="16" height="16"');

        // Each category's icon, by its slug; anything else gets a game pad.
        const CATEGORY_ICONS = { slots: 'slots', slot: 'slots', crash: 'crash', originals: 'dice', dice: 'dice', table: 'cards', 'table-games': 'cards',
            cards: 'cards', live: 'live', 'live-casino': 'live', instant: 'bolt', jackpots: 'trophy', jackpot: 'trophy' };
        const TYPE_ICONS = { slot: 'slots', crash: 'crash', dice: 'dice' };
        const iconFor = (slug) => CATEGORY_ICONS[slug] ?? 'game';

        /** Element helper: el('div', { class: 'x' }, child, 'text', …). */
        function el(tag, attrs = {}, ...children) {
            const node = document.createElement(tag);
            for (const [k, v] of Object.entries(attrs)) {
                if (v === false || v == null) continue;
                if (k === 'class') node.className = v;
                else if (k === 'html') node.innerHTML = v; // static markup only, never data
                else if (k.startsWith('on')) node.addEventListener(k.slice(2), v);
                else node.setAttribute(k, v === true ? '' : v);
            }
            node.append(...children.filter((c) => c != null && c !== false));
            return node;
        }

        /** The operator's colours, with text on the primary colour dark or light, whichever reads. */
        function applyTheme(theme) {
            const root = document.documentElement.style;
            root.setProperty('--primary', theme.primary);
            root.setProperty('--accent', theme.accent);
            root.setProperty('--bg', theme.background);
            const n = parseInt(String(theme.primary).slice(1), 16);
            if (Number.isNaN(n)) return;
            const [r, g, b] = [(n >> 16) & 255, (n >> 8) & 255, n & 255]
                .map((c) => { c /= 255; return c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4; });
            root.setProperty('--on-primary', 0.2126 * r + 0.7152 * g + 0.0722 * b > 0.179 ? '#0b0f14' : '#ffffff');
        }
        applyTheme(@json($theme));

        function showLobby() {
            // Expanded for the game: give the operator page its header (and close button) back.
            if (document.body.classList.contains('game-expanded')) host?.outer.send('exit_fullscreen', {});
            host?.close();
            $('game').classList.remove('shown');
            $('lobby').hidden = false;
            refresh();
        }

        const host = startPlayHost({
            gameCode: null,
            frameUrl: null,
            bearer: @json($bearer),
            storageKey: 'orange.lobby',
            outerOrigins: @json($outerOrigins),
            playApi: @json(url('/play-api')),
            frame: $('frame'),
            onExpired(reason) {
                $('lobby').hidden = true;
                $('game').classList.remove('shown');
                // Unload the game too (its sounds stop); `host` may not exist yet here.
                $('frame').src = 'about:blank';
                if (reason === 'idle') $('expired-title').textContent = 'Your session timed out';
                $('expired').classList.add('shown');
            },
            onBackToLobby: showLobby,
            onGameReady(payload) {
                document.body.classList.toggle('game-has-home', payload?.home === true);
            },
            onFullscreen(active) {
                document.body.classList.toggle('game-expanded', active);
            },
            // The casino's page told us the balance after /session answered (e.g. after a deposit).
            onBalance(balance) {
                showBalance(balance);
            },
        });

        function open(game) {
            if (!game.playable) return;
            $('game-name').textContent = game.name;
            $('loader-name').textContent = game.name;
            $('loader-art').replaceChildren(art(game, { eager: true }));
            $('loader-bg').style.backgroundImage = game.thumbnail_url ? `url(${JSON.stringify(game.thumbnail_url)})` : 'none';
            $('spinner').classList.remove('gone');
            // The bar shows until the game says it has its own Home.
            document.body.classList.remove('game-has-home');
            $('lobby').hidden = true;
            $('game').classList.add('shown');
            host.open(game.code, game.frame_url, game.name);
        }
        $('frame').addEventListener('load', () => {
            if ($('frame').getAttribute('src') !== 'about:blank') $('spinner').classList.add('gone');
        });

        // ---------- Formatting ----------
        const categoryName = (slug) => lobby.categories.find((c) => c.slug === slug)?.name ?? null;
        const pct = (value) => (value ? `${(Number(value) * 100).toFixed(2)}%` : null);
        const maxWin = (game) => {
            const x = Number(game.max_multiplier);
            return x > 0 ? `${new Intl.NumberFormat('en-US', { maximumFractionDigits: x >= 100 ? 0 : 2 }).format(x)}×` : null;
        };
        const subtitle = (game) => [categoryName(game.category), maxWin(game) ? `Win up to ${maxWin(game)}` : (game.rtp ? `RTP ${pct(game.rtp)}` : null)]
            .filter(Boolean).join(' · ');
        const plural = (n) => `${n} ${n === 1 ? 'game' : 'games'}`;

        // ---------- Pieces ----------
        /** A deterministic hue for a game without art: its kind's colour, else one from its code. */
        function hue(game) {
            const byType = { crash: 22, dice: 262, slot: 318 };
            if (byType[game.type] != null) return byType[game.type];
            let h = 0;
            for (const ch of game.code) h = (h * 31 + ch.charCodeAt(0)) % 360;
            return h;
        }

        function cover(game) {
            const node = el('div', { class: 'cover', 'aria-hidden': 'true' },
                el('div', { class: 'cover-icon', html: icon(TYPE_ICONS[game.type] ?? iconFor(game.category), 48) }),
                el('div', { class: 'cover-name' }, game.name));
            node.style.setProperty('--h', hue(game));
            return node;
        }

        /** The game's art, or its designed cover (also if the art fails to load). */
        function art(game, { eager = false } = {}) {
            if (!game.thumbnail_url) return cover(game);
            return el('img', { src: game.thumbnail_url, alt: '', loading: eager ? 'eager' : 'lazy', decoding: 'async',
                onerror: (e) => e.currentTarget.replaceWith(cover(game)) });
        }

        let hot = new Set();
        function media(game, { badge = true } = {}) {
            return el('div', { class: 'media' },
                art(game),
                !game.playable ? el('span', { class: 'badge badge-maint', html: icon('wrench', 12) }, 'Maintenance')
                    : badge && hot.has(game.code) ? el('span', { class: 'badge badge-hot', html: icon('flame', 12) }, 'Hot') : null,
                game.rtp ? el('span', { class: 'rtp-chip' }, `RTP ${pct(game.rtp)}`) : null,
                el('div', { class: 'play', 'aria-hidden': 'true' }, el('i', { html: PLAY_ICON })),
            );
        }

        const caption = (game) => el('div', { class: 'caption' }, el('b', {}, game.name), el('small', {}, subtitle(game) || ' '));
        const label = (game) => (game.playable ? `Play ${game.name}` : `${game.name} (maintenance)`);

        function tile(game) {
            return el('button', { class: 'tile', disabled: !game.playable, 'aria-label': label(game), onclick: () => open(game) }, media(game), caption(game));
        }

        function ranked(game, i) {
            return el('button', { class: `rank${i >= 9 ? ' wide' : ''}`, disabled: !game.playable, 'aria-label': `Number ${i + 1}: ${label(game)}`, onclick: () => open(game) },
                el('span', { class: 'rank-num', 'aria-hidden': 'true' }, String(i + 1)),
                el('div', { class: 'rank-body' }, media(game, { badge: false }), caption(game)));
        }

        function resume(game) {
            return el('button', { class: 'resume', 'aria-label': `Continue ${game.name}`, onclick: () => open(game) },
                el('div', { class: 'resume-thumb' }, art(game)),
                el('div', { class: 'resume-text' }, el('b', {}, game.name), el('small', {}, categoryName(game.category) ?? 'Continue playing')),
                el('i', { class: 'resume-go', html: PLAY_SMALL }));
        }

        /** A horizontally scrolling row, with arrows in its section's head (wide screens). */
        function row(className, items) {
            const node = el('div', { class: `row ${className}` }, ...items);
            const prev = el('button', { class: 'arrow', 'aria-label': 'Scroll left', html: icon('left', 18), onclick: () => node.scrollBy({ left: -node.clientWidth * 0.85, behavior: 'smooth' }) });
            const next = el('button', { class: 'arrow', 'aria-label': 'Scroll right', html: icon('right', 18), onclick: () => node.scrollBy({ left: node.clientWidth * 0.85, behavior: 'smooth' }) });
            node.sync = () => {
                const max = node.scrollWidth - node.clientWidth - 2;
                prev.hidden = next.hidden = max <= 0;
                prev.disabled = node.scrollLeft <= 2;
                next.disabled = node.scrollLeft >= max;
            };
            node.addEventListener('scroll', node.sync, { passive: true });
            node.arrows = [prev, next];
            return node;
        }

        function section(title, { icon: ic, note, action, body }) {
            return el('section', { class: 'section' },
                el('div', { class: 'section-head' },
                    el('div', { class: 'section-title' },
                        ic ? el('span', { class: 'section-icon', html: icon(ic, 18) }) : null,
                        el('h2', {}, title),
                        note ? el('span', { class: 'note' }, note) : null),
                    el('div', { class: 'section-tools' }, action ?? null, ...(body.arrows ?? []))),
                body);
        }

        const viewAll = (slug) => el('button', { class: 'link', onclick: () => selectTab(slug), html: `View all${icon('right', 16)}` });

        function empty(title, text, ic = 'game') {
            return el('div', { class: 'empty' }, el('div', { class: 'empty-icon', html: icon(ic, 30) }), el('strong', {}, title), el('span', {}, text));
        }

        // ---------- Featured carousel ----------
        let carousel = null;

        function hero(games) {
            const n = games.length;
            const slides = games.map((game, i) => {
                const stats = [
                    maxWin(game) ? ['Max win', maxWin(game), true] : null,
                    game.rtp ? ['RTP', pct(game.rtp)] : null,
                    categoryName(game.category) ? ['Game type', categoryName(game.category)] : null,
                ].filter(Boolean);
                const sameKind = lobby.games.filter((g) => g.category === game.category).length;
                const slide = el('article', { class: 'slide', 'aria-roledescription': 'slide', 'aria-label': `${i + 1} of ${n}: ${game.name}` },
                    game.thumbnail_url ? el('div', { class: 'slide-amb' }) : null,
                    el('div', { class: 'slide-art' }, art(game, { eager: i === 0 })),
                    el('div', { class: 'slide-shade' }),
                    el('div', { class: 'slide-body' },
                        el('div', { class: 'eyebrow' },
                            el('span', { class: 'tag' }, lobby.popular[0] === game.code ? 'Most played' : 'Featured'),
                            el('span', { class: 'with-icon', html: icon('shield', 15) }, 'Provably fair')),
                        el('h2', { class: 'slide-title' }, game.name),
                        stats.length ? el('dl', { class: 'stats' }, ...stats.map(([k, v, hi]) => el('div', { class: `stat${hi ? ' hi' : ''}` }, el('dt', {}, k), el('dd', {}, v)))) : el('div', { class: 'stats' }),
                        el('div', { class: 'cta' },
                            el('button', { class: 'btn-primary', disabled: !game.playable, onclick: () => open(game), html: PLAY_ICON + (game.playable ? 'Play now' : 'In maintenance') }),
                            game.category && sameKind > 1 && lobby.categories.length > 1
                                ? el('button', { class: 'btn-glass', onclick: () => selectTab(game.category) }, `More ${categoryName(game.category)}`) : null)),
                );
                if (game.thumbnail_url) slide.querySelector('.slide-amb').style.backgroundImage = `url(${JSON.stringify(game.thumbnail_url)})`;
                return slide;
            });

            let index = 0;
            const tabs = games.map((game, i) => el('button', { class: 'hero-tab', 'aria-label': `Show ${game.name}`, onclick: () => go(i) },
                el('span', { class: 'hero-tab-name' }, game.name), el('span', { class: 'hero-bar' }, el('i'))));
            const root = el('section', { class: 'hero', 'aria-roledescription': 'carousel', 'aria-label': 'Featured games' },
                el('div', { class: 'slides' }, ...slides),
                n > 1 ? el('div', { class: 'hero-nav' },
                    el('div', { class: 'hero-tabs' }, ...tabs),
                    el('div', { class: 'hero-arrows' },
                        el('button', { class: 'glass-btn', 'aria-label': 'Previous game', html: icon('left', 20), onclick: () => go(index - 1) }),
                        el('button', { class: 'glass-btn', 'aria-label': 'Next game', html: icon('right', 20), onclick: () => go(index + 1) }))) : null);

            function go(i) {
                index = (i + n) % n;
                slides.forEach((s, k) => { s.classList.toggle('active', k === index); s.inert = k !== index; });
                tabs.forEach((t, k) => {
                    t.classList.toggle('done', k < index);
                    t.classList.remove('active');
                });
                // The progress line restarts: it moves on when it fills (and waits while hovered).
                void tabs[index]?.offsetWidth;
                tabs[index]?.classList.add('active');
            }
            tabs.forEach((t) => t.querySelector('i').addEventListener('animationend', () => go(index + 1)));

            // Swipe between slides on touch screens.
            let startX = null;
            root.addEventListener('pointerdown', (e) => { startX = e.clientX; });
            root.addEventListener('pointerup', (e) => {
                if (startX === null || n < 2) return;
                const dx = e.clientX - startX;
                startX = null;
                if (Math.abs(dx) > 50) go(index + (dx < 0 ? 1 : -1));
            });
            root.addEventListener('keydown', (e) => {
                if (e.key === 'ArrowRight') go(index + 1);
                if (e.key === 'ArrowLeft') go(index - 1);
            });

            go(0);
            return root;
        }

        /** Featured: the most played games with art first, then the rest; up to five. */
        function featuredGames(byCode) {
            const ordered = [...new Set([...lobby.popular, ...lobby.games.map((g) => g.code)])]
                .map((c) => byCode.get(c)).filter((g) => g && g.playable);
            const withArt = ordered.filter((g) => g.thumbnail_url);
            const list = (withArt.length >= 2 ? withArt : ordered).slice(0, 5);
            return list.length ? list : [lobby.games[0]];
        }

        // ---------- Views ----------
        function renderTabs() {
            const tabs = [['all', 'Home', 'home'], ...lobby.categories.map((c) => [c.slug, c.name, iconFor(c.slug)])];
            $('tabs').replaceChildren(...tabs.map(([id, name, ic]) => el('button', {
                class: 'tab', 'aria-pressed': String(id === tab), onclick: () => selectTab(id), html: icon(ic, 18),
            }, name)));
        }

        function selectTab(id) {
            tab = id;
            $('search').value = '';
            $('topbar').classList.remove('searching');
            render();
            if (window.scrollY > 0) window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        function renderSkeleton() {
            $('tabs').replaceChildren(...Array.from({ length: 4 }, () => el('span', { class: 'skeleton tab-skel' })));
            $('content').replaceChildren(
                el('div', { class: 'skeleton hero-skel' }),
                el('div', { class: 'section' }, el('div', { class: 'skeleton title' }),
                    el('div', { class: 'row' }, ...Array.from({ length: 6 }, () => el('div', {}, el('div', { class: 'skeleton tile-skel' }), el('div', { class: 'skeleton line' }))))),
            );
        }

        function render() {
            if (!lobby) return renderSkeleton();
            renderTabs();
            const query = $('search').value.trim().toLowerCase();
            const byCode = new Map(lobby.games.map((g) => [g.code, g]));
            const pick = (codes) => codes.map((c) => byCode.get(c)).filter(Boolean);
            // "Hot" marks the week's top three, once there are enough plays to rank.
            hot = new Set(lobby.popular.length >= 3 ? lobby.popular.slice(0, 3) : []);

            const content = [];
            if (query) {
                const found = lobby.games.filter((g) => g.name.toLowerCase().includes(query) || g.code.includes(query));
                content.push(found.length
                    ? section(`Results for “${$('search').value.trim()}”`, { icon: 'search', note: plural(found.length), body: el('div', { class: 'grid' }, ...found.map(tile)) })
                    : empty('No games found', 'Try another name, or browse the categories above.', 'search'));
            } else if (tab !== 'all') {
                const games = lobby.games.filter((g) => g.category === tab);
                const shelf = games.filter((g) => g.thumbnail_url).slice(0, 3);
                content.push(el('div', { class: 'page-head' },
                    shelf.length ? el('div', { class: 'page-art', 'aria-hidden': 'true' },
                        ...shelf.map((g) => el('img', { src: g.thumbnail_url, alt: '', decoding: 'async' }))) : null,
                    el('span', { class: 'page-icon', html: icon(iconFor(tab), 28) }),
                    el('div', {}, el('h1', {}, categoryName(tab) ?? 'Games'), el('p', {}, plural(games.length)))));
                content.push(games.length
                    ? el('div', { class: 'grid' }, ...games.map(tile))
                    : empty('Nothing here yet', 'New games are on their way.', iconFor(tab)));
            } else if (lobby.games.length === 0) {
                content.push(empty('No games available', 'Please check back soon.'));
            } else {
                content.push(hero(featuredGames(byCode)));
                if (lobby.games.length > 4) {
                    // Small catalogues stay uncluttered: the rows appear once they are useful.
                    const recent = pick(lobby.recent).filter((g) => g.playable);
                    const popular = pick(lobby.popular);
                    if (recent.length) content.push(section('Continue playing', { icon: 'history', body: row('row-resume', recent.map(resume)) }));
                    if (popular.length >= 3) content.push(section('Top games this week', { icon: 'fire', note: 'Most played', body: row('row-rank', popular.map(ranked)) }));
                    // A row per category once the catalogue fills them (sparse rows look empty);
                    // the rest, and every smaller catalogue, in one grid.
                    const kinds = lobby.categories.map((c) => [c, lobby.games.filter((g) => g.category === c.slug)]).filter(([, games]) => games.length >= 4);
                    if (lobby.games.length >= 12 && kinds.length > 1) {
                        for (const [c, games] of kinds) {
                            content.push(section(c.name, { icon: iconFor(c.slug), note: plural(games.length), action: viewAll(c.slug), body: row('', games.map(tile)) }));
                        }
                        const loose = lobby.games.filter((g) => !kinds.some(([c]) => c.slug === g.category));
                        if (loose.length) content.push(section('More games', { icon: 'grid', note: plural(loose.length), body: el('div', { class: 'grid' }, ...loose.map(tile)) }));
                    } else {
                        content.push(section('All games', { icon: 'grid', note: plural(lobby.games.length), body: el('div', { class: 'grid' }, ...lobby.games.map(tile)) }));
                    }
                } else if (lobby.games.length > 1) {
                    content.push(section('All games', { icon: 'grid', note: plural(lobby.games.length), body: el('div', { class: 'grid' }, ...lobby.games.map(tile)) }));
                }
            }

            if (firstPaint) {
                content.forEach((node, i) => { node.classList.add('reveal'); node.style.setProperty('--i', i); });
                firstPaint = false;
            }
            $('content').replaceChildren(...content);
            requestAnimationFrame(syncRows);
        }

        const syncRows = () => document.querySelectorAll('.row').forEach((r) => r.sync?.());
        window.addEventListener('resize', syncRows);

        // ---------- Balance ----------
        const money = (amount) => new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(amount));

        function showBalance(balance) {
            const none = balance == null || balance === '';
            for (const [box, amount] of [['balance', 'balance-amount'], ['game-balance', 'game-balance-amount']]) {
                $(box).hidden = none;
                if (!none) $(amount).replaceChildren(money(balance), currency ? el('em', {}, currency) : '');
            }
        }

        async function refresh() {
            if (!host) return;
            const [r, s] = await Promise.all([host.api('/lobby'), host.session()]);
            if (r.ok) {
                lobby = r.data;
                applyTheme(lobby.theme);
                if (!lobby.categories.some((c) => c.slug === tab)) tab = 'all';
                render();
            }
            if (s.ok) currency = s.data.player.currency;
            showBalance(s.ok ? host.balance : null);
        }

        // ---------- Search ----------
        function openSearch() {
            $('topbar').classList.add('searching');
            $('search').focus();
        }
        function closeSearch() {
            $('search').value = '';
            $('topbar').classList.remove('searching');
            render();
        }
        $('search').addEventListener('input', render);
        $('search').addEventListener('keydown', (e) => { if (e.key === 'Escape') { closeSearch(); e.target.blur(); } });
        $('search').addEventListener('blur', () => { if (!$('search').value) $('topbar').classList.remove('searching'); });
        $('search-toggle').addEventListener('click', openSearch);
        $('search-close').addEventListener('mousedown', (e) => e.preventDefault()); // keep focus until the click lands
        $('search-close').addEventListener('click', closeSearch);
        document.addEventListener('keydown', (e) => {
            if (e.key === '/' && document.activeElement !== $('search') && !$('lobby').hidden) { e.preventDefault(); openSearch(); }
        });

        $('back').addEventListener('click', () => {
            showLobby();
            host?.outer.send('back_to_lobby', {});
        });
        render();
        refresh();
    </script>
</body>
</html>
