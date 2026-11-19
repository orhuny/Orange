# Slots

Audience: Orange engineers adding a slot, changing slot maths, or reviewing them.

A slot is a game with `type = slot`. It is an **instant** game like dice (`SlotEngine` implements `InstantEngine`):
- One bet is one paid spin, its tumbles and every free spin it triggers.
- The whole bet is resolved by `InstantPlayService` after the stake is confirmed.
- The core, the API, the bridge and the wallet are unchanged; see [adding-a-game.md](../adding-a-game.md) for the shared rules.

A slot is two independent pieces, picked in the admin panel:

| Piece | Setting | What it is | Where |
|---|---|---|---|
| **Maths model** | `config.model` | Paytable, rules and RTP variants (weight tables) | `app/Domain/Game/Engines/Slot/Models/*`, registered in `SlotModels` |
| **Theme** | `config.theme` | Art, sound and colours for one model's symbols | `config/slots.php`, `resources/views/games/slot/themes/{theme}/theme.php`, `public/images/{theme}/` |

The admin panel also sets:
- `config.variant`: the RTP variant. It sets the game's RTP target and house edge.
- `config.ante`, `config.buy`, `config.autoplay`: switch those features off where a jurisdiction requires it.
- `config.bet_steps`: what the page's − / + buttons step through.

## The maths (`Maths/`)

`SpinResolver` is the only place outcomes are made. It is pure and deterministic, and it draws only from the bet's `RandomStream`. Its docblock and `SlotVerifier::describe()` state the draw order. That order is part of the fairness contract: players replay it.

Rules:
- **Amounts are integers in hundredths of the bet.** No floats anywhere in the maths.
- **A live variant is immutable.** A bet's params pin `model`, `variant`, `mode`, `cost` and `rtp`, and verification replays exactly that. To retune, add a new variant id (`v2-9650`), measure it, and switch games to it in the admin panel. Never edit one that a real bet has used.
- **A paytable or rules change is a new model** with its own code.
- **Every variant measures the same RTP in every mode.** base, ante (stake 1.25×) and buy (stake 100×) are each tuned on their own tables.

## Measuring: `slot:simulate`

```
php artisan slot:simulate sugar-orchard --variant=v1-9650 --mode=base --spins=144000000 --processes=12
```

It prints:
- RTP with a 95% interval, split between the base game and free spins
- volatility, hit rate, free-spin frequency, the average free-spins win
- the biggest win and the win distribution

Two RNG options:
- `--rng=fast` (default) uses a seeded xoshiro256\*\*.
- `--rng=hmac` draws exactly as production does.

Both measure the same tables. Neither ever plays a bet.

Slot RTP has no closed form (tumbles and multipliers), so it is measured. With about 8× the stake as the standard deviation per spin, 144M spins give an interval of about ±0.13%. The published figures per model are in `docs/slots/{model}-maths.md`. A test lab certifying a model for real money reruns this and the golden vectors.

## Tests (all required for a new model or variant)

- **Golden vectors** (`tests/Unit/Slot/SpinResolverTest.php`). They come from the **independent** Python implementation in `tools/slot-reference/reference.py`; regenerate them with `python -I tools/slot-reference/reference.py > tests/Fixtures/slot/sugar-orchard-golden.json`. A new model needs its own reference and fixture.
- **Scripted grids:** the `scripted()` helper lays out exact grids. Use them for pays and tiers, gravity, multipliers, retriggers, the max-win cap and the buy guarantee.
- **Fixed-seed totals:** these catch any change to the maths. If a change is intended, it is a new variant.
- **Money flows, verification, secrecy, page and admin:** `SlotPlayTest`, `SlotSecrecyTest`, `SlotAdminTest`.
- **Page logic:** `tests/js/slot-timeline.test.js`.

## A new theme for an existing model

1. Add it to `config/slots.php` with the model it draws.
2. Write `resources/views/games/slot/themes/{theme}/theme.php` (Sugar Orchard's is the example):
   - `sound`: the pack name, from `public/js/orange/sound-{pack}.js`
   - `logo`
   - `backgrounds`: day and night (free spins), each landscape and portrait
   - `scenery`: what sways in front of the background (a branch; lanterns at night)
   - `frame`: the image around the grid and its window, which the grid fills exactly
   - `spin`, `plaque` and `banners`: the spin button, the feature dialogs' panel, and the win and free-spins titles
   - `symbols`: the model's letters mapped to `name`, `img` and `colour`
   - `stars`: the multiplier star per colour (`sky`, `emerald`, `gold`, `ruby`); the page prints the value
   - `fragments`: each symbol's explosion pieces, with where each sits in the burst
   - `fx`: the win effect's light (frost burst, ring, crust, crystals, twinkle), the comet, the rays, the fireworks, the sprinkles and the petals
3. Put the art in `public/images/{theme}/`, built by `scripts/art/process-{theme}.py` from the raw deliveries; see [art/README.md](../art/README.md).
   - Symbols are square transparent WebP, 320 px.
   - Light effects are light on transparent: the script turns their black into transparency.
   - The script writes `art.json` (pieces, sprite lists, the frame's window), which `theme.php` reads.
4. Create the game in the admin panel with that theme.

## The page

`resources/views/games/slot.blade.php` is the shell; `public/js/orange/slot-game.js` drives it. `slot-timeline.js` turns an outcome into animation steps and does the money maths with BigInt; it has no DOM, so Vitest covers it.

The page never decides anything. It plays back the outcome it is handed, then shows the balance the casino reported.

**How a win looks:**
1. Winning symbols frost over.
2. Each one bursts: a frost flash and a shockwave, then the symbol's own pieces fly out with spin and gravity, with crystals and a twinkle.
3. In free spins, each star sends a comet to the middle of the grid, where the stars' sum appears.
4. Big wins get their banner, golden rays, fireworks and falling sprinkles.

The effects run on one canvas that reaches past the frame, at the game's speed (turbo). Quick-stop skips them, and the effects and scenery motion respect `prefers-reduced-motion`.
