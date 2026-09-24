(() => {
    const fingerprint = doc => {
        const content = doc.querySelector('main')?.cloneNode(true);
        if (!content) return null;
        content.querySelectorAll('input[name="_token"], script').forEach(node => node.remove());
        // The previous URL can change after an unrelated request in the same session.
        content.querySelectorAll('.catalog-back-link a').forEach(link => link.removeAttribute('href'));
        const styles = Array.from(doc.querySelectorAll('link[rel="stylesheet"]'), link => link.getAttribute('href'));
        return JSON.stringify([content.innerHTML, doc.querySelector('.brand')?.innerHTML,
            doc.querySelector('footer')?.innerHTML, doc.title, styles]);
    };

    // Capture server markup before form annotations and other browser enhancements.
    const initial = fingerprint(document);
    if (!initial) return;
    const pageUrl = location.href;
    const dirtyForms = new Set();
    let busy = false;
    let changed = false;
    let navigating = false;

    const interacting = () => dirtyForms.size > 0
        || document.activeElement?.matches('input, select, textarea, [contenteditable="true"]')
        || Array.from(document.querySelectorAll('video, audio')).some(media => !media.paused && !media.ended);

    const refresh = () => {
        if (changed && !document.hidden && !interacting() && !navigating) {
            navigating = true;
            location.reload();
        }
    };
    document.addEventListener('input', event => {
        if (event.target.form) dirtyForms.add(event.target.form);
    });
    document.addEventListener('change', event => {
        if (event.target.form) dirtyForms.add(event.target.form);
    });
    document.addEventListener('reset', event => {
        dirtyForms.delete(event.target);
        setTimeout(refresh, 0);
    });
    document.addEventListener('submit', () => { navigating = true; });
    window.addEventListener('pageshow', () => { navigating = false; });

    const check = async () => {
        if (busy || document.hidden || navigating || !navigator.onLine) return;
        busy = true;
        const controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), 8000);
        try {
            const response = await fetch(pageUrl, {
                cache: 'no-store', credentials: 'same-origin', signal: controller.signal,
                headers: { 'X-Visitor-Update': '1', 'X-Requested-With': 'XMLHttpRequest' },
            });
            // A removed/deactivated product must no longer appear purchasable.
            if (response.status === 404 || response.status === 410) {
                changed = true;
            } else if (response.ok && !response.redirected && response.headers.get('content-type')?.includes('text/html')) {
                const next = new DOMParser().parseFromString(await response.text(), 'text/html');
                const nextFingerprint = fingerprint(next);
                changed = nextFingerprint !== null && nextFingerprint !== initial;
            }
            refresh();
        } catch {
            // Keep the current page usable during a temporary network/server failure.
        } finally {
            clearTimeout(timeout);
            busy = false;
        }
    };
    setInterval(check, 10000);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) check(); });
    window.addEventListener('online', check);
})();
