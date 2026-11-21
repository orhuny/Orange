# Stones of the Titan: art prompts (round 1)

Audience: whoever generates the art for Stones of the Titan. Every prompt below is complete; copy it as is.

**Name:** Stones of the Titan (code `stones-of-the-titan`), picked by the user on 2026-11-21. Only `txt-logo` carries the name.

## The look in one paragraph

The game is set in **the Titan's realm, deep in space, inside a golden nebula**. In the base game the sky is warm and bright: gold, amber and rose nebula clouds with teal and deep blue, a huge ringed planet, and floating islands of black obsidian. Rivers of liquid starlight pour off the islands, and the islands grow glowing crystals and split geodes. Free spins happen during **the Eclipse**: the same place under a black sun with a blazing gold corona, the nebula turned crimson and indigo. The host is **the Titan**, a massive, wrathful conqueror of polished black stone, angry in every pose. Through the cracks in his body you can see the cosmos inside him, and the cracks burn hotter with his rage. He stands full-length on a floating rock to the right of the grid. The materials are **polished black obsidian, dark meteoric iron, polished gold with constellation lines, and glowing natural crystal**. Multipliers are **meteors**. In free spins they feed **the Eclipse**, a ring above the grid whose corona grows with the running multiplier.

## How the art becomes the game

**A win:**
1. The Titan points at the grid (`char-titan-command`).
2. Glowing cracks run across each winning symbol (`fx-cracks`), and the symbol **shatters into shards of its own stone**. Code cuts the shards from the symbol's own pixels, so no extra art is needed.
3. The shatter comes with a flash, a shockwave and sparks (`fx-shatter-burst`, `fx-shockwave`, `fx-embers`). The shards spiral into a tiny black hole (`fx-vortex`) that closes with a glint (`fx-twinkle`).
4. New symbols drop in from above and land with weight and a puff of stardust (`fx-stardust`).

**The meteors (multipliers):**
1. A meteor lands on a cell with a short trail (`sym-m-meteor-*`, `fx-meteor-trail`). The game prints its value on the stone.
2. When a winning tumble chain ends, the Titan raises his fist (`char-titan-crush`). The meteors lift off the grid and fly into his fist along their trails.
3. He crushes them, a blaze bursts from his fingers, and he hurls one blazing star into the win, which jumps with a shockwave.
4. In free spins the star flies into the Eclipse (`ui-eclipse-ring` + `fx-corona`) instead, and the corona grows brighter as the total climbs.

**Celebrations:**
- **Big wins:** a torrent of golden starfire sweeps the screen (`fx-sweep`), and gold coins and glowing stones rain down (`fx-treasure`). The Titan roars (`char-titan-roar`), and the screen shakes more with each tier.
- **Free spins:** the Titan summons (`char-titan-summon`). The moon slides across the star, the corona flares, the scene turns to the Eclipse, and FREE SPINS appears.
- **Scatters:** each scatter that lands flashes a corona and a ring pulse. Once three are down, the remaining columns slow down behind a gravity-lens shimmer.
- **The scene is alive:** the nebula drifts, the stars twinkle with depth, and floating rocks drift past (`fg-asteroids`). Crystal outcrops sit in the lower corners (`fg-crystals`). The Titan's floating rock bobs gently (`fg-pedestal`), and his cracks pulse with light. Code draws the nebula's motion and the starfield.

**What code does and what art does:** the animation is code. The art supplies the pieces it animates: layered stills, particles and light. Image models can't draw a consistent frame-by-frame sequence, so **never ask for sprite sheets or animation frames**.

## Rules (read before generating)

1. **One prompt per request.** Never ask for several assets at once. If you do, the tool falls back to flat vector art.
2. **Set the style first.**
   - Generate `sym-a-caged-star` until you love it, then attach it as a style reference to every other symbol, meteor, UI and effect sheet.
   - Generate `char-titan-idle` until you love him, then attach him to every other pose.
   - Each prompt says what to attach. In Midjourney, use `--sref <image url>` for style and `--cref <image url>` for the character.
3. **Four kinds of canvas.** Each prompt ends with one of these:
   - **Transparent** (the default): a real transparent PNG. ChatGPT image can export one. If your tool can't, replace the last sentence with: *"Isolated on a flat, solid, pure magenta (#FF00FF) background: no gradient, no floor, no shadow. Nothing on the object is pink, purple or see-through."* The rose and violet symbols need transparency. Never key them on magenta.
   - **Magenta:** flat pure `#FF00FF`, only for the frame. The script finds the frame's window by its magenta.
   - **Glow:** pure black `#000000`. These are light effects. The game adds them as light, so black disappears and the glow stays soft. Never generate a glow on magenta or transparency.
   - **Scene:** a full picture.
4. **Size and format:** the largest size the tool offers, as PNG. Backgrounds need at least 2560×1440 (or 1440×2560); upscale if needed.
5. **Text:** only the `txt-*` assets contain text. Check every letter, and regenerate until the spelling is exact.
6. **Delivery:** put the files, under the names below, in `C:\Git\Orange\storage\app\art\stones-of-the-titan\round1\`. The build script reads that folder.
   - When unsure, send 2–3 variants (`-a`, `-b`, `-c`). I pick the best one and key it.
   - Batches are fine. Delivering the symbols, the Titan and the backgrounds first already changes the whole look.
7. **Tools:**
   - **ChatGPT image** is best for transparency, text, layouts and the character. Use it for `char-*`, `txt-*`, `ui-*`, the symbols and the meteors.
   - **Midjourney** is richest for backgrounds and light effects. Add `--ar 16:9`, `--ar 9:16` or `--ar 1:1`, and move each prompt's "Avoid:" list into `--no`.
8. **Keep it ours.** This game must not look like any other slot, film or comic. Never generate any of these:
   - **For the Titan:**
     - purple, lilac, grey or blue skin;
     - a helmet of any kind;
     - a glove or gauntlet, with or without stones set in it;
     - a bald head with a ridged chin;
     - red eyes;
     - horns.
   - **Stones and gems:**
     - six coloured stones together, whether in a row, a set, a glove or a crown;
     - small smooth polished oval or cube-shaped gems.
   - **Other slots' look:**
     - lightning, thunderbolts or storm clouds;
     - white marble columns, temples in the sky, or golden gates;
     - faceted cut gemstones as symbols, or crowns, hourglasses, rings or chalices as symbols;
     - coloured glass orbs or balls as multipliers;
     - a god's or a titan's face or head as a symbol.
   - **Names:** never put the name of a film, comic, actor or character in any prompt. Image tools refuse them, and they pull the design toward the original.

### Order

1. `sym-a-caged-star`: the style anchor.
2. The other symbols, `sym-b` … `sym-i` and `sym-s`, then the four `sym-m-meteor-*`.
3. `char-titan-idle`: the character anchor. Then the other five poses and `fg-pedestal`.
4. `bg-day-landscape`, then the other three backgrounds.
5. `ui-frame`, `ui-tumble-plaque`, `ui-card-buy`, `ui-card-ante`, `ui-spin`, `ui-plaque`, `ui-eclipse-ring`.
6. The banners, then the `fx-*` light effects.
7. `fg-crystals`, `fg-asteroids`, `fx-treasure`, `fx-sweep`.
8. `key-thumbnail`.
9. `txt-logo`.

---

## Symbols (square 1:1, transparent)

The grid shows the symbols at about 60–120 px. Each one needs a bold, instantly readable silhouette and its own dominant colour.

| Symbol | Rank | Dominant colour |
|---|---|---|
| A, the caged star | top | gold and white light |
| B, the orrery | high | sapphire blue and gold |
| C, the Titan's shield | high | crimson and gold |
| D, the sword in the meteorite | high | emerald green and silver |
| E, the ammonite | low | copper orange |
| F, the sun geode | low | amber yellow |
| G, the amethyst cluster | low | violet |
| H, the runestone | low | turquoise |
| I, the comet shard | low | ice white and pale cyan |

The low symbols are all *natural* stones: a fossil, a geode, a crystal cluster, a carved runestone and a chunk of comet ice. None is a cut jewel.

Code cuts a winning symbol's shards from its own pixels, so **every symbol must be solid and opaque**, with crisp edges.

### sym-a-caged-star.png · top symbol · generate first
```text
The most precious relic of a cosmic titan: a blazing caged star. At its centre burns a small star, a sphere of brilliant white-gold light with soft flares, held inside an ornate cage of eight curved polished-gold rays that sweep around it like the petals of a closing flower and meet at a pointed gold finial on top. The cage rises from a heavy collar of polished gold and smooth black obsidian, engraved with thin gold lines that trace constellations. Light from the star spills across the gold; a few warm sparks drift around it. Upright, front three-quarter view, radiant and powerful: the most valuable symbol of the set. Fills about 80% of the frame. Premium AAA slot game art, stylised photoreal 3D render, glossy and tactile, rich saturated colours, crisp specular highlights, soft inner glow, a warm white-gold key light from the upper left like the light of a nearby star, a cool teal nebula rim light from the right, tiny glints of starlight on the edges, tack-sharp focus, high detail. No text, no outline, no cast shadow, no frame. On a transparent background, exported as a PNG with transparency. The whole object is in frame with a clear margin.
```

### sym-b-orrery.png · attach sym-a
```text
A celestial orrery: an armillary sphere of three crossed polished-gold rings engraved with tiny star marks, turning around a small glowing sapphire-blue planet with swirling bands of deep blue and teal, with one tiny white-gold moon on a thin gold arm. It stands on a short ornate gold stand with a smooth black obsidian foot. The colours are sapphire blue and gold. Three-quarter view from slightly above. Elegant, precious and clearly readable. Match the style, lighting and finish of the attached image. Fills about 80% of the frame. Premium AAA slot game art, stylised photoreal 3D render, glossy and tactile, rich saturated colours, crisp specular highlights, soft inner glow, a warm white-gold key light from the upper left like the light of a nearby star, a cool teal nebula rim light from the right, tiny glints of starlight on the edges, tack-sharp focus, high detail. No text, no outline, no cast shadow, no frame. On a transparent background, exported as a PNG with transparency. The whole object is in frame with a clear margin.
```

### sym-c-shield.png · attach sym-a
```text
A titan's round war shield seen from the front, slightly tilted: a face of deep crimson enamel with a rich metallic sheen, a thick raised rim of polished gold studded with small rough pieces of dark meteorite, and at its centre a raised gold emblem of a constellation: seven gold stars joined by fine glowing gold lines in the shape of a roaring lion, around a small boss of polished black obsidian. The colours are crimson and gold. Bold, heavy and heroic. Match the style, lighting and finish of the attached image. Fills about 80% of the frame. Premium AAA slot game art, stylised photoreal 3D render, glossy and tactile, rich saturated colours, crisp specular highlights, soft inner glow, a warm white-gold key light from the upper left like the light of a nearby star, a cool teal nebula rim light from the right, tiny glints of starlight on the edges, tack-sharp focus, high detail. No text, no outline, no cast shadow, no frame. On a transparent background, exported as a PNG with transparency. The whole object is in frame with a clear margin.
```

### sym-d-sword.png · attach sym-a
```text
A broad short sword plunged point-first into a rough chunk of dark meteorite rock. Its blade is smooth polished emerald-green crystal, with no facets, with a swirling green nebula and tiny stars glowing inside it and a bright silver edge. The cross-guard is silver, shaped like two swept-back wings; the grip is wrapped in dark leather; the pommel is a small silver star. The meteorite is cracked, and emerald light glows from the cracks. The colours are emerald green and silver. Upright, three-quarter view. Legendary and mysterious. Match the style, lighting and finish of the attached image. Fills about 80% of the frame. Premium AAA slot game art, stylised photoreal 3D render, glossy and tactile, rich saturated colours, crisp specular highlights, soft inner glow, a warm white-gold key light from the upper left like the light of a nearby star, a cool teal nebula rim light from the right, tiny glints of starlight on the edges, tack-sharp focus, high detail. No text, no outline, no cast shadow, no frame. On a transparent background, exported as a PNG with transparency. The whole object is in frame with a clear margin.
```

### sym-e-ammonite.png · attach sym-a
```text
A starstone: a large ammonite fossil, a coiled spiral shell turned to stone, its ridged surface shimmering with fiery copper-orange and gold iridescence like opal, and a soft orange glow from deep inside the spiral. Its edges are rough natural stone. The colour is copper orange. Front view of the spiral, tilted slightly toward the viewer: a bold, round, compact silhouette. A natural stone, not a cut jewel. Match the style, lighting and finish of the attached image. Fills about 80% of the frame. Premium AAA slot game art, stylised photoreal 3D render, glossy and tactile, rich saturated colours, crisp specular highlights, soft inner glow, a warm white-gold key light from the upper left like the light of a nearby star, a cool teal nebula rim light from the right, tiny glints of starlight on the edges, tack-sharp focus, high detail. No text, no outline, no cast shadow, no frame. On a transparent background, exported as a PNG with transparency. The whole object is in frame with a clear margin.
```

### sym-f-geode.png · attach sym-a
```text
A starstone: a large round geode split open. Its outer shell is rough cream-and-brown stone; its hollow inside is packed with sparkling amber-yellow crystal points glowing warmly like sunlight, brightest at the centre. The colour is sunny amber yellow. Front view of the open half, tilted slightly toward the viewer: a bold, round, compact silhouette. Natural crystals, not cut jewels. Match the style, lighting and finish of the attached image. Fills about 80% of the frame. Premium AAA slot game art, stylised photoreal 3D render, glossy and tactile, rich saturated colours, crisp specular highlights, soft inner glow, a warm white-gold key light from the upper left like the light of a nearby star, a cool teal nebula rim light from the right, tiny glints of starlight on the edges, tack-sharp focus, high detail. No text, no outline, no cast shadow, no frame. On a transparent background, exported as a PNG with transparency. The whole object is in frame with a clear margin.
```

### sym-g-amethyst.png · attach sym-a
```text
A starstone: a cluster of raw amethyst crystals, five to seven tall natural crystal points of glowing deep violet, the tallest in the middle, rising from a small base of rough black rock; faint pinpoints of starlight shimmer inside the crystals and their tips glow brighter. The colour is deep violet. Upright, three-quarter view, a bold crown-like silhouette of points. Natural crystals, not cut jewels. Match the style, lighting and finish of the attached image. Fills about 80% of the frame. Premium AAA slot game art, stylised photoreal 3D render, glossy and tactile, rich saturated colours, crisp specular highlights, soft inner glow, a warm white-gold key light from the upper left like the light of a nearby star, a cool teal nebula rim light from the right, tiny glints of starlight on the edges, tack-sharp focus, high detail. No text, no outline, no cast shadow, no frame. On a transparent background, exported as a PNG with transparency. The whole object is in frame with a clear margin.
```

### sym-h-runestone.png · attach sym-a
```text
A starstone: a smooth, flat, rounded runestone of dark slate, like a large river stone standing on its edge, with a bold sigil carved deep into its face: a circle crossed by four short rays, like a small eclipse. The carving glows bright turquoise from within, and a faint turquoise glow spills over the stone's rim. The colour is turquoise on dark slate. Straight front view, a bold, simple silhouette. Match the style, lighting and finish of the attached image. Fills about 80% of the frame. Premium AAA slot game art, stylised photoreal 3D render, glossy and tactile, rich saturated colours, crisp specular highlights, soft inner glow, a warm white-gold key light from the upper left like the light of a nearby star, a cool teal nebula rim light from the right, tiny glints of starlight on the edges, tack-sharp focus, high detail. No text, no outline, no cast shadow, no frame. On a transparent background, exported as a PNG with transparency. The whole object is in frame with a clear margin.
```

### sym-i-comet.png · attach sym-a
```text
A starstone: a rough chunk of comet ice and pale stone, frosted white, with sharp frost crystals growing on its edges, cracks glowing a soft pale cyan from within, and a thin wisp of white vapour curling off its top. The colours are ice white and pale cyan. Three-quarter view, a bold, compact silhouette. Match the style, lighting and finish of the attached image. Fills about 80% of the frame. Premium AAA slot game art, stylised photoreal 3D render, glossy and tactile, rich saturated colours, crisp specular highlights, soft inner glow, a warm white-gold key light from the upper left like the light of a nearby star, a cool teal nebula rim light from the right, tiny glints of starlight on the edges, tack-sharp focus, high detail. No text, no outline, no cast shadow, no frame. On a transparent background, exported as a PNG with transparency. The whole object is in frame with a clear margin.
```

### sym-s-eclipse.png · scatter · attach sym-a
The page adds the word SCATTER over it as text, so the bottom fifth should stay quiet.
```text
The scatter symbol: the Titan's eclipse sigil. A heavy round medallion of polished gold with eight long, sharp sun-ray points around its rim, and small rough glowing stones (amber, violet and turquoise) set between the rays. At its heart, a total solar eclipse: a perfectly black disc ringed by a blazing white-gold corona with fine curling flares, casting light across the gold. Clearly the most special and magical symbol: bigger, brighter and more radiant than any other. Straight front view; the lowest fifth of the medallion is plain polished gold. Match the style, lighting and finish of the attached image. Fills about 85% of the frame. Premium AAA slot game art, stylised photoreal 3D render, glossy and tactile, rich saturated colours, crisp specular highlights, soft inner glow, a warm white-gold key light from the upper left like the light of a nearby star, a cool teal nebula rim light from the right, tiny glints of starlight on the edges, tack-sharp focus, high detail. No text, no outline, no cast shadow, no frame. On a transparent background, exported as a PNG with transparency. The whole object is in frame with a clear margin.
```

### Multiplier meteors (4 colours by value) · attach sym-a

The game prints the value, from 2× up to 500×, on the meteor's face, so the face must be big and plain. There is one prompt per colour; only the colour sentence changes.

| File | Values | Colour sentence |
|---|---|---|
| `sym-m-meteor-ice.png` | 2×–5× | `its face glows with bright ice-blue light, and a crown of ice-blue starfire` |
| `sym-m-meteor-jade.png` | 6×–15× | `its face glows with bright jade-green light, and a crown of jade-green starfire` |
| `sym-m-meteor-ember.png` | 20×–50× | `its face glows with bright crimson-orange light, and a crown of crimson-orange starfire` |
| `sym-m-meteor-sun.png` | 100×–500× | `its face blazes with brilliant white-gold light ringed by a thin black-and-gold corona, and a crown of white-gold starfire with tiny stars in it, the most dazzling and rare of the set` |

```text
A multiplier symbol: a glowing meteor that has just landed. A round, fist-shaped stone of dark rock with a rough, cratered rim; its front is a large smooth face lit from within: its face glows with bright ice-blue light, and a crown of ice-blue starfire flickers around its upper rim and streams up toward the top right like the end of its fall, with a few sparks. The face has a clean bright area at its centre where a number will be printed later: no number, no text. Round, compact and bold, clearly a rock and not a glass ball. Straight front view. Match the style, lighting and finish of the attached image. Fills about 80% of the frame. Premium AAA slot game art, stylised photoreal 3D render, glossy and tactile, rich saturated colours, crisp specular highlights, soft inner glow, a warm white-gold key light from the upper left like the light of a nearby star, a cool teal nebula rim light from the right, tiny glints of starlight on the edges, tack-sharp focus, high detail. No outline, no cast shadow, no frame. On a transparent background, exported as a PNG with transparency. The whole object is in frame with a clear margin.
```

---

## The Titan, the game's host (2:3, transparent)

He stands **full-length on the right of the grid**, facing left toward it, on his floating rock (`fg-pedestal`). On a phone, the game shows his upper body over the frame's top-right corner, so his head, halo and hands must be crisp.

**How to generate:** use ChatGPT image, one request per image, on a transparent background. Generate `char-titan-idle` first. Then attach it to every other pose, so his face, mane, halo, armour, cloak and sceptre stay identical. Regenerate a pose until it's clearly the same character. Keep his feet at the same height in every pose; the game stands each pose on the same rock.

### char-titan-idle.png · the character anchor · attach sym-a
```text
An original cosmic titan, the host of a premium slot game: a colossal, massively built warrior-king and conqueror, towering and heavy, with enormous shoulders, a huge deep chest, and thick, powerful arms and legs, like a statue of a war god. His whole body is made of polished black obsidian, smooth and gleaming like a carved statue, and split by a fine network of glowing cracks; through the cracks you see the cosmos inside him: bright white-gold starlight, swirls of deep-blue and teal nebula and tiny stars. His face is fierce and wrathful, chiselled like a statue's: high cheekbones, a straight nose, a heavy brow drawn down in fury, a clenched jaw, and eyes that blaze like two small white-gold stars under the scowl. A long flowing mane and a full, long beard made of drifting cosmic nebula, soft smoky clouds of deep blue, teal and gold dust sprinkled with tiny stars, float upward and backward as if in zero gravity. Above his head a halo of seven small rough meteorite shards orbits in a ring, their edges glowing gold. He wears armour of dark meteoric iron with a silvery crystalline etched pattern, inlaid with thin gold lines that trace constellations: a sculpted breastplate, layered shoulder guards of rough meteorite rock rimmed in gold, a wide belt with a round gold buckle shaped like a solar eclipse, armoured bracers and greaves, and a short skirt of meteoric-iron plates. From his shoulders falls a long cloak made of the night sky itself, deep ultramarine with real stars and a faint spiral galaxy in its folds, its lower edge dissolving into stardust. In his right hand he holds upright a tall gold sceptre topped by a small burning white-gold star inside a cage of curved gold rays. Pose: the full figure from head to feet, standing tall with his weight on one leg, three-quarter view with his body turned toward the left of the image; his left hand raised in front of his chest, palm open and up, with three small glowing rough stones floating and circling above it; an angry, contemptuous glare straight at the viewer, lips pressed tight, nostrils flared, the cracks on his face glowing hotter with his rage. Wrathful, intimidating and overwhelmingly powerful: a conqueror who is furious with the universe. Original design, not resembling any real person or any existing film, cartoon, comic or game character. His skin is black obsidian, never purple, lilac, grey or blue; his eyes are white-gold, never red; no helmet, no horns; his hands are bare stone with no glove, no gauntlet and no jewels set in them. Match the finish of the attached image. The figure fills about 92% of the frame height. Premium AAA slot game art, stylised 3D character render with feature-film quality, polished stone and metal with crisp specular highlights, rich saturated colours, a warm white-gold key light from the upper left, a cool teal nebula rim light from the right, tack-sharp focus, high detail. No text, no outline, no cast shadow, no floor, no frame. On a transparent background, exported as a PNG with transparency. The whole figure from the top of the halo to the soles of the feet, and the whole sceptre, are in frame with a clear margin.
```

### char-titan-command.png · on a win · attach char-titan-idle and sym-a
```text
The same titan as in the first attached image: identical face, star-white eyes, nebula mane and beard, halo of meteorite shards, black obsidian body with its glowing cosmic cracks, meteoric-iron armour with gold constellation lines, eclipse buckle, star-field cloak and gold sceptre. New pose: the sceptre stands planted beside him in his right hand; his left arm is stretched out toward the left of the image, index finger pointing as if commanding the stones there to break; the cracks along that arm glow brighter; a furious snarl, teeth bared, his brow drawn down in rage. The full figure from head to feet, three-quarter view turned toward the left, feet at the same height and the same size in the frame as in the attached image. His skin stays black obsidian (never purple, grey or blue), his eyes white-gold (never red), no helmet, no horns, and his hands are bare stone with no glove, gauntlet or jewels. Match the finish of the second attached image. Premium AAA slot game art, stylised 3D character render with feature-film quality, polished stone and metal with crisp specular highlights, rich saturated colours, a warm white-gold key light from the upper left, a cool teal nebula rim light from the right, tack-sharp focus, high detail. No text, no outline, no cast shadow, no floor, no frame. On a transparent background, exported as a PNG with transparency. The whole figure from the top of the halo to the soles of the feet, and the whole sceptre, are in frame with a clear margin.
```

### char-titan-crush.png · when the meteors pay · attach char-titan-idle and sym-a
```text
The same titan as in the first attached image: identical face, star-white eyes, nebula mane and beard, halo of meteorite shards, black obsidian body with its glowing cosmic cracks, meteoric-iron armour with gold constellation lines, eclipse buckle, star-field cloak and gold sceptre. New pose: he raises his bare left fist to the height of his face, clenched tight, and brilliant white-gold light bursts out between his stone fingers, with a few glowing rock fragments spiralling into the fist; the sceptre is held low at his side in his right hand; the cracks on his arm and chest blaze; his nebula mane flares; a ferocious, furious expression, teeth gritted, eyes blazing. The full figure from head to feet, three-quarter view turned toward the left, feet at the same height and the same size in the frame as in the attached image. His skin stays black obsidian (never purple, grey or blue), his eyes white-gold (never red), no helmet, no horns, and his hands are bare stone with no glove, gauntlet or jewels. Match the finish of the second attached image. Premium AAA slot game art, stylised 3D character render with feature-film quality, polished stone and metal with crisp specular highlights, rich saturated colours, a warm white-gold key light from the upper left, a cool teal nebula rim light from the right, tack-sharp focus, high detail. No text, no outline, no cast shadow, no floor, no frame. On a transparent background, exported as a PNG with transparency. The whole figure from the top of the halo to the soles of the feet, and the whole sceptre, are in frame with a clear margin.
```

### char-titan-roar.png · with the big-win banners · attach char-titan-idle and sym-a
```text
The same titan as in the first attached image: identical face, star-white eyes, nebula mane and beard, halo of meteorite shards, black obsidian body with its glowing cosmic cracks, meteoric-iron armour with gold constellation lines, eclipse buckle, star-field cloak and gold sceptre. New pose: triumphant, he raises the sceptre high above his head in his right hand, its caged star blazing; his left fist is clenched at his chest; his head is thrown back in a mighty roar of rage and triumph; his eyes and every crack in his body blaze white-gold; his nebula mane and cloak fly outward and his halo spins wide. The full figure from head to feet, feet at the same height and the head and body the same size in the frame as in the attached image. His skin stays black obsidian (never purple, grey or blue), his eyes white-gold (never red), no helmet, no horns, and his hands are bare stone with no glove, gauntlet or jewels. Match the finish of the second attached image. Premium AAA slot game art, stylised 3D character render with feature-film quality, polished stone and metal with crisp specular highlights, rich saturated colours, a warm white-gold key light from the upper left, a cool teal nebula rim light from the right, tack-sharp focus, high detail. No text, no outline, no cast shadow, no floor, no frame. On a transparent background, exported as a PNG with transparency. The whole figure from the top of the halo to the soles of the feet, and the whole sceptre, are in frame with a clear margin.
```

### char-titan-summon.png · free spins · attach char-titan-idle and sym-a
```text
The same titan as in the first attached image: identical face, star-white eyes, nebula mane and beard, halo of meteorite shards, black obsidian body with its glowing cosmic cracks, meteoric-iron armour with gold constellation lines, eclipse buckle, star-field cloak and gold sceptre. New pose: he summons the eclipse. Both arms are spread wide, his left palm open and facing up, the sceptre held out to the side in his right hand; his eyes blaze pure white; every crack in his body blazes; ribbons of white-gold starlight spiral up around his arms and shoulders; his mane and cloak rise as if lifted by a cosmic wind; his face is a mask of fury. Wrathful, powerful and mystical. The full figure from head to feet, facing the viewer with a slight turn toward the left, feet at the same height and the head and body the same size in the frame as in the attached image. His skin stays black obsidian (never purple, grey or blue), his eyes white-gold (never red), no helmet, no horns, and his hands are bare stone with no glove, gauntlet or jewels. Match the finish of the second attached image. Premium AAA slot game art, stylised 3D character render with feature-film quality, polished stone and metal with crisp specular highlights, rich saturated colours, a warm white-gold key light from the upper left, a cool teal nebula rim light from the right, tack-sharp focus, high detail. No text, no outline, no cast shadow, no floor, no frame. On a transparent background, exported as a PNG with transparency. The whole figure from the top of the halo to the soles of the feet, and the whole sceptre, are in frame with a clear margin.
```

### char-titan-waiting.png · after a minute without a spin · attach char-titan-idle and sym-a
```text
The same titan as in the first attached image: identical face, star-white eyes, nebula mane and beard, halo of meteorite shards, black obsidian body with its glowing cosmic cracks, meteoric-iron armour with gold constellation lines, eclipse buckle, star-field cloak and gold sceptre. New pose: impatient and irritated, he leans on the sceptre planted upright in front of him, both bare hands gripping its shaft one above the other below the caged star; his brow is drawn down in a hard, impatient glare straight at the viewer, jaw clenched, as if demanding that the player spin; his mane and cloak drift gently. The full figure from head to feet, three-quarter view turned toward the left, feet at the same height and the same size in the frame as in the attached image. His skin stays black obsidian (never purple, grey or blue), his eyes white-gold (never red), no helmet, no horns, and his hands are bare stone with no glove, gauntlet or jewels. Match the finish of the second attached image. Premium AAA slot game art, stylised 3D character render with feature-film quality, polished stone and metal with crisp specular highlights, rich saturated colours, a warm white-gold key light from the upper left, a cool teal nebula rim light from the right, tack-sharp focus, high detail. No text, no outline, no cast shadow, no floor, no frame. On a transparent background, exported as a PNG with transparency. The whole figure from the top of the halo to the soles of the feet, and the whole sceptre, are in frame with a clear margin.
```

### fg-pedestal.png · 3:2 · transparent · his floating rock · attach char-titan-idle and sym-a
Every pose stands on it. It bobs gently, and stardust falls from it.
```text
A floating island of rock seen from the front and slightly above, on which a giant will stand: a broad slab of polished black obsidian and dark basalt with a smooth, level top surface; its underside is a jagged inverted cone of rock tapering downward. Fine gold veins and glowing white-gold cracks run through it, like the cracks in the body of the titan in the first attached image. Small clusters of glowing natural crystals, teal and amber, grow at its edges, and thin streams of glowing stardust spill from its rim and fall away below. Wide, solid and stable, empty on top. Match the finish of the second attached image. Premium AAA slot game art, stylised photoreal 3D render, glossy, rich saturated colours, crisp specular highlights, a warm white-gold key light from the upper left, a cool teal rim light from the right, tack-sharp focus, high detail. No text, no figure, no cast shadow, no frame. On a transparent background, exported as a PNG with transparency. The whole island is in frame with a clear margin.
```

---

## Backgrounds (scene)

**The layout:**
- **Landscape:** the reel grid fills the centre, with the tumble-win plaque on top of its frame. The Buy and Ante cards stand in a column on the left. The Titan stands full-length on his floating rock on the right, with the logo above him in the top-right corner. The bar runs along the bottom.
- **Portrait:** the logo is at the top, then the tumble-win plaque and the grid. The Titan's upper body rises over the frame's top-right corner. The cards and the buttons are at the bottom.

So keep **the grid area calm and soft**, and keep the left column and the area behind the Titan fairly calm too. Spend the detail on the edges, which frame them. Code adds the drifting nebula, the twinkling stars and the floating rocks, so leave space for them. Put no big objects in the centre.

### bg-day-landscape.png · 16:9 · base game · generate first
```text
The Titan's realm in deep space, at the heart of a golden nebula: a vast luminous sky of space glowing with warm gold, amber and soft rose nebula clouds mixed with teal and deep blue, sprinkled with thousands of stars. In the upper left, a huge ringed planet in creamy gold and teal bands, its rings catching the light. Floating islands of black obsidian and basalt drift at different depths, their edges veined with gold; some are crowned with tall clusters of glowing natural crystals in teal, gold and violet and with enormous split-open geodes; ribbons of liquid starlight pour off them like waterfalls into the void below. In the far distance, on the largest floating rock, the silhouette of a colossal dark citadel with glowing gold windows and a beam of light rising from it into the nebula. Composition for a game screen: the reel grid covers the centre (about 55% of the width, from 12% to 88% of the height), so that area is a soft, calm nebula glow with little detail; the left fifth, where buttons go, is fairly calm; the right quarter, where a giant will stand, is a calmer area of open sky lit by the planet's glow; the top-right corner is calm, for the logo; the richest detail is around the outer edges. Warm, bright, luminous and majestic, like a golden hour in space. Painterly 3D matte painting, cinematic lighting, volumetric light, atmospheric depth, rich detail, premium AAA slot game background. No text, no logo, no characters, no UI. Avoid: lightning, a daytime sky with clouds, marble columns, Greek temples, golden gates, faceted cut gemstones, spaceships, planets with faces, a purple-dominant sky.
```

### bg-day-portrait.png · 9:16 · attach bg-day-landscape
```text
The same golden-nebula realm as in the attached image, recomposed for a tall phone screen. The reel grid sits in the middle (about 90% of the width, from 20% to 62% of the height), so that area stays a soft, calm nebula glow. The ringed planet and the floating obsidian islands with their crystals and starlight waterfalls frame the sides; the top 12% is a calm, glowing band of nebula where the logo goes; the upper right just above the grid stays calm, because a giant's head and shoulders rise there; the bottom 35% is soft and darker with little detail, where the buttons go. Same light, palette and style as the attached image. Painterly 3D matte painting, cinematic lighting, volumetric light, atmospheric depth, rich detail, premium AAA slot game background. No text, no logo, no characters, no UI. Avoid: lightning, a daytime sky with clouds, marble columns, Greek temples, golden gates, faceted cut gemstones, spaceships, planets with faces, a purple-dominant sky.
```

### bg-night-landscape.png · 16:9 · free spins, the Eclipse · attach bg-day-landscape
```text
The same place as in the attached image, with the same composition, now during a total eclipse for the free spins. In the upper right, a black moon covers the star completely, leaving a vast blazing ring of white-gold corona with long curling flares of gold and crimson, so huge that it would frame a giant standing below it like a halo. The nebula has turned deep crimson, dark gold and black-indigo; the stars shine brighter; the crystals on the floating islands glow intensely in teal, gold and crimson; the starlight waterfalls blaze against the darkness; the distant citadel's beam burns bright. Dramatic, powerful and magical, with rich contrast. The centre stays calm and softly lit for the reel grid, the left fifth fairly calm, the top-right corner calm enough for the logo, the edges rich. Painterly 3D matte painting, cinematic lighting, volumetric light, atmospheric depth, rich detail, premium AAA slot game background. No text, no logo, no characters, no UI. Avoid: lightning, a daytime sky with clouds, marble columns, Greek temples, golden gates, faceted cut gemstones, spaceships, planets with faces, a purple-dominant sky, horror.
```

### bg-night-portrait.png · 9:16 · attach bg-day-portrait and bg-night-landscape
```text
The eclipse realm of the second attached image, recomposed exactly like the tall phone layout of the first attached image: the reel grid in the middle (about 90% of the width, from 20% to 62% of the height) stays calm, dark and softly lit; the black sun with its blazing white-gold corona sits in the upper right just above the grid, where a giant's head and shoulders will rise in front of it; crimson nebula, glowing crystals and floating islands frame the sides; the top 12% is calm, where the logo goes; the bottom 35% is soft and darker with little detail, where the buttons go. Painterly 3D matte painting, cinematic lighting, volumetric light, atmospheric depth, rich detail, premium AAA slot game background. No text, no logo, no characters, no UI. Avoid: lightning, a daytime sky with clouds, marble columns, Greek temples, golden gates, faceted cut gemstones, spaceships, planets with faces, a purple-dominant sky, horror.
```

### fg-crystals.png · 1:1 · transparent · attach bg-day-landscape
It sits in the lower-left corner in front of everything (mirrored at the right) and moves a little with parallax. During the Eclipse the game darkens it and makes its crystals glow brighter.
```text
A rich outcrop of glowing natural crystals on black rock, entering from the lower-left corner of the image: tall raw crystal points in teal, amber and violet (natural crystals, not cut jewels), a large split geode glowing gold inside, rough black obsidian rock with fine gold veins, and a few small chips of stone floating above it, as in the attached image. It enters from the left and bottom edges (cut off by them on purpose) and ends in open space toward the upper right. Premium AAA slot game art, stylised photoreal 3D render, glossy, rich saturated colours, crisp specular highlights, a warm white-gold light from the upper left, tack-sharp focus, high detail. No text, no cast shadow. On a transparent background, exported as a PNG with transparency.
```

### fg-asteroids.png · 1:1 · transparent · attach bg-day-landscape
The floating rocks that drift past at different depths. I cut each one out, so they must be separate.
```text
About 10 separate floating rocks scattered evenly across the image with clear empty space between every one of them, none touching each other or the edge: rough chunks of black obsidian and dark basalt in many sizes, from pebble to boulder, some with fine glowing gold cracks, some with a tiny cluster of teal crystals, all lit from the upper left as in the attached image. Each one crisp and solid. Premium AAA slot game art, stylised photoreal 3D render, glossy, rich saturated colours, crisp specular highlights, tack-sharp focus. No text, no cast shadow. On a transparent background, exported as a PNG with transparency.
```

---

## Frame, cards and buttons · attach sym-a

The frame, the tumble-win plaque, the two cards and the message plaque are blank on purpose. The game writes every word and number on them, so they stay sharp at any size and in any language.

### ui-frame.png · 5:4 · magenta · ChatGPT recommended
```text
An ornate premium frame around a slot game's reel window, made of dark meteoric iron with a silvery crystalline etched pattern, inlaid with bands of polished black obsidian and trimmed with polished gold filigree that traces constellations (small gold stars joined by fine gold lines). A small cluster of glowing natural crystals, teal and amber, sits on each of the four corners, and a few tiny meteorite shards float just off the corners. The middle of the top edge is a plain, straight gold ledge with no crest, because a title plaque will sit there. The frame is symmetrical left to right and evenly thick on all four sides. The inner opening is a clean, sharp-cornered rectangle with proportions of exactly 6 wide to 5 high, filled with flat pure magenta (#FF00FF); everything outside the frame is flat pure magenta too. Straight front view, no perspective. Match the style and lighting of the attached image. Premium AAA slot game art, stylised photoreal 3D render, glossy, rich saturated colours, crisp specular highlights, tack-sharp focus, high detail. No text, no cast shadow. Nothing on the frame is pink, purple or see-through.
```

### ui-tumble-plaque.png · 4:1 · transparent · sits on the frame's top edge
The game writes "TUMBLE WIN" and the amount on it.
```text
A wide horizontal title plaque made to sit on the top edge of a slot game's reel frame: a long, shallow shape with a straight bottom edge and a gently arched top edge. Its face is smooth polished black obsidian with a faint starry shimmer and a soft deep-crimson glow in its depths. It is framed by a raised border of dark meteoric iron trimmed with polished gold constellation filigree, with a small gold eclipse sigil (a black disc ringed by short gold rays) at each end and short swept gold rays fanning out from both ends like wings. The large centre of the face is plain and empty, left blank for text. Symmetrical, straight front view. Match the style and lighting of the attached image. Premium AAA slot game art, stylised photoreal 3D render, glossy, rich saturated colours, crisp specular highlights, tack-sharp focus, high detail. No text, no cast shadow. On a transparent background, exported as a PNG with transparency. The whole plaque is in frame with a clear margin.
```

### ui-card-buy.png and ui-card-ante.png · 3:4 · transparent
These are the two cards in the left column. The game writes "BUY FREE SPINS" and the price on the first. On the second it writes the ante text and draws the switch. Only the colour sentence changes:

| File | Colour sentence |
|---|---|
| `ui-card-buy.png` | `a warm molten-gold glow rising from its lower half, crowned by a small cluster of glowing amber crystals` |
| `ui-card-ante.png` | `a cool teal glow rising from its lower half, crowned by a small cluster of glowing teal crystals` |

```text
A tall feature card for a slot game's side menu: a vertical rounded-rectangle tablet of smooth polished black obsidian with a faint starry shimmer, a warm molten-gold glow rising from its lower half, crowned by a small cluster of glowing amber crystals at its top centre. It is framed by a raised border of dark meteoric iron trimmed with polished gold constellation filigree, with small gold star studs at its four corners. Its face is plain and empty, left blank for text and buttons. Symmetrical, straight front view. Match the style and lighting of the attached image. Premium AAA slot game art, stylised photoreal 3D render, glossy, rich saturated colours, crisp specular highlights, tack-sharp focus, high detail. No text, no cast shadow. On a transparent background, exported as a PNG with transparency. The whole card is in frame with a clear margin.
```

### ui-spin.png · 1:1 · transparent
```text
A round premium spin button seen straight on: a domed disc of dark glass holding a swirling nebula of gold, teal and deep blue with tiny stars inside it; a raised rim of polished black obsidian with thin glowing white-gold cracks; a polished gold outer ring engraved with constellation lines and eight tiny star studs; and at its centre a bold white circular double-arrow spin icon, embossed and softly glowing. Tactile, powerful and irresistible to press. Match the style and lighting of the attached image. Premium AAA slot game art, stylised photoreal 3D render, glossy, rich saturated colours, crisp specular highlights, tack-sharp focus, high detail. No text, no cast shadow. On a transparent background, exported as a PNG with transparency. The whole button is in frame with a clear margin.
```

### ui-plaque.png · 16:9 · transparent · for the free-spins intro, the buy confirmation and the win summary
```text
An ornate horizontal message panel for a slot game: a rounded-rectangle plaque of polished black obsidian with a faint starry shimmer and a soft deep-blue nebula glow in its depths, framed by a border of dark meteoric iron with polished gold constellation filigree, small glowing natural crystals at its four corners, and a gold eclipse sigil crest (a black disc ringed by gold sun rays) at the top centre. Its large centre is a smooth, plain, empty dark surface, left blank for text. Symmetrical, straight front view. Match the style and lighting of the attached image. Premium AAA slot game art, stylised photoreal 3D render, glossy, rich saturated colours, crisp specular highlights, tack-sharp focus, high detail. No text, no cast shadow. On a transparent background, exported as a PNG with transparency. The whole panel is in frame with a clear margin.
```

### ui-eclipse-ring.png · 1:1 · transparent · the Eclipse meter
The game draws the black sun, its growing corona and the free-spins total multiplier inside it.
```text
A round meter ring seen straight on: a thick ring of polished gold and dark meteoric iron, engraved with constellation lines, with eight short, sharp gold sun-ray points around its outer edge and a small cluster of glowing amber crystals at its top. Its centre is completely empty and see-through: a perfect clear circle, about 68% of the ring's diameter. Match the style and lighting of the attached image. Premium AAA slot game art, stylised photoreal 3D render, glossy, rich saturated colours, crisp specular highlights, tack-sharp focus, high detail. No text, no cast shadow. On a transparent background, exported as a PNG with transparency. The whole ring is in frame with a clear margin.
```

---

## Light effects (glow on black)

These are light, so the game adds them on top. Black means "no light". Keep them **centred, and fading out before the edges**.

### fx-cracks.png · 1:1 · cracks run across a winner before it shatters
```text
A pattern of glowing fracture lines seen head-on, like a stone cracking open from the inside: thin, jagged, branching cracks of brilliant white-gold light with teal edges, radiating from a bright point at the centre outward in every direction, thinning and fading out before the edges, with a faint glow along the brightest cracks. Centred and roughly circular overall. A light effect only, glowing, on a pure black (#000000) background with nothing else in the frame. Nothing is cropped by the frame. No text.
```

### fx-shatter-burst.png · 1:1 · the win burst
```text
A powerful burst of light as a stone shatters, seen head-on: a brilliant white-gold flash at the centre, and a radial spray of glowing dust, tiny bright stone chips and sparks blasting outward in every direction, with fine streaks of teal and gold light and a soft haze. Symmetrical radial composition, centred, fading out well before the edges of the image. A light effect only, glowing, on a pure black (#000000) background with nothing else in the frame. Nothing is cropped by the frame. No text.
```

### fx-shockwave.png · 1:1 · the ring after a burst, a crush and a big win
```text
A thin expanding shockwave ring seen straight on: a perfect circle of white-gold energy with a crisp bright leading edge and a soft teal glow fading inward, flecked with tiny sparks; the centre is empty and black. The ring's diameter is about 80% of the image. A light effect only, glowing, on a pure black (#000000) background with nothing else in the frame. Nothing is cropped by the frame. No text.
```

### fx-vortex.png · 1:1 · the tiny black hole that swallows the shards
```text
A tiny black hole seen from slightly above: a glowing accretion disc of swirling gold, white and teal light spiralling into a perfectly dark centre, a thin bright ring of light hugging the dark centre, and streaks of stardust being pulled inward along the spiral. Centred and circular, fading out before the edges. A light effect only, glowing, on a pure black (#000000) background with nothing else in the frame. No text.
```

### fx-stardust.png · 1:1 · landing puffs, the cracks' glow, idle sparkle
I cut each mote out, so they must be separate.
```text
About 30 separate glowing motes of stardust scattered evenly across the image, with clear black space between every one of them: tiny four-pointed glints, small soft round glows and a few short streaks, in white, pale gold and teal, in many sizes. None of them touch. A light effect only, glowing, on a pure black (#000000) background with nothing else in the frame. No text.
```

### fx-embers.png · 1:1 · sparks and chips from a shatter
I cut each piece out, so they must be separate.
```text
About 30 separate tiny glowing stone chips and sparks scattered evenly across the image, with clear black space between every one of them: jagged little fragments glowing white-gold at their cores with orange and teal edges, and small bright sparks, in different sizes and angles. None of them touch. A light effect only, glowing, on a pure black (#000000) background with nothing else in the frame. No text.
```

### fx-meteor-trail.png · 2:1 · a landing meteor, and a meteor flying to the Titan
```text
A glowing meteor streaking from left to right: a brilliant white-gold head with a small burst of sparks, followed by a long tapering tail of gold and teal starfire, fine embers and stardust, fading out toward the left. Horizontal, vertically centred, nothing cropped. A light effect only, glowing, on a pure black (#000000) background with nothing else in the frame. No text.
```

### fx-corona.png · 1:1 · scatters, the Eclipse meter and the free-spins intro
```text
A radiant solar corona seen straight on: a perfectly black disc at the centre, surrounded by a blazing thin ring of white-gold light, from which long soft rays and delicate curling flares of gold and pale crimson stream outward all around 360 degrees, with a gentle haze. Centred and symmetrical, fading out before the edges. A light effect only, glowing, on a pure black (#000000) background with nothing else in the frame. No text.
```

### fx-twinkle.png · 1:1 · sparkle glint
```text
A single four-pointed sparkle star: a tiny brilliant white core, two long razor-thin rays (one horizontal, one vertical), two shorter diagonal rays and a soft pale-gold halo. Centred, with the rays fading out before the edges. A light effect only, glowing, on a pure black (#000000) background with nothing else in the frame. No text.
```

### fx-sweep.png · 21:9 · glow · the big-win and free-spins sweep
The game slides it across the whole screen, so it must reach the left, top and bottom edges.
```text
A massive torrent of golden starfire and cosmic dust sweeping from left to right: billowing glowing clouds of gold, amber and teal nebula, thousands of sparkling stars and embers, and streaks of white-gold light; its leading edge on the right is a tall, bright cresting wave of white-gold fire throwing sparks forward. It fills the image from the left edge, top to bottom, and its crest ends at about 80% of the width; the rest of the image to the right is pure black. Powerful, dramatic and beautiful. A light effect only, glowing, on a pure black (#000000) background. No text, no creatures.
```

---

## Celebration pieces (transparent)

### fx-treasure.png · 1:1 · big-win rain · attach sym-a and sym-s
I cut each piece out, so they must be separate.
```text
About 35 small separate pieces scattered evenly across the image with clear gaps, none touching each other or the edge: shiny gold coins stamped with an eclipse sigil like the one in the second attached image (a disc ringed by sun rays), seen at many angles (face-on, tilted, edge-on); small rough glowing natural stones in amber, violet, turquoise and ice white; and tiny chips of black obsidian with glowing gold cracks; all in different sizes. Each piece crisp, glossy and solid. Match the style and lighting of the first attached image. Premium AAA slot game art, stylised photoreal 3D render, glossy, rich saturated colours, crisp specular highlights, tack-sharp focus. No text, no cast shadow. On a transparent background, exported as a PNG with transparency.
```

---

## Lettering (transparent) · ChatGPT or Ideogram

Generate the banners after `ui-frame`, and attach `sym-a` and `ui-frame` so the lettering matches the materials. Check the spelling every time. Once `txt-logo` exists, attach it too whenever you regenerate a banner.

### Banners · 3:1 · attach sym-a and ui-frame

There is one prompt per banner; only the words and the material sentence change.

| File | Words | Material sentence |
|---|---|---|
| `txt-big-win.png` | `BIG WIN` | `polished gold letters with black obsidian bevels and thin glowing white-gold cracks, with bright sparkles` |
| `txt-mega-win.png` | `MEGA WIN` | `glowing teal crystal letters, opaque, with thick polished gold rims and a scatter of stardust` |
| `txt-epic-win.png` | `EPIC WIN` | `black obsidian letters split by blazing white-gold cracks with swirling blue nebula light inside them, rimmed in polished gold` |
| `txt-max-win.png` | `MAX WIN` | `blazing white-gold letters with black obsidian bevels, a fiery eclipse corona flaring behind them and sparks flying around them` |
| `txt-free-spins.png` | `FREE SPINS` | `polished gold letters with a deep crimson inner bevel and an eclipse corona glowing behind them` |
| `txt-total-win.png` | `TOTAL WIN` | `polished gold letters with black obsidian bevels and thin glowing white-gold cracks, with bright sparkles` |

```text
The words "BIG WIN" in chunky three-dimensional lettering on one line: polished gold letters with black obsidian bevels and thin glowing white-gold cracks, with bright sparkles. Bold, celebratory, perfectly legible, slightly arched. Match the materials and lighting of the attached images. Spell exactly: BIG WIN. Premium AAA slot game art, stylised photoreal 3D render, glossy, rich saturated colours, crisp specular highlights, tack-sharp focus. No other text, no cast shadow. On a transparent background, exported as a PNG with transparency. The whole word is in frame with a clear margin.
```

### txt-logo.png · 2:1 · attach sym-a and sym-m-meteor-sun
```text
The game logo in chunky three-dimensional lettering on two lines: "STONES" on top in polished gold letters with black obsidian bevels and thin glowing white-gold cracks; a small "OF THE" in polished gold between the lines; "TITAN" below it, the largest word, in black obsidian letters split by blazing white-gold cracks with swirling blue nebula light inside them, rimmed in polished gold. The letter O in STONES is a small total eclipse: a black disc ringed by a blazing white-gold corona. A few small glowing meteorite shards orbit the lettering, and one blazing meteor like the one in the second attached image streaks past behind the word TITAN. Majestic, premium and perfectly legible. Spell exactly: STONES OF THE TITAN. Match the finish of the first attached image. Premium AAA slot game art, stylised photoreal 3D render, glossy, rich saturated colours, crisp specular highlights, tack-sharp focus. No other text, no cast shadow. On a transparent background, exported as a PNG with transparency. The whole logo is in frame with a clear margin.
```

---

## Lobby tile (scene)

### key-thumbnail.png · 4:3 · attach char-titan-roar, bg-day-landscape and sym-m-meteor-sun
The logo goes on top afterwards; I compose it.
```text
Hero key art for the game's lobby tile: the titan from the first attached image, seen from the knees up on the right side of the image, raising his blazing sceptre high in a furious roar, eyes blazing white-gold, nebula mane flaring, every crack in his black obsidian body glowing; around him, glowing meteors like the one in the third attached image and gold eclipse coins burst toward the viewer in a spiral of golden starfire and stardust; the golden-nebula realm of the second attached image behind, with its ringed planet and floating crystal islands; shallow depth of field. The top third and the left side are calmer and slightly darker, because the logo goes there afterwards. Spectacular, majestic and premium. Painterly 3D, cinematic lighting, volumetric light, rich detail, premium AAA slot game key art. No text, no logo. Avoid: lightning, marble columns, faceted gemstones, purple skin, a helmet, a glove or gauntlet.
```

---

## What I do when the art arrives

**The build script:** `scripts/art/process-stones-of-the-titan.py` does the following:
- keys the frame's magenta;
- turns the glow sheets' black into light;
- splits the sheets (`fx-stardust`, `fx-embers`, `fg-asteroids`, `fx-treasure`) into single pieces;
- measures each pose: the feet line, the head, the open palm, the fist and the sceptre's star;
- packs everything as WebP into `public/images/stones-of-the-titan/`, with an `art.json`.

**Made in code, not art:**
- the drifting nebula and the starfield with depth;
- the pulse of light in the Titan's cracks, which comes from his own bright pixels;
- the drift of his mane and cloak;
- each symbol cracking into shards of its own stone;
- the meteors' flight into his fist;
- the growing corona of the Eclipse;
- the eclipse itself at the free-spins intro;
- the gravity-lens shimmer of the scatter tease;
- the grid, the bar, every word on the plaques and cards, and every number.

**Thumbnail:** I compose the lobby thumbnail from `key-thumbnail` and the logo.
