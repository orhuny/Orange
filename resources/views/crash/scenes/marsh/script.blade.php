// Lucky Duck's scenery (the marsh hunt), in the page's main module (crash.blade.php): Lucky
        // floats, takes off and flies; the strips and clouds scroll past; the fox's gun follows
        // him; the sky turns from day to dusk with the multiplier.
        const $marsh = document.querySelector('.scene.marsh');
        const $duck = $marsh.querySelector('.duck-wrap');
        const $fox = $marsh.querySelector('.fox');
        const $loot = $marsh.querySelector('.loot');
        const LOOT = [@json($sceneDef['art']('coin.webp')), @json($sceneDef['art']('feather.webp'))];
        // Each strip's art, width ÷ height (one repeat), for the scroll's wrap-around.
        const STRIP_RATIO = { 'strip-hills': 1448 / 270, 'strip-marsh': 1448 / 451, 'strip-reeds': 1448 / 546 };
        const $strips = [...$marsh.querySelectorAll('.strip')].map(($s) => ({
            $b: $s.querySelector('b'),
            k: parseFloat($s.style.getPropertyValue('--k')) || 1,
            ratio: STRIP_RATIO[[...$s.classList].find((c) => c in STRIP_RATIO)] || 1,
        }));
        const $clouds = [...$marsh.querySelectorAll('.cloud')].map(($c) => ({
            $c, x: parseFloat($c.style.getPropertyValue('--x')) / 100, k: parseFloat($c.style.getPropertyValue('--k')) || 0.2,
        }));

        // The stars that come out at dusk.
        (function scatterStars() {
            const $stars = document.getElementById('stars');
            for (let i = 0; i < 46; i++) {
                const s = document.createElement('i');
                s.className = 'star';
                s.style.left = (Math.random() * 100).toFixed(2) + '%';
                s.style.top = (Math.random() * 100).toFixed(2) + '%';
                s.style.setProperty('--s', (1.5 + Math.random() * 2.5).toFixed(1) + 'px');
                s.style.setProperty('--t', (1.2 + Math.random() * 2.4).toFixed(2) + 's');
                s.style.setProperty('--d', (-Math.random() * 3).toFixed(2) + 's');
                $stars.appendChild(s);
            }
        })();

        // How far the marsh has scrolled, in screen widths, at multiplier m: the speed grows
        // with m (it rises by e^(g·t)), ~0.1 widths a second at 1x, held from TRAVEL_CAP_X on so
        // the near reeds never blur into a strobe.
        const G = @json((float) config('crash.growth_rate', 0.06));
        const TRAVEL_PER_X = 1.7;
        const TRAVEL_CAP_X = 6;
        function travel(m) {
            const x = Math.max(1, m);
            if (x <= TRAVEL_CAP_X) return TRAVEL_PER_X * (x - 1);
            return TRAVEL_PER_X * (TRAVEL_CAP_X - 1 + TRAVEL_CAP_X * Math.log(x / TRAVEL_CAP_X));
        }
        // The scenery keeps its place between rounds: `flown` is what earlier rounds scrolled.
        let flown = 0;
        let lastM = 1;

        // The layout, measured on resize: the scene, Lucky's size, where he floats and where he
        // cruises (clear of the multiplier row: --hud-room), and the fox's shoulder.
        let L = null;
        function layout() {
            const W = $marsh.clientWidth, H = $marsh.clientHeight;
            const portrait = H > W;
            const dw = $duck.offsetWidth, dh = $duck.offsetHeight;
            // The pond: the open water in the marsh strip's art starts about half-way down it.
            const marshStrip = $marsh.querySelector('.strip-marsh');
            const water = marshStrip.offsetTop + marshStrip.offsetHeight * 0.5;
            const hudRoom = parseFloat(getComputedStyle(document.documentElement).getPropertyValue('--hud-room')) || 220;
            const fox = $fox.getBoundingClientRect(), scene = $marsh.getBoundingClientRect();
            for (const s of $strips) s.tile = s.$b.clientHeight * s.ratio; // px per repeat
            L = {
                W, H, dw, dh,
                // Floating on the pond, right of the multiplier (centred at the bottom) and, on a
                // wide screen, left of the docked All bets panel.
                home: { x: W * 0.76, y: water + dh * 0.12 },
                cruise: { x: W * (portrait ? 0.6 : 0.62), y: Math.max(H * (portrait ? 0.3 : 0.27), Math.min(H * 0.42, H - hudRoom - dh * 0.75)) },
                climb: H * 0.1,
                // Where he holds the gun (half-way up the fox), to aim from.
                eye: { x: fox.left - scene.left + fox.width * 0.55, y: fox.top - scene.top + fox.height * 0.4 },
            };
        }
        new ResizeObserver(() => { L = null; place(lastM); }).observe($marsh);

        const ease = (p) => 1 - Math.pow(1 - p, 3);
        let aim = 0; // the fox's pose: 0 low, 1 mid, 2 high
        let pose = 'idle';
        function setPose(next) {
            if (pose === next) return;
            pose = next;
            $duck.dataset.pose = next;
        }
        function setSky(m) {
            const sky = m >= 8 ? 'dusk' : m >= 3.5 ? 'sunset' : m >= 1.8 ? 'gold' : 'day';
            if ($marsh.dataset.sky !== sky) $marsh.dataset.sky = sky;
        }

        // Lucky's centre at multiplier m (1 on the water): the take-off eases him from the water
        // to the cruise over its first seconds (by time, t = ln m / g), then he climbs a little
        // more as the multiplier grows.
        function duckAt(m) {
            if (m <= 1) return { ...L.home };
            const t = Math.log(m) / G;
            const p = Math.min(1, t / 2.6);
            const climb = L.climb * Math.min(1, Math.log(m) / Math.log(20));
            return {
                x: L.home.x + (L.cruise.x - L.home.x) * ease(p),
                y: L.home.y + (L.cruise.y - L.home.y) * (1 - Math.pow(1 - p, 2)) - climb * p,
            };
        }

        function place(m, flying) {
            if (!L) layout();
            const { x, y } = duckAt(m);
            $duck.style.transform = `translate3d(${(x - L.dw / 2).toFixed(1)}px, ${(y - L.dh / 2).toFixed(1)}px, 0)`;
            $stage.style.setProperty('--hero-x', `${x.toFixed(0)}px`);
            $stage.style.setProperty('--hero-y', `${y.toFixed(0)}px`);
            // The fox takes the pose nearest the angle up to Lucky (low under ~25°, high over ~45°),
            // with 3° of give so he doesn't flick between two poses on a boundary.
            const up = Math.atan2(L.eye.y - y, x - L.eye.x) * 180 / Math.PI;
            const want = up < 25 ? 0 : up < 45 ? 1 : 2;
            if (want !== aim && (!flying || Math.abs(up - (want > aim ? [25, 45][want - 1] : [25, 45][aim - 1])) > 3)) {
                aim = want;
                $fox.dataset.aim = String(aim);
            }
            // The scenery scrolls by what has been flown.
            const dist = (flown + travel(m)) * L.W;
            for (const s of $strips) {
                const off = s.tile > 0 ? (dist * s.k) % s.tile : 0;
                s.$b.style.transform = `translate3d(${(-off).toFixed(1)}px, 0, 0)`;
            }
            for (const c of $clouds) {
                const span = L.W * 1.3;
                const cx = (((c.x * L.W - dist * c.k) % span) + span) % span - L.W * 0.18;
                c.$c.style.translate = `${cx.toFixed(1)}px 0`;
            }
        }

        // BANG! goes on the shot's path, a third of the way from the gun (the firing pose's bell,
        // 88.6% / 25.3% of the fox) to Lucky, kept on screen and below the top status rows.
        const $banner = document.querySelector('.crash-banner');
        function placeBang() {
            if (!L) layout();
            const fox = $fox.getBoundingClientRect(), sc = $marsh.getBoundingClientRect();
            const gx = fox.left - sc.left + fox.width * 0.886, gy = fox.top - sc.top + fox.height * 0.253;
            const { x, y } = duckAt(lastM);
            const bw = $banner.offsetWidth, bh = $banner.offsetHeight;
            const bx = Math.max(bw / 2 + 8, Math.min(L.W - bw / 2 - 8, gx + (x - gx) * 0.36));
            const by = Math.max(bh / 2 + 76, Math.min(L.H - bh / 2, gy + (y - gy) * 0.36));
            $stage.style.setProperty('--bang-x', `${bx.toFixed(0)}px`);
            $stage.style.setProperty('--bang-y', `${by.toFixed(0)}px`);
        }

        const scene = {
            // Every animation frame of a round, at its multiplier.
            frame(m) {
                lastM = m;
                setPose(Math.log(Math.max(1, m)) / G < 0.5 ? 'lift' : 'fly');
                place(m, true);
            },
            // With each multiplier the readout shows.
            update(m) {
                setSky(m);
            },
            crashed(point) {
                setSky(point);
                setPose('hit');
                placeBang();
            },
            reset() {
                L = null; // measured afresh each round (the HUD may have changed size)
                flown += travel(lastM);
                lastM = 1;
                setSky(1);
                setPose('idle');
                place(1, false);
            },
            // A bet cashed out: a few coins and golden feathers burst from Lucky.
            cashedOut() {
                if (!L || pose !== 'fly') return;
                const { x, y } = duckAt(lastM);
                for (let i = 0; i < 9; i++) {
                    const img = document.createElement('img');
                    img.src = LOOT[i % 3 === 2 ? 1 : 0];
                    img.alt = '';
                    const a = -Math.PI / 2 + (Math.random() - 0.5) * 2.4;
                    const d = 90 + Math.random() * 120;
                    img.style.cssText = `--x:${x}px;--y:${y}px;--dx:${(Math.cos(a) * d).toFixed(0)}px;--dy:${(Math.sin(a) * d + 60).toFixed(0)}px;`
                        + `--r:${((Math.random() - 0.5) * 540).toFixed(0)}deg;--d:${(i * 0.03).toFixed(2)}s;--s:${(22 + Math.random() * 16).toFixed(0)}px`;
                    img.addEventListener('animationend', () => img.remove());
                    $loot.appendChild(img);
                }
            },
        };
        scene.reset();
