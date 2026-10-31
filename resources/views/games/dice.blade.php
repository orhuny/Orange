<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dice</title>
    <style>
        :root { --bg: #04121f; --panel: #0b2236; --line: #17405f; --text: #e2e8f0; --muted: #8aa4bd;
                --accent: #ff8a00; --win: #22c55e; --loss: #ef4444; }
        * { box-sizing: border-box; }
        html, body { margin: 0; min-height: 100%; background: var(--bg); color: var(--text);
                     font: 15px/1.4 system-ui, -apple-system, Segoe UI, sans-serif; }
        main { max-width: 720px; margin: 0 auto; padding: 16px; display: grid; gap: 16px; }
        header { display: flex; justify-content: space-between; align-items: center; color: var(--muted); }
        .panel { background: var(--panel); border: 1px solid var(--line); border-radius: 12px; padding: 16px; }
        .roll { font-size: 64px; font-weight: 700; text-align: center; letter-spacing: 1px; font-variant-numeric: tabular-nums; }
        .roll.win { color: var(--win); } .roll.loss { color: var(--loss); }
        .verdict { text-align: center; min-height: 1.4em; color: var(--muted); }
        .track { position: relative; height: 10px; border-radius: 5px; background: var(--loss); margin: 12px 0 4px; }
        .track .win-zone { position: absolute; top: 0; bottom: 0; background: var(--win); border-radius: 5px; }
        input[type=range] { width: 100%; accent-color: var(--accent); }
        .row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; }
        .row div { background: var(--bg); border: 1px solid var(--line); border-radius: 8px; padding: 8px; text-align: center; }
        .row small { display: block; color: var(--muted); }
        .controls { display: grid; grid-template-columns: 1fr auto auto; gap: 8px; align-items: center; }
        input[type=text] { width: 100%; padding: 10px; border-radius: 8px; border: 1px solid var(--line);
                           background: var(--bg); color: var(--text); font-size: 16px; }
        button { padding: 10px 16px; border-radius: 8px; border: 1px solid var(--line); background: var(--bg);
                 color: var(--text); font-size: 15px; cursor: pointer; }
        button.primary { background: var(--accent); border-color: var(--accent); color: #111; font-weight: 700; }
        button:disabled { opacity: .5; cursor: not-allowed; }
        .toggle[aria-pressed=true] { border-color: var(--accent); color: var(--accent); }
        .history { display: flex; gap: 6px; flex-wrap: wrap; }
        .history span { padding: 2px 8px; border-radius: 999px; font-variant-numeric: tabular-nums; }
        .history .win { background: rgba(34,197,94,.15); color: var(--win); }
        .history .loss { background: rgba(239,68,68,.15); color: var(--loss); }
        .fair { color: var(--muted); font-size: 12px; word-break: break-all; }
        .status { color: var(--muted); font-size: 13px; min-height: 1.2em; }
        .status.err { color: var(--loss); }
        @media (max-width: 480px) { .roll { font-size: 48px; } .row { grid-template-columns: 1fr 1fr 1fr; gap: 6px; } }
    </style>
</head>
<body>
<main>
    <header><strong>Dice</strong><span id="balance">—</span></header>

    <section class="panel" aria-live="polite">
        <div class="roll" id="roll">00.00</div>
        <div class="verdict" id="verdict">Pick a target and roll.</div>
        <div class="track" aria-hidden="true"><div class="win-zone" id="zone"></div></div>
        <input type="range" id="target" min="0.01" max="99.99" step="0.01" value="50.00" aria-label="Target">
        <div class="row">
            <div><small>Target</small><span id="targetOut">50.00</span></div>
            <div><small>Win chance</small><span id="chanceOut">50.00%</span></div>
            <div><small>Multiplier</small><span id="multOut">—</span></div>
        </div>
    </section>

    <section class="panel controls">
        <input type="text" id="amount" inputmode="decimal" value="1.00" aria-label="Stake">
        <button class="toggle" id="under" aria-pressed="true">Under</button>
        <button class="toggle" id="over" aria-pressed="false">Over</button>
        <button class="primary" id="roll-btn" style="grid-column: 1 / -1" disabled>Roll</button>
        <div class="status" id="status" style="grid-column: 1 / -1">Waiting for the casino…</div>
    </section>

    <section class="panel">
        <div class="history" id="history"></div>
        <p class="fair" id="fair">Each roll is provably fair: verify it with the revealed server seed at {{ url('/v1/fairness/dice') }}.</p>
    </section>
</main>

<script type="module">
    import { createGameBridge } from @json(asset('js/orange/bridge.js'));

    const RTP = @json($rtp ?? '0.9900');
    const MIN_CHANCE = @json($config['min_chance'] ?? '0.01');
    const MAX_CHANCE = @json($config['max_chance'] ?? '98.00');
    // Operators embed this page directly (relay play); Orange's host pages do from our own origin.
    const allowedOrigins = [...@json($allowedOrigins), window.location.origin];

    const $ = (id) => document.getElementById(id);
    let direction = 'under';
    let currency = '';
    let rolling = false;

    // Integer arithmetic in hundredths so the page shows exactly what the server pays.
    const hundredths = (s) => Math.round(parseFloat(s) * 100);
    const fmt = (h) => (h / 100).toFixed(2);
    function chanceH() {
        const t = hundredths($('target').value);
        return direction === 'under' ? t : 10000 - t;
    }
    function multiplier(ch) {
        // floor4(rtp * 100 / chance), as DiceEngine computes it.
        return (Math.floor((parseFloat(RTP) * 100 / (ch / 100)) * 10000 + 1e-7) / 10000).toFixed(4);
    }

    function render() {
        const t = hundredths($('target').value);
        const ch = chanceH();
        const valid = ch >= hundredths(MIN_CHANCE) && ch <= hundredths(MAX_CHANCE);
        $('targetOut').textContent = fmt(t);
        $('chanceOut').textContent = fmt(ch) + '%';
        $('multOut').textContent = valid ? multiplier(ch) + '×' : '—';
        const zone = $('zone');
        zone.style.left = direction === 'under' ? '0' : (t / 100) + '%';
        zone.style.width = (ch / 100) + '%';
        $('under').setAttribute('aria-pressed', String(direction === 'under'));
        $('over').setAttribute('aria-pressed', String(direction === 'over'));
        $('roll-btn').disabled = rolling || !valid || bridge.parentOrigin === null;
        return valid;
    }

    function setStatus(text, error = false) {
        $('status').textContent = text;
        $('status').className = 'status' + (error ? ' err' : '');
    }

    const bridge = createGameBridge({
        allowedOrigins,
        onMessage(type, payload) {
            if (type === 'player_info') {
                currency = payload.currency || '';
                $('balance').textContent = payload.balance ? `${payload.balance} ${currency}` : currency;
                setStatus('Ready.');
            } else if (type === 'balance_update') {
                $('balance').textContent = `${payload.balance} ${currency}`.trim();
            } else if (type === 'bet_placed_result') {
                rolling = false;
                if (!payload.success) {
                    setStatus(`Bet not placed: ${payload.error || 'unknown error'}`, true);
                } else if (payload.outcome) {
                    const won = payload.outcome.win === true;
                    $('roll').textContent = payload.outcome.roll;
                    $('roll').className = 'roll ' + (won ? 'win' : 'loss');
                    $('verdict').textContent = won ? `Won ${payload.payout_amount} ${currency}` : 'No win this time';
                    const chip = document.createElement('span');
                    chip.className = won ? 'win' : 'loss';
                    chip.textContent = payload.outcome.roll;
                    $('history').prepend(chip);
                    while ($('history').children.length > 12) $('history').lastChild.remove();
                    if (payload.fairness) {
                        $('fair').textContent = `Server seed hash ${payload.fairness.server_seed_hash} · client seed ${payload.fairness.client_seed} · nonce ${payload.fairness.nonce}`;
                    }
                    if (payload.player_balance) $('balance').textContent = `${payload.player_balance} ${currency}`;
                    setStatus('');
                }
                render();
            }
        },
    });

    $('target').addEventListener('input', render);
    $('under').addEventListener('click', () => { direction = 'under'; render(); });
    $('over').addEventListener('click', () => { direction = 'over'; render(); });
    $('roll-btn').addEventListener('click', () => {
        if (!render()) return;
        rolling = true;
        render();
        setStatus('Rolling…');
        bridge.send('place_bet', { amount: $('amount').value.trim(), params: { target: fmt(hundredths($('target').value)), direction } });
    });

    render();
    if (window.parent === window) {
        setStatus('Open Dice from your casino to play.', true);
    } else {
        bridge.ready();
    }
</script>
</body>
</html>
