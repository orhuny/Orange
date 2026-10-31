<!DOCTYPE html>
<html lang="{{ $language ?? 'en' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="referrer" content="no-referrer">
    <title>{{ $gameName }}</title>
    <style>
        html, body { margin: 0; height: 100%; background: #04121f; overflow: hidden; }
        iframe { border: 0; width: 100%; height: 100%; display: block; }
        .expired { display: none; position: fixed; inset: 0; align-items: center; justify-content: center;
            color: #e2e8f0; font: 16px/1.5 system-ui, sans-serif; text-align: center; padding: 16px; }
        .expired.shown { display: flex; }
    </style>
</head>
<body>
    <iframe id="game" title="{{ $gameName }}" allow="fullscreen; autoplay"></iframe>
    <div class="expired" id="expired" role="alert">This game session has ended. Please open the game again.</div>

    <script type="module">
        import { startPlayHost } from @json(asset('js/orange/play-host.js'));

        const frame = document.getElementById('game');
        startPlayHost({
            gameCode: @json($gameCode),
            frameUrl: @json($frameUrl),
            bearer: @json($bearer),
            storageKey: @json('orange.play.'.$gameCode),
            outerOrigins: @json($outerOrigins),
            playApi: @json(url('/play-api')),
            frame,
            onExpired() {
                frame.remove();
                document.getElementById('expired').classList.add('shown');
            },
        });
    </script>
</body>
</html>
