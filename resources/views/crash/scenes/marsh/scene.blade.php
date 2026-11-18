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
                 sets its pose: idle | lift | fly | hit. .duck-bob bobs him, .duck-fall carries
                 the drop once he is hit. --}}
            <div class="duck-wrap" data-pose="idle" aria-hidden="true">
                <div class="duck-fall">
                    <div class="duck-bob">
                        <div class="duck-sprite duck-takeoff"></div>
                        <div class="duck-sprite duck-fly"></div>
                    </div>
                    <div class="duck-sprite duck-hit"></div>
                </div>
            </div>

            <div class="strip strip-reeds" style="--k: 1.2"><b></b></div>

            {{-- The fox in his blind, bottom left. His gun arm is its own layer, behind him: the
                 script turns it toward Lucky from low inside the blind, so the barrel pokes out past
                 his face; the flash and the smoke sit at the gun's mouth. --}}
            <div class="fox" aria-hidden="true">
                <div class="fox-arm">
                    <img class="fox-gun" src="{{ $art('fox-gun.webp') }}" alt="" draggable="false">
                    <div class="muzzle">
                        <img class="smoke" src="{{ $art('cloud-3.webp') }}" alt="" draggable="false" style="--d: 0s; --dx: 30px; --dy: -26px;">
                        <img class="smoke" src="{{ $art('cloud-4.webp') }}" alt="" draggable="false" style="--d: 0.05s; --dx: 58px; --dy: 8px;">
                        <img class="smoke" src="{{ $art('cloud-6.webp') }}" alt="" draggable="false" style="--d: 0.1s; --dx: 10px; --dy: -54px;">
                        <svg class="flash" viewBox="-50 -50 100 100" aria-hidden="true">
                            <polygon points="0,-46 9,-15 40,-26 18,-3 46,8 14,10 22,40 0,16 -20,38 -12,9 -40,6 -16,-6 -34,-30 -6,-16"
                                     fill="#fde047" stroke="#f97316" stroke-width="4" stroke-linejoin="round"/>
                            <circle r="12" fill="#fffbeb"/>
                        </svg>
                    </div>
                </div>
                <img class="fox-body" src="{{ $art('fox-body.webp') }}" alt="" draggable="false">
            </div>

            {{-- Gold coins and feathers that burst from Lucky when a bet cashes out. --}}
            <div class="loot" aria-hidden="true"></div>
        </div>
