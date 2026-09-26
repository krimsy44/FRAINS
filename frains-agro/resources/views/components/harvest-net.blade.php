<label>Entrée nette en stock<input type="text" data-harvest-net readonly aria-live="polite" value="{{ is_numeric(old('quantity', $quantity ?? '')) && is_numeric(old('loss_quantity', $loss ?? 0)) ? number_format(old('quantity', $quantity ?? 0) - old('loss_quantity', $loss ?? 0), 2, '.', '') : '' }}"></label>
<p>Entrée nette = quantité brute récoltée − pertes. Seule cette quantité alimente le stock, dans l’unité du produit.</p>
<script>
(() => {
    const form = document.currentScript.closest('form');
    const gross = form.elements.quantity;
    const loss = form.elements.loss_quantity;
    const net = form.querySelector('[data-harvest-net]');
    const update = () => {
        const valid = gross.value !== '' && loss.value !== '' && Number(loss.value) <= Number(gross.value);
        net.value = valid ? (Number(gross.value) - Number(loss.value)).toFixed(2) : '';
        loss.max = gross.value || '';
    };
    gross.addEventListener('input', update);
    loss.addEventListener('input', update);
    update();
})();
</script>
