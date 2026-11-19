/* (7) Submarine. Gothic skin: a 7-frame sprite (cracks → burst → hull torn
           in two → sinking halves) plays over the sub, tilted with it via the
           shared .sub-svg rotation. The screen flash, shockwave, debris and banner
           wait for the burst frame (--boom-at) so the sub visibly cracks first.
           `translate` (not transform) so the embed offset on .sub-wrap is kept. */
        :root { --boom-at: 0s; }
        :root[data-theme="burton"] { --boom-at: 0.17s; }
        :root[data-theme="jackpot"] { --boom-at: 0.45s; }
        .crashed .sub-wrap { animation: sub-collapse 1.7s ease-in forwards; }
        /* Jackpot's sprite runs longer (the wreck settles): fade the sub out later. */
        :root[data-theme="jackpot"] .crashed .sub-wrap { animation-duration: 2.6s; }
        @keyframes sub-collapse {
            0%, 55% { translate: 0 0; opacity: 1; }
            100%    { translate: 0 6vh; opacity: 0; }
        }
        .sub-boom { display: none; position: absolute; inset: 0; pointer-events: none; }
        /* Frame cell placed so frame 1's sub sits exactly on the in-game art. */
        .sub-boom span {
            position: absolute; left: -8.15%; top: -48.8%; width: 106%; height: 214.5%;
            background: url("{{ asset('images/deep-dive/sub-explosion.webp') }}") 0 0 / 700% 100% no-repeat;
            opacity: 0;
        }
        /* Jackpot's sprite: 36 frames in a 6x6 grid (the hull strains, cracks and crumples,
           the flash, a shockwave and a gas bubble round the torn hull, the burst of coins,
           bubbles and silt, then the wreck settling). */
        :root[data-theme="jackpot"] .sub-boom span {
            left: -4.83%; top: -39.17%; width: 97.79%; height: 160.54%;
            background-image: url("{{ asset('images/deep-dive/sub-explosion-jackpot.webp') }}?v={{ filemtime(public_path('images/deep-dive/sub-explosion-jackpot.webp')) }}");
            background-size: 600% 600%;
        }
        :root:is([data-theme="burton"], [data-theme="jackpot"]) .sub-boom { display: block; }
        :root[data-theme="burton"] .crashed .sub-gothic,
        :root[data-theme="jackpot"] .crashed .sub-jackpot { opacity: 0; }
        :root:is([data-theme="burton"], [data-theme="jackpot"]) .crashed .sub-boom span { opacity: 1; animation: sub-boom 1.25s step-end forwards; }
        @keyframes sub-boom {
            0%        { background-position-x: 0%; }
            5%        { background-position-x: 16.667%; }
            10%       { background-position-x: 33.333%; }
            14%       { background-position-x: 50%; }      /* burst — matches --boom-at */
            28%       { background-position-x: 66.667%; }
            44%       { background-position-x: 83.333%; }
            62%, 100% { background-position-x: 100%; }
        }
        /* Frame n (0-35) at column n % 6, row n / 6; the full flash (frame 12) at 0.45s = --boom-at. */
        :root[data-theme="jackpot"] .crashed .sub-boom span { animation: sub-boom-jackpot 2.07s step-end forwards; }
        @keyframes sub-boom-jackpot {
            0.00%          { background-position: 0.000% 0.000%; }
            2.66%          { background-position: 20.000% 0.000%; }
            4.35%          { background-position: 40.000% 0.000%; }
            6.04%          { background-position: 60.000% 0.000%; }
            7.73%          { background-position: 80.000% 0.000%; }
            9.42%          { background-position: 100.000% 0.000%; }
            11.11%         { background-position: 0.000% 20.000%; }
            12.80%         { background-position: 20.000% 20.000%; }
            14.49%         { background-position: 40.000% 20.000%; }
            16.18%         { background-position: 60.000% 20.000%; }
            17.87%         { background-position: 80.000% 20.000%; }
            19.81%         { background-position: 100.000% 20.000%; }
            21.74%         { background-position: 0.000% 40.000%; }   /* the flash: 0.45s */
            25.12%         { background-position: 20.000% 40.000%; }
            27.54%         { background-position: 40.000% 40.000%; }
            29.71%         { background-position: 60.000% 40.000%; }
            31.88%         { background-position: 80.000% 40.000%; }
            34.06%         { background-position: 100.000% 40.000%; }
            36.23%         { background-position: 0.000% 60.000%; }
            38.65%         { background-position: 20.000% 60.000%; }
            41.06%         { background-position: 40.000% 60.000%; }
            43.48%         { background-position: 60.000% 60.000%; }
            45.89%         { background-position: 80.000% 60.000%; }
            48.55%         { background-position: 100.000% 60.000%; }
            51.45%         { background-position: 0.000% 80.000%; }
            54.35%         { background-position: 20.000% 80.000%; }
            57.25%         { background-position: 40.000% 80.000%; }
            60.14%         { background-position: 60.000% 80.000%; }
            63.29%         { background-position: 80.000% 80.000%; }
            66.43%         { background-position: 100.000% 80.000%; }
            69.57%         { background-position: 0.000% 100.000%; }
            72.95%         { background-position: 20.000% 100.000%; }
            76.33%         { background-position: 40.000% 100.000%; }
            79.95%         { background-position: 60.000% 100.000%; }
            83.82%         { background-position: 80.000% 100.000%; }
            87.92%, 100%   { background-position: 100.000% 100.000%; }
        }

