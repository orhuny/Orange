# Tutti Frutti: maths

Audience: whoever certifies, reviews or retunes Tutti Frutti's maths (model `tutti-frutti`, `app/Domain/Game/Engines/Slot/Models/TuttiFrutti.php`).

Tutti Frutti is our first **paylines** slot (`LinesModel`, played by `LinesResolver`). The tumbling slots' rules don't apply to it.

## Rules

- **Reels:** 5 reels × 3 rows. Every cell is drawn on its own from its reel's weight table (the draw order is in `SlotVerifier::describe()`).
- **Lines:** 20 fixed lines (`TuttiFrutti::LINES`). A line pays when the same fruit runs along it from the leftmost reel: the peach from 2, the others from 3. Each line pays its run; all lines add up.
- **The kiss (W), the wild:** on reels 2–5 only. It stands in for every fruit. Each kiss draws a value: 1 (plain), 2, 3 or 5. A paying line is multiplied by the **sum** of the multipliers of the kisses in its run (1 when there are none): 2× and 3× make 5×.
- **The cocktail (S), the scatter:** anywhere. 3, 4 or 5 pay 2×, 10× or 50× the bet (never multiplied) and award 10, 12 or 15 free spins.
- **Free spins:** "the amore" starts at 1. Every kiss that lands adds 1 to it, before its spin pays, and it multiplies every free spin's line wins. 3+ cocktails in free spins add 5 spins, up to 100 in all. Free spins use their own tables (more kisses, more often multipliers).
- **Buy:** 100× the bet. The bought spin places 3, 4 or 5 cocktails at random cells (`buyScatters`, per variant), then fills the rest without scatters.
- **No ante, no super buy.**
- **Max win:** 5,000× the bet by default. A game may pick another measured cap in the admin panel (below). A bet's total win stops at its cap, and the feature ends there.

## Paytable (× the line bet; the line bet is the bet ÷ 20)

| Symbol | 2 | 3 | 4 | 5 |
|---|---|---|---|---|
| A Peach | 5 | 50 | 200 | 1,000 |
| B Pineapple | | 40 | 150 | 500 |
| C Dragon fruit | | 30 | 100 | 400 |
| D Pomegranate | | 25 | 80 | 300 |
| E Mango | | 15 | 40 | 150 |
| F Lime | | 10 | 30 | 120 |
| G Blueberries | | 10 | 25 | 100 |
| H Fig | | 5 | 20 | 80 |
| I Lychee | | 5 | 15 | 60 |

The page shows the same pays × the bet (÷ 20). Five peaches on a line pay 50× the bet before any kiss or amore.

## Tables

Every reel has the same fruit weights: A 400, B 500, C 600, D 700, E 1,100, F 1,200, G 1,300, H 1,400, I 1,500. Reel 1 never has a kiss.

| | Scatter | Kiss (reels 2–5) | Kiss values 1 / 2 / 3 / 5 |
|---|---|---|---|
| Base game | 215 | per variant (below) | 80 / 12 / 6 / 2 |
| Free spins | 250 | 635 | 70 / 16 / 9 / 5 |
| Buy (its fill) | none | as the base game | as the base game |

| Variant | Base kiss | Bought scatters 3 / 4 / 5 |
|---|---|---|
| v1-9650 | 430 | 561 / 329 / 110 |
| v1-9550 | 421 | 573 / 324 / 103 |
| v1-9450 | 411 | 586 / 317 / 97 |

The base kiss sets the base game's RTP. The bought scatter mix sets the buy's: more 4s and 5s mean longer free spins, and the amore grows with their length.

**How it was tuned:** the uncapped base-game RTP is computed exactly (each line's run over independent cells, the scatter count as a sum of binomials, and the free spins from the per-spin means of the line win, the kisses and their product). The simulator then measures every variant, mode and cap. The two agree within the simulator's interval.

## Measured (slot:simulate, rng=fast, 2026-11-21)

400M base bets and 20M buys per variant, seed 7, played at a 20,000× cap. Nothing in the base game reached it (the biggest win was 13,181×), so the base runs are effectively uncapped. The RTP at 5,000× comes from the same bets with each win stopped at 5,000× (`--caps`). The split, volatility and biggest win are without the cap.

| Variant | Mode | RTP at 5,000× | Base game / free spins | Hit rate | Free spins | Volatility (sd × stake) | Biggest win |
|---|---|---|---|---|---|---|---|
| v1-9650 | base | 96.47% ± 0.10 | 62.86% / 33.62% | 1 in 2.9 | 1 in 216 | 10.45 | 13,181× |
| v1-9650 | buy | 96.51% ± 0.07 | 10.16% / 86.43% | — | always | 1.53 | 20,000× (cap) |
| v1-9550 | base | 95.49% ± 0.10 | 61.83% / 33.69% | 1 in 2.9 | 1 in 216 | 10.56 | 11,718× |
| v1-9550 | buy | 95.52% ± 0.07 | 9.77% / 85.83% | — | always | 1.52 | 20,000× (cap) |
| v1-9450 | base | 94.55% ± 0.10 | 60.73% / 33.84% | 1 in 2.9 | 1 in 215 | 10.66 | 12,712× |
| v1-9450 | buy | 94.61% ± 0.07 | 9.44% / 85.25% | — | always | 1.51 | 20,000× (cap) |

## Max win: the choices and their RTP

A game picks its max win in the admin panel (Games → the game → Max win), from the caps below. Each choice has its own RTP, and picking it sets the game's RTP target.

- The default, 5,000×, publishes the variant's RTP.
- Every other cap publishes the variant's RTP plus the lower of the two modes' measured change from 5,000×, rounded down, so no mode pays less than the published figure (as for Trident of Poseidon).
- The changes come from the same bets with only the cap moved, so they are far more precise than the RTP itself.
- The buy sets the caps below 5,000× (a bought feature costs 100×, so a low cap cuts the most from it). The base game sets the caps above.

| Max win | Published: v1-9650 | v1-9550 | v1-9450 | Measured with v1-9650: base | buy |
|---|---|---|---|---|---|
| 1,000× | 94.30% | 93.32% | 92.36% | 95.76% | 94.32% |
| 2,000× | 95.90% | 94.90% | 93.91% | 96.29% | 95.91% |
| 2,500× | 96.14% | 95.15% | 94.15% | 96.37% | 96.15% |
| **5,000×** (default) | **96.50%** | **95.50%** | **94.50%** | 96.47% | 96.51% |
| 10,000× | 96.51% | 95.52% | 94.52% | 96.48% | 96.58% |
| 15,000× | 96.51% | 95.52% | 94.52% | 96.48% | 96.58% |
| 20,000× | 96.51% | 95.52% | 94.52% | 96.48% | 96.59% |

- **Below 5,000×:** a low cap takes the most from the buy (2.2 points at 1,000×).
- **Above 10,000×:** the biggest base-game win in 1.2 billion bets was 13,181×, so a cap above 10,000× adds at most 0.03 points.

**To re-measure** (one run at the highest cap measures every lower one):

```
php artisan slot:simulate tutti-frutti --variant=v1-9650 --mode=base --spins=400000000 --processes=20 --seed=7 --max-win=20000 --caps=1000,2000,2500,5000,10000,15000
php artisan slot:simulate tutti-frutti --variant=v1-9650 --mode=buy --spins=20000000 --processes=20 --seed=7 --max-win=20000 --caps=1000,2000,2500,5000,10000,15000
```

## Tests

- **Golden vectors:** 89 outcomes from the independent Python reference (`tools/slot-reference/reference.py tutti-frutti`, written from the published rules), in `tests/Fixtures/slot/tutti-frutti-golden.json`. `tests/Unit/Slot/TuttiFruttiTest.php` checks every one.
- **Fixed-seed totals:** the same test pins the total of 20,000 base bets and 300 buys, so any change to the tables shows.

## Before real money

- A test lab reruns the measurement and the golden vectors.
- A trademark search for the name: other providers have slots called "Tutti Frutti" (Kajot, Cozy Games, Logispin, Cartel Gaming); the bare name may not be registrable.
