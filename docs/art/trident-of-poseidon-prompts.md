# Trident of Poseidon: art prompts (round 1)

Audience: whoever generates Trident of Poseidon's art. Every prompt below is complete; copy it as is.

**Name:** Trident of Poseidon (code `trident-of-poseidon`), picked by the user on 2026-11-20. It was "Pearls of Poseidon" before; the art doesn't change, only `txt-logo` carries the name.

**Status (2026-11-20):** generation has started.

## The look in one paragraph

The game is set **underwater, in Poseidon's own sea**. Base game: sunlit turquoise shallows above a sunken city of bronze, gold and mother-of-pearl, with coral, kelp, fish and light shafts from the surface. Free spins: the same place in **the Abyss**, at midnight depth, lit only by glowing coral, jellyfish and the palace's aquamarine light. Poseidon rises from a swirling column of seawater beside the grid. The materials are **polished gold, verdigris bronze, mother-of-pearl, red coral and glowing aquamarine water**. Multipliers are **pearls in open clam shells**; in free spins they feed **the Tide**, a glowing whirlpool that holds the running multiplier.

## How the art becomes the game

**A win:**
1. Poseidon points at the grid (`char-poseidon-point`), and the winning symbols are caught in a small glowing whirlpool (`fx-whirlpool`).
2. Each one dissolves into water. Code breaks the symbol's own pixels into coloured droplets, so no explosion pieces are needed. A splash crown and a ripple ring go with it (`fx-splash-burst`, `fx-splash-ring`), then droplets and bubbles (`fx-droplets`, `fx-bubbles`).
3. New symbols sink in from above, with a little drift, as they would through water.

**The pearls:**
1. A pearl lands in its clam (`sym-m-pearl-*`); the game prints the value on the pearl.
2. When a winning tumble chain ends, Poseidon strikes (`char-poseidon-strike`). A jet of water runs from his trident to each pearl (`fx-water-jet`).
3. The pearls fly to the win. In free spins they fly into the Tide (`ui-tide-ring` + `fx-whirlpool`), which grows brighter as its total climbs.

**Celebrations:**
- **Big wins:** a surge of water sweeps across the screen (`fx-surge`), dolphins circle the banner (`char-dolphin`), and gold coins and pearls rain down (`fx-treasure`). Poseidon roars (`char-poseidon-roar`).
- **Free spins:** Poseidon summons (`char-poseidon-summon`), a whirlpool opens across the screen, and the scene sinks into the Abyss.
- **Scatters:** aqua god-rays (`fx-aqua-rays`) and a ring pulse on each one that lands.
- **The scene is alive:** light shafts sway (`fx-sunrays`), kelp sways in front (`fg-kelp`), schools of fish cross (`fx-fish`), bubbles rise, and jellyfish drift in the Abyss (`fg-jelly`). Code draws the moving caustic light over everything.

**What the code does and what the art does:** the animation is code; the art supplies the pieces it animates (layered stills, particles and shaders). Image models can't draw a consistent frame-by-frame sequence, so **never ask for sprite sheets or animation frames**.

## Rules (read before generating)

1. **One prompt per request.** Never ask for several assets at once; tools then fall back to flat vector art.
2. **Set the style first.** Generate `sym-a-hippocampus` until you love it, then attach it as a style reference to every other symbol, pearl, UI and effect sheet. Generate `char-poseidon-idle` until you love him, then attach him to every other pose. Each prompt says what to attach. In Midjourney, use `--sref <image url>` for style and `--cref <image url>` for the character.
3. **Four kinds of canvas.** Each prompt ends with one of these:
   - **Transparent** (the default for this game): a real transparent PNG. ChatGPT image can export one. Water, pearls and foam are partly see-through, and magenta keying would eat into them.
     - If your tool can't export transparency, replace the last sentence with: *"Isolated on a flat, solid, pure magenta (#FF00FF) background: no gradient, no floor, no shadow. Nothing on the object is pink, purple or see-through."*
   - **Magenta:** flat pure `#FF00FF`, only for the frame, whose window the script finds by its magenta.
   - **Glow:** pure black `#000000`. These are light effects; the game adds them as light, so black disappears and the glow stays soft. Never on magenta or transparent.
   - **Scene:** a full picture.
4. **Size and format:** the largest size the tool offers, as PNG. Backgrounds at least 2560×1440 (or 1440×2560); upscale if needed.
5. **Text:** only the `txt-*` assets contain text. Check every letter, and regenerate until the spelling is exact.
6. **Delivery:** use the file names below, in `C:\Git\Orange\storage\app\art\trident-of-poseidon\round1\`. The earlier folder, `storage\app\art\pearls-of-poseidon\round1\`, works too: the build script reads both.
   - Send 2–3 variants when unsure (`-a`, `-b`, `-c`); I pick and key the best one.
   - Batches are fine. Symbols, Poseidon and the backgrounds first already change the whole look.
7. **Tools:**
   - **ChatGPT image:** best for transparency, text, layouts and the character. Use it for `char-*`, `txt-*`, `ui-*`, symbols and pearls.
   - **Midjourney:** richest for backgrounds and light effects. Add `--ar 16:9` / `--ar 9:16` / `--ar 1:1`, and move each prompt's "Avoid:" list into `--no`.
8. **Keep it ours.** This game must not look like any other slot. Never generate:
   - lightning, thunderbolts, storm clouds or a sky full of clouds;
   - white marble columns, temples floating in the sky, or golden gates;
   - faceted cut gemstones as symbols, or crowns, hourglasses, rings or chalices as symbols;
   - coloured glass orbs or balls as multipliers;
   - a god's face or head as a symbol.

   Poseidon must not resemble any existing film, cartoon, comic or game character: **no mermaid fish tail, no spiked gold crown, no armoured superhero suit**, and not any real actor.

### Order

1. `sym-a-hippocampus`: the style anchor.
2. The other symbols: `sym-b` … `sym-i`, `sym-s`, then the four `sym-m-pearl-*`.
3. `char-poseidon-idle`: the character anchor. Then the other poses and `char-dolphin`.
4. `bg-day-landscape`, then the other three backgrounds.
5. `ui-frame`, `ui-spin`, `ui-plaque`, `ui-tide-ring`.
6. The banners, then `fx-*`.
7. `fg-*`, `fx-fish`, `fx-treasure`, `fx-surge`.
8. `key-thumbnail`.
9. `txt-logo`.

---

## Symbols (square 1:1, transparent)

The grid shows them at about 60–120 px, so each needs a bold, instantly readable silhouette and its own dominant colour:

| Symbol | Rank | Dominant colour |
|---|---|---|
| A, hippocampus | top | gold |
| B, sea-war helmet | high | bronze and coral red |
| C, conch horn | high | ivory and burnt orange |
| D, amphora | high | lapis blue and gold |
| E, crab | low | scarlet |
| F, pufferfish | low | golden yellow |
| G, sea turtle | low | emerald |
| H, starfish | low | vivid orange |
| I, nautilus | low | turquoise and pearl white |

The pieces of a winning symbol are cut from its own pixels by code, so **every symbol must be solid and opaque**, with crisp edges.

### sym-a-hippocampus.png · top symbol · generate first
```text
A majestic golden hippocampus statuette, the mythical sea-horse of the Greek sea god: the front half a rearing horse whose flowing mane curls like breaking waves and whose forelegs end in webbed fins, the back half a long coiled fish tail covered in gleaming scales, ending in a wide fanned tail fin. Cast in mirror-bright polished gold, with glowing aquamarine eyes, a row of small lustrous white pearls set along its mane, beads of seawater glistening on the metal, and one bright star-shaped glint on the top highlight. Rearing upward in a proud three-quarter view, dynamic and powerful: the most valuable symbol of the set. Fills about 80% of the frame. Premium AAA slot game art, stylised photoreal 3D render, glossy and tactile, rich saturated colours, crisp specular highlights, soft subsurface glow, cool aquamarine key light from the upper left as if sunlight is filtering through clear water, delicate caustic light ripples across the surfaces, a warm gold rim light from the right, tack-sharp focus, high detail. No text, no outline, no cast shadow, no frame. On a transparent background, exported as a PNG with transparency. The whole object is in frame with a clear margin.
```

### sym-b-helmet.png · attach sym-a
```text
An ancient sea-war helmet in the classical Greek style, made of polished bronze with a rich verdigris-teal patina in its recesses: cheek guards shaped like curved fish fins, a tall sweeping crest of bright red branching coral instead of horsehair, a gold band of running-wave scrollwork around the brow, and a small gold trident emblem on the forehead. Beads of seawater sit on the metal. Three-quarter view facing left, empty eye openings dark. Bold and noble. Match the style, lighting and finish of the attached image. Fills about 80% of the frame. Premium AAA slot game art, stylised photoreal 3D render, glossy and tactile, rich saturated colours, crisp specular highlights, soft subsurface glow, cool aquamarine key light from the upper left as if sunlight is filtering through clear water, delicate caustic light ripples across the surfaces, a warm gold rim light from the right, tack-sharp focus, high detail. No text, no outline, no cast shadow, no frame. On a transparent background, exported as a PNG with transparency. The whole object is in frame with a clear margin.
```

### sym-c-conch.png · attach sym-a
```text
A large spiral conch-shell war horn: a glossy ivory-white shell with bold burnt-orange and caramel bands spiralling to its pointed tip, smooth as porcelain, its wide flared opening lined with warm apricot and gold mother-of-pearl, bound by two polished gold bands engraved with waves, a gold mouthpiece at the tip, and a short braided sea-blue cord ending in a pearl tassel. Three-quarter view with the opening toward the viewer. The colours are ivory, orange, apricot and gold, never pink. Match the style, lighting and finish of the attached image. Fills about 80% of the frame. Premium AAA slot game art, stylised photoreal 3D render, glossy and tactile, rich saturated colours, crisp specular highlights, soft subsurface glow, cool aquamarine key light from the upper left as if sunlight is filtering through clear water, delicate caustic light ripples across the surfaces, a warm gold rim light from the right, tack-sharp focus, high detail. No text, no outline, no cast shadow, no frame. On a transparent background, exported as a PNG with transparency. The whole object is in frame with a clear margin.
```

### sym-d-amphora.png · attach sym-a
```text
A sealed ancient amphora glazed in deep glossy lapis blue: two elegant gold handles shaped like leaping dolphins, a gold Greek running-wave frieze around its belly, gold bands at its neck, and its mouth sealed with a disc of gold wax stamped with a trident. A few small barnacles and a sprig of red coral cling to its base, and droplets of water bead on the glaze. Slightly tilted three-quarter view. Rich and treasured. Match the style, lighting and finish of the attached image. Fills about 80% of the frame. Premium AAA slot game art, stylised photoreal 3D render, glossy and tactile, rich saturated colours, crisp specular highlights, soft subsurface glow, cool aquamarine key light from the upper left as if sunlight is filtering through clear water, delicate caustic light ripples across the surfaces, a warm gold rim light from the right, tack-sharp focus, high detail. No text, no outline, no cast shadow, no frame. On a transparent background, exported as a PNG with transparency. The whole object is in frame with a clear margin.
```

### sym-e-crab.png · attach sym-a
```text
A bold scarlet-red crab seen from the front and slightly above, both big claws raised high and open, its glossy armoured shell lacquered deep scarlet with a subtle gold sheen along the ridges and tiny cream-coloured spikes at its edges, small black eyes on short stalks, legs spread wide. Stylised but naturalistic, like a precious figurine: no cartoon face, no smile, no mouth expression. Beads of seawater on the shell. Match the style, lighting and finish of the attached image. Fills about 80% of the frame. Premium AAA slot game art, stylised photoreal 3D render, glossy and tactile, rich saturated colours, crisp specular highlights, soft subsurface glow, cool aquamarine key light from the upper left as if sunlight is filtering through clear water, delicate caustic light ripples across the surfaces, a warm gold rim light from the right, tack-sharp focus, high detail. No text, no outline, no cast shadow, no frame. On a transparent background, exported as a PNG with transparency. The whole object is in frame with a clear margin.
```

### sym-f-pufferfish.png · attach sym-a
```text
A golden-yellow pufferfish fully puffed up into a near-perfect ball, its skin covered in short soft spines and faint gold spots, a pale cream belly, big glossy dark eyes, small translucent-looking but opaque yellow fins and a little tail fin. Three-quarter view, round and instantly recognisable. Stylised but naturalistic: no cartoon face, no smile. Match the style, lighting and finish of the attached image. Fills about 80% of the frame. Premium AAA slot game art, stylised photoreal 3D render, glossy and tactile, rich saturated colours, crisp specular highlights, soft subsurface glow, cool aquamarine key light from the upper left as if sunlight is filtering through clear water, delicate caustic light ripples across the surfaces, a warm gold rim light from the right, tack-sharp focus, high detail. No text, no outline, no cast shadow, no frame. On a transparent background, exported as a PNG with transparency. The whole object is in frame with a clear margin.
```

### sym-g-turtle.png · attach sym-a
```text
A green sea turtle swimming toward the viewer in a three-quarter view, its front flippers spread wide like wings, its domed shell a mosaic of glossy emerald and jade plates edged with fine gold lines, its head and flippers patterned with olive and cream scales, dark calm eyes. Graceful and serene. Stylised but naturalistic: no cartoon face, no smile. Match the style, lighting and finish of the attached image. Fills about 80% of the frame. Premium AAA slot game art, stylised photoreal 3D render, glossy and tactile, rich saturated colours, crisp specular highlights, soft subsurface glow, cool aquamarine key light from the upper left as if sunlight is filtering through clear water, delicate caustic light ripples across the surfaces, a warm gold rim light from the right, tack-sharp focus, high detail. No text, no outline, no cast shadow, no frame. On a transparent background, exported as a PNG with transparency. The whole object is in frame with a clear margin.
```

### sym-h-starfish.png · attach sym-a
```text
A plump five-armed starfish in vivid saturated orange, its surface covered in neat rows of rounded cream and gold bumps, the tips of its arms curling gently upward, wet and glossy. Seen from the front and slightly tilted, a strong clean star silhouette. Naturalistic: no face. The orange is a pure warm orange, never pink or red. Match the style, lighting and finish of the attached image. Fills about 80% of the frame. Premium AAA slot game art, stylised photoreal 3D render, glossy and tactile, rich saturated colours, crisp specular highlights, soft subsurface glow, cool aquamarine key light from the upper left as if sunlight is filtering through clear water, delicate caustic light ripples across the surfaces, a warm gold rim light from the right, tack-sharp focus, high detail. No text, no outline, no cast shadow, no frame. On a transparent background, exported as a PNG with transparency. The whole object is in frame with a clear margin.
```

### sym-i-nautilus.png · attach sym-a
```text
A chambered nautilus shell in a three-quarter side view: its smooth spiral shell glossy pearl-white with bold turquoise and teal flame stripes sweeping across it, its opening lined with shimmering silver-and-aqua mother-of-pearl, a thin gold rim along the lip. Elegant, clean and luminous. Solid and opaque. Match the style, lighting and finish of the attached image. Fills about 80% of the frame. Premium AAA slot game art, stylised photoreal 3D render, glossy and tactile, rich saturated colours, crisp specular highlights, soft subsurface glow, cool aquamarine key light from the upper left as if sunlight is filtering through clear water, delicate caustic light ripples across the surfaces, a warm gold rim light from the right, tack-sharp focus, high detail. No text, no outline, no cast shadow, no frame. On a transparent background, exported as a PNG with transparency. The whole object is in frame with a clear margin.
```

### sym-s-trident.png · scatter · attach sym-a
The page adds the word SCATTER over it, as text, so the bottom fifth should stay quiet.
```text
The scatter symbol: Poseidon's golden trident medallion. A round medallion of polished gold whose thick rim is engraved with a running-wave pattern and studded with small lustrous pearls; inside the rim, a swirling whirlpool of glowing turquoise water spirals toward the centre; standing upright in front of it, a gleaming gold trident whose three prong tips blaze with bright aquamarine light, with sea foam and droplets bursting around the prongs. Clearly the most special and magical symbol: bigger, brighter and more radiant than any other. Straight front view; the bottom fifth of the medallion is calm. Match the style, lighting and finish of the attached image. Fills about 85% of the frame. Premium AAA slot game art, stylised photoreal 3D render, glossy and tactile, rich saturated colours, crisp specular highlights, soft subsurface glow, cool aquamarine key light from the upper left as if sunlight is filtering through clear water, delicate caustic light ripples across the surfaces, a warm gold rim light from the right, tack-sharp focus, high detail. No text, no outline, no cast shadow, no frame. On a transparent background, exported as a PNG with transparency. The whole object is in frame with a clear margin.
```

### Multiplier pearls (4 colours by value) · attach sym-a

The game prints the value (2× up to 500×) on the pearl, so the pearl must be big, round and plain. There is one prompt per colour; only the colour sentence changes.

| File | Values | Colour sentence |
|---|---|---|
| `sym-m-pearl-moon.png` | 2×–5× | `a moon-white pearl with a soft silver sheen, resting in a cream and pale-gold clam shell` |
| `sym-m-pearl-aqua.png` | 6×–15× | `an aquamarine-blue pearl with a turquoise inner glow, resting in a sea-green and gold clam shell` |
| `sym-m-pearl-gold.png` | 20×–50× | `a molten-gold pearl with a warm inner glow, resting in a polished bronze clam shell with a bright gold rim` |
| `sym-m-pearl-abyss.png` | 100×–500× | `a black pearl with a peacock-green and teal iridescent sheen and a glowing aqua aura, resting in a dark bronze clam shell rimmed with gold and dotted with tiny glowing turquoise lights, the most dazzling and rare of the set` |

```text
A multiplier symbol: a moon-white pearl with a soft silver sheen, resting in a cream and pale-gold clam shell. The clam is wide open, its fluted lower half cradling the pearl and its upper half raised behind it like a fan. The pearl is very large, perfectly round and smooth, filling the middle of the image, with a clean bright area on its front where a number will be printed later: no number, no text. Lustrous, precious and inviting. Straight front view. Match the style, lighting and finish of the attached image. Fills about 80% of the frame. Premium AAA slot game art, stylised photoreal 3D render, glossy and tactile, rich saturated colours, crisp specular highlights, soft subsurface glow, cool aquamarine key light from the upper left as if sunlight is filtering through clear water, delicate caustic light ripples across the surfaces, a warm gold rim light from the right, tack-sharp focus, high detail. No outline, no cast shadow, no frame. On a transparent background, exported as a PNG with transparency. The whole object is in frame with a clear margin.
```

---

## Poseidon, the game's host (2:3, transparent)

**Who he is:** an original Poseidon, god of the sea: majestic, powerful and on the player's side. He looks about fifty, not old. Storm-grey hair streaked with deep teal drifts as if underwater. He wears a crown of red coral and mother-of-pearl, a bronze fish-scale pauldron and a sea-blue cloak, and carries a bronze-and-gold trident. **From the waist down he is a swirling column of seawater**, never legs and never a fish tail.

**How the game uses him:**
- **Landscape:** above the buy and ante panels on the left, facing the grid. The script keeps his upper body and fades the water column at the bottom into the panels.
- **Portrait:** his head, shoulders and trident rise over the frame's top-left corner.
- **Moves:**
  - `idle`: he breathes, and his hair and cloak drift (code warps them gently, and caustic light plays over him);
  - `point` on a win;
  - `strike` when the pearls pay: the water jet starts at his trident's prong tips;
  - `roar` with the big-win banners;
  - `summon` for free spins;
  - `waiting` after a minute without a spin.
- **Per-pose measurements:** I measure each pose's chin, prong tips and scale, so his head never jumps between poses. A regenerated pose needs measuring again.

**How to generate:** ChatGPT image, one request per image, transparent background. `char-poseidon-idle` first, then attach it to every other pose so his face, crown, hair, cloak and trident stay identical. Regenerate a pose until it's the same character.

### char-poseidon-idle.png · the character anchor · attach sym-a
```text
An original Poseidon, ancient god of the sea, as the host of a premium slot game. A towering, powerful man who looks about fifty: a broad, noble, weathered face, deep-set eyes that glow aquamarine, strong brows and a calm, commanding half-smile. His long thick hair and full beard are storm-grey streaked with deep teal, flowing upward and sideways as if drifting in water, with small gold rings and tiny pearls braided in at the temples. He wears a crown of branching red coral and mother-of-pearl spikes with one large lustrous white pearl at its centre front. He is bare-chested and strongly muscled, with faint glowing turquoise wave-shaped markings tracing his forearms and collarbones. On his left shoulder a pauldron of overlapping verdigris-bronze fish scales edged in gold holds a long sea-blue cloak with a gold running-wave border, billowing as if underwater. Gold armbands shaped like coiled sea serpents circle his upper arms. In his right hand he holds upright a tall bronze-and-gold trident with a sea serpent coiled around its shaft and three prong tips glowing aquamarine. From the waist down his body becomes a swirling column of churning turquoise seawater and white foam: no legs, no fish tail. Pose: waist-up figure rising from the water column, three-quarter view with his body turned toward the right of the image, his left hand open and extended toward the right in a welcoming, presenting gesture, looking at the viewer. Majestic, awe-inspiring and benevolent. Not resembling any real person or any existing film, cartoon, comic or game character. Match the finish of the attached image. Fills about 90% of the frame height. Premium AAA slot game art, stylised 3D character render with feature-film quality, realistic skin with soft subsurface scattering, glossy metal and coral, rich saturated colours, crisp specular highlights, cool aquamarine key light from the upper left as if sunlight is filtering through water, soft caustic light ripples on his skin, a warm gold rim light from the right, tack-sharp focus, high detail. No text, no outline, no cast shadow, no frame. On a transparent background, exported as a PNG with transparency. The whole figure, the crown and the trident's tips are in frame with a clear margin.
```

### char-poseidon-point.png · on a win · attach char-poseidon-idle and sym-a
```text
The same Poseidon as in the first attached image, identical face, crown, hair, beard, markings, pauldron, cloak, armbands, trident and water column. New pose: the trident rests against his right shoulder; his left arm is stretched out toward the right of the image, index finger pointing, as if commanding the sea there; a confident, satisfied grin. Hair and cloak drift as if underwater. Waist-up, three-quarter view turned toward the right, the same size and position in the frame as the attached image. Match the finish of the second attached image. Premium AAA slot game art, stylised 3D character render with feature-film quality, rich saturated colours, crisp specular highlights, cool aquamarine key light from the upper left, soft caustic light on his skin, warm gold rim light from the right, tack-sharp focus, high detail. No text, no outline, no cast shadow, no frame. On a transparent background, exported as a PNG with transparency. The whole figure, the crown and the trident's tips are in frame with a clear margin.
```

### char-poseidon-strike.png · when the pearls pay · attach char-poseidon-idle and sym-a
```text
The same Poseidon as in the first attached image, identical face, crown, hair, beard, markings, pauldron, cloak, armbands, trident and water column. New pose: he thrusts the trident forward with both hands, its prongs pointing toward the right of the image and slightly downward, the three prong tips blazing bright aquamarine, a small burst of white water spraying off them; his hair, beard and cloak are swept back by the force; a fierce, focused expression. Waist-up, three-quarter view turned toward the right, the same size and position in the frame as the attached image. Match the finish of the second attached image. Premium AAA slot game art, stylised 3D character render with feature-film quality, rich saturated colours, crisp specular highlights, cool aquamarine key light from the upper left, soft caustic light on his skin, warm gold rim light from the right, tack-sharp focus, high detail. No text, no outline, no cast shadow, no frame. On a transparent background, exported as a PNG with transparency. The whole figure, the crown and the trident's tips are in frame with a clear margin.
```

### char-poseidon-roar.png · with the big-win banners · attach char-poseidon-idle and sym-a
```text
The same Poseidon as in the first attached image, identical face, crown, hair, beard, markings, pauldron, cloak, armbands, trident and water column. New pose: triumphant, he raises the trident high above his head in his right hand, his left fist clenched at his chest, head thrown back in a mighty roar of victory, eyes blazing aquamarine, hair and beard flying, white water erupting upward from the column around him. Waist-up, three-quarter view turned toward the right, the same size of head and body as the attached image. Match the finish of the second attached image. Premium AAA slot game art, stylised 3D character render with feature-film quality, rich saturated colours, crisp specular highlights, cool aquamarine key light from the upper left, soft caustic light on his skin, warm gold rim light from the right, tack-sharp focus, high detail. No text, no outline, no cast shadow, no frame. On a transparent background, exported as a PNG with transparency. The whole figure, the crown and the trident's tips are in frame with a clear margin.
```

### char-poseidon-summon.png · free spins · attach char-poseidon-idle and sym-a
```text
The same Poseidon as in the first attached image, identical face, crown, hair, beard, markings, pauldron, cloak, armbands, trident and water column. New pose: he summons the deep: both arms spread wide, his left palm open and facing up, the trident held out to the side in his right hand, his eyes glowing intensely white-aqua, the turquoise markings on his arms blazing, and ribbons of glowing water spiralling up around his arms and shoulders. Powerful and mystical. Waist-up, facing the viewer with a slight turn toward the right, the same size of head and body as the attached image. Match the finish of the second attached image. Premium AAA slot game art, stylised 3D character render with feature-film quality, rich saturated colours, crisp specular highlights, cool aquamarine key light from the upper left, soft caustic light on his skin, warm gold rim light from the right, tack-sharp focus, high detail. No text, no outline, no cast shadow, no frame. On a transparent background, exported as a PNG with transparency. The whole figure, the crown and the trident's tips are in frame with a clear margin.
```

### char-poseidon-waiting.png · after a minute without a spin · attach char-poseidon-idle and sym-a
```text
The same Poseidon as in the first attached image, identical face, crown, hair, beard, markings, pauldron, cloak, armbands, trident and water column. New pose: patient and amused, he leans on the upright trident with both forearms crossed over its shaft, one eyebrow raised, a knowing, slightly teasing smile, looking straight at the viewer as if to say "your move". Hair and cloak drift gently. Waist-up, three-quarter view turned toward the right, the same size and position in the frame as the attached image. Match the finish of the second attached image. Premium AAA slot game art, stylised 3D character render with feature-film quality, rich saturated colours, crisp specular highlights, cool aquamarine key light from the upper left, soft caustic light on his skin, warm gold rim light from the right, tack-sharp focus, high detail. No text, no outline, no cast shadow, no frame. On a transparent background, exported as a PNG with transparency. The whole figure, the crown and the trident's tips are in frame with a clear margin.
```

### char-dolphin.png · 3:2 · his messenger · attach char-poseidon-idle and sym-a
The game flips and scales it. A pod circles the big-win banner, one leaps through at the free-spins intro, and now and then one swims past while the game is idle.
```text
A sleek, graceful bottlenose dolphin swimming from left to right in a gentle upward arc, seen from the side: glossy blue-grey back fading to a pale belly, faint glowing turquoise wave markings along its flank like the ones on Poseidon's arms in the attached image, a thin gold band around its dorsal fin, a calm intelligent eye. A few small bubbles trail from its tail. Naturalistic and noble: no cartoon face, no smile. Match the finish of the second attached image. Fills about 85% of the frame width. Premium AAA slot game art, stylised photoreal 3D render, glossy, rich saturated colours, crisp specular highlights, cool aquamarine key light from the upper left, warm gold rim light, tack-sharp focus, high detail. No text, no outline, no cast shadow, no frame. On a transparent background, exported as a PNG with transparency. The whole dolphin is in frame with a clear margin.
```

---

## Backgrounds (scene)

**Layout:**
- **Landscape:** the reel grid takes the middle. On the left, Poseidon stands above a narrow column of buttons, and the logo is in the header.
- **Portrait:** the logo is at the top, the grid in the middle, and the buttons at the bottom.

So keep the **grid area calm and soft**. Spend the detail on the edges, which frame it. Code adds the moving caustic light, the bubbles and the fish, so leave space for them: no big fish or creatures in the centre.

### bg-day-landscape.png · 16:9 · base game · generate first
```text
The sunlit shallows of the Greek sea god's realm, seen underwater: brilliant clear turquoise water, long slanting shafts of sunlight pouring down from the rippling silver surface at the top of the image, a sea floor of pale golden sand with soft rippled patterns of caustic light. Framing the left and right edges, tall formations of vivid orange, coral-red and gold coral, swaying sea fans, tall kelp, and the half-buried remains of a sunken city of bronze, gold and mother-of-pearl: a huge open clam shell, toppled bronze statues overgrown with coral, scattered gold coins, amphorae half in the sand, the arch of a mother-of-pearl gateway. In the far distance, through soft blue haze, the faint silhouette of a majestic undersea palace with mother-of-pearl domes and a tall spire shaped like a trident. Small silver fish near the edges, a few rising bubbles. Composition for a game screen: the centre (about 60% of the width and 75% of the height) is calm, open blue water, softly lit and with little detail, because the reel grid sits there; the richest detail frames the left and right edges; the bottom edge is a soft, darker sea floor. Painterly 3D matte painting, cinematic lighting, atmospheric depth, volumetric light, rich detail, premium AAA slot game background. No text, no logo, no characters, no UI. Avoid: lightning, clouds, sky, white marble columns, temples in the sky, gemstones, mermaids, sharks.
```

### bg-day-portrait.png · 9:16 · attach bg-day-landscape
```text
The same sunlit underwater realm as in the attached image, recomposed for a tall phone screen. The reel grid sits in the middle (about 85% of the width, from 15% to 60% of the height), so that area stays calm, open turquoise water, softly lit. Coral, kelp and the sunken bronze-and-pearl ruins frame the sides; the top 12% is the bright rippling water surface with sun shafts pouring down, where the logo goes; the bottom 30% is a soft, darker sandy sea floor with little detail, where the buttons go. Same light, palette and style as the attached image. Painterly 3D matte painting, cinematic lighting, atmospheric depth, volumetric light, rich detail, premium AAA slot game background. No text, no logo, no characters, no UI. Avoid: lightning, clouds, sky, white marble columns, temples in the sky, gemstones, mermaids, sharks.
```

### bg-night-landscape.png · 16:9 · free spins, the Abyss · attach bg-day-landscape
```text
The same underwater place as in the attached image, with the same composition, now in the Abyss for the free spins: the water is inky navy and deep black-teal, no sunlight from above. The coral on both edges glows with bioluminescence in turquoise, electric teal and warm gold; glowing jellyfish drift among the ruins; tiny glowing plankton float everywhere like stars. In the distance the undersea palace is lit from within, aquamarine light pouring from its windows and its trident spire, and a vast slow whirlpool of faint glowing water turns above it. Mysterious, powerful and magical, with rich contrast. The centre stays calm and softly lit for the reel grid, the edges are rich, the bottom edge darker. Painterly 3D matte painting, cinematic lighting, atmospheric depth, volumetric light, rich detail, premium AAA slot game background. No text, no logo, no characters, no UI. Avoid: lightning, clouds, sky, white marble columns, temples in the sky, gemstones, mermaids, sharks, horror.
```

### bg-night-portrait.png · 9:16 · attach bg-day-portrait and bg-night-landscape
```text
The glowing Abyss of the second attached image, recomposed exactly like the tall phone layout of the first attached image: the reel grid in the middle (about 85% of the width, from 15% to 60% of the height) stays calm, dark and softly lit; glowing coral, jellyfish and ruins frame the sides; the top 12% shows the distant glowing palace spire and the faint whirlpool, where the logo goes; the bottom 30% is a soft, darker sea floor with little detail, where the buttons go. Painterly 3D matte painting, cinematic lighting, atmospheric depth, volumetric light, rich detail, premium AAA slot game background. No text, no logo, no characters, no UI. Avoid: lightning, clouds, sky, white marble columns, temples in the sky, gemstones, mermaids, sharks, horror.
```

### fg-kelp.png · 1:2 · transparent · attach bg-day-landscape
Sways in front of the background at the lower left, mirrored at the right. In the Abyss the game darkens it and lights its edges.
```text
A tall clump of three or four golden-green kelp fronds rising from the bottom edge of the image, as in the attached image: long ribbon-like leaves with gas bladders, gently curving as if swaying in a current, sunlight glowing through their edges, a small cluster of orange coral and a scallop shell at their base. The fronds start at the bottom edge (cut off by it on purpose) and end in open space at the top. Premium AAA slot game art, stylised photoreal 3D render, glossy, rich saturated colours, cool aquamarine light from above, tack-sharp focus, high detail. No text, no cast shadow. On a transparent background, exported as a PNG with transparency.
```

### fg-reef.png · 3:2 · transparent · attach bg-day-landscape
Sits in the lower-left corner in front of everything (mirrored at the right) and moves a little with parallax.
```text
A rich coral outcrop entering from the lower-left corner of the image: a rock of vivid orange and coral-red branching coral, a gold sea fan, sea anemones in teal and gold, a few barnacles, a half-buried gold coin and a small bronze trident fragment, as in the attached image. It enters from the left and bottom edges (cut off by them on purpose) and ends in open space toward the upper right. Premium AAA slot game art, stylised photoreal 3D render, glossy, rich saturated colours, cool aquamarine light from above, tack-sharp focus, high detail. No text, no cast shadow. On a transparent background, exported as a PNG with transparency.
```

### fg-jelly.png · 1:1 · transparent · attach bg-night-landscape
Drifts slowly in front of the Abyss in free spins; the game pulses its glow.
```text
A single graceful jellyfish glowing with bioluminescence, as in the attached image: a softly domed bell in luminous turquoise and gold with fine glowing lines radiating from its top, long trailing frilly tentacles and thin threads drifting downward. Ethereal and beautiful. Premium AAA slot game art, stylised 3D render, rich saturated colours, soft glow, tack-sharp focus, high detail. No text, no cast shadow. On a transparent background, exported as a PNG with transparency. The whole jellyfish is in frame with a clear margin.
```

### fx-fish.png · 1:1 · transparent · attach bg-day-landscape
The school of fish that crosses the background. I cut each fish out, so they must be separate.
```text
About 12 small separate fish, all seen exactly from the side and swimming to the right: sleek silver fish with blue-green backs and a few with gold flanks, in two or three sizes, scattered evenly across the image with clear empty space between every one of them; none touch each other or the edge. Each is crisp, glossy and simple. Premium AAA slot game art, stylised photoreal 3D render, rich saturated colours, crisp specular highlights, tack-sharp focus. No text, no cast shadow. On a transparent background, exported as a PNG with transparency.
```

---

## Frame and buttons · attach sym-a

### ui-frame.png · 5:4 · magenta · ChatGPT recommended
```text
An ornate premium frame around a slot game's reel window, made of polished bronze with a rich verdigris-teal patina, trimmed with gold running-wave scrollwork and inlaid with bands of shimmering mother-of-pearl, a few small barnacles and tiny pearls set along it, branches of red coral curling over the two top corners, and at the top centre a golden scallop-shell crest with a small gold trident on it. The frame is symmetrical left to right and evenly thick on all four sides. The inner opening is a clean, sharp-cornered rectangle with proportions of exactly 6 wide to 5 high, filled with flat pure magenta (#FF00FF); everything outside the frame is flat pure magenta too. Straight front view, no perspective. Match the style and lighting of the attached image. Premium AAA slot game art, stylised photoreal 3D render, glossy, rich saturated colours, crisp specular highlights, tack-sharp focus, high detail. No text, no cast shadow. Nothing on the frame is pink, purple or see-through.
```

### ui-spin.png · 1:1 · transparent
```text
A round premium spin button seen straight on: a domed disc of polished aquamarine sea glass with a slowly swirling whirlpool of lighter turquoise inside it, a raised rim of tiny pearls, a polished gold outer ring engraved with running waves, and in the centre a bold white circular double-arrow "spin" icon, embossed and softly glowing. Cool, tactile and irresistible to press. Match the style and lighting of the attached image. Premium AAA slot game art, stylised photoreal 3D render, glossy, rich saturated colours, crisp specular highlights, tack-sharp focus, high detail. No text, no cast shadow. On a transparent background, exported as a PNG with transparency. The whole button is in frame with a clear margin.
```

### ui-plaque.png · 16:9 · transparent · for the free-spins intro, the buy confirmation and the win summary
```text
An ornate horizontal message panel for a slot game: a rounded-rectangle plaque of deep-sea-blue lacquer with a subtle mother-of-pearl shimmer, framed by a verdigris-bronze border with gold running-wave scrollwork, small pearls at its four corners, sprigs of red coral over the two top corners and a golden scallop-shell crest at the top centre. Its large centre is a smooth, plain, empty dark-blue surface, left blank for text. Symmetrical, straight front view. Match the style and lighting of the attached image. Premium AAA slot game art, stylised photoreal 3D render, glossy, rich saturated colours, crisp specular highlights, tack-sharp focus, high detail. No text, no cast shadow. On a transparent background, exported as a PNG with transparency. The whole panel is in frame with a clear margin.
```

### ui-tide-ring.png · 1:1 · transparent · the Tide meter
The game puts a turning whirlpool and the free-spins total multiplier inside it.
```text
A round porthole-like ring seen straight on: a thick ring of polished gold and verdigris bronze, engraved with running waves, set with a circle of small lustrous pearls, with three short gold trident points rising from its top edge and a small scallop shell at its bottom. Its centre is completely empty and see-through: a perfect clear circle, about 70% of the ring's diameter. Match the style and lighting of the attached image. Premium AAA slot game art, stylised photoreal 3D render, glossy, rich saturated colours, crisp specular highlights, tack-sharp focus, high detail. No text, no cast shadow. On a transparent background, exported as a PNG with transparency. The whole ring is in frame with a clear margin.
```

---

## Water and light effects (glow on black)

These are light, so the game adds them on top. Black means "no light". Keep them **centred and fading out before the edges**.

### fx-splash-burst.png · 1:1 · the win burst
```text
An explosive crown splash of luminous water seen head-on: a brilliant white-aqua flash at the centre, a ring of glowing water droplets and liquid spikes blasting outward in every direction, fine sprays of mist, a few larger glittering drops. Colours: brilliant white, turquoise and aquamarine with a hint of gold. Symmetrical radial composition, centred, fading out well before the edges of the image. A light effect only, glowing, on a pure black (#000000) background with nothing else in the frame. Nothing is cropped by the frame. No text.
```

### fx-splash-ring.png · 1:1 · the ripple
```text
A thin expanding ripple ring of glowing water seen straight on: a perfect circle of luminous aqua-white light made of tiny droplets and a crisp bright wave crest, fading softly inward, the centre empty and black. The ring's diameter is about 80% of the image. A light effect only, glowing, on a pure black (#000000) background with nothing else in the frame. Nothing is cropped by the frame. No text.
```

### fx-whirlpool.png · 1:1 · around winners, inside the Tide, the free-spins intro
```text
A glowing whirlpool seen from directly above: spiral arms of luminous turquoise and white water curling inward toward a bright aqua-white centre, fine streaks of foam and tiny glittering droplets following the spiral, the outer arms fading softly into black. A perfect circular spiral, centred, fading out before the edges. A light effect only, glowing, on a pure black (#000000) background with nothing else in the frame. No text.
```

### fx-bubbles.png · 1:1 · rising bubbles
```text
About 30 separate glowing air bubbles scattered evenly across the image with clear black space between every one of them, in many sizes from tiny to medium: each is a perfect circle drawn as a thin luminous aqua-white rim with a small bright highlight at its upper left and a faint inner glow. None of them touch. A light effect only, glowing, on a pure black (#000000) background with nothing else in the frame. No text.
```

### fx-droplets.png · 1:1 · flying water drops
```text
About 30 separate glowing water droplets and sparkles scattered evenly across the image with clear black space between every one of them: teardrop-shaped drops, round beads and a few tiny four-pointed glints, in white, turquoise and pale gold, in different sizes and angles. None of them touch. A light effect only, glowing, on a pure black (#000000) background with nothing else in the frame. No text.
```

### fx-sunrays.png · 16:9 · light shafts in the shallows
```text
Soft underwater light shafts seen from below the surface: six to eight long diagonal beams of pale aqua-white sunlight slanting down from the top edge toward the lower right, of different widths and brightness, with gentle haze and a few floating motes of light. The beams are brightest at the top edge and fade out before the bottom. A light effect only, glowing, on a pure black (#000000) background with nothing else in the frame. No text.
```

### fx-aqua-rays.png · 1:1 · scatters and free spins
```text
A radiant burst of god-rays from a bright white-aqua centre: long soft rays in turquoise and pale gold, evenly spaced all around 360 degrees, a gentle glowing haze and a few sparkles. Centred and symmetrical, fading out before the edges. A light effect only, glowing, on a pure black (#000000) background with nothing else in the frame. No text.
```

### fx-water-jet.png · 2:1 · the trident's jet, and a pearl in flight
```text
A glowing jet of water travelling from left to right: a bright white-aqua head with a small splash of droplets, a long twisting ribbon of luminous turquoise water and fine foam behind it, tapering and fading out toward the left, with a few tiny bubbles along it. Horizontal, vertically centred, nothing cropped. A light effect only, glowing, on a pure black (#000000) background with nothing else in the frame. No text.
```

### fx-twinkle.png · 1:1 · sparkle glint
```text
A single four-pointed sparkle star: a tiny brilliant white core, two long razor-thin rays (one horizontal, one vertical), two shorter diagonal rays and a soft aqua-white halo. Centred, with the rays fading out before the edges. A light effect only, glowing, on a pure black (#000000) background with nothing else in the frame. No text.
```

---

## Celebration pieces (transparent)

### fx-treasure.png · 1:1 · big-win rain · attach sym-a
I cut each piece out, so they must be separate.
```text
About 35 small separate treasure pieces scattered evenly across the image with clear gaps, none touching each other or the edge: shiny gold coins stamped with a trident, seen at many angles (face-on, tilted, edge-on), small lustrous white and golden pearls, tiny branches of red coral and small gold scallop shells, in different sizes. Each piece crisp, glossy and solid. Match the style and lighting of the attached image. Premium AAA slot game art, stylised photoreal 3D render, glossy, rich saturated colours, crisp specular highlights, tack-sharp focus. No text, no cast shadow. On a transparent background, exported as a PNG with transparency.
```

### fx-surge.png · 21:9 · transparent · the big-win and free-spins sweep
The game slides it across the whole screen. It must reach the left, top and bottom edges.
```text
A massive horizontal surge of churning water sweeping from left to right: a towering wall of luminous turquoise water full of white foam, swirling currents and thousands of bubbles, its leading edge on the right a tall curling crest of bright white foam throwing spray forward. The water fills the image from the left edge, top to bottom, and its foamy crest ends at about 80% of the width; the rest to the right is empty. Powerful, dramatic and beautiful. Premium AAA slot game art, stylised photoreal 3D render, rich saturated colours, volumetric light, crisp highlights, tack-sharp focus, high detail. No text, no creatures, no cast shadow. On a transparent background, exported as a PNG with transparency.
```

---

## Lettering (transparent) · ChatGPT or Ideogram

Generate the banners after `ui-frame`, and attach `sym-a` and `ui-frame` so the lettering matches the materials. Check the spelling every time. Once `txt-logo` exists, attach it too when you regenerate a banner.

### Banners · 3:1 · attach sym-a and ui-frame

There is one prompt per banner; only the words and the material change.

| File | Words | Material sentence |
|---|---|---|
| `txt-big-win.png` | `BIG WIN` | `polished gold letters with verdigris-bronze bevels, beads of water and bright sparkles` |
| `txt-mega-win.png` | `MEGA WIN` | `glowing aquamarine sea-glass letters with thick polished gold rims and splashes of white foam, opaque` |
| `txt-epic-win.png` | `EPIC WIN` | `shimmering mother-of-pearl letters with gold rims, red coral branches growing from their corners` |
| `txt-max-win.png` | `MAX WIN` | `molten-gold letters blazing with aquamarine light from within, rimmed with tiny pearls, with glittering droplets flying around them` |
| `txt-free-spins.png` | `FREE SPINS` | `glowing turquoise water-glass letters with gold rims and tiny bubbles rising from them, opaque` |
| `txt-total-win.png` | `TOTAL WIN` | `polished gold letters with verdigris-bronze bevels, beads of water and bright sparkles` |

```text
The words "BIG WIN" in chunky three-dimensional lettering on one line: polished gold letters with verdigris-bronze bevels, beads of water and bright sparkles. Bold, celebratory, perfectly legible, slightly arched. Match the materials and lighting of the attached images. Spell exactly: BIG WIN. Premium AAA slot game art, stylised photoreal 3D render, glossy, rich saturated colours, crisp specular highlights, tack-sharp focus. No other text, no cast shadow. On a transparent background, exported as a PNG with transparency. The whole word is in frame with a clear margin.
```

### txt-logo.png · 2:1 · attach sym-a and sym-m-pearl-moon
```text
The game logo in chunky three-dimensional lettering: "TRIDENT" on top in polished gold letters with verdigris-bronze bevels, its letter I shaped like a slim upright gold trident whose three prongs glow aquamarine; a small pearl-white "OF" between the lines; "POSEIDON" below it, larger, in polished gold letters with verdigris-bronze bevels and a faint aquamarine glow from within. The letter O in POSEIDON is a big glowing pearl set in a gold ring, like the pearl in the second attached image. A splash of white water and a few droplets curl around the bottom of the lettering. Majestic, premium and perfectly legible. Spell exactly: TRIDENT OF POSEIDON. Match the finish of the first attached image. Premium AAA slot game art, stylised photoreal 3D render, glossy, rich saturated colours, crisp specular highlights, tack-sharp focus. No other text, no cast shadow. On a transparent background, exported as a PNG with transparency. The whole logo is in frame with a clear margin.
```

---

## Lobby tile (scene)

### key-thumbnail.png · 4:3 · attach char-poseidon-roar, bg-day-landscape and sym-m-pearl-gold
The logo goes on top afterwards; I compose it.
```text
Hero key art for the game's lobby tile: Poseidon from the first attached image, waist-up and rising from a swirling column of glowing seawater on the right side of the image, raising his blazing trident high, eyes glowing aquamarine, hair and cloak flowing; around him, glowing pearls in open clam shells like the one in the third attached image and gold trident coins burst toward the viewer in a spiral of luminous water and bubbles; the sunlit underwater realm of the second attached image behind, with sun shafts from the surface; shallow depth of field. The top third and the left side are calmer and slightly darker, because the logo goes there afterwards. Spectacular, majestic and premium. Painterly 3D, cinematic lighting, volumetric light, rich detail, premium AAA slot game key art. No text, no logo. Avoid: lightning, clouds, sky, white marble columns, gemstones, mermaid tail.
```

---

## What I do when the art arrives

- **Key and cut:** `scripts/art/process-trident-of-poseidon.py` keys the frame's magenta, turns glow sheets' black into light, splits the sheets (`fx-bubbles`, `fx-droplets`, `fx-fish`, `fx-treasure`) into single pieces, measures each pose (chin, prong tips, scale), and packs everything as WebP into `public/images/trident-of-poseidon/` with an `art.json`.
- **Made in code, not art:** the moving caustic light, the gentle warp of Poseidon's hair and cloak, each symbol's dissolve into its own coloured droplets, the swirl of the Tide, the column of water under Poseidon, the grid, the bar and every number.
- **Thumbnail:** I compose the lobby thumbnail from `key-thumbnail` and the logo.
