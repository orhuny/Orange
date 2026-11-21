{{-- One hostess's poses, stacked (the "duet" layout): the theme's kit moves `on` to the pose she
     shows and the CSS crossfades them. Each pose stands on the same feet line (--fy) with its
     body's centre at --cx and its own size (--k, of her idle pose). --}}
@foreach ($poses as $pose => $art)
    <div @class(['hostess-fig', 'on' => $pose === 'idle']) data-pose="{{ $pose }}"
         style="--cx: {{ $art['cx'] }}; --ar: {{ $art['ar'] }}; --k: {{ $art['k'] ?? 1 }}; --fy: {{ $art['feet'] ?? 1 }}">
        <div class="hostess-body"><img class="pose" src="{{ $art['src'] }}" alt="" decoding="async"></div>
    </div>
@endforeach
