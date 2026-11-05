import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['dialog'];
    pending = null;
    connect() { this.element.addEventListener('click', this.intercept); this.element.addEventListener('submit', this.rememberPosition); this.restorePosition(); }
    disconnect() { this.element.removeEventListener('click', this.intercept); this.element.removeEventListener('submit', this.rememberPosition); }
    intercept = (event) => { const button = event.target.closest('.episode-toggle[data-watch-toggle-catch-up] button'); if (!button || button.getAttribute('aria-pressed') === 'true') return; event.preventDefault(); event.stopPropagation(); this.pending = button.closest('form'); this.dialogTarget.showModal(); };
    confirm(event) { event.preventDefault(); }
    only() { this.send(false); }
    catchUp() { this.send(true); }
    cancel() { this.dialogTarget.close(); this.pending = null; }
    rememberPosition = (event) => { this.rememberForm(event.target); }
    rememberForm(form) { const anchor = form.querySelector('[name="return_anchor"]')?.value; if (anchor) sessionStorage.setItem('zunatrack:return-anchor', anchor); }
    restorePosition() { const anchor = sessionStorage.getItem('zunatrack:return-anchor'); if (!anchor) return; sessionStorage.removeItem('zunatrack:return-anchor'); if (new URLSearchParams(window.location.search).get('season_completed') === '1') return; requestAnimationFrame(() => document.getElementById(anchor)?.scrollIntoView({ block: 'center' })); }
    send(catchUp) { this.pending.querySelector('[name="catch_up"]').value = catchUp ? '1' : '0'; this.rememberForm(this.pending); this.dialogTarget.close(); this.pending.submit(); }
}
