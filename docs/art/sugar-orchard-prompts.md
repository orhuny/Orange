# Sugar Orchard: art prompts (round 3, full redesign)

Audience: whoever generates Sugar Orchard's art. Every prompt below is complete; copy it as is.

Round 3 replaces all earlier art: symbols, scatter, backgrounds and logo. Rounds 1 and 2 are in git history.

**Status (2026-11-19): delivered and in the game.**
- All 50 assets are in `storage/app/art/sugar-orchard/round3/`, with the generator's QC notes.
- `scripts/art/process-sugar-orchard.py` builds them into `public/images/sugar-orchard/`.
- To redo an asset, regenerate it with its prompt below, replace the file there, and rerun the script.

## How the art becomes the game

**What happens on a win:**
1. The winning symbols frost over (`fx-frost-crust`).
2. Each one bursts in a sugar-frost explosion (`fx-frost-burst`, `fx-frost-ring`).
3. It shatters into its own pieces (`frag-*`), and crystals, sparkles and sprinkles fly (`fx-crystals`, `fx-twinkle`, `fx-sprinkles`).
4. New symbols tumble in.

**Other effects:**
- **Big wins:** candy fireworks (`fx-fireworks`) and a banner (`txt-*-win`).
- **Scatters and free spins:** golden rays (`fx-golden-rays`).
- **Multiplier stars:** fly to the win as comets (`fx-comet`).
- **Backgrounds:** they move in layers. Branches and lanterns sway in front (`fg-*`); petals drift by day and fireflies by night.

**What the code does and what the art does:** the animation is code; the art supplies the pieces it animates. That is how top slots are built (layered stills and particle systems). Image models can't draw a consistent frame-by-frame sequence, so **never ask for sprite sheets or animation frames**.

## Rules (read before generating)

1. **One prompt per request.** Never ask for several assets at once. When asked for many, tools start drawing flat vector art with code; that is what went wrong in round 1.
2. **Set the style first.** Generate `sym-a-toffee` until you love it.
   - Then attach it as a style reference to every other symbol, star, fragment and UI prompt.
   - In ChatGPT, attach the image. In Midjourney, use `--sref <image url>`.
   - Each prompt says what to attach.
3. **Three kinds of canvas.** Each prompt ends with one of these:
   - **Cutout:** flat pure magenta `#FF00FF`. I key the magenta out.
     - Nothing on the object may be pink, purple or see-through, because magenta shows through or bleeds into it.
     - If your tool can export a real transparent PNG (ChatGPT can), that is even better: replace the magenta sentence with "on a transparent background".
   - **Glow:** pure black `#000000`. These are light effects; the game adds them as light, so black disappears and the glow stays soft. Never on magenta.
   - **Scene:** a full picture.
4. **Size and format:** the largest size the tool offers, as PNG. Upscale backgrounds to at least 2560×1440 (or 1440×2560) if you can.
5. **Text:** only the `txt-*` assets contain text. Check every letter, and regenerate until the spelling is exact.
6. **Delivery:** use the file names below, in `C:\Git\Orange\storage\app\art\sugar-orchard\round3\`.
   - Send 2–3 variants when unsure (`-a`, `-b`, `-c`); I pick and key the best one.
   - You can deliver in batches. Symbols and backgrounds first already change the whole look.
7. **Tools:**
   - **ChatGPT image:** best for text, layouts and transparency. Use it for `txt-*`, `ui-*` and the frame.
   - **Midjourney:** richest for backgrounds. Add `--ar 16:9` / `--ar 9:16` / `--ar 1:1`, and move each prompt's "Avoid:" list into `--no`.
8. **Keep it ours.** No lollipops, no rainbows, no pink cotton-candy clouds, no jelly-bean hearts, no bananas, grapes, watermelons, plums or apples. That set belongs to another game.

### Order

1. `sym-a-toffee`: the style anchor.
2. The other symbols: `sym-b` … `sym-i`, `sym-s`, `sym-m-*`.
3. `bg-day-landscape`, then the other three backgrounds.
4. `txt-logo`, then the banners.
5. `fx-*`.
6. `frag-*`.
7. `ui-*`, `fg-*`, `key-thumbnail`.

---

## Symbols (square 1:1, cutout)

The grid shows them at about 60–120 px, so each needs a bold, instantly readable silhouette and its own dominant colour.

### sym-a-toffee.png · top symbol · generate first
```text
A luxurious toffee wrapped in mirror-bright gold foil, the foil twisted at both ends into two flaring fans, its crinkled facets catching the light. The foil is torn open at the front, revealing glossy amber caramel with a slow golden drip. Tiny sugar crystals sparkle on the foil, and one bright star-shaped glint sits on the top highlight. Tilted slightly toward the viewer, rich and dynamic: the most valuable symbol of the set. Fills about 80% of the frame. Premium AAA slot game art, stylised photoreal 3D render, ultra glossy, juicy and tactile, rich saturated colours, crisp specular highlights, soft subsurface glow, warm key light from the upper left, cool rim light from the right, tack-sharp focus, high detail. No text, no outline, no cast shadow, no frame. Isolated on a flat, solid, pure magenta (#FF00FF) background: no gradient, no floor, no shadow. Nothing on the object is pink, purple or see-through. The whole object is in frame with a clear margin.
```

### sym-b-peppermint.png · attach sym-a
```text
A thick domed peppermint candy disc with bold glossy red-and-white swirl stripes spiralling into its centre, glassy hard-candy shine with deep reflections, a dusting of fine sugar along one edge, and a fresh bright-green mint leaf tucked behind it. Tilted three-quarters toward the viewer. The red is a pure cherry red, never pink. Match the style, lighting and finish of the attached image. Fills about 80% of the frame. Premium AAA slot game art, stylised photoreal 3D render, ultra glossy, juicy and tactile, rich saturated colours, crisp specular highlights, soft subsurface glow, warm key light from the upper left, cool rim light from the right, tack-sharp focus, high detail. No text, no outline, no cast shadow, no frame. Isolated on a flat, solid, pure magenta (#FF00FF) background: no gradient, no floor, no shadow. Nothing on the object is pink, purple or see-through. The whole object is in frame with a clear margin.
```

### sym-c-gummy-bear.png · attach sym-a
```text
A plump, cheerful emerald-green gummy bear sitting upright with a playful head tilt, soft chunky rounded shapes, glossy and squishy-looking, coated in sparkling sugar crystals, with a warm inner glow where light enters it. It is solid and opaque: the background never shows through it. Match the style, lighting and finish of the attached image. Fills about 80% of the frame. Premium AAA slot game art, stylised photoreal 3D render, ultra glossy, juicy and tactile, rich saturated colours, crisp specular highlights, soft subsurface glow, warm key light from the upper left, cool rim light from the right, tack-sharp focus, high detail. No text, no outline, no cast shadow, no frame. Isolated on a flat, solid, pure magenta (#FF00FF) background: no gradient, no floor, no shadow. Nothing on the object is pink, purple or see-through. The whole object is in frame with a clear margin.
```

### sym-d-truffle.png · attach sym-a
```text
A decadent dark chocolate truffle in a fluted gold paper cup, glossy molten ganache poured over its top and dripping down the sides, crowned with a whole roasted hazelnut and a flake of gold leaf, a few cocoa crumbs on the cup's rim. Rich mahogany and gold tones, velvety and luxurious. Match the style, lighting and finish of the attached image. Fills about 80% of the frame. Premium AAA slot game art, stylised photoreal 3D render, ultra glossy, juicy and tactile, rich saturated colours, crisp specular highlights, soft subsurface glow, warm key light from the upper left, cool rim light from the right, tack-sharp focus, high detail. No text, no outline, no cast shadow, no frame. Isolated on a flat, solid, pure magenta (#FF00FF) background: no gradient, no floor, no shadow. Nothing on the object is pink, purple or see-through. The whole object is in frame with a clear margin.
```

### sym-e-orange.png · the brand fruit · attach sym-a
```text
A vibrant ripe orange with a short stem and two glossy dark-green leaves, its dimpled peel shining with dew drops. A wedge is cut out of the front, showing glistening juicy segments, and one round juice droplet hangs in mid-air beside it. Bright, fresh and bursting with life: this orange is the brand's emblem. Match the style, lighting and finish of the attached image. Fills about 80% of the frame. Premium AAA slot game art, stylised photoreal 3D render, ultra glossy, juicy and tactile, rich saturated colours, crisp specular highlights, soft subsurface glow, warm key light from the upper left, cool rim light from the right, tack-sharp focus, high detail. No text, no outline, no cast shadow, no frame. Isolated on a flat, solid, pure magenta (#FF00FF) background: no gradient, no floor, no shadow. Nothing on the object is pink, purple or see-through. The whole object is in frame with a clear margin.
```

### sym-f-cherries.png · attach sym-a
```text
A pair of deep crimson cherries joined by slender green stems with one fresh leaf, so glossy they reflect the light like lacquer, tiny dew drops on their skin, one cherry slightly in front of the other. Pure deep red, never pink. Match the style, lighting and finish of the attached image. Fills about 80% of the frame. Premium AAA slot game art, stylised photoreal 3D render, ultra glossy, juicy and tactile, rich saturated colours, crisp specular highlights, soft subsurface glow, warm key light from the upper left, cool rim light from the right, tack-sharp focus, high detail. No text, no outline, no cast shadow, no frame. Isolated on a flat, solid, pure magenta (#FF00FF) background: no gradient, no floor, no shadow. Nothing on the object is pink, purple or see-through. The whole object is in frame with a clear margin.
```

### sym-g-strawberry.png · attach sym-a
```text
A big plump ripe strawberry with a lush green leafy crown, glistening red skin dotted with golden seeds, dew drops, its tip lightly dusted with sparkling sugar crystals. Slightly tilted, juicy and irresistible. Pure strawberry red, never pink. Match the style, lighting and finish of the attached image. Fills about 80% of the frame. Premium AAA slot game art, stylised photoreal 3D render, ultra glossy, juicy and tactile, rich saturated colours, crisp specular highlights, soft subsurface glow, warm key light from the upper left, cool rim light from the right, tack-sharp focus, high detail. No text, no outline, no cast shadow, no frame. Isolated on a flat, solid, pure magenta (#FF00FF) background: no gradient, no floor, no shadow. Nothing on the object is pink, purple or see-through. The whole object is in frame with a clear margin.
```

### sym-h-lemon.png · attach sym-a
```text
A vivid sunny-yellow lemon with a short stem and one glossy leaf, its textured peel beaded with dew, and a thick fresh lemon slice leaning against its front, showing bright juicy segments and a pale rind. Zesty and luminous. The slice is opaque. Match the style, lighting and finish of the attached image. Fills about 80% of the frame. Premium AAA slot game art, stylised photoreal 3D render, ultra glossy, juicy and tactile, rich saturated colours, crisp specular highlights, soft subsurface glow, warm key light from the upper left, cool rim light from the right, tack-sharp focus, high detail. No text, no outline, no cast shadow, no frame. Isolated on a flat, solid, pure magenta (#FF00FF) background: no gradient, no floor, no shadow. Nothing on the object is pink, purple or see-through. The whole object is in frame with a clear margin.
```

### sym-i-kiwi.png · attach sym-a
```text
A kiwi fruit cut in half: the front half faces the viewer, showing vivid emerald flesh, a creamy sunburst core ringed with glossy black seeds, glistening with juice; the fuzzy golden-brown uncut half sits just behind it. Fresh, bright and juicy. Match the style, lighting and finish of the attached image. Fills about 80% of the frame. Premium AAA slot game art, stylised photoreal 3D render, ultra glossy, juicy and tactile, rich saturated colours, crisp specular highlights, soft subsurface glow, warm key light from the upper left, cool rim light from the right, tack-sharp focus, high detail. No text, no outline, no cast shadow, no frame. Isolated on a flat, solid, pure magenta (#FF00FF) background: no gradient, no floor, no shadow. Nothing on the object is pink, purple or see-through. The whole object is in frame with a clear margin.
```

### sym-s-scatter-basket.png · scatter · attach sym-a
```text
The scatter symbol: a woven golden wicker basket overflowing with the orchard's treasures, with oranges, cherries, a strawberry, gold-foil toffees and peppermint candies spilling over its rim, a big satin ribbon bow in deep orange tied on the handle, warm golden light glowing out from inside the basket, sparkles around the fruit. Clearly the most special and magical symbol: bigger, brighter and more radiant than any other. Front view, slightly from above. Match the style, lighting and finish of the attached image. Fills about 85% of the frame. Premium AAA slot game art, stylised photoreal 3D render, ultra glossy, juicy and tactile, rich saturated colours, crisp specular highlights, soft subsurface glow, warm key light from the upper left, cool rim light from the right, tack-sharp focus, high detail. No text, no outline, no cast shadow, no frame. Isolated on a flat, solid, pure magenta (#FF00FF) background: no gradient, no floor, no shadow. Nothing on the object is pink, purple or see-through. The whole object is in frame with a clear margin.
```

### Multiplier stars (4 colours by value) · attach sym-a

The game prints the value (2×–100×) on the star, so the centre must stay empty. There is one prompt per colour; only the colour sentence changes.

| File | Values | Colour sentence |
|---|---|---|
| `sym-m-star-sky.png` | 2×–6× | `in bright sky blue and white` |
| `sym-m-star-emerald.png` | 8×–15× | `in rich emerald green` |
| `sym-m-star-gold.png` | 20×–50× | `in molten amber gold` |
| `sym-m-star-ruby.png` | 100× | `in deep ruby red with golden fire inside, the most dazzling and rare of the set` |

```text
A five-pointed multiplier star carved from faceted hard candy in bright sky blue and white, with plump rounded points, jewel-like facets, a bright internal glow, a thin polished gold rim around its edge and a few sugar sparkles. Its centre is a smooth, flat, clear area where a number will be printed later: no number, no text. Solid and opaque: the background never shows through it. Straight front view. Match the style, lighting and finish of the attached image. Fills about 80% of the frame. Premium AAA slot game art, stylised photoreal 3D render, ultra glossy, juicy and tactile, rich saturated colours, crisp specular highlights, soft subsurface glow, warm key light from the upper left, cool rim light from the right, tack-sharp focus, high detail. No outline, no cast shadow, no frame. Isolated on a flat, solid, pure magenta (#FF00FF) background: no gradient, no floor, no shadow. Nothing on the object is pink, purple or see-through. The whole object is in frame with a clear margin.
```

---

## Explosion pieces (square 1:1, cutout)

Each symbol shatters into its own pieces, and the game throws them outward with spin and gravity. Every piece must be **separate**, with magenta all around it, so I can cut each one out. **Attach the matching `sym-*` image** to each prompt.

There is one prompt per symbol; only the pieces sentence changes.

| File | Attach | Pieces sentence |
|---|---|---|
| `frag-a-toffee.png` | sym-a | `shards of glossy amber caramel, ragged strips of torn gold foil, the two twisted foil ends, small caramel crumbs` |
| `frag-b-peppermint.png` | sym-b | `jagged shards of red-and-white striped hard candy, the mint leaf, small glassy candy crumbs` |
| `frag-c-gummy-bear.png` | sym-c | `squishy green gummy chunks (an ear, a paw, round blobs) and sugar-crystal clusters` |
| `frag-d-truffle.png` | sym-d | `chunks of dark chocolate oozing ganache, the hazelnut split in two, a scrap of the gold paper cup, the gold-leaf flake, cocoa crumbs` |
| `frag-e-orange.png` | sym-e | `juicy orange segments, a curl of peel, a leaf, round solid juice drops` |
| `frag-f-cherries.png` | sym-f | `the two cherries split open showing their flesh, the stems with the leaf, round solid juice drops` |
| `frag-g-strawberry.png` | sym-g | `strawberry halves and wedges, the leafy crown, round solid juice drops, a few sugar crystals` |
| `frag-h-lemon.png` | sym-h | `lemon wedges, a slice, a curl of peel, the leaf, round solid juice drops` |
| `frag-i-kiwi.png` | sym-i | `kiwi slices and chunks showing green flesh and black seeds, pieces of fuzzy skin, round solid juice drops` |

```text
The same toffee as in the attached image, frozen at the instant it bursts apart into 8 to 10 separate pieces: shards of glossy amber caramel, ragged strips of torn gold foil, the two twisted foil ends, small caramel crumbs. The pieces are spread evenly in a loose ring with clear magenta gaps between every piece; no piece touches another piece or the edge of the image. Every piece is solid and sharp-edged: no motion blur, no mist, no spray, no speed lines. Same materials, colours and lighting as the attached image. Premium AAA slot game art, stylised photoreal 3D render, ultra glossy, juicy and tactile, rich saturated colours, crisp specular highlights, warm key light from the upper left, cool rim light from the right, tack-sharp focus, high detail. No text, no outline, no cast shadow. Isolated on a flat, solid, pure magenta (#FF00FF) background: no gradient, no floor, no shadow. Nothing is pink, purple or see-through.
```
For each other symbol, change "toffee" to that symbol's name and swap in its pieces sentence.

---

## Sugar-frost effects (square 1:1, glow on black)

These are light, so the game adds them on top. Black means "no light". Keep them **centred and fading out before the edges**, except `fx-frost-crust`.

### fx-frost-burst.png · the win explosion
```text
An explosive burst of sparkling sugar frost seen head-on: a blinding white-blue flash at the centre, thousands of glittering ice-sugar crystals blasting outward in every direction, fine frosty streaks radiating like a starburst, a few larger crystalline sugar snowflakes, delicate glitter dust toward the edges. Colours: brilliant white, ice blue and a hint of pale gold. Symmetrical radial composition, centred, fading out well before the edges of the image. A light effect only, glowing, on a pure black (#000000) background with nothing else in the frame. Nothing is cropped by the frame. No text.
```

### fx-frost-ring.png · the shockwave
```text
A thin expanding shockwave ring of frosty light seen straight on: a perfect circle of icy white-blue glitter and tiny sugar crystals, a crisp bright outer edge fading softly inward, the centre empty and black. The ring's diameter is about 80% of the image. A light effect only, glowing, on a pure black (#000000) background with nothing else in the frame. Nothing is cropped by the frame. No text.
```

### fx-frost-crust.png · the freeze before the burst
```text
Sugar frost crystallising across a square pane of glass, seen straight on: intricate white-blue frost ferns and glittering sugar crystals growing inward from all four edges and corners, densest at the edges, thinning toward a nearly clear centre. An even, square, edge-to-edge pattern. Glowing white and ice-blue frost on a pure black (#000000) background. No text.
```

### fx-crystals.png · flying sugar crystals
```text
About 30 separate glowing sugar crystals and glints scattered evenly across the image with clear black space between every one of them: small faceted ice-sugar cubes, tiny diamond-like sparkles and a few soft round glitter dots, in white, ice blue and pale gold, in different sizes. None of them touch. A light effect only, glowing, on a pure black (#000000) background with nothing else in the frame. No text.
```

### fx-twinkle.png · sparkle glint
```text
A single four-pointed sparkle star: a tiny brilliant white core, two long razor-thin rays (one horizontal, one vertical), two shorter diagonal rays and a soft blue-white halo. Centred, with the rays fading out before the edges. A light effect only, glowing, on a pure black (#000000) background with nothing else in the frame. No text.
```

### fx-fireworks.png · big-win celebration
```text
A spectacular celebratory firework burst made of glowing candy-coloured sparks: streaks of orange, gold, cherry red and mint green shooting outward from the centre with glittering trails, small secondary sparkle bursts and falling glitter. Joyful and radial, centred, fading out before the edges. A light effect only, glowing, on a pure black (#000000) background with nothing else in the frame. Nothing is cropped by the frame. No text.
```

### fx-golden-rays.png · scatters and free spins
```text
A radiant burst of warm golden god-rays from a bright white-gold centre: long soft rays evenly spaced all around 360 degrees, a gentle golden haze and a few sparkles. Centred and symmetrical, fading out before the edges. A light effect only, glowing, on a pure black (#000000) background with nothing else in the frame. No text.
```

### fx-comet.png · a multiplier flying to the win · 2:1
```text
A glowing comet streak travelling from left to right: a bright white-gold head with a small starburst, a long sparkling tapering tail of golden light and glitter fading out toward the left. Horizontal, vertically centred, nothing cropped. A light effect only, glowing, on a pure black (#000000) background with nothing else in the frame. No text.
```

---

## Sprinkles and petals (square 1:1, cutout)

### fx-sprinkles.png · attach sym-a
```text
About 40 small separate candy pieces scattered evenly across the image with clear gaps, none touching each other or the edge: candy sprinkles in orange, white, gold and mint green, tiny pearly sugar balls, small star-shaped candies, and curled gold and orange foil confetti, in different sizes and angles. Each piece crisp, glossy and solid. Match the style and lighting of the attached image. Premium AAA slot game art, stylised photoreal 3D render, ultra glossy, rich saturated colours, crisp specular highlights, tack-sharp focus. No text, no cast shadow. Isolated on a flat, solid, pure magenta (#FF00FF) background: no gradient, no floor, no shadow. Nothing is pink, purple or see-through.
```

### fx-blossoms.png · drifting petals by day
```text
About 20 separate orange-blossom petals, small white five-petal blossoms with golden centres and a few small fresh green leaves, scattered evenly across the image with clear gaps, none touching each other or the edge, at varied angles. Softly lit, delicate and natural, slightly glossy. Premium AAA slot game art, stylised photoreal 3D render, tack-sharp focus. No text, no cast shadow. Isolated on a flat, solid, pure magenta (#FF00FF) background: no gradient, no floor, no shadow. Nothing is pink, purple or see-through.
```

---

## Backgrounds (scene)

**Layout:**
- **Landscape:** the reel grid takes the middle, a narrow button column sits on its left, and the logo is in the header.
- **Portrait:** the logo is at the top, the grid in the middle, and the buttons at the bottom.

So keep the **grid area calm and soft**. Spend the detail on the edges, which frame it.

### bg-day-landscape.png · 16:9 · generate first
```text
A breathtaking magical candy orchard at golden hour, seen from a garden path. Graceful trees with glossy caramel-brown bark and lush emerald canopies, their branches heavy with ripe glowing oranges, cherries, lemons and strawberries, and here and there gold-foil toffees and peppermint candies hanging like fruit. Orange blossoms drift through the air; sunbeams stream through the leaves; warm honey-gold light, soft haze and gentle bokeh sparkles. In the distance: rolling green hills, a sparkling stream and a sky of warm peach and gold. Composition for a game screen: the centre (about 60% of the width and 75% of the height) is calm, softly lit and less detailed, because the reel grid sits there; the richest detail and the biggest trees frame the left and right edges; the bottom edge is a soft, darker lawn. Painterly 3D matte painting, cinematic lighting, atmospheric depth, volumetric light, rich detail, premium AAA slot game background. No text, no logo, no characters, no UI. Avoid: lollipops, rainbows, pink cotton-candy clouds, jelly-bean hearts, bananas, grapes, watermelons, plums, apples.
```

### bg-day-portrait.png · 9:16 · attach bg-day-landscape
```text
The same magical candy orchard at golden hour as in the attached image, recomposed for a tall phone screen. The reel grid sits in the middle (about 85% of the width, from 15% to 60% of the height), so that area stays calm and softly lit. Trees and hanging fruit frame the sides and the top corners. The top 12% is open, glowing peach-gold sky, where the logo goes. The bottom 30% is a soft, darker garden foreground with little detail, where the buttons go. Same light, palette and style as the attached image. Painterly 3D matte painting, cinematic lighting, atmospheric depth, volumetric light, rich detail, premium AAA slot game background. No text, no logo, no characters, no UI. Avoid: lollipops, rainbows, pink cotton-candy clouds, jelly-bean hearts, bananas, grapes, watermelons, plums, apples.
```

### bg-night-landscape.png · 16:9 · attach bg-day-landscape
```text
The same candy orchard as in the attached image, with the same composition, now on an enchanted night for the free spins: a deep sapphire and indigo sky full of stars, a large glowing moon, warm paper lanterns strung between the trees, golden fireflies drifting among the branches, and the fruit and candies glistening with frost-like sugar sparkle in the moonlight, with a soft magical teal-and-gold glow among the leaves. The centre stays calm and softly lit for the reel grid, the edges are rich, the bottom edge darker. Painterly 3D matte painting, cinematic lighting, atmospheric depth, volumetric light, rich detail, premium AAA slot game background. No text, no logo, no characters, no UI. Avoid: lollipops, rainbows, pink cotton-candy clouds, jelly-bean hearts, bananas, grapes, watermelons, plums, apples.
```

### bg-night-portrait.png · 9:16 · attach bg-day-portrait and bg-night-landscape
```text
The enchanted night orchard of the second attached image, recomposed exactly like the tall phone layout of the first attached image: the reel grid in the middle (about 85% of the width, from 15% to 60% of the height) stays calm and softly lit; trees, lanterns and fireflies frame the sides and the top corners; the top 12% is open starry sky with the moon, where the logo goes; the bottom 30% is a soft, darker foreground with little detail, where the buttons go. Painterly 3D matte painting, cinematic lighting, atmospheric depth, volumetric light, rich detail, premium AAA slot game background. No text, no logo, no characters, no UI. Avoid: lollipops, rainbows, pink cotton-candy clouds, jelly-bean hearts, bananas, grapes, watermelons, plums, apples.
```

### fg-branch.png · 3:2 · cutout · attach bg-day-landscape
This branch sways in front of the background, at the lower left and mirrored at the right. At night the game darkens it.
```text
A lush flowering branch of the candy orchard from the attached image, sweeping in from the left edge of the image toward the right: glossy dark-green leaves, clusters of white orange blossoms, two ripe oranges and one gold-foil toffee hanging like fruit, three cherries on a twig. The branch enters from the left edge (cut off by the edge on purpose) and ends in open space with leafy tips. Premium AAA slot game art, stylised photoreal 3D render, glossy, rich saturated colours, warm key light from the upper left, tack-sharp focus, high detail. No text, no cast shadow. Isolated on a flat, solid, pure magenta (#FF00FF) background: no gradient, no shadow. Nothing on the branch is pink, purple or see-through.
```

### fg-lantern.png · 2:3 · cutout · attach bg-night-landscape
Lanterns hang in front at night and sway; the game adds their glow.
```text
A warm paper lantern hanging from a short cord, as in the attached image: round and ribbed, made of thin orange paper with polished gold caps at the top and bottom and a small golden tassel below, lit from within by a soft candle glow. The glow stays inside the lantern: no halo or light on the background. Premium AAA slot game art, stylised photoreal 3D render, rich saturated colours, tack-sharp focus, high detail. No text, no cast shadow. Isolated on a flat, solid, pure magenta (#FF00FF) background: no gradient, no shadow. Nothing is pink, purple or see-through.
```

---

## Frame and buttons (cutout) · attach sym-a

### ui-frame.png · 5:4 · ChatGPT recommended
```text
An ornate premium frame around a slot game's reel window, made of polished honey-coloured wood like a luxury fruit crate, trimmed with gold filigree, studded at its four corners with small gold-foil toffees and peppermint candies, with orange-blossom vines and little leaves curling over the two top corners and a small ripe orange with two leaves crowning the top centre. The frame is symmetrical left to right and evenly thick on all four sides. The inner opening is a clean, sharp-cornered rectangle with proportions of exactly 6 wide to 5 high, filled with flat pure magenta (#FF00FF); everything outside the frame is flat pure magenta too. Straight front view, no perspective. Match the style and lighting of the attached image. Premium AAA slot game art, stylised photoreal 3D render, glossy, rich saturated colours, crisp specular highlights, tack-sharp focus, high detail. No text, no cast shadow. Nothing on the frame is pink, purple or see-through.
```

### ui-spin.png · 1:1
```text
A round premium spin button seen straight on: a domed, glossy candy disc in vivid orange with a subtle swirl, a raised rim of sparkling sugar crystals, a polished gold outer ring, and in the centre a bold white circular double-arrow "spin" icon embossed into the candy. Juicy, tactile and irresistible to press. Match the style and lighting of the attached image. Premium AAA slot game art, stylised photoreal 3D render, ultra glossy, rich saturated colours, crisp specular highlights, tack-sharp focus, high detail. No text, no cast shadow. Isolated on a flat, solid, pure magenta (#FF00FF) background: no gradient, no floor, no shadow. Nothing is pink, purple or see-through. The whole button is in frame with a clear margin.
```

### ui-plaque.png · 16:9 · for the free-spins intro, the buy confirmation and the win summary
```text
An ornate horizontal message panel for a slot game: a rounded-rectangle plaque of glossy dark chocolate with a polished gold filigree border, small gold-foil toffees at its four corners and orange-blossom sprigs over the top corners. Its large centre is a smooth, plain, empty chocolate surface, left blank for text. Symmetrical, straight front view. Match the style and lighting of the attached image. Premium AAA slot game art, stylised photoreal 3D render, glossy, rich saturated colours, crisp specular highlights, tack-sharp focus, high detail. No text, no cast shadow. Isolated on a flat, solid, pure magenta (#FF00FF) background: no gradient, no floor, no shadow. Nothing is pink, purple or see-through. The whole panel is in frame with a clear margin.
```

---

## Lettering (cutout) · ChatGPT or Ideogram

Generate `txt-logo` first, then attach it to every banner so the lettering matches. Check the spelling every time.

### txt-logo.png · 2:1 · attach sym-a and sym-e
```text
The game logo in two lines of chunky three-dimensional lettering: "SUGAR" on top, "ORCHARD" below it and larger. "SUGAR" is in sparkling white sugar-frosted letters with a glittering crystal crust and a thin gold edge; "ORCHARD" is in glossy orange candy letters with golden caramel bevels and a few honey drips, and its letter O is a ripe orange with two leaves, like the attached orange. Two small orange blossoms and a gold-foil toffee are tucked around the letters, with sparkles on the highlights. Bold, joyful, premium and perfectly legible. Spell exactly: SUGAR ORCHARD. Premium AAA slot game art, stylised photoreal 3D render, ultra glossy, rich saturated colours, crisp specular highlights, tack-sharp focus. No other text, no cast shadow. Isolated on a flat, solid, pure magenta (#FF00FF) background: no gradient, no shadow. Nothing is pink, purple or see-through. The whole logo is in frame with a clear margin.
```

### Banners · 3:1 · attach txt-logo

There is one prompt per banner; only the words and the material change.

| File | Words | Material sentence |
|---|---|---|
| `txt-big-win.png` | `BIG WIN` | `glossy golden caramel letters with honey drips and bright sparkles` |
| `txt-mega-win.png` | `MEGA WIN` | `glossy cherry-red candy letters with thick polished gold outlines and sparkles` |
| `txt-epic-win.png` | `EPIC WIN` | `emerald hard-candy letters with polished gold outlines and a crust of glittering sugar frost` |
| `txt-max-win.png` | `MAX WIN` | `brilliant diamond-white frosted crystal letters with gold rims, radiant sparkles and tiny flying sugar crystals` |
| `txt-free-spins.png` | `FREE SPINS` | `glossy ice-blue sugar-glass letters with gold rims and small golden stars, opaque` |
| `txt-total-win.png` | `TOTAL WIN` | `glossy golden caramel letters with honey drips and bright sparkles` |

```text
The words "BIG WIN" in chunky three-dimensional lettering on one line: glossy golden caramel letters with honey drips and bright sparkles. The same lettering style, weight and finish as the attached logo. Bold, celebratory, perfectly legible, slightly arched. Spell exactly: BIG WIN. Premium AAA slot game art, stylised photoreal 3D render, ultra glossy, rich saturated colours, crisp specular highlights, tack-sharp focus. No other text, no cast shadow. Isolated on a flat, solid, pure magenta (#FF00FF) background: no gradient, no shadow. Nothing is pink, purple or see-through. The whole word is in frame with a clear margin.
```

---

## Lobby tile (scene)

### key-thumbnail.png · 4:3 · attach sym-s-scatter-basket and bg-day-landscape
The logo is placed on top afterwards; I compose it.
```text
Hero key art for the game's lobby tile: the golden scatter basket from the first attached image in the centre foreground, bursting open, with oranges, cherries, a strawberry, gold-foil toffees and peppermints flying out toward the viewer, a dazzling sugar-frost explosion of glittering ice-sugar crystals behind it, set in the golden-hour candy orchard of the second attached image, shallow depth of field. The top third is calmer and slightly darker, because the logo goes there afterwards. Spectacular, joyful, premium. Painterly 3D, cinematic lighting, volumetric light, rich detail, premium AAA slot game key art. No text, no logo. Avoid: lollipops, rainbows, pink cotton-candy clouds, jelly-bean hearts.
```

---

## Round 4: the Queen Bee, the game's host (cutout, 2:3, transparent)

**Status (2026-11-19): delivered and in the game.**
- **In** `storage/app/art/sugar-orchard/round4/`: all six. `char-queen-idle` came in three variants, and `-b` is the one in the game.
- **Build:** `python -I scripts/art/process-sugar-orchard.py --only-host` builds just her, in a few seconds.
- **Poses vs. size:** the generator drew each pose at its own size and place: raised arms made her smaller, and a lean moved her.
  - The script brings each pose to idle's size and cuts it at the same point below her chin, so her head never changes size when she switches.
  - Each pose's chin, wing root and scale are measured by eye and listed in `HOST_POSES`.
  - A regenerated pose needs those three numbers measured again.

**Who she is:** a glamorous, alluring Queen Bee who presents the game: classy red-carpet glamour, fully clothed. (A cute cartoon bee was the first idea; this replaced it.)

**How the game uses her:**
- **Where she stands:**
  - **Landscape:** above the buy and ante panels. The panels hide her skirt, and her raised hand presents the grid. The art keeps her upper body, fading out at the bottom.
  - **Portrait:** her head and shoulders peek over the frame's top-left corner.
- **How she moves:**
  - she sways gently, and hops at every win;
  - `kiss` as a win starts: she blows her bees out (round 5);
  - `cheer` on a win, as the sweets burst;
  - `wow` with the big-win banners;
  - `honey` for free spins;
  - `waiting` after a minute without a spin, until the next spin.
- **Wings:** a separate image, so the code can flutter them. Draw every pose without wings.

**How to generate:**
- **Tool:** ChatGPT image, one request per image, on a transparent background. Skin tones are close to pink, so magenta keying would eat into them.
- **Order:** `char-queen-idle` first (the character anchor), then the wing, then the poses.
- **References:** each pose attaches `char-queen-idle` for the character and `sym-a-toffee` for the finish.
- **Wings on a pose:** if a pose comes back with wings, regenerate it.
- **What to avoid:** nothing explicit. Image tools refuse it, and regulated operators would reject it (UK ad rules forbid linking gambling to seduction or sexual attractiveness).

### char-queen-idle.png · the character anchor · attach sym-a
```text
An original glamorous Queen Bee character, the queen of a candy orchard: a confident adult woman in her thirties with voluminous glossy chocolate-brown waves and honey-gold highlights, warm amber eyes with long lashes, elegant evening make-up, warm red lips and a knowing smile; a delicate jewelled tiara shaped like two curling bee antennae, each tipped with a glowing amber gem; a white orange blossom pinned in her hair. She wears an elegant fitted floor-length evening gown in liquid honey-gold satin with three wide glossy chocolate-brown bands across the bodice and skirt, a sweetheart neckline, long chocolate-brown satin opera gloves and a slim belt of tiny gold honeycomb links. Classy, alluring red-carpet glamour, fully clothed. Her back is smooth: no wings. Not resembling any real person. Pose: full-length figure floating just above the ground, three-quarter view with her body turned toward the right of the image, one hand on her hip, the other raised in a graceful welcoming gesture, head tilted, giving the viewer a playful wink. Match the finish of the attached image. Fills about 85% of the frame height. Premium AAA slot game art, stylised 3D character render with feature-animation glamour, silky satin and polished gold jewellery, rich saturated colours, crisp specular highlights, soft subsurface glow on the skin, warm key light from the upper left, cool rim light from the right, tack-sharp focus, high detail. Draw her WITHOUT wings: they are a separate image that the game animates. No text, no outline, no cast shadow, no frame. On a transparent background, exported as a PNG with transparency. The whole figure is in frame with a clear margin.
```

### char-queen-wing.png · attach char-queen-idle
One wing; the game mirrors it for the other side and flutters both from the root.
```text
A single wing for the Queen Bee character in the attached image, seen flat from the side: an elegant elongated teardrop shape, translucent like amber stained glass, with fine gold veins in a delicate honeycomb pattern, an iridescent gold-and-champagne sheen, and a few sparkling sugar crystals along its outer edge. Its narrow root sits at the lower-left corner of the image and the wing sweeps up toward the upper right, so it can turn around its root. Regal, delicate, luxurious. Fills about 80% of the frame. Premium AAA slot game art, stylised 3D render, crisp specular highlights, soft glow, tack-sharp focus, high detail. Only the one wing: no character, no second wing, no text, no outline, no cast shadow. On a transparent background, exported as a PNG with transparency.
```

### char-queen-cheer.png · on a win · attach char-queen-idle and sym-a
```text
The same Queen Bee character as in the first attached image, identical face, hair, make-up, tiara, gown and proportions: a confident adult woman with glossy chocolate-brown waves and honey-gold highlights, amber eyes, warm red lips, a jewelled antenna tiara with amber gems, a white orange blossom in her hair, a fitted honey-gold satin evening gown with three wide chocolate-brown bands, long chocolate-brown opera gloves and a gold honeycomb belt. Classy, alluring and fully clothed. Her back is smooth: no wings. Pose: celebrating a win in a graceful twirl, the gown flaring out, both arms raised elegantly, laughing with joy, eyes bright. Full-length figure, three-quarter view, turned toward the right of the image. Match the finish of the second attached image. Fills about 85% of the frame height. Premium AAA slot game art, stylised 3D character render with feature-animation glamour, silky satin and polished gold jewellery, rich saturated colours, crisp specular highlights, soft subsurface glow on the skin, warm key light from the upper left, cool rim light from the right, tack-sharp focus, high detail. Draw her WITHOUT wings: they are a separate image that the game animates. No text, no outline, no cast shadow, no frame. On a transparent background, exported as a PNG with transparency. The whole figure is in frame with a clear margin.
```

### char-queen-wow.png · with the big-win banners · attach char-queen-idle and sym-a
```text
The same Queen Bee character as in the first attached image, identical face, hair, make-up, tiara, gown and proportions: a confident adult woman with glossy chocolate-brown waves and honey-gold highlights, amber eyes, warm red lips, a jewelled antenna tiara with amber gems, a white orange blossom in her hair, a fitted honey-gold satin evening gown with three wide chocolate-brown bands, long chocolate-brown opera gloves and a gold honeycomb belt. Classy, alluring and fully clothed. Her back is smooth: no wings. Pose: dazzled by a huge win, eyes wide and sparkling, lips parted in a delighted gasp, one gloved hand at her cheek, the other flung out in excitement, leaning forward. Full-length figure, three-quarter view, turned toward the right of the image. Match the finish of the second attached image. Fills about 85% of the frame height. Premium AAA slot game art, stylised 3D character render with feature-animation glamour, silky satin and polished gold jewellery, rich saturated colours, crisp specular highlights, soft subsurface glow on the skin, warm key light from the upper left, cool rim light from the right, tack-sharp focus, high detail. Draw her WITHOUT wings: they are a separate image that the game animates. No text, no outline, no cast shadow, no frame. On a transparent background, exported as a PNG with transparency. The whole figure is in frame with a clear margin.
```

### char-queen-honey.png · free spins · attach char-queen-idle and sym-a
```text
The same Queen Bee character as in the first attached image, identical face, hair, make-up, tiara, gown and proportions: a confident adult woman with glossy chocolate-brown waves and honey-gold highlights, amber eyes, warm red lips, a jewelled antenna tiara with amber gems, a white orange blossom in her hair, a fitted honey-gold satin evening gown with three wide chocolate-brown bands, long chocolate-brown opera gloves and a gold honeycomb belt. Classy, alluring and fully clothed. Her back is smooth: no wings. Pose: holding a golden honey dipper like a royal sceptre in one hand and a small jewelled amber honey pot in the other, thick golden honey dripping from the dipper, a regal, inviting smile as if offering the player a treasure. Full-length figure, three-quarter view, turned toward the right of the image. Match the finish of the second attached image. Fills about 85% of the frame height. Premium AAA slot game art, stylised 3D character render with feature-animation glamour, silky satin and polished gold jewellery, rich saturated colours, crisp specular highlights, soft subsurface glow on the skin, warm key light from the upper left, cool rim light from the right, tack-sharp focus, high detail. Draw her WITHOUT wings: they are a separate image that the game animates. No text, no outline, no cast shadow, no frame. On a transparent background, exported as a PNG with transparency. The whole figure is in frame with a clear margin.
```

### char-queen-waiting.png · after a minute without a spin · delivered
```text
The same Queen Bee character as in the first attached image, identical face, hair, make-up, tiara, gown and proportions: a confident adult woman with glossy chocolate-brown waves and honey-gold highlights, amber eyes, warm red lips, a jewelled antenna tiara with amber gems, a white orange blossom in her hair, a fitted honey-gold satin evening gown with three wide chocolate-brown bands, long chocolate-brown opera gloves and a gold honeycomb belt. Classy, alluring and fully clothed. Her back is smooth: no wings. Pose: waiting, teasingly impatient, leaning back slightly in mid-air with one arm folded, a gloved finger of the other hand tapping her chin, one eyebrow raised, a playful half-smile, eyes glancing toward the right. Full-length figure, three-quarter view, turned toward the right of the image. Match the finish of the second attached image. Fills about 85% of the frame height. Premium AAA slot game art, stylised 3D character render with feature-animation glamour, silky satin and polished gold jewellery, rich saturated colours, crisp specular highlights, soft subsurface glow on the skin, warm key light from the upper left, cool rim light from the right, tack-sharp focus, high detail. Draw her WITHOUT wings: they are a separate image that the game animates. No text, no outline, no cast shadow, no frame. On a transparent background, exported as a PNG with transparency. The whole figure is in frame with a clear margin.
```

---

## Round 5: her worker bees (cutout, transparent)

**Status (2026-11-20): delivered and in the game.**
- **In** `storage/app/art/sugar-orchard/round4/` (beside her poses): `char-bee-worker`, `char-bee-wing` and `char-queen-kiss`.
- **Variants kept, not used:** `char-bee-worker-courier` (the bee in a postman's cap with a satchel) and `char-queen-kiss-alt`.
- **Build:** `--only-host` builds them with her. Measured by eye in the build script:
  - `HOST_PALMS`: the palm the bees leave from, per pose (kiss and idle);
  - `BEE_WING_ROOT` and `BEE_WING_LENGTH`: where the wings meet the bee's back, and their size.

**The idea:** she sends her bees to the sweets, so she takes part in every win instead of only reacting to it.

**How the game uses them** (all code; the outcome is already decided before anything moves):
- **A win:** she blows a kiss off her palm (`kiss`), and one bee per winning sweet flies out (up to 9).
  - Each bee lands on its sweet (the sweet gives a little under it) and sits a moment as the frost creeps over it.
  - When the sweet bursts, the screen shakes a little, the amount pops up, the bee zips off with a piece of it, and she cheers.
  - A winning tumble takes about 0.8 s longer when the bees fly, so they can be seen at work (`BEE_PACE` in slot-game.js).
- **Free spins:** bees carry the multiplier stars, with their values, to the middle of the grid. Without bees, the comets do it.
- **A big win:** a ribbon of bees loops around the banner (6 to 12, by tier).
- **Idle:** every 18–34 s, a bee leaves her open hand, nibbles a sweet (it wobbles) and flies home.
- **None:** on a skipped spin, with reduced motion, or in a theme without the bee art.

**How to generate:** the same as round 4. Draw the bee without wings: the game buzzes a separate wing, root at the lower left like hers.

### char-bee-worker.png · 1:1 · attach char-queen-idle and sym-a
```text
A tiny royal worker bee, a courier of the Queen Bee in the first attached image: a small, round, chubby bee with a glossy body striped in honey-gold and chocolate-brown like her gown, big friendly dark eyes with a bright catchlight, a cheerful determined smile, two short curling antennae each tipped with a tiny glowing amber gem like her tiara, and a tiny chocolate-brown satin bow tie. Seen from the side in flight, heading toward the right of the image, body tilted slightly forward, tiny legs tucked in. A bold, simple silhouette that still reads when shown very small. Match the finish of the second attached image. Fills about 75% of the frame. Premium AAA slot game art, stylised 3D render, glossy surfaces, rich saturated colours, crisp specular highlights, warm key light from the upper left, cool rim light from the right, tack-sharp focus, high detail. Draw it WITHOUT wings: they are a separate image that the game animates. No text, no outline, no cast shadow, no frame. On a transparent background, exported as a PNG with transparency. The whole bee is in frame with a clear margin.
```

### char-bee-wing.png · 1:1 · attach char-bee-worker
```text
A single wing for the little bee in the attached image, seen flat from the side: a short, rounded teardrop, clear and glassy like spun sugar, with a soft gold-and-champagne iridescent sheen and a few fine gold veins. Its narrow root sits at the lower-left corner of the image and the wing sweeps up toward the upper right, so it can turn around its root. Fills about 80% of the frame. Premium AAA slot game art, stylised 3D render, crisp specular highlights, soft glow, tack-sharp focus. Only the one wing: no bee, no second wing, no text, no outline, no cast shadow. On a transparent background, exported as a PNG with transparency.
```

### char-queen-kiss.png · 2:3 · on a win, sending her bees · attach char-queen-idle and sym-a
```text
The same Queen Bee character as in the first attached image, identical face, hair, make-up, tiara, gown and proportions: a confident adult woman with glossy chocolate-brown waves and honey-gold highlights, amber eyes, warm red lips, a jewelled antenna tiara with amber gems, a white orange blossom in her hair, a fitted honey-gold satin evening gown with three wide chocolate-brown bands, long chocolate-brown opera gloves and a gold honeycomb belt. Classy, alluring and fully clothed. Her back is smooth: no wings. Pose: blowing a kiss toward the right of the image to send her bees out: one gloved hand raised just in front of her lips, palm up and open, as if blowing the kiss off her palm; lips softly pursed, one eye closed in a playful wink, the other hand resting on her hip, chin slightly raised. Keep the raised hand close to her face, not stretched out. Full-length figure, three-quarter view, turned toward the right of the image. Match the finish of the second attached image. Fills about 85% of the frame height. Premium AAA slot game art, stylised 3D character render with feature-animation glamour, silky satin and polished gold jewellery, rich saturated colours, crisp specular highlights, soft subsurface glow on the skin, warm key light from the upper left, cool rim light from the right, tack-sharp focus, high detail. Draw her WITHOUT wings: they are a separate image that the game animates. No text, no outline, no cast shadow, no frame. On a transparent background, exported as a PNG with transparency. The whole figure is in frame with a clear margin.
```
The hand stays near her face on purpose: an outstretched arm would reach into the grid on wide screens.

---

## What I do when the art arrives

- **Key and cut:**
  - Cutouts are keyed out.
  - Fragment and particle sheets are split into single pieces.
  - Everything is packed as WebP into `public/images/sugar-orchard/` by `scripts/art/process-sugar-orchard.py`.
- **The win sequence**, in a particle layer: frost-over, burst, ring, shatter with spin and gravity, crystals and sparkles, then the tumble.
- **Celebrations:**
  - fireworks and banners for big wins;
  - golden rays and the basket's pulse for scatters;
  - her bees (round 5) or comets carrying the multipliers to the win;
  - caramel flames swirling around the winning sweets of a tumble that pays 2× the bet or more, bigger from 10× (drawn by code, no art).
- **Backgrounds:** layered and alive, with swaying branches and lanterns, drifting petals by day and fireflies by night.
- **Thumbnail:** I compose the lobby thumbnail from `key-thumbnail` and the logo.
- **Stays in code:** the grid cells, the bet bar, numbers and counters stay crisp and readable.
