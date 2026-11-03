import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['dialog'];
    pending = null;
    connect() { this.element.addEventListener('click', this.intercept); }
    disconnect() { this.element.removeEventListener('click', this.intercept); }
    intercept = (event) => { const button = event.target.closest('.episode-toggle button'); if (!button || button.getAttribute('aria-pressed') === 'true') return; event.preventDefault(); event.stopPropagation(); this.pending = button.closest('form'); this.dialogTarget.showModal(); };
    confirm(event) { event.preventDefault(); }
    only() { this.send(false); }
    catchUp() { this.send(true); }
    cancel() { this.dialogTarget.close(); this.pending = null; }
    send(catchUp) { this.pending.querySelector('[name="catch_up"]').value = catchUp ? '1' : '0'; this.dialogTarget.close(); this.pending.submit(); }
}
