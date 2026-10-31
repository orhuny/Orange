# Adding a game to Orange

A checklist for engineers adding a new game type. Dice (`app/Domain/Game/Engines/Dice`,
`resources/views/games/dice.blade.php`) is the worked example; copy its shape.

The core doesn't change. Everything below is new files plus one line per registry in
`config/games.php`. If you find yourself editing `InstantPlayService`, `BetPlacementService`,
the API controllers, the wallet or the routes, stop: the engine contract is missing something,
and that is a design discussion, not part of adding a game.

## 1. The engine (`app/Domain/Game/Engines/{Name}/`)

Pick the family and implement its contract:

| Family | Contract | Resolves |
|---|---|---|
| Instant (dice, plinko) | `Contracts\InstantEngine` | in the bet call |
| Round-based (crash-like) | `Contracts\RoundBasedEngine` | in shared rounds, driven by `crash:loop --game=…` / `crash:tick` |
| Session (mines) | `SessionEngine` (to be added with the first one, see the plan §2) | over several player actions |

The engine owns the maths, and nothing else:
- `validateConfig(Game, array)`: normalise `games.config` or throw `InvalidGameConfig`.
  `Game::saving` calls it, and so does the admin form.
- `validateParams(Game, array)`: the player's choices; throw `InvalidBetParams` (the API
  answers `422 invalid_params`).
- `play(Game, params, RandomStream)`: **pure and deterministic.** Draw every random number
  from the `RandomStream` (`nextInt(n)` is unbiased) and nothing else: no `random_int`, no
  time, no database. Return an `InstantResult` with the public outcome, `won`, and the
  multiplier as a 4-dp decimal string (`'0'` on a loss).
- `maxMultiplier(Game)`: the highest multiplier the game can pay.
- `verifier()`: a `FairnessVerifier`:
  - `describe()` returns the algorithm in words a player can follow, plus the inputs.
  - `rules()` validates the extra verify inputs.
  - `verify()` recomputes from revealed seeds with the same static maths `play()` uses.

The engine never touches money. The core debits the stake before `play()` is called, caps the
payout at the bet's max win and credits it.

Engines must be **stateless**: the registry keeps one instance per process (and per Octane
worker).

## 2. Register it (`config/games.php`)

```php
'engines'     => [..., 'plinko' => PlinkoEngine::class],
'frontends'   => [..., 'plinko' => '/games/plinko'],      // the page host pages embed
'admin_forms' => [..., 'plinko' => PlinkoConfigForm::class],
```

## 3. The page (`resources/views/games/{type}.blade.php`)

`GET /games/{type}` renders it with `$game`, `$rtp`, `$config` (already validated) and
`$allowedOrigins`. No route to add.
- Import `createGameBridge` from `public/js/orange/bridge.js`. Pass
  `allowedOrigins: [...$allowedOrigins, window.location.origin]`, and call `ready()` when
  embedded.
- To bet, send `place_bet` with `{ amount, params: { …your params… } }`.
- Handle `bet_placed_result`, which carries `success`, `status`, `outcome`, `multiplier`,
  `payout_amount`, `fairness` and `player_balance`. Also handle `player_info` and
  `balance_update`.
- Don't add message names; if you need one, add it to `bridge.js` and the integration guide.
- Must work at phone width.

## 4. The admin form (`app/Filament/GameEngines/{Name}ConfigForm.php`)

Implement `EngineConfigForm::components()`.
- Bind fields to `config.<key>`, never to `config` as a whole: a hidden whole-`config` field
  drops other types' settings on save.
- Use placeholders, not defaults; `validateConfig()` supplies the defaults.

## 5. Go live (admin panel)

1. **Games → New:** pick the type, code (fixed afterwards: it's in API URLs), name, category,
   thumbnail and settings. Start in `draft` with `access: listed`.
2. **Limits per currency:** add a row for every currency it takes. A game with limits refuses
   other currencies; one without limits takes any currency, unlimited, which is only acceptable
   for crash.
3. **Operators:** enable it for the operators who should offer it, with their play mode.
4. Set the status to `active`.

## 6. Tests (all required)

- **Golden vectors:** seeds, nonce and params mapped to an outcome, computed with an
  **independent** implementation (see `RandomStreamTest`, `DiceEngineTest`). Never generate
  them from the code under test.
- **Odds:**
  - an exhaustive check over every outcome when the space is small (Dice's 10,000 rolls);
  - otherwise a large seeded simulation, with the RTP within a stated tolerance of
    `rtp_target`.
- **Verifier:** verifying a played bet reproduces its outcome (see `DicePlayTest`).
- **Money flows** through `POST /v1/games/{code}/bets`:
  - win (credited as `payout:{bet_id}`) and loss;
  - stake refused (402, no outcome stored) and unanswered (503, rolled back);
  - payout capped at max win;
  - invalid params (422, no nonce or stake taken).
- **Secrecy test** (`tests/Feature/Secrecy/{Name}SecrecyTest.php`, modelled on
  `DiceSecrecyTest`): the unrevealed server seed, and any outcome before resolution, appear in
  no response, callback, webhook, log line, serialized model or admin screen.
- **The page renders** at `/games/{type}`, with its settings.

Pint, PHPStan (no baseline growth), `php artisan test` and `npm run test:js` must all pass.

## 7. Docs

Add a section to `docs/operator-integration.md` §11, like "Dice": the params, the response
outcome, and how relay operators answer `place_bet` for it.
