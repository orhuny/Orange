// Lucky Duck's scenery (the marsh hunt), in the page's main module (crash.blade.php): Lucky
        // floats, takes off and flies; the strips and clouds scroll past; the fox aims at him and
        // keeps shooting (and missing) while he flies; the sky turns from day to dusk.
        const $marsh = document.querySelector('.scene.marsh');
        const $duck = $marsh.querySelector('.duck-wrap');
        const $fox = $marsh.querySelector('.fox');
        const $foxSprite = $fox.querySelector('.fox-sprite');
        const $blast = $fox.querySelector('.blast');
        const $shots = $marsh.querySelector('.shots');
        const $loot = $marsh.querySelector('.loot');
        const LOOT = [@json($sceneDef['art']('coin.webp')), @json($sceneDef['art']('feather.webp'))];
        const SPRAY = @json($sceneDef['art']('shot-spray.webp'));
        // fox-boat.webp: 8 poses, 0-2 aiming low / mid / high, 3-5 firing at those, 6 reloading,
        // 7 paddling; and where the bell's mouth is in poses 0-5, as fractions of a pose
        // (scripts/art/process-lucky-duck.py).
        const FOX_MUZZLE = [[0.819, 0.432], [0.802, 0.272], [0.693, 0.192], [0.792, 0.434], [0.745, 0.271], [0.672, 0.118]];
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

        // The layout, measured on resize and each round: the scene, Lucky's size, where he floats
        // and where he cruises (clear of the multiplier row: --hud-room), and the fox.
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
            const foxBox = { x: fox.left - scene.left, y: fox.top - scene.top, w: fox.width, h: fox.height };
            L = {
                W, H, dw, dh,
                // Floating on the pond, right of the multiplier (centred at the bottom) and, on a
                // wide screen, left of the docked All bets panel.
                home: { x: W * 0.76, y: water + dh * 0.12 },
                cruise: { x: W * (portrait ? 0.6 : 0.62), y: Math.max(H * (portrait ? 0.3 : 0.27), Math.min(H * 0.42, H - hudRoom - dh * 0.75)) },
                climb: H * 0.1,
                // Where he holds the gun (half-way up the fox), to aim from.
                eye: { x: foxBox.x + foxBox.w * 0.55, y: foxBox.y + foxBox.h * 0.4 },
                fox: foxBox,
            };
        }
        new ResizeObserver(() => { L = null; place(lastM); }).observe($marsh);

        const ease = (p) => 1 - Math.pow(1 - p, 3);
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

        // The fox: where he aims (0 low, 1 mid, 2 high) and what he does (aim | fire | reload | paddle).
        let aim = 0;
        let act = 'aim';
        function renderFox() {
            const frame = act === 'paddle' ? 7 : act === 'reload' ? 6 : act === 'fire' ? 3 + aim : aim;
            $foxSprite.style.backgroundPosition = `${(frame / 7 * 100).toFixed(3)}% 0`;
            const [mx, my] = FOX_MUZZLE[Math.min(frame, 5)];
            $fox.style.setProperty('--mx', `${(mx * 100).toFixed(1)}%`);
            $fox.style.setProperty('--my', `${(my * 100).toFixed(1)}%`);
        }
        function setAct(next) {
            act = next;
            renderFox();
        }
        // The gun's mouth, in the scene, in the pose he is in.
        function bell() {
            const [mx, my] = FOX_MUZZLE[act === 'fire' ? 3 + aim : aim];
            return { x: L.fox.x + L.fox.w * mx, y: L.fox.y + L.fox.h * my };
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

        // Moves Lucky to multiplier m, the fox's aim with him, and the scenery; `moveDuck` false
        // leaves Lucky where he is (falling into the pond).
        function place(m, flying, moveDuck = true) {
            if (!L) layout();
            const { x, y } = duckAt(m);
            if (moveDuck) {
                $duck.style.transform = `translate3d(${(x - L.dw / 2).toFixed(1)}px, ${(y - L.dh / 2).toFixed(1)}px, 0)`;
                $stage.style.setProperty('--hero-x', `${x.toFixed(0)}px`);
                $stage.style.setProperty('--hero-y', `${y.toFixed(0)}px`);
            }
            // The fox takes the pose nearest the angle up to Lucky (low under ~25°, high over ~45°),
            // with 3° of give so he doesn't flick between two poses on a boundary; not while he
            // is firing or reloading.
            const up = Math.atan2(L.eye.y - y, x - L.eye.x) * 180 / Math.PI;
            const want = up < 25 ? 0 : up < 45 ? 1 : 2;
            if (act === 'aim' && want !== aim && (!flying || Math.abs(up - (want > aim ? [25, 45][want - 1] : [25, 45][aim - 1])) > 3)) {
                aim = want;
                renderFox();
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

        // He keeps shooting while Lucky flies, and misses: the first shot once Lucky is up, then
        // every few seconds, sooner as the multiplier climbs. On the round's clock (t = ln m / g),
        // so every player sees the same shots. A shot passes just over Lucky or just under him, in
        // turn; he ducks under it or rolls over it, and winks. The fox reloads, then aims again.
        const FIRST_SHOT = 3.2;
        const shotGap = (m) => (m >= 8 ? 1.8 : m >= 3.5 ? 2.3 : m >= 1.8 ? 2.8 : 3.4);
        let nextShot = FIRST_SHOT;
        let shotN = 0;
        let timers = [];
        let dodgeUntil = 0;
        const later = (ms, fn) => timers.push(setTimeout(fn, ms));
        function stopShooting() {
            timers.forEach(clearTimeout);
            timers = [];
            dodgeUntil = 0;
            $shots.replaceChildren();
        }
        function popBlast() {
            $blast.classList.remove('pop');
            void $blast.offsetWidth; // restarts its animation
            $blast.classList.add('pop');
        }
        // A puff of gun smoke from the bell, drifting up.
        function puff() {
            const from = bell();
            const $p = document.createElement('i');
            $p.className = 'puff';
            $p.style.cssText = `--x:${from.x.toFixed(0)}px;--y:${from.y.toFixed(0)}px`;
            $p.addEventListener('animationend', (e) => e.animationName === 'marsh-puff-drift' && $p.remove());
            $shots.appendChild($p);
        }
        function fire() {
            const over = shotN % 2 === 0; // even shots pass over him, odd ones under
            setAct('fire');
            popBlast();
            puff();
            audio.sfx('shot');
            const from = bell();
            const { x, y } = duckAt(lastM);
            const ty = y + (over ? -0.45 : 0.5) * L.dh; // a near miss
            const angle = Math.atan2(ty - from.y, x - from.x) * 180 / Math.PI;
            const reach = Math.hypot(x - from.x, ty - from.y) * 1.9; // on past him, off the screen
            const $spray = document.createElement('img');
            $spray.className = 'spray';
            $spray.src = SPRAY;
            $spray.alt = '';
            $spray.style.cssText = `--x:${from.x.toFixed(0)}px;--y:${from.y.toFixed(0)}px;--a:${angle.toFixed(1)}deg;--reach:${reach.toFixed(0)}px`;
            $spray.addEventListener('animationend', () => $spray.remove());
            $shots.appendChild($spray);
            // He sees it coming: under a shot passing over, over one passing under.
            later(110, () => {
                if (pose === 'hit') return;
                dodgeUntil = performance.now() + 950;
                pose = over ? 'dodge-dip' : 'dodge-roll';
                $duck.dataset.pose = pose;
            });
            // Then he reloads, paddles a stroke after Lucky, and aims again.
            later(340, () => act === 'fire' && setAct('reload'));
            later(1150, () => act === 'reload' && setAct('paddle'));
            later(1750, () => act === 'paddle' && setAct('aim'));
        }
        function shoot(m) {
            const t = Math.log(Math.max(1, m)) / G;
            if (t < nextShot) return;
            if (t - nextShot < 0.6) fire(); // a shot long gone (joined mid-round) isn't replayed
            while (nextShot <= t) {
                shotN += 1;
                nextShot += shotGap(Math.exp(G * nextShot));
            }
        }

        // BANG! goes on the shot's path, a third of the way from the gun's mouth to Lucky, kept on
        // screen and below the top status rows.
        const $banner = document.querySelector('.crash-banner');
        function placeBang() {
            if (!L) layout();
            const { x: gx, y: gy } = bell();
            const { x, y } = duckAt(lastM);
            const bw = $banner.offsetWidth, bh = $banner.offsetHeight;
            const bx = Math.max(bw / 2 + 8, Math.min(L.W - bw / 2 - 8, gx + (x - gx) * 0.36));
            const by = Math.max(bh / 2 + 76, Math.min(L.H - bh / 2, gy + (y - gy) * 0.36));
            $stage.style.setProperty('--bang-x', `${bx.toFixed(0)}px`);
            $stage.style.setProperty('--bang-y', `${by.toFixed(0)}px`);
        }

        // After the hit Lucky drops into the pond, at his spot on the water: he lands with a splash
        // (the styles' --land-at, 1.88 s after the crash), feet up, and sinks (1.4 s later). The next
        // round he pops back up there (emerge, 1.8 s) and floats as before; if the round resets
        // while he is still falling, he finishes first.
        const SUNK_MS = 3300;
        const EMERGE_MS = 2300;
        let crashedAt = 0;
        let emergeTimer = 0;
        function emerge() {
            emergeTimer = 0;
            place(1, false); // his spot on the water (where he sank)
            setPose('emerge');
            emergeTimer = setTimeout(() => {
                emergeTimer = 0;
                if (pose === 'emerge') setPose('idle');
            }, EMERGE_MS);
        }

        const scene = {
            // Every animation frame of a round, at its multiplier.
            frame(m) {
                lastM = m;
                if (performance.now() >= dodgeUntil) setPose(Math.log(Math.max(1, m)) / G < 0.5 ? 'lift' : 'fly');
                place(m, true);
                shoot(m);
            },
            // With each multiplier the readout shows.
            update(m) {
                setSky(m);
            },
            crashed(point) {
                stopShooting();
                clearTimeout(emergeTimer);
                emergeTimer = 0;
                crashedAt = performance.now();
                setSky(point);
                if (!L) layout();
                // The drop, from where he is hit to his spot on the pond.
                const { x, y } = duckAt(lastM);
                $duck.style.setProperty('--fall-x', `${(L.home.x - x).toFixed(0)}px`);
                $duck.style.setProperty('--fall-y', `${(L.home.y - y).toFixed(0)}px`);
                setPose('hit');
                setAct('fire'); // this one hits (the big blast: .crashed, in the styles)
                puff();
                placeBang();
            },
            reset() {
                L = null; // measured afresh each round (the HUD may have changed size)
                stopShooting();
                nextShot = FIRST_SHOT;
                shotN = 0;
                flown += travel(lastM);
                lastM = 1;
                setSky(1);
                setAct('aim');
                if (pose === 'hit') {
                    // In the pond: he comes back up once he has sunk (now, or when he has).
                    place(1, false, false);
                    if (!emergeTimer) emergeTimer = setTimeout(emerge, Math.max(0, SUNK_MS - (performance.now() - crashedAt)));
                } else if (pose === 'emerge') {
                    place(1, false, false); // already coming back up
                } else {
                    setPose('idle');
                    place(1, false);
                }
            },
            // A bet cashed out: a few coins and golden feathers burst from Lucky.
            cashedOut() {
                if (!L || pose === 'idle' || pose === 'hit') return;
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
