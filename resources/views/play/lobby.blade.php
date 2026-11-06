<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="referrer" content="no-referrer">
    <title>Games</title>
    @include('play.bridge-importmap')
    <style>
        /* The operator's colours (Operator::lobbyTheme) drive everything; the rest is mixed from them. */
        :root {
            --primary: {{ $theme['primary'] }}; --accent: {{ $theme['accent'] }}; --bg: {{ $theme['background'] }};
            --surface: color-mix(in srgb, var(--bg) 90%, #fff);
            --surface-2: color-mix(in srgb, var(--bg) 82%, #fff);
            --line: color-mix(in srgb, var(--bg) 78%, #fff);
            --text: #eef2f7; --muted: #8b98ab; --on-primary: #0b0f14;
            --radius: 14px; --ease: cubic-bezier(.2, .7, .3, 1);
            color-scheme: dark;
        }
        * { box-sizing: border-box; }
        html, body { margin: 0; min-height: 100%; background: var(--bg); color: var(--text);
                     font: 15px/1.45 Inter, ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
                     -webkit-font-smoothing: antialiased; }
        button { font: inherit; color: inherit; }
        svg { display: block; }
        [hidden] { display: none !important; }

        /* ---------- Top bar ---------- */
        .topbar { position: sticky; top: 0; z-index: 10; background: color-mix(in srgb, var(--bg) 86%, transparent);
                  backdrop-filter: blur(14px); -webkit-backdrop-filter: blur(14px); border-bottom: 1px solid var(--line); }
        .wrap { max-width: 1240px; margin: 0 auto; padding: 0 20px; }
        .topbar-row { display: flex; align-items: center; gap: 16px; height: 64px; }
        .brand { display: flex; align-items: center; gap: 10px; min-width: 0; flex-shrink: 0; }
        .brand img { height: 30px; max-width: 150px; object-fit: contain; }
        .brand-mark { width: 30px; height: 30px; border-radius: 9px; display: grid; place-items: center;
                      background: linear-gradient(135deg, var(--primary), color-mix(in srgb, var(--primary) 55%, var(--accent))); color: var(--on-primary); }
        .brand-name { font-weight: 700; letter-spacing: -.01em; font-size: 17px; }
        .search { position: relative; flex: 1; max-width: 460px; margin-left: auto; }
        .search svg { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--muted); pointer-events: none; }
        .search input { width: 100%; height: 40px; padding: 0 40px 0 38px; border-radius: 10px; border: 1px solid var(--line);
                        background: var(--surface); color: var(--text); font-size: 15px; outline: none; transition: border-color .15s, box-shadow .15s; }
        .search input::placeholder { color: var(--muted); }
        .search input:focus { border-color: color-mix(in srgb, var(--primary) 70%, transparent); box-shadow: 0 0 0 3px color-mix(in srgb, var(--primary) 22%, transparent); }
        .search input::-webkit-search-cancel-button { display: none; }
        .search kbd { position: absolute; right: 10px; top: 50%; transform: translateY(-50%); font: 600 11px/1 inherit; color: var(--muted);
                      border: 1px solid var(--line); border-radius: 5px; padding: 3px 6px; }
        .search input:focus + kbd, .search input:not(:placeholder-shown) + kbd { display: none; }
        .balance { display: flex; flex-direction: column; align-items: flex-end; padding: 6px 12px; border-radius: 10px;
                   background: var(--surface); border: 1px solid var(--line); flex-shrink: 0; }
        .balance span { font-size: 11px; text-transform: uppercase; letter-spacing: .08em; color: var(--muted); }
        .balance strong { font-size: 15px; font-variant-numeric: tabular-nums; }
        .chips { display: flex; gap: 8px; overflow-x: auto; padding: 12px 0; scrollbar-width: none; }
        .chips::-webkit-scrollbar { display: none; }
        .chip { display: inline-flex; align-items: center; gap: 8px; white-space: nowrap; height: 34px; padding: 0 14px; border-radius: 999px;
                border: 1px solid var(--line); background: transparent; color: var(--muted); cursor: pointer; font-size: 14px; font-weight: 500;
                transition: background .15s, color .15s, border-color .15s; }
        .chip:hover { color: var(--text); background: var(--surface); }
        .chip[aria-pressed=true] { background: var(--primary); border-color: var(--primary); color: var(--on-primary); font-weight: 600; }
        .chip .count { font-size: 12px; padding: 1px 7px; border-radius: 999px; background: color-mix(in srgb, currentColor 14%, transparent); }

        /* ---------- Content ---------- */
        #content { padding-top: 28px; padding-bottom: 56px; }
        .section { margin-top: 32px; }
        .section:first-child { margin-top: 0; }
        .section-head { display: flex; align-items: baseline; justify-content: space-between; gap: 12px; margin-bottom: 14px; }
        .section-head h2 { margin: 0; font-size: 18px; font-weight: 700; letter-spacing: -.01em; }
        .section-head span { color: var(--muted); font-size: 13px; }

        /* Featured game */
        .hero { position: relative; overflow: hidden; border-radius: 20px; border: 1px solid var(--line); background: var(--surface);
                display: grid; grid-template-columns: 1fr 1.1fr; align-items: center; min-height: 300px; isolation: isolate; }
        .hero-bg { position: absolute; inset: -40px; z-index: -2; background-size: cover; background-position: center; filter: blur(40px) saturate(1.3); opacity: .45; }
        .hero::before { content: ""; position: absolute; inset: 0; z-index: -1;
                        background: linear-gradient(90deg, var(--bg) 8%, color-mix(in srgb, var(--bg) 70%, transparent) 55%, transparent),
                                    radial-gradient(600px 300px at 0% 100%, color-mix(in srgb, var(--primary) 22%, transparent), transparent); }
        .hero-body { padding: 40px; }
        .eyebrow { display: inline-flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 700; letter-spacing: .1em; text-transform: uppercase;
                   color: var(--primary); }
        .eyebrow::before { content: ""; width: 6px; height: 6px; border-radius: 50%; background: var(--primary); box-shadow: 0 0 0 4px color-mix(in srgb, var(--primary) 25%, transparent); }
        .hero h1 { margin: 12px 0 8px; font-size: clamp(28px, 4vw, 44px); line-height: 1.05; letter-spacing: -.02em; }
        .hero-meta { margin: 0 0 24px; color: var(--muted); display: flex; gap: 8px; flex-wrap: wrap; }
        .pill { font-size: 12px; font-weight: 600; padding: 4px 10px; border-radius: 999px; background: var(--surface-2); color: var(--text); border: 1px solid var(--line); }
        .btn-primary { display: inline-flex; align-items: center; gap: 10px; height: 46px; padding: 0 22px; border: 0; border-radius: 12px; cursor: pointer;
                       background: var(--primary); color: var(--on-primary); font-weight: 700; font-size: 15px;
                       box-shadow: 0 10px 30px -8px color-mix(in srgb, var(--primary) 70%, transparent); transition: transform .15s var(--ease), filter .15s; }
        .btn-primary:hover { transform: translateY(-1px); filter: brightness(1.06); }
        .btn-primary:disabled { opacity: .5; cursor: not-allowed; transform: none; }
        .hero-art { padding: 28px 28px 28px 0; }
        .hero-art .media { aspect-ratio: 4 / 3; border-radius: 16px; box-shadow: 0 30px 60px -20px rgba(0,0,0,.7); }

        /* Game cards */
        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(190px, 1fr)); gap: 18px 16px; }
        .row { display: grid; grid-auto-flow: column; grid-auto-columns: minmax(190px, 220px); gap: 16px; overflow-x: auto; padding-bottom: 6px; scrollbar-width: thin; }
        .tile { display: block; width: 100%; padding: 0; border: 0; background: none; text-align: left; cursor: pointer; border-radius: var(--radius); }
        .media { position: relative; aspect-ratio: 4 / 3; border-radius: var(--radius); overflow: hidden; background: var(--surface-2);
                 border: 1px solid var(--line); transition: transform .25s var(--ease), box-shadow .25s var(--ease), border-color .25s; }
        .media img { width: 100%; height: 100%; object-fit: cover; display: block; transition: transform .4s var(--ease); }
        .media .ph { position: absolute; inset: 0; display: grid; place-items: center; font-size: 40px; font-weight: 800; letter-spacing: -.03em;
                     color: color-mix(in srgb, var(--primary) 85%, #fff);
                     background: radial-gradient(circle at 30% 20%, color-mix(in srgb, var(--primary) 30%, transparent), transparent 60%),
                                 radial-gradient(circle at 80% 90%, color-mix(in srgb, var(--accent) 22%, transparent), transparent 55%), var(--surface-2); }
        .play { position: absolute; inset: 0; display: grid; place-items: center; background: rgba(0,0,0,.35); opacity: 0; transition: opacity .2s; }
        .play i { width: 52px; height: 52px; border-radius: 50%; display: grid; place-items: center; background: var(--primary); color: var(--on-primary);
                  transform: scale(.85); transition: transform .25s var(--ease); box-shadow: 0 8px 24px rgba(0,0,0,.45); }
        .tile:hover .media, .tile:focus-visible .media { transform: translateY(-4px); border-color: color-mix(in srgb, var(--primary) 60%, transparent);
                                                         box-shadow: 0 18px 40px -16px color-mix(in srgb, var(--primary) 55%, transparent); }
        .tile:hover .media img { transform: scale(1.06); }
        .tile:hover .play, .tile:focus-visible .play { opacity: 1; }
        .tile:hover .play i, .tile:focus-visible .play i { transform: scale(1); }
        .tile:focus-visible { outline: none; }
        .tile:focus-visible .media { outline: 2px solid var(--accent); outline-offset: 3px; }
        .badge { position: absolute; top: 10px; left: 10px; font-size: 11px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase;
                 padding: 4px 8px; border-radius: 6px; background: rgba(0,0,0,.65); color: #fbbf24; backdrop-filter: blur(6px); }
        .tile:disabled { cursor: not-allowed; }
        .tile:disabled .media { filter: grayscale(.7); opacity: .7; }
        .tile:disabled:hover .media { transform: none; box-shadow: none; border-color: var(--line); }
        .tile:disabled .play { display: none; }
        .caption { padding: 10px 2px 0; }
        .caption b { display: block; font-size: 15px; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .caption small { display: block; margin-top: 2px; font-size: 13px; color: var(--muted); }

        /* Loading placeholders */
        .skeleton { background: linear-gradient(100deg, var(--surface) 30%, var(--surface-2) 50%, var(--surface) 70%) 0 0 / 300% 100%; animation: shimmer 1.4s linear infinite; border-radius: var(--radius); }
        .skeleton.hero-skel { height: 300px; border-radius: 20px; }
        .skeleton.tile-skel { aspect-ratio: 4 / 3; }
        .skeleton.line { height: 12px; margin-top: 12px; width: 60%; border-radius: 6px; }
        @keyframes shimmer { to { background-position: -150% 0; } }

        .empty { display: grid; place-items: center; text-align: center; gap: 10px; padding: 64px 16px; color: var(--muted); }
        .empty svg { color: var(--line); }
        .empty strong { color: var(--text); font-size: 16px; }

        /* ---------- Game view ---------- */
        #game { position: fixed; inset: 0; display: none; flex-direction: column; background: #000; z-index: 20; }
        #game.shown { display: flex; }
        .gamebar { display: flex; align-items: center; gap: 12px; height: 52px; padding: 0 12px; background: var(--bg); border-bottom: 1px solid var(--line); }
        .btn-ghost { display: inline-flex; align-items: center; gap: 6px; height: 34px; padding: 0 12px 0 8px; border-radius: 9px; border: 1px solid var(--line);
                     background: var(--surface); cursor: pointer; font-size: 14px; font-weight: 600; transition: background .15s; }
        .btn-ghost:hover { background: var(--surface-2); }
        .gamebar strong { font-size: 15px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .gamebar .balance { margin-left: auto; flex-direction: row; align-items: center; gap: 8px; padding: 4px 10px; }
        .stage { position: relative; flex: 1; }
        /* The operator page expanded the game (fullscreen, or where there is none, as on
           iPhones, filling the window): the bar steps aside too. */
        body.game-expanded .gamebar { display: none; }
        /* The game has its own way back (its menu's Home): the bar would only take room. */
        body.game-has-home .gamebar { display: none; }
        .stage iframe { position: absolute; inset: 0; width: 100%; height: 100%; border: 0; }
        .spinner { position: absolute; inset: 0; display: grid; place-items: center; background: #000; transition: opacity .3s; }
        .spinner i { width: 36px; height: 36px; border-radius: 50%; border: 3px solid rgba(255,255,255,.15); border-top-color: var(--primary); animation: spin .8s linear infinite; }
        .spinner.gone { opacity: 0; pointer-events: none; }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* ---------- Ended session ---------- */
        .expired { display: none; position: fixed; inset: 0; z-index: 30; align-items: center; justify-content: center; padding: 20px; background: var(--bg); }
        .expired.shown { display: flex; }
        .expired .card { max-width: 380px; text-align: center; padding: 36px 28px; border-radius: 20px; background: var(--surface); border: 1px solid var(--line); }
        .expired .icon { width: 56px; height: 56px; margin: 0 auto 16px; border-radius: 50%; display: grid; place-items: center;
                         background: color-mix(in srgb, var(--primary) 16%, transparent); color: var(--primary); }
        .expired h2 { margin: 0 0 8px; font-size: 20px; }
        .expired p { margin: 0; color: var(--muted); }

        @media (max-width: 760px) {
            .wrap { padding: 0 14px; }
            .topbar-row { gap: 10px; height: 58px; }
            .brand-name, .search kbd, .balance span { display: none; }
            .search { max-width: none; }
            .hero { grid-template-columns: 1fr; min-height: 0; }
            .hero-art { order: -1; padding: 14px 14px 0; }
            .hero-body { padding: 18px 20px 22px; }
            .hero::before { background: linear-gradient(0deg, var(--bg) 20%, transparent); }
            .grid { grid-template-columns: repeat(2, 1fr); gap: 14px 12px; }
            .row { grid-auto-columns: 46%; }
            #content { padding-top: 16px; }
        }
        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { transition: none !important; animation: none !important; }
        }
    </style>
</head>
<body>
    <div id="lobby">
        <header class="topbar">
            <div class="wrap">
                <div class="topbar-row">
                    <div class="brand">
                        @if ($theme['logo_url'])
                            <img src="{{ $theme['logo_url'] }}" alt="" id="logo">
                        @else
                            <span class="brand-mark" aria-hidden="true"><svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5.5v13a1 1 0 0 0 1.5.86l10.4-6.5a1 1 0 0 0 0-1.72L9.5 4.64A1 1 0 0 0 8 5.5Z"/></svg></span>
                            <span class="brand-name">Games</span>
                        @endif
                    </div>
                    <label class="search">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                        <input type="search" id="search" placeholder="Search games" aria-label="Search games" autocomplete="off">
                        <kbd aria-hidden="true">/</kbd>
                    </label>
                    <div class="balance" id="balance" hidden><span>Balance</span><strong id="balance-amount"></strong></div>
                </div>
                <nav class="chips" id="tabs" aria-label="Categories"></nav>
            </div>
        </header>
        <main class="wrap" id="content" aria-live="polite"></main>
    </div>

    <div id="game" aria-label="Game">
        <div class="gamebar">
            <button id="back" class="btn-ghost"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>Lobby</button>
            <strong id="game-name"></strong>
        </div>
        <div class="stage">
            <iframe id="frame" title="Game" allow="fullscreen; autoplay"></iframe>
            <div class="spinner gone" id="spinner" aria-hidden="true"><i></i></div>
        </div>
    </div>

    <div class="expired" id="expired" role="alert">
        <div class="card">
            <div class="icon"><svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg></div>
            <h2>This session has ended</h2>
            <p>Please open the games again from the casino.</p>
        </div>
    </div>

    <script type="module">
        import { startPlayHost } from @json(asset('js/orange/play-host.js').'?v='.filemtime(public_path('js/orange/play-host.js')));

        const $ = (id) => document.getElementById(id);
        let lobby = null; // null until the first answer: placeholders show meanwhile
        let tab = 'all';
        const PLAY_ICON = '<svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5.5v13a1 1 0 0 0 1.5.86l10.4-6.5a1 1 0 0 0 0-1.72L9.5 4.64A1 1 0 0 0 8 5.5Z"/></svg>';

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
            onExpired() {
                $('lobby').hidden = true;
                $('game').classList.remove('shown');
                $('expired').classList.add('shown');
            },
            onBackToLobby: showLobby,
            onGameReady(payload) {
                document.body.classList.toggle('game-has-home', !!payload?.home);
            },
            onFullscreen(active) {
                document.body.classList.toggle('game-expanded', active);
            },
        });

        function open(game) {
            if (!game.playable) return;
            $('game-name').textContent = game.name;
            $('spinner').classList.remove('gone');
            // The bar shows until the game says it has its own Home.
            document.body.classList.remove('game-has-home');
            $('lobby').hidden = true;
            $('game').classList.add('shown');
            host.open(game.code, game.frame_url);
        }
        $('frame').addEventListener('load', () => {
            if ($('frame').getAttribute('src') !== 'about:blank') $('spinner').classList.add('gone');
        });

        const categoryName = (slug) => lobby.categories.find((c) => c.slug === slug)?.name ?? null;
        const rtp = (value) => (value ? `RTP ${Number((Number(value) * 100).toFixed(2))}%` : null);
        const subtitle = (game) => [categoryName(game.category), rtp(game.rtp)].filter(Boolean).join(' · ');

        function media(game, { badge = true } = {}) {
            return el('div', { class: 'media' },
                game.thumbnail_url
                    ? el('img', { src: game.thumbnail_url, alt: '', loading: 'lazy', decoding: 'async' })
                    : el('div', { class: 'ph' }, game.name.slice(0, 1).toUpperCase()),
                badge && !game.playable ? el('span', { class: 'badge' }, 'Maintenance') : null,
                el('div', { class: 'play', 'aria-hidden': 'true' }, el('i', { html: PLAY_ICON })),
            );
        }

        function tile(game) {
            return el('button', {
                class: 'tile',
                disabled: !game.playable,
                'aria-label': game.playable ? `Play ${game.name}` : `${game.name} (maintenance)`,
                onclick: () => open(game),
            }, media(game), el('div', { class: 'caption' }, el('b', {}, game.name), el('small', {}, subtitle(game) || ' ')));
        }

        function hero(game) {
            const section = el('section', { class: 'hero' },
                el('div', { class: 'hero-body' },
                    el('span', { class: 'eyebrow' }, 'Featured'),
                    el('h1', {}, game.name),
                    el('p', { class: 'hero-meta' },
                        ...[categoryName(game.category), rtp(game.rtp), game.playable ? null : 'Maintenance'].filter(Boolean).map((t) => el('span', { class: 'pill' }, t))),
                    el('button', { class: 'btn-primary', disabled: !game.playable, onclick: () => open(game), html: PLAY_ICON + 'Play now' }),
                ),
                el('div', { class: 'hero-art' }, media(game, { badge: false })),
            );
            if (game.thumbnail_url) {
                const bg = el('div', { class: 'hero-bg' });
                bg.style.backgroundImage = `url(${JSON.stringify(game.thumbnail_url)})`;
                section.prepend(bg);
            }
            return section;
        }

        function section(title, note, body) {
            return el('section', { class: 'section' }, el('div', { class: 'section-head' }, el('h2', {}, title), note ? el('span', {}, note) : null), body);
        }

        function empty(title, text) {
            return el('div', { class: 'empty', html: '<svg width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"><rect x="3" y="6" width="18" height="12" rx="3"/><path d="M8 10v4M6 12h4M15 11h.01M18 13h.01"/></svg>' },
                el('strong', {}, title), el('span', {}, text));
        }

        const plural = (n) => `${n} ${n === 1 ? 'game' : 'games'}`;

        function renderSkeleton() {
            $('content').replaceChildren(
                el('div', { class: 'skeleton hero-skel' }),
                section('Games', null, el('div', { class: 'grid' }, ...Array.from({ length: 6 }, () => el('div', {}, el('div', { class: 'skeleton tile-skel' }), el('div', { class: 'skeleton line' }))))),
            );
        }

        function render() {
            if (!lobby) return renderSkeleton();
            const query = $('search').value.trim().toLowerCase();
            const byCode = new Map(lobby.games.map((g) => [g.code, g]));
            const pick = (codes) => codes.map((c) => byCode.get(c)).filter(Boolean);

            const tabs = [['all', 'All games', lobby.games.length],
                ...lobby.categories.map((c) => [c.slug, c.name, lobby.games.filter((g) => g.category === c.slug).length])];
            $('tabs').replaceChildren(...tabs.map(([id, name, count]) => el('button', {
                class: 'chip', 'aria-pressed': String(id === tab), onclick: () => { tab = id; render(); },
            }, name, el('span', { class: 'count' }, String(count)))));

            const content = [];
            if (query) {
                const found = lobby.games.filter((g) => g.name.toLowerCase().includes(query) || g.code.includes(query));
                content.push(found.length
                    ? section(`Results for “${$('search').value.trim()}”`, plural(found.length), el('div', { class: 'grid' }, ...found.map(tile)))
                    : empty('No games found', 'Try a different name.'));
            } else if (tab !== 'all') {
                const games = lobby.games.filter((g) => g.category === tab);
                content.push(games.length
                    ? section(categoryName(tab) ?? 'Games', plural(games.length), el('div', { class: 'grid' }, ...games.map(tile)))
                    : empty('Nothing here yet', 'New games are on their way.'));
            } else if (lobby.games.length === 0) {
                content.push(empty('No games available', 'Please check back soon.'));
            } else {
                // Featured: the operator's most played game, else the first. Small catalogues stay
                // uncluttered: the rows only appear once there are enough games to make them useful.
                const featured = pick(lobby.popular).find((g) => g.playable) ?? lobby.games.find((g) => g.playable) ?? lobby.games[0];
                content.push(hero(featured));
                if (lobby.games.length > 4) {
                    const recent = pick(lobby.recent);
                    const popular = pick(lobby.popular);
                    if (recent.length) content.push(section('Continue playing', null, el('div', { class: 'row' }, ...recent.map(tile))));
                    if (popular.length) content.push(section('Popular', 'Most played this week', el('div', { class: 'row' }, ...popular.map(tile))));
                }
                if (lobby.games.length > 1) {
                    content.push(section('All games', plural(lobby.games.length), el('div', { class: 'grid' }, ...lobby.games.map(tile))));
                }
            }
            $('content').replaceChildren(...content);
        }

        const money = (amount, currency) => `${new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(amount))} ${currency}`;

        async function refresh() {
            if (!host) return;
            const [r, s] = await Promise.all([host.api('/lobby'), host.api('/session')]);
            if (r.ok) {
                lobby = r.data;
                const root = document.documentElement.style;
                root.setProperty('--primary', lobby.theme.primary);
                root.setProperty('--accent', lobby.theme.accent);
                root.setProperty('--bg', lobby.theme.background);
                if (!lobby.categories.some((c) => c.slug === tab)) tab = 'all';
                render();
            }
            const balance = s.ok ? s.data.balance : null;
            $('balance').hidden = balance == null || balance === '';
            if (!$('balance').hidden) $('balance-amount').textContent = money(balance, s.data.player.currency);
        }

        $('search').addEventListener('input', render);
        $('search').addEventListener('keydown', (e) => { if (e.key === 'Escape') { e.target.value = ''; render(); } });
        document.addEventListener('keydown', (e) => {
            if (e.key === '/' && document.activeElement !== $('search') && !$('lobby').hidden) { e.preventDefault(); $('search').focus(); }
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
