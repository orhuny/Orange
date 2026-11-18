// The dive's scenery and HUD, in the page's main module (crash.blade.php): the sub's
        // descent past the reef and the trench walls, the bubbles and silt, the depth meter
        // and the pressure and hull gauges.
        const $pressArc = document.getElementById('pressureArc');
        const $pressVal = document.getElementById('pressureValue');
        const $hullFill = document.getElementById('hullFill');
        const $hullVal = document.getElementById('hullValue');
        const $pressNeedle = document.getElementById('pressureNeedle');
        const $hullLevel = document.getElementById('hullLevel');
        const $pressGauge = $pressArc.closest('.gauge');
        const $hullGauge = $hullFill.closest('.gauge');
        // Pressure dial sweeps 270°; hull colour runs green (120°) → red (0°).
        function setGauges(norm, hull, pressLevel) {
            $pressArc.style.strokeDasharray = (75 * norm).toFixed(2) + ' 100';
            $pressNeedle.style.transform = 'rotate(' + (-135 + 270 * norm).toFixed(1) + 'deg)';
            $pressGauge.dataset.level = pressLevel;
            $hullGauge.dataset.level = hull <= 30 ? 'crit' : (hull <= 60 ? 'warn' : 'ok');
            $hullGauge.style.setProperty('--hull-c', 'hsl(' + Math.round(hull * 1.2) + ' 80% 50%)');
            $hullLevel.style.transform = 'scaleY(' + (hull / 100) + ')';
            $hullFill.style.width = hull + '%';
        }

        // Rising bubbles in depth layers, for parallax. --base is a bubble's own
        // speed and --layer its depth (see .bubble): far ones crawl, near ones
        // rush past. The parent stage class drives --speed / --drift-x, so all
        // layers speed up together in deeper dive states without resetting a
        // bubble. Fewer on phones. Every player sees the same dive (a device's
        // reduced-motion setting doesn't change it: the dive is the game).
        const fewer = window.matchMedia('(max-width: 600px)').matches ? 0.6 : 1;
        function spawnBubbles(id, count, { min, max, layer, band }) {
            const host = document.getElementById(id);
            for (let i = 0; i < count; i++) {
                const b = document.createElement('div');
                b.className = 'bubble';
                const size = min + Math.random() * (max - min);
                b.style.width = b.style.height = size + 'px';
                // Spawn across a band reaching offscreen right (wider for faster
                // layers) so the leftward dive drift keeps the screen populated;
                // without this the right side empties as soon as the sub moves.
                b.style.left = (-10 + Math.random() * band) + 'vw';
                b.style.setProperty('--layer', layer);
                b.style.setProperty('--base', (6 + Math.random() * 10) + 's');
                b.style.animationDelay = (-Math.random() * 12) + 's';
                host.appendChild(b);
            }
        }
        spawnBubbles('bubbles', 64, { min: 4, max: 18, layer: 1, band: 150 });
        spawnBubbles('bubbles-far', Math.round(90 * fewer), { min: 2, max: 6, layer: 0.45, band: 150 });
        spawnBubbles('bubbles-dive', Math.round(90 * fewer), { min: 3, max: 14, layer: 1.2, band: 230 });
        spawnBubbles('bubbles-near', Math.round(18 * fewer), { min: 24, max: 56, layer: 2, band: 300 });

        // ambient silt particles. Spawn slightly past the right edge so the
        // strong leftward drift during dive keeps the visible area populated
        // instead of emptying out as everything streams toward upper-left.
        const particleHost = document.getElementById('particles');
        for (let i = 0; i < 50; i++) {
            const p = document.createElement('div');
            p.className = 'particle';
            p.style.left = (-5 + Math.random() * 130) + 'vw';
            p.style.animationDuration = (8 + Math.random() * 14) + 's';
            p.style.animationDelay = (-Math.random() * 16) + 's';
            p.style.opacity = (0.3 + Math.random() * 0.5);
            particleHost.appendChild(p);
        }

        // Dive progress 0..1 on a log scale up to config('crash.depth_max_multiplier'),
        // so depth, pressure and hull keep moving on any realistic round instead of
        // maxing out early. With 1000x: 2x ≈ 1.1 km, 10x ≈ 3.7 km, 100x ≈ 7.3 km.
        const DIVE_MAX_X = @json((float) config('crash.depth_max_multiplier', 1000));
        const diveProgress = (m) => Math.min(1, Math.max(0, Math.log(m) / Math.log(DIVE_MAX_X)));
        const $depthMeter = document.getElementById('depth-meter');
        const $backdrop = document.querySelector('.backdrop');
        const $seabedFog = document.querySelector('.seabed-fog');
        const $walls = document.querySelector('.walls');
        const $depthVal = document.getElementById('depth-value');
        const $depthZone = document.getElementById('depth-zone');
        const MAX_DEPTH_M = 10994;
        // How far the camera has descended, in % of the screen height, at multiplier m.
        // Distance grows with m - 1, so the speed grows with the multiplier itself (it
        // rises by e^(g·t)): ~11%/s at 1x, ~21 at 2x, ~42 at 4x. From DESCENT_CAP_X on
        // the speed holds (~74%/s) so the looping walls never strobe.
        const DESCENT_PER_X = 176;
        const DESCENT_CAP_X = 7;
        function descent(m) {
            const x = Math.max(1, m);
            if (x <= DESCENT_CAP_X) return DESCENT_PER_X * (x - 1);
            return DESCENT_PER_X * (DESCENT_CAP_X - 1 + DESCENT_CAP_X * Math.log(x / DESCENT_CAP_X));
        }

        // Moves the backdrop and the trench walls to the descent at multiplier m: the
        // walls fall by it, the reef (further away) by 80% of it until it has left the
        // screen; the walls hang from the reef's lower edge and scroll inside that.
        const $scene = document.querySelector('.scene');
        const $wallStrips = [...document.querySelectorAll('.wall b')];
        let tileHeights = null; // px per repeat of each strip's art
        function tiles() {
            tileHeights ??= $wallStrips.map((b) => b.clientWidth * (parseFloat(getComputedStyle(b.parentNode).getPropertyValue('--tile-ratio')) || 0));
            return tileHeights;
        }
        window.addEventListener('resize', () => { tileHeights = null; });
        new MutationObserver(() => { tileHeights = null; })
            .observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });
        // The jellyfish hang in the water, so the sub passes them: they rise with the
        // descent (a little slower than the walls, being further off) and, once above
        // the screen, come round again from below it, out of sight. `translate`, so
        // their bob (the float animation, on `transform`) carries on.
        const $jellies = [...document.querySelectorAll('.jelly')];
        const JELLY_PARALLAX = 0.9;
        const JELLY_WRAP_MARGIN = 140; // px beyond each edge, more than a jelly is tall
        function setScenery(m) {
            const h = $scene?.clientHeight || window.innerHeight;
            const fall = descent(m) / 100 * h;
            const rise = Math.min(1.25 * h, fall * 0.8);
            const lift = `translate3d(0, ${(-rise).toFixed(2)}px, 0)`;
            if ($backdrop) $backdrop.style.transform = lift;
            // The floor sinks into the water as soon as the reef starts rising, all the
            // way by the time it has risen an eighth of the screen.
            const sunk = Math.min(1, rise / (0.125 * h)).toFixed(3);
            if ($seabedFog) $seabedFog.style.opacity = sunk;
            if ($walls) $walls.style.setProperty('--walls-in', sunk); // where the cliffs reach into the painting
            if ($walls) $walls.style.transform = lift;
            tiles().forEach((tile, i) => {
                const y = tile > 0 ? (fall - rise) % tile : 0;
                $wallStrips[i].style.transform = `translate3d(0, ${(-y).toFixed(2)}px, 0)`;
            });
            const span = h + 2 * JELLY_WRAP_MARGIN;
            for (const $j of $jellies) {
                const home = parseFloat($j.style.top) / 100 * h; // its place at the surface
                const y = ((home - fall * JELLY_PARALLAX + JELLY_WRAP_MARGIN) % span + span) % span - JELLY_WRAP_MARGIN;
                $j.style.translate = `0 ${(y - home).toFixed(2)}px`;
            }
        }

        const depthFmt = new Intl.NumberFormat('en-US');
        function setDepth(m) {
            const p = diveProgress(m);
            const metres = Math.round(p * MAX_DEPTH_M);
            $depthMeter.style.setProperty('--p', p.toFixed(4));
            $depthVal.textContent = depthFmt.format(metres) + ' m';
            $depthZone.textContent = metres === 0 ? 'SURFACE'
                : metres < 200 ? 'SUNLIGHT ZONE'
                : metres < 1000 ? 'TWILIGHT ZONE'
                : metres < 4000 ? 'MIDNIGHT ZONE'
                : metres < 6000 ? 'ABYSSAL ZONE'
                : 'HADAL ZONE';
        }

        const scene = {
            // Every animation frame of a round, at its multiplier.
            frame: setScenery,
            // With each multiplier the readout shows.
            update(m) {
                setDepth(m);
                // Same dive progress as the depth meter: ~1 bar per 10 m of water.
                const norm = diveProgress(m);
                const pressure = Math.round(1 + norm * MAX_DEPTH_M / 10);
                const hull = Math.max(0, Math.round(100 - norm * 100));
                $pressVal.textContent = pressure;
                $hullVal.textContent = hull;
                setGauges(norm, hull, m >= 8 ? 'crit' : (m >= 3 ? 'warn' : 'ok'));
            },
            crashed(point) {
                setDepth(point);
            },
            reset() {
                setScenery(1); // glides back up to the surface
                setDepth(1);
                $pressVal.textContent = '0';
                $hullVal.textContent = '100';
                setGauges(0, 100, 'ok');
            },
        };
