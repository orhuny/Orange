# Tutti Frutti: the girls (art prompts, round 2)

Audience: whoever generates the art for Tutti Frutti's girl picker. Every prompt is complete once you paste in the girl's lines from the tables; nothing else changes.

**Status (2026-11-24): delivered and in the game.** All 70 files are in `storage/app/art/tutti-frutti/round2/`. `python -I scripts/art/process-tutti-frutti.py --only-girls` rebuilds just the girls and the picker's art (about 5 minutes); each girl's kiss hand is measured by eye in `GIRLS` in that script, so a regenerated kiss pose needs its point checked.

## The feature

The player chooses who stands beside the reels from **10 girls**: Bella and Marina, who are already in the game, and 8 new ones.

- **Her lucky girl** stands on the right. She blows the kisses when a kiss multiplier pays.
- **Her friend** stands on the left. She cheers the wins and gets nervous when two cocktails are down.
- The default pair is Bella (lucky girl) and Marina (friend). The choice is kept on the player's device.
- **It is only for show.** Every girl plays the same game with the same odds, and the picker says so.

Each girl has **her fruit** from the reels and her colour, so the picker reads like part of the game.

## How the art is used

Every girl needs the same **7 poses** and **1 picker card**. The game uses whichever she has; a pose that isn't delivered yet falls back to her idle pose.

| File | When the game shows it | Needed to launch her? |
|---|---|---|
| `char-{girl}-idle.png` | at rest; the anchor of all her other images | yes |
| `char-{girl}-win.png` | a line win | yes |
| `char-{girl}-kiss.png` | a kiss multiplier pays (as the lucky girl) | yes |
| `char-{girl}-tease.png` | two cocktails are down (as the friend) | no |
| `char-{girl}-dance.png` | big wins | no |
| `char-{girl}-party.png` | free spins start | yes |
| `char-{girl}-waiting.png` | a minute without a spin | no |
| `card-{girl}.png` | her card in the picker | yes |

**Which way she faces:** draw every new girl turned toward the **left** of the image, as Bella is. On the left side of the reels the game mirrors her so she faces them. Because of that, put **no writing, numbers or logos** on her or her clothes.

**Her size and feet:** the full figure from head to toe, filling about 92% of the image's height, with her feet at the same height in every pose. The game stands each pose on the same podium.

## Rules (read before generating)

1. **One prompt per request.** Use ChatGPT image, on a transparent background.
2. **Each girl starts with her idle pose.** Attach `char-bella-idle.png` (round 1) as the first image and `sym-a-peach.png` as the second, so she has the same finish and scale as Bella. Regenerate until you love her. Then attach her own idle image to every other image of hers.
3. **Same style for everyone:** crisp, glossy stylised 3D like a premium mobile game, bright summer light, no blur. Every prompt carries this.
4. **Sexy, not explicit.** They are glamorous summer pin-ups in figure-hugging dresses, always fully dressed, all adults in their late twenties or early thirties. Never ask for lingerie, swimwear, nudity or see-through fabric. Image tools refuse it, and regulated operators would reject the game.
5. **Original women.** Never name or describe a real person, model, actress or character. Each girl must look clearly different from the others: her hair, her skin, her outfit and her colour.
6. **Keep it ours:** no fruit hats or turbans, no heart-shaped sunglasses, no candy, no bees.
7. **Delivery:** put the files, under the names below, in `C:\Git\Orange\storage\app\art\tutti-frutti\round2\`. Batches are fine. A girl joins the picker once her "yes" files from the table above are in.

### Order

1. `char-marina-kiss`, `char-bella-tease` and the two existing girls' cards: they complete Bella and Marina.
2. `ui-girl-frame` and `ui-girl-button`.
3. One girl at a time: her idle pose, then her win, kiss, party and card, then the rest.

---

## The 10 girls

| Girl | Her fruit | Her colour | Her look in one line |
|---|---|---|---|
| Bella | dragon fruit | hot pink | jet-black ponytail, bronze skin, hot-pink halter dress, the house cocktail |
| Marina | the cocktail | turquoise | long copper waves, freckles, turquoise one-shoulder dress, a gold shaker |
| Aurora | peach | coral peach | strawberry-blonde waves, peaches-and-cream skin, a puff-sleeved coral dress |
| Stella | pineapple | sunshine yellow | platinum-blonde vintage waves, a big white sun hat, a ruffled yellow dress |
| Rosa | pomegranate | ruby red | honey balayage beach waves, golden tan, a rose in her hair, a ruby sequin halter |
| Gemma | mango | mango orange and gold | a long black braid with gold beads, warm brown skin, an embroidered mango dress |
| Jade | lime | lime green | sleek black hair with blunt bangs, a corset-style lime dress, a jade choker |
| Celeste | blueberries | cobalt blue | a chestnut bob, olive skin, a silk headband, a cobalt wrap dress |
| Luna | fig | violet | a big natural afro, deep brown skin, large gold hoops, a violet sequin dress |
| Perla | lychee | pearl white and rose | rose-gold hair in a high bun, pearls, a pearl-white cowl-neck dress |

---

## Completing Bella and Marina

### char-marina-kiss.png · attach char-marina-idle and sym-a-peach
Marina's poses face the right of the image; keep that.
```text
The same woman as in the first attached image: identical face, sapphire-blue eyes, freckles, berry-red lips, long copper-red waves over one shoulder, dangling gold-and-turquoise earrings, the same turquoise satin one-shoulder ruched mini dress, white sandals with gold buckles, gold cuff and gold anklet, and the same gold cocktail shaker in her left hand. New pose: she blows a kiss toward the right of the image: her right hand is raised in front of her lips with the palm open and facing right, her lips puckered in a kiss, her eyes half-closed and her head tilted, one knee bent playfully. Draw no hearts or effects around her: the game adds them. Romantic, flirtatious and charming. Fully dressed, nothing explicit. The full figure from head to toe, three-quarter view turned toward the right, feet at the same height and the head and body the same size in the frame as in the first attached image. Match the finish of the second attached image. Premium AAA slot game art, high-end stylised 3D character render, crisp and clean like a premium mobile-game heroine: glossy satin, polished gold jewellery, smooth skin with a soft healthy glow, saturated colours, crisp specular highlights, bright clean summer sunlight from the upper left, a soft turquoise rim light from the right, tack-sharp edges, no blur, no depth of field, high detail. No text, no outline, no cast shadow, no floor, no frame. On a transparent background, exported as a PNG with transparency. The whole figure, including anything she raises, is in frame with a clear margin.
```

### char-bella-tease.png · attach char-bella-idle and sym-a-peach
```text
The same woman as in the first attached image: identical face, emerald-green eyes, coral-red lips, sleek jet-black high ponytail with the hot-pink hibiscus, white cat-eye sunglasses on her head, gold hoop earrings, sun-kissed bronze skin, the same hot-pink satin halter mini dress with its gold belt and side slit, gold sandals and gold bangles, and the same cocktail. New pose: she can't wait: she holds her cocktail close in her left hand, raises her right hand near her chin with her fingers crossed, and bites her lower lip in excited anticipation, leaning forward slightly, her eyes on the left of the image. Thrilling, playful and alluring. Fully dressed, nothing explicit. The full figure from head to toe, three-quarter view turned toward the left, feet at the same height and the head and body the same size in the frame as in the first attached image. Match the finish of the second attached image. Premium AAA slot game art, high-end stylised 3D character render, crisp and clean like a premium mobile-game heroine: glossy satin, polished gold jewellery, smooth skin with a soft healthy glow, saturated colours, crisp specular highlights, bright clean summer sunlight from the upper left, a soft turquoise rim light from the right, tack-sharp edges, no blur, no depth of field, high detail. No text, no outline, no cast shadow, no floor, no frame. On a transparent background, exported as a PNG with transparency. The whole figure, including anything she raises, is in frame with a clear margin.
```

Bella's `wink` and Marina's `cheer` are their win poses, and Marina's `shake` is her tease: nothing to redo. Their cards follow the card prompt below, with these lines:

| File | Attach | Her (card) | Her fruit |
|---|---|---|---|
| `card-bella.png` | char-bella-idle | `her jet-black high ponytail with the hot-pink hibiscus, the white cat-eye sunglasses on her head, gold hoop earrings and the hot-pink satin halter neckline` | `a glossy hot-magenta dragon fruit` |
| `card-marina.png` | char-marina-idle | `her long copper-red waves over one shoulder, her freckles, the dangling gold-and-turquoise earrings and the turquoise one-shoulder neckline` | `a small turquoise-and-gold cocktail shaker` |

---

## The 8 new girls: their idle pose (the anchor) · attach char-bella-idle and sym-a-peach

Each prompt is complete. Generate one, regenerate until you love her, then go on to her other poses.

### char-aurora-idle.png
```text
A new original glamorous hostess of the same Riviera beach club as the woman in the first attached image, rendered in exactly the same style, finish and scale, but a completely different woman: a radiant adult woman in her late twenties with a soft, curvy hourglass figure and peaches-and-cream skin with a warm sun-kissed blush; long, glossy strawberry-blonde hair in big romantic waves held back on one side by a small gold peach-shaped clip; hazel-green eyes with long lashes; glossy peach-coral lips; small rose-gold hoop earrings. She wears a figure-hugging coral-peach satin mini dress with short puffed off-the-shoulder sleeves and a ruched bodice, the hem high on the thigh, with rose-gold strappy high-heeled sandals and a thin rose-gold anklet. In her left hand she holds a coupe glass of peach bellini with a peach slice on the rim. Pose: the full figure from head to toe, standing with her weight on one hip in a confident, alluring summer pin-up pose, three-quarter view with her body turned toward the left of the image; her right hand resting lightly on her collarbone; a sweet, flirtatious smile with her head tilted, looking at the viewer. Sexy, glamorous and playful, tasteful and fully dressed, nothing explicit. Original design, not resembling any real person, model or existing character, and clearly not the woman in the first attached image. Match the finish of the second attached image. The figure fills about 92% of the frame height. Premium AAA slot game art, high-end stylised 3D character render, crisp and clean like a premium mobile-game heroine: glossy satin, polished jewellery, smooth skin with a soft healthy glow, saturated colours, crisp specular highlights, bright clean summer sunlight from the upper left, a soft turquoise rim light from the right like light off the sea, tack-sharp edges, no blur, no depth of field, high detail. No text, no logos, no outline, no cast shadow, no floor, no frame. On a transparent background, exported as a PNG with transparency. The whole figure from the top of her head to the soles of her sandals is in frame with a clear margin.
```

### char-stella-idle.png
```text
A new original glamorous hostess of the same Riviera beach club as the woman in the first attached image, rendered in exactly the same style, finish and scale, but a completely different woman: a dazzling adult woman around thirty, tall with long legs and a curvy figure, fair skin with a light golden tan; long, glamorous platinum-blonde hair in smooth vintage waves swept to one side; bright sky-blue eyes with dramatic lashes; classic glossy red lips; small gold star-shaped earrings. She wears a sunshine-yellow satin off-the-shoulder mini dress with a ruffled neckline, cinched at the waist by a thin gold belt, with gold platform high-heeled sandals and an oversized white straw sun hat with a yellow ribbon, tilted on her head. In her left hand she holds a tall glass of golden pineapple cocktail with a pineapple wedge and a small yellow paper parasol. Pose: the full figure from head to toe, standing with her weight on one hip in a confident, alluring summer pin-up pose, three-quarter view with her body turned toward the left of the image; her right hand holding the brim of her sun hat; a confident, dazzling movie-star smile, looking at the viewer. Sexy, glamorous and playful, tasteful and fully dressed, nothing explicit. Original design, not resembling any real person, model or existing character, and clearly not the woman in the first attached image. Match the finish of the second attached image. The figure fills about 92% of the frame height. Premium AAA slot game art, high-end stylised 3D character render, crisp and clean like a premium mobile-game heroine: glossy satin, polished jewellery, smooth skin with a soft healthy glow, saturated colours, crisp specular highlights, bright clean summer sunlight from the upper left, a soft turquoise rim light from the right like light off the sea, tack-sharp edges, no blur, no depth of field, high detail. No text, no logos, no outline, no cast shadow, no floor, no frame. On a transparent background, exported as a PNG with transparency. The whole figure from the top of her hat to the soles of her sandals is in frame with a clear margin.
```

### char-rosa-idle.png
```text
A new original glamorous hostess of the same Riviera beach club as the woman in the first attached image, rendered in exactly the same style, finish and scale, but a completely different woman: a sultry adult woman in her early thirties with a curvy hourglass figure and golden tanned skin; long, voluminous honey-caramel balayage hair in loose beach waves, with a single red rose tucked behind one ear; warm brown eyes with smoky liner; glossy ruby-red lips; gold drop earrings. She wears a figure-hugging ruby-red sequin halter mini dress that sparkles, with an open back, red strappy high-heeled sandals and a stack of thin gold bracelets. In her left hand she holds a champagne flute of sparkling ruby pomegranate spritz with a few pomegranate seeds in it. Pose: the full figure from head to toe, standing with her weight on one hip in a confident, alluring summer pin-up pose, three-quarter view with her body turned toward the left of the image; her right hand on her hip; a smouldering, confident smile, looking at the viewer. Sexy, glamorous and playful, tasteful and fully dressed, nothing explicit. Original design, not resembling any real person, model or existing character, and clearly not the woman in the first attached image. Match the finish of the second attached image. The figure fills about 92% of the frame height. Premium AAA slot game art, high-end stylised 3D character render, crisp and clean like a premium mobile-game heroine: glossy sequins and satin, polished jewellery, smooth skin with a soft healthy glow, saturated colours, crisp specular highlights, bright clean summer sunlight from the upper left, a soft turquoise rim light from the right like light off the sea, tack-sharp edges, no blur, no depth of field, high detail. No text, no logos, no outline, no cast shadow, no floor, no frame. On a transparent background, exported as a PNG with transparency. The whole figure from the top of her head to the soles of her sandals is in frame with a clear margin.
```

### char-gemma-idle.png
```text
A new original glamorous hostess of the same Riviera beach club as the woman in the first attached image, rendered in exactly the same style, finish and scale, but a completely different woman: a stunning adult woman in her late twenties of South Asian descent, with a curvy figure and warm brown skin with a radiant glow; very long, glossy black hair in a loose side braid over one shoulder, woven with tiny gold beads; large dark-brown eyes with elegant winged liner; glossy terracotta lips; ornate gold chandelier earrings. She wears a figure-hugging mango-orange satin mini dress with a sweetheart neckline and fine gold embroidery along the bodice and hem, gold strappy high-heeled sandals and slim gold bangles on both wrists. In her left hand she holds a coupe glass of mango-passion cocktail with a mango slice on the rim. Pose: the full figure from head to toe, standing with her weight on one hip in a confident, alluring summer pin-up pose, three-quarter view with her body turned toward the left of the image; her right hand playing with the end of her braid; a warm, captivating smile, looking at the viewer. Sexy, glamorous and playful, tasteful and fully dressed, nothing explicit. Original design, not resembling any real person, model or existing character, and clearly not the woman in the first attached image. Match the finish of the second attached image. The figure fills about 92% of the frame height. Premium AAA slot game art, high-end stylised 3D character render, crisp and clean like a premium mobile-game heroine: glossy satin, polished gold jewellery, smooth skin with a soft healthy glow, saturated colours, crisp specular highlights, bright clean summer sunlight from the upper left, a soft turquoise rim light from the right like light off the sea, tack-sharp edges, no blur, no depth of field, high detail. No text, no logos, no outline, no cast shadow, no floor, no frame. On a transparent background, exported as a PNG with transparency. The whole figure from the top of her head to the soles of her sandals is in frame with a clear margin.
```

### char-jade-idle.png
```text
A new original glamorous hostess of the same Riviera beach club as the woman in the first attached image, rendered in exactly the same style, finish and scale, but a completely different woman: a striking adult woman in her late twenties of East Asian descent, slim and toned with elegant curves and light porcelain skin; sleek, glossy jet-black hair, long and straight, with blunt bangs; dark almond-shaped eyes with sharp winged liner; glossy berry-pink lips; a thin gold choker with a small lime-green gem. She wears a glossy lime-green satin mini dress with a structured corset-style bodice and thin straps, the hem high on the thigh, with white patent high-heeled sandals. In her left hand she holds a tall glass of lime mojito with fresh mint and a lime wheel. Pose: the full figure from head to toe, standing with her weight on one hip in a confident, alluring summer pin-up pose, three-quarter view with her body turned toward the left of the image; her right hand lifted to brush her bangs; a cool, mysterious half-smile, looking at the viewer. Sexy, glamorous and playful, tasteful and fully dressed, nothing explicit. Original design, not resembling any real person, model or existing character, and clearly not the woman in the first attached image. Match the finish of the second attached image. The figure fills about 92% of the frame height. Premium AAA slot game art, high-end stylised 3D character render, crisp and clean like a premium mobile-game heroine: glossy satin, polished jewellery, smooth skin with a soft healthy glow, saturated colours, crisp specular highlights, bright clean summer sunlight from the upper left, a soft turquoise rim light from the right like light off the sea, tack-sharp edges, no blur, no depth of field, high detail. No text, no logos, no outline, no cast shadow, no floor, no frame. On a transparent background, exported as a PNG with transparency. The whole figure from the top of her head to the soles of her sandals is in frame with a clear margin.
```

### char-celeste-idle.png
```text
A new original glamorous hostess of the same Riviera beach club as the woman in the first attached image, rendered in exactly the same style, finish and scale, but a completely different woman: an elegant adult woman around thirty with an athletic, toned hourglass figure and olive skin; a glossy chestnut-brown chin-length bob with a deep side part, held back by a cobalt-blue silk scarf tied as a headband; grey-blue eyes; glossy nude-pink lips; small sapphire-blue drop earrings. She wears a figure-hugging cobalt-blue satin wrap mini dress with a V-neckline and a thigh-high slit, with silver strappy high-heeled sandals. In her left hand she holds a coupe glass of deep blue blueberry cocktail with three blueberries on a pick. Pose: the full figure from head to toe, standing with her weight on one hip in a confident, alluring summer pin-up pose, three-quarter view with her body turned toward the left of the image; her right hand tucking her hair behind her ear; a chic, knowing smile, looking at the viewer. Sexy, glamorous and playful, tasteful and fully dressed, nothing explicit. Original design, not resembling any real person, model or existing character, and clearly not the woman in the first attached image. Match the finish of the second attached image. The figure fills about 92% of the frame height. Premium AAA slot game art, high-end stylised 3D character render, crisp and clean like a premium mobile-game heroine: glossy satin and silk, polished jewellery, smooth skin with a soft healthy glow, saturated colours, crisp specular highlights, bright clean summer sunlight from the upper left, a soft turquoise rim light from the right like light off the sea, tack-sharp edges, no blur, no depth of field, high detail. No text, no logos, no outline, no cast shadow, no floor, no frame. On a transparent background, exported as a PNG with transparency. The whole figure from the top of her head to the soles of her sandals is in frame with a clear margin.
```

### char-luna-idle.png
```text
A new original glamorous hostess of the same Riviera beach club as the woman in the first attached image, rendered in exactly the same style, finish and scale, but a completely different woman: a gorgeous adult woman in her late twenties with deep brown skin and a curvy hourglass figure; a big, voluminous natural afro of tight glossy curls; dark eyes with shimmering violet eyeshadow; glossy plum lips; large gold hoop earrings. She wears a figure-hugging shimmering violet sequin mini dress with long sleeves and an open back, gold strappy high-heeled sandals and a gold cuff on one upper arm. In her left hand she holds a coupe glass of deep violet fig-and-berry cocktail. Pose: the full figure from head to toe, standing with her weight on one hip in a confident, alluring summer pin-up pose, three-quarter view with her body turned toward the left of the image; her right hand resting on her hip with her fingers spread; a radiant, self-assured smile, looking at the viewer. Sexy, glamorous and playful, tasteful and fully dressed, nothing explicit. Original design, not resembling any real person, model or existing character, and clearly not the woman in the first attached image. Match the finish of the second attached image. The figure fills about 92% of the frame height. Premium AAA slot game art, high-end stylised 3D character render, crisp and clean like a premium mobile-game heroine: glossy sequins, polished gold jewellery, smooth skin with a soft healthy glow, saturated colours, crisp specular highlights, bright clean summer sunlight from the upper left, a soft turquoise rim light from the right like light off the sea, tack-sharp edges, no blur, no depth of field, high detail. No text, no logos, no outline, no cast shadow, no floor, no frame. On a transparent background, exported as a PNG with transparency. The whole figure from the top of her hair to the soles of her sandals is in frame with a clear margin.
```

### char-perla-idle.png
```text
A new original glamorous hostess of the same Riviera beach club as the woman in the first attached image, rendered in exactly the same style, finish and scale, but a completely different woman: a graceful adult woman in her late twenties, slender with soft curves and fair skin with a rosy glow; pastel rose-gold hair in a sleek high bun with a few loose strands framing her face; soft grey eyes with long lashes; glossy rose-pink lips; small pearl stud earrings and a single-strand pearl necklace. She wears a pearl-white satin cowl-neck mini dress with thin straps, trimmed in rose pink at the hem, with rose-pink high-heeled sandals with pearl straps. In her left hand she holds a martini glass of pearly white lychee martini with a peeled lychee on a pick. Pose: the full figure from head to toe, standing with her weight on one hip in a confident, alluring summer pin-up pose, three-quarter view with her body turned toward the left of the image; her right hand touching her pearl necklace; a sweet, teasing smile, looking at the viewer. Sexy, glamorous and playful, tasteful and fully dressed, nothing explicit. Original design, not resembling any real person, model or existing character, and clearly not the woman in the first attached image. Match the finish of the second attached image. The figure fills about 92% of the frame height. Premium AAA slot game art, high-end stylised 3D character render, crisp and clean like a premium mobile-game heroine: glossy satin and pearls, polished jewellery, smooth skin with a soft healthy glow, saturated colours, crisp specular highlights, bright clean summer sunlight from the upper left, a soft turquoise rim light from the right like light off the sea, tack-sharp edges, no blur, no depth of field, high detail. No text, no logos, no outline, no cast shadow, no floor, no frame. On a transparent background, exported as a PNG with transparency. The whole figure from the top of her head to the soles of her sandals is in frame with a clear margin.
```

---

## The 8 new girls: their other poses · attach char-{girl}-idle and sym-a-peach

One prompt for every pose of every new girl. Paste her lines from the first table and the pose's sentences from the second, where the prompt says so.

**Her lines:**

| Girl | Her (paste over {HER}) | Her fruit (paste over {FRUIT}) |
|---|---|---|
| Aurora | `identical face, hazel-green eyes, peach-coral lips, long strawberry-blonde waves with the gold peach-shaped clip, rose-gold hoop earrings, peaches-and-cream skin, the same coral-peach puff-sleeved satin mini dress, rose-gold sandals and anklet, and the same peach bellini in her left hand` | `a ripe golden-and-coral peach` |
| Stella | `identical face, sky-blue eyes, red lips, platinum-blonde vintage waves, gold star earrings, the same oversized white sun hat with its yellow ribbon, the same ruffled sunshine-yellow off-the-shoulder mini dress with its gold belt, gold platform sandals, and the same pineapple cocktail in her left hand` | `a juicy golden pineapple wedge` |
| Rosa | `identical face, warm brown eyes, ruby-red lips, honey-caramel balayage waves with the red rose behind her ear, gold drop earrings, golden tanned skin, the same ruby-red sequin halter mini dress, red sandals and gold bracelets, and the same pomegranate spritz in her left hand` | `half a pomegranate full of glistening ruby seeds` |
| Gemma | `identical face, dark-brown eyes with winged liner, terracotta lips, the long black braid with its tiny gold beads, gold chandelier earrings, warm brown skin, the same embroidered mango-orange satin mini dress, gold sandals and gold bangles, and the same mango cocktail in her left hand` | `a ripe mango` |
| Jade | `identical face, dark almond eyes with winged liner, berry-pink lips, sleek long black hair with blunt bangs, the gold choker with its lime-green gem, porcelain skin, the same lime-green corset-style satin mini dress, white patent sandals, and the same lime mojito in her left hand` | `a glossy lime` |
| Celeste | `identical face, grey-blue eyes, nude-pink lips, the chestnut-brown chin-length bob with its cobalt silk headband, sapphire drop earrings, olive skin, the same cobalt-blue satin wrap mini dress with its slit, silver sandals, and the same blueberry cocktail in her left hand` | `a small bunch of plump blueberries` |
| Luna | `identical face, dark eyes with violet eyeshadow, plum lips, the big natural afro of glossy curls, large gold hoop earrings, deep brown skin, the same long-sleeved violet sequin mini dress, gold sandals and gold arm cuff, and the same violet cocktail in her left hand` | `a ripe purple fig` |
| Perla | `identical face, soft grey eyes, rose-pink lips, rose-gold hair in its sleek high bun, pearl earrings and pearl necklace, fair rosy skin, the same pearl-white cowl-neck satin mini dress with its rose trim, pearl-strapped sandals, and the same lychee martini in her left hand` | `a peeled lychee, glossy and pearl-white` |

**The poses:**

| File | Paste over {POSE} |
|---|---|
| `char-{girl}-win.png` | `she celebrates a win: she raises her glass high in her left hand and pumps her right fist, laughing with delight and giving the viewer a cheeky wink, her hair swinging. Joyful, flirty and fun.` |
| `char-{girl}-kiss.png` | `she blows a kiss toward the left of the image: her right hand is raised in front of her lips with the palm open and facing left, her lips puckered in a kiss, her eyes half-closed and her head tilted, one knee bent playfully. Draw no hearts or effects around her: the game adds them. Romantic, flirtatious and charming.` |
| `char-{girl}-tease.png` | `she can't wait: she holds her glass close in her left hand, raises her right hand near her chin with her fingers crossed, and bites her lower lip in excited anticipation, leaning forward slightly, her eyes on the left of the image. Thrilling, playful and alluring.` |
| `char-{girl}-dance.png` | `she dances with joy: her hips swing to one side and her dress catches the movement, her left arm raised high above her head holding her glass without spilling, her right hand on her hip, her hair flying, laughing with her head tilted back. Exuberant, sexy and celebratory.` |
| `char-{girl}-party.png` | `she starts the party: she raises her glass high in a toast with a lit gold sparkler in it throwing bright sparks, and opens her right arm wide in welcome; she faces the viewer with a slight turn toward the left, with a dazzling, excited smile and lips parted as if calling everyone to the party. Glamorous, thrilling and inviting.` |
| `char-{girl}-waiting.png` | `she waits for the player, teasing: her glass in her left hand rests against her hip, and with her right hand she lifts {FRUIT} to her lips, about to take a slow bite, one eyebrow raised and a teasing look straight at the viewer, as if to say "your move". Playful, provocative and charming.` |

```text
The same woman as in the first attached image: {HER}. New pose: {POSE} Fully dressed, nothing explicit. The full figure from head to toe, three-quarter view turned toward the left, feet at the same height and the head and body the same size in the frame as in the first attached image. Match the finish of the second attached image. Premium AAA slot game art, high-end stylised 3D character render, crisp and clean like a premium mobile-game heroine: glossy satin, polished jewellery, smooth skin with a soft healthy glow, saturated colours, crisp specular highlights, bright clean summer sunlight from the upper left, a soft turquoise rim light from the right, tack-sharp edges, no blur, no depth of field, high detail. No text, no logos, no outline, no cast shadow, no floor, no frame. On a transparent background, exported as a PNG with transparency. The whole figure, including anything she raises, is in frame with a clear margin.
```

For the waiting pose, paste her fruit into {FRUIT} inside the pose's sentences.

---

## The picker cards (square 1:1, transparent) · attach char-{girl}-idle and sym-a-peach

The picker shows each girl's card in a gold frame (`ui-girl-frame`) with her name under it. The game writes the name, so the card has no text. Use the tables: `card-bella` and `card-marina` above, and for the new girls:

| File | Her (card) | Her fruit |
|---|---|---|
| `card-aurora.png` | `her strawberry-blonde waves with the gold peach-shaped clip, rose-gold hoop earrings and the coral-peach puff-sleeved neckline` | `a ripe golden-and-coral peach` |
| `card-stella.png` | `her platinum-blonde vintage waves under the big white sun hat with its yellow ribbon, gold star earrings and the ruffled sunshine-yellow neckline` | `a golden pineapple` |
| `card-rosa.png` | `her honey-caramel balayage waves with the red rose behind her ear, gold drop earrings and the ruby-red sequin halter neckline` | `a ripe pomegranate with a wedge cut out, showing its ruby seeds` |
| `card-gemma.png` | `her long black braid with its tiny gold beads over one shoulder, gold chandelier earrings and the embroidered mango-orange neckline` | `a ripe mango` |
| `card-jade.png` | `her sleek black hair with blunt bangs, the gold choker with its lime-green gem and the lime-green corset-style neckline` | `a glossy lime` |
| `card-celeste.png` | `her chestnut-brown bob with the cobalt silk headband, sapphire drop earrings and the cobalt-blue wrap neckline` | `three plump blueberries` |
| `card-luna.png` | `her big natural afro of glossy curls, large gold hoop earrings and the violet sequin neckline` | `a ripe purple fig` |
| `card-perla.png` | `her rose-gold hair in its sleek high bun, pearl earrings, the pearl necklace and the pearl-white cowl neckline` | `a half-peeled lychee` |

```text
A head-and-shoulders portrait of the woman in the first attached image, for a character card in a slot game: her identical face, {HER}. She faces the viewer with a slight turn of her head, smiling invitingly, as if to say "pick me". The crop shows her head, hair and bare shoulders and ends just below her shoulders. Beside her shoulder floats {FRUIT}, glossy and crisp, in the same style as the second attached image. Square composition, her face in the upper middle. Premium AAA slot game art, high-end stylised 3D character render, crisp and clean like a premium mobile-game heroine: smooth skin with a soft healthy glow, glossy hair, polished jewellery, saturated colours, crisp specular highlights, bright clean summer sunlight from the upper left, a soft turquoise rim light from the right, tack-sharp focus, no blur, high detail. No text, no outline, no frame. On a transparent background, exported as a PNG with transparency.
```

---

## The picker's own art · attach sym-a-peach and ui-frame (round 1)

### ui-girl-frame.png · 3:4 · transparent · each card's frame in the picker
The game puts the girl's card inside it, writes her name on the plate, and lights the frame of the chosen girl.
```text
An ornate portrait frame for a character card in a slot game, seen straight on: a tall rounded-arch frame of glossy white glazed ceramic set with a band of hand-painted Mediterranean majolica tiles (turquoise, lemon yellow and lime green fruit-and-leaf motifs on white), edged with polished gold mouldings, a small glossy hot-pink heart with a gold rim at the top of the arch, and at the bottom a plain, smooth polished-gold nameplate, left blank for a name. The inside of the frame is completely empty and see-through: a clean arch-shaped window filling most of the frame. Symmetrical, straight front view. Match the style and lighting of the attached images. Premium AAA slot game art in a crisp, glossy stylised 3D style: bold clean shapes, saturated colours, crisp specular highlights, tack-sharp edges, no blur, high detail. No text, no cast shadow. On a transparent background, exported as a PNG with transparency. The whole frame is in frame with a clear margin.
```

### ui-girl-button.png · 1:1 · transparent · opens the picker
```text
A round premium game button seen straight on: a domed disc of glossy hot-pink lacquer with a soft inner glow and a polished gold rim with small gold heart studs, and at its centre a bold white glossy heart topped by a small polished gold crown, embossed and softly glowing. Tactile, playful and inviting. Match the style and lighting of the attached images. Premium AAA slot game art in a crisp, glossy stylised 3D style: bold clean shapes, saturated colours, crisp specular highlights, tack-sharp edges, no blur, high detail. No text, no cast shadow. On a transparent background, exported as a PNG with transparency. The whole button is in frame with a clear margin.
```

---

## What I do when the art arrives

**The build script** (`scripts/art/process-tutti-frutti.py`) learns the roster:
- every girl's poses, measured like Bella's and Marina's (feet, centre, the kiss hand);
- which way she faces, so the game mirrors her on the side where she must turn;
- her card and her colour, for the picker;
- `art.json` lists only the girls whose launch poses and card are in.

**The game:**
- **The picker:** a heart button opens a gallery of the 10 cards. The player taps one as the lucky girl and one as her friend. The two swap places if the player picks the same girl for the other side.
- **Loading:** only the two chosen girls' poses load, so 10 girls cost no more than 2.
- **The note:** "Just for fun: your choice never changes the odds."
