@php
    $socialSettings = \App\Models\Setting::values();
    $networks = [
        'facebook' => ['Facebook', $socialSettings['facebook_url'] ?? '', 'M14 21v-8h3l.5-4H14V7c0-1 .3-2 2-2h2V1h-3c-4 0-6 2-6 6v2H6v4h3v8z'],
        'instagram' => ['Instagram', $socialSettings['instagram_url'] ?? '', 'M7 2h10a5 5 0 0 1 5 5v10a5 5 0 0 1-5 5H7a5 5 0 0 1-5-5V7a5 5 0 0 1 5-5z M16 12a4 4 0 1 1-8 0 4 4 0 0 1 8 0 M17.5 6.5h.01'],
        'snapchat' => ['Snapchat', $socialSettings['snapchat_url'] ?? '', 'M12 2c-4 0-5 3-5 6v2l-2-1c-1 0-1 2 1 3l1 1c-1 3-3 4-5 4 0 1 2 2 4 2l1 2 3-1 2 1 2-1 3 1 1-2c2 0 4-1 4-2-2 0-4-1-5-4l1-1c2-1 2-3 1-3l-2 1V8c0-3-1-6-5-6z'],
        'tiktok' => ['TikTok', $socialSettings['tiktok_url'] ?? '', 'M14 3h3c.3 2.3 1.6 3.7 4 4v3a9 9 0 0 1-4-1.2V16a6 6 0 1 1-6-6v3a3 3 0 1 0 3 3z'],
    ];
@endphp
<div class="footer-socials">
    <strong>Suivez-nous</strong>
    <div class="footer-social-links">
        @foreach($networks as $network => [$label, $url, $path])
            @php($available = preg_match('~^https?://~i', $url))
            @if($available)
                <a class="social-{{ $network }}" href="{{ $url }}" target="_blank" rel="noopener noreferrer" aria-label="{{ $label }} (nouvel onglet)" title="{{ $label }}">
            @else
                <span class="social-unavailable social-{{ $network }}" role="img" aria-label="{{ $label }} — lien à venir" title="{{ $label }} — lien à venir">
            @endif
                <svg width="22" height="22" viewBox="0 0 24 24" aria-hidden="true" fill="{{ $network === 'facebook' ? 'currentColor' : 'none' }}" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="{{ $path }}"/></svg>
            @if($available)</a>@else</span>@endif
        @endforeach
    </div>
</div>
