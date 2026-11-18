<div class="is-stage">
            <div class="is-layer is-bg"></div>
            <div class="is-reeds"></div>
            <div class="is-duck"><div class="is-duck-sprite"></div></div>
            <img class="is-logo" src="{{ $sceneDef['art']('logo.webp') }}" alt="{{ $intro['title'] }}">
            @if ($intro['tagline'] !== '')
            <p class="is-tag"><b></b><span>{{ $intro['tagline'] }}</span><b></b></p>
            @endif
            <div class="is-layer is-flash"></div>
        </div>
