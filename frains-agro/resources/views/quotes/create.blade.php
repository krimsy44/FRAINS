<x-layouts.app title="Demande de devis">
<section class="commerce">
    <a class="quote-back-link" href="{{ route('products.index') }}">
        <span aria-hidden="true">&larr;</span> Retour au catalogue
    </a>
    @php
        $companyPhone = preg_replace('/\D+/', '', $settings['phone'] ?? '779890101');
        $callPhone = str_starts_with($companyPhone, '221') ? '+'.$companyPhone : '+221'.$companyPhone;
        $whatsappPhone = ltrim($callPhone, '+');
        $quoteMessage = rawurlencode('Bonjour FRAINS Agro, je souhaite avoir des informations pour ma demande de devis.');
    @endphp

    <h1>Demander un devis en gros</h1>

    <div class="quote-contact-card">
        <div>
            <span class="quote-contact-label">Contact entreprise</span>
            <h2>Besoin d'aide pour votre devis ?</h2>
            <p>Vous pouvez contacter directement FRAINS Agro avant d'envoyer votre demande.</p>
            @if(! empty($settings['address']))<small>{{ $settings['address'] }}</small>@endif
        </div>
        <div class="quote-contact-actions quote-contact-actions-icon">
            <a class="quote-contact-button whatsapp whatsapp-icon-only" aria-label="Contacter FRAINS Agro sur WhatsApp" title="Contacter sur WhatsApp" href="https://wa.me/{{ $whatsappPhone }}?text={{ $quoteMessage }}" target="_blank" rel="noopener">
                <span class="quote-contact-icon whatsapp-logo" aria-hidden="true">
                    <svg viewBox="0 0 32 32" role="img" focusable="false"><path d="M16.04 3.2A12.7 12.7 0 0 0 5.1 22.35L3.2 29l6.8-1.78a12.66 12.66 0 0 0 6.03 1.54h.01A12.78 12.78 0 0 0 28.8 16.02 12.76 12.76 0 0 0 16.04 3.2Zm0 23.4h-.01a10.48 10.48 0 0 1-5.34-1.46l-.38-.23-4.04 1.06 1.08-3.94-.25-.4a10.53 10.53 0 1 1 8.94 4.97Zm5.78-7.86c-.32-.16-1.87-.92-2.16-1.03-.29-.11-.5-.16-.71.16-.21.32-.81 1.03-1 1.24-.18.21-.37.24-.69.08-.32-.16-1.34-.49-2.55-1.57-.94-.84-1.58-1.88-1.76-2.2-.18-.32-.02-.49.14-.65.14-.14.32-.37.48-.55.16-.18.21-.32.32-.53.11-.21.05-.4-.03-.56-.08-.16-.71-1.71-.97-2.35-.26-.62-.52-.54-.71-.55h-.61c-.21 0-.56.08-.85.4-.29.32-1.11 1.08-1.11 2.64 0 1.56 1.13 3.06 1.29 3.27.16.21 2.23 3.4 5.4 4.77.75.32 1.34.51 1.8.66.76.24 1.45.21 2 .13.61-.09 1.87-.76 2.13-1.5.26-.74.26-1.37.18-1.5-.08-.13-.29-.21-.61-.37Z"/></svg>
                </span>
            </a>
        </div>
    </div>

    <form class="panel commerce-form" method="POST" action="{{ route('quotes.store') }}">
        @csrf
        @for($i=0;$i<5;$i++)
            <fieldset>
                <legend>Produit {{ $i+1 }} {{ $i?'(facultatif)':'' }}</legend>
                <label>Produit
                    <select name="items[{{ $i }}][product_id]">
                        <option value="">Choisir</option>
                        @foreach($products as $product)
                            <option value="{{ $product->id }}" @selected(old('items.'.$i.'.product_id',$i===0?request('product'):null)==$product->id)>{{ $product->name }} - {{ $product->unit }}</option>
                        @endforeach
                    </select>
                </label>
                <label>Quantite<input type="number" step="0.01" min="0.01" name="items[{{ $i }}][quantity]" data-required-when="items[{{ $i }}][product_id]" data-required-nonempty value="{{ old('items.'.$i.'.quantity') }}"></label>
                <label>Conditionnement souhaite<input name="items[{{ $i }}][packaging]" value="{{ old('items.'.$i.'.packaging') }}" placeholder="Ex. caisses"></label>
            </fieldset>
        @endfor
        <label>Zone<select name="delivery_zone_id" required>@foreach($zones as $zone)<option value="{{ $zone->id }}" @selected(old('delivery_zone_id')==$zone->id)>{{ $zone->name }}</option>@endforeach</select></label>
        <label>Adresse<textarea name="delivery_address" required>{{ old('delivery_address',auth()->user()->address) }}</textarea></label>
        <label>Date souhaitee<input type="date" name="requested_date" min="{{ today()->toDateString() }}" value="{{ old('requested_date') }}" required></label>
        <label>Precisions<textarea name="notes">{{ old('notes') }}</textarea></label>
        <button class="button">Envoyer la demande</button>
    </form>
</section>
</x-layouts.app>
