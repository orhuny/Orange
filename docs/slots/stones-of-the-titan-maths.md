# Stones of the Titan: maths

Audience: Orange engineers, reviewers and a test lab. Model code `stones-of-the-titan`, tables in
`app/Domain/Game/Engines/Slot/Models/StonesOfTheTitan.php`. How slots work and how to measure them:
[README.md](README.md).

## The same tables as Trident of Poseidon

Stones of the Titan's paytable, rules and weight tables are a copy of Trident of Poseidon's v1:
- Its base game, ante and buy draw exactly as Trident's do.
- `StonesOfTheTitanTest` checks that every table is equal to Trident's. It also checks that the outcomes of the same seeds are equal, and that the fixed-seed totals are the ones Trident recorded.
- So Trident's measurements hold for these three modes, and are not repeated here: the RTP per variant and mode, the split, volatility, the max-win choices and their published RTPs. See [trident-of-poseidon-maths.md](trident-of-poseidon-maths.md).
- The tables are this model's own from here on. A retune of either game is a new variant of that game only.

**Names:** the symbols are named for this game:

| Letter | Name | 8–9 | 10–11 | 12+ |
|---|---|---|---|---|
| A | Caged star | 10.00 | 25.00 | 50.00 |
| B | Orrery | 4.00 | 12.00 | 25.00 |
| C | Titan's shield | 3.00 | 8.00 | 16.00 |
| D | Sword in the meteorite | 2.00 | 6.00 | 14.00 |
| E | Ammonite | 1.50 | 3.00 | 10.00 |
| F | Sun geode | 1.20 | 2.20 | 8.00 |
| G | Amethyst | 0.90 | 1.60 | 6.00 |
| H | Runestone | 0.60 | 1.20 | 4.00 |
| I | Comet shard | 0.35 | 0.90 | 2.50 |

- **S** is the scatter (the eclipse sigil).
- **M** is the meteor, the multiplier. Trident calls it a pearl.
- **The Eclipse** is the free-spins tide. It starts each feature at 0, and every winning free spin's meteors join it.

## The Super Eclipse buy

This mode is not in Trident. It is a bought feature (4–6 scatters guaranteed, 80 : 16 : 4, exactly as the buy draws) whose Eclipse starts at **10** instead of 0. Every winning free spin is therefore multiplied by at least 10×, and the meteors add to that.

**Its price** is per variant: what it measured to be worth at the 5,000× cap, divided by the variant's RTP, rounded down to whole bets.
- Whole bets keep the stake of any bet exact.
- Rounding down means it never pays below the variant's RTP.

**Measured** (slot:simulate, rng=fast, 2026-11-21):
- 10M bonuses per variant, seed 2026.
- Played at a 25,000× cap, so effectively uncapped; the 5,000× figures are the same bonuses with each win stopped at 5,000× (`--caps`).

| Variant | Worth at 5,000× (× the bet) | Price | RTP at 5,000× | RTP at 10,000× | Biggest win |
|---|---|---|---|---|---|
| v1-9650 | 172.88 ± 0.13 | **179×** | 96.58% ± 0.07 | 96.77% | 15,193.50× |
| v1-9550 | 171.91 ± 0.13 | **180×** | 95.51% ± 0.07 | 95.71% | 17,958.75× |
| v1-9450 | 170.88 ± 0.13 | **180×** | 94.93% ± 0.07 | 95.12% | 16,087.90× |

Other figures:
- **Where the value comes from:** about 7.0× from the bought spin itself (scatters and its line wins), the rest from the free spins.
- **Length:** the feature runs 15.3 free spins on average.
- **Volatility:** 1.17 × the stake (sd per bonus 210× the bet).
- **The cap:** 5,000× cuts about 0.2% of its value, 2,500× about 1.1%.

**At other caps:**
- The super buy is offered only at the default 5,000× cap or higher (`SlotEngine::validateConfig`). Below it, the cap would cut off too much of what it buys.
- Above 5,000× it pays a little more (the table). The other modes set the published RTP of those caps, so `rtpByCap` stays Trident's.

**v1-9450 is the furthest above target**, at +0.43 points: 180.82× rounds down to 180×. A 181× price would pay 94.41%, below target.

**To re-measure:**

```
php artisan slot:simulate stones-of-the-titan --variant=v1-9650 --mode=super --spins=10000000 --processes=16 --seed=2026 --max-win=25000 --caps=2500,5000,10000,15000,20000
```

The report's RTP is against the variant's current price. Worth = RTP × price.

## Before real money

Everything in Trident's "Before real money" applies here too: the base game, ante and buy are the same tables.

For the super buy:
1. Measure each variant again with 100M+ bonuses and a different seed per variant.
2. If a price moves, it is a new variant id, never an edit of a live one: a bet pins its price (`cost`).
