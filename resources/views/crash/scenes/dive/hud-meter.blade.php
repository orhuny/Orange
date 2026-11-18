<!-- Depth meter: surface → Challenger Deep; ticks every 2 km on a linear depth axis -->
        <div class="depth-meter" id="depth-meter" aria-hidden="true">
            <div class="depth-track"></div>
            <div class="depth-fill"></div>
            @foreach ([0, 2, 4, 6, 8, 10] as $km)
            <span class="depth-tick" style="--at: {{ round($km * 1000 / 10994 * 100, 2) }}%">{{ $km === 0 ? '0 m' : $km.' km' }}</span>
            @endforeach
            <div class="depth-needle"><div class="depth-chip"><b id="depth-value">0 m</b><small id="depth-zone">SURFACE</small></div></div>
        </div>

