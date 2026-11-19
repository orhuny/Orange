{{-- The host's poses, stacked: slot-game.js moves `on` to the pose she shows, and the CSS
     crossfades them ("The host" in slot.blade.php). Each has its own size and wing root. --}}
@foreach ($hostPoses as $pose => $art)
    <div @class(['host-fig', 'on' => $pose === 'idle']) data-pose="{{ $pose }}" style="{{ $hostStyle($art) }}">
        <div class="host-body">
            @if ($hostWing)
                <img class="wing far" src="{{ $hostWing['src'] }}" alt=""><img class="wing near" src="{{ $hostWing['src'] }}" alt="">
            @endif
            <img class="pose" src="{{ $art['src'] }}" alt="" decoding="async">
        </div>
    </div>
@endforeach
