<div class="gauge gauge-pressure" title="Pressure" data-level="ok">
                    <svg width="44" height="44" viewBox="0 0 44 44" aria-hidden="true">
                        <circle class="dial-face" cx="22" cy="22" r="21"/>
                        <circle class="dial-track" cx="22" cy="22" r="17" pathLength="100" transform="rotate(135 22 22)"/>
                        <circle id="pressureArc" class="dial-arc" cx="22" cy="22" r="17" pathLength="100" transform="rotate(135 22 22)"/>
                        @for ($tick = 0; $tick <= 9; $tick++)
                        <line class="dial-tick{{ $tick >= 7 ? ' hot' : '' }}" x1="22" y1="8" x2="22" y2="{{ $tick % 3 === 0 ? 11.5 : 10 }}" transform="rotate({{ -135 + $tick * 30 }} 22 22)"/>
                        @endfor
                        <g class="dial-wobble"><line id="pressureNeedle" class="dial-needle" x1="22" y1="22" x2="22" y2="9"/></g>
                        <circle class="dial-hub" cx="22" cy="22" r="2.6"/>
                    </svg>
                    <div class="gauge-text">
                        <div class="gauge-label">Pressure</div>
                        <div class="gauge-value"><span id="pressureValue">0</span><small>bar</small></div>
                    </div>
                </div>

