<div class="is-stage">
            <div class="is-layer is-bg"></div>
            <div class="is-layer is-tint"></div>
            <div class="is-rays"></div>
            <div class="is-layer is-motes">@for ($m = 0; $m < 18; $m++)<i style="--x: {{ ($m * 37) % 100 }}%; --s: {{ 2 + $m % 4 }}px; --t: {{ 2.2 + ($m % 5) * 0.45 }}s; --d: -{{ fmod($m * 0.61, 3) }}s"></i>@endfor</div>
            <img class="is-whale art-burton" src="{{ asset('images/whale-gothic.png') }}" alt="">
            <img class="is-whale art-jackpot" src="{{ asset('images/whale-jackpot.webp') }}?v={{ filemtime(public_path('images/whale-jackpot.webp')) }}" alt="">
            <img class="is-fish is-fish-1 art-burton" src="{{ asset('images/fish-gothic.png') }}" alt="">
            <img class="is-fish is-fish-1 art-jackpot" src="{{ asset('images/fish-jackpot.webp') }}" alt="">
            <img class="is-fish is-fish-2 art-burton" src="{{ asset('images/fish-puffer-gothic.png') }}" alt="">
            <img class="is-fish is-fish-2 art-jackpot" src="{{ asset('images/fish-puffer-jackpot.webp') }}" alt="">
            <div class="is-layer is-pings"><i style="--n: 0"></i><i style="--n: 1"></i><i style="--n: 2"></i><i style="--n: 3.3"></i></div>
            <div class="is-layer is-sub-path">
                <div class="is-sub-fly">
                    <div class="is-sub-bob">
                        <img class="is-sub art-burton" src="{{ asset('images/sub-gothic.png') }}" width="856" height="507" alt="">
                        <img class="is-sub art-jackpot" src="{{ asset('images/sub-jackpot.webp') }}" width="716" height="522" alt="">
                        <span class="is-beam"></span>
                        <div class="is-bubbles">
                            <i style="--s:9px;  --d:0s;    --t:1.5s; --x:-70px"></i>
                            <i style="--s:6px;  --d:0.25s; --t:1.3s; --x:-40px"></i>
                            <i style="--s:12px; --d:0.5s;  --t:1.8s; --x:-95px"></i>
                            <i style="--s:5px;  --d:0.7s;  --t:1.2s; --x:-30px"></i>
                            <i style="--s:8px;  --d:0.95s; --t:1.6s; --x:-60px"></i>
                            <i style="--s:10px; --d:1.2s;  --t:1.7s; --x:-85px"></i>
                        </div>
                        <div class="is-burst">@for ($m = 0; $m < 14; $m++)<i style="--a: {{ round($m * 360 / 14 + ($m % 3) * 9) }}deg; --r: {{ 18 + ($m * 7) % 16 }}vmin; --s: {{ 6 + ($m * 5) % 12 }}px; --d: {{ ($m % 4) * 40 }}ms"></i>@endfor</div>
                        <span class="is-lantern"></span>
                    </div>
                </div>
            </div>
            <div class="is-layer is-flash"></div>
            <h1 class="is-title" aria-label="{{ $intro['title'] }}">@foreach (mb_str_split($intro['title']) as $i => $ch)<span style="--i: {{ $i }}">{{ $ch }}</span>@endforeach</h1>
            @if ($intro['tagline'] !== '')
            <p class="is-tag"><b></b><span>{{ $intro['tagline'] }}</span><b></b></p>
            @endif
            <div class="is-layer is-speed"></div>
            <div class="is-layer is-vignette"></div>
        </div>
