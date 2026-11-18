<!-- The marsh: the sky turns from day to gold, sunset and dusk as the multiplier climbs;
             the far hills, the water and the near reeds scroll past as Lucky flies; the fox
             takes aim from his blind and fires at the crash. The script (script.blade.php)
             places Lucky, turns the gun and scrolls the strips. -->
        @php($art = $sceneDef['art'])
        <div class="scene marsh" data-sky="day">
            <div class="sky sky-day"></div>
            <div class="sky sky-glow"></div>
            <div class="sky sky-sunset"></div>
            <div class="sky sky-dusk"></div>
            <div id="stars" class="stars"></div>
            <div class="clouds" aria-hidden="true">
                @foreach ([[1, 8, 9, 15, 0.22], [2, 46, 5, 12, 0.16], [3, 76, 20, 9, 0.3], [4, 28, 27, 8, 0.38], [5, 94, 8, 14, 0.2], [6, 60, 33, 10, 0.34]] as [$n, $x, $y, $w, $k])
                <img class="cloud" src="{{ $art("cloud-{$n}.webp") }}" alt="" draggable="false" style="--x: {{ $x }}; --y: {{ $y }}%; --w: {{ $w }}; --k: {{ $k }};">
                @endforeach
            </div>
            <div class="strip strip-hills" style="--k: 0.2"><b></b></div>
            <div class="strip strip-marsh" style="--k: 0.55"><b></b></div>

            {{-- Lucky. The script places .duck-wrap (on the water, the take-off, the flight) and
                 sets its pose: idle | lift | fly | dodge-dip | dodge-roll | hit | emerge. .duck-bob bobs
                 him, .duck-fall carries the drop once he is hit: his eyes spin (.dizzy) and stars
                 circle his head (.orbit) as he tumbles, then he lands in the pond with a splash, feet
                 up, and sinks (.duck-water); the next round he pops back up (emerge). --}}
            <div class="duck-wrap" data-pose="idle" aria-hidden="true">
                <div class="duck-fall">
                    <div class="duck-bob">
                        <div class="duck-sprite duck-takeoff"></div>
                        <div class="duck-sprite duck-fly"></div>
                        <div class="duck-sprite duck-dodge"></div>
                    </div>
                    <div class="duck-sprite duck-hit">
                        <i class="dizzy dizzy-1"></i>
                        <i class="dizzy dizzy-2"></i>
                        <i class="orbit"><b></b><b></b><b></b></i>
                    </div>
                    <div class="duck-sprite duck-water"></div>
                    <div class="duck-sprite water-splash"></div>
                </div>
            </div>

            <div class="strip strip-reeds" style="--k: 1.2"><b></b></div>

            {{-- The fox in his tub boat on the pond, bottom left (on the bet bar when embedded).
                 Eight poses (fox-boat.webp): aiming low, mid and high, firing at those, reloading and
                 paddling, picked by the script, which also puts the muzzle at the pose's bell
                 (--mx / --my). The boat bobs; its wake streams out behind it while Lucky flies. --}}
            <div class="fox" aria-hidden="true">
                <div class="boat">
                    <div class="wake"></div>
                    <div class="fox-sprite"></div>
                    <div class="muzzle">
                        <img class="blast" src="{{ $art('muzzle-burst.webp') }}" alt="" draggable="false">
                    </div>
                </div>
            </div>

            {{-- The shots he misses with, flying past Lucky (the script adds them). --}}
            <div class="shots" aria-hidden="true"></div>

            {{-- Gold coins and feathers that burst from Lucky when a bet cashes out. --}}
            <div class="loot" aria-hidden="true"></div>
        </div>
