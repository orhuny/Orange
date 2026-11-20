# Game art

Audience: anyone adding or changing a game's art. How a game uses its art: [adding-a-game.md](../adding-a-game.md) (crash scenes) and [slots/README.md](../slots/README.md) (slot themes).

## Where everything lives

| What | Where | In git |
|---|---|---|
| A game's served art, one folder per game | `public/images/{game}/` | yes |
| Its lobby thumbnail: a 4:3 JPEG, uploaded in the admin panel | `public/images/{game}/thumbnail.jpg` | yes |
| The script that builds the served art from the raw deliveries | `scripts/art/process-{game}.py` | yes |
| The prompts the art was generated from | `docs/art/{game}-prompts.md` | yes |
| Raw deliveries from the image generator | `storage/app/art/{game}/` | **no**: large files, keep a backup |
| Where a look is wired up: crash scenes | `resources/views/crash/scenes/{scene}/` (`scene.php` maps the art) | yes |
| Where a look is wired up: slot themes | `resources/views/games/slot/themes/{theme}/theme.php` | yes |
| Studio intro frames for crash games (optional, empty for now) | `public/images/intro/{landscape,portrait}/` | yes |
| Fonts, each with its licence | `public/fonts/{font}/` | yes |
| The admin panel's brand, Majestic Games: the logo | `resources/views/filament/brand-logo.blade.php` | yes |
| The admin panel's brand: the favicon | `public/images/majestic/favicon.svg` | yes |
| The admin panel's brand: the stylesheet (plain CSS, no build) | `public/css/majestic-admin.css` | yes |
| Sound packs, synthesised in code (no audio files) | `public/js/orange/sound-{pack}.js` | yes |

## The games

| Game | Type and look | Served art | Build script | Prompts |
|---|---|---|---|---|
| Deep Dive | crash, scene `dive`; skins Jackpot Reef and Gothic Nightmare | `public/images/deep-dive/` | none: made before the pipeline | none |
| Lucky Duck | crash, scene `marsh` | `public/images/lucky-duck/` | `process-lucky-duck.py` (Pillow and numpy) | [lucky-duck-prompts.md](lucky-duck-prompts.md) |
| Sugar Orchard | slot, theme `sugar-orchard` | `public/images/sugar-orchard/` (symbols in `symbols/`) | `process-sugar-orchard.py` (Pillow) | [sugar-orchard-prompts.md](sugar-orchard-prompts.md) |
| Trident of Poseidon | slot, theme `trident-of-poseidon` | `public/images/trident-of-poseidon/` (symbols in `symbols/`, Poseidon in `host/`) | `process-trident-of-poseidon.py` (Pillow, helpers in `artlib.py`) | [trident-of-poseidon-prompts.md](trident-of-poseidon-prompts.md) |

Deep Dive's thumbnail is only in the admin panel. If you have its source, save it as `public/images/deep-dive/thumbnail.jpg`.

## Rules

- **Folder name:** the game's name in kebab-case (`lucky-duck`), not its scene or skin key.
- **File names:** lower-case kebab-case, the thing first and its variant after: `bg-day-landscape.webp`, `sub-jackpot-hull.webp`, `duck-fly.webp`.
- **Formats:**
  - WebP for cut-outs and sprites (transparent);
  - JPEG or WebP for full pictures;
  - `thumbnail.jpg` for the lobby tile.
- **Built, not edited:** a game with a build script owns its folder. To change a file, change the raw delivery or the script and rerun it:
  ```
  python -I scripts/art/process-sugar-orchard.py
  ```
  The Sugar Orchard script takes `--out DIR` to build somewhere else and compare.
- **Raw deliveries:**
  - Cut-outs come on flat magenta `#FF00FF`.
  - Sugar Orchard's light effects come on black and are blended as light.
  - Trident of Poseidon's cut-outs come as transparent PNGs (water, pearls and foam are partly see-through); only its frame comes on magenta.
  - Name the files as the prompts doc does, in `storage/app/art/{game}/`. Sugar Orchard's round 3 goes in `round3/`, Trident of Poseidon's round 1 in `round1/`.
- **Every served file is used by its game.** Remove what nothing references, unless its build script makes it.
- **Referencing art:** use `asset('images/{game}/…')`, with `?v=` and the file's `filemtime` where the file may change. Then players get a new file without clearing their cache.
