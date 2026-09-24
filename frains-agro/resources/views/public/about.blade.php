<div class="gie-intro"><p class="eyebrow">LE GIE · FRAINS AGRO</p><p>Fraternité Agricole Les Inséparables : production maraîchère et commercialisation de produits agricoles au Sénégal.</p></div>
@foreach(['objectives' => 'Notre objectif', 'history' => 'Notre historique'] as $key => $heading)
<article class="panel gie-story">
    <div><h2>{{ $heading }}</h2>
        <p class="gie-text">{{ $settings[$key] ?? ($key === 'objectives' ? 'Développer les activités de production maraîchère et de commercialisation de produits agricoles du GIE au Sénégal.' : '') }}</p>
        @if($key === 'history' && empty($settings[$key]))<p>L’historique officiel du GIE sera publié prochainement : sa création, ses fondateurs et les étapes de son développement.</p>@endif
    </div>
    @if(!empty($settings[$key.'_image_path']))
    <figure><img src="{{ asset('storage/'.$settings[$key.'_image_path']) }}" alt="{{ $settings[$key.'_image_caption'] ?? $heading.' — FRAINS Agro' }}" loading="lazy"><figcaption>{{ $settings[$key.'_image_caption'] ?? $heading.' — FRAINS Agro' }}</figcaption></figure>
    @else
    <div class="gie-photo-pending"><strong>{{ $key === 'history' ? 'Le GIE au fil du temps' : 'Nos activités en images' }}</strong><p>Une photographie du GIE sera ajoutée prochainement.</p></div>
    @endif
</article>
@endforeach
@foreach(['mission'=>'Notre mission','vision'=>'Notre vision','values'=>'Nos valeurs','production_zones'=>'Zones de production','partners'=>'Nos partenaires'] as $key=>$label)
    @if(!empty($settings[$key]))<article class="panel"><h2>{{ $label }}</h2><p class="gie-text">{{ $settings[$key] }}</p></article>@endif
@endforeach
<p><a class="button" href="{{ route('public.page', 'contact') }}">Contacter le GIE</a></p>
