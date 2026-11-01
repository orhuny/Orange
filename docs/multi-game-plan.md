# Orange — Multi-game platform plan

Status: **Phase 1 approved, with the decisions in §13. Phase 0 hotfix (D1) implemented.**
Audience: Orange engineers implementing phases 2–9, and reviewers of the afq integration.

This plan turns Orange from a single crash game ("DeepDive") into a catalogue of games of
different types, with an embeddable lobby. The existing afq integration must keep working
unchanged; it also covers OrangeConsumer and OrangeApp, which use the same `/v1/bets`, `/crash`
and `crash` channel contract.

---

## 0. Findings from reading the code

These are observations about today's code. Several differ from the task description, and three are
existing defects that affect the design.

### 0.1 Verified as described
- Domains `Game`, `Operator`, `Wallet`, `ProvablyFair`; `Risk` exists but is empty.
- `RoundOrchestrator` state machine `waiting → betting → running → crashed → settled`
  (`RoundState::next()`), `BetPlacementService`, `CashoutService` (lock-free, race-safe via
  `UPDATE … WHERE status='placed'`), `MultiplierCalculator`, `ProvablyFairService`
  (bustabit HMAC, divisor = `round(1 / games.house_edge)`).
- `crash:loop --game=crash` drives one game; `crash:tick` broadcasts every 100 ms but only for
  the **newest** running round (`latest('started_at')->first()`).
- Events go to `round.{uuid}` + `crash`. All are **public** channels (`Echo.channel(...)`), so the
  callbacks in `routes/channels.php` never run.
- HMAC: `hex(HMAC-SHA256(secret, METHOD\nPATH\nBODY\nTIMESTAMP))` in `X-Signature`, plus
  `X-Operator-Id`, `X-Timestamp`; replay cache; `Idempotency-Key` required on mutations.
  **The query string is not part of the signed PATH**, which matters for any new `GET` endpoint.
- Admin: Filament **4** (the codebase uses `Filament\Schemas`, although ARCHITECTURE.md says v3),
  `AccessControl::PERMISSIONS` / `OPERATOR_PERMISSIONS`, `Gate::before`,
  `AuthorizesWithPermissions`, `scopeToOperator`, `DateRangeFilter` on Bets and Rounds.

### 0.2 Not in the brief, but part of the live contract
- The bridge has **two more parent → iframe messages** than the brief lists: `round_state` and
  `auto_cashout_fired`. They must be preserved.
- The iframe never talks to Orange's API. It posts `place_bet` / `cashout` intents to the
  parent, the operator's backend calls `/v1/bets` server-to-server, and the iframe locks onto
  the first allow-listed parent origin that messages it. This is the **relay** model.
- afq **pre-debits** its own wallet under `tx_id = "bet:{Idempotency-Key}"` and relies on
  Orange's later debit callback with the same `tx_id` replaying idempotently. Crash credits use
  `tx_id = "cashout:{bet_id}"`. Both tx_id formats are part of the contract.
- afq's **credit and rollback callbacks reject any bet afq did not place itself**
  (`OrangeBet` lookup → `bet_not_found`). So a game launched through `/play` or the lobby, where
  Orange places the bet, **cannot be paid out to afq's wallet** without changes on afq's side.
  That is why launch/lobby play is an opt-in mode (§5.1).
- Wallet callbacks are **queued and never awaited**: a crash bet is live before the operator has
  accepted the debit. Crash with afq is safe only because afq pre-debits. **Instant games cannot
  work this way**, because the outcome would be revealed before the stake is confirmed (§3.3).

### 0.3 Existing defects found along the way
| # | Severity | Defect | Where |
|---|---|---|---|
| D1 | **Critical** | One server seed serves up to 1000 rounds (`seed_rotation_rounds`), yet every `round.settled` broadcast **and** `round.settled` webhook reveal the raw `server_seed`. The client seed is a single static house value, which must be public for verification (and is the committed default `orange-house-seed-rotate-me` in the local `.env` and `.env.example`). `BettingOpened` broadcasts the nonce. Once round N settles, anyone can compute the crash points of rounds N+1 … 1000 before betting opens. `SeedRotator::reveal()` correctly refuses to reveal an active seed, but the broadcast bypasses it. `BroadcastEventsTest` asserts the leak as intended behaviour. | `RoundSettled::broadcastWith`, `WebhookOnGameEvents::onRoundSettled` |
| D2 | High | Operator users hold `rounds.view`, but `GameRoundResource` is not operator-scoped. They see every round's `total_bet_amount` / `total_payout_amount`, which include other operators' stakes. | `GameRoundResource::getEloquentQuery` |
| D3 | High | `crash:loop` orphan clean-up sets rounds to `settled` without touching their bets. Bets stay `placed` forever, so the stake is never refunded or rolled back. `WalletService::rollback()` has no caller. | `RunCrashLoopCommand` |
| D4 | Medium | A wallet callback that still fails after the in-job retries is marked `failed` and never retried. Nothing reconciles owed credits or rollbacks. | `WalletService::dispatchCallback` |
| D5 | Low | `amount` is validated as `numeric`, so `"1e3"` passes and then `bcadd()` throws `ValueError`, giving a 500. | `BetController` |
| D6 | Low | `POST /v1/fairness/verify` accepts any `house_edge` in (0, 1), but an edge above ⅔ gives a divisor below 2 and the endpoint returns 500. The new `CrashVerifier` bounds it (0.000001–0.6); the legacy endpoint gets the same rule in Phase 4. | `VerifyController` |

**D1 is fixed** by the Phase 0 hotfix (§4.4): one seed per round, needing no schema change and
keeping the `round.settled` payload shape. D2 (scoping) and D3–D5 (money) are fixed in Phase 3,
D6 in Phase 4.

| # | Severity | Defect | Where |
|---|---|---|---|
| D7 | Low | The signature doesn't cover `Idempotency-Key`, and a cashout has an empty body. Two *different* cashout calls on one bet in the same second (for example afq's manual click racing its auto-cashout) therefore carry identical signatures, and the second gets 401 `replay_detected` instead of 409 `already_settled`. afq handles it as a failed cashout. **Fixed in Phase 4 with an opt-in signature v2** (`X-Signature-Version: 2`), which also signs the Idempotency-Key. Only adding the key to the replay-cache key would have been unsafe: the key isn't signed under v1, so a captured request could be resent under a new key. v1 is unchanged and documented. | `VerifyOperatorSignature` |

---

## 1. Target architecture

```
                     ┌──────────────── Operator API /v1 (HMAC) ─────────────────┐
 operator backend ──►│ legacy: /bets, /bets/{id}/cashout, /ping, /fairness/verify│
                     │ new:    /games, /games/{code}/launch, /games/{code}/bets, │
                     │         /lobby/launch, /game-sessions/*, /players/*/seeds │
                     └────────────────────────────┬─────────────────────────────┘
                                                  ▼
 player browser ──► /play/{code} · /lobby ──► Player API /play-api (play-session bearer)
   (iframe)          /crash (alias, relay)                 │
                                                           ▼
                         ┌────────────── GamePlayService (core) ─────────────────┐
                         │ limits · enablement · bet rows · wallet · idempotency  │
                         │ seeds/nonces · payout cap · events                     │
                         └───────┬──────────────────────────────┬────────────────┘
                                 ▼                              ▼
                    RoundBasedEngine (crash, …)        InstantEngine / SessionEngine
                    RoundOrchestrator + loop/tick      (dice, plinko / mines)
                                 └──────── EngineRegistry (games.type) ─────────┘
```

**Division of responsibility.** Engines own math, outcome shape, public state, config validation
and fairness verification. Engines **never touch money or the database's money columns**. The core
(`GamePlayService` + existing `WalletService`) owns limits, debit, credit, rollback, payout capping,
idempotency and persistence. A new game therefore cannot get the money wrong. It can only produce a
multiplier, and the core caps and pays it.

---

## 2. Engine abstraction (Phase 2)

New namespace `App\Domain\Game\Engines`.

```php
enum EngineFamily: string { case RoundBased = 'round'; case Instant = 'instant'; case Session = 'session'; }

interface GameEngine
{
    /** Matches games.type, e.g. 'crash', 'dice'. */
    public static function type(): string;

    public function family(): EngineFamily;

    /**
     * Validates and normalises games.config for this engine (gets the game for checks
     * against its other settings, e.g. a plinko table's RTP against rtp_target).
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     * @throws InvalidGameConfig
     */
    public function validateConfig(Game $game, array $config): array;

    /** Highest multiplier the game can pay as a decimal string ('0' = unbounded); used for max-win/exposure. */
    public function maxMultiplier(Game $game): string;

    /** Public verifier for /v1/fairness/{code}/verify. */
    public function verifier(): FairnessVerifier;
}

interface FairnessVerifier
{
    /** Laravel validation rules for the verify endpoint's game-specific inputs. */
    public function rules(): array;

    /** Recomputes an outcome from revealed seeds; must be pure. */
    public function verify(Game $game, string $serverSeed, string $clientSeed, int $nonce, array $params): array;
}

interface RoundBasedEngine extends GameEngine
{
    public function schedule(Game $game): RoundSchedule;              // waiting/betting seconds, tick interval
    public function validateBetParams(Game $game, array $params): array; // crash: auto_cashout_at
    public function resolveRound(GameRound $round): RoundOutcome;      // crash: crash point (stored, secret until finished)
    public function runningDuration(GameRound $round): float;          // how long the running phase lasts
    public function publicTick(GameRound $round, CarbonInterface $now): array; // never contains the outcome
    public function publicResult(GameRound $round): array;             // only callable once the round is finished
}

interface InstantEngine extends GameEngine
{
    public function validateParams(Game $game, array $params): array;   // dice: target, direction
    public function multiplierFor(Game $game, array $params): string;   // known before play (dice)
    public function play(Game $game, array $params, RandomStream $rng): InstantResult; // pure, deterministic
}

interface SessionEngine extends GameEngine                               // mines (later)
{
    public function validateParams(Game $game, array $params): array;
    public function start(Game $game, array $params, RandomStream $rng): SessionStep;
    public function act(GameSession $session, array $action, RandomStream $rng): SessionStep;
    public function cashout(GameSession $session, RandomStream $rng): SessionStep;
}
```

Value objects (readonly): `InstantResult{array $outcome, string $multiplier}`,
`SessionStep{array $publicState, bool $finished, ?string $multiplier, ?array $outcome}`,
`RoundOutcome{array $secret}` (for example `['crash_point' => '3.21']`), `RoundSchedule`.
All multipliers are decimal strings with 4 decimal places, never floats.

**Session engines are stateless.** They recompute hidden state (mine positions) from the seed pair
and nonce on every call. No secret state is stored next to the session, so there is no column to
leak.

**`EngineRegistry`** maps `type → class` from `config/games.php` (`'engines' => ['crash' => CrashEngine::class, 'dice' => DiceEngine::class]`).
It resolves engines from the container and throws `UnknownGameType`. Adding a game means adding
one config line.

**Crash migration (no behaviour change).** `CrashEngine` is an *adapter* over the existing
`ProvablyFairService`, `MultiplierCalculator` and `RoundOrchestrator`; none of their maths moves:
- `resolveRound` → `ProvablyFairService::crashPoint(seed, client_seed, nonce, divisorForGame)`
  (the exact call in `RoundOrchestrator::start`).
- `runningDuration` → `MultiplierCalculator::durationToReach`.
- `publicTick` → today's `MultiplierTick` payload.
- `RoundOrchestrator::start` asks the registry for the engine instead of calling the math directly.
  `crash:loop` keeps its name and options and resolves the engine from `--game`.
- Crash keeps its legacy HMAC message (`"{client_seed}:{nonce}"`, no cursor) so every historical
  round still verifies.

**As built in Phase 2:**
- `GameEngine`, `RoundBasedEngine`, `FairnessVerifier`, `EngineRegistry` (singleton, from
  `config/games.php`), `CrashEngine` + `CrashVerifier`.
- `RoundOrchestrator::start` takes the outcome from `resolveRound`; `crash:loop` takes its
  schedule and running time from the engine.
- Until `games.type` exists (Phase 3), `Game::engineType()` returns `crash` for every game.
- `InstantEngine` and `SessionEngine` are added with their first engines (dice in Phase 6, mines
  later), because their value objects need `RandomStream` and `game_sessions`.
- `crash:tick` is unchanged until Phase 5's multi-round broadcaster, which will use `publicTick`.
- Engines must be **stateless**: the registry is a singleton and keeps engine instances for the
  life of the process (and later of an Octane worker).

**Safety net (first commit of Phase 2):** `tests/Feature/Compat/AfqContractTest.php`. It replays
afq's real sequence: signed `/v1/bets` → wallet debit callback with `tx_id=bet:{K}` → cashout →
credit `cashout:{id}`. It snapshots the JSON keys of every response, the broadcast payloads and
channel names, the webhook payloads, and the bridge message names in the rendered `/crash` page.
The suite must stay green through every phase.

---

## 3. Data model (Phase 3; the seed and session tables land with their first user)

All migrations are reversible. Money is `decimal(18,8)`, multipliers `decimal(14,4)`.

### 3.1 `games` — add catalogue columns
| column | type | backfill / default |
|---|---|---|
| `type` | string(32), index | `'crash'` for existing rows; NOT NULL after backfill |
| `slug` | string, unique | `= code` |
| `category_id` | FK `game_categories`, nullable | crash → seeded category `crash` |
| `thumbnail` | string nullable | path on `public` disk |
| `sort_order` | int default 0 | 0 |
| `status` | *exists* | widen to `active` / `maintenance` / `disabled` / `draft` (string, enum cast) |
| `config` | *exists* (json) | now validated by `engine->validateConfig()` in `Game::saving` |

`code` stays the stable API identifier (`/v1/games/{code}`); `slug` is used in lobby URLs and
asset paths. The `rtp_target + house_edge = 1` invariant stays. Dice uses `house_edge` directly;
table-driven games (plinko) validate that their table's RTP matches `rtp_target` in `validateConfig`.

The brief asks for a `category` column. I propose a `game_categories` table instead
(`id, slug unique, name, sort_order, is_active`), because Phase 7 needs categories to be managed
and ordered in the panel. A string column cannot be renamed or reordered in one place.

### 3.2 `operator_game` (pivot)
```
id, operator_id uuid FK, game_id FK, enabled bool default true,
sort_order int nullable, settings jsonb nullable  -- launch settings: play_mode, lobby visibility, default language
unique(operator_id, game_id), timestamps
```
**Access (as built; replaces the planned pivot backfill):** `games.access` decides who may play.
- `all`: every operator, unless an `operator_game` row disables it for one.
- `listed`: only operators with an enabled row; this is the default for new games.

Crash is backfilled to `all`. afq, OrangeConsumer, OrangeApp and operators created later keep
playing it with no rows at all, and `/v1/bets` behaves as before. Backfilling a row per operator
would have locked out every operator created afterwards.

### 3.2a Per-currency limits (decision: every currency has its own limits)
```
game_limits:          id, game_id FK, currency char(3), min_bet, max_bet, max_win decimal(18,8) NOT NULL,
                      unique(game_id, currency), timestamps
operator_game_limits: id, operator_game_id FK, currency char(3), min_bet, max_bet, max_win decimal(18,8) nullable,
                      unique(operator_game_id, currency), timestamps   -- may only tighten
```
- **Currency coverage rule:**
  - A game with **no** `game_limits` rows accepts any currency without limits. Only crash is in
    this state after the backfill, which keeps today's afq traffic unaffected.
  - Once a game has at least one row, a currency without a row is rejected with
    422 `currency_not_supported`, both at bet time and at launch.
  - A game other than crash cannot be set to `active` without at least one currency row.
- **Guard against locking out a live currency:** before the first row is saved for a game, the
  panel lists every currency that game has taken bets in over the last 30 days and has no row
  for, and asks for confirmation.
- **Effective limits** are computed per currency at bet time by
  `GameLimits::for($game, $operator, $currency)`:
  - `min = max(game.min, op.min)`;
  - `max = min(game.max, op.max)`;
  - `max_win = min(game.max_win, op.max_win)`;
  - null operator values are ignored.

  The operator form rejects looser values. Recomputing at runtime also covers the case where the
  game's own limits are tightened later.
- Operator users may tighten their own operator's limits in the panel (`game_limits.tighten`, §9).

### 3.3 `bets` — generalise
```
+ game_id        FK games       -- add nullable → backfill → NOT NULL; index (game_id, placed_at)
~ round_id       nullable       -- crash keeps using it
+ game_session_id uuid FK nullable
+ player_seed_id  FK player_seeds nullable, + nonce bigint nullable   -- instant/session fairness inputs
+ params         jsonb nullable -- the player's own choices (dice target/direction); not secret
+ outcome        jsonb nullable -- WRITE-ONCE, only when resolved
+ multiplier     decimal(14,4) nullable
+ payout_amount  decimal(18,8) nullable
+ settled_at     timestamptz nullable
+ max_payout     decimal(18,8) nullable -- effective max win fixed at placement (null = uncapped);
                                        -- caps the payout and lets auto-cashout fire at max win in SQL
```
- **Backfill** (in chunks of 10k by id, `UPDATE bets SET game_id = r.game_id FROM game_rounds r WHERE …`).
  For crash bets with a cashout, also set `multiplier`, `payout_amount` and `settled_at` from
  `cashouts`; lost bets get `multiplier = 0`, `payout_amount = 0`. From then on `CashoutService`
  and `settleLosers` write the same columns, so per-game GGR and RTP come from `bets` alone.
  `cashouts` stays as it is (afq/webhooks/admin read it).
- Crash bets keep `outcome = null`; the crash outcome lives on `game_rounds.crash_point` and is
  already guarded by `publicCrashPoint()`.
- `Bet::outcome` gets a guard: setting it once it is non-null throws. A model test checks this.
- `BetStatus` gains `Rejected` (the operator declined the debit, nothing was taken).
- **`down()`** restores `round_id NOT NULL` and drops the new columns. It **refuses with a clear
  exception** if any bet has `round_id IS NULL`. Undoing the migration would otherwise mean deleting
  real dice bets (money records), so the rollback is blocked once non-round bets exist; before that
  it works normally.

### 3.4 `player_seeds` (instant/session fairness)
```
id, operator_id uuid, player_id uuid, server_seed text (encrypted), server_seed_hash char(64) unique,
client_seed string(64), nonce bigint default 0, active bool, revealed_at timestamptz nullable,
unique partial index (player_id) WHERE active
```
This is a separate table, not `rng_seeds`, because `SeedRotator::current()` picks
`RngSeed::active()->latest()`. Player rows in that table would hijack the house round seed.
One active pair per player, shared by all instant games (Stake-style). `nonce` increments per bet.

### 3.5 `game_sessions` (multi-step games)
```
id uuid, game_id, bet_id unique, operator_id, player_id,
state (active | won | lost | cancelled), step int, public_state jsonb, expires_at, timestamps
```
No hidden state is stored (§2). An expired active session is auto-cashed-out or lost according to
the engine's `expiry_policy` config.

### 3.6 `launch_tokens` and `play_sessions` (Phase 4)
```
launch_tokens: id, token_hash char(64) unique, operator_id, player_id, game_id nullable (null = lobby),
               currency, language, return_url, expires_at, used_at nullable
play_sessions: id uuid, token_hash char(64) unique, operator_id, player_id, game_id nullable, lobby bool,
               currency, language, return_url, expires_at, last_seen_at, revoked_at
```
Only hashes are stored. Launch tokens live 120 s and are single-use (`UPDATE … SET used_at WHERE used_at IS NULL`).

### 3.7 Migration order
1. `create_game_categories_table` (seed `crash`, `dice`, `originals`)
2. `add_catalogue_columns_to_games` (+ backfill type/slug/category)
3. `create_operator_game_table` (+ backfill), `create_game_limits_tables`; index
   `game_rounds.server_seed_id` (used by seed retirement since the D1 hotfix)
4. `generalise_bets` (+ chunked backfill, then NOT NULL / FK / index)
5. `create_player_seeds_table`, `create_game_sessions_table`
6. `create_launch_tokens_table`, `create_play_sessions_table` (Phase 4)

Each step is a separate migration so a failed backfill rolls back alone. Adding nullable columns is
metadata-only in PostgreSQL; the only scan is `SET NOT NULL` on `bets.game_id`, which is acceptable
at current volume.

---

## 4. Money, limits and fairness

### 4.1 Instant bet flow (core, any instant engine)
1. Validate amount with a strict decimal regex (fixes D5 on new endpoints), check enablement and
   effective limits, `engine->validateParams`.
2. **Tx 1:** lock the player's active seed pair `FOR UPDATE`, `nonce++`, insert the bet
   (`placed`, `player_seed_id`, `nonce`, `params`, `outcome=null`), commit. No lock is held across HTTP.
3. **Synchronous debit** (`WalletService::debitNow()`, a new method; the queued path is unchanged
   for crash). It makes one attempt with a short timeout (`config/games.php`) and uses
   `tx_id = bet:{Idempotency-Key}`, the same as crash, so afq-style pre-debiting operators keep working.
   - 2xx → continue.
   - 4xx (`insufficient_funds`, …) → bet `rejected`; outcome never computed. API 402/409.
   - timeout/5xx → queue **rollback** (`tx_id = rollback:{bet_id}`, retried until acknowledged),
     bet `rolled_back`. API 503 `wallet_unavailable`.
4. **Tx 2:** `engine->play()` with a `RandomStream`; payout = `min(amount × multiplier, max_win)`
   (bcmath, 8 dp); write `outcome`, `multiplier`, `payout_amount`, `settled_at`, status `won`/`lost`.
5. If payout > 0, queue **credit** `tx_id = payout:{bet_id}` (idempotent, retried by D4's reconciler).
6. Respond with the outcome. The outcome becomes public only here, after it is written.

### 4.2 Crash
The crash flow is unchanged, with two additions in Phase 3:
- Limits and enablement are checked in `BetPlacementService` (new 4xx codes, §5.3).
- **Max win:** `AutoCashoutOnTick` also cashes a bet out when `amount × multiplier ≥ max_win`,
  and `CashoutService` caps the payout. Nothing changes while crash has no `game_limits` row
  for the bet's currency (§3.2a).

### 4.3 Reliability fixes (Phase 3)
- `wallet:reconcile` (scheduled every minute) retries `failed` credits and rollbacks with backoff
  and alerts after N attempts (D4). Debits are not retried; the rollback path handles them.
- `crash:loop` orphan clean-up rolls back still-`placed` bets on orphaned rounds (`rollback:{bet_id}`)
  instead of abandoning them (D3).

### 4.4 Fairness
**Random stream** (all new engines):
```
block_k = HMAC_SHA256(key = server_seed, msg = "{client_seed}:{nonce}:{k}")   k = 0,1,2,…
each 32-byte block yields eight uint32 values, read big-endian, in order.
nextInt(n): rejection sampling — draw u; reject if u >= floor(2^32 / n) * n; return u mod n   (no modulo bias)
```
This is documented byte-for-byte in `docs/fairness.md` with a reference JS snippet for players.

**Dice:** `roll = nextInt(10000) / 100` (0.00–99.99).
`under T` wins if `roll < T` (P = T/100). `over T` wins if `roll ≥ T` (P = (100 − T)/100).
`multiplier = floor4((1 − house_edge) × 100 / chance)`. Chance bounds come from config
(default 0.01–98.00).

**Instant seeds:** each player has an active `player_seeds` row with the hash committed, and the
player may set the client seed. Rotation reveals the old `server_seed` and creates a new pair. It
is **refused (409 `bets_in_progress`)** while a bet on that pair is unresolved or a session is
active, so a seed is never revealed while it still determines a pending outcome.

**Round seeds — D1 fix (Phase 0, implemented):** one fresh `rng_seeds` row **per round**
(`SeedRotator::mintForRound`, nonce 1). Its hash goes out in `round.betting_opened`, as before. At
settle, `SeedRotator::retire` rotates the seed and reveals it, unless another live round still uses
it; that can only happen to a seed shared by rounds created before the fix.
- `round.settled` and the `round.settled` webhook publish `GameRound::revealedServerSeed()`, so a
  seed that hasn't been revealed is never sent. The payload keys don't change.
- The admin "reveal" action refuses a seed that a live round still uses.
- The admin "rotate active seed" button is gone, because there is no shared seed left to rotate.
- `crash:loop` retires the seeds of finished rounds at start-up.
- The `seed_rotation_rounds` config no longer affects crash.
- This adds about 4,300 `rng_seeds` rows a day, which is negligible.

Later hardening, not required for the leak: a bustabit-style hash chain (commit to a chain's
terminal hash up front, then use the seeds in reverse). It would also stop the house from choosing
each round's seed, but it changes how players verify, so it would need a deliberate decision.

**Verify endpoints:** `GET /v1/fairness/{code}/verify` is public, uses no HMAC and is rate-limited.
It dispatches to `engine->verifier()`. The existing `POST /v1/fairness/verify` stays as it is.

---

## 5. Operator API (Phase 4)

### 5.1 Two play modes, chosen per operator × game (`operator_game.settings.play_mode`)
In plain terms, the two modes differ in **who presses "place bet" towards Orange**:
- **Relay** (how afq works today): the game page asks the operator's page, the operator's server
  takes the stake and calls Orange's `/v1/bets`. The operator sees and owns every bet before
  Orange does, but it has to write code for every game's bet shape.
- **Direct** (needed for the lobby): the operator only launches the player into a game or the
  lobby once (`/v1/games/{code}/launch` or `/v1/lobby/launch`). After that the game page sends bets
  straight to Orange, and Orange takes the stake and pays wins through the operator's existing wallet
  callbacks. The operator writes no per-game code. Its wallet must accept debits and credits for bets
  that Orange started.

| mode | who places bets | wallet | used by |
|---|---|---|---|
| `relay` (default for existing rows) | operator backend via `/v1/...`, iframe posts intents over the bridge | operator may pre-debit; Orange callbacks replay | afq today, OrangeConsumer, OrangeApp: **unchanged** |
| `direct` | the game page via `/play-api`, authenticated by the play session | Orange calls the operator wallet; operator accepts credits for bets it did not originate | lobby, `/play/{code}`; afq from Phase 9 |

**afq (decision):** afq will launch the lobby from its side. The afq-side work is Phase 9 (§12),
done once Orange's lobby exists. Until afq switches, `/crash` and the relay flow keep working
exactly as they do today.

### 5.2 Signing GET requests
For requests **with a query string**, the signed PATH becomes `path?query` (raw, as sent). No
existing client sends query strings, so current signatures are unaffected. This closes the
unsigned-query gap for the new GETs.

### 5.3 Endpoints
**`GET /v1/games`** (HMAC) returns the games enabled for the caller, with effective limits:
```json
{ "data": [
  { "code": "crash", "name": "DeepDive", "type": "crash", "family": "round",
    "category": "crash", "thumbnail_url": "https://…/thumbs/crash.webp", "status": "active",
    "limits": { "EUR": { "min_bet": "0.10000000", "max_bet": "500.00000000", "max_win": "50000.00000000" },
                "TRY": { "min_bet": "5.00000000", "max_bet": "20000.00000000", "max_win": "2000000.00000000" } },
    "currencies": "listed", "rtp": "0.9700", "play_modes": ["relay"] },
  { "code": "dice", "name": "Dice", "type": "dice", "family": "instant", "category": "originals",
    "limits": { "EUR": { "min_bet": "0.10000000", "max_bet": "100.00000000", "max_win": "10000.00000000" } },
    "currencies": "listed", "rtp": "0.9900", "play_modes": ["direct", "relay"] } ] }
```
`limits` are the caller's effective limits per currency. `"currencies": "any"` with empty `limits`
marks a game that has no currency rows yet; in practice that is only crash, before any rows are added.
The values above are illustrative.

**`POST /v1/games/{code}/launch`** (HMAC + Idempotency-Key)
```json
// request
{ "player_external_id": "12*3456", "currency": "EUR", "language": "tr",
  "return_url": "https://casino.example/games" }
// 201
{ "launch_token": "lt_6vQ…", "play_url": "https://orange…/play/dice?token=lt_6vQ…",
  "expires_at": "2026-11-02T10:00:00Z" }
```
**`POST /v1/lobby/launch`** takes the same body without `{code}` and returns `lobby_url`.
Errors: 404 `unknown_game`, 403 `game_not_enabled`, 409 `game_unavailable` (maintenance),
422 `currency_not_supported`, 422 validation.

**`POST /v1/games/{code}/bets`** (HMAC + Idempotency-Key; relay mode, generic)
```json
// dice request
{ "player_external_id": "12*3456", "amount": "2.50", "currency": "EUR",
  "params": { "target": "49.50", "direction": "under" }, "client_seed": "optional-override" }
// 201 — instant: resolved in the same call
{ "bet_id": 9123, "game": "dice", "status": "won", "amount": "2.50000000", "currency": "EUR",
  "multiplier": "2.0000", "payout_amount": "5.00000000", "outcome": { "roll": "23.45" },
  "fairness": { "server_seed_hash": "9f…", "client_seed": "abc", "nonce": 42 },
  "wallet_tx_id": "88121" }

// crash request — round_uuid optional (defaults to the round currently in betting)
{ "player_external_id": "12*3456", "amount": "1.00", "currency": "EUR",
  "round_uuid": "…", "params": { "auto_cashout_at": "2.00" } }
// 201 — same keys as POST /v1/bets plus "game": "crash"
```
For session games (mines, later) the call returns `session_id` and `public_state`, followed by
`POST /v1/game-sessions/{id}/actions` and `POST /v1/game-sessions/{id}/cashout`.
New error codes: 422 `invalid_params`, 422 `bet_below_min` / `bet_above_max` / `currency_not_supported`,
402 `insufficient_funds`, 503 `wallet_unavailable`.
`POST /v1/bets` and `POST /v1/bets/{id}/cashout` are kept verbatim. The legacy cashout returns
409 `not_cashoutable` for a non-round bet.

**Seeds (relay mode):** `GET /v1/players/{external_id}/seeds` returns
`{ server_seed_hash, client_seed, nonce }`.
`POST /v1/players/{external_id}/seeds/rotate {client_seed?}` returns
`{ previous: {server_seed, server_seed_hash, client_seed, last_nonce}, current: {server_seed_hash, client_seed, nonce: 0} }`.

**Webhooks:** new `bet.settled` event, only for non-crash games, payload
`{bet_id, game, status, multiplier, payout_amount, outcome, settled_at}` (sent after resolution).
Crash webhooks are unchanged. Wallet `reference` gains the optional keys `game` and `round_uuid`;
afq ignores unknown keys (verified in `processCallback`).

Docs: `docs/operator-integration.md` gets a "Multi-game" section, and `docs/api/openapi.yaml` is extended.

---

## 6. Player side (Phase 5)

- **`/play/{code}?token=`** consumes the launch token and creates a `play_session`. It renders the
  game view registered for the engine (`resources/views/games/{type}.blade.php`) with the
  play-session bearer token in a JS variable, and `history.replaceState` strips `?token` from the URL.
  **No cookies:** third-party iframe cookies are blocked by Safari and partitioned elsewhere, so
  `/play-api` uses `Authorization: Bearer` with a token held in memory.
- **`/play-api`** (play-session auth, per-session rate limit): `GET /state`, `POST /bets`,
  `POST /sessions/{id}/actions|cashout`, `GET /seeds`, `POST /seeds/rotate`,
  `POST /launch/{code}` (from lobby), `GET /history`.
- **`/crash`** stays as it is (relay mode, global `EMBED_ALLOWED_ORIGINS`). `/play/crash` renders
  the same view, in relay or direct mode depending on the pivot setting.
- **Shared bridge:** `resources/js/bridge.js` exports
  `createBridge({ allowedOrigins, onMessage })` with the exact semantics in use today: allow-list
  check, lock onto the first parent origin, `'*'` only for `embed_ready`, and the same message
  names and payloads, including `round_state` and `auto_cashout_fired`. It adds `launch_game`
  and `back_to_lobby` (iframe → parent, informational; navigation happens inside the iframe).
  `crash.blade.php` switches to the module with no changes to its message flow. The afq contract
  test (§2) asserts the message names, and a small Vitest suite covers the module (new dev
  dependency, approved).
- **CSP:** `AllowGameEmbed` adds `frame-ancestors` from `operators.config.embed_origins` for pages
  served under a play session, merged with the global list. `/crash` uses the global list as today.
- **Realtime:**
  - Round events also go to `game.{code}`.
  - The `crash` channel only carries the game whose code is `config('games.legacy_channel_game')`
    (= `crash`). A second crash-type game will therefore not appear in afq's iframe.
  - `crash:tick` ticks **every** running round of every round-based game, each with its own 120 s
    stale guard.
  - `crash:loop --game=X` runs one process per round-based game. A `games:loops` supervisor
    helper lists which processes should be running.
  - Instant games broadcast nothing in v1.

---

## 7. Dice end to end (Phase 6)

Dice consists of:
- `DiceEngine` (instant) with the config `{min_chance, max_chance}`;
- its verifier and `GET /v1/fairness/dice/verify?server_seed&client_seed&nonce&target&direction`;
- `games/dice.blade.php` plus `resources/js/games/dice.js` (vanilla JS + bridge, matching the crash page's stack);
- a seeder row;
- a Filament config form;
- tests: math (RTP over 1M simulated rolls within ±0.2%, exact multiplier table), determinism
  against published vectors, a verify round-trip, a wallet flow (debit → credit / rejected /
  rolled_back), limits and max-win cap, idempotent replay, and secrecy.

**Secrecy test per engine**, `tests/Feature/Secrecy/{Engine}SecrecyTest.php`, built on a shared
`AssertsOutcomeSecrecy` trait. It plants a recognisable outcome, then captures `Event::fake`
broadcasts, `Http::fake` webhooks and wallet calls, a `Log` spy, API responses, and the Filament
Bets, Sessions and Seeds tables. It asserts that the outcome and the unrevealed server seed appear
in none of them before resolution, and that they do appear afterwards. `docs/adding-a-game.md`
makes this test mandatory.

---

## 8. Lobby (Phase 7)

`/lobby?token=` creates a lobby play session. It shows tiles for the operator's enabled `active`
games, grouped by `game_categories` (ordered by category and then game `sort_order`, with the
operator's `operator_game.sort_order` overriding). It also offers client-side search, "Recent"
(this player's last 10 distinct games) and "Popular" (bet count per game over the last 7 days for
the operator, cached 10 min).

Clicking a tile calls `/play-api/launch/{code}` and swaps the iframe content. The game has a
"back to lobby" control and emits `back_to_lobby`. Theme values (`logo_url`, `primary`, `accent`,
`background`) come from `operators.config.lobby`, are validated as URL and hex values, and are
injected as CSS variables. CSP is the same as for games.

Panel: a `GameCategoryResource` (CRUD + drag-reorder) and reorderable games within a category.

---

## 9. Admin panel (Phase 8) and access control

**New permissions** (in `AccessControl::PERMISSIONS`):
| permission | purpose | operator? |
|---|---|---|
| `games.view` *(exists)* | see games; operator users see only their enabled games | **add to OPERATOR_PERMISSIONS** |
| `games.manage` *(exists)* | game CRUD, engine config, assets | no |
| `game_access.manage` | enable games for operators, set per-operator limits and play mode | no |
| `game_limits.tighten` | operator user may tighten *their own* per-game, per-currency limits (tighten-only validation) | yes |
| `lobby.manage` | categories and ordering | no |
| `sessions.view` | game sessions report | yes |
| `player_seeds.view` | player seed pairs (hash only until revealed) | no |

`starterRoles()` is updated for Admin, Finance, Support, Viewer, Operator Admin and Operator
Viewer. `orange:sync-access` creates the missing permissions; edited roles are left as they are.

**Resources:**
- `GameResource`:
  - type select from the registry; when the type changes, the config form is swapped for the one
    registered for that engine (`App\Filament\GameEngines\{Type}ConfigForm`, so the domain stays
    free of Filament);
  - thumbnail upload, limits, status, sort order and category;
  - an **Operators** relation manager for the pivot, gated by `game_access.manage`;
  - for operator users, the query is `scopeToOperator` via `operators.operator_id` with `enabled=true`;
    they get a read-only view plus a limits action gated by `game_limits.tighten`.
- `OperatorResource` gets a **Games** relation manager (the same pivot, seen from the operator side).
- `BetResource`: game column and filter; `outcome` and `multiplier` columns render only for
  resolved bets; the export gains a `game` column (additive).
- `GameRoundResource`:
  - game filter;
  - **D2 fix**: operator users only see rounds they had bets on, and the totals columns show their
    own stake and payout (sub-select scoped to `operator_id`) instead of the round totals.
- `GameSessionResource` (new): `sessions.view`, scoped, date filter, shows `public_state` only.
- `PlayerSeedResource` (new, platform only): hash, client seed, nonce; `server_seed` only after reveal.
- Dashboard: a game filter (Filament page filters) feeding `GlobalKpiWidget`, plus a new
  `GameKpiTable` widget with per-game bets, stake, payout, GGR and RTP against target. It has its
  own date range (the same from/until semantics as `DateRangeFilter`) and is scoped by
  `scopeToOperator`.

**Scoping rule:** every new query reachable by an operator user goes through `scopeToOperator`. An
`OperatorScopeTest` case is added for each new resource and widget, following the existing test.

---

## 10. Backward-compatibility guarantees and how they are enforced

| Contract | Guarantee | Enforced by |
|---|---|---|
| `POST /v1/bets`, `/v1/bets/{id}/cashout`, `/v1/ping`, `POST /v1/fairness/verify` | same routes, request rules, response keys and status codes. The only new outcomes are limit/enablement 4xx, which cannot occur with the backfilled data | `AfqContractTest` snapshots |
| Wallet callbacks | same paths, body, headers and tx_id formats (`bet:{K}`, `cashout:{bet_id}`); new optional `reference` keys only | contract test asserts `Http::fake` recorded bodies |
| Broadcasts | same events, names and payloads on `round.{uuid}` and `crash`; `game.{code}` is added alongside | `BroadcastEventsTest` + contract test |
| Webhooks | crash events unchanged; `bet.settled` is new and only sent for non-crash games | `WebhookDeliveryTest` + contract test |
| `/crash` + bridge | same URL, CSP source and message names/payloads (including `round_state` and `auto_cashout_fired`) | contract test on the rendered page + Vitest |
| HMAC | unchanged for requests without a query string | `HmacAuthTest` |
| `.env` | no new or changed semantics; new settings go in `config/games.php` with literal defaults | review |

---

## 11. Risks

| Risk | Mitigation |
|---|---|
| Phase 2 refactor subtly changes crash timing or maths | Adapter only; crash maths isn't moved; contract test + full suite; RTP regression test over fixed seed vectors |
| Synchronous debit adds operator latency to instant bets | Short timeout, a single attempt, rollback on uncertainty, latency metric; relay mode lets the operator pre-debit |
| Seed-reveal mistakes (D1-class) recur in new engines | Rotation refused while bets are pending; per-engine secrecy test is mandatory; seeds are `encrypted` and `$hidden` |
| `bets` backfill on a large table | Chunked by id, separate migration, `SET NOT NULL` last; measure on a prod snapshot first |
| afq turns on `direct` mode without changing its credit check | `direct` is off by default; enabling it is a deliberate pivot setting; integrator docs call out the requirement |
| Multiple round loops + one ticker raise DB and Reverb load | Ticker query uses the `(game_id, state)` index; one loop process per game; `crash:tick` keeps its memory guard. Measure with the existing k6 script |
| Adding the first currency row to crash cuts off a currency afq plays in | §3.2a coverage rule + the panel's 30-day currency check before the first row is saved |
| Laravel/Filament version drift in ARCHITECTURE.md | Update ARCHITECTURE.md in Phase 8 |

---

## 12. Phase plan (each phase ends with a summary, test results and open questions, then waits)

0. ✅ **Hotfix D1**: per-round seeds, publishing only revealed seeds, admin reveal guard; tests in
   `CrashPointSecrecyTest`, `SeedRotatorTest`, `RoundOrchestratorTest`, `RunCrashRoundJobTest`.
1. ✅ Discovery (this document).
2. ✅ Engine abstraction: contract test first, then registry + `CrashEngine` adapter. No schema change.
3. ✅ Schema + money: §3.1–3.5 migrations; limits/enablement; max-win; D2–D5 fixes; `wallet:reconcile`.
4. ✅ Operator API (catalogue, launch, generic bets for round games, per-game fairness, query signing, signature v2, D6, D7; per-player seed endpoints and `bet.settled` move to Phase 6 with the first instant game): catalogue, launch tokens/play sessions, generic bets, seeds, query signing,
   `bet.settled`, docs and OpenAPI.
5. ✅ Player side (as built):
   - Orange **host pages** embed the game frame and act as its bridge parent, so
     `crash.blade.php` keeps its own bridge code (its only change: it also accepts its own
     origin as parent).
   - Direct bets are created `pending` and debited synchronously (`WalletService::debitNow`).
     Accepted → live (or lost, if the round already ended); refused → rejected; no answer →
     rolled back.
   - The shared bridge is `public/js/orange/bridge.js` (no build step), tested with Vitest
     (`npm run test:js`).
   - Operators' `config.embed_origins` join `frame-ancestors`.
   - Rounds broadcast on `game.{code}`; `crash` keeps every crash-type game unless
     `games.legacy_channel_games` narrows it.
   - `crash:tick` ticks every running round through its engine.
   - Moving `crash.blade.php` onto the shared module is left for a later pass (it doesn't need
     it to work).

   Original scope: bridge module, `/play/{code}`, `/play-api`, per-operator CSP, `game.{code}`,
   multi-round ticker.
6. ✅ Dice end to end (as built):
   - `RandomStream` and `player_seeds`: one active pair per player, rotation refused while a
     bet on the pair is pending.
   - The `InstantEngine` contract, `DiceEngine` and `DiceVerifier`.
   - `InstantPlayService`: a pending bet, then a synchronous debit; the outcome is computed
     only after the stake is confirmed; wins credited as `payout:{bet_id}`, capped at max win.
   - `games:settle-stale-bets` every minute.
   - The `bet.settled` webhook; seed endpoints in both APIs; the `/games/dice` page on the
     shared bridge.
   - Golden vectors from an independent implementation, an exhaustive win-probability check
     over all 10,000 rolls, and `DiceSecrecyTest`.

   Original scope: Dice end to end.
7. ✅ Lobby + categories (as built):
   - `/lobby?token=` is a host page with a tile grid; games open in the same frame through
     the play-host (`open` / `close`).
   - `/play-api/lobby` returns tiles by category, recent (player), popular (operator, 7 days,
     cached) and the theme.
   - The operator theme and embed origins are flat config keys, validated.
   - `GameCategoryResource` adds CRUD and drag reordering, with new `lobby.view` /
     `lobby.manage` permissions (platform only), synced by migration.
8. ✅ Admin panel (as built):
   - **Games:** type-specific engine settings (`config/games.php` → `admin_forms`), validated by
     the engine (also on `Game::saving`); catalogue fields; a read-only view page; per-currency
     limits; operators (enable, play mode, tighten-only limits via `OperatorLimitsEditor`).
   - **Operator users:** see only the games their operator may offer
     (`AccessControl::scopeGamesToOperator`), and tighten their own limits (`My limits`).
   - **Reports:** game filters on Bets and Rounds; bets show settlement from the bet and the
     outcome only once resolved.
   - **Dashboard:** game and date filters; `GameKpiTable` shows stake, payout, GGR and RTP vs
     target per game, scoped to the operator.
   - **Player seeds:** read-only, platform staff only; the server seed only once revealed.
   - **Permissions:** `games.view` and `game_limits.tighten` for operators; `game_access.manage`
     and `player_seeds.view` for platform staff. Existing starter roles get them by migration.
   - Game pages are served by one `GET /games/{type}`.
   - `docs/adding-a-game.md` is written.

   Original scope: Games resource, pivot managers, per-game reports/dashboard, new permissions and
   scoping tests, `docs/adding-a-game.md`.
9. **afq switches to the lobby.** These changes are in afq's repos, and Orange's side must
   already be live.
   - **afq-integrations:** a `secure.orange.lobby` RPC calls `POST /v1/lobby/launch`. The wallet
     credit and rollback handlers accept bets Orange started; they validate the player, currency
     and the matching `bet:{K}` debit instead of requiring an afq-placed `OrangeBet`.
   - **afq-casino:** the game page embeds the returned `lobby_url` instead of `/crash`.
   - Orange's `operator_game.settings.play_mode` for afq flips to `direct`.

   Both sides are tested against a staging Orange before the switch. `/crash` keeps working
   throughout, so the switch can be rolled back by pointing afq's embed back at `/crash`.

Pint, PHPStan (baseline must not grow; currently 66 entries) and Pest must be green at the end of
every phase.

---

## 12b. Whole-build review (2026-11-01)

An adversarial review of Phases 3–8 found thirteen defects. Each was checked against the code
and then fixed (branch `fix/review-findings`):

**Money**
- Direct crash bets left `pending` by a dying process are now resolved by
  `games:settle-stale-bets`.
- Every status flip and its wallet record are one transaction, and callbacks are queued after
  commit.
- Rollbacks after an unanswered debit wait `wallet.unanswered_debit_rollback_delay_seconds` and
  name the debit (`reference.debit_tx_id`); afq refuses such a late debit.
- A stake must be below the max win, and max win must exceed max bet.
- Instant games need limit rows for every currency they take.
- New endpoints accept at most 8 decimals.

**Fairness and secrecy**
- Dice fixes its RTP at placement, and the verifier takes it.
- Player seed pairs pre-commit the next server seed (`next_server_seed_hash`).
- Game pages show the odds of the game being played (`?game={code}`).
- The Dice verifier refuses target 0.

**Access**
- Play sessions and launch links stop when their operator is suspended.
- Direct play (launch, lobby, player API) requires the per-game play mode `direct`.

**Reports**
- The dashboard reports one currency at a time.

## 13. Decisions (2026-10-31)

| # | Question | Decision |
|---|---|---|
| Q1 | Hotfix D1 before Phase 2? | Yes; done (Phase 0). |
| Q2 | Per-round seed vs withholding the seed | Per-round seed (keeps the payload). |
| Q3 | afq and direct play | afq will launch the lobby; the afq-side changes are Phase 9. |
| Q4 | Limits | Every currency has its own limits (§3.2a). The actual values are still needed before dice goes live. |
| Q5 | Bets migration `down()` | Left to us: it refuses once non-round bets exist (§3.3). |
| Q6 | Operator users tighten own limits | Yes (`game_limits.tighten`). |
| Q7 | Vitest | Yes. |
| Q8 | `game_categories` table | Yes. |

Still open:
- Per-currency limit values (min, max, max win) for crash and dice, and the currency list.
- Whether to adopt the hash-chain hardening for round seeds (§4.4).
- **Publishing the house client seed.** Crash verification needs it, but no API, broadcast or doc
  publishes it today, so in practice players can't verify a round. Publishing it is safe since the
  D1 fix. The plan is to return it from `GET /v1/fairness/crash/verify` (Phase 4) and list it in the
  integration guide. Separately, check whether production ever used the committed default
  `orange-house-seed-rotate-me`. If it did, D1 was exploitable by anyone until the fix was
  deployed, and past rounds are worth reviewing for suspicious win patterns.
