<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="referrer" content="no-referrer">
    <title>Games</title>
    <style>
        :root { --primary: {{ $theme['primary'] }}; --accent: {{ $theme['accent'] }}; --bg: {{ $theme['background'] }};
                --panel: rgba(255,255,255,.06); --line: rgba(255,255,255,.12); --text: #e2e8f0; --muted: #94a3b8; }
        * { box-sizing: border-box; }
        html, body { margin: 0; height: 100%; background: var(--bg); color: var(--text); font: 15px/1.4 system-ui, -apple-system, Segoe UI, sans-serif; }
        #lobby { max-width: 1100px; margin: 0 auto; padding: 16px; }
        header { display: flex; align-items: center; gap: 12px; margin-bottom: 12px; flex-wrap: wrap; }
        header img { height: 32px; max-width: 160px; object-fit: contain; }
        header input { flex: 1; min-width: 160px; padding: 10px 12px; border-radius: 8px; border: 1px solid var(--line); background: var(--panel); color: var(--text); font-size: 16px; }
        nav { display: flex; gap: 8px; overflow-x: auto; padding-bottom: 8px; margin-bottom: 8px; }
        nav button { white-space: nowrap; padding: 8px 14px; border-radius: 999px; border: 1px solid var(--line); background: transparent; color: var(--text); cursor: pointer; font-size: 14px; }
        nav button[aria-pressed=true] { background: var(--primary); border-color: var(--primary); color: #111; font-weight: 600; }
        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 12px; }
        .tile { position: relative; aspect-ratio: 4 / 3; border-radius: 12px; overflow: hidden; border: 1px solid var(--line); background: var(--panel); cursor: pointer; padding: 0; color: var(--text); text-align: left; }
        .tile img { width: 100%; height: 100%; object-fit: cover; display: block; }
        .tile span { position: absolute; left: 0; right: 0; bottom: 0; padding: 8px 10px; background: linear-gradient(transparent, rgba(0,0,0,.75)); font-weight: 600; }
        .tile .ph { display: flex; align-items: center; justify-content: center; height: 100%; font-size: 28px; font-weight: 800; color: var(--primary); }
        .tile:disabled { opacity: .5; cursor: not-allowed; }
        .tile:focus-visible { outline: 2px solid var(--accent); outline-offset: 2px; }
        .empty { color: var(--muted); padding: 24px 0; }
        #game { position: fixed; inset: 0; display: none; flex-direction: column; background: #000; }
        #game.shown { display: flex; }
        #game .bar { display: flex; align-items: center; gap: 8px; padding: 6px 8px; background: var(--bg); border-bottom: 1px solid var(--line); }
        #game .bar button { padding: 6px 12px; border-radius: 8px; border: 1px solid var(--line); background: var(--panel); color: var(--text); cursor: pointer; }
        #game iframe { flex: 1; border: 0; width: 100%; }
        .expired { display: none; position: fixed; inset: 0; align-items: center; justify-content: center; text-align: center; padding: 16px; background: var(--bg); }
        .expired.shown { display: flex; }
    </style>
</head>
<body>
    <div id="lobby">
        <header>
            @if ($theme['logo_url'])
                <img src="{{ $theme['logo_url'] }}" alt="" id="logo">
            @endif
            <input type="search" id="search" placeholder="Search games" aria-label="Search games">
        </header>
        <nav id="tabs" aria-label="Categories"></nav>
        <div class="grid" id="grid" aria-live="polite"></div>
    </div>

    <div id="game" aria-label="Game">
        <div class="bar"><button id="back">← Lobby</button><strong id="game-name"></strong></div>
        <iframe id="frame" title="Game" allow="fullscreen; autoplay"></iframe>
    </div>

    <div class="expired" id="expired" role="alert">This session has ended. Please open the games again.</div>

    <script type="module">
        import { startPlayHost } from @json(asset('js/orange/play-host.js'));

        const $ = (id) => document.getElementById(id);
        let lobby = { categories: [], games: [], recent: [], popular: [] };
        let tab = 'all';

        function showLobby() {
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
        });

        function open(game) {
            if (!game.playable) return;
            $('game-name').textContent = game.name;
            $('lobby').hidden = true;
            $('game').classList.add('shown');
            host.open(game.code, game.frame_url);
        }

        function tile(game) {
            const button = document.createElement('button');
            button.className = 'tile';
            button.disabled = !game.playable;
            button.title = game.playable ? game.name : `${game.name} (maintenance)`;
            if (game.thumbnail_url) {
                const img = document.createElement('img');
                img.src = game.thumbnail_url;
                img.alt = '';
                img.loading = 'lazy';
                button.append(img);
            } else {
                const ph = document.createElement('div');
                ph.className = 'ph';
                ph.textContent = game.name.slice(0, 1).toUpperCase();
                button.append(ph);
            }
            const label = document.createElement('span');
            label.textContent = game.name;
            button.append(label);
            button.addEventListener('click', () => open(game));
            return button;
        }

        function render() {
            const query = $('search').value.trim().toLowerCase();
            const byCode = new Map(lobby.games.map((g) => [g.code, g]));
            let games = tab === 'recent' ? lobby.recent.map((c) => byCode.get(c))
                : tab === 'popular' ? lobby.popular.map((c) => byCode.get(c))
                : tab === 'all' ? lobby.games
                : lobby.games.filter((g) => g.category === tab);
            games = games.filter(Boolean).filter((g) => !query || g.name.toLowerCase().includes(query) || g.code.includes(query));

            $('grid').replaceChildren(...games.map(tile));
            if (games.length === 0) {
                const empty = document.createElement('p');
                empty.className = 'empty';
                empty.textContent = query ? 'No games match your search.' : 'Nothing here yet.';
                $('grid').append(empty);
            }

            const tabs = [['all', 'All games'], ...(lobby.recent.length ? [['recent', 'Recent']] : []),
                ...(lobby.popular.length ? [['popular', 'Popular']] : []), ...lobby.categories.map((c) => [c.slug, c.name])];
            $('tabs').replaceChildren(...tabs.map(([id, name]) => {
                const b = document.createElement('button');
                b.textContent = name;
                b.setAttribute('aria-pressed', String(id === tab));
                b.addEventListener('click', () => { tab = id; render(); });
                return b;
            }));
        }

        async function refresh() {
            if (!host) return;
            const r = await host.api('/lobby');
            if (!r.ok) return;
            lobby = r.data;
            const root = document.documentElement.style;
            root.setProperty('--primary', lobby.theme.primary);
            root.setProperty('--accent', lobby.theme.accent);
            root.setProperty('--bg', lobby.theme.background);
            render();
        }

        $('search').addEventListener('input', render);
        $('back').addEventListener('click', () => {
            showLobby();
            host?.outer.send('back_to_lobby', {});
        });
        refresh();
    </script>
</body>
</html>
