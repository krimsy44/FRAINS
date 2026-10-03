@props(['publication', 'compact' => false])
@if($publication->image)
    @if(($publication->media_type ?? 'image') === 'video')
        <video controls playsinline preload="metadata" aria-label="{{ $publication->title }}" style="display:block;width:100%;max-height:{{ $compact ? '240px' : '560px' }};border-radius:10px;background:#113426">
            <source src="{{ asset('storage/'.$publication->image) }}" type="{{ strtolower(pathinfo($publication->image, PATHINFO_EXTENSION)) === 'webm' ? 'video/webm' : 'video/mp4' }}">
            Votre navigateur ne peut pas lire cette vidéo.
        </video>
    @else
        <img src="{{ asset('storage/'.$publication->image) }}" alt="{{ $publication->title }}" loading="lazy" style="max-width:100%;{{ $compact ? 'width:100%;height:180px;object-fit:cover' : 'height:auto' }}">
    @endif
@endif
