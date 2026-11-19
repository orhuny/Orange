<!-- Filtered scene (depth darkening lives here) -->
        <div class="scene">
            <div class="backdrop"><i class="seabed-fog"></i></div>
            <div class="walls" aria-hidden="true"><i class="wall wall-l"><b></b></i><i class="wall wall-r"><b></b></i></div>
            <div class="rays"></div>
            <div id="particles" class="particles"></div>
            <div id="bubbles-far" class="bubbles bubbles-far"></div>
            <div class="caustics"></div>
            <div id="bubbles"  class="bubbles"></div>
            <div id="bubbles-dive" class="bubbles bubbles-dive"></div>
            {{-- Sea life behind the sub, by kind: config('crash.sea_life'). --}}
            @if ($seaLife['fish'])
            <div class="fishes">
                <!-- Orange tropical fish, left → right -->
                <div class="fish" style="left:0; top:18%; --dur:18s; animation-delay:-3s;">
                    <svg width="46" height="26" viewBox="0 0 46 26" xmlns="http://www.w3.org/2000/svg">
                        <ellipse cx="22" cy="13" rx="14" ry="7" fill="#f97316"/>
                        <ellipse cx="22" cy="13" rx="14" ry="7" fill="none" stroke="#7c2d12" stroke-width="0.6"/>
                        <path d="M 8 13 L 1 6 L 1 20 Z" fill="#fb923c" stroke="#7c2d12" stroke-width="0.6"/>
                        <path d="M 22 6 Q 26 2 30 6 Z" fill="#fdba74"/>
                        <line x1="14" y1="9" x2="14" y2="17" stroke="#7c2d12" stroke-width="0.7"/>
                        <line x1="20" y1="9" x2="20" y2="17" stroke="#7c2d12" stroke-width="0.7"/>
                        <circle cx="34" cy="11" r="1.6" fill="#fff"/>
                        <circle cx="34.4" cy="11.2" r="0.9" fill="#0f172a"/>
                    </svg>
                    <img class="skin-art art-burton" src="{{ asset('images/deep-dive/fish-gothic.png') }}" width="69" height="39" alt="" aria-hidden="true" loading="lazy">
                    <img class="skin-art art-jackpot" src="{{ asset('images/deep-dive/fish-jackpot.webp') }}" width="54" height="50" alt="" aria-hidden="true" loading="lazy">
                </div>

                <!-- Yellow & black striped fish, right → left -->
                <div class="fish swim-reverse" style="right:0; top:32%; --dur:22s; animation-delay:-8s;">
                    <svg width="40" height="22" viewBox="0 0 40 22" xmlns="http://www.w3.org/2000/svg">
                        <ellipse cx="19" cy="11" rx="12" ry="6" fill="#fde047"/>
                        <ellipse cx="19" cy="11" rx="12" ry="6" fill="none" stroke="#713f12" stroke-width="0.5"/>
                        <path d="M 7 11 L 1 5 L 1 17 Z" fill="#facc15"/>
                        <rect x="13" y="5" width="2" height="12" fill="#1e293b"/>
                        <rect x="20" y="5" width="2" height="12" fill="#1e293b"/>
                        <rect x="27" y="6" width="2" height="10" fill="#1e293b"/>
                        <circle cx="29" cy="9" r="1.3" fill="#fff"/>
                        <circle cx="29.3" cy="9.2" r="0.7" fill="#0f172a"/>
                    </svg>
                    <img class="skin-art art-burton" src="{{ asset('images/deep-dive/fish-puffer-gothic.png') }}" width="50" height="39" alt="" aria-hidden="true" loading="lazy">
                    <img class="skin-art art-jackpot" src="{{ asset('images/deep-dive/fish-puffer-jackpot.webp') }}" width="47" height="41" alt="" aria-hidden="true" loading="lazy">
                </div>

                <!-- Pink fish, left → right -->
                <div class="fish" style="left:0; top:45%; --dur:26s; animation-delay:-15s;">
                    <svg width="38" height="22" viewBox="0 0 38 22" xmlns="http://www.w3.org/2000/svg">
                        <ellipse cx="18" cy="11" rx="11" ry="6" fill="#f472b6"/>
                        <ellipse cx="18" cy="11" rx="11" ry="6" fill="none" stroke="#831843" stroke-width="0.5"/>
                        <path d="M 7 11 L 1 5 L 1 17 Z" fill="#f9a8d4"/>
                        <path d="M 18 5 Q 22 1 26 5 Z" fill="#fbcfe8"/>
                        <circle cx="28" cy="9" r="1.4" fill="#fff"/>
                        <circle cx="28.3" cy="9.2" r="0.8" fill="#0f172a"/>
                    </svg>
                    <img class="skin-art art-burton" src="{{ asset('images/deep-dive/fish-angler-gothic.png') }}" width="61" height="36" alt="" aria-hidden="true" loading="lazy">
                    <img class="skin-art art-jackpot" src="{{ asset('images/deep-dive/fish-angler-jackpot.webp') }}" width="53" height="41" alt="" aria-hidden="true" loading="lazy">
                </div>

                <!-- Cyan fish, right → left, smaller -->
                <div class="fish swim-reverse" style="right:0; top:25%; --dur:16s; animation-delay:-1s;">
                    <svg width="32" height="18" viewBox="0 0 32 18" xmlns="http://www.w3.org/2000/svg">
                        <ellipse cx="15" cy="9" rx="9" ry="5" fill="#22d3ee"/>
                        <ellipse cx="15" cy="9" rx="9" ry="5" fill="none" stroke="#155e75" stroke-width="0.5"/>
                        <path d="M 6 9 L 1 4 L 1 14 Z" fill="#67e8f9"/>
                        <circle cx="22" cy="7.5" r="1.2" fill="#fff"/>
                        <circle cx="22.3" cy="7.7" r="0.7" fill="#0f172a"/>
                    </svg>
                    <img class="skin-art art-burton" src="{{ asset('images/deep-dive/fish-gothic.png') }}" width="48" height="27" alt="" aria-hidden="true" loading="lazy">
                    <img class="skin-art art-jackpot" src="{{ asset('images/deep-dive/fish-jackpot.webp') }}" width="37" height="35" alt="" aria-hidden="true" loading="lazy">
                </div>

                <!-- Purple fish, left → right -->
                <div class="fish" style="left:0; top:55%; --dur:32s; animation-delay:-20s;">
                    <svg width="42" height="24" viewBox="0 0 42 24" xmlns="http://www.w3.org/2000/svg">
                        <ellipse cx="20" cy="12" rx="13" ry="7" fill="#a855f7"/>
                        <ellipse cx="20" cy="12" rx="13" ry="7" fill="none" stroke="#581c87" stroke-width="0.5"/>
                        <path d="M 7 12 L 1 5 L 1 19 Z" fill="#c084fc"/>
                        <path d="M 20 5 Q 24 1 28 5 Z" fill="#d8b4fe"/>
                        <circle cx="31" cy="10" r="1.5" fill="#fff"/>
                        <circle cx="31.3" cy="10.2" r="0.8" fill="#0f172a"/>
                    </svg>
                    <img class="skin-art art-burton" src="{{ asset('images/deep-dive/fish-puffer-gothic.png') }}" width="52" height="40" alt="" aria-hidden="true" loading="lazy">
                    <img class="skin-art art-jackpot" src="{{ asset('images/deep-dive/fish-puffer-jackpot.webp') }}" width="49" height="42" alt="" aria-hidden="true" loading="lazy">
                </div>
            </div>
            @endif

            <div id="creatures" class="creatures">
                @if ($seaLife['creatures'])
                <!-- Whale silhouette, slow drift left → right -->
                <div class="creature whale cross-reverse" style="right:0; top:50%; --dur:58s; animation-delay:-15s;">
                    <svg width="340" height="120" viewBox="0 0 340 120" xmlns="http://www.w3.org/2000/svg">
                        <!-- Tail flukes (horizontal V at the back) -->
                        <path d="M 295 55
                                 Q 310 38 335 30
                                 Q 322 50 308 60
                                 Q 322 66 335 88
                                 Q 310 80 295 65 Z"
                              fill="rgba(2,6,23,0.95)" stroke="rgba(15,23,42,1)" stroke-width="0.7"/>

                        <!-- Main body -->
                        <path d="M 25 58
                                 Q 12 48 22 38
                                 Q 35 18 95 22
                                 Q 185 18 255 32
                                 Q 285 38 298 52
                                 L 298 66
                                 Q 285 78 255 84
                                 Q 185 96 95 92
                                 Q 35 100 22 78
                                 Q 12 70 25 58 Z"
                              fill="rgba(2,6,23,0.95)" stroke="rgba(15,23,42,1)" stroke-width="0.8"/>

                        <!-- Dorsal fin -->
                        <path d="M 185 22 Q 195 6 215 24 Q 205 22 195 24 Z"
                              fill="rgba(2,6,23,0.95)" stroke="rgba(15,23,42,1)" stroke-width="0.6"/>

                        <!-- Pectoral fin (under, midbody) -->
                        <path d="M 90 84 Q 100 110 135 100 Q 115 88 90 84 Z"
                              fill="rgba(2,6,23,0.85)" stroke="rgba(15,23,42,1)" stroke-width="0.5"/>

                        <!-- Mouth line -->
                        <path d="M 22 68 Q 42 74 68 70" stroke="rgba(15,23,42,0.9)" stroke-width="1.1" fill="none"/>

                        <!-- Belly seam (subtle texture) -->
                        <path d="M 60 88 Q 140 96 240 84" stroke="rgba(103,232,249,0.15)" stroke-width="1" fill="none"/>

                        <!-- Eye -->
                        <circle cx="58" cy="50" r="2.4" fill="white"/>
                        <circle cx="58.5" cy="50.4" r="1.3" fill="#0f172a"/>

                        <!-- Blowhole -->
                        <ellipse cx="92" cy="22" rx="3" ry="1.2" fill="rgba(15,23,42,0.7)"/>
                    </svg>
                    <img class="skin-art art-burton" src="{{ asset('images/deep-dive/whale-gothic.png') }}" width="340" height="106" alt="" aria-hidden="true">
                    <img class="skin-art art-jackpot" src="{{ asset('images/deep-dive/whale-jackpot.webp') }}?v={{ filemtime(public_path('images/deep-dive/whale-jackpot.webp')) }}" width="196" height="184" alt="" aria-hidden="true">
                </div>

                <!-- Anglerfish-style silhouette, right → left -->
                <div class="creature angler-deep cross-reverse" style="right:0; top:75%; --dur:56s; animation-delay:-30s;">
                    <svg width="120" height="55" viewBox="0 0 120 55" xmlns="http://www.w3.org/2000/svg">
                        <path d="M 10 30
                                 Q 25 10 60 16
                                 Q 95 22 110 30
                                 Q 95 38 65 42
                                 Q 30 45 18 38
                                 Q 5 36 10 30 Z"
                              fill="rgba(2,6,23,0.95)" stroke="rgba(15,23,42,1)" stroke-width="0.6"/>
                        <path d="M 105 28 Q 118 22 115 32 Q 118 35 110 35 Z" fill="rgba(2,6,23,0.9)"/>
                        <circle cx="115" cy="30" r="2" fill="rgba(252,211,77,1)" filter="url(#glow)"/>
                        <circle cx="115" cy="30" r="4" fill="rgba(252,211,77,0.4)"/>
                        <circle cx="115" cy="30" r="7" fill="rgba(252,211,77,0.18)"/>
                        <circle cx="22" cy="28" r="1" fill="rgba(186,230,253,0.7)"/>
                    </svg>
                    <span class="skin-art art-burton" aria-hidden="true">
                        <img src="{{ asset('images/deep-dive/angler-deep-gothic.png') }}" width="170" height="81" alt="" loading="lazy">
                        <i class="bulb-glow"></i>
                    </span>
                    <span class="skin-art art-jackpot" aria-hidden="true">
                        <img src="{{ asset('images/deep-dive/angler-deep-jackpot.webp') }}" width="130" height="106" alt="" loading="lazy">
                        <i class="bulb-glow"></i>
                    </span>
                </div>

                <!-- Eel-like serpent silhouette, slow weaving (top) -->
                <div class="creature cross-reverse" style="right:0; top:35%; --dur:70s; animation-delay:-40s;">
                    <svg width="280" height="40" viewBox="0 0 280 40" xmlns="http://www.w3.org/2000/svg">
                        <path d="M 5 20
                                 Q 40 5 80 20
                                 Q 120 35 160 20
                                 Q 200 5 240 20
                                 Q 260 26 270 22
                                 L 268 25
                                 Q 240 30 200 25
                                 Q 160 30 120 25
                                 Q 80 30 40 25
                                 Q 15 22 5 20 Z"
                              fill="rgba(2,6,23,0.92)" stroke="rgba(15,23,42,1)" stroke-width="0.5"/>
                        <circle cx="20" cy="20" r="1.2" fill="rgba(255,255,255,0.55)"/>
                    </svg>
                </div>
                @endif

                @if ($seaLife['octopi'])
                <!-- Octopus, slow drift right → left with swaying tentacles -->
                <div class="creature octopus cross-reverse" style="right:0; top:42%; --dur:64s; animation-delay:-25s;">
                    <svg width="220" height="200" viewBox="0 0 220 200" xmlns="http://www.w3.org/2000/svg">
                        <!-- Tentacles (animated sway via group) -->
                        <g class="tentacles">
                            <!-- 8 curling tentacles fanning out from the mantle -->
                            <path d="M 60 95 Q 35 130 30 165 Q 28 180 36 178 Q 32 170 38 160 Q 50 130 70 105 Z"
                                  fill="rgba(2,6,23,0.92)" stroke="rgba(15,23,42,1)" stroke-width="0.7"/>
                            <path d="M 78 100 Q 60 145 70 185 Q 78 188 76 180 Q 70 165 80 140 Q 88 115 90 105 Z"
                                  fill="rgba(2,6,23,0.92)" stroke="rgba(15,23,42,1)" stroke-width="0.7"/>
                            <path d="M 95 105 Q 88 155 100 195 Q 110 195 105 185 Q 96 165 105 130 Q 108 115 105 105 Z"
                                  fill="rgba(2,6,23,0.92)" stroke="rgba(15,23,42,1)" stroke-width="0.7"/>
                            <path d="M 115 105 Q 115 155 120 198 Q 128 196 124 188 Q 118 165 122 130 Q 122 115 122 105 Z"
                                  fill="rgba(2,6,23,0.94)" stroke="rgba(15,23,42,1)" stroke-width="0.7"/>
                            <path d="M 135 105 Q 142 155 138 195 Q 130 195 134 185 Q 142 165 134 130 Q 130 115 130 105 Z"
                                  fill="rgba(2,6,23,0.94)" stroke="rgba(15,23,42,1)" stroke-width="0.7"/>
                            <path d="M 152 100 Q 170 145 158 185 Q 150 188 152 178 Q 158 165 148 140 Q 142 115 142 105 Z"
                                  fill="rgba(2,6,23,0.92)" stroke="rgba(15,23,42,1)" stroke-width="0.7"/>
                            <path d="M 170 95 Q 195 130 195 165 Q 200 180 188 178 Q 192 170 188 160 Q 175 130 160 105 Z"
                                  fill="rgba(2,6,23,0.92)" stroke="rgba(15,23,42,1)" stroke-width="0.7"/>
                            <path d="M 50 85 Q 18 105 14 140 Q 12 155 22 152 Q 18 145 24 132 Q 38 110 60 95 Z"
                                  fill="rgba(2,6,23,0.9)" stroke="rgba(15,23,42,1)" stroke-width="0.7"/>
                        </g>

                        <!-- Mantle / head -->
                        <ellipse cx="110" cy="60" rx="62" ry="46" fill="rgba(2,6,23,0.96)" stroke="rgba(15,23,42,1)" stroke-width="0.9"/>

                        <!-- Eyes -->
                        <ellipse cx="88" cy="55" rx="9" ry="7" fill="white"/>
                        <ellipse cx="88" cy="55" rx="3.5" ry="6" fill="#0f172a"/>
                        <circle cx="89.5" cy="53" r="1.4" fill="white"/>
                        <ellipse cx="132" cy="55" rx="9" ry="7" fill="white"/>
                        <ellipse cx="132" cy="55" rx="3.5" ry="6" fill="#0f172a"/>
                        <circle cx="133.5" cy="53" r="1.4" fill="white"/>

                        <!-- Mantle highlight (texture) -->
                        <ellipse cx="100" cy="32" rx="32" ry="6" fill="rgba(255,255,255,0.10)"/>
                        <!-- Bioluminescent spots along mantle -->
                        <circle cx="60" cy="68" r="1.3" fill="rgba(186,230,253,0.65)"/>
                        <circle cx="160" cy="65" r="1.3" fill="rgba(186,230,253,0.65)"/>
                        <circle cx="155" cy="80" r="1" fill="rgba(186,230,253,0.5)"/>
                        <circle cx="65" cy="82" r="1" fill="rgba(186,230,253,0.5)"/>
                    </svg>
                    <img class="skin-art art-burton" src="{{ asset('images/deep-dive/octopus-gothic.png') }}" width="190" height="143" alt="" aria-hidden="true" loading="lazy">
                    <img class="skin-art art-jackpot" src="{{ asset('images/deep-dive/octopus-jackpot.webp') }}" width="162" height="168" alt="" aria-hidden="true" loading="lazy">
                </div>

                {{-- Mini octopi — five silhouettes swimming upward at random
                     horizontal lanes, with offset animation delays so they
                     never all leave the screen at once. --}}
                @foreach ([
                    ['left' => '12%', 'dur' => '16s', 'delay' => '-3s',  'size' => 56],
                    ['left' => '32%', 'dur' => '21s', 'delay' => '-12s', 'size' => 44],
                    ['left' => '54%', 'dur' => '14s', 'delay' => '-7s',  'size' => 62],
                    ['left' => '72%', 'dur' => '19s', 'delay' => '-15s', 'size' => 48],
                    ['left' => '88%', 'dur' => '23s', 'delay' => '-9s',  'size' => 40],
                ] as $o)
                <div class="creature octopus mini" style="left:{{ $o['left'] }}; top:0; --dur:{{ $o['dur'] }}; animation-delay:{{ $o['delay'] }};">
                    <svg width="{{ $o['size'] }}" height="{{ (int) ($o['size'] * 1.25) }}" viewBox="0 0 80 100" xmlns="http://www.w3.org/2000/svg">
                        <g class="tentacles">
                            <path d="M 18 38 Q  6 60 10 92" stroke="rgba(2,6,23,0.92)" stroke-width="4"   fill="none" stroke-linecap="round"/>
                            <path d="M 30 44 Q 26 70 28 96" stroke="rgba(2,6,23,0.92)" stroke-width="4"   fill="none" stroke-linecap="round"/>
                            <path d="M 42 44 Q 48 70 44 96" stroke="rgba(2,6,23,0.92)" stroke-width="4"   fill="none" stroke-linecap="round"/>
                            <path d="M 54 38 Q 68 60 62 92" stroke="rgba(2,6,23,0.92)" stroke-width="4"   fill="none" stroke-linecap="round"/>
                            <path d="M 10 30 Q -2 50  4 76" stroke="rgba(2,6,23,0.88)" stroke-width="3.5" fill="none" stroke-linecap="round"/>
                            <path d="M 62 30 Q 78 50 70 76" stroke="rgba(2,6,23,0.88)" stroke-width="3.5" fill="none" stroke-linecap="round"/>
                        </g>
                        <ellipse cx="36" cy="26" rx="26" ry="20" fill="rgba(2,6,23,0.96)" stroke="rgba(15,23,42,1)" stroke-width="0.5"/>
                        <ellipse cx="27" cy="24" rx="3.2" ry="2.6" fill="white"/>
                        <circle  cx="28" cy="23.5" r="1.2" fill="#0f172a"/>
                        <ellipse cx="45" cy="24" rx="3.2" ry="2.6" fill="white"/>
                        <circle  cx="46" cy="23.5" r="1.2" fill="#0f172a"/>
                        <circle  cx="20" cy="34" r="0.9" fill="rgba(186,230,253,0.55)"/>
                        <circle  cx="52" cy="32" r="0.9" fill="rgba(186,230,253,0.55)"/>
                    </svg>
                    <img class="skin-art art-burton" src="{{ asset('images/deep-dive/octopus-gothic.png') }}" width="{{ (int) ($o['size'] * 1.4) }}" height="{{ (int) ($o['size'] * 1.05) }}" alt="" aria-hidden="true" loading="lazy">
                    <img class="skin-art art-jackpot" src="{{ asset('images/deep-dive/octopus-jackpot.webp') }}" width="{{ (int) ($o['size'] * 1.19) }}" height="{{ (int) ($o['size'] * 1.23) }}" alt="" aria-hidden="true" loading="lazy">
                </div>
                @endforeach
                @endif
            </div>

            @if ($seaLife['jellyfish'])
            <div class="jelly" style="left:12%; top:50%; animation-delay:-2s;"><img class="skin-art art-burton" src="{{ asset('images/deep-dive/jelly-gothic.png') }}" width="60" height="86" alt="" aria-hidden="true" loading="lazy"><img class="skin-art art-jackpot" src="{{ asset('images/deep-dive/jelly-jackpot.webp') }}" width="55" height="95" alt="" aria-hidden="true" loading="lazy"></div>
            <div class="jelly" style="left:80%; top:35%; animation-delay:-5s;"><img class="skin-art art-burton" src="{{ asset('images/deep-dive/jelly-gothic.png') }}" width="60" height="86" alt="" aria-hidden="true" loading="lazy"><img class="skin-art art-jackpot" src="{{ asset('images/deep-dive/jelly-jackpot.webp') }}" width="55" height="95" alt="" aria-hidden="true" loading="lazy"></div>
            <div class="jelly" style="left:25%; top:70%; animation-delay:-7s;"><img class="skin-art art-burton" src="{{ asset('images/deep-dive/jelly-gothic.png') }}" width="60" height="86" alt="" aria-hidden="true" loading="lazy"><img class="skin-art art-jackpot" src="{{ asset('images/deep-dive/jelly-jackpot.webp') }}" width="55" height="95" alt="" aria-hidden="true" loading="lazy"></div>
            @endif

            <div class="sub-wrap">
                <svg class="sub-svg" viewBox="0 0 240 130" xmlns="http://www.w3.org/2000/svg">
                    <defs>
                        {{-- Cartoon "cardboard" submarine: flat cel colours, soft
                             top-to-bottom shading, thick ink outlines. Bow points
                             right (x~224) so the nose-down tilt / wake / bubble
                             mechanics keep working unchanged. --}}
                        <linearGradient id="hullGrad" x1="0" x2="0" y1="0" y2="1">
                            <stop offset="0"    stop-color="#ffe070"/>
                            <stop offset="0.5"  stop-color="#ffc932"/>
                            <stop offset="1"    stop-color="#f4a71d"/>
                        </linearGradient>
                        <linearGradient id="noseGrad" x1="0" x2="0" y1="0" y2="1">
                            <stop offset="0"    stop-color="#8dbde9"/>
                            <stop offset="0.5"  stop-color="#5289c9"/>
                            <stop offset="1"    stop-color="#376aa8"/>
                        </linearGradient>
                        <linearGradient id="finGrad" x1="0" x2="0" y1="0" y2="1">
                            <stop offset="0" stop-color="#5d93cf"/>
                            <stop offset="1" stop-color="#3a6ba6"/>
                        </linearGradient>
                        <radialGradient id="glass" cx="0.35" cy="0.3" r="0.9">
                            <stop offset="0"    stop-color="#eefaff"/>
                            <stop offset="0.45" stop-color="#a9dcf4"/>
                            <stop offset="1"    stop-color="#5aa4da"/>
                        </radialGradient>
                        <linearGradient id="propGrad" x1="0" x2="0" y1="0" y2="1">
                            <stop offset="0" stop-color="#f47272"/>
                            <stop offset="1" stop-color="#c92e2e"/>
                        </linearGradient>
                        <linearGradient id="wakeGrad" x1="0" y1="0" x2="-220" y2="-50" gradientUnits="userSpaceOnUse">
                            <stop offset="0%"   stop-color="#bae6fd" stop-opacity="0.70"/>
                            <stop offset="35%"  stop-color="#bae6fd" stop-opacity="0.40"/>
                            <stop offset="80%"  stop-color="#bae6fd" stop-opacity="0.10"/>
                            <stop offset="100%" stop-color="#bae6fd" stop-opacity="0"/>
                        </linearGradient>
                        {{-- Hull silhouette reused as a clip so the nose cap and
                             belly shade never bleed outside the outline. --}}
                        <clipPath id="hullClip">
                            <path d="M 44 64 C 44 44 58 34 84 33 L 156 32 C 200 32 224 46 224 64 C 224 82 200 96 156 96 L 84 95 C 58 94 44 84 44 64 Z"/>
                        </clipPath>
                    </defs>

                    <!-- wake + bubble trail: local origin at the propeller hub, behind everything -->
                    <g transform="translate(22, 64)">
                        {{-- Curved tapered wake body, bows UPWARD in local
                             frame. After the nose-down rotation this reads as a
                             fountain-style arc trailing the sub, water rising
                             past us as we descend. --}}
                        <path class="sub-wake"
                              d="M 0 -3
                                 Q -50 -22 -110 -38
                                 Q -170 -50 -210 -52
                                 Q -170 -46 -110 -32
                                 Q -50 -16 0 3 Z"
                              fill="url(#wakeGrad)"/>
                        {{-- Three parallel curved scrolling rails: a bright spine
                             plus two thinner offset arcs for layered turbulence. --}}
                        <path class="sub-wake-core"
                              d="M 0 0
                                 Q -50 -14 -110 -30
                                 Q -170 -44 -210 -48"
                              fill="none" stroke="rgba(255,255,255,0.60)" stroke-width="0.7" stroke-linecap="round"/>
                        <path class="sub-wake-core alt"
                              d="M 0 -1.6
                                 Q -50 -18 -100 -34
                                 Q -160 -47 -195 -50"
                              fill="none" stroke="rgba(186,230,253,0.55)" stroke-width="0.5" stroke-linecap="round"/>
                        <path class="sub-wake-core alt-2"
                              d="M 0 1.6
                                 Q -50 -10 -110 -26
                                 Q -170 -42 -200 -45"
                              fill="none" stroke="rgba(186,230,253,0.55)" stroke-width="0.5" stroke-linecap="round"/>

                        {{-- Bubble trail: each circle rises up-back from local (0,0),
                             staggered via per-element animation-delay so the stream
                             feels continuous. Path goes up-and-back in SVG space,
                             which rotates with the nose-down tilt. --}}
                        <g class="sub-bubbles" fill="rgba(186,230,253,0.78)" stroke="rgba(255,255,255,0.55)" stroke-width="0.4">
                            <circle class="prop-bubble"       cx="-8"  cy="0"  r="2.4" style="animation-delay: 0s;"/>
                            <circle class="prop-bubble var-2" cx="-6"  cy="-2" r="1.6" style="animation-delay: -0.10s;"/>
                            <circle class="prop-bubble var-3" cx="-9"  cy="2"  r="2.0" style="animation-delay: -0.20s;"/>
                            <circle class="prop-bubble"       cx="-7"  cy="-3" r="1.3" style="animation-delay: -0.30s;"/>
                            <circle class="prop-bubble var-2" cx="-10" cy="1"  r="2.8" style="animation-delay: -0.40s;"/>
                            <circle class="prop-bubble var-3" cx="-5"  cy="-1" r="1.1" style="animation-delay: -0.50s;"/>
                            <circle class="prop-bubble"       cx="-8"  cy="3"  r="2.2" style="animation-delay: -0.60s;"/>
                            <circle class="prop-bubble var-2" cx="-6"  cy="-4" r="1.5" style="animation-delay: -0.70s;"/>
                            <circle class="prop-bubble var-3" cx="-9"  cy="0"  r="1.9" style="animation-delay: -0.80s;"/>
                            <circle class="prop-bubble"       cx="-11" cy="-2" r="2.4" style="animation-delay: -0.90s;"/>
                            <circle class="prop-bubble var-2" cx="-4"  cy="2"  r="1.2" style="animation-delay: -1.00s;"/>
                            <circle class="prop-bubble var-3" cx="-8"  cy="-3" r="1.7" style="animation-delay: -1.10s;"/>
                            <circle class="prop-bubble"       cx="-10" cy="1"  r="2.1" style="animation-delay: -1.20s;"/>
                            <circle class="prop-bubble var-2" cx="-6"  cy="3"  r="1.4" style="animation-delay: -1.30s;"/>
                            <circle class="prop-bubble var-3" cx="-9"  cy="-1" r="2.6" style="animation-delay: -1.40s;"/>
                            <circle class="prop-bubble"       cx="-7"  cy="2"  r="1.0" style="animation-delay: -1.50s;"/>
                            <circle class="prop-bubble var-2" cx="-11" cy="0"  r="2.3" style="animation-delay: -1.60s;"/>
                            <circle class="prop-bubble var-3" cx="-5"  cy="-2" r="1.5" style="animation-delay: -1.70s;"/>
                            <circle class="prop-bubble"       cx="-8"  cy="3"  r="1.8" style="animation-delay: -1.85s;"/>
                            <circle class="prop-bubble var-2" cx="-10" cy="-3" r="2.0" style="animation-delay: -2.00s;"/>
                        </g>
                    </g>

                    {{-- Cartoon hull. Hidden on the gothic skin, which swaps in a
                         raster sub below; wake + bubbles above stay shared. --}}
                    <g class="sub-body">
                    {{-- Tail fins (swept back, tucked behind the hull) --}}
                    <path d="M 86 36 L 70 14 L 50 16 L 46 48 Z"   fill="url(#finGrad)" stroke="#1f2a37" stroke-width="2" stroke-linejoin="round"/>
                    <path d="M 86 92 L 70 114 L 50 112 L 46 80 Z" fill="url(#finGrad)" stroke="#1f2a37" stroke-width="2" stroke-linejoin="round"/>

                    {{-- Conning tower (drawn before the hull so its base tucks under the deck) --}}
                    <rect x="102" y="18" width="62" height="22" rx="7" fill="url(#hullGrad)" stroke="#1f2a37" stroke-width="2"/>

                    {{-- Hull: fat yellow capsule, big round bow --}}
                    <path d="M 44 64 C 44 44 58 34 84 33 L 156 32 C 200 32 224 46 224 64 C 224 82 200 96 156 96 L 84 95 C 58 94 44 84 44 64 Z"
                          fill="url(#hullGrad)" stroke="#1f2a37" stroke-width="2.2"/>
                    {{-- belly shade --}}
                    <path d="M 44 76 Q 130 86 224 76 L 224 100 L 44 100 Z" fill="#e0901a" opacity="0.4" clip-path="url(#hullClip)"/>
                    {{-- deck gloss --}}
                    <path d="M 62 41 Q 130 35 204 42" stroke="#fff" stroke-width="3.5" fill="none" opacity="0.35" stroke-linecap="round"/>

                    {{-- Blue nose cap with riveted seam --}}
                    <path d="M 178 20 Q 170 64 178 108 L 240 108 L 240 20 Z" fill="url(#noseGrad)" clip-path="url(#hullClip)"/>
                    <path d="M 178 20 Q 170 64 178 108" fill="none" stroke="#1f2a37" stroke-width="2" clip-path="url(#hullClip)"/>
                    <path d="M 186 44 Q 204 38 214 50" stroke="#fff" stroke-width="3" fill="none" opacity="0.45" stroke-linecap="round"/>
                    <g fill="#d19a1f" stroke="#1f2a37" stroke-width="0.7">
                        <circle cx="170.5" cy="38" r="1.5"/>
                        <circle cx="169.4" cy="51" r="1.5"/>
                        <circle cx="169"   cy="64" r="1.5"/>
                        <circle cx="169.4" cy="77" r="1.5"/>
                        <circle cx="170.5" cy="90" r="1.5"/>
                    </g>
                    {{-- hull outline redrawn on top so the cap / shade fills do not eat it --}}
                    <path d="M 44 64 C 44 44 58 34 84 33 L 156 32 C 200 32 224 46 224 64 C 224 82 200 96 156 96 L 84 95 C 58 94 44 84 44 64 Z"
                          fill="none" stroke="#1f2a37" stroke-width="2.2"/>

                    {{-- Deck rivet row --}}
                    <g fill="#d19a1f" stroke="#1f2a37" stroke-width="0.6">
                        <circle cx="64"  cy="42" r="1.3"/>
                        <circle cx="76"  cy="42" r="1.3"/>
                        <circle cx="88"  cy="42" r="1.3"/>
                        <circle cx="100" cy="42" r="1.3"/>
                        <circle cx="112" cy="42" r="1.3"/>
                        <circle cx="124" cy="42" r="1.3"/>
                        <circle cx="136" cy="42" r="1.3"/>
                        <circle cx="148" cy="42" r="1.3"/>
                        <circle cx="160" cy="42" r="1.3"/>
                    </g>

                    {{-- Three portholes --}}
                    @foreach ([86, 120, 154] as $px)
                    <g>
                        <circle cx="{{ $px }}" cy="66" r="13"  fill="#7d9bbd" stroke="#1f2a37" stroke-width="2"/>
                        <circle cx="{{ $px }}" cy="66" r="9.5" fill="url(#glass)" stroke="#33557a" stroke-width="1.2"/>
                        <ellipse cx="{{ $px - 3.5 }}" cy="62" rx="3" ry="1.8" transform="rotate(-35 {{ $px - 3.5 }} 62)" fill="#fff" opacity="0.8"/>
                        <g fill="#4a6a8e" stroke="#1f2a37" stroke-width="0.5">
                            <circle cx="{{ $px - 8.1 }}" cy="57.9" r="1.3"/>
                            <circle cx="{{ $px + 8.1 }}" cy="57.9" r="1.3"/>
                            <circle cx="{{ $px - 8.1 }}" cy="74.1" r="1.3"/>
                            <circle cx="{{ $px + 8.1 }}" cy="74.1" r="1.3"/>
                        </g>
                    </g>
                    @endforeach

                    {{-- Tower details: periscope, lens, antenna + running light, small porthole --}}
                    <path d="M 116 22 L 116 11 Q 116 6 121 6 L 131 6" fill="none" stroke="#1f2a37" stroke-width="8.5" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M 116 22 L 116 11 Q 116 6 121 6 L 131 6" fill="none" stroke="#ffc932" stroke-width="5"   stroke-linecap="round" stroke-linejoin="round"/>
                    <rect x="110.5" y="17" width="11" height="6" rx="2" fill="#f4a71d" stroke="#1f2a37" stroke-width="1.5"/>
                    <circle cx="136"   cy="6"   r="6"   fill="#1f2a37"/>
                    <circle cx="136"   cy="6"   r="4.6" fill="#7d9bbd"/>
                    <circle cx="136"   cy="6"   r="3.1" fill="url(#glass)"/>
                    <circle cx="134.9" cy="4.9" r="1"   fill="#fff" opacity="0.85"/>
                    <line x1="156" y1="18" x2="156" y2="11" stroke="#1f2a37" stroke-width="2" stroke-linecap="round"/>
                    <circle class="periscope-light" cx="156" cy="9.5" r="2.4" fill="#ef4444"/>
                    <circle cx="150" cy="26.5" r="5"   fill="#7d9bbd" stroke="#1f2a37" stroke-width="1.5"/>
                    <circle cx="150" cy="26.5" r="3.2" fill="url(#glass)" stroke="#33557a" stroke-width="0.8"/>
                    <g fill="#d19a1f" stroke="#1f2a37" stroke-width="0.6">
                        <circle cx="126" cy="29" r="1.1"/>
                        <circle cx="133" cy="29" r="1.1"/>
                        <circle cx="140" cy="29" r="1.1"/>
                    </g>

                    {{-- Stern: prop shaft, horizontal stabiliser, red 3-blade propeller --}}
                    <rect x="26" y="61.5" width="22" height="5" rx="2" fill="#3b4a5c" stroke="#1f2a37" stroke-width="1.2"/>
                    <path d="M 76 56 L 44 48 L 32 64 L 44 80 L 76 72 Z" fill="url(#finGrad)" stroke="#1f2a37" stroke-width="2" stroke-linejoin="round"/>
                    <g transform="translate(22, 64)">
                        <g class="propeller">
                            {{-- invisible disc keeps the fill-box symmetric so the
                                 3-blade rotor spins about its hub, not its bbox --}}
                            <circle r="18.5" fill="none" stroke="none"/>
                            <ellipse cx="0" cy="-9" rx="4.3" ry="9.5" fill="url(#propGrad)" stroke="#1f2a37" stroke-width="1.3"/>
                            <ellipse cx="0" cy="-9" rx="4.3" ry="9.5" fill="url(#propGrad)" stroke="#1f2a37" stroke-width="1.3" transform="rotate(120)"/>
                            <ellipse cx="0" cy="-9" rx="4.3" ry="9.5" fill="url(#propGrad)" stroke="#1f2a37" stroke-width="1.3" transform="rotate(240)"/>
                            <circle r="4" fill="#f47272" stroke="#1f2a37" stroke-width="1.3"/>
                        </g>
                    </g>
                    </g>

                    {{-- Gothic skin sub. Placed so its spiral propeller sits on the
                         wake/bubble origin (22,64); lantern glow flickers on top. --}}
                    <g class="sub-gothic" aria-hidden="true">
                        <defs>
                            <radialGradient id="gothicLanternGlow">
                                <stop offset="0%"   stop-color="#fff4c2" stop-opacity="0.85"/>
                                <stop offset="45%"  stop-color="#f5e08a" stop-opacity="0.35"/>
                                <stop offset="100%" stop-color="#f5e08a" stop-opacity="0"/>
                            </radialGradient>
                        </defs>
                        <image class="sub-prop" href="{{ asset('images/deep-dive/sub-gothic-prop.webp') }}?v={{ filemtime(public_path('images/deep-dive/sub-gothic-prop.webp')) }}" x="5.84" y="46.06" width="20.06" height="39.03" style="--axis: 51.4%" preserveAspectRatio="none"/>
                        <image href="{{ asset('images/deep-dive/sub-gothic-hull.webp') }}?v={{ filemtime(public_path('images/deep-dive/sub-gothic-hull.webp')) }}" x="-5" y="-32" width="232" height="137.4" preserveAspectRatio="xMidYMid meet"/>
                        <circle class="gothic-lantern-glow" cx="171" cy="-5" r="20" fill="url(#gothicLanternGlow)"/>
                    </g>

                    {{-- Jackpot Reef sub: its propeller hub on the same wake origin (22,64). --}}
                    <g class="sub-jackpot" aria-hidden="true">
                        <image class="sub-prop" href="{{ asset('images/deep-dive/sub-jackpot-prop.webp') }}?v={{ filemtime(public_path('images/deep-dive/sub-jackpot-prop.webp')) }}" x="14.2" y="35.53" width="12.85" height="56.42" style="--axis: 52%" preserveAspectRatio="none"/>
                        <image href="{{ asset('images/deep-dive/sub-jackpot-hull.webp') }}?v={{ filemtime(public_path('images/deep-dive/sub-jackpot-hull.webp')) }}" x="14.2" y="-34.3" width="200" height="145.8" preserveAspectRatio="xMidYMid meet"/>
                    </g>
                </svg>
                {{-- Gothic implosion sprite; shares .sub-svg so it tilts exactly like the sub. --}}
                <div class="sub-svg sub-boom" aria-hidden="true"><span></span></div>
            </div>
            <div id="bubbles-near" class="bubbles bubbles-near"></div>
        </div>

