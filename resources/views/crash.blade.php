<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orange — Crash (live)</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        body { font-family: ui-monospace, monospace; background: #0b1020; color: #f3f4f6; padding: 2rem; }
        .multiplier { font-size: 6rem; font-weight: 700; text-align: center; color: #fbbf24; }
        .multiplier.crashed { color: #ef4444; }
        .meta { text-align: center; opacity: 0.6; margin-top: 1rem; }
        .log { margin-top: 2rem; max-height: 300px; overflow: auto; font-size: 0.8rem; opacity: 0.7; }
    </style>
</head>
<body>
    <h1>Orange — Crash demo (Reverb)</h1>
    <div id="multiplier" class="multiplier">1.00x</div>
    <div id="state" class="meta">waiting for round…</div>
    <div id="log" class="log"></div>

    <script type="module">
        const $mult = document.getElementById('multiplier');
        const $state = document.getElementById('state');
        const $log = document.getElementById('log');

        const log = (msg) => {
            const line = document.createElement('div');
            line.textContent = `[${new Date().toLocaleTimeString()}] ${msg}`;
            $log.prepend(line);
        };

        const onReady = () => {
            log('Echo ready, subscribing to crash channel');

            window.Echo.channel('crash')
                .listen('.round.betting_opened', (e) => {
                    $state.textContent = `betting open · seed_hash ${e.server_seed_hash?.slice(0, 12)}…`;
                    $mult.classList.remove('crashed');
                    $mult.textContent = '1.00x';
                    log(`betting_opened ${e.round_uuid}`);
                })
                .listen('.round.started', (e) => {
                    $state.textContent = `running · ${e.round_uuid}`;
                    log(`started ${e.round_uuid}`);
                })
                .listen('.round.tick', (e) => {
                    $mult.textContent = `${e.multiplier.toFixed(2)}x`;
                })
                .listen('.round.crashed', (e) => {
                    $state.textContent = `crashed @ ${e.crash_point.toFixed(2)}x`;
                    $mult.classList.add('crashed');
                    $mult.textContent = `${e.crash_point.toFixed(2)}x`;
                    log(`crashed ${e.round_uuid} @ ${e.crash_point}`);
                })
                .listen('.round.settled', (e) => {
                    log(`settled ${e.round_uuid} (seed revealed: ${e.server_seed?.slice(0, 12)}…)`);
                });
        };

        if (window.Echo) onReady();
        else window.addEventListener('DOMContentLoaded', () => setTimeout(onReady, 100));
    </script>
</body>
</html>
