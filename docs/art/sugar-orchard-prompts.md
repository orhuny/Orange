# Sugar Orchard: görsel prompt'ları

Slot "Sugar Orchard" (çalışma adı) için üretilecek görseller. Prompt'lar İngilizce, çünkü görsel modelleri İngilizceyle daha iyi sonuç veriyor.

## Nasıl kullanılır

- **Teslim:** Ham dosyaları `C:\Git\Orange\storage\app\art\sugar-orchard\` klasörüne, aşağıdaki dosya adlarıyla bırak (ör. `02-toffee.png`). Alabildiğin en yüksek çözünürlükte PNG olsun. Kesme, key'leme, boyutlandırma ve paketleme işini ben yapıyorum.
- **Sıra:** Önce **1 numarayı** üret. Bu, stil referans paftası; oyunda kullanılmayacak. 2–13 arasındaki her prompt'a bunu referans görsel olarak ekle ki semboller aynı aileden görünsün. Arka planlarda 16 ve 17 numara, 18 ve 19 için referans.
- **Stil:** **STYLE** bloğunu her prompt'un başına ekle.
- **Arka planı kesilecek görseller** (sprite, sembol, efekt, çerçeve, logo): Sonlarına **CUTOUT** bloğunu ekle. Bunlar düz magenta `#FF00FF` zemin üzerinde olmalı. Bu yüzden bu görsellerde hiç pembe, mor veya magenta ton bulunmamalı. Cam, saydamlık ve parlama (glow) da olmamalı, çünkü magenta bunların içinden görünür ve temiz kesilemez. Parlamaları ben kodla ekliyorum.
- **Yazı:** Sadece 23 (logo) ve 24 (lobi thumbnail'i) yazı içeriyor. Diğer tüm yazıları (BIG WIN, FREE SPINS, çarpan sayıları vb.) kodda ben yazıyorum, böylece çeviri ve yazım hatası sorunu olmuyor.
- **Tekrar üretme:** Bir sembol diğerlerinden farklı bir stilde çıkarsa yeniden üret ya da birkaç deneme gönder, en iyisini ben seçerim.
- **İsim:** Oyunun adı henüz kesin değil. Ad değişirse sadece 23 ve 24'ü yeniden üretmen yeterli.

Renkler, magenta key'e ve küçük boyutta ayırt edilebilmeye göre seçildi:

| Sembol | Renk |
|---|---|
| karamela | altın |
| nane şekeri | kırmızı-beyaz |
| gummy ayı | **mavi** (planda yeşil yazıyordu; kiviyle karışmasın diye maviye çevirdim) |
| trüf | kahverengi |
| portakal | turuncu |
| kiraz | bordo |
| çilek | parlak kırmızı |
| limon | sarı |
| kivi | yeşil |

---

**STYLE (her prompt'un başına):**
> Premium casino slot game art, polished semi-3D illustrated style: glossy, juicy, tactile surfaces with soft studio lighting from the upper left, one crisp specular highlight per object, gentle ambient occlusion, rich saturated warm colours, appetising and cheerful, slightly stylised proportions (chunky, rounded, toy-like), no outlines. Family-friendly. No text, no letters, no numbers, no watermark, no signature, no UI, no border.

**CUTOUT (sembol, sprite ve efekt prompt'larının sonuna):**
> Isolated on a perfectly flat solid magenta background (#FF00FF): no gradient, no texture, no cast shadow, no glow or aura around the subject, no other objects. No pink, magenta or purple anywhere on the subject. Everything is opaque; nothing is transparent or see-through. The subject does not touch the image edges; keep a clear margin all round.

---

## Semboller

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

**11. Scatter: sakız makinesi** → `11-scatter.png` · 1:1 · 1'i ekle
> A single special slot symbol, more detailed and prestigious than the others: a vintage gumball machine with a shiny red cast-metal base, a chrome coin mechanism with a little turning handle and a small chrome chute, topped by a round dome completely packed with opaque glossy gumballs in red, orange, yellow, green, blue and white (no gaps, nothing behind the dome visible), a small gold star finial on top. Centred, front three-quarter view, filling about 85% of the square, matching the attached style sheet. + CUTOUT

**12. Scatter kutlama animasyonu** → `12-scatter-celebrate.png` (6 kare) · 3:2 · 11'i ekle
> A sprite sheet of the gumball machine from the reference, 6 frames in a 3-column × 2-row grid, read left to right, top to bottom: at rest; squashing down slightly; stretching up tall with the handle turning; a few gumballs popping up out of the dome; the dome bouncing back with gumballs in the air; settling back to rest. Same size, angle and centring in every frame, equal invisible cells with generous spacing, no grid lines. + CUTOUT

**13. Çarpan yıldızları (4 kademe)** → `13-multiplier-stars.png` · 1:1 · 1'i ekle
> Four separate chunky five-pointed star-shaped hard candies in a 2×2 grid with wide gaps, all the same shape, size and angle (straight front view), with softly rounded faceted points and a glossy opaque sugar-glass finish. Each has a large flat, plain, smooth circular area in its centre left completely empty (a number will be added later). Colours: top left sky blue; top right emerald green; bottom left rich gold with tiny sugar sparkles; bottom right deep ruby red with a gold rim. + CUTOUT

## Efektler

**14. Meyve patlaması (meyve sembolleri için)** → `14-fruit-burst.png` (6 kare) · 3:2
> A sprite sheet of a cartoon juice-splash burst, 6 frames in a 3×2 grid, in pure white and very light grey only (it will be tinted in code): a small round splash; a big star-shaped splash with droplets flying outward; the splash breaking into many droplets; droplets spreading wider and smaller; a few scattered droplets; the last tiny droplets fading. The burst is centred in every cell, same scale reference, equal invisible cells, no grid lines. + CUTOUT

**15. Şeker kırılması (şeker sembolleri için)** → `15-candy-burst.png` (6 kare) · 3:2
> A sprite sheet of a cartoon candy-shatter burst, 6 frames in a 3×2 grid, in pure white and very light grey only (it will be tinted in code): a bright crack flash; the candy bursting into angular sugar shards and little four-point sparkles; shards flying outward; shards spreading and shrinking; a few tiny sugar crystals; the last sparkles fading. Centred in every cell, equal invisible cells, no grid lines. + CUTOUT

## Arka planlar ve çerçeve

**16. Bahçe, gündüz, yatay** → `16-bg-day-landscape.jpg` · 16:9
> A whimsical fantasy orchard at golden hour, a wide painterly background for a slot game: rolling green hills with rows of plump round trees whose branches bear oranges, lemons and cherries mixed with wrapped sweets and peppermint candies hanging like fruit; a few trees with red-and-white striped candy-cane trunks; wooden fruit crates and baskets stacked at the edges; a warm amber-gold sky with soft cream clouds and a low sun glow behind the hills. Rich detail at the left and right edges, softer, simpler and slightly blurred in the centre (a game grid will cover the middle 55%). Gentle depth of field. No characters, no text.

**17. Bahçe, gündüz, dikey (telefon)** → `17-bg-day-portrait.jpg` · 9:16 · 16'yı ekle
> The same orchard as the attached reference in a tall 9:16 composition: the amber-gold sky and candy-fruit tree tops in the upper 30%, a calm softer middle area (the game grid will cover it), and crates, baskets and fallen oranges in the grass along the bottom 15%. Same palette and light. No characters, no text.

**18. Bahçe, gece (free spin'ler), yatay** → `18-bg-night-landscape.jpg` · 16:9 · 16'yı ekle
> The same orchard as the attached reference, same composition, at a magical night: deep teal and navy sky with a big soft moon and stars, warm paper lanterns strung between the candy trees, fireflies glowing in the grass, the hanging fruits and sweets catching the lantern light. Softer and simpler in the centre (a game grid will cover the middle 55%). No characters, no text.

**19. Bahçe, gece, dikey** → `19-bg-night-portrait.jpg` · 9:16 · 17 ve 18'i ekle
> The night orchard from the attached reference in a tall 9:16 composition matching the attached daytime portrait layout. No characters, no text.

**20. Meyve kasası çerçevesi** → `20-crate-frame.png` · 4:3
> A rectangular frame made of a rustic wooden fruit crate seen straight from the front: thick, warm honey-brown wooden slats with visible grain and small iron nails, slightly rounded corners, a stencilled-look orange-slice emblem on the top centre plank (no letters). Decorations only at the four corners and the top centre: a few green leaves and a small cluster of cherries at the top corners. The four straight sides are plain and uniform along their length, so they can be stretched. The entire inside of the frame is solid magenta (#FF00FF). + CUTOUT

## Paneller ve kutlamalar (yazısız; yazıları ben ekliyorum)

**21. Kurdele afiş** → `21-ribbon-banner.png` · 2:1
> A wide, empty decorative ribbon banner: a broad glossy golden-orange satin ribbon with folded swallow-tail ends, a few small gold stars and a sprig of green leaves at each end, a flat clean centre area left empty for text. Straight front view. + CUTOUT

**22. Ahşap tabela paneli** → `22-sign-panel.png` · 4:3
> An empty wooden signboard panel for a game dialog: a rounded rectangle of warm painted wood with a thick golden-orange rim, two small hanging leafy vines at the top corners, a few cherries and a wrapped sweet tucked at the bottom corners, a large flat calm cream-coloured centre left completely empty for text and buttons. Straight front view. + CUTOUT

## Logo, lobi ve ekstralar

**23. Logo** → `23-logo.png` · 2:1
> A game logo reading exactly "SUGAR ORCHARD" (check the spelling) in chunky, bouncy, rounded letters: "SUGAR" in glossy golden-orange candy letters with a thick chocolate-brown outline and white shine, "ORCHARD" below it in fresh leaf-green letters with the same outline. A shiny orange with two leaves dots the composition at the top right, and a small peppermint swirl and a cherry pair sit at the bottom left. + CUTOUT

**24. Lobi thumbnail'i** → `24-thumbnail.jpg` (admin panelinden yüklenir) · 4:3 (lobi kartı `object-fit: cover`, ana öğeleri kenarlardan uzak tut) · 1, 11, 16 ve 23'ü ekle
> Key art for a casino game tile: the vintage gumball machine from the reference in the centre bursting with gumballs, surrounded by flying oranges, cherries, strawberries, gold-foil toffees, peppermints and blue gummy bears with gold sparkle, in front of the golden-hour candy orchard. The logo "SUGAR ORCHARD" across the top centre, matching the attached logo. Vibrant, high contrast, readable at small size. Full scene, no magenta background.

**25. Ekstralar (konfeti ve parçacıklar)** → `25-extras.png` · 3:2
> Six separate small sprites in a 3×2 arrangement with wide gaps: a glossy green leaf; a gold coin with an embossed orange-slice emblem; a small sugar cube; a tiny wrapped sweet in orange foil; a single orange segment; a round orange juice droplet. + CUTOUT

**26. Spin butonu (isteğe bağlı)** → `26-spin-button.png` · 1:1
> A big round game button shaped like a glossy fresh orange slice seen from the front: bright orange segments, a white pith ring and a thin peel rim, with a smooth, slightly domed glossy surface and a clean empty centre (an icon will be added later). + CUTOUT

---

**Üretilmeyecekler (kodda yapılacak):**
- tüm yazılar ve çarpan sayıları
- sembollerin kazanınca zıplaması ve parlaması
- tumble düşüşleri
- çarpanların toplanıp kazanca uçması
- ekran sarsıntısı ve parlama
- ateşböceklerinin hareketi
- parallax
- düğme ikonları
