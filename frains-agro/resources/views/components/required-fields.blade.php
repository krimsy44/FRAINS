<style>
    .required-marker { color: #b42318; font-weight: 700; }
    .required-fields-note { grid-column: 1 / -1; margin: 0 0 12px; font-size: 13px; }
</style>
<script>
(() => {
    const refresh = form => {
        form.querySelectorAll('[data-required-when]').forEach(field => {
            const source = form.elements.namedItem(field.dataset.requiredWhen);
            field.required = field.hasAttribute('data-required-nonempty')
                ? !!source?.value
                : source?.value === field.dataset.requiredValue;
        });
        form.querySelectorAll('input, select, textarea').forEach(field => {
            if (field.type === 'hidden') return;
            const label = field.labels?.[0];
            if (!label) return;
            let marker = label.querySelector('.required-marker');
            if (!field.required || field.disabled) {
                marker?.remove();
                return;
            }
            if (marker) return;
            marker = document.createElement('span');
            marker.className = 'required-marker';
            marker.textContent = ' *';
            marker.setAttribute('aria-hidden', 'true');
            const text = Array.from(label.childNodes).find(node => node.nodeType === Node.TEXT_NODE && node.textContent.trim());
            if (text) {
                const caption = document.createElement('span');
                text.replaceWith(caption);
                caption.append(text, marker);
            } else {
                const caption = label.querySelector('span:not(.required-marker)');
                (caption || label).append(marker);
            }
        });
        const hasRequired = !!form.querySelector(':required:not(:disabled):not([type="hidden"])');
        let note = form.querySelector('.required-fields-note');
        if (hasRequired && !note) {
            note = document.createElement('p');
            note.className = 'required-fields-note';
            note.textContent = 'Les champs marqués d’un astérisque (*) sont obligatoires.';
            form.prepend(note);
        }
        if (note) note.hidden = !hasRequired;
    };
    document.querySelectorAll('form').forEach(form => {
        refresh(form);
        form.addEventListener('change', () => refresh(form));
        form.addEventListener('reset', () => setTimeout(() => refresh(form), 0));
    });
})();
</script>
