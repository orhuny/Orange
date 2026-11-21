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
| **Theme** | `config.theme` | Art, sound and colours for one model's symbols. The panel lists only the model's own themes, and none picked means the model's first. | `config/slots.php`, `resources/views/games/slot/themes/{theme}/theme.php`, `public/images/{theme}/` |

The admin panel also sets:
- `config.variant`: the RTP variant. It sets the game's RTP target and house edge.
- `config.ante`, `config.buy`, `config.autoplay`: switch those features off where a jurisdiction requires it.
- `config.super`: the super buy, for a model that has one (Stones of the Titan). Same reason to switch it off. It needs a max win of at least the model's default.
- `config.max_win_x`: the max win, from the variant's measured choices (below). It also sets the RTP target.
- `config.bet_steps`: what the page's − / + buttons step through. Left empty, the defaults (0.20 up to 100.00).

## The maths (`Maths/`)

`SpinResolver` is the only place outcomes are made. It is pure and deterministic, and it draws only from the bet's `RandomStream`. Its docblock and `SlotVerifier::describe()` state the draw order. That order is part of the fairness contract: players replay it.

Rules:
- **Amounts are integers in hundredths of the bet.** No floats anywhere in the maths.
- **A live variant is immutable.** A bet's params pin `model`, `variant`, `max_win_x`, `mode`, `cost` and `rtp`, and verification replays exactly that. To retune, add a new variant id (`v2-9650`), measure it, and switch games to it in the admin panel. Never edit one that a real bet has used.
- **A paytable or rules change is a new model** with its own code.
- **Every variant measures the same RTP in every mode.** base, ante (stake 1.25×) and buy (stake 100×) are each tuned on their own tables. A super buy is priced instead (below).

What a model can choose (`TumbleModel`):
- **Multipliers in the base game** (`baseMultipliers`). Their values come from the variant's `baseMultipliers` table (default: the free-spin one), and they multiply the line win of the spin they end on, never the scatter pay. Sugar Orchard has none; Trident of Poseidon's pearls land in both.
- **Free-spin multipliers** (`freeSpinMultiplier`):
  - `SPIN`: each winning spin is multiplied by the sum on its own grid (Sugar Orchard's stars).
  - `TIDE`: every winning spin's sum joins a running total, the Tide, which multiplies that spin and every winning spin after (Trident of Poseidon; Stones of the Titan calls it the Eclipse).
- **A super buy** (`superBuyTide`, TIDE models only): mode `super`, a bought feature that draws exactly like the buy, except that its tide starts at `superBuyTide` instead of 0. The outcome records `tide_start`.
  - Its price is per variant (`Variant::$superCost`, hundredths of the bet): measure what it is worth at the default cap with `slot:simulate --mode=super`, divide by the variant's RTP, and round down to whole bets. Whole bets keep every stake exact; rounding down means it never pays below the published RTP.
  - The rules publish the price (`costs.super`), the start (`free_spins.super_buy_tide`) and the switch (`features.super`). Models without one publish exactly what they always have.
  - It is offered only with the default cap or higher: below it, the cap would cut off most of what it buys.

**The max win is a game setting.** The admin form offers a slot's max win from its variant's measured choices: the model's default (`maxWin`, its own RTP) plus each cap in the variant's `rtpByCap`. A lower cap cuts off the biggest wins, so each choice comes with its own RTP, and picking it fills the game's RTP target as the variant does.
- To offer a cap, measure it (`--caps`, below) and add it to the variant's `rtpByCap` with the RTP to publish. Use the lowest of the three modes, so no mode pays less than the published RTP.
- Bets pin `max_win_x`; bets from before the setting existed replay with the model's default.
- The currency limits' max win (an amount, `bets.max_payout`) still applies on top.

## Measuring: `slot:simulate`

```
php artisan slot:simulate sugar-orchard --variant=v1-9650 --mode=base --spins=144000000 --processes=12
```

It prints:
- RTP with a 95% interval, split between the base game and free spins
- volatility, hit rate, free-spin frequency, the average free-spins win
- the biggest win and the win distribution

To measure other caps from the same bets, run at the highest one and list the others: `--max-win=25000 --caps=1000,2000,2500,5000,10000,15000,20000`. A capped bet wins exactly min(its uncapped win, cap): the running total only grows, and reaching the cap only ends the bet. The difference between two caps is measured on the same bets, so it is much more precise than either RTP.

Two RNG options:
- `--rng=fast` (default) uses a seeded xoshiro256\*\*.
- `--rng=hmac` draws exactly as production does.

Both measure the same tables. Neither ever plays a bet.

Slot RTP has no closed form (tumbles and multipliers), so it is measured. With about 8× the stake as the standard deviation per spin, 144M spins give an interval of about ±0.13%. The published figures per model are in `docs/slots/{model}-maths.md`. A test lab certifying a model for real money reruns this and the golden vectors.

## Tests (all required for a new model or variant)

- **Golden vectors** (`tests/Unit/Slot/SpinResolverTest.php`, `TridentOfPoseidonTest.php`, `StonesOfTheTitanTest.php`). They come from the **independent** Python implementation in `tools/slot-reference/reference.py`; regenerate them with `python -I tools/slot-reference/reference.py {model} > tests/Fixtures/slot/{model}-golden.json`. A new model needs its own section in the reference and its own fixture.
- **Scripted grids:** the `scripted()` helper (`tests/Support/slot-script.php`) lays out exact grids. Use them for pays and tiers, gravity, multipliers, retriggers, the max-win cap and the buy guarantee.
- **Fixed-seed totals:** these catch any change to the maths. If a change is intended, it is a new variant.
- **Money flows, verification, secrecy, page and admin:** `SlotPlayTest`, `SlotSecrecyTest`, `SlotAdminTest`.
- **Page logic:** `tests/js/slot-timeline.test.js`.

## A new theme for an existing model

1. Add it to `config/slots.php` with the model it draws.
2. Write `resources/views/games/slot/themes/{theme}/theme.php` (Sugar Orchard's and Trident of Poseidon's are the examples):
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
   - Optional:
     - `tiers`: the multiplier's colours by value, `[[from, colour], …]`, highest first (default: Sugar Orchard's stars);
     - `hostReactions`: which pose answers a win, a big win, free spins (and `strike`, for a kit);
     - `effects`: `flames` and `dew` off (Sugar Orchard's caramel flames and dew drops);
     - `pace`: a winning tumble's timing;
     - `text`: `anteOften` (how much more often the ante brings free spins, from the maths doc), `freeSpins` (the free-spins intro's note), `multipliers` (the rules' lines about multipliers), `tideName` and `superBuy` (what the tide and the super buy are called), `headlines` (extra [label, value] pairs for the tumble-win plaque);
     - `music`: `true` plays the sound pack's `ambience()` as background music, from the first sound the browser allows until the sound is switched off (busier in free spins, paused in a hidden tab). Off by default: Sugar Orchard and Trident of Poseidon play effects only;
     - `layout`: `classic` (default: the host above the feature panels, the logo on the frame) or `throne` (feature cards on the left, a tumble-win plaque on the frame, the host full-length on his rock on the right under the logo; `resources/views/games/slot/layouts/throne.blade.php`). A throne theme also gives `tumblePlaque`, `cards` (`buy`, `ante`), `pedestal`, and a `feet` line per host pose.
   - `kit` and `kitArt`: a theme's own effects module, `public/js/orange/slot-kit-{kit}.js`, and the art only it uses. It exports `createKit(api)` and answers any of the hooks listed at `kit` in `slot-game.js`: the scenery, a winning tumble's lead-up and burst, the multipliers, the free-spins change and the big-win celebration. Trident of Poseidon's (`slot-kit-tides.js`) and Stones of the Titan's (`slot-kit-cosmos.js`) are the examples.
   - `style.blade.php` beside `theme.php`: CSS the page loads after its own, for the theme's colours.
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
