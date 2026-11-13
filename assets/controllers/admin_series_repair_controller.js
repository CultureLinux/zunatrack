import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['dialog', 'start', 'status', 'progress', 'items'];
    static values = { url: String, token: String };

    open() { this.dialogTarget.showModal(); }
    backdrop(event) { if (event.target === this.dialogTarget) this.dialogTarget.close(); }
    stop() { this.running = false; }

    async start() {
        this.running = true;
        this.startTarget.disabled = true;
        this.itemsTarget.replaceChildren();
        this.progressTarget.value = 0;
        let after = 0;
        let processed = 0;
        let retries = 0;
        while (this.running) {
            try {
                const response = await fetch(this.urlValue, { method: 'POST', headers: { Accept: 'application/json' }, body: new URLSearchParams({ _token: this.tokenValue, after: String(after) }) });
                if (!response.ok) throw new Error(`Erreur HTTP ${response.status}`);
                const data = await response.json();
                if (data.retry) {
                    if (++retries > 3) { this.statusTarget.textContent = data.error; break; }
                    this.statusTarget.textContent = `${data.error} Nouvelle tentative dans 3 secondes…`;
                    await new Promise(resolve => window.setTimeout(resolve, 3000));
                    continue;
                }
                if (data.done) {
                    this.statusTarget.textContent = `Vérification terminée : ${processed} série(s) traitée(s).`;
                    break;
                }
                retries = 0;
                after = data.after;
                processed++;
                this.progressTarget.max = Math.max(data.total, 1);
                this.progressTarget.value = processed;
                this.statusTarget.textContent = `${processed}/${data.total} séries vérifiées`;
                const item = document.createElement('li');
                item.textContent = `${data.title} · ${data.reason}`;
                this.itemsTarget.prepend(item);
            } catch (error) {
                this.statusTarget.textContent = `Vérification interrompue : ${error.message}`;
                break;
            }
        }
        this.running = false;
        this.startTarget.disabled = false;
    }
}
