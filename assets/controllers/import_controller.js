import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['dialog', 'form', 'file', 'status', 'items'];
    static values = { uploadUrl: String, replayToken: String };
    open() { this.dialogTarget.showModal(); }
    backdrop(event) { if (event.target === this.dialogTarget) this.dialogTarget.close(); }
    async upload(event) {
        event.preventDefault(); this.statusTarget.textContent = 'Préparation de l’import…';
        const response = await fetch(this.uploadUrlValue, { method: 'POST', body: new FormData(this.formTarget), headers: { Accept: 'application/json' } });
        const data = await response.json(); if (!response.ok) { this.statusTarget.textContent = data.error || 'Import impossible.'; return; }
        this.statusUrl = data.statusUrl; this.poll();
    }
    async poll() {
        const response = await fetch(this.statusUrl, { headers: { Accept: 'application/json' } }); if (!response.ok) { this.statusTarget.textContent = 'Impossible de suivre cet import.'; return; }
        const data = await response.json(); this.statusTarget.textContent = `${data.processed}/${data.total} séries traitées — ${data.imported} importées, ${data.merged} fusionnées, ${data.unresolved} à valider.`;
        this.itemsTarget.replaceChildren(...data.items.map((item) => { const row=document.createElement('li'); if(item.coverUrl){const image=document.createElement('img');image.src=item.coverUrl;image.alt='';row.append(image);} const text=document.createElement('span');text.textContent=`${item.title} · ${item.state}${item.reason ? ` — ${item.reason}` : ''}`;row.append(text);if(item.candidates.length){const select=document.createElement('select');item.candidates.forEach(candidate=>{const option=document.createElement('option');option.value=candidate.id;option.textContent=candidate.title;select.append(option)});const button=document.createElement('button');button.type='button';button.textContent='Utiliser';button.onclick=async()=>{await fetch(item.replayUrl,{method:'POST',headers:{Accept:'application/json'},body:new URLSearchParams({_token:this.replayTokenValue,candidate:select.value})});this.poll()};row.append(select,button);}return row;}));
        if (data.state !== 'done') window.setTimeout(() => this.poll(), 1200);
    }
}
