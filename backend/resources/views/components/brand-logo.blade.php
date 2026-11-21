@props(['light' => false])

<div class="flex items-center gap-3">
    {{-- FreightPath logo: white truck and amber route on teal. --}}
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" class="h-11 w-11 shrink-0" role="img"
        aria-label="FreightPath logo">
        <rect width="512" height="512" rx="112" fill="#0B6E6E" />
        <g transform="translate(-43.6 -16.0) scale(1.15)">
            <rect x="85" y="120" width="215" height="140" rx="14" fill="#fff" />
            <path d="M312 160 H378 Q395 160 405 175 L430 218 Q436 228 436 240 V260 H312 Z" fill="#fff" />
            <path d="M330 178 H372 Q380 178 385 186 L402 216 H330 Z" fill="#FFB020" />
            <circle cx="165" cy="268" r="38" fill="#fff" stroke="#0B6E6E" stroke-width="9" />
            <circle cx="165" cy="268" r="17" fill="#0B6E6E" />
            <circle cx="370" cy="268" r="38" fill="#fff" stroke="#0B6E6E" stroke-width="9" />
            <circle cx="370" cy="268" r="17" fill="#0B6E6E" />
            <path d="M95 342 H395" stroke="#FFB020" stroke-width="8" stroke-linecap="round" stroke-dasharray="2 20"
                fill="none" />
            <circle cx="420" cy="342" r="11" fill="#FFB020" />
        </g>
    </svg>
    <span class="text-2xl font-bold tracking-tight {{ $light ? 'text-white' : 'text-[#0E2F30]' }}">Freight<span
            class="{{ $light ? 'text-accent-400' : 'text-brand-600' }}">Path</span></span>
</div>
