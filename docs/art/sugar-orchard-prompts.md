# Sugar Orchard: görsel prompt'ları

Slot "Sugar Orchard" (çalışma adı) için üretilecek görseller. Prompt'lar İngilizce, çünkü görsel modelleri İngilizceyle daha iyi sonuç veriyor.

## Durum

| Grup | Durum |
|---|---|
| **Tur 1, 01–10** (stil paftası ve 9 sembol) | Kullanılıyor. Key'lendi ve `public/images/sugar-orchard/symbols/` altına paketlendi. Prompt'ları aşağıda; bir sembolü yeniden üretmek gerekirse aynı aileden çıksın diye duruyor. |
| **Tur 1, 11–26** | Kullanılmıyor. Düz vektör olarak geldi, sembollerle uyuşmadı. |
| **Tur 2, 27–34** | Yalnızca görsel modeli gerektiren 8 görsel; 34 isteğe bağlı. |

Kodla yapılacaklar:
- patlama ve parçacık efektleri
- scatter kutlaması
- kasa çerçevesi (34'teki dokuyla)
- kurdele ve paneller
- spin butonu
- konfeti

Lobi thumbnail'i de üretilmeyecek: sembollerden, yeni arka plandan ve logodan ben birleştireceğim.

## Nasıl üretilir (önemli)

- **Her prompt'u ayrı bir istek olarak üret.** "Hepsini birden yap" denince araç bir kısmını kodla çiziyor. Tur 1'deki düz görseller büyük ihtimalle bundan oldu.
- **Referans görselleri ekle:**
  - 27, 28 ve 33'e `01-symbol-sheet.png`'yi
  - 30 ve 31'e 29'u
  - 32'ye 30 ile 31'i
- **Logo (33):** Yazıyı en doğru Ideogram veya ChatGPT görsel üretimi çıkarıyor. Harfleri tek tek kontrol et.
- **Kesilecek görseller (27, 28, 33):** Düz magenta `#FF00FF` zemin üzerinde olmalı. Üzerlerinde pembe, mor, saydam cam ya da parlama olmamalı, çünkü magenta bunların içinden görünüyor.
- **Arka planlar (29–32):** Tam sahne, magenta yok.
- **Teslim:** En yüksek çözünürlükte PNG, aşağıdaki dosya adlarıyla `C:\Git\Orange\storage\app\art\sugar-orchard\` klasörüne. Bir görsel beğenmediğin gibi çıkarsa 2–3 deneme gönder, en iyisini seçerim.

---

## Tur 2

### 27-scatter-basket.png · 1:1 · 01'i ekle
Scatter artık sakız makinesi değil, meyve ve şeker dolu altın bir sepet. Bahçe temasına daha çok uyuyor, opak olduğu için temiz kesiliyor ve küçük boyutta da diğer sembollerden ayrışıyor.
```
High-end mobile casino slot game asset with a semi-realistic 3D-render look, like a premium slot from a top studio: glossy, juicy, tactile materials, soft studio key light from the upper left, crisp specular highlights, gentle ambient occlusion, rich saturated warm colours, slightly stylised chunky proportions. Not flat, not vector, not cartoon line art, not clip art. No text, no numbers, no watermark. A single special slot symbol, clearly the most precious one in the game: a small round woven wicker basket in polished golden straw, overflowing with treats — a shiny orange with a leaf, a pair of burgundy cherries, a bright red strawberry, a blue gummy bear and two gold-foil-wrapped toffees — with a big glossy red satin bow tied on its arched handle and tiny gold sparkles caught in the weave. Compact, about as wide as it is tall, centred, three-quarter front view, filling about 85% of the square. Match the style, lighting and finish of the attached symbol sheet exactly. Isolated on a perfectly flat solid magenta background (#FF00FF): no gradient, no texture, no cast shadow, no glow or aura, no other objects. No pink, magenta or purple anywhere on the subject. Everything opaque, nothing transparent. Keep a clear margin all round.
```

### 28-multiplier-stars.png · 1:1 · 01'i ekle
Sayılar (2×, 10×, 100×…) yıldızın ortasına kodla yazılacak.
```
High-end mobile casino slot game asset with a semi-realistic 3D-render look, like a premium slot from a top studio: glossy, juicy, tactile materials, soft studio key light from the upper left, crisp specular highlights, gentle ambient occlusion, rich saturated warm colours, slightly stylised chunky proportions. Not flat, not vector, not cartoon line art, not clip art. No text, no numbers, no watermark. Four separate multiplier gems in a 2×2 grid with wide gaps: each is a chunky five-pointed star made of glossy faceted hard candy, cut like a jewel, with softly rounded points, a thick polished gold rim around its edge, and a smooth, slightly domed, calm centre with no pattern (a number will be printed over it later). All four have exactly the same shape, size and straight front view; only the candy colour changes: top left sky blue, top right emerald green, bottom left warm honey gold, bottom right deep ruby red. Light seems to glow inside the candy while it stays opaque. Match the finish of the attached symbol sheet. Isolated on a perfectly flat solid magenta background (#FF00FF): no gradient, no texture, no cast shadow, no glow or aura, no other objects. No pink, magenta or purple anywhere on the stars. Everything opaque, nothing transparent. Keep a clear margin around each star.
```

### 29-bg-day-landscape.png · 16:9
```
Lush painted background for a premium mobile slot game, semi-realistic 2.5D digital painting with real depth: blurred foreground, detailed midground, hazy distance, atmospheric perspective, soft bokeh, warm volumetric light. Not flat, not vector, not minimal, no hard outlines, no characters, no text, no UI. A whimsical fruit-and-candy orchard at golden hour, seen from a slight height across a green valley: rows of round, bushy fruit trees heavy with glowing oranges, lemons and cherries, with gold-wrapped sweets and red-and-white peppermint candies hanging among the fruit like ornaments; a few trees with twisting red-and-white candy-cane trunks; rolling hills fading into warm haze; a big low sun behind the hills casting long golden rays; soft cream clouds in an amber-to-peach sky. At the far left and far right edges, close to the viewer and softly out of focus: stacked wooden fruit crates spilling oranges, wicker baskets, tall grass and white blossoms. The middle 55% of the width is calm and low in contrast — open sky, distant hills and haze only — because the game grid will cover it. Rich colour and detail at the edges.
```

### 30-bg-day-portrait.png · 9:16 · 29'u ekle
```
Lush painted background for a premium mobile slot game, semi-realistic 2.5D digital painting with real depth: blurred foreground, detailed midground, hazy distance, atmospheric perspective, soft bokeh, warm volumetric light. Not flat, not vector, not minimal, no hard outlines, no characters, no text, no UI. The same golden-hour candy-fruit orchard as the attached image, same palette, light and painting style, recomposed as a tall 9:16 phone screen: the top quarter shows the amber sky, the glowing low sun and the leafy tops of candy-fruit trees curving in from both sides like a frame; the middle half is calm and low in contrast — distant hills and warm haze only — because the game grid will cover it; the bottom quarter shows a grassy foreground, softly out of focus, with a wooden fruit crate, a wicker basket and a few fallen oranges.
```

### 31-bg-night-landscape.png · 16:9 · 29'u ekle
```
Lush painted background for a premium mobile slot game, semi-realistic 2.5D digital painting with real depth: blurred foreground, detailed midground, hazy distance, atmospheric perspective, soft bokeh, warm volumetric light. Not flat, not vector, not minimal, no hard outlines, no characters, no text, no UI. The same orchard as the attached image, same composition and camera, transformed into a magical festival night for the free spins: a deep teal-to-navy sky with a large soft moon and scattered stars, strings of warm glowing paper lanterns looping between the candy trees, fireflies sparkling over the grass, the hanging fruits and sweets catching the warm lantern light, a gentle blue moonlit haze over the hills. The middle 55% of the width stays calm and low in contrast, as in the original.
```

### 32-bg-night-portrait.png · 9:16 · 30 ve 31'i ekle
```
Lush painted background for a premium mobile slot game, semi-realistic 2.5D digital painting with real depth: blurred foreground, detailed midground, hazy distance, atmospheric perspective, soft bokeh, warm volumetric light. Not flat, not vector, not minimal, no hard outlines, no characters, no text, no UI. The festival-night orchard of the attached landscape image (moon, stars, glowing paper lanterns, fireflies, warm lantern light on the fruit and sweets), recomposed exactly like the attached tall daytime phone image: trees and lanterns framing the top quarter, a calm low-contrast middle half for the game grid, and a softly blurred grassy foreground with a crate and a basket in the bottom quarter.
```

### 33-logo.png · 2:1 (ya da 16:9) · 01'i ekle · Ideogram veya ChatGPT önerilir
```
High-end mobile casino slot game logo with a semi-realistic 3D-render look: glossy, juicy, tactile materials, soft studio key light from the upper left, crisp specular highlights. Not flat, not vector, not clip art. A game logo that reads exactly "SUGAR ORCHARD" (check the spelling letter by letter). Two stacked words in chunky, bouncy, rounded 3D letters, slightly arched: "SUGAR" made of glossy golden-orange hard candy with a fine white sugar-sparkle coating on its top edges, and "ORCHARD" below it in glossy fresh leaf-green candy; both words have a thick dark chocolate-brown outline and a soft drop shadow beneath. Small decorations: a shiny orange with two leaves sitting on top of the last "R" of SUGAR, a pair of burgundy cherries hanging from the "O" of ORCHARD, and a small red-and-white peppermint swirl. Match the finish of the attached symbol sheet. Isolated on a perfectly flat solid magenta background (#FF00FF): no gradient, no texture, no glow or aura, nothing else. No pink, magenta or purple in the logo. Keep a clear margin all round.
```

### 34-wood-texture.png · 1:1 · isteğe bağlı (ızgaranın kasa çerçevesi için)
```
Seamless tileable square texture: warm honey-brown wooden planks of a rustic fruit crate, seen straight on, planks running horizontally, visible grain, a couple of small knots and tiny dark nail heads, softly painted with gentle highlights. Even, flat lighting with no shadows, no vignette and no perspective; it must tile perfectly on all four sides. No text.
```

---

## Tur 1 (teslim edildi, kullanılıyor): stil paftası ve semboller 01–10

STYLE (1–10'un başına):
> Premium casino slot game art, polished semi-3D illustrated style: glossy, juicy, tactile surfaces with soft studio lighting from the upper left, one crisp specular highlight per object, gentle ambient occlusion, rich saturated warm colours, appetising and cheerful, slightly stylised proportions (chunky, rounded, toy-like), no outlines. Family-friendly. No text, no letters, no numbers, no watermark, no signature, no UI, no border.

CUTOUT (2–10'un sonuna):
> Isolated on a perfectly flat solid magenta background (#FF00FF): no gradient, no texture, no cast shadow, no glow or aura around the subject, no other objects. No pink, magenta or purple anywhere on the subject. Everything is opaque; nothing is transparent or see-through. The subject does not touch the image edges; keep a clear margin all round.

### Semboller

**1. Stil referans paftası** → `01-symbol-sheet.png` (oyunda kullanılmayacak) · 16:9
> A slot-game symbol style sheet: ten symbols in a 5-column × 2-row grid, evenly spaced, all the same size and seen from the same three-quarter front angle. Top row (sweets): a gold-foil-wrapped toffee with twisted ends; a round red-and-white peppermint swirl disc; an opaque glossy blue gummy bear; a round dark-chocolate truffle with a glossy chocolate drizzle and a few gold sugar flecks. Bottom row (fruits and the special symbol): a whole shiny orange with two green leaves; a pair of deep burgundy cherries joined at green stems; a big bright-red strawberry with green leaves and golden seeds; a plump yellow lemon with one leaf; a kiwi cut in half showing its bright green flesh, black seeds and white centre. Below or beside them, slightly larger: a vintage gumball machine with a red cast-metal base and a coin slot, its round top completely filled with opaque multicoloured gumballs (red, orange, yellow, green, blue, white). Plain light warm-grey background.

**2. Karamela (H1, en yüksek)** → `02-toffee.png` · 1:1 · 1'i ekle
> A single slot symbol: a large toffee candy wrapped in shiny crinkled gold foil with two twisted, fanned ends, the wrapper slightly open in the middle to reveal glossy amber-brown toffee. Centred, three-quarter front view, filling about 80% of the square, matching the attached style sheet exactly. + CUTOUT

**3. Nane şekeri (H2)** → `03-peppermint.png` · 1:1 · 1'i ekle
> A single slot symbol: a thick round peppermint hard candy with a bold red-and-white pinwheel swirl, a glassy-looking but opaque glossy surface with one bright highlight, seen three-quarter front so its thickness shows. Centred, filling about 80% of the square, matching the attached style sheet exactly. + CUTOUT

**4. Gummy ayı (H3)** → `04-gummy-bear.png` · 1:1 · 1'i ekle
> A single slot symbol: a chunky, cheerful blue-raspberry gummy bear standing, rich opaque saturated blue with a soft sugary sheen and gentle highlights, simple cute rounded shape, tiny dimple eyes pressed into the candy (no drawn face). Centred, three-quarter front view, filling about 80% of the square, matching the attached style sheet exactly. + CUTOUT

**5. Trüf (H4)** → `05-truffle.png` · 1:1 · 1'i ekle
> A single slot symbol: a round dark-chocolate truffle with a glossy milk-chocolate drizzle zig-zagged across the top and a sprinkle of gold sugar flecks, sitting in a small fluted gold paper cup. Centred, three-quarter front view, filling about 80% of the square, matching the attached style sheet exactly. + CUTOUT

**6. Portakal (L1)** → `06-orange.png` · 1:1 · 1'i ekle
> A single slot symbol: a whole round shiny orange with a dimpled peel, a short stem and two glossy green leaves on top. Centred, three-quarter front view, filling about 80% of the square, matching the attached style sheet exactly. + CUTOUT

**7. Kiraz (L2)** → `07-cherries.png` · 1:1 · 1'i ekle
> A single slot symbol: a pair of plump, deep burgundy-red cherries hanging from two green stems joined at the top with one small green leaf, glossy highlights on each cherry. Centred, filling about 80% of the square, matching the attached style sheet exactly. + CUTOUT

**8. Çilek (L3)** → `08-strawberry.png` · 1:1 · 1'i ekle
> A single slot symbol: a big bright-red strawberry with a fresh green leafy crown, small golden seeds and a juicy glossy surface. Centred, three-quarter front view, filling about 80% of the square, matching the attached style sheet exactly. + CUTOUT

**9. Limon (L4)** → `09-lemon.png` · 1:1 · 1'i ekle
> A single slot symbol: a plump, sunny-yellow lemon with a textured peel, pointed ends and one green leaf at the stem, lying at a slight diagonal. Centred, filling about 80% of the square, matching the attached style sheet exactly. + CUTOUT

**10. Kivi (L5)** → `10-kiwi.png` · 1:1 · 1'i ekle
> A single slot symbol: a kiwi fruit cut in half, the cut face turned toward the viewer showing bright green juicy flesh, a ring of small black seeds and a creamy white centre, the fuzzy brown skin visible at the back edge. Centred, filling about 80% of the square, matching the attached style sheet exactly. + CUTOUT

