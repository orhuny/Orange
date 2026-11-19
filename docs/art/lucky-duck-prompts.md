# Lucky Duck: art prompts

Audience: whoever generates Lucky Duck's art. Where the art goes and how it is built: [README.md](README.md).

These are the round-1 prompts from the game's plan (2026-11-18). Later art came from variations of them, delivered with the names that `scripts/art/process-lucky-duck.py` reads: the fox hunting from a tub boat, the BANG! word, the dizzy stars and the duck coming up out of the pond. The raw deliveries are in `storage/app/art/lucky-duck/` (not in git).

## How to use them

- Generate in this order. Prompts 1-2 make the **reference sheets**. Attach them as reference images to every later prompt that shows that character.
- Prepend the **STYLE** block to every prompt.
- Sprites and strips must be on **flat magenta `#FF00FF`**, the cut-out key. Magenta is safe because the marsh palette (greens, blues, browns) has none. The mallard's green head would clash with a green-screen key.
- Deliver the highest resolution PNG you can get. I resize, cut and pack. If a sheet's frames come out inconsistent, generate it again or send several takes, and I'll pick the frames (as for the 36-frame implosion).
- The only text allowed is in prompts 17 and 18.

**STYLE (prepend to every prompt):**
> Bright, polished 2D cartoon illustration for a premium mobile casino game. Clean, bold dark-brown outlines of even weight, flat cel shading with one highlight and one shadow tone, soft warm rim light, saturated cheerful colours, friendly and funny, family-friendly, no blood or gore. No text, no letters, no watermark, no signature, no UI, no border.

**CUTOUT (append to every sprite and strip prompt):**
> Isolated on a perfectly flat solid magenta background (#FF00FF): no gradient, no texture, no cast shadow on the background, no other objects. The subject does not touch the image edges; keep a clear margin all round.

---

**1. Duck model sheet** (reference only, not shipped) · 16:9
> Character model sheet of "Lucky", a cocky cartoon mallard drake: glossy emerald-green head, thin white neck ring, chestnut-brown chest, soft grey body, curled black tail feather, bright orange bill and webbed feet, big expressive eyes with a smug half-lidded grin. He wears a small red bandana knotted round his neck and a shiny gold four-leaf-clover medallion on a short gold chain. Show him four times, evenly spaced and the same size: standing side view facing right; three-quarter view; flying side view facing right with wings fully spread; a close-up head with a confident wink. Plain light-grey background.

**2. Fox model sheet** (reference only) · 16:9
> Character model sheet of a scheming cartoon red fox hunter: lanky, orange-red fur with a white muzzle and chest, bushy white-tipped tail, narrow sly eyes and a toothy over-confident grin. He wears a too-big mustard-and-brown tweed hunting jacket with elbow patches and a green felt hat with a long pheasant feather. He holds an antique blunderbuss: dark polished wooden stock, brass barrel ending in a wide flared bell muzzle. Show him four times, evenly spaced and the same size: standing side view facing right holding the blunderbuss; three-quarter view; crouching and aiming up and to the right; a close-up head with a scheming grin. Plain light-grey background.

**3. Day sky, wide** → `sky-day-landscape.jpg` · 16:9
> A bright, cheerful late-morning sky over a marsh, sky only: clear saturated blue fading to a pale warm haze toward the bottom, a soft glowing sun in the upper left, two or three very faint wispy high clouds. The bottom 30% is a soft pale haze where the horizon would be, with no land, water or plants anywhere. Wide 16:9 composition, nothing in the centre.

**4. Day sky, phone** → `sky-day-portrait.jpg` · 9:16
> Same as prompt 3 in a tall 9:16 composition: deeper blue at the top, the sun glow at the upper right, the pale hazy horizon in the bottom 25%. No land, no water, sky only. (Attach prompt 3's result as the reference.)

**5. Sunset sky, wide** → `sky-sunset-landscape.jpg` · 16:9
> The same sky as the attached reference, same composition, at sunset: warm orange and peach near the horizon, rose and lavender higher up, deep violet at the very top, a large soft golden sun low on the right just above the hazy horizon, a few thin glowing pink clouds. The bottom 30% is the hazy horizon, with no land or water. Sky only.

**6. Sunset sky, phone** → `sky-sunset-portrait.jpg` · 9:16
> Prompt 5's sunset sky in a tall 9:16 composition, matching prompt 4's layout. Sky only.

**7. Far hills strip** → `strip-hills.webp` (tiles horizontally, slowest layer) · 16:9
> A wide horizontal band of distant scenery seen from a marsh, filling only the bottom 35% of the image: soft rolling blue-green hills, a hazy treeline, a tiny far-off windmill and a small farmhouse. Pale and slightly desaturated with atmospheric haze. The left and right edges must line up at exactly the same height so the band tiles seamlessly side by side. Everything above the hills is solid magenta (#FF00FF). + CUTOUT

**8. Marsh water strip** → `strip-marsh.webp` (middle layer) · 16:9
> A wide horizontal band of calm marsh water filling the bottom 40% of the image: gentle blue water with soft white highlights and ripples, scattered lily pads with a few pink water lilies, low clumps of reeds and rushes at the far waterline, a muddy grassy bank along the top edge of the band. The left and right edges must match so it tiles seamlessly. Everything above is solid magenta (#FF00FF). + CUTOUT

**9. Foreground reeds strip** → `strip-reeds-front.webp` (fastest layer, in front) · 16:9
> A wide horizontal band of very close foreground marsh plants filling the bottom 45% of the image: tall green reeds and grasses, brown cattails (bulrushes) at varied heights, a few broad leaves, rich greens with darker shadows at the base. Uneven, natural top silhouette. The left and right edges must match so it tiles seamlessly. Everything above is solid magenta (#FF00FF). + CUTOUT

**10. Clouds** → `clouds.webp` (6 sprites) · 3:2
> Six separate fluffy cartoon cumulus clouds in a loose 3×2 arrangement with wide gaps, all different shapes (two big, two medium, two small and flat), white with soft lavender-blue shading underneath and a warm top highlight. None touch each other. + CUTOUT

**11. Duck flight cycle** → `duck-fly.webp` (8 frames) · 2:1, attach sheet 1
> A sprite sheet of the duck from the reference (Lucky, red bandana, gold clover medallion) flying, side view facing right: 8 frames of one smooth wing-flap cycle in a 4-column × 2-row grid read left to right, top to bottom. Wings fully up, coming down, level, fully down, pushing back, rising, level, nearly up. The body, head and size stay identical in every frame; only the wings, a slight body bob and the bandana's flutter change. Each frame is centred in an equal invisible cell with generous spacing, no grid lines. + CUTOUT

**12. Duck idle and take-off** → `duck-takeoff.webp` (8 frames) · 2:1, attach sheet 1
> A sprite sheet of the same duck, side view facing right, 8 frames in a 4×2 grid. Frames 1-2: floating on the water line (only a flat little water ripple under him), relaxed and smug, then preening a wing. Frames 3-8, the take-off: crouch with wings raised; first wing beat with a splash of water droplets; running on the water with big splashes; lifting off with droplets falling; climbing with wings down; airborne and level. Same scale in every frame, equal cells, generous spacing, no grid lines. + CUTOUT

**13. Duck hit sheets, a to f** → packed into `duck-hit.webp` (36 frames, 6×6). Six prompts, one per beat, each a 3×2 sheet of 6 frames. Attach sheet 1. Same scale and centring in every frame, equal cells, no grid lines. + CUTOUT
> Prefix for all six: "A sprite sheet of the same cartoon duck, side view facing right, 6 frames in a 3-column × 2-row grid showing, in order:"
> - **a:** "a puff of grey cartoon smoke bursts against his side, his eyes pop wide, beak open in shock, feathers start to fly"
> - **b:** "a big round soot-and-smoke explosion engulfs him, a ring of loose feathers bursting outward, small orange sparks"
> - **c:** "he comes out of the smoke covered in black soot with frazzled feathers, dazed spiral eyes, little yellow stars circling his head, the bandana singed"
> - **d:** "he starts spinning and tumbling downward head over tail, wings limp, feathers trailing, the medallion swinging"
> - **e:** "still tumbling and falling, smaller as he drops away, a trail of loose feathers and a wisp of smoke"
> - **f:** "only loose feathers drifting down in a gentle zigzag, a last soot wisp fading away, no duck"

**14. Fox body in the blind** → `fox-body.webp` · 1:1, attach sheet 2
> The fox hunter from the reference crouching behind a duck blind on a muddy bank, facing right and looking up and to the right. The blind is a low, rough screen of bundled reeds lashed to weathered wooden planks, covering him from the chest down. His head, hat, shoulders and upper chest show above it. **Both arms are lowered out of sight behind the blind; no paws or gun are visible.** Some reeds and cattails in front of the blind. + CUTOUT

**15. Fox gun-arm** → `fox-gun.webp` (a separate layer, rotated in code) · 2:1, attach sheet 2
> Only the fox hunter's two forearms in the tweed jacket's sleeves, with orange-furred paws gripping the antique blunderbuss (dark polished wood stock, brass barrel, wide flared bell muzzle). The gun lies perfectly horizontal, pointing right. Nothing else: no body, no head. The sleeves end cleanly at the left where the shoulder would be. Plain side view. + CUTOUT

**16. Muzzle flash and smoke** → `muzzle.webp` (6 frames) · 3:2
> A sprite sheet of a cartoon blunderbuss shot coming out of a muzzle pointing right, 6 frames in a 3×2 grid: a small bright yellow-white flash; a big star-shaped orange-yellow flash with sparks; the flash breaking up into a round white-grey smoke puff; the puff growing and swirling; the puff drifting and thinning; a few last wisps. The source point is on the left edge of each cell, at the same height in every frame. No gun drawn. + CUTOUT

**17. Lobby thumbnail** → uploaded in the admin panel · **4:3** (lobby tiles are 4:3 `object-fit: cover`, so keep the key art away from the edges), attach sheets 1 and 2
> Key art for a casino game tile: the duck from the reference flying boldly toward the viewer and up to the right across a bright marsh sky, wings spread, a cocky grin, gold coins and golden feathers sparkling in his wake. In the lower-left corner, the fox hunter peeks out of his reed blind aiming the blunderbuss, a comic sweat drop on his brow. Reeds and cattails frame the bottom, with soft clouds behind. Across the top centre, the game logo "LUCKY DUCK" in chunky rounded cartoon letters, glossy gold with a thick dark-green outline and a white highlight, a small four-leaf clover dotting the composition. Vibrant, high contrast, readable at small size. Full scene, no magenta background.

**18. Logo** → `logo.webp` (intro title, lobby hero) · 2:1
> A game logo reading exactly "LUCKY DUCK" (check the spelling) in chunky, bouncy, rounded cartoon letters, glossy gold with a thick dark-green outline, a white shine on top and a soft inner shadow. The two words are stacked or slightly arched. A small cartoon mallard head with a red bandana peeks over the top of the second "K", and a tiny green four-leaf clover forms the dot of a sparkle. + CUTOUT

**19. Extras (optional)** → `extras.webp` · 3:2
> Six separate small cartoon sprites in a 3×2 arrangement with wide gaps: a shiny gold coin with a four-leaf-clover emblem; a golden glowing feather; a blue-green dragonfly in side view; a small green frog sitting on a lily pad; a tiny distant V-formation of three bird silhouettes in soft grey-blue; a glowing yellow firefly. + CUTOUT

**Not generated (done in code):** dusk and night tint, stars, fireflies' glow and motion, water sparkle, parallax motion, gun aiming and recoil, screen flash, shockwave.
