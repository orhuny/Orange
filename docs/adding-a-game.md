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

A **slot** is already an engine type (`slot`, instant). A new slot is a maths model and/or a theme, not a new
engine: see [slots/README.md](slots/README.md).

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

---

# Adding a crash scene (a new-looking crash game)

A second crash game with its own look, like Lucky Duck next to Orange Crash, is **not** a new
game type: it is a crash game (`type=crash`) with its own code, rounds and limits, played in its
own **scene**. The engine, the maths, the bet bar, the menu, history, All bets, rules and the
audio engine are shared; a scene brings the art, the motion, the words and the sounds.

## How a game picks its scene

- `config('crash.scenes')` lists them (`dive`, `marsh`), with a label and optional intro
  overrides (`intro.title`, `intro.tagline`).
- `games.config.scene` picks one: the **Scene** field in the admin panel's engine settings.
  `CrashEngine::validateConfig` refuses an unknown scene; a game without one plays the dive.
- `/crash?game={code}` (`CrashPageController`) renders `crash.blade.php` with that scene. The
  page listens on `game.{code}`, shows that game's history and live bets, and keeps that game's
  saved state (skin, armed auto bet, two bets) apart from the others'.

## What a scene is: `resources/views/crash/scenes/{scene}/`

`scene.php` (required) returns the scene's data:

| Key | What |
|---|---|
| `themes` | its skins, each a label and the colour tokens (see the dive's for the keys); the first is the default, and with one skin the menu shows no picker |
| `titlePrefix` | put before the game's name in the `<title>` |
| `packs` | skin → sound pack (`window.OrangeSound.<pack>`, from `js/orange/sound-<pack>.js`) |
| `text` | the round's words: `round`, `rise`, `lose` (the rules), `bettingIn`, `liftOff`, `running`, `crashed`, `nextIn`, `awaiting`, `banner` |
| `introBg`, `sprites` | per skin: the built-in intro's backdrop, and the crash sprite to preload |

`script.blade.php` (required) runs inside the page's main module and defines `scene`:

```js
const scene = {
    frame(m) {},       // every animation frame of a round (the multiplier from the round's clock)
    update(m) {},      // with each multiplier the readout shows
    crashed(point) {}, // the round ended at `point`
    reset() {},        // back to the start, before the next round
    cashedOut(m) {},   // optional: a bet cashed out (Lucky Duck's coins)
};
```

The page also sets the stage classes the CSS keys on: `waiting`, `betting`, `running`, and the
tiers `deep` (≥1.8x), `abyss` (≥3.5x), `critical` (≥8x), then `crashed critical`.

Every other partial is optional (the page `@includeIf`s them where they belong):
`styles-skins`, `styles-scene`, `styles-hud`, `styles-sonar`, `styles-crash`, `styles-intro`
(CSS, in that order in the page's `<style>`), `scene` (the scenery, inside `#stage`), `hud-meter`,
`hud-warnings`, `hud-left`, `hud-right`, `hud-extra`, `crash-layers`, `intro-scene`, `sounds`.

Two Blade details: a rendered partial is left-trimmed, so start each with content, not a
directive or a Blade comment; and the crash layers (flash, shockwave, debris, banner) are shared,
so a scene restyles them under `:root[data-theme="<its skin>"]` in `styles-crash`.

## Art

Where everything goes and the naming rules: [art/README.md](art/README.md). In short:
- A game's served art lives in `public/images/{game}/`, with its lobby tile as `thumbnail.jpg`.
- Generated art comes as images on flat magenta (`#FF00FF`). Keep the raw deliveries out of git
  (`storage/app/art/{game}/`).
- Build the served files with `scripts/art/process-{game}.py`, as `process-lucky-duck.py` does:
  key out the magenta, line frames up on a fixed point, make strips tile, pack sheets into
  equal frames.

## Checklist

1. The scene folder, its `config('crash.scenes')` entry and its sound pack (in
   `tests/js/sound-packs.test.js`).
2. `CrashPageTest`: the game renders its scene and none of another's.
3. Replay a round in a browser at desktop and phone sizes: betting, take-off, each tier, the crash.
4. Admin panel: **Games → New**, type crash, a new code, **Scene**; limits per currency; enable
   for operators (play mode `direct` for the lobby); the thumbnail (`public/images/{game}/thumbnail.jpg`).
5. Run one more `crash:loop --game=<code>` (`scripts/start-stack.ps1 -CrashGames`, and in
   production).
