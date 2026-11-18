# Sugar Orchard: maths

Audience: Orange engineers, reviewers and a test lab. Model code `sugar-orchard`, tables in
`app/Domain/Game/Engines/Slot/Models/SugarOrchard.php`. How slots work and how to measure them:
[README.md](README.md).

## Rules

- **Grid and pays:** a 6×5 grid. 8 or more of a pay symbol anywhere pay their tier: 8–9, 10–11 or 12+.
- **Tumbles:** winners are removed, the rest fall, the gaps refill, and this repeats until nothing pays.
- **Scatters** (the basket) are counted on the final grid:
  - 4, 5 and 6+ pay 2.50×, 8.00× and 60.00× the bet;
  - 4+ award 10 free spins;
  - in free spins, 3+ add 5 (at most 100 free spins in all).
- **Multiplier stars** land only in free spins, with values 2×, 3×, 4×, 5×, 6×, 8×, 10×, 12×, 15×, 20×, 25×, 50× and 100×. A free spin that pays is multiplied by the sum of the stars on its final grid.
- **Modes:** the ante bet costs 1.25× the bet, with free spins about 1.75× as often. The buy costs 100× the bet and guarantees 4–6 scatters, weighted 80 : 16 : 4.
- **Max win:** a bet's total win stops at 5,000× the bet, and the feature ends there.

## Paytable (× the bet)

| Symbol | 8–9 | 10–11 | 12+ |
|---|---|---|---|
| A Toffee | 15.00 | 37.50 | 75.00 |
| B Peppermint | 7.50 | 15.00 | 37.50 |
| C Gummy bear | 4.50 | 10.50 | 27.00 |
| D Truffle | 3.00 | 7.50 | 18.00 |
| E Orange | 2.40 | 4.80 | 15.00 |
| F Cherries | 1.90 | 3.20 | 12.00 |
| G Strawberry | 1.60 | 2.40 | 7.50 |
| H Lemon | 1.00 | 1.90 | 6.00 |
| I Kiwi | 0.65 | 1.40 | 3.75 |

## Variants

All six columns use the same table in every reel set. Free spins use the same symbol weights in every variant. What changes per variant:
- the base weights (the buy's are the base weights without the scatter);
- the ante's scatter weight, set on the base weights × 10;
- the weight of a 2× star.

| Variant | Base A–I, S | Ante scatter (on base × 10) | Weight of 2× |
|---|---|---|---|
| v1-9650 | 454 604 754 904 1117 1277 1397 1497 1617, 185 | 2185 | 317 |
| v1-9550 | 456 606 756 906 1115 1275 1395 1495 1615, 185 | 2183 | 335 |
| v1-9450 | 459 609 759 909 1112 1272 1392 1492 1612, 185 | 2188 | 352 |

- **Free-spin weights:** A 38, B 52, C 68, D 85, E 116, F 134, G 150, H 162, I 176, S 12, M 100.
- **Star weights from 3× up:** 175, 120, 100, 80, 65, 50, 38, 28, 20, 14, 6, 3.

## Measured (slot:simulate, rng=fast, 2026-11-18)

144M bets for base and ante, 14.4M for buy. The ± figure is the 95% interval.

| Variant | Mode | RTP | Base game / free spins | Hit rate | Free spins | Volatility (sd × stake) | Biggest win |
|---|---|---|---|---|---|---|---|
| v1-9650 | base | 96.60% ± 0.13 | 64.04% / 32.56% | 1 in 3.5 | 1 in 278 | 8.17 | 2,815× |
| v1-9650 | ante | 96.41% ± 0.14 | 50.83% / 45.58% | 1 in 3.5 | 1 in 159 | 8.61 | 2,826× |
| v1-9650 | buy | 96.54% ± 0.05 | 5.91% / 90.64% | — | always | 0.96 | 3,469× |
| v1-9550 | base | 95.59% ± 0.13 | 63.59% / 32.00% | 1 in 3.5 | 1 in 279 | 8.06 | 2,131× |
| v1-9550 | ante | 95.37% ± 0.14 | 50.48% / 44.89% | 1 in 3.5 | 1 in 160 | 8.49 | 2,673× |
| v1-9550 | buy | 95.51% ± 0.05 | 5.91% / 89.60% | — | always | 0.95 | 3,708× |
| v1-9450 | base | 94.48% ± 0.13 | 62.91% / 31.57% | 1 in 3.5 | 1 in 281 | 7.97 | 2,100× |
| v1-9450 | ante | 94.60% ± 0.14 | 49.95% / 44.65% | 1 in 3.6 | 1 in 159 | 8.44 | 3,381× |
| v1-9450 | buy | 94.59% ± 0.05 | 5.91% / 88.68% | — | always | 0.94 | 4,782× |

- **Free spins:** they average about 90× the bet and run 10.3 spins.
- **Max win:** 5,000× never came up in these runs; it is a cap, not an expected outcome.

**Before real money:** every figure is within about ±0.13 of its target. Most targets sit inside their interval; v1-9450's buy (94.59 ± 0.05) sits just outside it. Before certification:
1. Measure each variant with 1B+ bets.
2. Nudge any figure that stays off target. Nudging means a new variant id, never an edit of a live one.
