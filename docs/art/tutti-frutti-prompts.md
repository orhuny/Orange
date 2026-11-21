# Tutti Frutti: art prompts (round 1)

Audience: whoever generates the art for Tutti Frutti. Every prompt below is complete; copy it as is.

**Name:** Tutti Frutti (code `tutti-frutti`), the user's choice, confirmed on 2026-11-21. Only `txt-logo` carries the name.

**Status (2026-11-21): delivered and in the game.** All 54 files are in `storage/app/art/tutti-frutti/round1/`; `python -I scripts/art/process-tutti-frutti.py` builds them into `public/images/tutti-frutti/` (about 10 minutes). To redo an asset, regenerate it with its prompt below, replace the file there, and rerun the script. A regenerated pose of Marina or Bella keeps its measured points only if it is drawn the same way (`HOSTS` in the script).

**The game:** a **lines** game. Five reels and three rows, 20 fixed lines (the classic shape), and fruit that matches along a line from the left pays. That makes it our first slot that isn't pay-anywhere.

## The look in one paragraph

The game is set in **a glamorous beach club on the Italian Riviera, in high summer**. In the base game it is noon: a white terrace with a turquoise infinity pool above a sparkling bay, pastel villas stacked up the cliffs, hot-magenta bougainvillea, palms, and a clear azure sky. Free spins are **the Neon Night**: the same terrace after dark, with the pool lit from below, neon signs shaped like fruit, party lights strung between the palms, and heart-shaped fireworks over the sea. Two hostesses run the club and flank the reels, each on her own tiled podium:
- **Marina**, a copper-haired siren in turquoise, stands on the left with a gold cocktail shaker.
- **Bella**, a jet-black-haired pin-up in hot pink, stands on the right with the house cocktail.

The symbols are **nine exotic fruits, and only fruit**. The wild is **the kiss**, a heart-shaped enamel locket with a lipstick print, which can carry a multiplier. In free spins every kiss fills **Amore**, a big heart that multiplies every win.

**The style, which is the user's main ask:** crisp and sharp like a top mobile game. That means bold, simple, glossy shapes, juicy saturated colours, a thin clean contour on every fruit, and bright clean sunlight. There is no haze, no bokeh, no depth-of-field blur and no painterly softness, in the symbols and in the backgrounds alike.

## How it differs from Sugar Orchard

Tutti Frutti must not look or feel like Sugar Orchard. Keep these differences in every asset:

| | Sugar Orchard | Tutti Frutti |
|---|---|---|
| How it pays | 6×5, any 8+ of a fruit anywhere, tumbles | 5×3, 20 lines, 3+ of a fruit along a line |
| Place and light | a candy orchard at golden hour, warm haze | a Riviera beach club at noon: turquoise, azure, white, hot pink |
| Free spins | the orchard at night, lanterns and fireflies | the Neon Night: neon fruit signs, a lit pool, heart fireworks |
| Style | painterly, photoreal, soft light | crisp glossy stylised 3D with a clean contour, sharp everywhere |
| Symbols | four candies and five orchard fruits | nine exotic fruits, no candy at all |
| A win | frost crust, then a sugar explosion | the line lights up, and its fruit bounce and burst with juice in their own colour |
| Special symbols | candy stars (multipliers) | kiss lockets (wilds, some with a multiplier) and the house cocktail (scatter) |
| Host | the Queen Bee: brown waves, honey-gold gown, tiara, wings, upper body on the left | two hostesses, full-length on both sides: Marina (copper hair, turquoise) and Bella (jet black, hot pink) |

## How the art becomes the game

**The screen:** the reels sit in the centre in their frame (`ui-frame`), with the logo above them. Marina stands full-length on the left and Bella on the right, each on a podium (`fg-podium`). The game marks the 20 lines on the frame's two side bands. The buy badge (`ui-buy-badge`) sits at the bottom left, and the spin button (`ui-spin`) on the bar.

**A line win:**
1. The game draws each winning line across the reels as a glowing ribbon in its own colour, with a sparkle running along it (`fx-twinkle`).
2. The fruit on the line bounce with a glossy squash and stretch, and each bursts with juice in its own colour behind it (`fx-splash`, tinted per fruit by code). Drops fly and fall with gravity (`fx-droplets`).
3. The win rises in the bar. Marina cheers (`char-marina-cheer`), and on a good win Bella winks (`char-bella-wink`).

**The kiss (wild):**
1. A kiss locket substitutes for every fruit. The game writes WILD on it, and its value when it carries one: 2×, 3× or 5×, each in its own colour (`sym-m-kiss-*`).
2. When a line wins with a multiplier kiss, Bella blows a kiss (`char-bella-kiss`). A trail of tiny hearts flies from her to the locket (`fx-heart-trail`), and the line win bursts in hearts (`fx-heart-burst`) as it multiplies.
3. In free spins every kiss that lands flies into **Amore** (`ui-amore-heart`). The game fills the heart with glowing pink, and Amore multiplies every win until the free spins end.

**Celebrations:**
- **Scatters:** each cocktail that lands flashes a sunburst (`fx-sunburst`). Once two are down, Marina shakes her cocktail shaker (`char-marina-shake`) and the last reels spin longer to a drumroll.
- **Free spins:** both hostesses start the party (`char-marina-party`, `char-bella-party`). The sun sets in a fast time-lapse, the neon signs flicker on, heart fireworks burst over the bay (`fx-firework-heart`), and FREE SPINS lights up in neon.
- **Big wins:** a wave of glittering pink, turquoise and gold light sweeps the screen (`fx-sweep`). Confetti, paper parasols, fruit and gold heart coins rain down (`fx-confetti`). Both hostesses dance (`char-*-dance`), and the screen shakes more with each tier.
- **Idle:** after a minute without a spin, both tease the player to spin again (`char-*-waiting`).
- **The scene is alive:** the sea glitters and the pool ripples. Tropical leaves sway in the lower corners (`fg-tropical`). The hostesses sway gently. At night the neon flickers and fireworks go off now and then. Code draws the glitter, the ripples and the flicker.

**What code does and what art does:** the animation is code. The art supplies the pieces it animates: layered stills, particles and light. Image models can't draw a consistent frame-by-frame sequence, so **never ask for sprite sheets or animation frames**.

## Rules (read before generating)

1. **One prompt per request.** Never ask for several assets at once. If you do, the tool falls back to flat vector art.
2. **Set the style first.**
   - Generate `sym-a-peach` until you love it, then attach it as a style reference to every other symbol, kiss, UI and lettering prompt.
   - Generate `char-bella-idle` until you love her. Attach her to every other Bella pose, and attach her to `char-marina-idle` too, for the finish only.
   - Then generate `char-marina-idle` until you love her, and attach her to every other Marina pose.
   - Each prompt says what to attach. In Midjourney, use `--sref <image url>` for style and `--cref <image url>` for the character.
3. **Four kinds of canvas.** Each prompt ends with one of these:
   - **Transparent** (the default): a real transparent PNG. ChatGPT image can export one. This game is full of pink, magenta and purple (the peach, the dragon fruit, the fig, Bella's dress), so **never key a symbol, a pose or a badge on magenta**.
   - **Magenta:** flat pure `#FF00FF`, only for the frame, which has no pink or purple on it. The script finds the frame's window by its magenta.
   - **Glow:** pure black `#000000`. These are light effects. The game adds them as light, so black disappears and the glow stays soft. Never generate a glow on magenta or transparency.
   - **Liquid:** white liquid on pure black (`fx-splash`, `fx-droplets`). The script turns the white into the liquid's shape and shading, and the game tints it with each fruit's colour, so one splash serves all nine fruits. Keep the liquid pure white: no colour.
   - **Scene:** a full picture.
4. **Crisp and sharp.**
   - Always ask for the largest size the tool offers, as PNG. Never send a JPEG or a screenshot of the image.
   - Backgrounds need at least 2560×1440 (or 1440×2560). If the tool gives less, upscale with a sharp AI upscaler, not a plain resize.
   - If an image comes back soft, hazy or blurred anywhere, regenerate it. A sharp symbol at 1024 px stays sharp when the game shrinks it; a soft one never gets sharper.
5. **Text:** only the `txt-*` assets contain text. Check every letter, and regenerate until the spelling is exact.
6. **Delivery:** put the files, under the names below, in `C:\Git\Orange\storage\app\art\tutti-frutti\round1\`. The build script reads that folder.
   - When unsure, send 2–3 variants (`-a`, `-b`, `-c`). I pick the best one.
   - Batches are fine. The symbols, the two hostesses and the backgrounds first already change the whole look.
7. **Tools:**
   - **ChatGPT image** is best for transparency, text, layouts and the characters. Use it for `sym-*`, `char-*`, `fg-podium`, `ui-*`, `txt-*` and `fx-confetti`.
   - **Midjourney** is richest for backgrounds and light effects. Add `--ar 16:9`, `--ar 9:16`, `--ar 2:1`, `--ar 21:9` or `--ar 1:1`, and move each prompt's "Avoid:" list into `--no`.
8. **The hostesses are sexy, not explicit.** They are glamorous summer pin-ups: figure-hugging dresses, bare shoulders, high hems, teasing looks, winks and blown kisses. They are always fully dressed. Never ask for lingerie, swimwear, nudity, see-through fabric or explicit poses. Image tools refuse them, and regulated operators would reject the game; UK advertising rules, for one, forbid linking gambling to seduction or sexual attractiveness, and operators use the lobby tile in their marketing.
9. **Keep it ours.** This game must not look like Sugar Orchard or like any other slot. Never generate any of these:
   - **Sugar Orchard's world:** candy, sweets, toffee, chocolate, caramel, sugar frost, gumballs, baskets, bows, orchards, golden-hour light, bees, honey, wings, tiaras, evening gowns.
   - **Sugar Orchard's fruit:** oranges, cherries, strawberries, lemons, kiwis.
   - **Other fruit slots' sets:** bananas, grapes, watermelons, plums, apples, lollipops, rainbows, candy hearts, colourful bombs, red-and-white candy-cane stripes, pastel cotton-candy clouds, lucky sevens, bells, bars, dollar signs, crowns, flames.
   - **For the hostesses:** a hat or turban piled with fruit (that is a famous real performer's signature look), heart-shaped sunglasses, or any resemblance to a real person, model or film character.
   - **Names:** never put the name of a real person, film, song, brand or other game in any prompt.

### Order

1. `sym-a-peach`: the style anchor.
2. The other symbols, `sym-b` … `sym-i`, then `sym-s-cocktail` and the four `sym-m-kiss-*`.
3. `char-bella-idle`, then `char-marina-idle`: the two character anchors. Then their other poses and `fg-podium`.
4. `bg-day-landscape`, then the other three backgrounds and `fg-tropical`.
5. `ui-frame`, `ui-buy-badge`, `ui-spin`, `ui-plaque`, `ui-amore-heart`.
6. `txt-logo`, then the banners.
7. The `fx-*` effects and `fx-confetti`.
8. `key-thumbnail`.

---

## Symbols (square 1:1, transparent)

The reels show the symbols at about 90–180 px. Each one needs a bold, instantly readable silhouette and its own dominant colour.

| Symbol | Rank | Dominant colour |
|---|---|---|
| A, the peach | top | golden yellow blushing to coral red |
| B, the pineapple | high | golden yellow and green |
| C, the dragon fruit | high | hot magenta and lime green |
| D, the pomegranate | high | deep ruby red |
| E, the mango | low | tangerine orange |
| F, the lime | low | bright lime green |
| G, the blueberries | low | cobalt blue |
| H, the fig | low | deep violet |
| I, the lychee | low | pearl white and rose |

The four high fruits are the lavish ones: a sparkle on the highlight, a dew drop, a leaf. The five low fruits are simpler and bolder.

Every symbol must be **solid and opaque**, with crisp edges. Every symbol prompt ends with the same style block, so the set matches.

### sym-a-peach.png · top symbol · generate first
```text
The most perfect, irresistible peach: a plump, round peach with a soft heart-like shape and a gentle crease down its middle, its velvety skin blushing from warm golden yellow into deep coral red, a short brown stem with two glossy green leaves at the top, two clear dew drops on its cheek and one bright star-shaped glint on its highlight. Seen from the front, tilted slightly toward the viewer, juicy and tempting: the most valuable symbol of the set. Fills about 80% of the frame. Premium AAA slot game art in a crisp, glossy stylised 3D style like a top mobile game: bold simple shapes, smooth glossy surfaces, juicy saturated colours, a thin clean contour line a shade darker than the fruit around its silhouette, bright crisp white specular highlights, bright clean summer sunlight from the upper left, a soft turquoise bounce light from the right like light off the sea, tack-sharp edges everywhere, no blur, no depth of field, no film grain, no haze, high detail. No text, no cast shadow, no frame. On a transparent background, exported as a PNG with transparency. The whole object is in frame with a clear margin.
```

### sym-b-pineapple.png · attach sym-a
```text
A golden pineapple standing upright, tilted slightly: its skin is a crisp pattern of glossy golden-yellow diamond scales, each with an amber edge and its own little highlight, and it is crowned with a tall, bold fan of spiky glossy green leaves. The colours are golden yellow and green. Bold, sunny and generous. Match the style, lighting and finish of the attached image. Fills about 80% of the frame height. Premium AAA slot game art in a crisp, glossy stylised 3D style like a top mobile game: bold simple shapes, smooth glossy surfaces, juicy saturated colours, a thin clean contour line a shade darker than the fruit around its silhouette, bright crisp white specular highlights, bright clean summer sunlight from the upper left, a soft turquoise bounce light from the right like light off the sea, tack-sharp edges everywhere, no blur, no depth of field, no film grain, no haze, high detail. No text, no cast shadow, no frame. On a transparent background, exported as a PNG with transparency. The whole object is in frame with a clear margin.
```

### sym-c-dragonfruit.png · attach sym-a
```text
A whole dragon fruit standing upright at a slight angle: a plump oval fruit of glossy hot-magenta skin covered in curling, flame-shaped scales whose tips turn bright lime green, with a small sparkle on its highlight. The colours are hot magenta and lime green. Exotic, vivid and striking. Match the style, lighting and finish of the attached image. Fills about 80% of the frame. Premium AAA slot game art in a crisp, glossy stylised 3D style like a top mobile game: bold simple shapes, smooth glossy surfaces, juicy saturated colours, a thin clean contour line a shade darker than the fruit around its silhouette, bright crisp white specular highlights, bright clean summer sunlight from the upper left, a soft turquoise bounce light from the right like light off the sea, tack-sharp edges everywhere, no blur, no depth of field, no film grain, no haze, high detail. No text, no cast shadow, no frame. On a transparent background, exported as a PNG with transparency. The whole object is in frame with a clear margin.
```

### sym-d-pomegranate.png · attach sym-a
```text
A ripe pomegranate: round and glossy, deep ruby red with a darker crimson blush, crowned with a small star-shaped calyx on top. A neat wedge is cut out of its front, showing rows of glistening translucent ruby-red seeds packed inside, each with its own tiny highlight. The colour is deep ruby red. Rich, precious and sensual. Match the style, lighting and finish of the attached image. Fills about 80% of the frame. Premium AAA slot game art in a crisp, glossy stylised 3D style like a top mobile game: bold simple shapes, smooth glossy surfaces, juicy saturated colours, a thin clean contour line a shade darker than the fruit around its silhouette, bright crisp white specular highlights, bright clean summer sunlight from the upper left, a soft turquoise bounce light from the right like light off the sea, tack-sharp edges everywhere, no blur, no depth of field, no film grain, no haze, high detail. No text, no cast shadow, no frame. On a transparent background, exported as a PNG with transparency. The whole object is in frame with a clear margin.
```

### sym-e-mango.png · attach sym-a
```text
A ripe mango: a plump, smooth kidney-shaped fruit lying at a slight angle, its glossy skin glowing from sunny yellow through tangerine orange to a red cheek, with one long glossy green leaf at its stem. The colour is tangerine orange. Simple, bold and juicy. Match the style, lighting and finish of the attached image. Fills about 80% of the frame. Premium AAA slot game art in a crisp, glossy stylised 3D style like a top mobile game: bold simple shapes, smooth glossy surfaces, juicy saturated colours, a thin clean contour line a shade darker than the fruit around its silhouette, bright crisp white specular highlights, bright clean summer sunlight from the upper left, a soft turquoise bounce light from the right like light off the sea, tack-sharp edges everywhere, no blur, no depth of field, no film grain, no haze, high detail. No text, no cast shadow, no frame. On a transparent background, exported as a PNG with transparency. The whole object is in frame with a clear margin.
```

### sym-f-lime.png · attach sym-a
```text
A plump, glossy lime, slightly oval, bright lime green with a fine dimpled peel, a tiny nub at each end and one small glossy leaf at the top. The colour is bright lime green. Simple, bold and zesty. Match the style, lighting and finish of the attached image. Fills about 80% of the frame. Premium AAA slot game art in a crisp, glossy stylised 3D style like a top mobile game: bold simple shapes, smooth glossy surfaces, juicy saturated colours, a thin clean contour line a shade darker than the fruit around its silhouette, bright crisp white specular highlights, bright clean summer sunlight from the upper left, a soft turquoise bounce light from the right like light off the sea, tack-sharp edges everywhere, no blur, no depth of field, no film grain, no haze, high detail. No text, no cast shadow, no frame. On a transparent background, exported as a PNG with transparency. The whole object is in frame with a clear margin.
```

### sym-g-blueberries.png · attach sym-a
```text
Three plump blueberries in a tight triangle cluster, two in front and one behind, glossy deep cobalt blue with lighter blue highlights, each with its little star-shaped crown, and one small glossy green leaf tucked behind them. The colour is cobalt blue. Round, compact and bold. Match the style, lighting and finish of the attached image. Fills about 80% of the frame. Premium AAA slot game art in a crisp, glossy stylised 3D style like a top mobile game: bold simple shapes, smooth glossy surfaces, juicy saturated colours, a thin clean contour line a shade darker than the fruit around its silhouette, bright crisp white specular highlights, bright clean summer sunlight from the upper left, a soft turquoise bounce light from the right like light off the sea, tack-sharp edges everywhere, no blur, no depth of field, no film grain, no haze, high detail. No text, no cast shadow, no frame. On a transparent background, exported as a PNG with transparency. The whole object is in frame with a clear margin.
```

### sym-h-fig.png · attach sym-a
```text
A ripe fig: a plump teardrop-shaped fruit standing upright, its glossy skin deep violet purple fading to a touch of green at the short curved stem, with faint lighter streaks down its sides. The colour is deep violet. Simple, bold and velvety-rich. Match the style, lighting and finish of the attached image. Fills about 80% of the frame. Premium AAA slot game art in a crisp, glossy stylised 3D style like a top mobile game: bold simple shapes, smooth glossy surfaces, juicy saturated colours, a thin clean contour line a shade darker than the fruit around its silhouette, bright crisp white specular highlights, bright clean summer sunlight from the upper left, a soft turquoise bounce light from the right like light off the sea, tack-sharp edges everywhere, no blur, no depth of field, no film grain, no haze, high detail. No text, no cast shadow, no frame. On a transparent background, exported as a PNG with transparency. The whole object is in frame with a clear margin.
```

### sym-i-lychee.png · attach sym-a
```text
A lychee, half peeled: its bumpy rose-red shell is opened at the top like petals, revealing the round, glossy, translucent pearl-white fruit inside, with a short twig and one small green leaf. The colours are pearl white and rose red. Round, compact and bold. Match the style, lighting and finish of the attached image. Fills about 80% of the frame. Premium AAA slot game art in a crisp, glossy stylised 3D style like a top mobile game: bold simple shapes, smooth glossy surfaces, juicy saturated colours, a thin clean contour line a shade darker than the fruit around its silhouette, bright crisp white specular highlights, bright clean summer sunlight from the upper left, a soft turquoise bounce light from the right like light off the sea, tack-sharp edges everywhere, no blur, no depth of field, no film grain, no haze, high detail. No text, no cast shadow, no frame. On a transparent background, exported as a PNG with transparency. The whole object is in frame with a clear margin.
```

### sym-s-cocktail.png · scatter · attach sym-a
The page adds the word SCATTER over it as text, so the bottom fifth should stay quiet.
```text
The scatter symbol: the house cocktail of a glamorous beach club. A wide, elegant coupe glass with a polished gold stem and a plain polished gold foot, brimming with a layered tropical cocktail in three bright bands (hot pink at the bottom, tangerine in the middle, sunny yellow on top), frosted with tiny bubbles. It is garnished with a wedge of pineapple and a lime wheel on the rim, a small pink paper cocktail parasol, and a lit gold sparkler throwing tiny bright stars. A glossy red lipstick kiss print sits on the rim of the glass. Clearly the most special and festive symbol: bigger, brighter and more radiant than any other. Straight front view; the lowest fifth is the plain gold foot. Match the style, lighting and finish of the attached image. Fills about 85% of the frame. Premium AAA slot game art in a crisp, glossy stylised 3D style like a top mobile game: bold simple shapes, smooth glossy surfaces, juicy saturated colours, a thin clean contour line around the silhouette, bright crisp white specular highlights, bright clean summer sunlight from the upper left, a soft turquoise bounce light from the right like light off the sea, tack-sharp edges everywhere, no blur, no depth of field, no film grain, no haze, high detail. No text, no cast shadow, no frame. On a transparent background, exported as a PNG with transparency. The whole object is in frame with a clear margin.
```

### Kiss lockets, the wilds (4 colours) · attach sym-a

The game writes WILD across the bottom of the locket and, on a multiplier kiss, its value in the middle. So the face must be big and plain. There is one prompt per colour; only the colour sentence changes.

| File | Use | Colour sentence |
|---|---|---|
| `sym-m-kiss-pink.png` | the plain wild | `glossy hot-pink enamel` |
| `sym-m-kiss-aqua.png` | the 2× wild | `glossy turquoise enamel` |
| `sym-m-kiss-lime.png` | the 3× wild | `glossy lime-green enamel` |
| `sym-m-kiss-gold.png` | the 5× wild | `mirror-polished gold, its rim set with a row of tiny sparkling white diamonds, the most dazzling and rare of the set` |

```text
A wild symbol: a plump, heart-shaped locket of glossy hot-pink enamel with a smooth, polished gold rim and a small gold ring at its top, and a small glossy red lipstick kiss print on its upper left lobe. The heart's face is large, smooth and plain in its middle and lower part, where a word and a number will be printed later: no word, no number, no text. It is a piece of jewellery, not a sweet. Bold, round and compact. Straight front view, tilted very slightly. Match the style, lighting and finish of the attached image. Fills about 80% of the frame. Premium AAA slot game art in a crisp, glossy stylised 3D style like a top mobile game: bold simple shapes, smooth glossy surfaces, juicy saturated colours, a thin clean contour line around the silhouette, bright crisp white specular highlights, bright clean summer sunlight from the upper left, a soft turquoise bounce light from the right, tack-sharp edges everywhere, no blur, no depth of field, no film grain, no haze, high detail. No cast shadow, no frame. On a transparent background, exported as a PNG with transparency. The whole object is in frame with a clear margin.
```

---

## The two hostesses (2:3, transparent)

They stand **full-length on both sides of the reels**, each turned toward them, on a podium (`fg-podium`):
- **Marina** on the left, turned toward the right of the image;
- **Bella** on the right, turned toward the left of the image.

On a phone, the game shows both from the waist up over the frame's top corners, so their faces, hair and hands must be crisp.

They must look **clearly different from each other**:

| | Marina (left) | Bella (right) |
|---|---|---|
| Hair | long, voluminous copper-red waves over one shoulder | sleek jet-black high ponytail with a hot-pink hibiscus |
| Skin and eyes | fair, lightly sun-kissed, freckles; sapphire-blue eyes | sun-kissed bronze; emerald-green eyes |
| Dress | turquoise satin one-shoulder mini dress, ruched | hot-pink satin halter mini dress with a side slit and a gold belt |
| Prop | a polished gold cocktail shaker | the house cocktail in a gold-stemmed coupe |
| Character | the sultry, mysterious siren | the playful, flirty pin-up |

**How to generate:** use ChatGPT image, one request per image, on a transparent background.
- Generate `char-bella-idle` first. Then generate `char-marina-idle` with Bella attached for the rendering finish only: Marina is a completely different woman.
- Attach each woman's idle image to every one of her other poses, so her face, hair, dress and jewellery stay identical. Regenerate a pose until it's clearly the same woman.
- Keep both women at the same height and size, with their feet at the same height in every pose. The game stands each pose on the same podium.

### char-bella-idle.png · Bella's anchor · attach sym-a
```text
An original glamorous hostess of a Riviera beach club, a host of a premium slot game: a stunning, confident adult woman in her late twenties with a curvy hourglass figure and sun-kissed bronze skin. She has sleek, glossy jet-black hair in a high swinging ponytail, with a big hot-pink hibiscus flower tucked behind one ear; large emerald-green eyes with long lashes and a sharp winged liner; glossy coral-red lips; small gold hoop earrings; and oversized white cat-eye sunglasses pushed up on top of her head. She wears a figure-hugging halter sundress of glossy hot-pink satin with a sweetheart neckline, bare shoulders and an open back, cinched at the waist by a thin polished gold belt; the skirt ends at mid-thigh with a flirty high slit on one side. Strappy gold high-heeled sandals, a stack of thin gold bangles on one wrist. In her left hand she holds a wide coupe glass with a polished gold stem, filled with a layered tropical cocktail in hot pink, tangerine and yellow, garnished with a pineapple wedge, a lime wheel and a small pink paper parasol. Pose: the full figure from head to toe, standing with her weight on one hip in a confident, alluring summer pin-up pose, three-quarter view with her body turned toward the left of the image; her right hand lifted to touch her ponytail; a teasing, flirtatious half-smile, looking at the viewer from under her lashes. Sexy, playful and glamorous, tasteful and fully dressed, nothing explicit. Original design, not resembling any real person, model or existing character. Match the finish of the attached image. The figure fills about 92% of the frame height. Premium AAA slot game art, high-end stylised 3D character render, crisp and clean like a premium mobile-game heroine: glossy satin, polished gold jewellery, smooth skin with a soft healthy glow, saturated colours, crisp specular highlights, bright clean summer sunlight from the upper left, a soft turquoise rim light from the right like light off the sea, tack-sharp edges, no blur, no depth of field, high detail. No text, no outline, no cast shadow, no floor, no frame. On a transparent background, exported as a PNG with transparency. The whole figure from the top of her ponytail to the soles of her sandals is in frame with a clear margin.
```

### char-marina-idle.png · Marina's anchor · attach char-bella-idle and sym-a
```text
A second original glamorous hostess of the same Riviera beach club, a host of a premium slot game, rendered in exactly the same style and finish as the woman in the first attached image but a completely different woman: a stunning, sultry adult woman around thirty with a curvy hourglass figure and fair, lightly sun-kissed skin with a dusting of freckles across her nose and shoulders. She has long, voluminous copper-red hair in loose glossy waves swept over one shoulder, large sapphire-blue eyes with long lashes and a soft gold shimmer on the lids, glossy berry-red lips, long dangling gold earrings set with small turquoise stones, and a delicate gold anklet. She wears a figure-hugging one-shoulder mini dress of glossy turquoise satin, softly ruched across the body, the hem ending high on the thigh, with white high-heeled sandals with gold buckles and a wide gold cuff on one wrist. In her right hand she holds a polished gold cocktail shaker resting on her shoulder. Pose: the full figure from head to toe, standing with her weight on one hip and one knee bent in a confident, alluring summer pin-up pose, three-quarter view with her body turned toward the right of the image; her left hand on her hip; her chin lowered, a slow, sultry, mysterious smile, looking at the viewer through her lashes. Sexy, mysterious and glamorous, tasteful and fully dressed, nothing explicit. Original design, not resembling any real person, model or existing character, and clearly not the woman in the first attached image. Match the finish of the second attached image. The figure fills about 92% of the frame height, the same size as the woman in the first attached image. Premium AAA slot game art, high-end stylised 3D character render, crisp and clean like a premium mobile-game heroine: glossy satin, polished gold jewellery, smooth skin with a soft healthy glow, saturated colours, crisp specular highlights, bright clean summer sunlight from the upper left, a soft turquoise rim light from the right like light off the sea, tack-sharp edges, no blur, no depth of field, high detail. No text, no outline, no cast shadow, no floor, no frame. On a transparent background, exported as a PNG with transparency. The whole figure from the top of her hair to the soles of her sandals is in frame with a clear margin.
```

### Bella's poses · attach char-bella-idle and sym-a

Each pose starts with the same description of her, so she stays the same woman. Only the "New pose" sentences differ.

| File | When | New pose |
|---|---|---|
| `char-bella-wink.png` | a good win | `New pose: she stretches her right arm toward the left of the image and flicks her index finger at it playfully, as if sending a spark there, and gives the viewer a cheeky wink with a bright, delighted smile; her hip is popped to one side and her ponytail swings. Flirty, confident and fun.` |
| `char-bella-kiss.png` | a multiplier kiss pays | `New pose: she blows a kiss toward the left of the image: her right hand is raised in front of her lips with the palm open and facing left, her lips puckered in a kiss, her eyes half-closed and her head tilted, one knee bent playfully. Draw no hearts or effects around her: the game adds them. Romantic, flirtatious and charming.` |
| `char-bella-dance.png` | big wins | `New pose: she dances with joy: her hips swing to one side and the skirt of her dress flares, her left arm raised high above her head holding the cocktail glass up without spilling, her right hand on her hip, her ponytail flying, laughing with her head tilted back. Exuberant, sexy and celebratory.` |
| `char-bella-party.png` | free spins | `New pose: she starts the party: she raises her cocktail glass high in a toast with her left hand, a lit gold sparkler in the glass throwing bright sparks, and opens her right arm wide in welcome; she faces the viewer with a slight turn toward the left, with a dazzling, excited smile and lips parted as if calling everyone to the party. Glamorous, thrilling and inviting.` |
| `char-bella-waiting.png` | a minute without a spin | `New pose: she waits for the player, teasing: her left hand rests on her hip, and with her right hand she lifts a ripe golden-and-coral peach like the one in the second attached image to her lips, about to take a slow bite, one eyebrow raised and a teasing look straight at the viewer, as if to say "your move". Playful, provocative and charming.` |

```text
The same woman as in the first attached image: identical face, emerald-green eyes, coral-red lips, sleek jet-black high ponytail with the hot-pink hibiscus, white cat-eye sunglasses on her head, gold hoop earrings, sun-kissed bronze skin, the same hot-pink satin halter mini dress with its gold belt and side slit, gold sandals and gold bangles, and the same cocktail. New pose: she stretches her right arm toward the left of the image and flicks her index finger at it playfully, as if sending a spark there, and gives the viewer a cheeky wink with a bright, delighted smile; her hip is popped to one side and her ponytail swings. Flirty, confident and fun. Fully dressed, nothing explicit. The full figure from head to toe, three-quarter view turned toward the left, feet at the same height and the head and body the same size in the frame as in the first attached image. Match the finish of the second attached image. Premium AAA slot game art, high-end stylised 3D character render, crisp and clean like a premium mobile-game heroine: glossy satin, polished gold jewellery, smooth skin with a soft healthy glow, saturated colours, crisp specular highlights, bright clean summer sunlight from the upper left, a soft turquoise rim light from the right, tack-sharp edges, no blur, no depth of field, high detail. No text, no outline, no cast shadow, no floor, no frame. On a transparent background, exported as a PNG with transparency. The whole figure, including anything she raises, is in frame with a clear margin.
```

### Marina's poses · attach char-marina-idle and sym-a

The same pattern as Bella's: only the "New pose" sentences differ.

| File | When | New pose |
|---|---|---|
| `char-marina-cheer.png` | a line win | `New pose: she cheers: she raises the gold cocktail shaker high in her right hand like a trophy, her left hand pressed to her chest, laughing with delight and looking at the viewer, her copper hair swinging. Joyful, warm and sexy.` |
| `char-marina-shake.png` | the scatter tease | `New pose: she shakes the gold cocktail shaker with both hands beside her shoulder, hips swaying to the rhythm, biting her lower lip in excited anticipation, her eyes on the right of the image. Thrilling, playful and alluring.` |
| `char-marina-dance.png` | big wins | `New pose: she dances with joy: her hips sway, one arm raised high above her head with the shaker, the other sweeping her copper hair up off her neck, her head tilted back with a delighted laugh. Exuberant, sexy and celebratory.` |
| `char-marina-party.png` | free spins | `New pose: she starts the party: she flings a handful of hot-pink hibiscus petals and confetti up into the air with her left hand, the shaker raised in her right, facing the viewer with a slight turn toward the right, with a dazzling, excited smile. Glamorous, thrilling and inviting.` |
| `char-marina-waiting.png` | a minute without a spin | `New pose: she waits for the player: she leans back with the shaker resting on her shoulder, slowly twirling a lock of her copper hair around one finger, giving the viewer a smouldering look through her lashes with a knowing half-smile, as if daring them to spin. Sultry, teasing and charming.` |

```text
The same woman as in the first attached image: identical face, sapphire-blue eyes, freckles, berry-red lips, long copper-red waves over one shoulder, dangling gold-and-turquoise earrings, the same turquoise satin one-shoulder ruched mini dress, white sandals with gold buckles, gold cuff and gold anklet, and the same gold cocktail shaker. New pose: she cheers: she raises the gold cocktail shaker high in her right hand like a trophy, her left hand pressed to her chest, laughing with delight and looking at the viewer, her copper hair swinging. Joyful, warm and sexy. Fully dressed, nothing explicit. The full figure from head to toe, three-quarter view turned toward the right, feet at the same height and the head and body the same size in the frame as in the first attached image. Match the finish of the second attached image. Premium AAA slot game art, high-end stylised 3D character render, crisp and clean like a premium mobile-game heroine: glossy satin, polished gold jewellery, smooth skin with a soft healthy glow, saturated colours, crisp specular highlights, bright clean summer sunlight from the upper left, a soft turquoise rim light from the right, tack-sharp edges, no blur, no depth of field, high detail. No text, no outline, no cast shadow, no floor, no frame. On a transparent background, exported as a PNG with transparency. The whole figure, including anything she raises, is in frame with a clear margin.
```

### fg-podium.png · 3:2 · transparent · both hostesses stand on it · attach char-bella-idle and sym-a
The game uses it under both women, mirrored for Marina.
```text
A round, low podium for a glamorous hostess to stand on, seen from the front and slightly above: a short, wide drum of glossy white glazed ceramic wrapped in a band of hand-painted Mediterranean majolica tiles (turquoise, lemon yellow and lime green fruit-and-leaf motifs on white), with a polished gold rim at its top and bottom edges and a flat, smooth, empty top. At its foot on both sides, a few glossy tropical leaves and two hot-pink hibiscus flowers. Wide, solid and stable, empty on top. Match the colours of the woman in the first attached image and the finish of the second. Premium AAA slot game art in a crisp, glossy stylised 3D style: bold clean shapes, saturated colours, crisp specular highlights, bright clean summer sunlight from the upper left, tack-sharp edges, no blur, high detail. No text, no figure, no cast shadow, no frame. On a transparent background, exported as a PNG with transparency. The whole podium is in frame with a clear margin.
```

---

## Backgrounds (scene)

**The layout:**
- **Landscape:** the logo sits at the top centre. Below it are the reels in their frame, 5 wide by 3 high. Marina stands full-length on the left and Bella on the right. The bar runs along the bottom.
- **Portrait:** the logo is at the top, then the reels, with each woman's upper body rising over one of the frame's top corners. The buttons are at the bottom.

So keep **the reel area calm**: open sky and sea in clean, smooth gradients. Keep the left and right quarters, where the women stand, fairly calm too, and spend the detail on the outer edges. Calm still means crisp: clean shapes and smooth colour, never haze or blur. Code adds the glitter on the sea, the ripples in the pool, the neon flicker and the fireworks, so leave room for them.

### bg-day-landscape.png · 16:9 · base game · generate first
```text
A glamorous beach club on the Italian Riviera at noon in high summer. A bright white stone terrace runs along the bottom with a long turquoise infinity pool whose far edge seems to spill into a sparkling turquoise-and-cobalt bay. On the cliffs to the far left and far right, pastel villas in coral, peach, lemon yellow and white stack up the hillside with little balconies and green shutters; cascades of hot-magenta bougainvillea pour over the walls; tall palm trees lean in from both outer edges; crisp white sun loungers and round parasols in turquoise and coral line the terrace's outer ends; a white yacht sits far out on the sea; a clear azure sky with a few crisp, bright white clouds and a hot, bright sun in the upper left. Composition for a game screen: the reels cover the centre (a wide band about 55% of the width, from 20% to 84% of the height), so that area is open sea and sky in clean, smooth, calm gradients with little detail; the top centre is calm sky for the logo; the left quarter and the right quarter, where two women will stand, are calm open sea, sky and terrace; the richest detail is around the outer edges and corners. Bright, sunny, glamorous and joyful. Crisp and sharp everywhere: clean shapes, high clarity, no haze, no fog, no bokeh, no depth-of-field blur, no film grain, no painterly brush strokes. Premium AAA slot game background, crisp stylised 3D illustration with clean shapes and juicy saturated colours, like a top mobile game. No text, no logo, no signs with letters, no people, no characters, no UI. Avoid: candy, sweets, lollipops, rainbows, orchards, fruit trees, golden-hour light, sunset, haze, bokeh, red-and-white stripes, cotton-candy clouds.
```

### bg-day-portrait.png · 9:16 · attach bg-day-landscape
```text
The same Riviera beach club at noon as in the attached image, recomposed for a tall phone screen. The reels sit in the middle (about 94% of the width, from 26% to 58% of the height), so that area stays open sea and sky in clean, smooth, calm gradients. The pastel villas with their bougainvillea, and the palms, frame the outer sides; the top 12% is calm azure sky, where the logo goes; the band just above the reels stays calm, because two women's heads and shoulders rise there at the left and right corners; the bottom 35% is the white terrace and the turquoise pool, simple and slightly darker in tone, where the buttons go. Same light, palette and style as the attached image. Crisp and sharp everywhere: clean shapes, high clarity, no haze, no fog, no bokeh, no depth-of-field blur, no film grain. Premium AAA slot game background, crisp stylised 3D illustration with clean shapes and juicy saturated colours, like a top mobile game. No text, no logo, no signs with letters, no people, no characters, no UI. Avoid: candy, sweets, lollipops, rainbows, orchards, fruit trees, golden-hour light, sunset, haze, bokeh, red-and-white stripes, cotton-candy clouds.
```

### bg-night-landscape.png · 16:9 · free spins, the Neon Night · attach bg-day-landscape
```text
The same beach club as in the attached image, with the same composition, now at night during a glamorous party for the free spins. A deep violet-blue night sky with a big, bright full moon over the bay and its silver path on the sea. The infinity pool glows electric turquoise from underwater lights. Glowing neon signs shaped like fruit hang on the villa walls at the far left and far right: a hot-pink peach, a lime-green pineapple, a turquoise palm tree and a pink heart, made of neon tubes only, with no letters or words. Strings of warm round party lights loop between the palms; the villa windows glow warm gold; the bougainvillea glows magenta in the neon light; two heart-shaped fireworks burst in hot pink and gold high over the sea and are mirrored in the water. Electric, glamorous and exciting, with rich contrast. The centre stays calm and dark for the reels, the top centre calm for the logo, the left and right quarters calm behind where two women will stand, the outer edges rich. Crisp and sharp everywhere: clean shapes, high clarity, no haze, no fog, no bokeh, no depth-of-field blur, no film grain. Premium AAA slot game background, crisp stylised 3D illustration with clean shapes and juicy saturated colours, like a top mobile game. No text, no logo, no letters in the neon, no people, no characters, no UI. Avoid: candy, sweets, lanterns, fireflies, orchards, fruit trees, haze, bokeh, red-and-white stripes, horror.
```

### bg-night-portrait.png · 9:16 · attach bg-day-portrait and bg-night-landscape
```text
The Neon Night beach club of the second attached image, recomposed exactly like the tall phone layout of the first attached image: the reels in the middle (about 94% of the width, from 26% to 58% of the height) stay calm and dark; the moon sits high in the centre above the logo area; the neon fruit signs, the party lights and the villas frame the outer sides; the band just above the reels stays calm, because two women's heads and shoulders rise there at the left and right corners; the top 12% is calm night sky, where the logo goes; the bottom 35% is the terrace and the glowing pool, simple and darker, where the buttons go. Crisp and sharp everywhere: clean shapes, high clarity, no haze, no fog, no bokeh, no depth-of-field blur, no film grain. Premium AAA slot game background, crisp stylised 3D illustration with clean shapes and juicy saturated colours, like a top mobile game. No text, no logo, no letters in the neon, no people, no characters, no UI. Avoid: candy, sweets, lanterns, fireflies, orchards, fruit trees, haze, bokeh, red-and-white stripes, horror.
```

### fg-tropical.png · 1:1 · transparent · attach bg-day-landscape
It sits in the lower-left corner in front of everything (mirrored at the right) and sways gently. In the Neon Night the game darkens it and tints it with neon light.
```text
A lush cluster of glossy tropical foliage entering from the lower-left corner of the image: big glossy monstera leaves and palm fronds in fresh greens, three hot-pink hibiscus flowers with yellow stamens, and tucked among the leaves the crown of a pineapple and a glossy lime, as in the sunny beach club of the attached image. It enters from the left and bottom edges (cut off by them on purpose) and ends in open space toward the upper right with leafy tips. Premium AAA slot game art in a crisp, glossy stylised 3D style: bold clean shapes, saturated colours, crisp specular highlights, bright clean summer sunlight from the upper left, tack-sharp edges, no blur, high detail. No text, no cast shadow. On a transparent background, exported as a PNG with transparency.
```

---

## Frame and buttons · attach sym-a

The frame, the buy badge and the message plaque are blank on purpose. The game writes every word and number on them, so they stay sharp at any size and in any language.

The materials are **glossy white glazed ceramic, hand-painted Mediterranean majolica tiles, glossy coloured lacquer and polished gold**.

### ui-frame.png · 16:9 · magenta · ChatGPT recommended
The game marks the 20 lines on the two side bands.
```text
An ornate premium frame around a slot game's reel window, in the style of a glamorous Riviera beach club: a frame of glossy white glazed ceramic set with a band of hand-painted Mediterranean majolica tiles (turquoise, lemon yellow and lime green fruit-and-leaf motifs on white), edged inside and out with thin polished gold mouldings. The left and right sides of the frame are a little wider than the top and bottom, and each holds a plain, smooth vertical band of polished gold running its full height, where small markers will be placed. On each of the four corners sits a small cluster of two glossy tropical leaves, a glossy lime and the green crown of a pineapple. The middle of the top edge is a plain, straight gold ledge with no crest, because the game's logo will sit there. The frame is symmetrical left to right. The inner opening is a clean, sharp-cornered rectangle with proportions of exactly 5 wide to 3 high, filled with flat pure magenta (#FF00FF); everything outside the frame is flat pure magenta too. Straight front view, no perspective. Match the style and lighting of the attached image. Premium AAA slot game art in a crisp, glossy stylised 3D style: bold clean shapes, saturated colours, crisp specular highlights, tack-sharp edges, no blur, high detail. No text, no numbers, no cast shadow. Nothing on the frame is pink, purple, magenta or see-through.
```

### ui-buy-badge.png · 1:1 · transparent · the feature buy
The game writes "BUY FREE SPINS" and the price on it.
```text
A round feature badge for a slot game's menu, seen straight on: a domed disc of glossy hot-pink lacquer with a soft inner glow, framed by a raised polished gold ring with a thin band of white-and-turquoise majolica tile inside it, and crowned at its top by a small cocktail coupe with a gold stem, a pink paper parasol and a tiny sparkler. Its face is plain and empty, left blank for text. Symmetrical, straight front view. Match the style and lighting of the attached image. Premium AAA slot game art in a crisp, glossy stylised 3D style: bold clean shapes, saturated colours, crisp specular highlights, tack-sharp edges, no blur, high detail. No text, no cast shadow. On a transparent background, exported as a PNG with transparency. The whole badge is in frame with a clear margin.
```

### ui-spin.png · 1:1 · transparent
```text
A round premium spin button seen straight on, made like a giant glossy slice of pink grapefruit: juicy coral-pink segments radiating from the centre under a smooth, clear glossy dome, a creamy white ring of pith, a thin glossy yellow-pink rind, and an outer ring of polished gold with eight small gold heart studs. At its centre, a bold white circular double-arrow spin icon, embossed and glossy. Juicy, tactile and irresistible to press. Match the style and lighting of the attached image. Premium AAA slot game art in a crisp, glossy stylised 3D style: bold clean shapes, saturated colours, crisp specular highlights, tack-sharp edges, no blur, high detail. No text, no cast shadow. On a transparent background, exported as a PNG with transparency. The whole button is in frame with a clear margin.
```

### ui-plaque.png · 16:9 · transparent · for the free-spins intro, the buy confirmation and the win summary
```text
An ornate horizontal message panel for a slot game: a rounded-rectangle plaque of glossy deep turquoise lacquer with a soft inner glow, framed by a raised polished gold border with a band of white majolica tiles painted with tiny limes, pineapples and leaves; a hot-pink hibiscus between two glossy tropical leaves crowns its top centre, and small gold heart studs sit at its four corners. Its large centre is a smooth, plain, empty surface, left blank for text. Symmetrical, straight front view. Match the style and lighting of the attached image. Premium AAA slot game art in a crisp, glossy stylised 3D style: bold clean shapes, saturated colours, crisp specular highlights, tack-sharp edges, no blur, high detail. No text, no cast shadow. On a transparent background, exported as a PNG with transparency. The whole panel is in frame with a clear margin.
```

### ui-amore-heart.png · 1:1 · transparent · the Amore meter
The game fills the heart with glowing pink and writes the free-spins multiplier inside it.
```text
A heart-shaped meter frame seen straight on: a thick, heart-shaped ring of glossy hot-pink lacquer with thin polished gold mouldings on its inner and outer edges, a row of small glossy white pearls along its outer edge, and a small gold bow with a red lipstick kiss print at its top centre. Its centre is completely empty and see-through: a clean heart-shaped hole about 70% of the heart's width. Match the style and lighting of the attached image. Premium AAA slot game art in a crisp, glossy stylised 3D style: bold clean shapes, saturated colours, crisp specular highlights, tack-sharp edges, no blur, high detail. No text, no cast shadow. On a transparent background, exported as a PNG with transparency. The whole heart is in frame with a clear margin.
```

---

## Light effects (glow on black)

These are light, so the game adds them on top. Black means "no light". Keep them **centred, and fading out before the edges**.

### fx-twinkle.png · 1:1 · the sparkle that runs along a winning line
```text
A single four-pointed sparkle star: a tiny brilliant white core, two long razor-thin rays (one horizontal, one vertical), two shorter diagonal rays and a soft pale-pink halo. Centred, with the rays fading out before the edges. A light effect only, glowing, on a pure black (#000000) background with nothing else in the frame. No text.
```

### fx-heart-trail.png · 2:1 · a kiss flying from Bella, and into Amore
```text
A tiny glowing heart flying from left to right: a bright white-pink heart at its head with a small sparkle, followed by a long tapering trail of tiny glowing hearts, sparkles and glitter in hot pink, coral and gold, fading out toward the left. Horizontal, vertically centred, nothing cropped. A light effect only, glowing, on a pure black (#000000) background with nothing else in the frame. No text.
```

### fx-heart-burst.png · 1:1 · a multiplier kiss paying, and a kiss landing in Amore
```text
A joyful burst of light seen head-on: a bright white-pink flash at the centre, with dozens of tiny glowing hearts and sparkles exploding outward in every direction in hot pink, coral and gold, with a soft pink haze. Symmetrical radial composition, centred, fading out well before the edges of the image. A light effect only, glowing, on a pure black (#000000) background with nothing else in the frame. Nothing is cropped by the frame. No text.
```

### fx-sunburst.png · 1:1 · scatters and the free-spins intro
```text
A radiant sunburst seen straight on: a brilliant white-gold core, and many long, crisp, alternating rays of warm gold and soft turquoise light streaming outward 360 degrees, with tiny sparkles along the rays. Centred and symmetrical, fading out before the edges. A light effect only, glowing, on a pure black (#000000) background with nothing else in the frame. No text.
```

### fx-firework-heart.png · 1:1 · the Neon Night sky and big wins
```text
A single firework bursting in the night sky in the shape of a heart: hundreds of glowing sparks in hot pink with gold tips forming a crisp heart outline, each spark trailing a short glittering tail, with a bright white flash at the centre and a few sparks drifting down. Centred, fading out before the edges. A light effect only, glowing, on a pure black (#000000) background with nothing else in the frame. No text.
```

### fx-sweep.png · 21:9 · the big-win and free-spins sweep
The game slides it across the whole screen, so it must reach the left, top and bottom edges.
```text
A sweeping wave of glittering light rushing from left to right: billowing, shimmering streams of hot pink, turquoise and gold light filled with thousands of sparkles, tiny glowing hearts and bright bubbles; its leading edge on the right is a tall, bright cresting wave of white-pink light throwing sparkles forward. It fills the image from the left edge, top to bottom, and its crest ends at about 80% of the width; the rest of the image to the right is pure black. Joyful, glamorous and dazzling. A light effect only, glowing, on a pure black (#000000) background. No text, no figures.
```

---

## Juice (liquid, white on black)

The script turns the white into the liquid's shape and shading, and the game tints it with the winning fruit's colour: pink for the peach, gold for the pineapple, magenta for the dragon fruit, and so on. So the liquid must be **pure white with grey shading**, never coloured.

### fx-splash.png · 1:1 · the juice burst behind a winning fruit
```text
A juicy liquid splash seen head-on, frozen in time as if photographed with a high-speed flash: a burst of pure white milk-like liquid flying outward from the centre in every direction, a crown of smooth liquid tendrils that end in round drops, and a few loose drops around it, with crisp bright highlights and soft grey shading inside the liquid so it reads as glossy, three-dimensional liquid. Centred; the splash fills about 75% of the image and nothing is cropped by the frame. Tack-sharp, no motion blur. Only white liquid on a pure black (#000000) background: no glass, no fruit, no colour, no text.
```

### fx-droplets.png · 1:1 · drops flying from the splash
I cut each drop out, so they must be separate.
```text
About 30 separate drops of pure white milk-like liquid scattered evenly across the image, with clear black space between every one of them: round drops, teardrops, small splats and tiny beads in many sizes and angles, each glossy and three-dimensional with a crisp bright highlight and soft grey shading. None of them touch each other or the edge. Tack-sharp, no motion blur. Only white liquid on a pure black (#000000) background: no colour, no text.
```

---

## Celebration pieces (transparent)

### fx-confetti.png · 1:1 · big-win rain · attach sym-a and sym-m-kiss-gold
I cut each piece out, so they must be separate.
```text
About 40 small separate pieces scattered evenly across the image with clear gaps, none touching each other or the edge: curly paper streamers and confetti squares in hot pink, turquoise, lime green and lemon yellow; small paper cocktail parasols in pink and turquoise, open and closed; glossy fruit pieces (lime wheels, pineapple wedges, mango cubes, a few ruby pomegranate seeds, blueberries); shiny gold coins stamped with a heart, at many angles (face-on, tilted, edge-on); and a few hot-pink hibiscus petals; all in different sizes. Each piece crisp, glossy and solid. Match the style and lighting of the first attached image, and the gold of the second. Premium AAA slot game art in a crisp, glossy stylised 3D style: bold clean shapes, saturated colours, crisp specular highlights, tack-sharp edges, no blur. No text, no cast shadow. On a transparent background, exported as a PNG with transparency.
```

---

## Lettering (transparent) · ChatGPT or Ideogram

Generate `txt-logo` first, then attach it to every banner so the lettering matches. Check the spelling every time.

### txt-logo.png · 2:1 · attach sym-a, sym-c-dragonfruit and sym-m-kiss-pink
It sits on the frame's top edge, so it must read well over both the frame and the sky.
```text
The game logo in chunky, juicy three-dimensional lettering on two lines. On top, "TUTTI" in glossy hot-pink lacquer letters with polished gold rims and bright highlights. Below it and larger, "FRUTTI", each letter a different juicy fruit colour in glossy lacquer with a polished gold rim: F tangerine orange, R golden yellow, U lime green, T turquoise, T hot magenta, I cobalt blue. A ripe peach like the one in the first attached image nestles against the T at the start of TUTTI; a small glossy red lipstick kiss print sits beside the last letter of FRUTTI; a few clear juice drops and sparkles fly off the letters; two glossy tropical leaves peek out from behind. Flirty, juicy, glamorous, premium and perfectly legible. Spell exactly: TUTTI FRUTTI. Match the finish of the attached images. Premium AAA slot game art in a crisp, glossy stylised 3D style like a top mobile game: bold clean shapes, saturated colours, crisp specular highlights, tack-sharp edges, no blur. No other text, no cast shadow. On a transparent background, exported as a PNG with transparency. The whole logo is in frame with a clear margin.
```

### Banners · 3:1 · attach txt-logo

There is one prompt per banner; only the words and the material sentence change.

| File | Words | Material sentence |
|---|---|---|
| `txt-big-win.png` | `BIG WIN` | `glossy hot-pink lacquer letters with polished gold rims, with juice drops splashing off them and bright sparkles` |
| `txt-mega-win.png` | `MEGA WIN` | `glossy turquoise lacquer letters with polished gold rims, with juice drops splashing off them and bright sparkles` |
| `txt-epic-win.png` | `EPIC WIN` | `glossy letters fading from tangerine at the bottom to hot pink at the top, with polished gold rims, a burst of tiny hearts behind them and bright sparkles` |
| `txt-max-win.png` | `MAX WIN` | `mirror-polished gold letters with rims of tiny sparkling white diamonds, a splash of juice in every fruit colour bursting behind them and radiant sparkles` |
| `txt-free-spins.png` | `FREE SPINS` | `glowing hot-pink neon-tube letters with a turquoise neon outline, mounted on thin polished gold backing, softly glowing` |
| `txt-total-win.png` | `TOTAL WIN` | `glossy hot-pink lacquer letters with polished gold rims, with juice drops splashing off them and bright sparkles` |

```text
The words "BIG WIN" in chunky, juicy three-dimensional lettering on one line: glossy hot-pink lacquer letters with polished gold rims, with juice drops splashing off them and bright sparkles. The same lettering style, weight and finish as the attached logo. Bold, celebratory, perfectly legible, slightly arched. Spell exactly: BIG WIN. Premium AAA slot game art in a crisp, glossy stylised 3D style: bold clean shapes, saturated colours, crisp specular highlights, tack-sharp edges, no blur. No other text, no cast shadow. On a transparent background, exported as a PNG with transparency. The whole word is in frame with a clear margin.
```

---

## Lobby tile (scene)

### key-thumbnail.png · 4:3 · attach char-bella-party, char-marina-party, bg-day-landscape and sym-a
The logo goes on top afterwards; I compose it.
```text
Hero key art for the game's lobby tile: the two women from the first and second attached images, seen from the knees up, side by side on the right half of the image and turned slightly toward each other: the jet-black-haired one in hot pink raising her sparkler cocktail in a toast and winking at the viewer, the copper-haired one in turquoise raising her gold cocktail shaker with a sultry smile; glamorous and flirtatious, both fully dressed. Around them, glossy fruit bursts toward the viewer (a peach like the one in the fourth attached image, a pineapple, a dragon fruit, a pomegranate and a lime), with crisp juice splashes in pink, gold and green, hot-pink heart lockets and confetti; the sunny Riviera beach club of the third attached image behind them. The background stays clear and crisp, only slightly softer than the foreground. The top third and the left side are calmer, because the logo goes there afterwards. Spectacular, juicy, glamorous and premium. Crisp stylised 3D, bright summer light, saturated colours, premium AAA slot game key art. No text, no logo. Avoid: candy, sweets, lollipops, rainbows, orchards, golden-hour light, haze, heavy bokeh, red-and-white stripes, a hat of fruit, lingerie, swimwear.
```

---

## What I do when the art arrives

**The build script:** `scripts/art/process-tutti-frutti.py`, on the shared helpers in `scripts/art/artlib.py`, does the following:
- keys the frame's magenta, and finds its window and its two side bands;
- turns the glow sheets' black into light;
- turns the juice sheets' white into a tintable liquid;
- splits the sheets (`fx-droplets`, `fx-confetti`) into single pieces;
- measures each pose of both women: the feet line, the head, the hand that flicks or blows the kiss, the raised glass or shaker;
- packs everything as WebP into `public/images/tutti-frutti/`, with an `art.json`;
- draws a placeholder for every file not yet delivered.

**Kept crisp:** symbols are shrunk once, with a high-quality filter and a light sharpen, to twice the largest cell size, so they stay sharp on high-density screens. Backgrounds are served at their full size and never stretched past it.

**Made in code, not art:**
- the 20 line markers on the frame and the glowing line ribbons;
- the glitter on the sea, the ripples in the pool, the sway of the leaves;
- the neon flicker and the fireworks of the Neon Night;
- the sunset time-lapse into the free spins;
- the fruit's bounce, and the juice's colour;
- the kisses' flight, and Amore filling up;
- the reels, the bar, every word on the badge, the plaque and the kisses, and every number.

**Thumbnail:** I compose the lobby thumbnail from `key-thumbnail` and the logo.
