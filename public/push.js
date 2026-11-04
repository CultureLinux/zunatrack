document.querySelectorAll('[data-push-subscription]').forEach((root) => {
    const status = root.querySelector('[data-push-status]');
    const enable = root.querySelector('[data-push-enable]');
    const disable = root.querySelector('[data-push-disable]');
    const test = root.querySelector('[data-push-test]');
    const token = document.querySelector('meta[name="csrf-push-subscription"]')?.content;
    const send = (method, subscription) => fetch(root.dataset.url, {method, headers: {'Content-Type': 'application/json', 'X-CSRF-Token': token}, body: JSON.stringify(subscription.toJSON())});
    if (!('serviceWorker' in navigator) || !('PushManager' in window)) { status.textContent = 'Notifications indisponibles sur cet appareil.'; enable.disabled = true; return; }
    navigator.serviceWorker.register('/sw.js').then(async (registration) => {
        const refresh = async () => { const subscription = await registration.pushManager.getSubscription(); enable.hidden = !!subscription; disable.hidden = !subscription; test.hidden = !subscription; return subscription; };
        refresh();
        enable.onclick = async () => { if (await Notification.requestPermission() !== 'granted') { status.textContent = 'Autorisation refusée.'; return; } const subscription = await registration.pushManager.subscribe({userVisibleOnly: true, applicationServerKey: Uint8Array.from(atob(root.dataset.publicKey.replace(/-/g, '+').replace(/_/g, '/')), c => c.charCodeAt(0))}); await send('POST', subscription); await refresh(); status.textContent = 'Notifications activées.'; };
        disable.onclick = async () => { const subscription = await registration.pushManager.getSubscription(); if (subscription) { await send('DELETE', subscription); await subscription.unsubscribe(); } await refresh(); status.textContent = 'Notifications désactivées.'; };
        test.onclick = async () => { await registration.showNotification('ZunaTrack', {body: 'La notification de test fonctionne sur cet appareil.', tag: 'zunatrack-test'}); status.textContent = 'Notification de test affichée.'; };
    });
});
