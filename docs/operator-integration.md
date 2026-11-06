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
    "nonce": 1,
    "expected_server_seed_hash": "<optional 64-char hex>",
    "house_edge": 0.03
}
```

Pass the game's `house_edge` (`0.03` for the standard crash game). Without it the default edge is
used, which gives a different crash point for a game configured with another edge.

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
| `game.{code}` | public | Same events for one game, e.g. `game.crash` (new; every round-based game has one) |

### Events

All events fire on both channels above.

| Event | Payload | When |
|---|---|---|
| `round.betting_opened` | `{ round_uuid, server_seed_hash, nonce, betting_opened_at, betting_seconds }` | Betting window starts |
| `round.started` | `{ round_uuid, growth_rate }` | Round leaves betting → goes to `running` |
| `round.tick` | `{ round_uuid, multiplier, elapsed_seconds }` | ~10 Hz multiplier updates |
| `round.crashed` | `{ round_uuid, crash_point }` | Round just imploded |
| `round.settled` | `{ round_uuid, server_seed, next_round_in_seconds }` | Seed revealed, round closed |
| `round.bets` | `{ round_uuid, bets: [{ id, player, amount, currency, multiplier, payout }] }` | (new) Bets were placed or cashed out. These are rows of the round's live bet list, shown to every player. `player` is masked (`1***5`). `multiplier` and `payout` are set once a bet cashes out. A tick's auto-cashouts come together, at most 30 rows a message. Safe to ignore |

Every round has its own server seed: its SHA-256 hash is published in `round.betting_opened`
(`server_seed_hash`, with `nonce`) and the seed itself in `round.settled`. A revealed seed never
decides another round. To verify a round, call `POST /v1/fairness/verify` with that round's
`server_seed`, the house client seed, its `nonce`, `expected_server_seed_hash` and the game's
`house_edge`.

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
| `place_bet` | `{ amount, auto_cashout_at, round_uuid, slot? }` | User clicked Place Bet in the iframe. `slot` (0 or 1) only when your `player_info` said `bet_slots: 2` |
| `cashout` | `{ bet_id }` | User clicked Cash Out in the iframe |
| `enter_fullscreen` | `{}` | User clicked the fullscreen toggle |
| `exit_fullscreen` | `{}` | User clicked the toggle while in fullscreen |
| `launch_game` | `{ code }` | (new, informational) The player opened another game from the lobby |
| `back_to_lobby` | `{}` | (new, informational) The player left a game for the lobby, or asked to leave it |
| `bet_history` | `{}` | (new, optional) The player opened My bets. Sent only if your `player_info` said `bet_history: true` |

#### Messages **parent → iframe**

| `type` | `payload` | Purpose |
|---|---|---|
| `player_info` | `{ id, username, currency, balance, bet_history?, bet_slots? }` | Identify the logged-in player to the iframe HUD. `bet_history: true` (optional) shows the My bets button; only send it if you answer `bet_history`. `bet_slots: 2` (optional) offers a second bet per round, see below |
| `round_state` | `{ phase, round_uuid }` | Sync state machine — phase ∈ `waiting`/`betting`/`running`/`crashed` |
| `bet_placed_result` | `{ success, bet_id, round_uuid, amount, auto_cashout_at, player_balance, error?, error_message?, slot? }` | Echo of your `/v1/bets` response, with the `slot` its `place_bet` named |
| `cashout_result` | `{ success, bet_id, multiplier?, payout?, player_balance, error?, error_message? }` | Echo of your `/v1/bets/{id}/cashout` response |
| `auto_cashout_fired` | `{ multiplier, threshold }` | Tell the iframe HUD that auto-cashout has been triggered server-side |
| `fullscreen_state` | `{ active: bool }` | Sync the iframe's fullscreen icon state |
| `bet_history_result` | `{ success, bets?, error? }` | (new, optional) Answer to `bet_history`: the player's latest bets on this game, newest first, each `{ bet_id, placed_at, amount, currency, status, multiplier, payout_amount, round_id, round_uuid, crash_point }`. `multiplier` is the cash-out multiplier (won bets only). `crash_point` is `null` until the round is over |

**Two bets a round (optional).** Like Aviator, the HUD can hold a second bet, with its own stake,
auto-cashout and AUTO BET. It only offers one if your `player_info` says `bet_slots: 2`, and
then your page must:
- handle two `place_bet` calls in the same round, each its own `/v1/bets` call with its own
  idempotency key;
- put the `slot` from each `place_bet` into its `bet_placed_result`. The two calls can answer in
  either order, and the slot is how the HUD tells them apart. Without it, results are matched in
  the order the bets were sent.

`cashout_result` already names its `bet_id`; with two bets, also send `bet_id` with a failed
cashout and with `auto_cashout_fired`. Orange's own `/play/{code}` and `/lobby` pages do all of
this.

The recent-crashes rail needs nothing from you. Each chip opens that round's details (seeds,
HMAC and the result worked out from them) from the public `GET /v1/rounds/{uuid}`.

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
| 403 | `game_not_enabled` | The game is switched off for your operator |
| 409 | `game_unavailable` | The game is disabled or in maintenance |
| 422 | `currency_not_supported` | The game has limits configured, but none for this currency |
| 422 | `bet_below_min` / `bet_above_max` | Outside the per-currency limits; `limit` holds the bound. A stake must also stay below the max win |
| 422 | (validation) | Inspect `errors` field. `amount` must be a plain decimal (`"1.50"`; no exponent or sign) |

Every 4xx above means **no bet was placed and no debit was sent**, so it is safe to refund the stake on your side.

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

## 11. Multi-game API

Everything above keeps working unchanged. The endpoints below are new and optional; they serve
every game, crash included.

### Signing GET requests and signature v2

- **Query strings are signed.** When a request has a query string, PATH in the signed message is
  the path plus `?` plus the query string, byte for byte as you send it:
  `GET\n/v1/games?currency=EUR\n\n1730000000`. Requests without a query string sign exactly as before.
- **Signature v2 (opt-in).** Send `X-Signature-Version: 2` and append `\n` + the `Idempotency-Key`
  to the signed message: `METHOD\nPATH\nBODY\nTIMESTAMP\nIDEMPOTENCY_KEY`. The key is then covered
  by the signature. Two *different* requests with the same method, path, body and timestamp
  second, such as two cashouts with empty bodies, are no longer rejected as `replay_detected`.
  Under v1 they are: give each request its own timestamp second, or switch to v2.

### `GET /v1/games` — your game catalogue

Lists the games enabled for your operator, ordered for display, with your effective limits per currency.

```json
{ "data": [
  { "code": "crash", "name": "DeepDive", "type": "crash", "family": "round", "category": "crash",
    "thumbnail_url": null, "status": "active", "rtp": "0.9700",
    "currencies": "any", "limits": {}, "play_mode": "relay" },
  { "code": "dice", "name": "Dice", "type": "dice", "family": "instant", "category": "originals",
    "thumbnail_url": "https://…/storage/thumbs/dice.webp", "status": "active", "rtp": "0.9900",
    "currencies": "listed",
    "limits": { "EUR": { "min_bet": "0.10000000", "max_bet": "100.00000000", "max_win": "10000.00000000" } },
    "play_mode": "direct" } ] }
```

- `family`: `round` (shared rounds: crash), `instant` (resolved in one call: dice), or `session`
  (several steps: mines).
- `currencies: "any"` with empty `limits`: the game takes any currency without limits. With
  `"listed"`, only the currencies in `limits` are accepted.
- `status: "maintenance"`: listed, but bets and launches answer `409 game_unavailable`.

### `POST /v1/games/{code}/launch` and `POST /v1/lobby/launch` — open a game or the lobby

```json
// request
{ "player_external_id": "12*3456", "currency": "EUR", "language": "tr",
  "return_url": "https://casino.example/games", "username": "orhuntest" }
// 201 (lobby: "lobby_url" instead of "play_url")
{ "launch_token": "lt_6vQ…", "play_url": "https://orange…/play/dice?token=lt_6vQ…",
  "expires_at": "2026-11-02T10:02:00+00:00" }
```

Open the URL in your iframe within `expires_at` (2 minutes). The token works **once**. In direct
play mode, Orange then debits and credits your wallet through the wallet callbacks of §8 for bets
the player places in the page. Those bets were not started by your backend, so your credit and
rollback handlers must accept them. Errors: `404 unknown_game`, `403 game_not_enabled`,
`409 game_unavailable`, `422 currency_not_supported`, `422` validation.

`username` (optional) is the name your site shows the player. The game shows it in place of
`player_external_id`. Each launch updates it, and one without it keeps the last. Names over 64
characters are cut, never refused.

### `POST /v1/games/{code}/bets` — place a bet on any game (relay mode)

The same idempotency and wallet rules as `POST /v1/bets`.

- **Round games (crash).** `round_uuid` is optional: by default the bet goes on the round that is
  taking bets now. Player choices go in `params`, e.g. `{ "auto_cashout_at": "2.00" }`. The
  response has the same keys as `POST /v1/bets`, plus `game`. Cash out with
  `POST /v1/bets/{id}/cashout`.
- **Instant games (dice).** The bet resolves in the same call. The response carries the outcome,
  multiplier and payout (see the game's page in this guide).

```json
{ "player_external_id": "12*3456", "amount": "2.50", "currency": "EUR",
  "params": { "auto_cashout_at": "2.00" } }
```

Extra errors: `404 unknown_game`, `404 unknown_round` (the round isn't this game's), and
`422 invalid_params`.

### Fairness: `GET /v1/fairness/{code}` and `GET /v1/fairness/{code}/verify`

Public, no HMAC, 60 requests a minute per IP.

- `GET /v1/fairness/{code}` returns what a player needs to verify the game's outcomes: the
  algorithm, the game's house edge and, for crash, the public `house_client_seed`.
- `GET /v1/fairness/{code}/verify?server_seed=…&client_seed=…&nonce=…[&expected_server_seed_hash=…]`
  recomputes an outcome with the game's own parameters, so you don't need to pass `house_edge`.
  Games that are no longer offered stay verifiable. For crash, the answer also gives the steps:
  `hex` (the HMAC's first 13 hex digits), `decimal` (their value) and `instant_crash` (whether
  the first 8 hit the house edge).
- `GET /v1/rounds/{uuid}/bets` returns the round's live bet list (`round_uuid`, `state`, and `bets`
  as in `round.bets`, biggest stake first). Only bets that count are listed, not stakes that
  were declined or rolled back, and nobody's auto-cashout target is shown. The game page's
  All bets panel loads it when it joins a round late.
- `GET /v1/rounds/{uuid}` returns one round's details: `round_id`, `game`, `state`, its times,
  `result` (`{ crash_point }`, `null` while the round is live) and `fairness`
  (`server_seed_hash`, `server_seed`, `client_seed`, `nonce`, and `verification`, the outcome
  recomputed as by the verify endpoint above). `server_seed` and `verification` are `null` until
  the round settles. The game page's round details use this endpoint.

### Direct play: `/play/{code}`

Direct play is set up **per game** for your operator by Orange (play mode `direct`). Until it is,
the game is relay-only for you:
- launching it answers `403 direct_play_not_enabled`;
- the lobby doesn't list it;
- Orange never starts bets on it for you, so it never debits your wallet for them.

`GET /v1/games` shows each game's `play_mode`.

The `play_url` from a launch opens Orange's host page in your iframe. The page hosts the game,
and the game places bets through Orange's player API, authenticated by a play session that the
launch token was exchanged for. You don't relay any `place_bet` or `cashout` messages.

What you need:
- **Your origin allowed:** add it to your operator's `embed_origins` (Orange sets this in your
  operator configuration).
- **Rollbacks that may arrive before their debit.** If your answer to a debit doesn't reach
  Orange in time, Orange rolls the stake back about a minute later (`rollback:{bet_id}`). The
  rollback names the debit in `reference.debit_tx_id`. If you haven't seen that debit yet,
  acknowledge the rollback **and refuse that debit `tx_id` from then on**: it may still arrive
  late, and it must not take a stake that was already rolled back.
- **A wallet that accepts Orange-originated bets:** a direct bet's stake is debited
  synchronously, with `tx_id` = `bet:ps-…`, before the bet counts.
  - A 4xx from your wallet rejects the bet (`wallet_rejected`, with your error code passed to
    the game).
  - No answer rolls it back (`rollback:{bet_id}`).

  Wins and refunds come as usual through `/wallet/credit` and `/wallet/rollback`.
- **Optional:** answer the page's `embed_ready` with any message (for example
  `fullscreen_state`), so the page can forward `enter_fullscreen` / `exit_fullscreen` and
  `back_to_lobby` to you.

Reloading the page keeps the session. A launch token can't be reused.

The game page has a **My bets** list (the player's latest bets on that game). Orange's host
page serves it from the player API, so you don't need to do anything for it.

### The lobby: `/lobby`

`POST /v1/lobby/launch` returns a `lobby_url`; open it in your iframe.
- **Contents:** every game enabled for you, as tiles grouped by Orange's categories, with
  search, "Recent" (this player's last games) and "Popular" (your players' most played games
  over the last week). Games in maintenance are shown greyed out.
- **Playing:** a tile opens the game in the same iframe, with a "← Lobby" bar. Bets are direct
  play, as described above.
- **Bridge messages to your page:** `launch_game` `{ code }` when a game opens, and
  `back_to_lobby` when the player returns.

Look and embedding are set on your operator configuration (flat keys):

| Key | Value |
|---|---|
| `embed_origins` | Your origins, comma-separated, e.g. `https://casino.example, https://m.casino.example` |
| `lobby_logo_url` | An `https://` image URL shown in the lobby header |
| `lobby_primary_color` / `lobby_accent_color` / `lobby_background_color` | `#rrggbb` colours |

Anything that isn't an `https://` URL or a `#rrggbb` colour is ignored, and the default is used.

### Dice (`code: dice`, instant)

Roll 0.00–99.99 and bet that the roll lands under or over a target.
- **Win chance:** T% for "under T", (100 − T)% for "over T". The game's range applies (by
  default 0.01%–98%).
- **Multiplier:** `floor4(rtp × 100 / chance)`, e.g. 1.9800 at 50%.

The bet resolves in the same call:

```json
// POST /v1/games/dice/bets
{ "player_external_id": "12*3456", "amount": "2.00", "currency": "EUR",
  "params": { "target": "50.00", "direction": "under" } }
// 201
{ "bet_id": 9123, "game": "dice", "status": "won", "amount": "2.00000000", "currency": "EUR",
  "multiplier": "1.9800", "payout_amount": "3.96000000",
  "outcome": { "roll": "44.30", "target": "50.00", "direction": "under", "win": true, "payout_multiplier": "1.9800" },
  "fairness": { "server_seed_hash": "…", "client_seed": "…", "nonce": 1 },
  "wallet_tx_id": "88121" }
```

Money flow:
1. The stake is debited **synchronously** (`tx_id = bet:{Idempotency-Key}`), and the roll
   happens only after your wallet accepts. If you took the stake yourself first, answer the
   same `tx_id` idempotently, as afq already does for crash.
2. A win is credited with `tx_id = payout:{bet_id}`, capped at the max win.
3. A 4xx from your wallet means `402 wallet_rejected`, with your error code in `reason`; no
   roll. No answer means `503 wallet_unavailable`; the stake is rolled back with
   `rollback:{bet_id}`.
4. A `bet.settled` webhook follows every resolved bet:
   `{ bet_id, game, player_external_id, status, amount, currency, multiplier, payout_amount, outcome, settled_at }`.

**Fairness and seeds.** Each player has one seed pair for all instant games. Every bet uses the
next nonce, and the server seed stays secret (only its SHA-256 is shown) until the player
rotates the pair:
- `GET /v1/players/{external_id}/seeds` returns `{ server_seed_hash, client_seed, nonce, next_server_seed_hash }`.
  `next_server_seed_hash` commits to the server seed the *next* pair will use, before the player
  picks its client seed.
- `POST /v1/players/{external_id}/seeds/rotate` takes an optional `{ "client_seed": "…" }`.
  It returns `previous` (with the revealed `server_seed`) and `current`. While a bet on the
  pair is still settling, it answers `409 bets_in_progress`.

Verify a bet with
`GET /v1/fairness/dice/verify?server_seed=…&client_seed=…&nonce=…&target=…&direction=…&rtp=…`.
`rtp` is the one the bet was played with (it's in its `outcome`), so a later RTP change doesn't
affect it. Stakes have at most 8 decimals.
The algorithm is spelled out at `GET /v1/fairness/dice`.

**Embedding the page yourself (relay play).** Embed `/games/dice`. The page posts
`place_bet` with `{ amount, params: { target, direction } }`. Call
`POST /v1/games/dice/bets`, then answer `bet_placed_result` with `success: true` plus the
response's `status`, `outcome`, `multiplier`, `payout_amount`, `fairness` and the player's new
balance (`player_balance`). With direct play (`/play/dice`), Orange does all of this for you.

### Max win

When a game has limits for the bet's currency, a payout never exceeds the effective `max_win`.
A crash bet that reaches its max win is cashed out automatically, with `is_auto: true`. The max
win is fixed when the bet is placed.

---

## Contact

Technical questions, secret rotation requests, or production allow-listing: `dev@arkingroup.com`.

Production credentials and the production `base_url` are issued through Orange's onboarding flow — do **not** commit them to source control. Use environment variables or your secret manager.
