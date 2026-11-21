@php
    // The scene this crash game plays in (games.config.scene; see CrashPageController and
    // config('crash.scenes')): its skins, art, sounds and words, in
    // resources/views/crash/scenes/{scene}/. scene.php holds its data; the page includes
    // its partials at fixed places (@includeIf: a scene leaves out what it doesn't use).
    $scene ??= 'dive';
    $sceneDef = require resource_path("views/crash/scenes/{$scene}/scene.php");
    // The skins offered (picker, CSS, ?theme=, saved choice), in order; the first is the default.
    $themes = $sceneDef['themes'];
    $rgb = static fn (string $hex): string => implode(',', sscanf($hex, '#%02x%02x%02x'));

    // The game being played (CrashPageController). Its rounds go out on game.{code}, and
    // the page's saved state (skin, an armed auto bet, two bets) is its own, so nothing
    // carries from one crash game into another; the original game keeps the bare keys.
    $game ??= null;
    $roundChannel ??= 'crash';
    $storeSuffix = ($game instanceof \App\Domain\Game\Models\Game && $game->code !== 'crash') ? '.'.$game->code : '';

    // Which sea life swims behind the sub (config('crash.sea_life')); a kind not set there shows.
    $seaLife = array_merge(
        ['fish' => true, 'creatures' => true, 'octopi' => true, 'jellyfish' => true],
        (array) config('crash.sea_life', []),
    );

    // Studio intro frames (see config('crash.intro')): every image in the
    // folder, in natural file-name order, as public URLs.
    $introFrames = static function (string $dir): array {
        $files = glob(public_path("images/intro/{$dir}/*.{webp,png,jpg,jpeg}"), GLOB_BRACE) ?: [];
        natsort($files);
        return array_values(array_map(fn ($f) => asset("images/intro/{$dir}/" . basename($f)), $files));
    };
    $intro = null;
    if (config('crash.intro.enabled', false)) {
        $landscape = $introFrames('landscape');
        $portrait = $introFrames('portrait');
        $intro = [
            // Uploaded frames win; otherwise the built-in scene from the game art plays.
            'mode' => ($landscape || $portrait) ? 'frames' : 'scene',
            'title' => (string) config("crash.scenes.{$scene}.intro.title", config('crash.intro.title', 'DeepDive')),
            'tagline' => (string) config("crash.scenes.{$scene}.intro.tagline", config('crash.intro.tagline', '')),
            'landscape' => $landscape,
            'portrait' => $portrait,
            'bg' => $sceneDef['introBg'],
            'frameMs' => (int) config('crash.intro.frame_ms', 550),
            'fadeMs' => (int) config('crash.intro.fade_ms', 400),
            'holdMs' => (array) config('crash.intro.hold_ms', []),
            'oncePerSession' => (bool) config('crash.intro.once_per_session', true),
        ];
    }
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $sceneDef['titlePrefix'] }}{{ $game?->name ?? 'Orange Crash' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    {{-- Apply the theme before first paint so the page never flashes the
         default palette. Priority: player's saved choice → ?theme= (operator
         default on the embed URL) → first entry of $themes. --}}
    <script>
        (function () {
            var valid = @json(array_keys($themes));
            var t = null;
            try { t = localStorage.getItem(@json('orange.ui.theme'.$storeSuffix)); } catch (_) {}
            if (valid.indexOf(t) === -1) t = new URLSearchParams(location.search).get('theme');
            if (valid.indexOf(t) === -1) t = valid[0];
            document.documentElement.setAttribute('data-theme', t);
            // The skin's implosion sprite must be decoded before the first crash, not fetched during it.
            var sprites = @json($sceneDef['sprites']);
            if (sprites[t]) {
                var preload = document.createElement('link');
                preload.rel = 'preload';
                preload.as = 'image';
                preload.href = sprites[t];
                document.head.appendChild(preload);
            }
        })();
    </script>
    <style>
        /* Register dynamic CSS variables so they interpolate smoothly via
           `transition`. Without @property, custom properties switch
           instantly when the stage class changes, which makes every bubble,
           particle, and creature jump to its new trajectory at once. With
           these declarations the browser can animate the variable values
           over time and the keyframes are re-evaluated each frame with a
           smoothly drifting target — no visible frame skip on state shifts. */
        @property --drift-x {
            syntax: "<length-percentage>";
            inherits: true;
            initial-value: -15px;
        }
        @property --p-drift {
            syntax: "<length-percentage>";
            inherits: true;
            initial-value: 40px;
        }
        @property --speed {
            syntax: "<number>";
            inherits: true;
            initial-value: 1;
        }

        /* ---------- Theme tokens ----------
           Every UI colour derives from these. The `-rgb` twins feed the
           rgba() glows/borders. One block per skin in $themes; the first is
           also the bare :root default. `html[data-theme]` is set by the
           <head> bootstrap + the top-right picker. */
        @foreach ($themes as $key => $t)
        {{ $loop->first ? ':root,' : '' }} :root[data-theme="{{ $key }}"] {
            --shallow:    {{ $t['shallow'] }};
            --mid:        {{ $t['mid'] }};
            --depth-1:    {{ $t['depth1'] }};
            --depth-2:    {{ $t['depth2'] }};
            --abyss:      {{ $t['abyss'] }};
            --hud:        {{ $t['hud'] }};
            --hud-rgb:    {{ $rgb($t['hud']) }};
            --hud-bright: {{ $t['hudBright'] }};
            --hud-dark:   {{ $t['hudDark'] }};
            --panel-rgb:  {{ $rgb($t['panel']) }};
            --text:       {{ $t['text'] }};
            --text-muted: {{ $t['muted'] }};
        }
        @endforeach
        :root {
            /* Semantic colours stay fixed across skins: red = danger,
               gold = betting/deep, green = cashout/win. */
            --warn:    #ef4444;
            --gold:    #fbbf24;
            --good:    #22c55e;
        }

        @includeIf("crash.scenes.{$scene}.styles-skins")
        * { box-sizing: border-box; }
        html, body {
            margin: 0; padding: 0; height: 100%;
            font-family: ui-monospace, "SF Mono", Menlo, monospace;
            color: var(--text);
            overflow: hidden;
            user-select: none;
        }

        /* ---------- Stage = container only, never filtered ---------- */
        .stage { position: fixed; inset: 0; background: var(--abyss); }

        /* All depth darkening lives on this layer so the HUD/multiplier
           stay readable. */
        .scene {
            position: absolute; inset: 0;
            background:
              radial-gradient(ellipse at 50% -10%, rgba(255,255,255,0.18) 0%, transparent 35%),
              linear-gradient(180deg,
                  var(--shallow) 0%,
                  var(--mid) 18%,
                  var(--depth-1) 45%,
                  var(--depth-2) 75%,
                  var(--abyss) 100%);
            transition: filter 1s ease, background 2s ease;
            z-index: 1;
        }
        .stage.deep    .scene { filter: brightness(0.7) saturate(1.1); }
        .stage.abyss   .scene { filter: brightness(0.5) saturate(0.85) contrast(1.1); }
        .stage.critical .scene { filter: brightness(0.45) saturate(0.75) contrast(1.25) hue-rotate(-15deg); }

        @includeIf("crash.scenes.{$scene}.styles-scene")
        /* ---------- HUD ---------- */
        .hud-top {
            position: absolute; top: 0; left: 0; right: 0;
            display: flex; justify-content: space-between; align-items: flex-start;
            padding: 16px 20px;
            pointer-events: none;
            z-index: 10;
        }

        @includeIf("crash.scenes.{$scene}.styles-hud")
        /* ---------- Recent crashes history ---------- */
        /* The rail stays clear of the buttons at the top right (--ctl-reserve: their
           row's width plus margins, measured in the script): centred with that much room
           on both sides. (On phones the buttons stack down the edge, so it's narrow.) */
        :root { --ctl-reserve: 146px; }
        @media (max-width: 640px) { :root { --ctl-reserve: 46px; } }
        .history-wrap {
            position: absolute;
            top: 18px; left: 50%;
            transform: translateX(-50%);
            display: flex; flex-direction: column; align-items: center;
            gap: 4px;
            z-index: 9;
            max-width: calc(100vw - 2 * var(--ctl-reserve));
        }
        .history-wrap .history { max-width: 100%; }
        @media (max-width: 640px) {
            /* The status pill under it on one line. */
            .history-wrap .state {
                width: max-content;
                gap: 9px; padding: 7px 16px 7px 13px; font-size: 12px; letter-spacing: 0.16em;
            }
        }
        .history-label {
            font-size: 10px; letter-spacing: 0.32em;
            color: var(--hud); opacity: 0.7;
            text-transform: uppercase;
            text-shadow: 0 0 8px rgba(var(--hud-rgb),0.3), 0 1px 3px rgba(0,0,0,0.45);
        }
        .history {
            display: flex; gap: 7px;
            padding: 8px 14px;
            background: rgba(var(--panel-rgb),0.55);
            border: 1px solid rgba(var(--hud-rgb),0.25);
            border-radius: 999px;
            backdrop-filter: blur(6px);
            overflow: hidden;
        }
        .history-chip {
            font-size: 14px; font-weight: 700;
            padding: 5px 12px;
            border-radius: 999px;
            white-space: nowrap;
            letter-spacing: 0.04em;
            transition: transform 0.3s ease;
        }
        /* A chip opens its round's details. */
        button.history-chip {
            border: 0; font-family: inherit; line-height: inherit; cursor: pointer;
        }
        button.history-chip:hover { filter: brightness(1.25); }
        button.history-chip:focus-visible { outline: 2px solid var(--hud); outline-offset: 2px; }
        /* The same tier colours mark multipliers in the round details and My bets. */
        :is(.history-chip, .x-chip).tier-low  { background: rgba(239,68,68,0.20);  color: #fecaca; }
        :is(.history-chip, .x-chip).tier-mid  { background: rgba(251,191,36,0.20); color: #fef3c7; }
        :is(.history-chip, .x-chip).tier-high { background: rgba(34,197,94,0.20);  color: #bbf7d0; }
        :is(.history-chip, .x-chip).tier-epic {
            background: rgba(168,85,247,0.30);
            color: #f3e8ff;
            box-shadow: 0 0 12px rgba(168,85,247,0.45);
        }
        .history-chip.is-new { animation: chip-pop 0.5s cubic-bezier(0.34, 1.56, 0.64, 1); }
        @keyframes chip-pop {
            0%   { transform: scale(0); opacity: 0; }
            60%  { transform: scale(1.15); opacity: 1; }
            100% { transform: scale(1); }
        }
        @media (max-width: 500px) {
            .history-wrap { top: 14px; gap: 3px; }
            .history-label { font-size: 9px; letter-spacing: 0.24em; }
            .history {
                gap: 4px; padding: 5px 8px;
                /* Cleaner than `overflow: hidden` here — flexbox will shrink
                   each chip down rather than mid-cropping the rightmost one. */
                flex-wrap: nowrap;
            }
            .history-chip {
                font-size: 11px; padding: 3px 6px;
                flex: 0 0 auto;
            }
            /* Show only the most recent few — anything past the 6th gets
               clipped no matter how small the chip is, and a half-chip
               looks broken. pushHistory() uses unshift, so DOM order is
               newest→oldest; nth-child(n+7) hides the oldest. */
            .history-chip:nth-child(n+7) { display: none; }
        }

        /* ---------- Multiplier (sits low so the submarine commands the centre) ---------- */
        .center {
            position: absolute;
            left: 0; right: 0;
            bottom: 70px;
            display: flex; flex-direction: column;
            align-items: center; gap: 6px;
            pointer-events: none;
            z-index: 8;
        }
        /* In embed mode the operator HUD already eats 52px at the bottom — keep
           a steady ~16px gap above it so the multiplier doesn't crowd the bar. */
        body.embedded .center { bottom: calc(16px + var(--op-bar-h)); }

        /* Row that puts pressure | multiplier | hull on the same baseline. */
        .center-row {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: clamp(18px, 3.5vw, 44px);
            width: 100%;
        }
        @media (max-width: 720px) {
            .center-row { gap: 14px; }
            .center-row .gauge { padding: 6px 9px; }
            .center-row .gauge { gap: 8px; }
            .center-row .gauge svg { width: 32px; height: 32px; }
            .center-row .gauge-text { min-width: 60px; }
            .center-row .gauge-value { font-size: 16px; }
            .center-row .hull-bar { width: 90px; }
        }
        @media (max-width: 480px) {
            .center-row .gauge { padding: 4px 7px; }
            .center-row .gauge-label { font-size: 9px; }
            .center-row .hull-bar { width: 56px; }
            .center-row .gauge svg, .gauge-label-long { display: none; }
            .center-row .gauge-text { min-width: 0; }
            .center-row .gauge-label { letter-spacing: 0.12em; }
            .center-row .gauge-value { font-size: 15px; }
        }
        .multiplier {
            font-family: "SF Mono", ui-monospace, monospace;
            font-size: clamp(72px, 14vw, 160px);
            font-weight: 700; line-height: 0.95;
            letter-spacing: -0.04em;
            background: linear-gradient(180deg, var(--hud-bright) 0%, var(--hud) 50%, var(--hud-dark) 100%);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            text-shadow: 0 0 40px rgba(var(--hud-rgb),0.4);
            transition: text-shadow 0.5s ease, transform 0.2s ease;
        }
        .deep .multiplier {
            background: linear-gradient(180deg, #fef3c7 0%, var(--gold) 50%, #b45309 100%);
            -webkit-background-clip: text; background-clip: text;
            text-shadow: 0 0 50px rgba(251,191,36,0.55);
        }
        .critical .multiplier {
            background: linear-gradient(180deg, #fecaca 0%, var(--warn) 50%, #7f1d1d 100%);
            -webkit-background-clip: text; background-clip: text;
            text-shadow: 0 0 70px rgba(239,68,68,0.85);
            animation: pulse 0.6s ease-in-out infinite;
        }
        .crashed .multiplier {
            color: var(--warn) !important;
            background: none !important; -webkit-background-clip: initial; background-clip: initial;
            text-shadow: 0 0 60px var(--warn);
            animation: shatter 0.6s forwards;
        }
        @keyframes pulse { 50% { transform: scale(1.04); } }
        @keyframes shatter {
            0%   { transform: scale(1); opacity: 1; }
            30%  { transform: scale(1.15); }
            100% { transform: scale(0.8); opacity: 0.45; }
        }
        /* Round status pill; the dot follows the stage (gold betting, live green, red crash). */
        .state {
            --dot: var(--hud);
            display: inline-flex; align-items: center; gap: 11px;
            margin: 8px 0 0; padding: 8px 20px 8px 15px;
            font-size: 14px; font-weight: 700; letter-spacing: 0.24em;
            font-variant-numeric: tabular-nums;
            color: var(--hud-bright);
            text-transform: uppercase;
            background: rgba(var(--panel-rgb),0.6);
            border: 1px solid rgba(var(--hud-rgb),0.25);
            border-radius: 999px;
            box-shadow: 0 4px 14px rgba(0,0,0,0.35);
            text-shadow: 0 1px 2px rgba(0,0,0,0.6);
        }
        .state::before {
            content: ""; flex: none; width: 10px; height: 10px; border-radius: 50%;
            background: var(--dot); box-shadow: 0 0 8px var(--dot);
        }
        .betting .state { --dot: var(--gold); }
        .running .state { --dot: var(--good); }
        .running .state::before { animation: state-live 1s ease-in-out infinite; }
        .critical .state, .crashed .state { --dot: var(--warn); border-color: rgba(239,68,68,0.45); }
        @keyframes state-live { 50% { opacity: 0.35; transform: scale(0.7); } }

        /* ---------- Warnings (only critical) ----------
           Right under the status pill, in the top HUD: clear of the sub, which
           sits high on short screens to stay clear of the multiplier. */
        .warnings {
            display: flex; flex-wrap: wrap; justify-content: center; gap: 8px;
            margin-top: 4px; max-width: calc(100vw - 32px);
            pointer-events: none;
        }
        .warnings > div {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 6px 14px 6px 8px;
            font-size: 11px; font-weight: 800; letter-spacing: 0.18em; text-transform: uppercase;
            color: #fecaca;
            background: linear-gradient(180deg, rgba(127,29,29,0.75), rgba(69,10,10,0.8));
            border: 1px solid rgba(239,68,68,0.7);
            border-radius: 6px;
            box-shadow: 0 0 18px rgba(239,68,68,0.35), inset 0 1px 0 rgba(255,255,255,0.12);
            opacity: 0; transform: translateY(-8px);
            transition: opacity 0.25s ease, transform 0.25s ease;
        }
        .warnings i {
            display: grid; place-items: center; width: 18px; height: 18px;
            font-style: normal; font-size: 12px; font-weight: 900; color: #1a0505;
            background: var(--warn); border-radius: 4px;
            animation: warn-blink 0.6s steps(1) infinite;
        }
        .critical:not(.crashed) .warnings > div { opacity: 1; transform: none; }
        .critical:not(.crashed) .warnings > div + div { transition-delay: 0.12s; }
        @keyframes warn-blink { 50% { background: #fecaca; } }

        /* ---------- Cash out ---------- */
        .cashout-wrap {
            position: absolute; bottom: 32px; left: 0; right: 0;
            display: flex; justify-content: center;
            z-index: 12;
        }
        .cashout {
            padding: 18px 56px;
            font-family: inherit; font-size: 16px; font-weight: 700;
            letter-spacing: 0.25em; text-transform: uppercase;
            color: #022c22; background: var(--good);
            border: none; border-radius: 999px;
            cursor: pointer;
            box-shadow: 0 0 30px rgba(34,197,94,0.7), inset 0 -3px 0 rgba(0,0,0,0.18);
            transition: transform 0.15s ease, box-shadow 0.3s ease, opacity 0.3s ease;
        }
        .cashout:hover { transform: translateY(-2px); box-shadow: 0 0 50px rgba(34,197,94,0.9); }
        .cashout:active { transform: translateY(1px); }
        .cashout:disabled { opacity: 0.35; cursor: not-allowed; box-shadow: none; }
        .cashout small {
            display: block; font-size: 9px; letter-spacing: 0.2em;
            opacity: 0.7; margin-top: 4px; font-weight: 500;
        }

        /* ---------- Top-right menu ----------
           One button (☰) opens the game's menu: Home (in a lobby), All bets, My bets,
           sound, fullscreen and the theme. The recent-crashes rail keeps clear of the
           button (--ctl-reserve, measured in the script). */
        .top-controls {
            position: fixed;
            top: 14px; right: 14px;
            z-index: 40;
            display: flex;
        }
        .ctl-btn {
            width: 34px; height: 34px;
            display: inline-flex; align-items: center; justify-content: center;
            background: rgba(var(--panel-rgb),0.7);
            border: 1px solid rgba(var(--hud-rgb),0.32);
            border-radius: 6px;
            color: var(--hud);
            cursor: pointer;
            backdrop-filter: blur(6px);
            box-shadow: 0 0 10px rgba(var(--hud-rgb),0.10);
            transition: box-shadow 0.18s, transform 0.12s, background 0.18s, color 0.18s;
        }
        .ctl-btn:hover,
        .ctl-btn[aria-expanded="true"] {
            background: rgba(var(--panel-rgb),0.85);
            box-shadow: 0 0 18px rgba(var(--hud-rgb),0.45);
            color: var(--hud-bright);
        }
        .ctl-btn:active { transform: translateY(1px); }
        .ctl-btn svg { width: 16px; height: 16px; display: block; pointer-events: none; }

        .game-menu {
            position: fixed;
            top: 54px; right: 14px;
            z-index: 41;
            display: flex; flex-direction: column; align-items: center; gap: 4px;
            max-height: calc(100% - 68px);
            overflow-y: auto;
            padding: 5px;
            background: rgba(var(--panel-rgb),0.92);
            border: 1px solid rgba(var(--hud-rgb),0.32);
            border-radius: 8px;
            backdrop-filter: blur(8px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.45), 0 0 18px rgba(var(--hud-rgb),0.12);
            transform-origin: 100% 0;
            animation: theme-menu-in 0.16s ease-out;
        }
        .game-menu[hidden] { display: none; }
        @keyframes theme-menu-in {
            from { opacity: 0; transform: translateY(-4px) scale(0.97); }
            to   { opacity: 1; transform: none; }
        }
        /* Icons only: the names are tooltips (title) and labels for screen readers. */
        .menu-item,
        .theme-opt {
            position: relative;
            flex: 0 0 auto;
            width: 36px; height: 36px;
            display: inline-flex; align-items: center; justify-content: center;
            padding: 0;
            background: transparent;
            border: 1px solid transparent;
            border-radius: 6px;
            color: var(--hud);
            cursor: pointer;
            transition: background 0.15s, border-color 0.15s, color 0.15s;
        }
        .menu-item:hover, .theme-opt:hover { background: rgba(var(--hud-rgb),0.14); color: var(--hud-bright); }
        .menu-item:focus-visible, .theme-opt:focus-visible { outline: 2px solid var(--hud); outline-offset: -2px; }
        .menu-item svg { width: 18px; height: 18px; pointer-events: none; }
        .menu-label {
            position: absolute; width: 1px; height: 1px; overflow: hidden;
            clip: rect(0 0 0 0); clip-path: inset(50%); white-space: nowrap;
        }
        .menu-sep { flex: 0 0 auto; width: 24px; height: 1px; margin: 2px 0; background: rgba(var(--hud-rgb),0.25); }

        /* All bets: lit while its panel is open. */
        .live-btn[aria-checked="true"] { background: rgba(var(--hud-rgb),0.18); border-color: rgba(var(--hud-rgb),0.5); }

        /* My bets: only when the page hosting the game answers bet_history (Orange's
           host pages say so in player_info). */
        .bets-btn { display: none; }
        .has-bets .bets-btn { display: inline-flex; }

        /* Sound */
        .audio-btn .audio-icon-mute { display: none; }
        .audio-btn.is-muted .audio-icon-on { display: none; }
        .audio-btn.is-muted .audio-icon-mute { display: block; }
        .audio-btn.is-muted { color: var(--text-muted); }

        /* Fullscreen. None to be had (the page on its own on an iPhone): the row goes. */
        .fs-btn .fs-icon-exit, .fs-btn .fs-label-exit { display: none; }
        .no-fullscreen .fs-btn { display: none; }
        :fullscreen .fs-btn :is(.fs-icon-enter, .fs-label-enter),
        body.is-fullscreen .fs-btn :is(.fs-icon-enter, .fs-label-enter) { display: none; }
        :fullscreen .fs-btn .fs-icon-exit,
        body.is-fullscreen .fs-btn .fs-icon-exit { display: block; }
        :fullscreen .fs-btn .fs-label-exit,
        body.is-fullscreen .fs-btn .fs-label-exit { display: block; }

        /* Theme: a swatch per skin; the one in use is ringed. */
        .theme-opt.is-active {
            border-color: rgba(var(--hud-rgb),0.6);
            background: rgba(var(--hud-rgb),0.12);
        }
        /* Swatch previews the skin's own colours (set inline per option) so
           every row looks right no matter which skin is active. */
        .theme-swatch {
            flex: 0 0 auto;
            width: 18px; height: 18px;
            border-radius: 50%;
            background: linear-gradient(160deg, var(--sw-top) 0%, var(--sw-deep) 100%);
            box-shadow: inset 0 0 0 2px var(--sw-hud), 0 0 6px var(--sw-hud);
        }
        /* Phones: the button a little smaller and closer to the corner. */
        @media (max-width: 640px) {
            .top-controls { top: 12px; right: 10px; }
            .ctl-btn { width: 30px; height: 30px; }
            .game-menu { top: 48px; right: 10px; max-height: calc(100% - 60px); }
        }

        /* ---------- All bets: every player's bet in the round, live ----------
           Docked at the right on wide screens, between the buttons and the multiplier
           row (--hud-room); elsewhere it opens over the game from its button. */
        .live-bets {
            position: fixed; z-index: 35;
            top: 96px; right: 14px;
            bottom: calc(var(--op-bar-h, 0px) + 14px);
            width: min(300px, calc(100vw - 28px));
            display: flex; flex-direction: column;
            background: rgba(var(--panel-rgb),0.82);
            border: 1px solid rgba(var(--hud-rgb),0.28);
            border-radius: 12px;
            backdrop-filter: blur(8px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.4);
            font-size: 12px;
            overflow: hidden;
        }
        .live-bets[hidden] { display: none; }
        @media (min-width: 1100px) and (min-height: 600px) {
            .live-bets { bottom: calc(var(--hud-room, 220px) + 12px); }
        }
        @media (max-width: 640px) {
            .live-bets { top: 120px; right: 10px; left: 10px; width: auto; bottom: calc(var(--op-bar-h, 0px) + 10px); }
        }
        .lb-top { display: flex; align-items: center; gap: 4px; padding: 8px 6px 0 8px; }
        .lb-tabs {
            flex: 1;
            display: grid; grid-template-columns: 1fr 1fr;
            padding: 3px;
            background: rgba(0,0,0,0.3);
            border-radius: 999px;
        }
        .lb-tab {
            padding: 6px 8px;
            background: transparent; border: 0; border-radius: 999px;
            color: var(--text-muted);
            font: inherit; font-size: 11px; font-weight: 700; letter-spacing: 0.06em;
            cursor: pointer;
        }
        .lb-tab[aria-selected="true"] { background: rgba(var(--hud-rgb),0.2); color: var(--hud-bright); }
        .lb-tab:focus-visible { outline: 2px solid var(--hud); outline-offset: -2px; }
        .lb-summary {
            display: flex; align-items: flex-end; justify-content: space-between; gap: 8px;
            padding: 10px 12px 7px;
        }
        .lb-summary b { display: block; font-size: 15px; color: var(--hud-bright); white-space: nowrap; }
        .lb-summary small { font-size: 10px; letter-spacing: 0.06em; color: var(--text-muted); white-space: nowrap; }
        .lb-result { display: flex; flex-direction: column; align-items: center; gap: 3px; }
        .lb-result[hidden] { display: none; }
        .lb-total { min-width: 0; text-align: right; }
        .lb-total b { overflow: hidden; text-overflow: ellipsis; }
        .lb-progress { height: 4px; margin: 0 12px 8px; background: rgba(0,0,0,0.35); border-radius: 999px; overflow: hidden; }
        .lb-progress i { display: block; width: 0; height: 100%; background: var(--good); transition: width 0.3s ease; }
        .lb-row {
            display: grid; grid-template-columns: minmax(0, 1.3fr) minmax(0, 1fr) minmax(0, 0.8fr) minmax(0, 1fr);
            align-items: center; gap: 6px;
            padding: 5px 10px;
        }
        .lb-row > :not(:first-child) { text-align: right; white-space: nowrap; }
        .lb-head { padding: 0 18px 4px; font-size: 10px; color: var(--text-muted); }
        .lb-rows {
            flex: 1;
            display: flex; flex-direction: column; gap: 3px;
            padding: 0 8px 8px;
            overflow-y: auto; overscroll-behavior: contain;
        }
        .lb-rows .lb-row {
            min-height: 30px;
            background: rgba(0,0,0,0.22);
            border: 1px solid transparent; border-radius: 8px;
        }
        .lb-rows .lb-row.is-won { background: rgba(34,197,94,0.14); border-color: rgba(34,197,94,0.3); }
        .lb-rows .lb-row.is-lost { opacity: 0.5; }
        .lb-rows .lb-row.is-mine { border-color: rgba(var(--hud-rgb),0.75); }
        .lb-rows .lb-row.flash { animation: lb-flash 0.8s ease-out; }
        @keyframes lb-flash { from { background: rgba(34,197,94,0.5); } }
        .lb-player { display: flex; align-items: center; gap: 6px; }
        .lb-player i {
            flex: 0 0 auto;
            width: 18px; height: 18px;
            display: inline-flex; align-items: center; justify-content: center;
            border-radius: 50%;
            background: hsl(var(--h, 200) 55% 42%);
            color: #fff; font-size: 10px; font-style: normal; font-weight: 700;
        }
        .lb-player span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .lb-x .x-chip { padding: 2px 6px; font-size: 11px; }
        .lb-win { color: var(--good); font-weight: 700; }
        .lb-note { padding: 14px 8px; text-align: center; font-size: 11px; color: var(--text-muted); }

        /* Where the panel isn't docked: the round's bets as circles down the right edge,
           below the buttons and above the multiplier row. A circle that cashes out steps
           inward as its multiplier grows in beside it; those that didn't grey out at the
           crash. --y is each circle's place, so a reorder glides. */
        .lb-mini {
            position: fixed; z-index: 34;
            top: 64px; right: 11px;
            bottom: calc(var(--hud-room, 220px) + 10px);
            width: 28px;
            pointer-events: none;
        }
        .lb-mini[hidden] { display: none; }
        .lb-dot {
            position: absolute; top: 0; right: 0;
            display: flex; align-items: center; gap: 4px;
            height: 28px;
            transform: translateY(var(--y, 0px));
            transition: transform 0.45s cubic-bezier(.2,.8,.2,1), opacity 0.4s ease, filter 0.4s ease;
            pointer-events: auto;
            cursor: pointer;
        }
        .lb-dot i {
            flex: 0 0 auto;
            width: 28px; height: 28px;
            display: inline-flex; align-items: center; justify-content: center;
            border-radius: 50%;
            background: hsl(var(--h, 200) 55% 42%);
            box-shadow: 0 0 0 2px rgba(255,255,255,0.75), 0 2px 6px rgba(0,0,0,0.45);
            color: #fff; font-size: 12px; font-style: normal; font-weight: 700;
            transition: box-shadow 0.3s ease;
        }
        .lb-dot b {
            max-width: 0; height: 18px; padding: 0;
            overflow: hidden; opacity: 0;
            background: rgba(22,163,74,0.92);
            border-radius: 999px;
            color: #fff; font-size: 10.5px; line-height: 18px; font-weight: 700; white-space: nowrap;
            box-shadow: 0 2px 6px rgba(0,0,0,0.4);
            transition: max-width 0.45s cubic-bezier(.2,.8,.2,1), padding 0.45s cubic-bezier(.2,.8,.2,1), opacity 0.3s ease;
        }
        .lb-dot.is-out b { max-width: 60px; padding: 0 6px; opacity: 1; }
        .lb-dot.is-out i { box-shadow: 0 0 0 2px var(--good), 0 0 12px rgba(34,197,94,0.7); }
        .lb-dot.is-mine i { box-shadow: 0 0 0 2px var(--hud), 0 0 12px rgba(var(--hud-rgb),0.6); }
        .lb-dot.is-lost { opacity: 0.4; filter: grayscale(1); }
        .lb-dot.is-new { animation: lb-dot-in 0.4s cubic-bezier(.34,1.56,.64,1); }
        .lb-dot.is-gone { opacity: 0; transform: translateY(var(--y, 0px)) scale(0.5); }
        @keyframes lb-dot-in { from { opacity: 0; transform: translateY(var(--y, 0px)) scale(0); } }
        .lb-mini-more i { background: rgba(var(--panel-rgb),0.85); color: var(--hud-bright); font-size: 10px; }
        .lb-mini-more[hidden] { display: none; }

        /* ---------- Round details and My bets: one dialog over the game ---------- */
        .sheet {
            position: fixed; inset: 0; z-index: 60;
            display: flex; align-items: center; justify-content: center;
            padding: 16px;
            background: rgba(0,0,0,0.55);
            animation: sheet-fade 0.16s ease-out;
        }
        .sheet[hidden] { display: none; }
        @keyframes sheet-fade { from { opacity: 0; } }
        .sheet-card {
            display: flex; flex-direction: column;
            width: min(600px, 100%);
            max-height: 100%;
            background: rgba(var(--panel-rgb),0.97);
            border: 1px solid rgba(var(--hud-rgb),0.32);
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.6), 0 0 24px rgba(var(--hud-rgb),0.12);
            overflow: hidden;
            animation: theme-menu-in 0.18s ease-out;
        }
        .sheet-head {
            display: flex; align-items: center; gap: 10px;
            min-height: 52px;
            padding: 9px 10px 9px 16px;
            border-bottom: 1px solid rgba(var(--hud-rgb),0.18);
        }
        .sheet-head .sheet-back:not([hidden]) + .sheet-title { margin-left: -6px; }
        .sheet-title {
            margin: 0;
            font-size: 14px; font-weight: 700; letter-spacing: 0.14em; text-transform: uppercase;
            color: var(--hud-bright);
            white-space: nowrap;
        }
        .sheet-time { font-size: 12px; color: var(--text-muted); white-space: nowrap; }
        .sheet-btn {
            flex: 0 0 auto;
            width: 32px; height: 32px;
            display: inline-flex; align-items: center; justify-content: center;
            background: transparent; border: 0; border-radius: 6px;
            color: var(--text-muted);
            cursor: pointer;
        }
        .sheet-btn:hover { color: var(--hud-bright); background: rgba(var(--hud-rgb),0.12); }
        .sheet-btn:focus-visible { outline: 2px solid var(--hud); outline-offset: -2px; }
        .sheet-btn svg { width: 16px; height: 16px; display: block; pointer-events: none; }
        .sheet-back { margin-left: -8px; }
        .sheet-close { margin-left: auto; }
        .sheet-body {
            padding: 16px;
            overflow-y: auto; overscroll-behavior: contain;
            font-size: 13px;
        }
        .sheet-note { margin: 18px 0; text-align: center; color: var(--text-muted); }
        .sheet-note .sheet-retry {
            margin-left: 8px; padding: 4px 10px;
            background: transparent; border: 1px solid rgba(var(--hud-rgb),0.4); border-radius: 6px;
            color: var(--hud); font: inherit; cursor: pointer;
        }
        .x-chip {
            display: inline-block;
            padding: 3px 9px;
            border-radius: 999px;
            font-size: 12px; font-weight: 700; letter-spacing: 0.04em;
            white-space: nowrap;
        }

        /* Round details: each input to the result, then the result worked out from them. */
        .pf-section {
            display: grid; grid-template-columns: 26px 1fr; align-items: center;
            column-gap: 10px;
            margin-bottom: 16px;
        }
        .pf-section > svg { grid-row: span 2; width: 24px; height: 24px; color: var(--hud); }
        .pf-label { font-weight: 700; }
        .pf-sub { font-size: 11px; color: var(--text-muted); }
        .pf-value {
            grid-column: 1 / -1;
            margin-top: 8px;
            padding: 10px 12px;
            background: rgba(0,0,0,0.3);
            border: 1px solid rgba(var(--hud-rgb),0.12);
            border-radius: 8px;
            font-size: 12px; line-height: 1.5;
            overflow-wrap: anywhere;
            user-select: text;
        }
        .pf-value.is-pending { color: var(--text-muted); font-style: italic; user-select: none; }
        .pf-value mark { background: rgba(var(--hud-rgb),0.25); color: var(--hud-bright); border-radius: 3px; }
        .pf-calc {
            /* The decimal is 16 digits: it gets the room. */
            display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1.35fr) minmax(0, 0.75fr);
            margin: 0; padding: 10px 12px;
            background: rgba(0,0,0,0.3);
            border: 1px solid rgba(var(--hud-rgb),0.12);
            border-radius: 8px;
            text-align: center;
        }
        .pf-calc dt { font-size: 11px; color: var(--text-muted); }
        .pf-calc dd { margin: 5px 0 0; font-weight: 700; overflow-wrap: anywhere; user-select: text; }
        .pf-calc > div + div { border-left: 1px solid rgba(var(--hud-rgb),0.12); padding-left: 8px; }
        .pf-edge { margin: 10px 2px 0; font-size: 11px; color: var(--text-muted); }
        .pf-check { margin: 12px 2px 0; font-size: 12px; }
        .pf-check.ok { color: var(--good); }
        .pf-check.bad { color: var(--warn); }
        .pf-how {
            margin-top: 16px; padding-top: 12px;
            border-top: 1px solid rgba(var(--hud-rgb),0.18);
            font-size: 12px; color: var(--text-muted);
        }
        .pf-how summary { cursor: pointer; color: var(--hud); }
        .pf-how summary:focus-visible { outline: 2px solid var(--hud); outline-offset: 2px; }
        .pf-how ol { margin: 10px 0 2px; padding-left: 18px; line-height: 1.6; }
        .pf-how li + li { margin-top: 4px; }

        /* My bets: one row a bet, newest first; a row opens its round. */
        .bets-list { display: flex; flex-direction: column; gap: 4px; }
        .bets-row {
            display: grid; grid-template-columns: 1.25fr 1fr 1fr 1fr 14px;
            align-items: center; gap: 8px;
            padding: 9px 10px;
            font-size: 12.5px;
            text-align: right;
        }
        .bets-row > :first-child { text-align: left; }
        .bets-row.is-head { padding-top: 0; padding-bottom: 4px; font-size: 11px; color: var(--text-muted); }
        button.bets-row {
            width: 100%;
            background: rgba(0,0,0,0.22);
            border: 1px solid transparent; border-radius: 8px;
            color: var(--text); font-family: inherit;
            cursor: pointer;
        }
        button.bets-row:hover { border-color: rgba(var(--hud-rgb),0.35); background: rgba(var(--hud-rgb),0.08); }
        button.bets-row:focus-visible { outline: 2px solid var(--hud); outline-offset: -2px; }
        button.bets-row:disabled { cursor: default; }
        .bets-row.is-won { background: rgba(34,197,94,0.10); }
        .bets-row .bets-when small { display: block; font-size: 10px; color: var(--text-muted); }
        .bets-row .is-muted { color: var(--text-muted); }
        .bets-row .is-win { color: var(--good); font-weight: 700; }
        .bets-row svg { width: 12px; height: 12px; color: var(--text-muted); }

        /* Auto bet: what it repeats, for how many rounds, and what that comes to. */
        .sheet-card[data-view="autobet"] { width: min(440px, 100%); }
        .ab-what {
            display: grid; grid-template-columns: 1fr 1fr; gap: 8px;
            margin: 0 0 16px;
        }
        .ab-what > div {
            padding: 10px 12px;
            background: rgba(0,0,0,0.3);
            border: 1px solid rgba(var(--hud-rgb),0.12);
            border-radius: 8px;
        }
        .ab-what dt { font-size: 11px; color: var(--text-muted); }
        .ab-what dd { margin: 4px 0 0; font-size: 15px; font-weight: 700; }
        .ab-what dd.is-off { color: var(--text-muted); font-weight: 600; font-size: 13px; }
        .ab-label { display: block; margin: 0 0 8px; font-size: 12px; font-weight: 700; color: var(--hud-bright); }
        .ab-rounds { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 6px; }
        .ab-chip, .ab-custom {
            height: 38px; min-width: 0;
            background: rgba(0,0,0,0.25);
            border: 1px solid rgba(var(--hud-rgb),0.3); border-radius: 8px;
            color: var(--text);
            font: inherit; font-size: 14px; font-weight: 700;
            text-align: center;
        }
        .ab-chip { cursor: pointer; }
        .ab-chip:hover { border-color: var(--hud); }
        .ab-chip[aria-pressed="true"] { background: rgba(var(--hud-rgb),0.22); border-color: var(--hud); color: var(--hud-bright); }
        .ab-custom { padding: 0 6px; }
        .ab-custom::placeholder { color: var(--text-muted); font-weight: 600; font-size: 12px; }
        .ab-custom:focus, .ab-chip:focus-visible { outline: none; border-color: var(--hud); box-shadow: 0 0 0 1px var(--hud); }
        .ab-total {
            display: flex; align-items: baseline; justify-content: space-between; gap: 10px;
            margin: 16px 0 0; padding: 12px 14px;
            background: rgba(251,191,36,0.08);
            border: 1px solid rgba(251,191,36,0.35); border-radius: 8px;
        }
        .ab-total span { font-size: 12px; color: var(--text-muted); }
        .ab-total b { font-size: 20px; color: var(--gold); font-variant-numeric: tabular-nums; white-space: nowrap; }
        .ab-note { margin: 10px 2px 0; font-size: 12px; color: var(--text-muted); line-height: 1.5; }
        .ab-note.is-warn { color: #fca5a5; }
        .ab-actions { display: flex; gap: 8px; margin-top: 16px; }
        .ab-actions button {
            flex: 1 1 0; height: 42px;
            border-radius: 8px;
            font: inherit; font-size: 13px; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase;
            cursor: pointer;
        }
        .ab-cancel { background: transparent; border: 1px solid rgba(var(--hud-rgb),0.35); color: var(--text); }
        .ab-start { flex-grow: 1.6; background: var(--gold); border: 1px solid var(--gold); color: #1c1300; }
        .ab-start:disabled { opacity: 0.4; cursor: not-allowed; }
        .ab-actions button:focus-visible { outline: 2px solid var(--hud); outline-offset: 2px; }

        /* Rules */
        .rules h3 {
            margin: 18px 0 6px;
            font-size: 12px; font-weight: 700; letter-spacing: 0.12em; text-transform: uppercase;
            color: var(--hud-bright);
        }
        .rules h3:first-child { margin-top: 0; }
        .rules p { margin: 0 0 6px; line-height: 1.55; }
        .rules ol { margin: 0; padding-left: 20px; line-height: 1.55; list-style: decimal; }
        .rules ul { margin: 0; padding-left: 20px; line-height: 1.55; list-style: disc; }
        .rules li + li { margin-top: 3px; }
        .rules .is-muted { color: var(--text-muted); }
        .rules-grid {
            display: grid; grid-template-columns: repeat(3, minmax(0, 1fr));
            margin: 4px 0 8px; padding: 10px 12px;
            background: rgba(0,0,0,0.3);
            border: 1px solid rgba(var(--hud-rgb),0.12);
            border-radius: 8px;
            text-align: center;
        }
        .rules-grid dt { font-size: 11px; color: var(--text-muted); }
        .rules-grid dd { margin: 5px 0 0; font-weight: 700; overflow-wrap: anywhere; }
        .rules-grid > div + div { border-left: 1px solid rgba(var(--hud-rgb),0.12); }
        @media (max-width: 500px) {
            .sheet { padding: 10px; }
            .sheet-head { padding-left: 12px; gap: 8px; }
            .sheet-title { font-size: 13px; letter-spacing: 0.1em; }
            .sheet-body { padding: 12px; }
            .bets-row { gap: 6px; padding: 8px; font-size: 12px; grid-template-columns: 1.2fr 1fr 1fr 1fr 10px; }
            .pf-calc dd { font-size: 12px; }
        }
        /* ---------- Operator HUD bar (slim — only when /crash is iframe-embedded) ---------- */
        .operator-panel { display: none; }
        body.embedded { --op-bar-h: 52px; }
        body.embedded .operator-panel {
            display: flex;
            align-items: center;
            gap: 12px;
            position: fixed;
            left: 0; right: 0; bottom: 0;
            height: var(--op-bar-h);
            z-index: 30;
            padding: 0 14px;
            background: rgba(var(--panel-rgb),0.88);
            border-top: 1px solid rgba(var(--hud-rgb),0.28);
            box-shadow: 0 -1px 14px rgba(var(--hud-rgb),0.06);
            backdrop-filter: blur(8px);
            color: var(--text);
            font-family: ui-monospace, "SF Mono", Menlo, monospace;
            font-size: 12px;
            white-space: nowrap;
        }
        body.embedded .operator-panel::before {
            content: '';
            position: absolute;
            left: 14px; right: 14px; top: -1px; height: 1px;
            background: linear-gradient(90deg, transparent, var(--hud) 50%, transparent);
            opacity: 0.5;
        }

        /* Player identity (compact inline) */
        body.embedded .op-id {
            display: flex; align-items: center; gap: 8px;
            font-variant-numeric: tabular-nums;
        }
        body.embedded .op-id .op-name {
            font-weight: 700; letter-spacing: 0.04em;
            color: var(--text);
        }
        body.embedded .op-id .op-balance {
            color: var(--hud);
            font-weight: 700;
            text-shadow: 0 0 10px rgba(var(--hud-rgb),0.35);
        }
        body.embedded .op-sep {
            width: 1px; height: 22px;
            background: rgba(var(--hud-rgb),0.18);
            flex-shrink: 0;
        }

        /* Phase chip */
        body.embedded .op-phase {
            display: inline-flex; align-items: center;
            gap: 5px; padding: 2px 8px;
            font-size: 9px; letter-spacing: 0.24em; text-transform: uppercase;
            border: 1px solid currentColor;
            border-radius: 999px;
            color: var(--hud); opacity: 0.85;
        }
        body.embedded .op-phase::before {
            content: ''; width: 5px; height: 5px; border-radius: 50%;
            background: currentColor;
            box-shadow: 0 0 5px currentColor;
        }
        body.embedded .op-phase[data-phase="betting"] { color: var(--gold); }
        body.embedded .op-phase[data-phase="running"] { color: var(--good); }
        body.embedded .op-phase[data-phase="crashed"] { color: var(--warn); }

        /* Bet controls — inline, tight. The fields' captions stand above the line, so
           the row lines its controls up by their bottoms (each as tall as the others). */
        body.embedded .op-bet-row {
            display: flex; align-items: flex-end; gap: 6px;
        }
        body.embedded .op-input {
            background: rgba(var(--panel-rgb),0.6);
            color: var(--text);
            border: 1px solid rgba(var(--hud-rgb),0.28);
            border-radius: 4px;
            padding: 5px 8px;
            font-family: inherit;
            font-size: 12px; font-weight: 700;
            font-variant-numeric: tabular-nums;
            transition: border-color 0.15s, box-shadow 0.15s;
        }
        body.embedded .op-input[data-kind="stake"] { width: 64px; text-align: center; }
        /* The − / + beside each field step it; the browser's own spinners would only eat its room. */
        body.embedded .op-input { appearance: textfield; -moz-appearance: textfield; }
        body.embedded .op-input::-webkit-inner-spin-button,
        body.embedded .op-input::-webkit-outer-spin-button { -webkit-appearance: none; margin: 0; }
        body.embedded .op-input[data-kind="auto"]  { width: 60px; }

        /* Stake stepper (− input +) */
        body.embedded .op-stepper {
            display: inline-flex; align-items: stretch;
            border: 1px solid rgba(var(--hud-rgb),0.28);
            border-radius: 4px;
            overflow: hidden;
            transition: border-color 0.15s, box-shadow 0.15s;
        }
        body.embedded .op-stepper:focus-within {
            border-color: var(--hud);
            box-shadow: 0 0 0 1px var(--hud);
        }
        body.embedded .op-stepper .op-input {
            border: 0; border-radius: 0;
            background: rgba(var(--panel-rgb),0.6);
        }
        body.embedded .op-stepper .op-input:focus { box-shadow: none; }
        body.embedded .op-step {
            background: rgba(var(--panel-rgb),0.6);
            color: var(--hud);
            border: 0;
            padding: 0 10px;
            font-family: inherit; font-weight: 700; font-size: 14px;
            line-height: 1;
            cursor: pointer;
            transition: background 0.15s, color 0.15s;
            user-select: none;
        }
        body.embedded .op-step:hover  { background: rgba(var(--hud-rgb),0.18); color: var(--hud-bright); }
        body.embedded .op-step:active { background: rgba(var(--hud-rgb),0.30); }

        body.embedded .op-stepper-auto .op-input { width: 56px; text-align: center; }
        body.embedded .op-input:focus {
            outline: none;
            border-color: var(--hud);
            box-shadow: 0 0 0 1px var(--hud);
        }

        body.embedded .op-btn {
            position: relative;
            display: inline-flex; align-items: center; justify-content: center;
            font-family: inherit; font-weight: 700; font-size: 11px;
            letter-spacing: 0.22em; text-transform: uppercase;
            height: 30px; padding: 0 14px;
            border: 1px solid currentColor;
            border-radius: 4px;
            background: transparent;
            cursor: pointer;
            transition: box-shadow 0.18s, opacity 0.18s;
            overflow: hidden;
        }
        body.embedded .op-btn::before {
            content: ''; position: absolute; inset: 0;
            background: currentColor; opacity: 0.08;
            transition: opacity 0.18s;
        }
        body.embedded .op-btn:hover:not(:disabled)::before { opacity: 0.16; }
        body.embedded .op-btn:disabled {
            opacity: 0.25; cursor: not-allowed; filter: saturate(0.4);
        }
        body.embedded .op-bet-btn {
            color: var(--gold);
            box-shadow: 0 0 10px rgba(251,191,36,0.20);
        }
        body.embedded .op-bet-btn:hover:not(:disabled) {
            box-shadow: 0 0 18px rgba(251,191,36,0.5);
        }
        body.embedded .op-cashout-btn {
            color: var(--good);
            box-shadow: 0 0 12px rgba(34,197,94,0.25);
            min-width: 150px;
            height: 34px; font-size: 12px;
            white-space: nowrap;
            display: inline-flex; align-items: center; justify-content: center;
        }
        body.embedded .op-cashout-btn:hover:not(:disabled) {
            box-shadow: 0 0 22px rgba(34,197,94,0.7);
        }
        body.embedded .op-cashout-btn .op-cashout-x {
            margin-left: 8px;
            font-size: 10px;
            letter-spacing: 0.12em;
            opacity: 0.8;
            font-weight: 600;
        }
        body.embedded .op-cashout-btn.pulse {
            animation: op-cashout-pulse 1.2s ease-in-out infinite;
        }
        @keyframes op-cashout-pulse {
            0%, 100% { box-shadow: 0 0 12px rgba(34,197,94,0.25); }
            50%      { box-shadow: 0 0 28px rgba(34,197,94,0.85); }
        }

        /* AUTO BET toggle — pill that fires a bet on every betting_opened
           when armed. Off state is muted; on state is loud + pulsing so the
           player can't forget money is moving in the background. */
        body.embedded .op-autobet-btn {
            background: transparent;
            border: 1px solid rgba(148,163,184,0.35);
            color: rgba(148,163,184,0.9);
            box-shadow: none;
            font-size: 10px;
            letter-spacing: 0.18em;
            padding: 4px 10px;
            height: 28px;
            display: inline-flex; align-items: center; gap: 6px;
        }
        body.embedded .op-autobet-btn .op-autobet-dot {
            width: 6px; height: 6px;
            border-radius: 50%;
            background: currentColor;
            opacity: 0.35;
        }
        /* Desktop: show full "AUTO BET" label, hide the short variant. */
        body.embedded .op-autobet-btn .op-autobet-label-short { display: none; }
        body.embedded .op-autobet-btn[data-active="true"] {
            color: var(--gold);
            border-color: rgba(251,191,36,0.55);
            background: rgba(251,191,36,0.08);
            box-shadow: 0 0 12px rgba(251,191,36,0.3);
        }
        body.embedded .op-autobet-btn[data-active="true"] .op-autobet-dot {
            opacity: 1;
            box-shadow: 0 0 6px currentColor;
            animation: op-autobet-pulse 1.6s ease-in-out infinite;
        }
        @keyframes op-autobet-pulse {
            0%, 100% { opacity: 1;   transform: scale(1); }
            50%      { opacity: 0.6; transform: scale(1.35); }
        }

        /* Floating "+payout" toast that pops over the multiplier on a
           successful cashout, drifts upward, fades out. Removed after
           the animation ends via JS `animationend`. Lives on .stage so
           it inherits the in-game coordinate space. */
        .fly-win {
            position: absolute;
            left: 50%; top: 38%;
            transform: translate(-50%, 0);
            display: flex; flex-direction: column; align-items: center;
            gap: 4px;
            pointer-events: none;
            z-index: 15;
            font-family: "SF Mono", ui-monospace, monospace;
            font-weight: 700;
            color: var(--good);
            text-shadow: 0 0 32px rgba(34,197,94,0.85), 0 0 10px rgba(34,197,94,1);
            white-space: nowrap;
            will-change: transform, opacity;
            animation: fly-win 1.7s cubic-bezier(0.22, 0.61, 0.36, 1) forwards;
        }
        .fly-win-mult   { font-size: clamp(14px, 3.4vw, 22px); letter-spacing: 0.08em; opacity: 0.85; }
        .fly-win-amount { font-size: clamp(28px, 7vw,   52px); letter-spacing: -0.01em; }
        @keyframes fly-win {
            0%   { opacity: 0; transform: translate(-50%, 30px) scale(0.6); }
            12%  { opacity: 1; transform: translate(-50%, 0)    scale(1.18); }
            25%  { opacity: 1; transform: translate(-50%, -8px) scale(1); }
            100% { opacity: 0; transform: translate(-50%, -180px) scale(0.9); }
        }
        @media (max-width: 719px) {
            .fly-win-mult   { font-size: 14px; }
            .fly-win-amount { font-size: 30px; }
        }

        /* Right-side spacer + last result pill */
        body.embedded .op-spacer { flex: 1; }
        body.embedded .op-last {
            display: none;
            align-items: baseline; gap: 8px;
            padding: 4px 10px;
            background: rgba(34,197,94,0.10);
            border: 1px solid rgba(34,197,94,0.35);
            border-radius: 4px;
            font-variant-numeric: tabular-nums;
        }
        body.embedded .op-last.visible { display: inline-flex; }
        body.embedded .op-last.loss {
            background: rgba(239,68,68,0.10);
            border-color: rgba(239,68,68,0.35);
        }
        body.embedded .op-last-label {
            font-size: 8px; letter-spacing: 0.28em; text-transform: uppercase;
            color: var(--text-muted);
        }
        body.embedded .op-last-mult {
            font-size: 13px; font-weight: 700; color: var(--good);
        }
        body.embedded .op-last.loss .op-last-mult { color: #fca5a5; }
        body.embedded .op-last-payout {
            font-size: 12px; font-weight: 700; color: var(--gold);
        }

        /* Inline status (small, fades from view) */
        body.embedded .op-status {
            font-size: 10px;
            color: var(--text-muted);
            letter-spacing: 0.04em;
            max-width: 220px; overflow: hidden; text-overflow: ellipsis;
        }
        body.embedded .op-status.err { color: #fca5a5; }
        body.embedded .op-status.ok  { color: var(--good); }

        /* When holding a bet during a running round, hide the entire
           stake/auto stepper UI (not just the inputs — empty [− +] boxes
           look broken) and the Place Bet button. Auto-bet toggle stays
           visible so the player can flip it off without waiting for the
           next window. */
        /* Fields keep their space (just hidden) so nothing to their right
           shifts; Place Bet / Cash Out / the cashed-out result swap in place
           inside .op-action. */
        body.embedded .op-slot:is([data-mode="cashout"], [data-mode="cashed"]) .op-field,
        body.embedded .op-slot:is([data-mode="cashout"], [data-mode="cashed"]) .op-bet-btn,
        body.embedded .op-slot:not([data-mode="cashout"]) .op-cashout-btn,
        body.embedded .op-slot:not([data-mode="cashed"]) .op-cashed { visibility: hidden; }
        body.embedded .op-action { display: grid; }
        body.embedded .op-action > * { grid-area: 1 / 1; width: 100%; }
        body.embedded .op-action .op-bet-btn { height: 34px; font-size: 12px; min-width: 150px; }

        /* A field: its caption over its stepper, so every input says what it is. */
        body.embedded .op-field { display: inline-flex; flex-direction: column; gap: 2px; min-width: 0; }
        body.embedded .op-cap {
            display: flex; align-items: center; gap: 5px;
            height: 13px; padding: 0 1px;
            font-family: inherit; font-size: 8.5px; font-weight: 700;
            letter-spacing: 0.14em; text-transform: uppercase;
            color: var(--text-muted);
            white-space: nowrap; overflow: hidden;
        }
        /* Auto cash-out: the caption is its switch. Off: grey, the stepper dimmed and
           empty; on: green, the stepper lit, its multiplier in it. */
        body.embedded button.op-cap {
            background: transparent; border: 0; border-radius: 3px;
            cursor: pointer;
        }
        body.embedded button.op-cap:focus-visible { outline: 2px solid var(--hud); outline-offset: 1px; }
        body.embedded .op-switch-track {
            position: relative; flex: 0 0 auto;
            width: 22px; height: 12px;
            border: 1px solid rgba(148,163,184,0.6); border-radius: 999px;
            background: rgba(var(--panel-rgb),0.8);
            transition: background 0.15s, border-color 0.15s;
        }
        body.embedded .op-switch-track > i {
            position: absolute; top: 1px; left: 1px;
            width: 8px; height: 8px; border-radius: 50%;
            background: rgba(148,163,184,0.9);
            transition: transform 0.15s ease, background 0.15s;
        }
        body.embedded .op-cap-state {
            min-width: 3.4em; text-align: center; /* On and Off take the same room: nothing shifts */
            padding: 0 4px; border-radius: 3px;
            background: rgba(148,163,184,0.18);
            letter-spacing: 0.1em;
        }
        body.embedded .op-field-auto .op-stepper { transition: opacity 0.15s, border-color 0.15s, box-shadow 0.15s; }
        body.embedded .op-field-auto[data-on="false"] .op-stepper { opacity: 0.6; }
        body.embedded .op-field-auto[data-on="true"] .op-cap { color: var(--good); }
        body.embedded .op-field-auto[data-on="true"] .op-switch-track { background: rgba(34,197,94,0.3); border-color: var(--good); }
        body.embedded .op-field-auto[data-on="true"] .op-switch-track > i { transform: translateX(10px); background: var(--good); box-shadow: 0 0 6px var(--good); }
        body.embedded .op-field-auto[data-on="true"] .op-cap-state { background: var(--good); color: #052e16; }
        body.embedded .op-field-auto[data-on="true"] .op-stepper { border-color: rgba(34,197,94,0.65); box-shadow: 0 0 8px rgba(34,197,94,0.25); }
        /* Auto bet running: the bet and auto cash-out it repeats are locked. */
        body.embedded .op-slot[data-auto="true"] .op-field { opacity: 0.55; }
        body.embedded .op-field :disabled { cursor: not-allowed; }

        /* Cash Out names the auto cash-out the bet carries, under the live multiplier. */
        body.embedded .op-cashout-btn { flex-direction: column; gap: 1px; line-height: 1.1; }
        body.embedded .op-cashout-auto {
            font-size: 8.5px; font-weight: 600; letter-spacing: 0.14em;
            opacity: 0.85;
        }
        body.embedded .op-cashout-auto:empty { display: none; }

        /* Cashed out: the result takes Cash Out's place until the next round's betting. */
        body.embedded .op-cashed {
            display: inline-flex; align-items: center; justify-content: center; gap: 8px;
            height: 34px; padding: 0 12px;
            border: 1px solid rgba(34,197,94,0.65); border-radius: 4px;
            background: rgba(34,197,94,0.16);
            box-shadow: 0 0 14px rgba(34,197,94,0.35);
            color: var(--good);
            font-size: 11px; font-weight: 700; letter-spacing: 0.14em; text-transform: uppercase;
            white-space: nowrap;
            font-variant-numeric: tabular-nums;
        }
        body.embedded .op-slot[data-mode="cashed"] .op-cashed { animation: op-cashed-in 0.4s cubic-bezier(.34,1.56,.64,1); }
        @keyframes op-cashed-in { from { transform: scale(0.85); opacity: 0; } }
        body.embedded .op-cashed-x { font-size: 1.25em; letter-spacing: 0.02em; color: #ecfdf5; }
        body.embedded .op-cashed-win { letter-spacing: 0.02em; color: var(--gold); }
        body.embedded .op-cashed-win:empty { display: none; }

        /* Bet placed, waiting for the dive: said plainly, not greyed out like a dead button. */
        body.embedded .op-slot[data-mode="placed"] .op-bet-btn:disabled {
            opacity: 0.9; filter: none; cursor: default;
            background: rgba(251,191,36,0.12);
        }
        body.embedded .op-slot[data-mode="placed"] .op-bet-btn::after { content: '✓'; margin-left: 6px; }

        /* Auto bet's count while it runs: bets placed / bets in all. */
        body.embedded .op-autobet-count { letter-spacing: 0.04em; font-variant-numeric: tabular-nums; }
        body.embedded .op-autobet-count:empty { display: none; }

        /* Bet slots: one row each, the second under the first (the bar grows for it:
           body.two-bets). + adds the second, − on it takes it away. */
        body.embedded .op-slots { display: flex; flex-direction: column; gap: 6px; flex-shrink: 0; }
        body.embedded .op-slot[hidden] { display: none; }
        /* The bets keep their room; the status line gives way (it truncates). */
        body.embedded .op-status { min-width: 0; flex: 0 1 auto; }
        /* With the second bet open, the first keeps its + button's room (invisible), so
           the rows line up. A class, not [hidden]: the page's base CSS makes that !important. */
        body.embedded .op-add-slot.is-placeholder { visibility: hidden; }
        body.embedded .op-slot-toggle {
            flex: 0 0 auto;
            width: 30px; height: 30px;
            display: inline-flex; align-items: center; justify-content: center;
            background: rgba(var(--panel-rgb),0.6);
            border: 1px solid rgba(var(--hud-rgb),0.28);
            border-radius: 50%;
            color: var(--hud);
            font-family: inherit; font-size: 16px; font-weight: 700; line-height: 1;
            cursor: pointer;
            transition: background 0.15s, color 0.15s, opacity 0.15s;
        }
        body.embedded .op-slot-toggle:hover:not(:disabled) { background: rgba(var(--hud-rgb),0.18); color: var(--hud-bright); }
        body.embedded .op-slot-toggle:disabled { opacity: 0.3; cursor: not-allowed; }
        body.embedded .op-slot-toggle[hidden] { display: none; }
        body.embedded.two-bets { --op-bar-h: 100px; }

        /* Hide legacy cashout button + nudge the in-game submarine HUD up. */
        body.embedded .cashout-wrap { display: none; }
        body.embedded .sub-wrap { --lift: calc(var(--op-bar-h) * 0.45); }
        body.embedded .sonar    { bottom: calc(110px + var(--op-bar-h)); }

        /* Mobile (<720px) — bar wraps into rows so 360-414px screens can
           still place a bet without horizontal overflow. Layout becomes:
             row 1: balance · phase · status
             row 2: stake stepper · auto stepper
             row 3: Place Bet · AUTO BET · Cash Out
           In cashout mode the inputs row collapses (display:none on inputs),
           so the bar effectively shrinks to 2 rows + a giant Cash Out CTA. */
        @media (max-width: 719px) {
            body.embedded { --op-bar-h: 126px; }
            body.embedded .operator-panel {
                flex-wrap: wrap;
                white-space: normal;
                height: auto;
                min-height: var(--op-bar-h);
                gap: 4px 6px;
                padding: 5px 8px 6px;
                font-size: 11px;
                align-items: center;
            }
            /* Embedded mobile: shrink the multiplier so it doesn't fight the
               bar for vertical space. 22vw → 16vw means ~58px on a 360-width
               viewport — still the dominant element. */
            body.embedded .multiplier { font-size: 16vw; }
            /* Bottom-align the gauges with the multiplier so they extend
               UPWARD from the multiplier's baseline, never below it. With
               `align-items: center` (the default on .center-row) the hull
               gauge — which is taller than the mobile-shrunk multiplier —
               extends BELOW the baseline and collides with the operator
               bar's top edge. flex-end pins all three items' bottoms to
               the same line so the bar can never overlap them. */
            body.embedded .center-row { align-items: flex-end; }
            /* Lift the whole multiplier+gauges block higher above the bar.
               The default 16px clearance is too tight on mobile — the bar's
               actual rendered height fluctuates with status text length /
               wrapping, and gauges sit ~46px tall on the multiplier's
               baseline. 36px gives the assembly clean breathing room. */
            body.embedded .center { bottom: calc(36px + var(--op-bar-h)); }
            /* Drop visual chrome that wastes mobile pixels. The `.visible`
               variant of op-last has higher specificity in the base rules,
               so match it explicitly here or it'll stay rendered. */
            body.embedded .op-sep,
            body.embedded .op-spacer,
            body.embedded .op-last,
            body.embedded .op-last.visible { display: none !important; }
            /* Swap to the short "AUTO" label so the toggle sits next to
               PLACE BET on the same row instead of wrapping below. */
            body.embedded .op-autobet-btn .op-autobet-label-full  { display: none; }
            body.embedded .op-autobet-btn .op-autobet-label-short { display: inline; }

            /* Force two-row layout: info chunks (id/phase/status) stay on
               row 1, the bet-row (which has flex-basis:100%) wraps to row 2.
               Source order has status AFTER bet-row, so without `order:` it
               would land on a third row. */
            body.embedded .op-id     { order: 0; }
            body.embedded .op-phase  { order: 0; }
            body.embedded .op-status { order: 0; }
            body.embedded .op-bet-row { order: 1; }

            body.embedded .op-id { gap: 8px; min-width: 0; flex-shrink: 1; }
            body.embedded .op-id .op-name {
                max-width: 96px;
                overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
                font-size: 12px;
            }
            body.embedded .op-id .op-balance {
                font-size: 15px;
                max-width: 140px;
                overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
            }
            body.embedded .op-phase { font-size: 9px; padding: 3px 8px; letter-spacing: 0.16em; flex-shrink: 0; margin-left: auto; }
            /* Status gets its own full-width line under the player row so the
               bet/cashout message reads in full instead of an ellipsis at 9px.
               --op-bar-h above accounts for this extra line. */
            body.embedded .op-status {
                flex: 1 0 100%;
                font-size: 11px;
                margin-left: 0;
                text-align: left;
                min-width: 0;
                max-width: none;
                overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
            }

            /* Bet row breaks onto its own line(s) and wraps internally. */
            body.embedded .op-bet-row {
                flex: 1 0 100%;
                flex-wrap: wrap;
                gap: 6px;
                justify-content: stretch;
            }

            /* Fields share whatever row 2 has left after AUTO + PLACE BET, so the
               stake and the auto cash-out (and their captions) stay readable from 360px up. */
            body.embedded .op-field { flex: 1 1 0; }
            body.embedded .op-field-auto { flex-grow: 1.1; } /* the longer caption */
            body.embedded .op-field .op-stepper { display: flex; height: 30px; } /* as tall as the buttons */
            body.embedded .op-stepper .op-input,
            body.embedded .op-stepper-auto .op-input { flex: 1 1 auto; width: 0; min-width: 34px; }
            body.embedded .op-cap { font-size: 8px; letter-spacing: 0.02em; gap: 3px; }
            /* No room for On/Off here: the switch, the colour and the field's "off" say it. */
            body.embedded .op-cap-state { display: none; }
            body.embedded .op-switch-track { width: 18px; height: 10px; }
            body.embedded .op-switch-track > i { width: 6px; height: 6px; }
            body.embedded .op-field-auto[data-on="true"] .op-switch-track > i { transform: translateX(8px); }
            /* "Bet placed" in Place Bet's room, so the row doesn't shift. */
            body.embedded .op-slot[data-mode="placed"] .op-bet-btn::after { content: none; }
            body.embedded .op-input { font-size: 12px; padding: 4px 2px; text-align: center; }
            body.embedded .op-input::placeholder { font-size: 11px; letter-spacing: 0; }
            body.embedded .op-step  { padding: 0 4px; font-size: 13px; }

            /* Tight bet row — stays on one line in place mode. */
            body.embedded .op-bet-row { gap: 5px; flex-wrap: nowrap; overflow: hidden; }
            body.embedded .op-btn { font-size: 10px; padding: 0 8px; height: 30px; min-width: 0; }
            body.embedded .op-action .op-bet-btn { height: 30px; font-size: 10px; min-width: 0; padding: 0 9px; letter-spacing: 0.12em; }
            body.embedded .op-action { flex: 0 0 auto; min-width: 0; }
            body.embedded .op-slot:is([data-mode="cashout"], [data-mode="cashed"]) .op-action { flex: 1 1 auto; }
            /* Cash Out and the result stretch over this spot in their modes anyway — don't
               let their wider labels size the slot while PLACE BET is showing. */
            body.embedded .op-slot:not([data-mode="cashout"]) .op-action .op-cashout-btn,
            body.embedded .op-slot:not([data-mode="cashed"]) .op-action .op-cashed { display: none; }
            body.embedded .op-slot:is([data-mode="cashout"], [data-mode="cashed"]) .op-field { display: none; }
            body.embedded .op-autobet-btn  {
                flex: 0 0 auto;
                padding: 0 8px; height: 30px;
                font-size: 9px; letter-spacing: 0.1em; gap: 4px;
            }
            body.embedded .op-cashout-btn  {
                flex: 1 1 auto; min-width: 0; font-size: 14px;
                white-space: nowrap;
            }
            body.embedded .op-cashout-btn .op-cashout-x { font-size: 11px; margin-left: 6px; }

            /* Cashout mode: only AUTO BET + Cash Out left on row 2. Blow up
               the Cash Out CTA so the bar's reserved height doesn't leave
               dead space, AND so the player's primary action is the most
               thumbable target. */
            body.embedded .op-slot[data-mode="cashout"] .op-cashout-btn {
                height: 48px; font-size: 16px;
            }
            body.embedded .op-slot[data-mode="cashed"] .op-cashed {
                height: 48px; font-size: 12px; letter-spacing: 0.08em; gap: 6px;
            }
            body.embedded .op-slot:is([data-mode="cashout"], [data-mode="cashed"]) .op-autobet-btn {
                height: 48px;
            }
            /* A running auto bet: AUTO over its count, in the same narrow button. */
            body.embedded .op-autobet-btn[data-active="true"] { flex-direction: column; gap: 1px; line-height: 1.05; }
            body.embedded .op-autobet-btn[data-active="true"] .op-autobet-dot { display: none; }

            /* The slots take the bet row's place (row 3), one row each. With two, a
               slot's Cash Out stays 40px high so the bar's height holds. */
            body.embedded .op-slots { order: 1; flex: 1 0 100%; gap: 5px; }
            body.embedded .op-slots > .op-bet-row { flex: 0 0 auto; }
            body.embedded .op-slot-toggle { width: 28px; height: 30px; border-radius: 6px; }
            /* Diving or cashed out, the fields are gone and the buttons taller: + sits mid-height. */
            body.embedded .op-slot:is([data-mode="cashout"], [data-mode="cashed"]) .op-slot-toggle { align-self: center; }
            body.embedded.two-bets { --op-bar-h: 178px; }
            body.embedded.two-bets .op-slot[data-mode="cashout"] .op-cashout-btn,
            body.embedded.two-bets .op-slot:is([data-mode="cashout"], [data-mode="cashed"]) .op-autobet-btn { height: 40px; font-size: 14px; }
            body.embedded.two-bets .op-slot[data-mode="cashed"] .op-cashed { height: 40px; }
        }

        /* Desktop — scale the wager controls + cashout CTA ~20% larger. */
        @media (min-width: 720px) {
            body.embedded                                  { --op-bar-h: 62px; }
            body.embedded .operator-panel                  { font-size: 13px; gap: 14px; padding: 0 16px; }
            body.embedded .op-id .op-name                  { font-size: 15px; }
            body.embedded .op-id .op-balance               { font-size: 14px; }
            body.embedded .op-sep                          { height: 26px; }
            body.embedded .op-phase                        { font-size: 10px; padding: 3px 9px; }
            body.embedded .op-input                        { font-size: 14px; padding: 6px 10px; }
            body.embedded .op-input[data-kind="stake"]     { width: 84px; }
            body.embedded .op-input[data-kind="auto"]      { width: 72px; }
            body.embedded .op-stepper-auto .op-input       { width: 66px; }
            body.embedded .op-step                         { font-size: 17px; padding: 0 12px; }
            body.embedded .op-btn                          { font-size: 13px; padding: 0 18px; height: 36px; }
            /* One line: steppers, AUTO BET, Place Bet / Cash Out all 40px; + centred on it. */
            body.embedded .op-stepper,
            body.embedded .op-autobet-btn                  { height: 40px; }
            body.embedded .op-cashout-btn,
            body.embedded .op-action .op-bet-btn           { font-size: 14px; height: 40px; min-width: 180px; }
            body.embedded .op-cashout-btn .op-cashout-x    { font-size: 12px; }
            body.embedded .op-cashout-auto                 { font-size: 9.5px; }
            body.embedded .op-cashed                       { font-size: 12px; height: 40px; }
            body.embedded .op-cap                          { font-size: 9px; }
            body.embedded .op-status                       { font-size: 11px; max-width: 260px; }
            body.embedded .op-last                         { padding: 5px 12px; }
            body.embedded .op-last-mult                    { font-size: 15px; }
            body.embedded .op-last-payout                  { font-size: 14px; }
            body.embedded .op-slot-toggle                  { width: 36px; height: 36px; font-size: 18px; margin-bottom: 2px; }
            body.embedded.two-bets                         { --op-bar-h: 124px; }
        }

        /* Fullscreen mode — center the HUD as a floating pill, 10% larger.
           Triggered by either the native :fullscreen pseudo-class or the
           is-fullscreen class the parent flips on via postMessage. */
        :fullscreen .operator-panel,
        body.is-fullscreen .operator-panel {
            left: 50%;
            right: auto;
            bottom: 18px;
            width: max-content;
            max-width: calc(100vw - 48px);
            justify-content: center;
            border: 1px solid rgba(var(--hud-rgb),0.32);
            border-radius: 10px;
            box-shadow:
                0 8px 28px rgba(0,0,0,0.55),
                0 0 30px rgba(var(--hud-rgb),0.16);
            transform: translateX(-50%) scale(1.1);
            transform-origin: 50% 100%;
        }
        :fullscreen .operator-panel::before,
        body.is-fullscreen .operator-panel::before {
            /* The decorative top scan-line riding the viewport edge would clip
               on rounded corners — hide it once the bar is detached. */
            display: none;
        }


        /* ---------- Bet bar: the holo glass skin ----------
           A pane of frosted glass the game shows through, in each game's theme colours: a light
           running along its top edge, soft rounded capsules, cyan captions, and a glowing button
           with a sheen passing over it. Only the look: no sizes, paddings or (on phones)
           letter-spacing here, so the layout above, and the phone rules that fit the bar in one
           row, stay as they are. Font: Sora, served from public/fonts/sora (SIL Open Font License,
           its text beside it); its tabular digits keep amounts from jittering as they change. */
        @font-face { font-family: "HUD Sora"; font-weight: 400 800; font-style: normal; font-display: swap;
            src: url("{{ asset('fonts/sora/sora-latin-ext.woff2') }}") format("woff2");
            unicode-range: U+0100-02BA, U+02BD-02C5, U+02C7-02CC, U+02CE-02D7, U+02DD-02FF, U+0304, U+0308, U+0329, U+1D00-1DBF, U+1E00-1E9F, U+1EF2-1EFF, U+2020, U+20A0-20AB, U+20AD-20C4, U+2113, U+2C60-2C7F, U+A720-A7FF; }
        @font-face { font-family: "HUD Sora"; font-weight: 400 800; font-style: normal; font-display: swap;
            src: url("{{ asset('fonts/sora/sora-latin.woff2') }}") format("woff2");
            unicode-range: U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+0304, U+0308, U+0329, U+2000-206F, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD; }
        body.embedded {
            --op-f: "HUD Sora", "Segoe UI", system-ui, sans-serif;
            --op-cyan-rgb: 103, 232, 249;
        }
        body.embedded .operator-panel {
            font-family: var(--op-f);
            font-variant-numeric: tabular-nums;
            background:
                linear-gradient(180deg, rgba(255,255,255,0.12), rgba(0,0,0,0.14)),
                rgba(var(--panel-rgb),0.78);
            backdrop-filter: blur(16px) saturate(1.5);
            -webkit-backdrop-filter: blur(16px) saturate(1.5);
            border-top: 1px solid rgba(255,255,255,0.2);
            border-radius: 20px 20px 0 0;
            box-shadow: 0 -12px 30px -14px rgba(0,0,0,0.65), inset 0 1px 0 rgba(255,255,255,0.22);
        }
        /* The light running along the top edge: the old top line, now a moving glint. */
        body.embedded .operator-panel::before {
            left: 24px; right: 24px; top: -1px; height: 1px; opacity: 1;
            background: linear-gradient(90deg, transparent, rgba(var(--hud-rgb),0.95), rgba(var(--op-cyan-rgb),0.95), transparent) 0 0 / 50% 100% no-repeat;
            animation: op-holo-sweep 3.6s linear infinite;
        }
        @keyframes op-holo-sweep { from { background-position: -60% 0; } to { background-position: 160% 0; } }

        body.embedded .op-id .op-name { font-weight: 600; color: rgba(255,255,255,0.82); }
        body.embedded .op-id .op-balance { font-weight: 800; text-shadow: 0 0 18px rgba(var(--hud-rgb),0.5); }
        body.embedded .op-sep { background: linear-gradient(transparent, rgba(255,255,255,0.22), transparent); }

        body.embedded .op-phase {
            font-weight: 700; opacity: 1;
            background: rgba(0,0,0,0.28);
            border-color: rgba(255,255,255,0.16);
        }
        body.embedded .op-phase::before { animation: op-holo-ping 1.4s ease-out infinite; }
        @keyframes op-holo-ping {
            from { box-shadow: 0 0 0 0 color-mix(in srgb, currentColor 70%, transparent); }
            to   { box-shadow: 0 0 0 6px transparent; }
        }

        body.embedded .op-status { font-weight: 600; color: rgba(var(--op-cyan-rgb),0.9); }

        /* Fields: cyan captions; the steppers are recessed glass capsules. */
        body.embedded .op-cap { font-weight: 700; color: rgba(var(--op-cyan-rgb),0.85); }
        body.embedded .op-field-auto[data-on="false"] .op-cap { color: var(--text-muted); }
        body.embedded .op-stepper {
            border-color: transparent;
            border-radius: 14px;
            background: rgba(0,0,0,0.3);
            box-shadow: inset 0 1px 3px rgba(0,0,0,0.5), inset 0 0 0 1px rgba(255,255,255,0.08);
        }
        body.embedded .op-stepper:focus-within { border-color: transparent; box-shadow: inset 0 1px 3px rgba(0,0,0,0.5), inset 0 0 0 1px var(--hud), 0 0 12px rgba(var(--hud-rgb),0.35); }
        body.embedded .op-field-auto[data-on="true"] .op-stepper { border-color: transparent; box-shadow: inset 0 1px 3px rgba(0,0,0,0.5), inset 0 0 0 1px rgba(34,197,94,0.7), 0 0 10px rgba(34,197,94,0.25); }
        body.embedded .op-stepper .op-input { background: transparent; }
        body.embedded .op-step { background: rgba(255,255,255,0.06); }
        body.embedded .op-step:hover { background: rgba(var(--hud-rgb),0.2); }
        body.embedded .op-input { font-family: inherit; font-weight: 700; color: #fff; }

        /* Buttons: soft capsules. Place Bet a glowing gold gradient with a sheen; a bet in, gold
           glass; Cash Out a glowing green gradient; the result, green glass. */
        body.embedded .op-btn { border-radius: 14px; font-family: inherit; font-weight: 800; }
        body.embedded .op-bet-btn {
            color: #1d1405; border-color: transparent;
            background: linear-gradient(135deg, #fef08a, var(--hud) 45%, var(--hud-dark));
            box-shadow: 0 0 22px rgba(var(--hud-rgb),0.42), inset 0 1px 0 rgba(255,255,255,0.6);
        }
        body.embedded .op-bet-btn::before,
        body.embedded .op-cashout-btn::before { background: #fff; opacity: 0; }
        body.embedded .op-bet-btn:hover:not(:disabled) { box-shadow: 0 0 30px rgba(var(--hud-rgb),0.65), inset 0 1px 0 rgba(255,255,255,0.6); }
        body.embedded .op-bet-btn:hover:not(:disabled)::before,
        body.embedded .op-cashout-btn:hover:not(:disabled)::before { opacity: 0.18; }
        body.embedded .op-bet-btn:not(:disabled)::after {
            content: ""; position: absolute; inset: 0; translate: -120% 0; pointer-events: none;
            background: linear-gradient(105deg, transparent 30%, rgba(255,255,255,0.55) 48%, transparent 66%);
            animation: op-holo-sheen 2.8s ease-in-out infinite;
        }
        @keyframes op-holo-sheen { 60%, 100% { translate: 120% 0; } }
        body.embedded .op-slot[data-mode="placed"] .op-bet-btn:disabled {
            opacity: 1; filter: none; color: var(--hud); border-color: transparent;
            background: rgba(var(--hud-rgb),0.13);
            box-shadow: inset 0 0 0 1px rgba(var(--hud-rgb),0.65), 0 0 18px rgba(var(--hud-rgb),0.2);
        }
        body.embedded .op-cashout-btn {
            color: #04220f; border-color: transparent;
            background: linear-gradient(135deg, #bbf7d0, #4ade80 45%, #16a34a);
            box-shadow: 0 0 26px rgba(34,197,94,0.5), inset 0 1px 0 rgba(255,255,255,0.6);
        }
        body.embedded .op-cashout-btn:hover:not(:disabled) { box-shadow: 0 0 34px rgba(34,197,94,0.75), inset 0 1px 0 rgba(255,255,255,0.6); }
        @keyframes op-cashout-pulse {
            0%, 100% { box-shadow: 0 0 18px rgba(34,197,94,0.4), inset 0 1px 0 rgba(255,255,255,0.6); }
            50%      { box-shadow: 0 0 34px rgba(34,197,94,0.85), inset 0 1px 0 rgba(255,255,255,0.6); }
        }
        body.embedded .op-cashed {
            border-color: transparent; border-radius: 14px;
            color: #bbf7d0; background: rgba(34,197,94,0.15);
            box-shadow: inset 0 0 0 1px rgba(34,197,94,0.6), 0 0 16px rgba(34,197,94,0.25);
        }
        body.embedded .op-autobet-btn {
            border-color: transparent; background: rgba(0,0,0,0.24);
            box-shadow: inset 0 0 0 1px rgba(255,255,255,0.12);
        }
        body.embedded .op-autobet-btn[data-active="true"] {
            border-color: transparent; background: rgba(var(--hud-rgb),0.1);
            box-shadow: inset 0 0 0 1px rgba(var(--hud-rgb),0.6), 0 0 14px rgba(var(--hud-rgb),0.3);
        }
        body.embedded .op-slot-toggle {
            border-color: transparent; border-radius: 12px;
            background: rgba(0,0,0,0.24); box-shadow: inset 0 0 0 1px rgba(var(--hud-rgb),0.5);
            font-family: inherit;
        }
        body.embedded .op-last { border-radius: 12px; }

        /* Wide screens: a little more room for the balance. */
        @media (min-width: 720px) {
            body.embedded .op-id .op-name { letter-spacing: 0.06em; }
            body.embedded .op-id .op-balance { font-size: 17px; }
        }
        /* Phones: tighter capsules for the tighter row. */
        @media (max-width: 719px) {
            body.embedded .op-stepper,
            body.embedded .op-btn,
            body.embedded .op-cashed,
            body.embedded .op-slot-toggle { border-radius: 10px; }
            body.embedded .operator-panel { border-radius: 16px 16px 0 0; }
        }

        /* Fullscreen: the floating bar is a whole glass pane. */
        :fullscreen .operator-panel,
        body.is-fullscreen .operator-panel {
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 20px;
        }
        @media (prefers-reduced-motion: reduce) {
            body.embedded .operator-panel::before,
            body.embedded .op-phase::before,
            body.embedded .op-bet-btn:not(:disabled)::after { animation: none; }
        }

        @includeIf("crash.scenes.{$scene}.styles-sonar")
        /* ---------- Strobes (critical) ---------- */
        .strobe {
            position: absolute; inset: 0;
            background: radial-gradient(ellipse at 50% 50%, rgba(239,68,68,0.45) 0%, transparent 60%);
            opacity: 0; pointer-events: none;
            z-index: 6;
        }
        .critical .strobe { animation: strobe 0.4s linear infinite; }
        @keyframes strobe {
            0%, 100% { opacity: 0.0; }
            50%      { opacity: 0.8; }
        }

        /* ===================================================
           CRASH / IMPLODE SEQUENCE (multi-layer)
           Layer order from back to front:
             .scene → .strobe → .shockwave → .crash-flash →
             .crash-debris → .crash-cracks → .crash-banner
           =================================================== */

        /* (1) Hard white flash — punches first, fades fast */
        .crash-flash {
            position: absolute; inset: 0;
            background: white;
            opacity: 0;
            pointer-events: none;
            z-index: 48;
        }
        .crashed .crash-flash {
            animation: crash-flash 0.9s ease-out var(--boom-at) forwards;
        }
        /* The gothic sprite has its own burst glow — keep the screen flash a quick punch. */
        :root:is([data-theme="burton"], [data-theme="jackpot"]) .crashed .crash-flash {
            animation: crash-flash-soft 0.45s ease-out var(--boom-at) forwards;
        }
        @keyframes crash-flash-soft {
            0%   { opacity: 0; }
            12%  { opacity: 0.55; }
            100% { opacity: 0; }
        }
        @keyframes crash-flash {
            0%   { opacity: 0; }
            6%   { opacity: 1; }
            18%  { opacity: 0.9; }
            55%  { opacity: 0.25; }
            100% { opacity: 0; }
        }

        /* (2) Expanding shockwave ring */
        .shockwave {
            position: absolute; left: 50%; top: 50%;
            width: 60px; height: 60px;
            border-radius: 50%;
            border: 4px solid white;
            background: radial-gradient(circle, rgba(255,255,255,0.55) 0%, transparent 65%);
            transform: translate(-50%, -50%) scale(0);
            opacity: 0;
            pointer-events: none;
            z-index: 49;
            box-shadow: 0 0 60px white, inset 0 0 60px white;
        }
        .crashed .shockwave {
            animation: shockwave 1.1s cubic-bezier(0.2, 0.8, 0.4, 1) var(--boom-at) forwards;
        }
        @keyframes shockwave {
            0%   { transform: translate(-50%, -50%) scale(0); opacity: 0; border-width: 6px; }
            10%  { opacity: 1; }
            100% { transform: translate(-50%, -50%) scale(45); opacity: 0; border-width: 1px; }
        }

        /* (3) Debris — 24 fragments shooting outward */
        .crash-debris {
            position: absolute; left: 50%; top: 50%;
            width: 1px; height: 1px;
            pointer-events: none;
            z-index: 50;
        }
        .frag {
            position: absolute; left: 0; top: 0;
            width: 6px; height: 6px;
            background: linear-gradient(135deg, #fef3c7, #f59e0b 50%, #7f1d1d);
            border-radius: 1px;
            opacity: 0;
            transform: translate(-50%, -50%);
            box-shadow: 0 0 8px rgba(251,191,36,0.7);
        }
        .crashed .frag {
            animation: frag-fly 1.4s cubic-bezier(0.15, 0.85, 0.4, 1) calc(var(--boom-at) + var(--d, 0s)) forwards;
        }
        @keyframes frag-fly {
            0%   { opacity: 0; transform: translate(-50%, -50%) rotate(0); }
            8%   { opacity: 1; }
            100% {
                opacity: 0;
                transform: translate(calc(-50% + var(--dx, 200px)), calc(-50% + var(--dy, 200px))) rotate(720deg);
            }
        }

        /* (4) Glass crack overlay — SVG inline, masked over the dome */
        .crash-cracks {
            position: absolute; inset: 0;
            opacity: 0;
            pointer-events: none;
            z-index: 52;
        }
        .crashed .crash-cracks {
            animation: cracks-in 1.6s ease-out var(--boom-at) forwards;
        }
        @keyframes cracks-in {
            0%   { opacity: 0; transform: scale(0.4); }
            12%  { opacity: 0.95; transform: scale(1); }
            65%  { opacity: 0.7; }
            100% { opacity: 0; }
        }

        /* (5) HULL BREACH banner — slams in with a short glitch, holds, fades. */
        .crash-banner {
            position: absolute; left: 50%; top: 34%;
            display: flex; align-items: center; gap: clamp(10px, 2vw, 22px);
            transform: translate(-50%, 0);
            opacity: 0;
            pointer-events: none;
            white-space: nowrap;
            z-index: 53;
        }
        .crash-banner b {
            width: clamp(28px, 8vw, 110px); height: 3px;
            background: repeating-linear-gradient(-45deg, var(--warn) 0 6px, transparent 6px 10px);
            transform: scaleX(0);
        }
        .crash-banner b:first-child { transform-origin: right; }
        .crash-banner b:last-child  { transform-origin: left; }
        .crash-banner span {
            display: inline-block;
            font-family: "Palatino Linotype", "Book Antiqua", Georgia, serif;
            font-size: clamp(30px, 7vw, 72px); font-weight: 800; line-height: 1;
            letter-spacing: 0.12em; text-transform: uppercase;
            color: #ffe4e4;
            -webkit-text-stroke: 1px rgba(60,0,0,0.6);
            text-shadow: 0 3px 0 #450a0a, 0 0 24px rgba(239,68,68,0.9), 0 0 60px rgba(239,68,68,0.55);
        }
        .crashed .crash-banner { animation: banner-in 1.7s cubic-bezier(0.2, 0.9, 0.3, 1) var(--boom-at) forwards; }
        .crashed .crash-banner span { animation: banner-glitch 0.35s steps(1) calc(var(--boom-at) + 0.08s); }
        .crashed .crash-banner b { animation: banner-line 0.45s ease-out calc(var(--boom-at) + 0.12s) forwards; }
        @keyframes banner-in {
            0%   { opacity: 0; transform: translate(-50%, 0) scale(1.7); }
            10%  { opacity: 1; transform: translate(-50%, 0) scale(0.96); }
            18%  { transform: translate(-50%, 0) scale(1); }
            75%  { opacity: 1; transform: translate(-50%, 0) scale(1); }
            100% { opacity: 0; transform: translate(-50%, -10px) scale(1); }
        }
        @keyframes banner-glitch {
            0%  { transform: translate(-4px, 0); text-shadow: 4px 0 0 rgba(34,211,238,0.8), -4px 0 0 rgba(239,68,68,0.9); }
            25% { transform: translate(3px, 1px); text-shadow: -3px 0 0 rgba(34,211,238,0.8), 3px 0 0 rgba(239,68,68,0.9); }
            50% { transform: translate(-2px, -1px); }
            75% { transform: none; text-shadow: 2px 0 0 rgba(34,211,238,0.6), -2px 0 0 rgba(239,68,68,0.7); }
        }
        @keyframes banner-line { to { transform: scaleX(1); } }

        /* (6) Stage shake — strong then settle */
        .crashed .stage { animation: stage-rumble 0.9s linear var(--boom-at); }
        @keyframes stage-rumble {
            0%   { transform: translate(0,0); }
            10%  { transform: translate(-8px, 6px) rotate(-0.4deg); }
            20%  { transform: translate(7px, -5px) rotate(0.3deg); }
            30%  { transform: translate(-5px, 4px) rotate(-0.2deg); }
            45%  { transform: translate(4px, -3px); }
            60%  { transform: translate(-2px, 2px); }
            80%  { transform: translate(1px, -1px); }
            100% { transform: translate(0,0); }
        }

        @includeIf("crash.scenes.{$scene}.styles-crash")
        /* ---------- Log ---------- */
        /* Debug-only activity feed: hidden for players, shown with ?debug=1. */
        .log {
            display: none;
            position: absolute; right: 12px; bottom: 12px;
            width: 220px; max-height: 110px; overflow: hidden;
            font-size: 9px; line-height: 1.5; color: var(--hud); opacity: 0.45;
            z-index: 10; pointer-events: none;
            text-align: right;
        }
        .log div { padding: 1px 0; }
        body.debug .log { display: block; }

        /* ---------- Mobile tighten ---------- */
        @media (max-width: 500px) {
            .multiplier { font-size: 22vw; }
            .gauge { padding: 8px 10px; }
            .gauge-value { font-size: 16px; }
            .hull-bar { width: 80px; }
            .cashout { padding: 16px 40px; font-size: 14px; }
            .sonar { width: 180px; bottom: 100px; }
        }
        /* ---------- Studio intro (config crash.intro) ----------
           Two modes: "frames" cross-fades the images in public/images/intro/,
           "scene" (no frames present) animates the game's own artwork. */
        .intro {
            position: fixed; inset: 0; z-index: 100;
            background: #000; cursor: pointer; overflow: hidden;
            transition: opacity var(--intro-fade, 600ms) ease;
        }
        .intro.done { opacity: 0; pointer-events: none; }
        .intro-frame {
            position: absolute; inset: 0; width: 100%; height: 100%;
            object-fit: cover; opacity: 0;
            transition: opacity var(--intro-fade, 400ms) ease;
        }
        .intro-frame.on { opacity: 1; }
        .intro-skip {
            position: absolute; right: 16px; bottom: 16px; z-index: 5;
            font-family: inherit; font-size: 12px; font-weight: 600; line-height: 1;
            letter-spacing: 0.12em; text-transform: uppercase;
            color: rgba(255, 255, 255, 0.55); background: rgba(0, 0, 0, 0.35);
            border: 1px solid rgba(255, 255, 255, 0.2); border-radius: 999px;
            padding: 8px 14px; cursor: pointer;
        }
        .intro-skip:hover { color: #fff; }

        @includeIf("crash.scenes.{$scene}.styles-intro")
    </style>
</head>
<body>
    @include('play.provider-intro')
    @if ($intro)
    <div id="intro" class="intro" hidden aria-hidden="true">
        @if ($intro['mode'] === 'scene')
        @includeIf("crash.scenes.{$scene}.intro-scene")
        @endif
        <button type="button" class="intro-skip">Skip</button>
    </div>
    <script>
        // Studio intro over the live game: either cross-fades the uploaded frames
        // or plays the built-in scene made from the game's own artwork. Never
        // blocks the game: a load error (or, if configured, a repeat visit in the
        // same session) just skips it. It plays whatever the device's
        // reduced-motion setting, like the rest of the game.
        (function studioIntro(cfg) {
            const $intro = document.getElementById('intro');
            const SEEN_KEY = 'orange.intro.seen';
            const SCENE_MS = 4250; // matches the CSS timeline (warp dive ends at ~4.3s)
            const seen = () => { try { return sessionStorage.getItem(SEEN_KEY) === '1'; } catch (_) { return false; } };
            const skipReason = (cfg.oncePerSession && seen()) ? 'already shown in this session' : null;
            if (skipReason) {
                console.info('[intro] skipped: ' + skipReason);
                $intro.remove();
                return;
            }

            const portrait = window.matchMedia('(orientation: portrait)').matches;
            const timers = [];
            let finished = false;
            function finish() {
                if (finished) return;
                finished = true;
                timers.forEach(clearTimeout);
                try { sessionStorage.setItem(SEEN_KEY, '1'); } catch (_) {}
                $intro.classList.add('done');
                setTimeout(() => $intro.remove(), cfg.fadeMs + 50);
            }

            const giveUp = (e) => { console.info('[intro] skipped: ' + (e && e.message === 'intro timeout' ? 'artwork not loaded within 5 s' : 'artwork failed to load')); finish(); };
            $intro.style.setProperty('--intro-fade', cfg.fadeMs + 'ms');
            $intro.hidden = false;
            $intro.addEventListener('click', finish);
            window.addEventListener('keydown', finish, { once: true });

            const decode = (img) => (img.decode ? img.decode() : Promise.resolve());
            // Give up after 5 s so a slow network never holds players at a black screen.
            const timeout = new Promise((_, reject) => setTimeout(() => reject(new Error('intro timeout')), 5000));
            // It plays once the Majestic Games intro (play.provider-intro) has made way.
            const providerDone = window.orangeIntro ? window.orangeIntro.done : Promise.resolve();

            if (cfg.mode === 'frames') {
                const urls = (portrait && cfg.portrait.length) ? cfg.portrait : (cfg.landscape.length ? cfg.landscape : cfg.portrait);
                const $skip = $intro.querySelector('.intro-skip');
                const imgs = urls.map((src) => {
                    const img = new Image();
                    img.alt = '';
                    img.className = 'intro-frame';
                    img.src = src;
                    $intro.insertBefore(img, $skip); // in order, so each new frame fades in on top
                    return img;
                });
                Promise.race([Promise.all(imgs.map(decode)), timeout]).then(() => providerDone).then(() => {
                    if (finished) return;
                    let at = 0;
                    imgs.forEach((img, i) => {
                        timers.push(setTimeout(() => {
                            img.classList.add('on');
                            if (i > 0) timers.push(setTimeout(() => imgs[i - 1].classList.remove('on'), cfg.fadeMs));
                        }, at));
                        at += cfg.holdMs[i + 1] ?? cfg.frameMs;
                    });
                    timers.push(setTimeout(finish, at));
                }).catch(giveUp);
                return;
            }

            // Scene: wait for the artwork (incl. the CSS background) so nothing pops in mid-animation.
            const bg = new Image();
            const backdrop = cfg.bg[document.documentElement.getAttribute('data-theme')] ?? cfg.bg.burton;
            bg.src = portrait ? backdrop.portrait : backdrop.landscape;
            const art = [bg, ...[...$intro.querySelectorAll('img')].filter((img) => img.getClientRects().length)];
            Promise.race([Promise.allSettled(art.map(decode)), timeout]).then(() => providerDone).then(() => {
                if (finished) return;
                $intro.classList.add('play');
                timers.push(setTimeout(finish, SCENE_MS));
            }).catch(giveUp);
        })(@json($intro));
    </script>
    @endif

    {{-- Top right: one menu button; the game's controls are in its menu (#game-menu). --}}
    <div id="top-controls" class="top-controls">
        <button id="menu-btn" class="ctl-btn menu-btn" type="button" title="Menu" aria-label="Menu"
                aria-haspopup="menu" aria-expanded="false" aria-controls="game-menu">
            <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" aria-hidden="true">
                <path d="M2.5 4h11M2.5 8h11M2.5 12h11"/>
            </svg>
        </button>
    </div>
    {{-- data-close: the item closes the menu; the toggles (sound, theme) leave it open, so their new state shows. --}}
    <div id="game-menu" class="game-menu" role="menu" aria-label="Menu" hidden>
        {{-- Back to the lobby, when the game was opened from one (player_info.home). --}}
        <button id="home-btn" class="menu-item" type="button" role="menuitem" title="Home" data-close hidden>
            <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M2.5 7.2 8 2.6l5.5 4.6"/>
                <path d="M4 6v7.4h3V10h2v3.4h3V6"/>
            </svg>
            <span class="menu-label">Home</span>
        </button>
        @if ($liveRounds ?? null)
        <button id="live-btn" class="menu-item live-btn" type="button" role="menuitemcheckbox" aria-checked="false" aria-controls="live-bets" title="All bets" data-close>
            <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <circle cx="6" cy="5.4" r="2.4"/>
                <path d="M1.8 13.4c.4-2.4 2.1-3.8 4.2-3.8s3.8 1.4 4.2 3.8"/>
                <path d="M10.6 3.2a2.3 2.3 0 0 1 0 4.4"/>
                <path d="M12 9.8c1.2.5 2 1.7 2.2 3.6"/>
            </svg>
            <span class="menu-label">All bets</span>
        </button>
        @endif
        <button id="bets-btn" class="menu-item bets-btn" type="button" role="menuitem" aria-haspopup="dialog" title="My bets" data-close>
            <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M2.6 8a5.4 5.4 0 1 0 1.6-3.85"/>
                <path d="M2.4 2.4v2.8h2.8"/>
                <path d="M8 5v3.2l2.2 1.4"/>
            </svg>
            <span class="menu-label">My bets</span>
        </button>
        <button id="rules-btn" class="menu-item" type="button" role="menuitem" aria-haspopup="dialog" title="Rules" data-close>
            <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M3.5 2.2h6.3l2.7 2.7v8.9h-9z"/>
                <path d="M9.6 2.4v2.7h2.7"/>
                <path d="M5.8 7.6h4.4M5.8 9.8h4.4M5.8 12h2.6"/>
            </svg>
            <span class="menu-label">Rules</span>
        </button>
        <button id="audio-btn"class="menu-item audio-btn" type="button" role="menuitem" title="Sound">
            <svg class="audio-icon-on" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M3 6h2l4-3v10L5 10H3V6Z"/>
                <path d="M11 6c0.8 0.6 1.2 1.4 1.2 2s-0.4 1.4-1.2 2"/>
                <path d="M12.6 4.4c1.4 0.9 2.2 2.2 2.2 3.6s-0.8 2.7-2.2 3.6"/>
            </svg>
            <svg class="audio-icon-mute" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M3 6h2l4-3v10L5 10H3V6Z"/>
                <path d="M11 6l4 4M15 6l-4 4"/>
            </svg>
            <span class="menu-label">Sound</span>
        </button>
        <button id="fs-btn" class="menu-item fs-btn" type="button" role="menuitem" title="Fullscreen" data-close>
            <svg class="fs-icon-enter" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M2 6V2h4M14 6V2h-4M2 10v4h4M14 10v4h-4"/>
            </svg>
            <svg class="fs-icon-exit" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M6 2v4H2M10 2v4h4M6 14v-4H2M10 14v-4h4"/>
            </svg>
            <span class="menu-label fs-label-enter">Fullscreen</span>
            <span class="menu-label fs-label-exit">Exit fullscreen</span>
        </button>
        @if (count($themes) > 1)
        <div class="menu-sep" role="separator"></div>
        @foreach ($themes as $key => $t)
        <button type="button" class="theme-opt" role="menuitemradio" aria-checked="false" data-theme="{{ $key }}" title="Theme: {{ $t['label'] }}">
            <span class="theme-swatch" aria-hidden="true"
                  style="--sw-top: {{ $t['shallow'] }}; --sw-deep: {{ $t['depth2'] }}; --sw-hud: {{ $t['hud'] }};"></span>
            <span class="menu-label">Theme: {{ $t['label'] }}</span>
        </button>
        @endforeach
        @endif
    </div>

    @if ($liveRounds ?? null)
    {{-- All bets: every player's bet in the round, live (config('crash.live_bets')). Filled in by the script. --}}
    <aside id="live-bets" class="live-bets" aria-label="All bets" hidden>
        <div class="lb-top">
            <div class="lb-tabs" role="tablist" aria-label="Round">
                <button type="button" class="lb-tab" role="tab" data-tab="current" aria-selected="true">All bets</button>
                <button type="button" class="lb-tab" role="tab" data-tab="previous" aria-selected="false">Previous</button>
            </div>
            <button id="live-close" class="sheet-btn" type="button" title="Close" aria-label="Close all bets">
                <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="M4 4l8 8M12 4l-8 8"/></svg>
            </button>
        </div>
        <div class="lb-summary">
            <div><b id="lb-count">0/0</b><small>cashed out</small></div>
            <div id="lb-result" class="lb-result" hidden></div>
            <div class="lb-total"><b id="lb-total">0.00</b><small id="lb-total-label">Total win</small></div>
        </div>
        <div class="lb-progress"><i id="lb-progress"></i></div>
        <div class="lb-row lb-head" aria-hidden="true"><span>Player</span><span id="lb-head-bet">Bet</span><span>X</span><span id="lb-head-win">Win</span></div>
        <div id="lb-rows" class="lb-rows" role="list"></div>
    </aside>
    {{-- Phones and narrow screens: the round's bets as circles down the right edge (a tap opens All bets). --}}
    <div id="lb-mini" class="lb-mini" aria-hidden="true" hidden><div id="lb-mini-more" class="lb-dot lb-mini-more" hidden><i></i></div></div>
    @endif

    {{-- Round details (a recent crash, or a bet's round) and My bets. Filled in by the script. --}}
    <div id="sheet" class="sheet" hidden>
        <section class="sheet-card" role="dialog" aria-modal="true" aria-labelledby="sheet-title">
            <header class="sheet-head">
                <button id="sheet-back" class="sheet-btn sheet-back" type="button" title="Back to my bets" aria-label="Back to my bets" hidden>
                    <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 3 5 8l5 5"/></svg>
                </button>
                <h2 id="sheet-title" class="sheet-title"></h2>
                <span id="sheet-pill" hidden></span>
                <span id="sheet-time" class="sheet-time"></span>
                <button id="sheet-close" class="sheet-btn sheet-close" type="button" title="Close" aria-label="Close">
                    <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="M4 4l8 8M12 4l-8 8"/></svg>
                </button>
            </header>
            <div id="sheet-body" class="sheet-body"></div>
        </section>
    </div>

    <div id="stage" class="stage waiting">
        @includeIf("crash.scenes.{$scene}.scene")
        <!-- Strobe sits above the scene but below the UI -->
        <div class="strobe"></div>

        @includeIf("crash.scenes.{$scene}.hud-meter")
        <!-- Recent crashes -->
        <div class="history-wrap" aria-label="Recent crashes">
            <div class="history-label">Recent crashes</div>
            <div id="history" class="history"></div>
            {{-- Round status sits under the rail, clear of the scene's hero. --}}
            <div id="state" class="state">{{ $sceneDef['text']['awaiting'] }}</div>
            @includeIf("crash.scenes.{$scene}.hud-warnings")
        </div>

        <!-- State + multiplier with cockpit gauges flanking the readout -->
        <div class="center">
            <div class="center-row">
                @includeIf("crash.scenes.{$scene}.hud-left")
                <div id="multiplier" class="multiplier">1.00x</div>

                @includeIf("crash.scenes.{$scene}.hud-right")
            </div>
        </div>

        @includeIf("crash.scenes.{$scene}.hud-extra")
        <!-- Cash out (legacy visual demo — hidden when embedded; replaced by .operator-panel) -->
        <div class="cashout-wrap">
            <button id="cashout" class="cashout" disabled>
                CASH OUT
                <small id="cashoutHint">awaiting dive</small>
            </button>
        </div>

        <!-- Operator HUD bar — slim, single-row. Buttons here do NOT call /v1/bets
             directly; they postMessage their intent to the parent operator page,
             which signs and forwards to Orange. -->
        <div class="operator-panel" id="op-panel" data-mode="place" aria-hidden="true">
            <div class="op-id">
                <span class="op-name" id="op-player">—</span>
                <span class="op-balance" id="op-balance">—</span>
            </div>

            <span class="op-sep"></span>

            <span class="op-phase" id="op-phase" data-phase="waiting">waiting</span>

            <span class="op-sep"></span>

            {{-- Bet slots: each its own stake, auto-cashout, auto-bet and bet. The second
                 is offered when the page hosting the game takes two bets a round
                 (player_info.bet_slots), like Aviator's second bet panel. --}}
            <div class="op-slots">
                @foreach ([0, 1] as $slot)
                <div class="op-bet-row op-slot" data-slot="{{ $slot }}" data-mode="place"@if ($slot === 1) hidden @endif>
                    {{-- Each field says what it is above it. data-mode: place → placed (bet
                         in, waiting for the dive) → cashout (diving) → cashed (cashed out). --}}
                    <div class="op-field">
                        <span class="op-cap">Bet <span class="op-cap-currency"></span></span>
                        <div class="op-stepper">
                            <button class="op-step op-stake-down" type="button" aria-label="Decrease stake{{ $slot ? ' (bet 2)' : '' }}">−</button>
                            <input class="op-input op-stake" data-kind="stake" type="number" step="0.01" min="0.01" value="10.00" inputmode="decimal" aria-label="Stake{{ $slot ? ' (bet 2)' : '' }}">
                            <button class="op-step op-stake-up" type="button" aria-label="Increase stake{{ $slot ? ' (bet 2)' : '' }}">+</button>
                        </div>
                    </div>
                    {{-- Auto cash-out: the switch turns it on and off; − / + or typing a value turns it on. --}}
                    <div class="op-field op-field-auto" data-on="false">
                        <button class="op-cap op-switch" type="button" role="switch" aria-checked="false"
                                aria-label="Auto cash out{{ $slot ? ' (bet 2)' : '' }}">
                            <i class="op-switch-track" aria-hidden="true"><i></i></i>
                            <span class="op-cap-text" aria-hidden="true">Auto cash out</span>
                            <b class="op-cap-state" aria-hidden="true">Off</b>
                        </button>
                        <div class="op-stepper op-stepper-auto">
                            <button class="op-step op-auto-down" type="button" aria-label="Decrease auto cash out{{ $slot ? ' (bet 2)' : '' }}">−</button>
                            <input class="op-input op-auto" data-kind="auto" type="number" step="0.01" min="1.01" placeholder="off" inputmode="decimal" aria-label="Auto cash out at{{ $slot ? ' (bet 2)' : '' }}">
                            <button class="op-step op-auto-up" type="button" aria-label="Increase auto cash out{{ $slot ? ' (bet 2)' : '' }}">+</button>
                        </div>
                    </div>
                    <button class="op-btn op-autobet-btn" type="button"
                            data-active="false" aria-pressed="false" aria-haspopup="dialog"
                            title="Auto bet: place this bet for a number of rounds">
                        <span class="op-autobet-label-full">AUTO BET</span>
                        <span class="op-autobet-label-short">AUTO</span>
                        <span class="op-autobet-count"></span>
                        <span class="op-autobet-dot" aria-hidden="true"></span>
                    </button>
                    {{-- Place Bet, Cash Out and the cashed-out result share one spot so the
                         button the player needs is always under the cursor when the round flips. --}}
                    <div class="op-action">
                        <button class="op-btn op-bet-btn" disabled>Place Bet</button>
                        <button class="op-btn op-cashout-btn" disabled>
                            <span class="op-cashout-main">Cash Out <span class="op-cashout-x"></span></span>
                            <span class="op-cashout-auto"></span>
                        </button>
                        <div class="op-cashed" role="status">
                            <span class="op-cashed-label">Cashed out</span>
                            <b class="op-cashed-x"></b>
                            <span class="op-cashed-win"></span>
                        </div>
                    </div>
                    @if ($slot === 0)
                    <button class="op-slot-toggle op-add-slot" type="button" title="Add a second bet" aria-label="Add a second bet" hidden>+</button>
                    @else
                    <button class="op-slot-toggle op-remove-slot" type="button" title="Remove the second bet" aria-label="Remove the second bet">−</button>
                    @endif
                </div>
                @endforeach
            </div>

            <div class="op-spacer"></div>

            <div id="op-last" class="op-last">
                <span class="op-last-label">Last</span>
                <span class="op-last-mult" id="op-last-mult">—</span>
                <span class="op-last-payout" id="op-last-payout">—</span>
            </div>

            <div id="op-status" class="op-status">connecting…</div>
        </div>

        <!-- Implode sequence layers -->
        <div class="shockwave"></div>
        <div class="crash-flash"></div>
        <div id="debris" class="crash-debris"></div>
        @includeIf("crash.scenes.{$scene}.crash-layers")
        <div class="crash-banner"><b></b><span>{{ $sceneDef['text']['banner'] }}</span><b></b></div>

        <!-- Activity log -->
        <div id="log" class="log"></div>
    </div>

    {{-- Each illustrated skin's own sounds (used by the audio engine below while that skin is on). --}}
    @includeIf("crash.scenes.{$scene}.sounds")
    <script type="module">
        const $stage = document.getElementById('stage');
        const $mult = document.getElementById('multiplier');
        const $state = document.getElementById('state');
        const $log = document.getElementById('log');
        if (new URLSearchParams(location.search).has('debug')) document.body.classList.add('debug');

        // The scene's words (scenes/{scene}/scene.php: the round, how it rises and is lost,
        // the status line) and its scenery (scenes/{scene}/script.blade.php), which defines
        // `scene`: the page calls scene.frame(m) every animation frame of a round,
        // scene.update(m) with each multiplier it shows, scene.crashed(point) when the round
        // ends and scene.reset() before the next.
        const TEXT = @json($sceneDef['text']);
        @include("crash.scenes.{$scene}.script")
        const $cashout = document.getElementById('cashout');
        const $cashoutHint = document.getElementById('cashoutHint');

        // 24 hull fragments — preset random vectors, only animated when .crashed fires
        const debrisHost = document.getElementById('debris');
        function rebuildDebris() {
            debrisHost.innerHTML = '';
            for (let i = 0; i < 24; i++) {
                const f = document.createElement('div');
                f.className = 'frag';
                const angle = (i / 24) * Math.PI * 2 + (Math.random() - 0.5) * 0.5;
                const dist = 280 + Math.random() * 320;
                f.style.setProperty('--dx', Math.cos(angle) * dist + 'px');
                f.style.setProperty('--dy', Math.sin(angle) * dist + 'px');
                f.style.setProperty('--d', (Math.random() * 0.18) + 's');
                f.style.width = f.style.height = (3 + Math.random() * 8) + 'px';
                debrisHost.appendChild(f);
            }
        }
        rebuildDebris();

        // ---------- Recent crash history (top chip rail) ----------
        const HISTORY_MAX = 8;
        const $history = document.getElementById('history');
        // Newest first: { uuid, cp }. A chip with a round id opens that round's details.
        const UUID = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i;
        const history = @json($recentCrashes ?? []).map((r) => ({ uuid: r.round_uuid, cp: r.crash_point }));

        function tierClassFor(cp) {
            if (cp >= 10) return 'tier-epic';
            if (cp >= 3)  return 'tier-high';
            if (cp >= 1.5) return 'tier-mid';
            return 'tier-low';
        }
        function renderHistory(highlightFirst = false) {
            $history.innerHTML = history.map(({ uuid, cp }, i) => {
                const cls = `history-chip ${tierClassFor(cp)}${highlightFirst && i === 0 ? ' is-new' : ''}`;
                return UUID.test(uuid ?? '')
                    ? `<button type="button" class="${cls}" data-round="${uuid}" title="Round details">${cp.toFixed(2)}x</button>`
                    : `<span class="${cls}">${cp.toFixed(2)}x</span>`;
            }).join('');
            fitHistory();
        }
        $history.addEventListener('click', (e) => {
            const chip = e.target.closest('[data-round]');
            if (chip) sheet.openRound(chip.dataset.round);
        });
        // Only whole chips: the rail stays clear of the buttons at the top right, and a
        // chip that doesn't fit is left out rather than cut in half. (Offsets, not rects:
        // a new chip is still popping in, scaled.)
        function fitHistory() {
            const chips = [...$history.children];
            chips.forEach((c) => { c.style.display = ''; });
            const edge = $history.offsetLeft + $history.clientWidth - parseFloat(getComputedStyle($history).paddingRight);
            chips.forEach((c) => { if (c.offsetLeft + c.offsetWidth > edge + 0.5) c.style.display = 'none'; });
        }
        window.addEventListener('resize', fitHistory);
        document.fonts?.ready.then(fitHistory);
        function pushHistory(cp, uuid) {
            history.unshift({ uuid, cp });
            if (history.length > HISTORY_MAX) history.length = HISTORY_MAX;
            renderHistory(true);
        }
        renderHistory();

        // The rail keeps clear of the top-right buttons, whichever of them are shown:
        // their row's width plus its margin, on both sides (the rail is centred).
        const $controls = document.getElementById('top-controls');
        new ResizeObserver(() => {
            const r = $controls.getBoundingClientRect();
            const reserve = r.width + 2 * (window.innerWidth - r.right);
            document.documentElement.style.setProperty('--ctl-reserve', `${Math.ceil(reserve)}px`);
            fitHistory();
        }).observe($controls);

        // ---------- Round details and My bets (one dialog over the game) ----------
        // Round details come from the public GET /v1/rounds/{uuid}; My bets from the page
        // hosting the game (bet_history → bet_history_result), wired by the operator bridge.
        const sheet = (function roundSheet() {
            const ROUNDS_URL = @json(url('/v1/rounds'));
            const $sheet = document.getElementById('sheet');
            const $card  = $sheet.querySelector('.sheet-card');
            const $title = document.getElementById('sheet-title');
            const $pill  = document.getElementById('sheet-pill');
            const $time  = document.getElementById('sheet-time');
            const $body  = document.getElementById('sheet-body');
            const $back  = document.getElementById('sheet-back');
            const $close = document.getElementById('sheet-close');

            const settledRounds = new Map(); // uuid → details; a settled round no longer changes
            let view = null;                 // 'round' | 'bets' while open
            let token = 0;                   // answers for a view that has since changed are dropped
            let lastFocus = null;
            let bets = { request: null, list: null, pending: false, timer: null, money: (v) => String(v) };

            function el(tag, attrs = {}, ...children) {
                const node = document.createElement(tag);
                for (const [k, v] of Object.entries(attrs)) {
                    if (v === null || v === undefined || v === false) continue;
                    if (k.startsWith('on')) node.addEventListener(k.slice(2), v);
                    else if (k === 'html') node.innerHTML = v; // only ever our own markup (icons)
                    else node.setAttribute(k, v === true ? '' : v);
                }
                node.append(...children.flat().filter((c) => c !== null && c !== undefined && c !== false));
                return node;
            }
            const note = (...children) => el('p', { class: 'sheet-note' }, ...children);
            const x = (cp) => `${Number(cp).toFixed(2)}x`;
            const chip = (cp) => el('span', { class: `x-chip ${tierClassFor(Number(cp))}` }, x(cp));
            const clock = (iso, withDate = false) => {
                const d = iso ? new Date(iso) : null;
                if (!d || Number.isNaN(d.getTime())) return '';
                const time = d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
                return withDate ? `${d.toLocaleDateString([], { day: 'numeric', month: 'short' })} ${time}` : time;
            };
            const isToday = (iso) => new Date(iso).toDateString() === new Date().toDateString();

            function show(name, { back = false } = {}) {
                if ($sheet.hidden) {
                    lastFocus = document.activeElement;
                    $sheet.hidden = false;
                    $close.focus({ preventScroll: true });
                }
                view = name;
                $card.dataset.view = name;
                $back.hidden = !back;
                return ++token;
            }
            function close() {
                if ($sheet.hidden) return;
                $sheet.hidden = true;
                view = null;
                token++;
                lastFocus?.focus?.({ preventScroll: true });
            }
            function head(title, cp = null, time = '') {
                $title.textContent = title;
                $pill.hidden = cp === null;
                if (cp !== null) $pill.replaceChildren(chip(cp));
                $time.textContent = time;
            }

            $close.addEventListener('click', close);
            $back.addEventListener('click', () => openBets());
            $sheet.addEventListener('click', (e) => { if (e.target === $sheet) close(); });
            document.addEventListener('keydown', (e) => { if (e.key === 'Escape') close(); });

            // ----- Round details -----
            const ICONS = {
                server: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3.5" width="18" height="7" rx="1.5"/><rect x="3" y="13.5" width="18" height="7" rx="1.5"/><path d="M7 7h.01M7 17h.01M11 7h6M11 17h6"/></svg>',
                lock:   '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="4.5" y="10.5" width="15" height="10" rx="2"/><path d="M8 10.5V7.5a4 4 0 0 1 8 0v3M12 14.5v2.5"/></svg>',
                client: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="4.5" width="16" height="11" rx="1.5"/><path d="M2 19.5h20M9 15.5l-.5 4M15 15.5l.5 4"/></svg>',
                hash:   '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2.8 4.5 6v5.5c0 4.6 3.1 8.4 7.5 9.7 4.4-1.3 7.5-5.1 7.5-9.7V6L12 2.8Z"/><path d="M10 9.5 9 15.5M15 9.5l-1 6M8.5 11.5h7.5M8 13.5h7.5"/></svg>',
            };
            function svgIcon(name) {
                const t = document.createElement('template');
                t.innerHTML = ICONS[name];
                const svg = t.content.firstElementChild;
                svg.setAttribute('aria-hidden', 'true');
                return svg;
            }
            function section(icon, label, sub, value) {
                return el('div', { class: 'pf-section' },
                    svgIcon(icon),
                    el('div', { class: 'pf-label' }, label),
                    el('div', { class: 'pf-sub' }, sub),
                    value);
            }
            const value = (text, pending = false) => el('div', { class: `pf-value${pending ? ' is-pending' : ''}` }, text);

            // The same maths as the server (ProvablyFairService), redone here from the
            // revealed seed. Needs WebCrypto, which browsers only offer in secure contexts
            // (not inside an http casino page): without it there's no check, not a failed one.
            function crashFromHmac(hmac, divisor) {
                if (parseInt(hmac.slice(0, 8), 16) % divisor === 0) return 1;
                const h = parseInt(hmac.slice(0, 13), 16);
                const e = 2 ** 52;
                return Math.max(1, Math.floor((100 * e - h) / (e - h)) / 100);
            }
            async function checkInBrowser(f) {
                const subtle = window.crypto?.subtle;
                if (!subtle || !f.server_seed || !f.verification) return null;
                try {
                    const enc = new TextEncoder();
                    const hex = (buf) => Array.from(new Uint8Array(buf), (b) => b.toString(16).padStart(2, '0')).join('');
                    const key = await subtle.importKey('raw', enc.encode(f.server_seed), { name: 'HMAC', hash: 'SHA-256' }, false, ['sign']);
                    const [hash, hmac] = await Promise.all([
                        subtle.digest('SHA-256', enc.encode(f.server_seed)).then(hex),
                        subtle.sign('HMAC', key, enc.encode(`${f.client_seed}:${f.nonce}`)).then(hex),
                    ]);
                    return hash === f.server_seed_hash
                        && hmac === f.verification.hmac
                        && crashFromHmac(hmac, f.verification.house_edge_divisor) === Number(f.verification.crash_point);
                } catch (_) {
                    return null;
                }
            }

            function renderRound(d, my) {
                const f = d.fairness ?? {};
                const v = f.verification;
                const cp = d.result?.crash_point ?? null;
                head(`Round ${d.round_id}`, cp, clock(d.crashed_at ?? d.started_at ?? d.betting_opened_at, !isToday(d.crashed_at ?? d.betting_opened_at ?? Date.now())));

                const hmac = v ? el('div', { class: 'pf-value' }, el('mark', {}, v.hmac.slice(0, 13)), v.hmac.slice(13)) : value('Worked out once the round is over', true);
                const parts = [
                    section('server', 'Server seed', f.server_seed ? 'Made by us before the round, revealed after it' : 'Made by us before the round, revealed once it is over',
                        f.server_seed ? value(f.server_seed) : value('Revealed once the round is over', true)),
                    section('lock', 'Server seed SHA-256', 'Published before betting opened, so the seed could not change',
                        value(f.server_seed_hash ?? '—')),
                    section('client', 'Client seed : nonce', 'Public, the same for every player in the round',
                        value(`${f.client_seed}:${f.nonce}`)),
                    section('hash', 'HMAC-SHA256', 'The client seed and nonce, keyed with the server seed. This is the round result', hmac),
                ];
                if (v) {
                    parts.push(el('dl', { class: 'pf-calc' },
                        el('div', {}, el('dt', {}, 'Hex'), el('dd', {}, v.hex)),
                        el('div', {}, el('dt', {}, 'Decimal'), el('dd', {}, String(v.decimal))),
                        el('div', {}, el('dt', {}, 'Result'), el('dd', {}, x(v.crash_point)))));
                    if (v.instant_crash) {
                        parts.push(el('p', { class: 'pf-edge' },
                            `The first 8 hex digits (${v.hmac.slice(0, 8)}) are a multiple of ${v.house_edge_divisor}: the round ends at 1.00x. That is the house edge.`));
                    }
                    const $check = el('p', { class: 'pf-check', 'aria-live': 'polite' });
                    parts.push($check);
                    checkInBrowser(f).then((ok) => {
                        if (my !== token || ok === null) return;
                        $check.classList.add(ok ? 'ok' : 'bad');
                        $check.textContent = ok
                            ? '✓ Checked in your browser: the seed matches the hash published before the round, and gives this result.'
                            : '✗ Your browser could not confirm this result from the seeds above.';
                    });
                }
                parts.push(el('details', { class: 'pf-how' },
                    el('summary', {}, 'How is the result worked out?'),
                    el('ol', {},
                        el('li', {}, 'Before betting opens we publish the SHA-256 of the round’s server seed, so the seed (and the result) is fixed before anyone bets.'),
                        el('li', {}, 'Once the round is over we reveal the seed: its SHA-256 matches the published one.'),
                        el('li', {}, 'HMAC-SHA256 of “client seed:nonce”, keyed with the server seed, gives the hash above.'),
                        el('li', {}, `If its first 8 hex digits are a multiple of ${v?.house_edge_divisor ?? 'N'} (the house edge), the round ends at 1.00x.`),
                        el('li', {}, 'Otherwise its first 13 hex digits, as a decimal h, give the result: floor((100 × 2^52 − h) / (2^52 − h)) / 100.'))));
                $body.replaceChildren(...parts);
            }

            async function openRound(uuid, { back = false } = {}) {
                if (!UUID.test(uuid ?? '')) return;
                const my = show('round', { back });
                const cached = settledRounds.get(uuid);
                if (cached) return renderRound(cached, my);

                head('Round');
                $body.replaceChildren(note('Loading…'));
                let d = null;
                try {
                    const r = await fetch(`${ROUNDS_URL}/${uuid}`, { headers: { Accept: 'application/json' } });
                    d = r.ok ? await r.json() : null;
                } catch (_) { /* offline: said below */ }
                if (my !== token) return;
                if (!d) {
                    $body.replaceChildren(note('Couldn’t load this round.', el('button', { class: 'sheet-retry', type: 'button', onclick: () => openRound(uuid, { back }) }, 'Retry')));
                    return;
                }
                if (d.state === 'settled' && d.fairness?.server_seed) settledRounds.set(uuid, d);
                renderRound(d, my);
            }

            // ----- My bets -----
            function renderBets() {
                const list = bets.list ?? [];
                if (list.length === 0) {
                    $body.replaceChildren(note('No bets yet. Your bets in this game will show up here.'));
                    return;
                }
                const currency = list[0].currency;
                const one = list.every((b) => b.currency === currency);
                const amount = (b, v) => one ? bets.money(v) : `${bets.money(v)} ${b.currency}`;
                const chevron = '<svg viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4.5 2.5 8 6l-3.5 3.5"/></svg>';
                const rows = list.map((b) => {
                    const won = b.status === 'won';
                    const cashout = won ? chip(b.multiplier)
                        : b.status === 'placed' ? el('span', { class: 'is-muted' }, 'in play')
                        : b.status === 'refunded' ? el('span', { class: 'is-muted' }, 'refunded')
                        : el('span', { class: 'is-muted' }, '—');
                    const win = won ? el('span', { class: 'is-win' }, amount(b, b.payout_amount))
                        : el('span', { class: 'is-muted' }, b.status === 'lost' ? amount(b, '0') : '—');
                    const when = isToday(b.placed_at) ? el('span', { class: 'bets-when' }, clock(b.placed_at))
                        : el('span', { class: 'bets-when' }, clock(b.placed_at), el('small', {}, new Date(b.placed_at).toLocaleDateString([], { day: 'numeric', month: 'short' })));
                    const opens = UUID.test(b.round_uuid ?? '');
                    return el('button', {
                        type: 'button',
                        class: `bets-row${won ? ' is-won' : ''}`,
                        disabled: !opens,
                        title: opens ? 'Round details' : null,
                        onclick: opens ? () => openRound(b.round_uuid, { back: true }) : null,
                    }, when, el('span', {}, amount(b, b.amount)), el('span', {}, cashout), win, el('span', { html: opens ? chevron : '' }));
                });
                $body.replaceChildren(el('div', { class: 'bets-list' },
                    el('div', { class: 'bets-row is-head', 'aria-hidden': 'true' },
                        el('span', {}, 'Time'), el('span', {}, one ? `Bet ${currency}` : 'Bet'), el('span', {}, 'Cash out'), el('span', {}, one ? `Win ${currency}` : 'Win'), el('span')),
                    rows));
            }
            function failBets() {
                $body.replaceChildren(note('Couldn’t load your bets.', el('button', { class: 'sheet-retry', type: 'button', onclick: () => { $body.replaceChildren(note('Loading…')); askForBets(); } }, 'Retry')));
            }
            function askForBets() {
                if (!bets.request) return;
                bets.pending = true;
                clearTimeout(bets.timer);
                bets.timer = setTimeout(() => {
                    bets.pending = false;
                    if (view === 'bets' && bets.list === null) failBets();
                }, 8000);
                bets.request();
            }
            function openBets() {
                if (!bets.request) return;
                show('bets');
                head('My bets');
                if (bets.list === null) $body.replaceChildren(note('Loading…'));
                else renderBets();
                askForBets();
            }

            return {
                openRound,
                openBets,
                close,
                el,
                /** Another panel in the same dialog (Rules, Auto bet): its title and content. */
                panel(name, title, ...content) {
                    show(name);
                    head(title);
                    $body.replaceChildren(...content);
                },
                /** The host answers bet_history: offer My bets. request() asks it for them. */
                enableBets({ request, money }) {
                    bets.request = request;
                    if (money) bets.money = money;
                },
                /** A bet_history_result from the host. */
                receiveBets(payload) {
                    bets.pending = false;
                    clearTimeout(bets.timer);
                    if (payload?.success) bets.list = Array.isArray(payload.bets) ? payload.bets : [];
                    if (view !== 'bets') return;
                    if (payload?.success || bets.list !== null) renderBets();
                    else failBets();
                },
                /** Something changed the player's bets (a bet, a cashout, a round over). */
                betsChanged() {
                    if (view === 'bets' && !bets.pending) askForBets();
                },
            };
        })();

        // ---------- Rules: how to play, the bet limits and the RTP ----------
        // The limits are the player's own, from the page hosting the game (player_info.limits,
        // passed on by the operator bridge); without them the rules say the casino sets them.
        const rules = (function rulesPanel() {
            const RTP = @json($rtp ?? null);
            const BETTING_SECONDS = @json((int) config('crash.betting_seconds', 10));
            const opts = { limits: null, currency: '', twoBets: false, money: (v) => Number(v).toFixed(2) };
            const { el } = sheet;

            function open() {
                const { limits, currency, money } = opts;
                // "500000.00" → "500,000.00": a max win reads at a glance.
                const grouped = (v) => money(v).replace(/^(-?\d+)/, (n) => n.replace(/\B(?=(\d{3})+$)/g, ','));
                const amount = (v, none) => (v === null || v === undefined ? none : `${grouped(v)} ${currency}`.trim());
                const pct = RTP === null ? null : `${(Number(RTP) * 100).toFixed(2)}%`;
                const h = (text) => el('h3', {}, text);
                const p = (...children) => el('p', {}, ...children);

                sheet.panel('rules', 'Rules', el('div', { class: 'rules' },
                    h('How to play'),
                    el('ol', {},
                        el('li', {}, `Place your bet while betting is open: ${BETTING_SECONDS} seconds before each ${TEXT.round}.`),
                        el('li', {}, `${TEXT.rise} and the multiplier climbs from 1.00×.`),
                        el('li', {}, `Cash out at any moment of the ${TEXT.round}: you win your bet × the multiplier at that moment.`),
                        el('li', {}, `If ${TEXT.lose} before you cash out, the bet is lost.`)),
                    h('Auto cash out'),
                    p(`Switch it on and set a multiplier (1.01× or more). The bet is cashed out the moment the ${TEXT.round} reaches it, by our server, so it works even if your connection drops. You can still cash out sooner yourself.`),
                    h('Auto bet'),
                    p('Places the same bet, with its auto cash out, for the number of rounds you choose, and shows how many it has placed (3/10). It stops after the last one, when you press it again, or when a bet is refused, for example when your balance runs out. While it runs, its bet and auto cash out are locked.'),
                    opts.twoBets ? h('Two bets') : null,
                    opts.twoBets ? p('+ adds a second bet in the same round, with its own amount, auto cash out and auto bet.') : null,
                    h('Limits'),
                    limits
                        ? el('dl', { class: 'rules-grid' },
                            el('div', {}, el('dt', {}, 'Min bet'), el('dd', {}, amount(limits.min_bet, `0.01 ${currency}`.trim()))),
                            el('div', {}, el('dt', {}, 'Max bet'), el('dd', {}, amount(limits.max_bet, 'No limit'))),
                            el('div', {}, el('dt', {}, 'Max win'), el('dd', {}, amount(limits.max_win, 'No limit'))))
                        : p('Your casino sets the minimum and maximum bet; a bet outside them is refused, with the reason.'),
                    limits?.max_win ? el('p', { class: 'is-muted' }, 'Max win is per bet: a bet that reaches it is cashed out automatically.') : null,
                    pct ? h('Return to player') : null,
                    pct ? p(`The game’s theoretical return to player (RTP) is ${pct}.`) : null,
                    h('Provably fair'),
                    p('Every result is fixed before betting opens: we publish the SHA-256 of the round’s server seed in advance and reveal the seed once the round is over. Tap a past result at the top of the screen to check it.'),
                    h('If you lose connection'),
                    p(`A bet with auto cash out is still cashed out at its multiplier. A bet without it stays in the round, and is lost if ${TEXT.lose} before you cash out.`),
                    // What the platform does (crash:loop's orphan rounds, the stake confirmation,
                    // wallet:reconcile): keep this in step with them.
                    h('Malfunction'),
                    el('ul', {},
                        el('li', {}, 'If a fault on our side cuts a round short, the round is cancelled and every bet still in it is refunded in full. Bets already cashed out keep their win.'),
                        el('li', {}, 'A bet only counts once your casino confirms its stake. If that can’t be confirmed, the bet is cancelled and anything taken for it is returned.'),
                        el('li', {}, 'If paying a win to your casino fails, it is sent again automatically.'))));
            }
            document.getElementById('rules-btn')?.addEventListener('click', open);

            return {
                /** What the bridge learns from the host: the player's limits and currency, two bets or not. */
                configure(next) {
                    Object.assign(opts, next);
                },
            };
        })();

        // ---------- All bets: every player's bet in the round, live ----------
        // Rows come from round.bets broadcasts. A round the page joins late, and the
        // previous one when its tab opens, are fetched once (GET /v1/rounds/{uuid}/bets).
        const liveBets = (function livePanel() {
            const $panel = document.getElementById('live-bets');
            const $btn = document.getElementById('live-btn');
            if (!$panel || !$btn) return { seen() {}, update() {}, crashed() {}, mine() {} };

            const START = @json($liveRounds ?? null);
            const ROUNDS_URL = @json(url('/v1/rounds'));
            const MAX_ROWS = 100; // the biggest bets; the counts and totals cover all of them
            const OPEN_KEY = 'orange.ui.livebets';
            const $rows = document.getElementById('lb-rows');
            const $count = document.getElementById('lb-count');
            const $total = document.getElementById('lb-total');
            const $totalLabel = document.getElementById('lb-total-label');
            const $progress = document.getElementById('lb-progress');
            const $result = document.getElementById('lb-result');
            const $headBet = document.getElementById('lb-head-bet');
            const $headWin = document.getElementById('lb-head-win');
            const $tabs = [...$panel.querySelectorAll('.lb-tab')];
            const docked = window.matchMedia('(min-width: 1100px) and (min-height: 600px)');

            const rounds = new Map(); // uuid → { rows: Map<bet id, row>, crashPoint, crashed, loaded, loading }
            let current = null;
            let previous = null;
            let tab = 'current';
            const mine = new Set();   // this player's bet ids
            const nodes = new Map();  // bet id → its row element, reused across renders
            const flashes = new Set(); // bet ids just cashed out
            let frame = 0;

            // Money with two decimals, cut (never rounded up), and thousands separators.
            function fmt(value) {
                const s = typeof value === 'number' ? value.toFixed(2) : String(value);
                const m = /^(-?)(\d+)(?:\.(\d*))?$/.exec(s);
                if (!m) return s;
                return `${m[1]}${m[2].replace(/\B(?=(\d{3})+(?!\d))/g, ',')}.${((m[3] || '') + '00').slice(0, 2)}`;
            }
            const hue = (s) => [...s].reduce((h, c) => (h * 31 + c.charCodeAt(0)) % 360, 7);

            function round(uuid) {
                if (!rounds.has(uuid)) rounds.set(uuid, { rows: new Map(), crashPoint: null, crashed: false, loaded: false, loading: false });
                return rounds.get(uuid);
            }
            function merge(r, row) {
                const before = r.rows.get(row.id);
                if (before?.multiplier && !row.multiplier) return; // a late copy from before the cash-out
                if (before && !before.multiplier && row.multiplier) flashes.add(row.id);
                r.rows.set(row.id, row);
            }
            async function load(uuid) {
                const r = round(uuid);
                if (r.loaded || r.loading) return;
                r.loading = true;
                try {
                    const res = await fetch(`${ROUNDS_URL}/${uuid}/bets`, { headers: { Accept: 'application/json' } });
                    if (res.ok) {
                        const data = await res.json();
                        for (const row of data.bets ?? []) merge(r, row);
                        if (data.state === 'crashed' || data.state === 'settled') r.crashed = true;
                        r.loaded = true;
                    }
                } catch (_) { /* offline: tried again the next time it's shown */ }
                r.loading = false;
                flashes.clear();
                schedule();
            }

            // A round the page hadn't seen is the new current one; the current one becomes
            // the previous. `fresh`: its betting just opened, so there's nothing to fetch.
            function seen(uuid, { fresh = false } = {}) {
                if (!UUID.test(uuid ?? '') || uuid === current || uuid === previous) return;
                if (current) previous = current; // else the page's start value stays
                current = uuid;
                if (fresh) round(uuid).loaded = true;
                for (const id of rounds.keys()) {
                    if (id === current || id === previous) continue;
                    for (const betId of rounds.get(id).rows.keys()) nodes.delete(betId);
                    rounds.delete(id);
                }
                schedule();
            }

            function schedule() {
                if (!frame) frame = requestAnimationFrame(render);
            }
            function rowNode(b, crashed, one) {
                let n = nodes.get(b.id);
                if (!n) {
                    n = document.createElement('div');
                    n.setAttribute('role', 'listitem');
                    n.innerHTML = '<span class="lb-player"><i></i><span></span></span><span></span><span class="lb-x"></span><span class="lb-win"></span>';
                    const [$who, , , ] = n.children;
                    $who.firstChild.textContent = (b.player || '?').slice(0, 1).toUpperCase();
                    $who.firstChild.style.setProperty('--h', hue(b.player || ''));
                    $who.lastChild.textContent = b.player;
                    nodes.set(b.id, n);
                }
                const won = !!b.multiplier;
                const key = [won, crashed, mine.has(b.id), one].join('|');
                if (n.dataset.key !== key) {
                    n.dataset.key = key;
                    n.className = `lb-row${won ? ' is-won' : ''}${crashed && !won ? ' is-lost' : ''}${mine.has(b.id) ? ' is-mine' : ''}`;
                    const [, $bet, $x, $win] = n.children;
                    $bet.textContent = one === null ? `${fmt(b.amount)} ${b.currency}` : fmt(b.amount);
                    $x.replaceChildren(...(won ? [Object.assign(document.createElement('span'), { className: `x-chip ${tierClassFor(Number(b.multiplier))}`, textContent: `${Number(b.multiplier).toFixed(2)}x` })] : []));
                    $win.textContent = won ? (one === null ? `${fmt(b.payout)} ${b.currency}` : fmt(b.payout)) : '';
                }
                if (flashes.delete(b.id)) {
                    n.classList.remove('flash');
                    void n.offsetWidth; // restart the animation
                    n.classList.add('flash');
                }
                return n;
            }
            function render() {
                frame = 0;
                if (!$panel.hidden) renderPanel();
                renderMini();
            }
            function renderPanel() {
                const uuid = tab === 'current' ? current : previous;
                const r = uuid ? round(uuid) : null;
                if (r && !r.loaded) load(uuid);

                const list = r ? [...r.rows.values()] : [];
                list.sort((a, b) => Number(b.amount) - Number(a.amount) || a.id - b.id);
                const won = list.filter((b) => b.multiplier);
                const currencies = [...new Set(list.map((b) => b.currency))];
                const one = currencies.length <= 1 ? (currencies[0] ?? '') : null; // null: several
                const totals = new Map();
                for (const b of won) totals.set(b.currency, (totals.get(b.currency) ?? 0) + Number(b.payout));

                $count.textContent = `${won.length}/${list.length}`;
                $progress.style.width = list.length ? `${(won.length / list.length) * 100}%` : '0';
                $headBet.textContent = one ? `Bet ${one}` : 'Bet';
                $headWin.textContent = one ? `Win ${one}` : 'Win';
                $total.textContent = one !== null
                    ? fmt(totals.get(one) ?? 0)
                    : [...totals].map(([c, v]) => `${fmt(v)} ${c}`).join(' · ') || '0.00';
                $totalLabel.textContent = one ? `Total win ${one}` : 'Total win';
                const cp = tab === 'previous' ? r?.crashPoint ?? history.find((h) => h.uuid === uuid)?.cp : null;
                $result.hidden = !cp;
                if (cp) {
                    $result.replaceChildren(Object.assign(document.createElement('span'), { className: `x-chip ${tierClassFor(cp)}`, textContent: `${Number(cp).toFixed(2)}x` }),
                        Object.assign(document.createElement('small'), { textContent: 'Result' }));
                }

                const children = list.slice(0, MAX_ROWS).map((b) => rowNode(b, !!r?.crashed, one));
                if (list.length === 0) {
                    children.push(Object.assign(document.createElement('div'), {
                        className: 'lb-note',
                        textContent: r && !r.loaded ? 'Loading…' : tab === 'current' ? 'No bets yet in this round.' : 'No bets in the previous round.',
                    }));
                } else if (list.length > MAX_ROWS) {
                    children.push(Object.assign(document.createElement('div'), { className: 'lb-note', textContent: `+${list.length - MAX_ROWS} smaller bets` }));
                }
                $rows.replaceChildren(...children);
            }

            // Where the panel isn't docked: the current round's bets as circles down the
            // right edge (the player's own first, then the biggest stakes; as many as fit,
            // then "+N"). One that cashes out steps inward, its multiplier beside it.
            const $mini = document.getElementById('lb-mini');
            const $miniMore = document.getElementById('lb-mini-more');
            const DOT = 28;
            const DOT_GAP = 6;
            const DOT_SLOTS = 9; // at most 8 circles and "+N": a light touch, the panel has the rest
            const dots = new Map(); // bet id → its circle
            function renderMini() {
                const shownMini = !docked.matches && $panel.hidden;
                $mini.hidden = !shownMini;
                if (!shownMini) return;
                const r = current ? round(current) : null;
                if (r && !r.loaded) load(current);

                const list = r ? [...r.rows.values()] : [];
                list.sort((a, b) => Number(mine.has(b.id)) - Number(mine.has(a.id)) || Number(b.amount) - Number(a.amount) || a.id - b.id);
                const fits = Math.max(0, Math.min(DOT_SLOTS, Math.floor(($mini.clientHeight + DOT_GAP) / (DOT + DOT_GAP))));
                const shown = list.length > fits ? list.slice(0, Math.max(0, fits - 1)) : list;

                const keep = new Set();
                shown.forEach((b, i) => {
                    let n = dots.get(b.id);
                    if (!n) {
                        n = document.createElement('div');
                        n.className = 'lb-dot is-new';
                        n.innerHTML = '<i></i><b></b>';
                        n.firstChild.textContent = (b.player || '?').slice(0, 1).toUpperCase();
                        n.firstChild.style.setProperty('--h', hue(b.player || ''));
                        n.addEventListener('animationend', () => n.classList.remove('is-new'), { once: true });
                        $mini.insertBefore(n, $miniMore);
                        dots.set(b.id, n);
                    }
                    n.style.setProperty('--y', `${i * (DOT + DOT_GAP)}px`);
                    if (b.multiplier) n.lastChild.textContent = `${Number(b.multiplier).toFixed(2)}x`;
                    n.classList.toggle('is-out', !!b.multiplier);
                    n.classList.toggle('is-lost', !!r?.crashed && !b.multiplier);
                    n.classList.toggle('is-mine', mine.has(b.id));
                    keep.add(b.id);
                });
                for (const [id, n] of dots) {
                    if (keep.has(id)) continue;
                    dots.delete(id);
                    n.classList.add('is-gone');
                    setTimeout(() => n.remove(), 400);
                }
                $miniMore.hidden = list.length === shown.length;
                $miniMore.firstChild.textContent = `+${list.length - shown.length}`;
                $miniMore.style.setProperty('--y', `${shown.length * (DOT + DOT_GAP)}px`);
            }
            $mini.addEventListener('click', () => setOpen(true));
            window.addEventListener('resize', schedule);

            function setOpen(open, remember = false) {
                $panel.hidden = !open;
                $btn.setAttribute('aria-checked', String(open));
                // Only the docked panel's state is kept: over the game it starts closed.
                if (remember && docked.matches) {
                    try { localStorage.setItem(OPEN_KEY, open ? '1' : '0'); } catch (_) {}
                }
                schedule(); // the panel, or the circles in its place
            }
            function defaultOpen() {
                let stored = null;
                try { stored = localStorage.getItem(OPEN_KEY); } catch (_) {}
                return docked.matches && stored !== '0';
            }
            $btn.addEventListener('click', () => setOpen($panel.hidden, true));
            document.getElementById('live-close').addEventListener('click', () => setOpen(false, true));
            docked.addEventListener('change', () => setOpen(defaultOpen()));
            for (const $t of $tabs) {
                $t.addEventListener('click', () => {
                    tab = $t.dataset.tab;
                    for (const $o of $tabs) $o.setAttribute('aria-selected', String($o === $t));
                    $rows.scrollTop = 0;
                    schedule();
                });
            }

            if (UUID.test(START?.current ?? '')) current = START.current;
            if (UUID.test(START?.previous ?? '')) previous = START.previous;
            setOpen(defaultOpen());

            return {
                seen,
                /** A round.bets message. */
                update(e) {
                    seen(e.round_uuid);
                    if (e.round_uuid !== current && e.round_uuid !== previous) return;
                    const r = round(e.round_uuid);
                    for (const row of e.bets ?? []) merge(r, row);
                    schedule();
                },
                crashed(uuid, cp) {
                    if (uuid !== current && uuid !== previous) return;
                    const r = round(uuid);
                    r.crashed = true;
                    r.crashPoint = cp;
                    schedule();
                },
                /** The player's own bet, marked in the list. */
                mine(betId) {
                    mine.add(Number(betId));
                    schedule();
                },
            };
        })();

        const log = (msg) => {
            const line = document.createElement('div');
            line.textContent = `${new Date().toLocaleTimeString()}  ${msg}`;
            $log.prepend(line);
            while ($log.children.length > 6) $log.lastChild.remove();
        };

        // How much of the screen's bottom the multiplier row takes up: the diving
        // sub and the docked All bets panel stay clear of it (see the .sub-wrap diving
        // rules and .live-bets).
        const $center = document.querySelector('.center');
        const hudRoom = new ResizeObserver(() => {
            const room = $stage.getBoundingClientRect().bottom - $center.getBoundingClientRect().top;
            document.documentElement.style.setProperty('--hud-room', `${Math.round(room)}px`);
        });
        hudRoom.observe($stage);
        hudRoom.observe($center);

        let stageKey = '';
        function setStage(state) {
            stageKey = state.join(' ');
            $stage.classList.remove('waiting', 'betting', 'running', 'deep', 'abyss', 'critical', 'crashed');
            for (const s of state) $stage.classList.add(s);
        }

        // The descent runs on its own clock, not on the ticks: ticks carry multipliers
        // rounded to 0.01 (early in a dive several in a row repeat the same value)
        // and can arrive late, which made the scenery stutter. The clock computes
        // m = e^(g·t) every frame; ticks only keep t in step with the server.
        const descentClock = { g: @json((float) config('crash.growth_rate', 0.06)), start: 0, target: 0, synced: 0, on: false, raf: 0 };
        function descentFrame(now) {
            if (!descentClock.on) return;
            // No tick for a while (connection lost): hold still rather than run away.
            if (now - descentClock.synced > 2500) { descentClock.on = false; return; }
            // Ease toward the server's clock by at most 0.5 ms a frame (a ~3% speed
            // change nobody sees), never in one step.
            const gap = descentClock.target - descentClock.start;
            descentClock.start += Math.max(-0.5, Math.min(0.5, gap * 0.05));
            scene.frame(Math.exp(descentClock.g * Math.max(0, now - descentClock.start) / 1000));
            descentClock.raf = requestAnimationFrame(descentFrame);
        }
        function syncDescent(elapsedSeconds, growthRate) {
            if (growthRate > 0) descentClock.g = growthRate;
            const now = performance.now();
            const measured = now - Math.max(0, Number(elapsedSeconds) || 0) * 1000;
            descentClock.synced = now;
            if (!descentClock.on) {
                descentClock.start = descentClock.target = measured;
                descentClock.on = true;
                descentClock.raf = requestAnimationFrame(descentFrame);
                return;
            }
            // A gap of over a second (a device waking from sleep) is caught up at once;
            // otherwise the target is averaged so network jitter cancels out, and the
            // frames ease toward it.
            if (Math.abs(measured - descentClock.target) > 1000) {
                descentClock.start = descentClock.target = measured;
            } else {
                descentClock.target += (measured - descentClock.target) * 0.1;
            }
        }
        function stopDescent() {
            descentClock.on = false;
            cancelAnimationFrame(descentClock.raf);
        }

        function updateMultiplierUI(m) {
            $mult.textContent = m.toFixed(2) + 'x';
            scene.update(m);

            const tiers = ['running'];
            if (m >= 8)  tiers.push('critical');
            else if (m >= 3.5) tiers.push('abyss');
            else if (m >= 1.8) tiers.push('deep');
            // Runs every animation frame — only touch the stage classes on a tier change.
            if (tiers.join(' ') !== stageKey) setStage(tiers);
        }

        // ---------- Countdown helper (state text only) ----------
        let countdownTimer = null;
        function cancelCountdown() {
            if (countdownTimer) { clearInterval(countdownTimer); countdownTimer = null; }
        }
        function startCountdown(seconds, label, onZero) {
            cancelCountdown();
            let remaining = Math.max(0, Math.floor(seconds));
            const render = () => {
                if (remaining <= 0) {
                    cancelCountdown();
                    if (onZero) onZero();
                    return;
                }
                $state.textContent = `${label} ${remaining}s`;
                remaining--;
            };
            render();
            countdownTimer = setInterval(render, 1000);
        }

        // Ticks land every ~100 ms; glide the readout from the shown value to each
        // new tick over the measured interval. It never runs ahead of the last
        // tick, so it can't overshoot the crash point.
        const tween = { from: 1, to: 1, start: 0, dur: 100, lastAt: 0, raf: 0 };
        function tweenValue(now) {
            const k = Math.min(1, (now - tween.start) / tween.dur);
            return tween.from + (tween.to - tween.from) * k;
        }
        function tweenFrame(now) {
            updateMultiplierUI(tweenValue(now));
            tween.raf = now - tween.start < tween.dur ? requestAnimationFrame(tweenFrame) : 0;
        }
        function tweenMultiplier(m) {
            const now = performance.now();
            const shown = tween.lastAt ? tweenValue(now) : m;
            tween.dur = tween.lastAt ? Math.min(250, Math.max(60, now - tween.lastAt)) : 100;
            Object.assign(tween, { from: shown, to: m, start: now, lastAt: now });
            if (!tween.raf) tween.raf = requestAnimationFrame(tweenFrame);
        }
        function stopTween(m) {
            cancelAnimationFrame(tween.raf);
            Object.assign(tween, { from: m, to: m, lastAt: 0, raf: 0 });
        }

        function resetIdleVisuals() {
            stopTween(1);
            stopDescent();
            scene.reset(); // back to the start (the betting state's transition)
            rebuildDebris(); // fresh vectors for the next crash, built while nothing is animating
            $mult.textContent = '1.00x';
        }

        function attachEcho() {
            log('Echo ready, joining channel: ' + @json($roundChannel));
            window.Echo.channel(@json($roundChannel))
                .listen('.round.betting_opened', (e) => {
                    cancelCountdown();
                    setStage(['betting']);
                    resetIdleVisuals();
                    $cashout.disabled = true;
                    $cashoutHint.textContent = `awaiting ${TEXT.round}`;
                    log(`betting_opened ${e.round_uuid.slice(0,8)}…`);
                    liveBets.seen(e.round_uuid, { fresh: true });
                    startCountdown(e.betting_seconds || 10, TEXT.bettingIn, () => {
                        $state.textContent = TEXT.liftOff;
                    });
                })
                .listen('.round.started', (e) => {
                    cancelCountdown();
                    setStage(['running']);
                    $state.textContent = TEXT.running;
                    $cashout.disabled = false;
                    $cashoutHint.textContent = 'cash out at current x';
                    syncDescent(0, Number(e.growth_rate));
                    liveBets.seen(e.round_uuid);
                    log(`${TEXT.round} started · growth ${e.growth_rate}/s`);
                })
                .listen('.round.tick', (e) => {
                    if ($stage.classList.contains('crashed')) return;
                    const m = Number(e.multiplier ?? e.data?.multiplier ?? NaN);
                    if (!Number.isFinite(m)) return;
                    tweenMultiplier(m);
                    syncDescent(e.elapsed_seconds, NaN);
                    liveBets.seen(e.round_uuid);
                    // Tick implies running. If user opened the page mid-round and
                    // missed betting_opened/started, sync state text + cashout button.
                    const elapsed = Number(e.elapsed_seconds ?? 0).toFixed(1);
                    $state.textContent = `${TEXT.running} · ${elapsed}s`;
                    if ($cashout.disabled) {
                        $cashout.disabled = false;
                        $cashoutHint.textContent = 'cash out at current x';
                    }
                })
                .listen('.round.crashed', (e) => {
                    try {
                        log('💥 raw: ' + JSON.stringify(e));
                        const cp = Number(e.crash_point ?? e.crashPoint ?? e.data?.crash_point ?? NaN);
                        stopTween(Number.isFinite(cp) ? cp : tween.to);
                        stopDescent(); // the scenery holds where the round ended
                        setStage(['crashed', 'critical']);
                        if (Number.isFinite(cp)) {
                            $mult.textContent = cp.toFixed(2) + 'x';
                            scene.crashed(cp);
                            $state.textContent = `${TEXT.crashed} @ ${cp.toFixed(2)}x`;
                            log(`💥 ${TEXT.crashed.toLowerCase()} @ ${cp.toFixed(2)}x`);
                            pushHistory(cp, e.round_uuid);
                            liveBets.crashed(e.round_uuid, cp);
                        } else {
                            $state.textContent = `${TEXT.crashed} (no crash_point)`;
                            log('⚠ crash_point missing in payload');
                        }
                        $cashout.disabled = true;
                        $cashoutHint.textContent = 'round over';
                    } catch (err) {
                        log('⚠ crashed handler threw: ' + err.message);
                    }
                })
                .listen('.round.settled', (e) => {
                    log(`settled · seed ${e.server_seed?.slice(0,12)}…`);
                    sheet.betsChanged(); // the round's bets are settled now
                    // Hold the crash splash for ~1.2s so the player can read
                    // the crash point, then snap back to a neutral idle and
                    // start a visible countdown until the next betting window.
                    setTimeout(() => {
                        if ($stage.classList.contains('betting') ||
                            $stage.classList.contains('running')) return;
                        setStage(['waiting']);
                        resetIdleVisuals();
                        const wait = Number(e.next_round_in_seconds ?? 5);
                        startCountdown(wait, TEXT.nextIn, () => {
                            $state.textContent = TEXT.awaiting;
                        });
                    }, 1200);
                })
                .listen('.round.bets', (e) => liveBets.update(e));
        }

        if (window.Echo) attachEcho();
        else window.addEventListener('DOMContentLoaded', () => setTimeout(attachEcho, 250));

        $cashout.addEventListener('click', () => {
            $cashout.disabled = true;
            $cashoutHint.textContent = 'visual demo only — use crash:bet CLI';
            log('🎯 cash-out tapped (visual demo)');
        });

        // ---- Fullscreen toggle -------------------------------------------------
        (function fullscreenButton() {
            const $btn = document.getElementById('fs-btn');
            if (!$btn) return;

            const isEmbedded = window.self !== window.top;
            // On its own, the page can't go fullscreen where the browser has no Fullscreen
            // API for pages (iPhones: video only), so the button goes. Embedded, the operator
            // page decides (fullscreen, or where there is none, expanding the game).
            if (!isEmbedded && !(document.fullscreenEnabled || document.webkitFullscreenEnabled)) {
                document.documentElement.classList.add('no-fullscreen');
                fitHistory();
                return;
            }

            function isFullscreen() {
                return !!(document.fullscreenElement || document.webkitFullscreenElement);
            }

            function syncState() {
                document.body.classList.toggle('is-fullscreen', isFullscreen());
            }

            $btn.addEventListener('click', () => {
                // If we're inside an iframe, ask the parent to fullscreen the iframe
                // (the child can't reliably fullscreen its own document tree).
                // Important: `document.fullscreenElement` inside this iframe stays
                // null even when the iframe element itself is fullscreen on the
                // parent page — so use the parent-mirrored `is-fullscreen` class
                // on body as the source of truth here.
                if (isEmbedded) {
                    const parentSaysFullscreen = document.body.classList.contains('is-fullscreen');
                    try {
                        window.parent.postMessage(
                            { type: parentSaysFullscreen ? 'exit_fullscreen' : 'enter_fullscreen' },
                            '*'
                        );
                    } catch (_) { /* ignore */ }
                    return;
                }

                const el = document.documentElement;
                if (!isFullscreen()) {
                    (el.requestFullscreen || el.webkitRequestFullscreen)?.call(el);
                } else {
                    (document.exitFullscreen || document.webkitExitFullscreen)?.call(document);
                }
            });

            // Mirror parent-driven fullscreen so the icon flips inside the iframe too.
            window.addEventListener('message', (event) => {
                if (event.data?.type === 'fullscreen_state') {
                    document.body.classList.toggle('is-fullscreen', !!event.data.payload?.active);
                }
            });

            document.addEventListener('fullscreenchange', syncState);
            document.addEventListener('webkitfullscreenchange', syncState);
        })();

        // ---- Menu (☰) and theme -------------------------------------------------
        // The top-right button opens the game's menu; an item marked data-close closes
        // it after doing its thing (the sound and theme toggles leave it open, so their
        // new state shows). Skins are `:root[data-theme]` token blocks in the <style>
        // above: the <head> bootstrap already applied the saved one before first paint;
        // this wires the theme list, persistence and the active marker.
        (function gameMenu() {
            const $btn  = document.getElementById('menu-btn');
            const $menu = document.getElementById('game-menu');
            if (!$btn || !$menu) return;

            function open()  { $menu.hidden = false; $btn.setAttribute('aria-expanded', 'true'); }
            function close() { $menu.hidden = true;  $btn.setAttribute('aria-expanded', 'false'); }

            $btn.addEventListener('click', (e) => {
                e.stopPropagation();
                if ($menu.hidden) open(); else close();
            });
            $menu.addEventListener('click', (e) => {
                e.stopPropagation();
                if (e.target.closest('[data-close]')) close();
            });
            document.addEventListener('click', () => { if (!$menu.hidden) close(); });
            document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && !$menu.hidden) close(); });

            const THEME_KEY = @json('orange.ui.theme'.$storeSuffix);
            const $opts = [...$menu.querySelectorAll('.theme-opt')];

            function current() {
                return document.documentElement.getAttribute('data-theme') || $opts[0]?.dataset.theme;
            }

            function syncActive() {
                const active = current();
                for (const $o of $opts) {
                    const on = $o.dataset.theme === active;
                    $o.classList.toggle('is-active', on);
                    $o.setAttribute('aria-checked', on ? 'true' : 'false');
                }
            }

            function apply(theme) {
                document.documentElement.setAttribute('data-theme', theme);
                try { localStorage.setItem(THEME_KEY, theme); } catch (_) {}
                syncActive();
            }

            for (const $o of $opts) {
                $o.addEventListener('click', () => apply($o.dataset.theme));
            }

            syncActive();
        })();

        // ----------------------------------------------------------------------
        // Audio engine — procedural SFX + background ambience, per theme: the
        // Gothic ("burton") and Jackpot Reef ("jackpot") skins play their own
        // packs (js/orange/sound-*.js), every other theme the underwater set
        // below. No external assets.
        // AudioContext is gated behind first user gesture (browser autoplay
        // policy); a persistent mute toggle lives top-right.
        // ----------------------------------------------------------------------
        const audio = (function () {
            let ctx, master, musicTimer, musicStep = 0, musicEnabled = true, started = false;
            const STORAGE_KEY = 'orange.audio.muted';
            let themeAmbience = null; // the running ambience of a theme pack, if any
            const PACKS = @json($sceneDef['packs']);
            const packs = {};

            /** The current theme's sound pack, or null for the built-in underwater set. */
            function themePack() {
                const name = PACKS[document.documentElement.getAttribute('data-theme')];
                if (!name || !window.OrangeSound?.[name]) return null;
                init();
                return packs[name] ??= window.OrangeSound[name](ctx, master);
            }

            function init() {
                if (ctx) return;
                ctx = new (window.AudioContext || window.webkitAudioContext)();
                master = ctx.createGain();
                master.gain.value = 0.35;
                master.connect(ctx.destination);
            }

            function midi(n) { return 440 * Math.pow(2, (n - 69) / 12); }

            // One-shot synth: type + freq + envelope + duration
            function tone({ freq, type = 'square', attack = 0.005, decay = 0.08, sustain = 0.0, release = 0.05, gain = 0.18, dur = 0.12, slideTo = null }) {
                if (!ctx) return;
                const t0 = ctx.currentTime;
                const osc = ctx.createOscillator();
                const g = ctx.createGain();
                osc.type = type;
                osc.frequency.setValueAtTime(freq, t0);
                if (slideTo) osc.frequency.exponentialRampToValueAtTime(slideTo, t0 + dur);

                const peak = gain;
                g.gain.setValueAtTime(0.0001, t0);
                g.gain.exponentialRampToValueAtTime(peak, t0 + attack);
                g.gain.exponentialRampToValueAtTime(Math.max(0.0001, peak * sustain + 0.0001), t0 + attack + decay);
                g.gain.exponentialRampToValueAtTime(0.0001, t0 + Math.max(attack + decay + release, dur));

                osc.connect(g).connect(master);
                osc.start(t0);
                osc.stop(t0 + dur + 0.05);
            }

            function noise({ dur = 0.12, gain = 0.10, lowPass = 1200 }) {
                if (!ctx) return;
                const t0 = ctx.currentTime;
                const bufferSize = Math.floor(ctx.sampleRate * dur);
                const buffer = ctx.createBuffer(1, bufferSize, ctx.sampleRate);
                const data = buffer.getChannelData(0);
                for (let i = 0; i < bufferSize; i++) data[i] = Math.random() * 2 - 1;
                const src = ctx.createBufferSource();
                src.buffer = buffer;
                const filter = ctx.createBiquadFilter();
                filter.type = 'lowpass';
                filter.frequency.value = lowPass;
                const g = ctx.createGain();
                g.gain.setValueAtTime(gain, t0);
                g.gain.exponentialRampToValueAtTime(0.0001, t0 + dur);
                src.connect(filter).connect(g).connect(master);
                src.start(t0);
                src.stop(t0 + dur + 0.05);
            }

            // ---- SFX library ----
            function sfx(name) {
                if (!started || master?.gain.value === 0) return;
                init();
                const pack = themePack();
                if (pack) {
                    pack.sfx(name);
                    return;
                }
                switch (name) {
                    case 'bet':       // short ascending blip
                        tone({ freq: midi(72), slideTo: midi(79), type: 'square', gain: 0.20, dur: 0.10 });
                        break;

                    case 'start':     // DIVE — multi-layer dramatic descent
                        // Layer 1: deep engine ignition swoop (sub bass slide down)
                        tone({ freq: midi(60), slideTo: midi(36), type: 'sawtooth', gain: 0.30, dur: 0.75, attack: 0.01, decay: 0.20, sustain: 0.5, release: 0.45 });
                        // Layer 2: mid-range engine harmonic (square doubles the body)
                        tone({ freq: midi(48), slideTo: midi(28), type: 'square',   gain: 0.10, dur: 0.70, attack: 0.02, decay: 0.30, sustain: 0.4, release: 0.35 });
                        // Layer 3: turbulent water rush (filtered noise burst)
                        noise({ dur: 0.55, gain: 0.18, lowPass: 1400 });
                        // Layer 4: sonar ping kicks in mid-swoop — descending sine
                        setTimeout(() => tone({ freq: midi(84), slideTo: midi(60), type: 'sine', gain: 0.14, dur: 0.40, attack: 0.005, decay: 0.10, sustain: 0.4, release: 0.30 }), 180);
                        // Layer 5: pad-like sustain tail for "we are now diving" ambient
                        setTimeout(() => tone({ freq: midi(43), type: 'triangle', gain: 0.06, dur: 0.80, attack: 0.20, decay: 0.30, sustain: 0.6, release: 0.50 }), 350);
                        break;

                    case 'win':       // coin jingle — three rising notes
                        [76, 81, 88].forEach((n, i) => setTimeout(() => tone({ freq: midi(n), type: 'square', gain: 0.22, dur: 0.14 }), i * 70));
                        break;
                    case 'loss':      // descending sad notes
                        [60, 56, 52].forEach((n, i) => setTimeout(() => tone({ freq: midi(n), type: 'square', gain: 0.18, dur: 0.18 }), i * 110));
                        break;

                    case 'crash':     // EXPLOSION — sharp impact + crackle + long boom + ring-down
                        // Layer 1: sharp click (transient — gives the punch)
                        tone({ freq: midi(96), slideTo: midi(40), type: 'square',   gain: 0.20, dur: 0.04, attack: 0.001, decay: 0.02, sustain: 0.0, release: 0.02 });
                        // Layer 2: heavy sub-bass boom slide down (the IMPACT)
                        tone({ freq: midi(48), slideTo: midi(24), type: 'sawtooth', gain: 0.36, dur: 0.90, attack: 0.005, decay: 0.30, sustain: 0.4, release: 0.55 });
                        // Layer 3: mid-range body (square — pressure crush)
                        tone({ freq: midi(40), slideTo: midi(22), type: 'square',   gain: 0.18, dur: 0.70, attack: 0.005, decay: 0.30, sustain: 0.3, release: 0.40 });
                        // Layer 4: shrapnel/cavitation crackle (mid-high noise)
                        noise({ dur: 0.25, gain: 0.20, lowPass: 3500 });
                        // Layer 5: extended low-rumble debris (long noise tail)
                        setTimeout(() => noise({ dur: 0.80, gain: 0.10, lowPass: 400 }), 30);
                        // Layer 6: metallic ring-out (two pitched echoes)
                        setTimeout(() => tone({ freq: midi(31), type: 'sine', gain: 0.10, dur: 1.20, attack: 0.05, decay: 0.30, sustain: 0.5, release: 0.85 }), 80);
                        setTimeout(() => tone({ freq: midi(35), type: 'sine', gain: 0.08, dur: 1.00, attack: 0.05, decay: 0.30, sustain: 0.5, release: 0.65 }), 160);
                        // Layer 7: dissonant secondary boom (creates explosive width)
                        setTimeout(() => tone({ freq: midi(38), slideTo: midi(20), type: 'triangle', gain: 0.14, dur: 0.65, attack: 0.005, decay: 0.25, sustain: 0.4, release: 0.40 }), 60);
                        break;

                    case 'tick':      // soft click for betting countdown
                        tone({ freq: midi(72), type: 'sine', gain: 0.08, dur: 0.04 });
                        break;
                }
            }

            // ---- Underwater ambience engine ----
            // Replaces the chiptune loop with layered procedural soundscape:
            //   1. Sub-bass drone (35–60 Hz sine cluster) — the "pressure"
            //   2. Filtered noise loop — distant water current
            //   3. Random sonar pings every 6–20 s
            //   4. Random hull creaks every 10–30 s (deeper = more frequent)
            //   5. Rare distant whale calls every 25–60 s
            // All nodes are tracked so stop/mute can cleanly tear them down.
            let ambientNodes = [];
            let ambientTimers = [];

            function addNode(n) { ambientNodes.push(n); return n; }
            function addTimer(t) { ambientTimers.push(t); return t; }

            function stopMusic() {
                if (themeAmbience) { themeAmbience.stop(); themeAmbience = null; }
                if (musicTimer) { clearTimeout(musicTimer); musicTimer = null; } // legacy guard
                ambientTimers.forEach(t => clearTimeout(t));
                ambientTimers = [];
                if (!ctx) { ambientNodes = []; return; }
                const t = ctx.currentTime;
                ambientNodes.forEach(node => {
                    try {
                        if (node.gain && node.gain.cancelScheduledValues) {
                            node.gain.cancelScheduledValues(t);
                            node.gain.exponentialRampToValueAtTime(0.0001, t + 0.4);
                        }
                        if (typeof node.stop === 'function') node.stop(t + 0.5);
                    } catch (_) { /* node already stopped */ }
                });
                ambientNodes = [];
            }

            function startMusic() {
                if (!musicEnabled) return;
                if (ambientNodes.length || themeAmbience) return; // already running
                init();
                const pack = themePack();
                if (pack) {
                    themeAmbience = pack.ambience();
                    themeAmbience.intensity?.(diveLevel());
                    return;
                }

                // === Layer 1: low drone cluster ====================================
                [37, 55.5, 74].forEach((freq, i) => {
                    const osc = ctx.createOscillator();
                    const g = ctx.createGain();
                    osc.type = i === 0 ? 'sine' : 'triangle';
                    osc.frequency.value = freq;
                    const target = [0.040, 0.020, 0.010][i];
                    const t0 = ctx.currentTime;
                    g.gain.setValueAtTime(0.0001, t0);
                    g.gain.exponentialRampToValueAtTime(target, t0 + 3.0);
                    osc.connect(g).connect(master);
                    osc.start(t0);
                    addNode(osc); addNode(g);
                });

                // === Layer 2: distant water current (filtered noise loop) ==========
                const noiseBuf = ctx.createBuffer(1, ctx.sampleRate * 4, ctx.sampleRate);
                const data = noiseBuf.getChannelData(0);
                for (let i = 0; i < data.length; i++) data[i] = Math.random() * 2 - 1;
                const noiseSrc = ctx.createBufferSource();
                noiseSrc.buffer = noiseBuf;
                noiseSrc.loop = true;
                const flowFilter = ctx.createBiquadFilter();
                flowFilter.type = 'lowpass';
                flowFilter.frequency.value = 380;
                flowFilter.Q.value = 0.5;
                const flowGain = ctx.createGain();
                const t0 = ctx.currentTime;
                flowGain.gain.setValueAtTime(0.0001, t0);
                flowGain.gain.exponentialRampToValueAtTime(0.022, t0 + 4.0);
                noiseSrc.connect(flowFilter).connect(flowGain).connect(master);
                noiseSrc.start(t0);
                addNode(noiseSrc); addNode(flowFilter); addNode(flowGain);

                // Slow LFO on the noise filter cutoff for a gently shifting current
                const lfo = ctx.createOscillator();
                const lfoDepth = ctx.createGain();
                lfo.type = 'sine';
                lfo.frequency.value = 0.08;   // very slow — ~12 s cycle
                lfoDepth.gain.value = 120;
                lfo.connect(lfoDepth).connect(flowFilter.frequency);
                lfo.start();
                addNode(lfo); addNode(lfoDepth);

                // === Layer 3–5: scheduled one-shots ================================
                schedulePing();
                scheduleCreak();
                scheduleWhale();
            }

            // -- Sonar ping (every 6–20 s) --
            function schedulePing() {
                const delay = 6000 + Math.random() * 14000;
                addTimer(setTimeout(() => {
                    if (!ambientNodes.length) return;
                    const t = ctx.currentTime;
                    const freq = 720 + Math.random() * 280;
                    const osc = ctx.createOscillator();
                    const g = ctx.createGain();
                    osc.type = 'sine';
                    osc.frequency.setValueAtTime(freq, t);
                    osc.frequency.exponentialRampToValueAtTime(freq * 0.45, t + 1.6);
                    g.gain.setValueAtTime(0.0001, t);
                    g.gain.exponentialRampToValueAtTime(0.055, t + 0.02);
                    g.gain.exponentialRampToValueAtTime(0.0001, t + 1.7);
                    osc.connect(g).connect(master);
                    osc.start(t);
                    osc.stop(t + 1.75);
                    schedulePing();
                }, delay));
            }

            // -- Hull creak / groan (every 10–30 s) --
            function scheduleCreak() {
                const delay = 10000 + Math.random() * 20000;
                addTimer(setTimeout(() => {
                    if (!ambientNodes.length) return;
                    const t = ctx.currentTime;
                    const osc = ctx.createOscillator();
                    const g = ctx.createGain();
                    const bp = ctx.createBiquadFilter();
                    bp.type = 'bandpass';
                    bp.frequency.value = 180 + Math.random() * 220;
                    bp.Q.value = 5.5;
                    osc.type = 'sawtooth';
                    const f0 = 70 + Math.random() * 60;
                    osc.frequency.setValueAtTime(f0, t);
                    osc.frequency.linearRampToValueAtTime(f0 * 1.35, t + 1.5);
                    osc.frequency.linearRampToValueAtTime(f0 * 0.9,  t + 2.6);
                    g.gain.setValueAtTime(0.0001, t);
                    g.gain.exponentialRampToValueAtTime(0.075, t + 0.3);
                    g.gain.exponentialRampToValueAtTime(0.0001, t + 2.7);
                    osc.connect(bp).connect(g).connect(master);
                    osc.start(t);
                    osc.stop(t + 2.8);
                    scheduleCreak();
                }, delay));
            }

            // -- Distant whale call (every 25–60 s) — long sine glissando --
            function scheduleWhale() {
                const delay = 25000 + Math.random() * 35000;
                addTimer(setTimeout(() => {
                    if (!ambientNodes.length) return;
                    const t = ctx.currentTime;
                    const osc = ctx.createOscillator();
                    const g = ctx.createGain();
                    osc.type = 'sine';
                    const start = 90 + Math.random() * 40;
                    const peak  = start + 60 + Math.random() * 80;
                    osc.frequency.setValueAtTime(start, t);
                    osc.frequency.exponentialRampToValueAtTime(peak, t + 1.4);
                    osc.frequency.exponentialRampToValueAtTime(start * 0.7, t + 3.4);
                    g.gain.setValueAtTime(0.0001, t);
                    g.gain.exponentialRampToValueAtTime(0.045, t + 0.5);
                    g.gain.exponentialRampToValueAtTime(0.0001, t + 3.5);
                    osc.connect(g).connect(master);
                    osc.start(t);
                    osc.stop(t + 3.6);
                    scheduleWhale();
                }, delay));
            }

            function setMuted(muted) {
                musicEnabled = !muted;
                if (master) master.gain.value = muted ? 0 : 0.35;
                try { localStorage.setItem(STORAGE_KEY, muted ? '1' : '0'); } catch (_) {}
                document.getElementById('audio-btn')?.classList.toggle('is-muted', muted);
                if (muted) stopMusic();
                else if (started) startMusic();
            }

            function isMuted() {
                try { return localStorage.getItem(STORAGE_KEY) === '1'; } catch (_) { return false; }
            }

            // First user gesture anywhere → unlock AudioContext + kick off music
            function unlock() {
                if (started) return;
                started = true;
                init();
                // Some browsers leave the context in 'suspended' even after init.
                if (ctx.state === 'suspended') ctx.resume();
                setMuted(isMuted());
                if (!isMuted()) startMusic();
            }

            // Capture phase: controls that stop their clicks (the theme picker) still unlock audio.
            ['click', 'touchstart', 'keydown'].forEach(ev =>
                document.addEventListener(ev, unlock, { capture: true, passive: true }));

            document.getElementById('audio-btn')?.addEventListener('click', (e) => {
                e.stopPropagation();
                unlock();
                setMuted(!isMuted());
            });

            // Reflect initial mute state in icon
            document.getElementById('audio-btn')?.classList.toggle('is-muted', isMuted());

            // A theme brings its own sounds: swap the background when the skin changes.
            new MutationObserver((records) => {
                const now = document.documentElement.getAttribute('data-theme');
                if (records.every((r) => r.oldValue === now)) return;
                if (started && musicEnabled) {
                    stopMusic();
                    startMusic();
                }
            }).observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'], attributeOldValue: true });

            // A pack's music may follow the dive (Jackpot's band builds as it deepens):
            // 0 betting/waiting/crashed · 1 diving · 2 deep · 3 abyss or critical.
            function diveLevel() {
                const c = document.getElementById('stage')?.classList;
                if (!c || c.contains('crashed')) return 0;
                if (c.contains('abyss') || c.contains('critical')) return 3;
                if (c.contains('deep')) return 2;
                return c.contains('running') ? 1 : 0;
            }
            const $stageForMusic = document.getElementById('stage');
            if ($stageForMusic) {
                new MutationObserver(() => themeAmbience?.intensity?.(diveLevel()))
                    .observe($stageForMusic, { attributes: true, attributeFilter: ['class'] });
            }

            return { sfx, startMusic, stopMusic };
        })();

        // ---- Wire SFX to the round + operator-panel events ----
        window.Echo?.channel(@json($roundChannel))
            .listen('.round.betting_opened', () => audio.sfx('tick'))
            .listen('.round.started',        () => audio.sfx('start'))
            .listen('.round.crashed',        () => audio.sfx('crash'));

        // The operator panel's buttons, in either bet slot (only shown when embedded). The
        // win sound plays once a cash-out is confirmed, however it came (operatorBridge).
        document.getElementById('op-panel')?.addEventListener('click', (e) => {
            if (e.target.closest('.op-bet-btn')) audio.sfx('bet');
        });

        // postMessage results from the parent — play feedback SFX based on outcome.
        window.addEventListener('message', (event) => {
            const { type, payload } = event.data || {};
            if (type === 'cashout_result') {
                if (payload?.error === 'round_crashed') audio.sfx('loss');
            } else if (type === 'bet_placed_result' && payload?.success === false) {
                audio.sfx('loss');
            }
        });

        // ----------------------------------------------------------------------
        // Operator embed bridge — when /crash is loaded in an iframe by an
        // operator (e.g. OrangeConsumer), expose a small overlay panel that
        // postMessages bet/cashout INTENTS to the parent window. The parent
        // owns the operator HMAC secret and forwards the signed call to Orange.
        // ----------------------------------------------------------------------
        (function operatorBridge() {
            const isEmbedded = window.self !== window.top;
            if (!isEmbedded) return;

            document.body.classList.add('embedded');

            // Origins permitted to drive this embed. Mirrors config('embed.allowed_origins').
            // Orange's own host pages (/play, /lobby) drive the game from the same origin.
            const allowedParentOrigins = [...@json(array_values((array) config('embed.allowed_origins', []))), window.location.origin];

            const $panel    = document.getElementById('op-panel');
            const $player   = document.getElementById('op-player');
            const $balance  = document.getElementById('op-balance');
            const $phaseChip = document.getElementById('op-phase');
            const $status   = document.getElementById('op-status');
            const $last     = document.getElementById('op-last');
            const $lastMult = document.getElementById('op-last-mult');
            const $lastPay  = document.getElementById('op-last-payout');
            const $addSlot  = $panel.querySelector('.op-add-slot');
            const $removeSlot = $panel.querySelector('.op-remove-slot');
            const $home     = document.getElementById('home-btn');
            $panel.setAttribute('aria-hidden', 'false');

            function showLast(kind, multiplier, payout) {
                $last.classList.add('visible');
                $last.classList.toggle('loss', kind === 'loss');
                const m = Number(multiplier);
                $lastMult.textContent = Number.isFinite(m) ? m.toFixed(2) + '×' : '—';
                $lastPay.textContent = kind === 'win' ? '+' + money(payout) : 'lost';
            }

            // Money is always shown with two decimals: "673.99000000" → "673.99",
            // "10" → "10.00". Wallets answer with 8-decimal precision; the extra
            // digits are cut (never rounded up past what the player has).
            function money(value) {
                if (value === null || value === undefined || value === '') return '—';
                const s = String(value).trim();
                const m = /^(-?\d+)(?:\.(\d*))?$/.exec(s);
                if (!m) return Number.isFinite(Number(s)) ? Number(s).toFixed(2) : s;
                return `${m[1]}.${((m[2] || '') + '00').slice(0, 2)}`;
            }

            // Spawn a "× 1.30   +10.30 EUR" toast over the stage that drifts
            // upward and fades. Re-entrant: two bets cashing out together overlap.
            function spawnFlyWin(multiplier, payout) {
                const m = Number(multiplier);
                if (!Number.isFinite(m)) return;
                scene.cashedOut?.(m); // the scene's own flourish, if it has one
                const node = document.createElement('div');
                node.className = 'fly-win';
                const multSpan = document.createElement('div');
                multSpan.className = 'fly-win-mult';
                multSpan.textContent = `× ${m.toFixed(2)}`;
                const amtSpan = document.createElement('div');
                amtSpan.className = 'fly-win-amount';
                amtSpan.textContent = `+${money(payout)}${opState.currency ? ' ' + opState.currency : ''}`;
                node.append(multSpan, amtSpan);
                $stage.appendChild(node);
                node.addEventListener('animationend', () => node.remove(), { once: true });
            }

            function applyPhaseChip(phase) {
                $phaseChip.dataset.phase = phase;
                $phaseChip.textContent = phase;
            }

            const opState = {
                parentOrigin: null,        // first valid origin we received a message from
                phase: 'waiting',          // synced to the in-page Echo state machine
                roundUuid: null,
                playerId: null,
                currency: '',
                balance: null,             // the last balance the host gave, for auto bet's total
                maxSlots: 1,               // 2 once the host takes two bets a round (player_info.bet_slots)
                placing: [],               // slots whose place_bet awaits its result, in the order sent
            };
            const unit = () => (opState.currency ? ' ' + opState.currency : '');

            function setBalance(value) {
                opState.balance = value === null || value === undefined || value === '' ? null : String(value);
                $balance.textContent = money(value) + unit();
            }

            // The player's bet limits (player_info.limits) as numbers, null for none. Until the
            // host gives them, only the smallest stake there is. The server checks them again.
            const limits = { min: 0.01, max: null, maxWin: null };
            function setLimits(given) {
                const num = (v) => (v === null || v === undefined || v === '' || !Number.isFinite(Number(v)) ? null : Number(v));
                limits.min = Math.max(0.01, num(given?.min_bet) ?? 0.01);
                limits.max = num(given?.max_bet);
                limits.maxWin = num(given?.max_win);
                for (const slot of slots) {
                    slot.$stake.min = String(limits.min);
                    if (limits.max === null) slot.$stake.removeAttribute('max');
                    else slot.$stake.max = String(limits.max);
                    const v = parseFloat(slot.$stake.value);
                    if (Number.isFinite(v)) slot.$stake.value = clampStake(v).toFixed(2);
                }
            }
            // Why a stake can't be bet, or null.
            function stakeProblem(value) {
                const v = Number(value);
                if (!Number.isFinite(v) || v <= 0) return 'enter a bet amount';
                if (v < limits.min) return `the minimum bet is ${money(String(limits.min))}${unit()}`;
                if (limits.max !== null && v > limits.max) return `the maximum bet is ${money(String(limits.max))}${unit()}`;
                if (limits.maxWin !== null && v >= limits.maxWin) return `a bet must be below the max win, ${money(String(limits.maxWin))}${unit()}`;
                return null;
            }
            // The nearest stake the limits allow (a stake must stay below the max win).
            // The stake − / + go to the previous / next multiple of 5; − never goes under 5, and
            // leaves a stake already under 5 where it is. In cents, so no 0.1 + 0.2 creeps in.
            function stakeStep(value, direction) {
                const cents = Math.round(value * 100);
                if (direction > 0) return (Math.floor(cents / 500) + 1) * 5;
                const down = (Math.ceil(cents / 500) - 1) * 500;
                return (down >= 500 ? down : Math.min(cents, 500)) / 100;
            }

            function clampStake(v) {
                let top = limits.max ?? Infinity;
                if (limits.maxWin !== null) top = Math.min(top, limits.maxWin - 0.01);
                return Math.min(Math.max(v, limits.min), top);
            }

            // A bet slot: its own stake, auto cash-out, AUTO BET and bet. Slot 0 is the bar
            // as it always was; slot 1 is the second bet.
            function makeSlot(index) {
                const $root = $panel.querySelector(`.op-slot[data-slot="${index}"]`);
                const q = (selector) => $root.querySelector(selector);
                return {
                    index,
                    $root,
                    $stake: q('.op-stake'),
                    $currency: q('.op-cap-currency'),
                    $auto: q('.op-auto'),
                    $autoField: q('.op-field-auto'),
                    $switch: q('.op-switch'),
                    $bet: q('.op-bet-btn'),
                    $co: q('.op-cashout-btn'),
                    $coX: q('.op-cashout-x'),
                    $coAuto: q('.op-cashout-auto'),
                    $cashed: q('.op-cashed'),
                    $autoBet: q('.op-autobet-btn'),
                    $autoCount: q('.op-autobet-count'),
                    betId: null,
                    betRoundUuid: null,
                    autoCashoutAt: null,       // threshold sent with the active bet
                    autoCashoutFired: false,   // local guard: already asked parent to cashout
                    cashingOut: false,         // a cashout is on its way to the parent
                    clicked: false,            // the player pressed Cash Out (else the cash-out was automatic)
                    won: null,                 // { betId } once the round's bet is cashed out, until the next betting
                    autoOn: false,             // auto cash-out switched on, at the multiplier in $auto
                    autoLast: '',              // its multiplier while switched off, back when it's on again
                    autoBet: false,            // AUTO BET running: a bet on every betting_opened…
                    autoRounds: 0,             // …this many bets in all…
                    autoDone: 0,               // …of which this many are placed
                    lastRounds: 10,            // what the auto bet dialog offers next
                    lastAutoBetRound: null,    // round_uuid we last fired auto-bet for (de-dupe guard)
                    // Restore AUTO BET across reloads. Keyed by host so a user sharing the
                    // machine doesn't inherit someone else's armed state.
                    autoBetKey: (index === 0 ? 'orange.embed.autobet' : 'orange.embed.autobet.2') + @json($storeSuffix),
                };
            }
            const slots = [makeSlot(0), makeSlot(1)];
            const SLOTS_KEY = @json('orange.embed.slots'.$storeSuffix);
            const twoOpen = () => !slots[1].$root.hidden;
            // Messages name the bet when there are two.
            const tag = (slot) => (twoOpen() ? `bet ${slot.index + 1} · ` : '');
            function forgetBet(slot) {
                slot.betId = null;
                slot.betRoundUuid = null;
                slot.autoCashoutAt = null;
                slot.autoCashoutFired = false;
                slot.cashingOut = false;
                slot.clicked = false;
            }

            // AUTO BET places the slot's bet every round until autoDone reaches autoRounds. While
            // it runs the slot's stake and auto cash-out are locked, so the total the player saw
            // when starting it holds. A reload picks it up where it was.
            function renderAutoBet(slot) {
                const on = slot.autoBet;
                slot.$autoBet.dataset.active = String(on);
                slot.$autoBet.setAttribute('aria-pressed', String(on));
                slot.$autoBet.setAttribute('aria-haspopup', on ? 'false' : 'dialog');
                slot.$autoCount.textContent = on ? `${slot.autoDone}/${slot.autoRounds}` : '';
                slot.$autoBet.title = on
                    ? `Auto bet: ${slot.autoDone} of ${slot.autoRounds} bets placed. Click to stop.`
                    : 'Auto bet: place this bet for a number of rounds';
                slot.$root.dataset.auto = String(on);
                for (const $c of slot.$root.querySelectorAll('.op-field input, .op-field button')) $c.disabled = on;
            }
            function saveAutoBet(slot) {
                try {
                    if (slot.autoBet) localStorage.setItem(slot.autoBetKey, JSON.stringify({ rounds: slot.autoRounds, done: slot.autoDone }));
                    else localStorage.removeItem(slot.autoBetKey);
                } catch (_) { /* privacy mode / quota */ }
            }
            function startAutoBet(slot, rounds, done = 0) {
                slot.autoBet = true;
                slot.autoRounds = rounds;
                slot.autoDone = done;
                renderAutoBet(slot);
                saveAutoBet(slot);
            }
            function stopAutoBet(slot, reason = '', kind = '') {
                if (!slot.autoBet) return;
                slot.autoBet = false;
                renderAutoBet(slot);
                saveAutoBet(slot);
                if (reason) setStatus(tag(slot) + reason, kind);
            }
            function restoreAutoBet(slot) {
                let saved = null;
                try { saved = JSON.parse(localStorage.getItem(slot.autoBetKey) ?? 'null'); } catch (_) { /* unreadable: dropped below */ }
                const rounds = Number(saved?.rounds);
                const done = Number(saved?.done);
                if (Number.isInteger(rounds) && Number.isInteger(done) && done >= 0 && done < rounds) startAutoBet(slot, rounds, done);
                else saveAutoBet(slot); // nothing to restore, or an older "1" without a count: cleared
            }

            // Auto cash-out: on at the multiplier in the field (1.01× or more), or off with the
            // field empty. The switch flips it; − / + or typing a multiplier turns it on.
            function renderAutoCashout(slot) {
                slot.$autoField.dataset.on = String(slot.autoOn);
                slot.$switch.setAttribute('aria-checked', String(slot.autoOn));
                slot.$switch.querySelector('.op-cap-state').textContent = slot.autoOn ? 'On' : 'Off';
            }
            function setAutoCashout(slot, on) {
                if (!on && slot.$auto.value) slot.autoLast = slot.$auto.value;
                slot.$auto.value = on ? (slot.$auto.value || slot.autoLast || '1.50') : '';
                slot.autoOn = on;
                renderAutoCashout(slot);
            }

            // The second bet opens and closes with + / −. It closes only without a bet
            // in play, and closing it stops its AUTO BET.
            function setTwo(open) {
                if (!open && (slots[1].betId || opState.placing.includes(slots[1]))) return;
                slots[1].$root.hidden = !open;
                $addSlot.hidden = opState.maxSlots < 2;
                // Open: + only holds its place, so the two rows line up.
                $addSlot.classList.toggle('is-placeholder', open);
                $addSlot.disabled = open;
                $addSlot.setAttribute('aria-hidden', String(open));
                document.body.classList.toggle('two-bets', open);
                if (!open) stopAutoBet(slots[1]);
                if (open && !slots[1].autoBet) restoreAutoBet(slots[1]);
                try { localStorage.setItem(SLOTS_KEY, open ? '2' : '1'); } catch (_) {}
                updateButtons();
            }

            function setStatus(msg, kind = '') {
                $status.className = 'op-status' + (kind ? ' ' + kind : '');
                $status.textContent = msg;
            }

            function postToParent(payload) {
                const target = opState.parentOrigin;
                if (!target || !window.parent) return;
                window.parent.postMessage(payload, target);
            }

            function updateButtons() {
                for (const slot of slots) {
                    const held = !!slot.betId && slot.betRoundUuid === opState.roundUuid;
                    const canBet = opState.phase === 'betting' && !slot.betId && !opState.placing.includes(slot)
                        && !!opState.parentOrigin && !slot.$root.hidden;
                    const canCashout = opState.phase === 'running' && held;
                    slot.$bet.disabled = !canBet;
                    slot.$co.disabled = !canCashout || slot.cashingOut;
                    // One action at a time: Place Bet → Bet placed (until the dive) → Cash Out while
                    // diving (inputs hidden, the button blown up) → the cashed-out result.
                    const mode = slot.won && ['running', 'crashed'].includes(opState.phase) ? 'cashed'
                        : slot.betId && opState.phase === 'running' ? 'cashout'
                        : held && ['betting', 'waiting'].includes(opState.phase) ? 'placed'
                        : 'place';
                    slot.$root.dataset.mode = mode;
                    slot.$bet.textContent = mode === 'placed' ? 'Bet placed' : 'Place Bet';
                    slot.$coAuto.textContent = slot.autoCashoutAt ? `auto at ${Number(slot.autoCashoutAt).toFixed(2)}×` : '';
                    slot.$co.classList.toggle('pulse', canCashout && !slot.cashingOut);
                }
                $removeSlot.disabled = !!(slots[1].betId || opState.placing.includes(slots[1]));
                applyPhaseChip(opState.phase);
            }

            // One of the slot's bets was cashed out: by the player, its auto cash-out or its max
            // win. Whichever word comes first says so (the host's cashout_result, or the round's
            // live bet list); later ones about the same bet change nothing.
            function cashedOut(slot, betId, multiplier, payout, auto) {
                if (slot.won && String(slot.won.betId) === String(betId)) return;
                slot.won = { betId };
                forgetBet(slot);
                const m = Number(multiplier);
                const x = Number.isFinite(m) ? `${m.toFixed(2)}×` : '';
                slot.$cashed.querySelector('.op-cashed-label').textContent = auto ? 'Auto cashed out' : 'Cashed out';
                slot.$cashed.querySelector('.op-cashed-x').textContent = x;
                slot.$cashed.querySelector('.op-cashed-win').textContent = payout ? `+${money(payout)}` : '';
                setStatus(`${tag(slot)}${auto ? 'auto ' : ''}cashed out @ ${x} · won ${money(payout)}${unit()}`, 'ok');
                showLast('win', multiplier, payout);
                spawnFlyWin(multiplier, payout);
                audio.sfx('win');
                sheet.betsChanged();
                updateButtons();
                // The win is paid into the casino's wallet after this: ask the page for the
                // balance the wallet then reports (it answers with balance_update).
                postToParent({ type: 'refresh_balance', payload: {} });
            }

            // Auto bet's dialog: what it repeats (the slot's bet and auto cash-out), for how many
            // rounds, and what all of that comes to.
            function openAutoBet(slot) {
                const { el } = sheet;
                const CHOICES = [10, 20, 50, 100];
                const MAX_ROUNDS = 1000;
                const stake = Number(slot.$stake.value);
                const problem = stakeProblem(slot.$stake.value);
                const autoAt = slot.autoOn ? Number(slot.$auto.value) : null;
                let rounds = slot.lastRounds;

                const $custom = el('input', {
                    class: 'ab-custom', type: 'number', min: '1', max: String(MAX_ROUNDS), step: '1',
                    inputmode: 'numeric', placeholder: 'Other', 'aria-label': 'Other number of rounds',
                });
                const $chips = CHOICES.map((n) => el('button', {
                    type: 'button', class: 'ab-chip', 'aria-pressed': 'false',
                    onclick: () => { $custom.value = ''; pick(n); },
                }, String(n)));
                const $calc = el('span');
                const $total = el('b');
                const $note = el('p', { class: 'ab-note' });
                const $start = el('button', { type: 'button', class: 'ab-start', onclick: begin });

                function pick(n) {
                    rounds = n;
                    for (const $c of $chips) $c.setAttribute('aria-pressed', String($custom.value === '' && Number($c.textContent) === n));
                    render();
                }
                function render() {
                    const total = stake * rounds;
                    const balance = opState.balance === null ? NaN : Number(opState.balance);
                    $calc.textContent = rounds >= 1 ? `Total: ${rounds} × ${money(String(stake))}` : 'Total';
                    $total.textContent = rounds >= 1 && !problem ? `${money(total.toFixed(2))}${unit()}` : '—';
                    $note.classList.toggle('is-warn', !!problem || total > balance);
                    $note.textContent = problem ? `Can’t start: ${problem}.`
                        : total > balance ? `That’s more than your balance (${money(opState.balance)}${unit()}). Auto bet stops when a bet is refused.`
                        : 'The most it can cost: what you lose if no bet wins.';
                    $start.disabled = !!problem || !(rounds >= 1);
                    $start.textContent = rounds >= 1 ? `Start · ${rounds} ${rounds === 1 ? 'round' : 'rounds'}` : 'Start';
                }
                function begin() {
                    sheet.close();
                    slot.lastRounds = rounds;
                    startAutoBet(slot, rounds);
                    setStatus(`${tag(slot)}auto bet on · ${rounds} ${rounds === 1 ? 'round' : 'rounds'}`, 'ok');
                    // Started while betting is open, with no bet in yet: this round is its first.
                    if (
                        opState.phase === 'betting' &&
                        !slot.betId &&
                        !opState.placing.includes(slot) &&
                        opState.parentOrigin &&
                        slot.lastAutoBetRound !== opState.roundUuid
                    ) {
                        slot.lastAutoBetRound = opState.roundUuid;
                        triggerBet(slot);
                    }
                }
                $custom.addEventListener('input', () => {
                    const n = Math.floor(Number($custom.value));
                    pick(Number.isFinite(n) && n >= 1 ? Math.min(n, MAX_ROUNDS) : 0);
                });

                sheet.panel('autobet', twoOpen() ? `Auto bet · bet ${slot.index + 1}` : 'Auto bet',
                    el('dl', { class: 'ab-what' },
                        el('div', {}, el('dt', {}, 'Each bet'), el('dd', {}, `${money(slot.$stake.value)}${unit()}`)),
                        el('div', {}, el('dt', {}, 'Auto cash out'), autoAt
                            ? el('dd', {}, `at ${autoAt.toFixed(2)}×`)
                            : el('dd', { class: 'is-off' }, 'Off: you cash out each bet'))),
                    el('span', { class: 'ab-label' }, 'Number of rounds'),
                    el('div', { class: 'ab-rounds', role: 'group', 'aria-label': 'Number of rounds' }, ...$chips, $custom),
                    el('div', { class: 'ab-total' }, $calc, $total),
                    $note,
                    el('div', { class: 'ab-actions' },
                        el('button', { type: 'button', class: 'ab-cancel', onclick: () => sheet.close() }, 'Cancel'),
                        $start));
                if (!CHOICES.includes(rounds)) $custom.value = String(rounds);
                pick(rounds);
            }

            // Which slot a result is for. Orange's host pages echo the slot a place_bet
            // named; from a page that doesn't, results come back in the order sent.
            function slotPlaced(payload) {
                const named = Number.isInteger(payload.slot) ? slots[payload.slot] : null;
                const slot = named ?? opState.placing[0] ?? slots[0];
                const i = opState.placing.indexOf(slot);
                if (i >= 0) opState.placing.splice(i, 1);
                return slot;
            }
            // The slot holding a bet, or showing it cashed out.
            const slotOfBet = (betId) => (betId === undefined || betId === null ? undefined
                : slots.find((s) => (s.betId !== null && String(s.betId) === String(betId)) || (s.won && String(s.won.betId) === String(betId))));

            // Mirror the in-page Echo state machine onto our buttons.
            window.addEventListener('message', (event) => {
                if (!allowedParentOrigins.includes(event.origin)) return;
                if (!event.data || typeof event.data !== 'object') return;
                const { type, payload } = event.data;
                if (!type) return;

                // Lock onto the first parent origin that talked to us.
                if (!opState.parentOrigin) opState.parentOrigin = event.origin;

                switch (type) {
                    case 'player_info': {
                        opState.playerId = payload.id;
                        opState.currency = payload.currency;
                        // The player's name when the page knows one (Orange's host sends the id
                        // twice when it doesn't); the id stays in the tooltip.
                        $player.textContent = payload.username && payload.username !== String(payload.id)
                            ? payload.username
                            : `#${payload.id}`;
                        $player.title = `#${payload.id}`;
                        setBalance(payload.balance);
                        for (const slot of slots) slot.$currency.textContent = payload.currency || '';
                        // The limits the player's bets are played with, when the page knows them.
                        if (payload.limits) setLimits(payload.limits);
                        rules.configure({ limits: payload.limits ?? null, currency: payload.currency || '', money });
                        setStatus('connected to operator', 'ok');
                        // Pages that answer bet_history say so (Orange's own host pages do).
                        if (payload.bet_history === true && !document.documentElement.classList.contains('has-bets')) {
                            sheet.enableBets({ request: () => postToParent({ type: 'bet_history', payload: {} }), money });
                            document.documentElement.classList.add('has-bets');
                            fitHistory();
                        }
                        // In a lobby, the menu's Home goes back to it (the lobby hides its own
                        // bar for a game that says it has one: see embed_ready below).
                        if (payload.home === true) $home.hidden = false;
                        // Pages that take two bets a round say so (Orange's own host pages do).
                        if (Number(payload.bet_slots) >= 2 && opState.maxSlots < 2) {
                            opState.maxSlots = 2;
                            rules.configure({ twoBets: true });
                            let two = false;
                            try { two = localStorage.getItem(SLOTS_KEY) === '2'; } catch (_) {}
                            setTwo(two);
                        }
                        updateButtons();
                        break;
                    }

                    case 'bet_history_result':
                        sheet.receiveBets(payload);
                        break;

                    case 'balance_update':
                        setBalance(payload.balance);
                        break;

                    case 'bet_placed_result': {
                        const slot = slotPlaced(payload);
                        if (payload.success) {
                            slot.betId = payload.bet_id;
                            slot.betRoundUuid = payload.round_uuid;
                            slot.autoCashoutAt = payload.auto_cashout_at ?? slot.autoCashoutAt ?? null;
                            slot.autoCashoutFired = false;
                            slot.cashingOut = false;
                            const placed = `bet #${payload.bet_id} placed @ ${money(payload.amount)}`;
                            if (slot.autoBet) {
                                // Every bet placed while auto bet runs is one of its own.
                                slot.autoDone += 1;
                                if (slot.autoDone >= slot.autoRounds) {
                                    stopAutoBet(slot, `auto bet done (${slot.autoRounds}/${slot.autoRounds}) · ${placed}`, 'ok');
                                } else {
                                    renderAutoBet(slot);
                                    saveAutoBet(slot);
                                    setStatus(`${tag(slot)}auto bet ${slot.autoDone}/${slot.autoRounds} · ${placed}`, 'ok');
                                }
                            } else {
                                setStatus(tag(slot) + placed, 'ok');
                            }
                        } else {
                            slot.autoCashoutAt = null;
                            // Stop auto bet on failure — otherwise an insufficient-balance
                            // error (or any structural reject) would loop every round
                            // and spam the operator with rejected calls.
                            if (slot.autoBet) {
                                stopAutoBet(slot, `bet failed: ${payload.error || 'unknown'} — auto bet stopped`, 'err');
                            } else {
                                setStatus(`${tag(slot)}bet failed: ${payload.error || 'unknown'}`, 'err');
                            }
                        }
                        if (payload.player_balance) setBalance(payload.player_balance);
                        if (payload.success) {
                            sheet.betsChanged();
                            liveBets.mine(payload.bet_id);
                        }
                        updateButtons();
                        break;
                    }

                    case 'cashout_result': {
                        // The bet it names (one still held, or already shown cashed out); from a
                        // page that doesn't name it, the one being cashed out.
                        const slot = payload.bet_id !== undefined && payload.bet_id !== null
                            ? slotOfBet(payload.bet_id)
                            : slots.find((s) => s.cashingOut) ?? slots[0];
                        if (payload.player_balance) setBalance(payload.player_balance);
                        if (!slot) break; // a bet this page no longer holds
                        if (payload.success) {
                            cashedOut(slot, payload.bet_id ?? slot.betId, payload.multiplier, payload.payout, payload.is_auto ?? !slot.clicked);
                            break;
                        }
                        if (payload.error === 'round_crashed') {
                            setStatus(`${tag(slot)}round crashed — bet #${payload.bet_id} lost`, 'err');
                            showLast('loss', payload.error_message?.replace(/[^0-9.]/g, ''), null);
                            forgetBet(slot);
                        } else if (payload.error === 'already_in_flight') {
                            /* parent already cashing out — ignore the duplicate response */
                        } else if (payload.error === 'already_settled') {
                            // Settled already, by its auto cash-out most likely: Cash Out stays off,
                            // and the round's live bet list brings the result (round.bets below).
                            slot.cashingOut = true;
                            setStatus(`${tag(slot)}bet already settled`);
                        } else {
                            slot.cashingOut = false;
                            setStatus(`${tag(slot)}cashout failed: ${payload.error || 'unknown'}`, 'err');
                        }
                        sheet.betsChanged();
                        updateButtons();
                        break;
                    }

                    case 'auto_cashout_fired': {
                        // The bet it names, else the one with that threshold, else the first held.
                        const slot = slotOfBet(payload.bet_id)
                            ?? slots.find((s) => s.betId && s.autoCashoutAt !== null && Number(s.autoCashoutAt) === Number(payload.threshold))
                            ?? slots.find((s) => s.betId);
                        if (!slot) break;
                        slot.autoCashoutFired = true;
                        slot.cashingOut = true;
                        setStatus(`${tag(slot)}auto-cashout @ ${Number(payload.multiplier).toFixed(2)}×…`, 'ok');
                        slot.$co.disabled = true;
                        break;
                    }

                    case 'round_state':
                        opState.phase = payload.phase;
                        opState.roundUuid = payload.round_uuid;
                        // If a new round started while we were holding a bet from the previous one,
                        // forget it — that bet can't be cashed out against this round.
                        for (const slot of slots) {
                            if (slot.betRoundUuid && slot.betRoundUuid !== payload.round_uuid) forgetBet(slot);
                            if (payload.phase === 'betting') slot.won = null;
                        }
                        updateButtons();
                        break;
                }
            });

            // Locally drive button state off the same Echo events the page is
            // already subscribed to — so the panel feels instant even before
            // a parent broadcasts round_state. (Parent re-confirms below.)
            window.Echo?.channel(@json($roundChannel))
                .listen('.round.betting_opened', (e) => {
                    opState.phase = 'betting';
                    opState.roundUuid = e.round_uuid;
                    opState.placing = []; // a result that never came can't hold a slot past its round
                    for (const slot of slots) {
                        if (slot.betRoundUuid && slot.betRoundUuid !== e.round_uuid) forgetBet(slot);
                        slot.won = null; // the last round's result gives way to the new bet
                    }
                    updateButtons();

                    // AUTO BET: each armed slot without a bet for this round fires one.
                    // lastAutoBetRound de-dupes against the rare case where
                    // betting_opened arrives twice (e.g. reconnect).
                    for (const slot of slots) {
                        if (
                            slot.autoBet &&
                            !slot.$root.hidden &&
                            !slot.betId &&
                            opState.parentOrigin &&
                            slot.lastAutoBetRound !== e.round_uuid
                        ) {
                            slot.lastAutoBetRound = e.round_uuid;
                            triggerBet(slot);
                        }
                    }
                })
                .listen('.round.started',   () => { opState.phase = 'running'; opState.placing = []; updateButtons(); })
                .listen('.round.tick', (e) => {
                    const m = Number(e.multiplier);
                    for (const slot of slots) {
                        if (Number.isFinite(m)) slot.$coX.textContent = '@ ' + m.toFixed(2) + '×';

                        // Local auto-cashout safety net: if the parent is slow, the
                        // iframe also fires once when the threshold is crossed. The
                        // autoCashoutFired flag + parent's cashingOut guard ensure
                        // we don't double-send.
                        const at = slot.autoCashoutAt;
                        if (
                            slot.betId &&
                            opState.parentOrigin &&
                            !slot.autoCashoutFired &&
                            Number.isFinite(at) && at > 1 &&
                            m >= at
                        ) {
                            slot.autoCashoutFired = true;
                            slot.cashingOut = true;
                            setStatus(`${tag(slot)}auto-cashout @ ${m.toFixed(2)}×…`, 'ok');
                            slot.$co.disabled = true;
                            postToParent({ type: 'cashout', payload: { bet_id: slot.betId } });
                        }
                    }
                })
                .listen('.round.bets', (e) => {
                    // Every cash-out shows in the round's live bet list, ours too: the word of an
                    // auto cash-out when the host has none to give (Orange's own pages don't).
                    for (const row of e.bets ?? []) {
                        const slot = row.multiplier ? slots.find((s) => s.betId !== null && String(s.betId) === String(row.id)) : null;
                        if (slot) cashedOut(slot, row.id, row.multiplier, row.payout, !slot.clicked);
                    }
                })
                .listen('.round.crashed', () => {
                    opState.phase = 'crashed';
                    for (const slot of slots) slot.$coX.textContent = '—';
                    updateButtons();
                })
                .listen('.round.settled', () => {
                    opState.phase = 'waiting';
                    for (const slot of slots) {
                        forgetBet(slot);
                        slot.$coX.textContent = '—';
                    }
                    updateButtons();
                });

            function triggerBet(slot) {
                if (!opState.parentOrigin) {
                    setStatus('parent not ready', 'err');
                    return;
                }
                // Outside the player's limits: said here, rather than refused by the server.
                const problem = stakeProblem(slot.$stake.value);
                if (problem) {
                    if (slot.autoBet) stopAutoBet(slot, `${problem} — auto bet stopped`, 'err');
                    else setStatus(tag(slot) + problem, 'err');
                    return;
                }
                const autoAt = slot.autoOn ? Number(slot.$auto.value) : null;
                slot.autoCashoutAt = Number.isFinite(autoAt) && autoAt > 1 ? autoAt : null;
                slot.autoCashoutFired = false;
                slot.$bet.disabled = true;
                opState.placing.push(slot);
                setStatus(tag(slot) + (slot.autoBet ? `auto bet ${slot.autoDone + 1}/${slot.autoRounds} · placing…` : 'placing bet…'));
                const payload = {
                    amount: money(slot.$stake.value),
                    auto_cashout_at: slot.autoCashoutAt,
                    round_uuid: opState.roundUuid,
                };
                // Named only for a host that takes two bets, which echoes it in bet_placed_result.
                if (opState.maxSlots > 1) payload.slot = slot.index;
                postToParent({ type: 'place_bet', payload });
                updateButtons();
            }

            for (const slot of slots) {
                const { $stake, $auto } = slot;

                // Stake stepper — − / + move to the previous / next multiple of 5 (12 → 15 →
                // 20, 12 → 10 → 5); − stops at 5 (and leaves a typed stake under 5 as it is).
                // The player's limits still apply.
                function stepStake(direction) {
                    const cur = parseFloat($stake.value);
                    const next = stakeStep(Number.isFinite(cur) && cur > 0 ? cur : 0, direction);
                    $stake.value = clampStake(next).toFixed(2);
                    $stake.dispatchEvent(new Event('input', { bubbles: true }));
                }
                // Stakes have two decimals, like the money they come out of: typing
                // stops at the second decimal, and the field settles on "0.00" form,
                // within the player's limits (and says so when it had to move).
                $stake.addEventListener('input', () => {
                    const cut = $stake.value.replace(/^(\d*\.\d{2})\d+$/, '$1');
                    if (cut !== $stake.value) $stake.value = cut;
                });
                $stake.addEventListener('change', () => {
                    const v = parseFloat($stake.value);
                    if (!Number.isFinite(v) || v <= 0) return;
                    const allowed = clampStake(v);
                    if (allowed !== v) setStatus(tag(slot) + stakeProblem(v), 'err');
                    $stake.value = allowed.toFixed(2);
                });
                slot.$root.querySelector('.op-stake-down').addEventListener('click', () => stepStake(-1));
                slot.$root.querySelector('.op-stake-up').addEventListener('click', () => stepStake(+1));

                // Auto cash-out stepper — 0.10× per click. From off, the first click
                // turns it on at its last multiplier, or 1.50× (a sensible default);
                // − below 1.01 (the upstream minimum) just clamps to 1.01.
                function stepAuto(direction) {
                    const cur = parseFloat($auto.value);
                    let next;
                    if (!slot.autoOn || !Number.isFinite(cur) || cur <= 0) {
                        next = Number(slot.autoLast) || 1.50;
                    } else {
                        next = +(cur + direction * 0.10).toFixed(2);
                    }
                    if (next < 1.01) next = 1.01;
                    $auto.value = next.toFixed(2);
                    $auto.dispatchEvent(new Event('input', { bubbles: true }));
                }
                slot.$root.querySelector('.op-auto-down').addEventListener('click', () => stepAuto(-1));
                slot.$root.querySelector('.op-auto-up').addEventListener('click', () => stepAuto(+1));
                slot.$switch.addEventListener('click', () => setAutoCashout(slot, !slot.autoOn));
                // A multiplier in the field means on; an empty field, off.
                $auto.addEventListener('input', () => {
                    slot.autoOn = $auto.value !== '';
                    renderAutoCashout(slot);
                });
                $auto.addEventListener('change', () => {
                    const v = parseFloat($auto.value);
                    if (Number.isFinite(v)) $auto.value = Math.max(1.01, v).toFixed(2);
                });

                slot.$bet.addEventListener('click', () => triggerBet(slot));

                // AUTO BET: off, it asks for how many rounds (and shows the total); on, it stops.
                slot.$autoBet.addEventListener('click', () => {
                    if (slot.autoBet) stopAutoBet(slot, `auto bet stopped after ${slot.autoDone} of ${slot.autoRounds}`);
                    else openAutoBet(slot);
                });

                slot.$co.addEventListener('click', () => {
                    if (!slot.betId || !opState.parentOrigin) return;
                    slot.cashingOut = true;
                    slot.clicked = true;
                    slot.$co.disabled = true;
                    setStatus(tag(slot) + 'cashing out…');
                    postToParent({
                        type: 'cashout',
                        payload: { bet_id: slot.betId },
                    });
                    updateButtons();
                });

            }
            // Restore the first bet's AUTO BET; the second's is restored when that bet
            // opens (setTwo).
            restoreAutoBet(slots[0]);

            $home.addEventListener('click', () => postToParent({ type: 'back_to_lobby', payload: {} }));

            $addSlot.addEventListener('click', () => setTwo(true));
            $removeSlot.addEventListener('click', () => setTwo(false));
            document.getElementById('bets-btn').addEventListener('click', () => sheet.openBets());

            // Announce readiness so the parent can replay player_info / round_state.
            // We don't know the parent origin yet, so use '*' for this one beacon;
            // the parent must still verify *our* origin on its side.
            // home: the game has its own way back to a lobby (the menu's Home), so a lobby
            // hosting it can drop its bar.
            if (window.parent) {
                window.parent.postMessage({ type: 'embed_ready', payload: { home: true } }, '*');
            }

            setStatus('embed ready · waiting for player_info');
        })();
    </script>
</body>
</html>
