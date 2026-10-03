# Orange — Operator Integration Guide

Audience: 3rd-party operators integrating Orange's crash-game provider into their own platform.

This document describes everything you need to consume the Orange API, embed the game UI, and keep your wallet in sync with bet/cashout settlement.

---

## 1. What Orange gives you

When your operator is onboarded, Orange provisions:

| Item | Format | Purpose |
|---|---|---|
| `operator_id` | UUID v7 | Identifies your operator (sent as `X-Operator-Id`) |
| `api_secret` | 64-char random string | HMAC-SHA256 signing key. **Never expose client-side.** |
| `base_url` | URL | Orange host root, e.g. `https://api.orange.example` |
| `reverb_host` | hostname | Reverb WebSocket host |
| `reverb_port` | int | Reverb WebSocket port |
| `reverb_app_key` | string | Reverb app key (public, embedded in JS) |

You receive these by secure channel during onboarding. Store the secret in your operator-side server config (`.env` or secret manager), never in client code.

---

## 2. Architecture at a glance

```
┌──────────────────────────────────┐                ┌───────────────────────┐
│  Your operator platform          │  HMAC POST     │  Orange provider      │
│                                  │ ─────────────► │                       │
│  · Wallet ledger (your DB)       │                │  REST API  /v1/*      │
│  · Player auth (your IdP)        │ ◄───────────── │  Reverb    :8090      │
│  · Server signs every request    │  Reverb WS     │  Round engine         │
│  · Browser renders iframe        │  (public)      │                       │
└──────────────────────────────────┘                └───────────────────────┘
              │                                             ▲
              │ iframe + postMessage bridge                 │
              ▼                                             │
        Player browser ◄──── Reverb WebSocket (direct, public) ────┘
```

Three integration surfaces:
1. **REST** — your server places/settles bets (HMAC-signed).
2. **WebSocket** — your browser (or server) listens to round events.
3. **Iframe** — embed `/crash` for the game UI; controls postMessage their intent to your page, which signs and forwards via REST.

---

## 3. HMAC signature

Every mutating REST call carries four headers. Three are computed; one is replay protection.

| Header | Value |
|---|---|
| `X-Operator-Id` | Your operator UUID |
| `X-Timestamp` | Unix seconds, ±30 s window |
| `X-Signature` | HMAC-SHA256 hex (lowercase, 64 chars) of the message below |
| `Idempotency-Key` | Fresh UUID per request (`<= 128 chars`) |

**Message format** (literal `\n` newlines, no trailing newline):

```
METHOD\nPATH\nBODY\nTIMESTAMP
```

- `METHOD` — uppercase (`POST`)
- `PATH` — request path with leading slash and api prefix, e.g. `/v1/bets`. The `api/` prefix is **not** included because Orange routes mount under `/v1/...`.
- `BODY` — raw request body bytes exactly as transmitted. Empty string `""` if no body.
- `TIMESTAMP` — the same integer you put in `X-Timestamp`.

Pseudo-code:

```php
$message = "POST\n/v1/bets\n" . $json . "\n" . $timestamp;
$signature = hash_hmac('sha256', $message, $apiSecret);
```

```js
// Node 18+
import crypto from 'crypto';
const message = `POST\n/v1/bets\n${body}\n${timestamp}`;
const signature = crypto.createHmac('sha256', apiSecret).update(message).digest('hex');
```

**Common pitfalls**

- Lowercase the hex output (`hex` in Node and `hash_hmac` PHP already do this).
- Use the **exact** bytes you send as body. Don't re-stringify after signing.
- The `api_secret` Orange gives you is plaintext. If you ever read it from a DB column with an encrypted cast, you must read through the model accessor (which decrypts), not the raw column.

---

## 4. REST API

Base URL: `{base_url}/v1`

### `POST /v1/bets` — place a bet

Auth: HMAC + Idempotency

**Request**

```json
{
    "round_uuid": "9e3a2c20-7d14-4d1a-9b8b-9d7c6e4d2c01",
    "player_external_id": "your-player-id-or-uuid",
    "amount": "10.00000000",
    "currency": "EUR",
    "auto_cashout_at": 1.75
}
```

- `round_uuid` — must be the UUID of a round currently in the `betting` state (you learn this via the WebSocket — see §5).
- `player_external_id` — your internal player identifier. Orange stores it as-is.
- `amount` — decimal string (Orange uses bcmath internally for money — pass it as a string to avoid float drift).
- `currency` — 3-letter code, must match what you've configured per operator.
- `auto_cashout_at` — optional. If set ≥ 1.01, Orange will auto-settle the bet when the multiplier first reaches that value.

**Responses**

- `201 Created`

```json
{
    "bet_id": 42,
    "status": "placed",
    "round_uuid": "9e3a2c20-7d14-4d1a-9b8b-9d7c6e4d2c01",
    "amount": "10.00000000",
    "currency": "EUR",
    "wallet_tx_id": "tx_abc123"
}
```

- `401 unauthorized` — signature/timestamp/operator problem (see §7)
- `402 insufficient_funds` — operator wallet rejected (when wallet callbacks are wired)
- `404 unknown_round` — `round_uuid` doesn't exist
- `409 betting_closed` — round has moved past the betting window
- `409 idempotency_conflict` — same `Idempotency-Key` already used with a different body
- `422` — validation error (amount ≤ 0, currency wrong size, etc.)

### `POST /v1/bets/{bet_id}/cashout` — cash out a placed bet

Auth: HMAC + Idempotency. Request body is empty.

**Responses**

- `200 OK`

```json
{
    "cashout_id": 99,
    "bet_id": 42,
    "multiplier": 1.85,
    "payout_amount": "18.50000000",
    "cashed_out_at": "2026-05-16T12:00:00+00:00",
    "is_auto": false
}
```

- `404 bet_not_found` — bet does not belong to your operator
- `409 already_cashed_out` / `409 already_settled` — bet was already settled (auto or manual)
- `409 round_not_running` — round has crashed or has not started yet

### `POST /v1/fairness/verify` — public verification (no HMAC)

Anyone can reproduce a round's crash point from the seed reveal. Useful for player-facing "verify" buttons.

```json
{
    "server_seed": "...",
    "client_seed": "...",
    "nonce": 42,
    "expected_server_seed_hash": "<optional 64-char hex>"
}
```

Returns the computed crash point and the seed hash for cross-check. No auth.

### `POST /v1/ping` — health check

HMAC-signed. Echoes back your payload. Use this in your CI / monitoring to validate the signing setup end-to-end.

---

## 5. WebSocket — Reverb / Pusher protocol

Orange broadcasts every round transition via Laravel Reverb, which speaks the Pusher protocol. You can use **pusher-js** directly (no Laravel Echo needed) or any Pusher-compatible client.

### Connection

```js
import Pusher from 'pusher-js';   // or <script src="https://js.pusher.com/8.2.0/pusher.min.js"></script>

const pusher = new Pusher('YOUR_REVERB_APP_KEY', {
    wsHost:   'reverb.orange.example',
    wsPort:   8090,
    wssPort:  8090,
    forceTLS: true,                    // false for local dev
    enabledTransports: ['ws', 'wss'],
    disableStats: true,
    cluster: 'mt1',                    // ignored by Reverb but required by pusher-js
});

const channel = pusher.subscribe('crash');   // public, no auth signing required
```

### Channels

| Channel | Auth | Notes |
|---|---|---|
| `crash` | public | All round-lifecycle events for the live crash game |
| `round.{round_uuid}` | public | Same events, scoped to one round (subscribe if you only care about one) |

### Events

All events fire on both channels above.

| Event | Payload | When |
|---|---|---|
| `round.betting_opened` | `{ round_uuid, server_seed_hash, nonce, betting_opened_at, betting_seconds }` | Betting window starts |
| `round.started` | `{ round_uuid, growth_rate }` | Round leaves betting → goes to `running` |
| `round.tick` | `{ round_uuid, multiplier, elapsed_seconds }` | ~10 Hz multiplier updates |
| `round.crashed` | `{ round_uuid, crash_point }` | Round just imploded |
| `round.settled` | `{ round_uuid, server_seed, next_round_in_seconds }` | Seed revealed, round closed |

**Important**: events use a dotted `broadcastAs` name (e.g. `round.tick`), so bind them as such:

```js
channel.bind('round.betting_opened', (data) => { /* enable place-bet UI */ });
channel.bind('round.tick',           (data) => { /* update multiplier */ });
channel.bind('round.crashed',        (data) => { /* round over */ });
```

### Server-side listening

If your stack consumes Pusher events server-side (e.g. for analytics or auto-actions), use any Pusher-protocol server client. Orange's Reverb does not gate the `crash` channel, so no auth signing is needed on these subscriptions.

---

## 6. Iframe embed

Orange ships a fully-rendered crash game page at `/crash` that you embed in your operator UI.

### Step 1 — Operator origin allow-listed

Send Orange the origin(s) you'll iframe from (`https://operator.example.com`, plus any staging hosts). Orange adds them to `EMBED_ALLOWED_ORIGINS`. The page sets `Content-Security-Policy: frame-ancestors 'self' <your-origins>` so browsers permit embedding.

### Step 2 — Embed the iframe

```html
<iframe
    src="https://api.orange.example/crash"
    allow="autoplay; fullscreen"
    allowfullscreen
    width="100%" height="720"
    style="border:0;">
</iframe>
```

`allowfullscreen` is required so the in-iframe fullscreen toggle works.

### Step 3 — postMessage bridge

The iframe carries a slim **operator HUD** at the bottom (stake input, Place Bet / Cash Out buttons, +/− steppers). Those buttons do **not** call Orange directly — they postMessage their intent to your parent page, which signs and forwards via REST.

#### Messages **iframe → parent**

| `type` | `payload` | When |
|---|---|---|
| `embed_ready` | `{}` | Iframe finished loading and is ready to receive `player_info` |
| `place_bet` | `{ amount, auto_cashout_at, round_uuid }` | User clicked Place Bet in the iframe |
| `cashout` | `{ bet_id }` | User clicked Cash Out in the iframe |
| `enter_fullscreen` | `{}` | User clicked the fullscreen toggle |
| `exit_fullscreen` | `{}` | User clicked the toggle while in fullscreen |

#### Messages **parent → iframe**

| `type` | `payload` | Purpose |
|---|---|---|
| `player_info` | `{ id, username, currency, balance }` | Identify the logged-in player to the iframe HUD |
| `round_state` | `{ phase, round_uuid }` | Sync state machine — phase ∈ `waiting`/`betting`/`running`/`crashed` |
| `bet_placed_result` | `{ success, bet_id, round_uuid, amount, auto_cashout_at, player_balance, error?, error_message? }` | Echo of your `/v1/bets` response |
| `cashout_result` | `{ success, bet_id, multiplier?, payout?, player_balance, error?, error_message? }` | Echo of your `/v1/bets/{id}/cashout` response |
| `auto_cashout_fired` | `{ multiplier, threshold }` | Tell the iframe HUD that auto-cashout has been triggered server-side |
| `fullscreen_state` | `{ active: bool }` | Sync the iframe's fullscreen icon state |

**Security**

- Parent: verify `event.origin === ORANGE_BASE_URL` before processing any message.
- Iframe: only accepts postMessage from origins listed in `embed.allowed_origins`. Anything else is dropped.
- The operator secret never crosses to the iframe. Iframe sends intents only.

#### Minimal parent-side wiring

```js
const ORANGE_ORIGIN = 'https://api.orange.example';
const iframe = document.getElementById('game-frame');

window.addEventListener('message', async (event) => {
    if (event.origin !== ORANGE_ORIGIN) return;
    const { type, payload } = event.data || {};

    switch (type) {
        case 'embed_ready':
            iframe.contentWindow.postMessage({
                type: 'player_info',
                payload: { id: currentPlayer.id, username: currentPlayer.username, currency: 'EUR', balance: '2500.00' },
            }, ORANGE_ORIGIN);
            break;

        case 'place_bet':
            const betRes = await yourServer.placeBet(payload);   // server signs and POSTs /v1/bets
            iframe.contentWindow.postMessage({
                type: 'bet_placed_result',
                payload: betRes,
            }, ORANGE_ORIGIN);
            break;

        case 'cashout':
            const coRes = await yourServer.cashoutBet(payload.bet_id);
            iframe.contentWindow.postMessage({
                type: 'cashout_result',
                payload: coRes,
            }, ORANGE_ORIGIN);
            break;
    }
});
```

---

## 7. Error codes

All errors return JSON `{ "error": "<machine_code>", "reason": "<more_detail>"? }`.

### Signature middleware (401)

| `reason` | Meaning | Fix |
|---|---|---|
| `missing_signature_headers` | One of the 4 headers absent | Add all four |
| `invalid_timestamp` | Header isn't a positive integer | Send Unix seconds, not ISO |
| `timestamp_outside_window` | Client/server clocks differ > 30 s | Sync NTP or recompute timestamp closer to send time |
| `unknown_operator` | `X-Operator-Id` not in DB | Use the UUID Orange gave you |
| `operator_inactive` | Operator suspended | Contact Orange |
| `signature_mismatch` | HMAC didn't verify | See §3 pitfalls; usually wrong secret or wrong path/body |
| `replay_detected` | Same (operator+timestamp+signature) seen twice | Don't retry the exact same request; generate a fresh `Idempotency-Key` |

### Bet placement (4xx)

| Code | `error` | Notes |
|---|---|---|
| 404 | `unknown_round` | Wrong / expired `round_uuid` |
| 409 | `betting_closed` | Window already shut |
| 409 | `idempotency_conflict` | Reused key with different body |
| 422 | (validation) | Inspect `errors` field |

### Cashout (4xx)

| Code | `error` | Notes |
|---|---|---|
| 404 | `bet_not_found` | Either no such bet, or it belongs to another operator |
| 409 | `already_cashed_out` | Cashout was already processed |
| 409 | `already_settled` | Round already finalised (won/lost) |
| 409 | `round_not_running` | Cashout outside the running window |

---

## 8. What you must implement on the operator side

- [ ] **Server-side signer**. A small helper that computes the HMAC for every outbound REST call. Keep `api_secret` out of client bundles.
- [ ] **Wallet ledger**. Debit on `/v1/bets` success, credit on `/v1/bets/{id}/cashout` success. Treat amounts as decimal strings end-to-end; use bcmath / `bignumber.js` / equivalent — never floats.
- [ ] **Idempotency** — generate a fresh UUID per request. If you retry a failed request, **reuse the same key** so Orange returns the same response (instead of placing a duplicate bet).
- [ ] **Round state tracking** — subscribe to the `crash` channel from the browser (and ideally also server-side) so you know which `round_uuid` is currently in `betting` state when the user clicks Place Bet.
- [ ] **postMessage bridge** between iframe and parent (see §6).
- [ ] **Fullscreen support** — `allow="fullscreen"` and `allowfullscreen` on the iframe, plus handle `enter_fullscreen`/`exit_fullscreen` messages.
- [ ] **Error UX** — surface `error_message` from any 4xx response (e.g. show "round closed" to the user when `betting_closed` comes back).
- [ ] **Reconciliation** — on cashout success, the `payout_amount` in the response is authoritative. Credit your wallet with that exact value (it may differ from `amount × multiplier` due to rounding).
- [ ] **Currency** — your operator config tells Orange which currency code(s) you accept. Sending a mismatched currency returns a 422.

---

## 9. Testing checklist

Before going live, run through:

1. **Signing**: call `POST /v1/ping` with a sample payload, verify `201` with the echoed body.
2. **Clock skew**: shift your server clock by 60 s and confirm `timestamp_outside_window`.
3. **Wrong secret**: change one char of `api_secret` and confirm `signature_mismatch`.
4. **Replay**: send the same signed request twice without a fresh idempotency key — confirm `replay_detected`.
5. **Place + cashout happy path**: connect to WS, wait for `betting_opened`, place a bet, wait for `round.started`, send cashout before `round.crashed`. Confirm wallet ledger matches the returned `payout_amount`.
6. **Round-crashed loss**: place a bet, do NOT cashout, wait for `round.crashed`. Confirm your local debit stays (no payout).
7. **Auto cashout**: place a bet with `auto_cashout_at: 1.20`. Confirm server-side settlement when multiplier crosses 1.20.
8. **Iframe origin**: load the iframe from each origin you'll use (production + staging). Confirm no CSP `frame-ancestors` console error.
9. **Fullscreen**: toggle in/out, verify icon swap.
10. **postMessage**: inspect DevTools — only messages from `event.origin === <orange_base_url>` are accepted on your side.

A working reference operator is shipped in this repo at `../OrangeConsumer` — clone it, point `.env` at your Orange instance, and use it as a known-good baseline when debugging your own integration.

---

## 10. Sample reference flow (sequence)

```
Operator Browser                Operator Server               Orange API
─────────────────               ───────────────               ──────────
                       ─── WS ────────── subscribe 'crash' ────────────►
                       ◄────────── round.betting_opened ──────────────
[user clicks Place Bet inside iframe HUD]
       │
       ├── postMessage 'place_bet' ──►
                                       │
                                       ├── HMAC-sign POST /v1/bets ──►
                                       ◄──── 201 { bet_id: 42 } ─────
       ◄── postMessage 'bet_placed_result' ──┤
                       ◄────────── round.started ──────────────────────
                       ◄────────── round.tick (×N) ─────────────────────
[user clicks Cash Out at 1.85x]
       │
       ├── postMessage 'cashout' ──►
                                       │
                                       ├── HMAC-sign POST /bets/42/cashout ──►
                                       ◄── 200 { payout_amount: '18.50' } ──
       ◄── postMessage 'cashout_result' ──┤
                       ◄────────── round.settled ────────────────────────
```

---

## Contact

Technical questions, secret rotation requests, or production allow-listing: `dev@arkingroup.com`.

Production credentials and the production `base_url` are issued through Orange's onboarding flow — do **not** commit them to source control. Use environment variables or your secret manager.
