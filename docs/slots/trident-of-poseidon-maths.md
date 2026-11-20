# Trident of Poseidon: maths

Audience: Orange engineers, reviewers and a test lab. Model code `trident-of-poseidon`, tables in
`app/Domain/Game/Engines/Slot/Models/TridentOfPoseidon.php`. How slots work and how to measure them:
[README.md](README.md).

## Rules

- **Grid and pays:** a 6×5 grid. 8 or more of a pay symbol anywhere pay their tier: 8–9, 10–11 or 12+.
- **Tumbles:** winners are removed, the rest fall, the gaps refill, and this repeats until nothing pays.
- **Scatters** (the trident) are counted on the final grid:
  - 4, 5 and 6+ pay 4.00×, 10.00× and 50.00× the bet;
  - 4+ award 15 free spins;
  - in free spins, 3+ add 5 (at most 100 free spins in all).
- **Pearls in the base game** (also in a bought spin) have values 2×, 3×, 4×, 5×, 6×, 8×, 10×, 12×, 15×, 20×, 25×, 50× and 100×. A spin that pays has its pay-symbol win multiplied by the sum of the pearls on its final grid. The scatter pay is never multiplied. Pearls on a spin that doesn't pay do nothing.
- **Pearls in free spins** have values 2× up to 500×: the base values plus 250× and 500×.
- **The Tide:** it starts each feature at 0. When a free spin pays, the pearls on its final grid join the Tide, and that spin's win is multiplied by the whole Tide. The Tide never goes down, so every later winning spin is multiplied by it too. A winning spin while the Tide is still 0 pays unmultiplied.
- **Modes:**
  - The ante bet costs 1.25× the bet, with free spins about twice as often (1 in 184 spins against 1 in 380).
  - The buy costs 100× the bet and guarantees 4–6 scatters, weighted 80 : 16 : 4.
- **Max win:** 5,000× the bet by default. A game may pick another measured cap in the admin panel (below). A bet's total win stops at its cap, and the feature ends there.

## Paytable (× the bet)

| Symbol | 8–9 | 10–11 | 12+ |
|---|---|---|---|
| A Hippocampus | 10.00 | 25.00 | 50.00 |
| B Sea-war helmet | 4.00 | 12.00 | 25.00 |
| C Conch horn | 3.00 | 8.00 | 16.00 |
| D Amphora | 2.00 | 6.00 | 14.00 |
| E Crab | 1.50 | 3.00 | 10.00 |
| F Pufferfish | 1.20 | 2.20 | 8.00 |
| G Sea turtle | 0.90 | 1.60 | 6.00 |
| H Starfish | 0.60 | 1.20 | 4.00 |
| I Nautilus | 0.35 | 0.90 | 2.50 |

## Variants

All six columns use the same table in every reel set. What changes per variant:
- the weight of a pearl in the base game (the ante's is the same, on its × 10 weights);
- the weight of a 2× pearl in free spins.

| Variant | Base pearl (M) | Weight of a 2× free-spin pearl |
|---|---|---|
| v1-9650 | 650 | 610 |
| v1-9550 | 634 | 643 |
| v1-9450 | 619 | 677 |

The same in every variant:
- **Base weights:** A 4500, B 6000, C 7500, D 9000, E 11000, F 12500, G 14000, H 15000, I 16000, S 1700, plus the variant's M.
- **Ante weights:** the base weights × 10, with the scatter at 21000.
- **Buy weights:** the base weights without the scatter.
- **Free-spin weights:** A 38, B 52, C 68, D 85, E 116, F 134, G 150, H 162, I 176, S 9, M 13.
- **Base pearl weights** (2× to 100×): 500, 300, 200, 150, 100, 70, 50, 30, 20, 12, 8, 3, 1.
- **Free-spin pearl weights** from 3× up (3× to 500×): 300, 220, 180, 140, 110, 85, 60, 45, 32, 22, 9, 4, 2, 1.

## Measured (slot:simulate, rng=fast, 2026-11-20)

- 288M bets for base and ante, 28.8M for buy, seed 2026.
- Played with a 25,000× cap, which nothing reached, so the run is effectively uncapped. The RTP at 5,000× is the same bets with each win stopped at 5,000× (`--caps`).
- The ± figure is the 95% interval.
- The split, volatility and biggest win are without the cap.

| Variant | Mode | RTP at 5,000× | Base game / free spins | Hit rate | Free spins | Volatility (sd × stake) | Biggest win |
|---|---|---|---|---|---|---|---|
| v1-9650 | base | 96.57% ± 0.13 | 73.03% / 23.62% | 1 in 3.6 | 1 in 380 | 11.56 | 12,629× |
| v1-9650 | ante | 96.48% ± 0.15 | 57.60% / 39.02% | 1 in 3.6 | 1 in 184 | 13.03 | 12,583× |
| v1-9650 | buy | 96.42% ± 0.07 | 7.03% / 89.70% | — | always | 1.95 | 15,772× |
| v1-9550 | base | 95.61% ± 0.13 | 72.25% / 23.45% | 1 in 3.6 | 1 in 379 | 11.58 | 12,450× |
| v1-9550 | ante | 95.27% ± 0.15 | 56.97% / 38.42% | 1 in 3.6 | 1 in 184 | 12.78 | 13,563× |
| v1-9550 | buy | 95.38% ± 0.07 | 7.03% / 88.68% | — | always | 1.94 | 18,265× |
| v1-9450 | base | 94.73% ± 0.13 | 71.58% / 23.24% | 1 in 3.6 | 1 in 379 | 11.62 | 16,158× |
| v1-9450 | ante | 94.44% ± 0.15 | 56.41% / 38.16% | 1 in 3.6 | 1 in 184 | 12.85 | 13,773× |
| v1-9450 | buy | 94.34% ± 0.07 | 7.03% / 87.63% | — | always | 1.92 | 16,931× |

- **Free spins:** they average about 89× the bet and run 15.3 spins.
- **Volatility:** higher than Sugar Orchard's (11.6 against 8.2 in the base game): the Tide keeps growing, so a few features pay very large amounts.
- **Max win:** 5,000× is a cap, not an expected outcome. Wins above it are rare: with v1-9650, the cap takes 0.07 points of RTP from the base game and 0.32 from the buy.

## Max win: the choices and their RTP

A game picks its max win in the admin panel (Games → the game → Max win), from the caps below. Each choice has its own RTP, and picking it sets the game's RTP target.

**What is published:**
- The default (5,000×) publishes the variant's own RTP.
- Every other cap publishes the variant's RTP plus the lowest of the three modes' measured change from 5,000×, rounded down. That way no mode pays less than the published figure.
- The changes come from the same bets with only the cap moved, so they are far more precise than the RTP itself.
- The buy sets the caps below 5,000×: a bought feature costs 100×, so a low cap cuts the most from it. The base game sets the caps above 5,000×.

| Max win | Published: v1-9650 | v1-9550 | v1-9450 | Measured with v1-9650: base | ante | buy |
|---|---|---|---|---|---|---|
| 1,000× | 91.69% | 90.77% | 89.85% | 95.31% | 94.41% | 91.61% |
| 2,000× | 94.41% | 93.45% | 92.48% | 96.03% | 95.58% | 94.33% |
| 2,500× | 95.11% | 94.14% | 93.16% | 96.22% | 95.88% | 95.03% |
| **5,000×** (default) | **96.50%** | **95.50%** | **94.50%** | 96.57% | 96.48% | 96.42% |
| 10,000× | 96.56% | 95.58% | 94.58% | 96.64% | 96.61% | 96.73% |
| 15,000× | 96.57% | 95.58% | 94.58% | 96.64% | 96.61% | 96.73% |
| 20,000× | 96.57% | 95.58% | 94.58% | 96.64% | 96.61% | 96.73% |

- **Above 10,000×:** wins that large did come up (the biggest was 18,265×), but so rarely that a higher cap adds at most 0.01 points in any mode.
- **At 2,500× and below:** the published figure is the buy's. The base game and ante pay more, for example 95.31% against the published 91.69% at 1,000×, where a bought feature can return at most 10× its price. This holds even with the buy switched off. Publishing a separate buy RTP would need the engine to keep an RTP per mode.

**To re-measure** (one run at the highest cap measures every lower one):

```
php artisan slot:simulate trident-of-poseidon --variant=v1-9650 --mode=base --spins=288000000 --processes=16 --seed=2026 --max-win=25000 --caps=1000,2000,2500,5000,10000,15000,20000
```

## Before real money

**How far each figure is from its target:**
- Every figure is within 0.24 points of its target.
- An earlier tuning run used the same tables at a 5,000× cap, with 96M base and ante bets and 12.8M buys, seed 7. Its figures are within 0.24 points of these.
- **The buy is the clearest miss:** it is below target in every variant in both runs, by 0.02 to 0.09 points in the tuning run and 0.08 to 0.16 in this one. Its interval is narrow, so this is probably not noise.
- **Other misses:** v1-9550's ante (−0.23) and v1-9450's base (+0.23) also sit outside their intervals in this run, but not in the tuning run.

**Before certification:**
1. **Measure again:** each variant with 1B+ bets, with a different seed per variant. Here all three shared seed 2026, so their errors move together.
2. **Nudge what stays off target:** a lighter 2× free-spin pearl raises the buy, and a lighter base pearl lowers the base game. Nudging means a new variant id, never an edit of a live one.
3. **Re-measure the caps** for the new variants and update `rtpByCap`.
