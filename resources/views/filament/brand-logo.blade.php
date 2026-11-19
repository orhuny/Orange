{{-- The panel's brand: the crown mark and the "Majestic Games" wordmark. Inline styles only:
     Filament's stylesheet has no utility classes for app views. The words take the text colour,
     so one logo serves light and dark mode. --}}
<span class="mg-brand" style="display:inline-flex;align-items:center;gap:.625rem;height:100%;color:inherit;white-space:nowrap;">
    <svg viewBox="0 0 32 32" style="height:100%;width:auto;flex:none;" aria-hidden="true">
        <defs>
            <linearGradient id="mg-mark-bg" x1="0" y1="0" x2="1" y2="1">
                <stop offset="0" stop-color="#4f46e5"/>
                <stop offset="1" stop-color="#312e81"/>
            </linearGradient>
            <linearGradient id="mg-mark-gold" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0" stop-color="#fde68a"/>
                <stop offset="1" stop-color="#f59e0b"/>
            </linearGradient>
        </defs>
        <rect width="32" height="32" rx="8" fill="url(#mg-mark-bg)"/>
        <path d="M8.5 20.5V12.2l4.2 3.6L16 9.9l3.3 5.9 4.2-3.6v8.3z" fill="url(#mg-mark-gold)"/>
        <rect x="8.5" y="21.8" width="15" height="2.4" rx="1.2" fill="url(#mg-mark-gold)"/>
        <circle cx="8.5" cy="11.4" r="1.4" fill="#fde68a"/>
        <circle cx="16" cy="8.9" r="1.5" fill="#fde68a"/>
        <circle cx="23.5" cy="11.4" r="1.4" fill="#fde68a"/>
    </svg>
    <span style="display:inline-flex;align-items:baseline;gap:.3rem;font-size:1.0625rem;line-height:1;letter-spacing:-.01em;">
        <span style="font-weight:700;">Majestic</span>
        <span style="font-weight:500;opacity:.62;">Games</span>
    </span>
</span>
