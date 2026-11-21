# Tutti Frutti: art prompts (round 1)

Audience: whoever generates the art for Tutti Frutti. Every prompt below is complete; copy it as is.

**Name:** Tutti Frutti (code `tutti-frutti`), the user's choice on 2026-11-21. Only `txt-logo` carries the name.

## The look in one paragraph

The game is set in **a glamorous beach club on the Italian Riviera, in high summer**. In the base game it is noon: a white terrace with a turquoise infinity pool above a sparkling bay, pastel villas stacked up the cliffs, hot-magenta bougainvillea, palms, and a clear azure sky. Free spins are **the Neon Night**: the same terrace after dark, with the pool lit from below, neon signs shaped like fruit, party lights strung between the palms, and heart-shaped fireworks over the sea. The host is **Bella**, the club's hostess: a glamorous, flirtatious pin-up in a hot-pink halter dress, holding the house cocktail. She stands full-length on a tiled podium to the right of the grid. The symbols are **nine exotic fruits, and only fruit**. Wins are **sliced and squeezed**: a slash of light cuts each winning fruit into two juicy halves, and juice splashes in its own colour. Multipliers are **kisses**, heart-shaped enamel lockets with a lipstick print. In free spins they fill **Amore**, a big heart above the grid.

**The style, which is the user's main ask:** crisp and sharp like a top mobile game. That means bold, simple, glossy shapes, juicy saturated colours, a thin clean contour on every fruit, and bright clean sunlight. There is no haze, no bokeh, no depth-of-field blur and no painterly softness, in the symbols and in the backgrounds alike.

## How it differs from Sugar Orchard

Tutti Frutti must not look or feel like Sugar Orchard. Keep these differences in every asset:

| | Sugar Orchard | Tutti Frutti |
|---|---|---|
| Place and light | a candy orchard at golden hour, warm haze | a Riviera beach club at noon: turquoise, azure, white, hot pink |
| Free spins | the orchard at night, lanterns and fireflies | the Neon Night: neon fruit signs, a lit pool, heart fireworks |
| Style | painterly, photoreal, soft light | crisp glossy stylised 3D with a clean contour, sharp everywhere |
| Symbols | four candies and five orchard fruits | nine exotic fruits, no candy at all |
| A win | frost crust, then a sugar explosion | a slash of light, two juicy halves, a juice splash |
| Multipliers | candy stars | kiss lockets: enamel hearts with a lipstick print |
| Scatter | a fruit-and-sweets basket with a bow | the house cocktail in a gold-stemmed coupe |
| Host | the Queen Bee: brown waves, honey-gold gown, tiara, wings | Bella: jet-black ponytail, hot-pink mini halter dress, no wings, no crown |
| Host's helpers | worker bees | none: her kisses |
| Screen | the host's upper body on the left | the host full-length on the right (the "throne" layout) |

## How the art becomes the game

**A win:**
1. Bella winks and flicks her finger at the grid (`char-bella-wink`).
2. A crisp slash of light swipes across each winning fruit (`fx-slash`). The fruit falls apart into its two juicy halves (`cut-*`), which slide apart and tumble away.
3. Juice bursts out in the fruit's own colour (`fx-splash`, tinted per fruit by code), and drops fly and fall with gravity (`fx-droplets`). A glint flashes on the cut (`fx-twinkle`).
4. New fruit drops in from above and lands with a glossy bounce.

**The kisses (multipliers):**
1. A kiss locket lands on a cell with a pop (`sym-m-kiss-*`). The game prints its value on the locket.
2. When a winning tumble chain ends, Bella blows a kiss (`char-bella-kiss`). The lockets lift off the grid and fly to her on a trail of tiny hearts (`fx-heart-trail`).
3. She sends one big kiss into the win, which bursts in hearts (`fx-heart-burst`) and jumps.
4. In free spins the kiss flies into **Amore** (`ui-amore-heart`) instead. The game fills the heart with glowing pink, and its total multiplies every later winning free spin.

**Celebrations:**
- **Big wins:** a wave of glittering pink, turquoise and gold light sweeps the screen (`fx-sweep`). Confetti, paper parasols, fruit slices and gold heart coins rain down (`fx-confetti`). Bella dances (`char-bella-dance`), and the screen shakes more with each tier.
- **Free spins:** Bella raises her sparkler cocktail (`char-bella-party`). The sun sets in a fast time-lapse, the neon signs flicker on, heart fireworks burst over the bay (`fx-firework-heart`), and FREE SPINS lights up in neon.
- **Scatters:** each cocktail that lands flashes a sunburst (`fx-sunburst`). Once three are down, the remaining columns slow down to a drumroll.
- **The scene is alive:** the sea glitters and the pool water ripples. Tropical leaves sway in the lower corners (`fg-tropical`). Bella sways gently on her podium (`fg-podium`). At night the neon flickers and fireworks go off now and then. Code draws the glitter, the ripples and the flicker.

**What code does and what art does:** the animation is code. The art supplies the pieces it animates: layered stills, particles and light. Image models can't draw a consistent frame-by-frame sequence, so **never ask for sprite sheets or animation frames**.

## Rules (read before generating)

1. **One prompt per request.** Never ask for several assets at once. If you do, the tool falls back to flat vector art.
2. **Set the style first.**
   - Generate `sym-a-peach` until you love it, then attach it as a style reference to every other symbol, kiss, cut, UI and lettering prompt.
   - Generate `char-bella-idle` until you love her, then attach her to every other pose.
   - Each prompt says what to attach. In Midjourney, use `--sref <image url>` for style and `--cref <image url>` for the character.
3. **Five kinds of canvas.** Each prompt ends with one of these:
   - **Transparent** (the default): a real transparent PNG. ChatGPT image can export one. This game is full of pink, magenta and purple (the peach, the dragon fruit, the fig, Bella's dress), so **never key a symbol, a pose or a card on magenta**.
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
   - Batches are fine. The symbols, Bella and the backgrounds first already change the whole look. The `cut-*` halves can come last: until they arrive, the game splits each fruit's own picture.
7. **Tools:**
   - **ChatGPT image** is best for transparency, text, layouts and the character. Use it for `sym-*`, `cut-*`, `char-*`, `ui-*`, `txt-*` and `fx-confetti`.
   - **Midjourney** is richest for backgrounds and light effects. Add `--ar 16:9`, `--ar 9:16`, `--ar 2:1`, `--ar 21:9` or `--ar 1:1`, and move each prompt's "Avoid:" list into `--no`.
8. **Bella is flirtatious, not explicit.** She is a glamorous summer pin-up: a figure-hugging dress, bare shoulders, a side slit, teasing looks, winks and blown kisses. She is always fully dressed. Never ask for lingerie, nudity, see-through fabric or explicit poses. Image tools refuse them, and regulated operators would reject the game; UK advertising rules, for one, forbid linking gambling to seduction or sexual attractiveness, and operators use the lobby tile in their marketing.
9. **Keep it ours.** This game must not look like Sugar Orchard or like any other slot. Never generate any of these:
   - **Sugar Orchard's world:** candy, sweets, toffee, chocolate, caramel, sugar frost, gumballs, baskets, bows, orchards, golden-hour light, bees, honey, wings, tiaras, evening gowns.
   - **Sugar Orchard's fruit:** oranges, cherries, strawberries, lemons, kiwis.
   - **Another famous fruit-and-candy slot's set:** bananas, grapes, watermelons, plums, apples, lollipops, rainbows, candy hearts, colourful bombs, red-and-white candy-cane stripes, pastel cotton-candy clouds.
   - **For Bella:** a hat or turban piled with fruit (that is a famous real performer's signature look), or any resemblance to a real person, model or film character.
   - **Names:** never put the name of a real person, film, song, brand or other game in any prompt.

### Order

1. `sym-a-peach`: the style anchor.
2. The other symbols, `sym-b` … `sym-i`, then `sym-s-cocktail` and the four `sym-m-kiss-*`.
3. `char-bella-idle`: the character anchor. Then the other five poses and `fg-podium`.
4. `bg-day-landscape`, then the other three backgrounds and `fg-tropical`.
5. `ui-frame`, `ui-tumble-plaque`, the three `ui-card-*`, `ui-spin`, `ui-plaque`, `ui-amore-heart`.
6. `txt-logo`, then the banners.
7. The `fx-*` effects and `fx-confetti`.
8. The nine `cut-*` halves.
9. `key-thumbnail`.

---

## Symbols (square 1:1, transparent)

The grid shows the symbols at about 60–120 px. Each one needs a bold, instantly readable silhouette and its own dominant colour.

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

The game cuts a winning fruit in two, so **every symbol must be solid and opaque**, with crisp edges.

Every symbol prompt ends with the same style block, so the set matches.

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

### Kiss lockets, the multipliers (4 colours by value) · attach sym-a

The game prints the value, from 2× up to 500×, on the locket's face, so the face must be big and plain. There is one prompt per colour; only the colour sentence changes.

| File | Values | Colour sentence |
|---|---|---|
| `sym-m-kiss-aqua.png` | 2×–5× | `glossy turquoise enamel` |
| `sym-m-kiss-lime.png` | 6×–15× | `glossy lime-green enamel` |
| `sym-m-kiss-pink.png` | 20×–50× | `glossy hot-pink enamel` |
| `sym-m-kiss-gold.png` | 100×–500× | `mirror-polished gold, its rim set with a row of tiny sparkling white diamonds, the most dazzling and rare of the set` |

```text
A multiplier symbol: a plump, heart-shaped locket of glossy turquoise enamel with a smooth, polished gold rim and a small gold ring at its top, and a small glossy red lipstick kiss print on its upper left lobe. The heart's face is large, smooth and plain at its centre, where a number will be printed later: no number, no text. It is a piece of jewellery, not a sweet. Bold, round and compact. Straight front view, tilted very slightly. Match the style, lighting and finish of the attached image. Fills about 80% of the frame. Premium AAA slot game art in a crisp, glossy stylised 3D style like a top mobile game: bold simple shapes, smooth glossy surfaces, juicy saturated colours, a thin clean contour line around the silhouette, bright crisp white specular highlights, bright clean summer sunlight from the upper left, a soft turquoise bounce light from the right, tack-sharp edges everywhere, no blur, no depth of field, no film grain, no haze, high detail. No cast shadow, no frame. On a transparent background, exported as a PNG with transparency. The whole object is in frame with a clear margin.
```

---

## Bella, the game's host (2:3, transparent)

She stands **full-length on the right of the grid**, turned toward it, on her podium (`fg-podium`). On a phone, the game shows her from the waist up over the frame's top-right corner, so her face, hair and hands must be crisp.

**How to generate:** use ChatGPT image, one request per image, on a transparent background. Generate `char-bella-idle` first. Then attach it to every other pose, so her face, hair, dress and jewellery stay identical. Regenerate a pose until it's clearly the same woman. Keep her feet at the same height and her body at the same size in every pose; the game stands each pose on the same podium.

### char-bella-idle.png · the character anchor · attach sym-a
```text
An original glamorous hostess of a Riviera beach club, the host of a premium slot game: a stunning, confident adult woman in her late twenties with a curvy hourglass figure and sun-kissed bronze skin. She has sleek, glossy jet-black hair in a high swinging ponytail, with a big hot-pink hibiscus flower tucked behind one ear; large emerald-green eyes with long lashes and a sharp winged liner; glossy coral-red lips; small gold hoop earrings; and oversized white cat-eye sunglasses pushed up on top of her head. She wears a figure-hugging halter sundress of glossy hot-pink satin with a sweetheart neckline, bare shoulders and an open back, cinched at the waist by a thin polished gold belt; the skirt ends at mid-thigh with a flirty high slit on one side. Strappy gold high-heeled sandals, a stack of thin gold bangles on one wrist. In her left hand she holds a wide coupe glass with a polished gold stem, filled with a layered tropical cocktail in hot pink, tangerine and yellow, garnished with a pineapple wedge, a lime wheel and a small pink paper parasol, like the cocktail of a glamorous beach club. Pose: the full figure from head to toe, standing with her weight on one hip in a confident, alluring summer pin-up pose, three-quarter view with her body turned toward the left of the image; her right hand lifted to touch her ponytail; a teasing, flirtatious half-smile, looking at the viewer from under her lashes. Sensual, playful and glamorous, tasteful and fully dressed, nothing explicit. Original design, not resembling any real person, model or existing character. Match the finish of the attached image. The figure fills about 92% of the frame height. Premium AAA slot game art, high-end stylised 3D character render, crisp and clean like a premium mobile-game heroine: glossy satin, polished gold jewellery, smooth skin with a soft healthy glow, saturated colours, crisp specular highlights, bright clean summer sunlight from the upper left, a soft turquoise rim light from the right like light off the sea, tack-sharp edges, no blur, no depth of field, high detail. No text, no outline, no cast shadow, no floor, no frame. On a transparent background, exported as a PNG with transparency. The whole figure from the top of her ponytail to the soles of her sandals is in frame with a clear margin.
```

Every other pose starts with the same description of her, so she stays the same woman. Only the "New pose" sentences differ.

### char-bella-wink.png · on a win · attach char-bella-idle and sym-a
```text
The same woman as in the first attached image: identical face, emerald-green eyes, coral-red lips, sleek jet-black high ponytail with the hot-pink hibiscus, white cat-eye sunglasses on her head, gold hoop earrings, sun-kissed bronze skin, the same hot-pink satin halter mini dress with its gold belt and side slit, gold sandals and gold bangles, and the same cocktail in her left hand. New pose: she stretches her right arm toward the left of the image and flicks her index finger at it playfully, as if sending a spark there, and gives the viewer a cheeky wink with a bright, delighted smile; her hip is popped to one side and her ponytail swings. Flirty, confident and fun, fully dressed. The full figure from head to toe, three-quarter view turned toward the left, feet at the same height and the same size in the frame as in the attached image. Match the finish of the second attached image. Premium AAA slot game art, high-end stylised 3D character render, crisp and clean like a premium mobile-game heroine: glossy satin, polished gold jewellery, smooth skin with a soft healthy glow, saturated colours, crisp specular highlights, bright clean summer sunlight from the upper left, a soft turquoise rim light from the right, tack-sharp edges, no blur, no depth of field, high detail. No text, no outline, no cast shadow, no floor, no frame. On a transparent background, exported as a PNG with transparency. The whole figure from the top of her ponytail to the soles of her sandals is in frame with a clear margin.
```

### char-bella-kiss.png · when the kisses pay · attach char-bella-idle and sym-a
```text
The same woman as in the first attached image: identical face, emerald-green eyes, coral-red lips, sleek jet-black high ponytail with the hot-pink hibiscus, white cat-eye sunglasses on her head, gold hoop earrings, sun-kissed bronze skin, the same hot-pink satin halter mini dress with its gold belt and side slit, gold sandals and gold bangles, and the same cocktail in her left hand. New pose: she blows a kiss toward the left of the image: her right hand is raised in front of her lips with the palm open and facing left, her lips puckered in a kiss, her eyes half-closed and her head tilted, one knee bent playfully. Draw no hearts or effects around her: the game adds them. Romantic, flirtatious and charming, fully dressed. The full figure from head to toe, three-quarter view turned toward the left, feet at the same height and the same size in the frame as in the attached image. Match the finish of the second attached image. Premium AAA slot game art, high-end stylised 3D character render, crisp and clean like a premium mobile-game heroine: glossy satin, polished gold jewellery, smooth skin with a soft healthy glow, saturated colours, crisp specular highlights, bright clean summer sunlight from the upper left, a soft turquoise rim light from the right, tack-sharp edges, no blur, no depth of field, high detail. No text, no outline, no cast shadow, no floor, no frame. On a transparent background, exported as a PNG with transparency. The whole figure from the top of her ponytail to the soles of her sandals is in frame with a clear margin.
```

### char-bella-dance.png · with the big-win banners · attach char-bella-idle and sym-a
```text
The same woman as in the first attached image: identical face, emerald-green eyes, coral-red lips, sleek jet-black high ponytail with the hot-pink hibiscus, white cat-eye sunglasses on her head, gold hoop earrings, sun-kissed bronze skin, the same hot-pink satin halter mini dress with its gold belt and side slit, gold sandals and gold bangles, and the same cocktail. New pose: she dances with joy: her hips swing to one side and the skirt of her dress flares, her left arm raised high above her head holding the cocktail glass up without spilling, her right hand on her hip, her ponytail flying, laughing with her head tilted back. Exuberant, sexy and celebratory, fully dressed. The full figure from head to toe, feet at the same height and the head and body the same size in the frame as in the attached image. Match the finish of the second attached image. Premium AAA slot game art, high-end stylised 3D character render, crisp and clean like a premium mobile-game heroine: glossy satin, polished gold jewellery, smooth skin with a soft healthy glow, saturated colours, crisp specular highlights, bright clean summer sunlight from the upper left, a soft turquoise rim light from the right, tack-sharp edges, no blur, no depth of field, high detail. No text, no outline, no cast shadow, no floor, no frame. On a transparent background, exported as a PNG with transparency. The whole figure from the top of her ponytail and raised glass to the soles of her sandals is in frame with a clear margin.
```

### char-bella-party.png · free spins · attach char-bella-idle and sym-a
```text
The same woman as in the first attached image: identical face, emerald-green eyes, coral-red lips, sleek jet-black high ponytail with the hot-pink hibiscus, white cat-eye sunglasses on her head, gold hoop earrings, sun-kissed bronze skin, the same hot-pink satin halter mini dress with its gold belt and side slit, gold sandals and gold bangles. New pose: she starts the party: she raises her cocktail glass high in a toast with her left hand, a lit gold sparkler in the glass throwing bright sparks, and opens her right arm wide in welcome; she faces the viewer with a slight turn toward the left, with a dazzling, excited smile and lips parted as if calling everyone to the party. Glamorous, thrilling and inviting, fully dressed. The full figure from head to toe, feet at the same height and the head and body the same size in the frame as in the attached image. Match the finish of the second attached image. Premium AAA slot game art, high-end stylised 3D character render, crisp and clean like a premium mobile-game heroine: glossy satin, polished gold jewellery, smooth skin with a soft healthy glow, saturated colours, crisp specular highlights, bright clean summer sunlight from the upper left, a soft turquoise rim light from the right, tack-sharp edges, no blur, no depth of field, high detail. No text, no outline, no cast shadow, no floor, no frame. On a transparent background, exported as a PNG with transparency. The whole figure from the top of her raised glass to the soles of her sandals is in frame with a clear margin.
```

### char-bella-waiting.png · after a minute without a spin · attach char-bella-idle and sym-a
```text
The same woman as in the first attached image: identical face, emerald-green eyes, coral-red lips, sleek jet-black high ponytail with the hot-pink hibiscus, white cat-eye sunglasses on her head, gold hoop earrings, sun-kissed bronze skin, the same hot-pink satin halter mini dress with its gold belt and side slit, gold sandals and gold bangles. New pose: she waits for the player, teasing: her left hand rests on her hip, and with her right hand she lifts a ripe golden-and-coral peach like the one in the second attached image to her lips, about to take a slow bite, one eyebrow raised and a teasing look straight at the viewer, as if to say "your move". Playful, provocative and charming, fully dressed. The full figure from head to toe, three-quarter view turned toward the left, feet at the same height and the same size in the frame as in the attached image. Match the finish of the second attached image. Premium AAA slot game art, high-end stylised 3D character render, crisp and clean like a premium mobile-game heroine: glossy satin, polished gold jewellery, smooth skin with a soft healthy glow, saturated colours, crisp specular highlights, bright clean summer sunlight from the upper left, a soft turquoise rim light from the right, tack-sharp edges, no blur, no depth of field, high detail. No text, no outline, no cast shadow, no floor, no frame. On a transparent background, exported as a PNG with transparency. The whole figure from the top of her ponytail to the soles of her sandals is in frame with a clear margin.
```

### fg-podium.png · 3:2 · transparent · her podium · attach char-bella-idle and sym-a
Every pose stands on it.
```text
A round, low podium for a glamorous hostess to stand on, seen from the front and slightly above: a short, wide drum of glossy white glazed ceramic wrapped in a band of hand-painted Mediterranean majolica tiles (turquoise, lemon yellow and lime green fruit-and-leaf motifs on white), with a polished gold rim at its top and bottom edges and a flat, smooth, empty top. At its foot on both sides, a few glossy tropical leaves and two hot-pink hibiscus flowers. Wide, solid and stable, empty on top. Match the colours of the woman in the first attached image and the finish of the second. Premium AAA slot game art in a crisp, glossy stylised 3D style: bold clean shapes, saturated colours, crisp specular highlights, bright clean summer sunlight from the upper left, tack-sharp edges, no blur, high detail. No text, no figure, no cast shadow, no frame. On a transparent background, exported as a PNG with transparency. The whole podium is in frame with a clear margin.
```

---

## Backgrounds (scene)

**The layout:**
- **Landscape:** the reel grid fills the centre, with the tumble-win plaque on top of its frame. The logo and the Buy, Super Buy and Ante cards stand in a column on the left. Bella stands full-length on her podium on the right. The bar runs along the bottom.
- **Portrait:** the logo is at the top, then the tumble-win plaque and the grid. Bella's upper body rises over the frame's top-right corner. The cards and the buttons are at the bottom.

So keep **the grid area calm**: open sky and sea in clean, smooth gradients. Keep the left column and the area behind Bella fairly calm too, and spend the detail on the edges. Calm still means crisp: clean shapes and smooth colour, never haze or blur. Code adds the glitter on the sea, the ripples in the pool, the neon flicker and the fireworks, so leave room for them.

### bg-day-landscape.png · 16:9 · base game · generate first
```text
A glamorous beach club on the Italian Riviera at noon in high summer. A bright white stone terrace runs along the bottom with a long turquoise infinity pool whose far edge seems to spill into a sparkling turquoise-and-cobalt bay. On the cliffs to the left and right, pastel villas in coral, peach, lemon yellow and white stack up the hillside with little balconies and green shutters; cascades of hot-magenta bougainvillea pour over the walls; tall palm trees lean in from both sides; crisp white sun loungers and round parasols in turquoise and coral line the terrace edges; a white yacht sits far out on the sea; a clear azure sky with a few crisp, bright white clouds and a hot, bright sun in the upper left. Composition for a game screen: the reel grid covers the centre (about 55% of the width, from 12% to 88% of the height), so that area is open sea and sky in clean, smooth, calm gradients with little detail; the left fifth, where buttons go, is fairly calm; the right quarter, where a woman will stand, is calm open sea and sky; the richest detail is around the outer edges. Bright, sunny, glamorous and joyful. Crisp and sharp everywhere: clean shapes, high clarity, no haze, no fog, no bokeh, no depth-of-field blur, no film grain, no painterly brush strokes. Premium AAA slot game background, crisp stylised 3D illustration with clean shapes and juicy saturated colours, like a top mobile game. No text, no logo, no signs with letters, no people, no characters, no UI. Avoid: candy, sweets, lollipops, rainbows, orchards, fruit trees, golden-hour light, sunset, haze, bokeh, red-and-white stripes, cotton-candy clouds.
```

### bg-day-portrait.png · 9:16 · attach bg-day-landscape
```text
The same Riviera beach club at noon as in the attached image, recomposed for a tall phone screen. The reel grid sits in the middle (about 90% of the width, from 20% to 62% of the height), so that area stays open sea and sky in clean, smooth, calm gradients. The pastel villas with their bougainvillea, and the palms, frame the sides; the top 12% is calm azure sky, where the logo goes; the upper right just above the grid stays calm, because a woman's head and shoulders rise there; the bottom 35% is the white terrace and the turquoise pool, simple and slightly darker in tone, where the buttons go. Same light, palette and style as the attached image. Crisp and sharp everywhere: clean shapes, high clarity, no haze, no fog, no bokeh, no depth-of-field blur, no film grain. Premium AAA slot game background, crisp stylised 3D illustration with clean shapes and juicy saturated colours, like a top mobile game. No text, no logo, no signs with letters, no people, no characters, no UI. Avoid: candy, sweets, lollipops, rainbows, orchards, fruit trees, golden-hour light, sunset, haze, bokeh, red-and-white stripes, cotton-candy clouds.
```

### bg-night-landscape.png · 16:9 · free spins, the Neon Night · attach bg-day-landscape
```text
The same beach club as in the attached image, with the same composition, now at night during a glamorous party for the free spins. A deep violet-blue night sky with a big, bright full moon over the bay and its silver path on the sea. The infinity pool glows electric turquoise from underwater lights. Glowing neon signs shaped like fruit hang on the villa walls and the terrace: a hot-pink peach, a lime-green pineapple, a turquoise palm tree and a pink heart, made of neon tubes only, with no letters or words. Strings of warm round party lights loop between the palms; the villa windows glow warm gold; the bougainvillea glows magenta in the neon light; two heart-shaped fireworks burst in hot pink and gold high over the sea and are mirrored in the water. Electric, glamorous and exciting, with rich contrast. The centre stays calm and dark for the reel grid, the left fifth fairly calm, the right quarter calm behind where a woman will stand, the edges rich. Crisp and sharp everywhere: clean shapes, high clarity, no haze, no fog, no bokeh, no depth-of-field blur, no film grain. Premium AAA slot game background, crisp stylised 3D illustration with clean shapes and juicy saturated colours, like a top mobile game. No text, no logo, no letters in the neon, no people, no characters, no UI. Avoid: candy, sweets, lanterns, fireflies, orchards, fruit trees, haze, bokeh, red-and-white stripes, horror.
```

### bg-night-portrait.png · 9:16 · attach bg-day-portrait and bg-night-landscape
```text
The Neon Night beach club of the second attached image, recomposed exactly like the tall phone layout of the first attached image: the reel grid in the middle (about 90% of the width, from 20% to 62% of the height) stays calm and dark; the moon sits in the upper left; the neon fruit signs, the party lights and the villas frame the sides; a heart-shaped firework bursts in the upper right just above the grid, where a woman's head and shoulders will rise; the top 12% is calm night sky, where the logo goes; the bottom 35% is the terrace and the glowing pool, simple and darker, where the buttons go. Crisp and sharp everywhere: clean shapes, high clarity, no haze, no fog, no bokeh, no depth-of-field blur, no film grain. Premium AAA slot game background, crisp stylised 3D illustration with clean shapes and juicy saturated colours, like a top mobile game. No text, no logo, no letters in the neon, no people, no characters, no UI. Avoid: candy, sweets, lanterns, fireflies, orchards, fruit trees, haze, bokeh, red-and-white stripes, horror.
```

### fg-tropical.png · 1:1 · transparent · attach bg-day-landscape
It sits in the lower-left corner in front of everything (mirrored at the right) and sways gently. In the Neon Night the game darkens it and tints it with neon light.
```text
A lush cluster of glossy tropical foliage entering from the lower-left corner of the image: big glossy monstera leaves and palm fronds in fresh greens, three hot-pink hibiscus flowers with yellow stamens, and tucked among the leaves the crown of a pineapple and a glossy lime, as in the sunny beach club of the attached image. It enters from the left and bottom edges (cut off by them on purpose) and ends in open space toward the upper right with leafy tips. Premium AAA slot game art in a crisp, glossy stylised 3D style: bold clean shapes, saturated colours, crisp specular highlights, bright clean summer sunlight from the upper left, tack-sharp edges, no blur, high detail. No text, no cast shadow. On a transparent background, exported as a PNG with transparency.
```

---

## Frame, cards and buttons · attach sym-a

The frame, the tumble-win plaque, the cards and the message plaque are blank on purpose. The game writes every word and number on them, so they stay sharp at any size and in any language.

The materials are **glossy white glazed ceramic, hand-painted Mediterranean majolica tiles, glossy coloured lacquer and polished gold**.

### ui-frame.png · 5:4 · magenta · ChatGPT recommended
```text
An ornate premium frame around a slot game's reel window, in the style of a glamorous Riviera beach club: a frame of glossy white glazed ceramic set with a band of hand-painted Mediterranean majolica tiles (turquoise, lemon yellow and lime green fruit-and-leaf motifs on white), edged inside and out with thin polished gold mouldings. On each of the four corners sits a small cluster of two glossy tropical leaves, a glossy lime and the green crown of a pineapple. The middle of the top edge is a plain, straight gold ledge with no crest, because a title plaque will sit there. The frame is symmetrical left to right and evenly thick on all four sides. The inner opening is a clean, sharp-cornered rectangle with proportions of exactly 6 wide to 5 high, filled with flat pure magenta (#FF00FF); everything outside the frame is flat pure magenta too. Straight front view, no perspective. Match the style and lighting of the attached image. Premium AAA slot game art in a crisp, glossy stylised 3D style: bold clean shapes, saturated colours, crisp specular highlights, tack-sharp edges, no blur, high detail. No text, no cast shadow. Nothing on the frame is pink, purple, magenta or see-through.
```

### ui-tumble-plaque.png · 4:1 · transparent · sits on the frame's top edge
The game writes "TUMBLE WIN" and the amount on it.
```text
A wide horizontal title plaque made to sit on the top edge of a slot game's reel frame: a long, shallow shape with a straight bottom edge and a gently arched top edge. Its face is glossy hot-pink lacquer with a soft inner glow. It is framed by a raised polished gold border with a thin band of turquoise-and-white majolica tile inside it, and at each end sits a glossy tropical leaf with a small hot-pink hibiscus flower. The large centre of the face is plain and empty, left blank for text. Symmetrical, straight front view. Match the style and lighting of the attached image. Premium AAA slot game art in a crisp, glossy stylised 3D style: bold clean shapes, saturated colours, crisp specular highlights, tack-sharp edges, no blur, high detail. No text, no cast shadow. On a transparent background, exported as a PNG with transparency. The whole plaque is in frame with a clear margin.
```

### ui-card-buy.png, ui-card-super.png and ui-card-ante.png · 3:4 · transparent
These are the cards in the left column. The game writes "BUY FREE SPINS" and the price on the first, the super buy and its price on the second, and the ante text and its switch on the third. Only the colour sentence changes:

| File | Colour sentence |
|---|---|
| `ui-card-buy.png` | `glossy hot-pink lacquer with a soft inner glow, crowned at its top centre by a small cocktail coupe with a gold stem and a pink paper parasol` |
| `ui-card-super.png` | `glossy polished-gold lacquer with a soft warm inner glow, crowned at its top centre by a small gold heart-shaped locket with a red lipstick kiss print` |
| `ui-card-ante.png` | `glossy turquoise lacquer with a soft inner glow, crowned at its top centre by a small hot-pink hibiscus flower and a lime wheel` |

```text
A tall feature card for a slot game's side menu: a vertical rounded-rectangle tablet of glossy hot-pink lacquer with a soft inner glow, crowned at its top centre by a small cocktail coupe with a gold stem and a pink paper parasol. It is framed by a raised polished gold border with a thin band of white-and-turquoise majolica tile inside it, with small gold heart studs at its four corners. Its face is plain and empty, left blank for text and buttons. Symmetrical, straight front view. Match the style and lighting of the attached image. Premium AAA slot game art in a crisp, glossy stylised 3D style: bold clean shapes, saturated colours, crisp specular highlights, tack-sharp edges, no blur, high detail. No text, no cast shadow. On a transparent background, exported as a PNG with transparency. The whole card is in frame with a clear margin.
```

### ui-spin.png · 1:1 · transparent
```text
A round premium spin button seen straight on, made like a giant glossy slice of pink grapefruit: juicy coral-pink segments radiating from the centre under a smooth, clear glossy dome, a creamy white ring of pith, a thin glossy yellow-pink rind, and an outer ring of polished gold with eight small gold heart studs. At its centre, a bold white circular double-arrow spin icon, embossed and glossy. Juicy, tactile and irresistible to press. Match the style and lighting of the attached image. Premium AAA slot game art in a crisp, glossy stylised 3D style: bold clean shapes, saturated colours, crisp specular highlights, tack-sharp edges, no blur, high detail. No text, no cast shadow. On a transparent background, exported as a PNG with transparency. The whole button is in frame with a clear margin.
```

### ui-plaque.png · 16:9 · transparent · for the free-spins intro, the buy confirmation and the win summary
```text
An ornate horizontal message panel for a slot game: a rounded-rectangle plaque of glossy deep turquoise lacquer with a soft inner glow, framed by a raised polished gold border with a band of white majolica tiles painted with tiny lemons, limes and leaves; a hot-pink hibiscus between two glossy tropical leaves crowns its top centre, and small gold heart studs sit at its four corners. Its large centre is a smooth, plain, empty surface, left blank for text. Symmetrical, straight front view. Match the style and lighting of the attached image. Premium AAA slot game art in a crisp, glossy stylised 3D style: bold clean shapes, saturated colours, crisp specular highlights, tack-sharp edges, no blur, high detail. No text, no cast shadow. On a transparent background, exported as a PNG with transparency. The whole panel is in frame with a clear margin.
```

### ui-amore-heart.png · 1:1 · transparent · the Amore meter
The game fills the heart with glowing pink and writes the free-spins total multiplier inside it.
```text
A heart-shaped meter frame seen straight on: a thick, heart-shaped ring of glossy hot-pink lacquer with thin polished gold mouldings on its inner and outer edges, a row of small glossy white pearls along its outer edge, and a small gold bow with a red lipstick kiss print at its top centre. Its centre is completely empty and see-through: a clean heart-shaped hole about 70% of the heart's width. Match the style and lighting of the attached image. Premium AAA slot game art in a crisp, glossy stylised 3D style: bold clean shapes, saturated colours, crisp specular highlights, tack-sharp edges, no blur, high detail. No text, no cast shadow. On a transparent background, exported as a PNG with transparency. The whole frame is in frame with a clear margin.
```

---

## The halves (square 1:1, transparent) · batch 2

When a fruit wins, the game swaps it for its two halves and slides them apart. Attach the matching symbol, so each half matches its fruit exactly. Until these arrive, the game splits the fruit's own picture.

There is one prompt per fruit; only the fruit sentence changes.

| File | Attach | Fruit sentence |
|---|---|---|
| `cut-a-peach.png` | sym-a | `the peach, cut cleanly in two from top to bottom: juicy golden-yellow flesh blushing red toward the centre, the rough red-brown stone sitting in the left half, the leaves still on the right half` |
| `cut-b-pineapple.png` | sym-b | `the pineapple, cut cleanly in two from top to bottom: juicy golden flesh with fine fibres and a paler core, the leafy crown split between the two halves` |
| `cut-c-dragonfruit.png` | sym-c | `the dragon fruit, cut cleanly in two from top to bottom: bright white flesh speckled with tiny black seeds, inside the hot-magenta skin and its green-tipped scales` |
| `cut-d-pomegranate.png` | sym-d | `the pomegranate, cut cleanly in two across its middle: hundreds of glistening translucent ruby seeds packed between thin cream walls, a few loose seeds flying` |
| `cut-e-mango.png` | sym-e | `the mango, cut cleanly in two lengthwise: bright juicy orange flesh, the flat pale stone showing in the left half, the leaf on the right half` |
| `cut-f-lime.png` | sym-f | `the lime, cut cleanly in two across its middle: pale green juicy segments in a star pattern inside a white ring and the green peel` |
| `cut-g-blueberries.png` | sym-g | `the three blueberries, each cut cleanly in two: pale green-gold flesh with a purple rim inside the cobalt skins, the six halves in two groups of three` |
| `cut-h-fig.png` | sym-h | `the fig, cut cleanly in two from top to bottom: a glowing pink-red inside full of tiny golden seeds, ringed by pale cream flesh and the violet skin` |
| `cut-i-lychee.png` | sym-i | `the lychee, cut cleanly in two: translucent pearl-white flesh around a glossy dark-brown seed, the rose-red shell on the outside` |

```text
The peach, cut cleanly in two from top to bottom: juicy golden-yellow flesh blushing red toward the centre, the rough red-brown stone sitting in the left half, the leaves still on the right half. The two halves stand side by side with a clear gap between them, both turned slightly toward the viewer so the juicy cut faces show, wet and glistening with a few juice drops on them. Exactly the same fruit, colours, contour and finish as the attached image. Fills about 85% of the frame width. Premium AAA slot game art in a crisp, glossy stylised 3D style like a top mobile game: bold simple shapes, smooth glossy surfaces, juicy saturated colours, a thin clean contour line around each piece, bright crisp white specular highlights, bright clean summer sunlight from the upper left, tack-sharp edges everywhere, no blur, no depth of field, high detail. No knife, no hands, no plate, no text, no cast shadow, no frame. On a transparent background, exported as a PNG with transparency. Both halves are whole and in frame with a clear margin.
```

---

## Light effects (glow on black)

These are light, so the game adds them on top. Black means "no light". Keep them **centred, and fading out before the edges**.

### fx-slash.png · 1:1 · the slice across a winning fruit
```text
A single swift, curved slash of light, like the arc of a blade cutting through the air, seen head-on: a thin, razor-sharp crescent of brilliant white light sweeping from the lower left to the upper right, brightest and thickest in its middle and tapering to fine points at both ends, with a soft pale-pink glow along it and a few tiny sparks flying off its outer edge. Centred, with clear black space all around. A light effect only, glowing, on a pure black (#000000) background with nothing else in the frame. Nothing is cropped by the frame. No blade, no text.
```

### fx-twinkle.png · 1:1 · the glint on a cut, and sparkle
```text
A single four-pointed sparkle star: a tiny brilliant white core, two long razor-thin rays (one horizontal, one vertical), two shorter diagonal rays and a soft pale-pink halo. Centred, with the rays fading out before the edges. A light effect only, glowing, on a pure black (#000000) background with nothing else in the frame. No text.
```

### fx-heart-trail.png · 2:1 · a kiss flying to Bella and into the win
```text
A tiny glowing heart flying from left to right: a bright white-pink heart at its head with a small sparkle, followed by a long tapering trail of tiny glowing hearts, sparkles and glitter in hot pink, coral and gold, fading out toward the left. Horizontal, vertically centred, nothing cropped. A light effect only, glowing, on a pure black (#000000) background with nothing else in the frame. No text.
```

### fx-heart-burst.png · 1:1 · a kiss landing on the win or in Amore
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

### fx-splash.png · 1:1 · the juice burst of a sliced fruit
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

### key-thumbnail.png · 4:3 · attach char-bella-party, bg-day-landscape and sym-a
The logo goes on top afterwards; I compose it.
```text
Hero key art for the game's lobby tile: the woman from the first attached image, seen from the knees up on the right side of the image, raising her sparkler cocktail in a toast and winking at the viewer, glamorous and flirtatious, fully dressed; around her, glossy fruit bursts toward the viewer (a peach like the one in the third attached image, a pineapple, a dragon fruit, and pomegranate and lime halves), with crisp juice splashes in pink, gold and green, hot-pink heart lockets and confetti; the sunny Riviera beach club of the second attached image behind her. The background stays clear and crisp, only slightly softer than the foreground. The top third and the left side are calmer, because the logo goes there afterwards. Spectacular, juicy, glamorous and premium. Crisp stylised 3D, bright summer light, saturated colours, premium AAA slot game key art. No text, no logo. Avoid: candy, sweets, lollipops, rainbows, orchards, golden-hour light, haze, heavy bokeh, red-and-white stripes, a hat of fruit, lingerie.
```

---

## What I do when the art arrives

**The build script:** `scripts/art/process-tutti-frutti.py`, on the shared helpers in `scripts/art/artlib.py`, does the following:
- keys the frame's magenta and finds its window;
- turns the glow sheets' black into light;
- turns the juice sheets' white into a tintable liquid;
- splits the sheets (`fx-droplets`, `fx-confetti`) into single pieces;
- measures each pose: the feet line, the head, the hand that flicks, the lips for the kiss, and the raised glass;
- packs everything as WebP into `public/images/tutti-frutti/`, with an `art.json`;
- draws a placeholder for every file not yet delivered.

**Kept crisp:** symbols and halves are shrunk once, with a high-quality filter and a light sharpen, to twice the largest cell size, so they stay sharp on high-density screens. Backgrounds are served at their full size and never stretched past it.

**Made in code, not art:**
- the glitter on the sea, the ripples in the pool, the sway of the leaves;
- the neon flicker and the fireworks of the Neon Night;
- the sunset time-lapse into the free spins;
- each fruit's split, the halves' fall and the juice's colour;
- the kisses' flight, and Amore filling up;
- the grid, the bar, every word on the plaques and cards, and every number.

**Thumbnail:** I compose the lobby thumbnail from `key-thumbnail` and the logo.
